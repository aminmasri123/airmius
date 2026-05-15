<script setup>
import AppLayout from '@/Components/Auth/Layouts/AppLayout.vue'
import { Head, router, useForm } from '@inertiajs/vue3'
import { computed, ref } from 'vue'

defineOptions({ layout: AppLayout })

const props = defineProps({
    plans: { type: Array, default: () => [] },
    activities: { type: Array, default: () => [] },
    teams: { type: Array, default: () => [] },
    people: { type: Array, default: () => [] },
})

const sports = [
    { key: 'all', label: 'Alle', icon: 'las la-layer-group', accent: 'bg-air-blue' },
    { key: 'laufen', label: 'Laufen', icon: 'las la-running', accent: 'bg-emerald-500', metrics: ['Distanz km', 'Pace Ziel', 'Hoehenmeter', 'RPE'] },
    { key: 'schwimmen', label: 'Schwimmen', icon: 'las la-swimmer', accent: 'bg-cyan-500', metrics: ['Bahnen', 'Stil', 'Intervall', 'Pausenzeit'] },
    { key: 'gym', label: 'Gym', icon: 'las la-dumbbell', accent: 'bg-rose-500', metrics: ['Saetze', 'Wiederholungen', 'Gewicht kg', 'Pause'] },
    { key: 'fussball', label: 'Fussball', icon: 'las la-futbol', accent: 'bg-lime-500', metrics: ['Schwerpunkt', 'Spielfeld', 'Spielerzahl', 'Drill'] },
    { key: 'tanzen', label: 'Tanzen', icon: 'las la-music', accent: 'bg-fuchsia-500', metrics: ['Stil', 'Choreo', 'Takte', 'Tempo'] },
    { key: 'golf', label: 'Golf', icon: 'las la-golf-ball', accent: 'bg-amber-500', metrics: ['Loecher', 'Schlaeger', 'Schwerpunkt', 'Zielscore'] },
    { key: 'cycling', label: 'Radfahren', icon: 'las la-biking', accent: 'bg-orange-500', metrics: ['Distanz km', 'Watt Ziel', 'Kadenz', 'Hoehenmeter'] },
    { key: 'yoga', label: 'Yoga', icon: 'las la-spa', accent: 'bg-violet-500', metrics: ['Flow', 'Atemfokus', 'Level', 'Haltezeit'] },
]

const activeSport = ref('all')
const activeModal = ref(null)
const deleteText = ref('')
const selectedPlan = ref(null)
const activityImageInput = ref(null)
const planImageInput = ref(null)
const itemImageInput = ref(null)

const activityForm = useForm({
    title: '',
    activity_type: 'laufen',
    started_at: '',
    duration_minutes: '',
    distance_km: '',
    calories: '',
    image: null,
})

const planForm = useForm({
    title: '',
    description: '',
    cadence: 'weekly',
    starts_on: '',
    ends_on: '',
    status: 'published',
    share_permission: 'read',
    team_id: '',
    user_ids: [],
    item_title: '',
    item_sport_type: 'laufen',
    item_description: '',
    item_scheduled_at: '',
    item_duration_minutes: '',
    item_distance_km: '',
    item_calories: '',
    item_intensity: 'mittel',
    item_todos: '',
    item_image: null,
    item_video_url: '',
    item_metrics: {},
})

const editForm = useForm({
    title: '',
    description: '',
    cadence: 'weekly',
    starts_on: '',
    ends_on: '',
    status: 'published',
    share_permission: 'read',
    team_id: '',
    user_ids: [],
})

const itemForm = useForm({
    title: '',
    sport_type: 'laufen',
    description: '',
    scheduled_at: '',
    duration_minutes: '',
    intensity: 'mittel',
    todos: '',
    image: null,
    video_url: '',
    metrics: {},
})

const selectedSport = computed(() => sports.find((sport) => sport.key === activeSport.value) || sports[0])
const planSport = computed(() => sports.find((sport) => sport.key === planForm.item_sport_type) || sports[1])
const itemSport = computed(() => sports.find((sport) => sport.key === itemForm.sport_type) || sports[1])

const filteredPlans = computed(() => {
    if (activeSport.value === 'all') return props.plans

    return props.plans.filter((plan) => plan.items?.some((item) => item.sport_type === activeSport.value))
})

const upcomingItems = computed(() => props.plans
    .flatMap((plan) => (plan.items || []).map((item) => ({ ...item, plan })))
    .filter((item) => item.scheduled_at)
    .sort((a, b) => new Date(a.scheduled_at) - new Date(b.scheduled_at))
    .slice(0, 5))

const selectedTeamMembers = computed(() => {
    const team = props.teams.find((item) => Number(item.id) === Number(planForm.team_id))
    return team?.users || []
})

const cadenceLabels = {
    single: 'Einmalig',
    daily: 'Taeglich',
    weekly: 'Woechentlich',
    monthly: 'Monatlich',
}

const permissionLabels = {
    read: 'Nur lesen',
    write: 'Mitarbeiten',
}

