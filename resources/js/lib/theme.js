const KEY = 'usandus-theme';

export function currentTheme() {
    try {
        const saved = localStorage.getItem(KEY);
        return saved === 'light' || saved === 'dark' ? saved : 'auto';
    } catch {
        return 'auto';
    }
}

export function setTheme(mode) {
    const root = document.documentElement;
    if (mode === 'light' || mode === 'dark') root.setAttribute('data-theme', mode);
    else root.removeAttribute('data-theme');
    setTimeout(syncBackdrop, 0);
    try {
        if (mode === 'auto') localStorage.removeItem(KEY);
        else localStorage.setItem(KEY, mode);
    } catch {
        // Private mode: the choice just lasts for this visit.
    }
}

const GLASS_KEY = 'usandus-glass';

export function currentGlass() {
    try {
        return localStorage.getItem(GLASS_KEY) === 'soft' ? 'soft' : 'liquid';
    } catch {
        return 'liquid';
    }
}

export function setGlass(mode) {
    document.documentElement.setAttribute('data-glass', mode);
    try {
        if (mode === 'soft') localStorage.setItem(GLASS_KEY, 'soft');
        else localStorage.removeItem(GLASS_KEY);
    } catch {
        // Private mode: the choice just lasts for this visit.
    }
}

// Built-in backdrops besides the default photo. Soft so the veil keeps text readable.
export const BACKGROUND_PRESETS = {
    blossom: 'linear-gradient(160deg, #f7b9cf 0%, #fbd9c9 45%, #f3c6e0 100%)',
    lavender: 'linear-gradient(160deg, #cbbdf3 0%, #e7dcfb 50%, #f5c9de 100%)',
    peach: 'linear-gradient(160deg, #ffd0b0 0%, #ffe6cf 45%, #f9bfcf 100%)',
    mint: 'linear-gradient(160deg, #bfe8d6 0%, #e4f5ea 50%, #cfe0f7 100%)',
    night: 'radial-gradient(120% 80% at 20% 10%, #6b4a8f 0%, #2d2147 55%, #160f24 100%)',
};

/** Paints the signed-in user's backdrop; null puts the default photo back (guest pages). */
export function applyBackground(bg) {
    const root = document.documentElement;
    let image = null;
    if (bg?.preset === 'custom' && bg.url) image = `url("${bg.url}")`;
    else if (bg && BACKGROUND_PRESETS[bg.preset]) image = BACKGROUND_PRESETS[bg.preset];
    if (image) root.style.setProperty('--bg-image', image);
    else root.style.removeProperty('--bg-image');
    if (bg?.veil) root.style.setProperty('--veil-adjust', `${bg.veil}%`);
    else root.style.removeProperty('--veil-adjust');
    syncBackdrop();
    try {
        if (image || bg?.veil) localStorage.setItem('usandus-bg', JSON.stringify({ image, veil: bg?.veil ?? 0 }));
        else localStorage.removeItem('usandus-bg');
    } catch {
        // Only a first-paint cache.
    }
}


// ---- Backdrop colour: the status bar / browser bar takes the colour of the page's real top edge, and a dark photo gets a stronger veil ----
const FALLBACK_IMAGE = '/img/background.webp';
let sampling = 0;

function hexToRgb(hex) {
    const n = Number.parseInt(hex.slice(1), 16);
    return [(n >> 16) & 255, (n >> 8) & 255, n & 255];
}
const luminance = ([r, g, b]) => (0.2126 * r + 0.7152 * g + 0.0722 * b) / 255;
const mix = (a, b, amount) => a.map((value, i) => Math.round(value * (1 - amount) + b[i] * amount));
const toHex = (rgb) => `#${rgb.map((v) => v.toString(16).padStart(2, '0')).join('')}`;

function parseCssColor(value) {
    const hex = /^#([0-9a-f]{6})$/i.exec(value.trim());
    if (hex) return hexToRgb(value.trim());
    const rgb = /rgba?\((\d+)[ ,]+(\d+)[ ,]+(\d+)/.exec(value);
    return rgb ? [Number(rgb[1]), Number(rgb[2]), Number(rgb[3])] : [242, 242, 247];
}

/** Top-edge colour and overall brightness of the current backdrop (photo or gradient). */
function measureBackdrop(image) {
    return new Promise((resolve) => {
        const colors = image.match(/#[0-9a-f]{6}/gi);
        if (colors?.length && !image.startsWith('url(')) {
            const all = colors.map(hexToRgb);
            resolve({ top: all[0], light: all.reduce((sum, c) => sum + luminance(c), 0) / all.length });
            return;
        }
        const src = (/url\(["']?([^"')]+)["']?\)/.exec(image) || [])[1] || FALLBACK_IMAGE;
        const img = new Image();
        img.onload = () => {
            try {
                const canvas = document.createElement('canvas');
                canvas.width = 16;
                canvas.height = 16;
                const ctx = canvas.getContext('2d', { willReadFrequently: true });
                ctx.drawImage(img, 0, 0, 16, 16);
                const data = ctx.getImageData(0, 0, 16, 16).data;
                let top = [0, 0, 0];
                let total = 0;
                for (let i = 0; i < 16 * 16; i++) {
                    const px = [data[i * 4], data[i * 4 + 1], data[i * 4 + 2]];
                    total += luminance(px);
                    if (i < 32) top = top.map((v, k) => v + px[k] / 32);
                }
                resolve({ top: top.map(Math.round), light: total / 256 });
            } catch {
                resolve(null);
            }
        };
        img.onerror = () => resolve(null);
        img.src = src;
    });
}

export async function syncBackdrop() {
    const root = document.documentElement;
    const token = ++sampling;
    const style = getComputedStyle(root);
    const image = style.getPropertyValue('--bg-image').trim() || `url("${FALLBACK_IMAGE}")`;
    const cream = parseCssColor(style.getPropertyValue('--color-cream'));
    const dark = root.getAttribute('data-theme') === 'dark' || (!root.getAttribute('data-theme') && matchMedia('(prefers-color-scheme: dark)').matches);
    const measured = await measureBackdrop(image);
    if (token !== sampling) return;
    const veil = Math.min(0.96, Math.max(0, (Number.parseFloat(style.getPropertyValue('--veil')) + Number.parseFloat(style.getPropertyValue('--veil-adjust') || '0')) / 100));
    if (measured) {
        // A dark photo under a light theme would swallow the dark text: keep at least a 62% veil over it.
        if (!dark && measured.light < 0.5) root.style.setProperty('--veil-floor', '62%');
        else root.style.removeProperty('--veil-floor');
    }
    const floor = Number.parseFloat(style.getPropertyValue('--veil-floor') || '0') / 100;
    const top = measured ? toHex(mix(measured.top, cream, Math.max(veil, floor))) : toHex(cream);
    document.querySelectorAll('meta[name="theme-color"]').forEach((meta) => meta.setAttribute('content', top));
}
