<script setup>
import { computed, onBeforeUnmount, onMounted, ref, watch } from 'vue';
import { playSound } from '@/lib/sound';
import { usePage } from '@inertiajs/vue3';
import { http } from '@/lib/http';
import { t } from '@/i18n';
import CoupleChar from '@/Components/brand/CoupleChar.vue';

// Shows what the partner sent (hug, kiss, miss you, cheer) as a short full-screen animation, once per item.
// Items come from /nudges/pending: when the app opens, when it comes back to the foreground, and every 20 s while it is open.
const page = usePage();
const queue = ref([]);
const seenIds = new Set();
const current = computed(() => queue.value[0] ?? null);
let poll = null;
let closeTimer = null;
let busy = false;

const hasPartner = computed(() => !!page.props.partner);
// Characters follow each person's gender (lib/couple.js): the sender is shown with their own, you with yours.
const myGender = computed(() => page.props.auth?.user?.gender ?? null);
const fromGender = computed(() => current.value?.fromGender ?? page.props.partner?.gender ?? null);

async function check() {
    if (busy || current.value || document.visibilityState !== 'visible' || !hasPartner.value) return;
    busy = true;
    try {
        const data = await http.get('/nudges/pending', { timeout: 8000 });
        const fresh = (data?.items ?? []).filter((n) => !seenIds.has(n.id));
        if (fresh.length) queue.value = fresh;
    } catch {
        // Offline or a hiccup: the next check tries again.
    } finally {
        busy = false;
    }
}

function close() {
    clearTimeout(closeTimer);
    queue.value = queue.value.slice(1);
}

watch(current, (n) => {
    clearTimeout(closeTimer);
    if (!n) return;
    seenIds.add(n.id);
    if (n.id > 0) http.post(`/nudges/${n.id}/seen`, {}).catch(() => {});
    // Phones only vibrate after the person has touched the page (otherwise the browser logs an error).
    if (navigator.userActivation?.hasBeenActive) navigator.vibrate?.([30, 50, 30]);
    playSound(n.kind);
    closeTimer = setTimeout(close, 8500);
});

function onVisible() {
    if (document.visibilityState === 'visible') check();
}
// Lets a test (or a "play again" button later) run a scene without the server.
function onPreview(e) {
    queue.value = [{ id: -Date.now(), kind: e.detail.kind, from: e.detail.from ?? 'Dia' }];
}
// The sender's side: a short, non-blocking flight of the feeling leaving the phone (sent from NudgeBar).
const flights = ref([]);
let flightId = 0;
function onSent(e) {
    const id = ++flightId;
    flights.value = [...flights.value, { id, kind: e.detail.kind, to: e.detail.to ?? '' }];
    setTimeout(() => (flights.value = flights.value.filter((f) => f.id !== id)), 2300);
}
function onKey(e) {
    if (e.key === 'Escape' && current.value) close();
}

onMounted(() => {
    check();
    poll = setInterval(check, 20000);
    document.addEventListener('visibilitychange', onVisible);
    window.addEventListener('nudge-preview', onPreview);
    window.addEventListener('nudge-sent', onSent);
    window.addEventListener('keydown', onKey);
});
onBeforeUnmount(() => {
    clearInterval(poll);
    clearTimeout(closeTimer);
    document.removeEventListener('visibilitychange', onVisible);
    window.removeEventListener('nudge-preview', onPreview);
    window.removeEventListener('nudge-sent', onSent);
    window.removeEventListener('keydown', onKey);
});

