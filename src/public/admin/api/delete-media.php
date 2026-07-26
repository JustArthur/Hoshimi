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

$body = json_decode(file_get_contents('php://input'), true) ?? [];
$type = $body['type'] ?? '';
$name = $body['name'] ?? '';

$roots = [
    'anime' => $_ENV['ANIMES_PATH'] ?? getenv('ANIMES_PATH') ?: '/media/animes',
    'film'  => $_ENV['FILMS_PATH']  ?? getenv('FILMS_PATH')  ?: '/media/films',
    'serie' => $_ENV['SERIES_PATH'] ?? getenv('SERIES_PATH') ?: '/media/series',
];

if (!isset($roots[$type]) || $name === '' || str_contains($name, '..') || str_contains($name, '/')) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'paramètres invalides']);
    exit;
}

$srcDir   = $roots[$type] . DIRECTORY_SEPARATOR . $name;
$trashDir = $roots[$type] . DIRECTORY_SEPARATOR . '_trash';

if (!is_dir($srcDir)) {
    http_response_code(404);
    echo json_encode(['ok' => false, 'error' => 'dossier introuvable']);
    exit;
}

if (!is_dir($trashDir) && !mkdir($trashDir, 0755, true)) {
    http_response_code(500);
    echo json_encode(['ok' => false, 'error' => 'impossible de créer _trash']);
    exit;
}

$dest = $trashDir . DIRECTORY_SEPARATOR . $name;
// Si déjà dans la corbeille, on suffixe avec la date
if (is_dir($dest)) {
    $dest .= '_' . date('Ymd_His');
}

if (!rename($srcDir, $dest)) {
    http_response_code(500);
    echo json_encode(['ok' => false, 'error' => 'déplacement échoué']);
    exit;
}

echo json_encode(['ok' => true, 'moved_to' => $dest]);
