<?php

declare(strict_types=1);

require_once __DIR__ . '/../../app/IndexManager.php';
require_once __DIR__ . '/../../app/Services/ThumbnailHelper.php';

$type = trim($_GET['type'] ?? 'anime');
if (!in_array($type, ['anime', 'serie', 'film'], true)) $type = 'anime';

$typeParam = match($type) {
    'serie' => '&type=serie',
    'film'  => '&type=film',
    default => '',
};

ThumbnailHelper::loadIndex();

preg_match('/(?:^|&)slug=([^&]*)/', $_SERVER['QUERY_STRING'] ?? '', $_m);
$slug    = isset($_m[1]) ? rawurldecode($_m[1]) : '';
$content = $slug ? IndexManager::getBySlug($type, $slug) : null;

if (!$content) {
    include __DIR__ . '/../404.php';
    exit;
}

$isFilm = ($type === 'film');
$isAjax = !empty($_GET['ajax']);

$metaRaw = $content['metadata'] ?? [];

// ── Logo TMDB ───────────────────────────────────────────────────────────────
$heroLogo = null;
$logosList = $metaRaw['logos'] ?? $metaRaw['images']['logos'] ?? [];
if (!empty($logosList)) {
    if (isset($logosList[0]['iso_639_1'])) {
        usort($logosList, fn($a, $b) => (($b['iso_639_1'] ?? '') === 'en') <=> (($a['iso_639_1'] ?? '') === 'en'));
    }
    $lg       = $logosList[0];
    $heroLogo = $lg['w500'] ?? $lg['original'] ?? $lg['urls']['w500'] ?? $lg['urls']['original'] ?? null;
    if (!$heroLogo && !empty($lg['file_path'])) {
        $heroLogo = 'https://image.tmdb.org/t/p/w500' . $lg['file_path'];
    }
}

$heroTitle = htmlspecialchars($isFilm ? $content['title'] : ($content['title_english'] ?: $content['title']));
$heroSplit = !$heroLogo && !empty($content['cover_url']);

// ── Meta hero ────────────────────────────────────────────────────────────────
$typeLabel = match($type) {
    'serie' => 'SÉRIE',
    'film'  => 'FILM',
    default => 'ANIME',
};

$scoreDisp = ($content['score'] ?? null) ? number_format((float)$content['score'], 1) : null;
$epCount   = $content['episodes_local'] ?? 0;

$statusLabels = [
    'FINISHED'         => 'Terminé',
    'RELEASING'        => 'En cours',
    'NOT_YET_RELEASED' => 'À venir',
    'CANCELLED'        => 'Annulé',
    'HIATUS'           => 'En pause',
    'ENDED'            => 'Terminé',
    'RETURNING SERIES' => 'En cours',
    'CANCELED'         => 'Annulé',
    'IN PRODUCTION'    => 'En production',
    'PLANNED'          => 'À venir',
    'PILOT'            => 'Pilote',
];
$statusLabel = !empty($content['status']) ? ($statusLabels[$content['status']] ?? $content['status']) : null;
$isTrending  = $content['is_trending'] ?? false;
$isAiring    = in_array($content['status'] ?? '', ['RELEASING', 'RETURNING SERIES'], true);

// ── Synopsis ─────────────────────────────────────────────────────────────────
$synopsis = null;
if (!empty($content['synopsis'])) {
    $clean   = preg_replace('/<br\s*\/?>/i', "\n", $content['synopsis']);
    $clean   = strip_tags($clean);
    $clean   = html_entity_decode($clean, ENT_QUOTES | ENT_HTML5, 'UTF-8');
    $synopsis = trim(preg_replace("/\n{3,}/", "\n\n", $clean));
}

// ── Casting ──────────────────────────────────────────────────────────────────
$castData = $metaRaw['cast'] ?? $metaRaw['credits']['cast'] ?? [];

// ── Film : runtime + VOD date ─────────────────────────────────────────────────
$runtimeStr = null;
$vodDate    = null;
if ($isFilm) {
    if ($content['runtime'] ?? null) {
        $min = (int)$content['runtime'];
        $runtimeStr = $min < 60 ? "{$min} min" : floor($min / 60) . 'h' . ($min % 60 ? ' ' . ($min % 60) . 'min' : '');
    }
    if (!empty($metaRaw['release_date_vod'])) {
        $ts = strtotime($metaRaw['release_date_vod']);
        if ($ts && $ts > time()) {
            $mois = ['janvier', 'février', 'mars', 'avril', 'mai', 'juin', 'juillet', 'août', 'septembre', 'octobre', 'novembre', 'décembre'];
            $vodDate = (int)date('j', $ts) . ' ' . $mois[(int)date('n', $ts) - 1] . ' ' . date('Y', $ts);
        }
    }
}

