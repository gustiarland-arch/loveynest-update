<script setup>
import { computed, onBeforeUnmount, onErrorCaptured, onMounted, ref } from 'vue';
import { Link } from '@inertiajs/vue3';
import { Flame, Gift, Heart, Trophy } from '@lucide/vue';
import TimeCard from '@/Components/time/TimeCard.vue';
import Avatar from '@/Components/ui/Avatar.vue';
import SkyBackdrop from '@/Components/memories/SkyBackdrop.vue';
import { formatDate } from '@/lib/dates';
import { t } from '@/i18n';

const props = defineProps({
    me: { type: Object, required: true },
    partner: { type: Object, required: true },
    facts: { type: Object, required: true },
    online: { type: Boolean, default: false },
});

// If the live sky ever fails, the card falls back to the plain gradient it had before.
const skyOk = ref(true);
onErrorCaptured(() => {
    skyOk.value = false;
    return false;
});

// Next round-number milestone (100 days, 1 year, ...) and how far along the way the couple is.
const MILESTONES = [100, 200, 300, 365, 500, 730, 1000, 1095, 1461, 1826, 2000, 2557, 3000, 3652];
const milestone = computed(() => {
    const days = props.facts.days;
    if (!days) return null;
    const next = MILESTONES.find((m) => m > days) ?? Math.ceil((days + 1) / 1000) * 1000;
    const prev = [...MILESTONES].reverse().find((m) => m <= days) ?? 0;
    return { target: next, left: next - days, progress: (days - prev) / (next - prev) };
});

// The day count rolls up once per visit so it does not replay on every return to the home screen.
const shownDays = ref(props.facts.days ?? 0);
let raf = 0;
onBeforeUnmount(() => cancelAnimationFrame(raf));
onMounted(() => {
    try {
        const target = props.facts.days;
        if (!target || window.matchMedia?.('(prefers-reduced-motion: reduce)').matches) return;
        if (sessionStorage.getItem('hero-counted') === String(target)) return;
        sessionStorage.setItem('hero-counted', String(target));
        const start = performance.now();
        shownDays.value = 0;
        const step = (now) => {
            const p = Math.min(1, (now - start) / 1400);
            shownDays.value = Math.round(target * (1 - Math.pow(1 - p, 4)));
            if (p < 1) raf = requestAnimationFrame(step);
        };
        raf = requestAnimationFrame(step);
    } catch {
        shownDays.value = props.facts.days ?? 0;
    }
});
</script>

<template>
    <Link href="/momen" class="relative mb-5 block overflow-hidden rounded-[1.75rem] bg-hero p-5 text-white shadow-[0_18px_36px_-18px_rgb(150_40_90/0.8)] transition hover:-translate-y-0.5" :aria-label="t('dashboard.couple')">
        <SkyBackdrop v-if="skyOk" />
        <div class="relative flex items-center gap-3" :class="{ 'sky-text': skyOk }">
            <div class="relative flex shrink-0">
                <Avatar :name="me.name" :src="me.avatarUrl" :frame="me.avatarFrame" size="lg" class="!size-12 !text-base !ring-white/85" :class="{ 'sky-float': skyOk }" />
                <span class="relative -ml-3.5">
                    <Avatar :name="partner.name" :src="partner.avatarUrl" :frame="partner.avatarFrame" tone="lavender" size="lg" class="!size-12 !text-base !ring-white/85" :class="{ 'sky-float sky-float-b': skyOk }" />
                    <span v-if="online" class="absolute bottom-0 right-0 size-3.5 rounded-full bg-online ring-2 ring-white" :title="t('chat.online')" />
                </span>
                <span v-if="skyOk" class="sky-beat absolute -bottom-1 left-[2.15rem] grid size-5 place-items-center rounded-full bg-white text-berry shadow-md" aria-hidden="true">
                    <Heart class="size-3" fill="currentColor" stroke-width="0" />
                </span>
            </div>
            <div class="min-w-0">
                <p v-if="facts.days" class="font-display text-[2.125rem] font-semibold leading-none">
                    {{ shownDays }} <span class="font-sans text-sm font-semibold opacity-90">{{ t('dashboard.daysTogether') }}</span>
                </p>
                <p v-else class="font-display text-xl font-semibold leading-tight">{{ t('berdua.setDate') }}</p>
                <p v-if="facts.anniversary" class="mt-1 text-[0.8125rem] opacity-90">{{ t('dashboard.since', { date: formatDate(facts.anniversary + 'T12:00:00Z', { dateStyle: 'medium' }, 'UTC') }) }}</p>
            </div>
        </div>
        <div v-if="facts.days" class="relative mt-3 flex flex-wrap gap-2">
            <TimeCard v-if="facts.streak" variant="chip" tone="sun" :icon="Flame" :label="t('dashboard.timeStreak')" :value="facts.streak || 0" :unit="t('dashboard.timeDays')" />
            <TimeCard v-if="milestone" variant="chip" tone="lavender" :icon="Trophy" :label="t('dashboard.timeMilestone', { count: milestone.target })" :value="milestone.left" :unit="t('dashboard.timeLeftTo', { count: milestone.target })" />
            <TimeCard v-if="facts.next" variant="chip" tone="rose" :icon="Gift" :label="facts.next.title" :value="facts.next.in === 0 ? t('dashboard.timeToday') : facts.next.in" :unit="facts.next.in === 0 ? '' : t('dashboard.timeDays')" />
        </div>
    </Link>
</template>
