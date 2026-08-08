<script setup>
defineProps({
    plannedLogItems: { type: Array, default: () => [] },
    weekDays: { type: Array, default: () => [] },
    trainerDashboard: { type: Object, default: () => ({ overdue: [], missed: [], feedbackOpen: [], painSignals: [] }) },
    formatWeekday: { type: Function, required: true },
    itemStatusClass: { type: Function, required: true },
    itemStatusLabel: { type: Function, required: true },
    sportLabel: { type: Function, required: true },
    formatDuration: { type: Function, required: true },
    formatDate: { type: Function, required: true },
})

const emit = defineEmits([
    'drop-item-on-day',
    'start-drag-item',
    'document-plan-item',
    'open-plan-item',
    'open-modal',
])
</script>

<template>
    <section class="grid gap-4 xl:grid-cols-[1.4fr_1fr]">
            <div class="rounded-2xl border border-border bg-card p-4">
                <div class="flex flex-wrap items-center justify-between gap-3">
                    <div>
                        <p class="text-xs font-semibold uppercase tracking-wide text-air-blue">Wochenansicht</p>
                        <h2 class="mt-1 text-lg font-semibold text-primary">Diese Trainingswoche</h2>
                    </div>
                    <span class="rounded-full border border-border px-3 py-1 text-xs font-semibold text-secondary">{{ plannedLogItems.length }} geplante Einheiten</span>
                </div>
                <div class="mt-4 grid gap-2 md:grid-cols-7">
                    <div v-for="day in weekDays" :key="day.key" class="min-h-32 rounded-xl border border-border bg-inputBg/40 p-2 transition hover:border-air-blue/50" @dragover.prevent @drop="emit('drop-item-on-day', day)">
                        <p class="text-xs font-semibold uppercase tracking-wide text-secondary">{{ formatWeekday(day.date) }}</p>
                        <div class="mt-2 space-y-2">
                            <div v-for="item in day.items.slice(0, 3)" :key="item.id" draggable="true" class="cursor-grab rounded-lg border border-border bg-card p-2 active:cursor-grabbing" @dragstart="emit('start-drag-item', item)">
                                <div class="flex items-start justify-between gap-2">
                                    <p class="line-clamp-2 text-xs font-semibold text-primary">{{ item.title }}</p>
                                    <span class="rounded-full px-2 py-0.5 text-[10px] font-semibold" :class="itemStatusClass(item)">{{ itemStatusLabel(item) }}</span>
                                </div>
                                <p class="mt-1 text-[11px] text-secondary">{{ sportLabel(item.sport_type) }} · {{ formatDuration(item.duration_minutes) }}</p>
                                <div class="mt-2 flex gap-1">
                                    <button type="button" class="rounded-md border border-border px-2 py-1 text-[11px] font-semibold text-primary hover:bg-muted" @click="emit('document-plan-item', item)">
                                        Log
                                    </button>
                                    <button type="button" class="rounded-md border border-border px-2 py-1 text-[11px] font-semibold text-primary hover:bg-muted" @click="emit('open-plan-item', item)">
                                        Details
                                    </button>
                                    <button type="button" class="rounded-md border border-border px-2 py-1 text-[11px] font-semibold text-primary hover:bg-muted" @click="emit('open-modal', 'item-missed', item.plan, item)">
                                        Ausfall
                                    </button>
                                </div>
                            </div>
                            <p v-if="!day.items.length" class="text-xs text-secondary">{{ $t('frei') }}</p>
                        </div>
                    </div>
                </div>
            </div>

            <div class="rounded-2xl border border-border bg-card p-4">
                <p class="text-xs font-semibold uppercase tracking-wide text-air-blue">Trainer-Dashboard</p>
                <h2 class="mt-1 text-lg font-semibold text-primary">Aufmerksamkeit</h2>
                <div class="mt-4 grid grid-cols-2 gap-2">
                    <div class="rounded-xl border border-border bg-inputBg/40 p-3">
                        <p class="text-2xl font-semibold text-primary">{{ trainerDashboard.overdue.length }}</p>
                        <p class="text-xs text-secondary">Überfällig</p>
                    </div>
                    <div class="rounded-xl border border-border bg-inputBg/40 p-3">
                        <p class="text-2xl font-semibold text-primary">{{ trainerDashboard.missed.length }}</p>
                        <p class="text-xs text-secondary">Ausfälle</p>
                    </div>
                    <div class="rounded-xl border border-border bg-inputBg/40 p-3">
                        <p class="text-2xl font-semibold text-primary">{{ trainerDashboard.feedbackOpen.length }}</p>
                        <p class="text-xs text-secondary">Feedback offen</p>
                    </div>
                    <div class="rounded-xl border border-border bg-inputBg/40 p-3">
                        <p class="text-2xl font-semibold text-primary">{{ trainerDashboard.painSignals.length }}</p>
                        <p class="text-xs text-secondary">Schmerzsignal</p>
                    </div>
                </div>
                <div class="mt-4 space-y-2">
                    <div v-for="item in trainerDashboard.overdue.slice(0, 3)" :key="item.id" class="rounded-xl border border-warning/30 bg-warning/10 p-3">
                        <p class="text-sm font-semibold text-primary">{{ item.title }}</p>
                        <p class="text-xs text-secondary">{{ item.plan.title }} · {{ formatDate(item.scheduled_at) }}</p>
                    </div>
                    <p v-if="!trainerDashboard.overdue.length" class="text-sm text-secondary">Keine Überfälligen Einheiten.</p>
                </div>
            </div>
        </section>
</template>

