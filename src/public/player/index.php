<?php

declare(strict_types=1);

require_once __DIR__ . '/../../app/Services/AnimeScanner.php';
require_once '../../app/Services/ThumbnailHelper.php';
require_once __DIR__ . '/../components/card.php';
require_once __DIR__ . '/../components/cast.php';
require_once __DIR__ . '/../components/tabs.php';

$type = $_GET['type'] ?? 'anime';

if ($type === 'serie') {
    require_once __DIR__ . '/../../app/Services/SeriesScanner.php';
    $scanner = new SeriesScanner();
} elseif ($type === 'film') {
    require_once __DIR__ . '/../../app/Services/FilmScanner.php';
    $scanner = new FilmScanner();
} else {
    $scanner = new AnimeScanner();
}

$slug          = $_GET['slug']    ?? '';
$seasonNumber  = max(1, (int) ($_GET['saison']  ?? 1));
$episodeNumber = max(1, (int) ($_GET['episode'] ?? 1));

if ($type === 'film') {
    $anime = $slug ? $scanner->getFilmBySlug($slug) : null;
    // Films : toujours saison 1, épisode 1
    $seasonNumber  = 1;
    $episodeNumber = 1;
} elseif ($type === 'serie') {
    $anime = $slug ? $scanner->getAnimeBySlug($slug) : null;
} else {
    $anime = $slug ? $scanner->getAnimeBySlug($slug) : null;
}

if (!$anime) {
    http_response_code(404);
    header('Location: /');
    exit;
}

// Saison courante
$season = null;
foreach ($anime['seasons'] as $s) {
    if ($s['number'] === $seasonNumber) {
        $season = $s;
        break;
    }
}
$season ??= $anime['seasons'][0] ?? null;

if (!$season || empty($season['episodes'])) {
    header('Location: /anime/?slug=' . urlencode($slug));
    exit;
}

// Épisode courant
$episode     = null;
$episodeIdx  = 0;
foreach ($season['episodes'] as $i => $ep) {
    if ($ep['number'] === $episodeNumber) {
        $episode    = $ep;
        $episodeIdx = $i;
        break;
    }
}
$episode ??= $season['episodes'][0];
$episodeIdx = $episodeIdx ?: 0;

// Épisode précédent / suivant au sein de la même saison
$prevEpisode = $season['episodes'][$episodeIdx - 1] ?? null;
$nextEpisode = $season['episodes'][$episodeIdx + 1] ?? null;

// ---- LOGIQUE DE SAISON SUIVANTE S'IL N'Y A PLUS D'ÉPISODE ----
$nextSeasonNumber = null;

$nextSeasonLabel = null;
if (!$nextEpisode) {
    // On cherche s'il y a une saison avec un numéro supérieur
    foreach ($anime['seasons'] as $s) {
        // Ignore la saison spéciale (999) s'il y en a une
        if ($s['number'] > $seasonNumber && $s['number'] !== 999 && !empty($s['episodes'])) {
            $nextSeasonNumber = $s['number'];
            $nextSeasonLabel  = $s['label'];
            // On récupère le premier épisode de cette saison suivante
            $nextEpisode = $s['episodes'][0];
            break; // On a trouvé la saison la plus proche, on s'arrête
        }
    }
}

// URLs de navigation (Ajustées dynamiquement avec le bon numéro de saison)
$baseUrl = '/player/?slug=' . urlencode($slug) . ($type !== 'anime' ? '&type=' . urlencode($type) : '');
$prevUrl = $prevEpisode ? $baseUrl . '&saison=' . $seasonNumber . '&episode=' . $prevEpisode['number'] : null;

if ($nextEpisode) {
    // Si c'est un changement de saison, on utilise $nextSeasonNumber, sinon la saison actuelle
    $targetSeason = $nextSeasonNumber ?? $seasonNumber;
    $nextUrl = $baseUrl . '&saison=' . $targetSeason . '&episode=' . $nextEpisode['number'];
} else {
    $nextUrl = null;
}
// -------------------------------------------------------------

