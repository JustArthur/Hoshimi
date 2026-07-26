<?php

declare(strict_types=1);

$current_path = '/wishboard/';

$dataFile = __DIR__ . '/../../data/wishboard.json';
$wishes   = [];
if (file_exists($dataFile)) {
    $wishes = json_decode(file_get_contents($dataFile), true) ?: [];
}

$VIDEO_EXTS = ['mkv','mp4','avi','mov','wmv','m4v','webm'];
$TYPE_ROOTS = [
    'anime' => rtrim($_ENV['ANIMES_PATH'] ?? getenv('ANIMES_PATH') ?: '/media/animes', '/\\'),
    'film'  => rtrim($_ENV['FILMS_PATH']  ?? getenv('FILMS_PATH')  ?: '/media/films',  '/\\'),
    'serie' => rtrim($_ENV['SERIES_PATH'] ?? getenv('SERIES_PATH') ?: '/media/series', '/\\'),
];

function pub_effectiveStatus(array $w, array $roots, array $videoExts): string {
    if ($w['status'] === 'pending') return 'pending';
    $folder = $w['folder'] ?? null;
    if (!$folder) return 'in_progress';
    $root = $roots[$w['type'] ?? 'film'] ?? '';
    $path = $root . DIRECTORY_SEPARATOR . $folder;
    if (!is_dir($path)) return 'in_progress';
    foreach (@scandir($path) ?: [] as $f) {
        if (in_array(strtolower(pathinfo($f, PATHINFO_EXTENSION)), $videoExts)) return 'added';
    }
    return 'in_progress';
}

function pub_isTooRecent(array $w): bool {
    $d = $w['release_date'] ?? null;
    return $d && (time() - (int)strtotime($d)) < 30 * 86400;
}

foreach ($wishes as &$w) {
    $w['_status'] = pub_effectiveStatus($w, $TYPE_ROOTS, $VIDEO_EXTS);
    $w['_recent'] = pub_isTooRecent($w);
}
unset($w);

usort($wishes, function($a, $b) {
    $order = ['pending' => 0, 'in_progress' => 1, 'added' => 2];
    $oa = $order[$a['_status']] ?? 9;
    $ob = $order[$b['_status']] ?? 9;
    if ($oa !== $ob) return $oa <=> $ob;
    return strcmp($b['created_at'], $a['created_at']);
});

$pendingCount    = count(array_filter($wishes, fn($w) => $w['_status'] === 'pending'));
$inProgressCount = count(array_filter($wishes, fn($w) => $w['_status'] === 'in_progress'));
$addedCount      = count(array_filter($wishes, fn($w) => $w['_status'] === 'added'));
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Wishboard — Hoshimi</title>
    <link rel="stylesheet" href="/css/hoshimi.css">
    <script src="/js/storage.js"></script>
    <script src="/js/select.js"></script>
</head>
<body>
<?php include __DIR__ . '/../components/navbar.php'; ?>

