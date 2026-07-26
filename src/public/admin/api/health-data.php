<?php
// ============================================================
//  HOSHIMI — Admin API : Santé des données
//  GET /admin/api/health-data.php?action=...
//
//  Actions :
//    episodes  — valide les comptes d'épisodes vs fichiers locaux
//    files     — détecte les doublons de fichiers vidéo
//    links     — vérifie les liens d'images cassés (curl)
// ============================================================

declare(strict_types=1);

require_once __DIR__ . '/../../../app/MacGuard.php';
MacGuard::protect();

header('Content-Type: application/json');

$action = $_GET['action'] ?? '';

$TYPE_MAP = [
    'anime' => ['env' => 'ANIMES_PATH', 'default' => '/media/animes', 'label' => 'Anime'],
    'film'  => ['env' => 'FILMS_PATH',  'default' => '/media/films',  'label' => 'Film'],
    'serie' => ['env' => 'SERIES_PATH', 'default' => '/media/series', 'label' => 'Série'],
];

$VIDEO_EXT = ['mkv', 'mp4', 'avi', 'webm', 'mov'];

function getRoot(string $type, array $map): string {
    $info = $map[$type];
    return $_ENV[$info['env']] ?? getenv($info['env']) ?: $info['default'];
}

function dirSize(string $dir): int {
    $size = 0;
    try {
        $it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($dir, FilesystemIterator::SKIP_DOTS));
        foreach ($it as $f) { if ($f->isFile()) $size += $f->getSize(); }
    } catch (\Throwable) {}
    return $size;
}

function countVideoFiles(string $dir): int {
    global $VIDEO_EXT;
    $count = 0;
    try {
        $it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($dir, FilesystemIterator::SKIP_DOTS));
        foreach ($it as $f) {
            if (in_array(strtolower($f->getExtension()), $VIDEO_EXT, true)) $count++;
        }
    } catch (\Throwable) {}
    return $count;
}

// ── Validation des épisodes ───────────────────────────────────────────────────
if ($action === 'episodes') {
    $results = [];

    foreach ($TYPE_MAP as $type => $info) {
        $root = getRoot($type, $TYPE_MAP);
        if (!is_dir($root)) continue;

        foreach (array_diff(scandir($root), ['.', '..']) as $dir) {
            $dirPath = $root . '/' . $dir;
            if (!is_dir($dirPath)) continue;

            $meta = @json_decode(@file_get_contents($dirPath . '/metadata.json') ?: '{}', true) ?: [];
            $expected = (int)($meta['episodes_total'] ?? 0);
            if ($expected <= 0) continue; // pas de donnée TMDB

            $actual = countVideoFiles($dirPath);
            if ($actual === 0) continue; // pas de fichiers locaux du tout

            $diff = $actual - $expected;
            if ($diff === 0) continue; // parfait

            $results[] = [
                'type'     => $type,
                'label'    => $info['label'],
                'name'     => $dir,
                'title'    => $meta['title'] ?? $dir,
                'expected' => $expected,
                'actual'   => $actual,
                'diff'     => $diff,
                'href'     => '/admin/edit.php?type=' . urlencode($type) . '&name=' . urlencode($dir),
            ];
        }
    }

    // Trier : écarts les plus importants en premier
    usort($results, fn($a, $b) => abs($b['diff']) <=> abs($a['diff']));

    echo json_encode(['ok' => true, 'results' => $results, 'total' => count($results)]);
    exit;
}

// ── Doublons de fichiers vidéo ────────────────────────────────────────────────
if ($action === 'files') {
    // Index : basename (sans extension) → liste de chemins
    $index = [];

    foreach ($TYPE_MAP as $type => $info) {
        $root = getRoot($type, $TYPE_MAP);
        if (!is_dir($root)) continue;

        try {
            $it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS));
            foreach ($it as $f) {
                if (!in_array(strtolower($f->getExtension()), $VIDEO_EXT, true)) continue;
                $base = $f->getBasename('.' . $f->getExtension()); // nom sans ext
                // Normaliser : retirer résolution/qualité communes
                $norm = preg_replace('/\b(1080p|720p|480p|2160p|4k|bluray|webrip|web-dl|x264|x265|hevc|avc)\b/i', '', $base);
                $norm = trim(preg_replace('/\s+/', ' ', $norm));
                $index[$norm][] = ['path' => $f->getPathname(), 'type' => $type, 'label' => $info['label']];
            }
        } catch (\Throwable) {}
    }

    $duplicates = [];
    foreach ($index as $norm => $files) {
        if (count($files) <= 1) continue;
        // Doublons inter-dossiers seulement (même show → normal)
        $dirs = array_unique(array_map(fn($f) => dirname($f['path']), $files));
        if (count($dirs) <= 1) continue; // tous dans le même dossier
        $duplicates[] = ['name' => $norm, 'files' => $files];
    }

    usort($duplicates, fn($a, $b) => count($b['files']) <=> count($a['files']));

    echo json_encode(['ok' => true, 'duplicates' => array_slice($duplicates, 0, 50), 'total' => count($duplicates)]);
    exit;
}

