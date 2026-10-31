<script setup>
import { computed } from 'vue';

// A small line chart for the last 30 days of one measurement (weight, temperature, water, sleep).
const props = defineProps({ points: { type: Array, required: true }, color: { type: String, default: '#e0245e' }, unit: { type: String, default: '' } });
const W = 220;
const H = 56;
const view = computed(() => {
    const values = props.points.map((p) => p.value);
    const min = Math.min(...values);
    const max = Math.max(...values);
    const span = max - min || 1;
    const xs = (i) => (props.points.length === 1 ? W / 2 : (i / (props.points.length - 1)) * (W - 8) + 4);
    const coords = props.points.map((p, i) => [xs(i), H - 6 - ((p.value - min) / span) * (H - 12)]);
    return { min, max, coords, last: values[values.length - 1], line: coords.map((c) => c.join(',')).join(' ') };
});
</script>

<template>
    <div>
        <svg :viewBox="`0 0 ${W} ${H}`" class="h-14 w-full" role="img" :aria-label="`${view.min}–${view.max} ${unit}`">
            <polyline v-if="points.length > 1" :points="view.line" fill="none" :stroke="color" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" />
            <circle v-for="(c, i) in view.coords" :key="i" :cx="c[0]" :cy="c[1]" r="2.8" :fill="color" />
        </svg>
        <p class="mt-0.5 flex justify-between text-[0.6875rem] tabular-nums text-ink-muted"><span>{{ view.min }}</span><span class="font-semibold text-plum">{{ view.last }} {{ unit }}</span><span>{{ view.max }}</span></p>
    </div>
</template>
