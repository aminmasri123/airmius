<script setup>
import { computed, onMounted, ref, watch } from 'vue'
import AppLoadingState from '@/Components/UI/AppLoadingState.vue'
import { confirmDialog } from '@/services/dialogService'

const props = defineProps({ clubId: { type: [Number, String], required: true } })
const baseUrl = computed(() => `/api/v1/clubs/${encodeURIComponent(props.clubId)}/governance/meetings`)
const meetings = ref([])
const meeting = ref(null)
const governance = ref({ bodies: [], member_options: [] })
const canCreate = ref(false)
const canManage = computed(() => meeting.value?.can_manage === true)
const loading = ref(false)
const busy = ref(false)
const error = ref('')
const notice = ref('')
const query = ref('')
const status = ref('')
const form = ref(null)
const decisionForm = ref(null)
const labels = {
    board: 'Vorstandssitzung', committee: 'Ausschusssitzung', general_assembly: 'Mitgliederversammlung',
    draft: 'Entwurf', invited: 'Eingeladen', held: 'Durchgeführt', cancelled: 'Abgesagt',
    body: 'Gremium', club_members: 'Vereinsmitglieder', custom: 'Ausgewählte Personen',
    pending: 'Ausstehend', sent: 'Versendet', delivered: 'Zugestellt', failed: 'Fehlgeschlagen', bounced: 'Unzustellbar',
    motion: 'Antrag', election: 'Wahl', open: 'Offen', named: 'Namentlich', secret: 'Geheim', closed: 'Abgeschlossen',
    simple: 'Einfache Mehrheit', absolute: 'Absolute Mehrheit', yes: 'Ja', no: 'Nein', abstain: 'Enthaltung',
    accepted: 'Angenommen', rejected: 'Abgelehnt', no_quorum: 'Quorum nicht erreicht',
}
const label = (value) => labels[value] || value || 'Nicht angegeben'
const date = (value) => value ? new Date(value).toLocaleString('de-DE') : 'Termin offen'
const creator = (version) => typeof version.created_by === 'string' ? version.created_by : version.created_by?.name || version.created_by_name
const personKey = (person) => `${person.kind}:${person.id}`
const eligibilityDate = computed(() => form.value?.eligibility_as_of || form.value?.scheduled_at?.slice(0, 10) || localDate(new Date().toISOString()).slice(0, 10))
const activeBodyKeys = computed(() => {
    const body = governance.value.bodies?.find((item) => item.id === form.value?.club_governance_body_id)
    return new Set((body?.assignments || []).filter((item) =>
        (!item.starts_on || item.starts_on <= eligibilityDate.value)
        && (!item.ends_on || item.ends_on >= eligibilityDate.value)).map((item) =>
        item.user_id ? `user:${item.user_id}` : `external:${item.club_external_member_id}`))
})
const recipientOptions = computed(() => (governance.value.member_options || []).filter((person) =>
    form.value?.participant_scope !== 'body' || activeBodyKeys.value.has(personKey(person))))
const defaultVoting = (person) => activeBodyKeys.value.has(personKey(person))
    || (person.voting_eligible ?? (person.kind === 'user' && (person.membership_status || 'active') === 'active'))
const votingRight = (person) => form.value.voting_rights[personKey(person)] ?? defaultVoting(person)
function selectBodyMembers() {
    if (!form.value || form.value.id) return
    form.value.recipient_keys = (governance.value.member_options || []).filter((person) => activeBodyKeys.value.has(personKey(person))).map(personKey)
    form.value.voting_rights = {}
}
const visibleMeetings = computed(() => meetings.value.filter((item) =>
    (!status.value || item.status === status.value)
    && `${item.title} ${item.location_name || ''}`.toLocaleLowerCase('de-DE').includes(query.value.toLocaleLowerCase('de-DE'))))
const canVote = (decision) => !!meeting.value?.current_recipient_id
    && meeting.value.status !== 'cancelled'
    && (Object.hasOwn(decision, 'viewer_can_vote') ? decision.viewer_can_vote === true : meeting.value.viewer_voting_eligible === true)
    && decision.viewer_has_voted !== true
    && decision.status === 'open' && !decision.correction_locked_at
