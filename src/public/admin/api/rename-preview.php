<?php

declare(strict_types=1);

require_once __DIR__ . '/../../../app/MacGuard.php';
MacGuard::protect();

header('Content-Type: application/json');

$type = $_GET['type'] ?? '';
$name = trim($_GET['name'] ?? '');

$TYPE_MAP = [
    'anime' => ['env' => 'ANIMES_PATH', 'default' => '/media/animes'],
    'film'  => ['env' => 'FILMS_PATH',  'default' => '/media/films'],
    'serie' => ['env' => 'SERIES_PATH', 'default' => '/media/series'],
];

if (!isset($TYPE_MAP[$type]) || $name === '') {
    echo json_encode(['ok' => false, 'error' => 'type ou nom manquant']); exit;
}

$root    = rtrim($_ENV[$TYPE_MAP[$type]['env']] ?? getenv($TYPE_MAP[$type]['env']) ?: $TYPE_MAP[$type]['default'], '/\\');
$dirPath = realpath($root . DIRECTORY_SEPARATOR . $name);

if (!$dirPath || !str_starts_with($dirPath, $root . DIRECTORY_SEPARATOR)) {
    echo json_encode(['ok' => false, 'error' => 'chemin invalide']); exit;
}

$VIDEO_EXT = ['mkv', 'mp4', 'avi', 'webm'];

// ── Charger les épisodes TMDB depuis metadata.json ────────────────────────────
$meta         = json_decode(@file_get_contents($dirPath . '/metadata.json') ?: '{}', true) ?: [];
$showTitle    = $meta['title'] ?? $name;
$metaSeasons  = $meta['seasons'] ?? [];

// Index TMDB : [saison][episode] => titre
$tmdbIndex = [];
foreach ($metaSeasons as $ms) {
    $sNum = $ms['tmdb_season'] ?? null;
    if ($sNum === null) continue;
    foreach (array_values($ms['episodes'] ?? []) as $i => $ep) {
        $tmdbIndex[$sNum][$i + 1] = $ep['name'] ?? null;
    }
}

// ── Scanner les fichiers vidéo dans les sous-dossiers de saison ───────────────
function scanVideoFiles(string $dir, array $ext): array {
    $files = [];
    $items = scandir($dir) ?: [];
    foreach ($items as $item) {
        if ($item === '.' || $item === '..') continue;
        $full = $dir . '/' . $item;
        if (is_dir($full)) {
            foreach (scanVideoFiles($full, $ext) as $f) $files[] = $f;
        } elseif (in_array(strtolower(pathinfo($item, PATHINFO_EXTENSION)), $ext, true)) {
            $files[] = $full;
        }
    }
    return $files;
}

function parseSeasonFromDir(string $dir): ?int {
    if (preg_match('/(?:season|saison|s)\s*0*(\d{1,2})\b/i', basename($dir), $m)) {
        return (int)$m[1];
    }
    return null;
}

function parseEpFilename(string $filename, ?int $dirSeason): array {
    $name = pathinfo($filename, PATHINFO_FILENAME);
    $season = $dirSeason ?? 1; $episode = 0;
    if (preg_match('/S(\d{1,2})E(\d{1,3})/i', $name, $m)) {
        $season = $dirSeason ?? (int)$m[1]; $episode = (int)$m[2];
    } elseif (preg_match('/(?:EP?|E)(\d{1,3})/i', $name, $m)) {
        $episode = (int)$m[1];
    } elseif (preg_match('/(?:^|\s|-)(\d{1,3})(?:\s|-|$)/', $name, $m)) {
        $episode = (int)$m[1];
    }
    return ['season' => $season, 'episode' => $episode ?: 1];
}

function sanitizeFilename(string $s): string {
    $s = preg_replace('/[<>:"\/\\\\|?*]/', '', $s);
    $s = preg_replace('/\s+/', ' ', $s);
    return trim($s);
}

// Extrait qualité/langue du nom de fichier ORIGINAL (mêmes règles que FilmScanner)
function parseQualityLang(string $name): array {
    $quality  = null;
    $language = null;

    if (preg_match('/\b(4K|2160p|1080p|720p|480p)\b/i', $name, $m)) {
        $quality = strtolower($m[1]);
    }

    if (preg_match_all('/\b(VOSTFR|VOSTA|VOSTEN|MULTI|DUAL|VF|VO)\b/i', $name, $ms)) {
        $priority = ['VOSTFR' => 6, 'VOSTA' => 5, 'VOSTEN' => 4, 'MULTI' => 3, 'DUAL' => 3, 'VF' => 2, 'VO' => 1];
        $tags = array_map('strtoupper', $ms[1]);
        usort($tags, fn($a, $b) => ($priority[$b] ?? 0) <=> ($priority[$a] ?? 0));
        $language = implode(' ', array_unique($tags));
    }

    return ['quality' => $quality, 'language' => $language];
}

function applyTemplate(string $tpl, string $show, int $s, int $e, ?string $title, ?string $quality, ?string $lang): string {
    $padS = str_pad((string)$s, 2, '0', STR_PAD_LEFT);
    $padE = str_pad((string)$e, 2, '0', STR_PAD_LEFT);
    $result = $tpl;
    $result = str_replace('{show}',    sanitizeFilename($show), $result);
    $result = str_replace('{s}',       $padS,                   $result);
    $result = str_replace('{e}',       $padE,                   $result);
    $result = str_replace('{title}',   $title ? sanitizeFilename($title) : "Episode $padE", $result);
    $result = str_replace('{quality}', $quality ?? '',          $result);
    $result = str_replace('{lang}',    $lang ?? '',             $result);
    // Nettoie les espaces doubles laissés par un token vide (ex: {quality} absent)
    $result = preg_replace('/\s+/', ' ', $result);
    return trim($result);
}

$template = $_GET['template'] ?? '{show} S{s}E{e} {title}';
$files    = scanVideoFiles($dirPath, $VIDEO_EXT);
sort($files);

$rows = [];
$seasonCounters = [];
foreach ($files as $fullPath) {
    $filename  = basename($fullPath);
    $ext       = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
    $dirSeason = parseSeasonFromDir(dirname($fullPath));
    $parsed    = parseEpFilename($filename, $dirSeason);
    $s         = $parsed['season'];
    $seasonCounters[$s] = ($seasonCounters[$s] ?? 0) + 1;
    $e         = $seasonCounters[$s];
    $tmdbTitle = $tmdbIndex[$s][$e] ?? null;
    $ql        = parseQualityLang($filename);
    $suggested = applyTemplate($template, $showTitle, $s, $e, $tmdbTitle, $ql['quality'], $ql['language']) . '.' . $ext;

    $rows[] = [
        'path'      => $fullPath,
        'dir'       => dirname($fullPath),
        'filename'  => $filename,
        'season'    => $s,
        'episode'   => $e,
        'tmdb_title'=> $tmdbTitle,
        'quality'   => $ql['quality'],
        'lang'      => $ql['language'],
        'suggested' => $suggested,
        'changed'   => $filename !== $suggested,
    ];
}

// Trier saison→épisode
usort($rows, fn($a, $b) => $a['season'] <=> $b['season'] ?: $a['episode'] <=> $b['episode']);

echo json_encode(['ok' => true, 'show' => $showTitle, 'rows' => $rows, 'total' => count($rows)]);
