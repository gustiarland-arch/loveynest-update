<script setup>
import { computed, onMounted, ref, watch } from 'vue';
import { Head, router, useForm } from '@inertiajs/vue3';
import { Droplets, Plus, GlassWater, Baby, Sparkles } from '@lucide/vue';
import PageHeader from '@/Components/layout/PageHeader.vue';
import Card from '@/Components/ui/Card.vue';
import Button from '@/Components/ui/Button.vue';
import Dialog from '@/Components/ui/Dialog.vue';
import DateField from '@/Components/ui/DateField.vue';
import ConfirmDialog from '@/Components/ui/ConfirmDialog.vue';
import SegmentedControl from '@/Components/ui/SegmentedControl.vue';
import TimeCard from '@/Components/time/TimeCard.vue';
import CycleDial from '@/Components/cycle/CycleDial.vue';
import WeekStrip from '@/Components/cycle/WeekStrip.vue';
import CycleCalendar from '@/Components/cycle/CycleCalendar.vue';
import InsightsPanel from '@/Components/cycle/InsightsPanel.vue';
import SettingsPanel from '@/Components/cycle/SettingsPanel.vue';
import LogSheet from '@/Components/cycle/LogSheet.vue';
import PinGate from '@/Components/cycle/PinGate.vue';
import CycleSetup from '@/Components/cycle/CycleSetup.vue';
import { http } from '@/lib/http';
import { formatDate } from '@/lib/dates';
import { t } from '@/i18n';

const props = defineProps({
    locked: { type: Boolean, default: false },
    summary: { type: Object, default: null },
    month: { type: String, default: '' },
    marks: { type: Object, default: () => ({}) },
    history: { type: Array, default: () => [] },
    options: { type: Object, required: true },
});

const tab = ref('today');
const tabs = computed(() => [
    { value: 'today', label: t('cycle.tabToday') },
    { value: 'calendar', label: t('cycle.tabCalendar') },
    { value: 'insights', label: t('cycle.tabInsights') },
    { value: 'settings', label: t('cycle.tabSettings') },
]);
const s = computed(() => props.summary);
const day = (key) => (key ? formatDate(`${key}T12:00:00Z`, { day: 'numeric', month: 'short' }, 'UTC') : '');

/* ---------- today ---------- */
const headline = computed(() => {
    const v = s.value;
    if (v.pregnancy) return t('cycle.dueOn', { date: day(v.pregnancy.due) });
    if (v.ongoing && v.cycleDay) return t('cycle.periodDay', { n: v.cycleDay });
    const n = v.nextPeriodIn;
    if (n === null) return t('cycle.startHint');
    if (n < 0) return t('cycle.lateBy', { n: Math.abs(n) });
    if (n === 0) return t('cycle.periodToday');
    return t('cycle.periodIn', { n });
});
const nextText = computed(() => {
    const n = s.value.nextPeriodIn;
    if (n === null) return { value: '–', unit: '' };
    if (n < 0) return { value: Math.abs(n), unit: t('cycle.lateDays') };
    if (n === 0) return { value: t('cycle.today'), unit: '' };
    return { value: n, unit: t('cycle.daysLeft') };
});
const ovulationText = computed(() => {
    const n = s.value.ovulationIn;
    if (n === null) return { value: '–', unit: '' };
    return n === 0 ? { value: t('cycle.today'), unit: '' } : { value: n, unit: t('cycle.daysLeft') };
});
const tip = computed(() => {
    const v = s.value;
    const phase = v.pregnancy ? 'pregnant' : (v.phase ?? 'start');
    return t(`cycle.tips.${phase}${((v.cycleDay ?? v.pregnancy?.week ?? 0) % 3) + 1}`);
});

const todayLog = ref(null);
async function loadToday() {
    try {
        todayLog.value = (await http.get(`/kesehatan/siklus/hari?date=${s.value.today}`)).log;
    } catch {
        todayLog.value = null;
    }
}
const todayWater = computed(() => todayLog.value?.water_ml ?? 0);
function addWater() {
    const log = todayLog.value ?? {};
    router.put('/kesehatan/siklus/catatan', { ...log, date: s.value.today, water_ml: Math.min(10000, (log.water_ml ?? 0) + 250) }, { preserveScroll: true, onSuccess: refresh });
}

/* ---------- period start/end ---------- */
const periodOpen = ref(false);
const periodDate = ref('');
const periodError = ref('');
function openPeriod() {
    periodDate.value = s.value.today;
    periodError.value = '';
    periodOpen.value = true;
}
function submitPeriod() {
    router.post(s.value.ongoing ? '/kesehatan/siklus/selesai' : '/kesehatan/siklus/mulai', { date: periodDate.value }, {
        preserveScroll: true,
        onError: (e) => (periodError.value = e.date ?? t('cycle.failed')),
        onSuccess: () => {
            periodOpen.value = false;
            refresh();
        },
    });
}

