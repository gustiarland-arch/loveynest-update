<script setup>
import { computed } from 'vue';
import { ChevronLeft, ChevronRight } from '@lucide/vue';
import Card from '@/Components/ui/Card.vue';
import { formatDate } from '@/lib/dates';
import { t } from '@/i18n';

const props = defineProps({
    month: { type: String, required: true },
    marks: { type: Object, required: true },
    today: { type: String, required: true },
    loading: { type: Boolean, default: false },
});
const emit = defineEmits(['shift', 'pick']);

const title = computed(() => formatDate(`${props.month}-15T12:00:00Z`, { month: 'long', year: 'numeric' }, 'UTC'));
const weekdays = ['Sen', 'Sel', 'Rab', 'Kam', 'Jum', 'Sab', 'Min'];
const cells = computed(() => {
    const [y, m] = props.month.split('-').map(Number);
    const offset = (new Date(Date.UTC(y, m - 1, 1)).getUTCDay() + 6) % 7;
    const total = new Date(Date.UTC(y, m, 0)).getUTCDate();
    const list = Array.from({ length: offset }, () => null);
    for (let d = 1; d <= total; d++) {
        const key = `${props.month}-${String(d).padStart(2, '0')}`;
        list.push({ d, key, marks: props.marks[key] ?? [] });
    }
    return list;
});
function tone(cell) {
    const m = cell.marks;
    return [
        m.includes('period') ? 'bg-berry text-white' : m.includes('predicted') ? 'border-2 border-dashed border-berry text-berry-ink' : m.includes('ovulation') ? 'bg-lavender text-lavender-ink ring-2 ring-[#7a6ae0]' : m.includes('fertile') ? 'bg-sage text-sage-ink' : 'text-plum',
        m.includes('today') && 'outline outline-2 outline-offset-1 outline-plum',
    ];
}
</script>

<template>
    <Card>
        <div class="mb-3 flex items-center justify-between">
            <button type="button" class="grid size-10 place-items-center rounded-full hover:bg-fill" :aria-label="t('cycle.prevMonth')" @click="emit('shift', -1)"><ChevronLeft class="size-5" aria-hidden="true" /></button>
            <h2 class="font-display text-lg font-semibold capitalize text-plum">{{ title }}</h2>
            <button type="button" class="grid size-10 place-items-center rounded-full hover:bg-fill" :aria-label="t('cycle.nextMonth')" @click="emit('shift', 1)"><ChevronRight class="size-5" aria-hidden="true" /></button>
        </div>
        <div class="grid grid-cols-7 gap-1 text-center text-[0.6875rem] font-semibold text-ink-muted"><span v-for="w in weekdays" :key="w">{{ w }}</span></div>
        <div class="mt-1 grid grid-cols-7 gap-1" :class="{ 'opacity-60': loading }">
            <template v-for="(cell, i) in cells" :key="i">
                <span v-if="!cell" />
                <button v-else type="button" class="relative grid aspect-square place-items-center rounded-full text-sm font-semibold tabular-nums transition active:scale-90" :class="tone(cell)" :disabled="cell.key > today" :aria-label="cell.key" @click="emit('pick', cell.key)">
                    {{ cell.d }}
                    <span v-if="cell.marks.includes('logged')" class="absolute bottom-0.5 size-1 rounded-full bg-current" aria-hidden="true" />
                </button>
            </template>
        </div>
        <ul class="mt-4 flex flex-wrap gap-x-4 gap-y-1.5 text-xs text-ink-muted">
            <li class="flex items-center gap-1.5"><span class="size-3 rounded-full bg-berry" />{{ t('cycle.legend.period') }}</li>
            <li class="flex items-center gap-1.5"><span class="size-3 rounded-full border-2 border-dashed border-berry" />{{ t('cycle.legend.predicted') }}</li>
            <li class="flex items-center gap-1.5"><span class="size-3 rounded-full bg-sage" />{{ t('cycle.legend.fertile') }}</li>
            <li class="flex items-center gap-1.5"><span class="size-3 rounded-full bg-lavender ring-2 ring-[#7a6ae0]" />{{ t('cycle.legend.ovulation') }}</li>
        </ul>
    </Card>
</template>
