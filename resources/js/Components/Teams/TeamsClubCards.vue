<script setup>
import { Link } from '@inertiajs/vue3'
import TeamsClubEditForm from '@/Components/Teams/TeamsClubEditForm.vue'
import TeamsClubJobsSection from '@/Components/Teams/TeamsClubJobsSection.vue'
import TeamsClubMembersSection from '@/Components/Teams/TeamsClubMembersSection.vue'
import TeamsClubTeamGrid from '@/Components/Teams/TeamsClubTeamGrid.vue'

defineProps({
    clubs: { type: Array, default: () => [] },
    sports: { type: Array, default: () => [] },
    user: { type: Object, default: null },
    clubRoles: { type: Array, default: () => [] },
    teamRoles: { type: Array, default: () => [] },
    openClubId: { type: [Number, String, null], default: null },
    editingClubId: { type: [Number, String, null], default: null },
    clubEditTabItems: { type: Array, default: () => [] },
    editingSponsorIds: { type: Object, default: () => ({}) },
    inviteNotices: { type: Object, default: () => ({}) },
    joinRequestNotices: { type: Object, default: () => ({}) },
    processingJoinTeamIds: { type: Object, default: () => new Set() },
    processingJoinRequestIds: { type: Object, default: () => new Set() },
    teamInsights: { type: Object, default: () => ({}) },
    teamInsightsLoading: { type: Object, default: () => new Set() },
    can: { type: Function, required: true },
    initials: { type: Function, required: true },
    sportLabel: { type: Function, required: true },
    clubRoleLabel: { type: Function, required: true },
    clubRoleList: { type: Function, required: true },
    teamRoleLabel: { type: Function, required: true },
    toggleClub: { type: Function, required: true },
    activeClubEditTab: { type: Function, required: true },
    setClubEditTab: { type: Function, required: true },
    openTeamModal: { type: Function, required: true },
    inviteFormFor: { type: Function, required: true },
    loadTeamInsights: { type: Function, required: true },
    inviteUser: { type: Function, required: true },
    requestJoinTeam: { type: Function, required: true },
    approveJoinRequest: { type: Function, required: true },
    declineJoinRequest: { type: Function, required: true },
    updateClubMemberRole: { type: Function, required: true },
    clubEditFormFor: { type: Function, required: true },
    editClub: { type: Function, required: true },
    cancelClubEdit: { type: Function, required: true },
    updateClub: { type: Function, required: true },
    sponsorFormFor: { type: Function, required: true },
    resetSponsorForm: { type: Function, required: true },
    editSponsor: { type: Function, required: true },
    submitSponsor: { type: Function, required: true },
    deleteSponsor: { type: Function, required: true },
    sponsorLogoUrl: { type: Function, required: true },
    updateTeamMemberRole: { type: Function, required: true },
    removeTeamMember: { type: Function, required: true },
    deleteClub: { type: Function, required: true },
    deleteTeam: { type: Function, required: true },
    openJobModal: { type: Function, required: true },
    editJob: { type: Function, required: true },
    deleteJob: { type: Function, required: true },
})
</script>

<template>
    <div
        v-for="club in clubs"
        :key="club.id"
        class="space-y-4 rounded-xl border border-border bg-card p-4 sm:p-5"
    >
        <div
            class="flex cursor-pointer flex-col gap-3 sm:flex-row sm:items-center sm:justify-between"
            @click="toggleClub(club)"
        >
            <div class="flex min-w-0 items-center gap-3">
                <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-full bg-muted text-sm font-bold">
                    {{ initials(club.name) }}
                </div>

                <div class="min-w-0">
                    <Link
                        :href="route('auth.clubs.show', club.id)"
                        class="font-semibold text-primary hover:underline"
                        @click.stop
                    >
                        {{ club.name }}
                    </Link>

                    <p class="break-words text-xs text-secondary">
                        {{ sportLabel(club.sport_type) }}
                        · {{ club.city || 'Ort offen' }} {{ club.postal_code || '' }}
                        · {{ club.country || user?.country || 'Land offen' }}
                        · {{ club.teams.length }} Teams
                    </p>
                </div>
            </div>

            <div class="flex flex-wrap items-center gap-2 sm:justify-end">
                <button
                    v-if="club.can_manage"
                    type="button"
                    class="rounded-lg border border-border px-3 py-2 text-sm text-primary hover:bg-muted"
                    @click.stop="editClub(club)"
                >
                    Daten bearbeiten
                </button>

                <button
                    v-if="can('team.store') && club.can_manage"
                    type="button"
                    class="rounded-lg border border-border px-3 py-2 text-sm text-primary hover:bg-muted disabled:cursor-not-allowed disabled:opacity-50"
                    :disabled="club.subscription_capabilities?.can_create_team === false"
                    :title="club.subscription_capabilities?.can_create_team === false ? 'Teamlimit des aktuellen Plans erreicht' : ''"
                    @click.stop="openTeamModal(club)"
                >
                    + Team
                </button>

                <button
                    v-if="club.can_delete"
                    type="button"
                    class="rounded-lg bg-error px-3 py-2 text-sm font-semibold text-white hover:opacity-90"
                    @click.stop="deleteClub(club)"
                >
                    Löschen
                </button>
            </div>
        </div>

        <TeamsClubEditForm
            v-if="openClubId === club.id && editingClubId === club.id"
            :club="club"
            :sports="sports"
            :club-edit-tab-items="clubEditTabItems"
            :editing-sponsor-ids="editingSponsorIds"
            :active-club-edit-tab="activeClubEditTab"
            :set-club-edit-tab="setClubEditTab"
            :club-edit-form-for="clubEditFormFor"
            :cancel-club-edit="cancelClubEdit"
            :update-club="updateClub"
            :sponsor-form-for="sponsorFormFor"
            :reset-sponsor-form="resetSponsorForm"
            :edit-sponsor="editSponsor"
            :submit-sponsor="submitSponsor"
            :delete-sponsor="deleteSponsor"
            :sponsor-logo-url="sponsorLogoUrl"
        />

        <TeamsClubTeamGrid
            v-if="openClubId === club.id"
            :club="club"
            :user="user"
            :team-roles="teamRoles"
            :invite-notices="inviteNotices"
            :join-request-notices="joinRequestNotices"
            :processing-join-team-ids="processingJoinTeamIds"
            :processing-join-request-ids="processingJoinRequestIds"
            :team-insights="teamInsights"
            :team-insights-loading="teamInsightsLoading"
            :initials="initials"
            :team-role-label="teamRoleLabel"
            :update-team-member-role="updateTeamMemberRole"
            :remove-team-member="removeTeamMember"
            :delete-team="deleteTeam"
            :request-join-team="requestJoinTeam"
            :approve-join-request="approveJoinRequest"
            :decline-join-request="declineJoinRequest"
            :invite-form-for="inviteFormFor"
            :load-team-insights="loadTeamInsights"
            :invite-user="inviteUser"
        />

        <TeamsClubMembersSection
            v-if="openClubId === club.id"
            :club="club"
            :club-roles="clubRoles"
            :initials="initials"
            :club-role-label="clubRoleLabel"
            :club-role-list="clubRoleList"
            :update-club-member-role="updateClubMemberRole"
        />

        <TeamsClubJobsSection
            v-if="openClubId === club.id"
            :club="club"
            :open-job-modal="openJobModal"
            :edit-job="editJob"
            :delete-job="deleteJob"
        />
    </div>
</template>


