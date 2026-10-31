// Voice calls from the chat (see app/Http/Controllers/Chat/CallController.php).
// One shared state for the whole app, so the call keeps going while you move between menus. The phones connect
// directly (WebRTC); the server passes the connection notes. The caller makes the offer once the callee has answered.
import { reactive } from 'vue';
import { http } from '@/lib/http';

const DEFAULT_ICE = [{ urls: ['stun:stun.l.google.com:19302', 'stun:stun1.l.google.com:19302'] }];

export const call = reactive({
    id: null,
    status: 'idle', // idle | outgoing | incoming | connecting | active | ended
    role: null, // caller | callee
    reason: null, // why it ended
    seconds: 0,
    muted: false,
    speaker: true,
    minimized: false,
    error: '',
    needsTap: false, // the phone blocked the partner's voice until the next tap (iPhone)
    partner: null, // { name, avatarUrl, avatarFrame, gender }
});

let ice = DEFAULT_ICE;
let pc = null;
let stream = null;
let audio = null;
let cursor = 0;
let queued = [];
let remoteSet = false;
let watchTimer = 0;
let sigTimer = 0;
let tick = 0;
let offered = false;
let ring = null;
let polling = false;
let closeTimer = 0;
let graceTimer = 0;
let connectTimer = 0;
let restarts = 0;

/** The connection broke or never formed: the caller tries a fresh one (twice at most), the callee waits for it. */
function reconnect() {
    if (call.status === 'idle' || call.status === 'ended') return;
    if (call.role === 'caller' && restarts < 2) {
        restarts++;
        offered = false;
        makeOffer().catch(() => {});
    }
    armConnectTimer();
}

/** No voice after 30 s: end the call with a clear reason instead of "menyambungkan" forever. */
function armConnectTimer() {
    clearTimeout(connectTimer);
    connectTimer = setTimeout(() => {
        if (call.status === 'connecting' || (pc && pc.connectionState !== 'connected' && call.status !== 'idle' && call.status !== 'ended' && call.status !== 'outgoing' && call.status !== 'incoming')) {
            call.error = 'Telepon belum bisa tersambung lewat jaringan ini. Coba pakai Wi-Fi, lalu telepon lagi.';
            hangUp();
        }
    }, 30000);
}

/** iPhone: play the partner's voice after a tap when the phone held it back. */
export function resumeAudio() {
    if (!audio) return;
    audio.play().then(() => (call.needsTap = false)).catch(() => {});
}

export function configureCalls({ iceServers, partner }) {
    if (Array.isArray(iceServers) && iceServers.length) ice = iceServers;
    if (partner) call.partner = partner;
}

export const callSupported = () => typeof window !== 'undefined' && !!(window.RTCPeerConnection && navigator.mediaDevices?.getUserMedia);

// ---------------------------------------------------------------- sound: a soft ring, made on the fly (no files)
let ctx = null;
function tone(freqs, length, gap, repeat) {
    try {
        ctx ??= new (window.AudioContext || window.webkitAudioContext)();
        if (ctx.state === 'suspended') ctx.resume().catch(() => {});
        let t = ctx.currentTime + 0.05;
        for (let r = 0; r < repeat; r++) {
            for (const f of freqs) {
                const o = ctx.createOscillator();
                const g = ctx.createGain();
                o.type = 'sine';
                o.frequency.value = f;
                g.gain.setValueAtTime(0, t);
                g.gain.linearRampToValueAtTime(0.18, t + 0.03);
                g.gain.setValueAtTime(0.18, t + length - 0.05);
                g.gain.linearRampToValueAtTime(0, t + length);
                o.connect(g).connect(ctx.destination);
                o.start(t);
                o.stop(t + length + 0.02);
                t += length;
            }
            t += gap;
        }
    } catch {
        /* no sound is fine */
    }
}
function startRing(kind) {
    stopRing();
    const play = () => (kind === 'in' ? tone([880, 1175, 1568, 1175], 0.16, 0.9, 1) : tone([425], 1, 3, 1));
    play();
    ring = setInterval(play, kind === 'in' ? 1800 : 4000);
    if (kind === 'in' && navigator.userActivation?.hasBeenActive) navigator.vibrate?.([300, 200, 300]);
}
function stopRing() {
    clearInterval(ring);
    ring = null;
    if (navigator.userActivation?.hasBeenActive) navigator.vibrate?.(0);
}

