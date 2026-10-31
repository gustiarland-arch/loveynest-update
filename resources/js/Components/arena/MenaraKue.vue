<script setup>
// Menara Kue: a cake layer slides from side to side; tap to drop it on the tower. What hangs over the edge is cut off
// and falls, so the tower gets thinner unless you drop it just right ("Sempurna!" keeps the full width, three in a row
// even grows it back). The colours, sprinkles and speed come from the seed, so two phones get the very same cake.
import { onBeforeUnmount, onMounted, ref } from 'vue';
import GameHud from '@/Components/arena/GameHud.vue';
import Face from '@/Components/arena/Face.vue';
import { rng } from '@/lib/rng';
import { sfx } from '@/lib/arenaAudio';

const props = defineProps({
    seed: { type: Number, required: true },
    limit: { type: Number, default: 60000 },
    me: { type: Object, required: true },
    partner: { type: Object, default: null },
});
const emit = defineEmits(['finish']);

const INK = '#3b1d33';
const FLAVOURS = [
    { body: '#ffb3c8', top: '#fff0f5', name: 'stroberi' },
    { body: '#c9a27a', top: '#fff3df', name: 'cokelat susu' },
    { body: '#b9a6ff', top: '#f4efff', name: 'taro' },
    { body: '#9fe3c3', top: '#effff7', name: 'pandan' },
    { body: '#ffd27a', top: '#fffbe8', name: 'vanila' },
    { body: '#8fd3ff', top: '#eef9ff', name: 'bluberi' },
    { body: '#ff9e7a', top: '#fff1e8', name: 'jeruk' },
];

const wrap = ref(null);
const canvas = ref(null);
const score = ref(0);
const layersCount = ref(0);
const secondsLeft = ref(Math.ceil(props.limit / 1000));
const over = ref(false);
const message = ref('');
const streak = ref(0);

const rand = rng(props.seed);
const order = Array.from({ length: 200 }, () => ({ flavour: Math.floor(rand() * FLAVOURS.length), sprinkle: rand(), speed: 0.92 + rand() * 0.16, fromLeft: rand() < 0.5 }));

let ctx = null;
let W = 0;
let H = 0;
let raf = 0;
let last = 0;
let elapsed = 0;
let tower = []; // { x, w, f, sprinkle }
let moving = null; // { x, w, f, dir, speed, sprinkle }
let chunks = []; // falling cut-offs
let sparks = [];
let cam = 0;
let camGoal = 0;
let baseW = 0;
let happy = 0;
let shake = 0;
let hudTimer = 0;
let finishTimer = 0;
let observer = null;

const LH = () => Math.max(22, Math.min(34, H * 0.052));
const plateY = () => H - 56;
const layerY = (i) => plateY() - (i + 1) * LH();

function size() {
    const box = wrap.value.getBoundingClientRect();
    const dpr = Math.min(window.devicePixelRatio || 1, 2);
    const oldW = W;
    W = Math.max(240, box.width);
    H = Math.max(320, box.height);
    canvas.value.width = Math.round(W * dpr);
    canvas.value.height = Math.round(H * dpr);
    ctx = canvas.value.getContext('2d');
    ctx.setTransform(dpr, 0, 0, dpr, 0, 0);
    if (oldW && oldW !== W) {
        const k = W / oldW;
        tower = tower.map((l) => ({ ...l, x: l.x * k, w: l.w * k }));
        if (moving) Object.assign(moving, { x: moving.x * k, w: moving.w * k });
        baseW *= k;
    }
}

function spawn() {
    const i = tower.length;
    const o = order[i % order.length];
    const top = tower[tower.length - 1];
    const speed = W * 0.5 * Math.min(2.3, 1 + i * 0.045) * o.speed;
    moving = { x: o.fromLeft ? -top.w * 0.3 : W - top.w * 0.7, w: top.w, f: o.flavour, dir: o.fromLeft ? 1 : -1, speed, sprinkle: o.sprinkle };
    camGoal = Math.max(0, (i + 1) * LH() - (plateY() - H * 0.38));
}

