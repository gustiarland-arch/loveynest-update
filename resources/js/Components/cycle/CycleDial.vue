<script setup>
import { computed } from 'vue';

/**
 * The cycle wheel: one ring split into the four phases (menstrual, follicular, fertile window, luteal) with a dot on today.
 * The middle is a slot for the day number or the pregnancy week.
 */
const props = defineProps({
    day: { type: Number, default: null },
    cycle: { type: Number, default: 28 },
    period: { type: Number, default: 5 },
    luteal: { type: Number, default: 14 },
    pregnant: { type: Boolean, default: false },
    progress: { type: Number, default: 0 }, // pregnancy progress 0..1
});

const R = 54;
const C = 2 * Math.PI * R;
const COLORS = { menstrual: '#e0245e', follicular: '#b9b3f2', fertile: '#46c08a', luteal: '#f7b267' };

const arcs = computed(() => {
    const cycle = Math.max(1, props.cycle);
    const menstrual = Math.min(props.period, cycle);
    const ovulation = cycle - props.luteal + 1;
    const fertileFrom = Math.max(menstrual, ovulation - 6);
    const fertileTo = Math.min(cycle, ovulation + 1);
    return [
        { key: 'menstrual', from: 0, to: menstrual },
        { key: 'follicular', from: menstrual, to: fertileFrom },
        { key: 'fertile', from: fertileFrom, to: fertileTo },
        { key: 'luteal', from: fertileTo, to: cycle },
    ]
        .filter((a) => a.to > a.from)
        .map((a) => ({ ...a, color: COLORS[a.key], length: ((a.to - a.from) / cycle) * C - 2.5, offset: -((a.from / cycle) * C) }));
});

const dot = computed(() => {
    const fraction = props.pregnant ? props.progress : props.day ? Math.min(1, (props.day - 0.5) / Math.max(1, props.cycle)) : null;
    if (fraction === null) return null;
    const angle = fraction * 2 * Math.PI - Math.PI / 2;
    return { x: 70 + R * Math.cos(angle), y: 70 + R * Math.sin(angle) };
});
</script>

<template>
    <div class="relative mx-auto aspect-square w-full max-w-[17rem]">
        <svg viewBox="0 0 140 140" class="size-full" aria-hidden="true">
            <circle cx="70" cy="70" r="54" fill="none" stroke="currentColor" stroke-opacity="0.08" stroke-width="12" />
            <template v-if="!pregnant">
                <circle
                    v-for="arc in arcs"
                    :key="arc.key"
                    cx="70" cy="70" r="54" fill="none" :stroke="arc.color" stroke-width="12" stroke-linecap="round"
                    :stroke-dasharray="`${Math.max(arc.length, 0.1)} ${C}`" :stroke-dashoffset="arc.offset"
                    transform="rotate(-90 70 70)"
                />
            </template>
            <circle v-else cx="70" cy="70" r="54" fill="none" stroke="#e0245e" stroke-width="12" stroke-linecap="round" :stroke-dasharray="`${Math.max(progress * C, 0.1)} ${C}`" transform="rotate(-90 70 70)" />
            <g v-if="dot">
                <circle :cx="dot.x" :cy="dot.y" r="9" fill="#fff" stroke="currentColor" stroke-opacity="0.15" />
                <circle :cx="dot.x" :cy="dot.y" r="4.5" fill="#e0245e" />
            </g>
        </svg>
        <div class="absolute inset-0 grid place-items-center px-8 text-center"><slot /></div>
    </div>
</template>
