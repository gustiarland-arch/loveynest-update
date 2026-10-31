<script setup>
import { computed, ref } from 'vue';
import { router, useForm } from '@inertiajs/vue3';
import { Baby, Download, LockKeyhole, ShieldCheck, Trash2 } from '@lucide/vue';
import Card from '@/Components/ui/Card.vue';
import Button from '@/Components/ui/Button.vue';
import Dialog from '@/Components/ui/Dialog.vue';
import DateField from '@/Components/ui/DateField.vue';
import TextField from '@/Components/ui/TextField.vue';
import TimeField from '@/Components/ui/TimeField.vue';
import Toggle from '@/Components/ui/Toggle.vue';
import ConfirmDialog from '@/Components/ui/ConfirmDialog.vue';
import SectionTitle from '@/Components/layout/SectionTitle.vue';
import { t } from '@/i18n';

const props = defineProps({ summary: { type: Object, required: true }, options: { type: Object, required: true } });
const emit = defineEmits(['changed']);
const after = { preserveScroll: true, onSuccess: () => emit('changed') };

/* ---- cycle ---- */
const s = computed(() => props.summary);
const cycle = useForm({
    cycle_length: props.summary.settings.cycle_length, period_length: props.summary.settings.period_length, luteal_length: props.summary.settings.luteal_length,
    birth_control: props.summary.birthControl ?? 'none', goal: props.summary.goal === 'conceive' ? 'conceive' : 'track',
});
const saveCycle = () => cycle.transform((d) => ({ ...d, birth_control: d.birth_control === 'none' ? null : d.birth_control })).patch('/kesehatan/siklus/pengaturan', after);

/* ---- reminders ---- */
const rem = useForm(JSON.parse(JSON.stringify(props.summary.reminders)));
const saveReminders = () => rem.patch('/kesehatan/siklus/pengingat', after);

/* ---- pregnancy ---- */
const pregOpen = ref(false);
const preg = useForm({ lmp: '' });
const startPregnancy = () => preg.post('/kesehatan/siklus/kehamilan', { ...after, onSuccess: () => { pregOpen.value = false; emit('changed'); } });
const endPregOpen = ref(false);
const endPregnancy = () => { endPregOpen.value = false; router.delete('/kesehatan/siklus/kehamilan', after); };

/* ---- PIN ---- */
const pinOpen = ref(false);
const pin = useForm({ pin: '', pin_confirmation: '', current_pin: '' });
const savePin = () => pin.post('/kesehatan/siklus/pin', { preserveScroll: true, onSuccess: () => { pinOpen.value = false; pin.reset(); emit('changed'); } });
const rmPinOpen = ref(false);
const rmPin = useForm({ pin: '' });
const removePin = () => rmPin.delete('/kesehatan/siklus/pin', { preserveScroll: true, onSuccess: () => { rmPinOpen.value = false; rmPin.reset(); emit('changed'); } });

/* ---- data ---- */
const wipeOpen = ref(false);
const wipe = () => { wipeOpen.value = false; router.delete('/kesehatan/siklus/data', after); };
</script>

