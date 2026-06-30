<script setup>
import AppLayout from '@/Components/Auth/Layouts/AppLayout.vue'
import ClubWorkspaceNav from '@/Components/Auth/ClubWorkspaceNav.vue'
import Modal from '@/Components/Modal.vue'
import { Head, Link, router, useForm, usePage } from '@inertiajs/vue3'
import { computed, ref } from 'vue'
import { confirmDialog } from '@/services/dialogService'

defineOptions({ layout: AppLayout })

const props = defineProps({
    clubs: { type: Array, default: () => [] },
    membershipStatuses: { type: Array, default: () => ['active', 'non_member', 'pending', 'former'] },
    contributionIntervals: { type: Array, default: () => ['none', 'monthly', 'quarterly', 'yearly', 'once'] },
    teamRoles: { type: Array, default: () => ['Coach', 'Captain', 'Player'] },
})

const page = usePage()
const selectedClubId = ref(props.clubs[0]?.id || null)
const activeTab = ref('members')
const memberSearch = ref('')
const memberStatusFilter = ref('all')
const memberEndFilter = ref('all')
const invoiceStatusFilter = ref('all')
const transactionStatusFilter = ref('all')
const editingMemberId = ref(null)
const invoiceMemberId = ref(null)
const showAddMemberModal = ref(false)
const showImportModal = ref(false)
const showBankImportModal = ref(false)
const showFinanceEntryModal = ref(false)
const editingFinanceEntryId = ref(null)
const memberForms = ref({})
const sepaSettingsForms = ref({})
const datevSettingsForms = ref({})
const datevExportForms = ref({})
const membershipSettingsForms = ref({})
const processingJoinRequestIds = ref(new Set())
const createEmailMemberRow = () => ({
    name: '',
    email: '',
    membership_status: 'active',
    member_number: '',
    athlete_license_number: '',
    contribution_amount: '',
    contribution_interval: 'none',
    contribution_next_invoice_on: '',
    sepa_iban: '',
    sepa_bic: '',
    sepa_mandate_reference: '',
    sepa_mandate_signed_on: '',
    sepa_mandate_active: false,
    membership_ends_on: '',
})
const emailMemberForm = ref({
    send_invitation: true,
    members: [createEmailMemberRow()],
})
const importForm = useForm({
    file: null,
    send_invitation: false,
})
const bankImportForm = useForm({
    file: null,
})
const membershipTypeForm = useForm({
    name: '',
    slug: '',
    description: '',
    is_public: true,
    is_active: true,
    sort_order: 0,
})
const contributionRuleForm = useForm({
    club_membership_type_id: '',
    name: '',
    valid_from: new Date().toISOString().slice(0, 10),
    valid_until: '',
    billing_interval: 'monthly',
    amount: '',
    age_min: '',
    age_max: '',
    factor_key: '',
    factor_operator: '',
    factor_value: '',
    is_active: true,
    notes: '',
})
const financeEntryForm = useForm({
    type: 'expense',
    account: 'cash',
    category: '',
    title: '',
    amount: '',
    booked_on: new Date().toISOString().slice(0, 10),
    reference: '',
    description: '',
})

const fieldModeOptions = [
    { value: 'off', label: 'Aus' },
    { value: 'optional', label: 'Optional' },
    { value: 'required', label: 'Pflicht' },
]

const createMembershipDocumentRow = () => ({
    id: `doc-${Date.now()}-${Math.random().toString(36).slice(2, 8)}`,
    type: 'privacy',
    title: '',
    url: '',
    file: null,
    file_id: null,
    file_name: '',
    description: '',
    is_visible: true,
    is_required: false,
})

const selectedClub = computed(() => props.clubs.find((club) => club.id === selectedClubId.value) || props.clubs[0] || null)
const pageError = computed(() => {
    const errors = page.props.errors || {}
    const value = errors.join_request || errors.general || errors.message || Object.values(errors)[0]

    return Array.isArray(value) ? value[0] : value
})
const pendingRequests = computed(() => selectedClub.value?.pending_requests || [])
const clubRequests = computed(() => selectedClub.value?.club_requests || [])
const membershipTypes = computed(() => selectedClub.value?.membership_types || [])
const contributionRules = computed(() => selectedClub.value?.contribution_rules || [])
const capabilities = computed(() => selectedClub.value?.capabilities || {})
const canOpenEmailMembers = computed(() => capabilities.value.external_members !== false || capabilities.value.member_invitations !== false)
const members = computed(() => selectedClub.value?.members || [])
const externalMembers = computed(() => selectedClub.value?.external_members || [])
const invoices = computed(() => selectedClub.value?.invoices || [])
const payments = computed(() => selectedClub.value?.payments || [])
const financeEntries = computed(() => selectedClub.value?.finance_entries || [])
const bankTransactions = computed(() => selectedClub.value?.bank_transactions || [])

const activeMembersCount = computed(() => members.value.filter((member) => formFor(member).membership_status === 'active').length)
const openInvoices = computed(() => invoices.value.filter((invoice) => ['open', 'overdue'].includes(invoice.status)))
const openInvoiceTotal = computed(() => openInvoices.value.reduce((sum, invoice) => sum + Number(invoice.amount || 0), 0))
const paymentAmount = (payment) => Number(payment.amount || 0)
const fallbackCashBalance = computed(() => payments.value
    .filter((payment) => payment.method === 'cash')
    .reduce((sum, payment) => sum + paymentAmount(payment), 0))
const fallbackBankBalance = computed(() => payments.value
    .filter((payment) => ['bank_transfer', 'sepa_debit'].includes(payment.method))
    .reduce((sum, payment) => sum + paymentAmount(payment), 0))
const fallbackTotalBalance = computed(() => payments.value.reduce((sum, payment) => sum + paymentAmount(payment), 0))
const cashBalance = computed(() => Number(selectedClub.value?.cash_balance ?? fallbackCashBalance.value))
const bankBalance = computed(() => Number(selectedClub.value?.bank_balance ?? fallbackBankBalance.value))
const totalBalance = computed(() => Number(selectedClub.value?.total_balance ?? fallbackTotalBalance.value))
const unassignedBalance = computed(() => Number(selectedClub.value?.unassigned_balance ?? Math.max(0, totalBalance.value - cashBalance.value - bankBalance.value)))
const financeEntryAmount = (entry) => Number(entry.amount || 0)
const incomeTotal = computed(() => Number(selectedClub.value?.income_total ?? (
    payments.value.reduce((sum, payment) => sum + paymentAmount(payment), 0)
    + financeEntries.value.filter((entry) => entry.type === 'income').reduce((sum, entry) => sum + financeEntryAmount(entry), 0)
)))
const expenseTotal = computed(() => Number(selectedClub.value?.expense_total ?? (
    financeEntries.value.filter((entry) => entry.type === 'expense').reduce((sum, entry) => sum + financeEntryAmount(entry), 0)
)))
const sepaReadyMembersCount = computed(() => members.value.filter((member) => {
    const form = formFor(member)

    return form.sepa_mandate_active && form.sepa_iban && form.sepa_mandate_reference && form.sepa_mandate_signed_on
}).length)
const memberUsagePercent = computed(() => {
    const limit = Number(selectedClub.value?.subscription?.member_limit || 0)

    if (!limit) return 0

    return Math.min(100, Math.round(((selectedClub.value?.subscription?.member_usage || members.value.length) / limit) * 100))
})
const recurringContributionTotal = computed(() => members.value.reduce((sum, member) => {
    const form = formFor(member)

    return form.membership_status === 'active' && form.contribution_interval !== 'none'
        ? sum + Number(form.contribution_amount || 0)
        : sum
}, 0))

const dateOnly = (value) => {
    if (!value) return null
    const [year, month, day] = String(value).slice(0, 10).split('-').map(Number)
    if (!year || !month || !day) return null

    return new Date(year, month - 1, day)
}

const daysUntil = (value) => {
    const target = dateOnly(value)
    if (!target) return null

    const now = new Date()
    const today = new Date(now.getFullYear(), now.getMonth(), now.getDate())

    return Math.ceil((target.getTime() - today.getTime()) / 86400000)
}

const matchesMembershipEndFilter = (member) => {
    const days = daysUntil(formFor(member).membership_ends_on)

    if (memberEndFilter.value === 'all') return true
    if (memberEndFilter.value === 'ending_30') return days !== null && days >= 0 && days <= 30
    if (memberEndFilter.value === 'ending_60') return days !== null && days >= 0 && days <= 60
    if (memberEndFilter.value === 'expired') return days !== null && days < 0
    if (memberEndFilter.value === 'no_end') return days === null

    return true
}

const endingSoonMembersCount = computed(() => members.value.filter((member) => {
    const days = daysUntil(formFor(member).membership_ends_on)

    return days !== null && days >= 0 && days <= 30
}).length)

const endLabel = (value) => {
    const days = daysUntil(value)
    if (days === null) return null
    if (days < 0) return 'abgelaufen'
    if (days === 0) return 'endet heute'
    if (days <= 30) return `endet in ${days} Tag${days === 1 ? '' : 'en'}`

    return null
}

const filteredMembers = computed(() => {
    const search = memberSearch.value.trim().toLowerCase()

    return members.value.filter((member) => {
        const form = formFor(member)
        const matchesStatus = memberStatusFilter.value === 'all' || form.membership_status === memberStatusFilter.value

        if (!matchesStatus) return false
        if (!matchesMembershipEndFilter(member)) return false
        if (!search) return true

        return [
            member.name,
            member.email,
            form.member_number,
            form.athlete_license_number,
        ].filter(Boolean).join(' ').toLowerCase().includes(search)
    })
})

const filteredInvoices = computed(() => invoices.value.filter((invoice) => (
    invoiceStatusFilter.value === 'all' || invoice.status === invoiceStatusFilter.value
)))

const filteredBankTransactions = computed(() => bankTransactions.value.filter((transaction) => (
    transactionStatusFilter.value === 'all' || transaction.status === transactionStatusFilter.value
)))

const tabs = computed(() => [
    { key: 'members', label: 'Mitglieder', count: members.value.length + externalMembers.value.length, icon: 'las la-users' },
    { key: 'requests', label: 'Anfragen', count: pendingRequests.value.length + clubRequests.value.length, icon: 'las la-user-plus' },
    { key: 'rules', label: 'Beitragsregeln', count: contributionRules.value.length, icon: 'las la-sliders-h' },
    { key: 'invoices', label: 'Rechnungen', count: openInvoices.value.length, icon: 'las la-file-invoice' },
    { key: 'payments', label: 'Finanzen', count: payments.value.length + financeEntries.value.length, icon: 'las la-university' },
    { key: 'exports', label: 'SEPA & DATEV', count: sepaReadyMembersCount.value, icon: 'las la-file-export' },
])

const statusLabel = (status) => ({
    active: 'Vereinsmitglied',
    non_member: 'Kein Vereinsmitglied',
    pending: 'In Prüfung',
    paused: 'Pausiert',
    former: 'Ehemalig',
}[status] || status)

const statusClass = (status) => ({
    active: 'bg-air-green/15 text-air-green',
    non_member: 'bg-muted text-secondary',
    pending: 'bg-air-blue/15 text-air-blue',
    paused: 'bg-warning/10 text-warning',
    former: 'bg-error/10 text-error',
}[status] || 'bg-muted text-secondary')

const clubRoleOptions = [
    { value: 'owner', label: 'Owner' },
    { value: 'admin', label: 'Verein-Admin' },
    { value: 'manager', label: 'Manager' },
    { value: 'academy_manager', label: 'Akademie-Manager' },
    { value: 'financial_controller', label: 'Kassierer' },
    { value: 'trainer', label: 'Trainer' },
    { value: 'member', label: 'Mitglied' },
]

const intervalLabel = (interval) => ({
    none: 'Kein Beitrag',
    monthly: 'Monatlich',
    quarterly: 'Quartal',
    four_monthly: 'Alle 4 Monate',
    semi_yearly: 'Halbjährlich',
    yearly: 'Jährlich',
    once: 'Einmalig',
}[interval] || interval)

const paymentMethodLabel = (method) => selectedClub.value?.membership_payment_method_options?.find((option) => option.value === method)?.label || method

const requestDataLabel = (key) => {
    const field = selectedClub.value?.membership_application_fields?.find((candidate) => candidate.key === key)

    return field?.label || key
}

const requestDataValue = (key, value) => {
    const field = selectedClub.value?.membership_application_fields?.find((candidate) => candidate.key === key)

    if ((field?.type || '') === 'checkbox') return value ? 'Ja' : 'Nein'
    if (field?.options) return field.options.find((option) => option.value === value)?.label || value

    return value
}

const documentTypeLabel = (type) => selectedClub.value?.membership_application_document_types?.find((option) => option.value === type)?.label || type

