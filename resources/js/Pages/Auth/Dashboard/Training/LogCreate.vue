<script setup>
import AppLayout from '@/Components/Auth/Layouts/AppLayout.vue'
import SearchableSelect from '@/Components/SearchableSelect.vue'
import { Head, Link, useForm, usePage } from '@inertiajs/vue3'
import { computed, onMounted, onUnmounted, ref, watch } from 'vue'
import { useI18n } from 'vue-i18n'

defineOptions({ layout: AppLayout })

const { t, locale } = useI18n()
const tx = (key, params = {}) => t(key, params)

const props = defineProps({
    plans: { type: Array, default: () => [] },
    manageableAthletes: { type: Array, default: () => [] },
    sportCatalog: { type: Array, default: () => [] },
    teams: { type: Array, default: () => [] },
    recentExercises: { type: Object, default: () => ({}) },
    recentSports: { type: Object, default: () => ({}) },
    draftLog: { type: Object, default: null },
    prefillPlanItemId: { type: Number, default: null },
    prefillEvent: { type: Object, default: null },
    sportRoutes: { type: Array, default: () => [] },
    sportRouteTracks: { type: Array, default: () => [] },
})

const page = usePage()

const fallbackSports = [
    { key: 'laufen', label: 'Laufen', category: 'Schnellauswahl' },
    { key: 'gym', label: 'Gym', category: 'Schnellauswahl' },
    { key: 'schwimmen', label: 'Schwimmen', category: 'Schnellauswahl' },
    { key: 'fussball', label: 'Fußball', category: 'Schnellauswahl' },
    { key: 'cycling', label: 'Radfahren', category: 'Schnellauswahl' },
    { key: 'yoga', label: 'Yoga', category: 'Schnellauswahl' },
]

const trainingTypes = [
    {
        key: 'gym',
        label: tx('training_workspace.log_create.types.gym.label'),
        shortLabel: tx('training_workspace.log_create.types.gym.short'),
        icon: 'las la-dumbbell',
        sport_type: 'gym',
        title: tx('training_workspace.log_create.types.gym.label'),
        fields: ['sets', 'reps', 'weight_kg', 'duration_minutes', 'intensity', 'notes'],
        mode: 'sets',
        detailTitle: tx('training_workspace.log_create.types.gym.detail'),
        entryLabel: tx('training_workspace.log_create.types.gym.entry_label'),
        entryPlaceholder: tx('training_workspace.log_create.types.gym.entry_placeholder'),
    },
    {
        key: 'run_interval',
        label: tx('training_workspace.log_create.types.run_interval.label'),
        shortLabel: tx('training_workspace.log_create.types.run_interval.short'),
        icon: 'las la-stopwatch',
        sport_type: 'laufen',
        title: tx('training_workspace.log_create.types.run_interval.label'),
        fields: ['reps', 'distance_km', 'duration_minutes', 'intensity', 'notes'],
        mode: 'rows',
        detailTitle: tx('training_workspace.log_create.types.run_interval.detail'),
        entryLabel: tx('training_workspace.log_create.types.run_interval.entry_label'),
        entryPlaceholder: tx('training_workspace.log_create.types.run_interval.entry_placeholder'),
    },
    {
        key: 'long_run',
        label: tx('training_workspace.log_create.types.long_run.label'),
        shortLabel: tx('training_workspace.log_create.types.long_run.short'),
        icon: 'las la-route',
        sport_type: 'laufen',
        title: tx('training_workspace.log_create.types.long_run.label'),
        fields: ['distance_km', 'duration_minutes', 'intensity', 'notes'],
        mode: 'rows',
        detailTitle: tx('training_workspace.log_create.types.long_run.detail'),
        entryLabel: tx('training_workspace.log_create.types.long_run.entry_label'),
        entryPlaceholder: tx('training_workspace.log_create.types.long_run.entry_placeholder'),
    },
    {
        key: 'swim',
        label: tx('training_workspace.log_create.types.swim.label'),
        shortLabel: tx('training_workspace.log_create.types.swim.short'),
        icon: 'las la-swimmer',
        sport_type: 'schwimmen',
        title: tx('training_workspace.log_create.types.swim.label'),
        fields: ['sets', 'reps', 'distance_km', 'duration_minutes', 'intensity', 'notes'],
        mode: 'rows',
        detailTitle: tx('training_workspace.log_create.types.swim.detail'),
        entryLabel: tx('training_workspace.log_create.types.swim.entry_label'),
        entryPlaceholder: tx('training_workspace.log_create.types.swim.entry_placeholder'),
    },
    {
        key: 'football',
        label: tx('training_workspace.log_create.types.football.label'),
        shortLabel: tx('training_workspace.log_create.types.football.short'),
        icon: 'las la-futbol',
        sport_type: 'fussball',
        title: tx('training_workspace.log_create.types.football.label'),
        fields: ['duration_minutes', 'distance_km', 'intensity', 'notes'],
        mode: 'rows',
        detailTitle: tx('training_workspace.log_create.types.football.detail'),
        entryLabel: tx('training_workspace.log_create.types.football.entry_label'),
        entryPlaceholder: tx('training_workspace.log_create.types.football.entry_placeholder'),
    },
    {
        key: 'cycling',
        label: tx('training_workspace.log_create.types.cycling.label'),
        shortLabel: tx('training_workspace.log_create.types.cycling.short'),
        icon: 'las la-biking',
        sport_type: 'cycling',
        title: tx('training_workspace.log_create.types.cycling.label'),
        fields: ['distance_km', 'duration_minutes', 'intensity', 'notes'],
        mode: 'rows',
        detailTitle: tx('training_workspace.log_create.types.cycling.detail'),
        entryLabel: tx('training_workspace.log_create.types.cycling.entry_label'),
        entryPlaceholder: tx('training_workspace.log_create.types.cycling.entry_placeholder'),
    },
    {
        key: 'generic',
        label: tx('training_workspace.log_create.types.generic.label'),
        shortLabel: tx('training_workspace.log_create.types.generic.short'),
        icon: 'las la-clipboard-list',
        sport_type: 'laufen',
        title: tx('training_workspace.log_create.types.generic.label'),
        fields: ['sets', 'reps', 'weight_kg', 'duration_minutes', 'distance_km', 'intensity', 'notes'],
        mode: 'rows',
        detailTitle: tx('training_workspace.log_create.types.generic.detail'),
        entryLabel: tx('training_workspace.log_create.types.generic.entry_label'),
        entryPlaceholder: tx('training_workspace.log_create.types.generic.entry_placeholder'),
    },
]

const detailTemplates = {
    run_interval: [
        {
            key: 'classic_400',
            label: '6 x 400 m',
            description: 'Einlaufen, 6 schnelle Wiederholungen, Auslaufen.',
            entries: [
                { title: 'Einlaufen', duration_minutes: 10, distance_km: 1.5, intensity: 'locker' },
                { title: '6 x 400 m schnell', reps: 6, distance_km: 0.4, intensity: 'RPE 8' },
                { title: 'Trabpause', reps: 6, duration_minutes: 1.5, intensity: 'locker' },
                { title: 'Auslaufen', duration_minutes: 10, distance_km: 1.5, intensity: 'locker' },
            ],
        },
        {
            key: 'pyramid',
            label: 'Pyramide',
            description: '200-400-800-400-200 mit lockeren Pausen.',
            entries: [
                { title: 'Einlaufen', duration_minutes: 12, intensity: 'locker' },
                { title: '200-400-800-400-200 m', reps: 5, distance_km: 2, intensity: 'RPE 7-9' },
                { title: 'Pausen locker traben', duration_minutes: 8, intensity: 'locker' },
            ],
        },
    ],
    long_run: [
        {
            key: 'steady',
            label: 'Ruhiger Dauerlauf',
            description: 'Nur Gesamtdaten plus Gefühl dokumentieren.',
            entries: [],
        },
        {
            key: 'finish',
            label: 'Endbeschleunigung',
            description: 'Lockerer Start, schneller Abschluss.',
            entries: [
                { title: 'Locker laufen', distance_km: 8, intensity: 'Zone 2' },
                { title: 'Endbeschleunigung', distance_km: 3, intensity: 'RPE 7' },
                { title: 'Cool-down', distance_km: 1, intensity: 'locker' },
            ],
        },
    ],
    swim: [
        {
            key: 'technique',
            label: 'Technik + Ausdauer',
            description: 'Einschwimmen, Technikserie, Hauptserie.',
            entries: [
                { title: 'Einschwimmen', distance_km: 0.2, intensity: 'locker' },
                { title: '6 x 50 m Technik', reps: 6, distance_km: 0.05, intensity: 'sauber' },
                { title: '4 x 100 m Kraul', reps: 4, distance_km: 0.1, intensity: 'mittel' },
                { title: 'Ausschwimmen', distance_km: 0.1, intensity: 'locker' },
            ],
        },
    ],
    football: [
        {
            key: 'team_session',
            label: 'Teamtraining',
            description: 'Aktivierung, Technik, Spielform, Abschluss.',
            entries: [
                { title: 'Aktivierung und Mobilität', duration_minutes: 12, intensity: 'locker' },
                { title: 'Passform / Technik', duration_minutes: 18, intensity: 'mittel' },
                { title: 'Spielform 4 gegen 4', duration_minutes: 25, intensity: 'hoch' },
                { title: 'Torschuss / Standards', duration_minutes: 15, intensity: 'mittel' },
            ],
        },
    ],
    cycling: [
        {
            key: 'zone2',
            label: 'Zone 2 Ride',
            description: 'Grundlage mit ruhiger Intensität.',
            entries: [
                { title: 'Einrollen', duration_minutes: 10, intensity: 'locker' },
                { title: 'Zone 2 Block', duration_minutes: 60, intensity: 'Zone 2' },
                { title: 'Ausrollen', duration_minutes: 10, intensity: 'locker' },
            ],
        },
    ],
}

const emptyEntry = () => ({
    title: '',
    sets: '',
    reps: '',
    weight_kg: '',
    duration_minutes: '',
    distance_km: '',
    intensity: '',
    notes: '',
    media_url: '',
    media_file: null,
})

const emptyGymSet = () => ({
    reps: '',
    weight_kg: '',
    duration_minutes: '',
    intensity: '',
    notes: '',
    media_url: '',
    media_file: null,
    completed: false,
})

const emptyGymExercise = () => ({
    title: '',
    notes: '',
    sets: [emptyGymSet()],
})

const form = useForm({
    draft_log_id: props.draftLog?.id || '',
    user_id: '',
    team_id: '',
    training_plan_item_id: '',
    sport_route_id: '',
    sport_route_track_id: '',
    title: trainingTypes[0].title,
    sport_type: trainingTypes[0].sport_type,
    training_type: trainingTypes[0].key,
    status: 'completed',
    privacy_scope: 'trainer',
    performed_at: '',
    duration_minutes: '',
    distance_km: '',
    calories: '',
    intensity: 'mittel',
    notes: '',
    trainer_feedback: '',
    notify_people: true,
    wellness: {
        rpe: '',
        energy: '',
        pain: '',
        sleep_hours: '',
    },
    entries: [emptyEntry()],
    gym_exercises: [emptyGymExercise()],
})

const restSeconds = ref(0)
const liveNow = ref(Date.now())
const autosaveStatus = ref(props.draftLog ? 'saved' : 'idle')
const autosaveSavedAt = ref(props.draftLog?.updated_at || null)
const autosaveError = ref('')
const activeGymExerciseIndex = ref(0)
const activeGymSetIndex = ref(0)
const activeEntryIndex = ref(0)
const currentTrainingStep = ref(1)
const mobileLivePanelOpen = ref(false)
const mobileTrainingTypeSheetOpen = ref(false)
let restInterval = null
let liveInterval = null
let autosaveTimer = null
let autosaveRequestId = 0

const trainingSteps = [
    { id: 1, label: tx('training_workspace.log_create.steps.1.label'), short: tx('training_workspace.log_create.steps.1.short') },
    { id: 2, label: tx('training_workspace.log_create.steps.2.label'), short: tx('training_workspace.log_create.steps.2.short') },
    { id: 3, label: tx('training_workspace.log_create.steps.3.label'), short: tx('training_workspace.log_create.steps.3.short') },
]

const trainingTypeThemes = {
    gym: {
        active: 'border-sky-400 bg-sky-500 text-white shadow-sm shadow-sky-500/20',
        idle: 'border-sky-500/30 bg-sky-500/10 text-sky-100 hover:border-sky-400/70 hover:bg-sky-500/20',
        icon: 'bg-sky-400/20 text-sky-100',
    },
    run_interval: {
        active: 'border-amber-300 bg-amber-400 text-slate-950 shadow-sm shadow-amber-400/20',
        idle: 'border-amber-400/30 bg-amber-400/10 text-amber-100 hover:border-amber-300/70 hover:bg-amber-400/20',
        icon: 'bg-amber-300/20 text-amber-100',
    },
    long_run: {
        active: 'border-emerald-300 bg-emerald-500 text-white shadow-sm shadow-emerald-500/20',
        idle: 'border-emerald-400/30 bg-emerald-500/10 text-emerald-100 hover:border-emerald-300/70 hover:bg-emerald-500/20',
        icon: 'bg-emerald-400/20 text-emerald-100',
    },
    swim: {
        active: 'border-cyan-300 bg-cyan-500 text-slate-950 shadow-sm shadow-cyan-500/20',
        idle: 'border-cyan-400/30 bg-cyan-500/10 text-cyan-100 hover:border-cyan-300/70 hover:bg-cyan-500/20',
        icon: 'bg-cyan-300/20 text-cyan-100',
    },
    football: {
        active: 'border-lime-300 bg-lime-500 text-slate-950 shadow-sm shadow-lime-500/20',
        idle: 'border-lime-400/30 bg-lime-500/10 text-lime-100 hover:border-lime-300/70 hover:bg-lime-500/20',
        icon: 'bg-lime-300/20 text-lime-100',
    },
    cycling: {
        active: 'border-fuchsia-300 bg-fuchsia-500 text-white shadow-sm shadow-fuchsia-500/20',
        idle: 'border-fuchsia-400/30 bg-fuchsia-500/10 text-fuchsia-100 hover:border-fuchsia-300/70 hover:bg-fuchsia-500/20',
        icon: 'bg-fuchsia-400/20 text-fuchsia-100',
    },
    generic: {
        active: 'border-indigo-300 bg-indigo-500 text-white shadow-sm shadow-indigo-500/20',
        idle: 'border-indigo-400/30 bg-indigo-500/10 text-indigo-100 hover:border-indigo-300/70 hover:bg-indigo-500/20',
        icon: 'bg-indigo-400/20 text-indigo-100',
    },
}

