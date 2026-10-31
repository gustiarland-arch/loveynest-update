<script setup>
import { Fragment, KeepAlive, cloneVNode, computed, h, nextTick, onBeforeUnmount, onMounted, ref, watch, watchEffect } from 'vue';
import { Link, router, usePage } from '@inertiajs/vue3';
import { WifiOff, House, ListTodo, CalendarHeart, MessageCircleHeart, CircleUser, Images, BookHeart, Sun, Sparkles, Heart, Camera } from '@lucide/vue';
import BottomNav from '@/Components/layout/BottomNav.vue';
import { pageNav } from '@/lib/pageTransition';
import { cacheNavStyle, resolveNavStyle } from '@/lib/navStyle';
import CloudBand from '@/Components/brand/CloudBand.vue';
import ToastRegion from '@/Components/ui/ToastRegion.vue';
import { t } from '@/i18n';
import { applyBackground } from '@/lib/theme';
import { permission, pushSupport, refreshNative, status as pushStatus, refreshAfterChange } from '@/lib/push';
import { NATIVE } from '@/lib/native';
import { useNotificationWatch } from '@/composables/useNotificationWatch';
import { arena, useArenaWatch } from '@/composables/useArenaWatch';
import InviteSheet from '@/Components/arena/InviteSheet.vue';
import NudgeStage from '@/Components/nudge/NudgeStage.vue';
import CallOverlay from '@/Components/chat/CallOverlay.vue';
import PushPrompt from '@/Components/notifications/PushPrompt.vue';
import UpdatePrompt from '@/Components/notifications/UpdatePrompt.vue';
import BuildPrompt from '@/Components/notifications/BuildPrompt.vue';
import E2eBanner from '@/Components/settings/E2eBanner.vue';
import ShareSheet from '@/Components/native/ShareSheet.vue';
import OfflineBanner from '@/Components/native/OfflineBanner.vue';

const page = usePage();

// The page component is re-keyed with its path (what the old keyed wrapper did), and only the pages named here are cached.
const KEPT_PAGES = ['KeepAlbums', 'KeepPhotobooth'];
const PageKey = (props, { slots }) => {
    let [vnode] = slots.default?.() ?? [];
    // <slot /> arrives wrapped in a Fragment; KeepAlive needs the page component itself.
    while (vnode && vnode.type === Fragment) vnode = Array.isArray(vnode.children) ? vnode.children[0] : null;
    // KeepAlive must be the direct parent of the page component, so it is created here around the keyed page.
    return h(KeepAlive, { include: KEPT_PAGES, max: 2 }, { default: () => (vnode ? cloneVNode(vnode, { key: props.k }) : null) });
};
PageKey.props = ['k'];
const path = computed(() => page.url.split('?')[0]);
// The page eases in on every menu change (CSS only: opacity and a short rise).
const rising = ref(false);
watch(path, () => {
    rising.value = false;
    nextTick(() => (rising.value = true));
}, { immediate: true });

// Pages and data are never cached for offline use, so say so instead of showing stale content.
const online = ref(true);
const syncOnline = () => (online.value = navigator.onLine !== false);
onMounted(() => {
    syncOnline();
    window.addEventListener('online', syncOnline);
    window.addEventListener('offline', syncOnline);
});
onBeforeUnmount(() => {
    window.removeEventListener('online', syncOnline);
    window.removeEventListener('offline', syncOnline);
});

// The user's own backdrop (Settings > Tampilan).
watchEffect(() => applyBackground(page.props.auth.user?.background ?? null));
// Our Space accent colour, shared by both of you.
watchEffect(() => {
    const theme = page.props.auth.user?.spaceTheme ?? 'rose';
    if (theme === 'rose') delete document.documentElement.dataset.spaceTheme;
    else document.documentElement.dataset.spaceTheme = theme;
});

// The bottom bar style the person chose in Settings (kept on the account, cached on the device).
const navStyle = computed(() => resolveNavStyle(page.props.auth.user?.navStyle));
watchEffect(() => page.props.auth.user?.navStyle && cacheNavStyle(page.props.auth.user.navStyle));

