<script setup>
import { computed } from 'vue';

// Seven days around today: tap a day to log it. Marks come from the calendar (period, predicted, fertile, ovulation, logged).
const props = defineProps({
    today: { type: String, required: true },
    marks: { type: Object, default: () => ({}) },
});
const emit = defineEmits(['pick']);

const names = ['Min', 'Sen', 'Sel', 'Rab', 'Kam', 'Jum', 'Sab'];
const days = computed(() => {
    const base = new Date(`${props.today}T12:00:00Z`);
    return Array.from({ length: 7 }, (_, i) => {
        const d = new Date(base.getTime() + (i - 3) * 86400000);
        const key = d.toISOString().slice(0, 10);
        return { key, d: d.getUTCDate(), name: names[d.getUTCDay()], marks: props.marks[key] ?? [], future: key > props.today, today: key === props.today };
    });
});
function tone(day) {
    const m = day.marks;
    if (m.includes('period')) return 'bg-berry text-white';
    if (m.includes('predicted')) return 'border-2 border-dashed border-berry text-berry-ink';
    if (m.includes('ovulation')) return 'bg-lavender text-lavender-ink ring-2 ring-[#7a6ae0]';
    if (m.includes('fertile')) return 'bg-sage text-sage-ink';
    return 'glass text-plum';
}
</script>

<template>
    <div class="grid grid-cols-7 gap-1.5">
        <button v-for="day in days" :key="day.key" type="button" class="flex flex-col items-center gap-1 rounded-2xl py-1 transition active:scale-95" :disabled="day.future" :aria-label="day.key" @click="emit('pick', day.key)">
            <span class="text-[0.6875rem] font-semibold" :class="day.today ? 'text-berry-ink' : 'text-ink-muted'">{{ day.name }}</span>
            <span class="relative grid size-9 place-items-center rounded-full text-sm font-semibold tabular-nums" :class="[tone(day), day.today && 'outline outline-2 outline-offset-2 outline-plum']">
                {{ day.d }}
                <span v-if="day.marks.includes('logged')" class="absolute bottom-0.5 size-1 rounded-full bg-current" aria-hidden="true" />
            </span>
        </button>
    </div>
</template>
