import { router, useForm } from '@inertiajs/vue3'
import { computed, ref } from 'vue'
import { useI18n } from 'vue-i18n'
import { useAiTrainingPlanBuilder } from '@/composables/useAiTrainingPlanBuilder'
import {
    aiPlanDurationPresets,
    aiPlanSteps,
    aiTrainingMethodGroups,
    cadenceLabels,
    defaultTrainingTypeForSport,
    equipmentPresets,
    exerciseLibrary,
    levelLabels,
    loadLabels,
    permissionLabels,
    phaseLabels,
    planTrainingTypes,
    planWizardSteps,
    sports,
    structuredMetricKeys,
    trainingGoals,
    trainingSections,
    trainingSessionBlocks,
} from '@/support/trainingOptions'

export function useTrainingWorkspace(props) {
    const { t, locale } = useI18n()
    const tx = (key, params = {}) => t(key, params)

    const activeSport = ref('all')
    const activeTrainingSection = ref('overview')
    const activeModal = ref(null)
    const deleteText = ref('')
    const selectedPlan = ref(null)
    const selectedItem = ref(null)
    const selectedDraft = ref(null)
    const activityImageInput = ref(null)
    const planImageInput = ref(null)
    const itemImageInput = ref(null)
    const editItemImageInput = ref(null)
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
        sport_route_id: '',
        sport_route_track_id: '',
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
        target_type: 'self',
        team_mode: 'all',
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
        item_session_block: 'main',
        item_goal: 'Technik',
        item_level: 'intermediate',
        item_equipment: '',
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
        target_type: 'self',
        team_mode: 'all',
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
        session_block: 'main',
        goal: 'Technik',
        level: 'intermediate',
        equipment: '',
        todos: '',
        image: null,
        video_url: '',
        metrics: {},
        sport_route_id: '',
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
        session_block: 'main',
        goal: 'Technik',
        level: 'intermediate',
        equipment: '',
        todos: '',
        image: null,
        video_url: '',
        metrics: {},
        sport_route_id: '',
    })

    const missedForm = useForm({
        user_id: '',
        reason: 'keine_zeit',
        notes: '',
    })

    const localizedValue = (key, fallback) => {
        const translated = tx(key)

        return translated === key ? fallback : translated
    }

    const localizedSports = computed(() => sports.map((sport) => ({
        ...sport,
        label: localizedValue(`training_workspace.sports.${sport.key}`, sport.label),
        metrics: (sport.metrics || []).map((metric) => localizedValue(`training_workspace.metrics.${metric}`, metric)),
    })))
    const localizedPlanTrainingTypes = computed(() => planTrainingTypes.map((type) => ({
        ...type,
        label: localizedValue(`training_workspace.plan_types.${type.key}`, type.label),
    })))
    const localizedTrainingSections = computed(() => trainingSections.map((section) => ({
        ...section,
        label: tx(`training_workspace.sections.${section.key}.label`),
        hint: tx(`training_workspace.sections.${section.key}.hint`),
    })))
    const localizedPlanWizardSteps = computed(() => planWizardSteps.map((step, index) => ({
        ...step,
        label: tx(`training_workspace.plan_steps.${index}.label`),
        hint: tx(`training_workspace.plan_steps.${index}.hint`),
    })))
    const localizedAiPlanSteps = computed(() => aiPlanSteps.map((step, index) => ({
        ...step,
        label: tx(`training_workspace.ai_steps.${index}.label`),
        hint: tx(`training_workspace.ai_steps.${index}.hint`),
    })))
    const localizedAiPlanDurationPresets = computed(() => aiPlanDurationPresets.map((preset, index) => ({
        ...preset,
        label: tx(`training_workspace.duration_presets.${index}.label`),
        hint: tx(`training_workspace.duration_presets.${index}.hint`),
    })))
    const localizedCadenceLabels = computed(() => Object.fromEntries(Object.keys(cadenceLabels).map((key) => [key, tx(`training_workspace.cadence.${key}`)])))
    const localizedPhaseLabels = computed(() => Object.fromEntries(Object.keys(phaseLabels).map((key) => [key, tx(`training_workspace.phase.${key}`)])))
    const localizedLevelLabels = computed(() => Object.fromEntries(Object.keys(levelLabels).map((key) => [key, tx(`training_workspace.level.${key}`)])))
    const localizedLoadLabels = computed(() => Object.fromEntries(Object.keys(loadLabels).map((key) => [key, tx(`training_workspace.load.${key}`)])))
    const localizedPermissionLabels = computed(() => Object.fromEntries(Object.keys(permissionLabels).map((key) => [key, tx(`training_workspace.permission.${key}`)])))

    const selectedSport = computed(() => localizedSports.value.find((sport) => sport.key === activeSport.value) || localizedSports.value[0])
    const planSport = computed(() => localizedSports.value.find((sport) => sport.key === planForm.item_sport_type) || localizedSports.value[1])
    const itemSport = computed(() => localizedSports.value.find((sport) => sport.key === itemForm.sport_type) || localizedSports.value[1])
    const editItemSport = computed(() => localizedSports.value.find((sport) => sport.key === editItemForm.sport_type) || localizedSports.value[1])
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

        return [...localizedSports.value.filter((sport) => sport.key !== 'all'), ...customSports]
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
        aiSafetyAccepted,
        aiSafetyCanSave,
        aiSafetyGate,
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
            router.reload({
                only: ['plans', 'aiCapabilities'],
                preserveScroll: true,
                preserveState: true,
            })
        },
        plans: () => props.plans,
        sportChoices,
    })

    const plannedLogItems = computed(() => props.plans
        .flatMap((plan) => (plan.items || []).map((item) => ({ ...item, plan })))
        .sort((a, b) => new Date(a.scheduled_at || 0) - new Date(b.scheduled_at || 0)))

    const athleteOptions = computed(() => [
        { id: '', name: tx('training_workspace.log_create.self'), email: '' },
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
        return (team?.users || []).filter((member) => {
            if (!member.team_role) return true
            return ['Player', 'player', 'athlete'].includes(member.team_role)
        })
    })

    const selectedEditTeamMembers = computed(() => {
        const team = props.teams.find((item) => Number(item.id) === Number(editForm.team_id))
        return (team?.users || []).filter((member) => {
            if (!member.team_role) return true
            return ['Player', 'player', 'athlete'].includes(member.team_role)
        })
    })

    const privatePeople = computed(() => props.privatePeople || props.people || [])

    const setPlanTargetType = (targetType, form = planForm) => {
        form.target_type = targetType
        form.team_id = ''
        form.team_mode = targetType === 'team' ? 'all' : null
        form.user_ids = []
    }

    const setPlanTeamMode = (mode, form = planForm) => {
        form.team_mode = mode
        form.user_ids = []
    }

    const selectPlanTrainingType = (key) => {
        const type = planTrainingTypes.find((item) => item.key === key) || planTrainingTypes[planTrainingTypes.length - 1]
        planForm.item_training_type = type.key
        planForm.item_sport_type = type.sport_type
        planForm.item_metrics = {
            ...planForm.item_metrics,
            _training_type: type.key,
        }
    }

    const structuredMetricsFor = ({ sessionBlock, goal, level, equipment }) => Object.fromEntries(Object.entries({
        Abschnitt: trainingSessionBlocks.find((block) => block.key === sessionBlock)?.label || sessionBlock,
        Trainingsziel: goal,
        Niveau: levelLabels[level] || level,
        Equipment: equipment,
    }).filter(([, value]) => value !== null && value !== undefined && String(value).trim() !== ''))

    const enrichItemFormMetrics = (form) => {
        form.metrics = {
            ...form.metrics,
            ...structuredMetricsFor({
                sessionBlock: form.session_block,
                goal: form.goal,
                level: form.level,
                equipment: form.equipment,
            }),
        }
    }

    const customMetrics = (metrics = {}) => Object.fromEntries(
        Object.entries(metrics || {}).filter(([key]) => !structuredMetricKeys.includes(key)),
    )

    const resolveSessionBlockKey = (value) => {
        if (!value) return 'main'

        return trainingSessionBlocks.find((block) => block.key === value || block.label === value)?.key || 'main'
    }

    const planAudienceCanContinue = computed(() => {
        if (planForm.target_type === 'self') return true
        if (planForm.target_type === 'private') return planForm.user_ids.length > 0
        return Boolean(planForm.team_id)
            && (planForm.team_mode === 'all' || planForm.user_ids.length > 0)
    })

    const editPlanAudienceCanSubmit = computed(() => {
        if (editForm.target_type === 'self') return true
        if (editForm.target_type === 'private') return editForm.user_ids.length > 0
        return Boolean(editForm.team_id)
            && (editForm.team_mode === 'all' || editForm.user_ids.length > 0)
    })

    const canOpenPlanWizardStep = (index) => {
        if (index === 0) return true
        if (!planForm.title?.trim()) return false
        if (index >= 3) return planAudienceCanContinue.value
        return true
    }
    const goToPlanWizardStep = (index) => {
        if (!canOpenPlanWizardStep(index)) return
        planWizardStep.value = index
    }

    const planWizardCanContinue = computed(() => {
        if (planWizardStep.value === 0) return Boolean(planForm.title?.trim())
        if (planWizardStep.value === 2) return planAudienceCanContinue.value
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
        planForm.target_type = 'self'
        planForm.team_mode = 'all'
        planForm.team_id = ''
        planForm.user_ids = []
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
        planForm.item_session_block = 'main'
        planForm.item_goal = 'Technik'
        planForm.item_level = planForm.level || 'intermediate'
        planForm.item_equipment = ''
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
            logForm.sport_route_id = ''
            logForm.sport_route_track_id = ''
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
            editForm.target_type = plan.target_type || (plan.team?.id ? 'team' : ((plan.assignments || []).some((assignment) => assignment.user) ? 'private' : 'self'))
            editForm.team_mode = plan.team_mode || (editForm.target_type === 'team' ? ((plan.assignments || []).some((assignment) => assignment.team) ? 'all' : 'individual') : null)
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
            itemForm.session_block = 'main'
            itemForm.goal = plan.settings?.goal || 'Technik'
            itemForm.level = plan.settings?.level || 'intermediate'
            itemForm.equipment = ''
            itemForm.metrics = {}
            itemForm.sport_route_id = ''
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
            editItemForm.session_block = resolveSessionBlockKey(item.metrics?.Abschnitt)
            editItemForm.goal = item.metrics?.Trainingsziel || plan.settings?.goal || 'Technik'
            editItemForm.level = Object.entries(levelLabels).find(([, label]) => label === item.metrics?.Niveau)?.[0] || item.metrics?.Niveau || plan.settings?.level || 'intermediate'
            editItemForm.equipment = item.metrics?.Equipment || ''
            editItemForm.sport_route_id = item.sport_route_id || ''
            editItemForm.todos = (item.todos || []).join('\n')
            editItemForm.image = null
            editItemForm.video_url = item.video_url || ''
            editItemForm.metrics = customMetrics(item.metrics)
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
        draft: tx('training_log.status.draft'),
        planned: tx('training_log.status.planned'),
        in_progress: tx('training_log.status.in_progress'),
        completed: tx('training_log.status.completed'),
        missed: tx('training_workspace.status.missed'),
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
        logForm.sport_route_id = item.sport_route_id || ''
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
        if (!planAudienceCanContinue.value) {
            planWizardStep.value = 2
            return
        }

        planForm.item_metrics = {
            ...planForm.item_metrics,
            ...structuredMetricsFor({
                sessionBlock: planForm.item_session_block,
                goal: planForm.item_goal,
                level: planForm.item_level,
                equipment: planForm.item_equipment,
            }),
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
        if (!editPlanAudienceCanSubmit.value) return
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
        enrichItemFormMetrics(itemForm)
        itemForm.post(route('auth.training.plans.items.store', selectedPlan.value.id), {
            preserveScroll: true,
            forceFormData: true,
            onSuccess: closeModal,
        })
    }

    const updatePlanItem = () => {
        if (!selectedPlan.value || !selectedItem.value) return
        enrichItemFormMetrics(editItemForm)

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
        sport_route_id: item.sport_route_id || '',
        todos: (item.todos || []).join('\n'),
        video_url: item.video_url || '',
        metrics: Object.fromEntries(Object.entries(item.metrics || {}).filter(([key]) => !['Woche', 'Belastung', 'Fokus', '_training_type', 'training_type', 'Trainingstyp'].includes(key))),
        _method: 'put',
    })

    const openPlanItem = (item) => {
        router.visit(route('auth.training.plans.items.show', [item.plan.id, item.id]))
    }

    const startDragItem = (item) => {
        if (!item.plan?.can_write) return

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

    const movePlanItemByDays = (item, dayOffset) => {
        const scheduledAt = item.scheduled_at ? new Date(item.scheduled_at) : new Date()

        if (Number.isNaN(scheduledAt.getTime())) return

        scheduledAt.setDate(scheduledAt.getDate() + dayOffset)

        router.post(
            route('auth.training.plans.items.update', [item.plan.id, item.id]),
            itemPayload(item, toLocalDateTime(scheduledAt)),
            { preserveScroll: true },
        )
    }

    const applyExerciseTemplate = (template, form = itemForm) => {
        form.sport_type = template.sport_type
        form.title = template.title
        form.focus = template.focus
        form.duration_minutes = template.duration_minutes
        form.todos = template.todos
        form.session_block = template.session_block || form.session_block || 'main'
        form.goal = template.goal || form.goal || template.focus || 'Technik'
        form.level = template.level || form.level || 'intermediate'
        form.equipment = template.equipment || form.equipment || ''
        form.metrics = { ...template.metrics }
    }

    const applyPlanExerciseTemplate = (template) => {
        planForm.item_training_type = template.training_type || planForm.item_training_type || 'generic'
        planForm.item_sport_type = template.sport_type
        planForm.item_title = template.title
        planForm.item_focus = template.focus
        planForm.item_duration_minutes = template.duration_minutes
        planForm.item_todos = template.todos
        planForm.item_session_block = template.session_block || planForm.item_session_block || 'main'
        planForm.item_goal = template.goal || planForm.item_goal || template.focus || 'Technik'
        planForm.item_level = template.level || planForm.item_level || 'intermediate'
        planForm.item_equipment = template.equipment || planForm.item_equipment || ''
        planForm.item_metrics = {
            ...template.metrics,
            ...structuredMetricsFor({
                sessionBlock: planForm.item_session_block,
                goal: planForm.item_goal,
                level: planForm.item_level,
                equipment: planForm.item_equipment,
            }),
            _training_type: planForm.item_training_type,
        }
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

    const localeCode = computed(() => locale.value === 'ar' ? 'ar-EG' : (locale.value === 'fr' ? 'fr-FR' : (locale.value === 'en' ? 'en-US' : 'de-DE')))

    const formatDate = (value) => {
        if (!value) return '-'
        return new Intl.DateTimeFormat(localeCode.value, { day: '2-digit', month: '2-digit', year: 'numeric' }).format(new Date(value))
    }

    const formatWeekday = (value) => new Intl.DateTimeFormat(localeCode.value, { weekday: 'short', day: '2-digit', month: '2-digit' }).format(new Date(value))

    const itemStatusLabel = (item) => {
        if (item.log_statuses?.some((log) => log.status === 'completed')) return tx('training_workspace.item_status.completed')
        if (item.log_statuses?.some((log) => log.status === 'missed')) return tx('training_workspace.item_status.missed')
        if (item.scheduled_at && new Date(item.scheduled_at) < new Date()) return tx('training_workspace.item_status.due')

        return tx('training_workspace.item_status.planned')
    }

    const itemStatusClass = (item) => {
        if (item.log_statuses?.some((log) => log.status === 'completed')) return 'bg-success/10 text-success'
        if (item.log_statuses?.some((log) => log.status === 'missed')) return 'bg-danger/10 text-danger'
        if (item.scheduled_at && new Date(item.scheduled_at) < new Date()) return 'bg-warning/10 text-warning'

        return 'bg-muted text-secondary'
    }

    const formatTime = (value) => {
        if (!value) return ''
        return new Intl.DateTimeFormat(localeCode.value, { hour: '2-digit', minute: '2-digit' }).format(new Date(value))
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
        return rest ? `${hours} ${tx('training_workspace.units.hours')} ${rest} ${tx('training_workspace.units.minutes')}` : `${hours} ${tx('training_workspace.units.hours')}`
    }

    const formatDistance = (meters) => {
        if (!meters) return '-'
        return `${(Number(meters) / 1000).toLocaleString(localeCode.value, { minimumFractionDigits: 2, maximumFractionDigits: 2 })} km`
    }

    const sportLabel = (key) => sportChoices.value.find((sport) => sport.key === key)?.label || key || tx('training_workspace.fallback_training')
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
        itemImageInput,
        editItemImageInput,
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
        aiSafetyAccepted,
        aiSafetyCanSave,
        aiSafetyGate,
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
        selectedEditTeamMembers,
        privatePeople,
        setPlanTargetType,
        setPlanTeamMode,
        planAudienceCanContinue,
        editPlanAudienceCanSubmit,
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
        movePlanItemByDays,
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
        aiPlanDurationPresets: localizedAiPlanDurationPresets,
        aiPlanSteps: localizedAiPlanSteps,
        aiTrainingMethodGroups,
        cadenceLabels: localizedCadenceLabels,
        defaultTrainingTypeForSport,
        equipmentPresets,
        exerciseLibrary,
        levelLabels: localizedLevelLabels,
        loadLabels: localizedLoadLabels,
        permissionLabels: localizedPermissionLabels,
        phaseLabels: localizedPhaseLabels,
        planTrainingTypes: localizedPlanTrainingTypes,
        planWizardSteps: localizedPlanWizardSteps,
        structuredMetricKeys,
        sports: localizedSports,
        trainingGoals,
        trainingSections: localizedTrainingSections,
        trainingSessionBlocks,
    }
}
