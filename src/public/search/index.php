<?php

declare(strict_types=1);

$current_path = '/search';
$query = trim($_GET['q'] ?? '');

// ── Cache index ──────────────────────────────────────────────────────────────

$cacheFile = sys_get_temp_dir() . '/hoshimi_search_index.json';
$cacheTTL  = 300; // 5 minutes

$cacheValid = file_exists($cacheFile) && (time() - filemtime($cacheFile)) < $cacheTTL;

if (!$cacheValid) {
    require_once __DIR__ . '/../../app/Services/AnimeScanner.php';
    require_once __DIR__ . '/../../app/Services/SeriesScanner.php';
    require_once __DIR__ . '/../../app/Services/FilmScanner.php';

    $index = [];

    foreach ((new AnimeScanner())->getAllAnimes() as $a) {
        $index[] = [
            'type'          => 'anime',
            'slug'          => $a['slug'],
            'title'         => $a['title']         ?? '',
            'title_english' => $a['title_english']  ?? '',
            'title_romaji'  => $a['title_romaji']   ?? '',
            'cover_url'     => $a['cover_url']      ?? '',
            'year'          => $a['year']            ?? null,
            'score'         => $a['score']           ?? 0,
            'episodes'      => $a['episodes_local']  ?? 0,
        ];
    }

    foreach ((new SeriesScanner())->getAllSeries() as $s) {
        $index[] = [
            'type'          => 'serie',
            'slug'          => $s['slug'],
            'title'         => $s['title']          ?? '',
            'title_english' => $s['title_english']  ?? '',
            'title_romaji'  => $s['title_romaji']   ?? '',
            'cover_url'     => $s['cover_url']      ?? '',
            'year'          => $s['year']            ?? null,
            'score'         => $s['score']           ?? 0,
            'episodes'      => $s['episodes_local']  ?? 0,
        ];
    }

    foreach ((new FilmScanner())->getAllFilms() as $f) {
        $index[] = [
            'type'          => 'film',
            'slug'          => $f['slug'],
            'title'         => $f['title']    ?? '',
            'title_english' => '',
            'title_romaji'  => '',
            'cover_url'     => $f['cover_url'] ?? '',
            'year'          => $f['year']      ?? null,
            'score'         => $f['score']     ?? 0,
            'runtime'       => $f['runtime']   ?? null,
        ];
    }

    file_put_contents($cacheFile, json_encode($index, JSON_UNESCAPED_UNICODE));
} else {
    $index = json_decode(file_get_contents($cacheFile), true) ?? [];
}

// ── Filtrage ─────────────────────────────────────────────────────────────────

$results = [];

if ($query !== '') {
    $q = mb_strtolower($query);
    foreach ($index as $item) {
        $haystack = mb_strtolower(implode(' ', array_filter([
            $item['title'],
            $item['title_english'],
            $item['title_romaji'],
        ])));
        if (str_contains($haystack, $q)) {
            $results[] = $item;
        }
    }
}

$byType = ['anime' => [], 'serie' => [], 'film' => []];
foreach ($results as $r) {
    $byType[$r['type']][] = $r;
}

$typeLabels = ['anime' => 'Animes', 'serie' => 'Séries', 'film' => 'Films'];

function searchCardUrl(array $item): string
{
    return match ($item['type']) {
        'film'  => '/film/?slug='  . urlencode($item['slug']),
        'serie' => '/anime/?slug=' . urlencode($item['slug']) . '&type=serie',
        default => '/anime/?slug=' . urlencode($item['slug']),
    };
}

function searchScore(array $item): ?string
{
    if (!($item['score'] ?? 0)) return null;
    return number_format((float)$item['score'], 1);
}

function searchRuntime(?int $mins): string
{
    if (!$mins) return 'Film';
    $h = (int)floor($mins / 60); $m = $mins % 60;
    return $m > 0 ? "{$h}h {$m}m" : "{$h}h";
}

require_once __DIR__ . '/../components/card.php';
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $query ? htmlspecialchars($query) . ' — Recherche' : 'Recherche' ?> — Hoshimi</title>
    <link rel="stylesheet" href="/css/hoshimi.css">
</head>
<body>
    <?php include __DIR__ . '/../components/navbar.php'; ?>

    <main>
        <div class="container" style="padding-top: 32px; padding-bottom: 60px;">

            <?php if ($query === '') : ?>
                <div style="text-align:center; padding: 80px 0; color: var(--color-text-muted);">
                    <p style="font-size:3rem; margin-bottom:16px;">🔍</p>
                    <p>Entrez un titre dans la barre de recherche.</p>
                </div>

            <?php elseif (empty($results)) : ?>
                <div style="text-align:center; padding: 80px 0; color: var(--color-text-muted);">
                    <p style="font-size:3rem; margin-bottom:16px;">🔭</p>
                    <p>Aucun résultat pour <strong style="color:var(--color-text)">«&nbsp;<?= htmlspecialchars($query) ?>&nbsp;»</strong></p>
                </div>

            <?php else : ?>
                <div class="section-header" style="margin-bottom:32px;">
                    <h1 class="section-header__title" style="font-size:1.25rem;">
                        <?= count($results) ?> résultat<?= count($results) > 1 ? 's' : '' ?> pour
                        <span style="color:var(--color-accent)">«&nbsp;<?= htmlspecialchars($query) ?>&nbsp;»</span>
                    </h1>
                </div>

                <?php foreach ($byType as $type => $items) : ?>
                    <?php if (empty($items)) continue; ?>
                    <section style="margin-bottom:48px;">
                        <div class="section-header" style="margin-bottom:16px;">
                            <h2 class="section-header__title" style="font-size:1rem; color:var(--color-text-muted);">
                                <?= $typeLabels[$type] ?>
                                <span style="font-size:0.85rem; margin-left:8px;">(<?= count($items) ?>)</span>
                            </h2>
                        </div>
                        <div class="grid-cards">
                            <?php foreach ($items as $item) : ?>
                                <?= render_card($item, $type, ['href' => searchCardUrl($item)]) ?>
                            <?php endforeach; ?>
                        </div>
                    </section>
                <?php endforeach; ?>
            <?php endif; ?>

        </div>
    </main>

    <?php include __DIR__ . '/../components/footer.php'; ?>
    <script src="/js/hoshimi_storage.js"></script>
</body>
</html>
