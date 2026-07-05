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
    $_ENV['ANIMES_PATH']  ?? '/media/animes',
    $_ENV['FILMS_PATH']   ?? '/media/films',
    $_ENV['SERIES_PATH']  ?? '/media/series',
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

// Envoyer l'image
header('Content-Type: ' . $mimeMap[$ext]);
header('Cache-Control: public, max-age=86400');
header('Content-Length: ' . filesize($realPath));
readfile($realPath);
