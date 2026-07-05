<?php

declare(strict_types=1);

require_once "../app/Services/AnimeScanner.php";

// Path detection first — drives conditional scanner loading
$current_path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$is_home   = ($current_path === '/' || $current_path === '/index.php' || $current_path === '');
$is_animes = ($current_path === '/animes');
$is_listes = ($current_path === '/listes');

$scanner      = new AnimeScanner();
$allAnimesRaw = $scanner->getAllAnimes();
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
foreach ($allAnimesRaw as $a) { foreach ($a['genres'] as $g) $allGenres[$g] = true; }
ksort($allGenres); $allGenres = array_keys($allGenres);

$allYears = [];
foreach ($allAnimesRaw as $a) { if (!empty($a['year'])) $allYears[$a['year']] = true; }
krsort($allYears); $allYears = array_keys($allYears);

$allFormats = [];
foreach ($allAnimesRaw as $a) { if (!empty($a['format'])) $allFormats[$a['format']] = true; }
ksort($allFormats); $allFormats = array_keys($allFormats);

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
$thumbIndex   = [];
$allSeriesRaw = $allFilmsRaw = [];

if ($is_home) {
    require_once "../app/Services/SeriesScanner.php";
    require_once "../app/Services/FilmScanner.php";

    $allSeriesRaw = (new SeriesScanner())->getAllSeries();
    $allFilmsRaw  = (new FilmScanner())->getAllFilms();

    // Sort animes/series by most recently added episode file; skip shows with no episodes
    $sortByLatestEpisodeMtime = function (array $items): array {
        foreach ($items as &$item) {
            $latest = 0;
            foreach ($item['seasons'] as $season) {
                foreach ($season['episodes'] as $ep) {
                    if (file_exists($ep['file_path'])) {
                        $t = filemtime($ep['file_path']);
                        if ($t > $latest) $latest = $t;
                    }
                }
            }
            $item['_mtime'] = $latest;
        }
        unset($item);
        $items = array_values(array_filter($items, fn($i) => $i['_mtime'] > 0));
        usort($items, fn($a, $b) => $b['_mtime'] <=> $a['_mtime']);
        return $items;
    };

    // Films: sort by file mtime (single episode = the film file)
    $sortFilmsByMtime = function (array $items): array {
        foreach ($items as &$item) {
            $ep = $item['seasons'][0]['episodes'][0] ?? null;
            $item['_mtime'] = ($ep && file_exists($ep['file_path'])) ? filemtime($ep['file_path']) : 0;
        }
        unset($item);
        $items = array_values(array_filter($items, fn($i) => $i['_mtime'] > 0));
        usort($items, fn($a, $b) => $b['_mtime'] <=> $a['_mtime']);
        return $items;
    };

    $latestAnimes = array_slice($sortByLatestEpisodeMtime($allAnimesRaw), 0, 5);
    $latestSeries = array_slice($sortByLatestEpisodeMtime($allSeriesRaw), 0, 5);
    $latestFilms  = array_slice($sortFilmsByMtime($allFilmsRaw),  0, 5);

    $thumbDir   = ($_SERVER['DOCUMENT_ROOT'] ?? '') . '/images/thumbnails/';
    $thumbIndex = file_exists($thumbDir . 'index.json')
        ? (json_decode(file_get_contents($thumbDir . 'index.json'), true) ?: [])
        : [];

    // ── Hero spotlight — rotation aléatoire (DEBUG: chaque refresh) ──────────
    $heroPool = [];
    foreach ($allAnimesRaw as $a) {
        if (!empty($a['cover_url']) || !empty($a['banner_url'])) {
            $heroPool[] = ['type' => 'anime', 'item' => $a, 'url' => '/anime/?slug=' . urlencode($a['slug'])];
        }
    }
    foreach ($allSeriesRaw as $s) {
        if (!empty($s['cover_url']) || !empty($s['banner_url'])) {
            $heroPool[] = ['type' => 'serie', 'item' => $s, 'url' => '/anime/?slug=' . urlencode($s['slug']) . '&type=serie'];
        }
    }
    foreach ($allFilmsRaw as $f) {
        if (!empty($f['cover_url']) || !empty($f['banner_url'])) {
            $heroPool[] = ['type' => 'film', 'item' => $f, 'url' => '/film/?slug=' . urlencode($f['slug'])];
        }
    }
    $featuredItem = !empty($heroPool)
        ? $heroPool[array_rand($heroPool)]
        : null;
}

