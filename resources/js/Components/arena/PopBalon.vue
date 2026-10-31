<script setup>
// Pop Balon: balloons with little faces float up. Pop the colour that is asked (it changes every few seconds), catch
// the golden heart balloons for a bonus, and leave the grumpy storm clouds alone: three of those and the game is over.
// The balloons and the order of colours come from the seed, so two phones get the same sky.
import { onBeforeUnmount, onMounted, ref, computed } from 'vue';
import GameHud from '@/Components/arena/GameHud.vue';
import Face from '@/Components/arena/Face.vue';
import Sticker from '@/Components/arena/Sticker.vue';
import { bits } from '@/lib/arenaArt';
import { rng, shuffled } from '@/lib/rng';
import { sfx } from '@/lib/arenaAudio';

const props = defineProps({
    seed: { type: Number, required: true },
    limit: { type: Number, default: 45000 },
    me: { type: Object, required: true },
    partner: { type: Object, default: null },
});
const emit = defineEmits(['finish']);

const INK = '#3b1d33';
const COLORS = [
    { key: 'pink', name: 'Pink', fill: '#ff7aa2', light: '#ffc2d4' },
    { key: 'blue', name: 'Biru', fill: '#4f9dff', light: '#b8d8ff' },
    { key: 'yellow', name: 'Kuning', fill: '#ffc83d', light: '#ffe9a8' },
    { key: 'green', name: 'Hijau', fill: '#35c987', light: '#b4f0d4' },
    { key: 'purple', name: 'Ungu', fill: '#9f80ff', light: '#d9ccff' },
];
const SWITCH = 7; // seconds per target colour

const wrap = ref(null);
const canvas = ref(null);
const score = ref(0);
const lives = ref(3);
const combo = ref(0);
const secondsLeft = ref(Math.ceil(props.limit / 1000));
const over = ref(false);
const targetIndex = ref(0);

const total = props.limit / 1000;
const plan = (() => {
    const rand = rng(props.seed);
    const targets = [];
    while (targets.length < Math.ceil(total / SWITCH) + 2) {
        for (const c of shuffled([0, 1, 2, 3, 4], rand)) if (targets[targets.length - 1] !== c) targets.push(c);
    }
    const list = [];
    let t = 0.4;
    while (t < total) {
        const progress = Math.min(t / total, 1);
        const r = rand();
        const slot = Math.floor(t / SWITCH);
        // about 40% of the balloons are the colour being asked, so there is always something to pop
        const type = t > 4 && r < 0.09 ? 'storm' : r > 0.95 ? 'gold' : 'color';
        const color = type !== 'color' ? -1 : rand() < 0.4 ? targets[slot] : Math.floor(rand() * COLORS.length);
        list.push({ t, x: 0.1 + rand() * 0.8, type, color, speed: 0.16 + progress * 0.12 + rand() * 0.05, sway: rand() * 6.28 });
        t += (0.62 - progress * 0.22) * (0.75 + rand() * 0.5);
    }
    return { list, targets };
})();
const target = computed(() => COLORS[plan.targets[targetIndex.value]]);

let ctx = null;
let W = 0;
let H = 0;
let raf = 0;
let last = 0;
let elapsed = 0;
let next = 0;
let balloons = [];
let bits_ = [];
let shake = 0;
let hudTimer = 0;
let finishTimer = 0;
let observer = null;

const sprites = {};
function makeSprite(key, inner) {
    const svg = `<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 64 64" width="160" height="160">${inner}</svg>`;
    const img = new Image();
    img.src = `data:image/svg+xml;charset=utf-8,${encodeURIComponent(svg)}`;
    sprites[key] = img;
}
makeSprite('storm', bits.storm);
makeSprite('heart', bits.heart('#ffc83d'));

const R = () => Math.max(26, Math.min(40, W * 0.085));

function size() {
    const box = wrap.value.getBoundingClientRect();
    const dpr = Math.min(window.devicePixelRatio || 1, 2);
    W = Math.max(240, box.width);
    H = Math.max(320, box.height);
    canvas.value.width = Math.round(W * dpr);
    canvas.value.height = Math.round(H * dpr);
    ctx = canvas.value.getContext('2d');
    ctx.setTransform(dpr, 0, 0, dpr, 0, 0);
}

function burst(x, y, color, text) {
    for (let i = 0; i < 12; i++) {
        const a = (i / 12) * Math.PI * 2;
        bits_.push({ x, y, vx: Math.cos(a) * (120 + Math.random() * 90), vy: Math.sin(a) * (120 + Math.random() * 90), life: 0.55, color, rot: Math.random() * 6 });
    }
    if (text) bits_.push({ x, y: y - 14, vx: 0, vy: -70, life: 0.9, text, color });
}