// Kept from before: the page itself eases in with CSS (see .page-rise). // The bottom bar springs up once when the app opens (@vueuse/motion). Page changes use the CSS .page-rise instead:
// a motion transform left on the page wrapper would pull every position:fixed button inside a page out of place.
const reduceMotion = typeof matchMedia === 'function' && matchMedia('(prefers-reduced-motion: reduce)').matches;
const barStart = reduceMotion ? { opacity: 1, y: 0 } : { opacity: 0, y: 28 };
const barEnter = { opacity: 1, y: 0, transition: { type: 'spring', stiffness: 260, damping: 24 } };

// The photobooth editor needs room for a preview beside its controls on tablets and desktops.
const wide = computed(() => page.url.startsWith('/photobooth'));

const items = computed(() => {
    const features = page.props.app?.features ?? {};
    return [
        { href: '/dashboard', label: t('nav.home'), icon: House, show: true },
        { href: '/tasks', label: t('nav.tasks'), icon: ListTodo, show: features.tasks },
        { href: '/calendar', label: t('nav.calendar'), icon: CalendarHeart, show: features.calendar, desktopOnly: true },
        { href: '/chat', label: t('nav.chat'), icon: MessageCircleHeart, show: features.chat && page.props.auth.user?.hasSpace, badge: page.props.badges?.chat ?? 0 },
        { href: '/berdua', label: t('memories.more'), icon: Sparkles, show: true, center: true },
        { href: '/albums', label: t('memories.albums'), icon: Images, show: features.albums, desktopOnly: true },
        { href: '/photobooth', label: t('memories.photobooth'), icon: Camera, show: features.albums, desktopOnly: true },
        { href: '/journal', label: t('memories.journal'), icon: BookHeart, show: features.journal, desktopOnly: true },
        { href: '/mood', label: t('memories.mood'), icon: Sun, show: true, desktopOnly: true },
        { href: '/settings', label: t('nav.profile'), icon: CircleUser, show: true },
    ].filter((item) => item.show);
});

onMounted(() => {
    if (NATIVE) {
        if (page.props.app?.fcm) refreshNative();
        return;
    }
    if (pushSupport() !== 'supported' || permission() !== 'granted') return;
    // Lets the server tie this session to the device, so logout can unsubscribe it.
    pushStatus().catch(() => {});
    navigator.serviceWorker.addEventListener('message', (event) => {
        if (event.data?.type === 'push-subscription-changed') refreshAfterChange(page.props.app.vapidPublicKey).catch(() => {});
    });
});

useNotificationWatch();
useArenaWatch();

// Phones: four tabs with the Berdua heart raised in the middle.
const mobileItems = computed(() => {
    const list = items.value.filter((i) => !i.desktopOnly && !i.center);
    const center = items.value.find((i) => i.center);
    list.splice(Math.min(2, list.length), 0, center);
    return list;
});

// The tapped tab lights up at once, before the next page has arrived, so a slow connection does not feel like a dead tap.
const pendingUrl = ref(null);
router.on('start', (event) => {
    const visit = event.detail.visit;
    pendingUrl.value = visit.method === 'get' && !visit.prefetch ? visit.url.pathname : null;
});
router.on('finish', () => (pendingUrl.value = null));

function isActive(href) {
    const url = (pendingUrl.value ?? page.url).split('?')[0];
    const berdua = ['/momen', '/pertanyaan', '/daftar', '/kapsul', '/voting', '/ide', '/kebiasaan', '/anggaran', '/playlist', '/impian', '/checkin', '/main', '/refleksi', '/kesepakatan', '/riwayat-kencan', '/pasangan', '/tahun-kita', '/tabungan', '/mood', '/journal', '/albums', '/photobooth', '/widget', '/arena'];
    if (href === '/berdua' && berdua.some((p) => url === p || url.startsWith(`${p}/`))) return true;
    return url === href || url.startsWith(`${href}/`) || (href === '/dashboard' && ['/my-space', '/our-space'].includes(url));
}
</script>

