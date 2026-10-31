// The two LoveyNest characters, drawn after the owner's artwork: a blue one with a cap (laki-laki) and a pink one with
// a flower (perempuan). One rule everywhere (feelings, Arena, widgets, promo): the character of a person follows their
// gender. Without a gender a lilac one without accessory is used, so nobody is shown as something they did not choose.
//
// Drawn as SVG (not the PNG) so faces and arms can change: mood = happy | cheer | kiss | sleepy | sad | wink | oops | think | love
// arms = down | up | hug | hold | wave. Everything sits in a 200x200 box; the groups carry class names that
// CoupleChar.vue animates (breathing, blinking, waving) with transforms only.

export const KINDS = {
    boy: { body: '#a0dafc', shade: '#79c2f5', limb: '#8dcdf8', line: '#242f62', blush: '#fcafcc', shadow: '#cfe3fb', tongue: '#fb82a4' },
    girl: { body: '#fcc1da', shade: '#f6a5c7', limb: '#f9b3d0', line: '#351639', blush: '#f99dbd', shadow: '#fdd6e9', tongue: '#fb82a4' },
    kid: { body: '#d9ccff', shade: '#bfaef5', limb: '#cbbcfb', line: '#33255e', blush: '#fcafcc', shadow: '#e6defd', tongue: '#fb82a4' },
};

/** laki-laki → boy (blue), perempuan → girl (pink), anything else → kid (lilac). */
export function kindOf(gender) {
    return gender === 'laki-laki' ? 'boy' : gender === 'perempuan' ? 'girl' : 'kid';
}

const W = 6; // outline width

function eyes(c, mood, kind) {
    const L = 72;
    const R = 128;
    const Y = 104;
    const open = (x) =>
        `<ellipse cx="${x}" cy="${Y}" rx="12.5" ry="14" fill="${c.line}"/><circle cx="${x + 4}" cy="${Y - 5}" r="5" fill="#fff"/><circle cx="${x - 4.5}" cy="${Y + 5}" r="2.4" fill="#fff"/>`;
    const happy = (x) => `<path d="M${x - 12} ${Y + 4}q12 -16 24 0" fill="none" stroke="${c.line}" stroke-width="${W}" stroke-linecap="round"/>`;
    const shut = (x) => `<path d="M${x - 12} ${Y}q12 12 24 0" fill="none" stroke="${c.line}" stroke-width="${W}" stroke-linecap="round"/>`;
    const sad = (x, s) => `<ellipse cx="${x}" cy="${Y + 2}" rx="11" ry="12" fill="${c.line}"/><circle cx="${x + 3}" cy="${Y - 2}" r="4.4" fill="#fff"/><path d="M${x - 14 * s} ${Y - 18}l${22 * s} -6" stroke="${c.line}" stroke-width="4" stroke-linecap="round"/>`;
    const heart = (x) => `<path d="M${x} ${Y + 12}c-14 -9 -15 -22 -7 -24c4 -1 6 2 7 4c1 -2 3 -5 7 -4c8 2 7 15 -7 24z" fill="#ff4f86" stroke="${c.line}" stroke-width="3" stroke-linejoin="round"/>`;
    const lash = (x, side) =>
        kind === 'girl' ? `<path d="M${x + side * 10} ${Y - 10}q${side * 4} -1 ${side * 6} -6" fill="none" stroke="${c.line}" stroke-width="3.4" stroke-linecap="round"/>` : '';
    let l;
    let r;
    if (mood === 'cheer') [l, r] = [happy(L), happy(R)];
    else if (mood === 'kiss' || mood === 'sleepy') [l, r] = [shut(L), shut(R)];
    else if (mood === 'sad') [l, r] = [sad(L, 1), sad(R, -1)];
    else if (mood === 'wink') [l, r] = [open(L), happy(R)];
    else if (mood === 'love') [l, r] = [heart(L), heart(R)];
    else if (mood === 'think') [l, r] = [open(L).replace(/cy="104"/, 'cy="100"'), open(R).replace(/cy="104"/, 'cy="100"')];
    else [l, r] = [open(L), open(R)];
    const lashes = ['cheer', 'kiss', 'sleepy', 'love'].includes(mood) ? '' : lash(L, -1) + lash(R, 1);
    return `<g class="cc-eyes">${l}${r}${lashes}</g>`;
}

