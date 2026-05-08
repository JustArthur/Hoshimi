<?php
// ============================================================
//  HOSHIMI — API Progression
//  GET  /api/progress/?file=...          → lit la progression
//  POST /api/progress/  body JSON        → sauvegarde
//
//  Stockage : fichier JSON local par utilisateur
// ============================================================

declare(strict_types=1);

header('Content-Type: application/json');

// Dossier de stockage des progressions (hors webroot idéalement)
$progressDir = sys_get_temp_dir() . '/hoshimi_progress';
if (!is_dir($progressDir)) {
    mkdir($progressDir, 0750, true);
}

// Identifiant "utilisateur" temporaire via session
session_start();
if (empty($_SESSION['hoshimi_uid'])) {
    $_SESSION['hoshimi_uid'] = bin2hex(random_bytes(8));
}
$uid  = $_SESSION['hoshimi_uid'];
$file = $progressDir . '/' . $uid . '.json';

// Charge les progressions existantes
$data = [];
if (file_exists($file)) {
    $raw  = file_get_contents($file);
    $data = json_decode($raw, true) ?? [];
}

$method = $_SERVER['REQUEST_METHOD'];

// ----------------------------------------------------------------
//  GET — retourne la progression d'un fichier
// ----------------------------------------------------------------
if ($method === 'GET') {
    $key      = $_GET['file'] ?? '';
    $progress = $data[$key] ?? ['position' => 0, 'duration' => 0, 'completed' => false];
    echo json_encode($progress);
    exit;
}

// ----------------------------------------------------------------
//  POST — sauvegarde la progression
//  Body : { "file": "...", "position": 123.4, "duration": 1440, "completed": false }
// ----------------------------------------------------------------
if ($method === 'POST') {
    $body = json_decode(file_get_contents('php://input'), true);

    $key       = $body['file']      ?? '';
    $position  = (float) ($body['position']  ?? 0);
    $duration  = (float) ($body['duration']  ?? 0);
    $completed = (bool)  ($body['completed'] ?? false);

    if (empty($key)) {
        http_response_code(400);
        echo json_encode(['error' => 'file manquant']);
        exit;
    }

    $data[$key] = [
        'position'   => $position,
        'duration'   => $duration,
        'completed'  => $completed,
        'updated_at' => date('c'),
    ];

    file_put_contents($file, json_encode($data, JSON_PRETTY_PRINT));
    echo json_encode(['ok' => true]);
    exit;
}

http_response_code(405);
echo json_encode(['error' => 'Méthode non autorisée']);
