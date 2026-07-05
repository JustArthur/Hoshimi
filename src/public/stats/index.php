<?php

declare(strict_types=1);

$current_path = '/stats';
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sauvegarde — Hoshimi</title>
    <link rel="stylesheet" href="/css/hoshimi.css">
</head>

<body>
    <?php include __DIR__ . '/../components/navbar.php'; ?>

    <main>
        <div class="container" style="padding-top: 40px; padding-bottom: 60px;">

            <div class="section-header" style="margin-bottom: 32px;">
                <h1 class="section-header__title">Sauvegarde &amp; transfert</h1>
            </div>

            <p style="color: var(--color-text-muted); font-size: 0.9rem; margin-bottom: 32px; max-width: 540px;">
                Exportez votre progression, favoris et listes pour les retrouver sur un autre navigateur ou appareil.
            </p>

            <div class="backup-actions">
                <button class="btn btn--secondary" id="export-btn">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="7 10 12 15 17 10"/><line x1="12" y1="15" x2="12" y2="3"/></svg>
                    Exporter (.json)
                </button>
                <button class="btn btn--secondary" id="import-btn">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="17 8 12 3 7 8"/><line x1="12" y1="3" x2="12" y2="15"/></svg>
                    Importer
                </button>
                <button class="btn btn--secondary" id="copy-link-btn">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="9" y="9" width="13" height="13" rx="2" ry="2"/><path d="M5 15H4a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2h9a2 2 0 0 1 2 2v1"/></svg>
                    Copier le lien
                </button>
                <button class="btn btn--secondary" id="qr-btn">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="3" width="7" height="7"/><rect x="14" y="3" width="7" height="7"/><rect x="3" y="14" width="7" height="7"/><rect x="5" y="5" width="3" height="3" fill="currentColor" stroke="none"/><rect x="16" y="5" width="3" height="3" fill="currentColor" stroke="none"/><rect x="5" y="16" width="3" height="3" fill="currentColor" stroke="none"/><line x1="14" y1="14" x2="14" y2="14"/><line x1="17" y1="14" x2="21" y2="14"/><line x1="21" y1="17" x2="21" y2="21"/><line x1="14" y1="17" x2="17" y2="17"/><line x1="14" y1="21" x2="17" y2="21"/></svg>
                    QR Code
                </button>
                <input type="file" id="import-file" accept=".json" style="display:none">

                <div id="qr-modal" class="qr-modal-overlay" style="display:none;">
                    <div class="qr-modal">
                        <button class="qr-modal__close" id="qr-modal-close">✕</button>
                        <h3 class="qr-modal__title">Scanner sur mobile</h3>
                        <p class="qr-modal__sub">Scannez ce code avec votre téléphone pour importer votre progression. Valable <strong>5 minutes</strong>.</p>
                        <div id="qr-container" class="qr-modal__canvas"></div>
                        <p class="qr-modal__url" id="qr-url"></p>
                        <p class="qr-modal__loading" id="qr-loading">Génération en cours…</p>
                    </div>
                </div>
            </div>

        </div>
    </main>

    <?php include __DIR__ . '/../components/footer.php'; ?>

    <script src="/js/hoshimi_storage.js"></script>
    <script>
        const BACKUP_KEYS = ['hoshimi_progress', 'hoshimi_favorites', 'hoshimi_lists', 'hoshimi_watch_status'];

        document.getElementById('export-btn').addEventListener('click', () => {
            const data = {};
            BACKUP_KEYS.forEach(k => { const v = localStorage.getItem(k); if (v) data[k] = JSON.parse(v); });
            const blob = new Blob([JSON.stringify(data, null, 2)], { type: 'application/json' });
            const url  = URL.createObjectURL(blob);
            const a    = document.createElement('a');
            a.href = url; a.download = `hoshimi-backup-${new Date().toISOString().slice(0, 10)}.json`;
            a.click(); URL.revokeObjectURL(url);
            showToast('Sauvegarde exportée ✓');
        });

        document.getElementById('import-btn').addEventListener('click', () => document.getElementById('import-file').click());

        document.getElementById('import-file').addEventListener('change', e => {
            const file = e.target.files[0]; if (!file) return;
            const reader = new FileReader();
            reader.onload = ev => {
                try {
                    const data = JSON.parse(ev.target.result);
                    let count = 0;
                    BACKUP_KEYS.forEach(k => { if (data[k] !== undefined) { localStorage.setItem(k, JSON.stringify(data[k])); count++; } });
                    showToast(`${count} clé(s) importée(s) ✓`);
                    setTimeout(() => location.reload(), 1200);
                } catch { showToast('Fichier invalide — import annulé'); }
            };
            reader.readAsText(file);
            e.target.value = '';
        });

        document.getElementById('copy-link-btn').addEventListener('click', async () => {
            const data = {};
            BACKUP_KEYS.forEach(k => { const v = localStorage.getItem(k); if (v) data[k] = JSON.parse(v); });
            const json = JSON.stringify(data);
            let encoded;
            try {
                const cs = new CompressionStream('deflate-raw');
                const writer = cs.writable.getWriter();
                writer.write(new TextEncoder().encode(json)); writer.close();
                const buf = await new Response(cs.readable).arrayBuffer();
                encoded = btoa(String.fromCharCode(...new Uint8Array(buf)));
            } catch { encoded = btoa(unescape(encodeURIComponent(json))); }
            if (encoded.length > 200000) { showToast('Données trop volumineuses — utilisez l\'export JSON'); return; }
            const url = `${location.origin}/stats/?restore=${encodeURIComponent(encoded)}`;
            await navigator.clipboard.writeText(url);
            showToast('Lien copié dans le presse-papier ✓');
        });

        (async function restoreFromLink() {
            const encoded = new URLSearchParams(location.search).get('restore');
            if (!encoded) return;
            try {
                let json;
                try {
                    const bytes = Uint8Array.from(atob(decodeURIComponent(encoded)), c => c.charCodeAt(0));
                    const ds = new DecompressionStream('deflate-raw');
                    const writer = ds.writable.getWriter(); writer.write(bytes); writer.close();
                    json = await new Response(ds.readable).text();
                } catch { json = decodeURIComponent(escape(atob(decodeURIComponent(encoded)))); }
                const data = JSON.parse(json);
                if (confirm('Importer la sauvegarde depuis ce lien ? Cela écrasera vos données actuelles.')) {
                    BACKUP_KEYS.forEach(k => { if (data[k] !== undefined) localStorage.setItem(k, JSON.stringify(data[k])); });
                    history.replaceState(null, '', location.origin + location.pathname);
                    showToast('Données restaurées ✓');
                    setTimeout(() => location.reload(), 1200);
                } else { history.replaceState(null, '', location.origin + location.pathname); }
            } catch { showToast('Lien invalide ou corrompu'); }
        })();

        (async function restoreFromToken() {
            const token = new URLSearchParams(location.search).get('restore_token');
            if (!token) return;
            history.replaceState(null, '', location.origin + location.pathname);
            try {
                const resp = await fetch(`/api/backup/?token=${encodeURIComponent(token)}`);
                if (!resp.ok) throw new Error('Token introuvable ou expiré');
                const data = await resp.json();
                if (confirm('Importer la sauvegarde depuis le QR code ? Cela écrasera vos données actuelles.')) {
                    BACKUP_KEYS.forEach(k => { if (data[k] !== undefined) localStorage.setItem(k, JSON.stringify(data[k])); });
                    showToast('Données restaurées ✓');
                    setTimeout(() => location.reload(), 1200);
                }
            } catch (err) { showToast(err.message ?? 'QR code invalide ou expiré'); }
        })();

        document.getElementById('qr-btn').addEventListener('click', async () => {
            const modal = document.getElementById('qr-modal');
            const loading = document.getElementById('qr-loading');
            const qrUrl = document.getElementById('qr-url');
            const container = document.getElementById('qr-container');
            container.innerHTML = ''; loading.style.display = 'block'; qrUrl.textContent = ''; modal.style.display = 'flex';
            try {
                const data = {};
                BACKUP_KEYS.forEach(k => { const v = localStorage.getItem(k); if (v) data[k] = JSON.parse(v); });
                const resp = await fetch('/api/backup/', { method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify(data) });
                if (!resp.ok) throw new Error('Erreur serveur');
                const { token } = await resp.json();
                const url = `${location.origin}/stats/?restore_token=${token}`;
                qrUrl.textContent = url;
                const qr = qrcode(0, 'M'); qr.addData(url); qr.make();
                const img = document.createElement('img');
                img.src = qr.createDataURL(5, 8); img.alt = 'QR Code';
                img.style.cssText = 'width:220px;height:220px;border-radius:8px;';
                container.appendChild(img); loading.style.display = 'none';
            } catch (err) { loading.textContent = 'Erreur : ' + (err.message ?? 'impossible de générer le QR code'); }
        });

        document.getElementById('qr-modal-close').addEventListener('click', () => { document.getElementById('qr-modal').style.display = 'none'; });
        document.getElementById('qr-modal').addEventListener('click', e => { if (e.target === e.currentTarget) e.currentTarget.style.display = 'none'; });
    </script>
    <script src="https://cdn.jsdelivr.net/npm/qrcode-generator@1.4.4/qrcode.js"></script>
</body>

</html>
