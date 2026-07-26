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
$action = $body['action'] ?? '';   // accept uniquement
$id     = $body['id']     ?? '';

if ($action !== 'accept' || $id === '') {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'action invalide ou id manquant']);
    exit;
}

$dataFile = __DIR__ . '/../../../data/wishboard.json';
$wishes   = file_exists($dataFile)
    ? (json_decode(file_get_contents($dataFile), true) ?: [])
    : [];

$idx = null;
foreach ($wishes as $i => $w) {
    if ($w['id'] === $id) { $idx = $i; break; }
}

if ($idx === null) {
    http_response_code(404);
    echo json_encode(['ok' => false, 'error' => 'Demande introuvable.']);
    exit;
}

$wish = $wishes[$idx];

if ($wish['status'] !== 'pending') {
    echo json_encode(['ok' => false, 'error' => 'Cette demande n\'est plus en attente.']);
    exit;
}

// ── Créer le dossier ──────────────────────────────────────────────────────────
$TYPE_MAP = [
    'anime' => ['env' => 'ANIMES_PATH',  'default' => '/media/animes'],
    'film'  => ['env' => 'FILMS_PATH',   'default' => '/media/films'],
    'serie' => ['env' => 'SERIES_PATH',  'default' => '/media/series'],
];

$type = $wish['type'] ?? 'film';
if (!isset($TYPE_MAP[$type])) {
    echo json_encode(['ok' => false, 'error' => 'Type de média inconnu.']);
    exit;
}

$cfg  = $TYPE_MAP[$type];
$root = rtrim($_ENV[$cfg['env']] ?? getenv($cfg['env']) ?: $cfg['default'], '/\\');

if (!is_dir($root)) {
    echo json_encode(['ok' => false, 'error' => "Dossier racine introuvable : $root"]);
    exit;
}

$title    = $wish['title'];
$year     = $wish['year'] ?? null;
$safeName = preg_replace('/[<>:"\/\\\\|?*]/', '', $title);
$safeName = trim(preg_replace('/\s+/', ' ', $safeName));
if ($year) $safeName .= " ($year)";

$folderPath = $root . DIRECTORY_SEPARATOR . $safeName;

if (!is_dir($folderPath)) {
    if (!mkdir($folderPath, 0775, true)) {
        echo json_encode(['ok' => false, 'error' => 'Impossible de créer le dossier (permissions ?).']);
        exit;
    }
}

$wishes[$idx]['status']      = 'in_progress';
$wishes[$idx]['resolved_at'] = date('c');
$wishes[$idx]['folder']      = $safeName;
file_put_contents($dataFile, json_encode($wishes, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT), LOCK_EX);

echo json_encode(['ok' => true, 'folder' => $safeName]);
