<?php
declare(strict_types=1);

require_once __DIR__ . '/../../app/MacGuard.php';
require_once __DIR__ . '/_completeness.php';
require_once __DIR__ . '/../components/icon.php';
MacGuard::protect();

$current_path = $_SERVER['REQUEST_URI'];
$activeTab    = $_GET['tab'] ?? 'all';

$mediaTypes = [
    'anime' => ['root' => $_ENV['ANIMES_PATH'] ?? getenv('ANIMES_PATH') ?: '/media/animes', 'label' => 'Anime', 'icon' => lc('tv-2', 14)],
    'film'  => ['root' => $_ENV['FILMS_PATH']  ?? getenv('FILMS_PATH')  ?: '/media/films',  'label' => 'Film',  'icon' => lc('clapperboard', 14)],
    'serie' => ['root' => $_ENV['SERIES_PATH'] ?? getenv('SERIES_PATH') ?: '/media/series', 'label' => 'Série', 'icon' => lc('tv', 14)],
];
?>
<!doctype html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Médias — Hoshimi Admin</title>
    <link rel="stylesheet" href="/css/hoshimi.css">
    <link rel="stylesheet" href="/css/pages/admin.css">
    <script src="/js/storage.js"></script>
    <script src="/js/toast.js"></script>
</head>
<body>
<?php include __DIR__ . '/../components/navbar.php'; ?>

<main class="admin-page">
    <div class="admin-page__header">
        <div class="section-header section-header--simple" style="margin-bottom:0">
            <div class="section-header__bar"></div>
            <div class="section-header__inner">
                <span class="section-header__type">Médias</span>
                <h1 class="section-header__title" style="font-size:1.4rem">Éditeur de métadonnées</h1>
            </div>
        </div>
        <?php include __DIR__ . '/_nav.php'; ?>
    </div>

    <div class="loader" id="loader">
        <div class="loader__ring"></div>
        <span class="loader__label">Chargement des médias…</span>
    </div>

    <div class="adm-content" id="adm-content" hidden>
<?php
if (ob_get_level()) { ob_end_flush(); } flush();

$allMedia   = [];
$noMetaDirs = [];

foreach ($mediaTypes as $type => $info) {
    $root = $info['root'];
    if (!is_dir($root)) continue;
    foreach (array_diff(scandir($root), ['.', '..']) as $dir) {
        $dirPath  = $root . DIRECTORY_SEPARATOR . $dir;
        if (!is_dir($dirPath)) continue;
        $metaPath = $dirPath . DIRECTORY_SEPARATOR . 'metadata.json';
        if (!file_exists($metaPath)) {
            $noMetaDirs[] = ['type' => $type, 'name' => $dir, 'label' => $info['label']];
            continue;
        }
        $meta = json_decode(file_get_contents($metaPath), true);
        if (!is_array($meta)) continue;
        $completeness = mediaCompleteness($meta, $type, $dirPath);
        $allMedia[] = [
            'type'         => $type,
            'label'        => $info['label'],
            'icon'         => $info['icon'],
            'name'         => $dir,
            'title'        => $meta['title'] ?? $dir,
            'year'         => $meta['year'] ?? null,
            'cover'        => $meta['cover'] ?? null,
            'status'       => $meta['status'] ?? null,
            'tmdb_id'      => $meta['tmdb_id'] ?? null,
            'studio'       => is_string($meta['studio'] ?? null) ? $meta['studio'] : (is_string($meta['director'] ?? null) ? $meta['director'] : null),
            'episodes'     => is_numeric($meta['episodes'] ?? $meta['episode_count'] ?? null) ? (int)($meta['episodes'] ?? $meta['episode_count']) : null,
            'seasons'      => is_numeric($meta['seasons'] ?? $meta['season_count'] ?? null) ? (int)($meta['seasons'] ?? $meta['season_count']) : null,
            'completeness' => $completeness,
        ];
    }
}

usort($allMedia, fn($a, $b) => strcasecmp($a['title'], $b['title']));

$counts = ['all' => count($allMedia)];
foreach ($mediaTypes as $type => $info) {
    $counts[$type] = count(array_filter($allMedia, fn($m) => $m['type'] === $type));
}
$counts['incomplete'] = count(array_filter($allMedia, fn($m) => $m['completeness']['status'] !== 'complete'));

