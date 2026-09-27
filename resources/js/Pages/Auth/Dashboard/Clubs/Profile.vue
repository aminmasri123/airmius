<script setup>
import AppLayout from '@/Components/Auth/Layouts/AppLayout.vue'
import ClubWorkspaceNav from '@/Components/Auth/ClubWorkspaceNav.vue'
import AppButton from '@/Components/UI/AppButton.vue'
import AppEmptyState from '@/Components/UI/AppEmptyState.vue'
import AppLoadingState from '@/Components/UI/AppLoadingState.vue'
import ClubGovernanceSection from '@/Components/Clubs/ClubGovernanceSection.vue'
import ClubMetadataSection from '@/Components/Clubs/ClubMetadataSection.vue'
import ClubPolicyDocumentsSection from '@/Components/Clubs/ClubPolicyDocumentsSection.vue'
import ClubYearPeriodsSection from '@/Components/Clubs/ClubYearPeriodsSection.vue'
import ClubDeletionPanel from '@/Components/Clubs/ClubDeletionPanel.vue'
import { Head, Link, router, useForm, usePage } from '@inertiajs/vue3'
import { computed, onMounted, ref } from 'vue'
import { confirmDialog } from '@/services/dialogService'
import { useI18n } from 'vue-i18n'
import legalTranslations from '@/i18n/clubLegalMasterDataLocalization.json'
import contactTranslations from '@/i18n/clubContactMasterDataLocalization.json'
import brandingTranslations from '@/i18n/clubBrandingLocalization.json'
import organizationTranslations from '@/i18n/clubOrganizationLocalization.json'
import membershipChangeTranslations from '@/i18n/clubMembershipChangeLocalization.json'

const props = defineProps({
    clubProfile: Object,
    clubRoles: { type: Array, default: () => ['owner', 'admin', 'manager', 'member'] },
    posts: { type: Array, default: () => [] },
    viewer: Object,
})

const { locale, messages, te, t } = useI18n({ useScope: 'global' })
const tAuto = (value, params = {}) => {
    const source = String(value ?? '').trim()
    if (!source || locale.value === 'de') return source
    const dictionary = messages.value?.[locale.value]?.auto || {}
    if (dictionary[source]) return dictionary[source]
    return te(source) ? t(source, params) : source
}
const localeCode = computed(() => ({ ar: 'ar-EG', fr: 'fr-FR', en: 'en-US', de: 'de-DE' })[locale.value] || 'de-DE')
const legalText = computed(() => legalTranslations[locale.value] || legalTranslations.de)
const lt = (key) => legalText.value[key] || legalTranslations.de[key] || key
const contactText = computed(() => contactTranslations[locale.value] || contactTranslations.de)
const ct = (key) => contactText.value[key] || contactTranslations.de[key] || key
const brandingText = computed(() => brandingTranslations[locale.value] || brandingTranslations.de)
const bt = (key) => brandingText.value[key] || brandingTranslations.de[key] || key
const organizationText = computed(() => organizationTranslations[locale.value] || organizationTranslations.de)
const ot = (key) => organizationText.value[key] || organizationTranslations.de[key] || key
const membershipChangeText = computed(() => membershipChangeTranslations[locale.value] || membershipChangeTranslations.de)
const mct = (key) => membershipChangeText.value[key] || membershipChangeTranslations.de[key] || key
const initials = (name) => (name || '?').split(' ').slice(0, 2).map((part) => part.charAt(0)).join('').toUpperCase()
const formatDate = (value) => new Intl.DateTimeFormat(localeCode.value, { day: '2-digit', month: '2-digit', year: 'numeric' }).format(new Date(value))
const formatDateTime = (value) => new Intl.DateTimeFormat(localeCode.value, { dateStyle: 'medium', timeStyle: 'short' }).format(new Date(value))
const page = usePage()
const storageUrl = (path) => path?.startsWith('http') ? path : `${page.props.uploads?.url || '/storage'}/${path}`
const clubRoleLabel = (role) => ({
    owner: tAuto('Owner'),
    admin: tAuto('Verein-Admin'),
    manager: tAuto('Manager'),
    academy_manager: tAuto('Akademie-Manager'),
    financial_controller: tAuto('Kassierer'),
    trainer: tAuto('Trainer'),
    member: tAuto('Mitglied'),
}[role] || role)
const memberRoles = (member) => Array.isArray(member.pivot.roles) && member.pivot.roles.length
    ? member.pivot.roles
    : [member.pivot.role || 'member']
const toggleMemberRole = (member, role) => {
    const roles = memberRoles(member)

    member.pivot.roles = roles.includes(role)
        ? roles.filter((value) => value !== role)
        : [...roles, role]
}
const logoInput = ref(null)
const coverInput = ref(null)
const membershipRequestOpen = ref(false)
const terminationRequestOpen = ref(false)
const activeMembershipTab = ref(0)
const membershipRequestValidationVisible = ref(false)
const membershipRequestLocalErrors = ref({})
const memberCard = ref(null)
const memberCardLoading = ref(false)
const memberCardError = ref('')
const organization = ref({ departments: [], locations: [], training_groups: [], team_assignments: [], can_manage: false })
const canEditOrganization = computed(() => organization.value.can_edit === true
    || (!Object.hasOwn(organization.value, 'can_edit') && organization.value.can_manage === true))
const canDeleteOrganization = computed(() => organization.value.can_delete === true
    || (!Object.hasOwn(organization.value, 'can_delete') && organization.value.can_manage === true))
const canEditTeamAssignments = computed(() => organization.value.can_edit_team_assignments === true
    || (!Object.hasOwn(organization.value, 'can_edit_team_assignments') && organization.value.can_manage === true))
const canCreateOrganizationItem = (type) => {
    const key = {
        departments: 'can_create_departments',
        locations: 'can_create_locations',
        'training-groups': 'can_create_training_groups',
    }[type]
    return organization.value[key] === true
        || (!Object.hasOwn(organization.value, key) && canEditOrganization.value)
}
const canEditOrganizationItem = (item) => item?.can_edit === true
    || (!Object.hasOwn(item || {}, 'can_edit') && canEditOrganization.value)
const canDeleteOrganizationItem = (item) => item?.can_delete === true
    || (!Object.hasOwn(item || {}, 'can_delete') && canDeleteOrganization.value)
const editableOrganizationDepartments = computed(() => organization.value.departments.filter(canEditOrganizationItem))
const assignableOrganizationDepartments = computed(() => organization.value.departments.filter((item) => item.can_assign_teams === true
    || (!Object.hasOwn(item, 'can_assign_teams') && canEditTeamAssignments.value)))
const assignableOrganizationTrainingGroups = computed(() => organization.value.training_groups.filter((item) => item.can_assign_teams === true
    || (!Object.hasOwn(item, 'can_assign_teams') && canEditTeamAssignments.value)))
const editableTeamAssignments = computed(() => organization.value.team_assignments.filter((item) => item.can_edit_assignment === true
    || (!Object.hasOwn(item, 'can_edit_assignment') && canEditTeamAssignments.value)))
const organizationLoading = ref(true)
const organizationSaving = ref(false)
const organizationError = ref('')
const departmentForm = ref({ id: null, name: '', sport_type: '', description: '', is_public: false })
const locationForm = ref({ id: null, name: '', street: '', house_number: '', postal_code: '', city: '', country: 'DE', notes: '', is_public: false })
const trainingGroupForm = ref({ id: null, name: '', club_department_id: null, club_location_id: null, sport_type: '', description: '', is_public: false })
const organizationBaseUrl = `/api/v1/clubs/${encodeURIComponent(props.clubProfile.id)}/organization`
const apiErrorText = (error) => Object.values(error?.response?.data?.errors || {}).flat()[0]
    || error?.response?.data?.message || ot('saveError')
const loadOrganization = async () => {
    organizationLoading.value = true
    organizationError.value = ''
    try {
        const response = await window.axios.get(organizationBaseUrl, { headers: { Accept: 'application/json' } })
        organization.value = response.data.data
    } catch (error) {
        organizationError.value = apiErrorText(error)
    } finally {
        organizationLoading.value = false
    }
}
const resetOrganizationForm = (type) => {
    if (type === 'departments') departmentForm.value = { id: null, name: '', sport_type: '', description: '', is_public: false }
    if (type === 'locations') locationForm.value = { id: null, name: '', street: '', house_number: '', postal_code: '', city: '', country: 'DE', notes: '', is_public: false }
    if (type === 'training-groups') trainingGroupForm.value = { id: null, name: '', club_department_id: null, club_location_id: null, sport_type: '', description: '', is_public: false }
}
const editOrganizationItem = (type, item) => {
    if (type === 'departments') departmentForm.value = { ...item }
    if (type === 'locations') locationForm.value = { ...item }
    if (type === 'training-groups') trainingGroupForm.value = { ...item }
}
const saveOrganizationItem = async (type, form) => {
    organizationSaving.value = true
    organizationError.value = ''
    try {
        const url = `${organizationBaseUrl}/${type}${form.id ? `/${form.id}` : ''}`
        await window.axios({ method: form.id ? 'put' : 'post', url, data: form, headers: { Accept: 'application/json' } })
        resetOrganizationForm(type)
        await loadOrganization()
    } catch (error) {
        organizationError.value = apiErrorText(error)
    } finally {
        organizationSaving.value = false
    }
}
const deleteOrganizationItem = async (type, item) => {
    const confirmed = await confirmDialog({ title: ot('deleteTitle'), message: ot('deleteMessage'), confirmLabel: ot('delete') })
    if (!confirmed) return
    organizationSaving.value = true
    organizationError.value = ''
    try {
        await window.axios.delete(`${organizationBaseUrl}/${type}/${item.id}`, { headers: { Accept: 'application/json' } })
        await loadOrganization()
    } catch (error) {
        organizationError.value = apiErrorText(error)
    } finally {
        organizationSaving.value = false
    }
}
const selectTrainingGroup = (team) => {
    const group = organization.value.training_groups.find((item) => Number(item.id) === Number(team.club_training_group_id))
    if (!group) return
    if (group.club_department_id) team.club_department_id = group.club_department_id
    if (group.club_location_id) team.club_location_id = group.club_location_id
}
const saveTeamAssignment = async (team) => {
    organizationSaving.value = true
    organizationError.value = ''
    try {
        await window.axios.put(`/api/v1/teams/${encodeURIComponent(team.id)}`, {
            name: team.name,
            sport_type: team.sport_type,
            club_department_id: team.club_department_id || null,
            club_location_id: team.club_location_id || null,
            club_training_group_id: team.club_training_group_id || null,
        }, { headers: { Accept: 'application/json' } })
        await loadOrganization()
    } catch (error) {
        organizationError.value = apiErrorText(error)
    } finally {
        organizationSaving.value = false
    }
}
const teamOrganizationLabels = (team) => [
    organization.value.departments.find((item) => Number(item.id) === Number(team.club_department_id))?.name,
    organization.value.locations.find((item) => Number(item.id) === Number(team.club_location_id))?.name,
    organization.value.training_groups.find((item) => Number(item.id) === Number(team.club_training_group_id))?.name,
].filter(Boolean)
onMounted(loadOrganization)
const imageForm = useForm({
    logo: null,
    cover_image: null,
})
const membershipRequestForm = useForm({
    club_membership_type_id: props.clubProfile.membership_types?.[0]?.id || '',
    application_data: {},
    accepted_documents: {},
    preferred_payment_method: props.clubProfile.membership_payment_methods?.[0] || '',
    requested_billing_interval: '',
    message: '',
})
const membershipResponseForm = useForm({ message: '' })
const pauseForm = useForm({
    requested_pause_from: '',
    requested_pause_until: '',
    message: '',
})
const membershipChangeTypes = computed(() => (props.clubProfile.membership_types || [])
    .filter((type) => Number(type.id) !== Number(props.viewer.membership_type_id)))
const membershipChangeForm = useForm({
    club_membership_type_id: '',
    club_department_id: '',
    message: '',
})
const membershipChangeDepartments = computed(() => organization.value.departments.filter((department) => (
    department.is_public === true && Number(department.id) !== Number(props.viewer.club_department_id)
)))
const currentMembershipDepartment = computed(() => organization.value.departments
    .find((department) => Number(department.id) === Number(props.viewer.club_department_id)))
const today = new Date()
const todayInput = `${today.getFullYear()}-${String(today.getMonth() + 1).padStart(2, '0')}-${String(today.getDate()).padStart(2, '0')}`
const terminationForm = useForm({
    requested_termination_on: todayInput,
    termination_reason: '',
})

