// Tab switching — detail page + player page
(function () {
    'use strict';

    document.querySelectorAll('.detail-tab-btn[data-tab]').forEach(btn => {
        btn.addEventListener('click', () => {
            const target = btn.dataset.tab;

            document.querySelectorAll('.detail-tab-btn').forEach(b =>
                b.classList.toggle('is-active', b === btn)
            );
            document.querySelectorAll('.detail-tab-panel').forEach(p => {
                const show = p.id === 'tab-' + target;
                p.hidden = !show;
                p.classList.toggle('is-active', show);
            });
        });
    });

    // Player only: sync .player-info height → --player-info-height CSS var
    const playerInfo = document.querySelector('.player-info');
    if (playerInfo) {
        const page = document.querySelector('.player-page');
        if (page) {
            const sync = () => page.style.setProperty('--player-info-height', playerInfo.offsetHeight + 'px');
            sync();
            new ResizeObserver(sync).observe(playerInfo);
        }
    }
})();
