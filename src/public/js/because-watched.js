// « Parce que tu as regardé… » — suggestions basées sur le dernier titre regardé
(function () {
    const section = document.getElementById('because-section');
    const grid    = document.getElementById('because-grid');
    const label   = document.getElementById('because-title');

    if (!section || !grid || !label) return;
    if (typeof allShowsData === 'undefined') return;

    let progress = {};
    try { progress = JSON.parse(localStorage.getItem('hoshimi_progress') ?? '{}'); } catch {}

    // Titre dont la progression a été mise à jour le plus récemment
    let last = null;
    Object.values(allShowsData).forEach(show => {
        (show.episodes ?? []).forEach(ep => {
            const p = progress[ep.file_key];
            if (!p || !p.updated_at) return;
            if (!last || p.updated_at > last.at) last = { at: p.updated_at, show };
        });
    });
    if (!last) return;

    fetch('/api/because-you-watched.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ type: last.show.type, slug: last.show.slug }),
    })
        .then(r => r.json())
        .then(data => {
            if (!data.html) return;
            label.textContent = `Parce que tu as regardé ${data.title}`;
            grid.innerHTML = data.html;
            section.style.display = 'block';
        })
        .catch(() => {});
})();
