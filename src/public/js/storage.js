// ============================================================
//  HOSHIMI — Storage Manager
//  Gère via localStorage :
//    - Progression de visionnage par épisode
//    - Favoris (par slug anime/serie/film)
// ============================================================

// JS mirror of icon.php's lc() — used in inline scripts
window.lc = (function () {
    const P = {
        'arrow-right':    '<path d="M5 12h14"/><path d="m12 5 7 7-7 7"/>',
        'arrow-left':     '<path d="m12 19-7-7 7-7"/><path d="M19 12H5"/>',
        'chevron-right':  '<path d="m9 18 6-6-6-6"/>',
        'chevron-left':   '<path d="m15 18-6-6 6-6"/>',
        'external-link':  '<path d="M18 13v6a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h6"/><polyline points="15 3 21 3 21 9"/><line x1="10" x2="21" y1="14" y2="3"/>',
        'x':              '<path d="M18 6 6 18"/><path d="m6 6 12 12"/>',
        'check':          '<polyline points="20 6 9 17 4 12"/>',
        'refresh-cw':     '<path d="M3 12a9 9 0 0 1 9-9 9.75 9.75 0 0 1 6.74 2.74L21 8"/><path d="M21 3v5h-5"/><path d="M21 12a9 9 0 0 1-9 9 9.75 9.75 0 0 1-6.74-2.74L3 16"/><path d="M8 16H3v5"/>',
        'search':         '<circle cx="11" cy="11" r="8"/><path d="m21 21-4.3-4.3"/>',
        'search-x':       '<path d="m13.5 8.5-5 5"/><path d="m8.5 8.5 5 5"/><circle cx="11" cy="11" r="8"/><path d="m21 21-4.3-4.3"/>',
        'play':           '<path d="M5 5a2 2 0 0 1 3.008-1.728l11.997 6.998a2 2 0 0 1 .003 3.458l-12 7A2 2 0 0 1 5 19z"/>',
        'star':           '<polygon points="12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2" fill="currentColor" stroke="currentColor"/>',
        'calendar':       '<path d="M8 2v4"/><path d="M16 2v4"/><rect width="18" height="18" x="3" y="4" rx="2"/><path d="M3 10h18"/>',
        'clock':          '<circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/>',
        'history':        '<path d="M3 12a9 9 0 1 0 9-9 9.75 9.75 0 0 0-6.74 2.74L3 8"/><path d="M3 3v5h5"/><path d="M12 7v5l4 2"/>',
        'list':           '<line x1="8" x2="21" y1="6" y2="6"/><line x1="8" x2="21" y1="12" y2="12"/><line x1="8" x2="21" y1="18" y2="18"/><line x1="3" x2="3.01" y1="6" y2="6"/><line x1="3" x2="3.01" y1="12" y2="12"/><line x1="3" x2="3.01" y1="18" y2="18"/>',
        'clapperboard':   '<path d="M20.2 6 3 11l-.9-2.4c-.3-1.1.3-2.2 1.3-2.5l13.5-4c1.1-.3 2.2.3 2.5 1.3Z"/><path d="m6.2 5.3 3.1 3.9"/><path d="m12.4 3.4 3.1 3.9"/><path d="M3 11h18v8a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2Z"/>',
        'tv':             '<rect width="20" height="15" x="2" y="7" rx="2" ry="2"/><polyline points="17 2 12 7 7 2"/>',
        'tv-2':           '<path d="M7 21h10"/><rect width="20" height="14" x="2" y="3" rx="2"/>',
        'sparkles':       '<path d="m12 3-1.912 5.813a2 2 0 0 1-1.275 1.275L3 12l5.813 1.912a2 2 0 0 1 1.275 1.275L12 21l1.912-5.813a2 2 0 0 1 1.275-1.275L21 12l-5.813-1.912a2 2 0 0 1-1.275-1.275L12 3Z"/><path d="M5 3v4"/><path d="M19 17v4"/><path d="M3 5h4"/><path d="M17 19h4"/>',
        'plus':           '<path d="M5 12h14"/><path d="M12 5v14"/>',
        'eye':'<path d="M2.062 12.348a1 1 0 0 1 0-.696 10.75 10.75 0 0 1 19.876 0 1 1 0 0 1 0 .696 10.75 10.75 0 0 1-19.876 0"/><circle cx="12" cy="12" r="3"/>',
        'eye-off':'<path d="M9.88 9.88a3 3 0 1 0 4.24 4.24"/><path d="M10.73 5.08A10.43 10.43 0 0 1 12 5c7 0 10 7 10 7a13.16 13.16 0 0 1-1.67 2.68"/><path d="M6.61 6.61A13.526 13.526 0 0 0 2 12s3 7 10 7a9.74 9.74 0 0 0 5.39-1.61"/><line x1="2" x2="22" y1="2" y2="22"/>',
        'user':           '<path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/>',
        'heart':          '<path d="M19 14c1.49-1.46 3-3.21 3-5.5A5.5 5.5 0 0 0 16.5 3c-1.76 0-3 .5-4.5 2-1.5-1.5-2.74-2-4.5-2A5.5 5.5 0 0 0 2 8.5c0 2.3 1.5 4.05 3 5.5l7 7Z"/>',
        'bookmark':       '<path d="m19 21-7-4-7 4V5a2 2 0 0 1 2-2h10a2 2 0 0 1 2 2v16z"/>',
        'alert-triangle': '<path d="m21.73 18-8-14a2 2 0 0 0-3.48 0l-8 14A2 2 0 0 0 4 21h16a2 2 0 0 0 1.73-3Z"/><line x1="12" x2="12" y1="9" y2="13"/><line x1="12" x2="12.01" y1="17" y2="17"/>',
        'inbox':          '<polyline points="22 12 16 12 14 15 10 15 8 12 2 12"/><path d="M5.45 5.11 2 12v6a2 2 0 0 0 2 2h16a2 2 0 0 0 2-2v-6l-3.45-6.89A2 2 0 0 0 16.76 4H7.24a2 2 0 0 0-1.79 1.11z"/>',
        'package-open':   '<path d="M20.91 8.84 8.56 2.23a1.93 1.93 0 0 0-1.81 0L3.1 4.13a2.12 2.12 0 0 0-.05 3.69l12.22 6.93a2 2 0 0 0 1.94 0L21 12.51a2.12 2.12 0 0 0-.09-3.67Z"/><path d="m3.09 8.84 12.35-6.61a1.93 1.93 0 0 1 1.81 0l3.65 1.9a2.12 2.12 0 0 1 .1 3.69L8.73 14.75a2 2 0 0 1-1.94 0L3 12.51a2.12 2.12 0 0 1 .09-3.67Z"/><line x1="12" x2="12" y1="22" y2="13"/><path d="M20 13.5v3.37a2.06 2.06 0 0 1-1.11 1.83l-6 3.08a1.93 1.93 0 0 1-1.78 0l-6-3.08A2.06 2.06 0 0 1 4 16.87V13.5"/>',
    };
    return function lc(name, size) {
        size = size || 16;
        const inner = P[name] || '';
        const fill  = (name === 'star' || name === 'play') ? 'currentColor' : 'none';
        const sw    = name === 'star' ? '1' : '2';
        return '<svg xmlns="http://www.w3.org/2000/svg" width="' + size + '" height="' + size + '" viewBox="0 0 24 24" fill="' + fill + '" stroke="currentColor" stroke-width="' + sw + '" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">' + inner + '</svg>';
    };
})();

