<?php
// ============================================================
//  HOSHIMI — Proxy de streaming Jellyfin
//  Sert de frontière d'auth : la clé API Jellyfin ne quitte
//  jamais le serveur, le client ne voit que cette URL.
//
//  Deux modes :
//   ?path=<chemin fichier Hoshimi>  → point d'entrée, résout
//                                      l'ItemId puis sert le
//                                      master.m3u8 (réécrit)
//   ?jf=<chemin relatif Jellyfin>   → passthrough (playlists
//                                      variantes + segments)
// ============================================================
declare(strict_types=1);

require_once __DIR__ . '/../../app/Services/JellyfinClient.php';

const JELLYFIN_MAP_FILE = __DIR__ . '/../../data/jellyfin-map.json';

$allowedRoots = [
    $_ENV['ANIMES_PATH'] ?? '/media/animes',
    $_ENV['FILMS_PATH']  ?? '/media/films',
    $_ENV['SERIES_PATH'] ?? '/media/series',
];

$jellyfinUrl = rtrim($_ENV['JELLYFIN_URL'] ?? '', '/');
$apiKey      = $_ENV['JELLYFIN_API_KEY'] ?? '';

if ($jellyfinUrl === '' || $apiKey === '') {
    http_response_code(503);
    exit;
}

// ----------------------------------------------------------------
//  Passthrough : playlists variantes + segments (.m3u8, .ts, .m4s)
// ----------------------------------------------------------------
if (isset($_GET['jf'])) {
    $relative = urldecode($_GET['jf']);

    // Doit rester un chemin relatif vers Jellyfin — jamais une URL absolue
    if ($relative === '' || str_contains($relative, '://') || !str_starts_with($relative, '/')) {
        http_response_code(400);
        exit;
    }

    proxyJellyfin($jellyfinUrl, $apiKey, $relative);
    exit;
}

// ----------------------------------------------------------------
//  Point d'entrée : résolution fichier Hoshimi → ItemId Jellyfin
// ----------------------------------------------------------------
$requestedPath = urldecode($_GET['path'] ?? '');

if (empty($requestedPath)) { http_response_code(400); exit; }

$realPath = realpath($requestedPath);
if ($realPath === false || !is_file($realPath)) { http_response_code(404); exit; }

$allowed = false;
foreach ($allowedRoots as $root) {
    $realRoot = realpath($root);
    if ($realRoot && str_starts_with($realPath, $realRoot . DIRECTORY_SEPARATOR)) {
        $allowed = true;
        break;
    }
}
if (!$allowed) { http_response_code(403); exit; }

$map = file_exists(JELLYFIN_MAP_FILE)
    ? (json_decode((string) file_get_contents(JELLYFIN_MAP_FILE), true) ?: [])
    : [];

$itemId = $map[$realPath] ?? null;
if ($itemId === null) {
    // Pas encore mappé côté Jellyfin (item récent, scan pas encore passé)
    http_response_code(404);
    exit;
}

$audioStreamIndex = isset($_GET['audio']) ? (int) $_GET['audio'] : null;

$client      = new JellyfinClient($jellyfinUrl, $apiKey);
$masterPath  = $client->getTranscodingUrl($itemId, $audioStreamIndex);
if ($masterPath === null) {
    http_response_code(502);
    exit;
}
proxyJellyfin($jellyfinUrl, $apiKey, $masterPath);

// ----------------------------------------------------------------
//  Fonctions
// ----------------------------------------------------------------

function proxyJellyfin(string $jellyfinUrl, string $apiKey, string $relativePath): void
{
    $url        = $jellyfinUrl . $relativePath;
    $isManifest = str_contains(parse_url($relativePath, PHP_URL_PATH) ?? '', '.m3u8');

    header('Cache-Control: no-cache');
    header('Access-Control-Allow-Origin: *');

    $ch = curl_init($url);

    if ($isManifest) {
        // Manifest : petit fichier texte, doit être entièrement lu pour être réécrit
        curl_setopt_array($ch, [
            CURLOPT_HTTPHEADER     => ['X-Emby-Token: ' . $apiKey],
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => 30,
        ]);

        $body     = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($body === false || $httpCode >= 400) {
            http_response_code($httpCode >= 400 ? $httpCode : 502);
            exit;
        }

        header('Content-Type: application/vnd.apple.mpegurl');
        echo rewriteManifest((string) $body, $relativePath);
        return;
    }

    // Segment binaire (.ts/.m4s) : streaming direct, pas de bufferisation
    // (évite de doubler la latence de démarrage du transcodage Jellyfin)
    $headersSent = false;
    curl_setopt_array($ch, [
        CURLOPT_HTTPHEADER     => ['X-Emby-Token: ' . $apiKey],
        CURLOPT_TIMEOUT        => 60,
        CURLOPT_HEADERFUNCTION => function ($curl, $header) use (&$headersSent) {
            if (!$headersSent && stripos($header, 'content-type:') === 0) {
                header('Content-Type: ' . trim(substr($header, 13)));
                $headersSent = true;
            }
            return strlen($header);
        },
        CURLOPT_WRITEFUNCTION => function ($curl, $chunk) {
            echo $chunk;
            flush();
            return strlen($chunk);
        },
    ]);

    while (ob_get_level()) ob_end_flush();

    $ok       = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($ok === false && !$headersSent) {
        http_response_code($httpCode >= 400 ? $httpCode : 502);
    }
}

// Réécrit chaque URI de segment/playlist référencée dans un manifest HLS
// pour qu'elle repasse par ce proxy (le client ne parle jamais à Jellyfin directement)
function rewriteManifest(string $manifest, string $currentRelativePath): string
{
    $baseDir = rtrim(dirname(parse_url($currentRelativePath, PHP_URL_PATH) ?? '/'), '/');

    $lines = explode("\n", $manifest);
    foreach ($lines as &$line) {
        $trimmed = trim($line);
        if ($trimmed === '' || str_starts_with($trimmed, '#')) {
            continue;
        }

        // URI déjà absolue (http/https) ou relative au manifest
        if (preg_match('#^https?://#i', $trimmed)) {
            $parsed = parse_url($trimmed);
            $target = ($parsed['path'] ?? '') . (isset($parsed['query']) ? '?' . $parsed['query'] : '');
        } elseif (str_starts_with($trimmed, '/')) {
            $target = $trimmed;
        } else {
            $target = $baseDir . '/' . $trimmed;
        }

        $line = '/stream/jellyfin.php?jf=' . urlencode($target);
    }

    return implode("\n", $lines);
}
