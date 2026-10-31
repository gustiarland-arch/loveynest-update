<script setup>
import { computed, ref } from 'vue';
import { router, usePage } from '@inertiajs/vue3';
import { Check, House, ListTodo, MessageCircleHeart, Sparkles, CircleUser } from '@lucide/vue';
import Card from '@/Components/ui/Card.vue';
import Button from '@/Components/ui/Button.vue';
import BottomNav from '@/Components/layout/BottomNav.vue';
import { NAV_STYLES, cacheNavStyle, resolveNavStyle } from '@/lib/navStyle';
import { t } from '@/i18n';

const page = usePage();
const saved = computed(() => resolveNavStyle(page.props.auth.user?.navStyle));
const picked = ref(saved.value);
const saving = ref(false);

// Same five tabs as the real bar; tapping inside the preview moves the highlight so the motion can be tried.
const demo = [
    { href: 'a', label: t('nav.home'), icon: House },
    { href: 'b', label: t('nav.tasks'), icon: ListTodo },
    { href: 'c', label: t('berdua.title'), icon: Sparkles, center: true },
    { href: 'd', label: t('nav.chat'), icon: MessageCircleHeart, badge: 2 },
    { href: 'e', label: t('nav.profile'), icon: CircleUser },
];

function save() {
    saving.value = true;
    router.patch('/settings/nav-style', { style: picked.value }, {
        preserveScroll: true,
        onSuccess: () => cacheNavStyle(picked.value),
        onFinish: () => (saving.value = false),
    });
}
</script>

<template>
    <Card class="mt-4 space-y-4">
        <div>
            <h2 class="text-lg font-semibold">{{ t('settings.nav.title') }}</h2>
            <p class="text-sm text-ink-muted">{{ t('settings.nav.lead') }}</p>
        </div>
        <ul class="space-y-3">
            <li v-for="style in NAV_STYLES" :key="style">
                <div class="glass rounded-[1.25rem] border p-3 transition" :class="picked === style ? '!border-berry wash-blush' : ''">
                    <button type="button" class="flex w-full items-center justify-between gap-2 text-left" :aria-pressed="picked === style" @click="picked = style">
                        <span>
                            <span class="block font-semibold text-plum">{{ t(`settings.nav.${style}`) }}</span>
                            <span class="block text-xs text-ink-muted">{{ t(`settings.nav.${style}Hint`) }}</span>
                        </span>
                        <span class="grid size-6 shrink-0 place-items-center rounded-full border" :class="picked === style ? 'border-berry bg-berry text-white' : 'border-[var(--glass-edge)]'"><Check v-if="picked === style" class="size-4" aria-hidden="true" /></span>
                    </button>
                    <div class="mt-3 rounded-[1rem] bg-[color-mix(in_srgb,var(--color-plum)_6%,var(--bg-top))] px-2 pb-3 pt-7">
                        <BottomNav :items="demo" :variant="style" preview />
                    </div>
                </div>
            </li>
        </ul>
        <Button :loading="saving" :disabled="picked === saved" @click="save">{{ t('common.save') }}</Button>
    </Card>
</template>
