<?php

declare(strict_types=1);

require_once __DIR__ . '/../../app/MacGuard.php';
require_once __DIR__ . '/../components/icon.php';
MacGuard::protect();

$current_path = $_SERVER['REQUEST_URI'];

// ── Résolution du chemin ──────────────────────────────────────────────────
$type = $_GET['type'] ?? '';
$name = $_GET['name'] ?? '';

$roots = [
    'anime' => $_ENV['ANIMES_PATH'] ?? getenv('ANIMES_PATH') ?: '/media/animes',
    'film'  => $_ENV['FILMS_PATH']  ?? getenv('FILMS_PATH')  ?: '/media/films',
    'serie' => $_ENV['SERIES_PATH'] ?? getenv('SERIES_PATH') ?: '/media/series',
];

if (!isset($roots[$type]) || $name === '') {
    http_response_code(400);
    echo 'Paramètres invalides.';
    exit;
}

$root     = $roots[$type];
$dirPath  = realpath($root . DIRECTORY_SEPARATOR . $name);
$realRoot = realpath($root);

if (!$dirPath || !$realRoot || !str_starts_with($dirPath, $realRoot . DIRECTORY_SEPARATOR)) {
    http_response_code(403);
    echo 'Accès interdit.';
    exit;
}

$metaPath = $dirPath . DIRECTORY_SEPARATOR . 'metadata.json';
if (!file_exists($metaPath)) {
    http_response_code(404);
    echo 'metadata.json introuvable.';
    exit;
}

$meta = json_decode(file_get_contents($metaPath), true) ?: [];

$typeLabel = match ($type) {
    'film'  => 'Film',
    'serie' => 'Série',
    default => 'Anime',
};
$isTV     = $type !== 'film';
$title    = $meta['title'] ?? $name;
$statuses = ['Ended', 'Returning Series', 'In Production', 'Cancelled', 'Released'];
$formats  = ['TV', 'Film', 'ONA', 'OVA', 'Special', 'Movie'];

// Fonctions helpers
function csSelectWrap(string $name, array $options, array $meta, string $placeholder = '—', string $extraClass = ''): string
{
    $cur = (string)($meta[$name] ?? '');
    $SVG_CHECK = '<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"/></svg>';
    $cls = trim('cs-wrap admin-cs-field ' . $extraClass);
    $html  = '<div class="' . $cls . '" id="cs-' . htmlspecialchars($name) . '" data-value="' . htmlspecialchars($cur) . '">';
    $html .= '<input type="hidden" name="' . htmlspecialchars($name) . '" value="' . htmlspecialchars($cur) . '">';
    $html .= '<button class="cs-btn" type="button" aria-haspopup="listbox" aria-expanded="false">';
    $html .= '<span class="cs-label">' . htmlspecialchars($cur !== '' ? $cur : $placeholder) . '</span>';
    $html .= '</button><ul class="cs-list" role="listbox">';
    $emptyActive = $cur === '';
    $html .= '<li class="cs-item' . ($emptyActive ? ' is-active' : '') . '" data-value="" role="option" aria-selected="' . ($emptyActive ? 'true' : 'false') . '">'
           . '<span class="cs-check">' . ($emptyActive ? $SVG_CHECK : '') . '</span>'
           . '<span class="cs-body"><span class="cs-item-label">' . htmlspecialchars($placeholder) . '</span></span></li>';
    foreach ($options as $o) {
        $active = $cur === $o;
        $html .= '<li class="cs-item' . ($active ? ' is-active' : '') . '" data-value="' . htmlspecialchars($o) . '" role="option" aria-selected="' . ($active ? 'true' : 'false') . '">'
               . '<span class="cs-check">' . ($active ? $SVG_CHECK : '') . '</span>'
               . '<span class="cs-body"><span class="cs-item-label">' . htmlspecialchars($o) . '</span></span></li>';
    }
    $html .= '</ul></div>';
    return $html;
}

function csCastRoleWrap(string $role): string
{
    $SVG_CHECK = '<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"/></svg>';
    $isMain = $role === 'MAIN';
    return '<div class="cs-wrap admin-cs-cast-role" data-value="' . $role . '">'
         . '<input type="hidden" class="cast-role" value="' . $role . '">'
         . '<button class="cs-btn" type="button" aria-haspopup="listbox" aria-expanded="false">'
         . '<span class="cs-label">' . ($isMain ? 'Principal' : 'Secondaire') . '</span>'
         . '</button><ul class="cs-list" role="listbox">'
         . '<li class="cs-item' . ($isMain ? ' is-active' : '') . '" data-value="MAIN" role="option" aria-selected="' . ($isMain ? 'true' : 'false') . '">'
         . '<span class="cs-check">' . ($isMain ? $SVG_CHECK : '') . '</span>'
         . '<span class="cs-body"><span class="cs-item-label">Principal</span></span></li>'
         . '<li class="cs-item' . (!$isMain ? ' is-active' : '') . '" data-value="SUPPORTING" role="option" aria-selected="' . (!$isMain ? 'true' : 'false') . '">'
         . '<span class="cs-check">' . (!$isMain ? $SVG_CHECK : '') . '</span>'
         . '<span class="cs-body"><span class="cs-item-label">Secondaire</span></span></li>'
         . '</ul></div>';
}

function field(string $key, array $meta): string
{
    return htmlspecialchars((string) ($meta[$key] ?? ''));
}
function numField(string $key, array $meta): string
{
    return isset($meta[$key]) && $meta[$key] !== null ? (string) $meta[$key] : '';
}
function selectField(string $key, array $meta, array $options): string
{
    $cur = $meta[$key] ?? '';
    $html = '';
    foreach ($options as $o) {
        $sel  = $cur === $o ? ' selected' : '';
        $html .= '<option value="' . htmlspecialchars($o) . '"' . $sel . '>' . htmlspecialchars($o) . '</option>';
    }
    return $html;
}
?>
<!doctype html>
<html lang="fr">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Éditeur — <?= htmlspecialchars($title) ?></title>
    <link rel="stylesheet" href="/css/hoshimi.css">
    <link rel="stylesheet" href="/css/pages/admin.css">
    <script src="/js/storage.js"></script>
    <script src="/js/select.js"></script>
    <script src="/js/confirm.js"></script>
    <script src="/js/toast.js"></script>