const trainingTypeTheme = (key) => trainingTypeThemes[key] || trainingTypeThemes.generic

const trainingTypeButtonClass = (type) => form.training_type === type.key
    ? trainingTypeTheme(type.key).active
    : trainingTypeTheme(type.key).idle

const sportChoices = computed(() => {
    const catalog = (props.sportCatalog || [])
        .map((sport) => ({
            key: sport.slug || sport.name,
            label: sport.name,
            slug: sport.slug,
            category: sport.category,
        }))
        .filter((sport) => sport.key)

        return [...fallbackSports.map((sport) => ({ ...sport, label: tx(`training_workspace.sports.${sport.key}`), category: tx('training_workspace.log_create.quick_choice') })), ...catalog]
        .filter((sport, index, list) => list.findIndex((item) => item.key === sport.key) === index)
})

const athleteOptions = computed(() => [
    { id: '', name: tx('training_workspace.log_create.self') },
    ...(props.manageableAthletes || []),
])

const plannedItems = computed(() => props.plans
    .flatMap((plan) => (plan.items || []).map((item) => ({ ...item, plan })))
    .sort((a, b) => new Date(a.scheduled_at || 0) - new Date(b.scheduled_at || 0)))

const selectedPlannedItem = computed(() => plannedItems.value.find((entry) => Number(entry.id) === Number(form.training_plan_item_id)) || null)
const selectedSportRoute = computed(() => props.sportRoutes.find((entry) => Number(entry.id) === Number(form.sport_route_id)) || null)
const selectedType = computed(() => trainingTypes.find((type) => type.key === form.training_type) || trainingTypes[trainingTypes.length - 1])
const selectedTypeLabel = computed(() => tx(`training_workspace.log_create.types.${selectedType.value.key}.label`))
const selectedTypeDetailTitle = computed(() => tx(`training_workspace.log_create.types.${selectedType.value.key}.detail`))
const visibleFields = computed(() => selectedType.value.fields || [])
const usesGymSets = computed(() => selectedType.value.mode === 'sets')
const selectedTemplates = computed(() => detailTemplates[selectedType.value.key] || [])
const hasField = (field) => field !== 'intensity' && visibleFields.value.includes(field)
const showSessionDistance = computed(() => !usesGymSets.value && hasField('distance_km'))
const showQuickDistanceAction = computed(() => !usesGymSets.value && hasField('distance_km'))

const visibleEntries = computed(() => form.entries.filter((entry) => entry.title || entry.distance_km || entry.duration_minutes || entry.notes))

const gymSetCount = computed(() => form.gym_exercises.reduce((total, exercise) => total + exercise.sets.length, 0))
const completedGymSetCount = computed(() => form.gym_exercises.reduce(
    (total, exercise) => total + exercise.sets.filter((set) => set.completed).length,
    0,
))
const totalEntryDistanceKm = computed(() => form.entries.reduce((total, entry) => total + Number(entry.distance_km || 0), 0))
const totalEntryMinutes = computed(() => form.entries.reduce((total, entry) => total + Number(entry.duration_minutes || 0), 0))
const gymVolumeKg = computed(() => form.gym_exercises.reduce((total, exercise) => total + exercise.sets.reduce((setTotal, set) => {
    const reps = Number(set.reps || 0)
    const weight = Number(set.weight_kg || 0)

    return setTotal + reps * weight
}, 0), 0))
const sessionDistanceKm = computed(() => Number(form.distance_km || 0) || totalEntryDistanceKm.value)
const sessionMinutes = computed(() => Number(form.duration_minutes || 0) || totalEntryMinutes.value)
const sessionPace = computed(() => {
    if (!sessionDistanceKm.value || !sessionMinutes.value) return ''

    const minutesPerKm = sessionMinutes.value / sessionDistanceKm.value
    const minutes = Math.floor(minutesPerKm)
    const seconds = Math.round((minutesPerKm - minutes) * 60).toString().padStart(2, '0')

    return `${minutes}:${seconds} min/km`
})
const sessionSpeedKmh = computed(() => {
    if (!sessionDistanceKm.value || !sessionMinutes.value) return ''

    const kmh = sessionDistanceKm.value / (sessionMinutes.value / 60)

    return `${formatNumber(kmh, 1)} km/h`
})
const selectedAthleteId = computed(() => String(form.user_id || page.props.auth?.user?.id || ''))
const recentForAthlete = computed(() => props.recentExercises?.[selectedAthleteId.value] || [])
const recentSportChoices = computed(() => (props.recentSports?.[selectedAthleteId.value] || [])
    .map((sportKey) => sportChoices.value.find((sport) => sport.key === sportKey) || { key: sportKey, label: sportKey })
    .filter((sport, index, list) => sport.key && list.findIndex((item) => item.key === sport.key) === index)
    .slice(0, 5))
const activeGymExercise = computed(() => form.gym_exercises[activeGymExerciseIndex.value] || null)
const activeGymSet = computed(() => activeGymExercise.value?.sets?.[activeGymSetIndex.value] || null)
const activeEntry = computed(() => form.entries[activeEntryIndex.value] || null)
const isLiveTraining = computed(() => form.status === 'in_progress')
const liveElapsedSeconds = computed(() => {
    if (!isLiveTraining.value || !form.performed_at) return 0

    const startedAt = new Date(form.performed_at).getTime()
    if (Number.isNaN(startedAt)) return 0

    return Math.max(0, Math.floor((liveNow.value - startedAt) / 1000))
})

const restTimerLabel = computed(() => {
    const minutes = Math.floor(restSeconds.value / 60).toString().padStart(2, '0')
    const seconds = (restSeconds.value % 60).toString().padStart(2, '0')

    return `${minutes}:${seconds}`
})

const liveElapsedLabel = computed(() => {
    const hours = Math.floor(liveElapsedSeconds.value / 3600).toString().padStart(2, '0')
    const minutes = Math.floor((liveElapsedSeconds.value % 3600) / 60).toString().padStart(2, '0')
    const seconds = (liveElapsedSeconds.value % 60).toString().padStart(2, '0')

    return `${hours}:${minutes}:${seconds}`
})

const detailSummary = computed(() => {
    if (usesGymSets.value) {
        return tx('training_workspace.log_create.summary.gym', { exercises: form.gym_exercises.length, completed: completedGymSetCount.value, sets: gymSetCount.value })
    }

    if (selectedType.value.key === 'long_run' && !visibleEntries.value.length) {
        return tx('training_workspace.log_create.summary.long_run')
    }

    return tx('training_workspace.log_create.summary.entries', { count: visibleEntries.value.length })
})

const hasAnyMedia = computed(() => {
    if (usesGymSets.value) {
        return form.gym_exercises.some((exercise) => exercise.sets.some((set) => set.media_url || set.media_file))
    }

    return form.entries.some((entry) => entry.media_url || entry.media_file)
})

const hasTrainingDetails = computed(() => usesGymSets.value
    ? form.gym_exercises.some((exercise) => exercise.title && exercise.sets.some((set) => set.reps || set.weight_kg || set.duration_minutes || set.notes))
    : selectedType.value.key === 'long_run'
        ? Boolean(form.duration_minutes || form.distance_km || visibleEntries.value.length)
        : Boolean(visibleEntries.value.length))

const documentationChecklist = computed(() => [
    { label: tx('training_workspace.log_create.checklist.basics'), done: Boolean(form.title && form.sport_type && form.status) },
    { label: tx('training_workspace.log_create.checklist.time'), done: Boolean(form.performed_at) },
    { label: tx('training_workspace.log_create.checklist.load'), done: Boolean(sessionMinutes.value || sessionDistanceKm.value || completedGymSetCount.value || form.intensity) },
    { label: tx('training_workspace.log_create.checklist.details'), done: hasTrainingDetails.value },
    { label: tx('training_workspace.log_create.checklist.wellness'), done: Boolean(form.wellness.rpe || form.wellness.energy || form.wellness.pain || form.wellness.sleep_hours) },
    { label: tx('training_workspace.log_create.checklist.notes_media'), done: Boolean(form.notes || hasAnyMedia.value) },
    { label: tx('training_workspace.log_create.checklist.visibility'), done: Boolean(form.privacy_scope) },
])

const documentationScore = computed(() => Math.round(
    (documentationChecklist.value.filter((item) => item.done).length / documentationChecklist.value.length) * 100,
))

const documentationScoreClass = computed(() => {
    if (documentationScore.value >= 85) return 'text-success'
    if (documentationScore.value >= 60) return 'text-warning'

    return 'text-danger'
})

const toLocalDateTime = (value) => {
    if (!value) return ''
    const date = new Date(value)
    if (Number.isNaN(date.getTime())) return ''
    const offsetDate = new Date(date.getTime() - date.getTimezoneOffset() * 60000)
    return offsetDate.toISOString().slice(0, 16)
}

const formatDate = (value) => {
    if (!value) return ''
    return new Intl.DateTimeFormat(locale.value === 'ar' ? 'ar-EG' : (locale.value === 'fr' ? 'fr-FR' : (locale.value === 'en' ? 'en-US' : 'de-DE')), { day: '2-digit', month: '2-digit', year: 'numeric' }).format(new Date(value))
}

const formatSaveTime = (value) => {
    if (!value) return ''
    return new Intl.DateTimeFormat(locale.value === 'ar' ? 'ar-EG' : (locale.value === 'fr' ? 'fr-FR' : (locale.value === 'en' ? 'en-US' : 'de-DE')), { hour: '2-digit', minute: '2-digit', second: '2-digit' }).format(new Date(value))
}

const formatShortDate = (value) => {
    if (!value) return ''
    return new Intl.DateTimeFormat(locale.value === 'ar' ? 'ar-EG' : (locale.value === 'fr' ? 'fr-FR' : (locale.value === 'en' ? 'en-US' : 'de-DE')), { day: '2-digit', month: '2-digit' }).format(new Date(value))
}

const formatNumber = (value, maximumFractionDigits = 1) => Number(value || 0).toLocaleString(locale.value === 'ar' ? 'ar-EG' : (locale.value === 'fr' ? 'fr-FR' : (locale.value === 'en' ? 'en-US' : 'de-DE')), { maximumFractionDigits })

const roundedLiveMinutes = () => Math.max(1, Math.round(liveElapsedSeconds.value / 60))

const setDurationFromLive = () => {
    if (!liveElapsedSeconds.value) return
    form.duration_minutes = String(roundedLiveMinutes())
}

const fieldLabel = (field) => tx({
    sets: 'training_workspace.metrics.Sätze',
    reps: 'training_workspace.metrics.Wiederholungen',
    weight_kg: 'training_workspace.metrics.Gewicht kg',
    duration_minutes: 'training_workspace.log_create.fields.duration',
    distance_km: 'training_workspace.log_create.fields.distance',
    intensity: 'training_workspace.log_create.fields.intensity',
    notes: 'training_workspace.log_create.fields.notes',
}[field] || field)

const normalizeEntry = (entry = {}) => ({
    ...emptyEntry(),
    title: entry.title || '',
    sets: entry.sets ?? '',
    reps: entry.reps ?? '',
    weight_kg: entry.weight_kg ?? '',
    duration_minutes: entry.duration_minutes ?? '',
    distance_km: entry.distance_km ?? '',
    intensity: entry.intensity ?? '',
    notes: entry.notes ?? '',
    media_url: entry.media_url ?? entry.metrics?.media_url ?? entry.metrics?.uploaded_media_url ?? '',
    media_file: null,
})

const applyDetailTemplate = (template) => {
    form.entries = template.entries.length
        ? template.entries.map(normalizeEntry)
        : []

    const distance = form.entries.reduce((total, entry) => total + Number(entry.distance_km || 0), 0)
    const minutes = form.entries.reduce((total, entry) => total + Number(entry.duration_minutes || 0), 0)

    if (!form.distance_km && distance) form.distance_km = String(distance)
    if (!form.duration_minutes && minutes) form.duration_minutes = String(minutes)
}

const decimalForInput = (value, digits = 2) => {
    const number = Number(value)
    if (!number) return ''

    return number.toFixed(digits).replace(/\.?0+$/, '')
}

const parsePlannedEntryTime = (text) => {
    const normalized = String(text || '').toLowerCase()
    const mmss = normalized.match(/\b(\d{1,2}):(\d{2})\s*(?:min|minute|minuten)?\b/)
    if (mmss) {
        return decimalForInput(Number(mmss[1]) + (Number(mmss[2]) / 60), 2)
    }

    const seconds = normalized.match(/\b(?:in\s*)?(\d+(?:[.,]\d+)?)\s*(?:s|sek|sekunden)\b/)
    if (seconds) {
        return decimalForInput(Number(seconds[1].replace(',', '.')) / 60, 2)
    }

    const minutes = normalized.match(/\b(?:in\s*)?(\d+(?:[.,]\d+)?)\s*(?:min|minute|minuten)\b/)
    if (minutes) {
        return decimalForInput(Number(minutes[1].replace(',', '.')), 2)
    }

    return ''
}

