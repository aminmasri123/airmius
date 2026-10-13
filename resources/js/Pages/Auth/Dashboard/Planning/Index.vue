<script setup>
import AppLayout from '@/Components/Auth/Layouts/AppLayout.vue'
import PlanningFields from '@/Components/Planning/PlanningFields.vue'
import GameLineupBoard from '@/Components/Planning/GameLineupBoard.vue'
import { apiDateTime, localDateTime, planningError, seasonDraft } from '@/Components/Planning/planningState.js'
import { Head, Link } from '@inertiajs/vue3'
import { computed, onMounted, ref, watch } from 'vue'
import { useI18n } from 'vue-i18n'

defineOptions({ layout: AppLayout })
const { locale } = useI18n()
const labels = { planning: ['Saison & Wettkampf', 'Season & Competition'], season: ['Saisonplanung', 'Season Planning'], competition: ['Wettkampfplanung', 'Competition Planning'], family: ['Familienkalender', 'Family Calendar'], club: ['Verein', 'Club'], team: ['Mannschaft', 'Team'], period: ['Sportjahr', 'Sport Year'], name: ['Name', 'Name'], goals: ['Ziele', 'Goals'], status: ['Status', 'Status'], start: ['Beginn', 'Start'], end: ['Ende', 'End'], phases: ['Phasen', 'Phases'], goal: ['Phasenziel', 'Phase Goal'], events: ['Termine', 'Events'], save: ['Speichern', 'Save'], cancel: ['Abbrechen', 'Cancel'], add: ['Hinzufügen', 'Add'], edit: ['Bearbeiten', 'Edit'], remove: ['Löschen', 'Delete'], new: ['Neu', 'New'], saved: ['Gespeichert', 'Saved'], empty: ['Keine Einträge', 'No Entries'], loading: ['Wird geladen', 'Loading'], retry: ['Erneut laden', 'Reload'], confirmDelete: ['Eintrag wirklich löschen?', 'Delete this entry?'], classes: ['Wettkampfklassen', 'Classes'], venues: ['Austragungsorte', 'Venues'], roster: ['Teilnehmende', 'Participants'], registration: ['Meldungen', 'Registrations'], member: ['Mitglied', 'Member'], bib: ['Startnummer', 'Bib Number'], city: ['Ort', 'City'], age: ['Altersklasse', 'Age Group'], discipline: ['Disziplin', 'Discipline'], deadline: ['Meldefrist', 'Registration Deadline'], fee: ['Startgeld (Cent)', 'Start Fee (Cents)'], nominate: ['Nominieren', 'Nominate'], eligibility: ['Spielberechtigung prüfen', 'Check Eligibility'], paid: ['Startgeld bezahlt', 'Fee Paid'], confirm: ['Meldung bestätigen', 'Confirm Registration'], tournament: ['Spielplan', 'Fixtures'], groups: ['Gruppenanzahl', 'Group Count'], generate: ['Spielplan erstellen', 'Generate Fixtures'], scoreHome: ['Punkte Heim', 'Home Score'], scoreAway: ['Punkte Gast', 'Away Score'], reason: ['Änderungsgrund', 'Change Reason'], lineups: ['Aufstellungen', 'Lineups'], starter: ['Startaufstellung', 'Starter'], substitute: ['Ersatz', 'Substitute'], position: ['Position', 'Position'], pro: ['Saisonplanung erfordert Pro.', 'Season planning requires Pro.'] }
const l = (key) => labels[key]?.[locale.value === 'de' ? 0 : 1] || key
const readOnlyLabel = computed(() => locale.value === 'de' ? 'Nur Lesen' : 'Read Only')
const sportYearLabel = (id) => club.value?.periods.find((period) => period.id === id)?.name || '-'
const options = (items) => items.map((item) => ({ value: item.id, label: item.name }))
const statusOptions = (values) => values.map((value) => ({ value, label: value }))
const query = new URLSearchParams(typeof window === 'undefined' ? '' : window.location.search)
const tab = ref(query.get('tab') === 'season' ? 'season' : 'competition')
const clubs = ref([]), clubId = ref(null), teamId = ref(null), competitions = ref([]), detail = ref(null), seasons = ref({ plans: [], events: [], can_edit: false })
const busy = ref(false), loading = ref(false), error = ref(''), success = ref(''), editor = ref(null), selectedRoster = ref([]), fee = ref(0), groups = ref(1), lineupName = ref(''), lineupRows = ref({})
const gameDraft = ref(null), selectedGameId = ref(null)
let loadId = 0
const club = computed(() => clubs.value.find((row) => row.id === clubId.value))
const team = computed(() => club.value?.teams.find((row) => row.id === teamId.value))
const canEditCompetition = computed(() => detail.value?.can_edit ?? club.value?.can_edit_competitions ?? false)
const nominationsOpen = computed(() => detail.value && ['planned', 'open'].includes(detail.value.status)
    && (!detail.value.registration_deadline_at || new Date(detail.value.registration_deadline_at) >= new Date()))