$duplicateIds = [];
foreach (detectDuplicates($allMedia) as $group) {
    foreach ($group as $m) {
        $duplicateIds[$m['name'] . '_' . $m['type']] = true;
    }
}
?>
    <div class="admin-filter-bar">
        <input type="search" class="admin-filter-bar__search" id="admin-search" placeholder="Rechercher un titre…" autocomplete="off">
        <div class="admin-type-tabs">
            <button class="admin-type-tab <?= $activeTab === 'all' ? 'is-active' : '' ?>" data-filter="" data-tab="all">Tous (<?= $counts['all'] ?>)</button>
            <?php foreach ($mediaTypes as $type => $info): ?>
            <button class="admin-type-tab <?= $activeTab === $type ? 'is-active' : '' ?>" data-filter="<?= $type ?>" data-tab="<?= $type ?>"><?= $info['label'] ?> (<?= $counts[$type] ?>)</button>
            <?php endforeach; ?>
            <button class="admin-type-tab <?= $activeTab === 'incomplete' ? 'is-active' : '' ?>" data-filter="incomplete" data-tab="incomplete" style="<?= $counts['incomplete'] > 0 ? 'color:#f87171' : '' ?>">
                Incomplet (<?= $counts['incomplete'] ?>)
            </button>
        </div>
    </div>

    <div class="admin-media-list" id="admin-grid">
        <?php foreach ($allMedia as $m):
            $c     = $m['completeness'];
            $isDup = $duplicateIds[$m['name'] . '_' . $m['type']] ?? false;
            $missing = array_map('completenessFieldLabel', $c['missing']);
        ?>
        <div class="admin-row"
             data-type="<?= $m['type'] ?>"
             data-title="<?= htmlspecialchars(strtolower($m['title'])) ?>"
             data-status="<?= $c['status'] ?>"
             <?= $isDup ? 'data-duplicate="1"' : '' ?>>

            <!-- Miniature -->
            <div class="admin-row__thumb">
                <?php if ($m['cover']): ?>
                    <img src="<?= htmlspecialchars($m['cover']) ?>" alt="" loading="lazy">
                <?php else: ?>
                    <span><?= $m['icon'] ?></span>
                <?php endif; ?>
            </div>

            <!-- Infos -->
            <div class="admin-row__body">
                <div class="admin-row__top">
                    <span class="admin-row__title"><?= htmlspecialchars($m['title']) ?></span>
                    <div class="admin-row__badges">
                        <?php if ($m['year']): ?>
                            <span class="admin-row__year"><?= $m['year'] ?></span>
                        <?php endif; ?>
                        <span class="admin-row__type admin-row__type--<?= $m['type'] ?>"><?= $m['label'] ?></span>
                        <?php if ($isDup): ?>
                            <span class="admin-row__dup" title="TMDB ID en doublon">doublon</span>
                        <?php endif; ?>
                    </div>
                </div>

                <div class="admin-row__comp">
                    <div class="admin-row__bar">
                        <div class="admin-row__bar-fill admin-row__bar-fill--<?= $c['status'] ?>" style="width:<?= $c['pct'] ?>%"></div>
                    </div>
                    <span class="admin-row__pct admin-row__pct--<?= $c['status'] ?>"><?= $c['pct'] ?>%</span>
                </div>

                <!-- Métadonnées secondaires -->
                <div class="admin-row__meta">
                    <?php if ($m['studio']): ?>
                        <span class="admin-row__studio" title="<?= htmlspecialchars($m['studio']) ?>">
                            <?= htmlspecialchars($m['studio']) ?>
                        </span>
                    <?php endif; ?>
                    <?php if ($m['studio'] && ($m['episodes'] || $m['seasons'])): ?>
                        <span class="admin-row__meta-sep"></span>
                    <?php endif; ?>
                    <?php if ($m['seasons'] && $m['seasons'] > 1): ?>
                        <span class="admin-row__eps"><?= $m['seasons'] ?> saisons</span>
                    <?php elseif ($m['episodes']): ?>
                        <span class="admin-row__eps"><?= $m['episodes'] ?> ép.</span>
                    <?php endif; ?>
                    <?php if ($m['tmdb_id'] && ($m['studio'] || $m['episodes'])): ?>
                        <span class="admin-row__meta-sep"></span>
                    <?php endif; ?>
                    <?php if ($m['tmdb_id']): ?>
                        <a class="admin-row__tmdb-link"
                           href="https://www.themoviedb.org/<?= $m['type'] === 'film' ? 'movie' : 'tv' ?>/<?= $m['tmdb_id'] ?>"
                           target="_blank" rel="noopener" title="Voir sur TMDB">
                            <svg width="11" height="11" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M18 13v6a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h6"/><polyline points="15 3 21 3 21 9"/><line x1="10" y1="14" x2="21" y2="3"/></svg>
                            TMDB
                        </a>
                    <?php endif; ?>
                </div>

                <?php if ($missing): ?>
                <div class="admin-row__missing">
                    <?php foreach ($missing as $f): ?>
                        <span class="admin-row__miss-chip"><?= $f ?></span>
                    <?php endforeach; ?>
                </div>
                <?php endif; ?>
            </div>

            <!-- Actions -->
            <?php
                $publicUrl = '/content/?type=' . rawurlencode($m['type']) . '&slug=' . rawurlencode(urlencode($m['name']));
            ?>
            <div class="admin-row__actions">
                <!-- Éditer -->
                <a class="admin-row__btn"
                   href="/admin/edit.php?type=<?= urlencode($m['type']) ?>&name=<?= urlencode($m['name']) ?>"
                   title="Éditer les métadonnées">
                    <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4z"/></svg>
                    Éditer
                </a>
                <!-- Voir la fiche publique -->
                <a class="admin-row__btn admin-row__btn--view"
                   href="<?= $publicUrl ?>"
                   target="_blank" rel="noopener"
                   title="Voir la fiche publique">
                    <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>
                    Voir
                </a>
                <!-- Copier le nom du dossier -->
                <button class="admin-row__btn admin-row__btn--copy js-copy"
                        data-copy="<?= htmlspecialchars($m['name']) ?>"
                        title="Copier le nom du dossier">
                    <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="9" y="9" width="13" height="13" rx="2"/><path d="M5 15H4a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2h9a2 2 0 0 1 2 2v1"/></svg>
                    Copier
                </button>
                <!-- Fetch -->
                <button class="admin-row__btn admin-row__btn--rescan js-rescan"
                        data-type="<?= $m['type'] ?>"
                        data-name="<?= htmlspecialchars($m['name']) ?>"
                        title="Relancer le fetch metadata">
                    <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="1 4 1 10 7 10"/><path d="M3.51 15a9 9 0 1 0 .49-4.14"/></svg>
                    Fetch
                </button>
                <!-- Supprimer (déplacer vers _trash) -->
                <button class="admin-row__btn admin-row__btn--delete js-delete"
                        data-type="<?= $m['type'] ?>"
                        data-name="<?= htmlspecialchars($m['name']) ?>"
                        data-title="<?= htmlspecialchars($m['title']) ?>"
                        title="Déplacer vers _trash">
                    <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="3 6 5 6 21 6"/><path d="M19 6l-1 14a2 2 0 0 1-2 2H8a2 2 0 0 1-2-2L5 6"/><path d="M10 11v6"/><path d="M14 11v6"/><path d="M9 6V4a1 1 0 0 1 1-1h4a1 1 0 0 1 1 1v2"/></svg>
                    Supprimer
                </button>
            </div>
        </div>
        <?php endforeach; ?>
    </div>

    <!-- Pagination -->
    <div class="media-pagination" id="media-pagination">
        <button class="thumb-pag-btn" id="media-pag-prev" disabled>
            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="15 18 9 12 15 6"/></svg>
            Précédent
        </button>
        <span class="thumb-pag-info" id="media-pag-info"></span>
        <button class="thumb-pag-btn" id="media-pag-next">
            Suivant
            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="9 18 15 12 9 6"/></svg>
        </button>
    </div>

    <?php if ($noMetaDirs): ?>
    <div class="admin-nometa">
        <h2 class="admin-nometa__title"><?= count($noMetaDirs) ?> dossier(s) sans metadata</h2>
        <div class="admin-nometa__list">
            <?php foreach ($noMetaDirs as $nd): ?>
            <span class="admin-nometa__item">
                <span class="admin-scan-btn__dot admin-scan-btn__dot--<?= $nd['type'] ?>"></span>
                <?= htmlspecialchars($nd['name']) ?>
            </span>
            <?php endforeach; ?>
        </div>
    </div>
    <?php endif; ?>
