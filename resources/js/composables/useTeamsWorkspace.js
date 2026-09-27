import { router, useForm, usePage } from '@inertiajs/vue3'
import { computed, ref } from 'vue'
import { useTeamsJobs } from '@/composables/useTeamsJobs'

export function useTeamsWorkspace({ props, t, te }) {
const page = usePage()
const user = page.props.auth?.user
const tx = (key, fallback, params = {}) => te(`teams_workspace.${key}`) ? t(`teams_workspace.${key}`, params) : fallback

const showClubModal = ref(false)
const showTeamModal = ref(false)
const showFilterModal = ref(false)
const showDeleteModal = ref(false)

const selectedClub = ref(null)
const openClubId = ref(null)
const editingClubId = ref(null)
const actionNotice = ref(null)
const clubModalNotice = ref(null)
const deleteTarget = ref(null)
const deleteConfirmation = ref('')
const deleteReason = ref('')
const errors = computed(() => page.props.errors || {})

const clubCreateStep = ref(1)

const clubCreateSteps = computed(() => [
    { number: 1, label: tx('steps.basic', 'Basis') },
    { number: 2, label: tx('steps.address', 'Adresse') },
    { number: 3, label: tx('steps.review', 'Prüfen') },
])

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
const teamMemberForms = ref({})
const inviteNotices = ref({})
const joinRequestNotices = ref({})
const teamForms = ref({})
const teamEditForms = ref({})
const editingTeamIds = ref(new Set())
const clubEditForms = ref({})
const clubEditTabs = ref({})
const sponsorForms = ref({})
const editingSponsorIds = ref({})
const processingJoinTeamIds = ref(new Set())
const processingJoinRequestIds = ref(new Set())
const teamInsights = ref({})
const teamInsightsLoading = ref(new Set())

const initials = (name) =>
    name?.split(' ').map(n => n[0]).join('').slice(0, 2).toUpperCase()

const sportLabel = (value) => {
    if (!value) return tx('sport_open', 'Sportart offen')

    const sport = props.sports.find((sport) => sport.slug === value || sport.name === value)
    const slug = sport?.slug || value
    const key = `sports.${slug}`

    return te(key) ? t(key) : (sport?.name || value)
}

const clubRoleLabel = (role) => ({
    owner: tx('roles.owner', 'Owner'),
    admin: tx('roles.admin', 'Verein-Admin'),
    manager: tx('roles.manager', 'Manager'),
    academy_manager: tx('roles.academy_manager', 'Akademie-Manager'),
    financial_controller: tx('roles.financial_controller', 'Kassierer'),
    trainer: tx('roles.trainer', 'Trainer'),
    member: tx('roles.member', 'Mitglied'),
}[role] || role)
const clubRoleList = (member) => Array.isArray(member.pivot.roles) && member.pivot.roles.length
    ? member.pivot.roles
    : [member.pivot.role || 'member']

const teamRoleLabel = (role) => ({
    Coach: tx('team_roles.coach', 'Trainer'),
    Captain: tx('team_roles.captain', 'Kapitän'),
    Player: tx('team_roles.player', 'Spieler'),
}[role] || role)

const toggleClub = (club) => {
    openClubId.value = openClubId.value === club.id ? null : club.id
}

const clubEditTabItems = computed(() => [
    { key: 'basis', label: tx('tabs.basic', 'Basis') },
    { key: 'sichtbarkeit', label: tx('tabs.visibility', 'Sichtbarkeit') },
    { key: 'adresse', label: tx('tabs.address', 'Adresse') },
    { key: 'bank', label: tx('tabs.bank', 'Bankkonto') },
    { key: 'sponsoren', label: tx('tabs.sponsors', 'Sponsoren') },
])

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
    if (clubCreateStep.value < clubCreateSteps.value.length) {
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
    const form = teamFormFor(club)
    if (!club.can_create_teams_globally && !form.club_department_id) {
        form.club_department_id = club.team_creation_departments?.[0]?.id ?? null
    }
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
            club_department_id: null,
            sport_year_period_id: null,
        }
    }

    teamForms.value[club.id] ??= {
        club_id: club.id,
        name: '',
        sport_type: club.sport_type || props.sports[0]?.slug || '',
        club_department_id: club.can_create_teams_globally
            ? null
            : (club.team_creation_departments?.[0]?.id ?? null),
        sport_year_period_id: null,
    }

    return teamForms.value[club.id]
}

