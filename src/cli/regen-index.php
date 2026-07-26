<?php

declare(strict_types=1);

require_once __DIR__ . '/../app/IndexManager.php';

$t0     = microtime(true);
$counts = IndexManager::regenerateAll();
$ms     = round((microtime(true) - $t0) * 1000);

foreach ($counts as $type => $count) {
    if ($type === 'time_ms') continue;
    $status = $count >= 0 ? "✓ $count items" : '✗ erreur';
    echo "[" . date('Y-m-d H:i:s') . "] $type : $status\n";
}

echo "[" . date('Y-m-d H:i:s') . "] Index régénéré en {$ms}ms\n";
