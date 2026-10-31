<script setup>
import { computed } from 'vue';

/**
 * The one design for every "time" card in the app: days together, streaks, countdowns, milestones.
 * variant "tile": compact square-ish card (3 across on a phone), variant "row": full-width list item.
 * `progress` (0..1) draws a ring around the icon so the card shows how far along the time is.
 */
const props = defineProps({
    variant: { type: String, default: 'tile' }, // tile | row | chip (one slim pill: icon, value, unit)
    tone: { type: String, default: 'rose' }, // rose | lavender | sage | sun | glass
    icon: { type: [Object, Function], default: null },
    value: { type: [Number, String], default: null },
    unit: { type: String, default: '' },
    label: { type: String, default: '' },
    sub: { type: String, default: '' },
    progress: { type: Number, default: null },
});

const tones = {
    rose: { chip: 'bg-blush text-rose-ink', ring: 'var(--color-berry)', face: 'glass wash-blush' },
    lavender: { chip: 'bg-lavender text-lavender-ink', ring: '#7a6ae0', face: 'glass wash-lavender' },
    sage: { chip: 'bg-sage text-sage-ink', ring: '#3f9d6b', face: 'glass wash-sage' },
    sun: { chip: 'bg-[#ffe9b8] text-[#8a5a00]', ring: '#e8a317', face: 'glass wash-peach' },
    glass: { chip: 'bg-fill text-plum', ring: 'var(--color-plum)', face: 'glass' },
};
const look = computed(() => tones[props.tone] ?? tones.rose);
const deg = computed(() => `${Math.round(Math.min(1, Math.max(0, props.progress ?? 0)) * 360)}deg`);
</script>

<template>
    <div
        v-if="variant === 'chip'"
        class="time-card flex h-10 min-w-0 items-center gap-1.5 rounded-full px-2.5"
        :class="look.face"
        :title="label"
    >
        <span class="grid size-6 shrink-0 place-items-center rounded-full" :class="look.chip"><component :is="icon" v-if="icon" class="size-3.5" :stroke-width="2.2" aria-hidden="true" /></span>
        <span class="min-w-0 truncate text-[0.8125rem] font-semibold tabular-nums text-plum"><span class="sr-only">{{ label }}: </span>{{ value }}<span v-if="unit" class="ml-1 font-medium text-ink-muted">{{ unit }}</span></span>
    </div>
    <div
        v-else
        class="time-card relative min-w-0"
        :class="[look.face, variant === 'row' ? 'flex items-center gap-3 rounded-[1.25rem] p-3.5' : 'flex flex-col rounded-[1.25rem] p-3']"
    >
        <span
            v-if="icon || progress !== null"
            class="time-ring grid shrink-0 place-items-center rounded-full"
            :class="variant === 'row' ? 'size-14' : 'size-10'"
            :style="progress !== null ? { background: `conic-gradient(${look.ring} ${deg}, color-mix(in srgb, ${look.ring} 16%, transparent) 0)` } : undefined"
        >
            <span class="grid place-items-center rounded-full" :class="[look.chip, progress !== null ? (variant === 'row' ? 'size-[3.125rem]' : 'size-[2.125rem]') : variant === 'row' ? 'size-14' : 'size-10']">
                <component :is="icon" v-if="icon" :class="variant === 'row' ? 'size-6' : 'size-[1.125rem]'" :stroke-width="2" aria-hidden="true" />
            </span>
        </span>
        <span class="min-w-0" :class="variant === 'row' ? 'flex-1' : 'mt-2'">
            <span v-if="label" class="block truncate text-[0.6875rem] font-semibold uppercase tracking-[0.07em] text-ink-muted">{{ label }}</span>
            <span class="flex items-baseline gap-1">
                <span v-if="value !== null" class="font-display text-[1.625rem] font-semibold leading-tight tabular-nums text-plum">{{ value }}</span>
                <span v-if="unit" class="text-xs font-semibold text-ink-muted">{{ unit }}</span>
            </span>
            <span v-if="sub" class="block truncate text-xs text-ink-muted">{{ sub }}</span>
            <slot />
        </span>
        <slot name="aside" />
    </div>
</template>
