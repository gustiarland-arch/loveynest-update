<script setup>
import { onMounted, ref } from 'vue';
import { Head, Link } from '@inertiajs/vue3';
import { HeartHandshake, CalendarHeart, MessageCircleHeart } from '@lucide/vue';
import HeroScene from '@/Components/brand/HeroScene.vue';
import Emblem from '@/Components/brand/Emblem.vue';
import { t } from '@/i18n';

// First screen: a short splash with the mark, then the hero. The splash shows once per visit and is skipped for reduced motion.
const splash = ref(false);
onMounted(() => {
    try {
        if (sessionStorage.getItem('ln-splash') || matchMedia('(prefers-reduced-motion: reduce)').matches) return;
        sessionStorage.setItem('ln-splash', '1');
    } catch {
        return;
    }
    splash.value = true;
    setTimeout(() => (splash.value = false), 1150);
});

const features = [
    { icon: HeartHandshake, title: t('welcome.featureSpacesTitle'), body: t('welcome.featureSpacesBody'), tone: 'bg-blush' },
    { icon: CalendarHeart, title: t('welcome.featurePlanTitle'), body: t('welcome.featurePlanBody'), tone: 'bg-lavender' },
    { icon: MessageCircleHeart, title: t('welcome.featureChatTitle'), body: t('welcome.featureChatBody'), tone: 'bg-sage' },
];
</script>

<template>
    <Head :title="null" />

    <Transition name="splash">
        <div v-if="splash" class="fixed inset-0 z-[90] grid place-items-center bg-[var(--bg-top)]" aria-hidden="true">
            <div class="splash-mark"><Emblem :size="132" /></div>
        </div>
    </Transition>

    <section class="enter flex flex-1 flex-col pt-3" style="--d: 0ms">
        <HeroScene />

        <div class="px-1 pb-6 pt-7 text-center">
            <p class="enter text-xs font-semibold uppercase tracking-[0.2em] text-rose-ink" style="--d: 120ms">{{ $t('app.tagline') }}</p>
            <h1 class="enter mt-2 text-balance font-display text-[2.25rem] font-semibold leading-[1.1] text-plum" style="--d: 180ms">{{ $t('welcome.headline') }}</h1>
            <p class="enter mx-auto mt-3 max-w-xs text-[0.9375rem] text-ink-muted" style="--d: 240ms">{{ $t('welcome.lead') }}</p>
        </div>

        <div class="enter space-y-3" style="--d: 320ms">
            <Link href="/login" class="flex h-[3.25rem] w-full items-center justify-center rounded-full bg-plum text-base font-semibold text-ivory shadow-soft transition active:scale-[0.98]">{{ $t('nav.login') }}</Link>
            <Link href="/register" class="flex h-[3.25rem] w-full items-center justify-center rounded-full border-2 border-plum/70 text-base font-semibold text-plum transition active:scale-[0.98]">{{ $t('welcome.start') }}</Link>
        </div>
    </section>

    <section class="space-y-3 pb-8 pt-8">
        <article v-for="feature in features" :key="feature.title" class="glass flex gap-4 rounded-[1.25rem] p-4">
            <span class="grid size-11 shrink-0 place-items-center rounded-md" :class="feature.tone">
                <component :is="feature.icon" class="size-5 text-plum" aria-hidden="true" />
            </span>
            <div>
                <h2 class="font-sans text-sm font-bold tracking-normal text-plum">{{ feature.title }}</h2>
                <p class="mt-0.5 text-sm text-ink-muted">{{ feature.body }}</p>
            </div>
        </article>
    </section>
</template>

<style>
.splash-leave-active { transition: opacity 0.45s ease; }
.splash-leave-to { opacity: 0; }
.splash-mark { animation: splash-in 0.9s cubic-bezier(0.34, 1.4, 0.64, 1) both; }
@keyframes splash-in {
    from { opacity: 0; transform: scale(0.6); }
    to { opacity: 1; transform: scale(1); }
}
</style>
