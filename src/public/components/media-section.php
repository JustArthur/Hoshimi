<?php
/**
 * Renders a media section with a section-header and a grid of cards.
 *
 * Variables:
 *   string       $sectionType         Eyebrow label (e.g. 'Anime')
 *   string       $sectionTitle        Section title (e.g. 'Derniers ajouts')
 *   string       $sectionHref         "Voir tout" link target
 *   string       $sectionItemType     Type passed to render_card ('anime', 'serie', 'film')
 *   array        $sectionItems        Items to render
 *   string|null  $sectionDataAttrKey  If set, adds data-{key}="{slug}" to each card (optional)
 */
$sectionItems       ??= [];
$sectionDataAttrKey ??= null;
?>
<section style="margin-bottom:48px;">
    <div class="section-header">
        <div class="section-header__bar"></div>
        <div class="section-header__inner">
            <span class="section-header__type"><?= htmlspecialchars($sectionType) ?></span>
            <h2 class="section-header__title"><?= htmlspecialchars($sectionTitle) ?></h2>
        </div>
        <div class="section-header__line"></div>
        <a href="<?= htmlspecialchars($sectionHref) ?>" class="section-header__link">Voir tout <?= lc('arrow-right', 12) ?></a>
    </div>
    <div class="grid-cards grid-cards--row">
        <?php foreach ($sectionItems as $_item) : ?>
            <?= render_card($_item, $sectionItemType, $sectionDataAttrKey ? ['data_attrs' => [$sectionDataAttrKey => $_item['slug']]] : []) ?>
        <?php endforeach; ?>
    </div>
</section>
<?php unset($sectionType, $sectionTitle, $sectionHref, $sectionItemType, $sectionItems, $sectionDataAttrKey, $_item); ?>
