<?php

declare(strict_types=1);

require_once __DIR__ . '/../../app/MacGuard.php';
MacGuard::protect();

$histFile = '/tmp/hoshimi_scan_history.json';
$history  = file_exists($histFile) ? (json_decode(file_get_contents($histFile), true) ?? []) : [];

$typeLabels = ['anime' => 'Anime', 'film' => 'Film', 'serie' => 'Série', 'unknown' => '?'];
$typeColors = ['anime' => '#6c63ff', 'film' => '#22d3ee', 'serie' => '#34d399', 'unknown' => '#888'];

$totalScans  = count($history);
$totalOk     = array_sum(array_column($history, 'ok_count'));
$totalErrors = array_sum(array_column($history, 'err_count'));
$successRate = $totalScans > 0
    ? round(count(array_filter($history, fn($e) => !($e['error'] ?? false))) / $totalScans * 100)
    : 100;

function timeAgoPhp(string $iso): string {
    $diff = time() - strtotime($iso);
    if ($diff < 60)    return 'il y a quelques secondes';
    if ($diff < 3600)  return 'il y a ' . floor($diff / 60) . ' min';
    if ($diff < 86400) return 'il y a ' . floor($diff / 3600) . ' h';
    return 'il y a ' . floor($diff / 86400) . ' j';
}
?>
<!doctype html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Logs — Hoshimi Admin</title>
    <link rel="stylesheet" href="/css/hoshimi.css">
    <link rel="stylesheet" href="/css/pages/admin.css">
    <script src="/js/storage.js"></script>
    <script src="/js/confirm.js"></script>
    <script src="/js/toast.js"></script>
