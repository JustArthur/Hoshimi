<?php

declare(strict_types=1);

$current_path = '/favoris';
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Mes favoris — Hoshimi</title>
    <link rel="stylesheet" href="/css/hoshimi.css">
    <script src="/js/storage.js"></script>
    <script src="/js/select.js"></script>
    <script src="/js/confirm.js"></script>
</head>
<body>
    <?php include __DIR__ . '/../components/navbar.php'; ?>

    <main class="fav-page">

        <!-- ── Hero ────────────────────────────────────────────────────────── -->
        <?php
        ob_start(); ?>
        <form class="page-hero__search" id="fav-search-form">
            <svg class="page-hero__search-icon" xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="11" cy="11" r="8"/><path d="m21 21-4.3-4.3"/></svg>
            <input type="search" id="fav-search" class="fav-search__input" placeholder="Rechercher…" autocomplete="off">
        </form>
        <?php
        $heroTitle   = 'Favoris';
        $heroSub     = 'Vos animes, séries et films sauvegardés en un seul endroit.';
        $heroExtra   = ob_get_clean();
        include __DIR__ . '/../components/page-hero.php'; ?>

        <!-- ── Barre de contrôles (sticky) ─────────────────────────────────── -->
        <div class="fav-controls-bar" id="fav-controls-bar">
            <div class="container fav-controls-bar__inner">

                <div class="fav-tabs" id="fav-tabs"></div>

                <div class="fav-controls-bar__right">
                    <button class="btn btn--ghost btn--red btn--small" id="fav-clear-btn" title="Vider tous les favoris">
                        <svg xmlns="http://www.w3.org/2000/svg" width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><polyline points="3 6 5 6 21 6"/><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"/></svg>
                        Tout supprimer
                    </button>
                    <div class="cs-wrap fav-sort-wrap" id="fav-genre-wrap" style="display:none">
                        <button class="cs-btn" id="fav-genre-btn" aria-haspopup="listbox" aria-expanded="false">
                            <svg xmlns="http://www.w3.org/2000/svg" width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><polygon points="22 3 2 3 10 12.46 10 19 14 21 14 12.46 22 3"/></svg>
                            <span id="fav-genre-label">Tous les genres</span>
                            <svg class="cs-chevron" width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><polyline points="6 9 12 15 18 9"/></svg>
                        </button>
                        <ul class="cs-list" id="fav-genre-dropdown" role="listbox" style="right:0;left:auto;min-width:180px;width:max-content"></ul>
                    </div>

                    <div class="cs-wrap fav-sort-wrap" id="fav-sort-wrap">
                        <button class="cs-btn fav-sort-btn" id="fav-sort-btn" aria-haspopup="listbox" aria-expanded="false">
                            <svg xmlns="http://www.w3.org/2000/svg" width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><line x1="4" y1="6" x2="11" y2="6"/><line x1="4" y1="12" x2="11" y2="12"/><line x1="4" y1="18" x2="13" y2="18"/><polyline points="15 15 18 18 21 15"/><line x1="18" y1="6" x2="18" y2="18"/></svg>
                            <span id="fav-sort-label">Récents</span>
                            <svg class="cs-chevron" width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><polyline points="6 9 12 15 18 9"/></svg>
                        </button>
                        <ul class="cs-list fav-sort-dropdown" id="fav-sort-dropdown" role="listbox" style="right:0;left:auto;min-width:180px;width:max-content">
                            <li class="cs-item is-active" role="option" data-value="date_desc" aria-selected="true">
                                <span class="cs-check"><svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"/></svg></span>
                                <span class="cs-body"><span class="cs-item-label">Récemment ajoutés</span></span>
                            </li>
                            <li class="cs-item" role="option" data-value="date_asc" aria-selected="false">
                                <span class="cs-check"></span>
                                <span class="cs-body"><span class="cs-item-label">Ajoutés en premier</span></span>
                            </li>
                            <li class="cs-item" role="option" data-value="alpha" aria-selected="false">
                                <span class="cs-check"></span>
                                <span class="cs-body"><span class="cs-item-label">A → Z</span></span>
                            </li>
                            <li class="cs-item" role="option" data-value="alpha_desc" aria-selected="false">
                                <span class="cs-check"></span>
                                <span class="cs-body"><span class="cs-item-label">Z → A</span></span>
                            </li>
                        </ul>
                    </div>
                </div>

            </div>
        </div>

        <!-- ── Contenu ──────────────────────────────────────────────────────── -->
        <div class="container fav-content">

            <div id="fav-grid-wrap">
                <div class="grid-cards" id="favorites-grid"></div>
                <div id="fav-pagination"></div>

                <div class="fav-empty" id="fav-empty" style="display:none">
                    <div class="fav-empty__visual">
                        <svg xmlns="http://www.w3.org/2000/svg" width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M19 14c1.49-1.46 3-3.21 3-5.5A5.5 5.5 0 0 0 16.5 3c-1.76 0-3 .5-4.5 2-1.5-1.5-2.74-2-4.5-2A5.5 5.5 0 0 0 2 8.5c0 2.3 1.5 4.05 3 5.5l7 7Z"/></svg>
                    </div>
                    <div class="fav-empty__title" id="fav-empty-title">Aucun favori</div>
                    <p class="fav-empty__sub" id="fav-empty-sub">Ajoute des titres depuis leur page en cliquant sur le cœur.</p>
                    <a href="/" class="btn btn--ghost">Explorer le catalogue</a>
                </div>
            </div>

        </div>

    </main>

    <?php include __DIR__ . '/../components/footer.php'; ?>

    <script src="/js/guard.js"></script>
    <script src="/js/favorites.js"></script>
    <script>
    (function () {
        const PER_PAGE   = 24;
        let   currentPage = 1;

        const grid       = document.getElementById('favorites-grid');
        const pagEl      = document.getElementById('fav-pagination');
        const empty      = document.getElementById('fav-empty');
        const emptyTitle = document.getElementById('fav-empty-title');
        const emptySub   = document.getElementById('fav-empty-sub');
        const tabsEl     = document.getElementById('fav-tabs');
        const clearBtn   = document.getElementById('fav-clear-btn');
        const searchEl   = document.getElementById('fav-search');

        const genreWrap     = document.getElementById('fav-genre-wrap');
        const genreBtn      = document.getElementById('fav-genre-btn');
        const genreLabel    = document.getElementById('fav-genre-label');
        const genreDropdown = document.getElementById('fav-genre-dropdown');

        // ── Sort ─────────────────────────────────────────────────────────────
        const sortWrap     = document.getElementById('fav-sort-wrap');
        const sortBtn      = document.getElementById('fav-sort-btn');
        const sortLabel    = document.getElementById('fav-sort-label');
        const sortDropdown = document.getElementById('fav-sort-dropdown');

        let sortMode    = 'date_desc';
        let activeType  = 'all';
        let activeGenre = null;

        const SORT_SHORT = {
            date_desc: 'Récents', date_asc: 'Anciens', alpha: 'A→Z', alpha_desc: 'Z→A',
        };

        genreBtn.addEventListener('click', e => {
            e.stopPropagation();
            const isOpen = genreWrap.classList.contains('is-open');
            genreWrap.classList.toggle('is-open', !isOpen);
            genreBtn.setAttribute('aria-expanded', String(!isOpen));
        });
        sortBtn.addEventListener('click', e => {
            e.stopPropagation();
            const isOpen = sortWrap.classList.contains('is-open');
            sortWrap.classList.toggle('is-open', !isOpen);
            sortBtn.setAttribute('aria-expanded', String(!isOpen));
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
                render();
            });
        });

        // ── Type meta ─────────────────────────────────────────────────────────
        const TYPE_META = {
            all:    { label: 'Tous',   color: 'var(--color-accent)',  icon: lc('bookmark', 15) },
            anime:  { label: 'Anime',  color: '#7eb8f7',              icon: lc('tv-2', 15) },
            serie:  { label: 'Série',  color: '#87d68b',              icon: lc('tv', 15) },
            film:   { label: 'Film',   color: '#f7b76b',              icon: lc('clapperboard', 15) },
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

        // ── Genre index ───────────────────────────────────────────────────────
        let showsGenreIndex = {};
        fetch('/api/shows-index.php').then(r => r.json()).then(data => {
            Object.values(data).forEach(s => { showsGenreIndex[s.slug] = s.genres ?? []; });
            render();
        }).catch(() => {});

        function getAll() {
            return HoshimiStorage.Favorites.getAll().map(f => ({
                ...f,
                genres: (f.genres && f.genres.length) ? f.genres : (showsGenreIndex[f.slug] ?? []),
            }));
        }

        function sorted(favs) {
            return [...favs].sort((a, b) => {
                if (sortMode === 'date_desc') return (b.added_at ?? '') > (a.added_at ?? '') ? 1 : -1;
                if (sortMode === 'date_asc')  return (a.added_at ?? '') > (b.added_at ?? '') ? 1 : -1;
                if (sortMode === 'alpha')      return (a.title ?? '').localeCompare(b.title ?? '', 'fr');
                if (sortMode === 'alpha_desc') return (b.title ?? '').localeCompare(a.title ?? '', 'fr');
                return 0;
            });
        }


        // ── Type tabs ─────────────────────────────────────────────────────────
        function buildTabs(all) {
            tabsEl.innerHTML = '';
            const counts = { all: all.length };
            all.forEach(f => { const t = f.type ?? 'anime'; counts[t] = (counts[t] ?? 0) + 1; });

            ['all', 'anime', 'serie', 'film'].forEach(type => {
                if (type !== 'all' && !counts[type]) return;
                const m   = TYPE_META[type];
                const btn = document.createElement('button');
                btn.className    = 'fav-tab' + (type === activeType ? ' is-active' : '');
                btn.dataset.type = type;
                btn.style.setProperty('--tab-color', m.color);
                btn.innerHTML    = `${m.icon}<span class="fav-tab__label">${m.label}</span><span class="fav-tab__badge">${counts[type] ?? 0}</span>`;
                btn.addEventListener('click', () => {
                    activeType  = type;
                    activeGenre = null;
                    currentPage = 1;
                    searchEl.value = '';
                    render();
                });
                tabsEl.appendChild(btn);
            });
        }

        // ── Genre custom select ───────────────────────────────────────────────
        const CHECK_SVG = '<svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"/></svg>';

        function buildGenres(favs) {
            const genreCount = {};
            favs.forEach(f => { (f.genres ?? []).forEach(g => { genreCount[g] = (genreCount[g] ?? 0) + 1; }); });
            const genres = Object.keys(genreCount).sort();

            genreDropdown.innerHTML = '';
            if (!genres.length) { genreWrap.style.display = 'none'; return; }
            genreWrap.style.display = '';

            const mkItem = (value, label) => {
                const active = activeGenre === value;
                const li = document.createElement('li');
                li.className = 'cs-item' + (active ? ' is-active' : '');
                li.setAttribute('role', 'option');
                li.setAttribute('aria-selected', active);
                li.dataset.value = value ?? '';
                li.innerHTML = `<span class="cs-check">${active ? CHECK_SVG : ''}</span><span class="cs-body"><span class="cs-item-label">${label}</span></span>`;
                li.addEventListener('click', () => {
                    activeGenre = value;
                    genreLabel.textContent = value ?? 'Tous les genres';
                    currentPage = 1;
                    render();
                });
                return li;
            };

            genreDropdown.appendChild(mkItem(null, 'Tous les genres'));
            genres.forEach(g => genreDropdown.appendChild(mkItem(g, g)));
            genreLabel.textContent = activeGenre ?? 'Tous les genres';
        }

        // ── Card builder ──────────────────────────────────────────────────────
        function buildCard(fav, index) {
            const type = fav.type ?? 'anime';
            const url  = (URLS[type] ?? URLS.anime)(fav.slug);

            const wrap = document.createElement('div');
            wrap.className = 'fav-card-wrap';
            wrap.style.animationDelay = Math.min(index * 30, 300) + 'ms';

            const typeLabel = { anime: 'Anime', serie: 'Série', film: 'Film' }[type] ?? 'Anime';

            const card = document.createElement('a');
            card.href         = url;
            card.className    = 'card';
            card.dataset.type = type;

            card.innerHTML = `
                <div class="card__media">
                    ${fav.cover_url
                        ? `<img class="card__poster" src="${esc(fav.cover_url)}" alt="${esc(fav.title)}" loading="lazy">`
                        : `<div class="no-cover"><div class="no-cover__content"><span class="no-cover__icon">${NO_COVER_ICON[type] ?? NO_COVER_ICON.anime}</span></div></div>`}
                    <div class="card__overlay"></div>
                    <div class="card__play"><div class="card__play-btn">${lc('play', 16)}</div></div>
                    <div class="card__body">
                        <div class="card__title">${esc(fav.title)}</div>
                        <div class="card__meta">
                            <span class="card__meta-badge">${typeLabel}</span>
                            ${fav.year ? `<span class="card__meta-year">${fav.year}</span>` : ''}
                        </div>
                    </div>
                </div>`;

            const removeBtn = document.createElement('button');
            removeBtn.className = 'fav-remove-btn';
            removeBtn.title     = 'Retirer des favoris';
            removeBtn.innerHTML = lc('x', 12);
            removeBtn.addEventListener('click', e => {
                e.preventDefault();
                e.stopPropagation();
                wrap.style.transition = 'opacity .2s, transform .2s';
                wrap.style.opacity    = '0';
                wrap.style.transform  = 'scale(.92)';
                setTimeout(() => {
                    HoshimiStorage.Favorites.remove(fav.slug);
                    render();
                }, 200);
            });

            wrap.appendChild(card);
            wrap.appendChild(removeBtn);
            return wrap;
        }

        // ── Pagination ────────────────────────────────────────────────────────
        function buildPagination(total, totalPages) {
            pagEl.innerHTML = '';
            if (totalPages <= 1) return;

            const nav = document.createElement('div');
            nav.className = 'pagination';

            const mkBtn = (label, page, disabled, active = false) => {
                const el = document.createElement(disabled ? 'span' : 'button');
                el.className = 'pagination__btn' + (disabled ? ' pagination__btn--disabled' : '');
                el.innerHTML = label;
                if (!disabled) el.addEventListener('click', () => {
                    currentPage = page;
                    render();
                    document.querySelector('.fav-controls-bar').scrollIntoView({ behavior: 'smooth', block: 'start' });
                });
                return el;
            };

            const mkPage = (n) => {
                const el = document.createElement(n === currentPage ? 'span' : 'button');
                el.className = 'pagination__page' + (n === currentPage ? ' is-active' : '');
                el.textContent = n;
                if (n !== currentPage) el.addEventListener('click', () => {
                    currentPage = n;
                    render();
                    document.querySelector('.fav-controls-bar').scrollIntoView({ behavior: 'smooth', block: 'start' });
                });
                return el;
            };

            const left = document.createElement('div');
            left.className = 'pagination__nav pagination__nav--left';
            left.appendChild(mkBtn('«', 1,              currentPage <= 1));
            left.appendChild(mkBtn('‹ Précédente', currentPage - 1, currentPage <= 1));

            const pages = document.createElement('div');
            pages.className = 'pagination__pages';

            const start = Math.max(1, currentPage - 2);
            const end   = Math.min(totalPages, currentPage + 2);
            if (start > 1) { pages.appendChild(mkPage(1)); }
            if (start > 2) { const ell = document.createElement('span'); ell.className = 'pagination__ellipsis'; ell.textContent = '…'; pages.appendChild(ell); }
            for (let i = start; i <= end; i++) pages.appendChild(mkPage(i));
            if (end < totalPages - 1) { const ell = document.createElement('span'); ell.className = 'pagination__ellipsis'; ell.textContent = '…'; pages.appendChild(ell); }
            if (end < totalPages) pages.appendChild(mkPage(totalPages));

            const right = document.createElement('div');
            right.className = 'pagination__nav pagination__nav--right';
            right.appendChild(mkBtn('Suivante ›', currentPage + 1, currentPage >= totalPages));
            right.appendChild(mkBtn('»', totalPages,    currentPage >= totalPages));

            nav.appendChild(left);
            nav.appendChild(pages);
            nav.appendChild(right);
            pagEl.appendChild(nav);
        }

        // ── Render ────────────────────────────────────────────────────────────
        function render() {
            const all     = getAll();
            const byType  = activeType === 'all' ? all : all.filter(f => (f.type ?? 'anime') === activeType);
            const byGenre = activeGenre ? byType.filter(f => (f.genres ?? []).includes(activeGenre)) : byType;
            const q       = searchEl.value.toLowerCase().trim();
            const items   = sorted(q ? byGenre.filter(f => (f.title ?? '').toLowerCase().includes(q)) : byGenre);

            clearBtn.style.display = all.length ? '' : 'none';

            buildTabs(all);
            buildGenres(byType);

            grid.innerHTML = '';
            pagEl.innerHTML = '';

            if (!items.length) {
                grid.style.display  = 'none';
                empty.style.display = 'flex';
                if (!all.length) {
                    emptyTitle.textContent = 'Aucun favori pour l\'instant';
                    emptySub.textContent   = 'Ajoute des titres depuis leur page en cliquant sur le cœur.';
                } else if (q) {
                    emptyTitle.textContent = `Aucun résultat pour « ${q} »`;
                    emptySub.textContent   = '';
                } else {
                    emptyTitle.textContent = 'Aucun favori dans cette catégorie';
                    emptySub.textContent   = '';
                }
                return;
            }

            const totalPages = Math.ceil(items.length / PER_PAGE);
            currentPage = Math.min(currentPage, totalPages);
            const pageItems = items.slice((currentPage - 1) * PER_PAGE, currentPage * PER_PAGE);

            grid.style.display  = '';
            empty.style.display = 'none';
            pageItems.forEach((fav, i) => grid.appendChild(buildCard(fav, i)));
            buildPagination(items.length, totalPages);
        }

        clearBtn.addEventListener('click', async () => {
            if (!await HoshimiConfirm.ask(`Supprimer tous les favoris (${getAll().length}) ?`, { confirm: 'Supprimer', type: 'danger' })) return;
            getAll().forEach(f => HoshimiStorage.Favorites.remove(f.slug));
            render();
        });

        searchEl.addEventListener('input', () => { currentPage = 1; render(); });

        // Sticky controls bar sentinel
        const sentinel = document.createElement('div');
        sentinel.style.height = '1px';
        document.querySelector('.fav-controls-bar').before(sentinel);
        new IntersectionObserver(([e]) => {
            document.getElementById('fav-controls-bar').classList.toggle('is-sticky', !e.isIntersecting);
        }, { threshold: 1 }).observe(sentinel);

        render();
    })();
    </script>
</body>
</html>
