<script setup>
import AppLayout from '@/Components/Auth/Layouts/AppLayout.vue'
import ClubWorkspaceNav from '@/Components/Auth/ClubWorkspaceNav.vue'
import Modal from '@/Components/Modal.vue'
import SearchableSelect from '@/Components/SearchableSelect.vue'
import { Head, Link, router, useForm, usePage } from '@inertiajs/vue3'
import { computed, ref } from 'vue'
import { usePermissions } from '@/composables/usePermissions'
import { useI18n } from 'vue-i18n'

const { can } = usePermissions()
const { t, te } = useI18n()

defineOptions({ layout: AppLayout })

const props = defineProps({
    clubs: Array,
    sports: { type: Array, default: () => [] },
    clubRoles: { type: Array, default: () => ['owner', 'admin', 'manager', 'member'] },
    teamRoles: { type: Array, default: () => ['Coach', 'Captain', 'Player'] },
    filters: { type: Object, default: () => ({}) },
    receivedInvitations: { type: Array, default: () => [] },
})

const page = usePage()
const user = page.props.auth?.user

const showClubModal = ref(false)
const showTeamModal = ref(false)
const showFilterModal = ref(false)
const showDeleteModal = ref(false)
const showJobModal = ref(false)

const selectedClub = ref(null)
const selectedJobClub = ref(null)
const openClubId = ref(null)
const editingClubId = ref(null)
const actionNotice = ref(null)
const clubModalNotice = ref(null)
const jobModalNotice = ref(null)
const deleteTarget = ref(null)
const deleteConfirmation = ref('')
const deleteReason = ref('')
const errors = computed(() => page.props.errors || {})

const clubCreateStep = ref(1)

const clubCreateSteps = [
    { number: 1, label: 'Basis' },
    { number: 2, label: 'Adresse' },
    { number: 3, label: 'Prüfen' },
]

const filtersForm = ref({
    search: props.filters.search || '',
    sport_type: props.filters.sport_type || '',
    location: props.filters.location || '',
})

const defaultClubForm = () => ({
    name: '',
    sport_type: '',
    is_official: false,
    official_club_number: '',
    country: user?.country || 'DE',
    is_listed: true,
    teams_are_listed: true,
    members_can_post_to_club: true,
    members_can_post_to_teams: true,
    street: '',
    house_number: '',
    postal_code: '',
    city: '',
    state: '',
    sepa_account_holder: '',
    sepa_iban: '',
    sepa_bic: '',
})

const clubForm = useForm(defaultClubForm())

const inviteForms = ref({})
const inviteNotices = ref({})
const joinRequestNotices = ref({})
const teamForms = ref({})
const clubEditForms = ref({})
const clubEditTabs = ref({})
const sponsorForms = ref({})
const editingSponsorIds = ref({})
const jobForms = ref({})
const editingJobId = ref(null)
const isSubmittingJob = ref(false)
const processingJoinTeamIds = ref(new Set())
const processingJoinRequestIds = ref(new Set())

const initials = (name) =>
    name?.split(' ').map(n => n[0]).join('').slice(0, 2).toUpperCase()

const sportLabel = (value) => {
    if (!value) return 'Sportart offen'

    const sport = props.sports.find((sport) => sport.slug === value || sport.name === value)
    const slug = sport?.slug || value
    const key = `sports.${slug}`

    return te(key) ? t(key) : (sport?.name || value)
}

const clubRoleLabel = (role) => ({
    owner: 'Owner',
    admin: 'Verein-Admin',
    manager: 'Manager',
    academy_manager: 'Akademie-Manager',
    financial_controller: 'Kassierer',
    trainer: 'Trainer',
    member: 'Mitglied',
}[role] || role)
const clubRoleList = (member) => Array.isArray(member.pivot.roles) && member.pivot.roles.length
    ? member.pivot.roles
    : [member.pivot.role || 'member']

const teamRoleLabel = (role) => ({
    Coach: 'Trainer',
    Captain: 'Kapitän',
    Player: 'Spieler',
}[role] || role)

const toggleClub = (club) => {
    openClubId.value = openClubId.value === club.id ? null : club.id
}

const clubEditTabItems = [
    { key: 'basis', label: 'Basis' },
    { key: 'sichtbarkeit', label: 'Sichtbarkeit' },
    { key: 'adresse', label: 'Adresse' },
    { key: 'bank', label: 'Bankkonto' },
    { key: 'sponsoren', label: 'Sponsoren' },
]

const activeClubEditTab = (club) => clubEditTabs.value[club.id] || 'basis'

const setClubEditTab = (club, tab) => {
    clubEditTabs.value[club.id] = tab
}

const openClubModal = () => {
    clubCreateStep.value = 1
    clubModalNotice.value = null
    clubForm.clearErrors()
    showClubModal.value = true
}

const closeClubModal = () => {
    showClubModal.value = false
    clubCreateStep.value = 1
    clubModalNotice.value = null
}

const nextClubStep = () => {
    if (clubCreateStep.value < clubCreateSteps.length) {
        clubCreateStep.value++
    }
}

const prevClubStep = () => {
    if (clubCreateStep.value > 1) {
        clubCreateStep.value--
    }
}

const resetClubForm = () => {
    clubForm.defaults(defaultClubForm())
    clubForm.reset()
    clubForm.clearErrors()
    clubModalNotice.value = null

    clubCreateStep.value = 1
}

const openTeamModal = (club) => {
    if (club?.subscription_capabilities?.can_create_team === false) {
        return
    }

    selectedClub.value = club
    showTeamModal.value = true
}

const closeTeamModal = () => {
    showTeamModal.value = false
    selectedClub.value = null
}

const teamFormFor = (club) => {
    if (!club?.id) {
        return {
            club_id: '',
            name: '',
            sport_type: props.sports[0]?.slug || '',
        }
    }

    teamForms.value[club.id] ??= {
        club_id: club.id,
        name: '',
        sport_type: club.sport_type || props.sports[0]?.slug || '',
    }

    return teamForms.value[club.id]
}

const inviteFormFor = (team) => {
    inviteForms.value[team.id] ??= { email: '', role: 'Player' }
    return inviteForms.value[team.id]
}

const setActionNotice = (type, message) => {
    actionNotice.value = { type, message }
}

const setInviteNotice = (team, type, message) => {
    inviteNotices.value[team.id] = { type, message }
}

const setJoinRequestNotice = (team, type, message) => {
    joinRequestNotices.value[team.id] = { type, message }
}

const clubForTeam = (team) => props.clubs.find((club) => club.teams?.some((clubTeam) => clubTeam.id === team.id))

const decrementInvitationLimit = (club, amount = 1) => {
    const capabilities = club?.subscription_capabilities

    if (!capabilities || capabilities.member_invitation_daily_limit === null || capabilities.member_invitation_daily_limit === undefined) {
        return
    }

    capabilities.member_invitation_usage_today = Number(capabilities.member_invitation_usage_today || 0) + amount
    capabilities.member_invitation_remaining_today = Math.max(
        0,
        Number(capabilities.member_invitation_daily_limit || 0) - capabilities.member_invitation_usage_today,
    )
}

const openDeleteModal = (target) => {
    deleteTarget.value = target
    deleteConfirmation.value = ''
    deleteReason.value = ''
    showDeleteModal.value = true
}

const closeDeleteModal = () => {
    showDeleteModal.value = false
    deleteTarget.value = null
    deleteConfirmation.value = ''
    deleteReason.value = ''
}

const confirmDelete = () => {
    if (!deleteTarget.value || deleteConfirmation.value !== (deleteTarget.value.confirmText || 'delete')) return

    const target = deleteTarget.value
    actionNotice.value = null

    router.delete(route(target.route, target.params), {
        data: target.requiresReason ? { reason: deleteReason.value } : {},
        preserveScroll: true,
        onSuccess: () => {
            setActionNotice('success', target.successMessage)
            closeDeleteModal()
        },
        onError: (errors) => setActionNotice('error', errors.team || errors.user || target.errorMessage),
    })
}

const createClub = () => {
    actionNotice.value = null
    clubModalNotice.value = null

    clubForm.post(route('auth.clubs.store'), {
        preserveScroll: true,
        onSuccess: () => {
            resetClubForm()
            closeClubModal()
            setActionNotice('success', 'Verein wurde registriert.')
        },
        onError: (errors) => {
            const firstMessage = Object.values(errors)[0]
            clubModalNotice.value = firstMessage || 'Verein konnte nicht registriert werden. Bitte prüfe die markierten Eingaben.'

            if (errors.name || errors.sport_type || errors.country || errors.official_club_number) {
                clubCreateStep.value = 1
            } else if (errors.city || errors.postal_code || errors.state || errors.street || errors.house_number || errors.sepa_account_holder || errors.sepa_iban || errors.sepa_bic) {
                clubCreateStep.value = 2
            }
        },
    })
}

