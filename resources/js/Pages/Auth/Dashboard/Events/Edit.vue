<script setup>
import AppLayout from '@/Components/Auth/Layouts/AppLayout.vue'
import { Head, Link, useForm } from '@inertiajs/vue3'
import { computed, watch } from 'vue'
import { useI18n } from 'vue-i18n'

defineOptions({ layout: AppLayout })

const props = defineProps({
    event: { type: Object, required: true },
    clubs: { type: Array, default: () => [] },
    teams: { type: Array, default: () => [] },
    eventTypes: { type: Array, default: () => [] },
    visibilities: { type: Array, default: () => [] },
    sportRoutes: { type: Array, default: () => [] },
    trainingSessions: { type: Array, default: () => [] },
})

const { t, locale } = useI18n()
const browserTimeZone = () => Intl.DateTimeFormat().resolvedOptions().timeZone || 'UTC'
const localInput = (value, dateOnly = false) => {
    if (!value) return ''
    const date = new Date(value)
    const local = new Date(date.getTime() - date.getTimezoneOffset() * 60000)
    return local.toISOString().slice(0, dateOnly ? 10 : 16)
}

const form = useForm({
    club_id: props.event.club_id || props.event.team?.club_id || '',
    team_id: props.event.team_id || '',
    sport_route_id: props.event.sport_route_id || props.event.sport_route?.id || '',
    training_session_id: props.event.training_session_id || '',
    title: props.event.title || '',
    type: props.event.type || 'training',
    visibility: props.event.visibility || 'private',
    start_time: localInput(props.event.start_time),
    end_time: localInput(props.event.end_time),
    reminder_at: localInput(props.event.reminder_at),
    recurring: props.event.recurring || '',
    recurrence_days: props.event.recurrence_days || [],
    recurrence_ends_at: localInput(props.event.recurrence_ends_at, true),
    location: props.event.location || '',
    location_name: props.event.location_name || '',
    location_street: props.event.location_street || '',
    location_house_number: props.event.location_house_number || '',
    location_postal_code: props.event.location_postal_code || '',
    location_city: props.event.location_city || '',
    location_country: props.event.location_country || 'DE',
    max_participants: props.event.max_participants || '',
    uses_penalty_catalog: Boolean(props.event.uses_penalty_catalog),
    qr_check_in_enabled: Boolean(props.event.qr_check_in_enabled),
    notes: props.event.notes || '',
    event_timezone: browserTimeZone(),
})

const typeLabels = { training: 'Training', match: 'Spiel', meeting: 'Meeting', public: 'Öffentliches Event' }
const visibilityLabels = { private: 'Team-intern', organization: 'Verein/Organisation', public: 'Öffentlich' }
const recurrenceOptions = [
    { value: '', label: 'Keine' }, { value: 'daily', label: 'Täglich' },
    { value: 'weekly', label: 'Wöchentlich' }, { value: 'biweekly', label: 'Alle zwei Wochen' },
    { value: 'monthly', label: 'Monatlich' },
]
const weekdays = [
    { value: 1, label: 'Mo' }, { value: 2, label: 'Di' }, { value: 3, label: 'Mi' },
    { value: 4, label: 'Do' }, { value: 5, label: 'Fr' }, { value: 6, label: 'Sa' }, { value: 0, label: 'So' },
]
const filteredTeams = computed(() => form.club_id
    ? props.teams.filter((team) => Number(team.club_id) === Number(form.club_id))
    : props.teams)
const availableTrainingSessions = computed(() => props.trainingSessions.filter((session) => {
    if (session.team_id && Number(session.team_id) !== Number(form.team_id)) return false
    if (session.club_id && Number(session.club_id) !== Number(form.club_id)) return false
    return true
}))
const selectedRoute = computed(() => props.sportRoutes.find((route) => Number(route.id) === Number(form.sport_route_id)))
const routeDistance = (route) => route?.distance_meters
    ? `${(Number(route.distance_meters) / 1000).toLocaleString(locale.value, { maximumFractionDigits: 1 })} km`
    : ''

