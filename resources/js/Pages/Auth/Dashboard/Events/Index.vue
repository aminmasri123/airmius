<script setup>
import AppLayout from '@/Components/Auth/Layouts/AppLayout.vue'
import { Head, Link, router, useForm, usePage } from '@inertiajs/vue3'
import { computed, ref, watch } from 'vue'
import { useI18n } from 'vue-i18n'

defineOptions({ layout: AppLayout })

const props = defineProps({
    events: Array,
    clubs: Array,
    teams: Array,
    eventTypes: Array,
    visibilities: Array,
    sports: { type: Array, default: () => [] },
    eventDefaults: { type: Object, default: () => ({}) },
    filters: { type: Object, default: () => ({}) },
})

const { t } = useI18n()
const page = usePage()

const showCreateModal = ref(false)
const createStep = ref(1)
const errors = computed(() => page.props.errors || {})
const authorizationMessage = computed(() => errors.value.authorization || page.props.flash?.upgrade_required?.message || '')

const steps = [
    { number: 1, label: 'Basis' },
    { number: 2, label: 'Zeit' },
    { number: 3, label: 'Details' },
    { number: 4, label: 'Prüfen' },
]

const recurrenceOptions = [
    { value: '', label: 'events.recurrence.none' },
    { value: 'daily', label: 'events.recurrence.daily' },
    { value: 'weekly', label: 'events.recurrence.weekly' },
    { value: 'biweekly', label: 'events.recurrence.biweekly' },
    { value: 'monthly', label: 'events.recurrence.monthly' },
]

const weekdayOptions = [
    { value: 1, label: 'events.weekdays.monday', short: 'events.weekdays_short.monday' },
    { value: 2, label: 'events.weekdays.tuesday', short: 'events.weekdays_short.tuesday' },
    { value: 3, label: 'events.weekdays.wednesday', short: 'events.weekdays_short.wednesday' },
    { value: 4, label: 'events.weekdays.thursday', short: 'events.weekdays_short.thursday' },
    { value: 5, label: 'events.weekdays.friday', short: 'events.weekdays_short.friday' },
    { value: 6, label: 'events.weekdays.saturday', short: 'events.weekdays_short.saturday' },
    { value: 0, label: 'events.weekdays.sunday', short: 'events.weekdays_short.sunday' },
]

const typeLabels = {
    training: 'events.types.training',
    match: 'events.types.match',
    meeting: 'events.types.meeting',
    public: 'events.types.public',
}

const visibilityLabels = {
    private: 'events.visibility.private',
    organization: 'events.visibility.organization',
    public: 'events.visibility.public',
}

const rsvpOptions = [
    { value: 'yes', label: 'events.rsvp.yes', active: 'border-air-green bg-air-green/15 text-air-green' },
    { value: 'maybe', label: 'events.rsvp.maybe', active: 'border-air-blue bg-air-blue/15 text-air-blue' },
    { value: 'no', label: 'events.rsvp.no', active: 'border-error bg-error/15 text-error' },
]

const browserTimeZone = () => Intl.DateTimeFormat().resolvedOptions().timeZone || 'UTC'

const form = useForm({
    club_id: props.clubs?.[0]?.id || '',
    team_id: '',
    title: '',
    type: 'training',
    visibility: 'private',
    start_time: '',
    end_time: '',
    location: '',
    notes: '',
    recurring: '',
    recurrence_days: [],
    recurrence_ends_at: '',
    reminder_at: '',
    event_timezone: browserTimeZone(),
})

const filterForm = ref({
    search: props.filters.search || '',
    type: props.filters.type || '',
    visibility: props.filters.visibility || '',
    club_id: props.filters.club_id || '',
    team_id: props.filters.team_id || '',
    period: props.filters.period || 'upcoming',
    radius_km: props.filters.radius_km || '',
    sport_ids: props.filters.sport_ids || [],
})

const filteredTeams = computed(() => {
    if (!form.club_id) return props.teams || []

    return (props.teams || []).filter((team) => Number(team.club_id) === Number(form.club_id))
})

watch(() => form.club_id, () => {
    if (form.team_id && !filteredTeams.value.some((team) => Number(team.id) === Number(form.team_id))) {
        form.team_id = ''
    }
})

watch(() => form.recurring, (value) => {
    if (!value) {
        form.recurrence_ends_at = ''
        form.recurrence_days = []
    }

    if (!['weekly', 'biweekly'].includes(value)) {
        form.recurrence_days = []
    }
})

const resetCreateForm = () => {
    form.reset(
        'title',
        'team_id',
        'start_time',
        'end_time',
        'location',
        'notes',
        'recurring',
        'recurrence_days',
        'recurrence_ends_at',
        'reminder_at'
    )

    form.clearErrors()
    createStep.value = 1
}

const closeCreateModal = () => {
    showCreateModal.value = false
    createStep.value = 1
    form.clearErrors()
}

const openCreateModal = () => {
    createStep.value = 1
    showCreateModal.value = true
}

const nextStep = () => {
    if (createStep.value < steps.length) {
        createStep.value++
    }
}

