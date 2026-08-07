<script setup>
import { computed, ref } from 'vue'
import { Head, router, useForm } from '@inertiajs/vue3'
import AppLayout from '@/Components/Auth/Layouts/AppLayout.vue'
import SearchableSelect from '@/Components/SearchableSelect.vue'
import SportMatchingSwipeDeck from '@/Components/SportMatching/SportMatchingSwipeDeck.vue'

defineOptions({ layout: AppLayout })

const props = defineProps({
    matchings: Object,
    sports: Array,
    teams: Array,
    filters: Object,
    skillLevels: Array,
})

const tab = ref(props.filters?.mode || 'partner')
const view = ref(props.filters?.view || 'swipe')
const showCreate = ref(false)
const filterLocation = ref(props.filters?.location || props.filters?.city || '')
const filterSport = ref(props.filters?.sport_id || '')
const filterRadius = ref(props.filters?.radius_km || '')
const filterSkill = ref(props.filters?.skill_level || '')
const form = useForm({
    mode: tab.value,
    sport_id: '',
    team_id: '',
    title: '',
    description: '',
    city: '',
    postal_code: '',
    location_name: '',
    address: '',
    country_code: 'DE',
    radius_km: 25,
    start_date: '',
    start_time: '',
    starts_at: '',
    end_date: '',
    end_time: '',
    ends_at: null,
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
    router.get(route('auth.sport-matching.index'), { mode, location: filterLocation.value, sport_id: filterSport.value, radius_km: filterRadius.value, skill_level: filterSkill.value, view: view.value }, { preserveState: true })
}
const search = () => router.get(route('auth.sport-matching.index'), {
    mode: tab.value,
    location: filterLocation.value || undefined,
    sport_id: filterSport.value || undefined,
    radius_km: filterRadius.value || undefined,
    skill_level: filterSkill.value || undefined,
    view: view.value,
}, { preserveState: true })
const switchView = (nextView) => {
    view.value = nextView
    router.get(route('auth.sport-matching.index'), {
        mode: tab.value,
        location: filterLocation.value || undefined,
        sport_id: filterSport.value || undefined,
        radius_km: filterRadius.value || undefined,
        skill_level: filterSkill.value || undefined,
        view: nextView,
    }, { preserveState: true, preserveScroll: true })
}
const submit = () => {
    form.starts_at = form.start_date && form.start_time
        ? `${form.start_date}T${form.start_time}`
        : ''
    form.ends_at = form.end_date && form.end_time
        ? `${form.end_date}T${form.end_time}`
        : null

    form.post(route('auth.sport-matching.store'), {
    preserveScroll: true,
    onSuccess: () => {
        showCreate.value = false
        form.reset('title', 'description', 'postal_code', 'location_name', 'address', 'start_date', 'start_time', 'starts_at', 'end_date', 'end_time', 'ends_at', 'team_id', 'team_size')
    },
    })
}
const apply = (matching) => router.post(route('auth.sport-matching.apply', matching.id), {
    team_id: matching.mode === 'team' ? selectedTeams.value[matching.id] : null,
    message: applicationMessages.value[matching.id] || null,
}, { preserveScroll: true })
const decide = (matching, application, status) => router.put(
    route('auth.sport-matching.applications.update', [matching.id, application.id]),
    { status },
    { preserveScroll: true },
)
const formatDateTime = (value) => new Intl.DateTimeFormat(undefined, {
    dateStyle: 'medium',
    timeStyle: 'short',
    hour12: false,
}).format(new Date(value))
const applicationStatusLabel = (status) => ({
    pending: 'Anfrage ausstehend',
    accepted: 'Anfrage angenommen',
    declined: 'Anfrage abgelehnt',
})[status] || status
const goToPage = (pageNumber) => router.get(route('auth.sport-matching.index'), {
    mode: tab.value,
    location: filterLocation.value || undefined,
    sport_id: filterSport.value || undefined,
    radius_km: filterRadius.value || undefined,
    skill_level: filterSkill.value || undefined,
    view: view.value,
    page: pageNumber,
}, { preserveState: true, preserveScroll: true })
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
                <div class="mt-3 flex flex-wrap gap-2 rounded-xl border border-border bg-inputBg/60 p-1">
                    <button class="flex-1 rounded-lg px-4 py-2.5 text-sm font-bold transition" :class="view === 'swipe' ? 'bg-air-blue text-white shadow' : 'text-secondary hover:text-primary'" @click="switchView('swipe')"><i class="las la-bolt me-1"></i> Entdecken</button>
                    <button class="flex-1 rounded-lg px-4 py-2.5 text-sm font-bold transition" :class="view === 'list' ? 'bg-card text-primary shadow' : 'text-secondary hover:text-primary'" @click="switchView('list')"><i class="las la-list me-1"></i> Listenansicht</button>
                </div>
            </header>

            <form v-if="showCreate" class="grid gap-4 rounded-2xl border border-air-blue/40 bg-card p-5 md:grid-cols-2" @submit.prevent="submit">
                <div class="md:col-span-2"><h2 class="text-xl font-black text-primary">{{ form.mode === 'partner' ? 'Sportpartner-Suche erstellen' : 'Team-Herausforderung erstellen' }}</h2></div>
                <label class="text-sm font-bold text-primary">Sportart
                    <SearchableSelect
                        v-model="form.sport_id"
                        class="mt-1"
                        :options="sports"
                        value-key="id"
                        :allow-custom="false"
                        placeholder="Sportart suchen"
                        empty-text="Keine Sportart gefunden."
                    />
                </label>
                <label v-if="form.mode === 'team'" class="text-sm font-bold text-primary">Dein Team
                    <select v-model="form.team_id" required class="mt-1 w-full rounded-xl border-border bg-inputBg"><option value="">Bitte wählen</option><option v-for="team in availableTeams" :key="team.id" :value="team.id">{{ team.name }}</option></select>
                </label>
                <label class="text-sm font-bold text-primary">Titel <span class="font-normal text-secondary">(optional)</span><input v-model="form.title" maxlength="140" class="mt-1 w-full rounded-xl border-border bg-inputBg" placeholder="z. B. Lockerer Lauf am Strand" /></label>
                <div class="grid grid-cols-2 gap-2">
                    <label class="text-sm font-bold text-primary">Datum<input v-model="form.start_date" required type="date" class="mt-1 w-full rounded-xl border-border bg-inputBg" /></label>
                    <label class="text-sm font-bold text-primary">Uhrzeit<input v-model="form.start_time" required type="time" class="mt-1 w-full rounded-xl border-border bg-inputBg" /></label>
                    <label class="text-sm font-bold text-primary">Enddatum <span class="font-normal text-secondary">(optional)</span><input v-model="form.end_date" type="date" class="mt-1 w-full rounded-xl border-border bg-inputBg" /></label>
                    <label class="text-sm font-bold text-primary">Endzeit <span class="font-normal text-secondary">(optional)</span><input v-model="form.end_time" type="time" class="mt-1 w-full rounded-xl border-border bg-inputBg" /></label>
                    <p v-if="form.errors.starts_at" class="col-span-2 text-xs font-normal text-red-400">{{ form.errors.starts_at }}</p>
                    <p v-if="form.errors.ends_at" class="col-span-2 text-xs font-normal text-red-400">{{ form.errors.ends_at }}</p>
                </div>
                <label class="text-sm font-bold text-primary">Stadt / Ort<input v-model="form.city" required class="mt-1 w-full rounded-xl border-border bg-inputBg" placeholder="z. B. Kenitra" /></label>
                <label class="text-sm font-bold text-primary">PLZ <span class="font-normal text-secondary">(optional)</span><input v-model="form.postal_code" maxlength="20" class="mt-1 w-full rounded-xl border-border bg-inputBg" placeholder="z. B. 14000" /></label>
                <label class="text-sm font-bold text-primary">Sportstätte / Treffpunkt <span class="font-normal text-secondary">(optional)</span><input v-model="form.location_name" class="mt-1 w-full rounded-xl border-border bg-inputBg" placeholder="z. B. Stadtpark, Court 2" /></label>
                <label class="text-sm font-bold text-primary">Adresse <span class="font-normal text-secondary">(optional)</span><input v-model="form.address" class="mt-1 w-full rounded-xl border-border bg-inputBg" placeholder="Straße und Hausnummer" /></label>
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

            <section class="grid gap-3 rounded-2xl border border-border bg-card p-4 sm:grid-cols-[1fr_1fr_1fr_auto]">
                <input v-model="filterLocation" class="rounded-xl border-border bg-inputBg" placeholder="Stadt, PLZ oder Sportstätte" />
                <SearchableSelect
                    v-model="filterSport"
                    :options="sports"
                    value-key="id"
                    :allow-custom="false"
                    placeholder="Alle Sportarten / suchen"
                    empty-text="Keine Sportart gefunden."
                />
                <select v-model="filterRadius" class="rounded-xl border-border bg-inputBg">
                    <option value="">Beliebiger Umkreis</option>
                    <option v-for="radius in [5, 10, 25, 50, 100, 250, 500]" :key="radius" :value="radius">Bis {{ radius }} km</option>
                </select>
                <select v-model="filterSkill" class="rounded-xl border-border bg-inputBg">
                    <option value="">Alle Niveaus</option>
                    <option v-for="level in skillLevels" :key="level" :value="level">{{ level === 'all' ? 'Alle Niveaus' : level }}</option>
                </select>
                <button class="rounded-xl border border-air-blue px-5 py-2 font-bold text-air-blue" @click="search">Suchen</button>
            </section>

            <SportMatchingSwipeDeck v-if="view === 'swipe'" :items="matchings.data || []" :teams="availableTeams" />

            <section v-else-if="matchings.data?.length" class="grid gap-4 lg:grid-cols-2">
                <article v-for="matching in matchings.data" :key="matching.id" class="rounded-2xl border border-border bg-card p-5">
                    <div class="flex items-start justify-between gap-3"><div><span class="rounded-full bg-air-blue/15 px-3 py-1 text-xs font-black text-air-blue">{{ matching.sport?.name }}</span><h2 class="mt-3 text-xl font-black text-primary">{{ matching.title }}</h2></div><span class="text-xs font-bold text-secondary">{{ matching.mode === 'team' ? `${matching.team_size} vs. ${matching.team_size}` : `${matching.participants_needed} gesucht` }}</span></div>
                    <p class="mt-2 text-sm text-secondary">{{ matching.description }}</p>
                    <div class="mt-4 grid grid-cols-2 gap-2 text-sm"><span>📍 {{ matching.location_name ? `${matching.location_name} · ${matching.city}` : `${matching.city}, ${matching.country_code}` }}</span><span>🗓 {{ formatDateTime(matching.starts_at) }} Uhr</span><span v-if="matching.postal_code" class="text-secondary">PLZ: {{ matching.postal_code }}</span><span v-if="matching.address" class="col-span-2 text-secondary">Adresse: {{ matching.address }}</span><span>🎯 {{ matching.skill_level }}</span><span>📡 {{ matching.radius_km }} km</span></div>
                    <p class="mt-3 text-xs text-secondary">Von {{ matching.owner?.name }}<template v-if="matching.team"> · {{ matching.team.name }}</template></p>

                    <div v-if="matching.mine" class="mt-4 space-y-3 border-t border-border pt-4">
                        <div v-for="application in matching.applications" :key="application.id" class="flex flex-wrap items-center justify-between gap-2 rounded-xl bg-inputBg p-3">
                            <div><strong>{{ application.team?.name || application.user?.name }}</strong><p class="text-xs text-secondary">{{ application.message }}</p></div>
                            <div v-if="application.status === 'pending'" class="flex gap-2"><button class="text-sm font-bold text-emerald-400" @click="decide(matching, application, 'accepted')">Annehmen</button><button class="text-sm font-bold text-red-400" @click="decide(matching, application, 'declined')">Ablehnen</button></div><span v-else class="text-xs font-bold" :class="application.status === 'accepted' ? 'text-emerald-400' : 'text-secondary'">{{ applicationStatusLabel(application.status) }}</span>
                        </div>
                        <button class="text-sm font-bold text-red-400" @click="router.post(route('auth.sport-matching.cancel', matching.id), {}, { preserveScroll: true })">Suche schließen</button>
                    </div>
                    <div v-else-if="!matching.my_application" class="mt-4 space-y-2 border-t border-border pt-4">
                        <select v-if="matching.mode === 'team'" v-model="selectedTeams[matching.id]" class="w-full rounded-xl border-border bg-inputBg"><option value="">Team auswählen</option><option v-for="team in availableTeams" :key="team.id" :value="team.id">{{ team.name }}</option></select>
                        <textarea v-model="applicationMessages[matching.id]" rows="2" class="w-full rounded-xl border-border bg-inputBg" placeholder="Kurze Nachricht (optional)" />
                        <button class="w-full rounded-xl bg-buttonPrimary px-4 py-2 font-bold text-buttonText" @click="apply(matching)">Interesse senden</button>
                    </div>
                    <p v-else class="mt-4 rounded-xl p-3 text-sm font-bold" :class="matching.my_application === 'accepted' ? 'bg-emerald-400/10 text-emerald-400' : matching.my_application === 'declined' ? 'bg-red-400/10 text-red-400' : 'bg-air-blue/10 text-air-blue'">{{ applicationStatusLabel(matching.my_application) }}<span v-if="matching.my_application === 'accepted'" class="mt-1 block text-xs font-normal">Der Kontakt wurde bestätigt. Vereinbare die Details anschließend direkt mit der anderen Person.</span></p>
                </article>
            </section>
            <div v-else class="rounded-2xl border border-dashed border-border p-10 text-center text-secondary">Noch keine passenden Suchen. Veröffentliche die erste.</div>
            <div v-if="matchings.meta?.last_page > 1" class="flex items-center justify-between rounded-2xl border border-border bg-card p-3">
                <button class="rounded-xl border border-border px-4 py-2 text-sm font-bold text-secondary disabled:opacity-40" :disabled="matchings.meta.current_page <= 1" @click="goToPage(matchings.meta.current_page - 1)">Zurück</button>
                <span class="text-sm font-bold text-secondary">Seite {{ matchings.meta.current_page }} von {{ matchings.meta.last_page }}</span>
                <button class="rounded-xl border border-border px-4 py-2 text-sm font-bold text-secondary disabled:opacity-40" :disabled="matchings.meta.current_page >= matchings.meta.last_page" @click="goToPage(matchings.meta.current_page + 1)">Weiter</button>
            </div>
    </div>
</template>