const HoshimiStorage = (() => {

    function escapeHtml(str) {
        return String(str)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#39;');
    }

    const KEYS = {
        progress:  'hoshimi_progress',
        favorites: 'hoshimi_favorites',
        watchlist: 'hoshimi_watchlist',
    };

    // ----------------------------------------------------------------
    //  Helpers lecture / écriture
    // ----------------------------------------------------------------
    function read(key) {
        try {
            return JSON.parse(localStorage.getItem(key) ?? 'null') ?? {};
        } catch {
            return {};
        }
    }

    function write(key, data) {
        try {
            localStorage.setItem(key, JSON.stringify(data));
        } catch (e) {
            console.warn('HoshimiStorage: impossible d\'écrire', e);
        }
    }

    // ================================================================
    //  PROGRESSION
    //  Clé : md5 du chemin fichier (généré côté PHP, passé en data-*)
    // ================================================================
    const Progress = {

        // Sauvegarde la progression d'un épisode (max 2000 entrées — purge les plus anciennes)
        save(fileKey, position, duration, completed = false) {
            const all = read(KEYS.progress);
            const isCompleted = completed || (duration > 0 && position / duration >= 0.92);
            all[fileKey] = {
                position: Math.floor(position),
                duration: Math.floor(duration),
                completed: isCompleted,
                updated_at: new Date().toISOString(),
            };
            const entries = Object.entries(all);
            if (entries.length > 2000) {
                entries.sort((a, b) => (a[1].updated_at ?? '') < (b[1].updated_at ?? '') ? -1 : 1);
                const trimmed = Object.fromEntries(entries.slice(-2000));
                write(KEYS.progress, trimmed);
            } else {
                write(KEYS.progress, all);
            }
        },

        // Marque une liste d'épisodes comme vus (ou annule : supprime leur progression)
        setSeen(fileKeys, seen) {
            const all = read(KEYS.progress);
            const now = new Date().toISOString();
            fileKeys.forEach(k => {
                if (!seen) { delete all[k]; return; }
                const dur = all[k]?.duration ?? 0;
                all[k] = { position: dur, duration: dur, completed: true, updated_at: now };
            });
            write(KEYS.progress, all);
        },

        // Récupère la progression d'un épisode
        get(fileKey) {
            const all = read(KEYS.progress);
            return all[fileKey] ?? { position: 0, duration: 0, completed: false };
        },

        // Calcule le % de progression d'un anime complet
        // episodes = tableau de { file_key, ... }
        getAnimeProgress(episodes) {
            if (!episodes || episodes.length === 0) return 0;
            const all = read(KEYS.progress);
            let completed = 0;
            let inProgress = 0;

            episodes.forEach(ep => {
                const p = all[ep.file_key];
                if (!p) return;
                if (p.completed) {
                    completed++;
                } else if (p.position > 10) {
                    inProgress++;
                }
            });

            // % basé sur épisodes complétés + partiel pour en cours
            return Math.round(((completed + inProgress * 0.5) / episodes.length) * 100);
        },

        // Retourne le dernier épisode (ou film) en cours pour un anime
        getLastEpisode(episodes) {
            if (!episodes || episodes.length === 0) return null;
            const all = read(KEYS.progress);
            let last = null;

            episodes.forEach(ep => {
                const p = all[ep.file_key];
                if (!p || p.completed || p.position <= 3) return;
                if (!last || new Date(p.updated_at) > new Date(last.updated_at)) {
                    last = { ...ep, ...p };
                }
            });

            return last;
        },

        // Retourne l'épisode à proposer : en cours > prochain non vu après le dernier complété
        getResumeEpisode(episodes) {
            if (!episodes || episodes.length === 0) return null;
            const all = read(KEYS.progress);

            // Chercher un épisode en cours (non complété, > 10s)
            let inProgress = null;
            episodes.forEach(ep => {
                const p = all[ep.file_key];
                if (!p || p.completed || p.position <= 3) return;
                if (!inProgress || new Date(p.updated_at) > new Date(inProgress.updated_at)) {
                    inProgress = { ...ep, ...p, isNext: false };
                }
            });
            if (inProgress) return inProgress;

            // Sinon : trouver le dernier épisode complété, proposer le suivant
            let lastCompleted = null;
            let lastCompletedIdx = -1;
            episodes.forEach((ep, idx) => {
                const p = all[ep.file_key];
                if (!p || !p.completed) return;
                if (!lastCompleted || new Date(p.updated_at) > new Date(lastCompleted.updated_at)) {
                    lastCompleted = { ...ep, ...p };
                    lastCompletedIdx = idx;
                }
            });

            if (lastCompletedIdx >= 0 && lastCompletedIdx < episodes.length - 1) {
                const nextEp = episodes[lastCompletedIdx + 1];
                const p = all[nextEp.file_key] ?? { position: 0, duration: 0, completed: false };
                return { ...nextEp, ...p, position: p.position || 0, updated_at: lastCompleted.updated_at, isNext: true };
            }

            return null;
        },

        // Supprime la progression d'un épisode
        remove(fileKey) {
            const all = read(KEYS.progress);
            delete all[fileKey];
            write(KEYS.progress, all);
        },

        // Retourne tous les animes avec progression (pour page d'accueil)
        getAllProgress() {
            return read(KEYS.progress);
        },
    };

    // ================================================================
    //  FAVORIS
    //  Clé : slug de l'anime
    // ================================================================
    const Favorites = {

        add(slug, meta = {}) {
            const all = read(KEYS.favorites);
            all[slug] = { slug, added_at: new Date().toISOString(), ...meta };
            write(KEYS.favorites, all);
        },

        remove(slug) {
            const all = read(KEYS.favorites);
            delete all[slug];
            write(KEYS.favorites, all);
        },

        toggle(slug, meta = {}) {
            this.isFavorite(slug) ? this.remove(slug) : this.add(slug, meta);
            return this.isFavorite(slug);
        },

        isFavorite(slug) {
            return slug in read(KEYS.favorites);
        },

        getAll() {
            return Object.values(read(KEYS.favorites));
        },
    };

    // ================================================================
    //  LISTES PERSONNALISÉES — SUPPRIMÉ
    //  (conservé vide pour rétro-compat backup)
    //  Structure : { list_id: { id, name, items: [slug, ...] } }
    // ================================================================
    const Lists = {

        // Crée une nouvelle liste
        create(name) {
            const all = read(KEYS.lists);
            const id = 'list_' + Date.now();
            all[id] = { id, name, created_at: new Date().toISOString(), items: [] };
            write(KEYS.lists, all);
            return id;
        },

        // Supprime une liste
        delete(listId) {
            const all = read(KEYS.lists);
            delete all[listId];
            write(KEYS.lists, all);
        },

        // Renomme une liste
        rename(listId, name) {
            const all = read(KEYS.lists);
            if (all[listId]) {
                all[listId].name = name;
                write(KEYS.lists, all);
            }
        },

        // Ajoute un anime à une liste
        addItem(listId, slug, meta = {}) {
            const all = read(KEYS.lists);
            if (!all[listId]) return;
            if (!all[listId].items.find(i => i.slug === slug)) {
                all[listId].items.push({ slug, added_at: new Date().toISOString(), ...meta });
                write(KEYS.lists, all);
            }
        },

        // Retire un anime d'une liste
        removeItem(listId, slug) {
            const all = read(KEYS.lists);
            if (!all[listId]) return;
            all[listId].items = all[listId].items.filter(i => i.slug !== slug);
            write(KEYS.lists, all);
        },

        // Vérifie si un anime est dans une liste
        hasItem(listId, slug) {
            const all = read(KEYS.lists);
            return all[listId]?.items?.some(i => i.slug === slug) ?? false;
        },

        // Retourne toutes les listes où un anime apparaît
        getListsForAnime(slug) {
            return Object.values(read(KEYS.lists)).filter(l =>
                l.items?.some(i => i.slug === slug)
            );
        },

        // Retourne toutes les listes
        getAll() {
            return Object.values(read(KEYS.lists));
        },

        // Retourne une liste précise
        get(listId) {
            return read(KEYS.lists)[listId] ?? null;
        },
    };

    // ================================================================
    //  WATCHLIST
    // ================================================================
    const Watchlist = {

        add(slug, meta = {}) {
            const all = read(KEYS.watchlist);
            all[slug] = { slug, added_at: new Date().toISOString(), ...meta };
            const entries = Object.entries(all);
            if (entries.length > 100) {
                entries.sort((a, b) => (a[1].added_at ?? '') < (b[1].added_at ?? '') ? -1 : 1);
                write(KEYS.watchlist, Object.fromEntries(entries.slice(-100)));
            } else {
                write(KEYS.watchlist, all);
            }
        },

        remove(slug) {
            const all = read(KEYS.watchlist);
            delete all[slug];
            write(KEYS.watchlist, all);
        },

        toggle(slug, meta = {}) {
            this.has(slug) ? this.remove(slug) : this.add(slug, meta);
            return this.has(slug);
        },

        has(slug) {
            return slug in read(KEYS.watchlist);
        },

        getAll() {
            return Object.values(read(KEYS.watchlist));
        },
    };

    // ================================================================
    //  UI — Bouton watchlist
    // ================================================================
    const WL_SVG_OUTLINE = '<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M17 3a2 2 0 0 1 2 2v15a1 1 0 0 1-1.496.868l-4.512-2.578a2 2 0 0 0-1.984 0l-4.512 2.578A1 1 0 0 1 5 20V5a2 2 0 0 1 2-2z"/></svg>';
    const WL_SVG_FILLED  = '<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M17 3a2 2 0 0 1 2 2v15a1 1 0 0 1-1.496.868l-4.512-2.578a2 2 0 0 0-1.984 0l-4.512 2.578A1 1 0 0 1 5 20V5a2 2 0 0 1 2-2z"/><path d="m9 10 2 2 4-4"/></svg>';

    function initWatchlistButton(btn, slug, meta = {}) {
        if (!btn) return;

        function updateBtn(inWl) {
            btn.innerHTML = inWl ? WL_SVG_FILLED : WL_SVG_OUTLINE;
            btn.style.color = inWl ? 'var(--color-accent)' : '';
            btn.style.backgroundColor = inWl ? 'rgba(200, 164, 104, 0.06)' : '';
        }

        updateBtn(Watchlist.has(slug));

        btn.addEventListener('click', () => {
            const inWl = Watchlist.toggle(slug, meta);
            updateBtn(inWl);
            if (typeof showToast === 'function') {
                showToast(inWl ? 'Ajouté à la watchlist' : 'Retiré de la watchlist');
            }
        });
    }

    // ================================================================
    //  UI — Mise à jour des barres de progression sur les cartes
    //  Appelle cette fonction sur la page d'accueil après chargement
    // ================================================================
    function updateCardProgressBars(episodes) {
        // episodes = objet { slug: [{ file_key, number }, ...] }
        const all = read(KEYS.progress);

        document.querySelectorAll('[data-anime-slug]').forEach(card => {
            const slug = card.dataset.animeSlug;
            const eps = episodes[slug] ?? [];
            if (eps.length === 0) return;

            const pct = Progress.getAnimeProgress(eps);
            const bar = card.querySelector('.card__progress-fill');
            if (bar) bar.style.width = pct + '%';

            // Badge "En cours" si progression partielle
            if (pct > 0 && pct < 100) {
                const badge = card.querySelector('.card__badge-progress');
                if (badge) badge.textContent = pct + '%';
            }
        });
    }

    // ================================================================
    //  UI — Bouton favori
    // ================================================================
    const FAV_SVG_OUTLINE = '<svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M20.84 4.61a5.5 5.5 0 0 0-7.78 0L12 5.67l-1.06-1.06a5.5 5.5 0 0 0-7.78 7.78l1.06 1.06L12 21.23l7.78-7.78 1.06-1.06a5.5 5.5 0 0 0 0-7.78z"/></svg>';
    const FAV_SVG_FILLED  = '<svg width="15" height="15" viewBox="0 0 24 24" fill="currentColor" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M20.84 4.61a5.5 5.5 0 0 0-7.78 0L12 5.67l-1.06-1.06a5.5 5.5 0 0 0-7.78 7.78l1.06 1.06L12 21.23l7.78-7.78 1.06-1.06a5.5 5.5 0 0 0 0-7.78z"/></svg>';

    function initFavoriteButton(btn, slug, meta = {}) {
        if (!btn) return;

        function updateBtn(isFav) {
            btn.innerHTML = (isFav ? FAV_SVG_FILLED : FAV_SVG_OUTLINE);
            btn.style.color = isFav ? 'var(--color-accent)' : '';
            btn.style.backgroundColor = isFav ? 'rgba(200, 164, 104, 0.06)' : '';
        }

        updateBtn(Favorites.isFavorite(slug));

        btn.addEventListener('click', () => {
            const isFav = Favorites.toggle(slug, meta);
            updateBtn(isFav);
            if (typeof showToast === 'function') {
                showToast(isFav ? 'Ajouté aux favoris' : 'Retiré des favoris');
            }
        });
    }

    // ================================================================
    //  UI — Modal gestion des listes
    // API publique
    return { Progress, Favorites, Watchlist, updateCardProgressBars, initFavoriteButton, initWatchlistButton };

})();

