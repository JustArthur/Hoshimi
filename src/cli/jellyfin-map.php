<?php

declare(strict_types=1);

require_once __DIR__ . '/../app/Services/JellyfinClient.php';

const JELLYFIN_MAP_FILE = __DIR__ . '/../data/jellyfin-map.json';

$t0 = microtime(true);

$client = new JellyfinClient();

if (!$client->isConfigured()) {
    echo "[" . date('Y-m-d H:i:s') . "] Jellyfin non configuré (JELLYFIN_URL/JELLYFIN_API_KEY absents) — mapping ignoré\n";
    exit(0);
}

$map = $client->getPathToItemIdMap();

if (empty($map)) {
    echo "[" . date('Y-m-d H:i:s') . "] Jellyfin injoignable ou aucun item trouvé — mapping non mis à jour\n";
    exit(0);
}

@mkdir(dirname(JELLYFIN_MAP_FILE), 0775, true);
file_put_contents(
    JELLYFIN_MAP_FILE,
    json_encode($map, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
    LOCK_EX
);

$ms = round((microtime(true) - $t0) * 1000);
echo "[" . date('Y-m-d H:i:s') . "] Mapping Jellyfin : " . count($map) . " items en {$ms}ms\n";