function mouth(c, mood) {
    const m = {
        happy: `<path d="M87 117h26c0 14 -6 20 -13 20s-13 -6 -13 -20z" fill="${c.line}" stroke="${c.line}" stroke-width="3" stroke-linejoin="round"/><path d="M92 131c4 -5 12 -5 16 0c-3 4 -13 4 -16 0z" fill="${c.tongue}"/>`,
        love: `<path d="M87 117h26c0 14 -6 20 -13 20s-13 -6 -13 -20z" fill="${c.line}" stroke="${c.line}" stroke-width="3" stroke-linejoin="round"/><path d="M92 131c4 -5 12 -5 16 0c-3 4 -13 4 -16 0z" fill="${c.tongue}"/>`,
        cheer: `<path d="M82 114h36c0 20 -9 28 -18 28s-18 -8 -18 -28z" fill="${c.line}" stroke="${c.line}" stroke-width="3" stroke-linejoin="round"/><path d="M89 134c6 -7 16 -7 22 0c-4 6 -18 6 -22 0z" fill="${c.tongue}"/>`,
        kiss: `<g class="cc-pucker"><path d="M100 116c-8 -1 -9 6 -3 9c-6 2 -5 10 3 9" fill="none" stroke="${c.line}" stroke-width="${W - 1}" stroke-linecap="round" stroke-linejoin="round"/><circle cx="104" cy="125" r="4" fill="#ff4f86"/></g>`,
        sleepy: `<path d="M93 124q7 5 14 0" fill="none" stroke="${c.line}" stroke-width="${W - 1}" stroke-linecap="round"/>`,
        sad: `<path d="M90 128q10 -9 20 0" fill="none" stroke="${c.line}" stroke-width="${W}" stroke-linecap="round"/>`,
        wink: `<path d="M88 118q12 14 24 0" fill="none" stroke="${c.line}" stroke-width="${W}" stroke-linecap="round"/><path d="M98 125q4 6 8 0" fill="${c.tongue}"/>`,
        oops: `<ellipse cx="100" cy="125" rx="8" ry="10" fill="${c.line}"/><ellipse cx="100" cy="130" rx="4.5" ry="3" fill="${c.tongue}"/>`,
        think: `<path d="M91 124h18" fill="none" stroke="${c.line}" stroke-width="${W}" stroke-linecap="round"/>`,
    };
    return `<g class="cc-mouth">${m[mood] ?? m.happy}</g>`;
}

function arm(c, side, pose) {
    // side: -1 left, 1 right. Poses rotate a rounded arm around its shoulder.
    const x = 100 + side * (pose === 'hold' ? 52 : 70);
    const y = pose === 'hold' ? 136 : 122;
    const rot = { down: side * -12, up: side * -150, wave: side * -140, hug: side * -95, hold: side * 38 }[pose] ?? side * -12;
    const cls = pose === 'wave' && side === 1 ? 'cc-arm cc-wave' : pose === 'up' ? 'cc-arm cc-cheer' : 'cc-arm';
    return `<g class="${cls}" style="transform-origin:${x}px ${y - 8}px"><g transform="rotate(${rot} ${x} ${y - 8})"><ellipse cx="${x}" cy="${y + 10}" rx="14" ry="22" fill="${c.limb}" stroke="${c.line}" stroke-width="${W}"/></g></g>`;
}

function cap(c) {
    return `<g class="cc-acc cc-cap">
        <path d="M27 104C21 52 58 20 106 20c40 0 66 22 70 56l2 10c-30 -10 -78 -6 -116 18z" fill="#4b8ef1" stroke="${c.line}" stroke-width="${W}" stroke-linejoin="round"/>
        <path d="M96 22c36 -4 70 16 77 52c-30 -6 -54 -4 -72 2c-6 -18 -8 -36 -5 -54z" fill="#fff" stroke="${c.line}" stroke-width="${W - 1}" stroke-linejoin="round"/>
        <path d="M95 30q-6 22 2 44" fill="none" stroke="#9fb6e6" stroke-width="2.4" stroke-dasharray="4 5" stroke-linecap="round"/>
        <path d="M58 90c34 -22 84 -26 120 -12c16 6 24 16 19 24c-4 6 -14 4 -28 -2c-34 -14 -74 -8 -103 10z" fill="#3f7ee6" stroke="${c.line}" stroke-width="${W}" stroke-linejoin="round"/>
        <ellipse cx="96" cy="18" rx="13" ry="7" fill="#4b8ef1" stroke="${c.line}" stroke-width="${W - 1}"/>
        <g fill="#3f7ee6"><circle cx="133" cy="52" r="12"/><circle cx="123" cy="41" r="5"/><circle cx="143" cy="41" r="5"/></g>
        <g fill="#fff"><circle cx="129" cy="50" r="1.8"/><circle cx="137" cy="50" r="1.8"/></g><path d="M129 56q4 4 8 0" fill="none" stroke="#fff" stroke-width="2" stroke-linecap="round"/>
        <path d="M44 66q6 -16 18 -24" fill="none" stroke="#fff" stroke-opacity=".55" stroke-width="7" stroke-linecap="round"/>
    </g>`;
}

