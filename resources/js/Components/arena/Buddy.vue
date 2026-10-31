<script setup>
// A player character in the Arena, after the LoveyNest characters: blue with a cap for laki-laki, pink with a flower
// for perempuan (lib/couple.js). Who it is: `who="me" | "partner"`, or the older `tone` (pink = me, blue = partner,
// as the games always used them). `gender` overrides both.
import { computed } from 'vue';
import { usePage } from '@inertiajs/vue3';
import CoupleChar from '@/Components/brand/CoupleChar.vue';

const props = defineProps({
    tone: { type: String, default: 'pink' },
    who: { type: String, default: null },
    gender: { type: String, default: null },
    mood: { type: String, default: 'happy' },
    size: { type: [Number, String], default: 96 },
    flip: { type: Boolean, default: false },
});
const page = usePage();
const person = computed(() => props.who ?? (props.tone === 'blue' ? 'partner' : 'me'));
const resolvedGender = computed(() => props.gender ?? (person.value === 'partner' ? page.props.partner?.gender : page.props.auth?.user?.gender) ?? null);
const arms = computed(() => ({ cheer: 'up', wink: 'wave', happy: 'down', think: 'down', sad: 'down', oops: 'down' })[props.mood] ?? 'down');
</script>

<template>
    <CoupleChar :gender="resolvedGender" :mood="mood" :arms="arms" :size="size" :flip="flip" />
</template>
