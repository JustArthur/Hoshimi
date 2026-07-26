<?php
// ============================================================
//  HOSHIMI — Recherche en temps réel (JSON API)
//  GET /search/api.php?q=naruto&limit=8
// ============================================================

declare(strict_types=1);

header('Content-Type: application/json');

$q     = trim($_GET['q'] ?? '');
$limit = min(max((int)($_GET['limit'] ?? 8), 1), 20);

if (strlen($q) < 2) {
    echo json_encode(['results' => [], 'q' => $q]);
    exit;
}

// ── Build search index from IndexManager ─────────────────────────────────────
require_once __DIR__ . '/../../app/IndexManager.php';

$index = [];

foreach (IndexManager::getAll('anime') as $a) {
    $index[] = [
        'type'          => 'anime',
        'slug'          => $a['slug'],
        'title'         => $a['title']         ?? '',
        'title_english' => $a['title_english']  ?? '',
        'title_romaji'  => $a['title_romaji']   ?? '',
        'cover_url'     => $a['cover_url']      ?? '',
        'year'          => $a['year']            ?? null,
        'score'         => $a['score']           ?? 0,
    ];
}

foreach (IndexManager::getAll('serie') as $s) {
    $index[] = [
        'type'          => 'serie',
        'slug'          => $s['slug'],
        'title'         => $s['title']          ?? '',
        'title_english' => $s['title_english']  ?? '',
        'title_romaji'  => $s['title_romaji']   ?? '',
        'cover_url'     => $s['cover_url']      ?? '',
        'year'          => $s['year']            ?? null,
        'score'         => $s['score']           ?? 0,
    ];
}

foreach (IndexManager::getAll('film') as $f) {
    $index[] = [
        'type'          => 'film',
        'slug'          => $f['slug'],
        'title'         => $f['title']    ?? '',
        'title_english' => '',
        'title_romaji'  => '',
        'cover_url'     => $f['cover_url'] ?? '',
        'year'          => $f['year']      ?? null,
        'score'         => $f['score']     ?? 0,
    ];
}

// ── Filtrage ─────────────────────────────────────────────────────────────────
$ql      = mb_strtolower($q);
$results = [];

foreach ($index as $item) {
    if (count($results) >= $limit) break;
    $haystack = mb_strtolower(implode(' ', array_filter([
        $item['title'],
        $item['title_english'],
        $item['title_romaji'],
    ])));
    if (str_contains($haystack, $ql)) {
        $results[] = [
            'type'      => $item['type'],
            'title'     => $item['title'],
            'year'      => $item['year'],
            'cover_url' => $item['cover_url'],
            'href'      => '/content/?type=' . $item['type'] . '&slug=' . urlencode($item['slug']),
        ];
    }
}

echo json_encode(['results' => $results, 'q' => $q, 'total' => count($results)]);
