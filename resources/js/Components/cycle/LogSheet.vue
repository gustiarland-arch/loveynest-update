<script setup>
import { ref, watch } from 'vue';
import { useForm } from '@inertiajs/vue3';
import { Minus, Plus } from '@lucide/vue';
import Dialog from '@/Components/ui/Dialog.vue';
import Button from '@/Components/ui/Button.vue';
import TextField from '@/Components/ui/TextField.vue';
import TextArea from '@/Components/ui/TextArea.vue';
import { http } from '@/lib/http';
import { formatDate } from '@/lib/dates';
import { t } from '@/i18n';

/** The daily log: every category of the tracker in one sheet. Saved per day; empty fields are simply not stored. */
const open = defineModel('open', { type: Boolean, default: false });
const props = defineProps({
    date: { type: String, required: true },
    options: { type: Object, required: true },
    birthControl: { type: String, default: null },
});
const emit = defineEmits(['saved']);

const blank = () => ({
    date: '', flow: null, moods: [], cramps: null, skin: null, energy: null, sleep: null, symptoms: [], sex: [], discharge: null, digestion: [],
    pregnancy_test: null, ovulation_test: null, activity: [], other: [], pill_taken: null, water_ml: null, weight_kg: '', temperature_c: '', sleep_hours: '', note: '',
});
const form = useForm(blank());
const loading = ref(false);

const title = () => formatDate(`${props.date}T12:00:00Z`, { weekday: 'long', day: 'numeric', month: 'long' }, 'UTC');

watch(open, async (isOpen) => {
    if (!isOpen) return;
    form.defaults(blank());
    form.reset();
    form.clearErrors();
    form.date = props.date;
    loading.value = true;
    try {
        const result = await http.get(`/kesehatan/siklus/hari?date=${props.date}`);
        if (result.log) {
            const log = result.log;
            Object.assign(form, { ...log, note: log.note ?? '', weight_kg: log.weight_kg ?? '', temperature_c: log.temperature_c ?? '', sleep_hours: log.sleep_hours ?? '' });
        }
    } catch {
        // an empty form is fine
    } finally {
        loading.value = false;
    }
});

const pick = (field, value) => (form[field] = form[field] === value ? null : value);
const toggle = (field, value) => (form[field] = form[field].includes(value) ? form[field].filter((v) => v !== value) : [...form[field], value]);
const glass = () => Math.round((form.water_ml ?? 0) / 250);
const water = (delta) => (form.water_ml = Math.max(0, Math.min(10000, (form.water_ml ?? 0) + delta)) || null);

function save() {
    form
        .transform((data) => ({ ...data, weight_kg: data.weight_kg === '' ? null : data.weight_kg, temperature_c: data.temperature_c === '' ? null : data.temperature_c, sleep_hours: data.sleep_hours === '' ? null : data.sleep_hours }))
        .put('/kesehatan/siklus/catatan', {
            preserveScroll: true,
            onSuccess: () => {
                open.value = false;
                emit('saved');
            },
        });
}

// Groups of chips: [form field, option list, i18n namespace, multi?]
const groups = [
    ['moods', 'moods', 'moods', true, 'mood'],
    ['symptoms', 'symptoms', 'symptomNames', true, 'symptoms'],
    ['sex', 'sex', 'sexNames', true, 'sexTitle'],
    ['discharge', 'discharge', 'dischargeNames', false, 'discharge'],
    ['digestion', 'digestion', 'digestionNames', true, 'digestion'],
    ['pregnancy_test', 'pregnancyTest', 'pregTestNames', false, 'pregTest'],
    ['ovulation_test', 'ovulationTest', 'ovuTestNames', false, 'ovuTest'],
    ['activity', 'activity', 'activityNames', true, 'activity'],
    ['other', 'other', 'otherNames', true, 'otherTitle'],
    ['skin', 'skin', 'skinTypes', false, 'skin'],
];
</script>

