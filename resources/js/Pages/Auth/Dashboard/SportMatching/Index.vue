<script setup>
import { computed, ref } from 'vue'
import { Head, router, useForm } from '@inertiajs/vue3'
import AppLayout from '@/Components/Auth/Layouts/AppLayout.vue'

defineOptions({ layout: AppLayout })

const props = defineProps({
    matchings: Object,
    sports: Array,
    teams: Array,
    filters: Object,
    skillLevels: Array,
})

const tab = ref(props.filters?.mode || 'partner')
const showCreate = ref(false)
const filterCity = ref(props.filters?.city || '')
const filterSport = ref(props.filters?.sport_id || '')
const form = useForm({
    mode: tab.value,
    sport_id: '',
    team_id: '',
    title: '',
    description: '',
    city: '',
    country_code: 'DE',
    radius_km: 25,
    starts_at: '',
    participants_needed: 1,
    team_size: '',
    skill_level: 'all',
})
const applicationMessages = ref({})
const selectedTeams = ref({})

const availableTeams = computed(() => props.teams || [])
const switchTab = (mode) => {
    tab.value = mode
    form.mode = mode
    router.get(route('auth.sport-matching.index'), { mode, city: filterCity.value, sport_id: filterSport.value }, { preserveState: true })
}
const search = () => router.get(route('auth.sport-matching.index'), {
    mode: tab.value,
    city: filterCity.value || undefined,
    sport_id: filterSport.value || undefined,
}, { preserveState: true })
const submit = () => form.post(route('auth.sport-matching.store'), {
    preserveScroll: true,
    onSuccess: () => {
        showCreate.value = false
        form.reset('title', 'description', 'starts_at', 'team_id', 'team_size')
    },
})
const apply = (matching) => router.post(route('auth.sport-matching.apply', matching.id), {
    team_id: matching.mode === 'team' ? selectedTeams.value[matching.id] : null,
    message: applicationMessages.value[matching.id] || null,
}, { preserveScroll: true })
const decide = (matching, application, status) => router.put(
    route('auth.sport-matching.applications.update', [matching.id, application.id]),
    { status },
    { preserveScroll: true },
)
const formatDate = (value) => new Intl.DateTimeFormat(undefined, {
    dateStyle: 'medium', timeStyle: 'short',
}).format(new Date(value))
</script>

