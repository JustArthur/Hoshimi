<?php

declare(strict_types=1);

require_once __DIR__ . '/../../app/MacGuard.php';
require_once __DIR__ . '/../components/icon.php';
MacGuard::protect();

$mediaTypes = [
    'anime' => ['root' => $_ENV['ANIMES_PATH'] ?? getenv('ANIMES_PATH') ?: '/media/animes', 'label' => 'Anime'],
    'serie' => ['root' => $_ENV['SERIES_PATH'] ?? getenv('SERIES_PATH') ?: '/media/series', 'label' => 'Série'],
];

?>
<!doctype html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Renommage — Hoshimi Admin</title>
    <link rel="stylesheet" href="/css/hoshimi.css">
    <link rel="stylesheet" href="/css/pages/admin.css">
    <script src="/js/storage.js"></script>
    <script src="/js/toast.js"></script>
</head>
<body>
    <?php include __DIR__ . '/../components/navbar.php'; ?>

    <main class="admin-page">

        <div class="admin-page__header">
        <div class="section-header section-header--simple" style="margin-bottom:0">
            <div class="section-header__bar"></div>
            <div class="section-header__inner">
                <span class="section-header__type">Outils</span>
                <h1 class="section-header__title" style="font-size:1.4rem">Renommage en lot</h1>
            </div>
        </div>
            <?php include __DIR__ . '/_nav.php'; ?>
        </div>

        <div class="loader" id="loader">
            <div class="loader__ring"></div>
            <span class="loader__label">Chargement des médias…</span>
        </div>

        <div class="adm-content" id="adm-content" hidden>
<?php
if (ob_get_level()) { ob_end_flush(); } flush();

