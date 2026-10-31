<script>
// Backgrounds of the profile card (Profil > kartu nama). `tone` says which text colour reads well on top.
export const CARDS = [
    { id: 'senja', tone: 'light' },
    { id: 'malam', tone: 'light' },
    { id: 'sakura', tone: 'dark' },
    { id: 'laut', tone: 'light' },
    { id: 'hutan', tone: 'light' },
    { id: 'emas', tone: 'light' },
    { id: 'lavender', tone: 'dark' },
    { id: 'marmer', tone: 'dark' },
    { id: 'galaksi', tone: 'light' },
    { id: 'awan', tone: 'dark' },
    { id: 'aurora', tone: 'light' },
    { id: 'sampanye', tone: 'light' },
    { id: 'permen', tone: 'dark' },
    { id: 'hologram', tone: 'dark' },
    { id: 'kembangapi', tone: 'light' },
];

export const cardTone = (id) => CARDS.find((c) => c.id === id)?.tone ?? null;
</script>

<script setup>
import { useId } from 'vue';

defineProps({ id: { type: String, default: null } });
const uid = useId();
</script>

<template>
    <svg v-if="id" class="card-art pointer-events-none absolute inset-0 size-full" viewBox="0 0 400 200" preserveAspectRatio="xMidYMid slice" aria-hidden="true">
        <defs>
            <linearGradient :id="`${uid}-g`" x1="0" y1="0" x2="0" y2="1">
                <template v-if="id === 'senja'"><stop offset="0" stop-color="#3b2a6b" /><stop offset=".45" stop-color="#c2527e" /><stop offset=".8" stop-color="#ff9a62" /><stop offset="1" stop-color="#ffd089" /></template>
                <template v-else-if="id === 'malam'"><stop offset="0" stop-color="#0b1233" /><stop offset="1" stop-color="#2b2f6e" /></template>
                <template v-else-if="id === 'sakura'"><stop offset="0" stop-color="#ffeef3" /><stop offset="1" stop-color="#ffd0df" /></template>
                <template v-else-if="id === 'laut'"><stop offset="0" stop-color="#0b4f6c" /><stop offset="1" stop-color="#1fa6a6" /></template>
                <template v-else-if="id === 'hutan'"><stop offset="0" stop-color="#16382d" /><stop offset="1" stop-color="#3f7a5a" /></template>
                <template v-else-if="id === 'emas'"><stop offset="0" stop-color="#1b1712" /><stop offset="1" stop-color="#3a2f1c" /></template>
                <template v-else-if="id === 'lavender'"><stop offset="0" stop-color="#efe7ff" /><stop offset="1" stop-color="#cdbcf5" /></template>
                <template v-else-if="id === 'marmer'"><stop offset="0" stop-color="#fbfbfa" /><stop offset="1" stop-color="#e3e1df" /></template>
                <template v-else-if="id === 'galaksi'"><stop offset="0" stop-color="#150b34" /><stop offset=".6" stop-color="#3a1b6b" /><stop offset="1" stop-color="#1b3a7a" /></template>
                <template v-else-if="id === 'aurora'"><stop offset="0" stop-color="#04122b" /><stop offset=".55" stop-color="#0b2d4a" /><stop offset="1" stop-color="#103a3a" /></template>
                <template v-else-if="id === 'sampanye'"><stop offset="0" stop-color="#2a1f14" /><stop offset=".5" stop-color="#5a4224" /><stop offset="1" stop-color="#2a1f14" /></template>
                <template v-else-if="id === 'permen'"><stop offset="0" stop-color="#fff0f6" /><stop offset="1" stop-color="#e8f4ff" /></template>
                <template v-else-if="id === 'hologram'"><stop offset="0" stop-color="#f3e8ff" /><stop offset=".35" stop-color="#d8f3ff" /><stop offset=".7" stop-color="#ffe3f1" /><stop offset="1" stop-color="#fff7d6" /></template>
                <template v-else-if="id === 'kembangapi'"><stop offset="0" stop-color="#0a0820" /><stop offset=".7" stop-color="#1c1240" /><stop offset="1" stop-color="#3a1a4a" /></template>
                <template v-else><stop offset="0" stop-color="#cfe6ff" /><stop offset="1" stop-color="#ffd9ec" /></template>
            </linearGradient>
            <linearGradient :id="`${uid}-gold`" x1="0" y1="0" x2="1" y2="1"><stop offset="0" stop-color="#f6e3a1" /><stop offset=".5" stop-color="#c9a24a" /><stop offset="1" stop-color="#f1d98a" /></linearGradient>
            <radialGradient :id="`${uid}-neb`" cx=".7" cy=".3" r=".7"><stop offset="0" stop-color="#ff6fb7" stop-opacity=".55" /><stop offset="1" stop-color="#ff6fb7" stop-opacity="0" /></radialGradient>
            <radialGradient :id="`${uid}-neb2`" cx=".2" cy=".8" r=".6"><stop offset="0" stop-color="#4fb4ff" stop-opacity=".45" /><stop offset="1" stop-color="#4fb4ff" stop-opacity="0" /></radialGradient>
            <filter :id="`${uid}-bl`" x="-30%" y="-30%" width="160%" height="160%"><feGaussianBlur stdDeviation="14" /></filter>
            <filter :id="`${uid}-bl2`" x="-30%" y="-60%" width="160%" height="220%"><feGaussianBlur stdDeviation="6" /></filter>
            <linearGradient :id="`${uid}-aur`" x1="0" y1="0" x2="1" y2="0"><stop offset="0" stop-color="#3dffb0" stop-opacity="0" /><stop offset=".3" stop-color="#3dffb0" /><stop offset=".65" stop-color="#4fb4ff" /><stop offset="1" stop-color="#b56bff" stop-opacity="0" /></linearGradient>
            <linearGradient :id="`${uid}-holo`" x1="0" y1="0" x2="1" y2="1"><stop offset="0" stop-color="#ff9ad5" /><stop offset=".25" stop-color="#a6b8ff" /><stop offset=".5" stop-color="#8ff0e4" /><stop offset=".75" stop-color="#ffe38a" /><stop offset="1" stop-color="#ff9ad5" /></linearGradient>
            <pattern :id="`${uid}-stripe`" width="28" height="28" patternUnits="userSpaceOnUse" patternTransform="rotate(35)"><rect width="14" height="28" fill="#ffd6e7" opacity=".55" /></pattern>
        </defs>
        <rect width="400" height="200" :fill="`url(#${uid}-g)`" />

        <!-- Senja -->
        <g v-if="id === 'senja'">
            <circle cx="290" cy="138" r="46" fill="#fff3c9" opacity=".9" />
            <circle cx="290" cy="138" r="70" fill="#ffd089" opacity=".35" :filter="`url(#${uid}-bl)`" />
            <path d="M0 160 Q70 118 150 150 T300 142 T400 150 V200 H0Z" fill="#6a2f66" opacity=".75" />
            <path d="M0 180 Q90 150 190 175 T400 168 V200 H0Z" fill="#3b1f55" />
            <path class="drift" d="M40 60 q14 -10 30 0 q16 -8 28 2 q-8 8 -26 6 q-16 6 -32 -8z" fill="#fff" opacity=".35" />
        </g>

        <!-- Malam -->
        <g v-else-if="id === 'malam'">
            <circle cx="320" cy="56" r="26" fill="#fff4cf" /><circle cx="332" cy="48" r="24" :fill="`url(#${uid}-g)`" />
            <circle cx="320" cy="56" r="44" fill="#fff4cf" opacity=".18" :filter="`url(#${uid}-bl)`" />
            <g fill="#fff">
                <circle class="tw" cx="40" cy="40" r="1.8" /><circle class="tw d1" cx="95" cy="90" r="1.3" /><circle class="tw d2" cx="150" cy="30" r="2" /><circle class="tw d3" cx="210" cy="70" r="1.4" />
                <circle class="tw d2" cx="250" cy="24" r="1.6" /><circle class="tw" cx="60" cy="140" r="1.4" /><circle class="tw d1" cx="180" cy="130" r="1.8" /><circle class="tw d3" cx="370" cy="130" r="1.5" /><circle class="tw d2" cx="30" cy="95" r="1.1" />
            </g>
            <path d="M0 170 Q100 140 200 168 T400 160 V200 H0Z" fill="#0a0f2a" opacity=".85" />
        </g>

        <!-- Sakura -->
        <g v-else-if="id === 'sakura'">
            <path d="M330 -10 Q300 40 250 60 M360 10 Q330 60 290 78" stroke="#a6667a" stroke-width="5" fill="none" stroke-linecap="round" opacity=".7" />
            <g fill="#ff9fbd">
                <circle cx="300" cy="42" r="9" opacity=".85" /><circle cx="330" cy="28" r="7" opacity=".8" /><circle cx="268" cy="58" r="8" opacity=".8" /><circle cx="350" cy="52" r="8" opacity=".75" /><circle cx="318" cy="72" r="6" opacity=".7" />
            </g>
            <g fill="#fff" opacity=".9"><circle cx="300" cy="42" r="3" /><circle cx="330" cy="28" r="2.4" /><circle cx="268" cy="58" r="2.6" /><circle cx="350" cy="52" r="2.6" /></g>
            <g class="fall" fill="#ffb3cb"><ellipse cx="220" cy="90" rx="5" ry="3" transform="rotate(30 220 90)" /><ellipse cx="260" cy="150" rx="5" ry="3" transform="rotate(-20 260 150)" /><ellipse cx="350" cy="130" rx="4" ry="2.6" transform="rotate(50 350 130)" /><ellipse cx="180" cy="170" rx="4" ry="2.6" transform="rotate(10 180 170)" /></g>
        </g>

        <!-- Laut -->
        <g v-else-if="id === 'laut'">
            <circle cx="320" cy="48" r="22" fill="#fff6d1" opacity=".85" />
            <path class="wave" d="M-20 120 Q30 100 80 120 T180 120 T280 120 T380 120 T480 120 V200 H-20Z" fill="#fff" opacity=".18" />
            <path class="wave w2" d="M-20 140 Q30 122 80 140 T180 140 T280 140 T380 140 T480 140 V200 H-20Z" fill="#fff" opacity=".22" />
            <path class="wave w3" d="M-20 164 Q30 148 80 164 T180 164 T280 164 T380 164 T480 164 V200 H-20Z" fill="#06384f" opacity=".5" />
        </g>

        <!-- Hutan -->
        <g v-else-if="id === 'hutan'">
            <circle cx="320" cy="56" r="60" fill="#d7ffe3" opacity=".22" :filter="`url(#${uid}-bl)`" />
            <g fill="#0f2a22" opacity=".8">
                <path d="M20 200 L45 110 L70 200Z" /><path d="M60 200 L90 90 L120 200Z" /><path d="M300 200 L330 100 L360 200Z" /><path d="M350 200 L378 120 L406 200Z" />
            </g>
            <g fill="#1d4a3a" opacity=".9"><path d="M120 200 L148 130 L176 200Z" /><path d="M255 200 L283 135 L311 200Z" /></g>
            <path class="drift" d="M0 150 Q100 130 200 150 T400 146 V200 H0Z" fill="#cfe9d8" opacity=".16" />
        </g>

        <!-- Emas -->
        <g v-else-if="id === 'emas'" fill="none" :stroke="`url(#${uid}-gold)`" stroke-width="1.4" opacity=".85">
            <path d="M10 10 H390 V190 H10Z" opacity=".6" /><path d="M18 18 H382 V182 H18Z" opacity=".4" />
            <path d="M200 -10 L260 60 L200 130 L140 60Z" opacity=".5" />
            <path d="M330 -20 Q300 60 380 100 M330 220 Q300 140 380 100" opacity=".55" />
            <circle cx="340" cy="100" r="34" opacity=".5" /><circle cx="340" cy="100" r="22" opacity=".4" />
            <path d="M0 100 H110" opacity=".5" /><circle cx="110" cy="100" r="3" :fill="`url(#${uid}-gold)`" />
        </g>

        <!-- Lavender -->
        <g v-else-if="id === 'lavender'">
            <circle cx="330" cy="40" r="70" fill="#b69cf0" opacity=".45" :filter="`url(#${uid}-bl)`" />
            <circle cx="60" cy="170" r="80" fill="#ff9ecb" opacity=".3" :filter="`url(#${uid}-bl)`" />
            <g fill="#fff" opacity=".7"><circle cx="40" cy="40" r="3" /><circle cx="130" cy="30" r="2" /><circle cx="250" cy="70" r="2.6" /><circle cx="370" cy="150" r="3" /><circle cx="300" cy="180" r="2" /></g>
            <g stroke="#9a7fe0" stroke-width="2" fill="none" opacity=".5" stroke-linecap="round"><path d="M360 200 Q352 150 366 100" /><path d="M376 200 Q372 160 388 120" /><path d="M344 200 Q332 160 340 130" /></g>
        </g>

        <!-- Marmer -->
        <g v-else-if="id === 'marmer'" fill="none" stroke-linecap="round">
            <path d="M-10 60 Q80 20 150 90 T300 70 T420 120" stroke="#9c9a98" stroke-width="1.6" opacity=".55" />
            <path d="M20 200 Q100 130 190 150 T330 120 T420 60" stroke="#b7b4b1" stroke-width="1.2" opacity=".6" />
            <path d="M140 -10 Q170 60 130 110 T190 210" stroke="#c9a24a" stroke-width="1.2" opacity=".55" />
            <path d="M250 -10 Q230 50 280 90" stroke="#8d8a87" stroke-width="1" opacity=".4" />
        </g>

        <!-- Galaksi -->
        <g v-else-if="id === 'galaksi'">
            <rect width="400" height="200" :fill="`url(#${uid}-neb)`" /><rect width="400" height="200" :fill="`url(#${uid}-neb2)`" />
            <g fill="#fff">
                <circle class="tw" cx="30" cy="30" r="1.6" /><circle class="tw d1" cx="80" cy="120" r="1.2" /><circle class="tw d2" cx="140" cy="50" r="1.8" /><circle class="tw d3" cx="200" cy="150" r="1.2" /><circle class="tw" cx="260" cy="40" r="1.4" />
                <circle class="tw d2" cx="320" cy="110" r="1.8" /><circle class="tw d1" cx="370" cy="40" r="1.3" /><circle class="tw d3" cx="110" cy="180" r="1.4" /><circle class="tw" cx="350" cy="170" r="1.2" /><circle class="tw d2" cx="40" cy="160" r="1.5" />
            </g>
            <ellipse cx="300" cy="110" rx="90" ry="14" fill="none" stroke="#fff" stroke-opacity=".25" transform="rotate(-18 300 110)" />
            <circle cx="300" cy="110" r="10" fill="#ffd9f2" opacity=".8" /><circle cx="300" cy="110" r="24" fill="#ff8fd0" opacity=".25" :filter="`url(#${uid}-bl)`" />
        </g>

        <!-- Aurora: green and blue curtains of light ripple over a night sky with a mountain line -->
        <g v-else-if="id === 'aurora'">
            <g fill="#fff"><circle class="tw" cx="40" cy="30" r="1.3" /><circle class="tw d1" cx="120" cy="18" r="1.1" /><circle class="tw d2" cx="210" cy="36" r="1.4" /><circle class="tw d3" cx="300" cy="22" r="1.2" /><circle class="tw d1" cx="370" cy="44" r="1.3" /><circle class="tw d2" cx="80" cy="64" r="1" /></g>
            <g :filter="`url(#${uid}-bl2)`" opacity=".85">
                <path class="aur" d="M-20 70 C60 20 120 110 200 60 S330 20 420 70 L420 110 C330 70 270 140 200 105 S60 70 -20 120Z" :fill="`url(#${uid}-aur)`" />
                <path class="aur a2" d="M-20 95 C80 60 140 130 220 90 S350 60 420 100 L420 125 C340 100 280 150 210 125 S70 100 -20 135Z" :fill="`url(#${uid}-aur)`" opacity=".6" />
            </g>
            <path d="M0 200 L0 160 L40 140 L70 156 L110 128 L150 152 L190 136 L230 158 L270 132 L320 154 L360 140 L400 158 L400 200Z" fill="#06121f" />
        </g>

        <!-- Sampanye: dark gold with champagne bubbles rising and an art deco fan -->
        <g v-else-if="id === 'sampanye'">
            <g fill="none" :stroke="`url(#${uid}-gold)`" stroke-width="1.2" opacity=".75">
                <path d="M200 200 L120 120 M200 200 L150 105 M200 200 L200 100 M200 200 L250 105 M200 200 L280 120" />
                <path d="M120 120 A113 113 0 0 1 280 120" /><path d="M140 140 A85 85 0 0 1 260 140" /><path d="M160 160 A57 57 0 0 1 240 160" />
                <path d="M12 12 H388 V188 H12Z" opacity=".55" />
            </g>
            <g :fill="`url(#${uid}-gold)`">
                <circle class="bub" cx="40" cy="190" r="3" /><circle class="bub b1" cx="80" cy="196" r="2" /><circle class="bub b2" cx="330" cy="192" r="3.4" />
                <circle class="bub b3" cx="360" cy="198" r="2.2" /><circle class="bub b1" cx="300" cy="196" r="1.8" /><circle class="bub b2" cx="60" cy="198" r="2.6" />
            </g>
            <circle cx="320" cy="50" r="40" fill="#f6e3a1" opacity=".16" :filter="`url(#${uid}-bl)`" />
        </g>

        <!-- Permen kapas: pastel stripes, floating candy hearts and a lollipop -->
        <g v-else-if="id === 'permen'">
            <rect width="400" height="200" :fill="`url(#${uid}-stripe)`" />
            <g class="bob"><path d="M330 70 C330 62 340 58 346 64 C352 58 362 62 362 70 C362 80 346 90 346 90 C346 90 330 80 330 70Z" fill="#ff8fb8" /></g>
            <g class="bob d1"><path d="M60 140 C60 134 67 131 71 135 C75 131 82 134 82 140 C82 147 71 154 71 154 C71 154 60 147 60 140Z" fill="#b9a6ff" /></g>
            <g class="bob d2"><path d="M250 30 C250 25 256 22 259 26 C262 22 268 25 268 30 C268 36 259 41 259 41 C259 41 250 36 250 30Z" fill="#8fd3ff" /></g>
            <g transform="translate(300 150)"><path d="M0 0 L0 50" stroke="#fff" stroke-width="5" stroke-linecap="round" /><circle r="24" fill="#ffd6e7" stroke="#ff8fb8" stroke-width="3" /><path class="spinslow" d="M0 0 m-16 0 a16 16 0 1 1 32 0 a10 10 0 1 1 -20 0 a5 5 0 1 1 10 0" fill="none" stroke="#ff5c8a" stroke-width="3" stroke-linecap="round" /></g>
            <g fill="#fff"><circle class="tw" cx="30" cy="40" r="3" /><circle class="tw d2" cx="180" cy="170" r="2.4" /><circle class="tw d1" cx="220" cy="80" r="2" /></g>
        </g>

        <!-- Hologram: iridescent sheen sweeping over pearl white, with sparkles -->
        <g v-else-if="id === 'hologram'">
            <rect class="sheen" x="-200" width="800" height="200" :fill="`url(#${uid}-holo)`" opacity=".55" />
            <path d="M-20 160 Q100 110 220 150 T420 120" fill="none" stroke="#fff" stroke-width="18" opacity=".45" :filter="`url(#${uid}-bl2)`" />
            <g fill="#fff">
                <path class="tw" d="M320 40 l3 9 9 3 -9 3 -3 9 -3 -9 -9 -3 9 -3Z" /><path class="tw d2" d="M60 150 l2 6 6 2 -6 2 -2 6 -2 -6 -6 -2 6 -2Z" />
                <path class="tw d1" d="M250 150 l2 6 6 2 -6 2 -2 6 -2 -6 -6 -2 6 -2Z" /><path class="tw d3" d="M150 30 l2 6 6 2 -6 2 -2 6 -2 -6 -6 -2 6 -2Z" />
            </g>
        </g>

        <!-- Kembang api: fireworks bloom one after another over a city -->
        <g v-else-if="id === 'kembangapi'">
            <g v-for="(fw, i) in [{ x: 300, y: 60, c: '#ff8fd0' }, { x: 110, y: 50, c: '#ffd54a' }, { x: 210, y: 90, c: '#7ad7ff' }]" :key="i" class="fw" :class="`f${i}`" :style="{ transformOrigin: `${fw.x}px ${fw.y}px` }">
                <circle :cx="fw.x" :cy="fw.y" r="30" :fill="fw.c" opacity=".18" :filter="`url(#${uid}-bl)`" />
                <g :stroke="fw.c" stroke-width="2" stroke-linecap="round">
                    <path v-for="a in 12" :key="a" :d="`M${fw.x} ${fw.y} l${(Math.cos((a * Math.PI) / 6) * 26).toFixed(1)} ${(Math.sin((a * Math.PI) / 6) * 26).toFixed(1)}`" />
                </g>
                <circle :cx="fw.x" :cy="fw.y" r="3" fill="#fff" />
            </g>
            <path d="M0 200 V170 H30 V150 H55 V172 H80 V140 H110 V176 H140 V160 H170 V180 H200 V146 H232 V170 H260 V156 H290 V178 H320 V150 H350 V168 H400 V200Z" fill="#07061a" />
            <g fill="#ffd96b" opacity=".75"><rect x="88" y="150" width="4" height="4" /><rect x="96" y="160" width="4" height="4" /><rect x="210" y="156" width="4" height="4" /><rect x="330" y="160" width="4" height="4" /><rect x="40" y="160" width="4" height="4" /></g>
        </g>

        <!-- Awan pastel -->
        <g v-else>
            <g fill="#fff" opacity=".85">
                <path class="drift" d="M40 70 q10 -22 34 -14 q10 -16 32 -4 q22 -2 22 16 q0 12 -16 12 H56 q-18 0 -16 -10z" />
                <path class="drift d1" d="M250 120 q10 -22 34 -14 q10 -16 32 -4 q22 -2 22 16 q0 12 -16 12 H266 q-18 0 -16 -10z" />
                <path d="M300 40 q8 -16 26 -10 q8 -12 24 -3 q16 0 16 12 q0 9 -12 9 H312 q-14 0 -12 -8z" opacity=".7" />
            </g>
            <g fill="#fff"><circle class="tw" cx="200" cy="40" r="2" /><circle class="tw d2" cx="130" cy="150" r="1.6" /><circle class="tw d1" cx="360" cy="170" r="2" /></g>
        </g>
    </svg>
