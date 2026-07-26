/**
 * HoshimiSelect — Custom select dropdown réutilisable.
 *
 * HTML attendu :
 *   <div class="cs-wrap" data-value="VALEUR_INITIALE">
 *     <button class="cs-btn" type="button" aria-haspopup="listbox" aria-expanded="false">
 *       <span class="cs-label">Label affiché</span>
 *       <!-- cs-chevron injecté automatiquement si absent -->
 *     </button>
 *     <ul class="cs-list" role="listbox">
 *       <li class="cs-item [is-active]" data-value="val" role="option" aria-selected="true|false"
 *           [data-href="url"]>          ← optionnel : navigue au clic
 *         <span class="cs-check"></span>
 *         <span class="cs-body">
 *           <span class="cs-item-label">Label</span>
 *         </span>
 *       </li>
 *     </ul>
 *   </div>
 *
 * Événement émis : 'cs:change'  → { detail: { value, item } }
 * Utilisation     : HoshimiSelect.init(el)  ou  HoshimiSelect.initAll()
 */
const HoshimiSelect = (() => {
    const SVG_CHEVRON = `<svg class="cs-chevron" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><polyline points="6 9 12 15 18 9"/></svg>`;
    const SVG_CHECK   = `<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"/></svg>`;

    function open(wrap, btn) {
        // Fermer tous les autres selects ouverts
        document.querySelectorAll('.cs-wrap.is-open').forEach(other => {
            if (other !== wrap) {
                other.classList.remove('is-open');
                other.querySelector('.cs-btn')?.setAttribute('aria-expanded', 'false');
            }
        });
        wrap.classList.add('is-open');
        btn.setAttribute('aria-expanded', 'true');
        const list   = wrap.querySelector('.cs-list');
        const active = list?.querySelector('.cs-item.is-active');
        if (list && active) list.scrollTop = active.offsetTop - list.offsetTop;
    }

    function close(wrap, btn) {
        wrap.classList.remove('is-open');
        btn.setAttribute('aria-expanded', 'false');
    }

    function select(wrap, btn, item) {
        const list = wrap.querySelector('.cs-list');
        if (!list) return;

        list.querySelectorAll('.cs-item').forEach(i => {
            i.classList.remove('is-active');
            i.setAttribute('aria-selected', 'false');
            const chk = i.querySelector('.cs-check');
            if (chk) chk.innerHTML = '';
        });

        item.classList.add('is-active');
        item.setAttribute('aria-selected', 'true');
        const chk = item.querySelector('.cs-check');
        if (chk) chk.innerHTML = SVG_CHECK;

        const value    = item.dataset.value ?? '';
        const labelEl  = wrap.querySelector('.cs-label');
        const labelTxt = item.querySelector('.cs-item-label')?.textContent ?? value;
        if (labelEl) labelEl.textContent = labelTxt;
        wrap.dataset.value = value;

        // Sync dot de couleur dans le bouton si présent
        const btnDot  = wrap.querySelector('.cs-btn .cs-dot');
        const itemDot = item.querySelector('.cs-dot');
        if (btnDot && itemDot) {
            btnDot.className = itemDot.className;
        }

        close(wrap, btn);
        wrap.dispatchEvent(new CustomEvent('cs:change', { bubbles: true, detail: { value, item } }));
    }

    function init(wrap) {
        if (wrap._csInit) return;
        wrap._csInit = true;

        const btn  = wrap.querySelector('.cs-btn');
        const list = wrap.querySelector('.cs-list');
        if (!btn || !list) return;

        // Injecter le chevron s'il est absent
        if (!wrap.querySelector('.cs-chevron')) {
            btn.insertAdjacentHTML('beforeend', SVG_CHEVRON);
        }

        btn.addEventListener('click', e => {
            e.stopPropagation();
            wrap.classList.contains('is-open') ? close(wrap, btn) : open(wrap, btn);
        });

        list.addEventListener('click', e => {
            const item = e.target.closest('.cs-item');
            if (!item) return;
            select(wrap, btn, item);
        });

        // Fermer sur clic extérieur
        document.addEventListener('click', () => close(wrap, btn));
        // Empêcher la propagation depuis l'intérieur du dropdown
        list.addEventListener('click', e => e.stopPropagation());

        // Keyboard : Escape
        wrap.addEventListener('keydown', e => {
            if (e.key === 'Escape') close(wrap, btn);
        });
    }

    function initAll(root = document) {
        root.querySelectorAll('.cs-wrap').forEach(init);
    }

    return { init, initAll, SVG_CHECK };
})();

// ── Global cs-wrap mutual exclusion ──────────────────────────────────────────
// Capture phase : s'exécute avant stopPropagation() des listeners locaux.
// Ferme tous les .cs-wrap.is-open sauf celui du bouton cliqué.
document.addEventListener('click', e => {
    const btn  = e.target.closest('.cs-btn');
    const wrap = btn?.closest('.cs-wrap');
    document.querySelectorAll('.cs-wrap.is-open').forEach(other => {
        if (other !== wrap) {
            other.classList.remove('is-open');
            other.querySelector('.cs-btn')?.setAttribute('aria-expanded', 'false');
        }
    });
}, true);

// Ferme tout si le clic est en dehors d'un .cs-wrap.
document.addEventListener('click', e => {
    if (!e.target.closest('.cs-wrap')) {
        document.querySelectorAll('.cs-wrap.is-open').forEach(wrap => {
            wrap.classList.remove('is-open');
            wrap.querySelector('.cs-btn')?.setAttribute('aria-expanded', 'false');
        });
    }
});
