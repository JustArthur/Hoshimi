// Boutons ‹ › des rangées de cartes de l'accueil
(function () {
    function init(slider) {
        const track = slider.querySelector('.grid-cards--row');
        const prev  = slider.querySelector('.row-slider__btn--prev');
        const next  = slider.querySelector('.row-slider__btn--next');
        if (!track || !prev || !next) return;

        function update() {
            const max = track.scrollWidth - track.clientWidth;
            prev.hidden = track.scrollLeft <= 4;
            next.hidden = max <= 4 || track.scrollLeft >= max - 4;
        }

        function slide(dir) {
            track.scrollBy({ left: dir * track.clientWidth * 0.8, behavior: 'smooth' });
        }

        prev.addEventListener('click', () => slide(-1));
        next.addEventListener('click', () => slide(1));
        track.addEventListener('scroll', update, { passive: true });
        window.addEventListener('resize', update);
        new MutationObserver(update).observe(track, { childList: true });
        update();
    }

    document.querySelectorAll('.row-slider').forEach(init);
})();
