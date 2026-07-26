<?php

declare(strict_types=1);

require_once __DIR__ . '/../../../app/MacGuard.php';
MacGuard::protect();

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405); echo json_encode(['ok' => false, 'error' => 'method not allowed']); exit;
}

$body  = json_decode(file_get_contents('php://input'), true) ?? [];
$renames = $body['renames'] ?? [];

if (empty($renames)) {
    echo json_encode(['ok' => false, 'error' => 'aucun fichier']); exit;
}

$ALLOWED_ROOTS = array_filter([
    $_ENV['ANIMES_PATH'] ?? getenv('ANIMES_PATH') ?: '/media/animes',
    $_ENV['FILMS_PATH']  ?? getenv('FILMS_PATH')  ?: '/media/films',
    $_ENV['SERIES_PATH'] ?? getenv('SERIES_PATH') ?: '/media/series',
]);

function isAllowedPath(string $path, array $roots): bool {
    foreach ($roots as $root) {
        $root = rtrim($root, '/\\');
        if (str_starts_with($path, $root . '/') || str_starts_with($path, $root . DIRECTORY_SEPARATOR)) {
            return true;
        }
    }
    return false;
}

$VIDEO_EXT = ['mkv', 'mp4', 'avi', 'webm'];

$done   = [];
$errors = [];

foreach ($renames as $r) {
    $from    = $r['from']    ?? '';
    $toName  = basename($r['to'] ?? '');
    $dir     = dirname($from);
    $to      = $dir . DIRECTORY_SEPARATOR . $toName;

    // Sécurité : chemin autorisé
    if (!isAllowedPath($from, $ALLOWED_ROOTS) || !isAllowedPath($to, $ALLOWED_ROOTS)) {
        $errors[] = ['file' => basename($from), 'error' => 'chemin non autorisé']; continue;
    }

    // Extension vidéo uniquement
    if (!in_array(strtolower(pathinfo($from, PATHINFO_EXTENSION)), $VIDEO_EXT, true)) {
        $errors[] = ['file' => basename($from), 'error' => 'extension non autorisée']; continue;
    }

    // Fichier source doit exister
    if (!file_exists($from)) {
        $errors[] = ['file' => basename($from), 'error' => 'fichier introuvable']; continue;
    }

    // Destination ne doit pas exister (sauf si même fichier)
    if ($from !== $to && file_exists($to)) {
        $errors[] = ['file' => basename($from), 'error' => 'destination déjà occupée']; continue;
    }

    if ($from === $to) {
        $done[] = basename($from); continue; // rien à faire
    }

    if (@rename($from, $to)) {
        $done[] = basename($to);
    } else {
        $errors[] = ['file' => basename($from), 'error' => 'rename() échoué'];
    }
}

echo json_encode(['ok' => true, 'done' => count($done), 'errors' => $errors]);