<template>
    <div class="space-y-4">
        <!-- cycle -->
        <Card class="space-y-4">
            <h3 class="font-display text-lg font-semibold text-plum">{{ t('cycle.adjust') }}</h3>
            <p class="-mt-2 text-sm text-ink-muted">{{ t('cycle.adjustHint') }}</p>
            <div class="grid grid-cols-3 gap-2">
                <TextField v-model.number="cycle.cycle_length" :label="t('cycle.setCycle')" type="number" inputmode="numeric" :error="cycle.errors.cycle_length" />
                <TextField v-model.number="cycle.period_length" :label="t('cycle.setPeriod')" type="number" inputmode="numeric" :error="cycle.errors.period_length" />
                <TextField v-model.number="cycle.luteal_length" :label="t('cycle.setLuteal')" type="number" inputmode="numeric" :error="cycle.errors.luteal_length" />
            </div>
            <p class="-mt-2 text-xs text-ink-muted">{{ t('cycle.setLutealHint') }}</p>
            <fieldset>
                <legend class="mb-1.5 text-sm font-semibold text-plum">{{ t('cycle.birthControl') }}</legend>
                <div class="flex flex-wrap gap-2"><button v-for="b in options.birthControl" :key="b" type="button" class="chip" :class="{ on: cycle.birth_control === b }" @click="cycle.birth_control = b">{{ t(`cycle.birthControls.${b}`) }}</button></div>
            </fieldset>
            <fieldset v-if="!s.pregnancy">
                <legend class="mb-1.5 text-sm font-semibold text-plum">{{ t('cycle.goal') }}</legend>
                <div class="flex flex-wrap gap-2"><button v-for="g in ['track', 'conceive']" :key="g" type="button" class="chip" :class="{ on: cycle.goal === g }" @click="cycle.goal = g">{{ t(`cycle.goals.${g}`) }}</button></div>
            </fieldset>
            <Button :loading="cycle.processing" @click="saveCycle">{{ t('common.save') }}</Button>
        </Card>

        <!-- reminders -->
        <Card class="space-y-1">
            <h3 class="font-display text-lg font-semibold text-plum">{{ t('cycle.reminders') }}</h3>
            <p class="text-sm text-ink-muted">{{ t('cycle.remindersHint') }}</p>
            <Toggle v-model="rem.period.on" :label="t('cycle.remPeriod')" />
            <div v-if="rem.period.on" class="pb-2"><TextField v-model.number="rem.period.days_before" :label="t('cycle.remDaysBefore')" type="number" inputmode="numeric" :error="rem.errors['period.days_before']" /></div>
            <Toggle v-model="rem.ovulation.on" :label="t('cycle.remOvulation')" />
            <Toggle v-model="rem.pill.on" :label="t('cycle.remPill')" />
            <div v-if="rem.pill.on" class="pb-2">
                <TimeField v-model="rem.pill.time" :label="t('cycle.remTime')" />
                <p v-if="s.birthControl !== 'pill'" class="mt-1 text-xs text-ink-muted">{{ t('cycle.remPillNeeds') }}</p>
            </div>
            <Toggle v-model="rem.water.on" :label="t('cycle.remWater')" />
            <Toggle v-model="rem.log.on" :label="t('cycle.remLog')" />
            <div v-if="rem.log.on" class="pb-2"><TimeField v-model="rem.log.time" :label="t('cycle.remTime')" /></div>
            <Toggle v-model="rem.discreet" :label="t('cycle.remDiscreet')" />
            <p class="text-xs text-ink-muted">{{ t('cycle.remDiscreetHint') }}</p>
            <Button class="mt-2" :loading="rem.processing" @click="saveReminders">{{ t('common.save') }}</Button>
        </Card>

        <!-- pregnancy -->
        <Card class="space-y-3">
            <h3 class="flex items-center gap-2 font-display text-lg font-semibold text-plum"><Baby class="size-5 text-berry-ink" aria-hidden="true" />{{ t('cycle.pregnancyMode') }}</h3>
            <p class="text-sm text-ink-muted">{{ s.pregnancy ? t('cycle.pregnancyOn') : t('cycle.pregnancyOff') }}</p>
            <Button v-if="!s.pregnancy" variant="secondary" @click="pregOpen = true">{{ t('cycle.pregnancyStart') }}</Button>
            <Button v-else variant="danger-ghost" @click="endPregOpen = true">{{ t('cycle.pregnancyEnd') }}</Button>
        </Card>

        <!-- PIN -->
        <Card class="space-y-3">
            <h3 class="flex items-center gap-2 font-display text-lg font-semibold text-plum"><LockKeyhole class="size-5 text-berry-ink" aria-hidden="true" />{{ t('cycle.pinTitle') }}</h3>
            <p class="text-sm text-ink-muted">{{ t('cycle.pinBody') }}</p>
            <div class="flex flex-wrap gap-2">
                <Button variant="secondary" @click="pinOpen = true">{{ s.hasPin ? t('cycle.changePin') : t('cycle.setPin') }}</Button>
                <Button v-if="s.hasPin" variant="danger-ghost" @click="rmPinOpen = true">{{ t('cycle.removePin') }}</Button>
            </div>
        </Card>

        <!-- privacy and data -->
        <Card tone="lavender" class="space-y-2 text-sm text-lavender-ink">
            <p class="flex items-center gap-2 font-semibold"><ShieldCheck class="size-5" aria-hidden="true" />{{ t('cycle.privacyTitle') }}</p>
            <p>{{ t('cycle.privacyBody') }}</p>
            <p class="font-semibold">{{ t('cycle.disclaimer') }}</p>
            <div class="flex flex-wrap gap-2 pt-1">
                <a href="/kesehatan/siklus/ekspor" class="glass inline-flex h-9 items-center gap-1.5 rounded-full px-4 text-sm font-semibold text-berry-ink"><Download class="size-4" aria-hidden="true" />{{ t('cycle.export') }}</a>
                <Button variant="danger-ghost" size="sm" @click="wipeOpen = true"><Trash2 class="size-4" aria-hidden="true" />{{ t('cycle.wipe') }}</Button>
            </div>
        </Card>

        <Dialog v-model:open="pregOpen" :title="t('cycle.pregnancyStart')" :description="t('cycle.pregnancyLmpHint')">
            <DateField v-model="preg.lmp" :label="t('cycle.pregnancyLmp')" :error="preg.errors.lmp" required />
            <template #footer><Button variant="secondary" @click="pregOpen = false">{{ t('common.cancel') }}</Button><Button :loading="preg.processing" :disabled="!preg.lmp" @click="startPregnancy">{{ t('common.save') }}</Button></template>
        </Dialog>
        <Dialog v-model:open="pinOpen" :title="s.hasPin ? t('cycle.changePin') : t('cycle.setPin')" :description="t('cycle.pinHint')">
            <div class="space-y-3">
                <TextField v-if="s.hasPin" v-model="pin.current_pin" :label="t('cycle.currentPin')" type="password" inputmode="numeric" :maxlength="6" :error="pin.errors.current_pin" />
                <TextField v-model="pin.pin" :label="t('cycle.newPin')" type="password" inputmode="numeric" :maxlength="6" :error="pin.errors.pin" />
                <TextField v-model="pin.pin_confirmation" :label="t('cycle.confirmPin')" type="password" inputmode="numeric" :maxlength="6" />
            </div>
            <template #footer><Button variant="secondary" @click="pinOpen = false">{{ t('common.cancel') }}</Button><Button :loading="pin.processing" :disabled="pin.pin.length < 4" @click="savePin">{{ t('common.save') }}</Button></template>
        </Dialog>
        <Dialog v-model:open="rmPinOpen" :title="t('cycle.removePin')">
            <TextField v-model="rmPin.pin" :label="t('cycle.currentPin')" type="password" inputmode="numeric" :maxlength="6" :error="rmPin.errors.pin" />
            <template #footer><Button variant="secondary" @click="rmPinOpen = false">{{ t('common.cancel') }}</Button><Button variant="danger" :loading="rmPin.processing" :disabled="rmPin.pin.length < 4" @click="removePin">{{ t('cycle.removePin') }}</Button></template>
        </Dialog>
        <ConfirmDialog v-model:open="endPregOpen" :title="t('cycle.pregnancyEnd')" :description="t('cycle.pregnancyEndBody')" :confirm-label="t('cycle.pregnancyEnd')" danger @confirm="endPregnancy" />
        <ConfirmDialog v-model:open="wipeOpen" :title="t('cycle.wipeTitle')" :description="t('cycle.wipeBody')" :confirm-label="t('cycle.wipe')" danger @confirm="wipe" />
    </div>
</template>
