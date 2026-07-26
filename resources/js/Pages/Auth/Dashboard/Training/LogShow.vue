<script setup>
import AppLayout from '@/Components/Auth/Layouts/AppLayout.vue'
import { Head, Link, useForm } from '@inertiajs/vue3'
import { computed } from 'vue'
import { useI18n } from 'vue-i18n'

defineOptions({ layout: AppLayout })

const { t, locale } = useI18n()
const tx = (value, params = {}) => t(value, params)

const props = defineProps({
    log: { type: Object, required: true },
})

const feedbackForm = useForm({
    body: '',
})

const statusLabels = {
    draft: tx('training_log.status.draft'),
    planned: tx('training_log.status.planned'),
    in_progress: tx('training_log.status.in_progress'),
    completed: tx('training_log.status.completed'),
}

const formatDateTime = (value) => {
    if (!value) return '-'

    return new Intl.DateTimeFormat(locale.value === 'ar' ? 'ar' : (locale.value === 'fr' ? 'fr-FR' : (locale.value === 'en' ? 'en-US' : 'de-DE')), {
        day: '2-digit',
        month: '2-digit',
        year: 'numeric',
        hour: '2-digit',
        minute: '2-digit',
    }).format(new Date(value))
}

const formatNumber = (value, digits = 0) => {
    if (value === null || value === undefined || value === '') return '-'

    return Number(value).toLocaleString(locale.value === 'ar' ? 'ar-EG' : (locale.value === 'fr' ? 'fr-FR' : (locale.value === 'en' ? 'en-US' : 'de-DE')), {
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
    if (Number(minutes) === 0) return tx('training_log.exact')

    return `${Number(minutes) > 0 ? '+' : ''}${formatNumber(minutes)} min`
}

const formatDeltaDistance = (meters) => {
    if (meters === null || meters === undefined) return '-'
    if (Number(meters) === 0) return tx('training_log.exact')

    return `${Number(meters) > 0 ? '+' : ''}${formatNumber(Number(meters) / 1000, 2)} km`
}

const formatDeltaNumber = (value) => {
    if (value === null || value === undefined) return '-'
    if (Number(value) === 0) return tx('training_log.exact')

    return `${Number(value) > 0 ? '+' : ''}${formatNumber(value)}`
}

const deltaClass = (value) => {
    if (value === null || value === undefined || Number(value) === 0) return 'text-secondary'

    return Number(value) > 0 ? 'text-success' : 'text-warning'
}

const roleLabels = {
    athlete: tx('training_log.roles.athlete'),
    trainer: tx('training_log.roles.trainer'),
    team_staff: tx('training_log.roles.team_staff'),
    admin: tx('training_log.roles.admin'),
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
        { label: tx('training_log.metrics.time'), value: formatDateTime(props.log.performed_at) },
        { label: tx('training_log.metrics.duration'), value: props.log.duration_minutes ? `${props.log.duration_minutes} min` : '-' },
        { label: tx('training_log.metrics.distance'), value: formatDistance(props.log.distance_meters) },
        { label: tx('training_log.metrics.calories'), value: props.log.calories ? formatNumber(props.log.calories) : '-' },
        { label: tx('training_log.metrics.intensity'), value: props.log.intensity || '-' },
])

const comparisonRows = computed(() => {
    const comparison = props.log.plan_comparison
    if (!comparison) return []

    return [
        {
            label: tx('training_log.metrics.duration'),
            planned: formatMinutes(comparison.planned?.duration_minutes),
            actual: formatMinutes(comparison.actual?.duration_minutes),
            delta: formatDeltaMinutes(comparison.delta?.duration_minutes),
            deltaValue: comparison.delta?.duration_minutes,
        },
        {
            label: tx('training_log.metrics.distance'),
            planned: formatDistance(comparison.planned?.distance_meters),
            actual: formatDistance(comparison.actual?.distance_meters),
            delta: formatDeltaDistance(comparison.delta?.distance_meters),
            deltaValue: comparison.delta?.distance_meters,
        },
        {
            label: tx('training_log.metrics.calories'),
            planned: comparison.planned?.calories ? formatNumber(comparison.planned.calories) : '-',
            actual: comparison.actual?.calories ? formatNumber(comparison.actual.calories) : '-',
            delta: formatDeltaNumber(comparison.delta?.calories),
            deltaValue: comparison.delta?.calories,
        },
        {
            label: tx('training_log.metrics.intensity'),
            planned: comparison.planned?.intensity || '-',
            actual: comparison.actual?.intensity || '-',
            delta: comparison.planned?.intensity && comparison.actual?.intensity && comparison.planned.intensity === comparison.actual.intensity ? tx('training_log.equal') : '-',
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
                    <p class="text-xs font-semibold uppercase tracking-wide text-air-blue">{{ tx('training_log.title') }}</p>
                    <h1 class="mt-1 text-2xl font-semibold text-primary">{{ log.title }}</h1>
                    <div class="mt-3 flex flex-wrap items-center gap-2 text-sm text-secondary">
                        <span class="rounded-full border border-border px-3 py-1 font-semibold text-primary">{{ statusLabels[log.status] || log.status }}</span>
                        <span v-if="log.athlete">{{ tx('training_log.labels.athlete') }} {{ log.athlete.name }}</span>
                        <span v-if="log.team">{{ tx('training_log.labels.team') }} {{ log.team.name }}</span>
                        <span v-if="log.sport_type">{{ tx('training_log.labels.sport') }} {{ log.sport_type }}</span>
                    </div>
                </div>
                <div class="flex flex-wrap gap-2">
                    <Link :href="route('auth.training.logs.create')" class="rounded-xl bg-buttonPrimary px-4 py-2 text-sm font-semibold text-buttonTextPrimary">
                        {{ tx('training_log.actions.new') }}
                    </Link>
                    <Link :href="route('auth.training.index')" class="rounded-xl border border-border px-4 py-2 text-sm font-semibold text-primary hover:bg-muted">
                        {{ tx('training_log.actions.overview') }}
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
                <p class="text-xs font-semibold uppercase tracking-wide text-secondary">{{ tx('training_log.comparison.eyebrow') }}</p>
                <h2 class="mt-1 text-xl font-semibold text-primary">{{ tx('training_log.comparison.title') }}</h2>
            </div>
            <div class="overflow-x-auto">
                <table class="w-full min-w-[680px] text-left text-sm">
                    <thead class="border-b border-border text-xs uppercase tracking-wide text-secondary">
                        <tr>
                            <th class="px-5 py-3">{{ tx('training_log.comparison.value') }}</th>
                            <th class="px-5 py-3">{{ tx('training_log.comparison.planned') }}</th>
                            <th class="px-5 py-3">{{ tx('training_log.comparison.actual') }}</th>
                            <th class="px-5 py-3">{{ tx('training_log.comparison.delta') }}</th>
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
                    <p class="text-xs font-semibold uppercase tracking-wide text-secondary">{{ tx('training_log.comparison.scheduled') }}</p>
                    <p class="mt-1 text-sm font-semibold text-primary">{{ formatDateTime(log.plan_comparison.planned?.scheduled_at) }}</p>
                </div>
                <div class="rounded-xl border border-border bg-inputBg/40 p-3">
                    <p class="text-xs font-semibold uppercase tracking-wide text-secondary">{{ tx('training_log.comparison.documented') }}</p>
                    <p class="mt-1 text-sm font-semibold text-primary">{{ formatDateTime(log.plan_comparison.actual?.performed_at) }}</p>
                </div>
                <div class="rounded-xl border border-border bg-inputBg/40 p-3">
                    <p class="text-xs font-semibold uppercase tracking-wide text-secondary">{{ tx('training_log.comparison.details') }}</p>
                    <p class="mt-1 text-sm font-semibold text-primary">{{ log.plan_comparison.actual?.entries_count ?? 0 }} {{ tx('training_log.entries') }}</p>
                </div>
            </div>
        </section>

        <section class="rounded-2xl border border-border bg-card">
            <div class="border-b border-border p-5">
                <p class="text-xs font-semibold uppercase tracking-wide text-secondary">{{ tx('training_log.documentation') }}</p>
                <h2 class="mt-1 text-xl font-semibold text-primary">{{ tx('training_log.exercises') }}</h2>
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
                            <p class="text-sm font-semibold text-primary">{{ group.isSetGroup ? `${tx('training_log.set')} ${index + 1}` : entry.title }}</p>
                            <p class="text-sm text-secondary">{{ tx('training_log.labels.reps') }} <span class="font-semibold text-primary">{{ entry.reps || '-' }}</span></p>
                            <p class="text-sm text-secondary">{{ tx('training_log.labels.weight') }} <span class="font-semibold text-primary">{{ entry.weight_kg ? `${formatNumber(entry.weight_kg, 2)} kg` : '-' }}</span></p>
                            <p class="text-sm text-secondary">{{ tx('training_log.labels.time') }} <span class="font-semibold text-primary">{{ formatDuration(entry.duration_seconds) }}</span></p>
                            <p class="text-sm text-secondary">{{ tx('training_log.labels.distance') }} <span class="font-semibold text-primary">{{ formatDistance(entry.distance_meters) }}</span></p>
                            <p class="text-sm text-secondary">{{ tx('training_log.labels.intensity') }} <span class="font-semibold text-primary">{{ entry.intensity || '-' }}</span></p>
                            <p v-if="entry.notes" class="text-sm text-secondary sm:col-span-2 lg:col-span-6">{{ entry.notes }}</p>
                            <a v-if="entry.metrics?.media_url" :href="entry.metrics.media_url" target="_blank" class="text-sm font-semibold text-air-blue underline sm:col-span-2 lg:col-span-6">
                                {{ tx('training_log.media') }}
                            </a>
                        </div>
                    </div>
                </article>
            </div>

            <p v-else class="p-5 text-sm text-secondary">{{ tx('training_log.no_details') }}</p>
        </section>

        <section v-if="log.notes || log.trainer_feedback || log.plan || log.plan_item" class="grid gap-4 lg:grid-cols-2">
            <article v-if="log.notes" class="rounded-2xl border border-border bg-card p-5">
                <p class="text-xs font-semibold uppercase tracking-wide text-secondary">{{ tx('training_log.notes') }}</p>
                <p class="mt-2 whitespace-pre-line text-sm leading-6 text-primary">{{ log.notes }}</p>
            </article>
            <article v-if="log.trainer_feedback" class="rounded-2xl border border-border bg-card p-5">
                <p class="text-xs font-semibold uppercase tracking-wide text-secondary">{{ tx('training_log.trainer_note') }}</p>
                <p class="mt-2 whitespace-pre-line text-sm leading-6 text-primary">{{ log.trainer_feedback }}</p>
            </article>
            <article v-if="log.metrics?.wellness" class="rounded-2xl border border-border bg-card p-5">
                <p class="text-xs font-semibold uppercase tracking-wide text-secondary">{{ tx('training_log.wellness.title') }}</p>
                <div class="mt-3 grid grid-cols-2 gap-2 text-sm">
                    <p class="rounded-xl border border-border bg-inputBg/40 p-2 text-secondary">{{ tx('training_log.wellness.rpe') }} <span class="font-semibold text-primary">{{ log.metrics.wellness.rpe || '-' }}</span></p>
                    <p class="rounded-xl border border-border bg-inputBg/40 p-2 text-secondary">{{ tx('training_log.wellness.energy') }} <span class="font-semibold text-primary">{{ log.metrics.wellness.energy || '-' }}</span></p>
                    <p class="rounded-xl border border-border bg-inputBg/40 p-2 text-secondary">{{ tx('training_log.wellness.pain') }} <span class="font-semibold text-primary">{{ log.metrics.wellness.pain ?? '-' }}</span></p>
                    <p class="rounded-xl border border-border bg-inputBg/40 p-2 text-secondary">{{ tx('training_log.wellness.sleep') }} <span class="font-semibold text-primary">{{ log.metrics.wellness.sleep_hours || '-' }}</span></p>
                </div>
            </article>
            <article v-if="log.plan || log.plan_item" class="rounded-2xl border border-border bg-card p-5">
                <p class="text-xs font-semibold uppercase tracking-wide text-secondary">{{ tx('training_log.plan_reference') }}</p>
                <p v-if="log.plan" class="mt-2 text-sm text-primary">{{ log.plan.title }}</p>
                <p v-if="log.plan_item" class="mt-1 text-sm text-secondary">{{ log.plan_item.title }}</p>
            </article>
        </section>

        <section class="rounded-2xl border border-border bg-card">
            <div class="border-b border-border p-5">
                <p class="text-xs font-semibold uppercase tracking-wide text-secondary">{{ tx('training_log.feedback') }}</p>
                <h2 class="mt-1 text-xl font-semibold text-primary">{{ tx('training_log.feedback_title') }}</h2>
            </div>

            <div class="space-y-3 p-5">
                <article
                    v-for="feedback in log.feedbacks"
                    :key="feedback.id"
                    class="rounded-2xl border border-border bg-inputBg/40 p-4"
                >
                    <div class="flex flex-wrap items-start justify-between gap-2">
                        <div>
                            <p class="text-sm font-semibold text-primary">{{ feedback.author?.name || tx('training_log.unknown') }}</p>
                            <p class="mt-1 text-xs text-secondary">
                                {{ roleLabels[feedback.role] || feedback.role || tx('training_log.feedback') }} · {{ formatDateTime(feedback.created_at) }}
                            </p>
                        </div>
                    </div>
                    <p class="mt-3 whitespace-pre-line text-sm leading-6 text-primary">{{ feedback.body }}</p>
                </article>

                <p v-if="!log.feedbacks?.length" class="rounded-xl border border-dashed border-border p-4 text-sm text-secondary">
                    {{ tx('training_log.no_feedback') }}
                </p>

                <form class="rounded-2xl border border-border bg-inputBg/40 p-4" @submit.prevent="submitFeedback">
                    <label class="block text-sm font-semibold text-primary">{{ tx('training_log.reply') }}
                        <textarea
                            v-model="feedbackForm.body"
                            rows="4"
                            class="mt-2 w-full rounded-xl border border-border bg-inputBg px-3 py-2 text-primary"
                            :placeholder="tx('training_log.placeholder')"
                            required
                        />
                    </label>
                    <p v-if="feedbackForm.errors.body" class="mt-2 text-sm font-semibold text-danger">{{ feedbackForm.errors.body }}</p>
                    <div class="mt-3 flex justify-end">
                        <button type="submit" class="rounded-xl bg-buttonPrimary px-4 py-2 text-sm font-semibold text-buttonTextPrimary disabled:opacity-60" :disabled="feedbackForm.processing">
                            {{ tx('training_log.send') }}
                        </button>
                    </div>
                </form>
            </div>
        </section>
    </div>
</template>
