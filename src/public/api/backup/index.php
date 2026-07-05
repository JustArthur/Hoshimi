<?php

declare(strict_types=1);

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Cache-Control: no-store');

$storageDir = sys_get_temp_dir() . '/hoshimi_backup';

if (!is_dir($storageDir)) {
    mkdir($storageDir, 0700, true);
}

// Nettoyage des tokens expirés (>30 min)
foreach (glob($storageDir . '/*.json') ?: [] as $f) {
    if (time() - filemtime($f) > 300) {
        unlink($f);
    }
}

$method = $_SERVER['REQUEST_METHOD'];

// POST /api/backup/ — Stocke les données, retourne un token
if ($method === 'POST') {
    $body = file_get_contents('php://input');
    if (!$body || !json_validate($body)) {
        http_response_code(400);
        echo json_encode(['error' => 'Corps JSON invalide']);
        exit;
    }

    $token = bin2hex(random_bytes(8)); // 16 caractères hex
    $file  = $storageDir . '/' . $token . '.json';
    file_put_contents($file, $body);

    echo json_encode(['token' => $token, 'expires_in' => 300]);
    exit;
}

// GET /api/backup/?token=xxx — Récupère et supprime les données
if ($method === 'GET') {
    $token = preg_replace('/[^a-f0-9]/', '', $_GET['token'] ?? '');
    if (strlen($token) !== 16) {
        http_response_code(400);
        echo json_encode(['error' => 'Token invalide']);
        exit;
    }

    $file = $storageDir . '/' . $token . '.json';
    if (!file_exists($file)) {
        http_response_code(404);
        echo json_encode(['error' => 'Token introuvable ou expiré']);
        exit;
    }

    $data = file_get_contents($file);
    unlink($file); // usage unique
    echo $data;
    exit;
}

http_response_code(405);
echo json_encode(['error' => 'Méthode non autorisée']);