<template>
    <Head title="Sport-Matching" />
    <div class="mx-auto max-w-7xl space-y-6 p-4 sm:p-6">
            <header class="rounded-2xl border border-border bg-card p-5">
                <div class="flex flex-wrap items-start justify-between gap-4">
                    <div>
                        <p class="text-xs font-black uppercase tracking-wider text-air-blue">Gemeinsam aktiv</p>
                        <h1 class="mt-1 text-3xl font-black text-primary">Sport-Matching</h1>
                        <p class="mt-2 max-w-2xl text-sm text-secondary">Finde Sportpartner an deinem aktuellen Ort oder fordere mit deinem Team ein anderes Team heraus – für jede Sportart und jede Teamgröße.</p>
                    </div>
                    <button class="rounded-xl bg-buttonPrimary px-5 py-3 font-bold text-buttonText" @click="showCreate = !showCreate">
                        {{ showCreate ? 'Schließen' : 'Suche veröffentlichen' }}
                    </button>
                </div>
                <div class="mt-5 grid grid-cols-2 gap-2 rounded-xl bg-inputBg p-1">
                    <button class="rounded-lg px-4 py-3 font-bold" :class="tab === 'partner' ? 'bg-card text-air-blue shadow' : 'text-secondary'" @click="switchTab('partner')">Sportpartner finden</button>
                    <button class="rounded-lg px-4 py-3 font-bold" :class="tab === 'team' ? 'bg-card text-air-blue shadow' : 'text-secondary'" @click="switchTab('team')">Teamgegner finden</button>
                </div>
            </header>

            <form v-if="showCreate" class="grid gap-4 rounded-2xl border border-air-blue/40 bg-card p-5 md:grid-cols-2" @submit.prevent="submit">
                <div class="md:col-span-2"><h2 class="text-xl font-black text-primary">{{ form.mode === 'partner' ? 'Sportpartner-Suche erstellen' : 'Team-Herausforderung erstellen' }}</h2></div>
                <label class="text-sm font-bold text-primary">Sportart
                    <select v-model="form.sport_id" required class="mt-1 w-full rounded-xl border-border bg-inputBg"><option value="">Bitte wählen</option><option v-for="sport in sports" :key="sport.id" :value="sport.id">{{ sport.name }}</option></select>
                </label>
                <label v-if="form.mode === 'team'" class="text-sm font-bold text-primary">Dein Team
                    <select v-model="form.team_id" required class="mt-1 w-full rounded-xl border-border bg-inputBg"><option value="">Bitte wählen</option><option v-for="team in availableTeams" :key="team.id" :value="team.id">{{ team.name }}</option></select>
                </label>
                <label class="text-sm font-bold text-primary">Titel<input v-model="form.title" required maxlength="140" class="mt-1 w-full rounded-xl border-border bg-inputBg" placeholder="z. B. Lauf am Strand von Kenitra" /></label>
                <label class="text-sm font-bold text-primary">Termin<input v-model="form.starts_at" required type="datetime-local" class="mt-1 w-full rounded-xl border-border bg-inputBg" /></label>
                <label class="text-sm font-bold text-primary">Ort<input v-model="form.city" required class="mt-1 w-full rounded-xl border-border bg-inputBg" placeholder="Kenitra" /></label>
                <label class="text-sm font-bold text-primary">Land (ISO)<input v-model="form.country_code" required maxlength="2" class="mt-1 w-full rounded-xl border-border bg-inputBg uppercase" placeholder="MA" /></label>
                <label v-if="form.mode === 'partner'" class="text-sm font-bold text-primary">Gesuchte Personen<input v-model.number="form.participants_needed" type="number" min="1" max="500" class="mt-1 w-full rounded-xl border-border bg-inputBg" /></label>
                <label v-else class="text-sm font-bold text-primary">Personen pro Team<input v-model.number="form.team_size" required type="number" min="1" max="500" class="mt-1 w-full rounded-xl border-border bg-inputBg" placeholder="5, 7, 11 …" /></label>
                <label class="text-sm font-bold text-primary">Niveau
                    <select v-model="form.skill_level" class="mt-1 w-full rounded-xl border-border bg-inputBg"><option v-for="level in skillLevels" :key="level" :value="level">{{ level }}</option></select>
                </label>
                <label class="text-sm font-bold text-primary">Umkreis<input v-model.number="form.radius_km" type="number" min="1" max="500" class="mt-1 w-full rounded-xl border-border bg-inputBg" /></label>
                <label class="text-sm font-bold text-primary md:col-span-2">Beschreibung<textarea v-model="form.description" rows="3" class="mt-1 w-full rounded-xl border-border bg-inputBg" /></label>
                <div v-if="Object.keys(form.errors).length" class="md:col-span-2 rounded-xl bg-red-500/10 p-3 text-sm text-red-400">{{ Object.values(form.errors)[0] }}</div>
                <button :disabled="form.processing" class="rounded-xl bg-buttonPrimary px-5 py-3 font-bold text-buttonText md:col-span-2">Veröffentlichen</button>
            </form>

            <section class="grid gap-3 rounded-2xl border border-border bg-card p-4 sm:grid-cols-[1fr_1fr_auto]">
                <input v-model="filterCity" class="rounded-xl border-border bg-inputBg" placeholder="Ort, z. B. Kenitra" />
                <select v-model="filterSport" class="rounded-xl border-border bg-inputBg"><option value="">Alle Sportarten</option><option v-for="sport in sports" :key="sport.id" :value="sport.id">{{ sport.name }}</option></select>
                <button class="rounded-xl border border-air-blue px-5 py-2 font-bold text-air-blue" @click="search">Suchen</button>
            </section>

            <section v-if="matchings.data?.length" class="grid gap-4 lg:grid-cols-2">
                <article v-for="matching in matchings.data" :key="matching.id" class="rounded-2xl border border-border bg-card p-5">
                    <div class="flex items-start justify-between gap-3"><div><span class="rounded-full bg-air-blue/15 px-3 py-1 text-xs font-black text-air-blue">{{ matching.sport?.name }}</span><h2 class="mt-3 text-xl font-black text-primary">{{ matching.title }}</h2></div><span class="text-xs font-bold text-secondary">{{ matching.mode === 'team' ? `${matching.team_size} vs. ${matching.team_size}` : `${matching.participants_needed} gesucht` }}</span></div>
                    <p class="mt-2 text-sm text-secondary">{{ matching.description }}</p>
                    <div class="mt-4 grid grid-cols-2 gap-2 text-sm"><span>📍 {{ matching.city }}, {{ matching.country_code }}</span><span>🗓 {{ formatDate(matching.starts_at) }}</span><span>🎯 {{ matching.skill_level }}</span><span>📡 {{ matching.radius_km }} km</span></div>
                    <p class="mt-3 text-xs text-secondary">Von {{ matching.owner?.name }}<template v-if="matching.team"> · {{ matching.team.name }}</template></p>

                    <div v-if="matching.mine" class="mt-4 space-y-3 border-t border-border pt-4">
                        <div v-for="application in matching.applications" :key="application.id" class="flex flex-wrap items-center justify-between gap-2 rounded-xl bg-inputBg p-3">
                            <div><strong>{{ application.team?.name || application.user?.name }}</strong><p class="text-xs text-secondary">{{ application.message }}</p></div>
                            <div v-if="application.status === 'pending'" class="flex gap-2"><button class="text-sm font-bold text-emerald-400" @click="decide(matching, application, 'accepted')">Annehmen</button><button class="text-sm font-bold text-red-400" @click="decide(matching, application, 'declined')">Ablehnen</button></div><span v-else class="text-xs font-bold">{{ application.status }}</span>
                        </div>
                        <button class="text-sm font-bold text-red-400" @click="router.post(route('auth.sport-matching.cancel', matching.id), {}, { preserveScroll: true })">Suche schließen</button>
                    </div>
                    <div v-else-if="!matching.my_application" class="mt-4 space-y-2 border-t border-border pt-4">
                        <select v-if="matching.mode === 'team'" v-model="selectedTeams[matching.id]" class="w-full rounded-xl border-border bg-inputBg"><option value="">Team auswählen</option><option v-for="team in availableTeams" :key="team.id" :value="team.id">{{ team.name }}</option></select>
                        <textarea v-model="applicationMessages[matching.id]" rows="2" class="w-full rounded-xl border-border bg-inputBg" placeholder="Kurze Nachricht (optional)" />
                        <button class="w-full rounded-xl bg-buttonPrimary px-4 py-2 font-bold text-buttonText" @click="apply(matching)">Interesse senden</button>
                    </div>
                    <p v-else class="mt-4 rounded-xl bg-air-blue/10 p-3 text-sm font-bold text-air-blue">Anfrage: {{ matching.my_application }}</p>
                </article>
            </section>
            <div v-else class="rounded-2xl border border-dashed border-border p-10 text-center text-secondary">Noch keine passenden Suchen. Veröffentliche die erste.</div>
    </div>
</template>
