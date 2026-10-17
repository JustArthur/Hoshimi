// Video player — Video.js init, progress, resume notice, keyboard shortcuts
// Requires: videojs, HoshimiStorage
// Config injected by PHP via window.HOSHIMI_PLAYER
(function () {
    'use strict';

    const cfg = window.HOSHIMI_PLAYER || {};
    const PROGRESS_KEY       = cfg.progressKey       || '';
    const SAVE_INTERVAL_SEC  = cfg.saveInterval      || 5;
    const COMPLETE_THRESHOLD = cfg.completeThreshold || 0.92;
    const IS_FILM            = cfg.isFilm            || false;

    // ── Video.js init ─────────────────────────────────────────────────────────
    const player = videojs('hoshimi-player', {
        controls:   false,   // on utilise notre overlay custom
        autoplay:   false,
        preload:    'auto',
        fill:       true,
        responsive: true,
    });

    const SUBTITLES = cfg.subtitles || [];

    // ── Indicateur de chargement (poster + spinner pendant le buffering) ───────
    const bufferingEl = document.getElementById('hpl-buffering');
    if (bufferingEl) {
        const showBuffering = () => bufferingEl.classList.add('is-visible');
        const hideBuffering = () => bufferingEl.classList.remove('is-visible');
        player.on('waiting',   showBuffering);
        player.on('seeking',   showBuffering);
        player.on('loadstart', showBuffering);
        player.on('playing',   hideBuffering);
        player.on('canplay',   hideBuffering);
    }

    // ── Volume + résumé + sous-titres ─────────────────────────────────────────
    player.ready(function () {
        const savedVol   = parseFloat(localStorage.getItem('hoshimi_volume') || '1');
        const savedMuted = localStorage.getItem('hoshimi_muted') === 'true';
        if (!isNaN(savedVol)) player.volume(savedVol);
        if (savedMuted)       player.muted(true);

        player.one('loadedmetadata', function () {
            // Resume (animes + films)
            const data = HoshimiStorage.Progress.get(PROGRESS_KEY);
            const hasResume = data.position > MIN_SAVE_SEC && !data.completed;
            if (hasResume) {
                player.currentTime(data.position);
            }

            // Sous-titres
            if (SUBTITLES.length) {
                const tracks = player.textTracks();
                for (let i = 0; i < tracks.length; i++) {
                    const track = tracks[i];
                    if (track.kind !== 'subtitles') continue;
                    const meta = SUBTITLES.find(s => s.label === track.label);
                    track.mode = (meta && meta.show) ? 'showing' : 'disabled';
                }
            }

            // Autoplay uniquement s'il n'y a pas de position à reprendre
            if (!hasResume) {
                const playPromise = player.play();
                if (playPromise !== undefined) {
                    playPromise.catch(function () {
                        player.muted(true);
                        player.play();
                    });
                }
            }
        });

        initOverlay();
    });

    // ── Overlay custom ─────────────────────────────────────────────────────────
    function initOverlay() {
        const wrapper   = document.getElementById('hpl-wrapper');
        const overlay   = document.getElementById('hpl-overlay');
        const clickZone = document.getElementById('hpl-click-zone');

        // Éléments de progression
        const progressWrap = document.getElementById('hpl-progress-wrap');
        const bufferEl     = document.getElementById('hpl-buffer');
        const playedEl     = document.getElementById('hpl-played');
        const thumbEl      = document.getElementById('hpl-thumb');

        // Boutons
        const playBtn    = document.getElementById('hpl-play-btn');
        const back10Btn  = document.getElementById('hpl-back10');
        const fwd10Btn   = document.getElementById('hpl-fwd10');
        const muteBtn    = document.getElementById('hpl-mute-btn');
        const volWrap    = document.getElementById('hpl-vol-wrap');
        const volFill    = document.getElementById('hpl-vol-fill');
        const volThumb   = document.getElementById('hpl-vol-thumb');
        const timeEl     = document.getElementById('hpl-time');
        const settingsBtn= document.getElementById('hpl-settings-btn');
        const fsBtn      = document.getElementById('hpl-fs-btn');

        // Icônes
        const iconPlay   = playBtn?.querySelector('.hpl-icon-play');
        const iconPause  = playBtn?.querySelector('.hpl-icon-pause');
        const iconVolHigh = muteBtn?.querySelector('.hpl-icon-vol-high');
        const iconVolLow  = muteBtn?.querySelector('.hpl-icon-vol-low');
        const iconMuted   = muteBtn?.querySelector('.hpl-icon-muted');
        const iconFsEnter= fsBtn?.querySelector('.hpl-icon-fs-enter');
        const iconFsExit = fsBtn?.querySelector('.hpl-icon-fs-exit');

        // Panneau settings
        const settingsPanel  = document.getElementById('hpl-settings-panel');
        const menuMain       = document.getElementById('hpl-menu-main');
        const menuSubs       = document.getElementById('hpl-menu-subtitles');
        const menuSpeed      = document.getElementById('hpl-menu-speed');
        const menuAudio      = document.getElementById('hpl-menu-audio');
        const curSubEl       = document.getElementById('hpl-cur-sub');
        const curSpeedEl     = document.getElementById('hpl-cur-speed');
        const curAudioEl     = document.getElementById('hpl-cur-audio');

        let _activeSetting = { sub: false, speed: false };
        const updateSettingsDot = () => {
            settingsBtn?.classList.toggle('has-active-setting', _activeSetting.sub || _activeSetting.speed);
        };

        if (!overlay || !wrapper) return;

        // ── Utilitaires ──
        function fmtTime(s) {
            s = Math.floor(s || 0);
            const h = Math.floor(s / 3600);
            const m = Math.floor((s % 3600) / 60);
            const sec = String(s % 60).padStart(2, '0');
            return h ? h + ':' + String(m).padStart(2, '0') + ':' + sec : m + ':' + sec;
        }

        function showIcon(show, hide) { if (show) show.style.display = ''; if (hide) hide.style.display = 'none'; }

        // ── Idle (masquage automatique) ──
        let idleTimer;
        function resetIdle() {
            overlay.classList.remove('is-idle');
            clearTimeout(idleTimer);
            if (!player.paused()) {
                idleTimer = setTimeout(() => overlay.classList.add('is-idle'), 3000);
            }
        }

        overlay.addEventListener('mousemove', resetIdle);
        overlay.addEventListener('mousedown', resetIdle);
        overlay.addEventListener('keydown',   resetIdle);

        player.on('play',  resetIdle);
        player.on('pause', () => { clearTimeout(idleTimer); overlay.classList.remove('is-idle'); });

        // ── Play / Pause ──
        function updatePlayIcon() {
            if (player.paused()) {
                showIcon(iconPlay, iconPause);
            } else {
                showIcon(iconPause, iconPlay);
            }
        }

        clickZone?.addEventListener('click', () => player.paused() ? player.play() : player.pause());
        playBtn?.addEventListener('click', () => player.paused() ? player.play() : player.pause());
        player.on('play',  updatePlayIcon);
        player.on('pause', updatePlayIcon);

        // ── Skip ──
        back10Btn?.addEventListener('click', () => player.currentTime(Math.max(player.currentTime() - 10, 0)));
        fwd10Btn?.addEventListener('click',  () => player.currentTime(Math.min(player.currentTime() + 10, player.duration())));

        // ── Volume ──
        function updateVolBar(vol) {
            const pct = Math.round(vol * 100) + '%';
            if (volFill)  volFill.style.width = pct;
            if (volThumb) volThumb.style.left  = pct;
        }

        function updateVolIcon() {
            const muted = player.muted() || player.volume() === 0;
            const low   = !muted && player.volume() < 0.5;
            if (iconVolHigh) iconVolHigh.style.display = (!muted && !low) ? '' : 'none';
            if (iconVolLow)  iconVolLow.style.display  = low ? '' : 'none';
            if (iconMuted)   iconMuted.style.display   = muted ? '' : 'none';
            updateVolBar(muted ? 0 : player.volume());
        }

        muteBtn?.addEventListener('click', () => { player.muted(!player.muted()); updateVolIcon(); });

        // Seek volume sur la barre custom
        let isVolSeeking = false;
        function volFromEvent(e) {
            const rect = volWrap.getBoundingClientRect();
            return Math.max(0, Math.min((e.clientX - rect.left) / rect.width, 1));
        }
        volWrap?.addEventListener('mousedown', e => {
            isVolSeeking = true;
            const v = volFromEvent(e);
            player.volume(v); player.muted(v === 0); updateVolIcon();
        });
        document.addEventListener('mousemove', e => {
            if (!isVolSeeking) return;
            const v = volFromEvent(e);
            player.volume(v); player.muted(v === 0); updateVolIcon();
        });
        document.addEventListener('mouseup', () => { isVolSeeking = false; });

        player.on('volumechange', updateVolIcon);

        // Init
        const savedVol2   = parseFloat(localStorage.getItem('hoshimi_volume') || '1');
        const savedMuted2 = localStorage.getItem('hoshimi_muted') === 'true';
        updateVolBar(savedMuted2 ? 0 : (isNaN(savedVol2) ? 1 : savedVol2));
        updateVolIcon();

        // ── Progression ──
        function updateProgress() {
            const dur = player.duration() || 1;
            const cur = player.currentTime() || 0;
            const pct = Math.min(cur / dur * 100, 100);
            if (playedEl) playedEl.style.width = pct + '%';
            if (thumbEl)  thumbEl.style.left   = pct + '%';
            if (timeEl)   timeEl.textContent    = fmtTime(cur) + ' / ' + fmtTime(dur);

            // Buffer
            if (bufferEl && player.buffered().length) {
                const bEnd = player.buffered().end(player.buffered().length - 1);
                bufferEl.style.width = Math.min(bEnd / dur * 100, 100) + '%';
            }
        }

        player.on('timeupdate',  updateProgress);
        player.on('durationchange', updateProgress);
        player.on('progress',    updateProgress);

        // Seek au clic sur la barre + tooltip
        const thumbTooltip = document.getElementById('hpl-thumb-tooltip');
        let isSeeking = false;

        function pctFromEvent(e) {
            const rect = progressWrap.getBoundingClientRect();
            return Math.max(0, Math.min((e.clientX - rect.left) / rect.width, 1));
        }

        function seekFromEvent(e) {
            player.currentTime(pctFromEvent(e) * player.duration());
        }

        function updateTooltipFromEvent(e) {
            if (!thumbTooltip) return;
            const pct  = pctFromEvent(e);
            const time = pct * (player.duration() || 0);
            thumbTooltip.textContent = fmtTime(time);
            thumbTooltip.style.left  = (pct * 100) + '%';
        }

        progressWrap?.addEventListener('mousemove', updateTooltipFromEvent);
        progressWrap?.addEventListener('mousedown', e => {
            isSeeking = true;
            seekFromEvent(e);
        });
        document.addEventListener('mousemove', e => {
            if (isSeeking) { seekFromEvent(e); updateTooltipFromEvent(e); }
        });
        document.addEventListener('mouseup', () => { isSeeking = false; });

        // ── Plein écran ──
        function updateFsIcon() {
            const isFs = !!document.fullscreenElement;
            showIcon(isFs ? iconFsExit : iconFsEnter, isFs ? iconFsEnter : iconFsExit);
        }

        fsBtn?.addEventListener('click', () => {
            if (document.fullscreenElement) {
                document.exitFullscreen();
            } else {
                wrapper.requestFullscreen();
            }
        });

        document.addEventListener('fullscreenchange', () => {
            updateFsIcon();
            const vjsEl = wrapper.querySelector('.video-js');
            if (document.fullscreenElement) {
                vjsEl.style.setProperty('padding-bottom', '0', 'important');
                vjsEl.style.setProperty('height', '100vh', 'important');
            } else {
                vjsEl.style.removeProperty('padding-bottom');
                vjsEl.style.removeProperty('height');
            }
        });

        // ── Liste d'épisodes (bandeau latéral) ──
        const eplistBtn      = document.getElementById('hpl-eplist-btn');
        const eplistPanel    = document.getElementById('hpl-eplist-panel');
        const eplistClose    = document.getElementById('hpl-eplist-close');
        const eplistBody     = document.getElementById('hpl-eplist-body');
        const seasonSelect   = document.getElementById('hpl-eplist-season-select');
        const seasonBtn      = document.getElementById('hpl-eplist-season-btn');
        const seasonBtnLabel = document.getElementById('hpl-eplist-season-btn-label');
        const seasonBtnCount = document.getElementById('hpl-eplist-season-btn-count');

        function closeEplist() { eplistPanel?.classList.remove('is-open'); seasonSelect?.classList.remove('is-open'); }
        function openEplist()  {
            eplistPanel?.classList.add('is-open');
            closeSettings();
            const active = eplistPanel?.querySelector('.hpl-eplist-card.is-active');
            if (active) setTimeout(() => active.scrollIntoView({ block: 'center' }), 50);
        }

        eplistBtn?.addEventListener('click', e => {
            e.stopPropagation();
            eplistPanel?.classList.contains('is-open') ? closeEplist() : openEplist();
        });
        eplistClose?.addEventListener('click', e => { e.stopPropagation(); closeEplist(); });
        eplistPanel?.addEventListener('click', e => e.stopPropagation());

        // Dropdown de sélection de saison
        seasonBtn?.addEventListener('click', e => {
            e.stopPropagation();
            seasonSelect?.classList.toggle('is-open');
        });
        seasonSelect?.querySelectorAll('[data-season]').forEach(btn => {
            btn.addEventListener('click', e => {
                e.stopPropagation();
                const sn = btn.dataset.season;

                eplistPanel.querySelectorAll('.hpl-eplist-season-block').forEach(block => {
                    block.classList.toggle('is-visible', block.dataset.season === sn);
                });
                seasonSelect.querySelectorAll('[data-season]').forEach(b => b.classList.toggle('is-active', b === btn));

                if (seasonBtnLabel) seasonBtnLabel.textContent = btn.dataset.seasonLabel;
                if (seasonBtnCount) seasonBtnCount.textContent = btn.dataset.seasonCount;

                seasonSelect.classList.remove('is-open');
                eplistBody.scrollTop = 0;
            });
        });
        document.addEventListener('click', () => seasonSelect?.classList.remove('is-open'));

        document.addEventListener('click', closeEplist);
        eplistPanel?.addEventListener('click', e => e.stopPropagation());

        // ── Settings — navigation entre sous-menus ──
        function showMenu(id, direction) {
            const incoming = document.getElementById('hpl-menu-' + id);
            [menuMain, menuSubs, menuSpeed, menuAudio].forEach(m => {
                if (!m) return;
                if (m === incoming) {
                    m.classList.add('is-visible');
                    if (direction) {
                        const animClass = direction === 'forward' ? 'anim-from-right' : 'anim-from-left';
                        m.classList.add(animClass);
                        m.addEventListener('animationend', () => m.classList.remove(animClass), { once: true });
                    }
                } else {
                    m.classList.remove('is-visible');
                }
            });
        }

        function openSettings() {
            settingsPanel?.classList.add('is-open');
            showMenu('main');
        }

        function closeSettings() {
            settingsPanel?.classList.remove('is-open');
            showMenu('main');
        }

        settingsBtn?.addEventListener('click', e => {
            e.stopPropagation();
            settingsPanel?.classList.contains('is-open') ? closeSettings() : (closeEplist(), openSettings());
        });

        settingsPanel?.querySelectorAll('[data-submenu]').forEach(btn => {
            btn.addEventListener('click', e => {
                e.stopPropagation();
                showMenu(btn.dataset.submenu, 'forward');
            });
        });

        settingsPanel?.querySelectorAll('[data-back]').forEach(btn => {
            btn.addEventListener('click', e => {
                e.stopPropagation();
                showMenu(btn.dataset.back, 'back');
            });
        });

        // Fermer sur clic extérieur
        document.addEventListener('click', closeSettings);
        settingsPanel?.addEventListener('click', e => e.stopPropagation());

        // ── Sous-titres via le panneau ──
        function selectSubtitle(idx) {
            const tracks = player.textTracks();
            for (let i = 0; i < tracks.length; i++) {
                if (tracks[i].kind !== 'subtitles') continue;
                const meta = SUBTITLES[idx];
                tracks[i].mode = (meta && tracks[i].label === meta.label) ? 'showing' : 'disabled';
            }
            settingsPanel?.querySelectorAll('[data-track-idx]').forEach(b => {
                b.classList.toggle('is-active', parseInt(b.dataset.trackIdx, 10) === idx);
            });
            if (curSubEl) curSubEl.textContent = idx === -1 ? 'Désactivés' : (SUBTITLES[idx]?.label ?? '');
            _activeSetting.sub = idx !== -1;
            updateSettingsDot();
        }

        settingsPanel?.querySelectorAll('[data-track-idx]').forEach(btn => {
            btn.addEventListener('click', () => {
                selectSubtitle(parseInt(btn.dataset.trackIdx, 10));
                showMenu('main', 'back');
            });
        });

        // ── Audio via le panneau ──
        const AUDIO_TRACKS = cfg.audioTracks || [];
        const STREAM_URL   = cfg.streamUrl   || '';
        settingsPanel?.querySelectorAll('[data-audio-idx]').forEach(btn => {
            btn.addEventListener('click', () => {
                const idx      = parseInt(btn.dataset.audioIdx, 10);
                const wasPaused = player.paused();
                const resumeAt  = player.currentTime();
                const sep       = STREAM_URL.includes('?') ? '&' : '?';
                player.src({ src: STREAM_URL + sep + 'audio=' + idx, type: player.currentType() });
                player.one('loadedmetadata', function () {
                    player.currentTime(resumeAt);
                    if (!wasPaused) player.play();
                });
                settingsPanel?.querySelectorAll('[data-audio-idx]').forEach(b => b.classList.remove('is-active'));
                btn.classList.add('is-active');
                const meta = AUDIO_TRACKS.find(t => t.index === idx);
                if (curAudioEl) curAudioEl.textContent = meta?.label ?? '';

                // Audio non-FR → active automatiquement les sous-titres FR (si dispo)
                // Audio FR → désactive les sous-titres (plus besoin)
                if (meta?.lang && meta.lang !== 'fr') {
                    const frSubIdx = SUBTITLES.findIndex(s => s.lang === 'fr');
                    if (frSubIdx !== -1) selectSubtitle(frSubIdx);
                } else if (meta?.lang === 'fr') {
                    selectSubtitle(-1);
                }
                showMenu('main', 'back');
            });
        });

        // ── Vitesse via le panneau ──
        settingsPanel?.querySelectorAll('[data-speed]').forEach(btn => {
            btn.addEventListener('click', () => {
                const rate = parseFloat(btn.dataset.speed);
                player.playbackRate(rate);
                settingsPanel?.querySelectorAll('[data-speed]').forEach(b => b.classList.remove('is-active'));
                btn.classList.add('is-active');
                if (curSpeedEl) curSpeedEl.textContent = rate === 1 ? 'Normal' : '×' + btn.dataset.speed;
                _activeSetting.speed = rate !== 1;
                updateSettingsDot();
                showMenu('main', 'back');
            });
        });

        // Init du label sous-titres actif
        if (curSubEl) {
            const activeSub = SUBTITLES.find(s => s.show);
            curSubEl.textContent = activeSub ? activeSub.label : 'Désactivés';
            if (activeSub) {
                const idx = SUBTITLES.indexOf(activeSub);
                settingsPanel?.querySelectorAll('[data-track-idx]').forEach(b => {
                    b.classList.toggle('is-active', parseInt(b.dataset.trackIdx, 10) === idx);
                });
                _activeSetting.sub = true;
                updateSettingsDot();
            }
        }
    }


    player.on('volumechange', function () {
        localStorage.setItem('hoshimi_volume', player.volume());
        localStorage.setItem('hoshimi_muted',  player.muted());
    });

    // ── Sauvegarde périodique ─────────────────────────────────────────────────
    const MIN_SAVE_SEC = 3; // seuil minimum avant de sauvegarder
    let saveInterval;
    let hasPlayed = false;

    player.on('timeupdate', function () {
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

    player.on('play', function () {
        hasPlayed = true;
        if (!saveInterval) {
            saveInterval = setInterval(saveProgress, 1000);
        }
    });
    player.on('pause', function () {
        clearInterval(saveInterval);
        saveInterval = null;
        if (hasPlayed) saveProgress();
    });
    player.on('ended', onVideoEnded);

    function saveProgress(completed) {
        const currentTime = player.currentTime();
        if (currentTime < MIN_SAVE_SEC) return;
        HoshimiStorage.Progress.save(
            PROGRESS_KEY,
            currentTime,
            player.duration() || 0,
            completed === true
        );
    }

    function onVideoEnded() {
        saveProgress(true);
        document.dispatchEvent(new CustomEvent('hoshimi:video-ended'));
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
            case 'f': {
                const w = document.getElementById('hpl-wrapper');
                document.fullscreenElement ? document.exitFullscreen() : w?.requestFullscreen();
                break;
            }
            case 'n':
                if (window.HOSHIMI_NEXT_URL) window.location.href = window.HOSHIMI_NEXT_URL;
                break;
        }
    });

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

    // ── Curseur auto-hide ─────────────────────────────────────────────────────
    (function () {
        const wrapper = document.getElementById('hpl-wrapper');
        if (!wrapper) return;
        let timer;
        function showCursor() {
            wrapper.classList.remove('cursor-hidden');
            clearTimeout(timer);
            timer = setTimeout(() => { wrapper.classList.add('cursor-hidden'); }, 3000);
        }
        wrapper.addEventListener('mousemove', showCursor);
        wrapper.addEventListener('mousedown', showCursor);
    })();

})();
