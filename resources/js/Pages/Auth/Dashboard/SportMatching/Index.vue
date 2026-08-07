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
const resultCount = computed(() => Number(props.matchings?.meta?.total || props.matchings?.data?.length || 0))
const modeLabel = computed(() => tab.value === 'team' ? 'Team-Herausforderungen' : 'Sportpartner')
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
    <div class="mx-auto max-w-[1480px] space-y-5 px-4 py-5 sm:px-6 lg:px-8">
        <section class="relative isolate overflow-hidden rounded-[30px] bg-[#081526] px-5 py-6 text-white shadow-[0_22px_70px_rgba(8,21,38,0.18)] sm:px-8 sm:py-8 lg:px-10">
            <div class="pointer-events-none absolute -right-20 -top-32 h-80 w-80 rounded-full bg-cyan-400/20 blur-3xl"></div>
            <div class="pointer-events-none absolute -bottom-40 left-1/3 h-96 w-96 rounded-full bg-emerald-400/10 blur-3xl"></div>
            <div class="relative grid gap-8 lg:grid-cols-[minmax(0,1fr)_360px] lg:items-center">
                <div>
                    <div class="flex flex-wrap items-center gap-2 text-[11px] font-black uppercase tracking-[0.22em] text-cyan-300">
                        <span class="inline-flex items-center gap-2 rounded-full border border-cyan-300/25 bg-cyan-300/10 px-3 py-1.5"><i class="las la-bolt"></i> Gemeinsam aktiv</span>
                        <span class="text-white/45">/</span>
                        <span class="text-white/60">{{ modeLabel }}</span>
                    </div>
                    <h1 class="mt-5 max-w-3xl text-4xl font-black tracking-[-0.04em] sm:text-5xl">Sportkontakte, die wirklich zu dir passen.</h1>
                    <p class="mt-4 max-w-2xl text-sm leading-7 text-slate-300 sm:text-base">Entdecke Menschen und Teams in deiner Nähe. Entscheide intuitiv per Karte oder nutze die präzise Liste für deine nächste Einheit.</p>
                    <div class="mt-7 flex flex-wrap items-center gap-3">
                        <button type="button" class="inline-flex items-center gap-2 rounded-2xl bg-cyan-400 px-5 py-3 text-sm font-black text-[#061423] shadow-lg shadow-cyan-400/20 transition hover:-translate-y-0.5 hover:bg-cyan-300" @click="showCreate = !showCreate">
                            <i :class="showCreate ? 'las la-times' : 'las la-plus'"></i>
                            {{ showCreate ? 'Erstellen schließen' : 'Suche veröffentlichen' }}
                        </button>
                        <span class="inline-flex items-center gap-2 rounded-2xl border border-white/10 bg-white/5 px-4 py-3 text-sm font-bold text-slate-300"><i class="las la-shield-alt text-cyan-300"></i> Privat bis zur Zustimmung</span>
                    </div>
                </div>
                <div class="rounded-[26px] border border-white/10 bg-white/[0.06] p-5 backdrop-blur-sm">
                    <div class="flex items-center justify-between">
                        <p class="text-xs font-black uppercase tracking-[0.18em] text-cyan-300">So funktioniert es</p>
                        <i class="las la-arrows-alt-h text-xl text-white/40"></i>
                    </div>
                    <div class="mt-5 space-y-4">
                        <div class="flex gap-3"><span class="grid h-8 w-8 shrink-0 place-items-center rounded-xl bg-cyan-300/15 text-sm font-black text-cyan-300">01</span><div><p class="text-sm font-black">Filter setzen</p><p class="mt-1 text-xs leading-5 text-slate-400">Sportart, Ort und Niveau auswählen.</p></div></div>
                        <div class="flex gap-3"><span class="grid h-8 w-8 shrink-0 place-items-center rounded-xl bg-cyan-300/15 text-sm font-black text-cyan-300">02</span><div><p class="text-sm font-black">Angebote entdecken</p><p class="mt-1 text-xs leading-5 text-slate-400">Karte nach links oder rechts bewegen.</p></div></div>
                        <div class="flex gap-3"><span class="grid h-8 w-8 shrink-0 place-items-center rounded-xl bg-cyan-300/15 text-sm font-black text-cyan-300">03</span><div><p class="text-sm font-black">Gemeinsam starten</p><p class="mt-1 text-xs leading-5 text-slate-400">Bei Interesse direkt Kontakt aufnehmen.</p></div></div>
                    </div>
                </div>
            </div>
            <div class="relative mt-8 grid grid-cols-2 gap-2 border-t border-white/10 pt-5 sm:grid-cols-4">
                <div><p class="text-2xl font-black">{{ resultCount }}</p><p class="mt-1 text-xs text-slate-400">passende Angebote</p></div>
                <div><p class="text-2xl font-black">{{ sports?.length || 0 }}+</p><p class="mt-1 text-xs text-slate-400">Sportarten</p></div>
                <div><p class="text-2xl font-black">{{ availableTeams.length }}</p><p class="mt-1 text-xs text-slate-400">deine Teams</p></div>
                <div><p class="text-2xl font-black">24/7</p><p class="mt-1 text-xs text-slate-400">offen für Matches</p></div>
            </div>
        </section>

        <section class="rounded-[26px] border border-border bg-card p-3 shadow-[0_12px_35px_rgba(15,23,42,0.05)] sm:p-4">
            <div class="flex flex-col gap-3 xl:flex-row xl:items-center xl:justify-between">
                <div class="inline-grid grid-cols-2 rounded-2xl bg-inputBg p-1">
                    <button type="button" class="inline-flex items-center justify-center gap-2 rounded-xl px-4 py-3 text-sm font-black transition" :class="tab === 'partner' ? 'bg-card text-air-blue shadow-sm' : 'text-secondary hover:text-primary'" @click="switchTab('partner')"><i class="las la-user-friends"></i> Sportpartner</button>
                    <button type="button" class="inline-flex items-center justify-center gap-2 rounded-xl px-4 py-3 text-sm font-black transition" :class="tab === 'team' ? 'bg-card text-air-blue shadow-sm' : 'text-secondary hover:text-primary'" @click="switchTab('team')"><i class="las la-users"></i> Teamgegner</button>
                </div>
                <div class="inline-flex rounded-2xl border border-border bg-inputBg/60 p-1">
                    <button type="button" class="inline-flex items-center gap-2 rounded-xl px-4 py-2.5 text-sm font-black transition" :class="view === 'swipe' ? 'bg-[#081526] text-white shadow-sm' : 'text-secondary hover:text-primary'" @click="switchView('swipe')"><i class="las la-layer-group"></i> Entdecken</button>
                    <button type="button" class="inline-flex items-center gap-2 rounded-xl px-4 py-2.5 text-sm font-black transition" :class="view === 'list' ? 'bg-card text-primary shadow-sm' : 'text-secondary hover:text-primary'" @click="switchView('list')"><i class="las la-list"></i> Liste</button>
                </div>
            </div>
            <div class="mt-4 grid gap-2 lg:grid-cols-[minmax(210px,1.1fr)_minmax(220px,1fr)_170px_170px_auto]">
                <label class="group relative"><span class="sr-only">Ort suchen</span><i class="las la-map-marker absolute left-3 top-1/2 -translate-y-1/2 text-lg text-air-blue"></i><input v-model="filterLocation" class="h-12 w-full rounded-2xl border-border bg-inputBg pl-10 text-sm text-primary placeholder-secondary transition focus:border-air-blue focus:ring-4 focus:ring-air-blue/10" placeholder="Ort, PLZ oder Sportstätte" /></label>
                <SearchableSelect v-model="filterSport" :options="sports" value-key="id" :allow-custom="false" placeholder="Sportart suchen" empty-text="Keine Sportart gefunden." />
                <select v-model="filterRadius" class="h-12 rounded-2xl border-border bg-inputBg px-3 text-sm text-primary focus:border-air-blue focus:ring-4 focus:ring-air-blue/10"><option value="">Beliebiger Umkreis</option><option v-for="radius in [5, 10, 25, 50, 100, 250, 500]" :key="radius" :value="radius">Bis {{ radius }} km</option></select>
                <select v-model="filterSkill" class="h-12 rounded-2xl border-border bg-inputBg px-3 text-sm text-primary focus:border-air-blue focus:ring-4 focus:ring-air-blue/10"><option value="">Alle Niveaus</option><option v-for="level in skillLevels" :key="level" :value="level">{{ level === 'all' ? 'Alle Niveaus' : level }}</option></select>
                <button type="button" class="inline-flex h-12 items-center justify-center gap-2 rounded-2xl bg-air-blue px-5 text-sm font-black text-white shadow-lg shadow-air-blue/15 transition hover:-translate-y-0.5 hover:bg-air-blue/90" @click="search"><i class="las la-search"></i> Suchen</button>
            </div>
        </section>

        <form v-if="showCreate" class="overflow-hidden rounded-[28px] border border-air-blue/25 bg-card shadow-[0_16px_45px_rgba(14,165,233,0.08)]" @submit.prevent="submit">
            <div class="flex flex-col gap-4 border-b border-border bg-gradient-to-r from-air-blue/10 via-transparent to-emerald-400/10 p-5 sm:flex-row sm:items-center sm:justify-between sm:p-7">
                <div class="flex items-center gap-4"><span class="grid h-12 w-12 place-items-center rounded-2xl bg-air-blue/15 text-2xl text-air-blue"><i class="las la-bullhorn"></i></span><div><p class="text-xs font-black uppercase tracking-[0.18em] text-air-blue">Neue Suche</p><h2 class="mt-1 text-2xl font-black text-primary">{{ form.mode === 'partner' ? 'Sportpartner-Suche erstellen' : 'Team-Herausforderung erstellen' }}</h2></div></div>
                <p class="max-w-sm text-sm leading-6 text-secondary">Fülle die wichtigsten Angaben aus. Weitere Details helfen anderen, schneller zu entscheiden.</p>
            </div>
            <div class="grid gap-5 p-5 sm:p-7 md:grid-cols-2 xl:grid-cols-4">
                <label class="text-xs font-black uppercase tracking-wide text-secondary">Sportart<SearchableSelect v-model="form.sport_id" class="mt-2" :options="sports" value-key="id" :allow-custom="false" placeholder="Sportart suchen" empty-text="Keine Sportart gefunden." /></label>
                <label v-if="form.mode === 'team'" class="text-xs font-black uppercase tracking-wide text-secondary">Dein Team<select v-model="form.team_id" required class="mt-2 h-12 w-full rounded-2xl border-border bg-inputBg text-sm font-medium text-primary"><option value="">Bitte wählen</option><option v-for="team in availableTeams" :key="team.id" :value="team.id">{{ team.name }}</option></select></label>
                <label class="text-xs font-black uppercase tracking-wide text-secondary">Titel <span class="font-normal normal-case tracking-normal">(optional)</span><input v-model="form.title" maxlength="140" class="mt-2 h-12 w-full rounded-2xl border-border bg-inputBg text-sm text-primary" placeholder="z. B. Lockerer Lauf am Strand" /></label>
                <label class="text-xs font-black uppercase tracking-wide text-secondary">Stadt / Ort<input v-model="form.city" required class="mt-2 h-12 w-full rounded-2xl border-border bg-inputBg text-sm text-primary" placeholder="z. B. Berlin" /></label>
                <label class="text-xs font-black uppercase tracking-wide text-secondary">Sportstätte <span class="font-normal normal-case tracking-normal">(optional)</span><input v-model="form.location_name" class="mt-2 h-12 w-full rounded-2xl border-border bg-inputBg text-sm text-primary" placeholder="z. B. Stadtpark, Court 2" /></label>
                <div class="rounded-2xl border border-border bg-inputBg/50 p-3 md:col-span-2 xl:col-span-2"><p class="text-xs font-black uppercase tracking-wide text-secondary">Start</p><div class="mt-2 grid grid-cols-2 gap-2"><input v-model="form.start_date" required type="date" class="h-11 rounded-xl border-border bg-card text-sm text-primary" /><input v-model="form.start_time" required type="time" class="h-11 rounded-xl border-border bg-card text-sm text-primary" /></div></div>
                <div class="rounded-2xl border border-border bg-inputBg/50 p-3 md:col-span-2 xl:col-span-2"><p class="text-xs font-black uppercase tracking-wide text-secondary">Ende <span class="font-normal normal-case tracking-normal">(optional)</span></p><div class="mt-2 grid grid-cols-2 gap-2"><input v-model="form.end_date" type="date" class="h-11 rounded-xl border-border bg-card text-sm text-primary" /><input v-model="form.end_time" type="time" class="h-11 rounded-xl border-border bg-card text-sm text-primary" /></div></div>
                <label class="text-xs font-black uppercase tracking-wide text-secondary">PLZ <span class="font-normal normal-case tracking-normal">(optional)</span><input v-model="form.postal_code" maxlength="20" class="mt-2 h-12 w-full rounded-2xl border-border bg-inputBg text-sm text-primary" placeholder="z. B. 10115" /></label>
                <label class="text-xs font-black uppercase tracking-wide text-secondary">Land<input v-model="form.country_code" required maxlength="2" class="mt-2 h-12 w-full rounded-2xl border-border bg-inputBg text-sm uppercase text-primary" placeholder="DE" /></label>
                <label v-if="form.mode === 'partner'" class="text-xs font-black uppercase tracking-wide text-secondary">Gesuchte Personen<input v-model.number="form.participants_needed" type="number" min="1" max="500" class="mt-2 h-12 w-full rounded-2xl border-border bg-inputBg text-sm text-primary" /></label>
                <label v-else class="text-xs font-black uppercase tracking-wide text-secondary">Personen pro Team<input v-model.number="form.team_size" required type="number" min="1" max="500" class="mt-2 h-12 w-full rounded-2xl border-border bg-inputBg text-sm text-primary" placeholder="5, 7, 11 …" /></label>
                <label class="text-xs font-black uppercase tracking-wide text-secondary">Niveau<select v-model="form.skill_level" class="mt-2 h-12 w-full rounded-2xl border-border bg-inputBg text-sm text-primary"><option v-for="level in skillLevels" :key="level" :value="level">{{ level === 'all' ? 'Alle Niveaus' : level }}</option></select></label>
                <label class="text-xs font-black uppercase tracking-wide text-secondary">Umkreis<input v-model.number="form.radius_km" type="number" min="1" max="500" class="mt-2 h-12 w-full rounded-2xl border-border bg-inputBg text-sm text-primary" /></label>
                <label class="text-xs font-black uppercase tracking-wide text-secondary md:col-span-2 xl:col-span-4">Beschreibung <span class="font-normal normal-case tracking-normal">(optional)</span><textarea v-model="form.description" rows="3" class="mt-2 w-full rounded-2xl border-border bg-inputBg text-sm text-primary" placeholder="Was möchtest du gemeinsam machen?" /></label>
                <p v-if="form.errors.starts_at" class="text-xs font-semibold text-red-500 md:col-span-2">{{ form.errors.starts_at }}</p><p v-if="form.errors.ends_at" class="text-xs font-semibold text-red-500 md:col-span-2">{{ form.errors.ends_at }}</p>
                <div v-if="Object.keys(form.errors).length" class="rounded-2xl bg-red-500/10 p-3 text-sm font-semibold text-red-500 md:col-span-2 xl:col-span-4">{{ Object.values(form.errors)[0] }}</div>
                <div class="flex flex-col gap-3 border-t border-border pt-5 md:col-span-2 md:flex-row md:items-center md:justify-end xl:col-span-4"><p class="mr-auto text-xs text-secondary"><i class="las la-lock me-1"></i>Deine Suche bleibt bis zur Kontaktaufnahme geschützt.</p><button type="button" class="rounded-2xl border border-border px-5 py-3 text-sm font-black text-secondary transition hover:bg-inputBg" @click="showCreate = false">Abbrechen</button><button type="submit" :disabled="form.processing" class="rounded-2xl bg-air-blue px-6 py-3 text-sm font-black text-white shadow-lg shadow-air-blue/15 transition hover:bg-air-blue/90 disabled:opacity-50">{{ form.processing ? 'Wird veröffentlicht …' : 'Suche veröffentlichen' }}</button></div>
            </div>
        </form>

        <div class="grid gap-5 xl:grid-cols-[minmax(0,1fr)_280px]">
            <main class="min-w-0">
                <SportMatchingSwipeDeck v-if="view === 'swipe'" :items="matchings.data || []" :teams="availableTeams" />

                <section v-else-if="matchings.data?.length" class="grid gap-4 lg:grid-cols-2">
                    <article v-for="matching in matchings.data" :key="matching.id" class="group overflow-hidden rounded-[26px] border border-border bg-card shadow-[0_12px_35px_rgba(15,23,42,0.05)] transition hover:-translate-y-1 hover:border-air-blue/40 hover:shadow-[0_18px_45px_rgba(14,165,233,0.12)]">
                        <div class="h-1.5 bg-gradient-to-r from-air-blue via-cyan-400 to-emerald-400"></div>
                        <div class="p-5 sm:p-6">
                            <div class="flex items-start justify-between gap-4"><div class="min-w-0"><div class="flex flex-wrap items-center gap-2"><span class="rounded-full bg-air-blue/10 px-3 py-1 text-[11px] font-black uppercase tracking-wide text-air-blue">{{ matching.sport?.name }}</span><span class="rounded-full bg-inputBg px-3 py-1 text-[11px] font-bold text-secondary">{{ matching.mode === 'team' ? 'Team-Match' : 'Sportpartner' }}</span></div><h2 class="mt-4 truncate text-xl font-black text-primary">{{ matching.title }}</h2></div><span class="shrink-0 text-right text-xs font-black text-secondary">{{ matching.mode === 'team' ? `${matching.team_size} vs. ${matching.team_size}` : `${matching.participants_needed} gesucht` }}</span></div>
                            <p v-if="matching.description" class="mt-3 line-clamp-2 text-sm leading-6 text-secondary">{{ matching.description }}</p>
                            <div class="mt-5 grid gap-3 rounded-2xl bg-inputBg/60 p-4 text-sm text-secondary"><span class="flex items-center gap-3"><i class="las la-map-marker text-lg text-air-blue"></i>{{ matching.location_name ? `${matching.location_name} · ${matching.city}` : `${matching.city}, ${matching.country_code}` }}</span><span class="flex items-center gap-3"><i class="las la-calendar text-lg text-air-blue"></i>{{ formatDateTime(matching.starts_at) }} Uhr</span><span class="flex items-center gap-3"><i class="las la-signal text-lg text-air-blue"></i>{{ matching.skill_level === 'all' ? 'Alle Niveaus' : matching.skill_level }} · bis {{ matching.radius_km }} km</span><span v-if="matching.address" class="flex items-center gap-3"><i class="las la-location-arrow text-lg text-air-blue"></i>{{ matching.address }}</span></div>
                            <div class="mt-5 flex items-center gap-3 border-t border-border pt-4"><div class="grid h-10 w-10 shrink-0 place-items-center overflow-hidden rounded-2xl bg-air-blue/10 text-sm font-black text-air-blue"><img v-if="matching.owner?.profile_photo_url" :src="matching.owner.profile_photo_url" :alt="matching.owner.name" class="h-full w-full object-cover"><span v-else>{{ (matching.owner?.name || '?').slice(0, 1).toUpperCase() }}</span></div><div class="min-w-0"><p class="text-[11px] font-bold uppercase tracking-wide text-secondary">Angebot von</p><p class="truncate text-sm font-black text-primary">{{ matching.owner?.name || 'Sport-Community' }}<span v-if="matching.team" class="font-normal text-secondary"> · {{ matching.team.name }}</span></p></div></div>
                            <div v-if="matching.mine" class="mt-5 space-y-3 border-t border-border pt-5"><div v-for="application in matching.applications" :key="application.id" class="flex flex-wrap items-center justify-between gap-3 rounded-2xl border border-border bg-inputBg/60 p-3"><div><strong class="text-sm text-primary">{{ application.team?.name || application.user?.name }}</strong><p v-if="application.message" class="mt-1 text-xs text-secondary">{{ application.message }}</p></div><div v-if="application.status === 'pending'" class="flex gap-2"><button type="button" class="rounded-xl bg-emerald-500/10 px-3 py-2 text-xs font-black text-emerald-600" @click="decide(matching, application, 'accepted')">Annehmen</button><button type="button" class="rounded-xl bg-red-500/10 px-3 py-2 text-xs font-black text-red-500" @click="decide(matching, application, 'declined')">Ablehnen</button></div><span v-else class="text-xs font-black" :class="application.status === 'accepted' ? 'text-emerald-600' : 'text-secondary'">{{ applicationStatusLabel(application.status) }}</span></div><button type="button" class="text-xs font-black text-red-500 hover:underline" @click="router.post(route('auth.sport-matching.cancel', matching.id), {}, { preserveScroll: true })">Suche schließen</button></div>
                            <div v-else-if="!matching.my_application" class="mt-5 space-y-3 border-t border-border pt-5"><select v-if="matching.mode === 'team'" v-model="selectedTeams[matching.id]" class="h-11 w-full rounded-xl border-border bg-inputBg text-sm text-primary"><option value="">Team auswählen</option><option v-for="team in availableTeams" :key="team.id" :value="team.id">{{ team.name }}</option></select><textarea v-model="applicationMessages[matching.id]" rows="2" class="w-full rounded-xl border-border bg-inputBg text-sm text-primary" placeholder="Kurze Nachricht (optional)" /><button type="button" class="w-full rounded-2xl bg-air-blue px-4 py-3 text-sm font-black text-white transition hover:bg-air-blue/90" @click="apply(matching)"><i class="las la-paper-plane me-1"></i> Interesse senden</button></div>
                            <p v-else class="mt-5 rounded-2xl p-4 text-sm font-black" :class="matching.my_application === 'accepted' ? 'bg-emerald-400/10 text-emerald-600' : matching.my_application === 'declined' ? 'bg-red-400/10 text-red-500' : 'bg-air-blue/10 text-air-blue'">{{ applicationStatusLabel(matching.my_application) }}<span v-if="matching.my_application === 'accepted'" class="mt-1 block text-xs font-normal">Der Kontakt wurde bestätigt. Vereinbare die Details anschließend direkt.</span></p>
                        </div>
                    </article>
                </section>
                <div v-else class="rounded-[28px] border border-dashed border-border bg-card p-12 text-center shadow-sm"><div class="mx-auto grid h-16 w-16 place-items-center rounded-3xl bg-air-blue/10 text-3xl text-air-blue"><i class="las la-compass"></i></div><h3 class="mt-5 text-xl font-black text-primary">Noch keine passenden Suchen</h3><p class="mx-auto mt-2 max-w-md text-sm leading-6 text-secondary">Ändere die Filter oder veröffentliche selbst ein Angebot für deine nächste Sporteinheit.</p><button type="button" class="mt-5 rounded-2xl bg-air-blue px-5 py-3 text-sm font-black text-white" @click="showCreate = true">Erste Suche veröffentlichen</button></div>
                <div v-if="matchings.meta?.last_page > 1" class="mt-5 flex items-center justify-between rounded-2xl border border-border bg-card p-3 shadow-sm"><button type="button" class="rounded-xl border border-border px-4 py-2 text-sm font-black text-secondary transition hover:bg-inputBg disabled:opacity-40" :disabled="matchings.meta.current_page <= 1" @click="goToPage(matchings.meta.current_page - 1)"><i class="las la-arrow-left me-1"></i> Zurück</button><span class="text-sm font-black text-secondary">{{ matchings.meta.current_page }} / {{ matchings.meta.last_page }}</span><button type="button" class="rounded-xl border border-border px-4 py-2 text-sm font-black text-secondary transition hover:bg-inputBg disabled:opacity-40" :disabled="matchings.meta.current_page >= matchings.meta.last_page" @click="goToPage(matchings.meta.current_page + 1)">Weiter <i class="las la-arrow-right ms-1"></i></button></div>
            </main>
            <aside class="hidden space-y-4 xl:block">
                <section class="rounded-[26px] border border-border bg-card p-5 shadow-sm"><div class="flex items-center justify-between"><h2 class="text-sm font-black text-primary">Dein Entdecken</h2><i class="las la-sliders-h text-lg text-air-blue"></i></div><p class="mt-2 text-xs leading-5 text-secondary">Deine Auswahl wird nur für die aktuelle Suche verwendet.</p><div class="mt-5 space-y-3"><div class="flex items-center justify-between rounded-2xl bg-inputBg/60 px-3 py-3"><span class="text-xs font-bold text-secondary">Modus</span><span class="text-xs font-black text-primary">{{ modeLabel }}</span></div><div class="flex items-center justify-between rounded-2xl bg-inputBg/60 px-3 py-3"><span class="text-xs font-bold text-secondary">Umkreis</span><span class="text-xs font-black text-primary">{{ filterRadius ? `bis ${filterRadius} km` : 'beliebig' }}</span></div><div class="flex items-center justify-between rounded-2xl bg-inputBg/60 px-3 py-3"><span class="text-xs font-bold text-secondary">Niveau</span><span class="text-xs font-black text-primary">{{ filterSkill || 'alle' }}</span></div></div></section>
                <section class="rounded-[26px] border border-air-blue/20 bg-gradient-to-br from-air-blue/10 to-emerald-400/10 p-5"><div class="grid h-10 w-10 place-items-center rounded-2xl bg-air-blue/15 text-xl text-air-blue"><i class="las la-lightbulb"></i></div><h2 class="mt-4 text-sm font-black text-primary">Tipp für bessere Matches</h2><p class="mt-2 text-xs leading-5 text-secondary">Ein genauer Ort und ein konkreter Startzeitpunkt erhöhen die Chance, dass andere direkt zusagen.</p></section>
            </aside>
        </div>
    </div>
</template>
