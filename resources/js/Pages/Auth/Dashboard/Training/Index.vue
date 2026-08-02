<script setup>
import AppLayout from "@/Components/Auth/Layouts/AppLayout.vue"
import { useTrainingWorkspace } from "@/composables/useTrainingWorkspace"
import { Head, Link } from "@inertiajs/vue3"
import { useI18n } from 'vue-i18n'

defineOptions({ layout: AppLayout })

const { t } = useI18n()
const tx = (key, fallback = key, params = {}) => {
    if (fallback && typeof fallback === 'object') {
        params = fallback
        fallback = key
    }
    const translated = t(key, params)
    return translated === key ? fallback : translated
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
    aiCapabilities: { type: Object, default: () => ({}) },
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
} = useTrainingWorkspace(props)
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
                        <button type="button" class="inline-flex min-h-10 items-center justify-center gap-2 rounded-xl bg-buttonPrimary px-3 py-2 text-sm font-semibold text-buttonTextPrimary sm:min-h-11 sm:px-4" @click="openModal('plan')">
                            <i class="las la-plus-circle text-lg"></i>
                            {{ tx('training_workspace.actions.create_plan') }}
                        </button>
                        <button type="button" class="inline-flex min-h-10 items-center justify-center gap-2 rounded-xl border border-air-blue/50 bg-air-blue/10 px-3 py-2 text-sm font-semibold text-air-blue hover:bg-air-blue/15 disabled:cursor-not-allowed disabled:opacity-50 sm:min-h-11 sm:px-4" :disabled="!aiTrainingPlanAvailable" @click="openAiTrainingPlanModal()">
                            <i class="las la-magic text-lg"></i>
                            {{ tx('training_workspace.actions.ai_plan') }}
                        </button>
                        <button type="button" class="inline-flex min-h-10 items-center justify-center gap-2 rounded-xl border border-border px-3 py-2 text-sm font-semibold text-primary hover:bg-muted sm:min-h-11 sm:px-4" @click="openLogPage">
                            <i class="las la-pen-alt text-lg"></i>
                            {{ tx('training_workspace.actions.document') }}
                        </button>
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
                            <p class="text-xs font-semibold uppercase tracking-wide text-air-blue">{{ tx('auto.Training fortsetzen', 'Training fortsetzen') }}</p>
                            <span class="rounded-full border border-success/40 bg-success/10 px-2 py-0.5 text-[11px] font-semibold text-success">
                                {{ tx('auto.Entwurf automatisch gespeichert', 'Entwurf automatisch gespeichert') }}
                            </span>
                        </div>
                        <h2 class="mt-1 truncate text-lg font-semibold text-primary">{{ activeDraftLog.title || tx('auto.Training-Entwurf', 'Training-Entwurf') }}</h2>
                        <p class="mt-1 text-sm text-secondary">
                            {{ sportLabel(activeDraftLog.sport_type) }} &middot; zuletzt gespeichert {{ formatDate(activeDraftLog.updated_at) }} {{ formatTime(activeDraftLog.updated_at) }} &middot; {{ activeDraftLog.entries?.length || 0 }} Einträge
                        </p>
                    </div>
                </div>
                <div class="flex flex-col gap-2 sm:flex-row">
                    <button type="button" class="rounded-xl bg-buttonPrimary px-4 py-2 text-sm font-semibold text-buttonTextPrimary" @click="openLogPage">
                        {{ tx('auto.Weiter trainieren', 'Weiter trainieren') }}
                    </button>
                    <button type="button" class="rounded-xl border border-danger/40 px-4 py-2 text-sm font-semibold text-danger hover:bg-danger/10" @click="openDraftDelete">
                        {{ tx('auto.Entwurf verwerfen', 'Entwurf verwerfen') }}
                    </button>
                </div>
            </div>
        </section>

        <nav class="flex gap-1.5 overflow-x-auto rounded-2xl border border-border bg-card p-1.5 md:grid md:grid-cols-5 md:gap-2 md:overflow-visible md:p-2">
            <button
                v-for="section in trainingSections"
                :key="section.key"
                type="button"
                :class="[
                    'flex min-w-[76px] shrink-0 items-center justify-center gap-1.5 rounded-xl border px-2 py-2 text-center transition md:min-w-0 md:justify-start md:gap-3 md:px-3 md:py-3 md:text-start',
                    activeTrainingSection === section.key
                        ? 'border-air-blue bg-air-blue/15 text-primary shadow-lg shadow-air-blue/10'
                        : 'border-transparent text-secondary hover:border-border hover:bg-inputBg'
                ]"
                @click="activeTrainingSection = section.key"
            >
                <i :class="[section.icon, 'text-lg md:text-xl']"></i>
                <span class="min-w-0">
                    <span class="block text-xs font-semibold md:text-sm">{{ section.label }}</span>
                    <span class="hidden truncate text-xs opacity-80 sm:block">{{ section.hint }}</span>
                </span>
            </button>
        </nav>

        <section v-if="activeTrainingSection === 'plans'" class="rounded-2xl border border-border bg-card p-3">
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

        <section v-if="activeTrainingSection === 'overview'" class="grid gap-3 xl:grid-cols-[minmax(0,1fr),360px]">
            <div v-if="upcomingItems.length" class="rounded-2xl border border-border bg-card p-3 sm:p-4">
                <div class="flex flex-wrap items-center justify-between gap-3">
                    <div>
                        <p class="text-xs font-semibold uppercase tracking-wide text-air-blue">{{ tx('auto.Start', 'Start') }}</p>
                        <h2 class="mt-1 text-lg font-semibold text-primary sm:text-xl">{{ tx('auto.Was steht als Nächstes an?', 'Was steht als Nächstes an?') }}</h2>
                    </div>
                    <button type="button" class="rounded-xl bg-buttonPrimary px-3 py-2 text-sm font-semibold text-buttonTextPrimary sm:px-4" @click="activeTrainingSection = 'plans'">
                        {{ tx('auto.Zu den Plänen', 'Zu den Plänen') }}
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
                                <div class="mt-3 flex flex-wrap gap-2">
                                    <button type="button" class="rounded-lg border border-success/40 px-3 py-1.5 text-xs font-semibold text-success hover:bg-success/10" @click="documentPlanItem(item)">
                                        {{ tx('auto.Dokumentieren', 'Dokumentieren') }}
                                    </button>
                                    <button type="button" class="rounded-lg border border-border px-3 py-1.5 text-xs font-semibold text-primary hover:bg-muted" @click="openPlanItem(item)">
                                        {{ tx('auto.Details', 'Details') }}
                                    </button>
                                </div>
                            </div>
                        </div>
                    </article>
                </div>
            </div>

            <div v-else class="rounded-2xl border border-border bg-card p-2 sm:hidden">
                <div class="grid grid-cols-2 gap-2">
                    <button type="button" class="rounded-xl bg-buttonPrimary px-3 py-2.5 text-sm font-semibold text-buttonTextPrimary" @click="activeTrainingSection = 'plans'">
                        {{ tx('auto.Pläne öffnen', 'Pläne öffnen') }}
                    </button>
                    <button type="button" class="rounded-xl border border-border px-3 py-2.5 text-sm font-semibold text-primary" @click="activeTrainingSection = 'week'">
                        {{ tx('auto.Woche', 'Woche') }}
                    </button>
                </div>
            </div>

            <aside class="hidden space-y-3 sm:block">
                <section class="rounded-2xl border border-border bg-card p-4">
                    <p class="text-xs font-semibold uppercase tracking-wide text-air-blue">{{ tx('auto.Aufmerksamkeit', 'Aufmerksamkeit') }}</p>
                    <div class="mt-3 grid grid-cols-2 gap-2">
                        <span class="rounded-xl border border-border bg-inputBg/40 p-3 text-xs text-secondary"><b class="block text-xl text-primary">{{ trainerDashboard.overdue.length }}</b>{{ tx('auto.überfällig', 'überfällig') }}</span>
                        <span class="rounded-xl border border-border bg-inputBg/40 p-3 text-xs text-secondary"><b class="block text-xl text-primary">{{ trainerDashboard.feedbackOpen.length }}</b>{{ tx('auto.Feedback offen', 'Feedback offen') }}</span>
                    </div>
                </section>
            </aside>
        </section>

        <section v-if="activeTrainingSection === 'logs'" class="rounded-2xl border border-border bg-card">
            <div class="flex flex-wrap items-center justify-between gap-3 border-b border-border p-4">
                <div>
                    <p class="text-xs font-semibold uppercase tracking-wide text-secondary">{{ tx('auto.Trainingsdokumentation', 'Trainingsdokumentation') }}</p>
                    <h2 class="text-xl font-semibold text-primary">{{ tx('auto.Ist-Einheiten', 'Ist-Einheiten') }}</h2>
                </div>
                <button type="button" class="rounded-xl bg-buttonPrimary px-4 py-2 text-sm font-semibold text-buttonTextPrimary" @click="openLogPage">
                    {{ tx('training_workspace.log_create.title', 'Training dokumentieren') }}
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
                            </div>
                        </div>
                    </div>
                    <div class="grid grid-cols-3 gap-2 text-xs text-secondary">
                        <span class="rounded-lg border border-border px-2 py-1">{{ formatDuration(log.duration_minutes) }}</span>
                        <span class="rounded-lg border border-border px-2 py-1">{{ formatDistance(log.distance_meters) }}</span>
                        <span class="rounded-lg border border-border px-2 py-1">{{ log.entries?.length || 0 }} {{ tx('auto.Übungen', 'Übungen') }}</span>
                    </div>
                    <div class="text-sm text-secondary lg:text-right">
                        <p class="font-semibold text-primary">{{ log.athlete?.name || tx('auto.Ich', 'Ich') }}</p>
                        <p v-if="log.trainer">{{ tx('auto.durch', 'durch') }} {{ log.trainer.name }}</p>
                        <p v-else>{{ log.plan_item ? `${tx('auto.Plan', 'Plan')}: ${log.plan_item.title}` : tx('auto.Spontan', 'Spontan') }}</p>
                        <button v-if="log.status === 'draft'" type="button" class="mt-2 rounded-lg border border-border px-3 py-1.5 text-xs font-semibold text-primary hover:bg-muted" @click="openLogPage">
                            Weiter bearbeiten
                        </button>
                    </div>
                </article>
                <p v-if="!visibleLogs.length" class="p-4 text-sm text-secondary">{{ tx('auto.Noch keine Trainings dokumentiert.', 'Noch keine Trainings dokumentiert.') }}</p>
            </div>
        </section>

        <section v-if="activeTrainingSection === 'week'" class="grid gap-4 xl:grid-cols-[1.4fr_1fr]">
            <div class="rounded-2xl border border-border bg-card p-4">
                <div class="flex flex-wrap items-center justify-between gap-3">
                    <div>
                    <p class="text-xs font-semibold uppercase tracking-wide text-air-blue">{{ tx('auto.Wochenansicht', 'Wochenansicht') }}</p>
                    <h2 class="mt-1 text-lg font-semibold text-primary">{{ tx('auto.Diese Trainingswoche', 'Diese Trainingswoche') }}</h2>
                    </div>
                    <span class="rounded-full border border-border px-3 py-1 text-xs font-semibold text-secondary">{{ plannedLogItems.length }} {{ tx('auto.geplante Einheiten', 'geplante Einheiten') }}</span>
                </div>
                <div class="mt-4 grid gap-2 md:grid-cols-7">
                    <div v-for="day in weekDays" :key="day.key" class="min-h-32 rounded-xl border border-border bg-inputBg/40 p-2 transition hover:border-air-blue/50" @dragover.prevent @drop="dropItemOnDay(day)">
                        <p class="text-xs font-semibold uppercase tracking-wide text-secondary">{{ formatWeekday(day.date) }}</p>
                        <div class="mt-2 space-y-2">
                            <div v-for="item in day.items.slice(0, 3)" :key="item.id" draggable="true" class="cursor-grab rounded-lg border border-border bg-card p-2 active:cursor-grabbing" @dragstart="startDragItem(item)">
                                <div class="flex items-start justify-between gap-2">
                                    <p class="line-clamp-2 text-xs font-semibold text-primary">{{ item.title }}</p>
                                    <span class="rounded-full px-2 py-0.5 text-[10px] font-semibold" :class="itemStatusClass(item)">{{ itemStatusLabel(item) }}</span>
                                </div>
                                <p class="mt-1 text-[11px] text-secondary">{{ sportLabel(item.sport_type) }} · {{ formatDuration(item.duration_minutes) }}</p>
                                <div class="mt-2 flex gap-1">
                                    <button type="button" class="rounded-md border border-border px-2 py-1 text-[11px] font-semibold text-primary hover:bg-muted" @click="documentPlanItem(item)">
                                        Log
                                    </button>
                                    <button type="button" class="rounded-md border border-border px-2 py-1 text-[11px] font-semibold text-primary hover:bg-muted" @click="openPlanItem(item)">
                                        Details
                                    </button>
                                    <button type="button" class="rounded-md border border-border px-2 py-1 text-[11px] font-semibold text-primary hover:bg-muted" @click="openModal('item-missed', item.plan, item)">
                                        Ausfall
                                    </button>
                                </div>
                            </div>
                            <p v-if="!day.items.length" class="text-xs text-secondary">{{ tx('frei', 'frei') }}</p>
                        </div>
                    </div>
                </div>
            </div>

            <div class="rounded-2xl border border-border bg-card p-4">
                <p class="text-xs font-semibold uppercase tracking-wide text-air-blue">{{ tx('auto.Trainer-Dashboard', 'Trainer-Dashboard') }}</p>
                <h2 class="mt-1 text-lg font-semibold text-primary">{{ tx('auto.Aufmerksamkeit', 'Aufmerksamkeit') }}</h2>
                <div class="mt-4 grid grid-cols-2 gap-2">
                    <div class="rounded-xl border border-border bg-inputBg/40 p-3">
                        <p class="text-2xl font-semibold text-primary">{{ trainerDashboard.overdue.length }}</p>
                        <p class="text-xs text-secondary">{{ tx('auto.überfällig', 'überfällig') }}</p>
                    </div>
                    <div class="rounded-xl border border-border bg-inputBg/40 p-3">
                        <p class="text-2xl font-semibold text-primary">{{ trainerDashboard.missed.length }}</p>
                        <p class="text-xs text-secondary">{{ tx('auto.Ausfälle', 'Ausfälle') }}</p>
                    </div>
                    <div class="rounded-xl border border-border bg-inputBg/40 p-3">
                        <p class="text-2xl font-semibold text-primary">{{ trainerDashboard.feedbackOpen.length }}</p>
                        <p class="text-xs text-secondary">{{ tx('auto.Feedback offen', 'Feedback offen') }}</p>
                    </div>
                    <div class="rounded-xl border border-border bg-inputBg/40 p-3">
                        <p class="text-2xl font-semibold text-primary">{{ trainerDashboard.painSignals.length }}</p>
                        <p class="text-xs text-secondary">{{ tx('auto.Schmerzsignal', 'Schmerzsignal') }}</p>
                    </div>
                </div>
                <div class="mt-4 space-y-2">
                    <div v-for="item in trainerDashboard.overdue.slice(0, 3)" :key="item.id" class="rounded-xl border border-warning/30 bg-warning/10 p-3">
                        <p class="text-sm font-semibold text-primary">{{ item.title }}</p>
                        <p class="text-xs text-secondary">{{ item.plan.title }} · {{ formatDate(item.scheduled_at) }}</p>
                    </div>
                    <p v-if="!trainerDashboard.overdue.length" class="text-sm text-secondary">{{ tx('auto.Keine überfälligen Einheiten.', 'Keine überfälligen Einheiten.') }}</p>
                </div>
            </div>
        </section>

        <section v-if="activeTrainingSection === 'analysis'" class="grid gap-4 xl:grid-cols-[1.2fr_1fr]">
            <div class="rounded-2xl border border-border bg-card p-4">
                <div class="flex items-center justify-between gap-3">
                    <div>
                        <p class="text-xs font-semibold uppercase tracking-wide text-air-blue">{{ tx('auto.Athleten-Cockpit', 'Athleten-Cockpit') }}</p>
                        <h2 class="mt-1 text-lg font-semibold text-primary">{{ tx('auto.Belastung, Signale und letzte Aktivität', 'Belastung, Signale und letzte Aktivität') }}</h2>
                    </div>
                    <span class="rounded-full border border-border px-3 py-1 text-xs font-semibold text-secondary">{{ athleteCockpit.length }} {{ tx('Profile', 'Profile') }}</span>
                </div>
                <div class="mt-4 grid gap-3 lg:grid-cols-2">
                    <article v-for="entry in athleteCockpit.slice(0, 6)" :key="entry.athlete.id || entry.athlete.name" class="rounded-xl border border-border bg-inputBg/40 p-3">
                        <div class="flex items-start justify-between gap-3">
                            <div class="min-w-0">
                                <p class="truncate text-sm font-semibold text-primary">{{ entry.athlete.name }}</p>
                                <p class="mt-1 text-xs text-secondary">{{ entry.latest?.title || tx('auto.Keine letzte Einheit', 'Keine letzte Einheit') }} · {{ formatDate(entry.latest?.performed_at || entry.latest?.created_at) }}</p>
                            </div>
                            <span class="rounded-full bg-muted px-2 py-1 text-xs font-semibold text-secondary">{{ entry.sessions }} {{ tx('auto.Logs', 'Logs') }}</span>
                        </div>
                        <div class="mt-3 grid grid-cols-4 gap-2 text-center text-xs">
                            <span class="rounded-lg border border-border px-2 py-1"><b class="block text-primary">{{ formatDuration(entry.minutes) }}</b>{{ tx('auto.Zeit', 'Zeit') }}</span>
                            <span class="rounded-lg border border-border px-2 py-1"><b class="block text-primary">{{ formatDistance(entry.meters) }}</b>{{ tx('auto.Distanz', 'Distanz') }}</span>
                            <span class="rounded-lg border border-border px-2 py-1"><b class="block text-primary">{{ entry.avgRpe }}</b>RPE</span>
                            <span class="rounded-lg border border-border px-2 py-1"><b class="block text-primary">{{ entry.avgPain }}</b>{{ tx('auto.Schmerz', 'Schmerz') }}</span>
                        </div>
                    </article>
                    <p v-if="!athleteCockpit.length" class="text-sm text-secondary">{{ tx('auto.Noch keine dokumentierten Einheiten für das Cockpit.', 'Noch keine dokumentierten Einheiten für das Cockpit.') }}</p>
                </div>
            </div>

            <div class="rounded-2xl border border-air-blue/30 bg-air-blue/10 p-4">
                <div class="flex items-center justify-between gap-3">
                    <div>
                        <p class="text-xs font-semibold uppercase tracking-wide text-air-blue">{{ tx('auto.KI-Coach', 'KI-Coach') }}</p>
                        <h2 class="mt-1 text-lg font-semibold text-primary">{{ tx('auto.Tipps aus generierten Plänen', 'Tipps aus generierten Plänen') }}</h2>
                    </div>
                    <span class="rounded-full border border-air-blue/30 px-3 py-1 text-xs font-semibold text-air-blue">{{ aiGeneratedPlans.length }} {{ tx('auto.Pläne', 'Pläne') }}</span>
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
                    <p v-if="!aiGeneratedPlanInsights.length" class="text-sm text-secondary">{{ tx('auto.Sobald ein KI-Plan gespeichert ist, erscheinen hier konkrete Analyse- und Verbesserungsvorschläge.', 'Sobald ein KI-Plan gespeichert ist, erscheinen hier konkrete Analyse- und Verbesserungsvorschläge.') }}</p>
                </div>
            </div>

            <div class="rounded-2xl border border-border bg-card p-4">
                <p class="text-xs font-semibold uppercase tracking-wide text-air-blue">{{ tx('auto.Statistik', 'Statistik') }}</p>
                <h2 class="mt-1 text-lg font-semibold text-primary">{{ tx('auto.Sportarten-Verteilung', 'Sportarten-Verteilung') }}</h2>
                <div class="mt-4 space-y-3">
                    <div v-for="row in sportStats.slice(0, 6)" :key="row.key">
                        <div class="flex items-center justify-between text-xs font-semibold">
                            <span class="text-primary">{{ row.label }}</span>
                            <span class="text-secondary">{{ row.sessions }} · {{ formatDuration(row.minutes) }}</span>
                        </div>
                        <div class="mt-1 h-2 overflow-hidden rounded-full bg-muted">
                            <div class="h-full rounded-full bg-air-blue" :style="{ width: `${Math.min(100, row.sessions * 18)}%` }"></div>
                        </div>
                    </div>
                    <p v-if="!sportStats.length" class="text-sm text-secondary">{{ tx('auto.Sobald Trainings gespeichert sind, erscheinen hier Trends.', 'Sobald Trainings gespeichert sind, erscheinen hier Trends.') }}</p>
                </div>
            </div>
        </section>

        <div v-if="activeTrainingSection === 'plans'" class="grid gap-5 xl:grid-cols-[1fr_340px]">
            <section class="space-y-4">
                <div class="flex flex-wrap items-center justify-between gap-3">
                    <div>
                        <p class="text-xs font-semibold uppercase tracking-wide text-secondary">{{ selectedSport.label }}</p>
                        <h2 class="text-xl font-semibold text-primary">{{ tx('Trainingspläne', 'Trainingspläne') }}</h2>
                    </div>
                    <div class="flex flex-wrap gap-2">
                        <button type="button" class="rounded-xl border border-air-blue/50 bg-air-blue/10 px-4 py-2 text-sm font-semibold text-air-blue hover:bg-air-blue/15 disabled:cursor-not-allowed disabled:opacity-50" :disabled="!aiTrainingPlanAvailable" @click="openAiTrainingPlanModal()">
                            KI-Plan
                        </button>
                        <button type="button" class="rounded-xl border border-border px-4 py-2 text-sm font-semibold text-primary hover:bg-muted" @click="openModal('plan')">
                            Neuer Plan
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
                                            {{ plan.status === 'published' ? 'Freigegeben' : 'Entwurf' }}
                                        </span>
                                        <span v-if="plan.settings?.ai_generation" class="rounded-full border border-air-blue/40 bg-air-blue/10 px-2.5 py-1 text-xs font-semibold text-air-blue">
                                            KI-generiert
                                        </span>
                                        <span v-if="plan.settings?.ai_generation?.profile_estimate_mode" class="rounded-full border border-warning/40 bg-warning/10 px-2.5 py-1 text-xs font-semibold text-warning">
                                            Schätzmodus
                                        </span>
                                    </div>
                                    <h3 class="mt-3 truncate text-lg font-semibold text-primary">{{ plan.title }}</h3>
                                    <p class="mt-1 hidden line-clamp-2 text-sm text-secondary md:block">{{ plan.description || tx('auto.Keine Beschreibung hinterlegt.', 'Keine Beschreibung hinterlegt.') }}</p>
                                    <p v-if="plan.settings?.ai_generation?.convincing_explanation" class="mt-2 hidden line-clamp-2 rounded-xl border border-air-blue/25 bg-air-blue/10 px-3 py-2 text-xs text-primary md:block">
                                        Warum so: {{ plan.settings.ai_generation.convincing_explanation }}
                                    </p>
                                    <p v-if="plan.settings?.goal" class="mt-2 rounded-xl border border-air-blue/30 bg-air-blue/10 px-3 py-2 text-xs font-semibold text-primary">
                                        Ziel: {{ plan.settings.goal }}
                                    </p>
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
                                    <p class="truncate text-base font-semibold text-primary">{{ plan.settings?.weeks || '-' }}</p>
                                    <p class="text-[11px] text-secondary">Wochen</p>
                                </div>
                                <div class="rounded-xl border border-border bg-inputBg/50 p-2">
                                    <p class="text-base font-semibold text-primary">{{ plan.settings?.weekly_sessions || '-' }}</p>
                                    <p class="text-[11px] text-secondary">pro Woche</p>
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
                                    Ziel: {{ formatDate(plan.settings.competition_date) }}
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
                                    <span class="text-secondary">Planfortschritt</span>
                                    <span class="text-primary">{{ plan.progress?.percent || 0 }}%</span>
                                </div>
                                <div class="mt-2 h-2 overflow-hidden rounded-full bg-muted">
                                    <div class="h-full rounded-full bg-success" :style="{ width: `${plan.progress?.percent || 0}%` }"></div>
                                </div>
                                <div class="mt-2 flex flex-wrap gap-2 text-[11px] text-secondary">
                                    <span>{{ plan.progress?.completed || 0 }} erledigt</span>
                                    <span>{{ plan.progress?.open || 0 }} offen</span>
                                    <span>{{ plan.progress?.missed || 0 }} Ausfall</span>
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
                                            <p class="mt-1 text-xs text-secondary">{{ item.scheduled_at ? formatDate(item.scheduled_at) : 'offen' }}</p>
                                            <p class="mt-1 text-xs text-secondary">{{ sportLabel(item.sport_type) }} · {{ formatDuration(item.duration_minutes) }}</p>
                                            <div class="mt-2 flex flex-wrap gap-1">
                                                <span v-if="item.metrics?.Woche" class="rounded-full bg-air-blue/10 px-2 py-0.5 text-[11px] font-semibold text-air-blue">Woche {{ item.metrics.Woche }}</span>
                                                <span v-if="item.metrics?.Belastung" class="rounded-full bg-muted px-2 py-0.5 text-[11px] text-secondary">{{ loadLabels[item.metrics.Belastung] || item.metrics.Belastung }}</span>
                                                <span v-if="item.metrics?.Fokus" class="rounded-full bg-muted px-2 py-0.5 text-[11px] text-secondary">{{ item.metrics.Fokus }}</span>
                                            </div>
                                            <div v-if="item.metrics && Object.keys(item.metrics).length" class="mt-2 flex flex-wrap gap-1">
                                                <span v-for="(value, key) in item.metrics" v-show="!['Woche', 'Belastung', 'Fokus', '_training_type', 'training_type', 'Trainingstyp'].includes(key)" :key="key" class="rounded-full bg-muted px-2 py-0.5 text-[11px] text-secondary">
                                                    {{ key }}: {{ value }}
                                                </span>
                                            </div>
                                            <div v-if="plan.can_write" class="mt-3 flex flex-wrap gap-2">
                                                <button type="button" class="rounded-lg border border-border px-2.5 py-1.5 text-[11px] font-semibold text-primary hover:bg-muted" @click="openPlanItem({ ...item, plan })">
                                                    Details
                                                </button>
                                                <button type="button" class="rounded-lg border border-success/40 px-2.5 py-1.5 text-[11px] font-semibold text-success hover:bg-success/10" @click="documentPlanItem(item)">
                                                    Dokumentieren
                                                </button>
                                                <button type="button" class="rounded-lg border border-warning/40 px-2.5 py-1.5 text-[11px] font-semibold text-warning hover:bg-warning/10" @click="openModal('item-missed', plan, item)">
                                                    Ausfall
                                                </button>
                                                <button type="button" class="rounded-lg border border-border px-2.5 py-1.5 text-[11px] font-semibold text-primary hover:bg-muted" @click="openModal('item-edit', plan, item)">
                                                    Bearbeiten
                                                </button>
                                                <button type="button" class="rounded-lg border border-border px-2.5 py-1.5 text-[11px] font-semibold text-primary hover:bg-muted" @click="duplicatePlanItem(plan, item)">
                                                    Kopie
                                                </button>
                                                <button type="button" class="rounded-lg border border-danger/40 px-2.5 py-1.5 text-[11px] font-semibold text-danger hover:bg-danger/10" @click="openModal('item-delete', plan, item)">
                                                    Löschen
                                                </button>
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
                            <button v-if="plan.can_write && plan.settings?.ai_generation && aiTrainingPlanAvailable" type="button" class="rounded-lg border border-air-blue/40 bg-air-blue/10 px-3 py-2 text-xs font-semibold text-air-blue hover:bg-air-blue/15" @click="openAiTrainingPlanModal(plan)">
                                Mit KI anpassen
                            </button>
                            <button v-if="plan.can_write" type="button" class="rounded-lg border border-border px-3 py-2 text-xs font-semibold text-primary hover:bg-muted" @click="duplicatePlan(plan)">
                                Als Vorlage kopieren
                            </button>
                            <button v-if="plan.status !== 'published' && plan.can_write" type="button" class="rounded-lg border border-success/40 px-3 py-2 text-xs font-semibold text-success hover:bg-success/10" @click="publishPlan(plan)">
                                Freigeben
                            </button>
                            <button v-if="plan.can_write" type="button" class="ml-auto rounded-lg border border-danger/40 px-3 py-2 text-xs font-semibold text-danger hover:bg-danger/10" @click="openModal('delete', plan)">
                                Löschen
                            </button>
                        </div>
                    </article>

                    <div v-if="!filteredPlans.length" class="rounded-2xl border border-dashed border-border bg-card p-8 text-center lg:col-span-2">
                        <p class="text-lg font-semibold text-primary">Noch kein Plan für diese Auswahl.</p>
                        <p class="mt-2 text-sm text-secondary">Erstelle den ersten Plan und gib ihn direkt an Sportler oder ein Team frei.</p>
                        <div class="mt-4 flex flex-wrap justify-center gap-2">
                            <button type="button" class="rounded-xl border border-air-blue/50 bg-air-blue/10 px-4 py-2 text-sm font-semibold text-air-blue disabled:opacity-50" :disabled="!aiTrainingPlanAvailable" @click="openAiTrainingPlanModal()">
                                KI-Plan erstellen
                            </button>
                            <button type="button" class="rounded-xl bg-buttonPrimary px-4 py-2 text-sm font-semibold text-buttonTextPrimary" @click="openModal('plan')">
                                Plan manuell erstellen
                            </button>
                        </div>
                    </div>
                </div>
            </section>

            <aside class="space-y-4">
                <section class="rounded-2xl border border-border bg-card p-4">
                    <h2 class="text-base font-semibold text-primary">Letzte Einheiten</h2>
                    <div class="mt-4 space-y-3">
                        <div v-for="log in visibleLogs.slice(0, 6)" :key="log.id" class="flex items-center gap-3 rounded-xl border border-border bg-inputBg/40 p-2">
                            <span class="flex h-12 w-12 items-center justify-center rounded-xl text-white" :class="sportAccent(log.sport_type)">
                                <i :class="sportIcon(log.sport_type)" class="text-xl"></i>
                            </span>
                            <div class="min-w-0">
                                <p class="truncate text-sm font-semibold text-primary">{{ log.title || 'Training' }}</p>
                                <p class="text-xs text-secondary">{{ formatDate(log.performed_at) }} · {{ formatDuration(log.duration_minutes) }} · {{ log.athlete?.name || 'Ich' }}</p>
                            </div>
                        </div>
                        <p v-if="!visibleLogs.length" class="text-sm text-secondary">Noch keine Einheiten dokumentiert.</p>
                    </div>
                </section>

                <section class="rounded-2xl border border-border bg-card p-4">
                    <h2 class="text-base font-semibold text-primary">Sportart-Parameter</h2>
                    <p class="mt-1 text-sm text-secondary">Die Felder im Plan passen sich der gewählten Sportart an.</p>
                    <div class="mt-4 flex flex-wrap gap-2">
                        <span v-for="metric in (selectedSport.metrics || ['Dauer', 'Intensität', 'Todo'])" :key="metric" class="rounded-full border border-border px-3 py-1 text-xs font-semibold text-secondary">
                            {{ metric }}
                        </span>
                    </div>
                </section>

                <section class="rounded-2xl border border-border bg-card p-4">
                    <h2 class="text-base font-semibold text-primary">Vorlagen</h2>
                    <p class="mt-1 text-sm text-secondary">Kopierte oder vorbereitete Pläne können als Startpunkt genutzt werden.</p>
                    <div class="mt-4 space-y-2">
                        <div v-for="plan in templatePlans.slice(0, 4)" :key="plan.id" class="rounded-xl border border-border bg-inputBg/40 p-3">
                            <p class="text-sm font-semibold text-primary">{{ plan.title }}</p>
                            <p class="mt-1 text-xs text-secondary">{{ plan.items?.length || 0 }} Einheiten · {{ phaseLabels[plan.settings?.phase] || 'Phase offen' }}</p>
                            <button type="button" class="mt-2 rounded-lg border border-border px-3 py-1.5 text-xs font-semibold text-primary hover:bg-muted" @click="duplicatePlan(plan)">
                                Wiederverwenden
                            </button>
                        </div>
                        <p v-if="!templatePlans.length" class="text-sm text-secondary">Noch keine Vorlagen vorhanden.</p>
                    </div>
                </section>
            </aside>
        </div>

        <div v-if="activeModal" class="fixed inset-0 z-50 flex items-end justify-center bg-black/70 p-3 sm:items-center" @click.self="closeModal">
            <div class="max-h-[92vh] w-full overflow-y-auto rounded-2xl border border-border bg-bg shadow-2xl" :class="['delete', 'draft-delete', 'item-delete', 'item-missed'].includes(activeModal) ? 'max-w-lg' : 'max-w-4xl'">
                <div class="sticky top-0 z-10 flex items-start justify-between gap-4 border-b border-border bg-bg/95 p-4 backdrop-blur">
                    <div>
                        <p class="text-xs font-semibold uppercase tracking-wide text-air-blue">
                            {{ ['log', 'activity', 'item', 'item-edit', 'item-missed'].includes(activeModal) ? 'Trainingseinheit' : ['delete', 'draft-delete', 'item-delete'].includes(activeModal) ? 'Bestätigen' : 'Trainingsplan' }}
                        </p>
                        <h2 class="mt-1 text-xl font-semibold text-primary">
                            {{ activeModal === 'ai-plan' ? 'KI-Plan erstellen' : activeModal === 'plan' ? 'Plan erstellen' : activeModal === 'log' ? 'Training dokumentieren' : activeModal === 'activity' ? 'Einheit eintragen' : activeModal === 'edit' ? 'Plan bearbeiten & freigeben' : activeModal === 'item' ? 'Einheit zum Plan hinzufügen' : activeModal === 'item-edit' ? 'Einheit bearbeiten' : activeModal === 'item-missed' ? 'Ausfall melden' : activeModal === 'item-delete' ? 'Einheit löschen' : activeModal === 'draft-delete' ? 'Training-Entwurf verwerfen' : 'Trainingsplan löschen' }}
                        </h2>
                    </div>
                    <button type="button" class="rounded-xl border border-border px-3 py-2 text-sm font-semibold text-primary hover:bg-muted" @click="closeModal">
                        Schließen
                    </button>
                </div>

                <form v-if="activeModal === 'log'" class="space-y-5 p-4" @submit.prevent="submitLog">
                    <div class="grid gap-4 md:grid-cols-2">
                        <label v-if="athleteOptions.length > 1" class="block text-sm font-semibold text-primary">Sportler
                            <select v-model="logForm.user_id" class="mt-2 w-full rounded-xl border border-border bg-inputBg px-3 py-2 text-primary">
                                <option v-for="athlete in athleteOptions" :key="athlete.id || 'self'" :value="athlete.id">{{ athlete.name }}</option>
                            </select>
                        </label>
                        <label class="block text-sm font-semibold text-primary">Geplante Einheit
                            <select v-model="logForm.training_plan_item_id" class="mt-2 w-full rounded-xl border border-border bg-inputBg px-3 py-2 text-primary" @change="applySelectedPlanItem">
                                <option value="">Spontanes Training</option>
                                <option v-for="item in plannedLogItems" :key="item.id" :value="item.id">
                                    {{ item.plan.title }} · {{ item.title }}{{ item.scheduled_at ? ` · ${formatDate(item.scheduled_at)}` : '' }}
                                </option>
                            </select>
                        </label>
                        <label class="block text-sm font-semibold text-primary md:col-span-2">Titel
                            <input v-model="logForm.title" class="mt-2 w-full rounded-xl border border-border bg-inputBg px-3 py-2 text-primary" required />
                        </label>
                        <label class="block text-sm font-semibold text-primary">Sportart
                            <select v-model="logForm.sport_type" class="mt-2 w-full rounded-xl border border-border bg-inputBg px-3 py-2 text-primary">
                                <option v-for="sport in sportChoices" :key="sport.key" :value="sport.key">{{ sport.label }}</option>
                            </select>
                        </label>
                        <label class="block text-sm font-semibold text-primary">Status
                            <select v-model="logForm.status" class="mt-2 w-full rounded-xl border border-border bg-inputBg px-3 py-2 text-primary" @change="setLogStatus">
                                <option value="completed">Abgeschlossen</option>
                                <option value="in_progress">Läuft gerade</option>
                                <option value="planned">Geplant</option>
                            </select>
                        </label>
                        <label class="block text-sm font-semibold text-primary">Zeitpunkt
                            <input v-model="logForm.performed_at" type="datetime-local" class="mt-2 w-full rounded-xl border border-border bg-inputBg px-3 py-2 text-primary" />
                        </label>
                        <label class="block text-sm font-semibold text-primary">Intensität
                            <select v-model="logForm.intensity" class="mt-2 w-full rounded-xl border border-border bg-inputBg px-3 py-2 text-primary">
                                <option value="">Keine Angabe</option>
                                <option value="locker">Locker</option>
                                <option value="mittel">Mittel</option>
                                <option value="hart">Hart</option>
                                <option value="recovery">Regeneration</option>
                            </select>
                        </label>
                        <label class="block text-sm font-semibold text-primary">Dauer in Minuten
                            <input v-model="logForm.duration_minutes" type="number" min="0" class="mt-2 w-full rounded-xl border border-border bg-inputBg px-3 py-2 text-primary" />
                        </label>
                        <label class="block text-sm font-semibold text-primary">Distanz in km
                            <input v-model="logForm.distance_km" type="number" min="0" step="0.01" class="mt-2 w-full rounded-xl border border-border bg-inputBg px-3 py-2 text-primary" />
                        </label>
                        <label class="block text-sm font-semibold text-primary">Kalorien
                            <input v-model="logForm.calories" type="number" min="0" class="mt-2 w-full rounded-xl border border-border bg-inputBg px-3 py-2 text-primary" />
                        </label>
                        <label class="block text-sm font-semibold text-primary md:col-span-2">Notizen
                            <textarea v-model="logForm.notes" rows="3" class="mt-2 w-full rounded-xl border border-border bg-inputBg px-3 py-2 text-primary" placeholder="Gefühl, Technik, Schmerzen, Besonderheiten" />
                        </label>
                        <label v-if="logForm.user_id" class="block text-sm font-semibold text-primary md:col-span-2">Trainer-Hinweis
                            <textarea v-model="logForm.trainer_feedback" rows="3" class="mt-2 w-full rounded-xl border border-border bg-inputBg px-3 py-2 text-primary" placeholder="Hinweise, Korrekturen oder Fokus für die nächste Einheit" />
                        </label>
                    </div>

                    <div class="rounded-2xl border border-border bg-inputBg/40 p-4">
                        <div class="flex flex-wrap items-center justify-between gap-3">
                            <h3 class="text-sm font-semibold uppercase tracking-wide text-secondary">Übungen / Werte</h3>
                            <button type="button" class="rounded-xl border border-border px-3 py-2 text-sm font-semibold text-primary hover:bg-muted" @click="addLogEntry">
                                Zeile hinzufügen
                            </button>
                        </div>
                        <div class="mt-4 space-y-3">
                            <div v-for="(entry, index) in logForm.entries" :key="index" class="grid gap-3 rounded-xl border border-border p-3 lg:grid-cols-6">
                                <label class="block text-sm font-semibold text-primary lg:col-span-2">Übung / Abschnitt
                                    <input v-model="entry.title" class="mt-2 w-full rounded-xl border border-border bg-inputBg px-3 py-2 text-primary" placeholder="z. B. Kniebeugen, 5-km-Lauf, Technikdrill" />
                                </label>
                                <label class="block text-sm font-semibold text-primary">Sätze
                                    <input v-model="entry.sets" type="number" min="0" class="mt-2 w-full rounded-xl border border-border bg-inputBg px-3 py-2 text-primary" />
                                </label>
                                <label class="block text-sm font-semibold text-primary">Wdh.
                                    <input v-model="entry.reps" type="number" min="0" class="mt-2 w-full rounded-xl border border-border bg-inputBg px-3 py-2 text-primary" />
                                </label>
                                <label class="block text-sm font-semibold text-primary">kg
                                    <input v-model="entry.weight_kg" type="number" min="0" step="0.01" class="mt-2 w-full rounded-xl border border-border bg-inputBg px-3 py-2 text-primary" />
                                </label>
                                <label class="block text-sm font-semibold text-primary">Zeit min
                                    <input v-model="entry.duration_minutes" type="number" min="0" step="0.1" class="mt-2 w-full rounded-xl border border-border bg-inputBg px-3 py-2 text-primary" />
                                </label>
                                <label class="block text-sm font-semibold text-primary">Distanz km
                                    <input v-model="entry.distance_km" type="number" min="0" step="0.01" class="mt-2 w-full rounded-xl border border-border bg-inputBg px-3 py-2 text-primary" />
                                </label>
                                <label class="block text-sm font-semibold text-primary lg:col-span-4">Kommentar
                                    <input v-model="entry.notes" class="mt-2 w-full rounded-xl border border-border bg-inputBg px-3 py-2 text-primary" />
                                </label>
                                <button type="button" class="self-end rounded-xl border border-border px-3 py-2 text-sm font-semibold text-danger hover:bg-danger/10" @click="removeLogEntry(index)">
                                    Entfernen
                                </button>
                            </div>
                        </div>
                    </div>

                    <button type="submit" class="w-full rounded-xl bg-buttonPrimary px-4 py-2 text-sm font-semibold text-buttonTextPrimary disabled:opacity-60" :disabled="logForm.processing">
                        Training speichern
                    </button>
                </form>

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

                <form v-if="activeModal === 'ai-plan'" class="space-y-4 p-4" @submit.prevent="aiPlanStep < 2 ? generateAiTrainingPlan(false) : saveAiTrainingPlan()">
                    <div class="grid grid-cols-3 gap-2">
                        <button
                            v-for="(step, index) in aiPlanSteps"
                            :key="step.label"
                            type="button"
                            class="rounded-2xl border p-3 text-left transition"
                            :class="[
                                aiPlanStep === index ? 'border-air-blue bg-air-blue text-white shadow-lg shadow-air-blue/20' : 'border-border bg-card text-primary hover:bg-muted',
                                !canOpenAiPlanStep(index) ? 'cursor-not-allowed opacity-50' : '',
                            ]"
                            :disabled="!canOpenAiPlanStep(index)"
                            @click="aiPlanStep = index"
                        >
                            <span class="flex items-center gap-2 text-[11px] font-semibold uppercase tracking-wide" :class="aiPlanStep === index ? 'text-white/80' : 'text-secondary'">
                                <i :class="step.icon"></i>
                                Schritt {{ index + 1 }}
                            </span>
                            <span class="mt-2 block text-sm font-semibold">{{ step.label }}</span>
                            <span class="hidden text-xs opacity-80 sm:block">{{ step.hint }}</span>
                        </button>
                    </div>

                    <div v-if="aiTrainingPlanError || aiTrainingPlanMessage" class="rounded-xl border px-4 py-3 text-sm font-semibold" :class="aiTrainingPlanError ? 'border-danger/40 bg-danger/10 text-danger' : 'border-success/40 bg-success/10 text-success'">
                        {{ aiTrainingPlanError || aiTrainingPlanMessage }}
                    </div>

                    <div v-if="aiProfileMissingFields.length" class="rounded-2xl border border-warning/40 bg-warning/10 p-4 text-sm">
                        <div class="flex flex-col gap-4 lg:flex-row lg:items-start lg:justify-between">
                            <div class="min-w-0">
                                <p class="text-xs font-semibold uppercase tracking-wide text-warning">Sportprofil ergänzen</p>
                                <h3 class="mt-1 text-base font-semibold text-primary">Möchtest du die Daten vor dem Generieren nachtragen?</h3>
                                <p class="mt-2 max-w-3xl text-sm text-primary">{{ aiProfileMissingMessage }}</p>
                                <div class="mt-3 grid gap-2 sm:grid-cols-2">
                                    <div v-for="field in aiProfileMissingFields" :key="field.key" class="rounded-xl border border-warning/30 bg-bg/70 px-3 py-2">
                                        <p class="text-xs font-semibold text-warning">{{ field.label }}</p>
                                        <p v-if="field.help" class="mt-1 text-xs text-secondary">{{ field.help }}</p>
                                    </div>
                                </div>
                                <p class="mt-3 text-xs text-secondary">
                                    Wenn du die Werte nicht kennst, kann Airmius trotzdem starten, aber nur vorsichtig und allgemeiner. Präziser wird es erst mit nachgetragenen Leistungsdaten.
                                </p>
                            </div>
                            <div class="flex shrink-0 flex-col gap-2 sm:flex-row lg:flex-col">
                                <Link
                                    :href="aiProfileCompletionUrl"
                                    class="inline-flex items-center justify-center rounded-xl bg-buttonPrimary px-4 py-2 text-sm font-semibold text-buttonTextPrimary"
                                >
                                    Daten jetzt nachtragen
                                </Link>
                                <button
                                    v-if="aiProfileEstimateAllowed"
                                    type="button"
                                    class="inline-flex items-center justify-center rounded-xl border border-warning/50 px-4 py-2 text-sm font-semibold text-warning hover:bg-warning/10 disabled:opacity-60"
                                    :disabled="aiTrainingPlanGenerating"
                                    @click="generateAiTrainingPlanWithProfileEstimates"
                                >
                                    Ich kenne sie nicht - vorsichtig generieren
                                </button>
                            </div>
                        </div>
                    </div>

                    <section v-if="aiPlanStep === 0" class="rounded-2xl border border-border bg-card p-4">
                        <div class="flex flex-wrap items-center justify-between gap-3">
                            <div>
                                <p class="text-xs font-semibold uppercase tracking-wide text-air-blue">KI-Planung</p>
                                <h3 class="mt-1 text-lg font-semibold text-primary">{{ aiPlanSourcePlan ? 'Plan mit KI anpassen' : 'Ziel festlegen' }}</h3>
                            </div>
                            <span class="rounded-full border border-border px-3 py-1 text-xs font-semibold text-secondary">{{ aiTrainingProviderLabel }}</span>
                        </div>

                        <div class="mt-3 rounded-xl border border-air-blue/30 bg-air-blue/10 px-3 py-2 text-sm text-primary">
                            <span class="font-semibold">{{ aiPlanLimitLabel }}</span>
                            <span v-if="aiTrainingPlan.access_reason" class="mt-1 block text-danger">{{ aiTrainingPlan.access_reason }}</span>
                        </div>

                        <p v-if="aiPlanSourcePlan" class="mt-3 rounded-xl border border-air-blue/30 bg-air-blue/10 px-3 py-2 text-sm text-primary">
                            Ausgangsplan: {{ aiPlanSourcePlan.title }}. Die KI erstellt eine neue bestätigbare Version, damit dein alter Plan nachvollziehbar bleibt.
                        </p>

                        <div class="mt-4 grid gap-4">
                            <label class="block text-sm font-semibold text-primary">Planname
                                <input v-model="aiPlanForm.title" class="mt-2 w-full rounded-xl border border-border bg-inputBg px-3 py-3 text-primary" placeholder="z. B. 5-km Comeback, Hyrox Aufbau, Oberkörper Kraft" />
                            </label>

                            <label class="block text-sm font-semibold text-primary">Trainingsziel
                                <textarea v-model="aiPlanForm.goal" rows="3" class="mt-2 w-full rounded-xl border border-border bg-inputBg px-3 py-3 text-primary" placeholder="Was soll der Plan erreichen? Beispiel: 6 Wochen, 5 km schneller laufen, 3 Einheiten pro Woche, Knie schonen." required />
                            </label>

                            <div>
                                <p class="text-sm font-semibold text-primary">Sportart</p>
                                <p class="mt-1 text-xs text-secondary">Die KI kombiniert die passenden Schwerpunkte wie Grundlage, Tempo, Technik, Kraft und Regeneration automatisch.</p>
                                <div class="mt-2 grid grid-cols-2 gap-2 sm:grid-cols-3">
                                    <button
                                        v-for="sport in aiPlanSportChoices"
                                        :key="sport.key"
                                        type="button"
                                        class="rounded-xl border px-3 py-3 text-left text-sm font-semibold transition"
                                        :class="aiPlanForm.sport_type === sport.key ? 'border-air-blue bg-air-blue/10 text-air-blue' : 'border-border bg-inputBg text-primary hover:bg-muted'"
                                        @click="selectAiPlanSportType(sport.key)"
                                    >
                                        <span :class="['mb-2 block h-1.5 w-8 rounded-full', sport.accent]"></span>
                                        <i :class="sport.icon" class="mr-2"></i>{{ sport.label }}
                                    </button>
                                </div>
                                <div class="mt-3 rounded-xl border border-air-blue/30 bg-air-blue/10 px-3 py-2 text-xs font-semibold text-primary">
                                    Ausgewogene Planung aktiv: Die KI ordnet jede Einheit automatisch einem passenden Typ zu, z. B. Long Run, Intervalle, Technik oder Regeneration.
                                </div>
                            </div>
                        </div>
                    </section>

                    <section v-if="aiPlanStep === 1" class="rounded-2xl border border-border bg-card p-4">
                        <p class="text-xs font-semibold uppercase tracking-wide text-air-blue">Rahmen</p>
                        <h3 class="mt-1 text-lg font-semibold text-primary">Damit der Plan wirklich passt</h3>

                        <div class="mt-4 grid gap-4 md:grid-cols-2">
                            <label class="block text-sm font-semibold text-primary">Niveau
                                <select v-model="aiPlanForm.level" class="mt-2 w-full rounded-xl border border-border bg-inputBg px-3 py-3 text-primary">
                                    <option value="beginner">Einsteiger</option>
                                    <option value="intermediate">Fortgeschritten</option>
                                    <option value="advanced">Advanced</option>
                                    <option value="elite">Leistung</option>
                                </select>
                            </label>
                            <label class="block text-sm font-semibold text-primary">Phase
                                <select v-model="aiPlanForm.phase" class="mt-2 w-full rounded-xl border border-border bg-inputBg px-3 py-3 text-primary">
                                    <option value="base">Grundlage</option>
                                    <option value="build">Aufbau</option>
                                    <option value="peak">Peak</option>
                                    <option value="recovery">Regeneration</option>
                                    <option value="rehab">Reha / Wiedereinstieg</option>
                                </select>
                            </label>
                            <div class="md:col-span-2">
                                <p class="text-sm font-semibold text-primary">Planlänge</p>
                                <div class="mt-2 grid grid-cols-2 gap-2 sm:grid-cols-4">
                                    <button
                                        v-for="preset in aiPlanDurationPresets"
                                        :key="preset.weeks"
                                        type="button"
                                        class="rounded-xl border px-3 py-3 text-left transition"
                                        :class="[
                                            Number(aiPlanForm.weeks) === Math.min(preset.weeks, aiPlanMaxWeeks) ? 'border-air-blue bg-air-blue/10 text-air-blue' : 'border-border bg-inputBg text-primary hover:bg-muted',
                                            preset.weeks > aiPlanMaxWeeks ? 'opacity-50' : '',
                                        ]"
                                        @click="setAiPlanDurationPreset(preset.weeks)"
                                    >
                                        <span class="block text-sm font-semibold">{{ preset.label }}</span>
                                        <span class="mt-1 block text-xs text-secondary">{{ preset.hint }}</span>
                                        <span v-if="preset.weeks > aiPlanMaxWeeks" class="mt-1 block text-[11px] text-danger">ab nächster Stufe</span>
                                    </button>
                                </div>
                            </div>
                            <label class="block text-sm font-semibold text-primary">Wochen
                                <input v-model="aiPlanForm.weeks" type="number" min="1" :max="aiPlanMaxWeeks" class="mt-2 w-full rounded-xl border border-border bg-inputBg px-3 py-3 text-primary" />
                            </label>
                            <label class="block text-sm font-semibold text-primary">Einheiten pro Woche
                                <input v-model="aiPlanForm.sessions_per_week" type="number" min="1" max="6" class="mt-2 w-full rounded-xl border border-border bg-inputBg px-3 py-3 text-primary" />
                            </label>
                            <div class="md:col-span-2 rounded-xl border px-3 py-2 text-sm" :class="aiPlanTooLarge || aiPlanWeeksTooLong ? 'border-danger/40 bg-danger/10 text-danger' : 'border-border bg-inputBg/40 text-secondary'">
                                {{ aiPlanRequestedItems }} geplante Einheiten · maximal {{ aiPlanMaxItems }} pro KI-Plan · maximal {{ aiPlanMaxWeeks }} Wochen in deiner Stufe.
                            </div>
                            <label class="block text-sm font-semibold text-primary">Dauer je Einheit
                                <input v-model="aiPlanForm.duration_minutes" type="number" min="10" max="240" class="mt-2 w-full rounded-xl border border-border bg-inputBg px-3 py-3 text-primary" />
                            </label>
                            <label class="block text-sm font-semibold text-primary">Startdatum
                                <input v-model="aiPlanForm.starts_on" type="date" class="mt-2 w-full rounded-xl border border-border bg-inputBg px-3 py-3 text-primary" />
                            </label>
                            <label class="block text-sm font-semibold text-primary md:col-span-2">Equipment / Ort
                                <input v-model="aiPlanForm.equipment" class="mt-2 w-full rounded-xl border border-border bg-inputBg px-3 py-3 text-primary" placeholder="z. B. Kurzhanteln, Laufbahn, Gym, kein Gerät" />
                            </label>
                            <label class="block text-sm font-semibold text-primary md:col-span-2">Einschränkungen
                                <textarea v-model="aiPlanForm.constraints" rows="3" class="mt-2 w-full rounded-xl border border-border bg-inputBg px-3 py-3 text-primary" placeholder="Verletzungen, Zeitfenster, Pausentage, Dinge die vermieden werden sollen" />
                            </label>
                            <label class="block text-sm font-semibold text-primary md:col-span-2">Vorlieben
                                <textarea v-model="aiPlanForm.preferences" rows="3" class="mt-2 w-full rounded-xl border border-border bg-inputBg px-3 py-3 text-primary" placeholder="Lieblingsübungen, bevorzugte Tage, Fokus, Stil" />
                            </label>
                            <label v-if="aiPlanSourcePlan || aiTrainingPlanPreview" class="block text-sm font-semibold text-primary md:col-span-2">Was soll die KI ändern?
                                <textarea v-model="aiPlanForm.revision_instruction" rows="3" class="mt-2 w-full rounded-xl border border-air-blue/40 bg-air-blue/10 px-3 py-3 text-primary" placeholder="z. B. weniger Umfang, mehr Kraft, Dienstag frei lassen, Intervalle kürzer machen" />
                            </label>
                        </div>
                    </section>

                    <section v-if="aiPlanStep === 2" class="space-y-4">
                        <div v-if="aiTrainingPlanPreview" class="rounded-2xl border border-air-blue/30 bg-air-blue/10 p-4">
                            <div class="flex flex-wrap items-start justify-between gap-3">
                                <div>
                                    <p class="text-xs font-semibold uppercase tracking-wide text-air-blue">Vorschlag prüfen</p>
                                    <h3 class="mt-1 text-xl font-semibold text-primary">{{ aiTrainingPlanPreview.title }}</h3>
                                    <p class="mt-2 max-w-3xl text-sm text-secondary">{{ aiTrainingPlanPreview.summary }}</p>
                                </div>
                                <span class="rounded-full border border-air-blue/40 px-3 py-1 text-xs font-semibold text-air-blue">
                                    {{ aiTrainingPlanPreview.items?.length || 0 }} Einheiten
                                </span>
                                <span v-if="aiTrainingPlanPreview.profile_estimate_mode" class="rounded-full border border-warning/40 bg-warning/10 px-3 py-1 text-xs font-semibold text-warning">
                                    Schätzmodus
                                </span>
                            </div>
                            <div class="mt-4 rounded-xl border border-air-blue/25 bg-bg/50 p-3">
                                <p class="text-xs font-semibold uppercase tracking-wide text-air-blue">Warum genau so?</p>
                                <p class="mt-1 text-sm text-primary">{{ aiTrainingPlanPreview.convincing_explanation }}</p>
                            </div>
                            <div v-if="aiTrainingPlanPreview.profile_estimate_mode" class="mt-3 rounded-xl border border-warning/30 bg-warning/10 p-3 text-sm text-primary">
                                <p class="font-semibold text-warning">Mit Schätzungen erstellt</p>
                                <p class="mt-1 text-xs text-secondary">Einige Leistungsdaten fehlen oder wurden als unbekannt markiert. Der Vorschlag ist deshalb bewusst vorsichtig und sollte vor dem Speichern genauer geprüft werden.</p>
                            </div>
                        </div>

                        <div v-if="aiTrainingPlanPreview?.quality_check" class="rounded-2xl border border-border bg-card p-4">
                            <div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
                                <div>
                                    <p class="text-xs font-semibold uppercase tracking-wide text-air-blue">Airmius-Regelwerk</p>
                                    <h4 class="mt-1 text-lg font-semibold text-primary">Technische Prüfung</h4>
                                    <p class="mt-1 text-sm text-secondary">
                                        Airmius prüft Umfang, Steigerung, Belastung, Regeneration und sportartspezifische Risiken.
                                    </p>
                                </div>
                                <div class="flex flex-wrap gap-2">
                                    <span class="rounded-full border px-3 py-1 text-xs font-semibold" :class="qualityRiskClass(aiTrainingPlanPreview.quality_check.risk)">
                                        Risiko: {{ aiTrainingPlanPreview.quality_check.risk }}
                                    </span>
                                    <span class="rounded-full border border-air-blue/40 bg-air-blue/10 px-3 py-1 text-xs font-semibold text-air-blue">
                                        {{ aiTrainingPlanPreview.quality_check.score }} / 100
                                    </span>
                                </div>
                            </div>

                            <div class="mt-4 grid gap-2 md:grid-cols-2">
                                <div
                                    v-for="check in aiTrainingPlanPreview.quality_check.checks || []"
                                    :key="check.key"
                                    class="rounded-xl border px-3 py-2"
                                    :class="qualityStatusClass(check.status)"
                                >
                                    <p class="text-sm font-semibold">{{ check.label }}</p>
                                    <p class="mt-1 text-xs opacity-90">{{ check.message }}</p>
                                </div>
                            </div>

                            <div v-if="(aiTrainingPlanPreview.quality_check.warnings || []).length || (aiTrainingPlanPreview.quality_check.suggestions || []).length" class="mt-4 grid gap-3 lg:grid-cols-2">
                                <section v-if="(aiTrainingPlanPreview.quality_check.warnings || []).length" class="rounded-xl border border-warning/30 bg-warning/10 p-3">
                                    <p class="text-xs font-semibold uppercase tracking-wide text-warning">Warnungen</p>
                                    <ul class="mt-2 space-y-1 text-xs text-primary">
                                        <li v-for="warning in aiTrainingPlanPreview.quality_check.warnings" :key="warning">- {{ warning }}</li>
                                    </ul>
                                </section>
                                <section v-if="(aiTrainingPlanPreview.quality_check.suggestions || []).length" class="rounded-xl border border-success/30 bg-success/10 p-3">
                                    <p class="text-xs font-semibold uppercase tracking-wide text-success">Verbesserungen</p>
                                    <ul class="mt-2 space-y-1 text-xs text-primary">
                                        <li v-for="suggestion in aiTrainingPlanPreview.quality_check.suggestions" :key="suggestion">- {{ suggestion }}</li>
                                    </ul>
                                </section>
                            </div>
                        </div>

                        <div v-if="aiTrainingPlanPreview" class="grid gap-4 lg:grid-cols-2">
                            <section class="rounded-2xl border border-border bg-card p-4">
                                <h4 class="text-sm font-semibold uppercase tracking-wide text-secondary">Planlogik</h4>
                                <ul class="mt-3 space-y-2 text-sm text-primary">
                                    <li v-for="reason in aiTrainingPlanPreview.progression_logic || []" :key="reason" class="rounded-xl border border-border bg-inputBg/40 px-3 py-2">{{ reason }}</li>
                                </ul>
                            </section>
                            <section class="rounded-2xl border border-border bg-card p-4">
                                <h4 class="text-sm font-semibold uppercase tracking-wide text-secondary">Analyse-Tipps</h4>
                                <ul class="mt-3 space-y-2 text-sm text-primary">
                                    <li v-for="tip in aiTrainingPlanPreview.analysis_tips || []" :key="tip" class="rounded-xl border border-border bg-inputBg/40 px-3 py-2">{{ tip }}</li>
                                    <li v-if="!(aiTrainingPlanPreview.analysis_tips || []).length" class="text-secondary">Keine zusätzlichen Tipps.</li>
                                </ul>
                            </section>
                        </div>

                        <div v-if="aiTrainingPlanPreview" class="rounded-2xl border border-border bg-card p-4">
                            <div class="flex flex-wrap items-center justify-between gap-3">
                                <div>
                                    <p class="text-xs font-semibold uppercase tracking-wide text-secondary">Trainingseinheiten</p>
                                    <h4 class="text-lg font-semibold text-primary">Nach dem Speichern normal bearbeitbar</h4>
                                </div>
                                <button type="button" class="rounded-xl border border-air-blue/40 px-3 py-2 text-sm font-semibold text-air-blue hover:bg-air-blue/10" :disabled="aiTrainingPlanGenerating" @click="generateAiTrainingPlan(true)">
                                    KI überarbeiten
                                </button>
                            </div>

                            <label class="mt-4 block text-sm font-semibold text-primary">Änderungswunsch an die KI
                                <textarea v-model="aiPlanForm.revision_instruction" rows="2" class="mt-2 w-full rounded-xl border border-border bg-inputBg px-3 py-3 text-primary" placeholder="z. B. Einheit 3 leichter machen, mehr Gym-Sätze, Laufumfang reduzieren" />
                            </label>

                            <div class="mt-4 space-y-2">
                                <article v-for="(item, index) in aiTrainingPlanPreview.items || []" :key="`${item.title}-${index}`" class="rounded-xl border border-border bg-inputBg/40 p-3">
                                    <div class="flex flex-wrap items-start justify-between gap-2">
                                        <div class="min-w-0">
                                            <p class="text-xs font-semibold uppercase tracking-wide text-air-blue">Woche {{ item.week || '-' }} · {{ item.day || 'Termin offen' }}</p>
                                            <h5 class="mt-1 text-sm font-semibold text-primary">{{ item.title }}</h5>
                                            <p class="mt-1 text-xs text-secondary">{{ item.description }}</p>
                                        </div>
                                        <span class="rounded-full bg-muted px-2.5 py-1 text-xs font-semibold text-secondary">{{ sportLabel(item.sport_type) }}</span>
                                    </div>
                                    <div class="mt-3 flex flex-wrap gap-1.5 text-[11px] text-secondary">
                                        <span v-if="item.duration_minutes" class="rounded-full bg-bg px-2 py-1">{{ item.duration_minutes }} min</span>
                                        <span v-if="item.distance_km" class="rounded-full bg-bg px-2 py-1">{{ item.distance_km }} km</span>
                                        <span v-if="item.intensity" class="rounded-full bg-bg px-2 py-1">{{ item.intensity }}</span>
                                        <span v-for="(value, key) in item.metrics || {}" :key="key" class="rounded-full bg-bg px-2 py-1">{{ key }}: {{ value }}</span>
                                    </div>
                                </article>
                            </div>
                        </div>
                    </section>

                    <div class="sticky bottom-0 -mx-4 -mb-4 flex items-center justify-between gap-3 border-t border-border bg-bg/95 p-4 backdrop-blur">
                        <button type="button" class="rounded-xl border border-border px-4 py-3 text-sm font-semibold text-primary disabled:opacity-40" :disabled="aiPlanStep === 0 || aiTrainingPlanGenerating || aiTrainingPlanSaving" @click="aiPlanStep = Math.max(0, aiPlanStep - 1)">
                            Zurück
                        </button>
                        <button v-if="aiPlanStep < 2" type="button" class="rounded-xl bg-buttonPrimary px-5 py-3 text-sm font-semibold text-buttonTextPrimary disabled:opacity-60" :disabled="aiTrainingPlanGenerating || !aiPlanForm.goal?.trim() || (aiPlanStep === 1 && aiPlanCannotGenerate)" @click="continueAiTrainingPlan">
                            {{ aiPlanStep === 0 ? 'Weiter' : aiTrainingPlanGenerating ? 'KI arbeitet...' : 'Plan generieren' }}
                        </button>
                        <button v-else type="button" class="rounded-xl bg-buttonPrimary px-5 py-3 text-sm font-semibold text-buttonTextPrimary disabled:opacity-60" :disabled="aiTrainingPlanSaving || !aiTrainingPlanPreview" @click="saveAiTrainingPlan">
                            {{ aiTrainingPlanSaving ? 'Speichert...' : 'Plan bestätigen & speichern' }}
                        </button>
                    </div>
                </form>

                <form v-if="activeModal === 'plan'" class="space-y-4 p-4" @submit.prevent="submitPlan">
                    <div class="grid grid-cols-2 gap-2 md:grid-cols-4">
                        <button
                            v-for="(step, index) in planWizardSteps"
                            :key="step.label"
                            type="button"
                            class="rounded-2xl border p-3 text-left transition"
                            :class="[
                                planWizardStep === index ? 'border-air-blue bg-air-blue text-white shadow-lg shadow-air-blue/20' : 'border-border bg-card text-primary hover:bg-muted',
                                !canOpenPlanWizardStep(index) ? 'cursor-not-allowed opacity-50' : '',
                            ]"
                            :disabled="!canOpenPlanWizardStep(index)"
                            @click="goToPlanWizardStep(index)"
                        >
                            <span class="flex items-center gap-2 text-[11px] font-semibold uppercase tracking-wide" :class="planWizardStep === index ? 'text-white/80' : 'text-secondary'">
                                <i :class="step.icon"></i>
                                Schritt {{ index + 1 }}
                            </span>
                            <span class="mt-2 block text-sm font-semibold">{{ step.label }}</span>
                            <span class="hidden text-xs opacity-80 sm:block">{{ step.hint }}</span>
                        </button>
                    </div>

                    <section v-if="planWizardStep === 0" class="rounded-2xl border border-border bg-card p-4">
                        <p class="text-xs font-semibold uppercase tracking-wide text-air-blue">Schritt 1</p>
                        <h3 class="mt-1 text-lg font-semibold text-primary">Plan-Basis</h3>
                        <p class="mt-1 text-sm text-secondary">Erst nur das Wichtigste: Name und Rhythmus.</p>

                        <div class="mt-4 space-y-4">
                            <label class="block text-sm font-semibold text-primary">Planname
                                <input v-model="planForm.title" class="mt-2 w-full rounded-xl border border-border bg-inputBg px-3 py-3 text-primary" placeholder="z. B. 10k Aufbau, Gym Push/Pull, Comeback" required />
                            </label>

                            <div>
                                <p class="text-sm font-semibold text-primary">Rhythmus</p>
                                <div class="mt-2 grid grid-cols-2 gap-2 sm:grid-cols-4">
                                    <button
                                        v-for="option in [
                                            { value: 'single', label: 'Einmalig', icon: 'las la-calendar-day' },
                                            { value: 'daily', label: 'Täglich', icon: 'las la-redo' },
                                            { value: 'weekly', label: 'Wöchentlich', icon: 'las la-calendar-week' },
                                            { value: 'monthly', label: 'Monatlich', icon: 'las la-calendar-alt' },
                                        ]"
                                        :key="option.value"
                                        type="button"
                                        class="rounded-xl border px-3 py-3 text-left text-sm font-semibold transition"
                                        :class="planForm.cadence === option.value ? 'border-air-blue bg-air-blue/10 text-air-blue' : 'border-border bg-inputBg text-primary hover:bg-muted'"
                                        @click="planForm.cadence = option.value"
                                    >
                                        <i :class="option.icon" class="mr-2"></i>{{ option.label }}
                                    </button>
                                </div>
                            </div>
                        </div>
                    </section>

                    <section v-if="planWizardStep === 1" class="rounded-2xl border border-border bg-card p-4">
                        <p class="text-xs font-semibold uppercase tracking-wide text-air-blue">Schritt 2</p>
                        <h3 class="mt-1 text-lg font-semibold text-primary">Ziel und Zeitraum</h3>
                        <p class="mt-1 text-sm text-secondary">Alles hier ist optional, hilft aber bei Struktur und Auswertung.</p>

                        <div class="mt-4 grid gap-4 md:grid-cols-2">
                            <label class="block text-sm font-semibold text-primary md:col-span-2">Ziel des Plans
                                <input v-model="planForm.goal" class="mt-2 w-full rounded-xl border border-border bg-inputBg px-3 py-3 text-primary" placeholder="z. B. 10 km unter 45 Minuten, Muskelaufbau, Wiedereinstieg" />
                            </label>
                            <label class="block text-sm font-semibold text-primary">Trainingsphase
                                <select v-model="planForm.phase" class="mt-2 w-full rounded-xl border border-border bg-inputBg px-3 py-3 text-primary">
                                    <option value="base">Grundlage</option>
                                    <option value="build">Aufbau</option>
                                    <option value="peak">Peak / Wettkampfnähe</option>
                                    <option value="recovery">Regeneration</option>
                                    <option value="rehab">Reha / Wiedereinstieg</option>
                                </select>
                            </label>
                            <label class="block text-sm font-semibold text-primary">Niveau
                                <select v-model="planForm.level" class="mt-2 w-full rounded-xl border border-border bg-inputBg px-3 py-3 text-primary">
                                    <option value="beginner">Einsteiger</option>
                                    <option value="intermediate">Fortgeschritten</option>
                                    <option value="advanced">Advanced</option>
                                    <option value="elite">Leistung</option>
                                </select>
                            </label>
                            <label class="block text-sm font-semibold text-primary">Start
                                <input v-model="planForm.starts_on" type="date" class="mt-2 w-full rounded-xl border border-border bg-inputBg px-3 py-3 text-primary" />
                            </label>
                            <label class="block text-sm font-semibold text-primary">Ende
                                <input v-model="planForm.ends_on" type="date" class="mt-2 w-full rounded-xl border border-border bg-inputBg px-3 py-3 text-primary" />
                            </label>
                            <label class="block text-sm font-semibold text-primary">Wochen
                                <input v-model="planForm.weeks" type="number" min="1" max="104" class="mt-2 w-full rounded-xl border border-border bg-inputBg px-3 py-3 text-primary" />
                            </label>
                            <label class="block text-sm font-semibold text-primary">Einheiten pro Woche
                                <input v-model="planForm.weekly_sessions" type="number" min="1" max="21" class="mt-2 w-full rounded-xl border border-border bg-inputBg px-3 py-3 text-primary" />
                            </label>
                        </div>

                        <details class="mt-4 rounded-2xl border border-border bg-inputBg/40 p-3">
                            <summary class="cursor-pointer text-sm font-semibold text-primary">Erweiterte Planung</summary>
                            <div class="mt-4 grid gap-4 md:grid-cols-2">
                                <label class="block text-sm font-semibold text-primary">Makrozyklus
                                    <input v-model="planForm.macrocycle" class="mt-2 w-full rounded-xl border border-border bg-inputBg px-3 py-3 text-primary" placeholder="z. B. Sommeraufbau 2026" />
                                </label>
                                <label class="block text-sm font-semibold text-primary">Mesozyklus
                                    <input v-model="planForm.mesocycle" class="mt-2 w-full rounded-xl border border-border bg-inputBg px-3 py-3 text-primary" placeholder="z. B. Kraftblock 1" />
                                </label>
                                <label class="block text-sm font-semibold text-primary">Deload-Woche
                                    <input v-model="planForm.deload_week" type="number" min="1" max="104" class="mt-2 w-full rounded-xl border border-border bg-inputBg px-3 py-3 text-primary" />
                                </label>
                                <label class="block text-sm font-semibold text-primary">Wettkampf / Zieltermin
                                    <input v-model="planForm.competition_date" type="date" class="mt-2 w-full rounded-xl border border-border bg-inputBg px-3 py-3 text-primary" />
                                </label>
                                <label class="block text-sm font-semibold text-primary md:col-span-2">Beschreibung
                                    <textarea v-model="planForm.description" rows="3" class="mt-2 w-full rounded-xl border border-border bg-inputBg px-3 py-3 text-primary" />
                                </label>
                            </div>
                        </details>
                    </section>

                    <section v-if="planWizardStep === 2" class="rounded-2xl border border-border bg-card p-4">
                        <p class="text-xs font-semibold uppercase tracking-wide text-air-blue">Schritt 3</p>
                        <h3 class="mt-1 text-lg font-semibold text-primary">Freigabe</h3>
                        <p class="mt-1 text-sm text-secondary">Wähle, ob der Plan sofort sichtbar ist und wer Zugriff bekommt.</p>

                        <div class="mt-4 grid gap-4 md:grid-cols-2">
                            <label class="block text-sm font-semibold text-primary">Status
                                <select v-model="planForm.status" class="mt-2 w-full rounded-xl border border-border bg-inputBg px-3 py-3 text-primary">
                                    <option value="published">Direkt freigeben</option>
                                    <option value="draft">Entwurf</option>
                                </select>
                            </label>
                            <label class="block text-sm font-semibold text-primary">Berechtigung
                                <select v-model="planForm.share_permission" class="mt-2 w-full rounded-xl border border-border bg-inputBg px-3 py-3 text-primary">
                                    <option value="read">Nur lesen</option>
                                    <option value="write">Mit schreiben / verbessern</option>
                                </select>
                            </label>
                            <label class="block text-sm font-semibold text-primary md:col-span-2">Team
                                <select v-model="planForm.team_id" class="mt-2 w-full rounded-xl border border-border bg-inputBg px-3 py-3 text-primary">
                                    <option value="">Kein komplettes Team</option>
                                    <option v-for="team in teams" :key="team.id" :value="team.id">{{ team.name }}</option>
                                </select>
                            </label>
                        </div>

                        <div class="mt-4 rounded-2xl border border-border bg-inputBg/40 p-3">
                            <div class="flex items-center justify-between gap-3">
                                <p class="text-sm font-semibold text-primary">Einzelne Sportler</p>
                                <span class="rounded-full bg-muted px-3 py-1 text-xs font-semibold text-secondary">{{ planForm.user_ids.length }} gewählt</span>
                            </div>
                            <div class="mt-3 grid max-h-52 gap-2 overflow-y-auto sm:grid-cols-2">
                                <label v-for="person in people" :key="person.id" class="flex items-center gap-2 rounded-xl border border-border bg-bg/40 px-3 py-2 text-sm text-primary">
                                    <input type="checkbox" class="rounded border-border bg-inputBg" :checked="planForm.user_ids.map(Number).includes(Number(person.id))" @change="togglePlanUser(person.id)" />
                                    <span class="truncate">{{ person.name }}</span>
                                </label>
                                <p v-if="!people.length" class="text-sm text-secondary">Keine einzelnen Sportler verfügbar.</p>
                            </div>
                            <p v-if="selectedTeamMembers.length" class="mt-3 text-xs text-secondary">Team-Auswahl umfasst {{ selectedTeamMembers.length }} Personen.</p>
                        </div>
                    </section>

                    <section v-if="planWizardStep === 3" class="rounded-2xl border border-border bg-card p-4">
                        <p class="text-xs font-semibold uppercase tracking-wide text-air-blue">Schritt 4</p>
                        <h3 class="mt-1 text-lg font-semibold text-primary">Erste Einheit</h3>
                        <p class="mt-1 text-sm text-secondary">Der Plan braucht eine erste Einheit. Weitere Einheiten kannst du danach hinzufügen.</p>

                        <div class="mt-4 rounded-2xl border border-border bg-inputBg/40 p-3">
                            <p class="text-xs font-semibold uppercase tracking-wide text-secondary">Schnellstart</p>
                            <div class="mt-2 flex gap-2 overflow-x-auto pb-1">
                                <button v-for="template in exerciseLibrary" :key="template.title" type="button" class="shrink-0 rounded-xl border border-border bg-bg/50 px-3 py-2 text-left text-xs text-primary hover:bg-muted" @click="applyPlanExerciseTemplate(template)">
                                    <span class="block font-semibold">{{ template.title }}</span>
                                    <span class="text-secondary">{{ sportLabel(template.sport_type) }} - {{ template.focus }}</span>
                                </button>
                            </div>
                        </div>

                        <div class="mt-4 grid gap-4 md:grid-cols-2">
                            <div class="md:col-span-2">
                                <p class="text-sm font-semibold text-primary">Einheitstyp</p>
                                <p class="mt-1 text-xs text-secondary">Hier geht es um die Art dieser Einheit, nicht um die Sportart.</p>
                                <div class="mt-2 grid grid-cols-2 gap-2 sm:grid-cols-4">
                                    <button
                                        v-for="type in planTrainingTypes"
                                        :key="type.key"
                                        type="button"
                                        class="rounded-xl border px-3 py-3 text-left text-sm font-semibold transition"
                                        :class="planForm.item_training_type === type.key ? 'border-air-blue bg-air-blue/10 text-air-blue' : 'border-border bg-inputBg text-primary hover:bg-muted'"
                                        @click="selectPlanTrainingType(type.key)"
                                    >
                                        <span :class="['mb-2 block h-1.5 w-8 rounded-full', type.accent]"></span>
                                        <i :class="type.icon" class="mr-2"></i>{{ type.label }}
                                    </button>
                                </div>
                            </div>
                            <label class="block text-sm font-semibold text-primary md:col-span-2">Titel
                                <input v-model="planForm.item_title" class="mt-2 w-full rounded-xl border border-border bg-inputBg px-3 py-3 text-primary" placeholder="z. B. Long Run, Push Training, Technikdrill" required />
                            </label>
                            <label class="block text-sm font-semibold text-primary">Termin
                                <input v-model="planForm.item_scheduled_at" type="datetime-local" class="mt-2 w-full rounded-xl border border-border bg-inputBg px-3 py-3 text-primary" />
                            </label>
                            <label class="block text-sm font-semibold text-primary">Woche
                                <input v-model="planForm.item_week" type="number" min="1" max="104" class="mt-2 w-full rounded-xl border border-border bg-inputBg px-3 py-3 text-primary" />
                            </label>
                            <label class="block text-sm font-semibold text-primary">Dauer
                                <input v-model="planForm.item_duration_minutes" type="number" min="0" class="mt-2 w-full rounded-xl border border-border bg-inputBg px-3 py-3 text-primary" />
                            </label>
                            <label v-if="['laufen', 'cycling', 'schwimmen', 'fussball'].includes(planForm.item_sport_type)" class="block text-sm font-semibold text-primary">Distanz km
                                <input v-model="planForm.item_distance_km" type="number" min="0" step="0.01" class="mt-2 w-full rounded-xl border border-border bg-inputBg px-3 py-3 text-primary" />
                            </label>
                            <label class="block text-sm font-semibold text-primary">Belastung
                                <select v-model="planForm.item_load" class="mt-2 w-full rounded-xl border border-border bg-inputBg px-3 py-3 text-primary">
                                    <option value="low">Locker</option>
                                    <option value="medium">Mittel</option>
                                    <option value="high">Hoch</option>
                                    <option value="test">Test</option>
                                </select>
                            </label>
                            <label class="block text-sm font-semibold text-primary">Fokus
                                <input v-model="planForm.item_focus" class="mt-2 w-full rounded-xl border border-border bg-inputBg px-3 py-3 text-primary" placeholder="z. B. Technik, Zone 2, Explosivität" />
                            </label>
                            <label v-for="metric in planSport.metrics" :key="metric" class="block text-sm font-semibold text-primary">
                                {{ metric }}
                                <input v-model="planForm.item_metrics[metric]" class="mt-2 w-full rounded-xl border border-border bg-inputBg px-3 py-3 text-primary" />
                            </label>
                        </div>

                        <details class="mt-4 rounded-2xl border border-border bg-inputBg/40 p-3">
                            <summary class="cursor-pointer text-sm font-semibold text-primary">Medien und Aufgaben</summary>
                            <div class="mt-4 grid gap-4 md:grid-cols-2">
                                <label class="block text-sm font-semibold text-primary md:col-span-2">Todo-Liste
                                    <textarea v-model="planForm.item_todos" rows="3" class="mt-2 w-full rounded-xl border border-border bg-inputBg px-3 py-3 text-primary" placeholder="Eine Aufgabe pro Zeile" />
                                </label>
                                <label class="block text-sm font-semibold text-primary">Video-Link
                                    <input v-model="planForm.item_video_url" type="url" class="mt-2 w-full rounded-xl border border-border bg-inputBg px-3 py-3 text-primary" />
                                </label>
                                <label class="block text-sm font-semibold text-primary">Bild
                                    <input ref="planImageInput" type="file" accept="image/*" class="mt-2 w-full rounded-xl border border-border bg-inputBg px-3 py-3 text-primary file:mr-3 file:rounded-md file:border-0 file:bg-buttonPrimary file:px-3 file:py-1 file:text-sm file:font-semibold file:text-buttonTextPrimary" @change="setPlanImage" />
                                </label>
                            </div>
                        </details>
                    </section>

                    <div class="sticky bottom-0 -mx-4 -mb-4 flex items-center justify-between gap-3 border-t border-border bg-bg/95 p-4 backdrop-blur">
                        <button type="button" class="rounded-xl border border-border px-4 py-3 text-sm font-semibold text-primary disabled:opacity-40" :disabled="planWizardStep === 0" @click="previousPlanWizardStep">
                            Zurück
                        </button>
                        <button v-if="planWizardStep < planWizardSteps.length - 1" type="button" class="rounded-xl bg-buttonPrimary px-5 py-3 text-sm font-semibold text-buttonTextPrimary disabled:opacity-50" :disabled="!planWizardCanContinue" @click="nextPlanWizardStep">
                            Weiter
                        </button>
                        <button v-else type="submit" class="rounded-xl bg-buttonPrimary px-5 py-3 text-sm font-semibold text-buttonTextPrimary disabled:opacity-60" :disabled="planForm.processing || !planWizardCanContinue">
                            Plan speichern
                        </button>
                    </div>
                </form>

                <form v-if="activeModal === 'edit'" class="space-y-4 p-4" @submit.prevent="updatePlan">
                    <div class="grid gap-4 md:grid-cols-2">
                        <label class="block text-sm font-semibold text-primary md:col-span-2">Planname
                            <input v-model="editForm.title" class="mt-2 w-full rounded-xl border border-border bg-inputBg px-3 py-2 text-primary" required />
                        </label>
                        <label class="block text-sm font-semibold text-primary">Rhythmus
                            <select v-model="editForm.cadence" class="mt-2 w-full rounded-xl border border-border bg-inputBg px-3 py-2 text-primary">
                                <option value="single">Einmalig</option>
                                <option value="daily">Täglich</option>
                                <option value="weekly">Wöchentlich</option>
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
                        <label class="block text-sm font-semibold text-primary">Start
                            <input v-model="editForm.starts_on" type="date" class="mt-2 w-full rounded-xl border border-border bg-inputBg px-3 py-2 text-primary" />
                        </label>
                        <label class="block text-sm font-semibold text-primary">Ende
                            <input v-model="editForm.ends_on" type="date" class="mt-2 w-full rounded-xl border border-border bg-inputBg px-3 py-2 text-primary" />
                        </label>
                        <label class="block text-sm font-semibold text-primary md:col-span-2">Ziel des Plans
                            <input v-model="editForm.goal" class="mt-2 w-full rounded-xl border border-border bg-inputBg px-3 py-2 text-primary" />
                        </label>
                        <label class="block text-sm font-semibold text-primary">Trainingsphase
                            <select v-model="editForm.phase" class="mt-2 w-full rounded-xl border border-border bg-inputBg px-3 py-2 text-primary">
                                <option value="base">Grundlage</option>
                                <option value="build">Aufbau</option>
                                <option value="peak">Peak / Wettkampfnähe</option>
                                <option value="recovery">Regeneration</option>
                                <option value="rehab">Reha / Wiedereinstieg</option>
                            </select>
                        </label>
                        <label class="block text-sm font-semibold text-primary">Niveau
                            <select v-model="editForm.level" class="mt-2 w-full rounded-xl border border-border bg-inputBg px-3 py-2 text-primary">
                                <option value="beginner">Einsteiger</option>
                                <option value="intermediate">Fortgeschritten</option>
                                <option value="advanced">Advanced</option>
                                <option value="elite">Leistung</option>
                            </select>
                        </label>
                        <label class="block text-sm font-semibold text-primary">Wochen
                            <input v-model="editForm.weeks" type="number" min="1" max="104" class="mt-2 w-full rounded-xl border border-border bg-inputBg px-3 py-2 text-primary" />
                        </label>
                        <label class="block text-sm font-semibold text-primary">Einheiten pro Woche
                            <input v-model="editForm.weekly_sessions" type="number" min="1" max="21" class="mt-2 w-full rounded-xl border border-border bg-inputBg px-3 py-2 text-primary" />
                        </label>
                        <label class="block text-sm font-semibold text-primary">Makrozyklus
                            <input v-model="editForm.macrocycle" class="mt-2 w-full rounded-xl border border-border bg-inputBg px-3 py-2 text-primary" />
                        </label>
                        <label class="block text-sm font-semibold text-primary">Mesozyklus
                            <input v-model="editForm.mesocycle" class="mt-2 w-full rounded-xl border border-border bg-inputBg px-3 py-2 text-primary" />
                        </label>
                        <label class="block text-sm font-semibold text-primary">Deload-Woche
                            <input v-model="editForm.deload_week" type="number" min="1" max="104" class="mt-2 w-full rounded-xl border border-border bg-inputBg px-3 py-2 text-primary" />
                        </label>
                        <label class="block text-sm font-semibold text-primary">Wettkampf / Zieltermin
                            <input v-model="editForm.competition_date" type="date" class="mt-2 w-full rounded-xl border border-border bg-inputBg px-3 py-2 text-primary" />
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
                        Änderungen speichern
                    </button>
                </form>

                <form v-if="activeModal === 'item'" class="grid gap-4 p-4 md:grid-cols-2" @submit.prevent="submitPlanItem">
                    <div class="md:col-span-2 rounded-2xl border border-border bg-inputBg/40 p-3">
                        <p class="text-xs font-semibold uppercase tracking-wide text-secondary">Übungsbibliothek</p>
                        <div class="mt-2 flex gap-2 overflow-x-auto pb-1">
                            <button v-for="template in exerciseLibrary" :key="template.title" type="button" class="shrink-0 rounded-xl border border-border px-3 py-2 text-left text-xs text-primary hover:bg-muted" @click="applyExerciseTemplate(template, itemForm)">
                                <span class="block font-semibold">{{ template.title }}</span>
                                <span class="text-secondary">{{ sportLabel(template.sport_type) }} · {{ template.focus }}</span>
                            </button>
                        </div>
                    </div>
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
                    <label class="block text-sm font-semibold text-primary">Woche
                        <input v-model="itemForm.week" type="number" min="1" max="104" class="mt-2 w-full rounded-xl border border-border bg-inputBg px-3 py-2 text-primary" />
                    </label>
                    <label class="block text-sm font-semibold text-primary">Dauer
                        <input v-model="itemForm.duration_minutes" type="number" min="0" class="mt-2 w-full rounded-xl border border-border bg-inputBg px-3 py-2 text-primary" />
                    </label>
                    <label class="block text-sm font-semibold text-primary">Distanz km
                        <input v-model="itemForm.distance_km" type="number" min="0" step="0.01" class="mt-2 w-full rounded-xl border border-border bg-inputBg px-3 py-2 text-primary" />
                    </label>
                    <label class="block text-sm font-semibold text-primary">Kalorien
                        <input v-model="itemForm.calories" type="number" min="0" class="mt-2 w-full rounded-xl border border-border bg-inputBg px-3 py-2 text-primary" />
                    </label>
                    <label class="block text-sm font-semibold text-primary">Belastung
                        <select v-model="itemForm.load" class="mt-2 w-full rounded-xl border border-border bg-inputBg px-3 py-2 text-primary">
                            <option value="low">Locker</option>
                            <option value="medium">Mittel</option>
                            <option value="high">Hoch</option>
                            <option value="test">Test</option>
                        </select>
                    </label>
                    <label class="block text-sm font-semibold text-primary">Fokus
                        <input v-model="itemForm.focus" class="mt-2 w-full rounded-xl border border-border bg-inputBg px-3 py-2 text-primary" />
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
                        Einheit hinzufügen
                    </button>
                </form>

                <form v-if="activeModal === 'item-edit'" class="grid gap-4 p-4 md:grid-cols-2" @submit.prevent="updatePlanItem">
                    <div class="md:col-span-2 rounded-2xl border border-border bg-inputBg/40 p-3">
                        <p class="text-xs font-semibold uppercase tracking-wide text-secondary">Übungsbibliothek</p>
                        <div class="mt-2 flex gap-2 overflow-x-auto pb-1">
                            <button v-for="template in exerciseLibrary" :key="template.title" type="button" class="shrink-0 rounded-xl border border-border px-3 py-2 text-left text-xs text-primary hover:bg-muted" @click="applyExerciseTemplate(template, editItemForm)">
                                <span class="block font-semibold">{{ template.title }}</span>
                                <span class="text-secondary">{{ sportLabel(template.sport_type) }} · {{ template.focus }}</span>
                            </button>
                        </div>
                    </div>
                    <label class="block text-sm font-semibold text-primary">Sportart
                        <select v-model="editItemForm.sport_type" class="mt-2 w-full rounded-xl border border-border bg-inputBg px-3 py-2 text-primary">
                            <option v-for="sport in sports.filter((item) => item.key !== 'all')" :key="sport.key" :value="sport.key">{{ sport.label }}</option>
                        </select>
                    </label>
                    <label class="block text-sm font-semibold text-primary">Titel
                        <input v-model="editItemForm.title" class="mt-2 w-full rounded-xl border border-border bg-inputBg px-3 py-2 text-primary" required />
                    </label>
                    <label v-for="metric in editItemSport.metrics" :key="metric" class="block text-sm font-semibold text-primary">
                        {{ metric }}
                        <input v-model="editItemForm.metrics[metric]" class="mt-2 w-full rounded-xl border border-border bg-inputBg px-3 py-2 text-primary" />
                    </label>
                    <label class="block text-sm font-semibold text-primary">Termin
                        <input v-model="editItemForm.scheduled_at" type="datetime-local" class="mt-2 w-full rounded-xl border border-border bg-inputBg px-3 py-2 text-primary" />
                    </label>
                    <label class="block text-sm font-semibold text-primary">Woche
                        <input v-model="editItemForm.week" type="number" min="1" max="104" class="mt-2 w-full rounded-xl border border-border bg-inputBg px-3 py-2 text-primary" />
                    </label>
                    <label class="block text-sm font-semibold text-primary">Dauer
                        <input v-model="editItemForm.duration_minutes" type="number" min="0" class="mt-2 w-full rounded-xl border border-border bg-inputBg px-3 py-2 text-primary" />
                    </label>
                    <label class="block text-sm font-semibold text-primary">Distanz km
                        <input v-model="editItemForm.distance_km" type="number" min="0" step="0.01" class="mt-2 w-full rounded-xl border border-border bg-inputBg px-3 py-2 text-primary" />
                    </label>
                    <label class="block text-sm font-semibold text-primary">Kalorien
                        <input v-model="editItemForm.calories" type="number" min="0" class="mt-2 w-full rounded-xl border border-border bg-inputBg px-3 py-2 text-primary" />
                    </label>
                    <label class="block text-sm font-semibold text-primary">Belastung
                        <select v-model="editItemForm.load" class="mt-2 w-full rounded-xl border border-border bg-inputBg px-3 py-2 text-primary">
                            <option value="low">Locker</option>
                            <option value="medium">Mittel</option>
                            <option value="high">Hoch</option>
                            <option value="test">Test</option>
                        </select>
                    </label>
                    <label class="block text-sm font-semibold text-primary">Fokus
                        <input v-model="editItemForm.focus" class="mt-2 w-full rounded-xl border border-border bg-inputBg px-3 py-2 text-primary" />
                    </label>
                    <label class="block text-sm font-semibold text-primary md:col-span-2">Beschreibung
                        <textarea v-model="editItemForm.description" rows="3" class="mt-2 w-full rounded-xl border border-border bg-inputBg px-3 py-2 text-primary" />
                    </label>
                    <label class="block text-sm font-semibold text-primary md:col-span-2">Todo-Liste
                        <textarea v-model="editItemForm.todos" rows="3" class="mt-2 w-full rounded-xl border border-border bg-inputBg px-3 py-2 text-primary" placeholder="Eine Aufgabe pro Zeile" />
                    </label>
                    <label class="block text-sm font-semibold text-primary">Video-Link
                        <input v-model="editItemForm.video_url" type="url" class="mt-2 w-full rounded-xl border border-border bg-inputBg px-3 py-2 text-primary" />
                    </label>
                    <label class="block text-sm font-semibold text-primary">Bild ersetzen
                        <input ref="editItemImageInput" type="file" accept="image/*" class="mt-2 w-full rounded-xl border border-border bg-inputBg px-3 py-2 text-primary file:mr-3 file:rounded-md file:border-0 file:bg-buttonPrimary file:px-3 file:py-1 file:text-sm file:font-semibold file:text-buttonTextPrimary" @change="setEditItemImage" />
                    </label>
                    <button type="submit" class="md:col-span-2 rounded-xl bg-buttonPrimary px-4 py-2 text-sm font-semibold text-buttonTextPrimary disabled:opacity-60" :disabled="editItemForm.processing">
                        Einheit speichern
                    </button>
                </form>

                <div v-if="activeModal === 'delete'" class="p-4">
                    <p class="text-sm text-secondary">
                        Der Plan <span class="font-semibold text-primary">{{ selectedPlan?.title }}</span> wird inklusive Einheiten und Bildern gelöscht. Tippe <span class="font-semibold text-danger">delete</span>, um fortzufahren.
                    </p>
                    <input v-model="deleteText" class="mt-4 w-full rounded-xl border border-border bg-inputBg px-3 py-2 text-primary" placeholder="delete" />
                    <div class="mt-4 flex justify-end gap-2">
                        <button type="button" class="rounded-xl border border-border px-4 py-2 text-sm font-semibold text-primary" @click="closeModal">Abbrechen</button>
                        <button type="button" class="rounded-xl bg-danger px-4 py-2 text-sm font-semibold text-white disabled:opacity-50" :disabled="deleteText !== 'delete'" @click="deletePlan">
                            Endgültig löschen
                        </button>
                    </div>
                </div>

                <div v-if="activeModal === 'item-delete'" class="p-4">
                    <p class="text-sm text-secondary">
                        Die Einheit <span class="font-semibold text-primary">{{ selectedItem?.title }}</span> wird aus dem Plan entfernt. Tippe <span class="font-semibold text-danger">delete</span>, um fortzufahren.
                    </p>
                    <input v-model="deleteText" class="mt-4 w-full rounded-xl border border-border bg-inputBg px-3 py-2 text-primary" placeholder="delete" />
                    <div class="mt-4 flex justify-end gap-2">
                        <button type="button" class="rounded-xl border border-border px-4 py-2 text-sm font-semibold text-primary" @click="closeModal">Abbrechen</button>
                        <button type="button" class="rounded-xl bg-danger px-4 py-2 text-sm font-semibold text-white disabled:opacity-50" :disabled="deleteText !== 'delete'" @click="deletePlanItem">
                            Einheit löschen
                        </button>
                    </div>
                </div>

                <form v-if="activeModal === 'item-missed'" class="space-y-4 p-4" @submit.prevent="markPlanItemMissed">
                    <p class="text-sm text-secondary">
                        Markiere <span class="font-semibold text-primary">{{ selectedItem?.title }}</span> als nicht gemacht und dokumentiere kurz warum.
                    </p>
                    <label v-if="athleteOptions.length > 1" class="block text-sm font-semibold text-primary">Sportler
                        <select v-model="missedForm.user_id" class="mt-2 w-full rounded-xl border border-border bg-inputBg px-3 py-2 text-primary">
                            <option v-for="athlete in athleteOptions" :key="athlete.id || 'self'" :value="athlete.id">{{ athlete.name }}</option>
                        </select>
                    </label>
                    <label class="block text-sm font-semibold text-primary">Grund
                        <select v-model="missedForm.reason" class="mt-2 w-full rounded-xl border border-border bg-inputBg px-3 py-2 text-primary">
                            <option value="krank">Krank</option>
                            <option value="verletzt">Verletzt</option>
                            <option value="keine_zeit">Keine Zeit</option>
                            <option value="verschoben">Verschoben</option>
                            <option value="bewusst_ausgelassen">Bewusst ausgelassen</option>
                            <option value="anderes">Anderes</option>
                        </select>
                    </label>
                    <label class="block text-sm font-semibold text-primary">Notiz
                        <textarea v-model="missedForm.notes" rows="3" class="mt-2 w-full rounded-xl border border-border bg-inputBg px-3 py-2 text-primary" placeholder="Optional: kurze Einordnung für dich oder den Trainer" />
                    </label>
                    <button type="submit" class="w-full rounded-xl bg-buttonPrimary px-4 py-2 text-sm font-semibold text-buttonTextPrimary disabled:opacity-60" :disabled="missedForm.processing">
                        Ausfall speichern
                    </button>
                </form>

                <div v-if="activeModal === 'draft-delete'" class="p-4">
                    <p class="text-sm text-secondary">
                        Der Entwurf <span class="font-semibold text-primary">{{ selectedDraft?.title || 'Training-Entwurf' }}</span> wird gelöscht. Deine gespeicherten Werte gehen verloren. Tippe <span class="font-semibold text-danger">delete</span>, um fortzufahren.
                    </p>
                    <input v-model="deleteText" class="mt-4 w-full rounded-xl border border-border bg-inputBg px-3 py-2 text-primary" placeholder="delete" />
                    <div class="mt-4 flex justify-end gap-2">
                        <button type="button" class="rounded-xl border border-border px-4 py-2 text-sm font-semibold text-primary" @click="closeModal">Abbrechen</button>
                        <button type="button" class="rounded-xl bg-danger px-4 py-2 text-sm font-semibold text-white disabled:opacity-50" :disabled="deleteText !== 'delete'" @click="deleteDraft">
                            Entwurf verwerfen
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <div class="fixed inset-x-0 bottom-0 z-40 border-t border-border bg-bg/95 px-4 py-3 shadow-2xl shadow-black/30 backdrop-blur sm:hidden">
            <div class="mx-auto grid max-w-md grid-cols-[1fr_1fr_1fr_auto] gap-2">
                <button type="button" class="inline-flex h-12 items-center justify-center gap-2 rounded-xl bg-buttonPrimary px-3 text-sm font-semibold text-buttonTextPrimary" @click="openModal('plan')">
                    <i class="las la-plus-circle text-lg"></i>
                    Plan
                </button>
                <button type="button" class="inline-flex h-12 items-center justify-center gap-2 rounded-xl border border-air-blue/50 bg-air-blue/10 px-3 text-sm font-semibold text-air-blue disabled:opacity-50" :disabled="!aiTrainingPlanAvailable" @click="openAiTrainingPlanModal()">
                    <i class="las la-magic text-lg"></i>
                    KI
                </button>
                <button type="button" class="inline-flex h-12 items-center justify-center gap-2 rounded-xl border border-border bg-card px-3 text-sm font-semibold text-primary" @click="openLogPage">
                    <i class="las la-pen-alt text-lg"></i>
                    Log
                </button>
                <button type="button" class="flex h-12 w-12 items-center justify-center rounded-xl border border-border bg-card text-primary" aria-label="Zur Woche" @click="activeTrainingSection = 'week'">
                    <i class="las la-calendar-week text-xl"></i>
                </button>
            </div>
        </div>
    </div>
</template>