/* ---------- calendar ---------- */
const viewMonth = ref(props.month);
const viewMarks = ref(props.marks);
const loading = ref(false);
watch(() => props.marks, (next) => {
    if (viewMonth.value === props.month) viewMarks.value = next;
});
async function loadMonth(month = viewMonth.value) {
    loading.value = true;
    try {
        const result = await http.get(`/kesehatan/siklus/kalender?month=${month}`);
        viewMonth.value = month;
        viewMarks.value = result.marks;
    } catch {
        // keeps the month on screen
    } finally {
        loading.value = false;
    }
}
function shiftMonth(delta) {
    const [y, m] = viewMonth.value.split('-').map(Number);
    const d = new Date(Date.UTC(y, m - 1 + delta, 1));
    loadMonth(`${d.getUTCFullYear()}-${String(d.getUTCMonth() + 1).padStart(2, '0')}`);
}

/* ---------- log sheet ---------- */
const logOpen = ref(false);
const logDate = ref('');
function openLog(date) {
    if (date > s.value.today) return;
    logDate.value = date;
    logOpen.value = true;
}

/* ---------- history edit / remove ---------- */
const insights = ref(null);
const editing = ref(null);
const editForm = useForm({ started_on: '', ended_on: '' });
function openEdit(row) {
    editing.value = row;
    editForm.clearErrors();
    editForm.started_on = row.started_on;
    editForm.ended_on = row.ended_on ?? '';
}
function saveEdit() {
    editForm.transform((d) => ({ ...d, ended_on: d.ended_on || null })).patch(`/kesehatan/siklus/periode/${editing.value.id}`, { preserveScroll: true, onSuccess: () => { editing.value = null; refresh(); } });
}
const removing = ref(null);
function removePeriod() {
    const row = removing.value;
    removing.value = null;
    if (row) router.delete(`/kesehatan/siklus/periode/${row.id}`, { preserveScroll: true, onSuccess: refresh });
}

function refresh() {
    loadMonth();
    loadToday();
    insights.value?.load?.();
}
onMounted(() => {
    if (!props.locked && props.summary) loadToday();
});
const showSetup = computed(() => !props.locked && props.summary && !props.summary.onboarded);
</script>