const plannedEntryFromItem = (item) => {
    if (!item || usesGymSets.value) return emptyEntry()

    const text = planItemSearchText(item)
    const primaryText = [
        item.title,
        item.description,
        item.metrics?.Zeit,
        item.metrics?.Dauer,
        item.metrics?.Intervallzeit,
        item.metrics?.['Intervall Zeit'],
        item.metrics?.['Pace Ziel'],
    ].filter(Boolean).join(' ')
    const repeatedDistance = text.match(/\b(\d+)\s*(?:x|\*)\s*(\d+(?:[.,]\d+)?)\s*(m|meter|km)\b/)
    const entry = emptyEntry()

    if (repeatedDistance) {
        const reps = Number(repeatedDistance[1])
        const distanceValue = Number(repeatedDistance[2].replace(',', '.'))
        const unit = repeatedDistance[3]
        const distanceKm = unit === 'km' ? distanceValue : distanceValue / 1000

        entry.title = `${reps} x ${unit === 'km' ? decimalForInput(distanceValue, 2) : Math.round(distanceValue)} ${unit === 'km' ? 'km' : 'm'}`
        entry.reps = String(reps)
        entry.distance_km = decimalForInput(distanceKm, 3)
        entry.duration_minutes = parsePlannedEntryTime(primaryText)
        entry.intensity = item.metrics?.RPE ? `RPE ${item.metrics.RPE}` : (item.intensity || '')

        return entry
    }

    if (item.distance_meters && hasField('distance_km')) {
        entry.title = item.title || selectedType.value.entryLabel
        entry.distance_km = decimalForInput(Number(item.distance_meters) / 1000, 2)
    }

    entry.duration_minutes = parsePlannedEntryTime(text)
    entry.intensity = item.metrics?.RPE ? `RPE ${item.metrics.RPE}` : (item.intensity || '')

    return entry
}

const isBlankEntry = (entry) => !Object.entries(entry || {})
    .some(([key, value]) => key !== 'media_file' && value !== null && value !== undefined && value !== '')

const inferTrainingTypeFromDraft = (log) => {
    const savedType = log?.metrics?.training_type
    if (savedType && trainingTypes.some((type) => type.key === savedType)) return savedType

    if (log?.sport_type === 'gym') return 'gym'
    if (log?.sport_type === 'schwimmen') return 'swim'
    if (log?.sport_type === 'fussball') return 'football'
    if (log?.sport_type === 'cycling') return 'cycling'

    return 'generic'
}

const planItemSearchText = (item = {}) => [
    item.title,
    item.description,
    item.intensity,
    item.metrics?.Fokus,
    item.metrics?.Belastung,
    ...(item.todos || []),
    ...Object.keys(item.metrics || {}),
    ...Object.values(item.metrics || {}),
].filter(Boolean).join(' ').toLowerCase()

const inferTrainingTypeFromPlanItem = (item = {}) => {
    const savedType = item.metrics?._training_type || item.metrics?.training_type || item.metrics?.Trainingstyp
    if (savedType && trainingTypes.some((type) => type.key === savedType)) return savedType

    const sport = String(item.sport_type || '').toLowerCase()
    const text = planItemSearchText(item)

    if (sport === 'gym' || text.match(/\b(Sätze|sätze|wiederholungen|gewicht|kraft|bankdrücken|bankdrücken|kniebeuge|deadlift)\b/)) return 'gym'
    if (sport === 'schwimmen') return 'swim'
    if (sport === 'fussball' || sport === 'football') return 'football'
    if (sport === 'cycling' || sport === 'radfahren' || sport === 'bike') return 'cycling'

    if (sport === 'laufen' || sport === 'running') {
        if (text.match(/\b(intervall|intervalle|interval|400\s*m|800\s*m|sprint|tempolauf|tempo|rpe\s*8|trabpause|wiederholung)\b/) || text.match(/\b\d+\s*x\s*\d+/)) {
            return 'run_interval'
        }

        if (text.match(/\b(long run|dauerlauf|zone\s*2|grundlage|ausdauer|locker|endbeschleunigung)\b/)) {
            return 'long_run'
        }

        return 'long_run'
    }

    return 'generic'
}

const entriesToGymExercises = (entries = []) => {
    const grouped = new Map()

    entries.forEach((entry) => {
        const match = String(entry.title || '').match(/^(.*)\s+-\s+Satz\s+\d+$/i)
        const title = (match?.[1] || entry.title || '').trim()
        if (!title) return

        if (!grouped.has(title)) {
            grouped.set(title, { title, notes: '', sets: [] })
        }

        const cleanedNotes = String(entry.notes || '').replace('Erledigt | ', '').replace('Erledigt', '').trim()
        grouped.get(title).sets.push({
            ...emptyGymSet(),
            reps: entry.reps ?? '',
            weight_kg: entry.weight_kg ?? '',
            duration_minutes: entry.duration_seconds ? String(Math.round(Number(entry.duration_seconds) / 60)) : '',
            intensity: entry.intensity ?? '',
            notes: cleanedNotes,
            media_url: entry.metrics?.media_url ?? entry.metrics?.uploaded_media_url ?? '',
            media_file: null,
            completed: String(entry.notes || '').includes('Erledigt'),
        })
    })

    return Array.from(grouped.values()).map((exercise) => ({
        ...exercise,
        sets: exercise.sets.length ? exercise.sets : [emptyGymSet()],
    }))
}

const hydrateDraft = (log) => {
    if (!log) return

    form.user_id = log.athlete?.id && Number(log.athlete.id) !== Number(page.props.auth?.user?.id) ? log.athlete.id : ''
    form.team_id = log.team?.id || ''
    form.training_plan_item_id = log.plan_item?.id || ''
    form.sport_route_id = log.sport_route?.id || log.plan_item?.sport_route?.id || ''
    form.sport_route_track_id = log.sport_route_track?.id || ''
    form.title = log.title || form.title
    form.sport_type = log.sport_type || form.sport_type
    form.training_type = inferTrainingTypeFromDraft(log)
    form.status = log.metrics?.intended_status || 'completed'
    form.privacy_scope = log.metrics?.privacy_scope || 'trainer'
    form.performed_at = toLocalDateTime(log.performed_at)
    form.duration_minutes = log.duration_minutes ?? ''
    form.distance_km = log.distance_meters ? String(Number(log.distance_meters) / 1000) : ''
    form.calories = log.calories ?? ''
    form.intensity = log.intensity || ''
    form.notes = log.notes || ''
    form.trainer_feedback = log.trainer_feedback || ''
    form.notify_people = log.metrics?.notify_people ?? true
    form.wellness = {
        rpe: log.metrics?.wellness?.rpe ?? '',
        energy: log.metrics?.wellness?.energy ?? '',
        pain: log.metrics?.wellness?.pain ?? '',
        sleep_hours: log.metrics?.wellness?.sleep_hours ?? '',
    }
    form.entries = (log.entries || []).map((entry) => normalizeEntry({
        ...entry,
        duration_minutes: entry.duration_seconds ? Math.round(Number(entry.duration_seconds) / 60) : '',
        distance_km: entry.distance_meters ? Number(entry.distance_meters) / 1000 : '',
    }))
    if (!form.entries.length && form.training_type !== 'long_run') form.entries = [emptyEntry()]
    form.gym_exercises = entriesToGymExercises(log.entries || [])
    if (!form.gym_exercises.length) form.gym_exercises = [emptyGymExercise()]
}

const applyTrainingType = () => {
    const type = selectedType.value
    form.sport_type = type.sport_type
    if (!form.title || trainingTypes.some((item) => item.title === form.title)) {
        form.title = type.title
    }
    if (type.key === 'long_run' && form.entries.length === 1 && !visibleEntries.value.length) {
        form.entries = []
    } else if (type.key !== 'long_run' && !usesGymSets.value && !form.entries.length) {
        form.entries = [emptyEntry()]
    }
}

const selectTrainingType = (key) => {
    form.training_type = key
    mobileTrainingTypeSheetOpen.value = false
}

const applySelectedPlanItem = () => {
    const item = plannedItems.value.find((entry) => Number(entry.id) === Number(form.training_plan_item_id))
    if (!item) return

    form.training_type = inferTrainingTypeFromPlanItem(item)
    form.title = item.title || form.title
    form.sport_type = item.sport_type || form.sport_type
    form.performed_at = form.performed_at || toLocalDateTime(item.scheduled_at)
    form.duration_minutes = form.duration_minutes || item.duration_minutes || ''
    form.distance_km = form.distance_km || (item.distance_meters ? (Number(item.distance_meters) / 1000).toFixed(2) : '')
    form.calories = form.calories || item.calories || ''
    form.intensity = item.intensity || form.intensity
    form.notes = form.notes || item.description || ''
    form.sport_route_id = item.sport_route_id || ''
}

const applyPrefillFromQuery = () => {
    const params = new URLSearchParams(String(page.url || '').split('?')[1] || '')
    const planItemId = props.prefillPlanItemId || params.get('plan_item_id')
    if (planItemId) {
        form.training_plan_item_id = planItemId
        applySelectedPlanItem()
    }

    if (props.prefillEvent) {
        form.title = props.prefillEvent.title || form.title
        form.sport_route_id = props.prefillEvent.sport_route_id || form.sport_route_id
        form.notes = form.notes || tx('training_workspace.log_create.event_context', { event: props.prefillEvent.title })
    }
}

const setStatusDefaults = () => {
    if (form.status === 'in_progress' && !form.performed_at) {
        form.performed_at = toLocalDateTime(new Date())
    }
}

const startLiveTraining = () => {
    form.status = 'in_progress'
    if (!form.performed_at) {
        form.performed_at = toLocalDateTime(new Date())
    }
    liveNow.value = Date.now()
}

const finishLiveTraining = () => {
    if (isLiveTraining.value && liveElapsedSeconds.value && !form.duration_minutes) {
        setDurationFromLive()
    }
    form.status = 'completed'
}

const addEntry = () => {
    const plannedEntry = plannedEntryFromItem(selectedPlannedItem.value)

    if (selectedPlannedItem.value && !isBlankEntry(plannedEntry) && form.entries.length === 1 && isBlankEntry(form.entries[0])) {
        form.entries = [plannedEntry]
        activeEntryIndex.value = 0
        return
    }

    form.entries = [...form.entries, plannedEntry]
    activeEntryIndex.value = form.entries.length - 1
}

const removeEntry = (index) => {
    form.entries = form.entries.filter((_, entryIndex) => entryIndex !== index)
    if (!form.entries.length && selectedType.value.key !== 'long_run') form.entries = [emptyEntry()]
    activeEntryIndex.value = Math.min(activeEntryIndex.value, Math.max(0, form.entries.length - 1))
}

const setActiveEntry = (index) => {
    activeEntryIndex.value = index
}

const nextActiveEntry = () => {
    if (activeEntryIndex.value < form.entries.length - 1) {
        activeEntryIndex.value += 1
        return
    }

    addEntry()
}

const adjustNumberField = (target, field, delta, step = 1) => {
    if (!target) return

    const current = Number(target[field] || 0)
    const next = Math.max(0, current + delta)
    target[field] = step < 1 ? next.toFixed(2).replace(/\.?0+$/, '') : String(next)
}

const adjustSessionNumber = (field, delta, step = 1) => {
    adjustNumberField(form, field, delta, step)
}

const adjustActiveEntry = (field, delta, step = 1) => {
    adjustNumberField(activeEntry.value, field, delta, step)
}

const addGymExercise = () => {
    form.gym_exercises = [...form.gym_exercises, emptyGymExercise()]
    activeGymExerciseIndex.value = form.gym_exercises.length - 1
    activeGymSetIndex.value = 0
}

const removeGymExercise = (index) => {
    form.gym_exercises = form.gym_exercises.filter((_, exerciseIndex) => exerciseIndex !== index)
    if (!form.gym_exercises.length) form.gym_exercises = [emptyGymExercise()]
    activeGymExerciseIndex.value = Math.min(activeGymExerciseIndex.value, form.gym_exercises.length - 1)
    activeGymSetIndex.value = 0
}

const addGymSet = (exerciseIndex) => {
    const previousSet = form.gym_exercises[exerciseIndex].sets.at(-1)
    form.gym_exercises[exerciseIndex].sets = [
        ...form.gym_exercises[exerciseIndex].sets,
        previousSet
            ? {
                ...emptyGymSet(),
                reps: previousSet.reps,
                weight_kg: previousSet.weight_kg,
                duration_minutes: previousSet.duration_minutes,
                intensity: previousSet.intensity,
            }
            : emptyGymSet(),
    ]
    activeGymExerciseIndex.value = exerciseIndex
    activeGymSetIndex.value = form.gym_exercises[exerciseIndex].sets.length - 1
}

const removeGymSet = (exerciseIndex, setIndex) => {
    form.gym_exercises[exerciseIndex].sets = form.gym_exercises[exerciseIndex].sets.filter((_, index) => index !== setIndex)
    if (!form.gym_exercises[exerciseIndex].sets.length) {
        form.gym_exercises[exerciseIndex].sets = [emptyGymSet()]
    }
    if (activeGymExerciseIndex.value === exerciseIndex) {
        activeGymSetIndex.value = Math.min(activeGymSetIndex.value, form.gym_exercises[exerciseIndex].sets.length - 1)
    }
}

const toggleGymSetDone = (exerciseIndex, setIndex) => {
    setActiveGymSet(exerciseIndex, setIndex)
    const set = form.gym_exercises[exerciseIndex].sets[setIndex]
    set.completed = !set.completed

    if (set.completed) {
        startRestTimer()
    }
}

const setActiveGymSet = (exerciseIndex, setIndex) => {
    activeGymExerciseIndex.value = exerciseIndex
    activeGymSetIndex.value = setIndex
}

const setActiveGymExercise = (exerciseIndex) => {
    activeGymExerciseIndex.value = exerciseIndex
    activeGymSetIndex.value = 0
}

const adjustActiveGymSet = (field, delta, step = 1) => {
    if (!activeGymSet.value) return

    const current = Number(activeGymSet.value[field] || 0)
    const next = Math.max(0, current + delta)
    activeGymSet.value[field] = step < 1 ? next.toFixed(1) : String(next)
}

const nextActiveGymSet = () => {
    if (!activeGymExercise.value) return

    if (activeGymSetIndex.value < activeGymExercise.value.sets.length - 1) {
        activeGymSetIndex.value += 1
        return
    }

    addGymSet(activeGymExerciseIndex.value)
}

