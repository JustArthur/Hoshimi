<?php

declare(strict_types=1);

// ============================================================
//  jellyfin-refresh.php — Déclenche un scan ciblé (async) de la
//  bibliothèque Jellyfin correspondant au type donné, pour que
//  les nouveaux fichiers soient visibles avant le prochain
//  passage de jellyfin-map.php.
//
//  Usage : php jellyfin-refresh.php --type anime
// ============================================================

require_once __DIR__ . '/../app/Services/JellyfinClient.php';

const MEDIA_PATHS = [
    'anime' => 'ANIMES_PATH',
    'serie' => 'SERIES_PATH',
    'film'  => 'FILMS_PATH',
];

$opts = getopt('', ['type:']);
$type = $opts['type'] ?? null;

if (!$type || !isset(MEDIA_PATHS[$type])) {
    fwrite(STDERR, "Usage: jellyfin-refresh.php --type <anime|serie|film>\n");
    exit(1);
}

$client = new JellyfinClient();

if (!$client->isConfigured()) {
    echo "[" . date('Y-m-d H:i:s') . "] Jellyfin non configuré — refresh ignoré\n";
    exit(0);
}

$path = $_ENV[MEDIA_PATHS[$type]] ?? null;
if (!$path) {
    fwrite(STDERR, "[ERR] Variable d'environnement " . MEDIA_PATHS[$type] . " absente\n");
    exit(1);
}

$libraryId = $client->getLibraryIdForPath($path);
if ($libraryId === null) {
    echo "[" . date('Y-m-d H:i:s') . "] Bibliothèque Jellyfin introuvable pour $path — refresh ignoré\n";
    exit(0);
}

$ok = $client->refreshLibrary($libraryId);
echo "[" . date('Y-m-d H:i:s') . "] Refresh Jellyfin ($type) : " . ($ok ? 'déclenché' : 'échec') . "\n";
