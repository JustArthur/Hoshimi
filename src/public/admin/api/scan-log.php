<?php

declare(strict_types=1);

require_once __DIR__ . '/../../../app/MacGuard.php';
MacGuard::protect();

header('Content-Type: application/json');

$jobId = preg_replace('/[^a-f0-9]/', '', $_GET['job'] ?? '');
if ($jobId === '') {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'job_id manquant']);
    exit;
}

$logFile = '/tmp/hoshimi_job_' . $jobId . '.log';
clearstatcache(true, $logFile);
if (!file_exists($logFile)) {
    echo json_encode(['ok' => true, 'lines' => [], 'done' => false, 'started' => false]);
    exit;
}

$content = file_get_contents($logFile) ?: '';
$lines   = array_values(array_filter(explode("\n", $content)));

$done  = false;
$error = false;
foreach (array_slice($lines, -3) as $line) {
    if (str_contains($line, '[HOSHIMI_DONE]'))  { $done = true; break; }
    if (str_contains($line, '[HOSHIMI_ERROR]')) { $done = true; $error = true; break; }
}

// Nettoyer les sentinelles de la sortie
$lines = array_values(array_filter($lines, fn($l) => !str_contains($l, '[HOSHIMI_')));

// Nettoyer les codes couleur ANSI
$lines = array_map(fn($l) => preg_replace('/\x1B\[[0-9;]*m/', '', $l), $lines);

// Retourner seulement les nouvelles lignes depuis l'offset
$offset = max(0, (int)($_GET['offset'] ?? 0));
$lines  = array_slice($lines, $offset);

// Persister dans l'historique et supprimer le log une fois terminé et lu
if ($done && isset($_GET['cleanup'])) {
    $type     = preg_replace('/[^a-z]/', '', $_GET['type'] ?? 'unknown');
    $histFile = '/tmp/hoshimi_scan_history.json';
    $history  = file_exists($histFile) ? (json_decode(file_get_contents($histFile), true) ?? []) : [];
    $okCount  = count(array_filter($lines, fn($l) => preg_match('/^\[ ?OK ?\]|✓/', $l)));
    $errCount = count(array_filter($lines, fn($l) => preg_match('/^\[ERR ?\]|✗/', $l)));
    array_unshift($history, [
        'type'      => $type,
        'date'      => date('c'),
        'error'     => $error,
        'ok_count'  => $okCount,
        'err_count' => $errCount,
        'lines'     => count($lines),
    ]);
    file_put_contents($histFile, json_encode(array_slice($history, 0, 100)));
    @unlink($logFile);
}

echo json_encode(['ok' => true, 'lines' => $lines, 'done' => $done, 'error' => $error, 'started' => true]);
