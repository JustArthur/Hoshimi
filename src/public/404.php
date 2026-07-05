<?php

declare(strict_types=1);

http_response_code(404);
$current_path = '';
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Page introuvable — Hoshimi</title>
    <link rel="stylesheet" href="/css/hoshimi.css">
</head>
<body>
    <?php include __DIR__ . '/components/navbar.php'; ?>

    <main>
        <div class="error-page">
            <div class="error-page__kanji" aria-hidden="true">404</div>
            <div class="error-page__content">
                <h1 class="error-page__title">Page introuvable</h1>
                <p class="error-page__sub">Ce contenu n'existe pas ou a été déplacé.</p>
                <div class="error-page__actions">
                    <a href="/" class="btn btn--primary">Retour à l'accueil</a>
                    <a href="javascript:history.back()" class="btn btn--ghost">Page précédente</a>
                </div>
            </div>
        </div>
    </main>

    <?php include __DIR__ . '/components/footer.php'; ?>
</body>
</html>