// Deterministic "random" so every scene looks the same on both phones.
const rnd = (i, salt) => {
    const x = Math.sin(i * 12.9898 + salt * 78.233) * 43758.5453;
    return x - Math.floor(x);
};
const hearts = Array.from({ length: 14 }, (_, i) => ({
    s: { '--dx': `${Math.round((rnd(i, 1) - 0.5) * 280)}px`, '--dy': `${-Math.round(60 + rnd(i, 2) * 200)}px`, '--delay': `${(0.9 + rnd(i, 3) * 1.1).toFixed(2)}s`, '--size': `${(1 + rnd(i, 4) * 1.1).toFixed(2)}rem` },
    c: ['💖', '💗', '💕', '💘'][i % 4],
}));
const confetti = Array.from({ length: 26 }, (_, i) => ({
    s: { left: `${Math.round(rnd(i, 5) * 100)}%`, '--delay': `${(rnd(i, 6) * 2.2).toFixed(2)}s`, '--dur': `${(2.2 + rnd(i, 7) * 1.6).toFixed(2)}s`, '--rot': `${Math.round(rnd(i, 8) * 720 - 360)}deg`, background: ['#ff6f9c', '#ffd166', '#7ad7c1', '#8f7cf0', '#ff9f68'][i % 5] },
}));
const stars = Array.from({ length: 12 }, (_, i) => ({ s: { left: `${Math.round(rnd(i, 9) * 96)}%`, top: `${Math.round(rnd(i, 10) * 55)}%`, '--delay': `${(rnd(i, 11) * 2).toFixed(2)}s` } }));
// The caption pops in letter by letter.
// Words stay whole (a line never breaks inside a word); each letter still gets its own little delay.
const words = computed(() => {
    if (!current.value) return [];
    let i = 0;
    return t(`nudge.stage.${current.value.kind}`, { name: current.value.from })
        .split(' ')
        .map((word) => [...word].map((ch) => ({ ch, i: i++ })));
});
const sparkles = Array.from({ length: 10 }, (_, i) => ({ s: { left: `${8 + Math.round(rnd(i, 13) * 84)}%`, top: `${6 + Math.round(rnd(i, 14) * 80)}%`, '--delay': `${(rnd(i, 15) * 2.4).toFixed(2)}s`, '--size': `${(0.6 + rnd(i, 16) * 0.9).toFixed(2)}rem` } }));
const prints = Array.from({ length: 5 }, (_, i) => ({ s: { left: `${8 + Math.round(rnd(i, 17) * 74)}%`, top: `${8 + Math.round(rnd(i, 18) * 70)}%`, '--rot': `${Math.round(rnd(i, 19) * 60 - 30)}deg`, '--delay': `${(1.1 + i * 0.32).toFixed(2)}s` } }));
const fireworks = Array.from({ length: 4 }, (_, i) => ({ s: { left: `${14 + Math.round(rnd(i, 20) * 70)}%`, top: `${6 + Math.round(rnd(i, 21) * 34)}%`, '--delay': `${(0.6 + i * 0.55).toFixed(2)}s`, '--c': ['#fff36b', '#ff8fd0', '#7ad7ff', '#9cff8f'][i] } }));
const flightEmoji = { peluk: '🤗', cium: '💋', kangen: '💌', semangat: '⭐' };
const rise = Array.from({ length: 8 }, (_, i) => ({ s: { left: `${20 + Math.round(rnd(i, 12) * 60)}%`, '--delay': `${(i * 0.55).toFixed(2)}s` }, c: i % 3 === 0 ? '💭' : '💙' }));
</script>

