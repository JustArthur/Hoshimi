<?php

declare(strict_types=1);

require_once __DIR__ . '/../../app/Services/FilmScanner.php';

$scanner  = new FilmScanner();
$allFilms = $scanner->getAllFilms();
$films    = $allFilms;

$sortMode    = $_GET['sort']  ?? 'alpha';
$yearFilter  = trim($_GET['year']  ?? '');
$genreFilter = trim($_GET['genre'] ?? '');

if ($yearFilter  !== '') $films = array_filter($films, fn($f) => (string)($f['year'] ?? '') === $yearFilter);
if ($genreFilter !== '') $films = array_filter($films, fn($f) => in_array($genreFilter, $f['genres'] ?? [], true));

$films = array_values($films);
match ($sortMode) {
    'year'     => usort($films, fn($a, $b) => ($b['year']  ?? 0) <=> ($a['year']  ?? 0)),
    'year_asc' => usort($films, fn($a, $b) => ($a['year']  ?? 0) <=> ($b['year']  ?? 0)),
    'score'    => usort($films, fn($a, $b) => ($b['score'] ?? 0) <=> ($a['score'] ?? 0)),
    'z_a'      => usort($films, fn($a, $b) => strcmp($b['title'], $a['title'])),
    default    => null,
};

$allYears = [];
foreach ($allFilms as $f) { if (!empty($f['year'])) $allYears[$f['year']] = true; }
krsort($allYears); $allYears = array_keys($allYears);

$allGenres = [];
foreach ($allFilms as $f) { foreach (($f['genres'] ?? []) as $g) $allGenres[$g] = true; }
ksort($allGenres); $allGenres = array_keys($allGenres);

$perPage    = 20;
$totalItems = count($films);
$page       = max(1, (int)($_GET['page'] ?? 1));
$totalPages = max(1, (int)ceil($totalItems / $perPage));
$page       = min($page, $totalPages);
$filmsPage  = array_slice($films, ($page - 1) * $perPage, $perPage);

function filmsPaginationUrl(int $p): string
{
    $params = array_filter([
        'genre' => $_GET['genre'] ?? '',
        'year'  => $_GET['year']  ?? '',
        'sort'  => $_GET['sort']  ?? '',
        'page'  => $p > 1 ? (string)$p : '',
    ]);
    return '/films/?' . http_build_query($params);
}

$current_path = '/films';

require_once __DIR__ . '/../components/card.php';
require_once __DIR__ . '/../components/pagination.php';
require_once __DIR__ . '/../components/catalog-sidebar.php';
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Films — Hoshimi</title>
    <link rel="stylesheet" href="/css/hoshimi.css">
</head>
<body>
    <?php include __DIR__ . '/../components/navbar.php'; ?>

    <main>
        <div class="container" style="padding-top: 32px; padding-bottom: 60px;">
            <div class="catalog-layout">

                <?= render_catalog_sidebar([
                    'base_url' => '/films/',
                    'sort'     => $sortMode,
                    'year'     => $yearFilter,
                    'genre'    => $genreFilter,
                    'years'    => $allYears,
                    'genres'   => $allGenres,
                    'sort_options' => [
                        'alpha'    => 'A → Z',
                        'z_a'      => 'Z → A',
                        'year'     => 'Année (récent)',
                        'year_asc' => 'Année (ancien)',
                        'score'    => 'Note',
                    ],
                ]) ?>

                <div class="catalog-main">
                    <div style="display:flex; align-items:baseline; gap:12px; margin-bottom:20px;">
                        <h1 class="section-header__title" style="font-size:1.25rem; font-weight:700; color:var(--color-text);">Films</h1>
                        <span style="font-size:0.9rem; color:var(--color-text-muted);"><?= $totalItems ?> titre<?= $totalItems > 1 ? 's' : '' ?></span>
                    </div>

                    <?php if (empty($filmsPage)) : ?>
                        <div style="text-align:center; padding:80px 0; color:var(--color-text-muted);">
                            <p style="font-size:3rem; margin-bottom:16px;">🎬</p>
                            <p>Aucun film trouvé.</p>
                            <?php if ($genreFilter || $yearFilter) : ?>
                                <a href="/films/" class="btn btn--ghost" style="margin-top:16px;">Réinitialiser les filtres</a>
                            <?php elseif (!is_dir($_ENV['FILMS_PATH'] ?? '/media/films')) : ?>
                                <p style="font-size:0.85rem; margin-top:8px;">Le répertoire films n'est pas monté.</p>
                            <?php endif; ?>
                        </div>
                    <?php else : ?>
                        <div class="grid-cards" id="films-grid">
                            <?php foreach ($filmsPage as $film) : ?>
                                <?= render_card($film, 'film', [
                                    'data_attrs' => ['search' => strtolower($film['title'])],
                                ]) ?>
                            <?php endforeach; ?>
                        </div>
                        <?= render_pagination($page, $totalPages, 'filmsPaginationUrl') ?>
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
                    document.querySelectorAll('#films-grid [data-search]').forEach(card => {
                        card.style.display = (!q || card.dataset.search.includes(q)) ? '' : 'none';
                    });
                }, 120);
            });
            searchInput.closest('form')?.addEventListener('submit', e => {
                if (document.querySelectorAll('#films-grid [data-search]').length > 0) {
                    e.preventDefault();
                    searchInput.dispatchEvent(new Event('input'));
                }
            });
        }
    </script>
</body>
</html>