require_once __DIR__ . '/components/card.php';
require_once __DIR__ . '/components/pagination.php';

// ── allEpisodesData (animes, backward-compat for updateCardProgressBars) ──────
$episodesForJs = [];
foreach ($allAnimesRaw as $a) {
    $eps = [];
    foreach ($a['seasons'] as $season) {
        foreach ($season['episodes'] as $ep) {
            $eps[] = ['file_key' => md5($ep['file_path']), 'number' => $ep['number'], 'season' => $season['number'], 'title' => $ep['title']];
        }
    }
    $episodesForJs[$a['slug']] = $eps;
}

// ── allShowsData (home only — full resume data for all three types) ───────────
$allShowsData = [];
if ($is_home) {
    $buildEps = fn(array $item): array => array_merge(
        [],
        ...array_map(
            fn($season) => array_map(
                fn($ep) => ['file_key' => md5($ep['file_path']), 'number' => $ep['number'], 'season' => $season['number']],
                $season['episodes']
            ),
            $item['seasons']
        )
    );

    foreach ($allAnimesRaw as $a) {
        $allShowsData['anime:' . $a['slug']] = [
            'slug'       => $a['slug'], 'type' => 'anime',
            'show_title' => $a['title_english'] ?: $a['title'],
            'cover_url'  => $a['cover_url'] ?? '',
            'banner_url' => $a['banner_url'] ?? '',
            'episodes'   => $buildEps($a),
        ];
    }
    foreach ($allSeriesRaw as $s) {
        $allShowsData['serie:' . $s['slug']] = [
            'slug'       => $s['slug'], 'type' => 'serie',
            'show_title' => $s['title_english'] ?: $s['title'],
            'cover_url'  => $s['cover_url'] ?? '',
            'banner_url' => $s['banner_url'] ?? '',
            'episodes'   => $buildEps($s),
        ];
    }
    foreach ($allFilmsRaw as $f) {
        $allShowsData['film:' . $f['slug']] = [
            'slug'       => $f['slug'], 'type' => 'film',
            'show_title' => $f['title'],
            'cover_url'  => $f['cover_url'] ?? '',
            'banner_url' => $f['banner_url'] ?? '',
            'episodes'   => $buildEps($f),
        ];
    }
}

// ── Hero helpers ─────────────────────────────────────────────────────────────

function heroColorValid(string $hex): bool
{
    if (!preg_match('/^#[0-9a-f]{6}$/i', $hex)) return false;
    $r = hexdec(substr($hex, 1, 2));
    $g = hexdec(substr($hex, 3, 2));
    $b = hexdec(substr($hex, 5, 2));
    return ($r * 299 + $g * 587 + $b * 114) / 1000 >= 72; // trop sombre = illisible
}

function heroAccentColor(array $item): ?string
{
    // 1. Couleur AniList (anime/série)
    if (!empty($item['accent_color']) && heroColorValid($item['accent_color'])) {
        return $item['accent_color'];
    }

    // 2. Extraction GD depuis la cover locale
    $filePath = null;
    if (!empty($item['cover_path'])) {
        $filePath = $item['cover_path'];
    } elseif (!empty($item['cover_url']) && str_starts_with($item['cover_url'], '/stream-image/')) {
        parse_str(parse_url($item['cover_url'], PHP_URL_QUERY) ?? '', $qp);
        $p = $qp['path'] ?? '';
        if ($p && file_exists($p)) $filePath = $p;
    }

    if ($filePath === null || !function_exists('imagecreatefromstring')) return null;

    static $colorCache = null;
    $cacheFile = sys_get_temp_dir() . '/hoshimi_colors.json';
    if ($colorCache === null) {
        $colorCache = file_exists($cacheFile)
            ? (json_decode((string)file_get_contents($cacheFile), true) ?: [])
            : [];
    }

    $key = md5($filePath);
    if (array_key_exists($key, $colorCache)) return $colorCache[$key] ?: null;

    $raw = @file_get_contents($filePath);
    $img = $raw ? @imagecreatefromstring($raw) : false;
    if (!$img) { $colorCache[$key] = ''; @file_put_contents($cacheFile, json_encode($colorCache)); return null; }

    // Réduction 8×12 sur les 2/3 supérieurs (là où la couleur est la plus représentative)
    $tiny = imagecreatetruecolor(8, 12);
    imagecopyresampled($tiny, $img, 0, 0, 0, 0, 8, 12, imagesx($img), (int)(imagesy($img) * 0.66));
    imagedestroy($img);

    $votes = [];
    for ($x = 0; $x < 8; $x++) {
        for ($y = 0; $y < 12; $y++) {
            $rgb = imagecolorat($tiny, $x, $y);
            $r = ($rgb >> 16) & 0xFF;
            $g = ($rgb >> 8)  & 0xFF;
            $b = $rgb & 0xFF;
            $brightness  = ($r * 299 + $g * 587 + $b * 114) / 1000;
            $saturation  = max($r, $g, $b) - min($r, $g, $b);
            if ($brightness < 65 || $brightness > 215 || $saturation < 35) continue;
            $hex = sprintf('#%02x%02x%02x', $r, $g, $b);
            $votes[$hex] = ($votes[$hex] ?? 0) + 1;
        }
    }
    imagedestroy($tiny);

    $result = '';
    if (!empty($votes)) { arsort($votes); $result = array_key_first($votes); }
    $colorCache[$key] = $result;
    @file_put_contents($cacheFile, json_encode($colorCache));
    return $result ?: null;
}