function tap(event) {
    if (over.value) return;
    const box = canvas.value.getBoundingClientRect();
    const px = event.clientX - box.left;
    const py = event.clientY - box.top;
    const r = R();
    for (let i = balloons.length - 1; i >= 0; i--) {
        const b = balloons[i];
        const bx = b.x * W + Math.sin(elapsed * 1.6 + b.sway) * 10;
        const by = b.y * H;
        if ((px - bx) ** 2 / (r * 1.05) ** 2 + (py - by) ** 2 / (r * 1.2) ** 2 > 1.15) continue;
        balloons.splice(i, 1);
        pop(b, bx, by);
        return;
    }
}

function pop(b, x, y) {
    if (b.type === 'storm') {
        lives.value--;
        combo.value = 0;
        sfx('bad');
        navigator.vibrate?.(90);
        shake = 0.5;
        burst(x, y, '#7d86a8', 'Aduh!');
        if (lives.value <= 0) end();
        return;
    }
    if (b.type === 'gold') {
        score.value += 50;
        combo.value++;
        sfx('star');
        burst(x, y, '#ffc83d', '+50');
        return;
    }
    if (b.color === plan.targets[targetIndex.value]) {
        combo.value++;
        const points = 10 + Math.min(combo.value, 15);
        score.value += points;
        sfx('pop');
        navigator.vibrate?.(10);
        burst(x, y, COLORS[b.color].fill, combo.value >= 5 ? `+${points} kombo!` : `+${points}`);
    } else {
        combo.value = 0;
        score.value = Math.max(0, score.value - 5);
        sfx('wrong');
        burst(x, y, COLORS[b.color].fill, 'Salah warna!');
    }
}

function end() {
    if (over.value) return;
    over.value = true;
    sfx(lives.value > 0 ? 'win' : 'lose');
    finishTimer = setTimeout(() => emit('finish', { score: score.value }), 1300);
}

function update(dt) {
    elapsed += dt;
    shake = Math.max(0, shake - dt * 2.4);
    if (!over.value) {
        if (elapsed >= total) end();
        const w = Math.min(plan.targets.length - 1, Math.floor(elapsed / SWITCH));
        if (w !== targetIndex.value) {
            targetIndex.value = w;
            sfx('tick');
        }
        while (next < plan.list.length && plan.list[next].t <= elapsed) {
            const p = plan.list[next++];
            balloons.push({ ...p, y: 1.12, id: next, born: elapsed });
        }
    }
    for (const b of balloons) b.y -= b.speed * dt * (H > 0 ? 1 : 0) * (over.value ? 2 : 1);
    balloons = balloons.filter((b) => b.y > -0.2);
    for (const s of bits_) {
        s.x += s.vx * dt;
        s.y += s.vy * dt;
        s.vy += s.text ? 0 : 300 * dt;
        s.vx *= 0.96;
        s.rot += dt * 8;
        s.life -= dt;
    }
    bits_ = bits_.filter((s) => s.life > 0);
}

