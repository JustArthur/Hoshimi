<?php

declare(strict_types=1);

require_once "../app/IndexManager.php";

// Path detection first — drives conditional scanner loading
$current_path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$is_home   = ($current_path === '/' || $current_path === '/index.php' || $current_path === '');
$is_animes = ($current_path === '/animes');
$is_listes = ($current_path === '/listes');

$_debugMode = isset($_GET['debug']);
$_t0 = microtime(true);

$allAnimesRaw = IndexManager::getAll('anime');
$_tAnime = microtime(true);
$animes       = $allAnimesRaw;

// ── Filters (catalog /animes only) ───────────────────────────────────────────
$genreFilter  = trim($_GET['genre']  ?? '');
$langFilter   = trim($_GET['lang']   ?? '');
$formatFilter = trim($_GET['format'] ?? '');
$yearFilter   = trim($_GET['year']   ?? '');
$sortMode     = $_GET['sort']        ?? 'alpha';
$search       = $_GET['q']           ?? '';

if ($genreFilter  !== '') $animes = array_filter($animes, fn($a) => in_array($genreFilter, $a['genres'], true));
if ($langFilter   !== '') $animes = array_filter($animes, fn($a) => strtoupper($a['main_language'] ?? '') === strtoupper($langFilter));
if ($formatFilter !== '') $animes = array_filter($animes, fn($a) => strtoupper($a['format'] ?? '') === strtoupper($formatFilter));
if ($yearFilter   !== '') $animes = array_filter($animes, fn($a) => (string)($a['year'] ?? '') === $yearFilter);

$animes = array_values($animes);
match ($sortMode) {
    'score'    => usort($animes, fn($a, $b) => ($b['score'] ?? 0) <=> ($a['score'] ?? 0)),
    'year'     => usort($animes, fn($a, $b) => ($b['year']  ?? 0) <=> ($a['year']  ?? 0)),
    'year_asc' => usort($animes, fn($a, $b) => ($a['year']  ?? 0) <=> ($b['year']  ?? 0)),
    'z_a'      => usort($animes, fn($a, $b) => strcmp($b['title'], $a['title'])),
    default    => null,
};

$allGenres = [];
foreach ($allAnimesRaw as $a) {
    foreach ($a['genres'] as $g) $allGenres[$g] = true;
}
ksort($allGenres);
$allGenres = array_keys($allGenres);

$allYears = [];
foreach ($allAnimesRaw as $a) {
    if (!empty($a['year'])) $allYears[$a['year']] = true;
}
krsort($allYears);
$allYears = array_keys($allYears);

$allFormats = [];
foreach ($allAnimesRaw as $a) {
    if (!empty($a['format'])) $allFormats[$a['format']] = true;
}
ksort($allFormats);
$allFormats = array_keys($allFormats);

// Pagination (catalog /animes only)
$perPage    = 18;
$totalItems = count($animes);
$page       = max(1, (int)($_GET['page'] ?? 1));
$totalPages = max(1, (int)ceil($totalItems / $perPage));
$page       = min($page, $totalPages);
$animesPage = array_slice($animes, ($page - 1) * $perPage, $perPage);

function formatRuntime(?int $mins): string
{
    if (!$mins) return 'Film';
    if ($mins < 60) return "{$mins}m";
    $h = (int)floor($mins / 60);
    $m = $mins % 60;
    return $m > 0 ? "{$h}h {$m}m" : "{$h}h";
}

function langBadgeHtml(?string $lang): string
{
    if (!$lang) return '';
    $map = [
        'VOSTFR' => 'vostfr',
        'VF'     => 'vf',
        'MULTI'  => 'multi',
        'VO'     => 'vo',
    ];
    $key = strtoupper(trim($lang));
    $cls = $map[$key] ?? null;
    if ($cls) return '<span class="card__lang card__lang--' . $cls . '">' . $key . '</span>';
    return '<span class="card__lang">' . htmlspecialchars($key) . '</span>';
}

function paginationUrl(int $p): string
{
    $params = array_filter([
        'genre'  => $_GET['genre']  ?? '',
        'lang'   => $_GET['lang']   ?? '',
        'format' => $_GET['format'] ?? '',
        'year'   => $_GET['year']   ?? '',
        'sort'   => $_GET['sort']   ?? '',
        'q'      => $_GET['q']      ?? '',
        'page'   => $p > 1 ? (string)$p : '',
    ]);
    return '/animes?' . http_build_query($params);
}