const applyFilters = () => {
    router.get(route('auth.teams.index'), {
        search: filtersForm.value.search || undefined,
        sport_type: filtersForm.value.sport_type || undefined,
        location: filtersForm.value.location || undefined,
    }, {
        preserveState: true,
        replace: true,
        preserveScroll: true,
        onSuccess: () => {
            showFilterModal.value = false
        },
    })
}

const resetFilters = () => {
    filtersForm.value = {
        search: '',
        sport_type: '',
        location: '',
    }

    applyFilters()
}

const createTeam = () => {
    if (!selectedClub.value?.id) return

    const clubId = selectedClub.value.id
    actionNotice.value = null

    router.post(route('auth.teams.store'), teamFormFor(selectedClub.value), {
        onSuccess: () => {
            teamForms.value[clubId] = {
                club_id: clubId,
                name: '',
                sport_type: selectedClub.value?.sport_type || props.sports[0]?.slug || '',
            }

            closeTeamModal()
            setActionNotice('success', 'Team wurde erstellt.')
        },
        onError: () => setActionNotice('error', 'Team konnte nicht erstellt werden. Bitte prüfe die Eingaben.'),
        preserveScroll: true,
    })
}

const inviteUser = (team) => {
    actionNotice.value = null
    inviteNotices.value[team.id] = null

    router.post(route('auth.teams.invite', team.id), inviteFormFor(team), {
        preserveScroll: true,
        onSuccess: () => {
            decrementInvitationLimit(clubForTeam(team))
            inviteFormFor(team).email = ''
            setInviteNotice(team, 'success', 'Einladung wurde erfolgreich gesendet.')
        },
        onError: (errors) => {
            const message = errors.email
                || errors.user_id
                || errors.role
                || errors.general
                || errors.message
                || Object.values(errors)[0]
                || 'Einladung konnte nicht gesendet werden.'
            setInviteNotice(team, 'error', message)
        },
    })
}

const acceptInvitation = (invitation) => {
    actionNotice.value = null

    router.post(route('auth.team-invitations.accept', invitation.id), {}, {
        preserveScroll: true,
        onSuccess: () => setActionNotice('success', 'Team-Einladung wurde angenommen.'),
        onError: () => setActionNotice('error', 'Team-Einladung konnte nicht angenommen werden.'),
    })
}

const declineInvitation = (invitation) => {
    actionNotice.value = null

    router.post(route('auth.team-invitations.decline', invitation.id), {}, {
        preserveScroll: true,
        onSuccess: () => setActionNotice('success', 'Team-Einladung wurde abgelehnt.'),
        onError: () => setActionNotice('error', 'Team-Einladung konnte nicht abgelehnt werden.'),
    })
}

const requestJoinTeam = (team) => {
    actionNotice.value = null
    joinRequestNotices.value[team.id] = null

    if (processingJoinTeamIds.value.has(team.id)) return
    processingJoinTeamIds.value.add(team.id)

    router.post(route('auth.teams.join-requests.store', team.id), {}, {
        preserveScroll: true,
        onSuccess: () => {
            setJoinRequestNotice(team, 'success', 'Team-Beitrittsanfrage wurde gesendet.')
        },
        onError: (errors) => {
            setJoinRequestNotice(team, 'error', errors.team || errors.general || errors.message || 'Team-Beitrittsanfrage konnte nicht gesendet werden.')
        },
        onFinish: () => processingJoinTeamIds.value.delete(team.id),
    })
}

const approveJoinRequest = (request) => {
    actionNotice.value = null

    if (processingJoinRequestIds.value.has(request.id)) return
    processingJoinRequestIds.value.add(request.id)

    router.post(route('auth.team-join-requests.approve', request.id), { role: 'Player' }, {
        preserveScroll: true,
        onSuccess: () => setActionNotice('success', 'Team-Beitrittsanfrage wurde angenommen.'),
        onError: (errors) => setActionNotice('error', errors.join_request || errors.general || errors.message || 'Team-Beitrittsanfrage konnte nicht angenommen werden.'),
        onFinish: () => processingJoinRequestIds.value.delete(request.id),
    })
}

const declineJoinRequest = (request) => {
    actionNotice.value = null

    if (processingJoinRequestIds.value.has(request.id)) return
    processingJoinRequestIds.value.add(request.id)

    router.post(route('auth.team-join-requests.decline', request.id), {}, {
        preserveScroll: true,
        onSuccess: () => setActionNotice('success', 'Team-Beitrittsanfrage wurde abgelehnt.'),
        onError: (errors) => setActionNotice('error', errors.join_request || errors.general || errors.message || 'Team-Beitrittsanfrage konnte nicht abgelehnt werden.'),
        onFinish: () => processingJoinRequestIds.value.delete(request.id),
    })
}

const updateClubMemberRole = (club, member) => {
    actionNotice.value = null

    const selectedRole = member.pivot.role || 'member'
    member.pivot.roles = [selectedRole]

    router.put(route('auth.clubs.members.update', [club.id, member.id]), {
        role: selectedRole,
        roles: [selectedRole],
    }, {
        preserveScroll: true,
        onSuccess: () => setActionNotice('success', 'Vereinsrolle wurde gespeichert.'),
        onError: () => setActionNotice('error', 'Vereinsrolle konnte nicht gespeichert werden.'),
    })
}

const clubEditFormFor = (club) => {
    clubEditForms.value[club.id] ??= {
        name: club.name || '',
        sport_type: club.sport_type || '',
        official_club_number: club.requested_official_club_number || club.official_club_number || '',
        country: club.country || user?.country || 'DE',
        is_listed: club.is_listed !== false,
        teams_are_listed: club.teams_are_listed !== false,
        members_can_post_to_club: club.members_can_post_to_club !== false,
        members_can_post_to_teams: club.members_can_post_to_teams !== false,
        street: club.street || '',
        house_number: club.house_number || '',
        postal_code: club.postal_code || '',
        city: club.city || '',
        state: club.state || '',
        sepa_account_holder: club.sepa_account_holder || '',
        sepa_iban: club.sepa_iban || '',
        sepa_bic: club.sepa_bic || '',
    }

    return clubEditForms.value[club.id]
}

const editClub = (club) => {
    editingClubId.value = club.id
    clubEditTabs.value[club.id] = 'basis'
    clubEditForms.value[club.id] = {
        name: club.name || '',
        sport_type: club.sport_type || '',
        official_club_number: club.requested_official_club_number || club.official_club_number || '',
        country: club.country || user?.country || 'DE',
        is_listed: club.is_listed !== false,
        teams_are_listed: club.teams_are_listed !== false,
        members_can_post_to_club: club.members_can_post_to_club !== false,
        members_can_post_to_teams: club.members_can_post_to_teams !== false,
        street: club.street || '',
        house_number: club.house_number || '',
        postal_code: club.postal_code || '',
        city: club.city || '',
        state: club.state || '',
        sepa_account_holder: club.sepa_account_holder || '',
        sepa_iban: club.sepa_iban || '',
        sepa_bic: club.sepa_bic || '',
    }

    if (openClubId.value !== club.id) {
        openClubId.value = club.id
    }
}

const cancelClubEdit = () => {
    editingClubId.value = null
}

const updateClub = (club) => {
    actionNotice.value = null

    router.put(route('auth.clubs.update', club.id), clubEditFormFor(club), {
        preserveScroll: true,
        onSuccess: () => {
            editingClubId.value = null
            setActionNotice('success', 'Vereinsdaten wurden gespeichert.')
        },
        onError: () => setActionNotice('error', 'Vereinsdaten konnten nicht gespeichert werden. Bitte prüfe die Eingaben.'),
    })
}

const emptySponsorForm = () => ({
    name: '',
    contact_name: '',
    email: '',
    website: '',
    logo_light: '',
    logo_dark: '',
    amount: '',
    starts_at: '',
    ends_at: '',
})

const sponsorFormFor = (club) => {
    sponsorForms.value[club.id] ??= emptySponsorForm()
    return sponsorForms.value[club.id]
}

const resetSponsorForm = (club) => {
    sponsorForms.value[club.id] = emptySponsorForm()
    editingSponsorIds.value[club.id] = null
}

const editSponsor = (club, sponsor) => {
    editingSponsorIds.value[club.id] = sponsor.id
    sponsorForms.value[club.id] = {
        name: sponsor.name || '',
        contact_name: sponsor.contact_name || '',
        email: sponsor.email || '',
        website: sponsor.website || '',
        logo_light: sponsor.logo_light || sponsor.logo || '',
        logo_dark: sponsor.logo_dark || sponsor.logo_light || sponsor.logo || '',
        amount: sponsor.amount || '',
        starts_at: sponsor.starts_at || '',
        ends_at: sponsor.ends_at || '',
    }
    setClubEditTab(club, 'sponsoren')
}

