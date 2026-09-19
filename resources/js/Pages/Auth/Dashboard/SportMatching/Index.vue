<script setup>
import { computed, ref } from 'vue'
import { Head, router, useForm } from '@inertiajs/vue3'
import { useI18n } from 'vue-i18n'
import AppLayout from '@/Components/Auth/Layouts/AppLayout.vue'
import SearchableSelect from '@/Components/SearchableSelect.vue'
import SportMatchingSwipeDeck from '@/Components/SportMatching/SportMatchingSwipeDeck.vue'

defineOptions({ layout: AppLayout })

const { t, locale } = useI18n()

const props = defineProps({
    matchings: Object,
    sports: Array,
    teams: Array,
    filters: Object,
    skillLevels: Array,
})

const tab = ref(props.filters?.mode || 'partner')
const view = ref(props.filters?.view || 'list')
const showCreate = ref(false)
const showExtraDetails = ref(false)
const filterLocation = ref(props.filters?.location || props.filters?.city || '')
const filterLatitude = ref(props.filters?.latitude || null)
const filterLongitude = ref(props.filters?.longitude || null)
const locationError = ref(false)
const filterSport = ref(props.filters?.sport_id || '')
const filterRadius = ref(props.filters?.radius_km || 25)
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
    own_team_size: '',
    opponent_size_type: 'exact',
    latitude: null,
    longitude: null,
    skill_level: 'all',
})
const applicationMessages = ref({})
const selectedTeams = ref({})
const applicationTeamSizes = ref({})
const teamSizeLabel = (matching) => `${matching.own_team_size || matching.team_size} vs. ${matching.opponent_size_type === 'minimum' ? (locale.value === 'de' ? 'mind. ' : 'min. ') : ''}${matching.team_size}`
const useSearchLocation = () => {
    if (!navigator.geolocation) { locationError.value = true; return }
    navigator.geolocation.getCurrentPosition((position) => {
        filterLatitude.value = position.coords.latitude
        filterLongitude.value = position.coords.longitude
        filterLocation.value = ''
        locationError.value = false
    }, () => { locationError.value = true }, { enableHighAccuracy: false, timeout: 15000 })
}
const useOfferLocation = () => {
    if (!navigator.geolocation) return
    navigator.geolocation.getCurrentPosition((position) => {
        form.latitude = position.coords.latitude
        form.longitude = position.coords.longitude
    }, () => { locationError.value = true }, { enableHighAccuracy: false, timeout: 15000 })
}
const searchCoordinates = () => filterLatitude.value != null && filterLongitude.value != null
    ? { latitude: filterLatitude.value, longitude: filterLongitude.value, radius_km: filterRadius.value || 25 }
    : {}

const availableTeams = computed(() => props.teams || [])
const resultCount = computed(() => Number(props.matchings?.meta?.total || props.matchings?.data?.length || 0))
const ownMatchings = computed(() => (props.matchings?.data || []).filter((matching) => (matching.mine || matching.my_application === 'accepted') && matching.attendance))
const modeLabel = computed(() => t(tab.value === 'team'
    ? 'sport_matching.modes.team_challenges'
    : 'sport_matching.modes.partner'))
const dateLocale = computed(() => ({ de: 'de-DE', en: 'en-US', fr: 'fr-FR', ar: 'ar' })[locale.value] || 'de-DE')
const skillLabel = (level) => t(`sport_matching.skill.${level}`, level)
const formatLocation = (matching) => {
    const locationName = String(matching?.location_name || '').trim()
    const address = String(matching?.address || '').trim()
    const locality = [matching?.postal_code, matching?.city]
        .map((value) => String(value || '').trim())
        .filter(Boolean)
        .join(' ')
    const country = String(matching?.country_code || '').trim()
    const cityAndCountry = [locality, country].filter(Boolean).join(', ')

    return [locationName, address, cityAndCountry].filter(Boolean).join(' · ')
        || t('sport_matching.common.location_open')
}
const switchTab = (mode) => {
    tab.value = mode
    form.mode = mode
    router.get(route('auth.sport-matching.index'), { mode, location: filterLocation.value, sport_id: filterSport.value, ...searchCoordinates(), skill_level: filterSkill.value, view: view.value }, { preserveState: true })
}
const search = () => router.get(route('auth.sport-matching.index'), {
    mode: tab.value,
    location: filterLocation.value || undefined,
    sport_id: filterSport.value || undefined,
    ...searchCoordinates(),
    skill_level: filterSkill.value || undefined,
    view: view.value,
}, { preserveState: true })
const switchView = (nextView) => {
    view.value = nextView
    router.get(route('auth.sport-matching.index'), {
        mode: tab.value,
        location: filterLocation.value || undefined,
        sport_id: filterSport.value || undefined,
        ...searchCoordinates(),
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
        form.reset('title', 'description', 'postal_code', 'location_name', 'address', 'start_date', 'start_time', 'starts_at', 'end_date', 'end_time', 'ends_at', 'team_id', 'team_size', 'own_team_size', 'latitude', 'longitude')
    },
    })
}
const apply = (matching) => router.post(route('auth.sport-matching.apply', matching.id), {
    team_id: matching.mode === 'team' ? selectedTeams.value[matching.id] : null,
    team_size: matching.mode === 'team' ? applicationTeamSizes.value[matching.id] : null,
    message: applicationMessages.value[matching.id] || null,
}, { preserveScroll: true })
const decide = (matching, application, status) => router.put(
    route('auth.sport-matching.applications.update', [matching.id, application.id]),
    { status },
    { preserveScroll: true },
)
const formatDateTime = (value) => new Intl.DateTimeFormat(dateLocale.value, {
    dateStyle: 'medium',
    timeStyle: 'short',
    hour12: false,
}).format(new Date(value))
const applicationStatusLabel = (status) => t(`sport_matching.application_status.${status}`, status)
const attendanceStatusLabel = (status) => t(
    `sport_matching.attendance_status.${status || 'pending'}`,
    status || t('sport_matching.attendance_status.pending'),
)
const attendanceStatusClass = (status) => ({
    pending: 'bg-amber-400/10 text-amber-600',
    confirmed: 'bg-air-blue/10 text-air-blue',
    checked_in: 'bg-emerald-400/10 text-emerald-600',
    cancelled: 'bg-inputBg text-secondary',
    no_show: 'bg-red-400/10 text-red-500',
})[status] || 'bg-inputBg text-secondary'
const updateAttendance = (matching, action) => router.put(
    route('auth.sport-matching.attendance.update', matching.id),
    { action },
    { preserveScroll: true },
)
const reportNoShow = (matching, targetUserId) => {
    if (!window.confirm(t('sport_matching.attendance.report_confirm'))) return
    router.post(
        route('auth.sport-matching.attendance.no-show', matching.id),
        { target_user_id: targetUserId },
        { preserveScroll: true },
    )
}
const goToPage = (pageNumber) => router.get(route('auth.sport-matching.index'), {
    mode: tab.value,
    location: filterLocation.value || undefined,
    sport_id: filterSport.value || undefined,
    ...searchCoordinates(),
    skill_level: filterSkill.value || undefined,
    view: view.value,
    page: pageNumber,
}, { preserveState: true, preserveScroll: true })
</script>

