<?php

declare(strict_types=1);

require_once __DIR__ . '/../../app/IndexManager.php';

$isAjax = isset($_GET['_ajax']);

// ── Filtre type ──────────────────────────────────────────────────────────────
$typeFilter  = trim($_GET['type'] ?? '');
$validTypes  = ['anime', 'serie', 'film'];
if (!in_array($typeFilter, $validTypes, true)) $typeFilter = '';

// ── Chargement du pool selon le type ────────────────────────────────────────
$pool = [];
$loadTypes = $typeFilter !== '' ? [$typeFilter] : $validTypes;
foreach ($loadTypes as $t) {
    foreach (IndexManager::getAll($t) as $item) {
        $pool[] = $item + ['_type' => $t];
    }
}

// ── Filtres ──────────────────────────────────────────────────────────────────
$sortMode    = $_GET['sort']  ?? 'recent';
$yearFilter  = trim($_GET['year']  ?? '');
$genreFilter = trim($_GET['genre'] ?? '');
$langFilter  = trim($_GET['lang']  ?? '');
$localFilter = array_key_exists('local', $_GET) ? trim($_GET['local']) : '1';

if ($yearFilter  !== '') $pool = array_filter($pool, fn($a) => (string)($a['year'] ?? '') === $yearFilter);
if ($genreFilter !== '') $pool = array_filter($pool, fn($a) => in_array($genreFilter, $a['genres'] ?? [], true));
if ($langFilter  !== '') $pool = array_filter($pool, fn($a) => strtoupper($a['main_language'] ?? '') === strtoupper($langFilter));
if ($localFilter === '1')   $pool = array_filter($pool, fn($a) => ($a['episodes_local'] ?? 0) > 0);
if ($localFilter === '0')   $pool = array_filter($pool, fn($a) => ($a['episodes_local'] ?? 0) === 0);
// 'all' = aucun filtre (explicitement choisi par l'utilisateur)

$searchQuery = trim($_GET['q'] ?? '');
if ($searchQuery !== '') {
    // Normalise : minuscules + supprime accents + remplace ponctuation par espace + collapse espaces
    $normalize = function(string $s): string {
        $s = mb_strtolower($s, 'UTF-8');
        $s = strtr($s, [
            'à'=>'a','â'=>'a','ä'=>'a','á'=>'a','ã'=>'a',
            'è'=>'e','é'=>'e','ê'=>'e','ë'=>'e',
            'î'=>'i','ï'=>'i','í'=>'i','ì'=>'i',
            'ô'=>'o','ö'=>'o','ó'=>'o','ò'=>'o','õ'=>'o',
            'ù'=>'u','û'=>'u','ü'=>'u','ú'=>'u',
            'ç'=>'c','ñ'=>'n','ý'=>'y','ÿ'=>'y',
        ]);
        $s = preg_replace('/[\-\–\—\_\.\,\:\!\?\'\"]+/', ' ', $s);
        return preg_replace('/\s+/', ' ', trim($s));
    };
    $q = $normalize($searchQuery);
    $pool = array_filter(
        $pool,
        fn($a) =>
            str_contains($normalize($a['title']          ?? ''), $q) ||
            str_contains($normalize($a['title_english']  ?? ''), $q) ||
            str_contains($normalize($a['title_romaji']   ?? ''), $q) ||
            str_contains($normalize($a['title_original'] ?? ''), $q)
    );
}

$pool = array_values($pool);

// ── Tri ───────────────────────────────────────────────────────────────────────
$sagaSort = function (array $a, array $b, bool $desc = false): int {
    $prefix = fn(string $t): string => mb_strtolower(
        trim(preg_replace('/\s+/', ' ', preg_replace('/^(le|la|les|the|un|une|des|l\'|d\')\s+/iu', '', $t)))
    );
    $key = fn(string $t): string => trim((string)(preg_match('/^([^:,\-\(0-9]+)/u', $prefix($t), $m) ? $m[1] : $prefix($t)));
    $ka = $key($a['title']);
    $kb = $key($b['title']);
    $cmp = $desc ? strcmp($kb, $ka) : strcmp($ka, $kb);
    if ($cmp !== 0) return $cmp;
    $ya = $a['year'] ?? 0;
    $yb = $b['year'] ?? 0;
    $ycmp = $desc ? ($yb <=> $ya) : ($ya <=> $yb);
    return $ycmp !== 0 ? $ycmp : ($desc ? strcmp($b['title'], $a['title']) : strcmp($a['title'], $b['title']));
};

