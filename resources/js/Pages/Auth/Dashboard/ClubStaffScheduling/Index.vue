<script setup>
import AppLayout from '@/Components/Auth/Layouts/AppLayout.vue'
import ClubWorkspaceNav from '@/Components/Auth/ClubWorkspaceNav.vue'
import { Head } from '@inertiajs/vue3'
import { computed, ref, watch } from 'vue'
import { useI18n } from 'vue-i18n'

defineOptions({ layout: AppLayout })

const props = defineProps({
    clubs: { type: Array, default: () => [] },
    managedClubs: { type: Array, default: () => [] },
})
const { t, locale } = useI18n()
const query = typeof window === 'undefined' ? new URLSearchParams() : new URLSearchParams(window.location.search)
const requestedClubId = Number(query.get('club_id') || 0)
const selectedClubId = ref(props.clubs.some((club) => Number(club.id) === requestedClubId) ? requestedClubId : (props.clubs[0]?.id || null))
const selectedClub = computed(() => props.clubs.find((club) => Number(club.id) === Number(selectedClubId.value)) || null)
const planner = ref({ assignments: [], members: [], audience_people: [], external_members: [], teams: [], events: [], change_requests: [], can_manage: false, current_user_id: null })
const loading = ref(false)
const saving = ref('')
const errorMessage = ref('')
const successMessage = ref('')
const showForm = ref(false)
const editingId = ref(null)
const commentDrafts = ref({})
const externalSelections = ref({})
const alternativeSelections = ref({})
const reopenCreateAfterClubSwitch = ref(false)

const emptyForm = () => ({
    event_id: '',
    role: '',
    note: '',
    location: '',
    instructions: '',
    requirements: '',
    contact_name: '',
    contact_details: '',
    starts_at: '',
    ends_at: '',
    signup_deadline: '',
    capacity: 1,
    visibility_scope: 'club',
    audience_team_ids: [],
    audience_user_ids: [],
    notify_audience: true,
    status: 'open',
})
const form = ref(emptyForm())

const api = (path = '') => `/api/v1/clubs/${selectedClubId.value}/staff-scheduling${path}`
const notify = (message) => {
    successMessage.value = message
    window.setTimeout(() => { if (successMessage.value === message) successMessage.value = '' }, 4000)
}
const showError = (error, fallback) => {
    const errors = error.response?.data?.errors ? Object.values(error.response.data.errors).flat() : []
    errorMessage.value = errors[0] || error.response?.data?.message || fallback
}
const toInputValue = (value) => {
    if (!value) return ''
    const date = new Date(value)
    const local = new Date(date.getTime() - date.getTimezoneOffset() * 60000)
    return local.toISOString().slice(0, 16)
}
const toIso = (value) => value ? new Date(value).toISOString() : null
const formatDate = (value) => new Intl.DateTimeFormat(locale.value, { dateStyle: 'medium' }).format(new Date(value))
const formatDateTime = (value) => new Intl.DateTimeFormat(locale.value, { dateStyle: 'medium', timeStyle: 'short' }).format(new Date(value))
const formatTime = (value) => new Intl.DateTimeFormat(locale.value, { hour: '2-digit', minute: '2-digit' }).format(new Date(value))

const loadPlanner = async () => {
    if (!selectedClub.value || loading.value) return
    loading.value = true
    errorMessage.value = ''
    try {
        const from = new Date(); from.setHours(0, 0, 0, 0)
        const to = new Date(from); to.setFullYear(to.getFullYear() + 1)
        const response = await window.axios.get(api(), { params: { from: from.toISOString(), to: to.toISOString() }, headers: { Accept: 'application/json' } })
        planner.value = response.data.data
    } catch (error) {
        showError(error, t('Die Einsatzplanung konnte nicht geladen werden.'))
    } finally {
        loading.value = false
    }
}