function burst(x, y, color, text) {
    for (let i = 0; i < 10; i++) sparks.push({ x, y, vx: (Math.random() - 0.5) * 220, vy: -Math.random() * 160 - 40, life: 0.7, color });
    if (text) sparks.push({ x, y: y - 16, vx: 0, vy: -60, life: 1, text, color });
}

function drop() {
    if (!moving || over.value) return;
    const top = tower[tower.length - 1];
    const m = moving;
    const left = Math.max(m.x, top.x);
    const right = Math.min(m.x + m.w, top.x + top.w);
    const y = layerY(tower.length);
    if (right - left <= 2) {
        chunks.push({ x: m.x, y, w: m.w, f: m.f, vx: m.dir * 60, vy: 0, rot: 0, vr: m.dir * 2.5 });
        moving = null;
        sfx('bad');
        navigator.vibrate?.(90);
        end('Kuenya jatuh!');
        return;
    }
    const tolerance = Math.max(4, W * 0.012);
    let layer;
    if (Math.abs(m.x - top.x) <= tolerance) {
        streak.value++;
        let w = top.w;
        if (streak.value >= 3) w = Math.min(baseW, w + 8);
        layer = { x: top.x - (w - top.w) / 2, w, f: m.f, sprinkle: m.sprinkle };
        const bonus = 50 * Math.min(streak.value, 4);
        score.value += 100 + bonus;
        sfx(streak.value >= 3 ? 'star' : 'place');
        happy = 0.8;
        burst(layer.x + layer.w / 2, y, '#ffd54a', streak.value >= 3 ? `Sempurna x${streak.value}!` : 'Sempurna!');
    } else {
        streak.value = 0;
        layer = { x: left, w: right - left, f: m.f, sprinkle: m.sprinkle };
        // the part that hangs over falls off
        if (m.x < left) chunks.push({ x: m.x, y, w: left - m.x, f: m.f, vx: -50, vy: 0, rot: 0, vr: -2.2 });
        if (m.x + m.w > right) chunks.push({ x: right, y, w: m.x + m.w - right, f: m.f, vx: 50, vy: 0, rot: 0, vr: 2.2 });
        score.value += 100;
        sfx('flip');
        burst(layer.x + layer.w / 2, y, FLAVOURS[m.f].body);
    }
    navigator.vibrate?.(12);
    tower.push(layer);
    layersCount.value = tower.length - 1;
    shake = 0.12;
    spawn();
}

function end(text) {
    if (over.value) return;
    over.value = true;
    message.value = text;
    sfx(text === 'Waktu habis!' ? 'win' : 'lose');
    finishTimer = setTimeout(() => emit('finish', { score: score.value }), 1400);
}

function update(dt) {
    elapsed += dt;
    happy = Math.max(0, happy - dt);
    shake = Math.max(0, shake - dt);
    if (!over.value && elapsed * 1000 >= props.limit) end('Waktu habis!');
    if (moving && !over.value) {
        moving.x += moving.dir * moving.speed * dt;
        const minX = -moving.w * 0.35;
        const maxX = W - moving.w * 0.65;
        if (moving.x < minX) {
            moving.x = minX;
            moving.dir = 1;
        } else if (moving.x > maxX) {
            moving.x = maxX;
            moving.dir = -1;
        }
    }
    cam += (camGoal - cam) * Math.min(1, dt * 6);
    for (const c of chunks) {
        c.vy += 1400 * dt;
        c.x += c.vx * dt;
        c.y += c.vy * dt;
        c.rot += c.vr * dt;
    }
    chunks = chunks.filter((c) => c.y + cam < H + 200);
    for (const s of sparks) {
        s.x += s.vx * dt;
        s.y += s.vy * dt;
        s.vy += s.text ? 0 : 420 * dt;
        s.life -= dt;
    }
    sparks = sparks.filter((s) => s.life > 0);
}

