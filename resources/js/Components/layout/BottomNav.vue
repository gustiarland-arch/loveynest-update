<script setup>
import { computed, onBeforeUnmount, onMounted, ref, watch } from 'vue';
import { Link, router } from '@inertiajs/vue3';
import { Heart } from '@lucide/vue';

/**
 * Phone bottom bar. Eleven designs after the reference sheet (lib/navStyle.js), each with its own look AND motion:
 *   liquid     01 Liquid Floating Bar     – floating soft bar, a wobbling liquid drop under the active tab, filled icon
 *   magnetic   02 Magnetic Dock           – a white tile is pulled to the tapped tab (springs past it and settles)
 *   capsule    03 Glass Capsule Dock      – glass pill, a blue bubble slides to the tab and lights the icon
 *   segmented  04 Segmented Dynamic Bar   – a raised segment that rises out of the bar and glides
 *   orbit      05 Orbit Navigation        – a thin ring with a small arc orbiting the active icon
 *   glass      06 Glass Tile              – cyan glass tile with a glow
 *   wave       08 Wave Indicator Nav      – dark bar, a moving wave under the active tab
 *   neon       07 Cyber Neon Dock         – dark green bar, neon glow under the active tab
 *   blob       08 Morphing Blob Nav       – a gradient blob that keeps changing shape behind the active tab
 *   layered    09 Layered Card Navigation – tabs are stacked cards, the active card lifts
 *   luxury     10 Minimal Luxury Dock     – stacked soft layers, black filled active icon
 *   classic    (default) the LoveyNest bar exactly as it was first built (same markup and classes as the original
 *              AppLayout bar), with the newer motion on top: a pill that springs to the tab and stretches while it travels.
 * Every design can be dragged: put a finger on the bar and slide, the highlight follows like a glass lens and the page
 * opens where you let go. `preview` makes it a self-contained demo (buttons instead of links) for the Settings picker.
 */
const props = defineProps({
    items: { type: Array, required: true }, // { href, label, icon, badge, center }
    variant: { type: String, default: 'liquid' },
    activeHref: { type: String, default: null },
    preview: { type: Boolean, default: false },
});

const WITH_INDICATOR = ['classic', 'liquid', 'magnetic', 'capsule', 'segmented', 'orbit', 'glass', 'wave', 'neon', 'blob'];
const classic = computed(() => props.variant === 'classic');
const demoActive = ref(props.items[0]?.href ?? null);
const current = computed(() => (props.preview ? demoActive.value : props.activeHref));
const activeIndex = computed(() => props.items.findIndex((item) => item.href === current.value));
const hasIndicator = computed(() => WITH_INDICATOR.includes(props.variant));

// ---- drag to switch: slide a finger along the bar, the indicator follows, the page opens where you let go ----
const bar = ref(null);
const dragPos = ref(null); // fractional tab position while dragging, null otherwise
const dragging = computed(() => dragPos.value !== null);
const overIndex = computed(() => (dragging.value ? Math.round(dragPos.value) : activeIndex.value));
const isOn = (item, index) => (dragging.value ? index === overIndex.value : item.href === current.value);
const position = computed(() => (dragging.value ? dragPos.value : Math.max(activeIndex.value, 0)));
const hideIndicator = computed(() => activeIndex.value < 0 && !dragging.value);
// The classic raised heart stands on its own, so the pill is not drawn behind it (as in the original bar).
const pillOnHeart = computed(() => classic.value && !!props.items[overIndex.value]?.center);

let press = null;
let swallowClick = false;
let lastOver = -1;

