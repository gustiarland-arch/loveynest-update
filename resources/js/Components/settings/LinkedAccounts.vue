<script setup>
import { computed, onBeforeUnmount, ref } from 'vue';
import { router } from '@inertiajs/vue3';
import { Phone, Trash2, ShieldCheck } from '@lucide/vue';
import Card from '@/Components/ui/Card.vue';
import Button from '@/Components/ui/Button.vue';
import TextField from '@/Components/ui/TextField.vue';
import ConfirmDialog from '@/Components/ui/ConfirmDialog.vue';
import GoogleMark from '@/Components/brand/GoogleMark.vue';
import { http } from '@/lib/http';
import { t } from '@/i18n';

const props = defineProps({ identities: { type: Object, required: true } });

const google = computed(() => props.identities.items.find((i) => i.provider === 'google'));
const phone = computed(() => props.identities.items.find((i) => i.provider === 'phone'));

// Phone linking: number -> code -> done.
const number = ref('');
const code = ref('');
const step = ref('number');
const busy = ref(false);
const error = ref('');
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
    error.value = '';
    try {
        const result = await http.post('/settings/identitas/telepon/kirim', { phone: number.value });
        step.value = 'code';
        startWait(result?.wait ?? 60);
    } catch (e) {
        error.value = e?.data?.errors?.phone?.[0] ?? e?.data?.message ?? t('settings.linked.failed');
    } finally {
        busy.value = false;
    }
}

function verify() {
    busy.value = true;
    error.value = '';
    router.post('/settings/identitas/telepon', { phone: number.value, code: code.value }, {
        preserveScroll: true,
        onError: (errors) => (error.value = errors.code ?? errors.phone ?? t('settings.linked.failed')),
        onSuccess: () => {
            step.value = 'number';
            number.value = '';
            code.value = '';
        },
        onFinish: () => (busy.value = false),
    });
}

const removing = ref(null);
function remove() {
    const item = removing.value;
    removing.value = null;
    if (item) router.delete(`/settings/identitas/${item.id}`, { preserveScroll: true });
}
</script>

<template>
    <Card class="space-y-4">
        <div>
            <h3 class="font-display text-lg font-semibold text-plum">{{ t('settings.linked.title') }}</h3>
            <p class="text-sm text-ink-muted">{{ t('settings.linked.lead') }}</p>
        </div>

        <div class="flex items-center gap-3 glass rounded-[1rem] p-3">
            <span class="grid size-10 shrink-0 place-items-center rounded-full bg-fill"><GoogleMark class="size-5" /></span>
            <div class="min-w-0 flex-1">
                <p class="font-semibold text-plum">Google</p>
                <p class="truncate text-xs text-ink-muted">{{ google ? google.label : identities.google ? t('settings.linked.notLinked') : t('settings.linked.unavailable') }}</p>
            </div>
            <span v-if="google" class="inline-flex items-center gap-1 text-xs font-semibold text-sage-ink"><ShieldCheck class="size-4" aria-hidden="true" />{{ t('settings.linked.verified') }}</span>
            <Button v-if="google" variant="ghost" size="sm" class="!text-danger" :aria-label="t('common.delete')" @click="removing = google"><Trash2 class="size-4" aria-hidden="true" /></Button>
            <a v-else-if="identities.google" href="/auth/google/tautkan" class="glass inline-flex h-9 items-center rounded-full px-4 text-sm font-semibold text-berry-ink">{{ t('settings.linked.link') }}</a>
        </div>

        <div class="glass rounded-[1rem] p-3">
            <div class="flex items-center gap-3">
                <span class="grid size-10 shrink-0 place-items-center rounded-full bg-fill"><Phone class="size-5 text-plum" aria-hidden="true" /></span>
                <div class="min-w-0 flex-1">
                    <p class="font-semibold text-plum">{{ t('settings.linked.phone') }}</p>
                    <p class="truncate text-xs text-ink-muted">{{ phone ? phone.label : identities.phone ? t('settings.linked.notLinked') : t('settings.linked.unavailable') }}</p>
                </div>
                <span v-if="phone" class="inline-flex items-center gap-1 text-xs font-semibold text-sage-ink"><ShieldCheck class="size-4" aria-hidden="true" />{{ t('settings.linked.verified') }}</span>
                <Button v-if="phone" variant="ghost" size="sm" class="!text-danger" :aria-label="t('common.delete')" @click="removing = phone"><Trash2 class="size-4" aria-hidden="true" /></Button>
            </div>
            <form v-if="!phone && identities.phone" class="mt-3 space-y-3" @submit.prevent="step === 'number' ? sendCode() : verify()">
                <TextField v-model="number" :label="t('settings.linked.phoneLabel')" type="tel" inputmode="tel" autocomplete="tel" :hint="t('settings.linked.phoneHint')" />
                <TextField v-if="step === 'code'" v-model="code" :label="t('settings.linked.codeLabel')" inputmode="numeric" autocomplete="one-time-code" :maxlength="8" />
                <p v-if="error" class="text-sm text-danger" role="alert">{{ error }}</p>
                <div class="flex flex-wrap gap-2">
                    <Button type="submit" :loading="busy" :disabled="step === 'number' ? !number.trim() : code.trim().length < 4">{{ step === 'number' ? t('settings.linked.sendCode') : t('settings.linked.verify') }}</Button>
                    <Button v-if="step === 'code'" type="button" variant="ghost" :disabled="wait > 0 || busy" @click="sendCode">{{ wait > 0 ? t('settings.linked.resendIn', { s: wait }) : t('settings.linked.resend') }}</Button>
                </div>
            </form>
        </div>

        <p v-if="!identities.hasPassword" class="text-xs text-ink-muted">{{ t('settings.linked.noPassword') }}</p>

        <ConfirmDialog :open="!!removing" :title="t('settings.linked.removeTitle')" :description="t('settings.linked.removeBody')" :confirm-label="t('common.delete')" danger @update:open="(v) => !v && (removing = null)" @confirm="remove" />
    </Card>
</template>
