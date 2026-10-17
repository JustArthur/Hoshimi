// Bouton « Déjà vu » des cards : marque tout le film / la série / l'anime comme vu
// (lecture/écriture directe de la progression locale, indépendante de storage.js)
(function () {
    const KEY = 'hoshimi_progress';

    function readProgress() {
        try {
            return JSON.parse(localStorage.getItem(KEY) ?? 'null') ?? {};
        } catch {
            return {};
        }
    }

    function writeProgress(data) {
        try {
            localStorage.setItem(KEY, JSON.stringify(data));
        } catch (e) {
            console.warn('card-seen: impossible d\'écrire', e);
        }
    }

    function keysOf(card) {
        return (card.dataset.epKeys || '').split(',').filter(Boolean);
    }

    function setState(btn, seen) {
        btn.classList.toggle('is-seen', seen);
        btn.setAttribute('aria-pressed', seen ? 'true' : 'false');
        btn.title = seen ? 'Marquer comme non vu' : 'Marquer comme vu';
    }

    function refresh(root, progress) {
        root.querySelectorAll('.card[data-ep-keys]').forEach(card => {
            const btn = card.querySelector('[data-card-seen]');
            const keys = keysOf(card);
            if (btn && keys.length) setState(btn, keys.every(k => progress[k]?.completed));
        });
    }

    // ── Watchlist (+) ───────────────────────────────────────────────────────
    const WL_KEY = 'hoshimi_watchlist';
    const ICON_PLUS  = '<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M5 12h14"/><path d="M12 5v14"/></svg>';
    const ICON_CHECK = '<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><polyline points="20 6 9 17 4 12"/></svg>';

    function readWl() {
        try {
            return JSON.parse(localStorage.getItem(WL_KEY) ?? 'null') ?? {};
        } catch {
            return {};
        }
    }

    function setWlState(btn, inWl) {
        btn.classList.toggle('is-in-wl', inWl);
        btn.setAttribute('aria-pressed', inWl ? 'true' : 'false');
        btn.title = inWl ? 'Retirer de la watchlist' : 'Ajouter à la watchlist';
        btn.innerHTML = inWl ? ICON_CHECK : ICON_PLUS;
    }

    function refreshWl(root, wl) {
        root.querySelectorAll('[data-card-wl]').forEach(btn => setWlState(btn, btn.dataset.wlSlug in wl));
    }

    function init() {
        refresh(document, readProgress());
        refreshWl(document, readWl());

        document.addEventListener('click', e => {
            const btn = e.target.closest('[data-card-wl]');
            if (!btn) return;
            e.preventDefault();
            e.stopPropagation();
            const slug = btn.dataset.wlSlug;
            let meta = {};
            try { meta = JSON.parse(btn.dataset.wlMeta || '{}'); } catch {}
            // HoshimiStorage gère le plafond de la watchlist ; repli direct sinon
            if (typeof HoshimiStorage !== 'undefined') {
                const inWl = HoshimiStorage.Watchlist.toggle(slug, meta);
                setWlState(btn, inWl);
                if (typeof showToast === 'function') showToast(inWl ? 'Ajouté à la watchlist' : 'Retiré de la watchlist');
            } else {
                const wl = readWl();
                if (slug in wl) delete wl[slug];
                else wl[slug] = { slug, added_at: new Date().toISOString(), ...meta };
                try { localStorage.setItem(WL_KEY, JSON.stringify(wl)); } catch {}
                setWlState(btn, slug in wl);
            }
        });

        // Clic sur l'œil : ne pas suivre le lien de la card
        document.addEventListener('click', e => {
            const btn = e.target.closest('[data-card-seen]');
            if (!btn) return;
            e.preventDefault();
            e.stopPropagation();
            const card = btn.closest('.card');
            const keys = keysOf(card);
            if (!keys.length) return;

            const progress = readProgress();
            const allSeen = keys.every(k => progress[k]?.completed);
            const now = new Date().toISOString();
            keys.forEach(k => {
                if (allSeen) {
                    delete progress[k];
                } else {
                    const dur = progress[k]?.duration ?? 0;
                    progress[k] = { position: dur, duration: dur, completed: true, updated_at: now };
                }
            });
            writeProgress(progress);
            refresh(document, progress);
        });

        // Cards ajoutées dynamiquement (filtres, scroll infini…)
        new MutationObserver(mutations => {
            const p = readProgress();
            const wl = readWl();
            for (const m of mutations) {
                m.addedNodes.forEach(n => {
                    if (n.nodeType !== 1) return;
                    const root = n.matches?.('.card') ? n.parentNode : n;
                    refresh(root, p);
                    refreshWl(root, wl);
                });
            }
        }).observe(document.body, { childList: true, subtree: true });
    }

    if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', init);
    else init();
})();