match ($sortMode) {
    'score'    => usort($pool, fn($a, $b) => ($b['score'] ?? 0) <=> ($a['score'] ?? 0)),
    'year'     => usort($pool, fn($a, $b) => ($b['year']  ?? 0) <=> ($a['year']  ?? 0)),
    'year_asc' => usort($pool, fn($a, $b) => ($a['year']  ?? 0) <=> ($b['year']  ?? 0)),
    'z_a'      => usort($pool, fn($a, $b) => $sagaSort($a, $b, true)),
    'recent'   => usort($pool, fn($a, $b) => strcmp($b['updated_at'] ?? '', $a['updated_at'] ?? '')),
    default    => usort($pool, fn($a, $b) => $sagaSort($a, $b)),
};

// ── Counts par type (toujours sur la totalité, sans filtre type) ─────────────
$countPerType = ['anime' => 0, 'serie' => 0, 'film' => 0];
foreach ($validTypes as $t) $countPerType[$t] = count(IndexManager::getAll($t));
$countAll = array_sum($countPerType);

// ── Genres disponibles (pour le select) ──────────────────────────────────────
$allGenres = [];
foreach ($loadTypes as $t) {
    foreach (IndexManager::getAll($t) as $item) {
        foreach (($item['genres'] ?? []) as $g) $allGenres[$g] = true;
    }
}
ksort($allGenres);
$allGenres = array_keys($allGenres);

// ── Pagination ───────────────────────────────────────────────────────────────
$perPage    = 20;
$totalItems = count($pool);
$page       = max(1, (int)($_GET['page'] ?? 1));
$totalPages = max(1, (int)ceil($totalItems / $perPage));
$page       = min($page, $totalPages);
$pageItems  = array_slice($pool, ($page - 1) * $perPage, $perPage);

function catalogPaginationUrl(int $p): string
{
    $params = array_filter([
        'type'  => $_GET['type']  ?? '',
        'q'     => $_GET['q']     ?? '',
        'genre' => $_GET['genre'] ?? '',
        'year'  => $_GET['year']  ?? '',
        'lang'  => $_GET['lang']  ?? '',
        'local' => $_GET['local'] ?? '',
        'sort'  => $_GET['sort']  ?? '',
        'page'  => $p > 1 ? (string)$p : '',
    ], fn($v) => $v !== '');
    return '/catalog/?' . http_build_query($params);
}

// ── URL générique (préserve tous les filtres, remplace la clé donnée) ────────
function catalogFilterUrl(array $override): string
{
    $params = array_filter(array_merge([
        'type'  => $_GET['type']  ?? '',
        'q'     => $_GET['q']     ?? '',
        'genre' => $_GET['genre'] ?? '',
        'year'  => $_GET['year']  ?? '',
        'lang'  => $_GET['lang']  ?? '',
        'local' => $_GET['local'] ?? '',
        'sort'  => $_GET['sort']  ?? '',
    ], $override), fn($v) => $v !== '');
    return '/catalog/' . ($params ? '?' . http_build_query($params) : '');
}

// ── URL des tabs (en préservant les filtres actifs) ──────────────────────────
function catalogTabUrl(string $type): string
{
    $params = array_filter([
        'type'  => $type,
        'q'     => $_GET['q']     ?? '',
        'genre' => $_GET['genre'] ?? '',
        'year'  => $_GET['year']  ?? '',
        'lang'  => $_GET['lang']  ?? '',
        'local' => $_GET['local'] ?? '',
        'sort'  => $_GET['sort']  ?? '',
    ], fn($v) => $v !== '');
    return '/catalog/?' . http_build_query($params);
}

// ── Titre de page ─────────────────────────────────────────────────────────────
$pageTitle = match ($typeFilter) {
    'anime' => 'Animes',
    'serie' => 'Séries',
    'film'  => 'Films',
    default => 'Catalogue',
};