function go(item) {
    if (props.preview) demoActive.value = item.href;
    else if (item.href !== current.value) router.visit(item.href);
}
function onDown(event) {
    if (event.pointerType === 'mouse' && event.button !== 0) return;
    press = { x: event.clientX, y: event.clientY, id: event.pointerId, moved: false };
}
function onMove(event) {
    if (!press) return;
    if (!press.moved) {
        const dx = event.clientX - press.x;
        if (Math.abs(dx) < 10) return;
        if (Math.abs(event.clientY - press.y) > Math.abs(dx)) {
            press = null; // a vertical swipe belongs to the page
            return;
        }
        press.moved = true;
        swallowClick = true;
        try {
            bar.value?.setPointerCapture(press.id);
        } catch {
            /* the drag still works without capture */
        }
    }
    const box = bar.value.getBoundingClientRect();
    const pad = Number.parseFloat(getComputedStyle(bar.value).paddingLeft) || 0;
    const cell = (box.width - 2 * pad) / props.items.length;
    // A little rubber band past both ends, like a real glass lens being pulled.
    const raw = (event.clientX - box.left - pad) / cell - 0.5;
    const max = props.items.length - 1;
    dragPos.value = raw < 0 ? raw * 0.25 : raw > max ? max + (raw - max) * 0.25 : raw;
    if (overIndex.value !== lastOver) {
        lastOver = overIndex.value;
        navigator.vibrate?.(6);
    }
}
function onUp() {
    if (!press) return;
    const moved = press.moved;
    press = null;
    if (moved) {
        const item = props.items[Math.min(props.items.length - 1, Math.max(0, overIndex.value))];
        dragPos.value = null;
        lastOver = -1;
        if (item) go(item);
        setTimeout(() => (swallowClick = false), 60);
    }
}
function onCancel() {
    press = null;
    dragPos.value = null;
    lastOver = -1;
    swallowClick = false;
}
function onClickCapture(event) {
    if (!swallowClick) return;
    event.preventDefault();
    event.stopPropagation();
}

// ---- the indicator is moved by a small spring simulation (one rAF loop that sleeps when it has settled) ----
// Released: slightly under-damped, so it glides past the tab a touch and settles (the "magnetic" feel of the reference video).
// Dragged: stiff and well damped, so it stays under the finger. While it travels it stretches along its path and thins a
// little (squash & stretch), and while a finger holds the bar it swells into a lens.
const ind = ref(null);
const reduce = typeof matchMedia === 'function' && matchMedia('(prefers-reduced-motion: reduce)').matches;
let x = 0;
let v = 0;
let lift = 1;
let raf = 0;
let then = 0;

function paint() {
    const el = ind.value;
    if (!el) return;
    const speed = Math.min(Math.abs(v) / 9, 1);
    const sx = (1 + speed * 0.42) * lift;
    const sy = (1 - speed * 0.18) * (1 + (lift - 1) * 0.6);
    el.style.transform = `translate3d(${(x * 100).toFixed(3)}%,0,0)`;
    const shape = el.firstElementChild;
    if (shape) shape.style.transform = `scale(${sx.toFixed(4)},${sy.toFixed(4)})`;
}
function step(now) {
    const dt = Math.min(0.034, Math.max(0.001, (now - then) / 1000));
    then = now;
    const goal = position.value;
    const [k, c] = dragging.value ? [1100, 62] : [420, 27];
    v += (-k * (x - goal) - c * v) * dt;
    x += v * dt;
    const liftGoal = dragging.value && classic.value ? 1.14 : dragging.value ? 1.08 : 1;
    lift += (liftGoal - lift) * Math.min(1, dt * 16);
    if (Math.abs(x - goal) < 0.0015 && Math.abs(v) < 0.02 && Math.abs(lift - liftGoal) < 0.002) {
        x = goal;
        v = 0;
        lift = liftGoal;
        paint();
        raf = 0;
        return;
    }
    paint();
    raf = requestAnimationFrame(step);
}
function wake() {
    if (reduce) {
        x = position.value;
        v = 0;
        lift = 1;
        paint();
        return;
    }
    if (raf) return;
    then = performance.now();
    raf = requestAnimationFrame(step);
}
watch([position, dragging], wake);
watch(ind, () => {
    x = position.value;
    paint();
});
onMounted(() => {
    x = position.value;
    paint();
});
onBeforeUnmount(() => cancelAnimationFrame(raf));
</script>

