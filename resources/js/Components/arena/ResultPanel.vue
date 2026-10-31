<script setup>
// The end-of-game screen (v2): light rays, both characters reacting (winner cheers, the other pouts; a draw makes both
// happy), stars landing one by one with a sound, the score counting up, a "Rekor baru!" ribbon and confetti.
import { computed, onBeforeUnmount, onMounted, ref } from 'vue';
import Buddy from '@/Components/arena/Buddy.vue';
import Stars from '@/Components/arena/Stars.vue';
import Face from '@/Components/arena/Face.vue';
import Confetti from '@/Components/arena/Confetti.vue';
import { sfx } from '@/lib/arenaAudio';

const props = defineProps({
    title: { type: String, default: '' },
    score: { type: Number, default: 0 },
    unit: { type: String, default: 'poin' },
    stars: { type: Number, default: 1 },
    best: { type: Boolean, default: false },
    me: { type: Object, default: null },
    partner: { type: Object, default: null },
    // Duo: 'me' | 'partner' | 'draw' | null
    winner: { type: String, default: null },
    partnerScore: { type: Number, default: null },
    partnerStars: { type: Number, default: 0 },
    note: { type: String, default: '' },
});

const duo = computed(() => props.winner !== null && props.partnerScore !== null);
const headline = computed(() => {
    if (!duo.value) return props.best ? 'Rekor baru!' : props.stars >= 3 ? 'Luar biasa!' : props.stars === 2 ? 'Keren!' : 'Selesai!';
    if (props.winner === 'me') return 'Kamu menang!';
    if (props.winner === 'draw') return 'Seri, sama kuat!';
    return `${props.partner?.name ?? 'Pasanganmu'} menang!`;
});
const myMood = computed(() => (!duo.value ? (props.stars >= 2 ? 'cheer' : 'happy') : props.winner === 'me' ? 'cheer' : props.winner === 'draw' ? 'happy' : 'sad'));
const theirMood = computed(() => (props.winner === 'partner' ? 'cheer' : props.winner === 'draw' ? 'happy' : 'sad'));
const celebrate = computed(() => (duo.value ? props.winner !== 'partner' : props.stars >= 2 || props.best));

// stars land one by one, then the score counts up
const shownStars = ref(0);
const shownScore = ref(0);
const shownPartner = ref(0);
const timers = [];
let raf = 0;
function countUp() {
    const start = performance.now();
    const step = (now) => {
        const p = Math.min(1, (now - start) / 900);
        const e = 1 - Math.pow(1 - p, 3);
        shownScore.value = Math.round(props.score * e);
        shownPartner.value = Math.round((props.partnerScore ?? 0) * e);
        if (p < 1) raf = requestAnimationFrame(step);
    };
    raf = requestAnimationFrame(step);
}
onMounted(() => {
    sfx(!duo.value ? 'win' : props.winner === 'me' ? 'win' : props.winner === 'draw' ? 'draw' : 'lose');
    countUp();
    for (let i = 1; i <= props.stars; i++) {
        timers.push(
            setTimeout(() => {
                shownStars.value = i;
                sfx('star');
                navigator.vibrate?.(15);
            }, 350 + i * 330),
        );
    }
});
onBeforeUnmount(() => {
    timers.forEach(clearTimeout);
    cancelAnimationFrame(raf);
});
</script>

