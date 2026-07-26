<?php

declare(strict_types=1);

require_once __DIR__ . '/../../../app/MacGuard.php';
MacGuard::protect();

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['ok' => false]);
    exit;
}

$histFile = '/tmp/hoshimi_scan_history.json';
if (file_exists($histFile)) {
    unlink($histFile);
}

echo json_encode(['ok' => true]);
