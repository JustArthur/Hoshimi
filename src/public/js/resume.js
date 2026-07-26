// Resume / Continue watching section
(function () {
    const section = document.getElementById('resume-section');
    const row     = document.getElementById('resume-list');

    if (!section || !row) return;
    if (typeof allShowsData === 'undefined' || typeof HoshimiStorage === 'undefined') return;

    const DISMISSED_KEY = 'hoshimi_resume_dismissed';

    function getDismissed() {
        try { return JSON.parse(localStorage.getItem(DISMISSED_KEY) ?? '{}'); } catch { return {}; }
    }
    function dismiss(fileKey) {
        const d = getDismissed();
        d[fileKey] = true;
        localStorage.setItem(DISMISSED_KEY, JSON.stringify(d));
    }

    function fmtTime(s) {
        s = Math.floor(s);
        const h = Math.floor(s / 3600), m = Math.floor((s % 3600) / 60), sec = s % 60;
        return h > 0
            ? `${h}:${String(m).padStart(2,'0')}:${String(sec).padStart(2,'0')}`
            : `${m}:${String(sec).padStart(2,'0')}`;
    }

    const esc = s => String(s)
        .replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;');

    const dismissed = getDismissed();
    const items     = [];

    Object.values(allShowsData).forEach(show => {
        const resume = HoshimiStorage.Progress.getResumeEpisode(show.episodes);
        if (!resume) return;
        if (dismissed[resume.file_key]) return;

        items.push({
            slug:       show.slug,
            type:       show.type,
            show_title: show.show_title,
            cover_url:  show.cover_url,
            score:      show.score ?? null,
            overview:   show.overview ?? '',
            file_key:   resume.file_key,
            number:     resume.number,
            season:     resume.season,
            position:   resume.position,
            duration:   resume.duration,
            updated_at: resume.updated_at,
            isNext:     resume.isNext ?? false,
        });
    });

    items.sort((a, b) => b.updated_at.localeCompare(a.updated_at));

    if (items.length === 0) return;
    section.style.display = 'block';

    // ── Génération des cards ───────────────────────────────────────────────
    items.forEach((item) => {
        const pct      = (!item.isNext && item.duration > 0)
            ? Math.min(100, Math.round((item.position / item.duration) * 100))
            : 0;
        const fmtPos   = item.position > 0 ? fmtTime(item.position) : null;
        const fmtDur   = item.duration  > 0 ? fmtTime(item.duration)  : null;
        const time     = fmtPos && fmtDur ? `${fmtPos} / ${fmtDur}` : null;
        const badgeText  = item.isNext ? 'Suivant' : 'En cours';
        const typeLabel  = item.type === 'film' ? 'Film' : item.type === 'serie' ? 'Série' : 'Anime';

        let playerUrl;
        if (item.type === 'film') {
            playerUrl = `/player/?type=film&slug=${encodeURIComponent(item.slug)}&saison=${item.season}&episode=${item.number}`;
        } else if (item.type === 'serie') {
            playerUrl = `/player/?type=serie&slug=${encodeURIComponent(item.slug)}&saison=${item.season}&episode=${item.number}`;
        } else {
            playerUrl = `/player/?slug=${encodeURIComponent(item.slug)}&saison=${item.season}&episode=${item.number}`;
        }

        const coverHTML = item.cover_url
            ? `<img src="${esc(item.cover_url)}" alt="" draggable="false" loading="lazy">`
            : `<div style="width:100%;height:100%;display:flex;align-items:center;justify-content:center;opacity:.2">${lc('play', 24)}</div>`;

        const card = document.createElement('div');
        card.className = 'resume-card';
        card.dataset.fileKey = item.file_key;

        card.innerHTML = `
            <a href="${esc(playerUrl)}" class="resume-card__poster">
                ${coverHTML}
                <button class="resume-card__dismiss" data-key="${esc(item.file_key)}">${lc('x', 12)}</button>
                ${!item.isNext ? `<div class="resume-card__progress"><div class="resume-card__progress-fill" style="width:${pct}%"></div></div>` : ''}
            </a>
            <div class="card-bubble">
                <div class="card-bubble-inner">
                    <div class="card-bubble-title">${esc(item.show_title)}</div>
                    <div class="card-bubble-meta">
                        ${item.score ? `<span class="card-bubble-rating">${lc('star', 13)} ${item.score}</span>` : ''}
                        <span class="card-bubble-type">${typeLabel}</span>
                    </div>
                    ${time ? `<div class="card-bubble-meta"><span class="card-bubble-year">${time}</span></div>` : ''}
                    ${item.overview ? `<p class="card-bubble-synopsis">${esc(item.overview)}</p>` : ''}
                    <div class="card-bubble-buttons" style="margin-top:auto">
                        <a href="${esc(playerUrl)}" class="btn btn--primary btn-bubble">${lc('play', 13)} Reprendre</a>
                    </div>
                </div>
            </div>`;
        row.appendChild(card);
    });

    // ── Dismiss ───────────────────────────────────────────────────────────
    row.addEventListener('click', e => {
        const btn = e.target.closest('.resume-card__dismiss');
        if (!btn) return;
        e.preventDefault();
        e.stopPropagation();
        const key  = btn.dataset.key;
        const card = btn.closest('.resume-card');
        dismiss(key);
        card.style.transition = 'opacity 0.2s, transform 0.2s';
        card.style.opacity    = '0';
        card.style.transform  = 'scale(0.95)';
        setTimeout(() => {
            card.remove();
            if (!row.querySelector('.resume-card')) section.style.display = 'none';
        }, 200);
    });
})();
