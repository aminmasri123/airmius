<script setup>
import { computed, onMounted, reactive, ref } from 'vue'
import { Head } from '@inertiajs/vue3'
import AppLayout from '@/Components/Auth/Layouts/AppLayout.vue'

defineOptions({ layout: AppLayout })

const props = defineProps({ initialInviteeId: { type: Number, default: null } })

const loading = ref(true)
const saving = ref(false)
const error = ref('')
const challenges = ref([])
const meta = ref({ sports: [], friends: [], clubs: [], teams: [], can_create_public: false })
const showCreate = ref(false)
const initialInviteApplied = ref(false)
const filter = ref('all')
const expanded = ref(null)
const details = reactive({})
const comments = reactive({})
const commentDrafts = reactive({})
const values = reactive({})
const notes = reactive({})

const today = new Date().toISOString().slice(0, 10)
const inThirtyDays = new Date(Date.now() + 29 * 86400000).toISOString().slice(0, 10)
const form = reactive({
    title: '', description: '', sport_id: '', visibility: 'invite_only', club_id: '', team_id: '',
    metric: 'steps', target_value: 10000, unit: 'Schritte', frequency: 'daily', verification: 'manual',
    starts_on: today, ends_on: inThirtyDays, invitee_ids: [],
})

const metricLabels = { steps: 'Schritte', distance_meters: 'Distanz', duration_minutes: 'Trainingszeit', sessions: 'Einheiten', repetitions: 'Wiederholungen', calories: 'Kalorien', custom: 'Eigenes Ziel' }
const frequencyLabels = { daily: 'Täglich', weekly: 'Wöchentlich', once: 'Einmalig' }
const visibilityLabels = { public: 'Öffentlich', club: 'Verein', team: 'Team', invite_only: 'Privat / Einladungen' }
const stateLabels = { active: 'Aktiv', upcoming: 'Demnächst', finished: 'Beendet', cancelled: 'Abgebrochen' }
const unitDefaults = { steps: 'Schritte', distance_meters: 'm', duration_minutes: 'Minuten', sessions: 'Einheiten', repetitions: 'Wiederholungen', calories: 'kcal', custom: '' }

const visibleChallenges = computed(() => filter.value === 'all' ? challenges.value : challenges.value.filter((item) => item.state === filter.value || (filter.value === 'invited' && item.my_participation?.status === 'pending')))
const creatableClubs = computed(() => (meta.value.clubs || []).filter((item) => item.can_create))
const creatableTeams = computed(() => (meta.value.teams || []).filter((item) => item.can_create))
const visibilityOptions = computed(() => ['invite_only', ...(creatableClubs.value.length ? ['club'] : []), ...(creatableTeams.value.length ? ['team'] : []), ...(meta.value.can_create_public ? ['public'] : [])])

const apiError = (exception) => {
    const errors = exception?.response?.data?.errors
    if (errors) return Object.values(errors).flat().join(' ')
    return exception?.response?.data?.message || 'Die Aktion konnte nicht ausgeführt werden.'
}

async function load() {
    loading.value = true
    error.value = ''
    try {
        const response = await window.axios.get('/api/v1/challenges')
        challenges.value = response.data.data || []
        meta.value = response.data.meta || meta.value
        if (!initialInviteApplied.value && props.initialInviteeId && meta.value.friends?.some((friend) => Number(friend.id) === Number(props.initialInviteeId))) {
            form.invitee_ids = [Number(props.initialInviteeId)]
            showCreate.value = true
            initialInviteApplied.value = true
        }
    } catch (exception) {
        error.value = apiError(exception)
    } finally {
        loading.value = false
    }
}

function resetForm() {
    Object.assign(form, { title: '', description: '', sport_id: '', visibility: 'invite_only', club_id: '', team_id: '', metric: 'steps', target_value: 10000, unit: 'Schritte', frequency: 'daily', verification: 'manual', starts_on: today, ends_on: inThirtyDays, invitee_ids: [] })
}

