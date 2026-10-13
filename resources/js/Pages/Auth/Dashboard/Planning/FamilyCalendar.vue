<script setup>
import AppLayout from '@/Components/Auth/Layouts/AppLayout.vue'
import PlanningFields from '@/Components/Planning/PlanningFields.vue'
import { apiDateTime, localDateTime, planningError } from '@/Components/Planning/planningState.js'
import { Head, Link } from '@inertiajs/vue3'
import { computed, onMounted, ref, watch } from 'vue'
import { useI18n } from 'vue-i18n'

defineOptions({ layout: AppLayout })
const { locale } = useI18n()
const labels = {
    title: ['Familienkalender', 'Family Calendar'], planning: ['Saison & Wettkampf', 'Season & Competition'],
    month: ['Monat', 'Month'], person: ['Person', 'Person'], all: ['Alle', 'All'], self: ['Eigener Termin', 'My Appointment'],
    private: ['Private Termine', 'Private Appointments'], events: ['Vereins- und Teamtermine', 'Club and Team Events'],
    new: ['Privater Termin', 'Private Appointment'], name: ['Titel', 'Title'], start: ['Beginn', 'Start'], end: ['Ende', 'End'],
    location: ['Ort', 'Location'], save: ['Speichern', 'Save'], cancel: ['Abbrechen', 'Cancel'], edit: ['Bearbeiten', 'Edit'],
    remove: ['Löschen', 'Delete'], confirmDelete: ['Privaten Termin löschen?', 'Delete this private appointment?'],
    reload: ['Neu laden', 'Reload'], previous: ['Vorheriger Monat', 'Previous Month'], next: ['Nächster Monat', 'Next Month'],
    empty: ['Keine Termine in diesem Monat', 'No Appointments This Month'], loading: ['Wird geladen', 'Loading'],
    saved: ['Gespeichert', 'Saved'], respond: ['Teilnahme beantworten', 'Respond to Event'],
    yes: ['Zusage', 'Attending'], no: ['Absage', 'Not Attending'], maybe: ['Vielleicht', 'Maybe'], late: ['Verspätet', 'Late'],
    waitlist: ['Warteliste', 'Waitlist'], unanswered: ['Unbeantwortet', 'Unanswered'], response: ['Antwort', 'Response'],
    responseReason: ['Kommentar', 'Comment'], absenceReason: ['Absagegrund', 'Absence Reason'],
    requirements: ['Teilnahmevoraussetzungen akzeptiert', 'Participation Requirements Accepted'],
    consent: ['Teilnahme-Einwilligung', 'Participation Consent'], group: ['Campgruppe', 'Camp Group'],
    privacy: ['Datenschutzhinweis akzeptiert', 'Privacy Notice Accepted'], travel: ['Reise-Einwilligung', 'Travel Consent'],
    emergency: ['Notfallkontakt', 'Emergency Contact'], contactName: ['Name des Kontakts', 'Contact Name'], phone: ['Telefon', 'Phone'],
    relationship: ['Beziehung', 'Relationship'], dietary: ['Verpflegungshinweise', 'Dietary Notes'],
    readOnly: ['Antwort geschlossen', 'Responses Closed'], club: ['Verein', 'Club'],
}
const l = (key) => labels[key]?.[locale.value === 'de' ? 0 : 1] || key
const now = new Date()
const month = ref(`${now.getFullYear()}-${String(now.getMonth() + 1).padStart(2, '0')}`)
const emptyCalendar = () => ({ self: null, children: [], events: [], entries: [] })
const calendar = ref(emptyCalendar()), loading = ref(false), busy = ref(false), error = ref(''), success = ref('')
const person = ref('all'), entryForm = ref(null), responseForm = ref(null)
let requestId = 0
const api = async (method, url, data) => (await window.axios({ method, url, data, headers: { Accept: 'application/json' } })).data.data
const childKey = (child) => `${child.id}:${child.club_id}`
const people = computed(() => [...new Map(calendar.value.children.map((child) => [child.id, child])).values()])
const personName = (entry) => calendar.value.children.find((child) => child.id === entry.child_user_id && child.club_id === entry.club_id)?.name || calendar.value.self?.name
const privateEntries = computed(() => calendar.value.entries.filter((entry) => person.value === 'all' || (entry.child_user_id || calendar.value.self?.id) === Number(person.value)))
const eventDays = computed(() => {
    const days = new Map()
    for (const event of calendar.value.events) {
        if (person.value !== 'all' && event.person_id !== Number(person.value)) continue
        const key = localDateTime(event.starts_at).slice(0, 10)
        if (!days.has(key)) days.set(key, [])
        days.get(key).push(event)
    }
    return [...days.entries()].sort(([a], [b]) => a.localeCompare(b))
})
const field = (key, label, type = 'text', extra = {}) => ({ key, label: l(label), type, ...extra })
const entryFields = computed(() => [
    field('title', 'name', 'text', { required: true }),
    field('child', 'person', 'text', { required: true, options: [{ value: 'self', label: calendar.value.self?.name || l('self') }, ...calendar.value.children.map((child) => ({ value: childKey(child), label: `${child.name} (${l('club')} #${child.club_id})` }))] }),
    field('starts_at', 'start', 'datetime-local', { required: true }), field('ends_at', 'end', 'datetime-local', { required: true }),
    field('location', 'location'),
])
const load = async () => {
    const token = ++requestId
    loading.value = true; error.value = ''; calendar.value = emptyCalendar(); entryForm.value = null; responseForm.value = null
    try {
        const data = await api('get', `/api/v1/family-calendar?month=${encodeURIComponent(month.value)}`)
        if (token === requestId) {
            calendar.value = data
            if (person.value !== 'all' && Number(person.value) !== data.self.id && !data.children.some((child) => child.id === Number(person.value))) person.value = 'all'
        }
    } catch (e) { if (token === requestId) error.value = planningError(e) }
    finally { if (token === requestId) loading.value = false }
}
const moveMonth = (delta) => {
    const [year, index] = month.value.split('-').map(Number)
    const date = new Date(year, index - 1 + delta, 1)
    month.value = `${date.getFullYear()}-${String(date.getMonth() + 1).padStart(2, '0')}`
}
const editEntry = (entry = null) => {
    responseForm.value = null; success.value = ''; error.value = ''
    entryForm.value = { id: entry?.id, title: entry?.title || '', location: entry?.location || '',
        starts_at: localDateTime(entry?.starts_at) || `${month.value}-01T09:00`, ends_at: localDateTime(entry?.ends_at) || `${month.value}-01T10:00`,
        child: entry?.child_user_id ? `${entry.child_user_id}:${entry.club_id}` : 'self' }
}
const run = async (action) => {
    if (busy.value) return
    busy.value = true; error.value = ''; success.value = ''
    try {
        await action(); entryForm.value = null; responseForm.value = null; success.value = l('saved')
        await load()
    } catch (e) {
        error.value = planningError(e)
        if ([403, 404].includes(e.response?.status)) { calendar.value = emptyCalendar(); entryForm.value = null; responseForm.value = null }
    } finally { busy.value = false }
}
const saveEntry = () => {
    const form = entryForm.value
    const child = calendar.value.children.find((row) => childKey(row) === form.child)
    const payload = { title: form.title, location: form.location || null, starts_at: apiDateTime(form.starts_at), ends_at: apiDateTime(form.ends_at), child_user_id: child?.id || null, club_id: child?.club_id || null }
    return run(() => api(form.id ? 'put' : 'post', `/api/v1/family-calendar/entries${form.id ? `/${form.id}` : ''}`, payload))
}
const deleteEntry = (entry) => { if (window.confirm(l('confirmDelete'))) return run(() => api('delete', `/api/v1/family-calendar/entries/${entry.id}`)) }
const editResponse = (event) => {
    entryForm.value = null; success.value = ''; error.value = ''
    responseForm.value = { event, status: ['yes', 'no', 'maybe', 'late'].includes(event.response_status) ? event.response_status : 'yes',
        response_reason: '', absence_reason: '', requirements_accepted: false, consent_accepted: false,
        camp_group_key: event.camp_group_key || '', camp_privacy_notice_accepted: false, camp_travel_consent_accepted: false,
        contact_name: '', contact_phone: '', contact_relationship: '', camp_dietary_notes: '' }
}
const saveResponse = () => {
    const form = responseForm.value, event = form.event
    const payload = { status: form.status, response_reason: form.response_reason || null, absence_reason: form.absence_reason || null }
    if (form.status === 'yes') {
        Object.assign(payload, { requirements_accepted: form.requirements_accepted, consent_accepted: form.consent_accepted, consent_version: event.participation_consent_version,
            camp_group_key: form.camp_group_key || null, camp_privacy_notice_accepted: form.camp_privacy_notice_accepted,
            camp_privacy_notice_version: event.camp_privacy_notice_version, camp_travel_consent_accepted: form.camp_travel_consent_accepted,
            camp_dietary_notes: form.camp_dietary_notes || null })
        if (form.contact_name || form.contact_phone) payload.camp_emergency_contact = { name: form.contact_name, phone: form.contact_phone, relationship: form.contact_relationship || null }
    }
    return run(() => api('post', `/api/v1/family-calendar/events/${event.id}/people/${event.person_id}/response`, payload))
}
const date = (value) => value ? new Date(value).toLocaleString(locale.value) : '-'
const day = (value) => new Date(`${value}T12:00`).toLocaleDateString(locale.value, { weekday: 'long', day: 'numeric', month: 'long' })
watch(month, () => { success.value = ''; load() })
onMounted(load)
</script>

