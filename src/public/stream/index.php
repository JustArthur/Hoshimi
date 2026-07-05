<?php
// ============================================================
//  HOSHIMI — Stream vidéo
//  MP4/WebM  : range requests directs (seek natif)
//  MKV/AVI   : transcodage FFmpeg à la volée
//              (audio → AAC, vidéo copiée) → fMP4 streamable
// ============================================================

declare(strict_types=1);

$allowedRoots = [
    $_ENV['ANIMES_PATH']  ?? '/media/animes',
    $_ENV['FILMS_PATH']   ?? '/media/films',
    $_ENV['SERIES_PATH']  ?? '/media/series',
];

$requestedPath = urldecode($_GET['path'] ?? '');

if (empty($requestedPath)) { http_response_code(400); exit; }

$realPath = realpath($requestedPath);

if ($realPath === false || !is_file($realPath)) { http_response_code(404); exit; }

$allowed = false;
foreach ($allowedRoots as $root) {
    $realRoot = realpath($root);
    if ($realRoot && str_starts_with($realPath, $realRoot . DIRECTORY_SEPARATOR)) {
        $allowed = true;
        break;
    }
}
if (!$allowed) { http_response_code(403); exit; }

$ext = strtolower(pathinfo($realPath, PATHINFO_EXTENSION));
if (!in_array($ext, ['mp4', 'mkv', 'webm', 'avi', 'm4v'], true)) {
    http_response_code(415);
    exit;
}

// ----------------------------------------------------------------
//  MP4 / WebM / M4V  →  Range requests directs
// ----------------------------------------------------------------
if (in_array($ext, ['mp4', 'webm', 'm4v'], true)) {

    $fileSize = filesize($realPath);
    $start    = 0;
    $end      = $fileSize - 1;

    if (isset($_SERVER['HTTP_RANGE'])) {
        preg_match('/bytes=(\d+)-(\d*)/', $_SERVER['HTTP_RANGE'], $m);
        $start = (int)($m[1] ?? 0);
        $end   = isset($m[2]) && $m[2] !== '' ? (int)$m[2] : $fileSize - 1;

        if ($start > $end || $start >= $fileSize) {
            http_response_code(416);
            header("Content-Range: bytes */$fileSize");
            exit;
        }
        http_response_code(206);
        header("Content-Range: bytes $start-$end/$fileSize");
    } else {
        http_response_code(200);
    }

    $mimeMap = ['mp4' => 'video/mp4', 'webm' => 'video/webm', 'm4v' => 'video/mp4'];
    header('Content-Type: '   . $mimeMap[$ext]);
    header('Content-Length: ' . ($end - $start + 1));
    header('Accept-Ranges: bytes');
    header('Cache-Control: no-cache');

    $fp = fopen($realPath, 'rb');
    fseek($fp, $start);
    $remaining = $end - $start + 1;
    while (!feof($fp) && $remaining > 0) {
        echo fread($fp, min(256 * 1024, $remaining));
        $remaining -= 256 * 1024;
        flush();
    }
    fclose($fp);
    exit;
}

// ----------------------------------------------------------------
//  MKV / AVI  →  FFmpeg transcodage à la volée
//
//  Stratégie :
//  - Vidéo : copiée telle quelle (pas de re-encodage, CPU faible)
//  - Audio : converti en AAC 192k (compatible tous navigateurs)
//  - Conteneur : fMP4 fragmenté (streamable sans index complet)
//  - Seek : le Range header bytes → estimation du temps via ffprobe
// ----------------------------------------------------------------

// Durée totale pour estimer la position de seek
function probeDuration(string $path): float
{
    $cmd = 'ffprobe -v error -show_entries format=duration'
         . ' -of default=noprint_wrappers=1:nokey=1 '
         . escapeshellarg($path) . ' 2>/dev/null';
    return max(0.0, (float)trim((string)shell_exec($cmd)));
}

$seekSec  = 0.0;
$fileSize = filesize($realPath);

if (isset($_SERVER['HTTP_RANGE']) && $fileSize > 0) {
    preg_match('/bytes=(\d+)-/', $_SERVER['HTTP_RANGE'], $m);
    $byteOffset = (int)($m[1] ?? 0);

    if ($byteOffset > 0) {
        $duration = probeDuration($realPath);
        if ($duration > 0) {
            // Estimation linéaire octets → secondes
            $seekSec = ($byteOffset / $fileSize) * $duration;
        }
    }
}

// Sélection des meilleures pistes : première vidéo + meilleure audio
// -map 0:v:0 -map 0:a:0  →  piste vidéo 0, piste audio 0 (priorité)
$ssArg = $seekSec > 0 ? sprintf('-ss %.3f', $seekSec) : '';

$cmd = sprintf(
    'ffmpeg -hide_banner -loglevel error'
    . ' %s'                                    // seek avant l'input (rapide)
    . ' -i %s'
    . ' -map 0:v:0 -map 0:a:0'                // 1re piste vidéo + audio
    . ' -c:v copy'                             // vidéo sans re-encodage
    . ' -c:a aac -b:a 192k -ac 2'             // audio → AAC stéréo
    . ' -f mp4'
    . ' -movflags frag_keyframe+empty_moov+default_base_moof'
    . ' pipe:1 2>/dev/null',
    $ssArg,
    escapeshellarg($realPath)
);

// Vider tout buffer PHP avant de streamer
while (ob_get_level()) ob_end_clean();

http_response_code(200);
header('Content-Type: video/mp4');
header('Accept-Ranges: none');   // pas de range natif sur flux transcodé
header('Cache-Control: no-cache');
header('X-Accel-Buffering: no'); // désactive le buffer Nginx pour le streaming

$proc = popen($cmd, 'r');
if ($proc === false) {
    http_response_code(500);
    exit;
}

while (!feof($proc)) {
    $chunk = fread($proc, 256 * 1024);
    if ($chunk === false || $chunk === '') break;
    echo $chunk;
    flush();
}

pclose($proc);