const teamEditFormFor = (team) => {
    if (!team?.id) {
        return {
            name: '',
            sport_type: '',
            sport_year_period_id: null,
        }
    }

    teamEditForms.value[team.id] ??= {
        name: team.name || '',
        sport_type: team.sport_type || '',
        sport_year_period_id: team.sport_year_period_id || null,
    }

    return teamEditForms.value[team.id]
}

const editTeam = (team) => {
    if (!team?.id) return

    teamEditFormFor(team)
    editingTeamIds.value.add(team.id)
}

const cancelTeamEdit = (team) => {
    if (!team?.id) return

    editingTeamIds.value.delete(team.id)
}

const updateTeam = (team) => {
    if (!team?.id) return

    actionNotice.value = null

    router.put(route('auth.teams.update', team.id), teamEditFormFor(team), {
        preserveScroll: true,
        onSuccess: () => {
            editingTeamIds.value.delete(team.id)
            setActionNotice('success', tx('messages.team_saved', 'Team wurde gespeichert.'))
        },
        onError: () => setActionNotice('error', tx('messages.team_save_error', 'Team konnte nicht gespeichert werden. Bitte prüfe die Eingaben.')),
    })
}

const inviteFormFor = (team) => {
    inviteForms.value[team.id] ??= { email: '', role: 'Player' }
    return inviteForms.value[team.id]
}

const teamMemberFormFor = (team) => {
    teamMemberForms.value[team.id] ??= { user_id: '', role: 'Player' }
    return teamMemberForms.value[team.id]
}

const availableTeamMemberOptions = (team) => {
    const club = clubForTeam(team)
    const currentMemberIds = new Set((team.users || []).map((member) => Number(member.id)))

    return (club?.users || [])
        .filter((member) => !currentMemberIds.has(Number(member.id)))
        .sort((a, b) => String(a.name || '').localeCompare(String(b.name || '')))
}

const loadTeamInsights = async (team) => {
    if (!team?.id || teamInsightsLoading.value.has(team.id)) return

    teamInsightsLoading.value.add(team.id)

    try {
        const response = await window.axios.get(route('api.v1.teams.competitiveness.insights', team.id))
        teamInsights.value[team.id] = response.data?.data || null
    } catch (error) {
        teamInsights.value[team.id] = {
            error: error.response?.data?.message || tx('messages.insights_error', 'Team-Alltag konnte nicht geladen werden.'),
        }
    } finally {
        teamInsightsLoading.value.delete(team.id)
    }
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

const {
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
} = useTeamsJobs({
    openDeleteModal,
    tx,
    setActionNotice: (type, message) => {
        if (type === null) {
            actionNotice.value = null
            return
        }

        setActionNotice(type, message)
    },
})

const confirmDelete = () => {
    if (!deleteTarget.value || deleteConfirmation.value !== (deleteTarget.value.confirmText || 'delete')) return

    const target = deleteTarget.value
    actionNotice.value = null

    router.delete(route(target.route, target.params), {
        data: { ...(target.requiresReason ? { reason: deleteReason.value } : {}), confirmation: deleteConfirmation.value },
        preserveScroll: true,
        onSuccess: () => {
            setActionNotice('success', target.successMessage)
            closeDeleteModal()
        },
        onError: (errors) => setActionNotice('error', errors.club || errors.confirmation || errors.team || errors.user || target.errorMessage),
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
            setActionNotice('success', tx('messages.club_registered', 'Verein wurde registriert.'))
        },
        onError: (errors) => {
            const firstMessage = Object.values(errors)[0]
            clubModalNotice.value = firstMessage || tx('messages.club_register_error', 'Verein konnte nicht registriert werden. Bitte prüfe die markierten Eingaben.')

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
                club_department_id: selectedClub.value?.can_create_teams_globally
                    ? null
                    : (selectedClub.value?.team_creation_departments?.[0]?.id ?? null),
                sport_year_period_id: null,
            }

            closeTeamModal()
            setActionNotice('success', tx('messages.team_created', 'Team wurde erstellt.'))
        },
        onError: () => setActionNotice('error', tx('messages.team_create_error', 'Team konnte nicht erstellt werden. Bitte prüfe die Eingaben.')),
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
            setInviteNotice(team, 'success', tx('messages.invite_sent', 'Einladung wurde erfolgreich gesendet.'))
        },
        onError: (errors) => {
            const message = errors.email
                || errors.user_id
                || errors.role
                || errors.general
                || errors.message
                || Object.values(errors)[0]
                || tx('messages.invite_error', 'Einladung konnte nicht gesendet werden.')
            setInviteNotice(team, 'error', message)
        },
    })
}