</main>

<?php include __DIR__ . '/../components/footer.php'; ?>
<script>
const PER_PAGE   = 20;
const grid       = document.getElementById('admin-grid');
const allRows    = [...grid.querySelectorAll('.admin-row')];
const search     = document.getElementById('admin-search');
const typeBtns   = document.querySelectorAll('.admin-type-tab');
const prevBtn    = document.getElementById('media-pag-prev');
const nextBtn    = document.getElementById('media-pag-next');
const pageInfo   = document.getElementById('media-pag-info');
let activeFilter = '<?= $activeTab === 'incomplete' ? 'incomplete' : ($activeTab !== 'all' ? $activeTab : '') ?>';
let page         = 0;
let filtered     = allRows;

function applyFilter() {
    const q = search.value.toLowerCase().trim();
    filtered = allRows.filter(row => {
        const matchType = activeFilter === 'incomplete'
            ? row.dataset.status !== 'complete'
            : (!activeFilter || row.dataset.type === activeFilter);
        const matchSearch = !q || row.dataset.title.includes(q);
        return matchType && matchSearch;
    });
    page = 0;
    renderPage();
}

function renderPage() {
    const total = filtered.length;
    const pages = Math.max(1, Math.ceil(total / PER_PAGE));
    page = Math.min(page, pages - 1);
    const start = page * PER_PAGE;
    const end   = start + PER_PAGE;

    allRows.forEach(row => row.style.display = 'none');
    filtered.slice(start, end).forEach(row => row.style.display = '');
    grid.style.display = total === 0 ? 'none' : '';

    pageInfo.textContent = total === 0
        ? 'Aucun résultat'
        : `Page ${page + 1} / ${pages} — ${total} média${total > 1 ? 's' : ''}`;
    prevBtn.disabled = page === 0;
    nextBtn.disabled = page >= pages - 1;
}