// ── Film : episode unique ─────────────────────────────────────────────────────
$filmEpisode = null;
$filmEpKey   = null;
$filmThumb   = null;
if ($isFilm) {
    $filmEpisode = $content['seasons'][0]['episodes'][0] ?? null;
    $filmEpKey   = $filmEpisode ? md5($filmEpisode['file_path']) : null;
    $thumbDir    = ($_SERVER['DOCUMENT_ROOT'] ?? '') . '/images/thumbnails/';
    $thumbIndex  = file_exists($thumbDir . 'index.json') ? (json_decode(file_get_contents($thumbDir . 'index.json'), true) ?: []) : [];
    $filmThumb   = $filmEpKey ? ($thumbIndex[$filmEpKey] ?? null) : null;
    $hasFile     = $content['has_file'] ?? true;
}

// ── Film : Collection TMDB ────────────────────────────────────────────────────
$collectionName  = null;
$collectionCards = [];
if ($isFilm) {
    $collectionName  = $metaRaw['collection_name'] ?? null;
    $collectionParts = $metaRaw['collection_parts'] ?? [];
    usort($collectionParts, fn($a, $b) => strcmp($a['release_date'] ?? '', $b['release_date'] ?? ''));
    if ($collectionName && !empty($collectionParts)) {
        $allFilms = IndexManager::getAll('film');
        $byTmdbId = [];
        foreach ($allFilms as $f) {
            $tid = $f['tmdb_id'] ?? null;
            if ($tid) $byTmdbId[$tid] = $f;
        }
        foreach ($collectionParts as $part) {
            $tid       = $part['tmdb_id'] ?? null;
            $local     = $tid ? ($byTmdbId[$tid] ?? null) : null;
            $year      = $part['release_date'] ? (int)substr($part['release_date'], 0, 4) : null;
            $poster    = $part['poster_path'] ? 'https://image.tmdb.org/t/p/w342' . $part['poster_path'] : null;
            $isCurrent = $local && $local['slug'] === $content['slug'];
            $collectionCards[] = [
                'local'      => $local,
                'title'      => $part['title'],
                'year'       => $year,
                'cover_url'  => $local['cover_url'] ?? $poster,
                'is_current' => $isCurrent,
                'tmdb_id'    => $tid,
            ];
        }
    }
}

// ── Anime/Série : saisons ─────────────────────────────────────────────────────
$activeSeason = 0;
$seasonData   = null;
$tmdbEpisodes = [];
if (!$isFilm) {
    $activeSeason = (int)($_GET['saison'] ?? 0);

    // Compléter les saisons depuis metadata.json
    $metaSeasons = $metaRaw['seasons'] ?? [];
    if (!empty($metaSeasons)) {
        $coveredNumbers = array_map(fn($s) => (int)$s['number'], $content['seasons']);
        foreach ($metaSeasons as $folderKey => $ms) {
            $num = isset($ms['tmdb_season']) ? (int)$ms['tmdb_season'] : null;
            if ($num === null) {
                preg_match('/\d+/', $folderKey, $m);
                $num = isset($m[0]) ? (int)$m[0] : null;
            }
            if ($num === null || $num === 0 || in_array($num, $coveredNumbers, true)) continue;
            $label = (!preg_match('/^(season|saison)\s*\d+$/i', $folderKey)) ? $folderKey : 'Saison ' . $num;
            $content['seasons'][] = ['number' => $num, 'label' => $label, 'path' => '', 'episodes' => []];
            $coveredNumbers[] = $num;
        }
        usort($content['seasons'], fn($a, $b) => $a['number'] <=> $b['number']);
    }

    foreach ($content['seasons'] as $s) {
        if ($s['number'] === $activeSeason) { $seasonData = $s; break; }
    }
    if ($seasonData === null) {
        $seasonData   = $content['seasons'][0] ?? null;
        $activeSeason = $seasonData['number'] ?? 0;
    }

    // TMDB episodes pour la saison active
    if ($seasonData) {
        $folderName = basename($seasonData['path'] ?? '');
        if (!empty($metaSeasons[$folderName]['episodes'])) {
            foreach (array_values($metaSeasons[$folderName]['episodes']) as $idx => $ep) {
                $tmdbEpisodes[$idx + 1] = $ep;
            }
        } elseif (!empty($seasonData['episodes'])) {
            $firstEpPath  = $seasonData['episodes'][0]['file_path'] ?? '';
            $seasonFolder = $firstEpPath ? dirname($firstEpPath) : '';
            foreach ([$seasonFolder . '/metadata.json', dirname($seasonFolder) . '/metadata.json'] as $candidate) {
                if ($candidate !== '/metadata.json' && file_exists($candidate)) {
                    $seasonJson = @json_decode(@file_get_contents($candidate), true) ?: [];
                    if (!empty($seasonJson['episodes']) && isset($seasonJson['season_number'])) {
                        $localNum = 1;
                        foreach ($seasonJson['episodes'] as $tmdbEp) {
                            $tmdbEpisodes[$localNum++] = $tmdbEp;
                        }
                        break;
                    }
                }
            }
        }
        if (empty($tmdbEpisodes)) {
            foreach ($metaSeasons as $ms) {
                $msNum = $ms['tmdb_season'] ?? null;
                if ($msNum !== null && ($activeSeason === 0 || $msNum === $activeSeason) && !empty($ms['episodes'])) {
                    foreach (array_values($ms['episodes']) as $idx => $ep) {
                        $tmdbEpisodes[$idx + 1] = $ep;
                    }
                    break;
                }
            }
        }
    }
}

