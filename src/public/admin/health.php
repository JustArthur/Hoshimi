<?php

declare(strict_types=1);

require_once __DIR__ . '/../../app/MacGuard.php';
MacGuard::protect();
?>
<!doctype html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Santé — Hoshimi Admin</title>
    <link rel="stylesheet" href="/css/hoshimi.css">
    <link rel="stylesheet" href="/css/pages/admin.css">
    <link rel="stylesheet" href="/css/components/select.css">
    <script src="/js/storage.js"></script>
    <script src="/js/toast.js"></script>
    <script src="/js/select.js"></script>
</head>
<body>
    <?php include __DIR__ . '/../components/navbar.php'; ?>

    <main class="admin-page">

        <div class="admin-page__header">
        <div class="section-header section-header--simple" style="margin-bottom:0">
            <div class="section-header__bar"></div>
            <div class="section-header__inner">
                <span class="section-header__type">Données</span>
                <h1 class="section-header__title" style="font-size:1.4rem">Santé des données</h1>
            </div>
        </div>
            <?php include __DIR__ . '/_nav.php'; ?>
        </div>

        <div class="loader" id="loader">
            <div class="loader__ring"></div>
            <span class="loader__label">Chargement…</span>
        </div>

        <div class="adm-content" id="adm-content" hidden>
        <div class="health-grid">

            <!-- ── Validation des épisodes ──────────────────────────────────── -->
            <div class="health-card" id="hc-episodes">
                <div class="health-card__header">
                    <div class="health-card__title-wrap">
                        <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="2" y="7" width="20" height="15" rx="2"/><polyline points="17 2 12 7 7 2"/></svg>
                        <h2>Validation des épisodes</h2>
                    </div>
                    <button class="btn btn--primary" data-action="episodes">Analyser</button>
                </div>
                <p class="health-card__desc">Compare le nombre d'épisodes attendus (TMDB) avec les fichiers vidéo présents localement.</p>
                <div class="health-card__body" id="hc-episodes-body">
                    <div class="health-idle">Clique sur Analyser pour lancer la vérification.</div>
                </div>
            </div>

            <!-- ── Doublons de fichiers ──────────────────────────────────────── -->
            <div class="health-card" id="hc-files">
                <div class="health-card__header">
                    <div class="health-card__title-wrap">
                        <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="12" y1="18" x2="12" y2="12"/><line x1="9" y1="15" x2="15" y2="15"/></svg>
                        <h2>Doublons de fichiers</h2>
                    </div>
                    <button class="btn btn--primary" data-action="files">Analyser</button>
                </div>
                <p class="health-card__desc">Détecte les fichiers vidéo avec le même nom présents dans plusieurs dossiers différents.</p>
                <div class="health-card__body" id="hc-files-body">
                    <div class="health-idle">Clique sur Analyser pour lancer la vérification.</div>
                </div>
            </div>

            <!-- ── Poids par média ───────────────────────────────────────────── -->
            <div class="health-card health-card--full" id="hc-sizes">
                <div class="health-card__header">
                    <div class="health-card__title-wrap">
                        <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><ellipse cx="12" cy="5" rx="9" ry="3"/><path d="M3 5v14a9 3 0 0 0 18 0V5"/><path d="M3 12a9 3 0 0 0 18 0"/></svg>
                        <h2>Poids par média</h2>
                    </div>
                    <button class="btn btn--primary" data-action="sizes">Analyser</button>
                </div>
                <p class="health-card__desc">Calcule la taille totale de chaque dossier média et affiche un classement par poids décroissant.</p>
                <div class="health-card__body" id="hc-sizes-body">
                    <div class="health-idle">Clique sur Analyser pour calculer les tailles.</div>
                </div>
            </div>

            <!-- ── Infos vidéo (ffprobe) ─────────────────────────────────────── -->
            <div class="health-card health-card--full" id="hc-ffprobe">
                <div class="health-card__header">
                    <div class="health-card__title-wrap">
                        <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polygon points="23 7 16 12 23 17 23 7"/><rect x="1" y="5" width="15" height="14" rx="2" ry="2"/></svg>
                        <h2>Infos vidéo</h2>
                    </div>
                </div>
                <p class="health-card__desc">Affiche le codec, la résolution, le bitrate et la taille de chaque fichier vidéo via ffprobe.</p>
                <div class="health-probe-form">
                    <div class="health-probe-type-btns" id="probe-type-btns">
                        <button class="btn btn--ghost is-active" data-type="anime">Anime</button>
                        <button class="btn btn--ghost" data-type="film">Film</button>
                        <button class="btn btn--ghost" data-type="serie">Série</button>
                    </div>
                    <div class="cs-wrap" id="probe-cs-wrap" data-value="" style="flex:1; min-width:200px; border-radius: var(--radius-sm);">
                        <button class="cs-btn" type="button" aria-haspopup="listbox" aria-expanded="false" style="width:100%; justify-content:space-between; border-radius: var(--radius-sm); background: color-mix(in srgb, var(--color-bg) 25%, transparent);">
                            <span class="cs-label">— Choisir un média —</span>
                        </button>
                        <ul class="cs-list" role="listbox" id="probe-cs-list" style="border-radius: var(--radius-sm);"></ul>
                    </div>
                    <button class="btn btn--primary" id="probe-run-btn" disabled>Analyser</button>
                </div>
                <div class="health-card__body" id="hc-ffprobe-body">
                    <div class="health-idle">Sélectionne un type et un média pour analyser ses fichiers vidéo.</div>
                </div>
            </div>

            <!-- ── Liens cassés ──────────────────────────────────────────────── -->
            <div class="health-card health-card--full" id="hc-links">
                <div class="health-card__header">
                    <div class="health-card__title-wrap">
                        <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M10 13a5 5 0 0 0 7.54.54l3-3a5 5 0 0 0-7.07-7.07l-1.72 1.71"/><path d="M14 11a5 5 0 0 0-7.54-.54l-3 3a5 5 0 0 0 7.07 7.07l1.71-1.71"/></svg>
                        <h2>Vérification des liens d'images</h2>
                    </div>
                    <button class="btn btn--primary" data-action="links">Vérifier</button>
                </div>
                <p class="health-card__desc">Vérifie que les URLs de covers et banners stockées en metadata pointent vers des ressources accessibles.</p>
                <div class="health-card__body" id="hc-links-body">
                    <div class="health-idle">Clique sur Vérifier pour lancer la vérification (peut prendre quelques secondes).</div>
                </div>
            </div>

        </div>

    </main>

    <?php include __DIR__ . '/../components/footer.php'; ?>

    <script>
    (() => {
        const TYPE_COLORS = { anime: '#60a5fa', film: '#c8a468', serie: '#a78bfa' };

        function esc(s) {
            return String(s).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;');
        }

        function setLoading(bodyEl, msg = 'Analyse en cours…') {
            bodyEl.innerHTML = `<div class="health-loading">
                <div class="health-loading__spinner"></div>
                <span>${esc(msg)}</span>
            </div>`;
        }

        function setError(bodyEl, msg) {
            bodyEl.innerHTML = `<div class="health-error">${esc(msg)}</div>`;
        }

        function setOk(bodyEl, msg) {
            bodyEl.innerHTML = `<div class="health-ok">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="20 6 9 17 4 12"/></svg>
                ${esc(msg)}
            </div>`;
        }

        /* ── Épisodes ─────────────────────────────────────────────────────── */
        function renderEpisodes(data) {
            const body = document.getElementById('hc-episodes-body');
            if (!data.results.length) { setOk(body, 'Tous les épisodes correspondent.'); return; }

            body.innerHTML = `<div class="health-summary health-summary--warn">${data.total} médias avec écart</div>
            <div class="health-ep-list">
            ${data.results.map(r => {
                const diff  = r.diff;
                const cls   = diff > 0 ? 'health-ep--extra' : 'health-ep--missing';
                const label = diff > 0 ? `+${diff} en trop` : `${diff} manquants`;
                const color = TYPE_COLORS[r.type] ?? '#888';
                return `<a class="health-ep-row" href="${esc(r.href)}">
                    <div class="health-ep-row__main">
                        <span class="health-ep-dot" style="background:${color}"></span>
                        <span class="health-ep-title">${esc(r.title)}</span>
                    </div>
                    <div class="health-ep-row__details">
                        <span class="health-ep-badge health-ep-badge--${r.type}">${esc(r.label)}</span>
                        <span class="health-ep-counts">${r.actual} / ${r.expected}</span>
                        <span class="health-ep-diff ${cls}">${label}</span>
                    </div>
                </a>`;
            }).join('')}
            </div>`;
        }

        /* ── Doublons ─────────────────────────────────────────────────────── */
        function renderFiles(data) {
            const body = document.getElementById('hc-files-body');
            if (!data.duplicates.length) { setOk(body, 'Aucun doublon détecté.'); return; }

            body.innerHTML = `<div class="health-summary health-summary--warn">${data.total} doublon(s) détecté(s)</div>
            <div class="health-dup-list">
            ${data.duplicates.map(d => `
                <div class="health-dup-group">
                    <span class="health-dup-name">${esc(d.name)}</span>
                    ${d.files.map(f => `<span class="health-dup-path">${esc(f.path)}</span>`).join('')}
                </div>
            `).join('')}
            </div>`;
        }

        /* ── Liens ────────────────────────────────────────────────────────── */
        function renderLinks(data) {
            const body = document.getElementById('hc-links-body');
            const summary = `<div class="health-summary ${data.broken.length ? 'health-summary--warn' : 'health-summary--ok'}">
                ${data.checked} URL${data.checked > 1 ? 's' : ''} vérifiée${data.checked > 1 ? 's' : ''}
                · ${data.broken.length ? data.broken.length + ' cassée(s)' : 'Aucune cassée'}
            </div>`;

            if (!data.broken.length) {
                body.innerHTML = summary + `<div class="health-ok" style="margin-top:12px">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="20 6 9 17 4 12"/></svg>
                    Tous les liens sont valides.
                </div>`;
                return;
            }

            body.innerHTML = summary + `<div class="health-link-list">
            ${data.broken.map(b => `
                <div class="health-link-row">
                    <span class="health-ep-badge health-ep-badge--${esc(b.type)}">${esc(b.label)}</span>
                    <a class="health-link-title" href="${esc(b.href)}">${esc(b.title)}</a>
                    <span class="health-link-field">${esc(b.field)}</span>
                    <span class="health-link-code">${b.code === 0 ? 'timeout' : b.code}</span>
                    <a class="health-link-url" href="${esc(b.url)}" target="_blank" rel="noopener">${esc(b.url.slice(0, 60))}…</a>
                </div>
            `).join('')}
            </div>`;
        }

        /* ── Run buttons ──────────────────────────────────────────────────── */
        /* ── Poids ────────────────────────────────────────────────────────── */
        function fmtSize(bytes) {
            if (bytes >= 1e12) return (bytes / 1e12).toFixed(2) + ' To';
            if (bytes >= 1e9)  return (bytes / 1e9).toFixed(1)  + ' Go';
            if (bytes >= 1e6)  return (bytes / 1e6).toFixed(0)  + ' Mo';
            return (bytes / 1e3).toFixed(0) + ' Ko';
        }

        function renderSizes(data) {
            const body = document.getElementById('hc-sizes-body');
            if (!data.results.length) { setOk(body, 'Aucun média trouvé.'); return; }

            const max = data.results[0].size;
            const TYPE_COLORS = { anime: '#60a5fa', film: '#c8a468', serie: '#a78bfa' };
            const types = [...new Set(data.results.map(r => r.type))];

            let filterBtns = '';
            if (types.length > 1) {
                filterBtns = `<div class="health-size-filters">
                    <button class="health-size-filter-btn is-active" data-type="all">Tout</button>
                    ${types.map(t => `<button class="health-size-filter-btn" data-type="${t}" style="--dot:${TYPE_COLORS[t] ?? '#888'}">${esc(TYPE_MAP_JS[t] ?? t)}</button>`).join('')}
                </div>`;
            }

            const rows = data.results.map(r => {
                const pct = Math.max(2, Math.round(r.size / max * 100));
                const color = TYPE_COLORS[r.type] ?? '#888';
                return `<a class="health-size-row" href="${esc(r.href)}" data-type="${r.type}">
                    <span class="health-ep-badge health-ep-badge--${r.type}">${esc(TYPE_MAP_JS[r.type] ?? r.type)}</span>
                    <span class="health-size-title">${esc(r.title)}</span>
                    <span class="health-size-bar-wrap">
                        <span class="health-size-bar" style="width:${pct}%;background:${color}"></span>
                    </span>
                    <span class="health-size-val">${fmtSize(r.size)}</span>
                </a>`;
            }).join('');

            body.innerHTML = filterBtns + `
                <div class="health-size-list" id="health-size-list">${rows}</div>
                <div class="health-size-total">Total : <strong>${fmtSize(data.total)}</strong></div>`;

            body.querySelectorAll('.health-size-filter-btn').forEach(btn => {
                btn.addEventListener('click', () => {
                    body.querySelectorAll('.health-size-filter-btn').forEach(b => b.classList.remove('is-active'));
                    btn.classList.add('is-active');
                    const type = btn.dataset.type;
                    body.querySelectorAll('.health-size-row').forEach(row => {
                        row.style.display = (type === 'all' || row.dataset.type === type) ? '' : 'none';
                    });
                });
            });
        }

        const TYPE_MAP_JS = { anime: 'Anime', film: 'Film', serie: 'Série' };

        /* ── ffprobe ───────────────────────────────────────────────────────── */
        function codecLabel(c) {
            if (!c) return '—';
            const m = { h264: 'H.264', hevc: 'H.265', av1: 'AV1', vp9: 'VP9', mpeg4: 'MPEG-4', mpeg2video: 'MPEG-2' };
            return m[c.toLowerCase()] ?? c.toUpperCase();
        }

        function resLabel(w, h) {
            if (!h) return '—';
            if (h >= 2160) return '4K';
            if (h >= 1080) return '1080p';
            if (h >= 720)  return '720p';
            if (h >= 480)  return '480p';
            return h + 'p';
        }

        function resClass(h) {
            if (!h) return '';
            if (h >= 2160) return 'health-res--4k';
            if (h >= 1080) return 'health-res--1080';
            if (h >= 720)  return 'health-res--720';
            return 'health-res--sd';
        }

        function codecClass(c) {
            if (!c) return '';
            const l = c.toLowerCase();
            if (l === 'hevc' || l === 'av1') return 'health-codec--good';
            if (l === 'h264')                return 'health-codec--ok';
            return 'health-codec--meh';
        }

        function fmtDuration(s) {
            if (!s) return '—';
            const m = Math.floor(s / 60);
            const sec = String(s % 60).padStart(2, '0');
            return m + ':' + sec;
        }

        function fmtBitrate(kb) {
            if (!kb) return '—';
            return kb >= 1000 ? (kb / 1000).toFixed(1) + ' Mb/s' : kb + ' kb/s';
        }

        function renderFfprobe(data) {
            const body = document.getElementById('hc-ffprobe-body');
            if (!data.episodes.length) { setOk(body, 'Aucun fichier vidéo trouvé.'); return; }

            const rows = data.episodes.map((ep, i) => `
                <tr>
                    <td class="health-probe-num">${i + 1}</td>
                    <td class="health-probe-name" title="${esc(ep.name)}">${esc(ep.name.replace(/\.[^.]+$/, ''))}</td>
                    <td><span class="health-codec-badge ${codecClass(ep.codec)}">${esc(codecLabel(ep.codec))}</span></td>
                    <td><span class="health-res-badge ${resClass(ep.height)}">${esc(resLabel(ep.width, ep.height))}</span></td>
                    <td class="health-probe-num">${ep.width && ep.height ? ep.width + '×' + ep.height : '—'}</td>
                    <td class="health-probe-num">${fmtBitrate(ep.bitrate_kb)}</td>
                    <td class="health-probe-num">${fmtSize(ep.size)}</td>
                    <td class="health-probe-num">${fmtDuration(ep.duration_s)}</td>
                </tr>`).join('');

            body.innerHTML = `
                <div class="health-summary health-summary--ok">${data.total} fichier${data.total > 1 ? 's' : ''} · Total : ${fmtSize(data.total_size)}</div>
                <div class="health-probe-table-wrap">
                    <table class="health-probe-table">
                        <thead><tr>
                            <th>#</th><th>Fichier</th><th>Codec</th><th>Résolution</th>
                            <th>Dimensions</th><th>Bitrate</th><th>Taille</th><th>Durée</th>
                        </tr></thead>
                        <tbody>${rows}</tbody>
                    </table>
                </div>`;
        }

        /* ── Probe type / media select ─────────────────────────────────────── */
        let probeType      = 'anime';
        let probeMediaName = '';

        const csWrap  = document.getElementById('probe-cs-wrap');
        const csList  = document.getElementById('probe-cs-list');
        const runBtn  = document.getElementById('probe-run-btn');
        const csLabel = csWrap.querySelector('.cs-label');

        function resetLabel(msg, empty = true) {
            if (empty) csList.innerHTML = '';
            csLabel.textContent = msg;
            csWrap.dataset.value = '';
            probeMediaName = '';
            runBtn.disabled = true;
        }

        async function loadMediaList(type) {
            resetLabel('Chargement…');

            const data = await fetch(`/admin/api/health-data.php?action=media_list&type=${type}`)
                .then(r => r.json()).catch(() => null);

            if (!data?.ok || !data.items.length) {
                resetLabel('Aucun média');
                return;
            }

            csList.innerHTML = data.items.map(i =>
                `<li class="cs-item" data-value="${esc(i.name)}" role="option" aria-selected="false">
                    <span class="cs-check"></span>
                    <span class="cs-body"><span class="cs-item-label">${esc(i.title)}</span></span>
                </li>`
            ).join('');

            resetLabel('— Choisir un média —', false);
            HoshimiSelect.init(csWrap);
        }

        csWrap.addEventListener('cs:change', e => {
            probeMediaName = e.detail.value;
            csLabel.style.color = '';
            runBtn.disabled = !probeMediaName;
        });

        document.getElementById('probe-type-btns').addEventListener('click', async e => {
            const btn = e.target.closest('button[data-type]');
            if (!btn) return;
            document.querySelectorAll('#probe-type-btns button[data-type]').forEach(b => b.classList.remove('is-active'));
            btn.classList.add('is-active');
            probeType = btn.dataset.type;
            await loadMediaList(probeType);
        });

        runBtn.addEventListener('click', async () => {
            if (!probeMediaName) return;
            const body = document.getElementById('hc-ffprobe-body');

            runBtn.disabled = true;
            setLoading(body, 'Analyse ffprobe en cours… (peut prendre quelques secondes)');

            const data = await fetch(`/admin/api/health-data.php?action=ffprobe&type=${probeType}&name=${encodeURIComponent(probeMediaName)}`)
                .then(r => r.json()).catch(() => null);

            runBtn.disabled = !probeMediaName;
            if (!data?.ok) { setError(body, data?.error ?? 'Erreur serveur'); return; }
            renderFfprobe(data);
        });

        // Pré-charger la liste anime au démarrage
        loadMediaList('anime');

        const RENDERERS = {
            episodes: renderEpisodes,
            files:    renderFiles,
            sizes:    renderSizes,
            links:    renderLinks,
        };

        const LOADING_MSGS = {
            episodes: 'Comptage des fichiers vidéo…',
            files:    'Scan des doublons…',
            sizes:    'Calcul des tailles (peut prendre quelques secondes)…',
            links:    'Vérification des URLs (peut prendre ~10s)…',
        };

        document.querySelectorAll('button[data-action]').forEach(btn => {
            btn.addEventListener('click', async () => {
                const action = btn.dataset.action;
                const bodyEl = document.getElementById(`hc-${action}-body`);

                btn.disabled = true;
                setLoading(bodyEl, LOADING_MSGS[action] ?? 'Analyse…');

                const data = await fetch(`/admin/api/health-data.php?action=${action}`)
                    .then(r => r.json())
                    .catch(() => null);

                btn.disabled = false;

                if (!data?.ok) {
                    setError(bodyEl, data?.error ?? 'Erreur serveur');
                    return;
                }

                RENDERERS[action]?.(data);
            });
        });
    })();
    </script>
    <script>(function(){const l=document.getElementById('loader'),c=document.getElementById('adm-content');if(c)c.hidden=false;if(l)l.remove();})();</script>
        </div><!-- #adm-content -->
</body>
</html>
