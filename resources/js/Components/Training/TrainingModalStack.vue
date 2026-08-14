<script setup>
import { computed, nextTick, onBeforeUnmount, onMounted, ref } from 'vue'
import TrainingActivityForm from '@/Components/Training/TrainingActivityForm.vue'
import TrainingAiPlanModal from '@/Components/Training/TrainingAiPlanModal.vue'
import TrainingDeleteConfirmation from '@/Components/Training/TrainingDeleteConfirmation.vue'
import TrainingLogModalForm from '@/Components/Training/TrainingLogModalForm.vue'
import TrainingMissedItemForm from '@/Components/Training/TrainingMissedItemForm.vue'
import TrainingPlanCreateModal from '@/Components/Training/TrainingPlanCreateModal.vue'
import TrainingPlanEditForm from '@/Components/Training/TrainingPlanEditForm.vue'
import TrainingPlanItemForm from '@/Components/Training/TrainingPlanItemForm.vue'
import { useI18n } from 'vue-i18n'

const props = defineProps({
    activeModal: { type: String, required: true },
    deleteText: { type: String, default: '' },
    selectedPlan: { type: Object, default: null },
    selectedItem: { type: Object, default: null },
    selectedDraft: { type: Object, default: null },
    athleteOptions: { type: Array, default: () => [] },
    logForm: { type: Object, required: true },
    plannedLogItems: { type: Array, default: () => [] },
    sportChoices: { type: Array, default: () => [] },
    formatDate: { type: Function, required: true },
    addLogEntry: { type: Function, required: true },
    applySelectedPlanItem: { type: Function, required: true },
    removeLogEntry: { type: Function, required: true },
    setLogStatus: { type: Function, required: true },
    submitLog: { type: Function, required: true },
    activityForm: { type: Object, required: true },
    setActivityImageElement: { type: Function, required: true },
    sports: { type: Array, default: () => [] },
    setActivityImage: { type: Function, required: true },
    submitActivity: { type: Function, required: true },
    aiPlanStep: { type: Number, default: 0 },
    aiPlanSteps: { type: Array, default: () => [] },
    canOpenAiPlanStep: { type: Function, required: true },
    aiTrainingPlanError: { type: String, default: '' },
    aiTrainingPlanMessage: { type: String, default: '' },
    aiProfileMissingFields: { type: Array, default: () => [] },
    aiProfileMissingMessage: { type: String, default: '' },
    aiProfileCompletionUrl: { type: String, default: '' },
    aiProfileEstimateAllowed: { type: Boolean, default: false },
    aiTrainingPlanGenerating: { type: Boolean, default: false },
    generateAiTrainingPlanWithProfileEstimates: { type: Function, required: true },
    aiPlanSourcePlan: { type: Object, default: null },
    aiTrainingProviderLabel: { type: String, default: '' },
    aiPlanLimitLabel: { type: String, default: '' },
    aiTrainingPlan: { type: Object, default: () => ({}) },
    aiPlanForm: { type: Object, required: true },
    aiPlanSportChoices: { type: Array, default: () => [] },
    selectAiPlanSportType: { type: Function, required: true },
    aiPlanDurationPresets: { type: Array, default: () => [] },
    aiPlanMaxWeeks: { type: Number, default: 1 },
    setAiPlanDurationPreset: { type: Function, required: true },
    aiPlanTooLarge: { type: Boolean, default: false },
    aiPlanWeeksTooLong: { type: Boolean, default: false },
    aiPlanRequestedItems: { type: Number, default: 0 },
    aiPlanMaxItems: { type: Number, default: 0 },
    aiTrainingPlanPreview: { type: Object, default: null },
    aiSafetyAccepted: { type: Boolean, default: false },
    aiSafetyCanSave: { type: Boolean, default: false },
    aiSafetyGate: { type: Object, default: null },
    qualityRiskClass: { type: Function, required: true },
    qualityStatusClass: { type: Function, required: true },
    generateAiTrainingPlan: { type: Function, required: true },
    continueAiTrainingPlan: { type: Function, required: true },
    aiPlanCannotGenerate: { type: Boolean, default: false },
    saveAiTrainingPlan: { type: Function, required: true },
    aiTrainingPlanSaving: { type: Boolean, default: false },
    sportLabel: { type: Function, required: true },
    planWizardSteps: { type: Array, default: () => [] },
    planWizardStep: { type: Number, required: true },
    canOpenPlanWizardStep: { type: Function, required: true },
    goToPlanWizardStep: { type: Function, required: true },
    planForm: { type: Object, required: true },
    planTrainingTypes: { type: Array, default: () => [] },
    trainingSessionBlocks: { type: Array, default: () => [] },
    trainingGoals: { type: Array, default: () => [] },
    equipmentPresets: { type: Array, default: () => [] },
    selectPlanTrainingType: { type: Function, required: true },
    planSport: { type: Object, required: true },
    exerciseLibrary: { type: Array, default: () => [] },
    teams: { type: Array, default: () => [] },
    people: { type: Array, default: () => [] },
    privatePeople: { type: Array, default: () => [] },
    selectedTeamMembers: { type: Array, default: () => [] },
    selectedEditTeamMembers: { type: Array, default: () => [] },
    setPlanTargetType: { type: Function, required: true },
    setPlanTeamMode: { type: Function, required: true },
    togglePlanUser: { type: Function, required: true },
    applyPlanExerciseTemplate: { type: Function, required: true },
    setPlanImage: { type: Function, required: true },
    setPlanImageElement: { type: Function, required: true },
    previousPlanWizardStep: { type: Function, required: true },
    nextPlanWizardStep: { type: Function, required: true },
    planWizardCanContinue: { type: Boolean, default: false },
    submitPlan: { type: Function, required: true },
    editForm: { type: Object, required: true },
    editPlanAudienceCanSubmit: { type: Boolean, default: false },
    updatePlan: { type: Function, required: true },
    itemForm: { type: Object, required: true },
    itemSport: { type: Object, required: true },
    applyExerciseTemplate: { type: Function, required: true },
    setItemImage: { type: Function, required: true },
    submitPlanItem: { type: Function, required: true },
    editItemForm: { type: Object, required: true },
    editItemSport: { type: Object, required: true },
    sportRoutes: { type: Array, default: () => [] },
    setEditItemImage: { type: Function, required: true },
    updatePlanItem: { type: Function, required: true },
    missedForm: { type: Object, required: true },
    markPlanItemMissed: { type: Function, required: true },
    closeModal: { type: Function, required: true },
    deletePlan: { type: Function, required: true },
    deletePlanItem: { type: Function, required: true },
    deleteDraft: { type: Function, required: true },
})

