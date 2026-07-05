<?php

declare(strict_types=1);

// Hôtes distants autorisés (AniList + TMDB)
const ALLOWED_HOSTS = [
    's4.anilist.co', 's1.anilist.co', 'cdn.anilist.co', 'img.anilist.co',
    'image.tmdb.org',
];

// Racines locales autorisées pour les covers
const LOCAL_MEDIA_ROOTS = ['/media/animes', '/media/films', '/media/series'];

$src = $_GET['src'] ?? '';
$w   = max(40, min(600, (int)($_GET['w'] ?? 300)));

if ($src === '') {
    http_response_code(400);
    exit;
}

$cacheDir = __DIR__ . '/../images/img-cache';
if (!is_dir($cacheDir)) {
    mkdir($cacheDir, 0755, true);
}

$cacheKey  = md5($src . '_' . $w) . '.jpg';
$cachePath = $cacheDir . '/' . $cacheKey;

// Sert depuis le cache si dispo et récent (7 jours)
if (file_exists($cachePath) && (time() - filemtime($cachePath)) < 604800) {
    header('Content-Type: image/jpeg');
    header('Cache-Control: public, max-age=604800');
    header('X-Cache: HIT');
    readfile($cachePath);
    exit;
}

// ---- Résolution de la source ----
$parsed = parse_url($src);
$host   = $parsed['host'] ?? '';

if ($host !== '') {
    // URL distante (AniList CDN)
    if (!in_array($host, ALLOWED_HOSTS, true)) {
        http_response_code(403);
        exit;
    }

    $ctx  = stream_context_create([
        'http' => ['timeout' => 8, 'user_agent' => 'Hoshimi/1.0', 'header' => "Accept: image/*\r\n"],
        'ssl'  => ['verify_peer' => true],
    ]);
    $data = @file_get_contents($src, false, $ctx);

} else {
    // URL locale — extrait le paramètre ?path= de /stream-image/?path=...
    $localPath = null;

    $srcPath  = $parsed['path']  ?? '';
    $srcQuery = $parsed['query'] ?? '';
    parse_str($srcQuery, $srcParams);

    if (str_starts_with($srcPath, '/stream-image') && isset($srcParams['path'])) {
        // /stream-image/?path=/media/animes/Dandadan/cover.jpg
        $localPath = $srcParams['path'];
    } elseif (str_starts_with($srcPath, '/images/')) {
        // /images/thumbnails/abc123.jpg  →  DOCUMENT_ROOT/images/...
        $localPath = $_SERVER['DOCUMENT_ROOT'] . $srcPath;
    }

    if ($localPath === null) {
        http_response_code(400);
        exit;
    }

    // Sécurité : le chemin réel doit être sous une racine autorisée
    $real = realpath($localPath);
    $allowedRoots = array_map('realpath', array_merge(
        LOCAL_MEDIA_ROOTS,
        [$_SERVER['DOCUMENT_ROOT'] . '/images']
    ));

    $allowed = false;
    foreach ($allowedRoots as $root) {
        if ($root && str_starts_with($real ?: '', $root)) {
            $allowed = true;
            break;
        }
    }

    if (!$allowed || !$real || !is_file($real)) {
        http_response_code(403);
        exit;
    }

    $data = @file_get_contents($real);
}

if ($data === false || $data === '') {
    http_response_code(502);
    exit;
}

// ---- Redimensionnement GD ----
$im = @imagecreatefromstring($data);
if ($im === false) {
    header('Content-Type: image/jpeg');
    header('Cache-Control: public, max-age=86400');
    echo $data;
    exit;
}

$origW = imagesx($im);
$origH = imagesy($im);

// Ne pas upscaler
if ($w >= $origW) {
    imagedestroy($im);
    header('Content-Type: image/jpeg');
    header('Cache-Control: public, max-age=604800');
    echo $data;
    exit;
}

$newH    = (int)round($origH * $w / $origW);
$resized = imagecreatetruecolor($w, $newH);

imagefill($resized, 0, 0, imagecolorallocate($resized, 0, 0, 0));
imagecopyresampled($resized, $im, 0, 0, 0, 0, $w, $newH, $origW, $origH);
imagedestroy($im);

imagejpeg($resized, $cachePath, 82);
imagedestroy($resized);

header('Content-Type: image/jpeg');
header('Cache-Control: public, max-age=604800');
header('X-Cache: MISS');
readfile($cachePath);
