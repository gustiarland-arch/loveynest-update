<script setup>
import { ref, shallowRef, computed, watch, onMounted, onBeforeUnmount, nextTick } from 'vue';
import { Eye, RotateCcw, Undo2, Save, Shuffle, Maximize2, FlipHorizontal2, LoaderCircle, TriangleAlert, ScanFace, Film, Trash2, BringToFront } from '@lucide/vue';
import { usePage } from '@inertiajs/vue3';
import Button from '@/Components/ui/Button.vue';
import Card from '@/Components/ui/Card.vue';
import Dialog from '@/Components/ui/Dialog.vue';
import SegmentedControl from '@/Components/ui/SegmentedControl.vue';
import SliderField from '@/Components/ui/SliderField.vue';
import TextField from '@/Components/ui/TextField.vue';
import ThemeCard from '@/Components/photobooth/ThemeCard.vue';
import FilterStrip from '@/Components/photobooth/FilterStrip.vue';
import { THEMES, STICKER_SETS, themeByKey } from '@/lib/booth/themes';
import { ADJUST_FIELDS, EFFECT_FIELDS, SKIN_FIELDS, NEUTRAL } from '@/lib/booth/engine';
import { composeBooth, ensureFonts, ensureMascots } from '@/lib/booth/draw';
import { FREE_STICKERS, newPlaced, preloadPlaced, stickerByKey, baseSize } from '@/lib/booth/placed';
import { makeGif, shareOrDownload } from '@/lib/booth/gif';
import { coupleSvgDoc, kindOf } from '@/lib/couple';
import { loadShot, renderPhoto, toJpeg, DEFAULT_TRANSFORM } from '@/lib/booth/pipeline';
import { faceDetectionAvailable } from '@/lib/booth/faces';
import { booth, lookOf, chooseTheme, resetLook, resetPicture } from '@/lib/booth/settings';
import { t } from '@/i18n';

// Editor for a finished round: theme, filter, adjustments, skin retouch, stickers and per-photo crop.
// Photos stay as the camera took them; every change is drawn again from those originals.
const props = defineProps({
    session: { type: Object, required: true }, // { layout, count, slots[{url}] }
    names: { type: Array, required: true },
    date: { type: Date, required: true },
    saving: Boolean,
});
const emit = defineEmits(['save']);

const PREVIEW_SIZE = 360;
const EXPORT_SIZE = 640;

const raws = shallowRef([]);
const status = ref('loading'); // loading | ready | failed
const working = ref(false);
const preview = ref(null);
const tab = ref('theme');
const selected = ref(0);
const comparing = ref(false);
const full = ref(false);
const fullUrl = ref(null);
const exporting = ref(false);
const exportError = ref(null);
let token = 0;
let timer = null;
let initial = null;

const tabs = computed(() => ['theme', 'filter', 'adjust', 'skin', 'decor', 'free', 'photo'].map((value) => ({ value, label: t(`booth.tabs.${value}`) })));
const theme = computed(() => themeByKey(booth.theme));
const names = computed(() => props.names.filter(Boolean));
const faces = faceDetectionAvailable();

const NEUTRAL_LOOK = { preset: 'original', intensity: 100, adjust: { ...NEUTRAL } };
const tf = (i) => ({ ...DEFAULT_TRANSFORM, ...(booth.transforms[i] ?? {}) });

function setT(key, value) {
    booth.transforms = { ...booth.transforms, [selected.value]: { ...tf(selected.value), [key]: value } };
}
const zoom = computed({ get: () => Math.round(tf(selected.value).zoom * 100), set: (v) => setT('zoom', v / 100) });
const panX = computed({ get: () => Math.round(tf(selected.value).x * 100), set: (v) => setT('x', v / 100) });
const panY = computed({ get: () => Math.round(tf(selected.value).y * 100), set: (v) => setT('y', v / 100) });
const rot = computed({ get: () => tf(selected.value).rot, set: (v) => setT('rot', v) });
const mirror = computed(() => tf(selected.value).mirror);

const stickerSets = computed(() => booth.stickers.sets ?? theme.value.ornaments.sets);
const density = computed({ get: () => booth.stickers.density ?? theme.value.ornaments.density, set: (v) => (booth.stickers.density = v) });
function toggleSet(key) {
    const now = new Set(stickerSets.value);
    now.has(key) ? now.delete(key) : now.add(key);
    booth.stickers.sets = STICKER_SETS.filter((k) => now.has(k));
}

