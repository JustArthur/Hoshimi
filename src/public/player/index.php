<?php
// ============================================================
//  HOSHIMI — Page lecteur vidéo
//  URL : /player/?slug=One+Piece&saison=1&episode=1
// ============================================================

declare(strict_types=1);

require_once __DIR__ . '/../../app/Services/AnimeScanner.php';

$scanner = new AnimeScanner();

$slug          = $_GET['slug']    ?? '';
$seasonNumber  = max(1, (int) ($_GET['saison']  ?? 1));
$episodeNumber = max(1, (int) ($_GET['episode'] ?? 1));

$anime = $slug ? $scanner->getAnimeBySlug($slug) : null;

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

// Épisode précédent / suivant
$prevEpisode = $season['episodes'][$episodeIdx - 1] ?? null;
$nextEpisode = $season['episodes'][$episodeIdx + 1] ?? null;

// URLs de navigation
$baseUrl = '/player/?slug=' . urlencode($slug) . '&saison=' . $seasonNumber;
$prevUrl = $prevEpisode ? $baseUrl . '&episode=' . $prevEpisode['number'] : null;
$nextUrl = $nextEpisode ? $baseUrl . '&episode=' . $nextEpisode['number'] : null;

// URL stream
$streamUrl = '/stream/index.php?path=' . rawurlencode($episode['file_path']);

// Clé unique pour la progression
$progressKey = md5($episode['file_path']);

// Accent couleur
$accentColor = $anime['metadata']['coverImage']['color'] ?? '#F7D622';
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

    <link rel="stylesheet" href="../css/hoshimi_base.css">
    <link rel="stylesheet" href="../css/hoshimi_player.css">

    <!-- Video.js -->
    <link href="https://cdnjs.cloudflare.com/ajax/libs/video.js/8.10.0/video-js.min.css" rel="stylesheet">
    <script src="https://cdnjs.cloudflare.com/ajax/libs/video.js/8.10.0/video.min.js"></script>
</head>

