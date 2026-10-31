<script setup>
import { Head, Link, usePage } from '@inertiajs/vue3';
import { computed } from 'vue';
import { Palette, Bell, Heart, ShieldCheck, Info, ChevronRight, Smartphone, HeartPulse } from '@lucide/vue';
import PageHeader from '@/Components/layout/PageHeader.vue';
import Card from '@/Components/ui/Card.vue';
import { NATIVE } from '@/lib/native';

const page = usePage();
const rows = computed(() => [
    { href: '/pengaturan/tampilan', icon: Palette, title: 'appearance', body: 'appearanceBody' },
    { href: '/pengaturan/notifikasi', icon: Bell, title: 'notifications', body: 'notificationsBody' },
    ...(NATIVE ? [{ href: '/pengaturan/aplikasi', icon: Smartphone, title: 'app', body: 'appBody' }] : []),
    // A menu of its own, only for profiles that chose perempuan (the server refuses everyone else).
    ...(page.props.auth.user?.gender === 'perempuan' ? [{ href: '/kesehatan/siklus', icon: HeartPulse, title: 'cycle', body: 'cycleBody' }] : []),
    { href: '/pengaturan/pasangan', icon: Heart, title: 'couple', body: 'coupleBody' },
    { href: '/settings/privasi', icon: ShieldCheck, title: 'privacy', body: 'privacyBody' },
    { href: '/pengaturan/tentang', icon: Info, title: 'about', body: 'aboutBody' },
]);
</script>

<template>
    <Head :title="$t('pengaturan.title')" />
    <PageHeader :title="$t('pengaturan.title')" :lead="$t('pengaturan.lead')" back="/settings" />

    <Card :padded="false">
        <ul class="divide-y divide-line">
            <li v-for="row in rows" :key="row.href">
                <Link :href="row.href" class="flex min-h-16 items-center gap-3 px-4 py-3 transition active:bg-blush-soft">
                    <span class="grid size-10 shrink-0 place-items-center rounded-[0.875rem] bg-blush text-berry-ink"><component :is="row.icon" class="size-5" aria-hidden="true" /></span>
                    <span class="min-w-0 flex-1">
                        <span class="block text-[0.9375rem] font-semibold text-plum">{{ $t(`pengaturan.${row.title}`) }}</span>
                        <span class="block text-xs text-ink-muted">{{ $t(`pengaturan.${row.body}`) }}</span>
                    </span>
                    <ChevronRight class="size-4 shrink-0 text-ink-muted" aria-hidden="true" />
                </Link>
            </li>
        </ul>
    </Card>
</template>
