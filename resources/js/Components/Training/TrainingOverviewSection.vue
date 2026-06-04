<script setup>
defineProps({
    upcomingItems: { type: Array, default: () => [] },
    trainerDashboard: { type: Object, required: true },
    sportAccent: { type: Function, required: true },
    sportIcon: { type: Function, required: true },
    formatDate: { type: Function, required: true },
    formatTime: { type: Function, required: true },
    documentPlanItem: { type: Function, required: true },
    openPlanItem: { type: Function, required: true },
    setTrainingSection: { type: Function, required: true },
})
</script>

<template>
    <section class="grid gap-3 xl:grid-cols-[minmax(0,1fr),360px]">
        <div v-if="upcomingItems.length" class="rounded-2xl border border-border bg-card p-3 sm:p-4">
            <div class="flex flex-wrap items-center justify-between gap-3">
                <div>
                    <p class="text-xs font-semibold uppercase tracking-wide text-air-blue">Start</p>
                    <h2 class="mt-1 text-lg font-semibold text-primary sm:text-xl">Was steht als Nächstes an?</h2>
                </div>
                <button type="button" class="rounded-xl bg-buttonPrimary px-3 py-2 text-sm font-semibold text-buttonTextPrimary sm:px-4" @click="setTrainingSection('plans')">
                    Zu den Plänen
                </button>
            </div>
            <div class="mt-3 grid gap-2.5 md:grid-cols-2">
                <article v-for="item in upcomingItems.slice(0, 4)" :key="`${item.plan.id}-${item.id}`" class="rounded-xl border border-border bg-inputBg/40 p-3">
                    <div class="flex items-start gap-3">
                        <span class="flex h-11 w-11 shrink-0 items-center justify-center rounded-xl text-white" :class="sportAccent(item.sport_type)">
                            <i :class="sportIcon(item.sport_type)" class="text-xl"></i>
                        </span>
                        <div class="min-w-0 flex-1">
                            <p class="truncate text-sm font-semibold text-primary">{{ item.title }}</p>
                            <p class="mt-1 text-xs text-secondary">{{ item.plan.title }} &middot; {{ formatDate(item.scheduled_at) }} {{ formatTime(item.scheduled_at) }}</p>
                            <div class="mt-3 flex flex-wrap gap-2">
                                <button type="button" class="rounded-lg border border-success/40 px-3 py-1.5 text-xs font-semibold text-success hover:bg-success/10" @click="documentPlanItem(item)">
                                    Dokumentieren
                                </button>
                                <button type="button" class="rounded-lg border border-border px-3 py-1.5 text-xs font-semibold text-primary hover:bg-muted" @click="openPlanItem(item)">
                                    Details
                                </button>
                            </div>
                        </div>
                    </div>
                </article>
            </div>
        </div>

        <div v-else class="rounded-2xl border border-border bg-card p-2 sm:hidden">
            <div class="grid grid-cols-2 gap-2">
                <button type="button" class="rounded-xl bg-buttonPrimary px-3 py-2.5 text-sm font-semibold text-buttonTextPrimary" @click="setTrainingSection('plans')">
                    Pläne öffnen
                </button>
                <button type="button" class="rounded-xl border border-border px-3 py-2.5 text-sm font-semibold text-primary" @click="setTrainingSection('week')">
                    Woche
                </button>
            </div>
        </div>

        <aside class="hidden space-y-3 sm:block">
            <section class="rounded-2xl border border-border bg-card p-4">
                <p class="text-xs font-semibold uppercase tracking-wide text-air-blue">Aufmerksamkeit</p>
                <div class="mt-3 grid grid-cols-2 gap-2">
                    <span class="rounded-xl border border-border bg-inputBg/40 p-3 text-xs text-secondary"><b class="block text-xl text-primary">{{ trainerDashboard.overdue.length }}</b>überfällig</span>
                    <span class="rounded-xl border border-border bg-inputBg/40 p-3 text-xs text-secondary"><b class="block text-xl text-primary">{{ trainerDashboard.feedbackOpen.length }}</b>Feedback offen</span>
                </div>
            </section>
        </aside>
    </section>
</template>

