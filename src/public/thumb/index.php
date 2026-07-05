<?php
// ============================================================
//  HOSHIMI — Thumbnail on-demand
//  GET /thumb/?path=/media/animes/.../episode.mkv
//  Génère la miniature via FFmpeg si elle n'existe pas encore,
//  la met en cache, puis sert l'image.
// ============================================================

declare(strict_types=1);

$animesPath = $_ENV['ANIMES_PATH'] ?? '/media/animes';
$imagesPath = $_ENV['IMAGES_PATH'] ?? '/var/www/html/public/images';
$thumbDir   = rtrim($imagesPath, '/') . '/thumbnails';

$path      = $_GET['path'] ?? '';
$maxWidth  = max(80, min(480, (int)($_GET['w'] ?? 0)));

if ($path === '') {
    http_response_code(400);
    exit;
}

// Sécurité : le chemin doit être dans ANIMES_PATH
$realPath   = realpath($path);
$realAnimes = realpath($animesPath);

if (!$realPath || !$realAnimes || !str_starts_with($realPath, $realAnimes . '/')) {
    http_response_code(403);
    exit;
}

$hash      = md5($realPath);
$thumbFile = $thumbDir . '/' . $hash . '.jpg';

// Génère le thumbnail original s'il n'existe pas encore
if (!file_exists($thumbFile)) {
    if (!is_dir($thumbDir)) {
        mkdir($thumbDir, 0755, true);
    }

    $safeIn  = escapeshellarg($realPath);
    $safeOut = escapeshellarg($thumbFile);

    $duration = (float) shell_exec("ffprobe -v error -show_entries format=duration -of default=noprint_wrappers=1:nokey=1 $safeIn 2>/dev/null");
    $seekTime = $duration > 60 ? (int)($duration * 0.3) : 30;

    shell_exec("ffmpeg -ss $seekTime -i $safeIn -vframes 1 -vf \"scale=480:270:force_original_aspect_ratio=decrease,pad=480:270:(ow-iw)/2:(oh-ih)/2\" -q:v 5 $safeOut 2>/dev/null");

    if (!file_exists($thumbFile)) {
        http_response_code(404);
        exit;
    }

    $indexFile = $thumbDir . '/index.json';
    $index = file_exists($indexFile) ? (json_decode(file_get_contents($indexFile), true) ?? []) : [];
    $index[$hash] = '/images/thumbnails/' . $hash . '.jpg';
    file_put_contents($indexFile, json_encode($index));

    if ($duration > 0) {
        $durFile = $thumbDir . '/durations.json';
        $durs = file_exists($durFile) ? (json_decode(file_get_contents($durFile), true) ?? []) : [];
        if (!isset($durs[$hash])) {
            $h = (int)($duration / 3600);
            $m = (int)(($duration % 3600) / 60);
            $s = (int)($duration % 60);
            $durs[$hash] = $h > 0
                ? sprintf('%d:%02d:%02d', $h, $m, $s)
                : sprintf('%d:%02d', $m, $s);
            file_put_contents($durFile, json_encode($durs));
        }
    }
}

// Redimensionnement à la volée si ?w= demandé
if ($maxWidth > 0 && $maxWidth < 480) {
    $smallKey  = $hash . '_' . $maxWidth;
    $smallFile = $thumbDir . '/' . md5($smallKey) . '.jpg';

    if (!file_exists($smallFile)) {
        $im = @imagecreatefromjpeg($thumbFile);
        if ($im) {
            $origW = imagesx($im);
            $origH = imagesy($im);
            $newH  = (int)round($origH * $maxWidth / $origW);
            $out   = imagecreatetruecolor($maxWidth, $newH);
            imagefill($out, 0, 0, imagecolorallocate($out, 0, 0, 0));
            imagecopyresampled($out, $im, 0, 0, 0, 0, $maxWidth, $newH, $origW, $origH);
            imagejpeg($out, $smallFile, 80);
            imagedestroy($im);
            imagedestroy($out);
        }
    }

    if (file_exists($smallFile)) {
        header('Content-Type: image/jpeg');
        header('Cache-Control: public, max-age=86400');
        readfile($smallFile);
        exit;
    }
}

// Sert le thumbnail pleine taille
header('Content-Type: image/jpeg');
header('Cache-Control: public, max-age=86400');
readfile($thumbFile);
exit;

