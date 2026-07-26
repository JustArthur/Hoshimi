<?php

declare(strict_types=1);

require_once __DIR__ . '/../../../app/MacGuard.php';
MacGuard::protect();

header('Content-Type: application/json');

$mediaTypes = [
    'anime' => ['env' => 'ANIMES_PATH', 'default' => '/media/animes', 'label' => 'Anime'],
    'film'  => ['env' => 'FILMS_PATH',  'default' => '/media/films',  'label' => 'Film'],
    'serie' => ['env' => 'SERIES_PATH', 'default' => '/media/series', 'label' => 'Série'],
];

$cacheFile = '/tmp/hoshimi_volumes.json';
$cacheTtl  = 300; // 5 minutes — du -sb est lent

if (file_exists($cacheFile) && (time() - filemtime($cacheFile) < $cacheTtl)) {
    echo file_get_contents($cacheFile);
    exit;
}

function fmtBytes(int|float $bytes): string {
    if ($bytes >= 1_099_511_627_776) return round($bytes / 1_099_511_627_776, 1) . ' To';
    if ($bytes >= 1_073_741_824)     return round($bytes / 1_073_741_824, 1) . ' Go';
    if ($bytes >= 1_048_576)         return round($bytes / 1_048_576, 1) . ' Mo';
    return round($bytes / 1024, 1) . ' Ko';
}

function dirSizeBytes(string $path): int {
    $out = shell_exec('du -sb ' . escapeshellarg($path) . ' 2>/dev/null');
    if ($out && preg_match('/^(\d+)/', trim($out), $m)) return (int)$m[1];
    $size = 0;
    try {
        $it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($path, FilesystemIterator::SKIP_DOTS));
        foreach ($it as $f) { try { $size += $f->getSize(); } catch (\Throwable) {} }
    } catch (\Throwable) {}
    return $size;
}

$diskRef     = '/';
$volumes     = [];

foreach ($mediaTypes as $type => $info) {
    $root = $_ENV[$info['env']] ?? getenv($info['env']) ?: $info['default'];
    if (!is_dir($root)) continue;
    $diskRef   = $root;
    $dirSize   = dirSizeBytes($root);
    $itemCount = count(array_filter(array_diff(scandir($root), ['.', '..']), fn($d) => is_dir($root . '/' . $d)));
    $volumes[] = ['type' => $type, 'label' => $info['label'], 'size' => $dirSize, 'size_fmt' => fmtBytes($dirSize), 'count' => $itemCount];
}

$total = (int)(disk_total_space($diskRef) ?: 0);
$free  = (int)(disk_free_space($diskRef)  ?: 0);
$used  = $total - $free;

$result = json_encode([
    'ok'      => true,
    'volumes' => $volumes,
    'disk'    => ['total' => $total, 'free' => $free, 'used' => $used, 'total_fmt' => fmtBytes($total), 'free_fmt' => fmtBytes($free), 'used_fmt' => fmtBytes($used)],
]);

@file_put_contents($cacheFile, $result);
echo $result;
