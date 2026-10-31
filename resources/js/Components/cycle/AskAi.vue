<script setup>
// "Tanya AI" about menstruation (see app/Services/Ai/CycleAsk.php). The conversation lives only on this screen.
import { nextTick, ref } from 'vue';
import { MessageCircleQuestion, SendHorizontal, Siren, ShieldCheck } from '@lucide/vue';
import { http, HttpError } from '@/lib/http';
import CoupleChar from '@/Components/brand/CoupleChar.vue';

const SUGGEST = ['Kenapa haidku telat?', 'Cara meredakan kram haid?', 'Kapan masa suburku?', 'Flek di luar haid normal nggak?', 'Apa itu PMS?', 'Berapa lama siklus yang normal?'];
const thread = ref([]);
const draft = ref('');
const busy = ref(false);
const box = ref(null);

async function ask(text = draft.value) {
    const q = text.trim();
    if (q.length < 4 || busy.value) return;
    draft.value = '';
    thread.value.push({ who: 'me', text: q });
    busy.value = true;
    scroll();
    try {
        const r = await http.post('/kesehatan/siklus/tanya', { question: q });
        thread.value.push({ who: 'ai', ...r });
    } catch (e) {
        thread.value.push({ who: 'ai', answer: e instanceof HttpError && e.status === 429 ? 'Tunggu sebentar sebelum bertanya lagi, ya.' : e?.data?.errors?.question?.[0] ?? 'Jawaban belum bisa dimuat. Coba lagi.', tips: [], error: true });
    } finally {
        busy.value = false;
        scroll();
    }
}
function scroll() {
    nextTick(() => box.value?.scrollTo({ top: box.value.scrollHeight, behavior: 'smooth' }));
}
</script>

<template>
    <section class="glass relative overflow-hidden rounded-[1.5rem] p-4" data-testid="ask-ai">
        <div class="flex items-center gap-2.5">
            <span class="w-11 shrink-0"><CoupleChar gender="perempuan" :mood="busy ? 'think' : 'happy'" :arms="busy ? 'down' : 'wave'" /></span>
            <div class="min-w-0 flex-1">
                <h3 class="flex items-center gap-1.5 font-semibold text-plum"><MessageCircleQuestion class="size-4 text-berry-ink" aria-hidden="true" />Tanya AI seputar haid</h3>
                <p class="text-xs text-ink-muted">Jawaban singkat dan mudah dipahami. Bukan pengganti dokter.</p>
            </div>
        </div>

        <div ref="box" class="mt-3 max-h-[22rem] space-y-2 overflow-y-auto pr-1" aria-live="polite">
            <div v-for="(m, i) in thread" :key="i" class="ask-in flex" :class="m.who === 'me' ? 'justify-end' : 'justify-start'">
                <p v-if="m.who === 'me'" class="max-w-[85%] rounded-[1.1rem] rounded-br-md bg-berry px-3.5 py-2 text-sm text-white">{{ m.text }}</p>
                <div v-else class="max-w-[92%] rounded-[1.1rem] rounded-bl-md bg-surface/80 px-3.5 py-2.5 text-sm text-plum shadow-soft">
                    <p v-if="m.danger" class="mb-2 flex items-start gap-1.5 rounded-xl bg-danger-soft px-2.5 py-2 text-[0.8rem] font-semibold text-danger"><Siren class="mt-0.5 size-4 shrink-0" aria-hidden="true" />Tanda yang kamu sebut bisa butuh pertolongan cepat. Segera ke IGD atau fasilitas kesehatan terdekat.</p>
                    <p class="leading-relaxed">{{ m.answer }}</p>
                    <ul v-if="m.tips?.length" class="mt-1.5 space-y-1">
                        <li v-for="(tip, k) in m.tips" :key="k" class="flex gap-2 text-[0.85rem]"><span class="mt-2 size-1.5 shrink-0 rounded-full bg-berry" />{{ tip }}</li>
                    </ul>
                    <p v-if="!m.error" class="mt-1.5 text-[0.68rem] text-ink-muted">{{ m.source === 'ai' ? 'Dijawab AI, dicek aturan aplikasi' : m.onTopic === false ? 'Di luar topik' : 'Jawaban bawaan aplikasi' }}</p>
                </div>
            </div>
            <div v-if="busy" class="flex"><p class="rounded-[1.1rem] bg-surface/80 px-4 py-3 shadow-soft"><span class="dot" /><span class="dot d2" /><span class="dot d3" /></p></div>
        </div>

        <div v-if="!thread.length" class="mt-1 flex flex-wrap gap-1.5">
            <button v-for="s in SUGGEST" :key="s" type="button" class="rounded-full bg-blush-soft px-3 py-1.5 text-xs font-semibold text-berry-ink transition active:scale-95" @click="ask(s)">{{ s }}</button>
        </div>

        <form class="mt-3 flex items-center gap-2" @submit.prevent="ask()">
            <input v-model="draft" type="text" maxlength="400" class="h-11 min-w-0 flex-1 rounded-full border-0 bg-surface/80 px-4 text-sm text-plum ring-1 ring-line focus:ring-2 focus:ring-berry" placeholder="Tulis pertanyaan seputar haid…" aria-label="Pertanyaan untuk AI" />
            <button type="submit" class="grid size-11 shrink-0 place-items-center rounded-full bg-berry text-white shadow-soft transition active:scale-95 disabled:opacity-50" :disabled="busy || draft.trim().length < 4" aria-label="Kirim pertanyaan"><SendHorizontal class="size-5" aria-hidden="true" /></button>
        </form>
        <p class="mt-2 flex items-start gap-1.5 text-[0.7rem] leading-snug text-ink-muted"><ShieldCheck class="mt-px size-3.5 shrink-0" aria-hidden="true" />Pertanyaanmu dan beberapa angka rata-rata siklus dikirim ke AI, tanpa nama atau catatan. Tidak disimpan. AI tidak mendiagnosis dan tidak memberi dosis obat.</p>
    </section>
</template>

<style scoped>
.ask-in { animation: ask-in 0.35s cubic-bezier(0.22, 1, 0.36, 1) backwards; }
.dot { display: inline-block; width: 0.45rem; height: 0.45rem; margin: 0 0.12rem; border-radius: 50%; background: var(--color-berry); animation: dot 1s ease-in-out infinite; }
.d2 { animation-delay: 0.15s; }
.d3 { animation-delay: 0.3s; }
@keyframes ask-in { from { opacity: 0; transform: translateY(8px); } }
@keyframes dot { 50% { transform: translateY(-4px); opacity: 0.5; } }
@media (prefers-reduced-motion: reduce) { .ask-in, .dot { animation: none; } }
</style>