const hasWork = computed(() => JSON.stringify(snapshot()) !== initial);

function snapshot() {
    return { theme: booth.theme, preset: booth.preset, intensity: booth.intensity, adjust: booth.adjust, stickers: { sets: booth.stickers.sets, density: booth.stickers.density, seed: booth.stickers.seed }, title: booth.title, sub: booth.sub, transforms: booth.transforms, placed: booth.placed };
}

function undoAll() {
    const s = JSON.parse(initial);
    booth.theme = s.theme;
    booth.preset = s.preset;
    booth.intensity = s.intensity;
    booth.adjust = { ...NEUTRAL, ...s.adjust };
    booth.stickers.sets = s.stickers.sets;
    booth.stickers.density = s.stickers.density;
    booth.stickers.seed = s.stickers.seed;
    booth.title = s.title;
    booth.sub = s.sub;
    booth.transforms = s.transforms;
    booth.placed = s.placed ?? [];
}

function resetAll() {
    resetLook();
    resetPicture();
    booth.stickers.sets = null;
    booth.stickers.density = null;
}

async function cellsFor(look, size) {
    return Promise.all(raws.value.map((raw, i) => renderPhoto(raw, i, tf(i), look, size)));
}

// v2: who the two characters are (the person's own gender first, as the names are listed).
const page = usePage();
const kinds = computed(() => [kindOf(page.props.auth?.user?.gender), kindOf(page.props.partner?.gender ?? (page.props.auth?.user?.gender === 'laki-laki' ? 'perempuan' : 'laki-laki'))]);
let lastCells = null;

function compose(cells, maxEdge) {
    return composeBooth({
        placed: booth.placed,
        kinds: kinds.value,
        themeKey: booth.theme,
        layout: props.session.layout,
        cells,
        names: names.value,
        date: props.date,
        text: { title: booth.title.trim(), sub: booth.sub.trim() },
        stickers: { sets: booth.stickers.sets, density: booth.stickers.density, seed: booth.stickers.seed },
        maxEdge,
    });
}

async function refresh() {
    if (status.value !== 'ready') return;
    const mine = ++token;
    working.value = true;
    try {
        const [cells] = await Promise.all([cellsFor(comparing.value ? NEUTRAL_LOOK : lookOf(), PREVIEW_SIZE), ensureFonts(), ensureMascots(kinds.value), preloadPlaced(booth.placed)]);
        if (mine !== token) return;
        lastCells = cells;
        const canvas = compose(cells, 1100);
        const target = preview.value;
        if (target) {
            target.width = canvas.width;
            target.height = canvas.height;
            target.getContext('2d').drawImage(canvas, 0, 0);
            nextTick(measure);
        }
        if (full.value) fullUrl.value = canvas.toDataURL('image/jpeg', 0.9);
    } finally {
        if (mine === token) working.value = false;
    }
}

function schedule() {
    clearTimeout(timer);
    timer = setTimeout(refresh, 80);
}

watch(() => [booth.theme, booth.preset, booth.intensity, { ...booth.adjust }, booth.title, booth.sub, booth.stickers.sets, booth.stickers.density, booth.stickers.seed, booth.transforms, comparing.value], schedule, { deep: true });
watch(full, (open) => {
    if (open) fullUrl.value = preview.value?.toDataURL('image/jpeg', 0.9) ?? null;
});

async function load() {
    status.value = 'loading';
    try {
        raws.value = await Promise.all(props.session.slots.map((slot) => loadShot(slot.url)));
        status.value = 'ready';
        initial ??= JSON.stringify(snapshot());
        await nextTick();
        refresh();
    } catch {
        status.value = 'failed';
    }
}

async function save() {
    if (exporting.value || props.saving) return;
    exporting.value = true;
    exportError.value = null;
    try {
        await ensureFonts();
        await Promise.all([ensureMascots(kinds.value), preloadPlaced(booth.placed)]);
        const cells = await cellsFor(lookOf(), EXPORT_SIZE);
        const blob = await toJpeg(compose(cells, 2560), 0.9);
        emit('save', blob);
    } catch {
        exportError.value = t('booth.composeFailed');
    } finally {
        exporting.value = false;
    }
}

