<script setup>
import AppLayout from '@/Components/Auth/Layouts/AppLayout.vue'
import { Head, Link, useForm } from '@inertiajs/vue3'
import { computed } from 'vue'

defineOptions({ layout: AppLayout })

const props = defineProps({
    log: { type: Object, required: true },
})

const feedbackForm = useForm({
    body: '',
})

const statusLabels = {
    draft: 'Entwurf',
    planned: 'Geplant',
    in_progress: 'Laeuft gerade',
    completed: 'Abgeschlossen',
}

const formatDateTime = (value) => {
    if (!value) return '-'

    return new Intl.DateTimeFormat('de-DE', {
        day: '2-digit',
        month: '2-digit',
        year: 'numeric',
        hour: '2-digit',
        minute: '2-digit',
    }).format(new Date(value))
}

const formatNumber = (value, digits = 0) => {
    if (value === null || value === undefined || value === '') return '-'

    return Number(value).toLocaleString('de-DE', {
        minimumFractionDigits: digits,
        maximumFractionDigits: digits,
    })
}

const formatDistance = (meters) => {
    if (!meters) return '-'

    return `${formatNumber(Number(meters) / 1000, 2)} km`
}

const formatDuration = (seconds) => {
    if (!seconds) return '-'

    const minutes = Math.round(Number(seconds) / 60)

    return `${minutes} min`
}

const formatMinutes = (minutes) => {
    if (minutes === null || minutes === undefined || minutes === '') return '-'

    return `${formatNumber(minutes)} min`
}

const formatDeltaMinutes = (minutes) => {
    if (minutes === null || minutes === undefined) return '-'
    if (Number(minutes) === 0) return 'genau'

    return `${Number(minutes) > 0 ? '+' : ''}${formatNumber(minutes)} min`
}

const formatDeltaDistance = (meters) => {
    if (meters === null || meters === undefined) return '-'
    if (Number(meters) === 0) return 'genau'

    return `${Number(meters) > 0 ? '+' : ''}${formatNumber(Number(meters) / 1000, 2)} km`
}

const formatDeltaNumber = (value) => {
    if (value === null || value === undefined) return '-'
    if (Number(value) === 0) return 'genau'

    return `${Number(value) > 0 ? '+' : ''}${formatNumber(value)}`
}

const deltaClass = (value) => {
    if (value === null || value === undefined || Number(value) === 0) return 'text-secondary'

    return Number(value) > 0 ? 'text-success' : 'text-warning'
}

const roleLabels = {
    athlete: 'Sportler',
    trainer: 'Trainer',
    team_staff: 'Team',
    admin: 'Admin',
}

const submitFeedback = () => {
    feedbackForm.post(route('auth.training.logs.feedback.store', props.log.id), {
        preserveScroll: true,
        onSuccess: () => feedbackForm.reset(),
    })
}

const exerciseTitle = (title) => (title || '').replace(/\s+-\s+Satz\s+\d+$/i, '').trim()
const isSetEntry = (entry) => /\s+-\s+Satz\s+\d+$/i.test(entry.title || '')

const groupedGymEntries = computed(() => {
    const groups = []
    const byTitle = new Map()

    ;(props.log.entries || []).forEach((entry) => {
        if (!isSetEntry(entry)) {
            groups.push({ title: entry.title, entries: [entry], isSetGroup: false })
            return
        }

        const title = exerciseTitle(entry.title)
        if (!byTitle.has(title)) {
            byTitle.set(title, { title, entries: [], isSetGroup: true })
            groups.push(byTitle.get(title))
        }

        byTitle.get(title).entries.push(entry)
    })

    return groups
})

const metrics = computed(() => [
    { label: 'Zeitpunkt', value: formatDateTime(props.log.performed_at) },
    { label: 'Dauer', value: props.log.duration_minutes ? `${props.log.duration_minutes} min` : '-' },
    { label: 'Distanz', value: formatDistance(props.log.distance_meters) },
    { label: 'Kalorien', value: props.log.calories ? formatNumber(props.log.calories) : '-' },
    { label: 'Intensitaet', value: props.log.intensity || '-' },
])

