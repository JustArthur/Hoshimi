<?php

declare(strict_types=1);

require_once __DIR__ . '/../../../app/MacGuard.php';
MacGuard::protect();

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['ok' => false, 'error' => 'method not allowed']);
    exit;
}

$body   = json_decode(file_get_contents('php://input'), true) ?? [];
$action = $body['action'] ?? '';

// ── Vider le cache PHP ─────────────────────────────────────────────────────────
if ($action === 'clear_cache') {
    $files   = glob('/tmp/hoshimi_scanner_*.json') ?: [];
    $cleared = 0;
    foreach ($files as $f) {
        if (@unlink($f)) $cleared++;
    }
    echo json_encode(['ok' => true, 'cleared' => $cleared]);
    exit;
}

// ── Helpers communs ───────────────────────────────────────────────────────────
$TYPE_MAP = [
    'anime' => ['env' => 'ANIMES_PATH', 'default' => '/media/animes'],
    'film'  => ['env' => 'FILMS_PATH',  'default' => '/media/films'],
    'serie' => ['env' => 'SERIES_PATH', 'default' => '/media/series'],
];

function resolveRoot(array $info): string {
    return $_ENV[$info['env']] ?? getenv($info['env']) ?: $info['default'];
}

function launchPython(string $pyScript, string $args, string &$jobId): string {
    $jobId   = bin2hex(random_bytes(8));
    $logFile = '/tmp/hoshimi_job_' . $jobId . '.log';
    $cmd = sprintf(
        'nohup env PYTHONUNBUFFERED=1 python3 -u %s %s >> %s 2>&1 && echo "[HOSHIMI_DONE]" >> %s || echo "[HOSHIMI_ERROR]" >> %s',
        escapeshellarg($pyScript),
        $args,
        escapeshellarg($logFile),
        escapeshellarg($logFile),
        escapeshellarg($logFile)
    );
    exec($cmd . ' &');
    return $logFile;
}

// ── Scan batch (skip existants) ───────────────────────────────────────────────
if ($action === 'fetch_meta') {
    $type = $body['type'] ?? '';
    if (!isset($TYPE_MAP[$type])) {
        http_response_code(400);
        echo json_encode(['ok' => false, 'error' => 'type invalide']);
        exit;
    }

    $scanPath = resolveRoot($TYPE_MAP[$type]);
    $pyScript = realpath(__DIR__ . '/../../../cli/fetch_metadata.py');
    if (!$pyScript || !is_file($pyScript)) {
        echo json_encode(['ok' => false, 'error' => 'script fetch_metadata.py introuvable']);
        exit;
    }

    $jobId = '';
    launchPython($pyScript, sprintf('--type %s --scan %s', escapeshellarg($type), escapeshellarg($scanPath)), $jobId);
    echo json_encode(['ok' => true, 'job_id' => $jobId]);
    exit;
}

// ── Fetch oeuvre précise (--dir) ──────────────────────────────────────────────
if ($action === 'fetch_one') {
    $type = $body['type'] ?? '';
    $name = $body['name'] ?? '';

    if (!isset($TYPE_MAP[$type]) || $name === '') {
        http_response_code(400);
        echo json_encode(['ok' => false, 'error' => 'type ou name manquant']);
        exit;
    }

    $root    = realpath(resolveRoot($TYPE_MAP[$type]));
    $dirPath = $root ? realpath($root . DIRECTORY_SEPARATOR . $name) : false;

    if (!$dirPath || !$root || !str_starts_with($dirPath, $root . DIRECTORY_SEPARATOR)) {
        http_response_code(403);
        echo json_encode(['ok' => false, 'error' => 'chemin invalide']);
        exit;
    }

    $pyScript = realpath(__DIR__ . '/../../../cli/fetch_metadata.py');
    if (!$pyScript || !is_file($pyScript)) {
        echo json_encode(['ok' => false, 'error' => 'script fetch_metadata.py introuvable']);
        exit;
    }

    $tmdbId  = isset($body['tmdb_id']) ? (int)$body['tmdb_id'] : null;
    $idArg   = $tmdbId ? ' --tmdb-id ' . $tmdbId : '';

    $jobId = '';
    launchPython($pyScript, sprintf('--type %s --dir %s%s', escapeshellarg($type), escapeshellarg($dirPath), $idArg), $jobId);
    echo json_encode(['ok' => true, 'job_id' => $jobId]);
    exit;
}

// ── Rescan forcé (--force) ────────────────────────────────────────────────────
if ($action === 'force_scan') {
    $type = $body['type'] ?? '';
    if (!isset($TYPE_MAP[$type])) {
        http_response_code(400);
        echo json_encode(['ok' => false, 'error' => 'type invalide']);
        exit;
    }

    $scanPath = resolveRoot($TYPE_MAP[$type]);
    $pyScript = realpath(__DIR__ . '/../../../cli/fetch_metadata.py');
    if (!$pyScript || !is_file($pyScript)) {
        echo json_encode(['ok' => false, 'error' => 'script fetch_metadata.py introuvable']);
        exit;
    }

    $jobId = '';
    launchPython($pyScript, sprintf('--type %s --force %s', escapeshellarg($type), escapeshellarg($scanPath)), $jobId);
    echo json_encode(['ok' => true, 'job_id' => $jobId]);
    exit;
}