// Hold to see the originals in the same template; a key press toggles for people who cannot hold.
function holdStart() {
    comparing.value = true;
}
function holdEnd() {
    comparing.value = false;
}
function toggleCompare(event) {
    if (event.detail === 0) comparing.value = !comparing.value;
}

// ---- v2: stickers placed by hand. Only the picture is drawn again while one moves (photos are kept). ----
const activeSticker = ref(null);
const canvasBox = ref({ left: 0, top: 0, width: 0, height: 0 });
const stage = ref(null);
let raf = 0;
function measure() {
    const c = preview.value;
    const host = stage.value;
    if (!c || !host) return;
    const a = c.getBoundingClientRect();
    const b = host.getBoundingClientRect();
    canvasBox.value = { left: a.left - b.left, top: a.top - b.top, width: a.width, height: a.height };
}
function redrawFast() {
    if (raf) return;
    raf = requestAnimationFrame(async () => {
        raf = 0;
        if (!lastCells || !preview.value) return schedule();
        await preloadPlaced(booth.placed);
        const canvas = compose(lastCells, 1100);
        const target = preview.value;
        target.getContext('2d').drawImage(canvas, 0, 0, target.width, target.height);
    });
}
watch(() => booth.placed, redrawFast, { deep: true });
function addSticker(key) {
    const item = newPlaced(key, booth.placed.length);
    booth.placed = [...booth.placed, item];
    activeSticker.value = item.id;
}
const active = computed(() => booth.placed.find((p) => p.id === activeSticker.value) ?? null);
function patchActive(change) {
    booth.placed = booth.placed.map((p) => (p.id === activeSticker.value ? { ...p, ...change } : p));
}
function removeActive() {
    booth.placed = booth.placed.filter((p) => p.id !== activeSticker.value);
    activeSticker.value = null;
}
function toFront() {
    const item = active.value;
    if (item) booth.placed = [...booth.placed.filter((p) => p.id !== item.id), item];
}
let drag = null;
function grab(event, item) {
    activeSticker.value = item.id;
    drag = { id: item.id, sx: event.clientX, sy: event.clientY, x: item.x, y: item.y };
    event.currentTarget.setPointerCapture?.(event.pointerId);
}
function move(event) {
    if (!drag) return;
    const { width, height } = canvasBox.value;
    const x = Math.min(1, Math.max(0, drag.x + (event.clientX - drag.sx) / width));
    const y = Math.min(1, Math.max(0, drag.y + (event.clientY - drag.sy) / height));
    booth.placed = booth.placed.map((p) => (p.id === drag.id ? { ...p, x, y } : p));
}
function drop() {
    drag = null;
}
const hitSize = (item) => baseSize(canvasBox.value.width, canvasBox.value.height) * item.s;
const charThumb = (st) => `data:image/svg+xml;charset=utf-8,${encodeURIComponent(coupleSvgDoc(st.kind, st.mood, st.arms, 96))}`;
let resizeObs = null;
onMounted(() => {
    resizeObs = new ResizeObserver(measure);
    if (preview.value) resizeObs.observe(preview.value);
    window.addEventListener('resize', measure);
});
onBeforeUnmount(() => {
    resizeObs?.disconnect();
    window.removeEventListener('resize', measure);
    cancelAnimationFrame(raf);
});
watch([status, tab], () => nextTick(measure));

// ---- v2: animated GIF of the round ----
const gifBusy = ref(false);
async function saveGif() {
    if (gifBusy.value || status.value !== 'ready') return;
    gifBusy.value = true;
    try {
        const cells = await cellsFor(lookOf(), 420);
        const blob = await makeGif(cells, { bg: theme.value.bg.c1 ?? '#ffe3ee', ink: theme.value.palette.ink, title: booth.title.trim() || names.value.join(' & ') });
        await shareOrDownload(blob, `loveynest-photobooth-${Date.now()}.gif`);
    } catch {
        exportError.value = 'GIF belum berhasil dibuat. Coba lagi.';
    } finally {
        gifBusy.value = false;
    }
}

onMounted(load);
onBeforeUnmount(() => clearTimeout(timer));
</script>

