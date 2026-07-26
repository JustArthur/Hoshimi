// Tab switching — detail page + player page
(function () {
    'use strict';

    const allBtns   = [...document.querySelectorAll('.detail-tab-btn[data-tab]')];
    const allPanels = [...document.querySelectorAll('.detail-tab-panel')];
    const bar       = document.querySelector('.detail-tabs-bar__inner');

    // Crée l'indicateur glissant
    let indicator = null;
    if (bar && allBtns.length) {
        indicator = document.createElement('span');
        indicator.className = 'detail-tabs-indicator';
        bar.appendChild(indicator);
    }

    function moveIndicator(btn) {
        if (!indicator) return;
        indicator.style.left  = btn.offsetLeft + 'px';
        indicator.style.width = btn.offsetWidth + 'px';
    }

    // Position initiale (sans transition)
    const activeBtn = allBtns.find(b => b.classList.contains('is-active'));
    if (activeBtn && indicator) {
        indicator.style.transition = 'none';
        moveIndicator(activeBtn);
        requestAnimationFrame(() => { indicator.style.transition = ''; });
    }

    allBtns.forEach(btn => {
        btn.addEventListener('click', () => {
            const target  = btn.dataset.tab;
            const fromIdx = allBtns.findIndex(b => b.classList.contains('is-active'));
            const toIdx   = allBtns.indexOf(btn);
            if (fromIdx === toIdx) return;

            const dir = toIdx > fromIdx ? 'right' : 'left';

            allBtns.forEach(b => b.classList.toggle('is-active', b === btn));
            moveIndicator(btn);

            allPanels.forEach(p => {
                const show = p.id === 'tab-' + target;
                p.classList.remove('is-entering-right', 'is-entering-left');
                if (show) {
                    p.hidden = false;
                    p.classList.add('is-entering-' + dir);
                    p.addEventListener('animationend', () => p.classList.remove('is-entering-right', 'is-entering-left'), { once: true });
                } else {
                    p.hidden = true;
                }
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