<body>
    <div class="player-page">

        <!-- ============================================================
       NAVBAR
       ============================================================ -->
        <?php include "../components/navbar.php"; ?>

        <main>

            <!-- ============================================================
         LECTEUR VIDÉO
         ============================================================ -->
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
                    <?php $ext = strtolower(pathinfo($episode['file_path'], PATHINFO_EXTENSION)); ?>
                    <source src="<?= htmlspecialchars($streamUrl) ?>" type="video/mp4">
                    <p class="vjs-no-js">Votre navigateur ne supporte pas la lecture vidéo.</p>
                </video>
            </div>

            <!-- ============================================================
         INFOS ÉPISODE
         ============================================================ -->
            <div class="player-info">
                <div class="player-info__inner">

                    <a href="/anime/?slug=<?= urlencode($slug) ?>" class="player-info__back">
                        ← Retour
                    </a>

                    <div class="player-info__titles">
                        <div class="player-info__anime">
                            <?= htmlspecialchars($anime['title']) ?>
                            · <?= htmlspecialchars($season['label']) ?>
                        </div>

                        <div class="player-info__episode">
                            <?php if ($season['number'] == 999) : ?>
                                🎬
                                <?php
                                // On nettoie le titre du film s'il contient des tags techniques
                                $cleanFilmTitle = $episode['title'];
                                $search = ['1080p', '720p', 'MULTISUB', 'MULTI', 'FILM', 'x264', 'x265'];
                                $cleanFilmTitle = str_ireplace($search, '', $cleanFilmTitle);
                                $cleanFilmTitle = trim($cleanFilmTitle, " _-");

                                // Si le titre nettoyé est le même que le nom de l'anime, on met juste "Film"
                                if (empty($cleanFilmTitle) || strtolower($cleanFilmTitle) == strtolower($anime['title'])) {
                                    echo "Film";
                                } else {
                                    echo htmlspecialchars($cleanFilmTitle);
                                }
                                ?>
                            <?php else : ?>
                                Épisode <?= str_pad((string)$episode['number'], 2, '0', STR_PAD_LEFT) ?>
                                — <?= htmlspecialchars($episode['title']) ?>
                            <?php endif; ?>
                        </div>

                        <div class="player-info__meta">
                            <?php if (!empty($episode['quality'])) : ?>
                                <span><?= strtoupper(htmlspecialchars($episode['quality'])) ?></span>
                            <?php endif; ?>
                            <?php if (!empty($episode['language'])) : ?>
                                <span><?= htmlspecialchars($episode['language']) ?></span>
                            <?php endif; ?>
                            <span><?= htmlspecialchars(strtoupper(pathinfo($episode['filename'], PATHINFO_EXTENSION))) ?></span>
                        </div>
                    </div>

                    <div class="player-info__nav">
                        <?php if ($prevUrl) : ?>
                            <a href="<?= htmlspecialchars($prevUrl) ?>" class="btn btn--ghost" title="Précédent">← Préc.</a>
                        <?php endif; ?>
                        <?php if ($nextUrl) : ?>
                            <a href="<?= htmlspecialchars($nextUrl) ?>" class="btn btn--primary" title="Suivant">Suiv. →</a>
                        <?php endif; ?>
                    </div>

                </div>
            </div>

            <!-- ============================================================
         CORPS — Infos + Playlist
         ============================================================ -->
            <div class="player-body">

                <!-- Infos anime -->
                <div>
                    <div class="section-header">
                        <h2 class="section-header__title" style="font-size:1rem;">
                            À propos
                        </h2>
                        <a href="/anime/?slug=<?= urlencode($slug) ?>" class="section-header__link">
                            Voir la page complète →
                        </a>
                    </div>

                    <?php if (!empty($anime['genres'])) : ?>
                        <div style="display:flex; flex-wrap:wrap; gap:6px; margin-bottom:16px;">
                            <?php foreach ($anime['genres'] as $genre) : ?>
                                <span class="badge"><?= htmlspecialchars($genre) ?></span>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>

                    <?php
                    $synopsis = $anime['synopsis']
                        ? strip_tags(str_replace(['<br>', '<br/>'], "\n", $anime['synopsis']))
                        : null;
                    ?>
                    <?php if ($synopsis) : ?>
                        <p style="font-size:0.9rem; color:var(--color-text-soft); line-height:1.8;">
                            <?= nl2br(htmlspecialchars(mb_substr($synopsis, 0, 400))) ?>
                            <?= mb_strlen($synopsis) > 400 ? '…' : '' ?>
                        </p>
                    <?php endif; ?>
                </div>

                <!-- Playlist -->
                <aside>
                    <div class="playlist">
                        <div class="playlist__header">
                            <?= htmlspecialchars($season['label']) ?>
                            — <?= count($season['episodes']) ?> épisodes
                        </div>
                        <div class="playlist__list" id="playlist-list">
                            <?php foreach ($season['episodes'] as $ep) : ?>
                                <?php
                                $epUrl     = $baseUrl . '&episode=' . $ep['number'];
                                $isActive  = $ep['number'] === $episode['number'];
                                $epKey     = md5($ep['file_path']);
                                ?>
                                <a
                                    href="<?= htmlspecialchars($epUrl) ?>"
                                    class="playlist__item <?= $isActive ? 'active' : '' ?>"
                                    data-progress-key="<?= $epKey ?>">
                                    <span class="playlist__num">
                                        <?= $isActive ? '▶' : str_pad((string)$ep['number'], 2, '0', STR_PAD_LEFT) ?>
                                    </span>
                                    <span class="playlist__title" title="<?= htmlspecialchars($ep['title']) ?>">
                                        <?= htmlspecialchars($ep['title']) ?>
                                    </span>
                                    <div class="playlist__badges">
                                        <?php if (!empty($ep['quality'])) : ?>
                                            <span class="playlist__badge"><?= htmlspecialchars($ep['quality']) ?></span>
                                        <?php endif; ?>
                                        <?php if (!empty($ep['language'])) : ?>
                                            <span class="playlist__badge"><?= htmlspecialchars($ep['language']) ?></span>
                                        <?php endif; ?>
                                    </div>
                                    <!-- Barre progression (remplie par JS) -->
                                    <div class="playlist__progress" style="width:0%" data-ep-key="<?= $epKey ?>"></div>
                                </a>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </aside>

            </div>
        </main>

    </div>

    <!-- ============================================================
     TOAST ÉPISODE SUIVANT
     ============================================================ -->
    <?php if ($nextEpisode) : ?>
        <div class="next-toast" id="next-toast">
            <div class="next-toast__label">Épisode suivant dans <span id="next-countdown">10</span>s</div>
            <div class="next-toast__title">
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

    <!-- Scripts -->
    <script src="../js/hoshimi_accent.js"></script>
    <script src="../js/hoshimi_storage.js"></script>
    <script>
        // ============================================================
        //  Hoshimi Player — Video.js + progression + épisode suivant
        // ============================================================

        const PROGRESS_KEY = document.getElementById('hoshimi-player').dataset.progressKey;
        const NEXT_URL = document.getElementById('hoshimi-player').dataset.nextUrl;
        const NEXT_TITLE = document.getElementById('hoshimi-player').dataset.nextTitle;
        const SAVE_INTERVAL_SEC = 5; // sauvegarde toutes les 5s
        const NEXT_DELAY_SEC = 10; // délai avant épisode suivant
        const COMPLETE_THRESHOLD = 0.92; // 92% = considéré comme vu

        let player;
        let saveTimer;
        let nextTimer;
        let nextCancelled = false;

        // ---- Init Video.js ----
        player = videojs('hoshimi-player', {
            controls: true,
            autoplay: false,
            preload: 'auto',
            fluid: false,
            responsive: false,
            playbackRates: [0.5, 0.75, 1, 1.25, 1.5, 2],
        });

        // ---- Reprise de la progression ----
        player.ready(function() {
            const data = HoshimiStorage.Progress.get(PROGRESS_KEY);
            if (data.position > 10 && !data.completed) {
                player.currentTime(data.position);
                showResumeNotice(data.position);
            }
        });

        // ---- Sauvegarde périodique ----
        player.on('timeupdate', function() {
            clearTimeout(saveTimer);
            saveTimer = setTimeout(saveProgress, SAVE_INTERVAL_SEC * 1000);

            // Met à jour la barre de progression de la playlist
            const ratio = player.currentTime() / (player.duration() || 1);
            const bar = document.querySelector('[data-ep-key="' + PROGRESS_KEY + '"] .playlist__progress') ||
                document.querySelector('.playlist__item.active .playlist__progress');
            if (bar) bar.style.width = Math.min(ratio * 100, 100) + '%';
        });

        player.on('pause', saveProgress);
        player.on('ended', onVideoEnded);

        // ---- Fin de vidéo ----
        function onVideoEnded() {
            saveProgress(true);
            if (NEXT_URL && !nextCancelled) {
                showNextToast();
            }
        }

        // ---- Sauvegarde progression ----
        function saveProgress(completed = false) {
            HoshimiStorage.Progress.save(
                PROGRESS_KEY,
                player.currentTime(),
                player.duration() || 0,
                completed === true
            );
        }

        // ---- Toast épisode suivant ----
        function showNextToast() {
            const toast = document.getElementById('next-toast');
            const bar = document.getElementById('next-bar');
            const countdown = document.getElementById('next-countdown');
            if (!toast) return;

            toast.classList.add('visible');

            // Countdown visuel
            bar.style.transition = 'none';
            bar.style.width = '100%';
            setTimeout(() => {
                bar.style.transition = `width ${NEXT_DELAY_SEC}s linear`;
                bar.style.width = '0%';
            }, 50);

            let remaining = NEXT_DELAY_SEC;
            const tick = setInterval(() => {
                remaining--;
                if (countdown) countdown.textContent = remaining;
                if (remaining <= 0) {
                    clearInterval(tick);
                    if (!nextCancelled) {
                        window.location.href = NEXT_URL;
                    }
                }
            }, 1000);

            nextTimer = tick;
        }

        function cancelNext() {
            nextCancelled = true;
            clearInterval(nextTimer);
            const toast = document.getElementById('next-toast');
            if (toast) toast.classList.remove('visible');
        }

        // ---- Notice de reprise ----
        function showResumeNotice(position) {
            const mins = Math.floor(position / 60);
            const secs = Math.floor(position % 60).toString().padStart(2, '0');
            const msg = document.createElement('div');
            msg.style.cssText = `
    position:fixed; top:80px; right:24px; z-index:999;
    background:var(--color-bg-elevated);
    border:1px solid var(--color-accent);
    border-radius:var(--radius-md);
    padding:12px 18px; font-size:.85rem;
    box-shadow:var(--shadow-md);
    display:flex; gap:12px; align-items:center;
  `;
            msg.innerHTML = `
    <span>Reprendre à <strong>${mins}:${secs}</strong> ?</span>
    <button onclick="player.play(); this.closest('div').remove();"
      style="background:var(--color-accent);color:#111;border:none;padding:4px 12px;
             border-radius:4px;font-weight:600;cursor:pointer;font-size:.8rem;">
      Reprendre
    </button>
    <button onclick="player.currentTime(0); this.closest('div').remove();"
      style="background:transparent;border:1px solid var(--color-border);color:var(--color-text-muted);
             padding:4px 12px;border-radius:4px;cursor:pointer;font-size:.8rem;">
      Début
    </button>
  `;
            document.body.appendChild(msg);
            setTimeout(() => msg.remove(), 8000);
        }

        // ---- Raccourcis clavier ----
        document.addEventListener('keydown', function(e) {
            // Ignorer si focus sur input
            if (['INPUT', 'TEXTAREA', 'SELECT'].includes(document.activeElement.tagName)) return;

            switch (e.key) {
                case ' ':
                case 'k':
                    e.preventDefault();
                    player.paused() ? player.play() : player.pause();
                    break;
                case 'ArrowRight':
                case 'l':
                    player.currentTime(Math.min(player.currentTime() + 10, player.duration()));
                    break;
                case 'ArrowLeft':
                case 'j':
                    player.currentTime(Math.max(player.currentTime() - 10, 0));
                    break;
                case 'ArrowUp':
                    player.volume(Math.min(player.volume() + 0.1, 1));
                    break;
                case 'ArrowDown':
                    player.volume(Math.max(player.volume() - 0.1, 0));
                    break;
                case 'm':
                    player.muted(!player.muted());
                    break;
                case 'f':
                    player.isFullscreen() ? player.exitFullscreen() : player.requestFullscreen();
                    break;
            }
        });

        // ---- Charge les progressions de la playlist ----
        document.querySelectorAll('.playlist__item[data-progress-key]').forEach(item => {
            const key = item.dataset.progressKey;
            fetch('/api/progress/?file=' + encodeURIComponent(key))
                .then(r => r.json())
                .then(data => {
                    if (data.duration > 0) {
                        const bar = item.querySelector('.playlist__progress');
                        if (bar) {
                            const pct = data.completed ? 100 : (data.position / data.duration) * 100;
                            bar.style.width = Math.min(pct, 100) + '%';
                        }
                    }
                })
                .catch(() => {});
        });

        // ---- Accent couleur depuis les métadonnées PHP ----
        const animeData = <?= json_encode($anime['metadata'] ?? []) ?>;
        setAccentFromAnime(animeData);

        // ---- Scroll playlist sur l'épisode actif (sans scroller la page) ----
        const activeItem = document.querySelector('.playlist__item.active');
        if (activeItem) {
            const playlist = document.getElementById('playlist-list');
            if (playlist) {
                playlist.scrollTop = activeItem.offsetTop - playlist.offsetTop - (playlist.clientHeight / 2);
            }
        }

        document.addEventListener('DOMContentLoaded', () => {
            // 1. On récupère toutes les progressions sauvegardées
            const allProgress = HoshimiStorage.Progress.getAllProgress();

            // 2. On parcourt toutes les barres de progression de la playlist
            document.querySelectorAll('.playlist__progress').forEach(bar => {
                const epKey = bar.dataset.epKey;
                const progress = allProgress[epKey];

                if (progress && progress.duration > 0) {
                    // Calcul du pourcentage
                    let pct = (progress.position / progress.duration) * 100;

                    // Si l'épisode est marqué comme complété, on force 100%
                    if (progress.completed) pct = 100;

                    // Mise à jour visuelle
                    bar.style.width = Math.min(pct, 100) + '%';

                    // Optionnel : ajouter une classe si terminé pour changer la couleur
                    if (pct >= 92) {
                        bar.parentElement.classList.add('is-completed');
                    }
                }
            });
        });
    </script>

    <?php include "../components/footer.php"; ?>
</body>

</html>