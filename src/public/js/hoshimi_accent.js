/**
 * HOSHIMI — Accent couleur dynamique
 *
 * Lit coverImage.color depuis le JSON AniList de l'anime courant
 * et surcharge la CSS variable --color-accent sur <html>.
 *
 * Usage :
 *   setAccentFromAnime(animeJson);   // objet JSON AniList complet
 *   setAccentColor('#e85d9a');        // couleur directe
 *   resetAccentColor();              // revenir au jaune par défaut
 */

const ACCENT_DEFAULT = '#F7D622';
const ACCENT_DEFAULT_RGB = '247, 214, 34';

/**
 * Convertit un hex #RRGGBB en "R, G, B" pour les rgba()
 */
function hexToRgb(hex) {
    const clean = hex.replace('#', '');
    const r = parseInt(clean.substring(0, 2), 16);
    const g = parseInt(clean.substring(2, 4), 16);
    const b = parseInt(clean.substring(4, 6), 16);
    if (isNaN(r) || isNaN(g) || isNaN(b)) return null;
    return `${r}, ${g}, ${b}`;
}

/**
 * Calcule la luminance relative d'une couleur hex.
 * Retourne true si la couleur est trop sombre (< seuil)
 * et qu'il faut fallback sur le jaune par défaut.
 */
function isTooLight(hex, threshold = 0.7) {
    const rgb = hexToRgb(hex);
    if (!rgb) return false;
    const [r, g, b] = rgb.split(',').map(v => {
        const c = parseInt(v.trim()) / 255;
        return c <= 0.03928 ? c / 12.92 : Math.pow((c + 0.055) / 1.055, 2.4);
    });
    const luminance = 0.2126 * r + 0.7152 * g + 0.0722 * b;
    return luminance > threshold;
}

/**
 * Applique une couleur accent sur :root
 */
function setAccentColor(hex) {
    if (!hex || typeof hex !== 'string') {
        resetAccentColor();
        return;
    }

    // Fallback si couleur trop claire (blanc cassé, jaune pâle…)
    const color = isTooLight(hex) ? ACCENT_DEFAULT : hex;
    const rgb = hexToRgb(color) || ACCENT_DEFAULT_RGB;

    const root = document.documentElement;
    root.style.setProperty('--color-accent', color);
    root.style.setProperty('--color-accent-rgb', rgb);
    root.style.setProperty('--color-accent-dim', `rgba(${rgb}, 0.15)`);
    root.style.setProperty('--color-accent-glow', `rgba(${rgb}, 0.25)`);
}

/**
 * Applique la couleur depuis un objet JSON AniList complet
 */
function setAccentFromAnime(animeData) {
    const color = animeData?.coverImage?.color ?? null;
    setAccentColor(color);
}

/**
 * Réinitialise l'accent (retour au jaune Hoshimi)
 */
function resetAccentColor() {
    const root = document.documentElement;
    root.style.setProperty('--color-accent', ACCENT_DEFAULT);
    root.style.setProperty('--color-accent-rgb', ACCENT_DEFAULT_RGB);
    root.style.setProperty('--color-accent-dim', `rgba(${ACCENT_DEFAULT_RGB}, 0.15)`);
    root.style.setProperty('--color-accent-glow', `rgba(${ACCENT_DEFAULT_RGB}, 0.25)`);
}

// ----------------------------------------------------------------
// Exemple d'utilisation depuis PHP via une balise <script> inline :
//
//   <script>
//     const animeData = <?= json_encode($animeJson) ?>;
//     setAccentFromAnime(animeData);
//   </script>
// ----------------------------------------------------------------