const addTeamMember = (team) => {
    actionNotice.value = null
    inviteNotices.value[team.id] = null

    router.post(route('auth.teams.members.store', team.id), teamMemberFormFor(team), {
        preserveScroll: true,
        onSuccess: () => {
            teamMemberForms.value[team.id] = { user_id: '', role: 'Player' }
            setInviteNotice(team, 'success', tx('messages.member_added', 'Mitglied wurde zum Team hinzugefügt.'))
        },
        onError: (errors) => {
            const message = errors.user_id
                || errors.role
                || errors.general
                || errors.message
                || Object.values(errors)[0]
                || tx('messages.member_add_error', 'Mitglied konnte nicht hinzugefügt werden.')
            setInviteNotice(team, 'error', message)
        },
    })
}

const acceptInvitation = (invitation) => {
    actionNotice.value = null

    router.post(route('auth.team-invitations.accept', invitation.id), {}, {
        preserveScroll: true,
        onSuccess: () => setActionNotice('success', tx('messages.invitation_accepted', 'Team-Einladung wurde angenommen.')),
        onError: () => setActionNotice('error', tx('messages.invitation_accept_error', 'Team-Einladung konnte nicht angenommen werden.')),
    })
}

const declineInvitation = (invitation) => {
    actionNotice.value = null

    router.post(route('auth.team-invitations.decline', invitation.id), {}, {
        preserveScroll: true,
        onSuccess: () => setActionNotice('success', tx('messages.invitation_declined', 'Team-Einladung wurde abgelehnt.')),
        onError: () => setActionNotice('error', tx('messages.invitation_decline_error', 'Team-Einladung konnte nicht abgelehnt werden.')),
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
            setJoinRequestNotice(team, 'success', tx('messages.join_request_sent', 'Team-Beitrittsanfrage wurde gesendet.'))
        },
        onError: (errors) => {
            setJoinRequestNotice(team, 'error', errors.team || errors.general || errors.message || tx('messages.join_request_send_error', 'Team-Beitrittsanfrage konnte nicht gesendet werden.'))
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
        onSuccess: () => setActionNotice('success', tx('messages.join_request_approved', 'Team-Beitrittsanfrage wurde angenommen.')),
        onError: (errors) => setActionNotice('error', errors.join_request || errors.general || errors.message || tx('messages.join_request_approve_error', 'Team-Beitrittsanfrage konnte nicht angenommen werden.')),
        onFinish: () => processingJoinRequestIds.value.delete(request.id),
    })
}

const declineJoinRequest = (request) => {
    actionNotice.value = null

    if (processingJoinRequestIds.value.has(request.id)) return
    processingJoinRequestIds.value.add(request.id)

    router.post(route('auth.team-join-requests.decline', request.id), {}, {
        preserveScroll: true,
        onSuccess: () => setActionNotice('success', tx('messages.join_request_declined', 'Team-Beitrittsanfrage wurde abgelehnt.')),
        onError: (errors) => setActionNotice('error', errors.join_request || errors.general || errors.message || tx('messages.join_request_decline_error', 'Team-Beitrittsanfrage konnte nicht abgelehnt werden.')),
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
        onSuccess: () => setActionNotice('success', tx('messages.club_role_saved', 'Vereinsrolle wurde gespeichert.')),
        onError: () => setActionNotice('error', tx('messages.club_role_error', 'Vereinsrolle konnte nicht gespeichert werden.')),
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
            setActionNotice('success', tx('messages.club_saved', 'Vereinsdaten wurden gespeichert.'))
        },
        onError: () => setActionNotice('error', tx('messages.club_save_error', 'Vereinsdaten konnten nicht gespeichert werden. Bitte prüfe die Eingaben.')),
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
            setActionNotice('success', sponsorId ? tx('messages.sponsor_updated', 'Sponsor wurde aktualisiert.') : tx('messages.sponsor_created', 'Sponsor wurde erstellt.'))
        },
        onError: () => setActionNotice('error', tx('messages.sponsor_save_error', 'Sponsor konnte nicht gespeichert werden. Bitte prüfe die Eingaben.')),
    }

    sponsorId
        ? router.put(route('auth.clubs.sponsors.update', [club.id, sponsorId]), sponsorFormFor(club), options)
        : router.post(route('auth.clubs.sponsors.store', club.id), sponsorFormFor(club), options)
}

