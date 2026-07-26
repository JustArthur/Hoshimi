<?php

declare(strict_types=1);

$current_path = '/historique';
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Mon Historique — Hoshimi</title>
    <link rel="stylesheet" href="/css/hoshimi.css">
    <link rel="stylesheet" href="/css/pages/historique.css">
    <script src="/js/storage.js"></script>
    <script src="/js/confirm.js"></script>
</head>
<body>
    <?php include __DIR__ . '/../components/navbar.php'; ?>

    <main class="fav-page">

        <!-- ── Hero ─────────────────────────────────────────────────────────── -->
        <?php
        ob_start(); ?>
        <form class="page-hero__search" id="hist-search-form">
            <svg class="page-hero__search-icon" xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="11" cy="11" r="8"/><path d="m21 21-4.3-4.3"/></svg>
            <input type="search" id="hist-search" class="fav-search__input" placeholder="Rechercher…" autocomplete="off">
        </form>
        <?php
        $heroTitle   = 'Historique';
        $heroSub     = 'Tout ce que vous avez regardé, là où vous vous êtes arrêté.';
        $heroExtra   = ob_get_clean();
        include __DIR__ . '/../components/page-hero.php'; ?>

        <!-- ── Barre de contrôles (sticky) ──────────────────────────────────── -->
        <div class="fav-controls-bar" id="hist-controls-bar">
            <div class="container fav-controls-bar__inner">

                <div class="fav-tabs" id="hist-tabs"></div>

                <span class="fav-limit-counter" id="hist-limit-counter"></span>

                <div class="fav-controls-bar__right">
                    <button class="btn btn--ghost btn--red btn--small" id="hist-clear-btn" title="Effacer l'historique">
                        <svg xmlns="http://www.w3.org/2000/svg" width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><polyline points="3 6 5 6 21 6"/><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"/></svg>
                        Tout effacer
                    </button>
                    <div class="cs-wrap fav-sort-wrap" id="hist-status-wrap" style="display:none">
                        <button class="cs-btn" id="hist-status-btn" aria-haspopup="listbox" aria-expanded="false">
                            <svg xmlns="http://www.w3.org/2000/svg" width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><polygon points="22 3 2 3 10 12.46 10 19 14 21 14 12.46 22 3"/></svg>
                            <span id="hist-status-label">Tous</span>
                            <svg class="cs-chevron" width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><polyline points="6 9 12 15 18 9"/></svg>
                        </button>
                        <ul class="cs-list" id="hist-status-dropdown" role="listbox" style="right:0;left:auto;min-width:180px;width:max-content"></ul>
                    </div>

                    <div class="cs-wrap fav-sort-wrap" id="hist-sort-wrap">
                        <button class="cs-btn fav-sort-btn" id="hist-sort-btn" aria-haspopup="listbox" aria-expanded="false">
                            <svg xmlns="http://www.w3.org/2000/svg" width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><line x1="4" y1="6" x2="11" y2="6"/><line x1="4" y1="12" x2="11" y2="12"/><line x1="4" y1="18" x2="13" y2="18"/><polyline points="15 15 18 18 21 15"/><line x1="18" y1="6" x2="18" y2="18"/></svg>
                            <span id="hist-sort-label">Récents</span>
                            <svg class="cs-chevron" width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><polyline points="6 9 12 15 18 9"/></svg>
                        </button>
                        <ul class="cs-list fav-sort-dropdown" id="hist-sort-dropdown" role="listbox" style="right:0;left:auto;min-width:190px;width:max-content">
                            <li class="cs-item is-active" role="option" data-value="date_desc" aria-selected="true">
                                <span class="cs-check"><svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"/></svg></span>
                                <span class="cs-body"><span class="cs-item-label">Récemment regardés</span></span>
                            </li>
                            <li class="cs-item" role="option" data-value="date_asc" aria-selected="false">
                                <span class="cs-check"></span>
                                <span class="cs-body"><span class="cs-item-label">Plus anciens</span></span>
                            </li>
                            <li class="cs-item" role="option" data-value="pct_desc" aria-selected="false">
                                <span class="cs-check"></span>
                                <span class="cs-body"><span class="cs-item-label">Progression ↓</span></span>
                            </li>
                            <li class="cs-item" role="option" data-value="alpha" aria-selected="false">
                                <span class="cs-check"></span>
                                <span class="cs-body"><span class="cs-item-label">A → Z</span></span>
                            </li>
                        </ul>
                    </div>
                </div>

            </div>
        </div>

        <!-- ── Contenu ───────────────────────────────────────────────────────── -->
        <div class="container fav-content">

            <div id="hist-grid-wrap">
                <div class="loader" id="hist-loader">
                    <div class="loader__ring"></div>
                    <span class="loader__label">Chargement de l'historique…</span>
                </div>
                <div class="grid-cards" id="hist-grid" style="display:none"></div>
                <div id="hist-pagination"></div>

                <div class="fav-empty" id="hist-empty" style="display:none">
                    <div class="fav-empty__visual">
                        <svg xmlns="http://www.w3.org/2000/svg" width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>
                    </div>
                    <div class="fav-empty__title" id="hist-empty-title">Historique vide</div>
                    <p class="fav-empty__sub" id="hist-empty-sub">Tes visionnages apparaîtront ici automatiquement.</p>
                    <a href="/" class="btn btn--ghost">Explorer le catalogue</a>
                </div>
            </div>

        </div>

    </main>

    <?php include __DIR__ . '/../components/footer.php'; ?>

    <script>
    (async () => {
        const PER_PAGE   = 24;
        let   currentPage = 1;

        const loader     = document.getElementById('hist-loader');
        const grid       = document.getElementById('hist-grid');
        const pagEl      = document.getElementById('hist-pagination');
        const empty      = document.getElementById('hist-empty');
        const emptyTitle = document.getElementById('hist-empty-title');
        const emptySub   = document.getElementById('hist-empty-sub');
        const tabsEl     = document.getElementById('hist-tabs');
        const clearBtn   = document.getElementById('hist-clear-btn');
        const searchEl   = document.getElementById('hist-search');

        const statusWrap     = document.getElementById('hist-status-wrap');
        const statusBtn      = document.getElementById('hist-status-btn');
        const statusLabel    = document.getElementById('hist-status-label');
        const statusDropdown = document.getElementById('hist-status-dropdown');

        const sortWrap     = document.getElementById('hist-sort-wrap');
        const sortBtn      = document.getElementById('hist-sort-btn');
        const sortLabel    = document.getElementById('hist-sort-label');
        const sortDropdown = document.getElementById('hist-sort-dropdown');

        let sortMode     = 'date_desc';
        let activeType   = 'all';
        let activeStatus = 'all'; // all | in_progress | completed

        const SORT_SHORT = {
            date_desc: 'Récents', date_asc: 'Anciens', pct_desc: 'Progression', alpha: 'A→Z',
        };

        statusBtn.addEventListener('click', e => {
            e.stopPropagation();
            const open = statusWrap.classList.toggle('is-open');
            statusBtn.setAttribute('aria-expanded', open);
        });

        sortBtn.addEventListener('click', e => {
            e.stopPropagation();
            const open = sortWrap.classList.toggle('is-open');
            sortBtn.setAttribute('aria-expanded', open);
        });
        document.addEventListener('click', () => {
            statusWrap.classList.remove('is-open');
            statusBtn.setAttribute('aria-expanded', 'false');
            sortWrap.classList.remove('is-open');
            sortBtn.setAttribute('aria-expanded', 'false');
        });
        sortDropdown.querySelectorAll('.cs-item').forEach(item => {
            item.addEventListener('click', () => {
                sortMode = item.dataset.value;
                sortLabel.textContent = SORT_SHORT[sortMode] ?? item.querySelector('.cs-item-label').textContent;
                sortDropdown.querySelectorAll('.cs-item').forEach(i => {
                    const active = i === item;
                    i.classList.toggle('is-active', active);
                    i.setAttribute('aria-selected', active);
                    i.querySelector('.cs-check').innerHTML = active
                        ? '<svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"/></svg>'
                        : '';
                });
                sortWrap.classList.remove('is-open');
                currentPage = 1;
                render();
            });
        });

        const TYPE_META = {
            all:    { label: 'Tous',   color: 'var(--color-accent)', icon: lc('bookmark', 15) },
            anime:  { label: 'Anime',  color: '#7eb8f7',             icon: lc('tv-2', 15) },
            serie:  { label: 'Série',  color: '#87d68b',             icon: lc('tv', 15) },
            film:   { label: 'Film',   color: '#f7b76b',             icon: lc('clapperboard', 15) },
        };

        const NO_COVER_ICON = {
            anime:  lc('tv-2', 36), serie: lc('tv', 36),
            film:   lc('clapperboard', 36),
        };

        const URLS = {
            film:   s => `/content/?type=film&slug=${encodeURIComponent(s)}`,
            serie:  s => `/content/?type=serie&slug=${encodeURIComponent(s)}`,
            anime:  s => `/content/?type=anime&slug=${encodeURIComponent(s)}`,
        };

        const esc = s => String(s ?? '').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');

        // ── Build file → show index from API ─────────────────────────────────
        let fileIndex  = {};
        let showTotals = {}; // "type_slug" → total episode count
        try {
            // Extrait les slugs uniques depuis le localStorage pour ne scanner que ceux-là
            const progress  = HoshimiStorage.Progress.getAllProgress();
            const fileKeys  = Object.keys(progress);
            // On envoie les file_keys — le serveur fera le matching via son propre index léger
            const data = await fetch('/api/shows-index.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ file_keys: fileKeys }),
            }).then(r => r.json());
            Object.values(data).forEach(show => {
                const k = (show.type ?? 'unknown') + '_' + (show.slug ?? '');
                showTotals[k] = (show.episodes ?? []).length;
                (show.episodes ?? []).forEach(ep => {
                    fileIndex[ep.file_key] = { show, ep };
                });
            });
        } catch {}

        loader.style.display = 'none';
        grid.style.display   = '';

        function timeAgo(iso) {
            if (!iso) return '';
            const diff = Math.floor((Date.now() - new Date(iso)) / 1000);
            if (diff < 60)     return 'À l\'instant';
            if (diff < 3600)   return 'Il y a ' + Math.floor(diff / 60) + ' min';
            if (diff < 86400)  return 'Il y a ' + Math.floor(diff / 3600) + ' h';
            if (diff < 604800) return 'Il y a ' + Math.floor(diff / 86400) + ' j';
            return new Date(iso).toLocaleDateString('fr-FR', { day: 'numeric', month: 'short' });
        }

        function playerUrl(ep, type, slug) {
            if (!ep || !slug) return '#';
            if (type === 'film')  return '/player/?type=film&slug='  + encodeURIComponent(slug) + '&saison=' + ep.season + '&episode=' + ep.number;
            if (type === 'serie') return '/player/?type=serie&slug=' + encodeURIComponent(slug) + '&saison=' + ep.season + '&episode=' + ep.number;
            return '/player/?slug=' + encodeURIComponent(slug) + '&saison=' + ep.season + '&episode=' + ep.number;
        }

        // ── Data (grouped by show) ────────────────────────────────────────────
        function getAll() {
            const progress = HoshimiStorage.Progress.getAllProgress();
            const groups   = {};

            Object.entries(progress).forEach(([fileKey, p]) => {
                const info = fileIndex[fileKey];
                if (!info) {
                    // Fichier inconnu — entrée isolée
                    groups['unknown_' + fileKey] = {
                        fileKeys: [fileKey], fileKey, p,
                        type: null, slug: null,
                        title: fileKey, cover: '', ep: null,
                        lastUpdated: p.updated_at ?? '',
                        watchedCount: 1, totalCount: 1,
                    };
                    return;
                }
                const { show, ep } = info;
                const k = (show.type ?? 'unknown') + '_' + (show.slug ?? fileKey);
                if (!groups[k]) {
                    groups[k] = {
                        fileKeys: [], fileKey, p,
                        type: show.type, slug: show.slug,
                        title: show.show_title,
                        cover: show.cover_url || show.banner_url || '',
                        ep,
                        lastUpdated: p.updated_at ?? '',
                        watchedCount: 0, totalCount: 0,
                    };
                }
                const g = groups[k];
                g.fileKeys.push(fileKey);
                g.watchedCount++;
                if ((p.updated_at ?? '') >= g.lastUpdated) {
                    g.lastUpdated = p.updated_at ?? '';
                    g.p  = p;
                    g.ep = ep;
                    g.fileKey = fileKey;
                }
            });

            return Object.values(groups).map(g => {
                const k          = (g.type ?? 'unknown') + '_' + (g.slug ?? '');
                g.totalCount     = showTotals[k] ?? g.watchedCount;
                g.p              = { ...g.p, updated_at: g.lastUpdated };

                if (g.type === 'film') {
                    g.pct       = g.p.duration > 0 ? Math.min(100, Math.round(g.p.position / g.p.duration * 100)) : 0;
                    g.completed = !!g.p.completed;
                } else {
                    g.pct       = g.totalCount > 0 ? Math.round(g.watchedCount / g.totalCount * 100) : 0;
                    g.completed = g.watchedCount >= g.totalCount && !!g.p.completed;
                }
                return g;
            });
        }

        function sorted(items) {
            return [...items].sort((a, b) => {
                if (sortMode === 'date_desc') return (b.p.updated_at ?? '').localeCompare(a.p.updated_at ?? '');
                if (sortMode === 'date_asc')  return (a.p.updated_at ?? '').localeCompare(b.p.updated_at ?? '');
                if (sortMode === 'pct_desc')  return b.pct - a.pct;
                if (sortMode === 'alpha')     return (a.title ?? '').localeCompare(b.title ?? '', 'fr');
                return 0;
            });
        }

        // ── Limit counter ─────────────────────────────────────────────────────
        function buildStats(all) {
            const limitEl = document.getElementById('hist-limit-counter');
            if (limitEl) limitEl.textContent = `${all.length} / 50`;
        }

        // ── Type tabs ─────────────────────────────────────────────────────────
        function buildTabs(all) {
            tabsEl.innerHTML = '';
            const counts = { all: all.length };
            all.forEach(e => { if (e.type) counts[e.type] = (counts[e.type] ?? 0) + 1; });

            ['all', 'anime', 'serie', 'film'].forEach(type => {
                if (type !== 'all' && !counts[type]) return;
                const m   = TYPE_META[type];
                const btn = document.createElement('button');
                btn.className    = 'fav-tab' + (type === activeType ? ' is-active' : '');
                btn.dataset.type = type;
                btn.style.setProperty('--tab-color', m.color);
                btn.innerHTML    = `${m.icon}<span class="fav-tab__label">${m.label}</span><span class="fav-tab__badge">${counts[type] ?? 0}</span>`;
                btn.addEventListener('click', () => {
                    activeType   = type;
                    activeStatus = 'all';
                    currentPage  = 1;
                    searchEl.value = '';
                    render();
                });
                tabsEl.appendChild(btn);
            });
        }

        // ── Status custom select ──────────────────────────────────────────────
        const CHECK_SVG = '<svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"/></svg>';

        function buildStatusPills(items) {
            let inProgress = 0, completed = 0;
            items.forEach(e => { if (e.completed) completed++; else inProgress++; });

            if (!inProgress && !completed) { statusWrap.style.display = 'none'; return; }
            statusWrap.style.display = '';

            const STATUS = [
                { key: 'all',         label: 'Tous' },
                { key: 'in_progress', label: `En cours (${inProgress})`,  show: !!inProgress },
                { key: 'completed',   label: `Terminés (${completed})`,   show: !!completed },
            ];

            statusDropdown.innerHTML = '';
            STATUS.forEach(s => {
                if (s.key !== 'all' && !s.show) return;
                const active = activeStatus === s.key;
                const li = document.createElement('li');
                li.className = 'cs-item' + (active ? ' is-active' : '');
                li.setAttribute('role', 'option');
                li.setAttribute('aria-selected', active);
                li.dataset.value = s.key;
                li.innerHTML = `<span class="cs-check">${active ? CHECK_SVG : ''}</span><span class="cs-body"><span class="cs-item-label">${s.label}</span></span>`;
                li.addEventListener('click', () => {
                    activeStatus = s.key;
                    statusLabel.textContent = s.key === 'all' ? 'Tous' : s.label;
                    currentPage = 1;
                    render();
                });
                statusDropdown.appendChild(li);
            });
            statusLabel.textContent = activeStatus === 'all' ? 'Tous'
                : activeStatus === 'in_progress' ? `En cours (${inProgress})`
                : `Terminés (${completed})`;
        }

        // ── Card builder ──────────────────────────────────────────────────────
        function buildCard(entry, index) {
            const { fileKey, fileKeys, p, type, slug, title, cover, ep, pct, completed, watchedCount, totalCount } = entry;
            const url       = playerUrl(ep, type, slug);
            const typeLabel = { anime: 'Anime', serie: 'Série', film: 'Film' }[type] ?? '';

            // Badge coin : progression
            let cornerBadge;
            if (completed) {
                cornerBadge = `<span class="hist-badge hist-badge--done">${lc('check', 11)}</span>`;
            } else if (type === 'film') {
                cornerBadge = pct > 0 ? `<span class="hist-badge hist-badge--pct">${pct}%</span>` : '';
            } else {
                cornerBadge = watchedCount > 0
                    ? `<span class="hist-badge hist-badge--pct">${watchedCount}/${totalCount} ep.</span>`
                    : '';
            }

            // Barre de progression
            const barPct = type === 'film' ? pct : (totalCount > 0 ? Math.round(watchedCount / totalCount * 100) : 0);

            const wrap = document.createElement('div');
            wrap.className = 'fav-card-wrap';
            wrap.style.animationDelay = Math.min(index * 30, 300) + 'ms';

            const card = document.createElement('a');
            card.href         = url;
            card.className    = 'card';
            if (type) card.dataset.type = type;

            const coverHtml = cover
                ? `<img class="card__poster" src="${esc(cover)}" alt="${esc(title)}" loading="lazy">`
                : `<div class="no-cover"><div class="no-cover__content"><span class="no-cover__icon">${NO_COVER_ICON[type] ?? lc('clock', 36)}</span></div></div>`;

            card.innerHTML = `
                <div class="card__media">
                    ${coverHtml}
                    <div class="card__overlay"></div>
                    <div class="card__play"><div class="card__play-btn">${lc('play', 16)}</div></div>
                    <div class="hist-prog-bar"><div class="hist-prog-bar__fill" style="width:${barPct}%"></div></div>
                    ${cornerBadge}
                    <div class="card__body">
                        <div class="card__title">${esc(title)}</div>
                        <div class="card__meta">
                            ${typeLabel ? `<span class="card__meta-badge">${esc(typeLabel)}</span>` : ''}
                            ${p.updated_at ? `<span class="card__meta-year">${timeAgo(p.updated_at)}</span>` : ''}
                        </div>
                    </div>
                </div>`;

            const removeBtn = document.createElement('button');
            removeBtn.className = 'fav-remove-btn';
            removeBtn.title     = 'Retirer de l\'historique';
            removeBtn.innerHTML = lc('x', 12);
            removeBtn.addEventListener('click', e => {
                e.preventDefault();
                e.stopPropagation();
                wrap.style.transition = 'opacity .2s, transform .2s';
                wrap.style.opacity    = '0';
                wrap.style.transform  = 'scale(.92)';
                setTimeout(() => {
                    (fileKeys ?? [fileKey]).forEach(k => HoshimiStorage.Progress.remove(k));
                    render();
                }, 200);
            });

            wrap.appendChild(card);
            wrap.appendChild(removeBtn);
            return wrap;
        }

        // ── Pagination ────────────────────────────────────────────────────────
        function buildPagination(totalPages) {
            pagEl.innerHTML = '';
            if (totalPages <= 1) return;

            const nav = document.createElement('div');
            nav.className = 'pagination';

            const mkBtn = (label, page, disabled) => {
                const el = document.createElement(disabled ? 'span' : 'button');
                el.className = 'pagination__btn' + (disabled ? ' pagination__btn--disabled' : '');
                el.innerHTML = label;
                if (!disabled) el.addEventListener('click', () => {
                    currentPage = page;
                    render();
                    document.getElementById('hist-controls-bar').scrollIntoView({ behavior: 'smooth', block: 'start' });
                });
                return el;
            };

            const mkPage = n => {
                const el = document.createElement(n === currentPage ? 'span' : 'button');
                el.className = 'pagination__page' + (n === currentPage ? ' is-active' : '');
                el.textContent = n;
                if (n !== currentPage) el.addEventListener('click', () => {
                    currentPage = n;
                    render();
                    document.getElementById('hist-controls-bar').scrollIntoView({ behavior: 'smooth', block: 'start' });
                });
                return el;
            };

            const left = document.createElement('div');
            left.className = 'pagination__nav pagination__nav--left';
            left.appendChild(mkBtn('«', 1, currentPage <= 1));
            left.appendChild(mkBtn('‹ Précédente', currentPage - 1, currentPage <= 1));

            const pages = document.createElement('div');
            pages.className = 'pagination__pages';
            const start = Math.max(1, currentPage - 2);
            const end   = Math.min(totalPages, currentPage + 2);
            if (start > 1) pages.appendChild(mkPage(1));
            if (start > 2) { const ell = document.createElement('span'); ell.className = 'pagination__ellipsis'; ell.textContent = '…'; pages.appendChild(ell); }
            for (let i = start; i <= end; i++) pages.appendChild(mkPage(i));
            if (end < totalPages - 1) { const ell = document.createElement('span'); ell.className = 'pagination__ellipsis'; ell.textContent = '…'; pages.appendChild(ell); }
            if (end < totalPages) pages.appendChild(mkPage(totalPages));

            const right = document.createElement('div');
            right.className = 'pagination__nav pagination__nav--right';
            right.appendChild(mkBtn('Suivante ›', currentPage + 1, currentPage >= totalPages));
            right.appendChild(mkBtn('»', totalPages, currentPage >= totalPages));

            nav.appendChild(left);
            nav.appendChild(pages);
            nav.appendChild(right);
            pagEl.appendChild(nav);
        }

        // ── Render ────────────────────────────────────────────────────────────
        function render() {
            const all    = getAll();
            const byType = activeType === 'all' ? all : all.filter(e => e.type === activeType);
            const byStat = activeStatus === 'in_progress' ? byType.filter(e => !e.completed)
                         : activeStatus === 'completed'   ? byType.filter(e =>  e.completed)
                         : byType;
            const q      = searchEl.value.toLowerCase().trim();
            const items  = sorted(q ? byStat.filter(e => e.title.toLowerCase().includes(q)) : byStat);

            clearBtn.style.display = all.length ? '' : 'none';

            buildStats(all);
            buildTabs(all);
            buildStatusPills(byType);

            grid.innerHTML  = '';
            pagEl.innerHTML = '';

            if (!items.length) {
                grid.style.display  = 'none';
                empty.style.display = 'flex';
                if (!all.length) {
                    emptyTitle.textContent = 'Historique vide pour l\'instant';
                    emptySub.textContent   = 'Tes visionnages apparaîtront ici automatiquement.';
                } else if (q) {
                    emptyTitle.textContent = `Aucun résultat pour « ${q} »`;
                    emptySub.textContent   = '';
                } else {
                    emptyTitle.textContent = 'Aucun titre dans cette catégorie';
                    emptySub.textContent   = '';
                }
                return;
            }

            const totalPages = Math.ceil(items.length / PER_PAGE);
            currentPage = Math.min(currentPage, totalPages);
            const pageItems = items.slice((currentPage - 1) * PER_PAGE, currentPage * PER_PAGE);

            grid.style.display  = '';
            empty.style.display = 'none';
            pageItems.forEach((entry, i) => grid.appendChild(buildCard(entry, i)));
            buildPagination(totalPages);
        }

        clearBtn.addEventListener('click', async () => {
            const all = getAll();
            const epCount = all.reduce((s, e) => s + (e.fileKeys?.length ?? 1), 0);
            if (!await HoshimiConfirm.ask(`Effacer tout l'historique (${epCount} épisode${epCount > 1 ? 's' : ''}) ?`, { confirm: 'Effacer', type: 'danger' })) return;
            all.forEach(e => (e.fileKeys ?? [e.fileKey]).forEach(k => HoshimiStorage.Progress.remove(k)));
            render();
        });

        searchEl.addEventListener('input', () => { currentPage = 1; render(); });

        const sentinel = document.createElement('div');
        sentinel.style.height = '1px';
        document.getElementById('hist-controls-bar').before(sentinel);
        new IntersectionObserver(([e]) => {
            document.getElementById('hist-controls-bar').classList.toggle('is-sticky', !e.isIntersecting);
        }, { threshold: 1 }).observe(sentinel);

        render();
    })();
    </script>
</body>
</html>
