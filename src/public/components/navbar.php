<?php
// On récupère le chemin de l'URL (ex: /animes ou /listes)
$current_path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);

// Filtres GET
$search      = trim($_GET['q']   ?? '');

// Appliquer les filtres
if ($search !== '') {
    $animes = array_filter(
        $animes,
        fn($a) =>
        stripos($a['title'], $search) !== false ||
            stripos($a['title_romaji'], $search) !== false
    );
}
?>


<header>
    <nav class="navbar">
        <div class="navbar__inner">

            <a href="/" class="navbar__logo">
                Hoshimi <span>星見</span>
            </a>

            <ul class="navbar__nav">
                <li><a href="/animes" class="navbar__link <?= $current_path === '/animes' ? 'active' : '' ?>">Animes</a></li>
                <li><a href="/listes" class="navbar__link <?= $current_path === '/listes' ? 'active' : '' ?>">Mes listes</a></li>
            </ul>

            <div class="navbar__search">
                <form class="search-bar" method="GET" action="/animes">
                    <span class="search-bar__icon">🔍</span>
                    <input
                        type="search"
                        name="q"
                        placeholder="Rechercher un anime…"
                        value="<?= htmlspecialchars($search) ?>"
                        autocomplete="off">
                </form>
            </div>
        </div>
    </nav>
</header>