// ── Thumbnails/durations (anime/série) ───────────────────────────────────────
$durations = [];
$sizes     = [];
if (!$isFilm) {
    $cachePath = $_SERVER['DOCUMENT_ROOT'] . '/images/thumbnails/';
    if (file_exists($cachePath . 'durations.json')) $durations = json_decode(file_get_contents($cachePath . 'durations.json'), true) ?: [];
    if (file_exists($cachePath . 'filesizes.json')) $sizes     = json_decode(file_get_contents($cachePath . 'filesizes.json'), true) ?: [];
}

// ── Suggestions ──────────────────────────────────────────────────────────────
$similaires    = [];
$currentGenres = array_unique($content['genres'] ?? []);
if (!$isAjax && !empty($currentGenres)) {
    if ($isFilm) {
        $pool = array_merge(
            array_map(fn($i) => $i + ['_type' => 'film'],  IndexManager::getAll('film')),
            array_map(fn($i) => $i + ['_type' => 'anime'], IndexManager::getAll('anime')),
            array_map(fn($i) => $i + ['_type' => 'serie'], IndexManager::getAll('serie')),
        );
    } else {
        $pool = array_merge(
            array_map(fn($i) => $i + ['_type' => 'anime'], IndexManager::getAll('anime')),
            array_map(fn($i) => $i + ['_type' => 'film'],  IndexManager::getAll('film')),
        );
    }
    $scored = [];
    foreach ($pool as $item) {
        if ($item['_type'] === $type && $item['slug'] === $slug) continue;
        $itemGenres = array_unique($item['genres'] ?? []);
        $common     = array_values(array_intersect($currentGenres, $itemGenres));
        if (empty($common)) continue;
        $union      = array_unique(array_merge($currentGenres, $itemGenres));
        $jaccard    = count($common) / count($union) * 4.0;
        $sA = $content['score'] ?? null;
        $sB = $item['score']    ?? null;
        $scoreBonus = ($sA !== null && $sB !== null) ? max(0.0, 1 - abs($sA - $sB) / 3) * 1.5 : 0.0;
        $yA = $content['year'] ?? null;
        $yB = $item['year']    ?? null;
        $yearBonus  = ($yA !== null && $yB !== null) ? max(0.0, 1 - abs($yA - $yB) / 10) * 1.0 : 0.0;
        $studioBonus = (!$isFilm && !empty($content['studio']) && !empty($item['studio']) && $content['studio'] === $item['studio']) ? 0.75 : 0.0;
        $scored[] = [
            'item'         => $item,
            'type'         => $item['_type'],
            'total'        => $jaccard + $scoreBonus + $yearBonus + $studioBonus,
            'match_genres' => array_slice($common, 0, 3),
        ];
    }
    usort($scored, fn($a, $b) => $b['total'] <=> $a['total']);
    $similaires = array_slice($scored, 0, 7);
}

require_once __DIR__ . '/../components/card.php';
require_once __DIR__ . '/../components/cast.php';
require_once __DIR__ . '/../components/tabs.php';
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $heroTitle ?><?= $content['year'] ? ' (' . $content['year'] . ')' : '' ?> — Hoshimi</title>
    <link rel="stylesheet" href="/css/hoshimi.css">
    <script src="/js/storage.js"></script>
