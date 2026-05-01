<script setup>
import AppLayout from '@/Components/Auth/Layouts/AppLayout.vue'
import { Head, Link, router, useForm } from '@inertiajs/vue3'
import { computed, ref, watch } from 'vue'
import { useI18n } from 'vue-i18n'

defineOptions({ layout: AppLayout })

const props = defineProps({
    events: Array,
    clubs: Array,
    teams: Array,
    eventTypes: Array,
    visibilities: Array,
})

const { t } = useI18n()

const showCreateModal = ref(false)

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
}

const closeCreateModal = () => {
    showCreateModal.value = false
    form.clearErrors()
}

const submit = () => {
    form.event_timezone = browserTimeZone()

    form.post(route('auth.events.store'), {
        preserveScroll: true,
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
</script>

<template>

    <Head :title="$t('Events')" />

    <div class="space-y-6">
        <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
            <div>
                <h1 class="text-2xl font-semibold text-primary">{{ $t('Events') }}</h1>
                <p class="mt-1 text-sm text-secondary">{{ $t('events.subtitle') }}</p>
            </div>

            <button type="button"
                class="rounded-lg bg-buttonPrimary px-4 py-2 font-semibold text-buttonTextPrimary transition hover:bg-buttonPrimaryHover"
                @click="showCreateModal = true">
                + {{ $t('events.create') }}
            </button>
        </div>

        <div v-if="showCreateModal" class="fixed inset-0 z-50 flex items-center justify-center bg-black/60 p-4"
            @click.self="closeCreateModal">
            <div
                class="max-h-[90vh] w-full max-w-5xl overflow-y-auto rounded-xl border border-border bg-card shadow-xl">
                <div
                    class="sticky top-0 z-10 flex items-start justify-between gap-4 border-b border-border bg-card p-5">
                    <div>
                        <h2 class="text-xl font-semibold text-primary">
                            {{ $t('events.create') }}
                        </h2>
                        <p class="mt-1 text-sm text-secondary">
                            Training, Event oder Meeting erstellen
                        </p>
                    </div>

                    <button type="button"
                        class="rounded-lg border border-border px-3 py-1 text-secondary transition hover:border-borderHover hover:text-primary"
                        @click="closeCreateModal">
                        ✕
                    </button>
                </div>

                <form class="p-5" @submit.prevent="submit">
                    <div class="grid gap-6 lg:grid-cols-3">
                        <div class="space-y-5 lg:col-span-2">
                            <section class="rounded-lg border border-border bg-inputBg p-4">
                                <h3 class="mb-4 text-sm font-semibold text-primary">
                                    Basisdaten
                                </h3>

                                <div class="space-y-4">
                                    <div>
                                        <label for="event-title" class="block text-sm font-semibold text-primary">
                                            {{ $t('events.fields.title') }}
                                        </label>
                                        <input id="event-title" v-model="form.title"
                                            class="mt-1 w-full rounded-lg border border-border bg-card px-3 py-2 text-primary placeholder-secondary focus:border-borderHover focus:ring-borderHover"
                                            :placeholder="$t('events.placeholders.title')" required>
                                        <div v-if="form.errors.title" class="mt-1 text-sm text-error">
                                            {{ form.errors.title }}
                                        </div>
                                    </div>

                                    <div class="grid gap-4 md:grid-cols-2">
                                        <div>
                                            <label for="event-type" class="block text-sm font-semibold text-primary">
                                                {{ $t('events.fields.type') }}
                                            </label>
                                            <select id="event-type" v-model="form.type"
                                                class="mt-1 w-full rounded-lg border border-border bg-card px-3 py-2 text-primary focus:border-borderHover focus:ring-borderHover">
                                                <option v-for="type in eventTypes" :key="type" :value="type">
                                                    {{ $t(typeLabels[type] || type) }}
                                                </option>
                                            </select>
                                        </div>

                                        <div>
                                            <label for="event-visibility"
                                                class="block text-sm font-semibold text-primary">
                                                {{ $t('events.fields.visibility') }}
                                            </label>
                                            <select id="event-visibility" v-model="form.visibility"
                                                class="mt-1 w-full rounded-lg border border-border bg-card px-3 py-2 text-primary focus:border-borderHover focus:ring-borderHover">
                                                <option v-for="visibility in visibilities" :key="visibility"
                                                    :value="visibility">
                                                    {{ $t(visibilityLabels[visibility] || visibility) }}
                                                </option>
                                            </select>
                                        </div>

                                        <div>
                                            <label for="event-club" class="block text-sm font-semibold text-primary">
                                                {{ $t('events.fields.club') }}
                                            </label>
                                            <select id="event-club" v-model="form.club_id"
                                                class="mt-1 w-full rounded-lg border border-border bg-card px-3 py-2 text-primary focus:border-borderHover focus:ring-borderHover">
                                                <option value="">{{ $t('events.none.club') }}</option>
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
                                                class="mt-1 w-full rounded-lg border border-border bg-card px-3 py-2 text-primary focus:border-borderHover focus:ring-borderHover">
                                                <option value="">{{ $t('events.none.team') }}</option>
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
                                </div>
                            </section>

                            <section class="rounded-lg border border-border bg-inputBg p-4">
                                <h3 class="mb-4 text-sm font-semibold text-primary">
                                    Details
                                </h3>

                                <div class="space-y-4">
                                    <div>
                                        <label for="event-location" class="block text-sm font-semibold text-primary">
                                            {{ $t('events.fields.location') }}
                                        </label>
                                        <input id="event-location" v-model="form.location"
                                            class="mt-1 w-full rounded-lg border border-border bg-card px-3 py-2 text-primary placeholder-secondary focus:border-borderHover focus:ring-borderHover"
                                            :placeholder="$t('events.placeholders.location')">
                                    </div>

                                    <div>
                                        <label for="event-notes" class="block text-sm font-semibold text-primary">
                                            {{ $t('events.fields.notes') }}
                                        </label>
                                        <textarea id="event-notes" v-model="form.notes" rows="4"
                                            class="mt-1 w-full resize-none rounded-lg border border-border bg-card px-3 py-2 text-primary placeholder-secondary focus:border-borderHover focus:ring-borderHover"
                                            :placeholder="$t('events.placeholders.notes')" />
                                    </div>
                                </div>
                            </section>
                        </div>

                        <div class="space-y-5">
                            <section class="rounded-lg border border-border bg-inputBg p-4">
                                <h3 class="mb-4 text-sm font-semibold text-primary">
                                    Zeit & Wiederholung
                                </h3>

                                <div class="space-y-4">
                                    <div>
                                        <label for="event-start" class="block text-sm font-semibold text-primary">
                                            {{ $t('events.fields.start') }}
                                        </label>
                                        <input id="event-start" v-model="form.start_time"
                                            class="date-input mt-1 w-full rounded-lg border border-border bg-card px-3 py-2 text-primary focus:border-borderHover focus:ring-borderHover"
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
                                            class="date-input mt-1 w-full rounded-lg border border-border bg-card px-3 py-2 text-primary focus:border-borderHover focus:ring-borderHover"
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
                                            class="date-input mt-1 w-full rounded-lg border border-border bg-card px-3 py-2 text-primary focus:border-borderHover focus:ring-borderHover"
                                            type="datetime-local">
                                        <div v-if="form.errors.reminder_at" class="mt-1 text-sm text-error">
                                            {{ form.errors.reminder_at }}
                                        </div>
                                    </div>

                                    <div class="border-t border-border pt-4">
                                        <label for="event-recurring" class="block text-sm font-semibold text-primary">
                                            {{ $t('events.fields.recurrence') }}
                                        </label>
                                        <select id="event-recurring" v-model="form.recurring"
                                            class="mt-1 w-full rounded-lg border border-border bg-card px-3 py-2 text-primary focus:border-borderHover focus:ring-borderHover">
                                            <option v-for="option in recurrenceOptions" :key="option.value"
                                                :value="option.value">
                                                {{ $t(option.label) }}
                                            </option>
                                        </select>
                                    </div>

                                    <div v-if="form.recurring">
                                        <label for="event-recurrence-end"
                                            class="block text-sm font-semibold text-primary">
                                            {{ $t('events.fields.recurrence_end') }}
                                        </label>
                                        <input id="event-recurrence-end" v-model="form.recurrence_ends_at"
                                            class="date-input mt-1 w-full rounded-lg border border-border bg-card px-3 py-2 text-primary focus:border-borderHover focus:ring-borderHover"
                                            type="date">
                                        <div v-if="form.errors.recurrence_ends_at" class="mt-1 text-sm text-error">
                                            {{ form.errors.recurrence_ends_at }}
                                        </div>
                                    </div>

                                    <div v-if="['weekly', 'biweekly'].includes(form.recurring)">
                                        <div class="mb-2 text-sm font-semibold text-primary">
                                            {{ $t('events.fields.recurrence_days') }}
                                        </div>

                                        <div class="grid grid-cols-4 gap-2 sm:grid-cols-7 lg:grid-cols-4">
                                            <button v-for="day in weekdayOptions" :key="day.value" type="button"
                                                class="rounded-lg border px-2 py-2 text-xs font-semibold transition"
                                                :class="form.recurrence_days.map(Number).includes(day.value)
                                                    ? 'border-buttonPrimary bg-buttonPrimary text-buttonTextPrimary'
                                                    : 'border-border bg-card text-primary hover:bg-muted'"
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
                                </div>
                            </section>

                            <div class="flex gap-3">
                                <button type="button"
                                    class="flex-1 rounded-lg border border-border px-4 py-2 font-semibold text-secondary transition hover:border-borderHover hover:text-primary"
                                    @click="closeCreateModal">
                                    Abbrechen
                                </button>

                                <button type="submit"
                                    class="flex-1 rounded-lg bg-buttonPrimary px-4 py-2 font-semibold text-buttonTextPrimary transition hover:bg-buttonPrimaryHover disabled:opacity-50"
                                    :disabled="form.processing">
                                    {{ form.processing ? 'Speichern...' : $t('events.create') }}
                                </button>
                            </div>
                        </div>
                    </div>
                </form>
            </div>
        </div>

        <div class="grid gap-4 md:grid-cols-2 xl:grid-cols-3">
            <article v-for="event in events" :key="event.id"
                class="rounded-lg border border-border bg-card p-4 transition hover:border-borderHover">
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
                                    class="font-semibold text-primary hover:underline">
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

                        <div class="mt-3 space-y-1">
                            <p class="text-sm font-semibold text-primary">
                                {{ eventDateTimeLabel(event) }}
                            </p>

                            <p class="text-sm text-secondary">
                                {{ event.team?.name || event.club?.name || $t('events.public_scope') }}
                            </p>

                            <p v-if="event.location" class="text-sm text-secondary">
                                📍 {{ event.location }}
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
                    </div>
                </div>
            </article>
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