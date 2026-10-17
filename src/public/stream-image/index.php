<?php
// ============================================================
//  HOSHIMI — Proxy d'images locales
//  URL : /stream-image/?path=/media/animes/One%20Piece/cover.jpg
//
//  Sert les images depuis le disque local (hors webroot)
//  de façon sécurisée en vérifiant le chemin autorisé.
// ============================================================

declare(strict_types=1);

$allowedRoots = [
    $_ENV['ANIMES_PATH'] ?? '/media/animes',
    $_ENV['FILMS_PATH']  ?? '/media/films',
    $_ENV['SERIES_PATH'] ?? '/media/series',
];

$requestedPath = $_GET['path'] ?? '';

if (empty($requestedPath)) {
    http_response_code(400);
    exit;
}

// Résoudre le chemin réel (évite les ../ traversals)
$realPath = realpath($requestedPath);

if ($realPath === false) {
    http_response_code(404);
    exit;
}

// Vérifier que le fichier est dans un dossier autorisé
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

// Vérifier l'extension
$ext      = strtolower(pathinfo($realPath, PATHINFO_EXTENSION));
$mimeMap  = [
    'jpg'  => 'image/jpeg',
    'jpeg' => 'image/jpeg',
    'png'  => 'image/png',
    'webp' => 'image/webp',
    'gif'  => 'image/gif',
];

if (!isset($mimeMap[$ext])) {
    http_response_code(415);
    exit;
}

// ── WebP automatique ─────────────────────────────────────────────────────────
// Si le navigateur accepte WebP et que le fichier est jpg/png, on le convertit
// à la volée et on met en cache la version WebP à côté du fichier original.
$canConvert  = in_array($ext, ['jpg', 'jpeg', 'png'], true);
$acceptsWebp = str_contains($_SERVER['HTTP_ACCEPT'] ?? '', 'image/webp');

if ($canConvert && $acceptsWebp && function_exists('imagewebp')) {
    // Cache dans /tmp pour éviter les problèmes de droits sur les volumes montés
    $webpDir  = sys_get_temp_dir() . '/hoshimi_webp/' . md5(dirname($realPath));
    $webpPath = $webpDir . '/' . basename($realPath) . '.webp';

    if (!file_exists($webpPath) || filemtime($webpPath) < filemtime($realPath)) {
        @mkdir($webpDir, 0755, true);
        $src = match ($ext) {
            'jpg', 'jpeg' => @imagecreatefromjpeg($realPath),
            'png'         => @imagecreatefrompng($realPath),
        };
        if ($src !== false) {
            @imagewebp($src, $webpPath, 82);
            unset($src);
        }
    }

    if (file_exists($webpPath)) {
        header('Content-Type: image/webp');
        header('Cache-Control: public, max-age=604800');
        header('Vary: Accept');
        header('Content-Length: ' . filesize($webpPath));
        readfile($webpPath);
    exit;
}
}

// Envoyer l'image originale (fallback ou gif/webp natif)
header('Content-Type: ' . $mimeMap[$ext]);
header('Cache-Control: public, max-age=86400');
header('Vary: Accept');
header('Content-Length: ' . filesize($realPath));
readfile($realPath);
