<?php

declare(strict_types=1);

require_once '../../app/Services/AnimeScanner.php';
require_once '../../app/Services/ThumbnailHelper.php';

$type      = $_GET['type'] ?? 'anime';
$typeParam = $type !== 'anime' ? '&type=' . urlencode($type) : '';

if ($type === 'serie') {
    require_once '../../app/Services/SeriesScanner.php';
    $scanner = new SeriesScanner();
} else {
    $scanner = new AnimeScanner();
}

ThumbnailHelper::loadIndex();

$slug  = $_GET['slug'] ?? '';
$anime = $slug ? $scanner->getAnimeBySlug($slug) : null;

if (!$anime) {
    include __DIR__ . '/../404.php';
    exit;
}


$activeSeason = (int) ($_GET['saison'] ?? 0);
$seasonData = null;
foreach ($anime['seasons'] as $s) {
    if ($s['number'] === $activeSeason) { $seasonData = $s; break; }
}
if ($seasonData === null) {
    $seasonData   = $anime['seasons'][0] ?? null;
    $activeSeason = $seasonData['number'] ?? 0;
}

// ── Synopsis nettoyé ────────────────────────────────────────────────────────
$synopsis = null;
if (!empty($anime['synopsis'])) {
    $clean   = preg_replace('/<br\s*\/?>/i', "\n", $anime['synopsis']);
    $clean   = strip_tags($clean);
    $clean   = html_entity_decode($clean, ENT_QUOTES | ENT_HTML5, 'UTF-8');
    $synopsis = trim(preg_replace("/\n{3,}/", "\n\n", $clean));
}

// ── TMDB logo URL ───────────────────────────────────────────────────────────
$heroLogo = null;
$metaRaw  = $anime['metadata'] ?? [];


if (!empty($metaRaw['images']['logos'])) {
    $logos = $metaRaw['images']['logos'];
    usort($logos, fn($a, $b) => (($b['iso_639_1'] ?? '') === 'en') <=> (($a['iso_639_1'] ?? '') === 'en'));
    $logoEntry = $logos[0];
    $heroLogo  = $logoEntry['urls']['w500'] ?? $logoEntry['urls']['original'] ?? null;
    if (!$heroLogo && !empty($logoEntry['file_path'])) {
        $heroLogo = 'https://image.tmdb.org/t/p/w500' . $logoEntry['file_path'];
    }
}

// ── Meta hero ───────────────────────────────────────────────────────────────
$typeLabel  = $type === 'serie' ? 'SÉRIE' : ($type === 'film' ? 'FILM' : 'ANIME');
$genresStr  = implode(', ', array_slice($anime['genres'] ?? [], 0, 3));
$epCount    = $anime['episodes_local'] ?? 0;
$scoreDisp  = $anime['score'] ? number_format((float)$anime['score'], 1) : null;

$isTrending = $anime['is_trending'] ?? false;

$statusLabels = [
    // AniList
    'FINISHED'          => 'Terminé',
    'RELEASING'         => 'En cours',
    'NOT_YET_RELEASED'  => 'À venir',
    'CANCELLED'         => 'Annulé',
    'HIATUS'            => 'En pause',
    // TMDB (strtoupper)
    'ENDED'             => 'Terminé',
    'RETURNING SERIES'  => 'En cours',
    'CANCELED'          => 'Annulé',
    'IN PRODUCTION'     => 'En production',
    'PLANNED'           => 'À venir',
    'PILOT'             => 'Pilote',
];
$statusLabel  = !empty($anime['status']) ? ($statusLabels[$anime['status']] ?? $anime['status']) : null;
$statusClass  = 'badge--status-' . str_replace([' ', '_'], '-', strtolower($anime['status'] ?? ''));

// ── Personnages (AniList) / Casting (TMDB serie) ─────────────────────────────
$castData = $metaRaw['credits']['cast'] ?? [];

