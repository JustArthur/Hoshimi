<?php

declare(strict_types=1);

require_once __DIR__ . '/../../app/Services/SeriesScanner.php';

$scanner   = new SeriesScanner();
$allSeries = $scanner->getAllSeries();
$series    = $allSeries;

$sortMode    = $_GET['sort']  ?? 'alpha';
$yearFilter  = trim($_GET['year']  ?? '');
$genreFilter = trim($_GET['genre'] ?? '');

if ($yearFilter  !== '') $series = array_filter($series, fn($s) => (string)($s['year'] ?? '') === $yearFilter);
if ($genreFilter !== '') $series = array_filter($series, fn($s) => in_array($genreFilter, $s['genres'] ?? [], true));

$series = array_values($series);
match ($sortMode) {
    'score'    => usort($series, fn($a, $b) => ($b['score'] ?? 0) <=> ($a['score'] ?? 0)),
    'year'     => usort($series, fn($a, $b) => ($b['year']  ?? 0) <=> ($a['year']  ?? 0)),
    'year_asc' => usort($series, fn($a, $b) => ($a['year']  ?? 0) <=> ($b['year']  ?? 0)),
    'z_a'      => usort($series, fn($a, $b) => strcmp($b['title'], $a['title'])),
    default    => null,
};

$allYears = [];
foreach ($allSeries as $s) { if (!empty($s['year'])) $allYears[$s['year']] = true; }
krsort($allYears); $allYears = array_keys($allYears);

$allGenres = [];
foreach ($allSeries as $s) { foreach (($s['genres'] ?? []) as $g) $allGenres[$g] = true; }
ksort($allGenres); $allGenres = array_keys($allGenres);

$perPage    = 20;
$totalItems = count($series);
$page       = max(1, (int)($_GET['page'] ?? 1));
$totalPages = max(1, (int)ceil($totalItems / $perPage));
$page       = min($page, $totalPages);
$seriesPage = array_slice($series, ($page - 1) * $perPage, $perPage);

function seriesPaginationUrl(int $p): string
{
    $params = array_filter([
        'genre' => $_GET['genre'] ?? '',
        'year'  => $_GET['year']  ?? '',
        'sort'  => $_GET['sort']  ?? '',
        'page'  => $p > 1 ? (string)$p : '',
    ]);
    return '/series/?' . http_build_query($params);
}

$current_path = '/series';

require_once __DIR__ . '/../components/card.php';
require_once __DIR__ . '/../components/pagination.php';
require_once __DIR__ . '/../components/catalog-sidebar.php';
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Séries — Hoshimi</title>
    <link rel="stylesheet" href="/css/hoshimi.css">
</head>
<body>
    <?php include __DIR__ . '/../components/navbar.php'; ?>

    <main>
        <div class="container" style="padding-top: 32px; padding-bottom: 60px;">
            <div class="catalog-layout">

                <?= render_catalog_sidebar([
                    'base_url' => '/series/',
                    'sort'     => $sortMode,
                    'year'     => $yearFilter,
                    'genre'    => $genreFilter,
                    'years'    => $allYears,
                    'genres'   => $allGenres,
                ]) ?>

                <div class="catalog-main">
                    <div style="display:flex; align-items:baseline; gap:12px; margin-bottom:20px;">
                        <h1 class="section-header__title" style="font-size:1.25rem; font-weight:700; color:var(--color-text);">Séries</h1>
                        <span style="font-size:0.9rem; color:var(--color-text-muted);"><?= $totalItems ?> titre<?= $totalItems > 1 ? 's' : '' ?></span>
                    </div>

                    <?php if (empty($seriesPage)) : ?>
                        <div style="text-align:center; padding:80px 0; color:var(--color-text-muted);">
                            <p style="font-size:3rem; margin-bottom:16px;">📺</p>
                            <p>Aucune série trouvée.</p>
                            <?php if ($genreFilter || $yearFilter) : ?>
                                <a href="/series/" class="btn btn--ghost" style="margin-top:16px;">Réinitialiser les filtres</a>
                            <?php elseif (!is_dir($_ENV['SERIES_PATH'] ?? '/media/series')) : ?>
                                <p style="font-size:0.85rem; margin-top:8px;">Le répertoire séries n'est pas monté.</p>
                            <?php endif; ?>
                        </div>
                    <?php else : ?>
                        <div class="grid-cards" id="series-grid">
                            <?php foreach ($seriesPage as $serie) : ?>
                                <?= render_card($serie, 'serie', [
                                    'show_fav'   => true,
                                    'data_attrs' => [
                                        'anime-slug' => $serie['slug'],
                                        'search'     => strtolower(implode(' ', array_filter([$serie['title'], $serie['title_english'] ?? '', $serie['title_romaji'] ?? '']))),
                                    ],
                                ]) ?>
                            <?php endforeach; ?>
                        </div>
                        <?= render_pagination($page, $totalPages, 'seriesPaginationUrl') ?>
                    <?php endif; ?>
                </div>

            </div>
        </div>
    </main>

    <?php include __DIR__ . '/../components/footer.php'; ?>
    <script src="/js/hoshimi_storage.js"></script>
    <script src="/js/hoshimi_fav.js"></script>
    <script>
        const searchInput = document.querySelector('.search-bar input[name="q"]');
        if (searchInput) {
            let timer;
            searchInput.addEventListener('input', () => {
                clearTimeout(timer);
                timer = setTimeout(() => {
                    const q = searchInput.value.toLowerCase().trim();
                    document.querySelectorAll('#series-grid [data-search]').forEach(card => {
                        card.style.display = (!q || card.dataset.search.includes(q)) ? '' : 'none';
                    });
                }, 120);
            });
            searchInput.closest('form')?.addEventListener('submit', e => {
                if (document.querySelectorAll('#series-grid [data-search]').length > 0) {
                    e.preventDefault();
                    searchInput.dispatchEvent(new Event('input'));
                }
            });
        }
    </script>
</body>
</html>
