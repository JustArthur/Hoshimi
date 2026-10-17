<?php
/**
 * Composant page-hero — utilisé sur Catalogue, Favoris, Watchlist, Historique
 *
 * Variables attendues (définir avant l'include) :
 *   string  $heroTitle          Texte du <h1>                              (obligatoire)
 *   string  $heroSub            Sous-titre sous le titre                   (optionnel)
 *   string  $heroStatsId        id du <div> stats rempli par JS            (optionnel)
 *   string  $heroExtra          HTML brut injecté après les stats          (optionnel)
 *   string  $heroClass          Classes CSS supplémentaires sur .page-hero (optionnel)
 */
$heroTitle   ??= '';
$heroSub     ??= '';
$heroStatsId ??= '';
$heroExtra   ??= '';
$heroClass   ??= '';
?>
<div class="page-hero<?= $heroClass !== '' ? ' ' . htmlspecialchars($heroClass) : '' ?>">
    <div class="container page-hero__inner">

        <h1 class="page-hero__title"><?= htmlspecialchars($heroTitle) ?></h1>

        <?php if ($heroSub !== ''): ?>
            <p class="page-hero__sub"><?= htmlspecialchars($heroSub) ?></p>
        <?php endif; ?>

        <?php if ($heroStatsId !== ''): ?>
            <div class="page-hero__stats" id="<?= htmlspecialchars($heroStatsId) ?>"></div>
        <?php endif; ?>

        <?php if ($heroExtra !== ''): ?>
            <?= $heroExtra ?>
        <?php endif; ?>

    </div>
</div>
<?php
// Reset pour éviter la fuite entre includes
unset($heroTitle, $heroSub, $heroStatsId, $heroExtra, $heroClass);
?>
