<script setup>
import { computed, ref } from 'vue'
import { Head, Link, usePage } from '@inertiajs/vue3'
import { useI18n } from 'vue-i18n'
import AppLayout from '@/Components/Auth/Layouts/AppLayout.vue'

defineOptions({ layout: AppLayout })

const props = defineProps({
    teams: { type: Array, default: () => [] },
    summary: { type: Object, default: () => ({}) },
    upcomingEvents: { type: Array, default: () => [] },
    plannedItems: { type: Array, default: () => [] },
    feedbackOpen: { type: Array, default: () => [] },
    overdueItems: { type: Array, default: () => [] },
    recentLogs: { type: Array, default: () => [] },
    plans: { type: Array, default: () => [] },
    coachWeekly: { type: Object, default: () => ({}) },
})

const page = usePage()
const { t } = useI18n()
const activeTab = ref('overview')

const locale = computed(() => page.props.locale || 'de')
const dateTimeFormat = computed(() => new Intl.DateTimeFormat(locale.value, {
    dateStyle: 'medium',
    timeStyle: 'short',
}))
const numberFormat = computed(() => new Intl.NumberFormat(locale.value))

const tabs = computed(() => [
    { key: 'overview', label: t('trainerCockpit.overview'), icon: 'las la-home' },
    { key: 'teams', label: t('trainerCockpit.teams'), icon: 'las la-users' },
    { key: 'feedback', label: t('trainerCockpit.feedback'), icon: 'las la-comment-dots' },
    { key: 'planning', label: t('trainerCockpit.planning'), icon: 'las la-calendar-check' },
])

const statCards = computed(() => [
    { key: 'readiness', label: t('trainerCockpit.readinessScore'), value: props.summary.readiness_score || 0, icon: 'las la-heartbeat', tone: 'from-rose-500/25 to-rose-500/5', suffix: '%' },
    { key: 'teams', label: t('trainerCockpit.teamCount'), value: props.summary.teams || 0, icon: 'las la-users', tone: 'from-sky-500/25 to-sky-500/5' },
    { key: 'athletes', label: t('trainerCockpit.athleteCount'), value: props.summary.athletes || 0, icon: 'las la-running', tone: 'from-emerald-500/25 to-emerald-500/5' },
    { key: 'events', label: t('trainerCockpit.upcomingEvents'), value: props.summary.upcoming_events || 0, icon: 'las la-calendar', tone: 'from-violet-500/25 to-violet-500/5' },
    { key: 'feedback', label: t('trainerCockpit.openFeedback'), value: props.summary.feedback_open || 0, icon: 'las la-comment-medical', tone: 'from-amber-500/25 to-amber-500/5' },
])

const quickActions = computed(() => [
    { label: t('trainerCockpit.planTraining'), href: route('auth.events.index'), icon: 'las la-calendar-plus' },
    { label: t('trainerCockpit.documentTraining'), href: route('auth.training.logs.create'), icon: 'las la-pen-nib' },
    { label: t('trainerCockpit.openPlans'), href: route('auth.training.index'), icon: 'las la-clipboard-list' },
    { label: t('trainerCockpit.openEvents'), href: route('auth.events.index'), icon: 'las la-calendar-day' },
])

const focusItems = computed(() => [
    {
        key: 'feedback',
        label: t('trainerCockpit.openFeedback'),
        value: props.summary.feedback_open || 0,
        text: t('trainerCockpit.feedbackHint'),
        href: '#feedback',
    },
    {
        key: 'overdue',
        label: t('trainerCockpit.overdueItems'),
        value: props.summary.overdue_items || 0,
        text: t('trainerCockpit.overdueHint'),
        href: '#planning',
    },
    {
        key: 'events',
        label: t('trainerCockpit.upcomingEvents'),
        value: props.summary.upcoming_events || 0,
        text: t('trainerCockpit.eventsHint'),
        href: route('auth.events.index'),
    },
])

