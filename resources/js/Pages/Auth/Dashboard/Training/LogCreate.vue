<script setup>
import AppLayout from '@/Components/Auth/Layouts/AppLayout.vue'
import SearchableSelect from '@/Components/SearchableSelect.vue'
import { Head, Link, useForm, usePage } from '@inertiajs/vue3'
import { computed, onMounted, onUnmounted, ref, watch } from 'vue'

defineOptions({ layout: AppLayout })

const props = defineProps({
    plans: { type: Array, default: () => [] },
    manageableAthletes: { type: Array, default: () => [] },
    sportCatalog: { type: Array, default: () => [] },
    teams: { type: Array, default: () => [] },
    recentExercises: { type: Object, default: () => ({}) },
    recentSports: { type: Object, default: () => ({}) },
    draftLog: { type: Object, default: null },
})

const page = usePage()

const fallbackSports = [
    { key: 'laufen', label: 'Laufen', category: 'Schnellauswahl' },
    { key: 'gym', label: 'Gym', category: 'Schnellauswahl' },
    { key: 'schwimmen', label: 'Schwimmen', category: 'Schnellauswahl' },
    { key: 'fussball', label: 'Fussball', category: 'Schnellauswahl' },
    { key: 'cycling', label: 'Radfahren', category: 'Schnellauswahl' },
    { key: 'yoga', label: 'Yoga', category: 'Schnellauswahl' },
]

const trainingTypes = [
    {
        key: 'gym',
        label: 'Gym / Krafttraining',
        shortLabel: 'Gym',
        icon: 'las la-dumbbell',
        sport_type: 'gym',
        title: 'Krafttraining',
        fields: ['sets', 'reps', 'weight_kg', 'duration_minutes', 'intensity', 'notes'],
        mode: 'sets',
        detailTitle: 'Uebungen und Saetze',
        entryLabel: 'Uebung',
        entryPlaceholder: 'z. B. Kniebeugen, Bankdruecken, Core',
    },
    {
        key: 'run_interval',
        label: 'Laufintervall',
        shortLabel: 'Intervalle',
        icon: 'las la-stopwatch',
        sport_type: 'laufen',
        title: 'Laufintervall',
        fields: ['reps', 'distance_km', 'duration_minutes', 'intensity', 'notes'],
        mode: 'rows',
        detailTitle: 'Intervalle',
        entryLabel: 'Intervall / Abschnitt',
        entryPlaceholder: 'z. B. 6 x 400 m, Trabpause, Sprint',
    },
    {
        key: 'long_run',
        label: 'Long Run',
        shortLabel: 'Long Run',
        icon: 'las la-route',
        sport_type: 'laufen',
        title: 'Long Run',
        fields: ['distance_km', 'duration_minutes', 'intensity', 'notes'],
        mode: 'rows',
        detailTitle: 'Streckenabschnitte',
        entryLabel: 'Abschnitt / Kilometerblock',
        entryPlaceholder: 'Optional: z. B. km 1-5 locker, km 12-15 Endbeschleunigung',
    },
    {
        key: 'swim',
        label: 'Schwimmen',
        shortLabel: 'Swim',
        icon: 'las la-swimmer',
        sport_type: 'schwimmen',
        title: 'Schwimmtraining',
        fields: ['sets', 'reps', 'distance_km', 'duration_minutes', 'intensity', 'notes'],
        mode: 'rows',
        detailTitle: 'Serien und Technik',
        entryLabel: 'Serie / Technik',
        entryPlaceholder: 'z. B. 8 x 50 m Kraul, Technik Beine',
    },
    {
        key: 'football',
        label: 'Fussball',
        shortLabel: 'Fussball',
        icon: 'las la-futbol',
        sport_type: 'fussball',
        title: 'Fussballtraining',
        fields: ['duration_minutes', 'distance_km', 'intensity', 'notes'],
        mode: 'rows',
        detailTitle: 'Drills und Spielformen',
        entryLabel: 'Drill / Spielform',
        entryPlaceholder: 'z. B. Passform, 4 gegen 4, Torschuss',
    },
    {
        key: 'cycling',
        label: 'Radtraining',
        shortLabel: 'Bike',
        icon: 'las la-biking',
        sport_type: 'cycling',
        title: 'Radtraining',
        fields: ['distance_km', 'duration_minutes', 'intensity', 'notes'],
        mode: 'rows',
        detailTitle: 'Streckenabschnitte',
        entryLabel: 'Abschnitt',
        entryPlaceholder: 'z. B. Zone 2, Bergintervall, Kadenz',
    },
    {
        key: 'generic',
        label: 'Freies Training',
        shortLabel: 'Frei',
        icon: 'las la-clipboard-list',
        sport_type: 'laufen',
        title: 'Training',
        fields: ['sets', 'reps', 'weight_kg', 'duration_minutes', 'distance_km', 'intensity', 'notes'],
        mode: 'rows',
        detailTitle: 'Uebungen / Werte',
        entryLabel: 'Uebung / Abschnitt',
        entryPlaceholder: 'z. B. Technik, Drill, Runde',
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
            description: 'Nur Gesamtdaten plus Gefuehl dokumentieren.',
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
                { title: 'Aktivierung und Mobilitaet', duration_minutes: 12, intensity: 'locker' },
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
            description: 'Grundlage mit ruhiger Intensitaet.',
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
let restInterval = null
let liveInterval = null
let autosaveTimer = null
let autosaveRequestId = 0

const trainingSteps = [
    { id: 1, label: 'Training waehlen', short: 'Start' },
    { id: 2, label: 'Dokumentieren', short: 'Doku' },
    { id: 3, label: 'Abschliessen', short: 'Finish' },
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

    return [...fallbackSports, ...catalog]
        .filter((sport, index, list) => list.findIndex((item) => item.key === sport.key) === index)
})

const athleteOptions = computed(() => [
    { id: '', name: 'Ich selbst' },
    ...(props.manageableAthletes || []),
])

const plannedItems = computed(() => props.plans
    .flatMap((plan) => (plan.items || []).map((item) => ({ ...item, plan })))
    .sort((a, b) => new Date(a.scheduled_at || 0) - new Date(b.scheduled_at || 0)))

const selectedPlannedItem = computed(() => plannedItems.value.find((entry) => Number(entry.id) === Number(form.training_plan_item_id)) || null)
const selectedType = computed(() => trainingTypes.find((type) => type.key === form.training_type) || trainingTypes[trainingTypes.length - 1])
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
        return `${form.gym_exercises.length} Uebungen · ${completedGymSetCount.value}/${gymSetCount.value} Saetze erledigt`
    }

    if (selectedType.value.key === 'long_run' && !visibleEntries.value.length) {
        return 'Gesamtdaten reichen aus · Abschnitte optional'
    }

    return `${visibleEntries.value.length} Detailzeilen`
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
    { label: 'Basisdaten', done: Boolean(form.title && form.sport_type && form.status) },
    { label: 'Zeitpunkt', done: Boolean(form.performed_at) },
    { label: 'Belastung', done: Boolean(sessionMinutes.value || sessionDistanceKm.value || completedGymSetCount.value || form.intensity) },
    { label: 'Details', done: hasTrainingDetails.value },
    { label: 'Koerperfeedback', done: Boolean(form.wellness.rpe || form.wellness.energy || form.wellness.pain || form.wellness.sleep_hours) },
    { label: 'Notiz oder Medien', done: Boolean(form.notes || hasAnyMedia.value) },
    { label: 'Sichtbarkeit', done: Boolean(form.privacy_scope) },
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
    return new Intl.DateTimeFormat('de-DE', { day: '2-digit', month: '2-digit', year: 'numeric' }).format(new Date(value))
}

