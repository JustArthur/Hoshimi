<?php

declare(strict_types=1);

header('Content-Type: application/json');

$q    = trim($_GET['q']    ?? '');
$type = trim($_GET['type'] ?? 'all');  // film | serie | anime | all
$page = max(1, (int)($_GET['page'] ?? 1));

if (strlen($q) < 2) {
    echo json_encode(['ok' => false, 'error' => 'Requête trop courte.']);
    exit;
}

$apiKey = $_ENV['TMDB_API_KEY'] ?? getenv('TMDB_API_KEY') ?: '';
if (!$apiKey) {
    echo json_encode(['ok' => false, 'error' => 'TMDB_API_KEY non configurée.']);
    exit;
}

$ctx = stream_context_create(['http' => ['timeout' => 6, 'ignore_errors' => true]]);

$results = [];

function tmdb_get(string $url, $ctx): ?array {
    $raw = @file_get_contents($url, false, $ctx);
    if (!$raw) return null;
    $data = json_decode($raw, true);
    return is_array($data) ? $data : null;
}

function is_anime(array $r): bool {
    $genres    = $r['genre_ids'] ?? [];
    $countries = $r['origin_country'] ?? [];
    return in_array(16, $genres, true) && in_array('JP', $countries, true);
}

function map_result(array $r, string $mediaType): array {
    $type = match($mediaType) {
        'tv'    => is_anime($r) ? 'anime' : 'serie',
        default => 'film',
    };
    $title    = $r['title'] ?? $r['name'] ?? '';
    $date     = $r['release_date'] ?? $r['first_air_date'] ?? '';
    $year     = $date ? (int)substr($date, 0, 4) : null;
    $poster   = $r['poster_path'] ?? null;
    $coverUrl = $poster ? 'https://image.tmdb.org/t/p/w342' . $poster : null;

    return [
        'tmdb_id'      => $r['id'],
        'title'        => $title,
        'year'         => $year,
        'release_date' => $date ?: null,
        'type'         => $type,
        'cover_url'    => $coverUrl,
        'overview'     => mb_strimwidth($r['overview'] ?? '', 0, 160, '…'),
        'score'        => isset($r['vote_average']) && $r['vote_average'] > 0
                            ? round((float)$r['vote_average'], 1) : null,
    ];
}

// Selon le type demandé, on cherche sur movie, tv, ou les deux via /search/multi
if ($type === 'film') {
    $url  = 'https://api.themoviedb.org/3/search/movie?api_key=' . urlencode($apiKey)
          . '&query=' . urlencode($q) . '&language=fr-FR&page=' . $page;
    $data = tmdb_get($url, $ctx);
    if ($data) {
        foreach ($data['results'] ?? [] as $r) {
            $results[] = map_result($r, 'movie');
        }
    }
} elseif (in_array($type, ['serie', 'anime'], true)) {
    $url  = 'https://api.themoviedb.org/3/search/tv?api_key=' . urlencode($apiKey)
          . '&query=' . urlencode($q) . '&language=fr-FR&page=' . $page;
    $data = tmdb_get($url, $ctx);
    if ($data) {
        foreach ($data['results'] ?? [] as $r) {
            $item = map_result($r, 'tv');
            $item['type'] = $type; // garde anime/serie selon le choix user
            $results[] = $item;
        }
    }
} else {
    // Multi-search
    $url  = 'https://api.themoviedb.org/3/search/multi?api_key=' . urlencode($apiKey)
          . '&query=' . urlencode($q) . '&language=fr-FR&page=' . $page;
    $data = tmdb_get($url, $ctx);
    if ($data) {
        foreach ($data['results'] ?? [] as $r) {
            $mt = $r['media_type'] ?? '';
            if (!in_array($mt, ['movie', 'tv'], true)) continue;
            $results[] = map_result($r, $mt);
        }
    }
}

// Filtrer les résultats sans poster (optionnel, retire les entrées sans image)
$results = array_values(array_filter($results, fn($r) => $r['cover_url'] !== null));

echo json_encode([
    'ok'          => true,
    'results'     => $results,
    'total_pages' => $data['total_pages'] ?? 1,
    'page'        => $page,
]);
