// LoveyNest promo site. Plain JS, no libraries. The page reads fully without it; JS adds the demo, clips, version text and music.
const APP_URL = 'https://loveynest.my.id';
const APK_URL = APP_URL + '/aplikasi/LoveyNest.apk';
const $ = (s, r = document) => r.querySelector(s);
const $$ = (s, r = document) => [...r.querySelectorAll(s)];

$$('[data-app]').forEach((a) => (a.href = APP_URL));
$$('[data-apk]').forEach((a) => (a.href = APK_URL));
$$('[data-year]').forEach((e) => (e.textContent = new Date().getFullYear()));

// Newest APK version, size and notes from the app server (quietly keeps the default text if it cannot be read).
(async () => {
    try {
        const ctl = new AbortController();
        const timer = setTimeout(() => ctl.abort(), 6000);
        const res = await fetch(APP_URL + '/aplikasi/versi', { headers: { Accept: 'application/json' }, cache: 'no-store', signal: ctl.signal });
        clearTimeout(timer);
        if (!res.ok) return;
        const info = await res.json();
        if (!info || !info.name) return;
        const mb = info.size ? ', ' + (info.size / 1048576).toFixed(1).replace('.', ',') + ' MB' : '';
        const text = 'Versi terbaru ' + info.name + mb;
        $$('[data-apk-version]').forEach((e) => (e.textContent = text));
        $$('[data-apk-notes]').forEach((e) => (e.textContent = info.notes || ''));
        if (info.sha256) $$('[data-apk-sha]').forEach((e) => { e.hidden = false; e.textContent = 'SHA-256: ' + info.sha256; });
        $$('[data-apk-meta]').forEach((e) => (e.textContent = text + '. Di iPhone, buka di Safari, ketuk Bagikan, lalu Tambahkan ke Layar Utama.'));
    } catch (e) { /* offline or blocked */ }
})();

// Demo: the same colour rule as the app (male = blue, female = pink). The one who taps is on the left.
(() => {
    const scene = $('[data-stage]');
    if (!scene) return;
    let who = 'girl';
    const other = () => (who === 'girl' ? 'boy' : 'girl');
    const CAP = { peluk: 'kirim pelukan!', cium: 'kirim ciuman!', kangen: 'kangen kamu', semangat: 'bilang: semangat!' };
    const FX = { peluk: ['💗', '🤗'], cium: ['💋', '💗'], kangen: ['🌙', '⭐'], semangat: ['✨', '🎉'] };
    const me = $('[data-hero-me]');
    const you = $('[data-hero-you]');
    $$('.who button').forEach((b) => b.addEventListener('click', () => {
        who = b.dataset.who;
        $$('.who button').forEach((x) => x.setAttribute('aria-checked', String(x === b)));
        if (me) { me.src = `img/ch/${who}-happy.svg`; me.alt = who === 'girl' ? 'Karakter pink' : 'Karakter biru'; }
        if (you) { you.src = `img/ch/${other()}-happy.svg`; you.alt = other() === 'girl' ? 'Karakter pink' : 'Karakter biru'; }
    }));
    const close = () => { scene.hidden = true; $('[data-fx]', scene).replaceChildren(); document.removeEventListener('keydown', onKey); };
    const onKey = (e) => e.key === 'Escape' && close();
    const open = (feel) => {
        scene.dataset.feel = feel;
        $('[data-c1]', scene).src = `img/ch/${feel}-kirim-${who}.svg`;
        $('[data-c2]', scene).src = `img/ch/${feel}-terima-${other()}.svg`;
        $('[data-cap]', scene).textContent = (who === 'girl' ? 'Ariana ' : 'Hansen ') + CAP[feel];
        const fx = $('[data-fx]', scene);
        fx.replaceChildren();
        for (let i = 0; i < 18; i++) {
            const s = document.createElement('i');
            s.textContent = FX[feel][i % 3 ? 0 : 1];
            s.style.cssText = `left:${Math.random() * 96}%;font-size:${18 + Math.random() * 26}px;animation-duration:${3.2 + Math.random() * 3}s;animation-delay:${Math.random() * 1.6}s`;
            fx.appendChild(s);
        }
        scene.hidden = false;
        if (navigator.userActivation?.hasBeenActive) navigator.vibrate?.([30, 50, 30]);
        document.addEventListener('keydown', onKey);
        $('[data-close]', scene).focus({ preventScroll: true });
    };
    $$('[data-feel]').forEach((b) => b.addEventListener('click', () => open(b.dataset.feel)));
    scene.addEventListener('click', close);
})();

