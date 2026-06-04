<script setup>
import EventCreateStepBasics from './EventCreateStepBasics.vue'
import EventCreateStepDetails from './EventCreateStepDetails.vue'
import EventCreateStepReview from './EventCreateStepReview.vue'
import EventCreateStepTime from './EventCreateStepTime.vue'
import EventCreateWizardFooter from './EventCreateWizardFooter.vue'
import EventCreateWizardHeader from './EventCreateWizardHeader.vue'

defineProps({
    showCreateModal: { type: Boolean, default: false },
    createStep: { type: Number, required: true },
    steps: { type: Array, default: () => [] },
    form: { type: Object, required: true },
    eventTypes: { type: Array, default: () => [] },
    visibilities: { type: Array, default: () => [] },
    clubs: { type: Array, default: () => [] },
    filteredTeams: { type: Array, default: () => [] },
    eventCreation: { type: Object, default: () => ({}) },
    freeEventLimitMessage: { type: String, default: '' },
    recurrenceOptions: { type: Array, default: () => [] },
    weekdayOptions: { type: Array, default: () => [] },
    typeLabels: { type: Object, default: () => ({}) },
    visibilityLabels: { type: Object, default: () => ({}) },
    recurrenceSummary: { type: String, default: '' },
    selectedClubName: { type: String, default: '-' },
    selectedTeamName: { type: String, default: '-' },
    currentStepValidationMessage: { type: String, default: '' },
    closeCreateModal: { type: Function, required: true },
    canEnterStep: { type: Function, required: true },
    goToStep: { type: Function, required: true },
    submit: { type: Function, required: true },
    toggleWeekday: { type: Function, required: true },
    recurrenceLabel: { type: Function, required: true },
    nextStep: { type: Function, required: true },
    prevStep: { type: Function, required: true },
})
</script>

<template>
    <Teleport to="body">
        <div
            v-if="showCreateModal"
            class="fixed inset-0 z-[80] flex items-center justify-center bg-black/60"
            @click.self="closeCreateModal"
        >
            <div class="flex h-full w-full flex-col bg-card sm:h-auto sm:max-h-[92vh] sm:max-w-2xl sm:rounded-2xl sm:border sm:border-border sm:shadow-xl">
                <EventCreateWizardHeader
                    :can-enter-step="canEnterStep"
                    :close-create-modal="closeCreateModal"
                    :create-step="createStep"
                    :go-to-step="goToStep"
                    :steps="steps"
                />

                <form class="min-h-0 flex-1 overflow-y-auto p-4" @submit.prevent="submit">
                    <EventCreateStepBasics
                        v-if="createStep === 1"
                        :clubs="clubs"
                        :event-types="eventTypes"
                        :filtered-teams="filteredTeams"
                        :form="form"
                        :free-event-limit-message="freeEventLimitMessage"
                        :type-labels="typeLabels"
                        :visibilities="visibilities"
                        :visibility-labels="visibilityLabels"
                    />

                    <EventCreateStepTime
                        v-if="createStep === 2"
                        :form="form"
                    />

                    <EventCreateStepDetails
                        v-if="createStep === 3"
                        :event-creation="eventCreation"
                        :form="form"
                        :recurrence-options="recurrenceOptions"
                        :recurrence-summary="recurrenceSummary"
                        :toggle-weekday="toggleWeekday"
                        :weekday-options="weekdayOptions"
                    />

                    <EventCreateStepReview
                        v-if="createStep === 4"
                        :form="form"
                        :recurrence-label="recurrenceLabel"
                        :recurrence-summary="recurrenceSummary"
                        :selected-club-name="selectedClubName"
                        :selected-team-name="selectedTeamName"
                        :type-labels="typeLabels"
                        :visibility-labels="visibilityLabels"
                    />
                </form>

                <EventCreateWizardFooter
                    :create-step="createStep"
                    :current-step-validation-message="currentStepValidationMessage"
                    :form="form"
                    :next-step="nextStep"
                    :prev-step="prevStep"
                    :steps="steps"
                    :submit="submit"
                />
            </div>
        </div>
    </Teleport>
</template>
