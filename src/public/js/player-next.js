// Next-episode toast countdown
// Reads NEXT_URL / NEXT_TITLE from #hoshimi-player data attrs (set before this script runs)
(function () {
    'use strict';

    const NEXT_DELAY_SEC = 10;

    let nextTimer    = null;
    let nextCancelled = false;

    function showNextToast() {
        const toast     = document.getElementById('next-toast');
        const bar       = document.getElementById('next-bar');
        const countdown = document.getElementById('next-countdown');
        if (!toast) return;

        toast.classList.add('is-visible');

        bar.style.transition = 'none';
        bar.style.width      = '100%';
        setTimeout(() => {
            bar.style.transition = 'width ' + NEXT_DELAY_SEC + 's linear';
            bar.style.width      = '0%';
        }, 50);

        let remaining = NEXT_DELAY_SEC;
        nextTimer = setInterval(() => {
            remaining--;
            if (countdown) countdown.textContent = remaining;
            if (remaining <= 0) {
                clearInterval(nextTimer);
                if (!nextCancelled && window.HOSHIMI_NEXT_URL) {
                    window.location.href = window.HOSHIMI_NEXT_URL;
                }
            }
        }, 1000);
    }

    function cancelNext() {
        nextCancelled = true;
        clearInterval(nextTimer);
        const toast = document.getElementById('next-toast');
        if (toast) toast.classList.remove('is-visible');
    }

    // Expose so the inline onclick in the toast button can call it
    window.cancelNext = cancelNext;

    // Listen for the custom event fired by hoshimi_player.js
    document.addEventListener('hoshimi:video-ended', () => {
        if (window.HOSHIMI_NEXT_URL && !nextCancelled) showNextToast();
    });
})();
