<script setup>
import TeamsClubCreateModal from '@/Components/Teams/TeamsClubCreateModal.vue'
import TeamsDeleteConfirmModal from '@/Components/Teams/TeamsDeleteConfirmModal.vue'
import TeamsJobModal from '@/Components/Teams/TeamsJobModal.vue'
import TeamsTeamCreateModal from '@/Components/Teams/TeamsTeamCreateModal.vue'

defineProps({
    clubCreateStep: { type: Number, default: 0 },
    clubCreateSteps: { type: Array, default: () => [] },
    clubForm: { type: Object, required: true },
    clubModalNotice: { type: Object, default: null },
    showClubModal: { type: Boolean, default: false },
    sportLabel: { type: Function, required: true },
    sports: { type: Array, default: () => [] },
    closeClubModal: { type: Function, required: true },
    createClub: { type: Function, required: true },
    nextClubStep: { type: Function, required: true },
    prevClubStep: { type: Function, required: true },
    errors: { type: Object, default: () => ({}) },
    selectedClub: { type: Object, default: null },
    showTeamModal: { type: Boolean, default: false },
    teamFormFor: { type: Function, required: true },
    closeTeamModal: { type: Function, required: true },
    createTeam: { type: Function, required: true },
    editingJobId: { type: [Number, String], default: null },
    isSubmittingJob: { type: Boolean, default: false },
    jobFormFor: { type: Function, required: true },
    jobModalNotice: { type: Object, default: null },
    selectedJobClub: { type: Object, default: null },
    showJobModal: { type: Boolean, default: false },
    closeJobModal: { type: Function, required: true },
    submitJob: { type: Function, required: true },
    deleteConfirmation: { type: String, default: '' },
    deleteReason: { type: String, default: '' },
    deleteTarget: { type: Object, default: null },
    showDeleteModal: { type: Boolean, default: false },
    closeDeleteModal: { type: Function, required: true },
    confirmDelete: { type: Function, required: true },
})

const emit = defineEmits([
    'update:clubCreateStep',
    'update:deleteConfirmation',
    'update:deleteReason',
])
</script>

<template>
    <TeamsClubCreateModal
        :club-create-step="clubCreateStep"
        :club-create-steps="clubCreateSteps"
        :club-form="clubForm"
        :club-modal-notice="clubModalNotice"
        :show="showClubModal"
        :sport-label="sportLabel"
        :sports="sports"
        @close="closeClubModal"
        @create="createClub"
        @next-step="nextClubStep"
        @previous-step="prevClubStep"
        @update:club-create-step="emit('update:clubCreateStep', $event)"
    />

    <TeamsTeamCreateModal
        :errors="errors"
        :selected-club="selectedClub"
        :show="showTeamModal"
        :sports="sports"
        :team-form-for="teamFormFor"
        @close="closeTeamModal"
        @create="createTeam"
    />

    <TeamsJobModal
        :editing-job-id="editingJobId"
        :errors="errors"
        :is-submitting-job="isSubmittingJob"
        :job-form-for="jobFormFor"
        :job-modal-notice="jobModalNotice"
        :selected-club="selectedJobClub"
        :sports="sports"
        :show="showJobModal"
        @close="closeJobModal"
        @submit="submitJob"
    />

    <TeamsDeleteConfirmModal
        :delete-confirmation="deleteConfirmation"
        :delete-reason="deleteReason"
        :delete-target="deleteTarget"
        :show="showDeleteModal"
        @close="closeDeleteModal"
        @confirm="confirmDelete"
        @update:delete-confirmation="emit('update:deleteConfirmation', $event)"
        @update:delete-reason="emit('update:deleteReason', $event)"
    />
</template>
