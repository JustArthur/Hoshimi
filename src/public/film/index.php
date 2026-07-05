<?php

declare(strict_types=1);

require_once __DIR__ . '/../../app/Services/FilmScanner.php';

$scanner = new FilmScanner();
$slug    = $_GET['slug'] ?? '';
$film    = $slug ? $scanner->getFilmBySlug($slug) : null;

if (!$film) {
    http_response_code(404);
    header('Location: /films/');
    exit;
}


$hasFile  = $film['has_file'] ?? true;
$episode  = $film['seasons'][0]['episodes'][0] ?? null;
$watchUrl = '/player/?type=film&slug=' . urlencode($film['slug']);

$thumbDir  = ($_SERVER['DOCUMENT_ROOT'] ?? '') . '/images/thumbnails/';
$index     = file_exists($thumbDir . 'index.json')     ? (json_decode(file_get_contents($thumbDir . 'index.json'),     true) ?: []) : [];
$durations = file_exists($thumbDir . 'durations.json') ? (json_decode(file_get_contents($thumbDir . 'durations.json'), true) ?: []) : [];
$sizes     = file_exists($thumbDir . 'filesizes.json') ? (json_decode(file_get_contents($thumbDir . 'filesizes.json'), true) ?: []) : [];

$epKey    = $episode ? md5($episode['file_path']) : null;
$thumb    = $epKey ? ($index[$epKey] ?? null) : null;
$dur      = $epKey ? ($durations[$epKey] ?? null) : null;
$filesize = $epKey ? ($sizes[$epKey] ?? null) : null;

$heroTitle = htmlspecialchars($film['title']);
$synopsis  = $film['synopsis'] ?? null;
$current_path = '/films';

// Logo TMDB si disponible
$heroLogo = null;
$metaRaw  = $film['metadata'] ?? [];
if (!empty($metaRaw['images']['logos'])) {
    $logos = $metaRaw['images']['logos'];
    usort($logos, fn($a, $b) => (($b['iso_639_1'] ?? '') === 'en') <=> (($a['iso_639_1'] ?? '') === 'en'));
    $logoEntry = $logos[0];
    $heroLogo  = $logoEntry['urls']['w500'] ?? $logoEntry['urls']['original'] ?? null;
    if (!$heroLogo && !empty($logoEntry['file_path'])) {
        $heroLogo = 'https://image.tmdb.org/t/p/w500' . $logoEntry['file_path'];
    }
}

$heroSplit = !$heroLogo && !empty($film['cover_url']);

require_once __DIR__ . '/../components/card.php';
require_once __DIR__ . '/../components/cast.php';
require_once __DIR__ . '/../components/tabs.php';