// ---------------------------------------------------------------- WebRTC
async function microphone() {
    if (stream) return stream;
    stream = await navigator.mediaDevices.getUserMedia({ audio: { echoCancellation: true, noiseSuppression: true, autoGainControl: true }, video: false });
    stream.getAudioTracks().forEach((t) => (t.enabled = !call.muted));
    return stream;
}

function ensureAudio() {
    if (audio) return audio;
    audio = Object.assign(document.createElement('audio'), { autoplay: true, playsInline: true });
    audio.style.display = 'none';
    document.body.appendChild(audio);
    return audio;
}

const send = (type, data) => http.post(`/panggilan/${call.id}/sinyal`, { type, data: data ? JSON.stringify(data) : null }).catch(() => {});

function makePeer() {
    closePeer();
    pc = new RTCPeerConnection({ iceServers: ice });
    stream.getTracks().forEach((track) => pc.addTrack(track, stream));
    pc.onicecandidate = (e) => e.candidate && send('ice', e.candidate);
    pc.ontrack = (e) => {
        const el = ensureAudio();
        el.srcObject = e.streams[0] ?? new MediaStream([e.track]);
        el.play().then(() => (call.needsTap = false)).catch(() => (call.needsTap = true));
    };
    pc.onconnectionstatechange = () => {
        if (!pc) return;
        const st = pc.connectionState;
        if (st === 'connected') {
            call.status = 'active';
            clearTimeout(graceTimer);
            clearTimeout(connectTimer);
            restarts = 0;
            stopRing();
        } else if (st === 'disconnected') {
            // Mobile networks drop for a moment all the time: give it a few seconds before starting over.
            clearTimeout(graceTimer);
            graceTimer = setTimeout(() => pc && pc.connectionState !== 'connected' && reconnect(), 6000);
        } else if (st === 'failed') {
            reconnect();
        }
    };
    return pc;
}

function closePeer() {
    if (pc) {
        pc.onicecandidate = pc.ontrack = pc.onconnectionstatechange = null;
        try {
            pc.close();
        } catch {
            /* closed */
        }
    }
    pc = null;
    remoteSet = false;
    queued = [];
}

async function makeOffer() {
    if (offered || !stream) return;
    offered = true;
    const p = makePeer();
    const offer = await p.createOffer({ offerToReceiveAudio: true });
    await p.setLocalDescription(offer);
    send('offer', p.localDescription);
}

async function flushIce() {
    remoteSet = true;
    for (const c of queued) await pc?.addIceCandidate(c).catch(() => {});
    queued = [];
}

async function handle(item) {
    const data = item.data ? JSON.parse(item.data) : null;
    if (item.type === 'offer' && call.role === 'callee') {
        await microphone();
        const p = makePeer();
        await p.setRemoteDescription(data);
        await flushIce();
        const answer = await p.createAnswer();
        await p.setLocalDescription(answer);
        send('answer', p.localDescription);
    } else if (item.type === 'answer' && call.role === 'caller' && pc && !remoteSet) {
        await pc.setRemoteDescription(data);
        await flushIce();
    } else if (item.type === 'ice' && pc) {
        if (remoteSet) await pc.addIceCandidate(data).catch(() => {});
        else queued.push(data);
    } else if (item.type === 'restart' && call.role === 'caller') {
        offered = false;
        await makeOffer();
    } else if (item.type === 'bye') {
        finishLocal('hangup');
    }
}

