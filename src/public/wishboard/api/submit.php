<?php

declare(strict_types=1);

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['ok' => false, 'error' => 'Method not allowed']);
    exit;
}

$body     = json_decode(file_get_contents('php://input'), true) ?? [];
$title    = trim($body['title'] ?? '');
$type     = $body['type']     ?? 'film';
$year        = isset($body['year']) && is_numeric($body['year']) ? (int)$body['year'] : null;
$releaseDate = trim($body['release_date'] ?? '') ?: null;
$note        = trim($body['note'] ?? '') ?: null;
$tmdbId      = isset($body['tmdb_id']) && is_numeric($body['tmdb_id']) ? (int)$body['tmdb_id'] : null;
$coverUrl = trim($body['cover_url'] ?? '') ?: null;
// Valider que cover_url pointe bien vers TMDB
if ($coverUrl && !str_starts_with($coverUrl, 'https://image.tmdb.org/')) {
    $coverUrl = null;
}

$validTypes = ['film', 'anime', 'serie'];

if ($title === '') {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'Le titre est obligatoire.']);
    exit;
}

if (!in_array($type, $validTypes, true)) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'Type invalide.']);
    exit;
}

if ($year !== null && ($year < 1900 || $year > 2099)) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'Année invalide.']);
    exit;
}

$dataFile = __DIR__ . '/../../../data/wishboard.json';
$wishes   = [];
if (file_exists($dataFile)) {
    $wishes = json_decode(file_get_contents($dataFile), true) ?: [];
}

// Anti-doublon : bloque uniquement si une demande identique est déjà en attente
$titleLower = mb_strtolower($title);
foreach ($wishes as $w) {
    if ($w['status'] !== 'pending') continue;
    $sameId    = $tmdbId && ($w['tmdb_id'] ?? null) === $tmdbId;
    $sameTitle = mb_strtolower($w['title']) === $titleLower && $w['type'] === $type;
    if ($sameId || $sameTitle) {
        echo json_encode(['ok' => false, 'error' => 'Cette demande est déjà en attente.']);
        exit;
    }
}

$wish = [
    'id'           => bin2hex(random_bytes(8)),
    'title'        => $title,
    'type'         => $type,
    'year'         => $year,
    'release_date' => $releaseDate,
    'note'         => $note,
    'tmdb_id'      => $tmdbId,
    'cover_url'    => $coverUrl,
    'status'       => 'pending',
    'created_at'   => date('c'),
    'resolved_at'  => null,
];

$wishes[] = $wish;

if (!file_put_contents($dataFile, json_encode($wishes, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT), LOCK_EX)) {
    http_response_code(500);
    echo json_encode(['ok' => false, 'error' => 'Impossible d\'écrire le fichier.']);
    exit;
}

echo json_encode(['ok' => true, 'id' => $wish['id']]);