</head>
<body>
    <?php include __DIR__ . '/../components/navbar.php'; ?>

    <main class="admin-page">

        <div class="admin-page__header">
        <div class="section-header section-header--simple" style="margin-bottom:0">
            <div class="section-header__bar"></div>
            <div class="section-header__inner">
                <span class="section-header__type">Activité</span>
                <h1 class="section-header__title" style="font-size:1.4rem">Logs de scan</h1>
            </div>
        </div>
            <?php include __DIR__ . '/_nav.php'; ?>
        </div>

        <div class="loader" id="loader">
            <div class="loader__ring"></div>
            <span class="loader__label">Chargement…</span>
        </div>

        <div class="adm-content" id="adm-content" hidden>
        <?php if (empty($history)): ?>
            <div class="logs-empty">
                <svg width="32" height="32" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" style="opacity:.3"><polyline points="4 17 10 11 4 5"/><line x1="12" y1="19" x2="20" y2="19"/></svg>
                <p>Aucun scan enregistré. Lance un import depuis le dashboard.</p>
            </div>
        <?php else: ?>

        <!-- Stats -->
        <div class="logs-stats">
            <div class="logs-stat">
                <span class="logs-stat__value"><?= $totalScans ?></span>
                <span class="logs-stat__label">Scans</span>
            </div>
            <div class="logs-stat logs-stat--ok">
                <span class="logs-stat__value"><?= $successRate ?>%</span>
                <span class="logs-stat__label">Succès</span>
            </div>
            <div class="logs-stat">
                <span class="logs-stat__value"><?= $totalOk ?></span>
                <span class="logs-stat__label">Fichiers traités</span>
            </div>
            <?php if ($totalErrors > 0): ?>
            <div class="logs-stat logs-stat--err">
                <span class="logs-stat__value"><?= $totalErrors ?></span>
                <span class="logs-stat__label">Erreurs</span>
            </div>
            <?php endif; ?>
            <div class="logs-stat logs-stat--action">
                <button class="logs-clear-btn" id="logs-clear">
                    <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="3 6 5 6 21 6"/><path d="M19 6l-1 14a2 2 0 0 1-2 2H8a2 2 0 0 1-2-2L5 6"/></svg>
                    Vider les logs
                </button>
            </div>
        </div>

        <!-- Filtres -->
        <?php
        $byType = [];
        foreach ($history as $e) {
            $t = $e['type'] ?? 'unknown';
            $byType[$t] = ($byType[$t] ?? 0) + 1;
        }
        ?>
        <div class="logs-filters" id="logs-filter">
            <button class="logs-filter-btn is-active" data-filter="">Tous (<?= $totalScans ?>)</button>
            <?php foreach ($byType as $type => $count): ?>
            <button class="logs-filter-btn" data-filter="<?= $type ?>"
                    style="--dot-color:<?= $typeColors[$type] ?? '#888' ?>">
                <span class="logs-filter-dot"></span>
                <?= $typeLabels[$type] ?? $type ?> (<?= $count ?>)
            </button>
            <?php endforeach; ?>
        </div>

        <!-- Timeline -->
        <div class="logs-timeline" id="logs-list">
            <?php foreach ($history as $i => $entry):
                $type  = $entry['type']  ?? 'unknown';
                $color = $typeColors[$type] ?? '#888';
                $label = $typeLabels[$type] ?? $type;
                $ok    = !($entry['error'] ?? false);
                $date  = $entry['date'] ?? '';
            ?>
            <div class="logs-entry" data-type="<?= $type ?>">
                <div class="logs-entry__track" style="--track-color:<?= $color ?>">
                    <span class="logs-entry__dot" style="background:<?= $color ?>;color:<?= $color ?>"></span>
                    <?php if ($i < $totalScans - 1): ?>
                        <span class="logs-entry__line"></span>
                    <?php endif; ?>
                </div>
                <div class="logs-entry__card">
                    <div class="logs-entry__row">
                        <span class="logs-entry__type" style="background:<?= $color ?>1a;color:<?= $color ?>"><?= $label ?></span>
                        <span class="logs-entry__status <?= $ok ? 'logs-entry__status--ok' : 'logs-entry__status--err' ?>">
                            <?php if ($ok): ?>
                                <svg width="11" height="11" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"/></svg> Succès
                            <?php else: ?>
                                <svg width="11" height="11" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg> Erreur
                            <?php endif; ?>
                        </span>
                        <div class="logs-entry__chips">
                            <?php if (($entry['ok_count'] ?? 0) > 0): ?>
                                <span class="logs-entry__chip logs-entry__chip--ok"><?= $entry['ok_count'] ?> OK</span>
                            <?php endif; ?>
                            <?php if (($entry['err_count'] ?? 0) > 0): ?>
                                <span class="logs-entry__chip logs-entry__chip--err"><?= $entry['err_count'] ?> ERR</span>
                            <?php endif; ?>
                            <span class="logs-entry__chip"><?= $entry['lines'] ?? 0 ?> lignes</span>
                        </div>
                        <div class="logs-entry__date">
                            <span class="logs-entry__ago"><?= $date ? timeAgoPhp($date) : '—' ?></span>
                            <?php if ($date): ?>
                                <span class="logs-entry__full"><?= date('d/m/Y · H:i', strtotime($date)) ?></span>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
        </div>

        <?php endif; ?>

    </main>

    <script>
    const filterBar = document.getElementById('logs-filter');
    if (filterBar) {
        filterBar.addEventListener('click', e => {
            const btn = e.target.closest('.logs-filter-btn');
            if (!btn) return;
            filterBar.querySelectorAll('.logs-filter-btn').forEach(b => b.classList.remove('is-active'));
            btn.classList.add('is-active');
            const f = btn.dataset.filter;
            document.querySelectorAll('.logs-entry').forEach(row => {
                row.style.display = (!f || row.dataset.type === f) ? '' : 'none';
            });
        });
    }

    const clearBtn = document.getElementById('logs-clear');
    if (clearBtn) {
        clearBtn.addEventListener('click', async () => {
            if (!await HoshimiConfirm.ask('Vider tout l\'historique des logs ?', { confirm: 'Vider', type: 'danger' })) return;
            clearBtn.disabled = true;
            const res = await fetch('/admin/api/clear-logs.php', { method: 'POST' })
                .then(r => r.json()).catch(() => ({ ok: false }));
            if (res.ok) location.reload();
            else clearBtn.disabled = false;
        });
    }
    </script>
    <script>(function(){const l=document.getElementById('loader'),c=document.getElementById('adm-content');if(c)c.hidden=false;if(l)l.remove();})();</script>
        </div><!-- #adm-content -->
</body>
</html>
