<script setup>
// The call screen, mounted once in the layout so a call survives menu changes. Full screen while ringing or talking;
// "Kecilkan" turns it into a small glass pill at the top so the app can be used during the call.
import { computed, onMounted, watch } from 'vue';
import { usePage } from '@inertiajs/vue3';
import { Phone, PhoneOff, Mic, MicOff, Volume2, Volume1, Minimize2 } from '@lucide/vue';
import Avatar from '@/Components/ui/Avatar.vue';
import CoupleChar from '@/Components/brand/CoupleChar.vue';
import { acceptCall, call, clock, configureCalls, declineCall, hangUp, resumeAudio, toggleMute, toggleSpeaker, watchCalls } from '@/lib/call';
import { useToast } from '@/composables/useToast';

const page = usePage();
const toast = useToast();
const partner = computed(() => page.props.partner);

watch(
    partner,
    (p) => configureCalls({ iceServers: page.props.app?.ice, partner: p ? { name: p.firstName ?? p.name, avatarUrl: p.avatarUrl, avatarFrame: p.avatarFrame, gender: p.gender } : null }),
    { immediate: true },
);
onMounted(() => watchCalls());
watch(
    () => call.error,
    (e) => {
        if (e) {
            toast.error(e, { timeout: 7000 });
            call.error = '';
        }
    },
);

const open = computed(() => call.status !== 'idle');
const label = computed(
    () =>
        ({
            outgoing: 'Memanggil…',
            incoming: 'Panggilan suara masuk',
            connecting: 'Menyambungkan…',
            active: clock(call.seconds),
            ended: { declined: 'Panggilan ditolak', missed: 'Tidak dijawab', cancelled: 'Panggilan dibatalkan', lost: 'Sambungan terputus', hangup: 'Panggilan selesai' }[call.reason] ?? 'Panggilan selesai',
        })[call.status] ?? '',
);
const mood = computed(() => ({ incoming: 'love', outgoing: 'happy', connecting: 'think', active: 'cheer', ended: call.reason === 'hangup' ? 'wink' : 'sad' })[call.status] ?? 'happy');
</script>

<template>
    <Teleport to="body">
        <Transition name="call">
            <div v-if="open && !call.minimized" class="call-screen fixed inset-0 z-[95] flex flex-col items-center justify-between px-6 pb-[max(2rem,env(safe-area-inset-bottom))] pt-[max(3rem,env(safe-area-inset-top))] text-white" role="dialog" aria-modal="true" :aria-label="`Panggilan dengan ${call.partner?.name ?? 'pasangan'}`" data-testid="call-screen" @pointerdown="call.needsTap && resumeAudio()">
                <div class="call-bg" aria-hidden="true"><span class="cb1" /><span class="cb2" /></div>
                <button v-if="call.status === 'active' || call.status === 'connecting'" type="button" class="call-glass absolute right-4 top-[max(1rem,env(safe-area-inset-top))] flex h-10 items-center gap-1.5 rounded-full px-3 text-sm font-semibold" @click="call.minimized = true"><Minimize2 class="size-4" aria-hidden="true" />Kecilkan</button>

                <div class="relative flex flex-col items-center text-center">
                    <p class="text-sm font-semibold uppercase tracking-[0.14em] opacity-80">LoveyNest · suara</p>
                    <div class="relative mt-8">
                        <span v-if="call.status === 'incoming' || call.status === 'outgoing'" class="ring-wave" aria-hidden="true" /><span v-if="call.status === 'incoming' || call.status === 'outgoing'" class="ring-wave w2" aria-hidden="true" />
                        <span v-if="call.status === 'active'" class="talk-glow" aria-hidden="true" />
                        <Avatar :name="call.partner?.name ?? '?'" :src="call.partner?.avatarUrl" :frame="call.partner?.avatarFrame" size="hero" class="relative" />
                    </div>
                    <h2 class="mt-6 font-display text-3xl font-semibold" data-testid="call-name">{{ call.partner?.name }}</h2>
                    <p class="mt-1 text-base font-semibold tabular-nums opacity-90" role="status" data-testid="call-status">{{ label }}</p>
                    <button v-if="call.needsTap" type="button" class="call-glass mt-3 rounded-full px-4 py-2 text-sm font-semibold" data-testid="call-hear" @click="resumeAudio">🔊 Ketuk untuk mendengar suara</button>
                    <div class="mt-6 w-28"><CoupleChar :gender="call.partner?.gender" :mood="mood" :arms="call.status === 'incoming' ? 'wave' : call.status === 'active' ? 'up' : 'down'" /></div>
                </div>

                <div v-if="call.status === 'incoming'" class="relative flex w-full max-w-xs items-center justify-between">
                    <button type="button" class="call-btn" aria-label="Tolak" data-testid="call-decline" @click="declineCall"><span class="ci bg-[#ff3b5c]"><PhoneOff class="size-7" aria-hidden="true" /></span><span>Tolak</span></button>
                    <button type="button" class="call-btn answer" aria-label="Angkat" data-testid="call-accept" @click="acceptCall"><span class="ci bg-[#34c759]"><Phone class="size-7" aria-hidden="true" /></span><span>Angkat</span></button>
                </div>
                <div v-else-if="call.status !== 'ended'" class="relative flex w-full max-w-sm items-center justify-between">
                    <button type="button" class="call-btn" :aria-pressed="call.muted" :aria-label="call.muted ? 'Nyalakan mikrofon' : 'Bisukan'" data-testid="call-mute" @click="toggleMute"><span class="ci call-glass"><component :is="call.muted ? MicOff : Mic" class="size-7" aria-hidden="true" /></span><span>{{ call.muted ? 'Dibisukan' : 'Bisukan' }}</span></button>
                    <button type="button" class="call-btn" aria-label="Akhiri panggilan" data-testid="call-hangup" @click="hangUp"><span class="ci bg-[#ff3b5c]"><PhoneOff class="size-7" aria-hidden="true" /></span><span>Akhiri</span></button>
                    <button type="button" class="call-btn" :aria-pressed="call.speaker" aria-label="Pengeras suara" @click="toggleSpeaker"><span class="ci call-glass"><component :is="call.speaker ? Volume2 : Volume1" class="size-7" aria-hidden="true" /></span><span>Speaker</span></button>
                </div>
                <div v-else class="h-24" />
            </div>
        </Transition>

        <!-- minimized: a glass pill on top of the app -->
        <button v-if="open && call.minimized" type="button" class="call-pill fixed left-1/2 top-[max(0.5rem,env(safe-area-inset-top))] z-[94] flex -translate-x-1/2 items-center gap-2 rounded-full px-4 py-2 text-sm font-semibold text-white" data-testid="call-pill" @click="call.minimized = false">
            <span class="pill-dot" aria-hidden="true" />{{ call.partner?.name }} · {{ call.status === 'active' ? clock(call.seconds) : 'menyambungkan…' }}
        </button>
    </Teleport>