const nomination = ref({ team_id: null, competition_class_id: null })
const nominationRoster = computed(() => selectedRoster.value.filter((id) => detail.value?.roster_entries.some((row) => row.id === id && !row.competition_registration_id)))
const boardSport = computed(() => detail.value?.game_planning_enabled && ['fussball', 'football', 'basketball'].includes(detail.value?.sport_type))
const selectedGame = computed(() => detail.value?.games?.find((game) => game.id === selectedGameId.value))
const refreshGame = async () => { if (detail.value) detail.value = await api('get', `/api/v1/competitions/${detail.value.id}`) }
const saveGame = async () => {
    if (!gameDraft.value) return
    let createdId = null
    const saved = await run(async () => {
        const created = await api(gameDraft.value.id ? 'put' : 'post', `/api/v1/competitions/${detail.value.id}/games${gameDraft.value.id ? `/${gameDraft.value.id}` : ''}`, { opponent: gameDraft.value.opponent, scheduled_at: apiDateTime(gameDraft.value.scheduled_at) })
        createdId = created.id
        return created
    })
    if (saved) { selectedGameId.value = createdId; gameDraft.value = null }
}
const seasonForm = ref(null)
const seasonEvents = computed(() => seasons.value.events.filter((row) => row.sport_year_period_id === seasonForm.value?.sport_year_period_id))
const field = (key, label = key, type = 'text', extra = {}) => ({ key, label: l(label), type, ...extra })
const competitionFields = computed(() => [field('name', 'name', 'text', { required: true }), field('team_id', 'team', 'text', { options: options(club.value?.teams || []) }), field('season_name', 'period'), field('status', 'status', 'text', { required: true, options: statusOptions(['planned', 'open', 'closed', 'completed', 'cancelled']) }), field('starts_on', 'start', 'date', { required: true }), field('ends_on', 'end', 'date', { required: true }), field('registration_deadline_at', 'deadline', 'datetime-local')])
const seasonFields = computed(() => [field('name', 'name', 'text', { required: true }), field('sport_year_period_id', 'period', 'text', { required: true, options: options(club.value?.periods || []) }), field('status', 'status', 'text', { required: true, options: statusOptions(['draft', 'published', 'completed']) }), field('goals', 'goals', 'textarea')])
const phaseFields = computed(() => [field('name', 'name', 'text', { required: true }), field('starts_on', 'start', 'date', { required: true }), field('ends_on', 'end', 'date', { required: true }), field('goal', 'goal', 'textarea')])
const editorFields = computed(() => {
    if (!editor.value) return []
    switch (editor.value.kind) {
        case 'competition': return competitionFields.value
        case 'classes': return [field('name', 'name', 'text', { required: true }), field('age_group', 'age'), field('discipline', 'discipline')]
        case 'venues': return [field('name', 'name', 'text', { required: true }), field('city', 'city')]
        case 'roster': return [field('display_name', 'name', 'text', { required: true }), field('user_id', 'member', 'text', { options: options(club.value?.members || []) }), field('competition_class_id', 'classes', 'text', { options: options(detail.value?.classes || []) }), field('bib_number', 'bib')]
        case 'match': return [field('home_score', 'scoreHome', 'number', { min: 0 }), field('away_score', 'scoreAway', 'number', { min: 0 }), field('status', 'status', 'text', { required: true, options: statusOptions(['planned', 'scheduled', 'completed', 'cancelled']) }), field('scheduled_at', 'start', 'datetime-local'), field('reason', 'reason', 'textarea', { required: true })]
        default: return []
    }
})
const api = async (method, url, data) => (await window.axios({ method, url, data, headers: { Accept: 'application/json' } })).data.data
const showError = (e) => { error.value = planningError(e) }
const load = async (selectedId = null) => {
    const id = ++loadId; loading.value = true; error.value = ''; detail.value = null; editor.value = null; seasonForm.value = null
    competitions.value = []; seasons.value = { plans: [], events: [], can_edit: false }
    selectedRoster.value = []; lineupRows.value = {}; lineupName.value = ''; nomination.value = { team_id: null, competition_class_id: null }
    try {
        if (tab.value === 'competition' && clubId.value) {
            const data = await api('get', `/api/v1/clubs/${clubId.value}/competitions`)
            if (id === loadId) {
                competitions.value = data
                if (selectedId && data.some((row) => row.id === selectedId)) {
                    const result = await api('get', `/api/v1/competitions/${selectedId}`)
                    if (id === loadId) detail.value = result
                }
            }
        } else if (teamId.value) {
            const data = await api('get', `/api/v1/teams/${teamId.value}/season-plans`)
            if (id === loadId) seasons.value = data
        } else seasons.value = { plans: [], events: [], can_edit: false }
    } catch (e) { if (id === loadId) showError(e) }
    finally { if (id === loadId) loading.value = false }
}
const openCompetition = async (id) => {
    if (busy.value || loading.value) return
    const token = ++loadId; loading.value = true; error.value = ''; detail.value = null; editor.value = null; selectedGameId.value = null; gameDraft.value = null
    try { const data = await api('get', `/api/v1/competitions/${id}`); if (token === loadId) { detail.value = data; selectedRoster.value = []; lineupRows.value = {}; lineupName.value = ''; nomination.value = { team_id: null, competition_class_id: null } } }
    catch (e) { if (token === loadId) showError(e) } finally { if (token === loadId) loading.value = false }
}
const run = async (action, refreshDetail = true) => {
    if (busy.value) return
    busy.value = true; error.value = ''; success.value = ''
    try {
        const selectedId = detail.value?.id
        const data = await action(); success.value = l('saved'); editor.value = null; seasonForm.value = null
        await load(tab.value === 'competition' ? (refreshDetail ? selectedId || data?.id : data?.id) : null)
        return true
    } catch (e) { showError(e); return false } finally { busy.value = false }
}
const editCompetition = (row = null) => {
    editor.value = { kind: 'competition', id: row?.id, data: { name: row?.name || '', team_id: row?.team_id || null, season_name: row?.season_name || '', status: row?.status || 'planned', starts_on: row?.starts_on?.slice(0, 10) || '', ends_on: row?.ends_on?.slice(0, 10) || '', registration_deadline_at: localDateTime(row?.registration_deadline_at) } }
}
const submitEditor = () => {
    const { kind, id, data } = editor.value
    const normalized = Object.fromEntries(Object.entries(data).map(([key, value]) => [key, value === '' ? null : value]))
    if (kind === 'competition') return run(() => api(id ? 'put' : 'post', id ? `/api/v1/competitions/${id}` : `/api/v1/clubs/${clubId.value}/competitions`, { ...normalized, registration_deadline_at: apiDateTime(data.registration_deadline_at) }), false)
    if (kind === 'match') return run(() => api('put', `/api/v1/competitions/${detail.value.id}/matches/${id}`, { ...normalized, scheduled_at: apiDateTime(data.scheduled_at) }))
    return run(() => api('post', `/api/v1/competitions/${detail.value.id}/components/${kind}`, normalized))
}
const editSeason = (row = null) => {
    const period = club.value?.periods.find((p) => p.id === team.value?.sport_year_period_id) || club.value?.periods[0]
    seasonForm.value = row ? seasonDraft(row) : { name: '', sport_year_period_id: period?.id || null, goals: '', status: 'draft', phases: [{ name: '', starts_on: period?.starts_on?.slice(0, 10) || '', ends_on: period?.ends_on?.slice(0, 10) || '', goal: '' }], event_ids: [] }
}
const saveSeason = () => {
    const row = seasonForm.value
    return run(() => api(row.id ? 'put' : 'post', `/api/v1/teams/${teamId.value}/season-plans${row.id ? `/${row.id}` : ''}`, { name: row.name, sport_year_period_id: row.sport_year_period_id, goals: row.goals, status: row.status, revision: row.revision, phases: row.phases, event_ids: row.event_ids.filter((id) => seasonEvents.value.some((e) => e.id === id)) }), false)
}
const deleteSeason = (row) => { if (window.confirm(l('confirmDelete'))) run(() => api('delete', `/api/v1/teams/${teamId.value}/season-plans/${row.id}`), false) }
const deleteCompetition = () => { if (window.confirm(l('confirmDelete'))) run(() => api('delete', `/api/v1/competitions/${detail.value.id}`)) }
const date = (value) => value ? new Date(value).toLocaleString(locale.value) : '-'
watch(clubId, () => { teamId.value = club.value?.teams.find((row) => row.id === Number(query.get('team_id')))?.id || club.value?.teams[0]?.id || null })
watch([tab, clubId, teamId], () => { success.value = ''; load() })
onMounted(async () => {
    loading.value = true
    try { clubs.value = (await api('get', '/api/v1/planning/workspace')).clubs; clubId.value = clubs.value.find((c) => c.id === Number(query.get('club_id')))?.id || clubs.value[0]?.id || null }
    catch (e) { showError(e) } finally { if (!clubId.value) loading.value = false }
})
</script>