const emit = defineEmits(['update:aiPlanStep', 'update:aiSafetyAccepted', 'update:deleteText'])
const { locale, t } = useI18n()
const modalPanel = ref(null)
let previouslyFocusedElement = null
const deleteCopy = {
    de: {
        planAfter: 'wird inklusive Einheiten und Bildern gelöscht.',
        itemAfter: 'wird aus dem Plan entfernt.',
        draftAfter: 'wird gelöscht. Deine gespeicherten Werte gehen verloren.',
    },
    en: {
        planAfter: 'will be deleted together with all sessions and images.',
        itemAfter: 'will be removed from the plan.',
        draftAfter: 'will be deleted. Your saved values will be lost.',
    },
    fr: {
        planAfter: 'sera supprimé avec toutes les séances et images.',
        itemAfter: 'sera retirée du plan.',
        draftAfter: 'sera supprimé. Tes valeurs enregistrées seront perdues.',
    },
    ar: {
        planAfter: 'سيُحذف مع جميع الوحدات والصور.',
        itemAfter: 'ستُزال من الخطة.',
        draftAfter: 'سيُحذف وستفقد القيم المحفوظة.',
    },
}
const dc = (key) => (deleteCopy[String(locale.value || 'de').split('-')[0]] || deleteCopy.de)[key]

const compactModals = ['delete', 'draft-delete', 'item-delete', 'item-missed']
const unitModals = ['log', 'activity', 'item', 'item-edit', 'item-missed']
const destructiveModals = ['delete', 'draft-delete', 'item-delete']

const modalWidthClass = computed(() => (compactModals.includes(props.activeModal) ? 'max-w-lg' : 'max-w-4xl'))

