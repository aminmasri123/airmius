<script setup>
defineProps({
    selectedCourse: { type: Object, required: true },
    activePanel: { type: String, required: true },
    statusLabel: { type: Function, required: true },
    formatMoney: { type: Function, required: true },
    formatMinutes: { type: Function, required: true },
    formatPercent: { type: Function, required: true },
})

defineEmits(['update:activePanel'])

const panels = [
    ['structure', 'Struktur', 'las la-list'],
    ['details', 'Kursdaten', 'las la-sliders-h'],
    ['sales', 'Landingpage', 'las la-bullhorn'],
    ['quiz', 'Quiz', 'las la-question-circle'],
    ['assignments', 'Aufgaben', 'las la-clipboard-check'],
    ['students', 'Teilnehmer', 'las la-users'],
    ['questions', 'Fragen', 'las la-comments'],
]
</script>

<template>
    <article class="surface-card overflow-hidden">
        <div class="grid gap-4 p-5 lg:grid-cols-[minmax(0,1fr)_16rem]">
            <div class="min-w-0">
                <p class="text-xs font-semibold uppercase tracking-wide text-air-blue">Aktueller Kurs</p>
                <h2 class="mt-1 break-words text-2xl font-bold text-primary">{{ selectedCourse.title }}</h2>
                <p class="mt-2 text-sm text-secondary">
                    {{ selectedCourse.subtitle || selectedCourse.description || 'Beschreibe den Kurs, damit Sportler sofort wissen, was sie lernen.' }}
                </p>
            </div>
            <div class="rounded-lg border border-border bg-bg p-3">
                <p class="text-xs uppercase text-secondary">Status</p>
                <p class="mt-1 text-lg font-bold text-primary">{{ statusLabel(selectedCourse.status) }}</p>
                <p class="mt-2 text-xs text-secondary">
                    {{ selectedCourse.is_free ? 'Kostenlos' : formatMoney(selectedCourse.price_cents, selectedCourse.currency) }} - {{ formatMinutes(selectedCourse.estimated_minutes) }}
                </p>
                <a v-if="selectedCourse.preview_url" :href="selectedCourse.preview_url" target="_blank" class="mt-3 inline-flex rounded-lg border border-border px-3 py-2 text-xs font-semibold text-primary hover:bg-muted">
                    Als Teilnehmer ansehen
                </a>
            </div>
        </div>

        <div class="grid gap-3 border-t border-border p-5 md:grid-cols-4 xl:grid-cols-10">
            <div class="rounded-lg border border-border bg-bg p-3">
                <p class="text-xs uppercase text-secondary">Durchschnitt</p>
                <p class="mt-1 text-xl font-bold text-primary">{{ selectedCourse.analytics?.average_progress || 0 }}%</p>
            </div>
            <div class="rounded-lg border border-border bg-bg p-3">
                <p class="text-xs uppercase text-secondary">Abschluesse</p>
                <p class="mt-1 text-xl font-bold text-primary">{{ selectedCourse.analytics?.completed_enrollments || 0 }}</p>
            </div>
            <div class="rounded-lg border border-border bg-bg p-3">
                <p class="text-xs uppercase text-secondary">Offene Fragen</p>
                <p class="mt-1 text-xl font-bold text-primary">{{ selectedCourse.analytics?.open_questions || 0 }}</p>
            </div>
            <div class="rounded-lg border border-border bg-bg p-3">
                <p class="text-xs uppercase text-secondary">Bewertung</p>
                <p class="mt-1 text-xl font-bold text-primary">{{ selectedCourse.analytics?.average_rating || '-' }}</p>
            </div>
            <div class="rounded-lg border border-border bg-bg p-3">
                <p class="text-xs uppercase text-secondary">VerKäufe</p>
                <p class="mt-1 text-xl font-bold text-primary">{{ selectedCourse.analytics?.sales_count || 0 }}</p>
            </div>
            <div class="rounded-lg border border-border bg-bg p-3">
                <p class="text-xs uppercase text-secondary">Umsatz netto</p>
                <p class="mt-1 text-xl font-bold text-primary">{{ formatMoney(selectedCourse.analytics?.net_revenue_cents || 0, selectedCourse.currency) }}</p>
            </div>
            <div class="rounded-lg border border-border bg-bg p-3">
                <p class="text-xs uppercase text-secondary">Offene Zahlungen</p>
                <p class="mt-1 text-xl font-bold text-primary">{{ selectedCourse.analytics?.pending_sales_count || 0 }}</p>
            </div>
            <div class="rounded-lg border border-border bg-bg p-3">
                <p class="text-xs uppercase text-secondary">Refund/Storno</p>
                <p class="mt-1 text-xl font-bold text-primary">{{ selectedCourse.analytics?.cancelled_sales_count || 0 }}</p>
            </div>
            <div class="rounded-lg border border-border bg-bg p-3">
                <p class="text-xs uppercase text-secondary">Security 24h</p>
                <p class="mt-1 text-xl font-bold text-primary">{{ selectedCourse.analytics?.security_events_24h || 0 }}</p>
            </div>
            <div class="rounded-lg border border-border bg-bg p-3">
                <p class="text-xs uppercase text-secondary">Video-Block 24h</p>
                <p class="mt-1 text-xl font-bold text-primary">{{ selectedCourse.analytics?.blocked_video_attempts_24h || 0 }}</p>
            </div>
        </div>

        <div class="border-t border-border p-5">
            <div class="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
                <div>
                    <p class="text-xs font-semibold uppercase text-secondary">Publish-Check</p>
                    <p class="mt-1 text-sm text-secondary">
                        {{ selectedCourse.publish_checklist?.done_count || 0 }} von {{ selectedCourse.publish_checklist?.total_count || 0 }} Punkten erledigt
                        ({{ selectedCourse.publish_checklist?.score ?? formatPercent(selectedCourse.publish_checklist?.done_count, selectedCourse.publish_checklist?.total_count) }}%)
                    </p>
                </div>
                <span class="rounded-full px-3 py-1 text-xs font-semibold" :class="selectedCourse.publish_checklist?.ready ? 'bg-success/10 text-success' : 'bg-warning/10 text-warning'">
                    {{ selectedCourse.publish_checklist?.ready ? 'Bereit' : 'Noch offen' }}
                </span>
            </div>
            <div class="mt-4 grid gap-2 md:grid-cols-3">
                <div v-for="item in selectedCourse.publish_checklist?.items || []" :key="item.key" class="flex items-center gap-2 rounded-lg border border-border bg-bg px-3 py-2 text-sm">
                    <i :class="item.done ? 'las la-check-circle text-success' : 'las la-circle text-secondary'"></i>
                    <span :class="item.done ? 'text-primary' : 'text-secondary'">{{ item.label }}</span>
                </div>
            </div>
            <div v-if="selectedCourse.security_events?.length" class="mt-5 rounded-lg border border-border bg-bg p-4">
                <div class="flex items-center justify-between gap-3">
                    <p class="text-xs font-semibold uppercase text-secondary">Monitoring</p>
                    <span v-if="selectedCourse.analytics?.critical_security_events_24h" class="rounded-full bg-error/10 px-2 py-1 text-xs font-semibold text-error">
                        {{ selectedCourse.analytics.critical_security_events_24h }} kritisch
                    </span>
                </div>
                <div class="mt-3 grid gap-2">
                    <div v-for="event in selectedCourse.security_events" :key="event.id" class="flex flex-wrap items-center justify-between gap-2 rounded-lg border border-border bg-card px-3 py-2 text-xs">
                        <span class="font-semibold text-primary">{{ event.type }}</span>
                        <span class="text-secondary">{{ event.lesson_title || 'Kurs' }}</span>
                        <span class="text-secondary">{{ event.user?.email || 'Unbekannt' }}</span>
                        <span :class="event.severity === 'critical' ? 'text-error' : 'text-warning'" class="font-semibold">{{ event.severity }}</span>
                    </div>
                </div>
            </div>
        </div>

        <div class="flex gap-2 overflow-x-auto border-t border-border p-2">
            <button
                v-for="panel in panels"
                :key="panel[0]"
                type="button"
                class="flex min-h-10 shrink-0 items-center gap-2 rounded-lg px-4 py-2 text-sm font-semibold transition"
                :class="activePanel === panel[0] ? 'bg-buttonPrimary text-buttonTextPrimary' : 'text-secondary hover:bg-muted hover:text-primary'"
                @click="$emit('update:activePanel', panel[0])"
            >
                <i :class="panel[2]"></i>
                <span>{{ panel[1] }}</span>
            </button>
        </div>
    </article>
</template>