<template>
    <Teleport to="body">
        <Transition name="stage">
            <div
                v-if="current"
                :key="current.id"
                class="stage fixed inset-0 z-[90] grid place-items-center overflow-hidden px-6 text-white"
                :class="`stage-${current.kind}`"
                role="dialog"
                aria-modal="true"
                :aria-label="t(`nudge.stage.${current.kind}`, { name: current.from })"
                @click="close"
            >
                <!-- moving light behind every scene -->
                <div class="glow g1" aria-hidden="true" /><div class="glow g2" aria-hidden="true" />
                <div class="absolute inset-0" aria-hidden="true"><span v-for="(sp, i) in sparkles" :key="'s' + i" class="spark" :style="sp.s">✦</span></div>

                <div v-if="current.kind === 'semangat'" class="absolute inset-0" aria-hidden="true">
                    <span v-for="(c, i) in confetti" :key="i" class="confetti" :style="c.s" />
                    <span v-for="(f, i) in fireworks" :key="'f' + i" class="firework" :style="f.s"><i v-for="k in 10" :key="k" :style="{ '--a': `${k * 36}deg` }" /></span>
                </div>
                <div v-if="current.kind === 'kangen'" class="absolute inset-0" aria-hidden="true">
                    <span v-for="(s, i) in stars" :key="i" class="twinkle" :style="s.s">✦</span>
                    <svg class="moon" viewBox="0 0 100 100"><path d="M62 12a38 38 0 1 0 26 62A32 32 0 0 1 62 12Z" fill="#fff6c9" /><circle cx="40" cy="52" r="3" fill="#3d2336" /><path d="M46 64q6 4 12 0" fill="none" stroke="#3d2336" stroke-width="2.4" stroke-linecap="round" /></svg>
                    <span class="shooting" />
                    <span v-for="(r, i) in rise" :key="'r' + i" class="rise" :style="r.s">{{ r.c }}</span>
                </div>
                <div v-if="current.kind === 'cium'" class="absolute inset-0" aria-hidden="true">
                    <svg v-for="(p, i) in prints" :key="'p' + i" class="print" :style="p.s" viewBox="-34 -6 68 52"><path d="M0 6C-14-8-30 0-28 12-26 26-6 32 0 38 6 32 26 26 28 12 30 0 14-8 0 6Z" fill="#ff2e63" opacity=".85" /><path d="M-24 14C-12 18 12 18 24 14" fill="none" stroke="#b3123f" stroke-width="2.2" stroke-linecap="round" /></svg>
                </div>

                <div class="relative z-[1] flex w-full max-w-sm flex-col items-center">
                    <div class="scene relative aspect-square w-[min(84vw,21rem)]" aria-hidden="true">
                        <!-- Peluk: two mochi run to each other, squeeze, and a big heart swells behind them. -->
                        <template v-if="current.kind === 'peluk'">
                            <svg class="bigheart" viewBox="0 0 24 24"><path d="M12 21C5 15.5 2 12 2 8.2 2 5.2 4.3 3 7 3c2 0 3.8 1.1 5 3 1.2-1.9 3-3 5-3 2.7 0 5 2.2 5 5.2 0 3.8-3 7.3-10 12.8Z" fill="#fff" opacity=".35" /></svg>
                            <div class="absolute bottom-[10%] left-[10%] w-[44%] hug-l"><div class="hop"><CoupleChar :gender="fromGender" mood="love" arms="hug" /></div></div>
                            <div class="absolute bottom-[10%] right-[10%] w-[44%] hug-r"><div class="hop d"><CoupleChar :gender="myGender" mood="cheer" arms="hug" flip /></div></div>
                            <span class="blush-pop">💞</span>
                            <span v-for="(h, i) in hearts" :key="i" class="burst" :style="h.s">{{ h.c }}</span>
                        </template>

                        <!-- Cium: they lean in, a MUAH! pops and the kiss stamps between them. -->
                        <template v-else-if="current.kind === 'cium'">
                            <div class="absolute bottom-[12%] left-[4%] w-[42%] kiss-l"><CoupleChar :gender="fromGender" mood="kiss" /></div>
                            <div class="absolute bottom-[12%] right-[4%] w-[42%] kiss-r"><div class="shy"><CoupleChar :gender="myGender" mood="love" flip /></div></div>
                            <span class="ring ring-a" /><span class="ring ring-b" />
                            <svg class="lips" viewBox="-34 -6 68 52"><path d="M0 6C-14-8-30 0-28 12-26 26-6 32 0 38 6 32 26 26 28 12 30 0 14-8 0 6Z" fill="#ff2e63" /><path d="M-24 14C-12 18 12 18 24 14" fill="none" stroke="#b3123f" stroke-width="2.2" stroke-linecap="round" /><path d="M-14 6c4-4 9-3 14 1" fill="none" stroke="#fff" stroke-opacity=".55" stroke-width="3" stroke-linecap="round" /></svg>
                            <span class="muah">MUAH!</span>
                            <span v-for="(h, i) in hearts.slice(0, 9)" :key="i" class="burst late" :style="h.s">{{ h.c }}</span>
                        </template>

                        <!-- Kangen: a sleepy mochi hugs a heart pillow and dreams of you in a little thought cloud. -->
                        <template v-else-if="current.kind === 'kangen'">
                            <div class="dream"><span class="dream-dot a" /><span class="dream-dot b" /><div class="dream-cloud"><span class="dream-heart"><CoupleChar :gender="myGender" mood="wink" arms="wave" /></span></div></div>
                            <div class="absolute bottom-[8%] left-1/2 w-[56%] -translate-x-1/2"><div class="sway"><CoupleChar :gender="fromGender" mood="sleepy" arms="hold" /><span class="pillow">💗</span></div></div>
                            <span class="zz">z</span><span class="zz z2">z</span>
                        </template>

                        <!-- Semangat: a cheering mochi with a headband bounces in front of a smiling sun, fireworks pop. -->
                        <template v-else>
                            <svg class="sun" viewBox="-60 -60 120 120"><g class="sun-rays"><template v-for="i in 12" :key="i"><rect x="-4" y="-56" width="8" height="18" rx="4" fill="#ffe27a" :transform="`rotate(${i * 30})`" /></template></g><circle r="32" fill="#ffd34d" /><circle r="32" fill="none" stroke="#fff" stroke-opacity=".5" stroke-width="3" /><circle cx="-11" cy="-4" r="3.4" fill="#3d2336" /><circle cx="11" cy="-4" r="3.4" fill="#3d2336" /><path d="M-10 8q10 9 20 0" fill="none" stroke="#3d2336" stroke-width="3" stroke-linecap="round" /></svg>
                            <div class="absolute bottom-[8%] left-1/2 w-[50%] -translate-x-1/2"><div class="jump"><CoupleChar :gender="fromGender" mood="cheer" arms="up" />
                                <svg class="absolute inset-0 overflow-visible" viewBox="0 0 100 96"><path d="M13 36Q50 8 87 36" fill="none" stroke="#ff3d6e" stroke-width="7" stroke-linecap="round" /><path d="M84 32l10-6-3 12Zm0 0l8 10-12 0Z" fill="#ff3d6e" /></svg></div></div>
                            <span class="pom pom-l">🎉</span><span class="pom pom-r">✨</span>
                        </template>
                    </div>

                    <p class="caption mt-2 text-center font-display text-[2rem] font-bold leading-tight" aria-hidden="true"><template v-for="(word, w) in words" :key="w"><span class="whitespace-nowrap"><span v-for="l in word" :key="l.i" class="letter" :style="{ '--i': l.i }">{{ l.ch }}</span></span>{{ ' ' }}</template></p>
                    <p class="sub mt-1 text-center text-sm font-semibold opacity-90">{{ t(`nudge.stageSub.${current.kind}`) }}</p>
                    <p class="mt-6 text-xs font-semibold uppercase tracking-[0.12em] opacity-70">{{ t('nudge.tapClose') }}</p>
                </div>
            </div>
        </Transition>
        <div v-for="f in flights" :key="f.id" class="flight pointer-events-none fixed inset-0 z-[85] grid place-items-center" aria-hidden="true">
            <div class="flight-burst" />
            <div class="flight-mochi"><CoupleChar :gender="myGender" :mood="f.kind === 'cium' ? 'kiss' : f.kind === 'kangen' ? 'love' : 'cheer'" :arms="f.kind === 'peluk' ? 'hug' : 'up'" /></div>
            <span class="flight-item">{{ flightEmoji[f.kind] }}</span>
            <span v-for="k in 6" :key="k" class="flight-trail" :style="{ '--k': k }">{{ f.kind === 'semangat' ? '✦' : '♥' }}</span>
            <p class="flight-chip">{{ t('nudge.sentFly.' + f.kind, { name: f.to }) }}</p>
        </div>
        <p class="sr-only" role="status" aria-live="polite">{{ flights.length ? t('nudge.sent', { name: flights[flights.length - 1].to }) : '' }}</p>
    </Teleport>
