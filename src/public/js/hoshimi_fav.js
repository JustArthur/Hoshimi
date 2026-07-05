(function () {
    const favSection = document.getElementById('favorites-section');
    const favGrid    = document.getElementById('favorites-grid');
    const favEmpty   = document.getElementById('favorites-empty');
    if (!favSection) return;

    const favs = HoshimiStorage.Favorites.getAll();

    favSection.style.display = 'block';

    if (favs.length === 0) {
        favGrid.style.display    = 'none';
        favEmpty.style.display   = 'block';
        return;
    }

    favGrid.style.display  = 'grid';
    favEmpty.style.display = 'none';

    const esc = s => String(s ?? '').replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;');

    favs.forEach(fav => {
        const type = fav.type ?? 'anime';
        const url  = type === 'film'
            ? `/film/?slug=${encodeURIComponent(fav.slug)}`
            : type === 'serie'
                ? `/anime/?slug=${encodeURIComponent(fav.slug)}&type=serie`
                : `/anime/?slug=${encodeURIComponent(fav.slug)}`;

        const icon = type === 'film' ? '🎬' : type === 'serie' ? '📺' : '🎌';

        const typeLabel = type === 'film' ? 'Film' : type === 'serie' ? 'Série' : 'Anime';

        const card = document.createElement('a');
        card.href      = url;
        card.className = 'card';
        card.innerHTML = `
            <div class="card__media">
                ${fav.cover_url
                    ? `<img class="card__poster" src="${esc(fav.cover_url)}" alt="${esc(fav.title)}" loading="lazy">`
                    : `<div class="no-cover"><div class="no-cover__content"><span class="no-cover__icon">${icon}</span></div></div>`}
                <div class="card__overlay"></div>
                <div class="card__play"><div class="card__play-btn">▶</div></div>
                <div class="card__body">
                    <div class="card__title">${esc(fav.title)}</div>
                    <div class="card__meta">
                        <span class="card__meta-badge">${typeLabel}</span>
                    </div>
                </div>
            </div>`;
        favGrid.appendChild(card);
    });
})();
