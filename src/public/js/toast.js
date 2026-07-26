/* ═══════════════════════════════════════════════════════════════════════════
   HOSHIMI — Toast component
   Usage : HoshimiToast.show('Message')
           HoshimiToast.show('Message', { type: 'success' | 'error' | 'warning' | 'info', duration: 4000 })
           HoshimiToast.success('Message')
           HoshimiToast.error('Message')
           HoshimiToast.warning('Message')
           HoshimiToast.info('Message')
   ══════════════════════════════════════════════════════════════════════════ */

(function () {
    const ICONS = {
        success: '<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"/></svg>',
        error:   '<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>',
        warning: '<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"/><line x1="12" y1="9" x2="12" y2="13"/><line x1="12" y1="17" x2="12.01" y2="17"/></svg>',
        info:    '<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>',
    };

    let container = null;

    function getContainer() {
        if (!container) {
            container = document.createElement('div');
            container.className = 'ht-container';
            document.body.appendChild(container);
        }
        return container;
    }

    function show(msg, opts = {}) {
        const type     = opts.type     ?? 'info';
        const duration = opts.duration ?? 4000;

        const wrap = document.createElement('div');
        wrap.className = 'ht ht--' + type;

        wrap.innerHTML = `
            <span class="ht__icon">${ICONS[type] ?? ICONS.info}</span>
            <span class="ht__msg">${String(msg).replace(/</g, '&lt;')}</span>
        `;

        function dismiss() {
            wrap.classList.add('ht--out');
            wrap.addEventListener('animationend', () => wrap.remove(), { once: true });
        }

        if (!window.HOSHIMI_TOAST_DEBUG) setTimeout(dismiss, duration);


        getContainer().appendChild(wrap);

        requestAnimationFrame(() => {
            requestAnimationFrame(() => wrap.classList.add('ht--in'));
        });

        return { dismiss };
    }

    window.HoshimiToast = {
        show,
        success: (msg, opts) => show(msg, { ...opts, type: 'success' }),
        error:   (msg, opts) => show(msg, { ...opts, type: 'error' }),
        warning: (msg, opts) => show(msg, { ...opts, type: 'warning' }),
        info:    (msg, opts) => show(msg, { ...opts, type: 'info' }),
    };

    // Compat avec l'ancien showToast() global
    window.showToast = function (msg, type) {
        const map = { success: 'success', error: 'error', warning: 'warning' };
        show(msg, { type: map[type] ?? 'info' });
    };
})();
