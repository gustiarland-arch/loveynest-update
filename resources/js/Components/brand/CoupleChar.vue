<script setup>
// One of the two LoveyNest characters (see lib/couple.js). Pass `gender` (laki-laki → blue with cap, perempuan → pink
// with flower) or `kind` directly. Alive by default: it breathes, blinks, and its cap/flower bobs; `arms="wave"` waves,
// `arms="up"` cheers. Transform-only animations, switched off for reduced motion.
import { computed } from 'vue';
import { coupleSvg, kindOf } from '@/lib/couple';

const props = defineProps({
    gender: { type: String, default: null },
    kind: { type: String, default: null },
    mood: { type: String, default: 'happy' },
    arms: { type: String, default: 'down' },
    size: { type: [Number, String], default: null },
    flip: { type: Boolean, default: false },
    still: { type: Boolean, default: false },
});
const resolved = computed(() => props.kind ?? kindOf(props.gender));
const markup = computed(() => coupleSvg(resolved.value, props.mood, props.arms));
</script>

<template>
    <svg
        viewBox="-12 -8 224 216"
        class="couple-char block overflow-visible"
        :class="[`cc-k-${resolved}`, { 'cc-still': still, 'cc-flip': flip }]"
        :width="size ?? undefined"
        :height="size ?? undefined"
        aria-hidden="true"
        v-html="markup"
    />
</template>

<style>
.couple-char.cc-flip { transform: scaleX(-1); }
.couple-char .cc-body { transform-box: view-box; transform-origin: 100px 180px; animation: cc-breathe 3.2s ease-in-out infinite; }
.couple-char .cc-eyes { transform-box: fill-box; transform-origin: center; animation: cc-blink 4.6s infinite; }
.couple-char .cc-flower { animation: cc-sway 2.8s ease-in-out infinite; }
.couple-char .cc-cap { transform-box: view-box; transform-origin: 100px 100px; animation: cc-bob 3.2s ease-in-out infinite; }
.couple-char .cc-wave { animation: cc-wave 0.9s ease-in-out infinite; }
.couple-char .cc-cheer { animation: cc-cheer 0.6s ease-in-out infinite alternate; }
.couple-char .cc-pucker { transform-box: fill-box; transform-origin: center; animation: cc-pucker 1.1s ease-in-out infinite; }
.couple-char .cc-blush { transform-box: fill-box; transform-origin: center; animation: cc-blush 2.4s ease-in-out infinite; }
@keyframes cc-breathe { 0%, 100% { transform: scale(1, 1); } 50% { transform: scale(1.025, 0.975); } }
@keyframes cc-blink { 0%, 92%, 100% { transform: scaleY(1); } 95% { transform: scaleY(0.1); } }
@keyframes cc-sway { 0%, 100% { transform: rotate(-6deg); } 50% { transform: rotate(8deg); } }
@keyframes cc-bob { 0%, 100% { transform: translateY(0) rotate(0); } 50% { transform: translateY(-2px) rotate(-1.5deg); } }
@keyframes cc-wave { 0%, 100% { transform: rotate(0); } 50% { transform: rotate(-24deg); } }
@keyframes cc-cheer { to { transform: rotate(-14deg) translateY(-3px); } }
@keyframes cc-pucker { 50% { transform: scale(1.2); } }
@keyframes cc-blush { 50% { transform: scale(1.12); opacity: 0.8; } }
.couple-char.cc-still *, .couple-char.cc-still { animation: none !important; }
@media (prefers-reduced-motion: reduce) {
    .couple-char * { animation: none !important; }
}
</style>