<template>
    <nav class="bottom-nav" :class="[`bnv-${props.variant}`, preview && 'bn-preview', dragging && 'bn-dragging']" :style="{ '--n': items.length }" :aria-label="preview ? undefined : $t('app.name')" :aria-hidden="preview ? 'true' : undefined">
        <ul
            ref="bar"
            :class="classic ? 'bn-classic glass-bar relative mx-auto flex h-16 max-w-md items-center justify-around rounded-full px-1.5' : 'bn-bar'"
            @pointerdown="onDown"
            @pointermove="onMove"
            @pointerup="onUp"
            @pointercancel="onCancel"
            @click.capture="onClickCapture"
        >
            <li v-if="hasIndicator" ref="ind" class="bn-ind" :class="{ 'bn-hidden': hideIndicator || pillOnHeart, 'bn-lens': dragging }" aria-hidden="true"><span class="bn-shape"><i /></span></li>

            <!-- Klasik: the original bar, element for element -->
            <template v-if="classic">
                <li v-for="(item, index) in items" :key="item.href" class="bn-cell flex-1">
                    <component
                        :is="preview ? 'button' : Link"
                        v-bind="preview ? { type: 'button', onClick: () => go(item) } : { href: item.href, prefetch: item.href !== '/chat' ? ['mount', 'hover'] : false, cacheFor: ['30s', '5m'] }"
                        class="relative mx-auto flex flex-col items-center justify-center gap-0.5 rounded-full text-[0.625rem] font-semibold transition duration-300 ease-gentle"
                        :class="[item.center ? '-mt-6 bn-c-center' : 'h-[3.25rem] w-full max-w-[4.75rem]', isOn(item, index) ? 'text-berry-ink bn-on' : 'text-plum']"
                        :aria-current="item.href === current ? 'page' : undefined"
                    >
                        <span
                            v-if="item.center"
                            class="bn-heart grid size-14 place-items-center rounded-full bg-hero text-white shadow-[0_10px_22px_-8px_color-mix(in_srgb,var(--color-berry)_80%,transparent),inset_1.5px_1.5px_0.5px_-1px_rgb(255_255_255/0.9),inset_0_0_0_0.5px_rgb(255_255_255/0.35)] ring-4 ring-[var(--glass-strong)] transition active:scale-95"
                        >
                            <Heart class="size-6" :stroke-width="2.2" aria-hidden="true" />
                        </span>
                        <span v-else class="bn-icon"><component :is="item.icon" class="size-6" :stroke-width="isOn(item, index) ? 2.25 : 1.75" aria-hidden="true" /></span>
                        {{ item.center ? $t('berdua.title') : item.label }}
                        <span v-if="item.badge" class="absolute right-[calc(50%-1.6rem)] top-0.5 min-w-5 rounded-full bg-berry px-1 text-center text-[0.6875rem] leading-5 text-white">{{ item.badge > 9 ? '9+' : item.badge }}</span>
                    </component>
                </li>
            </template>

            <template v-else>
                <li v-for="(item, index) in items" :key="item.href" class="bn-cell" :style="{ '--k': index }">
                    <component
                        :is="preview ? 'button' : Link"
                        v-bind="preview ? { type: 'button', onClick: () => go(item) } : { href: item.href, prefetch: item.href !== '/chat' ? ['mount', 'hover'] : false, cacheFor: ['30s', '5m'] }"
                        class="bn-item"
                        :class="{ 'bn-on': isOn(item, index) }"
                        :aria-current="item.href === current ? 'page' : undefined"
                    >
                        <span class="bn-icon">
                            <component :is="item.center ? Heart : item.icon" class="size-[1.375rem]" :stroke-width="isOn(item, index) ? 2.2 : 1.75" aria-hidden="true" />
                        </span>
                        <span class="bn-label">{{ item.label }}</span>
                        <span v-if="item.badge" class="bn-badge">{{ item.badge > 9 ? '9+' : item.badge }}</span>
                    </component>
                </li>
            </template>
        </ul>
    </nav>
</template>

<style>
.bottom-nav {
    --bn-bar: rgb(255 255 255 / 0.88);
    --bn-edge: rgb(255 255 255 / 0.9);
    --bn-ink: #151b3a;
    --bn-dim: #5d6685;
    --bn-tile: #fff;
    --bn-shadow: 0 14px 30px -14px rgb(40 50 100 / 0.45);
    position: fixed;
    inset-inline: 0;
    bottom: 0;
    z-index: 40;
    padding: 0 1rem max(0.75rem, env(safe-area-inset-bottom));
    /* backwards, not both: after the entrance the normal styles (hidden while the keyboard is open) must apply again. */
    animation: bn-enter 0.5s var(--ease-gentle) backwards;
}
[data-theme='dark'] .bottom-nav {
    --bn-bar: rgb(38 36 52 / 0.9);
    --bn-edge: rgb(255 255 255 / 0.1);
    --bn-ink: #fff;
    --bn-dim: #b2a8c2;
    --bn-tile: #4a4560;
    --bn-shadow: 0 14px 30px -14px rgb(0 0 0 / 0.7);
}
@media (prefers-color-scheme: dark) {
    :root:not([data-theme='light']) .bottom-nav {
        --bn-bar: rgb(38 36 52 / 0.9);
        --bn-edge: rgb(255 255 255 / 0.1);
        --bn-ink: #fff;
        --bn-dim: #b2a8c2;
        --bn-tile: #4a4560;
        --bn-shadow: 0 14px 30px -14px rgb(0 0 0 / 0.7);
    }
}
@media (min-width: 1024px) {
    .bottom-nav:not(.bn-preview) {
        display: none;
    }
}
.bottom-nav.bn-preview {
    position: relative;
    inset: auto;
    z-index: 0;
    padding: 0;
    animation: none;
}