const formatNumber = (value) => numberFormat.value.format(Number(value || 0))
const formatDateTime = (value) => value ? dateTimeFormat.value.format(new Date(value)) : t('trainerCockpit.notScheduled')
const formatDistance = (meters) => {
    const value = Number(meters || 0)

    if (!value) return null

    return t('trainerCockpit.kilometers', { count: numberFormat.value.format(value / 1000) })
}
const formatDuration = (minutes) => Number(minutes || 0)
    ? t('trainerCockpit.minutes', { count: formatNumber(minutes) })
    : null

const readinessLabel = (level) => t(`trainerCockpit.readiness.${level || 'empty'}`)
const readinessTone = (level) => ({
    good: 'border-emerald-400/40 bg-emerald-500/10 text-emerald-100',
    watch: 'border-amber-400/40 bg-amber-500/10 text-amber-100',
    risk: 'border-rose-400/40 bg-rose-500/10 text-rose-100',
    empty: 'border-border bg-inputBg text-secondary',
}[level || 'empty'])
const actionTone = (tone) => ({
    success: 'border-emerald-400/40 bg-emerald-500/10',
    warning: 'border-amber-400/40 bg-amber-500/10',
    danger: 'border-rose-400/40 bg-rose-500/10',
    info: 'border-sky-400/40 bg-sky-500/10',
}[tone] || 'border-border bg-inputBg')
const formatTrend = (value) => {
    const number = Number(value || 0)
    const sign = number > 0 ? '+' : ''

    return `${sign}${numberFormat.value.format(number)}%`
}

const switchTab = (tab) => {
    activeTab.value = tab
}
</script>