<template>
    <Head :title="t('cycle.title')" />
    <PageHeader :title="t('cycle.title')" :lead="t('cycle.lead')" back="/pengaturan" />

    <PinGate v-if="locked" />
    <CycleSetup v-else-if="showSetup" :today="summary.today" />

    <template v-else>
        <SegmentedControl v-model="tab" :options="tabs" :label="t('cycle.title')" class="mb-4" />

        <!-- ================= Hari ini ================= -->
        <div v-show="tab === 'today'" class="space-y-4">
            <WeekStrip :today="s.today" :marks="viewMarks" @pick="openLog" />

            <Card class="gloss space-y-3 !pb-5 text-center">
                <CycleDial :day="s.cycleDay" :cycle="s.stats.avg_cycle" :period="s.stats.avg_period" :luteal="s.settings.luteal_length" :pregnant="!!s.pregnancy" :progress="s.pregnancy?.progress ?? 0">
                    <div v-if="s.pregnancy">
                        <p class="text-xs font-semibold uppercase tracking-wide text-ink-muted">{{ t('cycle.pregWeek') }}</p>
                        <p class="font-display text-5xl font-semibold leading-none text-plum">{{ s.pregnancy.week }}</p>
                        <p class="mt-1 text-xs text-ink-muted">{{ t('cycle.trimester', { n: s.pregnancy.trimester }) }}</p>
                    </div>
                    <div v-else-if="s.cycleDay">
                        <p class="text-xs font-semibold uppercase tracking-wide text-ink-muted">{{ t('cycle.day') }}</p>
                        <p class="font-display text-5xl font-semibold leading-none text-plum">{{ s.cycleDay }}</p>
                        <p class="mt-1 text-xs font-semibold text-berry-ink">{{ t(`cycle.phase.${s.phase}`) }}</p>
                    </div>
                    <p v-else class="text-sm font-semibold text-plum">{{ t('cycle.noData') }}</p>
                </CycleDial>
                <p class="font-display text-xl font-semibold text-plum">{{ headline }}</p>
                <p v-if="s.pregnancy" class="text-sm text-ink-muted">{{ t('cycle.pregDaysLeft', { n: s.pregnancy.daysLeft }) }}</p>
                <p v-else-if="s.fertility" class="mx-auto inline-flex items-center gap-1.5 rounded-full px-3 py-1 text-xs font-semibold" :class="s.fertility === 'low' ? 'bg-fill text-plum' : s.fertility === 'high' ? 'bg-sage text-sage-ink' : 'bg-lavender text-lavender-ink'">
                    <Baby class="size-3.5" aria-hidden="true" />{{ t('cycle.chance') }}: {{ t(`cycle.chances.${s.fertility}`) }}
                </p>
                <div class="flex flex-wrap justify-center gap-2 pt-1">
                    <Button v-if="!s.pregnancy" @click="openPeriod"><Droplets class="size-4" aria-hidden="true" />{{ s.ongoing ? t('cycle.endPeriod') : t('cycle.startPeriod') }}</Button>
                    <Button :variant="s.pregnancy ? 'primary' : 'secondary'" @click="openLog(s.today)"><Plus class="size-4" aria-hidden="true" />{{ t('cycle.logToday') }}</Button>
                </div>
            </Card>

            <div v-if="!s.pregnancy" class="grid grid-cols-3 gap-2">
                <TimeCard tone="rose" :icon="Droplets" :label="t('cycle.nextPeriod')" :value="nextText.value" :unit="nextText.unit" :sub="s.nextPeriod ? day(s.nextPeriod) : ''" />
                <TimeCard tone="lavender" :label="t('cycle.ovulation')" :value="ovulationText.value" :unit="ovulationText.unit" :sub="s.ovulation ? day(s.ovulation) : ''" />
                <TimeCard tone="sage" :label="t('cycle.fertile')" :value="s.fertileStart ? day(s.fertileStart) : '–'" :sub="s.fertileEnd ? `– ${day(s.fertileEnd)}` : ''" />
            </div>

            <Card class="flex items-center gap-3 !p-3.5">
                <span class="grid size-11 shrink-0 place-items-center rounded-2xl bg-[#dff1ff] text-[#2f7fc4]"><GlassWater class="size-5" aria-hidden="true" /></span>
                <div class="min-w-0 flex-1"><p class="text-sm font-semibold text-plum">{{ t('cycle.water') }}</p><p class="text-xs text-ink-muted"><span class="tabular-nums">{{ todayWater }}</span> ml</p></div>
                <Button size="sm" variant="secondary" @click="addWater">+ 250 ml</Button>
            </Card>

            <Card tone="lavender" class="space-y-1">
                <p class="flex items-center gap-1.5 text-xs font-semibold uppercase tracking-wide text-lavender-ink"><Sparkles class="size-4" aria-hidden="true" />{{ t('cycle.tipTitle') }}</p>
                <p class="text-sm text-plum">{{ tip }}</p>
            </Card>

            <p class="px-1 text-center text-xs text-ink-muted">{{ t('cycle.disclaimer') }}</p>
        </div>

        <!-- ================= Kalender ================= -->
        <div v-show="tab === 'calendar'" class="space-y-4">
            <CycleCalendar :month="viewMonth" :marks="viewMarks" :today="s.today" :loading="loading" @shift="shiftMonth" @pick="openLog" />
            <p class="px-1 text-center text-xs text-ink-muted">{{ t('cycle.calendarHint') }}</p>
        </div>

        <!-- ================= Analisis ================= -->
        <div v-show="tab === 'insights'">
            <InsightsPanel ref="insights" :history="history" @edit="openEdit" @remove="(row) => (removing = row)" />
        </div>

        <!-- ================= Pengaturan ================= -->
        <div v-show="tab === 'settings'">
            <SettingsPanel :summary="s" :options="options" @changed="refresh" />
        </div>

        <LogSheet v-model:open="logOpen" :date="logDate || s.today" :options="options" :birth-control="s.birthControl" @saved="refresh" />

        <Dialog v-model:open="periodOpen" :title="s.ongoing ? t('cycle.endPeriod') : t('cycle.startPeriod')">
            <DateField v-model="periodDate" :label="t('cycle.date')" :error="periodError || null" required />
            <template #footer>
                <Button variant="secondary" @click="periodOpen = false">{{ t('common.cancel') }}</Button>
                <Button @click="submitPeriod">{{ t('common.save') }}</Button>
            </template>
        </Dialog>

        <Dialog :open="!!editing" :title="t('cycle.editPeriod')" @update:open="(v) => !v && (editing = null)">
            <div class="space-y-4">
                <DateField v-model="editForm.started_on" :label="t('cycle.startDate')" :error="editForm.errors.started_on || editForm.errors.date" required />
                <DateField v-model="editForm.ended_on" :label="t('cycle.endDate')" clearable :error="editForm.errors.ended_on" />
            </div>
            <template #footer>
                <Button variant="secondary" @click="editing = null">{{ t('common.cancel') }}</Button>
                <Button :loading="editForm.processing" @click="saveEdit">{{ t('common.save') }}</Button>
            </template>
        </Dialog>
        <ConfirmDialog :open="!!removing" :title="t('cycle.removeTitle')" :description="t('cycle.removeBody')" :confirm-label="t('common.delete')" danger @update:open="(v) => !v && (removing = null)" @confirm="removePeriod" />
    </template>
</template>

<style>
.chip {
    display: inline-flex;
    min-height: 2.25rem;
    align-items: center;
    border-radius: 9999px;
    border: 0.5px solid var(--glass-edge);
    background: var(--glass-shine, none), var(--glass);
    -webkit-backdrop-filter: blur(var(--glass-blur, 20px)) saturate(var(--glass-sat, 1.6));
    backdrop-filter: blur(var(--glass-blur, 20px)) saturate(var(--glass-sat, 1.6));
    padding: 0 0.875rem;
    font-size: 0.8125rem;
    font-weight: 600;
    color: var(--color-plum);
    transition: transform 0.15s, background-color 0.2s, color 0.2s;
}
.chip:active {
    transform: scale(0.95);
}
.chip.on {
    background: var(--color-berry);
    border-color: var(--color-berry);
    color: #fff;
}
</style>