</template>

<style scoped>
.tw {
    animation: tw 2.8s ease-in-out infinite;
}
.d1 {
    animation-delay: 0.7s;
}
.d2 {
    animation-delay: 1.4s;
}
.d3 {
    animation-delay: 2.1s;
}
.drift {
    animation: drift 14s ease-in-out infinite alternate;
}
.fall {
    animation: fall 9s ease-in-out infinite alternate;
}
.wave {
    animation: wave 7s ease-in-out infinite alternate;
}
.w2 {
    animation-duration: 9s;
    animation-direction: alternate-reverse;
}
.w3 {
    animation-duration: 11s;
}
.aur {
    animation: aur 9s ease-in-out infinite alternate;
}
.a2 {
    animation-duration: 12s;
    animation-direction: alternate-reverse;
}
.bub {
    animation: bub 5s ease-in infinite;
}
.b1 {
    animation-delay: 1.3s;
}
.b2 {
    animation-delay: 2.6s;
}
.b3 {
    animation-delay: 3.6s;
}
.bob {
    animation: bob 3.4s ease-in-out infinite;
}
.spinslow {
    transform-box: fill-box;
    transform-origin: center;
    animation: spin 8s linear infinite;
}
.sheen {
    animation: sheen 7s ease-in-out infinite alternate;
}
.fw {
    animation: fw 3.6s ease-out infinite;
    opacity: 0;
}
.f1 {
    animation-delay: 1.2s;
}
.f2 {
    animation-delay: 2.4s;
}
@keyframes aur {
    to {
        transform: translateX(-30px) scaleY(1.15);
    }
}
@keyframes bub {
    0% {
        transform: translateY(0);
        opacity: 0;
    }
    15% {
        opacity: 0.9;
    }
    100% {
        transform: translateY(-190px);
        opacity: 0;
    }
}
@keyframes bob {
    0%,
    100% {
        transform: translateY(0);
    }
    50% {
        transform: translateY(-6px);
    }
}
@keyframes spin {
    to {
        transform: rotate(360deg);
    }
}
@keyframes sheen {
    to {
        transform: translateX(200px);
    }
}
@keyframes fw {
    0% {
        transform: scale(0.2);
        opacity: 0;
    }
    12% {
        opacity: 1;
    }
    60% {
        transform: scale(1);
        opacity: 0.9;
    }
    100% {
        transform: scale(1.15);
        opacity: 0;
    }
}
@keyframes tw {
    0%,
    100% {
        opacity: 0.25;
    }
    50% {
        opacity: 1;
    }
}
@keyframes drift {
    to {
        transform: translateX(18px);
    }
}
@keyframes fall {
    to {
        transform: translate(-10px, 12px);
    }
}
@keyframes wave {
    to {
        transform: translateX(-24px);
    }
}
@media (prefers-reduced-motion: reduce) {
    .card-art * {
        animation: none !important;
    }
}
</style>
