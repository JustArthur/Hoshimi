<?php

/**
 * render_catalog_sidebar()
 *
 * Renders: <div class="sidebar-wrapper"> (first grid column)
 *   containing the mobile toggle button, overlay, and the aside panel.
 *
 * $cfg keys:
 *  base_url     string  e.g. '/animes/'
 *  sort         string  current sort mode ('alpha' = default)
 *  year         string  current year filter ('' = none)
 *  genre        string  current genre filter ('' = none)
 *  years        array   list of available years (desc)
 *  genres       array   list of available genre strings
 *  sort_options array   [value => label] — optional override
 */
function render_catalog_sidebar(array $cfg): string
{
    $base         = rtrim($cfg['base_url'], '/') . '/';
    $sortMode     = $cfg['sort']    ?? 'alpha';
    $yearFilter   = $cfg['year']    ?? '';
    $genreFilter  = $cfg['genre']   ?? '';
    $langFilter   = $cfg['lang']    ?? '';
    $localFilter  = $cfg['local']   ?? '';
    $allYears     = $cfg['years']   ?? [];
    $allGenres    = $cfg['genres']  ?? [];
    $allLangs     = $cfg['langs']   ?? [];
    $sortOptions  = $cfg['sort_options'] ?? [
        'alpha'    => 'A → Z',
        'z_a'      => 'Z → A',
        'year'     => 'Année (récent)',
        'year_asc' => 'Année (ancien)',
        'score'    => 'Note',
        'recent'   => 'Récemment ajouté',
    ];

    $activeFilters = ($genreFilter !== '' ? 1 : 0) + ($yearFilter !== '' ? 1 : 0) + ($langFilter !== '' ? 1 : 0) + ($localFilter !== '' ? 1 : 0);

    $extraParams  = $cfg['extra_params'] ?? [];
    $u = function (array $over) use ($base, $sortMode, $yearFilter, $genreFilter, $langFilter, $localFilter, $extraParams): string {
        $params = array_filter(array_merge($extraParams, [
            'genre'   => $genreFilter,
            'year'    => $yearFilter,
            'lang'    => $langFilter,
            'local'   => $localFilter,
            'sort'    => $sortMode !== 'alpha' ? $sortMode : '',
        ], $over), fn($v) => $v !== '');
        $qs = http_build_query($params);
        return htmlspecialchars($base . ($qs ? '?' . $qs : ''));
    };

    ob_start();
?>
<div class="sidebar-wrapper">

    <button class="sidebar-mobile-btn" id="sidebar-open-btn" aria-label="Ouvrir les filtres">
        <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round">
            <line x1="4" y1="6" x2="20" y2="6"/><line x1="8" y1="12" x2="20" y2="12"/><line x1="12" y1="18" x2="20" y2="18"/>
        </svg>
        Filtres<?php if ($activeFilters > 0): ?><span class="sidebar-mobile-btn__badge"><?= $activeFilters ?></span><?php endif; ?>
    </button>

    <div class="sidebar-overlay" id="sidebar-overlay"></div>

    <aside class="catalog-sidebar" id="catalog-sidebar">

        <div class="sidebar-header">
            <span class="sidebar-header__title">Filtres</span>
            <?php if ($genreFilter || $yearFilter || $langFilter || $localFilter !== '' || $sortMode !== 'alpha'): ?>
            <a href="<?= htmlspecialchars($base) ?>" class="sidebar-reset">Réinitialiser</a>
            <?php endif; ?>
            <button class="sidebar-close" id="sidebar-close-btn" aria-label="Fermer">
                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round">
                    <line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/>
                </svg>
            </button>
        </div>

        <div class="sidebar-section" data-section>
            <button class="sidebar-section__toggle" aria-expanded="true" data-toggle>
                <span>Tri</span>
                <svg class="sidebar-chevron" width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round"><polyline points="6 9 12 15 18 9"/></svg>
            </button>
            <div class="sidebar-section__body">
                <?php foreach ($sortOptions as $val => $lbl): ?>
                <a href="<?= $u(['sort' => $val !== 'alpha' ? $val : '', 'page' => '']) ?>"
                   class="sidebar-option <?= $sortMode === $val ? 'is-active' : '' ?>">
                    <?= htmlspecialchars($lbl) ?>
                </a>
                <?php endforeach; ?>
            </div>
        </div>

        <?php if (!empty($allGenres)): ?>
        <div class="sidebar-section" data-section>
            <button class="sidebar-section__toggle" aria-expanded="<?= $genreFilter !== '' ? 'true' : 'false' ?>" data-toggle>
                <span>Genres<?php if ($genreFilter !== ''): ?><span class="sidebar-active-dot"></span><?php endif; ?></span>
                <svg class="sidebar-chevron" width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round"><polyline points="6 9 12 15 18 9"/></svg>
            </button>
            <div class="sidebar-section__body" <?= $genreFilter === '' ? 'hidden' : '' ?>>
                <a href="<?= $u(['genre' => '', 'page' => '']) ?>"
                   class="sidebar-option <?= $genreFilter === '' ? 'is-active' : '' ?>">Tous</a>
                <?php foreach ($allGenres as $genre): ?>
                <a href="<?= $u(['genre' => $genre, 'page' => '']) ?>"
                   class="sidebar-option <?= $genreFilter === $genre ? 'is-active' : '' ?>">
                    <?= htmlspecialchars($genre) ?>
                </a>
                <?php endforeach; ?>
            </div>
        </div>
        <?php endif; ?>

<?php if (!empty($allLangs)): ?>
        <div class="sidebar-section" data-section>
            <button class="sidebar-section__toggle" aria-expanded="<?= $langFilter !== '' ? 'true' : 'false' ?>" data-toggle>
                <span>Langue<?php if ($langFilter !== ''): ?><span class="sidebar-active-dot"></span><?php endif; ?></span>
                <svg class="sidebar-chevron" width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round"><polyline points="6 9 12 15 18 9"/></svg>
            </button>
            <div class="sidebar-section__body" <?= $langFilter === '' ? 'hidden' : '' ?>>
                <a href="<?= $u(['lang' => '', 'page' => '']) ?>"
                   class="sidebar-option <?= $langFilter === '' ? 'is-active' : '' ?>">Toutes</a>
                <?php foreach ($allLangs as $lang): ?>
                <a href="<?= $u(['lang' => $lang, 'page' => '']) ?>"
                   class="sidebar-option <?= strtoupper($langFilter) === $lang ? 'is-active' : '' ?>">
                    <?= htmlspecialchars($lang) ?>
                </a>
                <?php endforeach; ?>
            </div>
        </div>
        <?php endif; ?>

        <div class="sidebar-section" data-section>
            <button class="sidebar-section__toggle" aria-expanded="<?= $localFilter !== '' ? 'true' : 'false' ?>" data-toggle>
                <span>Disponibilité<?php if ($localFilter !== ''): ?><span class="sidebar-active-dot"></span><?php endif; ?></span>
                <svg class="sidebar-chevron" width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round"><polyline points="6 9 12 15 18 9"/></svg>
            </button>
            <div class="sidebar-section__body" <?= $localFilter === '' ? 'hidden' : '' ?>>
                <a href="<?= $u(['local' => '', 'page' => '']) ?>"
                   class="sidebar-option <?= $localFilter === '' ? 'is-active' : '' ?>">Tous</a>
                <a href="<?= $u(['local' => '1', 'page' => '']) ?>"
                   class="sidebar-option <?= $localFilter === '1' ? 'is-active' : '' ?>">
                    <svg width="11" height="11" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" style="margin-right:5px;color:var(--color-accent)"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="7 10 12 15 17 10"/><line x1="12" y1="15" x2="12" y2="3"/></svg>Disponible en local
                </a>
                <a href="<?= $u(['local' => '0', 'page' => '']) ?>"
                   class="sidebar-option <?= $localFilter === '0' ? 'is-active' : '' ?>">
                    <svg width="11" height="11" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" style="margin-right:5px;color:var(--color-text-muted)"><circle cx="12" cy="12" r="10"/><line x1="15" y1="9" x2="9" y2="15"/><line x1="9" y1="9" x2="15" y2="15"/></svg>Non disponible
                </a>
            </div>
        </div>

        <?php if (!empty($allYears)): ?>
        <div class="sidebar-section" data-section>
            <button class="sidebar-section__toggle" aria-expanded="<?= $yearFilter !== '' ? 'true' : 'false' ?>" data-toggle>
                <span>Année<?php if ($yearFilter !== ''): ?><span class="sidebar-active-dot"></span><?php endif; ?></span>
                <svg class="sidebar-chevron" width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round"><polyline points="6 9 12 15 18 9"/></svg>
            </button>
            <div class="sidebar-section__body" <?= $yearFilter === '' ? 'hidden' : '' ?>>
                <a href="<?= $u(['year' => '', 'page' => '']) ?>"
                   class="sidebar-option <?= $yearFilter === '' ? 'is-active' : '' ?>">Toutes</a>
                <?php foreach ($allYears as $yr): ?>
                <a href="<?= $u(['year' => (string)$yr, 'page' => '']) ?>"
                   class="sidebar-option <?= $yearFilter === (string)$yr ? 'is-active' : '' ?>">
                    <?= $yr ?>
                </a>
                <?php endforeach; ?>
            </div>
        </div>
        <?php endif; ?>

    </aside>
</div>

<script>
(function () {
    const sidebar  = document.getElementById('catalog-sidebar');
    const overlay  = document.getElementById('sidebar-overlay');
    const openBtn  = document.getElementById('sidebar-open-btn');
    const closeBtn = document.getElementById('sidebar-close-btn');

    function open()  { sidebar.classList.add('is-open');  overlay.classList.add('is-visible'); document.documentElement.style.overflow = 'hidden'; document.body.style.overflow = 'hidden'; }
    function close() { sidebar.classList.remove('is-open'); overlay.classList.remove('is-visible'); document.documentElement.style.overflow = ''; document.body.style.overflow = ''; }

    openBtn?.addEventListener('click', open);
    closeBtn?.addEventListener('click', close);
    overlay?.addEventListener('click', close);

    document.querySelectorAll('[data-toggle]').forEach(btn => {
        btn.addEventListener('click', () => {
            const expanded = btn.getAttribute('aria-expanded') === 'true';
            btn.setAttribute('aria-expanded', String(!expanded));
            const body = btn.closest('[data-section]').querySelector('.sidebar-section__body');
            body.hidden = expanded;
        });
    });
})();
</script>
<?php
    return ob_get_clean();
}
