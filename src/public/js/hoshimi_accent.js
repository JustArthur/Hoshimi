/**
 * HOSHIMI — Accent couleur dynamique
 *
 * Stratégie :
 *   1. AniList  → coverImage.color (fourni directement)
 *   2. Fallback → extraction Canvas depuis l'image de cover
 *
 * Usage :
 *   setAccentFromAnime(animeJson);          // objet metadata complet
 *   setAccentFromImage(imgElement);         // extraction Canvas
 *   setAccentColor('#e85d9a');              // couleur directe
 *   resetAccentColor();                     // retour au défaut
 */

const ACCENT_DEFAULT     = '#F7D622';
const ACCENT_DEFAULT_RGB = '247, 214, 34';

// ── Utilitaires couleur ────────────────────────────────────────────────────────

function hexToRgb(hex) {
    const clean = hex.replace('#', '');
    const r = parseInt(clean.substring(0, 2), 16);
    const g = parseInt(clean.substring(2, 4), 16);
    const b = parseInt(clean.substring(4, 6), 16);
    return isNaN(r) || isNaN(g) || isNaN(b) ? null : `${r}, ${g}, ${b}`;
}

function rgbToHsl(r, g, b) {
    r /= 255; g /= 255; b /= 255;
    const max = Math.max(r, g, b), min = Math.min(r, g, b);
    let h = 0, s = 0;
    const l = (max + min) / 2;
    if (max !== min) {
        const d = max - min;
        s = l > 0.5 ? d / (2 - max - min) : d / (max + min);
        switch (max) {
            case r: h = ((g - b) / d + (g < b ? 6 : 0)) / 6; break;
            case g: h = ((b - r) / d + 2) / 6; break;
            case b: h = ((r - g) / d + 4) / 6; break;
        }
    }
    return { h, s, l };
}

function luminance(hex) {
    const rgb = hexToRgb(hex);
    if (!rgb) return 0;
    const [r, g, b] = rgb.split(',').map(v => {
        const c = parseInt(v.trim()) / 255;
        return c <= 0.03928 ? c / 12.92 : Math.pow((c + 0.055) / 1.055, 2.4);
    });
    return 0.2126 * r + 0.7152 * g + 0.0722 * b;
}

function isTooLight(hex, threshold = 0.7) {
    return luminance(hex) > threshold;
}

// Retourne '#000' ou '#fff' selon le contraste optimal sur ce fond
function accentTextColor(hex) {
    return luminance(hex) > 0.179 ? '#000' : '#fff';
}

function toHex(r, g, b) {
    return '#' + [r, g, b].map(v => v.toString(16).padStart(2, '0')).join('');
}

// ── Application de l'accent ────────────────────────────────────────────────────

function setAccentColor(hex) {
    if (!hex || typeof hex !== 'string') { resetAccentColor(); return; }
    const color = isTooLight(hex) ? ACCENT_DEFAULT : hex;
    const rgb   = hexToRgb(color) || ACCENT_DEFAULT_RGB;
    const root  = document.documentElement;
    root.style.setProperty('--color-accent',      color);
    root.style.setProperty('--color-accent-rgb',  rgb);
    root.style.setProperty('--color-accent-dim',  `rgba(${rgb}, 0.15)`);
    root.style.setProperty('--color-accent-glow', `rgba(${rgb}, 0.25)`);
    root.style.setProperty('--color-accent-text', accentTextColor(color));
}

function resetAccentColor() {
    const root = document.documentElement;
    root.style.setProperty('--color-accent',      ACCENT_DEFAULT);
    root.style.setProperty('--color-accent-rgb',  ACCENT_DEFAULT_RGB);
    root.style.setProperty('--color-accent-dim',  `rgba(${ACCENT_DEFAULT_RGB}, 0.15)`);
    root.style.setProperty('--color-accent-glow', `rgba(${ACCENT_DEFAULT_RGB}, 0.25)`);
    root.style.setProperty('--color-accent-text', accentTextColor(ACCENT_DEFAULT));
}

// ── Extraction Canvas ──────────────────────────────────────────────────────────

/**
 * Extrait la couleur la plus vibrante (saturée + luminance moyenne)
 * d'un élément <img> déjà chargé, via Canvas.
 * Retourne un hex ou null si CORS/erreur.
 */
function extractDominantColor(imgEl) {
    try {
        const SIZE = 64;
        const canvas = document.createElement('canvas');
        canvas.width = canvas.height = SIZE;
        const ctx = canvas.getContext('2d');
        ctx.drawImage(imgEl, 0, 0, SIZE, SIZE);
        const { data } = ctx.getImageData(0, 0, SIZE, SIZE);

        let bestColor = null;
        let bestScore = -1;

        for (let i = 0; i < data.length; i += 4) {
            const r = data[i], g = data[i + 1], b = data[i + 2], a = data[i + 3];
            if (a < 200) continue;

            const { s, l } = rgbToHsl(r, g, b);
            // Privilégie saturation élevée + luminance entre 0.25 et 0.75
            const lScore = 1 - Math.abs(l - 0.45) * 2.2;
            const score  = s * Math.max(0, lScore);

            if (score > bestScore) {
                bestScore = score;
                bestColor = toHex(r, g, b);
            }
        }

        return bestColor;
    } catch {
        return null; // CORS ou autre erreur
    }
}

/**
 * Applique l'accent en extrayant la couleur dominante d'un <img>.
 * Si l'image n'est pas encore chargée, attend le load event.
 */
function setAccentFromImage(imgEl) {
    if (!imgEl) return;

    const apply = () => {
        const color = extractDominantColor(imgEl);
        if (color) setAccentColor(color);
    };

    if (imgEl.complete && imgEl.naturalWidth > 0) {
        apply();
    } else {
        imgEl.addEventListener('load', apply, { once: true });
    }
}

// ── Point d'entrée principal ───────────────────────────────────────────────────

/**
 * Applique l'accent depuis un objet metadata (AniList ou TMDB normalisé).
 * Priorité : coverImage.color (AniList) → extraction Canvas → défaut.
 *
 * @param {object} animeData  Metadata complet
 * @param {string} [coverSelector]  Sélecteur CSS de l'image cover (fallback Canvas)
 */
function setAccentFromAnime(animeData, coverSelector = '.detail-hero__poster-img, [data-cover-img]') {
    // 1. AniList fournit directement la couleur
    const anilistColor = animeData?.coverImage?.color ?? null;
    if (anilistColor) {
        setAccentColor(anilistColor);
        return;
    }

    // 2. Extraction Canvas depuis la cover affichée
    const imgEl = document.querySelector(coverSelector);
    if (imgEl) {
        setAccentFromImage(imgEl);
        return;
    }

    // 3. Fallback défaut
    resetAccentColor();
}
