import { useForm, usePage } from '@inertiajs/vue3'
import { computed, onMounted, onUnmounted, ref, watch } from 'vue'
import {
    trainingLogDetailTemplates as detailTemplates,
    trainingLogFallbackSports as fallbackSports,
    trainingLogSteps as trainingSteps,
    trainingLogTypeThemes as trainingTypeThemes,
    trainingLogTypes as trainingTypes,
} from '@/support/trainingLogOptions'

export function useTrainingLogCreateForm(props) {
const page = usePage()

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
const mobileLivePanelOpen = ref(false)
const mobileTrainingTypeSheetOpen = ref(false)
let restInterval = null
let liveInterval = null
let autosaveTimer = null
let autosaveRequestId = 0

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
        return `${form.gym_exercises.length} Übungen · ${completedGymSetCount.value}/${gymSetCount.value} Sätze erledigt`
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
    { label: 'Körperfeedback', done: Boolean(form.wellness.rpe || form.wellness.energy || form.wellness.pain || form.wellness.sleep_hours) },
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
        intensity: 'Zone / Gefühl',
        notes: 'Notiz',
    },
    swim: {
        sets: 'Serien',
        reps: 'Wiederholungen',
        distance_km: 'Meter als km',
        duration_minutes: 'Zeit min',
        intensity: 'Stil / Intensität',
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
    sets: 'Sätze',
    reps: 'Wdh./Intervalle',
    weight_kg: 'Gewicht kg',
    duration_minutes: 'Zeit min',
    distance_km: 'Distanz km',
    intensity: 'Intensität',
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

    return {
        detailTemplates,
        fallbackSports,
        trainingSteps,
        trainingTypeThemes,
        trainingTypes,
        page,
        emptyEntry,
        emptyGymSet,
        emptyGymExercise,
        form,
        restSeconds,
        liveNow,
        autosaveStatus,
        autosaveSavedAt,
        autosaveError,
        activeGymExerciseIndex,
        activeGymSetIndex,
        activeEntryIndex,
        currentTrainingStep,
        mobileLivePanelOpen,
        mobileTrainingTypeSheetOpen,
        trainingTypeTheme,
        trainingTypeButtonClass,
        sportChoices,
        athleteOptions,
        plannedItems,
        selectedPlannedItem,
        selectedType,
        visibleFields,
        usesGymSets,
        selectedTemplates,
        hasField,
        showSessionDistance,
        showQuickDistanceAction,
        visibleEntries,
        gymSetCount,
        completedGymSetCount,
        totalEntryDistanceKm,
        totalEntryMinutes,
        gymVolumeKg,
        sessionDistanceKm,
        sessionMinutes,
        sessionPace,
        sessionSpeedKmh,
        selectedAthleteId,
        recentForAthlete,
        recentSportChoices,
        activeGymExercise,
        activeGymSet,
        activeEntry,
        isLiveTraining,
        liveElapsedSeconds,
        restTimerLabel,
        liveElapsedLabel,
        detailSummary,
        hasAnyMedia,
        hasTrainingDetails,
        documentationChecklist,
        documentationScore,
        documentationScoreClass,
        toLocalDateTime,
        formatDate,
        formatSaveTime,
        formatShortDate,
        formatNumber,
        roundedLiveMinutes,
        setDurationFromLive,
        fieldLabel,
        normalizeEntry,
        applyDetailTemplate,
        decimalForInput,
        parsePlannedEntryTime,
        plannedEntryFromItem,
        isBlankEntry,
        inferTrainingTypeFromDraft,
        planItemSearchText,
        inferTrainingTypeFromPlanItem,
        entriesToGymExercises,
        hydrateDraft,
        applyTrainingType,
        selectTrainingType,
        applySelectedPlanItem,
        applyPrefillFromQuery,
        setStatusDefaults,
        startLiveTraining,
        finishLiveTraining,
        addEntry,
        removeEntry,
        setActiveEntry,
        nextActiveEntry,
        adjustNumberField,
        adjustSessionNumber,
        adjustActiveEntry,
        addGymExercise,
        removeGymExercise,
        addGymSet,
        removeGymSet,
        toggleGymSetDone,
        setActiveGymSet,
        setActiveGymExercise,
        adjustActiveGymSet,
        nextActiveGymSet,
        finishActiveGymSet,
        startRestTimer,
        stopRestTimer,
        startLiveTimer,
        stopLiveTimer,
        syncLiveTimer,
        matchingRecentExercise,
        recentExerciseLabel,
        exerciseHistoryLabel,
        applyRecentExercise,
        gymEntriesForSubmit,
        entriesForSubmit,
        payloadForSave,
        autosaveDraft,
        scheduleAutosave,
        submit,
    }
}