const resetPlanForm = () => {
    planForm.reset()
    planForm.cadence = 'weekly'
    planForm.status = 'published'
    planForm.share_permission = 'read'
    planForm.item_sport_type = activeSport.value === 'all' ? 'laufen' : activeSport.value
    planForm.item_intensity = 'mittel'
    planForm.item_metrics = {}
    if (planImageInput.value) planImageInput.value.value = ''
}

const openModal = (name, plan = null) => {
    selectedPlan.value = plan
    deleteText.value = ''

    if (name === 'plan') resetPlanForm()
    if (name === 'activity') {
        activityForm.reset()
        activityForm.activity_type = activeSport.value === 'all' ? 'laufen' : activeSport.value
        if (activityImageInput.value) activityImageInput.value.value = ''
    }
    if (name === 'edit' && plan) {
        editForm.title = plan.title || ''
        editForm.description = plan.description || ''
        editForm.cadence = plan.cadence || 'weekly'
        editForm.starts_on = plan.starts_on || ''
        editForm.ends_on = plan.ends_on || ''
        editForm.status = plan.status || 'draft'
        editForm.share_permission = plan.share_permission || 'read'
        editForm.team_id = plan.team?.id || ''
        editForm.user_ids = (plan.assignments || []).filter((assignment) => assignment.user).map((assignment) => assignment.user.id)
        editForm.clearErrors()
    }
    if (name === 'item' && plan) {
        itemForm.reset()
        itemForm.sport_type = plan.items?.[0]?.sport_type || (activeSport.value === 'all' ? 'laufen' : activeSport.value)
        itemForm.intensity = 'mittel'
        itemForm.metrics = {}
        if (itemImageInput.value) itemImageInput.value.value = ''
    }

    activeModal.value = name
}

const closeModal = () => {
    activeModal.value = null
    selectedPlan.value = null
    deleteText.value = ''
}

const togglePlanUser = (userId, form = planForm) => {
    const id = Number(userId)
    form.user_ids = form.user_ids.map(Number).includes(id)
        ? form.user_ids.filter((value) => Number(value) !== id)
        : [...form.user_ids, id]
}

const setActivityImage = (event) => {
    activityForm.image = event.target.files?.[0] || null
}

const setPlanImage = (event) => {
    planForm.item_image = event.target.files?.[0] || null
}

const setItemImage = (event) => {
    itemForm.image = event.target.files?.[0] || null
}

const submitActivity = () => {
    activityForm.post(route('auth.training.activities.store'), {
        preserveScroll: true,
        forceFormData: true,
        onSuccess: closeModal,
    })
}

const submitPlan = () => {
    planForm.post(route('auth.training.plans.store'), {
        preserveScroll: true,
        forceFormData: true,
        onSuccess: closeModal,
    })
}

const updatePlan = () => {
    if (!selectedPlan.value) return
    editForm.put(route('auth.training.plans.update', selectedPlan.value.id), {
        preserveScroll: true,
        onSuccess: closeModal,
    })
}

const publishPlan = (plan) => {
    router.post(route('auth.training.plans.publish', plan.id), {}, { preserveScroll: true })
}

const deletePlan = () => {
    if (!selectedPlan.value || deleteText.value !== 'delete') return
    router.delete(route('auth.training.plans.destroy', selectedPlan.value.id), {
        preserveScroll: true,
        onSuccess: closeModal,
    })
}

const submitPlanItem = () => {
    if (!selectedPlan.value) return
    itemForm.post(route('auth.training.plans.items.store', selectedPlan.value.id), {
        preserveScroll: true,
        forceFormData: true,
        onSuccess: closeModal,
    })
}

const formatDate = (value) => {
    if (!value) return '-'
    return new Intl.DateTimeFormat('de-DE', { day: '2-digit', month: '2-digit', year: 'numeric' }).format(new Date(value))
}

const formatTime = (value) => {
    if (!value) return ''
    return new Intl.DateTimeFormat('de-DE', { hour: '2-digit', minute: '2-digit' }).format(new Date(value))
}

const formatDuration = (minutesOrSeconds, isSeconds = false) => {
    const minutes = isSeconds ? Math.round(Number(minutesOrSeconds || 0) / 60) : Number(minutesOrSeconds || 0)
    if (!minutes) return '-'
    if (minutes < 60) return `${minutes} min`
    const hours = Math.floor(minutes / 60)
    const rest = minutes % 60
    return rest ? `${hours} h ${rest} min` : `${hours} h`
}

const formatDistance = (meters) => {
    if (!meters) return '-'
    return `${(Number(meters) / 1000).toFixed(2).replace('.', ',')} km`
}

const sportLabel = (key) => sports.find((sport) => sport.key === key)?.label || key || 'Training'
const sportIcon = (key) => sports.find((sport) => sport.key === key)?.icon || 'las la-running'
const sportAccent = (key) => sports.find((sport) => sport.key === key)?.accent || 'bg-air-blue'
</script>

