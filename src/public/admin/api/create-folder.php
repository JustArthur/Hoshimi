<?php

declare(strict_types=1);

require_once __DIR__ . '/../../../app/MacGuard.php';
MacGuard::protect();

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['ok' => false, 'error' => 'method not allowed']);
    exit;
}

$body  = json_decode(file_get_contents('php://input'), true) ?? [];
$type  = $body['type']   ?? '';
$title = trim($body['title'] ?? '');
$year  = isset($body['year']) ? (int)$body['year'] : null;
$tmdbId = isset($body['tmdb_id']) ? (int)$body['tmdb_id'] : null;

$TYPE_MAP = [
    'anime'  => ['env' => 'ANIMES_PATH',  'default' => '/media/animes'],
    'film'   => ['env' => 'FILMS_PATH',   'default' => '/media/films'],
    'serie'  => ['env' => 'SERIES_PATH',  'default' => '/media/series'],
];

if (!isset($TYPE_MAP[$type]) || $title === '') {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'type ou titre manquant']);
    exit;
}

$info = $TYPE_MAP[$type];
$root = $_ENV[$info['env']] ?? getenv($info['env']) ?: $info['default'];
$root = rtrim($root, '/\\');

if (!is_dir($root)) {
    echo json_encode(['ok' => false, 'error' => "Dossier racine introuvable : $root"]);
    exit;
}

// Construire un nom de dossier propre : "Titre (Année)"
$safeName = preg_replace('/[<>:"\/\\\\|?*]/', '', $title);
$safeName = trim(preg_replace('/\s+/', ' ', $safeName));
if ($year) $safeName .= " ($year)";

$folderPath = $root . DIRECTORY_SEPARATOR . $safeName;

if (is_dir($folderPath)) {
    echo json_encode(['ok' => false, 'error' => "Le dossier existe déjà : $safeName"]);
    exit;
}

if (!mkdir($folderPath, 0775, true)) {
    echo json_encode(['ok' => false, 'error' => 'Impossible de créer le dossier (permissions ?)']);
    exit;
}

// .tmdb_id : évite une recherche floue lors du scan
if ($tmdbId) {
    file_put_contents($folderPath . DIRECTORY_SEPARATOR . '.tmdb_id', (string)$tmdbId);
}

echo json_encode(['ok' => true, 'folder' => $safeName, 'path' => $folderPath]);