// Chat: what you see vs what the server keeps
(() => {
    const box = $('[data-vs]');
    if (!box) return;
    $$('[data-tab]', box).forEach((t) => t.addEventListener('click', () => {
        $$('[data-tab]', box).forEach((x) => x.setAttribute('aria-selected', String(x === t)));
        $$('[data-pane]', box).forEach((p) => (p.hidden = p.dataset.pane !== t.dataset.tab));
    }));
})();

// Days-together counter
(() => {
    const input = $('#since');
    const out = $('[data-out]');
    if (!input || !out) return;
    input.max = new Date().toISOString().slice(0, 10);
    input.addEventListener('input', () => {
        const d = new Date(input.value + 'T00:00:00');
        if (isNaN(d)) return;
        const days = Math.floor((Date.now() - d.getTime()) / 86400000);
        if (days < 0) { out.textContent = 'Tanggalnya belum tiba.'; return; }
        const next = [100, 200, 365, 500, 730, 1000, 1500, 2000].find((m) => m > days);
        out.innerHTML = '<b>' + days.toLocaleString('id-ID') + '</b> hari bersama' + (next ? '. ' + (next - days).toLocaleString('id-ID') + ' hari lagi menuju ' + next.toLocaleString('id-ID') + ' hari.' : '. Luar biasa!');
    });
})();

// Live clips: real screen recordings, loaded only when the section is near the screen
(() => {
    const box = $('[data-live]');
    const video = $('[data-live-video]');
    if (!box || !video) return;
    const NOTE = { rasa: 'Ariana mengirim, Hansen menerima: peluk, cium, kangen, semangat.', telepon: 'Telepon masuk, diangkat, lalu dibisukan.', arena: 'Beranda Arena dan game Menara Kue.', nada: 'Konser Hewan: hafalkan urutan nada.', widget: 'Empat gaya widget: Kaca, Lucu, Kertas, Malam.', ai: 'Tanya AI seputar haid, jawabannya ringkas.' };
    let current = 'rasa';
    let started = false;
    const load = (name) => {
        current = name;
        video.poster = 'media/clip/' + name + '.jpg';
        video.src = 'media/clip/' + name + '.mp4';
        $('[data-live-note]').textContent = NOTE[name] || '';
        video.play().catch(() => {});
    };
    $$('[data-clip]', box).forEach((b) => b.addEventListener('click', () => {
        $$('[data-clip]', box).forEach((x) => x.setAttribute('aria-selected', String(x === b)));
        started = true;
        load(b.dataset.clip);
    }));
    if ('IntersectionObserver' in window) {
        new IntersectionObserver((list) => list.forEach((x) => {
            if (x.isIntersecting && !started) { started = true; load(current); }
            else if (!x.isIntersecting) video.pause();
            else video.play().catch(() => {});
        }), { threshold: 0.35 }).observe(video);
    }
})();

// Background music: only on a tap; pauses while the video plays
(() => {
    const btn = $('[data-music]');
    const bgm = $('#bgm');
    const video = $('#promo-video');
    if (!btn || !bgm) return;
    bgm.volume = 0.5;
    const set = (on) => {
        btn.setAttribute('aria-pressed', String(on));
        btn.setAttribute('aria-label', on ? 'Matikan musik latar' : 'Putar musik latar');
        if (on) { if (video && !video.paused) video.pause(); bgm.play().catch(() => btn.setAttribute('aria-pressed', 'false')); } else bgm.pause();
    };
    btn.addEventListener('click', () => set(btn.getAttribute('aria-pressed') !== 'true'));
    video?.addEventListener('play', () => { if (!bgm.paused) set(false); });
})();

// One look (light) and a fixed size: pinch zoom is blocked here, and double-tap zoom by `touch-action: manipulation` in the CSS.
['gesturestart', 'gesturechange', 'gestureend'].forEach((n) => document.addEventListener(n, (e) => e.preventDefault()));
document.addEventListener('touchmove', (e) => { if (e.touches.length > 1) e.preventDefault(); }, { passive: false });
