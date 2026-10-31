<script setup>
import { computed, onMounted, ref } from 'vue';
import { Flame, Pencil, Trash2, TrendingUp, TrendingDown, Minus, Activity } from '@lucide/vue';
import Card from '@/Components/ui/Card.vue';
import SectionTitle from '@/Components/layout/SectionTitle.vue';
import TimeCard from '@/Components/time/TimeCard.vue';
import SparkLine from '@/Components/cycle/SparkLine.vue';
import AiAnalysis from '@/Components/cycle/AiAnalysis.vue';
import AskAi from '@/Components/cycle/AskAi.vue';
import { http } from '@/lib/http';
import { formatDate } from '@/lib/dates';
import { t } from '@/i18n';

const props = defineProps({ history: { type: Array, required: true }, reloadKey: { type: Number, default: 0 } });
const emit = defineEmits(['edit', 'remove']);

const data = ref(null);
const failed = ref(false);
async function load() {
    try {
        data.value = await http.get('/kesehatan/siklus/analisis');
        failed.value = false;
    } catch {
        failed.value = true;
    }
}
onMounted(load);
defineExpose({ load });

const day = (key) => (key ? formatDate(`${key}T12:00:00Z`, { day: 'numeric', month: 'short' }, 'UTC') : '');
const maxLength = computed(() => Math.max(40, ...(data.value?.cycles ?? []).map((c) => c.length ?? 0)));
const barHeight = (value) => `${Math.max(6, (value / maxLength.value) * 100)}%`;
const stat = computed(() => data.value?.stats);
const trend = computed(() => {
    const n = data.value?.trend;
    if (n === null || n === undefined) return null;
    return n === 0 ? { icon: Minus, text: t('cycle.trendSame') } : n > 0 ? { icon: TrendingUp, text: t('cycle.trendLonger', { n }) } : { icon: TrendingDown, text: t('cycle.trendShorter', { n: Math.abs(n) }) };
});
const maxCount = (list) => Math.max(1, ...list.map((i) => i.count));
const phaseOrder = ['menstrual', 'follicular', 'fertile', 'luteal'];
const series = [['weight', '#6b5bd9', 'kg'], ['temperature', '#e0245e', '°C'], ['water', '#2f8fe6', 'ml'], ['sleep', '#3f9d6b', 'jam']];
</script>