const apiError = (cause) => Object.values(cause?.response?.data?.errors || {}).flat()[0]
    || cause?.response?.data?.message || 'Die Daten konnten nicht gespeichert oder geladen werden.'
const request = async (method, url, data) => (await window.axios({ method, url, data, headers: { Accept: 'application/json' } })).data
let generation = 0

async function loadList() {
    const token = ++generation
    loading.value = true
    error.value = ''
    meetings.value = []
    canCreate.value = false
    try {
        const response = await request('get', baseUrl.value)
        if (token !== generation) return
        meetings.value = response.data || []
        canCreate.value = response.meta?.can_manage === true
    } catch (cause) {
        if (token === generation) { error.value = apiError(cause); canCreate.value = false }
    } finally {
        if (token === generation) loading.value = false
    }
}
async function openMeeting(id) {
    if (busy.value) return
    const token = ++generation
    loading.value = true
    meeting.value = null
    form.value = null
    decisionForm.value = null
    error.value = ''
    notice.value = ''
    try {
        const response = await request('get', `${baseUrl.value}/${id}`)
        if (token === generation) meeting.value = response.data
    } catch (cause) {
        if (token === generation) { error.value = apiError(cause); meetings.value = []; canCreate.value = false }
    } finally {
        if (token === generation) loading.value = false
    }
}
async function refresh() {
    if (busy.value) return
    if (meeting.value) await openMeeting(meeting.value.id)
    else await loadList()
}
async function back() {
    if (busy.value) return
    meeting.value = null
    form.value = null
    decisionForm.value = null
    notice.value = ''
    await loadList()
}
function localDate(value) {
    if (!value) return ''
    const time = new Date(value)
    return new Date(time.getTime() - time.getTimezoneOffset() * 60000).toISOString().slice(0, 16)
}
async function editMeeting(create = false) {
    if (busy.value || (create ? !canCreate.value : !canManage.value)) return
    busy.value = true
    error.value = ''
    try {
        governance.value = (await request('get', `/api/v1/clubs/${encodeURIComponent(props.clubId)}/governance`)).data
        const item = create ? {} : meeting.value
        const defaultBody = governance.value.bodies?.find((body) => body.type === 'board') || governance.value.bodies?.[0]
        form.value = {
            id: item.id || null, type: item.type || (defaultBody ? 'board' : 'general_assembly'), title: item.title || '',
            status: item.status || 'draft', scheduled_at: localDate(item.scheduled_at),
            location_name: item.location_name || '', participant_scope: item.participant_scope || (defaultBody ? 'body' : 'club_members'),
            club_governance_body_id: item.governance_body?.id || (create ? defaultBody?.id : null) || null,
            club_year_period_id: item.year_period?.id || null,
            eligibility_as_of: item.eligibility_as_of || '', motions_due_on: item.motions_due_on || '',
            invitation_sent_at: item.invitation_sent_at || null,
            agenda_items: (item.agenda_items || []).map((entry) => ({ ...entry })), notes: item.notes || '',
            materials: item.materials || [], decision_templates: item.decision_templates || [], recipient_keys: [], voting_rights: {},
        }
    } catch (cause) { error.value = apiError(cause) }
    finally { busy.value = false }
}
async function mutate(action, success) {
    if (busy.value) return false
    busy.value = true
    error.value = ''
    notice.value = ''
    try {
        await action()
        notice.value = success
        return true
    } catch (cause) {
        error.value = apiError(cause)
        return false
    } finally { busy.value = false }
}
async function saveMeeting() {
    if (!form.value || (form.value.id ? !canManage.value : !canCreate.value)) return
    const { id, recipient_keys, voting_rights, ...data } = form.value
    if (!id && !recipient_keys.length) { error.value = 'Bitte mindestens eine Person einladen.'; return }
    if (data.participant_scope === 'body' && !data.club_governance_body_id) { error.value = 'Bitte ein Gremium auswählen.'; return }
    data.title = data.title.trim()
    data.eligibility_as_of ||= eligibilityDate.value
    data.scheduled_at = data.scheduled_at ? new Date(data.scheduled_at).toISOString() : null
    data.motions_due_on ||= null
    if (!id) data.recipients = recipient_keys.map((key) => {
        const [kind, personId] = key.split(':')
        const person = governance.value.member_options.find((entry) => personKey(entry) === key)
        return { [kind === 'user' ? 'user_id' : 'club_external_member_id']: Number(personId), delivery_status: 'pending', attendance_eligible: true, voting_eligible: voting_rights[key] ?? defaultVoting(person) }
    })
    let savedId
    const ok = await mutate(async () => {
        const response = await request(id ? 'put' : 'post', `${baseUrl.value}${id ? `/${id}` : ''}`, data)
        savedId = response.data.id
    }, 'Sitzung gespeichert.')
    if (ok) { await openMeeting(savedId); notice.value = 'Sitzung gespeichert.' }
}
function newDecision() {
    if (meeting.value?.status === 'cancelled') return
    decisionForm.value = { title: '', description: '', type: 'motion', voting_mode: 'open', majority_rule: 'simple', quorum: '', candidates: '' }
}
async function saveDecision() {
    if (!canManage.value || !decisionForm.value || meeting.value.status === 'cancelled') return
    const { candidates, ...data } = decisionForm.value
    data.title = data.title.trim()
    data.quorum = data.quorum === '' ? null : Number(data.quorum)
    data.options = data.type === 'motion' ? ['yes', 'no', 'abstain']
        : [...new Set(candidates.split('\n').map((item) => item.trim()).filter(Boolean)), 'abstain']
    if (data.type === 'election' && (data.options.length < 2 || data.options.length > 30
        || data.options.slice(0, -1).some((value) => value.length > 80 || ['yes', 'no', 'abstain'].includes(value)))) {
        error.value = 'Bitte 1 bis 29 gültige Kandidaturen mit höchstens 80 Zeichen angeben; yes, no und abstain sind reserviert.'; return
    }
    if (await mutate(() => request('post', `${baseUrl.value}/${meeting.value.id}/decisions`, data), 'Beschluss angelegt.')) {
        await openMeeting(meeting.value.id)
        notice.value = 'Beschluss angelegt.'
    }
}
async function vote(decision, choice) {
    if (!canVote(decision) || busy.value) return
    if (!await confirmDialog({ title: 'Stimme abgeben', message: `${decision.title}: ${label(choice)}. Die Stimme kann nicht geändert werden.`, confirmLabel: 'Stimme abgeben' })) return
    if (!canVote(decision)) return
    const id = meeting.value.id
    const ok = await mutate(() => request('post', `${baseUrl.value}/${id}/decisions/${decision.id}/vote`, {
        recipient_id: meeting.value.current_recipient_id, choice,
    }), 'Stimme gespeichert.')
    const voteError = error.value
    await openMeeting(id)
    if (ok) notice.value = 'Stimme gespeichert.'
    else error.value = voteError || error.value
}
async function closeDecision(decision) {
    if (!canManage.value || busy.value || decision.status !== 'open') return
    if (!await confirmDialog({ title: 'Abstimmung abschließen', message: 'Das Ergebnis wird festgeschrieben. Weitere Stimmen sind danach ausgeschlossen.', confirmLabel: 'Abschließen' })) return
    if (await mutate(() => request('post', `${baseUrl.value}/${meeting.value.id}/decisions/${decision.id}/close`), 'Abstimmung abgeschlossen.')) {
        await openMeeting(meeting.value.id)
        notice.value = 'Abstimmung abgeschlossen.'
    }
}
async function delivery(recipient, value) {
    if (!canManage.value) return
    if (await mutate(() => request('patch', `${baseUrl.value}/${meeting.value.id}/recipients/${recipient.id}/delivery`, { delivery_status: value }), 'Zustellstatus gespeichert.')) {
        await openMeeting(meeting.value.id)
        notice.value = 'Zustellstatus gespeichert.'
    }
}
watch(() => props.clubId, () => { meeting.value = null; form.value = null; decisionForm.value = null; meetings.value = []; loadList() })
watch(() => [form.value?.participant_scope, form.value?.club_governance_body_id, eligibilityDate.value], () => {
    if (!form.value || form.value.id) return
    if (form.value.participant_scope === 'body') form.value.recipient_keys = form.value.recipient_keys.filter((key) => activeBodyKeys.value.has(key))
    form.value.voting_rights = {}
})
onMounted(loadList)
</script>