// ── TMDB données d'épisodes pour la saison active ───────────────────────────
$tmdbEpisodes = [];
if ($seasonData && !empty($seasonData['episodes'])) {
    $firstEpPath  = $seasonData['episodes'][0]['file_path'] ?? '';
    $seasonFolder = $firstEpPath ? dirname($firstEpPath) : '';
    // Try Season N/metadata.json first, then root metadata.json
    foreach ([$seasonFolder . '/metadata.json', dirname($seasonFolder) . '/metadata.json'] as $candidate) {
        if ($candidate !== '/metadata.json' && file_exists($candidate)) {
            $seasonJson = @json_decode(@file_get_contents($candidate), true) ?: [];
            // Season format: { "season_number": 1, "episodes": [...] }
            if (!empty($seasonJson['episodes']) && isset($seasonJson['season_number'])) {
                // Re-index sequentially from 1 so local ep numbers match
                // even when season-map maps to TMDB episodes 13-24 etc.
                $localNum = 1;
                foreach ($seasonJson['episodes'] as $tmdbEp) {
                    $tmdbEpisodes[$localNum++] = $tmdbEp;
                }
                break;
            }
        }
    }
}

// ── Suggestions (score multi-facteurs) ──────────────────────────────────────
$similaires = [];
$currentGenres = array_unique($anime['genres'] ?? []);
if (!empty($currentGenres)) {
    $allItems = ($type === 'serie' && method_exists($scanner, 'getAllSeries'))
        ? $scanner->getAllSeries()
        : $scanner->getAllAnimes();
    $scored = [];
    foreach ($allItems as $item) {
        if ($item['slug'] === $slug) continue;
        $itemGenres = array_unique($item['genres'] ?? []);
        $common     = array_values(array_intersect($currentGenres, $itemGenres));
        if (empty($common)) continue;

        // Jaccard genre similarity (pénalise les items avec peu de genres en commun sur beaucoup)
        $union   = array_unique(array_merge($currentGenres, $itemGenres));
        $jaccard = count($common) / count($union) * 4.0;

        // Proximité de note (échelle 0-100)
        $sA = $anime['score'] ?? null;
        $sB = $item['score']  ?? null;
        $scoreBonus = ($sA !== null && $sB !== null)
            ? max(0.0, 1 - abs($sA - $sB) / 3) * 1.5 : 0.0;

        // Proximité d'année (±10 ans max)
        $yA = $anime['year'] ?? null;
        $yB = $item['year']  ?? null;
        $yearBonus = ($yA !== null && $yB !== null)
            ? max(0.0, 1 - abs($yA - $yB) / 10) * 1.0 : 0.0;

        // Même studio
        $studioBonus = (!empty($anime['studio']) && !empty($item['studio']) && $anime['studio'] === $item['studio'])
            ? 0.75 : 0.0;

        $scored[] = [
            'item'         => $item,
            'total'        => $jaccard + $scoreBonus + $yearBonus + $studioBonus,
            'match_genres' => array_slice($common, 0, 3),
        ];
    }
    usort($scored, fn($a, $b) => $b['total'] <=> $a['total']);
    $similaires = array_slice($scored, 0, 12);
}

$durations  = [];
$sizes      = [];
$cachePath  = $_SERVER['DOCUMENT_ROOT'] . '/images/thumbnails/';
if (file_exists($cachePath . 'durations.json')) $durations = json_decode(file_get_contents($cachePath . 'durations.json'), true) ?: [];
if (file_exists($cachePath . 'filesizes.json')) $sizes     = json_decode(file_get_contents($cachePath . 'filesizes.json'), true) ?: [];

require_once __DIR__ . '/../components/card.php';
require_once __DIR__ . '/../components/cast.php';
require_once __DIR__ . '/../components/tabs.php';
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($anime['title_english'] ?: $anime['title']) ?> — Hoshimi</title>
    <link rel="stylesheet" href="/css/hoshimi.css">