<template>
    <p v-if="failed" class="glass rounded-2xl p-4 text-sm text-ink-muted">{{ t('cycle.failed') }}</p>
    <div v-else-if="!data" class="glass h-40 animate-pulse rounded-2xl" />
    <div v-else class="space-y-4">
        <AiAnalysis />
        <AskAi />
        <div class="grid grid-cols-2 gap-2">
            <TimeCard tone="rose" :icon="Activity" :label="t('cycle.avgCycle')" :value="stat.avg_cycle" :unit="t('cycle.days')" :sub="stat.learned ? '' : t('cycle.fromSettings')" />
            <TimeCard tone="sun" :icon="Flame" :label="t('cycle.streak')" :value="data.streak" :unit="t('cycle.days')" :sub="t('cycle.logged', { n: data.logCount })" />
            <TimeCard tone="lavender" :label="t('cycle.regular')" :value="stat.regularity ? t(`cycle.regularity.${stat.regularity}`) : '–'" :sub="stat.spread !== null ? t('cycle.spread', { n: stat.spread }) : t('cycle.needMore')" />
            <TimeCard tone="sage" :label="t('cycle.avgPeriod')" :value="stat.avg_period" :unit="t('cycle.days')" :sub="stat.shortest ? `${stat.shortest}–${stat.longest} ${t('cycle.days')}` : ''" />
        </div>
        <p v-if="trend" class="glass flex items-center gap-2 rounded-2xl px-4 py-3 text-sm font-semibold text-plum"><component :is="trend.icon" class="size-4 text-berry-ink" aria-hidden="true" />{{ trend.text }}</p>

        <Card v-if="data.cycles.length">
            <h3 class="mb-3 text-sm font-semibold text-plum">{{ t('cycle.cycleLengths') }}</h3>
            <div class="flex h-32 items-end gap-1.5" role="img" :aria-label="t('cycle.cycleLengths')">
                <div v-for="c in data.cycles" :key="c.start" class="flex h-full flex-1 flex-col items-center justify-end gap-1">
                    <span class="text-[0.625rem] tabular-nums text-ink-muted">{{ c.length ?? '…' }}</span>
                    <span class="w-full rounded-t-lg" :class="c.length ? 'bg-berry/80' : 'bg-fill'" :style="{ height: barHeight(c.length ?? 8) }" />
                    <span class="text-[0.5625rem] text-ink-muted">{{ day(c.start).split(' ')[1] }}</span>
                </div>
            </div>
            <p class="mt-2 text-xs text-ink-muted">{{ t('cycle.cycleLengthsHint') }}</p>
        </Card>

        <template v-if="data.logCount">
            <SectionTitle :title="t('cycle.byPhase')" />
            <div class="grid gap-2">
                <Card v-for="ph in phaseOrder" :key="ph" class="!p-3.5">
                    <div class="flex items-center justify-between">
                        <p class="font-semibold text-plum">{{ t(`cycle.phase.${ph}`) }}</p>
                        <p class="text-xs text-ink-muted">{{ t('cycle.loggedDays', { n: data.phases[ph].days }) }}</p>
                    </div>
                    <p v-if="!data.phases[ph].days" class="mt-1 text-xs text-ink-muted">{{ t('cycle.noPhaseData') }}</p>
                    <template v-else>
                        <p v-if="data.phases[ph].symptoms.length" class="mt-1 text-sm text-plum">{{ data.phases[ph].symptoms.map((s) => t(`cycle.symptomNames.${s.key}`)).join(', ') }}</p>
                        <p class="mt-1 flex flex-wrap gap-x-3 text-xs text-ink-muted">
                            <span v-if="data.phases[ph].moods.length">{{ t('cycle.mood') }}: {{ data.phases[ph].moods.map((m) => t(`cycle.moods.${m.key}`)).join(', ') }}</span>
                            <span v-if="data.phases[ph].energy !== null">{{ t('cycle.energyShort') }} {{ data.phases[ph].energy }}/5</span>
                            <span v-if="data.phases[ph].cramps !== null">{{ t('cycle.crampsShort') }} {{ data.phases[ph].cramps }}/3</span>
                        </p>
                    </template>
                </Card>
            </div>

            <Card v-if="data.topSymptoms.length" class="space-y-2">
                <h3 class="text-sm font-semibold text-plum">{{ t('cycle.topSymptoms') }}</h3>
                <div v-for="s in data.topSymptoms" :key="s.key" class="flex items-center gap-2 text-xs">
                    <span class="w-28 shrink-0 truncate text-plum">{{ t(`cycle.symptomNames.${s.key}`) }}</span>
                    <span class="h-2 rounded-full bg-berry/70" :style="{ width: `${(s.count / maxCount(data.topSymptoms)) * 100}%` }" /><span class="tabular-nums text-ink-muted">{{ s.count }}</span>
                </div>
            </Card>
            <Card v-if="data.topMoods.length" class="space-y-2">
                <h3 class="text-sm font-semibold text-plum">{{ t('cycle.topMoods') }}</h3>
                <div v-for="m in data.topMoods" :key="m.key" class="flex items-center gap-2 text-xs">
                    <span class="w-28 shrink-0 truncate text-plum">{{ t(`cycle.moods.${m.key}`) }}</span>
                    <span class="h-2 rounded-full bg-[#7a6ae0]/70" :style="{ width: `${(m.count / maxCount(data.topMoods)) * 100}%` }" /><span class="tabular-nums text-ink-muted">{{ m.count }}</span>
                </div>
            </Card>
        </template>
        <p v-else class="glass rounded-2xl p-4 text-sm text-ink-muted">{{ t('cycle.insightsEmpty') }}</p>

        <template v-for="[key, color, unit] in series" :key="key">
            <Card v-if="data.series[key].length">
                <h3 class="mb-1 text-sm font-semibold text-plum">{{ t(`cycle.series.${key}`) }} <span class="font-normal text-ink-muted">· {{ t('cycle.last30') }}</span></h3>
                <SparkLine :points="data.series[key]" :color="color" :unit="unit" />
            </Card>
        </template>

        <SectionTitle :title="t('cycle.history')" />
        <p v-if="!history.length" class="glass rounded-2xl p-4 text-sm text-ink-muted">{{ t('cycle.historyEmpty') }}</p>
        <ul v-else class="space-y-2">
            <li v-for="row in history" :key="row.id" class="glass flex items-center gap-3 rounded-[1.25rem] p-3.5">
                <div class="min-w-0 flex-1">
                    <p class="font-semibold text-plum">{{ day(row.started_on) }}<template v-if="row.ended_on"> – {{ day(row.ended_on) }}</template><template v-else> · {{ t('cycle.ongoing') }}</template></p>
                    <p class="text-xs text-ink-muted"><template v-if="row.period_days">{{ t('cycle.periodDays', { n: row.period_days }) }}</template><template v-if="row.cycle_days"> · {{ t('cycle.cycleDays', { n: row.cycle_days }) }}</template></p>
                </div>
                <button type="button" class="grid size-9 place-items-center rounded-full hover:bg-fill" :aria-label="t('common.edit')" @click="emit('edit', row)"><Pencil class="size-4" aria-hidden="true" /></button>
                <button type="button" class="grid size-9 place-items-center rounded-full text-danger hover:bg-danger-soft" :aria-label="t('common.delete')" @click="emit('remove', row)"><Trash2 class="size-4" aria-hidden="true" /></button>
            </li>
        </ul>
    </div>
</template>
