<script setup>
import AppLayout from '@/Components/Auth/Layouts/AppLayout.vue'
import AppEmptyState from '@/Components/UI/AppEmptyState.vue'
import SavedViewBar from '@/Components/SavedViewBar.vue'
import { Head, Link, router, useForm, usePage } from '@inertiajs/vue3'
import { computed, onMounted, ref, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import { useSavedViews } from '@/composables/useSavedViews'
import { promptDialog } from '@/services/dialogService'

defineOptions({ layout: AppLayout })

const props = defineProps({
    events: { type: Object, default: () => ({ data: [] }) },
    calendarEvents: { type: Array, default: () => [] },
    eventStats: { type: Object, default: () => ({ upcoming: 0, today: 0, cancelled: 0 }) },
    nextEvent: { type: Object, default: null },
    calendar: { type: Object, default: () => ({}) },
    clubs: Array,
    teams: Array,
    eventTypes: Array,
    visibilities: Array,
    sports: { type: Array, default: () => [] },
    sportRoutes: { type: Array, default: () => [] },
    eventDefaults: { type: Object, default: () => ({}) },
    eventCreation: { type: Object, default: () => ({ allows_recurring: true }) },
    filters: { type: Object, default: () => ({}) },
})

const { t, locale } = useI18n()
const paginationLabel = (label) => String(label || '')
    .replace(/<[^>]*>/g, '')
    .replace(/&laquo;/g, '«')
    .replace(/&raquo;/g, '»')
    .replace(/&amp;/g, '&')
const page = usePage()

const showCreateModal = ref(false)
const filterPanelOpen = ref(false)
const createStep = ref(1)
const viewMode = ref('calendar')
const eventItems = computed(() => Array.isArray(props.events) ? props.events : (props.events?.data || []))
const eventPaginationLinks = computed(() => Array.isArray(props.events) ? [] : (props.events?.links || []))
const calendarEventItems = computed(() => props.calendarEvents || [])
const errors = computed(() => page.props.errors || {})
const authorizationMessage = computed(() => errors.value.authorization || page.props.flash?.upgrade_required?.message || '')
const allowsRecurringForSelection = computed(() => {
    if (props.eventCreation?.allows_recurring_globally === true) return true
    if (props.eventCreation?.allows_recurring_globally === undefined) {
        return props.eventCreation?.allows_recurring === true
    }

    if (form.visibility === 'organization') {
        return (props.eventCreation?.recurring_club_ids || []).some((id) => Number(id) === Number(form.club_id))
    }
    if (form.visibility === 'private') {
        return (props.eventCreation?.recurring_team_ids || []).some((id) => Number(id) === Number(form.team_id))
    }

    return false
})
const freeEventLimitMessage = computed(() => {
    if (!props.eventCreation?.is_free_limited || allowsRecurringForSelection.value) return ''

    return t('events.free_limit', {
        remaining: props.eventCreation.remaining_this_month ?? 0,
        limit: props.eventCreation.monthly_limit ?? 2,
    })
})

const steps = [
    { number: 1, label: 'events.steps.base' },
    { number: 2, label: 'events.steps.time' },
    { number: 3, label: 'events.steps.details' },
    { number: 4, label: 'events.steps.review' },
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
const dateKey = (value) => {
    const date = value instanceof Date ? value : new Date(value)

    return [
        date.getFullYear(),
        String(date.getMonth() + 1).padStart(2, '0'),
        String(date.getDate()).padStart(2, '0'),
    ].join('-')
}

const initialCalendarMonth = props.filters.calendar_month || props.calendar?.month || dateKey(new Date()).slice(0, 7)
const selectedCalendarDate = ref(dateKey(new Date()))
const calendarCursor = ref(new Date(`${initialCalendarMonth}-01T12:00:00`))
const calendarWeekdays = [
    'events.weekdays_short.monday',
    'events.weekdays_short.tuesday',
    'events.weekdays_short.wednesday',
    'events.weekdays_short.thursday',
    'events.weekdays_short.friday',
    'events.weekdays_short.saturday',
    'events.weekdays_short.sunday',
]

const form = useForm({
    club_id: props.clubs?.[0]?.id || '',
    team_id: '',
    sport_route_id: '',
    title: '',
    type: 'training',
    visibility: 'private',
    start_time: '',
    end_time: '',
    location: '',
    location_name: '',
    location_street: '',
    location_house_number: '',
    location_postal_code: '',
    location_city: '',
    location_country: 'DE',
    max_participants: '',
    uses_penalty_catalog: false,
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
    calendar_month: initialCalendarMonth,
})
const eventSavedViews = useSavedViews('events', t('search.saved_views_error'))

const filteredTeams = computed(() => {
    if (form.visibility === 'private') return props.teams || []
    if (!form.club_id) return props.teams || []

    return (props.teams || []).filter((team) => Number(team.club_id) === Number(form.club_id))
})

const filteredFilterTeams = computed(() => {
    if (!filterForm.value.club_id) return props.teams || []

    return (props.teams || []).filter((team) => Number(team.club_id) === Number(filterForm.value.club_id))
})

const selectedSportsCount = computed(() => (filterForm.value.sport_ids || []).length)
const selectedSportRoute = computed(() => props.sportRoutes.find((item) => Number(item.id) === Number(form.sport_route_id)) || null)
const routeEndpointsLabel = (sportRoute) => [sportRoute?.start_name, sportRoute?.end_name].filter(Boolean).join(' → ')
const routeDistanceLabel = (sportRoute) => sportRoute?.distance_meters
    ? `${(Number(sportRoute.distance_meters) / 1000).toLocaleString(locale.value, { maximumFractionDigits: 1 })} km`
    : ''

const activeFilterCount = computed(() => {
    const values = [
        filterForm.value.search,
        filterForm.value.type,
        filterForm.value.visibility,
        filterForm.value.club_id,
        filterForm.value.team_id,
        filterForm.value.radius_km,
    ].filter((value) => value !== '' && value !== null && value !== undefined)

    const periodChanged = filterForm.value.period && filterForm.value.period !== 'upcoming'

    return values.length + selectedSportsCount.value + (periodChanged ? 1 : 0)
})

const todayEventsCount = computed(() => Number(props.eventStats.today || 0))
const upcomingEventsCount = computed(() => Number(props.eventStats.upcoming || 0))
const cancelledEventsCount = computed(() => Number(props.eventStats.cancelled || 0))
const nextEvent = computed(() => props.nextEvent || null)

const localeCode = computed(() => ({ ar: 'ar-EG', fr: 'fr-FR', en: 'en-US', de: 'de-DE' })[locale.value] || 'de-DE')
const calendarMonthLabel = computed(() => new Intl.DateTimeFormat(localeCode.value, {
    month: 'long',
    year: 'numeric',
}).format(calendarCursor.value))

const calendarEventsByDate = computed(() => calendarEventItems.value.reduce((days, event) => {
    const key = dateKey(event.start_time)
    days[key] ||= []
    days[key].push(event)

    return days
}, {}))

const calendarDays = computed(() => {
    const year = calendarCursor.value.getFullYear()
    const month = calendarCursor.value.getMonth()
    const firstOfMonth = new Date(year, month, 1)
    const mondayOffset = (firstOfMonth.getDay() + 6) % 7
    const start = new Date(year, month, 1 - mondayOffset)

    return Array.from({ length: 42 }, (_, index) => {
        const date = new Date(start)
        date.setDate(start.getDate() + index)

        const key = dateKey(date)

        return {
            date,
            key,
            day: date.getDate(),
            isCurrentMonth: date.getMonth() === month,
            isToday: key === dateKey(new Date()),
            events: calendarEventsByDate.value[key] || [],
        }
    })
})

const selectedCalendarEvents = computed(() => [...(calendarEventsByDate.value[selectedCalendarDate.value] || [])]
    .sort((a, b) => new Date(a.start_time) - new Date(b.start_time)))

const moveCalendarMonth = (direction) => {
    const nextCursor = new Date(
        calendarCursor.value.getFullYear(),
        calendarCursor.value.getMonth() + direction,
        1,
    )
    const month = dateKey(nextCursor).slice(0, 7)

    calendarCursor.value = nextCursor
    filterForm.value.calendar_month = month

    router.get(route('auth.events.index'), filterPayload({ calendar_month: month }), {
        preserveScroll: true,
        preserveState: true,
        replace: true,
    })
}

const selectCalendarDay = (day) => {
    selectedCalendarDate.value = day.key
    calendarCursor.value = new Date(day.date.getFullYear(), day.date.getMonth(), 1)
}

const jumpToToday = () => {
    const today = new Date()
    const month = dateKey(today).slice(0, 7)

    selectedCalendarDate.value = dateKey(today)
    calendarCursor.value = new Date(today.getFullYear(), today.getMonth(), 1)
    filterForm.value.calendar_month = month

    router.get(route('auth.events.index'), filterPayload({ calendar_month: month }), {
        preserveScroll: true,
        preserveState: true,
        replace: true,
    })
}

watch(() => form.club_id, () => {
    if (form.team_id && !filteredTeams.value.some((team) => Number(team.id) === Number(form.team_id))) {
        form.team_id = ''
    }
})

watch(() => form.visibility, (visibility) => {
    if (visibility === 'public') {
        form.club_id = ''
        form.team_id = ''
        form.uses_penalty_catalog = false
        return
    }

    if (visibility === 'organization') {
        form.team_id = ''
        form.uses_penalty_catalog = false
        if (!form.club_id && props.clubs?.length) {
            form.club_id = props.clubs[0].id
        }
        return
    }

    if (visibility === 'private') {
        form.club_id = ''
    }
})

watch(() => filterForm.value.club_id, () => {
    if (filterForm.value.team_id && !filteredFilterTeams.value.some((team) => Number(team.id) === Number(filterForm.value.team_id))) {
        filterForm.value.team_id = ''
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
        'sport_route_id',
        'start_time',
        'end_time',
        'location',
        'location_name',
        'location_street',
        'location_house_number',
        'location_postal_code',
        'location_city',
        'location_country',
        'max_participants',
        'uses_penalty_catalog',
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

const stepValidationMessage = (step) => {
    if (step === 1) {
        if (!String(form.title || '').trim()) {
            return t('events.validation.title')
        }

        if (form.visibility === 'private' && !form.team_id) {
            return t('events.validation.private_team')
        }

        if (form.visibility === 'organization' && !form.club_id) {
            return t('events.validation.organization_club')
        }
    }

    if (step === 2) {
        if (!form.start_time) {
            return t('events.validation.start')
        }

        if (form.end_time && new Date(form.end_time) < new Date(form.start_time)) {
            return t('events.validation.end_before_start')
        }

        if (form.reminder_at && new Date(form.reminder_at) > new Date(form.start_time)) {
            return t('events.validation.reminder_after_start')
        }
    }

    if (step === 3) {
        if (form.recurring && !form.recurrence_ends_at) {
            return t('events.validation.recurrence_end')
        }

        if (form.recurring && form.start_time && form.recurrence_ends_at) {
            const startDate = new Date(form.start_time)
            const recurrenceEnd = new Date(`${form.recurrence_ends_at}T23:59:59`)

            if (recurrenceEnd < startDate) {
                return t('events.validation.recurrence_before_start')
            }
        }

        if (['weekly', 'biweekly'].includes(form.recurring) && !form.recurrence_days.length) {
            return t('events.validation.recurrence_days')
        }

        if (form.max_participants && Number(form.max_participants) < 1) {
            return t('events.validation.participants')
        }
    }

    return ''
}

const currentStepValidationMessage = computed(() => stepValidationMessage(createStep.value))

const canEnterStep = (step) => {
    if (step <= createStep.value) {
        return true
    }

    for (let index = 1; index < step; index++) {
        if (stepValidationMessage(index)) {
            return false
        }
    }

    return true
}

const goToStep = (step) => {
    if (canEnterStep(step)) {
        createStep.value = step
    }
}

const nextStep = () => {
    if (currentStepValidationMessage.value) {
        return
    }

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
    for (let step = 1; step <= 3; step++) {
        if (stepValidationMessage(step)) {
            createStep.value = step

            return
        }
    }

    form.event_timezone = browserTimeZone()
    form.location = [
        form.location_name,
        [form.location_street, form.location_house_number].filter(Boolean).join(' '),
        [form.location_postal_code, form.location_city].filter(Boolean).join(' '),
    ].filter(Boolean).join(', ')

    if (form.visibility === 'public') {
        form.club_id = ''
        form.team_id = ''
        form.uses_penalty_catalog = false
    } else if (form.visibility === 'organization') {
        form.team_id = ''
        form.uses_penalty_catalog = false
    } else if (form.visibility === 'private') {
        form.club_id = ''
    }

    if (!allowsRecurringForSelection.value) {
        form.recurring = ''
        form.recurrence_days = []
        form.recurrence_ends_at = ''
    }

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
    if (form.visibility === 'public') return t('events.none.not_required')
    if (form.visibility === 'private') return t('events.none.team_assigned')

    return props.clubs?.find((club) => Number(club.id) === Number(form.club_id))?.name || '-'
})

const selectedTeamName = computed(() => {
    if (form.visibility !== 'private') return t('events.none.not_required')

    return props.teams?.find((team) => Number(team.id) === Number(form.team_id))?.name || '-'
})

const eventTimeZone = (event = null) => event?.event_timezone || browserTimeZone()

const formatDate = (date, event = null) => {
    if (!date) return ''

    return new Intl.DateTimeFormat(localeCode.value, {
        timeZone: eventTimeZone(event),
        weekday: 'short',
        day: '2-digit',
        month: '2-digit',
        year: 'numeric',
    }).format(new Date(date))
}

const formatTime = (date, event = null) => {
    if (!date) return ''

    return new Intl.DateTimeFormat(localeCode.value, {
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

    return new Intl.DateTimeFormat(localeCode.value, {
        timeZone: eventTimeZone(event),
        day: '2-digit',
    }).format(new Date(date))
}

const formatMonthShort = (date, event = null) => {
    if (!date) return ''

    return new Intl.DateTimeFormat(localeCode.value, {
        timeZone: eventTimeZone(event),
        month: 'short',
    })
        .format(new Date(date))
        .replace('.', '')
        .toUpperCase()
}

const formatWeekdayShort = (date, event = null) => {
    if (!date) return ''

    return new Intl.DateTimeFormat(localeCode.value, {
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

const acceptedParticipantsCount = (event) => Number(event.accepted_participants_count || 0)
const hasParticipantLimit = (event) => Number(event.max_participants || 0) > 0
const isEventFullForYes = (event) => hasParticipantLimit(event)
    && acceptedParticipantsCount(event) >= Number(event.max_participants)
    && event.current_participant_status !== 'yes'

const participantCapacityLabel = (event) => {
    if (!hasParticipantLimit(event)) {
        return `${acceptedParticipantsCount(event)} Zusagen`
    }

    return `${acceptedParticipantsCount(event)}/${event.max_participants} Plätze`
}

const rsvpButtonClass = (event, status) => event.current_participant_status === status
    ? rsvpOptions.find((option) => option.value === status)?.active
    : 'border-border bg-inputBg text-secondary hover:border-borderHover hover:text-primary'

const setParticipation = (event, status) => {
    if (event.status === 'cancelled') return
    if (status === 'yes' && isEventFullForYes(event)) return

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

const filterPayload = (extra = {}) => ({
    search: filterForm.value.search || undefined,
    type: filterForm.value.type || undefined,
    visibility: filterForm.value.visibility || undefined,
    club_id: filterForm.value.club_id || undefined,
    team_id: filterForm.value.team_id || undefined,
    period: filterForm.value.period || undefined,
    radius_km: filterForm.value.radius_km || undefined,
    sport_ids: filterForm.value.sport_ids?.length ? filterForm.value.sport_ids : undefined,
    calendar_month: extra.calendar_month || filterForm.value.calendar_month || dateKey(calendarCursor.value).slice(0, 7),
})

const applyEventSavedView = (view) => {
    const configuration = view.configuration || {}
    const filters = configuration.filters || {}
    filterForm.value = {
        ...filterForm.value,
        search: configuration.query || '',
        type: filters.type || '',
        visibility: filters.visibility || '',
        club_id: filters.club_id || '',
        team_id: filters.team_id || '',
        period: filters.period || 'upcoming',
        radius_km: filters.radius_km || '',
        sport_ids: Array.isArray(filters.sport_ids) ? filters.sport_ids : [],
        calendar_month: filters.calendar_month || initialCalendarMonth,
    }
    applyFilters()
}

const saveEventView = async () => {
    const name = await promptDialog({
        title: t('search.save_view'),
        inputLabel: t('search.saved_view_name'),
        required: true,
        minLength: 1,
    })
    if (!name?.trim()) return
    await eventSavedViews.save(name.trim(), {
        query: filterForm.value.search || '',
        filters: {
            type: filterForm.value.type || '',
            visibility: filterForm.value.visibility || '',
            club_id: filterForm.value.club_id || '',
            team_id: filterForm.value.team_id || '',
            period: filterForm.value.period || 'upcoming',
            radius_km: filterForm.value.radius_km || '',
            sport_ids: filterForm.value.sport_ids || [],
            calendar_month: filterForm.value.calendar_month || initialCalendarMonth,
        },
    })
}

onMounted(() => {
    void eventSavedViews.load()
})

const applyFilters = () => {
    filterForm.value.calendar_month = dateKey(calendarCursor.value).slice(0, 7)

    router.get(route('auth.events.index'), filterPayload(), {
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
        calendar_month: dateKey(new Date()).slice(0, 7),
    }
    calendarCursor.value = new Date(`${filterForm.value.calendar_month}-01T12:00:00`)
    selectedCalendarDate.value = dateKey(new Date())

    applyFilters()
}
</script>

<template>

    <Head :title="$t('events.title')" />

    <div class="space-y-5">
        <section class="overflow-hidden rounded-xl border border-border bg-card">
            <div class="flex flex-col gap-4 p-4 sm:p-5 lg:flex-row lg:items-start lg:justify-between">
                <div class="min-w-0">
                    <div class="flex flex-wrap items-center gap-2">
                        <span class="inline-flex h-9 w-9 items-center justify-center rounded-lg bg-buttonPrimary text-buttonTextPrimary">
                            <i class="las la-calendar-check text-xl"></i>
                        </span>
                        <p class="text-xs font-semibold uppercase tracking-wide text-secondary">{{ $t('Events & Training') }}</p>
                    </div>

                    <h1 class="mt-3 text-2xl font-bold text-primary sm:text-3xl">
                        {{ $t('events.title') }}
                    </h1>

                    <p class="mt-2 max-w-2xl text-sm leading-6 text-secondary">
                        {{ $t('events.subtitle') }}
                    </p>

                    <div v-if="nextEvent" class="mt-4 flex flex-wrap items-center gap-2 text-sm">
                        <span class="rounded-full bg-air-green/10 px-3 py-1 font-semibold text-air-green">
                            {{ $t('events.next_occurrence') }}
                        </span>
                        <span class="text-secondary">
                            {{ nextEvent.title }} · {{ eventDateTimeLabel(nextEvent) }}
                        </span>
                    </div>
                </div>

                <button
                    type="button"
                    class="inline-flex min-h-11 w-full shrink-0 items-center justify-center gap-2 rounded-xl bg-buttonPrimary px-4 py-3 text-sm font-semibold text-buttonTextPrimary transition hover:bg-buttonPrimaryHover sm:w-auto"
                    @click="openCreateModal"
                >
                    <i class="las la-plus text-lg"></i>
                    {{ $t('events.create') }}
                </button>
            </div>

            <div class="grid grid-cols-3 gap-2 border-t border-border p-3 sm:gap-0 sm:p-0">
                <div class="rounded-xl border border-border bg-inputBg p-3 text-center sm:rounded-none sm:border-0 sm:border-r sm:bg-transparent sm:p-4 sm:text-left">
                    <p class="text-[10px] font-semibold uppercase text-secondary sm:text-xs sm:tracking-wide">{{ $t('events.next_occurrence') }}</p>
                    <p class="mt-1 text-xl font-bold text-primary sm:text-2xl">{{ upcomingEventsCount }}</p>
                </div>
                <div class="rounded-xl border border-border bg-inputBg p-3 text-center sm:rounded-none sm:border-0 sm:border-r sm:bg-transparent sm:p-4 sm:text-left">
                    <p class="text-[10px] font-semibold uppercase text-secondary sm:text-xs sm:tracking-wide">{{ $t('Heute') }}</p>
                    <p class="mt-1 text-xl font-bold text-primary sm:text-2xl">{{ todayEventsCount }}</p>
                </div>
                <div class="rounded-xl border border-border bg-inputBg p-3 text-center sm:rounded-none sm:border-0 sm:bg-transparent sm:p-4 sm:text-left">
                    <p class="text-[10px] font-semibold uppercase text-secondary sm:text-xs sm:tracking-wide">{{ $t('Abgesagt') }}</p>
                    <p class="mt-1 text-xl font-bold text-primary sm:text-2xl">{{ cancelledEventsCount }}</p>
                </div>
            </div>
        </section>

        <div v-if="authorizationMessage" class="rounded-lg border border-warning/40 bg-warning/10 p-4 text-sm text-warning">
            <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                <p class="font-semibold">
                    {{ authorizationMessage }}
                </p>
                <Link :href="route('guest.pricing')" class="shrink-0 rounded-lg bg-buttonPrimary px-4 py-2 text-center text-sm font-semibold text-buttonTextPrimary hover:bg-buttonPrimaryHover">
                    {{ $t('Upgrade ansehen') }}
                </Link>
            </div>
        </div>

        <section class="rounded-lg border border-border bg-card p-4">
            <form class="space-y-4" @submit.prevent="applyFilters">
                <div class="flex flex-col gap-3 lg:flex-row">
                    <label class="relative min-w-0 flex-1" for="event-search">
                        <span class="sr-only">{{ $t('Suchen') }}</span>
                        <i class="las la-search absolute left-3 top-1/2 -translate-y-1/2 text-xl text-secondary"></i>
                        <input
                            id="event-search"
                            v-model="filterForm.search"
                            class="h-12 w-full rounded-lg border border-border bg-inputBg pl-10 pr-3 text-sm text-primary placeholder-secondary focus:border-borderHover focus:ring-borderHover"
                            :placeholder="$t('Suche nach Titel, Ort, Team oder Verein')"
                        >
                    </label>

                    <div class="grid grid-cols-3 gap-2 sm:flex sm:shrink-0">
                        <button
                            type="button"
                            class="rounded-lg border px-3 py-2 text-sm font-semibold transition"
                            :class="filterForm.period === 'upcoming' ? 'border-buttonPrimary bg-buttonPrimary text-buttonTextPrimary' : 'border-border text-secondary hover:border-borderHover hover:text-primary'"
                            @click="filterForm.period = 'upcoming'; applyFilters()"
                        >
                            {{ $t('Kommend') }}
                        </button>
                        <button
                            type="button"
                            class="rounded-lg border px-3 py-2 text-sm font-semibold transition"
                            :class="filterForm.period === 'past' ? 'border-buttonPrimary bg-buttonPrimary text-buttonTextPrimary' : 'border-border text-secondary hover:border-borderHover hover:text-primary'"
                            @click="filterForm.period = 'past'; applyFilters()"
                        >
                            {{ $t('Vergangen') }}
                        </button>
                        <button
                            type="button"
                            class="rounded-lg border px-3 py-2 text-sm font-semibold transition"
                            :class="filterForm.period === 'all' ? 'border-buttonPrimary bg-buttonPrimary text-buttonTextPrimary' : 'border-border text-secondary hover:border-borderHover hover:text-primary'"
                            @click="filterForm.period = 'all'; applyFilters()"
                        >
                            {{ $t('Alle') }}
                        </button>
                    </div>

                    <button
                        type="button"
                        class="inline-flex h-12 items-center justify-center gap-2 rounded-lg border border-border px-4 text-sm font-semibold text-primary transition hover:border-borderHover hover:bg-muted"
                        @click="filterPanelOpen = !filterPanelOpen"
                    >
                        <i class="las la-sliders-h text-lg"></i>
                        {{ $t('Filter') }}
                        <span v-if="activeFilterCount" class="rounded-full bg-buttonPrimary px-2 py-0.5 text-xs text-buttonTextPrimary">
                            {{ activeFilterCount }}
                        </span>
                    </button>

                    <button class="h-12 rounded-lg bg-buttonPrimary px-5 text-sm font-semibold text-buttonTextPrimary transition hover:bg-buttonPrimaryHover">
                        {{ $t('Suchen') }}
                    </button>
                    </div>

                    <SavedViewBar
                        :views="eventSavedViews.views.value"
                        :loading="eventSavedViews.loading.value"
                        :error="eventSavedViews.error.value"
                        @apply="applyEventSavedView"
                        @save="saveEventView"
                        @remove="eventSavedViews.remove"
                    />

                    <div v-if="filterPanelOpen" class="rounded-lg border border-border bg-inputBg p-3">
                    <div class="grid gap-3 md:grid-cols-2 xl:grid-cols-4">
                        <div>
                            <label class="mb-1 block text-xs font-semibold uppercase tracking-wide text-secondary" for="event-filter-type">
                                {{ $t('events.fields.type') }}
                            </label>
                            <select id="event-filter-type" v-model="filterForm.type" class="h-11 w-full rounded-lg border border-border bg-card px-3 text-sm text-primary focus:border-borderHover focus:ring-borderHover">
                                <option value="">{{ $t('guest.events.all_types') }}</option>
                                <option v-for="type in eventTypes" :key="type" :value="type">
                                    {{ $t(typeLabels[type] || type) }}
                                </option>
                            </select>
                        </div>

                        <div>
                            <label class="mb-1 block text-xs font-semibold uppercase tracking-wide text-secondary" for="event-filter-visibility">
                                {{ $t('events.fields.visibility') }}
                            </label>
                            <select id="event-filter-visibility" v-model="filterForm.visibility" class="h-11 w-full rounded-lg border border-border bg-card px-3 text-sm text-primary focus:border-borderHover focus:ring-borderHover">
                                <option value="">{{ $t('Alle') }}</option>
                                <option v-for="visibility in visibilities" :key="visibility" :value="visibility">
                                    {{ $t(visibilityLabels[visibility] || visibility) }}
                                </option>
                            </select>
                        </div>

                        <div>
                            <label class="mb-1 block text-xs font-semibold uppercase tracking-wide text-secondary" for="event-filter-club">
                                {{ $t('events.fields.club') }}
                            </label>
                            <select id="event-filter-club" v-model="filterForm.club_id" class="h-11 w-full rounded-lg border border-border bg-card px-3 text-sm text-primary focus:border-borderHover focus:ring-borderHover">
                                <option value="">{{ $t('Alle Vereine') }}</option>
                                <option v-for="club in clubs" :key="club.id" :value="club.id">
                                    {{ club.name }}
                                </option>
                            </select>
                        </div>

                        <div>
                            <label class="mb-1 block text-xs font-semibold uppercase tracking-wide text-secondary" for="event-filter-team">
                                {{ $t('events.fields.team') }}
                            </label>
                            <select id="event-filter-team" v-model="filterForm.team_id" class="h-11 w-full rounded-lg border border-border bg-card px-3 text-sm text-primary focus:border-borderHover focus:ring-borderHover">
                                <option value="">{{ $t('Alle Teams') }}</option>
                                <option v-for="team in filteredFilterTeams" :key="team.id" :value="team.id">
                                    {{ team.name }}
                                </option>
                            </select>
                        </div>

                        <div>
                            <label class="mb-1 block text-xs font-semibold uppercase tracking-wide text-secondary" for="event-filter-radius">
                                {{ $t('PLZ-/Stadt-Nähe') }}
                            </label>
                            <div class="flex h-11 items-center gap-2 rounded-lg border border-border bg-card px-3">
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
                            <p class="mt-1 text-xs text-secondary">
                                {{ $t('Näherung über dein Profil, PLZ und Stadt.') }}
                            </p>
                        </div>

                        <div class="md:col-span-2 xl:col-span-4">
                            <div class="mb-2 flex items-center justify-between gap-3">
                                <label class="block text-xs font-semibold uppercase tracking-wide text-secondary">
                                    {{ $t('Sportarten') }}
                                </label>
                                <span class="text-xs font-semibold text-secondary">
                                    {{ selectedSportsCount }} {{ $t('ausgewählt') }}
                                </span>
                            </div>
                            <div class="flex max-h-32 flex-wrap gap-2 overflow-y-auto rounded-lg border border-border bg-card p-2">
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
                    </div>

                    <div class="mt-3 flex flex-col gap-2 sm:flex-row sm:justify-end">
                        <button type="button" class="h-11 rounded-lg border border-border px-4 text-sm font-semibold text-secondary transition hover:border-borderHover hover:text-primary" @click="resetFilters">
                            {{ $t('Zurücksetzen') }}
                        </button>
                        <button type="button" class="h-11 rounded-lg border border-buttonPrimary px-4 text-sm font-semibold text-buttonPrimary transition hover:bg-buttonPrimary/10" @click="saveDefaultFilters">
                            {{ $t('Als Standard speichern') }}
                        </button>
                        <button class="h-11 rounded-lg bg-buttonPrimary px-5 text-sm font-semibold text-buttonTextPrimary transition hover:bg-buttonPrimaryHover">
                            {{ $t('Filter anwenden') }}
                        </button>
                    </div>
                </div>
            </form>
        </section>

        <section v-if="eventItems.length || calendarEventItems.length" class="rounded-lg border border-border bg-card">
            <div class="flex flex-col gap-3 border-b border-border p-4 sm:flex-row sm:items-center sm:justify-between">
                <div>
                    <p class="text-xs font-semibold uppercase tracking-wide text-secondary">{{ $t('Ansicht') }}</p>
                    <h2 class="mt-1 text-lg font-bold text-primary">{{ $t('Kalender & Liste') }}</h2>
                </div>

                <div class="grid grid-cols-2 gap-2 rounded-lg border border-border bg-inputBg p-1">
                    <button
                        type="button"
                        class="rounded-md px-3 py-2 text-sm font-semibold transition"
                        :class="viewMode === 'calendar' ? 'bg-buttonPrimary text-buttonTextPrimary' : 'text-secondary hover:text-primary'"
                        @click="viewMode = 'calendar'"
                    >
                        <i class="las la-calendar mr-1"></i>
                        {{ $t('Kalender') }}
                    </button>
                    <button
                        type="button"
                        class="rounded-md px-3 py-2 text-sm font-semibold transition"
                        :class="viewMode === 'list' ? 'bg-buttonPrimary text-buttonTextPrimary' : 'text-secondary hover:text-primary'"
                        @click="viewMode = 'list'"
                    >
                        <i class="las la-list mr-1"></i>
                        {{ $t('Liste') }}
                    </button>
                </div>
            </div>

            <div v-if="viewMode === 'calendar'" class="grid gap-0 lg:grid-cols-[minmax(0,1fr)_22rem]">
                <div class="border-b border-border p-4 lg:border-b-0 lg:border-r">
                    <div class="mb-4 flex items-center justify-between gap-3">
                        <button
                            type="button"
                            class="flex h-10 w-10 items-center justify-center rounded-lg border border-border text-secondary hover:border-borderHover hover:text-primary"
                            :aria-label="$t('Vorheriger Monat')"
                            @click="moveCalendarMonth(-1)"
                        >
                            <i class="las la-angle-left text-xl"></i>
                        </button>

                        <div class="flex flex-col items-center gap-2 sm:flex-row">
                            <h3 class="text-center text-base font-bold capitalize text-primary">
                                {{ calendarMonthLabel }}
                            </h3>
                            <button
                                type="button"
                                class="rounded-lg border border-border px-3 py-1.5 text-xs font-semibold text-secondary hover:border-borderHover hover:text-primary"
                                @click="jumpToToday"
                            >
                                {{ $t('Heute') }}
                            </button>
                        </div>

                        <button
                            type="button"
                            class="flex h-10 w-10 items-center justify-center rounded-lg border border-border text-secondary hover:border-borderHover hover:text-primary"
                            :aria-label="$t('Nächster Monat')"
                            @click="moveCalendarMonth(1)"
                        >
                            <i class="las la-angle-right text-xl"></i>
                        </button>
                    </div>

                    <div class="grid grid-cols-7 gap-px overflow-hidden rounded-lg border border-border bg-border">
                        <div
                            v-for="weekday in calendarWeekdays"
                            :key="weekday"
                            class="bg-inputBg px-2 py-2 text-center text-xs font-bold uppercase text-secondary"
                        >
                            {{ $t(weekday) }}
                        </div>

                        <button
                            v-for="day in calendarDays"
                            :key="day.key"
                            type="button"
                            class="min-h-24 bg-card p-2 text-left transition hover:bg-inputBg"
                            :class="[
                                !day.isCurrentMonth ? 'opacity-45' : '',
                                selectedCalendarDate === day.key ? 'ring-2 ring-inset ring-buttonPrimary' : '',
                            ]"
                            @click="selectCalendarDay(day)"
                        >
                            <span
                                class="inline-flex h-7 w-7 items-center justify-center rounded-full text-xs font-bold"
                                :class="day.isToday ? 'bg-buttonPrimary text-buttonTextPrimary' : 'text-primary'"
                            >
                                {{ day.day }}
                            </span>

                            <div class="mt-2 space-y-1">
                                <span
                                    v-for="event in day.events.slice(0, 2)"
                                    :key="event.id"
                                    class="block truncate rounded px-2 py-1 text-[11px] font-semibold"
                                    :class="event.status === 'cancelled' ? 'bg-error/10 text-error line-through' : 'bg-buttonPrimary/10 text-buttonPrimary'"
                                >
                                    {{ formatTime(event.start_time, event) }} {{ event.title }}
                                </span>
                                <span v-if="day.events.length > 2" class="block text-[11px] font-semibold text-secondary">
                                    +{{ day.events.length - 2 }} mehr
                                </span>
                            </div>
                        </button>
                    </div>
                </div>

                <aside class="p-4">
                    <p class="text-xs font-semibold uppercase tracking-wide text-secondary">
                        Ausgewählter Tag
                    </p>
                    <h3 class="mt-1 text-lg font-bold text-primary">
                        {{ formatDate(`${selectedCalendarDate}T12:00:00`) }}
                    </h3>

                    <div class="mt-4 space-y-3">
                        <article
                            v-for="event in selectedCalendarEvents"
                            :key="event.id"
                            class="rounded-lg border border-border bg-inputBg p-3"
                        >
                            <div class="flex items-start justify-between gap-3">
                                <div class="min-w-0">
                                    <Link :href="route('auth.events.show', event.id)" class="font-bold text-primary hover:text-buttonPrimary hover:underline">
                                        {{ event.title }}
                                    </Link>
                                    <p class="mt-1 text-sm text-secondary">
                                        {{ eventDateTimeLabel(event) }}
                                    </p>
                                </div>
                                <span class="shrink-0 rounded-full bg-card px-2 py-1 text-xs font-semibold text-secondary">
                                    {{ participantCapacityLabel(event) }}
                                </span>
                            </div>
                            <p class="mt-2 truncate text-sm text-secondary">
                                <i class="las la-map-marker-alt text-buttonPrimary"></i>
                                {{ event.location || 'Keine Eingabe' }}
                            </p>
                            <p v-if="event.sport_route_reference" class="mt-1 truncate text-xs font-semibold text-buttonPrimary">
                                <i class="las la-route"></i>
                                {{ event.sport_route_reference.title }}
                            </p>
                        </article>

                        <div v-if="!selectedCalendarEvents.length" class="rounded-lg border border-dashed border-border p-5 text-center text-sm text-secondary">
                            An diesem Tag sind keine Events im aktuellen Filter.
                        </div>
                    </div>
                </aside>
            </div>
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
                                class="rounded-full px-2 py-2 text-xs font-semibold transition disabled:cursor-not-allowed disabled:opacity-45" :class="createStep === step.number
                                    ? 'bg-buttonPrimary text-buttonTextPrimary'
                                    : createStep > step.number
                                        ? 'bg-air-green/15 text-air-green'
                                        : 'bg-inputBg text-secondary'"
                                :disabled="!canEnterStep(step.number)"
                                @click="goToStep(step.number)">
                                {{ $t(step.label) }}
                            </button>
                        </div>
                    </div>

                    <!-- Modal Body -->
                    <form class="min-h-0 flex-1 overflow-y-auto p-4" @submit.prevent="submit">
                        <!-- STEP 1 -->
                        <section v-if="createStep === 1" class="space-y-4">
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

                            <div v-else-if="freeEventLimitMessage" class="rounded-lg border border-air-blue/40 bg-air-blue/10 p-3 text-sm text-air-blue">
                                <p class="font-semibold">{{ freeEventLimitMessage }}</p>
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

                            <div class="rounded-lg border border-border bg-inputBg p-4">
                                <label for="event-sport-route" class="block text-sm font-semibold text-primary">
                                    {{ $t('events.route.label') }}
                                </label>
                                <select
                                    id="event-sport-route"
                                    v-model="form.sport_route_id"
                                    class="mt-2 w-full rounded-lg border border-border bg-card px-3 py-3 text-primary focus:border-borderHover focus:ring-borderHover"
                                >
                                    <option value="">{{ $t('events.route.none') }}</option>
                                    <option v-for="sportRoute in sportRoutes" :key="sportRoute.id" :value="sportRoute.id">
                                        {{ sportRoute.title }}{{ routeDistanceLabel(sportRoute) ? ` · ${routeDistanceLabel(sportRoute)}` : '' }}
                                    </option>
                                </select>
                                <p v-if="selectedSportRoute && routeEndpointsLabel(selectedSportRoute)" class="mt-2 text-xs font-semibold text-primary">
                                    {{ routeEndpointsLabel(selectedSportRoute) }}
                                </p>
                                <p class="mt-2 text-xs leading-relaxed text-secondary">
                                    {{ $t('events.route.share_hint') }}
                                </p>
                                <p v-if="form.errors.sport_route_id" class="mt-2 text-sm text-error">{{ form.errors.sport_route_id }}</p>
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

                            <div v-if="form.visibility !== 'public'" class="grid grid-cols-1 gap-3">
                                <div v-if="form.visibility === 'organization'">
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

                                <div v-if="form.visibility === 'private'">
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

                                    <div v-if="form.errors.club_id" class="mt-1 text-sm text-error">
                                        {{ form.errors.club_id }}
                                    </div>

                                    <div v-if="form.errors.team_id" class="mt-1 text-sm text-error">
                                        {{ form.errors.team_id }}
                                    </div>
                                </div>
                            </div>
                        </section>

                        <!-- STEP 2 -->
                        <section v-if="createStep === 2" class="space-y-4">
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
                        <section v-if="createStep === 3" class="space-y-4">
                            <div>
                                <h3 class="text-base font-semibold text-primary">
                                    Details & Wiederholung
                                </h3>

                                <p class="mt-1 text-sm text-secondary">
                                    Optional: Ort, Notizen und Wiederholung hinzufügen.
                                </p>
                            </div>

                            <div v-if="allowsRecurringForSelection">
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

                            <div v-else class="rounded-lg border border-border bg-inputBg p-3 text-sm text-secondary">
                <p class="font-semibold text-primary">{{ $t('Keine Intervalle im kostenlosen Konto') }}</p>
                <p class="mt-1">{{ $t('Du kannst einfache Einzel-Events erstellen. Wiederholungen sind ab einem passenden Paket verfügbar.') }}</p>
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

                            <div class="grid grid-cols-1 gap-3 sm:grid-cols-2">
                                <div class="sm:col-span-2">
                    <label for="event-location-name" class="block text-sm font-semibold text-primary">{{ $t('Ort / Treffpunkt') }}</label>
                                    <input id="event-location-name" v-model="form.location_name"
                                        class="mt-1 w-full rounded-lg border border-border bg-inputBg px-3 py-3 text-primary placeholder-secondary focus:border-borderHover focus:ring-borderHover"
                                        :placeholder="$t('z. B. Waldhaus, Sporthalle, Vereinsheim')">
                                </div>

                                <div>
                    <label for="event-location-street" class="block text-sm font-semibold text-primary">{{ $t('Straße') }}</label>
                                    <input id="event-location-street" v-model="form.location_street"
                                        class="mt-1 w-full rounded-lg border border-border bg-inputBg px-3 py-3 text-primary placeholder-secondary focus:border-borderHover focus:ring-borderHover"
                                        :placeholder="$t('Straße')">
                                </div>

                                <div>
                    <label for="event-location-house-number" class="block text-sm font-semibold text-primary">{{ $t('Nr.') }}</label>
                                    <input id="event-location-house-number" v-model="form.location_house_number"
                                        class="mt-1 w-full rounded-lg border border-border bg-inputBg px-3 py-3 text-primary placeholder-secondary focus:border-borderHover focus:ring-borderHover"
                                        :placeholder="$t('10')">
                                </div>

                                <div>
                    <label for="event-location-postal-code" class="block text-sm font-semibold text-primary">{{ $t('PLZ') }}</label>
                                    <input id="event-location-postal-code" v-model="form.location_postal_code"
                                        class="mt-1 w-full rounded-lg border border-border bg-inputBg px-3 py-3 text-primary placeholder-secondary focus:border-borderHover focus:ring-borderHover"
                                        :placeholder="$t('66119')">
                                </div>

                                <div>
                    <label for="event-location-city" class="block text-sm font-semibold text-primary">{{ $t('Stadt') }}</label>
                                    <input id="event-location-city" v-model="form.location_city"
                                        class="mt-1 w-full rounded-lg border border-border bg-inputBg px-3 py-3 text-primary placeholder-secondary focus:border-borderHover focus:ring-borderHover"
                                        :placeholder="$t('Saarbrücken')">
                                </div>

                                <div>
                    <label for="event-location-country" class="block text-sm font-semibold text-primary">{{ $t('Land') }}</label>
                                    <input id="event-location-country" v-model="form.location_country" maxlength="2"
                                        class="mt-1 w-full rounded-lg border border-border bg-inputBg px-3 py-3 uppercase text-primary placeholder-secondary focus:border-borderHover focus:ring-borderHover"
                                        :placeholder="$t('DE')">
                                </div>
                            </div>

                            <div>
                                <label for="event-max-participants" class="block text-sm font-semibold text-primary">
                                    Maximale Teilnehmerzahl
                                </label>

                                <input id="event-max-participants" v-model="form.max_participants"
                                    class="mt-1 w-full rounded-lg border border-border bg-inputBg px-3 py-3 text-primary placeholder-secondary focus:border-borderHover focus:ring-borderHover"
                                    type="number" min="1" max="100000" inputmode="numeric" :placeholder="$t('Leer lassen = unbegrenzt')">

                                <p class="mt-1 text-xs text-secondary">
                                    Nur Zusagen zählen gegen diese Grenze. Vielleicht und Absagen bleiben möglich.
                                </p>

                                <div v-if="form.errors.max_participants" class="mt-1 text-sm text-error">
                                    {{ form.errors.max_participants }}
                                </div>
                            </div>

                            <label
                                class="flex items-start gap-3 rounded-lg border border-border bg-inputBg p-4 text-sm"
                                :class="form.visibility === 'private' && form.team_id ? 'text-primary' : 'opacity-60'"
                            >
                                <input
                                    v-model="form.uses_penalty_catalog"
                                    type="checkbox"
                                    class="mt-1 rounded border-border bg-card"
                                    :disabled="form.visibility !== 'private' || !form.team_id"
                                >
                                <span>
                                    <span class="block font-semibold">{{ $t('Mit Strafkatalog arbeiten') }}</span>
                                    <span class="mt-1 block text-secondary">
                                        Berechtigte Teamrollen können während des Events anwesenden Spielern Strafen aus der Mannschaftskasse zuweisen.
                                    </span>
                                </span>
                            </label>

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
                        <section v-if="createStep === 4" class="space-y-4">
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
                                        Teilnehmerlimit
                                    </p>
                                    <p class="mt-1 text-primary">
                                        {{ form.max_participants ? `${form.max_participants} Personen` : 'Unbegrenzt' }}
                                    </p>
                                </div>

                                <div>
                                    <p class="text-xs font-semibold uppercase tracking-wide text-secondary">
                                        Strafkatalog
                                    </p>
                                    <p class="mt-1 text-primary">
                                        {{ form.uses_penalty_catalog ? 'Aktiv für dieses Team-Event' : 'Nicht aktiv' }}
                                    </p>
                                </div>

                                <div>
                                    <p class="text-xs font-semibold uppercase tracking-wide text-secondary">
                                        {{ $t('events.route.label') }}
                                    </p>
                                    <p class="mt-1 text-primary">
                                        {{ selectedSportRoute?.title || $t('events.route.none') }}
                                    </p>
                                    <p v-if="selectedSportRoute && routeEndpointsLabel(selectedSportRoute)" class="mt-1 text-xs text-secondary">
                                        {{ routeEndpointsLabel(selectedSportRoute) }}
                                    </p>
                                </div>

                                <div>
                                            <p class="text-xs font-semibold uppercase tracking-wide text-secondary">
                                                Ort
                                            </p>
                                            <p class="mt-1 text-primary">
                                                {{ form.location || [form.location_name, [form.location_street, form.location_house_number].filter(Boolean).join(' '), [form.location_postal_code, form.location_city].filter(Boolean).join(' ')].filter(Boolean).join(', ') || '-' }}
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
                        <div v-if="currentStepValidationMessage" class="mb-3 rounded-lg border border-warning/40 bg-warning/10 px-3 py-2 text-sm font-semibold text-warning">
                            {{ currentStepValidationMessage }}
                        </div>

                        <div class="flex gap-3">
                            <button type="button"
                                class="flex-1 rounded-lg border border-border px-4 py-3 font-semibold text-secondary transition hover:border-borderHover hover:text-primary disabled:opacity-50"
                                :disabled="createStep === 1" @click="prevStep">
                                Zurück
                            </button>

                            <button v-if="createStep < steps.length" type="button"
                                class="flex-1 rounded-lg bg-buttonPrimary px-4 py-3 font-semibold text-buttonTextPrimary transition hover:bg-buttonPrimaryHover disabled:cursor-not-allowed disabled:opacity-50"
                                :disabled="!!currentStepValidationMessage"
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
        <div v-if="viewMode === 'list' && eventItems.length" class="grid gap-4 md:grid-cols-2 xl:grid-cols-3">
            <article v-for="event in eventItems" :key="event.id"
                class="group overflow-hidden rounded-lg border border-border bg-card p-4 transition hover:-translate-y-0.5 hover:border-borderHover hover:shadow-xl">
                <div class="flex items-start gap-3">
                    <div class="flex w-16 shrink-0 flex-col items-center justify-center rounded-lg border border-border bg-inputBg py-2">
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
                                    class="line-clamp-2 text-base font-bold text-primary transition group-hover:text-buttonPrimary hover:underline">
                                    <span>{{ event.title }}</span>
                                </Link>

                                <p class="mt-1 text-sm text-secondary">
                                    {{ event.club?.name || event.team?.name || $t('events.public_scope') }}
                                </p>
                            </div>

                            <span class="shrink-0 rounded-full bg-inputBg px-2 py-1 text-xs text-secondary">
                                {{ participantCapacityLabel(event) }}
                            </span>
                        </div>

                        <div class="mt-3 flex flex-wrap gap-2">
                            <span class="rounded-full bg-buttonPrimary/10 px-2 py-1 text-xs font-semibold text-buttonPrimary">
                                {{ $t(typeLabels[event.type] || event.type) }}
                            </span>
                            <span class="rounded-full bg-inputBg px-2 py-1 text-xs font-semibold text-secondary">
                                {{ $t(visibilityLabels[event.visibility] || event.visibility) }}
                            </span>
                            <span v-if="event.comments_count" class="rounded-full bg-inputBg px-2 py-1 text-xs font-semibold text-secondary">
                                {{ event.comments_count }} Kommentare
                            </span>
                            <span v-if="event.sport_route_reference" class="rounded-full bg-buttonPrimary/10 px-2 py-1 text-xs font-semibold text-buttonPrimary">
                                <i class="las la-route"></i> {{ event.sport_route_reference.title }}
                            </span>
                            <span v-if="hasParticipantLimit(event)" class="rounded-full bg-inputBg px-2 py-1 text-xs font-semibold text-secondary">
                                Max. {{ event.max_participants }}
                            </span>
                            <span v-if="event.can_update" class="rounded-full bg-buttonPrimary/10 px-2 py-1 text-xs font-semibold text-buttonPrimary">
                                Bearbeitbar
                            </span>
                            <span v-if="event.status === 'cancelled'" class="rounded-full bg-error/10 px-2 py-1 text-xs font-semibold text-error">
                                Abgesagt
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
                                :disabled="event.status === 'cancelled' || (option.value === 'yes' && isEventFullForYes(event))"
                                :title="event.status === 'cancelled' ? 'Event ist abgesagt' : option.value === 'yes' && isEventFullForYes(event) ? 'Dieses Event ist voll' : ''"
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

        <nav v-if="viewMode === 'list' && eventPaginationLinks.length > 3" class="flex flex-wrap items-center justify-center gap-2">
            <Link
                v-for="link in eventPaginationLinks"
                :key="link.label"
                :href="link.url || '#'"
                preserve-scroll
                class="min-w-10 rounded-lg border px-3 py-2 text-center text-sm font-semibold transition"
                :class="[
                    link.active ? 'border-buttonPrimary bg-buttonPrimary text-buttonTextPrimary' : 'border-border bg-card text-secondary hover:border-borderHover hover:text-primary',
                    !link.url ? 'pointer-events-none opacity-45' : '',
                ]"
            >
                {{ paginationLabel(link.label) }}
            </Link>
        </nav>

        <AppEmptyState
            v-if="!eventItems.length && !calendarEventItems.length"
            title="Keine Events gefunden"
            description="Es gibt aktuell keine passenden Events. Passe die Filter an oder erstelle ein neues Event."
        >
            <template #icon>
                <i class="las la-calendar-times text-2xl" aria-hidden="true"></i>
            </template>
            <template #actions>
                <button type="button" class="rounded-lg border border-border px-4 py-2 text-sm font-semibold text-primary hover:bg-muted" @click="resetFilters">
                    Filter zurücksetzen
                </button>
                <button type="button" class="rounded-lg bg-buttonPrimary px-4 py-2 text-sm font-semibold text-buttonTextPrimary hover:bg-buttonPrimaryHover" @click="openCreateModal">
                    Event erstellen
                </button>
            </template>
        </AppEmptyState>
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
