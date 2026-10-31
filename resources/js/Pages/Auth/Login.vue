<script setup>
import { onBeforeUnmount, ref } from 'vue';
import { Head, Link, useForm } from '@inertiajs/vue3';
import TextField from '@/Components/ui/TextField.vue';
import Button from '@/Components/ui/Button.vue';
import SocialLogin from '@/Components/auth/SocialLogin.vue';
import { AtSign, LockKeyhole, ChevronLeft, Phone } from '@lucide/vue';
import { t } from '@/i18n';
import { http } from '@/lib/http';
import { forgetPassword, rememberPassword } from '@/lib/e2e';

const form = useForm({ email: '', password: '', remember: true });

function submit() {
    rememberPassword(form.password);
    form.post('/login', { onError: forgetPassword, onFinish: () => form.reset('password') });
}

// Phone sign-in: number -> code. Same page, same card.
const mode = ref('email');
const phoneForm = useForm({ phone: '', code: '' });
const step = ref('number');
const busy = ref(false);
const sendError = ref('');
const wait = ref(0);
let timer = null;

function startWait(seconds) {
    wait.value = seconds;
    clearInterval(timer);
    timer = setInterval(() => {
        wait.value = Math.max(0, wait.value - 1);
        if (!wait.value) clearInterval(timer);
    }, 1000);
}
onBeforeUnmount(() => clearInterval(timer));

async function sendCode() {
    busy.value = true;
    sendError.value = '';
    try {
        const result = await http.post('/login/telepon/kirim', { phone: phoneForm.phone });
        step.value = 'code';
        startWait(result?.wait ?? 60);
    } catch (e) {
        sendError.value = e?.data?.errors?.phone?.[0] ?? e?.data?.message ?? t('settings.linked.failed');
    } finally {
        busy.value = false;
    }
}

function verify() {
    phoneForm.post('/login/telepon', { onFinish: () => phoneForm.reset('code') });
}
</script>

<template>
    <Head :title="t('auth.login')" />
    <div class="flex flex-1 flex-col py-4">
        <Link href="/" class="enter grid size-11 place-items-center rounded-full glass text-plum" style="--d: 0ms" :aria-label="t('common.back')"><ChevronLeft class="size-6" aria-hidden="true" /></Link>

        <h1 class="enter mt-8 text-balance text-left font-display text-[2rem] font-semibold leading-[1.12] text-plum" style="--d: 80ms">{{ $t('auth.loginTitle') }}! <span class="text-berry-ink">{{ $t('auth.loginTitle2') }}</span></h1>
        <p class="enter mt-2 text-sm text-ink-muted" style="--d: 140ms">{{ mode === 'phone' ? $t('auth.phoneLead') : $t('auth.loginLead') }}</p>

        <form v-if="mode === 'email'" class="enter mt-7 space-y-4" style="--d: 220ms" novalidate @submit.prevent="submit">
            <TextField v-model="form.email" :label="$t('auth.email')" type="email" :icon="AtSign" autocomplete="email" inputmode="email" :error="form.errors.email" required />
            <TextField v-model="form.password" :label="$t('auth.password')" type="password" :icon="LockKeyhole" autocomplete="current-password" :error="form.errors.password" required />
            <label class="flex min-h-11 items-center gap-3 text-sm text-plum">
                <input v-model="form.remember" type="checkbox" class="size-5 rounded border-line-strong accent-berry" />
                {{ $t('auth.remember') }}
            </label>
            <button type="submit" class="flex h-[3.25rem] w-full items-center justify-center rounded-full bg-plum text-base font-semibold text-ivory shadow-soft transition active:scale-[0.98] disabled:opacity-60" :disabled="form.processing">{{ $t('auth.login') }}</button>
            <p class="text-center text-xs text-ink-muted">{{ $t('auth.forgot') }}</p>
        </form>

        <form v-else class="enter mt-7 space-y-4" novalidate @submit.prevent="step === 'number' ? sendCode() : verify()">
            <TextField v-model="phoneForm.phone" :label="$t('auth.phoneLabel')" type="tel" :icon="Phone" autocomplete="tel" inputmode="tel" :error="sendError || undefined" />
            <TextField v-if="step === 'code'" v-model="phoneForm.code" :label="$t('auth.codeLabel')" autocomplete="one-time-code" inputmode="numeric" :maxlength="8" :error="phoneForm.errors.code" :hint="$t('auth.codeSent')" />
            <Button type="submit" size="lg" block :loading="busy || phoneForm.processing" :disabled="step === 'number' ? !phoneForm.phone.trim() : phoneForm.code.trim().length < 4">{{ step === 'number' ? $t('auth.sendCode') : $t('auth.verifyCode') }}</Button>
            <Button v-if="step === 'code'" type="button" variant="ghost" block :disabled="wait > 0 || busy" @click="sendCode">{{ wait > 0 ? $t('auth.resendIn', { s: wait }) : $t('auth.resend') }}</Button>
            <Button type="button" variant="ghost" block @click="mode = 'email'; step = 'number'">{{ $t('auth.useEmail') }}</Button>
        </form>

        <SocialLogin :active="mode" @phone="mode = 'phone'" />

        <p class="enter mt-auto pt-8 text-center text-sm text-ink-muted" style="--d: 360ms">
            {{ $t('auth.noAccount') }}
            <Link href="/register" class="font-semibold text-berry-ink underline-offset-4 hover:underline">{{ $t('auth.registerNow') }}</Link>
        </p>
    </div>
</template>
