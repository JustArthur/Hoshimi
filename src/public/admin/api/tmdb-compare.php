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
    echo json_encode(['ok' => false, 'error' => 'Paramètres manquants']);
    exit;
}

$root    = realpath($roots[$type]);
$dirPath = $root ? realpath($root . DIRECTORY_SEPARATOR . $name) : false;

if (!$dirPath || !str_starts_with($dirPath, $root . DIRECTORY_SEPARATOR)) {
    http_response_code(403);
    echo json_encode(['ok' => false, 'error' => 'Chemin invalide']);
    exit;
}

$metaPath = $dirPath . DIRECTORY_SEPARATOR . 'metadata.json';
if (!file_exists($metaPath)) {
    echo json_encode(['ok' => false, 'error' => 'Pas de metadata.json']);
    exit;
}

$local  = json_decode(file_get_contents($metaPath), true);
$tmdbId = $local['tmdb_id'] ?? null;

if (!$tmdbId) {
    echo json_encode(['ok' => false, 'error' => 'Pas de tmdb_id dans metadata.json']);
    exit;
}

$apiKey = $_ENV['TMDB_API_KEY'] ?? getenv('TMDB_API_KEY') ?: '';
if (!$apiKey) {
    echo json_encode(['ok' => false, 'error' => 'TMDB_API_KEY non configurée']);
    exit;
}

// Déterminer le endpoint TMDB selon le type
$tmdbType = in_array($type, ['anime', 'serie']) ? 'tv' : 'movie';
$url      = "https://api.themoviedb.org/3/{$tmdbType}/{$tmdbId}?api_key={$apiKey}&language=fr-FR&append_to_response=credits";

$ctx    = stream_context_create(['http' => ['timeout' => 8, 'ignore_errors' => true]]);
$raw    = @file_get_contents($url, false, $ctx);
$remote = $raw ? json_decode($raw, true) : null;

if (!$remote || isset($remote['status_code'])) {
    echo json_encode(['ok' => false, 'error' => 'Erreur TMDB : ' . ($remote['status_message'] ?? 'inconnue')]);
    exit;
}

// Normaliser le poster path remote pour comparaison avec l'URL locale
$remotePosterPath = $remote['poster_path'] ?? null;
$remotePosterUrl  = $remotePosterPath ? 'https://image.tmdb.org/t/p/w500' . $remotePosterPath : null;

// Normaliser les champs pour comparaison
$fields = [
    'title'    => ['local' => $local['title']    ?? null, 'remote' => $remote['title'] ?? $remote['name'] ?? null,   'label' => 'Titre'],
    'year'     => ['local' => $local['year']     ?? null, 'remote' => (int)(substr($remote['release_date'] ?? $remote['first_air_date'] ?? '', 0, 4) ?: 0) ?: null, 'label' => 'Année'],
    'overview' => ['local' => $local['overview'] ?? null, 'remote' => $remote['overview'] ?? null,                   'label' => 'Synopsis'],
    'status'   => ['local' => $local['status']   ?? null, 'remote' => $remote['status']  ?? null,                   'label' => 'Statut'],
    'genres'   => [
        'local'       => implode(', ', $local['genres'] ?? []),
        'remote'      => implode(', ', array_column($remote['genres'] ?? [], 'name')),
        'label'       => 'Genres',
        'local_source' => $type === 'anime' ? 'anilist' : null,
    ],
    'score'    => ['local' => $local['score']    ?? null, 'remote' => isset($remote['vote_average']) ? round($remote['vote_average'], 1) : null, 'label' => 'Note'],
    'cover'    => ['local' => $local['cover']    ?? null, 'remote' => $remotePosterUrl, 'label' => 'Cover'],
];

echo json_encode(['ok' => true, 'fields' => $fields, 'tmdb_id' => $tmdbId, 'tmdb_type' => $tmdbType]);
