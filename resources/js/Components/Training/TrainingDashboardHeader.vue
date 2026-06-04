<script setup>
defineProps({
    aiTrainingPlanAvailable: {
        type: Boolean,
        default: false,
    },
    completedThisWeekCount: {
        type: Number,
        default: 0,
    },
    formatDate: {
        type: Function,
        required: true,
    },
    formatTime: {
        type: Function,
        required: true,
    },
    logsCount: {
        type: Number,
        default: 0,
    },
    nextTrainingItem: {
        type: Object,
        default: null,
    },
    plansCount: {
        type: Number,
        default: 0,
    },
    sportAccent: {
        type: Function,
        required: true,
    },
    sportIcon: {
        type: Function,
        required: true,
    },
    teamsCount: {
        type: Number,
        default: 0,
    },
    upcomingItems: {
        type: Array,
        default: () => [],
    },
})

const emit = defineEmits([
    'document-plan-item',
    'open-ai-training-plan-modal',
    'open-log-page',
    'open-plan-modal',
])
</script>

<template>
    <section class="rounded-2xl border border-border bg-card p-3 sm:p-5">
        <div class="grid gap-3 lg:grid-cols-[minmax(0,1fr)_340px] lg:gap-5">
            <div class="space-y-2.5 sm:space-y-3">
                <div class="flex items-center justify-between gap-2">
                    <span class="rounded-full border border-air-blue/40 bg-air-blue/10 px-3 py-1 text-[11px] font-semibold uppercase tracking-wide text-air-blue">
                        Training Hub
                    </span>
                    <span class="hidden rounded-full border border-border px-3 py-1 text-xs font-semibold text-secondary sm:inline-flex">
                        Planen · Ausführen · Teilen
                    </span>
                </div>
                <div
                    v-if="nextTrainingItem"
                    class="rounded-xl border px-3 py-2.5 sm:rounded-2xl sm:p-3"
                    :class="'border-air-blue/35 bg-air-blue/10'"
                >
                    <div class="flex items-center gap-3">
                        <span class="flex h-9 w-9 shrink-0 items-center justify-center rounded-xl text-white sm:h-11 sm:w-11 sm:rounded-2xl" :class="sportAccent(nextTrainingItem.sport_type)">
                            <i :class="sportIcon(nextTrainingItem.sport_type)" class="text-lg sm:text-xl"></i>
                        </span>
                        <div class="min-w-0 flex-1">
                            <p class="text-[11px] font-semibold uppercase tracking-wide text-air-blue">Nächstes Training</p>
                            <p class="truncate text-sm font-semibold text-primary">{{ nextTrainingItem.title }}</p>
                            <p class="truncate text-xs text-secondary">{{ nextTrainingItem.plan.title }} · {{ formatDate(nextTrainingItem.scheduled_at) }} {{ formatTime(nextTrainingItem.scheduled_at) }}</p>
                        </div>
                        <button type="button" class="hidden rounded-xl border border-success/40 px-3 py-2 text-xs font-semibold text-success hover:bg-success/10 sm:inline-flex" @click="emit('document-plan-item', nextTrainingItem)">
                            Starten
                        </button>
                    </div>
                </div>
                <div class="hidden grid-cols-2 gap-2 sm:flex sm:flex-wrap">
                    <button type="button" class="inline-flex min-h-10 items-center justify-center gap-2 rounded-xl bg-buttonPrimary px-3 py-2 text-sm font-semibold text-buttonTextPrimary sm:min-h-11 sm:px-4" @click="emit('open-plan-modal')">
                        <i class="las la-plus-circle text-lg"></i>
                        Plan erstellen
                    </button>
                    <button type="button" class="inline-flex min-h-10 items-center justify-center gap-2 rounded-xl border border-air-blue/50 bg-air-blue/10 px-3 py-2 text-sm font-semibold text-air-blue hover:bg-air-blue/15 disabled:cursor-not-allowed disabled:opacity-50 sm:min-h-11 sm:px-4" :disabled="!aiTrainingPlanAvailable" @click="emit('open-ai-training-plan-modal')">
                        <i class="las la-magic text-lg"></i>
                        KI-Plan
                    </button>
                    <button type="button" class="inline-flex min-h-10 items-center justify-center gap-2 rounded-xl border border-border px-3 py-2 text-sm font-semibold text-primary hover:bg-muted sm:min-h-11 sm:px-4" @click="emit('open-log-page')">
                        <i class="las la-pen-alt text-lg"></i>
                        Dokumentieren
                    </button>
                </div>
                <div v-if="!nextTrainingItem" class="rounded-xl border border-border bg-inputBg/50 px-3 py-2.5 sm:rounded-2xl sm:p-3">
                    <div class="flex items-center gap-3">
                        <span class="flex h-9 w-9 shrink-0 items-center justify-center rounded-xl bg-air-blue/15 text-air-blue sm:h-11 sm:w-11 sm:rounded-2xl">
                            <i class="las la-calendar-plus text-lg sm:text-xl"></i>
                        </span>
                        <div class="min-w-0">
                            <p class="text-sm font-semibold text-primary">Noch nichts geplant</p>
                            <p class="text-xs text-secondary sm:hidden">Starte direkt unten.</p>
                            <p class="hidden text-xs text-secondary sm:block">Erstelle einen Plan oder dokumentiere spontan.</p>
                        </div>
                    </div>
                </div>
            </div>
            <div class="rounded-xl border border-border bg-inputBg/40 p-2 lg:rounded-2xl lg:bg-muted/30 lg:p-2.5">
                <div class="grid grid-cols-4 gap-1.5 lg:grid-cols-2 lg:gap-2">
                    <div class="rounded-lg border border-border bg-card p-2 sm:rounded-xl sm:p-2.5">
                        <p class="text-base font-semibold leading-none text-primary sm:text-xl">{{ plansCount }}</p>
                        <p class="text-[11px] text-secondary">Pläne</p>
                    </div>
                    <div class="rounded-lg border border-border bg-card p-2 sm:rounded-xl sm:p-2.5">
                        <p class="text-base font-semibold leading-none text-primary sm:text-xl">{{ logsCount }}</p>
                        <p class="text-[11px] text-secondary">Logs</p>
                    </div>
                    <div class="rounded-lg border border-border bg-card p-2 sm:rounded-xl sm:p-2.5">
                        <p class="text-base font-semibold leading-none text-primary sm:text-xl">{{ completedThisWeekCount }}</p>
                        <p class="text-[11px] text-secondary">Erledigt</p>
                    </div>
                    <div class="rounded-lg border border-border bg-card p-2 sm:rounded-xl sm:p-2.5">
                        <p class="text-base font-semibold leading-none text-primary sm:text-xl">{{ teamsCount }}</p>
                        <p class="text-[11px] text-secondary">Teams</p>
                    </div>
                </div>
                <div class="mt-3 hidden rounded-xl border border-border bg-card p-3 lg:block">
                    <p class="text-xs font-semibold uppercase tracking-wide text-secondary">Nächstes Training</p>
                    <div v-if="upcomingItems.length" class="mt-2 space-y-2">
                        <div v-for="item in upcomingItems.slice(0, 2)" :key="`${item.plan.id}-${item.id}`" class="flex items-center gap-3">
                            <span class="flex h-10 w-10 items-center justify-center rounded-xl text-white" :class="sportAccent(item.sport_type)">
                                <i :class="sportIcon(item.sport_type)" class="text-xl"></i>
                            </span>
                            <div class="min-w-0">
                                <p class="truncate text-sm font-semibold text-primary">{{ item.title }}</p>
                                <p class="text-xs text-secondary">{{ formatDate(item.scheduled_at) }} {{ formatTime(item.scheduled_at) }}</p>
                            </div>
                        </div>
                    </div>
                    <p v-else class="mt-2 text-sm text-secondary">Noch kein Termin geplant.</p>
                </div>
            </div>
        </div>
    </section>
</template>


