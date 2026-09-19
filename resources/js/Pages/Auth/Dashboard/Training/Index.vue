<script setup>
import AppLayout from "@/Components/Auth/Layouts/AppLayout.vue"
import TrainingModalStack from "@/Components/Training/TrainingModalStack.vue"
import { useTrainingWorkspace } from "@/composables/useTrainingWorkspace"
import { Head, Link } from "@inertiajs/vue3"
import { useI18n } from 'vue-i18n'
import trainingWorkspaceCopy from './trainingWorkspaceCopy.json'

defineOptions({ layout: AppLayout })

const { t, locale } = useI18n()
const tx = (key, fallback = key, params = {}) => {
    if (fallback && typeof fallback === 'object') {
        params = fallback
        fallback = key
    }
    const translated = t(key, params)
    return translated === key ? fallback : translated
}
const workspaceLocale = () => {
    const candidate = String(locale.value || 'de').toLowerCase().split(/[-_]/)[0]
    return Object.hasOwn(trainingWorkspaceCopy, candidate) ? candidate : 'de'
}
const workspaceMessage = (catalog, key) => key
    .split('.')
    .reduce((value, segment) => value && value[segment], catalog)
const wc = (key, params = {}) => {
    const catalog = trainingWorkspaceCopy[workspaceLocale()]
    const message = workspaceMessage(catalog, key) ?? workspaceMessage(trainingWorkspaceCopy.de, key) ?? key

    return Object.entries(params).reduce(
        (text, [name, value]) => text.split(`{${name}}`).join(String(value ?? '')),
        String(message),
    )
}

const props = defineProps({
    plans: { type: Array, default: () => [] },
    activeDraftLog: { type: Object, default: null },
    activities: { type: Array, default: () => [] },
    logs: { type: Array, default: () => [] },
    manageableAthletes: { type: Array, default: () => [] },
    sportCatalog: { type: Array, default: () => [] },
    teams: { type: Array, default: () => [] },
    people: { type: Array, default: () => [] },
    privatePeople: { type: Array, default: () => [] },
    aiCapabilities: { type: Object, default: () => ({}) },
    canManageTrainingPlans: { type: Boolean, default: false },
    canCreatePersonalTrainingPlans: { type: Boolean, default: false },
    sportRoutes: { type: Array, default: () => [] },
    sportRouteTracks: { type: Array, default: () => [] },
})

const {
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
    structuredMetricKeys,
    sports,
    trainingGoals,
    trainingSections,
    trainingSessionBlocks,
} = useTrainingWorkspace(props)

const setActivityImageElement = (element) => {
    activityImageInput.value = element
}

const setPlanImageElement = (element) => {
    planImageInput.value = element
}

const updateAiPlanStep = (step) => {
    aiPlanStep.value = step
}

const updateAiSafetyAccepted = (accepted) => {
    aiSafetyAccepted.value = accepted
}

const updateDeleteText = (value) => {
    deleteText.value = value
}
</script>

