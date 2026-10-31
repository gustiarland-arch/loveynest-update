<script setup>
// More widgets for /widget (v2). Each one is small, lively and uses the two characters (blue cap for laki-laki,
// pink flower for perempuan). The look follows the chosen style: kaca (glass), lucu (sticker), kertas (paper), malam (night).
import { computed } from 'vue';
import { Link, usePage } from '@inertiajs/vue3';
import CoupleChar from '@/Components/brand/CoupleChar.vue';

const props = defineProps({ extras: { type: Object, required: true }, partner: { type: Object, default: null }, moods: { type: Object, default: null } });
const page = usePage();
const me = computed(() => page.props.auth?.user?.gender ?? null);
const them = computed(() => props.partner?.gender ?? page.props.partner?.gender ?? null);
const x = computed(() => props.extras);
const taskPct = computed(() => (x.value.tasks.total ? Math.round((x.value.tasks.done / x.value.tasks.total) * 100) : 0));
const missMeter = computed(() => Math.min(100, (x.value.nudges.mine + x.value.nudges.theirs) * 12));
const moodFace = (level) => (level == null ? 'think' : level >= 4 ? 'cheer' : level >= 3 ? 'happy' : level >= 2 ? 'think' : 'sad');
const dayLabel = (n) => (n === 0 ? 'Hari ini!' : n === 1 ? 'Besok' : `${n} hari lagi`);
</script>

<template>
    <div class="grid grid-cols-2 gap-3" data-testid="fun-widgets">
        <!-- Days together -->
        <div class="w-card w-pink col-span-2 flex items-center gap-3 p-4">
            <div class="flex w-28 shrink-0 items-end -space-x-5"><CoupleChar :gender="me" mood="love" arms="hug" class="w-16" /><CoupleChar :gender="them" mood="cheer" arms="hug" flip class="w-16" /></div>
            <div class="min-w-0">
                <p class="w-kicker">Sudah bersama</p>
                <p class="w-big tabular-nums">{{ x.days ?? '–' }} <span class="text-base font-semibold">hari</span></p>
                <p class="w-small">{{ x.streak ? `🔥 ${x.streak} hari berturut-turut saling menyapa` : 'Atur tanggal jadian di Pengaturan' }}</p>
            </div>
        </div>

        <!-- Countdown -->
        <div class="w-card w-lav flex flex-col p-4">
            <span class="w-emoji">⏳</span>
            <p class="w-kicker mt-2">Menuju</p>
            <p class="line-clamp-2 font-semibold leading-tight">{{ x.next?.title ?? 'Belum ada hari penting' }}</p>
            <p class="w-mid mt-auto pt-2 tabular-nums">{{ x.next ? dayLabel(x.next.in) : '–' }}</p>
        </div>

        <!-- Birthday -->
        <div class="w-card w-sun flex flex-col p-4">
            <span class="w-emoji">🎂</span>
            <p class="w-kicker mt-2">Ulang tahun {{ partner?.firstName ?? 'pasangan' }}</p>
            <p class="w-mid mt-auto pt-2 tabular-nums">{{ x.birthday ? dayLabel(x.birthday.in) : 'Belum diisi' }}</p>
        </div>

        <!-- Mood duo -->
        <Link href="/mood" class="w-card w-sky col-span-2 flex items-center gap-3 p-4">
            <div class="text-center"><CoupleChar :gender="me" :mood="moodFace(moods?.mine?.level)" class="mx-auto w-16" /><p class="w-small">Kamu</p></div>
            <div class="min-w-0 flex-1 text-center">
                <p class="w-kicker">Mood hari ini</p>
                <p class="text-sm font-semibold">{{ moods?.mine ? 'Kamu sudah cerita' : 'Kamu belum cerita' }} · {{ moods?.partner ? `${partner?.firstName ?? 'Dia'} sudah` : `${partner?.firstName ?? 'Dia'} belum` }}</p>
            </div>
            <div class="text-center"><CoupleChar :gender="them" :mood="moodFace(moods?.partner?.level)" flip class="mx-auto w-16" /><p class="w-small">{{ partner?.firstName ?? 'Dia' }}</p></div>
        </Link>

        <!-- Kangen-meter -->
        <div class="w-card w-pink flex flex-col p-4">
            <p class="w-kicker">Kangen-meter</p>
            <div class="meter mt-3" role="meter" :aria-valuenow="missMeter" aria-valuemin="0" aria-valuemax="100" aria-label="Kangen-meter"><span :style="{ transform: `scaleX(${missMeter / 100})` }" /></div>
            <p class="w-small mt-2">Rasa dikirim hari ini: kamu {{ x.nudges.mine }}, {{ partner?.firstName ?? 'dia' }} {{ x.nudges.theirs }}</p>
        </div>

        <!-- Tasks ring -->
        <Link href="/tasks" class="w-card w-mint flex flex-col items-center p-4 text-center">
            <svg viewBox="0 0 44 44" class="size-16 -rotate-90" aria-hidden="true"><circle cx="22" cy="22" r="18" fill="none" stroke="currentColor" stroke-opacity=".18" stroke-width="5" /><circle class="ring" cx="22" cy="22" r="18" fill="none" stroke="currentColor" stroke-width="5" stroke-linecap="round" :stroke-dasharray="`${(taskPct / 100) * 113} 113`" /></svg>
            <p class="w-kicker mt-1">Tugas hari ini</p>
            <p class="font-semibold tabular-nums">{{ x.tasks.done }}/{{ x.tasks.total }}</p>
        </Link>

        <!-- Quote -->
        <div v-if="x.quote" class="w-card w-paper col-span-2 p-4">
            <p class="w-kicker">Kutipan hari ini</p>
            <p class="mt-1 font-display text-[1.05rem] leading-snug">“{{ x.quote }}”</p>
        </div>
    </div>