/* ---------- shared structure ---------- */
.bn-bar {
    --pad: 0.375rem;
    touch-action: pan-y;
    user-select: none;
    -webkit-user-select: none;
    position: relative;
    display: flex;
    align-items: stretch;
    height: 4rem;
    max-width: 28rem;
    margin-inline: auto;
    list-style: none;
    padding: 0 var(--pad);
    background: var(--bn-bar);
    border: 1px solid var(--bn-edge);
    border-radius: 1.25rem;
    box-shadow: var(--bn-shadow), inset 0 1px 0 var(--bn-edge);
    backdrop-filter: blur(22px) saturate(1.5);
    -webkit-backdrop-filter: blur(22px) saturate(1.5);
}
.bn-cell {
    position: relative;
    z-index: 1;
    flex: 1;
    min-width: 0;
}
.bn-item {
    position: relative;
    display: flex;
    width: 100%;
    height: 100%;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    gap: 3px;
    border: 0;
    background: none;
    font: inherit;
    font-size: 0.625rem;
    font-weight: 600;
    color: var(--bn-dim);
    -webkit-tap-highlight-color: transparent;
    transition: color 0.25s;
}
.bn-item.bn-on {
    color: var(--bn-ink);
}
.bn-item:active .bn-icon {
    transform: scale(0.86);
}
.bn-icon {
    display: grid;
    place-items: center;
    transition: transform 0.4s var(--ease-gentle);
}
.bn-label {
    line-height: 1.1;
    letter-spacing: 0.01em;
}
.bn-badge {
    position: absolute;
    top: 0.3rem;
    left: calc(50% + 0.45rem);
    min-width: 1.15rem;
    border-radius: 9999px;
    background: var(--color-berry);
    padding: 0 0.25rem;
    text-align: center;
    font-size: 0.625rem;
    line-height: 1.15rem;
    color: #fff;
    z-index: 3;
}
.bn-hidden {
    opacity: 0;
}

/* the sliding indicator: one cell wide. Its position and stretch are written by the spring in the script (transform only). */
.bn-ind {
    position: absolute;
    top: 0;
    bottom: 0;
    left: var(--pad);
    width: calc((100% - 2 * var(--pad)) / var(--n));
    display: grid;
    place-items: center;
    pointer-events: none;
    z-index: 0;
    will-change: transform;
    transition: opacity 0.2s;
}
.bn-shape {
    display: grid;
    place-items: inherit;
    width: 100%;
    height: 100%;
    will-change: transform;
}
.bn-ind i {
    display: block;
    transition: background-color 0.25s, box-shadow 0.25s;
}
.bn-dragging .bn-item,
.bn-dragging .bn-cell > a {
    pointer-events: none;
}
/* A newly active icon gives a small elastic hop. */
.bn-on .bn-icon {
    animation: bn-pop 0.55s cubic-bezier(0.3, 1.7, 0.45, 1);
}
@keyframes bn-pop {
    0% { transform: scale(0.82) translateY(2px); }
    55% { transform: scale(1.12) translateY(-2px); }
    100% { transform: none; }
}

/* ---------- Klasik (default): the original bar. Its look comes from the same utility classes the first version used
   (glass-bar, h-16, max-w-md, rounded-full, px-1.5…); here only the moving parts are added. ---------- */
