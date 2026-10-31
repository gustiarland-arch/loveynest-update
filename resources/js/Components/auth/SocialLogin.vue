<script setup>
import { computed } from 'vue';
import { usePage } from '@inertiajs/vue3';
import { Phone } from '@lucide/vue';
import GoogleMark from '@/Components/brand/GoogleMark.vue';
import { t } from '@/i18n';

// "Or sign in with" row. A button exists only when the server can really do it (Google keys set, codes deliverable).
const props = defineProps({ active: { type: String, default: '' }, googleOnly: { type: Boolean, default: false } });
const emit = defineEmits(['phone']);
const page = usePage();
const raw = computed(() => page.props.app?.authMethods ?? { google: false, phone: false });
const methods = computed(() => ({ google: raw.value.google, phone: raw.value.phone && !props.googleOnly }));
</script>

<template>
    <div v-if="methods.google || methods.phone">
        <div class="my-5 flex items-center gap-3 text-xs text-ink-muted" role="separator">
            <span class="h-px flex-1 bg-[var(--glass-edge)]" />{{ googleOnly ? t('auth.orRegisterWith') : t('auth.orWith') }}<span class="h-px flex-1 bg-[var(--glass-edge)]" />
        </div>
        <div class="grid gap-3" :class="methods.google && methods.phone ? 'grid-cols-2' : 'grid-cols-1'">
            <a v-if="methods.google" href="/auth/google" class="glass flex h-12 items-center justify-center gap-2 rounded-2xl text-sm font-semibold text-plum transition active:scale-[0.98]">
                <GoogleMark class="size-5" />Google
            </a>
            <button v-if="methods.phone" type="button" class="glass flex h-12 items-center justify-center gap-2 rounded-2xl text-sm font-semibold text-plum transition active:scale-[0.98]" :class="{ 'ring-2 ring-berry': active === 'phone' }" @click="emit('phone')">
                <Phone class="size-5" aria-hidden="true" />{{ t('auth.phone') }}
            </button>
        </div>
    </div>
</template>
