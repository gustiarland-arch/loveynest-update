<script setup>
// Konser Hewan: four little animals each sing one note. They sing a tune, you sing it back by tapping them in the
// same order. Every round the tune gets one note longer and a little faster. A wrong note costs a heart and the tune
// is played again. The tune comes from the seed, so two phones get the very same concert.
import { computed, onBeforeUnmount, onMounted, ref } from 'vue';
import GameHud from '@/Components/arena/GameHud.vue';
import Face from '@/Components/arena/Face.vue';
import Sticker from '@/Components/arena/Sticker.vue';
import Buddy from '@/Components/arena/Buddy.vue';
import { rng } from '@/lib/rng';
import { note, sfx } from '@/lib/arenaAudio';

const props = defineProps({
    seed: { type: Number, required: true },
    limit: { type: Number, default: 90000 },
    me: { type: Object, required: true },
    partner: { type: Object, default: null },
});
const emit = defineEmits(['finish']);

const SINGERS = [
    { sticker: 'bear', name: 'Beruang', color: '#ff8fb8', dark: '#e0457a', freq: 523.25, key: '1' },
    { sticker: 'cat', name: 'Kucing', color: '#ffb347', dark: '#e07b12', freq: 659.25, key: '2' },
    { sticker: 'bunny', name: 'Kelinci', color: '#b79bff', dark: '#7a5ce0', freq: 783.99, key: '3' },
    { sticker: 'frog', name: 'Katak', color: '#4fd6a0', dark: '#1f9a64', freq: 1046.5, key: '4' },
];

const tune = (() => {
    const rand = rng(props.seed);
    const list = [];
    while (list.length < 60) {
        const n = Math.floor(rand() * 4);
        if (list.length >= 2 && list[list.length - 1] === n && list[list.length - 2] === n) continue;
        list.push(n);
    }
    return list;
})();

const round = ref(1);
const phase = ref('ready'); // ready | show | input | good | over
const lit = ref(-1);
const wrongPad = ref(-1);
const step = ref(0);
const score = ref(0);
const lives = ref(3);
const secondsLeft = ref(Math.ceil(props.limit / 1000));
const notes = ref([]);
let noteId = 0;
let startedAt = 0;
let inputAt = 0;
let timers = [];
let clock = 0;
let finished = false;

const later = (ms, fn) => timers.push(setTimeout(fn, ms));
const bubble = computed(() => ({ ready: 'Siap-siap ya…', show: 'Dengarkan baik-baik!', input: 'Giliranmu!', good: 'Hore, pas!', over: 'Konser selesai!' })[phase.value]);

function sing(i, length) {
    lit.value = i;
    note(SINGERS[i].freq, length);
    const id = ++noteId;
    notes.value = [...notes.value, { id, pad: i, dx: Math.round((Math.random() - 0.5) * 40), glyph: ['♪', '♫', '♬'][id % 3] }];
    later(900, () => (notes.value = notes.value.filter((n) => n.id !== id)));
}

function show() {
    if (finished) return;
    phase.value = 'show';
    step.value = 0;
    const on = Math.max(230, 470 - round.value * 18);
    const gap = Math.max(90, 150 - round.value * 5);
    for (let k = 0; k < round.value; k++) {
        later(k * (on + gap) + 350, () => sing(tune[k], on / 1000));
        later(k * (on + gap) + 350 + on, () => (lit.value = -1));
    }
    later(round.value * (on + gap) + 400, () => {
        if (finished) return;
        phase.value = 'input';
        inputAt = performance.now();
    });
}

function press(i) {
    if (phase.value !== 'input' || finished) return;
    if (tune[step.value] === i) {
        sing(i, 0.26);
        later(220, () => lit.value === i && (lit.value = -1));
        step.value++;
        navigator.vibrate?.(8);
        if (step.value === round.value) {
            // quick answers earn a little extra
            const secs = (performance.now() - inputAt) / 1000;
            const bonus = Math.max(0, Math.round((round.value * 0.9 - secs) * 10));
            score.value += round.value * 100 + Math.min(bonus, round.value * 40);
            phase.value = 'good';
            later(250, () => sfx('match'));
            later(1000, () => {
                round.value++;
                show();
            });
        }
    } else {
        wrongPad.value = i;
        sfx('wrong');
        navigator.vibrate?.(80);
        lives.value--;
        later(450, () => (wrongPad.value = -1));
        if (lives.value <= 0) return end();
        phase.value = 'show';
        later(800, show);
    }
}

function end() {
    if (finished) return;
    finished = true;
    phase.value = 'over';
    lit.value = -1;
    sfx(lives.value > 0 ? 'win' : 'lose');
    later(1400, () => emit('finish', { score: score.value }));
}

function key(event) {
    const i = SINGERS.findIndex((s) => s.key === event.key);
    if (i >= 0) press(i);
}

onMounted(() => {
    startedAt = performance.now();
    clock = setInterval(() => {
        const left = props.limit - (performance.now() - startedAt);
        secondsLeft.value = Math.max(0, Math.ceil(left / 1000));
        if (left <= 0) end();
    }, 200);
    window.addEventListener('keydown', key);
    later(500, show);
});