const prevStep = () => {
    if (createStep.value > 1) {
        createStep.value--
    }
}

const submit = () => {
    form.event_timezone = browserTimeZone()

    form.post(route('auth.events.store'), {
        preserveScroll: true,
        onError: () => {
            if (form.errors.authorization) {
                createStep.value = 1
            }
        },
        onSuccess: () => {
            resetCreateForm()
            showCreateModal.value = false
        },
    })
}

const toggleWeekday = (day) => {
    const days = form.recurrence_days.map(Number)

    form.recurrence_days = days.includes(day)
        ? days.filter((value) => value !== day)
        : [...days, day].sort((a, b) => a - b)
}

const recurrenceLabel = (value) => {
    const label = recurrenceOptions.find((option) => option.value === value)?.label
    return label ? t(label) : value
}

const recurrenceSummary = computed(() => {
    if (!form.recurring) return ''

    const selectedDays = weekdayOptions
        .filter((day) => form.recurrence_days.map(Number).includes(day.value))
        .map((day) => t(day.label))
        .join(', ')

    if (['weekly', 'biweekly'].includes(form.recurring) && selectedDays) {
        return `${recurrenceLabel(form.recurring)}: ${selectedDays}`
    }

    return recurrenceLabel(form.recurring)
})

const selectedClubName = computed(() => {
    return props.clubs?.find((club) => Number(club.id) === Number(form.club_id))?.name || '-'
})

const selectedTeamName = computed(() => {
    return props.teams?.find((team) => Number(team.id) === Number(form.team_id))?.name || '-'
})

const eventTimeZone = (event = null) => event?.event_timezone || browserTimeZone()

const formatDate = (date, event = null) => {
    if (!date) return ''

    return new Intl.DateTimeFormat('de-DE', {
        timeZone: eventTimeZone(event),
        weekday: 'short',
        day: '2-digit',
        month: '2-digit',
        year: 'numeric',
    }).format(new Date(date))
}

const formatTime = (date, event = null) => {
    if (!date) return ''

    return new Intl.DateTimeFormat('de-DE', {
        timeZone: eventTimeZone(event),
        hour: '2-digit',
        minute: '2-digit',
    }).format(new Date(date))
}

const timeRange = (event) => {
    if (!event.end_time) {
        return `${formatTime(event.start_time, event)} Uhr`
    }

    return `${formatTime(event.start_time, event)} – ${formatTime(event.end_time, event)} Uhr`
}

const formatDay = (date, event = null) => {
    if (!date) return ''

    return new Intl.DateTimeFormat('de-DE', {
        timeZone: eventTimeZone(event),
        day: '2-digit',
    }).format(new Date(date))
}

const formatMonthShort = (date, event = null) => {
    if (!date) return ''

    return new Intl.DateTimeFormat('de-DE', {
        timeZone: eventTimeZone(event),
        month: 'short',
    })
        .format(new Date(date))
        .replace('.', '')
        .toUpperCase()
}

const formatWeekdayShort = (date, event = null) => {
    if (!date) return ''

    return new Intl.DateTimeFormat('de-DE', {
        timeZone: eventTimeZone(event),
        weekday: 'short',
    })
        .format(new Date(date))
        .replace('.', '')
        .toUpperCase()
}

const weekdayShortLabel = (dayValue) => {
    const option = weekdayOptions.find((day) => day.value === Number(dayValue))
    return option ? t(option.short) : dayValue
}

const recurrenceDaysLabel = (event) => {
    const days = Array.isArray(event.recurrence_days) ? event.recurrence_days : []
    return days.map(weekdayShortLabel).join(', ')
}

const eventDateTimeLabel = (event) => {
    if (event.recurring && recurrenceDaysLabel(event)) {
        return `${recurrenceDaysLabel(event)} · ${timeRange(event)}`
    }

    if (event.recurring) {
        return `${recurrenceLabel(event.recurring)} · ${timeRange(event)}`
    }

    return `${formatDate(event.start_time, event)} · ${timeRange(event)}`
}

const rsvpButtonClass = (event, status) => event.current_participant_status === status
    ? rsvpOptions.find((option) => option.value === status)?.active
    : 'border-border bg-inputBg text-secondary hover:border-borderHover hover:text-primary'

const setParticipation = (event, status) => {
    router.post(route('auth.events.join', event.id), { status }, {
        preserveScroll: true,
        preserveState: true,
    })
}

const toggleFilterSport = (sportId) => {
    const id = Number(sportId)
    const selected = (filterForm.value.sport_ids || []).map(Number)

    filterForm.value.sport_ids = selected.includes(id)
        ? selected.filter((value) => value !== id)
        : [...selected, id]
}

const applyFilters = () => {
    router.get(route('auth.events.index'), {
        search: filterForm.value.search || undefined,
        type: filterForm.value.type || undefined,
        visibility: filterForm.value.visibility || undefined,
        club_id: filterForm.value.club_id || undefined,
        team_id: filterForm.value.team_id || undefined,
        period: filterForm.value.period || undefined,
        radius_km: filterForm.value.radius_km || undefined,
        sport_ids: filterForm.value.sport_ids?.length ? filterForm.value.sport_ids : undefined,
    }, {
        preserveScroll: true,
        preserveState: true,
        replace: true,
    })
}