const clubForm = useForm({
    name: props.clubProfile.name || '',
    sport_type: props.clubProfile.sport_type || '',
    official_club_number: props.clubProfile.requested_official_club_number || props.clubProfile.official_club_number || '',
    country: props.clubProfile.country || 'DE',
    street: props.clubProfile.street || '',
    house_number: props.clubProfile.house_number || '',
    postal_code: props.clubProfile.postal_code || '',
    city: props.clubProfile.city || '',
    state: props.clubProfile.state || '',
    contact_email: props.clubProfile.contact_email || '',
    contact_phone: props.clubProfile.contact_phone || '',
    website_url: props.clubProfile.website_url || '',
    contact_details_public: props.clubProfile.contact_details_public === true,
    contact_persons: (props.clubProfile.contact_persons || []).map((person) => ({ ...person })),
    brand_primary_color: props.clubProfile.brand_primary_color || '',
    brand_secondary_color: props.clubProfile.brand_secondary_color || '',
    brand_accent_color: props.clubProfile.brand_accent_color || '',
    letterhead_settings: {
        show_logo: props.clubProfile.letterhead_settings?.show_logo !== false,
        header: props.clubProfile.letterhead_settings?.header || '',
        address_line: props.clubProfile.letterhead_settings?.address_line || '',
        footer: props.clubProfile.letterhead_settings?.footer || '',
    },
    document_templates: (props.clubProfile.document_templates || []).map((template) => ({ ...template })),
    registry_authority: props.clubProfile.registry_authority || '',
    registry_number: props.clubProfile.registry_number || '',
    federation_affiliations: (props.clubProfile.federation_affiliations || []).map((affiliation) => ({ ...affiliation })),
    tax_authority: props.clubProfile.tax_authority || '',
    tax_number: props.clubProfile.tax_number || '',
    vat_id: props.clubProfile.vat_id || '',
    tax_status: props.clubProfile.tax_status || 'unknown',
    tax_exemption_valid_until: props.clubProfile.tax_exemption_valid_until || '',
    is_listed: props.clubProfile.is_listed !== false,
    teams_are_listed: props.clubProfile.teams_are_listed !== false,
    members_can_post_to_club: props.clubProfile.members_can_post_to_club !== false,
    members_can_post_to_teams: props.clubProfile.members_can_post_to_teams !== false,
})

const canEditClubProfile = computed(() => props.viewer.can_edit_club_profile === true)
const canEditClubLegal = computed(() => props.viewer.can_edit_club_legal === true)
const canEditClubContact = computed(() => props.viewer.can_edit_club_contact === true)
const canEditClubBranding = computed(() => props.viewer.can_edit_club_branding === true)
const canEditAnyClubData = computed(() => canEditClubProfile.value || canEditClubLegal.value || canEditClubContact.value || canEditClubBranding.value)
const canManageClubRoles = computed(() => props.viewer.can_manage_roles === true)

const fieldsFor = (names) => Object.fromEntries(names.map((name) => [name, clubForm[name]]))

const addFederationAffiliation = () => clubForm.federation_affiliations.push({
    name: '',
    member_number: '',
    valid_from: '',
    valid_until: '',
})
const removeFederationAffiliation = (index) => clubForm.federation_affiliations.splice(index, 1)
const addContactPerson = () => clubForm.contact_persons.push({ name: '', role: '', email: '', phone: '', is_public: false })
const removeContactPerson = (index) => clubForm.contact_persons.splice(index, 1)
const addDocumentTemplate = () => clubForm.document_templates.push({ name: '', type: 'letter', header: '', footer: '', is_default: false })
const removeDocumentTemplate = (index) => clubForm.document_templates.splice(index, 1)
const setDefaultDocumentTemplate = (index, checked) => {
    const selected = clubForm.document_templates[index]
    if (checked) {
        clubForm.document_templates.forEach((template, templateIndex) => {
            if (templateIndex !== index && template.type === selected.type) template.is_default = false
        })
    }
    selected.is_default = checked
}

const uploadImage = (field, event) => {
    if (!canEditClubBranding.value) return
    const file = event.target.files?.[0] || null

    if (!file) {
        return
    }

    imageForm.logo = field === 'logo' ? file : null
    imageForm.cover_image = field === 'cover_image' ? file : null
    imageForm.post(route('auth.clubs.images.update', props.clubProfile.id), {
        forceFormData: true,
        preserveScroll: true,
        onFinish: () => {
            imageForm.reset()
            if (logoInput.value) logoInput.value.value = null
            if (coverInput.value) coverInput.value.value = null
        },
    })
}

const updateMemberRole = (member) => {
    router.put(route('auth.clubs.members.update', [props.clubProfile.id, member.id]), {
        role: member.pivot.role,
        roles: memberRoles(member),
    }, {
        preserveScroll: true,
    })
}

const updateClubProfile = () => {
    const payload = {
        ...(canEditClubProfile.value ? fieldsFor([
            'name', 'sport_type', 'country', 'street', 'house_number', 'postal_code', 'city', 'state',
            'is_listed', 'teams_are_listed', 'members_can_post_to_club', 'members_can_post_to_teams',
        ]) : {}),
        ...(canEditClubLegal.value ? fieldsFor([
            'official_club_number', 'registry_authority', 'registry_number', 'federation_affiliations',
            'tax_authority', 'tax_number', 'vat_id', 'tax_status', 'tax_exemption_valid_until',
        ]) : {}),
        ...(canEditClubContact.value ? fieldsFor([
            'contact_email', 'contact_phone', 'website_url', 'contact_details_public', 'contact_persons',
        ]) : {}),
        ...(canEditClubBranding.value ? fieldsFor([
            'brand_primary_color', 'brand_secondary_color', 'brand_accent_color', 'letterhead_settings', 'document_templates',
        ]) : {}),
    }

    clubForm.transform(() => payload).put(route('auth.clubs.update', props.clubProfile.id), {
        preserveScroll: true,
    })
}

const openMembershipRequest = () => {
    activeMembershipTab.value = 0
    membershipRequestValidationVisible.value = false
    membershipRequestLocalErrors.value = {}
    membershipRequestForm.club_membership_type_id = props.clubProfile.membership_types?.[0]?.id || ''
    const selectedType = props.clubProfile.membership_types?.find((type) => String(type.id) === String(membershipRequestForm.club_membership_type_id))
    const fieldOverrides = selectedType?.application_fields || {}
    membershipRequestForm.application_data = Object.fromEntries(
        (props.clubProfile.membership_application_fields || []).map((field) => ({ ...field, mode: fieldOverrides[field.key] || field.mode }))
            .filter((field) => field.mode !== 'off')
            .map((field) => [field.key, props.viewer.application_prefill?.[field.key] || (field.type === 'checkbox' ? false : '')])
    )
    membershipRequestForm.accepted_documents = Object.fromEntries(
        (props.clubProfile.membership_application_documents || [])
            .map((document) => [document.id, false])
    )
    membershipRequestForm.preferred_payment_method = props.clubProfile.membership_payment_methods?.[0] || ''
    membershipRequestForm.requested_billing_interval = props.clubProfile.membership_types?.[0]?.billing_interval || ''
    membershipRequestForm.message = ''
    membershipRequestOpen.value = true
}

const submitMembershipRequest = () => {
    if (!validateMembershipRequest()) return

    membershipRequestForm.post(route('auth.club-membership-requests.store', props.clubProfile.id), {
        preserveScroll: true,
        onSuccess: () => {
            membershipRequestOpen.value = false
            membershipRequestForm.reset()
        },
    })
}

const withdrawMembershipRequest = async () => {
    const confirmed = await confirmDialog({
        title: tAuto('Anfrage zurückziehen'),
        message: t('clubs_profile.messages.withdraw_request', { name: props.clubProfile.name }),
        confirmLabel: tAuto('Zurückziehen'),
        danger: true,
    })

    if (!confirmed) return

    router.delete(route('auth.club-membership-requests.destroy', props.clubProfile.id), {
        preserveScroll: true,
    })
}

const respondToMembershipInformation = () => {
    if (!props.viewer.membership_request?.id) return
    membershipResponseForm.post(route('auth.club-membership-requests.respond', props.viewer.membership_request.id), {
        preserveScroll: true,
        onSuccess: () => membershipResponseForm.reset(),
    })
}

const requestPause = () => {
    pauseForm.post(route('auth.club-membership-pause-requests.store', props.clubProfile.id), {
        preserveScroll: true,
        onSuccess: () => pauseForm.reset(),
    })
}

const requestMembershipChange = () => {
    membershipChangeForm.post(route('auth.club-membership-change-requests.store', props.clubProfile.id), {
        preserveScroll: true,
        onSuccess: () => membershipChangeForm.reset('message'),
    })
}

const leaveClub = () => {
    terminationRequestOpen.value = true
}
const submitTerminationRequest = () => {
    terminationForm.post(route('auth.club-membership-termination-requests.store', props.clubProfile.id), {
        preserveScroll: true,
        onSuccess: () => {
            terminationRequestOpen.value = false
        },
    })
}

const followClubOwner = () => {
    if (!clubProfile.owner_id || viewer.social?.is_blocked) return

    if (viewer.social?.is_following) {
        router.delete(route('auth.users.unfollow', clubProfile.owner_id), { preserveScroll: true })
    } else {
        router.post(route('auth.users.follow', clubProfile.owner_id), {}, { preserveScroll: true })
    }
}

const messageClubOwner = () => {
    if (!clubProfile.owner_id || !viewer.social?.can_send_message) return

    router.post(route('auth.conversations.store'), {
        type: 'direct',
        participant_ids: [clubProfile.owner_id],
        club_id: clubProfile.id,
    })
}

const toggleClubOwnerBlock = async () => {
    if (!clubProfile.owner_id) return

    const blocked = viewer.social?.has_blocked
    const confirmed = blocked || await confirmDialog({
        title: tAuto('Verein blockieren'),
        message: tAuto('Möchtest du diesen Verein blockieren?'),
        confirmLabel: tAuto('Blockieren'),
        danger: true,
    })

    if (!confirmed) return

    if (blocked) {
        router.delete(route('auth.users.unblock', clubProfile.owner_id), { preserveScroll: true })
    } else {
        router.post(route('auth.users.block', clubProfile.owner_id), {}, { preserveScroll: true })
    }
}

const loadMemberCard = async (rotate = false) => {
    if (memberCardLoading.value) return
    memberCardLoading.value = true
    memberCardError.value = ''

    try {
        const request = rotate
            ? window.axios.post(route('api.v1.clubs.member-card.rotate', props.clubProfile.id), {}, { headers: { Accept: 'application/json' } })
            : window.axios.get(route('api.v1.clubs.member-card.show', props.clubProfile.id), { headers: { Accept: 'application/json' } })
        const response = await request
        memberCard.value = response.data?.data || null
    } catch (error) {
        memberCardError.value = error.response?.data?.message || tAuto('Die Mitgliedskarte konnte nicht geladen werden.')
    } finally {
        memberCardLoading.value = false
    }
}

const intervalLabel = (interval) => ({
    monthly: tAuto('Monat'),
    quarterly: tAuto('Quartal'),
    four_monthly: tAuto('4 Monate'),
    semi_yearly: tAuto('6 Monate'),
    yearly: tAuto('Jahr'),
    once: tAuto('einmalig'),
    none: tAuto('kein Beitrag'),
}[interval] || interval)

const paymentMethodLabel = (value) => props.clubProfile.membership_payment_method_options?.find((method) => method.value === value)?.label || value

const visibleMembershipDocuments = computed(() => {
    const selectedTypeId = membershipRequestForm.club_membership_type_id
    return (props.clubProfile.membership_application_documents || []).filter((document) => {
        return !document.membership_type_id || String(document.membership_type_id) === String(selectedTypeId)
    })
})

const membershipRequestTabs = computed(() => {
    const english = locale.value !== 'de'

    return [
        { key: 'membership', label: english ? 'Membership' : 'Mitgliedschaft' },
        { key: 'personal', label: english ? 'Personal details' : 'Personendaten' },
        { key: 'contact', label: english ? 'Contact' : 'Kontaktdaten' },
        { key: 'address', label: english ? 'Address' : 'Wohndaten' },
        { key: 'additional', label: english ? 'Additional details' : 'Weitere Angaben' },
        { key: 'payment', label: english ? 'Payment' : 'Zahlung' },
        { key: 'documents', label: english ? 'Documents & finish' : 'Dokumente & Abschluss' },
    ]
})

const applicationFieldSections = computed(() => {
    const sections = []

    const selectedType = props.clubProfile.membership_types?.find((type) => String(type.id) === String(membershipRequestForm.club_membership_type_id))
    const fieldOverrides = selectedType?.application_fields || {}

    ;(props.clubProfile.membership_application_fields || []).map((field) => ({ ...field, mode: fieldOverrides[field.key] || field.mode }))
        .filter((field) => field.mode !== 'off')
        .forEach((field) => {
            let section = sections.find((candidate) => candidate.name === field.section)

            if (!section) {
                section = { name: field.section, fields: [] }
                sections.push(section)
            }

            section.fields.push(field)
        })

    return sections
})

const applicationSectionsForTab = (tabKey) => {
    const tabFieldKeys = {
        personal: ['first_name', 'last_name', 'birth_date', 'gender', 'nationality', 'athlete_license_number'],
        contact: ['email', 'phone'],
        address: ['country', 'street', 'house_number', 'postal_code', 'city', 'state'],
        additional: ['guardian_name', 'guardian_email', 'guardian_phone', 'emergency_contact_name', 'emergency_contact_phone'],
    }
    const fieldKeys = [...(tabFieldKeys[tabKey] || [])]

    if (tabKey === 'additional') {
        const knownKeys = new Set(Object.values(tabFieldKeys).flat())
        applicationFieldSections.value.forEach((section) => section.fields.forEach((field) => {
            if (!knownKeys.has(field.key)) fieldKeys.push(field.key)
        }))
    }

    return applicationFieldSections.value
        .map((section) => ({
            ...section,
            fields: section.fields.filter((field) => fieldKeys.includes(field.key)),
        }))
        .filter((section) => section.fields.length)
}

const valueIsMissing = (field, value) => {
    if (field.type === 'checkbox') return value !== true
    return value === null || value === undefined || String(value).trim() === ''
}