$runtimeStr = null;
if ($film['runtime'] ?? null) {
    $min = (int)$film['runtime'];
    $runtimeStr = $min < 60 ? "{$min} min" : floor($min / 60) . 'h' . ($min % 60 ? ' ' . ($min % 60) . 'min' : '');
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $heroTitle ?> — Hoshimi</title>
    <link rel="stylesheet" href="/css/hoshimi.css">
</head>
<body>
    <?php include __DIR__ . '/../components/navbar.php'; ?>

    <main>

        <!-- ══════════════════ HERO BANNER ══════════════════ -->
        <section class="detail-hero">
            <div class="detail-hero__backdrop" style="background-image: url('<?= htmlspecialchars($film['banner_url'] ?? $film['cover_url'] ?? '') ?>')"></div>
            <div class="detail-hero__gradient"></div>
            <?php if (!$heroSplit && !empty($film['cover_url'])): ?>
                <img data-cover-img src="<?= htmlspecialchars($film['cover_url']) ?>" alt="" style="display:none" crossorigin="anonymous">
            <?php endif; ?>

            <div class="detail-hero__container">
            <div class="detail-hero__content<?= $heroSplit ? ' detail-hero__content--split' : '' ?>">

                <?php if ($heroSplit): ?>
                <div class="detail-hero__poster-col">
                    <img class="detail-hero__poster-img"
                         src="<?= htmlspecialchars($film['cover_url']) ?>"
                         alt="<?= $heroTitle ?>"
                         data-cover-img>
                </div>
                <div class="detail-hero__info-col">
                <?php endif; ?>

                    <!-- Badges -->
                    <div class="detail-hero__badges">
                        <?php if (!$hasFile): ?>
                        <span class="badge badge--soon">Prochainement</span>
                        <?php endif; ?>
                        <?php if ($film['score'] ?? null): ?>
                            <span class="badge badge--score-hero">★ <?= $film['score'] ?></span>
                        <?php endif; ?>
                    </div>

                    <?php if ($heroLogo): ?>
                        <img class="detail-hero__logo" src="<?= htmlspecialchars($heroLogo) ?>" alt="<?= $heroTitle ?>">
                    <?php else: ?>
                        <h1 class="detail-hero__title"><?= $heroTitle ?></h1>
                    <?php endif; ?>

                    <!-- Méta -->
                    <div class="detail-hero__meta">
                        <span class="detail-hero__meta-type">Film</span>
                        <?php if ($film['year'] ?? null): ?>
                            <span class="detail-hero__meta-sep">•</span>
                            <span><?= $film['year'] ?></span>
                        <?php endif; ?>
                        <?php if ($runtimeStr): ?>
                            <span class="detail-hero__meta-sep">•</span>
                            <span><?= $runtimeStr ?></span>
                        <?php endif; ?>
                        <?php if ($episode && $episode['language']): ?>
                            <span class="detail-hero__meta-sep">•</span>
                            <span><?= htmlspecialchars(strtoupper($episode['language'])) ?></span>
                        <?php endif; ?>
                    </div>

                    <!-- Genres -->
                    <?php if (!empty($film['genres'])): ?>
                    <div class="detail-genres">
                        <?php foreach ($film['genres'] as $g): ?>
                            <span class="badge"><?= htmlspecialchars($g) ?></span>
                        <?php endforeach; ?>
                    </div>
                    <?php endif; ?>

                    <!-- Actions -->
                    <div class="detail-hero__actions">
                        <?php if ($hasFile): ?>
                        <a href="<?= htmlspecialchars($watchUrl) ?>" class="btn btn--primary">▶ Regarder</a>
                        <?php endif; ?>
                        <button class="btn btn--ghost" data-fav-btn
                                data-slug="<?= htmlspecialchars($film['slug']) ?>">
                            ♡ Favoris
                        </button>
                    </div>

                <?php if ($heroSplit): ?>
                </div><!-- /.detail-hero__info-col -->
                <?php endif; ?>

            </div>
            </div>
        </section>

        <?php
        $cast = $film['metadata']['credits']['cast'] ?? [];
        ?>

        <?php
        // ── Suggestions ───────────────────────────────────────────────────────
        $currentGenres = array_unique($film['genres'] ?? []);
        $similaires    = [];
        if (!empty($currentGenres)) {
            $scored = [];
            foreach ($scanner->getAllFilms() as $f) {
                if ($f['slug'] === $film['slug']) continue;
                $itemGenres = array_unique($f['genres'] ?? []);
                $common     = array_values(array_intersect($currentGenres, $itemGenres));
                if (empty($common)) continue;
                $union      = array_unique(array_merge($currentGenres, $itemGenres));
                $jaccard    = count($common) / count($union) * 4.0;
                $sA = $film['score'] ?? null; $sB = $f['score'] ?? null;
                $scoreBonus = ($sA !== null && $sB !== null) ? max(0.0, 1 - abs($sA - $sB) / 3) * 1.5 : 0.0;
                $yA = $film['year'] ?? null;  $yB = $f['year']  ?? null;
                $yearBonus  = ($yA !== null && $yB !== null) ? max(0.0, 1 - abs($yA - $yB) / 10) * 1.0 : 0.0;
                $scored[] = ['item' => $f, 'total' => $jaccard + $scoreBonus + $yearBonus, 'match_genres' => array_slice($common, 0, 3)];
            }
            usort($scored, fn($a, $b) => $b['total'] <=> $a['total']);
            $similaires = array_slice($scored, 0, 12);
        }
        ?>

        <!-- ══════════════════ TABS ══════════════════ -->
        <?= render_tabs_bar([
            ['id' => 'synopsis',    'label' => 'Synopsis',    'icon' => 'book',     'active' => true],
            ['id' => 'casting',     'label' => 'Casting',     'icon' => 'cast',     'active' => false],
            ['id' => 'suggestions', 'label' => 'Suggestions', 'icon' => 'sparkles', 'active' => false, 'hidden' => empty($similaires)],
        ]) ?>

        <!-- ══════════════════ BODY ══════════════════ -->
        <div class="detail-body">

            <!-- ── Panel : Synopsis (actif par défaut) ── -->
            <div class="detail-tab-panel" id="tab-synopsis">
                <?php if ($synopsis): ?>
                <div class="synopsis-section">
                    <p class="synopsis"><?= htmlspecialchars($synopsis) ?></p>
                </div>
                <?php else: ?>
                    <p style="color:var(--color-text-muted); font-size:.9rem;">Aucun synopsis disponible.</p>
                <?php endif; ?>
            </div>

            <!-- ── Panel : Casting ── -->
            <div class="detail-tab-panel" id="tab-casting" hidden>
                <?= render_cast_grid($cast, 'Aucune donnée de casting disponible. Relancez tmdb_fetch.py avec --force pour enrichir les métadonnées.') ?>
            </div>

            <!-- ── Panel : Suggestions ── -->
            <?php if (!empty($similaires)): ?>
            <div class="detail-tab-panel" id="tab-suggestions" hidden>
                <div class="suggestions-section">
                    <div class="grid-cards">
                        <?php foreach ($similaires as $simData): ?>
                            <?= render_card($simData['item'], 'film', [
                                'match_genres' => $simData['match_genres'],
                            ]) ?>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
            <?php endif; ?>

        </div>

    </main>

    <?php include __DIR__ . '/../components/footer.php'; ?>

    <script src="/js/hoshimi_accent.js"></script>
    <script src="/js/hoshimi_storage.js"></script>
    <script src="/js/hoshimi_tabs.js"></script>
    <script>
        if (typeof setAccentFromAnime === 'function') {
            const filmData = <?= json_encode($film['metadata'] ?? []) ?>;
            if (document.readyState === 'loading') {
                document.addEventListener('DOMContentLoaded', () => setAccentFromAnime(filmData));
            } else {
                setAccentFromAnime(filmData);
            }
        }

        HoshimiStorage.initFavoriteButton(
            document.querySelector('[data-fav-btn]'),
            <?= json_encode($film['slug']) ?>, {
                title:     <?= json_encode($film['title']) ?>,
                cover_url: <?= json_encode($film['cover_url'] ?? '') ?>,
                type:      'film',
            }
        );
    </script>
</body>
</html>