// ── Home-only: load series, films, thumbnails ─────────────────────────────────
$latestAnimes = $latestSeries = $latestFilms = [];
$featuredItem = null;
$heroItems    = [];
$thumbIndex   = [];
$allSeriesRaw = $allFilmsRaw = [];

if ($is_home) {
    $allSeriesRaw = IndexManager::getAll('serie');
    $_tSerie = microtime(true);
    $allFilmsRaw  = IndexManager::getAll('film');
    $_tFilm = microtime(true);

    // Tri par updated_at (mtime dossier — se met à jour quand on ajoute un fichier vidéo)
    $sortByAdded = function (array $items): array {
        $items = array_values(array_filter($items, fn($i) => ($i['episodes_local'] ?? 0) > 0));
        usort($items, fn($a, $b) => strcmp($b['last_episode_at'] ?? '', $a['last_episode_at'] ?? ''));
        return $items;
    };

    $latestAnimes = array_slice($sortByAdded($allAnimesRaw), 0, 10);
    $latestSeries = array_slice($sortByAdded($allSeriesRaw), 0, 10);
    $latestFilms  = array_slice($sortByAdded($allFilmsRaw),  0, 10);

    $thumbDir   = ($_SERVER['DOCUMENT_ROOT'] ?? '') . '/images/thumbnails/';
    $thumbIndex = file_exists($thumbDir . 'index.json')
        ? (json_decode(file_get_contents($thumbDir . 'index.json'), true) ?: [])
        : [];

    // ── Hero spotlight — 5 items aléatoires pour le carousel ────────────────
    $heroPool = [];
    foreach ($allAnimesRaw as $a) {
        if (($a['episodes_local'] ?? 0) > 0 && (!empty($a['cover_url']) || !empty($a['banner_url']))) {
            $heroPool[] = ['type' => 'anime', 'item' => $a, 'url' => '/content/?type=anime&slug=' . urlencode($a['slug'])];
        }
    }
    foreach ($allSeriesRaw as $s) {
        if (($s['episodes_local'] ?? 0) > 0 && (!empty($s['cover_url']) || !empty($s['banner_url']))) {
            $heroPool[] = ['type' => 'serie', 'item' => $s, 'url' => '/content/?type=serie&slug=' . urlencode($s['slug'])];
        }
    }
    foreach ($allFilmsRaw as $f) {
        if (($f['episodes_local'] ?? 0) > 0 && (!empty($f['cover_url']) || !empty($f['banner_url']))) {
            $heroPool[] = ['type' => 'film', 'item' => $f, 'url' => '/content/?type=film&slug=' . urlencode($f['slug'])];
        }
    }
    shuffle($heroPool);
    $heroItems    = array_slice($heroPool, 0, 5);
    $featuredItem = $heroItems[0] ?? null;
}

require_once __DIR__ . '/components/card.php';
require_once __DIR__ . '/components/pagination.php';

// Episodes et shows data chargés côté JS via /api/shows-index.php
$episodesForJs = [];
$allShowsData  = [];

// ── Hero helpers ─────────────────────────────────────────────────────────────

function heroLogoUrl(array $item, string $type): ?string
{
    return $item['logo_url'] ?? null;
}

?>
<!DOCTYPE html>
<html lang="fr">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $is_home ? 'Hoshimi' : ($is_animes ? 'Animes — Hoshimi' : 'Listes — Hoshimi') ?></title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link rel="stylesheet" href="/css/hoshimi.css">
    <script src="/js/storage.js"></script>
</head>