// ---------------------------------------------------------------- server state
function apply(state) {
    if (!state) {
        if (call.status !== 'idle' && call.status !== 'ended') finishLocal('hangup');
        return;
    }
    if (call.id && state.id !== call.id && call.status !== 'idle' && call.status !== 'ended') return;
    const fresh = call.id !== state.id;
    call.id = state.id;
    call.role = state.role;
    if (state.status === 'ringing') {
        if (state.role === 'callee' && call.status !== 'incoming') {
            call.status = 'incoming';
            call.minimized = false;
            startRing('in');
        } else if (state.role === 'caller' && call.status !== 'outgoing') {
            call.status = 'outgoing';
            startRing('out');
        }
    } else if (state.status === 'active') {
        if (call.status === 'outgoing' || call.status === 'incoming') {
            call.status = 'connecting';
            armConnectTimer();
        }
        stopRing();
        if (!tick) startClock(state.elapsed);
        if (state.role === 'caller') makeOffer().catch(() => {});
    } else if (state.status === 'ended') {
        if (!fresh || call.status !== 'idle') finishLocal(state.reason);
    }
    if (call.status !== 'idle' && call.status !== 'ended') startSignals();
}

function startClock(from = 0) {
    call.seconds = from;
    clearInterval(tick);
    tick = setInterval(() => call.seconds++, 1000);
}

function startSignals() {
    if (sigTimer) return;
    sigTimer = setInterval(pollSignals, 900);
}

async function pollSignals() {
    if (polling || !call.id) return;
    polling = true;
    try {
        const res = await http.get(`/panggilan/${call.id}/sinyal?after=${cursor}`);
        cursor = res.last;
        apply(res.call);
        for (const item of res.items) await handle(item).catch(() => {});
    } catch (e) {
        if (e?.status === 404) finishLocal(call.status === 'outgoing' ? 'missed' : 'hangup');
    } finally {
        polling = false;
    }
}

async function check() {
    if (document.visibilityState !== 'visible' || !call.partner) return;
    if (call.status !== 'idle' && call.status !== 'ended') return;
    try {
        const res = await http.get('/panggilan');
        const c = res.call;
        if (c && c.status === 'ringing' && c.role === 'callee') apply(c);
        // The page was reloaded (or the app reopened) in the middle of our own call: join it again.
        else if (c && (c.status === 'active' || (c.status === 'ringing' && c.role === 'caller'))) await rejoin(c);
    } catch {
        /* next round */
    }
}

async function rejoin(state) {
    Object.assign(call, { id: state.id, role: state.role, status: state.status === 'active' ? 'connecting' : 'outgoing', reason: null, minimized: false, error: '' });
    if (state.status === 'active') armConnectTimer();
    try {
        await microphone();
    } catch {
        call.error = 'Izinkan mikrofon untuk menyambung lagi panggilannya.';
        return hangUp();
    }
    try {
        // skip everything that was sent before this page existed (an old offer/answer would break the new connection)
        cursor = (await http.get(`/panggilan/${state.id}/sinyal?after=0`)).last ?? 0;
    } catch {
        cursor = 0;
    }
    if (state.status === 'active') {
        startClock(state.elapsed);
        if (call.role === 'caller') {
            offered = false;
            makeOffer().catch(() => {});
        } else send('restart'); // the caller leads: it sends a fresh offer
    } else startRing('out');
    startSignals();
}

/** Starts listening for incoming calls (every 4 s while the app is in view). */
export function watchCalls() {
    if (watchTimer || typeof window === 'undefined') return;
    check();
    watchTimer = setInterval(check, 4000);
    document.addEventListener('visibilitychange', check);
}

