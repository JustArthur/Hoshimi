<?php
declare(strict_types=1);

header('Content-Type: application/json');

$allowedRoots = [
    $_ENV['ANIMES_PATH']  ?? '/media/animes',
    $_ENV['FILMS_PATH']   ?? '/media/films',
    $_ENV['SERIES_PATH']  ?? '/media/series',
];

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit;
}

$input    = json_decode(file_get_contents('php://input'), true) ?? [];
$metaPath = $input['meta_path'] ?? '';
$epNumber = (int)($input['ep_number'] ?? 0);
$name     = trim($input['name'] ?? '');
$overview = trim($input['overview'] ?? '');

if (!$metaPath || !$epNumber) {
    http_response_code(400);
    echo json_encode(['error' => 'Paramètres manquants']);
    exit;
}

$realPath = realpath($metaPath);
if (!$realPath || !str_ends_with($realPath, 'metadata.json')) {
    http_response_code(404);
    echo json_encode(['error' => 'Fichier introuvable']);
    exit;
}

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
    echo json_encode(['error' => 'Accès refusé']);
    exit;
}

$data = @json_decode(@file_get_contents($realPath), true);
if (!is_array($data) || !isset($data['episodes'])) {
    http_response_code(400);
    echo json_encode(['error' => 'Fichier metadata invalide']);
    exit;
}

$updated = false;
foreach ($data['episodes'] as &$ep) {
    if ((int)($ep['episode_number'] ?? 0) === $epNumber) {
        if ($name !== '') $ep['name'] = $name;
        $ep['overview'] = $overview;
        $updated = true;
        break;
    }
}
unset($ep);

if (!$updated) {
    http_response_code(404);
    echo json_encode(['error' => 'Épisode introuvable']);
    exit;
}

file_put_contents($realPath, json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT));
echo json_encode(['success' => true]);
