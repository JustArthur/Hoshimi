<?php
declare(strict_types=1);

header('Content-Type: application/json');
header('Cache-Control: no-store');

require_once __DIR__ . '/../../app/IndexManager.php';

$types = ['anime', 'serie', 'film'];
$pool  = [];

foreach ($types as $type) {
    try {
        $items = IndexManager::getAll($type);
        foreach ($items as $item) {
            if (($item['episodes_local'] ?? 0) > 0) {
                $pool[] = [
                    'type' => $type,
                    'slug' => $item['slug'],
                    'href' => '/content/?type=' . $type . '&slug=' . $item['slug'],
                ];
            }
        }
    } catch (\Throwable) {}
}

if (empty($pool)) {
    echo json_encode(['ok' => false, 'error' => 'Aucun média disponible']);
    exit;
}

echo json_encode(['ok' => true, ...$pool[array_rand($pool)]]);