// ================================================================
//  TOAST GLOBAL — délégué à HoshimiToast (hoshimi_toast.js)
// ================================================================
function showToast(msg, type, duration) {
    if (typeof HoshimiToast !== 'undefined') {
        HoshimiToast.show(msg, { type: type ?? 'info', duration: duration ?? 4000 });
    }
}

// ── Accent couleur global ──────────────────────────────────────────────────────
(function () {
    const ACCENTS = {
        red:     { color: '#ef4444', rgb: '239,68,68',   dim: 'rgba(239,68,68,0.15)',   glow: 'rgba(239,68,68,0.25)',   text: '#fff'    },
        orange:  { color: '#f97316', rgb: '249,115,22',  dim: 'rgba(249,115,22,0.15)',  glow: 'rgba(249,115,22,0.25)',  text: '#1a1a1a' },
        yellow:  { color: '#eab308', rgb: '234,179,8',   dim: 'rgba(234,179,8,0.15)',   glow: 'rgba(234,179,8,0.25)',   text: '#1a1a1a' },
        gold:    { color: '#d4a017', rgb: '212,160,23',  dim: 'rgba(212,160,23,0.15)',  glow: 'rgba(212,160,23,0.25)',  text: '#1a1a1a' },
        lime:    { color: '#84cc16', rgb: '132,204,22',  dim: 'rgba(132,204,22,0.15)',  glow: 'rgba(132,204,22,0.25)',  text: '#1a1a1a' },
        green:   { color: '#22c55e', rgb: '34,197,94',   dim: 'rgba(34,197,94,0.15)',   glow: 'rgba(34,197,94,0.25)',   text: '#1a1a1a' },
        dore:    { color: '#c8a468', rgb: '200,164,104', dim: 'rgba(200,164,104,0.15)', glow: 'rgba(200,164,104,0.25)', text: '#1a1a1a' },
        teal:    { color: '#14b8a6', rgb: '20,184,166',  dim: 'rgba(20,184,166,0.15)',  glow: 'rgba(20,184,166,0.25)',  text: '#1a1a1a' },
        cyan:    { color: '#06b6d4', rgb: '6,182,212',   dim: 'rgba(6,182,212,0.15)',   glow: 'rgba(6,182,212,0.25)',   text: '#1a1a1a' },
        blue:    { color: '#3b82f6', rgb: '59,130,246',  dim: 'rgba(59,130,246,0.15)',  glow: 'rgba(59,130,246,0.25)',  text: '#fff'    },
        indigo:  { color: '#6366f1', rgb: '99,102,241',  dim: 'rgba(99,102,241,0.15)',  glow: 'rgba(99,102,241,0.25)',  text: '#fff'    },
        purple:  { color: '#8b5cf6', rgb: '139,92,246',  dim: 'rgba(139,92,246,0.15)',  glow: 'rgba(139,92,246,0.25)',  text: '#fff'    },
        pink:    { color: '#ec4899', rgb: '236,72,153',  dim: 'rgba(236,72,153,0.15)',  glow: 'rgba(236,72,153,0.25)',  text: '#fff'    },
        white:   { color: '#e2e8f0', rgb: '226,232,240', dim: 'rgba(226,232,240,0.15)', glow: 'rgba(226,232,240,0.25)', text: '#1a1a1a' },
        silver:  { color: '#94a3b8', rgb: '148,163,184', dim: 'rgba(148,163,184,0.15)', glow: 'rgba(148,163,184,0.25)', text: '#1a1a1a' },
    };
    const key = localStorage.getItem('hoshimi_accent') ?? 'gold';
    const a = ACCENTS[key] ?? ACCENTS.gold;
    const r = document.documentElement;
    r.style.setProperty('--color-accent',     a.color);
    r.style.setProperty('--color-accent-rgb', a.rgb);
    r.style.setProperty('--color-accent-dim', a.dim);
    r.style.setProperty('--color-accent-glow',a.glow);
    r.style.setProperty('--color-accent-text',a.text);
})();