const comparisonRows = computed(() => {
    const comparison = props.log.plan_comparison
    if (!comparison) return []

    return [
        {
            label: 'Dauer',
            planned: formatMinutes(comparison.planned?.duration_minutes),
            actual: formatMinutes(comparison.actual?.duration_minutes),
            delta: formatDeltaMinutes(comparison.delta?.duration_minutes),
            deltaValue: comparison.delta?.duration_minutes,
        },
        {
            label: 'Distanz',
            planned: formatDistance(comparison.planned?.distance_meters),
            actual: formatDistance(comparison.actual?.distance_meters),
            delta: formatDeltaDistance(comparison.delta?.distance_meters),
            deltaValue: comparison.delta?.distance_meters,
        },
        {
            label: 'Kalorien',
            planned: comparison.planned?.calories ? formatNumber(comparison.planned.calories) : '-',
            actual: comparison.actual?.calories ? formatNumber(comparison.actual.calories) : '-',
            delta: formatDeltaNumber(comparison.delta?.calories),
            deltaValue: comparison.delta?.calories,
        },
        {
            label: 'Intensitaet',
            planned: comparison.planned?.intensity || '-',
            actual: comparison.actual?.intensity || '-',
            delta: comparison.planned?.intensity && comparison.actual?.intensity && comparison.planned.intensity === comparison.actual.intensity ? 'gleich' : '-',
            deltaValue: null,
        },
    ]
})
</script>