const missingMembershipRequestKeys = () => {
    const missing = []

    if (!props.clubProfile.membership_types?.length || !membershipRequestForm.club_membership_type_id) {
        missing.push('membership_type')
    }

    membershipRequestTabs.value.forEach((tab, index) => {
        if (index < 1 || index > 4) return
        applicationSectionsForTab(tab.key).forEach((section) => section.fields.forEach((field) => {
            if (field.mode === 'required' && valueIsMissing(field, membershipRequestForm.application_data[field.key])) {
                missing.push(`application_data.${field.key}`)
            }
        }))
    })

    visibleMembershipDocuments.value.forEach((document) => {
        if (document.is_required && membershipRequestForm.accepted_documents[document.id] !== true) {
            missing.push(`accepted_documents.${document.id}`)
        }
    })

    return missing
}

const validateMembershipRequest = () => {
    membershipRequestValidationVisible.value = true
    const missing = missingMembershipRequestKeys()
    membershipRequestLocalErrors.value = Object.fromEntries(missing.map((key) => [key, true]))

    if (missing.length) {
        const firstMissing = missing[0]
        if (firstMissing === 'membership_type') {
            activeMembershipTab.value = 0
        } else if (firstMissing.startsWith('application_data.')) {
            const fieldKey = firstMissing.replace('application_data.', '')
            const tabIndex = membershipRequestTabs.value.findIndex((tab) => applicationSectionsForTab(tab.key).some((section) => section.fields.some((field) => field.key === fieldKey)))
            if (tabIndex >= 0) activeMembershipTab.value = tabIndex
        } else if (firstMissing.startsWith('accepted_documents.')) {
            activeMembershipTab.value = membershipRequestTabs.value.findIndex((tab) => tab.key === 'documents')
        }
        return false
    }

    return true
}

const membershipRequestFieldHasError = (fieldKey) => membershipRequestValidationVisible.value
    && valueIsMissing({ type: applicationFieldSections.value.flatMap((section) => section.fields).find((field) => field.key === fieldKey)?.type }, membershipRequestForm.application_data[fieldKey])
    && Boolean(membershipRequestLocalErrors.value[`application_data.${fieldKey}`])

const membershipRequestTabHasErrors = (index) => {
    if (!membershipRequestValidationVisible.value) return false
    const tab = membershipRequestTabs.value[index]
    if (!tab) return false
    if (index === 0) return Boolean(membershipRequestLocalErrors.value.membership_type) && (!props.clubProfile.membership_types?.length || !membershipRequestForm.club_membership_type_id)
    if (tab.key === 'documents') return visibleMembershipDocuments.value.some((document) => document.is_required && membershipRequestForm.accepted_documents[document.id] !== true)
    return applicationSectionsForTab(tab.key).some((section) => section.fields.some((field) => membershipRequestFieldHasError(field.key)))
}

const goToMembershipRequestNext = () => {
    membershipRequestValidationVisible.value = true
    const missingBeforeTab = missingMembershipRequestKeys()
    const tab = membershipRequestTabs.value[activeMembershipTab.value]
    const hasCurrentTabError = tab?.key === 'membership'
        ? missingBeforeTab.includes('membership_type')
        : tab?.key === 'documents'
            ? missingBeforeTab.some((key) => key.startsWith('accepted_documents.'))
            : applicationSectionsForTab(tab?.key).some((section) => section.fields.some((field) => missingBeforeTab.includes(`application_data.${field.key}`)))

    membershipRequestLocalErrors.value = Object.fromEntries(missingBeforeTab.map((key) => [key, true]))
    if (hasCurrentTabError) return
    if (activeMembershipTab.value < membershipRequestTabs.value.length - 1) activeMembershipTab.value += 1
}

const formatMoney = (value) => new Intl.NumberFormat(localeCode.value, {
    style: 'currency',
    currency: 'EUR',
}).format(Number(value || 0))
</script>