const modalEyebrow = computed(() => {
    if (unitModals.includes(props.activeModal)) {
        return t('Trainingseinheit')
    }

    if (destructiveModals.includes(props.activeModal)) {
        return t('Bestätigen')
    }

    return t('Trainingsplan')
})

const modalTitle = computed(() => ({
    'ai-plan': t('KI-Plan erstellen'),
    plan: t('Plan erstellen'),
    log: t('Training dokumentieren'),
    activity: t('Einheit eintragen'),
    edit: t('Plan bearbeiten & freigeben'),
    item: t('Einheit zum Plan hinzufügen'),
    'item-edit': t('Einheit bearbeiten'),
    'item-missed': t('Ausfall melden'),
    'item-delete': t('Einheit löschen'),
    'draft-delete': t('Training-Entwurf verwerfen'),
    delete: t('Trainingsplan löschen'),
}[props.activeModal] || t('Trainingsplan')))

const closeOnEscape = (event) => {
    if (event.key === 'Escape') {
        props.closeModal()
        return
    }

    if (event.key !== 'Tab' || !modalPanel.value) return

    const focusable = [...modalPanel.value.querySelectorAll('button:not([disabled]), a[href], input:not([disabled]), select:not([disabled]), textarea:not([disabled]), [tabindex]:not([tabindex="-1"])')]
        .filter((element) => element.offsetParent !== null)
    if (!focusable.length) {
        event.preventDefault()
        return
    }

    const first = focusable[0]
    const last = focusable[focusable.length - 1]
    if (event.shiftKey && document.activeElement === first) {
        event.preventDefault()
        last.focus()
    } else if (!event.shiftKey && document.activeElement === last) {
        event.preventDefault()
        first.focus()
    }
}

onMounted(async () => {
    previouslyFocusedElement = document.activeElement
    document.addEventListener('keydown', closeOnEscape)
    await nextTick()
    modalPanel.value?.querySelector('button:not([disabled]), input:not([disabled]), select:not([disabled]), textarea:not([disabled])')?.focus()
})

onBeforeUnmount(() => {
    document.removeEventListener('keydown', closeOnEscape)
    previouslyFocusedElement?.focus?.()
})
</script>