const membershipFieldSections = computed(() => {
    const sections = []

    ;(selectedClub.value?.membership_application_fields || []).forEach((field) => {
        let section = sections.find((candidate) => candidate.name === field.section)

        if (!section) {
            section = { name: field.section, fields: [] }
            sections.push(section)
        }

        section.fields.push(field)
    })

    return sections
})

const formatMoney = (value) => new Intl.NumberFormat('de-DE', {
    style: 'currency',
    currency: 'EUR',
}).format(Number(value || 0))

const formatDate = (value) => {
    if (!value) return '-'
    return new Intl.DateTimeFormat('de-DE', { day: '2-digit', month: '2-digit', year: 'numeric' }).format(new Date(value))
}

const financeTypeLabel = (type) => ({
    income: 'Einnahme',
    expense: 'Ausgabe',
}[type] || type)

const financeAccountLabel = (account) => ({
    cash: 'Bar',
    bank: 'Bank',
}[account] || account)

const financeEntryClasses = (entry) => entry.type === 'income'
    ? 'border-air-green/30 bg-air-green/5 text-air-green'
    : 'border-error/30 bg-error/5 text-error'

const resetFinanceEntryForm = (type = 'expense', entry = null) => {
    editingFinanceEntryId.value = entry?.id || null
    financeEntryForm.type = entry?.type || type
    financeEntryForm.account = entry?.account || 'cash'
    financeEntryForm.category = entry?.category || ''
    financeEntryForm.title = entry?.title || ''
    financeEntryForm.amount = entry?.amount || ''
    financeEntryForm.booked_on = entry?.booked_on || new Date().toISOString().slice(0, 10)
    financeEntryForm.reference = entry?.reference || ''
    financeEntryForm.description = entry?.description || ''
    financeEntryForm.clearErrors()
}

const openFinanceEntryModal = (type = 'expense', entry = null) => {
    resetFinanceEntryForm(type, entry)
    showFinanceEntryModal.value = true
}

const saveFinanceEntry = () => {
    const options = {
        preserveScroll: true,
        only: ['clubs', 'flash', 'errors'],
        onSuccess: () => {
            showFinanceEntryModal.value = false
            resetFinanceEntryForm()
        },
    }

    if (editingFinanceEntryId.value) {
        financeEntryForm.put(route('auth.club-memberships.finance-entries.update', [selectedClub.value.id, editingFinanceEntryId.value]), options)
        return
    }

    financeEntryForm.post(route('auth.club-memberships.finance-entries.store', selectedClub.value.id), options)
}

const formFor = (member) => {
    memberForms.value[member.id] ??= {
        role: member.pivot.role || 'member',
        roles: Array.isArray(member.pivot.roles) && member.pivot.roles.length
            ? [...member.pivot.roles]
            : [member.pivot.role || 'member'],
        membership_status: member.pivot.membership_status || 'non_member',
        club_membership_type_id: member.pivot.club_membership_type_id || '',
        member_number: member.pivot.member_number || '',
        athlete_license_number: member.athlete_license_number || '',
        contribution_amount: member.pivot.contribution_amount || '',
        contribution_interval: member.pivot.contribution_interval || 'none',
        payment_method: member.pivot.payment_method || '',
        contribution_next_invoice_on: member.pivot.contribution_next_invoice_on || '',
        sepa_iban: member.pivot.sepa_iban || '',
        sepa_bic: member.pivot.sepa_bic || '',
        sepa_mandate_reference: member.pivot.sepa_mandate_reference || '',
        sepa_mandate_signed_on: member.pivot.sepa_mandate_signed_on || '',
        sepa_mandate_active: Boolean(member.pivot.sepa_mandate_active),
        joined_on: member.pivot.joined_on || '',
        membership_ends_on: member.pivot.membership_ends_on || '',
        membership_notes: member.pivot.membership_notes || '',
    }

    return memberForms.value[member.id]
}

const sepaSettingsFor = (club) => {
    sepaSettingsForms.value[club.id] ??= {
        sepa_creditor_id: club.sepa_creditor_id || '',
        sepa_account_holder: club.sepa_account_holder || '',
        sepa_iban: club.sepa_iban || '',
        sepa_bic: club.sepa_bic || '',
    }

    return sepaSettingsForms.value[club.id]
}

const saveSepaSettings = () => {
    router.put(route('auth.club-memberships.sepa-settings.update', selectedClub.value.id), sepaSettingsFor(selectedClub.value), {
        preserveScroll: true,
    })
}

const datevSettingsFor = (club) => {
    datevSettingsForms.value[club.id] ??= {
        datev_consultant_number: club.datev_consultant_number || '',
        datev_client_number: club.datev_client_number || '',
        datev_revenue_account: club.datev_revenue_account || '2110',
        datev_bank_account: club.datev_bank_account || '1200',
    }

    return datevSettingsForms.value[club.id]
}

const datevExportFor = (club) => {
    const now = new Date()
    const firstDay = new Date(now.getFullYear(), now.getMonth(), 1).toISOString().slice(0, 10)
    const today = now.toISOString().slice(0, 10)

    datevExportForms.value[club.id] ??= {
        from: firstDay,
        to: today,
    }

    return datevExportForms.value[club.id]
}

const saveDatevSettings = () => {
    router.put(route('auth.club-memberships.datev-settings.update', selectedClub.value.id), datevSettingsFor(selectedClub.value), {
        preserveScroll: true,
    })
}

const membershipSettingsFor = (club) => {
    membershipSettingsForms.value[club.id] ??= {
        membership_requests_enabled: Boolean(club.membership_requests_enabled),
        member_pause_requests_enabled: Boolean(club.member_pause_requests_enabled),
        membership_application_fields: Object.fromEntries((club.membership_application_fields || []).map((field) => [field.key, field.mode || 'off'])),
        membership_payment_methods: [...(club.membership_payment_methods || [])],
        membership_application_documents: (club.membership_application_documents || []).map((document) => ({ ...document, file: null })),
    }

    return membershipSettingsForms.value[club.id]
}

const addMembershipDocument = () => {
    membershipSettingsFor(selectedClub.value).membership_application_documents.push(createMembershipDocumentRow())
}

const removeMembershipDocument = (index) => {
    membershipSettingsFor(selectedClub.value).membership_application_documents.splice(index, 1)
}

const saveMembershipSettings = () => {
    router.post(route('auth.club-memberships.settings.update', selectedClub.value.id), {
        ...membershipSettingsFor(selectedClub.value),
        _method: 'put',
    }, {
        forceFormData: true,
        preserveScroll: true,
    })
}

const attachMembershipDocumentFile = (document, event) => {
    const file = event.target.files?.[0] || null

    document.file = file

    if (file && !document.title) {
        document.title = file.name
    }
}

const datevExportUrl = computed(() => {
    if (!selectedClub.value) return '#'

    const params = new URLSearchParams(datevExportFor(selectedClub.value)).toString()

    return `${route('auth.club-memberships.datev-export', selectedClub.value.id)}?${params}`
})

const invoiceForm = useForm({
    title: 'Mitgliedsbeitrag',
    description: '',
    amount: '',
    due_date: '',
})

const approveRequest = (request) => {
    if (processingJoinRequestIds.value.has(request.id)) return
    processingJoinRequestIds.value.add(request.id)

    router.post(route('auth.team-join-requests.approve', request.id), {
        role: 'Player',
    }, {
        preserveScroll: true,
        onFinish: () => processingJoinRequestIds.value.delete(request.id),
    })
}

const declineRequest = (request) => {
    if (processingJoinRequestIds.value.has(request.id)) return
    processingJoinRequestIds.value.add(request.id)

    router.post(route('auth.team-join-requests.decline', request.id), {}, {
        preserveScroll: true,
        onFinish: () => processingJoinRequestIds.value.delete(request.id),
    })
}

const approveClubRequest = (request) => {
    router.post(route('auth.club-membership-requests.approve', request.id), {}, { preserveScroll: true })
}

const declineClubRequest = (request) => {
    router.post(route('auth.club-membership-requests.decline', request.id), {}, { preserveScroll: true })
}

const storeMembershipType = () => {
    membershipTypeForm.post(route('auth.club-memberships.types.store', selectedClub.value.id), {
        preserveScroll: true,
        onSuccess: () => membershipTypeForm.reset('name', 'slug', 'description'),
    })
}

const storeContributionRule = () => {
    contributionRuleForm.post(route('auth.club-memberships.contribution-rules.store', selectedClub.value.id), {
        preserveScroll: true,
        onSuccess: () => contributionRuleForm.reset('name', 'amount', 'valid_until', 'age_min', 'age_max', 'factor_key', 'factor_operator', 'factor_value', 'notes'),
    })
}

const saveMember = (member) => {
    router.put(route('auth.club-memberships.members.update', [selectedClub.value.id, member.id]), formFor(member), {
        preserveScroll: true,
        onSuccess: () => {
            editingMemberId.value = null
        },
    })
}

const generateMemberNumber = (member) => {
    router.post(route('auth.club-memberships.members.member-number', [selectedClub.value.id, member.id]), {}, {
        preserveScroll: true,
        only: ['clubs', 'flash'],
        onSuccess: () => {
            delete memberForms.value[member.id]
        },
    })
}

const removeMember = async (member) => {
    if (!selectedClub.value) return

    const confirmed = await confirmDialog({
        title: 'Mitglied entfernen',
        message: `${member.name} wirklich aus dem Verein entfernen? Die Person wird auch aus allen Teams dieses Vereins entfernt.`,
        confirmLabel: 'Entfernen',
        danger: true,
    })

    if (!confirmed) return

    router.delete(route('auth.club-memberships.members.destroy', [selectedClub.value.id, member.id]), {
        preserveScroll: true,
        only: ['clubs', 'flash'],
    })
}

const openInvoice = (member) => {
    invoiceMemberId.value = member.id
    invoiceForm.title = 'Mitgliedsbeitrag'
    invoiceForm.description = ''
    invoiceForm.amount = formFor(member).contribution_amount || ''
    invoiceForm.due_date = ''
}

const createInvoice = (member) => {
    invoiceForm.post(route('auth.club-memberships.invoices.store', [selectedClub.value.id, member.id]), {
        preserveScroll: true,
        onSuccess: () => {
            invoiceMemberId.value = null
            invoiceForm.reset()
            invoiceForm.title = 'Mitgliedsbeitrag'
        },
    })
}

const markPaid = (invoice) => {
    router.post(route('auth.club-memberships.invoices.payments.store', invoice.id), {
        amount: invoice.amount,
        method: 'manual',
    }, { preserveScroll: true })
}

const updateInvoiceStatus = (invoice, status) => {
    router.put(route('auth.club-memberships.invoices.update', invoice.id), { status }, { preserveScroll: true })
}

const sendReminder = (invoice) => {
    router.post(route('auth.club-memberships.invoices.reminder', invoice.id), {}, { preserveScroll: true })
}

const importBankTransactions = () => {
    bankImportForm.post(route('auth.club-memberships.bank-transactions.import', selectedClub.value.id), {
        preserveScroll: true,
        forceFormData: true,
        onSuccess: () => {
            bankImportForm.reset()
            showBankImportModal.value = false
        },
    })
}

const confirmBankTransaction = (transaction) => {
    router.post(route('auth.club-memberships.bank-transactions.confirm', transaction.id), {}, { preserveScroll: true })
}

const addEmailMember = () => {
    const invitationCount = invitationRowsToSend()

    router.post(route('auth.club-memberships.email-members.store', selectedClub.value.id), emailMemberForm.value, {
        preserveScroll: true,
        onSuccess: () => {
            decrementInvitationLimit(selectedClub.value, invitationCount)
            emailMemberForm.value = {
                send_invitation: true,
                members: [createEmailMemberRow()],
            }
            showAddMemberModal.value = false
        },
    })
}

const addEmailMemberRow = () => {
    emailMemberForm.value.members.push(createEmailMemberRow())
}

const removeEmailMemberRow = (index) => {
    if (emailMemberForm.value.members.length === 1) {
        emailMemberForm.value.members = [createEmailMemberRow()]
        return
    }

    emailMemberForm.value.members.splice(index, 1)
}

const invitationRowsToSend = () => {
    if (!emailMemberForm.value.send_invitation) {
        return 0
    }

    return emailMemberForm.value.members.filter((member) => String(member.email || '').trim()).length
}

const decrementInvitationLimit = (club, amount = 1) => {
    const capabilities = club?.capabilities

    if (!capabilities || capabilities.member_invitation_daily_limit === null || capabilities.member_invitation_daily_limit === undefined) {
        return
    }

    capabilities.member_invitation_usage_today = Number(capabilities.member_invitation_usage_today || 0) + amount
    capabilities.member_invitation_remaining_today = Math.max(
        0,
        Number(capabilities.member_invitation_daily_limit || 0) - capabilities.member_invitation_usage_today,
    )
}

const importEmailMembers = () => {
    importForm.post(route('auth.club-memberships.email-members.import', selectedClub.value.id), {
        preserveScroll: true,
        forceFormData: true,
        onSuccess: () => {
            importForm.reset()
            showImportModal.value = false
        },
    })
}

