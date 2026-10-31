<script setup>
import { ref } from 'vue';
import { router, useForm } from '@inertiajs/vue3';
import { LockKeyhole } from '@lucide/vue';
import Card from '@/Components/ui/Card.vue';
import Button from '@/Components/ui/Button.vue';
import TextField from '@/Components/ui/TextField.vue';
import { t } from '@/i18n';

// The tracker is locked by a PIN: nothing from it has been sent to this page yet.
const form = useForm({ pin: '' });
const forgot = ref(false);
const reset = useForm({ password: '' });
const unlock = () => form.post('/kesehatan/siklus/pin/buka', { onFinish: () => form.reset('pin') });
const resetPin = () => reset.post('/kesehatan/siklus/pin/lupa', { onFinish: () => reset.reset('password') });
</script>

<template>
    <Card class="mx-auto mt-6 max-w-sm space-y-4 text-center">
        <span class="mx-auto grid size-14 place-items-center rounded-full bg-blush text-berry-ink"><LockKeyhole class="size-7" aria-hidden="true" /></span>
        <div>
            <h2 class="font-display text-xl font-semibold text-plum">{{ t('cycle.locked') }}</h2>
            <p class="text-sm text-ink-muted">{{ t('cycle.lockedLead') }}</p>
        </div>
        <form v-if="!forgot" class="space-y-3" @submit.prevent="unlock">
            <TextField v-model="form.pin" :label="t('cycle.pin')" type="password" inputmode="numeric" autocomplete="off" :maxlength="6" :error="form.errors.pin" />
            <Button type="submit" block :loading="form.processing" :disabled="form.pin.length < 4">{{ t('cycle.unlock') }}</Button>
            <button type="button" class="text-sm font-semibold text-berry-ink" @click="forgot = true">{{ t('cycle.forgotPin') }}</button>
        </form>
        <form v-else class="space-y-3" @submit.prevent="resetPin">
            <p class="text-sm text-ink-muted">{{ t('cycle.forgotPinBody') }}</p>
            <TextField v-model="reset.password" :label="t('auth.password')" type="password" autocomplete="current-password" :error="reset.errors.password" />
            <Button type="submit" block :loading="reset.processing" :disabled="!reset.password">{{ t('cycle.removePin') }}</Button>
            <button type="button" class="text-sm font-semibold text-berry-ink" @click="forgot = false">{{ t('common.cancel') }}</button>
        </form>
    </Card>
</template>