const finishActiveGymSet = () => {
    if (!activeGymSet.value) return

    activeGymSet.value.completed = true
    startRestTimer()
    nextActiveGymSet()
}

const startRestTimer = (seconds = 90) => {
    stopRestTimer()
    restSeconds.value = seconds
    restInterval = window.setInterval(() => {
        restSeconds.value -= 1
        if (restSeconds.value <= 0) {
            stopRestTimer()
        }
    }, 1000)
}

const stopRestTimer = () => {
    if (restInterval) {
        window.clearInterval(restInterval)
        restInterval = null
    }
    restSeconds.value = 0
}

const startLiveTimer = () => {
    if (liveInterval) return

    liveNow.value = Date.now()
    liveInterval = window.setInterval(() => {
        liveNow.value = Date.now()
    }, 1000)
}

const stopLiveTimer = () => {
    if (liveInterval) {
        window.clearInterval(liveInterval)
        liveInterval = null
    }
}

const syncLiveTimer = () => {
    if (isLiveTraining.value) {
        startLiveTimer()
    } else {
        stopLiveTimer()
    }
}

const matchingRecentExercise = (exercise) => {
    const title = (exercise.title || '').trim().toLowerCase()
    if (!title) return null

    return recentForAthlete.value.find((recent) => recent.title?.trim().toLowerCase() === title)
}

const recentExerciseLabel = (recent) => {
    if (!recent?.sets?.length) return ''

    return recent.sets
        .slice(0, 4)
        .map((set) => [
            set.reps ? `${set.reps} Wdh.` : null,
            set.weight_kg ? `${Number(set.weight_kg).toLocaleString('de-DE')} kg` : null,
            set.duration_minutes ? `${set.duration_minutes} min` : null,
        ].filter(Boolean).join(' / '))
        .filter(Boolean)
        .join(', ')
}

const exerciseHistoryLabel = (session) => {
    if (!session?.sets?.length) return '-'

    return session.sets
        .map((set) => [
            set.reps ? `${set.reps}x` : null,
            set.weight_kg ? `${Number(set.weight_kg).toLocaleString('de-DE')} kg` : null,
        ].filter(Boolean).join(' '))
        .filter(Boolean)
        .join(' - ')
}

const applyRecentExercise = (exerciseIndex, recent) => {
    if (!recent?.sets?.length) return

    form.gym_exercises[exerciseIndex].sets = recent.sets.map((set) => ({
        ...emptyGymSet(),
        reps: set.reps ?? '',
        weight_kg: set.weight_kg ?? '',
        duration_minutes: set.duration_minutes ?? '',
        intensity: set.intensity ?? '',
        notes: set.notes ?? '',
    }))
    setActiveGymSet(exerciseIndex, 0)
}

const gymEntriesForSubmit = (includeFiles = false) => form.gym_exercises.flatMap((exercise) => {
    const title = (exercise.title || '').trim()
    if (!title) return []

    return exercise.sets
        .map((set, index) => {
            const entry = {
                title: `${title} - Satz ${index + 1}`,
                sets: 1,
                reps: set.reps,
                weight_kg: set.weight_kg,
                duration_minutes: set.duration_minutes,
                intensity: set.intensity,
                media_url: set.media_url,
                notes: [set.completed ? 'Erledigt' : null, exercise.notes, set.notes].filter(Boolean).join(' | '),
            }

            if (includeFiles) entry.media_file = set.media_file

            return entry
        })
        .filter((set) => set.reps || set.weight_kg || set.duration_minutes || set.intensity || set.media_url || set.media_file || set.notes)
})

const entriesForSubmit = (includeFiles = false) => form.entries.map((entry) => {
    const payload = { ...entry }

    if (!includeFiles) {
        delete payload.media_file
    }

    return payload
})

const payloadForSave = () => ({
    draft_log_id: form.draft_log_id,
    user_id: form.user_id,
    team_id: form.team_id,
    training_plan_item_id: form.training_plan_item_id,
    sport_route_id: form.sport_route_id,
    sport_route_track_id: form.sport_route_track_id,
    title: form.title,
    sport_type: form.sport_type,
    training_type: form.training_type,
    status: form.status,
    performed_at: form.performed_at,
    duration_minutes: form.duration_minutes,
    distance_km: form.distance_km,
    calories: form.calories,
    intensity: form.intensity,
    notes: form.notes,
    trainer_feedback: form.trainer_feedback,
    privacy_scope: form.privacy_scope,
    notify_people: form.notify_people,
    wellness: form.wellness,
    entries: usesGymSets.value ? gymEntriesForSubmit(false) : entriesForSubmit(false),
})

const autosaveDraft = async () => {
    if (!form.draft_log_id || form.processing) return

    const requestId = ++autosaveRequestId
    autosaveStatus.value = 'saving'
    autosaveError.value = ''

    try {
        const response = await window.axios.put(route('auth.training.logs.draft.update', form.draft_log_id), payloadForSave())

        if (requestId !== autosaveRequestId) return

        autosaveSavedAt.value = response.data?.saved_at || new Date().toISOString()
        autosaveStatus.value = 'saved'
    } catch (error) {
        if (requestId !== autosaveRequestId) return

        autosaveStatus.value = 'error'
        autosaveError.value = error.response?.data?.message || 'Entwurf konnte nicht gespeichert werden.'
    }
}

const scheduleAutosave = () => {
    if (!form.draft_log_id) return

    autosaveStatus.value = 'dirty'
    window.clearTimeout(autosaveTimer)
    autosaveTimer = window.setTimeout(autosaveDraft, 900)
}

const submit = () => {
    window.clearTimeout(autosaveTimer)
    form.transform((data) => ({
        ...data,
        entries: usesGymSets.value ? gymEntriesForSubmit(true) : entriesForSubmit(true),
    }))

    form.post(route('auth.training.logs.store'), {
        preserveScroll: true,
        forceFormData: true,
    })
}

hydrateDraft(props.draftLog)
applyPrefillFromQuery()

watch(() => form.training_type, applyTrainingType)
watch(() => form.user_id, (athleteId) => {
    if (athleteId) form.sport_route_track_id = ''
})
watch(() => [form.status, form.performed_at], syncLiveTimer)
watch(() => [
    form.user_id,
    form.team_id,
    form.training_plan_item_id,
    form.sport_route_id,
    form.sport_route_track_id,
    form.title,
    form.sport_type,
    form.training_type,
    form.status,
    form.performed_at,
    form.duration_minutes,
    form.distance_km,
    form.calories,
    form.intensity,
    form.notes,
    form.trainer_feedback,
    form.privacy_scope,
    form.notify_people,
    JSON.stringify(form.wellness),
    JSON.stringify(form.entries),
    JSON.stringify(form.gym_exercises),
], scheduleAutosave)

onMounted(syncLiveTimer)

onUnmounted(() => {
    stopRestTimer()
    stopLiveTimer()
    window.clearTimeout(autosaveTimer)
})
</script>

