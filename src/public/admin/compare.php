<?php

declare(strict_types=1);

require_once __DIR__ . '/../../app/MacGuard.php';
require_once __DIR__ . '/../components/icon.php';
MacGuard::protect();

$mediaTypes = [
    'anime' => ['root' => $_ENV['ANIMES_PATH'] ?? getenv('ANIMES_PATH') ?: '/media/animes', 'label' => 'Anime', 'color' => '#6c63ff'],
    'film'  => ['root' => $_ENV['FILMS_PATH']  ?? getenv('FILMS_PATH')  ?: '/media/films',  'label' => 'Film',  'color' => '#22d3ee'],
    'serie' => ['root' => $_ENV['SERIES_PATH'] ?? getenv('SERIES_PATH') ?: '/media/series', 'label' => 'Série', 'color' => '#34d399'],
];

$selectedType = $_GET['type'] ?? '';
$selectedName = $_GET['name'] ?? '';
?>
<!doctype html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Comparateur TMDB — Hoshimi Admin</title>
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
                <span class="section-header__type">TMDB</span>
                <h1 class="section-header__title" style="font-size:1.4rem">Comparateur TMDB</h1>
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
        $dirPath  = $root . DIRECTORY_SEPARATOR . $dir;
        if (!is_dir($dirPath)) continue;
        $metaPath = $dirPath . DIRECTORY_SEPARATOR . 'metadata.json';
        if (!file_exists($metaPath)) continue;
        $meta = json_decode(file_get_contents($metaPath), true);
        if (!is_array($meta) || empty($meta['tmdb_id'])) continue;
        $allMedia[] = [
            'type'  => $type,
            'label' => $info['label'],
            'color' => $info['color'],
            'name'  => $dir,
            'title' => $meta['title'] ?? $dir,
        ];
    }
}
usort($allMedia, fn($a, $b) => strcasecmp($a['title'], $b['title']));
?>
        <!-- Recherche -->
        <div class="cmp-search-wrap">
            <svg class="cmp-search-icon" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
            <input type="text" id="cmp-search" class="cmp-search" placeholder="Rechercher un titre…" autocomplete="off" spellcheck="false">
            <kbd class="cmp-search-kbd">↑↓ Naviguer · ↵ Sélectionner</kbd>
            <div class="cmp-search-results" id="cmp-results" hidden></div>
        </div>

        <!-- Panel résultat -->
        <div id="cmp-panel" <?= ($selectedType && $selectedName) ? '' : 'hidden' ?>>

            <!-- Header résultat -->
            <div class="cmp-result-header" id="cmp-result-header">
                <div class="cmp-result-header__left">
                    <span class="cmp-result-header__type" id="cmp-type-badge"></span>
                    <h2 class="cmp-result-header__title" id="cmp-title"></h2>
                </div>
                <div class="cmp-result-header__right">
                    <span class="cmp-status-badge" id="cmp-status-badge"></span>
                    <a class="cmp-tmdb-link" id="cmp-tmdb-link" target="_blank" rel="noopener" hidden>
                        <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M18 13v6a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h6"/><polyline points="15 3 21 3 21 9"/><line x1="10" y1="14" x2="21" y2="3"/></svg>
                        Voir sur TMDB
                    </a>
                    <button class="admin-row__btn admin-row__btn--rescan" id="cmp-refetch">
                        <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="1 4 1 10 7 10"/><path d="M3.51 15a9 9 0 1 0 .49-4.14"/></svg>
                        Rescanner
                    </button>
                </div>
            </div>

            <div id="cmp-loading" class="cmp-state-msg" hidden>
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="animation:spin .8s linear infinite"><polyline points="1 4 1 10 7 10"/><path d="M3.51 15a9 9 0 1 0 .49-4.14"/></svg>
                Chargement…
            </div>
            <div id="cmp-error" class="cmp-state-msg cmp-state-msg--error" hidden></div>

            <!-- Covers côte à côte -->
            <div class="cmp-covers" id="cmp-covers" hidden>
                <div class="cmp-cover-col">
                    <div class="cmp-cover-label">Local</div>
                    <div class="cmp-cover-frame" id="cmp-cover-local">
                        <span class="cmp-cover-empty">Aucune</span>
                    </div>
                </div>
                <div class="cmp-cover-divider">
                    <span id="cmp-cover-diff-badge"></span>
                </div>
                <div class="cmp-cover-col">
                    <div class="cmp-cover-label">TMDB</div>
                    <div class="cmp-cover-frame" id="cmp-cover-remote">
                        <span class="cmp-cover-empty">Aucune</span>
                    </div>
                </div>
            </div>

            <!-- Tableau de comparaison -->
            <div class="cmp-table-wrap" id="cmp-table-wrap" hidden>
                <table class="cmp-table">
                    <thead>
                        <tr>
                            <th style="width:120px">Champ</th>
                            <th>Local</th>
                            <th>TMDB</th>
                            <th style="width:80px"></th>
                        </tr>
                    </thead>
                    <tbody id="cmp-tbody"></tbody>
                </table>
            </div>

        </div>

    </main>

    <script>
    const allMedia = <?= json_encode($allMedia, JSON_UNESCAPED_UNICODE) ?>;
    let currentType = <?= json_encode($selectedType) ?>;
    let currentName = <?= json_encode($selectedName) ?>;
    let currentData = null;

    // ── Recherche avec navigation clavier ────────────────────────────────────
    const searchInput = document.getElementById('cmp-search');
    const resultsEl   = document.getElementById('cmp-results');
    let activeIdx = -1;

    function renderResults(matches) {
        if (!matches.length) { resultsEl.hidden = true; return; }
        resultsEl.innerHTML = matches.map((m, i) =>
            `<div class="cmp-result-item" data-type="${m.type}" data-name="${encodeURIComponent(m.name)}" data-idx="${i}">
                <span class="cmp-result-dot" style="background:${m.color}"></span>
                <span class="cmp-result-label">${m.label}</span>
                <span class="cmp-result-title">${escHtml(m.title)}</span>
            </div>`
        ).join('');
        activeIdx = -1;
        resultsEl.hidden = false;
    }

    searchInput.addEventListener('input', () => {
        const q = searchInput.value.trim().toLowerCase();
        if (q.length < 2) { resultsEl.hidden = true; return; }
        const matches = allMedia.filter(m =>
            m.title.toLowerCase().includes(q) || m.name.toLowerCase().includes(q)
        ).slice(0, 14);
        renderResults(matches);
    });

    searchInput.addEventListener('keydown', e => {
        const items = [...resultsEl.querySelectorAll('.cmp-result-item')];
        if (!items.length) return;
        if (e.key === 'ArrowDown') {
            e.preventDefault();
            activeIdx = Math.min(activeIdx + 1, items.length - 1);
        } else if (e.key === 'ArrowUp') {
            e.preventDefault();
            activeIdx = Math.max(activeIdx - 1, 0);
        } else if (e.key === 'Enter' && activeIdx >= 0) {
            e.preventDefault();
            items[activeIdx].click();
            return;
        } else if (e.key === 'Escape') {
            resultsEl.hidden = true; return;
        } else return;
        items.forEach((el, i) => el.classList.toggle('is-active', i === activeIdx));
        items[activeIdx]?.scrollIntoView({ block: 'nearest' });
    });

    resultsEl.addEventListener('click', e => {
        const item = e.target.closest('.cmp-result-item');
        if (!item) return;
        currentType = item.dataset.type;
        currentName = decodeURIComponent(item.dataset.name);
        searchInput.value = '';
        resultsEl.hidden = true;
        loadComparison(currentType, currentName);
    });

    document.addEventListener('click', e => {
        if (!e.target.closest('.cmp-search-wrap')) resultsEl.hidden = true;
    });

    // ── Chargement comparaison ────────────────────────────────────────────────
    async function loadComparison(type, name) {
        const panel      = document.getElementById('cmp-panel');
        const loading    = document.getElementById('cmp-loading');
        const errEl      = document.getElementById('cmp-error');
        const covers     = document.getElementById('cmp-covers');
        const tableWrap  = document.getElementById('cmp-table-wrap');
        const titleEl    = document.getElementById('cmp-title');
        const typeBadge  = document.getElementById('cmp-type-badge');
        const statusBadge= document.getElementById('cmp-status-badge');
        const tmdbLink   = document.getElementById('cmp-tmdb-link');
        const header     = document.getElementById('cmp-result-header');

        panel.hidden   = false;
        loading.hidden = false;
        errEl.hidden   = true;
        covers.hidden  = true;
        tableWrap.hidden = true;
        header.style.opacity = '0.5';

        const data = await fetch(`/admin/api/tmdb-compare.php?type=${encodeURIComponent(type)}&name=${encodeURIComponent(name)}`)
            .then(r => r.json()).catch(() => ({ ok: false, error: 'Erreur réseau' }));

        loading.hidden = true;
        header.style.opacity = '';

        if (!data.ok) {
            errEl.innerHTML = lc('alert-triangle', 14) + ' ' + (data.error ?? 'Erreur inconnue');
            errEl.hidden = false;
            return;
        }

        currentData = data;

        // Header
        const mediaInfo = allMedia.find(m => m.type === type && m.name === name);
        titleEl.textContent = data.fields.title?.local ?? name;
        typeBadge.textContent = mediaInfo?.label ?? type;
        typeBadge.style.background = (mediaInfo?.color ?? '#888') + '22';
        typeBadge.style.color = mediaInfo?.color ?? '#888';

        const diffs = Object.values(data.fields).filter(f =>
            f.key !== 'cover'
            && f.local_source !== 'anilist'
            && String(f.local ?? '').trim() !== String(f.remote ?? '').trim()
        ).length;
        statusBadge.innerHTML = diffs === 0 ? `${lc('check', 13)} À jour` : `${diffs} différence${diffs > 1 ? 's' : ''}`;
        statusBadge.className   = 'cmp-status-badge' + (diffs > 0 ? ' cmp-status-badge--diff' : ' cmp-status-badge--ok');

        // TMDB link
        const tmdbBase = data.tmdb_type === 'movie' ? 'https://www.themoviedb.org/movie/' : 'https://www.themoviedb.org/tv/';
        tmdbLink.href = tmdbBase + data.tmdb_id;
        tmdbLink.hidden = false;

        // Covers
        const coverLocal  = document.getElementById('cmp-cover-local');
        const coverRemote = document.getElementById('cmp-cover-remote');
        const coverDiff   = document.getElementById('cmp-cover-diff-badge');
        const localCoverUrl  = data.fields.cover?.local  ?? null;
        const remoteCoverUrl = data.fields.cover?.remote ?? null;
        const coverSame = String(localCoverUrl ?? '').trim() === String(remoteCoverUrl ?? '').trim();

        coverLocal.innerHTML  = localCoverUrl
            ? `<img src="${escHtml(localCoverUrl)}" alt="">`
            : '<span class="cmp-cover-empty">Aucune</span>';
        coverRemote.innerHTML = remoteCoverUrl
            ? `<img src="${escHtml(remoteCoverUrl)}" alt="">`
            : '<span class="cmp-cover-empty">Aucune</span>';
        const iconSame = `<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"/></svg>Identique`;
        const iconDiff = `<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="5" y1="12" x2="19" y2="12"/><polyline points="12 5 19 12 12 19"/></svg>Différente`;
        coverDiff.innerHTML = coverSame ? iconSame : iconDiff;
        coverDiff.className = coverSame ? 'cmp-cover-eq' : 'cmp-cover-neq';
        covers.hidden = false;

        // Tableau (sans cover — gérée visuellement)
        const tbody = document.getElementById('cmp-tbody');
        const rows = Object.entries(data.fields).filter(([k]) => k !== 'cover');
        tbody.innerHTML = rows.map(([key, f]) => {
            const local      = f.local  ?? '';
            const remote     = f.remote ?? '';
            const isAnilist  = f.local_source === 'anilist';
            const diff       = !isAnilist && String(local).trim() !== String(remote).trim();
            const fmt = (v, cls, note) => {
                if (v === null || v === '' || v === undefined) return '<span class="cmp-empty">—</span>';
                const text = key === 'overview'
                    ? `<span class="cmp-text ${cls}">${escHtml(String(v))}</span>`
                    : `<span class="${cls}">${escHtml(String(v))}</span>`;
                return text + (note ? `<span class="cmp-source-note">${note}</span>` : '');
            };
            const actionCell = isAnilist
                ? '<span class="cmp-source-badge">AniList</span>'
                : diff
                    ? `<button class="cmp-apply-btn js-apply-field" data-key="${escHtml(key)}" title="Appliquer la valeur TMDB">Appliquer</button>`
                    : `<span class="cmp-ok-mark">${lc('check', 13)}</span>`;
            return `<tr class="${diff ? 'cmp-row--diff' : ''}">
                <td class="cmp-field">${escHtml(f.label)}</td>
                <td class="cmp-val">${fmt(local, '', isAnilist ? 'AniList' : null)}</td>
                <td class="cmp-val cmp-val--remote">${fmt(remote, diff ? 'cmp-val--highlight' : '')}</td>
                <td class="cmp-actions-cell">${actionCell}</td>
            </tr>`;
        }).join('');

        tableWrap.hidden = false;
    }

    // ── Appliquer un champ individuel ─────────────────────────────────────────
    document.addEventListener('click', async e => {
        const btn = e.target.closest('.js-apply-field');
        if (!btn || !currentData) return;
        const key = btn.dataset.key;
        btn.disabled = true;
        btn.textContent = '…';
        const res = await fetch('/admin/api/run-scan.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ action: 'apply_field', type: currentType, name: currentName, field: key, value: currentData.fields[key]?.remote }),
        }).then(r => r.json()).catch(() => ({ ok: false }));
        toast(res.ok ? `Champ "${key}" mis à jour` : ('Erreur : ' + (res.error ?? 'inconnue')), res.ok);
        if (res.ok) {
            btn.closest('tr').classList.remove('cmp-row--diff');
            btn.replaceWith(Object.assign(document.createElement('span'), { className: 'cmp-ok-mark', innerHTML: lc('check', 13) }));
        } else {
            btn.disabled = false;
            btn.textContent = 'Appliquer';
        }
    });

    // ── Rescanner ─────────────────────────────────────────────────────────────
    document.getElementById('cmp-refetch').addEventListener('click', async () => {
        const btn = document.getElementById('cmp-refetch');
        btn.disabled = true;
        const res = await fetch('/admin/api/run-scan.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ action: 'refetch_meta', type: currentType, name: currentName }),
        }).then(r => r.json()).catch(() => ({ ok: false }));
        if (res.ok) {
            toast('Scan lancé — rechargement dans 4s…', true);
            setTimeout(() => { loadComparison(currentType, currentName); btn.disabled = false; }, 4000);
        } else {
            toast('Erreur : ' + (res.error ?? 'inconnue'), false);
            btn.disabled = false;
        }
    });

    function toast(msg, ok) {
        HoshimiToast.show(msg, { type: ok ? 'success' : 'error' });
    }

    function escHtml(s) {
        return String(s).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
    }

    if (currentType && currentName) loadComparison(currentType, currentName);
    </script>
    <style>@keyframes spin { to { transform: rotate(360deg); } }</style>
    <script>(function(){const l=document.getElementById('loader'),c=document.getElementById('adm-content');if(c)c.hidden=false;if(l)l.remove();})();</script>
        </div><!-- #adm-content -->
</body>
</html>
