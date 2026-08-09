<script setup>
import AppLayout from "@/Components/Auth/Layouts/AppLayout.vue"
import ClubWorkspaceNav from "@/Components/Auth/ClubWorkspaceNav.vue"
import AppEmptyState from "@/Components/UI/AppEmptyState.vue"
import Modal from "@/Components/Modal.vue"
import SearchableSelect from "@/Components/SearchableSelect.vue"
import { useTeamsWorkspace } from "@/composables/useTeamsWorkspace"
import { usePermissions } from "@/composables/usePermissions"
import { Head, Link } from "@inertiajs/vue3"
import { useI18n } from "vue-i18n"
import { onMounted } from "vue"

defineOptions({ layout: AppLayout })

const props = defineProps({
    clubs: Array,
    sports: { type: Array, default: () => [] },
    clubRoles: { type: Array, default: () => ["owner", "admin", "manager", "member"] },
    teamRoles: { type: Array, default: () => ["Coach", "Captain", "Player"] },
    filters: { type: Object, default: () => ({}) },
    receivedInvitations: { type: Array, default: () => [] },
    startClubOnboarding: { type: Boolean, default: false },
})

const { can } = usePermissions()
const { t, te, locale, messages } = useI18n({ useScope: 'global' })
const tAuto = (value, params = {}) => {
    const source = String(value ?? '').trim()
    if (!source || locale.value === 'de') return source

    const dictionary = messages.value?.[locale.value]?.auto || {}
    if (dictionary[source]) return dictionary[source]

    return te(source) ? t(source, params) : source
}

const {
    page,
    user,
    tx,
    showClubModal,
    showTeamModal,
    showFilterModal,
    showDeleteModal,
    selectedClub,
    openClubId,
    editingClubId,
    actionNotice,
    clubModalNotice,
    deleteTarget,
    deleteConfirmation,
    deleteReason,
    errors,
    clubCreateStep,
    clubCreateSteps,
    filtersForm,
    defaultClubForm,
    clubForm,
    inviteForms,
    teamMemberForms,
    inviteNotices,
    joinRequestNotices,
    teamForms,
    teamEditForms,
    teamEditFormFor,
    editingTeamIds,
    editTeam,
    cancelTeamEdit,
    updateTeam,
    clubEditForms,
    clubEditTabs,
    sponsorForms,
    editingSponsorIds,
    processingJoinTeamIds,
    processingJoinRequestIds,
    teamInsights,
    teamInsightsLoading,
    initials,
    sportLabel,
    clubRoleLabel,
    clubRoleList,
    teamRoleLabel,
    toggleClub,
    clubEditTabItems,
    activeClubEditTab,
    setClubEditTab,
    openClubModal,
    closeClubModal,
    nextClubStep,
    prevClubStep,
    resetClubForm,
    openTeamModal,
    closeTeamModal,
    teamFormFor,
    inviteFormFor,
    teamMemberFormFor,
    availableTeamMemberOptions,
    loadTeamInsights,
    setActionNotice,
    setInviteNotice,
    setJoinRequestNotice,
    clubForTeam,
    decrementInvitationLimit,
    openDeleteModal,
    closeDeleteModal,
    closeJobModal,
    deleteJob,
    editJob,
    editingJobId,
    isSubmittingJob,
    jobFormFor,
    jobModalNotice,
    openJobModal,
    resetJobForm,
    selectedJobClub,
    showJobModal,
    submitJob,
    confirmDelete,
    createClub,
    applyFilters,
    resetFilters,
    createTeam,
    inviteUser,
    addTeamMember,
    acceptInvitation,
    declineInvitation,
    requestJoinTeam,
    approveJoinRequest,
    declineJoinRequest,
    updateClubMemberRole,
    clubEditFormFor,
    editClub,
    cancelClubEdit,
    updateClub,
    emptySponsorForm,
    sponsorFormFor,
    resetSponsorForm,
    editSponsor,
    submitSponsor,
    deleteSponsor,
    sponsorLogoUrl,
    updateTeamMemberRole,
    removeTeamMember,
    deleteClub,
    deleteTeam,
} = useTeamsWorkspace({ props, t, te })

onMounted(() => {
    if (props.startClubOnboarding && can('club.create')) {
        openClubModal()
    }
})
</script>

