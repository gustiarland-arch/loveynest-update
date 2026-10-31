// Photobooth v2: stickers you place yourself (drag, size, turn) on top of the finished picture: the two LoveyNest
// characters in several moods, emoji and small text badges. Positions are fractions of the picture (0..1), so the
// same placement works for the small preview and the full-size export.
import { coupleImage } from '@/lib/couple';

export const FREE_STICKERS = [
    { key: 'boy-love', type: 'char', kind: 'boy', mood: 'love', arms: 'wave' },
    { key: 'girl-love', type: 'char', kind: 'girl', mood: 'love', arms: 'wave' },
    { key: 'boy-cheer', type: 'char', kind: 'boy', mood: 'cheer', arms: 'up' },
    { key: 'girl-cheer', type: 'char', kind: 'girl', mood: 'cheer', arms: 'up' },
    { key: 'boy-kiss', type: 'char', kind: 'boy', mood: 'kiss', arms: 'hug' },
    { key: 'girl-kiss', type: 'char', kind: 'girl', mood: 'kiss', arms: 'hug' },
    { key: 'boy-wink', type: 'char', kind: 'boy', mood: 'wink', arms: 'down' },
    { key: 'girl-wink', type: 'char', kind: 'girl', mood: 'wink', arms: 'down' },
    ...['💖', '💕', '✨', '🌸', '🎀', '⭐', '🍓', '🥰', '😘', '💌', '🧸', '🌈', '🦋', '🔥', '👑', '🫶'].map((e) => ({ key: `e-${e}`, type: 'emoji', text: e })),
    ...['I ❤ U', 'KITA!', 'Sayang', 'Forever', 'Muach!', 'Bestie'].map((text) => ({ key: `t-${text}`, type: 'text', text })),
];

export const stickerByKey = (key) => FREE_STICKERS.find((s) => s.key === key);

export function newPlaced(key, index = 0) {
    return { id: `${Date.now().toString(36)}${Math.random().toString(36).slice(2, 6)}`, key, x: 0.5 + ((index % 3) - 1) * 0.12, y: 0.5 + ((index % 2) - 0.5) * 0.12, s: 1, r: 0 };
}

/** Waits until the character pictures are ready, so drawing is never missing one. */
export function preloadPlaced(items = []) {
    const waits = [];
    for (const item of items) {
        const st = stickerByKey(item.key);
        if (st?.type !== 'char') continue;
        const img = coupleImage(st.kind, st.mood, st.arms);
        if (!img.complete) waits.push(new Promise((resolve) => ((img.onload = resolve), (img.onerror = resolve))));
    }
    return Promise.all(waits);
}

/** Size of a placed sticker in canvas pixels (before its own scale). */
export const baseSize = (w, h) => Math.min(w, h) * 0.24;

export function drawPlaced(ctx, w, h, items = []) {
    for (const item of items) {
        const st = stickerByKey(item.key);
        if (!st) continue;
        const size = baseSize(w, h) * item.s;
        ctx.save();
        ctx.translate(item.x * w, item.y * h);
        ctx.rotate((item.r * Math.PI) / 180);
        if (st.type === 'char') {
            const img = coupleImage(st.kind, st.mood, st.arms);
            if (img.complete && img.naturalWidth) {
                ctx.shadowColor = 'rgba(40,20,40,0.25)';
                ctx.shadowBlur = size * 0.08;
                ctx.shadowOffsetY = size * 0.04;
                ctx.drawImage(img, -size / 2, -size / 2, size, size);
            }
        } else if (st.type === 'emoji') {
            ctx.font = `${Math.round(size * 0.78)}px "Apple Color Emoji","Segoe UI Emoji","Noto Color Emoji",sans-serif`;
            ctx.textAlign = 'center';
            ctx.textBaseline = 'middle';
            ctx.fillText(st.text, 0, size * 0.04);
        } else {
            const fs = Math.round(size * 0.3);
            ctx.font = `800 ${fs}px "Plus Jakarta Sans Variable","Inter Variable",system-ui,sans-serif`;
            const tw = ctx.measureText(st.text).width;
            const pw = tw + fs * 1.2;
            const ph = fs * 1.7;
            ctx.fillStyle = '#ffffff';
            ctx.strokeStyle = '#e0457a';
            ctx.lineWidth = Math.max(2, fs * 0.12);
            ctx.beginPath();
            ctx.roundRect ? ctx.roundRect(-pw / 2, -ph / 2, pw, ph, ph / 2) : ctx.rect(-pw / 2, -ph / 2, pw, ph);
            ctx.fill();
            ctx.stroke();
            ctx.fillStyle = '#e0457a';
            ctx.textAlign = 'center';
            ctx.textBaseline = 'middle';
            ctx.fillText(st.text, 0, fs * 0.05);
        }
        ctx.restore();
    }
}
