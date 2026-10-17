<?php
declare(strict_types=1);

// Checks rapides avant tout affichage
require_once __DIR__ . '/../../app/MacGuard.php';
require_once __DIR__ . '/../components/icon.php';
require_once __DIR__ . '/../components/card.php';
MacGuard::protect();

$current_path = $_SERVER['REQUEST_URI'];

$mediaTypes = [
    'anime' => ['root' => $_ENV['ANIMES_PATH'] ?? getenv('ANIMES_PATH') ?: '/media/animes', 'label' => 'Anime', 'icon' => lc('tv-2', 14)],
    'film'  => ['root' => $_ENV['FILMS_PATH']  ?? getenv('FILMS_PATH')  ?: '/media/films',  'label' => 'Film',  'icon' => lc('clapperboard', 14)],
    'serie' => ['root' => $_ENV['SERIES_PATH'] ?? getenv('SERIES_PATH') ?: '/media/series', 'label' => 'Série', 'icon' => lc('tv', 14)],
];
?>
<!doctype html>
<html lang="fr">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Dashboard — Hoshimi Admin</title>
<link rel="stylesheet" href="/css/hoshimi.css">
<link rel="stylesheet" href="/css/pages/admin.css">
<script src="/js/storage.js"></script>
</head>
<body>

<?php include __DIR__ . '/../components/navbar.php'; ?>

<main class="admin-page">
    <div class="admin-page__header">
        <div class="section-header section-header--simple" style="margin-bottom:0">
            <div class="section-header__bar"></div>
            <div class="section-header__inner">
                <span class="section-header__type">Admin</span>
                <h1 class="section-header__title" style="font-size:1.4rem">Dashboard</h1>
            </div>
        </div>
        <?php include __DIR__ . '/_nav.php'; ?>
    </div>

    <div class="loader" id="loader">
        <div class="loader__ring"></div>
        <span class="loader__label">Chargement des infos…</span>
    </div>

    <div class="adm-content" id="adm-content" hidden>
<?php
// Flush navbar + header + spinner au navigateur maintenant
if (ob_get_level()) { ob_end_flush(); }
flush();

// ── Traitement lourd ici ──────────────────────────────────────────────────────
require_once __DIR__ . '/_completeness.php';

$allMedia   = [];
$noMetaDirs = [];

$_cacheFile = '/tmp/hoshimi_admin_scan.json';
$_cacheTtl  = 60; // secondes
$_cacheHit  = false;

if (!isset($_GET['refresh']) && file_exists($_cacheFile) && (time() - filemtime($_cacheFile) < $_cacheTtl)) {
    $cached = json_decode(file_get_contents($_cacheFile), true);
    if ($cached) {
        $allMedia   = $cached['allMedia']   ?? [];
        $noMetaDirs = $cached['noMetaDirs'] ?? [];
        $_cacheHit  = true;
    }
}

if (!$_cacheHit) {
    foreach ($mediaTypes as $type => $info) {
        $root = $info['root'];
        if (!is_dir($root)) continue;
        foreach (array_diff(scandir($root), ['.', '..']) as $dir) {
            $dirPath  = $root . DIRECTORY_SEPARATOR . $dir;
            if (!is_dir($dirPath)) continue;
            $metaPath = $dirPath . DIRECTORY_SEPARATOR . 'metadata.json';
            if (!file_exists($metaPath)) {
                $noMetaDirs[] = ['type' => $type, 'name' => $dir, 'label' => $info['label']];
                continue;
            }
            $meta = json_decode(file_get_contents($metaPath), true);
            if (!is_array($meta)) continue;
            $completeness = mediaCompleteness($meta, $type, $dirPath);
            $allMedia[] = [
                'type'         => $type,
                'label'        => $info['label'],
                'icon'         => $info['icon'],
                'name'         => $dir,
                'title'        => $meta['title'] ?? $dir,
                'tmdb_id'      => $meta['tmdb_id'] ?? null,
                'completeness' => $completeness,
            ];
        }
    }
    @file_put_contents($_cacheFile, json_encode(['allMedia' => $allMedia, 'noMetaDirs' => $noMetaDirs]));
}

// ── Stats ──────────────────────────────────────────────────────────────────────
$counts = ['all' => count($allMedia)];
foreach ($mediaTypes as $type => $info) {
    $counts[$type] = count(array_filter($allMedia, fn($m) => $m['type'] === $type));
}

$byStatus = ['complete' => 0, 'partial' => 0, 'incomplete' => 0];
foreach ($allMedia as $m) {
    $byStatus[$m['completeness']['status']]++;
}

$totalPct = $counts['all'] > 0
    ? (int) round(array_sum(array_column(array_column($allMedia, 'completeness'), 'pct')) / $counts['all'])
    : 0;

// ── Médias à corriger (les moins complets) ─────────────────────────────────────
$toFix = $allMedia;
usort($toFix, fn($a, $b) => $a['completeness']['pct'] <=> $b['completeness']['pct']);
$toFix = array_filter($toFix, fn($m) => $m['completeness']['status'] !== 'complete');
$toFix = array_slice(array_values($toFix), 0, 10);

