<?php
// Retourne si les dossiers médias ont été modifiés depuis le dernier cache scanner.
declare(strict_types=1);
header('Content-Type: application/json');
header('Cache-Control: no-store');

$roots = array_filter([
    $_ENV['ANIMES_PATH'] ?? '/media/animes',
    $_ENV['FILMS_PATH']  ?? '/media/films',
    $_ENV['SERIES_PATH'] ?? '/media/series',
], 'is_dir');

// mtime le plus récent parmi tous les dossiers de médias (niveau racine + sous-dossiers immédiats)
$latestMtime = 0;
foreach ($roots as $root) {
    $mtime = @filemtime($root);
    if ($mtime > $latestMtime) $latestMtime = $mtime;

    foreach (glob($root . '/*', GLOB_ONLYDIR) ?: [] as $sub) {
        $mtime = @filemtime($sub);
        if ($mtime > $latestMtime) $latestMtime = $mtime;
    }
}

// mtime du cache scanner le plus récent
$cacheFiles = glob(sys_get_temp_dir() . '/hoshimi_scanner_*.json') ?: [];
$cacheMtime = $cacheFiles ? max(array_map('filemtime', $cacheFiles)) : 0;

echo json_encode([
    'changed'     => $latestMtime > $cacheMtime,
    'dirs_mtime'  => $latestMtime,
    'cache_mtime' => $cacheMtime,
]);
