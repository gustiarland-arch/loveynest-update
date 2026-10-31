// Bottom navigation designs, after the "10 Next-Gen Mobile Navigation Designs" reference sheet. The choice belongs to the account
// (users.nav_style) so it follows the person to the PWA and the Android app; the last choice is also kept on the device so the bar
// does not flash another design while the page loads.
export const NAV_STYLES = ['classic', 'liquid', 'magnetic', 'capsule', 'segmented', 'orbit', 'glass', 'wave', 'neon', 'blob', 'layered', 'luxury'];
export const DEFAULT_NAV_STYLE = 'classic'; // the bar LoveyNest always had; the others are optional
const KEY = 'usandus-nav';

export function isNavStyle(value) {
    return NAV_STYLES.includes(value);
}

export function cachedNavStyle() {
    try {
        const saved = localStorage.getItem(KEY);
        return isNavStyle(saved) ? saved : null;
    } catch {
        return null;
    }
}

export function cacheNavStyle(style) {
    try {
        localStorage.setItem(KEY, style);
    } catch {
        // Private mode: the account value still applies.
    }
}

/** Account value first, then what this device last used, then the default. Old names from earlier versions fall back to the default. */
export function resolveNavStyle(fromAccount) {
    return isNavStyle(fromAccount) ? fromAccount : (cachedNavStyle() ?? DEFAULT_NAV_STYLE);
}