<template>
    <Head :title="tAuto('Vereine & Teams')" />

    <div class="space-y-6">
        <!-- HEADER -->
        <div class="flex items-start justify-between gap-4">
            <div class="min-w-0">
                <h1 class="text-2xl font-bold text-primary">
                    {{ tAuto('Vereine & Teams') }}
                </h1>

                <p class="text-sm text-secondary">
                    {{ tAuto('Verwalte Vereinsstruktur, Teams, Rollen und Einladungen') }}
                </p>
            </div>

            <!-- Mobile Plus Button -->
            <button
                v-if="can('club.create')"
                type="button"
                class="flex h-11 w-11 shrink-0 items-center justify-center rounded bg-buttonPrimary text-buttonTextPrimary shadow sm:hidden"
                @click="openClubModal"
                :aria-label="tAuto('Verein registrieren')"
            >
                <i class="las la-plus text-2xl"></i>
            </button>

            <!-- Desktop Button -->
            <button
                v-if="can('club.create')"
                type="button"
                class="hidden rounded-lg bg-buttonPrimary px-4 py-3 text-sm font-semibold text-buttonTextPrimary hover:bg-buttonPrimaryHover sm:inline-flex"
                @click="openClubModal"
            >
                + {{ tAuto('Verein registrieren') }}
            </button>
        </div>

        <ClubWorkspaceNav
            active="structure"
            :description="tAuto('Verwalte Vereinsstruktur, Teams, Rollen und Einladungen')"
        />

        <div
            v-if="actionNotice"
            class="rounded-lg border px-4 py-3 text-sm"
            :class="actionNotice.type === 'success'
                ? 'border-success/30 bg-success/10 text-success'
                : 'border-error/30 bg-error/10 text-error'"
        >
            {{ actionNotice.message }}
        </div>

        <div
            v-if="receivedInvitations.length"
            class="space-y-3 rounded-xl border border-border bg-card p-4 sm:p-5"
        >
            <div class="flex flex-col gap-1">
                <p class="text-xs font-semibold uppercase tracking-wide text-secondary">
                    {{ tAuto('Offene Team-Einladungen') }}
                </p>
                <h2 class="text-lg font-semibold text-primary">
                    {{ tAuto('Du wurdest zu einem Team eingeladen') }}
                </h2>
                <p class="text-sm text-secondary">
                    {{ tAuto('Nimm die Einladung an, um dem Team und dem zugehörigen Verein beizutreten.') }}
                </p>
            </div>

            <div class="grid gap-3 md:grid-cols-2">
                <div
                    v-for="invitation in receivedInvitations"
                    :key="invitation.id"
                    class="rounded-lg border border-border bg-bg p-4"
                >
                    <div class="flex items-start gap-3">
                        <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-full bg-muted text-sm font-bold text-primary">
                            {{ initials(invitation.team?.name) }}
                        </div>

                        <div class="min-w-0 flex-1">
                            <h3 class="truncate font-semibold text-primary">
                                {{ invitation.team?.name || tAuto('Team') }}
                            </h3>
                            <p class="mt-1 text-sm text-secondary">
                                {{ invitation.team?.club?.name || tAuto('Verein') }} - {{ tAuto('Rolle') }}: {{ teamRoleLabel(invitation.role) }}
                            </p>
                            <p v-if="invitation.inviter?.name" class="mt-1 text-xs text-secondary">
                                {{ tAuto('Eingeladen von') }} {{ invitation.inviter.name }}
                            </p>
                        </div>
                    </div>

                    <div class="mt-4 flex flex-col gap-2 sm:flex-row">
                        <button
                            type="button"
                            class="inline-flex flex-1 items-center justify-center rounded-lg bg-buttonPrimary px-4 py-2 text-sm font-semibold text-buttonTextPrimary hover:bg-buttonPrimaryHover"
                            @click="acceptInvitation(invitation)"
                        >
                            {{ tAuto('Annehmen') }}
                        </button>
                        <button
                            type="button"
                            class="inline-flex flex-1 items-center justify-center rounded-lg border border-border px-4 py-2 text-sm font-semibold text-primary hover:bg-muted"
                            @click="declineInvitation(invitation)"
                        >
                            {{ tAuto('Ablehnen') }}
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <!-- MOBILE FILTER SHORT BAR -->
        <!-- <form
            class="rounded-xl border border-border bg-card p-3 md:hidden"
            @submit.prevent="applyFilters"
        >
            <div class="flex gap-2">
                <input
                    v-model="filtersForm.search"
                    class="min-w-0 flex-1 rounded-lg border border-border bg-inputBg px-3 py-3 text-sm text-primary"
                    :placeholder="$t('Verein suchen')"
                >

                <button
                    type="submit"
                    class="flex h-11 w-11 shrink-0 items-center justify-center rounded-lg bg-buttonPrimary text-buttonTextPrimary"
                    :aria-label="$t('Suchen')"
                >
                    <i class="las la-search text-xl"></i>
                </button>

                <button
                    type="button"
                    class="relative flex h-11 w-11 shrink-0 items-center justify-center rounded-lg border border-border bg-inputBg text-primary"
                    @click="showFilterModal = true"
                    :aria-label="tAuto('Filter')"
                >
                    <i class="las la-sliders-h text-xl"></i>

                    <span
                        v-if="filtersForm.sport_type || filtersForm.location"
                        class="absolute -right-1 -top-1 h-3 w-3 rounded-full bg-buttonPrimary"
                    ></span>
                </button>
            </div>
        </form> -->

        <!-- DESKTOP FILTER -->
        <!-- <form
            class="hidden gap-3 rounded-xl border border-border bg-card p-4 md:grid md:grid-cols-4"
            @submit.prevent="applyFilters"
        >
            <input
                v-model="filtersForm.search"
                class="rounded-lg border border-border bg-inputBg px-3 py-3 text-sm text-primary"
                :placeholder="$t('Verein suchen')"
            >

            <SearchableSelect
                v-model="filtersForm.sport_type"
                :options="sports"
                value-key="slug"
                translation-prefix="sports"
                category-translation-prefix="sport_categories"
                :placeholder="$t('Sportart suchen')"
            />

            <input
                v-model="filtersForm.location"
                class="rounded-lg border border-border bg-inputBg px-3 py-3 text-sm text-primary"
                :placeholder="$t('Ort, Stadt, PLZ oder Land')"
            >

            <button
                class="rounded-lg bg-buttonPrimary px-4 py-3 text-sm font-semibold text-buttonTextPrimary hover:bg-buttonPrimaryHover"
            >
                {{ $t('Suchen') }}
            </button>
        </form> -->

        <!-- CLUBS -->
        <div
            v-for="club in clubs"
            :key="club.id"
            class="space-y-4 rounded-xl border border-border bg-card p-4 sm:p-5"
        >
            <!-- CLUB HEADER -->
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
                            · {{ club.city || tAuto('Ort offen') }} {{ club.postal_code || '' }}
                            · {{ club.country || user?.country || tAuto('Land offen') }}
                            · {{ club.teams.length }} {{ tAuto('Teams') }}
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
                        {{ tAuto('Daten bearbeiten') }}
                    </button>

                    <button
                        v-if="can('team.store') && club.can_manage"
                        type="button"
                        class="rounded-lg border border-border px-3 py-2 text-sm text-primary hover:bg-muted disabled:cursor-not-allowed disabled:opacity-50"
                        :disabled="club.subscription_capabilities?.can_create_team === false"
                        :title="club.subscription_capabilities?.can_create_team === false ? tAuto('Teamlimit des aktuellen Plans erreicht') : ''"
                        @click.stop="openTeamModal(club)"
                    >
                        + {{ tAuto('Team') }}
                    </button>

                    <button
                        v-if="club.can_delete"
                        type="button"
                        class="rounded-lg bg-error px-3 py-2 text-sm font-semibold text-white hover:opacity-90"
                        @click.stop="deleteClub(club)"
                    >
                        {{ tAuto('Löschen') }}
                    </button>
                </div>
            </div>

            <!-- CLUB EDIT -->
            <form
                v-if="openClubId === club.id && editingClubId === club.id"
                class="rounded-xl border border-border bg-bg p-4 sm:p-5"
                @submit.prevent="updateClub(club)"
            >
                <div class="mb-4 flex flex-col gap-2 sm:flex-row sm:items-end sm:justify-between">
                    <div>
                        <h2 class="font-semibold text-primary">{{ tAuto('Vereinsdaten bearbeiten') }}</h2>
                        <p class="text-xs text-secondary">
                            {{ tAuto('Basisdaten, Adresse und Sportart pflegen. Offizielle Prüfung läuft separat über Admin.') }}
                        </p>
                    </div>
                    <span
                        class="w-fit rounded-full px-3 py-1 text-xs font-semibold"
                        :class="club.verification_status === 'verified'
                            ? 'bg-success/10 text-success'
                            : club.verification_status === 'rejected'
                                ? 'bg-error/10 text-error'
                                : 'bg-warning/10 text-warning'"
                    >
                        {{ club.verification_status === 'verified' ? tAuto('Freigegeben') : club.verification_status === 'rejected' ? tAuto('Abgelehnt') : tAuto('Wartet auf Prüfung') }}
                    </span>
                </div>

                <div class="mb-4 flex flex-wrap gap-2 border-b border-border pb-2">
                    <button
                        v-for="tab in clubEditTabItems"
                        :key="tab.key"
                        type="button"
                        class="rounded-lg px-3 py-2 text-sm font-semibold transition"
                        :class="activeClubEditTab(club) === tab.key ? 'bg-buttonPrimary text-buttonTextPrimary' : 'text-secondary hover:bg-card hover:text-primary'"
                        @click="setClubEditTab(club, tab.key)"
                    >
                        {{ tab.label }}
                    </button>
                </div>

                <div class="grid gap-3 md:grid-cols-2 xl:grid-cols-3">
                    <label v-if="activeClubEditTab(club) === 'basis'" class="block xl:col-span-2">
                        <span class="text-xs font-semibold uppercase text-secondary">{{ tAuto('Vereinsname') }}</span>
                        <input v-model="clubEditFormFor(club).name" required class="mt-1 w-full rounded-lg border border-border bg-inputBg px-3 py-2 text-sm text-primary">
                    </label>

                    <label v-if="activeClubEditTab(club) === 'basis'" class="block">
                        <span class="text-xs font-semibold uppercase text-secondary">{{ tAuto('Sportart') }}</span>
                        <SearchableSelect
                            v-model="clubEditFormFor(club).sport_type"
                            class="mt-1 w-full"
                            :options="sports"
                            value-key="slug"
                            translation-prefix="sports"
                            category-translation-prefix="sport_categories"
                            :placeholder="tAuto('Sportart suchen')"
                        />
                    </label>

                    <label v-if="activeClubEditTab(club) === 'sichtbarkeit'" class="flex items-start gap-3 rounded-lg border border-border bg-card p-3 text-sm text-primary">
                        <input
                            v-model="clubEditFormFor(club).is_listed"
                            type="checkbox"
                            class="mt-1 rounded border-border bg-inputBg"
                        >
                        <span>
                            <span class="block font-semibold">{{ tAuto('Verein auflisten') }}</span>
                            <span class="block text-xs text-secondary">{{ tAuto('Der Verein darf in Vereinslisten und Auswahlfeldern sichtbar sein.') }}</span>
                        </span>
                    </label>

                    <label v-if="activeClubEditTab(club) === 'sichtbarkeit'" class="flex items-start gap-3 rounded-lg border border-border bg-card p-3 text-sm text-primary">
                        <input
                            v-model="clubEditFormFor(club).teams_are_listed"
                            type="checkbox"
                            class="mt-1 rounded border-border bg-inputBg"
                        >
                        <span>
                            <span class="block font-semibold">{{ tAuto('Teams auflisten') }}</span>
                            <span class="block text-xs text-secondary">{{ tAuto('Teams dürfen außerhalb des internen Vereinsbereichs sichtbar sein.') }}</span>
                        </span>
                    </label>

                    <label v-if="activeClubEditTab(club) === 'sichtbarkeit'" class="flex items-start gap-3 rounded-lg border border-border bg-card p-3 text-sm text-primary">
                        <input
                            v-model="clubEditFormFor(club).members_can_post_to_club"
                            type="checkbox"
                            class="mt-1 rounded border-border bg-inputBg"
                        >
                        <span>
                            <span class="block font-semibold">{{ tAuto('Vereinsbeiträge erlauben') }}</span>
                            <span class="block text-xs text-secondary">{{ tAuto('Normale Mitglieder dürfen Beiträge für den Verein erstellen.') }}</span>
                        </span>
                    </label>

                    <label v-if="activeClubEditTab(club) === 'sichtbarkeit'" class="flex items-start gap-3 rounded-lg border border-border bg-card p-3 text-sm text-primary">
                        <input
                            v-model="clubEditFormFor(club).members_can_post_to_teams"
                            type="checkbox"
                            class="mt-1 rounded border-border bg-inputBg"
                        >
                        <span>
                            <span class="block font-semibold">{{ tAuto('Teambeiträge erlauben') }}</span>
                            <span class="block text-xs text-secondary">{{ tAuto('Normale Teammitglieder dürfen Beiträge für ihre Teams erstellen.') }}</span>
                        </span>
                    </label>

                    <label v-if="activeClubEditTab(club) === 'basis'" class="block xl:col-span-2">
                        <span class="text-xs font-semibold uppercase text-secondary">{{ tAuto('Vereinsnummer zur Prüfung') }}</span>
                        <input
                            v-model="clubEditFormFor(club).official_club_number"
                            class="mt-1 w-full rounded-lg border border-border bg-inputBg px-3 py-2 text-sm text-primary"
                            :placeholder="tAuto('z. B. Vereinsregister- oder Verbandsnummer')"
                        >
                        <span class="mt-1 block text-xs text-secondary">
                            {{ tAuto('Neue oder geänderte Nummern werden zur Admin-Prüfung vorgemerkt.') }}
                        </span>
                    </label>

                    <label v-if="activeClubEditTab(club) === 'adresse'" class="block">
                        <span class="text-xs font-semibold uppercase text-secondary">{{ tAuto('Land') }}</span>
                        <select v-model="clubEditFormFor(club).country" required class="mt-1 w-full rounded-lg border border-border bg-inputBg px-3 py-2 text-sm text-primary">
                            <option value="DE">{{ tAuto('Deutschland') }}</option>
                            <option value="AT">{{ tAuto('Österreich') }}</option>
                            <option value="CH">{{ tAuto('Schweiz') }}</option>
                            <option value="FR">{{ tAuto('Frankreich') }}</option>
                            <option value="NL">{{ tAuto('Niederlande') }}</option>
                            <option value="BE">{{ tAuto('Belgien') }}</option>
                            <option value="TR">{{ tAuto('Türkei') }}</option>
                            <option value="US">{{ tAuto('USA') }}</option>
                        </select>
                    </label>

                    <label v-if="activeClubEditTab(club) === 'adresse'" class="block">
                        <span class="text-xs font-semibold uppercase text-secondary">{{ tAuto('Stadt') }}</span>
                        <input v-model="clubEditFormFor(club).city" class="mt-1 w-full rounded-lg border border-border bg-inputBg px-3 py-2 text-sm text-primary">
                    </label>

                    <label v-if="activeClubEditTab(club) === 'adresse'" class="block">
                        <span class="text-xs font-semibold uppercase text-secondary">{{ tAuto('PLZ') }}</span>
                        <input v-model="clubEditFormFor(club).postal_code" class="mt-1 w-full rounded-lg border border-border bg-inputBg px-3 py-2 text-sm text-primary">
                    </label>

                    <label v-if="activeClubEditTab(club) === 'adresse'" class="block">
                        <span class="text-xs font-semibold uppercase text-secondary">{{ tAuto('Region') }}</span>
                        <input v-model="clubEditFormFor(club).state" class="mt-1 w-full rounded-lg border border-border bg-inputBg px-3 py-2 text-sm text-primary">
                    </label>

                    <label v-if="activeClubEditTab(club) === 'adresse'" class="block">
                        <span class="text-xs font-semibold uppercase text-secondary">{{ tAuto('Straße') }}</span>
                        <input v-model="clubEditFormFor(club).street" class="mt-1 w-full rounded-lg border border-border bg-inputBg px-3 py-2 text-sm text-primary">
                    </label>

                    <label v-if="activeClubEditTab(club) === 'adresse'" class="block">
                        <span class="text-xs font-semibold uppercase text-secondary">{{ tAuto('Hausnummer') }}</span>
                        <input v-model="clubEditFormFor(club).house_number" class="mt-1 w-full rounded-lg border border-border bg-inputBg px-3 py-2 text-sm text-primary">
                    </label>

                    <div v-if="activeClubEditTab(club) === 'bank'" class="rounded-lg border border-border bg-card p-3 md:col-span-2 xl:col-span-3">
                        <p class="text-xs font-semibold uppercase text-secondary">{{ tAuto('Bankkonto für Mitglieder-Überweisungen') }}</p>
                        <p class="mt-1 text-xs text-secondary">
                            {{ tAuto('Diese Daten werden Mitgliedern bei offenen Vereinsrechnungen angezeigt.') }}
                        </p>
                        <div class="mt-3 grid gap-3 md:grid-cols-3">
                            <label class="block">
                                <span class="text-xs font-semibold uppercase text-secondary">{{ tAuto('Kontoinhaber') }}</span>
                                <input
                                    v-model="clubEditFormFor(club).sepa_account_holder"
                                    class="mt-1 w-full rounded-lg border border-border bg-inputBg px-3 py-2 text-sm text-primary"
                                    :placeholder="tAuto('Name laut Bankkonto')"
                                >
                            </label>
                            <label class="block">
                                <span class="text-xs font-semibold uppercase text-secondary">{{ tAuto('IBAN') }}</span>
                                <input
                                    v-model="clubEditFormFor(club).sepa_iban"
                                    class="mt-1 w-full rounded-lg border border-border bg-inputBg px-3 py-2 text-sm text-primary"
                                    :placeholder="'DE...'"
                                >
                            </label>
                            <label class="block">
                                <span class="text-xs font-semibold uppercase text-secondary">{{ tAuto('BIC') }}</span>
                                <input
                                    v-model="clubEditFormFor(club).sepa_bic"
                                    class="mt-1 w-full rounded-lg border border-border bg-inputBg px-3 py-2 text-sm text-primary"
                                    :placeholder="'GENODE...'"
                                >
                            </label>
                        </div>
                    </div>

                    <div v-if="activeClubEditTab(club) === 'sponsoren'" class="space-y-4 rounded-lg border border-border bg-card p-3 md:col-span-2 xl:col-span-3">
                        <div class="flex flex-col gap-2 sm:flex-row sm:items-start sm:justify-between">
                            <div>
                        <p class="text-xs font-semibold uppercase text-secondary">{{ tAuto('Vereins-Sponsoren') }}</p>
                                <p class="mt-1 text-xs text-secondary">
                                    {{ tAuto('Pflege Sponsoren, die öffentlich dem Verein zugeordnet werden.') }}
                                </p>
                            </div>
                            <span class="rounded-full bg-inputBg px-3 py-1 text-xs font-semibold text-secondary">
                                {{ club.sponsors?.length || 0 }} {{ tAuto('Sponsoren') }}
                            </span>
                        </div>

                        <div v-if="club.subscription_capabilities?.sponsors === false" class="rounded-lg border border-warning/30 bg-warning/10 p-3 text-sm text-warning">
                            {{ tAuto('Sponsorenverwaltung ist ab dem Club-Plan verfügbar.') }}
                        </div>

                        <div class="grid gap-3 md:grid-cols-2 xl:grid-cols-3">
                            <label class="block">
                                <span class="text-xs font-semibold uppercase text-secondary">{{ tAuto('Sponsorname') }}</span>
                                <input v-model="sponsorFormFor(club).name" class="mt-1 w-full rounded-lg border border-border bg-inputBg px-3 py-2 text-sm text-primary" :placeholder="tAuto('Sponsorname')">
                            </label>
                            <label class="block">
                                <span class="text-xs font-semibold uppercase text-secondary">{{ tAuto('Kontaktperson') }}</span>
                                <input v-model="sponsorFormFor(club).contact_name" class="mt-1 w-full rounded-lg border border-border bg-inputBg px-3 py-2 text-sm text-primary" :placeholder="tAuto('Ansprechpartner')">
                            </label>
                            <label class="block">
                                <span class="text-xs font-semibold uppercase text-secondary">{{ tAuto('E-Mail') }}</span>
                                <input v-model="sponsorFormFor(club).email" type="email" class="mt-1 w-full rounded-lg border border-border bg-inputBg px-3 py-2 text-sm text-primary" placeholder="sponsor@example.com">
                            </label>
                            <label class="block">
                                <span class="text-xs font-semibold uppercase text-secondary">{{ tAuto('Website') }}</span>
                                <input v-model="sponsorFormFor(club).website" type="url" class="mt-1 w-full rounded-lg border border-border bg-inputBg px-3 py-2 text-sm text-primary" :placeholder="'https://...'">
                            </label>
                            <label class="block">
                                <span class="text-xs font-semibold uppercase text-secondary">{{ tAuto('Budget / Betrag') }}</span>
                                <input v-model="sponsorFormFor(club).amount" type="number" min="0" step="0.01" class="mt-1 w-full rounded-lg border border-border bg-inputBg px-3 py-2 text-sm text-primary" placeholder="0,00">
                            </label>
                            <div class="grid gap-3 sm:grid-cols-2">
                                <label class="block">
                                    <span class="text-xs font-semibold uppercase text-secondary">{{ tAuto('Start') }}</span>
                                    <input v-model="sponsorFormFor(club).starts_at" type="date" class="mt-1 w-full rounded-lg border border-border bg-inputBg px-3 py-2 text-sm text-primary">
                                </label>
                                <label class="block">
                                    <span class="text-xs font-semibold uppercase text-secondary">{{ tAuto('Ende') }}</span>
                                    <input v-model="sponsorFormFor(club).ends_at" type="date" class="mt-1 w-full rounded-lg border border-border bg-inputBg px-3 py-2 text-sm text-primary">
                                </label>
                            </div>
                            <label class="block md:col-span-1 xl:col-span-3">
                                <span class="text-xs font-semibold uppercase text-secondary">{{ tAuto('Logo für helle Flächen') }}</span>
                                <input v-model="sponsorFormFor(club).logo_light" class="mt-1 w-full rounded-lg border border-border bg-inputBg px-3 py-2 text-sm text-primary" placeholder="sponsors/logo-light.webp oder https://...">
                            </label>
                            <label class="block md:col-span-1 xl:col-span-3">
                                <span class="text-xs font-semibold uppercase text-secondary">{{ tAuto('Logo für dunkle Flächen') }}</span>
                                <input v-model="sponsorFormFor(club).logo_dark" class="mt-1 w-full rounded-lg border border-border bg-inputBg px-3 py-2 text-sm text-primary" placeholder="sponsors/logo-dark.webp oder https://...">
                            </label>
                        </div>

                        <div class="flex flex-wrap gap-2">
                            <button
                                type="button"
                                class="rounded-lg bg-buttonPrimary px-4 py-2 text-sm font-semibold text-buttonTextPrimary disabled:cursor-not-allowed disabled:opacity-50"
                                :disabled="club.subscription_capabilities?.sponsors === false"
                                @click="submitSponsor(club)"
                            >
                                {{ editingSponsorIds[club.id] ? tAuto('Speichern') : tAuto('Erstellen') }}
                            </button>
                            <button
                                v-if="editingSponsorIds[club.id]"
                                type="button"
                                class="rounded-lg border border-border px-4 py-2 text-sm font-semibold text-primary"
                                @click="resetSponsorForm(club)"
                            >
                                {{ tAuto('Abbrechen') }}
                            </button>
                        </div>

                        <div class="divide-y divide-border overflow-hidden rounded-lg border border-border">
                            <div
                                v-for="sponsor in club.sponsors || []"
                                :key="sponsor.id"
                                class="flex flex-col gap-3 bg-bg p-3 sm:flex-row sm:items-center sm:justify-between"
                            >
                                <div class="flex min-w-0 items-center gap-3">
                                    <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-lg bg-inputBg text-xs font-bold text-primary">
                                        <img v-if="sponsorLogoUrl(sponsor)" :src="sponsorLogoUrl(sponsor)" :alt="sponsor.name" width="40" height="40" loading="lazy" decoding="async" class="h-full w-full object-contain p-1">
                                        <span v-else>{{ sponsor.name?.slice(0, 2)?.toUpperCase() }}</span>
                                    </div>
                                    <div class="min-w-0">
                                        <p class="truncate text-sm font-semibold text-primary">{{ sponsor.name }}</p>
                                        <p class="text-xs text-secondary">
                                            {{ sponsor.amount || '-' }} EUR · {{ sponsor.starts_at || '-' }} bis {{ sponsor.ends_at || '-' }}
                                        </p>
                                    </div>
                                </div>
                                <div class="flex flex-wrap gap-2">
                                    <button type="button" class="rounded-lg border border-border px-3 py-1 text-sm font-semibold text-primary" @click="editSponsor(club, sponsor)">
                                        {{ tAuto('Bearbeiten') }}
                                    </button>
                                    <button type="button" class="rounded-lg border border-error px-3 py-1 text-sm font-semibold text-error" @click="deleteSponsor(club, sponsor)">
                                        {{ tAuto('Löschen') }}
                                    </button>
                                </div>
                            </div>
                            <div v-if="!(club.sponsors || []).length" class="bg-bg p-4 text-sm text-secondary">
                                {{ tAuto('Noch keine Sponsoren für diesen Verein vorhanden.') }}
                            </div>
                        </div>
                    </div>
                </div>

                <div class="mt-4 flex flex-col-reverse gap-2 sm:flex-row sm:justify-end">
                    <button type="button" class="rounded-lg border border-border px-4 py-2 text-sm font-semibold text-primary hover:bg-muted" @click="cancelClubEdit">
                        {{ tAuto('Abbrechen') }}
                    </button>
                    <button class="rounded-lg bg-buttonPrimary px-4 py-2 text-sm font-semibold text-buttonTextPrimary">
                        {{ tAuto('Speichern') }}
                    </button>
                </div>
            </form>

            <!-- TEAMS GRID -->
            <div
                v-if="openClubId === club.id"
                class="grid gap-4 md:grid-cols-2 xl:grid-cols-3"
            >
                <div
                    v-for="team in club.teams"
                    :key="team.id"
                    class="space-y-4 rounded-xl border border-border bg-bg p-4"
                >
                    <div class="flex items-start justify-between gap-3">
                        <div class="flex min-w-0 items-center gap-3">
                            <div class="flex h-9 w-9 shrink-0 items-center justify-center rounded-full bg-muted text-xs font-bold">
                                {{ initials(team.name) }}
                            </div>

                            <div class="min-w-0">
                                <Link
                                    :href="route('auth.teams.show', team.id)"
                                    class="font-semibold text-primary hover:underline"
                                >
                                    {{ team.name }}
                                </Link>

                                <p class="text-xs text-secondary">
                                    {{ team.users?.length || 0 }} {{ tAuto('Mitglieder') }}
                                </p>
                            </div>
                        </div>

                        <div class="flex shrink-0 items-center gap-2">
                            <span class="rounded-full bg-muted px-2 py-1 text-xs text-secondary">
                                {{ tAuto('Aktiv') }}
                            </span>

                            <button
                                v-if="team.can_manage"
                                type="button"
                                class="rounded border border-border px-2 py-1 text-xs font-semibold text-primary hover:bg-muted"
                                @click="editingTeamIds.has(team.id) ? cancelTeamEdit(team) : editTeam(team)"
                            >
                                {{ editingTeamIds.has(team.id) ? tAuto('Schließen') : tAuto('Bearbeiten') }}
                            </button>

                            <button
                                v-if="team.can_delete"
                                type="button"
                                class="rounded bg-error px-2 py-1 text-xs font-semibold text-white hover:opacity-90"
                                @click="deleteTeam(team)"
                            >
                                {{ tAuto('Löschen') }}
                            </button>
                        </div>
                    </div>

                    <form
                        v-if="team.can_manage && editingTeamIds.has(team.id)"
                        class="grid gap-3 rounded-lg border border-border bg-card p-3"
                        @submit.prevent="updateTeam(team)"
                    >
                        <label class="grid gap-1 text-sm font-semibold text-primary">
                            {{ tAuto('Teamname') }}
                            <input
                                v-model="teamEditFormFor(team).name"
                                required
                                class="rounded-lg border border-border bg-inputBg px-3 py-2 text-sm text-primary"
                                :placeholder="tAuto('Teamname')"
                            >
                        </label>

                        <label class="grid gap-1 text-sm font-semibold text-primary">
                            {{ tAuto('Sportart') }}
                            <select
                                v-model="teamEditFormFor(team).sport_type"
                                class="rounded-lg border border-border bg-inputBg px-3 py-2 text-sm text-primary"
                            >
                                <option value="">{{ tAuto('Sportart offen') }}</option>
                                <option
                                    v-for="sport in sports"
                                    :key="sport.slug || sport.id"
                                    :value="sport.slug || sport.name"
                                >
                                    {{ sportLabel(sport.slug || sport.name) }}
                                </option>
                            </select>
                        </label>

                        <div class="flex flex-col-reverse gap-2 sm:flex-row sm:justify-end">
                            <button
                                type="button"
                                class="rounded-lg border border-border px-3 py-2 text-sm font-semibold text-primary hover:bg-muted"
                                @click="cancelTeamEdit(team)"
                            >
                                {{ tAuto('Abbrechen') }}
                            </button>
                            <button
                                type="submit"
                                class="rounded-lg bg-buttonPrimary px-3 py-2 text-sm font-semibold text-buttonTextPrimary hover:bg-buttonPrimaryHover"
                            >
                                {{ tAuto('Speichern') }}
                            </button>
                        </div>
                    </form>

                    <div class="space-y-2">
                        <div
                            v-for="member in team.users"
                            :key="member.id"
                            class="flex items-center gap-3 rounded-lg p-2 hover:bg-muted"
                        >
                            <div class="flex h-8 w-8 shrink-0 items-center justify-center rounded-full bg-muted text-xs font-semibold">
                                {{ initials(member.name) }}
                            </div>

                            <div class="min-w-0 flex-1">
                                <p class="truncate text-sm font-medium text-primary">
                                    {{ member.name }}
                                </p>

                                <p class="text-xs text-secondary">
                                    {{ member.pivot.role }}
                                </p>
                            </div>

                            <select
                                v-if="team.can_manage"
                                v-model="member.pivot.role"
                                class="rounded border border-border bg-card px-2 py-1 text-xs text-primary"
                                @change="updateTeamMemberRole(team, member)"
                            >
                                <option
                                    v-for="role in teamRoles"
                                    :key="role"
                                    :value="role"
                                >
                                    {{ teamRoleLabel(role) }}
                                </option>
                            </select>

                            <span
                                v-else
                                class="rounded-full bg-muted px-2 py-1 text-xs text-secondary"
                            >
                                {{ teamRoleLabel(member.pivot.role) }}
                            </span>

                            <button
                                v-if="member.id === user?.id || team.can_remove_members"
                                type="button"
                                class="rounded border border-border px-2 py-1 text-xs font-semibold text-primary hover:border-error/40 hover:bg-error/10 hover:text-error"
                                @click="removeTeamMember(team, member)"
                            >
                                {{ member.id === user?.id ? tAuto('Team verlassen') : tAuto('Entfernen') }}
                            </button>
                        </div>

                        <AppEmptyState
                            v-if="!team.users?.length"
                            :title="tAuto('Noch keine Teammitglieder')"
                            :description="tAuto('Sobald Mitglieder eingeladen wurden oder dem Team beitreten, erscheinen sie hier.')"
                            compact
                        >
                            <template #icon>
                                <i class="las la-user-friends text-xl" aria-hidden="true"></i>
                            </template>
                        </AppEmptyState>
                    </div>

                    <div
                        v-if="team.can_request_join || team.viewer_pending_join_request_id || team.pending_join_requests?.length"
                        class="space-y-2 rounded-lg border border-border bg-card p-3"
                    >
                        <button
                            v-if="team.can_request_join"
                            type="button"
                            class="w-full rounded bg-buttonPrimary px-3 py-2 text-sm font-semibold text-buttonTextPrimary"
                            :disabled="processingJoinTeamIds.has(team.id)"
                            :class="{ 'opacity-60': processingJoinTeamIds.has(team.id) }"
                            @click="requestJoinTeam(team)"
                        >
                            {{ processingJoinTeamIds.has(team.id) ? tAuto('Wird gesendet...') : tAuto('Beitritt anfragen') }}
                        </button>

                        <p
                            v-if="joinRequestNotices[team.id]"
                            class="rounded border px-3 py-2 text-xs font-semibold"
                            :class="joinRequestNotices[team.id].type === 'success'
                                ? 'border-success/30 bg-success/10 text-success'
                                : 'border-error/30 bg-error/10 text-error'"
                        >
                            {{ joinRequestNotices[team.id].message }}
                        </p>

                        <p
                            v-else-if="team.viewer_pending_join_request_id"
                            class="rounded border border-air-blue/30 bg-air-blue/10 px-3 py-2 text-xs font-semibold text-air-blue"
                        >
                            {{ tAuto('Deine Beitrittsanfrage wartet auf Freigabe.') }}
                        </p>

                        <div v-if="team.pending_join_requests?.length" class="space-y-2">
                            <p class="text-xs font-semibold uppercase text-secondary">
                                {{ tAuto('Offene Team-Anfragen') }}
                            </p>

                            <div
                                v-for="request in team.pending_join_requests"
                                :key="request.id"
                                class="flex flex-col gap-2 rounded border border-border bg-bg p-2 sm:flex-row sm:items-center sm:justify-between"
                            >
                                <div class="min-w-0">
                                    <p class="truncate text-sm font-semibold text-primary">
                                        {{ request.user?.name || tAuto('Mitglied') }}
                                    </p>
                                    <p class="truncate text-xs text-secondary">
                                        {{ request.user?.email }}
                                    </p>
                                </div>

                                <div class="flex gap-2">
                                    <button
                                        type="button"
                                        class="rounded bg-buttonPrimary px-3 py-1.5 text-xs font-semibold text-buttonTextPrimary"
                                        :disabled="processingJoinRequestIds.has(request.id)"
                                        :class="{ 'opacity-60': processingJoinRequestIds.has(request.id) }"
                                        @click="approveJoinRequest(request)"
                                    >
                                        {{ tAuto('Annehmen') }}
                                    </button>
                                    <button
                                        type="button"
                                        class="rounded border border-border px-3 py-1.5 text-xs font-semibold text-primary hover:bg-muted"
                                        :disabled="processingJoinRequestIds.has(request.id)"
                                        :class="{ 'opacity-60': processingJoinRequestIds.has(request.id) }"
                                        @click="declineJoinRequest(request)"
                                    >
                                        {{ tAuto('Ablehnen') }}
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>

                    <form
                        v-if="team.can_manage"
                        class="grid gap-2 border-t border-border pt-3 sm:grid-cols-[minmax(0,1fr)_10rem_auto]"
                        @submit.prevent="addTeamMember(team)"
                    >
                        <select
                            v-model="teamMemberFormFor(team).user_id"
                            class="min-w-0 rounded border border-border bg-inputBg px-3 py-2 text-sm text-primary"
                        >
                            <option value="">{{ tAuto('Vereinsmitglied wählen') }}</option>
                            <option
                                v-for="member in availableTeamMemberOptions(team)"
                                :key="member.id"
                                :value="member.id"
                            >
                                {{ member.name }} · {{ member.email }}
                            </option>
                        </select>

                        <select
                            v-model="teamMemberFormFor(team).role"
                            class="rounded border border-border bg-inputBg px-3 py-2 text-sm text-primary"
                        >
                            <option
                                v-for="role in teamRoles"
                                :key="role"
                                :value="role"
                            >
                                {{ teamRoleLabel(role) }}
                            </option>
                        </select>

                        <button
                            type="submit"
                            class="rounded bg-buttonPrimary px-3 py-2 text-sm font-semibold text-buttonTextPrimary disabled:opacity-50"
                            :disabled="!teamMemberFormFor(team).user_id"
                        >
                            {{ tAuto('Hinzufügen') }}
                        </button>
                    </form>

                    <form
                        v-if="team.can_manage"
                        class="flex flex-col gap-2 sm:flex-row"
                        @submit.prevent="inviteUser(team)"
                    >
                        <input
                            v-model="inviteFormFor(team).email"
                            type="email"
                            :placeholder="tAuto('E-Mail')"
                            class="min-w-0 flex-1 rounded border border-border bg-inputBg px-3 py-2 text-sm text-primary"
                        >

                        <button
                            class="rounded bg-buttonPrimary px-3 py-2 text-sm text-buttonTextPrimary disabled:opacity-50"
                            :disabled="club.subscription_capabilities?.member_invitation_remaining_today === 0"
                        >
                            {{ tAuto('Einladen') }}
                        </button>
                    </form>

                    <p
                        v-if="club.subscription_capabilities?.member_invitation_daily_limit"
                        class="text-xs text-secondary"
                    >
                        {{ tAuto('Free-Limit') }}: {{ club.subscription_capabilities.member_invitation_remaining_today }} {{ tAuto('von') }} {{ club.subscription_capabilities.member_invitation_daily_limit }} {{ tAuto('Einladungen heute übrig.') }}
                    </p>

                    <p
                        v-if="inviteNotices[team.id]"
                        class="rounded-lg border px-3 py-2 text-xs font-semibold"
                        :class="inviteNotices[team.id].type === 'success'
                            ? 'border-success/30 bg-success/10 text-success'
                            : 'border-error/30 bg-error/10 text-error'"
                    >
                        {{ inviteNotices[team.id].message }}
                    </p>
                </div>

                <AppEmptyState
                    v-if="!club.teams?.length"
                    class="md:col-span-2 xl:col-span-3"
                    :title="tAuto('Noch keine Teams')"
                    :description="tAuto('Lege das erste Team für diesen Verein an, damit Mitglieder, Trainings und Events sauber zugeordnet werden können.')"
                    compact
                >
                    <template #icon>
                        <i class="las la-users text-xl" aria-hidden="true"></i>
                    </template>
                </AppEmptyState>
            </div>

            <!-- CLUB MEMBERS -->
            <div
                v-if="openClubId === club.id"
                class="rounded-xl border border-border bg-bg p-4"
            >
                <div class="mb-4 flex flex-col gap-3 sm:flex-row sm:items-end sm:justify-between">
                    <div>
                        <h2 class="font-semibold text-primary">
                            {{ tAuto('Vereinsmitglieder') }}
                        </h2>

                        <p class="text-xs text-secondary">
                            {{ tAuto('Owner, Admins und Manager steuern die Rollen im Verein.') }}
                        </p>
                    </div>

                    <span class="rounded-full bg-muted px-3 py-1 text-xs text-secondary">
                        {{ club.users?.length || 0 }} {{ tAuto('Mitglieder') }}
                    </span>
                </div>

                <div class="grid gap-2 md:grid-cols-2">
                    <div
                        v-for="member in club.users"
                        :key="member.id"
                        class="flex items-center gap-3 rounded-lg border border-border bg-card p-3"
                    >
                        <img
                            v-if="member.profile_photo_thumb"
                            :src="member.profile_photo_thumb"
                            :alt="member.name"
                            width="36"
                            height="36"
                            loading="lazy"
                            decoding="async"
                            class="h-9 w-9 shrink-0 rounded-full object-cover"
                        >

                        <div
                            v-else
                            class="flex h-9 w-9 shrink-0 items-center justify-center rounded-full bg-muted text-xs font-semibold text-primary"
                        >
                            {{ initials(member.name) }}
                        </div>

                        <div class="min-w-0 flex-1">
                            <p class="truncate text-sm font-medium text-primary">
                                {{ member.name }}
                            </p>

                            <p class="truncate text-xs text-secondary">
                                {{ member.email }}
                            </p>
                        </div>

                        <select
                            v-if="club.can_manage"
                            v-model="member.pivot.role"
                            class="rounded border border-border bg-inputBg px-2 py-1 text-xs text-primary"
                            @change="updateClubMemberRole(club, member)"
                        >
                            <option
                                v-for="role in clubRoles"
                                :key="role"
                                :value="role"
                            >
                                {{ clubRoleLabel(role) }}
                            </option>
                        </select>

                        <span
                            v-else
                            class="rounded-full bg-muted px-2 py-1 text-xs text-secondary"
                        >
                            {{ clubRoleList(member).map(clubRoleLabel).join(', ') }}
                        </span>
                    </div>

                    <AppEmptyState
                        v-if="!club.users?.length"
                        class="md:col-span-2"
                        :title="tAuto('Noch keine Vereinsmitglieder')"
                        :description="tAuto('Eingeladene oder angenommene Mitglieder erscheinen hier mit ihren Rollen.')"
                        compact
                    >
                        <template #icon>
                            <i class="las la-id-badge text-xl" aria-hidden="true"></i>
                        </template>
                    </AppEmptyState>
                </div>
            </div>

            <!-- JOBS -->
            <div
                v-if="openClubId === club.id"
                class="rounded-xl border border-border bg-bg p-4"
            >
                <div class="mb-4 flex items-center justify-between gap-3">
                    <div>
                        <p class="text-xs font-semibold uppercase tracking-wide text-secondary">{{ tAuto('Engagement') }}</p>
                        <h2 class="mt-1 text-lg font-semibold text-primary">
                            {{ tAuto('Jobs & Ehrenamt') }}
                        </h2>

                        <p class="text-xs text-secondary">
                            {{ tAuto('Veröffentliche bezahlte Stellen, Ehrenamtsrollen und konkrete Aufgaben direkt auf der Jobs-Seite.') }}
                        </p>
                    </div>

                    <div class="flex flex-wrap items-center gap-2">
                        <span class="rounded-full bg-muted px-3 py-1 text-xs text-secondary">
                            {{ club.jobs?.length || 0 }} {{ tAuto('Einträge') }}
                        </span>
                        <Link
                            v-if="club.can_manage_jobs"
                            :href="route('auth.recruiting-pipeline.index')"
                            class="rounded-lg border border-border px-4 py-2 text-sm font-semibold text-primary hover:bg-muted"
                        >
                            {{ t('recruiting_pipeline.page_title') }}
                        </Link>
                        <button
                            v-if="club.can_manage_jobs"
                            type="button"
                            class="rounded-lg bg-buttonPrimary px-4 py-2 text-sm font-semibold text-buttonTextPrimary"
                            @click="openJobModal(club)"
                        >
                            {{ tAuto('Eintrag hinzufügen') }}
                        </button>
                    </div>
                </div>

                <form
                    v-if="false"
                    class="grid gap-3 rounded-lg border border-border bg-card p-4 md:grid-cols-2"
                    @submit.prevent="submitJob(club)"
                >
                    <input
                        v-model="jobFormFor(club).title"
                        required
                        class="rounded-lg border border-border bg-inputBg px-3 py-2 text-sm text-primary"
                        :placeholder="tAuto('Titel, z.B. Jugendtrainer U15')"
                    >

                    <select
                        v-model="jobFormFor(club).type"
                        class="rounded-lg border border-border bg-inputBg px-3 py-2 text-sm text-primary"
                    >
                        <option value="volunteer">{{ tAuto('Ehrenamt') }}</option>
                        <option value="professional">{{ tAuto('Beruf / bezahlte Stelle') }}</option>
                    </select>

                    <input
                        v-model="jobFormFor(club).location"
                        class="rounded-lg border border-border bg-inputBg px-3 py-2 text-sm text-primary"
                        :placeholder="tAuto('Ort / Remote')"
                    >

                    <input
                        v-model="jobFormFor(club).workload"
                        class="rounded-lg border border-border bg-inputBg px-3 py-2 text-sm text-primary"
                        :placeholder="tAuto('Umfang, z.B. 6 Std./Woche')"
                    >

                    <input
                        v-model="jobFormFor(club).employment_type"
                        class="rounded-lg border border-border bg-inputBg px-3 py-2 text-sm text-primary"
                        :placeholder="tAuto('Art, z.B. Teilzeit, Minijob, Ehrenamt')"
                    >

                    <input
                        v-model="jobFormFor(club).contact_email"
                        type="email"
                        class="rounded-lg border border-border bg-inputBg px-3 py-2 text-sm text-primary"
                        :placeholder="tAuto('Kontakt E-Mail')"
                    >

                    <input
                        v-model="jobFormFor(club).application_url"
                        class="rounded-lg border border-border bg-inputBg px-3 py-2 text-sm text-primary md:col-span-2"
                        :placeholder="tAuto('Externer Bewerbungslink optional')"
                    >

                    <textarea
                        v-model="jobFormFor(club).description"
                        required
                        rows="4"
                        class="rounded-lg border border-border bg-inputBg px-3 py-2 text-sm text-primary md:col-span-2"
                        :placeholder="tAuto('Beschreibung, Aufgaben, Voraussetzungen')"
                    ></textarea>

                    <label class="flex items-center gap-2 text-sm text-primary">
                        <input
                            v-model="jobFormFor(club).is_published"
                            type="checkbox"
                            class="rounded border-border bg-inputBg"
                        >
                        {{ tAuto('Auf Webseite veröffentlichen') }}
                    </label>

                    <div class="flex gap-2 md:justify-end">
                        <button
                            v-if="editingJobId"
                            type="button"
                            class="rounded-lg border border-border px-4 py-2 text-sm text-primary"
                            @click="resetJobForm(club)"
                        >
                            {{ tAuto('Abbrechen') }}
                        </button>

                        <button class="rounded-lg bg-buttonPrimary px-4 py-2 text-sm font-semibold text-buttonTextPrimary">
                            {{ editingJobId ? tAuto('Aktualisieren') : tAuto('Stelle erstellen') }}
                        </button>
                    </div>
                </form>

                <div class="mt-4 grid gap-3 lg:grid-cols-2">
                    <article
                        v-for="job in club.jobs"
                        :key="job.id"
                        class="rounded-lg border border-border bg-card p-4"
                    >
                        <div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
                            <div class="min-w-0">
                                <span
                                    class="rounded-full px-2 py-1 text-xs"
                                    :class="job.type === 'volunteer'
                                        ? 'bg-air-green/15 text-air-green'
                                        : 'bg-air-blue/15 text-air-blue'"
                                >
                                    {{ job.type === 'volunteer' ? tAuto('Ehrenamt') : tAuto('Beruf') }}
                                </span>

                                <h3 class="mt-3 font-semibold text-primary">
                                    {{ job.title }}
                                </h3>

                                <p class="mt-1 break-words text-xs text-secondary">
                                    {{ job.location || tAuto('Ort offen') }} · {{ job.workload || tAuto('Umfang offen') }} · {{ job.employment_type || tAuto('Art offen') }}
                                </p>
                            </div>

                            <span
                                class="rounded-full px-2 py-1 text-xs"
                                :class="job.is_published ? 'bg-air-green/15 text-air-green' : 'bg-muted text-secondary'"
                            >
                                {{ job.is_published ? tAuto('Online') : tAuto('Entwurf') }}
                            </span>
                        </div>

                        <p class="mt-3 line-clamp-3 text-sm text-secondary">
                            {{ job.description }}
                        </p>

                        <div
                            v-if="job.contact_email || job.application_url"
                            class="mt-3 flex flex-wrap gap-2 text-xs"
                        >
                            <a
                                v-if="job.contact_email"
                                :href="`mailto:${job.contact_email}`"
                                class="rounded-full border border-border px-3 py-1 text-secondary hover:bg-muted hover:text-primary"
                            >
                                {{ tAuto('Kontakt') }}: {{ job.contact_email }}
                            </a>
                            <a
                                v-if="job.application_url"
                                :href="job.application_url"
                                target="_blank"
                                rel="noopener noreferrer"
                                class="rounded-full border border-air-blue/30 px-3 py-1 text-air-blue hover:bg-air-blue/10"
                            >
                                {{ tAuto('Bewerbungslink prüfen') }}
                            </a>
                        </div>

                        <div v-if="club.can_manage_jobs" class="mt-4 grid grid-cols-2 gap-2 sm:flex">
                            <button
                                type="button"
                                class="rounded border border-border px-3 py-2 text-sm text-primary hover:bg-muted"
                                @click="editJob(club, job)"
                            >
                                {{ tAuto('Bearbeiten') }}
                            </button>

                            <button
                                type="button"
                                class="rounded bg-error px-3 py-2 text-sm text-white"
                                @click="deleteJob(job)"
                            >
                                {{ tAuto('Löschen') }}
                            </button>
                        </div>
                    </article>

                    <div
                        v-if="!club.jobs?.length"
                        class="rounded-lg border border-dashed border-border bg-card p-5 text-sm text-secondary lg:col-span-2"
                    >
                        <p class="font-semibold text-primary">{{ tAuto('Noch keine Stellen veröffentlicht.') }}</p>
                        <p class="mt-1">
                            {{ tAuto('Lege den ersten Eintrag an, damit interessierte Menschen passende Jobs oder Ehrenamtsrollen finden.') }}
                        </p>
                        <button
                            v-if="club.can_manage_jobs"
                            type="button"
                            class="mt-4 rounded-lg bg-buttonPrimary px-4 py-2 text-sm font-semibold text-buttonTextPrimary"
                            @click="openJobModal(club)"
                        >
                            {{ tAuto('Ersten Eintrag erstellen') }}
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- MOBILE FILTER MODAL -->
    <Teleport to="body">
        <div
            v-if="showFilterModal"
            class="fixed inset-0 z-[80] flex items-center justify-center bg-black/60"
            @click.self="showFilterModal = false"
        >
            <div class="flex h-full w-full flex-col bg-card sm:h-auto sm:max-h-[90vh] sm:max-w-lg sm:rounded-2xl sm:border sm:border-border sm:shadow-xl">
                <div class="shrink-0 border-b border-border bg-card p-4">
                    <div class="flex items-center justify-between gap-4">
                        <div>
                            <h2 class="text-lg font-semibold text-primary">{{ tAuto('Filter') }}</h2>

                            <p class="mt-1 text-sm text-secondary">
                                {{ tAuto('Suche nach Vereinen, Sportart oder Ort.') }}
                            </p>
                        </div>

                        <button
                            type="button"
                            class="rounded-lg border border-border px-3 py-1 text-secondary hover:border-borderHover hover:text-primary"
                            @click="showFilterModal = false"
                        >
                            ✕
                        </button>
                    </div>
                </div>

                <div class="min-h-0 flex-1 space-y-4 overflow-y-auto p-4">
                    <div>
                        <label class="block text-sm font-semibold text-primary">
                            {{ tAuto('Verein suchen') }}
                        </label>

                        <input
                            v-model="filtersForm.search"
                            class="mt-1 w-full rounded-lg border border-border bg-inputBg px-3 py-3 text-sm text-primary"
                            :placeholder="tAuto('Verein suchen')"
                        >
                    </div>

                    <div>
                        <label class="block text-sm font-semibold text-primary">
                            {{ tAuto('Sportart') }}
                        </label>

                        <SearchableSelect
                            v-model="filtersForm.sport_type"
                            class="mt-1"
                            :options="sports"
                            value-key="slug"
                            translation-prefix="sports"
                            category-translation-prefix="sport_categories"
                            :placeholder="tAuto('Sportart suchen')"
                        />
                    </div>

                    <div>
                        <label class="block text-sm font-semibold text-primary">
                            {{ tAuto('Ort') }}
                        </label>

                        <input
                            v-model="filtersForm.location"
                            class="mt-1 w-full rounded-lg border border-border bg-inputBg px-3 py-3 text-sm text-primary"
                            :placeholder="tAuto('Ort, Stadt, PLZ oder Land')"
                        >
                    </div>
                </div>

                <div class="shrink-0 border-t border-border bg-card p-4">
                    <div class="flex gap-3">
                        <button
                            type="button"
                            class="flex-1 rounded-lg border border-border px-4 py-3 font-semibold text-secondary hover:border-borderHover hover:text-primary"
                            @click="resetFilters"
                        >
                            {{ tAuto('Zurücksetzen') }}
                        </button>

                        <button
                            type="button"
                            class="flex-1 rounded-lg bg-buttonPrimary px-4 py-3 font-semibold text-buttonTextPrimary hover:bg-buttonPrimaryHover"
                            @click="applyFilters"
                        >
                            {{ tAuto('Anwenden') }}
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </Teleport>

    <!-- CLUB CREATE WIZARD MODAL -->
    <Teleport to="body">
        <div
            v-if="showClubModal"
            class="fixed inset-0 z-[80] flex items-center justify-center bg-black/60"
            @click.self="closeClubModal"
        >
            <div class="flex h-full w-full flex-col bg-card sm:h-auto sm:max-h-[92vh] sm:max-w-2xl sm:rounded-2xl sm:border sm:border-border sm:shadow-xl">
                <div class="shrink-0 border-b border-border bg-card p-4">
                    <div class="flex items-start justify-between gap-4">
                        <div class="min-w-0">
                            <h2 class="truncate text-lg font-semibold text-primary">
                                {{ tAuto('Verein registrieren') }}
                            </h2>

                            <p class="mt-1 text-sm text-secondary">
                                {{ tAuto('Schritt') }} {{ clubCreateStep }} {{ tAuto('von') }} {{ clubCreateSteps.length }}
                            </p>
                        </div>

                        <button
                            type="button"
                            class="shrink-0 rounded-lg border border-border px-3 py-1 text-secondary hover:border-borderHover hover:text-primary"
                            @click="closeClubModal"
                        >
                            ✕
                        </button>
                    </div>

                    <div class="mt-4 grid grid-cols-3 gap-2">
                        <button
                            v-for="step in clubCreateSteps"
                            :key="step.number"
                            type="button"
                            class="rounded-full px-2 py-2 text-xs font-semibold transition"
                            :class="clubCreateStep === step.number
                                ? 'bg-buttonPrimary text-buttonTextPrimary'
                                : clubCreateStep > step.number
                                    ? 'bg-air-green/15 text-air-green'
                                    : 'bg-inputBg text-secondary'"
                            @click="clubCreateStep = step.number"
                        >
                            {{ step.label }}
                        </button>
                    </div>
                </div>

                <div class="min-h-0 flex-1 overflow-y-auto p-4">
                    <div
                        v-if="clubModalNotice"
                        class="mb-4 rounded-lg border border-error/30 bg-error/10 px-4 py-3 text-sm text-error"
                    >
                        {{ clubModalNotice }}
                    </div>

                    <section v-if="clubCreateStep === 1" class="space-y-4">
                        <div>
                            <h3 class="text-base font-semibold text-primary">
                                {{ tAuto('Basisdaten') }}
                            </h3>

                            <p class="mt-1 text-sm text-secondary">
                                {{ tAuto('Name, Sportart und Land des Vereins. Nach dem Absenden prüft Airmius den Antrag.') }}
                            </p>
                        </div>

                        <div>
                            <label class="block text-sm font-semibold text-primary">
                                {{ tAuto('Vereinsname') }}
                            </label>

                            <input
                                v-model="clubForm.name"
                                class="mt-1 w-full rounded-lg border border-border bg-inputBg px-3 py-3 text-sm text-primary"
                                :class="clubForm.errors.name ? 'border-error' : ''"
                                :placeholder="tAuto('Vereinsname')"
                                required
                            >
                            <p v-if="clubForm.errors.name" class="mt-1 text-xs text-error">{{ clubForm.errors.name }}</p>
                        </div>

                        <div>
                            <label class="block text-sm font-semibold text-primary">
                                {{ tAuto('Sportart') }}
                            </label>

                            <SearchableSelect
                                v-model="clubForm.sport_type"
                                class="mt-1 w-full"
                                :options="sports"
                                value-key="slug"
                                translation-prefix="sports"
                                category-translation-prefix="sport_categories"
                                :placeholder="tAuto('Sportart suchen')"
                            />
                            <p v-if="clubForm.errors.sport_type" class="mt-1 text-xs text-error">{{ clubForm.errors.sport_type }}</p>
                        </div>

                        <label
                            :class="[
                                'flex cursor-pointer items-start gap-3 rounded-lg border p-3 text-sm text-primary transition',
                                clubForm.is_official ? 'border-air-blue bg-air-blue/10' : 'border-border bg-bg',
                            ]"
                        >
                            <input
                                v-model="clubForm.is_official"
                                type="checkbox"
                                class="sr-only"
                            >
                            <span
                                :class="[
                                    'mt-0.5 flex h-6 w-6 shrink-0 items-center justify-center rounded-lg border text-sm transition',
                                    clubForm.is_official
                                        ? 'border-air-blue bg-air-blue text-white'
                                        : 'border-border bg-inputBg text-transparent',
                                ]"
                            >
                                <i class="las la-check"></i>
                            </span>
                            <span>
                                <span class="block font-semibold">{{ tAuto('Offizielle Prüfung beantragen') }}</span>
                                <span class="block text-secondary">{{ tAuto('Der Verein wird erst nach Admin-Freigabe öffentlich sichtbar und als offiziell markiert.') }}</span>
                            </span>
                        </label>

                        <div v-if="clubForm.is_official">
                            <label class="block text-sm font-semibold text-primary">
                                {{ tAuto('Vereinsnummer zur Prüfung') }}
                            </label>

                            <input
                                v-model="clubForm.official_club_number"
                                class="mt-1 w-full rounded-lg border border-border bg-inputBg px-3 py-3 text-sm text-primary"
                                :class="clubForm.errors.official_club_number ? 'border-error' : ''"
                                :placeholder="tAuto('z. B. Vereinsregister- oder Verbandsnummer')"
                            >
                            <p v-if="clubForm.errors.official_club_number" class="mt-1 text-xs text-error">{{ clubForm.errors.official_club_number }}</p>
                        </div>

                        <div>
                            <label class="block text-sm font-semibold text-primary">
                                {{ tAuto('Land') }}
                            </label>

                            <select
                                v-model="clubForm.country"
                                class="mt-1 w-full rounded-lg border border-border bg-inputBg px-3 py-3 text-sm text-primary"
                                required
                            >
                                <option value="DE">{{ tAuto('Deutschland') }}</option>
                                <option value="AT">{{ tAuto('Österreich') }}</option>
                                <option value="CH">{{ tAuto('Schweiz') }}</option>
                                <option value="FR">{{ tAuto('Frankreich') }}</option>
                                <option value="NL">{{ tAuto('Niederlande') }}</option>
                                <option value="BE">{{ tAuto('Belgien') }}</option>
                                <option value="MA">{{ tAuto('Marokko') }}</option>
                                <option value="ES">{{ tAuto('Spanien') }}</option>
                                <option value="PT">{{ tAuto('Portugal') }}</option>
                                <option value="IT">{{ tAuto('Italien') }}</option>
                                <option value="GB">{{ tAuto('Großbritannien') }}</option>
                                <option value="TR">{{ tAuto('Türkei') }}</option>
                                <option value="US">{{ tAuto('USA') }}</option>
                            </select>
                            <p v-if="clubForm.errors.country" class="mt-1 text-xs text-error">{{ clubForm.errors.country }}</p>
                        </div>
                    </section>

                    <section v-if="clubCreateStep === 2" class="space-y-4">
                        <div>
                            <h3 class="text-base font-semibold text-primary">
                                {{ tAuto('Adresse & Bankkonto') }}
                            </h3>

                            <p class="mt-1 text-sm text-secondary">
                                {{ tAuto('Optional: Standort und Bankkonto für Mitglieder-Überweisungen eintragen.') }}
                            </p>
                        </div>

                        <div class="grid grid-cols-1 gap-3 sm:grid-cols-2">
                            <div>
                                <input v-model="clubForm.city" class="w-full rounded-lg border border-border bg-inputBg px-3 py-3 text-sm text-primary" :class="clubForm.errors.city ? 'border-error' : ''" :placeholder="tAuto('Stadt')">
                                <p v-if="clubForm.errors.city" class="mt-1 text-xs text-error">{{ clubForm.errors.city }}</p>
                            </div>
                            <div>
                                <input v-model="clubForm.postal_code" class="w-full rounded-lg border border-border bg-inputBg px-3 py-3 text-sm text-primary" :class="clubForm.errors.postal_code ? 'border-error' : ''" :placeholder="tAuto('PLZ')">
                                <p v-if="clubForm.errors.postal_code" class="mt-1 text-xs text-error">{{ clubForm.errors.postal_code }}</p>
                            </div>
                            <div>
                                <input v-model="clubForm.state" class="w-full rounded-lg border border-border bg-inputBg px-3 py-3 text-sm text-primary" :class="clubForm.errors.state ? 'border-error' : ''" :placeholder="tAuto('Region')">
                                <p v-if="clubForm.errors.state" class="mt-1 text-xs text-error">{{ clubForm.errors.state }}</p>
                            </div>
                            <div>
                                <input v-model="clubForm.street" class="w-full rounded-lg border border-border bg-inputBg px-3 py-3 text-sm text-primary" :class="clubForm.errors.street ? 'border-error' : ''" :placeholder="tAuto('Straße')">
                                <p v-if="clubForm.errors.street" class="mt-1 text-xs text-error">{{ clubForm.errors.street }}</p>
                            </div>
                            <div>
                                <input v-model="clubForm.house_number" class="w-full rounded-lg border border-border bg-inputBg px-3 py-3 text-sm text-primary" :class="clubForm.errors.house_number ? 'border-error' : ''" :placeholder="tAuto('Hausnummer')">
                                <p v-if="clubForm.errors.house_number" class="mt-1 text-xs text-error">{{ clubForm.errors.house_number }}</p>
                            </div>
                            <div class="rounded-lg border border-border bg-card p-3 sm:col-span-2">
                                <p class="text-xs font-semibold uppercase text-secondary">{{ tAuto('Bankkonto für Vereinsrechnungen') }}</p>
                                <p class="mt-1 text-xs text-secondary">
                                    {{ tAuto('Diese Daten werden Mitgliedern angezeigt, wenn sie offene Vereinsrechnungen per Überweisung zahlen.') }}
                                </p>

                                <div class="mt-3 grid gap-3 sm:grid-cols-3">
                                    <div>
                                        <input v-model="clubForm.sepa_account_holder" class="w-full rounded-lg border border-border bg-inputBg px-3 py-3 text-sm text-primary" :class="clubForm.errors.sepa_account_holder ? 'border-error' : ''" :placeholder="tAuto('Kontoinhaber')">
                                        <p v-if="clubForm.errors.sepa_account_holder" class="mt-1 text-xs text-error">{{ clubForm.errors.sepa_account_holder }}</p>
                                    </div>
                                    <div>
                                <input v-model="clubForm.sepa_iban" class="w-full rounded-lg border border-border bg-inputBg px-3 py-3 text-sm text-primary" :class="clubForm.errors.sepa_iban ? 'border-error' : ''" :placeholder="tAuto('IBAN')">
                                        <p v-if="clubForm.errors.sepa_iban" class="mt-1 text-xs text-error">{{ clubForm.errors.sepa_iban }}</p>
                                    </div>
                                    <div>
                                <input v-model="clubForm.sepa_bic" class="w-full rounded-lg border border-border bg-inputBg px-3 py-3 text-sm text-primary" :class="clubForm.errors.sepa_bic ? 'border-error' : ''" :placeholder="tAuto('BIC')">
                                        <p v-if="clubForm.errors.sepa_bic" class="mt-1 text-xs text-error">{{ clubForm.errors.sepa_bic }}</p>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </section>

                    <section v-if="clubCreateStep === 3" class="space-y-4">
                        <div>
                            <h3 class="text-base font-semibold text-primary">
                                {{ tAuto('Prüfen') }}
                            </h3>

                            <p class="mt-1 text-sm text-secondary">
                                {{ tAuto('Kontrolliere die Angaben vor dem Absenden. Der Verein wird als Antrag gespeichert.') }}
                            </p>
                        </div>

                        <div class="rounded-xl border border-border bg-inputBg p-4">
                            <div class="space-y-3 text-sm">
                                <p><strong>{{ tAuto('Verein') }}:</strong> {{ clubForm.name || '-' }}</p>
                                <p><strong>{{ tAuto('Sportart') }}:</strong> {{ sportLabel(clubForm.sport_type) }}</p>
                                <p><strong>{{ tAuto('Offizielle Prüfung') }}:</strong> {{ clubForm.is_official ? tAuto('Beantragt') : tAuto('Nicht beantragt') }}</p>
                                <p v-if="clubForm.is_official"><strong>{{ tAuto('Vereinsnummer zur Prüfung') }}:</strong> {{ clubForm.official_club_number || '-' }}</p>
                                <p><strong>{{ tAuto('Status nach Absenden') }}:</strong> {{ tAuto('Wartet auf Prüfung') }}</p>
                                <p><strong>{{ tAuto('Land') }}:</strong> {{ clubForm.country || '-' }}</p>
                                <p>
                                    <strong>{{ tAuto('Adresse') }}:</strong>
                                    {{ clubForm.street || '-' }}
                                    {{ clubForm.house_number || '' }},
                                    {{ clubForm.postal_code || '' }}
                                    {{ clubForm.city || '' }}
                                </p>
                                <p><strong>{{ tAuto('Region') }}:</strong> {{ clubForm.state || '-' }}</p>
                                <p><strong>{{ tAuto('Kontoinhaber') }}:</strong> {{ clubForm.sepa_account_holder || '-' }}</p>
                                <p><strong>IBAN:</strong> {{ clubForm.sepa_iban || '-' }}</p>
                                <p><strong>BIC:</strong> {{ clubForm.sepa_bic || '-' }}</p>
                            </div>
                        </div>
                    </section>
                </div>

                <div class="shrink-0 border-t border-border bg-card p-4">
                    <div class="flex gap-3">
                        <button
                            type="button"
                            class="flex-1 rounded-lg border border-border px-4 py-3 font-semibold text-secondary hover:border-borderHover hover:text-primary disabled:opacity-50"
                            :disabled="clubCreateStep === 1"
                            @click="prevClubStep"
                        >
                            {{ tAuto('Zurück') }}
                        </button>

                        <button
                            v-if="clubCreateStep < clubCreateSteps.length"
                            type="button"
                            class="flex-1 rounded-lg bg-buttonPrimary px-4 py-3 font-semibold text-buttonTextPrimary hover:bg-buttonPrimaryHover"
                            @click="nextClubStep"
                        >
                            {{ tAuto('Weiter') }}
                        </button>

                        <button
                            v-else
                            type="button"
                            class="flex-1 rounded-lg bg-buttonPrimary px-4 py-3 font-semibold text-buttonTextPrimary hover:bg-buttonPrimaryHover disabled:cursor-not-allowed disabled:opacity-60"
                            :disabled="clubForm.processing"
                            @click="createClub"
                        >
                            {{ clubForm.processing ? tAuto('Speichert...') : tAuto('Speichern') }}
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </Teleport>

    <!-- TEAM MODAL -->
    <Modal :show="showTeamModal" @close="closeTeamModal">
        <div v-if="selectedClub" class="space-y-4">
            <h2 class="font-bold text-primary">
                {{ tAuto('Team erstellen') }}
            </h2>

            <input
                v-model="teamFormFor(selectedClub).name"
                class="w-full rounded border border-border bg-inputBg p-3 text-primary"
                :placeholder="tAuto('Teamname')"
            >

            <p v-if="errors.name" class="text-sm text-error">
                {{ errors.name }}
            </p>

            <SearchableSelect
                v-model="teamFormFor(selectedClub).sport_type"
                :options="sports"
                value-key="slug"
                translation-prefix="sports"
                category-translation-prefix="sport_categories"
                :placeholder="tAuto('Sportart suchen')"
            />

            <p v-if="errors.club_id" class="text-sm text-error">
                {{ errors.club_id }}
            </p>

            <p v-if="errors.sport_type" class="text-sm text-error">
                {{ errors.sport_type }}
            </p>

            <button
                @click="createTeam"
                class="w-full rounded bg-buttonPrimary py-3 text-buttonTextPrimary"
            >
                {{ tAuto('Erstellen') }}
            </button>
        </div>
    </Modal>

    <Modal :show="showJobModal" max-width="xl" @close="closeJobModal">
        <form
            v-if="selectedJobClub"
            class="space-y-5"
            @submit.prevent="submitJob(selectedJobClub)"
            aria-labelledby="job-modal-title"
        >
            <div>
                <p class="text-xs font-semibold uppercase tracking-wide text-secondary">
                    {{ selectedJobClub.name }}
                </p>
                <h2 id="job-modal-title" class="mt-1 text-lg font-bold text-primary">
                    {{ editingJobId ? tAuto('Eintrag bearbeiten') : tAuto('Jobs- oder Ehrenamtsangebot erstellen') }}
                </h2>
                <p class="mt-2 text-sm text-secondary">
                    {{ tAuto('Beschreibe die Aufgabe klar genug, damit Interessierte sofort verstehen, ob sie passt und wie sie Kontakt aufnehmen können.') }}
                </p>
            </div>

            <div
                v-if="jobModalNotice"
                class="rounded-lg border px-4 py-3 text-sm"
                role="alert"
                :class="jobModalNotice.type === 'success'
                    ? 'border-success/30 bg-success/10 text-success'
                    : 'border-error/30 bg-error/10 text-error'"
            >
                {{ jobModalNotice.message }}
            </div>

            <section class="space-y-3">
                <h3 class="text-sm font-semibold text-primary">{{ tAuto('Was wird gesucht?') }}</h3>

                <div class="grid gap-3 sm:grid-cols-2">
                    <label class="block sm:col-span-2">
                        <span class="text-xs font-semibold uppercase text-secondary">{{ tAuto('Titel') }} *</span>
                        <input
                            v-model="jobFormFor(selectedJobClub).title"
                            required
                            autocomplete="off"
                            class="mt-1 w-full rounded-lg border border-border bg-inputBg px-3 py-3 text-sm text-primary"
                            :placeholder="tAuto('z.B. Jugendtrainer U15')"
                        >
                        <span v-if="errors.title" class="mt-1 block text-xs text-error">{{ errors.title }}</span>
                    </label>

                    <label class="block">
                        <span class="text-xs font-semibold uppercase text-secondary">{{ tAuto('Kategorie') }}</span>
                        <select
                            v-model="jobFormFor(selectedJobClub).type"
                            class="mt-1 w-full rounded-lg border border-border bg-inputBg px-3 py-3 text-sm text-primary"
                        >
                            <option value="volunteer">{{ tAuto('Ehrenamt') }}</option>
                            <option value="professional">{{ tAuto('Beruf / bezahlte Stelle') }}</option>
                        </select>
                        <span v-if="errors.type" class="mt-1 block text-xs text-error">{{ errors.type }}</span>
                    </label>

                    <label class="block">
                        <span class="text-xs font-semibold uppercase text-secondary">{{ tAuto('Art') }}</span>
                        <input
                            v-model="jobFormFor(selectedJobClub).employment_type"
                            autocomplete="off"
                            class="mt-1 w-full rounded-lg border border-border bg-inputBg px-3 py-3 text-sm text-primary"
                            :placeholder="tAuto('Teilzeit, Minijob, Ehrenamt')"
                        >
                        <span v-if="errors.employment_type" class="mt-1 block text-xs text-error">{{ errors.employment_type }}</span>
                    </label>

                    <label class="block">
                        <span class="text-xs font-semibold uppercase text-secondary">{{ t('recruiting.criteria.sport') }}</span>
                        <select v-model="jobFormFor(selectedJobClub).sport_id" class="mt-1 w-full rounded-lg border border-border bg-inputBg px-3 py-3 text-sm text-primary">
                            <option value="">{{ t('recruiting.criteria.no_sport') }}</option>
                            <option v-for="sport in sports" :key="sport.id" :value="sport.id">{{ sportLabel(sport) }}</option>
                        </select>
                        <span v-if="errors.sport_id" class="mt-1 block text-xs text-error">{{ errors.sport_id }}</span>
                    </label>

                    <label class="block">
                        <span class="text-xs font-semibold uppercase text-secondary">{{ t('recruiting.criteria.minimum_experience') }}</span>
                        <select v-model="jobFormFor(selectedJobClub).minimum_experience_level" class="mt-1 w-full rounded-lg border border-border bg-inputBg px-3 py-3 text-sm text-primary">
                            <option value="">{{ t('recruiting.criteria.no_minimum') }}</option>
                            <option v-for="level in ['beginner', 'intermediate', 'advanced', 'expert', 'elite']" :key="level" :value="level">{{ t(`recruiting.experience.${level}`) }}</option>
                        </select>
                        <span v-if="errors.minimum_experience_level" class="mt-1 block text-xs text-error">{{ errors.minimum_experience_level }}</span>
                    </label>
                </div>
            </section>

            <section class="space-y-3">
                <h3 class="text-sm font-semibold text-primary">{{ tAuto('Rahmen') }}</h3>

                <div class="grid gap-3 sm:grid-cols-2">
                    <label class="block">
                        <span class="text-xs font-semibold uppercase text-secondary">{{ tAuto('Adresse / Ort') }}</span>
                        <input
                            v-model="jobFormFor(selectedJobClub).location"
                            autocomplete="address-line1"
                            class="mt-1 w-full rounded-lg border border-border bg-inputBg px-3 py-3 text-sm text-primary"
                            :placeholder="tAuto('Sportanlage, Adresse, Stadt oder Remote')"
                        >
                        <span v-if="errors.location" class="mt-1 block text-xs text-error">{{ errors.location }}</span>
                    </label>

                    <label class="block">
                        <span class="text-xs font-semibold uppercase text-secondary">{{ tAuto('Umfang') }}</span>
                        <input
                            v-model="jobFormFor(selectedJobClub).workload"
                            autocomplete="off"
                            class="mt-1 w-full rounded-lg border border-border bg-inputBg px-3 py-3 text-sm text-primary"
                            :placeholder="tAuto('z.B. 6 Std./Woche')"
                        >
                        <span v-if="errors.workload" class="mt-1 block text-xs text-error">{{ errors.workload }}</span>
                    </label>
                </div>
            </section>

            <section class="space-y-3">
                <h3 class="text-sm font-semibold text-primary">{{ tAuto('Beschreibung & Kontakt') }}</h3>

                <label class="block">
                    <span class="text-xs font-semibold uppercase text-secondary">{{ tAuto('Beschreibung') }} *</span>
                    <textarea
                        v-model="jobFormFor(selectedJobClub).description"
                        required
                        rows="5"
                        class="mt-1 w-full rounded-lg border border-border bg-inputBg px-3 py-3 text-sm text-primary"
                        :placeholder="tAuto('Aufgaben, Voraussetzungen, Zeitraum und was die Person wissen sollte.')"
                    ></textarea>
                    <span v-if="errors.description" class="mt-1 block text-xs text-error">{{ errors.description }}</span>
                </label>

                <div class="grid gap-3 sm:grid-cols-2">
                    <label class="block">
                        <span class="text-xs font-semibold uppercase text-secondary">{{ tAuto('Kontakt E-Mail') }}</span>
                        <input
                            v-model="jobFormFor(selectedJobClub).contact_email"
                            type="email"
                            autocomplete="email"
                            class="mt-1 w-full rounded-lg border border-border bg-inputBg px-3 py-3 text-sm text-primary"
                            placeholder="kontakt@verein.de"
                        >
                        <span v-if="errors.contact_email" class="mt-1 block text-xs text-error">{{ errors.contact_email }}</span>
                    </label>

                    <label class="block">
                        <span class="text-xs font-semibold uppercase text-secondary">{{ tAuto('Externer Bewerbungslink optional') }}</span>
                        <input
                            v-model="jobFormFor(selectedJobClub).application_url"
                            type="url"
                            inputmode="url"
                            autocomplete="url"
                            class="mt-1 w-full rounded-lg border border-border bg-inputBg px-3 py-3 text-sm text-primary"
                            :placeholder="'https://formular.verein.de'"
                        >
                        <span v-if="errors.application_url" class="mt-1 block text-xs text-error">{{ errors.application_url }}</span>
                        <span class="mt-1 block text-xs text-secondary">
                            {{ tAuto('Nur ausfüllen, wenn Interessierte zusätzlich auf ein externes Formular weitergeleitet werden sollen.') }}
                        </span>
                    </label>
                </div>
            </section>

            <label class="flex items-start gap-3 rounded-lg border border-border bg-bg p-3 text-sm text-primary">
                <input
                    v-model="jobFormFor(selectedJobClub).is_published"
                    type="checkbox"
                    class="mt-1 rounded border-border bg-inputBg"
                >
                <span>
                    <span class="block font-semibold">{{ tAuto('Auf Webseite veröffentlichen') }}</span>
                    <span class="block text-xs text-secondary">{{ tAuto('Wenn deaktiviert, bleibt der Eintrag als Entwurf im Dashboard.') }}</span>
                </span>
            </label>

            <div class="flex flex-col-reverse gap-2 sm:flex-row sm:justify-end">
                <button
                    type="button"
                    class="rounded-lg border border-border px-4 py-3 text-sm font-semibold text-primary hover:bg-muted"
                    @click="closeJobModal"
                >
                    {{ tAuto('Abbrechen') }}
                </button>
                <button
                    class="rounded-lg bg-buttonPrimary px-4 py-3 text-sm font-semibold text-buttonTextPrimary disabled:cursor-not-allowed disabled:opacity-60"
                    :disabled="isSubmittingJob"
                    :aria-busy="isSubmittingJob"
                >
                    {{ isSubmittingJob ? tAuto('Wird gespeichert…') : (editingJobId ? tAuto('Aktualisieren') : tAuto('Eintrag erstellen')) }}
                </button>
            </div>
        </form>
    </Modal>

    <Modal :show="showDeleteModal" max-width="md" @close="closeDeleteModal">
        <div v-if="deleteTarget" class="space-y-4">
            <div>
                <h2 class="text-lg font-bold text-primary">{{ deleteTarget.title }}</h2>
                <p class="mt-2 text-sm text-secondary">{{ deleteTarget.description }}</p>
            </div>

            <div class="rounded-lg border border-error/30 bg-error/10 p-3 text-sm text-error">
                {{ tAuto('Bitte gib') }} <strong>{{ deleteTarget.confirmText || 'delete' }}</strong> {{ tAuto('ein, um die Aktion zu bestätigen.') }}
            </div>

            <label class="block">
                <span class="text-sm font-semibold text-primary">{{ tAuto('Bestätigung') }}</span>
                <input
                    v-model="deleteConfirmation"
                    class="mt-1 w-full rounded-lg border border-border bg-inputBg px-3 py-2 text-sm text-primary"
                    :placeholder="deleteTarget.confirmText || 'delete'"
                    autocomplete="off"
                >
            </label>

            <label v-if="deleteTarget.requiresReason" class="block">
                <span class="text-sm font-semibold text-primary">{{ tAuto('Begründung') }}</span>
                <textarea
                    v-model="deleteReason"
                    rows="4"
                    class="mt-1 w-full rounded-lg border border-border bg-inputBg px-3 py-2 text-sm text-primary"
                    :placeholder="tAuto('Warum möchtest du dieses Team verlassen?')"
                ></textarea>
                <p class="mt-1 text-xs text-secondary">{{ tAuto('Die Begründung wird an die Vereinsverantwortlichen gesendet.') }}</p>
            </label>

            <div class="flex flex-col-reverse gap-2 sm:flex-row sm:justify-end">
                <button
                    type="button"
                    class="rounded-lg border border-border px-4 py-2 text-sm font-semibold text-primary hover:bg-muted"
                    @click="closeDeleteModal"
                >
                    {{ tAuto('Abbrechen') }}
                </button>
                <button
                    type="button"
                    class="rounded-lg bg-error px-4 py-2 text-sm font-semibold text-white disabled:cursor-not-allowed disabled:opacity-50"
                    :disabled="deleteConfirmation !== (deleteTarget.confirmText || 'delete')"
                    @click="confirmDelete"
                >
                    {{ deleteTarget.buttonLabel || tAuto('Endgültig löschen') }}
                </button>
            </div>
        </div>
    </Modal>
</template>
