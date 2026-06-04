import { router, useForm } from '@inertiajs/vue3'
import { computed, ref } from 'vue'
import { useAiTrainingPlanBuilder } from '@/composables/useAiTrainingPlanBuilder'
import {
    aiPlanDurationPresets,
    aiPlanSteps,
    aiTrainingMethodGroups,
    cadenceLabels,
    defaultTrainingTypeForSport,
    exerciseLibrary,
    levelLabels,
    loadLabels,
    permissionLabels,
    phaseLabels,
    planTrainingTypes,
    planWizardSteps,
    sports,
    trainingSections,
} from '@/support/trainingOptions'

export function useTrainingWorkspace(props) {
    const activeSport = ref('all')
    const activeTrainingSection = ref('overview')
    const activeModal = ref(null)
    const deleteText = ref('')
    const selectedPlan = ref(null)
    const selectedItem = ref(null)
    const selectedDraft = ref(null)
    const activityImageInput = ref(null)
    const planImageInput = ref(null)
    const draggedItem = ref(null)

    const planWizardStep = ref(0)

    const activityForm = useForm({
        title: '',
        activity_type: 'laufen',
        started_at: '',
        duration_minutes: '',
        distance_km: '',
        calories: '',
        image: null,
    })

    const emptyLogEntry = () => ({
        title: '',
        sets: '',
        reps: '',
        weight_kg: '',
        duration_minutes: '',
        distance_km: '',
        intensity: '',
        notes: '',
    })

    const logForm = useForm({
        user_id: '',
        team_id: '',
        training_plan_item_id: '',
        title: '',
        sport_type: 'laufen',
        status: 'completed',
        performed_at: '',
        duration_minutes: '',
        distance_km: '',
        calories: '',
        intensity: 'mittel',
        notes: '',
        trainer_feedback: '',
        entries: [emptyLogEntry()],
    })

    const planForm = useForm({
        title: '',
        description: '',
        cadence: 'weekly',
        starts_on: '',
        ends_on: '',
        goal: '',
        phase: 'base',
        level: 'intermediate',
        weeks: '',
        weekly_sessions: '',
        macrocycle: '',
        mesocycle: '',
        deload_week: '',
        competition_date: '',
        status: 'published',
        share_permission: 'read',
        team_id: '',
        user_ids: [],
        item_training_type: 'long_run',
        item_title: '',
        item_sport_type: 'laufen',
        item_description: '',
        item_scheduled_at: '',
        item_week: '',
        item_duration_minutes: '',
        item_distance_km: '',
        item_calories: '',
        item_intensity: 'mittel',
        item_load: 'medium',
        item_focus: '',
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
        goal: '',
        phase: 'base',
        level: 'intermediate',
        weeks: '',
        weekly_sessions: '',
        macrocycle: '',
        mesocycle: '',
        deload_week: '',
        competition_date: '',
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
        week: '',
        duration_minutes: '',
        distance_km: '',
        calories: '',
        intensity: 'mittel',
        load: 'medium',
        focus: '',
        todos: '',
        image: null,
        video_url: '',
        metrics: {},
    })

    const editItemForm = useForm({
        title: '',
        sport_type: 'laufen',
        description: '',
        scheduled_at: '',
        week: '',
        duration_minutes: '',
        distance_km: '',
        calories: '',
        intensity: 'mittel',
        load: 'medium',
        focus: '',
        todos: '',
        image: null,
        video_url: '',
        metrics: {},
    })

    const missedForm = useForm({
        user_id: '',
        reason: 'keine_zeit',
        notes: '',
    })

    const selectedSport = computed(() => sports.find((sport) => sport.key === activeSport.value) || sports[0])
    const planSport = computed(() => sports.find((sport) => sport.key === planForm.item_sport_type) || sports[1])
    const itemSport = computed(() => sports.find((sport) => sport.key === itemForm.sport_type) || sports[1])
    const editItemSport = computed(() => sports.find((sport) => sport.key === editItemForm.sport_type) || sports[1])
    const visibleLogs = computed(() => (props.logs || []).filter((log) => Number(log.id) !== Number(props.activeDraftLog?.id)))
    const sportChoices = computed(() => {
        const customSports = (props.sportCatalog || [])
            .map((sport) => ({
                key: sport.slug || sport.name,
                label: sport.name,
                icon: 'las la-running',
                accent: 'bg-air-blue',
            }))
            .filter((sport) => sport.key)

        return [...sports.filter((sport) => sport.key !== 'all'), ...customSports]
            .filter((sport, index, list) => list.findIndex((item) => item.key === sport.key) === index)
    })

    const {
        aiGeneratedPlanInsights,
        aiGeneratedPlans,
        aiPlanCannotGenerate,
        aiPlanForm,
        aiPlanLimitLabel,
        aiPlanMaxItems,
        aiPlanMaxWeeks,
        aiPlanRequestedItems,
        aiPlanSourcePlan,
        aiPlanSportChoices,
        aiPlanStep,
        aiPlanTooLarge,
        aiPlanWeeksTooLong,
        aiProfileCompletionUrl,
        aiProfileEstimateAllowed,
        aiProfileMissingFields,
        aiProfileMissingMessage,
        aiTrainingPlan,
        aiTrainingPlanAvailable,
        aiTrainingPlanError,
        aiTrainingPlanGenerating,
        aiTrainingPlanMessage,
        aiTrainingPlanPreview,
        aiTrainingPlanSaving,
        aiTrainingProviderLabel,
        canOpenAiPlanStep,
        continueAiTrainingPlan,
        generateAiTrainingPlan,
        generateAiTrainingPlanWithProfileEstimates,
        openAiTrainingPlanModal,
        qualityRiskClass,
        qualityStatusClass,
        resetAiTrainingPlanForm,
        saveAiTrainingPlan,
        selectAiPlanSportType,
        setAiPlanDurationPreset,
    } = useAiTrainingPlanBuilder({
        activeModal,
        activeSport,
        aiCapabilities: () => props.aiCapabilities,
        defaultTrainingTypeForSport,
        onPlanSaved: () => {
            activeTrainingSection.value = 'plans'
            closeModal()
            router.reload({ preserveScroll: true })
        },
        plans: () => props.plans,
        sportChoices,
    })

    const plannedLogItems = computed(() => props.plans
        .flatMap((plan) => (plan.items || []).map((item) => ({ ...item, plan })))
        .sort((a, b) => new Date(a.scheduled_at || 0) - new Date(b.scheduled_at || 0)))

    const athleteOptions = computed(() => [
        { id: '', name: 'Ich selbst', email: '' },
        ...(props.manageableAthletes || []),
    ])

    const filteredPlans = computed(() => {
        if (activeSport.value === 'all') return props.plans

        return props.plans.filter((plan) => plan.items?.some((item) => item.sport_type === activeSport.value))
    })

    const upcomingItems = computed(() => props.plans
        .flatMap((plan) => (plan.items || []).map((item) => ({ ...item, plan })))
        .filter((item) => item.scheduled_at)
        .sort((a, b) => new Date(a.scheduled_at) - new Date(b.scheduled_at))
        .slice(0, 5))

    const weekDays = computed(() => {
        const today = new Date()
        const monday = new Date(today)
        monday.setDate(today.getDate() - ((today.getDay() + 6) % 7))
        monday.setHours(0, 0, 0, 0)

        return Array.from({ length: 7 }, (_, index) => {
            const date = new Date(monday)
            date.setDate(monday.getDate() + index)
            const key = date.toISOString().slice(0, 10)

            return {
                key,
                date,
                items: plannedLogItems.value.filter((item) => item.scheduled_at?.slice(0, 10) === key),
            }
        })
    })

    const plannedThisWeekCount = computed(() => weekDays.value.reduce((count, day) => count + day.items.length, 0))
    const completedThisWeekCount = computed(() => {
        const keys = new Set(weekDays.value.map((day) => day.key))

        return visibleLogs.value.filter((log) => {
            const value = log.performed_at || log.created_at
            return value && keys.has(value.slice(0, 10))
        }).length
    })

    const nextTrainingItem = computed(() => upcomingItems.value[0] || null)

    const trainerDashboard = computed(() => {
        const overdue = plannedLogItems.value.filter((item) => {
            if (!item.scheduled_at) return false
            if (item.log_statuses?.some((log) => ['completed', 'missed'].includes(log.status))) return false

            return new Date(item.scheduled_at) < new Date()
        })

        const missed = plannedLogItems.value.filter((item) => item.log_statuses?.some((log) => log.status === 'missed'))
        const feedbackOpen = visibleLogs.value.filter((log) => log.status === 'completed' && !log.trainer_feedback && log.athlete?.id)
        const painSignals = visibleLogs.value.filter((log) => Number(log.metrics?.wellness?.pain || 0) >= 4)

        return { overdue, missed, feedbackOpen, painSignals }
    })

    const athleteCockpit = computed(() => Object.values(visibleLogs.value.reduce((groups, log) => {
        const athlete = log.athlete || { id: 'self', name: 'Ich' }
        const key = athlete.id || 'self'
        const wellness = log.metrics?.wellness || {}

        groups[key] ||= {
            athlete,
            sessions: 0,
            minutes: 0,
            meters: 0,
            painTotal: 0,
            painCount: 0,
            rpeTotal: 0,
            rpeCount: 0,
            latest: null,
        }

        groups[key].sessions += 1
        groups[key].minutes += Number(log.duration_minutes || 0)
        groups[key].meters += Number(log.distance_meters || 0)

        if (wellness.pain !== undefined && wellness.pain !== '') {
            groups[key].painTotal += Number(wellness.pain)
            groups[key].painCount += 1
        }

        if (wellness.rpe !== undefined && wellness.rpe !== '') {
            groups[key].rpeTotal += Number(wellness.rpe)
            groups[key].rpeCount += 1
        }

        if (!groups[key].latest || new Date(log.performed_at || log.created_at) > new Date(groups[key].latest.performed_at || groups[key].latest.created_at)) {
            groups[key].latest = log
        }

        return groups
    }, {})).map((entry) => ({
        ...entry,
        avgPain: entry.painCount ? (entry.painTotal / entry.painCount).toFixed(1) : '-',
        avgRpe: entry.rpeCount ? (entry.rpeTotal / entry.rpeCount).toFixed(1) : '-',
    })).sort((a, b) => b.sessions - a.sessions))

    const sportStats = computed(() => Object.values(visibleLogs.value.reduce((groups, log) => {
        const key = log.sport_type || 'training'
        groups[key] ||= { key, label: sportLabel(key), sessions: 0, minutes: 0, meters: 0 }
        groups[key].sessions += 1
        groups[key].minutes += Number(log.duration_minutes || 0)
        groups[key].meters += Number(log.distance_meters || 0)

        return groups
    }, {})).sort((a, b) => b.sessions - a.sessions))

    const templatePlans = computed(() => props.plans.filter((plan) => plan.settings?.is_template_copy || plan.status === 'draft'))

    const selectedTeamMembers = computed(() => {
        const team = props.teams.find((item) => Number(item.id) === Number(planForm.team_id))
        return team?.users || []
    })

    const selectPlanTrainingType = (key) => {
        const type = planTrainingTypes.find((item) => item.key === key) || planTrainingTypes[planTrainingTypes.length - 1]
        planForm.item_training_type = type.key
        planForm.item_sport_type = type.sport_type
        planForm.item_metrics = {
            ...planForm.item_metrics,
            _training_type: type.key,
        }
    }

    const canOpenPlanWizardStep = (index) => index === 0 || Boolean(planForm.title?.trim())
    const goToPlanWizardStep = (index) => {
        if (!canOpenPlanWizardStep(index)) return
        planWizardStep.value = index
    }

    const planWizardCanContinue = computed(() => {
        if (planWizardStep.value === 0) return Boolean(planForm.title?.trim())
        if (planWizardStep.value === planWizardSteps.length - 1) return Boolean(planForm.item_title?.trim())

        return true
    })

    const nextPlanWizardStep = () => {
        if (!planWizardCanContinue.value) return
        planWizardStep.value = Math.min(planWizardStep.value + 1, planWizardSteps.length - 1)
    }

    const previousPlanWizardStep = () => {
        planWizardStep.value = Math.max(planWizardStep.value - 1, 0)
    }

    const resetPlanForm = () => {
        planForm.reset()
        planWizardStep.value = 0
        planForm.cadence = 'weekly'
        planForm.status = 'published'
        planForm.share_permission = 'read'
        planForm.phase = 'base'
        planForm.level = 'intermediate'
        planForm.macrocycle = ''
        planForm.mesocycle = ''
        planForm.deload_week = ''
        planForm.competition_date = ''
        planForm.item_load = 'medium'
        planForm.item_week = 1
        planForm.item_training_type = activeSport.value === 'gym' ? 'gym' : activeSport.value === 'schwimmen' ? 'swim' : activeSport.value === 'fussball' ? 'football' : activeSport.value === 'cycling' ? 'cycling' : 'long_run'
        planForm.item_sport_type = planTrainingTypes.find((type) => type.key === planForm.item_training_type)?.sport_type || (activeSport.value === 'all' ? 'laufen' : activeSport.value)
        planForm.item_intensity = 'mittel'
        planForm.item_metrics = { _training_type: planForm.item_training_type }
        if (planImageInput.value) planImageInput.value.value = ''
    }

    const openModal = (name, plan = null, item = null) => {
        selectedPlan.value = plan
        selectedItem.value = item
        deleteText.value = ''

        if (name === 'plan') resetPlanForm()
        if (name === 'ai-plan') resetAiTrainingPlanForm()
        if (name === 'activity') {
            activityForm.reset()
            activityForm.activity_type = activeSport.value === 'all' ? 'laufen' : activeSport.value
            if (activityImageInput.value) activityImageInput.value.value = ''
        }
        if (name === 'log') {
            logForm.reset()
            logForm.user_id = ''
            logForm.team_id = ''
            logForm.training_plan_item_id = ''
            logForm.status = 'completed'
            logForm.sport_type = activeSport.value === 'all' ? 'laufen' : activeSport.value
            logForm.intensity = 'mittel'
            logForm.entries = [emptyLogEntry()]
            logForm.clearErrors()
        }
        if (name === 'edit' && plan) {
            editForm.title = plan.title || ''
            editForm.description = plan.description || ''
            editForm.cadence = plan.cadence || 'weekly'
            editForm.starts_on = plan.starts_on || ''
            editForm.ends_on = plan.ends_on || ''
            editForm.goal = plan.settings?.goal || ''
            editForm.phase = plan.settings?.phase || 'base'
            editForm.level = plan.settings?.level || 'intermediate'
            editForm.weeks = plan.settings?.weeks || ''
            editForm.weekly_sessions = plan.settings?.weekly_sessions || ''
            editForm.macrocycle = plan.settings?.macrocycle || ''
            editForm.mesocycle = plan.settings?.mesocycle || ''
            editForm.deload_week = plan.settings?.deload_week || ''
            editForm.competition_date = plan.settings?.competition_date || ''
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
            itemForm.load = 'medium'
            itemForm.week = nextPlanWeek(plan)
            itemForm.distance_km = ''
            itemForm.calories = ''
            itemForm.focus = ''
            itemForm.metrics = {}
        }
        if (name === 'item-edit' && plan && item) {
            editItemForm.title = item.title || ''
            editItemForm.sport_type = item.sport_type || 'laufen'
            editItemForm.description = item.description || ''
            editItemForm.scheduled_at = toLocalDateTime(item.scheduled_at)
            editItemForm.week = item.metrics?.Woche || ''
            editItemForm.duration_minutes = item.duration_minutes || ''
            editItemForm.distance_km = item.distance_meters ? (Number(item.distance_meters) / 1000).toFixed(2) : ''
            editItemForm.calories = item.calories || ''
            editItemForm.intensity = item.intensity || 'mittel'
            editItemForm.load = item.metrics?.Belastung || 'medium'
            editItemForm.focus = item.metrics?.Fokus || ''
            editItemForm.todos = (item.todos || []).join('\n')
            editItemForm.image = null
            editItemForm.video_url = item.video_url || ''
            editItemForm.metrics = Object.fromEntries(Object.entries(item.metrics || {}).filter(([key]) => !['Woche', 'Belastung', 'Fokus', '_training_type', 'training_type', 'Trainingstyp'].includes(key)))
            editItemForm.clearErrors()
        }
        if (name === 'item-missed' && plan && item) {
            missedForm.reset()
            missedForm.user_id = ''
            missedForm.reason = 'keine_zeit'
            missedForm.notes = ''
            missedForm.clearErrors()
        }

        activeModal.value = name
    }

    const nextPlanWeek = (plan) => {
        const weeks = (plan?.items || [])
            .map((item) => Number(item.metrics?.Woche || 0))
            .filter(Boolean)

        return weeks.length ? Math.max(...weeks) : 1
    }

    const closeModal = () => {
        activeModal.value = null
        selectedPlan.value = null
        selectedItem.value = null
        selectedDraft.value = null
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

    const setEditItemImage = (event) => {
        editItemForm.image = event.target.files?.[0] || null
    }

    const submitActivity = () => {
        activityForm.post(route('auth.training.activities.store'), {
            preserveScroll: true,
            forceFormData: true,
            onSuccess: closeModal,
        })
    }

    const openLogPage = () => {
        router.visit(route('auth.training.logs.create'))
    }

    const openDraftDelete = () => {
        if (!props.activeDraftLog) return
        selectedDraft.value = props.activeDraftLog
        deleteText.value = ''
        activeModal.value = 'draft-delete'
    }

    const logStatusLabel = (status) => ({
        draft: 'Entwurf',
        planned: 'Geplant',
        in_progress: 'Läuft gerade',
        completed: 'Abgeschlossen',
        missed: 'Nicht gemacht',
    }[status] || status)

    const applySelectedPlanItem = () => {
        const item = plannedLogItems.value.find((entry) => Number(entry.id) === Number(logForm.training_plan_item_id))
        if (!item) return

        logForm.title = logForm.title || item.title || ''
        logForm.sport_type = item.sport_type || logForm.sport_type
        logForm.performed_at = logForm.performed_at || toLocalDateTime(item.scheduled_at)
        logForm.duration_minutes = logForm.duration_minutes || item.duration_minutes || ''
        logForm.distance_km = logForm.distance_km || (item.distance_meters ? (Number(item.distance_meters) / 1000).toFixed(2) : '')
        logForm.calories = logForm.calories || item.calories || ''
        logForm.intensity = item.intensity || logForm.intensity
        logForm.notes = logForm.notes || item.description || ''
    }

    const setLogStatus = () => {
        if (logForm.status === 'in_progress' && !logForm.performed_at) {
            logForm.performed_at = toLocalDateTime(new Date())
        }
    }

    const addLogEntry = () => {
        logForm.entries = [...logForm.entries, emptyLogEntry()]
    }

    const removeLogEntry = (index) => {
        logForm.entries = logForm.entries.filter((_, entryIndex) => entryIndex !== index)
        if (!logForm.entries.length) {
            logForm.entries = [emptyLogEntry()]
        }
    }

    const submitLog = () => {
        logForm.post(route('auth.training.logs.store'), {
            preserveScroll: true,
            onSuccess: closeModal,
        })
    }

    const submitPlan = () => {
        if (!planForm.title?.trim()) {
            planWizardStep.value = 0
            return
        }
        if (!planForm.item_title?.trim()) {
            planWizardStep.value = planWizardSteps.length - 1
            return
        }

        planForm.item_metrics = {
            ...planForm.item_metrics,
            _training_type: planForm.item_training_type,
        }

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

    const deleteDraft = () => {
        if (!selectedDraft.value || deleteText.value !== 'delete') return
        router.delete(route('auth.training.logs.draft.destroy', selectedDraft.value.id), {
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

    const updatePlanItem = () => {
        if (!selectedPlan.value || !selectedItem.value) return

        editItemForm
            .transform((data) => ({ ...data, _method: 'put' }))
            .post(route('auth.training.plans.items.update', [selectedPlan.value.id, selectedItem.value.id]), {
                preserveScroll: true,
                forceFormData: true,
                onSuccess: closeModal,
            })
    }

    const duplicatePlanItem = (plan, item) => {
        router.post(route('auth.training.plans.items.duplicate', [plan.id, item.id]), {}, { preserveScroll: true })
    }

    const duplicatePlan = (plan) => {
        router.post(route('auth.training.plans.duplicate', plan.id), {}, { preserveScroll: true })
    }

    const itemPayload = (item, scheduledAt = null) => ({
        title: item.title || '',
        sport_type: item.sport_type || 'laufen',
        description: item.description || '',
        scheduled_at: scheduledAt ?? toLocalDateTime(item.scheduled_at),
        week: item.metrics?.Woche || '',
        duration_minutes: item.duration_minutes || '',
        distance_km: item.distance_meters ? (Number(item.distance_meters) / 1000).toFixed(2) : '',
        calories: item.calories || '',
        intensity: item.intensity || 'mittel',
        load: item.metrics?.Belastung || 'medium',
        focus: item.metrics?.Fokus || '',
        todos: (item.todos || []).join('\n'),
        video_url: item.video_url || '',
        metrics: Object.fromEntries(Object.entries(item.metrics || {}).filter(([key]) => !['Woche', 'Belastung', 'Fokus', '_training_type', 'training_type', 'Trainingstyp'].includes(key))),
        _method: 'put',
    })

    const openPlanItem = (item) => {
        router.visit(route('auth.training.plans.items.show', [item.plan.id, item.id]))
    }

    const startDragItem = (item) => {
        draggedItem.value = item
    }

    const dropItemOnDay = (day) => {
        if (!draggedItem.value) return

        const item = draggedItem.value
        const previousTime = item.scheduled_at ? toLocalDateTime(item.scheduled_at).slice(11, 16) : '18:00'
        const scheduledAt = `${day.key}T${previousTime || '18:00'}`

        router.post(route('auth.training.plans.items.update', [item.plan.id, item.id]), itemPayload(item, scheduledAt), {
            preserveScroll: true,
            onFinish: () => { draggedItem.value = null },
        })
    }

    const applyExerciseTemplate = (template, form = itemForm) => {
        form.sport_type = template.sport_type
        form.title = template.title
        form.focus = template.focus
        form.duration_minutes = template.duration_minutes
        form.todos = template.todos
        form.metrics = { ...template.metrics }
    }

    const applyPlanExerciseTemplate = (template) => {
        planForm.item_training_type = template.training_type || planForm.item_training_type || 'generic'
        planForm.item_sport_type = template.sport_type
        planForm.item_title = template.title
        planForm.item_focus = template.focus
        planForm.item_duration_minutes = template.duration_minutes
        planForm.item_todos = template.todos
        planForm.item_metrics = { ...template.metrics, _training_type: planForm.item_training_type }
    }

    const documentPlanItem = (item) => {
        router.visit(route('auth.training.logs.create', { plan_item_id: item.id }))
    }

    const markPlanItemMissed = () => {
        if (!selectedPlan.value || !selectedItem.value) return

        missedForm.post(route('auth.training.plans.items.missed', [selectedPlan.value.id, selectedItem.value.id]), {
            preserveScroll: true,
            onSuccess: closeModal,
        })
    }

    const deletePlanItem = () => {
        if (!selectedPlan.value || !selectedItem.value || deleteText.value !== 'delete') return

        router.delete(route('auth.training.plans.items.destroy', [selectedPlan.value.id, selectedItem.value.id]), {
            preserveScroll: true,
            onSuccess: closeModal,
        })
    }

    const formatDate = (value) => {
        if (!value) return '-'
        return new Intl.DateTimeFormat('de-DE', { day: '2-digit', month: '2-digit', year: 'numeric' }).format(new Date(value))
    }

    const formatWeekday = (value) => new Intl.DateTimeFormat('de-DE', { weekday: 'short', day: '2-digit', month: '2-digit' }).format(new Date(value))

    const itemStatusLabel = (item) => {
        if (item.log_statuses?.some((log) => log.status === 'completed')) return 'Erledigt'
        if (item.log_statuses?.some((log) => log.status === 'missed')) return 'Nicht gemacht'
        if (item.scheduled_at && new Date(item.scheduled_at) < new Date()) return 'Fällig'

        return 'Geplant'
    }

    const itemStatusClass = (item) => {
        const label = itemStatusLabel(item)
        if (label === 'Erledigt') return 'bg-success/10 text-success'
        if (label === 'Nicht gemacht') return 'bg-danger/10 text-danger'
        if (label === 'Fällig') return 'bg-warning/10 text-warning'

        return 'bg-muted text-secondary'
    }

    const formatTime = (value) => {
        if (!value) return ''
        return new Intl.DateTimeFormat('de-DE', { hour: '2-digit', minute: '2-digit' }).format(new Date(value))
    }

    const toLocalDateTime = (value) => {
        if (!value) return ''
        const date = new Date(value)
        if (Number.isNaN(date.getTime())) return ''
        const offsetDate = new Date(date.getTime() - date.getTimezoneOffset() * 60000)
        return offsetDate.toISOString().slice(0, 16)
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

    const sportLabel = (key) => sportChoices.value.find((sport) => sport.key === key)?.label || key || 'Training'
    const sportIcon = (key) => sports.find((sport) => sport.key === key)?.icon || 'las la-running'
    const sportAccent = (key) => sports.find((sport) => sport.key === key)?.accent || 'bg-air-blue'

    return {
        activeSport,
        activeTrainingSection,
        activeModal,
        deleteText,
        selectedPlan,
        selectedItem,
        selectedDraft,
        activityImageInput,
        planImageInput,
        draggedItem,
        planWizardStep,
        activityForm,
        emptyLogEntry,
        logForm,
        planForm,
        editForm,
        itemForm,
        editItemForm,
        missedForm,
        selectedSport,
        planSport,
        itemSport,
        editItemSport,
        visibleLogs,
        sportChoices,
        aiGeneratedPlanInsights,
        aiGeneratedPlans,
        aiPlanCannotGenerate,
        aiPlanForm,
        aiPlanLimitLabel,
        aiPlanMaxItems,
        aiPlanMaxWeeks,
        aiPlanRequestedItems,
        aiPlanSourcePlan,
        aiPlanSportChoices,
        aiPlanStep,
        aiPlanTooLarge,
        aiPlanWeeksTooLong,
        aiProfileCompletionUrl,
        aiProfileEstimateAllowed,
        aiProfileMissingFields,
        aiProfileMissingMessage,
        aiTrainingPlan,
        aiTrainingPlanAvailable,
        aiTrainingPlanError,
        aiTrainingPlanGenerating,
        aiTrainingPlanMessage,
        aiTrainingPlanPreview,
        aiTrainingPlanSaving,
        aiTrainingProviderLabel,
        canOpenAiPlanStep,
        continueAiTrainingPlan,
        generateAiTrainingPlan,
        generateAiTrainingPlanWithProfileEstimates,
        openAiTrainingPlanModal,
        qualityRiskClass,
        qualityStatusClass,
        resetAiTrainingPlanForm,
        saveAiTrainingPlan,
        selectAiPlanSportType,
        setAiPlanDurationPreset,
        plannedLogItems,
        athleteOptions,
        filteredPlans,
        upcomingItems,
        weekDays,
        plannedThisWeekCount,
        completedThisWeekCount,
        nextTrainingItem,
        trainerDashboard,
        athleteCockpit,
        sportStats,
        templatePlans,
        selectedTeamMembers,
        selectPlanTrainingType,
        canOpenPlanWizardStep,
        goToPlanWizardStep,
        planWizardCanContinue,
        nextPlanWizardStep,
        previousPlanWizardStep,
        resetPlanForm,
        openModal,
        nextPlanWeek,
        closeModal,
        togglePlanUser,
        setActivityImage,
        setPlanImage,
        setItemImage,
        setEditItemImage,
        submitActivity,
        openLogPage,
        openDraftDelete,
        logStatusLabel,
        applySelectedPlanItem,
        setLogStatus,
        addLogEntry,
        removeLogEntry,
        submitLog,
        submitPlan,
        updatePlan,
        publishPlan,
        deletePlan,
        deleteDraft,
        submitPlanItem,
        updatePlanItem,
        duplicatePlanItem,
        duplicatePlan,
        itemPayload,
        openPlanItem,
        startDragItem,
        dropItemOnDay,
        applyExerciseTemplate,
        applyPlanExerciseTemplate,
        documentPlanItem,
        markPlanItemMissed,
        deletePlanItem,
        formatDate,
        formatWeekday,
        itemStatusLabel,
        itemStatusClass,
        formatTime,
        toLocalDateTime,
        formatDuration,
        formatDistance,
        sportLabel,
        sportIcon,
        sportAccent,
        aiPlanDurationPresets,
        aiPlanSteps,
        aiTrainingMethodGroups,
        cadenceLabels,
        defaultTrainingTypeForSport,
        exerciseLibrary,
        levelLabels,
        loadLabels,
        permissionLabels,
        phaseLabels,
        planTrainingTypes,
        planWizardSteps,
        sports,
        trainingSections,
    }
}
