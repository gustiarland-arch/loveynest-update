import { onBeforeUnmount, ref } from 'vue';

const MAX_SECONDS = 180;
const TYPES = ['audio/webm;codecs=opus', 'audio/webm', 'audio/mp4', 'audio/ogg;codecs=opus'];

/** Which i18n key (chat.*) explains a microphone error. */
export function voiceErrorKey(error) {
    const name = error?.name ?? '';
    if (error?.code === 'insecure') return 'chat.voiceInsecure';
    if (name === 'NotAllowedError' || name === 'SecurityError' || name === 'PermissionDeniedError') return 'chat.voiceDenied';
    if (name === 'NotFoundError' || name === 'DevicesNotFoundError' || name === 'OverconstrainedError') return 'chat.voiceMissing';
    if (name === 'NotReadableError' || name === 'TrackStartError' || name === 'AbortError') return 'chat.voiceBusy';
    return 'chat.voiceFailed';
}

/**
 * Records a voice note with the microphone. Nothing is recorded until start()
 * is called from a button press, and the stream is always released afterwards.
 *
 * - `level` (0..1) follows the loudness, so the screen can show that the microphone is live.
 * - `permission` is 'granted' | 'denied' | 'prompt' | 'unknown' (best effort, not every browser can tell).
 * - If the microphone is taken away mid-recording (a call comes in, the app goes to the background) what was
 *   captured so far is kept: `interrupted` turns true and stop() still returns it.
 */
export function useVoiceRecorder() {
    const supported = typeof navigator !== 'undefined' && !!navigator.mediaDevices?.getUserMedia && typeof window.MediaRecorder !== 'undefined';
    const recording = ref(false);
    const interrupted = ref(false);
    const seconds = ref(0);
    const level = ref(0);
    const permission = ref('unknown');

    let recorder = null;
    let stream = null;
    let chunks = [];
    let timer = null;
    let startedAt = 0;
    let finish = null;
    let held = null;
    let audioContext = null;
    let meterFrame = 0;

    navigator.permissions
        ?.query({ name: 'microphone' })
        .then((status) => {
            permission.value = status.state;
            status.onchange = () => (permission.value = status.state);
        })
        .catch(() => {});

    function stopMeter() {
        cancelAnimationFrame(meterFrame);
        meterFrame = 0;
        level.value = 0;
        audioContext?.close?.().catch(() => {});
        audioContext = null;
    }

    function startMeter(source) {
        try {
            const Context = window.AudioContext || window.webkitAudioContext;
            if (!Context) return;
            audioContext = new Context();
            const analyser = audioContext.createAnalyser();
            analyser.fftSize = 256;
            audioContext.createMediaStreamSource(source).connect(analyser);
            const data = new Uint8Array(analyser.fftSize);
            const tick = () => {
                analyser.getByteTimeDomainData(data);
                let peak = 0;
                for (let i = 0; i < data.length; i++) peak = Math.max(peak, Math.abs(data[i] - 128));
                level.value = Math.min(1, peak / 64);
                meterFrame = requestAnimationFrame(tick);
            };
            tick();
        } catch {
            // The level meter is a nicety; recording works without it.
        }
    }

    function releaseStream() {
        clearInterval(timer);
        timer = null;
        stopMeter();
        stream?.getTracks().forEach((track) => {
            track.onended = null;
            track.stop();
        });
        stream = null;
    }

    function release() {
        releaseStream();
        recording.value = false;
        interrupted.value = false;
    }

    function onHidden() {
        // Android and iOS cut the microphone when the app leaves the screen: keep what was recorded.
        if (document.visibilityState === 'hidden' && recorder?.state === 'recording') {
            interrupted.value = true;
            recorder.stop();
        }
    }

    /** @throws when the microphone is blocked, missing or busy (see voiceErrorKey) */
    async function start() {
        if (!supported || recording.value) return;
        if (window.isSecureContext === false) throw Object.assign(new Error('insecure'), { code: 'insecure' });
        held = null;
        stream = await navigator.mediaDevices.getUserMedia({ audio: { echoCancellation: true, noiseSuppression: true, autoGainControl: true } });
        permission.value = 'granted';
        const type = TYPES.find((candidate) => window.MediaRecorder.isTypeSupported?.(candidate));
        chunks = [];
        try {
            recorder = new window.MediaRecorder(stream, type ? { mimeType: type } : undefined);
        } catch (error) {
            releaseStream();
            throw error;
        }
        recorder.ondataavailable = (event) => event.data?.size && chunks.push(event.data);
        recorder.onerror = () => {
            interrupted.value = true;
        };
        recorder.onstop = () => {
            const elapsed = Math.max(1, Math.round((Date.now() - startedAt) / 1000));
            const mime = (recorder.mimeType || type || 'audio/mp4').split(';')[0];
            const result = { blob: new Blob(chunks, { type: mime }), seconds: Math.min(elapsed, MAX_SECONDS), mime };
            releaseStream();
            document.removeEventListener('visibilitychange', onHidden);
            if (finish) {
                recording.value = false;
                const done = finish;
                finish = null;
                done(result);
            } else {
                // Stopped from outside: the screen keeps showing the recording until the person sends or cancels it.
                held = result;
            }
        };
        stream.getAudioTracks().forEach((track) => {
            track.onended = () => {
                interrupted.value = true;
                if (recorder?.state === 'recording') recorder.stop();
            };
        });
        recorder.start(1000);
        startedAt = Date.now();
        seconds.value = 0;
        interrupted.value = false;
        recording.value = true;
        startMeter(stream);
        document.addEventListener('visibilitychange', onHidden);
        timer = setInterval(() => {
            seconds.value = Math.round((Date.now() - startedAt) / 1000);
            if (seconds.value >= MAX_SECONDS) recorder?.state === 'recording' && recorder.stop();
        }, 250);
    }

    /** Stops and resolves with the recording, or null when nothing was captured. */
    function stop() {
        return new Promise((resolve) => {
            if (held) {
                const result = held;
                held = null;
                release();
                return resolve(result.blob.size ? result : null);
            }
            if (!recorder || recorder.state !== 'recording') {
                release();
                return resolve(null);
            }
            finish = (result) => {
                release();
                resolve(result.blob.size ? result : null);
            };
            recorder.stop();
        });
    }

    function cancel() {
        finish = null;
        held = null;
        document.removeEventListener('visibilitychange', onHidden);
        if (recorder && recorder.state === 'recording') {
            recorder.onstop = null;
            recorder.stop();
        }
        release();
    }

    onBeforeUnmount(cancel);

    return { supported, recording, interrupted, seconds, level, permission, start, stop, cancel };
}