// URL stream
$streamUrl = '/stream/index.php?path=' . rawurlencode($episode['file_path']);

// Clé unique pour la progression
$progressKey = md5($episode['file_path']);

// Accent couleur
$accentColor = $anime['metadata']['coverImage']['color'] ?? '#F7D622';
$synopsis = null;
if (!empty($anime['synopsis'])) {
    $clean = preg_replace('/<br\s*\/?>/i', "\n", $anime['synopsis']);
    $clean = strip_tags($clean);
    $clean = html_entity_decode($clean, ENT_QUOTES | ENT_HTML5, 'UTF-8');
    $synopsis = trim(preg_replace("/\n{3,}/", "\n\n", $clean));
}

$cachePath = $_SERVER['DOCUMENT_ROOT'] . '/images/thumbnails/';
$durations = file_exists($cachePath . 'durations.json') ? json_decode(file_get_contents($cachePath . 'durations.json'), true) : [];
$sizes     = file_exists($cachePath . 'filesizes.json') ? json_decode(file_get_contents($cachePath . 'filesizes.json'), true) : [];

// ── TMDB données d'épisodes pour la saison active ────────────────────────────
$tmdbEpisodes  = [];
$seasonMetaPath = null;
if ($season && !empty($season['episodes'])) {
    $firstEpPath  = $season['episodes'][0]['file_path'] ?? '';
    $seasonFolder = $firstEpPath ? dirname($firstEpPath) : '';
    foreach ([$seasonFolder . '/metadata.json', dirname($seasonFolder) . '/metadata.json'] as $candidate) {
        if ($candidate !== '/metadata.json' && file_exists($candidate)) {
            $seasonJson = @json_decode(@file_get_contents($candidate), true) ?: [];
            if (!empty($seasonJson['episodes']) && isset($seasonJson['season_number'])) {
                $localNum = 1;
                foreach ($seasonJson['episodes'] as $tmdbEp) {
                    $tmdbEpisodes[$localNum++] = $tmdbEp;
                }
                $seasonMetaPath = $candidate;
                break;
            }
        }
    }
}

// ── Suggestions (score multi-facteurs) ──────────────────────────────────────
$similaires = [];
$currentGenres = array_unique($anime['genres'] ?? []);
if (!empty($currentGenres)) {
    $allItems = match(true) {
        $type === 'serie' && method_exists($scanner, 'getAllSeries') => $scanner->getAllSeries(),
        $type === 'film'  && method_exists($scanner, 'getAllFilms')  => $scanner->getAllFilms(),
        method_exists($scanner, 'getAllAnimes')                      => $scanner->getAllAnimes(),
        default                                                      => [],
    };
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
    $similaires = array_slice($scored, 0, 10);
}

// Cast
$castData = array_slice($anime['metadata']['credits']['cast'] ?? [], 0, 16);
?>
<!DOCTYPE html>
<html lang="fr">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>
        <?= htmlspecialchars($episode['title']) ?> —
        <?= htmlspecialchars($anime['title']) ?> —
        Hoshimi
    </title>

    <link rel="stylesheet" href="../css/hoshimi.css">

    <link href="https://cdnjs.cloudflare.com/ajax/libs/video.js/8.10.0/video-js.min.css" rel="stylesheet">
    <script src="https://cdnjs.cloudflare.com/ajax/libs/video.js/8.10.0/video.min.js"></script>
</head>