watch(() => form.club_id, () => {
    if (form.team_id && !filteredTeams.value.some((team) => Number(team.id) === Number(form.team_id))) form.team_id = ''
})
watch(() => form.visibility, (value) => {
    if (value === 'public') {
        form.club_id = ''
        form.team_id = ''
        form.uses_penalty_catalog = false
        form.qr_check_in_enabled = false
    } else if (value === 'organization') {
        form.team_id = ''
        form.uses_penalty_catalog = false
    }
})
watch(() => form.type, (value) => { if (value !== 'training') form.training_session_id = '' })

const toggleWeekday = (day) => {
    const values = form.recurrence_days.map(Number)
    form.recurrence_days = values.includes(day) ? values.filter((value) => value !== day) : [...values, day].sort()
}

const submit = () => {
    form.event_timezone = browserTimeZone()
    form.location = [
        form.location_name,
        [form.location_street, form.location_house_number].filter(Boolean).join(' '),
        [form.location_postal_code, form.location_city].filter(Boolean).join(' '),
    ].filter(Boolean).join(', ')
    form.put(route('auth.events.update', props.event.id))
}
</script>

<template>
    <Head :title="`${$t('Event bearbeiten')}: ${event.title}`" />
    <div class="mx-auto max-w-5xl space-y-6">
        <header class="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
            <div>
                <Link :href="route('auth.events.show', event.id)" class="text-sm font-semibold text-air-blue hover:underline">← {{ $t('Zurück zum Event') }}</Link>
                <h1 class="mt-2 text-3xl font-bold text-primary">{{ $t('Event bearbeiten') }}</h1>
                <p class="mt-2 text-sm text-secondary">{{ $t('Alle Angaben des Events können hier geprüft und geändert werden.') }}</p>
            </div>
        </header>

        <form class="space-y-6" @submit.prevent="submit">
            <section class="rounded-2xl border border-border bg-card p-5 sm:p-6">
                <h2 class="text-xl font-semibold text-primary">{{ $t('Grunddaten') }}</h2>
                <div class="mt-5 grid gap-4 md:grid-cols-2">
                    <label class="md:col-span-2"><span class="field-label">{{ $t('events.fields.title') }}</span><input v-model="form.title" required maxlength="255" class="field-input" /><span v-if="form.errors.title" class="field-error">{{ form.errors.title }}</span></label>
                    <label><span class="field-label">{{ $t('events.fields.type') }}</span><select v-model="form.type" class="field-input"><option v-for="type in eventTypes" :key="type" :value="type">{{ $t(typeLabels[type] || type) }}</option></select></label>
                    <label><span class="field-label">{{ $t('events.fields.visibility') }}</span><select v-model="form.visibility" class="field-input"><option v-for="value in visibilities" :key="value" :value="value">{{ $t(visibilityLabels[value] || value) }}</option></select></label>
                    <label v-if="form.visibility !== 'public'"><span class="field-label">{{ $t('events.fields.club') }}</span><select v-model="form.club_id" class="field-input"><option value="">{{ $t('events.none.club') }}</option><option v-for="club in clubs" :key="club.id" :value="club.id">{{ club.name }}</option></select><span v-if="form.errors.club_id" class="field-error">{{ form.errors.club_id }}</span></label>
                    <label v-if="form.visibility === 'private'"><span class="field-label">{{ $t('events.fields.team') }}</span><select v-model="form.team_id" class="field-input"><option value="">{{ $t('events.none.team') }}</option><option v-for="team in filteredTeams" :key="team.id" :value="team.id">{{ team.name }}</option></select><span v-if="form.errors.team_id" class="field-error">{{ form.errors.team_id }}</span></label>
                </div>
            </section>

            <section class="rounded-2xl border border-border bg-card p-5 sm:p-6">
                <h2 class="text-xl font-semibold text-primary">{{ $t('Zeit und Wiederholung') }}</h2>
                <div class="mt-5 grid gap-4 md:grid-cols-2">
                    <label><span class="field-label">{{ $t('events.fields.start') }}</span><input v-model="form.start_time" required type="datetime-local" class="field-input" /><span v-if="form.errors.start_time" class="field-error">{{ form.errors.start_time }}</span></label>
                    <label><span class="field-label">{{ $t('events.fields.end') }}</span><input v-model="form.end_time" type="datetime-local" class="field-input" /><span v-if="form.errors.end_time" class="field-error">{{ form.errors.end_time }}</span></label>
                    <label><span class="field-label">{{ $t('events.fields.reminder') }}</span><input v-model="form.reminder_at" type="datetime-local" class="field-input" /><span v-if="form.errors.reminder_at" class="field-error">{{ form.errors.reminder_at }}</span></label>
                    <label><span class="field-label">{{ $t('events.fields.recurrence') }}</span><select v-model="form.recurring" class="field-input"><option v-for="option in recurrenceOptions" :key="option.value" :value="option.value">{{ $t(option.label) }}</option></select></label>
                    <label v-if="form.recurring"><span class="field-label">{{ $t('events.fields.recurrence_end') }}</span><input v-model="form.recurrence_ends_at" type="date" class="field-input" /><span v-if="form.errors.recurrence_ends_at" class="field-error">{{ form.errors.recurrence_ends_at }}</span></label>
                    <div v-if="['weekly', 'biweekly'].includes(form.recurring)" class="md:col-span-2"><span class="field-label">{{ $t('events.fields.recurrence_days') }}</span><div class="mt-2 grid grid-cols-4 gap-2 sm:grid-cols-7"><button v-for="day in weekdays" :key="day.value" type="button" class="rounded-lg border px-3 py-3 font-semibold" :class="form.recurrence_days.map(Number).includes(day.value) ? 'border-buttonPrimary bg-buttonPrimary text-buttonTextPrimary' : 'border-border bg-inputBg text-primary'" @click="toggleWeekday(day.value)">{{ day.label }}</button></div></div>
                </div>
            </section>

            <section class="rounded-2xl border border-border bg-card p-5 sm:p-6">
                <h2 class="text-xl font-semibold text-primary">{{ $t('Ort und Adresse') }}</h2>
                <div class="mt-5 grid gap-4 md:grid-cols-2">
                    <label class="md:col-span-2"><span class="field-label">{{ $t('Ort / Treffpunkt') }}</span><input v-model="form.location_name" class="field-input" :placeholder="$t('z. B. Sporthalle oder Vereinsheim')" /></label>
                    <label><span class="field-label">{{ $t('Straße') }}</span><input v-model="form.location_street" class="field-input" /></label>
                    <label><span class="field-label">{{ $t('Nr.') }}</span><input v-model="form.location_house_number" class="field-input" /></label>
                    <label><span class="field-label">{{ $t('PLZ') }}</span><input v-model="form.location_postal_code" class="field-input" /></label>
                    <label><span class="field-label">{{ $t('Stadt') }}</span><input v-model="form.location_city" class="field-input" /></label>
                    <label><span class="field-label">{{ $t('Land') }}</span><input v-model="form.location_country" maxlength="2" class="field-input uppercase" placeholder="DE" /></label>
                </div>
            </section>

            <section class="rounded-2xl border border-border bg-card p-5 sm:p-6">
                <h2 class="text-xl font-semibold text-primary">{{ $t('Organisation und Teilnahme') }}</h2>
                <div class="mt-5 grid gap-4 md:grid-cols-2">
                    <label class="md:col-span-2"><span class="field-label">{{ $t('events.route.label') }}</span><select v-model="form.sport_route_id" class="field-input"><option value="">{{ $t('events.route.none') }}</option><option v-for="sportRoute in sportRoutes" :key="sportRoute.id" :value="sportRoute.id">{{ sportRoute.title }}{{ routeDistance(sportRoute) ? ` · ${routeDistance(sportRoute)}` : '' }}</option></select><span v-if="selectedRoute" class="mt-1 block text-xs text-secondary">{{ [selectedRoute.start_name, selectedRoute.end_name].filter(Boolean).join(' → ') }}</span></label>
                    <label v-if="form.type === 'training'" class="md:col-span-2"><span class="field-label">{{ $t('Vorbereitete Trainingseinheit') }}</span><select v-model="form.training_session_id" class="field-input"><option value="">{{ $t('Keine Trainingseinheit verknüpfen') }}</option><option v-for="session in availableTrainingSessions" :key="session.id" :value="session.id">{{ session.title }}{{ session.duration_minutes ? ` · ${session.duration_minutes} Min.` : '' }}</option></select></label>
                    <label class="md:col-span-2"><span class="field-label">{{ $t('Maximale Teilnehmerzahl') }}</span><input v-model="form.max_participants" type="number" min="1" max="100000" class="field-input" :placeholder="$t('Leer lassen = unbegrenzt')" /><span v-if="form.errors.max_participants" class="field-error">{{ form.errors.max_participants }}</span></label>
                    <label class="option-card" :class="form.visibility === 'private' && form.team_id ? '' : 'opacity-60'"><input v-model="form.uses_penalty_catalog" type="checkbox" :disabled="form.visibility !== 'private' || !form.team_id" /><span><strong>{{ $t('Mit Strafkatalog arbeiten') }}</strong><small>{{ $t('Berechtigte Teamrollen können während des Events Strafen an anwesende Spieler vergeben.') }}</small></span></label>
                    <label class="option-card" :class="form.visibility === 'public' ? 'opacity-60' : ''"><input v-model="form.qr_check_in_enabled" type="checkbox" :disabled="form.visibility === 'public'" /><span><strong>{{ $t('QR-Check-in mit Mitgliedskarte') }}</strong><small>{{ $t('Trainer scannen beim Termin den automatisch wechselnden QR-Code auf der Mitgliedskarte.') }}</small></span></label>
                    <label class="md:col-span-2"><span class="field-label">{{ $t('events.fields.notes') }}</span><textarea v-model="form.notes" rows="6" class="field-input" /></label>
                </div>
            </section>

            <div v-if="Object.keys(form.errors).length" class="rounded-lg border border-error/40 bg-error/10 p-4 text-sm text-error">{{ $t('Bitte prüfe die markierten Felder.') }}</div>
            <div class="sticky bottom-0 flex flex-col-reverse gap-3 rounded-2xl border border-border bg-card/95 p-4 shadow-xl backdrop-blur sm:flex-row sm:justify-end">
                <Link :href="route('auth.events.show', event.id)" class="rounded-lg border border-border px-5 py-3 text-center font-semibold text-primary hover:bg-muted">{{ $t('Abbrechen') }}</Link>
                <button class="rounded-lg bg-buttonPrimary px-5 py-3 font-semibold text-buttonTextPrimary hover:bg-buttonPrimaryHover disabled:opacity-60" :disabled="form.processing">{{ form.processing ? $t('Wird gespeichert …') : $t('Änderungen speichern') }}</button>
            </div>
        </form>
    </div>
</template>

<style scoped>
.field-label { display: block; font-size: .875rem; font-weight: 600; color: var(--color-primary); }
.field-input { margin-top: .25rem; width: 100%; border-radius: .5rem; border-color: var(--color-border); background: var(--color-input-bg); color: var(--color-primary); }
.field-error { margin-top: .25rem; display: block; font-size: .875rem; color: var(--color-error); }
.option-card { display: flex; align-items: flex-start; gap: .75rem; border: 1px solid var(--color-border); border-radius: .75rem; background: var(--color-input-bg); padding: 1rem; color: var(--color-primary); }
.option-card input { margin-top: .2rem; }
.option-card strong, .option-card small { display: block; }
.option-card small { margin-top: .25rem; color: var(--color-secondary); line-height: 1.4; }
</style>
