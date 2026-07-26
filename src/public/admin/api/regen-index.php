<?php

declare(strict_types=1);

header('Content-Type: application/json');

require_once __DIR__ . '/../../../app/MacGuard.php';
MacGuard::protect();

require_once __DIR__ . '/../../../app/IndexManager.php';

$type = $_POST['type'] ?? $_GET['type'] ?? 'all';

try {
    if ($type === 'all') {
        $result = IndexManager::regenerateAll();
    } else {
        $t0     = microtime(true);
        $count  = IndexManager::regenerate($type);
        $result = [$type => $count, 'time_ms' => (int) round((microtime(true) - $t0) * 1000)];
    }
    echo json_encode(['success' => true, 'result' => $result]);
} catch (\Throwable $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