// ── Doublons ───────────────────────────────────────────────────────────────────
$duplicates = detectDuplicates($allMedia);

// ── Médias récemment ajoutés (top 6 par mtime dossier) ─────────────────────────
$recentMedia = [];
foreach ($mediaTypes as $type => $info) {
    $root = $info['root'];
    if (!is_dir($root)) continue;
    foreach (array_diff(scandir($root), ['.', '..']) as $dir) {
        $dirPath  = $root . DIRECTORY_SEPARATOR . $dir;
        if (!is_dir($dirPath)) continue;
        $mtime    = filemtime($dirPath);
        $metaPath = $dirPath . DIRECTORY_SEPARATOR . 'metadata.json';
        $meta     = file_exists($metaPath) ? (json_decode(file_get_contents($metaPath), true) ?: []) : [];
        $cover    = $meta['cover'] ?? $meta['cover_url'] ?? '';
        if (!$cover) {
            foreach (['cover.jpg', 'cover.jpeg', 'cover.png', 'cover.webp'] as $cf) {
                if (file_exists($dirPath . DIRECTORY_SEPARATOR . $cf)) {
                    $cover = '/stream-image/?path=' . urlencode($dir) . '&type=' . $type;
                    break;
                }
            }
        }
        $recentMedia[] = [
            'type'      => $type,
            'slug'      => $dir,
            'title'     => $meta['title'] ?? $dir,
            'cover_url' => $cover ?: null,
            'score'     => $meta['vote_average'] ?? $meta['score'] ?? 0,
            'year'      => substr($meta['release_date'] ?? $meta['first_air_date'] ?? '', 0, 4),
            'mtime'     => $mtime,
            'href'      => '/admin/edit.php?type=' . urlencode($type) . '&name=' . urlencode($dir),
        ];
    }
}
usort($recentMedia, fn($a, $b) => $b['mtime'] <=> $a['mtime']);
$recentMedia = array_slice($recentMedia, 0, 6);

