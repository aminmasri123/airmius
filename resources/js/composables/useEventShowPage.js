import { router, useForm, usePage } from '@inertiajs/vue3'
import { computed, onMounted, ref, watch } from 'vue'

export function useEventShowPage(props) {
    const showEditModal = ref(false)
    const showDeleteModal = ref(false)
    const showCancelModal = ref(false)
    const commentForm = useForm({ content: '' })
    const cancelForm = useForm({ reason: '' })
    const page = usePage()
    const locale = computed(() => page.props.locale || 'de')
    const participationForm = useForm({
        status: props.currentParticipantStatus || '',
        response_reason: props.currentParticipantResponse?.reason || '',
        response_mode: props.currentParticipantResponse?.response_mode || 'self',
    })

    const browserTimeZone = () => Intl.DateTimeFormat().resolvedOptions().timeZone || 'UTC'

    const typeLabels = {
        training: 'Training',
        match: 'Spiel',
        meeting: 'Meeting',
        public: 'Öffentliches Event',
    }

    const visibilityLabels = {
        private: 'Team-intern',
        organization: 'Verein/Organisation',
        public: 'Öffentlich',
    }

    const statusLabels = {
        yes: 'Zusage',
        maybe: 'Vielleicht',
        no: 'Absage',
        late: 'Verspätet',
    }

    const eventStatusLabels = {
        scheduled: 'Geplant',
        cancelled: 'Abgesagt',
    }

    const recurrenceOptions = [
        { value: '', label: 'Keine' },
        { value: 'daily', label: 'Täglich' },
        { value: 'weekly', label: 'Wöchentlich' },
        { value: 'biweekly', label: 'Alle zwei Wochen' },
        { value: 'monthly', label: 'Monatlich' },
    ]

    const weekdayOptions = [
        { value: 1, label: 'Mo' },
        { value: 2, label: 'Di' },
        { value: 3, label: 'Mi' },
        { value: 4, label: 'Do' },
        { value: 5, label: 'Fr' },
        { value: 6, label: 'Sa' },
        { value: 0, label: 'So' },
    ]

    const toLocalInput = (value) => {
        if (!value) return ''

        const date = new Date(value)
        const offset = date.getTimezoneOffset()
        const local = new Date(date.getTime() - offset * 60 * 1000)

        return local.toISOString().slice(0, 16)
    }

    const toLocalDate = (value) => {
        if (!value) return ''

        const date = new Date(value)
        const offset = date.getTimezoneOffset()
        const local = new Date(date.getTime() - offset * 60 * 1000)

        return local.toISOString().slice(0, 10)
    }

    const editForm = useForm({
        club_id: props.event.club_id || props.event.team?.club_id || '',
        team_id: props.event.team_id || '',
        title: props.event.title || '',
        type: props.event.type || 'training',
        visibility: props.event.visibility || 'private',
        start_time: toLocalInput(props.event.start_time),
        end_time: toLocalInput(props.event.end_time),
        location: props.event.location || '',
        location_name: props.event.location_name || '',
        location_street: props.event.location_street || '',
        location_house_number: props.event.location_house_number || '',
        location_postal_code: props.event.location_postal_code || '',
        location_city: props.event.location_city || '',
        location_country: props.event.location_country || 'DE',
        max_participants: props.event.max_participants || '',
        notes: props.event.notes || '',
        recurring: props.event.recurring || '',
        recurrence_days: props.event.recurrence_days || [],
        recurrence_ends_at: toLocalDate(props.event.recurrence_ends_at),
        reminder_at: toLocalInput(props.event.reminder_at),
        event_timezone: browserTimeZone(),
    })

    const filteredTeams = computed(() => {
        if (!editForm.club_id) return props.teams

        return props.teams.filter((team) => Number(team.club_id) === Number(editForm.club_id))
    })

    watch(() => editForm.club_id, () => {
        if (editForm.team_id && !filteredTeams.value.some((team) => Number(team.id) === Number(editForm.team_id))) {
            editForm.team_id = ''
        }
    })

    const formatDateTime = (date) => {
        if (!date) return '-'

        return new Intl.DateTimeFormat(locale.value, {
            weekday: 'short',
            day: '2-digit',
            month: '2-digit',
            year: 'numeric',
            hour: '2-digit',
            minute: '2-digit',
            timeZoneName: 'short',
        }).format(new Date(date))
    }

    const formatDate = (date) => {
        if (!date) return '-'

        return new Intl.DateTimeFormat(locale.value, {
            day: '2-digit',
            month: '2-digit',
            year: 'numeric',
        }).format(new Date(date))
    }

    const timeRange = computed(() => {
        if (!props.event.end_time) {
            return formatDateTime(props.event.start_time)
        }

        return `${formatDateTime(props.event.start_time)} bis ${formatDateTime(props.event.end_time)}`
    })

    const recurrenceDaysLabel = computed(() => {
        const days = Array.isArray(props.event.recurrence_days) ? props.event.recurrence_days : []
        const labels = {
            0: 'So',
            1: 'Mo',
            2: 'Di',
            3: 'Mi',
            4: 'Do',
            5: 'Fr',
            6: 'Sa',
        }

        return days.map((day) => labels[Number(day)] || day).join(', ')
    })

    const yesCount = computed(() => props.event.participants?.filter((participant) => participant.pivot?.status === 'yes').length || 0)
    const lateCount = computed(() => props.event.participants?.filter((participant) => participant.pivot?.status === 'late').length || 0)
    const maybeCount = computed(() => props.event.participants?.filter((participant) => participant.pivot?.status === 'maybe').length || 0)
    const noCount = computed(() => props.event.participants?.filter((participant) => participant.pivot?.status === 'no').length || 0)
    const needsReason = computed(() => ['no', 'late'].includes(participationForm.status))
    const participationLocked = computed(() => Boolean(props.participationPolicy?.deadline_expired) && !props.can.update)
    const deadlineLabel = computed(() => props.participationPolicy?.response_deadline_at
        ? formatDateTime(props.participationPolicy.response_deadline_at)
        : null)
    const hasParticipantLimit = computed(() => Number(props.event.max_participants || 0) > 0)
    const isFullForYes = computed(() => hasParticipantLimit.value
        && yesCount.value >= Number(props.event.max_participants)
        && props.currentParticipantStatus !== 'yes')
    const capacityLabel = computed(() => hasParticipantLimit.value
        ? `${yesCount.value}/${props.event.max_participants} Plätze belegt`
        : `${yesCount.value} Zusagen, unbegrenzt`)

    const setStatus = (status) => {
        if (props.event.status === 'cancelled') return
        if (participationLocked.value) return
        if (status === 'yes' && isFullForYes.value) return

        participationForm.status = status

        if (!needsReason.value) {
            participationForm.response_reason = ''
        }

        if (needsReason.value && !participationForm.response_reason?.trim()) {
            return
        }

        participationForm.post(route('auth.events.join', props.event.id), {
            preserveScroll: true,
        })
    }

    const submitComment = () => {
        commentForm.post(route('auth.events.comments.store', props.event.id), {
            preserveScroll: true,
            onSuccess: () => commentForm.reset(),
        })
    }

    const updateEvent = () => {
        editForm.event_timezone = browserTimeZone()
        editForm.location = [
            editForm.location_name,
            [editForm.location_street, editForm.location_house_number].filter(Boolean).join(' '),
            [editForm.location_postal_code, editForm.location_city].filter(Boolean).join(' '),
        ].filter(Boolean).join(', ')

        editForm.put(route('auth.events.update', props.event.id), {
            preserveScroll: true,
            onSuccess: () => {
                showEditModal.value = false
            },
        })
    }

    const toggleWeekday = (day) => {
        const days = editForm.recurrence_days.map(Number)

        editForm.recurrence_days = days.includes(day)
            ? days.filter((value) => value !== day)
            : [...days, day].sort((a, b) => a - b)
    }

    const deleteEvent = () => {
        router.delete(route('auth.events.destroy', props.event.id), {
            preserveScroll: true,
            onFinish: () => {
                showDeleteModal.value = false
            },
        })
    }

    const cancelEvent = () => {
        cancelForm.post(route('auth.events.cancel', props.event.id), {
            preserveScroll: true,
            onSuccess: () => {
                cancelForm.reset()
                showCancelModal.value = false
            },
        })
    }

    onMounted(() => {
        if (props.can.update && page.url.includes('edit=1')) {
            showEditModal.value = true
        }
    })

    return {
        showEditModal,
        showDeleteModal,
        showCancelModal,
        commentForm,
        cancelForm,
        editForm,
        filteredTeams,
        typeLabels,
        visibilityLabels,
        statusLabels,
        eventStatusLabels,
        recurrenceOptions,
        weekdayOptions,
        timeRange,
        recurrenceDaysLabel,
        yesCount,
        lateCount,
        maybeCount,
        noCount,
        participationForm,
        needsReason,
        deadlineLabel,
        participationLocked,
        hasParticipantLimit,
        isFullForYes,
        capacityLabel,
        formatDateTime,
        formatDate,
        setStatus,
        submitComment,
        updateEvent,
        toggleWeekday,
        deleteEvent,
        cancelEvent,
    }
}