<main class="wish-page">

    <!-- ── Hero ──────────────────────────────────────────────────────────── -->
    <?php
    ob_start(); ?>
    <div class="wish-hero-search">
        <div class="wish-hero-search__field">
            <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
            <input class="wish-hero-search__input" id="wish-search-input" type="search"
                   placeholder="Rechercher un titre à demander…" autocomplete="off">
        </div>
        <div class="cs-wrap wish-hero-search__type" id="wish-type-select" data-value="all">
            <button class="cs-btn" type="button" aria-haspopup="listbox" aria-expanded="false">
                <span class="cs-label">Tout</span>
                <svg class="cs-chevron" width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><polyline points="6 9 12 15 18 9"/></svg>
            </button>
            <ul class="cs-list" role="listbox">
                <li class="cs-item is-active" data-value="all"   role="option" aria-selected="true"><span class="cs-check"><svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"/></svg></span><span class="cs-body"><span class="cs-item-label">Tout</span></span></li>
                <li class="cs-item"            data-value="film"  role="option" aria-selected="false"><span class="cs-check"></span><span class="cs-body"><span class="cs-item-label">Film</span></span></li>
                <li class="cs-item"            data-value="serie" role="option" aria-selected="false"><span class="cs-check"></span><span class="cs-body"><span class="cs-item-label">Série</span></span></li>
                <li class="cs-item"            data-value="anime" role="option" aria-selected="false"><span class="cs-check"></span><span class="cs-body"><span class="cs-item-label">Anime</span></span></li>
            </ul>
        </div>
    </div>
    <?php
    $heroTitle = 'Wishboard';
    $heroSub   = 'Les œuvres que vous souhaitez voir apparaître dans votre médiathèque.';
    $heroClass = 'wish-hero';
    $heroExtra = ob_get_clean();
    include __DIR__ . '/../components/page-hero.php';
    ?>

    <!-- ── Barre de contrôles (sticky) ──────────────────────────────────── -->
    <div class="fav-controls-bar" id="wish-controls-bar">
        <div class="container fav-controls-bar__inner">
            <div class="fav-tabs" id="wish-tabs">
                <?php
                $svg = fn(string $path) => '<svg xmlns="http://www.w3.org/2000/svg" width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">' . $path . '</svg>';
                $statusTabs = [
                    ['value' => 'all',         'label' => 'Toutes',     'color' => 'var(--color-accent)', 'count' => count($wishes),    'icon' => $svg('<line x1="8" y1="6" x2="21" y2="6"/><line x1="8" y1="12" x2="21" y2="12"/><line x1="8" y1="18" x2="21" y2="18"/><line x1="3" y1="6" x2="3.01" y2="6"/><line x1="3" y1="12" x2="3.01" y2="12"/><line x1="3" y1="18" x2="3.01" y2="18"/>')],
                    ['value' => 'pending',     'label' => 'En attente', 'color' => '#f7b76b',             'count' => $pendingCount,      'icon' => $svg('<circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/>')],
                    ['value' => 'in_progress', 'label' => 'En cours',   'color' => '#7eb8f7',             'count' => $inProgressCount,   'icon' => $svg('<path d="M21 12a9 9 0 1 1-6.219-8.56"/>')],
                    ['value' => 'added',       'label' => 'Ajoutées',   'color' => '#6ee7b7',             'count' => $addedCount,        'icon' => $svg('<path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/>')],
                ];
                foreach ($statusTabs as $tab):
                    if ($tab['value'] !== 'all' && $tab['count'] === 0) continue;
                ?>
                <button class="fav-tab<?= $tab['value'] === 'all' ? ' is-active' : '' ?>"
                        data-filter="<?= $tab['value'] ?>"
                        style="--tab-color:<?= $tab['color'] ?>">
                    <?= $tab['icon'] ?>
                    <span class="fav-tab__label"><?= $tab['label'] ?></span>
                </button>
                <?php endforeach; ?>
            </div>
        </div>
    </div>

    <div class="container fav-content">

        <!-- ── Vue A : liste des demandes ───────────────────────────────── -->
        <div id="view-list">

            <?php if (empty($wishes)): ?>
            <div class="fav-empty">
                <div class="fav-empty__visual">
                    <svg xmlns="http://www.w3.org/2000/svg" width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.2" stroke-linecap="round" stroke-linejoin="round"><path d="M9 12h6m-3-3v6M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2z"/></svg>
                </div>
                <div class="fav-empty__title">Aucune demande pour l'instant</div>
                <p class="fav-empty__sub">Recherche un titre ci-dessus pour faire ta première demande.</p>
            </div>
            <?php else: ?>
            <div class="grid-cards wish-list-grid" id="wish-list">
                <?php foreach ($wishes as $w):
                    $effSt       = $w['_status'];
                    $statusLabel = match($effSt) { 'in_progress' => 'En cours', 'added' => 'Ajouté', default => 'En attente' };
                    $typeLabel   = match($w['type'] ?? 'film') { 'anime' => 'Anime', 'serie' => 'Série', default => 'Film' };
                    $detailUrl   = null;
                    if ($effSt === 'added' && !empty($w['folder'])) {
                        $slug = urlencode($w['folder']);
                        $detailUrl = '/content/?type=' . ($w['type'] ?? 'film') . '&slug=' . $slug;
                    }
                    $Tag = $detailUrl ? 'a' : 'div';
                    $tagAttr = $detailUrl ? ' href="' . htmlspecialchars($detailUrl) . '"' : '';
                ?>
                <<?= $Tag ?><?= $tagAttr ?> class="card wish-list-card<?= $detailUrl ? ' wish-list-card--linked' : '' ?>" data-status="<?= $effSt ?>">
                    <div class="card__media">
                        <?php if (!empty($w['cover_url'])): ?>
                        <img class="card__poster is-loaded" src="<?= htmlspecialchars($w['cover_url']) ?>" alt="<?= htmlspecialchars($w['title']) ?>" loading="lazy">
                        <?php else: ?>
                        <div class="card__poster card__poster--empty"></div>
                        <?php endif; ?>
                        <?php
                            $isFilm   = ($w['type'] ?? 'film') === 'film';
                            $showRecent = $isFilm && $w['_recent'];
                        ?>
                        <span class="wish-status-badge wish-status-badge--<?= $showRecent ? 'recent' : $effSt ?>"><?= $showRecent ? 'Trop récent' : $statusLabel ?></span>
                        <div class="card__overlay"></div>
                        <div class="card__body">
                            <div class="card__title"><?= htmlspecialchars($w['title']) ?></div>
                            <div class="card__meta">
                                <span class="card__meta-badge"><?= $typeLabel ?></span>
                                <?php if (!empty($w['year'])): ?><span class="card__meta-year"><?= (int)$w['year'] ?></span><?php endif; ?>
                            </div>
                        </div>
                    </div>
                </<?= $Tag ?>>
                <?php endforeach; ?>
            </div>
            <div class="fav-empty" id="wish-filter-empty" style="display:none">
                <div class="fav-empty__visual">
                    <svg xmlns="http://www.w3.org/2000/svg" width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.2" stroke-linecap="round" stroke-linejoin="round"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
                </div>
                <div class="fav-empty__title" id="wish-filter-empty-title">Aucune demande ici</div>
                <p class="fav-empty__sub" id="wish-filter-empty-sub"></p>
            </div>
            <?php endif; ?>
        </div>

        <!-- ── Vue B : résultats TMDB ───────────────────────────────────── -->
        <div id="view-results" hidden>
            <div class="wish-results-header">
                <p class="wish-results-hint" id="wish-results-hint">Recherche…</p>
            </div>
            <div class="grid-cards wish-results-grid" id="wish-results-grid"></div>
        </div>

        <!-- ── Vue C : confirmation ─────────────────────────────────────── -->
        <div id="view-confirm" hidden>
            <button class="wish-back-btn" id="wish-back-btn">
                <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><polyline points="15 18 9 12 15 6"/></svg>
                Retour aux résultats
            </button>

            <div class="wish-confirm-layout">
                <!-- Cover -->
                <div class="wish-confirm-cover-wrap" id="wish-confirm-cover-wrap"></div>

                <!-- Infos + formulaire -->
                <div class="wish-confirm-form">
                    <div class="wish-confirm-meta" id="wish-confirm-info"></div>

                    <div class="wish-confirm-divider"></div>

                    <div class="wish-note-field">
                        <label class="wish-note-label" for="wish-note-input">
                            <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"/></svg>
                            Note (optionnel)
                        </label>
                        <textarea class="wish-note-textarea" id="wish-note-input"
                                  placeholder="Saison précise, lien, info utile…" maxlength="400" rows="4"></textarea>
                    </div>

                    <div class="wish-confirm-actions">
                        <button class="btn btn--primary wish-submit-btn" id="wish-submit-btn">
                            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><line x1="22" y1="2" x2="11" y2="13"/><polygon points="22 2 15 22 11 13 2 9 22 2"/></svg>
                            Envoyer la demande
                        </button>
                        <div class="wish-submit-feedback" id="wish-submit-feedback" hidden></div>
                    </div>
                </div>
            </div>
        </div>

    </div><!-- /.container.fav-content -->