<template>
    <Head :title="l('title')" />
    <main class="mx-auto max-w-6xl space-y-5 p-4 text-primary md:p-6" :aria-busy="loading || busy">
        <header class="flex flex-wrap items-center justify-between gap-3"><h1 class="text-2xl font-bold">{{ l('title') }}</h1><Link :href="route('auth.planning.index')" class="text-link">{{ l('planning') }}</Link></header>
        <div class="flex flex-wrap items-center gap-3 border-b border-border pb-4">
            <button type="button" :disabled="busy || loading" :title="l('previous')" :aria-label="l('previous')" @click="moveMonth(-1)"><i class="las la-angle-left text-xl" /></button>
            <label class="text-sm">{{ l('month') }}<input v-model="month" required type="month" :disabled="busy || loading" class="ml-2 max-w-full rounded border-border bg-inputBg" /></label>
            <button type="button" :disabled="busy || loading" :title="l('next')" :aria-label="l('next')" @click="moveMonth(1)"><i class="las la-angle-right text-xl" /></button>
            <label v-if="calendar.self" class="text-sm">{{ l('person') }}<select v-model="person" class="ml-2 max-w-full rounded border-border bg-inputBg"><option value="all">{{ l('all') }}</option><option :value="calendar.self.id">{{ calendar.self.name }}</option><option v-for="child in people" :key="child.id" :value="child.id">{{ child.name }}</option></select></label>
            <button type="button" :disabled="busy || loading" :title="l('reload')" :aria-label="l('reload')" @click="load"><i class="las la-sync text-xl" /></button>
            <button v-if="calendar.self" type="button" :disabled="busy || loading" class="rounded bg-buttonPrimary px-3 py-2 text-sm text-buttonTextPrimary" @click="editEntry()"><i class="las la-plus" /> {{ l('new') }}</button>
        </div>
        <p v-if="error" role="alert" class="text-error">{{ error }}</p><p v-if="success" role="status" class="text-success">{{ success }}</p><p v-if="loading" role="status">{{ l('loading') }}</p>
        <form v-if="entryForm" class="space-y-4 border-b border-border pb-5" @submit.prevent="saveEntry">
            <h2 class="text-lg font-bold">{{ l('new') }}</h2><PlanningFields :model="entryForm" :fields="entryFields" :disabled="busy" />
            <div class="flex gap-3"><button :disabled="busy" class="rounded bg-buttonPrimary px-4 py-2 text-buttonTextPrimary"><i class="las la-save" /> {{ l('save') }}</button><button type="button" :disabled="busy" @click="entryForm = null">{{ l('cancel') }}</button></div>
        </form>
        <form v-if="responseForm" class="space-y-4 border-b border-border pb-5" @submit.prevent="saveResponse">
            <h2 class="text-lg font-bold">{{ responseForm.event.title }} · {{ responseForm.event.person_name }}</h2>
            <PlanningFields :model="responseForm" :disabled="busy" :fields="[field('status', 'response', 'text', { required: true, options: ['yes', 'no', 'maybe', 'late'].map((status) => ({ value: status, label: l(status) })) }), field('response_reason', 'responseReason', 'textarea', { maxlength: 500 }), ...(responseForm.status === 'no' ? [field('absence_reason', 'absenceReason', 'textarea', { maxlength: 1000 })] : [])]" />
            <fieldset v-if="responseForm.status === 'yes'" :disabled="busy" class="space-y-3">
                <template v-if="responseForm.event.participation_requirements?.length"><ul class="list-inside list-disc text-sm"><li v-for="requirement in responseForm.event.participation_requirements" :key="requirement">{{ requirement }}</li></ul><label class="flex items-start gap-2"><input v-model="responseForm.requirements_accepted" required type="checkbox" />{{ l('requirements') }}</label></template>
                <label v-if="responseForm.event.participation_consent_required" class="flex items-start gap-2"><input v-model="responseForm.consent_accepted" required type="checkbox" />{{ l('consent') }} {{ responseForm.event.participation_consent_version }}</label>
                <PlanningFields v-if="responseForm.event.camp_groups?.length" :model="responseForm" :fields="[field('camp_group_key', 'group', 'text', { required: true, options: responseForm.event.camp_groups.map((group) => ({ value: group.key, label: group.name })) })]" />
                <label v-if="responseForm.event.camp_privacy_notice_version" class="flex items-start gap-2"><input v-model="responseForm.camp_privacy_notice_accepted" required type="checkbox" />{{ l('privacy') }} {{ responseForm.event.camp_privacy_notice_version }}</label>
                <label v-if="responseForm.event.camp_travel_consent_required" class="flex items-start gap-2"><input v-model="responseForm.camp_travel_consent_accepted" required type="checkbox" />{{ l('travel') }}</label>
                <template v-if="responseForm.event.requires_camp_details"><h3 class="font-semibold">{{ l('emergency') }}</h3><PlanningFields :model="responseForm" :fields="[field('contact_name', 'contactName', 'text', { required: responseForm.event.camp_emergency_contact_required, maxlength: 120 }), field('contact_phone', 'phone', 'tel', { required: responseForm.event.camp_emergency_contact_required, maxlength: 80 }), field('contact_relationship', 'relationship', 'text', { maxlength: 80 }), field('camp_dietary_notes', 'dietary', 'textarea', { maxlength: 1000 })]" /></template>
            </fieldset>
            <div class="flex gap-3"><button :disabled="busy" class="rounded bg-buttonPrimary px-4 py-2 text-buttonTextPrimary"><i class="las la-save" /> {{ l('save') }}</button><button type="button" :disabled="busy" @click="responseForm = null">{{ l('cancel') }}</button></div>
        </form>
        <div v-if="calendar.self" class="grid min-w-0 gap-6 lg:grid-cols-2">
            <section class="min-w-0 space-y-3"><h2 class="text-lg font-bold">{{ l('private') }}</h2>
                <article v-for="entry in privateEntries" :key="entry.id" class="flex min-w-0 items-start justify-between gap-3 border-b border-border py-3">
                    <div class="min-w-0 break-words"><h3 class="font-semibold">{{ entry.title }}</h3><p class="text-sm text-secondary">{{ personName(entry) }}</p><p class="text-sm">{{ date(entry.starts_at) }} - {{ date(entry.ends_at) }}</p><p v-if="entry.location" class="text-sm text-secondary">{{ entry.location }}</p></div>
                    <div class="flex shrink-0 gap-3"><button type="button" :disabled="busy || loading" :title="l('edit')" :aria-label="`${l('edit')}: ${entry.title}`" @click="editEntry(entry)"><i class="las la-edit text-xl" /></button><button type="button" :disabled="busy || loading" :title="l('remove')" :aria-label="`${l('remove')}: ${entry.title}`" @click="deleteEntry(entry)"><i class="las la-trash text-xl" /></button></div>
                </article><p v-if="!privateEntries.length" class="text-sm text-secondary">{{ l('empty') }}</p>
            </section>
            <section class="min-w-0 space-y-3"><h2 class="text-lg font-bold">{{ l('events') }}</h2>
                <div v-for="[dateKey, events] in eventDays" :key="dateKey" class="space-y-2"><h3 class="border-b border-border py-2 text-sm font-semibold">{{ day(dateKey) }}</h3>
                    <article v-for="event in events" :key="`${event.id}:${event.person_id}`" class="min-w-0 space-y-2 border-b border-border py-3">
                        <h4 class="break-words font-semibold">{{ event.title }}</h4><p class="text-sm text-secondary">{{ event.person_name }} · {{ l(event.response_status || 'unanswered') }}</p><p class="text-sm">{{ date(event.starts_at) }} - {{ date(event.ends_at) }}</p><p v-if="event.location" class="break-words text-sm text-secondary">{{ event.location }}</p>
                        <button v-if="event.can_respond" type="button" :disabled="busy || loading" class="text-sm text-link" @click="editResponse(event)"><i class="las la-calendar-check" /> {{ l('respond') }}</button><p v-else class="text-sm text-secondary">{{ l('readOnly') }} · {{ event.status }}</p>
                    </article>
                </div><p v-if="!eventDays.length" class="text-sm text-secondary">{{ l('empty') }}</p>
            </section>
        </div>
    </main>
</template>