prevBtn.addEventListener('click', () => {
    page--;
    renderPage();
    window.scrollTo({ top: grid.offsetTop - 100, behavior: 'smooth' });
});
nextBtn.addEventListener('click', () => {
    page++;
    renderPage();
    window.scrollTo({ top: grid.offsetTop - 100, behavior: 'smooth' });
});

search.addEventListener('input', applyFilter);
typeBtns.forEach(btn => {
    btn.addEventListener('click', () => {
        typeBtns.forEach(b => b.classList.remove('is-active'));
        btn.classList.add('is-active');
        activeFilter = btn.dataset.filter;
        applyFilter();
    });
});

applyFilter();

// Bouton Copier
grid.addEventListener('click', e => {
    const btn = e.target.closest('.js-copy');
    if (!btn) return;
    navigator.clipboard.writeText(btn.dataset.copy).then(() => {
        const orig = btn.innerHTML;
        btn.innerHTML = '<svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"/></svg> Copié';
        btn.style.color = '#4ade80';
        setTimeout(() => { btn.innerHTML = orig; btn.style.color = ''; }, 1800);
    });
});

// Bouton Supprimer
grid.addEventListener('click', async e => {
    const btn = e.target.closest('.js-delete');
    if (!btn) return;
    if (!await HoshimiConfirm.ask(`Déplacer "${btn.dataset.title}" vers _trash ? Cette action est réversible.`, { confirm: 'Déplacer', type: 'warning' })) return;
    btn.disabled = true;
    const row = btn.closest('.admin-row');
    const res = await fetch('/admin/api/delete-media.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ type: btn.dataset.type, name: btn.dataset.name }),
    }).then(r => r.json()).catch(() => ({ ok: false }));
    if (res.ok) {
        row.style.transition = 'opacity .3s, transform .3s';
        row.style.opacity = '0';
        row.style.transform = 'scale(.97)';
        setTimeout(() => row.remove(), 320);
    } else {
        btn.disabled = false;
        HoshimiToast.error(res.error ?? 'Erreur inconnue.');
    }
});

// Bouton Fetch inline
grid.addEventListener('click', async e => {
    const btn = e.target.closest('.js-rescan');
    if (!btn) return;
    btn.disabled = true;
    btn.innerHTML = '<svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="animation:spin .7s linear infinite"><polyline points="1 4 1 10 7 10"/><path d="M3.51 15a9 9 0 1 0 .49-4.14"/></svg> …';

    const res = await fetch('/admin/api/run-scan.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ action: 'fetch_one', type: btn.dataset.type, name: btn.dataset.name }),
    }).then(r => r.json()).catch(() => ({ ok: false }));

    if (!res.ok) {
        btn.innerHTML = lc('x', 13) + ' Erreur';
        btn.style.color = '#f87171';
        setTimeout(() => { btn.disabled = false; btn.innerHTML = '<svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="1 4 1 10 7 10"/><path d="M3.51 15a9 9 0 1 0 .49-4.14"/></svg> Fetch'; btn.style.color = ''; }, 2000);
        return;
    }

    // Polling jusqu'à done
    const jobId = res.job_id;
    const poll = setInterval(async () => {
        const log = await fetch(`/admin/api/scan-log.php?job=${jobId}`).then(r => r.json()).catch(() => null);
        if (!log || !log.done) return;
        clearInterval(poll);
        fetch(`/admin/api/scan-log.php?job=${jobId}&cleanup=1&type=${btn.dataset.type}`);
        btn.innerHTML = log.error ? lc('x', 13) : lc('check', 13);
        btn.style.color = log.error ? '#f87171' : '#4ade80';
        setTimeout(() => { btn.disabled = false; btn.innerHTML = '<svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="1 4 1 10 7 10"/><path d="M3.51 15a9 9 0 1 0 .49-4.14"/></svg> Fetch'; btn.style.color = ''; }, 2500);
    }, 1200);
});

applyFilter();
</script>
<style>
@keyframes spin { to { transform: rotate(360deg); } }
</style>
<script>(function(){const l=document.getElementById('loader'),c=document.getElementById('adm-content');if(c)c.hidden=false;if(l)l.remove();})();</script>
    </div><!-- #adm-content -->
</body>
</html>