const submitSponsor = (club) => {
    actionNotice.value = null

    const sponsorId = editingSponsorIds.value[club.id]
    const options = {
        preserveScroll: true,
        onSuccess: () => {
            resetSponsorForm(club)
            setActionNotice('success', sponsorId ? 'Sponsor wurde aktualisiert.' : 'Sponsor wurde erstellt.')
        },
        onError: () => setActionNotice('error', 'Sponsor konnte nicht gespeichert werden. Bitte prüfe die Eingaben.'),
    }

    sponsorId
        ? router.put(route('auth.clubs.sponsors.update', [club.id, sponsorId]), sponsorFormFor(club), options)
        : router.post(route('auth.clubs.sponsors.store', club.id), sponsorFormFor(club), options)
}

const deleteSponsor = (club, sponsor) => {
    openDeleteModal({
        title: `Sponsor "${sponsor.name}" löschen`,
        description: 'Der Sponsor wird aus diesem Verein entfernt. Diese Aktion kann nicht rückgaengig gemacht werden.',
        route: 'auth.clubs.sponsors.destroy',
        params: [club.id, sponsor.id],
        successMessage: 'Sponsor wurde gelöscht.',
        errorMessage: 'Sponsor konnte nicht gelöscht werden.',
    })
}

const sponsorLogoUrl = (sponsor) => sponsor.logo_light_url || sponsor.logo_url || sponsor.logo_light || sponsor.logo

const updateTeamMemberRole = (team, member) => {
    actionNotice.value = null

    router.put(route('auth.teams.members.update', [team.id, member.id]), {
        role: member.pivot.role,
    }, {
        preserveScroll: true,
        onSuccess: () => setActionNotice('success', 'Teamrolle wurde gespeichert.'),
        onError: () => setActionNotice('error', 'Teamrolle konnte nicht gespeichert werden.'),
    })
}

const removeTeamMember = (team, member) => {
    const isLeavingSelf = member.id === user?.id

    openDeleteModal({
        title: isLeavingSelf ? `Team "${team.name}" verlassen` : `${member.name} aus "${team.name}" entfernen`,
        description: isLeavingSelf
            ? 'Du kannst dieses Team nur verlassen, wenn alle offenen Rechnungen im zugehoerigen Verein ausgeglichen sind.'
            : 'Das Mitglied wird aus diesem Team entfernt. Die Vereinsmitgliedschaft bleibt bestehen.',
        route: 'auth.teams.members.destroy',
        params: [team.id, member.id],
        confirmText: isLeavingSelf ? 'verlassen' : 'entfernen',
        buttonLabel: isLeavingSelf ? 'Team verlassen' : 'Mitglied entfernen',
        requiresReason: isLeavingSelf,
        successMessage: isLeavingSelf ? 'Du hast das Team verlassen.' : 'Mitglied wurde aus dem Team entfernt.',
        errorMessage: isLeavingSelf
            ? 'Team konnte nicht verlassen werden. Bitte prüfe, ob noch offene Rechnungen vorhanden sind.'
            : 'Mitglied konnte nicht entfernt werden.',
    })
}

const deleteClub = (club) => {
    openDeleteModal({
        title: `Verein "${club.name}" löschen`,
        description: 'Dadurch werden auch alle Teams dieses Vereins gelöscht. Diese Aktion kann nicht rückgaengig gemacht werden.',
        route: 'auth.clubs.destroy',
        params: club.id,
        successMessage: 'Verein wurde gelöscht.',
        errorMessage: 'Verein konnte nicht gelöscht werden.',
    })
}

const deleteTeam = (team) => {
    openDeleteModal({
        title: `Team "${team.name}" löschen`,
        description: 'Das Team und seine Zuordnungen werden entfernt. Diese Aktion kann nicht rückgaengig gemacht werden.',
        route: 'auth.teams.destroy',
        params: team.id,
        successMessage: 'Team wurde gelöscht.',
        errorMessage: 'Team konnte nicht gelöscht werden.',
    })
}

const emptyJobForm = () => ({
    title: '',
    type: 'volunteer',
    location: '',
    workload: '',
    employment_type: '',
    description: '',
    contact_email: '',
    application_url: '',
    is_published: true,
})

const jobFormFor = (club) => {
    jobForms.value[club.id] ??= emptyJobForm()
    return jobForms.value[club.id]
}

const resetJobForm = (club) => {
    jobForms.value[club.id] = emptyJobForm()
    editingJobId.value = null
}

const openJobModal = (club) => {
    selectedJobClub.value = club
    jobModalNotice.value = null
    resetJobForm(club)
    showJobModal.value = true
}

const closeJobModal = () => {
    showJobModal.value = false
    selectedJobClub.value = null
    jobModalNotice.value = null
    editingJobId.value = null
    isSubmittingJob.value = false
}

const editJob = (club, job) => {
    selectedJobClub.value = club
    jobModalNotice.value = null
    editingJobId.value = job.id

    jobForms.value[club.id] = {
        title: job.title || '',
        type: job.type || 'volunteer',
        location: job.location || '',
        workload: job.workload || '',
        employment_type: job.employment_type || '',
        description: job.description || '',
        contact_email: job.contact_email || '',
        application_url: job.application_url || '',
        is_published: Boolean(job.is_published),
    }

    showJobModal.value = true
}

const submitJob = (club) => {
    if (isSubmittingJob.value) return

    actionNotice.value = null
    jobModalNotice.value = null
    isSubmittingJob.value = true
    const isEditing = Boolean(editingJobId.value)

    const options = {
        preserveScroll: true,
        onSuccess: () => {
            resetJobForm(club)
            closeJobModal()
            setActionNotice('success', isEditing ? 'Eintrag wurde aktualisiert.' : 'Eintrag wurde erstellt.')
        },
        onError: () => {
            jobModalNotice.value = {
                type: 'error',
                message: 'Eintrag konnte nicht gespeichert werden. Bitte prüfe die markierten Felder.',
            }
        },
        onFinish: () => {
            isSubmittingJob.value = false
        },
    }

    editingJobId.value
        ? router.put(route('auth.organization-jobs.update', editingJobId.value), jobFormFor(club), options)
        : router.post(route('auth.clubs.jobs.store', club.id), jobFormFor(club), options)
}

const deleteJob = (job) => {
    openDeleteModal({
        title: `Stelle "${job.title}" löschen`,
        description: 'Dieser Eintrag wird dauerhaft gelöscht und erscheint danach nicht mehr auf der Jobs-Seite.',
        route: 'auth.organization-jobs.destroy',
        params: job.id,
        successMessage: 'Eintrag wurde gelöscht.',
        errorMessage: 'Eintrag konnte nicht gelöscht werden.',
        confirmText: 'löschen',
        buttonLabel: 'Stelle löschen',
    })
}
</script>

