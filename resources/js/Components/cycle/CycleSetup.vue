<script setup>
import { ref } from 'vue';
import { useForm } from '@inertiajs/vue3';
import { Flower2, Baby, CalendarHeart } from '@lucide/vue';
import Card from '@/Components/ui/Card.vue';
import Button from '@/Components/ui/Button.vue';
import DateField from '@/Components/ui/DateField.vue';
import { t } from '@/i18n';

// First run: three short questions, then the tracker starts predicting.
const props = defineProps({ today: { type: String, required: true } });
const step = ref(0);
const form = useForm({ last_start: '', period_length: 5, cycle_length: 28, goal: 'track' });
const stepper = (field, delta, min, max) => (form[field] = Math.max(min, Math.min(max, form[field] + delta)));
const submit = () => form.transform((d) => ({ ...d, last_start: d.last_start || null })).post('/kesehatan/siklus/siapkan', { preserveScroll: true });
</script>

<template>
    <Card class="mx-auto mt-2 max-w-md space-y-5">
        <div class="flex gap-1.5" aria-hidden="true"><span v-for="n in 3" :key="n" class="h-1.5 flex-1 rounded-full" :class="n <= step + 1 ? 'bg-berry' : 'bg-fill'" /></div>

        <template v-if="step === 0">
            <span class="grid size-12 place-items-center rounded-2xl bg-blush text-berry-ink"><CalendarHeart class="size-6" aria-hidden="true" /></span>
            <div>
                <h2 class="font-display text-xl font-semibold text-plum">{{ t('cycle.setup.q1') }}</h2>
                <p class="text-sm text-ink-muted">{{ t('cycle.setup.q1Hint') }}</p>
            </div>
            <DateField v-model="form.last_start" :label="t('cycle.startDate')" clearable :error="form.errors.last_start" />
        </template>

        <template v-else-if="step === 1">
            <div>
                <h2 class="font-display text-xl font-semibold text-plum">{{ t('cycle.setup.q2') }}</h2>
                <p class="text-sm text-ink-muted">{{ t('cycle.setup.q2Hint') }}</p>
            </div>
            <div v-for="[field, label, min, max] in [['period_length', 'setPeriod', 1, 12], ['cycle_length', 'setCycle', 18, 60]]" :key="field" class="glass flex items-center justify-between rounded-2xl px-4 py-3">
                <span class="text-sm font-semibold text-plum">{{ t(`cycle.${label}`) }}</span>
                <span class="flex items-center gap-3">
                    <button type="button" class="grid size-9 place-items-center rounded-full bg-fill text-lg font-semibold" :aria-label="t('cycle.less')" @click="stepper(field, -1, min, max)">−</button>
                    <span class="w-8 text-center font-display text-xl font-semibold tabular-nums">{{ form[field] }}</span>
                    <button type="button" class="grid size-9 place-items-center rounded-full bg-berry text-lg font-semibold text-white" :aria-label="t('cycle.more')" @click="stepper(field, 1, min, max)">+</button>
                </span>
            </div>
        </template>

        <template v-else>
            <div>
                <h2 class="font-display text-xl font-semibold text-plum">{{ t('cycle.setup.q3') }}</h2>
                <p class="text-sm text-ink-muted">{{ t('cycle.setup.q3Hint') }}</p>
            </div>
            <div class="grid gap-2">
                <button v-for="[key, icon] in [['track', Flower2], ['conceive', Baby]]" :key="key" type="button" class="glass flex items-center gap-3 rounded-2xl p-4 text-left transition" :class="form.goal === key ? '!border-berry wash-blush' : ''" @click="form.goal = key">
                    <span class="grid size-10 place-items-center rounded-xl bg-blush text-berry-ink"><component :is="icon" class="size-5" aria-hidden="true" /></span>
                    <span><span class="block font-semibold text-plum">{{ t(`cycle.goals.${key}`) }}</span><span class="block text-xs text-ink-muted">{{ t(`cycle.goals.${key}Hint`) }}</span></span>
                </button>
            </div>
        </template>

        <div class="flex justify-between gap-2">
            <Button v-if="step > 0" variant="secondary" @click="step--">{{ t('common.back') }}</Button>
            <span v-else />
            <Button v-if="step < 2" @click="step++">{{ t('cycle.setup.next') }}</Button>
            <Button v-else :loading="form.processing" @click="submit">{{ t('cycle.setup.start') }}</Button>
        </div>
    </Card>
</template>