onBeforeUnmount(() => {
    finished = true;
    timers.forEach(clearTimeout);
    timers = [];
    clearInterval(clock);
    window.removeEventListener('keydown', key);
});
</script>

<template>
    <div class="relative flex min-h-[60dvh] flex-1 flex-col overflow-hidden px-4 pb-6" data-testid="nada">
        <GameHud :me="me" :score="score" :seconds="secondsLeft" :total="limit / 1000" :lives="lives" /><div class="h-[5.2rem] shrink-0" aria-hidden="true" />

        <div class="mx-auto mt-3 flex w-full max-w-sm items-end gap-2">
            <Buddy :tone="phase === 'input' ? 'pink' : 'lilac'" :mood="phase === 'good' ? 'cheer' : phase === 'over' ? (lives > 0 ? 'cheer' : 'sad') : phase === 'input' ? 'wink' : 'think'" :size="64" :class="phase === 'show' ? 'ar-wiggle' : 'ar-bounce'" />
            <div :key="phase" class="ar-card ar-pop relative mb-4 flex-1 px-3 py-2 text-sm font-bold" role="status" data-testid="bubble">
                {{ bubble }}<span v-if="phase !== 'over'" class="ml-1 text-[var(--ar-soft)]">· Ronde {{ round }}</span>
                <span v-if="phase === 'input'" class="ml-1 tabular-nums text-[#1f9a64]">{{ step }}/{{ round }}</span>
            </div>
        </div>

        <div class="mx-auto mt-2 grid w-full max-w-sm flex-1 grid-cols-2 content-center gap-4">
            <button
                v-for="(s, i) in SINGERS"
                :key="s.sticker"
                type="button"
                class="pad relative grid aspect-square place-items-center rounded-[1.6rem] border-[3px] border-[#3b1d33]"
                :class="{ 'is-lit': lit === i, 'is-wrong': wrongPad === i, 'is-wait': phase !== 'input' }"
                :style="{ '--c': s.color, '--d': s.dark }"
                :aria-label="`${s.name}, nada ${i + 1}`"
                :data-testid="`pad-${i}`"
                @pointerdown.prevent="press(i)"
            >
                <Sticker :name="s.sticker" :size="96" class="pad-face" />
                <span class="absolute bottom-2 left-1/2 -translate-x-1/2 text-xs font-extrabold text-white [text-shadow:0_1px_0_#3b1d33]">{{ s.name }}</span>
                <span v-for="n in notes.filter((x) => x.pad === i)" :key="n.id" class="note ar-title" :style="{ '--dx': `${n.dx}px` }" aria-hidden="true">{{ n.glyph }}</span>
            </button>
        </div>

        <div v-if="phase === 'over'" class="pointer-events-none absolute inset-0 grid place-items-center"><p class="ar-title ar-outline ar-pop text-center text-5xl text-white">{{ lives > 0 ? 'Waktu habis!' : 'Yah, salah nada!' }}</p></div>
    </div>
</template>

<style scoped>
.pad {
    background: var(--c);
    box-shadow: 0 6px 0 var(--d), inset 0 4px 0 rgb(255 255 255 / 0.35);
    transition: transform 0.12s ease, box-shadow 0.12s ease, filter 0.2s;
    touch-action: manipulation;
}
.pad:active:not(.is-wait),
.pad.is-lit {
    transform: translateY(4px) scale(1.03);
    box-shadow: 0 2px 0 var(--d), inset 0 4px 0 rgb(255 255 255 / 0.35), 0 0 0 6px rgb(255 255 255 / 0.65), 0 0 28px 6px var(--c);
    filter: brightness(1.12);
}
.pad.is-wait:not(.is-lit) {
    filter: saturate(0.85);
}
.pad.is-wrong {
    animation: pad-shake 0.4s ease;
}
.pad-face {
    transition: transform 0.18s cubic-bezier(0.3, 1.8, 0.5, 1);
}
.is-lit .pad-face {
    transform: scale(1.14) rotate(-6deg);
}
.note {
    position: absolute;
    top: 18%;
    left: 50%;
    font-size: 1.8rem;
    color: #fff;
    -webkit-text-stroke: 1.5px #3b1d33;
    pointer-events: none;
    animation: note-up 0.9s ease-out forwards;
}
@keyframes note-up {
    from { opacity: 1; transform: translate(-50%, 0) scale(0.6); }
    to { opacity: 0; transform: translate(calc(-50% + var(--dx)), -70px) scale(1.2) rotate(12deg); }
}
@keyframes pad-shake {
    20% { transform: translateX(-8px) rotate(-3deg); }
    40% { transform: translateX(8px) rotate(3deg); }
    60% { transform: translateX(-5px); }
    80% { transform: translateX(5px); }
}
@media (prefers-reduced-motion: reduce) {
    .note, .pad.is-wrong { animation: none; }
}
</style>