</head>
<body>
    <?php include __DIR__ . '/../components/navbar.php'; ?>

    <!-- ══════════════════ HERO ══════════════════ -->
    <section class="detail-hero">
        <div class="detail-hero__backdrop" style="background-image: url('<?= htmlspecialchars($content['banner_url'] ?? $content['cover_url'] ?? '') ?>')"></div>
        <div class="detail-hero__gradient"></div>

        <div class="detail-hero__container">
            <div class="detail-hero__content<?= $heroSplit ? ' detail-hero__content--split' : '' ?>">

                <?php if ($heroSplit): ?>
                    <div class="detail-hero__poster-col">
                        <img class="detail-hero__poster-img"
                            src="<?= htmlspecialchars($content['cover_url']) ?>"
                            alt="<?= $heroTitle ?>">
                    </div>
                    <div class="detail-hero__info-col">
                <?php endif; ?>

                <!-- Badges -->
                <div class="detail-hero__badges">
                    <?php if ($isFilm): ?>
                        <span class="badge badge--type"><?= lc('clapperboard', 12) ?> Film</span>
                        <?php if (!($content['has_file'] ?? true)): ?>
                            <span class="badge badge--soon">Prochainement</span>
                        <?php endif; ?>
                    <?php else: ?>
                        <?php $typeIcon = $type === 'serie' ? 'tv' : 'tv-2'; ?>
                        <span class="badge badge--type"><?= lc($typeIcon, 12) ?> <?= $typeLabel ?></span>
                        <?php if ($isTrending): ?>
                            <span class="badge badge--trending"><svg xmlns="http://www.w3.org/2000/svg" width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M12 3q1 4 4 6.5t3 5.5a1 1 0 0 1-14 0 5 5 0 0 1 1-3 1 1 0 0 0 5 0c0-2-1.5-3-1.5-5q0-2 2.5-4"></path></svg> Tendance</span>
                        <?php endif; ?>
                        <?php if (!($seasonData['episodes'][0] ?? null)): ?>
                            <span class="badge badge--soon">Prochainement</span>
                        <?php endif; ?>
                        <?php if ($isAiring): ?>
                            <span class="airing-badge"><span class="airing-dot"></span>En cours</span>
                        <?php endif; ?>
                    <?php endif; ?>
                    <?php if ($scoreDisp): ?>
                        <span class="badge badge--score-hero"><?= lc('star', 12) ?> <?= $scoreDisp ?></span>
                    <?php endif; ?>
                </div>

                <?php if ($heroLogo): ?>
                    <img class="detail-hero__logo" src="<?= htmlspecialchars($heroLogo) ?>" alt="<?= $heroTitle ?>">
                <?php else: ?>
                    <h1 class="detail-hero__title"><?= $heroTitle ?></h1>
                <?php endif; ?>

                <?php if (!$isFilm && !empty($content['title_original']) && $content['title_original'] !== ($content['title_english'] ?: $content['title'])): ?>
                    <p class="detail-hero__subtitle"><?= htmlspecialchars($content['title_original']) ?></p>
                <?php endif; ?>

                <!-- Méta -->
                <div class="detail-hero__meta">
                    <?php if ($statusLabel && !$isFilm): ?>
                        <span class="detail-hero__meta-item"><?= htmlspecialchars($statusLabel) ?></span>
                    <?php endif; ?>
                    <?php if ($content['year'] ?? null): ?>
                        <span class="detail-hero__meta-item"><?= lc('calendar', 13) ?> <?= $content['year'] ?></span>
                    <?php endif; ?>
                    <?php if ($isFilm && $runtimeStr): ?>
                        <span class="detail-hero__meta-item"><?= lc('clock', 13) ?> <?= $runtimeStr ?></span>
                    <?php endif; ?>
                    <?php if ($isFilm && $vodDate): ?>
                        <span class="detail-hero__meta-item" title="Sortie VOD">Disponible le <?= htmlspecialchars($vodDate) ?></span>
                    <?php endif; ?>
                    <?php if (!$isFilm && $epCount > 0): ?>
                        <span class="detail-hero__meta-item"><?= lc('list', 13) ?> <?= $epCount ?> ép.</span>
                    <?php endif; ?>
                </div>

                <!-- Genres -->
                <?php if (!empty($content['genres']) || ($isFilm && !empty($filmEpisode['language']))): ?>
                    <div class="detail-genres">
                        <?php if ($isFilm && !empty($filmEpisode['language'])): ?>
                            <?= renderLangBadges($filmEpisode['language']) ?>
                        <?php elseif (!$isFilm && !empty($content['main_language'])): ?>
                            <a href="#" class="badge badge--lang"><?= htmlspecialchars(strtoupper($content['main_language'])) ?></a>
                        <?php endif; ?>
                        <?php foreach (array_slice($content['genres'], 0, 6) as $genre): ?>
                            <a href="/catalog/?type=<?= urlencode($type) ?>&genre=<?= urlencode($genre) ?>" class="badge"><?= htmlspecialchars($genre) ?></a>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>

                <!-- Actions -->
                <div class="detail-hero__actions">
                    <?php if ($isFilm): ?>
                        <?php if ($content['has_file'] ?? true): ?>
                            <a href="/player/?type=film&slug=<?= urlencode($slug) ?>" class="btn btn--primary" id="watch-btn">
                                <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="currentColor" stroke="none" aria-hidden="true"><path d="M5 5a2 2 0 0 1 3.008-1.728l11.997 6.998a2 2 0 0 1 .003 3.458l-12 7A2 2 0 0 1 5 19z"/></svg>
                                <span id="watch-btn-label">Regarder</span>
                            </a>
                        <?php endif; ?>
                    <?php else: ?>
                        <?php $firstEp = $seasonData['episodes'][0] ?? null; ?>
                        <?php if ($firstEp): ?>
                            <a id="watch-btn"
                                href="/player/?slug=<?= urlencode($slug) ?><?= $typeParam ?>&saison=<?= $activeSeason ?>&episode=<?= $firstEp['number'] ?>"
                                data-default-href="/player/?slug=<?= urlencode($slug) ?><?= $typeParam ?>&saison=<?= $activeSeason ?>&episode=<?= $firstEp['number'] ?>"
                                class="btn btn--primary">
                                <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="currentColor" stroke="none" aria-hidden="true"><path d="M5 5a2 2 0 0 1 3.008-1.728l11.997 6.998a2 2 0 0 1 .003 3.458l-12 7A2 2 0 0 1 5 19z"></path></svg>
                                Premier épisode
                            </a>
                        <?php endif; ?>
                    <?php endif; ?>
                    <button class="btn btn--ghost" data-fav-btn title="Ajouter aux favoris">
                        <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M20.84 4.61a5.5 5.5 0 0 0-7.78 0L12 5.67l-1.06-1.06a5.5 5.5 0 0 0-7.78 7.78l1.06 1.06L12 21.23l7.78-7.78 1.06-1.06a5.5 5.5 0 0 0 0-7.78z"/></svg>
                        Favoris
                    </button>
                    <button class="btn btn--ghost" data-wl-btn title="Watchlist">
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M17 3a2 2 0 0 1 2 2v15a1 1 0 0 1-1.496.868l-4.512-2.578a2 2 0 0 0-1.984 0l-4.512 2.578A1 1 0 0 1 5 20V5a2 2 0 0 1 2-2z"/></svg>
                    </button>
                </div>

                <?php if ($heroSplit): ?>
                    </div><!-- /.detail-hero__info-col -->
                <?php endif; ?>

            </div>
        </div>
    </section>

    <!-- ══════════════════ TABS ══════════════════ -->
    <?php if ($isFilm): ?>
        <?= render_tabs_bar([
            ['id' => 'synopsis',    'label' => 'Synopsis',    'icon' => 'book',       'active' => true],
            ['id' => 'casting',     'label' => 'Casting',     'icon' => 'cast',       'active' => false, 'count' => count($castData)],
            ['id' => 'collection',  'label' => $collectionName ?? 'Collection', 'icon' => 'collection', 'active' => false, 'hidden' => empty($collectionCards), 'count' => count($collectionCards)],
            ['id' => 'suggestions', 'label' => 'Suggestions', 'icon' => 'sparkles',   'active' => false, 'hidden' => empty($similaires)],
        ]) ?>
    <?php else: ?>
        <?= render_tabs_bar([
            ['id' => 'synopsis',    'label' => 'Synopsis',          'icon' => 'book',     'active' => false],
            ['id' => 'episodes',    'label' => 'Saisons & Épisodes', 'icon' => 'play',    'active' => true, 'count' => $epCount],
            ['id' => 'cast',        'label' => $type === 'serie' ? 'Casting' : 'Personnages', 'icon' => 'cast', 'active' => false, 'count' => count($castData)],
            ['id' => 'suggestions', 'label' => 'Suggestions',       'icon' => 'sparkles', 'active' => false, 'hidden' => empty($similaires)],
        ]) ?>
    <?php endif; ?>

    <!-- ══════════════════ BODY ══════════════════ -->
    <div class="detail-body">

        <!-- ── Synopsis ── -->
        <div class="detail-tab-panel" id="tab-synopsis" <?= !$isFilm ? 'hidden' : '' ?>>
            <?php if ($synopsis): ?>
                <div class="synopsis-section">
                    <p class="synopsis"><?= htmlspecialchars($synopsis) ?></p>
                </div>
            <?php else: ?>
                <p style="color:var(--color-text-muted); font-size:.9rem;">Aucun synopsis disponible.</p>
            <?php endif; ?>
        </div>

        <?php if ($isFilm): ?>

            <!-- ── Casting (film) ── -->
            <div class="detail-tab-panel" id="tab-casting" hidden>
                <?= render_character_grid($castData, 'Aucune donnée de casting disponible.', 'Acteurs') ?>
            </div>

            <!-- ── Collection ── -->
            <?php if (!empty($collectionCards)): ?>
                <div class="detail-tab-panel" id="tab-collection" hidden>
                    <div class="collection-section">
                        <div class="grid-cards">
                            <?php foreach ($collectionCards as $card): ?>
                                <?php if ($card['local']): ?>
                                    <?= render_card($card['local'], 'film', ['collection_current' => $card['is_current']]) ?>
                                <?php else: ?>
                                    <?= render_card([
                                        'title'     => $card['title'],
                                        'cover_url' => $card['cover_url'],
                                        'year'      => $card['year'],
                                        'slug'      => '',
                                        'has_file'  => false,
                                    ], 'film', [
                                        'href'        => '#',
                                        'unavailable' => true,
                                    ]) ?>
                                <?php endif; ?>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>
            <?php endif; ?>

            <!-- ── Suggestions (film) ── -->
            <?php if (!empty($similaires)): ?>
                <div class="detail-tab-panel" id="tab-suggestions" hidden>
                    <div class="suggestions-section">
                        <div class="grid-cards grid-cards--7">
                            <?php foreach ($similaires as $simData): ?>
                                <?= render_card($simData['item'], $simData['type'], ['match_genres' => $simData['match_genres']]) ?>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>
            <?php endif; ?>

        <?php else: ?>

            <!-- ── Épisodes (anime/série) ── -->
            <?php if ($isAjax): ob_start(); ?>
                <div class="detail-tab-panel" id="tab-episodes">
            <?php else: ?>
                <div class="detail-tab-panel" id="tab-episodes">
            <?php endif; ?>

                <?php if (count($content['seasons']) > 1):
                    $activeLabel = '';
                    foreach ($content['seasons'] as $s) {
                        if ((int)$s['number'] === (int)$activeSeason) { $activeLabel = $s['label']; break; }
                    }
                ?>
                    <div class="cs-wrap" id="season-select" data-value="<?= $activeSeason ?>">
                        <button class="cs-btn" type="button" aria-haspopup="listbox" aria-expanded="false">
                            <span class="cs-label"><?= htmlspecialchars($activeLabel) ?></span>
                        </button>
                        <ul class="cs-list" role="listbox">
                            <?php foreach ($content['seasons'] as $s):
                                $isActive = (int)$s['number'] === (int)$activeSeason;
                                $sEpCount = count($s['episodes'] ?? []);
                            ?>
                                <li class="cs-item <?= $isActive ? 'is-active' : '' ?>"
                                    role="option"
                                    aria-selected="<?= $isActive ? 'true' : 'false' ?>"
                                    data-value="<?= $s['number'] ?>"
                                    data-href="?slug=<?= urlencode($slug) ?><?= $typeParam ?>&saison=<?= $s['number'] ?>">
                                    <span class="cs-check"><?php if ($isActive): ?><svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"/></svg><?php endif; ?></span>
                                    <span class="cs-body">
                                        <span class="cs-item-label"><?= htmlspecialchars($s['label']) ?></span>
                                        <span class="cs-item-count"><?= $sEpCount ?> épisode<?= $sEpCount > 1 ? 's' : '' ?></span>
                                    </span>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                <?php endif; ?>

                <?php
                $localCount = count($seasonData['episodes'] ?? []);
                $futureEps  = array_filter($tmdbEpisodes, fn($idx) => $idx > $localCount, ARRAY_FILTER_USE_KEY);
                ?>
                <?php if (!empty($seasonData['episodes']) || !empty($tmdbEpisodes)): ?>
                    <div class="episode-list">
                        <?php foreach ($seasonData['episodes'] ?? [] as $ep):
                            $isFilmEp  = ($activeSeason == 999);
                            $epNum     = $ep['number'];
                            $epKey     = md5($ep['file_path']);
                            $lang      = strtoupper($ep['language'] ?? 'VOSTFR');
                            $filesize  = $sizes[$epKey] ?? null;
                            $tmdbEp    = $tmdbEpisodes[$epNum] ?? null;
                            $stillUrl  = $tmdbEp['still'] ?? $tmdbEp['still_urls']['w300'] ?? $tmdbEp['still_urls']['w500'] ?? null;
                            $epTmdbName= $tmdbEp['name'] ?? null;
                            $epOverview= $tmdbEp['overview'] ?? null;
                            $duration  = ($tmdbEp['runtime'] ?? 0) > 0
                                ? ($tmdbEp['runtime'] . ' min')
                                : ($durations[$epKey] ?? null);
                            $thumb     = ThumbnailHelper::getUrlFromIndex($ep['file_path']);
                            $imgSrc    = $stillUrl ?: $thumb;
                            if ($isFilmEp) {
                                $epLabel = trim(str_ireplace(['.mkv', '.mp4', '1080p', '720p', 'MULTISUB', 'MULTI'], '', $ep['title']), " _-") ?: 'Film';
                            } else {
                                $epLabel = 'E' . str_pad((string)$epNum, 2, '0', STR_PAD_LEFT);
                                if ($epTmdbName) $epLabel .= ' - ' . $epTmdbName;
                                elseif ($ep['title'] && $ep['title'] !== $epLabel) $epLabel .= ' - ' . $ep['title'];
                            }
                        ?>
                            <a href="/player/?slug=<?= urlencode($slug) ?><?= $typeParam ?>&saison=<?= $activeSeason ?>&episode=<?= $epNum ?>"
                                class="episode-card"
                                data-ep-key="<?= $epKey ?>">
                                <div class="episode-card__thumbnail <?= empty($imgSrc) ? 'is-empty' : '' ?>">
                                    <?php if ($imgSrc): ?>
                                        <img src="<?= htmlspecialchars($imgSrc) ?>" alt="" loading="lazy" onerror="this.parentElement.classList.add('is-empty'); this.remove();">
                                    <?php endif; ?>
                                    <div class="episode-card__placeholder-number"><?= str_pad((string)$epNum, 2, '0', STR_PAD_LEFT) ?></div>
                                    <div class="episode-card__overlay">
                                        <div class="episode-card__play-icon"><?= lc('play', 16) ?></div>
                                    </div>
                                    <?php if ($duration): ?>
                                        <div class="episode-card__duration"><?= $duration ?></div>
                                    <?php endif; ?>
                                    <div class="episode-card__progress">
                                        <div class="episode-card__progress-fill" style="width:0%"></div>
                                    </div>
                                </div>
                                <div class="episode-card__body">
                                    <span class="episode-card__label"><?= htmlspecialchars($epLabel) ?></span>
                                    <?php if ($epOverview): ?>
                                        <p class="episode-card__description"><?= htmlspecialchars($epOverview) ?></p>
                                    <?php endif; ?>
                                    <div class="episode-card__info">
                                        <?= renderLangBadges($lang) ?>
                                        <?php if ($filesize): ?>
                                            <span style="font-size:0.65rem; color:var(--color-text-muted); margin-left:auto;"><?= $filesize ?></span>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </a>
                        <?php endforeach; ?>

                        <?php
                        $moisFr = ['','janv.','févr.','mars','avr.','mai','juin','juil.','août','sept.','oct.','nov.','déc.'];
                        ?>
                        <?php foreach ($futureEps as $epNum => $ep):
                            $epName    = $ep['name'] ?? null;
                            $airDate   = $ep['air_date'] ?? null;
                            $runtime   = isset($ep['runtime']) && $ep['runtime'] > 0 ? $ep['runtime'] . ' min' : null;
                            $epLabel   = 'E' . str_pad((string)$epNum, 2, '0', STR_PAD_LEFT) . ($epName ? ' - ' . $epName : '');
                            $airDateFmt = null;
                            if ($airDate && preg_match('/^(\d{4})-(\d{2})-(\d{2})$/', $airDate, $dm)) {
                                $airDateFmt = (int)$dm[3] . ' ' . $moisFr[(int)$dm[2]] . ' ' . $dm[1];
                            }
                            $epStill = $ep['still'] ?? $ep['still_urls']['w300'] ?? $ep['still_urls']['w500'] ?? null;
                        ?>
                            <div class="episode-card episode-card--upcoming">
                                <div class="episode-card__thumbnail <?= $epStill ? 'has-still' : 'is-empty' ?>">
                                    <?php if ($epStill): ?>
                                        <img src="<?= htmlspecialchars($epStill) ?>" alt="" class="episode-card__still-blur" loading="lazy">
                                    <?php endif; ?>
                                    <div class="episode-card__upcoming-overlay">
                                        <span class="episode-card__upcoming-num">Ep. <?= (int)$epNum ?></span>
                                        <span class="episode-card__upcoming-soon">Bientôt dispo</span>
                                    </div>
                                    <?php if ($runtime): ?>
                                        <div class="episode-card__duration"><?= $runtime ?></div>
                                    <?php endif; ?>
                                </div>
                                <div class="episode-card__body">
                                    <span class="episode-card__label"><?= htmlspecialchars($epLabel) ?></span>
                                    <?php if (!empty($ep['overview'])): ?>
                                        <p class="episode-card__description"><?= htmlspecialchars($ep['overview']) ?></p>
                                    <?php endif; ?>
                                    <?php if ($airDateFmt): ?>
                                        <span class="episode-card__airdate">Dispo le <?= $airDateFmt ?></span>
                                    <?php endif; ?>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php else: ?>
                    <div class="no-episodes">
                        <div class="no-episodes__icon">
                            <svg viewBox="0 0 24 24" aria-hidden="true"><circle cx="12" cy="12" r="9"/><polyline points="12 7 12 12 15 15"/></svg>
                        </div>
                        <p class="no-episodes__text">Aucun épisode disponible pour le moment.</p>
                        <p class="no-episodes__sub">Le contenu sera ajouté prochainement.</p>
                    </div>
                <?php endif; ?>

            </div><!-- #tab-episodes -->
            <?php if ($isAjax): header('Content-Type: text/html; charset=utf-8'); echo ob_get_clean(); exit; endif; ?>

            <!-- ── Cast ── -->
            <div class="detail-tab-panel" id="tab-cast" hidden>
                <?= render_character_grid($castData, 'Aucune donnée disponible.', $type === 'anime' ? null : 'Acteurs') ?>
            </div>

            <!-- ── Suggestions (anime/série) ── -->
            <?php if (!empty($similaires)): ?>
                <div class="detail-tab-panel" id="tab-suggestions" hidden>
                    <div class="suggestions-section">
                        <div class="grid-cards grid-cards--7">
                            <?php foreach ($similaires as $simData): ?>
                                <?= render_card($simData['item'], $simData['type'], ['match_genres' => $simData['match_genres']]) ?>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>
            <?php endif; ?>

        <?php endif; ?>

    </div><!-- .detail-body -->

    <?php include __DIR__ . '/../components/footer.php'; ?>

    <script src="/js/guard.js"></script>
    <script src="/js/parallax.js"></script>
    <script src="/js/tabs.js"></script>
    <?php if (!$isFilm): ?>
    <script src="/js/select.js"></script>
    <?php endif; ?>

    <script>
    <?php if ($isFilm): ?>
        // ── Film : Reprendre ──────────────────────────────────────────────────
        (function() {
            const epKey = <?= json_encode($filmEpKey) ?>;
            const btn   = document.getElementById('watch-btn');
            const label = document.getElementById('watch-btn-label');
            if (!epKey || !btn || !label) return;
            const p = HoshimiStorage.Progress.get(epKey);
            if (!p || p.position < 10 || p.completed) return;
            const pos = Math.floor(p.position);
            const dur = p.duration ?? 0;
            const pct = dur > 0 ? Math.min(100, (pos / dur) * 100) : 0;
            const h = Math.floor(pos / 3600);
            const m = Math.floor((pos % 3600) / 60);
            const s = pos % 60;
            const time = h > 0
                ? `${h}:${String(m).padStart(2,'0')}:${String(s).padStart(2,'0')}`
                : `${m}:${String(s).padStart(2,'0')}`;
            btn.classList.add('btn--resume');
            btn.innerHTML = `<svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="currentColor" stroke="none" aria-hidden="true"><path d="M5 5a2 2 0 0 1 3.008-1.728l11.997 6.998a2 2 0 0 1 .003 3.458l-12 7A2 2 0 0 1 5 19z"/></svg><span>Reprendre</span><span class="btn-resume__sep">·</span><span class="btn-resume__time">${time}</span><span class="btn-resume__bar"><span class="btn-resume__fill" style="width:${pct}%"></span></span>`;
        })();

    <?php else: ?>

        const allAnimeEps = <?= json_encode(array_merge(...array_map(
            fn($s) => array_map(fn($ep) => [
                'key'    => md5($ep['file_path']),
                'season' => $s['number'],
                'number' => $ep['number'],
            ], $s['episodes']),
            $content['seasons']
        ))) ?>;

        const seasonEps = <?= json_encode(array_map(
            fn($ep) => ['key' => md5($ep['file_path']), 'number' => $ep['number']],
            $seasonData['episodes'] ?? []
        )) ?>;

        function initSeasonSelect() {
            const wrap = document.getElementById('season-select');
            if (!wrap) return;
            HoshimiSelect.init(wrap);
            wrap.addEventListener('cs:change', ({ detail: { item } }) => {
                const href = item.dataset.href;
                if (!href) return;
                const panel = document.getElementById('tab-episodes');
                if (!panel) { window.location.href = href; return; }
                panel.style.opacity = '0.4';
                fetch(href + '&ajax=1')
                    .then(r => r.text())
                    .then(html => {
                        const tmp = document.createElement('div');
                        tmp.innerHTML = html;
                        const inner = tmp.querySelector('#tab-episodes');
                        if (!inner) { window.location.href = href; return; }
                        panel.innerHTML = inner.innerHTML;
                        panel.style.opacity = '';
                        history.pushState(null, '', href);
                        initSeasonSelect();
                        initProgressBars();
                    })
                    .catch(() => { window.location.href = href; });
            });
        }

        function initProgressBars() {
            const allProgress = HoshimiStorage.Progress.getAllProgress();
            document.querySelectorAll('[data-ep-key]').forEach(el => {
                const p = allProgress[el.dataset.epKey];
                if (!p || p.duration <= 0) return;
                const pct = (p.position / p.duration) * 100;
                const isFinished = p.completed || pct >= 92;
                const bar = el.querySelector('.episode-card__progress-fill');
                if (bar) bar.style.width = (isFinished ? 100 : pct) + '%';
                if (isFinished) el.classList.add('is-completed');
            });
        }

        initSeasonSelect();

        document.addEventListener('DOMContentLoaded', () => {
            initProgressBars();
            const allProgress = HoshimiStorage.Progress.getAllProgress();
            const watchBtn = document.getElementById('watch-btn');
            if (watchBtn) {
                let resumeEp = null;
                allAnimeEps.forEach(ep => {
                    const p = allProgress[ep.key];
                    if (!p || p.completed || p.position <= 10) return;
                    if (!resumeEp || p.updated_at > allProgress[resumeEp.key]?.updated_at) resumeEp = ep;
                });
                if (resumeEp) {
                    const p   = allProgress[resumeEp.key];
                    const pos = p?.position ?? 0;
                    const dur = p?.duration  ?? 0;
                    const pct = dur > 0 ? Math.min(100, (pos / dur) * 100) : 0;
                    const fmt = s => { const m = Math.floor(s/60); return m + ':' + String(Math.floor(s%60)).padStart(2,'0'); };
                    const epCode = `S${String(resumeEp.season).padStart(2,'0')}E${String(resumeEp.number).padStart(2,'0')}`;
                    watchBtn.href = `/player/?slug=<?= urlencode($slug) ?><?= $typeParam ?>&saison=${resumeEp.season}&episode=${resumeEp.number}`;
                    watchBtn.classList.add('btn--resume');
                    watchBtn.innerHTML = `<svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="currentColor" stroke="none" aria-hidden="true"><path d="M5 5a2 2 0 0 1 3.008-1.728l11.997 6.998a2 2 0 0 1 .003 3.458l-12 7A2 2 0 0 1 5 19z"/></svg><span>Reprendre</span><span class="btn-resume__sep">·</span><span class="btn-resume__ep">${epCode}</span><span class="btn-resume__sep">·</span><span class="btn-resume__time">${fmt(pos)}</span><span class="btn-resume__bar"><span class="btn-resume__fill" style="width:${pct}%"></span></span>`;
                }
            }
        });
    <?php endif; ?>

        const _contentMeta = {
            title:     <?= json_encode($isFilm ? $content['title'] : ($content['title_english'] ?: $content['title'])) ?>,
            cover_url: <?= json_encode($content['cover_url'] ?? '') ?>,
            type:      <?= json_encode($type) ?>,
            year:      <?= (int)($content['year'] ?? 0) ?: 'null' ?>,
            genres:    <?= json_encode(array_values($content['genres'] ?? [])) ?>,
        };
        HoshimiStorage.initFavoriteButton(document.querySelector('[data-fav-btn]'), <?= json_encode($slug) ?>, _contentMeta);
        HoshimiStorage.initWatchlistButton(document.querySelector('[data-wl-btn]'), <?= json_encode($slug) ?>, _contentMeta);
    </script>

</body>
</html>