const saveDefaultFilters = () => {
    router.put(route('auth.events.default-filters.update'), {
        search: filterForm.value.search || '',
        type: filterForm.value.type || '',
        visibility: filterForm.value.visibility || '',
        club_id: filterForm.value.club_id || '',
        team_id: filterForm.value.team_id || '',
        period: filterForm.value.period || 'upcoming',
        radius_km: filterForm.value.radius_km || null,
        sport_ids: filterForm.value.sport_ids || [],
    }, {
        preserveScroll: true,
        preserveState: true,
    })
}

const resetFilters = () => {
    filterForm.value = {
        search: '',
        type: '',
        visibility: '',
        club_id: '',
        team_id: '',
        period: 'upcoming',
        radius_km: '',
        sport_ids: [],
    }

    applyFilters()
}
</script>

<template>

    <Head :title="$t('Events')" />

    <div class="space-y-6">
        <div class="flex items-start justify-between gap-4">
            <div class="min-w-0">
                <h1 class="text-2xl font-semibold text-primary">
                    {{ $t('Events') }}
                </h1>

                <p class="mt-1 text-sm text-secondary">
                    {{ $t('events.subtitle') }}
                </p>
            </div>

            <!-- Mobile Plus Button -->
            <button type="button"
                class="flex h-8 w-8 shrink-0 items-center justify-center rounded hover:bg-buttonPrimaryHover bg-buttonPrimary text-buttonTextPrimary shadow sm:hidden"
                @click="openCreateModal" :aria-label="$t('events.create')">
                <i class="las la-plus text-2xl"></i>
            </button>

            <!-- Desktop Button -->
            <button type="button"
                class="hidden rounded-lg bg-buttonPrimary px-4 py-3 font-semibold text-buttonTextPrimary transition hover:bg-buttonPrimaryHover sm:inline-flex"
                @click="openCreateModal">
                + {{ $t('events.create') }}
            </button>
        </div>

        <div v-if="authorizationMessage" class="rounded-lg border border-warning/40 bg-warning/10 p-4 text-sm text-warning">
            <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                <p class="font-semibold">
                    {{ authorizationMessage }}
                </p>
                <Link :href="route('guest.pricing')" class="shrink-0 rounded-lg bg-buttonPrimary px-4 py-2 text-center text-sm font-semibold text-buttonTextPrimary hover:bg-buttonPrimaryHover">
                    Upgrade ansehen
                </Link>
            </div>
        </div>

        <section class="rounded-lg border border-border bg-card p-4">
            <form class="grid gap-3 md:grid-cols-2 xl:grid-cols-4" @submit.prevent="applyFilters">
                <div>
                    <label class="mb-1 block text-xs font-semibold uppercase tracking-wide text-secondary" for="event-search">
                        Suche
                    </label>
                    <input
                        id="event-search"
                        v-model="filterForm.search"
                        class="h-11 w-full rounded-lg border border-border bg-inputBg px-3 text-sm text-primary placeholder-secondary focus:border-borderHover focus:ring-borderHover"
                        placeholder="Titel, Ort, Team, Verein..."
                    >
                </div>

                <div>
                    <label class="mb-1 block text-xs font-semibold uppercase tracking-wide text-secondary" for="event-filter-type">
                        Typ
                    </label>
                    <select id="event-filter-type" v-model="filterForm.type" class="h-11 w-full rounded-lg border border-border bg-inputBg px-3 text-sm text-primary focus:border-borderHover focus:ring-borderHover">
                        <option value="">Alle Typen</option>
                        <option v-for="type in eventTypes" :key="type" :value="type">
                            {{ $t(typeLabels[type] || type) }}
                        </option>
                    </select>
                </div>

                <div>
                    <label class="mb-1 block text-xs font-semibold uppercase tracking-wide text-secondary" for="event-filter-visibility">
                        Sichtbarkeit
                    </label>
                    <select id="event-filter-visibility" v-model="filterForm.visibility" class="h-11 w-full rounded-lg border border-border bg-inputBg px-3 text-sm text-primary focus:border-borderHover focus:ring-borderHover">
                        <option value="">Alle</option>
                        <option v-for="visibility in visibilities" :key="visibility" :value="visibility">
                            {{ $t(visibilityLabels[visibility] || visibility) }}
                        </option>
                    </select>
                </div>

                <div>
                    <label class="mb-1 block text-xs font-semibold uppercase tracking-wide text-secondary" for="event-filter-club">
                        Verein
                    </label>
                    <select id="event-filter-club" v-model="filterForm.club_id" class="h-11 w-full rounded-lg border border-border bg-inputBg px-3 text-sm text-primary focus:border-borderHover focus:ring-borderHover">
                        <option value="">Alle Vereine</option>
                        <option v-for="club in clubs" :key="club.id" :value="club.id">
                            {{ club.name }}
                        </option>
                    </select>
                </div>

                <div>
                    <label class="mb-1 block text-xs font-semibold uppercase tracking-wide text-secondary" for="event-filter-team">
                        Team
                    </label>
                    <select id="event-filter-team" v-model="filterForm.team_id" class="h-11 w-full rounded-lg border border-border bg-inputBg px-3 text-sm text-primary focus:border-borderHover focus:ring-borderHover">
                        <option value="">Alle Teams</option>
                        <option v-for="team in teams" :key="team.id" :value="team.id">
                            {{ team.name }}
                        </option>
                    </select>
                </div>

                <div>
                    <label class="mb-1 block text-xs font-semibold uppercase tracking-wide text-secondary" for="event-filter-period">
                        Zeitraum
                    </label>
                    <select id="event-filter-period" v-model="filterForm.period" class="h-11 w-full rounded-lg border border-border bg-inputBg px-3 text-sm text-primary focus:border-borderHover focus:ring-borderHover">
                        <option value="upcoming">Kommend</option>
                        <option value="past">Vergangen</option>
                        <option value="all">Alle</option>
                    </select>
                </div>

                <div>
                    <label class="mb-1 block text-xs font-semibold uppercase tracking-wide text-secondary" for="event-filter-radius">
                        Zone
                    </label>
                    <div class="flex h-11 items-center gap-2 rounded-lg border border-border bg-inputBg px-3">
                        <input
                            id="event-filter-radius"
                            v-model="filterForm.radius_km"
                            class="min-w-0 flex-1 border-0 bg-transparent p-0 text-sm text-primary focus:ring-0"
                            min="1"
                            max="500"
                            placeholder="20"
                            type="number"
                        >
                        <span class="text-sm font-semibold text-secondary">km</span>
                    </div>
                </div>

                <div class="md:col-span-2 xl:col-span-4">
                    <div class="mb-2 flex items-center justify-between gap-3">
                        <label class="block text-xs font-semibold uppercase tracking-wide text-secondary">
                            Sportarten
                        </label>
                        <span v-if="eventDefaults?.sport_ids?.length || eventDefaults?.radius_km" class="text-xs font-semibold text-secondary">
                            Standardfilter aktiv
                        </span>
                    </div>
                    <div class="flex max-h-28 flex-wrap gap-2 overflow-y-auto rounded-lg border border-border bg-inputBg p-2">
                        <button
                            v-for="sport in sports"
                            :key="sport.id"
                            type="button"
                            class="rounded-full border px-3 py-1.5 text-xs font-semibold transition"
                            :class="(filterForm.sport_ids || []).map(Number).includes(Number(sport.id))
                                ? 'border-buttonPrimary bg-buttonPrimary text-buttonTextPrimary'
                                : 'border-border bg-bg text-secondary hover:border-borderHover hover:text-primary'"
                            @click="toggleFilterSport(sport.id)"
                        >
                            {{ sport.name }}
                        </button>
                    </div>
                </div>

                <div class="flex items-end">
                    <button class="h-11 w-full rounded-lg bg-buttonPrimary px-4 text-sm font-semibold text-buttonTextPrimary transition hover:bg-buttonPrimaryHover">
                        Filtern
                    </button>
                </div>

                <div class="flex items-end">
                    <button type="button" class="h-11 w-full rounded-lg border border-border px-4 text-sm font-semibold text-secondary transition hover:border-borderHover hover:text-primary" @click="resetFilters">
                        Reset
                    </button>
                </div>

                <div class="flex items-end md:col-span-2">
                    <button type="button" class="h-11 w-full rounded-lg border border-buttonPrimary px-4 text-sm font-semibold text-buttonPrimary transition hover:bg-buttonPrimary/10" @click="saveDefaultFilters">
                        Diese Filter als Standard speichern
                    </button>
                </div>
            </form>
        </section>

        <!-- CREATE EVENT MODAL / WIZARD -->
        <Teleport to="body">
            <div v-if="showCreateModal" class="fixed inset-0 z-[80] flex items-center justify-center bg-black/60"
                @click.self="closeCreateModal">
                <div
                    class="flex h-full w-full flex-col bg-card sm:h-auto sm:max-h-[92vh] sm:max-w-2xl sm:rounded-2xl sm:border sm:border-border sm:shadow-xl">

                    <!-- Modal Header -->
                    <div class="shrink-0 border-b border-border bg-card p-4">
                        <div class="flex items-start justify-between gap-4">
                            <div class="min-w-0">
                                <h2 class="truncate text-lg font-semibold text-primary">
                                    {{ $t('events.create') }}
                                </h2>

                                <p class="mt-1 text-sm text-secondary">
                                    Schritt {{ createStep }} von {{ steps.length }}
                                </p>
                            </div>

                            <button type="button"
                                class="shrink-0 rounded-lg border border-border px-3 py-1 text-secondary transition hover:border-borderHover hover:text-primary"
                                @click="closeCreateModal">
                                ✕
                            </button>
                        </div>

                        <!-- Step Indicator -->
                        <div class="mt-4 grid grid-cols-4 gap-2">
                            <button v-for="step in steps" :key="step.number" type="button"
                                class="rounded-full px-2 py-2 text-xs font-semibold transition" :class="createStep === step.number
                                    ? 'bg-buttonPrimary text-buttonTextPrimary'
                                    : createStep > step.number
                                        ? 'bg-air-green/15 text-air-green'
                                        : 'bg-inputBg text-secondary'" @click="createStep = step.number">
                                {{ step.label }}
                            </button>
                        </div>
                    </div>

                    <!-- Modal Body -->
                    <form class="min-h-0 flex-1 overflow-y-auto p-4" @submit.prevent="submit">
                        <!-- STEP 1 -->
                        <section v-show="createStep === 1" class="space-y-4">
                            <div>
                                <h3 class="text-base font-semibold text-primary">
                                    Basisdaten
                                </h3>

                                <p class="mt-1 text-sm text-secondary">
                                    Was für ein Event möchtest du erstellen?
                                </p>
                            </div>

                            <div v-if="form.errors.authorization" class="rounded-lg border border-warning/40 bg-warning/10 p-3 text-sm text-warning">
                                <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                                    <p class="font-semibold">
                                        {{ form.errors.authorization }}
                                    </p>
                                    <Link :href="route('guest.pricing')" class="rounded-lg bg-buttonPrimary px-3 py-2 text-center text-sm font-semibold text-buttonTextPrimary">
                                        Upgrade ansehen
                                    </Link>
                                </div>
                            </div>

                            <div>
                                <label for="event-title" class="block text-sm font-semibold text-primary">
                                    {{ $t('events.fields.title') }}
                                </label>

                                <input id="event-title" v-model="form.title"
                                    class="mt-1 w-full rounded-lg border border-border bg-inputBg px-3 py-3 text-primary placeholder-secondary focus:border-borderHover focus:ring-borderHover"
                                    :placeholder="$t('events.placeholders.title')" required>

                                <div v-if="form.errors.title" class="mt-1 text-sm text-error">
                                    {{ form.errors.title }}
                                </div>
                            </div>

                            <div class="grid grid-cols-1 gap-3 sm:grid-cols-2">
                                <div>
                                    <label for="event-type" class="block text-sm font-semibold text-primary">
                                        {{ $t('events.fields.type') }}
                                    </label>

                                    <select id="event-type" v-model="form.type"
                                        class="mt-1 w-full rounded-lg border border-border bg-inputBg px-3 py-3 text-primary focus:border-borderHover focus:ring-borderHover">
                                        <option v-for="type in eventTypes" :key="type" :value="type">
                                            {{ $t(typeLabels[type] || type) }}
                                        </option>
                                    </select>
                                </div>

                                <div>
                                    <label for="event-visibility" class="block text-sm font-semibold text-primary">
                                        {{ $t('events.fields.visibility') }}
                                    </label>

                                    <select id="event-visibility" v-model="form.visibility"
                                        class="mt-1 w-full rounded-lg border border-border bg-inputBg px-3 py-3 text-primary focus:border-borderHover focus:ring-borderHover">
                                        <option v-for="visibility in visibilities" :key="visibility"
                                            :value="visibility">
                                            {{ $t(visibilityLabels[visibility] || visibility) }}
                                        </option>
                                    </select>
                                </div>
                            </div>

                            <div class="grid grid-cols-1 gap-3 sm:grid-cols-2">
                                <div>
                                    <label for="event-club" class="block text-sm font-semibold text-primary">
                                        {{ $t('events.fields.club') }}
                                    </label>

                                    <select id="event-club" v-model="form.club_id"
                                        class="mt-1 w-full rounded-lg border border-border bg-inputBg px-3 py-3 text-primary focus:border-borderHover focus:ring-borderHover">
                                        <option value="">
                                            {{ $t('events.none.club') }}
                                        </option>

                                        <option v-for="club in clubs" :key="club.id" :value="club.id">
                                            {{ club.name }}
                                        </option>
                                    </select>
                                </div>

                                <div>
                                    <label for="event-team" class="block text-sm font-semibold text-primary">
                                        {{ $t('events.fields.team') }}
                                    </label>

                                    <select id="event-team" v-model="form.team_id"
                                        class="mt-1 w-full rounded-lg border border-border bg-inputBg px-3 py-3 text-primary focus:border-borderHover focus:ring-borderHover">
                                        <option value="">
                                            {{ $t('events.none.team') }}
                                        </option>

                                        <option v-for="team in filteredTeams" :key="team.id" :value="team.id">
                                            {{ team.name }}
                                        </option>
                                    </select>

                                    <p v-if="form.visibility === 'private'" class="mt-1 text-xs text-secondary">
                                        {{ $t('events.private_requires_team') }}
                                    </p>

                                    <div v-if="form.errors.team_id" class="mt-1 text-sm text-error">
                                        {{ form.errors.team_id }}
                                    </div>
                                </div>
                            </div>
                        </section>

                        <!-- STEP 2 -->
                        <section v-show="createStep === 2" class="space-y-4">
                            <div>
                                <h3 class="text-base font-semibold text-primary">
                                    Zeit
                                </h3>

                                <p class="mt-1 text-sm text-secondary">
                                    Wann findet das Event statt?
                                </p>
                            </div>

                            <div>
                                <label for="event-start" class="block text-sm font-semibold text-primary">
                                    {{ $t('events.fields.start') }}
                                </label>

                                <input id="event-start" v-model="form.start_time"
                                    class="date-input mt-1 w-full rounded-lg border border-border bg-inputBg px-3 py-3 text-primary focus:border-borderHover focus:ring-borderHover"
                                    type="datetime-local" required>

                                <p class="mt-1 text-xs text-secondary">
                                    Zeitzone: {{ form.event_timezone }}
                                </p>

                                <div v-if="form.errors.start_time" class="mt-1 text-sm text-error">
                                    {{ form.errors.start_time }}
                                </div>
                            </div>

                            <div>
                                <label for="event-end" class="block text-sm font-semibold text-primary">
                                    {{ $t('events.fields.end') }}
                                </label>

                                <input id="event-end" v-model="form.end_time"
                                    class="date-input mt-1 w-full rounded-lg border border-border bg-inputBg px-3 py-3 text-primary focus:border-borderHover focus:ring-borderHover"
                                    type="datetime-local">

                                <div v-if="form.errors.end_time" class="mt-1 text-sm text-error">
                                    {{ form.errors.end_time }}
                                </div>
                            </div>

                            <div>
                                <label for="event-reminder" class="block text-sm font-semibold text-primary">
                                    {{ $t('events.fields.reminder') }}
                                </label>

                                <input id="event-reminder" v-model="form.reminder_at"
                                    class="date-input mt-1 w-full rounded-lg border border-border bg-inputBg px-3 py-3 text-primary focus:border-borderHover focus:ring-borderHover"
                                    type="datetime-local">

                                <div v-if="form.errors.reminder_at" class="mt-1 text-sm text-error">
                                    {{ form.errors.reminder_at }}
                                </div>
                            </div>
                        </section>

                        <!-- STEP 3 -->
                        <section v-show="createStep === 3" class="space-y-4">
                            <div>
                                <h3 class="text-base font-semibold text-primary">
                                    Details & Wiederholung
                                </h3>

                                <p class="mt-1 text-sm text-secondary">
                                    Optional: Ort, Notizen und Wiederholung hinzufügen.
                                </p>
                            </div>

                            <div>
                                <label for="event-recurring" class="block text-sm font-semibold text-primary">
                                    {{ $t('events.fields.recurrence') }}
                                </label>

                                <select id="event-recurring" v-model="form.recurring"
                                    class="mt-1 w-full rounded-lg border border-border bg-inputBg px-3 py-3 text-primary focus:border-borderHover focus:ring-borderHover">
                                    <option v-for="option in recurrenceOptions" :key="option.value"
                                        :value="option.value">
                                        {{ $t(option.label) }}
                                    </option>
                                </select>
                            </div>

                            <div v-if="form.recurring">
                                <label for="event-recurrence-end" class="block text-sm font-semibold text-primary">
                                    {{ $t('events.fields.recurrence_end') }}
                                </label>

                                <input id="event-recurrence-end" v-model="form.recurrence_ends_at"
                                    class="date-input mt-1 w-full rounded-lg border border-border bg-inputBg px-3 py-3 text-primary focus:border-borderHover focus:ring-borderHover"
                                    type="date">

                                <div v-if="form.errors.recurrence_ends_at" class="mt-1 text-sm text-error">
                                    {{ form.errors.recurrence_ends_at }}
                                </div>
                            </div>

                            <div v-if="['weekly', 'biweekly'].includes(form.recurring)">
                                <div class="mb-2 text-sm font-semibold text-primary">
                                    {{ $t('events.fields.recurrence_days') }}
                                </div>

                                <div class="grid grid-cols-4 gap-2 sm:grid-cols-7">
                                    <button v-for="day in weekdayOptions" :key="day.value" type="button"
                                        class="rounded-lg border px-2 py-3 text-xs font-semibold transition" :class="form.recurrence_days.map(Number).includes(day.value)
                                            ? 'border-buttonPrimary bg-buttonPrimary text-buttonTextPrimary'
                                            : 'border-border bg-inputBg text-primary hover:bg-muted'"
                                        :title="$t(day.label)" @click="toggleWeekday(day.value)">
                                        {{ $t(day.short) }}
                                    </button>
                                </div>

                                <div v-if="form.errors.recurrence_days" class="mt-1 text-sm text-error">
                                    {{ form.errors.recurrence_days }}
                                </div>

                                <p v-if="recurrenceSummary" class="mt-2 text-xs text-secondary">
                                    {{ recurrenceSummary }}
                                </p>
                            </div>

                            <div>
                                <label for="event-location" class="block text-sm font-semibold text-primary">
                                    {{ $t('events.fields.location') }}
                                </label>

                                <input id="event-location" v-model="form.location"
                                    class="mt-1 w-full rounded-lg border border-border bg-inputBg px-3 py-3 text-primary placeholder-secondary focus:border-borderHover focus:ring-borderHover"
                                    :placeholder="$t('events.placeholders.location')">
                            </div>

                            <div>
                                <label for="event-notes" class="block text-sm font-semibold text-primary">
                                    {{ $t('events.fields.notes') }}
                                </label>

                                <textarea id="event-notes" v-model="form.notes" rows="4"
                                    class="mt-1 w-full resize-none rounded-lg border border-border bg-inputBg px-3 py-3 text-primary placeholder-secondary focus:border-borderHover focus:ring-borderHover"
                                    :placeholder="$t('events.placeholders.notes')" />
                            </div>
                        </section>

                        <!-- STEP 4 -->
                        <section v-show="createStep === 4" class="space-y-4">
                            <div>
                                <h3 class="text-base font-semibold text-primary">
                                    Prüfen
                                </h3>

                                <p class="mt-1 text-sm text-secondary">
                                    Kontrolliere deine Angaben vor dem Speichern.
                                </p>
                            </div>

                            <div class="rounded-xl border border-border bg-inputBg p-4">
                                <div class="space-y-3 text-sm">
                                    <div>
                                        <p class="text-xs font-semibold uppercase tracking-wide text-secondary">
                                            Titel
                                        </p>
                                        <p class="mt-1 font-semibold text-primary">
                                            {{ form.title || '-' }}
                                        </p>
                                    </div>

                                    <div class="grid grid-cols-1 gap-3 sm:grid-cols-2">
                                        <div>
                                            <p class="text-xs font-semibold uppercase tracking-wide text-secondary">
                                                Typ
                                            </p>
                                            <p class="mt-1 text-primary">
                                                {{ $t(typeLabels[form.type] || form.type) }}
                                            </p>
                                        </div>

                                        <div>
                                            <p class="text-xs font-semibold uppercase tracking-wide text-secondary">
                                                Sichtbarkeit
                                            </p>
                                            <p class="mt-1 text-primary">
                                                {{ $t(visibilityLabels[form.visibility] || form.visibility) }}
                                            </p>
                                        </div>
                                    </div>

                                    <div class="grid grid-cols-1 gap-3 sm:grid-cols-2">
                                        <div>
                                            <p class="text-xs font-semibold uppercase tracking-wide text-secondary">
                                                Verein
                                            </p>
                                            <p class="mt-1 text-primary">
                                                {{ selectedClubName }}
                                            </p>
                                        </div>

                                        <div>
                                            <p class="text-xs font-semibold uppercase tracking-wide text-secondary">
                                                Team
                                            </p>
                                            <p class="mt-1 text-primary">
                                                {{ selectedTeamName }}
                                            </p>
                                        </div>
                                    </div>

                                    <div class="grid grid-cols-1 gap-3 sm:grid-cols-2">
                                        <div>
                                            <p class="text-xs font-semibold uppercase tracking-wide text-secondary">
                                                Start
                                            </p>
                                            <p class="mt-1 text-primary">
                                                {{ form.start_time || '-' }}
                                            </p>
                                        </div>

                                        <div>
                                            <p class="text-xs font-semibold uppercase tracking-wide text-secondary">
                                                Ende
                                            </p>
                                            <p class="mt-1 text-primary">
                                                {{ form.end_time || '-' }}
                                            </p>
                                        </div>
                                    </div>

                                    <div>
                                        <p class="text-xs font-semibold uppercase tracking-wide text-secondary">
                                            Erinnerung
                                        </p>
                                        <p class="mt-1 text-primary">
                                            {{ form.reminder_at || '-' }}
                                        </p>
                                    </div>

                                    <div>
                                        <p class="text-xs font-semibold uppercase tracking-wide text-secondary">
                                            Wiederholung
                                        </p>
                                        <p class="mt-1 text-primary">
                                            {{ recurrenceSummary || recurrenceLabel(form.recurring) || 'Keine' }}
                                        </p>
                                    </div>

                                    <div>
                                        <p class="text-xs font-semibold uppercase tracking-wide text-secondary">
                                            Ort
                                        </p>
                                        <p class="mt-1 text-primary">
                                            {{ form.location || '-' }}
                                        </p>
                                    </div>

                                    <div>
                                        <p class="text-xs font-semibold uppercase tracking-wide text-secondary">
                                            Notizen
                                        </p>
                                        <p class="mt-1 whitespace-pre-line break-words text-primary">
                                            {{ form.notes || '-' }}
                                        </p>
                                    </div>
                                </div>
                            </div>
                        </section>
                    </form>

                    <!-- Modal Footer -->
                    <div class="shrink-0 border-t border-border bg-card p-4">
                        <div class="flex gap-3">
                            <button type="button"
                                class="flex-1 rounded-lg border border-border px-4 py-3 font-semibold text-secondary transition hover:border-borderHover hover:text-primary disabled:opacity-50"
                                :disabled="createStep === 1" @click="prevStep">
                                Zurück
                            </button>

                            <button v-if="createStep < steps.length" type="button"
                                class="flex-1 rounded-lg bg-buttonPrimary px-4 py-3 font-semibold text-buttonTextPrimary transition hover:bg-buttonPrimaryHover"
                                @click="nextStep">
                                Weiter
                            </button>

                            <button v-else type="button"
                                class="flex-1 rounded-lg bg-buttonPrimary px-4 py-3 font-semibold text-buttonTextPrimary transition hover:bg-buttonPrimaryHover disabled:opacity-50"
                                :disabled="form.processing" @click="submit">
                                {{ form.processing ? 'Speichern...' : $t('events.create') }}
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </Teleport>

        <!-- EVENT LIST -->
        <div class="grid gap-4 md:grid-cols-2 xl:grid-cols-3">
            <article v-for="event in events" :key="event.id"
                class="group rounded-lg border border-border bg-card p-4 transition hover:-translate-y-0.5 hover:border-borderHover hover:shadow-lg">
                <div class="flex items-start gap-3">
                    <div class="flex w-16 shrink-0 flex-col items-center justify-center rounded-lg bg-inputBg py-2">
                        <span class="text-[10px] font-semibold uppercase text-secondary">
                            {{ formatWeekdayShort(event.start_time, event) }}
                        </span>

                        <span class="text-xl font-bold leading-tight text-primary">
                            {{ formatDay(event.start_time, event) }}
                        </span>

                        <span class="text-[10px] font-semibold uppercase text-secondary">
                            {{ formatMonthShort(event.start_time, event) }}
                        </span>
                    </div>

                    <div class="min-w-0 flex-1">
                        <div class="flex items-start justify-between gap-3">
                            <div class="min-w-0">
                                <Link :href="route('auth.events.show', event.id)"
                                    class="font-semibold text-primary transition group-hover:text-buttonPrimary hover:underline">
                                    <span>{{ event.club?.name }}</span>
                                    <span v-if="event.club?.name"> - </span>
                                    <span>{{ event.title }}</span>
                                </Link>

                                <p class="mt-1 text-sm text-secondary">
                                    {{ $t(typeLabels[event.type] || event.type) }}
                                    -
                                    {{ $t(visibilityLabels[event.visibility] || event.visibility) }}
                                </p>
                            </div>

                            <span class="shrink-0 rounded-full bg-inputBg px-2 py-1 text-xs text-secondary">
                                {{ event.participants_count }} Rückm.
                            </span>
                        </div>

                        <div class="mt-3 flex flex-wrap gap-2">
                            <span v-if="event.comments_count" class="rounded-full bg-inputBg px-2 py-1 text-xs font-semibold text-secondary">
                                {{ event.comments_count }} Kommentare
                            </span>
                            <span v-if="event.can_update" class="rounded-full bg-buttonPrimary/10 px-2 py-1 text-xs font-semibold text-buttonPrimary">
                                Bearbeitbar
                            </span>
                        </div>

                        <div class="mt-3 space-y-1">
                            <p class="text-sm font-semibold text-primary">
                                {{ eventDateTimeLabel(event) }}
                            </p>

                            <p class="text-sm text-secondary">
                                {{ event.team?.name || event.club?.name || $t('events.public_scope') }}
                            </p>

                            <p class="flex items-center gap-1 text-sm text-secondary">
                                <i class="las la-map-marker-alt text-base text-buttonPrimary"></i>
                                <span class="truncate">{{ event.location || 'Keine Eingabe' }}</span>
                            </p>
                        </div>

                        <div class="mt-4 grid grid-cols-3 gap-2">
                            <button v-for="option in rsvpOptions" :key="option.value" type="button"
                                class="rounded-lg border px-2 py-2 text-xs font-semibold transition"
                                :class="rsvpButtonClass(event, option.value)"
                                @click="setParticipation(event, option.value)">
                                {{ $t(option.label) }}
                            </button>
                        </div>

                        <div class="mt-4 flex flex-wrap gap-2">
                            <Link
                                :href="route('auth.events.show', event.id)"
                                class="inline-flex flex-1 items-center justify-center rounded-lg bg-buttonPrimary px-3 py-2 text-sm font-semibold text-buttonTextPrimary transition hover:bg-buttonPrimaryHover"
                            >
                                <i class="las la-eye mr-1"></i>
                                Details
                            </Link>
                            <Link
                                v-if="event.can_update"
                                :href="`${route('auth.events.show', event.id)}?edit=1`"
                                class="inline-flex flex-1 items-center justify-center rounded-lg border border-border px-3 py-2 text-sm font-semibold text-primary transition hover:border-borderHover hover:bg-muted"
                            >
                                <i class="las la-edit mr-1"></i>
                                Bearbeiten
                            </Link>
                        </div>
                    </div>
                </div>
            </article>
        </div>

        <div v-if="!events?.length" class="rounded-lg border border-border bg-card p-8 text-center text-secondary">
            Keine Events vorhanden.
        </div>
    </div>
</template>

<style scoped>
.date-input {
    color-scheme: dark;
}

.date-input::-webkit-calendar-picker-indicator {
    cursor: pointer;
    opacity: 1;
    filter: invert(1);
}
</style>
