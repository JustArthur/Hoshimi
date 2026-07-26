<?php

declare(strict_types=1);

$current_path = '/search';
$query = trim($_GET['q'] ?? '');

require_once __DIR__ . '/../../app/IndexManager.php';
require_once __DIR__ . '/../components/card.php';

$index = [];

foreach (IndexManager::getAll('anime') as $a) {
    $index[] = array_merge($a, ['type' => 'anime']);
}
foreach (IndexManager::getAll('serie') as $s) {
    $index[] = array_merge($s, ['type' => 'serie']);
}
foreach (IndexManager::getAll('film') as $f) {
    $index[] = array_merge($f, ['type' => 'film']);
}
$results = [];

if ($query !== '') {
    $q = mb_strtolower($query);
    foreach ($index as $item) {
        $haystack = mb_strtolower(implode(' ', array_filter([
            $item['title']         ?? '',
            $item['title_english'] ?? '',
            $item['title_romaji']  ?? '',
        ])));
        if (str_contains($haystack, $q)) {
            $results[] = $item;
        }
    }
}

$typeOrder  = ['anime', 'serie', 'film'];
$typeLabels = ['anime' => 'Animes', 'serie' => 'Séries', 'film' => 'Films'];

$byType = array_fill_keys($typeOrder, []);
foreach ($results as $r) {
    $t = $r['type'] ?? 'anime';
    if (isset($byType[$t])) $byType[$t][] = $r;
}

$totalCount = count($results);
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Recherche — Hoshimi</title>
    <link rel="stylesheet" href="/css/hoshimi.css">
    <script src="/js/storage.js"></script>
</head>
<body>
    <?php include __DIR__ . '/../components/navbar.php'; ?>

    <main>
        <div class="container" style="padding-top: 32px; padding-bottom: 60px;">

            <?php if ($query === '') : ?>
                <div class="search-empty">
                    <?= lc('search', 40) ?>
                    <p>Entrez un titre dans la barre de recherche.</p>
                </div>

            <?php elseif (empty($results)) : ?>
                <div class="search-empty">
                    <?= lc('search-x', 40) ?>
                    <p>Aucun résultat pour <strong style="color:var(--color-text)">«&nbsp;<?= htmlspecialchars($query) ?>&nbsp;»</strong></p>
                </div>

            <?php else : ?>

                <!-- Header + onglets filtres -->
                <div class="srp-header">
                    <p class="srp-header__count">
                        <span class="srp-header__num"><?= $totalCount ?></span>
                        résultat<?= $totalCount > 1 ? 's' : '' ?> pour
                        <em>«&nbsp;<?= htmlspecialchars($query) ?>&nbsp;»</em>
                    </p>

                    <div class="srp-tabs" role="tablist" id="srp-tabs">
                        <button class="srp-tab is-active" data-filter="all" role="tab" aria-selected="true">
                            Tous
                            <span class="srp-tab__count" id="srp-count-all"><?= $totalCount ?></span>
                        </button>
                        <?php foreach ($typeOrder as $type) : ?>
                            <?php if (empty($byType[$type])) continue; ?>
                            <button class="srp-tab" data-filter="<?= $type ?>" role="tab" aria-selected="false"
                                >
                                <?= $typeLabels[$type] ?>
                                <span class="srp-tab__count"><?= count($byType[$type]) ?></span>
                            </button>
                        <?php endforeach; ?>
                    </div>
                </div>

                <!-- Sections par type -->
                <?php foreach ($typeOrder as $type) : ?>
                    <?php if (empty($byType[$type])) continue; ?>
                    <section class="srp-section"
                             data-section="<?= $type ?>">

                        <div class="srp-section__header">
                            <span class="srp-type-label srp-type-label--<?= $type ?>">
                                <?= match($type) {
                                    'anime'  => lc('tv-2', 13),
                                    'serie'  => lc('tv', 13),
                                    'film'   => lc('clapperboard', 13),
                                    default  => lc('play-circle', 13),
                                } ?>
                                <?= $typeLabels[$type] ?>
                            </span>
                            <span class="srp-section__count"><?= count($byType[$type]) ?></span>
                        </div>

                        <div class="grid-cards">
                            <?php foreach ($byType[$type] as $item) : ?>
                                <?= render_card($item, $type) ?>
                            <?php endforeach; ?>
                        </div>

                    </section>
                <?php endforeach; ?>

            <?php endif; ?>

        </div>
    </main>

    <?php include __DIR__ . '/../components/footer.php'; ?>
    <script src="/js/guard.js"></script>
    <script>
    (function () {
        const tabs     = document.querySelectorAll('.srp-tab');
        const sections = document.querySelectorAll('.srp-section');
        if (!tabs.length) return;

        tabs.forEach(tab => {
            tab.addEventListener('click', () => {
                tabs.forEach(t => { t.classList.remove('is-active'); t.setAttribute('aria-selected', 'false'); });
                tab.classList.add('is-active');
                tab.setAttribute('aria-selected', 'true');
                const filter = tab.dataset.filter;
                sections.forEach(section => {
                    section.hidden = filter !== 'all' && section.dataset.section !== filter;
                });
            });
        });
    })();
    </script>
</body>
</html>
