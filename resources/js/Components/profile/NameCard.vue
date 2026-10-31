<script setup>
// Someone's name card exactly as they styled it in Profil: their chosen background (latar), their frame (bingkai) around
// the photo, and the text colour that reads well on that background. Used for the partner's profile page.
import { computed } from 'vue';
import Avatar from '@/Components/ui/Avatar.vue';
import ProfileCardBackdrop, { cardTone } from '@/Components/settings/ProfileCardBackdrop.vue';

const props = defineProps({
    name: { type: String, required: true },
    sub: { type: String, default: '' },
    avatarUrl: { type: String, default: null },
    frame: { type: String, default: null },
    card: { type: String, default: null },
});
const onDark = computed(() => cardTone(props.card) === 'light');
</script>

<template>
    <div class="name-card glass relative isolate overflow-hidden rounded-[1.75rem] px-5 pb-6 pt-4 text-center">
        <ProfileCardBackdrop :id="card" class="-z-10" />
        <div v-if="card" class="absolute inset-0 -z-10" :class="onDark ? 'bg-black/25' : 'bg-white/30'" aria-hidden="true" />
        <span v-if="card" class="nc-shine pointer-events-none absolute inset-0 -z-10" aria-hidden="true" />
        <Avatar :name="name" :src="avatarUrl" :frame="frame" size="hero" class="nc-avatar mx-auto mt-3" />
        <h2 class="mt-4 break-words font-display text-2xl font-semibold" :class="card ? (onDark ? 'text-white [text-shadow:0_1px_8px_rgb(0_0_0/0.4)]' : 'text-[#2a1722]') : 'text-plum'">{{ name }}</h2>
        <p v-if="sub" class="mt-0.5 text-sm" :class="card ? (onDark ? 'text-white/85' : 'text-[#4a3340]') : 'text-ink-muted'">{{ sub }}</p>
        <slot />
    </div>
</template>

<style scoped>
.name-card {
    animation: nc-in 0.6s cubic-bezier(0.22, 1, 0.36, 1) backwards;
}
.nc-avatar {
    animation: nc-pop 0.7s 0.1s cubic-bezier(0.3, 1.5, 0.5, 1) backwards;
}
/* a slow sheen crosses the card now and then, like light on a printed card */
.nc-shine {
    background: linear-gradient(115deg, transparent 35%, rgb(255 255 255 / 0.28) 50%, transparent 65%);
    transform: translateX(-100%);
    animation: nc-shine 6s 1s ease-in-out infinite;
}
@keyframes nc-in {
    from { opacity: 0; transform: translateY(14px) scale(0.98); }
}
@keyframes nc-pop {
    from { opacity: 0; transform: scale(0.7); }
}
@keyframes nc-shine {
    0%, 70% { transform: translateX(-100%); }
    100% { transform: translateX(100%); }
}
@media (prefers-reduced-motion: reduce) {
    .name-card, .nc-avatar, .nc-shine { animation: none; }
}
</style>