// ── Vérification des liens cassés ─────────────────────────────────────────────
if ($action === 'links') {
    if (!function_exists('curl_multi_init')) {
        echo json_encode(['ok' => false, 'error' => 'curl non disponible']);
        exit;
    }

    // Collecter toutes les URLs externes
    $urlMap = []; // url → ['type', 'name', 'title', 'field']

    foreach ($TYPE_MAP as $type => $info) {
        $root = getRoot($type, $TYPE_MAP);
        if (!is_dir($root)) continue;

        foreach (array_diff(scandir($root), ['.', '..']) as $dir) {
            $dirPath = $root . '/' . $dir;
            if (!is_dir($dirPath)) continue;
            $meta = @json_decode(@file_get_contents($dirPath . '/metadata.json') ?: '{}', true) ?: [];

            foreach (['cover' => 'Cover', 'banner' => 'Banner', 'cover_url' => 'Cover'] as $field => $fieldLabel) {
                $url = $meta[$field] ?? '';
                if (!$url || !str_starts_with($url, 'http')) continue;
                if (isset($urlMap[$url])) continue; // déjà dans la liste
                $urlMap[$url] = [
                    'type'  => $type,
                    'label' => $info['label'],
                    'name'  => $dir,
                    'title' => $meta['title'] ?? $dir,
                    'field' => $fieldLabel,
                    'href'  => '/admin/edit.php?type=' . urlencode($type) . '&name=' . urlencode($dir),
                ];
            }
        }
    }

    // curl_multi HEAD sur toutes les URLs
    $urls    = array_keys($urlMap);
    $broken  = [];
    $checked = 0;
    $batchSize = 20;

    for ($i = 0; $i < count($urls); $i += $batchSize) {
        $batch = array_slice($urls, $i, $batchSize);
        $mh    = curl_multi_init();
        $handles = [];

        foreach ($batch as $url) {
            $ch = curl_init($url);
            curl_setopt_array($ch, [
                CURLOPT_NOBODY         => true,
                CURLOPT_FOLLOWLOCATION => true,
                CURLOPT_TIMEOUT        => 8,
                CURLOPT_USERAGENT      => 'Hoshimi-Admin/1.0',
                CURLOPT_SSL_VERIFYPEER => false,
            ]);
            curl_multi_add_handle($mh, $ch);
            $handles[$url] = $ch;
        }

        do { curl_multi_exec($mh, $running); } while ($running > 0);

        foreach ($handles as $url => $ch) {
            $code = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $checked++;
            if ($code === 0 || $code >= 400) {
                $broken[] = array_merge($urlMap[$url], ['url' => $url, 'code' => $code]);
            }
            curl_multi_remove_handle($mh, $ch);
            curl_close($ch);
        }
        curl_multi_close($mh);
    }

    echo json_encode([
        'ok'      => true,
        'broken'  => $broken,
        'checked' => $checked,
        'total'   => count($broken),
    ]);
    exit;
}

// ── Poids par média ───────────────────────────────────────────────────────────
if ($action === 'sizes') {
    $results = [];
    $total   = 0;

    foreach ($TYPE_MAP as $type => $info) {
        $root = getRoot($type, $TYPE_MAP);
        if (!is_dir($root)) continue;

        foreach (array_diff(scandir($root), ['.', '..']) as $dir) {
            $dirPath = $root . '/' . $dir;
            if (!is_dir($dirPath)) continue;

            $meta  = @json_decode(@file_get_contents($dirPath . '/metadata.json') ?: '{}', true) ?: [];
            $size  = dirSize($dirPath);
            if ($size === 0) continue;
            $total += $size;

            $results[] = [
                'type'  => $type,
                'label' => $info['label'],
                'name'  => $dir,
                'title' => $meta['title'] ?? $dir,
                'size'  => $size,
                'href'  => '/admin/edit.php?type=' . urlencode($type) . '&name=' . urlencode($dir),
            ];
        }
    }

    usort($results, fn($a, $b) => $b['size'] <=> $a['size']);

    echo json_encode(['ok' => true, 'results' => $results, 'total' => $total]);
    exit;
}

