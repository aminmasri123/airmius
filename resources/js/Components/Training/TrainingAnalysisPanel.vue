<script setup>
defineProps({
    athleteCockpit: { type: Array, default: () => [] },
    aiGeneratedPlans: { type: Array, default: () => [] },
    aiGeneratedPlanInsights: { type: Array, default: () => [] },
    sportStats: { type: Array, default: () => [] },
    formatDate: { type: Function, required: true },
    formatDuration: { type: Function, required: true },
    formatDistance: { type: Function, required: true },
})
</script>

<template>
    <section class="grid gap-4 xl:grid-cols-[1.2fr_1fr]">
            <div class="rounded-2xl border border-border bg-card p-4">
                <div class="flex items-center justify-between gap-3">
                    <div>
                        <p class="text-xs font-semibold uppercase tracking-wide text-air-blue">Athleten-Cockpit</p>
                        <h2 class="mt-1 text-lg font-semibold text-primary">Belastung, Signale und letzte Aktivität</h2>
                    </div>
                    <span class="rounded-full border border-border px-3 py-1 text-xs font-semibold text-secondary">{{ athleteCockpit.length }} Profile</span>
                </div>
                <div class="mt-4 grid gap-3 lg:grid-cols-2">
                    <article v-for="entry in athleteCockpit.slice(0, 6)" :key="entry.athlete.id || entry.athlete.name" class="rounded-xl border border-border bg-inputBg/40 p-3">
                        <div class="flex items-start justify-between gap-3">
                            <div class="min-w-0">
                                <p class="truncate text-sm font-semibold text-primary">{{ entry.athlete.name }}</p>
                                <p class="mt-1 text-xs text-secondary">{{ entry.latest?.title || 'Keine letzte Einheit' }} · {{ formatDate(entry.latest?.performed_at || entry.latest?.created_at) }}</p>
                            </div>
                            <span class="rounded-full bg-muted px-2 py-1 text-xs font-semibold text-secondary">{{ entry.sessions }} Logs</span>
                        </div>
                        <div class="mt-3 grid grid-cols-4 gap-2 text-center text-xs">
                            <span class="rounded-lg border border-border px-2 py-1"><b class="block text-primary">{{ formatDuration(entry.minutes) }}</b>Zeit</span>
                            <span class="rounded-lg border border-border px-2 py-1"><b class="block text-primary">{{ formatDistance(entry.meters) }}</b>Distanz</span>
                            <span class="rounded-lg border border-border px-2 py-1"><b class="block text-primary">{{ entry.avgRpe }}</b>RPE</span>
                            <span class="rounded-lg border border-border px-2 py-1"><b class="block text-primary">{{ entry.avgPain }}</b>Schmerz</span>
                        </div>
                    </article>
                    <p v-if="!athleteCockpit.length" class="text-sm text-secondary">Noch keine dokumentierten Einheiten für das Cockpit.</p>
                </div>
            </div>

            <div class="rounded-2xl border border-air-blue/30 bg-air-blue/10 p-4">
                <div class="flex items-center justify-between gap-3">
                    <div>
                        <p class="text-xs font-semibold uppercase tracking-wide text-air-blue">KI-Coach</p>
                        <h2 class="mt-1 text-lg font-semibold text-primary">Tipps aus generierten Plänen</h2>
                    </div>
                    <span class="rounded-full border border-air-blue/30 px-3 py-1 text-xs font-semibold text-air-blue">{{ aiGeneratedPlans.length }} Pläne</span>
                </div>
                <div class="mt-4 space-y-2">
                    <article v-for="insight in aiGeneratedPlanInsights" :key="`${insight.plan.id}-${insight.label}-${insight.text}`" class="rounded-xl border border-air-blue/25 bg-bg/50 p-3">
                        <div class="flex items-start gap-3">
                            <span class="mt-0.5 flex h-8 w-8 shrink-0 items-center justify-center rounded-lg bg-air-blue text-white">
                                <i class="las la-lightbulb"></i>
                            </span>
                            <div class="min-w-0">
                                <p class="text-xs font-semibold uppercase tracking-wide text-air-blue">{{ insight.label }} · {{ insight.plan.title }}</p>
                                <p class="mt-1 text-sm text-primary">{{ insight.text }}</p>
                            </div>
                        </div>
                    </article>
                    <p v-if="!aiGeneratedPlanInsights.length" class="text-sm text-secondary">Sobald ein KI-Plan gespeichert ist, erscheinen hier konkrete Analyse- und Verbesserungsvorschläge.</p>
                </div>
            </div>

            <div class="rounded-2xl border border-border bg-card p-4">
                <p class="text-xs font-semibold uppercase tracking-wide text-air-blue">Statistik</p>
                <h2 class="mt-1 text-lg font-semibold text-primary">Sportarten-Verteilung</h2>
                <div class="mt-4 space-y-3">
                    <div v-for="row in sportStats.slice(0, 6)" :key="row.key">
                        <div class="flex items-center justify-between text-xs font-semibold">
                            <span class="text-primary">{{ row.label }}</span>
                            <span class="text-secondary">{{ row.sessions }} · {{ formatDuration(row.minutes) }}</span>
                        </div>
                        <div class="mt-1 h-2 overflow-hidden rounded-full bg-muted">
                            <div class="h-full rounded-full bg-air-blue" :style="{ width: `${Math.min(100, row.sessions * 18)}%` }"></div>
                        </div>
                    </div>
                    <p v-if="!sportStats.length" class="text-sm text-secondary">Sobald Trainings gespeichert sind, erscheinen hier Trends.</p>
                </div>
            </div>
        </section>
</template>