$current_path = '/catalog';

require_once __DIR__ . '/../components/card.php';
require_once __DIR__ . '/../components/pagination.php';

// ── Mode AJAX : retourne uniquement le fragment grille + pagination ───────────
if ($isAjax) {
    header('Content-Type: text/html; charset=utf-8');
    if (empty($pageItems)) {
        $resetUrl = '/catalog/' . ($typeFilter !== '' ? '?type=' . urlencode($typeFilter) : '');
        echo '<div style="text-align:center;padding:80px 0;color:var(--color-text-muted)">';
        echo '<p style="font-size:3rem;margin-bottom:16px">' . lc('tv-2', 48) . '</p>';
        echo '<p>Aucun résultat trouvé.</p>';
        echo '<a href="' . htmlspecialchars($resetUrl) . '" class="btn btn--ghost" style="margin-top:16px">Réinitialiser les filtres</a>';
        echo '</div>';
    } else {
        echo '<div class="grid-cards" id="catalog-grid">';
        foreach ($pageItems as $item) {
            echo render_card($item, $item['_type'], [
                'data_attrs' => [
                    'search' => strtolower(implode(' ', array_filter([
                        $item['title'],
                        $item['title_english'] ?? '',
                        $item['title_romaji'] ?? '',
                        $item['title_original'] ?? '',
                    ]))),
                ],
            ]);
        }
        echo '</div>';
        echo render_pagination($page, $totalPages, 'catalogPaginationUrl');
    }
    exit;
}
?>
<!DOCTYPE html>
<html lang="fr">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $pageTitle ?> — Hoshimi</title>
    <link rel="stylesheet" href="/css/hoshimi.css">
    <script src="/js/storage.js"></script>
</head>

