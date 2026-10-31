<script setup>
// The heads-up display shared by the Arena games: who is playing (their character), the score (counts up and bumps on
// every gain), a time bar that turns red and pulses in the last seconds, hearts that pop when one is lost, and room for
// one game-specific chip (slot). Same look in every game.
import { computed, onBeforeUnmount, ref, watch } from 'vue';
import CoupleChar from '@/Components/brand/CoupleChar.vue';
import Sticker from '@/Components/arena/Sticker.vue';

const props = defineProps({
    me: { type: Object, required: true },
    score: { type: Number, default: 0 },
    seconds: { type: Number, default: null }, // left
    total: { type: Number, default: null }, // seconds for the whole game
    lives: { type: Number, default: null },
    maxLives: { type: Number, default: 3 },
    label: { type: String, default: '' },
});

const shown = ref(props.score);
const bump = ref(0);
const lostAt = ref(-1);
let raf = 0;
watch(
    () => props.score,
    (to, from) => {
        cancelAnimationFrame(raf);
        if (to > from) bump.value++;
        const start = performance.now();
        const a = shown.value;
        const step = (now) => {
            const p = Math.min(1, (now - start) / 380);
            shown.value = Math.round(a + (to - a) * (1 - Math.pow(1 - p, 3)));
            if (p < 1) raf = requestAnimationFrame(step);
        };
        raf = requestAnimationFrame(step);
    },
);
watch(
    () => props.lives,
    (to, from) => {
        if (from != null && to < from) lostAt.value = to;
    },
);
onBeforeUnmount(() => cancelAnimationFrame(raf));
const frac = computed(() => (props.total ? Math.max(0, Math.min(1, props.seconds / props.total)) : 1));
const urgent = computed(() => props.seconds != null && props.seconds <= 10);
</script>

<template>
    <div class="hud pointer-events-none absolute inset-x-0 top-0 z-10 px-3 pt-2">
        <div class="flex items-center justify-between gap-2">
            <div class="hud-chip flex items-center gap-1.5 pl-1 pr-3">
                <span class="w-9"><CoupleChar :gender="me?.gender" :mood="urgent ? 'oops' : 'happy'" still /></span>
                <span :key="bump" class="hud-score ar-title tabular-nums" data-testid="score">{{ shown }}</span>
            </div>
            <slot />
            <div v-if="lives != null" class="hud-chip flex items-center gap-0.5 px-2" :aria-label="`${lives} nyawa`">
                <span v-for="n in maxLives" :key="n" class="hud-heart" :class="{ gone: n > lives, pop: n === lostAt + 1 }"><Sticker name="heart" :size="22" /></span>
            </div>
            <div v-else-if="seconds != null" class="hud-chip tabular-nums px-3 text-sm font-bold" :class="urgent && 'urgent'">⏱ {{ seconds }}</div>
        </div>
        <div v-if="seconds != null && total" class="hud-time mt-2" :class="urgent && 'urgent'" role="timer" :aria-label="`${seconds} detik lagi`">
            <span :style="{ transform: `scaleX(${frac})` }" />
            <b v-if="lives != null" class="tabular-nums">{{ seconds }} dtk</b>
        </div>
        <p v-if="label" class="hud-chip mx-auto mt-2 w-max max-w-full px-3 py-1 text-center text-sm font-semibold" aria-live="polite">{{ label }}</p>
    </div>
</template>

<style scoped>
.hud-chip { min-height: 2.6rem; border-radius: 9999px; background: #fffaf3; border: 3px solid #3b1d33; box-shadow: 0 4px 0 #3b1d33; color: #3b1d33; }
.hud-score { display: inline-block; font-size: 1.35rem; animation: hud-bump 0.35s cubic-bezier(0.3, 1.8, 0.5, 1); }
.hud-heart { display: inline-block; transition: transform 0.3s, opacity 0.3s, filter 0.3s; }
.hud-heart.gone { opacity: 0.25; filter: grayscale(1); transform: scale(0.8); }
.hud-heart.pop { animation: hud-lose 0.6s ease-out; }
.hud-time { position: relative; height: 0.7rem; border-radius: 9999px; background: rgb(59 29 51 / 0.25); border: 2px solid #3b1d33; overflow: hidden; }
.hud-time span { position: absolute; inset: 0; transform-origin: left; background: linear-gradient(90deg, #35c987, #9be58f); transition: transform 0.25s linear; }
.hud-time.urgent span { background: linear-gradient(90deg, #ff3d6e, #ff8fb8); }
.hud-time.urgent { animation: hud-pulse 0.8s ease-in-out infinite; }
.hud-time b { position: absolute; right: 0.4rem; top: 50%; transform: translateY(-50%); font-size: 0.55rem; color: #3b1d33; }
.urgent.hud-chip { background: #ff9aa8; animation: hud-pulse 0.8s ease-in-out infinite; }
@keyframes hud-bump { 0% { transform: scale(1.5); color: #e0245e; } 100% { transform: scale(1); } }
@keyframes hud-lose { 0% { transform: scale(1.6) rotate(-20deg); } 100% { transform: scale(0.8); } }
@keyframes hud-pulse { 50% { transform: scale(1.04); } }
@media (prefers-reduced-motion: reduce) { .hud-score, .hud-heart.pop, .hud-time.urgent, .urgent.hud-chip { animation: none; } }
</style>