<template>
    <div class="relative min-h-dvh lg:flex">
        <CloudBand class="pointer-events-none fixed inset-x-0 bottom-0 z-0 w-full opacity-40 lg:hidden" />
        <a href="#isi" class="sr-only z-50 rounded-md bg-plum px-4 py-2 text-ivory focus:not-sr-only focus:fixed focus:left-3 focus:top-3">{{ $t('common.skip') }}</a>
        <!-- Desktop: quiet side rail. Phones: thumb-reachable bottom bar. -->
        <aside class="glass-bar sticky top-3 m-3 hidden h-[calc(100dvh-1.5rem)] w-60 shrink-0 flex-col rounded-[1.75rem] px-3 py-6 lg:flex">
            <Link href="/dashboard" prefetch class="mb-8 px-3 font-display text-2xl font-bold text-plum">LoveyNest</Link>
            <nav class="flex flex-col gap-1" :aria-label="$t('app.name')">
                <Link
                    v-for="item in items"
                    :key="item.href"
                    :href="item.href"
                    :prefetch="item.href !== '/chat'" :cache-for="['30s', '5m']"
                    class="flex h-11 items-center gap-3 rounded-xl px-3 text-[0.9375rem] font-medium transition"
                    :class="isActive(item.href) ? 'bg-berry text-white' : 'text-plum hover:bg-fill'"
                    :aria-current="isActive(item.href) ? 'page' : undefined"
                >
                    <component :is="item.icon" class="size-5" aria-hidden="true" />
                    {{ item.label }}
                    <span v-if="item.badge" class="ml-auto rounded-full px-2 text-xs" :class="isActive(item.href) ? 'bg-white text-berry-ink' : 'bg-berry text-white'">{{ item.badge }}</span>
                </Link>
            </nav>
        </aside>

        <BuildPrompt v-if="!page.url.startsWith('/arena/')" />

        <div v-if="!online" class="fixed inset-x-0 top-0 z-50 flex items-center justify-center gap-2 bg-plum px-4 pb-2 pt-[max(0.5rem,env(safe-area-inset-top))] text-center text-xs font-semibold text-white" role="status"><WifiOff class="size-4 shrink-0" aria-hidden="true" />{{ t('app.offline') }}</div>

        <div class="relative z-10 flex min-h-dvh flex-1 flex-col">
            <main id="isi" tabindex="-1" class="mx-auto w-full flex-1 px-5 pb-32 pt-[max(1.25rem,env(safe-area-inset-top))] sm:px-6 lg:pb-12 lg:pt-10" :class="wide ? 'max-w-2xl md:max-w-5xl' : 'max-w-2xl'">
                <OfflineBanner class="mb-4 rounded-2xl" />
                <E2eBanner />
                <UpdatePrompt v-if="NATIVE && page.url.startsWith('/dashboard')" />
                <PushPrompt v-if="page.url.startsWith('/dashboard')" />
                <!-- The page eases in with a short spring on every menu change (opacity and a short rise via CSS, no transform left behind). -->
                <!-- Gallery and Photobooth stay in memory (KeepAlive) so coming back does not rebuild them or reload their pictures.
                     Every other page is keyed by its path, so it is built fresh on every visit exactly as before. -->
                <div class="page-host" :class="{ 'page-rise': rising }" :data-dir="pageNav.dir">
                    <PageKey :k="path"><slot /></PageKey>
                </div>
            </main>
        </div>

        <BottomNav :items="mobileItems" :variant="navStyle" :active-href="mobileItems.find((i) => isActive(i.href))?.href ?? null" />

        <InviteSheet />
        <Link v-if="arena.active && !page.url.startsWith('/arena')" :href="`/arena/sesi/${arena.active.id}`" class="fixed inset-x-0 bottom-24 z-30 mx-auto flex w-max max-w-[90vw] items-center gap-2 rounded-full bg-berry px-4 py-2 text-sm font-semibold text-white shadow-lift lg:bottom-6" data-testid="arena-pill">🎮 {{ arena.active.title }} sedang berjalan · Masuk</Link>
        <ToastRegion />
        <NudgeStage />
        <CallOverlay v-if="page.props.partner" />
        <ShareSheet v-if="NATIVE" />
    </div>
</template>