function flower(c) {
    const petals = [0, 60, 120, 180, 240, 300]
        .map((a) => {
            const r = (a * Math.PI) / 180;
            return `<circle cx="${(150 + Math.cos(r) * 13).toFixed(1)}" cy="${(40 + Math.sin(r) * 13).toFixed(1)}" r="11.5" fill="#fff" stroke="${c.line}" stroke-width="4.5"/>`;
        })
        .join('');
    return `<g class="cc-acc cc-flower" style="transform-origin:150px 40px">
        <path d="M164 56l20 -8c6 -2 9 4 6 9l-10 16c-3 5 -9 4 -10 -1z" fill="#f990b4" stroke="${c.line}" stroke-width="4.5" stroke-linejoin="round"/>
        ${petals}<circle cx="150" cy="40" r="11.5" fill="#fff" stroke="none"/><circle cx="150" cy="40" r="7.5" fill="#fde384" stroke="${c.line}" stroke-width="3"/>
    </g>`;
}

/** Inner SVG (no <svg> wrapper) for a 200x200 viewBox; use viewBox="-12 -8 224 216" so arms and accessories fit. */
export function coupleSvg(kind = 'girl', mood = 'happy', arms = 'down') {
    const c = KINDS[kind] ?? KINDS.kid;
    const front = arms === 'hold';
    const sideArms = arm(c, -1, arms) + arm(c, 1, arms);
    const body = kind === 'boy' ? 'M28 112c0 -48 30 -74 72 -74s72 26 72 74c0 38 -30 62 -72 62s-72 -24 -72 -62z' : 'M26 106c0 -46 32 -76 74 -76s74 30 74 76c0 42 -30 68 -74 68s-74 -26 -74 -68z';
    return `<defs><radialGradient id="cc-g-${kind}" cx=".38" cy=".34" r=".75"><stop offset=".55" stop-color="${c.body}"/><stop offset="1" stop-color="${c.shade}"/></radialGradient></defs><g class="cc">
        <ellipse cx="100" cy="190" rx="66" ry="8" fill="${c.shadow}"/>
        <g class="cc-feet"><ellipse cx="72" cy="178" rx="22" ry="12" fill="${c.limb}" stroke="${c.line}" stroke-width="${W}"/><ellipse cx="128" cy="178" rx="22" ry="12" fill="${c.limb}" stroke="${c.line}" stroke-width="${W}"/></g>
        ${front ? '' : sideArms}
        <g class="cc-body">
            <path d="${body}" fill="url(#cc-g-${kind})" stroke="${c.line}" stroke-width="${W}"/>
            <path d="M44 ${kind === 'boy' ? 118 : 96}q4 -22 22 -34" fill="none" stroke="#fff" stroke-opacity=".7" stroke-width="9" stroke-linecap="round"/>
            <circle cx="42" cy="${kind === 'boy' ? 134 : 114}" r="4" fill="#fff" opacity=".75"/>
            <ellipse cx="52" cy="${kind === 'boy' ? 134 : 126}" rx="15" ry="9.5" fill="${c.blush}" class="cc-blush"/><ellipse cx="148" cy="${kind === 'boy' ? 134 : 126}" rx="15" ry="9.5" fill="${c.blush}" class="cc-blush"/>
            <g ${kind === 'boy' ? 'transform="translate(0 8)"' : ''}>${eyes(c, mood, kind)}${mouth(c, mood)}</g>
            ${kind === 'boy' ? `<g transform="translate(4 -12) scale(.96)">${cap(c)}</g>` : kind === 'girl' ? flower(c) : ''}
        </g>
        ${front ? sideArms : ''}
    </g>`;
}

/** A whole SVG document (for <img> or canvas sprites). */
export function coupleSvgDoc(kind, mood, arms, size = 256) {
    return `<svg xmlns="http://www.w3.org/2000/svg" viewBox="-12 -8 224 216" width="${size}" height="${size}">${coupleSvg(kind, mood, arms)}</svg>`;
}

const cache = new Map();
/** An Image of a character for drawing on a canvas (cached per kind/mood/arms). */
export function coupleImage(kind, mood = 'happy', arms = 'down') {
    const key = `${kind}|${mood}|${arms}`;
    if (!cache.has(key)) {
        const img = new Image();
        img.src = `data:image/svg+xml;charset=utf-8,${encodeURIComponent(coupleSvgDoc(kind, mood, arms))}`;
        cache.set(key, img);
    }
    return cache.get(key);
}
