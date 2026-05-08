<?php
// ============================================================
//  HOSHIMI — Page d'accueil (catalogue animes)
// ============================================================

declare(strict_types=1);

require_once "../app/Services/AnimeScanner.php";

$scanner = new AnimeScanner();
$animes  = $scanner->getAllAnimes();

// Filtres GET
$genreFilter = trim($_GET['genre'] ?? '');
$langFilter  = trim($_GET['lang'] ?? '');

// Filtrage par Genre
if ($genreFilter !== '') {
    $animes = array_filter(
        $animes,
        fn($a) => in_array($genreFilter, $a['genres'], true)
    );
}

// --- Nouveau : Filtrage par Langue ---
if ($langFilter !== '') {
    $animes = array_filter(
        $animes,
        fn($a) => (strtoupper($a['main_language'] ?? '') === strtoupper($langFilter))
    );
}

// Tous les genres disponibles pour le filtre
$allGenres = [];
foreach ($scanner->getAllAnimes() as $a) {
    foreach ($a['genres'] as $g) {
        $allGenres[$g] = true;
    }
}

ksort($allGenres);
$allGenres = array_keys($allGenres);
?>
<!DOCTYPE html>
<html lang="fr">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Hoshimi — Catalogue</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>

    <link rel="stylesheet" href="/css/hoshimi_base.css">
</head>