</head>
<body>
    <?php include "../components/navbar.php"; ?>

    <!-- ══════════════════ HERO BANNER ══════════════════ -->
    <?php
        $firstEp  = $seasonData['episodes'][0] ?? null;
        $heroTitle = htmlspecialchars($anime['title_english'] ?: $anime['title']);
        $heroSplit = !$heroLogo && !empty($anime['cover_url']);
    ?>
    <section class="detail-hero">
        <div class="detail-hero__backdrop" style="background-image: url('<?= htmlspecialchars($anime['banner_url'] ?: ($anime['cover_url'] ?? '')) ?>')"></div>
        <div class="detail-hero__gradient"></div>
        <?php if (!$heroSplit && !empty($anime['cover_url'])): ?>
            <img data-cover-img src="<?= htmlspecialchars($anime['cover_url']) ?>" alt="" style="display:none" crossorigin="anonymous">
        <?php endif; ?>

        <div class="detail-hero__container">
        <div class="detail-hero__content<?= $heroSplit ? ' detail-hero__content--split' : '' ?>">

            <?php if ($heroSplit): ?>
            <!-- ── Mode sans logo : cover à gauche, info à droite ── -->
            <div class="detail-hero__poster-col">
                <img class="detail-hero__poster-img"
                     src="<?= htmlspecialchars($anime['cover_url']) ?>"
                     alt="<?= $heroTitle ?>"
                     data-cover-img>
            </div>
            <div class="detail-hero__info-col">
            <?php endif; ?>

                <!-- Badges Tendance + Prochainement + score -->
                <div class="detail-hero__badges">
                    <?php if ($isTrending): ?>
                        <span class="badge badge--trending"><svg xmlns="http://www.w3.org/2000/svg" width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M12 3q1 4 4 6.5t3 5.5a1 1 0 0 1-14 0 5 5 0 0 1 1-3 1 1 0 0 0 5 0c0-2-1.5-3-1.5-5q0-2 2.5-4"></path></svg> Tendance</span>
                    <?php endif; ?>
                    <?php if (!$firstEp): ?>
                        <span class="badge badge--soon">Prochainement</span>
                    <?php endif; ?>
                    <?php if ($scoreDisp): ?>
                        <span class="badge badge--score-hero">★ <?= $scoreDisp ?></span>
                    <?php endif; ?>
                </div>

                <!-- Logo ou titre -->
                <?php if ($heroLogo): ?>
                    <img class="detail-hero__logo"
                         src="<?= htmlspecialchars($heroLogo) ?>"
                         alt="<?= $heroTitle ?>">
                <?php else: ?>
                    <h1 class="detail-hero__title"><?= $heroTitle ?></h1>
                <?php endif; ?>

                <!-- Titre original -->
                <?php if (!empty($anime['title_original']) && $anime['title_original'] !== ($anime['title_english'] ?: $anime['title'])): ?>
                    <p class="detail-hero__subtitle"><?= htmlspecialchars($anime['title_original']) ?></p>
                <?php endif; ?>

                <!-- Méta : TYPE • GENRES • ANNÉE • N ÉPISODES -->
                <div class="detail-hero__meta">
                    <span class="detail-hero__meta-type"><?= $typeLabel ?></span>
                    <?php if ($genresStr): ?>
                        <span class="detail-hero__meta-sep">•</span>
                        <span><?= htmlspecialchars(strtoupper($genresStr)) ?></span>
                    <?php endif; ?>
                    <?php if ($anime['year'] ?? null): ?>
                        <span class="detail-hero__meta-sep">•</span>
                        <span><?= $anime['year'] ?></span>
                    <?php endif; ?>
                    <?php if ($epCount > 0): ?>
                        <span class="detail-hero__meta-sep">•</span>
                        <span><?= $epCount ?> ÉPISODE<?= $epCount > 1 ? 'S' : '' ?></span>
                    <?php endif; ?>
                    <?php if ($statusLabel): ?>
                        <span class="detail-hero__meta-sep">•</span>
                        <span class="badge <?= $statusClass ?>"><?= htmlspecialchars($statusLabel) ?></span>
                    <?php endif; ?>
                </div>

                <!-- Studio -->
                <?php if (!empty($anime['studio'])): ?>
                    <div class="detail-hero__dates"><?= htmlspecialchars($anime['studio']) ?></div>
                <?php endif; ?>

                <!-- Genres (badges cliquables) -->
                <?php if (!empty($anime['genres'])): ?>
                    <div class="detail-genres">
                        <?php if (!empty($anime['main_language'])): ?>
                            <a href="#" class="badge badge--lang"><?= htmlspecialchars(strtoupper($anime['main_language'])) ?></a>
                        <?php endif; ?>
                        <?php foreach (array_slice($anime['genres'], 0, 6) as $genre): ?>
                            <a href="/?genre=<?= urlencode($genre) ?>" class="badge"><?= htmlspecialchars($genre) ?></a>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>

                <!-- Actions -->
                <div class="detail-hero__actions">
                    <?php if ($firstEp): ?>
                        <a id="watch-btn"
                           href="/player/?slug=<?= urlencode($slug) ?><?= $typeParam ?>&saison=<?= $activeSeason ?>&episode=<?= $firstEp['number'] ?>"
                           data-default-href="/player/?slug=<?= urlencode($slug) ?><?= $typeParam ?>&saison=<?= $activeSeason ?>&episode=<?= $firstEp['number'] ?>"
                           class="btn btn--primary">
                            ▶ Premier épisode
                        </a>
                    <?php endif; ?>
                    <button class="btn btn--ghost" data-fav-btn title="Ajouter aux favoris">♡ Favoris</button>
                </div>

            <?php if ($heroSplit): ?>
            </div><!-- /.detail-hero__info-col -->
            <?php endif; ?>

        </div><!-- /.detail-hero__content -->
        </div><!-- /.detail-hero__container -->
    </section>

    <!-- ══════════════════ TABS BAR ══════════════════ -->
    <?php $totalEpCount = $anime['episodes_local'] ?? 0; ?>
    <?= render_tabs_bar([
        ['id' => 'synopsis',    'label' => 'Synopsis',          'icon' => 'book',     'active' => false],
        ['id' => 'episodes',    'label' => 'Saisons & Épisodes', 'icon' => 'play',    'active' => true, 'count' => $totalEpCount],
        ['id' => 'cast',        'label' => 'Casting',           'icon' => 'cast',     'active' => false],
        ['id' => 'suggestions', 'label' => 'Suggestions',       'icon' => 'sparkles', 'active' => false, 'hidden' => empty($similaires)],
    ]) ?>

    <!-- ══════════════════ BODY ══════════════════ -->
    <div class="detail-body">

        <!-- ── Panel : Synopsis & Détails (masqué par défaut) ── -->
        <div class="detail-tab-panel" id="tab-synopsis" hidden>
            <?php if ($synopsis): ?>
                <div class="synopsis-section">
                    <p class="synopsis"><?= htmlspecialchars($synopsis) ?></p>
                </div>
            <?php else: ?>
                <p style="color:var(--color-text-muted); font-size:.9rem;">Aucun synopsis disponible.</p>
            <?php endif; ?>
        </div>

        <!-- ── Panel : Saisons & Épisodes (actif par défaut) ── -->
        <div class="detail-tab-panel" id="tab-episodes">

            <!-- Season select (si plusieurs) -->
            <?php if (count($anime['seasons']) > 1): ?>
                <div class="season-select-wrap" id="seasons">
                    <button class="season-select-btn" id="seasonSelectBtn" aria-haspopup="listbox" aria-expanded="false">
                        <span id="seasonSelectLabel">
                            <?php foreach ($anime['seasons'] as $s): ?>
                                <?php if ((int)$s['number'] === (int)$activeSeason): ?>
                                    <?= htmlspecialchars($s['label']) ?>
                                <?php endif; ?>
                            <?php endforeach; ?>
                        </span>
                        <svg class="season-select-chevron" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><polyline points="6 9 12 15 18 9"/></svg>
                    </button>
                    <ul class="season-select-dropdown" id="seasonSelectDropdown" role="listbox">
                        <?php foreach ($anime['seasons'] as $s):
                            $isActive = (int)$s['number'] === (int)$activeSeason;
                            $epCount  = count($s['episodes'] ?? []);
                        ?>
                            <li class="season-select-item <?= $isActive ? 'is-active' : '' ?>"
                                role="option"
                                aria-selected="<?= $isActive ? 'true' : 'false' ?>"
                                data-href="?slug=<?= urlencode($slug) ?>&saison=<?= $s['number'] ?>#seasons">
                                <span class="season-select-check">
                                    <?php if ($isActive): ?>
                                        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"/></svg>
                                    <?php endif; ?>
                                </span>
                                <span class="season-select-info">
                                    <span class="season-select-name"><?= htmlspecialchars($s['label']) ?></span>
                                    <?php if ($epCount > 0): ?>
                                        <span class="season-select-count"><?= $epCount ?> épisode<?= $epCount > 1 ? 's' : '' ?></span>
                                    <?php endif; ?>
                                </span>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            <?php endif; ?>

            <?php if ($seasonData && !empty($seasonData['episodes'])): ?>
                <div class="episode-list">
                    <?php foreach ($seasonData['episodes'] as $ep):
                        $isFilm   = ($activeSeason == 999);
                        $epNum    = $ep['number'];
                        $epKey    = md5($ep['file_path']);
                        $thumb    = ThumbnailHelper::getUrlFromIndex($ep['file_path']);
                        $quality  = str_contains($ep['file_path'], '1080') ? '1080p' : '720p';
                        $duration = $durations[$epKey] ?? null;
                        $filesize = $sizes[$epKey] ?? null;
                        $lang     = strtoupper($ep['language'] ?? 'VOSTFR');

                        // TMDB episode data
                        $tmdbEp      = $tmdbEpisodes[$epNum] ?? null;
                        $stillUrl    = $tmdbEp['still_urls']['w300'] ?? null;
                        $epTmdbName  = $tmdbEp['name'] ?? null;
                        $epOverview  = $tmdbEp['overview'] ?? null;

                        // Use TMDB still if available, else local thumbnail
                        $imgSrc = $stillUrl ?: $thumb;

                        // Label: "E01 - Titre de l'épisode" ou "E01"
                        if ($isFilm) {
                            $epLabel = trim(str_ireplace(['.mkv','.mp4','1080p','720p','MULTISUB','MULTI'], '', $ep['title']), " _-") ?: 'Film';
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
                                    <img src="<?= htmlspecialchars($imgSrc) ?>"
                                         alt=""
                                         loading="lazy"
                                         onerror="this.parentElement.classList.add('is-empty'); this.remove();">
                                <?php endif; ?>
                                <div class="episode-card__placeholder-number"><?= str_pad((string)$epNum, 2, '0', STR_PAD_LEFT) ?></div>
                                <div class="episode-card__overlay">
                                    <div class="episode-card__play-icon">▶</div>
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
                                    <span class="episode-card__lang" data-lang="<?= $lang ?>"><?= $lang ?></span>
                                    <?php if ($filesize): ?>
                                        <span style="font-size:0.65rem; color:var(--color-text-muted); margin-left:auto;"><?= $filesize ?></span>
                                    <?php endif; ?>
                                </div>
                            </div>

                        </a>
                    <?php endforeach; ?>
                </div>
            <?php else: ?>
                <div class="no-episodes">
                    <div class="no-episodes__icon">
                        <svg viewBox="0 0 24 24" aria-hidden="true">
                            <circle cx="12" cy="12" r="9"/>
                            <polyline points="12 7 12 12 15 15"/>
                        </svg>
                    </div>
                    <p class="no-episodes__text">Aucun épisode disponible pour le moment.</p>
                    <p class="no-episodes__sub">Le contenu sera ajouté prochainement.</p>
                </div>
            <?php endif; ?>
        </div><!-- /.detail-tab-panel#tab-episodes -->

        <!-- ── Panel : Suggestions ── -->
        <?php if (!empty($similaires)): ?>
        <div class="detail-tab-panel" id="tab-suggestions" hidden>
            <div class="suggestions-section">
                <div class="grid-cards">
                    <?php foreach ($similaires as $simData): ?>
                        <?= render_card($simData['item'], $type, [
                            'match_genres' => $simData['match_genres'],
                        ]) ?>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
        <?php endif; ?>

        <!-- ── Panel : Casting ── -->
        <div class="detail-tab-panel" id="tab-cast" hidden>
            <?= render_cast_grid($castData, 'Aucune donnée disponible. Relancez fetch_metadata.py avec --force pour enrichir les métadonnées.') ?>
        </div>

    </div><!-- /.detail-body -->

    <?php include "../components/footer.php"; ?>

    <script src="../js/hoshimi_accent.js"></script>
    <script src="../js/hoshimi_storage.js"></script>
    <script src="../js/hoshimi_tabs.js"></script>
    <script>
        if (typeof setAccentFromAnime === 'function') {
            const animeData = <?= json_encode($anime['metadata'] ?? []) ?>;
            if (document.readyState === 'loading') {
                document.addEventListener('DOMContentLoaded', () => setAccentFromAnime(animeData));
            } else {
                setAccentFromAnime(animeData);
            }
        }

        HoshimiStorage.initFavoriteButton(
            document.querySelector('[data-fav-btn]'),
            "<?= htmlspecialchars($slug) ?>", {
                title:     "<?= htmlspecialchars(addslashes($anime['title'])) ?>",
                cover_url: "<?= htmlspecialchars($anime['cover_url'] ?? '') ?>",
                type:      "<?= $type === 'serie' ? 'serie' : 'anime' ?>",
            }
        );

        // Toutes les clés d'épisodes pour "Reprendre"
        const allAnimeEps = <?= json_encode(array_merge(...array_map(
            fn($s) => array_map(fn($ep) => [
                'key'    => md5($ep['file_path']),
                'season' => $s['number'],
                'number' => $ep['number'],
            ], $s['episodes']),
            $anime['seasons']
        ))) ?>;

        const seasonEps = <?= json_encode(array_map(
            fn($ep) => ['key' => md5($ep['file_path']), 'number' => $ep['number']],
            $seasonData['episodes'] ?? []
        )) ?>;

        // ── Season select dropdown ────────────────────────────────────────────
        const seasonBtn  = document.getElementById('seasonSelectBtn');
        const seasonWrap = seasonBtn?.closest('.season-select-wrap');
        if (seasonBtn && seasonWrap) {
            seasonBtn.addEventListener('click', e => {
                e.stopPropagation();
                const open = seasonWrap.classList.toggle('is-open');
                seasonBtn.setAttribute('aria-expanded', open);
            });
            seasonWrap.querySelectorAll('.season-select-item').forEach(item => {
                item.addEventListener('click', () => {
                    window.location.href = item.dataset.href;
                });
            });
            document.addEventListener('click', () => {
                seasonWrap.classList.remove('is-open');
                seasonBtn.setAttribute('aria-expanded', 'false');
            });
        }

        // Tab switching handled by hoshimi_tabs.js

        document.addEventListener('DOMContentLoaded', () => {
            const allProgress = HoshimiStorage.Progress.getAllProgress();

            // Barres de progression + badge vu
            document.querySelectorAll('[data-ep-key]').forEach(el => {
                const p = allProgress[el.dataset.epKey];
                if (!p || p.duration <= 0) return;
                const pct = (p.position / p.duration) * 100;
                const isFinished = p.completed || pct >= 92;
                const bar = el.querySelector('.episode-card__progress-fill');
                if (bar) bar.style.width = (isFinished ? 100 : pct) + '%';
                if (isFinished) el.classList.add('is-completed');
            });

            // Bouton "Reprendre"
            const watchBtn = document.getElementById('watch-btn');
            if (watchBtn) {
                let resumeEp = null;
                allAnimeEps.forEach(ep => {
                    const p = allProgress[ep.key];
                    if (!p || p.completed || p.position <= 10) return;
                    if (!resumeEp || p.updated_at > allProgress[resumeEp.key]?.updated_at) resumeEp = ep;
                });
                if (resumeEp) {
                    watchBtn.href = `/player/?slug=<?= urlencode($slug) ?><?= $typeParam ?>&saison=${resumeEp.season}&episode=${resumeEp.number}`;
                    watchBtn.innerHTML = '↩ Reprendre';
                }
            }

            // Bouton "Tout marquer vu"
            document.getElementById('mark-all-btn')?.addEventListener('click', () => {
                if (!confirm('Marquer tous les épisodes de cette saison comme vus ?')) return;
                seasonEps.forEach(ep => HoshimiStorage.Progress.save(ep.key, 9999, 9999, true));
                document.querySelectorAll('[data-ep-key]').forEach(card => card.classList.add('is-completed'));
            });
        });
    </script>

</body>
</html>