<template>
    <Dialog v-model:open="open" :title="title()">
        <form class="space-y-5" :class="{ 'opacity-60': loading }" @submit.prevent="save">
            <fieldset>
                <legend class="mb-1.5 text-sm font-semibold text-plum">{{ t('cycle.flow') }}</legend>
                <div class="flex flex-wrap gap-2"><button v-for="n in [0, 1, 2, 3]" :key="n" type="button" class="chip" :class="{ on: form.flow === n }" @click="pick('flow', n)">{{ t(`cycle.flowLevels.${n}`) }}</button></div>
            </fieldset>

            <fieldset v-for="[field, optionKey, ns, multi, label] in groups" :key="field">
                <legend class="mb-1.5 text-sm font-semibold text-plum">{{ t(`cycle.${label}`) }}</legend>
                <div class="flex flex-wrap gap-2">
                    <button v-for="k in options[optionKey]" :key="k" type="button" class="chip" :class="{ on: multi ? form[field].includes(k) : form[field] === k }" @click="multi ? toggle(field, k) : pick(field, k)">{{ t(`cycle.${ns}.${k}`) }}</button>
                </div>
            </fieldset>

            <fieldset>
                <legend class="mb-1.5 text-sm font-semibold text-plum">{{ t('cycle.cramps') }}</legend>
                <div class="flex flex-wrap gap-2"><button v-for="n in [0, 1, 2, 3]" :key="n" type="button" class="chip" :class="{ on: form.cramps === n }" @click="pick('cramps', n)">{{ t(`cycle.crampLevels.${n}`) }}</button></div>
            </fieldset>
            <fieldset>
                <legend class="mb-1.5 text-sm font-semibold text-plum">{{ t('cycle.energy') }}</legend>
                <div class="flex gap-2"><button v-for="n in 5" :key="n" type="button" class="chip flex-1 justify-center" :class="{ on: form.energy === n }" @click="pick('energy', n)">{{ n }}</button></div>
            </fieldset>
            <fieldset>
                <legend class="mb-1.5 text-sm font-semibold text-plum">{{ t('cycle.sleep') }}</legend>
                <div class="flex gap-2"><button v-for="n in 5" :key="n" type="button" class="chip flex-1 justify-center" :class="{ on: form.sleep === n }" @click="pick('sleep', n)">{{ n }}</button></div>
            </fieldset>

            <fieldset v-if="birthControl === 'pill'">
                <legend class="mb-1.5 text-sm font-semibold text-plum">{{ t('cycle.pill') }}</legend>
                <div class="flex gap-2">
                    <button type="button" class="chip" :class="{ on: form.pill_taken === true }" @click="form.pill_taken = form.pill_taken === true ? null : true">{{ t('cycle.pillTaken') }}</button>
                    <button type="button" class="chip" :class="{ on: form.pill_taken === false }" @click="form.pill_taken = form.pill_taken === false ? null : false">{{ t('cycle.pillMissed') }}</button>
                </div>
            </fieldset>

            <fieldset>
                <legend class="mb-1.5 text-sm font-semibold text-plum">{{ t('cycle.water') }}</legend>
                <div class="glass flex items-center justify-between rounded-2xl px-3 py-2">
                    <button type="button" class="grid size-9 place-items-center rounded-full bg-fill" :aria-label="t('cycle.less')" @click="water(-250)"><Minus class="size-4" aria-hidden="true" /></button>
                    <p class="text-center"><span class="font-display text-xl font-semibold tabular-nums text-plum">{{ form.water_ml ?? 0 }}</span> <span class="text-xs text-ink-muted">ml · {{ t('cycle.glasses', { n: glass() }) }}</span></p>
                    <button type="button" class="grid size-9 place-items-center rounded-full bg-berry text-white" :aria-label="t('cycle.more')" @click="water(250)"><Plus class="size-4" aria-hidden="true" /></button>
                </div>
            </fieldset>
            <div class="grid grid-cols-3 gap-2">
                <TextField v-model="form.weight_kg" :label="t('cycle.weight')" type="number" inputmode="decimal" :error="form.errors.weight_kg" />
                <TextField v-model="form.temperature_c" :label="t('cycle.temperature')" type="number" inputmode="decimal" :error="form.errors.temperature_c" />
                <TextField v-model="form.sleep_hours" :label="t('cycle.sleepHours')" type="number" inputmode="decimal" :error="form.errors.sleep_hours" />
            </div>

            <TextArea v-model="form.note" :label="t('cycle.note')" :maxlength="1000" :error="form.errors.note" />
            <p v-if="form.errors.date" class="text-sm text-danger" role="alert">{{ form.errors.date }}</p>
            <div class="flex justify-end gap-2">
                <Button type="button" variant="secondary" @click="open = false">{{ t('common.cancel') }}</Button>
                <Button type="submit" :loading="form.processing">{{ t('common.save') }}</Button>
            </div>
        </form>
    </Dialog>
</template>