<body>
    <?php include __DIR__ . "/components/navbar.php"; ?>

    <main>

        <?php if ($is_home && !empty($heroItems)) : ?>
            <div class="home-hero-slider" id="hero-slider">
                <?php foreach ($heroItems as $idx => $featuredItem) :
                    $fi_item    = $featuredItem['item'];
                    $fi_type    = $featuredItem['type'];
                    $fi_url     = $featuredItem['url'];
                    $bgImg      = $fi_item['banner_url'] ?? $fi_item['cover_url'] ?? '';
                    $hasBanner  = !empty($fi_item['banner_url']);
                    $fi_title   = ($fi_type !== 'film')
                        ? ($fi_item['title_english'] ?: $fi_item['title'])
                        : $fi_item['title'];
                    $rawScore   = $fi_item['score'] ?? 0;
                    $fi_score   = $rawScore > 0 ? number_format((float)$rawScore, 1) : null;
                    $fi_genres  = array_slice($fi_item['genres'] ?? [], 0, 3);
                    $fi_synopsis = !empty($fi_item['synopsis'])
                        ? mb_substr(strip_tags((string)$fi_item['synopsis']), 0, 220)
                        : null;
                    $typeLabel  = match ($fi_type) {
                        'anime' => lc('tv-2', 10) . ' Anime',
                        'serie' => lc('tv', 10)   . ' Série',
                        'film'  => lc('clapperboard', 10) . ' Film',
                        default => ''
                    };
                    $fi_slug    = $fi_item['slug'];
                    if ($fi_type === 'film') {
                        $fi_play = '/player/?type=film&slug=' . urlencode($fi_slug);
                    } else {
                        $s1num   = $fi_item['seasons'][0]['number'] ?? 1;
                        $e1num   = $fi_item['seasons'][0]['episodes'][0]['number'] ?? 1;
                        $fi_play = '/player/?slug=' . urlencode($fi_slug) . '&season=' . $s1num . '&episode=' . $e1num . '&type=' . $fi_type;
                    }
                    $heroLogo = heroLogoUrl($fi_item, $fi_type);
                ?>
                    <div class="home-hero<?= $idx === 0 ? ' is-active' : '' ?>">
                        <div class="home-hero__backdrop<?= $hasBanner ? '' : ' home-hero__backdrop--portrait' ?>"
                            style="background-image: url('<?= htmlspecialchars($bgImg) ?>')"></div>
                        <div class="home-hero__gradient"></div>
                        <div class="home-hero__container">
                            <div class="home-hero__content">
                                <div class="home-hero__eyebrow">
                                    <span class="home-hero__type-badge home-hero__type-badge--<?= $fi_type ?>"><?= $typeLabel ?></span>
                                    <?php if ($fi_item['year'] ?? null) : ?>
                                        <span class="home-hero__year"><?= $fi_item['year'] ?></span>
                                    <?php endif; ?>
                                </div>
                                <?php if ($heroLogo) : ?>
                                    <img class="home-hero__logo"
                                        src="<?= htmlspecialchars($heroLogo) ?>"
                                        alt="<?= htmlspecialchars($fi_title) ?>">
                                <?php else : ?>
                                    <h2 class="home-hero__title"><?= htmlspecialchars($fi_title) ?></h2>
                                <?php endif; ?>
                                <?php if ($fi_score || !empty($fi_genres)) : ?>
                                    <div class="home-hero__meta">
                                        <?php if ($fi_score) : ?><span class="home-hero__score"><?= lc('star', 13) ?> <?= $fi_score ?></span><?php endif; ?>
                                        <?php foreach ($fi_genres as $i => $g) : ?>
                                            <span class="home-hero__genre"><?= htmlspecialchars($g) ?></span>
                                        <?php endforeach; ?>
                                    </div>
                                <?php endif; ?>
                                <?php if ($fi_synopsis) : ?>
                                    <p class="home-hero__synopsis"><?= htmlspecialchars($fi_synopsis) ?>…</p>
                                <?php endif; ?>
                                <div class="home-hero__actions">
                                    <a href="<?= htmlspecialchars($fi_play) ?>" class="btn btn--primary">
                                        <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="currentColor" stroke="none" aria-hidden="true">
                                            <path d="M5 5a2 2 0 0 1 3.008-1.728l11.997 6.998a2 2 0 0 1 .003 3.458l-12 7A2 2 0 0 1 5 19z" />
                                        </svg>
                                        Regarder
                                    </a>
                                    <a href="<?= htmlspecialchars($fi_url) ?>" class="btn btn--ghost">
                                        <svg xmlns="http://www.w3.org/2000/svg" width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-info" aria-hidden="true">
                                            <circle cx="12" cy="12" r="10"></circle>
                                            <path d="M12 16v-4"></path>
                                            <path d="M12 8h.01"></path>
                                        </svg>
                                        Voir la fiche
                                    </a>
                                </div>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>

        <div class="container" style="padding-top: 40px; padding-bottom: 60px;">

            <?php if ($is_animes) : ?>
                <div class="lang-filters">
                    <a href="/animes?lang=VF<?= $genreFilter ? '&genre=' . urlencode($genreFilter) : '' ?>"
                        class="badge badge--vf <?= $langFilter === 'VF' ? 'badge--active' : '' ?>">
                        Version Française (VF)
                    </a>
                    <a href="/animes?lang=VOSTFR<?= $genreFilter ? '&genre=' . urlencode($genreFilter) : '' ?>"
                        class="badge badge--vostfr <?= $langFilter === 'VOSTFR' ? 'badge--active' : '' ?>">
                        VOSTFR
                    </a>
                    <?php if ($langFilter !== '') : ?>
                        <a href="/animes<?= $genreFilter ? '?genre=' . urlencode($genreFilter) : '' ?>" class="badge" style="opacity: 0.6; color: #c0392b; border: 1px solid #c0392b;"><?= lc('x', 11) ?> Retirer la langue</a>
                    <?php endif; ?>
                </div>

                <?php if (!empty($allGenres)) : ?>
                    <div class="genre-filters">
                        <a href="/animes<?= $langFilter ? '?lang=' . $langFilter : '' ?>"
                            class="badge <?= $genreFilter === '' ? 'badge--active' : '' ?>">Tous les genres</a>
                        <?php foreach ($allGenres as $genre) : ?>
                            <a href="/animes?genre=<?= urlencode($genre) ?><?= $langFilter ? '&lang=' . urlencode($langFilter) : '' ?>"
                                class="badge <?= $genreFilter === $genre ? 'badge--active' : '' ?>">
                                <?= htmlspecialchars($genre) ?>
                            </a>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            <?php endif; ?>

            <?php if ($is_home) : ?>

                <div id="resume-section" style="display:none; margin-bottom:48px;">
                    <div class="section-header">
                        <div class="section-header__bar"></div>
                        <div class="section-header__inner">
                            <span class="section-header__type">Historique</span>
                            <h2 class="section-header__title">Reprendre la lecture</h2>
                        </div>
                        <div class="section-header__line"></div>
                        <a href="/historique/" class="section-header__link">Historique <?= lc('arrow-right', 12) ?></a>
                    </div>
                    <div class="resume-row" id="resume-list"></div>
                </div>

                <section id="because-section" style="display:none; margin-bottom:48px;">
                    <div class="section-header">
                        <div class="section-header__bar"></div>
                        <div class="section-header__inner">
                            <span class="section-header__type">Pour toi</span>
                            <h2 class="section-header__title" id="because-title">Parce que tu as regardé…</h2>
                        </div>
                        <div class="section-header__line"></div>
                    </div>
                    <div class="row-slider">
                        <button type="button" class="row-slider__btn row-slider__btn--prev" aria-label="Précédent" hidden><?= lc('chevron-left', 20) ?></button>
                        <div class="grid-cards grid-cards--row" id="because-grid"></div>
                        <button type="button" class="row-slider__btn row-slider__btn--next" aria-label="Suivant" hidden><?= lc('chevron-right', 20) ?></button>
                    </div>
                </section>

                <?php if (!empty($latestAnimes)) : ?>
                    <?php $sectionType = 'Anime';
                    $sectionTitle = 'Derniers ajouts';
                    $sectionHref = '/catalog/?type=anime';
                    $sectionItemType = 'anime';
                    $sectionItems = $latestAnimes;
                    $sectionDataAttrKey = 'anime-slug';
                    include __DIR__ . '/components/media-section.php'; ?>
                <?php endif; ?>

                <?php if (!empty($latestSeries)) : ?>
                    <?php $sectionType = 'Série';
                    $sectionTitle = 'Derniers ajouts';
                    $sectionHref = '/catalog/?type=serie';
                    $sectionItemType = 'serie';
                    $sectionItems = $latestSeries;
                    include __DIR__ . '/components/media-section.php'; ?>
                <?php endif; ?>

                <?php if (!empty($latestFilms)) : ?>
                    <?php $sectionType = 'Film';
                    $sectionTitle = 'Derniers ajouts';
                    $sectionHref = '/catalog/?type=film';
                    $sectionItemType = 'film';
                    $sectionItems = $latestFilms;
                    include __DIR__ . '/components/media-section.php'; ?>
                <?php endif; ?>

                <?php if (empty($latestAnimes) && empty($latestSeries) && empty($latestFilms)) : ?>
                    <div class="flex-center" style="min-height:300px; flex-direction:column; gap:16px;">
                        <?= lc('search-x', 48) ?>
                        <p class="text-muted">Aucun contenu trouvé. Vérifiez que les dossiers média sont bien montés.</p>
                    </div>
                <?php endif; ?>

            <?php endif; // $is_home 
            ?>

            <?php if ($is_listes) : ?>
                <div id="favorites-section" style="display:none; margin-bottom:48px;">
                    <div class="section-header section-header--simple">
                        <div class="section-header__bar"></div>
                        <div class="section-header__inner">
                            <h2 class="section-header__title">Favoris</h2>
                        </div>
                        <div class="section-header__line"></div>
                    </div>
                    <div class="grid-cards" id="favorites-grid"></div>
                    <div id="favorites-empty" style="display:none;">
                        <div class="flex-center" style="min-height:200px; flex-direction:column; gap:12px;">
                            <?= lc('heart', 40, '', '') ?>
                            <p class="text-muted" style="font-size:.9rem;">Aucun favori pour l'instant.</p>
                            <a href="/animes" class="btn btn--ghost" style="margin-top:4px;">Parcourir le catalogue</a>
                        </div>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </main>

    <?php include __DIR__ . "/components/footer.php"; ?>

    <?php if ($_debugMode): ?>
        <?php
        $_tEnd  = microtime(true);
        $_meta  = IndexManager::getMeta();
        $_tTotal = round(($_tEnd - $_t0) * 1000, 1);
        $_tA    = round(($_tAnime - $_t0) * 1000, 1);
        $_tS    = $is_home ? round((($_tSerie ?? $_tAnime) - $_tAnime) * 1000, 1) : null;
        $_tF    = $is_home ? round((($_tFilm  ?? $_tAnime) - ($_tSerie ?? $_tAnime)) * 1000, 1) : null;
        $ttl    = null; // pas de TTL — regen par watcher/polling
        ?>
        <div id="im-debug" style="position:fixed;bottom:16px;right:16px;z-index:9999;background:rgba(0,0,0,.88);color:#e2e8f0;font:12px/1.5 monospace;padding:14px 18px;border-radius:8px;border:1px solid rgba(255,255,255,.12);max-width:340px;backdrop-filter:blur(8px);box-shadow:0 4px 24px rgba(0,0,0,.5)">
            <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:10px">
                <strong style="font-size:13px;color:#fff">IndexManager debug</strong>
                <button onclick="this.closest('#im-debug').remove()" style="background:none;border:none;color:#94a3b8;cursor:pointer;font-size:16px;line-height:1;padding:0">×</button>
            </div>
            <table style="width:100%;border-collapse:collapse">
                <tr style="color:#94a3b8;font-size:11px">
                    <th style="text-align:left;padding:2px 6px 6px 0">type</th>
                    <th style="text-align:right;padding:2px 0 6px">items</th>
                    <th style="text-align:right;padding:2px 0 6px 8px">âge</th>
                    <th style="text-align:right;padding:2px 0 6px 8px">source</th>
                </tr>
                <?php foreach ($_meta as $type => $m): ?>
                    <?php
                    $age    = $m['exists'] ? (time() - $m['generated_ts']) : null;
                    $stale  = $m['stale'] ?? true;
                    $color  = !$m['exists'] ? '#f87171' : '#4ade80';
                    $ageStr = $age !== null ? ($age < 60 ? $age . 's' : round($age / 60, 1) . 'm') : '—';
                    ?>
                    <tr>
                        <td style="padding:3px 6px 3px 0;color:<?= $color ?>"><?= $type ?></td>
                        <td style="text-align:right;padding:3px 0"><?= $m['exists'] ? $m['count'] : '—' ?></td>
                        <td style="text-align:right;padding:3px 0 3px 8px;color:#94a3b8"><?= $ageStr ?></td>
                        <td style="text-align:right;padding:3px 0 3px 8px;color:#64748b"><?= $m['exists'] ? round($m['size_kb'], 1) . 'kb' : 'scanner' ?></td>
                    </tr>
                <?php endforeach; ?>
            </table>
            <div style="border-top:1px solid rgba(255,255,255,.08);margin-top:8px;padding-top:8px;color:#94a3b8;font-size:11px">
                <div>⏱ total requête : <span style="color:#fff"><?= $_tTotal ?>ms</span></div>
                <div>anime: <span style="color:#fff"><?= $_tA ?>ms</span><?php if ($is_home): ?> · serie: <span style="color:#fff"><?= $_tS ?>ms</span> · film: <span style="color:#fff"><?= $_tF ?>ms</span><?php endif; ?></div>
                <div style="margin-top:6px;color:#475569">TTL: ∞ (watcher) · <a href="?" style="color:#64748b">sans debug</a> · <a href="/admin/api/regen-index.php?type=all" target="_blank" style="color:#64748b">regen</a></div>
            </div>
        </div>
    <?php endif; ?>

    <script src="/js/navbar.js"></script>
    <script src="/js/guard.js"></script>
    <script>
        const allEpisodesData = <?= json_encode($episodesForJs) ?>;
        const THUMB_INDEX = <?= json_encode($thumbIndex) ?>;
    </script>

    <script src="/js/favorites.js"></script>
    <?php if ($is_home) : ?>
        <script>
            // Stale-while-revalidate : affiche le cache immédiatement, rafraîchit en arrière-plan
            (function() {
                const CACHE_KEY = 'hoshimi_shows_index';
                const CACHE_TTL = 5 * 60 * 1000; // 5 min

                function loadResume(data) {
                    if (window.__resumeLoaded) return;
                    window.__resumeLoaded = true;
                    window.allShowsData = data;
                    const s = document.createElement('script');
                    s.src = '/js/resume.js';
                    document.body.appendChild(s);
                    const b = document.createElement('script');
                    b.src = '/js/because-watched.js';
                    document.body.appendChild(b);
                }

                let cached = null;
                try {
                    const raw = localStorage.getItem(CACHE_KEY);
                    if (raw) {
                        const parsed = JSON.parse(raw);
                        if (Date.now() - parsed.ts < CACHE_TTL) cached = parsed.data;
                    }
                } catch {}

                if (cached) loadResume(cached);

                const _progKeys = Object.keys((() => {
                    try {
                        return JSON.parse(localStorage.getItem('hoshimi_progress') ?? '{}');
                    } catch {
                        return {};
                    }
                })());
                fetch('/api/shows-index.php', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json'
                        },
                        body: JSON.stringify({
                            file_keys: _progKeys
                        }),
                    })
                    .then(r => r.json())
                    .then(data => {
                        try {
                            localStorage.setItem(CACHE_KEY, JSON.stringify({
                                ts: Date.now(),
                                data
                            }));
                        } catch {}
                        if (!cached) loadResume(data);
                    })
                    .catch(() => {});
            })();
        </script>
    <?php else : ?>
        <script src="/js/resume.js"></script>
    <?php endif; ?>
    <script src="/js/parallax.js"></script>
    <?php if ($is_home) : ?><script src="/js/row-slider.js"></script><?php endif; ?>
    <?php if ($is_home && count($heroItems) > 1) : ?>
        <script>
            (function() {
                const slides = document.querySelectorAll('#hero-slider .home-hero');
                if (slides.length < 2) return;
                let current = 0;

                function goTo(next) {
                    // Slides
                    slides[current].classList.remove('is-active');
                    slides[next].classList.add('is-active');

                    // Content animation restart
                    const content = slides[next].querySelector('.home-hero__content');
                    if (content) {
                        content.classList.remove('hero-content--in');
                        void content.offsetWidth;
                        content.classList.add('hero-content--in');
                    }

                    current = next;
                }

                setInterval(() => goTo((current + 1) % slides.length), 7000);
            })();
        </script>
    <?php endif; ?>
    <script>
        // ---- Barres de progression sur les cartes catalogue ----
        HoshimiStorage.updateCardProgressBars(allEpisodesData);

        <?php if (!$is_home) : ?>
            // ---- Recherche instantanée ----
            const searchInput = document.querySelector('.search-bar input[name="q"]');
            if (searchInput) {
                let searchTimer;
                searchInput.addEventListener('input', () => {
                    clearTimeout(searchTimer);
                    searchTimer = setTimeout(() => {
                        const q = searchInput.value.toLowerCase().trim();
                        document.querySelectorAll('#main-animes-section [data-search]').forEach(card => {
                            card.style.display = (!q || card.dataset.search.includes(q)) ? '' : 'none';
                        });
                    }, 120);
                });
                searchInput.closest('form')?.addEventListener('submit', e => {
                    if (document.querySelectorAll('#main-animes-section [data-search]').length > 0) {
                        e.preventDefault();
                        searchInput.dispatchEvent(new Event('input'));
                    }
                });
            }

        <?php endif; ?>
    </script>

</body>

</html>