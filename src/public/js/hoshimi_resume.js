// On récupère les éléments
const resumeSection = document.getElementById('resume-section');
const resumeGrid = document.getElementById('resume-grid');

// On ne lance la logique QUE si la section existe dans le HTML
if (resumeSection && resumeGrid) {

    Object.entries(allEpisodesData).forEach(([slug, episodes]) => {
        let lastEp = null;

        episodes.forEach(ep => {
            const p = allProgress[ep.file_key];
            if (!p || p.completed || p.position <= 10) return;
            if (!lastEp || p.updated_at > lastEp.updated_at) {
                lastEp = {
                    ...ep,
                    ...p,
                    slug
                };
            }
        });

        if (lastEp) resumeItems.push(lastEp);
    });

    // Trie par date de dernière lecture
    resumeItems.sort((a, b) => b.updated_at.localeCompare(a.updated_at));

    if (resumeItems.length > 0) {
        // On affiche la section si on a des items
        resumeSection.style.display = 'block';
        resumeGrid.innerHTML = ''; // Nettoyage de sécurité

        resumeItems.forEach(item => {
            const original = document.querySelector(`[data-anime-slug="${item.slug}"]`);
            if (!original) return;

            const clone = original.cloneNode(true);

            // 1. MISE À JOUR DU LIEN VERS LE PLAYER
            const season = item.season || 1;
            const episodeNum = item.number;
            clone.href = `/player/?slug=${encodeURIComponent(item.slug)}&saison=${season}&episode=${episodeNum}`;

            // 2. NETTOYAGE DES ÉLÉMENTS INUTILES
            ['.card__score', '.card__meta', '.card__fav', '.card__overlay'].forEach(sel => {
                const el = clone.querySelector(sel);
                if (el) el.remove();
            });

            // 3. GESTION DE LA BARRE DE PROGRESSION
            let barFill = clone.querySelector('.card__progress-fill');
            if (!barFill) {
                const barContainer = document.createElement('div');
                barContainer.className = 'card__progress';
                barContainer.innerHTML = '<div class="card__progress-fill"></div>';
                const body = clone.querySelector('.card__body');
                clone.insertBefore(barContainer, body);
                barFill = barContainer.querySelector('.card__progress-fill');
            }

            if (barFill && item.duration > 0) {
                const pct = (item.position / item.duration) * 100;
                barFill.style.width = Math.min(pct, 100) + '%';
            }

            // 4. AJOUT DU BADGE D'ÉPISODE
            const badge = document.createElement('div');
            badge.style.cssText = `
                position:absolute;
                top: 6px;
                left: 8px;
                font-size:0.7rem;
                font-weight:700;
                padding:2px 8px;
                border-radius:99px;
                background:rgba(0,0,0,.75);
                backdrop-filter:blur(6px);
                -webkit-backdrop-filter:blur(6px);
                border:1px solid var(--color-accent-dim);
                color:var(--color-accent);
                z-index:3;
            `;

            const mins = Math.floor(item.position / 60);
            const secs = Math.floor(item.position % 60).toString().padStart(2, '0');
            const sNum = parseInt(item.season);

            if (sNum === 999) {
                badge.textContent = `🎬 Film — ${mins}:${secs}`;
            } else if (sNum === 888) {
                badge.textContent = `📀 OAV ${item.number} — ${mins}:${secs}`;
            } else {
                badge.textContent = `📺 S${sNum} : Ép. ${String(item.number).padStart(2, '0')} — ${mins}:${secs}`;
            }

            clone.style.position = 'relative';
            clone.appendChild(badge);

            resumeGrid.appendChild(clone);
        });
    } else {
        // Optionnel : cacher la section si aucun item à reprendre
        resumeSection.style.display = 'none';
    }
}