</template>

<style scoped>
.w-card { position: relative; overflow: hidden; border-radius: var(--w-radius, 1.5rem); color: var(--w-ink, var(--color-plum)); background: var(--w-bg); border: var(--w-border, 0.5px solid var(--glass-edge)); box-shadow: var(--w-shadow, var(--shadow-soft)); backdrop-filter: var(--w-blur, blur(22px) saturate(1.5)); -webkit-backdrop-filter: var(--w-blur, blur(22px) saturate(1.5)); transition: transform 0.3s cubic-bezier(0.3, 1.5, 0.5, 1); animation: w-in 0.5s cubic-bezier(0.22, 1, 0.36, 1) backwards; }
.w-card:nth-child(2) { animation-delay: 0.05s; } .w-card:nth-child(3) { animation-delay: 0.1s; } .w-card:nth-child(4) { animation-delay: 0.15s; } .w-card:nth-child(5) { animation-delay: 0.2s; } .w-card:nth-child(6) { animation-delay: 0.25s; }
.w-card:active { transform: scale(0.97); }
.w-kicker { font-size: 0.7rem; font-weight: 800; letter-spacing: 0.06em; text-transform: uppercase; opacity: 0.75; }
.w-big { font-family: var(--font-display); font-size: 2.3rem; font-weight: 700; line-height: 1; }
.w-mid { font-family: var(--font-display); font-size: 1.35rem; font-weight: 700; line-height: 1.1; }
.w-small { font-size: 0.75rem; opacity: 0.8; }
.w-emoji { font-size: 1.8rem; line-height: 1; display: inline-block; animation: w-bob 2.6s ease-in-out infinite; }
.meter { height: 0.8rem; border-radius: 9999px; background: rgb(255 255 255 / 0.5); overflow: hidden; }
.meter span { display: block; height: 100%; width: 100%; transform-origin: left; border-radius: inherit; background: linear-gradient(90deg, #ff9ec2, #ff4f86); transition: transform 0.8s cubic-bezier(0.22, 1, 0.36, 1); }
.ring { transition: stroke-dasharray 0.8s ease; }
@keyframes w-in { from { opacity: 0; transform: translateY(12px) scale(0.97); } }
@keyframes w-bob { 50% { transform: translateY(-4px) rotate(-6deg); } }
@media (prefers-reduced-motion: reduce) { .w-card, .w-emoji { animation: none; } }
</style>
