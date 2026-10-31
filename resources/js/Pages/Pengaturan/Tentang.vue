<script setup>
import { Head, usePage } from '@inertiajs/vue3';
import { Info, ShieldCheck } from '@lucide/vue';
import PageHeader from '@/Components/layout/PageHeader.vue';
import Card from '@/Components/ui/Card.vue';
import { e2e } from '@/lib/e2e';
import { t } from '@/i18n';

const page = usePage();
</script>

<template>
    <Head :title="$t('pengaturan.about')" />
    <PageHeader :title="$t('pengaturan.about')" back="/pengaturan" />
    <Card>
        <h2 class="flex items-center gap-2 text-lg font-semibold"><Info class="size-4 text-ink-muted" aria-hidden="true" />{{ $t('settings.about') }}</h2>
        <p class="mt-2 text-sm text-ink-muted">LoveyNest · {{ $t('settings.version', { version: page.props.app.version }) }}</p>
        <p class="text-sm text-ink-muted">{{ $t('app.tagline') }}</p>
    </Card>

    <Card class="mt-4">
        <h2 class="flex items-center gap-2 text-lg font-semibold text-plum"><ShieldCheck class="size-4 text-berry-ink" aria-hidden="true" />{{ t('privacy.promises') }}</h2>
        <ul class="mt-2 list-disc space-y-1.5 pl-5 text-sm text-ink-muted">
            <template v-if="e2e.enabled"><li v-for="key in ['e1', 'e2', 'e3', 'e4', 'e5', 'e6']" :key="key">{{ t(`privacy.${key}`) }}</li></template>
            <template v-else><li>{{ t('privacy.p1') }}</li><li>{{ t('privacy.p2') }}</li><li>{{ t('privacy.p4') }}</li></template>
            <li>{{ t('privacy.p3') }}</li>
        </ul>
    </Card>
</template>
