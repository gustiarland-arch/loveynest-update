<script setup>
// "Analisis AI" on top of the cycle analysis. Nothing is sent to the AI until the button is tapped, and only a summary
// of numbers goes (see app/Services/Ai/CycleAnalyst.php for the rules the answer is held to).
import { onMounted, ref } from 'vue';
import { Sparkles, Stethoscope, Lightbulb, Activity, RefreshCw, ShieldCheck } from '@lucide/vue';
import { http, HttpError } from '@/lib/http';
import { t } from '@/i18n';

const result = ref(null);
const available = ref(true);
const busy = ref(false);
const error = ref('');
const showSent = ref(false);

onMounted(async () => {
    try {
        const data = await http.get('/kesehatan/siklus/analisis/ai');
        result.value = data.result;
        available.value = data.available;
    } catch {
        // The button still works.
    }
});

async function run() {
    busy.value = true;
    error.value = '';
    try {
        result.value = (await http.post('/kesehatan/siklus/analisis/ai', {})).result;
    } catch (e) {
        error.value = e instanceof HttpError && e.status === 429 ? t('cycle.ai.wait') : t('cycle.ai.failed');
    } finally {
        busy.value = false;
    }
}
defineExpose({ run });
</script>

<template>
    <section class="ai-card glass relative overflow-hidden rounded-[1.5rem] p-4" :aria-busy="busy">
        <span class="ai-orb" aria-hidden="true" />
        <div class="relative flex items-center gap-2.5">
            <span class="grid size-10 shrink-0 place-items-center rounded-[0.875rem] bg-hero text-white shadow-soft"><Sparkles class="size-5" aria-hidden="true" /></span>
            <div class="min-w-0 flex-1">
                <h3 class="font-semibold text-plum">{{ t('cycle.ai.title') }}</h3>
                <p class="text-xs text-ink-muted">{{ t('cycle.ai.lead') }}</p>
            </div>
        </div>

        <div v-if="busy" class="relative mt-3 space-y-2" role="status">
            <div class="ai-line h-3 w-11/12" /><div class="ai-line h-3 w-9/12" /><div class="ai-line h-3 w-10/12" />
            <p class="pt-1 text-xs text-ink-muted">{{ t('cycle.ai.thinking') }}</p>
        </div>

        <template v-else-if="result">
            <p class="ai-in relative mt-3 text-[0.95rem] leading-relaxed text-plum">{{ result.summary }}</p>
            <div v-if="result.patterns.length" class="ai-in relative mt-3" style="animation-delay: 0.08s">
                <p class="mb-1 flex items-center gap-1.5 text-xs font-semibold uppercase tracking-[0.06em] text-berry-ink"><Activity class="size-3.5" aria-hidden="true" />{{ t('cycle.ai.patterns') }}</p>
                <ul class="space-y-1 text-sm text-plum"><li v-for="(p, i) in result.patterns" :key="i" class="flex gap-2"><span class="mt-2 size-1.5 shrink-0 rounded-full bg-berry" />{{ p }}</li></ul>
            </div>
            <div v-if="result.tips.length" class="ai-in relative mt-3" style="animation-delay: 0.16s">
                <p class="mb-1 flex items-center gap-1.5 text-xs font-semibold uppercase tracking-[0.06em] text-sage-ink"><Lightbulb class="size-3.5" aria-hidden="true" />{{ t('cycle.ai.tips') }}</p>
                <ul class="space-y-1 text-sm text-plum"><li v-for="(p, i) in result.tips" :key="i" class="flex gap-2"><span class="mt-2 size-1.5 shrink-0 rounded-full bg-sage-ink" />{{ p }}</li></ul>
            </div>
            <div v-if="result.watch.length" class="ai-in relative mt-3 rounded-[1rem] bg-peach/60 p-3" style="animation-delay: 0.24s">
                <p class="mb-1 flex items-center gap-1.5 text-xs font-semibold uppercase tracking-[0.06em] text-peach-ink"><Stethoscope class="size-3.5" aria-hidden="true" />{{ t('cycle.ai.watch') }}</p>
                <ul class="space-y-1 text-sm text-plum"><li v-for="(p, i) in result.watch" :key="i">{{ p }}</li></ul>
            </div>
            <p class="relative mt-3 flex items-start gap-1.5 text-[0.72rem] leading-snug text-ink-muted">
                <ShieldCheck class="mt-px size-3.5 shrink-0" aria-hidden="true" />
                <span>{{ result.source === 'ai' ? t('cycle.ai.byAi') : t(`cycle.ai.byRules.${result.reason ?? 'data'}`) }} {{ t('cycle.ai.disclaimer') }}</span>
            </p>
        </template>
        <p v-else class="relative mt-3 text-sm text-ink-muted">{{ available ? t('cycle.ai.empty') : t('cycle.ai.off') }}</p>

        <p v-if="error" class="relative mt-2 text-sm font-semibold text-danger" role="alert">{{ error }}</p>

        <div class="relative mt-3 flex flex-wrap items-center gap-2">
            <button type="button" class="inline-flex h-10 items-center gap-1.5 rounded-full bg-berry px-4 text-sm font-semibold text-white shadow-soft transition active:scale-95 disabled:opacity-60" :disabled="busy" @click="run">
                <component :is="result ? RefreshCw : Sparkles" class="size-4" aria-hidden="true" />{{ result ? t('cycle.ai.again') : t('cycle.ai.run') }}
            </button>
            <button type="button" class="h-10 rounded-full px-3 text-xs font-semibold text-ink-muted underline-offset-4 hover:underline" :aria-expanded="showSent" @click="showSent = !showSent">{{ t('cycle.ai.whatSent') }}</button>
        </div>
        <p v-if="showSent" class="relative mt-2 rounded-[0.875rem] bg-fill p-3 text-xs leading-relaxed text-ink-muted">{{ t('cycle.ai.sentBody') }}</p>
    </section>
</template>

<style scoped>
.ai-orb {
    position: absolute;
    right: -3rem;
    top: -3rem;
    width: 9rem;
    aspect-ratio: 1;
    border-radius: 50%;
    background: radial-gradient(circle, color-mix(in srgb, var(--color-berry) 40%, transparent), transparent 70%);
    animation: ai-orb 6s ease-in-out infinite alternate;
    pointer-events: none;
}
.ai-line {
    border-radius: 9999px;
    background: linear-gradient(90deg, var(--color-fill), color-mix(in srgb, var(--color-berry) 25%, var(--color-fill)), var(--color-fill));
    background-size: 200% 100%;
    animation: ai-shimmer 1.2s linear infinite;
}
.ai-in {
    animation: ai-in 0.5s cubic-bezier(0.22, 1, 0.36, 1) backwards;
}
@keyframes ai-orb {
    to { transform: translate(-1.5rem, 1rem) scale(1.2); }
}
@keyframes ai-shimmer {
    to { background-position: -200% 0; }
}
@keyframes ai-in {
    from { opacity: 0; transform: translateY(8px); }
}
@media (prefers-reduced-motion: reduce) {
    .ai-orb, .ai-line, .ai-in { animation: none; }
}
</style>