</head>

<body class="admin-edit">
    <?php include __DIR__ . '/../components/navbar.php'; ?>

    <!-- Top bar ──────────────────────────────────────────────────────────────── -->
    <div class="admin-topbar">
        <a href="/admin/media.php" class="admin-topbar__back">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                <polyline points="15 18 9 12 15 6" />
            </svg>
            Retour
        </a>
        <span class="admin-topbar__title">
            <?= htmlspecialchars($title) ?>
            <span class="badge badge--type"><?= $typeLabel ?></span>
        </span>
        <div class="admin-topbar__actions">
            <div class="adm-act-dd" id="adm-act-dd">
                <button class="btn btn--ghost adm-act-dd__trigger" id="adm-act-trigger" onclick="toggleActionsMenu(event)">
                    <?= lc('settings', 14) ?> Actions <?= lc('chevron-down', 12) ?>
                </button>
                <div class="adm-act-dd__panel" id="adm-act-panel" hidden>
                    <?php if (!empty($meta['tmdb_id'])): ?>
                    <button class="adm-act-dd__item" data-action="update_meta">
                        <?= lc('trending-up', 14) ?> Sync score &amp; statut
                    </button>
                    <button class="adm-act-dd__item" data-action="seasons_meta">
                        <?= lc('list', 14) ?> Refresh épisodes
                    </button>
                    <button class="adm-act-dd__item" data-action="cover_only">
                        <?= lc('image', 14) ?> Re-télécharger la cover
                    </button>
                    <?php endif; ?>
                    <button class="adm-act-dd__item" data-action="gen_thumb">
                        <?= lc('film', 14) ?> Générer miniatures
                    </button>
                    <?php if (!empty($meta['tmdb_id'])): ?>
                    <div class="adm-act-dd__sep"></div>
                    <button class="adm-act-dd__item adm-act-dd__item--danger" data-action="refetch_meta">
                        <?= lc('alert-triangle', 14) ?> Refetch complet <span class="adm-act-dd__warn">écrase tout</span>
                    </button>
                    <?php endif; ?>
                </div>
            </div>
            <button class="btn btn--primary" id="save-btn" onclick="saveAll()">Sauvegarder</button>
        </div>
    </div>

    <!-- Tabs ─────────────────────────────────────────────────────────────────── -->
    <div class="admin-edit-tabs">
        <button class="admin-tab-btn is-active" data-tab="infos">Infos</button>
        <button class="admin-tab-btn" data-tab="medias">Médias</button>
        <button class="admin-tab-btn" data-tab="cast">Cast (<?= count($meta['cast'] ?? []) ?>)</button>
        <?php if ($isTV): ?>
            <button class="admin-tab-btn" data-tab="saisons">Saisons (<?= count($meta['seasons'] ?? []) ?>)</button>
        <?php endif; ?>
    </div>

    <!-- Body ─────────────────────────────────────────────────────────────────── -->
    <div class="admin-edit-body">

        <!-- ═══ Infos ═══════════════════════════════════════════════════════════ -->
        <div id="tab-infos" class="admin-tab-panel is-active">

            <div class="admin-section">
                <div class="admin-section__title">Titre</div>
                <div class="admin-field-row">
                    <div class="admin-field-group admin-field-group--xl">
                        <label>Titre</label>
                        <input type="text" name="title" value="<?= field('title', $meta) ?>">
                    </div>
                    <div class="admin-field-group admin-field-group--xl">
                        <label>Titre original</label>
                        <input type="text" name="title_original" value="<?= field('title_original', $meta) ?>">
                    </div>
                </div>
            </div>

            <div class="admin-section">
                <div class="admin-section__title">Informations</div>
                <div class="admin-field-row">
                    <div class="admin-field-group">
                        <label>Année</label>
                        <input type="number" name="year" value="<?= numField('year', $meta) ?>" min="1900" max="2100">
                    </div>
                    <div class="admin-field-group">
                        <label>Score TMDB</label>
                        <input type="number" name="score" value="<?= numField('score', $meta) ?>" min="0" max="10" step="0.1">
                    </div>
                    <div class="admin-field-group">
                        <label>Runtime (min)</label>
                        <input type="number" name="runtime" value="<?= numField('runtime', $meta) ?>" min="0">
                    </div>
                    <div class="admin-field-group">
                        <label>Statut</label>
                        <?= csSelectWrap('status', $statuses, $meta) ?>
                    </div>
                </div>
                <div class="admin-field-row">
                    <div class="admin-field-group">
                        <label>Format</label>
                        <?= csSelectWrap('format', $formats, $meta) ?>
                    </div>
                    <div class="admin-field-group admin-field-group--lg">
                        <label>Studio</label>
                        <input type="text" name="studio" value="<?= field('studio', $meta) ?>">
                    </div>
                    <?php if ($isTV): ?>
                        <div class="admin-field-group">
                            <label>Épisodes (total)</label>
                            <input type="number" name="episodes_total" value="<?= numField('episodes_total', $meta) ?>" min="0">
                        </div>
                        <div class="admin-field-group">
                            <label>Saisons (total)</label>
                            <input type="number" name="seasons_count" value="<?= numField('seasons_count', $meta) ?>" min="0">
                        </div>
                    <?php endif; ?>
                    <div class="admin-field-group">
                        <label>TMDB ID</label>
                        <input type="number" name="tmdb_id" value="<?= numField('tmdb_id', $meta) ?>">
                        <?php $tmdbId = $meta['tmdb_id'] ?? null;
                        if ($tmdbId): ?>
                            <a class="admin-tmdb-link" href="https://www.themoviedb.org/<?= $isTV ? 'tv' : 'movie' ?>/<?= (int)$tmdbId ?>" target="_blank" rel="noopener"><?= lc('external-link', 12) ?> Voir sur TMDB</a>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <div class="admin-section">
                <div class="admin-section__title">Synopsis</div>
                <div class="admin-field-group admin-field-group--full">
                    <textarea name="overview" rows="5"><?= field('overview', $meta) ?></textarea>
                </div>
            </div>

            <div class="admin-section">
                <div class="admin-section__title">Genres</div>
                <div class="admin-tags" id="genres-container">
                    <?php foreach ($meta['genres'] ?? [] as $g): ?>
                        <span class="admin-tag" data-genre="<?= htmlspecialchars($g) ?>">
                            <?= htmlspecialchars($g) ?>
                            <button type="button" class="admin-tag__remove" onclick="removeGenre(this)"><svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg></button>
                        </span>
                    <?php endforeach; ?>
                </div>
                <div class="admin-tags-add">
                    <input type="text" id="genre-input" placeholder="Nouveau genre…">
                    <button type="button" onclick="addGenre()">Ajouter</button>
                </div>
            </div>

        </div><!-- /tab-infos -->

        <!-- ═══ Médias ══════════════════════════════════════════════════════════ -->
        <div id="tab-medias" class="admin-tab-panel">

            <div class="admin-section">
                <div class="admin-section__title">Affiche (Cover)</div>
                <div class="admin-media-pair">
                    <div class="admin-preview">
                        <img id="cover-preview" src="<?= field('cover', $meta) ?>" alt=""
                            <?= empty($meta['cover']) ? 'style="display:none"' : '' ?> onerror="this.style.display='none'">
                    </div>
                    <div class="admin-field-group">
                        <label>URL</label>
                        <input type="text" name="cover" id="cover-url" value="<?= field('cover', $meta) ?>"
                            oninput="updateImgPreview('cover-url','cover-preview')">
                    </div>
                </div>
            </div>

            <div class="admin-section">
                <div class="admin-section__title">Bannière</div>
                <div class="admin-media-pair">
                    <div class="admin-field-group">
                        <label>URL</label>
                        <input type="text" name="banner" id="banner-url" value="<?= field('banner', $meta) ?>"
                            oninput="updateImgPreview('banner-url','banner-preview')">
                    </div>
                    <div class="admin-preview admin-preview--banner">
                        <img id="banner-preview" src="<?= field('banner', $meta) ?>" alt=""
                            <?= empty($meta['banner']) ? 'style="display:none"' : '' ?> onerror="this.style.display='none'">
                    </div>
                </div>
            </div>

            <div class="admin-section">
                <div class="admin-section__title">Logos</div>
                <div id="logos-container">
                    <?php foreach ($meta['logos'] ?? [] as $i => $logo): ?>
                        <div class="admin-logo-row">
                            <button type="button" class="admin-remove-btn" onclick="removeParent(this,'.admin-logo-row')"><svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg></button>
                            <div class="admin-logo-field">
                                <label class="admin-logo-label">w500 <span>(500px, utilisé à l'affichage)</span></label>
                                <input type="text" class="logo-w500" placeholder="https://image.tmdb.org/…" value="<?= htmlspecialchars($logo['w500'] ?? '') ?>"
                                    oninput="updateLogoPreview(this)">
                            </div>
                            <div class="admin-logo-field">
                                <label class="admin-logo-label">original <span>(pleine résolution, fallback)</span></label>
                                <input type="text" class="logo-original" placeholder="https://image.tmdb.org/…" value="<?= htmlspecialchars($logo['original'] ?? '') ?>">
                            </div>
                            <div class="admin-logo-preview">
                                <img src="<?= htmlspecialchars($logo['w500'] ?? '') ?>" alt="" onerror="this.style.display='none'"
                                    <?= empty($logo['w500']) ? 'style="display:none"' : '' ?>>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
                <button type="button" class="admin-add-btn" onclick="addLogo()">+ Ajouter un logo</button>
            </div>

        </div><!-- /tab-medias -->

        <!-- ═══ Cast ════════════════════════════════════════════════════════════ -->
        <div id="tab-cast" class="admin-tab-panel">

            <div id="cast-container">
                <?php foreach ($meta['cast'] ?? [] as $c): ?>
                    <div class="admin-cast-row">
                        <div class="admin-cast-avatar">
                            <?php if (!empty($c['profile_url'])): ?>
                                <img src="<?= htmlspecialchars($c['profile_url']) ?>" alt="" onerror="this.style.display='none'">
                                <?php else: ?><?= lc('user', 24) ?><?php endif; ?>
                        </div>
                        <input type="text" class="cast-profile" placeholder="URL photo"
                            value="<?= htmlspecialchars($c['profile_url'] ?? '') ?>"
                            oninput="updateCastAvatar(this)">
                        <input type="text" class="cast-name" placeholder="Nom" value="<?= htmlspecialchars($c['name'] ?? '') ?>">
                        <input type="text" class="cast-char" placeholder="Personnage" value="<?= htmlspecialchars($c['character'] ?? '') ?>">
                        <?= csCastRoleWrap(($c['role'] ?? '') === 'MAIN' ? 'MAIN' : 'SUPPORTING') ?>
                        <button type="button" class="admin-remove-btn" onclick="removeParent(this,'.admin-cast-row')"><svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg></button>
                    </div>
                <?php endforeach; ?>
            </div>
            <button type="button" class="admin-add-btn" onclick="addCast()" style="margin-top:8px">+ Ajouter un membre</button>

        </div><!-- /tab-cast -->

        <?php if ($isTV): ?>
            <!-- ═══ Saisons ══════════════════════════════════════════════════════════ -->
            <div id="tab-saisons" class="admin-tab-panel">

                <div id="seasons-container">
                    <?php foreach ($meta['seasons'] ?? [] as $key => $season): ?>
                        <?php $epCount = count($season['episodes'] ?? []); ?>
                        <details class="admin-season" data-key="<?= htmlspecialchars($key) ?>">
                            <summary class="admin-season__header">
                                <span class="admin-season__arrow"><svg width="11" height="11" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><polyline points="9 18 15 12 9 6"/></svg></span>
                                <span class="admin-season__name"><?= htmlspecialchars($key) ?></span>
                                <span class="admin-season__count"><?= $epCount ?> épisode<?= $epCount > 1 ? 's' : '' ?></span>
                                <label class="admin-season__tmdb-label" onclick="event.preventDefault()">
                                    TMDB #
                                    <input type="number" class="season-tmdb" value="<?= (int)($season['tmdb_season'] ?? 0) ?>"
                                        min="0" onclick="event.stopPropagation()">
                                </label>
                                <button type="button" class="admin-season__del" onclick="event.preventDefault();removeSeason(this)">Supprimer</button>
                            </summary>
                            <div class="admin-season__body">
                                <div class="admin-ep-list">
                                    <?php foreach ($season['episodes'] ?? [] as $ei => $ep): ?>
                                        <?php $still = $ep['still'] ?? ''; ?>
                                        <div class="admin-ep-row">
                                            <div class="admin-ep-row__main">
                                                <span class="admin-ep-num"><?= $ei + 1 ?></span>
                                                <input type="text" class="ep-name" placeholder="Titre" value="<?= htmlspecialchars($ep['name'] ?? '') ?>">
                                                <input type="date" class="ep-air_date" value="<?= htmlspecialchars($ep['air_date'] ?? '') ?>">
                                                <div class="ep-runtime-wrap">
                                                    <input type="number" class="ep-runtime" placeholder="0" value="<?= (int)($ep['runtime'] ?? 0) ?: '' ?>" min="0">
                                                    <span class="ep-runtime-suffix">min</span>
                                                </div>
                                                <div class="ep-still-thumb <?= empty($still) ? 'ep-still-thumb--empty' : '' ?>">
                                                    <?php if (!empty($still)): ?>
                                                        <img src="<?= htmlspecialchars($still) ?>" alt="" onerror="this.parentElement.classList.add('ep-still-thumb--empty');this.remove()">
                                                    <?php else: ?>
                                                        <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="3" width="18" height="18" rx="2"/><circle cx="8.5" cy="8.5" r="1.5"/><polyline points="21 15 16 10 5 21"/></svg>
                                                    <?php endif; ?>
                                                </div>
                                                <a href="/player/?slug=<?= urlencode($name) ?>&type=<?= $type ?>&saison=<?= (int)($season['tmdb_season'] ?? 0) ?>&episode=<?= $ei + 1 ?>" target="_blank" class="admin-ep-play-btn" title="Aperçu player"><svg width="11" height="11" viewBox="0 0 24 24" fill="currentColor" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M5 5a2 2 0 0 1 3.008-1.728l11.997 6.998a2 2 0 0 1 .003 3.458l-12 7A2 2 0 0 1 5 19z"/></svg></a>
                                                <button type="button" class="admin-ep-expand" title="Synopsis & still" onclick="toggleEpDetails(this)"><svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><polyline points="6 9 12 15 18 9"/></svg></button>
                                                <button type="button" class="admin-remove-btn" onclick="removeParent(this,'.admin-ep-row')"><svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg></button>
                                            </div>
                                            <div class="admin-ep-row__details" hidden>
                                                <textarea class="ep-overview" placeholder="Synopsis de l'épisode…"><?= htmlspecialchars($ep['overview'] ?? '') ?></textarea>
                                                <div class="admin-ep-still-group">
                                                    <input type="text" class="ep-still" placeholder="URL still (16/9)…"
                                                        value="<?= htmlspecialchars($still) ?>"
                                                        oninput="updateEpStill(this)">
                                                    <div class="admin-ep-still-preview<?= empty($still) ? ' admin-ep-still-preview--empty' : '' ?>">
                                                        <img src="<?= htmlspecialchars($still) ?>" alt=""
                                                            <?= empty($still) ? 'style="display:none"' : '' ?>
                                                            onerror="this.style.display='none';this.closest('.admin-ep-still-preview').classList.add('admin-ep-still-preview--empty')">
                                                        <svg class="ep-still-placeholder" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><rect x="3" y="3" width="18" height="18" rx="2"/><circle cx="8.5" cy="8.5" r="1.5"/><polyline points="21 15 16 10 5 21"/></svg>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                                <div class="admin-ep-list__footer">
                                    <button type="button" class="admin-add-btn" onclick="addEpisode(this)">+ Épisode</button>
                                </div>
                            </div>
                        </details>
                    <?php endforeach; ?>
                </div>
                <button type="button" class="admin-add-btn" onclick="addSeason()" style="margin-top:12px">+ Ajouter une saison</button>

            </div><!-- /tab-saisons -->
        <?php endif; ?>

    </div><!-- /admin-edit-body -->

    <script>
        const MEDIA_TYPE = <?= json_encode($type) ?>;
        const MEDIA_NAME = <?= json_encode($name) ?>;
        let isDirty = false;

        // ── Tabs ──────────────────────────────────────────────────────────────────
        document.querySelectorAll('.admin-tab-btn').forEach(btn => {
            btn.addEventListener('click', () => {
                document.querySelectorAll('.admin-tab-btn').forEach(b => b.classList.remove('is-active'));
                document.querySelectorAll('.admin-tab-panel').forEach(p => p.classList.remove('is-active'));
                btn.classList.add('is-active');
                document.getElementById('tab-' + btn.dataset.tab).classList.add('is-active');
            });
        });

        // Mark dirty on any input change
        document.addEventListener('input', () => {
            isDirty = true;
        });
        document.addEventListener('change', () => {
            isDirty = true;
        });
        window.addEventListener('beforeunload', e => {
            if (isDirty) {
                e.preventDefault();
                e.returnValue = '';
            }
        });

        // ── Image previews ────────────────────────────────────────────────────────
        function updateImgPreview(inputId, imgId) {
            const url = document.getElementById(inputId).value.trim();
            const img = document.getElementById(imgId);
            if (url) {
                img.src = url;
                img.style.display = '';
            } else {
                img.style.display = 'none';
            }
        }

        function updateLogoPreview(input) {
            const row = input.closest('.admin-logo-row');
            const img = row.querySelector('.admin-logo-preview img');
            const url = input.value.trim();
            if (url) {
                img.src = url;
                img.style.display = '';
            } else {
                img.style.display = 'none';
            }
        }

        function updateCastAvatar(input) {
            const row = input.closest('.admin-cast-row');
            const avatar = row.querySelector('.admin-cast-avatar');
            const url = input.value.trim();
            if (url) {
                avatar.innerHTML = '<img src="' + url + '" alt="" onerror="this.style.display=\'none\'">';
            } else {
                avatar.innerHTML = lc('user', 24);
            }
        }

        function updateEpStill(input) {
            const row   = input.closest('.admin-ep-row__details');
            const img   = row.querySelector('.admin-ep-still-preview img');
            const thumb = input.closest('.admin-ep-row').querySelector('.ep-still-thumb');
            const url   = input.value.trim();
            const preview = row.querySelector('.admin-ep-still-preview');
            if (url) {
                img.src = url;
                img.style.display = '';
                preview.classList.remove('admin-ep-still-preview--empty');
                // mise à jour du thumbnail inline
                thumb.classList.remove('ep-still-thumb--empty');
                thumb.innerHTML = `<img src="${url}" alt="" onerror="this.parentElement.classList.add('ep-still-thumb--empty');this.remove()">`;
            } else {
                img.style.display = 'none';
                preview.classList.add('admin-ep-still-preview--empty');
                thumb.classList.add('ep-still-thumb--empty');
                thumb.innerHTML = `<svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="3" width="18" height="18" rx="2"/><circle cx="8.5" cy="8.5" r="1.5"/><polyline points="21 15 16 10 5 21"/></svg>`;
            }
        }

        // ── Episode details expand ────────────────────────────────────────────────
        function toggleEpDetails(btn) {
            const details = btn.closest('.admin-ep-row').querySelector('.admin-ep-row__details');
            const hidden = details.hasAttribute('hidden');
            if (hidden) {
                details.removeAttribute('hidden');
                btn.style.color = 'var(--color-accent)';
                btn.querySelector('svg').style.transform = 'rotate(180deg)';
            } else {
                details.setAttribute('hidden', '');
                btn.style.color = '';
                btn.querySelector('svg').style.transform = '';
            }
        }

        // ── Generic helpers ───────────────────────────────────────────────────────
        function removeParent(btn, selector) {
            const el = btn.closest(selector);
            if (el) el.remove();
            isDirty = true;
        }

        async function removeSeason(btn) {
            if (!await HoshimiConfirm.ask('Supprimer cette saison des métadonnées ?', { confirm: 'Supprimer', type: 'danger' })) return;
            btn.closest('.admin-season').remove();
            isDirty = true;
        }

        // ── Genre tags ────────────────────────────────────────────────────────────
        function removeGenre(btn) {
            btn.closest('.admin-tag').remove();
            isDirty = true;
        }

        function addGenre() {
            const input = document.getElementById('genre-input');
            const val = input.value.trim();
            if (!val) return;
            const container = document.getElementById('genres-container');
            const span = document.createElement('span');
            span.className = 'admin-tag';
            span.dataset.genre = val;
            span.innerHTML = val + ' <button type="button" class="admin-tag__remove" onclick="removeGenre(this)"><svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg></button>';
            container.appendChild(span);
            input.value = '';
            isDirty = true;
        }

        document.getElementById('genre-input')?.addEventListener('keydown', e => {
            if (e.key === 'Enter') {
                e.preventDefault();
                addGenre();
            }
        });

        // ── Logos ─────────────────────────────────────────────────────────────────
        function addLogo() {
            const container = document.getElementById('logos-container');
            const row = document.createElement('div');
            row.className = 'admin-logo-row';
            row.innerHTML = `
        <div class="admin-logo-field">
          <label class="admin-logo-label">w500 <span>(500px, utilisé à l'affichage)</span></label>
          <input type="text" class="logo-w500" placeholder="https://image.tmdb.org/…" oninput="updateLogoPreview(this)">
        </div>
        <div class="admin-logo-field">
          <label class="admin-logo-label">original <span>(pleine résolution, fallback)</span></label>
          <input type="text" class="logo-original" placeholder="https://image.tmdb.org/…">
        </div>
        <div class="admin-logo-preview"><img src="" alt="" style="display:none"></div>
        <button type="button" class="admin-remove-btn" onclick="removeParent(this,'.admin-logo-row')"><svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg></button>`;
            container.appendChild(row);
            isDirty = true;
        }

        // ── Cast ──────────────────────────────────────────────────────────────────
        function addCast() {
            const container = document.getElementById('cast-container');
            const row = document.createElement('div');
            row.className = 'admin-cast-row';
            row.innerHTML = `
        <div class="admin-cast-avatar">${lc('user', 24)}</div>
        <input type="text" class="cast-profile" placeholder="URL photo" oninput="updateCastAvatar(this)">
        <input type="text" class="cast-name" placeholder="Nom">
        <input type="text" class="cast-char" placeholder="Personnage">
        <div class="cs-wrap admin-cs-cast-role" data-value="SUPPORTING">
            <input type="hidden" class="cast-role" value="SUPPORTING">
            <button class="cs-btn" type="button" aria-haspopup="listbox" aria-expanded="false">
                <span class="cs-label">Secondaire</span>
            </button>
            <ul class="cs-list" role="listbox">
                <li class="cs-item" data-value="MAIN" role="option" aria-selected="false"><span class="cs-check"></span><span class="cs-body"><span class="cs-item-label">Principal</span></span></li>
                <li class="cs-item is-active" data-value="SUPPORTING" role="option" aria-selected="true"><span class="cs-check"></span><span class="cs-body"><span class="cs-item-label">Secondaire</span></span></li>
            </ul>
        </div>
        <button type="button" class="admin-remove-btn" onclick="removeParent(this,'.admin-cast-row')"><svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg></button>`;
            container.appendChild(row);
            row.querySelector('.cast-name').focus();
            isDirty = true;
        }

        // ── Seasons / episodes ────────────────────────────────────────────────────
        function episodeRowHtml(ep = {}) {
            const still = ep.still || '';
            const name  = (ep.name || '').replace(/"/g, '&quot;');
            const date  = ep.air_date || '';
            const rt    = ep.runtime || '';
            const ov    = (ep.overview || '').replace(/</g, '&lt;');
            const thumbHtml = still
                ? `<img src="${still}" alt="" onerror="this.parentElement.classList.add('ep-still-thumb--empty');this.remove()">`
                : `<svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="3" width="18" height="18" rx="2"/><circle cx="8.5" cy="8.5" r="1.5"/><polyline points="21 15 16 10 5 21"/></svg>`;
            return `<div class="admin-ep-row">
  <div class="admin-ep-row__main">
    <span class="admin-ep-num">-</span>
    <input type="text" class="ep-name" placeholder="Titre" value="${name}">
    <input type="date" class="ep-air_date" value="${date}">
    <div class="ep-runtime-wrap">
      <input type="number" class="ep-runtime" placeholder="0" value="${rt}" min="0">
      <span class="ep-runtime-suffix">min</span>
    </div>
    <div class="ep-still-thumb ${still ? '' : 'ep-still-thumb--empty'}">${thumbHtml}</div>
    <button type="button" class="admin-ep-expand" title="Synopsis & still" onclick="toggleEpDetails(this)"><svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><polyline points="6 9 12 15 18 9"/></svg></button>
    <button type="button" class="admin-remove-btn" onclick="removeParent(this,'.admin-ep-row')"><svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg></button>
  </div>
  <div class="admin-ep-row__details" hidden>
    <textarea class="ep-overview" placeholder="Synopsis de l'épisode…">${ov}</textarea>
    <div class="admin-ep-still-group">
      <input type="text" class="ep-still" placeholder="URL still (16/9)…" value="${still}" oninput="updateEpStill(this)">
      <div class="admin-ep-still-preview${still ? '' : ' admin-ep-still-preview--empty'}">
        <img src="${still}" alt="" ${still ? '' : 'style="display:none"'} onerror="this.style.display='none';this.closest('.admin-ep-still-preview').classList.add('admin-ep-still-preview--empty')">
        <svg class="ep-still-placeholder" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><rect x="3" y="3" width="18" height="18" rx="2"/><circle cx="8.5" cy="8.5" r="1.5"/><polyline points="21 15 16 10 5 21"/></svg>
      </div>
    </div>
  </div>
</div>`;
        }

        function updateEpNumbers(list) {
            list.querySelectorAll('.admin-ep-num').forEach((span, i) => {
                span.textContent = i + 1;
            });
        }

        function addEpisode(btn) {
            const body = btn.closest('.admin-season__body');
            const list = body.querySelector('.admin-ep-list');
            list.insertAdjacentHTML('beforeend', episodeRowHtml());
            updateEpNumbers(list);
            // màj badge épisodes
            const count = list.querySelectorAll('.admin-ep-row').length;
            body.closest('.admin-season').querySelector('.admin-season__count').textContent =
                count + ' épisode' + (count > 1 ? 's' : '');
            isDirty = true;
        }

        function addSeason() {
            const key = prompt('Nom du dossier de la saison (ex : Season 02) :');
            if (!key || !key.trim()) return;
            const trimmed = key.trim();
            const container = document.getElementById('seasons-container');
            const block = document.createElement('details');
            block.className = 'admin-season';
            block.dataset.key = trimmed;
            block.open = true;
            block.innerHTML = `
  <summary class="admin-season__header">
    <span class="admin-season__arrow"><svg width="11" height="11" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><polyline points="9 18 15 12 9 6"/></svg></span>
    <span class="admin-season__name">${trimmed}</span>
    <span class="admin-season__count">0 épisode</span>
    <label class="admin-season__tmdb-label" onclick="event.preventDefault()">
      TMDB # <input type="number" class="season-tmdb" value="0" min="0" onclick="event.stopPropagation()">
    </label>
    <button type="button" class="admin-season__del" onclick="event.preventDefault();removeSeason(this)">Supprimer</button>
  </summary>
  <div class="admin-season__body">
    <div class="admin-ep-list"></div>
    <div class="admin-ep-list__footer">
      <button type="button" class="admin-add-btn" onclick="addEpisode(this)">+ Épisode</button>
    </div>
  </div>`;
            container.appendChild(block);
            isDirty = true;
        }

        // ── Collect form data ─────────────────────────────────────────────────────
        function val(name) {
            return document.querySelector(`[name="${name}"]`)?.value?.trim() ?? '';
        }

        function numVal(name) {
            const v = parseFloat(val(name));
            return isNaN(v) ? null : v;
        }

        function intVal(name) {
            const v = parseInt(val(name));
            return isNaN(v) ? null : v;
        }

        function collectFormData() {
            return {
                title: val('title'),
                title_original: val('title_original'),
                overview: val('overview'),
                year: intVal('year'),
                score: numVal('score'),
                runtime: intVal('runtime'),
                status: val('status'),
                format: val('format'),
                studio: val('studio'),
                tmdb_id: intVal('tmdb_id'),
                episodes_total: intVal('episodes_total'),
                seasons_count: intVal('seasons_count'),
                cover: val('cover'),
                banner: val('banner'),
                genres: [...document.querySelectorAll('#genres-container .admin-tag')].map(t => t.dataset.genre).filter(Boolean),
                logos: [...document.querySelectorAll('#logos-container .admin-logo-row')].map(row => ({
                    w500: row.querySelector('.logo-w500')?.value.trim() ?? '',
                    original: row.querySelector('.logo-original')?.value.trim() ?? '',
                })).filter(l => l.w500 || l.original),
                cast: [...document.querySelectorAll('#cast-container .admin-cast-row')].map(row => ({
                    name: row.querySelector('.cast-name')?.value.trim() ?? '',
                    character: row.querySelector('.cast-char')?.value.trim() ?? '',
                    profile_url: row.querySelector('.cast-profile')?.value.trim() ?? '',
                    role: row.querySelector('.cast-role')?.value ?? 'SUPPORTING',
                })).filter(c => c.name),
                seasons: collectSeasons(),
            };
        }

        function collectSeasons() {
            const seasons = {};
            document.querySelectorAll('#seasons-container .admin-season').forEach(block => {
                const key = block.dataset.key;
                if (!key) return;
                const tmdbSeason = parseInt(block.querySelector('.season-tmdb')?.value) || 0;
                const episodes = [...block.querySelectorAll('.admin-ep-row')].map(row => {
                    const name = row.querySelector('.ep-name')?.value.trim();
                    if (!name) return null;
                    const ep = {
                        name
                    };
                    const overview = row.querySelector('.ep-overview')?.value.trim();
                    if (overview) ep.overview = overview;
                    const airDate = row.querySelector('.ep-air_date')?.value;
                    if (airDate) ep.air_date = airDate;
                    const runtime = parseInt(row.querySelector('.ep-runtime')?.value);
                    if (runtime > 0) ep.runtime = runtime;
                    const still = row.querySelector('.ep-still')?.value.trim();
                    if (still) ep.still = still;
                    return ep;
                }).filter(Boolean);
                seasons[key] = {
                    tmdb_season: tmdbSeason,
                    episodes
                };
            });
            return seasons;
        }

        // ── Save ──────────────────────────────────────────────────────────────────
        function validateForm(meta) {
            const errs = [];
            if (!meta.title || !meta.title.trim()) {
                errs.push({ field: 'title', msg: 'Le titre est obligatoire.' });
            }
            if (meta.score !== undefined && meta.score !== null && meta.score !== '') {
                const s = parseFloat(meta.score);
                if (!isNaN(s) && (s < 0 || s > 10)) {
                    errs.push({ field: 'score', msg: 'Le score doit être entre 0 et 10.' });
                }
            }
            if (meta.year !== undefined && meta.year !== null && meta.year !== '') {
                const y = parseInt(meta.year);
                if (!isNaN(y) && (y < 1900 || y > 2100)) {
                    errs.push({ field: 'year', msg: "L'année doit être entre 1900 et 2100." });
                }
            }
            if (meta.runtime !== undefined && meta.runtime !== null && meta.runtime !== '') {
                if (parseInt(meta.runtime) < 0) {
                    errs.push({ field: 'runtime', msg: 'La durée ne peut pas être négative.' });
                }
            }
            return errs;
        }

        function highlightErrors(errors) {
            document.querySelectorAll('.admin-field-group.has-error').forEach(el => el.classList.remove('has-error'));
            document.querySelectorAll('.admin-field-error').forEach(el => el.remove());
            errors.forEach(({ field, msg }) => {
                const input = document.getElementById('field-' + field) || document.querySelector(`[name="${field}"]`);
                if (!input) return;
                const group = input.closest('.admin-field-group');
                if (group) {
                    group.classList.add('has-error');
                    const errEl = document.createElement('span');
                    errEl.className = 'admin-field-error';
                    errEl.textContent = msg;
                    group.appendChild(errEl);
                }
            });
            if (errors.length > 0) {
                const first = document.getElementById('field-' + errors[0].field) || document.querySelector(`[name="${errors[0].field}"]`);
                first?.scrollIntoView({ behavior: 'smooth', block: 'center' });
            }
        }

        async function saveAll() {
            const btn  = document.getElementById('save-btn');
            const meta = collectFormData();

            // Validation client
            const clientErrors = validateForm(meta);
            if (clientErrors.length > 0) {
                highlightErrors(clientErrors);
                showToast(clientErrors[0].msg, 'error');
                return;
            }
            highlightErrors([]);

            btn.disabled = true;
            btn.textContent = 'Sauvegarde…';
            try {
                const res = await fetch('/admin/save.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ type: MEDIA_TYPE, name: MEDIA_NAME, meta }),
                });
                const data = await res.json();
                if (data.ok) {
                    isDirty = false;
                    showToast('Sauvegardé', 'success');
                } else if (data.errors) {
                    highlightErrors(data.errors);
                    showToast(data.errors[0].msg, 'error');
                } else {
                    showToast('Erreur : ' + (data.error ?? 'inconnue'), 'error');
                }
            } catch (e) {
                showToast('Erreur réseau', 'error');
            } finally {
                btn.disabled = false;
                btn.textContent = 'Sauvegarder';
            }
        }

        // ── Menu Actions ──────────────────────────────────────────────────────
        const ACTION_LABELS = {
            update_meta:  'Sync score & statut',
            seasons_meta: 'Refresh épisodes',
            cover_only:   'Re-télécharger la cover',
            gen_thumb:    'Générer miniatures',
            refetch_meta: 'Refetch complet',
        };

        function toggleActionsMenu(e) {
            e.stopPropagation();
            const dd    = document.getElementById('adm-act-dd');
            const panel = document.getElementById('adm-act-panel');
            const open  = !panel.hidden;
            panel.hidden = open;
            dd.classList.toggle('is-open', !open);
        }

        document.addEventListener('click', () => {
            const panel = document.getElementById('adm-act-panel');
            const dd    = document.getElementById('adm-act-dd');
            if (panel) panel.hidden = true;
            if (dd)    dd.classList.remove('is-open');
        });

        document.getElementById('adm-act-panel')?.addEventListener('click', async e => {
            const btn = e.target.closest('.adm-act-dd__item');
            if (!btn) return;
            const action = btn.dataset.action;
            document.getElementById('adm-act-panel').hidden = true;
            await runAction(action);
        });

        async function runAction(action) {
            const label = ACTION_LABELS[action] ?? action;

            // ── Génération miniatures (fire-and-forget) ────────────────────
            if (action === 'gen_thumb') {
                showToast('Génération des miniatures lancée…');
                await fetch('/admin/api/run-scan.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ action: 'gen_thumb', type: <?= json_encode($type) ?>, name: <?= json_encode($name) ?> }),
                }).catch(() => {});
                return;
            }

            // ── Re-télécharger la cover ────────────────────────────────────
            if (action === 'cover_only') {
                showToast('Téléchargement de la cover…');
                try {
                    const r = await fetch('/admin/api/run-scan.php', {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/json' },
                        body: JSON.stringify({ action: 'redownload_cover', type: <?= json_encode($type) ?>, name: <?= json_encode($name) ?>, force: true }),
                    });
                    const d = await r.json();
                    if (d.ok) {
                        showToast('Cover re-téléchargée');
                        await fetch('/admin/api/maintenance.php', { method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify({ action: 'purge_webp' }) });
                        const img = document.getElementById('cover-preview');
                        if (img?.src) { const s = img.src.split('?')[0]; img.src = s + '?t=' + Date.now(); }
                    } else {
                        showToast(d.error ?? 'Erreur', 'error');
                    }
                } catch(e) { showToast('Erreur réseau', 'error'); }
                return;
            }

            // ── Actions avec log modal ─────────────────────────────────────
            const modal = document.getElementById('tmdb-update-modal');
            document.getElementById('tmdb-update-modal-title').textContent = label;
            const logEl = document.getElementById('tmdb-update-log');
            logEl.innerHTML = '';
            modal.classList.add('is-visible');

            let res;
            try {
                res = await fetch('/admin/api/run-scan.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ action, type: <?= json_encode($type) ?>, name: <?= json_encode($name) ?> })
                });
                res = await res.json();
            } catch(e) {
                appendLog(logEl, '[ERR ] ' + e.message, 'log-err');
                return;
            }

            if (!res.ok) {
                appendLog(logEl, '[ERR ] ' + res.error, 'log-err');
                return;
            }

            pollTmdbLog(res.job_id, logEl);
        }

        // ── Jobs avec log ─────────────────────────────────────────────────
        let tmdbPollTimer = null;

        function pollTmdbLog(jobId, logEl) {
            let offset = 0;
            tmdbPollTimer = setInterval(async () => {
                try {
                    const r = await fetch(`/admin/api/scan-log.php?job=${jobId}&offset=${offset}`);
                    const d = await r.json();
                    if (!d.ok) return;
                    (d.lines || []).forEach(l => appendLog(logEl, l));
                    offset += (d.lines || []).length;
                    if (d.done || d.error) {
                        clearInterval(tmdbPollTimer);
                        fetch(`/admin/api/scan-log.php?job=${jobId}&cleanup=1`);
                        if (d.error) {
                            showToast('Erreur lors de la mise à jour.', 'error');
                        } else {
                            reloadMetaFields();
                        }
                    }
                } catch(e) {}
            }, 1000);
        }

        function appendLog(el, line, cls) {
            const div = document.createElement('div');
            if (!cls) {
                if (line.startsWith('[ OK ]') || line.startsWith('[OK]') || line.includes('✓')) cls = 'log-ok';
                else if (line.startsWith('[WARN]')) cls = 'log-warn';
                else if (line.startsWith('[ERR') || line.startsWith('[HOSHIMI_ERROR]')) cls = 'log-err';
                else if (line.startsWith('[INFO]')) cls = 'log-info';
                else if (line.startsWith('---') || line.startsWith('===')) cls = 'log-sep';
                else if (line.includes('--')) cls = 'log-skip';
            }
            if (cls) div.className = cls;
            div.textContent = line;
            el.appendChild(div);
            el.scrollTop = el.scrollHeight;
        }

        function closeTmdbModal() {
            clearInterval(tmdbPollTimer);
            document.getElementById('tmdb-update-modal').classList.remove('is-visible');
        }

        // ── Sync cs:change → hidden input + isDirty ───────────────────────────
        document.addEventListener('cs:change', e => {
            const wrap  = e.target.closest('.cs-wrap');
            const input = wrap?.querySelector('input[type="hidden"]');
            if (input) input.value = e.detail.value;
            isDirty = true;
        });

        // ── Initialise HoshimiSelect sur tous les cs-wrap (y compris cast) ──
        HoshimiSelect.initAll();

        // Observer pour les nouvelles cast rows ajoutées dynamiquement
        new MutationObserver(muts => {
            muts.forEach(m => m.addedNodes.forEach(n => {
                if (n.nodeType === 1) n.querySelectorAll?.('.cs-wrap').forEach(HoshimiSelect.init);
            }));
        }).observe(document.getElementById('cast-container') ?? document.body, { childList: true, subtree: false });

        // ── Helper pour mettre à jour un cs-wrap depuis JS ────────────────────
        function csSet(wrapId, value) {
            const wrap = document.getElementById(wrapId);
            if (!wrap) return;
            const item = wrap.querySelector(`.cs-item[data-value="${CSS.escape(String(value ?? ''))}"]`);
            const labelEl  = wrap.querySelector('.cs-label');
            const hiddenEl = wrap.querySelector('input[type="hidden"]');
            const labelTxt = item?.querySelector('.cs-item-label')?.textContent ?? String(value ?? '');
            if (labelEl)  labelEl.textContent = labelTxt;
            if (hiddenEl) hiddenEl.value      = String(value ?? '');
            wrap.dataset.value = String(value ?? '');
            wrap.querySelectorAll('.cs-item').forEach(i => {
                const active = i.dataset.value === String(value ?? '');
                i.classList.toggle('is-active', active);
                i.setAttribute('aria-selected', active);
                const chk = i.querySelector('.cs-check');
                if (chk) chk.innerHTML = active ? HoshimiSelect.SVG_CHECK : '';
            });
        }

        // ── Live update des champs après job ──────────────────────────────────
        async function reloadMetaFields() {
            try {
                const r = await fetch(`/admin/api/get-meta.php?type=<?= urlencode($type) ?>&name=<?= urlencode($name) ?>`);
                const d = await r.json();
                if (!d.ok) return;
                const m = d.meta;

                const set = (sel, val) => { const el = document.querySelector(sel); if (el) el.value = val ?? ''; };
                set('[name="title"]',          m.title ?? '');
                set('[name="title_original"]', m.title_original ?? '');
                set('[name="year"]',           m.year ?? '');
                set('[name="score"]',          m.score ?? '');
                set('[name="runtime"]',        m.runtime ?? '');
                csSet('cs-status', m.status ?? '');
                csSet('cs-format', m.format ?? '');
                set('[name="studio"]',         m.studio ?? '');
                set('[name="tmdb_id"]',        m.tmdb_id ?? '');
                set('[name="episodes_total"]', m.episodes_total ?? '');
                set('[name="seasons_count"]',  m.seasons_count ?? '');
                set('textarea[name="overview"]', m.overview ?? '');

                // Cover
                if (m.cover) {
                    set('[name="cover"]', m.cover);
                    const img = document.getElementById('cover-preview');
                    if (img) { img.src = m.cover; img.style.display = ''; }
                }
                // Banner
                if (m.banner) {
                    set('[name="banner"]', m.banner);
                    const img = document.getElementById('banner-preview');
                    if (img) { img.src = m.banner; img.style.display = ''; }
                }

                // Genres
                const gc = document.getElementById('genres-container');
                if (gc && Array.isArray(m.genres)) {
                    gc.innerHTML = m.genres.map(g =>
                        `<span class="admin-tag" data-genre="${g}">${g}<button type="button" class="admin-tag__remove" onclick="removeGenre(this)"><svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg></button></span>`
                    ).join('');
                }

                showToast('Champs mis à jour. Rechargez pour voir le cast et les saisons.');
            } catch(e) {
                showToast('Impossible de recharger les champs.', 'error');
            }
        }

    </script>

<!-- Modal mise à jour TMDB -->
<div class="admin-update-modal" id="tmdb-update-modal">
    <div class="admin-update-modal__box">
        <div class="admin-update-modal__header">
            <span id="tmdb-update-modal-title">TMDB</span>
            <button onclick="closeTmdbModal()" class="admin-update-modal__close"><?= lc('x', 14) ?></button>
        </div>
        <div class="admin-scan-log__output" id="tmdb-update-log" style="height:320px"></div>
    </div>
</div>
</body>

</html>