// ── Liste des médias pour un type ─────────────────────────────────────────────
if ($action === 'media_list') {
    $type = $_GET['type'] ?? '';
    if (!isset($TYPE_MAP[$type])) {
        echo json_encode(['ok' => false, 'error' => 'Type inconnu']);
        exit;
    }

    $root  = getRoot($type, $TYPE_MAP);
    if (!is_dir($root)) {
        echo json_encode(['ok' => true, 'items' => []]);
        exit;
    }

    $items = [];
    foreach (array_diff(scandir($root), ['.', '..']) as $dir) {
        if (!is_dir($root . '/' . $dir)) continue;
        $meta    = @json_decode(@file_get_contents($root . '/' . $dir . '/metadata.json') ?: '{}', true) ?: [];
        $items[] = ['name' => $dir, 'title' => $meta['title'] ?? $dir];
    }
    usort($items, fn($a, $b) => strcmp($a['title'], $b['title']));

    echo json_encode(['ok' => true, 'items' => $items]);
    exit;
}

// ── Infos vidéo via ffprobe ───────────────────────────────────────────────────
if ($action === 'ffprobe') {
    $type = $_GET['type'] ?? '';
    $name = basename($_GET['name'] ?? ''); // basename empêche la traversée de chemin

    if (!isset($TYPE_MAP[$type]) || $name === '') {
        echo json_encode(['ok' => false, 'error' => 'Paramètres invalides']);
        exit;
    }

    $root    = getRoot($type, $TYPE_MAP);
    $dirPath = realpath($root . '/' . $name);
    $rootReal = realpath($root);

    if (!$dirPath || !$rootReal || !is_dir($dirPath) || !str_starts_with($dirPath, $rootReal)) {
        echo json_encode(['ok' => false, 'error' => 'Dossier introuvable']);
        exit;
    }

    $ffprobe = trim((string)shell_exec('which ffprobe 2>/dev/null')) ?: 'ffprobe';

    $files = [];
    try {
        $it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($dirPath, FilesystemIterator::SKIP_DOTS));
        foreach ($it as $f) {
            if (in_array(strtolower($f->getExtension()), $VIDEO_EXT, true)) {
                $files[] = $f->getPathname();
            }
        }
    } catch (\Throwable) {}
    sort($files);

    $episodes  = [];
    $totalSize = 0;

    foreach ($files as $file) {
        $cmd    = $ffprobe . ' -v quiet -print_format json -show_streams -show_format ' . escapeshellarg($file) . ' 2>/dev/null';
        $output = shell_exec($cmd);
        $data   = @json_decode($output ?: '{}', true) ?: [];

        $videoStream = null;
        $audioStream = null;
        foreach ($data['streams'] ?? [] as $stream) {
            if ($stream['codec_type'] === 'video' && $videoStream === null) $videoStream = $stream;
            if ($stream['codec_type'] === 'audio' && $audioStream === null) $audioStream = $stream;
        }

        $format     = $data['format'] ?? [];
        $bitrate_kb = isset($format['bit_rate']) ? (int)round((int)$format['bit_rate'] / 1000) : null;
        $duration_s = isset($format['duration']) ? (int)round((float)$format['duration']) : null;
        $size       = (int)($format['size'] ?? filesize($file));
        $totalSize += $size;

        $episodes[] = [
            'name'       => basename($file),
            'codec'      => $videoStream['codec_name'] ?? null,
            'width'      => $videoStream['width']      ?? null,
            'height'     => $videoStream['height']     ?? null,
            'bitrate_kb' => $bitrate_kb,
            'duration_s' => $duration_s,
            'size'       => $size,
            'audio'      => $audioStream['codec_name'] ?? null,
        ];
    }

    echo json_encode(['ok' => true, 'episodes' => $episodes, 'total' => count($episodes), 'total_size' => $totalSize]);
    exit;
}

echo json_encode(['ok' => false, 'error' => 'action inconnue']);
