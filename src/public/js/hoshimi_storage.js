// ============================================================
//  HOSHIMI — Storage Manager
//  Gère via localStorage :
//    - Progression de visionnage par épisode
//    - Favoris (par slug anime/serie/film)
// ============================================================

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
        progress:    'hoshimi_progress',
        favorites:   'hoshimi_favorites',
        watchStatus: 'hoshimi_watch_status',
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

        // Sauvegarde la progression d'un épisode
        save(fileKey, position, duration, completed = false) {
            const all = read(KEYS.progress);
            const isCompleted = completed || (duration > 0 && position / duration >= 0.92);
            all[fileKey] = {
                position: Math.floor(position),
                duration: Math.floor(duration),
                completed: isCompleted,
                updated_at: new Date().toISOString(),
            };
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
        // Dans HoshimiStorage.Progress.getLastEpisode
        getLastEpisode(episodes) {
            if (!episodes || episodes.length === 0) return null;
            const all = read(KEYS.progress);
            let last = null;

            episodes.forEach(ep => {
                const p = all[ep.file_key];
                if (!p || p.completed || p.position <= 10) return;

                if (!last || new Date(p.updated_at) > new Date(last.updated_at)) {
                    // CRUCIAL : On fusionne ep (qui contient 'season' venant du PHP) 
                    // avec p (la progression)
                    last = { ...ep, ...p };
                }
            });

            return last;
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
    function initFavoriteButton(btn, slug, meta = {}) {
        if (!btn) return;

        function updateBtn(isFav) {
            btn.textContent = isFav ? '♥ Favori' : '♡ Favoris';
            btn.style.color = isFav ? 'var(--color-accent)' : '';
            btn.style.borderColor = isFav ? 'var(--color-accent)' : '';
        }

        updateBtn(Favorites.isFavorite(slug));

        btn.addEventListener('click', () => {
            const isFav = Favorites.toggle(slug, meta);
            updateBtn(isFav);
            if (typeof showToast === 'function') {
                showToast(isFav ? 'Ajouté aux favoris ♥' : 'Retiré des favoris');
            }
        });
    }

    // ================================================================
    //  UI — Modal gestion des listes
    // ================================================================
    //  STATUT DE VISIONNAGE
    //  Valeurs : 'À regarder' | 'En cours' | 'Terminé' | 'Abandonné'
    // ================================================================
    const WatchStatus = {
        STATUSES: ['À regarder', 'En cours', 'Terminé', 'Abandonné'],

        set(slug, status) {
            const all = read(KEYS.watchStatus);
            if (status === null) {
                delete all[slug];
            } else {
                all[slug] = { slug, status, updated_at: new Date().toISOString() };
            }
            write(KEYS.watchStatus, all);
        },

        get(slug) {
            return read(KEYS.watchStatus)[slug]?.status ?? null;
        },

        getAll() {
            return read(KEYS.watchStatus);
        },

        remove(slug) {
            this.set(slug, null);
        },
    };

    // API publique
    return { Progress, Favorites, WatchStatus, updateCardProgressBars, initFavoriteButton };

})();

// ================================================================
//  TOAST GLOBAL
// ================================================================
function showToast(msg, duration = 3000) {
    document.querySelector('.hoshimi-toast')?.remove();

    const t = document.createElement('div');
    t.className = 'hoshimi-toast';
    t.textContent = msg;
    document.body.appendChild(t);

    requestAnimationFrame(() => {
        requestAnimationFrame(() => t.classList.add('is-visible'));
    });

    setTimeout(() => {
        t.classList.remove('is-visible');
        setTimeout(() => t.remove(), 300);
    }, duration);
}


