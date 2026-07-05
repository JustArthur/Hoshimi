<?php

declare(strict_types=1);

require_once __DIR__ . '/../../app/Services/AnimeScanner.php';

$scanner   = new AnimeScanner();
$allAnimes = $scanner->getAllAnimes();
$animes    = $allAnimes;

$sortMode    = $_GET['sort']  ?? 'alpha';
$yearFilter  = trim($_GET['year']  ?? '');
$genreFilter = trim($_GET['genre'] ?? '');

if ($yearFilter  !== '') $animes = array_filter($animes, fn($a) => (string)($a['year'] ?? '') === $yearFilter);
if ($genreFilter !== '') $animes = array_filter($animes, fn($a) => in_array($genreFilter, $a['genres'] ?? [], true));

$animes = array_values($animes);
match ($sortMode) {
    'score'    => usort($animes, fn($a, $b) => ($b['score'] ?? 0) <=> ($a['score'] ?? 0)),
    'year'     => usort($animes, fn($a, $b) => ($b['year']  ?? 0) <=> ($a['year']  ?? 0)),
    'year_asc' => usort($animes, fn($a, $b) => ($a['year']  ?? 0) <=> ($b['year']  ?? 0)),
    'z_a'      => usort($animes, fn($a, $b) => strcmp($b['title'], $a['title'])),
    default    => null,
};

$allYears = [];
foreach ($allAnimes as $a) { if (!empty($a['year'])) $allYears[$a['year']] = true; }
krsort($allYears); $allYears = array_keys($allYears);

$allGenres = [];
foreach ($allAnimes as $a) { foreach (($a['genres'] ?? []) as $g) $allGenres[$g] = true; }
ksort($allGenres); $allGenres = array_keys($allGenres);

$perPage    = 20;
$totalItems = count($animes);
$page       = max(1, (int)($_GET['page'] ?? 1));
$totalPages = max(1, (int)ceil($totalItems / $perPage));
$page       = min($page, $totalPages);
$animesPage = array_slice($animes, ($page - 1) * $perPage, $perPage);

function animesPaginationUrl(int $p): string
{
    $params = array_filter([
        'genre' => $_GET['genre'] ?? '',
        'year'  => $_GET['year']  ?? '',
        'sort'  => $_GET['sort']  ?? '',
        'page'  => $p > 1 ? (string)$p : '',
    ]);
    return '/animes/?' . http_build_query($params);
}

$current_path = '/animes';

require_once __DIR__ . '/../components/card.php';
require_once __DIR__ . '/../components/pagination.php';
require_once __DIR__ . '/../components/catalog-sidebar.php';
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Animes — Hoshimi</title>
    <link rel="stylesheet" href="/css/hoshimi.css">
</head>
<body>
    <?php include __DIR__ . '/../components/navbar.php'; ?>

    <main>
        <div class="container" style="padding-top: 32px; padding-bottom: 60px;">
            <div class="catalog-layout">

                <?= render_catalog_sidebar([
                    'base_url' => '/animes/',
                    'sort'     => $sortMode,
                    'year'     => $yearFilter,
                    'genre'    => $genreFilter,
                    'years'    => $allYears,
                    'genres'   => $allGenres,
                ]) ?>

                <div class="catalog-main">
                    <div style="display:flex; align-items:baseline; gap:12px; margin-bottom:20px;">
                        <h1 class="section-header__title" style="font-size:1.25rem; font-weight:700; color:var(--color-text);">Animes</h1>
                        <span style="font-size:0.9rem; color:var(--color-text-muted);"><?= $totalItems ?> titre<?= $totalItems > 1 ? 's' : '' ?></span>
                    </div>

                    <?php if (empty($animesPage)) : ?>
                        <div style="text-align:center; padding:80px 0; color:var(--color-text-muted);">
                            <p style="font-size:3rem; margin-bottom:16px;">📺</p>
                            <p>Aucun anime trouvé.</p>
                            <?php if ($genreFilter || $yearFilter) : ?>
                                <a href="/animes/" class="btn btn--ghost" style="margin-top:16px;">Réinitialiser les filtres</a>
                            <?php endif; ?>
                        </div>
                    <?php else : ?>
                        <div class="grid-cards" id="animes-grid">
                            <?php foreach ($animesPage as $anime) : ?>
                                <?= render_card($anime, 'anime', [
                                    'show_fav'   => true,
                                    'data_attrs' => [
                                        'anime-slug' => $anime['slug'],
                                        'search'     => strtolower(implode(' ', array_filter([$anime['title'], $anime['title_english'] ?? '', $anime['title_romaji'] ?? '']))),
                                    ],
                                ]) ?>
                            <?php endforeach; ?>
                        </div>
                        <?= render_pagination($page, $totalPages, 'animesPaginationUrl') ?>
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
                    document.querySelectorAll('#animes-grid [data-search]').forEach(card => {
                        card.style.display = (!q || card.dataset.search.includes(q)) ? '' : 'none';
                    });
                }, 120);
            });
            searchInput.closest('form')?.addEventListener('submit', e => {
                if (document.querySelectorAll('#animes-grid [data-search]').length > 0) {
                    e.preventDefault();
                    searchInput.dispatchEvent(new Event('input'));
                }
            });
        }
    </script>
</body>
</html>