</template>

<style scoped>
.stage { background: var(--bg); backdrop-filter: blur(6px); }
.stage-peluk { --bg: linear-gradient(160deg, #ff9db8f2, #ffc8a2f2); }
.stage-cium { --bg: linear-gradient(160deg, #ff5d8ff0, #b58bffee); }
.stage-kangen { --bg: linear-gradient(170deg, #1f2250f5, #5a4aa8f2); }
.stage-semangat { --bg: linear-gradient(170deg, #ffb25cf2, #ff7aa5f0); }
.caption { text-shadow: 0 3px 14px rgb(0 0 0 / 0.25); }
.letter { display: inline-block; animation: letter 0.6s calc(0.45s + var(--i) * 0.035s) cubic-bezier(0.2, 1.8, 0.4, 1) both; }
.sub { animation: pop 0.6s 1s ease-out both; }
.glow { position: absolute; width: 70vmax; aspect-ratio: 1; border-radius: 50%; filter: blur(60px); opacity: 0.45; background: #fff; }
.g1 { left: -30vmax; top: -30vmax; animation: glow 7s ease-in-out infinite alternate; }
.g2 { right: -35vmax; bottom: -35vmax; animation: glow 9s ease-in-out infinite alternate-reverse; opacity: 0.3; }
.spark { position: absolute; font-size: var(--size); color: #fff; opacity: 0; animation: spark 2.6s var(--delay) ease-in-out infinite; }
.hop { animation: hop 0.55s 1s ease-in-out 3 alternate; transform-origin: bottom center; }
.hop.d { animation-delay: 1.1s; }
.bigheart { position: absolute; left: 50%; top: 4%; width: 78%; transform: translateX(-50%); animation: bigheart 1.4s 0.8s ease-in-out infinite; }
.blush-pop { position: absolute; left: 50%; top: 30%; font-size: 2.6rem; animation: blushpop 1.2s 0.9s cubic-bezier(0.2, 1.8, 0.4, 1) both; }
.shy { animation: shy 1.4s 1.2s ease-in-out infinite; transform-origin: bottom center; }
.muah { position: absolute; left: 50%; top: 2%; font: 900 2.4rem/1 var(--font-display, sans-serif); color: #fff; letter-spacing: 0.04em; -webkit-text-stroke: 2px #ff2e63; text-shadow: 0 4px 0 #b3123f; animation: muah 0.9s 0.9s cubic-bezier(0.2, 1.8, 0.4, 1) both; }
.print { position: absolute; width: 3.6rem; opacity: 0; transform: rotate(var(--rot)) scale(0); animation: print 0.5s var(--delay) cubic-bezier(0.2, 1.8, 0.4, 1) forwards; }
.shooting { position: absolute; left: -10%; top: 12%; width: 7rem; height: 2px; border-radius: 2px; background: linear-gradient(90deg, transparent, #fff); opacity: 0; animation: shoot 3.2s 0.8s ease-in infinite; }
.dream { position: absolute; right: 2%; top: 0; width: 46%; animation: pop 0.8s 1s cubic-bezier(0.2, 1.6, 0.4, 1) both; }
.dream-cloud { position: relative; margin-left: auto; width: 74%; aspect-ratio: 1.3; border-radius: 50%; background: #fff; display: grid; place-items: center; box-shadow: 0 8px 22px rgb(0 0 0 / 0.2); animation: float 3s ease-in-out infinite; }
.dream-heart { display: block; width: 58%; animation: beat 1.4s ease-in-out infinite; }
.dream-dot { position: absolute; border-radius: 50%; background: #fff; }
.dream-dot.a { left: 4%; bottom: -28%; width: 0.7rem; height: 0.7rem; }
.dream-dot.b { left: 16%; bottom: -12%; width: 1.1rem; height: 1.1rem; }
.zz { position: absolute; left: 28%; top: 36%; font: 800 1.4rem/1 var(--font-display, sans-serif); opacity: 0; animation: zz 2.6s 1s ease-out infinite; }
.zz.z2 { animation-delay: 2.3s; font-size: 1rem; }
.firework { position: absolute; width: 0; height: 0; }
.firework i { position: absolute; left: -2px; top: -2px; width: 4px; height: 4px; border-radius: 50%; background: var(--c); box-shadow: 0 0 8px var(--c); opacity: 0; animation: firework 1.4s var(--delay) ease-out infinite; }
.pom { position: absolute; top: 46%; font-size: 2.2rem; animation: pom 1s 0.9s cubic-bezier(0.2, 1.8, 0.4, 1) both; }
.pom-l { left: 4%; --tilt: -16deg; }
.pom-r { right: 4%; --tilt: 16deg; }

/* sender: the feeling flies off the screen */
.flight-burst { position: absolute; width: 14rem; aspect-ratio: 1; border-radius: 50%; background: radial-gradient(circle, rgb(255 255 255 / 0.65), transparent 65%); animation: fburst 1.6s ease-out both; }
.flight-mochi { position: absolute; width: 7.5rem; animation: fmochi 1.9s cubic-bezier(0.3, 1.4, 0.5, 1) both; transform-origin: bottom center; }
.flight-item { position: absolute; font-size: 3rem; filter: drop-shadow(0 6px 10px rgb(0 0 0 / 0.25)); animation: fitem 1.7s 0.35s cubic-bezier(0.5, 0, 0.75, 0) both; }
.flight-trail { position: absolute; font-size: 1.1rem; color: #ff6f9c; opacity: 0; animation: ftrail 1.4s calc(0.45s + var(--k) * 0.06s) ease-out both; }
.flight-chip { position: absolute; top: calc(50% + 5.5rem); border-radius: 9999px; background: rgb(255 255 255 / 0.92); padding: 0.45rem 1rem; font-size: 0.85rem; font-weight: 700; color: #b8446f; box-shadow: 0 10px 24px -10px rgb(0 0 0 / 0.35); animation: fchip 2.2s ease-out both; }

.stage-enter-active { transition: opacity 0.3s ease; }
.stage-leave-active { transition: opacity 0.25s ease, transform 0.25s ease; }
.stage-enter-from, .stage-leave-to { opacity: 0; }
.stage-leave-to { transform: scale(1.05); }

/* Base state is the finished picture, so with reduced motion the animations are simply switched off. */
.hug-l { animation: run-l 0.9s cubic-bezier(0.3, 1.2, 0.5, 1) both, squeeze 1.1s 1s ease-in-out infinite; transform-origin: bottom center; }
.hug-r { animation: run-r 0.9s cubic-bezier(0.3, 1.2, 0.5, 1) both, squeeze 1.1s 1s ease-in-out infinite; transform-origin: bottom center; }
.kiss-l { animation: lean-l 1s cubic-bezier(0.3, 1.4, 0.5, 1) both; transform-origin: bottom center; }
.kiss-r { animation: lean-r 1s 0.1s cubic-bezier(0.3, 1.4, 0.5, 1) both; transform-origin: bottom center; }
.lips { position: absolute; left: 50%; top: 22%; width: 26%; transform: translateX(-50%); filter: drop-shadow(0 6px 10px rgb(120 0 40 / 0.35)); animation: stamp 0.9s 0.7s cubic-bezier(0.2, 1.8, 0.4, 1) both; }
.ring { position: absolute; left: 50%; top: 19%; width: 26%; aspect-ratio: 1; margin-left: -13%; border: 3px solid #ffffffcc; border-radius: 50%; opacity: 0; animation: ring 1.6s 1.05s ease-out infinite; }
.ring-b { animation-delay: 1.6s; }
.sway { position: relative; animation: sway 3.4s ease-in-out infinite; transform-origin: bottom center; }
.pillow { position: absolute; left: 50%; top: 52%; font-size: 3rem; transform: translateX(-50%); filter: drop-shadow(0 4px 6px rgb(0 0 0 / 0.25)); animation: thump 1.2s ease-in-out infinite; }
.moon { position: absolute; right: 8%; top: 7%; width: 5.5rem; filter: drop-shadow(0 0 18px #fff6c9aa); animation: drop 1s 0.2s ease-out both; }
.twinkle { position: absolute; font-size: 0.9rem; opacity: 0.9; animation: twinkle 2s var(--delay) ease-in-out infinite; }
.rise { position: absolute; bottom: 56%; font-size: 1.4rem; opacity: 0; animation: rise 4.4s var(--delay) ease-out infinite; }
.sun { position: absolute; left: 50%; top: 2%; width: 78%; transform: translateX(-50%); animation: sunrise 1.2s ease-out both; }
.sun-rays { transform-origin: center; animation: spin 14s linear infinite; }
.jump { position: relative; animation: jump 0.7s ease-in-out infinite; transform-origin: bottom center; }
.burst { position: absolute; left: 50%; top: 42%; font-size: var(--size); opacity: 0; animation: burst 2.4s var(--delay) ease-out infinite; }
.burst.late { animation-delay: calc(var(--delay) + 0.6s); }
.confetti { position: absolute; top: -6%; width: 10px; height: 16px; border-radius: 3px; animation: fall var(--dur) var(--delay) linear infinite; }

@keyframes pop { from { transform: scale(0.4) translateY(14px); opacity: 0; } }
@keyframes letter { from { transform: translateY(24px) scale(0.3) rotate(-12deg); opacity: 0; } }
@keyframes glow { to { transform: translate(18vmax, 14vmax) scale(1.2); } }
@keyframes spark { 0%, 100% { opacity: 0; transform: scale(0.4) rotate(0); } 50% { opacity: 0.95; transform: scale(1.2) rotate(90deg); } }
@keyframes hop { to { transform: translateY(-14px) scale(0.96, 1.05); } }
@keyframes bigheart { 0%, 100% { transform: translateX(-50%) scale(0.92); } 30% { transform: translateX(-50%) scale(1.06); } 45% { transform: translateX(-50%) scale(0.98); } 60% { transform: translateX(-50%) scale(1.04); } }
@keyframes blushpop { from { transform: translate(-50%, 20px) scale(0); opacity: 0; } to { transform: translate(-50%, 0) scale(1); } }
@keyframes shy { 0%, 100% { transform: rotate(0); } 40% { transform: rotate(-5deg) translateX(-3px); } 60% { transform: rotate(3deg); } }
@keyframes muah { from { transform: translateX(-50%) scale(0) rotate(-20deg); opacity: 0; } to { transform: translateX(-50%) scale(1) rotate(-6deg); } }
@keyframes print { to { opacity: 0.9; transform: rotate(var(--rot)) scale(1); } }
@keyframes shoot { 0% { opacity: 0; transform: translate(0, 0) rotate(18deg); } 8% { opacity: 1; } 40%, 100% { opacity: 0; transform: translate(120vw, 38vw) rotate(18deg); } }
@keyframes float { 0%, 100% { transform: translateY(0); } 50% { transform: translateY(-6px); } }
@keyframes beat { 0%, 100% { transform: scale(1); } 50% { transform: scale(1.2); } }
@keyframes zz { 0% { opacity: 0; transform: translate(0, 0) scale(0.6); } 25% { opacity: 1; } 100% { opacity: 0; transform: translate(-30px, -60px) scale(1.3); } }
@keyframes firework { 0% { opacity: 1; transform: rotate(var(--a)) translateY(0) scale(1); } 70% { opacity: 1; } 100% { opacity: 0; transform: rotate(var(--a)) translateY(-64px) scale(0.4); } }
@keyframes pom { from { transform: scale(0) rotate(0); opacity: 0; } to { transform: scale(1) rotate(var(--tilt)); } }
@keyframes fburst { 0% { transform: scale(0.2); opacity: 0; } 25% { opacity: 1; } 100% { transform: scale(1.5); opacity: 0; } }
@keyframes fmochi { 0% { transform: translateY(40px) scale(0.4); opacity: 0; } 20% { transform: translateY(0) scale(1.08, 0.92); opacity: 1; } 35% { transform: translateY(-16px) scale(0.95, 1.06); } 50% { transform: translateY(0) scale(1); } 85% { opacity: 1; transform: none; } 100% { opacity: 0; transform: translateY(20px) scale(0.9); } }
@keyframes fitem { 0% { transform: translate(0, -20px) scale(0.5) rotate(0); opacity: 0; } 15% { opacity: 1; transform: translate(0, -50px) scale(1.2) rotate(-10deg); } 100% { transform: translate(38vw, -70vh) scale(0.6) rotate(40deg); opacity: 0; } }
@keyframes ftrail { 0% { opacity: 0; transform: translate(0, -50px) scale(0.4); } 30% { opacity: 1; } 100% { opacity: 0; transform: translate(calc(var(--k) * 4vw), calc(-60px - var(--k) * 7vh)) scale(1.1); } }
@keyframes fchip { 0% { opacity: 0; transform: translateY(12px) scale(0.8); } 15% { opacity: 1; transform: none; } 80% { opacity: 1; } 100% { opacity: 0; transform: translateY(-6px); } }
@keyframes run-l { from { transform: translateX(-130%) rotate(-12deg); } }
@keyframes run-r { from { transform: translateX(130%) rotate(12deg); } }
@keyframes squeeze { 0%, 100% { transform: scale(1); } 50% { transform: scale(1.06, 0.93); } }
@keyframes lean-l { from { transform: translateX(-90%) rotate(-18deg); } 60% { transform: translateX(8%) rotate(8deg); } }
@keyframes lean-r { from { transform: translateX(90%) rotate(18deg); } 60% { transform: translateX(-6%) rotate(-6deg); } }
@keyframes stamp { from { transform: translateX(-50%) scale(0) rotate(-40deg); opacity: 0; } 60% { transform: translateX(-50%) scale(1.35) rotate(8deg); opacity: 1; } }
@keyframes ring { 0% { transform: scale(0.6); opacity: 0.9; } 100% { transform: scale(3.2); opacity: 0; } }
@keyframes sway { 0%, 100% { transform: rotate(-3deg); } 50% { transform: rotate(3deg) translateY(2px); } }
@keyframes thump { 0%, 100% { transform: translateX(-50%) scale(1); } 50% { transform: translateX(-50%) scale(1.14); } }
@keyframes drop { from { transform: translateY(-60px) rotate(-30deg); opacity: 0; } }
@keyframes twinkle { 0%, 100% { opacity: 0.15; transform: scale(0.7); } 50% { opacity: 1; transform: scale(1.3); } }
@keyframes rise { 0% { transform: translateY(0) scale(0.7); opacity: 0; } 15% { opacity: 0.95; } 100% { transform: translateY(-190px) scale(1.2); opacity: 0; } }
@keyframes sunrise { from { transform: translate(-50%, 70%) scale(0.7); opacity: 0; } }
@keyframes spin { to { transform: rotate(360deg); } }
@keyframes jump { 0%, 100% { transform: translateY(0) scale(1.04, 0.94); } 50% { transform: translateY(-26px) scale(0.97, 1.04); } }
@keyframes burst { 0% { transform: translate(-50%, 0) scale(0.2); opacity: 0; } 15% { opacity: 1; } 100% { transform: translate(calc(-50% + var(--dx)), var(--dy)) scale(1.15); opacity: 0; } }
@keyframes fall { from { transform: translateY(0) rotate(0); } to { transform: translateY(115vh) rotate(var(--rot)); } }

@media (prefers-reduced-motion: reduce) {
    .caption, .letter, .sub, .hug-l, .hug-r, .kiss-l, .kiss-r, .lips, .ring, .sway, .pillow, .moon, .twinkle, .rise, .sun, .sun-rays, .jump, .burst, .confetti,
    .glow, .spark, .hop, .bigheart, .blush-pop, .shy, .muah, .print, .shooting, .dream, .dream-cloud, .dream-heart, .zz, .firework i, .pom { animation: none; }
    .ring, .rise, .burst, .confetti, .spark, .shooting, .zz, .firework, .print { display: none; }
    .flight { display: none; }
}
</style>