function roundRect(x, y, w, h, r) {
    const rr = Math.min(r, w / 2, h / 2);
    ctx.beginPath();
    ctx.moveTo(x + rr, y);
    ctx.arcTo(x + w, y, x + w, y + h, rr);
    ctx.arcTo(x + w, y + h, x, y + h, rr);
    ctx.arcTo(x, y + h, x, y, rr);
    ctx.arcTo(x, y, x + w, y, rr);
    ctx.closePath();
}

function drawLayer(l, y, face = false) {
    const h = LH();
    const fl = FLAVOURS[l.f];
    roundRect(l.x, y, l.w, h, 7);
    ctx.fillStyle = fl.body;
    ctx.fill();
    ctx.lineWidth = 3;
    ctx.strokeStyle = INK;
    ctx.stroke();
    // icing with drips
    ctx.save();
    roundRect(l.x, y, l.w, h, 7);
    ctx.clip();
    ctx.fillStyle = fl.top;
    ctx.beginPath();
    ctx.moveTo(l.x, y);
    ctx.lineTo(l.x + l.w, y);
    ctx.lineTo(l.x + l.w, y + h * 0.32);
    const drips = Math.max(2, Math.floor(l.w / 22));
    for (let d = drips; d >= 0; d--) {
        const dx = l.x + (l.w * d) / drips;
        const deep = h * (0.32 + ((d * 37 + Math.floor(l.sprinkle * 100)) % 5) * 0.06);
        ctx.quadraticCurveTo(dx + l.w / drips / 2, y + deep + 4, dx, y + h * 0.32);
    }
    ctx.closePath();
    ctx.fill();
    // sprinkles
    const colors = ['#ff5c8a', '#ffd54a', '#4fb4ff', '#35c987', '#9f80ff'];
    const n = Math.floor(l.w / 18);
    for (let s = 0; s < n; s++) {
        const sx = l.x + 6 + ((s * 53 + l.sprinkle * 997) % Math.max(1, l.w - 12));
        const sy = y + 3 + ((s * 7) % 4);
        ctx.save();
        ctx.translate(sx, sy);
        ctx.rotate((s * 1.7 + l.sprinkle * 6) % 3);
        ctx.fillStyle = colors[s % colors.length];
        ctx.fillRect(-3, -1, 6, 2.4);
        ctx.restore();
    }
    ctx.restore();
    if (face && l.w > 34) {
        const cx = l.x + l.w / 2;
        const cy = y + h * 0.66;
        ctx.fillStyle = INK;
        if (happy > 0) {
            ctx.lineWidth = 2.4;
            ctx.beginPath();
            ctx.arc(cx - 9, cy - 1, 3.4, Math.PI * 1.1, Math.PI * 1.9);
            ctx.stroke();
            ctx.beginPath();
            ctx.arc(cx + 9, cy - 1, 3.4, Math.PI * 1.1, Math.PI * 1.9);
            ctx.stroke();
            ctx.beginPath();
            ctx.ellipse(cx, cy + 4, 4, 3.4, 0, 0, Math.PI);
            ctx.fill();
        } else {
            ctx.beginPath();
            ctx.arc(cx - 9, cy - 1, 2.6, 0, 6.3);
            ctx.arc(cx + 9, cy - 1, 2.6, 0, 6.3);
            ctx.fill();
            ctx.lineWidth = 2.2;
            ctx.beginPath();
            ctx.arc(cx, cy + 1, 3.6, 0.2, Math.PI - 0.2);
            ctx.stroke();
        }
        ctx.fillStyle = 'rgba(255,111,156,.55)';
        ctx.beginPath();
        ctx.ellipse(cx - 16, cy + 2, 4, 2.4, 0, 0, 6.3);
        ctx.ellipse(cx + 16, cy + 2, 4, 2.4, 0, 0, 6.3);
        ctx.fill();
    }
}

