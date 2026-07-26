<?php

$current_path ??= $_SERVER['REQUEST_URI'];
$search ??= $_GET['q'] ?? '';
$animes ??= [];

if ($search !== '') {
    $animes = array_filter(
        $animes,
        fn($a) =>
        stripos($a['title'], $search) !== false ||
            stripos($a['title_romaji'], $search) !== false
    );
}

require_once __DIR__ . '/../../app/MacGuard.php';
$_hasAccess = MacGuard::hasAccess();
?>

<header class="header" role="banner">
    <nav class="navbar" aria-label="Navigation principale">
        <div class="navbar__inner">

            <!-- Logo -->
            <a href="/" class="navbar__logo" aria-label="Hoshimi — Accueil">
                Hoshimi <span aria-hidden="true">星見</span>
            </a>

            <!-- Burger (mobile) -->
            <button class="navbar__burger" aria-label="Ouvrir le menu" aria-expanded="false" aria-controls="navbar-menu" data-burger-btn>
                <span class="burger-line"></span>
                <span class="burger-line"></span>
                <span class="burger-line"></span>
            </button>

            <!-- Liens -->
            <div class="navbar__menu" id="navbar-menu" data-navbar-menu>
                <ul class="navbar__nav" role="list">
                    <li>
                        <a href="/catalog/" class="navbar__link <?= str_starts_with($current_path, '/catalog') ? 'is-active' : '' ?>">Catalogue</a>
                    </li>
                    <li class="navbar__sep" aria-hidden="true"></li>

                    <li>
                        <a href="/favoris/" class="navbar__link <?= str_starts_with($current_path, '/favori') ? 'is-active' : '' ?>">Favoris</a>
                    </li>
                    <li>
                        <a href="/watchlist/" class="navbar__link <?= str_starts_with($current_path, '/watchlist') ? 'is-active' : '' ?>">Watchlist</a>
                    </li>
                    <li>
                        <a href="/historique/" class="navbar__link <?= str_starts_with($current_path, '/historique') ? 'is-active' : '' ?>">Historique</a>
                    </li>
                    <li>
                        <a href="/wishboard/" class="navbar__link <?= str_starts_with($current_path, '/wishboard') ? 'is-active' : '' ?>">Wishboard</a>
                    </li>

                    <?php if ($_hasAccess) : ?>
                    <li>
                        <a href="/admin/" class="navbar__link <?= str_starts_with($current_path, '/admin') ? 'is-active' : '' ?>">Admin</a>
                    </li>
                    <?php endif; ?>
                </ul>
            </div>

            <!-- Droite : search + random + profil -->
            <div class="navbar__right">
                <button class="navbar__random-btn" id="navbar-random-btn" title="Média aléatoire" aria-label="Lancer un média aléatoire">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                        <polyline points="16 3 21 3 21 8"/><line x1="4" y1="20" x2="21" y2="3"/>
                        <polyline points="21 16 21 21 16 21"/><line x1="15" y1="15" x2="21" y2="21"/>
                    </svg>
                </button>
                <div class="navbar__search">
                    <form class="search-bar" method="GET" action="/search/" role="search" aria-label="Rechercher">
                        <svg class="search-bar__icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                            <circle cx="11" cy="11" r="8"></circle>
                            <line x1="21" y1="21" x2="16.65" y2="16.65"></line>
                        </svg>
                        <input
                            id="navbar-search-input"
                            type="search"
                            name="q"
                            placeholder="Rechercher…"
                            value="<?= htmlspecialchars($search) ?>"
                            autocomplete="off"
                            aria-autocomplete="list"
                            aria-controls="navbar-search-dropdown"
                            aria-expanded="false">
                    </form>
                    <div class="search-dropdown" id="navbar-search-dropdown" role="listbox" hidden></div>
                </div>

                <a href="/profil/"
                   class="navbar__profil-btn <?= str_starts_with($current_path, '/profil') ? 'is-active' : '' ?>"
                   aria-label="Mon profil"
                   title="Profil">
                    <svg width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                        <circle cx="12" cy="8" r="4"/>
                        <path d="M4 20c0-4 3.6-7 8-7s8 3 8 7"/>
                    </svg>
                </a>
            </div>

        </div>
    </nav>
</header>