<template>
    <Head :title="t('sport_matching.title')" />
    <div class="mx-auto max-w-[1480px] space-y-5 px-4 py-5 sm:px-6 lg:px-8">
        <section class="relative isolate overflow-hidden rounded-[30px] border border-border bg-gradient-to-br from-card via-muted/45 to-card px-5 py-6 text-primary shadow-[0_22px_70px_rgba(15,23,42,0.10)] sm:px-8 sm:py-8 lg:px-10">
            <div class="pointer-events-none absolute -right-20 -top-32 h-80 w-80 rounded-full bg-air-blue/20 blur-3xl"></div>
            <div class="pointer-events-none absolute -bottom-40 left-1/3 h-96 w-96 rounded-full bg-air-green/10 blur-3xl"></div>
            <div class="relative grid gap-8 lg:grid-cols-[minmax(0,1fr)_360px] lg:items-center">
                <div>
                    <div class="flex flex-wrap items-center gap-2 text-[11px] font-black uppercase tracking-[0.22em] text-air-blue">
                        <span class="inline-flex items-center gap-2 rounded-full border border-air-blue/25 bg-air-blue/10 px-3 py-1.5"><i class="las la-bolt"></i> {{ t('sport_matching.eyebrow') }}</span>
                        <span class="text-secondary/60">/</span>
                        <span class="text-secondary">{{ modeLabel }}</span>
                    </div>
                    <h1 class="mt-5 max-w-3xl text-4xl font-black tracking-[-0.04em] sm:text-5xl">{{ t('sport_matching.hero_title') }}</h1>
                    <p class="mt-4 max-w-2xl text-sm leading-7 text-secondary sm:text-base">{{ t('sport_matching.hero_description') }}</p>
                    <div class="mt-7 flex flex-wrap items-center gap-3">
                        <button type="button" class="inline-flex items-center gap-2 rounded-2xl bg-air-blue px-5 py-3 text-sm font-black text-buttonTextPrimary shadow-lg shadow-air-blue/20 transition hover:-translate-y-0.5 hover:bg-air-blue/90" @click="showCreate = !showCreate">
                            <i :class="showCreate ? 'las la-times' : 'las la-plus'"></i>
                            {{ showCreate ? t('sport_matching.close_create') : t('sport_matching.create') }}
                        </button>
                        <span class="inline-flex items-center gap-2 rounded-2xl border border-border bg-inputBg px-4 py-3 text-sm font-bold text-secondary"><i class="las la-shield-alt text-air-blue"></i> {{ t('sport_matching.private_until_consent') }}</span>
                    </div>
                </div>
                <div class="rounded-[26px] border border-border bg-inputBg/70 p-5 backdrop-blur-sm">
                    <div class="flex items-center justify-between">
                    <p class="text-xs font-black uppercase tracking-[0.18em] text-air-blue">{{ t('sport_matching.how.title') }}</p>
                        <i class="las la-arrows-alt-h text-xl text-secondary"></i>
                    </div>
                    <div class="mt-5 space-y-4">
                        <div class="flex gap-3"><span class="grid h-8 w-8 shrink-0 place-items-center rounded-xl bg-air-blue/15 text-sm font-black text-air-blue">01</span><div><p class="text-sm font-black">{{ t('sport_matching.how.filter_title') }}</p><p class="mt-1 text-xs leading-5 text-secondary">{{ t('sport_matching.how.filter_text') }}</p></div></div>
                        <div class="flex gap-3"><span class="grid h-8 w-8 shrink-0 place-items-center rounded-xl bg-air-blue/15 text-sm font-black text-air-blue">02</span><div><p class="text-sm font-black">{{ t('sport_matching.how.discover_title') }}</p><p class="mt-1 text-xs leading-5 text-secondary">{{ t('sport_matching.how.discover_text') }}</p></div></div>
                        <div class="flex gap-3"><span class="grid h-8 w-8 shrink-0 place-items-center rounded-xl bg-air-blue/15 text-sm font-black text-air-blue">03</span><div><p class="text-sm font-black">{{ t('sport_matching.how.start_title') }}</p><p class="mt-1 text-xs leading-5 text-secondary">{{ t('sport_matching.how.start_text') }}</p></div></div>
                    </div>
                </div>
            </div>
            <div class="relative mt-8 grid grid-cols-2 gap-2 border-t border-border pt-5 sm:grid-cols-4">
                <div><p class="text-2xl font-black text-air-blue">{{ resultCount }}</p><p class="mt-1 text-xs text-secondary">{{ t('sport_matching.stats.offers') }}</p></div>
                <div><p class="text-2xl font-black text-air-blue">{{ sports?.length || 0 }}+</p><p class="mt-1 text-xs text-secondary">{{ t('sport_matching.stats.sports') }}</p></div>
                <div><p class="text-2xl font-black text-air-blue">{{ availableTeams.length }}</p><p class="mt-1 text-xs text-secondary">{{ t('sport_matching.stats.teams') }}</p></div>
                <div><p class="text-2xl font-black text-primary">24/7</p><p class="mt-1 text-xs text-secondary">{{ t('sport_matching.stats.always_open') }}</p></div>
            </div>
        </section>

        <section class="rounded-[26px] border border-border bg-card p-3 shadow-[0_12px_35px_rgba(15,23,42,0.05)] sm:p-4">
            <div class="flex flex-col gap-3 xl:flex-row xl:items-center xl:justify-between">
                <div role="tablist" class="inline-grid grid-cols-2 rounded-2xl bg-inputBg p-1">
                    <button type="button" role="tab" :aria-selected="tab === 'partner'" class="inline-flex items-center justify-center gap-2 rounded-xl px-4 py-3 text-sm font-black transition" :class="tab === 'partner' ? 'bg-card text-air-blue shadow-sm' : 'text-secondary hover:text-primary'" @click="switchTab('partner')"><i class="las la-user-friends"></i> {{ t('sport_matching.modes.partner') }}</button>
                    <button type="button" role="tab" :aria-selected="tab === 'team'" class="inline-flex items-center justify-center gap-2 rounded-xl px-4 py-3 text-sm font-black transition" :class="tab === 'team' ? 'bg-card text-air-blue shadow-sm' : 'text-secondary hover:text-primary'" @click="switchTab('team')"><i class="las la-users"></i> {{ t('sport_matching.modes.team_opponents') }}</button>
                </div>
                <div role="tablist" class="inline-flex rounded-2xl border border-border bg-inputBg/60 p-1">
                    <button type="button" role="tab" :aria-selected="view === 'swipe'" class="inline-flex items-center gap-2 rounded-xl px-4 py-2.5 text-sm font-black transition" :class="view === 'swipe' ? 'bg-primary text-buttonTextPrimary shadow-sm' : 'text-secondary hover:text-primary'" @click="switchView('swipe')"><i class="las la-layer-group"></i> {{ t('sport_matching.views.discover') }}</button>
                    <button type="button" role="tab" :aria-selected="view === 'list'" class="inline-flex items-center gap-2 rounded-xl px-4 py-2.5 text-sm font-black transition" :class="view === 'list' ? 'bg-card text-primary shadow-sm' : 'text-secondary hover:text-primary'" @click="switchView('list')"><i class="las la-list"></i> {{ t('sport_matching.views.list') }}</button>
                </div>
            </div>
            <div class="mt-4 grid gap-2 lg:grid-cols-[minmax(210px,1.1fr)_minmax(220px,1fr)_170px_170px_auto]">
                <label class="group relative"><span class="sr-only">{{ t('sport_matching.filters.location_label') }}</span><i class="las la-map-marker absolute start-3 top-1/2 -translate-y-1/2 text-lg text-air-blue"></i><input v-model="filterLocation" @input="filterLatitude = null; filterLongitude = null" class="h-12 w-full rounded-2xl border-border bg-inputBg ps-10 text-sm text-primary placeholder-secondary transition focus:border-air-blue focus:ring-4 focus:ring-air-blue/10" :placeholder="t('sport_matching.filters.location_placeholder')" /></label>
                <SearchableSelect v-model="filterSport" input-id="sport-matching-filter-sport" :aria-label="t('sport_matching.form.sport')" :options="sports" value-key="id" :allow-custom="false" :placeholder="t('sport_matching.filters.sport_placeholder')" :empty-text="t('sport_matching.filters.sport_empty')" />
                <button type="button" class="h-12 rounded-2xl border border-border px-3 text-sm font-bold text-primary" @click="useSearchLocation">{{ filterLatitude == null ? t('sport_matching.use_location') : t('sport_matching.location_selected') }}</button>
                <select v-if="filterLatitude != null" id="sport-matching-filter-radius" v-model="filterRadius" :aria-label="t('sport_matching.form.radius')" class="h-12 rounded-2xl border-border bg-inputBg px-3 text-sm text-primary"><option v-for="radius in [5, 10, 25, 50, 100, 250, 500]" :key="radius" :value="radius">{{ t('sport_matching.filters.radius_option', { radius }) }}</option></select>
                <label for="sport-matching-filter-skill" class="sr-only">{{ t('sport_matching.form.level') }}</label>
                <select id="sport-matching-filter-skill" v-model="filterSkill" class="h-12 rounded-2xl border-border bg-inputBg px-3 text-sm text-primary focus:border-air-blue focus:ring-4 focus:ring-air-blue/10"><option value="">{{ t('sport_matching.filters.skill_all') }}</option><option v-for="level in skillLevels" :key="level" :value="level">{{ skillLabel(level) }}</option></select>
                <button type="button" class="inline-flex h-12 items-center justify-center gap-2 rounded-2xl bg-air-blue px-5 text-sm font-black text-buttonTextPrimary shadow-lg shadow-air-blue/15 transition hover:-translate-y-0.5 hover:bg-air-blue/90" @click="search"><i class="las la-search"></i> {{ t('sport_matching.filters.search') }}</button>
            </div>
            <p v-if="locationError" class="mt-2 text-sm text-red-500">{{ t('sport_matching.location_unavailable') }}</p>
        </section>

        <form v-if="showCreate" class="overflow-hidden rounded-[28px] border border-air-blue/25 bg-card shadow-[0_16px_45px_rgba(0,0,0,0.06)]" @submit.prevent="submit">
            <div class="flex flex-col gap-4 border-b border-border bg-gradient-to-r from-air-blue/10 via-transparent to-air-green/10 p-5 sm:flex-row sm:items-center sm:justify-between sm:p-7">
                <div class="flex items-center gap-4"><span class="grid h-12 w-12 place-items-center rounded-2xl bg-air-blue/15 text-2xl text-air-blue"><i class="las la-bullhorn"></i></span><div><p class="text-xs font-black uppercase tracking-[0.18em] text-air-blue">{{ t('sport_matching.form.eyebrow') }}</p><h2 class="mt-1 text-2xl font-black text-primary">{{ t(form.mode === 'partner' ? 'sport_matching.form.partner_title' : 'sport_matching.form.team_title') }}</h2></div></div>
                <p class="max-w-sm text-sm leading-6 text-secondary">{{ t('sport_matching.form.intro') }}</p>
            </div>
            <div class="px-5 pt-4 sm:px-7"><button type="button" class="text-sm font-black text-air-blue" @click="showExtraDetails = !showExtraDetails">{{ t('sport_matching.more_details') }} {{ showExtraDetails ? '−' : '+' }}</button></div>
            <div class="grid gap-5 p-5 sm:p-7 md:grid-cols-2 xl:grid-cols-4">
                <label class="text-xs font-black uppercase tracking-wide text-secondary">{{ t('sport_matching.form.sport') }}<SearchableSelect v-model="form.sport_id" class="mt-2" :options="sports" value-key="id" :allow-custom="false" :placeholder="t('sport_matching.filters.sport_placeholder')" :empty-text="t('sport_matching.filters.sport_empty')" /></label>
                <label v-if="form.mode === 'team'" class="text-xs font-black uppercase tracking-wide text-secondary">{{ t('sport_matching.form.own_team') }}<select v-model="form.team_id" required class="mt-2 h-12 w-full rounded-2xl border-border bg-inputBg text-sm font-medium text-primary"><option value="">{{ t('sport_matching.form.choose') }}</option><option v-for="team in availableTeams" :key="team.id" :value="team.id">{{ team.name }}</option></select></label>
                <label v-if="showExtraDetails" class="text-xs font-black uppercase tracking-wide text-secondary">{{ t('sport_matching.form.title') }} <span class="font-normal normal-case tracking-normal">{{ t('sport_matching.form.optional') }}</span><input v-model="form.title" maxlength="140" class="mt-2 h-12 w-full rounded-2xl border-border bg-inputBg text-sm text-primary" :placeholder="t('sport_matching.form.title_placeholder')" /></label>
                <label class="text-xs font-black uppercase tracking-wide text-secondary">{{ t('sport_matching.form.city') }}<input v-model="form.city" @input="form.latitude = null; form.longitude = null" required class="mt-2 h-12 w-full rounded-2xl border-border bg-inputBg text-sm text-primary" :placeholder="t('sport_matching.form.city_placeholder')" /></label>
                <button type="button" class="rounded-2xl border border-border px-3 py-2 text-sm font-bold text-primary" @click="useOfferLocation">{{ form.latitude == null ? t('sport_matching.use_meeting_location') : t('sport_matching.location_selected') }}</button>
                <label v-if="showExtraDetails" class="text-xs font-black uppercase tracking-wide text-secondary">{{ t('sport_matching.form.venue') }} <span class="font-normal normal-case tracking-normal">{{ t('sport_matching.form.optional') }}</span><input v-model="form.location_name" class="mt-2 h-12 w-full rounded-2xl border-border bg-inputBg text-sm text-primary" :placeholder="t('sport_matching.form.venue_placeholder')" /></label>
                <div class="rounded-2xl border border-border bg-inputBg/50 p-3 md:col-span-2 xl:col-span-2"><p class="text-xs font-black uppercase tracking-wide text-secondary">{{ t('sport_matching.form.start') }}</p><div class="mt-2 grid grid-cols-2 gap-2"><input v-model="form.start_date" required type="date" class="h-11 rounded-xl border-border bg-card text-sm text-primary" /><input v-model="form.start_time" required type="time" class="h-11 rounded-xl border-border bg-card text-sm text-primary" /></div></div>
                <div v-if="showExtraDetails" class="rounded-2xl border border-border bg-inputBg/50 p-3 md:col-span-2 xl:col-span-2"><p class="text-xs font-black uppercase tracking-wide text-secondary">{{ t('sport_matching.form.end') }} <span class="font-normal normal-case tracking-normal">{{ t('sport_matching.form.optional') }}</span></p><div class="mt-2 grid grid-cols-2 gap-2"><input v-model="form.end_date" type="date" class="h-11 rounded-xl border-border bg-card text-sm text-primary" /><input v-model="form.end_time" type="time" class="h-11 rounded-xl border-border bg-card text-sm text-primary" /></div></div>
                <label v-if="showExtraDetails" class="text-xs font-black uppercase tracking-wide text-secondary">{{ t('sport_matching.form.postal_code') }} <span class="font-normal normal-case tracking-normal">{{ t('sport_matching.form.optional') }}</span><input v-model="form.postal_code" maxlength="20" class="mt-2 h-12 w-full rounded-2xl border-border bg-inputBg text-sm text-primary" :placeholder="t('sport_matching.form.postal_code_placeholder')" /></label>
                <label class="text-xs font-black uppercase tracking-wide text-secondary">{{ t('sport_matching.form.country') }}<input v-model="form.country_code" required maxlength="2" class="mt-2 h-12 w-full rounded-2xl border-border bg-inputBg text-sm uppercase text-primary" :placeholder="form.country_code || 'DE'" /></label>
                <label v-if="form.mode === 'team'" class="text-xs font-black uppercase tracking-wide text-secondary">{{ t('sport_matching.own_team_size') }}<input v-model.number="form.own_team_size" required type="number" min="1" max="500" class="mt-2 h-12 w-full rounded-2xl border-border bg-inputBg text-sm text-primary" /></label>
                <label v-if="form.mode === 'team'" class="text-xs font-black uppercase tracking-wide text-secondary">{{ t('sport_matching.opponent_rule') }}<select v-model="form.opponent_size_type" class="mt-2 h-12 w-full rounded-2xl border-border bg-inputBg text-sm text-primary"><option value="exact">{{ t('sport_matching.exactly') }}</option><option value="minimum">{{ t('sport_matching.at_least') }}</option></select></label>
                <label v-if="form.mode === 'team'" class="text-xs font-black uppercase tracking-wide text-secondary">{{ t('sport_matching.opponent_size') }}<input v-model.number="form.team_size" required type="number" min="1" max="500" class="mt-2 h-12 w-full rounded-2xl border-border bg-inputBg text-sm text-primary" /></label>
                <p v-else class="self-center text-sm text-secondary">{{ t('sport_matching.one_partner') }}</p>
                <label v-if="showExtraDetails" class="text-xs font-black uppercase tracking-wide text-secondary">{{ t('sport_matching.form.level') }}<select v-model="form.skill_level" class="mt-2 h-12 w-full rounded-2xl border-border bg-inputBg text-sm text-primary"><option v-for="level in skillLevels" :key="level" :value="level">{{ skillLabel(level) }}</option></select></label>
                <label v-if="showExtraDetails" class="text-xs font-black uppercase tracking-wide text-secondary md:col-span-2 xl:col-span-4">{{ t('sport_matching.form.description') }} <span class="font-normal normal-case tracking-normal">{{ t('sport_matching.form.optional') }}</span><textarea v-model="form.description" rows="3" class="mt-2 w-full rounded-2xl border-border bg-inputBg text-sm text-primary" :placeholder="t('sport_matching.form.description_placeholder')" /></label>
                <p v-if="form.errors.starts_at" class="text-xs font-semibold text-red-500 md:col-span-2">{{ form.errors.starts_at }}</p><p v-if="form.errors.ends_at" class="text-xs font-semibold text-red-500 md:col-span-2">{{ form.errors.ends_at }}</p>
                <div v-if="Object.keys(form.errors).length" class="rounded-2xl bg-red-500/10 p-3 text-sm font-semibold text-red-500 md:col-span-2 xl:col-span-4">{{ Object.values(form.errors)[0] }}</div>
                <div class="flex flex-col gap-3 border-t border-border pt-5 md:col-span-2 md:flex-row md:items-center md:justify-end xl:col-span-4"><p class="me-auto text-xs text-secondary"><i class="las la-lock me-1"></i>{{ t('sport_matching.form.privacy') }}</p><button type="button" class="rounded-2xl border border-border px-5 py-3 text-sm font-black text-secondary transition hover:bg-inputBg" @click="showCreate = false">{{ t('sport_matching.form.cancel') }}</button><button type="submit" :disabled="form.processing" class="rounded-2xl bg-air-blue px-6 py-3 text-sm font-black text-buttonTextPrimary shadow-lg shadow-air-blue/15 transition hover:bg-air-blue/90 disabled:opacity-50">{{ form.processing ? t('sport_matching.form.publishing') : t('sport_matching.form.submit') }}</button></div>
            </div>
        </form>

        <div class="grid gap-5 xl:grid-cols-[minmax(0,1fr)_280px]">
            <main class="min-w-0">
                <SportMatchingSwipeDeck v-if="view === 'swipe'" :items="matchings.data || []" :teams="availableTeams" />

                <section v-if="view === 'swipe' && ownMatchings.length" class="mt-5 rounded-[26px] border border-border bg-card p-5 shadow-sm sm:p-6">
                    <div class="flex items-center justify-between gap-3"><div><p class="text-xs font-black uppercase tracking-[0.18em] text-air-blue">{{ t('sport_matching.attendance.section_eyebrow') }}</p><h2 class="mt-1 text-xl font-black text-primary">{{ t('sport_matching.attendance.section_title') }}</h2></div><i class="las la-calendar-check text-2xl text-air-blue"></i></div>
                    <div class="mt-4 grid gap-3 lg:grid-cols-2">
                        <div v-for="matching in ownMatchings" :key="`attendance-${matching.id}`" class="rounded-2xl border border-border bg-inputBg/60 p-4">
                            <div class="flex items-start justify-between gap-3"><div class="min-w-0"><p class="truncate text-sm font-black text-primary">{{ matching.title }}</p><p class="mt-1 text-xs text-secondary">{{ formatDateTime(matching.starts_at) }} · {{ formatLocation(matching) }}</p></div><span class="shrink-0 rounded-full px-3 py-1 text-[11px] font-black" :class="attendanceStatusClass(matching.attendance?.status)">{{ attendanceStatusLabel(matching.attendance?.status) }}</span></div>
                            <div v-if="matching.attendance" class="mt-3 flex flex-wrap gap-2"><button v-if="['pending', 'cancelled'].includes(matching.attendance.status)" type="button" class="rounded-xl bg-air-blue px-3 py-2 text-xs font-black text-buttonTextPrimary" @click="updateAttendance(matching, 'confirm')">{{ t('sport_matching.attendance.confirm') }}</button><button v-if="matching.attendance.status === 'confirmed'" type="button" class="rounded-xl bg-emerald-500 px-3 py-2 text-xs font-black text-white" @click="updateAttendance(matching, 'check_in')">{{ t('sport_matching.attendance.check_in') }}</button><button v-if="['pending', 'confirmed'].includes(matching.attendance.status)" type="button" class="rounded-xl border border-border px-3 py-2 text-xs font-black text-secondary" @click="updateAttendance(matching, 'cancel')">{{ t('sport_matching.attendance.cancel') }}</button></div>
                        </div>
                    </div>
                </section>

                <section v-else-if="matchings.data?.length" class="grid gap-4 lg:grid-cols-2">
                    <article v-for="matching in matchings.data" :key="matching.id" class="group overflow-hidden rounded-[26px] border border-border bg-card shadow-[0_12px_35px_rgba(15,23,42,0.05)] transition hover:-translate-y-1 hover:border-air-blue/40 hover:shadow-[0_18px_45px_rgba(0,0,0,0.08)]">
                        <div class="h-1.5 bg-gradient-to-r from-air-blue via-air-blue/75 to-air-green"></div>
                        <div class="p-5 sm:p-6">
                            <div class="flex items-start justify-between gap-4"><div class="min-w-0"><div class="flex flex-wrap items-center gap-2"><span class="rounded-full bg-air-blue/10 px-3 py-1 text-[11px] font-black uppercase tracking-wide text-air-blue">{{ matching.sport?.name }}</span><span class="rounded-full bg-inputBg px-3 py-1 text-[11px] font-bold text-secondary">{{ t(matching.mode === 'team' ? 'sport_matching.modes.team_match' : 'sport_matching.modes.partner') }}</span></div><h2 class="mt-4 truncate text-xl font-black text-primary">{{ matching.title }}</h2></div><span class="shrink-0 text-end text-xs font-black text-secondary">{{ matching.mode === 'team' ? teamSizeLabel(matching) : t('sport_matching.cards.wanted', { count: matching.participants_needed }) }}</span></div>
                            <p v-if="matching.description" class="mt-3 line-clamp-2 text-sm leading-6 text-secondary">{{ matching.description }}</p>
                            <div class="mt-5 grid gap-3 rounded-2xl bg-inputBg/60 p-4 text-sm text-secondary"><span class="flex items-start gap-3"><i class="las la-map-marker mt-0.5 text-lg text-air-blue"></i><span class="min-w-0 break-words">{{ formatLocation(matching) }}</span></span><span class="flex items-center gap-3"><i class="las la-calendar text-lg text-air-blue"></i>{{ formatDateTime(matching.starts_at) }}</span><span class="flex items-center gap-3"><i class="las la-signal text-lg text-air-blue"></i>{{ skillLabel(matching.skill_level) }}<span v-if="matching.distance_km != null"> · {{ matching.distance_km }} km</span></span></div>
                            <div class="mt-5 flex items-center gap-3 border-t border-border pt-4"><div class="grid h-10 w-10 shrink-0 place-items-center overflow-hidden rounded-2xl bg-air-blue/10 text-sm font-black text-air-blue"><img v-if="matching.owner?.profile_photo_url" :src="matching.owner.profile_photo_url" :alt="matching.owner.name" class="h-full w-full object-cover"><span v-else>{{ (matching.owner?.name || '?').slice(0, 1).toUpperCase() }}</span></div><div class="min-w-0"><p class="text-[11px] font-bold uppercase tracking-wide text-secondary">{{ t('sport_matching.cards.offered_by') }}</p><p class="truncate text-sm font-black text-primary">{{ matching.owner?.name || t('sport_matching.cards.community') }}<span v-if="matching.team" class="font-normal text-secondary"> · {{ matching.team.name }}</span></p></div></div>
                            <div v-if="matching.mine" class="mt-5 space-y-3 border-t border-border pt-5"><div v-for="application in matching.applications" :key="application.id" class="rounded-2xl border border-border bg-inputBg/60 p-3"><div class="flex flex-wrap items-center justify-between gap-3"><div><strong class="text-sm text-primary">{{ application.team?.name || application.user?.name }}</strong><p v-if="application.message" class="mt-1 text-xs text-secondary">{{ application.message }}</p></div><div v-if="application.status === 'pending'" class="flex gap-2"><button type="button" class="rounded-xl bg-emerald-500/10 px-3 py-2 text-xs font-black text-emerald-600" @click="decide(matching, application, 'accepted')">{{ t('sport_matching.cards.accept') }}</button><button type="button" class="rounded-xl bg-red-500/10 px-3 py-2 text-xs font-black text-red-500" @click="decide(matching, application, 'declined')">{{ t('sport_matching.cards.decline') }}</button></div><span v-else class="text-xs font-black" :class="application.status === 'accepted' ? 'text-emerald-600' : 'text-secondary'">{{ applicationStatusLabel(application.status) }}</span></div><div v-if="application.status === 'accepted' && application.attendance" class="mt-3 flex flex-wrap items-center gap-2 border-t border-border pt-3"><span class="rounded-full px-3 py-1 text-[11px] font-black" :class="attendanceStatusClass(application.attendance.status)">{{ attendanceStatusLabel(application.attendance.status) }}</span><button v-if="application.attendance.status === 'confirmed'" type="button" class="rounded-xl border border-red-400/40 px-3 py-2 text-xs font-black text-red-500" @click="reportNoShow(matching, application.user.id)">{{ t('sport_matching.attendance.report_no_show') }}</button></div></div><button type="button" class="text-xs font-black text-red-500 hover:underline" @click="router.post(route('auth.sport-matching.cancel', matching.id), {}, { preserveScroll: true })">{{ t('sport_matching.cards.close_search') }}</button></div>
                            <div v-else-if="!matching.my_application" class="mt-5 space-y-3 border-t border-border pt-5"><select v-if="matching.mode === 'team'" v-model="selectedTeams[matching.id]" class="h-11 w-full rounded-xl border-border bg-inputBg text-sm text-primary"><option value="">{{ t('sport_matching.cards.choose_team') }}</option><option v-for="team in availableTeams" :key="team.id" :value="team.id">{{ team.name }}</option></select><input v-if="matching.mode === 'team'" v-model.number="applicationTeamSizes[matching.id]" type="number" min="1" max="500" class="h-11 w-full rounded-xl border-border bg-inputBg text-sm text-primary" :placeholder="t('sport_matching.your_player_count')" /><textarea v-model="applicationMessages[matching.id]" rows="2" class="w-full rounded-xl border-border bg-inputBg text-sm text-primary" :placeholder="t('sport_matching.cards.message_placeholder')" /><button type="button" class="w-full rounded-2xl bg-air-blue px-4 py-3 text-sm font-black text-buttonTextPrimary transition hover:bg-air-blue/90" @click="apply(matching)"><i class="las la-paper-plane me-1"></i> {{ t('sport_matching.cards.send_interest') }}</button></div>
                            <div v-else class="mt-5 rounded-2xl border border-border bg-inputBg/60 p-4"><div class="flex flex-wrap items-center justify-between gap-3"><span class="text-sm font-black" :class="matching.my_application === 'accepted' ? 'text-emerald-600' : matching.my_application === 'declined' ? 'text-red-500' : 'text-air-blue'">{{ applicationStatusLabel(matching.my_application) }}</span><span v-if="matching.attendance" class="rounded-full px-3 py-1 text-[11px] font-black" :class="attendanceStatusClass(matching.attendance.status)">{{ attendanceStatusLabel(matching.attendance.status) }}</span></div><button v-if="matching.my_application === 'pending'" type="button" class="mt-3 text-xs font-black text-air-blue" @click="router.post(route('auth.sport-matching.withdraw', matching.id), {}, { preserveScroll: true })">{{ t('sport_matching.withdraw') }}</button><p v-if="matching.my_application === 'accepted'" class="mt-2 text-xs text-secondary">{{ t('sport_matching.attendance.accepted_help') }}</p><div v-if="matching.my_application === 'accepted' && matching.attendance" class="mt-3 flex flex-wrap gap-2"><button v-if="['pending', 'cancelled'].includes(matching.attendance.status)" type="button" class="rounded-xl bg-air-blue px-3 py-2 text-xs font-black text-buttonTextPrimary" @click="updateAttendance(matching, 'confirm')">{{ t('sport_matching.attendance.confirm') }}</button><button v-if="matching.attendance.status === 'confirmed'" type="button" class="rounded-xl bg-emerald-500 px-3 py-2 text-xs font-black text-white" @click="updateAttendance(matching, 'check_in')">{{ t('sport_matching.attendance.check_in') }}</button><button v-if="['pending', 'confirmed'].includes(matching.attendance.status)" type="button" class="rounded-xl border border-border px-3 py-2 text-xs font-black text-secondary" @click="updateAttendance(matching, 'cancel')">{{ t('sport_matching.attendance.cancel') }}</button></div></div>
                        </div>
                    </article>
                </section>
                <div v-else class="rounded-[28px] border border-dashed border-border bg-card p-12 text-center shadow-sm"><div class="mx-auto grid h-16 w-16 place-items-center rounded-3xl bg-air-blue/10 text-3xl text-air-blue"><i class="las la-compass"></i></div><h3 class="mt-5 text-xl font-black text-primary">{{ t('sport_matching.cards.empty_title') }}</h3><p class="mx-auto mt-2 max-w-md text-sm leading-6 text-secondary">{{ t('sport_matching.cards.empty_text') }}</p><button type="button" class="mt-5 rounded-2xl bg-air-blue px-5 py-3 text-sm font-black text-buttonTextPrimary" @click="showCreate = true">{{ t('sport_matching.cards.publish_first') }}</button></div>
                <div v-if="matchings.meta?.last_page > 1" class="mt-5 flex items-center justify-between rounded-2xl border border-border bg-card p-3 shadow-sm"><button type="button" class="rounded-xl border border-border px-4 py-2 text-sm font-black text-secondary transition hover:bg-inputBg disabled:opacity-40" :disabled="matchings.meta.current_page <= 1" @click="goToPage(matchings.meta.current_page - 1)"><i class="las la-arrow-left me-1"></i> {{ t('sport_matching.cards.previous') }}</button><span class="text-sm font-black text-secondary">{{ matchings.meta.current_page }} / {{ matchings.meta.last_page }}</span><button type="button" class="rounded-xl border border-border px-4 py-2 text-sm font-black text-secondary transition hover:bg-inputBg disabled:opacity-40" :disabled="matchings.meta.current_page >= matchings.meta.last_page" @click="goToPage(matchings.meta.current_page + 1)">{{ t('sport_matching.cards.next') }} <i class="las la-arrow-right ms-1"></i></button></div>
            </main>
            <aside class="hidden space-y-4 xl:block">
                <section class="rounded-[26px] border border-border bg-card p-5 shadow-sm"><div class="flex items-center justify-between"><h2 class="text-sm font-black text-primary">{{ t('sport_matching.side.title') }}</h2><i class="las la-sliders-h text-lg text-air-blue"></i></div><p class="mt-2 text-xs leading-5 text-secondary">{{ t('sport_matching.side.privacy') }}</p><div class="mt-5 space-y-3"><div class="flex items-center justify-between rounded-2xl bg-inputBg/60 px-3 py-3"><span class="text-xs font-bold text-secondary">{{ t('sport_matching.side.mode') }}</span><span class="text-xs font-black text-primary">{{ modeLabel }}</span></div><div class="flex items-center justify-between rounded-2xl bg-inputBg/60 px-3 py-3"><span class="text-xs font-bold text-secondary">{{ t('sport_matching.side.radius') }}</span><span class="text-xs font-black text-primary">{{ filterLatitude != null ? t('sport_matching.filters.radius_option', { radius: filterRadius || 25 }) : t('sport_matching.city_search_only') }}</span></div><div class="flex items-center justify-between rounded-2xl bg-inputBg/60 px-3 py-3"><span class="text-xs font-bold text-secondary">{{ t('sport_matching.side.level') }}</span><span class="text-xs font-black text-primary">{{ filterSkill ? skillLabel(filterSkill) : t('sport_matching.side.level_all') }}</span></div></div></section>
                <section class="rounded-[26px] border border-air-blue/20 bg-gradient-to-br from-air-blue/10 to-air-green/10 p-5"><div class="grid h-10 w-10 place-items-center rounded-2xl bg-air-blue/15 text-xl text-air-blue"><i class="las la-lightbulb"></i></div><h2 class="mt-4 text-sm font-black text-primary">{{ t('sport_matching.side.tip_title') }}</h2><p class="mt-2 text-xs leading-5 text-secondary">{{ t('sport_matching.side.tip_text') }}</p></section>
            </aside>
        </div>
    </div>
</template>