// ── Re-fetch complet d'un seul média (depuis comparateur TMDB) ───────────────
if ($action === 'refetch_meta') {
    $type = $body['type'] ?? '';
    $name = $body['name'] ?? '';

    if (!isset($TYPE_MAP[$type]) || $name === '') {
        http_response_code(400);
        echo json_encode(['ok' => false, 'error' => 'type ou name manquant']);
        exit;
    }

    $root    = realpath(resolveRoot($TYPE_MAP[$type]));
    $dirPath = $root ? realpath($root . DIRECTORY_SEPARATOR . $name) : false;

    if (!$dirPath || !$root || !str_starts_with($dirPath, $root . DIRECTORY_SEPARATOR)) {
        http_response_code(403);
        echo json_encode(['ok' => false, 'error' => 'chemin invalide']);
        exit;
    }

    $pyScript = realpath(__DIR__ . '/../../../cli/fetch_metadata.py');
    if (!$pyScript || !is_file($pyScript)) {
        echo json_encode(['ok' => false, 'error' => 'script fetch_metadata.py introuvable']);
        exit;
    }

    $jobId = '';
    launchPython($pyScript, sprintf('--type %s --dir %s', escapeshellarg($type), escapeshellarg($dirPath)), $jobId);
    echo json_encode(['ok' => true, 'job_id' => $jobId]);
    exit;
}

// ── Refresh épisodes/saisons d'un seul média ─────────────────────────────────
if ($action === 'seasons_meta') {
    $type = $body['type'] ?? '';
    $name = $body['name'] ?? '';

    if (!isset($TYPE_MAP[$type]) || $name === '') {
        http_response_code(400);
        echo json_encode(['ok' => false, 'error' => 'type ou name manquant']);
        exit;
    }

    $root    = realpath(resolveRoot($TYPE_MAP[$type]));
    $dirPath = $root ? realpath($root . DIRECTORY_SEPARATOR . $name) : false;

    if (!$dirPath || !$root || !str_starts_with($dirPath, $root . DIRECTORY_SEPARATOR)) {
        http_response_code(403);
        echo json_encode(['ok' => false, 'error' => 'chemin invalide']);
        exit;
    }

    $pyScript = realpath(__DIR__ . '/../../../cli/fetch_metadata.py');
    if (!$pyScript || !is_file($pyScript)) {
        echo json_encode(['ok' => false, 'error' => 'script fetch_metadata.py introuvable']);
        exit;
    }

    $jobId = '';
    launchPython($pyScript, sprintf('--type %s --seasons %s', escapeshellarg($type), escapeshellarg($dirPath)), $jobId);
    echo json_encode(['ok' => true, 'job_id' => $jobId]);
    exit;
}

// ── Mise à jour champs volatils d'un seul média ───────────────────────────────
if ($action === 'update_meta') {
    $type = $body['type'] ?? '';
    $name = $body['name'] ?? '';

    if (!isset($TYPE_MAP[$type]) || $name === '') {
        http_response_code(400);
        echo json_encode(['ok' => false, 'error' => 'type ou name manquant']);
        exit;
    }

    $root    = realpath(resolveRoot($TYPE_MAP[$type]));
    $dirPath = $root ? realpath($root . DIRECTORY_SEPARATOR . $name) : false;

    if (!$dirPath || !$root || !str_starts_with($dirPath, $root . DIRECTORY_SEPARATOR)) {
        http_response_code(403);
        echo json_encode(['ok' => false, 'error' => 'chemin invalide']);
        exit;
    }

    $pyScript = realpath(__DIR__ . '/../../../cli/fetch_metadata.py');
    if (!$pyScript || !is_file($pyScript)) {
        echo json_encode(['ok' => false, 'error' => 'script fetch_metadata.py introuvable']);
        exit;
    }

    $jobId = '';
    launchPython($pyScript, sprintf('--type %s --update %s', escapeshellarg($type), escapeshellarg($dirPath)), $jobId);
    echo json_encode(['ok' => true, 'job_id' => $jobId]);
    exit;
}

// ── Générer thumbnail ─────────────────────────────────────────────────────────
if ($action === 'gen_thumb') {
    $name     = $body['name'] ?? '';
    $pyScript = realpath(__DIR__ . '/../../../cli/generate-thumbnails.php');
    if (!$pyScript || !is_file($pyScript)) {
        echo json_encode(['ok' => false, 'error' => 'script generate-thumbnails.php introuvable']);
        exit;
    }
    $cmd = sprintf('nohup php %s --slug %s > /dev/null 2>&1', escapeshellarg($pyScript), escapeshellarg($name));
    exec($cmd . ' &');
    echo json_encode(['ok' => true]);
    exit;
}

// ── Re-télécharger uniquement la cover ───────────────────────────────────────
if ($action === 'redownload_cover') {
    $type  = $body['type'] ?? '';
    $name  = $body['name'] ?? '';
    $force = !empty($body['force']);

    if (!isset($TYPE_MAP[$type]) || $name === '') {
        http_response_code(400);
        echo json_encode(['ok' => false, 'error' => 'type ou name manquant']);
        exit;
    }

    $root    = realpath(resolveRoot($TYPE_MAP[$type]));
    $dirPath = $root ? realpath($root . DIRECTORY_SEPARATOR . $name) : false;

    if (!$dirPath || !$root || !str_starts_with($dirPath, $root . DIRECTORY_SEPARATOR)) {
        http_response_code(403);
        echo json_encode(['ok' => false, 'error' => 'chemin invalide']);
        exit;
    }

    $pyScript = realpath(__DIR__ . '/../../../cli/fetch_metadata.py');
    if (!$pyScript || !is_file($pyScript)) {
        echo json_encode(['ok' => false, 'error' => 'script fetch_metadata.py introuvable']);
        exit;
    }

    $forceArg = $force ? ' --cover-force' : '';
    $jobId = '';
    launchPython($pyScript, sprintf('--type %s --cover-only %s%s', escapeshellarg($type), escapeshellarg($dirPath), $forceArg), $jobId);
    echo json_encode(['ok' => true, 'job_id' => $jobId]);
    exit;
}

http_response_code(400);
echo json_encode(['ok' => false, 'error' => 'action inconnue']);
