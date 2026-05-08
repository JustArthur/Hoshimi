<?php
// ============================================================
//  HOSHIMI — Page détail anime
//  URL : /anime/?slug=One+Piece
// ============================================================

declare(strict_types=1);

require_once '../../app/Services/AnimeScanner.php';
require_once '../../app/Services/ThumbnailHelper.php';
$scanner = new AnimeScanner();

ThumbnailHelper::loadIndex();

$slug  = $_GET['slug'] ?? '';
$anime = $slug ? $scanner->getAnimeBySlug($slug) : null;

if (!$anime) {
    http_response_code(404);
    echo '<h1>Anime introuvable</h1>';
    exit;
}

// Saison active (GET ?saison=1 par défaut)
$activeSeason = (int) ($_GET['saison'] ?? 0);

// Cherche la saison par son numéro réel, pas par index
$seasonData = null;
foreach ($anime['seasons'] as $s) {
    if ($s['number'] === $activeSeason) {
        $seasonData = $s;
        break;
    }
}
// Fallback sur la première saison disponible
if ($seasonData === null) {
    $seasonData   = $anime['seasons'][0] ?? null;
    $activeSeason = $seasonData['number'] ?? 0;
}

// Nettoyer le synopsis HTML AniList (balises <br> → texte simple)
$synopsis = $anime['synopsis']
    ? strip_tags(str_replace(['<br>', '<br/>'], "\n", $anime['synopsis']))
    : null;

$statusLabels = [
    'FINISHED'         => 'Terminé',
    'RELEASING'        => 'En cours',
    'NOT_YET_RELEASED' => 'À venir',
];
$statusLabel = !empty($anime['status'])
    ? ($statusLabels[$anime['status']] ?? $anime['status'])
    : null;
?>
<!DOCTYPE html>
<html lang="fr">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($anime['title']) ?> — Hoshimi</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link rel="stylesheet" href="/css/hoshimi_base.css">
    <link rel="stylesheet" href="/css/hoshimi_detail.css">
</head>

