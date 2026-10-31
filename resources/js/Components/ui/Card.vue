<script setup>
defineProps({
    tone: { type: String, default: 'glass' }, // glass | ivory | blush | lavender | sage
    padded: { type: Boolean, default: true },
});

const tones = {
    glass: 'glass',
    ivory: 'glass',
    blush: 'glass wash-blush',
    lavender: 'glass wash-lavender',
    sage: 'glass wash-sage',
};
</script>

<template>
    <section class="rounded-[1.375rem] shadow-soft" :class="[tones[tone] ?? tones.glass, padded && 'p-5']">
        <slot />
    </section>
</template>
