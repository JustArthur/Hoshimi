<?php

declare(strict_types=1);

require_once __DIR__ . '/../../../app/MacGuard.php';
MacGuard::protect();

header('Content-Type: application/json');

$type = $_GET['type'] ?? '';
$name = $_GET['name'] ?? '';

$roots = [
    'anime' => $_ENV['ANIMES_PATH'] ?? getenv('ANIMES_PATH') ?: '/media/animes',
    'film'  => $_ENV['FILMS_PATH']  ?? getenv('FILMS_PATH')  ?: '/media/films',
    'serie' => $_ENV['SERIES_PATH'] ?? getenv('SERIES_PATH') ?: '/media/series',
];

if (!isset($roots[$type]) || $name === '') {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'Paramètres manquants.']);
    exit;
}

$root    = realpath($roots[$type]);
$dirPath = $root ? realpath($root . DIRECTORY_SEPARATOR . $name) : false;

if (!$dirPath || !$root || !str_starts_with($dirPath, $root . DIRECTORY_SEPARATOR)) {
    http_response_code(403);
    echo json_encode(['ok' => false, 'error' => 'Chemin invalide.']);
    exit;
}

$metaPath = $dirPath . DIRECTORY_SEPARATOR . 'metadata.json';
if (!file_exists($metaPath)) {
    http_response_code(404);
    echo json_encode(['ok' => false, 'error' => 'metadata.json introuvable.']);
    exit;
}

$meta = json_decode(file_get_contents($metaPath), true);
if (!is_array($meta)) {
    http_response_code(500);
    echo json_encode(['ok' => false, 'error' => 'JSON invalide.']);
    exit;
}

echo json_encode(['ok' => true, 'meta' => $meta]);