function heroLogoUrl(array $item, string $type): ?string
{
    $logos = $item['metadata']['images']['logos'] ?? [];
    if (empty($logos)) return null;
    // Préférer les logos en anglais
    usort($logos, fn($a, $b) => (($b['iso_639_1'] ?? '') === 'en') <=> (($a['iso_639_1'] ?? '') === 'en'));
    $logo = $logos[0] ?? null;
    if ($logo) {
        return $logo['urls']['w500']
            ?? $logo['urls']['original']
            ?? (!empty($logo['file_path']) ? 'https://image.tmdb.org/t/p/w500' . $logo['file_path'] : null);
    }
    return null;
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
</head>

<body>
    <?php include __DIR__ . "/components/navbar.php"; ?>

    <main>

        <?php if ($is_home && $featuredItem !== null) :
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
            $fi_genres   = array_slice($fi_item['genres'] ?? [], 0, 3);
            $fi_synopsis = !empty($fi_item['synopsis'])
                ? mb_substr(strip_tags((string)$fi_item['synopsis']), 0, 220)
                : null;
            $typeLabel  = match($fi_type) { 'anime' => 'Anime', 'serie' => 'Série', 'film' => 'Film', default => '' };
            $fi_slug    = $fi_item['slug'];
            if ($fi_type === 'film') {
                $fi_play = '/player/?type=film&slug=' . urlencode($fi_slug);
            } else {
                $s1num   = $fi_item['seasons'][0]['number'] ?? 1;
                $e1num   = $fi_item['seasons'][0]['episodes'][0]['number'] ?? 1;
                $fi_play = '/player/?slug=' . urlencode($fi_slug) . '&season=' . $s1num . '&episode=' . $e1num . '&type=' . $fi_type;
            }
            $heroAccent = heroAccentColor($fi_item);
            $heroLogo   = heroLogoUrl($fi_item, $fi_type);
            $heroStyle  = $heroAccent ? ' style="--hero-accent:' . htmlspecialchars($heroAccent) . '"' : '';
        ?>
        <div class="home-hero"<?= $heroStyle ?>>
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
                    <?php if ($fi_score) : ?><span class="home-hero__score">★ <?= $fi_score ?></span><?php endif; ?>
                    <?php foreach ($fi_genres as $g) : ?><span class="home-hero__genre"><?= htmlspecialchars($g) ?></span><?php endforeach; ?>
                </div>
                <?php endif; ?>
                <?php if ($fi_synopsis) : ?>
                <p class="home-hero__synopsis"><?= htmlspecialchars($fi_synopsis) ?>…</p>
                <?php endif; ?>
                <div class="home-hero__actions">
                    <a href="<?= htmlspecialchars($fi_play) ?>" class="btn btn--primary">▶ Regarder</a>
                    <a href="<?= htmlspecialchars($fi_url) ?>" class="btn btn--ghost">Détails</a>
                </div>
            </div>
            </div><!-- /.home-hero__container -->
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
                        <a href="/animes<?= $genreFilter ? '?genre=' . urlencode($genreFilter) : '' ?>" class="badge" style="opacity: 0.6; color: #c0392b; border: 1px solid #c0392b;">✕ Retirer la langue</a>
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

                <!-- ── Reprendre la lecture ──────────────────────────────────── -->
                <div id="resume-section" style="display:none; margin-bottom:48px;">
                    <div class="section-header">
                        <h2 class="section-header__title">Reprendre la lecture</h2>
                    </div>
                    <div class="resume-row" id="resume-list"></div>
                </div>

                <!-- ── 6 derniers Animes ─────────────────────────────────────── -->
                <?php if (!empty($latestAnimes)) : ?>
                <section style="margin-bottom:48px;">
                    <div class="section-header">
                        <h2 class="section-header__title">Nouveautés <span class="section-header__type">Anime</span></h2>
                        <a href="/animes" class="section-header__link">Voir tout →</a>
                    </div>
                    <div class="grid-cards">
                        <?php foreach ($latestAnimes as $a) : ?>
                            <?= render_card($a, 'anime', [
                                'show_fav'   => true,
                                'data_attrs' => ['anime-slug' => $a['slug']],
                            ]) ?>
                        <?php endforeach; ?>
                    </div>
                </section>
                <?php endif; ?>

                <!-- ── 6 dernières Séries ────────────────────────────────────── -->
                <?php if (!empty($latestSeries)) : ?>
                <section style="margin-bottom:48px;">
                    <div class="section-header">
                        <h2 class="section-header__title">Nouveautés <span class="section-header__type">Série</span></h2>
                        <a href="/series/" class="section-header__link">Voir tout →</a>
                    </div>
                    <div class="grid-cards">
                        <?php foreach ($latestSeries as $s) : ?>
                            <?= render_card($s, 'serie') ?>
                        <?php endforeach; ?>
                    </div>
                </section>
                <?php endif; ?>

                <!-- ── 6 derniers Films ──────────────────────────────────────── -->
                <?php if (!empty($latestFilms)) : ?>
                <section style="margin-bottom:48px;">
                    <div class="section-header">
                        <h2 class="section-header__title">Nouveautés <span class="section-header__type">Film</span></h2>
                        <a href="/films/" class="section-header__link">Voir tout →</a>
                    </div>
                    <div class="grid-cards">
                        <?php foreach ($latestFilms as $f) : ?>
                            <?= render_card($f, 'film') ?>
                        <?php endforeach; ?>
                    </div>
                </section>
                <?php endif; ?>

                <?php if (empty($latestAnimes) && empty($latestSeries) && empty($latestFilms)) : ?>
                    <div class="flex-center" style="min-height:300px; flex-direction:column; gap:16px;">
                        <span style="font-size:3rem;">🔭</span>
                        <p class="text-muted">Aucun contenu trouvé. Vérifiez que les dossiers média sont bien montés.</p>
                    </div>
                <?php endif; ?>

            <?php endif; // $is_home ?>

            <?php if ($is_listes) : ?>
                <div id="favorites-section" style="display:none; margin-bottom:48px;">
                    <div class="section-header">
                        <h2 class="section-header__title">Mes Favoris</h2>
                    </div>
                    <div class="grid-cards" id="favorites-grid"></div>
                    <div id="favorites-empty" style="display:none;">
                        <div class="flex-center" style="min-height:200px; flex-direction:column; gap:12px;">
                            <span style="font-size:2.5rem; opacity:.3;">♡</span>
                            <p class="text-muted" style="font-size:.9rem;">Aucun favori pour l'instant.</p>
                            <a href="/animes" class="btn btn--ghost" style="margin-top:4px;">Parcourir le catalogue</a>
                        </div>
                    </div>
                </div>
            <?php endif; ?>

            <?php if (!$is_home) : ?>
            <div id="main-animes-section" style="<?= $is_listes ? 'display: none;' : '' ?>">
                <div class="catalog-toolbar" style="margin-bottom: 24px;">
                    <div class="section-header" style="margin-bottom: 0; flex: 1;">
                        <h1 class="section-header__title" style="font-size: 1.25rem;">
                            <?= $is_animes ? 'Catalogue' : 'Mes Animes' ?>
                        </h1>
                        <span class="text-muted" style="font-size: 0.9rem;">
                            <?= count($animes) ?> titre<?= count($animes) > 1 ? 's' : '' ?>
                        </span>
                    </div>

                    <div class="catalog-toolbar__right">
                        <?php if ($is_animes) : ?>
                        <select class="filter-select" onchange="location.href=this.value" title="Format">
                            <option value="/animes?<?= http_build_query(array_filter(['genre' => $genreFilter, 'lang' => $langFilter, 'year' => $yearFilter, 'sort' => $sortMode !== 'alpha' ? $sortMode : ''])) ?>">Format</option>
                            <?php foreach ($allFormats as $fmt) : ?>
                                <option value="/animes?<?= http_build_query(array_filter(['format' => $fmt, 'genre' => $genreFilter, 'lang' => $langFilter, 'year' => $yearFilter, 'sort' => $sortMode !== 'alpha' ? $sortMode : ''])) ?>"
                                    <?= strtoupper($formatFilter) === strtoupper($fmt) ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($fmt) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>

                        <select class="filter-select" onchange="location.href=this.value" title="Année">
                            <option value="/animes?<?= http_build_query(array_filter(['genre' => $genreFilter, 'lang' => $langFilter, 'format' => $formatFilter, 'sort' => $sortMode !== 'alpha' ? $sortMode : ''])) ?>">Année</option>
                            <?php foreach ($allYears as $yr) : ?>
                                <option value="/animes?<?= http_build_query(array_filter(['year' => $yr, 'genre' => $genreFilter, 'lang' => $langFilter, 'format' => $formatFilter, 'sort' => $sortMode !== 'alpha' ? $sortMode : ''])) ?>"
                                    <?= $yearFilter === (string)$yr ? 'selected' : '' ?>>
                                    <?= $yr ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <?php endif; ?>

                        <select class="filter-select" onchange="location.href=this.value" title="Tri">
                            <?php
                            $sortBase = http_build_query(array_filter(['genre' => $genreFilter, 'lang' => $langFilter, 'format' => $formatFilter, 'year' => $yearFilter]));
                            $sorts = ['alpha' => 'A → Z', 'z_a' => 'Z → A', 'score' => 'Score', 'year' => 'Année ↓', 'year_asc' => 'Année ↑'];
                            foreach ($sorts as $val => $label) :
                            ?>
                                <option value="/animes?<?= $sortBase ?>&sort=<?= $val ?>" <?= $sortMode === $val ? 'selected' : '' ?>>
                                    <?= $label ?>
                                </option>
                            <?php endforeach; ?>
                        </select>

                    </div>
                </div>

                <?php if (empty($animes)) : ?>
                    <div class="flex-center" style="min-height: 300px; flex-direction: column; gap: 16px;">
                        <span style="font-size: 3rem;">🔭</span>
                        <p class="text-muted">
                            <?= $search ? "Aucun résultat pour « " . htmlspecialchars($search) . " »" : "Aucun anime trouvé." ?>
                        </p>
                        <a href="/animes" class="btn btn--ghost">Réinitialiser les filtres</a>
                    </div>
                <?php else : ?>
                    <div class="grid-cards">
                        <?php foreach ($animesPage as $anime) : ?>
                            <?= render_card($anime, 'anime', [
                                'show_fav'   => true,
                                'data_attrs' => [
                                    'anime-slug' => $anime['slug'],
                                    'search'     => strtolower(implode(' ', array_filter([$anime['title'], $anime['title_english'], $anime['title_romaji'], $anime['title_original']]))),
                                ],
                            ]) ?>
                        <?php endforeach; ?>
                    </div>

                    <?= render_pagination($page, $totalPages, 'paginationUrl') ?>

                <?php endif; ?>
            </div>
            <?php endif; // !$is_home ?>


        </div>
    </main>

    <?php include __DIR__ . "/components/footer.php"; ?>

    <script src="/js/hoshimi_accent.js"></script>
    <script src="/js/header.js"></script>
    <script src="/js/hoshimi_storage.js"></script>
    <script>
        const allEpisodesData = <?= json_encode($episodesForJs) ?>;
        <?php if ($is_home) : ?>
        const allShowsData = <?= json_encode($allShowsData) ?>;
        const THUMB_INDEX  = <?= json_encode($thumbIndex) ?>;
        <?php endif; ?>

        // ---- Icônes favoris sur les cartes ----
        const favs     = HoshimiStorage.Favorites.getAll();
        const favSlugs = new Set(favs.map(f => f.slug));
        document.querySelectorAll('[data-fav-icon]').forEach(icon => {
            const isFav = favSlugs.has(icon.dataset.favIcon);
            if (isFav) { icon.textContent = '♥'; icon.classList.add('is-fav'); }
            else { icon.style.display = 'none'; }
        });

    </script>

    <script src="/js/hoshimi_fav.js"></script>
    <script src="/js/hoshimi_resume.js"></script>
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