function draw() {
    ctx.clearRect(0, 0, W, H);
    ctx.save();
    if (shake > 0) ctx.translate(0, Math.sin(elapsed * 80) * shake * 10);
    ctx.translate(0, cam);
    // plate
    const py = plateY();
    ctx.fillStyle = '#fff';
    ctx.strokeStyle = INK;
    ctx.lineWidth = 3;
    ctx.beginPath();
    ctx.ellipse(W / 2, py + 10, baseW * 0.72, 14, 0, 0, 6.3);
    ctx.fill();
    ctx.stroke();
    ctx.fillStyle = '#ffd6e4';
    ctx.beginPath();
    ctx.ellipse(W / 2, py + 7, baseW * 0.6, 7, 0, 0, 6.3);
    ctx.fill();
    // tower (only what can be seen)
    const first = Math.max(0, Math.floor((cam - 60) / LH()) - 2);
    for (let i = first; i < tower.length; i++) drawLayer(tower[i], layerY(i), i === tower.length - 1 && !moving);
    if (moving) drawLayer(moving, layerY(tower.length), true);
    for (const c of chunks) {
        ctx.save();
        ctx.translate(c.x + c.w / 2, c.y + LH() / 2);
        ctx.rotate(c.rot);
        drawLayer({ ...c, x: -c.w / 2, sprinkle: 0.3 }, -LH() / 2);
        ctx.restore();
    }
    for (const s of sparks) {
        ctx.globalAlpha = Math.max(0, Math.min(1, s.life * 1.6));
        if (s.text) {
            ctx.font = "24px 'Lilita One', system-ui, sans-serif";
            ctx.textAlign = 'center';
            ctx.lineWidth = 6;
            ctx.lineJoin = 'round';
            ctx.strokeStyle = INK;
            ctx.strokeText(s.text, s.x, s.y);
            ctx.fillStyle = s.color;
            ctx.fillText(s.text, s.x, s.y);
        } else {
            ctx.fillStyle = s.color;
            ctx.beginPath();
            ctx.arc(s.x, s.y, 3.2, 0, 6.3);
            ctx.fill();
        }
    }
    ctx.globalAlpha = 1;
    ctx.restore();
}

function frame(time) {
    raf = requestAnimationFrame(frame);
    const dt = Math.min(0.05, (time - last) / 1000 || 0);
    last = time;
    update(dt);
    draw();
}

function key(event) {
    if (event.code === 'Space' || event.key === 'Enter') {
        event.preventDefault();
        drop();
    }
}

onMounted(() => {
    size();
    baseW = Math.min(W * 0.62, 280);
    tower = [{ x: (W - baseW) / 2, w: baseW, f: order[0].flavour, sprinkle: order[0].sprinkle }];
    spawn();
    observer = new ResizeObserver(() => size());
    observer.observe(wrap.value);
    window.addEventListener('keydown', key);
    hudTimer = setInterval(() => {
        if (!over.value) secondsLeft.value = Math.max(0, Math.ceil((props.limit - elapsed * 1000) / 1000));
    }, 200);
    raf = requestAnimationFrame((t) => {
        last = t;
        frame(t);
    });
});

onBeforeUnmount(() => {
    cancelAnimationFrame(raf);
    clearInterval(hudTimer);
    clearTimeout(finishTimer);
    observer?.disconnect();
    window.removeEventListener('keydown', key);
});
</script>

<template>
    <div ref="wrap" class="relative min-h-[60dvh] flex-1 overflow-hidden" data-testid="kue">
        <canvas ref="canvas" class="absolute inset-0 size-full touch-none" aria-label="Ketuk untuk menjatuhkan lapisan kue" role="button" @pointerdown.prevent="drop" />
        <GameHud :me="me" :score="score" :seconds="secondsLeft" :total="limit / 1000"><div class="hud-chip-x ar-chip tabular-nums" aria-label="Jumlah lapisan">🎂 {{ layersCount }}</div></GameHud>
        <p v-if="layersCount === 0 && !over" class="ar-chip ar-pop pointer-events-none absolute left-1/2 top-24 -translate-x-1/2 whitespace-nowrap text-sm">Ketuk layar saat lapisan pas di atas kue!</p>
        <div v-if="over" class="pointer-events-none absolute inset-0 grid place-items-center"><p class="ar-title ar-outline ar-pop text-center text-5xl text-white">{{ message }}</p></div>
    </div>
</template>