<template>
    <Head :title="t('Trainer-Cockpit')" />

    <div class="space-y-5">
        <section class="surface-card overflow-hidden">
            <div class="grid gap-5 p-5 lg:grid-cols-[1fr_auto] lg:items-end">
                <div>
                    <p class="text-xs font-bold uppercase tracking-wide text-buttonPrimary">{{ t('trainerCockpit.eyebrow') }}</p>
                    <h1 class="mt-2 text-2xl font-bold text-primary md:text-3xl">{{ t('trainerCockpit.title') }}</h1>
                    <p class="mt-2 max-w-3xl text-sm leading-6 text-secondary">{{ t('trainerCockpit.subtitle') }}</p>
                </div>

                <div class="grid grid-cols-3 gap-2 sm:min-w-[340px]">
                    <div class="rounded-lg border border-border bg-inputBg p-3">
                        <p class="text-2xl font-bold text-primary">{{ formatNumber(summary.readiness_score) }}%</p>
                        <p class="text-xs text-secondary">{{ t('trainerCockpit.readinessScore') }}</p>
                    </div>
                    <div class="rounded-lg border border-border bg-inputBg p-3">
                        <p class="text-2xl font-bold text-primary">{{ formatNumber(summary.risk_athletes) }}</p>
                        <p class="text-xs text-secondary">{{ t('trainerCockpit.riskAthletes') }}</p>
                    </div>
                    <div class="rounded-lg border border-border bg-inputBg p-3">
                        <p class="text-2xl font-bold text-primary">{{ formatNumber(summary.overdue_items) }}</p>
                        <p class="text-xs text-secondary">{{ t('trainerCockpit.overdueItems') }}</p>
                    </div>
                </div>
            </div>

            <div v-if="props.teams.length" class="border-t border-border p-4">
                <p class="mb-3 text-xs font-bold uppercase text-buttonPrimary">{{ t('trainerCockpit.quickActions') }}</p>
                <div class="flex gap-3 overflow-x-auto pb-1">
                    <Link
                        v-for="action in quickActions"
                        :key="action.label"
                        :href="action.href"
                        class="inline-flex min-w-[180px] items-center gap-3 rounded-lg border border-border bg-inputBg px-4 py-3 text-sm font-bold text-primary transition hover:border-borderHover"
                    >
                        <i :class="[action.icon, 'text-lg text-buttonPrimary']"></i>
                        {{ action.label }}
                    </Link>
                </div>
            </div>
        </section>

        <section v-if="!props.teams.length" class="surface-card border border-dashed border-buttonPrimary/40 p-6 text-center">
            <span class="mx-auto flex h-14 w-14 items-center justify-center rounded-full bg-buttonPrimary/10 text-buttonPrimary">
                <i class="las la-users text-2xl"></i>
            </span>
            <h2 class="mt-4 text-xl font-bold text-primary">{{ t('trainerCockpit.noTeams') }}</h2>
            <p class="mx-auto mt-2 max-w-2xl text-sm leading-6 text-secondary">{{ t('trainerCockpit.noTeamsText') }}</p>
            <Link :href="route('auth.teams.index')" class="mt-5 inline-flex min-h-11 items-center justify-center gap-2 rounded-lg bg-buttonPrimary px-5 py-3 text-sm font-bold text-buttonPrimaryText">
                <i class="las la-search"></i>
                {{ t('trainerCockpit.findTeam') }}
            </Link>
        </section>

        <section v-if="props.teams.length" class="grid gap-3 sm:grid-cols-2 xl:grid-cols-5">
            <article
                v-for="card in statCards"
                :key="card.key"
                class="surface-card bg-gradient-to-br p-4"
                :class="card.tone"
            >
                <div class="flex items-center justify-between gap-3">
                    <div>
                        <p class="text-xs font-bold uppercase text-secondary">{{ card.label }}</p>
                        <p class="mt-2 text-2xl font-bold text-primary">{{ formatNumber(card.value) }}{{ card.suffix || '' }}</p>
                    </div>
                    <span class="flex h-11 w-11 items-center justify-center rounded-lg bg-inputBg text-buttonPrimary">
                        <i :class="[card.icon, 'text-xl']"></i>
                    </span>
                </div>
            </article>
        </section>

        <section v-if="props.teams.length" class="surface-card p-2">
            <div class="grid gap-2 md:grid-cols-4">
                <button
                    v-for="tab in tabs"
                    :key="tab.key"
                    type="button"
                    class="flex items-center gap-3 rounded-lg border px-4 py-3 text-start transition"
                    :class="activeTab === tab.key ? 'border-buttonPrimary bg-buttonPrimary/10 text-primary' : 'border-transparent text-secondary hover:border-border hover:text-primary'"
                    @click="switchTab(tab.key)"
                >
                    <i :class="[tab.icon, 'text-lg']"></i>
                    <span class="font-bold">{{ tab.label }}</span>
                </button>
            </div>
        </section>

        <section v-if="props.teams.length && activeTab === 'overview'" class="grid gap-5 xl:grid-cols-[.9fr_1.1fr]">
            <div class="surface-card p-5 xl:col-span-2">
                <div class="grid gap-5 lg:grid-cols-[.75fr_1.25fr]">
                    <div>
                        <p class="text-xs font-bold uppercase text-buttonPrimary">{{ t('trainerCockpit.weeklyControl') }}</p>
                        <div class="mt-3 flex flex-wrap items-end gap-3">
                            <p class="text-5xl font-black leading-none text-primary">{{ formatNumber(coachWeekly.readiness_score) }}%</p>
                            <span class="mb-1 rounded-full border px-3 py-1 text-xs font-bold" :class="readinessTone(coachWeekly.risk_level)">
                                {{ readinessLabel(coachWeekly.risk_level) }}
                            </span>
                        </div>
                        <p class="mt-3 text-sm leading-6 text-secondary">{{ t('trainerCockpit.weeklyControlHint') }}</p>

                        <div class="mt-5 grid grid-cols-2 gap-2">
                            <div class="rounded-lg border border-border bg-inputBg p-3">
                                <p class="text-xl font-bold text-primary">{{ formatNumber(coachWeekly.current_week?.session_count) }}</p>
                                <p class="text-xs text-secondary">{{ t('trainerCockpit.weekSessions') }}</p>
                            </div>
                            <div class="rounded-lg border border-border bg-inputBg p-3">
                                <p class="text-xl font-bold text-primary">{{ formatDuration(coachWeekly.current_week?.duration_minutes) || '0' }}</p>
                                <p class="text-xs text-secondary">{{ t('trainerCockpit.weekLoad') }}</p>
                            </div>
                            <div class="rounded-lg border border-border bg-inputBg p-3">
                                <p class="text-xl font-bold text-primary">{{ formatTrend(coachWeekly.trend?.sessions_percent) }}</p>
                                <p class="text-xs text-secondary">{{ t('trainerCockpit.sessionTrend') }}</p>
                            </div>
                            <div class="rounded-lg border border-border bg-inputBg p-3">
                                <p class="text-xl font-bold text-primary">{{ coachWeekly.current_week?.average_rpe ?? '-' }}</p>
                                <p class="text-xs text-secondary">{{ t('trainerCockpit.averageRpe') }}</p>
                            </div>
                        </div>
                    </div>

                    <div class="grid gap-4 lg:grid-cols-2">
                        <div class="rounded-lg border border-border bg-inputBg p-4">
                            <p class="text-sm font-bold text-primary">{{ t('trainerCockpit.coachActions') }}</p>
                            <div class="mt-3 space-y-2">
                                <a
                                    v-for="action in coachWeekly.actions || []"
                                    :key="action.key"
                                    :href="action.href || '#'"
                                    class="block rounded-lg border p-3 text-sm transition hover:border-borderHover"
                                    :class="actionTone(action.tone)"
                                >
                                    <span class="font-bold text-primary">{{ t(`trainerCockpit.actions.${action.key}`) }}</span>
                                    <span v-if="action.count" class="ms-2 rounded-full bg-bg px-2 py-0.5 text-xs font-bold text-primary">{{ formatNumber(action.count) }}</span>
                                </a>
                            </div>
                        </div>

                        <div class="rounded-lg border border-border bg-inputBg p-4">
                            <p class="text-sm font-bold text-primary">{{ t('trainerCockpit.riskAthletes') }}</p>
                            <div v-if="coachWeekly.risk_athletes?.length" class="mt-3 space-y-2">
                                <div v-for="athlete in coachWeekly.risk_athletes" :key="athlete.id" class="rounded-lg bg-bg p-3">
                                    <p class="font-bold text-primary">{{ athlete.name }}</p>
                                    <p class="text-xs text-secondary">{{ athlete.team?.name || t('trainerCockpit.noTeam') }}</p>
                                    <p class="mt-1 text-xs text-secondary">
                                        {{ t('trainerCockpit.rpe') }} {{ athlete.rpe || '-' }}
                                        <span aria-hidden="true">/</span>
                                        {{ t('trainerCockpit.pain') }} {{ athlete.pain || '-' }}
                                    </p>
                                </div>
                            </div>
                            <p v-else class="mt-3 rounded-lg border border-dashed border-border p-4 text-sm text-secondary">
                                {{ t('trainerCockpit.noRiskAthletes') }}
                            </p>
                        </div>
                    </div>
                </div>

                <div v-if="coachWeekly.team_cards?.length" class="mt-5 flex gap-3 overflow-x-auto pb-1">
                    <article
                        v-for="team in coachWeekly.team_cards"
                        :key="team.team_id"
                        class="min-w-[240px] rounded-lg border border-border bg-inputBg p-4"
                    >
                        <div class="flex items-start justify-between gap-3">
                            <div>
                                <p class="font-bold text-primary">{{ team.name }}</p>
                                <p class="text-xs text-secondary">{{ team.sport_type || t('trainerCockpit.team') }}</p>
                            </div>
                            <span class="rounded-full border px-2 py-1 text-xs font-bold" :class="readinessTone(team.risk_level)">
                                {{ formatNumber(team.readiness_score) }}%
                            </span>
                        </div>
                        <div class="mt-4 grid grid-cols-3 gap-2 text-center">
                            <div class="rounded bg-bg p-2">
                                <p class="font-bold text-primary">{{ formatNumber(team.sessions) }}</p>
                                <p class="text-[11px] text-secondary">{{ t('trainerCockpit.sessionsShort') }}</p>
                            </div>
                            <div class="rounded bg-bg p-2">
                                <p class="font-bold text-primary">{{ formatNumber(team.feedback_open) }}</p>
                                <p class="text-[11px] text-secondary">{{ t('trainerCockpit.feedbackShort') }}</p>
                            </div>
                            <div class="rounded bg-bg p-2">
                                <p class="font-bold text-primary">{{ formatNumber(team.risk_athletes) }}</p>
                                <p class="text-[11px] text-secondary">{{ t('trainerCockpit.riskShort') }}</p>
                            </div>
                        </div>
                    </article>
                </div>
            </div>

            <div class="surface-card p-5">
                <p class="text-xs font-bold uppercase text-buttonPrimary">{{ t('trainerCockpit.todayFocus') }}</p>
                <h2 class="mt-1 text-xl font-bold text-primary">{{ t('trainerCockpit.nextSteps') }}</h2>

                <div class="mt-4 space-y-3">
                    <a
                        v-for="item in focusItems"
                        :key="item.key"
                        :href="item.href"
                        class="block rounded-lg border border-border bg-inputBg p-4 transition hover:border-borderHover"
                    >
                        <div class="flex items-start justify-between gap-3">
                            <div>
                                <p class="font-bold text-primary">{{ item.label }}</p>
                                <p class="mt-1 text-sm text-secondary">{{ item.text }}</p>
                            </div>
                            <span class="rounded-full bg-bg px-3 py-1 text-sm font-bold text-primary">{{ formatNumber(item.value) }}</span>
                        </div>
                    </a>
                </div>
            </div>

            <div class="surface-card p-5">
                <div class="flex items-center justify-between gap-3">
                    <div>
                        <p class="text-xs font-bold uppercase text-buttonPrimary">{{ t('trainerCockpit.nextEvents') }}</p>
                        <h2 class="mt-1 text-xl font-bold text-primary">{{ t('trainerCockpit.upcomingEvents') }}</h2>
                    </div>
                    <Link :href="route('auth.events.index')" class="rounded-lg border border-border px-3 py-2 text-sm font-bold text-primary hover:border-borderHover">
                        {{ t('trainerCockpit.open') }}
                    </Link>
                </div>

                <div v-if="upcomingEvents.length" class="mt-4 space-y-3">
                    <article v-for="event in upcomingEvents" :key="event.id" class="rounded-lg border border-border bg-inputBg p-4">
                        <div class="flex flex-col gap-2 sm:flex-row sm:items-start sm:justify-between">
                            <div>
                                <p class="font-bold text-primary">{{ event.title }}</p>
                                <p class="text-sm text-secondary">{{ event.team?.name || t('trainerCockpit.noTeam') }}</p>
                            </div>
                            <p class="text-sm font-semibold text-buttonPrimary">{{ formatDateTime(event.start_time) }}</p>
                        </div>
                        <p v-if="event.location" class="mt-2 text-sm text-secondary">
                            <i class="las la-map-marker-alt"></i>
                            {{ event.location }}
                        </p>
                    </article>
                </div>
                <p v-else class="mt-4 rounded-lg border border-dashed border-border p-6 text-center text-sm text-secondary">
                    {{ t('trainerCockpit.noEvents') }}
                </p>
            </div>
        </section>

        <section v-if="props.teams.length && activeTab === 'teams'" class="surface-card p-5">
            <p class="text-xs font-bold uppercase text-buttonPrimary">{{ t('trainerCockpit.teamOverview') }}</p>
            <h2 class="mt-1 text-xl font-bold text-primary">{{ t('trainerCockpit.teams') }}</h2>

            <div v-if="teams.length" class="mt-4 grid gap-4 lg:grid-cols-2">
                <article v-for="team in teams" :key="team.id" class="rounded-lg border border-border bg-inputBg p-4">
                    <div class="flex items-start justify-between gap-3">
                        <div>
                            <p class="text-lg font-bold text-primary">{{ team.name }}</p>
                            <p class="text-sm text-secondary">{{ team.club?.name || t('trainerCockpit.noClub') }}</p>
                        </div>
                        <span
                            v-if="team.club && !team.club.trainer_cockpit_enabled"
                            class="rounded-full bg-amber-500/10 px-3 py-1 text-xs font-bold text-amber-200"
                        >
                            {{ t('trainerCockpit.preview') }}
                        </span>
                    </div>

                    <div class="mt-4 grid grid-cols-3 gap-2">
                        <div class="rounded bg-bg p-3">
                            <p class="text-xl font-bold text-primary">{{ formatNumber(team.stats?.athletes) }}</p>
                            <p class="text-xs text-secondary">{{ t('trainerCockpit.athletes') }}</p>
                        </div>
                        <div class="rounded bg-bg p-3">
                            <p class="text-xl font-bold text-primary">{{ formatNumber(team.stats?.events) }}</p>
                            <p class="text-xs text-secondary">{{ t('trainerCockpit.upcomingEvents') }}</p>
                        </div>
                        <div class="rounded bg-bg p-3">
                            <p class="text-xl font-bold text-primary">{{ formatNumber(team.stats?.plans) }}</p>
                            <p class="text-xs text-secondary">{{ t('trainerCockpit.plans') }}</p>
                        </div>
                    </div>

                    <div v-if="team.athletes?.length" class="mt-4 flex flex-wrap gap-2">
                        <span
                            v-for="athlete in team.athletes.slice(0, 8)"
                            :key="athlete.id"
                            class="rounded-full bg-bg px-3 py-1 text-xs font-semibold text-secondary"
                        >
                            {{ athlete.name }}
                        </span>
                    </div>
                </article>
            </div>
            <p v-else class="mt-4 rounded-lg border border-dashed border-border p-6 text-center text-sm text-secondary">
                {{ t('trainerCockpit.noTeamsText') }}
            </p>
        </section>

        <section v-if="props.teams.length && activeTab === 'feedback'" id="feedback" class="grid gap-5 xl:grid-cols-2">
            <div class="surface-card p-5">
                <p class="text-xs font-bold uppercase text-buttonPrimary">{{ t('trainerCockpit.feedbackQueue') }}</p>
                <h2 class="mt-1 text-xl font-bold text-primary">{{ t('trainerCockpit.openFeedback') }}</h2>

                <div v-if="feedbackOpen.length" class="mt-4 space-y-3">
                    <Link v-for="log in feedbackOpen" :key="log.id" :href="log.href" class="block rounded-lg border border-border bg-inputBg p-4 hover:border-borderHover">
                        <p class="font-bold text-primary">{{ log.title }}</p>
                        <p class="mt-1 text-sm text-secondary">
                            {{ log.athlete?.name || t('trainerCockpit.athlete') }}
                            <span aria-hidden="true">/</span>
                            {{ log.team?.name || t('trainerCockpit.noTeam') }}
                        </p>
                        <p class="mt-2 text-xs font-semibold text-buttonPrimary">{{ formatDateTime(log.performed_at) }}</p>
                    </Link>
                </div>
                <p v-else class="mt-4 rounded-lg border border-dashed border-border p-6 text-center text-sm text-secondary">
                    {{ t('trainerCockpit.noFeedback') }}
                </p>
            </div>

            <div class="surface-card p-5">
                <p class="text-xs font-bold uppercase text-buttonPrimary">{{ t('trainerCockpit.recentDocumentation') }}</p>
                <h2 class="mt-1 text-xl font-bold text-primary">{{ t('trainerCockpit.recentLogs') }}</h2>

                <div v-if="recentLogs.length" class="mt-4 space-y-3">
                    <Link v-for="log in recentLogs" :key="log.id" :href="log.href" class="block rounded-lg border border-border bg-inputBg p-4 hover:border-borderHover">
                        <div class="flex flex-col gap-2 sm:flex-row sm:items-start sm:justify-between">
                            <div>
                                <p class="font-bold text-primary">{{ log.title }}</p>
                                <p class="text-sm text-secondary">{{ log.athlete?.name || t('trainerCockpit.athlete') }}</p>
                            </div>
                            <p class="text-sm font-semibold text-buttonPrimary">{{ log.status }}</p>
                        </div>
                        <div class="mt-3 flex flex-wrap gap-2 text-xs text-secondary">
                            <span v-if="formatDuration(log.duration_minutes)" class="rounded bg-bg px-2 py-1">{{ formatDuration(log.duration_minutes) }}</span>
                            <span v-if="formatDistance(log.distance_meters)" class="rounded bg-bg px-2 py-1">{{ formatDistance(log.distance_meters) }}</span>
                            <span v-if="log.intensity" class="rounded bg-bg px-2 py-1">{{ log.intensity }}</span>
                        </div>
                    </Link>
                </div>
                <p v-else class="mt-4 rounded-lg border border-dashed border-border p-6 text-center text-sm text-secondary">
                    {{ t('trainerCockpit.noLogs') }}
                </p>
            </div>
        </section>

        <section v-if="props.teams.length && activeTab === 'planning'" id="planning" class="grid gap-5 xl:grid-cols-2">
            <div class="surface-card p-5">
                <p class="text-xs font-bold uppercase text-buttonPrimary">{{ t('trainerCockpit.overdueQueue') }}</p>
                <h2 class="mt-1 text-xl font-bold text-primary">{{ t('trainerCockpit.overdueItems') }}</h2>

                <div v-if="overdueItems.length" class="mt-4 space-y-3">
                    <Link v-for="item in overdueItems" :key="item.id" :href="item.href" class="block rounded-lg border border-border bg-inputBg p-4 hover:border-borderHover">
                        <p class="font-bold text-primary">{{ item.title }}</p>
                        <p class="mt-1 text-sm text-secondary">
                            {{ item.plan?.title }}
                            <span aria-hidden="true">/</span>
                            {{ item.plan?.team?.name || t('trainerCockpit.noTeam') }}
                        </p>
                        <p class="mt-2 text-xs font-semibold text-amber-200">{{ formatDateTime(item.scheduled_at) }}</p>
                    </Link>
                </div>
                <p v-else class="mt-4 rounded-lg border border-dashed border-border p-6 text-center text-sm text-secondary">
                    {{ t('trainerCockpit.noOverdue') }}
                </p>
            </div>

            <div class="surface-card p-5">
                <p class="text-xs font-bold uppercase text-buttonPrimary">{{ t('trainerCockpit.activePlans') }}</p>
                <h2 class="mt-1 text-xl font-bold text-primary">{{ t('trainerCockpit.plans') }}</h2>

                <div v-if="plans.length" class="mt-4 space-y-3">
                    <article v-for="plan in plans" :key="plan.id" class="rounded-lg border border-border bg-inputBg p-4">
                        <div class="flex items-start justify-between gap-3">
                            <div>
                                <p class="font-bold text-primary">{{ plan.title }}</p>
                                <p class="text-sm text-secondary">{{ plan.team?.name || t('trainerCockpit.noTeam') }}</p>
                            </div>
                            <span class="rounded-full bg-bg px-3 py-1 text-xs font-bold text-primary">{{ plan.status }}</span>
                        </div>
                        <p class="mt-3 text-sm text-secondary">
                            {{ t('trainerCockpit.plannedItems') }}: {{ formatNumber(plan.items_count) }}
                        </p>
                    </article>
                </div>
                <p v-else class="mt-4 rounded-lg border border-dashed border-border p-6 text-center text-sm text-secondary">
                    {{ t('trainerCockpit.noPlans') }}
                </p>
            </div>
        </section>
    </div>
</template>