<template>
    <section class="governance-meetings border-t border-border py-5" aria-label="Sitzungen und Beschlüsse">
        <header class="flex flex-wrap items-center justify-between gap-3">
            <h2 class="text-lg font-semibold text-primary">Sitzungen &amp; Beschlüsse</h2>
            <div class="flex flex-wrap gap-2">
                <button v-if="meeting || form" type="button" class="meeting-button" :disabled="busy || loading" @click="back"><i class="las la-arrow-left" aria-hidden="true"></i> Übersicht</button>
                <button type="button" class="meeting-button" title="Aktualisieren" aria-label="Aktualisieren" :disabled="busy || loading || !!form" @click="refresh"><i class="las la-sync" aria-hidden="true"></i></button>
                <button v-if="!meeting && !form && canCreate" type="button" class="meeting-primary" :disabled="busy || loading" @click="editMeeting(true)"><i class="las la-plus" aria-hidden="true"></i> Sitzung anlegen</button>
            </div>
        </header>
        <p v-if="error" role="alert" class="mt-3 text-sm text-error">{{ error }}</p>
        <p v-if="notice" role="status" class="mt-3 text-sm text-primary">{{ notice }}</p>
        <AppLoadingState v-if="loading || busy" label="Daten werden geladen …" inline class="mt-3" />

        <form v-if="form" class="mt-4 space-y-4" @submit.prevent="saveMeeting">
            <fieldset :disabled="busy || loading" class="space-y-4">
                <h3 class="font-semibold text-primary">{{ form.id ? 'Sitzung bearbeiten' : 'Neue Sitzung' }}</h3>
                <label>Titel<input v-model="form.title" required maxlength="180"></label>
                <div class="grid gap-3 sm:grid-cols-2">
                    <label>Sitzungsart<select v-model="form.type"><option v-for="value in ['board', 'committee', 'general_assembly']" :key="value" :value="value">{{ label(value) }}</option></select></label>
                    <label>Status<select v-model="form.status"><option v-for="value in ['draft', 'invited', 'held', 'cancelled']" :key="value" :value="value">{{ label(value) }}</option></select></label>
                    <label>Termin<input v-model="form.scheduled_at" type="datetime-local"></label>
                    <label>Ort<input v-model="form.location_name" maxlength="180"></label>
                    <label>Teilnehmerkreis<select v-model="form.participant_scope" :disabled="!!form.id"><option v-for="value in ['body', 'club_members', 'custom']" :key="value" :value="value">{{ label(value) }}</option></select></label>
                    <label>Gremium<select v-model="form.club_governance_body_id" :disabled="!!form.id" :required="form.participant_scope === 'body'"><option :value="null">Ohne Gremium</option><option v-for="body in governance.bodies" :key="body.id" :value="body.id">{{ body.name }}</option></select></label>
                    <label>Stichtag der Berechtigung<input v-model="form.eligibility_as_of" :disabled="!!form.id" type="date"></label>
                    <label>Antragsfrist<input v-model="form.motions_due_on" type="date"></label>
                </div>
                <h4 class="font-semibold text-primary">Tagesordnung</h4>
                <div v-for="(item, index) in form.agenda_items" :key="index" class="flex items-start gap-2">
                    <div class="min-w-0 flex-1 space-y-2"><label>{{ index + 1 }}. Tagesordnungspunkt<input v-model="item.title" required maxlength="180"></label><label>Beschreibung<textarea v-model="item.description" maxlength="2000" rows="2"></textarea></label></div>
                    <button type="button" class="meeting-button mt-5" title="Tagesordnungspunkt entfernen" aria-label="Tagesordnungspunkt entfernen" @click="form.agenda_items.splice(index, 1)"><i class="las la-trash" aria-hidden="true"></i></button>
                </div>
                <button type="button" class="meeting-button" :disabled="form.agenda_items.length >= 50" @click="form.agenda_items.push({ title: '', description: '' })"><i class="las la-plus" aria-hidden="true"></i> Tagesordnungspunkt</button>
                <label>Protokoll / Notizen<textarea v-model="form.notes" maxlength="5000" rows="6"></textarea></label>
                <fieldset v-if="!form.id" class="space-y-2">
                    <legend class="font-semibold text-primary">Einzuladende Personen</legend>
                    <button v-if="form.club_governance_body_id" type="button" class="meeting-button" @click="selectBodyMembers"><i class="las la-users" aria-hidden="true"></i> Gremiumsmitglieder auswählen</button>
                    <div v-for="person in recipientOptions" :key="personKey(person)" class="flex flex-wrap items-center justify-between gap-2 border-b border-border py-2"><label class="flex items-center gap-2"><input v-model="form.recipient_keys" type="checkbox" :value="personKey(person)">{{ person.name }}{{ person.kind === 'external' ? ' (Extern)' : '' }}</label><label v-if="form.recipient_keys.includes(personKey(person))" class="flex items-center gap-2"><input type="checkbox" :checked="votingRight(person)" @change="form.voting_rights[personKey(person)] = $event.target.checked">Stimmrecht</label></div>
                    <p v-if="!recipientOptions.length" class="text-sm text-secondary">Keine Personen im gewählten Teilnehmerkreis verfügbar.</p>
                </fieldset>
                <p v-else class="text-sm text-secondary">Empfänger und Stimmrechte sind nach dem Anlegen festgeschrieben.</p>
                <div class="flex gap-2"><button class="meeting-primary"><i class="las la-save" aria-hidden="true"></i> Speichern</button><button type="button" class="meeting-button" @click="form = null">Abbrechen</button></div>
            </fieldset>
        </form>

        <div v-else-if="meeting && !loading" class="mt-4 space-y-5">
            <header class="flex flex-wrap items-start justify-between gap-3">
                <div class="min-w-0"><h3 class="break-words font-semibold text-primary">{{ meeting.title }}</h3><p class="text-sm text-secondary">{{ label(meeting.type) }} · {{ label(meeting.status) }}</p><p class="text-sm text-secondary">{{ date(meeting.scheduled_at) }}{{ meeting.location_name ? ` · ${meeting.location_name}` : '' }}</p><p v-if="meeting.governance_body" class="text-sm text-secondary">{{ meeting.governance_body.name }}</p></div>
                <button v-if="canManage" type="button" class="meeting-button" :disabled="busy" @click="editMeeting()"><i class="las la-edit" aria-hidden="true"></i> Bearbeiten</button>
            </header>
            <div><h4 class="font-semibold text-primary">Tagesordnung</h4><ol class="mt-2 space-y-2"><li v-for="(item, index) in meeting.agenda_items" :key="index"><p class="text-sm text-primary">{{ index + 1 }}. {{ item.title }}</p><p v-if="item.description" class="whitespace-pre-line text-sm text-secondary">{{ item.description }}</p></li></ol><p v-if="!meeting.agenda_items?.length" class="text-sm text-secondary">Keine Tagesordnung hinterlegt.</p></div>
            <div><h4 class="font-semibold text-primary">Protokoll / Notizen</h4><p class="mt-2 whitespace-pre-wrap break-words text-sm text-primary">{{ meeting.notes || 'Noch kein Protokoll hinterlegt.' }}</p></div>
            <div>
                <div class="flex flex-wrap items-center justify-between gap-2"><h4 class="font-semibold text-primary">Beschlüsse &amp; Wahlen</h4><button v-if="canManage && !decisionForm && meeting.status !== 'cancelled'" type="button" class="meeting-button" :disabled="busy" @click="newDecision"><i class="las la-plus" aria-hidden="true"></i> Beschluss anlegen</button></div>
                <form v-if="decisionForm" class="mt-3 space-y-3 border-y border-border py-3" @submit.prevent="saveDecision">
                    <fieldset :disabled="busy" class="space-y-3">
                        <label>Titel<input v-model="decisionForm.title" required maxlength="180"></label>
                        <label>Beschreibung<textarea v-model="decisionForm.description" maxlength="5000" rows="2"></textarea></label>
                        <div class="grid gap-3 sm:grid-cols-2">
                            <label>Art<select v-model="decisionForm.type"><option value="motion">Antrag</option><option value="election">Wahl</option></select></label>
                            <label>Abstimmungsart<select v-model="decisionForm.voting_mode"><option v-for="value in ['open', 'named', 'secret']" :key="value" :value="value">{{ label(value) }}</option></select></label>
                            <label>Mehrheit<select v-model="decisionForm.majority_rule"><option value="simple">Einfache Mehrheit</option><option value="absolute">Absolute Mehrheit</option></select></label>
                            <label>Quorum (%)<input v-model="decisionForm.quorum" type="number" min="1" max="100" step="1"></label>
                        </div>
                        <label v-if="decisionForm.type === 'election'">Kandidaturen<textarea v-model="decisionForm.candidates" required rows="4"></textarea></label>
                        <div class="flex gap-2"><button class="meeting-primary">Anlegen</button><button type="button" class="meeting-button" @click="decisionForm = null">Abbrechen</button></div>
                    </fieldset>
                </form>
                <article v-for="decision in meeting.decisions" :key="decision.id" class="mt-3 rounded-lg border border-border p-3">
                    <h5 class="break-words font-semibold text-primary">{{ decision.title }}</h5>
                    <p class="text-xs text-secondary">{{ label(decision.type) }} · {{ label(decision.voting_mode) }} · {{ label(decision.majority_rule) }} · {{ label(decision.status) }} · Version {{ decision.meeting_version }}{{ decision.quorum ? ` · Quorum ${decision.quorum}%` : '' }}</p>
                    <p v-if="decision.description" class="mt-2 whitespace-pre-line text-sm text-primary">{{ decision.description }}</p>
                    <p class="mt-2 text-sm text-secondary">{{ decision.result?.votes_cast ?? 0 }} / {{ decision.eligible_voters }} Stimmen · Quorum {{ decision.result?.quorum_reached ? 'erreicht' : 'nicht erreicht' }}</p>
                    <ul class="mt-2 flex flex-wrap gap-x-4 gap-y-1 text-sm text-primary"><li v-for="option in decision.options" :key="option">{{ label(option) }}: {{ decision.result?.counts?.[option] ?? 0 }}</li></ul>
                    <p v-if="decision.status === 'closed'" class="mt-2 font-semibold text-primary">{{ label(decision.result?.outcome) }}{{ decision.result?.winner ? ` · ${decision.result.winner}` : '' }}</p>
                    <p v-if="decision.viewer_has_voted" class="mt-2 text-sm text-secondary">Ihre Stimme wurde abgegeben.</p>
                    <div v-if="canVote(decision)" class="mt-3 flex flex-wrap gap-2"><button v-for="option in decision.options" :key="option" type="button" class="meeting-button" :disabled="busy" @click="vote(decision, option)">{{ label(option) }}</button></div>
                    <button v-if="canManage && decision.status === 'open' && !decision.correction_locked_at" type="button" class="meeting-button mt-3" :disabled="busy" @click="closeDecision(decision)"><i class="las la-lock" aria-hidden="true"></i> Abstimmung abschließen</button>
                </article>
                <p v-if="!meeting.decisions?.length" class="mt-2 text-sm text-secondary">Noch keine Beschlüsse.</p>
            </div>
            <div><h4 class="font-semibold text-primary">Einladungen</h4><ul class="mt-2 divide-y divide-border"><li v-for="recipient in meeting.recipients" :key="recipient.id" class="flex flex-wrap items-center justify-between gap-2 py-2"><div class="min-w-0 text-sm text-primary"><p class="break-words">{{ recipient.person?.name || 'Mitglied' }}</p><p class="text-xs text-secondary">{{ recipient.voting_eligible ? 'Stimmberechtigt' : 'Nicht stimmberechtigt' }}{{ recipient.response_status ? ` · ${recipient.response_status}` : '' }}</p></div><select v-if="canManage" :value="recipient.delivery_status" :aria-label="`Zustellstatus: ${recipient.person?.name || 'Mitglied'}`" :disabled="busy" @change="delivery(recipient, $event.target.value)"><option v-for="value in ['pending', 'sent', 'delivered', 'failed', 'bounced']" :key="value" :value="value">{{ label(value) }}</option></select><span v-else class="text-sm text-secondary">{{ label(recipient.delivery_status) }}</span></li></ul></div>
            <div><h4 class="font-semibold text-primary">Protokollversionen</h4><details v-for="version in meeting.versions" :key="version.id" class="mt-2 border-t border-border py-2"><summary class="cursor-pointer text-sm text-primary">Version {{ version.version }} · {{ label(version.status) }} · {{ date(version.created_at) }}{{ creator(version) ? ` · ${creator(version)}` : '' }}</summary><ol class="mt-2 text-sm text-secondary"><li v-for="(item, index) in version.agenda_items" :key="index">{{ index + 1 }}. {{ item.title }}{{ item.description ? `: ${item.description}` : '' }}</li></ol><p class="mt-2 whitespace-pre-wrap break-words text-sm text-primary">{{ version.notes || 'Keine Notizen.' }}</p></details><p v-if="!meeting.versions?.length" class="text-sm text-secondary">Keine Versionen verfügbar.</p></div>
        </div>

        <div v-else-if="!loading && !form" class="mt-4">
            <div class="mb-3 flex flex-wrap gap-2"><input v-model="query" type="search" aria-label="Sitzungen suchen" placeholder="Sitzungen suchen" class="min-w-0 flex-1"><select v-model="status" aria-label="Sitzungsstatus"><option value="">Alle Status</option><option v-for="value in ['draft', 'invited', 'held', 'cancelled']" :key="value" :value="value">{{ label(value) }}</option></select></div>
            <ul class="divide-y divide-border"><li v-for="item in visibleMeetings" :key="item.id"><button type="button" class="flex w-full items-center justify-between gap-3 py-3 text-left" :disabled="busy" @click="openMeeting(item.id)"><div class="min-w-0"><p class="break-words font-semibold text-primary">{{ item.title }}</p><p class="text-sm text-secondary">{{ label(item.status) }} · {{ date(item.scheduled_at) }}{{ item.location_name ? ` · ${item.location_name}` : '' }}</p></div><i class="las la-angle-right shrink-0" aria-hidden="true"></i></button></li></ul>
            <p v-if="!visibleMeetings.length" class="text-sm text-secondary">{{ meetings.length ? 'Keine passenden Sitzungen.' : 'Keine Sitzungen verfügbar.' }}</p>
        </div>
    </section>
</template>

<style scoped>
.governance-meetings label { @apply text-sm text-primary; }
.governance-meetings label:not(.flex) { @apply block; }
.governance-meetings input[type=checkbox] { @apply h-4 w-4 min-h-0 shrink-0 rounded border-border bg-inputBg text-air-blue; }
.governance-meetings input[type=checkbox]:checked { @apply bg-air-blue; }
.governance-meetings input:not([type=checkbox]), .governance-meetings textarea, .governance-meetings select { @apply min-w-0 max-w-full rounded-lg border border-border bg-inputBg px-3 py-2 text-sm text-primary; }
.governance-meetings label > input:not([type=checkbox]), .governance-meetings label > textarea, .governance-meetings label > select { @apply mt-1 block w-full; }
.meeting-button { @apply inline-flex items-center justify-center gap-1 rounded-lg border border-border px-3 py-2 text-sm text-primary disabled:opacity-50; }
.meeting-primary { @apply inline-flex items-center justify-center gap-1 rounded-lg bg-buttonPrimary px-3 py-2 text-sm font-semibold text-buttonTextPrimary disabled:opacity-50; }
</style>
