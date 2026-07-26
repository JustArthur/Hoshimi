<?php
// ============================================================
//  HOSHIMI — Admin API : Maintenance
//  POST /admin/api/maintenance.php  { "action": "..." }
//
//  Actions :
//    purge_webp    — supprime tous les caches .webp générés
//    purge_search  — invalide l'index de recherche + cache admin
// ============================================================

declare(strict_types=1);

require_once __DIR__ . '/../../../app/MacGuard.php';
MacGuard::protect();

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405); echo json_encode(['ok' => false]); exit;
}

$body   = json_decode(file_get_contents('php://input'), true) ?? [];
$action = $body['action'] ?? '';

$ROOTS = array_filter([
    $_ENV['ANIMES_PATH'] ?? getenv('ANIMES_PATH') ?: '/media/animes',
    $_ENV['FILMS_PATH']  ?? getenv('FILMS_PATH')  ?: '/media/films',
    $_ENV['SERIES_PATH'] ?? getenv('SERIES_PATH') ?: '/media/series',
], 'is_dir');

// ── Purge cache WebP ──────────────────────────────────────────────────────────
if ($action === 'purge_webp') {
    $deleted = 0;
    $freed   = 0;

    $webpCacheDir = sys_get_temp_dir() . '/hoshimi_webp';
    if (is_dir($webpCacheDir)) {
        $it = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($webpCacheDir, FilesystemIterator::SKIP_DOTS)
        );
        foreach ($it as $file) {
            $freed += $file->getSize();
            if (@unlink($file->getPathname())) $deleted++;
        }
    }

    echo json_encode([
        'ok'        => true,
        'deleted'   => $deleted,
        'freed'     => $freed,
        'freed_fmt' => $freed >= 1_048_576
            ? round($freed / 1_048_576, 1) . ' Mo'
            : round($freed / 1024, 1) . ' Ko',
    ]);
    exit;
}

// ── Invalider l'index de recherche ────────────────────────────────────────────
if ($action === 'purge_search') {
    $files = [
        sys_get_temp_dir() . '/hoshimi_search_index.json',
        sys_get_temp_dir() . '/hoshimi_admin_scan.json',
        sys_get_temp_dir() . '/hoshimi_volumes.json',
    ];
    $deleted = 0;
    foreach ($files as $f) {
        if (file_exists($f) && @unlink($f)) $deleted++;
    }
    echo json_encode(['ok' => true, 'deleted' => $deleted]);
    exit;
}

// ── Vider le wishboard ───────────────────────────────────────────────────────
if ($action === 'clear_wishboard') {
    $file = __DIR__ . '/../../../data/wishboard.json';
    $ok   = file_put_contents($file, '[]') !== false;
    echo json_encode(['ok' => $ok]);
    exit;
}

echo json_encode(['ok' => false, 'error' => 'action inconnue']);
