// Photobooth v2: an animated GIF of the round. Each shot is shown in turn inside the theme's colours with the names,
// looping forever, like the little moving pictures of real photo booths. Encoded on the phone (gifenc).
import { GIFEncoder, quantize, applyPalette } from 'gifenc';

export async function makeGif(cells, { bg = '#ffe3ee', ink = '#a63a62', title = '', size = 420, delay = 650 } = {}) {
    const gif = GIFEncoder();
    const canvas = document.createElement('canvas');
    canvas.width = size;
    canvas.height = Math.round(size * 1.18);
    const ctx = canvas.getContext('2d', { willReadFrequently: true });
    const pad = Math.round(size * 0.07);
    const photo = size - pad * 2;
    for (let i = 0; i < cells.length; i++) {
        ctx.fillStyle = bg;
        ctx.fillRect(0, 0, canvas.width, canvas.height);
        ctx.fillStyle = '#fff';
        ctx.fillRect(pad - 6, pad - 6, photo + 12, photo + 12);
        ctx.drawImage(cells[i], pad, pad, photo, photo);
        ctx.fillStyle = ink;
        ctx.font = `700 ${Math.round(size * 0.06)}px "Plus Jakarta Sans Variable",system-ui,sans-serif`;
        ctx.textAlign = 'center';
        ctx.fillText(title, size / 2, pad + photo + (canvas.height - pad - photo) / 2 + size * 0.02);
        ctx.font = `600 ${Math.round(size * 0.034)}px system-ui,sans-serif`;
        ctx.fillText(`${i + 1}/${cells.length} · LoveyNest`, size / 2, canvas.height - pad * 0.45);
        const { data, width, height } = ctx.getImageData(0, 0, canvas.width, canvas.height);
        const palette = quantize(data, 256);
        gif.writeFrame(applyPalette(data, palette), width, height, { palette, delay });
        await new Promise((r) => setTimeout(r, 0)); // keep the page responsive between frames
    }
    gif.finish();
    return new Blob([gif.bytes()], { type: 'image/gif' });
}

/** Share where the phone can, otherwise download. */
export async function shareOrDownload(blob, name) {
    const file = new File([blob], name, { type: blob.type });
    if (navigator.canShare?.({ files: [file] })) {
        try {
            await navigator.share({ files: [file], title: 'LoveyNest Photobooth' });
            return 'shared';
        } catch {
            /* cancelled: fall through to download */
        }
    }
    const url = URL.createObjectURL(blob);
    const a = Object.assign(document.createElement('a'), { href: url, download: name });
    document.body.appendChild(a);
    a.click();
    a.remove();
    setTimeout(() => URL.revokeObjectURL(url), 4000);
    return 'downloaded';
}