const groups = computed(() => {
    const grouped = new Map()
    for (const item of planner.value.assignments || []) {
        const key = item.shift_key || `${item.role}-${item.starts_at}-${item.ends_at}`
        if (!grouped.has(key)) grouped.set(key, [])
        grouped.get(key).push(item)
    }
    return [...grouped.entries()].map(([key, items]) => {
        const slots = items.filter((item) => item.status !== 'waitlisted')
        const confirmed = slots.filter((item) => item.user_id && ['confirmed', 'planned', 'swap_requested'].includes(item.status))
        const external = slots.filter((item) => item.external_member_id && item.status === 'confirmed')
        const open = slots.filter((item) => !item.user_id && !item.external_member_id && ['open', 'planned'].includes(item.status))
        return {
            key,
            items,
            first: items[0],
            slots,
            confirmed,
            external,
            open,
            waitlisted: items.filter((item) => item.status === 'waitlisted'),
            comments: items[0]?.comments || [],
            occupied: confirmed.length + external.length,
            capacity: slots.length,
            canEdit: items.some((item) => item.can_edit),
        }
    }).sort((a, b) => new Date(a.first.starts_at) - new Date(b.first.starts_at))
})

const sections = computed(() => {
    const result = new Map()
    for (const group of groups.value) {
        const date = group.first.starts_at.slice(0, 10)
        const event = group.first.event_title || t('Ohne Veranstaltung')
        const key = `${date}-${event}`
        if (!result.has(key)) result.set(key, { key, date, event, groups: [] })
        result.get(key).groups.push(group)
    }
    return [...result.values()]
})
const metrics = computed(() => ({
    shifts: groups.value.length,
    open: groups.value.filter((group) => group.open.length > 0).length,
    waitlist: groups.value.reduce((sum, group) => sum + group.waitlisted.length, 0),
}))
const pendingRequests = computed(() => (planner.value.change_requests || []).filter((request) => request.status === 'pending' && Number(request.target_user_id) === Number(planner.value.current_user_id)))