.bn-classic {
    --pad: 0.375rem;
    touch-action: pan-y;
    user-select: none;
    -webkit-user-select: none;
    list-style: none;
    margin-block: 0;
    animation: bn-spring 0.62s cubic-bezier(0.22, 1.25, 0.36, 1) backwards;
}
.bnv-classic {
    animation: none;
}
.bnv-classic .bn-ind i {
    width: min(100%, 4.75rem);
    height: 3.25rem;
    border-radius: 9999px;
    background: var(--color-fill);
}
/* Finger on the bar: the pill turns into a clear glass lens. */
.bnv-classic .bn-lens i {
    background: color-mix(in srgb, var(--color-fill) 55%, transparent);
    box-shadow: inset 0 0 0 0.5px rgb(255 255 255 / 0.55), inset 1px 1.5px 1px -1px rgb(255 255 255 / 0.9), 0 8px 18px -10px rgb(0 0 0 / 0.35);
    -webkit-backdrop-filter: saturate(1.8) brightness(1.06);
    backdrop-filter: saturate(1.8) brightness(1.06);
}
.bnv-classic .bn-icon {
    display: grid;
    place-items: center;
}
.bn-dragging.bnv-classic .bn-on .bn-icon {
    transform: scale(1.1);
    transition: transform 0.25s cubic-bezier(0.3, 1.5, 0.5, 1);
}
.bnv-classic .bn-c-center.bn-on .bn-heart {
    animation: bn-heart 0.6s cubic-bezier(0.3, 1.6, 0.45, 1);
}
@keyframes bn-heart {
    0% { transform: scale(0.9); }
    50% { transform: scale(1.1); }
    100% { transform: none; }
}
@keyframes bn-spring {
    from { opacity: 0; transform: translateY(28px); }
    to { opacity: 1; transform: none; }
}

/* ---------- 01 Liquid Floating Bar ---------- */
.bnv-liquid .bn-bar {
    margin-bottom: 0.25rem;
    border-radius: 1.4rem;
    animation: bn-bob 5s ease-in-out infinite;
}
.bnv-liquid .bn-ind i {
    width: 3.1rem;
    height: 2.7rem;
    background: color-mix(in srgb, var(--bn-ink) 10%, transparent);
    border-radius: 58% 42% 52% 48% / 48% 56% 44% 52%;
    animation: bn-morph 3.2s ease-in-out infinite;
}
.bnv-liquid .bn-on svg {
    fill: currentColor;
    fill-opacity: 0.92;
}
.bnv-liquid .bn-on .bn-label {
    font-weight: 700;
}

/* ---------- 02 Magnetic Dock ---------- */
.bnv-magnetic .bn-bar {
    --pad: 0.3rem;
    border-radius: 1.1rem;
}
.bnv-magnetic .bn-ind i {
    width: calc(100% - 0.1rem);
    height: calc(100% - 0.6rem);
    background: var(--bn-tile);
    border-radius: 1rem;
    box-shadow: 0 10px 20px -8px rgb(30 40 90 / 0.4), 0 1px 0 var(--bn-edge) inset;
}
.bnv-magnetic .bn-on .bn-icon {
    transform: translateY(-1px) scale(1.1);
}