$allMedia = [];
foreach ($mediaTypes as $type => $info) {
    $root = $info['root'];
    if (!is_dir($root)) continue;
    foreach (array_diff(scandir($root), ['.', '..']) as $dir) {
        $dirPath = $root . DIRECTORY_SEPARATOR . $dir;
        if (!is_dir($dirPath)) continue;
        $meta = @json_decode(@file_get_contents($dirPath . '/metadata.json') ?: '{}', true) ?: [];
        $coverUrl = $meta['cover'] ?? $meta['cover_url'] ?? '';
        if (!$coverUrl) {
            foreach (['cover.jpg', 'cover.jpeg', 'cover.png', 'cover.webp'] as $f) {
                if (file_exists($dirPath . '/' . $f)) {
                    $coverUrl = '/stream-image/?path=' . urlencode($dirPath . '/' . $f);
                    break;
                }
            }
        }
        $allMedia[] = [
            'type'      => $type,
            'label'     => $info['label'],
            'name'      => $dir,
            'title'     => $meta['title'] ?? $dir,
            'year'      => $meta['year']  ?? null,
            'cover_url' => $coverUrl,
        ];
    }
}
usort($allMedia, fn($a, $b) => strcasecmp($a['title'], $b['title']));
?>
        <div class="rename-layout">

            <!-- ── Sidebar : liste filtrée ───────────────────────────────── -->
            <aside class="rename-sidebar">
                <div class="rename-list-search-wrap">
                    <svg class="rename-list-search-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                        <circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/>
                    </svg>
                    <input id="media-filter" class="rename-list-search" type="search"
                           placeholder="Filtrer les médias…" autocomplete="off">
                </div>

                <div class="rename-media-list" id="media-list">
                    <?php foreach ($allMedia as $m): ?>
                    <button class="rename-media-item"
                            type="button"
                            data-value="<?= htmlspecialchars($m['type'] . '|' . $m['name']) ?>"
                            data-title="<?= htmlspecialchars(mb_strtolower($m['title'])) ?>"
                            data-cover="<?= htmlspecialchars($m['cover_url']) ?>">
                        <div class="rename-media-item__thumb">
                            <?php if ($m['cover_url']): ?>
                                <img src="<?= htmlspecialchars($m['cover_url']) ?>" alt="" loading="lazy">
                            <?php else: ?>
                                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" opacity=".3"><rect x="3" y="3" width="18" height="18" rx="2"/><path d="M3 9h18M9 3v18"/></svg>
                            <?php endif; ?>
                        </div>
                        <span class="rename-media-item__body">
                            <span class="rename-media-item__title"><?= htmlspecialchars($m['title']) ?></span>
                            <span class="rename-media-item__sub">
                                <?= htmlspecialchars($m['label']) ?><?= $m['year'] ? ' · ' . $m['year'] : '' ?>
                            </span>
                        </span>
                    </button>
                    <?php endforeach; ?>
                </div>
            </aside>

            <!-- ── Zone principale ──────────────────────────────────────── -->
            <div class="rename-main">

                <!-- Bandeau média sélectionné -->
                <div class="rename-selected-banner" id="rename-selected-banner" hidden>
                    <img class="rename-selected-banner__cover" id="banner-cover" src="" alt="" hidden>
                    <div class="rename-selected-banner__info">
                        <span class="rename-selected-banner__title" id="banner-title"></span>
                        <span class="rename-selected-banner__meta" id="banner-meta"></span>
                    </div>
                    <button class="rename-selected-banner__clear" id="btn-clear-media" title="Changer de média">
                        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
                    </button>
                </div>

                <!-- Placeholder sélection -->
                <div class="rename-placeholder" id="rename-placeholder">
                    <svg width="36" height="36" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" opacity=".2"><path d="M17 3a2.828 2.828 0 1 1 4 4L7.5 20.5 2 22l1.5-5.5L17 3z"/></svg>
                    <span>Sélectionne un média dans la liste pour commencer.</span>
                </div>

                <!-- Config template -->
                <div class="rename-config-card" id="rename-config-card" hidden>
                    <div class="rename-config-field">
                        <label class="rename-config-label" for="rename-template">Template de nommage</label>
                        <div class="rename-template-row">
                            <input id="rename-template" class="rename-template-input" type="text"
                                   value="{show} S{s}E{e} {title}"
                                   placeholder="{show} S{s}E{e} {title}"
                                   spellcheck="false">
                            <button id="btn-load" class="rename-load-btn">
                                <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="1 4 1 10 7 10"/><path d="M3.51 15a9 9 0 1 0 .49-3.5"/></svg>
                                Charger
                            </button>
                        </div>
                        <div class="rename-chips">
                            <span class="rename-chips-label">Variables :</span>
                            <button class="rename-chip" type="button" data-var="{show}">{show}</button>
                            <button class="rename-chip" type="button" data-var="S{s}">S{s}</button>
                            <button class="rename-chip" type="button" data-var="E{e}">E{e}</button>
                            <button class="rename-chip" type="button" data-var="{title}">{title}</button>
                        </div>
                    </div>

                    <!-- Stats -->
                    <div class="rename-stats" id="rename-stats" hidden>
                        <span class="rename-stat rename-stat--change">
                            <span class="rename-stat__val" id="stat-change-val">0</span> à renommer
                        </span>
                        <span class="rename-stat-sep"></span>
                        <span class="rename-stat rename-stat--same">
                            <span class="rename-stat__val" id="stat-same-val">0</span> inchangés
                        </span>
                        <span class="rename-stat-sep"></span>
                        <span class="rename-stat rename-stat--notmdb">
                            <span class="rename-stat__val" id="stat-notmdb-val">0</span> sans titre TMDB
                        </span>
                        <label class="hoshi-checkbox-label rename-toggle-same">
                            <span class="hoshi-checkbox">
                                <input type="checkbox" id="toggle-same" checked>
                                <span class="hoshi-checkbox__box"></span>
                            </span>
                            Masquer inchangés
                        </label>
                    </div>
                </div>

                <!-- Résultats -->
                <div id="rename-result" hidden>
                    <div id="rename-seasons"></div>
                </div>

                <div id="rename-empty" class="rename-empty" hidden>
                    <svg width="36" height="36" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" opacity=".25"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/></svg>
                    Aucun fichier vidéo trouvé dans ce dossier.
                </div>

                <!-- Footer sticky -->
                <div class="rename-footer" id="rename-footer" hidden>
                    <label class="hoshi-checkbox-label">
                        <span class="hoshi-checkbox">
                            <input type="checkbox" id="check-all" checked>
                            <span class="hoshi-checkbox__box"></span>
                        </span>
                        Tout sélectionner
                    </label>
                    <div class="rename-footer-right">
                        <span class="rename-footer-count" id="rename-footer-count"></span>
                        <button id="btn-apply" class="rename-apply-btn">
                            <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="20 6 9 17 4 12"/></svg>
                            Appliquer
                        </button>
                    </div>
                </div>

            </div>
        </div>

    </main>

    <?php include __DIR__ . '/../components/footer.php'; ?>

    <script>
    (() => {
        /* ─ DOM refs ─────────────────────────────────────────────────────── */
        const mediaFilter   = document.getElementById('media-filter');
        const mediaList     = document.getElementById('media-list');
        const templateInput = document.getElementById('rename-template');
        const btnLoad       = document.getElementById('btn-load');
        const btnApply      = document.getElementById('btn-apply');
        const btnClear      = document.getElementById('btn-clear-media');

        const bannerEl      = document.getElementById('rename-selected-banner');
        const bannerCover   = document.getElementById('banner-cover');
        const bannerTitle   = document.getElementById('banner-title');
        const bannerMeta    = document.getElementById('banner-meta');

        const placeholderEl = document.getElementById('rename-placeholder');
        const configCard    = document.getElementById('rename-config-card');
        const resultEl      = document.getElementById('rename-result');
        const seasonsEl     = document.getElementById('rename-seasons');
        const emptyEl       = document.getElementById('rename-empty');
        const statsEl       = document.getElementById('rename-stats');
        const footerEl      = document.getElementById('rename-footer');
        const checkAll      = document.getElementById('check-all');
        const toggleSame    = document.getElementById('toggle-same');
        const footerCount   = document.getElementById('rename-footer-count');

        const statChangeVal = document.getElementById('stat-change-val');
        const statSameVal   = document.getElementById('stat-same-val');
        const statNotmdbVal = document.getElementById('stat-notmdb-val');

        let currentRows  = [];
        let selectedItem = null;
        let autoTimer    = null;

        /* ─ Filtre liste ─────────────────────────────────────────────────── */
        mediaFilter.addEventListener('input', () => {
            const q = mediaFilter.value.trim().toLowerCase();
            mediaList.querySelectorAll('.rename-media-item').forEach(btn => {
                btn.hidden = q && !btn.dataset.title.includes(q);
            });
        });

        /* ─ Sélection média ──────────────────────────────────────────────── */
        mediaList.addEventListener('click', e => {
            const btn = e.target.closest('.rename-media-item');
            if (!btn) return;

            if (selectedItem) selectedItem.classList.remove('is-active');
            selectedItem = btn;
            btn.classList.add('is-active');

            // Bandeau
            const [type, name] = btn.dataset.value.split('|');
            const title = btn.querySelector('.rename-media-item__title').textContent;
            const sub   = btn.querySelector('.rename-media-item__sub').textContent;
            const cover = btn.dataset.cover;

            bannerTitle.textContent = title;
            bannerMeta.textContent  = sub;
            if (cover) {
                bannerCover.src = cover;
                bannerCover.hidden = false;
            } else {
                bannerCover.hidden = true;
            }
            bannerEl.hidden = false;
            placeholderEl.hidden = true;
            configCard.hidden = false;

            loadPreview();
        });

        btnClear.addEventListener('click', () => {
            if (selectedItem) { selectedItem.classList.remove('is-active'); selectedItem = null; }
            bannerEl.hidden = true;
            placeholderEl.hidden = false;
            configCard.hidden = true;
            resultEl.hidden  = true;
            emptyEl.hidden   = true;
            statsEl.hidden   = true;
            footerEl.hidden  = true;
        });

        /* ─ Variable chips ───────────────────────────────────────────────── */
        document.querySelectorAll('.rename-chip').forEach(chip => {
            chip.addEventListener('click', () => {
                const v = chip.dataset.var;
                const s = templateInput.selectionStart ?? templateInput.value.length;
                const e = templateInput.selectionEnd   ?? s;
                templateInput.value = templateInput.value.slice(0, s) + v + templateInput.value.slice(e);
                templateInput.focus();
                templateInput.setSelectionRange(s + v.length, s + v.length);
                scheduleAutoLoad();
            });
        });

        /* ─ Template auto-reload ─────────────────────────────────────────── */
        templateInput.addEventListener('input', scheduleAutoLoad);
        templateInput.addEventListener('keydown', e => { if (e.key === 'Enter') loadPreview(); });
        btnLoad.addEventListener('click', loadPreview);

        function scheduleAutoLoad() {
            clearTimeout(autoTimer);
            if (!selectedItem) return;
            autoTimer = setTimeout(loadPreview, 600);
        }

        /* ─ Season check helpers ─────────────────────────────────────────── */
        function updateSeasonCheckState(season) {
            const all = [...season.querySelectorAll('.rename-check:not(:disabled)')];
            const checked = all.filter(cb => cb.checked).length;
            const sc = season.querySelector('.rename-season-check');
            if (!sc) return;
            sc.indeterminate = checked > 0 && checked < all.length;
            sc.checked = checked > 0 && checked === all.length;
        }

        function updateAllSeasonChecks() {
            seasonsEl.querySelectorAll('.rename-season').forEach(updateSeasonCheckState);
        }

        /* ─ Toggle inchangés ─────────────────────────────────────────────── */
        toggleSame.addEventListener('change', applyToggleSame);
        function applyToggleSame() {
            const hide = toggleSame.checked;
            document.querySelectorAll('.rename-row--same').forEach(tr => { tr.hidden = hide; });
        }

        /* ─ checkAll ─────────────────────────────────────────────────────── */
        checkAll.addEventListener('change', () => {
            document.querySelectorAll('.rename-check:not(:disabled)').forEach(cb => {
                cb.checked = checkAll.checked;
            });
            updateAllSeasonChecks();
            updateFooterCount();
        });

        /* ─ Chargement ───────────────────────────────────────────────────── */
        async function loadPreview() {
            if (!selectedItem) return;
            clearTimeout(autoTimer);

            const [type, name] = selectedItem.dataset.value.split('|');
            const tpl = templateInput.value.trim() || '{show} S{s}E{e} {title}';

            btnLoad.classList.add('is-loading');
            btnLoad.disabled = true;

            const data = await fetch(
                `/admin/api/rename-preview.php?type=${encodeURIComponent(type)}&name=${encodeURIComponent(name)}&template=${encodeURIComponent(tpl)}`
            ).then(r => r.json()).catch(() => null);

            btnLoad.classList.remove('is-loading');
            btnLoad.disabled = false;

            resultEl.hidden = true;
            emptyEl.hidden  = true;
            statsEl.hidden  = true;
            footerEl.hidden = true;

            if (!data?.ok) {
                HoshimiToast.show(data?.error ?? 'Erreur', { type: 'error' }); return;
            }

            if (!data.rows.length) { emptyEl.hidden = false; return; }

            currentRows = data.rows;
            renderSeasons(data.rows);
            updateStats(data.rows);
            applyToggleSame();

            resultEl.hidden = false;
            statsEl.hidden  = false;
            footerEl.hidden = false;
            updateFooterCount();
        }

        /* ─ Render par saison ────────────────────────────────────────────── */
        function renderSeasons(rows) {
            const seasons = {};
            for (const r of rows) {
                if (!seasons[r.season]) seasons[r.season] = [];
                seasons[r.season].push(r);
            }

            seasonsEl.innerHTML = Object.entries(seasons).map(([s, sRows]) => {
                const changed = sRows.filter(r => r.changed).length;
                const badge = changed > 0
                    ? `<span class="rename-season-badge">${changed} à renommer</span>`
                    : `<span class="rename-season-badge rename-season-badge--ok">${lc('check', 13)} Tous identiques</span>`;

                const trs = sRows.map(r => {
                    const idx = currentRows.indexOf(r);
                    const tmdbDot = r.tmdb_title
                        ? `<span class="rename-tmdb-dot rename-tmdb-dot--ok" title="Titre TMDB : ${esc(r.tmdb_title)}"></span>`
                        : `<span class="rename-tmdb-dot rename-tmdb-dot--miss" title="Aucun titre TMDB"></span>`;

                    const checkboxCell = `<label class="hoshi-checkbox-label" style="justify-content:center;">
                        <span class="hoshi-checkbox">
                            <input type="checkbox" class="rename-check" data-idx="${idx}"
                                ${r.changed ? 'checked' : ''} ${!r.changed ? 'disabled' : ''}>
                            <span class="hoshi-checkbox__box"></span>
                        </span>
                    </label>`;

                    return `<tr class="rename-row${!r.changed ? ' rename-row--same' : ''}" data-idx="${idx}">
                        <td class="rename-col-check">${checkboxCell}</td>
                        <td class="rename-col-ep">
                            <span class="rename-ep-num">${String(r.episode).padStart(2,'0')}</span>
                        </td>
                        <td class="rename-col-tmdb">${tmdbDot}</td>
                        <td class="rename-col-from">
                            <span class="rename-filename${r.changed ? ' rename-filename--old' : ''}">${esc(r.filename)}</span>
                        </td>
                        <td class="rename-col-arrow">${r.changed ? '<svg width="11" height="11" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><line x1="5" y1="12" x2="19" y2="12"/><polyline points="12 5 19 12 12 19"/></svg>' : ''}</td>
                        <td class="rename-col-to">
                            ${!r.changed
                                ? '<span class="rename-unchanged">inchangé</span>'
                                : `<div class="rename-to-wrap">
                                    <input class="rename-input" type="text" value="${esc(r.suggested)}" data-idx="${idx}" spellcheck="false">
                                    ${r.tmdb_title ? `<span class="rename-tmdb-hint">${esc(r.tmdb_title)}</span>` : ''}
                                   </div>`
                            }
                        </td>
                    </tr>`;
                }).join('');

                return `<details class="rename-season" open>
                    <summary class="rename-season__header">
                        <label class="hoshi-checkbox-label rename-season-check-label" title="Sélectionner / désélectionner la saison">
                            <span class="hoshi-checkbox">
                                <input type="checkbox" class="rename-season-check">
                                <span class="hoshi-checkbox__box"></span>
                            </span>
                        </label>
                        <div class="rename-season__arrow">
                            <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="9 18 15 12 9 6"/></svg>
                        </div>
                        <span class="rename-season__name">Saison ${s}</span>
                        <span class="rename-season__count">${sRows.length} épisode${sRows.length > 1 ? 's' : ''}</span>
                        ${badge}
                    </summary>
                    <table class="rename-table">
                        <thead><tr>
                            <th class="rename-col-check"></th>
                            <th class="rename-col-ep">Ép</th>
                            <th class="rename-col-tmdb" title="Titre TMDB">T</th>
                            <th class="rename-col-from">Nom actuel</th>
                            <th class="rename-col-arrow"></th>
                            <th class="rename-col-to">Nouveau nom</th>
                        </tr></thead>
                        <tbody>${trs}</tbody>
                    </table>
                </details>`;
            }).join('');

            // Empêche le clic sur le checkbox de saison de replier/déplier le <details>
            seasonsEl.querySelectorAll('.rename-season-check-label').forEach(lbl => {
                lbl.addEventListener('click', e => e.stopPropagation());
            });

            // Checkbox de saison → coche/décoche tous les épisodes de la saison
            seasonsEl.addEventListener('change', e => {
                const seasonCheck = e.target.closest('.rename-season-check');
                if (seasonCheck) {
                    const season = seasonCheck.closest('.rename-season');
                    season.querySelectorAll('.rename-check:not(:disabled)').forEach(cb => {
                        cb.checked = seasonCheck.checked;
                    });
                } else {
                    // Checkbox d'épisode → met à jour le checkbox de la saison parente
                    const season = e.target.closest('.rename-season');
                    if (season) updateSeasonCheckState(season);
                }
                updateFooterCount();
            });

            // État initial des checkboxes de saison
            seasonsEl.querySelectorAll('.rename-season').forEach(updateSeasonCheckState);
        }

        /* ─ Stats ────────────────────────────────────────────────────────── */
        function updateStats(rows) {
            statChangeVal.textContent = rows.filter(r => r.changed).length;
            statSameVal.textContent   = rows.filter(r => !r.changed).length;
            statNotmdbVal.textContent = rows.filter(r => !r.tmdb_title).length;
        }

        function updateFooterCount() {
            const n = document.querySelectorAll('.rename-check:checked').length;
            footerCount.textContent = n ? `${n} sélectionné${n > 1 ? 's' : ''}` : '';
        }

        /* ─ Apply ────────────────────────────────────────────────────────── */
        btnApply.addEventListener('click', async () => {
            const renames = [];
            document.querySelectorAll('.rename-check:checked').forEach(cb => {
                const idx    = parseInt(cb.dataset.idx);
                const row    = currentRows[idx];
                const input  = document.querySelector(`.rename-input[data-idx="${idx}"]`);
                const toName = input ? input.value.trim() : row.suggested;
                if (toName && toName !== row.filename) {
                    renames.push({ from: row.path, to: row.dir + '/' + toName });
                }
            });

            if (!renames.length) { HoshimiToast.show('Rien à renommer', { type: 'info' }); return; }

            btnApply.disabled = true;
            btnApply.innerHTML = `<svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" style="animation:scan-spin .8s linear infinite"><polyline points="1 4 1 10 7 10"/><path d="M3.51 15a9 9 0 1 0 .49-3.5"/></svg> ${renames.length} fichier(s)…`;

            const res = await fetch('/admin/api/rename-apply.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ renames }),
            }).then(r => r.json()).catch(() => null);

            btnApply.disabled = false;
            btnApply.innerHTML = `<svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="20 6 9 17 4 12"/></svg> Appliquer`;

            if (!res?.ok) { HoshimiToast.show('Erreur serveur', { type: 'error' }); return; }

            const errs = res.errors ?? [];
            if (errs.length) {
                HoshimiToast.show(`${res.done} renommé(s), ${errs.length} erreur(s)`, { type: 'error' });
            } else {
                HoshimiToast.show(`${res.done} fichier(s) renommé(s)`, { type: 'success' });
            }

            setTimeout(loadPreview, 800);
        });

        function esc(s) {
            return String(s).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
        }
    })();
    </script>
    <script>(function(){const l=document.getElementById('loader'),c=document.getElementById('adm-content');if(c)c.hidden=false;if(l)l.remove();})();</script>
        </div><!-- #adm-content -->
</body>
</html>
