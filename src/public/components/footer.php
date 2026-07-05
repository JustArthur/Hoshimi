<footer class="footer">
    <div class="footer__top-line" aria-hidden="true"></div>

    <div class="footer__inner">

        <div class="footer__brand">
            <a href="/" class="footer__logo">
                Hoshimi<span class="footer__logo-jp">星見</span>
            </a>
            <p class="footer__tagline">Ta bibliothèque de streaming,<br>dans ton salon.</p>
        </div>

        <nav class="footer__nav" aria-label="Découvrir">
            <span class="footer__nav-label">Découvrir</span>
            <ul class="footer__nav-list">
                <li><a href="/"        class="footer__link">Accueil</a></li>
                <li><a href="/animes/" class="footer__link">Animes</a></li>
                <li><a href="/series/" class="footer__link">Séries</a></li>
                <li><a href="/films/"  class="footer__link">Films</a></li>
            </ul>
        </nav>

        <nav class="footer__nav" aria-label="Mon espace">
            <span class="footer__nav-label">Mon espace</span>
            <ul class="footer__nav-list">
                <li><a href="/search/" class="footer__link">Recherche</a></li>
                <li><a href="/listes/" class="footer__link">Favoris</a></li>
                <li><a href="/stats/"  class="footer__link">Sauvegardes</a></li>
            </ul>
        </nav>

        <span class="footer__watermark" aria-hidden="true">星</span>

    </div>

    <div class="footer__bottom">
        <div class="footer__bottom-inner">
            <span class="footer__copy">&copy; <?= date('Y') ?> Hoshimi</span>
            <span class="footer__sep" aria-hidden="true">·</span>
            <span class="footer__copy">Projet personnel</span>
        </div>
    </div>
</footer>