function release() {
    stopRing();
    clearTimeout(graceTimer);
    clearTimeout(connectTimer);
    restarts = 0;
    call.needsTap = false;
    clearInterval(sigTimer);
    sigTimer = 0;
    clearInterval(tick);
    tick = 0;
    closePeer();
    stream?.getTracks().forEach((t) => t.stop());
    stream = null;
    if (audio) audio.srcObject = null;
    offered = false;
    cursor = 0;
}

function finishLocal(reason) {
    if (call.status === 'idle' || call.status === 'ended') return;
    release();
    call.status = 'ended';
    call.reason = reason ?? 'hangup';
    clearTimeout(closeTimer);
    closeTimer = setTimeout(() => {
        if (call.status === 'ended') Object.assign(call, { status: 'idle', id: null, role: null, reason: null, seconds: 0, minimized: false, error: '' });
    }, 2600);
}

// ---------------------------------------------------------------- actions
export async function startCall() {
    if (!callSupported()) {
        call.error = 'Perangkat ini belum mendukung panggilan.';
        return;
    }
    if (call.status !== 'idle' && call.status !== 'ended') {
        call.minimized = false;
        return;
    }
    clearTimeout(closeTimer);
    Object.assign(call, { status: 'outgoing', role: 'caller', reason: null, seconds: 0, muted: false, minimized: false, error: '' });
    ensureAudio().play().catch(() => {}); // inside the tap, so the phone allows sound later
    try {
        await microphone();
    } catch {
        call.error = 'Izin mikrofon ditolak. Izinkan mikrofon untuk LoveyNest lalu coba lagi.';
        call.status = 'idle';
        return;
    }
    try {
        const res = await http.post('/panggilan', {});
        apply(res.call);
        startRing('out');
    } catch (e) {
        release();
        call.status = 'idle';
        if (e?.status === 409 && e.data?.call) apply(e.data.call);
        else call.error = 'Panggilan belum bisa dimulai. Coba lagi.';
    }
}

export async function acceptCall() {
    if (call.status !== 'incoming') return;
    stopRing();
    ensureAudio().play().catch(() => {});
    try {
        await microphone();
    } catch {
        call.error = 'Izin mikrofon ditolak. Izinkan mikrofon untuk LoveyNest lalu coba lagi.';
        return declineCall();
    }
    call.status = 'connecting';
    armConnectTimer();
    try {
        apply((await http.post(`/panggilan/${call.id}/terima`, {})).call);
    } catch {
        finishLocal('hangup');
    }
}

export async function declineCall() {
    const id = call.id;
    finishLocal('declined');
    if (id) http.post(`/panggilan/${id}/tolak`, {}).catch(() => {});
}

export async function hangUp() {
    const id = call.id;
    const reason = call.status === 'outgoing' ? 'cancelled' : 'hangup';
    if (id && pc) send('bye');
    finishLocal(reason);
    if (id) http.post(`/panggilan/${id}/akhiri`, {}).catch(() => {});
}

export function toggleMute() {
    call.muted = !call.muted;
    stream?.getAudioTracks().forEach((t) => (t.enabled = !call.muted));
}

/** Phones that allow choosing the output (Chrome on Android): loudspeaker or the quieter default. */
export async function toggleSpeaker() {
    call.speaker = !call.speaker;
    try {
        if (audio && typeof audio.setSinkId === 'function') {
            const devices = await navigator.mediaDevices.enumerateDevices();
            const outs = devices.filter((d) => d.kind === 'audiooutput');
            const target = call.speaker ? outs.find((d) => /speaker|pengeras/i.test(d.label)) ?? outs[0] : outs.find((d) => /earpiece|receiver/i.test(d.label)) ?? outs[0];
            if (target) await audio.setSinkId(target.deviceId);
        }
        if (audio) audio.volume = call.speaker ? 1 : 0.45;
    } catch {
        /* not supported here: volume only */
    }
}

export const clock = (s) => `${String(Math.floor(s / 60)).padStart(2, '0')}:${String(s % 60).padStart(2, '0')}`;