const inviteExternalMember = (member) => {
    router.post(route('auth.club-memberships.email-members.invite', member.id), {}, {
        preserveScroll: true,
        onSuccess: () => decrementInvitationLimit(selectedClub.value),
    })
}
</script>

<template>
    <Head title="Mitgliederverwaltung" />

    <div class="space-y-6">
        <div
            v-if="page.props.flash?.success"
            class="rounded-lg border border-success/30 bg-success/10 px-4 py-3 text-sm font-semibold text-success"
        >
            {{ page.props.flash.success }}
        </div>

        <div
            v-if="pageError"
            class="rounded-lg border border-error/30 bg-error/10 px-4 py-3 text-sm font-semibold text-error"
        >
            {{ pageError }}
        </div>

        <ClubWorkspaceNav
            active="memberships"
            description="Mitgliedschaft, Beiträge, Abrechnung und Exporte."
        />

        <section class="surface-card overflow-hidden">
            <div class="grid gap-5 p-5 lg:grid-cols-[minmax(0,1fr)_20rem] lg:items-end">
                <div>
                    <p class="text-xs font-semibold uppercase tracking-wide text-secondary">Vereinsverwaltung</p>
                    <h1 class="mt-1 text-2xl font-bold text-primary">Mitglieder & Beiträge</h1>
                    <p class="mt-2 max-w-2xl text-sm leading-6 text-secondary">
                        Mitglieder pflegen, Anfragen prüfen, Beiträge abrechnen und Zahlungen abgleichen.
                    </p>
                </div>

                <label v-if="clubs.length" class="block">
                    <span class="text-xs font-semibold uppercase text-secondary">Aktiver Verein</span>
                    <select v-model="selectedClubId" class="mt-1 w-full rounded-lg border border-border bg-inputBg px-3 py-3 text-sm font-semibold text-primary">
                        <option v-for="club in clubs" :key="club.id" :value="club.id">
                            {{ club.name }}
                        </option>
                    </select>
                </label>
            </div>
        </section>

        <section v-if="!clubs.length" class="surface-card p-8 text-center text-secondary">
            Du verwaltest aktuell keinen Verein.
        </section>

        <template v-else-if="selectedClub">
            <section class="grid grid-cols-2 gap-3 xl:grid-cols-4">
                <div class="surface-card p-3 sm:p-4">
                    <div class="text-xs font-semibold uppercase text-secondary">Aktive Mitglieder</div>
                    <div class="mt-2 text-xl font-bold text-primary sm:text-2xl">{{ activeMembersCount }}</div>
                    <div class="mt-1 text-xs text-secondary">von {{ members.length }} verknüpften Personen</div>
                </div>
                <div class="surface-card p-3 sm:p-4">
                    <div class="text-xs font-semibold uppercase text-secondary">Offen</div>
                    <div class="mt-2 text-xl font-bold text-primary sm:text-2xl">{{ formatMoney(openInvoiceTotal) }}</div>
                    <div class="mt-1 text-xs text-secondary">{{ openInvoices.length }} offene Rechnung(en)</div>
                </div>
                <div class="surface-card p-3 sm:p-4">
                    <div class="text-xs font-semibold uppercase text-secondary">SEPA bereit</div>
                    <div class="mt-2 text-xl font-bold text-primary sm:text-2xl">{{ sepaReadyMembersCount }}</div>
                    <div class="mt-1 text-xs text-secondary">Mandate mit IBAN und Referenz</div>
                </div>
                <div class="surface-card p-3 sm:p-4">
                    <div class="text-xs font-semibold uppercase text-secondary">Wiederkehrende Beiträge</div>
                    <div class="mt-2 text-xl font-bold text-primary sm:text-2xl">{{ formatMoney(recurringContributionTotal) }}</div>
                    <div class="mt-1 text-xs text-secondary">Summe aktiver Beitragssätze</div>
                </div>
            </section>

            <section class="surface-card overflow-hidden">
                <div class="grid gap-4 border-b border-border p-5 xl:grid-cols-[minmax(0,1fr)_auto] xl:items-center">
                    <div>
                        <p class="text-xs font-semibold uppercase text-secondary">Aktueller Vereinsplan</p>
                        <h2 class="mt-1 text-xl font-semibold text-primary">{{ selectedClub.subscription?.plan?.name || 'Free' }}</h2>
                        <div class="mt-3 h-2 max-w-xl overflow-hidden rounded-full bg-inputBg">
                            <div class="h-full rounded-full bg-buttonPrimary" :style="{ width: `${memberUsagePercent}%` }"></div>
                        </div>
                        <p class="mt-2 text-xs text-secondary">
                            Mitglieder: {{ selectedClub.subscription?.member_usage || members.length }}
                            / {{ selectedClub.subscription?.member_limit || 'unbegrenzt' }}
                        </p>
                    </div>

                    <div class="flex flex-wrap gap-2 xl:justify-end">
                        <a
                            :href="route('auth.club-memberships.import-template')"
                            class="rounded-lg border border-border px-4 py-2 text-sm font-semibold text-primary hover:bg-inputBg"
                        >
                            Excel-Vorlage
                        </a>
                        <button
                            type="button"
                            class="rounded-lg border border-border px-4 py-2 text-sm font-semibold text-primary hover:bg-inputBg disabled:cursor-not-allowed disabled:opacity-50"
                            :disabled="capabilities.member_import === false"
                            :title="capabilities.member_import === false ? 'Import ist ab Starter verfügbar' : ''"
                            @click="showImportModal = true"
                        >
                            Importieren
                        </button>
                        <button
                            type="button"
                            class="rounded-lg bg-buttonPrimary px-4 py-2 text-sm font-semibold text-buttonTextPrimary disabled:cursor-not-allowed disabled:opacity-50"
                            :disabled="!canOpenEmailMembers"
                            :title="!canOpenEmailMembers ? 'Externe Mitglieder sind ab Starter verfügbar' : ''"
                            @click="showAddMemberModal = true"
                        >
                            Mitglied hinzufügen
                        </button>
                    </div>
                </div>

                <div class="flex gap-2 overflow-x-auto p-3">
                    <button
                        v-for="tab in tabs"
                        :key="tab.key"
                        type="button"
                        class="inline-flex shrink-0 items-center gap-2 rounded-lg border px-3 py-2 text-sm font-semibold transition"
                        :class="activeTab === tab.key
                            ? 'border-buttonPrimary bg-buttonPrimary text-buttonTextPrimary'
                            : 'border-border bg-card text-secondary hover:bg-inputBg hover:text-primary'"
                        @click="activeTab = tab.key"
                    >
                        <i :class="tab.icon"></i>
                        <span>{{ tab.label }}</span>
                        <span class="rounded bg-black/10 px-1.5 py-0.5 text-xs">{{ tab.count }}</span>
                    </button>
                </div>
            </section>

            <section v-if="activeTab === 'exports'" class="surface-card p-5">
                <div class="flex flex-col gap-4 xl:flex-row xl:items-start xl:justify-between">
                    <div class="max-w-2xl">
                        <h2 class="text-lg font-semibold text-primary">SEPA-Lastschrift</h2>
                        <p class="mt-1 text-sm text-secondary">
                            Hinterlege die Vereinsdaten und exportiere offene Rechnungen mit aktivem Mandat als SEPA-XML.
                        </p>
                    </div>

                    <a
                        :href="route('auth.club-memberships.sepa-export', selectedClub.id)"
                        class="rounded-lg bg-buttonPrimary px-4 py-2 text-sm font-semibold text-buttonTextPrimary disabled:cursor-not-allowed disabled:opacity-50"
                        :class="{ 'pointer-events-none opacity-50': capabilities.sepa_export === false }"
                        :title="capabilities.sepa_export === false ? 'SEPA-Export ist ab Pro verfügbar' : ''"
                    >
                        SEPA-XML exportieren
                    </a>
                </div>

                <form class="mt-4 grid gap-3 md:grid-cols-[1fr_1fr_1fr_1fr_auto]" @submit.prevent="saveSepaSettings">
                    <div>
                        <label class="text-xs font-semibold uppercase text-secondary">Gläubiger-ID</label>
                        <input v-model="sepaSettingsFor(selectedClub).sepa_creditor_id" class="mt-1 w-full rounded-lg border border-border bg-inputBg px-3 py-2 text-sm text-primary" placeholder="DE98ZZZ09999999999">
                    </div>
                    <div>
                        <label class="text-xs font-semibold uppercase text-secondary">Kontoinhaber</label>
                        <input v-model="sepaSettingsFor(selectedClub).sepa_account_holder" class="mt-1 w-full rounded-lg border border-border bg-inputBg px-3 py-2 text-sm text-primary" placeholder="Name laut Bankkonto">
                    </div>
                    <div>
                        <label class="text-xs font-semibold uppercase text-secondary">Vereins-IBAN</label>
                        <input v-model="sepaSettingsFor(selectedClub).sepa_iban" class="mt-1 w-full rounded-lg border border-border bg-inputBg px-3 py-2 text-sm text-primary" placeholder="DE...">
                    </div>
                    <div>
                        <label class="text-xs font-semibold uppercase text-secondary">BIC optional</label>
                        <input v-model="sepaSettingsFor(selectedClub).sepa_bic" class="mt-1 w-full rounded-lg border border-border bg-inputBg px-3 py-2 text-sm text-primary" placeholder="GENODE...">
                    </div>
                    <div class="flex items-end">
                        <button
                            class="w-full rounded-lg border border-border px-4 py-2 text-sm font-semibold text-primary disabled:cursor-not-allowed disabled:opacity-50"
                            :disabled="capabilities.sepa_export === false"
                            :title="capabilities.sepa_export === false ? 'SEPA-Export ist ab Pro verfügbar' : ''"
                        >
                            Speichern
                        </button>
                    </div>
                </form>
            </section>

            <section v-if="activeTab === 'members' && externalMembers.length" class="surface-card p-5">
                <h2 class="text-lg font-semibold text-primary">Externe Mitglieder ohne Verknüpfung</h2>
                <p class="mt-1 text-sm text-secondary">
                    Diese Personen sind im Verein hinterlegt, aber noch nicht mit einem Airmius-Konto verbunden.
                </p>

                <div class="mt-4 grid gap-3 lg:grid-cols-2">
                    <article v-for="member in externalMembers" :key="member.id" class="rounded-lg border border-border bg-bg p-4">
                        <div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
                            <div>
                                <h3 class="font-semibold text-primary">{{ member.name || member.email }}</h3>
                                <p class="text-sm text-secondary">{{ member.email }}</p>
                                <p class="mt-1 text-xs text-secondary">
                                    Ende: {{ formatDate(member.membership_ends_on) }}
                                </p>
                                <p class="mt-2 text-xs text-secondary">
                                    Mitgliedsnummer: {{ member.member_number || '-' }} · Lizenznummer: {{ member.athlete_license_number || '-' }}
                                </p>
                                <p class="mt-1 text-xs text-secondary">
                                    Einladung: {{ member.invitation_status === 'pending' ? 'gesendet' : member.invitation_status === 'linked' ? 'verknüpft' : 'nicht gesendet' }}
                                </p>
                            </div>

                            <button
                                v-if="member.invitation_status !== 'linked'"
                                type="button"
                                class="rounded-lg bg-buttonPrimary px-3 py-2 text-sm font-semibold text-buttonTextPrimary"
                                @click="inviteExternalMember(member)"
                            >
                                Einladung/Verknüpfung
                            </button>
                        </div>
                    </article>
                </div>
            </section>

            <section v-if="activeTab === 'requests'" class="surface-card p-5">
                <div class="flex items-center justify-between gap-4">
                    <div>
                        <h2 class="text-lg font-semibold text-primary">Offene Beitrittsanfragen</h2>
                        <p class="mt-1 text-sm text-secondary">
                            Personen treten erst nach Annahme dem Team und Verein bei.
                        </p>
                    </div>
                </div>

                <div class="mt-4 space-y-3">
                    <article v-for="request in clubRequests" :key="`club-${request.id}`" class="rounded-lg border border-air-blue/30 bg-air-blue/5 p-4">
                        <div class="flex flex-col gap-3 md:flex-row md:items-center md:justify-between">
                            <div>
                                <p class="font-semibold text-primary">{{ request.user.name }}</p>
                                <p class="text-sm text-secondary">
                                    {{ request.user.email }}
                                    <span v-if="request.type === 'pause'">möchte die Mitgliedschaft pausieren</span>
                                    <span v-else-if="request.type === 'removal_objection'">widerspricht der Entfernung aus dem Verein</span>
                                    <span v-else>möchte Vereinsmitglied werden</span>
                                </p>
                                <p v-if="request.membership_type" class="mt-1 text-xs text-secondary">
                                    Typ: {{ request.membership_type.name }} · Vorschau {{ formatMoney(request.preview_amount) }} / {{ intervalLabel(request.preview_interval) }}
                                </p>
                                <p v-if="request.requested_pause_from" class="mt-1 text-xs text-secondary">
                                    Pause: {{ formatDate(request.requested_pause_from) }} bis {{ formatDate(request.requested_pause_until) }}
                                </p>
                                <p v-if="request.message" class="mt-2 text-sm text-secondary">{{ request.message }}</p>
                                <div v-if="request.application_data && Object.keys(request.application_data).length" class="mt-3 grid gap-2 rounded-lg border border-border bg-bg p-3 text-xs text-secondary md:grid-cols-2">
                                    <p v-for="(value, key) in request.application_data" :key="key">
                                        <span class="font-semibold text-primary">{{ requestDataLabel(key) }}:</span>
                                        {{ requestDataValue(key, value) }}
                                    </p>
                                </div>
                                <div v-if="request.accepted_documents?.length" class="mt-3 rounded-lg border border-border bg-bg p-3 text-xs text-secondary">
                                    <p class="font-semibold text-primary">Bestätigte Dokumente</p>
                                    <div class="mt-2 flex flex-wrap gap-2">
                                        <a
                                            v-for="document in request.accepted_documents"
                                            :key="document.id"
                                            :href="document.url || '#'"
                                            target="_blank"
                                            rel="noreferrer"
                                            class="rounded-full bg-muted px-2 py-1 text-secondary hover:text-primary"
                                        >
                                            {{ document.title }}
                                        </a>
                                    </div>
                                </div>
                                <div v-if="request.preferred_payment_method || request.requested_billing_interval" class="mt-2 flex flex-wrap gap-2 text-xs">
                                    <span v-if="request.preferred_payment_method" class="rounded-full bg-muted px-2 py-1 text-secondary">
                                        Zahlmethode: {{ paymentMethodLabel(request.preferred_payment_method) }}
                                    </span>
                                    <span v-if="request.requested_billing_interval" class="rounded-full bg-muted px-2 py-1 text-secondary">
                                        Intervall: {{ intervalLabel(request.requested_billing_interval) }}
                                    </span>
                                </div>
                            </div>

                            <div class="flex gap-2">
                                <button type="button" class="rounded-lg bg-buttonPrimary px-4 py-2 text-sm font-semibold text-buttonTextPrimary" @click="approveClubRequest(request)">
                                    Annehmen
                                </button>
                                <button type="button" class="rounded-lg border border-border px-4 py-2 text-sm font-semibold text-primary" @click="declineClubRequest(request)">
                                    Ablehnen
                                </button>
                            </div>
                        </div>
                    </article>

                    <article v-for="request in pendingRequests" :key="request.id" class="rounded-lg border border-border bg-bg p-4">
                        <div class="flex flex-col gap-3 md:flex-row md:items-center md:justify-between">
                            <div>
                                <p class="font-semibold text-primary">{{ request.user.name }}</p>
                                <p class="text-sm text-secondary">
                                    {{ request.user.email }} möchte zu {{ request.team.name }}
                                </p>
                            </div>

                            <div class="flex gap-2">
                                <button
                                    type="button"
                                    class="rounded-lg bg-buttonPrimary px-4 py-2 text-sm font-semibold text-buttonTextPrimary disabled:opacity-60"
                                    :disabled="processingJoinRequestIds.has(request.id)"
                                    @click="approveRequest(request)"
                                >
                                    Annehmen
                                </button>
                                <button
                                    type="button"
                                    class="rounded-lg border border-border px-4 py-2 text-sm font-semibold text-primary disabled:opacity-60"
                                    :disabled="processingJoinRequestIds.has(request.id)"
                                    @click="declineRequest(request)"
                                >
                                    Ablehnen
                                </button>
                            </div>
                        </div>
                    </article>

                    <p v-if="!pendingRequests.length && !clubRequests.length" class="rounded-lg border border-border bg-bg p-4 text-sm text-secondary">
                        Keine offenen Anfragen.
                    </p>
                </div>
            </section>

            <section v-if="activeTab === 'rules'" class="grid gap-6 xl:grid-cols-[minmax(0,1fr)_24rem]">
                <div class="space-y-6">
                    <section class="surface-card p-5">
                        <h2 class="text-lg font-semibold text-primary">Online-Anfragen</h2>
                        <form class="mt-4 grid gap-3 md:grid-cols-3" @submit.prevent="saveMembershipSettings">
                            <label class="flex items-start gap-3 rounded-lg border border-border bg-bg p-3 text-sm text-primary">
                                <input v-model="membershipSettingsFor(selectedClub).membership_requests_enabled" type="checkbox" class="mt-1 rounded border-border bg-inputBg">
                                <span>
                                    <span class="block font-semibold">Mitgliedsanfragen erlauben</span>
                                    <span class="block text-secondary">Interessenten sehen die Beitragstypen und können eine Anfrage stellen.</span>
                                </span>
                            </label>
                            <label class="flex items-start gap-3 rounded-lg border border-border bg-bg p-3 text-sm text-primary">
                                <input v-model="membershipSettingsFor(selectedClub).member_pause_requests_enabled" type="checkbox" class="mt-1 rounded border-border bg-inputBg">
                                <span>
                                    <span class="block font-semibold">Pausen-Anfragen erlauben</span>
                                    <span class="block text-secondary">Mitglieder können eine Pause beantragen; der Verein entscheidet.</span>
                                </span>
                            </label>
                            <div class="rounded-lg border border-border bg-bg p-3 md:col-span-3">
                                <p class="text-sm font-semibold text-primary">Erlaubte Zahlmethoden</p>
                                <div class="mt-3 flex flex-wrap gap-3">
                                    <label
                                        v-for="method in selectedClub.membership_payment_method_options"
                                        :key="method.value"
                                        class="flex items-center gap-2 rounded-lg border border-border bg-card px-3 py-2 text-sm text-primary"
                                    >
                                        <input v-model="membershipSettingsFor(selectedClub).membership_payment_methods" :value="method.value" type="checkbox" class="rounded border-border bg-inputBg">
                                        {{ method.label }}
                                    </label>
                                </div>
                            </div>
                            <div class="rounded-lg border border-border bg-bg p-3 md:col-span-3">
                                <p class="text-sm font-semibold text-primary">Mitgliedsantrag-Felder</p>
                                <div class="mt-4 space-y-4">
                                    <section v-for="section in membershipFieldSections" :key="section.name">
                                        <h3 class="text-xs font-semibold uppercase tracking-wide text-secondary">{{ section.name }}</h3>
                                        <div class="mt-2 grid gap-2 md:grid-cols-2 xl:grid-cols-3">
                                            <label v-for="field in section.fields" :key="field.key" class="rounded-lg border border-border bg-card p-3 text-sm">
                                                <span class="font-semibold text-primary">{{ field.label }}</span>
                                                <select v-model="membershipSettingsFor(selectedClub).membership_application_fields[field.key]" class="mt-2 w-full rounded-lg border-border bg-inputBg text-sm text-primary">
                                                    <option v-for="mode in fieldModeOptions" :key="mode.value" :value="mode.value">{{ mode.label }}</option>
                                                </select>
                                            </label>
                                        </div>
                                    </section>
                                </div>
                            </div>
                            <div class="rounded-lg border border-border bg-bg p-3 md:col-span-3">
                                <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                                    <div>
                                        <p class="text-sm font-semibold text-primary">Dokumente & Bestätigungen</p>
                                        <p class="mt-1 text-xs text-secondary">Verknüpfe Datenschutz, Satzung, Regeln oder Beitragsordnung. Pflichtdokumente müssen Interessenten vor dem Absenden bestätigen.</p>
                                    </div>
                                    <button type="button" class="rounded-lg border border-border px-3 py-2 text-xs font-semibold text-primary hover:bg-inputBg" @click="addMembershipDocument">
                                        Dokument hinzufügen
                                    </button>
                                </div>
                                <div class="mt-4 space-y-3">
                                    <article
                                        v-for="(document, index) in membershipSettingsFor(selectedClub).membership_application_documents"
                                        :key="document.id || index"
                                        class="rounded-lg border border-border bg-card p-3"
                                    >
                                        <div class="grid gap-3 md:grid-cols-2">
                                            <label class="block text-sm">
                                                <span class="font-semibold text-primary">Typ</span>
                                                <select v-model="document.type" class="mt-1 w-full rounded-lg border-border bg-inputBg text-sm text-primary">
                                                    <option v-for="type in selectedClub.membership_application_document_types" :key="type.value" :value="type.value">{{ type.label }}</option>
                                                </select>
                                            </label>
                                            <label class="block text-sm">
                                                <span class="font-semibold text-primary">Titel</span>
                                                <input v-model="document.title" class="mt-1 w-full rounded-lg border-border bg-inputBg text-sm text-primary" placeholder="z. B. Datenschutzinformation">
                                            </label>
                                            <label class="block text-sm md:col-span-2">
                                                <span class="font-semibold text-primary">Link zur Datei oder Seite</span>
                                                <input v-model="document.url" type="url" class="mt-1 w-full rounded-lg border-border bg-inputBg text-sm text-primary" placeholder="https://...">
                                            </label>
                                            <label class="block text-sm md:col-span-2">
                                                <span class="font-semibold text-primary">Oder Datei hochladen</span>
                                                <input
                                                    type="file"
                                                    class="mt-1 block w-full rounded-lg border border-border bg-inputBg px-3 py-2 text-sm text-primary"
                                                    @change="attachMembershipDocumentFile(document, $event)"
                                                >
                                                <span v-if="document.file" class="mt-1 block text-xs text-air-blue">
                                                    Neue Datei: {{ document.file.name }}
                                                </span>
                                                <a
                                                    v-else-if="document.file_id"
                                                    :href="document.url || '#'"
                                                    target="_blank"
                                                    rel="noreferrer"
                                                    class="mt-1 inline-flex text-xs font-semibold text-air-blue hover:underline"
                                                >
                                                    Gespeicherte Datei öffnen: {{ document.file_name || document.title }}
                                                </a>
                                                <span class="mt-1 block text-xs text-secondary">
                                                    Hochgeladene Dateien landen im Vereins-Dateimanager im Ordner „Mitgliedsantrag“.
                                                </span>
                                            </label>
                                            <label class="block text-sm md:col-span-2">
                                                <span class="font-semibold text-primary">Hinweistext</span>
                                                <textarea v-model="document.description" rows="2" class="mt-1 w-full rounded-lg border-border bg-inputBg text-sm text-primary" placeholder="Optionaler Hinweis für Interessenten"></textarea>
                                            </label>
                                            <label class="flex items-start gap-2 rounded-lg border border-border bg-bg p-3 text-sm text-primary">
                                                <input v-model="document.is_visible" type="checkbox" class="mt-1 rounded border-border bg-inputBg">
                                                <span>
                                                    <span class="block font-semibold">Im Antrag anzeigen</span>
                                                    <span class="block text-xs text-secondary">User sehen dieses Dokument vor dem Absenden.</span>
                                                </span>
                                            </label>
                                            <label class="flex items-start gap-2 rounded-lg border border-border bg-bg p-3 text-sm text-primary">
                                                <input v-model="document.is_required" type="checkbox" class="mt-1 rounded border-border bg-inputBg">
                                                <span>
                                                    <span class="block font-semibold">Bestätigung erforderlich</span>
                                                    <span class="block text-xs text-secondary">Ohne Häkchen kann der Antrag nicht gesendet werden.</span>
                                                </span>
                                            </label>
                                        </div>
                                        <div class="mt-3 flex items-center justify-between gap-3">
                                            <span class="text-xs text-secondary">{{ documentTypeLabel(document.type) }}</span>
                                            <button type="button" class="rounded-lg border border-error/40 px-3 py-1.5 text-xs font-semibold text-error hover:bg-error/10" @click="removeMembershipDocument(index)">
                                                Entfernen
                                            </button>
                                        </div>
                                    </article>
                                    <p v-if="!membershipSettingsFor(selectedClub).membership_application_documents.length" class="rounded-lg border border-dashed border-border p-4 text-sm text-secondary">
                                        Noch keine Dokumente verknüpft.
                                    </p>
                                </div>
                            </div>
                            <div class="flex items-end md:col-span-3">
                                <button class="w-full rounded-lg bg-buttonPrimary px-4 py-3 text-sm font-semibold text-buttonTextPrimary">Speichern</button>
                            </div>
                        </form>
                    </section>

                    <section class="surface-card p-5">
                        <h2 class="text-lg font-semibold text-primary">Historische Beitragsregeln</h2>
                        <div class="mt-4 space-y-3">
                            <article v-for="rule in contributionRules" :key="rule.id" class="rounded-lg border border-border bg-bg p-4">
                                <div class="flex flex-col gap-2 md:flex-row md:items-start md:justify-between">
                                    <div>
                                        <p class="font-semibold text-primary">{{ rule.name }}</p>
                                        <p class="text-sm text-secondary">
                                            {{ rule.membership_type_name || 'Alle Typen' }} · {{ formatMoney(rule.amount) }} / {{ intervalLabel(rule.billing_interval) }}
                                        </p>
                                        <p class="mt-1 text-xs text-secondary">
                                            Gilt {{ formatDate(rule.valid_from) }} bis {{ formatDate(rule.valid_until) }}
                                            <span v-if="rule.age_min || rule.age_max"> · Alter {{ rule.age_min || 0 }}-{{ rule.age_max || 'offen' }}</span>
                                        </p>
                                    </div>
                                    <span class="rounded-full px-2 py-1 text-xs font-semibold" :class="rule.is_active ? 'bg-air-green/15 text-air-green' : 'bg-muted text-secondary'">
                                        {{ rule.is_active ? 'aktiv' : 'inaktiv' }}
                                    </span>
                                </div>
                            </article>
                            <p v-if="!contributionRules.length" class="rounded-lg border border-border bg-bg p-4 text-sm text-secondary">Noch keine Beitragsregeln.</p>
                        </div>
                    </section>
                </div>

                <aside class="space-y-6">
                    <section class="surface-card p-5">
                        <h2 class="text-lg font-semibold text-primary">Mitgliedschaftstyp</h2>
                        <form class="mt-4 grid gap-3" @submit.prevent="storeMembershipType">
                            <input v-model="membershipTypeForm.name" class="rounded-lg border-border bg-inputBg text-sm text-primary" placeholder="z. B. Jugendmitglied" required>
                            <input v-model="membershipTypeForm.slug" class="rounded-lg border-border bg-inputBg text-sm text-primary" placeholder="slug optional">
                            <textarea v-model="membershipTypeForm.description" rows="3" class="rounded-lg border-border bg-inputBg text-sm text-primary" placeholder="Beschreibung"></textarea>
                            <label class="flex items-center gap-2 text-sm text-primary"><input v-model="membershipTypeForm.is_public" type="checkbox" class="rounded border-border bg-inputBg"> Öffentlich sichtbar</label>
                            <button class="rounded-lg bg-buttonPrimary px-4 py-2 text-sm font-semibold text-buttonTextPrimary">Typ speichern</button>
                        </form>
                        <div class="mt-4 flex flex-wrap gap-2">
                            <span v-for="type in membershipTypes" :key="type.id" class="rounded-full bg-muted px-3 py-1 text-xs font-semibold text-secondary">{{ type.name }}</span>
                        </div>
                    </section>

                    <section class="surface-card p-5">
                        <h2 class="text-lg font-semibold text-primary">Neue Beitragsregel</h2>
                        <form class="mt-4 grid gap-3" @submit.prevent="storeContributionRule">
                            <select v-model="contributionRuleForm.club_membership_type_id" class="rounded-lg border-border bg-inputBg text-sm text-primary">
                                <option value="">Alle Typen</option>
                                <option v-for="type in membershipTypes" :key="type.id" :value="type.id">{{ type.name }}</option>
                            </select>
                            <input v-model="contributionRuleForm.name" class="rounded-lg border-border bg-inputBg text-sm text-primary" placeholder="Regelname" required>
                            <input v-model="contributionRuleForm.amount" type="number" min="0" step="0.01" class="rounded-lg border-border bg-inputBg text-sm text-primary" placeholder="Beitrag EUR" required>
                            <select v-model="contributionRuleForm.billing_interval" class="rounded-lg border-border bg-inputBg text-sm text-primary">
                                <option v-for="interval in contributionIntervals" :key="interval" :value="interval">{{ intervalLabel(interval) }}</option>
                            </select>
                            <div class="grid grid-cols-2 gap-2">
                                <input v-model="contributionRuleForm.valid_from" type="date" class="rounded-lg border-border bg-inputBg text-sm text-primary" required>
                                <input v-model="contributionRuleForm.valid_until" type="date" class="rounded-lg border-border bg-inputBg text-sm text-primary">
                            </div>
                            <div class="grid grid-cols-2 gap-2">
                                <input v-model="contributionRuleForm.age_min" type="number" min="0" class="rounded-lg border-border bg-inputBg text-sm text-primary" placeholder="Alter von">
                                <input v-model="contributionRuleForm.age_max" type="number" min="0" class="rounded-lg border-border bg-inputBg text-sm text-primary" placeholder="Alter bis">
                            </div>
                            <textarea v-model="contributionRuleForm.notes" rows="2" class="rounded-lg border-border bg-inputBg text-sm text-primary" placeholder="Notiz"></textarea>
                            <button class="rounded-lg bg-buttonPrimary px-4 py-2 text-sm font-semibold text-buttonTextPrimary">Regel speichern</button>
                        </form>
                    </section>
                </aside>
            </section>

            <section v-if="activeTab === 'members'" class="surface-card overflow-hidden">
                <div class="border-b border-border p-5">
                    <div class="flex flex-col gap-4 xl:flex-row xl:items-center xl:justify-between">
                        <div>
                            <h2 class="text-lg font-semibold text-primary">Mitglieder & Beitragsdaten</h2>
                            <p class="mt-1 text-sm text-secondary">
                                Teammitglieder können als echte Vereinsmitglieder oder als reine Teamteilnehmer markiert werden.
                            </p>
                        </div>

                        <div class="grid gap-2 sm:grid-cols-[minmax(13rem,1fr)_12rem_14rem]">
                            <label class="flex items-center gap-2 rounded-lg border border-border bg-inputBg px-3 py-2">
                                <i class="las la-search text-lg text-secondary"></i>
                                <input
                                    v-model="memberSearch"
                                    type="search"
                                    class="min-w-0 flex-1 border-0 bg-transparent p-0 text-sm text-primary placeholder-secondary focus:ring-0"
                                    placeholder="Mitglied suchen"
                                >
                            </label>
                            <select v-model="memberStatusFilter" class="rounded-lg border border-border bg-inputBg px-3 py-2 text-sm text-primary">
                                <option value="all">Alle Status</option>
                                <option v-for="status in membershipStatuses" :key="status" :value="status">{{ statusLabel(status) }}</option>
                            </select>
                            <select v-model="memberEndFilter" class="rounded-lg border border-border bg-inputBg px-3 py-2 text-sm text-primary">
                                <option value="all">Alle Laufzeiten</option>
                                <option value="ending_30">Endet in 30 Tagen</option>
                                <option value="ending_60">Endet in 60 Tagen</option>
                                <option value="expired">Bereits abgelaufen</option>
                                <option value="no_end">Ohne Enddatum</option>
                            </select>
                        </div>
                    </div>
                    <div v-if="endingSoonMembersCount" class="mt-4 rounded-lg border border-warning/30 bg-warning/10 px-4 py-3 text-sm text-warning">
                        {{ endingSoonMembersCount }} Mitgliedschaft{{ endingSoonMembersCount === 1 ? '' : 'en' }} endet innerhalb der nächsten 30 Tage.
                    </div>
                </div>

                <div class="divide-y divide-border">
                    <article v-for="member in filteredMembers" :key="member.id" class="p-5">
                        <div class="flex flex-col gap-4 xl:flex-row xl:items-start xl:justify-between">
                            <div class="min-w-0">
                                <div class="flex flex-wrap items-center gap-2">
                                    <Link :href="route('auth.users.show', member.id)" class="font-semibold text-primary hover:underline">
                                        {{ member.name }}
                                    </Link>
                                    <span class="rounded-full px-2 py-1 text-xs font-semibold" :class="statusClass(formFor(member).membership_status)">
                                        {{ statusLabel(formFor(member).membership_status) }}
                                    </span>
                                    <span
                                        v-if="endLabel(formFor(member).membership_ends_on)"
                                        class="rounded-full bg-warning/10 px-2 py-1 text-xs font-semibold text-warning"
                                    >
                                        {{ endLabel(formFor(member).membership_ends_on) }}
                                    </span>
                                </div>
                                <p class="mt-1 text-sm text-secondary">{{ member.email }}</p>
                                <p class="mt-2 text-xs text-secondary">
                                    Nr. {{ formFor(member).member_number || '-' }} · Beitrag {{ formatMoney(formFor(member).contribution_amount) }} · {{ intervalLabel(formFor(member).contribution_interval) }} · {{ paymentMethodLabel(formFor(member).payment_method) || 'Zahlmethode offen' }}
                                </p>
                                <p class="mt-1 text-xs text-secondary">
                                    Lizenznummer: {{ formFor(member).athlete_license_number || '-' }} - Ende {{ formatDate(formFor(member).membership_ends_on) }}
                                </p>
                            </div>

                            <div class="flex flex-wrap gap-2">
                                <button type="button" class="rounded-lg border border-border px-3 py-2 text-sm text-primary" @click="editingMemberId = editingMemberId === member.id ? null : member.id">
                                    Bearbeiten
                                </button>
                                <button
                                    type="button"
                                    class="rounded-lg bg-buttonPrimary px-3 py-2 text-sm font-semibold text-buttonTextPrimary disabled:cursor-not-allowed disabled:opacity-50"
                                    :disabled="capabilities.invoices === false"
                                    :title="capabilities.invoices === false ? 'Rechnungen sind ab Starter verfügbar' : ''"
                                    @click="openInvoice(member)"
                                >
                                    Rechnung
                                </button>
                                <button
                                    type="button"
                                    class="rounded-lg border border-error/40 px-3 py-2 text-sm font-semibold text-error hover:bg-error/10"
                                    @click="removeMember(member)"
                                >
                                    Aus Verein entfernen
                                </button>
                            </div>
                        </div>

                        <form v-if="editingMemberId === member.id" class="mt-4 grid gap-3 rounded-lg border border-border bg-bg p-4 md:grid-cols-3" @submit.prevent="saveMember(member)">
                            <div>
                                <label class="text-xs font-semibold uppercase text-secondary">Rollen</label>
                                <div class="mt-1 grid gap-2 rounded-lg border border-border bg-inputBg p-3">
                                    <label v-for="role in clubRoleOptions" :key="role.value" class="flex items-center gap-2 text-sm text-primary">
                                        <input
                                            v-model="formFor(member).roles"
                                            type="checkbox"
                                            :value="role.value"
                                            class="rounded border-border bg-card"
                                        >
                                        <span>{{ role.label }}</span>
                                    </label>
                                </div>
                                <p class="mt-1 text-xs text-secondary">Mehrere Rollen sind möglich, z. B. Trainer und Kassierer.</p>
                            </div>

                            <div>
                                <label class="text-xs font-semibold uppercase text-secondary">Mitgliedschaft</label>
                                <select v-model="formFor(member).membership_status" class="mt-1 w-full rounded-lg border border-border bg-inputBg px-3 py-2 text-sm text-primary">
                                    <option v-for="status in membershipStatuses" :key="status" :value="status">{{ statusLabel(status) }}</option>
                                </select>
                            </div>

                            <div>
                                <label class="text-xs font-semibold uppercase text-secondary">Mitgliedschaftstyp</label>
                                <select v-model="formFor(member).club_membership_type_id" class="mt-1 w-full rounded-lg border border-border bg-inputBg px-3 py-2 text-sm text-primary">
                                    <option value="">Kein Typ</option>
                                    <option v-for="type in membershipTypes" :key="type.id" :value="type.id">{{ type.name }}</option>
                                </select>
                            </div>

                            <div>
                                <label class="text-xs font-semibold uppercase text-secondary">Mitgliedsnummer</label>
                                <div class="mt-1 flex gap-2">
                                    <input v-model="formFor(member).member_number" class="min-w-0 flex-1 rounded-lg border border-border bg-inputBg px-3 py-2 text-sm text-primary">
                                    <button type="button" class="rounded-lg border border-border px-3 py-2 text-xs font-semibold text-primary hover:bg-inputBg" @click="generateMemberNumber(member)">
                                        Generieren
                                    </button>
                                </div>
                            </div>

                            <div>
                                <label class="text-xs font-semibold uppercase text-secondary">Lizenznummer</label>
                                <input
                                    v-model="formFor(member).athlete_license_number"
                                    class="mt-1 w-full rounded-lg border border-border bg-inputBg px-3 py-2 text-sm text-primary"
                                    placeholder="z. B. Spielerpass- oder Verbandsnummer"
                                >
                            </div>

                            <div>
                                <label class="text-xs font-semibold uppercase text-secondary">Beitrag</label>
                                <input v-model="formFor(member).contribution_amount" type="number" min="0" step="0.01" class="mt-1 w-full rounded-lg border border-border bg-inputBg px-3 py-2 text-sm text-primary">
                            </div>

                            <div>
                                <label class="text-xs font-semibold uppercase text-secondary">Intervall</label>
                                <select v-model="formFor(member).contribution_interval" class="mt-1 w-full rounded-lg border border-border bg-inputBg px-3 py-2 text-sm text-primary">
                                    <option v-for="interval in contributionIntervals" :key="interval" :value="interval">{{ intervalLabel(interval) }}</option>
                                </select>
                            </div>

                            <div>
                                <label class="text-xs font-semibold uppercase text-secondary">Zahlmethode</label>
                                <select v-model="formFor(member).payment_method" class="mt-1 w-full rounded-lg border border-border bg-inputBg px-3 py-2 text-sm text-primary">
                                    <option value="">Offen</option>
                                    <option v-for="method in selectedClub.membership_payment_method_options" :key="method.value" :value="method.value">{{ method.label }}</option>
                                </select>
                            </div>

                            <div>
                                <label class="text-xs font-semibold uppercase text-secondary">Nächste automatische Rechnung</label>
                                <input v-model="formFor(member).contribution_next_invoice_on" type="date" class="mt-1 w-full rounded-lg border border-border bg-inputBg px-3 py-2 text-sm text-primary">
                                <p class="mt-1 text-xs text-secondary">Automatik wird ab Pro/Elite ausgeführt.</p>
                            </div>

                            <div>
                                <label class="text-xs font-semibold uppercase text-secondary">SEPA IBAN</label>
                                <input v-model="formFor(member).sepa_iban" class="mt-1 w-full rounded-lg border border-border bg-inputBg px-3 py-2 text-sm text-primary" placeholder="DE...">
                            </div>

                            <div>
                                <label class="text-xs font-semibold uppercase text-secondary">SEPA BIC optional</label>
                                <input v-model="formFor(member).sepa_bic" class="mt-1 w-full rounded-lg border border-border bg-inputBg px-3 py-2 text-sm text-primary" placeholder="GENODE...">
                            </div>

                            <div>
                                <label class="text-xs font-semibold uppercase text-secondary">Mandatsreferenz</label>
                                <input v-model="formFor(member).sepa_mandate_reference" class="mt-1 w-full rounded-lg border border-border bg-inputBg px-3 py-2 text-sm text-primary" placeholder="MANDAT-1001">
                            </div>

                            <div>
                                <label class="text-xs font-semibold uppercase text-secondary">Mandatsdatum</label>
                                <input v-model="formFor(member).sepa_mandate_signed_on" type="date" class="mt-1 w-full rounded-lg border border-border bg-inputBg px-3 py-2 text-sm text-primary">
                            </div>

                            <label class="flex items-center gap-2 self-end rounded-lg border border-border bg-inputBg px-3 py-2 text-sm text-primary">
                                <input v-model="formFor(member).sepa_mandate_active" type="checkbox" class="rounded border-border bg-bg">
                                SEPA-Mandat aktiv
                            </label>

                            <div>
                                <label class="text-xs font-semibold uppercase text-secondary">Eintritt</label>
                                <input v-model="formFor(member).joined_on" type="date" class="mt-1 w-full rounded-lg border border-border bg-inputBg px-3 py-2 text-sm text-primary">
                            </div>

                            <div>
                                <label class="text-xs font-semibold uppercase text-secondary">Ende der Mitgliedschaft</label>
                                <input v-model="formFor(member).membership_ends_on" type="date" class="mt-1 w-full rounded-lg border border-border bg-inputBg px-3 py-2 text-sm text-primary">
                            </div>

                            <div class="md:col-span-2">
                                <label class="text-xs font-semibold uppercase text-secondary">Notiz</label>
                                <textarea v-model="formFor(member).membership_notes" rows="3" class="mt-1 w-full rounded-lg border border-border bg-inputBg px-3 py-2 text-sm text-primary"></textarea>
                            </div>

                            <div class="md:col-span-3">
                                <button class="rounded-lg bg-buttonPrimary px-4 py-2 text-sm font-semibold text-buttonTextPrimary">
                                    Speichern
                                </button>
                            </div>
                        </form>

                        <form v-if="invoiceMemberId === member.id" class="mt-4 grid gap-3 rounded-lg border border-border bg-bg p-4 md:grid-cols-[1fr_140px_170px_auto]" @submit.prevent="createInvoice(member)">
                            <input v-model="invoiceForm.title" class="rounded-lg border border-border bg-inputBg px-3 py-2 text-sm text-primary" placeholder="Titel">
                            <input v-model="invoiceForm.amount" type="number" min="0.01" step="0.01" class="rounded-lg border border-border bg-inputBg px-3 py-2 text-sm text-primary" placeholder="Betrag">
                            <input v-model="invoiceForm.due_date" type="date" class="rounded-lg border border-border bg-inputBg px-3 py-2 text-sm text-primary">
                            <button class="rounded-lg bg-buttonPrimary px-4 py-2 text-sm font-semibold text-buttonTextPrimary">
                                Erstellen
                            </button>
                            <textarea v-model="invoiceForm.description" rows="2" class="rounded-lg border border-border bg-inputBg px-3 py-2 text-sm text-primary md:col-span-4" placeholder="Beschreibung optional"></textarea>
                        </form>
                    </article>
                    <p v-if="!filteredMembers.length" class="p-6 text-sm text-secondary">
                        Keine Mitglieder passen zu deiner Suche.
                    </p>
                </div>
            </section>

            <section v-if="activeTab === 'invoices'" class="surface-card p-5">
                <h2 class="text-lg font-semibold text-primary">Rechnungen</h2>
                <div class="mt-4 flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                    <p class="text-sm text-secondary">{{ filteredInvoices.length }} von {{ invoices.length }} Rechnungen sichtbar.</p>
                    <select v-model="invoiceStatusFilter" class="rounded-lg border border-border bg-inputBg px-3 py-2 text-sm text-primary">
                        <option value="all">Alle Status</option>
                        <option value="open">Offen</option>
                        <option value="paid">Bezahlt</option>
                        <option value="overdue">Überfällig</option>
                        <option value="cancelled">Storniert</option>
                    </select>
                </div>
                <div class="mt-4 overflow-x-auto">
                    <table class="min-w-full text-left text-sm">
                        <thead class="text-xs uppercase text-secondary">
                            <tr>
                                <th class="py-2 pr-4">Nr.</th>
                                <th class="py-2 pr-4">User</th>
                                <th class="py-2 pr-4">Titel</th>
                                <th class="py-2 pr-4">Betrag</th>
                                <th class="py-2 pr-4">Fällig</th>
                                <th class="py-2 pr-4">Status</th>
                                <th class="py-2 pr-4">Aktion</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-border">
                            <tr v-for="invoice in filteredInvoices" :key="invoice.id">
                                <td class="py-3 pr-4 text-primary">{{ invoice.number }}</td>
                                <td class="py-3 pr-4 text-secondary">{{ invoice.user?.name || '-' }}</td>
                                <td class="py-3 pr-4 text-primary">{{ invoice.title || '-' }}</td>
                                <td class="py-3 pr-4 text-primary">{{ formatMoney(invoice.amount) }}</td>
                                <td class="py-3 pr-4 text-secondary">{{ formatDate(invoice.due_date) }}</td>
                                <td class="py-3 pr-4">
                                    <select :value="invoice.status" class="rounded border border-border bg-inputBg px-2 py-1 text-xs text-primary" @change="updateInvoiceStatus(invoice, $event.target.value)">
                                        <option value="open">Offen</option>
                                        <option value="paid">Bezahlt</option>
                                        <option value="overdue">Überfällig</option>
                                        <option value="cancelled">Storniert</option>
                                    </select>
                                </td>
                                <td class="py-3 pr-4">
                                    <div class="flex gap-2">
                                        <button v-if="invoice.status !== 'paid'" type="button" class="rounded bg-buttonPrimary px-2 py-1 text-xs text-buttonTextPrimary" @click="markPaid(invoice)">
                                            Bezahlt
                                        </button>
                                        <button
                                            v-if="invoice.status !== 'paid'"
                                            type="button"
                                            class="rounded border border-border px-2 py-1 text-xs text-primary disabled:cursor-not-allowed disabled:opacity-50"
                                            :disabled="capabilities.payment_reminders === false"
                                            :title="capabilities.payment_reminders === false ? 'Mahnungen sind ab Club verfügbar' : ''"
                                            @click="sendReminder(invoice)"
                                        >
                                            Mahnung
                                        </button>
                                    </div>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                    <p v-if="!filteredInvoices.length" class="py-6 text-sm text-secondary">Keine passenden Rechnungen.</p>
                </div>
            </section>

            <section v-if="activeTab === 'payments'" class="surface-card p-5">
                <div class="flex flex-col gap-4 lg:flex-row lg:items-start lg:justify-between">
                    <div>
                        <p class="text-xs font-semibold uppercase tracking-wide text-air-blue">Vereinskasse</p>
                        <h2 class="mt-1 text-lg font-semibold text-primary">Einnahmen, Ausgaben und Bestände</h2>
                        <p class="mt-1 max-w-2xl text-sm text-secondary">
                            Mitgliedszahlungen, Spenden und Vorauszahlungen fließen automatisch ein. Zusätzliche Einnahmen und Ausgaben werden im Kassenbuch erfasst.
                        </p>
                    </div>
                    <div class="flex flex-wrap gap-2">
                        <button
                            type="button"
                            class="rounded-lg border border-air-green/50 px-4 py-2 text-sm font-semibold text-air-green hover:bg-air-green/10"
                            @click="openFinanceEntryModal('income')"
                        >
                            Einnahme buchen
                        </button>
                        <button
                            type="button"
                            class="rounded-lg border border-error/50 px-4 py-2 text-sm font-semibold text-error hover:bg-error/10"
                            @click="openFinanceEntryModal('expense')"
                        >
                            Ausgabe buchen
                        </button>
                    </div>
                </div>

                <div class="mt-5 grid grid-cols-1 gap-3 sm:grid-cols-2 xl:grid-cols-5">
                    <div class="rounded-lg border border-border bg-inputBg/60 p-4">
                        <div class="text-xs font-semibold uppercase text-secondary">Barbestand</div>
                        <div class="mt-2 text-2xl font-bold text-primary">{{ formatMoney(cashBalance) }}</div>
                        <div class="mt-1 text-xs text-secondary">Kasse vor Ort</div>
                    </div>
                    <div class="rounded-lg border border-border bg-inputBg/60 p-4">
                        <div class="text-xs font-semibold uppercase text-secondary">Bankbestand</div>
                        <div class="mt-2 text-2xl font-bold text-primary">{{ formatMoney(bankBalance) }}</div>
                        <div class="mt-1 text-xs text-secondary">Überweisung und SEPA</div>
                    </div>
                    <div class="rounded-lg border border-border bg-inputBg/60 p-4">
                        <div class="text-xs font-semibold uppercase text-secondary">Gesamt</div>
                        <div class="mt-2 text-2xl font-bold text-primary">{{ formatMoney(totalBalance) }}</div>
                        <div class="mt-1 text-xs text-secondary">
                            <span v-if="unassignedBalance > 0">inkl. {{ formatMoney(unassignedBalance) }} manuell</span>
                            <span v-else>Bar plus Bank</span>
                        </div>
                    </div>
                    <div class="rounded-lg border border-air-green/25 bg-air-green/5 p-4">
                        <div class="text-xs font-semibold uppercase text-secondary">Einnahmen</div>
                        <div class="mt-2 text-2xl font-bold text-air-green">{{ formatMoney(incomeTotal) }}</div>
                        <div class="mt-1 text-xs text-secondary">Zahlungen und Buchungen</div>
                    </div>
                    <div class="rounded-lg border border-error/25 bg-error/5 p-4">
                        <div class="text-xs font-semibold uppercase text-secondary">Ausgaben</div>
                        <div class="mt-2 text-2xl font-bold text-error">{{ formatMoney(expenseTotal) }}</div>
                        <div class="mt-1 text-xs text-secondary">aus Kassenbuch</div>
                    </div>
                </div>
            </section>

            <section v-if="activeTab === 'payments'" class="surface-card p-5">
                <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                    <div>
                        <h2 class="text-lg font-semibold text-primary">Kassenbuch</h2>
                        <p class="mt-1 text-sm text-secondary">
                            Freie Einnahmen und Ausgaben, die nicht aus einer Mitgliedsrechnung entstehen.
                        </p>
                    </div>
                    <button
                        type="button"
                        class="rounded-lg border border-border px-4 py-2 text-sm font-semibold text-primary hover:bg-inputBg"
                        @click="openFinanceEntryModal('expense')"
                    >
                        Buchung hinzufügen
                    </button>
                </div>

                <div class="mt-4 overflow-x-auto">
                    <table v-if="financeEntries.length" class="min-w-full text-left text-sm">
                        <thead class="text-xs uppercase text-secondary">
                            <tr>
                                <th class="py-2 pr-4">Datum</th>
                                <th class="py-2 pr-4">Buchung</th>
                                <th class="py-2 pr-4">Konto</th>
                                <th class="py-2 pr-4">Betrag</th>
                                <th class="py-2 pr-4">Aktion</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-border">
                            <tr v-for="entry in financeEntries" :key="entry.id">
                                <td class="py-3 pr-4 text-secondary">{{ formatDate(entry.booked_on) }}</td>
                                <td class="py-3 pr-4">
                                    <div class="font-semibold text-primary">{{ entry.title }}</div>
                                    <div class="text-xs text-secondary">
                                        {{ entry.category || financeTypeLabel(entry.type) }}
                                        <span v-if="entry.reference"> · {{ entry.reference }}</span>
                                    </div>
                                </td>
                                <td class="py-3 pr-4 text-secondary">{{ financeAccountLabel(entry.account) }}</td>
                                <td class="py-3 pr-4">
                                    <span class="rounded-full border px-2 py-1 text-xs font-semibold" :class="financeEntryClasses(entry)">
                                        {{ entry.type === 'income' ? '+' : '-' }} {{ formatMoney(entry.amount) }}
                                    </span>
                                </td>
                                <td class="py-3 pr-4">
                                    <button
                                        type="button"
                                        class="rounded border border-border px-2 py-1 text-xs font-semibold text-primary hover:bg-inputBg"
                                        @click="openFinanceEntryModal(entry.type, entry)"
                                    >
                                        Bearbeiten
                                    </button>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                    <p v-else class="rounded-lg border border-dashed border-border bg-bg/50 p-4 text-sm text-secondary">
                        Noch keine freien Einnahmen oder Ausgaben erfasst.
                    </p>
                </div>
            </section>

            <section v-if="activeTab === 'payments'" class="surface-card p-5">
                <div class="flex flex-col gap-4 lg:flex-row lg:items-center lg:justify-between">
                    <div>
                        <h2 class="text-lg font-semibold text-primary">Bankabgleich</h2>
                        <p class="mt-1 text-sm text-secondary">
                            CSV-Umsätze importieren, Rechnungen automatisch zuordnen und unklare Treffer manuell bestätigen.
                        </p>
                    </div>
                    <button
                        type="button"
                        class="rounded-lg bg-buttonPrimary px-4 py-2 text-sm font-semibold text-buttonTextPrimary disabled:cursor-not-allowed disabled:opacity-50"
                        :disabled="capabilities.bank_reconciliation === false"
                        :title="capabilities.bank_reconciliation === false ? 'Bankabgleich ist ab Pro verfügbar' : ''"
                        @click="showBankImportModal = true"
                    >
                        Bank-CSV importieren
                    </button>
                </div>

                <div class="mt-4 flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                    <p class="text-sm text-secondary">{{ filteredBankTransactions.length }} von {{ bankTransactions.length }} Umsätzen sichtbar.</p>
                    <select v-model="transactionStatusFilter" class="rounded-lg border border-border bg-inputBg px-3 py-2 text-sm text-primary">
                        <option value="all">Alle Status</option>
                        <option value="matched">Verbucht</option>
                        <option value="suggested">Vorschlag</option>
                        <option value="unmatched">Offen</option>
                    </select>
                </div>

                <div class="mt-4 overflow-x-auto">
                    <table class="min-w-full text-left text-sm">
                        <thead class="text-xs uppercase text-secondary">
                            <tr>
                                <th class="py-2 pr-4">Datum</th>
                                <th class="py-2 pr-4">Zahler</th>
                                <th class="py-2 pr-4">Betrag</th>
                                <th class="py-2 pr-4">Rechnung</th>
                                <th class="py-2 pr-4">Status</th>
                                <th class="py-2 pr-4">Aktion</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-border">
                            <tr v-for="transaction in filteredBankTransactions" :key="transaction.id">
                                <td class="py-3 pr-4 text-secondary">{{ formatDate(transaction.booking_date) }}</td>
                                <td class="py-3 pr-4">
                                    <div class="text-primary">{{ transaction.debtor_name || '-' }}</div>
                                    <div class="text-xs text-secondary">{{ transaction.debtor_iban || transaction.purpose || '-' }}</div>
                                </td>
                                <td class="py-3 pr-4 text-primary">{{ formatMoney(transaction.amount) }}</td>
                                <td class="py-3 pr-4 text-secondary">
                                    {{ transaction.invoice?.number || '-' }}
                                    <span v-if="transaction.invoice?.title">- {{ transaction.invoice.title }}</span>
                                </td>
                                <td class="py-3 pr-4">
                                    <span class="rounded-full px-2 py-1 text-xs font-semibold" :class="{
                                        'bg-air-green/15 text-air-green': transaction.status === 'matched',
                                        'bg-air-blue/15 text-air-blue': transaction.status === 'suggested',
                                        'bg-muted text-secondary': transaction.status === 'unmatched',
                                    }">
                                        {{ transaction.status === 'matched' ? 'Verbucht' : transaction.status === 'suggested' ? 'Vorschlag' : 'Offen' }}
                                    </span>
                                    <div class="mt-1 text-xs text-secondary">{{ transaction.match_reason }}</div>
                                </td>
                                <td class="py-3 pr-4">
                                    <button
                                        v-if="transaction.status === 'suggested' && transaction.invoice"
                                        type="button"
                                        class="rounded border border-border px-2 py-1 text-xs text-primary"
                                        @click="confirmBankTransaction(transaction)"
                                    >
                                        Bestätigen
                                    </button>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                    <p v-if="!filteredBankTransactions.length" class="py-6 text-sm text-secondary">Keine passenden Bankumsätze.</p>
                </div>
            </section>

            <section v-if="activeTab === 'exports'" class="surface-card p-5">
                <div class="flex flex-col gap-4 xl:flex-row xl:items-start xl:justify-between">
                    <div class="max-w-2xl">
                        <h2 class="text-lg font-semibold text-primary">DATEV / SKR42</h2>
                        <p class="mt-1 text-sm text-secondary">
                            Exportiere bezahlte Mitgliedsbeiträge als CSV-Buchungsstapel. Konten bitte mit Steuerberatung abstimmen.
                        </p>
                    </div>

                    <a
                        :href="datevExportUrl"
                        class="rounded-lg bg-buttonPrimary px-4 py-2 text-sm font-semibold text-buttonTextPrimary"
                        :class="{ 'pointer-events-none opacity-50': capabilities.datev_export === false }"
                        :title="capabilities.datev_export === false ? 'DATEV-Export ist ab Pro verfügbar' : ''"
                    >
                        DATEV-CSV exportieren
                    </a>
                </div>

                <div class="mt-4 grid gap-4 xl:grid-cols-[1fr_1fr]">
                    <form class="grid gap-3 md:grid-cols-2" @submit.prevent="saveDatevSettings">
                        <div>
                            <label class="text-xs font-semibold uppercase text-secondary">Beraternummer</label>
                            <input v-model="datevSettingsFor(selectedClub).datev_consultant_number" class="mt-1 w-full rounded-lg border border-border bg-inputBg px-3 py-2 text-sm text-primary" placeholder="Optional">
                        </div>
                        <div>
                            <label class="text-xs font-semibold uppercase text-secondary">Mandantennummer</label>
                            <input v-model="datevSettingsFor(selectedClub).datev_client_number" class="mt-1 w-full rounded-lg border border-border bg-inputBg px-3 py-2 text-sm text-primary" placeholder="Optional">
                        </div>
                        <div>
                            <label class="text-xs font-semibold uppercase text-secondary">Erlöskonto SKR42</label>
                            <input v-model="datevSettingsFor(selectedClub).datev_revenue_account" class="mt-1 w-full rounded-lg border border-border bg-inputBg px-3 py-2 text-sm text-primary" placeholder="z. B. 2110">
                        </div>
                        <div>
                            <label class="text-xs font-semibold uppercase text-secondary">Bankkonto SKR42</label>
                            <input v-model="datevSettingsFor(selectedClub).datev_bank_account" class="mt-1 w-full rounded-lg border border-border bg-inputBg px-3 py-2 text-sm text-primary" placeholder="z. B. 1200">
                        </div>
                        <div class="md:col-span-2">
                            <button
                                class="rounded-lg border border-border px-4 py-2 text-sm font-semibold text-primary disabled:cursor-not-allowed disabled:opacity-50"
                                :disabled="capabilities.datev_export === false"
                                :title="capabilities.datev_export === false ? 'DATEV-Export ist ab Pro verfügbar' : ''"
                            >
                                DATEV-Einstellungen speichern
                            </button>
                        </div>
                    </form>

                    <div class="grid gap-3 rounded-lg border border-border bg-bg p-4 md:grid-cols-2">
                        <div>
                            <label class="text-xs font-semibold uppercase text-secondary">Von</label>
                            <input v-model="datevExportFor(selectedClub).from" type="date" class="mt-1 w-full rounded-lg border border-border bg-inputBg px-3 py-2 text-sm text-primary">
                        </div>
                        <div>
                            <label class="text-xs font-semibold uppercase text-secondary">Bis</label>
                            <input v-model="datevExportFor(selectedClub).to" type="date" class="mt-1 w-full rounded-lg border border-border bg-inputBg px-3 py-2 text-sm text-primary">
                        </div>
                        <p class="text-xs text-secondary md:col-span-2">
                            Exportiert werden bezahlte Zahlungen im Zeitraum. Der CSV-Aufbau ist für die Beta bewusst schlicht und prüfbar gehalten.
                        </p>
                    </div>
                </div>
            </section>
        </template>

        <Modal :show="showAddMemberModal" max-width="2xl" @close="showAddMemberModal = false">
            <div class="p-2">
                <h2 class="text-xl font-bold text-primary">Mitglieder per E-Mail hinzufügen</h2>
                <p class="mt-1 text-sm text-secondary">
                    Erfasse mehrere Mitglieder auf einmal. Wenn eine Einladung aktiv ist, werden vorhandene Konten verknüpft, sonst geht eine Einladung per E-Mail raus.
                </p>
                <p
                    v-if="capabilities.member_invitation_daily_limit"
                    class="mt-2 rounded-lg border border-border bg-bg px-3 py-2 text-xs font-semibold text-secondary"
                >
                    Free-Limit: {{ capabilities.member_invitation_remaining_today }} von {{ capabilities.member_invitation_daily_limit }} Einladungen heute übrig.
                </p>

                <form class="mt-5 space-y-4" @submit.prevent="addEmailMember">
                    <label class="flex items-center gap-2 rounded-lg border border-border bg-bg px-3 py-2 text-sm text-primary">
                        <input v-model="emailMemberForm.send_invitation" type="checkbox" class="rounded border-border bg-inputBg">
                        Einladung zu Airmius verschicken
                    </label>

                    <div class="max-h-[60vh] space-y-3 overflow-y-auto pr-1">
                        <article
                            v-for="(member, index) in emailMemberForm.members"
                            :key="index"
                            class="rounded-lg border border-border bg-bg p-4"
                        >
                            <div class="mb-3 flex items-center justify-between gap-3">
                                <h3 class="text-sm font-semibold text-primary">Mitglied {{ index + 1 }}</h3>
                                <button
                                    type="button"
                                    class="rounded-lg border border-border px-3 py-1 text-xs font-semibold text-primary hover:bg-inputBg"
                                    @click="removeEmailMemberRow(index)"
                                >
                                    Entfernen
                                </button>
                            </div>

                            <div class="grid gap-3 md:grid-cols-2">
                                <div>
                                    <label class="text-xs font-semibold uppercase text-secondary">Name</label>
                                    <input
                                        v-model="member.name"
                                        class="mt-1 w-full rounded-lg border border-border bg-inputBg px-3 py-2 text-sm text-primary"
                                        placeholder="Optional"
                                    >
                                </div>

                                <div>
                                    <label class="text-xs font-semibold uppercase text-secondary">E-Mail</label>
                                    <input
                                        v-model="member.email"
                                        type="email"
                                        required
                                        class="mt-1 w-full rounded-lg border border-border bg-inputBg px-3 py-2 text-sm text-primary"
                                        placeholder="mitglied@example.org"
                                    >
                                </div>

                                <div>
                                    <label class="text-xs font-semibold uppercase text-secondary">Mitgliedschaft</label>
                                    <select v-model="member.membership_status" class="mt-1 w-full rounded-lg border border-border bg-inputBg px-3 py-2 text-sm text-primary">
                                        <option v-for="status in membershipStatuses" :key="status" :value="status">{{ statusLabel(status) }}</option>
                                    </select>
                                </div>

                                <div>
                                    <label class="text-xs font-semibold uppercase text-secondary">Mitgliedsnummer</label>
                                    <input
                                        v-model="member.member_number"
                                        class="mt-1 w-full rounded-lg border border-border bg-inputBg px-3 py-2 text-sm text-primary"
                                        placeholder="Optional"
                                    >
                                </div>

                                <div>
                                    <label class="text-xs font-semibold uppercase text-secondary">Lizenznummer</label>
                                    <input
                                        v-model="member.athlete_license_number"
                                        class="mt-1 w-full rounded-lg border border-border bg-inputBg px-3 py-2 text-sm text-primary"
                                        placeholder="Optional"
                                    >
                                </div>

                                <div>
                                    <label class="text-xs font-semibold uppercase text-secondary">Beitrag</label>
                                    <input
                                        v-model="member.contribution_amount"
                                        type="number"
                                        min="0"
                                        step="0.01"
                                        class="mt-1 w-full rounded-lg border border-border bg-inputBg px-3 py-2 text-sm text-primary"
                                        placeholder="0,00"
                                    >
                                </div>

                                <div>
                                    <label class="text-xs font-semibold uppercase text-secondary">Intervall</label>
                                    <select v-model="member.contribution_interval" class="mt-1 w-full rounded-lg border border-border bg-inputBg px-3 py-2 text-sm text-primary">
                                        <option v-for="interval in contributionIntervals" :key="interval" :value="interval">{{ intervalLabel(interval) }}</option>
                                    </select>
                                </div>

                                <div>
                                    <label class="text-xs font-semibold uppercase text-secondary">Nächste automatische Rechnung</label>
                                    <input
                                        v-model="member.contribution_next_invoice_on"
                                        type="date"
                                        class="mt-1 w-full rounded-lg border border-border bg-inputBg px-3 py-2 text-sm text-primary"
                                    >
                                </div>

                                <div>
                                    <label class="text-xs font-semibold uppercase text-secondary">SEPA IBAN</label>
                                    <input
                                        v-model="member.sepa_iban"
                                        class="mt-1 w-full rounded-lg border border-border bg-inputBg px-3 py-2 text-sm text-primary"
                                        placeholder="Optional"
                                    >
                                </div>

                                <div>
                                    <label class="text-xs font-semibold uppercase text-secondary">SEPA BIC</label>
                                    <input
                                        v-model="member.sepa_bic"
                                        class="mt-1 w-full rounded-lg border border-border bg-inputBg px-3 py-2 text-sm text-primary"
                                        placeholder="Optional"
                                    >
                                </div>

                                <div>
                                    <label class="text-xs font-semibold uppercase text-secondary">Mandatsreferenz</label>
                                    <input
                                        v-model="member.sepa_mandate_reference"
                                        class="mt-1 w-full rounded-lg border border-border bg-inputBg px-3 py-2 text-sm text-primary"
                                        placeholder="Optional"
                                    >
                                </div>

                                <div>
                                    <label class="text-xs font-semibold uppercase text-secondary">Mandatsdatum</label>
                                    <input
                                        v-model="member.sepa_mandate_signed_on"
                                        type="date"
                                        class="mt-1 w-full rounded-lg border border-border bg-inputBg px-3 py-2 text-sm text-primary"
                                    >
                                </div>

                                <label class="flex items-center gap-2 self-end rounded-lg border border-border bg-inputBg px-3 py-2 text-sm text-primary">
                                    <input v-model="member.sepa_mandate_active" type="checkbox" class="rounded border-border bg-bg">
                                    SEPA aktiv
                                </label>

                                <div>
                                    <label class="text-xs font-semibold uppercase text-secondary">Ende der Mitgliedschaft</label>
                                    <input
                                        v-model="member.membership_ends_on"
                                        type="date"
                                        class="mt-1 w-full rounded-lg border border-border bg-inputBg px-3 py-2 text-sm text-primary"
                                    >
                                </div>
                            </div>
                        </article>
                    </div>

                    <button
                        type="button"
                        class="rounded-lg border border-border px-4 py-2 text-sm font-semibold text-primary hover:bg-inputBg"
                        @click="addEmailMemberRow"
                    >
                        Weiteres Mitglied
                    </button>

                    <div class="flex justify-end gap-2">
                        <button type="button" class="rounded-lg border border-border px-4 py-2 text-sm font-semibold text-primary" @click="showAddMemberModal = false">
                            Abbrechen
                        </button>
                        <button class="rounded-lg bg-buttonPrimary px-4 py-2 text-sm font-semibold text-buttonTextPrimary">
                            Mitglieder speichern
                        </button>
                    </div>
                </form>
            </div>
        </Modal>

        <Modal :show="showImportModal" max-width="2xl" @close="showImportModal = false">
            <div class="p-2">
                <h2 class="text-xl font-bold text-primary">Mitglieder importieren</h2>
                <p class="mt-1 text-sm text-secondary">
                    Importiere Excel- oder CSV-Listen mit Name, E-Mail, Mitgliedsnummer, Lizenznummer, Beitrag, Eintritts- und Enddatum.
                </p>

                <div class="mt-4 rounded-lg border border-border bg-bg p-4 text-sm text-secondary">
                    <p class="font-semibold text-primary">Empfohlen</p>
                    <p class="mt-1">
                        Lade zuerst die Airmius Excel-Vorlage herunter. Die erste Beispielzeile kannst du ersetzen oder entfernen.
                    </p>
                    <a
                        :href="route('auth.club-memberships.import-template')"
                        class="mt-3 inline-flex rounded-lg border border-border px-3 py-2 text-sm font-semibold text-primary hover:bg-inputBg"
                    >
                        Vorlage herunterladen
                    </a>
                </div>

                <form class="mt-5 space-y-4" @submit.prevent="importEmailMembers">
                    <div>
                        <label class="text-xs font-semibold uppercase text-secondary">Datei</label>
                        <input
                            type="file"
                            accept=".xlsx,.csv,.txt"
                            required
                            class="mt-1 block w-full rounded-lg border border-border bg-inputBg px-3 py-2 text-sm text-primary"
                            @input="importForm.file = $event.target.files[0]"
                        >
                        <p v-if="importForm.errors.file" class="mt-2 text-sm text-error">{{ importForm.errors.file }}</p>
                    </div>

                    <label class="flex items-start gap-2 rounded-lg border border-border bg-bg px-3 py-2 text-sm text-primary">
                        <input v-model="importForm.send_invitation" type="checkbox" class="mt-1 rounded border-border bg-inputBg">
                        <span>
                            Einladung/Verknüpfung direkt aktivieren
                            <span class="block text-xs text-secondary">
                                Bestehende Airmius-Konten werden verbunden, sonst wird eine Einladung an die E-Mail-Adresse gesendet.
                            </span>
                        </span>
                    </label>

                    <div class="flex justify-end gap-2">
                        <button type="button" class="rounded-lg border border-border px-4 py-2 text-sm font-semibold text-primary" @click="showImportModal = false">
                            Abbrechen
                        </button>
                        <button class="rounded-lg bg-buttonPrimary px-4 py-2 text-sm font-semibold text-buttonTextPrimary" :disabled="importForm.processing">
                            Import starten
                        </button>
                    </div>
                </form>
            </div>
        </Modal>

        <Modal :show="showFinanceEntryModal" max-width="2xl" @close="showFinanceEntryModal = false">
            <div class="p-2">
                <h2 class="text-xl font-bold text-primary">
                    {{ editingFinanceEntryId ? 'Buchung bearbeiten' : financeTypeLabel(financeEntryForm.type) + ' buchen' }}
                </h2>
                <p class="mt-1 text-sm text-secondary">
                    Erfasse freie Einnahmen und Ausgaben für Kasse oder Bank. Mitgliedszahlungen werden weiterhin über Rechnungen, Spenden oder Vorauszahlungen gebucht.
                </p>

                <form class="mt-5 space-y-4" @submit.prevent="saveFinanceEntry">
                    <div class="grid gap-3 sm:grid-cols-2">
                        <div>
                            <label class="text-xs font-semibold uppercase text-secondary">Typ</label>
                            <select v-model="financeEntryForm.type" class="mt-1 w-full rounded-lg border border-border bg-inputBg px-3 py-2 text-sm text-primary">
                                <option value="income">Einnahme</option>
                                <option value="expense">Ausgabe</option>
                            </select>
                            <p v-if="financeEntryForm.errors.type" class="mt-1 text-xs text-error">{{ financeEntryForm.errors.type }}</p>
                        </div>
                        <div>
                            <label class="text-xs font-semibold uppercase text-secondary">Konto</label>
                            <select v-model="financeEntryForm.account" class="mt-1 w-full rounded-lg border border-border bg-inputBg px-3 py-2 text-sm text-primary">
                                <option value="cash">Bar</option>
                                <option value="bank">Bank</option>
                            </select>
                            <p v-if="financeEntryForm.errors.account" class="mt-1 text-xs text-error">{{ financeEntryForm.errors.account }}</p>
                        </div>
                        <div>
                            <label class="text-xs font-semibold uppercase text-secondary">Titel</label>
                            <input
                                v-model="financeEntryForm.title"
                                class="mt-1 w-full rounded-lg border border-border bg-inputBg px-3 py-2 text-sm text-primary"
                                placeholder="z. B. Hallenmiete"
                                required
                            >
                            <p v-if="financeEntryForm.errors.title" class="mt-1 text-xs text-error">{{ financeEntryForm.errors.title }}</p>
                        </div>
                        <div>
                            <label class="text-xs font-semibold uppercase text-secondary">Kategorie</label>
                            <input
                                v-model="financeEntryForm.category"
                                class="mt-1 w-full rounded-lg border border-border bg-inputBg px-3 py-2 text-sm text-primary"
                                placeholder="z. B. Miete, Zuschuss, Material"
                            >
                        </div>
                        <div>
                            <label class="text-xs font-semibold uppercase text-secondary">Betrag EUR</label>
                            <input
                                v-model="financeEntryForm.amount"
                                type="number"
                                min="0.01"
                                step="0.01"
                                class="mt-1 w-full rounded-lg border border-border bg-inputBg px-3 py-2 text-sm text-primary"
                                required
                            >
                            <p v-if="financeEntryForm.errors.amount" class="mt-1 text-xs text-error">{{ financeEntryForm.errors.amount }}</p>
                        </div>
                        <div>
                            <label class="text-xs font-semibold uppercase text-secondary">Datum</label>
                            <input
                                v-model="financeEntryForm.booked_on"
                                type="date"
                                class="mt-1 w-full rounded-lg border border-border bg-inputBg px-3 py-2 text-sm text-primary"
                            >
                        </div>
                        <div class="sm:col-span-2">
                            <label class="text-xs font-semibold uppercase text-secondary">Referenz</label>
                            <input
                                v-model="financeEntryForm.reference"
                                class="mt-1 w-full rounded-lg border border-border bg-inputBg px-3 py-2 text-sm text-primary"
                                placeholder="Belegnummer, Kontoauszug, Notiz"
                            >
                        </div>
                        <div class="sm:col-span-2">
                            <label class="text-xs font-semibold uppercase text-secondary">Beschreibung</label>
                            <textarea
                                v-model="financeEntryForm.description"
                                rows="3"
                                class="mt-1 w-full rounded-lg border border-border bg-inputBg px-3 py-2 text-sm text-primary"
                                placeholder="Optional"
                            />
                        </div>
                    </div>

                    <div class="flex justify-end gap-2">
                        <button type="button" class="rounded-lg border border-border px-4 py-2 text-sm font-semibold text-primary" @click="showFinanceEntryModal = false">
                            Abbrechen
                        </button>
                        <button class="rounded-lg bg-buttonPrimary px-4 py-2 text-sm font-semibold text-buttonTextPrimary" :disabled="financeEntryForm.processing">
                            Speichern
                        </button>
                    </div>
                </form>
            </div>
        </Modal>

        <Modal :show="showBankImportModal" max-width="2xl" @close="showBankImportModal = false">
            <div class="p-2">
                <h2 class="text-xl font-bold text-primary">Bankumsätze importieren</h2>
                <p class="mt-1 text-sm text-secondary">
                    Lade eine CSV aus dem Online-Banking hoch. Erkannt werden typische Spalten wie Datum, Betrag, Auftraggeber, IBAN und Verwendungszweck.
                </p>

                <form class="mt-5 space-y-4" @submit.prevent="importBankTransactions">
                    <div>
                        <label class="text-xs font-semibold uppercase text-secondary">CSV-Datei</label>
                        <input
                            type="file"
                            accept=".csv,.txt"
                            required
                            class="mt-1 block w-full rounded-lg border border-border bg-inputBg px-3 py-2 text-sm text-primary"
                            @input="bankImportForm.file = $event.target.files[0]"
                        >
                        <p v-if="bankImportForm.errors.file" class="mt-2 text-sm text-error">{{ bankImportForm.errors.file }}</p>
                    </div>

                    <p class="rounded-lg border border-border bg-bg p-3 text-xs text-secondary">
                        Sichere Treffer mit Rechnungsnummer und Betrag werden automatisch als bezahlt markiert. Vorschläge kannst du danach bestätigen.
                    </p>

                    <div class="flex justify-end gap-2">
                        <button type="button" class="rounded-lg border border-border px-4 py-2 text-sm font-semibold text-primary" @click="showBankImportModal = false">
                            Abbrechen
                        </button>
                        <button class="rounded-lg bg-buttonPrimary px-4 py-2 text-sm font-semibold text-buttonTextPrimary" :disabled="bankImportForm.processing">
                            Import starten
                        </button>
                    </div>
                </form>
            </div>
        </Modal>
    </div>
</template>
