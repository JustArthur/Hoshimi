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
?>


<header class="header">
    <nav class="navbar">
        <div class="navbar__inner">

            <a href="/" class="navbar__logo">
                Hoshimi <span>星見</span>
            </a>

            <button class="navbar__burger" aria-label="Menu" aria-expanded="false" data-burger-btn>
                <span class="burger-line"></span>
                <span class="burger-line"></span>
                <span class="burger-line"></span>
            </button>

            <div class="navbar__menu" data-navbar-menu>
                <ul class="navbar__nav">
                    <li>
                        <a href="/animes/" class="navbar__link <?= str_starts_with($current_path, '/animes') ? 'is-active' : '' ?>">
                            Animes
                        </a>
                    </li>
                    <li>
                        <a href="/series/" class="navbar__link <?= str_starts_with($current_path, '/series') ? 'is-active' : '' ?>">
                            Séries
                        </a>
                    </li>
                    <li>
                        <a href="/films/" class="navbar__link <?= str_starts_with($current_path, '/films') ? 'is-active' : '' ?>">
                            Films
                        </a>
                    </li>
                    <li>
                        <a href="/listes/" class="navbar__link <?= str_starts_with($current_path, '/listes') ? 'is-active' : '' ?>">
                            Favoris
                        </a>
                    </li>
                    <li>
                        <a href="/stats/" class="navbar__link <?= str_starts_with($current_path, '/stats') ? 'is-active' : '' ?>">
                            Sauvegarde
                        </a>
                    </li>
                </ul>
            </div>

            <div class="navbar__search">
                <form class="search-bar" method="GET" action="/search/">
                    <svg class="search-bar__icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                        <circle cx="11" cy="11" r="8"></circle>
                        <line x1="21" y1="21" x2="16.65" y2="16.65"></line>
                    </svg>
                    <input
                        type="search"
                        name="q"
                        placeholder="Rechercher…"
                        value="<?= htmlspecialchars($search) ?>"
                        autocomplete="off">
                </form>
            </div>

        </div>
    </nav>
</header>

<script>
    document.addEventListener('DOMContentLoaded', () => {
    const burgerBtn = document.querySelector('[data-burger-btn]');
    const navbarMenu = document.querySelector('[data-navbar-menu]');

    if (burgerBtn && navbarMenu) {
        burgerBtn.addEventListener('click', () => {
            const isExpanded = burgerBtn.getAttribute('aria-expanded') === 'true';
            burgerBtn.setAttribute('aria-expanded', !isExpanded);
            navbarMenu.classList.toggle('is-active');
            document.body.style.overflow = !isExpanded ? 'hidden' : '';
        });

        document.addEventListener('click', (e) => {
            if (!navbarMenu.contains(e.target) && !burgerBtn.contains(e.target) && navbarMenu.classList.contains('is-active')) {
                burgerBtn.setAttribute('aria-expanded', 'false');
                navbarMenu.classList.remove('is-active');
                document.body.style.overflow = '';
            }
        });
    }

    // Loupe mobile : clic sur l'icône → focus l'input (déclenche :focus-within)
    const searchBar  = document.querySelector('.search-bar');
    const searchIcon = document.querySelector('.search-bar__icon');
    const searchInput = document.querySelector('.search-bar input');

    if (searchIcon && searchInput) {
        searchIcon.style.cursor = 'pointer';
        searchIcon.addEventListener('click', (e) => {
            e.preventDefault();
            searchInput.focus();
        });
    }
});
</script>