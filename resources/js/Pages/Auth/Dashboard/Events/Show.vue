<script setup>
import AppLayout from '@/Components/Auth/Layouts/AppLayout.vue'
import ConfirmActionModal from '@/Components/ConfirmActionModal.vue'
import Modal from '@/Components/Modal.vue'
import { Head, Link, router, useForm, usePage } from '@inertiajs/vue3'
import { computed, onMounted, ref, watch } from 'vue'
import { useI18n } from 'vue-i18n'

defineOptions({ layout: AppLayout })

const props = defineProps({
    event: Object,
    clubs: { type: Array, default: () => [] },
    teams: { type: Array, default: () => [] },
    eventTypes: { type: Array, default: () => [] },
    visibilities: { type: Array, default: () => [] },
    participantStatuses: Array,
    currentParticipantStatus: String,
    can: { type: Object, default: () => ({ update: false, delete: false, cancel: false }) },
    penaltyCatalog: { type: Object, default: null },
})

const showEditModal = ref(false)
const showDeleteModal = ref(false)
const showCancelModal = ref(false)
const penaltyError = ref('')
const attendanceError = ref('')
const attendanceSaving = ref(false)
const bulkAttendanceSaving = ref(false)
const attendanceForm = ref({})
const eventState = ref(JSON.parse(JSON.stringify(props.event)))
const currentParticipantStatus = ref(props.currentParticipantStatus)
const penaltyCatalogState = ref(props.penaltyCatalog || { rules: [], fees: [], can_manage: false })
const commentForm = useForm({ content: '' })
const cancelForm = useForm({ reason: '' })
const page = usePage()
const { locale } = useI18n()
const localeCode = computed(() => ({ ar: 'ar-EG', fr: 'fr-FR', en: 'en-US', de: 'de-DE' })[locale.value] || 'de-DE')

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
    late: 'Verspätet',
    maybe: 'Vielleicht',
    no: 'Absage',
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
    uses_penalty_catalog: Boolean(props.event.uses_penalty_catalog),
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

watch(() => props.event, (next) => {
    eventState.value = JSON.parse(JSON.stringify(next))
}, { deep: true })

watch(() => props.currentParticipantStatus, (next) => {
    currentParticipantStatus.value = next
})

