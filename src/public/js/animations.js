// ── Hoshimi — Animations globales ────────────────────────────────────────────
// Respecte prefers-reduced-motion partout

(function () {
    if (window.matchMedia('(prefers-reduced-motion: reduce)').matches) return;

    // ── ① Section reveal au scroll ──────────────────────────────────────────
    const REVEAL_SELECTORS = [
        '.section-header',
        '.catalog-main__header',
        '.detail-section',
        '.detail-tabs-bar',
        '.profil-section',
        '.profil-storage',
        '.profil-avatar-card',
        '.profil-stats',
        '.fav-hero__stats',
        '.fav-hero__title-row',
        '.hist-card',
    ];

    const revealObserver = new IntersectionObserver((entries) => {
        entries.forEach(entry => {
            if (!entry.isIntersecting) return;
            entry.target.classList.add('is-revealed');
            revealObserver.unobserve(entry.target);
        });
    }, { threshold: 0.08, rootMargin: '0px 0px -40px 0px' });

    function observeReveal() {
        document.querySelectorAll(REVEAL_SELECTORS.join(',')).forEach(el => {
            if (!el.classList.contains('is-revealed')) {
                el.classList.add('will-reveal');
                revealObserver.observe(el);
            }
        });
    }

    // ── ② Cards stagger sur grids ───────────────────────────────────────────
    function staggerGrid(grid) {
        const cards = grid.querySelectorAll(':scope > .card, :scope > .fav-card-wrap, :scope > .resume-card');
        cards.forEach((card, i) => {
            card.style.setProperty('--card-i', i);
            card.classList.add('card--stagger');
        });
    }

    function staggerAllGrids() {
        document.querySelectorAll('.grid-cards, .grid-cards--row').forEach(staggerGrid);
    }

    // ── ③ Hero détail entrance ──────────────────────────────────────────────
    function animateDetailHero() {
        const backdrop = document.querySelector('.detail-hero__backdrop');
        const content  = document.querySelector('.detail-hero__content');
        const poster   = document.querySelector('.detail-hero__poster-img');
        if (backdrop) backdrop.classList.add('detail-hero__backdrop--animate');
        if (content)  content.classList.add('detail-hero__content--animate');
        if (poster)   poster.classList.add('detail-hero__poster-img--animate');
    }

    // ── Init ────────────────────────────────────────────────────────────────
    function init() {
        staggerAllGrids();
        observeReveal();
        animateDetailHero();
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }

    // Re-observer après mutations (résultats de recherche, favoris rendus en JS…)
    new MutationObserver((mutations) => {
        let shouldRestagger = false;
        mutations.forEach(m => {
            m.addedNodes.forEach(node => {
                if (node.nodeType !== 1) return;
                if (node.matches?.('.card, .fav-card-wrap, .resume-card')) shouldRestagger = true;
                if (node.querySelector?.('.card, .fav-card-wrap, .resume-card')) shouldRestagger = true;
                // Reveal pour éléments ajoutés dynamiquement
                REVEAL_SELECTORS.forEach(sel => {
                    if (node.matches?.(sel) && !node.classList.contains('is-revealed')) {
                        node.classList.add('will-reveal');
                        revealObserver.observe(node);
                    }
                });
            });
        });
        if (shouldRestagger) staggerAllGrids();
    }).observe(document.body, { childList: true, subtree: true });
})();