<template>
    <div
        class="fixed inset-0 z-50 flex items-end justify-center bg-black/70 p-3 sm:items-center"
        @pointerdown.stop
        @click.self="closeModal"
    >
        <div
            ref="modalPanel"
            class="max-h-[92vh] w-full overflow-y-auto rounded-2xl border border-border bg-bg shadow-2xl"
            :class="modalWidthClass"
            role="dialog"
            aria-modal="true"
            aria-labelledby="training-modal-title"
            @pointerdown.stop
            @click.stop
        >
            <div class="sticky top-0 z-10 flex items-start justify-between gap-4 border-b border-border bg-bg/95 p-4 backdrop-blur">
                <div>
                    <p class="text-xs font-semibold uppercase tracking-wide text-air-blue">
                        {{ modalEyebrow }}
                    </p>
                    <h2 id="training-modal-title" class="mt-1 text-xl font-semibold text-primary">
                        {{ modalTitle }}
                    </h2>
                </div>
                <button type="button" class="rounded-xl border border-border px-3 py-2 text-sm font-semibold text-primary hover:bg-muted" :aria-label="t('Schließen')" @click="closeModal">
                    {{ t('Schließen') }}
                </button>
            </div>

            <TrainingLogModalForm
                v-if="activeModal === 'log'"
                :athlete-options="athleteOptions"
                :form="logForm"
                :format-date="formatDate"
                :planned-log-items="plannedLogItems"
                :sport-choices="sportChoices"
                @add-entry="addLogEntry"
                @apply-selected-plan-item="applySelectedPlanItem"
                @remove-entry="removeLogEntry"
                @set-log-status="setLogStatus"
                @submit="submitLog"
            />

            <TrainingActivityForm
                v-if="activeModal === 'activity'"
                :form="activityForm"
                :set-activity-image-element="setActivityImageElement"
                :sports="sports"
                @set-image="setActivityImage"
                @submit="submitActivity"
            />

            <TrainingAiPlanModal
                v-if="activeModal === 'ai-plan'"
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
                @update:ai-plan-step="emit('update:aiPlanStep', $event)"
                @update:ai-safety-accepted="emit('update:aiSafetyAccepted', $event)"
            />

            <TrainingPlanCreateModal
                v-if="activeModal === 'plan'"
                :plan-wizard-steps="planWizardSteps"
                :plan-wizard-step="planWizardStep"
                :can-open-plan-wizard-step="canOpenPlanWizardStep"
                :go-to-plan-wizard-step="goToPlanWizardStep"
                :plan-form="planForm"
                :plan-training-types="planTrainingTypes"
                :training-session-blocks="trainingSessionBlocks"
                :training-goals="trainingGoals"
                :equipment-presets="equipmentPresets"
                :select-plan-training-type="selectPlanTrainingType"
                :plan-sport="planSport"
                :exercise-library="exerciseLibrary"
                :sport-label="sportLabel"
                :teams="teams"
                :people="people"
                :private-people="privatePeople"
                :selected-team-members="selectedTeamMembers"
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
            />

            <TrainingPlanEditForm
                v-if="activeModal === 'edit'"
                :form="editForm"
                :people="people"
                :private-people="privatePeople"
                :selected-team-members="selectedEditTeamMembers"
                :teams="teams"
                :toggle-plan-user="togglePlanUser"
                :set-plan-target-type="setPlanTargetType"
                :set-plan-team-mode="setPlanTeamMode"
                :can-submit="editPlanAudienceCanSubmit"
                @submit="updatePlan"
            />

            <TrainingPlanItemForm
                v-if="activeModal === 'item'"
                :exercise-library="exerciseLibrary"
                :form="itemForm"
                :sport="itemSport"
                :sport-label="sportLabel"
                :sports="sports"
                :training-session-blocks="trainingSessionBlocks"
                :training-goals="trainingGoals"
                :equipment-presets="equipmentPresets"
                :sport-routes="sportRoutes"
                :submit-label="t('Einheit hinzufügen')"
                @apply-template="applyExerciseTemplate($event, itemForm)"
                @set-image="setItemImage"
                @submit="submitPlanItem"
            />

            <TrainingPlanItemForm
                v-if="activeModal === 'item-edit'"
                :exercise-library="exerciseLibrary"
                :form="editItemForm"
                :image-label="t('Bild ersetzen')"
                :sport="editItemSport"
                :sport-label="sportLabel"
                :sports="sports"
                :training-session-blocks="trainingSessionBlocks"
                :training-goals="trainingGoals"
                :equipment-presets="equipmentPresets"
                :sport-routes="sportRoutes"
                :submit-label="t('Einheit speichern')"
                @apply-template="applyExerciseTemplate($event, editItemForm)"
                @set-image="setEditItemImage"
                @submit="updatePlanItem"
            />

            <TrainingDeleteConfirmation
                v-if="activeModal === 'delete'"
                :confirmation="deleteText"
                :confirm-label="t('Endgültig löschen')"
                :description-after="dc('planAfter')"
                :description-before="t('Der Plan')"
                :title="selectedPlan?.title"
                @cancel="closeModal"
                @confirm="deletePlan"
                @update:confirmation="emit('update:deleteText', $event)"
            />

            <TrainingDeleteConfirmation
                v-if="activeModal === 'item-delete'"
                :confirmation="deleteText"
                :confirm-label="t('Einheit löschen')"
                :description-after="dc('itemAfter')"
                :description-before="t('Die Einheit')"
                :title="selectedItem?.title"
                @cancel="closeModal"
                @confirm="deletePlanItem"
                @update:confirmation="emit('update:deleteText', $event)"
            />

            <TrainingMissedItemForm
                v-if="activeModal === 'item-missed'"
                :athlete-options="athleteOptions"
                :form="missedForm"
                :selected-item="selectedItem"
                @submit="markPlanItemMissed"
            />

            <TrainingDeleteConfirmation
                v-if="activeModal === 'draft-delete'"
                :confirmation="deleteText"
                :confirm-label="t('Entwurf verwerfen')"
                :description-after="dc('draftAfter')"
                :description-before="t('Der Entwurf')"
                :title="selectedDraft?.title || t('Training-Entwurf')"
                @cancel="closeModal"
                @confirm="deleteDraft"
                @update:confirmation="emit('update:deleteText', $event)"
            />
        </div>
    </div>
</template>