/* ---------- 03 Glass Capsule Dock ---------- */
.bnv-capsule {
    --bn-bar: rgb(214 226 246 / 0.78);
}
[data-theme='dark'] .bnv-capsule {
    --bn-bar: rgb(48 56 86 / 0.8);
}
.bnv-capsule .bn-bar {
    border-radius: 9999px;
}
.bnv-capsule .bn-ind i {
    width: 3.45rem;
    height: 3.45rem;
    border-radius: 50%;
    background: radial-gradient(circle at 35% 25%, #5aa2ff, #1d55dc 70%);
    box-shadow: 0 10px 20px -6px rgb(29 85 220 / 0.7), inset 0 1px 1px rgb(255 255 255 / 0.6);
}
.bnv-capsule .bn-item.bn-on {
    color: #fff;
}
.bnv-capsule .bn-on .bn-icon {
    transform: translateY(-1px) scale(1.08);
}

/* ---------- 04 Segmented Dynamic Bar ---------- */
.bnv-segmented .bn-bar {
    --pad: 0.3rem;
    border-radius: 1.1rem;
}
.bnv-segmented .bn-ind {
    top: -0.6rem;
    bottom: 0.2rem;
}
.bnv-segmented .bn-ind i {
    width: calc(100% - 0.15rem);
    height: 100%;
    background: var(--bn-tile);
    border-radius: 1.1rem;
    box-shadow: 0 12px 22px -8px rgb(30 40 90 / 0.45), inset 0 1px 0 var(--bn-edge);
}
.bnv-segmented .bn-on .bn-icon {
    transform: translateY(-0.45rem) scale(1.22);
}
.bnv-segmented .bn-on .bn-label {
    transform: translateY(-0.4rem);
    font-weight: 700;
}
.bnv-segmented .bn-label {
    transition: transform 0.4s var(--ease-gentle);
}

/* ---------- 05 Orbit Navigation ---------- */
.bnv-orbit .bn-bar {
    border-radius: 1rem;
}
.bnv-orbit .bn-ind {
    padding-bottom: 0.9rem;
}
.bnv-orbit .bn-ind i {
    position: relative;
    width: 2.7rem;
    height: 2.7rem;
    border-radius: 50%;
    border: 1.5px solid color-mix(in srgb, var(--bn-ink) 14%, transparent);
}
.bnv-orbit .bn-ind i::after {
    content: '';
    position: absolute;
    inset: -1.5px;
    border-radius: 50%;
    border: 1.5px solid transparent;
    border-top-color: var(--bn-ink);
    animation: bn-spin 2.2s linear infinite;
}

/* ---------- 06 Glass Tile ---------- */
.bnv-glass .bn-bar {
    --pad: 0.3rem;
    border-radius: 1.1rem;
}
.bnv-glass .bn-ind i {
    width: calc(100% - 0.1rem);
    height: calc(100% - 0.5rem);
    border-radius: 1rem;
    background: linear-gradient(145deg, rgb(126 238 247 / 0.9), rgb(160 214 255 / 0.65));
    border: 1px solid rgb(255 255 255 / 0.95);
    box-shadow: 0 0 22px rgb(80 222 240 / 0.75), inset 0 1px 0 #fff;
}
.bnv-glass .bn-item.bn-on {
    color: #0a5566;
}

/* ---------- 08 Wave Indicator Nav (dark) ---------- */
.bnv-wave {
    --bn-bar: #121a2e;
    --bn-edge: rgb(255 255 255 / 0.06);
    --bn-ink: #fff;
    --bn-dim: #9ba7c6;
}
.bnv-wave .bn-bar {
    border-radius: 0.9rem;
    backdrop-filter: none;
}
.bnv-wave .bn-ind {
    align-items: end;
    padding-bottom: 0.22rem;
}
.bnv-wave .bn-ind i {
    width: 2.3rem;
    height: 0.6rem;
    background: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 24 8'%3E%3Cpath d='M0 4 Q6 -1 12 4 T24 4' fill='none' stroke='%23ffffff' stroke-width='1.8' stroke-linecap='round'/%3E%3C/svg%3E") repeat-x;
    background-size: 1.15rem 100%;
    animation: bn-wave 1.1s linear infinite;
}

/* ---------- 07 Cyber Neon Dock (dark green) ---------- */
.bnv-neon {
    --bn-bar: #04201e;
    --bn-edge: rgb(61 255 176 / 0.28);
    --bn-ink: #4dffb8;
    --bn-dim: #1f9c74;
}
.bnv-neon .bn-bar {
    border-radius: 0.9rem;
    backdrop-filter: none;
    box-shadow: 0 0 26px rgb(61 255 176 / 0.22), inset 0 0 0 1px var(--bn-edge);
}
.bnv-neon .bn-on svg {
    filter: drop-shadow(0 0 6px #3dffb0);
}
.bnv-neon .bn-on .bn-label {
    text-shadow: 0 0 8px rgb(61 255 176 / 0.9);
}
.bnv-neon .bn-ind {
    align-items: end;
    padding-bottom: 0.3rem;
}
.bnv-neon .bn-ind i {
    width: 1.8rem;
    height: 3px;
    border-radius: 3px;
    background: #3dffb0;
    box-shadow: 0 0 10px #3dffb0, 0 0 22px #3dffb0;
    animation: bn-glow 1.8s ease-in-out infinite;
}

/* ---------- 08 Morphing Blob Nav ---------- */
.bnv-blob .bn-bar {
    border-radius: 1rem;
    overflow: visible;
}
.bnv-blob .bn-ind {
    top: -1rem;
    bottom: -0.3rem;
}
.bnv-blob .bn-ind i {
    width: 4.1rem;
    height: 4.1rem;
    background: radial-gradient(circle at 30% 28%, #ff80ab 0%, #ff9d58 45%, #7a5cff 100%);
    border-radius: 60% 40% 55% 45% / 50% 58% 42% 50%;
    filter: drop-shadow(0 12px 14px rgb(255 100 150 / 0.4));
    animation: bn-morph 3.6s ease-in-out infinite;
}
.bnv-blob .bn-item.bn-on {
    color: #fff;
}
.bnv-blob .bn-on .bn-icon {
    transform: translateY(-1px) scale(1.12);
}

/* ---------- 09 Layered Card Navigation ---------- */
.bnv-layered .bn-bar {
    --pad: 0.2rem;
    background: transparent;
    border: 0;
    box-shadow: none;
    backdrop-filter: none;
    overflow: visible;
}
.bnv-layered .bn-bar::before,
.bnv-layered .bn-bar::after {
    content: '';
    position: absolute;
    z-index: -1;
    left: 0.7rem;
    right: 0.7rem;
    bottom: -0.3rem;
    height: 100%;
    border-radius: 1rem;
    background: color-mix(in srgb, var(--bn-tile) 80%, transparent);
    box-shadow: 0 8px 14px -8px rgb(30 40 90 / 0.35);
}
.bnv-layered .bn-bar::after {
    left: 1.2rem;
    right: 1.2rem;
    bottom: -0.6rem;
    opacity: 0.7;
}
.bnv-layered .bn-cell {
    padding: 0.3rem 0.12rem;
}
.bnv-layered .bn-item {
    background: var(--bn-tile);
    border-radius: 0.95rem;
    box-shadow: 0 6px 12px -8px rgb(30 40 90 / 0.4), inset 0 1px 0 var(--bn-edge);
    transition: transform 0.45s cubic-bezier(0.34, 1.45, 0.64, 1), box-shadow 0.3s, color 0.25s;
}
.bnv-layered .bn-item.bn-on {
    transform: translateY(-0.55rem) scale(1.1);
    box-shadow: 0 16px 24px -10px rgb(30 40 90 / 0.5), inset 0 1px 0 var(--bn-edge);
    z-index: 2;
}

/* ---------- 10 Minimal Luxury Dock ---------- */
.bnv-luxury .bn-bar {
    border-radius: 1.1rem;
    overflow: visible;
}
.bnv-luxury .bn-bar::before,
.bnv-luxury .bn-bar::after {
    content: '';
    position: absolute;
    z-index: -1;
    left: 0.6rem;
    right: 0.6rem;
    bottom: -0.28rem;
    height: 100%;
    border-radius: 1.1rem;
    background: color-mix(in srgb, var(--bn-tile) 75%, transparent);
    box-shadow: 0 8px 14px -8px rgb(30 40 90 / 0.3);
}
.bnv-luxury .bn-bar::after {
    left: 1.1rem;
    right: 1.1rem;
    bottom: -0.56rem;
    opacity: 0.65;
}
.bnv-luxury .bn-on svg {
    fill: currentColor;
}
.bnv-luxury .bn-on .bn-label {
    font-weight: 800;
}
.bnv-luxury .bn-item.bn-on::after {
    content: '';
    position: absolute;
    top: 0.35rem;
    width: 4px;
    height: 4px;
    border-radius: 50%;
    background: var(--color-berry);
}

@keyframes bn-enter {
    from { opacity: 0; transform: translateY(28px); }
    to { opacity: 1; transform: none; }
}
@keyframes bn-bob {
    0%, 100% { transform: translateY(0); }
    50% { transform: translateY(-3px); }
}
@keyframes bn-morph {
    0%, 100% { border-radius: 58% 42% 52% 48% / 48% 56% 44% 52%; transform: rotate(0); }
    33% { border-radius: 44% 56% 40% 60% / 58% 42% 58% 42%; transform: rotate(8deg); }
    66% { border-radius: 52% 48% 62% 38% / 40% 60% 40% 60%; transform: rotate(-6deg); }
}
@keyframes bn-spin {
    to { transform: rotate(360deg); }
}
@keyframes bn-wave {
    to { background-position-x: 1.15rem; }
}
@keyframes bn-glow {
    50% { opacity: 0.6; }
}

@media (prefers-reduced-motion: reduce) {
    .bottom-nav,
    .bottom-nav *,
    .bottom-nav *::after {
        animation: none !important;
        transition-duration: 0.01ms !important;
    }
}
</style>
