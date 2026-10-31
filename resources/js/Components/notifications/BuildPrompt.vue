<script setup>
import { onBeforeUnmount, onMounted, ref } from 'vue';
import { router } from '@inertiajs/vue3';
import { applyUpdate, buildState } from '@/lib/build';
import { RefreshCw, X } from '@lucide/vue';
import { t } from '@/i18n';

// Tells people when a newer LoveyNest has been put on the server, in the browser app and in the Android app alike.
// The server's build id is compared with the one this page was loaded with; the page itself is never reloaded without a tap.
const stale = ref(false);
let current = null;
let timer = 0;
const KEY = 'build.dismissed';

function dismissedFor(id) {
    try {
        return sessionStorage.getItem(KEY) === id;
    } catch {
        return false;
    }
}

let latest = null;
async function check() {
    if (!current || buildState.stale || document.visibilityState !== 'visible') return;
    try {
        const response = await fetch('/aplikasi/build', { headers: { Accept: 'application/json' }, cache: 'no-store' });
        if (!response.ok) return;
        latest = (await response.json()).build;
        if (latest && latest !== 'dev' && latest !== current) {
            // From now on the next menu tap loads the new version by itself (see onBefore); the banner only offers to do it at once.
            buildState.stale = true;
            if (!dismissedFor(latest)) stale.value = true;
        }
    } catch {
        // Offline: nothing to tell.
    }
}

function later() {
    try {
        sessionStorage.setItem(KEY, latest ?? '');
    } catch {
        /* ignore */
    }
    stale.value = false;
}

const reload = () => applyUpdate();

// An update that has been announced is applied on the next tap on a menu, so nobody keeps using an old screen by accident.
let stopBefore = () => {};
function onBefore(event) {
    const visit = event.detail.visit;
    if (!buildState.stale || visit.method !== 'get' || visit.prefetch) return;
    event.preventDefault();
    applyUpdate(visit.url.href);
}

onMounted(() => {
    current = document.querySelector('meta[name="usandus-build"]')?.content || null;
    check();
    timer = window.setInterval(check, 2 * 60 * 1000);
    stopBefore = router.on('before', onBefore);
    document.addEventListener('visibilitychange', check);
});
onBeforeUnmount(() => {
    stopBefore();
    clearInterval(timer);
    document.removeEventListener('visibilitychange', check);
});
</script>

<template>
    <div v-if="stale" class="fixed inset-x-0 top-0 z-[60] flex justify-center px-4 pt-[max(0.5rem,env(safe-area-inset-top))]" role="status" data-testid="build-prompt">
        <div class="flex w-full max-w-md items-center gap-3 glass-bar rounded-[1.25rem] p-3">
            <span class="grid size-9 shrink-0 place-items-center rounded-[0.75rem] bg-blush text-berry-ink"><RefreshCw class="size-[1.125rem]" aria-hidden="true" /></span>
            <div class="min-w-0 flex-1">
                <p class="text-sm font-semibold text-plum">{{ t('build.title') }}</p>
                <p class="text-xs text-ink-muted">{{ t('build.body') }}</p>
            </div>
            <button type="button" class="h-9 shrink-0 rounded-full bg-berry px-3 text-sm font-semibold text-white active:scale-95" data-testid="build-reload" @click="reload">{{ t('build.button') }}</button>
            <button type="button" class="grid size-9 shrink-0 place-items-center rounded-full text-ink-muted hover:bg-fill" :aria-label="t('update.later')" @click="later"><X class="size-4" aria-hidden="true" /></button>
        </div>
    </div>
</template>
