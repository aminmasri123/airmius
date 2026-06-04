import { computed, reactive, ref } from 'vue'

const sourceValue = (source) => {
    if (typeof source === 'function') return source()
    if (source && typeof source === 'object' && 'value' in source) return source.value

    return source
}

export function useAiTrainingPlanBuilder({
    activeModal,
    activeSport,
    aiCapabilities,
    defaultTrainingTypeForSport,
    onPlanSaved,
    plans,
    sportChoices,
}) {
    const aiPlanStep = ref(0)
    const aiTrainingPlanPreview = ref(null)
    const aiTrainingPlanError = ref('')
    const aiTrainingPlanMessage = ref('')
    const aiTrainingPlanGenerating = ref(false)
    const aiTrainingPlanSaving = ref(false)
    const aiPlanSourcePlan = ref(null)
    const aiProfileMissingFields = ref([])
    const aiProfileCompletionUrl = ref('')
    const aiProfileMissingMessage = ref('')
    const aiProfileEstimateAllowed = ref(false)
    const aiSafetyAccepted = ref(false)

    const resolvedAiCapabilities = computed(() => sourceValue(aiCapabilities) || {})
    const resolvedPlans = computed(() => sourceValue(plans) || [])
    const resolvedSportChoices = computed(() => sourceValue(sportChoices) || [])

    const aiPlanDefaults = () => ({
        title: '',
        goal: '',
        sport_type: activeSport.value === 'all' ? 'laufen' : activeSport.value,
        training_type: defaultTrainingTypeForSport(),
        level: 'intermediate',
        phase: 'build',
        weeks: 4,
        sessions_per_week: 3,
        duration_minutes: 45,
        starts_on: '',
        equipment: '',
        constraints: '',
        preferences: '',
        revision_instruction: '',
        allow_profile_estimate: false,
    })

    const aiPlanForm = reactive(aiPlanDefaults())

    const aiPlanSportChoices = computed(() => resolvedSportChoices.value.filter((sport) => ['laufen', 'gym', 'schwimmen', 'fussball', 'cycling', 'yoga'].includes(sport.key)))
    const aiTrainingPlan = computed(() => resolvedAiCapabilities.value?.training_plan_generation || {})
    const aiTrainingPlanAvailable = computed(() => Boolean(aiTrainingPlan.value.available))
    const aiPlanMaxItems = computed(() => Number(aiTrainingPlan.value.max_items || 156))
    const aiPlanMaxWeeks = computed(() => Number(aiTrainingPlan.value.max_weeks || 26))
    const aiPlanMonthlyLimit = computed(() => aiTrainingPlan.value.monthly_limit)
    const aiPlanMonthlyRemaining = computed(() => aiTrainingPlan.value.monthly_remaining)
    const aiPlanRequestedItems = computed(() => {
        const weeks = Math.max(1, Number(aiPlanForm.weeks || 0))
        const sessions = Math.max(1, Number(aiPlanForm.sessions_per_week || 0))

        return weeks * sessions
    })
    const aiPlanTooLarge = computed(() => aiPlanRequestedItems.value > aiPlanMaxItems.value)
    const aiPlanWeeksTooLong = computed(() => Math.max(1, Number(aiPlanForm.weeks || 0)) > aiPlanMaxWeeks.value)
    const aiPlanCannotGenerate = computed(() => !aiTrainingPlanAvailable.value || aiPlanTooLarge.value || aiPlanWeeksTooLong.value)
    const aiPlanTierLabel = computed(() => aiTrainingPlan.value.tier_label || 'Free')
    const aiPlanLimitLabel = computed(() => {
        if (aiPlanMonthlyLimit.value === null || aiPlanMonthlyLimit.value === undefined) {
            return `Stufe ${aiPlanTierLabel.value}: bis ${aiPlanMaxWeeks.value} Wochen`
        }

        return `Stufe ${aiPlanTierLabel.value}: ${Math.max(0, Number(aiPlanMonthlyRemaining.value ?? 0))}/${aiPlanMonthlyLimit.value} KI-Pläne diesen Monat, bis ${aiPlanMaxWeeks.value} Wochen`
    })
    const aiTrainingProviderLabel = computed(() => {
        const provider = aiTrainingPlan.value.primary_provider || resolvedAiCapabilities.value?.primary_provider || 'ionos'
        const match = (resolvedAiCapabilities.value?.available_providers || []).find((item) => item.key === provider)

        return match?.label || provider
    })
    const aiGeneratedPlans = computed(() => resolvedPlans.value.filter((plan) => plan.settings?.ai_generation))
    const aiGeneratedPlanInsights = computed(() => aiGeneratedPlans.value
        .flatMap((plan) => [
            ...(plan.settings?.ai_generation?.analysis_tips || []).map((text) => ({ plan, text, label: 'Analyse' })),
            ...(plan.settings?.ai_generation?.adjustment_tips || []).map((text) => ({ plan, text, label: 'Anpassung' })),
        ])
        .filter((item) => item.text)
        .slice(0, 6))
    const aiSafetyGate = computed(() => aiTrainingPlanPreview.value?.safety_gate || aiTrainingPlanPreview.value?.quality_check?.safety_gate || null)
    const aiSafetyCanSave = computed(() => Boolean(aiTrainingPlanPreview.value && aiSafetyAccepted.value && (aiSafetyGate.value?.can_save ?? true)))

    const qualityStatusClass = (status) => ({
        ok: 'border-success/30 bg-success/10 text-success',
        warning: 'border-warning/30 bg-warning/10 text-warning',
        danger: 'border-danger/30 bg-danger/10 text-danger',
    }[status] || 'border-border bg-inputBg text-secondary')

    const qualityRiskClass = (risk) => ({
        niedrig: 'border-success/30 bg-success/10 text-success',
        mittel: 'border-warning/30 bg-warning/10 text-warning',
        hoch: 'border-danger/30 bg-danger/10 text-danger',
    }[risk] || 'border-border bg-inputBg text-secondary')

    const resetAiTrainingPlanForm = () => {
        Object.assign(aiPlanForm, aiPlanDefaults())
        aiPlanStep.value = 0
        aiTrainingPlanPreview.value = null
        aiTrainingPlanError.value = ''
        aiTrainingPlanMessage.value = ''
        aiPlanSourcePlan.value = null
        aiProfileMissingFields.value = []
        aiProfileCompletionUrl.value = ''
        aiProfileMissingMessage.value = ''
        aiProfileEstimateAllowed.value = false
        aiSafetyAccepted.value = false
    }

    const selectAiPlanSportType = (sportKey) => {
        aiPlanForm.sport_type = sportKey
        aiPlanForm.training_type = defaultTrainingTypeForSport()
        aiPlanForm.allow_profile_estimate = false
        aiProfileMissingFields.value = []
        aiProfileCompletionUrl.value = ''
        aiProfileMissingMessage.value = ''
        aiProfileEstimateAllowed.value = false
    }

    const setAiPlanDurationPreset = (weeks) => {
        aiPlanForm.weeks = Math.min(weeks, aiPlanMaxWeeks.value)

        if (weeks >= 26 && Number(aiPlanForm.sessions_per_week || 0) > 6) {
            aiPlanForm.sessions_per_week = 6
        }
    }

    const compactPlanForAi = (plan) => {
        if (!plan) return null

        return {
            title: plan.title,
            description: plan.description,
            settings: plan.settings || {},
            items: (plan.items || []).map((item) => ({
                title: item.title,
                sport_type: item.sport_type,
                description: item.description,
                duration_minutes: item.duration_minutes,
                distance_km: item.distance_meters ? Number(item.distance_meters) / 1000 : null,
                intensity: item.intensity,
                todos: item.todos || [],
                metrics: item.metrics || {},
            })),
        }
    }

    const openAiTrainingPlanModal = (plan = null) => {
        resetAiTrainingPlanForm()

        if (plan) {
            aiPlanSourcePlan.value = plan
            aiPlanForm.title = `${plan.title || 'Trainingsplan'} angepasst`
            aiPlanForm.goal = plan.settings?.goal || ''
            aiPlanForm.phase = plan.settings?.phase || 'build'
            aiPlanForm.level = plan.settings?.level || 'intermediate'
            aiPlanForm.weeks = plan.settings?.weeks || 4
            aiPlanForm.sessions_per_week = plan.settings?.weekly_sessions || 3
            aiPlanForm.sport_type = plan.items?.[0]?.sport_type || 'laufen'
            aiPlanForm.training_type = plan.items?.[0]?.metrics?._training_type || plan.items?.[0]?.metrics?.training_type || 'long_run'
            aiPlanForm.starts_on = plan.starts_on || ''
            aiPlanStep.value = 1
        }

        activeModal.value = 'ai-plan'
    }

    const canOpenAiPlanStep = (index) => {
        if (index === 0) return true
        if (index === 1) return Boolean(aiPlanForm.goal?.trim())

        return Boolean(aiTrainingPlanPreview.value)
    }

    const generateAiTrainingPlan = async (revise = false, allowProfileEstimate = false) => {
        if (!aiTrainingPlanAvailable.value) {
            aiTrainingPlanError.value = aiTrainingPlan.value.access_reason || 'KI-Trainingspläne sind für dein aktuelles Kontingent nicht verfügbar.'
            return
        }

        if (!aiPlanForm.goal?.trim()) {
            aiTrainingPlanError.value = 'Bitte gib zuerst ein klares Trainingsziel ein.'
            aiPlanStep.value = 0
            return
        }

        if (revise && !aiPlanForm.revision_instruction?.trim()) {
            aiTrainingPlanError.value = 'Bitte schreibe kurz, was die KI am Plan verändern soll.'
            return
        }

        if (aiPlanWeeksTooLong.value) {
            aiTrainingPlanError.value = `Deine aktuelle Stufe erlaubt KI-Trainingspläne bis ${aiPlanMaxWeeks.value} Wochen. Bitte wähle eine kürzere Dauer oder nutze die nächste Stufe.`
            aiPlanStep.value = 1
            return
        }
        if (aiPlanTooLarge.value) {
            aiTrainingPlanError.value = `Dieser Plan hätte ${aiPlanRequestedItems.value} Einheiten. Bitte reduziere Wochen oder Einheiten pro Woche auf maximal ${aiPlanMaxItems.value} Einheiten.`
            aiPlanStep.value = 1
            return
        }

        aiTrainingPlanGenerating.value = true
        aiTrainingPlanError.value = ''
        aiTrainingPlanMessage.value = ''
        aiSafetyAccepted.value = false
        aiProfileMissingFields.value = []
        aiProfileCompletionUrl.value = ''
        aiProfileMissingMessage.value = ''
        aiProfileEstimateAllowed.value = false

        try {
            const response = await window.axios.post(route('auth.training.ai.plans.preview'), {
                title: aiPlanForm.title,
                goal: aiPlanForm.goal,
                sport_type: aiPlanForm.sport_type,
                training_type: aiPlanForm.training_type,
                level: aiPlanForm.level,
                phase: aiPlanForm.phase,
                weeks: aiPlanForm.weeks,
                sessions_per_week: aiPlanForm.sessions_per_week,
                duration_minutes: aiPlanForm.duration_minutes,
                starts_on: aiPlanForm.starts_on,
                equipment: aiPlanForm.equipment,
                constraints: aiPlanForm.constraints,
                preferences: aiPlanForm.preferences,
                revision_instruction: revise || aiPlanSourcePlan.value ? aiPlanForm.revision_instruction : '',
                current_plan: revise || aiPlanSourcePlan.value ? (aiTrainingPlanPreview.value || compactPlanForAi(aiPlanSourcePlan.value)) : null,
                allow_profile_estimate: Boolean(allowProfileEstimate || aiPlanForm.allow_profile_estimate),
            })

            aiTrainingPlanPreview.value = response.data?.plan || null
            aiTrainingPlanMessage.value = response.data?.message || 'KI-Vorschlag erstellt.'
            aiProfileMissingFields.value = []
            aiProfileCompletionUrl.value = ''
            aiProfileMissingMessage.value = ''
            aiProfileEstimateAllowed.value = false
            aiPlanForm.allow_profile_estimate = false
            aiPlanStep.value = 2
        } catch (error) {
            const responseData = error.response?.data || {}
            const missingFields = responseData.missing_profile_fields || []

            if (missingFields.length) {
                aiTrainingPlanError.value = ''
                aiTrainingPlanMessage.value = ''
                aiProfileMissingFields.value = missingFields
                aiProfileCompletionUrl.value = responseData.profile_completion_url || route('auth.settings', { tab: 'sport-profile' })
                aiProfileMissingMessage.value = responseData.message || 'Für einen zuverlässigen Plan fehlen noch Sportprofil-Daten.'
                aiProfileEstimateAllowed.value = Boolean(responseData.profile_estimate_allowed)
                aiPlanForm.allow_profile_estimate = false
                aiPlanStep.value = 1
                return
            }

            aiTrainingPlanError.value = responseData.message || 'KI-Trainingsplan konnte nicht erstellt werden.'
        } finally {
            aiTrainingPlanGenerating.value = false
        }
    }

    const continueAiTrainingPlan = () => {
        if (aiPlanStep.value === 0) {
            aiPlanStep.value = 1
            return
        }

        generateAiTrainingPlan(false)
    }

    const generateAiTrainingPlanWithProfileEstimates = () => {
        aiPlanForm.allow_profile_estimate = true
        aiProfileMissingFields.value = []
        aiProfileCompletionUrl.value = ''
        aiProfileMissingMessage.value = ''
        aiProfileEstimateAllowed.value = false
        generateAiTrainingPlan(false, true)
    }

    const saveAiTrainingPlan = async () => {
        if (!aiTrainingPlanPreview.value) return

        aiTrainingPlanSaving.value = true
        aiTrainingPlanError.value = ''

        try {
            await window.axios.post(route('auth.training.ai.plans.store'), {
                plan: aiTrainingPlanPreview.value,
                starts_on: aiPlanForm.starts_on,
                status: 'published',
                share_permission: 'read',
                accepted_ai_safety: aiSafetyAccepted.value,
            })

            onPlanSaved?.()
        } catch (error) {
            aiTrainingPlanError.value = error.response?.data?.message || 'KI-Plan konnte nicht gespeichert werden.'
        } finally {
            aiTrainingPlanSaving.value = false
        }
    }

    return {
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
        aiSafetyAccepted,
        aiSafetyCanSave,
        aiSafetyGate,
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
    }
}

