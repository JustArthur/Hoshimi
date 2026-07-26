<?php
declare(strict_types=1);

header('Content-Type: application/json');
header('Cache-Control: public, max-age=300');

require_once __DIR__ . '/../../app/IndexManager.php';

$body   = (string)file_get_contents('php://input');
$input  = $body ? (json_decode($body, true) ?: []) : [];
$wanted = array_flip(array_filter((array)($input['file_keys'] ?? []), 'is_string'));

if (empty($wanted)) {
    echo json_encode((object)[]);
    exit;
}

$out = [];

foreach (['anime', 'serie', 'film'] as $type) {
    foreach (IndexManager::getAll($type) as $item) {
        $eps = $item['episodes'] ?? [];
        $match = false;
        foreach ($eps as $ep) {
            if (isset($wanted[$ep['file_key']])) { $match = true; break; }
        }
        if (!$match) continue;

        $showTitle = $type === 'film'
            ? ($item['title'] ?? '')
            : (($item['title_english'] ?? null) ?: ($item['title'] ?? ''));

        $out[$type . ':' . $item['slug']] = [
            'slug'       => $item['slug'],
            'type'       => $type,
            'show_title' => $showTitle,
            'cover_url'  => $item['cover_url'] ?? '',
            'banner_url' => $item['banner_url'] ?? '',
            'score'      => $item['score'] ?? null,
            'overview'   => $item['synopsis'] ?? '',
            'genres'     => array_values($item['genres'] ?? []),
            'episodes'   => $eps,
        ];
    }
}

echo json_encode($out);
