<script setup>
import Emblem from '@/Components/brand/Emblem.vue';

// The opening illustration: a soft arch of sky, drifting clouds and hearts around the LoveyNest mark.
// Pure CSS and one small image, so the first screen stays light.
const hearts = [
    { x: '12%', y: '62%', s: 14, d: '0s' },
    { x: '82%', y: '70%', s: 18, d: '-2s' },
    { x: '24%', y: '30%', s: 10, d: '-4s' },
    { x: '74%', y: '22%', s: 12, d: '-1s' },
    { x: '50%', y: '84%', s: 9, d: '-3s' },
];
</script>

<template>
    <div class="hero-scene relative mx-auto aspect-[4/4.2] w-full max-w-sm overflow-hidden rounded-b-[3rem] rounded-t-[10rem]">
        <div class="absolute inset-0 hero-sky" />
        <span class="hero-cloud" style="--w: 7rem; top: 14%; left: -2rem; animation-delay: -3s" />
        <span class="hero-cloud" style="--w: 5rem; top: 38%; right: -1.5rem; animation-delay: -9s" />
        <span class="hero-cloud" style="--w: 9rem; bottom: 6%; left: 8%; animation-delay: -6s; opacity: 0.85" />
        <svg v-for="(h, i) in hearts" :key="i" class="hero-heart absolute" :style="{ left: h.x, top: h.y, width: `${h.s}px`, animationDelay: h.d }" viewBox="0 0 24 24" aria-hidden="true">
            <path fill="currentColor" d="M12 21s-7.5-4.6-9.6-9.2C.9 8.4 2.7 5 6 5c2.1 0 3.4 1.1 4.2 2.4h.1C11.1 6.1 12.4 5 14.5 5c3.3 0 5.1 3.4 3.6 6.8C19.5 16.4 12 21 12 21z" />
        </svg>
        <div class="absolute inset-0 grid place-items-center">
            <div class="hero-float"><Emblem :size="190" /></div>
        </div>
    </div>
</template>

<style>
.hero-sky {
    background:
        radial-gradient(70% 55% at 50% 38%, rgb(255 255 255 / 0.85), transparent 70%),
        linear-gradient(180deg, #ffd9e6 0%, #ffe8d9 45%, #e6dcfb 100%);
}
[data-theme='dark'] .hero-sky {
    background:
        radial-gradient(70% 55% at 50% 38%, rgb(255 190 220 / 0.25), transparent 70%),
        linear-gradient(180deg, #4a2a63 0%, #2d2147 55%, #1a1230 100%);
}
.hero-cloud {
    position: absolute;
    width: var(--w);
    height: calc(var(--w) * 0.36);
    border-radius: 9999px;
    background: rgb(255 255 255 / 0.8);
    filter: blur(0.5px);
    animation: hero-drift 16s ease-in-out infinite;
}
.hero-cloud::before {
    content: '';
    position: absolute;
    left: 18%;
    bottom: 40%;
    width: 46%;
    height: 120%;
    border-radius: 9999px;
    background: inherit;
}
.hero-heart {
    color: #f2386b;
    opacity: 0.55;
    animation: hero-rise 7s ease-in-out infinite;
}
.hero-float {
    animation: hero-bob 6s ease-in-out infinite;
    filter: drop-shadow(0 18px 20px rgb(150 40 90 / 0.25));
}
@keyframes hero-drift {
    50% { transform: translateX(14px); }
}
@keyframes hero-rise {
    0%, 100% { transform: translateY(0) scale(1); opacity: 0.35; }
    50% { transform: translateY(-14px) scale(1.15); opacity: 0.75; }
}
@keyframes hero-bob {
    50% { transform: translateY(-8px); }
}
@media (prefers-reduced-motion: reduce) {
    .hero-cloud, .hero-heart, .hero-float { animation: none; }
}
</style>