<body>

    <!-- ============================================================
     NAVBAR
     ============================================================ -->
    <?php

    include __DIR__ . "/components/navbar.php";

    // Détection de la page actuelle pour l'affichage conditionnel
    $current_path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
    $is_home   = ($current_path === '/' || $current_path === '/index.php' || $current_path === '');
    $is_animes = ($current_path === '/animes');
    $is_listes = ($current_path === '/listes');

    ?>

    <!-- ============================================================
     CONTENU PRINCIPAL
     ============================================================ -->
    <main>
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
                        <a href="/animes<?= $genreFilter ? '?genre=' . urlencode($genreFilter) : '' ?>" class="badge" style="opacity: 0.6; color: #c0392b; border: 1px solid #c0392b;">✕  Retirer la langue</a>
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
                        <h2 class="section-header__title">Reprendre la lecture</h2>
                    </div>
                    <div class="grid-cards" id="resume-grid"></div>
                </div>
            <?php endif; ?>

            <?php if ($is_listes) : ?>
                <div id="favorites-section" style="display:none; margin-bottom:48px;">
                    <div class="section-header">
                        <h2 class="section-header__title">Mes Favoris</h2>
                    </div>
                    <div class="grid-cards" id="favorites-grid"></div>
                    <div id="favorites-empty" style="display:none;">
                        <div class="flex-center" style="min-height:120px; flex-direction:column; gap:10px;">
                            <span style="font-size:2rem; opacity:.3;">♡</span>
                            <p class="text-muted" style="font-size:.9rem;">
                                Aucun favori pour l'instant — ajoutez-en depuis la page d'un anime.
                            </p>
                        </div>
                    </div>
                </div>
            <?php endif; ?>

            <div id="main-animes-section" style="<?= $is_listes ? 'display: none;' : '' ?>">
                <div class="section-header" style="margin-bottom: 32px;">
                    <h1 class="section-header__title">Mes Animes</h1>
                    <span class="text-muted" style="font-size: 0.9rem;">
                        <?= count($animes) ?> titre<?= count($animes) > 1 ? 's' : '' ?>
                    </span>
                </div>

                <?php if (empty($animes)) : ?>
                    <div class="flex-center" style="min-height: 300px; flex-direction: column; gap: 16px;">
                        <span style="font-size: 3rem;">🔭</span>
                        <p class="text-muted">
                            <?= $search ? "Aucun résultat pour « " . htmlspecialchars($search) . " »" : "Aucun anime trouvé." ?>
                        </p>
                        <a href="<?= $is_animes ? '/animes' : '/' ?>" class="btn btn--ghost">Réinitialiser les filtres</a>
                    </div>
                <?php else : ?>
                    <div class="grid-cards">
                        <?php foreach ($animes as $anime) : ?>
                            <a href="/anime/?slug=<?= urlencode($anime['slug']) ?>" data-anime-slug="<?= htmlspecialchars($anime['slug']) ?>" class="card" style="display: block;">
                                <div class="card__fav" data-fav-icon="<?= htmlspecialchars($anime['slug']) ?>">♡</div>

                                <?php if ($anime['cover_url']) : ?>
                                    <img class="card__poster" src="<?= htmlspecialchars($anime['cover_url']) ?>" alt="<?= htmlspecialchars($anime['title']) ?>" loading="lazy">
                                <?php else : ?>
                                    <div class="card__poster flex-center" style="background: var(--color-bg-elevated);">
                                        <span style="font-size: 3rem; opacity: .3;">🎬</span>
                                    </div>
                                <?php endif; ?>

                                <?php if ($anime['score']) : ?>
                                    <div class="card__score">★ <?= number_format($anime['score'] / 10, 1) ?></div>
                                <?php endif; ?>

                                <div class="card__overlay">
                                    <span style="font-size: 0.8rem; font-weight: 600; color: #fff;">
                                        <?= $anime['episodes_local'] ?> ép. disponible<?= $anime['episodes_local'] > 1 ? 's' : '' ?>
                                    </span>
                                </div>

                                <div class="card__body">
                                    <div class="card__title" title="<?= htmlspecialchars($anime['title_english']) ?>">
                                        <?= htmlspecialchars($anime['title_english']) ?>
                                    </div>
                                    <div class="card__meta">
                                        <?php if ($anime['year']) : ?><span><?= $anime['year'] ?></span><span style="opacity:.3;">·</span><?php endif; ?>
                                        <span><?= htmlspecialchars($anime['format']) ?></span>
                                    </div>
                                </div>
                            </a>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>

            <?php if ($is_listes) : ?>
                <div id="lists-section" style="margin-top: 20px;">
                    <div id="lists-container"></div>
                </div>
            <?php endif; ?>

        </div>
    </main>

    <!-- ============================================================
     FOOTER
     ============================================================ -->
    <?php include __DIR__ . "/components/footer.php"; ?>

    <script src="/js/hoshimi_accent.js"></script>
    <script src="/js/header.js"></script>

    <?php
    $episodesForJs = [];
    foreach ($scanner->getAllAnimes() as $a) {
        $eps = [];
        foreach ($a['seasons'] as $season) {
            foreach ($season['episodes'] as $ep) {
                $eps[] = [
                    'file_key' => md5($ep['file_path']),
                    'number'   => $ep['number'],
                    'season'   => $season['number'],
                    'title'    => $ep['title']
                ];
            }
        }
        $episodesForJs[$a['slug']] = $eps;
    }
    ?>
    <script src="/js/hoshimi_storage.js"></script>
    <script>
        // Barres de progression
        HoshimiStorage.updateCardProgressBars(<?= json_encode($episodesForJs) ?>);

        const favs = HoshimiStorage.Favorites.getAll();
        const favSlugs = new Set(favs.map(f => f.slug));

        // Icônes sur les cartes
        document.querySelectorAll('[data-fav-icon]').forEach(icon => {
            const isFav = favSlugs.has(icon.dataset.favIcon);
            if (isFav) {
                icon.textContent = '♥';
                icon.classList.add('is-fav');
            } else {
                icon.style.display = 'none';
            }
        });

        // ---- Section Reprendre ----
        const allProgress = HoshimiStorage.Progress.getAllProgress();
        const resumeItems = [];

        // Pour chaque anime, cherche le dernier épisode en cours
        const allEpisodesData = <?= json_encode($episodesForJs) ?>;

        // ---- Section Listes ----
        const allLists = HoshimiStorage.Lists.getAll();
        const listsSection = document.getElementById('lists-section');
        const listsContainer = document.getElementById('lists-container');

        // On ne garde que les listes qui ont au moins un anime
        const listsWithItems = allLists.filter(l => l.items && l.items.length > 0);

        if (listsSection) {
            listsSection.style.display = 'block';
            listsContainer.innerHTML = '';

            if (listsWithItems.length > 0) {
                // Affichage des listes
                listsWithItems.forEach(list => {
                    const header = document.createElement('div');
                    header.className = 'section-header';
                    header.style.cssText = 'margin-bottom: 16px; display: flex; align-items: center; gap: 10px;';

                    header.innerHTML = `
                        <h2 class="section-header__title" style="margin-bottom: 0;">${list.name}</h2>
                        
                        <div style="display: flex; gap: 4px; align-items: center; margin-left: 10px;">
                            <button onclick="renameList('${list.id}', '${list.name}')" class="btn-list-action" title="Renommer">
                                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"></path><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"></path></svg>
                            </button>

                            <button onclick="deleteList('${list.id}', '${list.name}')" class="btn-list-action btn-list-action--danger" title="Supprimer la liste">
                                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="3 6 5 6 21 6"></polyline><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"></path></svg>
                            </button>
                        </div>
                    `;

                    // Grille des cartes (inchangée)
                    const grid = document.createElement('div');
                    grid.className = 'grid-cards';
                    grid.style.marginBottom = '40px';

                    list.items.forEach(item => {
                        const original = document.querySelector(`[data-anime-slug="${item.slug}"]`);
                        if (original) grid.appendChild(original.cloneNode(true));
                    });

                    listsContainer.appendChild(header);
                    listsContainer.appendChild(grid);
                });
            } else {
                // --- MESSAGE : AUCUNE LISTE CRÉÉE ---
                listsContainer.innerHTML = `
                    <div class="flex-center" style="min-height:300px; flex-direction:column; gap:16px; text-align:center;">
                        <span style="font-size:3rem; opacity:.3;">📂</span>
                        <div>
                            <h2 style="font-size:1.2rem; margin-bottom:8px;">Aucune liste pour le moment</h2>
                            <p class="text-muted" style="font-size:.9rem; max-width:300px;">
                                Créez vos propres listes personnalisées depuis la page de vos animes préférés.
                            </p>
                        </div>
                        <a href="/animes" class="btn btn--ghost" style="margin-top:10px;">Parcourir le catalogue</a>
                    </div>
                `;
            }
        }
    </script>

    <script src="/js/hoshimi_fav.js"></script>
    <script src="/js/hoshimi_resume.js"></script>

</body>

</html>