<template>
    <Head :title="l('planning')" />
    <main class="mx-auto max-w-6xl space-y-5 p-4 text-primary md:p-6" :aria-busy="busy || loading">
        <header class="flex flex-wrap items-center justify-between gap-3"><h1 class="text-2xl font-bold text-primary">{{ l('planning') }}</h1><Link :href="route('auth.family-calendar.index')" class="text-link">{{ l('family') }}</Link></header>
        <div class="flex flex-wrap items-end gap-3 border-b border-border pb-4">
            <label class="text-sm text-primary">{{ l('club') }}<select v-model="clubId" :disabled="busy" class="ml-2 max-w-full rounded border-border bg-inputBg"><option v-for="row in clubs" :key="row.id" :value="row.id">{{ row.name }}</option></select></label>
            <button v-for="key in ['competition', 'season']" :key="key" :disabled="busy" type="button" :aria-pressed="tab === key" class="rounded border px-3 py-2 text-sm" :class="tab === key ? 'border-buttonPrimary text-primary' : 'border-border text-secondary'" @click="tab = key">{{ l(key) }}</button>
            <button :disabled="busy || loading" :title="l('retry')" :aria-label="l('retry')" @click="load(detail?.id)"><i class="las la-sync text-xl" /></button>
        </div>
        <p v-if="error" role="alert" class="text-error">{{ error }}</p><p v-if="success" role="status" class="text-success">{{ success }}</p><p v-if="loading" role="status">{{ l('loading') }}</p>
        <p v-if="!loading && !clubs.length">{{ l('empty') }}</p>
        <template v-if="club && tab === 'season'">
            <label class="block text-sm text-primary">{{ l('team') }}<select v-model="teamId" :disabled="busy" class="ml-2 max-w-full rounded border-border bg-inputBg"><option v-for="row in club.teams" :key="row.id" :value="row.id">{{ row.name }}</option></select></label>
            <p v-if="!club.season_planning_enabled" class="text-secondary">{{ l('pro') }}</p>
            <p v-else-if="!loading && team && !seasons.can_edit" class="text-sm text-secondary">{{ readOnlyLabel }}</p>
            <button v-if="seasons.can_edit && !seasonForm" :disabled="busy || loading || !club.periods.length" class="rounded bg-buttonPrimary px-4 py-2 text-buttonTextPrimary" @click="editSeason()"><i class="las la-plus" /> {{ l('season') }}: {{ l('new') }}</button>
            <form v-if="seasonForm && seasons.can_edit" class="space-y-4 border-y border-border py-4" @submit.prevent="saveSeason">
                <PlanningFields :model="seasonForm" :fields="seasonFields" :disabled="busy" />
                <h2 class="text-lg font-bold">{{ l('phases') }}</h2>
                <div v-for="(phase, index) in seasonForm.phases" :key="index" class="space-y-2 border-b border-border pb-4"><PlanningFields :model="phase" :fields="phaseFields" :disabled="busy" /><button :disabled="busy || seasonForm.phases.length === 1" type="button" :title="l('remove')" @click="seasonForm.phases.splice(index, 1)"><i class="las la-trash text-xl" /></button></div>
                <button type="button" :disabled="busy" @click="seasonForm.phases.push({ name: '', starts_on: '', ends_on: '', goal: '' })"><i class="las la-plus" /> {{ l('add') }}</button>
                <fieldset :disabled="busy" class="space-y-2"><legend class="font-bold">{{ l('events') }}</legend><label v-for="event in seasonEvents" :key="event.id" class="flex items-center gap-2"><input v-model="seasonForm.event_ids" type="checkbox" :value="event.id" />{{ event.title }} · {{ date(event.start_time) }}</label></fieldset>
                <div class="flex gap-3"><button :disabled="busy" class="rounded bg-buttonPrimary px-4 py-2 text-buttonTextPrimary"><i class="las la-save" /> {{ l('save') }}</button><button type="button" :disabled="busy" @click="seasonForm = null">{{ l('cancel') }}</button></div>
            </form>
            <section v-for="plan in seasons.plans" :key="plan.id" class="min-w-0 space-y-2 border-b border-border py-4"><div class="flex flex-wrap items-center justify-between gap-2"><h2 class="break-words text-lg font-bold">{{ plan.name }} · {{ plan.status }}</h2><div v-if="seasons.can_edit" class="flex gap-3"><button :disabled="busy" :title="l('edit')" :aria-label="`${l('edit')}: ${plan.name}`" @click="editSeason(plan)"><i class="las la-edit text-xl" /></button><button :disabled="busy" :title="l('remove')" :aria-label="`${l('remove')}: ${plan.name}`" @click="deleteSeason(plan)"><i class="las la-trash text-xl" /></button></div></div><p class="text-sm text-secondary">{{ l('period') }}: {{ sportYearLabel(plan.sport_year_period_id) }}</p><p class="whitespace-pre-wrap break-words">{{ plan.goals }}</p><p v-for="(phase, index) in plan.phases" :key="index" class="break-words">{{ phase.name }} · {{ phase.starts_on }} - {{ phase.ends_on }} · {{ phase.goal }}</p><p v-for="event in plan.events" :key="event.id">{{ event.title }} · {{ date(event.start_time) }}</p></section>
            <p v-if="!loading && !seasons.plans.length">{{ l('empty') }}</p>
        </template>
        <template v-if="club && tab === 'competition'">
            <button v-if="club.can_edit_competitions" :disabled="busy || loading" class="rounded bg-buttonPrimary px-4 py-2 text-buttonTextPrimary" @click="editCompetition()"><i class="las la-plus" /> {{ l('competition') }}: {{ l('new') }}</button>
            <p v-if="!canEditCompetition && !loading" class="text-sm text-secondary">{{ readOnlyLabel }}</p>
            <div class="grid gap-5 lg:grid-cols-[240px_minmax(0,1fr)]">
                <nav class="space-y-2" :aria-label="l('competition')"><button v-for="row in competitions" :key="row.id" :disabled="busy" class="block w-full border-b border-border p-3 text-start" @click="openCompetition(row.id)"><span class="font-semibold">{{ row.name }}</span><span class="block text-sm text-secondary">{{ row.status }} · {{ row.starts_on?.slice(0, 10) }}</span></button><p v-if="!loading && !competitions.length">{{ l('empty') }}</p></nav>
                <div class="min-w-0 space-y-5">
                    <form v-if="editor" class="space-y-3 border-y border-border py-4" @submit.prevent="submitEditor"><h2 class="text-lg font-bold">{{ editor.kind === 'competition' ? l('competition') : l(editor.kind === 'match' ? 'tournament' : editor.kind) }}</h2><PlanningFields :model="editor.data" :fields="editorFields" :disabled="busy" /><div class="flex gap-3"><button :disabled="busy" class="rounded bg-buttonPrimary px-4 py-2 text-buttonTextPrimary"><i class="las la-save" /> {{ l('save') }}</button><button type="button" :disabled="busy" @click="editor = null">{{ l('cancel') }}</button></div></form>
                    <template v-if="detail">
                        <header class="flex flex-wrap items-center justify-between gap-2"><h2 class="min-w-0 break-words text-xl font-bold">{{ detail.name }} · {{ detail.status }}</h2><div class="flex gap-3"><button v-if="canEditCompetition" :disabled="busy" :title="l('edit')" :aria-label="l('edit')" @click="editCompetition(detail)"><i class="las la-edit text-xl" /></button><button v-if="club.can_delete_competitions && !detail.registrations.length && !detail.tournament_matches.length && !detail.games.length" :disabled="busy" :title="l('remove')" :aria-label="l('remove')" @click="deleteCompetition"><i class="las la-trash text-xl" /></button></div></header>
                        <p class="text-sm text-secondary">{{ detail.starts_on?.slice(0, 10) }} - {{ detail.ends_on?.slice(0, 10) }} · {{ detail.season_name }}<span v-if="detail.registration_deadline_at" class="block">{{ l('deadline') }}: {{ date(detail.registration_deadline_at) }}</span></p>
                        <section v-for="kind in ['classes', 'venues', 'roster']" :key="kind" class="space-y-2 border-b border-border pb-4">
                            <div class="flex items-center justify-between"><h3 class="font-bold">{{ l(kind) }}</h3><button v-if="canEditCompetition" :disabled="busy" :title="l('add')" :aria-label="`${l('add')}: ${l(kind)}`" @click="editor = { kind, data: {} }"><i class="las la-plus text-xl" /></button></div>
                            <div v-for="row in kind === 'roster' ? detail.roster_entries : detail[kind]" :key="row.id" class="flex flex-wrap items-center gap-3">
                                <input v-if="kind === 'roster' && canEditCompetition" v-model="selectedRoster" type="checkbox" :value="row.id" :disabled="busy" :aria-label="row.display_name" />
                                <span class="min-w-0 flex-1 break-words">{{ row.name || row.display_name }} {{ row.bib_number || row.city || row.discipline }}<span v-if="row.competition_class_id" class="block text-sm text-secondary">{{ detail.classes.find((item) => item.id === row.competition_class_id)?.name }}</span></span>
                                <button v-if="canEditCompetition && !detail.registrations.length && !detail.tournament_matches.length && !detail.lineups.length" :disabled="busy" :title="l('remove')" :aria-label="`${l('remove')}: ${row.name || row.display_name}`" @click="run(() => api('delete', `/api/v1/competitions/${detail.id}/components/${kind}/${row.id}`))"><i class="las la-trash" /></button>
                            </div><p v-if="!(kind === 'roster' ? detail.roster_entries : detail[kind]).length" class="text-sm text-secondary">{{ l('empty') }}</p>
                        </section>
                        <section class="space-y-3 border-b border-border pb-4">
                            <h3 class="font-bold">{{ l('registration') }}</h3>
                            <form v-if="canEditCompetition && nominationsOpen" class="space-y-3" @submit.prevent="run(() => api('post', `/api/v1/competitions/${detail.id}/registrations`, { ...nomination, roster_ids: nominationRoster, start_fee_cents: Number(fee) }))">
                                <PlanningFields :model="nomination" :disabled="busy" :fields="[field('competition_class_id', 'classes', 'text', { options: options(detail.classes) }), field('team_id', 'team', 'text', { options: options(club.teams) })]" />
                                <div class="flex flex-wrap items-end gap-3"><label class="text-sm">{{ l('fee') }}<input v-model="fee" :disabled="busy" required type="number" min="0" max="100000000" class="ml-2 w-32 rounded border-border bg-inputBg" /></label><button :disabled="busy || !nominationRoster.length" class="rounded border border-border px-3 py-2">{{ l('nominate') }} ({{ nominationRoster.length }})</button></div>
                            </form>
                            <div v-for="row in detail.registrations" :key="row.id" class="space-y-2 py-2">
                                <p>#{{ row.id }} · {{ row.status }}<span v-if="row.license_status"> · {{ row.license_status }}</span><span v-if="detail.can_view_finance"> · {{ row.start_fee_cents }} Cent</span></p>
                                <p v-for="entry in detail.roster_entries.filter((entry) => entry.competition_registration_id === row.id)" :key="entry.id" class="text-sm text-secondary">{{ entry.display_name }}</p>
                                <div v-if="nominationsOpen" class="flex flex-wrap gap-3">
                                    <button v-if="canEditCompetition && row.status !== 'confirmed'" :disabled="busy" @click="run(() => api('post', `/api/v1/competitions/${detail.id}/registrations/${row.id}/eligibility`))">{{ l('eligibility') }}</button>
                                    <button v-if="detail.can_pay_fees && !row.start_fee_paid_at" :disabled="busy" @click="run(() => api('post', `/api/v1/competitions/${detail.id}/registrations/${row.id}/paid`))">{{ l('paid') }}</button>
                                    <button v-if="detail.can_confirm && row.status === 'pending_confirmation'" :disabled="busy || (row.start_fee_cents > 0 && !row.start_fee_paid_at)" @click="run(() => api('post', `/api/v1/competitions/${detail.id}/registrations/${row.id}/confirm`))">{{ l('confirm') }}</button>
                                </div>
                            </div><p v-if="!detail.registrations.length" class="text-sm text-secondary">{{ l('empty') }}</p>
                        </section>
                        <section class="space-y-3 border-b border-border pb-4">
                            <h3 class="font-bold">{{ l('tournament') }}</h3>
                            <form v-if="canEditCompetition && !detail.tournament_matches.length" class="flex flex-wrap items-end gap-3" @submit.prevent="run(() => api('post', `/api/v1/competitions/${detail.id}/tournament`, { roster_ids: selectedRoster, group_count: Number(groups) }))"><label>{{ l('groups') }}<input v-model="groups" :disabled="busy" required type="number" min="1" :max="Math.min(32, selectedRoster.length)" class="ml-2 w-20 rounded border-border bg-inputBg" /></label><button :disabled="busy || selectedRoster.length < 2" class="rounded border border-border px-3 py-2">{{ l('generate') }}</button></form>
                            <div v-for="match in detail.tournament_matches" :key="match.id" class="flex flex-wrap items-center justify-between gap-2">
                                <p class="break-words">{{ match.home_label }} · {{ match.home_score ?? '-' }} : {{ match.away_score ?? '-' }} · {{ match.away_label }}<span class="block text-sm text-secondary">{{ match.phase }} · {{ match.status }} · {{ date(match.scheduled_at) }}</span></p>
                                <button v-if="canEditCompetition" :disabled="busy" :title="l('edit')" :aria-label="`${l('edit')}: ${match.home_label} / ${match.away_label}`" @click="editor = { kind: 'match', id: match.id, data: { home_score: match.home_score, away_score: match.away_score, scheduled_at: localDateTime(match.scheduled_at), status: match.status, reason: '' } }"><i class="las la-edit text-xl" /></button>
                            </div>
                            <div v-for="row in detail.standings" :key="row.id" class="text-sm">{{ row.rank }}. {{ row.display_name }} · {{ row.points }}</div>
                        </section>
                        <section v-if="boardSport" class="space-y-3 border-b border-border pb-4">
                            <div class="flex items-center justify-between"><h3 class="font-bold">{{ locale === 'de' ? 'Spiele und Aufstellungen' : 'Games and lineups' }}</h3><button v-if="canEditCompetition && ['planned', 'open'].includes(detail.status)" type="button" :disabled="busy" :title="locale === 'de' ? 'Spiel anlegen' : 'Add game'" @click="gameDraft = { opponent: '', scheduled_at: '' }"><i class="las la-plus text-xl" /></button></div>
                            <form v-if="gameDraft" class="grid gap-3 border-y border-border py-3 sm:grid-cols-2" @submit.prevent="saveGame"><label class="text-sm">{{ locale === 'de' ? 'Gegner' : 'Opponent' }}<input v-model.trim="gameDraft.opponent" required maxlength="120" :disabled="busy" class="mt-1 block w-full rounded border-border bg-inputBg" /></label><label class="text-sm">{{ locale === 'de' ? 'Termin' : 'Date and time' }}<input v-model="gameDraft.scheduled_at" type="datetime-local" :disabled="busy" class="mt-1 block w-full rounded border-border bg-inputBg" /></label><div class="flex gap-3"><button :disabled="busy" class="rounded bg-buttonPrimary px-3 py-2 text-buttonTextPrimary"><i class="las la-save" /> {{ l('save') }}</button><button type="button" :disabled="busy" @click="gameDraft = null">{{ l('cancel') }}</button></div></form>
                            <div class="flex flex-wrap items-center gap-2"><button v-for="game in detail.games" :key="game.id" type="button" :disabled="busy" :aria-pressed="selectedGameId === game.id" class="border px-3 py-2 text-sm" :class="selectedGameId === game.id ? 'border-buttonPrimary text-primary' : 'border-border text-secondary'" @click="selectedGameId = game.id">{{ game.opponent }}<span class="block text-xs">{{ date(game.scheduled_at) }}</span></button><button v-if="selectedGame && canEditCompetition" type="button" :disabled="busy" :title="l('edit')" :aria-label="`${l('edit')}: ${selectedGame.opponent}`" @click="gameDraft = { id: selectedGame.id, opponent: selectedGame.opponent, scheduled_at: localDateTime(selectedGame.scheduled_at) }"><i class="las la-edit text-xl" /></button></div>
                            <GameLineupBoard v-if="selectedGame" :key="selectedGame.id" :competition-id="detail.id" :game="selectedGame" :roster="detail.roster_entries" :eligible-ids="detail.eligible_roster_ids" :can-edit="canEditCompetition && ['planned', 'open'].includes(detail.status)" :locale="locale" @saved="refreshGame" />
                        </section>
                        <section class="space-y-3"><h3 class="font-bold">{{ l('lineups') }}</h3><form v-if="canEditCompetition && !boardSport" class="space-y-3" @submit.prevent="run(() => api('post', `/api/v1/competitions/${detail.id}/lineups`, { name: lineupName, entries: selectedRoster.map((id) => ({ competition_roster_entry_id: id, role: lineupRows[id]?.role || 'starter', position: lineupRows[id]?.position || null })) }))"><input v-model="lineupName" :disabled="busy" required maxlength="120" :aria-label="l('name')" :placeholder="l('name')" class="w-full rounded border-border bg-inputBg" /><div v-for="id in selectedRoster" :key="id" class="flex flex-wrap items-center gap-2"><span>{{ detail.roster_entries.find((r) => r.id === id)?.display_name }}</span><select :value="lineupRows[id]?.role || 'starter'" :disabled="busy" :aria-label="l('status')" class="rounded border-border bg-inputBg" @change="lineupRows[id] = { ...lineupRows[id], role: $event.target.value }"><option value="starter">{{ l('starter') }}</option><option value="substitute">{{ l('substitute') }}</option></select><input :value="lineupRows[id]?.position || ''" :disabled="busy" maxlength="80" :aria-label="l('position')" :placeholder="l('position')" class="max-w-full rounded border-border bg-inputBg" @input="lineupRows[id] = { ...lineupRows[id], position: $event.target.value }" /></div><button :disabled="busy || !selectedRoster.length" class="rounded border border-border px-3 py-2"><i class="las la-save" /> {{ l('save') }}</button></form><div v-for="row in detail.lineups.filter((row) => !row.competition_game_id)" :key="row.id"><h4 class="font-semibold">{{ row.name }}</h4><p v-for="entry in row.entries" :key="entry.id">{{ detail.roster_entries.find((r) => r.id === entry.competition_roster_entry_id)?.display_name }} · {{ entry.role }} · {{ entry.position }}</p></div></section>
                    </template>
                </div>
            </div>
        </template>
    </main>
</template>