// ── Lazy fade-in pour les posters ─────────────────────────────────────────────
function initPosterFade(img) {
    const markLoaded = () => {
        img.classList.add('is-loaded');
        img.closest('.card__media')?.classList.add('is-img-loaded');
    };
    if (img.complete && img.naturalWidth > 0) {
        markLoaded();
    } else {
        img.addEventListener('load', markLoaded);
    }
}

document.addEventListener('DOMContentLoaded', () => {
    document.querySelectorAll('.card__poster').forEach(initPosterFade);

    new MutationObserver(mutations => {
        for (const m of mutations) {
            for (const node of m.addedNodes) {
                if (node.nodeType !== 1) continue;
                if (node.classList?.contains('card__poster')) {
                    initPosterFade(node);
                } else {
                    node.querySelectorAll?.('.card__poster').forEach(initPosterFade);
                }
            }
        }
    }).observe(document.body, { childList: true, subtree: true });

    // ── Prefetch on card hover ─────────────────────────────────────────────────
    const _prefetched = new Set();
    let _prefetchTimer = null;

    function _doPrefetch(url) {
        if (!url || url === '#' || _prefetched.has(url)) return;
        _prefetched.add(url);
        const link = document.createElement('link');
        link.rel  = 'prefetch';
        link.href = url;
        document.head.appendChild(link);
    }

    document.addEventListener('pointerenter', function (e) {
        const card = e.target.closest('a.card');
        if (!card) return;
        const url = card.getAttribute('href');
        if (!url || url === '#') return;
        clearTimeout(_prefetchTimer);
        _prefetchTimer = setTimeout(() => _doPrefetch(url), 80);
    }, true);

    document.addEventListener('pointerleave', function (e) {
        if (e.target.closest('a.card')) clearTimeout(_prefetchTimer);
    }, true);


});


