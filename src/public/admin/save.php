<?php

declare(strict_types=1);

require_once __DIR__ . '/../../app/MacGuard.php';
MacGuard::protect();

header('Content-Type: application/json');

function jsonErr(string $msg, int $code = 400): never
{
    http_response_code($code);
    echo json_encode(['ok' => false, 'error' => $msg]);
    exit;
}

// ── Lire le body JSON ─────────────────────────────────────────────────────
$raw = file_get_contents('php://input');
$payload = json_decode($raw, true);

if (!is_array($payload)) {
    jsonErr('Corps JSON invalide.');
}

$type = $payload['type'] ?? '';
$name = $payload['name'] ?? '';
$meta = $payload['meta'] ?? null;

if (!is_string($type) || !is_string($name) || !is_array($meta)) {
    jsonErr('Champs manquants : type, name, meta.');
}

// ── Résolution et validation du chemin ───────────────────────────────────
$roots = [
    'anime' => $_ENV['ANIMES_PATH'] ?? getenv('ANIMES_PATH') ?: '/media/animes',
    'film'  => $_ENV['FILMS_PATH']  ?? getenv('FILMS_PATH')  ?: '/media/films',
    'serie' => $_ENV['SERIES_PATH'] ?? getenv('SERIES_PATH') ?: '/media/series',
];

if (!isset($roots[$type])) {
    jsonErr('Type invalide.');
}

$root     = $roots[$type];
$dirPath  = realpath($root . DIRECTORY_SEPARATOR . $name);
$realRoot = realpath($root);

if (!$dirPath || !$realRoot || !str_starts_with($dirPath, $realRoot . DIRECTORY_SEPARATOR)) {
    jsonErr('Chemin invalide ou accès refusé.', 403);
}

$metaPath = $dirPath . DIRECTORY_SEPARATOR . 'metadata.json';

// ── Lire le fichier actuel et fusionner ───────────────────────────────────
$current = [];
if (file_exists($metaPath)) {
    $current = json_decode(file_get_contents($metaPath), true) ?: [];
}

// Champs éditables — on ne touche qu'à ceux-là, le reste est préservé
$editableFields = [
    'title', 'title_original', 'overview', 'year', 'score', 'runtime',
    'status', 'format', 'studio', 'tmdb_id', 'episodes_total', 'seasons_count',
    'cover', 'banner', 'genres', 'logos', 'cast', 'seasons',
];

foreach ($editableFields as $field) {
    if (array_key_exists($field, $meta)) {
        $current[$field] = $meta[$field];
    }
}

// ── Nettoyage des types ───────────────────────────────────────────────────
foreach (['year', 'runtime', 'tmdb_id', 'episodes_total', 'seasons_count'] as $intField) {
    if (isset($current[$intField]) && $current[$intField] !== null) {
        $v = (int)$current[$intField];
        $current[$intField] = $v !== 0 ? $v : null;
    }
}
if (isset($current['score']) && $current['score'] !== null) {
    $v = round((float)$current['score'], 1);
    $current['score'] = $v !== 0.0 ? $v : null;
}

// Genres : dédupliquer
if (isset($current['genres']) && is_array($current['genres'])) {
    $current['genres'] = array_values(array_unique(array_filter(array_map('trim', $current['genres']))));
}

// Cast : supprimer les entrées sans nom
if (isset($current['cast']) && is_array($current['cast'])) {
    $current['cast'] = array_values(array_filter($current['cast'], fn($c) => !empty($c['name'])));
}

// Saisons : nettoyer les épisodes sans nom
if (isset($current['seasons']) && is_array($current['seasons'])) {
    foreach ($current['seasons'] as $key => &$season) {
        if (isset($season['episodes']) && is_array($season['episodes'])) {
            $season['episodes'] = array_values(
                array_filter($season['episodes'], fn($ep) => !empty($ep['name']))
            );
        }
    }
    unset($season);
}

// ── Validation ───────────────────────────────────────────────────────────
$errors = [];

if (empty(trim((string)($current['title'] ?? '')))) {
    $errors[] = ['field' => 'title', 'msg' => 'Le titre est obligatoire.'];
}
if (isset($current['score']) && $current['score'] !== null) {
    $s = (float)$current['score'];
    if ($s < 0 || $s > 10) {
        $errors[] = ['field' => 'score', 'msg' => 'Le score doit être entre 0 et 10.'];
    }
}
if (isset($current['year']) && $current['year'] !== null) {
    $y = (int)$current['year'];
    if ($y < 1900 || $y > 2100) {
        $errors[] = ['field' => 'year', 'msg' => "L'année doit être comprise entre 1900 et 2100."];
    }
}
if (isset($current['runtime']) && $current['runtime'] !== null && (int)$current['runtime'] < 0) {
    $errors[] = ['field' => 'runtime', 'msg' => 'La durée ne peut pas être négative.'];
}

if (!empty($errors)) {
    http_response_code(422);
    echo json_encode(['ok' => false, 'errors' => $errors]);
    exit;
}

// ── Écriture ──────────────────────────────────────────────────────────────
$json = json_encode($current, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
if ($json === false) {
    jsonErr('Erreur lors de l\'encodage JSON.');
}

$written = file_put_contents($metaPath, $json);
if ($written === false) {
    jsonErr('Impossible d\'écrire dans le fichier metadata.json.', 500);
}

echo json_encode(['ok' => true]);