<template>
    <Head title="Vereine & Teams" />

    <div class="space-y-6">
        <!-- HEADER -->
        <div class="flex items-start justify-between gap-4">
            <div class="min-w-0">
                <h1 class="text-2xl font-bold text-primary">
                    {{ $t('Vereine & Teams') }}
                </h1>

                <p class="text-sm text-secondary">
                    {{ $t('Verwalte Vereinsstruktur, Teams, Rollen und Einladungen') }}
                </p>
            </div>

            <!-- Mobile Plus Button -->
            <button
                v-if="can('club.create')"
                type="button"
                class="flex h-11 w-11 shrink-0 items-center justify-center rounded bg-buttonPrimary text-buttonTextPrimary shadow sm:hidden"
                @click="openClubModal"
                :aria-label="$t('Verein registrieren')"
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
                + {{ $t('Verein registrieren') }}
            </button>
        </div>

        <ClubWorkspaceNav
            active="structure"
            description="Vereinsstruktur, Teams, Rollen und Einladungen."
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
                    Offene Team-Einladungen
                </p>
                <h2 class="text-lg font-semibold text-primary">
                    Du wurdest zu einem Team eingeladen
                </h2>
                <p class="text-sm text-secondary">
                    Nimm die Einladung an, um dem Team und dem zugehoerigen Verein beizutreten.
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
                                {{ invitation.team?.name || 'Team' }}
                            </h3>
                            <p class="mt-1 text-sm text-secondary">
                                {{ invitation.team?.club?.name || 'Verein' }} - Rolle: {{ teamRoleLabel(invitation.role) }}
                            </p>
                            <p v-if="invitation.inviter?.name" class="mt-1 text-xs text-secondary">
                                Eingeladen von {{ invitation.inviter.name }}
                            </p>
                        </div>
                    </div>

                    <div class="mt-4 flex flex-col gap-2 sm:flex-row">
                        <button
                            type="button"
                            class="inline-flex flex-1 items-center justify-center rounded-lg bg-buttonPrimary px-4 py-2 text-sm font-semibold text-buttonTextPrimary hover:bg-buttonPrimaryHover"
                            @click="acceptInvitation(invitation)"
                        >
                            Annehmen
                        </button>
                        <button
                            type="button"
                            class="inline-flex flex-1 items-center justify-center rounded-lg border border-border px-4 py-2 text-sm font-semibold text-primary hover:bg-muted"
                            @click="declineInvitation(invitation)"
                        >
                            Ablehnen
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
                    aria-label="Filter"
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

            <!-- CLUB EDIT -->
            <form
                v-if="openClubId === club.id && editingClubId === club.id"
                class="rounded-xl border border-border bg-bg p-4 sm:p-5"
                @submit.prevent="updateClub(club)"
            >
                <div class="mb-4 flex flex-col gap-2 sm:flex-row sm:items-end sm:justify-between">
                    <div>
                        <h2 class="font-semibold text-primary">Vereinsdaten bearbeiten</h2>
                        <p class="text-xs text-secondary">
                            Basisdaten, Adresse und Sportart pflegen. Offizielle Prüfung laeuft separat über Admin.
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
                        {{ club.verification_status === 'verified' ? 'Freigegeben' : club.verification_status === 'rejected' ? 'Abgelehnt' : 'Wartet auf Prüfung' }}
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
                        <span class="text-xs font-semibold uppercase text-secondary">Vereinsname</span>
                        <input v-model="clubEditFormFor(club).name" required class="mt-1 w-full rounded-lg border border-border bg-inputBg px-3 py-2 text-sm text-primary">
                    </label>

                    <label v-if="activeClubEditTab(club) === 'basis'" class="block">
                        <span class="text-xs font-semibold uppercase text-secondary">Sportart</span>
                        <SearchableSelect
                            v-model="clubEditFormFor(club).sport_type"
                            class="mt-1 w-full"
                            :options="sports"
                            value-key="slug"
                            translation-prefix="sports"
                            category-translation-prefix="sport_categories"
                            placeholder="Sportart suchen"
                        />
                    </label>

                    <label v-if="activeClubEditTab(club) === 'sichtbarkeit'" class="flex items-start gap-3 rounded-lg border border-border bg-card p-3 text-sm text-primary">
                        <input
                            v-model="clubEditFormFor(club).is_listed"
                            type="checkbox"
                            class="mt-1 rounded border-border bg-inputBg"
                        >
                        <span>
                            <span class="block font-semibold">Verein auflisten</span>
                            <span class="block text-xs text-secondary">Der Verein darf in Vereinslisten und Auswahlfeldern sichtbar sein.</span>
                        </span>
                    </label>

                    <label v-if="activeClubEditTab(club) === 'sichtbarkeit'" class="flex items-start gap-3 rounded-lg border border-border bg-card p-3 text-sm text-primary">
                        <input
                            v-model="clubEditFormFor(club).teams_are_listed"
                            type="checkbox"
                            class="mt-1 rounded border-border bg-inputBg"
                        >
                        <span>
                            <span class="block font-semibold">Teams auflisten</span>
                            <span class="block text-xs text-secondary">Teams dürfen außerhalb des internen Vereinsbereichs sichtbar sein.</span>
                        </span>
                    </label>

                    <label v-if="activeClubEditTab(club) === 'sichtbarkeit'" class="flex items-start gap-3 rounded-lg border border-border bg-card p-3 text-sm text-primary">
                        <input
                            v-model="clubEditFormFor(club).members_can_post_to_club"
                            type="checkbox"
                            class="mt-1 rounded border-border bg-inputBg"
                        >
                        <span>
                            <span class="block font-semibold">Vereinsbeiträge erlauben</span>
                            <span class="block text-xs text-secondary">Normale Mitglieder dürfen Beiträge für den Verein erstellen.</span>
                        </span>
                    </label>

                    <label v-if="activeClubEditTab(club) === 'sichtbarkeit'" class="flex items-start gap-3 rounded-lg border border-border bg-card p-3 text-sm text-primary">
                        <input
                            v-model="clubEditFormFor(club).members_can_post_to_teams"
                            type="checkbox"
                            class="mt-1 rounded border-border bg-inputBg"
                        >
                        <span>
                            <span class="block font-semibold">Teambeiträge erlauben</span>
                            <span class="block text-xs text-secondary">Normale Teammitglieder dürfen Beiträge für ihre Teams erstellen.</span>
                        </span>
                    </label>

                    <label v-if="activeClubEditTab(club) === 'basis'" class="block xl:col-span-2">
                        <span class="text-xs font-semibold uppercase text-secondary">Vereinsnummer zur Prüfung</span>
                        <input
                            v-model="clubEditFormFor(club).official_club_number"
                            class="mt-1 w-full rounded-lg border border-border bg-inputBg px-3 py-2 text-sm text-primary"
                            placeholder="z. B. Vereinsregister- oder Verbandsnummer"
                        >
                        <span class="mt-1 block text-xs text-secondary">
                            Neue oder geänderte Nummern werden zur Admin-Prüfung vorgemerkt.
                        </span>
                    </label>

                    <label v-if="activeClubEditTab(club) === 'adresse'" class="block">
                        <span class="text-xs font-semibold uppercase text-secondary">Land</span>
                        <select v-model="clubEditFormFor(club).country" required class="mt-1 w-full rounded-lg border border-border bg-inputBg px-3 py-2 text-sm text-primary">
                            <option value="DE">Deutschland</option>
                            <option value="AT">Oesterreich</option>
                            <option value="CH">Schweiz</option>
                            <option value="FR">Frankreich</option>
                            <option value="NL">Niederlande</option>
                            <option value="BE">Belgien</option>
                            <option value="TR">Tuerkei</option>
                            <option value="US">USA</option>
                        </select>
                    </label>

                    <label v-if="activeClubEditTab(club) === 'adresse'" class="block">
                        <span class="text-xs font-semibold uppercase text-secondary">Stadt</span>
                        <input v-model="clubEditFormFor(club).city" class="mt-1 w-full rounded-lg border border-border bg-inputBg px-3 py-2 text-sm text-primary">
                    </label>

                    <label v-if="activeClubEditTab(club) === 'adresse'" class="block">
                        <span class="text-xs font-semibold uppercase text-secondary">PLZ</span>
                        <input v-model="clubEditFormFor(club).postal_code" class="mt-1 w-full rounded-lg border border-border bg-inputBg px-3 py-2 text-sm text-primary">
                    </label>

                    <label v-if="activeClubEditTab(club) === 'adresse'" class="block">
                        <span class="text-xs font-semibold uppercase text-secondary">Region</span>
                        <input v-model="clubEditFormFor(club).state" class="mt-1 w-full rounded-lg border border-border bg-inputBg px-3 py-2 text-sm text-primary">
                    </label>

                    <label v-if="activeClubEditTab(club) === 'adresse'" class="block">
                        <span class="text-xs font-semibold uppercase text-secondary">Strasse</span>
                        <input v-model="clubEditFormFor(club).street" class="mt-1 w-full rounded-lg border border-border bg-inputBg px-3 py-2 text-sm text-primary">
                    </label>

                    <label v-if="activeClubEditTab(club) === 'adresse'" class="block">
                        <span class="text-xs font-semibold uppercase text-secondary">Hausnummer</span>
                        <input v-model="clubEditFormFor(club).house_number" class="mt-1 w-full rounded-lg border border-border bg-inputBg px-3 py-2 text-sm text-primary">
                    </label>

                    <div v-if="activeClubEditTab(club) === 'bank'" class="rounded-lg border border-border bg-card p-3 md:col-span-2 xl:col-span-3">
                        <p class="text-xs font-semibold uppercase text-secondary">Bankkonto für Mitglieder-Überweisungen</p>
                        <p class="mt-1 text-xs text-secondary">
                            Diese Daten werden Mitgliedern bei offenen Vereinsrechnungen angezeigt.
                        </p>
                        <div class="mt-3 grid gap-3 md:grid-cols-3">
                            <label class="block">
                                <span class="text-xs font-semibold uppercase text-secondary">Kontoinhaber</span>
                                <input
                                    v-model="clubEditFormFor(club).sepa_account_holder"
                                    class="mt-1 w-full rounded-lg border border-border bg-inputBg px-3 py-2 text-sm text-primary"
                                    placeholder="Name laut Bankkonto"
                                >
                            </label>
                            <label class="block">
                                <span class="text-xs font-semibold uppercase text-secondary">IBAN</span>
                                <input
                                    v-model="clubEditFormFor(club).sepa_iban"
                                    class="mt-1 w-full rounded-lg border border-border bg-inputBg px-3 py-2 text-sm text-primary"
                                    placeholder="DE..."
                                >
                            </label>
                            <label class="block">
                                <span class="text-xs font-semibold uppercase text-secondary">BIC</span>
                                <input
                                    v-model="clubEditFormFor(club).sepa_bic"
                                    class="mt-1 w-full rounded-lg border border-border bg-inputBg px-3 py-2 text-sm text-primary"
                                    placeholder="GENODE..."
                                >
                            </label>
                        </div>
                    </div>

                    <div v-if="activeClubEditTab(club) === 'sponsoren'" class="space-y-4 rounded-lg border border-border bg-card p-3 md:col-span-2 xl:col-span-3">
                        <div class="flex flex-col gap-2 sm:flex-row sm:items-start sm:justify-between">
                            <div>
                                <p class="text-xs font-semibold uppercase text-secondary">Vereins-Sponsoren</p>
                                <p class="mt-1 text-xs text-secondary">
                                    Pflege Sponsoren, die öffentlich dem Verein zugeordnet werden.
                                </p>
                            </div>
                            <span class="rounded-full bg-inputBg px-3 py-1 text-xs font-semibold text-secondary">
                                {{ club.sponsors?.length || 0 }} Sponsoren
                            </span>
                        </div>

                        <div v-if="club.subscription_capabilities?.sponsors === false" class="rounded-lg border border-warning/30 bg-warning/10 p-3 text-sm text-warning">
                            Sponsorenverwaltung ist ab dem Club-Plan verfügbar.
                        </div>

                        <div class="grid gap-3 md:grid-cols-2 xl:grid-cols-3">
                            <label class="block">
                                <span class="text-xs font-semibold uppercase text-secondary">Sponsorname</span>
                                <input v-model="sponsorFormFor(club).name" class="mt-1 w-full rounded-lg border border-border bg-inputBg px-3 py-2 text-sm text-primary" placeholder="Sponsorname">
                            </label>
                            <label class="block">
                                <span class="text-xs font-semibold uppercase text-secondary">Kontaktperson</span>
                                <input v-model="sponsorFormFor(club).contact_name" class="mt-1 w-full rounded-lg border border-border bg-inputBg px-3 py-2 text-sm text-primary" placeholder="Ansprechpartner">
                            </label>
                            <label class="block">
                                <span class="text-xs font-semibold uppercase text-secondary">E-Mail</span>
                                <input v-model="sponsorFormFor(club).email" type="email" class="mt-1 w-full rounded-lg border border-border bg-inputBg px-3 py-2 text-sm text-primary" placeholder="sponsor@example.com">
                            </label>
                            <label class="block">
                                <span class="text-xs font-semibold uppercase text-secondary">Website</span>
                                <input v-model="sponsorFormFor(club).website" type="url" class="mt-1 w-full rounded-lg border border-border bg-inputBg px-3 py-2 text-sm text-primary" placeholder="https://...">
                            </label>
                            <label class="block">
                                <span class="text-xs font-semibold uppercase text-secondary">Budget / Betrag</span>
                                <input v-model="sponsorFormFor(club).amount" type="number" min="0" step="0.01" class="mt-1 w-full rounded-lg border border-border bg-inputBg px-3 py-2 text-sm text-primary" placeholder="0,00">
                            </label>
                            <div class="grid gap-3 sm:grid-cols-2">
                                <label class="block">
                                    <span class="text-xs font-semibold uppercase text-secondary">Start</span>
                                    <input v-model="sponsorFormFor(club).starts_at" type="date" class="mt-1 w-full rounded-lg border border-border bg-inputBg px-3 py-2 text-sm text-primary">
                                </label>
                                <label class="block">
                                    <span class="text-xs font-semibold uppercase text-secondary">Ende</span>
                                    <input v-model="sponsorFormFor(club).ends_at" type="date" class="mt-1 w-full rounded-lg border border-border bg-inputBg px-3 py-2 text-sm text-primary">
                                </label>
                            </div>
                            <label class="block md:col-span-1 xl:col-span-3">
                                <span class="text-xs font-semibold uppercase text-secondary">Logo für helle Flächen</span>
                                <input v-model="sponsorFormFor(club).logo_light" class="mt-1 w-full rounded-lg border border-border bg-inputBg px-3 py-2 text-sm text-primary" placeholder="sponsors/logo-light.webp oder https://...">
                            </label>
                            <label class="block md:col-span-1 xl:col-span-3">
                                <span class="text-xs font-semibold uppercase text-secondary">Logo für dunkle Flächen</span>
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
                                {{ editingSponsorIds[club.id] ? 'Sponsor speichern' : 'Sponsor erstellen' }}
                            </button>
                            <button
                                v-if="editingSponsorIds[club.id]"
                                type="button"
                                class="rounded-lg border border-border px-4 py-2 text-sm font-semibold text-primary"
                                @click="resetSponsorForm(club)"
                            >
                                Abbrechen
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
                                        <img v-if="sponsorLogoUrl(sponsor)" :src="sponsorLogoUrl(sponsor)" :alt="sponsor.name" class="h-full w-full object-contain p-1">
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
                                        Bearbeiten
                                    </button>
                                    <button type="button" class="rounded-lg border border-error px-3 py-1 text-sm font-semibold text-error" @click="deleteSponsor(club, sponsor)">
                                        Löschen
                                    </button>
                                </div>
                            </div>
                            <div v-if="!(club.sponsors || []).length" class="bg-bg p-4 text-sm text-secondary">
                                Noch keine Sponsoren für diesen Verein vorhanden.
                            </div>
                        </div>
                    </div>
                </div>

                <div class="mt-4 flex flex-col-reverse gap-2 sm:flex-row sm:justify-end">
                    <button type="button" class="rounded-lg border border-border px-4 py-2 text-sm font-semibold text-primary hover:bg-muted" @click="cancelClubEdit">
                        Abbrechen
                    </button>
                    <button class="rounded-lg bg-buttonPrimary px-4 py-2 text-sm font-semibold text-buttonTextPrimary">
                        Speichern
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
                                    {{ team.users?.length || 0 }} Mitglieder
                                </p>
                            </div>
                        </div>

                        <div class="flex shrink-0 items-center gap-2">
                            <span class="rounded-full bg-muted px-2 py-1 text-xs text-secondary">
                                Aktiv
                            </span>

                            <button
                                v-if="team.can_delete"
                                type="button"
                                class="rounded bg-error px-2 py-1 text-xs font-semibold text-white hover:opacity-90"
                                @click="deleteTeam(team)"
                            >
                                Löschen
                            </button>
                        </div>
                    </div>

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
                                {{ member.id === user?.id ? 'Team verlassen' : 'Entfernen' }}
                            </button>
                        </div>
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
                            {{ processingJoinTeamIds.has(team.id) ? 'Wird gesendet...' : 'Beitritt anfragen' }}
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
                            Deine Beitrittsanfrage wartet auf Freigabe.
                        </p>

                        <div v-if="team.pending_join_requests?.length" class="space-y-2">
                            <p class="text-xs font-semibold uppercase text-secondary">
                                Offene Team-Anfragen
                            </p>

                            <div
                                v-for="request in team.pending_join_requests"
                                :key="request.id"
                                class="flex flex-col gap-2 rounded border border-border bg-bg p-2 sm:flex-row sm:items-center sm:justify-between"
                            >
                                <div class="min-w-0">
                                    <p class="truncate text-sm font-semibold text-primary">
                                        {{ request.user?.name || 'Mitglied' }}
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
                                        Annehmen
                                    </button>
                                    <button
                                        type="button"
                                        class="rounded border border-border px-3 py-1.5 text-xs font-semibold text-primary hover:bg-muted"
                                        :disabled="processingJoinRequestIds.has(request.id)"
                                        :class="{ 'opacity-60': processingJoinRequestIds.has(request.id) }"
                                        @click="declineJoinRequest(request)"
                                    >
                                        Ablehnen
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>

                    <form
                        v-if="team.can_manage"
                        class="flex flex-col gap-2 sm:flex-row"
                        @submit.prevent="inviteUser(team)"
                    >
                        <input
                            v-model="inviteFormFor(team).email"
                            type="email"
                            placeholder="E-Mail"
                            class="min-w-0 flex-1 rounded border border-border bg-inputBg px-3 py-2 text-sm text-primary"
                        >

                        <button
                            class="rounded bg-buttonPrimary px-3 py-2 text-sm text-buttonTextPrimary disabled:opacity-50"
                            :disabled="club.subscription_capabilities?.member_invitation_remaining_today === 0"
                        >
                            Einladen
                        </button>
                    </form>

                    <p
                        v-if="club.subscription_capabilities?.member_invitation_daily_limit"
                        class="text-xs text-secondary"
                    >
                        Free-Limit: {{ club.subscription_capabilities.member_invitation_remaining_today }} von {{ club.subscription_capabilities.member_invitation_daily_limit }} Einladungen heute uebrig.
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
            </div>

            <!-- CLUB MEMBERS -->
            <div
                v-if="openClubId === club.id"
                class="rounded-xl border border-border bg-bg p-4"
            >
                <div class="mb-4 flex flex-col gap-3 sm:flex-row sm:items-end sm:justify-between">
                    <div>
                        <h2 class="font-semibold text-primary">
                            Vereinsmitglieder
                        </h2>

                        <p class="text-xs text-secondary">
                            Owner, Admins und Manager steuern die Rollen im Verein.
                        </p>
                    </div>

                    <span class="rounded-full bg-muted px-3 py-1 text-xs text-secondary">
                        {{ club.users?.length || 0 }} Mitglieder
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
                </div>
            </div>

            <!-- JOBS -->
            <div
                v-if="openClubId === club.id"
                class="rounded-xl border border-border bg-bg p-4"
            >
                <div class="mb-4 flex items-center justify-between gap-3">
                    <div>
                        <p class="text-xs font-semibold uppercase tracking-wide text-secondary">Engagement</p>
                        <h2 class="mt-1 text-lg font-semibold text-primary">
                            Jobs & Ehrenamt
                        </h2>

                        <p class="text-xs text-secondary">
                            Veröffentliche bezahlte Stellen, Ehrenamtsrollen und konkrete Aufgaben direkt auf der Jobs-Seite.
                        </p>
                    </div>

                    <div class="flex flex-wrap items-center gap-2">
                        <span class="rounded-full bg-muted px-3 py-1 text-xs text-secondary">
                            {{ club.jobs?.length || 0 }} Einträge
                        </span>
                        <button
                            v-if="club.can_manage_jobs"
                            type="button"
                            class="rounded-lg bg-buttonPrimary px-4 py-2 text-sm font-semibold text-buttonTextPrimary"
                            @click="openJobModal(club)"
                        >
                            Eintrag hinzufügen
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
                        placeholder="Titel, z.B. Jugendtrainer U15"
                    >

                    <select
                        v-model="jobFormFor(club).type"
                        class="rounded-lg border border-border bg-inputBg px-3 py-2 text-sm text-primary"
                    >
                        <option value="volunteer">Ehrenamt</option>
                        <option value="professional">Beruf / bezahlte Stelle</option>
                    </select>

                    <input
                        v-model="jobFormFor(club).location"
                        class="rounded-lg border border-border bg-inputBg px-3 py-2 text-sm text-primary"
                        placeholder="Ort / Remote"
                    >

                    <input
                        v-model="jobFormFor(club).workload"
                        class="rounded-lg border border-border bg-inputBg px-3 py-2 text-sm text-primary"
                        placeholder="Umfang, z.B. 6 Std./Woche"
                    >

                    <input
                        v-model="jobFormFor(club).employment_type"
                        class="rounded-lg border border-border bg-inputBg px-3 py-2 text-sm text-primary"
                        placeholder="Art, z.B. Teilzeit, Minijob, Ehrenamt"
                    >

                    <input
                        v-model="jobFormFor(club).contact_email"
                        type="email"
                        class="rounded-lg border border-border bg-inputBg px-3 py-2 text-sm text-primary"
                        placeholder="Kontakt E-Mail"
                    >

                    <input
                        v-model="jobFormFor(club).application_url"
                        class="rounded-lg border border-border bg-inputBg px-3 py-2 text-sm text-primary md:col-span-2"
                        placeholder="Externer Bewerbungslink optional"
                    >

                    <textarea
                        v-model="jobFormFor(club).description"
                        required
                        rows="4"
                        class="rounded-lg border border-border bg-inputBg px-3 py-2 text-sm text-primary md:col-span-2"
                        placeholder="Beschreibung, Aufgaben, Voraussetzungen"
                    ></textarea>

                    <label class="flex items-center gap-2 text-sm text-primary">
                        <input
                            v-model="jobFormFor(club).is_published"
                            type="checkbox"
                            class="rounded border-border bg-inputBg"
                        >
                        Auf Webseite veröffentlichen
                    </label>

                    <div class="flex gap-2 md:justify-end">
                        <button
                            v-if="editingJobId"
                            type="button"
                            class="rounded-lg border border-border px-4 py-2 text-sm text-primary"
                            @click="resetJobForm(club)"
                        >
                            Abbrechen
                        </button>

                        <button class="rounded-lg bg-buttonPrimary px-4 py-2 text-sm font-semibold text-buttonTextPrimary">
                            {{ editingJobId ? 'Aktualisieren' : 'Stelle erstellen' }}
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
                                    {{ job.type === 'volunteer' ? 'Ehrenamt' : 'Beruf' }}
                                </span>

                                <h3 class="mt-3 font-semibold text-primary">
                                    {{ job.title }}
                                </h3>

                                <p class="mt-1 break-words text-xs text-secondary">
                                    {{ job.location || 'Ort offen' }} · {{ job.workload || 'Umfang offen' }} · {{ job.employment_type || 'Art offen' }}
                                </p>
                            </div>

                            <span
                                class="rounded-full px-2 py-1 text-xs"
                                :class="job.is_published ? 'bg-air-green/15 text-air-green' : 'bg-muted text-secondary'"
                            >
                                {{ job.is_published ? 'Online' : 'Entwurf' }}
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
                                Kontakt: {{ job.contact_email }}
                            </a>
                            <a
                                v-if="job.application_url"
                                :href="job.application_url"
                                target="_blank"
                                rel="noopener noreferrer"
                                class="rounded-full border border-air-blue/30 px-3 py-1 text-air-blue hover:bg-air-blue/10"
                            >
                                Bewerbungslink prüfen
                            </a>
                        </div>

                        <div v-if="club.can_manage_jobs" class="mt-4 grid grid-cols-2 gap-2 sm:flex">
                            <button
                                type="button"
                                class="rounded border border-border px-3 py-2 text-sm text-primary hover:bg-muted"
                                @click="editJob(club, job)"
                            >
                                Bearbeiten
                            </button>

                            <button
                                type="button"
                                class="rounded bg-error px-3 py-2 text-sm text-white"
                                @click="deleteJob(job)"
                            >
                                Löschen
                            </button>
                        </div>
                    </article>

                    <div
                        v-if="!club.jobs?.length"
                        class="rounded-lg border border-dashed border-border bg-card p-5 text-sm text-secondary lg:col-span-2"
                    >
                        <p class="font-semibold text-primary">Noch keine Stellen veröffentlicht.</p>
                        <p class="mt-1">
                            Lege den ersten Eintrag an, damit interessierte Menschen passende Jobs oder Ehrenamtsrollen finden.
                        </p>
                        <button
                            v-if="club.can_manage_jobs"
                            type="button"
                            class="mt-4 rounded-lg bg-buttonPrimary px-4 py-2 text-sm font-semibold text-buttonTextPrimary"
                            @click="openJobModal(club)"
                        >
                            Ersten Eintrag erstellen
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
                            <h2 class="text-lg font-semibold text-primary">
                                Filter
                            </h2>

                            <p class="mt-1 text-sm text-secondary">
                                Suche nach Vereinen, Sportart oder Ort.
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
                            Verein suchen
                        </label>

                        <input
                            v-model="filtersForm.search"
                            class="mt-1 w-full rounded-lg border border-border bg-inputBg px-3 py-3 text-sm text-primary"
                            :placeholder="$t('Verein suchen')"
                        >
                    </div>

                    <div>
                        <label class="block text-sm font-semibold text-primary">
                            Sportart
                        </label>

                        <SearchableSelect
                            v-model="filtersForm.sport_type"
                            class="mt-1"
                            :options="sports"
                            value-key="slug"
                            translation-prefix="sports"
                            category-translation-prefix="sport_categories"
                            :placeholder="$t('Sportart suchen')"
                        />
                    </div>

                    <div>
                        <label class="block text-sm font-semibold text-primary">
                            Ort
                        </label>

                        <input
                            v-model="filtersForm.location"
                            class="mt-1 w-full rounded-lg border border-border bg-inputBg px-3 py-3 text-sm text-primary"
                            :placeholder="$t('Ort, Stadt, PLZ oder Land')"
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
                            Zurücksetzen
                        </button>

                        <button
                            type="button"
                            class="flex-1 rounded-lg bg-buttonPrimary px-4 py-3 font-semibold text-buttonTextPrimary hover:bg-buttonPrimaryHover"
                            @click="applyFilters"
                        >
                            Anwenden
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
                                {{ $t('Verein registrieren') }}
                            </h2>

                            <p class="mt-1 text-sm text-secondary">
                                Schritt {{ clubCreateStep }} von {{ clubCreateSteps.length }}
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
                                Basisdaten
                            </h3>

                            <p class="mt-1 text-sm text-secondary">
                                Name, Sportart und Land des Vereins. Nach dem Absenden prüft Airmius den Antrag.
                            </p>
                        </div>

                        <div>
                            <label class="block text-sm font-semibold text-primary">
                                Vereinsname
                            </label>

                            <input
                                v-model="clubForm.name"
                                class="mt-1 w-full rounded-lg border border-border bg-inputBg px-3 py-3 text-sm text-primary"
                                :class="clubForm.errors.name ? 'border-error' : ''"
                                placeholder="Vereinsname"
                                required
                            >
                            <p v-if="clubForm.errors.name" class="mt-1 text-xs text-error">{{ clubForm.errors.name }}</p>
                        </div>

                        <div>
                            <label class="block text-sm font-semibold text-primary">
                                Sportart
                            </label>

                            <SearchableSelect
                                v-model="clubForm.sport_type"
                                class="mt-1 w-full"
                                :options="sports"
                                value-key="slug"
                                translation-prefix="sports"
                                category-translation-prefix="sport_categories"
                                placeholder="Sportart suchen"
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
                                <span class="block font-semibold">Offizielle Prüfung beantragen</span>
                                <span class="block text-secondary">Der Verein wird erst nach Admin-Freigabe öffentlich sichtbar und als offiziell markiert.</span>
                            </span>
                        </label>

                        <div v-if="clubForm.is_official">
                            <label class="block text-sm font-semibold text-primary">
                                Vereinsnummer zur Prüfung
                            </label>

                            <input
                                v-model="clubForm.official_club_number"
                                class="mt-1 w-full rounded-lg border border-border bg-inputBg px-3 py-3 text-sm text-primary"
                                :class="clubForm.errors.official_club_number ? 'border-error' : ''"
                                placeholder="z. B. Vereinsregister- oder Verbandsnummer"
                            >
                            <p v-if="clubForm.errors.official_club_number" class="mt-1 text-xs text-error">{{ clubForm.errors.official_club_number }}</p>
                        </div>

                        <div>
                            <label class="block text-sm font-semibold text-primary">
                                Land
                            </label>

                            <select
                                v-model="clubForm.country"
                                class="mt-1 w-full rounded-lg border border-border bg-inputBg px-3 py-3 text-sm text-primary"
                                required
                            >
                                <option value="DE">Deutschland</option>
                                <option value="AT">Österreich</option>
                                <option value="CH">Schweiz</option>
                                <option value="FR">Frankreich</option>
                                <option value="NL">Niederlande</option>
                                <option value="BE">Belgien</option>
                                <option value="TR">Türkei</option>
                                <option value="US">USA</option>
                            </select>
                            <p v-if="clubForm.errors.country" class="mt-1 text-xs text-error">{{ clubForm.errors.country }}</p>
                        </div>
                    </section>

                    <section v-if="clubCreateStep === 2" class="space-y-4">
                        <div>
                            <h3 class="text-base font-semibold text-primary">
                                Adresse & Bankkonto
                            </h3>

                            <p class="mt-1 text-sm text-secondary">
                                Optional: Standort und Bankkonto für Mitglieder-Überweisungen eintragen.
                            </p>
                        </div>

                        <div class="grid grid-cols-1 gap-3 sm:grid-cols-2">
                            <div>
                                <input v-model="clubForm.city" class="w-full rounded-lg border border-border bg-inputBg px-3 py-3 text-sm text-primary" :class="clubForm.errors.city ? 'border-error' : ''" placeholder="Stadt">
                                <p v-if="clubForm.errors.city" class="mt-1 text-xs text-error">{{ clubForm.errors.city }}</p>
                            </div>
                            <div>
                                <input v-model="clubForm.postal_code" class="w-full rounded-lg border border-border bg-inputBg px-3 py-3 text-sm text-primary" :class="clubForm.errors.postal_code ? 'border-error' : ''" placeholder="PLZ">
                                <p v-if="clubForm.errors.postal_code" class="mt-1 text-xs text-error">{{ clubForm.errors.postal_code }}</p>
                            </div>
                            <div>
                                <input v-model="clubForm.state" class="w-full rounded-lg border border-border bg-inputBg px-3 py-3 text-sm text-primary" :class="clubForm.errors.state ? 'border-error' : ''" placeholder="Region">
                                <p v-if="clubForm.errors.state" class="mt-1 text-xs text-error">{{ clubForm.errors.state }}</p>
                            </div>
                            <div>
                                <input v-model="clubForm.street" class="w-full rounded-lg border border-border bg-inputBg px-3 py-3 text-sm text-primary" :class="clubForm.errors.street ? 'border-error' : ''" placeholder="Straße">
                                <p v-if="clubForm.errors.street" class="mt-1 text-xs text-error">{{ clubForm.errors.street }}</p>
                            </div>
                            <div>
                                <input v-model="clubForm.house_number" class="w-full rounded-lg border border-border bg-inputBg px-3 py-3 text-sm text-primary" :class="clubForm.errors.house_number ? 'border-error' : ''" placeholder="Hausnummer">
                                <p v-if="clubForm.errors.house_number" class="mt-1 text-xs text-error">{{ clubForm.errors.house_number }}</p>
                            </div>
                            <div class="rounded-lg border border-border bg-card p-3 sm:col-span-2">
                                <p class="text-xs font-semibold uppercase text-secondary">Bankkonto für Vereinsrechnungen</p>
                                <p class="mt-1 text-xs text-secondary">
                                    Diese Daten werden Mitgliedern angezeigt, wenn sie offene Vereinsrechnungen per Überweisung zahlen.
                                </p>

                                <div class="mt-3 grid gap-3 sm:grid-cols-3">
                                    <div>
                                        <input v-model="clubForm.sepa_account_holder" class="w-full rounded-lg border border-border bg-inputBg px-3 py-3 text-sm text-primary" :class="clubForm.errors.sepa_account_holder ? 'border-error' : ''" placeholder="Kontoinhaber">
                                        <p v-if="clubForm.errors.sepa_account_holder" class="mt-1 text-xs text-error">{{ clubForm.errors.sepa_account_holder }}</p>
                                    </div>
                                    <div>
                                        <input v-model="clubForm.sepa_iban" class="w-full rounded-lg border border-border bg-inputBg px-3 py-3 text-sm text-primary" :class="clubForm.errors.sepa_iban ? 'border-error' : ''" placeholder="IBAN">
                                        <p v-if="clubForm.errors.sepa_iban" class="mt-1 text-xs text-error">{{ clubForm.errors.sepa_iban }}</p>
                                    </div>
                                    <div>
                                        <input v-model="clubForm.sepa_bic" class="w-full rounded-lg border border-border bg-inputBg px-3 py-3 text-sm text-primary" :class="clubForm.errors.sepa_bic ? 'border-error' : ''" placeholder="BIC">
                                        <p v-if="clubForm.errors.sepa_bic" class="mt-1 text-xs text-error">{{ clubForm.errors.sepa_bic }}</p>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </section>

                    <section v-if="clubCreateStep === 3" class="space-y-4">
                        <div>
                            <h3 class="text-base font-semibold text-primary">
                                Prüfen
                            </h3>

                            <p class="mt-1 text-sm text-secondary">
                                Kontrolliere die Angaben vor dem Absenden. Der Verein wird als Antrag gespeichert.
                            </p>
                        </div>

                        <div class="rounded-xl border border-border bg-inputBg p-4">
                            <div class="space-y-3 text-sm">
                                <p><strong>Verein:</strong> {{ clubForm.name || '-' }}</p>
                                <p><strong>Sportart:</strong> {{ sportLabel(clubForm.sport_type) }}</p>
                                <p><strong>Offizielle Prüfung:</strong> {{ clubForm.is_official ? 'Beantragt' : 'Nicht beantragt' }}</p>
                                <p v-if="clubForm.is_official"><strong>Vereinsnummer zur Prüfung:</strong> {{ clubForm.official_club_number || '-' }}</p>
                                <p><strong>Status nach Absenden:</strong> Wartet auf Prüfung</p>
                                <p><strong>Land:</strong> {{ clubForm.country || '-' }}</p>
                                <p>
                                    <strong>Adresse:</strong>
                                    {{ clubForm.street || '-' }}
                                    {{ clubForm.house_number || '' }},
                                    {{ clubForm.postal_code || '' }}
                                    {{ clubForm.city || '' }}
                                </p>
                                <p><strong>Region:</strong> {{ clubForm.state || '-' }}</p>
                                <p><strong>Kontoinhaber:</strong> {{ clubForm.sepa_account_holder || '-' }}</p>
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
                            Zurück
                        </button>

                        <button
                            v-if="clubCreateStep < clubCreateSteps.length"
                            type="button"
                            class="flex-1 rounded-lg bg-buttonPrimary px-4 py-3 font-semibold text-buttonTextPrimary hover:bg-buttonPrimaryHover"
                            @click="nextClubStep"
                        >
                            Weiter
                        </button>

                        <button
                            v-else
                            type="button"
                            class="flex-1 rounded-lg bg-buttonPrimary px-4 py-3 font-semibold text-buttonTextPrimary hover:bg-buttonPrimaryHover disabled:cursor-not-allowed disabled:opacity-60"
                            :disabled="clubForm.processing"
                            @click="createClub"
                        >
                            {{ clubForm.processing ? 'Speichert...' : 'Speichern' }}
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
                Team erstellen
            </h2>

            <input
                v-model="teamFormFor(selectedClub).name"
                class="w-full rounded border border-border bg-inputBg p-3 text-primary"
                placeholder="Teamname"
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
                placeholder="Sportart suchen"
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
                Erstellen
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
                    {{ editingJobId ? 'Eintrag bearbeiten' : 'Jobs- oder Ehrenamtsangebot erstellen' }}
                </h2>
                <p class="mt-2 text-sm text-secondary">
                    Beschreibe die Aufgabe klar genug, damit Interessierte sofort verstehen, ob sie passt und wie sie Kontakt aufnehmen können.
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
                <h3 class="text-sm font-semibold text-primary">Was wird gesucht?</h3>

                <div class="grid gap-3 sm:grid-cols-2">
                    <label class="block sm:col-span-2">
                        <span class="text-xs font-semibold uppercase text-secondary">Titel *</span>
                        <input
                            v-model="jobFormFor(selectedJobClub).title"
                            required
                            autocomplete="off"
                            class="mt-1 w-full rounded-lg border border-border bg-inputBg px-3 py-3 text-sm text-primary"
                            placeholder="z.B. Jugendtrainer U15"
                        >
                        <span v-if="errors.title" class="mt-1 block text-xs text-error">{{ errors.title }}</span>
                    </label>

                    <label class="block">
                        <span class="text-xs font-semibold uppercase text-secondary">Kategorie</span>
                        <select
                            v-model="jobFormFor(selectedJobClub).type"
                            class="mt-1 w-full rounded-lg border border-border bg-inputBg px-3 py-3 text-sm text-primary"
                        >
                            <option value="volunteer">Ehrenamt</option>
                            <option value="professional">Beruf / bezahlte Stelle</option>
                        </select>
                        <span v-if="errors.type" class="mt-1 block text-xs text-error">{{ errors.type }}</span>
                    </label>

                    <label class="block">
                        <span class="text-xs font-semibold uppercase text-secondary">Art</span>
                        <input
                            v-model="jobFormFor(selectedJobClub).employment_type"
                            autocomplete="off"
                            class="mt-1 w-full rounded-lg border border-border bg-inputBg px-3 py-3 text-sm text-primary"
                            placeholder="Teilzeit, Minijob, Ehrenamt"
                        >
                        <span v-if="errors.employment_type" class="mt-1 block text-xs text-error">{{ errors.employment_type }}</span>
                    </label>
                </div>
            </section>

            <section class="space-y-3">
                <h3 class="text-sm font-semibold text-primary">Rahmen</h3>

                <div class="grid gap-3 sm:grid-cols-2">
                    <label class="block">
                        <span class="text-xs font-semibold uppercase text-secondary">Adresse / Ort</span>
                        <input
                            v-model="jobFormFor(selectedJobClub).location"
                            autocomplete="address-line1"
                            class="mt-1 w-full rounded-lg border border-border bg-inputBg px-3 py-3 text-sm text-primary"
                            placeholder="Sportanlage, Adresse, Stadt oder Remote"
                        >
                        <span v-if="errors.location" class="mt-1 block text-xs text-error">{{ errors.location }}</span>
                    </label>

                    <label class="block">
                        <span class="text-xs font-semibold uppercase text-secondary">Umfang</span>
                        <input
                            v-model="jobFormFor(selectedJobClub).workload"
                            autocomplete="off"
                            class="mt-1 w-full rounded-lg border border-border bg-inputBg px-3 py-3 text-sm text-primary"
                            placeholder="z.B. 6 Std./Woche"
                        >
                        <span v-if="errors.workload" class="mt-1 block text-xs text-error">{{ errors.workload }}</span>
                    </label>
                </div>
            </section>

            <section class="space-y-3">
                <h3 class="text-sm font-semibold text-primary">Beschreibung & Kontakt</h3>

                <label class="block">
                    <span class="text-xs font-semibold uppercase text-secondary">Beschreibung *</span>
                    <textarea
                        v-model="jobFormFor(selectedJobClub).description"
                        required
                        rows="5"
                        class="mt-1 w-full rounded-lg border border-border bg-inputBg px-3 py-3 text-sm text-primary"
                        placeholder="Aufgaben, Voraussetzungen, Zeitraum und was die Person wissen sollte."
                    ></textarea>
                    <span v-if="errors.description" class="mt-1 block text-xs text-error">{{ errors.description }}</span>
                </label>

                <div class="grid gap-3 sm:grid-cols-2">
                    <label class="block">
                        <span class="text-xs font-semibold uppercase text-secondary">Kontakt E-Mail</span>
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
                        <span class="text-xs font-semibold uppercase text-secondary">Externer Bewerbungslink optional</span>
                        <input
                            v-model="jobFormFor(selectedJobClub).application_url"
                            type="url"
                            inputmode="url"
                            autocomplete="url"
                            class="mt-1 w-full rounded-lg border border-border bg-inputBg px-3 py-3 text-sm text-primary"
                            placeholder="https://formular.verein.de"
                        >
                        <span v-if="errors.application_url" class="mt-1 block text-xs text-error">{{ errors.application_url }}</span>
                        <span class="mt-1 block text-xs text-secondary">
                            Nur ausfüllen, wenn Interessierte zusätzlich auf ein externes Formular weitergeleitet werden sollen.
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
                    <span class="block font-semibold">Auf Webseite veröffentlichen</span>
                    <span class="block text-xs text-secondary">Wenn deaktiviert, bleibt der Eintrag als Entwurf im Dashboard.</span>
                </span>
            </label>

            <div class="flex flex-col-reverse gap-2 sm:flex-row sm:justify-end">
                <button
                    type="button"
                    class="rounded-lg border border-border px-4 py-3 text-sm font-semibold text-primary hover:bg-muted"
                    @click="closeJobModal"
                >
                    Abbrechen
                </button>
                <button
                    class="rounded-lg bg-buttonPrimary px-4 py-3 text-sm font-semibold text-buttonTextPrimary disabled:cursor-not-allowed disabled:opacity-60"
                    :disabled="isSubmittingJob"
                    :aria-busy="isSubmittingJob"
                >
                    {{ isSubmittingJob ? 'Wird gespeichert…' : (editingJobId ? 'Aktualisieren' : 'Eintrag erstellen') }}
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
                Bitte gib <strong>{{ deleteTarget.confirmText || 'delete' }}</strong> ein, um die Aktion zu bestätigen.
            </div>

            <label class="block">
                <span class="text-sm font-semibold text-primary">Bestätigung</span>
                <input
                    v-model="deleteConfirmation"
                    class="mt-1 w-full rounded-lg border border-border bg-inputBg px-3 py-2 text-sm text-primary"
                    :placeholder="deleteTarget.confirmText || 'delete'"
                    autocomplete="off"
                >
            </label>

            <label v-if="deleteTarget.requiresReason" class="block">
                <span class="text-sm font-semibold text-primary">Begründung</span>
                <textarea
                    v-model="deleteReason"
                    rows="4"
                    class="mt-1 w-full rounded-lg border border-border bg-inputBg px-3 py-2 text-sm text-primary"
                    placeholder="Warum möchtest du dieses Team verlassen?"
                ></textarea>
                <p class="mt-1 text-xs text-secondary">Die Begründung wird an die Vereinsverantwortlichen gesendet.</p>
            </label>

            <div class="flex flex-col-reverse gap-2 sm:flex-row sm:justify-end">
                <button
                    type="button"
                    class="rounded-lg border border-border px-4 py-2 text-sm font-semibold text-primary hover:bg-muted"
                    @click="closeDeleteModal"
                >
                    Abbrechen
                </button>
                <button
                    type="button"
                    class="rounded-lg bg-error px-4 py-2 text-sm font-semibold text-white disabled:cursor-not-allowed disabled:opacity-50"
                    :disabled="deleteConfirmation !== (deleteTarget.confirmText || 'delete')"
                    @click="confirmDelete"
                >
                    {{ deleteTarget.buttonLabel || 'Endgültig löschen' }}
                </button>
            </div>
        </div>
    </Modal>
</template>