// ── Historique de scan (activité 14 derniers jours) ────────────────────────────
$scanHistory = [];
$historyFile = sys_get_temp_dir() . '/hoshimi_scan_history.json';
if (file_exists($historyFile)) {
    $raw = json_decode(file_get_contents($historyFile), true) ?: [];
    $cutoff = strtotime('-14 days');
    foreach ($raw as $entry) {
        if (($entry['ts'] ?? 0) >= $cutoff) $scanHistory[] = $entry;
    }
}
?>
        <div class="adm-zone-label">
            <span class="adm-zone-label__text">Vue d'ensemble</span>
            <span class="adm-zone-label__line"></span>
        </div>
        <div class="dash-stat-row">
            <div class="dash-stat-card dash-stat-card--total">
                <span class="dash-stat-card__value"><?= $counts['all'] ?></span>
                <span class="dash-stat-card__label">Médias indexés</span>
            </div>
            <?php foreach ($mediaTypes as $type => $info): ?>
                <div class="dash-stat-card">
                    <span class="dash-stat-card__value"><?= $counts[$type] ?></span>
                    <span class="dash-stat-card__label"><?= $info['label'] ?></span>
                    <span class="dash-stat-card__ts" id="scan-ts-<?= $type ?>"></span>
                </div>
            <?php endforeach; ?>
            <?php if ($noMetaDirs): ?>
                <div class="dash-stat-card dash-stat-card--warn">
                    <span class="dash-stat-card__value"><?= count($noMetaDirs) ?></span>
                    <span class="dash-stat-card__label">Sans métadonnées</span>
                </div>
            <?php endif; ?>
        </div>

        <div class="dash-overview-grid">

            <div class="dash-section">
                <div class="dash-section__header">
                    <h2 class="dash-section__title">Répartition</h2>
                    <span class="dash-pct-badge"><?= $counts['all'] ?> médias</span>
                </div>
                <div class="dash-donut-wrap">
                    <?php
                    $typeColors = ['anime' => 'var(--color-anime)', 'film' => 'var(--color-film)', 'serie' => 'var(--color-serie)'];
                    $circumference = 339.3; // 2π × 54
                    $offset = 0;
                    $segments = [];
                    foreach ($mediaTypes as $type => $info) {
                        $n = $counts[$type];
                        $pct = $counts['all'] > 0 ? $n / $counts['all'] : 0;
                        $dash = round($pct * $circumference, 2);
                        $segments[] = ['type' => $type, 'label' => $info['label'], 'color' => $typeColors[$type], 'n' => $n, 'dash' => $dash, 'gap' => $circumference - $dash, 'offset' => $circumference - $offset];
                        $offset += $dash;
                    }
                    ?>
                    <svg class="dash-donut" viewBox="0 0 120 120" width="140" height="140">
                        <circle cx="60" cy="60" r="54" fill="none" stroke="var(--color-surface)" stroke-width="12"/>
                        <?php foreach ($segments as $s): ?>
                            <?php if ($s['dash'] > 0): ?>
                                <circle class="dash-donut__seg" cx="60" cy="60" r="54" fill="none"
                                    stroke="<?= $s['color'] ?>" stroke-width="12"
                                    stroke-dashoffset="<?= $s['offset'] ?>"
                                    data-dash="<?= $s['dash'] ?>"/>
                            <?php endif; ?>
                        <?php endforeach; ?>
                        <text x="60" y="56" text-anchor="middle" class="dash-donut__center-val"><?= $counts['all'] ?></text>
                        <text x="60" y="70" text-anchor="middle" class="dash-donut__center-label">médias</text>
                    </svg>
                    <div class="dash-donut-legend">
                        <?php foreach ($segments as $s): ?>
                            <div class="dash-donut-legend__item">
                                <span class="dash-donut-legend__dot" style="background:<?= $s['color'] ?>"></span>
                                <span class="dash-donut-legend__label"><?= $s['label'] ?></span>
                                <span class="dash-donut-legend__val"><?= $s['n'] ?></span>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>

            <div class="dash-section">
                <div class="dash-section__header">
                    <h2 class="dash-section__title">Complétude globale</h2>
                    <span class="dash-pct-badge"><?= $totalPct ?>% en moyenne</span>
                </div>
                <div class="dash-completion-bars">
                    <?php
                    $barDefs = [
                        'complete'   => 'Complets (≥ 80%)',
                        'partial'    => 'Partiels (40–79%)',
                        'incomplete' => 'Incomplets (< 40%)',
                    ];
                    foreach ($barDefs as $key => $label):
                        $n   = $byStatus[$key];
                        $pct = $counts['all'] > 0 ? round($n / $counts['all'] * 100) : 0;
                    ?>
                        <div class="dash-bar-row">
                            <span class="dash-bar-label"><?= $label ?></span>
                            <div class="dash-bar-track">
                                <div class="dash-bar-fill dash-bar-fill--<?= $key ?>" data-w="<?= $pct ?>"></div>
                            </div>
                            <span class="dash-bar-count"><?= $n ?>/<?= $counts['all'] ?></span>
                        </div>
                    <?php endforeach; ?>
                </div>
                <div class="dash-completion-footer">
                    <?php foreach ([
                        ['anime', 'Anime',  'Animes'],
                        ['serie', 'Série',  'Séries'],
                        ['film',  'Film',   'Films'],
                    ] as [$t, $singular, $plural]):
                        $n = $counts[$t];
                    ?>
                        <div class="dash-type-chip dash-type-chip--<?= $t ?>">
                            <span class="dash-type-chip__n"><?= $n ?></span>
                            <span class="dash-type-chip__label"><?= $n > 1 ? $plural : $singular ?></span>
                        </div>
                    <?php endforeach; ?>
                    <div class="dash-type-chip dash-type-chip--total">
                        <span class="dash-type-chip__n"><?= $counts['all'] ?></span>
                        <span class="dash-type-chip__label">au total</span>
                    </div>
                </div>
            </div>

        </div>

        <div class="dash-issues-grid">
            <div class="dash-section">
                <div class="dash-section__header">
                    <h2 class="dash-section__title">À corriger</h2>
                    <?php if (count($toFix) > 0): ?>
                        <a href="/admin/media.php?tab=incomplete" class="dash-section__link">Voir tout <?= lc('arrow-right', 12) ?></a>
                    <?php endif; ?>
                </div>
                <?php if (empty($toFix)): ?>
                    <div class="dash-empty-state">
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"/></svg>
                        Tous les médias sont complets
                    </div>
                <?php else: ?>
                    <div class="dash-fix-list">
                        <?php foreach ($toFix as $m): ?>
                            <?php $c = $m['completeness']; ?>
                            <a href="/admin/edit.php?type=<?= urlencode($m['type']) ?>&name=<?= urlencode($m['name']) ?>" class="dash-fix-item">
                                <div class="dash-fix-item__top">
                                    <span class="dash-fix-item__title"><?= htmlspecialchars($m['title']) ?></span>
                                    <span class="badge badge--type" style="font-size:0.68rem"><?= $m['label'] ?></span>
                                    <span class="dash-fix-item__pct dash-fix-item__pct--<?= $c['status'] ?>"><?= $c['pct'] ?>%</span>
                                </div>
                                <div class="dash-fix-progress">
                                    <div class="dash-fix-progress__fill dash-fix-progress__fill--<?= $c['status'] ?>" data-w="<?= $c['pct'] ?>"></div>
                                </div>
                                <?php if (!empty($c['missing'])): ?>
                                    <div class="dash-fix-item__missing">
                                        <?php foreach ($c['missing'] as $f): ?>
                                            <span>Manque <?= completenessFieldLabel($f) ?></span>
                                        <?php endforeach; ?>
                                    </div>
                                <?php endif; ?>
                            </a>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>

            <div class="dash-issues-grid-inner">

                <div class="dash-section">
                    <div class="dash-section__header">
                        <h2 class="dash-section__title">Doublons TMDB</h2>
                        <span class="dash-pct-badge <?= empty($duplicates) ? '' : 'dash-pct-badge--warn' ?>">
                            <?= empty($duplicates) ? 'Aucun' : count($duplicates) . ' détecté(s)' ?>
                        </span>
                    </div>
                    <?php if (empty($duplicates)): ?>
                        <div class="dash-empty-state">
                            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"/></svg>
                            Aucun doublon détecté
                        </div>
                    <?php else: ?>
                        <div class="dash-dup-list">
                            <?php foreach ($duplicates as $tmdbId => $group): ?>
                                <div class="dash-dup-group">
                                    <span class="dash-dup-tmdb">TMDB #<?= $tmdbId ?></span>
                                    <div class="dash-dup-items">
                                        <?php foreach ($group as $m): ?>
                                            <a href="/admin/edit.php?type=<?= urlencode($m['type']) ?>&name=<?= urlencode($m['name']) ?>" class="dash-dup-item">
                                                <span class="badge badge--type" style="font-size:0.65rem"><?= $m['label'] ?></span>
                                                <span><?= htmlspecialchars($m['title']) ?></span>
                                                <span class="dash-dup-folder"><?= htmlspecialchars($m['name']) ?></span>
                                            </a>
                                        <?php endforeach; ?>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>

                <?php
                $noMetaByType = [];
                foreach ($noMetaDirs as $nd) $noMetaByType[$nd['type']][] = $nd;
                ?>
                <div class="dash-section">
                    <div class="dash-section__header">
                        <h2 class="dash-section__title">Sans metadata</h2>
                        <?php if ($noMetaDirs): ?>
                            <span class="dash-pct-badge dash-pct-badge--warn"><?= count($noMetaDirs) ?></span>
                        <?php else: ?>
                            <span class="dash-pct-badge">Aucun</span>
                        <?php endif; ?>
                    </div>
                    <?php if ($noMetaDirs): ?>
                        <?php foreach ($noMetaByType as $type => $dirs): ?>
                            <div class="dash-nometa-group">
                                <div class="dash-nometa-group__header">
                                    <span class="admin-scan-btn__dot admin-scan-btn__dot--<?= $type ?>"></span>
                                    <span class="dash-nometa-group__label"><?= $mediaTypes[$type]['label'] ?> (<?= count($dirs) ?>)</span>
                                    <button class="dash-nometa-scan-btn admin-scan-btn--type" data-fetch-type="<?= $type ?>">
                                        <span class="admin-scan-btn__dot admin-scan-btn__dot--<?= $type ?>"></span>
                                        Scanner
                                    </button>
                                </div>
                                <div style="display:flex;flex-wrap:wrap;gap:5px;margin-top:8px">
                                    <?php foreach (array_slice($dirs, 0, 15) as $nd): ?>
                                        <span class="dash-no-meta-tag"><?= htmlspecialchars($nd['name']) ?></span>
                                    <?php endforeach; ?>
                                    <?php if (count($dirs) > 15): ?>
                                        <span class="dash-no-meta-tag" style="opacity:.5">+<?= count($dirs) - 15 ?> autres</span>
                                    <?php endif; ?>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <div class="dash-empty-state">
                            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"/></svg>
                            Tous les médias ont leurs métadonnées
                        </div>
                    <?php endif; ?>
                </div>

            </div>
        </div>

        <!-- ══════════════════ VOLUMES ══════════════════ -->
        <div class="adm-zone-label">
            <span class="adm-zone-label__text">Volumes</span>
            <span class="adm-zone-label__line"></span>
        </div>
        <div class="dash-section" id="volumes-panel">
            <div class="admin-volumes" id="volumes-content">
                <?php foreach ($mediaTypes as $type => $info): if (!is_dir($info['root'])) continue; ?>
                <div class="admin-volume-row">
                    <div class="admin-volume-row__head">
                        <span class="admin-scan-btn__dot admin-scan-btn__dot--<?= $type ?>"></span>
                        <strong><?= $info['label'] ?></strong>
                        <span class="admin-volume-row__meta" id="vol-meta-<?= $type ?>">—</span>
                        <span class="admin-volume-row__pct" id="vol-pct-<?= $type ?>">—</span>
                    </div>
                    <div class="admin-volume-bar">
                        <div class="admin-volume-bar__fill admin-volume-bar__fill--<?= $type ?>" style="width:0"></div>
                    </div>
                </div>
                <?php endforeach; ?>
                <p class="admin-volume-disk" id="volumes-disk-line"></p>
            </div>
        </div>

        <div class="adm-zone-label">
            <span class="adm-zone-label__text">Scan &amp; Import</span>
            <span class="adm-zone-label__line"></span>
        </div>
        <div class="dash-section" id="scan-panel">

            <div class="admin-scan-grid">

                <!-- Bloc 1 : Import -->
                <div class="admin-scan-block">
                    <div class="admin-scan-block__head">
                        <svg xmlns="http://www.w3.org/2000/svg" width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="17 8 12 3 7 8"/><line x1="12" y1="3" x2="12" y2="15"/></svg>
                        <strong>Import</strong>
                    </div>
                    <p class="admin-scan-block__desc">Scanne les dossiers sans métadonnées et récupère les infos depuis TMDB / AniList.</p>
                    <div class="admin-scan-block__btns">
                        <?php foreach ($mediaTypes as $type => $info): ?>
                            <button class="btn btn--ghost btn-admin" data-fetch-type="<?= $type ?>" data-action="fetch_meta">
                                <span class="admin-scan-btn__dot admin-scan-btn__dot--<?= $type ?>"></span>
                                <?= $info['label'] ?>
                            </button>
                        <?php endforeach; ?>
                    </div>
                </div>

                <!-- Bloc 2 : Force rescan -->
                <div class="admin-scan-block">
                    <div class="admin-scan-block__head">
                        <svg xmlns="http://www.w3.org/2000/svg" width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="1 4 1 10 7 10"/><path d="M3.51 15a9 9 0 1 0 .49-4.14"/></svg>
                        <strong>Force rescan</strong>
                    </div>
                    <p class="admin-scan-block__desc">Réinitialise et re-télécharge toutes les métadonnées en conservant l'ID TMDB existant.</p>
                    <div class="admin-scan-block__btns">
                        <?php foreach ($mediaTypes as $type => $info): ?>
                            <button class="btn btn--ghost btn-admin" data-fetch-type="<?= $type ?>" data-action="force_scan">
                                <span class="admin-scan-btn__dot admin-scan-btn__dot--<?= $type ?>"></span>
                                <?= $info['label'] ?>
                            </button>
                        <?php endforeach; ?>
                    </div>
                </div>

                <!-- Bloc 3 : Fetch précis -->
                <div class="admin-scan-block admin-scan-block--full">
                    <div class="admin-scan-block__head">
                        <svg xmlns="http://www.w3.org/2000/svg" width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
                        <strong>Fetch précis</strong>
                    </div>
                    <p class="admin-scan-block__desc">Récupère les métadonnées d'une œuvre spécifique par nom de dossier (sans retélécharger les covers).</p>
                    <div class="admin-fetch-one">
                        <?php $firstType = array_key_first($mediaTypes); ?>
                        <div class="cs-wrap" id="fetch-one-type" data-value="<?= $firstType ?>">
                            <button class="cs-btn" type="button" aria-haspopup="listbox" aria-expanded="false">
                                <span class="cs-dot cs-dot--<?= $firstType ?>"></span>
                                <span class="cs-label"><?= $mediaTypes[$firstType]['label'] ?></span>
                            </button>
                            <ul class="cs-list" role="listbox">
                                <?php foreach ($mediaTypes as $type => $info): $isFirst = $type === $firstType; ?>
                                    <li class="cs-item <?= $isFirst ? 'is-active' : '' ?>"
                                        data-value="<?= $type ?>"
                                        role="option"
                                        aria-selected="<?= $isFirst ? 'true' : 'false' ?>">
                                        <span class="cs-check"><?php if ($isFirst): ?><?= '' ?><?php endif; ?></span>
                                        <span class="cs-dot cs-dot--<?= $type ?>"></span>
                                        <span class="cs-body"><span class="cs-item-label"><?= $info['label'] ?></span></span>
                                    </li>
                                <?php endforeach; ?>
                            </ul>
                        </div>
                        <input id="fetch-one-name" class="admin-fetch-one__input" type="text" placeholder="Nom exact du dossier…" autocomplete="off">
                        <input id="fetch-one-tmdb-id" class="admin-fetch-one__input admin-fetch-one__input--id" type="number" placeholder="ID TMDB (optionnel)" min="1">
                        <button id="btn-fetch-one" class="btn btn--primary">Fetch précis</button>
                    </div>
                </div>

            </div>

            <!-- Bandeau de progression scan -->
            <div class="admin-scan-progress" id="scan-progress" hidden>
                <div class="admin-scan-progress__spinner"></div>
                <div class="admin-scan-progress__info">
                    <span class="admin-scan-progress__label" id="scan-progress-label">Scan en cours…</span>
                    <span class="admin-scan-progress__stats" id="scan-progress-stats"></span>
                </div>
                <button class="admin-scan-progress__cancel" id="scan-progress-cancel" title="Annuler l'affichage"><?= lc('x', 14) ?></button>
            </div>
        </div>

        <!-- ══════════════════ MAINTENANCE ══════════════════ -->
        <div class="adm-zone-label">
            <span class="adm-zone-label__text">Maintenance</span>
            <span class="adm-zone-label__line"></span>
        </div>
        <div class="dash-section" id="maintenance-panel">
            <div class="dash-maintenance-row">
                <div class="dash-maint-card">
                    <div class="dash-maint-card__head">
                        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="3" width="18" height="18" rx="2"/><circle cx="8.5" cy="8.5" r="1.5"/><polyline points="21 15 16 10 5 21"/></svg>
                        <strong>Cache WebP</strong>
                    </div>
                    <p class="dash-maint-card__desc">Supprime tous les fichiers <code>.webp</code> générés à la volée par le stream-image.</p>
                    <button class="btn btn--ghost dash-maint-btn" id="btn-purge-webp" style="font-size:0.8rem;padding:7px 14px">Purger les caches WebP</button>
                    <span class="dash-maint-result" id="maint-webp-result"></span>
                </div>
                <div class="dash-maint-card">
                    <div class="dash-maint-card__head">
                        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="1 4 1 10 7 10"/><path d="M3.51 15a9 9 0 1 0 .49-4.14"/></svg>
                        <strong>Index de recherche</strong>
                    </div>
                    <p class="dash-maint-card__desc">Invalide l'index de recherche et le cache admin pour forcer une reconstruction.</p>
                    <button class="btn btn--ghost dash-maint-btn" id="btn-purge-search" style="font-size:0.8rem;padding:7px 14px">Invalider le cache</button>
                    <span class="dash-maint-result" id="maint-search-result"></span>
                </div>
                <div class="dash-maint-card">
                    <div class="dash-maint-card__head">
                        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M4 6h16"/><path d="M4 12h16"/><path d="M4 18h16"/></svg>
                        <strong>Index JSON (IndexManager)</strong>
                    </div>
                    <p class="dash-maint-card__desc">Régénère les fichiers JSON précompilés utilisés par IndexManager pour animes, séries et films.</p>
                    <button class="btn btn--ghost dash-maint-btn" id="btn-regen-index" style="font-size:0.8rem;padding:7px 14px">Régénérer l'index</button>
                    <span class="dash-maint-result" id="maint-regen-index-result"></span>
                </div>
            </div>
        </div>

        <!-- ══════════════════ MÉDIAS RÉCENTS ══════════════════ -->
        <?php if (!empty($recentMedia)): ?>
        <div class="adm-zone-label">
            <span class="adm-zone-label__text">Récemment ajoutés</span>
            <span class="adm-zone-label__line"></span>
            <a href="/admin/media.php" class="dash-section__link">Voir tout <?= lc('arrow-right', 12) ?></a>
        </div>
        <div class="dash-section">
            <div class="grid-cards" style="grid-template-columns:repeat(6,1fr)">
                <?php foreach ($recentMedia as $rm): ?>
                    <?= render_card($rm, $rm['type'], ['href' => $rm['href']]) ?>
                <?php endforeach; ?>
            </div>
        </div>
        <?php endif; ?>

        <!-- ══════════════════ ACTIVITÉ (sparkline) ══════════════════ -->
        <?php if (!empty($scanHistory)): ?>
        <?php
            // Grouper par jour (14 derniers jours)
            $days = [];
            for ($d = 13; $d >= 0; $d--) {
                $days[date('Y-m-d', strtotime("-{$d} days"))] = 0;
            }
            foreach ($scanHistory as $entry) {
                $day = date('Y-m-d', $entry['ts']);
                if (isset($days[$day])) $days[$day]++;
            }
            $maxDay = max(array_values($days)) ?: 1;
        ?>
        <div class="adm-zone-label">
            <span class="adm-zone-label__text">Activité de scan</span>
            <span class="adm-zone-label__line"></span>
            <span class="dash-pct-badge"><?= count($scanHistory) ?> scan<?= count($scanHistory) > 1 ? 's' : '' ?> · 14 jours</span>
        </div>
        <div class="dash-section">
            <div class="dash-activity-wrap">
                <svg class="dash-activity-chart" viewBox="0 0 280 60" preserveAspectRatio="none">
                    <?php
                    $barW = 14; $gap = 6; $barX = 0;
                    foreach ($days as $day => $count):
                        $h = $count > 0 ? max(4, round(($count / $maxDay) * 48)) : 2;
                        $y = 56 - $h;
                        $alpha = $count > 0 ? 0.7 + 0.3 * ($count / $maxDay) : 0.15;
                    ?>
                    <rect x="<?= $barX ?>" y="<?= $y ?>" width="<?= $barW ?>" height="<?= $h ?>"
                          rx="2" fill="var(--color-accent)" fill-opacity="<?= round($alpha, 2) ?>">
                        <title><?= $day ?> : <?= $count ?> scan<?= $count > 1 ? 's' : '' ?></title>
                    </rect>
                    <?php $barX += $barW + $gap; endforeach; ?>
                </svg>
                <div class="dash-activity-labels">
                    <?php
                    $keys = array_keys($days);
                    echo '<span>' . date('d/m', strtotime($keys[0])) . '</span>';
                    echo '<span>' . date('d/m', strtotime($keys[6])) . '</span>';
                    echo '<span>' . date('d/m', strtotime($keys[13])) . '</span>';
                    ?>
                </div>
            </div>
        </div>
        <?php endif; ?>

    <?php include __DIR__ . '/../components/footer.php'; ?>

    <script src="/js/toast.js"></script>
    <script src="/js/confirm.js"></script>
    <script src="../js/select.js"></script>
    <script>
        // ── Boutons maintenance ───────────────────────────────────────────────────
        async function runMaintenance(action, btnId, resultId) {
            const btn = document.getElementById(btnId);
            const res = document.getElementById(resultId);
            btn.disabled = true;
            res.textContent = '…';

            let data;
            data = await fetch('/admin/api/maintenance.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ action }),
            }).then(r => r.json()).catch(() => ({ ok: false }));

            btn.disabled = false;
            if (!data.ok) { res.textContent = 'Erreur'; showScanToast('Erreur', 'error'); return; }

            let msg;
            if (action === 'purge_webp') {
                msg = data.deleted === 0 ? 'Aucun fichier à purger' : `${data.deleted} fichier(s) supprimé(s) · ${data.freed_fmt} libéré(s)`;
            } else {
                msg = data.deleted === 0 ? 'Cache déjà vide' : `${data.deleted} fichier(s) invalidé(s)`;
            }
            res.textContent = msg;
            showScanToast(msg, 'success');
        }

        document.getElementById('btn-purge-webp').addEventListener('click', () => runMaintenance('purge_webp', 'btn-purge-webp', 'maint-webp-result'));
        document.getElementById('btn-purge-search').addEventListener('click', () => runMaintenance('purge_search', 'btn-purge-search', 'maint-search-result'));
        document.getElementById('btn-regen-index').addEventListener('click', async () => {
            const btn = document.getElementById('btn-regen-index');
            const res = document.getElementById('maint-regen-index-result');
            btn.disabled = true;
            res.textContent = 'Régénération…';
            res.style.color = 'var(--color-text-muted)';
            try {
                const r = await fetch('/admin/api/regen-index.php?type=all', { method: 'POST' });
                const d = await r.json();
                if (d.success) {
                    const counts = Object.entries(d.result).filter(([k]) => k !== 'time_ms').map(([k, v]) => `${k}: ${v}`).join(', ');
                    res.textContent = `✓ ${counts} (${d.result.time_ms}ms)`;
                    res.style.color = 'var(--color-success, #22c55e)';
                } else {
                    res.textContent = `✗ ${d.error ?? 'Erreur'}`;
                    res.style.color = 'var(--color-danger, #ef4444)';
                }
            } catch (e) {
                res.textContent = '✗ Erreur réseau';
                res.style.color = 'var(--color-danger, #ef4444)';
            }
            btn.disabled = false;
        });

        let pollInterval = null;

        function allScanBtns() {
            return document.querySelectorAll('[data-fetch-type], #btn-fetch-one');
        }

        function setScanProgress(label, stats, visible) {
            const el = document.getElementById('scan-progress');
            el.hidden = !visible;
            if (label !== null) document.getElementById('scan-progress-label').textContent = label;
            if (stats !== null) document.getElementById('scan-progress-stats').textContent = stats;
        }

        async function startJob(action, payload, jobLabel) {
            if (pollInterval) { clearInterval(pollInterval); pollInterval = null; }
            allScanBtns().forEach(b => b.disabled = true);
            setScanProgress(jobLabel, 'Démarrage…', true);

            const res = await fetch('/admin/api/run-scan.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ action, ...payload }),
            }).then(r => r.json()).catch(() => ({ ok: false }));

            if (!res.ok) {
                setScanProgress(jobLabel, 'Erreur au démarrage', true);
                allScanBtns().forEach(b => b.disabled = false);
                showScanToast('Erreur au démarrage', 'error');
                return;
            }

            const jobId     = res.job_id;
            let   lastLen   = 0;
            let   countOk   = 0;
            let   countErr  = 0;
            const typeForTs = payload.type;

            pollInterval = setInterval(async () => {
                const log = await fetch(`/admin/api/scan-log.php?job=${jobId}`).then(r => r.json()).catch(() => null);
                if (!log) return;
                if (!log.started) { setScanProgress(null, 'En attente…', true); return; }

                const newLines = log.lines.slice(lastLen);
                lastLen = log.lines.length;
                newLines.forEach(line => {
                    if (/^\[ ?OK ?\]/.test(line) || /✓/.test(line)) countOk++;
                    if (/^\[ERR ?\]/.test(line) || /✗/.test(line))  countErr++;
                });

                const statParts = [];
                if (countOk)  statParts.push(`${countOk} OK`);
                if (countErr) statParts.push(`${countErr} erreur${countErr > 1 ? 's' : ''}`);
                setScanProgress(null, statParts.join(' · ') || 'En cours…', true);

                if (log.done) {
                    clearInterval(pollInterval); pollInterval = null;
                    allScanBtns().forEach(b => b.disabled = false);
                    fetch(`/admin/api/scan-log.php?job=${jobId}&cleanup=1&type=${typeForTs ?? ''}`);
                    if (typeForTs) localStorage.setItem(`hoshimi_scan_ts_${typeForTs}`, new Date().toISOString());
                    const hasErr = log.error || countErr > 0;
                    const msg = hasErr
                        ? `Terminé — ${countOk} OK, ${countErr} erreur${countErr > 1 ? 's' : ''}`
                        : `Terminé — ${countOk} importés`;
                    showScanToast(msg, hasErr ? 'error' : 'success');
                    setScanProgress(null, 'Terminé', true);
                    setTimeout(() => location.assign(location.pathname + '?refresh=1'), 1500);
                }
            }, 1000);
        }

        // Scan batch + force rescan
        document.querySelectorAll('[data-fetch-type]').forEach(btn => {
            btn.addEventListener('click', function() {
                const type   = this.dataset.fetchType;
                const action = this.dataset.action ?? 'fetch_meta';
                const label  = action === 'force_scan' ? `Force rescan ${type}` : `Import ${type}`;
                startJob(action, { type }, label);
            });
        });

        // Init custom select + fetch oeuvre précise
        const fetchTypeWrap = document.getElementById('fetch-one-type');
        if (fetchTypeWrap) {
            // Injecter le check SVG sur l'item actif dès le chargement
            const activeItem = fetchTypeWrap.querySelector('.cs-item.is-active .cs-check');
            if (activeItem) activeItem.innerHTML = HoshimiSelect.SVG_CHECK;
            HoshimiSelect.init(fetchTypeWrap);
        }

        document.getElementById('btn-fetch-one').addEventListener('click', function() {
            const type   = fetchTypeWrap?.dataset.value ?? '';
            const name   = document.getElementById('fetch-one-name').value.trim();
            const tmdbId = parseInt(document.getElementById('fetch-one-tmdb-id').value) || null;
            if (!name) { showScanToast('Entrez le nom du dossier', 'error'); return; }
            const payload = { type, name };
            if (tmdbId) payload.tmdb_id = tmdbId;
            startJob('fetch_one', payload, `Fetch précis — ${name}`);
        });

        document.getElementById('scan-progress-cancel').addEventListener('click', () => {
            document.getElementById('scan-progress').hidden = true;
            if (pollInterval) { clearInterval(pollInterval); pollInterval = null; }
            allScanBtns().forEach(b => b.disabled = false);
        });

        function showScanToast(msg, type = 'success') {
            HoshimiToast.show(msg, { type: type === 'error' ? 'error' : 'success' });
        }

        // ── Animation donut chart ────────────────────────────────────────────────
        requestAnimationFrame(() => {
            document.querySelectorAll('.dash-donut__seg').forEach(seg => {
                const dash = seg.dataset.dash;
                const gap  = 339.3 - dash;
                seg.style.strokeDasharray = `${dash} ${gap}`;
            });
        });

        // ── Timestamps de scan ────────────────────────────────────────────────────
        function timeAgo(iso) {
            const diff = Math.floor((Date.now() - new Date(iso)) / 1000);
            if (diff < 60) return 'il y a quelques secondes';
            if (diff < 3600) return `il y a ${Math.floor(diff / 60)} min`;
            if (diff < 86400) return `il y a ${Math.floor(diff / 3600)} h`;
            return `il y a ${Math.floor(diff / 86400)} j`;
        }
        ['anime', 'film', 'serie'].forEach(type => {
            const ts = localStorage.getItem(`hoshimi_scan_ts_${type}`);
            const el = document.getElementById(`scan-ts-${type}`);
            if (!el) return;
            el.textContent = ts ? timeAgo(ts) : 'Jamais scanné';
        });

        // ── Count-up animation des stat cards ────────────────────────────────────
        document.querySelectorAll('.dash-stat-card__value').forEach(el => {
            const target = parseInt(el.textContent.replace(/\s/g, ''), 10);
            if (isNaN(target) || target === 0) return;
            el.textContent = '0';
            const duration = 600;
            const start = performance.now();
            const step = now => {
                const t = Math.min((now - start) / duration, 1);
                const ease = 1 - Math.pow(1 - t, 3);
                el.textContent = Math.round(ease * target).toLocaleString('fr-FR');
                if (t < 1) requestAnimationFrame(step);
            };
            requestAnimationFrame(step);
        });

        // ── Animation des barres de progression ───────────────────────────────────
        const bars = document.querySelectorAll('[data-w]');
        if ('IntersectionObserver' in window) {
            const io = new IntersectionObserver(entries => {
                entries.forEach(e => {
                    if (!e.isIntersecting) return;
                    e.target.style.width = e.target.dataset.w + '%';
                    io.unobserve(e.target);
                });
            }, { threshold: 0.1 });
            bars.forEach(b => io.observe(b));
        } else {
            bars.forEach(b => { b.style.width = b.dataset.w + '%'; });
        }

        // ── Volumes (chargement async) ────────────────────────────────────────────
        (async function loadVolumes() {
            const data = await fetch('/admin/api/volumes.php').then(r => r.json()).catch(() => null);
            if (!data?.ok) return;
            const total = data.disk.total;
            const rows  = document.querySelectorAll('#volumes-content .admin-volume-row');
            data.volumes.forEach((vol, i) => {
                const row = rows[i];
                if (!row) return;
                const pct = total > 0 ? Math.min(100, Math.round(vol.size / total * 1000) / 10) : 0;
                const meta = row.querySelector('.admin-volume-row__meta');
                const pctEl = row.querySelector('.admin-volume-row__pct');
                meta.textContent  = `${vol.count} dossiers · ${vol.size_fmt}`;
                pctEl.textContent = `${pct}%`;
                const fill = row.querySelector('.admin-volume-bar__fill');
                fill.dataset.w = pct;
                fill.style.width = pct + '%';
            });
            const diskLine = document.getElementById('volumes-disk-line');
            if (diskLine) {
                const d = data.disk;
                diskLine.textContent = `Disque : ${d.used_fmt} utilisé / ${d.total_fmt} total (${d.free_fmt} libre)`;
            }
        })();
    </script>
    <script>
    (function(){
        const loader  = document.getElementById('loader');
        const content = document.getElementById('adm-content');
        if (content) content.hidden = false;
        if (loader)  loader.remove();
    })();
    </script>
    </div><!-- #adm-content -->
</main>
</body>
</html>