const resetForm = () => {
    form.value = emptyForm()
    editingId.value = null
    showForm.value = false
}
const openCreate = () => {
    form.value = emptyForm()
    editingId.value = null
    showForm.value = true
    window.scrollTo({ top: 0, behavior: 'smooth' })
}
const selectCreateClub = (clubId) => {
    const id = Number(clubId)
    if (!id || id === Number(selectedClubId.value)) return
    reopenCreateAfterClubSwitch.value = true
    selectedClubId.value = id
}
const editGroup = (group) => {
    const item = group.first
    editingId.value = item.id
    form.value = {
        ...emptyForm(),
        event_id: item.event_id || '',
        role: item.role || '',
        note: item.note || '',
        location: item.location || '',
        instructions: item.instructions || '',
        requirements: item.requirements || '',
        contact_name: item.contact_name || '',
        contact_details: item.contact_details || '',
        starts_at: toInputValue(item.starts_at),
        ends_at: toInputValue(item.ends_at),
        signup_deadline: toInputValue(item.signup_deadline),
        capacity: group.capacity,
        visibility_scope: item.visibility_scope || 'club',
        audience_team_ids: [...(item.audience_team_ids || [])],
        audience_user_ids: [...(item.audience_user_ids || [])],
        notify_audience: item.notify_audience !== false,
        status: 'open',
    }
    showForm.value = true
    window.scrollTo({ top: 0, behavior: 'smooth' })
}
const toggleId = (field, id) => {
    const values = form.value[field]
    form.value[field] = values.includes(id) ? values.filter((value) => value !== id) : [...values, id]
}
const saveShift = async () => {
    if (saving.value) return
    saving.value = 'shift'; errorMessage.value = ''
    try {
        const payload = {
            ...form.value,
            event_id: form.value.event_id || null,
            starts_at: toIso(form.value.starts_at),
            ends_at: toIso(form.value.ends_at),
            signup_deadline: toIso(form.value.signup_deadline),
            capacity: Number(form.value.capacity),
            audience_team_ids: form.value.visibility_scope === 'teams' ? form.value.audience_team_ids : [],
            audience_user_ids: form.value.visibility_scope === 'people' ? form.value.audience_user_ids : [],
        }
        if (editingId.value) await window.axios.put(api(`/assignments/${editingId.value}`), payload)
        else await window.axios.post(api('/assignments'), payload)
        notify(editingId.value ? t('Einsatz wurde aktualisiert.') : t('Einsatz wurde angelegt.'))
        resetForm(); await loadPlanner()
    } catch (error) { showError(error, t('Der Einsatz konnte nicht gespeichert werden.')) }
    finally { saving.value = '' }
}
const deleteGroup = async (group) => {
    if (!window.confirm(t('Diesen Einsatz einschließlich Warteliste und Kommentaren löschen?'))) return
    saving.value = `delete-${group.first.id}`; errorMessage.value = ''
    try {
        await window.axios.delete(api(`/assignments/${group.first.id}`))
        notify(t('Einsatz wurde gelöscht.')); await loadPlanner()
    } catch (error) { showError(error, t('Der Einsatz konnte nicht gelöscht werden.')) }
    finally { saving.value = '' }
}
const joinGroup = async (group) => {
    const target = group.open[0] || group.slots[0]
    if (!target) return
    saving.value = `join-${target.id}`; errorMessage.value = ''
    try { await window.axios.post(api(`/assignments/${target.id}/signup`)); notify(t('Deine Anmeldung wurde gespeichert.')); await loadPlanner() }
    catch (error) { showError(error, t('Die Anmeldung konnte nicht gespeichert werden.')) }
    finally { saving.value = '' }
}
const leaveAssignment = async (assignment) => {
    saving.value = `leave-${assignment.id}`; errorMessage.value = ''
    try { await window.axios.post(api(`/assignments/${assignment.id}/release`)); notify(t('Du wurdest vom Einsatz abgemeldet.')); await loadPlanner() }
    catch (error) { showError(error, t('Die Abmeldung konnte nicht gespeichert werden.')) }
    finally { saving.value = '' }
}
const addComment = async (group) => {
    const message = String(commentDrafts.value[group.key] || '').trim()
    if (!message) return
    saving.value = `comment-${group.key}`; errorMessage.value = ''
    try { await window.axios.post(api(`/assignments/${group.first.id}/comments`), { message }); commentDrafts.value[group.key] = ''; await loadPlanner() }
    catch (error) { showError(error, t('Der Kommentar konnte nicht gesendet werden.')) }
    finally { saving.value = '' }
}
const assignExternal = async (group) => {
    const id = Number(externalSelections.value[group.key] || 0)
    if (!id || !group.open[0]) return
    saving.value = `external-${group.key}`; errorMessage.value = ''
    try { await window.axios.post(api(`/assignments/${group.open[0].id}/external-member`), { external_member_id: id }); externalSelections.value[group.key] = ''; notify(t('Externes Mitglied wurde eingetragen.')); await loadPlanner() }
    catch (error) { showError(error, t('Das externe Mitglied konnte nicht eingetragen werden.')) }
    finally { saving.value = '' }
}
const alternativeGroups = (sourceGroup) => groups.value.filter((candidate) => candidate.key !== sourceGroup.key && candidate.open.length && candidate.first.starts_at === sourceGroup.first.starts_at && candidate.first.ends_at === sourceGroup.first.ends_at)
const offerAlternative = async (sourceGroup, waitlisted) => {
    const destination = groups.value.find((group) => group.key === alternativeSelections.value[waitlisted.id])
    if (!destination?.open[0]) return
    saving.value = `alternative-${waitlisted.id}`; errorMessage.value = ''
    try {
        await window.axios.post(api(`/assignments/${destination.open[0].id}/alternative`), { target_user_id: waitlisted.user_id, source_assignment_id: waitlisted.id })
        alternativeSelections.value[waitlisted.id] = ''; notify(t('Alternativangebot wurde gesendet.')); await loadPlanner()
    } catch (error) { showError(error, t('Das Alternativangebot konnte nicht gesendet werden.')) }
    finally { saving.value = '' }
}
const respond = async (request, decision) => {
    saving.value = `request-${request.id}`; errorMessage.value = ''
    try { await window.axios.post(api(`/change-requests/${request.id}/respond`), { decision }); notify(decision === 'accept' ? t('Angebot angenommen.') : t('Angebot abgelehnt.')); await loadPlanner() }
    catch (error) { showError(error, t('Die Antwort konnte nicht gespeichert werden.')) }
    finally { saving.value = '' }
}
const ownAssignment = (group) => group.items.find((item) => Number(item.user_id) === Number(planner.value.current_user_id) && ['confirmed', 'planned', 'waitlisted', 'swap_requested'].includes(item.status))

