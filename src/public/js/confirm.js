/* ═══════════════════════════════════════════════════════════════════════════
   HOSHIMI — HoshimiConfirm
   Usage : await HoshimiConfirm.ask('Message ?')
           await HoshimiConfirm.ask('Message ?', { confirm: 'Supprimer', cancel: 'Annuler', type: 'danger' })
   Types : 'danger' | 'warning' | 'default'
   ══════════════════════════════════════════════════════════════════════════ */

(function () {
    let overlay = null;

    const ICONS = {
        danger:  '<svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>',
        warning: '<svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"/><line x1="12" y1="9" x2="12" y2="13"/><line x1="12" y1="17" x2="12.01" y2="17"/></svg>',
        default: '<svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>',
    };

    function build() {
        const el = document.createElement('div');
        el.className = 'qr-modal-overlay hc-overlay';
        el.style.display = 'none';
        el.innerHTML = `
            <div class="qr-modal hc-modal" style="max-width:380px;align-items:flex-start;gap:14px;">
                <div class="hc-header">
                    <span class="hc-icon"></span>
                    <p class="hc-msg"></p>
                </div>
                <div class="hc-actions">
                    <button class="btn btn--ghost hc-cancel" style="border:1px solid var(--color-border);padding:.42rem 1rem;font-size:.83rem;border-radius:var(--radius-sm);">Annuler</button>
                    <button class="btn hc-confirm" style="padding:.42rem 1rem;font-size:.83rem;border-radius:var(--radius-sm);"></button>
                </div>
            </div>`;
        document.body.appendChild(el);
        return el;
    }

    function getOverlay() {
        if (!overlay) overlay = build();
        return overlay;
    }

    function ask(msg, opts = {}) {
        const type        = opts.type    ?? 'default';
        const confirmLabel= opts.confirm ?? 'Confirmer';
        const cancelLabel = opts.cancel  ?? 'Annuler';

        const el         = getOverlay();
        const iconEl     = el.querySelector('.hc-icon');
        const msgEl      = el.querySelector('.hc-msg');
        const cancelBtn  = el.querySelector('.hc-cancel');
        const confirmBtn = el.querySelector('.hc-confirm');

        iconEl.innerHTML    = ICONS[type] ?? ICONS.default;
        iconEl.className    = 'hc-icon hc-icon--' + type;
        msgEl.textContent   = msg;
        cancelBtn.textContent  = cancelLabel;
        confirmBtn.textContent = confirmLabel;

        confirmBtn.className = 'btn hc-confirm' + (type === 'danger' ? ' btn--danger' : type === 'warning' ? ' btn--warning' : ' btn--primary');
        confirmBtn.style.cssText = 'padding:.42rem 1rem;font-size:.83rem;border-radius:var(--radius-sm);';

        el.style.display = 'flex';
        confirmBtn.focus();

        return new Promise(resolve => {
            function done(val) {
                el.style.display = 'none';
                cancelBtn.removeEventListener('click', onCancel);
                confirmBtn.removeEventListener('click', onConfirm);
                el.removeEventListener('click', onBackdrop);
                document.removeEventListener('keydown', onKey);
                resolve(val);
            }
            function onCancel()  { done(false); }
            function onConfirm() { done(true);  }
            function onBackdrop(e) { if (e.target === el) done(false); }
            function onKey(e) { if (e.key === 'Escape') done(false); if (e.key === 'Enter') done(true); }

            cancelBtn.addEventListener('click',   onCancel);
            confirmBtn.addEventListener('click',  onConfirm);
            el.addEventListener('click',          onBackdrop);
            document.addEventListener('keydown',  onKey);
        });
    }

    window.HoshimiConfirm = { ask };
})();
