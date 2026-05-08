// ============================================================
//  HOSHIMI — Storage Manager
//  Gère via localStorage :
//    - Progression de visionnage par épisode
//    - Favoris (par slug anime)
//    - Listes personnalisées
// ============================================================

const HoshimiStorage = (() => {

    const KEYS = {
        progress: 'hoshimi_progress',
        favorites: 'hoshimi_favorites',
        lists: 'hoshimi_lists',
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
    //  LISTES PERSONNALISÉES
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
        });
    }

    // ================================================================
    //  UI — Modal gestion des listes
    // ================================================================
    function openListModal(slug, meta = {}) {
        // Supprime un éventuel modal existant
        document.getElementById('hoshimi-list-modal')?.remove();

        const lists = Lists.getAll();
        const modal = document.createElement('div');
        modal.id = 'hoshimi-list-modal';
        modal.style.cssText = `
      position:fixed; inset:0; z-index:1000;
      background:rgba(0,0,0,.7); backdrop-filter:blur(4px);
      display:flex; align-items:center; justify-content:center;
      padding:16px;
    `;

        modal.innerHTML = `
      <div style="
        background:var(--color-bg-elevated);
        border:1px solid var(--color-border);
        border-radius:var(--radius-md);
        padding:24px; min-width:320px; max-width:480px; width:100%;
        box-shadow:var(--shadow-lg);
      ">
        <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:20px;">
          <h3 style="font-size:1rem; font-weight:700;">Ajouter à une liste</h3>
          <button id="hoshimi-modal-close" style="
            background:none; border:none; color:var(--color-text-muted);
            font-size:1.2rem; cursor:pointer; padding:4px;
          ">✕</button>
        </div>

        <div id="hoshimi-lists-container" style="display:flex; flex-direction:column; gap:8px; margin-bottom:16px;">
          ${lists.length === 0
                ? '<p style="color:var(--color-text-muted); font-size:.85rem;">Aucune liste. Créez-en une ci-dessous.</p>'
                : lists.map(list => `
              <label style="
                display:flex; align-items:center; gap:12px;
                padding:10px 12px; border-radius:var(--radius-sm);
                background:var(--color-bg-card); border:1px solid var(--color-border-soft);
                cursor:pointer;
              ">
                <input type="checkbox"
                  data-list-id="${list.id}"
                  ${Lists.hasItem(list.id, slug) ? 'checked' : ''}
                  style="accent-color:var(--color-accent); width:16px; height:16px;"
                >
                <span style="font-size:.9rem;">${list.name}</span>
                <span style="margin-left:auto; font-size:.75rem; color:var(--color-text-muted);">
                  ${list.items?.length ?? 0} anime${(list.items?.length ?? 0) > 1 ? 's' : ''}
                </span>
              </label>
            `).join('')
            }
        </div>

        <div style="display:flex; gap:8px;">
          <input id="hoshimi-new-list-input" type="text" placeholder="Nouvelle liste…" style="
            flex:1; background:var(--color-bg-card); border:1px solid var(--color-border);
            border-radius:var(--radius-sm); padding:8px 12px; color:var(--color-text);
            font-size:.85rem; outline:none;
          ">
          <button id="hoshimi-new-list-btn" style="
            background:var(--color-accent); color:#111; border:none;
            border-radius:var(--radius-sm); padding:8px 14px;
            font-weight:600; font-size:.85rem; cursor:pointer;
          ">Créer</button>
        </div>
      </div>
    `;

        document.body.appendChild(modal);

        // Fermeture
        modal.querySelector('#hoshimi-modal-close').addEventListener('click', () => modal.remove());
        modal.addEventListener('click', e => { if (e.target === modal) modal.remove(); });

        // Checkboxes listes existantes
        modal.querySelectorAll('input[type="checkbox"]').forEach(cb => {
            cb.addEventListener('change', () => {
                const listId = cb.dataset.listId;
                cb.checked ? Lists.addItem(listId, slug, meta) : Lists.removeItem(listId, slug);
            });
        });

        // Créer nouvelle liste
        const input = modal.querySelector('#hoshimi-new-list-input');
        const newBtn = modal.querySelector('#hoshimi-new-list-btn');

        newBtn.addEventListener('click', () => {
            const name = input.value.trim();
            if (!name) return;
            const listId = Lists.create(name);
            Lists.addItem(listId, slug, meta);
            modal.remove();
            openListModal(slug, meta); // Rouvre avec la nouvelle liste
        });

        input.addEventListener('keydown', e => {
            if (e.key === 'Enter') newBtn.click();
        });
    }

    // API publique
    return { Progress, Favorites, Lists, updateCardProgressBars, initFavoriteButton, openListModal };

})();


function renameList(id, currentName) {
    const newName = prompt("Nouveau nom pour cette liste :", currentName);
    if (newName && newName.trim() !== "" && newName !== currentName) {
        HoshimiStorage.Lists.rename(id, newName.trim());
        window.location.reload();
    }
}

function deleteList(id, name) {
    if (confirm(`Supprimer la liste "${name}" ?`)) {
        HoshimiStorage.Lists.delete(id);
        window.location.reload();
    }
}