watch(selectedClubId, async () => {
    planner.value = { assignments: [], members: [], audience_people: [], external_members: [], teams: [], events: [], change_requests: [], can_manage: false, current_user_id: null }
    resetForm()
    await loadPlanner()
    if (reopenCreateAfterClubSwitch.value && planner.value.can_manage) {
        reopenCreateAfterClubSwitch.value = false
        openCreate()
    }
}, { immediate: true })
</script>

<template>
    <Head :title="t('Helfer & Schichten')" />
    <div class="space-y-6">
        <ClubWorkspaceNav active="shifts" :description="t('Einsätze planen, Helfer koordinieren und offene Fragen nachvollziehbar klären.')" />

        <section class="surface-card p-5">
            <div class="flex flex-col gap-4 lg:flex-row lg:items-end lg:justify-between">
                <div>
                    <p class="text-xs font-semibold uppercase tracking-wide text-buttonPrimary">{{ t('Vereinsbetrieb') }}</p>
                    <h1 class="mt-1 text-2xl font-bold text-primary">{{ t('Helfer & Schichten') }}</h1>
                    <p class="mt-1 max-w-3xl text-sm leading-6 text-secondary">{{ t('Plane Einsätze nach Veranstaltung und Datum, informiere passende Gruppen und behalte Besetzung sowie Wartelisten im Blick.') }}</p>
                </div>
                <div class="flex w-full flex-col gap-2 sm:flex-row lg:w-auto">
                    <label class="min-w-64 text-xs font-semibold uppercase text-secondary">{{ t('Verein') }}
                        <select v-model="selectedClubId" class="mt-1 w-full rounded-lg border border-border bg-inputBg px-3 py-2 text-sm font-semibold text-primary"><option v-for="club in clubs" :key="club.id" :value="club.id">{{ club.name }}</option></select>
                    </label>
                    <button v-if="planner.can_manage" type="button" class="mt-auto inline-flex min-h-11 items-center justify-center gap-2 rounded-lg bg-buttonPrimary px-4 text-sm font-bold text-buttonTextPrimary" @click="openCreate"><i class="las la-plus"></i>{{ t('Einsatz anlegen') }}</button>
                </div>
            </div>
            <div class="mt-5 grid gap-px overflow-hidden rounded-lg border border-border bg-border sm:grid-cols-3">
                <div class="bg-card p-4"><p class="text-xs font-semibold uppercase text-secondary">{{ t('Einsätze') }}</p><p class="mt-1 text-2xl font-bold text-primary">{{ metrics.shifts }}</p></div>
                <div class="bg-card p-4"><p class="text-xs font-semibold uppercase text-secondary">{{ t('Noch nicht voll') }}</p><p class="mt-1 text-2xl font-bold text-primary">{{ metrics.open }}</p></div>
                <div class="bg-card p-4"><p class="text-xs font-semibold uppercase text-secondary">{{ t('Warteliste') }}</p><p class="mt-1 text-2xl font-bold text-primary">{{ metrics.waitlist }}</p></div>
            </div>
        </section>

        <p v-if="errorMessage" class="rounded-lg border border-error/30 bg-error/10 px-4 py-3 text-sm font-semibold text-error" role="alert">{{ errorMessage }}</p>
        <p v-if="successMessage" class="rounded-lg border border-success/30 bg-success/10 px-4 py-3 text-sm font-semibold text-success" role="status">{{ successMessage }}</p>

        <form v-if="showForm && planner.can_manage" class="surface-card p-5" @submit.prevent="saveShift">
            <div class="flex items-start justify-between gap-4"><div><h2 class="text-xl font-bold text-primary">{{ editingId ? t('Einsatz bearbeiten') : t('Neuen Einsatz anlegen') }}</h2><p class="mt-1 text-sm text-secondary">{{ t('Pflichtangaben zuerst, Organisation und Zielgruppe darunter.') }}</p></div><button type="button" class="text-sm font-bold text-secondary" @click="resetForm">{{ t('Schließen') }}</button></div>
            <div class="mt-5 grid gap-4 lg:grid-cols-2">
                <label class="text-xs font-semibold uppercase text-secondary">{{ t('Name des Einsatzes') }}<input v-model="form.role" required maxlength="80" class="mt-1 w-full rounded-lg border-border bg-inputBg text-primary" placeholder="Sommerfest Aufbau"></label>
                <label class="text-xs font-semibold uppercase text-secondary">{{ t('Veranstaltung (optional)') }}<select v-model="form.event_id" class="mt-1 w-full rounded-lg border-border bg-inputBg text-primary"><option value="">{{ t('Ohne Veranstaltung') }}</option><option v-for="event in planner.events" :key="event.id" :value="event.id">{{ event.title }}</option></select></label>
                <label class="text-xs font-semibold uppercase text-secondary">{{ t('Beginn') }}<input v-model="form.starts_at" type="datetime-local" required class="mt-1 w-full rounded-lg border-border bg-inputBg text-primary"></label>
                <label class="text-xs font-semibold uppercase text-secondary">{{ t('Ende') }}<input v-model="form.ends_at" type="datetime-local" required class="mt-1 w-full rounded-lg border-border bg-inputBg text-primary"></label>
                <label class="text-xs font-semibold uppercase text-secondary">{{ t('Kapazität') }}<input v-model.number="form.capacity" type="number" min="1" max="100" required class="mt-1 w-full rounded-lg border-border bg-inputBg text-primary"></label>
                <label class="text-xs font-semibold uppercase text-secondary">{{ t('Anmeldeschluss (optional)') }}<input v-model="form.signup_deadline" type="datetime-local" class="mt-1 w-full rounded-lg border-border bg-inputBg text-primary"></label>
                <label class="text-xs font-semibold uppercase text-secondary lg:col-span-2">{{ t('Beschreibung (optional)') }}<textarea v-model="form.note" rows="3" maxlength="2000" class="mt-1 w-full rounded-lg border-border bg-inputBg text-primary"></textarea></label>
            </div>
            <div class="mt-6 border-t border-border pt-5"><h3 class="font-bold text-primary">{{ t('Organisation') }}</h3><div class="mt-3 grid gap-4 lg:grid-cols-2">
                <label class="text-xs font-semibold uppercase text-secondary">{{ t('Ort / Treffpunkt') }}<input v-model="form.location" maxlength="255" class="mt-1 w-full rounded-lg border-border bg-inputBg text-primary"></label>
                <label class="text-xs font-semibold uppercase text-secondary">{{ t('Kontaktperson') }}<input v-model="form.contact_name" maxlength="255" class="mt-1 w-full rounded-lg border-border bg-inputBg text-primary"></label>
                <label class="text-xs font-semibold uppercase text-secondary">{{ t('Kontaktdaten') }}<input v-model="form.contact_details" maxlength="255" class="mt-1 w-full rounded-lg border-border bg-inputBg text-primary"></label>
                <label class="text-xs font-semibold uppercase text-secondary">{{ t('Was ist mitzubringen / zu beachten?') }}<textarea v-model="form.requirements" rows="2" maxlength="4000" class="mt-1 w-full rounded-lg border-border bg-inputBg text-primary"></textarea></label>
                <label class="text-xs font-semibold uppercase text-secondary lg:col-span-2">{{ t('Aufgaben und Ablauf') }}<textarea v-model="form.instructions" rows="3" maxlength="4000" class="mt-1 w-full rounded-lg border-border bg-inputBg text-primary"></textarea></label>
            </div></div>
            <div class="mt-6 border-t border-border pt-5"><h3 class="font-bold text-primary">{{ t('Sichtbarkeit und Information') }}</h3><div class="mt-3 flex flex-wrap gap-2"><button v-for="scope in [{ id: 'club', label: t('Ganzer Verein') }, { id: 'teams', label: t('Teams') }, { id: 'people', label: t('Bestimmte Personen') }]" :key="scope.id" type="button" class="rounded-lg border px-3 py-2 text-sm font-bold" :class="form.visibility_scope === scope.id ? 'border-buttonPrimary bg-buttonPrimary text-buttonTextPrimary' : 'border-border text-secondary'" @click="form.visibility_scope = scope.id">{{ scope.label }}</button></div>
                <div v-if="form.visibility_scope === 'club'" class="mt-4 grid gap-2 sm:grid-cols-2 lg:grid-cols-3">
                    <label v-for="club in managedClubs" :key="club.id" class="flex cursor-pointer items-start gap-3 rounded-lg border p-3 text-sm" :class="Number(selectedClubId) === Number(club.id) ? 'border-buttonPrimary bg-buttonPrimary/10 text-primary' : 'border-border text-primary'">
                        <input type="radio" name="shift-club" class="mt-1" :checked="Number(selectedClubId) === Number(club.id)" @change="selectCreateClub(club.id)">
                        <span><strong class="block">{{ club.name }}</strong><span v-if="Number(selectedClubId) === Number(club.id)" class="text-xs text-secondary">{{ t('Aktuell geöffneter Verein') }}</span></span>
                    </label>
                </div>
                <div v-if="form.visibility_scope === 'teams'" class="mt-4 grid gap-2 sm:grid-cols-2 lg:grid-cols-3"><label v-for="team in planner.teams" :key="team.id" class="flex items-center gap-2 rounded-lg border border-border p-3 text-sm text-primary"><input type="checkbox" :checked="form.audience_team_ids.includes(team.id)" @change="toggleId('audience_team_ids', team.id)">{{ team.name }}</label></div>
                <div v-if="form.visibility_scope === 'people'" class="mt-4 grid max-h-64 gap-2 overflow-y-auto sm:grid-cols-2 lg:grid-cols-3"><label v-for="member in planner.audience_people" :key="member.id" class="flex items-start gap-2 rounded-lg border border-border p-3 text-sm" :class="member.selectable === false ? 'text-secondary opacity-70' : 'text-primary'"><input type="checkbox" class="mt-1" :disabled="member.selectable === false" :checked="form.audience_user_ids.includes(member.user_id)" @change="toggleId('audience_user_ids', member.user_id)"><span>{{ member.name }}<span class="block text-xs text-secondary">{{ member.subtitle }}</span></span></label></div>
                <label class="mt-4 flex items-start gap-2 text-sm text-primary"><input v-model="form.notify_audience" type="checkbox" class="mt-1"><span>{{ t('Ausgewählte Personen jetzt benachrichtigen') }}<span class="block text-xs text-secondary">{{ t('Sie erhalten eine Benachrichtigung mit direktem Link zu dieser Einsatzplanung.') }}</span></span></label>
            </div>
            <div class="mt-6 flex justify-end gap-2"><button type="button" class="rounded-lg border border-border px-4 py-2 text-sm font-bold text-primary" @click="resetForm">{{ t('Abbrechen') }}</button><button class="rounded-lg bg-buttonPrimary px-5 py-2 text-sm font-bold text-buttonTextPrimary disabled:opacity-60" :disabled="Boolean(saving)">{{ saving ? t('Wird gespeichert …') : t('Einsatz speichern') }}</button></div>
        </form>

        <section v-if="pendingRequests.length" class="surface-card border border-buttonPrimary/40 p-5"><h2 class="text-lg font-bold text-primary">{{ t('Offene Alternativangebote') }}</h2><div class="mt-3 divide-y divide-border"><article v-for="request in pendingRequests" :key="request.id" class="py-4 first:pt-0 last:pb-0"><p class="font-bold text-primary">{{ request.assignment_role }}</p><p class="mt-1 text-sm text-secondary">{{ formatDateTime(request.assignment_starts_at) }}<template v-if="request.note"> · {{ request.note }}</template></p><div class="mt-3 flex gap-2"><button type="button" class="rounded-lg bg-buttonPrimary px-4 py-2 text-sm font-bold text-buttonTextPrimary" :disabled="Boolean(saving)" @click="respond(request, 'accept')">{{ t('Annehmen') }}</button><button type="button" class="rounded-lg border border-border px-4 py-2 text-sm font-bold text-primary" :disabled="Boolean(saving)" @click="respond(request, 'decline')">{{ t('Ablehnen') }}</button></div></article></div></section>

        <p v-if="loading" class="surface-card p-8 text-center text-sm text-secondary" role="status">{{ t('Einsatzplanung wird geladen …') }}</p>
        <section v-else-if="!sections.length" class="surface-card p-10 text-center"><i class="las la-hands-helping text-4xl text-buttonPrimary"></i><h2 class="mt-3 text-lg font-bold text-primary">{{ t('Noch keine kommenden Einsätze') }}</h2><p class="mt-1 text-sm text-secondary">{{ planner.can_manage ? t('Lege den ersten Einsatz an und informiere den passenden Personenkreis.') : t('Für dich sind aktuell keine Einsätze sichtbar.') }}</p></section>

        <section v-for="section in sections" v-else :key="section.key" class="space-y-3">
            <div class="flex flex-wrap items-baseline justify-between gap-2 border-b border-border pb-2"><h2 class="text-lg font-bold text-primary"><i class="las la-calendar-day mr-2 text-buttonPrimary"></i>{{ section.event }}</h2><p class="text-sm font-semibold text-secondary">{{ formatDate(`${section.date}T00:00:00`) }}</p></div>
            <article v-for="group in section.groups" :key="group.key" class="surface-card p-5">
                <div class="flex flex-col gap-4 lg:flex-row lg:items-start lg:justify-between"><div class="min-w-0"><div class="flex flex-wrap items-center gap-2"><h3 class="text-lg font-bold text-primary">{{ group.first.role }}</h3><span class="rounded-full border border-border px-2.5 py-1 text-xs font-bold text-secondary">{{ group.occupied }}/{{ group.capacity }} {{ t('besetzt') }}</span><span v-if="group.waitlisted.length" class="rounded-full border border-warning/40 bg-warning/10 px-2.5 py-1 text-xs font-bold text-warning">{{ group.waitlisted.length }} {{ t('wartend') }}</span></div><p class="mt-1 text-sm text-secondary">{{ formatTime(group.first.starts_at) }}–{{ formatTime(group.first.ends_at) }}<template v-if="group.first.location"> · {{ group.first.location }}</template></p><p v-if="group.first.note" class="mt-3 whitespace-pre-wrap text-sm leading-6 text-primary">{{ group.first.note }}</p></div><div v-if="group.canEdit" class="flex shrink-0 gap-2"><button type="button" class="rounded-lg border border-border px-3 py-2 text-sm font-bold text-primary" @click="editGroup(group)"><i class="las la-pen mr-1"></i>{{ t('Bearbeiten') }}</button><button type="button" class="rounded-lg border border-error/40 px-3 py-2 text-sm font-bold text-error" :disabled="Boolean(saving)" @click="deleteGroup(group)"><i class="las la-trash mr-1"></i>{{ t('Löschen') }}</button></div></div>
                <div v-if="group.first.instructions || group.first.requirements || group.first.contact_name" class="mt-4 grid gap-3 border-y border-border py-4 md:grid-cols-3"><div v-if="group.first.instructions"><p class="text-xs font-bold uppercase text-secondary">{{ t('Aufgaben') }}</p><p class="mt-1 whitespace-pre-wrap text-sm text-primary">{{ group.first.instructions }}</p></div><div v-if="group.first.requirements"><p class="text-xs font-bold uppercase text-secondary">{{ t('Zu beachten') }}</p><p class="mt-1 whitespace-pre-wrap text-sm text-primary">{{ group.first.requirements }}</p></div><div v-if="group.first.contact_name"><p class="text-xs font-bold uppercase text-secondary">{{ t('Kontakt') }}</p><p class="mt-1 text-sm text-primary">{{ group.first.contact_name }}<template v-if="group.first.contact_details"> · {{ group.first.contact_details }}</template></p></div></div>
                <div class="mt-4 grid gap-5 xl:grid-cols-[1fr_0.9fr]"><div><h4 class="text-sm font-bold text-primary">{{ t('Besetzung') }}</h4><div class="mt-2 flex flex-wrap gap-2"><span v-for="assignment in group.confirmed" :key="assignment.id" class="rounded-lg bg-inputBg px-3 py-2 text-sm text-primary"><i class="las la-user mr-1"></i>{{ assignment.user_name }}</span><span v-for="assignment in group.external" :key="assignment.id" class="rounded-lg bg-inputBg px-3 py-2 text-sm text-primary"><i class="las la-user-tag mr-1"></i>{{ assignment.external_member_name }} · {{ t('extern') }}</span><span v-if="!group.occupied" class="text-sm text-secondary">{{ t('Noch niemand eingetragen.') }}</span></div>
                    <div class="mt-3 flex flex-wrap gap-2"><template v-if="ownAssignment(group)"><button type="button" class="rounded-lg border border-border px-3 py-2 text-sm font-bold text-primary" :disabled="Boolean(saving)" @click="leaveAssignment(ownAssignment(group))">{{ ownAssignment(group).status === 'waitlisted' ? t('Warteliste verlassen') : t('Abmelden') }}</button></template><button v-else type="button" class="rounded-lg bg-buttonPrimary px-4 py-2 text-sm font-bold text-buttonTextPrimary" :disabled="Boolean(saving)" @click="joinGroup(group)">{{ group.open.length ? t('Anmelden') : t('Auf Warteliste') }}</button></div>
                    <div v-if="group.canEdit && group.open.length && planner.external_members.length" class="mt-4 flex flex-col gap-2 sm:flex-row"><select v-model="externalSelections[group.key]" class="min-w-0 flex-1 rounded-lg border-border bg-inputBg text-sm text-primary"><option value="">{{ t('Externes Mitglied auswählen') }}</option><option v-for="member in planner.external_members" :key="member.id" :value="member.id">{{ member.name }}</option></select><button type="button" class="rounded-lg border border-border px-3 py-2 text-sm font-bold text-primary" :disabled="!externalSelections[group.key] || Boolean(saving)" @click="assignExternal(group)">{{ t('Manuell eintragen') }}</button></div>
                    <div v-if="group.canEdit && group.waitlisted.length" class="mt-5 border-t border-border pt-4"><h4 class="text-sm font-bold text-primary">{{ t('Warteliste und Alternativen') }}</h4><div v-for="person in group.waitlisted" :key="person.id" class="mt-3 grid gap-2 rounded-lg bg-inputBg p-3 md:grid-cols-[1fr_minmax(14rem,0.9fr)_auto] md:items-center"><span class="text-sm font-semibold text-primary">{{ person.user_name }}</span><select v-model="alternativeSelections[person.id]" class="rounded-lg border-border bg-card text-sm text-primary"><option value="">{{ t('Alternative wählen') }}</option><option v-for="candidate in alternativeGroups(group)" :key="candidate.key" :value="candidate.key">{{ candidate.first.role }} · {{ candidate.open.length }} {{ t('frei') }}</option></select><button type="button" class="rounded-lg border border-buttonPrimary/50 px-3 py-2 text-sm font-bold text-buttonPrimary" :disabled="!alternativeSelections[person.id] || Boolean(saving)" @click="offerAlternative(group, person)">{{ t('Vorschlagen') }}</button></div></div>
                </div><div><h4 class="text-sm font-bold text-primary">{{ t('Fragen & Antworten') }}</h4><div v-if="group.comments.length" class="mt-2 max-h-56 space-y-2 overflow-y-auto"><div v-for="comment in group.comments" :key="comment.id" class="rounded-lg bg-inputBg p-3"><div class="flex justify-between gap-3 text-xs text-secondary"><strong class="text-primary">{{ comment.user?.name }}</strong><span>{{ formatDateTime(comment.created_at) }}</span></div><p class="mt-1 whitespace-pre-wrap text-sm text-primary">{{ comment.message }}</p></div></div><p v-else class="mt-2 text-sm text-secondary">{{ t('Noch keine Fragen oder Antworten.') }}</p><form class="mt-3 flex flex-col gap-2 sm:flex-row" @submit.prevent="addComment(group)"><textarea v-model="commentDrafts[group.key]" rows="2" maxlength="2000" required class="min-w-0 flex-1 rounded-lg border-border bg-inputBg text-sm text-primary" :placeholder="t('Frage stellen oder Information ergänzen …')"></textarea><button class="rounded-lg border border-border px-4 py-2 text-sm font-bold text-primary" :disabled="Boolean(saving)">{{ t('Senden') }}</button></form></div></div>
            </article>
        </section>
    </div>
</template>