<template>
    <Head :title="tx('training_workspace.page_title')" />

    <div class="space-y-3 pb-24 sm:space-y-4 sm:pb-0">
        <section class="rounded-2xl border border-border bg-card p-3 sm:p-5">
            <div class="grid gap-3 lg:grid-cols-[minmax(0,1fr)_340px] lg:gap-5">
                <div class="space-y-2.5 sm:space-y-3">
                    <div class="flex items-center justify-between gap-2">
                        <span class="rounded-full border border-air-blue/40 bg-air-blue/10 px-3 py-1 text-[11px] font-semibold uppercase tracking-wide text-air-blue">
                            {{ tx('training_workspace.eyebrow') }}
                        </span>
                        <span class="hidden rounded-full border border-border px-3 py-1 text-xs font-semibold text-secondary sm:inline-flex">
                            {{ tx('training_workspace.tagline') }}
                        </span>
                    </div>
                    <h1 class="max-w-3xl text-lg font-semibold leading-tight text-primary sm:text-3xl">
                        {{ tx('training_workspace.title') }}
                    </h1>
                    <div
                        class="rounded-xl border px-3 py-2.5 sm:rounded-2xl sm:p-3"
                        :class="nextTrainingItem ? 'border-air-blue/35 bg-air-blue/10' : 'border-border bg-inputBg/50'"
                    >
                        <div v-if="nextTrainingItem" class="flex items-center gap-3">
                            <span class="flex h-9 w-9 shrink-0 items-center justify-center rounded-xl text-white sm:h-11 sm:w-11 sm:rounded-2xl" :class="sportAccent(nextTrainingItem.sport_type)">
                                <i :class="sportIcon(nextTrainingItem.sport_type)" class="text-lg sm:text-xl"></i>
                            </span>
                            <div class="min-w-0 flex-1">
                                <p class="text-[11px] font-semibold uppercase tracking-wide text-air-blue">{{ tx('training_workspace.next_training') }}</p>
                                <p class="truncate text-sm font-semibold text-primary">{{ nextTrainingItem.title }}</p>
                                <p class="truncate text-xs text-secondary">{{ nextTrainingItem.plan.title }} · {{ formatDate(nextTrainingItem.scheduled_at) }} {{ formatTime(nextTrainingItem.scheduled_at) }}</p>
                            </div>
                            <button type="button" class="hidden rounded-xl border border-success/40 px-3 py-2 text-xs font-semibold text-success hover:bg-success/10 sm:inline-flex" @click="documentPlanItem(nextTrainingItem)">
                                {{ tx('training_workspace.actions.start') }}
                            </button>
                        </div>
                        <div v-else class="flex items-center gap-3">
                            <span class="flex h-9 w-9 shrink-0 items-center justify-center rounded-xl bg-air-blue/15 text-air-blue sm:h-11 sm:w-11 sm:rounded-2xl">
                                <i class="las la-calendar-plus text-lg sm:text-xl"></i>
                            </span>
                            <div class="min-w-0">
                                <p class="text-sm font-semibold text-primary">{{ tx('training_workspace.empty_next') }}</p>
                                <p class="text-xs text-secondary sm:hidden">{{ tx('training_workspace.empty_next_mobile') }}</p>
                                <p class="hidden text-xs text-secondary sm:block">{{ tx('training_workspace.empty_next_desktop') }}</p>
                            </div>
                        </div>
                    </div>
                    <div class="hidden grid-cols-2 gap-2 sm:flex sm:flex-wrap">
                        <button v-if="canCreatePersonalTrainingPlans" type="button" class="inline-flex min-h-10 items-center justify-center gap-2 rounded-xl bg-buttonPrimary px-3 py-2 text-sm font-semibold text-buttonTextPrimary sm:min-h-11 sm:px-4" @click="openModal('plan')">
                            <i class="las la-plus-circle text-lg"></i>
                            {{ tx('training_workspace.actions.create_plan') }}
                        </button>
                        <button v-if="canManageTrainingPlans" type="button" class="inline-flex min-h-10 items-center justify-center gap-2 rounded-xl border border-air-blue/50 bg-air-blue/10 px-3 py-2 text-sm font-semibold text-air-blue hover:bg-air-blue/15 disabled:cursor-not-allowed disabled:opacity-50 sm:min-h-11 sm:px-4" :disabled="!aiTrainingPlanAvailable" @click="openAiTrainingPlanModal()">
                            <i class="las la-magic text-lg"></i>
                            {{ tx('training_workspace.actions.ai_plan') }}
                        </button>
                        <button type="button" class="inline-flex min-h-10 items-center justify-center gap-2 rounded-xl border border-border px-3 py-2 text-sm font-semibold text-primary hover:bg-muted sm:min-h-11 sm:px-4" @click="openLogPage">
                            <i class="las la-pen-alt text-lg"></i>
                            {{ tx('training_workspace.actions.document') }}
                        </button>
                        <span v-if="!canCreatePersonalTrainingPlans" class="col-span-2 text-xs text-secondary">
                            {{ wc('management_note') }}
                        </span>
                    </div>
                </div>
                <div class="rounded-xl border border-border bg-inputBg/40 p-2 lg:rounded-2xl lg:bg-muted/30 lg:p-2.5">
                    <div class="grid grid-cols-4 gap-1.5 lg:grid-cols-2 lg:gap-2">
                        <div class="rounded-lg border border-border bg-card p-2 sm:rounded-xl sm:p-2.5">
                            <p class="text-base font-semibold leading-none text-primary sm:text-xl">{{ plans.length }}</p>
                            <p class="text-[11px] text-secondary">{{ tx('training_workspace.stats.plans') }}</p>
                        </div>
                        <div class="rounded-lg border border-border bg-card p-2 sm:rounded-xl sm:p-2.5">
                            <p class="text-base font-semibold leading-none text-primary sm:text-xl">{{ visibleLogs.length }}</p>
                            <p class="text-[11px] text-secondary">{{ tx('training_workspace.stats.logs') }}</p>
                        </div>
                        <div class="rounded-lg border border-border bg-card p-2 sm:rounded-xl sm:p-2.5">
                            <p class="text-base font-semibold leading-none text-primary sm:text-xl">{{ completedThisWeekCount }}</p>
                            <p class="text-[11px] text-secondary">{{ tx('training_workspace.stats.completed') }}</p>
                        </div>
                        <div class="rounded-lg border border-border bg-card p-2 sm:rounded-xl sm:p-2.5">
                            <p class="text-base font-semibold leading-none text-primary sm:text-xl">{{ teams.length }}</p>
                            <p class="text-[11px] text-secondary">{{ tx('training_workspace.stats.teams') }}</p>
                        </div>
                    </div>
                    <div class="mt-3 hidden rounded-xl border border-border bg-card p-3 lg:block">
                        <p class="text-xs font-semibold uppercase tracking-wide text-secondary">{{ tx('training_workspace.next_training') }}</p>
                        <div v-if="upcomingItems.length" class="mt-2 space-y-2">
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
                        <p v-else class="mt-2 text-sm text-secondary">{{ tx('training_workspace.no_appointment') }}</p>
                    </div>
                </div>
            </div>
        </section>

        <div
            v-if="$page.props.flash?.success || $page.props.flash?.error"
            class="rounded-xl border px-4 py-3 text-sm font-semibold"
            :class="$page.props.flash?.success ? 'border-success/40 bg-success/10 text-success' : 'border-danger/40 bg-danger/10 text-danger'"
            :role="$page.props.flash?.error ? 'alert' : 'status'"
            :aria-live="$page.props.flash?.error ? 'assertive' : 'polite'"
        >
            {{ $page.props.flash?.success || $page.props.flash?.error }}
        </div>

        <section v-if="activeDraftLog" class="rounded-2xl border border-air-blue/40 bg-air-blue/10 p-4">
            <div class="flex flex-col gap-4 lg:flex-row lg:items-center lg:justify-between">
                <div class="flex min-w-0 gap-3">
                    <span class="flex h-12 w-12 shrink-0 items-center justify-center rounded-2xl text-white" :class="sportAccent(activeDraftLog.sport_type)">
                        <i :class="sportIcon(activeDraftLog.sport_type)" class="text-2xl"></i>
                    </span>
                    <div class="min-w-0">
                        <div class="flex flex-wrap items-center gap-2">
                            <p class="text-xs font-semibold uppercase tracking-wide text-air-blue">{{ wc('draft.continue') }}</p>
                            <span class="rounded-full border border-success/40 bg-success/10 px-2 py-0.5 text-[11px] font-semibold text-success">
                                {{ wc('draft.autosaved') }}
                            </span>
                        </div>
                        <h2 class="mt-1 truncate text-lg font-semibold text-primary">{{ activeDraftLog.title || wc('draft.title') }}</h2>
                        <p class="mt-1 text-sm text-secondary">
                            {{ wc('draft.meta', { sport: sportLabel(activeDraftLog.sport_type), date: formatDate(activeDraftLog.updated_at), time: formatTime(activeDraftLog.updated_at), count: activeDraftLog.entries?.length || 0 }) }}
                        </p>
                    </div>
                </div>
                <div class="flex flex-col gap-2 sm:flex-row">
                    <button type="button" class="rounded-xl bg-buttonPrimary px-4 py-2 text-sm font-semibold text-buttonTextPrimary" @click="openLogPage">
                        {{ wc('draft.resume') }}
                    </button>
                    <button type="button" class="rounded-xl border border-danger/40 px-4 py-2 text-sm font-semibold text-danger hover:bg-danger/10" @click="openDraftDelete">
                        {{ wc('draft.discard') }}
                    </button>
                </div>
            </div>
        </section>

        <div role="tablist" class="flex gap-1.5 overflow-x-auto rounded-2xl border border-border bg-card p-1.5 md:grid md:grid-cols-5 md:gap-2 md:overflow-visible md:p-2" :aria-label="wc('navigation.sections')">
            <button
                v-for="section in trainingSections"
                :key="section.key"
                :id="`training-section-tab-${section.key}`"
                type="button"
                role="tab"
                :class="[
                    'flex min-w-[76px] shrink-0 items-center justify-center gap-1.5 rounded-xl border px-2 py-2 text-center transition md:min-w-0 md:justify-start md:gap-3 md:px-3 md:py-3 md:text-start',
                    activeTrainingSection === section.key
                        ? 'border-air-blue bg-air-blue/15 text-primary shadow-lg shadow-air-blue/10'
                        : 'border-transparent text-secondary hover:border-border hover:bg-inputBg'
                ]"
                :aria-selected="activeTrainingSection === section.key"
                @click="activeTrainingSection = section.key"
            >
                <i :class="[section.icon, 'text-lg md:text-xl']"></i>
                <span class="min-w-0">
                    <span class="block text-xs font-semibold md:text-sm">{{ section.label }}</span>
                    <span class="hidden truncate text-xs opacity-80 sm:block">{{ section.hint }}</span>
                </span>
            </button>
        </div>

        <section v-if="activeTrainingSection === 'plans'" role="tabpanel" aria-labelledby="training-section-tab-plans" class="rounded-2xl border border-border bg-card p-3">
            <div class="flex gap-2 overflow-x-auto pb-1" role="group" :aria-label="wc('navigation.sports')">
                <button
                    v-for="sport in sports"
                    :key="sport.key"
                    type="button"
                    class="flex shrink-0 items-center gap-2 rounded-xl border px-3 py-2 text-sm font-semibold transition"
                    :class="activeSport === sport.key ? 'border-air-blue bg-air-blue/10 text-primary' : 'border-border text-secondary hover:bg-muted'"
                    :aria-pressed="activeSport === sport.key"
                    @click="activeSport = sport.key"
                >
                    <span class="flex h-8 w-8 items-center justify-center rounded-lg text-white" :class="sport.accent">
                        <i :class="sport.icon"></i>
                    </span>
                    {{ sport.label }}
                </button>
            </div>
        </section>

        <section v-if="activeTrainingSection === 'overview'" role="tabpanel" aria-labelledby="training-section-tab-overview" class="grid gap-3 xl:grid-cols-[minmax(0,1fr),360px]">
            <div v-if="upcomingItems.length" class="rounded-2xl border border-border bg-card p-3 sm:p-4">
                <div class="flex flex-wrap items-center justify-between gap-3">
                    <div>
                        <p class="text-xs font-semibold uppercase tracking-wide text-air-blue">{{ wc('overview.eyebrow') }}</p>
                        <h2 class="mt-1 text-lg font-semibold text-primary sm:text-xl">{{ wc('overview.title') }}</h2>
                    </div>
                    <button type="button" class="rounded-xl bg-buttonPrimary px-3 py-2 text-sm font-semibold text-buttonTextPrimary sm:px-4" @click="activeTrainingSection = 'plans'">
                        {{ wc('overview.to_plans') }}
                    </button>
                </div>
                <div class="mt-3 grid gap-2.5 md:grid-cols-2">
                    <article v-for="item in upcomingItems.slice(0, 4)" :key="`${item.plan.id}-${item.id}`" class="rounded-xl border border-border bg-inputBg/40 p-3">
                        <div class="flex items-start gap-3">
                            <span class="flex h-11 w-11 shrink-0 items-center justify-center rounded-xl text-white" :class="sportAccent(item.sport_type)">
                                <i :class="sportIcon(item.sport_type)" class="text-xl"></i>
                            </span>
                            <div class="min-w-0 flex-1">
                                <p class="truncate text-sm font-semibold text-primary">{{ item.title }}</p>
                                <p class="mt-1 text-xs text-secondary">{{ item.plan.title }} &middot; {{ formatDate(item.scheduled_at) }} {{ formatTime(item.scheduled_at) }}</p>
                                <p v-if="item.sport_route" class="mt-1 truncate text-xs font-semibold text-air-blue">
                                    <i class="las la-route me-1"></i>{{ item.sport_route.title }}
                                </p>
                                <div class="mt-3 flex flex-wrap gap-2">
                                    <button type="button" class="rounded-lg border border-success/40 px-3 py-1.5 text-xs font-semibold text-success hover:bg-success/10" @click="documentPlanItem(item)">
                                        {{ wc('actions.document') }}
                                    </button>
                                    <button type="button" class="rounded-lg border border-border px-3 py-1.5 text-xs font-semibold text-primary hover:bg-muted" @click="openPlanItem(item)">
                                        {{ wc('actions.details') }}
                                    </button>
                                </div>
                            </div>
                        </div>
                    </article>
                </div>
            </div>

            <div v-else class="rounded-2xl border border-dashed border-border bg-card p-4 sm:p-6">
                <p class="font-semibold text-primary">{{ wc('overview.empty_title') }}</p>
                <p class="mt-1 text-sm text-secondary">{{ wc('overview.empty_hint') }}</p>
                <div class="mt-4 grid grid-cols-2 gap-2 sm:flex">
                    <button type="button" class="rounded-xl bg-buttonPrimary px-3 py-2.5 text-sm font-semibold text-buttonTextPrimary" @click="activeTrainingSection = 'plans'">
                        {{ wc('overview.open_plans') }}
                    </button>
                    <button type="button" class="rounded-xl border border-border px-3 py-2.5 text-sm font-semibold text-primary" @click="activeTrainingSection = 'week'">
                        {{ tx('training_workspace.sections.week.label') }}
                    </button>
                </div>
            </div>

            <aside class="hidden space-y-3 sm:block">
                <section class="rounded-2xl border border-border bg-card p-4">
                    <p class="text-xs font-semibold uppercase tracking-wide text-air-blue">{{ wc('attention.title') }}</p>
                    <div class="mt-3 grid grid-cols-2 gap-2">
                        <span class="rounded-xl border border-border bg-inputBg/40 p-3 text-xs text-secondary"><b class="block text-xl text-primary">{{ trainerDashboard.overdue.length }}</b>{{ wc('attention.overdue') }}</span>
                        <span class="rounded-xl border border-border bg-inputBg/40 p-3 text-xs text-secondary"><b class="block text-xl text-primary">{{ trainerDashboard.feedbackOpen.length }}</b>{{ wc('attention.feedback_open') }}</span>
                    </div>
                </section>
            </aside>
        </section>

        <section v-if="activeTrainingSection === 'logs'" role="tabpanel" aria-labelledby="training-section-tab-logs" class="rounded-2xl border border-border bg-card">
            <div class="flex flex-wrap items-center justify-between gap-3 border-b border-border p-4">
                <div>
                    <p class="text-xs font-semibold uppercase tracking-wide text-secondary">{{ wc('logs.eyebrow') }}</p>
                    <h2 class="text-xl font-semibold text-primary">{{ wc('logs.title') }}</h2>
                </div>
                <button type="button" class="rounded-xl bg-buttonPrimary px-4 py-2 text-sm font-semibold text-buttonTextPrimary" @click="openLogPage">
                    {{ tx('training_workspace.log_create.title') }}
                </button>
            </div>
            <div class="divide-y divide-border">
                <article v-for="log in visibleLogs.slice(0, 8)" :key="log.id" class="grid gap-3 p-4 lg:grid-cols-[1.2fr_1fr_auto] lg:items-center">
                    <div class="min-w-0">
                        <div class="flex flex-wrap items-center gap-2">
                            <span class="flex h-9 w-9 items-center justify-center rounded-xl text-white" :class="sportAccent(log.sport_type)">
                                <i :class="sportIcon(log.sport_type)"></i>
                            </span>
                            <div class="min-w-0">
                                <div class="flex flex-wrap items-center gap-2">
                                    <h3 class="truncate text-base font-semibold text-primary">{{ log.title }}</h3>
                                    <span
                                        v-if="log.status === 'draft'"
                                        class="rounded-full border border-success/40 bg-success/10 px-2 py-0.5 text-[11px] font-semibold text-success"
                                    >
                                        {{ logStatusLabel(log.status) }}
                                    </span>
                                </div>
                                <p class="text-xs text-secondary">
                                    {{ sportLabel(log.sport_type) }} · {{ formatDate(log.performed_at) }} {{ formatTime(log.performed_at) }}
                                </p>
                                <p v-if="log.sport_route" class="mt-1 truncate text-xs font-semibold text-air-blue">
                                    <i class="las la-route me-1"></i>{{ log.sport_route.title }}
                                </p>
                            </div>
                        </div>
                    </div>
                    <div class="grid grid-cols-3 gap-2 text-xs text-secondary">
                        <span class="rounded-lg border border-border px-2 py-1">{{ formatDuration(log.duration_minutes) }}</span>
                        <span class="rounded-lg border border-border px-2 py-1">{{ formatDistance(log.distance_meters) }}</span>
                        <span class="rounded-lg border border-border px-2 py-1">{{ log.entries?.length || 0 }} {{ wc('logs.exercises') }}</span>
                    </div>
                    <div class="text-sm text-secondary lg:text-right">
                        <p class="font-semibold text-primary">{{ log.athlete?.name || wc('logs.me') }}</p>
                        <p v-if="log.trainer">{{ wc('logs.by') }} {{ log.trainer.name }}</p>
                        <p v-else>{{ log.plan_item ? `${wc('logs.plan')}: ${log.plan_item.title}` : wc('logs.spontaneous') }}</p>
                        <button v-if="log.status === 'draft'" type="button" class="mt-2 rounded-lg border border-border px-3 py-1.5 text-xs font-semibold text-primary hover:bg-muted" @click="openLogPage">
                            {{ wc('actions.continue_editing') }}
                        </button>
                        <Link
                            v-else
                            :href="route('auth.training.logs.show', log.id)"
                            class="mt-2 inline-flex rounded-lg border border-border px-3 py-1.5 text-xs font-semibold text-primary hover:bg-muted"
                        >
                            {{ wc('actions.details') }}
                        </Link>
                    </div>
                </article>
                <div v-if="!visibleLogs.length" class="p-6 text-center">
                    <p class="text-sm text-secondary">{{ wc('logs.empty') }}</p>
                    <button type="button" class="mt-3 rounded-xl bg-buttonPrimary px-4 py-2 text-sm font-semibold text-buttonTextPrimary" @click="openLogPage">
                        {{ tx('training_workspace.log_create.title') }}
                    </button>
                </div>
            </div>
        </section>

        <section v-if="activeTrainingSection === 'week'" role="tabpanel" aria-labelledby="training-section-tab-week" class="grid gap-4 xl:grid-cols-[1.4fr_1fr]">
            <div class="rounded-2xl border border-border bg-card p-4">
                <div class="flex flex-wrap items-center justify-between gap-3">
                    <div>
                    <p class="text-xs font-semibold uppercase tracking-wide text-air-blue">{{ wc('week.eyebrow') }}</p>
                    <h2 class="mt-1 text-lg font-semibold text-primary">{{ wc('week.title') }}</h2>
                    </div>
                    <span class="rounded-full border border-border px-3 py-1 text-xs font-semibold text-secondary">{{ wc('week.planned_count', { count: plannedThisWeekCount }) }}</span>
                </div>
                <div class="mt-4 grid gap-2 md:grid-cols-7">
                    <div v-for="day in weekDays" :key="day.key" class="min-h-32 rounded-xl border border-border bg-inputBg/40 p-2 transition hover:border-air-blue/50" role="group" :aria-label="wc('week.day_label', { day: formatWeekday(day.date), count: day.items.length })" @dragover.prevent @drop="dropItemOnDay(day)">
                        <p class="text-xs font-semibold uppercase tracking-wide text-secondary">{{ formatWeekday(day.date) }}</p>
                        <div class="mt-2 space-y-2">
                            <div v-for="item in day.items.slice(0, 3)" :key="item.id" :draggable="Boolean(item.plan?.can_write)" class="rounded-lg border border-border bg-card p-2" :class="item.plan?.can_write ? 'cursor-grab active:cursor-grabbing' : ''" @dragstart="startDragItem(item)">
                                <div class="flex items-start justify-between gap-2">
                                    <p class="line-clamp-2 text-xs font-semibold text-primary">{{ item.title }}</p>
                                    <span class="rounded-full px-2 py-0.5 text-[10px] font-semibold" :class="itemStatusClass(item)">{{ itemStatusLabel(item) }}</span>
                                </div>
                                <p class="mt-1 text-[11px] text-secondary">{{ sportLabel(item.sport_type) }} · {{ formatDuration(item.duration_minutes) }}</p>
                                <div class="mt-2 flex gap-1">
                                    <button type="button" class="rounded-md border border-border px-2 py-1 text-[11px] font-semibold text-primary hover:bg-muted" @click="documentPlanItem(item)">
                                        {{ wc('mobile.log') }}
                                    </button>
                                    <button type="button" class="rounded-md border border-border px-2 py-1 text-[11px] font-semibold text-primary hover:bg-muted" @click="openPlanItem(item)">
                                        {{ wc('actions.details') }}
                                    </button>
                                    <button type="button" class="rounded-md border border-border px-2 py-1 text-[11px] font-semibold text-primary hover:bg-muted" @click="openModal('item-missed', item.plan, item)">
                                        {{ wc('actions.missed') }}
                                    </button>
                                </div>
                                <div v-if="item.plan?.can_write" class="mt-1 grid grid-cols-2 gap-1">
                                    <button type="button" class="rounded-md border border-border px-1.5 py-1 text-[10px] font-semibold text-secondary hover:bg-muted" :aria-label="wc('week.move_previous', { title: item.title })" @click="movePlanItemByDays(item, -1)">
                                        {{ wc('week.previous_short') }}
                                    </button>
                                    <button type="button" class="rounded-md border border-border px-1.5 py-1 text-[10px] font-semibold text-secondary hover:bg-muted" :aria-label="wc('week.move_next', { title: item.title })" @click="movePlanItemByDays(item, 1)">
                                        {{ wc('week.next_short') }}
                                    </button>
                                </div>
                            </div>
                            <p v-if="!day.items.length" class="text-xs text-secondary">{{ wc('week.free') }}</p>
                        </div>
                    </div>
                </div>
            </div>

            <div class="rounded-2xl border border-border bg-card p-4">
                <p class="text-xs font-semibold uppercase tracking-wide text-air-blue">{{ wc('attention.trainer_dashboard') }}</p>
                <h2 class="mt-1 text-lg font-semibold text-primary">{{ wc('attention.title') }}</h2>
                <div class="mt-4 grid grid-cols-2 gap-2">
                    <div class="rounded-xl border border-border bg-inputBg/40 p-3">
                        <p class="text-2xl font-semibold text-primary">{{ trainerDashboard.overdue.length }}</p>
                        <p class="text-xs text-secondary">{{ wc('attention.overdue') }}</p>
                    </div>
                    <div class="rounded-xl border border-border bg-inputBg/40 p-3">
                        <p class="text-2xl font-semibold text-primary">{{ trainerDashboard.missed.length }}</p>
                        <p class="text-xs text-secondary">{{ wc('attention.missed') }}</p>
                    </div>
                    <div class="rounded-xl border border-border bg-inputBg/40 p-3">
                        <p class="text-2xl font-semibold text-primary">{{ trainerDashboard.feedbackOpen.length }}</p>
                        <p class="text-xs text-secondary">{{ wc('attention.feedback_open') }}</p>
                    </div>
                    <div class="rounded-xl border border-border bg-inputBg/40 p-3">
                        <p class="text-2xl font-semibold text-primary">{{ trainerDashboard.painSignals.length }}</p>
                        <p class="text-xs text-secondary">{{ wc('attention.pain_signal') }}</p>
                    </div>
                </div>
                <div class="mt-4 space-y-2">
                    <div v-for="item in trainerDashboard.overdue.slice(0, 3)" :key="item.id" class="rounded-xl border border-warning/30 bg-warning/10 p-3">
                        <p class="text-sm font-semibold text-primary">{{ item.title }}</p>
                        <p class="text-xs text-secondary">{{ item.plan.title }} · {{ formatDate(item.scheduled_at) }}</p>
                    </div>
                    <p v-if="!trainerDashboard.overdue.length" class="text-sm text-secondary">{{ wc('attention.none_overdue') }}</p>
                </div>
            </div>
        </section>

        <section v-if="activeTrainingSection === 'analysis'" role="tabpanel" aria-labelledby="training-section-tab-analysis" class="grid gap-4 xl:grid-cols-[1.2fr_1fr]">
            <div class="rounded-2xl border border-border bg-card p-4">
                <div class="flex items-center justify-between gap-3">
                    <div>
                        <p class="text-xs font-semibold uppercase tracking-wide text-air-blue">{{ wc('analysis.cockpit') }}</p>
                        <h2 class="mt-1 text-lg font-semibold text-primary">{{ wc('analysis.cockpit_title') }}</h2>
                    </div>
                    <span class="rounded-full border border-border px-3 py-1 text-xs font-semibold text-secondary">{{ athleteCockpit.length }} {{ wc('analysis.profiles') }}</span>
                </div>
                <div class="mt-4 grid gap-3 lg:grid-cols-2">
                    <article v-for="entry in athleteCockpit.slice(0, 6)" :key="entry.athlete.id || entry.athlete.name" class="rounded-xl border border-border bg-inputBg/40 p-3">
                        <div class="flex items-start justify-between gap-3">
                            <div class="min-w-0">
                                <p class="truncate text-sm font-semibold text-primary">{{ entry.athlete.name }}</p>
                                <p class="mt-1 text-xs text-secondary">{{ entry.latest?.title || wc('analysis.no_last_session') }} · {{ formatDate(entry.latest?.performed_at || entry.latest?.created_at) }}</p>
                            </div>
                            <span class="rounded-full bg-muted px-2 py-1 text-xs font-semibold text-secondary">{{ entry.sessions }} {{ wc('analysis.sessions') }}</span>
                        </div>
                        <div class="mt-3 grid grid-cols-4 gap-2 text-center text-xs">
                            <span class="rounded-lg border border-border px-2 py-1"><b class="block text-primary">{{ formatDuration(entry.minutes) }}</b>{{ wc('analysis.time') }}</span>
                            <span class="rounded-lg border border-border px-2 py-1"><b class="block text-primary">{{ formatDistance(entry.meters) }}</b>{{ wc('analysis.distance') }}</span>
                            <span class="rounded-lg border border-border px-2 py-1"><b class="block text-primary">{{ entry.avgRpe }}</b>RPE</span>
                            <span class="rounded-lg border border-border px-2 py-1"><b class="block text-primary">{{ entry.avgPain }}</b>{{ wc('analysis.pain') }}</span>
                        </div>
                    </article>
                    <p v-if="!athleteCockpit.length" class="text-sm text-secondary">{{ wc('analysis.empty_cockpit') }}</p>
                </div>
            </div>

            <div class="rounded-2xl border border-air-blue/30 bg-air-blue/10 p-4">
                <div class="flex items-center justify-between gap-3">
                    <div>
                        <p class="text-xs font-semibold uppercase tracking-wide text-air-blue">{{ wc('analysis.ai_coach') }}</p>
                        <h2 class="mt-1 text-lg font-semibold text-primary">{{ wc('analysis.ai_title') }}</h2>
                    </div>
                    <span class="rounded-full border border-air-blue/30 px-3 py-1 text-xs font-semibold text-air-blue">{{ aiGeneratedPlans.length }} {{ wc('analysis.plans') }}</span>
                </div>
                <div class="mt-4 space-y-2">
                    <article v-for="insight in aiGeneratedPlanInsights" :key="`${insight.plan.id}-${insight.label}-${insight.text}`" class="rounded-xl border border-air-blue/25 bg-bg/50 p-3">
                        <div class="flex items-start gap-3">
                            <span class="mt-0.5 flex h-8 w-8 shrink-0 items-center justify-center rounded-lg bg-air-blue text-white">
                                <i class="las la-lightbulb"></i>
                            </span>
                            <div class="min-w-0">
                                <p class="text-xs font-semibold uppercase tracking-wide text-air-blue">{{ insight.label }} · {{ insight.plan.title }}</p>
                                <p class="mt-1 text-sm text-primary">{{ insight.text }}</p>
                            </div>
                        </div>
                    </article>
                    <p v-if="!aiGeneratedPlanInsights.length" class="text-sm text-secondary">{{ wc('analysis.empty_ai') }}</p>
                </div>
            </div>

            <div class="rounded-2xl border border-border bg-card p-4">
                <p class="text-xs font-semibold uppercase tracking-wide text-air-blue">{{ wc('analysis.statistics') }}</p>
                <h2 class="mt-1 text-lg font-semibold text-primary">{{ wc('analysis.distribution') }}</h2>
                <div class="mt-4 space-y-3">
                    <div v-for="row in sportStats.slice(0, 6)" :key="row.key">
                        <div class="flex items-center justify-between text-xs font-semibold">
                            <span class="text-primary">{{ row.label }}</span>
                            <span class="text-secondary">{{ row.sessions }} · {{ formatDuration(row.minutes) }}</span>
                        </div>
                        <div class="mt-1 h-2 overflow-hidden rounded-full bg-muted" role="progressbar" aria-valuemin="0" aria-valuemax="100" :aria-valuenow="Math.min(100, row.sessions * 18)" :aria-label="wc('analysis.distribution_label', { sport: row.label, sessions: row.sessions })">
                            <div class="h-full rounded-full bg-air-blue" :style="{ width: `${Math.min(100, row.sessions * 18)}%` }"></div>
                        </div>
                    </div>
                    <p v-if="!sportStats.length" class="text-sm text-secondary">{{ wc('analysis.empty_statistics') }}</p>
                </div>
            </div>
        </section>

        <div v-if="activeTrainingSection === 'plans'" class="grid gap-5 xl:grid-cols-[1fr_340px]">
            <section class="space-y-4">
                <div class="flex flex-wrap items-center justify-between gap-3">
                    <div>
                        <p class="text-xs font-semibold uppercase tracking-wide text-secondary">{{ selectedSport.label }}</p>
                        <h2 class="text-xl font-semibold text-primary">{{ wc('plans.title') }}</h2>
                    </div>
                    <div class="flex flex-wrap gap-2">
                        <button v-if="canManageTrainingPlans" type="button" class="rounded-xl border border-air-blue/50 bg-air-blue/10 px-4 py-2 text-sm font-semibold text-air-blue hover:bg-air-blue/15 disabled:cursor-not-allowed disabled:opacity-50" :disabled="!aiTrainingPlanAvailable" @click="openAiTrainingPlanModal()">
                            {{ wc('plans.ai_plan') }}
                        </button>
                        <button v-if="canCreatePersonalTrainingPlans" type="button" class="rounded-xl border border-border px-4 py-2 text-sm font-semibold text-primary hover:bg-muted" @click="openModal('plan')">
                            {{ wc('plans.new_plan') }}
                        </button>
                    </div>
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
                                            {{ plan.status === 'published' ? wc('plans.published') : wc('plans.draft') }}
                                        </span>
                                        <span v-if="plan.settings?.ai_generation" class="rounded-full border border-air-blue/40 bg-air-blue/10 px-2.5 py-1 text-xs font-semibold text-air-blue">
                                            {{ wc('plans.ai_generated') }}
                                        </span>
                                        <span v-if="plan.settings?.ai_generation?.profile_estimate_mode" class="rounded-full border border-warning/40 bg-warning/10 px-2.5 py-1 text-xs font-semibold text-warning">
                                            {{ wc('plans.estimate_mode') }}
                                        </span>
                                    </div>
                                    <h3 class="mt-3 truncate text-lg font-semibold text-primary">{{ plan.title }}</h3>
                                    <p class="mt-1 hidden line-clamp-2 text-sm text-secondary md:block">{{ plan.description || wc('plans.no_description') }}</p>
                                    <p v-if="plan.settings?.ai_generation?.convincing_explanation" class="mt-2 hidden line-clamp-2 rounded-xl border border-air-blue/25 bg-air-blue/10 px-3 py-2 text-xs text-primary md:block">
                                        {{ wc('plans.why') }} {{ plan.settings.ai_generation.convincing_explanation }}
                                    </p>
                                    <p v-if="plan.settings?.goal" class="mt-2 rounded-xl border border-air-blue/30 bg-air-blue/10 px-3 py-2 text-xs font-semibold text-primary">
                                        {{ wc('plans.goal') }} {{ plan.settings.goal }}
                                    </p>
                                </div>
                                <span class="flex h-11 w-11 shrink-0 items-center justify-center rounded-xl text-white" :class="sportAccent(plan.items?.[0]?.sport_type)">
                                    <i :class="sportIcon(plan.items?.[0]?.sport_type)" class="text-xl"></i>
                                </span>
                            </div>

                            <div class="mt-4 grid grid-cols-3 gap-2 text-center">
                                <div class="rounded-xl border border-border bg-inputBg/50 p-2">
                                    <p class="text-base font-semibold text-primary">{{ plan.items?.length || 0 }}</p>
                                    <p class="text-[11px] text-secondary">{{ wc('plans.sessions') }}</p>
                                </div>
                                <div class="rounded-xl border border-border bg-inputBg/50 p-2">
                                    <p class="truncate text-base font-semibold text-primary">{{ plan.settings?.weeks || '-' }}</p>
                                    <p class="text-[11px] text-secondary">{{ wc('plans.weeks') }}</p>
                                </div>
                                <div class="rounded-xl border border-border bg-inputBg/50 p-2">
                                    <p class="text-base font-semibold text-primary">{{ plan.settings?.weekly_sessions || '-' }}</p>
                                    <p class="text-[11px] text-secondary">{{ wc('plans.per_week') }}</p>
                                </div>
                            </div>

                            <div class="mt-3 flex flex-wrap gap-2">
                                <span v-if="plan.settings?.phase" class="rounded-full border border-border px-2.5 py-1 text-xs font-semibold text-secondary">
                                    {{ phaseLabels[plan.settings.phase] || plan.settings.phase }}
                                </span>
                                <span v-if="plan.settings?.level" class="rounded-full border border-border px-2.5 py-1 text-xs font-semibold text-secondary">
                                    {{ levelLabels[plan.settings.level] || plan.settings.level }}
                                </span>
                                <span v-if="plan.settings?.macrocycle" class="rounded-full border border-border px-2.5 py-1 text-xs font-semibold text-secondary">
                                    {{ plan.settings.macrocycle }}
                                </span>
                                <span v-if="plan.settings?.mesocycle" class="rounded-full border border-border px-2.5 py-1 text-xs font-semibold text-secondary">
                                    {{ plan.settings.mesocycle }}
                                </span>
                                <span v-if="plan.settings?.competition_date" class="rounded-full border border-border px-2.5 py-1 text-xs font-semibold text-secondary">
                                    {{ wc('plans.target_date') }} {{ formatDate(plan.settings.competition_date) }}
                                </span>
                                <span v-if="plan.team?.name" class="rounded-full border border-border px-2.5 py-1 text-xs font-semibold text-secondary">
                                    {{ plan.team.name }}
                                </span>
                                <span class="rounded-full border border-border px-2.5 py-1 text-xs font-semibold text-secondary">
                                    {{ permissionLabels[plan.share_permission] }}
                                </span>
                            </div>

                            <div class="mt-4 rounded-xl border border-border bg-inputBg/40 p-3">
                                <div class="flex items-center justify-between gap-3 text-xs font-semibold">
                                    <span class="text-secondary">{{ wc('plans.progress') }}</span>
                                    <span class="text-primary">{{ plan.progress?.percent || 0 }}%</span>
                                </div>
                                <div class="mt-2 h-2 overflow-hidden rounded-full bg-muted" role="progressbar" aria-valuemin="0" aria-valuemax="100" :aria-valuenow="plan.progress?.percent || 0" :aria-label="`${wc('plans.progress')}: ${plan.title}`">
                                    <div class="h-full rounded-full bg-success" :style="{ width: `${plan.progress?.percent || 0}%` }"></div>
                                </div>
                                <div class="mt-2 flex flex-wrap gap-2 text-[11px] text-secondary">
                                    <span>{{ wc('plans.completed', { count: plan.progress?.completed || 0 }) }}</span>
                                    <span>{{ wc('plans.open', { count: plan.progress?.open || 0 }) }}</span>
                                    <span>{{ wc('plans.missed', { count: plan.progress?.missed || 0 }) }}</span>
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
                                                <span class="shrink-0 rounded-full px-2 py-0.5 text-[11px] font-semibold" :class="itemStatusClass(item)">{{ itemStatusLabel(item) }}</span>
                                            </div>
                                            <p class="mt-1 text-xs text-secondary">{{ item.scheduled_at ? formatDate(item.scheduled_at) : wc('plans.open_date') }}</p>
                                            <p class="mt-1 text-xs text-secondary">{{ sportLabel(item.sport_type) }} · {{ formatDuration(item.duration_minutes) }}</p>
                                            <Link v-if="item.sport_route" :href="item.sport_route.navigation_url" class="mt-1 inline-flex items-center gap-1 text-xs font-semibold text-air-blue hover:underline">
                                                <i class="las la-route"></i>{{ item.sport_route.title }}
                                            </Link>
                                            <div class="mt-2 flex flex-wrap gap-1">
                                                <span v-if="item.metrics?.Abschnitt" class="rounded-full bg-air-blue/10 px-2 py-0.5 text-[11px] font-semibold text-air-blue">{{ item.metrics.Abschnitt }}</span>
                                                <span v-if="item.metrics?.Trainingsziel" class="rounded-full bg-success/10 px-2 py-0.5 text-[11px] font-semibold text-success">{{ item.metrics.Trainingsziel }}</span>
                                                <span v-if="item.metrics?.Niveau" class="rounded-full bg-muted px-2 py-0.5 text-[11px] text-secondary">{{ item.metrics.Niveau }}</span>
                                                <span v-if="item.metrics?.Equipment" class="rounded-full bg-muted px-2 py-0.5 text-[11px] text-secondary">{{ item.metrics.Equipment }}</span>
                                                <span v-if="item.metrics?.Woche" class="rounded-full bg-air-blue/10 px-2 py-0.5 text-[11px] font-semibold text-air-blue">{{ wc('plans.week_metric', { week: item.metrics.Woche }) }}</span>
                                                <span v-if="item.metrics?.Belastung" class="rounded-full bg-muted px-2 py-0.5 text-[11px] text-secondary">{{ loadLabels[item.metrics.Belastung] || item.metrics.Belastung }}</span>
                                                <span v-if="item.metrics?.Fokus" class="rounded-full bg-muted px-2 py-0.5 text-[11px] text-secondary">{{ item.metrics.Fokus }}</span>
                                            </div>
                                            <div v-if="item.metrics && Object.keys(item.metrics).length" class="mt-2 flex flex-wrap gap-1">
                                                <span v-for="(value, key) in item.metrics" v-show="!structuredMetricKeys.includes(key)" :key="key" class="rounded-full bg-muted px-2 py-0.5 text-[11px] text-secondary">
                                                    {{ key }}: {{ value }}
                                                </span>
                                            </div>
                                            <div v-if="plan.can_write" class="mt-3 flex flex-wrap gap-2">
                                                <button type="button" class="rounded-lg border border-border px-2.5 py-1.5 text-[11px] font-semibold text-primary hover:bg-muted" @click="openPlanItem({ ...item, plan })">
                                                    {{ wc('actions.details') }}
                                                </button>
                                                <button type="button" class="rounded-lg border border-success/40 px-2.5 py-1.5 text-[11px] font-semibold text-success hover:bg-success/10" @click="documentPlanItem(item)">
                                                    {{ wc('actions.document') }}
                                                </button>
                                                <button type="button" class="rounded-lg border border-warning/40 px-2.5 py-1.5 text-[11px] font-semibold text-warning hover:bg-warning/10" @click="openModal('item-missed', plan, item)">
                                                    {{ wc('actions.missed') }}
                                                </button>
                                                <button type="button" class="rounded-lg border border-border px-2.5 py-1.5 text-[11px] font-semibold text-primary hover:bg-muted" @click="openModal('item-edit', plan, item)">
                                                    {{ wc('actions.edit') }}
                                                </button>
                                                <button type="button" class="rounded-lg border border-border px-2.5 py-1.5 text-[11px] font-semibold text-primary hover:bg-muted" @click="duplicatePlanItem(plan, item)">
                                                    {{ wc('actions.copy') }}
                                                </button>
                                                <button type="button" class="rounded-lg border border-danger/40 px-2.5 py-1.5 text-[11px] font-semibold text-danger hover:bg-danger/10" @click="openModal('item-delete', plan, item)">
                                                    {{ wc('actions.delete') }}
                                                </button>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="flex flex-wrap gap-2 border-t border-border bg-inputBg/30 p-3">
                            <button v-if="plan.can_write" type="button" class="rounded-lg border border-border px-3 py-2 text-xs font-semibold text-primary hover:bg-muted" @click="openModal('item', plan)">
                                {{ wc('actions.add_session') }}
                            </button>
                            <button v-if="plan.can_write" type="button" class="rounded-lg border border-border px-3 py-2 text-xs font-semibold text-primary hover:bg-muted" @click="openModal('edit', plan)">
                                {{ wc('actions.edit') }}
                            </button>
                            <button v-if="plan.can_write && plan.settings?.ai_generation && aiTrainingPlanAvailable" type="button" class="rounded-lg border border-air-blue/40 bg-air-blue/10 px-3 py-2 text-xs font-semibold text-air-blue hover:bg-air-blue/15" @click="openAiTrainingPlanModal(plan)">
                                {{ wc('actions.adapt_ai') }}
                            </button>
                            <button v-if="plan.can_write" type="button" class="rounded-lg border border-border px-3 py-2 text-xs font-semibold text-primary hover:bg-muted" @click="duplicatePlan(plan)">
                                {{ wc('actions.copy_template') }}
                            </button>
                            <button v-if="plan.status !== 'published' && plan.can_write" type="button" class="rounded-lg border border-success/40 px-3 py-2 text-xs font-semibold text-success hover:bg-success/10" @click="publishPlan(plan)">
                                {{ wc('actions.publish') }}
                            </button>
                            <button v-if="plan.can_delete" type="button" class="ms-auto rounded-lg border border-danger/40 px-3 py-2 text-xs font-semibold text-danger hover:bg-danger/10" @click="openModal('delete', plan)">
                                {{ wc('actions.delete') }}
                            </button>
                        </div>
                    </article>

                    <div v-if="!filteredPlans.length" class="rounded-2xl border border-dashed border-border bg-card p-8 text-center lg:col-span-2">
                        <p class="text-lg font-semibold text-primary">{{ wc('plans.empty_title') }}</p>
                        <p class="mt-2 text-sm text-secondary">{{ canManageTrainingPlans ? wc('plans.empty_manager') : wc('plans.empty_athlete') }}</p>
                        <div v-if="canCreatePersonalTrainingPlans" class="mt-4 flex flex-wrap justify-center gap-2">
                            <button v-if="canManageTrainingPlans" type="button" class="rounded-xl border border-air-blue/50 bg-air-blue/10 px-4 py-2 text-sm font-semibold text-air-blue disabled:opacity-50" :disabled="!aiTrainingPlanAvailable" @click="openAiTrainingPlanModal()">
                                {{ wc('plans.create_ai') }}
                            </button>
                            <button type="button" class="rounded-xl bg-buttonPrimary px-4 py-2 text-sm font-semibold text-buttonTextPrimary" @click="openModal('plan')">
                                {{ wc('plans.create_manual') }}
                            </button>
                        </div>
                    </div>
                </div>
            </section>

            <aside class="space-y-4">
                <section class="rounded-2xl border border-border bg-card p-4">
                    <h2 class="text-base font-semibold text-primary">{{ wc('logs.recent') }}</h2>
                    <div class="mt-4 space-y-3">
                        <div v-for="log in visibleLogs.slice(0, 6)" :key="log.id" class="flex items-center gap-3 rounded-xl border border-border bg-inputBg/40 p-2">
                            <span class="flex h-12 w-12 items-center justify-center rounded-xl text-white" :class="sportAccent(log.sport_type)">
                                <i :class="sportIcon(log.sport_type)" class="text-xl"></i>
                            </span>
                            <div class="min-w-0">
                                <p class="truncate text-sm font-semibold text-primary">{{ log.title || wc('logs.training_fallback') }}</p>
                                <p class="text-xs text-secondary">{{ formatDate(log.performed_at) }} · {{ formatDuration(log.duration_minutes) }} · {{ log.athlete?.name || wc('logs.me') }}</p>
                            </div>
                        </div>
                        <p v-if="!visibleLogs.length" class="text-sm text-secondary">{{ wc('logs.empty_recent') }}</p>
                    </div>
                </section>

                <section class="rounded-2xl border border-border bg-card p-4">
                    <h2 class="text-base font-semibold text-primary">{{ wc('parameters.title') }}</h2>
                    <p class="mt-1 text-sm text-secondary">{{ wc('parameters.hint') }}</p>
                    <div class="mt-4 flex flex-wrap gap-2">
                        <span v-for="metric in (selectedSport.metrics || [wc('parameters.duration'), wc('parameters.intensity'), wc('parameters.todo')])" :key="metric" class="rounded-full border border-border px-3 py-1 text-xs font-semibold text-secondary">
                            {{ metric }}
                        </span>
                    </div>
                </section>

                <section class="rounded-2xl border border-border bg-card p-4">
                    <h2 class="text-base font-semibold text-primary">{{ wc('templates.title') }}</h2>
                    <p class="mt-1 text-sm text-secondary">{{ wc('templates.hint') }}</p>
                    <div class="mt-4 space-y-2">
                        <div v-for="plan in templatePlans.slice(0, 4)" :key="plan.id" class="rounded-xl border border-border bg-inputBg/40 p-3">
                            <p class="text-sm font-semibold text-primary">{{ plan.title }}</p>
                            <p class="mt-1 text-xs text-secondary">{{ wc('templates.meta', { count: plan.items?.length || 0, phase: phaseLabels[plan.settings?.phase] || wc('templates.phase_open') }) }}</p>
                            <button v-if="canManageTrainingPlans && (plan.can_write || plan.settings?.is_template)" type="button" class="mt-2 rounded-lg border border-border px-3 py-1.5 text-xs font-semibold text-primary hover:bg-muted" @click="duplicatePlan(plan)">
                                {{ wc('actions.reuse') }}
                            </button>
                        </div>
                        <p v-if="!templatePlans.length" class="text-sm text-secondary">{{ wc('templates.empty') }}</p>
                    </div>
                </section>
            </aside>
        </div>

        <Teleport to="body">
            <TrainingModalStack
                v-if="activeModal"
                :active-modal="activeModal"
                :delete-text="deleteText"
                :selected-plan="selectedPlan"
                :selected-item="selectedItem"
                :selected-draft="selectedDraft"
                :athlete-options="athleteOptions"
                :log-form="logForm"
                :planned-log-items="plannedLogItems"
                :sport-choices="sportChoices"
                :format-date="formatDate"
                :add-log-entry="addLogEntry"
                :apply-selected-plan-item="applySelectedPlanItem"
                :remove-log-entry="removeLogEntry"
                :set-log-status="setLogStatus"
                :submit-log="submitLog"
                :activity-form="activityForm"
                :set-activity-image-element="setActivityImageElement"
                :sports="sports"
                :set-activity-image="setActivityImage"
                :submit-activity="submitActivity"
                :ai-plan-step="aiPlanStep"
                :ai-plan-steps="aiPlanSteps"
                :can-open-ai-plan-step="canOpenAiPlanStep"
                :ai-training-plan-error="aiTrainingPlanError"
                :ai-training-plan-message="aiTrainingPlanMessage"
                :ai-profile-missing-fields="aiProfileMissingFields"
                :ai-profile-missing-message="aiProfileMissingMessage"
                :ai-profile-completion-url="aiProfileCompletionUrl"
                :ai-profile-estimate-allowed="aiProfileEstimateAllowed"
                :ai-training-plan-generating="aiTrainingPlanGenerating"
                :generate-ai-training-plan-with-profile-estimates="generateAiTrainingPlanWithProfileEstimates"
                :ai-plan-source-plan="aiPlanSourcePlan"
                :ai-training-provider-label="aiTrainingProviderLabel"
                :ai-plan-limit-label="aiPlanLimitLabel"
                :ai-training-plan="aiTrainingPlan"
                :ai-plan-form="aiPlanForm"
                :ai-plan-sport-choices="aiPlanSportChoices"
                :select-ai-plan-sport-type="selectAiPlanSportType"
                :ai-plan-duration-presets="aiPlanDurationPresets"
                :ai-plan-max-weeks="aiPlanMaxWeeks"
                :set-ai-plan-duration-preset="setAiPlanDurationPreset"
                :ai-plan-too-large="aiPlanTooLarge"
                :ai-plan-weeks-too-long="aiPlanWeeksTooLong"
                :ai-plan-requested-items="aiPlanRequestedItems"
                :ai-plan-max-items="aiPlanMaxItems"
                :ai-training-plan-preview="aiTrainingPlanPreview"
                :ai-safety-accepted="aiSafetyAccepted"
                :ai-safety-can-save="aiSafetyCanSave"
                :ai-safety-gate="aiSafetyGate"
                :quality-risk-class="qualityRiskClass"
                :quality-status-class="qualityStatusClass"
                :generate-ai-training-plan="generateAiTrainingPlan"
                :continue-ai-training-plan="continueAiTrainingPlan"
                :ai-plan-cannot-generate="aiPlanCannotGenerate"
                :save-ai-training-plan="saveAiTrainingPlan"
                :ai-training-plan-saving="aiTrainingPlanSaving"
                :sport-label="sportLabel"
                :plan-wizard-steps="planWizardSteps"
                :plan-wizard-step="planWizardStep"
                :can-open-plan-wizard-step="canOpenPlanWizardStep"
                :go-to-plan-wizard-step="goToPlanWizardStep"
                :plan-form="planForm"
                :personal-plan-only="!canManageTrainingPlans"
                :plan-training-types="planTrainingTypes"
                :training-session-blocks="trainingSessionBlocks"
                :training-goals="trainingGoals"
                :equipment-presets="equipmentPresets"
                :select-plan-training-type="selectPlanTrainingType"
                :plan-sport="planSport"
                :exercise-library="exerciseLibrary"
                :teams="teams"
                :people="people"
                :private-people="privatePeople"
                :selected-team-members="selectedTeamMembers"
                :selected-edit-team-members="selectedEditTeamMembers"
                :set-plan-target-type="setPlanTargetType"
                :set-plan-team-mode="setPlanTeamMode"
                :toggle-plan-user="togglePlanUser"
                :apply-plan-exercise-template="applyPlanExerciseTemplate"
                :set-plan-image="setPlanImage"
                :set-plan-image-element="setPlanImageElement"
                :previous-plan-wizard-step="previousPlanWizardStep"
                :next-plan-wizard-step="nextPlanWizardStep"
                :plan-wizard-can-continue="planWizardCanContinue"
                :submit-plan="submitPlan"
                :edit-form="editForm"
                :edit-plan-audience-can-submit="editPlanAudienceCanSubmit"
                :update-plan="updatePlan"
                :item-form="itemForm"
                :item-sport="itemSport"
                :apply-exercise-template="applyExerciseTemplate"
                :set-item-image="setItemImage"
                :submit-plan-item="submitPlanItem"
                :edit-item-form="editItemForm"
                :edit-item-sport="editItemSport"
                :sport-routes="sportRoutes"
                :set-edit-item-image="setEditItemImage"
                :update-plan-item="updatePlanItem"
                :missed-form="missedForm"
                :mark-plan-item-missed="markPlanItemMissed"
                :close-modal="closeModal"
                :delete-plan="deletePlan"
                :delete-plan-item="deletePlanItem"
                :delete-draft="deleteDraft"
                @update:ai-plan-step="updateAiPlanStep"
                @update:ai-safety-accepted="updateAiSafetyAccepted"
                @update:delete-text="updateDeleteText"
            />
        </Teleport>

        <div class="fixed inset-x-0 bottom-0 z-40 border-t border-border bg-bg/95 px-4 py-3 shadow-2xl shadow-black/30 backdrop-blur sm:hidden">
            <div class="mx-auto grid max-w-md grid-cols-[1fr_1fr_1fr_auto] gap-2">
                <button v-if="canCreatePersonalTrainingPlans" type="button" class="inline-flex h-12 items-center justify-center gap-2 rounded-xl bg-buttonPrimary px-3 text-sm font-semibold text-buttonTextPrimary" @click="openModal('plan')">
                    <i class="las la-plus-circle text-lg"></i>
                    {{ wc('mobile.plan') }}
                </button>
                <button v-if="canManageTrainingPlans" type="button" class="inline-flex h-12 items-center justify-center gap-2 rounded-xl border border-air-blue/50 bg-air-blue/10 px-3 text-sm font-semibold text-air-blue disabled:opacity-50" :disabled="!aiTrainingPlanAvailable" @click="openAiTrainingPlanModal()">
                    <i class="las la-magic text-lg"></i>
                    {{ wc('mobile.ai') }}
                </button>
                <button type="button" class="inline-flex h-12 items-center justify-center gap-2 rounded-xl border border-border bg-card px-3 text-sm font-semibold text-primary" @click="openLogPage">
                    <i class="las la-pen-alt text-lg"></i>
                    {{ wc('mobile.log') }}
                </button>
                <button type="button" class="flex h-12 w-12 items-center justify-center rounded-xl border border-border bg-card text-primary" :aria-label="wc('week.open')" @click="activeTrainingSection = 'week'">
                    <i class="las la-calendar-week text-xl"></i>
                </button>
            </div>
        </div>
    </div>
</template>
