<script setup>
import { Head } from '@inertiajs/vue3';
import PageHeader from '@/Components/layout/PageHeader.vue';
import AppearanceSettings from '@/Components/settings/AppearanceSettings.vue';
import NavStyleSettings from '@/Components/settings/NavStyleSettings.vue';
</script>

<template>
    <Head :title="$t('pengaturan.appearance')" />
    <PageHeader :title="$t('pengaturan.appearance')" back="/pengaturan" />
    <AppearanceSettings />
    <NavStyleSettings />
</template>
