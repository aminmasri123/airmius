import { router, useForm, usePage } from '@inertiajs/vue3'
import { computed, ref, watch } from 'vue'
import { useI18n } from 'vue-i18n'

export function useEventsWorkspace(props) {
    const { t, locale } = useI18n()
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
            return
        }

        if (visibility === 'organization') {
            form.team_id = ''
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
        } else if (form.visibility === 'organization') {
            form.team_id = ''
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

        return `${formatTime(event.start_time, event)} - ${formatTime(event.end_time, event)} Uhr`
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
            only: ['events', 'calendarEvents', 'eventStats', 'nextEvent', 'calendar', 'flash', 'errors'],
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

    return {
        t,
        page,
        showCreateModal,
        filterPanelOpen,
        createStep,
        viewMode,
        eventItems,
        eventPaginationLinks,
        calendarEventItems,
        errors,
        authorizationMessage,
        allowsRecurringForSelection,
        freeEventLimitMessage,
        steps,
        recurrenceOptions,
        weekdayOptions,
        typeLabels,
        visibilityLabels,
        rsvpOptions,
        browserTimeZone,
        dateKey,
        initialCalendarMonth,
        selectedCalendarDate,
        calendarCursor,
        calendarWeekdays,
        form,
        filterForm,
        filteredTeams,
        filteredFilterTeams,
        selectedSportsCount,
        activeFilterCount,
        todayEventsCount,
        upcomingEventsCount,
        cancelledEventsCount,
        nextEvent,
        calendarMonthLabel,
        calendarEventsByDate,
        calendarDays,
        selectedCalendarEvents,
        moveCalendarMonth,
        selectCalendarDay,
        jumpToToday,
        resetCreateForm,
        closeCreateModal,
        openCreateModal,
        stepValidationMessage,
        currentStepValidationMessage,
        canEnterStep,
        goToStep,
        nextStep,
        prevStep,
        submit,
        toggleWeekday,
        recurrenceLabel,
        recurrenceSummary,
        selectedClubName,
        selectedTeamName,
        eventTimeZone,
        formatDate,
        formatTime,
        timeRange,
        formatDay,
        formatMonthShort,
        formatWeekdayShort,
        weekdayShortLabel,
        recurrenceDaysLabel,
        eventDateTimeLabel,
        acceptedParticipantsCount,
        hasParticipantLimit,
        isEventFullForYes,
        participantCapacityLabel,
        rsvpButtonClass,
        setParticipation,
        toggleFilterSport,
        filterPayload,
        applyFilters,
        saveDefaultFilters,
        resetFilters,
    }
}