<body>

    <!-- ============================================================
     NAVBAR
     ============================================================ -->
    <?php include "../components/navbar.php"; ?>

    <!-- ============================================================
     HERO BANNER
     ============================================================ -->
    <section class="detail-hero">

        <!-- Backdrop (bannerImage AniList ou cover en fallback) -->
        <div class="detail-hero__backdrop" style="background-image: url('<?= $anime['banner_url'] ? htmlspecialchars($anime['banner_url']) : htmlspecialchars($anime['cover_url'] ?? '') ?>');">
        </div>
        <div class="detail-hero__gradient"></div>

        <div class="detail-hero__content">

            <!-- Poster -->
            <div class="detail-poster">
                <?php if ($anime['cover_url']) : ?>
                    <img src="<?= htmlspecialchars($anime['cover_url']) ?>"
                        alt="<?= htmlspecialchars($anime['title']) ?>">
                <?php else : ?>
                    <div class="detail-poster__placeholder">🎬</div>
                <?php endif; ?>
            </div>

            <!-- Infos -->
            <div class="detail-hero__info">

                <div class="detail-hero__format">
                    <?= htmlspecialchars($anime['format']) ?>
                    <?= $anime['year'] ? ' · ' . $anime['year'] : '' ?>
                </div>

                <h1 class="detail-hero__title">
                    <?= htmlspecialchars($anime['title_english']) ?>
                </h1>

                <?php if ($anime['title_original']) : ?>
                    <div class="detail-hero__title-original">
                        <?= htmlspecialchars($anime['title_original']) ?>
                    </div>
                <?php endif; ?>

                <div class="detail-hero__meta">
                    <?php if ($anime['score']) : ?>
                        <span class="detail-hero__score">★ <?= number_format($anime['score'] / 10, 1) ?></span>
                        <span class="detail-hero__meta-sep">·</span>
                    <?php endif; ?>

                    <?php if ($anime['studio']) : ?>
                        <span><?= htmlspecialchars($anime['studio']) ?></span>
                        <span class="detail-hero__meta-sep">·</span>
                    <?php endif; ?>

                    <?php if ($anime['episodes_total']) : ?>
                        <span><?= $anime['episodes_total'] ?> épisodes</span>
                        <span class="detail-hero__meta-sep">·</span>
                    <?php endif; ?>

                    <span><?= $anime['episodes_local'] ?> en local</span>

                    <?php if ($anime['status']) :
                        $statusClass = 'badge--' . strtolower($anime['status']);
                    ?>
                        <span class="detail-hero__meta-sep">·</span>
                        <span class="badge <?= $statusClass ?>"><?= htmlspecialchars($statusLabel) ?></span>
                    <?php endif; ?>
                </div>

                <!-- Genres -->
                <?php if (!empty($anime['genres']) || !empty($anime['main_language'])) : ?>
                    <div class="detail-genres">

                        <?php if (!empty($anime['main_language'])) :
                            $lang = strtoupper($anime['main_language']);
                        ?>
                            <a href="/animes?lang=<?= urlencode($lang) ?>" class="badge badge--lang">
                                <?= htmlspecialchars($lang) ?>
                            </a>
                            <span style="width: 2px; height: 15px; background: rgba(255,255,255,0.1); margin: 0 4px;"></span>
                        <?php endif; ?>

                        <?php foreach (array_slice($anime['genres'], 0, 6) as $genre) : ?>
                            <a href="/animes?genre=<?= urlencode($genre) ?>" class="badge">
                                <?= htmlspecialchars($genre) ?>
                            </a>
                        <?php endforeach; ?>

                    </div>
                <?php endif; ?>

                <!-- Actions -->
                <div class="detail-hero__actions">
                    <?php
                    // Lien vers le premier épisode de la saison active
                    $firstEp = $seasonData['episodes'][0] ?? null;
                    ?>
                    <?php if ($firstEp) : ?>
                        <a href="/player/?slug=<?= urlencode($slug) ?>&saison=<?= $activeSeason ?>&episode=<?= $firstEp['number'] ?>" class="btn btn--primary">
                            ▶ Regarder
                        </a>
                    <?php endif; ?>
                    <button class="btn btn--ghost" data-fav-btn title="Ajouter aux favoris">♡ Favoris</button>
                    <button class="btn btn--ghost" data-list-btn title="Ajouter à une liste">+ Liste</button>
                </div>

            </div>
        </div>
    </section>

    <!-- ============================================================
     CORPS — Synopsis + Épisodes + Sidebar
     ============================================================ -->
    <div class="detail-body">

        <!-- Colonne principale -->
        <div>

            <!-- Synopsis -->
            <?php if ($synopsis) : ?>
                <div class="synopsis-section">
                    <div class="section-header">
                        <h2 class="section-header__title">Synopsis</h2>
                    </div>
                    <p class="synopsis synopsis--collapsed" id="synopsis-text">
                        <?= htmlspecialchars($synopsis) ?>
                    </p>
                    <button
                        type="button"
                        class="btn btn--ghost synopsis-toggle"
                        onclick="
            const el = document.getElementById('synopsis-text');
            const isCollapsed = el.classList.toggle('synopsis--collapsed');
            this.textContent = isCollapsed ? 'Lire plus' : 'Réduire';
          ">Lire plus</button>
                </div>
            <?php endif; ?>

            <!-- Onglets saisons -->
            <?php if (count($anime['seasons']) > 1) : ?>
                <div class="seasons-tabs">
                    <?php foreach ($anime['seasons'] as $season) : ?>
                        <a href="?slug=<?= urlencode($slug) ?>&saison=<?= $season['number'] ?>"
                            class="seasons-tab <?= (int)$activeSeason === (int)$season['number'] ? 'active' : '' ?>">
                            <?= htmlspecialchars($season['label']) ?>
                        </a>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>

            <!-- Liste des épisodes -->
            <div class="section-header section-header--episodes">
                <h2 class="section-header__title">
                    <?php
                    // On adapte le titre en fonction du type de saison
                    if ($activeSeason == 999) echo "Films";
                    elseif ($activeSeason == 888) echo "OAV";
                    elseif ($activeSeason == 777) echo "Spéciaux";
                    else echo "Épisodes";
                    ?>

                    <?php if ($seasonData) : ?>
                        <span class="text-muted" style="font-size: 0.8rem; font-weight: 400; margin-left: 8px;">
                            <?= count($seasonData['episodes']) ?> fichier<?= count($seasonData['episodes']) > 1 ? 's' : '' ?>
                        </span>
                    <?php endif; ?>
                </h2>
            </div>

            <?php if ($seasonData && !empty($seasonData['episodes'])) : ?>
                <div class="episode-grid">
                    <?php foreach ($seasonData['episodes'] as $ep) : ?>
                        <?php
                        // 1. Détection du type de contenu
                        $isFilm = ($activeSeason == 999);

                        // 2. Formatage de la durée (MM:SS ou HH:MM:SS)
                        $durationOutput = "";
                        if (!empty($ep['duration'])) {
                            $seconds = (int)$ep['duration'];
                            $h = floor($seconds / 3600);
                            $m = floor(($seconds % 3600) / 60);
                            $s = $seconds % 60;

                            if ($h > 0) {
                                $durationOutput = sprintf('%02d:%02d:%02d', $h, $m, $s);
                            } else {
                                $durationOutput = sprintf('%02d:%02d', $m, $s);
                            }
                        }

                        // 3. Nettoyage du titre pour les films
                        $displayTitle = $ep['title'];
                        if ($isFilm) {
                            $search = ['.mkv', '.mp4', '1080p', '720p', 'MULTISUB', 'MULTI', 'FILM', 'x264', 'x265', 'h264', 'h265'];
                            $displayTitle = str_ireplace($search, '', $displayTitle);
                            $displayTitle = trim($displayTitle, " _-");
                            if (empty($displayTitle)) $displayTitle = "Film";
                        }
                        ?>

                        <a href="/player/?slug=<?= urlencode($slug) ?>&saison=<?= $activeSeason ?>&episode=<?= $ep['number'] ?>"
                            data-ep-key="<?= md5($ep['file_path']) ?>"
                            class="episode-card">

                            <div class="episode-card__thumb">
                                <?php $thumb = ThumbnailHelper::getUrlFromIndex($ep['file_path']); ?>
                                <?php if ($thumb) : ?>
                                    <img src="<?= htmlspecialchars($thumb) ?>" alt="<?= $isFilm ? 'Film' : 'Épisode ' . $ep['number'] ?>" loading="lazy">
                                <?php else : ?>
                                    <span class="episode-card__num">
                                        <?= $isFilm ? '🎬' : str_pad((string)$ep['number'], 2, '0', STR_PAD_LEFT) ?>
                                    </span>
                                <?php endif; ?>

                                <?php if ($durationOutput) : ?>
                                    <div class="episode-card__duration"><?= $durationOutput ?></div>
                                <?php endif; ?>

                                <div class="episode-card__play">▶</div>

                                <div class="episode-card__progress">
                                    <div class="episode-card__progress-fill" style="width: 0%"></div>
                                </div>
                            </div>

                            <div class="episode-card__body">
                                <div class="episode-card__title">
                                    <?= $isFilm ? htmlspecialchars($displayTitle) : 'Épisode ' . str_pad((string)$ep['number'], 2, '0', STR_PAD_LEFT) ?>
                                </div>
                            </div>
                        </a>
                    <?php endforeach; ?>
                </div>
            <?php else : ?>
            <?php endif; ?>

        </div>

        <!-- Sidebar informations -->
        <aside>
            <div class="info-block">
                <div class="info-block__title">Informations</div>

                <?php
                $infos = [
                    'Format'    => $anime['format'] ?? null,
                    'Statut'    => $statusLabel,
                    'Année'     => $anime['year'] ?? null,
                    'Studio'    => $anime['studio'] ?? null,
                    'Épisodes'  => $anime['episodes_total']
                        ? $anime['episodes_total'] . ' épisodes'
                        : null,
                    'En local'  => $anime['episodes_local'] . ' fichier' . ($anime['episodes_local'] > 1 ? 's' : ''),
                    'Score'     => $anime['score']
                        ? '★ ' . number_format($anime['score'] / 10, 1) . ' / 10'
                        : null,
                    'AniList'   => $anime['anilist_id']
                        ? '#' . $anime['anilist_id']
                        : null,
                ];
                ?>

                <?php foreach ($infos as $label => $value) : ?>
                    <?php if ($value !== null) : ?>
                        <div class="info-row">
                            <span class="info-row__label"><?= $label ?></span>
                            <span class="info-row__value"><?= htmlspecialchars((string)$value) ?></span>
                        </div>
                    <?php endif; ?>
                <?php endforeach; ?>

            </div>

            <!-- Lien AniList externe -->
            <?php if ($anime['anilist_id']) : ?>
                <a
                    href="https://anilist.co/anime/<?= $anime['anilist_id'] ?>"
                    target="_blank"
                    rel="noopener"
                    class="btn btn--ghost"
                    style="width: 100%; justify-content: center; margin-top: 12px;">
                    Voir sur AniList ↗
                </a>
            <?php endif; ?>

        </aside>

    </div>

    <!-- ============================================================
     FOOTER
     ============================================================ -->
    <?php include "../components/footer.php"; ?>

    <!-- Couleur accent dynamique depuis le JSON AniList -->
    <script src="../js/hoshimi_accent.js"></script>
    <script>
        const animeData = <?= json_encode($anime['metadata'] ?? []) ?>;

        setAccentFromAnime(animeData);
    </script>

    <script src="../js/hoshimi_storage.js"></script>
    <script>
        HoshimiStorage.initFavoriteButton(
            document.querySelector('[data-fav-btn]'),
            "<?= htmlspecialchars($slug) ?>", {
                title: "<?= htmlspecialchars($anime['title']) ?>",
                cover_url: "<?= htmlspecialchars($anime['cover_url'] ?? '') ?>"
            }
        );

        document.querySelector('[data-list-btn]')?.addEventListener('click', () => {
            HoshimiStorage.openListModal("<?= htmlspecialchars($slug) ?>", {
                title: "<?= htmlspecialchars($anime['title']) ?>"
            });
        });

        document.addEventListener('DOMContentLoaded', () => {
            // 1. Récupérer toutes les données de progression
            const allProgress = HoshimiStorage.Progress.getAllProgress();

            // 2. Chercher tous les éléments qui ont une clé de progression
            const episodeElements = document.querySelectorAll('[data-ep-key]');

            episodeElements.forEach(el => {
                const epKey = el.dataset.epKey;
                const progress = allProgress[epKey];

                if (progress && progress.duration > 0) {
                    const bar = el.querySelector('.episode-card__progress-fill');
                    if (bar) {
                        let pct = (progress.position / progress.duration) * 100;

                        // Si considéré comme fini (ex: > 92%)
                        if (progress.completed || pct >= 92) {
                            pct = 100;
                            el.classList.add('is-completed'); // Optionnel pour le style
                        }

                        bar.style.width = pct + '%';
                    }
                }
            });
        });
    </script>

</body>

</html>