const formatDateTime = (date) => {
    if (!date) return '-'

    return new Intl.DateTimeFormat(localeCode.value, {
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

    return new Intl.DateTimeFormat(localeCode.value, {
        day: '2-digit',
        month: '2-digit',
        year: 'numeric',
    }).format(new Date(date))
}

const event = computed(() => eventState.value)

const timeRange = computed(() => {
    if (!event.value.end_time) {
        return formatDateTime(event.value.start_time)
    }

    return `${formatDateTime(event.value.start_time)} bis ${formatDateTime(event.value.end_time)}`
})

const recurrenceDaysLabel = computed(() => {
    const days = Array.isArray(event.value.recurrence_days) ? event.value.recurrence_days : []
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

const yesCount = computed(() => event.value.participants?.filter((participant) => participant.pivot?.status === 'yes').length || 0)
const lateCount = computed(() => event.value.participants?.filter((participant) => participant.pivot?.status === 'late').length || 0)
const maybeCount = computed(() => event.value.participants?.filter((participant) => participant.pivot?.status === 'maybe').length || 0)
const noCount = computed(() => event.value.participants?.filter((participant) => participant.pivot?.status === 'no').length || 0)
const attendanceStatusFor = (member) => event.value.participants
    ?.find((participant) => Number(participant.id) === Number(member.id))
    ?.pivot
    ?.status || ''
const attendanceRoster = computed(() => {
    const roster = event.value.team?.users?.length ? event.value.team.users : (event.value.participants || [])

    return roster.map((member) => ({
        ...member,
        attendance_status: attendanceStatusFor(member),
    }))
})
const eventPenaltyParticipants = computed(() => (event.value.participants || [])
    .filter((participant) => ['yes', 'late'].includes(participant.pivot?.status)))
const activePenaltyRules = computed(() => penaltyCatalogState.value?.rules || [])
const eventPenaltyFees = computed(() => penaltyCatalogState.value?.fees || [])
const canManageEventPenalties = computed(() => Boolean(event.value.uses_penalty_catalog && event.value.team_id && (props.can.manage_penalties || penaltyCatalogState.value?.can_manage)))
const penaltyForm = ref({
    user_id: '',
    penalty_rule_id: '',
    amount: '',
    minutes: '',
    note: '',
    due_date: '',
})
const hasParticipantLimit = computed(() => Number(event.value.max_participants || 0) > 0)
const isFullForYes = computed(() => hasParticipantLimit.value
    && yesCount.value >= Number(event.value.max_participants)
    && currentParticipantStatus.value !== 'yes')
const capacityLabel = computed(() => hasParticipantLimit.value
    ? `${yesCount.value}/${event.value.max_participants} Plätze belegt`
    : `${yesCount.value} Zusagen, unbegrenzt`)

const syncAttendanceForm = () => {
    const next = {}

    attendanceRoster.value.forEach((member) => {
        next[member.id] = member.attendance_status || ''
    })

    attendanceForm.value = next
}

watch(event, () => syncAttendanceForm(), { deep: true, immediate: true })

const optimisticParticipantForViewer = (status) => {
    const user = page.props.auth?.user || page.props.user || {}

    return {
        id: user.id,
        name: user.name || 'Du',
        email: user.email || '',
        profile_photo_url: user.profile_photo_url || null,
        pivot: { status },
    }
}

const applyOptimisticStatus = (status) => {
    const userId = page.props.auth?.user?.id || page.props.user?.id
    const participants = [...(eventState.value.participants || [])]
    const index = participants.findIndex((participant) => Number(participant.id) === Number(userId))

    if (index >= 0) {
        participants[index] = {
            ...participants[index],
            pivot: {
                ...(participants[index].pivot || {}),
                status,
            },
        }
    } else {
        participants.push(optimisticParticipantForViewer(status))
    }

    eventState.value = {
        ...eventState.value,
        participants,
    }
    currentParticipantStatus.value = status
}

const setStatus = (status) => {
    if (attendanceSaving.value) return
    if (event.value.status === 'cancelled') return
    if (status === 'yes' && isFullForYes.value) return

    const previousEvent = JSON.parse(JSON.stringify(eventState.value))
    const previousStatus = currentParticipantStatus.value
    attendanceError.value = ''
    attendanceSaving.value = true
    applyOptimisticStatus(status)

    router.post(route('auth.events.join', props.event.id), { status }, {
        preserveScroll: true,
        onError: (errors) => {
            eventState.value = previousEvent
            currentParticipantStatus.value = previousStatus
            attendanceError.value = Object.values(errors || {})[0] || 'Teilnahme konnte nicht gespeichert werden.'
        },
        onFinish: () => {
            attendanceSaving.value = false
        },
    })
}

const saveBulkAttendance = () => {
    if (bulkAttendanceSaving.value) return

    const attendance = attendanceRoster.value
        .map((member) => ({
            user_id: member.id,
            status: attendanceForm.value[member.id],
        }))
        .filter((row) => row.status)

    if (!attendance.length) {
        attendanceError.value = 'Bitte mindestens einen Anwesenheitsstatus auswählen.'

        return
    }

    attendanceError.value = ''
    bulkAttendanceSaving.value = true

    router.put(route('auth.events.attendance.update', props.event.id), { attendance }, {
        preserveScroll: true,
        onError: (errors) => {
            attendanceError.value = errors.attendance
                || Object.values(errors || {})[0]
                || 'Anwesenheit konnte nicht gespeichert werden.'
        },
        onFinish: () => {
            bulkAttendanceSaving.value = false
        },
    })
}

const formatMoney = (amount, currency = 'EUR') => new Intl.NumberFormat(localeCode.value, {
    style: 'currency',
    currency: currency || 'EUR',
}).format(Number(amount || 0))

const penaltyRuleLabel = (rule) => {
    if (!rule) return 'Manueller Betrag'
    if (rule.calculation_type === 'item') return `${rule.title} (${rule.unit_label || 'Sachstrafe'})`
    if (rule.calculation_type === 'per_minute') return `${rule.title} (${formatMoney(rule.amount, rule.currency)} / Min.)`
    if (rule.calculation_type === 'threshold_fixed') return `${rule.title} (ab ${rule.threshold_minutes || 0} Min.)`
    return `${rule.title} (${formatMoney(rule.amount, rule.currency)})`
}

const loadEventPenalties = async () => {
    if (!props.event.team_id) return

    try {
        const response = await window.axios.get(route('auth.teams.penalties.index', props.event.team_id), {
            params: { event_id: props.event.id },
        })
        penaltyCatalogState.value = response.data.data
    } catch (error) {
        penaltyError.value = error.response?.data?.message || 'Event-Strafen konnten nicht geladen werden.'
    }
}

const resetPenaltyForm = () => {
    penaltyForm.value = {
        user_id: '',
        penalty_rule_id: '',
        amount: '',
        minutes: '',
        note: '',
        due_date: '',
    }
}

const submitEventPenalty = async () => {
    if (!props.event.team_id) return
    penaltyError.value = ''

    try {
        await window.axios.post(route('auth.teams.penalty-fees.store', props.event.team_id), {
            event_id: props.event.id,
            user_id: Number(penaltyForm.value.user_id),
            penalty_rule_id: penaltyForm.value.penalty_rule_id ? Number(penaltyForm.value.penalty_rule_id) : null,
            amount: penaltyForm.value.amount === '' ? null : Number(penaltyForm.value.amount),
            minutes: penaltyForm.value.minutes === '' ? null : Number(penaltyForm.value.minutes),
            note: penaltyForm.value.note || null,
            due_date: penaltyForm.value.due_date || null,
        })
        resetPenaltyForm()
        await loadEventPenalties()
    } catch (error) {
        penaltyError.value = error.response?.data?.message || 'Strafe konnte nicht gebucht werden.'
    }
}

const markEventPenaltyPaid = async (fee) => {
    if (!props.event.team_id) return

    try {
        await window.axios.post(route('auth.teams.penalty-fees.paid', [props.event.team_id, fee.id]))
        await loadEventPenalties()
    } catch (error) {
        penaltyError.value = error.response?.data?.message || 'Strafe konnte nicht bezahlt markiert werden.'
    }
}

const cancelEventPenalty = async (fee) => {
    if (!props.event.team_id || !confirm('Diese Strafe stornieren?')) return

    try {
        await window.axios.post(route('auth.teams.penalty-fees.cancel', [props.event.team_id, fee.id]))
        await loadEventPenalties()
    } catch (error) {
        penaltyError.value = error.response?.data?.message || 'Strafe konnte nicht storniert werden.'
    }
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
    if (props.event.uses_penalty_catalog) {
        loadEventPenalties()
    }
})
</script>

<template>
    <Head :title="event.title" />

    <div class="mx-auto max-w-6xl space-y-6">
        <div class="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
            <div>
                <Link :href="route('auth.events.index')" class="text-sm font-semibold text-air-blue hover:underline">
                    Zurück zu Events
                </Link>
                <h1 class="mt-2 text-3xl font-bold text-primary">{{ event.title }}</h1>
                <p class="mt-2">
                    <span
                        class="rounded-full px-2 py-1 text-xs font-semibold"
                        :class="event.status === 'cancelled' ? 'bg-error/10 text-error' : 'bg-success/10 text-success'"
                    >
                        {{ eventStatusLabels[event.status || 'scheduled'] || event.status }}
                    </span>
                </p>
                <p class="mt-2 text-sm text-secondary">
                    {{ typeLabels[event.type] || event.type }} · {{ visibilityLabels[event.visibility] || event.visibility }}
                </p>
            </div>

            <div class="flex flex-wrap gap-2">
                <Link
                    v-if="event.conversation_id || event.team_id"
                    :href="route('auth.events.chat', event.id)"
                    class="rounded-lg border border-border px-4 py-2 text-sm font-semibold text-primary hover:bg-muted"
                >
                    <i class="las la-comments mr-1"></i>
                    Teamchat
                </Link>
                <button
                    v-if="can.update"
                    type="button"
                    class="rounded-lg bg-buttonPrimary px-4 py-2 text-sm font-semibold text-buttonTextPrimary hover:bg-buttonPrimaryHover"
                    @click="showEditModal = true"
                >
                    <i class="las la-edit mr-1"></i>
                    Event bearbeiten
                </button>
                <button
                    v-if="can.cancel && event.status !== 'cancelled'"
                    type="button"
                    class="rounded-lg border border-warning/40 px-4 py-2 text-sm font-semibold text-warning hover:bg-warning/10"
                    @click="showCancelModal = true"
                >
                    <i class="las la-calendar-times mr-1"></i>
                    Event absagen
                </button>
                <button
                    v-if="can.delete"
                    type="button"
                    class="rounded-lg border border-error/40 px-4 py-2 text-sm font-semibold text-error hover:bg-error/10"
                    @click="showDeleteModal = true"
                >
                    <i class="las la-trash mr-1"></i>
                    Löschen
                </button>
            </div>
        </div>

        <section class="grid gap-4 lg:grid-cols-[1.2fr_0.8fr]">
            <div v-if="event.status === 'cancelled'" class="rounded-lg border border-error/40 bg-error/10 p-4 text-error lg:col-span-2">
                <p class="font-semibold">Dieses Event wurde abgesagt.</p>
                <p v-if="event.cancellation_reason" class="mt-1 text-sm">{{ event.cancellation_reason }}</p>
                <p v-if="event.cancelled_by" class="mt-1 text-xs text-secondary">
                    Abgesagt von {{ event.cancelled_by.name }}{{ event.cancelled_at ? ` am ${formatDateTime(event.cancelled_at)}` : '' }}
                </p>
            </div>

            <article class="rounded-lg border border-border bg-card p-5">
                <div class="grid gap-4 md:grid-cols-2">
                    <div class="rounded-lg bg-inputBg p-4">
                        <p class="text-xs font-semibold uppercase tracking-wide text-secondary">Zeit</p>
                        <p class="mt-2 text-sm font-semibold text-primary">{{ timeRange }}</p>
                        <p v-if="event.reminder_at" class="mt-2 text-xs text-secondary">
                            Erinnerung: {{ formatDateTime(event.reminder_at) }}
                        </p>
                    </div>

                    <div class="rounded-lg bg-inputBg p-4">
                        <p class="text-xs font-semibold uppercase tracking-wide text-secondary">Ort</p>
                        <p class="mt-2 text-sm font-semibold text-primary">{{ event.location || 'Kein Ort angegeben' }}</p>
                    </div>

                    <div class="rounded-lg bg-inputBg p-4">
                        <p class="text-xs font-semibold uppercase tracking-wide text-secondary">Verein / Team</p>
                        <p class="mt-2 text-sm font-semibold text-primary">
                            {{ event.team?.name || event.club?.name || 'Öffentliches Event' }}
                        </p>
                        <p v-if="event.team?.name && event.club?.name" class="mt-1 text-xs text-secondary">{{ event.club.name }}</p>
                    </div>

                    <div class="rounded-lg bg-inputBg p-4">
                        <p class="text-xs font-semibold uppercase tracking-wide text-secondary">Besitzer</p>
                        <p class="mt-2 text-sm font-semibold text-primary">{{ event.user?.name || 'Nicht gespeichert' }}</p>
                    </div>

                    <div class="rounded-lg bg-inputBg p-4">
                        <p class="text-xs font-semibold uppercase tracking-wide text-secondary">Teilnehmerlimit</p>
                        <p class="mt-2 text-sm font-semibold text-primary">{{ hasParticipantLimit ? `${event.max_participants} Personen` : 'Unbegrenzt' }}</p>
                    </div>
                </div>

                <div class="mt-5 rounded-lg border border-border p-4">
                    <p class="text-xs font-semibold uppercase tracking-wide text-secondary">Beschreibung / Notizen</p>
                    <p v-if="event.notes" class="mt-3 whitespace-pre-line break-words text-sm leading-6 text-primary">{{ event.notes }}</p>
                    <p v-else class="mt-3 text-sm text-secondary">Keine Notizen hinterlegt.</p>
                </div>

                <div class="mt-5 grid gap-3 text-sm md:grid-cols-3">
                    <div class="rounded-lg bg-muted p-3">
                        <p class="text-xs font-semibold uppercase tracking-wide text-secondary">Wiederholung</p>
                        <p class="mt-1 font-semibold text-primary">{{ event.recurring || 'Keine' }}</p>
                        <p v-if="recurrenceDaysLabel" class="mt-1 text-xs text-secondary">{{ recurrenceDaysLabel }}</p>
                    </div>
                    <div class="rounded-lg bg-muted p-3">
                        <p class="text-xs font-semibold uppercase tracking-wide text-secondary">Wiederholung bis</p>
                        <p class="mt-1 font-semibold text-primary">{{ event.recurrence_ends_at ? formatDate(event.recurrence_ends_at) : 'Nicht gesetzt' }}</p>
                    </div>
                    <div class="rounded-lg bg-muted p-3">
                        <p class="text-xs font-semibold uppercase tracking-wide text-secondary">Kommentare</p>
                        <p class="mt-1 font-semibold text-primary">{{ event.comments?.length || 0 }}</p>
                    </div>
                </div>
            </article>

            <aside class="space-y-4">
                <section class="rounded-lg border border-border bg-card p-5">
                    <h2 class="text-lg font-semibold text-primary">Teilnahme</h2>
                    <p class="mt-1 text-sm text-secondary">{{ capacityLabel }}</p>
                    <p v-if="attendanceError" class="mt-3 rounded-lg border border-error/40 bg-error/10 px-3 py-2 text-sm font-semibold text-error">
                        {{ attendanceError }}
                    </p>
                    <div class="mt-4 grid grid-cols-2 gap-2 text-center sm:grid-cols-4">
                        <div class="rounded-lg bg-success/10 p-3 text-success">
                            <p class="text-2xl font-bold">{{ yesCount }}</p>
                            <p class="text-xs font-semibold">Zusagen</p>
                        </div>
                        <div class="rounded-lg bg-warning/10 p-3 text-warning">
                            <p class="text-2xl font-bold">{{ lateCount }}</p>
                            <p class="text-xs font-semibold">Verspätet</p>
                        </div>
                        <div class="rounded-lg bg-air-blue/10 p-3 text-air-blue">
                            <p class="text-2xl font-bold">{{ maybeCount }}</p>
                            <p class="text-xs font-semibold">Vielleicht</p>
                        </div>
                        <div class="rounded-lg bg-error/10 p-3 text-error">
                            <p class="text-2xl font-bold">{{ noCount }}</p>
                            <p class="text-xs font-semibold">Absagen</p>
                        </div>
                    </div>

                    <div class="mt-4 flex flex-wrap gap-2">
                        <button
                            v-for="status in participantStatuses"
                            :key="status"
                            class="rounded-lg border px-4 py-2 text-sm font-semibold"
                            :class="currentParticipantStatus === status ? 'border-buttonPrimary bg-buttonPrimary text-buttonTextPrimary' : 'border-border text-primary hover:bg-muted'"
                            :disabled="attendanceSaving || event.status === 'cancelled' || (status === 'yes' && isFullForYes)"
                            :title="event.status === 'cancelled' ? 'Event ist abgesagt' : status === 'yes' && isFullForYes ? 'Dieses Event ist voll' : ''"
                            @click="setStatus(status)"
                        >
                            {{ statusLabels[status] || status }}
                        </button>
                    </div>
                    <p v-if="attendanceSaving" class="mt-2 text-xs font-semibold text-secondary">
                        Teilnahme wird gespeichert…
                    </p>
                </section>

                <section class="rounded-lg border border-border bg-card p-5">
                    <h2 class="text-lg font-semibold text-primary">Teilnehmer</h2>
                    <div class="mt-3 max-h-80 space-y-2 overflow-y-auto pr-1">
                        <div v-for="participant in event.participants" :key="participant.id" class="flex items-center justify-between gap-3 rounded-lg bg-inputBg px-3 py-2 text-sm">
                            <span class="truncate font-medium text-primary">{{ participant.name }}</span>
                            <span class="shrink-0 rounded-full bg-muted px-2 py-1 text-xs text-secondary">
                                {{ statusLabels[participant.pivot.status] || participant.pivot.status }}
                            </span>
                        </div>
                        <p v-if="!event.participants?.length" class="text-sm text-secondary">Noch keine Teilnehmer.</p>
                    </div>

                    <form
                        v-if="can.manage_attendance && event.type === 'training' && attendanceRoster.length"
                        class="mt-4 space-y-2 border-t border-border pt-4"
                        @submit.prevent="saveBulkAttendance"
                    >
                        <div
                            v-for="member in attendanceRoster"
                            :key="member.id"
                            class="flex items-center gap-2 rounded-lg bg-inputBg px-3 py-2 text-sm"
                        >
                            <span class="min-w-0 flex-1 truncate font-medium text-primary">{{ member.name }}</span>
                            <select v-model="attendanceForm[member.id]" class="rounded border border-border bg-card px-2 py-1 text-xs text-primary">
                                <option value="">Offen</option>
                                <option v-for="status in participantStatuses" :key="status" :value="status">
                                    {{ statusLabels[status] || status }}
                                </option>
                            </select>
                        </div>
                        <button
                            type="submit"
                            class="w-full rounded-lg bg-buttonPrimary px-4 py-2 text-sm font-semibold text-buttonTextPrimary hover:bg-buttonPrimaryHover disabled:opacity-50"
                            :disabled="bulkAttendanceSaving"
                        >
                            {{ bulkAttendanceSaving ? 'Speichert...' : 'Anwesenheit speichern' }}
                        </button>
                    </form>
                </section>
            </aside>
        </section>

        <section v-if="event.uses_penalty_catalog && event.team_id" class="rounded-lg border border-border bg-card p-5">
            <div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
                <div>
                    <p class="text-xs font-semibold uppercase tracking-wide text-secondary">Mannschaftskasse</p>
                    <h2 class="mt-1 text-lg font-semibold text-primary">Event-Strafen</h2>
                    <p class="mt-1 text-sm text-secondary">
                        Strafen aus diesem Event werden der internen Teamkasse zugeordnet, nicht der Vereinskasse.
                    </p>
                </div>
                <button type="button" class="rounded-lg border border-border px-4 py-2 text-sm font-semibold text-primary hover:bg-muted" @click="loadEventPenalties">
                    Aktualisieren
                </button>
            </div>

            <p v-if="penaltyError" class="mt-4 rounded-lg border border-error/40 bg-error/10 p-3 text-sm font-semibold text-error">
                {{ penaltyError }}
            </p>

            <div class="mt-5 grid gap-5 lg:grid-cols-[360px_1fr]">
                <form v-if="canManageEventPenalties" class="rounded-lg border border-border bg-inputBg p-4" @submit.prevent="submitEventPenalty">
                    <h3 class="font-semibold text-primary">Strafe zuweisen</h3>
                    <div class="mt-4 space-y-3">
                        <label class="block text-sm font-semibold text-primary">
                            Spieler
                            <select v-model="penaltyForm.user_id" required class="mt-1 w-full rounded-lg border-border bg-card text-primary">
                                <option value="">Anwesenden Spieler auswählen</option>
                                <option v-for="participant in eventPenaltyParticipants" :key="participant.id" :value="participant.id">
                                    {{ participant.name }} · {{ statusLabels[participant.pivot?.status] || participant.pivot?.status }}
                                </option>
                            </select>
                        </label>
                        <label class="block text-sm font-semibold text-primary">
                            Strafe
                            <select v-model="penaltyForm.penalty_rule_id" class="mt-1 w-full rounded-lg border-border bg-card text-primary">
                                <option value="">Manueller Betrag</option>
                                <option v-for="rule in activePenaltyRules" :key="rule.id" :value="rule.id">
                                    {{ penaltyRuleLabel(rule) }}
                                </option>
                            </select>
                        </label>
                        <div class="grid gap-3 sm:grid-cols-3">
                            <label class="block text-sm font-semibold text-primary">
                                Minuten
                                <input v-model="penaltyForm.minutes" type="number" min="0" step="1" class="mt-1 w-full rounded-lg border-border bg-card text-primary" placeholder="0">
                            </label>
                            <label class="block text-sm font-semibold text-primary">
                                Betrag
                                <input v-model="penaltyForm.amount" type="number" min="0" step="0.01" class="mt-1 w-full rounded-lg border-border bg-card text-primary" placeholder="Automatisch">
                            </label>
                            <label class="block text-sm font-semibold text-primary">
                                Fällig
                                <input v-model="penaltyForm.due_date" type="date" class="mt-1 w-full rounded-lg border-border bg-card text-primary">
                            </label>
                        </div>
                        <label class="block text-sm font-semibold text-primary">
                            Notiz
                            <input v-model="penaltyForm.note" class="mt-1 w-full rounded-lg border-border bg-card text-primary" placeholder="z.B. Tunnel, zu spät, Ball vergessen">
                        </label>
                    </div>
                    <button class="mt-4 w-full rounded-lg bg-buttonPrimary px-4 py-2 text-sm font-bold text-buttonTextPrimary hover:bg-buttonPrimaryHover">
                        Strafe buchen
                    </button>
                </form>

                <div v-else class="rounded-lg border border-border bg-inputBg p-4 text-sm text-secondary">
                    Du kannst die Event-Strafen ansehen. Buchen dürfen Kassenwart, Trainer, Kapitän oder berechtigte Teamrollen.
                </div>

                <div class="rounded-lg border border-border bg-inputBg p-4">
                    <h3 class="font-semibold text-primary">Buchungen dieses Events</h3>
                    <div class="mt-4 divide-y divide-border overflow-hidden rounded-lg border border-border">
                        <div v-for="fee in eventPenaltyFees" :key="fee.id" class="bg-card p-3">
                            <div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
                                <div>
                                    <p class="font-semibold text-primary">{{ fee.member?.name || 'Spieler' }}</p>
                                    <p class="mt-1 text-sm text-secondary">
                                        {{ fee.rule?.title || fee.note || 'Strafe' }} · {{ formatMoney(fee.amount, fee.currency) }}
                                    </p>
                                    <p class="mt-1 text-xs text-secondary">
                                        Status: {{ fee.status }}
                                        <span v-if="fee.due_date"> · fällig {{ formatDate(fee.due_date) }}</span>
                                        <span v-if="fee.paid_at"> · bezahlt {{ formatDate(fee.paid_at) }}</span>
                                    </p>
                                </div>
                                <div v-if="canManageEventPenalties && fee.status === 'open'" class="flex gap-2">
                                    <button type="button" class="rounded-lg bg-buttonPrimary px-3 py-1 text-xs font-bold text-buttonTextPrimary" @click="markEventPenaltyPaid(fee)">
                                        Bezahlt
                                    </button>
                                    <button type="button" class="rounded-lg border border-border px-3 py-1 text-xs font-semibold text-secondary hover:text-error" @click="cancelEventPenalty(fee)">
                                        Storno
                                    </button>
                                </div>
                            </div>
                        </div>
                        <p v-if="!eventPenaltyFees.length" class="bg-card p-4 text-sm text-secondary">
                            Für dieses Event wurden noch keine Strafen gebucht.
                        </p>
                    </div>
                </div>
            </div>
        </section>

        <section class="rounded-lg border border-border bg-card p-5">
            <h2 class="text-lg font-semibold text-primary">Diskussion</h2>
            <form class="mt-3 flex flex-col gap-2 sm:flex-row" @submit.prevent="submitComment">
                <input v-model="commentForm.content" class="min-w-0 flex-1 rounded-lg border-border bg-inputBg text-primary" placeholder="Kommentar schreiben" required />
                <button class="rounded-lg bg-buttonPrimary px-4 py-2 font-semibold text-buttonTextPrimary" :disabled="commentForm.processing">
                    Senden
                </button>
            </form>
            <div class="mt-4 divide-y divide-border">
                <div v-for="comment in event.comments" :key="comment.id" class="py-3">
                    <div class="text-sm font-semibold text-primary">{{ comment.user.name }}</div>
                    <p class="mt-1 whitespace-pre-line text-sm text-secondary">{{ comment.content }}</p>
                </div>
                <div v-if="!event.comments?.length" class="py-4 text-sm text-secondary">Noch keine Kommentare.</div>
            </div>
        </section>

        <Teleport to="body">
            <div v-if="showEditModal" class="fixed inset-0 z-[80] flex items-center justify-center bg-black/60 p-4" @click.self="showEditModal = false">
                <form class="max-h-[92vh] w-full max-w-3xl overflow-y-auto rounded-2xl border border-border bg-card p-5 shadow-xl" @submit.prevent="updateEvent">
                    <div class="flex items-start justify-between gap-4">
                        <div>
                            <h2 class="text-xl font-semibold text-primary">Event bearbeiten</h2>
                            <p class="mt-1 text-sm text-secondary">Änderungen gelten für dieses Event.</p>
                        </div>
                        <button type="button" class="rounded-lg border border-border px-3 py-1 text-secondary hover:text-primary" @click="showEditModal = false">
                            Schließen
                        </button>
                    </div>

                    <div class="mt-5 grid gap-4 md:grid-cols-2">
                        <div class="md:col-span-2">
                            <label class="text-sm font-semibold text-primary" for="edit-title">Titel</label>
                            <input id="edit-title" v-model="editForm.title" class="mt-1 w-full rounded-lg border-border bg-inputBg text-primary" required />
                            <p v-if="editForm.errors.title" class="mt-1 text-sm text-error">{{ editForm.errors.title }}</p>
                        </div>

                        <div>
                            <label class="text-sm font-semibold text-primary" for="edit-type">Typ</label>
                            <select id="edit-type" v-model="editForm.type" class="mt-1 w-full rounded-lg border-border bg-inputBg text-primary">
                                <option v-for="type in eventTypes" :key="type" :value="type">{{ typeLabels[type] || type }}</option>
                            </select>
                        </div>

                        <div>
                            <label class="text-sm font-semibold text-primary" for="edit-visibility">Sichtbarkeit</label>
                            <select id="edit-visibility" v-model="editForm.visibility" class="mt-1 w-full rounded-lg border-border bg-inputBg text-primary">
                                <option v-for="visibility in visibilities" :key="visibility" :value="visibility">{{ visibilityLabels[visibility] || visibility }}</option>
                            </select>
                        </div>

                        <div>
                            <label class="text-sm font-semibold text-primary" for="edit-club">Verein</label>
                            <select id="edit-club" v-model="editForm.club_id" class="mt-1 w-full rounded-lg border-border bg-inputBg text-primary">
                                <option value="">Kein Verein</option>
                                <option v-for="club in clubs" :key="club.id" :value="club.id">{{ club.name }}</option>
                            </select>
                        </div>

                        <div>
                            <label class="text-sm font-semibold text-primary" for="edit-team">Team</label>
                            <select id="edit-team" v-model="editForm.team_id" class="mt-1 w-full rounded-lg border-border bg-inputBg text-primary">
                                <option value="">Kein Team</option>
                                <option v-for="team in filteredTeams" :key="team.id" :value="team.id">{{ team.name }}</option>
                            </select>
                            <p v-if="editForm.errors.team_id" class="mt-1 text-sm text-error">{{ editForm.errors.team_id }}</p>
                        </div>

                        <div>
                            <label class="text-sm font-semibold text-primary" for="edit-start">Start</label>
                            <input id="edit-start" v-model="editForm.start_time" type="datetime-local" class="mt-1 w-full rounded-lg border-border bg-inputBg text-primary" required />
                            <p v-if="editForm.errors.start_time" class="mt-1 text-sm text-error">{{ editForm.errors.start_time }}</p>
                        </div>

                        <div>
                            <label class="text-sm font-semibold text-primary" for="edit-end">Ende</label>
                            <input id="edit-end" v-model="editForm.end_time" type="datetime-local" class="mt-1 w-full rounded-lg border-border bg-inputBg text-primary" />
                            <p v-if="editForm.errors.end_time" class="mt-1 text-sm text-error">{{ editForm.errors.end_time }}</p>
                        </div>

                        <div>
                            <label class="text-sm font-semibold text-primary" for="edit-reminder">Erinnerung</label>
                            <input id="edit-reminder" v-model="editForm.reminder_at" type="datetime-local" class="mt-1 w-full rounded-lg border-border bg-inputBg text-primary" />
                            <p v-if="editForm.errors.reminder_at" class="mt-1 text-sm text-error">{{ editForm.errors.reminder_at }}</p>
                        </div>

                        <div>
                            <label class="text-sm font-semibold text-primary" for="edit-recurring">Wiederholung</label>
                            <select id="edit-recurring" v-model="editForm.recurring" class="mt-1 w-full rounded-lg border-border bg-inputBg text-primary">
                                <option v-for="option in recurrenceOptions" :key="option.value" :value="option.value">{{ option.label }}</option>
                            </select>
                        </div>

                        <div>
                            <label class="text-sm font-semibold text-primary" for="edit-recurrence-end">Wiederholung bis</label>
                            <input id="edit-recurrence-end" v-model="editForm.recurrence_ends_at" type="date" class="mt-1 w-full rounded-lg border-border bg-inputBg text-primary" />
                            <p v-if="editForm.errors.recurrence_ends_at" class="mt-1 text-sm text-error">{{ editForm.errors.recurrence_ends_at }}</p>
                        </div>

                        <div v-if="['weekly', 'biweekly'].includes(editForm.recurring)" class="md:col-span-2">
                            <p class="text-sm font-semibold text-primary">Wochentage</p>
                            <div class="mt-2 grid grid-cols-4 gap-2 sm:grid-cols-7">
                                <button
                                    v-for="day in weekdayOptions"
                                    :key="day.value"
                                    type="button"
                                    class="rounded-lg border px-2 py-3 text-xs font-semibold transition"
                                    :class="editForm.recurrence_days.map(Number).includes(day.value)
                                        ? 'border-buttonPrimary bg-buttonPrimary text-buttonTextPrimary'
                                        : 'border-border bg-inputBg text-primary hover:bg-muted'"
                                    @click="toggleWeekday(day.value)"
                                >
                                    {{ day.label }}
                                </button>
                            </div>
                            <p v-if="editForm.errors.recurrence_days" class="mt-1 text-sm text-error">{{ editForm.errors.recurrence_days }}</p>
                        </div>

                        <div class="md:col-span-2">
                            <p class="text-sm font-semibold text-primary">Adresse</p>
                            <div class="mt-2 grid grid-cols-1 gap-3 sm:grid-cols-2">
                                <div class="sm:col-span-2">
                                    <label class="text-xs font-semibold uppercase text-secondary" for="edit-location-name">Ort / Treffpunkt</label>
                                    <input id="edit-location-name" v-model="editForm.location_name" class="mt-1 w-full rounded-lg border-border bg-inputBg text-primary" placeholder="z. B. Waldhaus, Sporthalle, Vereinsheim" />
                                </div>
                                <div>
                                    <label class="text-xs font-semibold uppercase text-secondary" for="edit-location-street">Straße</label>
                                    <input id="edit-location-street" v-model="editForm.location_street" class="mt-1 w-full rounded-lg border-border bg-inputBg text-primary" />
                                </div>
                                <div>
                                    <label class="text-xs font-semibold uppercase text-secondary" for="edit-location-house-number">Nr.</label>
                                    <input id="edit-location-house-number" v-model="editForm.location_house_number" class="mt-1 w-full rounded-lg border-border bg-inputBg text-primary" />
                                </div>
                                <div>
                                    <label class="text-xs font-semibold uppercase text-secondary" for="edit-location-postal-code">PLZ</label>
                                    <input id="edit-location-postal-code" v-model="editForm.location_postal_code" class="mt-1 w-full rounded-lg border-border bg-inputBg text-primary" />
                                </div>
                                <div>
                                    <label class="text-xs font-semibold uppercase text-secondary" for="edit-location-city">Stadt</label>
                                    <input id="edit-location-city" v-model="editForm.location_city" class="mt-1 w-full rounded-lg border-border bg-inputBg text-primary" />
                                </div>
                                <div>
                                    <label class="text-xs font-semibold uppercase text-secondary" for="edit-location-country">Land</label>
                                    <input id="edit-location-country" v-model="editForm.location_country" maxlength="2" class="mt-1 w-full rounded-lg border-border bg-inputBg uppercase text-primary" placeholder="DE" />
                                </div>
                            </div>
                        </div>

                        <div class="md:col-span-2">
                            <label class="text-sm font-semibold text-primary" for="edit-max-participants">Maximale Teilnehmerzahl</label>
                            <input id="edit-max-participants" v-model="editForm.max_participants" type="number" min="1" max="100000" inputmode="numeric" class="mt-1 w-full rounded-lg border-border bg-inputBg text-primary" placeholder="Leer lassen = unbegrenzt" />
                            <p class="mt-1 text-xs text-secondary">Nur Zusagen zählen gegen diese Grenze. Vielleicht und Absagen bleiben möglich.</p>
                            <p v-if="editForm.errors.max_participants" class="mt-1 text-sm text-error">{{ editForm.errors.max_participants }}</p>
                        </div>

                        <label class="flex items-start gap-3 rounded-lg border border-border bg-inputBg p-4 text-sm md:col-span-2" :class="editForm.visibility === 'private' && editForm.team_id ? 'text-primary' : 'opacity-60'">
                            <input v-model="editForm.uses_penalty_catalog" type="checkbox" class="mt-1 rounded border-border bg-card" :disabled="editForm.visibility !== 'private' || !editForm.team_id">
                            <span>
                                <span class="block font-semibold">Mit Strafkatalog arbeiten</span>
                                <span class="mt-1 block text-secondary">
                                    Berechtigte Teamrollen können während des Events Strafen an anwesende Spieler vergeben.
                                </span>
                            </span>
                        </label>

                        <div class="md:col-span-2">
                            <label class="text-sm font-semibold text-primary" for="edit-notes">Notizen</label>
                            <textarea id="edit-notes" v-model="editForm.notes" rows="5" class="mt-1 w-full rounded-lg border-border bg-inputBg text-primary" />
                        </div>
                    </div>

                    <div v-if="editForm.errors.authorization" class="mt-4 rounded-lg border border-warning/40 bg-warning/10 p-3 text-sm text-warning">
                        {{ editForm.errors.authorization }}
                    </div>

                    <div class="mt-5 flex justify-end gap-3">
                        <button type="button" class="rounded-lg border border-border px-4 py-2 text-primary hover:bg-muted" @click="showEditModal = false">
                            Abbrechen
                        </button>
                        <button class="rounded-lg bg-buttonPrimary px-4 py-2 font-semibold text-buttonTextPrimary hover:bg-buttonPrimaryHover" :disabled="editForm.processing">
                            Speichern
                        </button>
                    </div>
                </form>
            </div>
        </Teleport>

        <Modal :show="showCancelModal" max-width="lg" @close="showCancelModal = false">
            <form class="p-5" @submit.prevent="cancelEvent">
                <h2 class="text-lg font-semibold text-primary">Event absagen</h2>
                <p class="mt-2 text-sm leading-6 text-secondary">
                    Teilnehmer mit Zusage werden informiert. Du kannst optional einen Grund angeben.
                </p>

                <label class="mt-4 block text-sm font-semibold text-primary" for="cancel-reason">
                    Grund
                </label>
                <textarea
                    id="cancel-reason"
                    v-model="cancelForm.reason"
                    rows="4"
                    class="mt-1 w-full resize-none rounded-lg border border-border bg-inputBg px-3 py-3 text-primary placeholder-secondary focus:border-borderHover focus:ring-borderHover"
                    placeholder="Optionaler Grund"
                />
                <p v-if="cancelForm.errors.reason" class="mt-1 text-sm text-error">{{ cancelForm.errors.reason }}</p>

                <div class="mt-5 flex justify-end gap-3">
                    <button type="button" class="rounded-lg border border-border px-4 py-2 text-primary hover:bg-muted" @click="showCancelModal = false">
                        Abbrechen
                    </button>
                    <button class="rounded-lg bg-warning px-4 py-2 font-semibold text-white hover:opacity-90" :disabled="cancelForm.processing">
                        {{ cancelForm.processing ? 'Wird abgesagt...' : 'Event absagen' }}
                    </button>
                </div>
            </form>
        </Modal>

        <ConfirmActionModal
            :show="showDeleteModal"
            title="Event löschen"
            message="Möchtest du dieses Event wirklich löschen? Teilnehmer mit Zusage werden informiert."
            confirm-label="Event löschen"
            :danger="true"
            :processing="false"
            @cancel="showDeleteModal = false"
            @confirm="deleteEvent"
        />
    </div>
</template>