<body>
    <?php include __DIR__ . '/../components/navbar.php'; ?>

    <main>

        <!-- ── Hero ──────────────────────────────────────────────────────────── -->
        <?php
        ob_start(); ?>
        <form class="page-hero__search" method="get" action="/catalog/" role="search" id="catalog-search-form">
            <?php foreach (['type', 'genre', 'year', 'lang', 'local', 'sort'] as $k) {
                if (!empty($_GET[$k])) echo '<input type="hidden" name="' . $k . '" value="' . htmlspecialchars($_GET[$k]) . '">';
            } ?>
            <svg xmlns="http://www.w3.org/2000/svg" id="catalog-search-icon" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="11" cy="11" r="8"/><path d="m21 21-4.3-4.3"/></svg>
            <div class="spinner spinner--sm" id="catalog-search-spinner" style="display:none"></div>
            <input type="search" name="q" id="catalog-search-input" placeholder="Rechercher un film, une série, un anime…" value="<?= htmlspecialchars($searchQuery) ?>" autocomplete="off">
            <button type="button" class="page-hero__search-clear" id="catalog-search-clear" style="<?= $searchQuery === '' ? 'display:none' : '' ?>"><?= lc('x', 14) ?></button>
        </form>
        <?php
        $heroTitle = 'Catalogue';
        $heroSub   = 'Explorez tout le contenu disponible sur Hoshimi';
        $heroExtra = ob_get_clean();
        include __DIR__ . '/../components/page-hero.php';
        ?>

        <!-- ── Barre de contrôles sticky ─────────────────────────────────────── -->
        <div class="fav-controls-bar" id="catalog-controls-bar">
            <div class="container fav-controls-bar__inner">
                <nav class="fav-tabs" aria-label="Filtrer par type">
                    <?php
                    $tabs = [
                        ''      => ['label' => 'Tous',   'icon' => 'list',         'count' => $countAll,              'color' => 'var(--color-accent)'],
                        'anime' => ['label' => 'Animes', 'icon' => 'tv-2',         'count' => $countPerType['anime'], 'color' => '#7eb8f7'],
                        'serie' => ['label' => 'Séries', 'icon' => 'tv',           'count' => $countPerType['serie'], 'color' => '#87d68b'],
                        'film'  => ['label' => 'Films',  'icon' => 'clapperboard', 'count' => $countPerType['film'],  'color' => '#f7b76b'],
                    ];
                    foreach ($tabs as $tabType => $tab):
                        $isActive = $typeFilter === $tabType;
                    ?>
                        <a href="<?= htmlspecialchars(catalogTabUrl($tabType)) ?>"
                            class="fav-tab<?= $isActive ? ' is-active' : '' ?>"
                            style="--tab-color: <?= $tab['color'] ?>"
                            <?= $isActive ? 'aria-current="page"' : '' ?>>
                            <?= lc($tab['icon'], 14) ?>
                            <?= $tab['label'] ?>
                            <span class="fav-tab__badge"><?= $tab['count'] ?></span>
                        </a>
                    <?php endforeach; ?>
                </nav>

                <div class="fav-controls-bar__right">

                    <?php
                    $CHECK = '<svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"/></svg>';

                    // ── Genre select ──────────────────────────────────────────
                    ?>
                    <div class="cs-wrap fav-sort-wrap" id="catalog-genre-wrap">
                        <button class="cs-btn" id="catalog-genre-btn" aria-haspopup="listbox" aria-expanded="false">
                            <svg xmlns="http://www.w3.org/2000/svg" width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                                <polygon points="22 3 2 3 10 12.46 10 19 14 21 14 12.46 22 3" />
                            </svg>
                            <span><?= htmlspecialchars($genreFilter !== '' ? $genreFilter : 'Tous les genres') ?></span>
                            <svg class="cs-chevron" width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                                <polyline points="6 9 12 15 18 9" />
                            </svg>
                        </button>
                        <ul class="cs-list" id="catalog-genre-dropdown" role="listbox" style="min-width:200px;width:max-content;max-height:320px;overflow-y:auto">
                            <?php $isNone = $genreFilter === ''; ?>
                            <li class="cs-item<?= $isNone ? ' is-active' : '' ?>" role="option" aria-selected="<?= $isNone ? 'true' : 'false' ?>">
                                <a class="cs-item__link" href="<?= htmlspecialchars(catalogFilterUrl(['genre' => ''])) ?>">
                                    <span class="cs-check"><?= $isNone ? $CHECK : '' ?></span>
                                    <span class="cs-body"><span class="cs-item-label">Tous les genres</span></span>
                                </a>
                            </li>
                            <?php foreach ($allGenres as $g): $active = $genreFilter === $g; ?>
                                <li class="cs-item<?= $active ? ' is-active' : '' ?>" role="option" aria-selected="<?= $active ? 'true' : 'false' ?>">
                                    <a class="cs-item__link" href="<?= htmlspecialchars(catalogFilterUrl(['genre' => $g])) ?>">
                                        <span class="cs-check"><?= $active ? $CHECK : '' ?></span>
                                        <span class="cs-body"><span class="cs-item-label"><?= htmlspecialchars($g) ?></span></span>
                                    </a>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                    </div>

                    <?php
                    // ── Local select ──────────────────────────────────────────
                    $localLabel = match ($localFilter) {
                        '1' => 'Disponible',
                        '0' => 'Non dispo',
                        default => 'Tous'
                    };
                    $localOptions = ['all' => 'Tous', '1' => 'Disponible', '0' => 'Non disponible'];
                    ?>
                    <div class="cs-wrap fav-sort-wrap" id="catalog-local-wrap">
                        <button class="cs-btn" id="catalog-local-btn" aria-haspopup="listbox" aria-expanded="false">
                            <svg xmlns="http://www.w3.org/2000/svg" width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                                <ellipse cx="12" cy="5" rx="9" ry="3" />
                                <path d="M3 5v14c0 1.66 4.03 3 9 3s9-1.34 9-3V5" />
                                <path d="M3 12c0 1.66 4.03 3 9 3s9-1.34 9-3" />
                            </svg>
                            <span><?= htmlspecialchars($localLabel) ?></span>
                            <svg class="cs-chevron" width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                                <polyline points="6 9 12 15 18 9" />
                            </svg>
                        </button>
                        <ul class="cs-list" id="catalog-local-dropdown" role="listbox" style="min-width:180px;width:max-content">
                            <?php foreach ($localOptions as $val => $lbl): $active = $localFilter === (string)$val; ?>
                                <li class="cs-item<?= $active ? ' is-active' : '' ?>" role="option" aria-selected="<?= $active ? 'true' : 'false' ?>">
                                    <a class="cs-item__link" href="<?= htmlspecialchars(catalogFilterUrl(['local' => (string)$val])) ?>">
                                        <span class="cs-check"><?= $active ? $CHECK : '' ?></span>
                                        <span class="cs-body"><span class="cs-item-label"><?= htmlspecialchars($lbl) ?></span></span>
                                    </a>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                    </div>

                    <?php
                    // ── Sort select ───────────────────────────────────────────
                    $sortOptions = [
                        'recent'   => 'Récemment ajoutés',
                        'alpha'    => 'A → Z',
                        'z_a'      => 'Z → A',
                        'score'    => 'Mieux notés',
                        'year'     => 'Plus récents',
                        'year_asc' => 'Plus anciens',
                    ];
                    ?>
                    <div class="cs-wrap fav-sort-wrap" id="catalog-sort-wrap">
                        <button class="cs-btn" id="catalog-sort-btn" aria-haspopup="listbox" aria-expanded="false">
                            <svg xmlns="http://www.w3.org/2000/svg" width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                                <line x1="4" y1="6" x2="11" y2="6" />
                                <line x1="4" y1="12" x2="11" y2="12" />
                                <line x1="4" y1="18" x2="13" y2="18" />
                                <polyline points="15 15 18 18 21 15" />
                                <line x1="18" y1="6" x2="18" y2="18" />
                            </svg>
                            <span><?= htmlspecialchars($sortOptions[$sortMode] ?? 'A → Z') ?></span>
                            <svg class="cs-chevron" width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                                <polyline points="6 9 12 15 18 9" />
                            </svg>
                        </button>
                        <ul class="cs-list" id="catalog-sort-dropdown" role="listbox" style="min-width:180px;width:max-content">
                            <?php foreach ($sortOptions as $val => $lbl): $active = $sortMode === $val; ?>
                                <li class="cs-item<?= $active ? ' is-active' : '' ?>" role="option" aria-selected="<?= $active ? 'true' : 'false' ?>">
                                    <a class="cs-item__link" href="<?= htmlspecialchars(catalogFilterUrl(['sort' => $val !== 'recent' ? $val : ''])) ?>">
                                        <span class="cs-check"><?= $active ? $CHECK : '' ?></span>
                                        <span class="cs-body"><span class="cs-item-label"><?= htmlspecialchars($lbl) ?></span></span>
                                    </a>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                    </div>

                </div>
            </div>
        </div>

        <div class="container" style="padding-top: 32px; padding-bottom: 60px;">

            <?php if (empty($pageItems)) : ?>
                <div style="text-align:center; padding:80px 0; color:var(--color-text-muted);">
                    <p style="font-size:3rem; margin-bottom:16px;"><?= lc('tv-2', 48) ?></p>
                    <p>Aucun résultat trouvé.</p>
                    <?php if ($genreFilter || $yearFilter || $localFilter === 'all' || $localFilter === '0' || $searchQuery !== '') : ?>
                        <a href="/catalog/<?= $typeFilter !== '' ? '?type=' . urlencode($typeFilter) : '' ?>" class="btn btn--ghost" style="margin-top:16px;">Réinitialiser les filtres</a>
                    <?php endif; ?>
                </div>
            <?php else : ?>
                <div class="grid-cards" id="catalog-grid">
                    <?php foreach ($pageItems as $item) : ?>
                        <?= render_card($item, $item['_type'], [
                            'data_attrs' => [
                                'search' => strtolower(implode(' ', array_filter([
                                    $item['title'],
                                    $item['title_english'] ?? '',
                                    $item['title_romaji'] ?? '',
                                    $item['title_original'] ?? '',
                                ]))),
                            ],
                        ]) ?>
                    <?php endforeach; ?>
                </div>
                <?= render_pagination($page, $totalPages, 'catalogPaginationUrl') ?>
            <?php endif; ?>

        </div>

    </main>

    <?php include __DIR__ . '/../components/footer.php'; ?>

    <script>
        (function() {
            // ── Live search ───────────────────────────────────────────────────────
            const searchInput   = document.getElementById('catalog-search-input');
            const searchIcon    = document.getElementById('catalog-search-icon');
            const searchSpinner = document.getElementById('catalog-search-spinner');
            const searchClear   = document.getElementById('catalog-search-clear');
            const catalogZone   = document.querySelector('.container[style*="padding-top"]');

            let _debounce = null;

            function getCurrentParams() {
                const url = new URL(window.location.href);
                url.searchParams.delete('_ajax');
                url.searchParams.delete('page');
                return url;
            }

            function setLoading(on) {
                if (searchIcon)    searchIcon.style.display    = on ? 'none'         : '';
                if (searchSpinner) searchSpinner.style.display = on ? 'inline-block' : 'none';
            }

            async function doSearch(q) {
                setLoading(true);
                const url = getCurrentParams();
                if (q) url.searchParams.set('q', q);
                else   url.searchParams.delete('q');
                url.searchParams.set('_ajax', '1');

                try {
                    const res  = await fetch(url.toString());
                    const html = await res.text();
                    if (catalogZone) catalogZone.innerHTML = html;
                } catch {
                    // Silently ignore network errors
                } finally {
                    setLoading(false);
                }

                // Met à jour l'URL sans recharger
                const histUrl = getCurrentParams();
                if (q) histUrl.searchParams.set('q', q);
                else   histUrl.searchParams.delete('q');
                history.replaceState(null, '', histUrl.toString());
            }

            searchInput?.addEventListener('input', () => {
                const q = searchInput.value.trim();
                if (searchClear) searchClear.style.display = q ? '' : 'none';
                clearTimeout(_debounce);
                _debounce = setTimeout(() => doSearch(q), 100);
            });

            searchClear?.addEventListener('click', () => {
                if (searchInput) searchInput.value = '';
                if (searchClear) searchClear.style.display = 'none';
                searchInput?.focus();
                doSearch('');
            });

            // ── Sticky sentinel ───────────────────────────────────────────────────
            const bar = document.getElementById('catalog-controls-bar');
            if (!bar) return;
            const sentinel = document.createElement('div');
            sentinel.style.height = '1px';
            bar.before(sentinel);
            new IntersectionObserver(([e]) => {
                bar.classList.toggle('is-sticky', !e.isIntersecting);
            }, {
                threshold: 1
            }).observe(sentinel);

            // ── Custom select toggles ─────────────────────────────────────────────
            const allWraps = document.querySelectorAll('#catalog-controls-bar .cs-wrap');

            function closeAll(except) {
                allWraps.forEach(w => {
                    if (w === except) return;
                    w.classList.remove('is-open');
                    w.querySelector('.cs-btn')?.setAttribute('aria-expanded', 'false');
                });
            }

            function positionDropdown(wrap) {
                const list = wrap.querySelector('.cs-list');
                if (!list) return;
                // Reset pour mesurer la position naturelle
                list.style.left = '';
                list.style.right = '0';
                const rect = list.getBoundingClientRect();
                const vw   = window.innerWidth;
                if (rect.left < 8) {
                    // Déborde à gauche → aligner à gauche du bouton
                    list.style.right = 'auto';
                    list.style.left  = '0';
                } else if (rect.right > vw - 8) {
                    // Déborde à droite → pousser vers la gauche
                    list.style.right = '0';
                    list.style.left  = 'auto';
                }
            }

            allWraps.forEach(wrap => {
                const btn = wrap.querySelector('.cs-btn');
                if (!btn) return;
                btn.addEventListener('click', e => {
                    e.stopPropagation();
                    const opening = !wrap.classList.contains('is-open');
                    closeAll(opening ? wrap : null);
                    wrap.classList.toggle('is-open', opening);
                    btn.setAttribute('aria-expanded', opening);
                    if (opening) {
                        positionDropdown(wrap);
                        const list = wrap.querySelector('.cs-list');
                        const active = list?.querySelector('.cs-item.is-active');
                        if (list && active) list.scrollTop = active.offsetTop - list.offsetTop;
                    }
                });
            });

            document.addEventListener('click', () => closeAll(null));
        })();
    </script>
    <script src="/js/guard.js"></script>
    <script src="/js/favorites.js"></script>
</body>

</html>