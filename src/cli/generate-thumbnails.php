#!/usr/bin/env php
<?php
// ============================================================
//  HOSHIMI — Générateur Maître (Miniatures, Durées, Tailles)
//  Supporte : animes, séries, films
//
//  Usage :
//    php generate-thumbnails.php                    # tout (défaut)
//    php generate-thumbnails.php --type anime       # animes seulement
//    php generate-thumbnails.php --type serie       # séries seulement
//    php generate-thumbnails.php --type film        # films seulement
//    php generate-thumbnails.php --slug "Dandadan"  # titre précis
//    php generate-thumbnails.php --force            # tout régénérer
// ============================================================

declare(strict_types=1);

// ─── 1. Environnement ────────────────────────────────────────────────────────
if (file_exists(__DIR__ . '/../../.env')) {
    foreach (file(__DIR__ . '/../../.env', FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
        $line = trim($line);
        if ($line === '' || $line[0] === '#' || !str_contains($line, '=')) continue;
        [$k, $v] = explode('=', $line, 2);
        $_ENV[trim($k)] = trim($v);
    }
}

require_once __DIR__ . '/../app/Services/AnimeScanner.php';
require_once __DIR__ . '/../app/Services/SeriesScanner.php';
require_once __DIR__ . '/../app/Services/FilmScanner.php';

// ─── 2. Arguments CLI ────────────────────────────────────────────────────────
$opts      = getopt('', ['force', 'type:', 'slug:', 'anime:', 'at:']);
$force     = isset($opts['force']);
$type      = strtolower($opts['type'] ?? 'all');         // défaut = tout
$onlySlug  = $opts['slug'] ?? $opts['anime'] ?? null;
$captureAt = max(5, min(90, (int)($opts['at'] ?? 30)));

// ─── 3. Résolution des chemins (hôte vs Docker) ──────────────────────────────
// Si le chemin Docker n'existe pas on bascule sur le chemin hôte du .env
function resolvePath(string $containerEnvKey, string $hostEnvKey, string $fallback): string
{
    $containerPath = $_ENV[$containerEnvKey] ?? $fallback;
    if (is_dir($containerPath)) return rtrim($containerPath, '/\\');

    $hostPath = $_ENV[$hostEnvKey] ?? '';
    if ($hostPath !== '' && is_dir($hostPath)) return rtrim($hostPath, '/\\');

    return $containerPath; // sera invalide → getAllX() retournera []
}

$animesPath = resolvePath('ANIMES_PATH', 'ANIMES_HOST_PATH', '/media/animes');
$filmsPath  = resolvePath('FILMS_PATH',  'FILMS_HOST_PATH',  '/media/films');
$seriesPath = resolvePath('SERIES_PATH', 'SERIES_HOST_PATH', '/media/series');

// Injecte les chemins résolus pour que les Scanners les utilisent
$_ENV['ANIMES_PATH']  = $animesPath;
$_ENV['FILMS_PATH']   = $filmsPath;
$_ENV['SERIES_PATH']  = $seriesPath;

// Dossier miniatures : relatif au script si le chemin Docker n'existe pas
$imagesPath = $_ENV['IMAGES_PATH'] ?? '/var/www/html/public/images';
if (!is_dir($imagesPath)) {
    $imagesPath = realpath(__DIR__ . '/../public/images') ?: (__DIR__ . '/../public/images');
}
$thumbDir     = rtrim($imagesPath, '/\\') . DIRECTORY_SEPARATOR . 'thumbnails';
$indexFile    = $thumbDir . DIRECTORY_SEPARATOR . 'index.json';
$durationFile = $thumbDir . DIRECTORY_SEPARATOR . 'durations.json';
$sizeFile     = $thumbDir . DIRECTORY_SEPARATOR . 'filesizes.json';

if (!is_dir($thumbDir)) mkdir($thumbDir, 0755, true);

// ─── 4. Nettoyage si --force ─────────────────────────────────────────────────
if ($force && !$onlySlug) {
    echo "⚠️  Nettoyage complet...\n";
    array_map('unlink', glob($thumbDir . DIRECTORY_SEPARATOR . '*.jpg') ?: []);
    foreach ([$indexFile, $durationFile, $sizeFile] as $f) {
        if (file_exists($f)) unlink($f);
    }
}

// ─── 5. Chargement des caches ────────────────────────────────────────────────
$index     = file_exists($indexFile)    ? (json_decode(file_get_contents($indexFile),    true) ?: []) : [];
$durations = file_exists($durationFile) ? (json_decode(file_get_contents($durationFile), true) ?: []) : [];
$sizes     = file_exists($sizeFile)     ? (json_decode(file_get_contents($sizeFile),     true) ?: []) : [];

echo "🎌 Hoshimi — Génération des miniatures\n\n";

// ─── 6. Construction des groupes ─────────────────────────────────────────────
$mediaGroups = [];

$wantsAnime = in_array($type, ['anime', 'all'], true);
$wantsSerie = in_array($type, ['serie', 'all'], true);
$wantsFilm  = in_array($type, ['film',  'all'], true);

if ($wantsAnime) {
    $scanner = new AnimeScanner($animesPath);
    $items   = $onlySlug
        ? array_filter([$scanner->getAnimeBySlug($onlySlug)])
        : $scanner->getAllAnimes();
    $mediaGroups[] = ['label' => 'Animes', 'path' => $animesPath, 'items' => array_values($items)];
}

if ($wantsSerie) {
    $scanner = new SeriesScanner();
    $items   = $onlySlug
        ? array_filter([$scanner->getSerieBySlug($onlySlug)])
        : $scanner->getAllSeries();
    $mediaGroups[] = ['label' => 'Séries', 'path' => $seriesPath, 'items' => array_values($items)];
}

if ($wantsFilm) {
    $scanner = new FilmScanner();
    $items   = $onlySlug
        ? array_filter([$scanner->getFilmBySlug($onlySlug)])
        : $scanner->getAllFilms();
    $mediaGroups[] = ['label' => 'Films', 'path' => $filmsPath, 'items' => array_values($items)];
}

// ─── 7. Traitement ───────────────────────────────────────────────────────────
$totalDone = $totalSkip = $totalFail = 0;

foreach ($mediaGroups as $group) {
    echo "{$group['label']}";
    if (!is_dir($group['path'])) {
        echo " — ⚠️  Chemin introuvable : {$group['path']}\n\n";
        continue;
    }
    echo " ({$group['path']})\n" . str_repeat('─', 50) . "\n";

    if (empty($group['items'])) {
        echo "  (aucun média trouvé)\n\n";
        continue;
    }

    foreach ($group['items'] as $media) {
        echo "📁 {$media['title']}\n";

        foreach ($media['seasons'] as $season) {
            foreach ($season['episodes'] as $ep) {
                $path = $ep['file_path'];
                if (!file_exists($path)) {
                    echo "   ⚠️  Fichier introuvable : {$ep['filename']}\n";
                    continue;
                }

                $key         = md5($path);
                $targetThumb = $thumbDir . DIRECTORY_SEPARATOR . $key . '.jpg';

                // Taille fichier
                if (!isset($sizes[$key]) || $force) {
                    $sizes[$key] = formatBytes(filesize($path));
                }

                // Durée
                $rawSeconds = 0;
                if (!isset($durations[$key]) || $force) {
                    $rawSeconds = getRawDuration($path);
                    if ($rawSeconds > 0) $durations[$key] = formatDuration($rawSeconds);
                }

                // Miniature
                if (!file_exists($targetThumb) || $force) {
                    if ($rawSeconds <= 0) $rawSeconds = getRawDuration($path);
                    $pos = max(1, (int)($rawSeconds * $captureAt / 100));

                    $cmd = sprintf(
                        'ffmpeg -ss %d -i %s -vframes 1 -vf "scale=480:270:force_original_aspect_ratio=decrease,pad=480:270:(ow-iw)/2:(oh-ih)/2" -q:v 4 %s -y 2>/dev/null',
                        $pos,
                        escapeshellarg($path),
                        escapeshellarg($targetThumb)
                    );
                    exec($cmd);

                    if (file_exists($targetThumb)) {
                        $index[$key] = '/images/thumbnails/' . $key . '.jpg';
                        $dur   = $durations[$key] ?? '??:??';
                        $size  = $sizes[$key]      ?? '?';
                        $label = $season['label'] === 'Film' ? 'Film' : "Ep {$ep['number']}";
                        echo "   ✅ $label ($dur – $size)\n";
                        $totalDone++;
                    } else {
                        echo "   ❌ Erreur miniature : {$ep['filename']}\n";
                        $totalFail++;
                    }
                } else {
                    $index[$key] = '/images/thumbnails/' . $key . '.jpg';
                    $totalSkip++;
                }
            }
        }
    }
    echo "\n";
}

// ─── 8. Sauvegarde ───────────────────────────────────────────────────────────
file_put_contents($indexFile,    json_encode($index,     JSON_PRETTY_PRINT));
file_put_contents($durationFile, json_encode($durations, JSON_PRETTY_PRINT));
file_put_contents($sizeFile,     json_encode($sizes,     JSON_PRETTY_PRINT));

echo str_repeat('─', 50) . "\n";
echo "✨ Terminé — ✅ {$totalDone} générée(s)  ⏭  {$totalSkip} ignorée(s)  ❌ {$totalFail} erreur(s)\n";

// ─── Helpers ─────────────────────────────────────────────────────────────────

function getRawDuration(string $path): float
{
    $cmd = 'ffprobe -v error -show_entries format=duration -of default=noprint_wrappers=1:nokey=1 ' . escapeshellarg($path);
    return (float)trim((string)shell_exec($cmd));
}

function formatDuration(float $seconds): string
{
    $s = (int)$seconds;
    [$h, $m, $sec] = [(int)floor($s / 3600), (int)floor(($s % 3600) / 60), $s % 60];
    return $h > 0 ? sprintf('%02d:%02d:%02d', $h, $m, $sec) : sprintf('%02d:%02d', $m, $sec);
}

function formatBytes(int $bytes): string
{
    $units = ['B', 'KB', 'MB', 'GB', 'TB'];
    $pow   = $bytes ? (int)floor(log($bytes) / log(1024)) : 0;
    return number_format($bytes / (1024 ** $pow), 1) . ' ' . $units[$pow];
}