<template>
    <AppLayout :title="clubProfile.name">

        <Head :title="clubProfile.name" />

        <div class="mx-auto max-w-5xl space-y-6">
            <ClubDeletionPanel v-if="viewer.can_delete_club" :club-id="clubProfile.id" :club-name="clubProfile.name" />
            <ClubWorkspaceNav
                active="structure"
                :description="tAuto('Vereinsprofil, Teams, Rollen und sichtbare Vereinsbeiträge.')"
            />

            <section class="overflow-hidden rounded-lg border border-border bg-card">
                <div class="relative h-40 bg-gradient-to-r from-buttonPrimary to-borderHover">
                    <img v-if="clubProfile.cover_image" :src="storageUrl(clubProfile.cover_image)"
                        :alt="clubProfile.name" width="1200" height="320" loading="eager" decoding="async" fetchpriority="high" class="h-full w-full object-cover" />
                    <button v-if="canEditClubBranding" type="button"
                        class="absolute bottom-3 right-3 rounded-lg bg-card/90 px-3 py-2 text-sm font-semibold text-primary shadow hover:bg-card"
                        @click="coverInput?.click()">
                        <i class="las la-camera"></i> {{ tAuto('Titelbild') }}
                    </button>
                    <input ref="coverInput" type="file" accept="image/*" class="hidden"
                        @change="uploadImage('cover_image', $event)" />
                </div>
                <div class="relative z-10 px-5 pb-5 pt-4">
                    <div class="flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between">
                        <div class="-mt-12 flex shrink-0 items-center gap-4">
                            <div
                                class="group relative flex h-24 w-24 items-center justify-center overflow-hidden rounded-lg border-4 border-card bg-inputBg text-3xl font-bold text-primary">
                                <img v-if="clubProfile.logo" :src="storageUrl(clubProfile.logo)" :alt="clubProfile.name"
                                    width="96" height="96" loading="eager" decoding="async" class="h-full w-full object-cover" />
                                <span v-else>{{ initials(clubProfile.name) }}</span>
                                <button v-if="canEditClubBranding" type="button"
                                    class="absolute inset-0 flex items-center justify-center bg-black/50 text-sm font-semibold text-white opacity-0 transition group-hover:opacity-100"
                                    @click="logoInput?.click()">
                                    <i class="las la-camera text-xl"></i>
                                </button>
                                <input ref="logoInput" type="file" accept="image/*" class="hidden"
                                    @change="uploadImage('logo', $event)" />
                            </div>
                            <div class="shrink-0">
                                <h1 class="whitespace-nowrap text-2xl font-bold text-primary">{{ clubProfile.name }}</h1>
                                <p class="text-sm text-secondary">{{ tAuto('Verein') }} · {{ viewer.is_member ? tAuto('Mitglied') : tAuto('Profil') }}</p>
                            </div>
                        </div>

                        <div class="flex min-w-0 flex-wrap gap-2 sm:justify-end">
                            <button
                                v-if="clubProfile.owner_id && viewer.social?.can_send_message"
                                type="button"
                                class="rounded-lg bg-buttonPrimary px-4 py-2 text-sm font-semibold text-buttonTextPrimary hover:bg-buttonPrimaryHover"
                                @click="messageClubOwner"
                            >
                                <i class="las la-comment"></i> {{ tAuto('Nachricht') }}
                            </button>
                            <button
                                v-if="clubProfile.owner_id && viewer.social?.can_follow"
                                type="button"
                                class="rounded-lg border border-border px-4 py-2 text-sm font-semibold text-primary hover:bg-inputBg"
                                @click="followClubOwner"
                            >
                                <i :class="viewer.social.is_following ? 'las la-user-minus' : 'las la-plus'"></i>
                                {{ viewer.social.is_following ? tAuto('Entfolgen') : tAuto('Folgen') }}
                            </button>
                            <button
                                v-if="clubProfile.owner_id && (viewer.social?.has_blocked || !viewer.social?.is_blocked)"
                                type="button"
                                class="rounded-lg border border-error/40 px-4 py-2 text-sm font-semibold text-error hover:bg-error/10"
                                @click="toggleClubOwnerBlock"
                            >
                                <i :class="viewer.social?.has_blocked ? 'las la-unlock' : 'las la-ban'"></i>
                                {{ viewer.social?.has_blocked ? tAuto('Entblockieren') : tAuto('Blockieren') }}
                            </button>
                            <button
                                v-if="!viewer.is_member && !viewer.can_manage && clubProfile.membership_requests_enabled && !viewer.has_pending_membership_request"
                                type="button"
                                class="rounded-lg bg-buttonPrimary px-4 py-2 text-sm font-semibold text-buttonTextPrimary hover:bg-buttonPrimaryHover"
                                @click="openMembershipRequest"
                            >
                                {{ tAuto('Mitgliedschaft anfragen') }}
                            </button>
                            <span
                                v-if="!viewer.is_member && viewer.has_pending_membership_request"
                                class="rounded-lg border border-success/40 px-4 py-2 text-sm font-semibold text-success"
                            >
                                {{ viewer.membership_request?.status === 'waitlisted' ? tAuto('Auf Warteliste') : tAuto('Anfrage gesendet') }}
                            </span>
                            <form
                                v-if="!viewer.is_member && viewer.membership_request?.status === 'information_requested'"
                                class="w-full rounded-lg border border-warning/40 bg-warning/10 p-3"
                                @submit.prevent="respondToMembershipInformation"
                            >
                                <p class="text-sm font-semibold text-primary">{{ tAuto('Ergänzende Angaben erforderlich') }}</p>
                                <p class="mt-1 text-sm text-secondary">{{ viewer.membership_request.information_request_message }}</p>
                                <textarea v-model.trim="membershipResponseForm.message" required maxlength="2000" rows="3" class="mt-3 w-full rounded-lg border border-border bg-inputBg px-3 py-2 text-sm text-primary" :placeholder="tAuto('Antwort an den Verein')"></textarea>
                                <p v-if="membershipResponseForm.errors.message" class="mt-1 text-sm text-error">{{ membershipResponseForm.errors.message }}</p>
                                <button type="submit" class="mt-2 rounded-lg bg-buttonPrimary px-4 py-2 text-sm font-semibold text-buttonTextPrimary disabled:opacity-60" :disabled="membershipResponseForm.processing">
                                    {{ membershipResponseForm.processing ? tAuto('Wird gespeichert …') : tAuto('Antwort senden') }}
                                </button>
                            </form>
                            <button
                                v-if="!viewer.is_member && viewer.has_pending_membership_request"
                                type="button"
                                class="rounded-lg border border-error/40 px-4 py-2 text-sm font-semibold text-error hover:bg-error/10"
                                @click="withdrawMembershipRequest"
                            >
                                {{ tAuto('Anfrage zurückziehen') }}
                            </button>
                            <Link :href="route('auth.teams.index')"
                                class="rounded-lg border border-border px-4 py-2 text-sm text-primary hover:bg-inputBg">
                                {{ tAuto('Teams ansehen') }}
                            </Link>
                            <button
                                v-if="viewer.is_member && !viewer.can_manage && !viewer.has_pending_termination_request"
                                type="button"
                                class="rounded-lg border border-error/40 px-4 py-2 text-sm font-semibold text-error hover:bg-error/10"
                                @click="leaveClub"
                            >
                                {{ tAuto('Verein verlassen') }}
                            </button>
                            <span
                                v-if="viewer.is_member && !viewer.can_manage && viewer.has_pending_termination_request"
                                class="rounded-lg border border-warning/40 bg-warning/10 px-4 py-2 text-sm font-semibold text-warning"
                            >
                                {{ tAuto('Wartet auf Prüfung') }}<template v-if="viewer.requested_termination_on"> · {{ formatDate(viewer.requested_termination_on) }}</template>
                            </span>
                        </div>
                    </div>
                </div>
            </section>

            <section class="grid gap-4 sm:grid-cols-3">
                <div class="rounded-lg border border-border bg-card p-4">
                    <div class="text-2xl font-semibold text-primary">{{ clubProfile.users_count }}</div>
                    <div class="text-sm text-secondary">{{ tAuto('Mitglieder') }}</div>
                </div>
                <div class="rounded-lg border border-border bg-card p-4">
                    <div class="text-2xl font-semibold text-primary">{{ clubProfile.teams_count }}</div>
                    <div class="text-sm text-secondary">{{ tAuto('Teams') }}</div>
                </div>
                <div class="rounded-lg border border-border bg-card p-4">
                    <div class="text-2xl font-semibold text-primary">{{ clubProfile.posts_count }}</div>
                    <div class="text-sm text-secondary">{{ tAuto('Beiträge') }}</div>
                </div>
            </section>

            <section v-if="viewer.is_member || viewer.can_manage" class="overflow-hidden rounded-xl border border-border bg-card">
                <div class="flex flex-col gap-3 border-b border-border p-5 sm:flex-row sm:items-center sm:justify-between">
                    <div>
                        <h2 class="text-lg font-semibold text-primary">{{ tAuto('Digitale Mitgliedskarte') }}</h2>
                        <p class="mt-1 text-sm text-secondary">{{ tAuto('Zeige den kurzlebigen QR-Code beim Training oder Vereinsevent vor.') }}</p>
                    </div>
                    <button
                        type="button"
                        class="self-start rounded-lg bg-buttonPrimary px-4 py-2 text-sm font-semibold text-buttonTextPrimary disabled:cursor-not-allowed disabled:opacity-60"
                        :disabled="memberCardLoading"
                        @click="loadMemberCard(Boolean(memberCard))"
                    >
                        {{ memberCardLoading
                            ? tAuto('Karte wird geladen …')
                            : memberCard
                                ? tAuto('Neuen QR-Code erzeugen')
                                : tAuto('Mitgliedskarte anzeigen') }}
                    </button>
                </div>

                <p v-if="memberCardError" class="m-5 rounded-lg border border-error/30 bg-error/10 px-4 py-3 text-sm font-semibold text-error" role="alert">
                    {{ memberCardError }}
                </p>

                <div v-if="memberCard" class="grid gap-6 p-5 md:grid-cols-[minmax(0,1fr)_18rem] md:items-center" aria-live="polite">
                    <div class="rounded-2xl bg-gradient-to-br from-air-blue to-air-green p-6 text-white shadow-lg">
                        <div class="flex items-start justify-between gap-4">
                            <div>
                                <p class="text-xs font-semibold uppercase tracking-widest text-white/75">Airmius</p>
                                <p class="mt-1 text-xl font-bold">{{ memberCard.club.name }}</p>
                            </div>
                            <i class="las la-id-card text-4xl text-white/80" aria-hidden="true"></i>
                        </div>
                        <div class="mt-10">
                            <p class="text-xs uppercase tracking-wide text-white/75">{{ tAuto('Mitglied') }}</p>
                            <p class="mt-1 text-2xl font-bold">{{ memberCard.member.name }}</p>
                            <div class="mt-4 flex flex-wrap gap-2 text-xs font-semibold">
                                <span class="rounded-full bg-white/20 px-3 py-1">{{ clubRoleLabel(memberCard.member.role) }}</span>
                                <span class="rounded-full bg-white/20 px-3 py-1">{{ tAuto('Aktiv') }}</span>
                            </div>
                        </div>
                    </div>

                    <div class="text-center">
                        <img
                            :src="memberCard.token.qr_svg_data_uri"
                            :alt="tAuto('QR-Code der digitalen Mitgliedskarte')"
                            width="256"
                            height="256"
                            class="mx-auto aspect-square w-full max-w-64 rounded-xl border border-border bg-white p-3"
                        >
                        <p class="mt-3 text-sm font-semibold text-primary">
                            {{ tAuto('Gültig bis') }} {{ formatDateTime(memberCard.token.expires_at) }}
                        </p>
                        <p class="mt-1 text-xs text-secondary">
                            {{ tAuto('Beim Erzeugen eines neuen Codes wird der vorherige sofort ungültig. E-Mail, Adresse, Zahlungs- und Gesundheitsdaten sind nicht enthalten.') }}
                        </p>
                    </div>
                </div>

                <div v-else-if="!memberCardLoading && !memberCardError" class="p-5 text-sm text-secondary">
                    {{ tAuto('Der QR-Code wird erst auf deinen Klick erzeugt und läuft nach zehn Minuten automatisch ab.') }}
                </div>
            </section>

            <section v-if="viewer.is_member && clubProfile.member_pause_requests_enabled && !viewer.can_manage" class="rounded-lg border border-border bg-card p-5">
                <h2 class="text-lg font-semibold text-primary">{{ tAuto('Mitgliedschaft pausieren') }}</h2>
                <p class="mt-1 text-sm text-secondary">
                    {{ tAuto('Dein Verein erlaubt Pausen-Anfragen. Die Pause wird erst nach Freigabe durch die Vereinsverwaltung aktiv.') }}
                </p>
                <form class="mt-4 grid gap-3 md:grid-cols-3" @submit.prevent="requestPause">
                    <input v-model="pauseForm.requested_pause_from" type="date" class="rounded-lg border border-border bg-inputBg px-3 py-2 text-sm text-primary" required>
                    <input v-model="pauseForm.requested_pause_until" type="date" class="rounded-lg border border-border bg-inputBg px-3 py-2 text-sm text-primary">
                    <input v-model="pauseForm.message" class="rounded-lg border border-border bg-inputBg px-3 py-2 text-sm text-primary" :placeholder="tAuto('Grund optional')">
                    <button class="rounded-lg bg-buttonPrimary px-4 py-2 text-sm font-semibold text-buttonTextPrimary" :disabled="pauseForm.processing">
                        {{ tAuto('Pause anfragen') }}
                    </button>
                </form>
            </section>

            <section v-if="viewer.is_member && !viewer.can_manage && (membershipChangeTypes.length || membershipChangeDepartments.length || viewer.has_pending_membership_change_request)" class="rounded-lg border border-border bg-card p-5">
                <h2 class="text-lg font-semibold text-primary">{{ mct('title') }}</h2>
                <p class="mt-1 text-sm text-secondary">{{ mct('hint') }}</p>
                <div class="mt-3 rounded-lg border border-border bg-bg px-3 py-2 text-sm text-secondary">
                    {{ mct('current') }}:
                    <span class="font-semibold text-primary">
                        {{ clubProfile.membership_types?.find((type) => Number(type.id) === Number(viewer.membership_type_id))?.name || mct('unknown') }}
                    </span>
                    <span class="mx-2 text-border">·</span>
                    {{ mct('currentDepartment') }}:
                    <span class="font-semibold text-primary">
                        {{ currentMembershipDepartment?.name || mct('unknownDepartment') }}
                    </span>
                </div>
                <p v-if="viewer.has_pending_membership_change_request" class="mt-4 rounded-lg bg-air-blue/10 px-3 py-2 text-sm font-semibold text-air-blue">
                    {{ mct('pending') }}
                </p>
                <form v-else class="mt-4 grid gap-3 md:grid-cols-2" @submit.prevent="requestMembershipChange">
                    <select v-model="membershipChangeForm.club_membership_type_id" class="rounded-lg border border-border bg-inputBg px-3 py-2 text-sm text-primary" :aria-label="mct('target')">
                        <option value="">{{ mct('noType') }}</option>
                        <option v-for="type in membershipChangeTypes" :key="type.id" :value="type.id">
                            {{ type.name }}<template v-if="type.amount !== null && type.amount !== undefined"> · {{ formatMoney(type.amount) }} / {{ intervalLabel(type.billing_interval) }}</template>
                        </option>
                    </select>
                    <select v-model="membershipChangeForm.club_department_id" class="rounded-lg border border-border bg-inputBg px-3 py-2 text-sm text-primary" :aria-label="mct('department')">
                        <option value="">{{ mct('noDepartment') }}</option>
                        <option v-for="department in membershipChangeDepartments" :key="department.id" :value="department.id">{{ department.name }}</option>
                    </select>
                    <input v-model="membershipChangeForm.message" maxlength="2000" class="rounded-lg border border-border bg-inputBg px-3 py-2 text-sm text-primary" :placeholder="mct('message')">
                    <button class="rounded-lg bg-buttonPrimary px-4 py-2 text-sm font-semibold text-buttonTextPrimary" :disabled="membershipChangeForm.processing || (!membershipChangeForm.club_membership_type_id && !membershipChangeForm.club_department_id)">
                        {{ mct('submit') }}
                    </button>
                    <p v-if="membershipChangeForm.errors.club_membership_type_id" class="text-xs text-error md:col-span-3">{{ membershipChangeForm.errors.club_membership_type_id }}</p>
                    <p v-if="membershipChangeForm.errors.club_department_id" class="text-xs text-error md:col-span-2">{{ membershipChangeForm.errors.club_department_id }}</p>
                </form>
            </section>

            <section v-if="clubProfile.brand_primary_color || clubProfile.brand_secondary_color || clubProfile.brand_accent_color" class="rounded-lg border border-border bg-card p-5">
                <h2 class="text-lg font-semibold text-primary">{{ bt('palette') }}</h2>
                <div class="mt-3 flex flex-wrap gap-3">
                    <div v-for="(color, key) in { primary: clubProfile.brand_primary_color, secondary: clubProfile.brand_secondary_color, accent: clubProfile.brand_accent_color }" v-show="color" :key="key" class="flex items-center gap-2 rounded-lg border border-border bg-bg px-3 py-2 text-sm text-secondary">
                        <span class="h-5 w-5 rounded-full border border-border" :style="{ backgroundColor: color }"></span>
                        <span>{{ bt(key) }} · {{ color }}</span>
                    </div>
                </div>
            </section>

            <section v-if="clubProfile.contact_email || clubProfile.contact_phone || clubProfile.website_url || clubProfile.contact_persons?.length" class="rounded-lg border border-border bg-card p-5">
                <h2 class="text-lg font-semibold text-primary">{{ ct('publicTitle') }}</h2>
                <div class="mt-3 grid gap-3 text-sm md:grid-cols-3">
                    <a v-if="clubProfile.contact_email" :href="`mailto:${clubProfile.contact_email}`" class="text-link hover:underline">{{ clubProfile.contact_email }}</a>
                    <a v-if="clubProfile.contact_phone" :href="`tel:${clubProfile.contact_phone}`" class="text-link hover:underline">{{ clubProfile.contact_phone }}</a>
                    <a v-if="clubProfile.website_url" :href="clubProfile.website_url" target="_blank" rel="noopener noreferrer" class="text-link hover:underline">{{ clubProfile.website_url }}</a>
                </div>
                <div v-if="clubProfile.contact_persons?.length" class="mt-4 grid gap-3 md:grid-cols-2">
                    <article v-for="(person, index) in clubProfile.contact_persons" :key="`${person.name}-${index}`" class="rounded-lg border border-border bg-bg p-3 text-sm">
                        <p class="font-semibold text-primary">{{ person.name }}</p>
                        <p v-if="person.role" class="text-secondary">{{ person.role }}</p>
                        <a v-if="person.email" :href="`mailto:${person.email}`" class="mt-1 block text-link hover:underline">{{ person.email }}</a>
                        <a v-if="person.phone" :href="`tel:${person.phone}`" class="mt-1 block text-link hover:underline">{{ person.phone }}</a>
                    </article>
                </div>
            </section>

            <section class="rounded-lg border border-border bg-card p-5">
                <div class="flex flex-wrap items-start justify-between gap-3">
                    <div>
                        <h2 class="text-lg font-semibold text-primary">{{ ot('title') }}</h2>
                        <p class="mt-1 text-sm text-secondary">{{ ot('hint') }}</p>
                    </div>
                    <button v-if="organizationError" type="button" class="rounded-lg border border-border px-3 py-2 text-xs font-semibold text-primary" @click="loadOrganization">{{ ot('retry') }}</button>
                </div>
                <AppLoadingState v-if="organizationLoading" class="mt-4" :label="ot('loading')" inline />
                <p v-if="organizationError" class="mt-3 rounded-lg border border-danger/30 bg-danger/10 p-3 text-sm text-danger" role="alert">{{ organizationError }}</p>

                <div v-if="!organizationLoading" class="mt-5 grid gap-5 lg:grid-cols-3">
                    <div>
                        <h3 class="font-semibold text-primary">{{ ot('departments') }}</h3>
                        <div class="mt-3 space-y-2">
                            <article v-for="item in organization.departments" :key="item.id" class="rounded-lg border border-border bg-bg p-3 text-sm">
                                <div class="flex items-start justify-between gap-2"><div><p class="font-semibold text-primary">{{ item.name }}</p><p v-if="item.sport_type" class="text-secondary">{{ item.sport_type }}</p></div><span class="text-xs text-secondary">{{ item.is_public ? ot('publicLabel') : ot('internal') }}</span></div>
                                <p v-if="item.description" class="mt-2 whitespace-pre-line text-secondary">{{ item.description }}</p>
                                <div v-if="canEditOrganizationItem(item) || canDeleteOrganizationItem(item)" class="mt-3 flex gap-3"><button v-if="canEditOrganizationItem(item)" type="button" class="text-xs font-semibold text-link" @click="editOrganizationItem('departments', item)">{{ ot('edit') }}</button><button v-if="canDeleteOrganizationItem(item)" type="button" class="text-xs font-semibold text-danger" @click="deleteOrganizationItem('departments', item)">{{ ot('delete') }}</button></div>
                            </article>
                            <p v-if="!organization.departments.length" class="text-sm text-secondary">{{ ot('empty') }}</p>
                        </div>
                        <form v-if="canCreateOrganizationItem('departments') || departmentForm.id" class="mt-4 grid gap-2 rounded-lg border border-border p-3" @submit.prevent="saveOrganizationItem('departments', departmentForm)">
                            <input v-model="departmentForm.name" required maxlength="160" class="rounded-lg border border-border bg-inputBg px-3 py-2 text-sm text-primary" :placeholder="ot('name')" :aria-label="`${ot('department')}: ${ot('name')}`">
                            <input v-model="departmentForm.sport_type" maxlength="120" class="rounded-lg border border-border bg-inputBg px-3 py-2 text-sm text-primary" :placeholder="ot('sport')" :aria-label="`${ot('department')}: ${ot('sport')}`">
                            <textarea v-model="departmentForm.description" maxlength="2000" rows="2" class="rounded-lg border border-border bg-inputBg px-3 py-2 text-sm text-primary" :placeholder="ot('description')" :aria-label="`${ot('department')}: ${ot('description')}`"></textarea>
                            <label class="flex items-center gap-2 text-xs text-secondary"><input v-model="departmentForm.is_public" type="checkbox" class="rounded border-border bg-inputBg">{{ ot('public') }}</label>
                            <div class="flex gap-2"><button class="rounded-lg bg-buttonPrimary px-3 py-2 text-xs font-semibold text-buttonTextPrimary" :disabled="organizationSaving">{{ departmentForm.id ? ot('save') : ot('add') }}</button><button v-if="departmentForm.id" type="button" class="px-3 py-2 text-xs font-semibold text-secondary" @click="resetOrganizationForm('departments')">{{ ot('cancel') }}</button></div>
                        </form>
                    </div>

                    <div>
                        <h3 class="font-semibold text-primary">{{ ot('locations') }}</h3>
                        <div class="mt-3 space-y-2">
                            <article v-for="item in organization.locations" :key="item.id" class="rounded-lg border border-border bg-bg p-3 text-sm">
                                <div class="flex items-start justify-between gap-2"><p class="font-semibold text-primary">{{ item.name }}</p><span class="text-xs text-secondary">{{ item.is_public ? ot('publicLabel') : ot('internal') }}</span></div>
                                <p class="mt-1 text-secondary">{{ [item.street, item.house_number, item.postal_code, item.city].filter(Boolean).join(' ') }}</p>
                                <div v-if="canEditOrganizationItem(item) || canDeleteOrganizationItem(item)" class="mt-3 flex gap-3"><button v-if="canEditOrganizationItem(item)" type="button" class="text-xs font-semibold text-link" @click="editOrganizationItem('locations', item)">{{ ot('edit') }}</button><button v-if="canDeleteOrganizationItem(item)" type="button" class="text-xs font-semibold text-danger" @click="deleteOrganizationItem('locations', item)">{{ ot('delete') }}</button></div>
                            </article>
                            <p v-if="!organization.locations.length" class="text-sm text-secondary">{{ ot('empty') }}</p>
                        </div>
                        <form v-if="canCreateOrganizationItem('locations') || locationForm.id" class="mt-4 grid grid-cols-2 gap-2 rounded-lg border border-border p-3" @submit.prevent="saveOrganizationItem('locations', locationForm)">
                            <input v-model="locationForm.name" required maxlength="160" class="col-span-2 rounded-lg border border-border bg-inputBg px-3 py-2 text-sm text-primary" :placeholder="ot('name')" :aria-label="`${ot('location')}: ${ot('name')}`">
                            <input v-model="locationForm.street" class="rounded-lg border border-border bg-inputBg px-3 py-2 text-sm text-primary" :placeholder="ot('street')" :aria-label="ot('street')"><input v-model="locationForm.house_number" maxlength="40" class="rounded-lg border border-border bg-inputBg px-3 py-2 text-sm text-primary" :placeholder="ot('houseNumber')" :aria-label="ot('houseNumber')">
                            <input v-model="locationForm.postal_code" maxlength="30" class="rounded-lg border border-border bg-inputBg px-3 py-2 text-sm text-primary" :placeholder="ot('postalCode')" :aria-label="ot('postalCode')"><input v-model="locationForm.city" class="rounded-lg border border-border bg-inputBg px-3 py-2 text-sm text-primary" :placeholder="ot('city')" :aria-label="ot('city')">
                            <input v-model="locationForm.country" required maxlength="2" class="rounded-lg border border-border bg-inputBg px-3 py-2 text-sm uppercase text-primary" :placeholder="ot('country')" :aria-label="ot('country')"><input v-model="locationForm.notes" class="rounded-lg border border-border bg-inputBg px-3 py-2 text-sm text-primary" :placeholder="ot('notes')" :aria-label="ot('notes')">
                            <label class="col-span-2 flex items-center gap-2 text-xs text-secondary"><input v-model="locationForm.is_public" type="checkbox" class="rounded border-border bg-inputBg">{{ ot('public') }}</label>
                            <div class="col-span-2 flex gap-2"><button class="rounded-lg bg-buttonPrimary px-3 py-2 text-xs font-semibold text-buttonTextPrimary" :disabled="organizationSaving">{{ locationForm.id ? ot('save') : ot('add') }}</button><button v-if="locationForm.id" type="button" class="px-3 py-2 text-xs font-semibold text-secondary" @click="resetOrganizationForm('locations')">{{ ot('cancel') }}</button></div>
                        </form>
                    </div>

                    <div>
                        <h3 class="font-semibold text-primary">{{ ot('trainingGroups') }}</h3>
                        <div class="mt-3 space-y-2">
                            <article v-for="item in organization.training_groups" :key="item.id" class="rounded-lg border border-border bg-bg p-3 text-sm">
                                <div class="flex items-start justify-between gap-2"><div><p class="font-semibold text-primary">{{ item.name }}</p><p v-if="item.sport_type" class="text-secondary">{{ item.sport_type }}</p></div><span class="text-xs text-secondary">{{ item.is_public ? ot('publicLabel') : ot('internal') }}</span></div>
                                <p class="mt-1 text-xs text-secondary">{{ organization.departments.find((entry) => entry.id === item.club_department_id)?.name || ot('unassigned') }} · {{ organization.locations.find((entry) => entry.id === item.club_location_id)?.name || ot('unassigned') }}</p>
                                <p v-if="item.description" class="mt-2 whitespace-pre-line text-secondary">{{ item.description }}</p>
                                <div v-if="canEditOrganizationItem(item) || canDeleteOrganizationItem(item)" class="mt-3 flex gap-3"><button v-if="canEditOrganizationItem(item)" type="button" class="text-xs font-semibold text-link" @click="editOrganizationItem('training-groups', item)">{{ ot('edit') }}</button><button v-if="canDeleteOrganizationItem(item)" type="button" class="text-xs font-semibold text-danger" @click="deleteOrganizationItem('training-groups', item)">{{ ot('delete') }}</button></div>
                            </article>
                            <p v-if="!organization.training_groups.length" class="text-sm text-secondary">{{ ot('empty') }}</p>
                        </div>
                        <form v-if="canCreateOrganizationItem('training-groups') || trainingGroupForm.id" class="mt-4 grid gap-2 rounded-lg border border-border p-3" @submit.prevent="saveOrganizationItem('training-groups', trainingGroupForm)">
                            <input v-model="trainingGroupForm.name" required maxlength="160" class="rounded-lg border border-border bg-inputBg px-3 py-2 text-sm text-primary" :placeholder="ot('name')" :aria-label="`${ot('trainingGroup')}: ${ot('name')}`">
                            <div class="grid grid-cols-2 gap-2"><select v-model="trainingGroupForm.club_department_id" :aria-label="ot('department')" class="rounded-lg border border-border bg-inputBg px-2 py-2 text-sm text-primary"><option v-if="organization.can_create_departments" :value="null">{{ ot('department') }}: {{ ot('unassigned') }}</option><option v-for="item in editableOrganizationDepartments" :key="item.id" :value="item.id">{{ item.name }}</option></select><select v-model="trainingGroupForm.club_location_id" :aria-label="ot('location')" class="rounded-lg border border-border bg-inputBg px-2 py-2 text-sm text-primary"><option :value="null">{{ ot('location') }}: {{ ot('unassigned') }}</option><option v-for="item in organization.locations" :key="item.id" :value="item.id">{{ item.name }}</option></select></div>
                            <input v-model="trainingGroupForm.sport_type" maxlength="120" class="rounded-lg border border-border bg-inputBg px-3 py-2 text-sm text-primary" :placeholder="ot('sport')" :aria-label="ot('sport')"><textarea v-model="trainingGroupForm.description" maxlength="2000" rows="2" class="rounded-lg border border-border bg-inputBg px-3 py-2 text-sm text-primary" :placeholder="ot('description')" :aria-label="ot('description')"></textarea>
                            <label class="flex items-center gap-2 text-xs text-secondary"><input v-model="trainingGroupForm.is_public" type="checkbox" class="rounded border-border bg-inputBg">{{ ot('public') }}</label>
                            <div class="flex gap-2"><button class="rounded-lg bg-buttonPrimary px-3 py-2 text-xs font-semibold text-buttonTextPrimary" :disabled="organizationSaving">{{ trainingGroupForm.id ? ot('save') : ot('add') }}</button><button v-if="trainingGroupForm.id" type="button" class="px-3 py-2 text-xs font-semibold text-secondary" @click="resetOrganizationForm('training-groups')">{{ ot('cancel') }}</button></div>
                        </form>
                    </div>
                </div>

                <div v-if="editableTeamAssignments.length" class="mt-6 border-t border-border pt-5">
                    <h3 class="font-semibold text-primary">{{ ot('teams') }}</h3>
                    <div class="mt-3 grid gap-3 lg:grid-cols-2">
                        <form v-for="team in editableTeamAssignments" :key="team.id" class="rounded-lg border border-border bg-bg p-3" @submit.prevent="saveTeamAssignment(team)">
                            <p class="font-semibold text-primary">{{ team.name }}</p>
                            <div class="mt-2 grid gap-2 sm:grid-cols-3"><select v-model="team.club_department_id" :aria-label="`${team.name}: ${ot('department')}`" class="rounded-lg border border-border bg-inputBg px-2 py-2 text-xs text-primary"><option v-if="organization.can_assign_teams_globally" :value="null">{{ ot('department') }}: {{ ot('unassigned') }}</option><option v-for="item in assignableOrganizationDepartments" :key="item.id" :value="item.id">{{ item.name }}</option></select><select v-model="team.club_location_id" :aria-label="`${team.name}: ${ot('location')}`" class="rounded-lg border border-border bg-inputBg px-2 py-2 text-xs text-primary"><option :value="null">{{ ot('location') }}: {{ ot('unassigned') }}</option><option v-for="item in organization.locations" :key="item.id" :value="item.id">{{ item.name }}</option></select><select v-model="team.club_training_group_id" :aria-label="`${team.name}: ${ot('trainingGroup')}`" class="rounded-lg border border-border bg-inputBg px-2 py-2 text-xs text-primary" @change="selectTrainingGroup(team)"><option :value="null">{{ ot('trainingGroup') }}: {{ ot('unassigned') }}</option><option v-for="item in assignableOrganizationTrainingGroups" :key="item.id" :value="item.id">{{ item.name }}</option></select></div>
                            <button class="mt-3 rounded-lg bg-buttonPrimary px-3 py-2 text-xs font-semibold text-buttonTextPrimary" :disabled="organizationSaving">{{ ot('assignmentSave') }}</button>
                        </form>
                    </div>
                </div>
            </section>

            <ClubGovernanceSection :club-id="clubProfile.id" />

            <ClubYearPeriodsSection v-if="viewer.is_member || viewer.can_manage" :club-id="clubProfile.id" />

            <ClubPolicyDocumentsSection :club-id="clubProfile.id" />

            <ClubMetadataSection v-if="viewer.can_view_metadata" :club-id="clubProfile.id" />

            <section v-if="canEditAnyClubData" class="rounded-lg border border-border bg-card p-5">
                <h2 class="text-lg font-semibold text-primary">{{ tAuto('Vereinsdaten') }}</h2>
                <p class="mt-1 text-sm text-secondary">
                    {{ tAuto('Offizielle Vereine müssen ihre Vereinsnummer hinterlegen. Nicht-offizielle Gruppen können das Feld leer lassen.') }}
                </p>

                <form class="mt-4 grid gap-4 md:grid-cols-2" @submit.prevent="updateClubProfile">
                    <div class="md:col-span-2 rounded-lg border border-border bg-bg p-4 text-sm">
                        <div class="flex flex-wrap items-center gap-2">
                            <span class="font-semibold text-primary">{{ tAuto('Prüfstatus:') }}</span>
                            <span class="rounded-full bg-inputBg px-2 py-1 text-xs font-semibold text-secondary">
                                {{ clubProfile.verification_status === 'pending_verification' ? tAuto('Wartet auf Prüfung') : clubProfile.verification_status === 'verified' ? tAuto('Freigegeben') : clubProfile.verification_status === 'rejected' ? tAuto('Abgelehnt') : tAuto(clubProfile.verification_status) }}
                            </span>
                            <span v-if="clubProfile.is_official" class="rounded-full bg-success/10 px-2 py-1 text-xs font-semibold text-success">
                                {{ tAuto('Offiziell') }}
                            </span>
                        </div>
                        <p v-if="clubProfile.official_club_number" class="mt-2 text-secondary">
                            {{ tAuto('Vereinsnummer:') }} {{ clubProfile.official_club_number }}
                        </p>
                        <p v-else-if="clubProfile.requested_official_club_number" class="mt-2 text-secondary">
                            {{ tAuto('Beantragte Vereinsnummer:') }} {{ clubProfile.requested_official_club_number }}
                        </p>
                        <p v-if="clubProfile.verification_notes" class="mt-2 text-secondary">
                            {{ tAuto('Hinweis:') }} {{ clubProfile.verification_notes }}
                        </p>
                    </div>

                    <div>
                        <label class="text-sm font-semibold text-primary">{{ tAuto('Vereinsname') }}</label>
                        <input v-model="clubForm.name" :disabled="!canEditClubProfile" class="mt-1 w-full rounded-lg border border-border bg-inputBg px-3 py-2 text-sm text-primary disabled:opacity-60">
                    </div>

                    <div>
                        <label class="text-sm font-semibold text-primary">{{ tAuto('Sportart') }}</label>
                        <input v-model="clubForm.sport_type" :disabled="!canEditClubProfile" class="mt-1 w-full rounded-lg border border-border bg-inputBg px-3 py-2 text-sm text-primary disabled:opacity-60">
                    </div>

                    <label class="flex items-start gap-3 rounded-lg border border-border bg-bg p-3 text-sm text-primary">
                        <input v-model="clubForm.is_listed" :disabled="!canEditClubProfile" type="checkbox" class="mt-1 rounded border-border bg-inputBg">
                        <span>
                            <span class="block font-semibold">{{ tAuto('Verein auflisten') }}</span>
                            <span class="block text-xs text-secondary">{{ tAuto('Der Verein darf in Vereinslisten und Auswahlfeldern sichtbar sein.') }}</span>
                        </span>
                    </label>

                    <label class="flex items-start gap-3 rounded-lg border border-border bg-bg p-3 text-sm text-primary">
                        <input v-model="clubForm.teams_are_listed" :disabled="!canEditClubProfile" type="checkbox" class="mt-1 rounded border-border bg-inputBg">
                        <span>
                            <span class="block font-semibold">{{ tAuto('Teams auflisten') }}</span>
                            <span class="block text-xs text-secondary">{{ tAuto('Teams dürfen außerhalb des internen Vereinsbereichs sichtbar sein.') }}</span>
                        </span>
                    </label>

                    <label class="flex items-start gap-3 rounded-lg border border-border bg-bg p-3 text-sm text-primary">
                        <input v-model="clubForm.members_can_post_to_club" :disabled="!canEditClubProfile" type="checkbox" class="mt-1 rounded border-border bg-inputBg">
                        <span>
                            <span class="block font-semibold">{{ tAuto('Vereinsbeiträge erlauben') }}</span>
                            <span class="block text-xs text-secondary">{{ tAuto('Normale Mitglieder dürfen Beiträge für den Verein erstellen.') }}</span>
                        </span>
                    </label>

                    <label class="flex items-start gap-3 rounded-lg border border-border bg-bg p-3 text-sm text-primary">
                        <input v-model="clubForm.members_can_post_to_teams" :disabled="!canEditClubProfile" type="checkbox" class="mt-1 rounded border-border bg-inputBg">
                        <span>
                            <span class="block font-semibold">{{ tAuto('Teambeiträge erlauben') }}</span>
                            <span class="block text-xs text-secondary">{{ tAuto('Normale Teammitglieder dürfen Beiträge für ihre Teams erstellen.') }}</span>
                        </span>
                    </label>

                    <label v-if="false" class="flex items-start gap-3 rounded-lg border border-border bg-bg p-3 text-sm text-primary md:col-span-2">
                        <input v-model="clubForm.is_official" type="checkbox" class="mt-1 rounded border-border bg-inputBg">
                        <span>
                            <span class="block font-semibold">{{ tAuto('Offizieller Verein') }}</span>
                            <span class="block text-secondary">{{ tAuto('Aktivieren, wenn der Verein offiziell registriert oder einem Verband zugeordnet ist.') }}</span>
                        </span>
                    </label>

                    <div class="md:col-span-2">
                        <label class="text-sm font-semibold text-primary">{{ tAuto('Vereinsnummer zur Prüfung') }}</label>
                        <input
                            v-model="clubForm.official_club_number"
                            :disabled="!canEditClubLegal"
                            class="mt-1 w-full rounded-lg border border-border bg-inputBg px-3 py-2 text-sm text-primary"
                            :placeholder="tAuto('z. B. Vereinsregister- oder Verbandsnummer')"
                        >
                        <p class="mt-1 text-xs text-secondary">
                            {{ tAuto('Wenn die Nummer neu oder geändert ist, wird sie zur Admin-Prüfung vorgemerkt.') }}
                        </p>
                    </div>

                    <div>
                        <label class="text-sm font-semibold text-primary">{{ tAuto('Land') }}</label>
                        <input v-model="clubForm.country" :disabled="!canEditClubProfile" maxlength="2" class="mt-1 w-full rounded-lg border border-border bg-inputBg px-3 py-2 text-sm uppercase text-primary disabled:opacity-60">
                    </div>

                    <div>
                        <label class="text-sm font-semibold text-primary">{{ tAuto('Stadt') }}</label>
                        <input v-model="clubForm.city" :disabled="!canEditClubProfile" class="mt-1 w-full rounded-lg border border-border bg-inputBg px-3 py-2 text-sm text-primary disabled:opacity-60">
                    </div>

                    <div>
                        <label class="text-sm font-semibold text-primary">{{ tAuto('PLZ') }}</label>
                        <input v-model="clubForm.postal_code" :disabled="!canEditClubProfile" class="mt-1 w-full rounded-lg border border-border bg-inputBg px-3 py-2 text-sm text-primary disabled:opacity-60">
                    </div>

                    <div>
                        <label class="text-sm font-semibold text-primary">{{ tAuto('Region') }}</label>
                        <input v-model="clubForm.state" :disabled="!canEditClubProfile" class="mt-1 w-full rounded-lg border border-border bg-inputBg px-3 py-2 text-sm text-primary disabled:opacity-60">
                    </div>

                    <div>
                        <label class="text-sm font-semibold text-primary">{{ tAuto('Straße') }}</label>
                        <input v-model="clubForm.street" :disabled="!canEditClubProfile" class="mt-1 w-full rounded-lg border border-border bg-inputBg px-3 py-2 text-sm text-primary disabled:opacity-60">
                    </div>

                    <div>
                        <label class="text-sm font-semibold text-primary">{{ tAuto('Hausnummer') }}</label>
                        <input v-model="clubForm.house_number" :disabled="!canEditClubProfile" class="mt-1 w-full rounded-lg border border-border bg-inputBg px-3 py-2 text-sm text-primary disabled:opacity-60">
                    </div>

                    <template v-if="canEditClubBranding">
                    <div class="md:col-span-2 mt-2 border-t border-border pt-5">
                        <h3 class="font-semibold text-primary">{{ bt('title') }}</h3>
                        <p class="mt-1 text-xs text-secondary">{{ bt('hint') }}</p>
                    </div>
                    <div v-for="field in ['primary', 'secondary', 'accent']" :key="field">
                        <label class="text-sm font-semibold text-primary">{{ bt(field) }}</label>
                        <div class="mt-1 flex items-center gap-2">
                            <input v-model="clubForm[`brand_${field}_color`]" type="color" class="h-10 w-14 rounded border border-border bg-inputBg p-1">
                            <input v-model="clubForm[`brand_${field}_color`]" maxlength="7" pattern="#[0-9A-Fa-f]{6}" class="min-w-0 flex-1 rounded-lg border border-border bg-inputBg px-3 py-2 text-sm uppercase text-primary" :placeholder="'#1D4ED8'">
                            <button type="button" class="text-xs font-semibold text-secondary" @click="clubForm[`brand_${field}_color`] = ''">{{ bt('clear') }}</button>
                        </div>
                    </div>
                    <div class="md:col-span-2 rounded-lg border border-border bg-bg p-4">
                        <h3 class="text-sm font-semibold text-primary">{{ bt('letterhead') }}</h3>
                        <label class="mt-3 flex items-center gap-2 text-sm text-primary"><input v-model="clubForm.letterhead_settings.show_logo" type="checkbox" class="rounded border-border bg-inputBg">{{ bt('showLogo') }}</label>
                        <div class="mt-3 grid gap-3 md:grid-cols-2">
                            <input v-model="clubForm.letterhead_settings.header" maxlength="300" class="rounded-lg border border-border bg-inputBg px-3 py-2 text-sm text-primary" :placeholder="bt('header')">
                            <input v-model="clubForm.letterhead_settings.address_line" maxlength="300" class="rounded-lg border border-border bg-inputBg px-3 py-2 text-sm text-primary" :placeholder="bt('addressLine')">
                            <textarea v-model="clubForm.letterhead_settings.footer" maxlength="500" rows="2" class="rounded-lg border border-border bg-inputBg px-3 py-2 text-sm text-primary md:col-span-2" :placeholder="bt('footer')"></textarea>
                        </div>
                    </div>
                    <div class="md:col-span-2 rounded-lg border border-border bg-bg p-4">
                        <div class="flex items-center justify-between gap-3">
                            <div><h3 class="text-sm font-semibold text-primary">{{ bt('templates') }}</h3><p class="mt-1 text-xs text-secondary">{{ bt('templatesHint') }}</p></div>
                            <button type="button" class="rounded-lg border border-border px-3 py-2 text-xs font-semibold text-primary" :disabled="clubForm.document_templates.length >= 20" @click="addDocumentTemplate">{{ bt('addTemplate') }}</button>
                        </div>
                        <div v-for="(template, index) in clubForm.document_templates" :key="index" class="mt-3 grid gap-3 rounded-lg border border-border p-3 md:grid-cols-2">
                            <input v-model="template.name" required maxlength="160" class="rounded-lg border border-border bg-inputBg px-3 py-2 text-sm text-primary" :placeholder="bt('templateName')">
                            <select v-model="template.type" class="rounded-lg border border-border bg-inputBg px-3 py-2 text-sm text-primary">
                                <option value="letter">{{ bt('typeLetter') }}</option><option value="invoice">{{ bt('typeInvoice') }}</option><option value="receipt">{{ bt('typeReceipt') }}</option><option value="certificate">{{ bt('typeCertificate') }}</option><option value="custom">{{ bt('typeCustom') }}</option>
                            </select>
                            <input v-model="template.header" maxlength="300" class="rounded-lg border border-border bg-inputBg px-3 py-2 text-sm text-primary" :placeholder="bt('header')">
                            <input v-model="template.footer" maxlength="500" class="rounded-lg border border-border bg-inputBg px-3 py-2 text-sm text-primary" :placeholder="bt('footer')">
                            <label class="flex items-center gap-2 text-xs text-secondary"><input :checked="template.is_default" type="checkbox" class="rounded border-border bg-inputBg" @change="setDefaultDocumentTemplate(index, $event.target.checked)">{{ bt('default') }}</label>
                            <button type="button" class="justify-self-end text-xs font-semibold text-danger" @click="removeDocumentTemplate(index)">{{ bt('remove') }}</button>
                        </div>
                    </div>
                    </template>

                    <template v-if="canEditClubContact">
                    <div class="md:col-span-2 mt-2 border-t border-border pt-5">
                        <h3 class="font-semibold text-primary">{{ ct('title') }}</h3>
                        <p class="mt-1 text-xs text-secondary">{{ ct('hint') }}</p>
                    </div>
                    <div>
                        <label class="text-sm font-semibold text-primary">{{ ct('email') }}</label>
                        <input v-model="clubForm.contact_email" type="email" autocomplete="email" class="mt-1 w-full rounded-lg border border-border bg-inputBg px-3 py-2 text-sm text-primary">
                    </div>
                    <div>
                        <label class="text-sm font-semibold text-primary">{{ ct('phone') }}</label>
                        <input v-model="clubForm.contact_phone" type="tel" autocomplete="tel" class="mt-1 w-full rounded-lg border border-border bg-inputBg px-3 py-2 text-sm text-primary">
                    </div>
                    <div class="md:col-span-2">
                        <label class="text-sm font-semibold text-primary">{{ ct('website') }}</label>
                        <input v-model="clubForm.website_url" type="url" inputmode="url" :placeholder="ct('websitePlaceholder')" class="mt-1 w-full rounded-lg border border-border bg-inputBg px-3 py-2 text-sm text-primary">
                    </div>
                    <label class="md:col-span-2 flex items-start gap-3 rounded-lg border border-border bg-bg p-3 text-sm text-primary">
                        <input v-model="clubForm.contact_details_public" type="checkbox" class="mt-1 rounded border-border bg-inputBg">
                        <span><span class="block font-semibold">{{ ct('publish') }}</span><span class="block text-xs text-secondary">{{ ct('publicHint') }}</span></span>
                    </label>
                    <div class="md:col-span-2 rounded-lg border border-border bg-bg p-4">
                        <div class="flex items-center justify-between gap-3">
                            <div><h3 class="text-sm font-semibold text-primary">{{ ct('persons') }}</h3><p class="mt-1 text-xs text-secondary">{{ ct('personsHint') }}</p></div>
                            <button type="button" class="rounded-lg border border-border px-3 py-2 text-xs font-semibold text-primary" :disabled="clubForm.contact_persons.length >= 20" @click="addContactPerson">{{ ct('add') }}</button>
                        </div>
                        <div v-for="(person, index) in clubForm.contact_persons" :key="index" class="mt-3 grid gap-3 rounded-lg border border-border p-3 md:grid-cols-2">
                            <input v-model="person.name" required class="rounded-lg border border-border bg-inputBg px-3 py-2 text-sm text-primary" :placeholder="ct('name')">
                            <input v-model="person.role" class="rounded-lg border border-border bg-inputBg px-3 py-2 text-sm text-primary" :placeholder="ct('role')">
                            <input v-model="person.email" type="email" class="rounded-lg border border-border bg-inputBg px-3 py-2 text-sm text-primary" :placeholder="ct('personEmail')">
                            <input v-model="person.phone" type="tel" class="rounded-lg border border-border bg-inputBg px-3 py-2 text-sm text-primary" :placeholder="ct('personPhone')">
                            <label class="flex items-center gap-2 text-xs text-secondary"><input v-model="person.is_public" type="checkbox" class="rounded border-border bg-inputBg">{{ ct('personPublic') }}</label>
                            <button type="button" class="justify-self-end text-xs font-semibold text-danger" @click="removeContactPerson(index)">{{ ct('remove') }}</button>
                        </div>
                    </div>
                    </template>

                    <template v-if="canEditClubLegal">
                    <div class="md:col-span-2 mt-2 border-t border-border pt-5">
                        <h3 class="font-semibold text-primary">{{ lt('title') }}</h3>
                        <p class="mt-1 text-xs text-secondary">{{ lt('hint') }}</p>
                    </div>

                    <div>
                        <label class="text-sm font-semibold text-primary">{{ lt('registryAuthority') }}</label>
                        <input v-model="clubForm.registry_authority" class="mt-1 w-full rounded-lg border border-border bg-inputBg px-3 py-2 text-sm text-primary">
                    </div>
                    <div>
                        <label class="text-sm font-semibold text-primary">{{ lt('registryNumber') }}</label>
                        <input v-model="clubForm.registry_number" class="mt-1 w-full rounded-lg border border-border bg-inputBg px-3 py-2 text-sm text-primary">
                    </div>
                    <div>
                        <label class="text-sm font-semibold text-primary">{{ lt('taxAuthority') }}</label>
                        <input v-model="clubForm.tax_authority" class="mt-1 w-full rounded-lg border border-border bg-inputBg px-3 py-2 text-sm text-primary">
                    </div>
                    <div>
                        <label class="text-sm font-semibold text-primary">{{ lt('taxNumber') }}</label>
                        <input v-model="clubForm.tax_number" autocomplete="off" class="mt-1 w-full rounded-lg border border-border bg-inputBg px-3 py-2 text-sm text-primary">
                    </div>
                    <div>
                        <label class="text-sm font-semibold text-primary">{{ lt('vatId') }}</label>
                        <input v-model="clubForm.vat_id" autocomplete="off" class="mt-1 w-full rounded-lg border border-border bg-inputBg px-3 py-2 text-sm uppercase text-primary">
                    </div>
                    <div>
                        <label class="text-sm font-semibold text-primary">{{ lt('taxStatus') }}</label>
                        <select v-model="clubForm.tax_status" class="mt-1 w-full rounded-lg border border-border bg-inputBg px-3 py-2 text-sm text-primary">
                            <option value="unknown">{{ lt('unknown') }}</option>
                            <option value="nonprofit">{{ lt('nonprofit') }}</option>
                            <option value="taxable">{{ lt('taxable') }}</option>
                            <option value="mixed">{{ lt('mixed') }}</option>
                        </select>
                    </div>
                    <div>
                        <label class="text-sm font-semibold text-primary">{{ lt('exemptionUntil') }}</label>
                        <input v-model="clubForm.tax_exemption_valid_until" type="date" class="mt-1 w-full rounded-lg border border-border bg-inputBg px-3 py-2 text-sm text-primary">
                    </div>

                    <div class="md:col-span-2 rounded-lg border border-border bg-bg p-4">
                        <div class="flex items-center justify-between gap-3">
                            <div>
                                <h3 class="text-sm font-semibold text-primary">{{ lt('affiliations') }}</h3>
                                <p class="mt-1 text-xs text-secondary">{{ lt('affiliationsHint') }}</p>
                            </div>
                            <button type="button" class="rounded-lg border border-border px-3 py-2 text-xs font-semibold text-primary" :disabled="clubForm.federation_affiliations.length >= 20" @click="addFederationAffiliation">
                                {{ lt('add') }}
                            </button>
                        </div>
                        <div v-for="(affiliation, index) in clubForm.federation_affiliations" :key="index" class="mt-3 grid gap-3 rounded-lg border border-border p-3 md:grid-cols-2">
                            <input v-model="affiliation.name" required class="rounded-lg border border-border bg-inputBg px-3 py-2 text-sm text-primary" :placeholder="lt('name')">
                            <input v-model="affiliation.member_number" class="rounded-lg border border-border bg-inputBg px-3 py-2 text-sm text-primary" :placeholder="lt('memberNumber')">
                            <label class="text-xs text-secondary">{{ lt('validFrom') }}<input v-model="affiliation.valid_from" type="date" class="mt-1 w-full rounded-lg border border-border bg-inputBg px-3 py-2 text-sm text-primary"></label>
                            <label class="text-xs text-secondary">{{ lt('validUntil') }}<input v-model="affiliation.valid_until" type="date" class="mt-1 w-full rounded-lg border border-border bg-inputBg px-3 py-2 text-sm text-primary"></label>
                            <button type="button" class="justify-self-start text-xs font-semibold text-danger md:col-span-2" @click="removeFederationAffiliation(index)">{{ lt('remove') }}</button>
                        </div>
                    </div>
                    </template>

                    <div class="md:col-span-2">
                        <div class="flex flex-col gap-3 sm:flex-row sm:items-center">
                            <AppButton type="submit" :loading="clubForm.processing" :disabled="clubForm.processing">
                        {{ clubForm.processing ? tAuto('Speichert...') : tAuto('Vereinsdaten speichern') }}
                            </AppButton>
                            <AppLoadingState v-if="clubForm.processing" :label="tAuto('Vereinsdaten werden gespeichert...')" inline />
                        </div>
                    </div>
                </form>
            </section>

            <div class="grid gap-6 lg:grid-cols-[1fr_320px]">
                <section class="space-y-4">
                    <article v-for="post in posts" :key="post.id" class="rounded-lg border border-border bg-card p-4">
                        <div class="flex items-center gap-3">
                            <img :src="post.user.profile_photo_url" :alt="post.user.name"
                                width="40" height="40" loading="lazy" decoding="async"
                                class="h-10 w-10 rounded-full object-cover">
                            <div>
                                <Link :href="route('auth.users.show', post.user.id)"
                                    class="text-sm font-semibold text-primary hover:underline">
                                    {{ post.user.name }}
                                </Link>
                                <p class="text-xs text-secondary">{{ post.team?.name || clubProfile.name }} · {{
                                    formatDate(post.created_at) }}</p>
                            </div>
                        </div>
                        <p class="mt-3 whitespace-pre-line text-sm leading-6 text-primary">{{ post.content }}</p>
                        <div class="mt-3 flex gap-4 text-xs text-secondary">
                            <span>{{ post.likes_count }} {{ tAuto('Likes') }}</span>
                            <span>{{ post.comments_count }} {{ tAuto('Kommentare') }}</span>
                        </div>
                    </article>
                    <div v-if="!posts.length"
                        class="rounded-lg border border-border bg-card p-8 text-center text-sm text-secondary">
                        {{ tAuto('Noch keine sichtbaren Beiträge.') }}
                    </div>
                </section>

                <aside class="space-y-4">
                    <section class="rounded-lg border border-border bg-card p-4">
                        <h2 class="text-sm font-semibold uppercase tracking-wide text-secondary">{{ tAuto('Teams') }}</h2>
                        <div class="mt-4 space-y-2">
                            <Link v-for="team in clubProfile.teams" :key="team.id"
                                :href="route('auth.teams.show', team.id)"
                                class="flex items-center gap-3 rounded-lg p-2 hover:bg-inputBg">
                                <div
                                    class="flex h-9 w-9 items-center justify-center rounded bg-buttonPrimary text-sm font-semibold text-buttonTextPrimary">
                                    {{ initials(team.name) }}</div>
                                <div class="min-w-0">
                                    <p class="truncate text-sm font-medium text-primary">{{ team.name }}</p>
                                    <p class="text-xs text-secondary">{{ team.users_count }} {{ tAuto('Mitglieder') }}</p>
                                    <p v-if="teamOrganizationLabels(team).length" class="truncate text-xs text-secondary">{{ teamOrganizationLabels(team).join(' · ') }}</p>
                                </div>
                            </Link>
                            <AppEmptyState
                                v-if="!clubProfile.teams.length"
                                :title="tAuto('Noch keine Teams')"
                                :description="tAuto('Teams dieses Vereins erscheinen hier, sobald sie erstellt wurden.')"
                                compact
                            >
                                <template #icon>
                                    <i class="las la-users text-xl" aria-hidden="true"></i>
                                </template>
                            </AppEmptyState>
                        </div>
                    </section>

                    <section class="rounded-lg border border-border bg-card p-4">
                        <h2 class="text-sm font-semibold uppercase tracking-wide text-secondary">{{ tAuto('Admins') }}</h2>
                        <div class="mt-4 space-y-2">
                            <Link v-for="admin in clubProfile.admins" :key="admin.id"
                                :href="route('auth.users.show', admin.id)"
                                class="flex items-center gap-3 rounded-lg p-2 hover:bg-inputBg">
                                <img v-if="admin.profile_photo_thumb" :src="admin.profile_photo_thumb" :alt="admin.name" width="32" height="32" loading="lazy" decoding="async"
                                    class="h-8 w-8 rounded-full object-cover" />
                                <div v-else
                                    class="flex h-8 w-8 items-center justify-center rounded-full bg-buttonPrimary text-xs font-semibold text-buttonTextPrimary">
                                    {{ initials(admin?.name) }}
                                </div>
                                <span class="min-w-0 truncate text-sm font-medium text-primary">{{ admin.name }}</span>
                            </Link>
                        </div>
                    </section>

                    <section v-if="viewer.is_member || viewer.can_manage" class="rounded-lg border border-border bg-card p-4">
                        <h2 class="text-sm font-semibold uppercase tracking-wide text-secondary">{{ tAuto('Mitglieder') }}</h2>
                        <div class="mt-4 space-y-2">
                            <div v-for="member in clubProfile.members" :key="member.id"
                                class="flex flex-col gap-3 rounded-lg border border-transparent p-3 hover:border-border hover:bg-inputBg">
                                <div class="flex min-w-0 items-center gap-3">
                                    <Link :href="route('auth.users.show', member.id)"
                                        class="flex min-w-0 items-center gap-3 hover:underline">
                                        <img v-if="member.profile_photo_thumb" :src="member.profile_photo_thumb"
                                            :alt="member.name" width="32" height="32" loading="lazy" decoding="async" class="h-8 w-8 rounded-full object-cover" />
                                        <div v-else
                                            class="flex h-8 w-8 shrink-0 items-center justify-center rounded-full bg-buttonPrimary text-xs font-semibold text-buttonTextPrimary">
                                            {{ initials(member?.name) }}
                                        </div>
                                        <span class="truncate text-sm font-medium text-primary">{{ member.name }}</span>
                                    </Link>
                                    <p v-if="!canManageClubRoles" class="ml-auto shrink-0 text-right text-xs text-secondary">
                                        {{ memberRoles(member).map(clubRoleLabel).join(', ') }}
                                    </p>
                                </div>

                                <div v-if="canManageClubRoles" class="flex flex-wrap items-center gap-2">
                                    <button
                                        v-for="role in clubRoles"
                                        :key="role"
                                        type="button"
                                        class="rounded-full border px-2.5 py-1 text-xs font-semibold transition"
                                        :class="memberRoles(member).includes(role)
                                            ? 'border-buttonPrimary bg-buttonPrimary text-buttonTextPrimary'
                                            : 'border-border bg-card text-secondary hover:border-borderHover hover:text-primary'"
                                        :aria-pressed="memberRoles(member).includes(role)"
                                        @click="toggleMemberRole(member, role)"
                                    >
                                        {{ clubRoleLabel(role) }}
                                    </button>

                                    <button type="button" class="ml-auto rounded bg-buttonPrimary px-3 py-1.5 text-xs font-semibold text-buttonTextPrimary" @click="updateMemberRole(member)">
                                        {{ tAuto('Speichern') }}
                                    </button>
                                </div>
                            </div>
                            <AppEmptyState
                                v-if="!clubProfile.members.length"
                                :title="tAuto('Noch keine Mitglieder')"
                                :description="tAuto('Angenommene Mitglieder werden in dieser Liste sichtbar.')"
                                compact
                            >
                                <template #icon>
                                    <i class="las la-id-badge text-xl" aria-hidden="true"></i>
                                </template>
                            </AppEmptyState>
                        </div>
                    </section>
                </aside>
            </div>

            <Teleport to="body">
                <div
                    v-if="terminationRequestOpen"
                    class="fixed inset-0 z-[9999] flex items-center justify-center bg-black/60 px-4 py-6"
                    role="dialog"
                    aria-modal="true"
                    :aria-label="tAuto('Verein verlassen')"
                    @click.self="terminationRequestOpen = false"
                >
                    <form class="w-full max-w-lg rounded-lg border border-border bg-card p-5 shadow-2xl" @submit.prevent="submitTerminationRequest">
                        <div class="flex items-start justify-between gap-4">
                            <div>
                                <p class="text-xs font-semibold uppercase tracking-wide text-air-blue">{{ tAuto('Verein verlassen') }}</p>
                                <h2 class="mt-1 text-xl font-bold text-primary">{{ clubProfile.name }}</h2>
                            </div>
                            <button type="button" class="rounded-lg p-2 text-secondary hover:bg-muted" :aria-label="tAuto('Abbrechen')" @click="terminationRequestOpen = false">
                                <i class="las la-times text-xl" aria-hidden="true"></i>
                            </button>
                        </div>

                        <p class="mt-4 text-sm text-secondary">
                            {{ t('clubs_profile.messages.leave_club', { name: clubProfile.name }) }}
                        </p>

                        <label class="mt-5 block text-sm font-semibold text-primary">
                            {{ tAuto('Datum') }}
                            <input
                                v-model="terminationForm.requested_termination_on"
                                type="date"
                                :min="todayInput"
                                required
                                class="mt-1 w-full rounded-lg border-border bg-inputBg text-primary"
                            />
                        </label>
                        <p v-if="terminationForm.errors.requested_termination_on" class="mt-1 text-xs text-error">
                            {{ terminationForm.errors.requested_termination_on }}
                        </p>

                        <label class="mt-4 block text-sm font-semibold text-primary">
                            {{ tAuto('Grund:') }}
                            <textarea
                                v-model="terminationForm.termination_reason"
                                rows="4"
                                maxlength="2000"
                                class="mt-1 w-full rounded-lg border-border bg-inputBg text-primary"
                            ></textarea>
                        </label>
                        <p v-if="terminationForm.errors.termination_reason" class="mt-1 text-xs text-error">
                            {{ terminationForm.errors.termination_reason }}
                        </p>

                        <div class="mt-5 flex justify-end gap-3">
                            <button type="button" class="rounded-lg border border-border px-4 py-2 text-sm font-semibold text-secondary" @click="terminationRequestOpen = false">
                                {{ tAuto('Abbrechen') }}
                            </button>
                            <button type="submit" class="rounded-lg bg-buttonPrimary px-4 py-2 text-sm font-semibold text-buttonTextPrimary" :disabled="terminationForm.processing">
                                {{ tAuto('Anfrage senden') }}
                            </button>
                        </div>
                    </form>
                </div>
            </Teleport>

            <Teleport to="body">
                <div v-if="membershipRequestOpen" class="fixed inset-0 z-[9999] flex items-center justify-center bg-black/60 px-4 py-6">
                    <form class="airmius-modal-scroll max-h-[calc(100vh-3rem)] w-full max-w-lg overflow-y-auto rounded-lg border border-border bg-card p-5 shadow-2xl lg:max-w-3xl xl:max-w-4xl" @submit.prevent="submitMembershipRequest">
                    <div class="flex items-start justify-between gap-4">
                        <div>
                            <p class="text-xs font-semibold uppercase tracking-wide text-air-blue">{{ tAuto('Mitgliedsantrag') }}</p>
                            <h2 class="mt-1 text-xl font-bold text-primary">{{ clubProfile.name }}</h2>
                        </div>
                        <button type="button" class="rounded-lg p-2 text-secondary hover:bg-muted" @click="membershipRequestOpen = false">
                            <i class="las la-times text-xl"></i>
                        </button>
                    </div>

                    <div class="mt-4 flex gap-2 overflow-x-auto border-b border-border pb-2" role="tablist" :aria-label="tAuto('Mitgliedsantrag')">
                        <button
                            v-for="(tab, index) in membershipRequestTabs"
                            :key="tab.key"
                            type="button"
                            role="tab"
                            :aria-selected="activeMembershipTab === index"
                            class="shrink-0 rounded-lg px-3 py-2 text-xs font-semibold transition"
                            :class="activeMembershipTab === index
                                ? 'bg-buttonPrimary text-buttonTextPrimary'
                                : 'bg-inputBg text-secondary hover:text-primary'"
                            :aria-invalid="membershipRequestTabHasErrors(index)"
                            @click="activeMembershipTab = index"
                        >
                            {{ tab.label }}
                            <span
                                v-if="membershipRequestTabHasErrors(index)"
                                class="ml-1 inline-flex h-4 w-4 items-center justify-center rounded-full bg-red-500 text-[10px] font-black leading-none text-white"
                                :aria-label="locale === 'de' ? 'Pflichtangaben fehlen' : 'Required information missing'"
                            >
                                !
                            </span>
                        </button>
                    </div>

                    <div class="mt-4 space-y-3">
                        <div v-if="activeMembershipTab === 0" class="space-y-3">
                        <label class="block">
                            <span class="text-sm font-semibold text-primary">{{ tAuto('Mitgliedschaftstyp') }}</span>
                            <select
                                v-model="membershipRequestForm.club_membership_type_id"
                                class="mt-1 w-full rounded-lg border bg-inputBg text-primary"
                                :class="membershipRequestTabHasErrors(0) ? 'border-red-500 ring-1 ring-red-500/30' : 'border-border'"
                                :aria-invalid="membershipRequestTabHasErrors(0)"
                            >
                                <option value="">{{ tAuto('Allgemeine Anfrage') }}</option>
                                <option v-for="type in clubProfile.membership_types" :key="type.id" :value="type.id">
                                    {{ type.name }}
                                    <template v-if="type.amount !== null && type.amount !== undefined">
                                        - {{ formatMoney(type.amount) }} / {{ intervalLabel(type.billing_interval) }}
                                    </template>
                                </option>
                            </select>
                            <p v-if="membershipRequestTabHasErrors(0)" class="mt-1 text-xs font-semibold text-red-500">
                                {{ locale === 'de' ? 'Bitte wähle einen Mitgliedschaftstyp aus.' : 'Please select a membership type.' }}
                            </p>
                        </label>

                        <div v-if="clubProfile.membership_types?.length" class="rounded-lg border border-border bg-bg p-3 text-sm text-secondary">
                            <p v-for="type in clubProfile.membership_types" :key="type.id" class="py-1">
                                <span class="font-semibold text-primary">{{ type.name }}:</span>
                                <span v-if="type.amount !== null && type.amount !== undefined">{{ formatMoney(type.amount) }} / {{ intervalLabel(type.billing_interval) }}</span>
                                <span v-else>{{ tAuto('Beitrag nach Rücksprache') }}</span>
                            </p>
                        </div>

                        </div>

                        <div v-if="activeMembershipTab !== 0 && activeMembershipTab <= 4" class="space-y-3">
                        <div v-for="section in applicationSectionsForTab(membershipRequestTabs[activeMembershipTab].key)" :key="section.name" class="rounded-lg border border-border bg-bg p-3">
                            <h3 class="text-sm font-semibold text-primary">{{ section.name }}</h3>
                            <div class="mt-3 grid gap-3 md:grid-cols-2">
                                <label v-for="field in section.fields" :key="field.key" class="block text-sm">
                                    <span class="font-semibold text-primary">
                                        {{ field.label }}
                                        <span v-if="field.mode === 'required'" class="text-error">*</span>
                                    </span>
                                    <select
                                        v-if="field.type === 'select'"
                                        v-model="membershipRequestForm.application_data[field.key]"
                                        class="mt-1 w-full rounded-lg border bg-inputBg text-primary"
                                        :class="membershipRequestFieldHasError(field.key) ? 'border-red-500 ring-1 ring-red-500/30' : 'border-border'"
                                        :aria-invalid="membershipRequestFieldHasError(field.key)"
                                        :required="field.mode === 'required'"
                                    >
                                        <option value="">{{ tAuto('Bitte wählen') }}</option>
                                        <option v-for="option in field.options" :key="option.value" :value="option.value">{{ option.label }}</option>
                                    </select>
                                    <label v-else-if="field.type === 'checkbox'" class="mt-2 flex items-start gap-2 rounded-lg border bg-card p-3 text-secondary" :class="membershipRequestFieldHasError(field.key) ? 'border-red-500 ring-1 ring-red-500/30' : 'border-border'">
                                        <input v-model="membershipRequestForm.application_data[field.key]" type="checkbox" class="mt-1 rounded border-border bg-inputBg" :class="membershipRequestFieldHasError(field.key) ? 'accent-red-500' : ''" :required="field.mode === 'required'">
                                        <span>{{ field.label }}</span>
                                    </label>
                                    <input
                                        v-else
                                        v-model="membershipRequestForm.application_data[field.key]"
                                        :type="field.type || 'text'"
                                        :maxlength="field.max || undefined"
                                        class="mt-1 w-full rounded-lg border bg-inputBg text-primary"
                                        :class="membershipRequestFieldHasError(field.key) ? 'border-red-500 ring-1 ring-red-500/30' : 'border-border'"
                                        :aria-invalid="membershipRequestFieldHasError(field.key)"
                                        :required="field.mode === 'required'"
                                    >
                                    <p v-if="membershipRequestFieldHasError(field.key)" class="mt-1 text-xs font-semibold text-red-500">
                                        {{ locale === 'de' ? 'Pflichtfeld fehlt.' : 'Required field is missing.' }}
                                    </p>
                                    <p v-if="membershipRequestForm.errors[`application_data.${field.key}`]" class="mt-1 text-xs text-error">
                                        {{ membershipRequestForm.errors[`application_data.${field.key}`] }}
                                    </p>
                                </label>
                            </div>
                        </div>

                        </div>

                        <div v-if="activeMembershipTab === 5" class="space-y-3">
                        <div class="grid gap-3 md:grid-cols-2">
                            <label class="block">
                                <span class="text-sm font-semibold text-primary">{{ tAuto('Gewünschte Zahlmethode') }}</span>
                                <select v-model="membershipRequestForm.preferred_payment_method" class="mt-1 w-full rounded-lg border-border bg-inputBg text-primary">
                                    <option value="">{{ tAuto('Nach Rücksprache') }}</option>
                                    <option v-for="method in clubProfile.membership_payment_methods" :key="method" :value="method">
                                        {{ paymentMethodLabel(method) }}
                                    </option>
                                </select>
                            </label>
                            <label class="block">
                                <span class="text-sm font-semibold text-primary">{{ tAuto('Beitragsintervall') }}</span>
                                <select v-model="membershipRequestForm.requested_billing_interval" class="mt-1 w-full rounded-lg border-border bg-inputBg text-primary">
                                    <option value="">{{ tAuto('Wie vom Verein festgelegt') }}</option>
                                    <option value="monthly">{{ tAuto('Monatlich') }}</option>
                                    <option value="quarterly">{{ tAuto('Quartal') }}</option>
                                    <option value="four_monthly">{{ tAuto('Alle 4 Monate') }}</option>
                                    <option value="semi_yearly">{{ tAuto('Alle 6 Monate') }}</option>
                                    <option value="yearly">{{ tAuto('Jährlich') }}</option>
                                    <option value="once">{{ tAuto('Einmalig') }}</option>
                                </select>
                            </label>
                        </div>

                        </div>

                        <div v-if="activeMembershipTab === 6" class="space-y-3">
                        <div v-if="visibleMembershipDocuments.length" class="rounded-lg border border-border bg-bg p-3">
                            <h3 class="text-sm font-semibold text-primary">{{ tAuto('Dokumente des Vereins') }}</h3>
                            <p class="mt-1 text-xs text-secondary">
                                {{ tAuto('Bitte lies die verknüpften Dokumente. Pflichtdokumente müssen vor dem Absenden bestätigt werden.') }}
                            </p>
                            <div class="mt-3 space-y-3">
                                <label
                                    v-for="document in visibleMembershipDocuments"
                                    :key="document.id"
                                    class="flex items-start gap-3 rounded-lg border bg-card p-3 text-sm text-secondary"
                                    :class="membershipRequestTabHasErrors(6) && document.is_required && membershipRequestForm.accepted_documents[document.id] !== true ? 'border-red-500 ring-1 ring-red-500/30' : 'border-border'"
                                >
                                    <input
                                        v-model="membershipRequestForm.accepted_documents[document.id]"
                                        type="checkbox"
                                        class="mt-1 rounded border-border bg-inputBg"
                                        :class="membershipRequestTabHasErrors(6) && document.is_required && membershipRequestForm.accepted_documents[document.id] !== true ? 'accent-red-500' : ''"
                                        :required="document.is_required"
                                    >
                                    <span class="min-w-0">
                                        <span class="block font-semibold text-primary">
                                            {{ document.title }}
                                            <span v-if="document.is_required" class="text-error">*</span>
                                        </span>
                                        <span v-if="document.description" class="mt-1 block text-xs">{{ document.description }}</span>
                                        <a
                                            v-if="document.url"
                                            :href="document.url"
                                            target="_blank"
                                            rel="noopener noreferrer"
                                            class="mt-2 inline-flex text-xs font-semibold text-air-blue hover:underline"
                                        >
                                            {{ tAuto('Dokument öffnen') }}
                                        </a>
                                        <span v-else class="mt-2 block text-xs text-error">{{ tAuto('Kein Link hinterlegt') }}</span>
                                        <span v-if="membershipRequestForm.errors[`accepted_documents.${document.id}`]" class="mt-1 block text-xs text-error">
                                            {{ membershipRequestForm.errors[`accepted_documents.${document.id}`] }}
                                        </span>
                                        <span v-if="membershipRequestTabHasErrors(6) && document.is_required && membershipRequestForm.accepted_documents[document.id] !== true" class="mt-1 block text-xs font-semibold text-red-500">
                                            {{ locale === 'de' ? 'Bitte bestätigen.' : 'Please confirm this document.' }}
                                        </span>
                                    </span>
                                </label>
                            </div>
                        </div>

                        <label class="block">
                            <span class="text-sm font-semibold text-primary">{{ tAuto('Nachricht') }}</span>
                            <textarea v-model="membershipRequestForm.message" rows="4" class="mt-1 w-full rounded-lg border-border bg-inputBg text-primary" :placeholder="tAuto('Warum möchtest du Mitglied werden?')"></textarea>
                        </label>
                    </div>

                        </div>

                    <div class="mt-5 flex items-center justify-between gap-3">
                        <button
                            v-if="activeMembershipTab > 0"
                            type="button"
                            class="rounded-lg border border-border px-4 py-3 text-sm font-semibold text-primary hover:bg-inputBg"
                            @click="activeMembershipTab -= 1"
                        >
                            {{ locale === 'de' ? 'Zurück' : 'Back' }}
                        </button>
                        <span v-else></span>
                        <span class="text-xs font-semibold text-secondary">{{ activeMembershipTab + 1 }} / {{ membershipRequestTabs.length }}</span>
                        <button
                            v-if="activeMembershipTab < membershipRequestTabs.length - 1"
                            type="button"
                            class="rounded-lg bg-buttonPrimary px-4 py-3 text-sm font-semibold text-buttonTextPrimary"
                            @click="goToMembershipRequestNext"
                        >
                            {{ locale === 'de' ? 'Weiter' : 'Next' }}
                        </button>
                        <button
                            v-else
                            type="submit"
                            class="rounded-lg bg-buttonPrimary px-4 py-3 text-sm font-semibold text-buttonTextPrimary"
                            :disabled="membershipRequestForm.processing"
                        >
                            {{ tAuto('Anfrage senden') }}
                        </button>
                    </div>
                    </form>
                </div>
            </Teleport>
        </div>
    </AppLayout>
</template>

<style scoped>
.airmius-modal-scroll {
    scrollbar-width: thin;
    scrollbar-color: var(--air-blue, #60a5fa) rgba(15, 23, 42, 0.75);
}

.airmius-modal-scroll::-webkit-scrollbar {
    width: 10px;
}

.airmius-modal-scroll::-webkit-scrollbar-track {
    background: rgba(15, 23, 42, 0.75);
    border-radius: 999px;
}

.airmius-modal-scroll::-webkit-scrollbar-thumb {
    background: linear-gradient(180deg, var(--button-primary, #60a5fa), var(--air-blue, #38bdf8));
    border: 2px solid rgba(15, 23, 42, 0.75);
    border-radius: 999px;
}

.airmius-modal-scroll::-webkit-scrollbar-thumb:hover {
    background: linear-gradient(180deg, var(--button-primary-hover, #3b82f6), var(--air-blue, #38bdf8));
}
</style>
