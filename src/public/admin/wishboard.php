<?php

declare(strict_types=1);

require_once __DIR__ . '/../../app/MacGuard.php';
MacGuard::protect();

$current_path = $_SERVER['REQUEST_URI'];

$dataFile = __DIR__ . '/../../data/wishboard.json';
$wishes   = file_exists($dataFile)
    ? (json_decode(file_get_contents($dataFile), true) ?: [])
    : [];

$TYPE_ROOTS = [
    'anime' => rtrim($_ENV['ANIMES_PATH'] ?? getenv('ANIMES_PATH') ?: '/media/animes', '/\\'),
    'film'  => rtrim($_ENV['FILMS_PATH']  ?? getenv('FILMS_PATH')  ?: '/media/films',  '/\\'),
    'serie' => rtrim($_ENV['SERIES_PATH'] ?? getenv('SERIES_PATH') ?: '/media/series', '/\\'),
];

$VIDEO_EXTS = ['mkv','mp4','avi','mov','wmv','m4v','webm'];

function effectiveStatus(array $w, array $roots, array $videoExts): string {
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

function isTooRecent(array $w): bool {
    $d = $w['release_date'] ?? null;
    return $d && (time() - (int)strtotime($d)) < 30 * 86400;
}

foreach ($wishes as &$w) {
    $w['_status'] = effectiveStatus($w, $TYPE_ROOTS, $VIDEO_EXTS);
    $w['_recent'] = isTooRecent($w);
}
unset($w);

usort($wishes, function($a, $b) {
    $order = ['pending' => 0, 'in_progress' => 1, 'added' => 2];
    $oa = $order[$a['_status']] ?? 9;
    $ob = $order[$b['_status']] ?? 9;
    if ($oa !== $ob) return $oa <=> $ob;
    return strcmp($b['created_at'], $a['created_at']);
});

$pending    = array_filter($wishes, fn($w) => $w['_status'] === 'pending');
$inProgress = array_filter($wishes, fn($w) => $w['_status'] === 'in_progress');
$added      = array_filter($wishes, fn($w) => $w['_status'] === 'added');

$perPage    = 20;
$total      = count($wishes);
$totalPages = max(1, (int)ceil($total / $perPage));
$page       = max(1, min($totalPages, (int)($_GET['page'] ?? 1)));
$pageWishes = array_slice($wishes, ($page - 1) * $perPage, $perPage);
?>
<!doctype html>
<html lang="fr">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Wishboard — Hoshimi Admin</title>
<link rel="stylesheet" href="/css/hoshimi.css">
<link rel="stylesheet" href="/css/pages/admin.css">
</head>
<body>

<?php include __DIR__ . '/../components/navbar.php'; ?>

<main class="admin-page">
    <div class="admin-page__header">
        <div class="section-header section-header--simple" style="margin-bottom:0">
            <div class="section-header__bar"></div>
            <div class="section-header__inner">
                <span class="section-header__type">Admin</span>
                <h1 class="section-header__title" style="font-size:1.4rem">Wishboard <span class="adm-wish-badge"><?= count($pending) ?> en attente</span></h1>
            </div>
        </div>
        <?php include __DIR__ . '/_nav.php'; ?>
    </div>

    <!-- ── Stats ─────────────────────────────────────────────────────────── -->
    <div class="adm-wish-stats">
        <div class="adm-wish-stat">
            <span class="adm-wish-stat__n"><?= count($wishes) ?></span>
            <span class="adm-wish-stat__l">Total</span>
        </div>
        <div class="adm-wish-stat adm-wish-stat--pending">
            <span class="adm-wish-stat__n"><?= count($pending) ?></span>
            <span class="adm-wish-stat__l">En attente</span>
        </div>
        <div class="adm-wish-stat adm-wish-stat--in-progress">
            <span class="adm-wish-stat__n"><?= count($inProgress) ?></span>
            <span class="adm-wish-stat__l">En cours</span>
        </div>
        <div class="adm-wish-stat adm-wish-stat--added">
            <span class="adm-wish-stat__n"><?= count($added) ?></span>
            <span class="adm-wish-stat__l">Ajoutées</span>
        </div>
    </div>

    <?php if (empty($wishes)): ?>
    <div class="adm-wish-empty">Aucune demande pour l'instant.</div>
    <?php else: ?>

    <!-- ── Table ─────────────────────────────────────────────────────────── -->
    <div class="adm-wish-table-wrap">
        <table class="adm-wish-table">
            <thead>
                <tr>
                    <th></th>
                    <th>Titre</th>
                    <th>Type</th>
                    <th>Année</th>
                    <th>Note</th>
                    <th>Demandé le</th>
                    <th>Statut</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($pageWishes as $w):
                $typeLabel   = match($w['type'] ?? 'film') { 'anime' => 'Anime', 'serie' => 'Série', default => 'Film' };
                $effStatus   = $w['_status'];
                $statusLabel = match($effStatus) { 'in_progress' => 'En cours', 'added' => 'Ajouté', default => 'En attente' };
                $date        = date('d/m/Y H:i', strtotime($w['created_at']));
            ?>
            <tr class="adm-wish-row adm-wish-row--<?= $effStatus ?>" data-id="<?= htmlspecialchars($w['id']) ?>">
                <td class="adm-wish-cover-cell">
                    <?php if (!empty($w['cover_url'])): ?>
                    <img class="adm-wish-cover" src="<?= htmlspecialchars($w['cover_url']) ?>" alt="" loading="lazy">
                    <?php else: ?>
                    <div class="adm-wish-cover adm-wish-cover--empty"></div>
                    <?php endif; ?>
                </td>
                <td class="adm-wish-title">
                    <?= htmlspecialchars($w['title']) ?>
                    <?php if ($w['_recent'] && ($w['type'] ?? 'film') === 'film'): ?><span class="wish-item__status wish-item__status--recent" style="margin-left:.4rem;">Trop récent</span><?php endif; ?>
                    <?php if (!empty($w['folder'])): ?>
                    <span class="adm-wish-folder">→ <?= htmlspecialchars($w['folder']) ?></span>
                    <?php endif; ?>
                </td>
                <td><span class="wish-item__type-badge wish-item__type-badge--<?= htmlspecialchars($w['type'] ?? 'film') ?>"><?= $typeLabel ?></span></td>
                <td><?= $w['year'] ? (int)$w['year'] : '—' ?></td>
                <td class="adm-wish-note"><?= $w['note'] ? htmlspecialchars($w['note']) : '<span class="adm-wish-empty-cell">—</span>' ?></td>
                <td class="adm-wish-date"><?= $date ?></td>
                <td>
                    <span class="wish-item__status wish-item__status--<?= $effStatus ?>">
                        <?= $statusLabel ?>
                    </span>
                </td>
                <td class="adm-wish-actions">
                    <?php if ($effStatus === 'pending'): ?>
                    <button class="btn btn--accept" data-action="accept" data-id="<?= htmlspecialchars($w['id']) ?>" title="Accepter"><svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"/></svg></button>
                    <?php endif; ?>
                </td>
            </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>

    <?php if ($totalPages > 1): ?>
    <div class="adm-wish-pagination">
        <?php if ($page > 1): ?>
        <a class="adm-pg-btn" href="?page=<?= $page - 1 ?>">
            <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><polyline points="15 18 9 12 15 6"/></svg>
        </a>
        <?php else: ?>
        <span class="adm-pg-btn is-disabled"><svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><polyline points="15 18 9 12 15 6"/></svg></span>
        <?php endif; ?>

        <?php
        $range = 2;
        $start = max(1, $page - $range);
        $end   = min($totalPages, $page + $range);
        if ($start > 1): ?><a class="adm-pg-btn" href="?page=1">1</a><?php if ($start > 2): ?><span class="adm-pg-ellipsis">…</span><?php endif; endif; ?>
        <?php for ($i = $start; $i <= $end; $i++): ?>
        <?php if ($i === $page): ?>
        <span class="adm-pg-btn is-active"><?= $i ?></span>
        <?php else: ?>
        <a class="adm-pg-btn" href="?page=<?= $i ?>"><?= $i ?></a>
        <?php endif; ?>
        <?php endfor; ?>
        <?php if ($end < $totalPages): ?><?php if ($end < $totalPages - 1): ?><span class="adm-pg-ellipsis">…</span><?php endif; ?><a class="adm-pg-btn" href="?page=<?= $totalPages ?>"><?= $totalPages ?></a><?php endif; ?>

        <?php if ($page < $totalPages): ?>
        <a class="adm-pg-btn" href="?page=<?= $page + 1 ?>">
            <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><polyline points="9 18 15 12 9 6"/></svg>
        </a>
        <?php else: ?>
        <span class="adm-pg-btn is-disabled"><svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><polyline points="9 18 15 12 9 6"/></svg></span>
        <?php endif; ?>

        <span class="adm-pg-info"><?= (($page-1)*$perPage)+1 ?>–<?= min($page*$perPage, $total) ?> / <?= $total ?></span>
    </div>
    <?php endif; ?>

    <?php endif; ?>

</main>

<script src="/js/storage.js"></script>
<script src="/js/toast.js"></script>
<script src="/js/confirm.js"></script>
<script>
(function () {
    document.querySelectorAll('[data-action]').forEach(btn => {
        btn.addEventListener('click', async () => {
            const action = btn.dataset.action;
            const id     = btn.dataset.id;

            if (action === 'accept') {
                if (!await HoshimiConfirm.ask('Accepter et créer le dossier sur le serveur ?', { confirm: 'Accepter' })) return;
            }

            btn.disabled = true;
            const row = btn.closest('tr');

            try {
                const res  = await fetch('/admin/api/wish-action.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ action, id }),
                });
                const data = await res.json();
                if (data.ok) {
                    if (action === 'delete') {
                        row.style.opacity = '0';
                        row.style.transition = 'opacity .3s';
                        setTimeout(() => row.remove(), 300);
                    } else {
                        location.reload();
                    }
                } else {
                    HoshimiToast.error(data.error || 'Erreur inconnue.');
                    btn.disabled = false;
                }
            } catch {
                HoshimiToast.error('Erreur réseau.');
                btn.disabled = false;
            }
        });
    });
})();
</script>
</body>
</html>