const formatSaveTime = (value) => {
    if (!value) return ''
    return new Intl.DateTimeFormat('de-DE', { hour: '2-digit', minute: '2-digit', second: '2-digit' }).format(new Date(value))
}

const formatShortDate = (value) => {
    if (!value) return ''
    return new Intl.DateTimeFormat('de-DE', { day: '2-digit', month: '2-digit' }).format(new Date(value))
}

const formatNumber = (value, maximumFractionDigits = 1) => Number(value || 0).toLocaleString('de-DE', { maximumFractionDigits })

const roundedLiveMinutes = () => Math.max(1, Math.round(liveElapsedSeconds.value / 60))

const setDurationFromLive = () => {
    if (!liveElapsedSeconds.value) return
    form.duration_minutes = String(roundedLiveMinutes())
}

const fieldLabel = (field) => ({
    run_interval: {
        reps: 'Wiederholungen',
        distance_km: 'Distanz je Wiederholung/km',
        duration_minutes: 'Zeit min',
        intensity: 'Tempo / RPE',
        notes: 'Hinweis',
    },
    long_run: {
        distance_km: 'Distanz km',
        duration_minutes: 'Zeit min',
        intensity: 'Zone / Gefuehl',
        notes: 'Notiz',
    },
    swim: {
        sets: 'Serien',
        reps: 'Wiederholungen',
        distance_km: 'Meter als km',
        duration_minutes: 'Zeit min',
        intensity: 'Stil / Intensitaet',
        notes: 'Technik-Hinweis',
    },
    football: {
        duration_minutes: 'Dauer min',
        distance_km: 'Laufdistanz km',
        intensity: 'Belastung',
        notes: 'Coachingpunkt',
    },
    cycling: {
        distance_km: 'Distanz km',
        duration_minutes: 'Zeit min',
        intensity: 'Zone / Watt',
        notes: 'Route / Kadenz',
    },
}[selectedType.value.key]?.[field] || {
    sets: 'Saetze',
    reps: 'Wdh./Intervalle',
    weight_kg: 'Gewicht kg',
    duration_minutes: 'Zeit min',
    distance_km: 'Distanz km',
    intensity: 'Intensitaet',
    notes: 'Kommentar',
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

    if (sport === 'gym' || text.match(/\b(saetze|sätze|wiederholungen|gewicht|kraft|bankdruecken|bankdrücken|kniebeuge|deadlift)\b/)) return 'gym'
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
}

const applyPrefillFromQuery = () => {
    const params = new URLSearchParams(String(page.url || '').split('?')[1] || '')
    const planItemId = params.get('plan_item_id')
    if (!planItemId) return

    form.training_plan_item_id = planItemId
    applySelectedPlanItem()
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
watch(() => [form.status, form.performed_at], syncLiveTimer)
watch(() => [
    form.user_id,
    form.team_id,
    form.training_plan_item_id,
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
    <Head title="Training dokumentieren" />

    <div class="space-y-4 pb-32 xl:pb-0">
        <section class="overflow-hidden rounded-2xl border border-border bg-card">
            <div class="border-b border-border bg-inputBg/30 p-4 sm:p-5">
                <div class="flex flex-wrap items-start justify-between gap-4">
                    <div class="min-w-0">
                        <p class="text-xs font-semibold uppercase tracking-wide text-air-blue">Training</p>
                        <h1 class="mt-1 text-2xl font-semibold text-primary sm:text-3xl">Dokumentieren</h1>
                        <p class="mt-2 max-w-3xl text-sm leading-6 text-secondary">
                            Schnell erfassen, Saetze abhaken, bei Bedarf spaeter Details ergaenzen.
                        </p>
                    </div>
                    <Link :href="route('auth.training.index')" class="rounded-xl border border-border px-4 py-2 text-sm font-semibold text-primary hover:bg-muted">
                        Zurueck
                    </Link>
                </div>
            </div>
            <div class="grid gap-2 p-4 sm:grid-cols-2 lg:grid-cols-4">
                <div class="rounded-xl border border-border bg-inputBg/40 p-3">
                    <p class="text-[11px] font-semibold uppercase tracking-wide text-secondary">Vorlage</p>
                    <p class="mt-1 truncate text-sm font-semibold text-primary">{{ selectedType.label }}</p>
                </div>
                <div class="rounded-xl border border-border bg-inputBg/40 p-3">
                    <p class="text-[11px] font-semibold uppercase tracking-wide text-secondary">Status</p>
                    <p class="mt-1 truncate text-sm font-semibold text-primary">{{ detailSummary }}</p>
                </div>
                <div class="rounded-xl border border-border bg-inputBg/40 p-3">
                    <p class="text-[11px] font-semibold uppercase tracking-wide text-secondary">Qualitaet</p>
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
                        <span v-if="autosaveStatus === 'saving'">Entwurf wird gespeichert...</span>
                        <span v-else-if="autosaveStatus === 'dirty'">Aenderungen werden gleich gespeichert</span>
                        <span v-else-if="autosaveStatus === 'error'">{{ autosaveError }}</span>
                        <span v-else>Entwurf gespeichert{{ autosaveSavedAt ? ` um ${formatSaveTime(autosaveSavedAt)}` : '' }}</span>
                    </p>
                    <p v-else class="text-sm font-semibold text-secondary">Noch kein Entwurf</p>
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
            <div class="min-w-0 self-start rounded-2xl border border-border bg-card p-2 2xl:col-span-2">
                <div class="grid gap-2 sm:grid-cols-3">
                    <button
                        v-for="step in trainingSteps"
                        :key="step.id"
                        type="button"
                        class="rounded-xl px-3 py-3 text-left transition"
                        :class="currentTrainingStep === step.id ? 'bg-air-blue text-white shadow-sm shadow-air-blue/20' : 'bg-inputBg/40 text-secondary hover:bg-muted hover:text-primary'"
                        @click="currentTrainingStep = step.id"
                    >
                        <span class="block text-[11px] font-semibold uppercase tracking-wide">Schritt {{ step.id }}</span>
                        <span class="mt-1 block text-sm font-semibold">{{ step.label }}</span>
                    </button>
                </div>
            </div>

            <section v-show="currentTrainingStep === 1" class="min-w-0 space-y-5 rounded-2xl border border-border bg-card p-4 sm:p-5 2xl:col-start-1 2xl:row-start-2">
                <div class="grid gap-4 md:grid-cols-2">
                    <div class="md:col-span-2">
                        <div class="flex items-end justify-between gap-3">
                            <div>
                                <p class="text-sm font-semibold text-primary">Trainingsart</p>
                                <p class="mt-1 text-xs text-secondary">Wische auf dem Handy seitlich durch die Vorlagen.</p>
                            </div>
                            <span class="rounded-full border border-border px-3 py-1 text-xs font-semibold text-secondary">{{ detailSummary }}</span>
                        </div>
                        <div class="mt-3 grid grid-cols-2 gap-2 sm:grid-cols-4 xl:grid-cols-7">
                            <button
                                v-for="type in trainingTypes"
                                :key="type.key"
                                type="button"
                                class="flex min-h-12 items-center gap-2 rounded-2xl border px-3 py-2.5 text-left transition"
                                :class="trainingTypeButtonClass(type)"
                                @click="selectTrainingType(type.key)"
                            >
                                <span class="flex h-8 w-8 shrink-0 items-center justify-center rounded-xl" :class="trainingTypeTheme(type.key).icon">
                                    <i :class="type.icon" class="text-lg"></i>
                                </span>
                                <span class="min-w-0 truncate text-sm font-semibold">{{ type.shortLabel }}</span>
                            </button>
                        </div>
                    </div>
                    <label v-if="athleteOptions.length > 1" class="block text-sm font-semibold text-primary">Sportler
                        <select v-model="form.user_id" class="mt-2 w-full rounded-xl border border-border bg-inputBg px-3 py-2 text-primary">
                            <option v-for="athlete in athleteOptions" :key="athlete.id || 'self'" :value="athlete.id">{{ athlete.name }}</option>
                        </select>
                    </label>
                    <label class="block text-sm font-semibold text-primary">Geplante Einheit
                        <select v-model="form.training_plan_item_id" class="mt-2 w-full rounded-xl border border-border bg-inputBg px-3 py-2 text-primary" @change="applySelectedPlanItem">
                            <option value="">Spontanes Training</option>
                            <option v-for="item in plannedItems" :key="item.id" :value="item.id">
                                {{ item.plan.title }} - {{ item.title }}{{ item.scheduled_at ? ` - ${formatDate(item.scheduled_at)}` : '' }}
                            </option>
                        </select>
                    </label>
                    <label class="block text-sm font-semibold text-primary">Sportart
                        <SearchableSelect
                            v-model="form.sport_type"
                            class="mt-2"
                            :options="sportChoices"
                            label-key="label"
                            value-key="key"
                            placeholder="Sportart suchen"
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
                    <label class="block text-sm font-semibold text-primary md:col-span-2">Titel
                        <input v-model="form.title" class="mt-2 w-full rounded-xl border border-border bg-inputBg px-3 py-2 text-primary" required />
                    </label>
                    <label class="block text-sm font-semibold text-primary">Status
                        <select v-model="form.status" class="mt-2 w-full rounded-xl border border-border bg-inputBg px-3 py-2 text-primary" @change="setStatusDefaults">
                            <option value="completed">Abgeschlossen</option>
                            <option value="in_progress">Laeuft gerade</option>
                            <option value="planned">Geplant</option>
                        </select>
                    </label>
                    <label class="block text-sm font-semibold text-primary">Zeitpunkt
                        <input v-model="form.performed_at" type="datetime-local" class="mt-2 w-full rounded-xl border border-border bg-inputBg px-3 py-2 text-primary" />
                    </label>
                    <div class="rounded-xl border border-border bg-inputBg/40 p-3 md:col-span-2">
                        <div class="flex flex-wrap items-center justify-between gap-3">
                            <div>
                                <p class="text-xs font-semibold uppercase tracking-wide text-secondary">Live-Modus</p>
                                <p class="mt-1 text-sm font-semibold text-primary">
                                    <span v-if="isLiveTraining">Training laeuft seit {{ liveElapsedLabel }}</span>
                                    <span v-else>Schnellstart fuer Training auf dem Platz, im Gym oder unterwegs.</span>
                                </p>
                            </div>
                            <div class="flex flex-wrap gap-2">
                                <button
                                    v-if="!isLiveTraining"
                                    type="button"
                                    class="rounded-xl border border-air-blue/40 bg-air-blue/10 px-3 py-2 text-sm font-semibold text-primary hover:bg-air-blue/20"
                                    @click="startLiveTraining"
                                >
                                    Laeuft gerade starten
                                </button>
                                <button
                                    v-if="isLiveTraining"
                                    type="button"
                                    class="rounded-xl border border-border px-3 py-2 text-sm font-semibold text-primary hover:bg-muted"
                                    @click="setDurationFromLive"
                                >
                                    Zeit uebernehmen
                                </button>
                                <button
                                    v-if="isLiveTraining"
                                    type="button"
                                    class="rounded-xl bg-buttonPrimary px-3 py-2 text-sm font-semibold text-buttonTextPrimary"
                                    @click="finishLiveTraining"
                                >
                                    Abschliessen
                                </button>
                            </div>
                        </div>
                    </div>
                    <label class="block text-sm font-semibold text-primary">Dauer in Minuten
                        <input v-model="form.duration_minutes" type="number" min="0" class="mt-2 w-full rounded-xl border border-border bg-inputBg px-3 py-2 text-primary" />
                    </label>
                    <label v-if="showSessionDistance" class="block text-sm font-semibold text-primary">Distanz in km
                        <input v-model="form.distance_km" type="number" min="0" step="0.01" class="mt-2 w-full rounded-xl border border-border bg-inputBg px-3 py-2 text-primary" />
                    </label>
                    <label class="block text-sm font-semibold text-primary md:col-span-2">Notizen
                        <textarea v-model="form.notes" rows="4" class="mt-2 w-full rounded-xl border border-border bg-inputBg px-3 py-2 text-primary" placeholder="Gefuehl, Technik, Schmerzen, Besonderheiten" />
                    </label>
                    <label v-if="form.user_id" class="block text-sm font-semibold text-primary md:col-span-2">Trainer-Hinweis
                        <textarea v-model="form.trainer_feedback" rows="3" class="mt-2 w-full rounded-xl border border-border bg-inputBg px-3 py-2 text-primary" placeholder="Hinweise, Korrekturen oder Fokus fuer die naechste Einheit" />
                    </label>
                    <label class="block text-sm font-semibold text-primary md:col-span-2">Sichtbarkeit
                        <select v-model="form.privacy_scope" class="mt-2 w-full rounded-xl border border-border bg-inputBg px-3 py-2 text-primary">
                            <option value="trainer">Trainer und berechtigte Betreuer</option>
                            <option value="private">Nur ich</option>
                            <option value="team">Team</option>
                        </select>
                    </label>
                    <label class="flex items-start gap-3 rounded-xl border border-border bg-inputBg/40 p-3 text-sm font-semibold text-primary md:col-span-2">
                        <input v-model="form.notify_people" type="checkbox" class="mt-1 rounded border-border bg-inputBg" />
                        <span>
                            <span>{{ form.user_id ? 'Sportler beim Speichern informieren' : 'Trainer beim Speichern informieren' }}</span>
                            <span class="mt-1 block text-xs font-normal leading-5 text-secondary">
                                Standard ist aktiv. Wenn du es deaktivierst, wird keine Benachrichtigung verschickt; berechtigte Personen koennen die Einheit weiterhin sehen.
                            </span>
                        </span>
                    </label>
                    <div class="md:col-span-2 flex justify-end">
                        <button type="button" class="rounded-xl bg-buttonPrimary px-5 py-3 text-sm font-semibold text-buttonTextPrimary" @click="currentTrainingStep = 2">
                            Weiter dokumentieren
                        </button>
                    </div>
                </div>
            </section>

            <section v-show="currentTrainingStep === 3" class="min-w-0 space-y-5 rounded-2xl border border-border bg-card p-4 sm:p-5 2xl:col-start-1 2xl:row-start-2">
                <div>
                    <p class="text-xs font-semibold uppercase tracking-wide text-air-blue">Schritt 3</p>
                    <h2 class="mt-1 text-xl font-semibold text-primary">Training abschliessen</h2>
                    <p class="mt-2 text-sm leading-6 text-secondary">
                        Diese Werte sind bewusst am Ende. Du musst nur eintragen, was du wirklich weisst.
                    </p>
                </div>
                <div class="grid gap-4 md:grid-cols-2">
                    <label class="block text-sm font-semibold text-primary">Intensitaet
                        <select v-model="form.intensity" class="mt-2 w-full rounded-xl border border-border bg-inputBg px-3 py-2 text-primary">
                            <option value="">Keine Angabe</option>
                            <option value="locker">Leicht / locker</option>
                            <option value="mittel">Mittel</option>
                            <option value="hart">Hart / intensiv</option>
                            <option value="recovery">Regeneration</option>
                        </select>
                    </label>
                    <label class="block text-sm font-semibold text-primary">Verbrannte Kalorien
                        <input v-model="form.calories" type="number" min="0" class="mt-2 w-full rounded-xl border border-border bg-inputBg px-3 py-2 text-primary" placeholder="Optional" />
                    </label>
                    <div class="rounded-xl border border-border bg-inputBg/40 p-3 md:col-span-2">
                        <p class="text-sm font-semibold text-primary">Koerpergefuehl optional</p>
                        <p class="mt-1 text-xs leading-5 text-secondary">
                            Nur ausfuellen, wenn du dein Befinden dokumentieren willst. 1 bedeutet niedrig, 10 bedeutet hoch.
                        </p>
                        <div class="mt-3 grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
                            <label class="block text-sm font-semibold text-primary">Anstrengung 1-10
                                <input v-model="form.wellness.rpe" type="number" min="1" max="10" class="mt-2 w-full rounded-xl border border-border bg-inputBg px-3 py-2 text-primary" />
                            </label>
                            <label class="block text-sm font-semibold text-primary">Energie 1-10
                                <input v-model="form.wellness.energy" type="number" min="1" max="10" class="mt-2 w-full rounded-xl border border-border bg-inputBg px-3 py-2 text-primary" />
                            </label>
                            <label class="block text-sm font-semibold text-primary">Schmerzen 0-10
                                <input v-model="form.wellness.pain" type="number" min="0" max="10" class="mt-2 w-full rounded-xl border border-border bg-inputBg px-3 py-2 text-primary" />
                            </label>
                            <label class="block text-sm font-semibold text-primary">Schlaf in Stunden
                                <input v-model="form.wellness.sleep_hours" type="number" min="0" max="24" step="0.5" class="mt-2 w-full rounded-xl border border-border bg-inputBg px-3 py-2 text-primary" />
                            </label>
                        </div>
                    </div>
                </div>
                <div class="flex flex-wrap justify-between gap-2">
                    <button type="button" class="rounded-xl border border-border px-4 py-3 text-sm font-semibold text-primary hover:bg-muted" @click="currentTrainingStep = 2">
                        Zurueck zur Doku
                    </button>
                    <button type="submit" class="rounded-xl bg-buttonPrimary px-5 py-3 text-sm font-semibold text-buttonTextPrimary disabled:opacity-60" :disabled="form.processing">
                        Training speichern
                    </button>
                </div>
            </section>

            <section class="rounded-2xl border border-air-blue/30 bg-air-blue/10 p-4 2xl:hidden">
                <div class="flex flex-col gap-3 lg:flex-row lg:items-center lg:justify-between">
                    <div class="min-w-0">
                        <p class="text-xs font-semibold uppercase tracking-wide text-air-blue">Arbeitsmodus</p>
                        <p class="mt-1 text-sm font-semibold text-primary">{{ selectedType.label }} · {{ detailSummary }}</p>
                    </div>
                    <div class="flex flex-wrap gap-2">
                        <button
                            v-if="!isLiveTraining"
                            type="button"
                            class="rounded-xl border border-air-blue/40 bg-card px-4 py-2 text-sm font-semibold text-primary hover:bg-air-blue/10"
                            @click="startLiveTraining"
                        >
                            Live starten
                        </button>
                        <button
                            v-else
                            type="button"
                            class="rounded-xl border border-air-blue/40 bg-card px-4 py-2 text-sm font-semibold text-primary hover:bg-air-blue/10"
                            @click="finishLiveTraining"
                        >
                            Live beenden
                        </button>
                        <button type="submit" class="rounded-xl bg-buttonPrimary px-5 py-2 text-sm font-semibold text-buttonTextPrimary disabled:opacity-60" :disabled="form.processing">
                            Training speichern
                        </button>
                    </div>
                </div>
            </section>

            <aside class="hidden min-w-0 space-y-4 2xl:sticky 2xl:top-20 2xl:col-start-2 2xl:row-start-2 2xl:block 2xl:self-start">
                <section class="rounded-2xl border border-border bg-card p-4">
                    <p class="text-xs font-semibold uppercase tracking-wide text-secondary">Aktive Vorlage</p>
                    <h2 class="mt-1 text-lg font-semibold text-primary">{{ selectedType.label }}</h2>
                    <p class="mt-2 text-sm leading-6 text-secondary">
                        {{ usesGymSets ? 'Erfasse zuerst die Uebung und darunter jeden Satz einzeln mit eigenen Wiederholungen und Gewicht.' : 'Die Felder passen sich der Trainingsart an. Bei Long Run sind Abschnitte optional, falls du Tempo- oder Kilometerbloecke dokumentieren willst.' }}
                    </p>
                    <div class="mt-4 rounded-xl border border-border bg-inputBg/40 p-3">
                        <p class="text-xs font-semibold uppercase tracking-wide text-secondary">Moment</p>
                        <p class="mt-1 text-sm font-semibold text-primary">{{ detailSummary }}</p>
                    </div>
                    <div class="mt-3 rounded-xl border border-border bg-inputBg/40 p-3">
                        <div class="flex items-center justify-between gap-3">
                            <p class="text-xs font-semibold uppercase tracking-wide text-secondary">Qualitaetscheck</p>
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
                        <p class="text-xs font-semibold uppercase tracking-wide text-success">Laeuft gerade</p>
                        <p class="mt-1 text-2xl font-semibold text-primary">{{ liveElapsedLabel }}</p>
                        <div class="mt-3 grid grid-cols-2 gap-2">
                            <button type="button" class="rounded-lg border border-border px-3 py-2 text-xs font-semibold text-primary hover:bg-muted" @click="setDurationFromLive">
                                Dauer setzen
                            </button>
                            <button type="button" class="rounded-lg bg-buttonPrimary px-3 py-2 text-xs font-semibold text-buttonTextPrimary" @click="finishLiveTraining">
                                Fertig
                            </button>
                        </div>
                    </div>
                    <div class="mt-3 grid grid-cols-2 gap-2">
                        <div class="rounded-xl border border-border bg-inputBg/40 p-3">
                            <p class="text-[11px] font-semibold uppercase tracking-wide text-secondary">Dauer</p>
                            <p class="mt-1 text-sm font-semibold text-primary">{{ sessionMinutes ? `${formatNumber(sessionMinutes)} min` : '-' }}</p>
                        </div>
                        <div class="rounded-xl border border-border bg-inputBg/40 p-3">
                            <p class="text-[11px] font-semibold uppercase tracking-wide text-secondary">Distanz</p>
                            <p class="mt-1 text-sm font-semibold text-primary">{{ sessionDistanceKm ? `${formatNumber(sessionDistanceKm, 2)} km` : '-' }}</p>
                        </div>
                        <div v-if="sessionPace" class="rounded-xl border border-air-blue/30 bg-air-blue/10 p-3">
                            <p class="text-[11px] font-semibold uppercase tracking-wide text-air-blue">Pace</p>
                            <p class="mt-1 text-sm font-semibold text-primary">{{ sessionPace }}</p>
                        </div>
                        <div v-if="usesGymSets && gymVolumeKg" class="rounded-xl border border-air-blue/30 bg-air-blue/10 p-3">
                            <p class="text-[11px] font-semibold uppercase tracking-wide text-air-blue">Volumen</p>
                            <p class="mt-1 text-sm font-semibold text-primary">{{ formatNumber(gymVolumeKg, 0) }} kg</p>
                        </div>
                    </div>
                    <div v-if="restSeconds > 0" class="mt-3 rounded-xl border border-air-blue/40 bg-air-blue/10 p-3">
                        <p class="text-xs font-semibold uppercase tracking-wide text-air-blue">Pause laeuft</p>
                        <div class="mt-1 flex items-center justify-between gap-3">
                            <p class="text-2xl font-semibold text-primary">{{ restTimerLabel }}</p>
                            <button type="button" class="rounded-lg border border-border px-3 py-1.5 text-xs font-semibold text-primary hover:bg-muted" @click="stopRestTimer">
                                Stop
                            </button>
                        </div>
                    </div>
                </section>
                <button type="submit" class="w-full rounded-xl bg-buttonPrimary px-4 py-3 text-sm font-semibold text-buttonTextPrimary disabled:opacity-60" :disabled="form.processing">
                    Training speichern
                </button>
            </aside>

            <section v-show="currentTrainingStep === 2" class="min-w-0 space-y-4 rounded-2xl border border-border bg-card p-4 sm:p-5 2xl:col-start-1 2xl:row-start-2">
                <div class="flex flex-wrap items-center justify-between gap-3">
                    <div>
                        <p class="text-xs font-semibold uppercase tracking-wide text-secondary">Details</p>
                        <h2 class="text-xl font-semibold text-primary">{{ selectedType.detailTitle }}</h2>
                    </div>
                    <button v-if="!usesGymSets" type="button" class="rounded-xl border border-border bg-inputBg/40 px-4 py-2 text-sm font-semibold text-primary hover:bg-muted" @click="addEntry">
                        Zeile hinzufuegen
                    </button>
                </div>

                <div v-if="usesGymSets" class="space-y-4">
                    <div class="rounded-2xl border border-border bg-inputBg/40 p-3">
                        <div class="flex flex-wrap items-center justify-between gap-3">
                            <div>
                                <p class="text-xs font-semibold uppercase tracking-wide text-secondary">Uebungen</p>
                                <p class="mt-1 text-sm font-semibold text-primary">{{ form.gym_exercises.length }} Uebungen angelegt</p>
                            </div>
                            <button type="button" class="rounded-xl border border-border bg-card px-3 py-2 text-sm font-semibold text-primary hover:bg-muted" @click="addGymExercise">
                                Uebung hinzufuegen
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
                                <span class="block text-[11px] font-semibold uppercase tracking-wide">Uebung {{ exerciseIndex + 1 }}</span>
                                <span class="mt-1 block truncate font-semibold">{{ exercise.title || 'Ohne Namen' }}</span>
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
                            <label class="block text-sm font-semibold text-primary">Uebung {{ exerciseIndex + 1 }}
                                <input v-model="exercise.title" class="mt-2 w-full rounded-xl border border-border bg-inputBg px-3 py-2 text-primary" placeholder="z. B. Kniebeugen" />
                            </label>
                            <button type="button" class="rounded-xl border border-border px-3 py-2 text-sm font-semibold text-danger hover:bg-danger/10" @click="removeGymExercise(exerciseIndex)">
                                Uebung entfernen
                            </button>
                        </div>
                        <label class="mt-3 block text-sm font-semibold text-primary">Notiz zur Uebung
                            <input v-model="exercise.notes" class="mt-2 w-full rounded-xl border border-border bg-inputBg px-3 py-2 text-primary" placeholder="z. B. tief, sauber, letzte Wiederholung schwer" />
                        </label>
                        <div
                            v-if="matchingRecentExercise(exercise)"
                            class="mt-3 rounded-xl border border-air-blue/30 bg-air-blue/10 p-3 text-sm"
                        >
                            <div class="flex flex-wrap items-center justify-between gap-3">
                                <div>
                                    <p class="font-semibold text-primary">Letzte Werte gefunden</p>
                                    <p class="mt-1 text-xs text-secondary">{{ recentExerciseLabel(matchingRecentExercise(exercise)) }}</p>
                                </div>
                                <button type="button" class="rounded-xl border border-air-blue/40 px-3 py-2 text-xs font-semibold text-primary hover:bg-air-blue/10" @click="applyRecentExercise(exerciseIndex, matchingRecentExercise(exercise))">
                                    Letzte Werte uebernehmen
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
                                    <p class="text-xs font-semibold uppercase tracking-wide text-secondary">Saetze</p>
                                    <p class="mt-1 text-sm font-semibold text-primary">{{ exercise.sets.length }} Saetze in Uebung {{ exerciseIndex + 1 }}</p>
                                </div>
                                <button type="button" class="rounded-xl border border-border bg-inputBg px-3 py-2 text-sm font-semibold text-primary hover:bg-muted" @click="addGymSet(exerciseIndex)">
                                    Satz hinzufuegen
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
                                    <span class="block text-[11px] font-semibold uppercase tracking-wide">Satz {{ setIndex + 1 }}</span>
                                    <span class="mt-1 block truncate font-semibold">{{ set.completed ? 'Erledigt' : 'Offen' }}</span>
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
                                    <p class="text-sm font-semibold text-primary">Satz {{ setIndex + 1 }}</p>
                                    <div class="flex flex-wrap gap-1">
                                        <span v-if="activeGymExerciseIndex === exerciseIndex && activeGymSetIndex === setIndex" class="rounded-full bg-air-blue/10 px-2 py-1 text-xs font-semibold text-air-blue">aktiv</span>
                                        <span v-if="set.completed" class="rounded-full bg-success/10 px-2 py-1 text-xs font-semibold text-success">erledigt</span>
                                    </div>
                                </div>
                                <label class="block text-sm font-semibold text-primary">Wdh.
                                    <input v-model="set.reps" type="number" min="0" class="mt-2 w-full rounded-xl border border-border bg-inputBg px-3 py-2 text-primary" placeholder="15" />
                                </label>
                                <label class="block text-sm font-semibold text-primary">Gewicht kg
                                    <input v-model="set.weight_kg" type="number" min="0" step="0.01" class="mt-2 w-full rounded-xl border border-border bg-inputBg px-3 py-2 text-primary" placeholder="30" />
                                </label>
                                <label class="block text-sm font-semibold text-primary">Zeit min
                                    <input v-model="set.duration_minutes" type="number" min="0" step="0.1" class="mt-2 w-full rounded-xl border border-border bg-inputBg px-3 py-2 text-primary" />
                                </label>
                                <div class="grid gap-2 self-end">
                                    <button
                                        type="button"
                                        class="rounded-xl border px-3 py-2 text-sm font-semibold transition"
                                        :class="set.completed ? 'border-success/40 text-success hover:bg-success/10' : 'border-border text-primary hover:bg-muted'"
                                        @click="toggleGymSetDone(exerciseIndex, setIndex)"
                                    >
                                        {{ set.completed ? 'Erledigt' : 'Satz erledigt' }}
                                    </button>
                                    <button type="button" class="rounded-xl border border-border px-3 py-2 text-sm font-semibold text-danger hover:bg-danger/10" @click.stop="removeGymSet(exerciseIndex, setIndex)">
                                        Entfernen
                                    </button>
                                </div>
                                <label class="block text-sm font-semibold text-primary sm:col-span-2 lg:col-span-6">Kommentar zum Satz
                                    <input v-model="set.notes" class="mt-2 w-full rounded-xl border border-border bg-inputBg px-3 py-2 text-primary" />
                                </label>
                                <label class="block text-sm font-semibold text-primary sm:col-span-2 lg:col-span-6">Medien-Link
                                    <input v-model="set.media_url" type="url" class="mt-2 w-full rounded-xl border border-border bg-inputBg px-3 py-2 text-primary" placeholder="Video oder Bild-Link fuer Technikfeedback" />
                                </label>
                                <label class="block text-sm font-semibold text-primary sm:col-span-2 lg:col-span-6">Datei hochladen
                                    <input type="file" accept="image/*,video/*" class="mt-2 w-full rounded-xl border border-border bg-inputBg px-3 py-2 text-primary file:mr-3 file:rounded-md file:border-0 file:bg-buttonPrimary file:px-3 file:py-1 file:text-sm file:font-semibold file:text-buttonTextPrimary" @change="set.media_file = $event.target.files?.[0] || null" />
                                </label>
                            </div>
                        </div>
                        <div class="mt-3 grid gap-2 sm:grid-cols-3">
                            <button type="button" class="rounded-xl border border-border bg-card px-4 py-3 text-sm font-semibold text-primary hover:bg-muted sm:py-2" @click="activeGymSetIndex = Math.max(0, activeGymSetIndex - 1)">
                                Vorheriger Satz
                            </button>
                            <button type="button" class="rounded-xl border border-border bg-card px-4 py-3 text-sm font-semibold text-primary hover:bg-muted sm:py-2" @click="nextActiveGymSet">
                                Naechster Satz
                            </button>
                            <button type="button" class="rounded-xl bg-buttonPrimary px-4 py-3 text-sm font-semibold text-buttonTextPrimary sm:py-2" @click="finishActiveGymSet">
                                Satz erledigt
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
                        Bei einem Long Run musst du hier nichts eintragen, wenn du nur Gesamtdauer und Distanz dokumentieren willst. Nutze Abschnitte nur fuer Kilometerbloecke, Tempoanteile oder besondere Phasen.
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
                            <input v-model="entry.intensity" class="mt-2 w-full rounded-xl border border-border bg-inputBg px-3 py-2 text-primary" placeholder="z. B. RPE 7, locker, Zone 2" />
                        </label>
                        <label v-if="hasField('notes')" class="block text-sm font-semibold text-primary lg:col-span-4">{{ fieldLabel('notes') }}
                            <input v-model="entry.notes" class="mt-2 w-full rounded-xl border border-border bg-inputBg px-3 py-2 text-primary" />
                        </label>
                        <label class="block text-sm font-semibold text-primary lg:col-span-4">Medien-Link
                            <input v-model="entry.media_url" type="url" class="mt-2 w-full rounded-xl border border-border bg-inputBg px-3 py-2 text-primary" placeholder="Video oder Bild-Link" />
                        </label>
                        <label class="block text-sm font-semibold text-primary lg:col-span-4">Datei hochladen
                            <input type="file" accept="image/*,video/*" class="mt-2 w-full rounded-xl border border-border bg-inputBg px-3 py-2 text-primary file:mr-3 file:rounded-md file:border-0 file:bg-buttonPrimary file:px-3 file:py-1 file:text-sm file:font-semibold file:text-buttonTextPrimary" @change="entry.media_file = $event.target.files?.[0] || null" />
                        </label>
                        <button type="button" class="self-end rounded-xl border border-border px-3 py-2 text-sm font-semibold text-danger hover:bg-danger/10" @click="removeEntry(index)">
                            Entfernen
                        </button>
                    </div>
                </div>
                <div class="flex flex-wrap justify-between gap-2 border-t border-border pt-4">
                    <button type="button" class="rounded-xl border border-border px-4 py-3 text-sm font-semibold text-primary hover:bg-muted" @click="currentTrainingStep = 1">
                        Zurueck
                    </button>
                    <button type="button" class="rounded-xl bg-buttonPrimary px-5 py-3 text-sm font-semibold text-buttonTextPrimary" @click="currentTrainingStep = 3">
                        Abschliessen
                    </button>
                </div>
            </section>

            <div class="fixed inset-x-0 bottom-0 z-30 border-t border-border bg-bg/95 p-3 pb-[calc(0.75rem+env(safe-area-inset-bottom))] shadow-2xl backdrop-blur lg:hidden">
                <div v-if="usesGymSets && activeGymSet" class="mx-auto max-w-4xl space-y-2">
                    <div class="flex items-center justify-between gap-3">
                        <div class="min-w-0">
                            <p class="truncate text-sm font-semibold text-primary">{{ activeGymExercise?.title || 'Aktive Uebung' }}</p>
                            <p class="text-xs text-secondary">
                                Satz {{ activeGymSetIndex + 1 }}{{ restSeconds > 0 ? ` - Pause ${restTimerLabel}` : '' }}
                            </p>
                        </div>
                        <button v-if="restSeconds > 0" type="button" class="rounded-xl border border-border px-3 py-2 text-xs font-semibold text-primary" @click="stopRestTimer">
                            Pause stop
                        </button>
                    </div>
                    <div class="grid grid-cols-3 gap-2">
                        <div class="rounded-xl border border-border bg-card p-2">
                            <p class="text-[11px] font-semibold uppercase text-secondary">Wdh.</p>
                            <div class="mt-1 flex items-center justify-between gap-1">
                                <button type="button" class="h-8 w-8 rounded-lg border border-border text-primary" @click="adjustActiveGymSet('reps', -1)">-</button>
                                <span class="text-sm font-semibold text-primary">{{ activeGymSet.reps || 0 }}</span>
                                <button type="button" class="h-8 w-8 rounded-lg border border-border text-primary" @click="adjustActiveGymSet('reps', 1)">+</button>
                            </div>
                        </div>
                        <div class="rounded-xl border border-border bg-card p-2">
                            <p class="text-[11px] font-semibold uppercase text-secondary">kg</p>
                            <div class="mt-1 flex items-center justify-between gap-1">
                                <button type="button" class="h-8 w-8 rounded-lg border border-border text-primary" @click="adjustActiveGymSet('weight_kg', -2.5, 0.5)">-</button>
                                <span class="text-sm font-semibold text-primary">{{ activeGymSet.weight_kg || 0 }}</span>
                                <button type="button" class="h-8 w-8 rounded-lg border border-border text-primary" @click="adjustActiveGymSet('weight_kg', 2.5, 0.5)">+</button>
                            </div>
                        </div>
                        <button type="button" class="rounded-xl bg-buttonPrimary px-3 py-2 text-sm font-semibold text-buttonTextPrimary" @click="finishActiveGymSet">
                            Satz fertig
                        </button>
                    </div>
                    <div class="grid grid-cols-2 gap-2">
                        <button type="button" class="rounded-xl border border-border px-3 py-2 text-xs font-semibold text-primary" @click="nextActiveGymSet">
                            Naechster Satz
                        </button>
                        <button type="submit" class="rounded-xl border border-border bg-card px-3 py-2 text-xs font-semibold text-primary disabled:opacity-60" :disabled="form.processing">
                            Speichern
                        </button>
                    </div>
                </div>
                <div v-else class="mx-auto max-w-4xl space-y-2">
                    <div class="flex items-center gap-3">
                        <div class="min-w-0 flex-1">
                            <p class="truncate text-sm font-semibold text-primary">{{ form.title || selectedType.label }}</p>
                            <p class="truncate text-xs text-secondary">
                                {{ isLiveTraining ? `Laeuft ${liveElapsedLabel}` : detailSummary }}
                            </p>
                        </div>
                        <button v-if="!isLiveTraining" type="button" class="rounded-xl border border-border px-3 py-3 text-xs font-semibold text-primary" @click="startLiveTraining">
                            Start
                        </button>
                        <button v-else type="button" class="rounded-xl border border-border px-3 py-3 text-xs font-semibold text-primary" @click="finishLiveTraining">
                            Fertig
                        </button>
                        <button type="submit" class="rounded-xl bg-buttonPrimary px-4 py-3 text-sm font-semibold text-buttonTextPrimary disabled:opacity-60" :disabled="form.processing">
                            Speichern
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
