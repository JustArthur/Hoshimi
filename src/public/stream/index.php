<?php
// ============================================================
//  HOSHIMI — Stream vidéo
//  URL : /stream/?path=/media/animes/Anime/Season 01/ep.mp4
//  Support des Range Requests (lecture partielle, seek)
// ============================================================

declare(strict_types=1);

$requestedPath = urldecode($_GET['path'] ?? '');

$allowedRoots = [
    $_ENV['ANIMES_PATH'] ?? '/media/animes',
];

$requestedPath = urldecode($_GET['path'] ?? '');

if (empty($requestedPath)) {
    http_response_code(400);
    exit;
}

$realPath = realpath($requestedPath);

if ($realPath === false || !is_file($realPath)) {
    http_response_code(404);
    exit;
}

// Vérification sécurité — fichier dans un dossier autorisé
$allowed = false;
foreach ($allowedRoots as $root) {
    $realRoot = realpath($root);
    if ($realRoot && str_starts_with($realPath, $realRoot . DIRECTORY_SEPARATOR)) {
        $allowed = true;
        break;
    }
}

if (!$allowed) {
    http_response_code(403);
    exit;
}

// Type MIME
$ext      = strtolower(pathinfo($realPath, PATHINFO_EXTENSION));
$mimeMap  = [
    'mp4'  => 'video/mp4',
    'mkv'  => 'video/x-matroska',
    'webm' => 'video/webm',
    'avi'  => 'video/x-msvideo',
];

if (!isset($mimeMap[$ext])) {
    http_response_code(415);
    exit;
}

$fileSize = filesize($realPath);
$mimeType = $mimeMap[$ext];

// ----------------------------------------------------------------
//  Range Requests — indispensable pour le seek dans Video.js
// ----------------------------------------------------------------
$start = 0;
$end   = $fileSize - 1;

if (isset($_SERVER['HTTP_RANGE'])) {
    preg_match('/bytes=(\d+)-(\d*)/', $_SERVER['HTTP_RANGE'], $matches);
    $start = (int) ($matches[1] ?? 0);
    $end   = isset($matches[2]) && $matches[2] !== ''
        ? (int) $matches[2]
        : $fileSize - 1;

    if ($start > $end || $start >= $fileSize) {
        http_response_code(416);
        header("Content-Range: bytes */$fileSize");
        exit;
    }

    http_response_code(206); // Partial Content
    header("Content-Range: bytes $start-$end/$fileSize");
} else {
    http_response_code(200);
}

$length = $end - $start + 1;

header('Content-Type: '   . $mimeType);
header('Content-Length: ' . $length);
header('Accept-Ranges: bytes');
header('Cache-Control: no-cache');

// Envoi du fichier par chunks
$fp = fopen($realPath, 'rb');
fseek($fp, $start);

$chunkSize = 1024 * 256; // 256 Ko
$remaining = $length;

while (!feof($fp) && $remaining > 0) {
    $read = min($chunkSize, $remaining);
    echo fread($fp, $read);
    $remaining -= $read;
    flush();
}

fclose($fp);
