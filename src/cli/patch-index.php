<?php

declare(strict_types=1);

// ============================================================
//  patch-index.php — Met à jour uniquement les entrées touchées
//  de l'index léger, sans reconstruire tout le catalogue.
//
//  Usage :
//    php patch-index.php --type anime --dir "/media/animes/Titre (2024)"
//    php patch-index.php --type film  --root /media/films
//    (répéter --dir pour plusieurs dossiers précis)
// ============================================================

require_once __DIR__ . '/../app/IndexManager.php';

$opts = getopt('', ['type:', 'dir:', 'root:']);
$type = $opts['type'] ?? null;

if (!$type) {
    fwrite(STDERR, "Usage: patch-index.php --type <anime|serie|film> (--dir <path> | --root <path>)\n");
    exit(1);
}

$dirs = [];

if (isset($opts['root'])) {
    foreach ((array) $opts['root'] as $root) {
        $dirs = array_merge($dirs, glob(rtrim($root, '/\\') . '/*', GLOB_ONLYDIR) ?: []);
    }
}

if (isset($opts['dir'])) {
    $dirs = array_merge($dirs, (array) $opts['dir']);
}

if (empty($dirs)) {
    echo "[" . date('Y-m-d H:i:s') . "] Aucun dossier à patcher.\n";
    exit(0);
}

try {
    IndexManager::patchDirs($type, $dirs);
    echo "[" . date('Y-m-d H:i:s') . "] Index patché ($type) : " . count($dirs) . " dossier(s) traité(s)\n";
} catch (\Throwable $e) {
    fwrite(STDERR, "[ERR] " . $e->getMessage() . "\n");
    exit(1);
}