<template>
    <Head :title="log.title" />

    <div class="space-y-5">
        <section
            v-if="$page.props.flash?.success || $page.props.flash?.error"
            class="rounded-xl border px-4 py-3 text-sm font-semibold"
            :class="$page.props.flash?.success ? 'border-success/40 bg-success/10 text-success' : 'border-danger/40 bg-danger/10 text-danger'"
        >
            {{ $page.props.flash?.success || $page.props.flash?.error }}
        </section>

        <section class="rounded-2xl border border-border bg-card p-5">
            <div class="flex flex-wrap items-start justify-between gap-4">
                <div class="min-w-0">
                    <p class="text-xs font-semibold uppercase tracking-wide text-air-blue">Trainingseinheit</p>
                    <h1 class="mt-1 text-2xl font-semibold text-primary">{{ log.title }}</h1>
                    <div class="mt-3 flex flex-wrap items-center gap-2 text-sm text-secondary">
                        <span class="rounded-full border border-border px-3 py-1 font-semibold text-primary">{{ statusLabels[log.status] || log.status }}</span>
                        <span v-if="log.athlete">Sportler: {{ log.athlete.name }}</span>
                        <span v-if="log.team">Team: {{ log.team.name }}</span>
                        <span v-if="log.sport_type">Sportart: {{ log.sport_type }}</span>
                    </div>
                </div>
                <div class="flex flex-wrap gap-2">
                    <Link :href="route('auth.training.logs.create')" class="rounded-xl bg-buttonPrimary px-4 py-2 text-sm font-semibold text-buttonTextPrimary">
                        Neue Einheit
                    </Link>
                    <Link :href="route('auth.training.index')" class="rounded-xl border border-border px-4 py-2 text-sm font-semibold text-primary hover:bg-muted">
                        Zur Uebersicht
                    </Link>
                </div>
            </div>
        </section>

        <section class="grid gap-3 sm:grid-cols-2 xl:grid-cols-5">
            <article v-for="metric in metrics" :key="metric.label" class="rounded-2xl border border-border bg-card p-4">
                <p class="text-xs font-semibold uppercase tracking-wide text-secondary">{{ metric.label }}</p>
                <p class="mt-2 text-lg font-semibold text-primary">{{ metric.value }}</p>
            </article>
        </section>

        <section v-if="log.plan_comparison" class="rounded-2xl border border-border bg-card">
            <div class="border-b border-border p-5">
                <p class="text-xs font-semibold uppercase tracking-wide text-secondary">Plan vs. Ist</p>
                <h2 class="mt-1 text-xl font-semibold text-primary">Geplante Einheit mit Ausfuehrung vergleichen</h2>
            </div>
            <div class="overflow-x-auto">
                <table class="w-full min-w-[680px] text-left text-sm">
                    <thead class="border-b border-border text-xs uppercase tracking-wide text-secondary">
                        <tr>
                            <th class="px-5 py-3">Wert</th>
                            <th class="px-5 py-3">Geplant</th>
                            <th class="px-5 py-3">Gemacht</th>
                            <th class="px-5 py-3">Abweichung</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-border">
                        <tr v-for="row in comparisonRows" :key="row.label">
                            <td class="px-5 py-3 font-semibold text-primary">{{ row.label }}</td>
                            <td class="px-5 py-3 text-secondary">{{ row.planned }}</td>
                            <td class="px-5 py-3 text-primary">{{ row.actual }}</td>
                            <td class="px-5 py-3 font-semibold" :class="deltaClass(row.deltaValue)">{{ row.delta }}</td>
                        </tr>
                    </tbody>
                </table>
            </div>
            <div class="grid gap-3 border-t border-border p-5 md:grid-cols-3">
                <div class="rounded-xl border border-border bg-inputBg/40 p-3">
                    <p class="text-xs font-semibold uppercase tracking-wide text-secondary">Geplanter Termin</p>
                    <p class="mt-1 text-sm font-semibold text-primary">{{ formatDateTime(log.plan_comparison.planned?.scheduled_at) }}</p>
                </div>
                <div class="rounded-xl border border-border bg-inputBg/40 p-3">
                    <p class="text-xs font-semibold uppercase tracking-wide text-secondary">Dokumentiert</p>
                    <p class="mt-1 text-sm font-semibold text-primary">{{ formatDateTime(log.plan_comparison.actual?.performed_at) }}</p>
                </div>
                <div class="rounded-xl border border-border bg-inputBg/40 p-3">
                    <p class="text-xs font-semibold uppercase tracking-wide text-secondary">Details</p>
                    <p class="mt-1 text-sm font-semibold text-primary">{{ log.plan_comparison.actual?.entries_count ?? 0 }} Eintraege</p>
                </div>
            </div>
        </section>

        <section class="rounded-2xl border border-border bg-card">
            <div class="border-b border-border p-5">
                <p class="text-xs font-semibold uppercase tracking-wide text-secondary">Dokumentation</p>
                <h2 class="mt-1 text-xl font-semibold text-primary">Uebungen und Werte</h2>
            </div>

            <div v-if="groupedGymEntries.length" class="space-y-4 p-5">
                <article v-for="group in groupedGymEntries" :key="group.title" class="rounded-2xl border border-border bg-inputBg/40 p-4">
                    <h3 class="text-base font-semibold text-primary">{{ group.title }}</h3>
                    <div class="mt-3 grid gap-3">
                        <div
                            v-for="(entry, index) in group.entries"
                            :key="entry.id || index"
                            class="grid gap-3 rounded-xl border border-border bg-card p-3 sm:grid-cols-2 lg:grid-cols-6"
                        >
                            <p class="text-sm font-semibold text-primary">{{ group.isSetGroup ? `Satz ${index + 1}` : entry.title }}</p>
                            <p class="text-sm text-secondary">Wdh.: <span class="font-semibold text-primary">{{ entry.reps || '-' }}</span></p>
                            <p class="text-sm text-secondary">Gewicht: <span class="font-semibold text-primary">{{ entry.weight_kg ? `${formatNumber(entry.weight_kg, 2)} kg` : '-' }}</span></p>
                            <p class="text-sm text-secondary">Zeit: <span class="font-semibold text-primary">{{ formatDuration(entry.duration_seconds) }}</span></p>
                            <p class="text-sm text-secondary">Distanz: <span class="font-semibold text-primary">{{ formatDistance(entry.distance_meters) }}</span></p>
                            <p class="text-sm text-secondary">Intensitaet: <span class="font-semibold text-primary">{{ entry.intensity || '-' }}</span></p>
                            <p v-if="entry.notes" class="text-sm text-secondary sm:col-span-2 lg:col-span-6">{{ entry.notes }}</p>
                            <a v-if="entry.metrics?.media_url" :href="entry.metrics.media_url" target="_blank" class="text-sm font-semibold text-air-blue underline sm:col-span-2 lg:col-span-6">
                                Medien ansehen
                            </a>
                        </div>
                    </div>
                </article>
            </div>

            <p v-else class="p-5 text-sm text-secondary">Keine Detailwerte hinterlegt.</p>
        </section>

        <section v-if="log.notes || log.trainer_feedback || log.plan || log.plan_item" class="grid gap-4 lg:grid-cols-2">
            <article v-if="log.notes" class="rounded-2xl border border-border bg-card p-5">
                <p class="text-xs font-semibold uppercase tracking-wide text-secondary">Notizen</p>
                <p class="mt-2 whitespace-pre-line text-sm leading-6 text-primary">{{ log.notes }}</p>
            </article>
            <article v-if="log.trainer_feedback" class="rounded-2xl border border-border bg-card p-5">
                <p class="text-xs font-semibold uppercase tracking-wide text-secondary">Trainer-Hinweis</p>
                <p class="mt-2 whitespace-pre-line text-sm leading-6 text-primary">{{ log.trainer_feedback }}</p>
            </article>
            <article v-if="log.metrics?.wellness" class="rounded-2xl border border-border bg-card p-5">
                <p class="text-xs font-semibold uppercase tracking-wide text-secondary">Belastung & Zustand</p>
                <div class="mt-3 grid grid-cols-2 gap-2 text-sm">
                    <p class="rounded-xl border border-border bg-inputBg/40 p-2 text-secondary">RPE <span class="font-semibold text-primary">{{ log.metrics.wellness.rpe || '-' }}</span></p>
                    <p class="rounded-xl border border-border bg-inputBg/40 p-2 text-secondary">Energie <span class="font-semibold text-primary">{{ log.metrics.wellness.energy || '-' }}</span></p>
                    <p class="rounded-xl border border-border bg-inputBg/40 p-2 text-secondary">Schmerz <span class="font-semibold text-primary">{{ log.metrics.wellness.pain ?? '-' }}</span></p>
                    <p class="rounded-xl border border-border bg-inputBg/40 p-2 text-secondary">Schlaf <span class="font-semibold text-primary">{{ log.metrics.wellness.sleep_hours || '-' }}</span></p>
                </div>
            </article>
            <article v-if="log.plan || log.plan_item" class="rounded-2xl border border-border bg-card p-5">
                <p class="text-xs font-semibold uppercase tracking-wide text-secondary">Planbezug</p>
                <p v-if="log.plan" class="mt-2 text-sm text-primary">{{ log.plan.title }}</p>
                <p v-if="log.plan_item" class="mt-1 text-sm text-secondary">{{ log.plan_item.title }}</p>
            </article>
        </section>

        <section class="rounded-2xl border border-border bg-card">
            <div class="border-b border-border p-5">
                <p class="text-xs font-semibold uppercase tracking-wide text-secondary">Feedback</p>
                <h2 class="mt-1 text-xl font-semibold text-primary">Verlauf zwischen Sportler und Trainer</h2>
            </div>

            <div class="space-y-3 p-5">
                <article
                    v-for="feedback in log.feedbacks"
                    :key="feedback.id"
                    class="rounded-2xl border border-border bg-inputBg/40 p-4"
                >
                    <div class="flex flex-wrap items-start justify-between gap-2">
                        <div>
                            <p class="text-sm font-semibold text-primary">{{ feedback.author?.name || 'Unbekannt' }}</p>
                            <p class="mt-1 text-xs text-secondary">
                                {{ roleLabels[feedback.role] || feedback.role || 'Feedback' }} · {{ formatDateTime(feedback.created_at) }}
                            </p>
                        </div>
                    </div>
                    <p class="mt-3 whitespace-pre-line text-sm leading-6 text-primary">{{ feedback.body }}</p>
                </article>

                <p v-if="!log.feedbacks?.length" class="rounded-xl border border-dashed border-border p-4 text-sm text-secondary">
                    Noch kein Feedback vorhanden. Schreibe die erste Rueckmeldung direkt zur Trainingseinheit.
                </p>

                <form class="rounded-2xl border border-border bg-inputBg/40 p-4" @submit.prevent="submitFeedback">
                    <label class="block text-sm font-semibold text-primary">Antwort schreiben
                        <textarea
                            v-model="feedbackForm.body"
                            rows="4"
                            class="mt-2 w-full rounded-xl border border-border bg-inputBg px-3 py-2 text-primary"
                            placeholder="Feedback, Rueckfrage, Technik-Hinweis oder naechster Fokus"
                            required
                        />
                    </label>
                    <p v-if="feedbackForm.errors.body" class="mt-2 text-sm font-semibold text-danger">{{ feedbackForm.errors.body }}</p>
                    <div class="mt-3 flex justify-end">
                        <button type="submit" class="rounded-xl bg-buttonPrimary px-4 py-2 text-sm font-semibold text-buttonTextPrimary disabled:opacity-60" :disabled="feedbackForm.processing">
                            Feedback senden
                        </button>
                    </div>
                </form>
            </div>
        </section>
    </div>
</template>
