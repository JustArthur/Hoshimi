<?php
// ============================================================
//  HOSHIMI — Serve subtitle files (.vtt)
// ============================================================

declare(strict_types=1);

$allowedRoots = [
    $_ENV['ANIMES_PATH'] ?? '/media/animes',
    $_ENV['FILMS_PATH']  ?? '/media/films',
    $_ENV['SERIES_PATH'] ?? '/media/series',
];

$requestedPath = urldecode($_GET['path'] ?? '');

if (empty($requestedPath)) { http_response_code(400); exit; }

$realPath = realpath($requestedPath);

if ($realPath === false || !is_file($realPath)) { http_response_code(404); exit; }

if (strtolower(pathinfo($realPath, PATHINFO_EXTENSION)) !== 'vtt') {
    http_response_code(403); exit;
}

$allowed = false;
foreach ($allowedRoots as $root) {
    $realRoot = realpath($root);
    if ($realRoot && str_starts_with($realPath, $realRoot . DIRECTORY_SEPARATOR)) {
        $allowed = true;
        break;
    }
}

if (!$allowed) { http_response_code(403); exit; }

header('Content-Type: text/vtt; charset=utf-8');
header('Cache-Control: public, max-age=3600');
readfile($realPath);