function metricChanged() {
    form.unit = unitDefaults[form.metric]
    if (form.metric === 'steps') form.target_value = 10000
    else if (form.metric === 'duration_minutes') form.target_value = 30
    else if (form.metric === 'distance_meters') form.target_value = 5000
    else form.target_value = 1
}

async function createChallenge() {
    saving.value = true
    error.value = ''
    try {
        await window.axios.post('/api/v1/challenges', {
            ...form,
            sport_id: form.sport_id || null,
            club_id: form.visibility === 'club' ? Number(form.club_id) : null,
            team_id: form.visibility === 'team' ? Number(form.team_id) : null,
            invitee_ids: form.invitee_ids.map(Number),
        })
        showCreate.value = false
        resetForm()
        await load()
    } catch (exception) {
        error.value = apiError(exception)
    } finally {
        saving.value = false
    }
}

async function action(challenge, method, path, payload = {}) {
    error.value = ''
    try {
        await window.axios({ method, url: `/api/v1/challenges/${challenge.id}${path}`, data: payload })
        await load()
        if (expanded.value === challenge.id) await openDetails(challenge, true)
    } catch (exception) {
        error.value = apiError(exception)
    }
}

async function openDetails(challenge, force = false) {
    if (expanded.value === challenge.id && !force) {
        expanded.value = null
        return
    }
    expanded.value = challenge.id
    try {
        const response = await window.axios.get(`/api/v1/challenges/${challenge.id}`)
        details[challenge.id] = response.data.data
        comments[challenge.id] = response.data.data.comments || []
    } catch (exception) {
        error.value = apiError(exception)
    }
}

async function checkin(challenge, completed = true) {
    const entered = values[challenge.id]
    await action(challenge, 'put', `/check-ins/${today}`, {
        completed,
        value: !completed || entered === '' || entered === undefined ? null : Number(entered),
        note: notes[challenge.id] || null,
    })
}

async function addComment(challenge) {
    const content = String(commentDrafts[challenge.id] || '').trim()
    if (!content) return
    try {
        const response = await window.axios.post(`/api/v1/challenges/${challenge.id}/comments`, { content })
        comments[challenge.id] = [...(comments[challenge.id] || []), response.data.data]
        commentDrafts[challenge.id] = ''
    } catch (exception) {
        error.value = apiError(exception)
    }
}

const isDoneToday = (challenge) => challenge.my_checkins?.some((item) => item.date === today && item.completed)
const formatDate = (date) => new Intl.DateTimeFormat('de-DE', { day: '2-digit', month: '2-digit', year: 'numeric' }).format(new Date(`${date}T12:00:00`))
const formatTime = (date) => new Intl.DateTimeFormat('de-DE', { day: '2-digit', month: '2-digit', hour: '2-digit', minute: '2-digit' }).format(new Date(date))

onMounted(load)
</script>

