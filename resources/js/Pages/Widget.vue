<script setup>
import { ref, watch } from 'vue';
import { Deferred, Head } from '@inertiajs/vue3';
import PageHeader from '@/Components/layout/PageHeader.vue';
import WidgetGrid from '@/Components/memories/WidgetGrid.vue';
import FunWidgets from '@/Components/memories/FunWidgets.vue';
import { t } from '@/i18n';

defineProps({
    partner: { type: Object, default: null },
    moods: { type: Object, default: undefined },
    event: { type: Object, default: null },
    unreadChat: { type: Number, default: 0 },
    photo: { type: Object, default: null },
    letter: { type: Object, default: null },
    extras: { type: Object, default: undefined }, // deferred ('extras')
});

// The look of the widgets, remembered on this device.
const STYLES = [
    { value: 'kaca', label: 'Kaca', icon: '🫧' },
    { value: 'lucu', label: 'Lucu', icon: '🧸' },
    { value: 'kertas', label: 'Kertas', icon: '📒' },
    { value: 'malam', label: 'Malam', icon: '🌙' },
];
const read = () => {
    try {
        return localStorage.getItem('widget.style') || 'kaca';
    } catch {
        return 'kaca';
    }
};
const style = ref(read());
watch(style, (v) => {
    try {
        localStorage.setItem('widget.style', v);
    } catch {
        /* private mode */
    }
});
</script>

<template>
    <Head :title="t('widget.title')" />
    <PageHeader :title="t('widget.title')" :lead="t('widget.lead')" back="/dashboard" />

    <div class="mb-4 flex gap-1.5 overflow-x-auto pb-1" role="radiogroup" aria-label="Gaya widget">
        <button v-for="s in STYLES" :key="s.value" type="button" role="radio" :aria-checked="style === s.value" class="flex h-10 shrink-0 items-center gap-1.5 rounded-full px-4 text-sm font-semibold ring-1 transition active:scale-95" :class="style === s.value ? 'bg-berry text-white ring-transparent' : 'glass text-plum ring-transparent'" @click="style = s.value">
            <span aria-hidden="true">{{ s.icon }}</span>{{ s.label }}
        </button>
    </div>

    <div :class="`wstyle-${style}`">
        <Deferred data="extras">
            <template #fallback><div class="mb-3 h-40 animate-pulse glass rounded-[1.5rem]" aria-busy="true" /></template>
            <FunWidgets v-if="extras" :extras="extras" :partner="partner" :moods="moods" class="mb-3" />
        </Deferred>
    </div>

    <Deferred :data="['moods', 'event', 'unreadChat', 'photo', 'letter']">
        <template #fallback>
        <div class="grid grid-cols-2 gap-3" aria-busy="true">
            <div class="col-span-2 h-[7.5rem] animate-pulse glass rounded-[1.5rem]"></div>
            <div class="h-[10.5rem] animate-pulse glass rounded-[1.5rem]"></div>
            <div class="h-[10.5rem] animate-pulse glass rounded-[1.5rem]"></div>
        </div>
        </template>
        <WidgetGrid :moods="moods" :partner="partner" :photo="photo" :letter="letter" :event="event" :unread-chat="unreadChat" show-event />
    </Deferred>
    <p class="mt-6 text-center text-xs text-ink-muted">{{ t('widget.note') }}</p>
</template>
