<?php

declare(strict_types=1);

$current_path = '/listes';
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Mes favoris — Hoshimi</title>
    <link rel="stylesheet" href="/css/hoshimi.css">
</head>
<body>
    <?php include __DIR__ . '/../components/navbar.php'; ?>

    <main>
        <div class="container" style="padding-top: 40px; padding-bottom: 60px;">

            <div class="section-header" style="margin-bottom: 32px;">
                <h1 class="section-header__title">Mes favoris</h1>
            </div>

            <!-- Rendu par hoshimi_fav.js -->
            <div id="favorites-section" style="display:none">

                <div class="grid-cards" id="favorites-grid" style="display:none"></div>

                <div id="favorites-empty" style="display:none; text-align:center; padding: 80px 0; color: var(--color-text-muted);">
                    <p style="font-size: 2.5rem; margin-bottom: 16px;">♡</p>
                    <p style="font-size: 1rem; margin-bottom: 8px;">Aucun favori pour l'instant.</p>
                    <p style="font-size: 0.85rem; opacity: .6; margin-bottom: 24px;">Ajoute des animes, séries ou films avec le bouton ♡ sur leurs pages.</p>
                    <a href="/" class="btn btn--ghost">Explorer le catalogue</a>
                </div>

            </div>

            <!-- Affiché tant que le JS n'a pas tourné -->
            <div id="favorites-loading" style="text-align:center; padding: 80px 0; color: var(--color-text-muted);">
                <p style="font-size: 0.9rem;">Chargement…</p>
            </div>

        </div>
    </main>

    <?php include __DIR__ . '/../components/footer.php'; ?>

    <script src="/js/hoshimi_storage.js"></script>
    <script src="/js/hoshimi_fav.js"></script>
    <script>
        document.getElementById('favorites-loading').style.display = 'none';
    </script>
</body>
</html>