</main>

<?php include __DIR__ . '/../components/footer.php'; ?>

<script>
(function () {
    let selected = null;
    let _debounce = null;

    const searchInput = document.getElementById('wish-search-input');
    const typeSelect  = document.getElementById('wish-type-select');
    const viewList    = document.getElementById('view-list');
    const viewResults = document.getElementById('view-results');
    const viewConfirm = document.getElementById('view-confirm');
    const resultsGrid = document.getElementById('wish-results-grid');
    const resultsHint = document.getElementById('wish-results-hint');
    const confirmCover= document.getElementById('wish-confirm-cover-wrap');
    const confirmInfo = document.getElementById('wish-confirm-info');
    const noteInput   = document.getElementById('wish-note-input');
    const submitBtn   = document.getElementById('wish-submit-btn');
    const feedback    = document.getElementById('wish-submit-feedback');
    const backBtn     = document.getElementById('wish-back-btn');

    /* ── Vue switcher ────────────────────────────────────────────────────── */
    function showView(name) {
        viewList.hidden    = name !== 'list';
        viewResults.hidden = name !== 'results';
        viewConfirm.hidden = name !== 'confirm';
    }

    /* ── Search ──────────────────────────────────────────────────────────── */
    searchInput?.addEventListener('input', () => {
        clearTimeout(_debounce);
        const q = searchInput.value.trim();
        if (!q) { showView('list'); return; }
        showView('results');
        resultsHint.textContent = 'Recherche…';
        resultsGrid.innerHTML = '';
        _debounce = setTimeout(() => doSearch(q), 380);
    });

    typeSelect?.addEventListener('cs:change', () => {
        const q = searchInput?.value.trim();
        if (q && q.length >= 2) doSearch(q);
    });

    async function doSearch(q) {
        const type = typeSelect?.dataset.value ?? 'all';
        try {
            const res  = await fetch(`/wishboard/api/search-tmdb.php?q=${encodeURIComponent(q)}&type=${encodeURIComponent(type)}`);
            const data = await res.json();
            if (!data.ok) { resultsHint.textContent = 'Erreur : ' + (data.error || 'inconnue'); return; }
            renderResults(data.results);
        } catch {
            resultsHint.textContent = 'Impossible de joindre le serveur.';
        }
    }

    function renderResults(results) {
        if (!results.length) {
            resultsHint.textContent = 'Aucun résultat.';
            resultsGrid.innerHTML = '';
            return;
        }
        resultsHint.textContent = `${results.length} résultat${results.length > 1 ? 's' : ''}`;

        const map = {};
        resultsGrid.innerHTML = results.map(r => {
            map[r.tmdb_id] = r;
            const typeLabel_ = typeLabel(r.type);
            return `
            <button class="card wish-result-btn" data-id="${r.tmdb_id}" type="button">
                <div class="card__media">
                    ${r.cover_url
                        ? `<img class="card__poster is-loaded" src="${esc(r.cover_url)}" alt="${esc(r.title)}" loading="lazy">`
                        : ''
                    }
                    <div class="card__overlay"></div>
                    <div class="card__body">
                        <div class="card__title">${esc(r.title)}</div>
                        <div class="card__meta">
                            <span class="card__meta-badge">${typeLabel_}</span>
                            ${r.year ? `<span class="card__meta-year">${r.year}</span>` : ''}
                        </div>
                    </div>
                </div>
            </button>`;
        }).join('');

        resultsGrid.querySelectorAll('.wish-result-btn').forEach(btn => {
            btn.addEventListener('click', () => {
                selected = map[btn.dataset.id];
                goToConfirm();
            });
        });
    }

    /* ── Confirmation ────────────────────────────────────────────────────── */
    function goToConfirm() {
        showView('confirm');
        noteInput.value = '';
        feedback.hidden = true;
        submitBtn.disabled = false;
        submitBtn.innerHTML = `<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><line x1="22" y1="2" x2="11" y2="13"/><polygon points="22 2 15 22 11 13 2 9 22 2"/></svg> Envoyer la demande`;

        // Cover avec effet poster
        confirmCover.innerHTML = `
            <div class="wish-confirm-poster">
                ${selected.cover_url
                    ? `<img class="wish-confirm-poster__img" src="${esc(selected.cover_url)}" alt="${esc(selected.title)}">`
                    : `<div class="wish-confirm-poster__empty"></div>`
                }
            </div>`;

        // Infos
        const score = selected.score ? `<span class="wish-confirm-score">★ ${selected.score}</span>` : '';
        const year  = selected.year  ? `<span class="wish-confirm-year">${selected.year}</span>` : '';

        confirmInfo.innerHTML = `
            <div class="wish-confirm-badges">
                <span class="wish-item__type-badge wish-item__type-badge--${esc(selected.type)}">${typeLabel(selected.type)}</span>
                ${year}${score}
            </div>
            <h2 class="wish-confirm-title">${esc(selected.title)}</h2>
            ${selected.overview ? `<p class="wish-confirm-overview">${esc(selected.overview)}</p>` : ''}
        `;

        // Scroll en haut
        window.scrollTo({ top: 0, behavior: 'smooth' });
    }

    backBtn?.addEventListener('click', () => showView('results'));

    /* ── Submit ──────────────────────────────────────────────────────────── */
    submitBtn?.addEventListener('click', async () => {
        if (!selected) return;
        submitBtn.disabled = true;
        submitBtn.textContent = 'Envoi…';

        try {
            const res  = await fetch('/wishboard/api/submit.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    title:        selected.title,
                    type:         selected.type,
                    year:         selected.year,
                    release_date: selected.release_date ?? null,
                    tmdb_id:      selected.tmdb_id,
                    cover_url:    selected.cover_url,
                    note:         noteInput.value.trim() || null,
                }),
            });
            const data = await res.json();
            if (data.ok) {
                showFeedback('success', 'Demande envoyée !');
                setTimeout(() => location.reload(), 1400);
            } else {
                showFeedback('error', data.error || 'Erreur inconnue.');
                submitBtn.disabled = false;
                submitBtn.textContent = 'Envoyer la demande';
            }
        } catch {
            showFeedback('error', 'Erreur réseau.');
            submitBtn.disabled = false;
            submitBtn.textContent = 'Envoyer la demande';
        }
    });

    function showFeedback(type, msg) {
        feedback.hidden    = false;
        feedback.className = 'wish-submit-feedback wish-submit-feedback--' + type;
        feedback.textContent = msg;
    }

    /* ── Filtre statut (tabs) + Pagination ──────────────────────────────── */
    const filterEmpty      = document.getElementById('wish-filter-empty');
    const filterEmptyTitle = document.getElementById('wish-filter-empty-title');
    const filterEmptySub   = document.getElementById('wish-filter-empty-sub');
    const FILTER_LABELS    = { all: 'Toutes', pending: 'En attente', in_progress: 'En cours', added: 'Ajoutées' };
    const PER_PAGE         = 18;

    let currentFilter = 'all';
    let currentPage   = 1;

    const allCards = () => [...document.querySelectorAll('.wish-list-card')];

    function filteredCards() {
        return allCards().filter(c => currentFilter === 'all' || c.dataset.status === currentFilter);
    }

    function renderPage() {
        const cards   = filteredCards();
        const total   = cards.length;
        const pages   = Math.ceil(total / PER_PAGE);
        currentPage   = Math.max(1, Math.min(currentPage, pages || 1));
        const start   = (currentPage - 1) * PER_PAGE;
        const end     = start + PER_PAGE;

        allCards().forEach(c => c.style.display = 'none');
        cards.forEach((c, i) => { c.style.display = (i >= start && i < end) ? '' : 'none'; });

        if (filterEmpty) {
            const isEmpty = total === 0;
            filterEmpty.style.display = isEmpty ? 'flex' : 'none';
            if (isEmpty && filterEmptyTitle) {
                filterEmptyTitle.textContent = `Aucune demande "${FILTER_LABELS[currentFilter] ?? currentFilter}"`;
                if (filterEmptySub) filterEmptySub.textContent = '';
            }
        }

        renderPagination(pages);
    }

    function renderPagination(pages) {
        let el = document.getElementById('wish-pagination');
        if (pages <= 1) { if (el) el.remove(); return; }

        if (!el) {
            el = document.createElement('div');
            el.id = 'wish-pagination';
            el.className = 'pagination';
            document.getElementById('wish-list').after(el);
        }

        const prev = currentPage > 1;
        const next = currentPage < pages;

        const pageNums = buildPageRange(currentPage, pages);

        el.innerHTML = `
            <button class="pagination__btn pagination__btn--icon${prev ? '' : ' pagination__btn--disabled'}" data-page="${currentPage - 1}" ${prev ? '' : 'disabled'}>
                <svg class="pagination__btn-icon" xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="15 18 9 12 15 6"/></svg>
            </button>
            <div class="pagination__pages">
                ${pageNums.map(p => p === '…'
                    ? `<span class="pagination__ellipsis">…</span>`
                    : `<button class="pagination__page${p === currentPage ? ' is-active' : ''}" data-page="${p}">${p}</button>`
                ).join('')}
            </div>
            <button class="pagination__btn pagination__btn--icon${next ? '' : ' pagination__btn--disabled'}" data-page="${currentPage + 1}" ${next ? '' : 'disabled'}>
                <svg class="pagination__btn-icon" xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="9 18 15 12 9 6"/></svg>
            </button>`;

        el.querySelectorAll('[data-page]').forEach(btn => {
            btn.addEventListener('click', () => {
                currentPage = parseInt(btn.dataset.page, 10);
                renderPage();
                document.getElementById('view-list').scrollIntoView({ behavior: 'smooth', block: 'start' });
            });
        });
    }

    function buildPageRange(cur, total) {
        if (total <= 7) return Array.from({ length: total }, (_, i) => i + 1);
        const pages = new Set([1, total, cur]);
        if (cur > 1) pages.add(cur - 1);
        if (cur < total) pages.add(cur + 1);
        const sorted = [...pages].sort((a, b) => a - b);
        const result = [];
        sorted.forEach((p, i) => {
            if (i > 0 && p - sorted[i - 1] > 1) result.push('…');
            result.push(p);
        });
        return result;
    }

    document.getElementById('wish-tabs')?.querySelectorAll('.fav-tab').forEach(tab => {
        tab.addEventListener('click', () => {
            document.querySelectorAll('#wish-tabs .fav-tab').forEach(t => t.classList.remove('is-active'));
            tab.classList.add('is-active');
            currentFilter = tab.dataset.filter;
            currentPage   = 1;
            renderPage();
        });
    });

    renderPage();

    // Sticky controls bar
    const controlsBar = document.getElementById('wish-controls-bar');
    if (controlsBar) {
        const sentinel = document.createElement('div');
        sentinel.style.height = '1px';
        controlsBar.before(sentinel);
        new IntersectionObserver(([e]) => {
            controlsBar.classList.toggle('is-sticky', !e.isIntersecting);
        }, { threshold: 1 }).observe(sentinel);
    }

    /* ── Helpers ─────────────────────────────────────────────────────────── */
    function typeLabel(t) {
        return { film: 'Film', anime: 'Anime', serie: 'Série' }[t] ?? t;
    }

    function esc(s) {
        return String(s ?? '').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
    }
})();

HoshimiSelect.initAll();
</script>
</body>
</html>