<template>
    <Head title="Challenges" />
    <div class="mx-auto max-w-7xl space-y-6 px-4 py-6 sm:px-6 lg:px-8">
        <section class="overflow-hidden rounded-[30px] border border-air-blue/20 bg-card shadow-sm">
            <div class="bg-gradient-to-br from-air-blue/15 via-card to-air-green/10 p-6 sm:p-9">
                <div class="flex flex-col gap-5 sm:flex-row sm:items-center sm:justify-between">
                    <div>
                        <p class="text-xs font-black uppercase tracking-[0.2em] text-air-blue">Gemeinsam dranbleiben</p>
                        <h1 class="mt-2 text-4xl font-black tracking-tight text-primary">Challenges</h1>
                        <p class="mt-3 max-w-2xl text-sm leading-6 text-secondary">Tägliche und wöchentliche Ziele für Freunde, Teams, Vereine oder die gesamte Community.</p>
                    </div>
                    <button class="inline-flex items-center justify-center gap-2 rounded-2xl bg-air-blue px-5 py-3 font-black text-white shadow-lg shadow-air-blue/20" @click="showCreate = !showCreate">
                        <i class="las" :class="showCreate ? 'la-times' : 'la-plus'"></i>{{ showCreate ? 'Schließen' : 'Challenge erstellen' }}
                    </button>
                </div>
            </div>
        </section>

        <p v-if="error" class="rounded-2xl border border-red-300 bg-red-50 p-4 text-sm font-semibold text-red-700">{{ error }}</p>

        <form v-if="showCreate" class="rounded-[28px] border border-border bg-card p-5 shadow-sm sm:p-7" @submit.prevent="createChallenge">
            <h2 class="text-xl font-black text-primary">Neue Challenge</h2>
            <div class="mt-5 grid gap-4 md:grid-cols-2 xl:grid-cols-4">
                <label class="text-sm font-bold text-secondary xl:col-span-2">Titel<input v-model="form.title" required maxlength="140" placeholder="30 Tage – 10.000 Schritte" class="mt-2 w-full rounded-xl border-border bg-inputBg text-primary" /></label>
                <label class="text-sm font-bold text-secondary">Sichtbarkeit<select v-model="form.visibility" class="mt-2 w-full rounded-xl border-border bg-inputBg text-primary"><option v-for="item in visibilityOptions" :key="item" :value="item">{{ visibilityLabels[item] }}</option></select></label>
                <label class="text-sm font-bold text-secondary">Sportart<select v-model="form.sport_id" class="mt-2 w-full rounded-xl border-border bg-inputBg text-primary"><option value="">Sportübergreifend</option><option v-for="sport in meta.sports" :key="sport.id" :value="sport.id">{{ sport.name }}</option></select></label>
                <label v-if="form.visibility === 'club'" class="text-sm font-bold text-secondary">Verein<select v-model="form.club_id" required class="mt-2 w-full rounded-xl border-border bg-inputBg text-primary"><option value="">Auswählen</option><option v-for="club in creatableClubs" :key="club.id" :value="club.id">{{ club.name }}</option></select></label>
                <label v-if="form.visibility === 'team'" class="text-sm font-bold text-secondary">Team<select v-model="form.team_id" required class="mt-2 w-full rounded-xl border-border bg-inputBg text-primary"><option value="">Auswählen</option><option v-for="team in creatableTeams" :key="team.id" :value="team.id">{{ team.name }}</option></select></label>
                <label class="text-sm font-bold text-secondary">Zieltyp<select v-model="form.metric" class="mt-2 w-full rounded-xl border-border bg-inputBg text-primary" @change="metricChanged"><option v-for="(label, key) in metricLabels" :key="key" :value="key">{{ label }}</option></select></label>
                <label class="text-sm font-bold text-secondary">Zielwert<input v-model.number="form.target_value" type="number" min="0.01" step="0.01" required class="mt-2 w-full rounded-xl border-border bg-inputBg text-primary" /></label>
                <label class="text-sm font-bold text-secondary">Einheit<input v-model="form.unit" maxlength="24" class="mt-2 w-full rounded-xl border-border bg-inputBg text-primary" /></label>
                <label class="text-sm font-bold text-secondary">Rhythmus<select v-model="form.frequency" class="mt-2 w-full rounded-xl border-border bg-inputBg text-primary"><option v-for="(label, key) in frequencyLabels" :key="key" :value="key">{{ label }}</option></select></label>
                <label class="text-sm font-bold text-secondary">Start<input v-model="form.starts_on" type="date" required class="mt-2 w-full rounded-xl border-border bg-inputBg text-primary" /></label>
                <label class="text-sm font-bold text-secondary">Ende<input v-model="form.ends_on" type="date" required class="mt-2 w-full rounded-xl border-border bg-inputBg text-primary" /></label>
                <label class="text-sm font-bold text-secondary xl:col-span-2">Beschreibung<textarea v-model="form.description" rows="3" maxlength="3000" placeholder="Worum geht es und wie wird das Ziel erreicht?" class="mt-2 w-full rounded-xl border-border bg-inputBg text-primary"></textarea></label>
            </div>
            <div v-if="meta.friends?.length" class="mt-5">
                <p class="text-sm font-black text-primary">Freunde einladen</p>
                <div class="mt-2 flex flex-wrap gap-2">
                    <label v-for="friend in meta.friends" :key="friend.id" class="flex cursor-pointer items-center gap-2 rounded-full border border-border px-3 py-2 text-sm text-secondary"><input v-model="form.invitee_ids" type="checkbox" :value="friend.id" class="rounded border-border text-air-blue" />{{ friend.name }}</label>
                </div>
            </div>
            <div class="mt-6 flex justify-end"><button :disabled="saving" class="rounded-2xl bg-air-blue px-6 py-3 font-black text-white disabled:opacity-50">{{ saving ? 'Wird veröffentlicht …' : 'Challenge veröffentlichen' }}</button></div>
        </form>

        <div class="flex gap-2 overflow-x-auto pb-1">
            <button v-for="item in [['all','Alle'],['active','Aktiv'],['invited','Einladungen'],['upcoming','Demnächst'],['finished','Beendet']]" :key="item[0]" class="whitespace-nowrap rounded-full border px-4 py-2 text-sm font-bold" :class="filter === item[0] ? 'border-air-blue bg-air-blue text-white' : 'border-border bg-card text-secondary'" @click="filter = item[0]">{{ item[1] }}</button>
        </div>

        <div v-if="loading" class="rounded-2xl border border-border bg-card p-8 text-center text-secondary">Challenges werden geladen …</div>
        <div v-else-if="!visibleChallenges.length" class="rounded-2xl border border-dashed border-border bg-card p-10 text-center"><i class="las la-flag-checkered text-4xl text-air-blue"></i><p class="mt-3 font-bold text-primary">Noch keine Challenges in diesem Bereich.</p></div>

        <div class="grid gap-5 lg:grid-cols-2">
            <article v-for="challenge in visibleChallenges" :key="challenge.id" class="overflow-hidden rounded-[26px] border border-border bg-card shadow-sm">
                <div class="p-5 sm:p-6">
                    <div class="flex items-start justify-between gap-4">
                        <div><div class="flex flex-wrap gap-2"><span class="rounded-full bg-air-blue/10 px-2.5 py-1 text-xs font-black text-air-blue">{{ visibilityLabels[challenge.visibility] }}</span><span class="rounded-full bg-inputBg px-2.5 py-1 text-xs font-bold text-secondary">{{ stateLabels[challenge.state] }}</span></div><h2 class="mt-3 text-xl font-black text-primary">{{ challenge.title }}</h2><p class="mt-1 text-xs text-secondary">von {{ challenge.creator?.name }} · {{ formatDate(challenge.starts_on) }}–{{ formatDate(challenge.ends_on) }}</p></div>
                        <span class="grid h-12 w-12 shrink-0 place-items-center rounded-2xl bg-air-green/10 text-2xl text-air-green"><i class="las la-flag-checkered"></i></span>
                    </div>
                    <p v-if="challenge.description" class="mt-4 line-clamp-3 text-sm leading-6 text-secondary">{{ challenge.description }}</p>
                    <div class="mt-4 rounded-2xl bg-inputBg p-4"><p class="text-xs font-bold uppercase tracking-wide text-secondary">{{ frequencyLabels[challenge.frequency] }}es Ziel</p><p class="mt-1 text-2xl font-black text-primary">{{ challenge.target_value }} {{ challenge.unit }}</p></div>

                    <div v-if="challenge.my_participation?.status === 'accepted'" class="mt-4">
                        <div class="flex items-center justify-between text-xs font-bold text-secondary"><span>Mein Fortschritt</span><span>{{ challenge.progress.completed }}/{{ challenge.progress.total }} · {{ challenge.progress.percentage }}%</span></div>
                        <div class="mt-2 h-2 overflow-hidden rounded-full bg-inputBg"><div class="h-full rounded-full bg-air-green transition-all" :style="{ width: `${challenge.progress.percentage}%` }"></div></div>
                    </div>

                    <div v-if="challenge.my_participation?.status === 'pending'" class="mt-5 flex gap-2"><button class="flex-1 rounded-xl bg-air-green px-4 py-2.5 font-black text-white" @click="action(challenge, 'put', '/invitation', { status: 'accepted' })">Annehmen</button><button class="rounded-xl border border-border px-4 py-2.5 font-bold text-secondary" @click="action(challenge, 'put', '/invitation', { status: 'declined' })">Ablehnen</button></div>
                    <button v-else-if="challenge.can_join" class="mt-5 w-full rounded-xl bg-air-blue px-4 py-2.5 font-black text-white" @click="action(challenge, 'post', '/join')">Teilnehmen</button>

                    <div v-if="challenge.can_checkin" class="mt-5 rounded-2xl border p-4" :class="isDoneToday(challenge) ? 'border-air-green/40 bg-air-green/5' : 'border-border'">
                        <div class="flex items-center justify-between"><p class="font-black text-primary">Heute</p><span v-if="isDoneToday(challenge)" class="font-black text-air-green"><i class="las la-check-circle"></i> Geschafft</span></div>
                        <div class="mt-3 flex gap-2"><input v-model="values[challenge.id]" type="number" min="0" step="0.01" :placeholder="`Wert in ${challenge.unit}`" class="min-w-0 flex-1 rounded-xl border-border bg-card text-sm text-primary" /><button class="rounded-xl bg-air-green px-4 font-black text-white" @click="checkin(challenge, true)"><i class="las la-check"></i></button><button v-if="isDoneToday(challenge)" class="rounded-xl border border-border px-3 text-secondary" title="Haken entfernen" @click="checkin(challenge, false)"><i class="las la-undo"></i></button></div>
                    </div>

                    <div class="mt-5 flex items-center justify-between border-t border-border pt-4"><span class="text-xs font-bold text-secondary"><i class="las la-users"></i> {{ challenge.participants_count }} · <i class="las la-comments"></i> {{ challenge.comments_count }}</span><button class="text-sm font-black text-air-blue" @click="openDetails(challenge)">{{ expanded === challenge.id ? 'Weniger' : 'Details & Kommentare' }}</button></div>
                </div>

                <div v-if="expanded === challenge.id" class="border-t border-border bg-inputBg/40 p-5 sm:p-6">
                    <h3 class="font-black text-primary">Teilnehmer</h3><div class="mt-2 flex flex-wrap gap-2"><span v-for="participant in details[challenge.id]?.participants || challenge.participants" :key="participant.id" class="rounded-full bg-card px-3 py-1.5 text-xs font-bold text-secondary">{{ participant.user?.name }} · {{ participant.status === 'accepted' ? 'dabei' : participant.status === 'pending' ? 'offen' : 'abgelehnt' }}</span></div>
                    <h3 class="mt-5 font-black text-primary">Kommentare</h3>
                    <div class="mt-3 max-h-64 space-y-3 overflow-y-auto"><div v-for="comment in comments[challenge.id] || []" :key="comment.id" class="rounded-2xl bg-card p-3"><div class="flex justify-between gap-3"><p class="text-sm font-black text-primary">{{ comment.user?.name }}</p><span class="text-[11px] text-secondary">{{ formatTime(comment.created_at) }}</span></div><p class="mt-1 text-sm text-secondary">{{ comment.content }}</p></div><p v-if="!(comments[challenge.id] || []).length" class="text-sm text-secondary">Noch keine Kommentare.</p></div>
                    <form v-if="challenge.can_comment" class="mt-3 flex gap-2" @submit.prevent="addComment(challenge)"><input v-model="commentDrafts[challenge.id]" maxlength="1500" placeholder="Motivation oder Kommentar schreiben …" class="min-w-0 flex-1 rounded-xl border-border bg-card text-sm text-primary" /><button class="rounded-xl bg-air-blue px-4 font-black text-white"><i class="las la-paper-plane"></i></button></form>
                    <button v-if="challenge.can_cancel && challenge.status !== 'cancelled'" class="mt-5 text-xs font-bold text-red-600" @click="action(challenge, 'post', '/cancel')">Challenge abbrechen</button>
                </div>
            </article>
        </div>
    </div>
</template>