<template>
    <Head :title="tx('training_workspace.log_create.page_title')" />

    <div class="space-y-3 pb-32 sm:space-y-4 xl:pb-0">
        <section class="hidden overflow-hidden rounded-2xl border border-border bg-card sm:block">
            <div class="border-b border-border bg-inputBg/30 p-4 sm:p-5">
                <div class="flex flex-wrap items-start justify-between gap-4">
                    <div class="min-w-0">
                        <p class="text-xs font-semibold uppercase tracking-wide text-air-blue">{{ tx('training_workspace.log_create.eyebrow') }}</p>
                        <h1 class="mt-1 text-2xl font-semibold text-primary sm:text-3xl">{{ tx('training_workspace.log_create.title') }}</h1>
                        <p class="mt-2 max-w-3xl text-sm leading-6 text-secondary">
                            {{ tx('training_workspace.log_create.intro') }}
                        </p>
                    </div>
                    <Link :href="route('auth.training.index')" class="rounded-xl border border-border px-4 py-2 text-sm font-semibold text-primary hover:bg-muted">
                        {{ tx('training_workspace.log_create.back') }}
                    </Link>
                </div>
            </div>
            <div class="grid gap-2 p-4 sm:grid-cols-2 lg:grid-cols-4">
                <div class="rounded-xl border border-border bg-inputBg/40 p-3">
                    <p class="text-[11px] font-semibold uppercase tracking-wide text-secondary">{{ tx('training_workspace.log_create.template') }}</p>
                    <p class="mt-1 truncate text-sm font-semibold text-primary">{{ selectedTypeLabel }}</p>
                </div>
                <div class="rounded-xl border border-border bg-inputBg/40 p-3">
                    <p class="text-[11px] font-semibold uppercase tracking-wide text-secondary">{{ tx('training_workspace.log_create.status') }}</p>
                    <p class="mt-1 truncate text-sm font-semibold text-primary">{{ detailSummary }}</p>
                </div>
                <div class="rounded-xl border border-border bg-inputBg/40 p-3">
                    <p class="text-[11px] font-semibold uppercase tracking-wide text-secondary">{{ tx('training_workspace.log_create.quality') }}</p>
                    <div class="mt-2 flex items-center gap-2">
                        <div class="h-2 flex-1 overflow-hidden rounded-full bg-muted">
                            <div class="h-full rounded-full bg-air-blue" :style="{ width: `${documentationScore}%` }"></div>
                        </div>
                        <span class="text-sm font-semibold" :class="documentationScoreClass">{{ documentationScore }}%</span>
                    </div>
                </div>
                <div class="rounded-xl border border-border bg-inputBg/40 p-3">
                    <p
                        v-if="draftLog"
                        class="inline-flex rounded-full border px-3 py-1 text-xs font-semibold"
                        :class="autosaveStatus === 'error' ? 'border-danger/40 bg-danger/10 text-danger' : autosaveStatus === 'saving' || autosaveStatus === 'dirty' ? 'border-air-blue/40 bg-air-blue/10 text-air-blue' : 'border-success/40 bg-success/10 text-success'"
                    >
                        <span v-if="autosaveStatus === 'saving'">{{ tx('training_workspace.log_create.autosave.saving') }}</span>
                        <span v-else-if="autosaveStatus === 'dirty'">{{ tx('training_workspace.log_create.autosave.dirty') }}</span>
                        <span v-else-if="autosaveStatus === 'error'">{{ autosaveError }}</span>
                        <span v-else>{{ tx('training_workspace.log_create.autosave.saved') }}{{ autosaveSavedAt ? ` ${tx('training_workspace.log_create.autosave.at')} ${formatSaveTime(autosaveSavedAt)}` : '' }}</span>
                    </p>
                    <p v-else class="text-sm font-semibold text-secondary">{{ tx('training_workspace.log_create.autosave.none') }}</p>
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

        <form class="grid items-start gap-5 2xl:grid-cols-[minmax(0,1fr)_340px]" @submit.prevent="submit">
            <div class="min-w-0 self-start rounded-2xl border border-border bg-card p-1.5 2xl:col-span-2">
                <div class="grid grid-cols-3 gap-1.5 sm:gap-2">
                    <button
                        v-for="step in trainingSteps"
                        :key="step.id"
                        type="button"
                        class="rounded-xl px-2 py-2 text-left transition sm:px-3 sm:py-3"
                        :class="currentTrainingStep === step.id ? 'bg-air-blue text-white shadow-sm shadow-air-blue/20' : 'bg-inputBg/40 text-secondary hover:bg-muted hover:text-primary'"
                        @click="currentTrainingStep = step.id"
                    >
                        <span class="block text-[10px] font-semibold uppercase tracking-wide sm:text-[11px]">{{ tx('training_workspace.log_create.step') }} {{ step.id }}</span>
                        <span class="mt-0.5 block truncate text-xs font-semibold sm:mt-1 sm:text-sm">{{ step.short || step.label }}</span>
                    </button>
                </div>
            </div>

            <section v-show="currentTrainingStep === 1" class="min-w-0 space-y-4 rounded-2xl border border-border bg-card p-3 sm:space-y-5 sm:p-5 2xl:col-start-1 2xl:row-start-2">
                <div class="grid gap-3 md:grid-cols-2 md:gap-4">
                    <div class="md:col-span-2">
                        <div class="flex items-end justify-between gap-3">
                            <div>
                                <p class="text-sm font-semibold text-primary">{{ tx('training_workspace.log_create.training_type') }}</p>
                                <p class="mt-1 hidden text-xs text-secondary sm:block">{{ tx('training_workspace.log_create.swipe_hint') }}</p>
                            </div>
                            <span class="hidden rounded-full border border-border px-3 py-1 text-xs font-semibold text-secondary sm:inline-flex">{{ detailSummary }}</span>
                        </div>
                        <button
                            type="button"
                            class="mt-3 flex w-full items-center gap-3 rounded-2xl border border-border bg-inputBg/40 p-3 text-left sm:hidden"
                            @click="mobileTrainingTypeSheetOpen = true"
                        >
                            <span class="flex h-11 w-11 shrink-0 items-center justify-center rounded-2xl" :class="trainingTypeTheme(selectedType.key).icon">
                                <i :class="selectedType.icon" class="text-xl"></i>
                            </span>
                            <span class="min-w-0 flex-1">
                                <span class="block text-[11px] font-semibold uppercase tracking-wide text-secondary">{{ tx('training_workspace.log_create.selected') }}</span>
                                <span class="mt-0.5 block truncate text-base font-semibold text-primary">{{ selectedTypeLabel }}</span>
                            </span>
                            <span class="rounded-xl bg-buttonPrimary px-3 py-2 text-xs font-semibold text-buttonTextPrimary">
                                {{ tx('training_workspace.log_create.change') }}
                            </span>
                        </button>
                        <div class="mt-3 hidden grid-cols-3 gap-2 sm:grid sm:grid-cols-4 xl:grid-cols-7">
                            <button
                                v-for="type in trainingTypes"
                                :key="type.key"
                                type="button"
                                class="flex min-h-11 min-w-0 items-center gap-2 rounded-2xl border px-2.5 py-2 text-left transition sm:min-h-12 sm:px-3 sm:py-2.5"
                                :class="trainingTypeButtonClass(type)"
                                @click="selectTrainingType(type.key)"
                            >
                                <span class="flex h-7 w-7 shrink-0 items-center justify-center rounded-xl sm:h-8 sm:w-8" :class="trainingTypeTheme(type.key).icon">
                                    <i :class="type.icon" class="text-base sm:text-lg"></i>
                                </span>
                                <span class="min-w-0 truncate text-xs font-semibold sm:text-sm">{{ tx(`training_workspace.log_create.types.${type.key}.short`) }}</span>
                            </button>
                        </div>
                    </div>
                    <label v-if="athleteOptions.length > 1" class="block text-sm font-semibold text-primary">{{ tx('training_workspace.log_create.athlete') }}
                        <select v-model="form.user_id" class="mt-2 w-full rounded-xl border border-border bg-inputBg px-3 py-2 text-primary">
                            <option v-for="athlete in athleteOptions" :key="athlete.id || 'self'" :value="athlete.id">{{ athlete.name }}</option>
                        </select>
                    </label>
                    <label class="block text-sm font-semibold text-primary">{{ tx('training_workspace.log_create.planned_session') }}
                        <select v-model="form.training_plan_item_id" class="mt-2 w-full rounded-xl border border-border bg-inputBg px-3 py-2 text-primary" @change="applySelectedPlanItem">
                            <option value="">{{ tx('training_workspace.log_create.spontaneous') }}</option>
                            <option v-for="item in plannedItems" :key="item.id" :value="item.id">
                                {{ item.plan.title }} - {{ item.title }}{{ item.scheduled_at ? ` - ${formatDate(item.scheduled_at)}` : '' }}
                            </option>
                        </select>
                    </label>
                    <label class="block text-sm font-semibold text-primary">{{ tx('training_workspace.route_link.planned_route') }}
                        <select v-model="form.sport_route_id" class="mt-2 w-full rounded-xl border border-border bg-inputBg px-3 py-2 text-primary">
                            <option value="">{{ tx('training_workspace.route_link.none') }}</option>
                            <option v-for="sportRoute in sportRoutes" :key="sportRoute.id" :value="sportRoute.id">
                                {{ sportRoute.title }} · {{ formatDistance(sportRoute.distance_meters) }}
                            </option>
                        </select>
                        <span v-if="selectedSportRoute" class="mt-1 block text-xs font-normal text-secondary">
                            {{ tx('training_workspace.route_link.location_minimized') }}
                        </span>
                    </label>
                    <label v-if="!form.user_id && sportRouteTracks.length" class="block text-sm font-semibold text-primary">{{ tx('training_workspace.route_link.recorded_track') }}
                        <select v-model="form.sport_route_track_id" class="mt-2 w-full rounded-xl border border-border bg-inputBg px-3 py-2 text-primary">
                            <option value="">{{ tx('training_workspace.route_link.no_track') }}</option>
                            <option v-for="sportTrack in sportRouteTracks" :key="sportTrack.id" :value="sportTrack.id">
                                {{ sportTrack.title }} · {{ formatDistance(sportTrack.distance_meters) }}
                            </option>
                        </select>
                        <span class="mt-1 block text-xs font-normal text-secondary">{{ tx('training_workspace.route_link.track_hint') }}</span>
                    </label>
                    <label class="block text-sm font-semibold text-primary">{{ tx('training_workspace.log_create.sport') }}
                        <SearchableSelect
                            v-model="form.sport_type"
                            class="mt-2"
                            :options="sportChoices"
                            label-key="label"
                            value-key="key"
                            :placeholder="tx('training_workspace.log_create.sport_search')"
                        />
                        <div v-if="recentSportChoices.length" class="mt-2 flex flex-wrap gap-2">
                            <button
                                v-for="sport in recentSportChoices"
                                :key="sport.key"
                                type="button"
                                class="rounded-full border px-3 py-1.5 text-xs font-semibold transition"
                                :class="form.sport_type === sport.key ? 'border-air-blue bg-air-blue/10 text-primary' : 'border-border text-secondary hover:bg-muted hover:text-primary'"
                                @click="form.sport_type = sport.key"
                            >
                                {{ sport.label }}
                            </button>
                        </div>
                    </label>
                    <details class="rounded-2xl border border-border bg-inputBg/30 p-3 md:hidden">
                        <summary class="cursor-pointer list-none text-sm font-semibold text-primary">
                            {{ tx('training_workspace.log_create.more_fields') }}
                            <span class="ml-2 text-xs font-normal text-secondary">{{ tx('training_workspace.log_create.optional') }}</span>
                        </summary>
                        <div class="mt-3 grid gap-3">
                            <label class="block text-sm font-semibold text-primary">{{ tx('training_workspace.log_create.fields.title') }}
                                <input v-model="form.title" class="mt-2 w-full rounded-xl border border-border bg-inputBg px-3 py-2 text-primary" required />
                            </label>
                            <label class="block text-sm font-semibold text-primary">{{ tx('training_workspace.log_create.fields.status') }}
                                <select v-model="form.status" class="mt-2 w-full rounded-xl border border-border bg-inputBg px-3 py-2 text-primary" @change="setStatusDefaults">
                                    <option value="completed">{{ tx('training_log.status.completed') }}</option>
                                    <option value="in_progress">{{ tx('training_log.status.in_progress') }}</option>
                                    <option value="planned">{{ tx('training_log.status.planned') }}</option>
                                </select>
                            </label>
                            <label class="block text-sm font-semibold text-primary">{{ tx('training_workspace.log_create.fields.time') }}
                                <input v-model="form.performed_at" type="datetime-local" class="mt-2 w-full rounded-xl border border-border bg-inputBg px-3 py-2 text-primary" />
                            </label>
                            <label class="block text-sm font-semibold text-primary">{{ tx('training_workspace.log_create.fields.duration') }}
                                <input v-model="form.duration_minutes" type="number" min="0" class="mt-2 w-full rounded-xl border border-border bg-inputBg px-3 py-2 text-primary" />
                            </label>
                            <label v-if="showSessionDistance" class="block text-sm font-semibold text-primary">{{ tx('training_workspace.log_create.fields.distance') }}
                                <input v-model="form.distance_km" type="number" min="0" step="0.01" class="mt-2 w-full rounded-xl border border-border bg-inputBg px-3 py-2 text-primary" />
                            </label>
                            <label class="block text-sm font-semibold text-primary">{{ tx('training_workspace.log_create.fields.notes') }}
                                <textarea v-model="form.notes" rows="3" class="mt-2 w-full rounded-xl border border-border bg-inputBg px-3 py-2 text-primary" :placeholder="tx('training_workspace.log_create.notes_placeholder')" />
                            </label>
                        </div>
                    </details>
                    <label class="hidden text-sm font-semibold text-primary md:col-span-2 md:block">{{ tx('training_workspace.log_create.fields.title') }}
                        <input v-model="form.title" class="mt-2 w-full rounded-xl border border-border bg-inputBg px-3 py-2 text-primary" required />
                    </label>
                    <label class="hidden text-sm font-semibold text-primary md:block">{{ tx('training_workspace.log_create.fields.status') }}
                        <select v-model="form.status" class="mt-2 w-full rounded-xl border border-border bg-inputBg px-3 py-2 text-primary" @change="setStatusDefaults">
                            <option value="completed">{{ tx('training_log.status.completed') }}</option>
                            <option value="in_progress">{{ tx('training_log.status.in_progress') }}</option>
                            <option value="planned">{{ tx('training_log.status.planned') }}</option>
                        </select>
                    </label>
                    <label class="hidden text-sm font-semibold text-primary md:block">{{ tx('training_workspace.log_create.fields.time') }}
                        <input v-model="form.performed_at" type="datetime-local" class="mt-2 w-full rounded-xl border border-border bg-inputBg px-3 py-2 text-primary" />
                    </label>
                    <div class="hidden rounded-xl border border-border bg-inputBg/40 p-3 md:col-span-2 md:block">
                        <div class="flex flex-wrap items-center justify-between gap-3">
                            <div>
                                <p class="text-xs font-semibold uppercase tracking-wide text-secondary">{{ tx('training_workspace.log_create.live_mode') }}</p>
                                <p class="mt-1 text-sm font-semibold text-primary">
                                    <span v-if="isLiveTraining">{{ tx('training_workspace.log_create.ui.running_since') }} {{ liveElapsedLabel }}</span>
                                    <span v-else>{{ tx('training_workspace.log_create.live_hint') }}</span>
                                </p>
                            </div>
                            <div class="flex flex-wrap gap-2">
                                <button
                                    v-if="!isLiveTraining"
                                    type="button"
                                    class="rounded-xl border border-air-blue/40 bg-air-blue/10 px-3 py-2 text-sm font-semibold text-primary hover:bg-air-blue/20"
                                    @click="startLiveTraining"
                                >
                                    {{ tx('training_workspace.log_create.live_start') }}
                                </button>
                                <button
                                    v-if="isLiveTraining"
                                    type="button"
                                    class="rounded-xl border border-border px-3 py-2 text-sm font-semibold text-primary hover:bg-muted"
                                    @click="setDurationFromLive"
                                >
                                    {{ tx('training_workspace.log_create.live_take_time') }}
                                </button>
                                <button
                                    v-if="isLiveTraining"
                                    type="button"
                                    class="rounded-xl bg-buttonPrimary px-3 py-2 text-sm font-semibold text-buttonTextPrimary"
                                    @click="finishLiveTraining"
                                >
                                    {{ tx('training_workspace.log_create.finish') }}
                                </button>
                            </div>
                        </div>
                    </div>
                    <label class="hidden text-sm font-semibold text-primary md:block">{{ tx('training_workspace.log_create.fields.duration') }}
                        <input v-model="form.duration_minutes" type="number" min="0" class="mt-2 w-full rounded-xl border border-border bg-inputBg px-3 py-2 text-primary" />
                    </label>
                    <label v-if="showSessionDistance" class="hidden text-sm font-semibold text-primary md:block">{{ tx('training_workspace.log_create.fields.distance') }}
                        <input v-model="form.distance_km" type="number" min="0" step="0.01" class="mt-2 w-full rounded-xl border border-border bg-inputBg px-3 py-2 text-primary" />
                    </label>
                    <label class="hidden text-sm font-semibold text-primary md:col-span-2 md:block">{{ tx('training_workspace.log_create.fields.notes') }}
                        <textarea v-model="form.notes" rows="4" class="mt-2 w-full rounded-xl border border-border bg-inputBg px-3 py-2 text-primary" :placeholder="tx('training_workspace.log_create.notes_placeholder')" />
                    </label>
                    <label v-if="form.user_id" class="hidden text-sm font-semibold text-primary md:col-span-2 md:block">{{ tx('training_workspace.log_create.fields.trainer_note') }}
                        <textarea v-model="form.trainer_feedback" rows="3" class="mt-2 w-full rounded-xl border border-border bg-inputBg px-3 py-2 text-primary" :placeholder="tx('training_workspace.log_create.ui.trainer_feedback_placeholder')" />
                    </label>
                    <label class="hidden text-sm font-semibold text-primary md:col-span-2 md:block">{{ tx('training_workspace.log_create.fields.visibility') }}
                        <select v-model="form.privacy_scope" class="mt-2 w-full rounded-xl border border-border bg-inputBg px-3 py-2 text-primary">
                            <option value="trainer">{{ tx('training_workspace.log_create.visibility.trainer') }}</option>
                            <option value="private">{{ tx('training_workspace.log_create.visibility.private') }}</option>
                            <option value="team">{{ tx('training_workspace.log_create.visibility.team') }}</option>
                        </select>
                    </label>
                    <label class="hidden items-start gap-3 rounded-xl border border-border bg-inputBg/40 p-3 text-sm font-semibold text-primary md:col-span-2 md:flex">
                        <input v-model="form.notify_people" type="checkbox" class="mt-1 rounded border-border bg-inputBg" />
                        <span>
                            <span>{{ form.user_id ? tx('training_workspace.log_create.notify.athlete') : tx('training_workspace.log_create.notify.trainer') }}</span>
                            <span class="mt-1 block text-xs font-normal leading-5 text-secondary">
                                {{ tx('training_workspace.log_create.notify.hint') }}
                            </span>
                        </span>
                    </label>
                    <div class="flex justify-end md:col-span-2">
                        <button type="button" class="inline-flex items-center gap-2 rounded-xl bg-buttonPrimary px-5 py-3 text-sm font-semibold text-buttonTextPrimary" @click="currentTrainingStep = 2">
                            <span class="sm:hidden">{{ tx('training_workspace.log_create.next') }}</span>
                            <span class="hidden sm:inline">{{ tx('training_workspace.log_create.next_document') }}</span>
                            <i class="las la-arrow-right text-lg sm:hidden"></i>
                        </button>
                    </div>
                </div>
            </section>

            <section v-show="currentTrainingStep === 3" class="min-w-0 space-y-5 rounded-2xl border border-border bg-card p-4 sm:p-5 2xl:col-start-1 2xl:row-start-2">
                <div>
                    <p class="text-xs font-semibold uppercase tracking-wide text-air-blue">{{ tx('training_workspace.log_create.step') }} 3</p>
                    <h2 class="mt-1 text-xl font-semibold text-primary">{{ tx('training_workspace.log_create.finish_title') }}</h2>
                    <p class="mt-2 text-sm leading-6 text-secondary">
                        {{ tx('training_workspace.log_create.finish_intro') }}
                    </p>
                </div>
                <div class="grid gap-4 md:grid-cols-2">
                    <label class="block text-sm font-semibold text-primary">{{ tx('training_workspace.log_create.fields.intensity') }}
                        <select v-model="form.intensity" class="mt-2 w-full rounded-xl border border-border bg-inputBg px-3 py-2 text-primary">
                            <option value="">{{ tx('training_workspace.log_create.intensity.none') }}</option>
                            <option value="locker">{{ tx('training_workspace.log_create.intensity.easy') }}</option>
                            <option value="mittel">{{ tx('training_workspace.log_create.intensity.medium') }}</option>
                            <option value="hart">{{ tx('training_workspace.log_create.intensity.hard') }}</option>
                            <option value="recovery">{{ tx('training_workspace.log_create.intensity.recovery') }}</option>
                        </select>
                    </label>
                    <label class="block text-sm font-semibold text-primary">{{ tx('training_workspace.log_create.fields.calories') }}
                        <input v-model="form.calories" type="number" min="0" class="mt-2 w-full rounded-xl border border-border bg-inputBg px-3 py-2 text-primary" :placeholder="tx('training_workspace.log_create.optional')" />
                    </label>
                    <div class="rounded-xl border border-border bg-inputBg/40 p-3 md:col-span-2">
                        <p class="text-sm font-semibold text-primary">{{ tx('training_workspace.log_create.wellness.title') }}</p>
                        <p class="mt-1 text-xs leading-5 text-secondary">
                            {{ tx('training_workspace.log_create.wellness.hint') }}
                        </p>
                        <div class="mt-3 grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
                            <label class="block text-sm font-semibold text-primary">{{ tx('training_workspace.log_create.wellness.rpe') }}
                                <input v-model="form.wellness.rpe" type="number" min="1" max="10" class="mt-2 w-full rounded-xl border border-border bg-inputBg px-3 py-2 text-primary" />
                            </label>
                            <label class="block text-sm font-semibold text-primary">{{ tx('training_workspace.log_create.wellness.energy') }}
                                <input v-model="form.wellness.energy" type="number" min="1" max="10" class="mt-2 w-full rounded-xl border border-border bg-inputBg px-3 py-2 text-primary" />
                            </label>
                            <label class="block text-sm font-semibold text-primary">{{ tx('training_workspace.log_create.wellness.pain') }}
                                <input v-model="form.wellness.pain" type="number" min="0" max="10" class="mt-2 w-full rounded-xl border border-border bg-inputBg px-3 py-2 text-primary" />
                            </label>
                            <label class="block text-sm font-semibold text-primary">{{ tx('training_workspace.log_create.wellness.sleep') }}
                                <input v-model="form.wellness.sleep_hours" type="number" min="0" max="24" step="0.5" class="mt-2 w-full rounded-xl border border-border bg-inputBg px-3 py-2 text-primary" />
                            </label>
                        </div>
                    </div>
                </div>
                <div class="flex flex-wrap justify-between gap-2">
                    <button type="button" class="rounded-xl border border-border px-4 py-3 text-sm font-semibold text-primary hover:bg-muted" @click="currentTrainingStep = 2">
                        {{ tx('training_workspace.log_create.back_to_details') }}
                    </button>
                    <button type="submit" class="rounded-xl bg-buttonPrimary px-5 py-3 text-sm font-semibold text-buttonTextPrimary disabled:opacity-60" :disabled="form.processing">
                        {{ tx('training_workspace.log_create.save') }}
                    </button>
                </div>
            </section>

            <section class="hidden rounded-2xl border border-air-blue/30 bg-air-blue/10 p-4 lg:block 2xl:hidden">
                <div class="flex flex-col gap-3 lg:flex-row lg:items-center lg:justify-between">
                    <div class="min-w-0">
                        <p class="text-xs font-semibold uppercase tracking-wide text-air-blue">{{ tx('training_workspace.log_create.work_mode') }}</p>
                        <p class="mt-1 text-sm font-semibold text-primary">{{ selectedTypeLabel }} · {{ detailSummary }}</p>
                    </div>
                    <div class="flex flex-wrap gap-2">
                        <button
                            v-if="!isLiveTraining"
                            type="button"
                            class="rounded-xl border border-air-blue/40 bg-card px-4 py-2 text-sm font-semibold text-primary hover:bg-air-blue/10"
                            @click="startLiveTraining"
                        >
                            {{ tx('training_workspace.log_create.live_start') }}
                        </button>
                        <button
                            v-else
                            type="button"
                            class="rounded-xl border border-air-blue/40 bg-card px-4 py-2 text-sm font-semibold text-primary hover:bg-air-blue/10"
                            @click="finishLiveTraining"
                        >
                            {{ tx('training_workspace.log_create.live_end') }}
                        </button>
                        <button type="submit" class="rounded-xl bg-buttonPrimary px-5 py-2 text-sm font-semibold text-buttonTextPrimary disabled:opacity-60" :disabled="form.processing">
                            {{ tx('training_workspace.log_create.save') }}
                        </button>
                    </div>
                </div>
            </section>

            <aside class="hidden min-w-0 space-y-4 2xl:sticky 2xl:top-20 2xl:col-start-2 2xl:row-start-2 2xl:block 2xl:self-start">
                <section class="rounded-2xl border border-border bg-card p-4">
                    <p class="text-xs font-semibold uppercase tracking-wide text-secondary">{{ tx('training_workspace.log_create.active_template') }}</p>
                    <h2 class="mt-1 text-lg font-semibold text-primary">{{ selectedTypeLabel }}</h2>
                    <p class="mt-2 text-sm leading-6 text-secondary">
                        {{ usesGymSets ? tx('training_workspace.log_create.gym_hint') : tx('training_workspace.log_create.general_hint') }}
                    </p>
                    <div class="mt-4 rounded-xl border border-border bg-inputBg/40 p-3">
                        <p class="text-xs font-semibold uppercase tracking-wide text-secondary">{{ tx('training_workspace.log_create.moment') }}</p>
                        <p class="mt-1 text-sm font-semibold text-primary">{{ detailSummary }}</p>
                    </div>
                    <div class="mt-3 rounded-xl border border-border bg-inputBg/40 p-3">
                        <div class="flex items-center justify-between gap-3">
                            <p class="text-xs font-semibold uppercase tracking-wide text-secondary">{{ tx('training_workspace.log_create.quality_check') }}</p>
                            <p class="text-sm font-semibold" :class="documentationScoreClass">{{ documentationScore }}%</p>
                        </div>
                        <div class="mt-2 h-2 overflow-hidden rounded-full bg-muted">
                            <div class="h-full rounded-full bg-air-blue" :style="{ width: `${documentationScore}%` }"></div>
                        </div>
                        <div class="mt-3 grid gap-1.5">
                            <div v-for="item in documentationChecklist" :key="item.label" class="flex items-center justify-between gap-2 text-xs">
                                <span class="text-secondary">{{ item.label }}</span>
                                <span class="font-semibold" :class="item.done ? 'text-success' : 'text-warning'">{{ item.done ? 'ok' : 'offen' }}</span>
                            </div>
                        </div>
                    </div>
                    <div v-if="isLiveTraining" class="mt-3 rounded-xl border border-success/40 bg-success/10 p-3">
                        <p class="text-xs font-semibold uppercase tracking-wide text-success">{{ tx('training_log.status.in_progress') }}</p>
                        <p class="mt-1 text-2xl font-semibold text-primary">{{ liveElapsedLabel }}</p>
                        <div class="mt-3 grid grid-cols-2 gap-2">
                            <button type="button" class="rounded-lg border border-border px-3 py-2 text-xs font-semibold text-primary hover:bg-muted" @click="setDurationFromLive">
                                {{ tx('training_workspace.log_create.duration_set') }}
                            </button>
                            <button type="button" class="rounded-lg bg-buttonPrimary px-3 py-2 text-xs font-semibold text-buttonTextPrimary" @click="finishLiveTraining">
                                {{ tx('training_workspace.log_create.finish') }}
                            </button>
                        </div>
                    </div>
                    <div class="mt-3 grid grid-cols-2 gap-2">
                        <div class="rounded-xl border border-border bg-inputBg/40 p-3">
                            <p class="text-[11px] font-semibold uppercase tracking-wide text-secondary">{{ tx('training_workspace.log_create.ui.duration') }}</p>
                            <p class="mt-1 text-sm font-semibold text-primary">{{ sessionMinutes ? `${formatNumber(sessionMinutes)} min` : '-' }}</p>
                        </div>
                        <div class="rounded-xl border border-border bg-inputBg/40 p-3">
                            <p class="text-[11px] font-semibold uppercase tracking-wide text-secondary">{{ tx('training_workspace.log_create.ui.distance') }}</p>
                            <p class="mt-1 text-sm font-semibold text-primary">{{ sessionDistanceKm ? `${formatNumber(sessionDistanceKm, 2)} km` : '-' }}</p>
                        </div>
                        <div v-if="sessionPace" class="rounded-xl border border-air-blue/30 bg-air-blue/10 p-3">
                            <p class="text-[11px] font-semibold uppercase tracking-wide text-air-blue">{{ tx('training_workspace.log_create.ui.pace') }}</p>
                            <p class="mt-1 text-sm font-semibold text-primary">{{ sessionPace }}</p>
                        </div>
                        <div v-if="sessionSpeedKmh" class="rounded-xl border border-emerald-400/30 bg-emerald-400/10 p-3">
                            <p class="text-[11px] font-semibold uppercase tracking-wide text-emerald-300">km/h</p>
                            <p class="mt-1 text-sm font-semibold text-primary">{{ sessionSpeedKmh }}</p>
                        </div>
                        <div v-if="usesGymSets && gymVolumeKg" class="rounded-xl border border-air-blue/30 bg-air-blue/10 p-3">
                            <p class="text-[11px] font-semibold uppercase tracking-wide text-air-blue">{{ tx('training_workspace.log_create.volume') }}</p>
                            <p class="mt-1 text-sm font-semibold text-primary">{{ formatNumber(gymVolumeKg, 0) }} kg</p>
                        </div>
                    </div>
                    <div v-if="restSeconds > 0" class="mt-3 rounded-xl border border-air-blue/40 bg-air-blue/10 p-3">
                        <p class="text-xs font-semibold uppercase tracking-wide text-air-blue">{{ tx('training_workspace.log_create.ui.rest_running') }}</p>
                        <div class="mt-1 flex items-center justify-between gap-3">
                            <p class="text-2xl font-semibold text-primary">{{ restTimerLabel }}</p>
                            <button type="button" class="rounded-lg border border-border px-3 py-1.5 text-xs font-semibold text-primary hover:bg-muted" @click="stopRestTimer">
                                {{ tx('training_workspace.log_create.ui.stop') }}
                            </button>
                        </div>
                    </div>
                </section>
                <button type="submit" class="w-full rounded-xl bg-buttonPrimary px-4 py-3 text-sm font-semibold text-buttonTextPrimary disabled:opacity-60" :disabled="form.processing">
                    {{ tx('training_workspace.log_create.save') }}
                </button>
            </aside>

            <section v-show="currentTrainingStep === 2" class="min-w-0 space-y-3 rounded-2xl border border-border bg-card p-3 sm:space-y-4 sm:p-5 2xl:col-start-1 2xl:row-start-2">
                <div class="flex flex-wrap items-center justify-between gap-3">
                    <div>
                        <p class="text-xs font-semibold uppercase tracking-wide text-secondary">{{ tx('training_workspace.log_create.ui.details') }}</p>
                        <h2 class="text-xl font-semibold text-primary">{{ selectedTypeDetailTitle }}</h2>
                    </div>
                    <button v-if="!usesGymSets" type="button" class="rounded-xl border border-border bg-inputBg/40 px-4 py-2 text-sm font-semibold text-primary hover:bg-muted" @click="addEntry">
                        Zeile hinzufügen
                    </button>
                </div>

                <div v-if="usesGymSets" class="space-y-4">
                    <div class="rounded-2xl border border-border bg-inputBg/40 p-3">
                        <div class="flex flex-wrap items-center justify-between gap-3">
                            <div>
                                <p class="text-xs font-semibold uppercase tracking-wide text-secondary">{{ tx('training_workspace.log_create.ui.exercises') }}</p>
                                <p class="mt-1 text-sm font-semibold text-primary">{{ form.gym_exercises.length }} {{ tx('training_workspace.log_create.ui.exercises') }}</p>
                            </div>
                            <button type="button" class="rounded-xl border border-border bg-card px-3 py-2 text-sm font-semibold text-primary hover:bg-muted" @click="addGymExercise">
                                {{ tx('training_workspace.log_create.ui.add_exercise') }}
                            </button>
                        </div>
                        <div class="custom-scrollbar mt-3 flex gap-2 overflow-x-auto pb-1">
                            <button
                                v-for="(exercise, exerciseIndex) in form.gym_exercises"
                                :key="`exercise-step-${exerciseIndex}`"
                                type="button"
                                class="min-w-32 rounded-xl border px-3 py-2 text-left text-sm transition"
                                :class="activeGymExerciseIndex === exerciseIndex ? 'border-air-blue bg-air-blue/10 text-primary ring-1 ring-air-blue/30' : 'border-border bg-card text-secondary hover:bg-muted hover:text-primary'"
                                @click="setActiveGymExercise(exerciseIndex)"
                            >
                                <span class="block text-[11px] font-semibold uppercase tracking-wide">{{ tx('training_workspace.log_create.ui.exercise_number', { number: exerciseIndex + 1 }) }}</span>
                                <span class="mt-1 block truncate font-semibold">{{ exercise.title || tx('training_workspace.log_create.ui.exercise_without_name') }}</span>
                            </button>
                        </div>
                    </div>

                    <div
                        v-for="(exercise, exerciseIndex) in form.gym_exercises"
                        :key="exerciseIndex"
                        v-show="activeGymExerciseIndex === exerciseIndex"
                        class="rounded-2xl border border-border bg-inputBg/40 p-3 sm:p-4"
                    >
                        <div class="grid gap-3 md:grid-cols-[1fr_auto] md:items-end">
                            <label class="block text-sm font-semibold text-primary">{{ tx('training_workspace.log_create.ui.exercise_number', { number: exerciseIndex + 1 }) }}
                                <input v-model="exercise.title" class="mt-2 w-full rounded-xl border border-border bg-inputBg px-3 py-2 text-primary" :placeholder="tx('training_workspace.log_create.ui.exercise_name_placeholder')" />
                            </label>
                            <button type="button" class="rounded-xl border border-border px-3 py-2 text-sm font-semibold text-danger hover:bg-danger/10" @click="removeGymExercise(exerciseIndex)">
                                {{ tx('training_workspace.log_create.ui.remove_exercise') }}
                            </button>
                        </div>
                        <label class="mt-3 hidden text-sm font-semibold text-primary md:block">{{ tx('training_workspace.log_create.ui.exercise_note') }}
                            <input v-model="exercise.notes" class="mt-2 w-full rounded-xl border border-border bg-inputBg px-3 py-2 text-primary" :placeholder="tx('training_workspace.log_create.ui.exercise_note_placeholder')" />
                        </label>
                        <details class="mt-3 rounded-xl border border-border bg-card p-3 md:hidden">
                            <summary class="cursor-pointer list-none text-sm font-semibold text-primary">
                                {{ tx('training_workspace.log_create.ui.details') }}
                                <span class="ml-2 text-xs font-normal text-secondary">{{ tx('training_workspace.log_create.optional') }}</span>
                            </summary>
                            <label class="mt-3 block text-sm font-semibold text-primary">{{ tx('training_workspace.log_create.ui.exercise_note') }}
                                <input v-model="exercise.notes" class="mt-2 w-full rounded-xl border border-border bg-inputBg px-3 py-2 text-primary" :placeholder="tx('training_workspace.log_create.ui.exercise_note_placeholder')" />
                            </label>
                        </details>
                        <div
                            v-if="matchingRecentExercise(exercise)"
                            class="mt-3 hidden rounded-xl border border-air-blue/30 bg-air-blue/10 p-3 text-sm md:block"
                        >
                            <div class="flex flex-wrap items-center justify-between gap-3">
                                <div>
                                    <p class="font-semibold text-primary">{{ tx('training_workspace.log_create.ui.recent_values') }}</p>
                                    <p class="mt-1 text-xs text-secondary">{{ recentExerciseLabel(matchingRecentExercise(exercise)) }}</p>
                                </div>
                                <button type="button" class="rounded-xl border border-air-blue/40 px-3 py-2 text-xs font-semibold text-primary hover:bg-air-blue/10" @click="applyRecentExercise(exerciseIndex, matchingRecentExercise(exercise))">
                                    {{ tx('training_workspace.log_create.ui.use_recent_values') }}
                                </button>
                            </div>
                            <div v-if="matchingRecentExercise(exercise).history?.length" class="mt-3 grid gap-2 sm:grid-cols-3">
                                <div
                                    v-for="session in matchingRecentExercise(exercise).history.slice(0, 3)"
                                    :key="`${session.performed_at}-${exerciseHistoryLabel(session)}`"
                                    class="rounded-lg border border-border bg-card/70 px-3 py-2"
                                >
                                    <p class="text-[11px] font-semibold uppercase tracking-wide text-secondary">{{ formatShortDate(session.performed_at) }}</p>
                                    <p class="mt-1 text-xs font-semibold text-primary">{{ exerciseHistoryLabel(session) }}</p>
                                </div>
                            </div>
                        </div>

                        <div class="mt-4 rounded-2xl border border-border bg-card p-3">
                            <div class="flex flex-wrap items-center justify-between gap-3">
                                <div>
                                    <p class="text-xs font-semibold uppercase tracking-wide text-secondary">{{ tx('training_workspace.log_create.ui.sets') }}</p>
                                    <p class="mt-1 text-sm font-semibold text-primary">{{ tx('training_workspace.log_create.ui.set_count', { count: exercise.sets.length, number: exerciseIndex + 1 }) }}</p>
                                </div>
                                <button type="button" class="rounded-xl border border-border bg-inputBg px-3 py-2 text-sm font-semibold text-primary hover:bg-muted" @click="addGymSet(exerciseIndex)">
                                    {{ tx('training_workspace.log_create.ui.add_set') }}
                                </button>
                            </div>
                            <div class="custom-scrollbar mt-3 flex gap-2 overflow-x-auto pb-1">
                                <button
                                    v-for="(set, setIndex) in exercise.sets"
                                    :key="`set-step-${exerciseIndex}-${setIndex}`"
                                    type="button"
                                    class="min-w-28 rounded-xl border px-3 py-2 text-left text-sm transition"
                                    :class="activeGymExerciseIndex === exerciseIndex && activeGymSetIndex === setIndex ? 'border-air-blue bg-air-blue/10 text-primary ring-1 ring-air-blue/30' : set.completed ? 'border-success/40 bg-success/10 text-success' : 'border-border bg-inputBg/40 text-secondary hover:bg-muted hover:text-primary'"
                                    @click="setActiveGymSet(exerciseIndex, setIndex)"
                                >
                                    <span class="block text-[11px] font-semibold uppercase tracking-wide">{{ tx('training_workspace.log_create.ui.set', { number: setIndex + 1 }) }}</span>
                                    <span class="mt-1 block truncate font-semibold">{{ set.completed ? tx('training_workspace.log_create.ui.set_done') : tx('training_workspace.log_create.ui.set_open') }}</span>
                                </button>
                            </div>
                        </div>

                        <div class="mt-4 space-y-3">
                            <div
                                v-for="(set, setIndex) in exercise.sets"
                                :key="setIndex"
                                v-show="activeGymExerciseIndex === exerciseIndex && activeGymSetIndex === setIndex"
                                class="grid gap-3 rounded-2xl border p-3 transition sm:grid-cols-2 lg:grid-cols-6"
                                :class="activeGymExerciseIndex === exerciseIndex && activeGymSetIndex === setIndex ? 'border-air-blue bg-air-blue/10 ring-1 ring-air-blue/30' : set.completed ? 'border-success/40 bg-success/10' : 'border-border bg-card'"
                                @click="setActiveGymSet(exerciseIndex, setIndex)"
                            >
                                <div class="flex items-center justify-between gap-2 lg:block lg:pt-8">
                                    <p class="text-sm font-semibold text-primary">{{ tx('training_workspace.log_create.ui.set', { number: setIndex + 1 }) }}</p>
                                    <div class="flex flex-wrap gap-1">
                                        <span v-if="activeGymExerciseIndex === exerciseIndex && activeGymSetIndex === setIndex" class="rounded-full bg-air-blue/10 px-2 py-1 text-xs font-semibold text-air-blue">{{ tx('training_workspace.log_create.ui.active') }}</span>
                                        <span v-if="set.completed" class="rounded-full bg-success/10 px-2 py-1 text-xs font-semibold text-success">{{ tx('training_workspace.log_create.ui.completed') }}</span>
                                    </div>
                                </div>
                                <label class="block text-sm font-semibold text-primary">{{ tx('training_workspace.log_create.ui.repetitions_short') }}
                                    <input v-model="set.reps" type="number" min="0" class="mt-2 w-full rounded-xl border border-border bg-inputBg px-3 py-2 text-primary" placeholder="15" />
                                </label>
                                <label class="block text-sm font-semibold text-primary">{{ tx('training_workspace.log_create.ui.weight_kg') }}
                                    <input v-model="set.weight_kg" type="number" min="0" step="0.01" class="mt-2 w-full rounded-xl border border-border bg-inputBg px-3 py-2 text-primary" placeholder="30" />
                                </label>
                                <label class="hidden text-sm font-semibold text-primary md:block">{{ tx('training_workspace.log_create.ui.time_minutes') }}
                                    <input v-model="set.duration_minutes" type="number" min="0" step="0.1" class="mt-2 w-full rounded-xl border border-border bg-inputBg px-3 py-2 text-primary" />
                                </label>
                                <div class="grid gap-2 self-end">
                                    <button
                                        type="button"
                                        class="rounded-xl border px-3 py-2 text-sm font-semibold transition"
                                        :class="set.completed ? 'border-success/40 text-success hover:bg-success/10' : 'border-border text-primary hover:bg-muted'"
                                        @click="toggleGymSetDone(exerciseIndex, setIndex)"
                                    >
                                        {{ set.completed ? tx('training_workspace.log_create.ui.set_done') : tx('training_workspace.log_create.ui.set_finished') }}
                                    </button>
                                    <button type="button" class="rounded-xl border border-border px-3 py-2 text-sm font-semibold text-danger hover:bg-danger/10" @click.stop="removeGymSet(exerciseIndex, setIndex)">
                                        {{ tx('training_workspace.log_create.ui.remove') }}
                                    </button>
                                </div>
                                <details class="rounded-xl border border-border bg-card/70 p-3 sm:col-span-2 md:hidden">
                                    <summary class="cursor-pointer list-none text-sm font-semibold text-primary">
                                        {{ tx('training_workspace.log_create.ui.set_details') }}
                                        <span class="ml-2 text-xs font-normal text-secondary">{{ tx('training_workspace.log_create.optional') }}</span>
                                    </summary>
                                    <div class="mt-3 grid gap-3">
                                        <label class="block text-sm font-semibold text-primary">{{ tx('training_workspace.log_create.ui.time_minutes') }}
                                            <input v-model="set.duration_minutes" type="number" min="0" step="0.1" class="mt-2 w-full rounded-xl border border-border bg-inputBg px-3 py-2 text-primary" />
                                        </label>
                                        <label class="block text-sm font-semibold text-primary">{{ tx('training_workspace.log_create.ui.set_comment') }}
                                            <input v-model="set.notes" class="mt-2 w-full rounded-xl border border-border bg-inputBg px-3 py-2 text-primary" />
                                        </label>
                                        <label class="block text-sm font-semibold text-primary">{{ tx('training_workspace.log_create.ui.media_link') }}
                                            <input v-model="set.media_url" type="url" class="mt-2 w-full rounded-xl border border-border bg-inputBg px-3 py-2 text-primary" :placeholder="tx('training_workspace.log_create.ui.media_placeholder')" />
                                        </label>
                                        <label class="block text-sm font-semibold text-primary">{{ tx('training_workspace.log_create.ui.upload_file') }}
                                            <input type="file" accept="image/*,video/*" class="mt-2 w-full rounded-xl border border-border bg-inputBg px-3 py-2 text-primary file:mr-3 file:rounded-md file:border-0 file:bg-buttonPrimary file:px-3 file:py-1 file:text-sm file:font-semibold file:text-buttonTextPrimary" @change="set.media_file = $event.target.files?.[0] || null" />
                                        </label>
                                    </div>
                                </details>
                                <label class="hidden text-sm font-semibold text-primary sm:col-span-2 md:block lg:col-span-6">{{ tx('training_workspace.log_create.ui.set_comment') }}
                                    <input v-model="set.notes" class="mt-2 w-full rounded-xl border border-border bg-inputBg px-3 py-2 text-primary" />
                                </label>
                                <label class="hidden text-sm font-semibold text-primary sm:col-span-2 md:block lg:col-span-6">{{ tx('training_workspace.log_create.ui.media_link') }}
                                    <input v-model="set.media_url" type="url" class="mt-2 w-full rounded-xl border border-border bg-inputBg px-3 py-2 text-primary" :placeholder="tx('training_workspace.log_create.ui.media_technique_placeholder')" />
                                </label>
                                <label class="hidden text-sm font-semibold text-primary sm:col-span-2 md:block lg:col-span-6">{{ tx('training_workspace.log_create.ui.upload_file') }}
                                    <input type="file" accept="image/*,video/*" class="mt-2 w-full rounded-xl border border-border bg-inputBg px-3 py-2 text-primary file:mr-3 file:rounded-md file:border-0 file:bg-buttonPrimary file:px-3 file:py-1 file:text-sm file:font-semibold file:text-buttonTextPrimary" @change="set.media_file = $event.target.files?.[0] || null" />
                                </label>
                            </div>
                        </div>
                        <div class="mt-3 grid gap-2 sm:grid-cols-3">
                            <button type="button" class="rounded-xl border border-border bg-card px-4 py-3 text-sm font-semibold text-primary hover:bg-muted sm:py-2" @click="activeGymSetIndex = Math.max(0, activeGymSetIndex - 1)">
                                {{ tx('training_workspace.log_create.ui.previous_set') }}
                            </button>
                            <button type="button" class="rounded-xl border border-border bg-card px-4 py-3 text-sm font-semibold text-primary hover:bg-muted sm:py-2" @click="nextActiveGymSet">
                                {{ tx('training_workspace.log_create.ui.next_set') }}
                            </button>
                            <button type="button" class="rounded-xl bg-buttonPrimary px-4 py-3 text-sm font-semibold text-buttonTextPrimary sm:py-2" @click="finishActiveGymSet">
                                {{ tx('training_workspace.log_create.ui.set_finished') }}
                            </button>
                        </div>
                    </div>
                </div>

                <div v-else class="space-y-3">
                    <div v-if="selectedTemplates.length" class="grid gap-2 md:grid-cols-2">
                        <button
                            v-for="template in selectedTemplates"
                            :key="template.key"
                            type="button"
                            class="rounded-2xl border border-border bg-inputBg/40 p-4 text-left transition hover:border-air-blue/50 hover:bg-air-blue/10"
                            @click="applyDetailTemplate(template)"
                        >
                            <span class="text-sm font-semibold text-primary">{{ template.label }}</span>
                            <span class="mt-1 block text-xs leading-5 text-secondary">{{ template.description }}</span>
                        </button>
                    </div>
                    <p v-if="selectedType.key === 'long_run' && !visibleEntries.length" class="rounded-xl border border-border bg-inputBg/40 p-3 text-sm text-secondary">
                        {{ tx('training_workspace.log_create.ui.long_run_hint') }}
                    </p>
                    <div
                        v-for="(entry, index) in form.entries"
                        :key="index"
                        class="grid gap-3 rounded-2xl border p-4 transition lg:grid-cols-6"
                        :class="activeEntryIndex === index ? 'border-air-blue bg-air-blue/10 ring-1 ring-air-blue/30' : 'border-border bg-inputBg/40'"
                        @click="setActiveEntry(index)"
                    >
                        <label class="block text-sm font-semibold text-primary lg:col-span-2">{{ selectedType.entryLabel }}
                            <input v-model="entry.title" class="mt-2 w-full rounded-xl border border-border bg-inputBg px-3 py-2 text-primary" :placeholder="selectedType.entryPlaceholder" />
                        </label>
                        <label v-if="hasField('sets')" class="block text-sm font-semibold text-primary">{{ fieldLabel('sets') }}
                            <input v-model="entry.sets" type="number" min="0" class="mt-2 w-full rounded-xl border border-border bg-inputBg px-3 py-2 text-primary" />
                        </label>
                        <label v-if="hasField('reps')" class="block text-sm font-semibold text-primary">{{ fieldLabel('reps') }}
                            <input v-model="entry.reps" type="number" min="0" class="mt-2 w-full rounded-xl border border-border bg-inputBg px-3 py-2 text-primary" />
                        </label>
                        <label v-if="hasField('weight_kg')" class="block text-sm font-semibold text-primary">{{ fieldLabel('weight_kg') }}
                            <input v-model="entry.weight_kg" type="number" min="0" step="0.01" class="mt-2 w-full rounded-xl border border-border bg-inputBg px-3 py-2 text-primary" />
                        </label>
                        <label v-if="hasField('duration_minutes')" class="block text-sm font-semibold text-primary">{{ fieldLabel('duration_minutes') }}
                            <input v-model="entry.duration_minutes" type="number" min="0" step="0.1" class="mt-2 w-full rounded-xl border border-border bg-inputBg px-3 py-2 text-primary" />
                        </label>
                        <label v-if="hasField('distance_km')" class="block text-sm font-semibold text-primary">{{ fieldLabel('distance_km') }}
                            <input v-model="entry.distance_km" type="number" min="0" step="0.01" class="mt-2 w-full rounded-xl border border-border bg-inputBg px-3 py-2 text-primary" />
                        </label>
                        <label v-if="hasField('intensity')" class="block text-sm font-semibold text-primary">{{ fieldLabel('intensity') }}
                            <input v-model="entry.intensity" class="mt-2 w-full rounded-xl border border-border bg-inputBg px-3 py-2 text-primary" :placeholder="tx('training_workspace.log_create.ui.intensity_placeholder')" />
                        </label>
                        <label v-if="hasField('notes')" class="block text-sm font-semibold text-primary lg:col-span-4">{{ fieldLabel('notes') }}
                            <input v-model="entry.notes" class="mt-2 w-full rounded-xl border border-border bg-inputBg px-3 py-2 text-primary" />
                        </label>
                        <label class="block text-sm font-semibold text-primary lg:col-span-4">{{ tx('training_workspace.log_create.ui.media_link') }}
                            <input v-model="entry.media_url" type="url" class="mt-2 w-full rounded-xl border border-border bg-inputBg px-3 py-2 text-primary" :placeholder="tx('training_workspace.log_create.ui.media_placeholder')" />
                        </label>
                        <label class="block text-sm font-semibold text-primary lg:col-span-4">{{ tx('training_workspace.log_create.ui.upload_file') }}
                            <input type="file" accept="image/*,video/*" class="mt-2 w-full rounded-xl border border-border bg-inputBg px-3 py-2 text-primary file:mr-3 file:rounded-md file:border-0 file:bg-buttonPrimary file:px-3 file:py-1 file:text-sm file:font-semibold file:text-buttonTextPrimary" @change="entry.media_file = $event.target.files?.[0] || null" />
                        </label>
                        <button type="button" class="self-end rounded-xl border border-border px-3 py-2 text-sm font-semibold text-danger hover:bg-danger/10" @click="removeEntry(index)">
                            {{ tx('training_workspace.log_create.ui.remove') }}
                        </button>
                    </div>
                </div>
                <div class="flex flex-wrap justify-between gap-2 border-t border-border pt-4">
                    <button type="button" class="rounded-xl border border-border px-4 py-3 text-sm font-semibold text-primary hover:bg-muted" @click="currentTrainingStep = 1">
                        Zurück
                    </button>
                    <button type="button" class="rounded-xl bg-buttonPrimary px-5 py-3 text-sm font-semibold text-buttonTextPrimary" @click="currentTrainingStep = 3">
                        {{ tx('training_workspace.log_create.finish') }}
                    </button>
                </div>
            </section>

            <div
                v-if="mobileTrainingTypeSheetOpen"
                class="fixed inset-0 z-40 bg-black/60 px-3 pb-3 pt-16 sm:hidden"
                @click.self="mobileTrainingTypeSheetOpen = false"
            >
                <div class="mt-auto max-h-[78vh] overflow-hidden rounded-3xl border border-border bg-card shadow-2xl">
                    <div class="flex items-center justify-between gap-3 border-b border-border p-4">
                        <div>
                            <p class="text-xs font-semibold uppercase tracking-wide text-air-blue">{{ tx('training_workspace.log_create.ui.training_type') }}</p>
                            <h3 class="text-lg font-semibold text-primary">{{ tx('training_workspace.log_create.ui.today_question') }}</h3>
                        </div>
                        <button
                            type="button"
                            class="flex h-10 w-10 items-center justify-center rounded-full border border-border text-secondary"
                            @click="mobileTrainingTypeSheetOpen = false"
                            :aria-label="tx('training_workspace.log_create.ui.close')"
                        >
                            <i class="las la-times text-xl"></i>
                        </button>
                    </div>
                    <div class="custom-scrollbar max-h-[62vh] space-y-2 overflow-y-auto p-3">
                        <button
                            v-for="type in trainingTypes"
                            :key="`mobile-type-${type.key}`"
                            type="button"
                            class="flex w-full items-center gap-3 rounded-2xl border p-3 text-left transition"
                            :class="trainingTypeButtonClass(type)"
                            @click="selectTrainingType(type.key)"
                        >
                            <span class="flex h-11 w-11 shrink-0 items-center justify-center rounded-2xl" :class="trainingTypeTheme(type.key).icon">
                                <i :class="type.icon" class="text-xl"></i>
                            </span>
                            <span class="min-w-0 flex-1">
                                <span class="block truncate text-sm font-semibold">{{ type.shortLabel }}</span>
                                <span class="mt-0.5 block truncate text-xs opacity-80">{{ type.label }}</span>
                            </span>
                            <i v-if="form.training_type === type.key" class="las la-check-circle text-xl"></i>
                        </button>
                    </div>
                </div>
            </div>

            <div class="fixed inset-x-0 bottom-0 z-30 border-t border-border bg-bg/95 p-3 pb-[calc(0.75rem+env(safe-area-inset-bottom))] shadow-2xl backdrop-blur lg:hidden">
                <div v-if="usesGymSets && activeGymSet" class="mx-auto max-w-4xl">
                    <button
                        v-if="!mobileLivePanelOpen"
                        type="button"
                        class="flex w-full items-center gap-3 rounded-2xl border border-border bg-card p-3 text-left"
                        @click="mobileLivePanelOpen = true"
                    >
                        <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-air-blue/15 text-air-blue">
                            <i class="las la-dumbbell text-xl"></i>
                        </span>
                        <span class="min-w-0 flex-1">
                            <span class="block truncate text-sm font-semibold text-primary">{{ activeGymExercise?.title || tx('training_workspace.log_create.ui.active_exercise') }}</span>
                            <span class="block truncate text-xs text-secondary">
                                Satz {{ activeGymSetIndex + 1 }} · {{ activeGymSet.reps || 0 }} Wdh. · {{ activeGymSet.weight_kg || 0 }} kg
                            </span>
                        </span>
                        <span class="rounded-xl bg-buttonPrimary px-3 py-2 text-xs font-semibold text-buttonTextPrimary">
                            {{ tx('training_workspace.log_create.ui.open') }}
                        </span>
                    </button>

                    <div v-else class="space-y-2">
                    <div class="flex items-center justify-between gap-3">
                        <div class="min-w-0">
                            <p class="truncate text-sm font-semibold text-primary">{{ activeGymExercise?.title || tx('training_workspace.log_create.ui.active_exercise') }}</p>
                            <p class="text-xs text-secondary">
                                {{ tx('training_workspace.log_create.ui.set', { number: activeGymSetIndex + 1 }) }}{{ restSeconds > 0 ? ` - ${tx('training_workspace.log_create.ui.pause')} ${restTimerLabel}` : '' }}
                            </p>
                        </div>
                        <button v-if="restSeconds > 0" type="button" class="rounded-xl border border-border px-3 py-2 text-xs font-semibold text-primary" @click="stopRestTimer">
                            {{ tx('training_workspace.log_create.ui.pause_stop') }}
                        </button>
                        <button type="button" class="rounded-xl border border-border px-3 py-2 text-xs font-semibold text-primary" @click="mobileLivePanelOpen = false">
                            {{ tx('training_workspace.log_create.ui.minimize') }}
                        </button>
                    </div>
                    <div class="grid grid-cols-3 gap-2">
                        <div class="rounded-xl border border-border bg-card p-2">
                            <p class="text-[11px] font-semibold uppercase text-secondary">{{ tx('training_workspace.log_create.ui.repetitions_short') }}</p>
                            <div class="mt-1 flex items-center justify-between gap-1">
                                <button type="button" class="h-8 w-8 rounded-lg border border-border text-primary" @click="adjustActiveGymSet('reps', -1)">-</button>
                                <span class="text-sm font-semibold text-primary">{{ activeGymSet.reps || 0 }}</span>
                                <button type="button" class="h-8 w-8 rounded-lg border border-border text-primary" @click="adjustActiveGymSet('reps', 1)">+</button>
                            </div>
                        </div>
                        <div class="rounded-xl border border-border bg-card p-2">
                            <p class="text-[11px] font-semibold uppercase text-secondary">{{ tx('training_workspace.log_create.ui.kilograms') }}</p>
                            <div class="mt-1 flex items-center justify-between gap-1">
                                <button type="button" class="h-8 w-8 rounded-lg border border-border text-primary" @click="adjustActiveGymSet('weight_kg', -2.5, 0.5)">-</button>
                                <span class="text-sm font-semibold text-primary">{{ activeGymSet.weight_kg || 0 }}</span>
                                <button type="button" class="h-8 w-8 rounded-lg border border-border text-primary" @click="adjustActiveGymSet('weight_kg', 2.5, 0.5)">+</button>
                            </div>
                        </div>
                        <button type="button" class="rounded-xl bg-buttonPrimary px-3 py-2 text-sm font-semibold text-buttonTextPrimary" @click="finishActiveGymSet">
                            {{ tx('training_workspace.log_create.ui.finish_set') }}
                        </button>
                    </div>
                    <div class="grid grid-cols-2 gap-2">
                        <button type="button" class="rounded-xl border border-border px-3 py-2 text-xs font-semibold text-primary" @click="nextActiveGymSet">
                            {{ tx('training_workspace.log_create.ui.next_set') }}
                        </button>
                        <button type="submit" class="rounded-xl border border-border bg-card px-3 py-2 text-xs font-semibold text-primary disabled:opacity-60" :disabled="form.processing">
                            {{ tx('training_workspace.log_create.ui.save') }}
                        </button>
                    </div>
                    </div>
                </div>
                <div v-else class="mx-auto max-w-4xl space-y-2">
                    <div class="flex items-center gap-3">
                        <div class="min-w-0 flex-1">
                            <p class="truncate text-sm font-semibold text-primary">{{ form.title || selectedTypeLabel }}</p>
                            <p class="truncate text-xs text-secondary">
                                {{ isLiveTraining ? `${tx('training_workspace.log_create.ui.start')} ${liveElapsedLabel}` : detailSummary }}
                            </p>
                        </div>
                        <button v-if="!isLiveTraining" type="button" class="rounded-xl border border-border px-3 py-3 text-xs font-semibold text-primary" @click="startLiveTraining">
                            {{ tx('training_workspace.log_create.ui.start') }}
                        </button>
                        <button v-else type="button" class="rounded-xl border border-border px-3 py-3 text-xs font-semibold text-primary" @click="finishLiveTraining">
                            {{ tx('training_workspace.log_create.finish') }}
                        </button>
                        <button type="submit" class="rounded-xl bg-buttonPrimary px-4 py-3 text-sm font-semibold text-buttonTextPrimary disabled:opacity-60" :disabled="form.processing">
                            {{ tx('training_workspace.log_create.ui.save') }}
                        </button>
                    </div>
                    <div class="grid grid-cols-4 gap-2">
                        <button type="button" class="rounded-xl border border-border bg-card px-2 py-2 text-xs font-semibold text-primary" @click="adjustSessionNumber('duration_minutes', 5)">
                            +5 min
                        </button>
                        <button v-if="showQuickDistanceAction" type="button" class="rounded-xl border border-border bg-card px-2 py-2 text-xs font-semibold text-primary" @click="adjustSessionNumber('distance_km', 0.5, 0.1)">
                            +0,5 km
                        </button>
                        <button type="button" class="rounded-xl border border-border bg-card px-2 py-2 text-xs font-semibold text-primary" @click="nextActiveEntry">
                            Abschnitt +
                        </button>
                        <button type="button" class="rounded-xl border border-border bg-card px-2 py-2 text-xs font-semibold text-primary" @click="setDurationFromLive">
                            Zeit
                        </button>
                    </div>
                    <div v-if="activeEntry" class="grid grid-cols-3 gap-2">
                        <button
                            v-if="hasField('duration_minutes')"
                            type="button"
                            class="rounded-xl border border-air-blue/30 bg-air-blue/10 px-2 py-2 text-xs font-semibold text-primary"
                            @click="adjustActiveEntry('duration_minutes', 1)"
                        >
                            Abschnitt +1 min
                        </button>
                        <button
                            v-if="hasField('distance_km')"
                            type="button"
                            class="rounded-xl border border-air-blue/30 bg-air-blue/10 px-2 py-2 text-xs font-semibold text-primary"
                            @click="adjustActiveEntry('distance_km', 0.1, 0.1)"
                        >
                            Abschnitt +0,1 km
                        </button>
                        <button
                            v-if="hasField('reps')"
                            type="button"
                            class="rounded-xl border border-air-blue/30 bg-air-blue/10 px-2 py-2 text-xs font-semibold text-primary"
                            @click="adjustActiveEntry('reps', 1)"
                        >
                            Wiederholung +
                        </button>
                    </div>
                </div>
            </div>
        </form>
    </div>
</template>