<script>
    (function() {
        const avatarUrl = localStorage.getItem('hoshimi_avatar');
        if (avatarUrl) {
            const btn = document.querySelector('.navbar__profil-btn');
            if (btn) {
                const img = document.createElement('img');
                img.className = 'navbar__profil-avatar';
                img.src = avatarUrl;
                img.alt = '';
                btn.querySelector('svg')?.remove();
                btn.appendChild(img);
            }
        }
    })();

    document.addEventListener('DOMContentLoaded', () => {
        const burgerBtn  = document.querySelector('[data-burger-btn]');
        const navbarMenu = document.querySelector('[data-navbar-menu]');

        if (burgerBtn && navbarMenu) {
            const overlay = document.createElement('div');
            overlay.className = 'navbar-overlay';
            document.body.appendChild(overlay);

            function closeMenu() {
                burgerBtn.setAttribute('aria-expanded', 'false');
                burgerBtn.setAttribute('aria-label', 'Ouvrir le menu');
                navbarMenu.classList.remove('is-active');
                overlay.classList.remove('is-visible');
            }

            function openMenu() {
                burgerBtn.setAttribute('aria-expanded', 'true');
                burgerBtn.setAttribute('aria-label', 'Fermer le menu');
                navbarMenu.classList.add('is-active');
                overlay.classList.add('is-visible');
            }

            burgerBtn.addEventListener('click', () => {
                burgerBtn.getAttribute('aria-expanded') === 'true' ? closeMenu() : openMenu();
            });

            overlay.addEventListener('click', closeMenu);

            document.addEventListener('keydown', e => {
                if (e.key === 'Escape') closeMenu();
            });
        }

        // ── Bouton aléatoire ─────────────────────────────────────────────────
        const randomBtn = document.getElementById('navbar-random-btn');
        if (randomBtn) {
            randomBtn.addEventListener('click', async () => {
                randomBtn.classList.add('is-loading');
                try {
                    const data = await fetch('/api/random.php').then(r => r.json());
                    if (data.ok) window.location.href = data.href;
                } finally {
                    randomBtn.classList.remove('is-loading');
                }
            });
        }

        const searchBar   = document.querySelector('.search-bar');
        const searchIcon  = document.querySelector('.search-bar__icon');
        const searchInput = document.getElementById('navbar-search-input');

        if (searchIcon && searchInput) {
            searchIcon.style.cursor = 'pointer';
            searchIcon.addEventListener('click', e => { e.preventDefault(); searchInput.focus(); });
        }

        // ── Recherche en temps réel ───────────────────────────────────────────
        const searchDropdown = document.getElementById('navbar-search-dropdown');
        if (searchInput && searchDropdown) {
            let _searchTimer = null;
            let _lastQ = '';

            const TYPE_LABELS = { anime: 'Anime', serie: 'Série', film: 'Film' };
            const TYPE_ORDER  = ['anime', 'serie', 'film'];

            function closeDropdown() {
                searchDropdown.hidden = true;
                searchDropdown.innerHTML = '';
                searchInput.setAttribute('aria-expanded', 'false');
            }

            function renderDropdown(results, q) {
                if (!results.length) { closeDropdown(); return; }

                // Grouper par type
                const groups = {};
                for (const r of results) {
                    if (!groups[r.type]) groups[r.type] = [];
                    groups[r.type].push(r);
                }

                let html = '';
                let itemIdx = 0;
                for (const type of TYPE_ORDER) {
                    if (!groups[type]) continue;
                    const label = TYPE_LABELS[type] ?? type;
                    const count = groups[type].length;
                    html += `<div class="search-dd__group-header">
                        <span class="search-dd__group-badge search-dd__badge--${esc(type)}">${label}</span>
                        <span class="search-dd__group-count">${count}</span>
                    </div>`;
                    for (const r of groups[type]) {
                        const cover = r.cover_url
                            ? `<img class="search-dd__cover" src="${esc(r.cover_url)}" alt="" loading="lazy">`
                            : `<div class="search-dd__cover search-dd__cover--empty"></div>`;
                        const score = r.score ? `<span class="search-dd__score">★ ${r.score}</span>` : '';
                        const year  = r.year  ? `<span class="search-dd__year">${r.year}</span>` : '';
                        const sep   = score && year ? `<span class="search-dd__dot">·</span>` : '';
                        html += `<a class="search-dd__item" href="${esc(r.href)}" role="option" id="search-dd-item-${itemIdx++}">
                            ${cover}
                            <span class="search-dd__info">
                                <span class="search-dd__title">${esc(r.title)}</span>
                                <span class="search-dd__meta">${score}${sep}${year}</span>
                            </span>
                        </a>`;
                    }
                }

                html += `<a class="search-dd__all" href="/search/?q=${encodeURIComponent(q)}">
                    Voir tous les résultats ${lc('arrow-right', 12)}
                </a>`;

                searchDropdown.innerHTML = html;
                searchDropdown.hidden = false;
                searchInput.setAttribute('aria-expanded', 'true');
            }

            async function doSearch(q) {
                if (q.length < 2) { closeDropdown(); return; }
                if (q === _lastQ) return;
                _lastQ = q;
                try {
                    const data = await fetch(`/search/api.php?q=${encodeURIComponent(q)}&limit=7`).then(r => r.json());
                    if (searchInput.value.trim() === q) renderDropdown(data.results ?? [], q);
                } catch { /* silencieux */ }
            }

            searchInput.addEventListener('input', () => {
                clearTimeout(_searchTimer);
                const q = searchInput.value.trim();
                if (q.length < 2) { closeDropdown(); return; }
                _searchTimer = setTimeout(() => doSearch(q), 280);
            });

            document.addEventListener('pointerdown', e => {
                if (!searchDropdown.contains(e.target) && e.target !== searchInput) closeDropdown();
            }, true);

            searchInput.addEventListener('keydown', e => {
                if (!searchDropdown.hidden) {
                    const items = [...searchDropdown.querySelectorAll('.search-dd__item, .search-dd__all')];
                    const focus = searchDropdown.querySelector(':focus');
                    const idx   = items.indexOf(focus);
                    if (e.key === 'ArrowDown') { e.preventDefault(); (items[idx + 1] ?? items[0])?.focus(); }
                    else if (e.key === 'ArrowUp') { e.preventDefault(); (items[idx - 1] ?? items.at(-1))?.focus(); }
                    else if (e.key === 'Escape') { closeDropdown(); searchInput.blur(); }
                }
            });

            searchBar?.addEventListener('submit', closeDropdown);
        }

        function esc(s) {
            return String(s).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
        }
    });
</script>