function drawBalloon(b) {
    const r = R();
    const x = b.x * W + Math.sin(elapsed * 1.6 + b.sway) * 10;
    const y = b.y * H;
    const tilt = Math.sin(elapsed * 1.6 + b.sway) * 0.08;
    // string
    ctx.strokeStyle = 'rgba(59,29,51,.55)';
    ctx.lineWidth = 1.6;
    ctx.beginPath();
    ctx.moveTo(x, y + r * 1.18);
    ctx.bezierCurveTo(x - 8, y + r * 1.6, x + 8, y + r * 2, x - 2, y + r * 2.5);
    ctx.stroke();
    if (b.type !== 'color') {
        const img = sprites[b.type === 'storm' ? 'storm' : 'heart'];
        if (img?.complete && img.naturalWidth) {
            ctx.save();
            ctx.translate(x, y);
            ctx.rotate(tilt);
            const s = r * (b.type === 'storm' ? 1.25 : 1.15);
            ctx.drawImage(img, -s, -s, s * 2, s * 2);
            ctx.restore();
        }
        if (b.type === 'gold') {
            ctx.fillStyle = '#fff';
            ctx.globalAlpha = 0.6 + Math.sin(elapsed * 8 + b.sway) * 0.4;
            ctx.beginPath();
            ctx.arc(x + r * 0.55, y - r * 0.6, 3, 0, 6.3);
            ctx.fill();
            ctx.globalAlpha = 1;
        }
        return;
    }
    const c = COLORS[b.color];
    ctx.save();
    ctx.translate(x, y);
    ctx.rotate(tilt);
    ctx.fillStyle = c.fill;
    ctx.strokeStyle = INK;
    ctx.lineWidth = 3;
    ctx.beginPath();
    ctx.ellipse(0, 0, r, r * 1.15, 0, 0, 6.3);
    ctx.fill();
    ctx.stroke();
    // knot
    ctx.beginPath();
    ctx.moveTo(-5, r * 1.12);
    ctx.lineTo(5, r * 1.12);
    ctx.lineTo(0, r * 1.25);
    ctx.closePath();
    ctx.fill();
    ctx.stroke();
    // shine
    ctx.fillStyle = 'rgba(255,255,255,.7)';
    ctx.beginPath();
    ctx.ellipse(-r * 0.42, -r * 0.5, r * 0.18, r * 0.3, -0.5, 0, 6.3);
    ctx.fill();
    // face
    ctx.fillStyle = INK;
    ctx.beginPath();
    ctx.arc(-r * 0.28, -r * 0.02, r * 0.09, 0, 6.3);
    ctx.arc(r * 0.28, -r * 0.02, r * 0.09, 0, 6.3);
    ctx.fill();
    ctx.lineWidth = 2.2;
    ctx.beginPath();
    ctx.arc(0, r * 0.14, r * 0.14, 0.15, Math.PI - 0.15);
    ctx.stroke();
    ctx.fillStyle = 'rgba(255,255,255,.45)';
    ctx.beginPath();
    ctx.ellipse(-r * 0.5, r * 0.18, r * 0.12, r * 0.07, 0, 0, 6.3);
    ctx.ellipse(r * 0.5, r * 0.18, r * 0.12, r * 0.07, 0, 0, 6.3);
    ctx.fill();
    ctx.restore();
}

function draw() {
    ctx.clearRect(0, 0, W, H);
    ctx.save();
    if (shake > 0) ctx.translate((Math.random() - 0.5) * shake * 20, (Math.random() - 0.5) * shake * 20);
    for (const b of balloons) drawBalloon(b);
    for (const s of bits_) {
        ctx.globalAlpha = Math.max(0, Math.min(1, s.life * 1.8));
        if (s.text) {
            ctx.font = "22px 'Lilita One', system-ui, sans-serif";
            ctx.textAlign = 'center';
            ctx.lineWidth = 6;
            ctx.lineJoin = 'round';
            ctx.strokeStyle = INK;
            ctx.strokeText(s.text, s.x, s.y);
            ctx.fillStyle = s.color;
            ctx.fillText(s.text, s.x, s.y);
        } else {
            ctx.save();
            ctx.translate(s.x, s.y);
            ctx.rotate(s.rot);
            ctx.fillStyle = s.color;
            ctx.fillRect(-4, -2, 8, 4);
            ctx.restore();
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

onMounted(() => {
    size();
    observer = new ResizeObserver(() => size());
    observer.observe(wrap.value);
    hudTimer = setInterval(() => {
        if (!over.value) secondsLeft.value = Math.max(0, Math.ceil(total - elapsed));
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
});
</script>

<template>
    <div ref="wrap" class="relative min-h-[60dvh] flex-1 overflow-hidden" data-testid="balon">
        <canvas ref="canvas" class="absolute inset-0 size-full touch-none" aria-label="Ketuk balon dengan warna yang diminta" role="button" @pointerdown.prevent="tap" />
        <GameHud :me="me" :score="score" :seconds="secondsLeft" :total="limit / 1000" :lives="lives" />
        <div :key="targetIndex" class="ar-card ar-pop pointer-events-none absolute left-1/2 top-24 flex -translate-x-1/2 items-center gap-2 whitespace-nowrap px-4 py-2" role="status" data-testid="target">
            <span class="block size-6 rounded-full border-[3px] border-[#3b1d33]" :style="{ background: target.fill }" aria-hidden="true" />
            <span class="text-base font-bold">Pecahkan yang <span class="ar-title" :style="{ color: target.fill, WebkitTextStroke: '1px #3b1d33' }">{{ target.name.toUpperCase() }}</span>!</span>
        </div>
        <p v-if="combo >= 5 && !over" :key="combo >= 10 ? 'x' : 'y'" class="ar-title ar-outline ar-pop pointer-events-none absolute left-1/2 top-40 -translate-x-1/2 text-center text-3xl text-[#ffd54a]">Kombo x{{ combo }}!</p>
        <div v-if="over" class="pointer-events-none absolute inset-0 grid place-items-center"><p class="ar-title ar-outline ar-pop text-center text-5xl text-white">{{ lives > 0 ? 'Waktu habis!' : 'Yah, kena badai!' }}</p></div>
    </div>
</template>