</template>

<style scoped>
.call-screen { background: linear-gradient(165deg, #3a1d4f, #b8446f 55%, #ff8fb1); }
.call-bg { position: absolute; inset: 0; overflow: hidden; pointer-events: none; }
.call-bg span { position: absolute; width: 70vmax; aspect-ratio: 1; border-radius: 50%; filter: blur(70px); opacity: 0.45; }
.cb1 { background: #9f80ff; left: -30vmax; top: -25vmax; animation: drift 9s ease-in-out infinite alternate; }
.cb2 { background: #ffb3cf; right: -35vmax; bottom: -30vmax; animation: drift 12s ease-in-out infinite alternate-reverse; }
.call-glass { background: rgb(255 255 255 / 0.18); border: 0.5px solid rgb(255 255 255 / 0.45); box-shadow: inset 1px 1px 0 rgb(255 255 255 / 0.4); backdrop-filter: blur(20px) saturate(1.6); -webkit-backdrop-filter: blur(20px) saturate(1.6); }
.call-btn { display: flex; flex-direction: column; align-items: center; gap: 0.45rem; color: #fff; font-size: 0.75rem; font-weight: 700; }
.ci { display: grid; place-items: center; width: 4.4rem; height: 4.4rem; border-radius: 9999px; box-shadow: 0 12px 24px -12px rgb(0 0 0 / 0.5); transition: transform 0.2s cubic-bezier(0.3, 1.6, 0.5, 1); }
.call-btn:active .ci { transform: scale(0.9); }
.call-btn.answer .ci { animation: answer 1.2s ease-in-out infinite; }
.call-btn[aria-pressed='true'] .ci { background: #fff; color: #b8446f; }
.ring-wave { position: absolute; inset: -14px; border-radius: 50%; border: 2px solid rgb(255 255 255 / 0.7); animation: wave 1.8s ease-out infinite; }
.ring-wave.w2 { animation-delay: 0.9s; }
.talk-glow { position: absolute; inset: -18px; border-radius: 50%; background: radial-gradient(circle, rgb(255 255 255 / 0.35), transparent 70%); animation: glow 1.6s ease-in-out infinite; }
.call-pill { background: linear-gradient(135deg, rgb(52 199 89 / 0.85), rgb(35 160 70 / 0.85)); border: 0.5px solid rgb(255 255 255 / 0.5); box-shadow: 0 10px 24px -10px rgb(0 0 0 / 0.4), inset 1px 1px 0 rgb(255 255 255 / 0.4); backdrop-filter: blur(16px); -webkit-backdrop-filter: blur(16px); }
.pill-dot { width: 0.55rem; height: 0.55rem; border-radius: 50%; background: #fff; animation: glow 1.2s ease-in-out infinite; }
.call-enter-active, .call-leave-active { transition: opacity 0.3s ease, transform 0.35s cubic-bezier(0.22, 1, 0.36, 1); }
.call-enter-from, .call-leave-to { opacity: 0; transform: scale(1.04); }
@keyframes drift { to { transform: translate(14vmax, 10vmax) scale(1.15); } }
@keyframes wave { from { transform: scale(1); opacity: 0.8; } to { transform: scale(1.6); opacity: 0; } }
@keyframes glow { 50% { transform: scale(1.08); opacity: 0.6; } }
@keyframes answer { 0%, 100% { transform: rotate(0); } 10% { transform: rotate(-14deg); } 20% { transform: rotate(14deg); } 30% { transform: rotate(-10deg); } 40% { transform: rotate(0); } }
@media (prefers-reduced-motion: reduce) {
    .cb1, .cb2, .ring-wave, .talk-glow, .pill-dot, .call-btn.answer .ci { animation: none; }
}
</style>
