#!/usr/bin/env php
<?php
// ============================================================
//  HOSHIMI — Générateur de miniatures d'épisodes
//  Supporte : Saisons, Films, OAV et Specials
// ============================================================

declare(strict_types=1);

// Chargement des variables d'environnement
if (file_exists(__DIR__ . '/../../.env')) {
    $lines = file(__DIR__ . '/../../.env', FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        if (str_starts_with(trim($line), '#') || !str_contains($line, '=')) continue;
        [$key, $value] = explode('=', $line, 2);
        $_ENV[trim($key)] = trim($value);
        putenv(trim($key) . '=' . trim($value));
    }
}

require_once __DIR__ . '/../app/Services/AnimeScanner.php';

// ---- Parsing des options CLI ----
$opts      = getopt('', ['force', 'anime:', 'at:']);
$force     = isset($opts['force']);
$onlySlug  = $opts['anime'] ?? null;
$captureAt = max(5, min(90, (int) ($opts['at'] ?? 30))); // entre 5% et 90%

// ---- Config ----
$thumbDir  = $_ENV['IMAGES_PATH'] ?? '/var/www/html/public/images';
$thumbDir  = rtrim($thumbDir, '/') . '/thumbnails';
$indexFile = $thumbDir . '/index.json';

// Créer le dossier si besoin
if (!is_dir($thumbDir)) {
    mkdir($thumbDir, 0755, true);
}

// ---- NETTOYAGE SI --force ----
if ($force && !$onlySlug) {
    echo "⚠️  Option --force détectée : Nettoyage complet du dossier...\n";
    $files = glob($thumbDir . '/*.jpg');
    foreach ($files as $file) {
        if (is_file($file)) unlink($file);
    }
    if (file_exists($indexFile)) unlink($indexFile);
    echo "   ✅ Dossier miniatures et index JSON supprimés.\n\n";
}

// Vérifie FFmpeg
exec('ffmpeg -version 2>&1', $out, $code);
if ($code !== 0) {
    echo "❌ FFmpeg introuvable.\n";
    exit(1);
}

echo "🎌 Hoshimi — Générateur de miniatures (Saisons, Films, OAV)\n";
echo "   Dossier : $thumbDir\n";
echo "   Capture à : {$captureAt}%\n";
echo "   Force : " . ($force ? 'OUI' : 'NON') . "\n\n";

// ---- Scan des animes ----
$scanner = new AnimeScanner();
$animes  = $onlySlug
    ? array_filter([$scanner->getAnimeBySlug($onlySlug)])
    : $scanner->getAllAnimes();

if (empty($animes)) {
    echo "Aucun anime trouvé.\n";
    exit(0);
}

$total     = 0;
$generated = 0;
$skipped   = 0;
$errors    = 0;

foreach ($animes as $anime) {
    echo "📁 {$anime['title']}\n";

    // Grâce à la modif du scanner, 'seasons' contient aussi Films et OAV
    foreach ($anime['seasons'] as $season) {
        echo "   🔹 {$season['label']}\n";

        foreach ($season['episodes'] as $ep) {
            $total++;
            $filePath = $ep['file_path'];

            if (!file_exists($filePath)) {
                echo "     ⚠️  Fichier introuvable : {$ep['filename']}\n";
                $errors++;
                continue;
            }

            $thumbKey  = md5($filePath);
            $thumbFile = $thumbDir . '/' . $thumbKey . '.jpg';

            if (file_exists($thumbFile) && !$force) {
                $skipped++;
                continue;
            }

            $duration = getDuration($filePath);
            if ($duration <= 0) {
                echo "     ❌ Imposssible de lire la durée : {$ep['filename']}\n";
                $errors++;
                continue;
            }

            $position = (int) ($duration * $captureAt / 100);

            // Commande FFmpeg optimisée pour la qualité et le ratio
            $cmd = sprintf(
                'ffmpeg -ss %d -i %s -vframes 1 -vf "scale=480:270:force_original_aspect_ratio=decrease,pad=480:270:(ow-iw)/2:(oh-ih)/2" -q:v 4 %s -y',
                $position,
                escapeshellarg($filePath),
                escapeshellarg($thumbFile)
            );

            exec($cmd . ' 2>&1', $output, $returnCode);

            if ($returnCode === 0 && file_exists($thumbFile)) {
                $size = round(filesize($thumbFile) / 1024);
                // Affichage adapté si c'est un film ou épisode
                $label = ($season['number'] >= 777) ? "Spécial" : "Ep." . str_pad((string)$ep['number'], 2, '0', STR_PAD_LEFT);
                echo "     ✅ $label → {$thumbKey}.jpg ({$size} Ko)\n";
                $generated++;
            } else {
                echo "     ❌ Erreur FFmpeg sur {$ep['filename']}\n";
                $errors++;
            }
        }
    }
    echo "\n";
}

// ---- Résumé ----
echo "─────────────────────────────────\n";
echo "✅ Générées  : $generated\n";
echo "⏭️  Ignorées  : $skipped\n";
echo "❌ Erreurs   : $errors\n";
echo "📊 Total     : $total fichiers traités\n";

// ---- Mise à jour de l'index JSON ----
// Si on est en mode force pour un seul anime, on garde l'ancien index et on met à jour.
// Si c'est un force global, l'index a été supprimé au début donc on repart de zéro.
$index = [];
if (file_exists($indexFile)) {
    $index = json_decode(file_get_contents($indexFile), true) ?? [];
}

foreach ($animes as $anime) {
    foreach ($anime['seasons'] as $season) {
        foreach ($season['episodes'] as $ep) {
            $key = md5($ep['file_path']);
            if (file_exists($thumbDir . '/' . $key . '.jpg')) {
                $index[$key] = '/images/thumbnails/' . $key . '.jpg';
            }
        }
    }
}

file_put_contents($indexFile, json_encode($index, JSON_PRETTY_PRINT));
echo "📄 Index JSON mis à jour : $indexFile\n";

// ----------------------------------------------------------------
// Helper : durée via ffprobe
// ----------------------------------------------------------------
function getDuration(string $filePath): float
{
    $cmd    = "ffprobe -v error -show_entries format=duration -of default=noprint_wrappers=1:nokey=1 " . escapeshellarg($filePath) . " 2>/dev/null";
    $output = trim((string) shell_exec($cmd));
    return (float) $output;
}