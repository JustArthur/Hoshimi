<?php
declare(strict_types=1);

header('Content-Type: application/json');

require_once __DIR__ . '/../../app/IndexManager.php';
require_once __DIR__ . '/../components/card.php';

$body = (string)file_get_contents('php://input');
$in   = $body ? (json_decode($body, true) ?: []) : [];

$type = (string)($in['type'] ?? '');
$slug = (string)($in['slug'] ?? '');

if (!in_array($type, ['anime', 'serie', 'film'], true) || $slug === '') {
    echo json_encode(['html' => '']);
    exit;
}

$source = null;
foreach (IndexManager::getAll($type) as $item) {
    if (($item['slug'] ?? '') === $slug) { $source = $item; break; }
}

$genres = array_unique($source['genres'] ?? []);
if ($source === null || empty($genres)) {
    echo json_encode(['html' => '']);
    exit;
}

$pool = [];
foreach (['anime', 'serie', 'film'] as $t) {
    foreach (IndexManager::getAll($t) as $item) {
        if (($item['episodes_local'] ?? 0) <= 0) continue;
        $pool[] = $item + ['_type' => $t];
    }
}

$scored = [];
foreach ($pool as $item) {
    if ($item['_type'] === $type && $item['slug'] === $slug) continue;
    $common = array_values(array_intersect($genres, array_unique($item['genres'] ?? [])));
    if (empty($common)) continue;

    $union   = array_unique(array_merge($genres, $item['genres'] ?? []));
    $jaccard = count($common) / count($union) * 4.0;
    $sA = $source['score'] ?? null;
    $sB = $item['score']   ?? null;
    $scoreBonus = ($sA !== null && $sB !== null) ? max(0.0, 1 - abs($sA - $sB) / 3) * 1.5 : 0.0;
    $yA = $source['year'] ?? null;
    $yB = $item['year']   ?? null;
    $yearBonus  = ($yA !== null && $yB !== null) ? max(0.0, 1 - abs($yA - $yB) / 10) * 1.0 : 0.0;

    $scored[] = [
        'item'         => $item,
        'type'         => $item['_type'],
        'total'        => $jaccard + $scoreBonus + $yearBonus,
        'match_genres' => array_slice($common, 0, 3),
    ];
}

usort($scored, fn($a, $b) => $b['total'] <=> $a['total']);

$html = '';
foreach (array_slice($scored, 0, 10) as $s) {
    $html .= render_card($s['item'], $s['type'], ['match_genres' => $s['match_genres']]);
}

$title = $type === 'film'
    ? ($source['title'] ?? '')
    : (($source['title_english'] ?? null) ?: ($source['title'] ?? ''));

echo json_encode(['title' => $title, 'html' => $html], JSON_UNESCAPED_UNICODE);
