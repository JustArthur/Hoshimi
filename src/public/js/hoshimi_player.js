// Video player — Video.js init, progress, resume notice, keyboard shortcuts, theatre mode
// Requires: videojs, HoshimiStorage, setAccentFromAnime (hoshimi_accent.js)
// Config injected by PHP via window.HOSHIMI_PLAYER and window.HOSHIMI_ACCENT_DATA
(function () {
    'use strict';

    const cfg = window.HOSHIMI_PLAYER || {};
    const PROGRESS_KEY       = cfg.progressKey       || '';
    const SAVE_INTERVAL_SEC  = cfg.saveInterval      || 5;
    const COMPLETE_THRESHOLD = cfg.completeThreshold || 0.92;
    const IS_FILM            = cfg.isFilm            || false;

    // ── Video.js init ─────────────────────────────────────────────────────────
    const player = videojs('hoshimi-player', {
        controls:      true,
        autoplay:      false,
        preload:       'auto',
        fluid:         true,
        responsive:    true,
        playbackRates: [0.5, 0.75, 1, 1.25, 1.5, 2],
    });

    // ── Volume + résumé ───────────────────────────────────────────────────────
    player.ready(function () {
        const savedVol   = parseFloat(localStorage.getItem('hoshimi_volume') || '1');
        const savedMuted = localStorage.getItem('hoshimi_muted') === 'true';
        if (!isNaN(savedVol)) player.volume(savedVol);
        if (savedMuted)       player.muted(true);

        if (!IS_FILM) {
            const data = HoshimiStorage.Progress.get(PROGRESS_KEY);
            if (data.position > 10 && !data.completed) {
                player.currentTime(data.position);
                showResumeNotice(data.position);
            }
        }
    });

    player.on('volumechange', function () {
        localStorage.setItem('hoshimi_volume', player.volume());
        localStorage.setItem('hoshimi_muted',  player.muted());
    });

    // ── Sauvegarde périodique ─────────────────────────────────────────────────
    let saveTimer;

    player.on('timeupdate', function () {
        clearTimeout(saveTimer);
        saveTimer = setTimeout(saveProgress, SAVE_INTERVAL_SEC * 1000);

        const currentTime = player.currentTime();
        const duration    = player.duration() || 1;
        const pct         = Math.min((currentTime / duration) * 100, 100);

        const activeCard = document.querySelector('.episode-card[data-ep-key="' + PROGRESS_KEY + '"]');
        if (activeCard) {
            const bar = activeCard.querySelector('.episode-card__progress-fill');
            if (bar) bar.style.width = pct + '%';
            if (pct >= 95) {
                activeCard.classList.add('is-completed');
                if (bar) bar.style.width = '100%';
            } else {
                activeCard.classList.remove('is-completed');
            }
        }
    });

    player.on('pause', saveProgress);
    player.on('ended', onVideoEnded);

    function saveProgress(completed) {
        HoshimiStorage.Progress.save(
            PROGRESS_KEY,
            player.currentTime(),
            player.duration() || 0,
            completed === true
        );
    }

    function onVideoEnded() {
        saveProgress(true);
        document.dispatchEvent(new CustomEvent('hoshimi:video-ended'));
    }

    // ── Resume notice ─────────────────────────────────────────────────────────
    function showResumeNotice(position) {
        if (document.querySelector('.resume-notice')) return;
        const mins = Math.floor(position / 60);
        const secs = String(Math.floor(position % 60)).padStart(2, '0');

        const el = document.createElement('div');
        el.className = 'resume-notice';
        el.innerHTML =
            '<div class="resume-notice__content"><p>Reprendre à <strong>' + mins + ':' + secs + '</strong> ?</p></div>'
            + '<div class="resume-notice__actions">'
            +   '<button class="resume-btn resume-btn--primary" id="resumeYes">Reprendre</button>'
            +   '<button class="resume-btn resume-btn--secondary" id="resumeNo">Recommencer</button>'
            + '</div>';

        document.body.appendChild(el);
        setTimeout(() => el.classList.add('is-visible'), 10);

        el.querySelector('#resumeYes').addEventListener('click', () => { player.play(); el.remove(); });
        el.querySelector('#resumeNo').addEventListener('click',  () => { player.currentTime(0); player.play(); el.remove(); });

        setTimeout(() => {
            if (el.parentNode) { el.classList.remove('is-visible'); setTimeout(() => el.remove(), 300); }
        }, 10000);
    }

    // ── Raccourcis clavier ────────────────────────────────────────────────────
    document.addEventListener('keydown', function (e) {
        if (['INPUT', 'TEXTAREA', 'SELECT'].includes(document.activeElement.tagName)) return;
        switch (e.key) {
            case ' ':
            case 'k':
                e.preventDefault();
                player.paused() ? player.play() : player.pause();
                break;
            case 'ArrowRight':
            case 'l':
                player.currentTime(Math.min(player.currentTime() + 10, player.duration()));
                break;
            case 'ArrowLeft':
            case 'j':
                player.currentTime(Math.max(player.currentTime() - 10, 0));
                break;
            case 'ArrowUp':
                player.volume(Math.min(player.volume() + 0.1, 1));
                break;
            case 'ArrowDown':
                player.volume(Math.max(player.volume() - 0.1, 0));
                break;
            case 'm':
                player.muted(!player.muted());
                break;
            case 'f':
                player.isFullscreen() ? player.exitFullscreen() : player.requestFullscreen();
                break;
            case 'n':
                if (window.HOSHIMI_NEXT_URL) window.location.href = window.HOSHIMI_NEXT_URL;
                break;
            case 't':
                toggleTheatre();
                break;
        }
    });

    // ── Mode théâtre ──────────────────────────────────────────────────────────
    function toggleTheatre() {
        const page = document.querySelector('.player-page');
        if (page) page.classList.toggle('is-theatre');
    }
    window.toggleTheatre = toggleTheatre;

    // ── Barres de progression sur les épisode-cards ───────────────────────────
    document.addEventListener('DOMContentLoaded', function () {
        const allProgress = HoshimiStorage.Progress.getAllProgress();

        document.querySelectorAll('.episode-card[data-ep-key]').forEach(card => {
            const p = allProgress[card.dataset.epKey];
            if (!p || p.duration <= 0) return;

            const pct        = (p.position / p.duration) * 100;
            const isFinished = p.completed || pct >= 92;
            const bar        = card.querySelector('.episode-card__progress-fill');

            if (bar) bar.style.width = (isFinished ? 100 : pct) + '%';
            if (isFinished) card.classList.add('is-completed');
        });
    });

    // ── Accent couleur ────────────────────────────────────────────────────────
    if (typeof setAccentFromAnime === 'function' && window.HOSHIMI_ACCENT_DATA) {
        setAccentFromAnime(window.HOSHIMI_ACCENT_DATA);
    }
})();
