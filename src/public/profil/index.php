<?php

declare(strict_types=1);

$current_path = '/profil';
require_once __DIR__ . '/../../app/MacGuard.php';
$_hasAccess = MacGuard::hasAccess();
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Profil — Hoshimi</title>
    <link rel="stylesheet" href="/css/hoshimi.css">
    <link rel="stylesheet" href="/css/pages/profil.css">
    <script src="/js/storage.js"></script>
    <script src="/js/confirm.js"></script>
</head>

<body>
    <?php include __DIR__ . '/../components/navbar.php'; ?>

    <main>
        <div class="container profil-container">

            <div class="profil-header">
                <h1 class="profil-header__title">Profil</h1>
            </div>

            <div class="profil-layout">

                <!-- ── Colonne gauche ───────────────────────────────────────── -->
                <aside class="profil-sidebar">

                    <!-- Avatar -->
                    <div class="profil-avatar-card">
                        <button class="profil-avatar profil-avatar--btn" id="avatar-trigger" title="Changer d'avatar" aria-label="Changer d'avatar">
                            <svg class="profil-avatar__fallback" width="26" height="26" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>
                            <img class="profil-avatar__img" id="avatar-img" src="" alt="" style="display:none">
                            <span class="profil-avatar__edit-hint">
                                <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M17 3a2.85 2.83 0 1 1 4 4L7.5 20.5 2 22l1.5-5.5Z"/></svg>
                            </span>
                        </button>
                        <div class="profil-avatar__info">
                            <span class="profil-avatar__name" id="avatar-name-display">Invité</span>
                            <span class="profil-avatar__badge">Données locales</span>
                        </div>
                    </div>

                    <!-- Stats -->
                    <div class="profil-stats">
                        <a href="/favoris/" class="profil-stat" id="stat-favs">
                            <div class="profil-stat__icon profil-stat__icon--fav">
                                <svg width="14" height="14" viewBox="0 0 24 24" fill="currentColor" stroke="currentColor" stroke-width="1" stroke-linecap="round" stroke-linejoin="round"><path d="M19 14c1.49-1.46 3-3.21 3-5.5A5.5 5.5 0 0 0 16.5 3c-1.76 0-3 .5-4.5 2-1.5-1.5-2.74-2-4.5-2A5.5 5.5 0 0 0 2 8.5c0 2.3 1.5 4.05 3 5.5l7 7Z"/></svg>
                            </div>
                            <span class="profil-stat__value">—</span>
                            <span class="profil-stat__label">Favoris</span>
                        </a>
                        <a href="/historique/" class="profil-stat" id="stat-hist">
                            <div class="profil-stat__icon profil-stat__icon--hist">
                                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 12a9 9 0 1 0 9-9 9.75 9.75 0 0 1-6.74 2.74L3 8"/><path d="M3 3v5h5"/><path d="M12 7v5l4 2"/></svg>
                            </div>
                            <span class="profil-stat__value">—</span>
                            <span class="profil-stat__label">Historique</span>
                        </a>
                        <div class="profil-stat" id="stat-progress">
                            <div class="profil-stat__icon profil-stat__icon--progress">
                                <svg width="14" height="14" viewBox="0 0 24 24" fill="currentColor" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M5 5a2 2 0 0 1 3.008-1.728l11.997 6.998a2 2 0 0 1 .003 3.458l-12 7A2 2 0 0 1 5 19z"/></svg>
                            </div>
                            <span class="profil-stat__value">—</span>
                            <span class="profil-stat__label">En cours</span>
                        </div>
                        <div class="profil-stat" id="stat-done">
                            <div class="profil-stat__icon profil-stat__icon--done">
                                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"/></svg>
                            </div>
                            <span class="profil-stat__value">—</span>
                            <span class="profil-stat__label">Terminés</span>
                        </div>
                        <div class="profil-stat profil-stat--wide" id="stat-time">
                            <div class="profil-stat__icon profil-stat__icon--time">
                                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>
                            </div>
                            <span class="profil-stat__value" id="stat-time-val">—</span>
                            <span class="profil-stat__label">Temps regardé</span>
                        </div>
                    </div>

                    <!-- Genre préféré -->
                    <div class="profil-top-genres" id="profil-top-genres" style="display:none">
                        <div class="profil-top-genres__title">Genres préférés</div>
                        <div class="profil-top-genres__list" id="top-genres-list"></div>
                    </div>

                    <!-- Storage -->
                    <div class="profil-storage" id="profil-storage">
                        <div class="profil-storage__header">
                            <span class="profil-storage__label">Stockage local</span>
                            <span class="profil-storage__val" id="storage-val">—</span>
                        </div>
                        <div class="profil-storage__bar">
                            <div class="profil-storage__fill" id="storage-fill" style="width:0%"></div>
                        </div>
                        <div class="profil-storage__footer">
                            <span class="profil-storage__used" id="storage-used-label">0 Ko utilisés</span>
                            <span class="profil-storage__max">sur ~1 Mo</span>
                        </div>
                    </div>

                </aside>

                <!-- ── Colonne droite ───────────────────────────────────────── -->
                <div class="profil-main">

                    <!-- Activité récente -->
                    <section class="profil-section" id="profil-recent-section" style="display:none">
                        <div class="profil-section__header">
                            <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>
                            <h2 class="profil-section__title">Activité récente</h2>
                            <a href="/historique/" class="profil-section__link">Tout voir</a>
                        </div>
                        <div class="profil-recent-list" id="profil-recent-list"></div>
                    </section>

                    <!-- Apparence -->
                    <section class="profil-section">
                        <div class="profil-section__header">
                            <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="3"/><path d="M19.07 4.93a10 10 0 0 1 0 14.14"/><path d="M4.93 4.93a10 10 0 0 0 0 14.14"/></svg>
                            <h2 class="profil-section__title">Apparence</h2>
                        </div>

                        <div class="profil-block">
                            <div class="profil-block__label">Couleur d'accent</div>
                            <div class="profil-block__sub">Personnalise la teinte principale de l'interface.</div>

                            <div class="theme-accents" id="accent-picker">
                                <button class="theme-accent-btn" data-accent="red"    title="Rouge"   style="--swatch:#ef4444"></button>
                                <button class="theme-accent-btn" data-accent="orange" title="Orange"  style="--swatch:#f97316"></button>
                                <button class="theme-accent-btn" data-accent="yellow" title="Jaune"   style="--swatch:#eab308"></button>
                                <button class="theme-accent-btn" data-accent="gold"   title="Or"      style="--swatch:#d4a017"></button>
                                <button class="theme-accent-btn" data-accent="lime"   title="Lime"    style="--swatch:#84cc16"></button>
                                <button class="theme-accent-btn" data-accent="green"  title="Vert"    style="--swatch:#22c55e"></button>
                                <button class="theme-accent-btn" data-accent="dore"   title="Doré"    style="--swatch:#c8a468"></button>
                                <button class="theme-accent-btn" data-accent="teal"   title="Teal"    style="--swatch:#14b8a6"></button>
                                <button class="theme-accent-btn" data-accent="cyan"   title="Cyan"    style="--swatch:#06b6d4"></button>
                                <button class="theme-accent-btn" data-accent="blue"   title="Bleu"    style="--swatch:#3b82f6"></button>
                                <button class="theme-accent-btn" data-accent="indigo" title="Indigo"  style="--swatch:#6366f1"></button>
                                <button class="theme-accent-btn" data-accent="purple" title="Violet"  style="--swatch:#8b5cf6"></button>
                                <button class="theme-accent-btn" data-accent="pink"   title="Rose"    style="--swatch:#ec4899"></button>
                                <button class="theme-accent-btn" data-accent="white"  title="Blanc"   style="--swatch:#e2e8f0"></button>
                                <button class="theme-accent-btn" data-accent="silver" title="Argent"  style="--swatch:#94a3b8"></button>
                            </div>

                            <div class="theme-accent-current" id="accent-label">
                                <span class="theme-accent-current__dot" id="accent-dot"></span>
                                <span id="accent-name">—</span>
                            </div>
                        </div>
                    </section>


                    <!-- Sauvegarde -->
                    <section class="profil-section">
                        <div class="profil-section__header">
                            <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="7 10 12 15 17 10"/><line x1="12" y1="15" x2="12" y2="3"/></svg>
                            <h2 class="profil-section__title">Sauvegarde &amp; transfert</h2>
                        </div>

                        <div class="profil-block">
                            <div class="profil-block__label">Exporter / Importer</div>
                            <div class="profil-block__sub">Exportez votre progression, favoris et statuts pour les retrouver sur un autre appareil.</div>
                            <div class="backup-actions">
                                <button class="backup-btn" id="export-btn">
                                    <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="7 10 12 15 17 10"/><line x1="12" y1="15" x2="12" y2="3"/></svg>
                                    <span class="backup-btn__label">Exporter</span>
                                    <span class="backup-btn__sub">.json</span>
                                </button>
                                <button class="backup-btn" id="import-btn">
                                    <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="17 8 12 3 7 8"/><line x1="12" y1="3" x2="12" y2="15"/></svg>
                                    <span class="backup-btn__label">Importer</span>
                                    <span class="backup-btn__sub">.json</span>
                                </button>
                                <button class="backup-btn" id="copy-link-btn">
                                    <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="9" y="9" width="13" height="13" rx="2" ry="2"/><path d="M5 15H4a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2h9a2 2 0 0 1 2 2v1"/></svg>
                                    <span class="backup-btn__label">Copier</span>
                                    <span class="backup-btn__sub">lien direct</span>
                                </button>
                                <button class="backup-btn" id="qr-btn">
                                    <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="3" width="7" height="7"/><rect x="14" y="3" width="7" height="7"/><rect x="3" y="14" width="7" height="7"/><rect x="5" y="5" width="3" height="3" fill="currentColor" stroke="none"/><rect x="16" y="5" width="3" height="3" fill="currentColor" stroke="none"/><rect x="5" y="16" width="3" height="3" fill="currentColor" stroke="none"/><line x1="14" y1="14" x2="14" y2="14"/><line x1="17" y1="14" x2="21" y2="14"/><line x1="21" y1="17" x2="21" y2="21"/><line x1="14" y1="17" x2="17" y2="17"/><line x1="14" y1="21" x2="17" y2="21"/></svg>
                                    <span class="backup-btn__label">QR Code</span>
                                    <span class="backup-btn__sub">mobile</span>
                                </button>
                                <input type="file" id="import-file" accept=".json" style="display:none">
                            </div>
                        </div>

                        <div class="profil-block profil-block--danger">
                            <div class="profil-danger-row">
                                <div>
                                    <div class="profil-block__label profil-block__label--danger">Réinitialiser les données</div>
                                    <div class="profil-block__sub">Supprime toutes les données locales (progression, favoris, statuts). Action irréversible.</div>
                                </div>
                                <button class="btn btn--danger" id="reset-btn">Réinitialiser</button>
                            </div>
                        </div>
                    </section>

                </div>
            </div>
        </div>
    </main>

    <!-- Avatar picker modal -->
    <div id="avatar-modal" class="avatar-modal-overlay" style="display:none" role="dialog" aria-modal="true" aria-label="Choisir un avatar">
        <div class="avatar-modal">

            <!-- Header -->
            <div class="avatar-modal__head">
                <div>
                    <div class="avatar-modal__eyebrow">Avatar</div>
                    <h3 class="avatar-modal__title">Choisis ton personnage.</h3>
                </div>
                <button class="avatar-modal__close" id="avatar-modal-close" aria-label="Fermer">
                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M18 6 6 18"/><path d="m6 6 12 12"/></svg>
                </button>
            </div>

            <!-- Mode tabs -->
            <div class="avatar-mode-tabs" id="avatar-mode-tabs">
                <button class="avatar-mode-tab is-active" data-mode="browse">
                    <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="3" width="7" height="7"/><rect x="14" y="3" width="7" height="7"/><rect x="3" y="14" width="7" height="7"/><rect x="14" y="14" width="7" height="7"/></svg>
                    Parcourir
                </button>
                <button class="avatar-mode-tab" data-mode="search">
                    <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="11" cy="11" r="8"/><path d="m21 21-4.35-4.35"/></svg>
                    Rechercher
                </button>
            </div>

            <!-- Browse panel -->
            <div class="avatar-panel" id="avatar-panel-browse">
                <div class="avatar-anime-list" id="avatar-anime-list">
                    <div class="avatar-loading" id="avatar-browse-loading">
                        <span class="avatar-loading__dot"></span><span class="avatar-loading__dot"></span><span class="avatar-loading__dot"></span>
                    </div>
                </div>
                <div class="avatar-load-more-wrap">
                    <button class="btn btn--ghost btn--large" id="avatar-load-more" style="display:none">Voir plus d'animes</button>
                </div>
            </div>

            <!-- Search panel -->
            <div class="avatar-panel" id="avatar-panel-search" style="display:none">
                <div class="avatar-search-wrap">
                    <svg class="avatar-search-icon" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="11" cy="11" r="8"/><path d="m21 21-4.35-4.35"/></svg>
                    <input type="text" class="avatar-search-input" id="avatar-search-input" placeholder="Nom du personnage…" autocomplete="off">
                </div>
                <div class="avatar-search-grid" id="avatar-search-grid"></div>
                <div class="avatar-loading" id="avatar-search-loading" style="display:none">
                    <span class="avatar-loading__dot"></span><span class="avatar-loading__dot"></span><span class="avatar-loading__dot"></span>
                </div>
            </div>

            <!-- Footer -->
            <div class="avatar-modal__footer">
                <div class="avatar-modal__name-row">
                    <input type="text" class="avatar-modal__name-input" id="avatar-name-input" placeholder="Nom d'affichage" maxlength="24">
                </div>
                <div class="avatar-modal__actions">
                    <button class="btn btn--ghost btn--red" id="avatar-delete" style="display:none">Supprimer l'avatar actuel</button>
                    <button class="btn btn--ghost" id="avatar-cancel">Annuler</button>
                    <button class="btn btn--primary" id="avatar-confirm" disabled>Confirmer</button>
                </div>
            </div>

        </div>
    </div>

    <!-- QR modal -->
    <div id="qr-modal" class="qr-modal-overlay" style="display:none;">
        <div class="qr-modal">
            <button class="qr-modal__close" id="qr-modal-close">
                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M18 6 6 18"/><path d="m6 6 12 12"/></svg>
            </button>
            <h3 class="qr-modal__title">Scanner sur mobile</h3>
            <p class="qr-modal__sub">Scannez ce code avec votre téléphone pour importer votre progression. Valable <strong>5 minutes</strong>.</p>
            <div id="qr-container" class="qr-modal__canvas"></div>
            <p class="qr-modal__url" id="qr-url"></p>
            <p class="qr-modal__loading" id="qr-loading">Génération en cours&hellip;</p>
        </div>
    </div>

    <?php include __DIR__ . '/../components/footer.php'; ?>

    <script src="/js/guard.js"></script>
    <script>
    // ── Stats sidebar ─────────────────────────────────────────────────────────
    (async function loadStats() {
        const favs = JSON.parse(localStorage.getItem('hoshimi_favorites') || '{}');
        const prog = JSON.parse(localStorage.getItem('hoshimi_progress')  || '{}');

        const favCount  = Object.keys(favs).length;
        const progVals  = Object.values(prog);
        const inProg    = progVals.filter(p => !p.completed).length;
        const done      = progVals.filter(p =>  p.completed).length;
        const histTotal = progVals.length;

        // Temps total regardé (somme des positions)
        const totalSecs = progVals.reduce((s, p) => s + (p.position || 0), 0);
        function formatTime(sec) {
            if (sec < 3600)  return Math.round(sec / 60) + ' min';
            if (sec < 86400) return (sec / 3600).toFixed(1).replace('.0','') + ' h';
            const days = Math.floor(sec / 86400);
            const hrs  = Math.round((sec % 86400) / 3600);
            return days + 'j ' + (hrs ? hrs + 'h' : '');
        }
        document.getElementById('stat-time-val').textContent = totalSecs > 0 ? formatTime(totalSecs) : '0 min';

        function countUp(el, target, duration) {
            if (window.matchMedia('(prefers-reduced-motion: reduce)').matches || target === 0) {
                el.textContent = target; return;
            }
            const start = performance.now();
            function step(now) {
                const pct = Math.min((now - start) / duration, 1);
                const val = Math.round(pct * pct * target);
                el.textContent = val;
                if (pct < 1) requestAnimationFrame(step);
            }
            requestAnimationFrame(step);
        }

        countUp(document.querySelector('#stat-favs .profil-stat__value'),    favCount,  900);
        countUp(document.querySelector('#stat-progress .profil-stat__value'), inProg,    700);
        countUp(document.querySelector('#stat-done .profil-stat__value'),     done,      700);
        countUp(document.querySelector('#stat-hist .profil-stat__value'),     histTotal, 800);

        // Genres préférés + activité récente via shows-index
        try {
            const data = await fetch('/api/shows-index.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ file_keys: Object.keys(prog) }),
            }).then(r => r.json());

            // Index fileKey → show
            const fileIndex = {};
            Object.values(data).forEach(show => {
                (show.episodes ?? []).forEach(ep => { fileIndex[ep.file_key] = show; });
            });

            // Genres depuis la progression
            const genreCounts = {};
            Object.entries(prog).forEach(([key]) => {
                const show = fileIndex[key];
                if (!show) return;
                (show.genres ?? []).forEach(g => { genreCounts[g] = (genreCounts[g] ?? 0) + 1; });
            });

            const topGenres = Object.entries(genreCounts)
                .sort((a, b) => b[1] - a[1]).slice(0, 5);

            if (topGenres.length > 0) {
                const max = topGenres[0][1];
                const listEl = document.getElementById('top-genres-list');
                listEl.innerHTML = topGenres.map(([g, n]) => `
                    <div class="profil-genre-row">
                        <span class="profil-genre-row__name">${g}</span>
                        <div class="profil-genre-row__bar">
                            <div class="profil-genre-row__fill" style="width:${Math.round(n/max*100)}%"></div>
                        </div>
                        <span class="profil-genre-row__count">${n}</span>
                    </div>`).join('');
                document.getElementById('profil-top-genres').style.display = '';
            }

            // Activité récente (5 derniers)
            const recent = Object.entries(prog)
                .filter(([, p]) => p.updated_at)
                .sort((a, b) => b[1].updated_at.localeCompare(a[1].updated_at))
                .slice(0, 5);

            if (recent.length > 0) {
                const timeAgo = iso => {
                    const d = Math.floor((Date.now() - new Date(iso)) / 1000);
                    if (d < 60) return 'À l\'instant';
                    if (d < 3600) return Math.floor(d/60) + ' min';
                    if (d < 86400) return Math.floor(d/3600) + ' h';
                    return Math.floor(d/86400) + ' j';
                };
                const listEl = document.getElementById('profil-recent-list');
                listEl.innerHTML = recent.map(([key, p]) => {
                    const show   = fileIndex[key];
                    const title  = show ? show.show_title : '—';
                    const cover  = show ? (show.cover_url || '') : '';
                    const pct    = p.duration > 0 ? Math.min(100, Math.round(p.position / p.duration * 100)) : 0;
                    const status = p.completed ? 'Terminé' : pct + '%';
                    return `<div class="profil-recent-row">
                        ${cover ? `<img class="profil-recent-row__cover" src="${cover}" alt="" loading="lazy">` : '<div class="profil-recent-row__cover profil-recent-row__cover--empty"></div>'}
                        <div class="profil-recent-row__info">
                            <span class="profil-recent-row__title">${title}</span>
                            <span class="profil-recent-row__meta">${status} · ${timeAgo(p.updated_at)}</span>
                        </div>
                        <div class="profil-recent-row__bar">
                            <div class="profil-recent-row__fill ${p.completed ? 'profil-recent-row__fill--done' : ''}" style="width:${pct}%"></div>
                        </div>
                    </div>`;
                }).join('');
                document.getElementById('profil-recent-section').style.display = '';
            }
        } catch {}


        // Storage estimate
        const BACKUP_KEYS = ['hoshimi_progress','hoshimi_favorites','hoshimi_watchlist','hoshimi_lists','hoshimi_accent','hoshimi_avatar','hoshimi_username'];
        let bytes = 0;
        BACKUP_KEYS.forEach(k => { const v = localStorage.getItem(k); if (v) bytes += v.length * 2; });
        const kb  = (bytes / 1024).toFixed(1);
        const pct = Math.min(100, (bytes / (1 * 1024 * 1024)) * 100).toFixed(1);
        document.getElementById('storage-val').textContent         = kb + ' Ko';
        document.getElementById('storage-fill').style.width        = pct + '%';
        const usedLabel = document.getElementById('storage-used-label');
        if (usedLabel) usedLabel.textContent = kb + ' Ko utilisés';

        // Color the bar based on usage
        const fill = document.getElementById('storage-fill');
        if (parseFloat(pct) > 75) fill.classList.add('profil-storage__fill--warn');
        else if (parseFloat(pct) > 50) fill.classList.add('profil-storage__fill--mid');
    })();

    // ── Accent couleur ────────────────────────────────────────────────────────
    const B = { bg: '#0f0f0f', card: '#131313', elevated: '#1a1a1a', surface: '#222222' };
    const ACCENTS = {
        red:     { color: '#ef4444', rgb: '239,68,68',    dim: 'rgba(239,68,68,0.15)',    glow: 'rgba(239,68,68,0.25)',    text: '#fff',    name: 'Rouge',  ...B },
        orange:  { color: '#f97316', rgb: '249,115,22',   dim: 'rgba(249,115,22,0.15)',   glow: 'rgba(249,115,22,0.25)',   text: '#1a1a1a', name: 'Orange', ...B },
        yellow:  { color: '#eab308', rgb: '234,179,8',    dim: 'rgba(234,179,8,0.15)',    glow: 'rgba(234,179,8,0.25)',    text: '#1a1a1a', name: 'Jaune',  ...B },
        gold:    { color: '#d4a017', rgb: '212,160,23',   dim: 'rgba(212,160,23,0.15)',   glow: 'rgba(212,160,23,0.25)',   text: '#1a1a1a', name: 'Or',     ...B },
        lime:    { color: '#84cc16', rgb: '132,204,22',   dim: 'rgba(132,204,22,0.15)',   glow: 'rgba(132,204,22,0.25)',   text: '#1a1a1a', name: 'Lime',   ...B },
        green:   { color: '#22c55e', rgb: '34,197,94',    dim: 'rgba(34,197,94,0.15)',    glow: 'rgba(34,197,94,0.25)',    text: '#1a1a1a', name: 'Vert',   ...B },
        dore:    { color: '#c8a468', rgb: '200,164,104',  dim: 'rgba(200,164,104,0.15)',  glow: 'rgba(200,164,104,0.25)',  text: '#1a1a1a', name: 'Dore',   ...B },
        teal:    { color: '#14b8a6', rgb: '20,184,166',   dim: 'rgba(20,184,166,0.15)',   glow: 'rgba(20,184,166,0.25)',   text: '#1a1a1a', name: 'Teal',   ...B },
        cyan:    { color: '#06b6d4', rgb: '6,182,212',    dim: 'rgba(6,182,212,0.15)',    glow: 'rgba(6,182,212,0.25)',    text: '#1a1a1a', name: 'Cyan',   ...B },
        blue:    { color: '#3b82f6', rgb: '59,130,246',   dim: 'rgba(59,130,246,0.15)',   glow: 'rgba(59,130,246,0.25)',   text: '#fff',    name: 'Bleu',   ...B },
        indigo:  { color: '#6366f1', rgb: '99,102,241',   dim: 'rgba(99,102,241,0.15)',   glow: 'rgba(99,102,241,0.25)',   text: '#fff',    name: 'Indigo', ...B },
        purple:  { color: '#8b5cf6', rgb: '139,92,246',   dim: 'rgba(139,92,246,0.15)',   glow: 'rgba(139,92,246,0.25)',   text: '#fff',    name: 'Violet', ...B },
        pink:    { color: '#ec4899', rgb: '236,72,153',   dim: 'rgba(236,72,153,0.15)',   glow: 'rgba(236,72,153,0.25)',   text: '#fff',    name: 'Rose',   ...B },
        white:   { color: '#e2e8f0', rgb: '226,232,240',  dim: 'rgba(226,232,240,0.15)',  glow: 'rgba(226,232,240,0.25)',  text: '#1a1a1a', name: 'Blanc',  ...B },
        silver:  { color: '#94a3b8', rgb: '148,163,184',  dim: 'rgba(148,163,184,0.15)',  glow: 'rgba(148,163,184,0.25)',  text: '#1a1a1a', name: 'Argent', ...B },
    };

    const ACCENT_KEY = 'hoshimi_accent';

    function applyAccent(key) {
        const a = ACCENTS[key] ?? ACCENTS.gold;
        const r = document.documentElement;
        r.style.setProperty('--color-accent',      a.color);
        r.style.setProperty('--color-accent-rgb',  a.rgb);
        r.style.setProperty('--color-accent-dim',  a.dim);
        r.style.setProperty('--color-accent-glow', a.glow);
        r.style.setProperty('--color-accent-text', a.text);
        r.style.setProperty('--color-bg',          a.bg);
        r.style.setProperty('--color-bg-card',     a.card);
        r.style.setProperty('--color-bg-elevated', a.elevated);
        r.style.setProperty('--color-surface',     a.surface);
        const dot  = document.getElementById('accent-dot');
        const name = document.getElementById('accent-name');
        if (dot)  dot.style.background  = a.color;
        if (name) name.textContent = a.name ?? key;
    }

    function loadAccent() {
        const saved = localStorage.getItem(ACCENT_KEY) ?? 'gold';
        applyAccent(saved);
        document.querySelectorAll('.theme-accent-btn').forEach(btn => {
            btn.classList.toggle('is-active', btn.dataset.accent === saved);
        });
    }

    document.getElementById('accent-picker').addEventListener('click', e => {
        const btn = e.target.closest('.theme-accent-btn');
        if (!btn) return;
        const key = btn.dataset.accent;
        localStorage.setItem(ACCENT_KEY, key);
        applyAccent(key);
        document.querySelectorAll('.theme-accent-btn').forEach(b => b.classList.toggle('is-active', b === btn));
    });

    loadAccent();

    // ── Backup ────────────────────────────────────────────────────────────────
    const BACKUP_KEYS = ['hoshimi_progress', 'hoshimi_favorites', 'hoshimi_watchlist', 'hoshimi_lists', 'hoshimi_accent', 'hoshimi_avatar', 'hoshimi_username'];

    function collectBackup() {
        const data = {};
        BACKUP_KEYS.forEach(k => {
            const v = localStorage.getItem(k);
            if (v === null) return;
            try { data[k] = JSON.parse(v); } catch { data[k] = v; }
        });
        return data;
    }

    function restoreBackup(data) {
        let count = 0;
        BACKUP_KEYS.forEach(k => {
            if (data[k] === undefined) return;
            const val = typeof data[k] === 'string' ? data[k] : JSON.stringify(data[k]);
            localStorage.setItem(k, val);
            count++;
        });
        return count;
    }

    document.getElementById('export-btn').addEventListener('click', () => {
        const data = collectBackup();
        const blob = new Blob([JSON.stringify(data, null, 2)], { type: 'application/json' });
        const url  = URL.createObjectURL(blob);
        const a    = document.createElement('a');
        a.href = url; a.download = 'hoshimi-backup-' + new Date().toISOString().slice(0, 10) + '.json';
        a.click(); URL.revokeObjectURL(url);
        showToast('Sauvegarde exportee');
    });

    document.getElementById('import-btn').addEventListener('click', () => document.getElementById('import-file').click());

    document.getElementById('import-file').addEventListener('change', e => {
        const file = e.target.files[0]; if (!file) return;
        const reader = new FileReader();
        reader.onload = ev => {
            try {
                const data = JSON.parse(ev.target.result);
                const count = restoreBackup(data);
                showToast(count + ' cle(s) importee(s)');
                setTimeout(() => location.reload(), 1200);
            } catch { showToast('Fichier invalide'); }
        };
        reader.readAsText(file);
        e.target.value = '';
    });

    document.getElementById('copy-link-btn').addEventListener('click', async () => {
        const data = collectBackup();
        const json = JSON.stringify(data);
        let encoded;
        try {
            const cs = new CompressionStream('deflate-raw');
            const writer = cs.writable.getWriter();
            writer.write(new TextEncoder().encode(json)); writer.close();
            const buf = await new Response(cs.readable).arrayBuffer();
            encoded = btoa(String.fromCharCode(...new Uint8Array(buf)));
        } catch { encoded = btoa(unescape(encodeURIComponent(json))); }
        if (encoded.length > 200000) { showToast('Donnees trop volumineuses'); return; }
        const url = location.origin + '/profil/?restore=' + encodeURIComponent(encoded);
        await navigator.clipboard.writeText(url);
        showToast('Lien copie dans le presse-papier');
    });

    (async function restoreFromLink() {
        const encoded = new URLSearchParams(location.search).get('restore');
        if (!encoded) return;
        try {
            let json;
            try {
                const bytes = Uint8Array.from(atob(decodeURIComponent(encoded)), c => c.charCodeAt(0));
                const ds = new DecompressionStream('deflate-raw');
                const writer = ds.writable.getWriter(); writer.write(bytes); writer.close();
                json = await new Response(ds.readable).text();
            } catch { json = decodeURIComponent(escape(atob(decodeURIComponent(encoded)))); }
            const data = JSON.parse(json);
            if (await HoshimiConfirm.ask('Importer la sauvegarde depuis ce lien ?', { confirm: 'Importer' })) {
                restoreBackup(data);
                history.replaceState(null, '', location.origin + location.pathname);
                showToast('Donnees restaurees');
                setTimeout(() => location.reload(), 1200);
            } else { history.replaceState(null, '', location.origin + location.pathname); }
        } catch { showToast('Lien invalide ou corrompu'); }
    })();

    (async function restoreFromToken() {
        const token = new URLSearchParams(location.search).get('restore_token');
        if (!token) return;
        history.replaceState(null, '', location.origin + location.pathname);
        try {
            const resp = await fetch('/api/backup/?token=' + encodeURIComponent(token));
            if (!resp.ok) throw new Error('Token introuvable ou expire');
            const data = await resp.json();
            if (await HoshimiConfirm.ask('Importer la sauvegarde depuis le QR code ?', { confirm: 'Importer' })) {
                restoreBackup(data);
                showToast('Donnees restaurees');
                setTimeout(() => location.reload(), 1200);
            }
        } catch (err) { showToast(err.message ?? 'QR code invalide ou expire'); }
    })();

    const LAN_ORIGIN = '<?= (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http') . '://' . (!empty($_ENV['LAN_HOST']) ? $_ENV['LAN_HOST'] : ($_SERVER['HTTP_HOST'] ?? 'localhost')) ?>';

    document.getElementById('qr-btn').addEventListener('click', async () => {
        const modal     = document.getElementById('qr-modal');
        const loading   = document.getElementById('qr-loading');
        const qrUrl     = document.getElementById('qr-url');
        const container = document.getElementById('qr-container');
        container.innerHTML = ''; loading.style.display = 'block'; qrUrl.textContent = ''; modal.style.display = 'flex';
        try {
            const data = collectBackup();
            const resp = await fetch('/api/backup/', { method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify(data) });
            if (!resp.ok) throw new Error('Erreur serveur');
            const { token } = await resp.json();
            const url = LAN_ORIGIN + '/profil/?restore_token=' + token;
            qrUrl.textContent = url;
            const qr = qrcode(0, 'M'); qr.addData(url); qr.make();
            const img = document.createElement('img');
            img.src = qr.createDataURL(5, 8); img.alt = 'QR Code';
            img.style.cssText = 'width:220px;height:220px;border-radius:8px;';
            container.appendChild(img); loading.style.display = 'none';
        } catch (err) { loading.textContent = 'Erreur : ' + (err.message ?? 'impossible de generer le QR code'); }
    });

    document.getElementById('qr-modal-close').addEventListener('click', () => { document.getElementById('qr-modal').style.display = 'none'; });
    document.getElementById('qr-modal').addEventListener('click', e => { if (e.target === e.currentTarget) e.currentTarget.style.display = 'none'; });

    document.getElementById('reset-btn').addEventListener('click', async () => {
        if (!await HoshimiConfirm.ask('Supprimer toutes les données locales ? Cette action est irréversible.', { confirm: 'Supprimer', type: 'danger' })) return;
        BACKUP_KEYS.forEach(k => localStorage.removeItem(k));
        showToast('Donnees reinitialisees');
        setTimeout(() => location.reload(), 1200);
    });

    // ── Avatar picker (AniList — client-side, CORS OK) ───────────────────────
    (function () {
        const AVATAR_KEY    = 'hoshimi_avatar';
        const NAME_KEY      = 'hoshimi_username';
        const ANILIST_URL   = 'https://graphql.anilist.co';
        const CACHE_PREFIX  = 'hoshimi_anicache_';

        let pendingAvatar = null;
        let browseLoaded  = false;
        let browsePage    = 1;
        let browseHasMore = true;
        let browseLoading = false;
        let searchTimer   = null;
        let searchPage    = 1;
        let searchHasMore = false;
        let currentMode   = 'browse';

        // ── AniList cache — un seul objet JSON dans localStorage ─────────────
        const CACHE_KEY = 'hoshimi_anicache';
        const CACHE_TTL = 86400000; // 24h

        function cacheLoad() {
            try { return JSON.parse(localStorage.getItem(CACHE_KEY) || '{}'); } catch { return {}; }
        }
        function cacheSave(store) {
            try { localStorage.setItem(CACHE_KEY, JSON.stringify(store)); } catch {}
        }
        function cacheGet(key) {
            const store = cacheLoad();
            const entry = store[key];
            if (!entry) return null;
            if (Date.now() - entry.ts > CACHE_TTL) { delete store[key]; cacheSave(store); return null; }
            return entry.data;
        }
        function cacheSet(key, data) {
            const store = cacheLoad();
            store[key] = { data, ts: Date.now() };
            cacheSave(store);
        }

        // ── AniList GraphQL ───────────────────────────────────────────────────
        async function anilistQuery(query, variables) {
            const res  = await fetch(ANILIST_URL, {
                method:  'POST',
                headers: { 'Content-Type': 'application/json', 'Accept': 'application/json' },
                body:    JSON.stringify({ query, variables }),
            });
            const json = await res.json().catch(() => ({}));
            return json.data ?? null;
        }

        // ── DOM ──────────────────────────────────────────────────────────────
        const modal       = document.getElementById('avatar-modal');
        const animeList   = document.getElementById('avatar-anime-list');
        const loadMoreBtn = document.getElementById('avatar-load-more');
        const browseLoad  = document.getElementById('avatar-browse-loading');
        const searchGrid  = document.getElementById('avatar-search-grid');
        const searchLoad  = document.getElementById('avatar-search-loading');
        const searchInput = document.getElementById('avatar-search-input');
        const confirmBtn  = document.getElementById('avatar-confirm');
        const deleteBtn   = document.getElementById('avatar-delete');
        const nameInput   = document.getElementById('avatar-name-input');
        const panelBrowse = document.getElementById('avatar-panel-browse');
        const panelSearch = document.getElementById('avatar-panel-search');

        function lockScroll()   { document.documentElement.style.overflow = 'hidden'; }
        function unlockScroll() { document.documentElement.style.overflow = ''; }

        // ── Apply avatar to UI ───────────────────────────────────────────────
        function applyAvatar(url, name) {
            const img      = document.getElementById('avatar-img');
            const fallback = document.querySelector('.profil-avatar__fallback');
            const nameEl   = document.getElementById('avatar-name-display');
            if (url) {
                img.src = url; img.style.display = 'block';
                if (fallback) fallback.style.display = 'none';
            } else {
                img.style.display = 'none';
                if (fallback) fallback.style.display = '';
            }
            if (nameEl && name) nameEl.textContent = name;
            const navBtn = document.querySelector('.navbar__profil-btn');
            if (navBtn && url) {
                let navImg = navBtn.querySelector('.navbar__profil-avatar');
                if (!navImg) {
                    navImg = document.createElement('img');
                    navImg.className = 'navbar__profil-avatar';
                    navImg.alt = '';
                    navBtn.querySelector('svg')?.remove();
                    navBtn.appendChild(navImg);
                }
                navImg.src = url;
            }
        }

        // ── Select character ─────────────────────────────────────────────────
        function selectChar(url, name) {
            pendingAvatar = url;
            modal.querySelectorAll('.avatar-char-btn').forEach(b =>
                b.classList.toggle('is-selected', b.dataset.url === url));
            confirmBtn.disabled = false;
        }

        // ── Build char button ────────────────────────────────────────────────
        function makeCharBtn(char) {
            const btn = document.createElement('button');
            btn.className = 'avatar-char-btn' + (pendingAvatar === char.image ? ' is-selected' : '');
            btn.dataset.url  = char.image;
            btn.dataset.name = char.name;
            btn.title        = char.name;
            const img = document.createElement('img');
            img.src     = char.image;
            img.alt     = char.name;
            img.loading = 'lazy';
            btn.appendChild(img);
            if (char.from) {
                const lbl = document.createElement('span');
                lbl.className   = 'avatar-char-from';
                lbl.textContent = char.from;
                btn.appendChild(lbl);
            }
            btn.addEventListener('click', () => selectChar(char.image, char.name));
            return btn;
        }

        // ── Browse: append page ──────────────────────────────────────────────
        const BROWSE_GQL = `
        query ($page: Int) {
          Page(page: $page, perPage: 8) {
            pageInfo { hasNextPage }
            media(sort: POPULARITY_DESC, type: ANIME, isAdult: false) {
              title { english romaji }
              characters(sort: FAVOURITES_DESC, perPage: 9) {
                nodes { id name { full } image { large } }
              }
            }
          }
        }`;

        const SEARCH_GQL = `query($search:String,$page:Int){Page(page:$page,perPage:20){pageInfo{hasNextPage}characters(search:$search){id name{full}image{large}media(perPage:1,sort:[POPULARITY_DESC]){nodes{title{english romaji}}}}}}`;


        async function loadBrowsePage() {
            if (browseLoading || !browseHasMore) return;
            browseLoading = true;
            loadMoreBtn.style.display = 'none';

            try {
                const cacheKey = `browse_p${browsePage}`;
                let result = cacheGet(cacheKey);

                if (!result) {
                    const data = await anilistQuery(BROWSE_GQL, { page: browsePage });
                    if (!data) throw new Error('no data');

                    result = {
                        hasNextPage: data.Page.pageInfo.hasNextPage,
                        animes: (data.Page.media ?? []).map(m => ({
                            title: m.title.english ?? m.title.romaji ?? '?',
                            characters: (m.characters.nodes ?? [])
                                .filter(c => c.image?.large)
                                .map(c => ({ id: c.id, name: c.name.full, image: c.image.large })),
                        })).filter(a => a.characters.length > 0),
                    };
                    cacheSet(cacheKey, result);
                }

                result.animes.forEach(anime => {
                    const section = document.createElement('div');
                    section.className = 'avatar-anime-section';
                    const titleEl = document.createElement('div');
                    titleEl.className   = 'avatar-anime-title';
                    titleEl.textContent = anime.title;
                    section.appendChild(titleEl);
                    const row = document.createElement('div');
                    row.className = 'avatar-char-row';
                    anime.characters.forEach(c => row.appendChild(makeCharBtn(c)));
                    section.appendChild(row);
                    animeList.appendChild(section);
                });

                browseHasMore = result.hasNextPage;
                browsePage++;
                if (browseHasMore) loadMoreBtn.style.display = '';

            } catch {
                const err = document.createElement('p');
                err.className   = 'avatar-error';
                err.textContent = 'Impossible de charger les personnages. Réessaie plus tard.';
                animeList.appendChild(err);
            } finally {
                browseLoad.style.display = 'none';
                browseLoading = false;
            }
        }

        loadMoreBtn.addEventListener('click', loadBrowsePage);

        // ── Search ───────────────────────────────────────────────────────────
        async function runSearch(q, page = 1) {
            if (!q) { searchGrid.innerHTML = ''; return; }

            searchLoad.style.display = 'block';
            if (page === 1) searchGrid.innerHTML = '';

            try {
                const cacheKey = `search_${q.toLowerCase()}_p${page}`;
                let result = cacheGet(cacheKey);

                if (!result) {
                    const data = await anilistQuery(SEARCH_GQL, { search: q, page });
                    if (!data) throw new Error('no data');
                    result = {
                        hasNextPage: data.Page.pageInfo.hasNextPage,
                        characters: (data.Page.characters ?? [])
                            .filter(c => c.image?.large)
                            .map(c => {
                                const mn = c.media?.nodes?.[0];
                                return {
                                    id:    c.id,
                                    name:  c.name.full,
                                    image: c.image.large,
                                    from:  mn ? (mn.title.english ?? mn.title.romaji ?? '') : '',
                                };
                            }),
                    };
                    cacheSet(cacheKey, result);
                }

                result.characters.forEach(c => searchGrid.appendChild(makeCharBtn(c)));
                searchHasMore = result.hasNextPage;
                searchPage    = page + 1;

            } catch {
                if (page === 1) searchGrid.innerHTML = '<p class="avatar-error">Erreur lors de la recherche.</p>';
            } finally {
                searchLoad.style.display = 'none';
            }
        }

        searchInput.addEventListener('input', () => {
            clearTimeout(searchTimer);
            searchTimer = setTimeout(() => {
                searchPage = 1; searchHasMore = false;
                runSearch(searchInput.value.trim());
            }, 420);
        });

        // ── Mode tabs ────────────────────────────────────────────────────────
        document.getElementById('avatar-mode-tabs').addEventListener('click', e => {
            const tab = e.target.closest('.avatar-mode-tab');
            if (!tab) return;
            currentMode = tab.dataset.mode;
            document.querySelectorAll('.avatar-mode-tab').forEach(t =>
                t.classList.toggle('is-active', t === tab));
            panelBrowse.style.display = currentMode === 'browse' ? '' : 'none';
            panelSearch.style.display = currentMode === 'search' ? '' : 'none';
        });

        // ── Open / close ─────────────────────────────────────────────────────
        function openModal() {
            pendingAvatar = localStorage.getItem(AVATAR_KEY) || null;
            nameInput.value = localStorage.getItem(NAME_KEY) || '';
            confirmBtn.disabled = !pendingAvatar;
            deleteBtn.style.display = pendingAvatar ? '' : 'none';

            // Sync any existing selection
            modal.querySelectorAll('.avatar-char-btn').forEach(b =>
                b.classList.toggle('is-selected', b.dataset.url === pendingAvatar));

            modal.style.display = 'flex';
            lockScroll();

            if (!browseLoaded) {
                browseLoaded = true;
                loadBrowsePage();
            }
        }

        function closeModal() {
            modal.style.display = 'none';
            unlockScroll();
        }

        document.getElementById('avatar-trigger').addEventListener('click', openModal);
        document.getElementById('avatar-modal-close').addEventListener('click', closeModal);
        document.getElementById('avatar-cancel').addEventListener('click', closeModal);
        modal.addEventListener('click', e => { if (e.target === modal) closeModal(); });
        document.addEventListener('keydown', e => {
            if (e.key === 'Escape' && modal.style.display !== 'none') closeModal();
        });

        // ── Confirm ──────────────────────────────────────────────────────────
        confirmBtn.addEventListener('click', () => {
            const name = nameInput.value.trim() || 'Invité';
            if (pendingAvatar) localStorage.setItem(AVATAR_KEY, pendingAvatar);
            localStorage.setItem(NAME_KEY, name);
            applyAvatar(pendingAvatar, name);
            closeModal();
            showToast('Avatar mis à jour');
        });

        // ── Delete ───────────────────────────────────────────────────────────
        deleteBtn.addEventListener('click', () => {
            localStorage.removeItem(AVATAR_KEY);
            pendingAvatar = null;
            confirmBtn.disabled = true;
            deleteBtn.style.display = 'none';
            modal.querySelectorAll('.avatar-char-btn').forEach(b => b.classList.remove('is-selected'));
            applyAvatar(null, nameInput.value.trim() || 'Invité');
            closeModal();
            showToast('Avatar supprimé');
        });

        // ── Init ─────────────────────────────────────────────────────────────
        const savedUrl  = localStorage.getItem(AVATAR_KEY);
        const savedName = localStorage.getItem(NAME_KEY);
        if (savedUrl || savedName) applyAvatar(savedUrl, savedName || 'Invité');
    })();

    </script>
    <script src="https://cdn.jsdelivr.net/npm/qrcode-generator@1.4.4/qrcode.js"></script>
</body>
</html>
