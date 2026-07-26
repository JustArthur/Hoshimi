(function () {
    const backdrops = document.querySelectorAll('.detail-hero__backdrop, .home-hero__backdrop');
    if (!backdrops.length) return;
    let ticking = false;
    window.addEventListener('scroll', () => {
        if (ticking) return;
        ticking = true;
        requestAnimationFrame(() => {
            const y = Math.round(window.scrollY * 0.28);
            backdrops.forEach(b => { b.style.transform = `translateY(${y}px)`; });
            ticking = false;
        });
    }, { passive: true });
})();