const deleteSponsor = (club, sponsor) => {
    openDeleteModal({
        title: tx('messages.sponsor_remove_title', `Sponsor "${sponsor.name}" löschen`, { name: sponsor.name }),
        description: tx('messages.sponsor_remove_message', 'Der Sponsor wird aus diesem Verein entfernt. Diese Aktion kann nicht rückgängig gemacht werden.'),
        route: 'auth.clubs.sponsors.destroy',
        params: [club.id, sponsor.id],
        successMessage: tx('messages.sponsor_removed', 'Sponsor wurde gelöscht.'),
        errorMessage: tx('messages.sponsor_remove_error', 'Sponsor konnte nicht gelöscht werden.'),
    })
}

const sponsorLogoUrl = (sponsor) => sponsor.logo_light_url || sponsor.logo_url || sponsor.logo_light || sponsor.logo

const updateTeamMemberRole = (team, member) => {
    actionNotice.value = null

    router.put(route('auth.teams.members.update', [team.id, member.id]), {
        role: member.pivot.role,
    }, {
        preserveScroll: true,
        onSuccess: () => setActionNotice('success', tx('messages.team_role_saved', 'Teamrolle wurde gespeichert.')),
        onError: () => setActionNotice('error', tx('messages.team_role_error', 'Teamrolle konnte nicht gespeichert werden.')),
    })
}

const removeTeamMember = (team, member) => {
    const isLeavingSelf = member.id === user?.id

    openDeleteModal({
        title: isLeavingSelf
            ? tx('messages.leave_team_title', `Team "${team.name}" verlassen`, { name: team.name })
            : tx('messages.remove_member_title', `${member.name} aus "${team.name}" entfernen`, { member: member.name, team: team.name }),
        description: isLeavingSelf
            ? tx('messages.leave_team_message', 'Du kannst dieses Team nur verlassen, wenn alle offenen Rechnungen im zugehörigen Verein ausgeglichen sind.')
            : tx('messages.remove_member_message', 'Das Mitglied wird aus diesem Team entfernt. Die Vereinsmitgliedschaft bleibt bestehen.'),
        route: 'auth.teams.members.destroy',
        params: [team.id, member.id],
        confirmText: isLeavingSelf ? 'verlassen' : 'entfernen',
        buttonLabel: isLeavingSelf ? tx('messages.leave_team_button', 'Team verlassen') : tx('messages.remove_member_button', 'Mitglied entfernen'),
        requiresReason: isLeavingSelf,
        successMessage: isLeavingSelf ? tx('messages.team_left', 'Du hast das Team verlassen.') : tx('messages.member_removed', 'Mitglied wurde aus dem Team entfernt.'),
        errorMessage: isLeavingSelf
            ? tx('messages.leave_team_error', 'Team konnte nicht verlassen werden. Bitte prüfe, ob noch offene Rechnungen vorhanden sind.')
            : tx('messages.member_remove_error', 'Mitglied konnte nicht entfernt werden.'),
    })
}

const deleteClub = (club) => {
    openDeleteModal({
        title: tx('messages.delete_club_title', `Verein "${club.name}" löschen`, { name: club.name }),
        description: 'Die Löschung wird frühestens in 30 Tagen ausgeführt. Bis dahin kannst du sie im Vereinsbereich zurücknehmen. Vereinsdaten und Dateien werden entfernt; persönliche Konten, andere Vereine und aufbewahrte Abrechnungs-/Supportunterlagen bleiben erhalten. Besitzer und eingetragener Vorstand werden informiert.',
        confirmText: 'Ja, ich bin mir sicher',
        buttonLabel: 'Löschung in 30 Tagen vormerken',
        route: 'auth.clubs.destroy',
        params: club.id,
        successMessage: 'Löschung vorgemerkt. Du kannst sie im Vereinsbereich zurücknehmen.',
        errorMessage: tx('messages.club_delete_error', 'Verein konnte nicht gelöscht werden.'),
    })
}

const deleteTeam = (team) => {
    openDeleteModal({
        title: tx('messages.delete_team_title', `Team "${team.name}" löschen`, { name: team.name }),
        description: tx('messages.delete_team_message', 'Das Team und seine Zuordnungen werden entfernt. Diese Aktion kann nicht rückgängig gemacht werden.'),
        route: 'auth.teams.destroy',
        params: team.id,
        successMessage: tx('messages.team_deleted', 'Team wurde gelöscht.'),
        errorMessage: tx('messages.team_delete_error', 'Team konnte nicht gelöscht werden.'),
    })
}

    return {
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
        editingTeamIds,
        teamForms,
        teamEditForms,
        teamEditFormFor,
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
        teamEditFormFor,
        editTeam,
        cancelTeamEdit,
        updateTeam,
        editingTeamIds,
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
    }
}