<template>
    <div class="grid gap-4 md:grid-cols-[minmax(0,1fr)_minmax(0,1.15fr)] md:items-start lg:grid-cols-[minmax(0,1fr)_26rem]">
        <!-- Preview -->
        <div class="sticky top-2 z-10 md:top-4">
            <div class="glass relative mx-auto flex w-full items-center justify-center rounded-[1.5rem] p-2 md:p-3" :class="session.layout === 'strip' || session.layout === 'trio' ? 'h-[38dvh] md:h-[78dvh]' : 'h-[38dvh] md:h-auto md:aspect-[4/5]'">
                <div ref="stage" class="absolute inset-0 flex items-center justify-center p-2 md:p-3" @pointermove="move" @pointerup="drop" @pointercancel="drop">
                    <canvas ref="preview" class="max-h-full max-w-full shrink-0 rounded-[0.75rem]" role="img" :aria-label="t('booth.previewLabel')" @load="measure" />
                    <!-- v2: hit areas for hand-placed stickers (the stickers themselves are drawn on the canvas) -->
                    <template v-if="tab === 'free'">
                        <button
                            v-for="item in booth.placed"
                            :key="item.id"
                            type="button"
                            class="placed-hit absolute rounded-2xl"
                            :class="item.id === activeSticker ? 'is-active' : ''"
                            :style="{ left: `${canvasBox.left + item.x * canvasBox.width - hitSize(item) / 2}px`, top: `${canvasBox.top + item.y * canvasBox.height - hitSize(item) / 2}px`, width: `${hitSize(item)}px`, height: `${hitSize(item)}px`, transform: `rotate(${item.r}deg)`, touchAction: 'none' }"
                            :aria-label="`Stiker ${stickerByKey(item.key)?.text ?? 'karakter'}, geser untuk memindahkan`"
                            @pointerdown.prevent="grab($event, item)"
                        />
                    </template>
                </div>
                <div v-if="status === 'loading'" class="absolute inset-0 grid place-items-center text-sm font-semibold text-ink-muted"><span class="inline-flex items-center gap-2"><LoaderCircle class="size-4 animate-spin" aria-hidden="true" />{{ t('booth.loadingPhotos') }}</span></div>
                <div v-else-if="status === 'failed'" class="absolute inset-0 grid place-items-center p-4 text-center">
                    <div class="space-y-2"><TriangleAlert class="mx-auto size-6 text-danger" aria-hidden="true" /><p class="text-sm text-plum">{{ t('booth.loadFailed') }}</p><Button size="sm" variant="soft" @click="load">{{ t('booth.retry') }}</Button></div>
                </div>
                <span v-if="comparing" class="absolute left-3 top-3 rounded-full bg-black/60 px-3 py-1 text-xs font-semibold text-white">{{ t('booth.before') }}</span>
                <span v-else-if="working" class="absolute right-3 top-3 grid size-7 place-items-center rounded-full bg-black/45 text-white" :aria-label="t('booth.processing')"><LoaderCircle class="size-4 animate-spin" aria-hidden="true" /></span>
                <button type="button" class="absolute bottom-3 right-3 grid size-10 place-items-center rounded-full bg-black/45 text-white" :aria-label="t('booth.viewFull')" @click="full = true"><Maximize2 class="size-4" aria-hidden="true" /></button>
            </div>
        </div>

        <!-- Controls -->
        <div class="min-w-0 space-y-4">
            <SegmentedControl v-model="tab" :options="tabs" :label="t('booth.editTitle')" />

            <Card v-show="tab === 'theme'" class="space-y-3">
                <p class="text-sm text-ink-muted">{{ t('booth.themeHint') }}</p>
                <div class="grid grid-cols-2 gap-2.5 min-[520px]:grid-cols-3 md:grid-cols-2 lg:grid-cols-3">
                    <ThemeCard v-for="th in THEMES" :key="th.key" :theme-key="th.key" :layout="session.layout" :selected="booth.theme === th.key" :names="names" @select="chooseTheme" />
                </div>
            </Card>

            <Card v-show="tab === 'filter'" class="space-y-3">
                <FilterStrip v-model="booth.preset" />
                <SliderField v-model="booth.intensity" :label="t('booth.intensity')" :reset="80" />
                <p class="pt-1 text-xs font-semibold uppercase tracking-[0.08em] text-ink-muted">{{ t('booth.effects') }}</p>
                <SliderField v-for="f in EFFECT_FIELDS" :key="f.key" v-model="booth.adjust[f.key]" :label="t(`booth.effect.${f.key}`)" :min="f.min" :max="f.max" />
            </Card>

            <Card v-show="tab === 'adjust'" class="space-y-1.5">
                <SliderField v-for="f in ADJUST_FIELDS" :key="f.key" v-model="booth.adjust[f.key]" :label="t(`booth.adjust.${f.key}`)" :min="f.min" :max="f.max" />
            </Card>

            <Card v-show="tab === 'skin'" class="space-y-1.5">
                <p class="text-sm text-ink-muted">{{ t('booth.skinHint') }}</p>
                <SliderField v-for="f in SKIN_FIELDS" :key="f.key" v-model="booth.adjust[f.key]" :label="t(`booth.skin.${f.key}`)" :min="f.min" :max="f.max" />
                <p class="flex items-start gap-2 pt-1 text-xs text-ink-muted"><ScanFace class="mt-0.5 size-4 shrink-0" aria-hidden="true" />{{ faces ? t('booth.facesOn') : t('booth.facesOff') }}</p>
            </Card>

            <Card v-show="tab === 'decor'" class="space-y-4">
                <div class="space-y-2">
                    <p class="text-sm font-semibold text-plum">{{ t('booth.stickers') }}</p>
                    <div class="flex flex-wrap gap-2" role="group" :aria-label="t('booth.stickers')">
                        <button v-for="key in STICKER_SETS" :key="key" type="button" class="h-10 rounded-full px-4 text-sm font-semibold ring-1 transition active:scale-[0.97]" :class="stickerSets.includes(key) ? 'bg-berry text-white ring-transparent' : 'bg-surface text-ink-muted ring-line'" :aria-pressed="stickerSets.includes(key)" @click="toggleSet(key)">{{ t(`booth.sticker.${key}`) }}</button>
                    </div>
                    <SliderField v-model="density" :label="t('booth.density')" :reset="theme.ornaments.density" />
                    <Button variant="soft" size="sm" @click="booth.stickers.seed += 1"><Shuffle class="size-4" aria-hidden="true" />{{ t('booth.shuffle') }}</Button>
                </div>
                <TextField v-model="booth.title" :label="t('booth.titleText')" :maxlength="40" :hint="t('booth.titleHint', { names: names.join(' & ') })" />
                <TextField v-model="booth.sub" :label="t('booth.subText')" :maxlength="50" :hint="t('booth.subHint')" />
            </Card>

            <Card v-show="tab === 'free'" class="space-y-3" data-testid="free-stickers">
                <p class="text-sm text-ink-muted">Ketuk stiker untuk menaruhnya, lalu geser di foto. Pilih stiker untuk mengubah ukuran dan arahnya.</p>
                <div class="grid grid-cols-6 gap-1.5">
                    <button v-for="st in FREE_STICKERS" :key="st.key" type="button" class="grid aspect-square place-items-center rounded-[0.9rem] bg-surface ring-1 ring-line transition active:scale-90" :aria-label="`Tambah stiker ${st.text ?? st.kind + ' ' + st.mood}`" @click="addSticker(st.key)">
                        <img v-if="st.type === 'char'" :src="charThumb(st)" alt="" class="size-[86%]" draggable="false" />
                        <span v-else-if="st.type === 'emoji'" class="text-2xl leading-none">{{ st.text }}</span>
                        <span v-else class="rounded-full bg-white px-1 text-[0.55rem] font-extrabold leading-tight text-[#e0457a] ring-1 ring-[#e0457a]">{{ st.text }}</span>
                    </button>
                </div>
                <div v-if="active" class="space-y-1.5 rounded-[1rem] bg-fill p-3">
                    <SliderField :model-value="Math.round(active.s * 100)" label="Ukuran" :min="40" :max="260" :step="5" unit="%" :reset="100" @update:model-value="(v) => patchActive({ s: v / 100 })" />
                    <SliderField :model-value="active.r" label="Putar" :min="-60" :max="60" unit="°" :reset="0" @update:model-value="(v) => patchActive({ r: v })" />
                    <div class="flex gap-2 pt-1">
                        <Button variant="soft" size="sm" @click="toFront"><BringToFront class="size-4" aria-hidden="true" />Ke depan</Button>
                        <Button variant="danger-ghost" size="sm" @click="removeActive"><Trash2 class="size-4" aria-hidden="true" />Hapus</Button>
                    </div>
                </div>
                <p v-else-if="booth.placed.length" class="text-xs text-ink-muted">{{ booth.placed.length }} stiker ditaruh. Ketuk salah satunya di foto untuk mengaturnya.</p>
            </Card>

            <Card v-show="tab === 'photo'" class="space-y-1.5">
                <div class="flex flex-wrap gap-2" role="radiogroup" :aria-label="t('booth.pickPhoto')">
                    <button v-for="(slot, i) in session.slots" :key="i" type="button" role="radio" :aria-checked="selected === i" class="h-10 min-w-10 rounded-full px-4 text-sm font-semibold ring-1 transition" :class="selected === i ? 'bg-berry text-white ring-transparent' : 'bg-surface text-ink-muted ring-line'" @click="selected = i">{{ i + 1 }}</button>
                </div>
                <SliderField v-model="zoom" :label="t('booth.zoom')" :min="100" :max="300" :step="5" :reset="100" unit="%" />
                <SliderField v-model="panX" :label="t('booth.panX')" :min="-100" :max="100" />
                <SliderField v-model="panY" :label="t('booth.panY')" :min="-100" :max="100" />
                <SliderField v-model="rot" :label="t('booth.rotate')" :min="-30" :max="30" unit="°" />
                <button type="button" class="flex min-h-11 w-full items-center justify-between rounded-[0.875rem] text-sm font-semibold text-plum" :aria-pressed="mirror" @click="setT('mirror', !mirror)">
                    <span class="inline-flex items-center gap-2"><FlipHorizontal2 class="size-4" aria-hidden="true" />{{ t('booth.mirror') }}</span>
                    <span class="rounded-full px-3 py-1 text-xs" :class="mirror ? 'bg-berry text-white' : 'bg-fill text-ink-muted'">{{ mirror ? t('booth.on') : t('booth.off') }}</span>
                </button>
            </Card>

            <!-- Actions -->
            <div class="space-y-2.5 pb-24 md:pb-2">
                <div class="grid grid-cols-3 gap-2">
                    <button type="button" class="glass flex min-h-12 select-none flex-col items-center justify-center gap-0.5 rounded-[1rem] px-1 text-xs font-semibold text-berry-ink active:scale-[0.97]" :aria-pressed="comparing" style="touch-action: none" @pointerdown.prevent="holdStart" @pointerup="holdEnd" @pointerleave="holdEnd" @pointercancel="holdEnd" @click="toggleCompare">
                        <Eye class="size-[1.125rem]" aria-hidden="true" />{{ t('booth.compare') }}
                    </button>
                    <button type="button" class="glass flex min-h-12 flex-col items-center justify-center gap-0.5 rounded-[1rem] px-1 text-xs font-semibold text-berry-ink active:scale-[0.97]" @click="resetAll"><RotateCcw class="size-[1.125rem]" aria-hidden="true" />{{ t('booth.resetAll') }}</button>
                    <button type="button" class="glass flex min-h-12 flex-col items-center justify-center gap-0.5 rounded-[1rem] px-1 text-xs font-semibold text-berry-ink active:scale-[0.97] disabled:opacity-40" :disabled="!hasWork" @click="undoAll"><Undo2 class="size-[1.125rem]" aria-hidden="true" />{{ t('booth.undo') }}</button>
                </div>
                <p v-if="exportError" class="text-center text-sm text-danger" role="alert">{{ exportError }}</p>
                <Button block size="lg" :loading="exporting || saving" :disabled="status !== 'ready'" @click="save"><Save class="size-5" aria-hidden="true" />{{ t('booth.saveToMemories') }}</Button>
                <Button block variant="soft" :loading="gifBusy" :disabled="status !== 'ready'" data-testid="booth-gif" @click="saveGif"><Film class="size-5" aria-hidden="true" />Simpan GIF bergerak</Button>
                <p class="text-center text-xs text-ink-muted">{{ t('booth.saveNote') }}</p>
            </div>
        </div>

        <Dialog v-model:open="full" :title="t('booth.previewLabel')" :description="t('booth.fullHint')">
            <img v-if="fullUrl" :src="fullUrl" :alt="t('booth.previewLabel')" class="mx-auto max-h-[62dvh] w-auto rounded-[1rem]" />
        </Dialog>
    </div>
</template>

<style scoped>
.placed-hit { background: transparent; outline: 2px dashed transparent; outline-offset: 2px; transition: outline-color 0.15s; cursor: grab; }
.placed-hit.is-active { outline-color: rgb(224 69 122 / 0.9); box-shadow: 0 0 0 4px rgb(255 255 255 / 0.6); }
.placed-hit:active { cursor: grabbing; }
</style>
