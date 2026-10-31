import { onBeforeUnmount, ref } from 'vue';
import { usePage } from '@inertiajs/vue3';
import { http } from '@/lib/http';

/**
 * Talking while you play. The two browsers connect directly (WebRTC); the server only passes the small
 * connection notes. Whoever has the lower user id makes the offer, so two people switching on at the same moment
 * never collide. Switching off closes the microphone for real.
 *
 * status: off | asking (waiting for the microphone permission) | waiting (on, partner not on yet) | connecting | on | failed (no route between the phones) | denied | unsupported
 * needsTap: the phone (iPhone) held the partner's voice back until the next tap.
 */
const ICE = { iceServers: [{ urls: ['stun:stun.l.google.com:19302', 'stun:stun1.l.google.com:19302'] }] };

export function useArenaVoice(sessionId, meId, partnerId) {
    const servers = usePage().props.app?.ice;
    const iceConfig = Array.isArray(servers) && servers.length ? { iceServers: servers } : ICE;
    const supported = typeof window !== 'undefined' && !!(window.RTCPeerConnection && navigator.mediaDevices?.getUserMedia);
    const status = ref(supported ? 'off' : 'unsupported');
    const needsTap = ref(false);
    const offerer = meId < partnerId;
    const base = `/arena/sesi/${sessionId}/suara`;

    let stream = null;
    let pc = null;
    let audio = null;
    let timer = 0;
    let cursor = 0;
    let polling = false;
    let queued = [];
    let remoteSet = false;
    let gone = false;
    let gen = '';
    let offeredAt = 0;
    let beat = 0;
    let grace = 0;
    let connectTimer = 0;

    function resume() {
        if (!audio) return;
        audio.play().then(() => (needsTap.value = false)).catch(() => {});
    }
    const onAnyTap = () => needsTap.value && resume();
    if (typeof document !== 'undefined') document.addEventListener('pointerdown', onAnyTap, true);

    /** No voice after 25 s of trying: say so instead of "menyambungkan" forever. A tap on the button tries again. */
    function armConnect() {
        clearTimeout(connectTimer);
        connectTimer = setTimeout(() => {
            if (status.value === 'connecting') {
                closePeer();
                status.value = 'failed';
            }
        }, 25000);
    }

    const send = (type, data, g = gen) => http.post(base, { type, data: JSON.stringify({ g, d: data ?? null }) }).catch(() => {});

    function closePeer() {
        if (pc) {
            pc.onicecandidate = pc.ontrack = pc.onconnectionstatechange = null;
            try {
                pc.close();
            } catch {
                /* already closed */
            }
        }
        pc = null;
        remoteSet = false;
        queued = [];
        if (audio) audio.srcObject = null;
    }

    function makePeer() {
        closePeer();
        pc = new RTCPeerConnection(iceConfig);
        stream.getTracks().forEach((track) => pc.addTrack(track, stream));
        const mine = gen;
        pc.onicecandidate = (e) => e.candidate && send('ice', e.candidate, mine);
        pc.ontrack = (e) => {
            if (!audio) return;
            audio.srcObject = e.streams[0] ?? new MediaStream([e.track]);
            audio.play().then(() => (needsTap.value = false)).catch(() => (needsTap.value = true));
        };
        pc.onconnectionstatechange = () => {
            if (!pc || status.value === 'off') return;
            const s = pc.connectionState;
            if (s === 'connected') {
                status.value = 'on';
                clearTimeout(grace);
                clearTimeout(connectTimer);
            } else if (s === 'failed' || s === 'disconnected') {
                // A short drop on mobile data heals by itself; only start over when it lasts.
                clearTimeout(grace);
                grace = setTimeout(() => {
                    if (!pc || pc.connectionState === 'connected' || status.value === 'off') return;
                    closePeer();
                    status.value = 'waiting'; // the heartbeat below asks the other side to start over
                    if (offerer) setTimeout(() => status.value === 'waiting' && !pc && startOffer().catch(() => {}), 1500);
                }, s === 'failed' ? 500 : 6000);
            }
        };
        return pc;
    }

    async function flushIce() {
        remoteSet = true;
        for (const c of queued) await pc?.addIceCandidate(c).catch(() => {});
        queued = [];
    }

    async function startOffer() {
        if (!stream || !offerer) return;
        gen = `${Date.now()}`;
        offeredAt = Date.now();
        const p = makePeer();
        status.value = 'connecting';
        armConnect();
        const offer = await p.createOffer();
        await p.setLocalDescription(offer);
        send('offer', p.localDescription);
    }

    async function handle(item) {
        if (status.value === 'off' || !stream) return;
        const note = item.data ? JSON.parse(item.data) : {};
        const data = note.d ?? null;
        if (item.type === 'hello') {
            // two hellos close together mean one start, not two
            if (offerer && Date.now() - offeredAt > 3000) await startOffer();
            else if (!offerer && !pc) send('hello');
        } else if (item.type === 'offer' && !offerer) {
            gen = note.g;
            const p = makePeer();
            status.value = 'connecting';
            armConnect();
            await p.setRemoteDescription(data);
            await flushIce();
            const answer = await p.createAnswer();
            await p.setLocalDescription(answer);
            send('answer', p.localDescription);
        } else if (item.type === 'answer' && offerer && pc && !remoteSet && note.g === gen) {
            await pc.setRemoteDescription(data);
            await flushIce();
        } else if (item.type === 'ice' && pc && note.g === gen) {
            if (remoteSet) await pc.addIceCandidate(data).catch(() => {});
            else queued.push(data);
        } else if (item.type === 'bye') {
            closePeer();
            status.value = 'waiting';
        }
    }

    async function poll() {
        if (polling || status.value === 'off') return;
        polling = true;
        try {
            const res = await http.get(`${base}?after=${cursor}`);
            cursor = res.last;
            for (const item of res.items) await handle(item).catch(() => {});
        } catch {
            /* next round */
        } finally {
            polling = false;
        }
    }

    async function on() {
        if (!supported || status.value !== 'off') return;
        status.value = 'asking';
        if (!audio) {
            audio = Object.assign(document.createElement('audio'), { autoplay: true, playsInline: true });
            audio.style.display = 'none';
            document.body.appendChild(audio);
        }
        audio.play().catch(() => {}); // inside the tap, so the phone lets sound play later
        try {
            stream = await navigator.mediaDevices.getUserMedia({ audio: { echoCancellation: true, noiseSuppression: true, autoGainControl: true }, video: false });
        } catch {
            status.value = 'denied';
            return;
        }
        if (gone) return release();
        status.value = 'waiting';
        try {
            const res = await http.get(`${base}?after=0`);
            cursor = res.last; // old notes belong to an older call
        } catch {
            /* the poll below catches up */
        }
        await send('hello');
        timer = setInterval(poll, 1000);
        // Not connected yet: the one who answers keeps saying hello, so the offerer always gets a turn.
        beat = setInterval(() => {
            if (!offerer && !pc && status.value === 'waiting') send('hello');
        }, 4000);
    }

    function release() {
        clearInterval(timer);
        clearInterval(beat);
        clearTimeout(grace);
        clearTimeout(connectTimer);
        needsTap.value = false;
        closePeer();
        stream?.getTracks().forEach((t) => t.stop());
        stream = null;
    }

    function off() {
        if (status.value === 'off' || status.value === 'unsupported') return;
        const wasOn = !!stream;
        release();
        status.value = 'off';
        if (wasOn) send('bye');
    }

    const toggle = () => {
        if (status.value === 'failed') {
            release();
            status.value = 'off';
            return on();
        }
        return status.value === 'off' || status.value === 'denied' ? on() : off();
    };

    onBeforeUnmount(() => {
        gone = true;
        document.removeEventListener('pointerdown', onAnyTap, true);
        off();
        audio?.remove();
        audio = null;
    });

    return { status, needsTap, toggle, on, off, supported };
}
