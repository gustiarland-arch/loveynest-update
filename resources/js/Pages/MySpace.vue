<script setup>
import { computed, ref } from 'vue';
import { Head, Link, usePage } from '@inertiajs/vue3';
import { Lock, Feather, Plus, HeartPulse, ChevronRight } from '@lucide/vue';
import PageHeader from '@/Components/layout/PageHeader.vue';
import SectionTitle from '@/Components/layout/SectionTitle.vue';
import Card from '@/Components/ui/Card.vue';
import EmptyState from '@/Components/ui/EmptyState.vue';
import TaskItem from '@/Components/tasks/TaskItem.vue';
import TaskFormDialog from '@/Components/tasks/TaskFormDialog.vue';
import EventItem from '@/Components/calendar/EventItem.vue';
import EventFormDialog from '@/Components/calendar/EventFormDialog.vue';
import { todayKey } from '@/lib/dates';
import { t } from '@/i18n';

const props = defineProps({
    tasks: { type: Array, required: true },
    events: { type: Array, required: true },
    stats: { type: Object, required: true },
});

// Private health tools live in the private space: the cycle tracker is only offered to profiles that chose perempuan.
const isWoman = computed(() => usePage().props.auth.user?.gender === 'perempuan');

const taskForm = ref(false);
const editingTask = ref(null);
const eventForm = ref(false);
const editingEvent = ref(null);
const openTask = (task) => ((editingTask.value = task), (taskForm.value = true));
// Share of this week's work that is finished, for the progress ring.
const progress = computed(() => {
    const total = props.stats.open + props.stats.doneThisWeek;
    return total ? Math.round((props.stats.doneThisWeek / total) * 100) : 0;
});
const ring = 2 * Math.PI * 31;
const openEvent = (event) => ((editingEvent.value = event), (eventForm.value = true));
</script>

<template>
    <Head :title="t('mySpace.title')" />
    <PageHeader :title="t('mySpace.title')" back="/dashboard">
        <template #action>
            <span class="inline-flex items-center gap-1.5 rounded-full bg-lavender px-3 py-1 text-xs font-semibold text-lavender-ink">
                <Lock class="size-3.5" aria-hidden="true" />{{ $t('dashboard.privateNote') }}
            </span>
        </template>
    </PageHeader>
    <p class="-mt-3 mb-6 text-sm text-ink-muted">{{ $t('mySpace.lead') }}</p>

    <Link v-if="isWoman" href="/kesehatan/siklus" class="glass wash-blush mb-4 flex items-center gap-3 rounded-[1.375rem] p-4 transition hover:-translate-y-0.5 hover:shadow-lift">
        <span class="grid size-12 shrink-0 place-items-center rounded-2xl bg-blush text-berry-ink"><HeartPulse class="size-6" aria-hidden="true" /></span>
        <span class="min-w-0 flex-1">
            <span class="block font-semibold text-plum">{{ t('cycle.title') }}</span>
            <span class="block text-xs text-ink-muted">{{ t('cycle.entryBody') }}</span>
        </span>
        <ChevronRight class="size-5 shrink-0 text-ink-muted" aria-hidden="true" />
    </Link>

    <Card class="flex items-center gap-4 !p-4">
        <div class="relative size-[4.75rem] shrink-0" role="img" :aria-label="`${progress}%`">
            <svg viewBox="0 0 76 76" class="size-full -rotate-90" aria-hidden="true">
                <circle cx="38" cy="38" r="31" fill="none" stroke-width="8" class="stroke-plum/10" />
                <circle cx="38" cy="38" r="31" fill="none" stroke-width="8" stroke-linecap="round" class="stroke-berry transition-all duration-700" :class="!progress && 'opacity-0'" :stroke-dasharray="`${(progress / 100) * ring} ${ring}`" />
            </svg>
            <span class="absolute inset-0 grid place-items-center font-display text-lg font-semibold text-plum">{{ progress }}%</span>
        </div>
        <dl class="grid flex-1 grid-cols-3">
            <div v-for="key in ['open', 'overdue', 'doneThisWeek']" :key="key" class="flex flex-col-reverse items-center text-center">
                <dt class="text-[0.6875rem] leading-tight text-ink-muted">
                    {{ t(key === 'open' ? 'mySpace.statOpen' : key === 'overdue' ? 'mySpace.statOverdue' : 'mySpace.statDone') }}
                </dt>
                <dd class="font-display text-[1.625rem] font-semibold leading-tight" :class="key === 'overdue' && stats.overdue ? 'text-danger' : 'text-plum'">{{ stats[key] }}</dd>
            </div>
        </dl>
    </Card>

    <template v-if="tasks.length || events.length">
        <SectionTitle :title="t('mySpace.tasksTitle')" href="/tasks?space=mine" :link-label="t('tasks.seeAll')" />
        <ul v-if="tasks.length" class="space-y-2">
            <TaskItem v-for="task in tasks" :key="task.id" :task="task" @edit="openTask" />
        </ul>
        <p v-else class="text-sm text-ink-muted">{{ t('tasks.emptyMineBody') }}</p>

        <SectionTitle :title="t('mySpace.eventsTitle')" href="/calendar?space=mine" :link-label="t('calendar.title')" />
        <ul v-if="events.length" class="space-y-2">
            <EventItem v-for="event in events" :key="event.id" :event="event" show-date @edit="openEvent" />
        </ul>
        <p v-else class="text-sm text-ink-muted">{{ t('calendar.emptyBody') }}</p>
    </template>
    <Card v-else :padded="false" class="mt-6">
        <EmptyState :title="t('mySpace.emptyTitle')" :body="t('mySpace.emptyBody')" :icon="Feather" />
    </Card>

    <Teleport to="body">
        <button
            type="button"
            class="fixed bottom-28 right-5 z-30 grid size-14 place-items-center rounded-[1.25rem] bg-plum text-surface shadow-lift transition active:scale-95 lg:bottom-10 lg:right-10"
            :aria-label="t('tasks.new')"
            @click="openTask(null)"
        >
            <Plus class="size-6" aria-hidden="true" />
        </button>
    </Teleport>

    <TaskFormDialog v-model:open="taskForm" :task="editingTask" />
    <EventFormDialog v-model:open="eventForm" :event="editingEvent" :default-date="todayKey()" />
</template>