<template>
    <div class="relative mx-auto w-full max-w-sm">
        <div class="rays" aria-hidden="true" />
        <div class="ar-card ar-pop relative px-6 pb-6 pt-5 text-center" data-testid="result">
            <Confetti v-if="celebrate" />
            <div v-if="best && !duo" class="ribbon ar-title" aria-hidden="true">REKOR BARU</div>
            <div class="-mt-20 flex items-end justify-center" :class="duo ? 'gap-1' : ''">
                <Buddy who="me" :gender="me?.gender" :mood="myMood" :size="duo ? 92 : 112" class="ar-bounce" />
                <Buddy v-if="duo" who="partner" :gender="partner?.gender" :mood="theirMood" :size="92" flip class="ar-bounce" style="animation-delay: -0.4s" />
            </div>
            <p class="ar-title ar-outline headline mt-1 text-4xl text-[#ffd54a]">{{ headline }}</p>
            <p v-if="title" class="mt-0.5 text-sm font-bold text-[var(--ar-soft)]">{{ title }}</p>

            <template v-if="duo">
                <div class="mt-4 grid grid-cols-2 gap-3">
                    <div class="rounded-2xl border-[2.5px] border-[var(--ar-line)] p-3 transition" :class="winner === 'me' ? 'win bg-[#ffe27a]' : 'bg-[#f3e6ee]'">
                        <Face :person="me" :size="44" />
                        <p class="mt-1 text-xs font-bold">{{ me?.name }}</p>
                        <p class="text-3xl font-black leading-none text-[#e0245e] tabular-nums">{{ shownScore }}</p>
                        <Stars :count="stars" :size="18" class="mt-1" />
                    </div>
                    <div class="rounded-2xl border-[2.5px] border-[var(--ar-line)] p-3 transition" :class="winner === 'partner' ? 'win bg-[#ffe27a]' : 'bg-[#f3e6ee]'">
                        <Face :person="partner" :size="44" />
                        <p class="mt-1 text-xs font-bold">{{ partner?.name }}</p>
                        <p class="text-3xl font-black leading-none text-[#7a5ad6] tabular-nums">{{ shownPartner }}</p>
                        <Stars :count="partnerStars" :size="18" class="mt-1" />
                    </div>
                </div>
            </template>
            <template v-else>
                <div class="mt-3 flex justify-center gap-1" :aria-label="`${stars} bintang`">
                    <span v-for="n in 3" :key="n" class="star-slot" :class="n <= shownStars && 'on'"><svg viewBox="0 0 24 24" width="56" height="56" aria-hidden="true"><path d="m12 2 2.9 6.3 6.9.8-5.1 4.7 1.4 6.8L12 17.2 5.9 20.6l1.4-6.8L2.2 9.1l6.9-.8L12 2Z" :fill="n <= shownStars ? '#ffd54a' : '#e8dde4'" stroke="#3b1d33" stroke-width="1.6" stroke-linejoin="round" /></svg></span>
                </div>
                <p class="mt-2 text-5xl font-black leading-none text-[#e0245e] tabular-nums">{{ shownScore }}</p>
                <p class="mt-1 text-sm font-bold text-[var(--ar-soft)]">{{ unit }}</p>
            </template>

            <p v-if="note" class="mt-3 text-sm font-semibold text-[var(--ar-soft)]">{{ note }}</p>
            <div class="mt-5 flex flex-col gap-3">
                <slot />
            </div>
        </div>
    </div>
</template>

<style scoped>
.rays { position: absolute; left: 50%; top: 4rem; width: 34rem; height: 34rem; margin-left: -17rem; margin-top: -17rem; background: repeating-conic-gradient(rgb(255 255 255 / 0.32) 0 10deg, transparent 10deg 20deg); border-radius: 50%; mask: radial-gradient(circle, #000 30%, transparent 68%); -webkit-mask: radial-gradient(circle, #000 30%, transparent 68%); animation: rays 18s linear infinite; pointer-events: none; }
.headline { animation: head 0.7s 0.15s cubic-bezier(0.2, 1.8, 0.4, 1) both; }
.ribbon { position: absolute; right: -0.6rem; top: 1rem; z-index: 2; padding: 0.25rem 0.9rem; background: #ff3d6e; color: #fff; border: 3px solid #3b1d33; border-radius: 0.6rem; transform: rotate(8deg); box-shadow: 0 4px 0 #3b1d33; animation: ribbon 0.6s 0.9s cubic-bezier(0.2, 1.8, 0.4, 1) both; }
.star-slot { opacity: 0.2; filter: grayscale(1); transform: scale(0.8); transition: transform 0.35s cubic-bezier(0.2, 2, 0.4, 1), opacity 0.2s, filter 0.2s; }
.star-slot.on { opacity: 1; filter: none; transform: scale(1) rotate(-8deg); }
.star-slot:nth-child(2).on { transform: scale(1.15) translateY(-6px); }
.star-slot:nth-child(3).on { transform: scale(1) rotate(8deg); }
.win { box-shadow: 0 0 0 4px #fff, 0 0 24px 4px #ffd54a; }
@keyframes rays { to { transform: rotate(360deg); } }
@keyframes head { from { transform: scale(0.3) rotate(-8deg); opacity: 0; } }
@keyframes ribbon { from { transform: rotate(40deg) scale(0); } }
@media (prefers-reduced-motion: reduce) { .rays, .headline, .ribbon { animation: none; } }
</style>