<body>
    <div class="player-page">
        <?php include "../components/navbar.php"; ?>

        <main>

            <div class="video-wrapper">
                <video
                    id="hoshimi-player"
                    class="video-js vjs-default-skin vjs-big-play-centered"
                    controls
                    preload="auto"
                    data-progress-key="<?= htmlspecialchars($progressKey) ?>"
                    data-next-url="<?= htmlspecialchars($nextUrl ?? '') ?>"
                    data-next-title="<?= htmlspecialchars($nextEpisode['title'] ?? '') ?>"
                    data-next-number="<?= $nextEpisode['number'] ?? '' ?>">
                    <?php
                    $ext = strtolower(pathinfo($episode['file_path'], PATHINFO_EXTENSION));
                    // mkv/avi sont transcodés à la volée en MP4 par stream/index.php
                    $mimeMap  = ['mp4' => 'video/mp4', 'webm' => 'video/webm', 'm4v' => 'video/mp4'];
                    $mimeType = $mimeMap[$ext] ?? 'video/mp4';
                    ?>
                    <source src="<?= htmlspecialchars($streamUrl) ?>" type="<?= $mimeType ?>">
                    <p class="vjs-no-js">Votre navigateur ne supporte pas la lecture vidéo.</p>
                </video>
            </div>

            <div class="player-info">
                <div class="player-info__inner">

                    <?php $backTypeParam = $type !== 'anime' ? '&type=' . urlencode($type) : ''; ?>
                    <a href="/anime/?slug=<?= urlencode($slug) ?><?= $backTypeParam ?>" class="player-info__back">
                        ← Retour
                    </a>

                    <div class="player-info__titles">
                        <div class="player-info__anime">
                            <span><?= htmlspecialchars($anime['title_english'] ?? $anime['title'] ?? '') ?></span>
                            <span>•</span>
                            <span><?= htmlspecialchars($season['label']) ?></span>
                        </div>

                        <?php
                        $curTmdb     = $tmdbEpisodes[$episode['number']] ?? null;
                        $curEpName   = $curTmdb['name'] ?? null;
                        $curEpOverview = $curTmdb['overview'] ?? null;
                        ?>
                        <div class="player-info__episode">
                            <?php if ($season['number'] == 999) :
                                $cleanFilmTitle = $episode['title'];
                                $search = ['1080p', '720p', 'MULTISUB', 'MULTI', 'FILM', 'x264', 'x265'];
                                $cleanFilmTitle = str_ireplace($search, '', $cleanFilmTitle);
                                $cleanFilmTitle = trim($cleanFilmTitle, " _-");
                                echo htmlspecialchars(
                                    (empty($cleanFilmTitle) || strtolower($cleanFilmTitle) == strtolower($anime['title']))
                                        ? 'Film' : $cleanFilmTitle
                                );
                            else : ?>
                                Épisode <?= str_pad((string)$episode['number'], 2, '0', STR_PAD_LEFT) ?>
                                <?php if ($curEpName): ?>
                                    <span class="player-info__ep-sep">•</span>
                                    <span class="player-info__ep-name"><?= htmlspecialchars($curEpName) ?></span>
                                <?php endif; ?>
                            <?php endif; ?>
                        </div>

                        <?php if ($curEpOverview): ?>
                            <p class="player-info__ep-synopsis"><?= htmlspecialchars($curEpOverview) ?></p>
                        <?php endif; ?>

                        <div class="player-info__meta">
                            <?php if (!empty($episode['quality'])) : ?>
                                <span><?= strtoupper(htmlspecialchars($episode['quality'])) ?></span>
                                <span>•</span>
                            <?php endif; ?>
                            <?php if (!empty($episode['language'])) : ?>
                                <span><?= htmlspecialchars($episode['language']) ?></span>
                                <span>•</span>
                            <?php endif; ?>
                            <span><?= htmlspecialchars(strtoupper(pathinfo($episode['filename'], PATHINFO_EXTENSION))) ?></span>
                        </div>
                    </div>

                    <div class="player-info__nav">
                        <?php if (count($anime['seasons']) > 1) : ?>
                            <select class="season-select" onchange="window.location.href='/player/?slug=<?= urlencode($slug) ?><?= $backTypeParam ?>&saison='+this.value+'&episode=1'">
                                <?php foreach ($anime['seasons'] as $s) : ?>
                                    <option value="<?= $s['number'] ?>" <?= $s['number'] === $seasonNumber ? 'selected' : '' ?>>
                                        <?= htmlspecialchars($s['label']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        <?php endif; ?>

                        <?php if ($prevUrl) : ?>
                            <a href="<?= htmlspecialchars($prevUrl) ?>" class="btn btn--ghost" title="Précédent">← Préc.</a>
                        <?php endif; ?>
                        <?php if ($nextUrl) : ?>
                            <a href="<?= htmlspecialchars($nextUrl) ?>" class="btn btn--primary" title="Suivant">
                                <?= $nextSeasonNumber ? htmlspecialchars($nextSeasonLabel ?? 'Saison ' . $nextSeasonNumber) . ' →' : 'Suiv. →' ?>
                            </a>
                        <?php endif; ?>
                    </div>

                </div>
            </div>

            <!-- ── Tab bar (full-width, hors player-body) ── -->
            <?= render_tabs_bar([
                ['id' => 'episodes',    'label' => 'Épisodes',    'icon' => 'play',     'active' => $type !== 'film', 'hidden' => $type === 'film', 'count' => count($season['episodes'])],
                ['id' => 'synopsis',    'label' => 'Synopsis',    'icon' => 'book',     'active' => $type === 'film'],
                ['id' => 'cast',        'label' => 'Casting',     'icon' => 'cast',     'active' => false, 'hidden' => empty($castData)],
                ['id' => 'suggestions', 'label' => 'Suggestions', 'icon' => 'sparkles', 'active' => false, 'hidden' => empty($similaires)],
            ]) ?>

            <div class="player-body">

                <!-- ── Panel : Épisodes ── -->
                <div class="detail-tab-panel <?= $type !== 'film' ? 'is-active' : '' ?>" id="tab-episodes" <?= $type === 'film' ? 'hidden' : '' ?>>
                    <div class="episode-list">
                        <?php foreach ($season['episodes'] as $ep) :
                            $isActive   = $ep['number'] === $episode['number'];
                            $epNum      = $ep['number'];
                            $epKey      = md5($ep['file_path']);
                            $thumb      = ThumbnailHelper::getUrlFromIndex($ep['file_path']);
                            $duration   = $durations[$epKey] ?? null;
                            $filesize   = $sizes[$epKey] ?? null;
                            $lang       = strtoupper($ep['language'] ?? 'VOSTFR');

                            $tmdbEp     = $tmdbEpisodes[$epNum] ?? null;
                            $stillUrl   = $tmdbEp['still_urls']['w300'] ?? null;
                            $epTmdbName = $tmdbEp['name'] ?? null;
                            $epOverview = $tmdbEp['overview'] ?? null;

                            $imgSrc = $stillUrl ?: $thumb;

                            if ($season['number'] == 999) {
                                $epLabel = trim(str_ireplace(['1080p', '720p', 'MULTISUB', 'MULTI', 'FILM', 'x264', 'x265'], '', $ep['title']), " _-") ?: 'Film';
                            } else {
                                $epLabel = 'E' . str_pad((string)$epNum, 2, '0', STR_PAD_LEFT);
                                if ($epTmdbName) $epLabel .= ' — ' . $epTmdbName;
                                elseif ($ep['title'] && $ep['title'] !== $epLabel) $epLabel .= ' — ' . $ep['title'];
                            }
                        ?>
                            <a href="<?= $baseUrl . '&saison=' . $seasonNumber . '&episode=' . $epNum ?>"
                                class="episode-card <?= $isActive ? 'is-active' : '' ?>"
                                data-ep-key="<?= $epKey ?>">

                                <div class="episode-card__thumbnail <?= empty($imgSrc) ? 'is-empty' : '' ?>">
                                    <?php if ($imgSrc): ?>
                                        <img src="<?= htmlspecialchars($imgSrc) ?>"
                                            alt="" loading="lazy"
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
                                        <div class="episode-card__progress-fill" style="width: 0%"></div>
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
                </div>

                <!-- ── Panel : Synopsis ── -->
                <div class="detail-tab-panel <?= $type === 'film' ? 'is-active' : '' ?>" id="tab-synopsis" <?= $type !== 'film' ? 'hidden' : '' ?>>
                    <?php if (!empty($anime['genres'])): ?>
                        <div class="player-about__genres">
                            <?php foreach ($anime['genres'] as $genre): ?>
                                <a href="/animes?genre=<?= urlencode($genre) ?>" class="badge"><?= htmlspecialchars($genre) ?></a>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                    <?php if ($synopsis): ?>
                        <p class="player-about__synopsis"><?= nl2br(htmlspecialchars($synopsis)) ?></p>
                    <?php else: ?>
                        <p style="color:var(--color-text-muted)">Aucun synopsis disponible.</p>
                    <?php endif; ?>
                    <a href="/anime/?slug=<?= urlencode($slug) ?><?= $backTypeParam ?>" class="btn btn--ghost" style="margin-top:20px; display:inline-flex;">Page complète →</a>
                </div>

                <!-- ── Panel : Casting ── -->
                <?php if (!empty($castData)): ?>
                    <div class="detail-tab-panel" id="tab-cast" hidden>
                        <?= render_cast_grid($castData) ?>
                    </div>
                <?php endif; ?>

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

            </div>


        </main>

    </div>

    <?php if ($nextEpisode) : ?>
        <div class="next-toast" id="next-toast">
            <div class="next-toast__label">
                <?= $nextSeasonNumber ? htmlspecialchars($nextSeasonLabel ?? 'Saison ' . $nextSeasonNumber) . ' dans ' : 'Épisode suivant dans ' ?><span id="next-countdown">10</span>s
            </div>
            <div class="next-toast__title">
                <?= $nextSeasonNumber ? htmlspecialchars($nextSeasonLabel ?? 'Saison ' . $nextSeasonNumber) . ' — ' : '' ?>
                Ép. <?= str_pad((string)$nextEpisode['number'], 2, '0', STR_PAD_LEFT) ?>
                — <?= htmlspecialchars($nextEpisode['title']) ?>
            </div>
            <div class="next-toast__countdown">
                <div class="next-toast__countdown-bar" id="next-bar"></div>
            </div>
            <div class="next-toast__actions">
                <a href="<?= htmlspecialchars($nextUrl) ?>" class="btn btn--primary" style="flex:1; justify-content:center;">
                    Regarder maintenant
                </a>
                <button class="btn btn--ghost" onclick="cancelNext()">Annuler</button>
            </div>
        </div>
    <?php endif; ?>

    <script>
        window.HOSHIMI_PLAYER = {
            progressKey: <?= json_encode($progressKey) ?>,
            isFilm:      <?= $type === 'film' ? 'true' : 'false' ?>,
        };
        window.HOSHIMI_NEXT_URL    = <?= json_encode($nextUrl) ?>;
        window.HOSHIMI_ACCENT_DATA = <?= json_encode($anime['metadata'] ?? []) ?>;
    </script>
    <script src="../js/hoshimi_accent.js"></script>
    <script src="../js/hoshimi_storage.js"></script>
    <script src="../js/hoshimi_player.js"></script>
    <script src="../js/hoshimi_next.js"></script>
    <script src="../js/hoshimi_tabs.js"></script>
    <script>
        HoshimiStorage.initFavoriteButton?.(
            document.querySelector('[data-fav-btn]'),
            <?= json_encode($anime['slug']) ?>, {
                title:     <?= json_encode($anime['title']) ?>,
                cover_url: <?= json_encode($anime['cover_url'] ?? '') ?>,
                type:      <?= json_encode($type) ?>,
            }
        );
    </script>

    <?php include "../components/footer.php"; ?>
</body>

</html>