<template>
    <Head title="Training" />

    <div class="space-y-5">
        <section class="overflow-hidden rounded-2xl border border-border bg-card">
            <div class="grid lg:grid-cols-[1fr_380px]">
                <div class="p-5 sm:p-6">
                    <div class="flex flex-wrap items-center gap-2">
                        <span class="rounded-full border border-air-blue/40 bg-air-blue/10 px-3 py-1 text-xs font-semibold uppercase tracking-wide text-air-blue">
                            Training Hub
                        </span>
                        <span class="rounded-full border border-border px-3 py-1 text-xs font-semibold text-secondary">
                            Planen · Ausfuehren · Teilen
                        </span>
                    </div>
                    <h1 class="mt-4 max-w-3xl text-2xl font-semibold leading-tight text-primary sm:text-3xl">
                        Trainingsplaene fuer jede Sportart, jedes Team und jeden Athleten.
                    </h1>
                    <p class="mt-3 max-w-3xl text-sm leading-6 text-secondary">
                        Trainer erstellen Programme mit Aufgaben, Bildern und Video-Erklaerungen. Sportler dokumentieren eigene Einheiten und koennen Plaene lesen oder gemeinsam verbessern.
                    </p>
                    <div class="mt-5 flex flex-wrap gap-2">
                        <button type="button" class="rounded-xl bg-buttonPrimary px-4 py-2 text-sm font-semibold text-buttonTextPrimary" @click="openModal('plan')">
                            Plan erstellen
                        </button>
                        <button type="button" class="rounded-xl border border-border px-4 py-2 text-sm font-semibold text-primary hover:bg-muted" @click="openModal('activity')">
                            Einheit eintragen
                        </button>
                    </div>
                </div>
                <div class="border-t border-border bg-muted/30 p-5 lg:border-l lg:border-t-0">
                    <div class="grid grid-cols-3 gap-3">
                        <div class="rounded-xl border border-border bg-card p-3 text-center">
                            <p class="text-2xl font-semibold text-primary">{{ plans.length }}</p>
                            <p class="text-xs text-secondary">Plaene</p>
                        </div>
                        <div class="rounded-xl border border-border bg-card p-3 text-center">
                            <p class="text-2xl font-semibold text-primary">{{ activities.length }}</p>
                            <p class="text-xs text-secondary">Einheiten</p>
                        </div>
                        <div class="rounded-xl border border-border bg-card p-3 text-center">
                            <p class="text-2xl font-semibold text-primary">{{ teams.length }}</p>
                            <p class="text-xs text-secondary">Teams</p>
                        </div>
                    </div>
                    <div class="mt-4 rounded-xl border border-border bg-card p-4">
                        <p class="text-xs font-semibold uppercase tracking-wide text-secondary">Naechstes Training</p>
                        <div v-if="upcomingItems.length" class="mt-3 space-y-3">
                            <div v-for="item in upcomingItems.slice(0, 2)" :key="`${item.plan.id}-${item.id}`" class="flex items-center gap-3">
                                <span class="flex h-10 w-10 items-center justify-center rounded-xl text-white" :class="sportAccent(item.sport_type)">
                                    <i :class="sportIcon(item.sport_type)" class="text-xl"></i>
                                </span>
                                <div class="min-w-0">
                                    <p class="truncate text-sm font-semibold text-primary">{{ item.title }}</p>
                                    <p class="text-xs text-secondary">{{ formatDate(item.scheduled_at) }} {{ formatTime(item.scheduled_at) }}</p>
                                </div>
                            </div>
                        </div>
                        <p v-else class="mt-3 text-sm text-secondary">Noch kein Termin geplant.</p>
                    </div>
                </div>
            </div>
        </section>

        <div
            v-if="$page.props.flash?.success || $page.props.flash?.error"
            class="rounded-xl border px-4 py-3 text-sm font-semibold"
            :class="$page.props.flash?.success ? 'border-success/40 bg-success/10 text-success' : 'border-danger/40 bg-danger/10 text-danger'"
        >
            {{ $page.props.flash?.success || $page.props.flash?.error }}
        </div>

        <section class="rounded-2xl border border-border bg-card p-3">
            <div class="flex gap-2 overflow-x-auto pb-1">
                <button
                    v-for="sport in sports"
                    :key="sport.key"
                    type="button"
                    class="flex shrink-0 items-center gap-2 rounded-xl border px-3 py-2 text-sm font-semibold transition"
                    :class="activeSport === sport.key ? 'border-air-blue bg-air-blue/10 text-primary' : 'border-border text-secondary hover:bg-muted'"
                    @click="activeSport = sport.key"
                >
                    <span class="flex h-8 w-8 items-center justify-center rounded-lg text-white" :class="sport.accent">
                        <i :class="sport.icon"></i>
                    </span>
                    {{ sport.label }}
                </button>
            </div>
        </section>

        <div class="grid gap-5 xl:grid-cols-[1fr_340px]">
            <section class="space-y-4">
                <div class="flex flex-wrap items-center justify-between gap-3">
                    <div>
                        <p class="text-xs font-semibold uppercase tracking-wide text-secondary">{{ selectedSport.label }}</p>
                        <h2 class="text-xl font-semibold text-primary">Trainingsplaene</h2>
                    </div>
                    <button type="button" class="rounded-xl border border-border px-4 py-2 text-sm font-semibold text-primary hover:bg-muted" @click="openModal('plan')">
                        Neuer Plan
                    </button>
                </div>

                <div class="grid gap-4 lg:grid-cols-2">
                    <article v-for="plan in filteredPlans" :key="plan.id" class="overflow-hidden rounded-2xl border border-border bg-card">
                        <div class="p-4">
                            <div class="flex items-start justify-between gap-3">
                                <div class="min-w-0">
                                    <div class="flex flex-wrap items-center gap-2">
                                        <span class="rounded-full bg-muted px-2.5 py-1 text-xs font-semibold text-secondary">
                                            {{ cadenceLabels[plan.cadence] || plan.cadence }}
                                        </span>
                                        <span class="rounded-full px-2.5 py-1 text-xs font-semibold" :class="plan.status === 'published' ? 'bg-success/10 text-success' : 'bg-warning/10 text-warning'">
                                            {{ plan.status === 'published' ? 'Freigegeben' : 'Entwurf' }}
                                        </span>
                                    </div>
                                    <h3 class="mt-3 truncate text-lg font-semibold text-primary">{{ plan.title }}</h3>
                                    <p class="mt-1 line-clamp-2 text-sm text-secondary">{{ plan.description || 'Keine Beschreibung hinterlegt.' }}</p>
                                </div>
                                <span class="flex h-11 w-11 shrink-0 items-center justify-center rounded-xl text-white" :class="sportAccent(plan.items?.[0]?.sport_type)">
                                    <i :class="sportIcon(plan.items?.[0]?.sport_type)" class="text-xl"></i>
                                </span>
                            </div>

                            <div class="mt-4 grid grid-cols-3 gap-2 text-center">
                                <div class="rounded-xl border border-border bg-inputBg/50 p-2">
                                    <p class="text-base font-semibold text-primary">{{ plan.items?.length || 0 }}</p>
                                    <p class="text-[11px] text-secondary">Einheiten</p>
                                </div>
                                <div class="rounded-xl border border-border bg-inputBg/50 p-2">
                                    <p class="truncate text-base font-semibold text-primary">{{ plan.team?.name || '-' }}</p>
                                    <p class="text-[11px] text-secondary">Team</p>
                                </div>
                                <div class="rounded-xl border border-border bg-inputBg/50 p-2">
                                    <p class="text-base font-semibold text-primary">{{ permissionLabels[plan.share_permission] }}</p>
                                    <p class="text-[11px] text-secondary">Rechte</p>
                                </div>
                            </div>

                            <div class="mt-4 space-y-2">
                                <div v-for="item in (plan.items || []).slice(0, 2)" :key="item.id" class="rounded-xl border border-border bg-inputBg/40 p-3">
                                    <div class="flex gap-3">
                                        <img v-if="item.image_url" :src="item.image_url" alt="" class="h-14 w-14 rounded-xl object-cover" />
                                        <span v-else class="flex h-14 w-14 items-center justify-center rounded-xl text-white" :class="sportAccent(item.sport_type)">
                                            <i :class="sportIcon(item.sport_type)" class="text-xl"></i>
                                        </span>
                                        <div class="min-w-0 flex-1">
                                            <div class="flex items-start justify-between gap-2">
                                                <p class="truncate text-sm font-semibold text-primary">{{ item.title }}</p>
                                                <span class="shrink-0 text-xs text-secondary">{{ item.scheduled_at ? formatDate(item.scheduled_at) : 'offen' }}</span>
                                            </div>
                                            <p class="mt-1 text-xs text-secondary">{{ sportLabel(item.sport_type) }} · {{ formatDuration(item.duration_minutes) }}</p>
                                            <div v-if="item.metrics && Object.keys(item.metrics).length" class="mt-2 flex flex-wrap gap-1">
                                                <span v-for="(value, key) in item.metrics" :key="key" class="rounded-full bg-muted px-2 py-0.5 text-[11px] text-secondary">
                                                    {{ key }}: {{ value }}
                                                </span>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="flex flex-wrap gap-2 border-t border-border bg-inputBg/30 p-3">
                            <button v-if="plan.can_write" type="button" class="rounded-lg border border-border px-3 py-2 text-xs font-semibold text-primary hover:bg-muted" @click="openModal('item', plan)">
                                Einheit
                            </button>
                            <button v-if="plan.can_write" type="button" class="rounded-lg border border-border px-3 py-2 text-xs font-semibold text-primary hover:bg-muted" @click="openModal('edit', plan)">
                                Bearbeiten
                            </button>
                            <button v-if="plan.status !== 'published' && plan.can_write" type="button" class="rounded-lg border border-success/40 px-3 py-2 text-xs font-semibold text-success hover:bg-success/10" @click="publishPlan(plan)">
                                Freigeben
                            </button>
                            <button v-if="plan.can_write" type="button" class="ml-auto rounded-lg border border-danger/40 px-3 py-2 text-xs font-semibold text-danger hover:bg-danger/10" @click="openModal('delete', plan)">
                                Loeschen
                            </button>
                        </div>
                    </article>

                    <div v-if="!filteredPlans.length" class="rounded-2xl border border-dashed border-border bg-card p-8 text-center lg:col-span-2">
                        <p class="text-lg font-semibold text-primary">Noch kein Plan fuer diese Auswahl.</p>
                        <p class="mt-2 text-sm text-secondary">Erstelle den ersten Plan und gib ihn direkt an Sportler oder ein Team frei.</p>
                        <button type="button" class="mt-4 rounded-xl bg-buttonPrimary px-4 py-2 text-sm font-semibold text-buttonTextPrimary" @click="openModal('plan')">
                            Plan erstellen
                        </button>
                    </div>
                </div>
            </section>

            <aside class="space-y-4">
                <section class="rounded-2xl border border-border bg-card p-4">
                    <h2 class="text-base font-semibold text-primary">Letzte Einheiten</h2>
                    <div class="mt-4 space-y-3">
                        <div v-for="activity in activities.slice(0, 6)" :key="activity.id" class="flex items-center gap-3 rounded-xl border border-border bg-inputBg/40 p-2">
                            <img v-if="activity.image_url" :src="activity.image_url" alt="" class="h-12 w-12 rounded-xl object-cover" />
                            <span v-else class="flex h-12 w-12 items-center justify-center rounded-xl bg-muted text-secondary">
                                <i class="las la-running text-xl"></i>
                            </span>
                            <div class="min-w-0">
                                <p class="truncate text-sm font-semibold text-primary">{{ activity.title || activity.activity_type || 'Training' }}</p>
                                <p class="text-xs text-secondary">{{ formatDate(activity.started_at) }} · {{ formatDuration(activity.duration_seconds, true) }} · {{ formatDistance(activity.distance_meters) }}</p>
                            </div>
                        </div>
                        <p v-if="!activities.length" class="text-sm text-secondary">Noch keine Einheiten dokumentiert.</p>
                    </div>
                </section>

                <section class="rounded-2xl border border-border bg-card p-4">
                    <h2 class="text-base font-semibold text-primary">Sportart-Parameter</h2>
                    <p class="mt-1 text-sm text-secondary">Die Felder im Plan passen sich der gewaehlten Sportart an.</p>
                    <div class="mt-4 flex flex-wrap gap-2">
                        <span v-for="metric in (selectedSport.metrics || ['Dauer', 'Intensitaet', 'Todo'])" :key="metric" class="rounded-full border border-border px-3 py-1 text-xs font-semibold text-secondary">
                            {{ metric }}
                        </span>
                    </div>
                </section>
            </aside>
        </div>

        <div v-if="activeModal" class="fixed inset-0 z-50 flex items-end justify-center bg-black/70 p-3 sm:items-center" @click.self="closeModal">
            <div class="max-h-[92vh] w-full overflow-y-auto rounded-2xl border border-border bg-bg shadow-2xl" :class="activeModal === 'delete' ? 'max-w-lg' : 'max-w-4xl'">
                <div class="sticky top-0 z-10 flex items-start justify-between gap-4 border-b border-border bg-bg/95 p-4 backdrop-blur">
                    <div>
                        <p class="text-xs font-semibold uppercase tracking-wide text-air-blue">
                            {{ activeModal === 'activity' ? 'Trainingseinheit' : activeModal === 'delete' ? 'Bestaetigen' : 'Trainingsplan' }}
                        </p>
                        <h2 class="mt-1 text-xl font-semibold text-primary">
                            {{ activeModal === 'plan' ? 'Plan erstellen' : activeModal === 'activity' ? 'Einheit eintragen' : activeModal === 'edit' ? 'Plan bearbeiten & freigeben' : activeModal === 'item' ? 'Einheit zum Plan hinzufuegen' : 'Trainingsplan loeschen' }}
                        </h2>
                    </div>
                    <button type="button" class="rounded-xl border border-border px-3 py-2 text-sm font-semibold text-primary hover:bg-muted" @click="closeModal">
                        Schliessen
                    </button>
                </div>

                <form v-if="activeModal === 'activity'" class="grid gap-4 p-4 md:grid-cols-2" @submit.prevent="submitActivity">
                    <label class="block text-sm font-semibold text-primary md:col-span-2">Name
                        <input v-model="activityForm.title" class="mt-2 w-full rounded-xl border border-border bg-inputBg px-3 py-2 text-primary" required />
                    </label>
                    <label class="block text-sm font-semibold text-primary">Sportart
                        <select v-model="activityForm.activity_type" class="mt-2 w-full rounded-xl border border-border bg-inputBg px-3 py-2 text-primary">
                            <option v-for="sport in sports.filter((item) => item.key !== 'all')" :key="sport.key" :value="sport.key">{{ sport.label }}</option>
                        </select>
                    </label>
                    <label class="block text-sm font-semibold text-primary">Datum und Zeit
                        <input v-model="activityForm.started_at" type="datetime-local" class="mt-2 w-full rounded-xl border border-border bg-inputBg px-3 py-2 text-primary" required />
                    </label>
                    <label class="block text-sm font-semibold text-primary">Dauer in Minuten
                        <input v-model="activityForm.duration_minutes" type="number" min="0" class="mt-2 w-full rounded-xl border border-border bg-inputBg px-3 py-2 text-primary" />
                    </label>
                    <label class="block text-sm font-semibold text-primary">Distanz in km
                        <input v-model="activityForm.distance_km" type="number" min="0" step="0.01" class="mt-2 w-full rounded-xl border border-border bg-inputBg px-3 py-2 text-primary" />
                    </label>
                    <label class="block text-sm font-semibold text-primary">Kalorien
                        <input v-model="activityForm.calories" type="number" min="0" class="mt-2 w-full rounded-xl border border-border bg-inputBg px-3 py-2 text-primary" />
                    </label>
                    <label class="block text-sm font-semibold text-primary">Bild
                        <input ref="activityImageInput" type="file" accept="image/*" class="mt-2 w-full rounded-xl border border-border bg-inputBg px-3 py-2 text-primary file:mr-3 file:rounded-md file:border-0 file:bg-buttonPrimary file:px-3 file:py-1 file:text-sm file:font-semibold file:text-buttonTextPrimary" @change="setActivityImage" />
                    </label>
                    <button type="submit" class="md:col-span-2 rounded-xl bg-buttonPrimary px-4 py-2 text-sm font-semibold text-buttonTextPrimary disabled:opacity-60" :disabled="activityForm.processing">
                        Einheit speichern
                    </button>
                </form>

                <form v-if="activeModal === 'plan'" class="space-y-5 p-4" @submit.prevent="submitPlan">
                    <div class="grid gap-4 md:grid-cols-2">
                        <label class="block text-sm font-semibold text-primary md:col-span-2">Planname
                            <input v-model="planForm.title" class="mt-2 w-full rounded-xl border border-border bg-inputBg px-3 py-2 text-primary" required />
                        </label>
                        <label class="block text-sm font-semibold text-primary">Rhythmus
                            <select v-model="planForm.cadence" class="mt-2 w-full rounded-xl border border-border bg-inputBg px-3 py-2 text-primary">
                                <option value="single">Einmalig</option>
                                <option value="daily">Taeglich</option>
                                <option value="weekly">Woechentlich</option>
                                <option value="monthly">Monatlich</option>
                            </select>
                        </label>
                        <label class="block text-sm font-semibold text-primary">Status
                            <select v-model="planForm.status" class="mt-2 w-full rounded-xl border border-border bg-inputBg px-3 py-2 text-primary">
                                <option value="published">Direkt freigeben</option>
                                <option value="draft">Entwurf</option>
                            </select>
                        </label>
                        <label class="block text-sm font-semibold text-primary">Start
                            <input v-model="planForm.starts_on" type="date" class="mt-2 w-full rounded-xl border border-border bg-inputBg px-3 py-2 text-primary" />
                        </label>
                        <label class="block text-sm font-semibold text-primary">Ende
                            <input v-model="planForm.ends_on" type="date" class="mt-2 w-full rounded-xl border border-border bg-inputBg px-3 py-2 text-primary" />
                        </label>
                        <label class="block text-sm font-semibold text-primary md:col-span-2">Beschreibung
                            <textarea v-model="planForm.description" rows="3" class="mt-2 w-full rounded-xl border border-border bg-inputBg px-3 py-2 text-primary" />
                        </label>
                    </div>

                    <div class="rounded-2xl border border-border bg-inputBg/40 p-4">
                        <h3 class="text-sm font-semibold uppercase tracking-wide text-secondary">Erste Einheit</h3>
                        <div class="mt-4 grid gap-4 md:grid-cols-2">
                            <label class="block text-sm font-semibold text-primary">Sportart
                                <select v-model="planForm.item_sport_type" class="mt-2 w-full rounded-xl border border-border bg-inputBg px-3 py-2 text-primary">
                                    <option v-for="sport in sports.filter((item) => item.key !== 'all')" :key="sport.key" :value="sport.key">{{ sport.label }}</option>
                                </select>
                            </label>
                            <label class="block text-sm font-semibold text-primary">Titel
                                <input v-model="planForm.item_title" class="mt-2 w-full rounded-xl border border-border bg-inputBg px-3 py-2 text-primary" required />
                            </label>
                            <label v-for="metric in planSport.metrics" :key="metric" class="block text-sm font-semibold text-primary">
                                {{ metric }}
                                <input v-model="planForm.item_metrics[metric]" class="mt-2 w-full rounded-xl border border-border bg-inputBg px-3 py-2 text-primary" />
                            </label>
                            <label class="block text-sm font-semibold text-primary">Termin
                                <input v-model="planForm.item_scheduled_at" type="datetime-local" class="mt-2 w-full rounded-xl border border-border bg-inputBg px-3 py-2 text-primary" />
                            </label>
                            <label class="block text-sm font-semibold text-primary">Dauer
                                <input v-model="planForm.item_duration_minutes" type="number" min="0" class="mt-2 w-full rounded-xl border border-border bg-inputBg px-3 py-2 text-primary" />
                            </label>
                            <label class="block text-sm font-semibold text-primary md:col-span-2">Todo-Liste
                                <textarea v-model="planForm.item_todos" rows="3" class="mt-2 w-full rounded-xl border border-border bg-inputBg px-3 py-2 text-primary" placeholder="Eine Aufgabe pro Zeile" />
                            </label>
                            <label class="block text-sm font-semibold text-primary">Video-Link
                                <input v-model="planForm.item_video_url" type="url" class="mt-2 w-full rounded-xl border border-border bg-inputBg px-3 py-2 text-primary" />
                            </label>
                            <label class="block text-sm font-semibold text-primary">Bild
                                <input ref="planImageInput" type="file" accept="image/*" class="mt-2 w-full rounded-xl border border-border bg-inputBg px-3 py-2 text-primary file:mr-3 file:rounded-md file:border-0 file:bg-buttonPrimary file:px-3 file:py-1 file:text-sm file:font-semibold file:text-buttonTextPrimary" @change="setPlanImage" />
                            </label>
                        </div>
                    </div>

                    <div class="grid gap-4 md:grid-cols-2">
                        <label class="block text-sm font-semibold text-primary">Team
                            <select v-model="planForm.team_id" class="mt-2 w-full rounded-xl border border-border bg-inputBg px-3 py-2 text-primary">
                                <option value="">Kein komplettes Team</option>
                                <option v-for="team in teams" :key="team.id" :value="team.id">{{ team.name }}</option>
                            </select>
                        </label>
                        <label class="block text-sm font-semibold text-primary">Berechtigung
                            <select v-model="planForm.share_permission" class="mt-2 w-full rounded-xl border border-border bg-inputBg px-3 py-2 text-primary">
                                <option value="read">Nur lesen</option>
                                <option value="write">Mit schreiben / verbessern</option>
                            </select>
                        </label>
                    </div>
                    <div class="rounded-2xl border border-border p-3">
                        <p class="text-sm font-semibold text-primary">Einzelne Sportler</p>
                        <div class="mt-3 grid max-h-44 gap-2 overflow-y-auto sm:grid-cols-2">
                            <label v-for="person in people" :key="person.id" class="flex items-center gap-2 rounded-xl border border-border px-3 py-2 text-sm text-primary">
                                <input type="checkbox" class="rounded border-border bg-inputBg" :checked="planForm.user_ids.map(Number).includes(Number(person.id))" @change="togglePlanUser(person.id)" />
                                <span class="truncate">{{ person.name }}</span>
                            </label>
                        </div>
                        <p v-if="selectedTeamMembers.length" class="mt-3 text-xs text-secondary">Team-Auswahl umfasst {{ selectedTeamMembers.length }} Personen.</p>
                    </div>
                    <button type="submit" class="w-full rounded-xl bg-buttonPrimary px-4 py-2 text-sm font-semibold text-buttonTextPrimary disabled:opacity-60" :disabled="planForm.processing">
                        Plan speichern
                    </button>
                </form>

                <form v-if="activeModal === 'edit'" class="space-y-4 p-4" @submit.prevent="updatePlan">
                    <div class="grid gap-4 md:grid-cols-2">
                        <label class="block text-sm font-semibold text-primary md:col-span-2">Planname
                            <input v-model="editForm.title" class="mt-2 w-full rounded-xl border border-border bg-inputBg px-3 py-2 text-primary" required />
                        </label>
                        <label class="block text-sm font-semibold text-primary">Rhythmus
                            <select v-model="editForm.cadence" class="mt-2 w-full rounded-xl border border-border bg-inputBg px-3 py-2 text-primary">
                                <option value="single">Einmalig</option>
                                <option value="daily">Taeglich</option>
                                <option value="weekly">Woechentlich</option>
                                <option value="monthly">Monatlich</option>
                            </select>
                        </label>
                        <label class="block text-sm font-semibold text-primary">Status
                            <select v-model="editForm.status" class="mt-2 w-full rounded-xl border border-border bg-inputBg px-3 py-2 text-primary">
                                <option value="published">Freigegeben</option>
                                <option value="draft">Entwurf</option>
                            </select>
                        </label>
                        <label class="block text-sm font-semibold text-primary">Team
                            <select v-model="editForm.team_id" class="mt-2 w-full rounded-xl border border-border bg-inputBg px-3 py-2 text-primary">
                                <option value="">Kein komplettes Team</option>
                                <option v-for="team in teams" :key="team.id" :value="team.id">{{ team.name }}</option>
                            </select>
                        </label>
                        <label class="block text-sm font-semibold text-primary">Berechtigung
                            <select v-model="editForm.share_permission" class="mt-2 w-full rounded-xl border border-border bg-inputBg px-3 py-2 text-primary">
                                <option value="read">Nur lesen</option>
                                <option value="write">Mit schreiben / verbessern</option>
                            </select>
                        </label>
                        <label class="block text-sm font-semibold text-primary md:col-span-2">Beschreibung
                            <textarea v-model="editForm.description" rows="3" class="mt-2 w-full rounded-xl border border-border bg-inputBg px-3 py-2 text-primary" />
                        </label>
                    </div>
                    <div class="rounded-2xl border border-border p-3">
                        <p class="text-sm font-semibold text-primary">Einzelne Sportler</p>
                        <div class="mt-3 grid max-h-44 gap-2 overflow-y-auto sm:grid-cols-2">
                            <label v-for="person in people" :key="person.id" class="flex items-center gap-2 rounded-xl border border-border px-3 py-2 text-sm text-primary">
                                <input type="checkbox" class="rounded border-border bg-inputBg" :checked="editForm.user_ids.map(Number).includes(Number(person.id))" @change="togglePlanUser(person.id, editForm)" />
                                <span class="truncate">{{ person.name }}</span>
                            </label>
                        </div>
                    </div>
                    <button type="submit" class="w-full rounded-xl bg-buttonPrimary px-4 py-2 text-sm font-semibold text-buttonTextPrimary disabled:opacity-60" :disabled="editForm.processing">
                        Aenderungen speichern
                    </button>
                </form>

                <form v-if="activeModal === 'item'" class="grid gap-4 p-4 md:grid-cols-2" @submit.prevent="submitPlanItem">
                    <label class="block text-sm font-semibold text-primary">Sportart
                        <select v-model="itemForm.sport_type" class="mt-2 w-full rounded-xl border border-border bg-inputBg px-3 py-2 text-primary">
                            <option v-for="sport in sports.filter((item) => item.key !== 'all')" :key="sport.key" :value="sport.key">{{ sport.label }}</option>
                        </select>
                    </label>
                    <label class="block text-sm font-semibold text-primary">Titel
                        <input v-model="itemForm.title" class="mt-2 w-full rounded-xl border border-border bg-inputBg px-3 py-2 text-primary" required />
                    </label>
                    <label v-for="metric in itemSport.metrics" :key="metric" class="block text-sm font-semibold text-primary">
                        {{ metric }}
                        <input v-model="itemForm.metrics[metric]" class="mt-2 w-full rounded-xl border border-border bg-inputBg px-3 py-2 text-primary" />
                    </label>
                    <label class="block text-sm font-semibold text-primary">Termin
                        <input v-model="itemForm.scheduled_at" type="datetime-local" class="mt-2 w-full rounded-xl border border-border bg-inputBg px-3 py-2 text-primary" />
                    </label>
                    <label class="block text-sm font-semibold text-primary">Dauer
                        <input v-model="itemForm.duration_minutes" type="number" min="0" class="mt-2 w-full rounded-xl border border-border bg-inputBg px-3 py-2 text-primary" />
                    </label>
                    <label class="block text-sm font-semibold text-primary md:col-span-2">Beschreibung
                        <textarea v-model="itemForm.description" rows="3" class="mt-2 w-full rounded-xl border border-border bg-inputBg px-3 py-2 text-primary" />
                    </label>
                    <label class="block text-sm font-semibold text-primary md:col-span-2">Todo-Liste
                        <textarea v-model="itemForm.todos" rows="3" class="mt-2 w-full rounded-xl border border-border bg-inputBg px-3 py-2 text-primary" placeholder="Eine Aufgabe pro Zeile" />
                    </label>
                    <label class="block text-sm font-semibold text-primary">Video-Link
                        <input v-model="itemForm.video_url" type="url" class="mt-2 w-full rounded-xl border border-border bg-inputBg px-3 py-2 text-primary" />
                    </label>
                    <label class="block text-sm font-semibold text-primary">Bild
                        <input ref="itemImageInput" type="file" accept="image/*" class="mt-2 w-full rounded-xl border border-border bg-inputBg px-3 py-2 text-primary file:mr-3 file:rounded-md file:border-0 file:bg-buttonPrimary file:px-3 file:py-1 file:text-sm file:font-semibold file:text-buttonTextPrimary" @change="setItemImage" />
                    </label>
                    <button type="submit" class="md:col-span-2 rounded-xl bg-buttonPrimary px-4 py-2 text-sm font-semibold text-buttonTextPrimary disabled:opacity-60" :disabled="itemForm.processing">
                        Einheit hinzufuegen
                    </button>
                </form>

                <div v-if="activeModal === 'delete'" class="p-4">
                    <p class="text-sm text-secondary">
                        Der Plan <span class="font-semibold text-primary">{{ selectedPlan?.title }}</span> wird inklusive Einheiten und Bildern geloescht. Tippe <span class="font-semibold text-danger">delete</span>, um fortzufahren.
                    </p>
                    <input v-model="deleteText" class="mt-4 w-full rounded-xl border border-border bg-inputBg px-3 py-2 text-primary" placeholder="delete" />
                    <div class="mt-4 flex justify-end gap-2">
                        <button type="button" class="rounded-xl border border-border px-4 py-2 text-sm font-semibold text-primary" @click="closeModal">Abbrechen</button>
                        <button type="button" class="rounded-xl bg-danger px-4 py-2 text-sm font-semibold text-white disabled:opacity-50" :disabled="deleteText !== 'delete'" @click="deletePlan">
                            Endgueltig loeschen
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>
