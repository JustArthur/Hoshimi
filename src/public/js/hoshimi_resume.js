// Builds vertical poster-style resume cards (horizontal scroll) from allShowsData
(function () {
    const section = document.getElementById('resume-section');
    const row     = document.getElementById('resume-list');

    if (!section || !row) return;
    if (typeof allShowsData === 'undefined' || typeof HoshimiStorage === 'undefined') return;

    const allProgress = HoshimiStorage.Progress.getAllProgress();
    const items = [];

    Object.values(allShowsData).forEach(show => {
        let lastEp = null;
        let lastP  = null;

        show.episodes.forEach(ep => {
            const p = allProgress[ep.file_key];
            if (!p || p.completed || p.position <= 10) return;
            if (!lastEp || p.updated_at > lastP.updated_at) {
                lastEp = ep;
                lastP  = p;
            }
        });

        if (lastEp && lastP) {
            items.push({
                slug:       show.slug,
                type:       show.type,
                show_title: show.show_title,
                cover_url:  show.cover_url,
                banner_url: show.banner_url,
                file_key:   lastEp.file_key,
                number:     lastEp.number,
                season:     lastEp.season,
                position:   lastP.position,
                duration:   lastP.duration,
                updated_at: lastP.updated_at,
            });
        }
    });

    items.sort((a, b) => b.updated_at.localeCompare(a.updated_at));

    if (items.length === 0) return;

    section.style.display = 'block';

    const esc = s => String(s)
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;');

    const thumbIndex = (typeof THUMB_INDEX !== 'undefined') ? THUMB_INDEX : {};
    const typeLabels = { anime: 'Anime', serie: 'Série', film: 'Film' };

    items.forEach(item => {
        const thumb   = item.banner_url || thumbIndex[item.file_key] || item.cover_url || '';
        const pct     = item.duration > 0 ? Math.min(100, Math.round((item.position / item.duration) * 100)) : 0;
        const sNum    = parseInt(item.season);

        let epLabel;
        if (item.type === 'film' || sNum === 999) {
            epLabel = 'Film';
        } else if (sNum === 888) {
            epLabel = `OAV ${item.number}`;
        } else if (sNum === 777) {
            epLabel = `Spécial ${item.number}`;
        } else {
            epLabel = `Saison ${sNum}  Épisode ${item.number}`;
        }

        let playerUrl;
        if (item.type === 'film') {
            playerUrl = `/player/?type=film&slug=${encodeURIComponent(item.slug)}&saison=${item.season}&episode=${item.number}`;
        } else if (item.type === 'serie') {
            playerUrl = `/player/?type=serie&slug=${encodeURIComponent(item.slug)}&saison=${item.season}&episode=${item.number}`;
        } else {
            playerUrl = `/player/?slug=${encodeURIComponent(item.slug)}&saison=${item.season}&episode=${item.number}`;
        }

        const imageHTML = thumb
            ? `<img src="${thumb}" alt="" loading="lazy">`
            : `<div class="resume-card__image-placeholder">▶</div>`;

        const card = document.createElement('a');
        card.href      = playerUrl;
        card.className = 'resume-card';
        card.innerHTML = `
            <div class="resume-card__image">
                ${imageHTML}
                <div class="resume-card__progress">
                    <div class="resume-card__progress-fill" style="width:${pct}%"></div>
                </div>
            </div>
            <div class="resume-card__body">
                <div class="resume-card__title">${esc(item.show_title)}</div>
                <div class="resume-card__ep-tag">${esc(epLabel)}</div>
            </div>`;

        row.appendChild(card);
    });
})();
