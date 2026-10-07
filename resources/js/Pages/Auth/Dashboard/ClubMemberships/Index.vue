<script setup>
import AppLayout from '@/Components/Auth/Layouts/AppLayout.vue'
import ClubWorkspaceNav from '@/Components/Auth/ClubWorkspaceNav.vue'
import ClubSepaBatches from '@/Components/ClubMemberships/ClubSepaBatches.vue'
import ClubMetadataSubjectEditor from '@/Components/Clubs/ClubMetadataSubjectEditor.vue'
import ClubAccessManager from '@/Components/ClubMemberships/ClubAccessManager.vue'
import ClubMembershipProspects from '@/Components/ClubMemberships/ClubMembershipProspects.vue'
import feeTranslations from '@/i18n/sepaFeeLocalization.json'
import contributionPolicyLinkTranslations from '@/i18n/contributionPolicyLinkLocalization.json'
import prospectTranslations from '@/i18n/clubMembershipProspectsLocalization.json'
import Modal from '@/Components/Modal.vue'
import SearchableSelect from '@/Components/SearchableSelect.vue'
import SavedViewBar from '@/Components/SavedViewBar.vue'
import { Head, Link, router, useForm, usePage } from '@inertiajs/vue3'
import { computed, onMounted, ref, watch } from 'vue'
import { confirmDialog, promptDialog } from '@/services/dialogService'
import { useI18n } from 'vue-i18n'
import { useSavedViews } from '@/composables/useSavedViews'

defineOptions({ layout: AppLayout })

const props = defineProps({
    clubs: { type: Array, default: () => [] },
    membershipStatuses: { type: Array, default: () => ['active', 'non_member', 'pending', 'former'] },
    contributionIntervals: { type: Array, default: () => ['none', 'monthly', 'quarterly', 'yearly', 'once'] },
    contributionRuleTypes: { type: Array, default: () => [{ value: 'standard', label: 'Standardbeitrag' }] },
    contributionDiscountOperators: { type: Array, default: () => [{ value: 'percent', label: 'Prozentualer Rabatt' }] },
    contributionProrationPolicies: { type: Array, default: () => [
        { value: 'prorate_days', label: 'Anteilig nach Tagen' },
        { value: 'full_amount', label: 'Voller Betrag' },
        { value: 'next_period', label: 'Erst ab nächstem Zeitraum' },
    ] },
    invoiceStatusOptions: { type: Array, default: () => [
        { value: 'open', label: 'Offen' },
        { value: 'paid', label: 'Bezahlt' },
        { value: 'overdue', label: 'Überfällig' },
        { value: 'cancelled', label: 'Storniert' },
        { value: 'waived', label: 'Erlassen' },
    ] },
    clubRoles: { type: Array, default: () => [{ value: 'member', label: 'Mitglied' }] },
    teamRoles: { type: Array, default: () => ['Coach', 'Captain', 'Player'] },
})

const page = usePage()
const { t, locale } = useI18n()
const feeText = key => (feeTranslations[locale.value] || feeTranslations.en)[key]
const contributionPolicyText = key => (contributionPolicyLinkTranslations[locale.value] || contributionPolicyLinkTranslations.en)[key]
const prospectText = key => (prospectTranslations[locale.value] || prospectTranslations.de)[key]
const tx = (key, fallback = key, values = {}) => {
    const translated = t(key, values)
    return translated === key ? fallback : translated
}
const initialQuery = new URLSearchParams(String(page.url || '').split('?')[1] || '')
const requestedClubId = Number(initialQuery.get('club_id') || 0) || null
const requestedTab = initialQuery.get('tab')
const selectedClubId = ref(props.clubs.some((club) => club.id === requestedClubId) ? requestedClubId : (props.clubs[0]?.id || null))
const activeTab = ref(['members', 'prospects', 'requests', 'rules', 'schedule', 'invoices', 'payments', 'surveys', 'audit', 'exports'].includes(requestedTab) ? requestedTab : 'members')
const rulesWizardStep = ref(0)
const membershipTypeMode = ref('choose')
const memberSearch = ref('')
const memberStatusFilter = ref('all')
const memberEndFilter = ref('all')
const invoiceStatusFilter = ref('all')
const transactionStatusFilter = ref('all')
const memberSavedViews = useSavedViews('members', t('search.saved_views_error'))
const invoiceSavedViews = useSavedViews('invoices', t('search.saved_views_error'))
const editingMemberId = ref(null)
const accessMember = ref(null)
const invoiceMemberId = ref(null)
const paymentInvoice = ref(null)
const processingInvoiceIds = ref(new Set())
const invoiceActionFeedback = ref('')
const invoiceActionError = ref('')
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
const documentSearch = ref('')
const documentTypeFilter = ref('all')
const expandedDocumentId = ref(null)
const processingJoinRequestIds = ref(new Set())
const processingClubRequestIds = ref(new Set())
const savingMemberIds = ref(new Set())
const membershipActionFeedback = ref('')
const membershipActionError = ref('')
const duplicateMergeMember = ref(null)
const duplicateMergeForm = useForm({
    resolution: 'keep_registered',
    confirm_email: '',
})
const timelineMember = ref(null)
const timelineForm = useForm({
    type: 'honor',
    title: '',
    description: '',
    occurred_on: new Date().toISOString().slice(0, 10),
})
const externalMemberEdit = ref(null)
const externalMemberForm = useForm({
    name: '',
    email: '',
    phone: '',
    country: '',
    street: '',
    house_number: '',
    postal_code: '',
    city: '',
    role: 'member',
    membership_status: 'active',
    club_membership_type_id: '',
})
const createEmailMemberRow = () => ({
    name: '',
    email: '',
    phone: '',
    country: '',
    street: '',
    house_number: '',
    postal_code: '',
    city: '',
    role: 'member',
    membership_status: 'active',
    club_membership_type_id: '',
    family_group_key: '',
    contribution_payer_user_id: '',
    member_number: '',
    athlete_license_number: '',
    athlete_license_valid_until: '',
    contribution_amount: '',
    contribution_interval: 'none',
    payment_method: 'bank_transfer',
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
    invitation_expires_at: '',
    members: [createEmailMemberRow()],
})
const importForm = useForm({
    file: null,
    send_invitation: false,
})
const importPreview = ref(null)
const importPreviewLoading = ref(false)
const importPreviewError = ref('')
const importSubmitting = ref(false)
const importOutcome = ref(null)
const surveys = ref([])
const surveysLoading = ref(false)
const surveysError = ref('')
const surveySavingId = ref(null)
const surveyForm = ref({
    question: '',
    description: '',
    quorum: '',
    closes_at: '',
    options: ['', ''],
})
const bankImportForm = useForm({
    file: null,
})
const bankImportPreview = ref(null)
const bankImportPreviewLoading = ref(false)
const bankImportSubmitting = ref(false)
const bankImportFeedback = ref('')
const financeActionFeedback = ref('')
const financeActionError = ref('')
const financeSettingsSaving = ref(false)
const processingBankTransactionIds = ref(new Set())
const membershipTypeForm = useForm({
    name: '',
    slug: '',
    description: '',
    is_public: true,
    is_active: true,
    sort_order: 0,
    application_fields: {},
})
const editingMembershipTypeId = ref(null)
const contributionRuleForm = useForm({
    club_membership_type_id: '',
    club_policy_document_id: '',
    name: '',
    valid_from: new Date().toISOString().slice(0, 10),
    valid_until: '',
    billing_interval: 'monthly',
    proration_policy: 'prorate_days',
    amount: '',
    age_min: '',
    age_max: '',
    factor_key: 'standard',
    factor_operator: '',
    factor_value: '',
    tax_account: '',
    accounting_account: '',
    is_active: true,
    notes: '',
})
const editingContributionRuleId = ref(null)
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

const financeExpenseCategories = [
    'Miete & Hallenkosten',
    'Material & Ausrüstung',
    'Trikots & Kleidung',
    'Trainerhonorare',
    'Schiedsrichter & Gebühren',
    'Verbandsbeiträge',
    'Versicherungen',
    'Reisekosten & Fahrtkosten',
    'Verpflegung',
    'Turniere & Wettkämpfe',
    'Lizenzen & Software',
    'Marketing & Werbung',
    'Büro & Verwaltung',
    'Bankgebühren',
    'Steuern & Abgaben',
    'Reparatur & Wartung',
    'Reinigung',
    'Energie & Nebenkosten',
    'Telefon & Internet',
    'Fortbildung',
    'Veranstaltungskosten',
    'Sonstige Ausgabe',
]

const financeIncomeCategories = [
    'Mitgliedsbeiträge',
    'Aufnahmegebühren',
    'Spenden',
    'Sponsoring',
    'Zuschüsse & Fördermittel',
    'Kursgebühren',
    'Event-Einnahmen',
    'Ticketverkauf',
    'Merchandise',
    'Vermietung',
    'Rückerstattung',
    'Zinsen',
    'Sonstige Einnahme',
]

const fieldModeOptions = [
    { value: 'off', label: 'Aus' },
    { value: 'optional', label: 'Optional' },
    { value: 'required', label: 'Pflicht' },
]

const createMembershipDocumentRow = () => ({
    id: `doc-${Date.now()}-${Math.random().toString(36).slice(2, 8)}`,
    membership_type_id: null,
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
const contributionPolicyDocuments = computed(() => selectedClub.value?.contribution_policy_documents || [])
const applicationFieldDefinitions = computed(() => selectedClub.value?.membership_application_fields || [])
const capabilities = computed(() => selectedClub.value?.capabilities || {})
const canOpenEmailMembers = computed(() => capabilities.value.external_members !== false || capabilities.value.member_invitations !== false)
const members = computed(() => selectedClub.value?.members || [])
const externalMembers = computed(() => selectedClub.value?.external_members || [])
const filteredExternalMembers = computed(() => {
    const search = memberSearch.value.trim().toLowerCase()

    return externalMembers.value.filter((member) => {
        if (memberStatusFilter.value !== 'all' && member.membership_status !== memberStatusFilter.value) return false
        if (memberEndFilter.value !== 'all') {
            const days = daysUntil(member.membership_ends_on)
            if (memberEndFilter.value === 'ending_30' && !(days !== null && days >= 0 && days <= 30)) return false
            if (memberEndFilter.value === 'ending_60' && !(days !== null && days >= 0 && days <= 60)) return false
            if (memberEndFilter.value === 'expired' && !(days !== null && days < 0)) return false
            if (memberEndFilter.value === 'no_end' && days !== null) return false
        }
        if (!search) return true

        return [member.name, member.email, member.phone, member.postal_code, member.city, member.member_number, member.athlete_license_number]
            .filter(Boolean)
            .join(' ')
            .toLowerCase()
            .includes(search)
    })
})
const invoices = computed(() => selectedClub.value?.invoices || [])
const payments = computed(() => selectedClub.value?.payments || [])
const financeEntries = computed(() => selectedClub.value?.finance_entries || [])
const bankTransactions = computed(() => selectedClub.value?.bank_transactions || [])
const auditLogs = computed(() => selectedClub.value?.audit_logs || [])

const activeMembersCount = computed(() => members.value.filter((member) => formFor(member).membership_status === 'active').length)
const openInvoices = computed(() => invoices.value.filter((invoice) => ['open', 'overdue'].includes(invoice.status)))
const openInvoiceTotal = computed(() => openInvoices.value.reduce((sum, invoice) => sum + Number(invoice.outstanding_amount ?? invoice.amount ?? 0), 0))
const invoiceSummary = computed(() => selectedClub.value?.invoice_summary || {
    total_count: invoices.value.length,
    open_count: openInvoices.value.length,
    paid_count: invoices.value.filter((invoice) => invoice.status === 'paid').length,
    overdue_count: invoices.value.filter((invoice) => invoice.status === 'overdue').length,
    cancelled_count: invoices.value.filter((invoice) => invoice.status === 'cancelled').length,
    open_amount: openInvoiceTotal.value,
    paid_amount: invoices.value.filter((invoice) => invoice.status === 'paid').reduce((sum, invoice) => sum + Number(invoice.amount || 0), 0),
    overdue_amount: invoices.value.filter((invoice) => invoice.status === 'overdue').reduce((sum, invoice) => sum + Number(invoice.amount || 0), 0),
    cancelled_amount: invoices.value.filter((invoice) => invoice.status === 'cancelled').reduce((sum, invoice) => sum + Number(invoice.amount || 0), 0),
})
const paymentAmount = (payment) => Number(payment.amount || 0)
const settledPaymentAmount = (payment) => (!payment.status || payment.status === 'paid') ? paymentAmount(payment) : 0
const fallbackCashBalance = computed(() => payments.value
    .filter((payment) => payment.method === 'cash')
    .reduce((sum, payment) => sum + settledPaymentAmount(payment), 0))
const fallbackBankBalance = computed(() => payments.value
    .filter((payment) => ['bank_transfer', 'sepa_debit'].includes(payment.method))
    .reduce((sum, payment) => sum + settledPaymentAmount(payment), 0))
const fallbackTotalBalance = computed(() => payments.value.reduce((sum, payment) => sum + settledPaymentAmount(payment), 0))
const cashBalance = computed(() => Number(selectedClub.value?.cash_balance ?? fallbackCashBalance.value))
const bankBalance = computed(() => Number(selectedClub.value?.bank_balance ?? fallbackBankBalance.value))
const totalBalance = computed(() => Number(selectedClub.value?.total_balance ?? fallbackTotalBalance.value))
const unassignedBalance = computed(() => Number(selectedClub.value?.unassigned_balance ?? Math.max(0, totalBalance.value - cashBalance.value - bankBalance.value)))
const financeEntryAmount = (entry) => Number(entry.amount || 0)
const currentYear = new Date().getFullYear()
const isInCurrentYear = (value) => {
    if (!value) return false

    const raw = String(value).trim()
    const datePrefix = raw.match(/^(\d{4})-/)

    if (datePrefix) return Number(datePrefix[1]) === currentYear

    const date = new Date(value)

    return !Number.isNaN(date.getTime()) && date.getFullYear() === currentYear
}
const incomePeriodTotal = computed(() => Number(selectedClub.value?.income_period_total ?? (
    payments.value.filter((payment) => isInCurrentYear(payment.paid_at || payment.created_at)).reduce((sum, payment) => sum + paymentAmount(payment), 0)
    + financeEntries.value.filter((entry) => entry.type === 'income' && isInCurrentYear(entry.booked_on)).reduce((sum, entry) => sum + financeEntryAmount(entry), 0)
)))
const expensePeriodTotal = computed(() => Number(selectedClub.value?.expense_period_total ?? (
    financeEntries.value.filter((entry) => entry.type === 'expense' && isInCurrentYear(entry.booked_on)).reduce((sum, entry) => sum + financeEntryAmount(entry), 0)
)))
const financePeriodLabel = computed(() => selectedClub.value?.finance_period_label || 'Dieses Jahr')
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

const isoDate = (value) => {
    if (!value) return ''

    const date = value instanceof Date ? value : dateOnly(value)
    if (!date) return ''

    return new Date(date.getTime() - date.getTimezoneOffset() * 60000).toISOString().slice(0, 10)
}

const addPeriod = (date, interval) => {
    const next = new Date(date)

    if (interval === 'monthly') next.setMonth(next.getMonth() + 1)
    else if (interval === 'quarterly') next.setMonth(next.getMonth() + 3)
    else if (interval === 'four_monthly') next.setMonth(next.getMonth() + 4)
    else if (interval === 'semi_yearly') next.setMonth(next.getMonth() + 6)
    else if (interval === 'yearly') next.setFullYear(next.getFullYear() + 1)
    else return new Date(date)

    next.setDate(next.getDate() - 1)

    return next
}

const inclusiveDays = (start, end) => Math.max(1, Math.round((end.getTime() - start.getTime()) / 86400000) + 1)

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

const paymentDueLabel = (days) => {
    if (days === null) return tx('club_memberships.workspace.no_due_date', 'Kein Datum')
    if (days < 0) return tx('club_memberships.workspace.overdue_days', 'überfällig seit {days} Tagen', { days: Math.abs(days) })
    if (days === 0) return tx('club_memberships.workspace.due_today', 'heute fällig')
    if (days <= 14) return tx('club_memberships.workspace.due_in_days', 'in {days} Tagen fällig', { days })

    return tx('club_memberships.workspace.due_later', 'später fällig')
}

const paymentDueClass = (days) => {
    if (days === null) return 'bg-secondary/10 text-secondary'
    if (days < 0) return 'bg-error/10 text-error'
    if (days <= 14) return 'bg-warning/10 text-warning'

    return 'bg-success/10 text-success'
}

const paymentSchedule = computed(() => members.value
    .map((member) => {
        const form = formFor(member)
        const days = daysUntil(form.contribution_next_invoice_on)

        return {
            member,
            form,
            days,
            sort: days === null ? 999999 : days,
        }
    })
    .filter(({ form }) => form.membership_status === 'active' && form.contribution_interval !== 'none')
    .sort((a, b) => a.sort - b.sort || String(a.member.name || '').localeCompare(String(b.member.name || ''))))

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
            member.phone,
            member.postal_code,
            member.city,
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

const applyMemberSavedView = (view) => {
    const configuration = view.configuration || {}
    if (props.clubs.some((club) => Number(club.id) === Number(configuration.filters?.club_id))) {
        selectedClubId.value = Number(configuration.filters.club_id)
    }
    memberSearch.value = configuration.query || ''
    memberStatusFilter.value = configuration.filters?.status || 'all'
    memberEndFilter.value = configuration.filters?.membership_end || 'all'
}

const saveMemberView = async () => {
    const name = await promptDialog({
        title: tx('search.save_view', 'Ansicht speichern'),
        inputLabel: tx('search.saved_view_name', 'Name der Ansicht'),
        required: true,
        minLength: 1,
    })
    if (!name?.trim()) return
    await memberSavedViews.save(name.trim(), {
        query: memberSearch.value.trim(),
        filters: {
            club_id: selectedClubId.value,
            status: memberStatusFilter.value,
            membership_end: memberEndFilter.value,
        },
    })
}

const applyInvoiceSavedView = (view) => {
    const configuration = view.configuration || {}
    if (props.clubs.some((club) => Number(club.id) === Number(configuration.filters?.club_id))) {
        selectedClubId.value = Number(configuration.filters.club_id)
    }
    invoiceStatusFilter.value = configuration.filters?.status || 'all'
}

const saveInvoiceView = async () => {
    const name = await promptDialog({
        title: tx('search.save_view', 'Ansicht speichern'),
        inputLabel: tx('search.saved_view_name', 'Name der Ansicht'),
        required: true,
        minLength: 1,
    })
    if (!name?.trim()) return
    await invoiceSavedViews.save(name.trim(), {
        filters: {
            status: invoiceStatusFilter.value,
            club_id: selectedClubId.value,
        },
    })
}

onMounted(() => {
    void memberSavedViews.load()
    void invoiceSavedViews.load()
})

const tabs = computed(() => [
    { key: 'members', label: tx('auto.Mitglieder', 'Mitglieder'), count: members.value.length + externalMembers.value.length, icon: 'las la-users' },
    { key: 'prospects', label: prospectText('tab'), count: null, icon: 'las la-user-clock' },
    { key: 'requests', label: tx('auto.Anfragen', 'Anfragen'), count: pendingRequests.value.length + clubRequests.value.length, icon: 'las la-user-plus' },
    { key: 'rules', label: tx('auto.Beitragsregeln', 'Beitragsregeln'), count: contributionRules.value.length, icon: 'las la-sliders-h' },
    { key: 'schedule', label: tx('club_memberships.workspace.payment_schedule', 'Fälligkeiten'), count: paymentSchedule.value.length, icon: 'las la-calendar-check' },
    { key: 'invoices', label: tx('auto.Rechnungen', 'Rechnungen'), count: openInvoices.value.length, icon: 'las la-file-invoice' },
    { key: 'payments', label: tx('auto.Finanzen', 'Finanzen'), count: payments.value.length + financeEntries.value.length, icon: 'las la-university' },
    { key: 'surveys', label: tx('auto.Umfragen', 'Umfragen'), count: surveys.value.length, icon: 'las la-poll' },
    { key: 'audit', label: tx('club_memberships.workspace.audit_log', 'Audit'), count: auditLogs.value.length, icon: 'las la-history' },
    { key: 'exports', label: 'SEPA & DATEV', count: sepaReadyMembersCount.value, icon: 'las la-file-export' },
])

const surveyErrorMessage = (error, fallback) => {
    const validationErrors = error.response?.data?.errors
    const firstValidationError = validationErrors ? Object.values(validationErrors).flat()[0] : null
    return firstValidationError || error.response?.data?.message || fallback
}

const loadSurveys = async () => {
    if (!selectedClub.value || surveysLoading.value) return
    surveysLoading.value = true
    surveysError.value = ''

    try {
        const response = await window.axios.get(route('api.v1.clubs.surveys.index', selectedClub.value.id), {
            headers: { Accept: 'application/json' },
        })
        surveys.value = response.data?.data || []
    } catch (error) {
        surveysError.value = surveyErrorMessage(error, tx('club_memberships.surveys.load_failed', 'Die Umfragen konnten nicht geladen werden.'))
    } finally {
        surveysLoading.value = false
    }
}

const addSurveyOption = () => {
    if (surveyForm.value.options.length < 6) surveyForm.value.options.push('')
}

const removeSurveyOption = (index) => {
    if (surveyForm.value.options.length > 2) surveyForm.value.options.splice(index, 1)
}

const createSurvey = async () => {
    if (!selectedClub.value || surveySavingId.value) return
    surveySavingId.value = 'create'
    surveysError.value = ''

    try {
        await window.axios.post(route('api.v1.clubs.surveys.store', selectedClub.value.id), {
            question: surveyForm.value.question,
            description: surveyForm.value.description || null,
            audience_type: 'all_members',
            quorum: surveyForm.value.quorum || null,
            closes_at: surveyForm.value.closes_at || null,
            options: surveyForm.value.options.map((option) => option.trim()).filter(Boolean),
        }, { headers: { Accept: 'application/json' } })
        surveyForm.value = { question: '', description: '', quorum: '', closes_at: '', options: ['', ''] }
        await loadSurveys()
    } catch (error) {
        surveysError.value = surveyErrorMessage(error, tx('club_memberships.surveys.create_failed', 'Die Umfrage konnte nicht erstellt werden.'))
    } finally {
        surveySavingId.value = null
    }
}

const voteSurvey = async (survey, optionId) => {
    if (!selectedClub.value || surveySavingId.value) return
    surveySavingId.value = `vote-${survey.id}`
    surveysError.value = ''
    try {
        await window.axios.post(route('api.v1.clubs.surveys.vote', [selectedClub.value.id, survey.id]), { option_id: optionId }, { headers: { Accept: 'application/json' } })
        await loadSurveys()
    } catch (error) {
        surveysError.value = surveyErrorMessage(error, tx('club_memberships.surveys.vote_failed', 'Die Stimme konnte nicht gespeichert werden.'))
    } finally {
        surveySavingId.value = null
    }
}

const closeSurvey = async (survey) => {
    if (!selectedClub.value || surveySavingId.value) return
    surveySavingId.value = `close-${survey.id}`
    surveysError.value = ''
    try {
        await window.axios.post(route('api.v1.clubs.surveys.close', [selectedClub.value.id, survey.id]), {}, { headers: { Accept: 'application/json' } })
        await loadSurveys()
    } catch (error) {
        surveysError.value = surveyErrorMessage(error, tx('club_memberships.surveys.close_failed', 'Die Umfrage konnte nicht geschlossen werden.'))
    } finally {
        surveySavingId.value = null
    }
}

watch([activeTab, selectedClubId], ([tab]) => {
    if (tab === 'surveys') loadSurveys()
}, { immediate: true })

const rulesWizardSteps = computed(() => [
    {
        key: 'types',
        label: tx('auto.Mitgliedschaftstyp', 'Mitgliedschaftstypen'),
        hint: tx('club_memberships.workspace.wizard_types_hint', 'Lege fest, welche Arten von Mitgliedschaften Interessenten auswählen können.'),
    },
    {
        key: 'application',
        label: tx('club_memberships.workspace.application_fields', 'Mitgliedsantrag-Felder'),
        hint: tx('club_memberships.workspace.wizard_application_hint', 'Bestimme, welche Informationen Interessenten im Antrag angeben müssen.'),
    },
    {
        key: 'contributions',
        label: tx('auto.Beitragsregeln', 'Beitragsregeln'),
        hint: tx('club_memberships.workspace.wizard_contributions_hint', 'Definiere Beitrag, Intervall und optionale Alters- oder Rabattregeln.'),
    },
    {
        key: 'access',
        label: tx('club_memberships.workspace.member_requests_enabled', 'Anfragezugang'),
        hint: tx('club_memberships.workspace.wizard_access_hint', 'Entscheide, ob Interessenten online Anfragen stellen und Pausen beantragen können.'),
    },
    {
        key: 'payment',
        label: tx('club_memberships.workspace.allowed_payment_methods', 'Zahlungsarten'),
        hint: tx('club_memberships.workspace.wizard_payment_hint', 'Lege fest, wie Beiträge bezahlt werden können.'),
    },
    {
        key: 'documents',
        label: tx('club_memberships.workspace.documents_confirmations', 'Dokumente'),
        hint: tx('club_memberships.workspace.wizard_documents_hint', 'Verknüpfe wichtige Dokumente und Bestätigungen.'),
    },
    {
        key: 'summary',
        label: tx('club_memberships.workspace.summary', 'Zusammenfassung'),
        hint: tx('club_memberships.workspace.summary_hint', 'Prüfe deine Angaben ein letztes Mal und schließe die Einrichtung ab.'),
    },
])

const rulesWizardNext = () => {
    if (rulesWizardStep.value < rulesWizardSteps.value.length - 1) rulesWizardStep.value += 1
}

const rulesWizardBack = () => {
    if (rulesWizardStep.value > 0) rulesWizardStep.value -= 1
}

const cancelRulesWizard = () => {
    cancelMembershipTypeEdit()
    cancelContributionRuleEdit()
    rulesWizardStep.value = 0
    activeTab.value = 'overview'
}

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

const invoiceStatusLabel = (status) => props.invoiceStatusOptions.find((option) => option.value === status)?.label || status || '-'
const invoiceStatusClass = (status) => ({
    open: 'bg-air-blue/15 text-air-blue',
    paid: 'bg-air-green/15 text-air-green',
    overdue: 'bg-error/15 text-error',
    cancelled: 'bg-muted text-secondary',
    waived: 'bg-air-green/10 text-air-green',
}[status] || 'bg-muted text-secondary')

const auditDetail = (entry) => {
    const data = entry.data || {}

    if (data.old_status && data.new_status) {
        return `${invoiceStatusLabel(data.old_status)} -> ${invoiceStatusLabel(data.new_status)}`
    }

    if (data.invoice_number && data.amount) {
        return `${data.invoice_number} · ${formatMoney(data.amount)}`
    }

    if (data.invoice_number) {
        return data.invoice_number
    }

    if (data.member_name) {
        return data.member_name
    }

    return '-'
}

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
    const fieldLabelByKey = {
        first_name: 'first_name',
        last_name: 'last_name',
        birth_date: 'Geburtsdatum',
        gender: 'Geschlecht',
        email: 'Email',
        phone: 'Telefon',
        country: 'country',
        street: 'street',
        house_number: 'house_number',
        postal_code: 'postal_code',
        city: 'city',
        state: 'state',
        guardian_name: 'Erziehungsberechtigte',
        guardian_email: 'E-Mail des Erziehungsberechtigten',
        guardian_phone: 'Telefon',
        emergency_contact_name: 'Name',
        emergency_contact_phone: 'Telefon',
        athlete_license_number: 'license_number',
        sepa_iban: 'sepa_iban',
        sepa_bic: 'sepa_bic',
    }[key]

    if (fieldLabelByKey) {
        return tx(fieldLabelByKey, key)
    }

    if (field?.label) {
        const label = String(field.label).trim()
        if (label && /^[a-z0-9_]+$/.test(label)) {
            return tx(label, label) || label
        }

        const normalizedLabel = label
            .toLowerCase()
            .replace(/[^a-z0-9]+/g, '_')
            .replace(/^_+|_+$/g, '')
            .replace(/_+/g, '_')

        if (normalizedLabel && /^[a-z0-9_]+$/.test(normalizedLabel)) {
            return tx(normalizedLabel, label)
        }

        return label
    }

    return tx(key, key)
}

const requestDataValue = (key, value) => {
    const field = selectedClub.value?.membership_application_fields?.find((candidate) => candidate.key === key)

    if ((field?.type || '') === 'checkbox') return value ? 'Ja' : 'Nein'
    if (field?.options) return field.options.find((option) => option.value === value)?.label || value

    return value
}

const membershipDocumentTypes = computed(() => {
    if (!selectedClub.value) return []
    const types = membershipSettingsFor(selectedClub.value).membership_application_document_types || selectedClub.value.membership_application_document_types || []
    const language = ['de', 'en', 'fr', 'ar'].includes(locale.value) ? locale.value : 'de'

    return types.map((type) => ({
        ...type,
        label: type.labels?.[language] || type.labels?.de || type.value,
    }))
})
const documentTypeLabel = (type) => membershipDocumentTypes.value.find((option) => option.value === type)?.label || type

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

const localeCode = computed(() => ({ ar: 'ar-EG', fr: 'fr-FR', en: 'en-US', de: 'de-DE' })[locale.value] || 'de-DE')
const formatMoney = (value) => new Intl.NumberFormat(localeCode.value, {
    style: 'currency',
    currency: 'EUR',
}).format(Number(value || 0))

const formatDate = (value) => {
    if (!value) return '-'
    return new Intl.DateTimeFormat(localeCode.value, { day: '2-digit', month: '2-digit', year: 'numeric' }).format(new Date(value))
}

const formatDateTime = (value) => {
    if (!value) return '-'
    return new Intl.DateTimeFormat(localeCode.value, {
        day: '2-digit',
        month: '2-digit',
        year: 'numeric',
        hour: '2-digit',
        minute: '2-digit',
    }).format(new Date(value))
}

const financeTypeLabel = (type) => tx(`club_memberships.finance.type.${type}`, type)

const financeAccountLabel = (account) => tx(`club_memberships.finance.account.${account}`, account)

const financeEntryClasses = (entry) => entry.type === 'income'
    ? 'border-air-green/30 bg-air-green/5 text-air-green'
    : 'border-error/30 bg-error/5 text-error'

const normalizedFinanceCategory = (value) => String(value || '').trim().toLowerCase()
const financeCategoryBaseList = computed(() => financeEntryForm.type === 'income' ? financeIncomeCategories : financeExpenseCategories)
const financeCategoryOptions = computed(() => {
    const selected = String(financeEntryForm.category || '').trim()
    const base = financeCategoryBaseList.value
    const categories = selected && !base.some((category) => normalizedFinanceCategory(category) === normalizedFinanceCategory(selected))
        ? [selected, ...base]
        : base

    return categories.map((category) => ({ name: category }))
})

const syncFinanceEntryCategoryForType = () => {
    const selected = String(financeEntryForm.category || '').trim()

    if (!selected) return
    if (financeCategoryBaseList.value.some((category) => normalizedFinanceCategory(category) === normalizedFinanceCategory(selected))) return

    financeEntryForm.category = ''
}

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

const saveFinanceEntry = async () => {
    if (!selectedClub.value || financeEntryForm.processing) return
    financeEntryForm.processing = true
    financeEntryForm.clearErrors()
    financeActionFeedback.value = ''
    financeActionError.value = ''
    try {
        const editingId = editingFinanceEntryId.value
        const response = editingId
            ? await window.axios.put(
                route('api.v1.clubs.finance-entries.update', [selectedClub.value.id, editingId]),
                financeEntryForm.data(),
                { headers: { Accept: 'application/json' } },
            )
            : await window.axios.post(
                route('api.v1.clubs.finance-entries.store', selectedClub.value.id),
                financeEntryForm.data(),
                { headers: { Accept: 'application/json' } },
            )
        applyMembershipManagement(response.data?.data)
        financeActionFeedback.value = response.data?.message || tx('club_memberships.workspace.finance_saved', 'Kassenbucheintrag wurde gespeichert.')
        showFinanceEntryModal.value = false
        resetFinanceEntryForm()
    } catch (error) {
        const errors = error.response?.data?.errors || {}
        Object.entries(errors).forEach(([field, messages]) => financeEntryForm.setError(field, Array.isArray(messages) ? messages[0] : messages))
        financeActionError.value = invoiceErrorMessage(error, tx('club_memberships.workspace.finance_save_failed', 'Der Kassenbucheintrag konnte nicht gespeichert werden.'))
    } finally {
        financeEntryForm.processing = false
    }
}

const formFor = (member) => {
    memberForms.value[member.id] ??= {
        role: member.pivot.role || 'member',
        roles: Array.isArray(member.pivot.roles) && member.pivot.roles.length
            ? [...member.pivot.roles]
            : [member.pivot.role || 'member'],
        membership_status: member.pivot.membership_status || 'non_member',
        club_membership_type_id: member.pivot.club_membership_type_id || '',
        family_group_key: member.pivot.family_group_key || '',
        contribution_payer_user_id: member.pivot.contribution_payer_user_id || '',
        member_number: member.pivot.member_number || '',
        athlete_license_number: member.athlete_license_number || '',
        athlete_license_valid_until: member.athlete_license_valid_until || '',
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

const saveSepaSettings = async () => {
    if (!selectedClub.value || financeSettingsSaving.value) return
    financeSettingsSaving.value = true
    financeActionFeedback.value = ''
    financeActionError.value = ''
    try {
        const response = await window.axios.put(
            route('api.v1.clubs.membership.sepa-settings.update', selectedClub.value.id),
            sepaSettingsFor(selectedClub.value),
            { headers: { Accept: 'application/json' } },
        )
        applyMembershipManagement(response.data?.data)
        financeActionFeedback.value = response.data?.message || tx('club_memberships.workspace.sepa_settings_saved', 'SEPA-Einstellungen wurden gespeichert.')
    } catch (error) {
        financeActionError.value = invoiceErrorMessage(error, tx('club_memberships.workspace.sepa_settings_failed', 'Die SEPA-Einstellungen konnten nicht gespeichert werden.'))
    } finally {
        financeSettingsSaving.value = false
    }
}

const datevSettingsFor = (club) => {
    datevSettingsForms.value[club.id] ??= {
        datev_consultant_number: club.datev_consultant_number || '',
        datev_client_number: club.datev_client_number || '',
        datev_revenue_account: club.datev_revenue_account || '2110',
        datev_bank_account: club.datev_bank_account || '1200',
        datev_fee_account: club.datev_fee_account || '',
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

const saveDatevSettings = async () => {
    if (!selectedClub.value || financeSettingsSaving.value) return
    financeSettingsSaving.value = true
    financeActionFeedback.value = ''
    financeActionError.value = ''
    try {
        const response = await window.axios.put(
            route('api.v1.clubs.membership.datev-settings.update', selectedClub.value.id),
            datevSettingsFor(selectedClub.value),
            { headers: { Accept: 'application/json' } },
        )
        applyMembershipManagement(response.data?.data)
        financeActionFeedback.value = response.data?.message || tx('club_memberships.workspace.datev_settings_saved', 'DATEV-Einstellungen wurden gespeichert.')
    } catch (error) {
        financeActionError.value = invoiceErrorMessage(error, tx('club_memberships.workspace.datev_settings_failed', 'Die DATEV-Einstellungen konnten nicht gespeichert werden.'))
    } finally {
        financeSettingsSaving.value = false
    }
}

const membershipSettingsFor = (club) => {
    membershipSettingsForms.value[club.id] ??= {
        membership_requests_enabled: Boolean(club.membership_requests_enabled),
        member_pause_requests_enabled: Boolean(club.member_pause_requests_enabled),
        membership_application_fields: Object.fromEntries((club.membership_application_fields || []).map((field) => [field.key, field.mode || 'off'])),
        membership_payment_methods: [...(club.membership_payment_methods || [])],
        membership_application_document_types: (club.membership_application_document_types || []).map((type) => ({
            value: type.value,
            labels: { ...(type.labels || {}) },
        })),
        membership_application_documents: (club.membership_application_documents || []).map((document) => ({ ...document, file: null })),
    }

    return membershipSettingsForms.value[club.id]
}

const addMembershipDocument = () => {
    const document = createMembershipDocumentRow()
    membershipSettingsFor(selectedClub.value).membership_application_documents.push(document)
    expandedDocumentId.value = document.id
}

const removeMembershipDocument = (index) => {
    membershipSettingsFor(selectedClub.value).membership_application_documents.splice(index, 1)
}

const filteredMembershipDocuments = computed(() => {
    const documents = membershipSettingsFor(selectedClub.value).membership_application_documents || []
    const search = documentSearch.value.trim().toLowerCase()

    return documents
        .map((document, index) => ({ document, index }))
        .filter(({ document }) => {
            const matchesType = documentTypeFilter.value === 'all' || document.type === documentTypeFilter.value
            const haystack = [document.title, document.description, document.file_name].filter(Boolean).join(' ').toLowerCase()
            return matchesType && (!search || haystack.includes(search))
        })
})

const toggleMembershipDocument = (id) => {
    expandedDocumentId.value = expandedDocumentId.value === id ? null : id
}

const addMembershipDocumentType = () => {
    membershipSettingsFor(selectedClub.value).membership_application_document_types.push({
        value: `custom_${Date.now()}`,
        labels: { de: '', en: '', fr: '', ar: '' },
    })
}

const removeMembershipDocumentType = (index) => {
    const types = membershipSettingsFor(selectedClub.value).membership_application_document_types
    if (types[index]?.value && ['privacy', 'statutes', 'rules', 'fees', 'sepa', 'other'].includes(types[index].value)) return
    types.splice(index, 1)
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
    billing_period_start: '',
    billing_period_end: '',
    due_date: '',
    waived: false,
    waiver_reason: '',
})
const invoiceRunForm = useForm({
    run_date: new Date().toISOString().slice(0, 10),
    due_date: new Date().toISOString().slice(0, 10),
    title: 'Mitgliedsbeitrag',
})
const invoiceRunPreview = ref(null)
const invoiceRunPreviewLoading = ref(false)
const paymentForm = useForm({
    amount: '',
    method: 'bank_transfer',
    paid_at: new Date().toISOString().slice(0, 10),
    reference: '',
    notes: '',
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

const applyMembershipManagement = (management) => {
    if (management && selectedClub.value) {
        Object.assign(selectedClub.value, management)
    }
}

const decideClubRequest = async (request, decision) => {
    if (!selectedClub.value || processingClubRequestIds.value.has(request.id)) return

    processingClubRequestIds.value.add(request.id)
    membershipActionFeedback.value = ''
    membershipActionError.value = ''
    try {
        const response = await window.axios.post(
            route(`api.v1.clubs.membership-requests.${decision}`, [selectedClub.value.id, request.id]),
            {},
            { headers: { Accept: 'application/json' } },
        )
        applyMembershipManagement(response.data?.management)
        membershipActionFeedback.value = decision === 'approve'
            ? tx('club_memberships.workspace.request_approved', 'Beitrittsanfrage wurde angenommen.')
            : tx('club_memberships.workspace.request_declined', 'Beitrittsanfrage wurde abgelehnt.')
    } catch (error) {
        membershipActionError.value = error.response?.data?.message
            || tx('club_memberships.workspace.request_decision_failed', 'Die Anfrage konnte nicht bearbeitet werden.')
    } finally {
        processingClubRequestIds.value.delete(request.id)
    }
}

const approveClubRequest = (request) => decideClubRequest(request, 'approve')

const declineClubRequest = (request) => decideClubRequest(request, 'decline')

const requestClubInformation = async (request) => {
    const message = window.prompt(tx('club_memberships.workspace.request_information_prompt', 'Welche Angaben oder Unterlagen fehlen?'))?.trim()
    if (!message || !selectedClub.value || processingClubRequestIds.value.has(request.id)) return

    processingClubRequestIds.value.add(request.id)
    membershipActionFeedback.value = ''
    membershipActionError.value = ''
    try {
        const response = await window.axios.post(
            route('api.v1.clubs.membership-requests.request-information', [selectedClub.value.id, request.id]),
            { message },
            { headers: { Accept: 'application/json' } },
        )
        applyMembershipManagement(response.data?.management)
        membershipActionFeedback.value = tx('club_memberships.workspace.request_information_sent', 'Rückfrage wurde gesendet.')
    } catch (error) {
        membershipActionError.value = error.response?.data?.message
            || tx('club_memberships.workspace.request_decision_failed', 'Die Anfrage konnte nicht bearbeitet werden.')
    } finally {
        processingClubRequestIds.value.delete(request.id)
    }
}

const waitlistClubRequest = async (request) => {
    const enteredNote = window.prompt(tx('club_memberships.workspace.waitlist_note_prompt', 'Optionale Notiz zur Warteliste:'))
    if (enteredNote === null) return
    const reviewNote = enteredNote.trim()
    if (!selectedClub.value || processingClubRequestIds.value.has(request.id)) return

    processingClubRequestIds.value.add(request.id)
    membershipActionFeedback.value = ''
    membershipActionError.value = ''
    try {
        const response = await window.axios.post(
            route('api.v1.clubs.membership-requests.waitlist', [selectedClub.value.id, request.id]),
            { review_note: reviewNote },
            { headers: { Accept: 'application/json' } },
        )
        applyMembershipManagement(response.data?.management)
        membershipActionFeedback.value = tx('club_memberships.workspace.request_waitlisted', 'Anfrage wurde auf die Warteliste gesetzt.')
    } catch (error) {
        membershipActionError.value = error.response?.data?.message
            || tx('club_memberships.workspace.request_decision_failed', 'Die Anfrage konnte nicht bearbeitet werden.')
    } finally {
        processingClubRequestIds.value.delete(request.id)
    }
}

const storeMembershipType = () => {
    const submittedTypeId = editingMembershipTypeId.value
    const submittedTypeName = membershipTypeForm.name.trim()
    const options = {
        preserveScroll: true,
        onSuccess: () => {
            const savedType = submittedTypeId
                ? membershipTypes.value.find((type) => type.id === submittedTypeId)
                : [...membershipTypes.value].reverse().find((type) => type.name === submittedTypeName)

            if (savedType) {
                editMembershipType(savedType)
                return
            }

            editingMembershipTypeId.value = null
            membershipTypeMode.value = 'choose'
            membershipTypeForm.reset('name', 'slug', 'description')
        },
    }

    if (editingMembershipTypeId.value) {
        membershipTypeForm.put(route('auth.club-memberships.types.update', [selectedClub.value.id, editingMembershipTypeId.value]), options)
    } else {
        membershipTypeForm.post(route('auth.club-memberships.types.store', selectedClub.value.id), options)
    }
}

const applySolidarityMembershipTemplate = () => {
    membershipTypeMode.value = 'edit'
    editingMembershipTypeId.value = null
    membershipTypeForm.name = tx('club_memberships.workspace.solidarity_type_name', 'Solidarische Mitgliedschaft')
    membershipTypeForm.slug = 'solidarische-mitgliedschaft'
    membershipTypeForm.description = tx(
        'club_memberships.workspace.solidarity_type_description',
        'Für Mitglieder, die aus sozialen Gründen einen ermäßigten oder beitragsfreien Zugang benötigen. Die Entscheidung erfolgt vertraulich durch den Verein.',
    )
    membershipTypeForm.is_public = true
    membershipTypeForm.is_active = true
    membershipTypeForm.sort_order = membershipTypes.value.length + 1
}

const storeContributionRule = () => {
    const options = {
        preserveScroll: true,
        onSuccess: () => {
            editingContributionRuleId.value = null
            contributionRuleForm.reset('club_policy_document_id', 'name', 'amount', 'valid_until', 'age_min', 'age_max', 'factor_operator', 'factor_value', 'tax_account', 'accounting_account', 'notes')
            contributionRuleForm.proration_policy = 'prorate_days'
            contributionRuleForm.factor_key = 'standard'
        },
    }

    if (editingContributionRuleId.value) {
        contributionRuleForm.put(route('auth.club-memberships.contribution-rules.update', [selectedClub.value.id, editingContributionRuleId.value]), options)
    } else {
        contributionRuleForm.post(route('auth.club-memberships.contribution-rules.store', selectedClub.value.id), options)
    }
}

const editMembershipType = (type) => {
    membershipTypeMode.value = 'edit'
    editingMembershipTypeId.value = type.id
    membershipTypeForm.name = type.name || ''
    membershipTypeForm.slug = type.slug || ''
    membershipTypeForm.description = type.description || ''
    membershipTypeForm.is_public = type.is_public !== false
    membershipTypeForm.is_active = type.is_active !== false
    membershipTypeForm.sort_order = type.sort_order || 0
    membershipTypeForm.application_fields = {
        ...Object.fromEntries(applicationFieldDefinitions.value.map((field) => [field.key, field.mode || 'off'])),
        ...(type.application_fields || {}),
    }
}

const editContributionRule = (rule) => {
    editingContributionRuleId.value = rule.id
    contributionRuleForm.club_membership_type_id = rule.club_membership_type_id || ''
    contributionRuleForm.club_policy_document_id = rule.club_policy_document_id || ''
    contributionRuleForm.name = rule.name || ''
    contributionRuleForm.valid_from = rule.valid_from || new Date().toISOString().slice(0, 10)
    contributionRuleForm.valid_until = rule.valid_until || ''
    contributionRuleForm.billing_interval = rule.billing_interval || 'monthly'
    contributionRuleForm.proration_policy = rule.proration_policy || 'prorate_days'
    contributionRuleForm.amount = rule.amount ?? ''
    contributionRuleForm.age_min = rule.age_min ?? ''
    contributionRuleForm.age_max = rule.age_max ?? ''
    contributionRuleForm.factor_key = rule.factor_key || 'standard'
    contributionRuleForm.factor_operator = rule.factor_operator || ''
    contributionRuleForm.factor_value = rule.factor_value ?? ''
    contributionRuleForm.tax_account = rule.tax_account || ''
    contributionRuleForm.accounting_account = rule.accounting_account || ''
    contributionRuleForm.is_active = rule.is_active !== false
    contributionRuleForm.notes = rule.notes || ''
}

const cancelMembershipTypeEdit = () => {
    editingMembershipTypeId.value = null
    membershipTypeMode.value = 'choose'
    membershipTypeForm.reset('name', 'slug', 'description')
    membershipTypeForm.is_public = true
    membershipTypeForm.is_active = true
    membershipTypeForm.sort_order = 0
    membershipTypeForm.application_fields = Object.fromEntries(applicationFieldDefinitions.value.map((field) => [field.key, field.mode || 'off']))
}

const startNewMembershipType = () => {
    cancelMembershipTypeEdit()
    membershipTypeMode.value = 'new'
}

const cancelContributionRuleEdit = () => {
    editingContributionRuleId.value = null
    contributionRuleForm.reset()
    contributionRuleForm.valid_from = new Date().toISOString().slice(0, 10)
    contributionRuleForm.billing_interval = 'monthly'
    contributionRuleForm.proration_policy = 'prorate_days'
    contributionRuleForm.factor_key = 'standard'
    contributionRuleForm.tax_account = ''
    contributionRuleForm.accounting_account = ''
    contributionRuleForm.is_active = true
}

const contributionRuleTypeLabel = (value) => props.contributionRuleTypes.find((type) => type.value === value)?.label || value || 'Standardbeitrag'
const contributionDiscountOperatorLabel = (value) => props.contributionDiscountOperators.find((operator) => operator.value === value)?.label || value

const normalizedMembershipTypeId = (value) => {
    const number = Number(value)

    return Number.isFinite(number) && number > 0 ? number : null
}

const localDateString = () => {
    const now = new Date()

    return new Date(now.getTime() - now.getTimezoneOffset() * 60000).toISOString().slice(0, 10)
}

const contributionRuleIsCurrent = (rule) => {
    const today = localDateString()
    const validFrom = rule.valid_from ? String(rule.valid_from).slice(0, 10) : ''
    const validUntil = rule.valid_until ? String(rule.valid_until).slice(0, 10) : ''

    const factorKey = rule.factor_key || 'standard'

    return rule.is_active !== false
        && (!validFrom || validFrom <= today)
        && (!validUntil || validUntil >= today)
        && ['standard', 'base'].includes(factorKey)
}

const matchingContributionRuleForType = (typeId) => {
    const selectedTypeId = normalizedMembershipTypeId(typeId)

    return [...contributionRules.value]
        .filter(contributionRuleIsCurrent)
        .filter((rule) => {
            const ruleTypeId = normalizedMembershipTypeId(rule.club_membership_type_id)

            if (!selectedTypeId) return ruleTypeId === null

            return ruleTypeId === selectedTypeId || ruleTypeId === null
        })
        .sort((a, b) => {
            const aExact = normalizedMembershipTypeId(a.club_membership_type_id) === selectedTypeId ? 1 : 0
            const bExact = normalizedMembershipTypeId(b.club_membership_type_id) === selectedTypeId ? 1 : 0

            if (aExact !== bExact) return bExact - aExact

            const priorityDifference = Number(b.priority || 0) - Number(a.priority || 0)

            if (priorityDifference !== 0) return priorityDifference

            return String(b.valid_from || '').localeCompare(String(a.valid_from || ''))
        })[0] || null
}

const contributionRulePreviewForMember = (member) => {
    const rule = matchingContributionRuleForType(formFor(member).club_membership_type_id)

    if (!rule) return ''

    return `${tx('auto.Wird übernommen', 'Wird übernommen')}: ${formatMoney(rule.amount)} · ${intervalLabel(rule.billing_interval)}`
}

const contributionInvoiceSuggestionFor = (member) => {
    const form = member.is_external ? member : formFor(member)
    const rule = matchingContributionRuleForType(form.club_membership_type_id)
    const amount = Number(form.contribution_amount || rule?.amount || 0)
    const interval = form.contribution_interval || rule?.billing_interval || 'none'
    const periodStart = dateOnly(form.contribution_next_invoice_on) || dateOnly(form.joined_on) || dateOnly(localDateString())
    const periodEnd = addPeriod(periodStart, interval)
    const joinedOn = dateOnly(form.joined_on)
    const policy = rule?.proration_policy || 'prorate_days'
    let invoiceAmount = amount
    let description = ''

    if (joinedOn && periodEnd && joinedOn > periodStart && joinedOn <= periodEnd) {
        if (policy === 'next_period') {
            invoiceAmount = 0
            description = tx('club_memberships.workspace.invoice_next_period_hint', 'Eintritt liegt im laufenden Zeitraum. Laut Beitragsregel beginnt die Berechnung erst im nächsten Zeitraum.')
        } else if (policy === 'prorate_days') {
            const periodDays = inclusiveDays(periodStart, periodEnd)
            const billableDays = inclusiveDays(joinedOn, periodEnd)
            invoiceAmount = Math.round(amount * 100 * (billableDays / periodDays)) / 100
            description = tx('club_memberships.workspace.invoice_prorated_hint', 'Anteilig berechnet ab Eintrittsdatum.')
        } else if (policy === 'full_amount') {
            description = tx('club_memberships.workspace.invoice_full_amount_hint', 'Voller Betrag laut Beitragsregel.')
        }
    }

    const due = new Date()
    due.setDate(due.getDate() + 14)

    return {
        amount: invoiceAmount.toFixed(2),
        billing_period_start: isoDate(periodStart),
        billing_period_end: isoDate(periodEnd),
        due_date: isoDate(due),
        description,
    }
}

const applyContributionRuleToMember = (member) => {
    const form = formFor(member)
    const rule = matchingContributionRuleForType(form.club_membership_type_id)

    if (!rule) return

    form.contribution_amount = rule.amount ?? ''
    form.contribution_interval = rule.billing_interval || 'none'
}

const saveMember = async (member) => {
    if (!selectedClub.value || savingMemberIds.value.has(member.id)) return

    savingMemberIds.value.add(member.id)
    membershipActionFeedback.value = ''
    membershipActionError.value = ''
    try {
        const response = await window.axios.put(
            route('api.v1.clubs.members.update', [selectedClub.value.id, member.id]),
            formFor(member),
            { headers: { Accept: 'application/json' } },
        )
        applyMembershipManagement(response.data?.data)
        delete memberForms.value[member.id]
        editingMemberId.value = null
        membershipActionFeedback.value = response.data?.message
            || tx('club_memberships.workspace.member_saved', 'Mitgliedsdaten wurden gespeichert.')
    } catch (error) {
        membershipActionError.value = error.response?.data?.message
            || tx('club_memberships.workspace.member_save_failed', 'Die Mitgliedsdaten konnten nicht gespeichert werden.')
    } finally {
        savingMemberIds.value.delete(member.id)
    }
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

    const reason = await promptDialog({
        title: 'Grund für die Entfernung',
        message: 'Bitte gib an, warum dieses Mitglied aus dem Verein entfernt wird. Die Begründung wird gespeichert und der Person mitgeteilt.',
        inputLabel: 'Begründung',
        placeholder: 'Begründung eingeben',
        multiline: true,
        required: true,
        minLength: 3,
        minLengthMessage: tx('club_memberships.workspace.removal_reason_min', 'Mindestens 3 Zeichen.'),
        confirmLabel: 'Entfernen',
        danger: true,
    })

    if (!reason?.trim()) return

    router.delete(route('auth.club-memberships.members.destroy', [selectedClub.value.id, member.id]), {
        data: { reason: reason.trim() },
        preserveScroll: true,
        only: ['clubs', 'flash'],
    })
}

const openInvoice = (member) => {
    const suggestion = contributionInvoiceSuggestionFor(member)
    invoiceMemberId.value = member.is_external ? `external-${member.id}` : member.id
    invoiceForm.title = 'Mitgliedsbeitrag'
    invoiceForm.description = suggestion.description
    invoiceForm.amount = suggestion.amount
    invoiceForm.billing_period_start = suggestion.billing_period_start
    invoiceForm.billing_period_end = suggestion.billing_period_end
    invoiceForm.due_date = suggestion.due_date
    invoiceForm.waived = false
    invoiceForm.waiver_reason = ''
}

const invoiceErrorMessage = (error, fallback) => surveyErrorMessage(error, fallback)

const previewInvoiceRun = async () => {
    if (!selectedClub.value || invoiceRunPreviewLoading.value) return

    invoiceRunPreviewLoading.value = true
    invoiceActionFeedback.value = ''
    invoiceActionError.value = ''
    invoiceRunForm.clearErrors()
    try {
        const response = await window.axios.post(
            route('api.v1.clubs.membership-invoice-runs.preview', selectedClub.value.id),
            {
                run_date: invoiceRunForm.run_date || null,
                due_date: invoiceRunForm.due_date || null,
                title: invoiceRunForm.title || null,
            },
            { headers: { Accept: 'application/json' } },
        )
        invoiceRunPreview.value = response.data?.data || null
    } catch (error) {
        const validationErrors = error.response?.data?.errors || {}
        Object.entries(validationErrors).forEach(([field, messages]) => invoiceRunForm.setError(field, messages?.[0] || String(messages)))
        invoiceActionError.value = invoiceErrorMessage(error, tx('club_memberships.workspace.invoice_run_preview_failed', 'Der Rechnungslauf konnte nicht geprüft werden.'))
    } finally {
        invoiceRunPreviewLoading.value = false
    }
}

const createInvoiceRun = async () => {
    if (!selectedClub.value || invoiceRunForm.processing) return

    if (!invoiceRunPreview.value) {
        await previewInvoiceRun()
    }
    if (!invoiceRunPreview.value?.billable_count) return

    invoiceRunForm.processing = true
    invoiceActionFeedback.value = ''
    invoiceActionError.value = ''
    try {
        const response = await window.axios.post(
            route('api.v1.clubs.membership-invoice-runs.store', selectedClub.value.id),
            {
                run_date: invoiceRunForm.run_date || null,
                due_date: invoiceRunForm.due_date || null,
                title: invoiceRunForm.title || null,
            },
            { headers: { Accept: 'application/json' } },
        )
        applyMembershipManagement(response.data?.data)
        const run = response.data?.invoice_run
        invoiceRunPreview.value = null
        invoiceActionFeedback.value = tx('club_memberships.workspace.invoice_run_created', '{count} Rechnung(en) wurden erstellt und benachrichtigt.', { count: run?.created_count ?? 0 })
        activeTab.value = 'invoices'
    } catch (error) {
        const validationErrors = error.response?.data?.errors || {}
        Object.entries(validationErrors).forEach(([field, messages]) => invoiceRunForm.setError(field, messages?.[0] || String(messages)))
        invoiceActionError.value = invoiceErrorMessage(error, tx('club_memberships.workspace.invoice_run_failed', 'Der Rechnungslauf konnte nicht erstellt werden.'))
    } finally {
        invoiceRunForm.processing = false
    }
}

const createInvoice = async (member) => {
    if (!selectedClub.value || invoiceForm.processing) return

    invoiceForm.processing = true
    invoiceForm.clearErrors()
    invoiceActionFeedback.value = ''
    invoiceActionError.value = ''
    try {
        const invoiceUrl = member.is_external
            ? route('api.v1.clubs.external-members.invoices.store', [selectedClub.value.id, member.id])
            : route('api.v1.clubs.members.invoices.store', [selectedClub.value.id, member.id])
        const response = await window.axios.post(
            invoiceUrl,
            {
                title: invoiceForm.title,
                description: invoiceForm.description || null,
                amount: invoiceForm.amount,
                billing_period_start: invoiceForm.billing_period_start || null,
                billing_period_end: invoiceForm.billing_period_end || null,
                due_date: invoiceForm.due_date,
                waived: invoiceForm.waived,
                waiver_reason: invoiceForm.waiver_reason || null,
            },
            { headers: { Accept: 'application/json' } },
        )
        applyMembershipManagement(response.data?.data)
        invoiceMemberId.value = null
        invoiceForm.reset()
        invoiceForm.title = 'Mitgliedsbeitrag'
        invoiceActionFeedback.value = response.data?.message || tx('club_memberships.workspace.invoice_created', 'Rechnung wurde erstellt.')
        activeTab.value = 'invoices'
    } catch (error) {
        const validationErrors = error.response?.data?.errors || {}
        Object.entries(validationErrors).forEach(([field, messages]) => invoiceForm.setError(field, messages?.[0] || String(messages)))
        invoiceActionError.value = invoiceErrorMessage(error, tx('club_memberships.workspace.invoice_create_failed', 'Die Rechnung konnte nicht erstellt werden.'))
    } finally {
        invoiceForm.processing = false
    }
}

const openPayment = (invoice) => {
    paymentInvoice.value = invoice
    paymentForm.amount = invoice.outstanding_amount ?? invoice.amount
    paymentForm.method = 'bank_transfer'
    paymentForm.paid_at = new Date().toISOString().slice(0, 10)
    paymentForm.reference = ''
    paymentForm.notes = ''
    paymentForm.clearErrors()
}

const recordInvoicePayment = async () => {
    const invoice = paymentInvoice.value
    if (!invoice || !selectedClub.value || paymentForm.processing) return

    paymentForm.processing = true
    invoiceActionFeedback.value = ''
    invoiceActionError.value = ''
    try {
        const response = await window.axios.post(
            route('api.v1.clubs.membership-invoices.payments.store', [selectedClub.value.id, invoice.id]),
            {
                amount: paymentForm.amount || null,
                method: paymentForm.method,
                paid_at: paymentForm.paid_at || null,
                reference: paymentForm.reference || null,
                notes: paymentForm.notes || null,
            },
            { headers: { Accept: 'application/json' } },
        )
        applyMembershipManagement(response.data?.data)
        paymentInvoice.value = null
        invoiceActionFeedback.value = response.data?.message || tx('club_memberships.workspace.payment_recorded', 'Zahlung wurde erfasst.')
    } catch (error) {
        const validationErrors = error.response?.data?.errors || {}
        Object.entries(validationErrors).forEach(([field, messages]) => paymentForm.setError(field, messages?.[0] || String(messages)))
        invoiceActionError.value = invoiceErrorMessage(error, tx('club_memberships.workspace.payment_failed', 'Die Zahlung konnte nicht erfasst werden.'))
    } finally {
        paymentForm.processing = false
    }
}

const updateInvoiceStatus = async (invoice, status) => {
    if (!selectedClub.value || processingInvoiceIds.value.has(invoice.id)) return
    processingInvoiceIds.value.add(invoice.id)
    invoiceActionFeedback.value = ''
    invoiceActionError.value = ''
    try {
        const response = await window.axios.put(
            route('api.v1.clubs.membership-invoices.status.update', [selectedClub.value.id, invoice.id]),
            { status },
            { headers: { Accept: 'application/json' } },
        )
        applyMembershipManagement(response.data?.data)
        invoiceActionFeedback.value = response.data?.message || tx('club_memberships.workspace.invoice_status_updated', 'Rechnungsstatus wurde aktualisiert.')
    } catch (error) {
        invoiceActionError.value = invoiceErrorMessage(error, tx('club_memberships.workspace.invoice_status_failed', 'Der Rechnungsstatus konnte nicht aktualisiert werden.'))
    } finally {
        processingInvoiceIds.value.delete(invoice.id)
    }
}

const sendReminder = async (invoice) => {
    if (!selectedClub.value || processingInvoiceIds.value.has(invoice.id)) return
    processingInvoiceIds.value.add(invoice.id)
    invoiceActionFeedback.value = ''
    invoiceActionError.value = ''
    try {
        const response = await window.axios.post(
            route('api.v1.clubs.membership-invoices.reminder.store', [selectedClub.value.id, invoice.id]),
            {},
            { headers: { Accept: 'application/json' } },
        )
        applyMembershipManagement(response.data?.data)
        invoiceActionFeedback.value = response.data?.message || tx('club_memberships.workspace.reminder_sent', 'Zahlungserinnerung wurde gesendet.')
    } catch (error) {
        invoiceActionError.value = invoiceErrorMessage(error, tx('club_memberships.workspace.reminder_failed', 'Die Zahlungserinnerung konnte nicht gesendet werden.'))
    } finally {
        processingInvoiceIds.value.delete(invoice.id)
    }
}

const resetBankImportPreview = (file = null) => {
    bankImportForm.file = file
    bankImportPreview.value = null
    bankImportFeedback.value = ''
    bankImportForm.clearErrors()
}

const closeBankImportModal = () => {
    if (bankImportPreviewLoading.value || bankImportSubmitting.value) return
    showBankImportModal.value = false
    bankImportForm.reset()
    bankImportPreview.value = null
    bankImportFeedback.value = ''
}

const bankImportPayload = () => {
    const payload = new FormData()
    if (bankImportForm.file) payload.append('file', bankImportForm.file)
    return payload
}

const previewBankTransactions = async () => {
    if (!selectedClub.value || !bankImportForm.file || bankImportPreviewLoading.value) return
    bankImportPreviewLoading.value = true
    bankImportForm.clearErrors()
    try {
        const response = await window.axios.post(
            route('api.v1.clubs.bank-transactions.preview', selectedClub.value.id),
            bankImportPayload(),
            { headers: { Accept: 'application/json', 'Content-Type': 'multipart/form-data' } },
        )
        bankImportPreview.value = response.data?.data || null
    } catch (error) {
        bankImportForm.setError('file', invoiceErrorMessage(error, tx('club_memberships.workspace.bank_preview_failed', 'Die Bankdatei konnte nicht geprüft werden.')))
    } finally {
        bankImportPreviewLoading.value = false
    }
}

const importBankTransactions = async () => {
    if (!selectedClub.value || !bankImportPreview.value?.can_import || bankImportSubmitting.value) return
    bankImportSubmitting.value = true
    bankImportForm.clearErrors()
    try {
        const response = await window.axios.post(
            route('api.v1.clubs.bank-transactions.import', selectedClub.value.id),
            bankImportPayload(),
            { headers: { Accept: 'application/json', 'Content-Type': 'multipart/form-data' } },
        )
        applyMembershipManagement(response.data?.data)
        bankImportFeedback.value = response.data?.message || tx('club_memberships.workspace.bank_import_complete', 'Bankabgleich wurde abgeschlossen.')
        bankImportForm.reset()
        bankImportPreview.value = null
        showBankImportModal.value = false
    } catch (error) {
        bankImportForm.setError('file', invoiceErrorMessage(error, tx('club_memberships.workspace.bank_import_failed', 'Die Bankdatei konnte nicht importiert werden.')))
    } finally {
        bankImportSubmitting.value = false
    }
}

const confirmBankTransaction = async (transaction) => {
    if (!selectedClub.value || processingBankTransactionIds.value.has(transaction.id)) return
    processingBankTransactionIds.value.add(transaction.id)
    financeActionFeedback.value = ''
    financeActionError.value = ''
    try {
        const response = await window.axios.post(
            route('api.v1.clubs.bank-transactions.confirm', [selectedClub.value.id, transaction.id]),
            {},
            { headers: { Accept: 'application/json' } },
        )
        applyMembershipManagement(response.data?.data)
        financeActionFeedback.value = response.data?.message || tx('club_memberships.workspace.bank_transaction_booked', 'Bankumsatz wurde verbucht.')
    } catch (error) {
        financeActionError.value = invoiceErrorMessage(error, tx('club_memberships.workspace.bank_transaction_failed', 'Der Bankumsatz konnte nicht verbucht werden.'))
    } finally {
        processingBankTransactionIds.value.delete(transaction.id)
    }
}

const addEmailMember = () => {
    const invitationCount = invitationRowsToSend()

    router.post(route('auth.club-memberships.email-members.store', selectedClub.value.id), emailMemberForm.value, {
        preserveScroll: true,
        onSuccess: () => {
            decrementInvitationLimit(selectedClub.value, invitationCount)
            emailMemberForm.value = {
                send_invitation: true,
                invitation_expires_at: '',
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

const resetImportPreview = () => {
    importPreview.value = null
    importPreviewError.value = ''
}

const selectImportFile = (event) => {
    importForm.file = event.target.files?.[0] || null
    importForm.clearErrors('file')
    resetImportPreview()
}

const closeImportModal = () => {
    showImportModal.value = false
    importForm.reset()
    importForm.clearErrors()
    resetImportPreview()
}

const previewEmailMembers = async () => {
    if (!importForm.file || !selectedClub.value || importPreviewLoading.value) return

    importPreviewLoading.value = true
    importPreviewError.value = ''
    importForm.clearErrors('file')

    const payload = new FormData()
    payload.append('file', importForm.file)

    try {
        const response = await window.axios.post(
            route('api.v1.clubs.members.import-preview', selectedClub.value.id),
            payload,
            { headers: { Accept: 'application/json' } },
        )
        importPreview.value = response.data?.data || null
    } catch (error) {
        const fileError = error.response?.data?.errors?.file?.[0]
        importPreviewError.value = fileError
            || error.response?.data?.message
            || tx('club_memberships.workspace.preview_failed', 'Die Datei konnte nicht geprüft werden. Bitte kontrolliere Format und Inhalt.')
    } finally {
        importPreviewLoading.value = false
    }
}

const importEmailMembers = async () => {
    if (!importPreview.value?.can_import || !selectedClub.value || importSubmitting.value) return

    importSubmitting.value = true
    importForm.clearErrors()
    const payload = new FormData()
    payload.append('file', importForm.file)
    payload.append('send_invitation', importForm.send_invitation ? '1' : '0')

    try {
        const response = await window.axios.post(
            route('api.v1.clubs.members.import', selectedClub.value.id),
            payload,
            { headers: { Accept: 'application/json' } },
        )
        const management = response.data?.data
        if (management && typeof management === 'object') {
            Object.assign(selectedClub.value, management)
        }
        importOutcome.value = {
            message: response.data?.message || tx('club_memberships.workspace.import_complete', 'Mitglieder wurden importiert.'),
            errors: [...(importPreview.value.errors || [])],
        }
        importForm.reset()
        showImportModal.value = false
        resetImportPreview()
    } catch (error) {
        const fileError = error.response?.data?.errors?.file?.[0]
        importForm.setError('file', fileError
            || error.response?.data?.message
            || tx('club_memberships.workspace.import_failed', 'Der Import konnte nicht abgeschlossen werden.'))
    } finally {
        importSubmitting.value = false
    }
}

const inviteExternalMember = (member) => {
    router.post(route('auth.club-memberships.email-members.invite', member.id), {}, {
        preserveScroll: true,
        onSuccess: () => decrementInvitationLimit(selectedClub.value),
    })
}

const openDuplicateMerge = (member) => {
    duplicateMergeMember.value = member
    duplicateMergeForm.reset()
    duplicateMergeForm.clearErrors()
}

const closeDuplicateMerge = () => {
    if (duplicateMergeForm.processing) return
    duplicateMergeMember.value = null
    duplicateMergeForm.reset()
    duplicateMergeForm.clearErrors()
}

const mergeDuplicate = () => {
    const member = duplicateMergeMember.value
    if (!member?.duplicate_candidate || !selectedClub.value) return

    duplicateMergeForm.post(route('auth.club-memberships.external-members.merge', [
        selectedClub.value.id,
        member.id,
        member.duplicate_candidate.user_id,
    ]), {
        preserveScroll: true,
        onSuccess: closeDuplicateMerge,
    })
}

const timelineEntriesFor = (member) => {
    const subjectType = member.is_external ? 'external_member' : 'member'
    return (selectedClub.value?.member_timeline_entries || []).filter((entry) => (
        entry.subject_type === subjectType && Number(entry.subject_id) === Number(member.id)
    ))
}

const openTimeline = (member, isExternal = false) => {
    timelineMember.value = { ...member, is_external: isExternal }
    timelineForm.reset()
    timelineForm.occurred_on = new Date().toISOString().slice(0, 10)
    timelineForm.clearErrors()
}

const closeTimeline = () => {
    if (timelineForm.processing) return
    timelineMember.value = null
    timelineForm.reset()
    timelineForm.clearErrors()
}

const saveTimelineEntry = async () => {
    if (!selectedClub.value || !timelineMember.value || timelineForm.processing) return
    timelineForm.processing = true
    timelineForm.clearErrors()
    try {
        const response = await window.axios.post(
            route('api.v1.clubs.member-timeline.store', selectedClub.value.id),
            {
                ...timelineForm.data(),
                subject_type: timelineMember.value.is_external ? 'external_member' : 'member',
                subject_id: timelineMember.value.id,
            },
            { headers: { Accept: 'application/json' } },
        )
        selectedClub.value.member_timeline_entries ??= []
        selectedClub.value.member_timeline_entries.unshift(response.data.data)
        timelineForm.reset()
        timelineForm.occurred_on = new Date().toISOString().slice(0, 10)
    } catch (error) {
        const errors = error.response?.data?.errors || {}
        Object.entries(errors).forEach(([field, messages]) => timelineForm.setError(field, Array.isArray(messages) ? messages[0] : messages))
    } finally {
        timelineForm.processing = false
    }
}

const deleteTimelineEntry = async (entry) => {
    if (!selectedClub.value || !['honor', 'anniversary', 'note'].includes(entry.type)) return
    await window.axios.delete(route('api.v1.clubs.member-timeline.destroy', [selectedClub.value.id, entry.id]), {
        headers: { Accept: 'application/json' },
    })
    selectedClub.value.member_timeline_entries = selectedClub.value.member_timeline_entries.filter((item) => item.id !== entry.id)
}

const openExternalMemberEdit = (member) => {
    externalMemberEdit.value = member
    for (const field of ['name', 'email', 'phone', 'country', 'street', 'house_number', 'postal_code', 'city', 'role', 'membership_status', 'club_membership_type_id']) {
        externalMemberForm[field] = member[field] ?? ''
    }
    externalMemberForm.clearErrors()
}

const closeExternalMemberEdit = () => {
    if (externalMemberForm.processing) return
    externalMemberEdit.value = null
    externalMemberForm.reset()
    externalMemberForm.clearErrors()
}

const saveExternalMember = async () => {
    if (!selectedClub.value || !externalMemberEdit.value || externalMemberForm.processing) return
    externalMemberForm.processing = true
    externalMemberForm.clearErrors()
    try {
        const member = externalMemberEdit.value
        const response = await window.axios.put(
            route('api.v1.clubs.external-members.update', [selectedClub.value.id, member.id]),
            {
                ...member,
                ...externalMemberForm.data(),
                club_membership_type_id: externalMemberForm.club_membership_type_id || null,
            },
            { headers: { Accept: 'application/json' } },
        )
        applyMembershipManagement(response.data?.data)
        externalMemberEdit.value = null
        externalMemberForm.reset()
    } catch (error) {
        const errors = error.response?.data?.errors || {}
        Object.entries(errors).forEach(([field, messages]) => externalMemberForm.setError(field, Array.isArray(messages) ? messages[0] : messages))
    } finally {
        externalMemberForm.processing = false
    }
}
</script>

<template>
    <Head :title="tx('club_memberships.workspace.title', 'Mitglieder & Finanzen')" />

    <div class="space-y-6">
        <div
            v-if="page.props.flash?.success"
            class="rounded-lg border border-success/30 bg-success/10 px-4 py-3 text-sm font-semibold text-success"
        >
            {{ page.props.flash.success }}
        </div>

        <div
            v-if="page.props.flash?.import_report?.total_errors"
            class="rounded-lg border border-warning/30 bg-warning/10 px-4 py-3 text-sm text-primary"
        >
            <p class="font-semibold">{{ tx('club_memberships.workspace.import_error_title', 'Import-Fehlerbericht') }}</p>
            <p class="mt-1 text-secondary">
                {{ tx('club_memberships.workspace.import_error_summary', '{count} Meldungen wurden protokolliert; betroffene Datensätze wurden übersprungen.', { count: page.props.flash.import_report.total_errors }) }}
            </p>
            <ul class="mt-3 space-y-1 text-xs text-secondary">
                <li
                    v-for="error in page.props.flash.import_report.errors"
                    :key="`${error.row}-${error.email}-${error.reason}`"
                >
                    {{ tx('club_memberships.workspace.row', 'Zeile') }} {{ error.row }}<template v-if="error.email">, {{ error.email }}</template>: {{ error.reason }}
                </li>
            </ul>
        </div>

        <div
            v-if="importOutcome"
            class="rounded-lg border border-success/30 bg-success/10 px-4 py-3 text-sm text-primary"
            role="status"
            aria-live="polite"
        >
            <p class="font-semibold text-success">{{ importOutcome.message }}</p>
            <p v-if="importOutcome.errors.length" class="mt-1 text-secondary">
                {{ tx('club_memberships.workspace.import_error_summary', '{count} Meldungen wurden protokolliert; betroffene Datensätze wurden übersprungen.', { count: importOutcome.errors.length }) }}
            </p>
            <ul v-if="importOutcome.errors.length" class="mt-2 space-y-1 text-xs text-secondary">
                <li v-for="error in importOutcome.errors" :key="`inline-${error.row}-${error.email}-${error.reason}`">
                    {{ tx('club_memberships.workspace.preview_error_row', 'Zeile {row}: {reason}', { row: error.row, reason: error.reason }) }}
                </li>
            </ul>
        </div>

        <div
            v-if="membershipActionFeedback"
            class="rounded-lg border border-success/30 bg-success/10 px-4 py-3 text-sm font-semibold text-success"
            role="status"
            aria-live="polite"
        >
            {{ membershipActionFeedback }}
        </div>

        <div
            v-if="membershipActionError"
            class="rounded-lg border border-error/30 bg-error/10 px-4 py-3 text-sm font-semibold text-error"
            role="alert"
        >
            {{ membershipActionError }}
        </div>

        <div
            v-if="pageError"
            class="rounded-lg border border-error/30 bg-error/10 px-4 py-3 text-sm font-semibold text-error"
        >
            {{ pageError }}
        </div>

        <ClubWorkspaceNav
            active="memberships"
            :description="tx('club_memberships.workspace.nav_description', 'Mitgliedschaft, Beiträge, Finanzen und Exporte.')"
        />

        <section class="surface-card overflow-hidden">
            <div class="grid gap-5 p-5 lg:grid-cols-[minmax(0,1fr)_20rem] lg:items-end">
                <div>
                    <p class="text-xs font-semibold uppercase tracking-wide text-secondary">{{ tx('club_memberships.workspace.eyebrow', 'Vereinsverwaltung') }}</p>
                    <h1 class="mt-1 text-2xl font-bold text-primary">{{ tx('club_memberships.workspace.title', 'Mitglieder & Finanzen') }}</h1>
                    <p class="mt-2 max-w-2xl text-sm leading-6 text-secondary">
                        {{ tx('club_memberships.workspace.intro', 'Mitglieder pflegen, Anfragen prüfen, Beiträge abrechnen und Zahlungen abgleichen.') }}
                    </p>
                </div>

                <label v-if="clubs.length" class="block">
                    <span class="text-xs font-semibold uppercase text-secondary">{{ tx('club_memberships.workspace.active_club', 'Aktiver Verein') }}</span>
                    <select v-model="selectedClubId" class="mt-1 w-full rounded-lg border border-border bg-inputBg px-3 py-3 text-sm font-semibold text-primary">
                        <option v-for="club in clubs" :key="club.id" :value="club.id">
                            {{ club.name }}
                        </option>
                    </select>
                </label>
            </div>
        </section>

        <section v-if="!clubs.length" class="surface-card p-8 text-center text-secondary">
            {{ tx('club_memberships.workspace.no_club', 'Du verwaltest aktuell keinen Verein.') }}
        </section>

        <template v-else-if="selectedClub">
            <section class="grid grid-cols-2 gap-3 xl:grid-cols-4">
                <div class="surface-card p-3 sm:p-4">
                    <div class="text-xs font-semibold uppercase text-secondary">{{ tx('club_memberships.workspace.active_members', 'Aktive Mitglieder') }}</div>
                    <div class="mt-2 text-xl font-bold text-primary sm:text-2xl">{{ activeMembersCount }}</div>
                    <div class="mt-1 text-xs text-secondary">{{ tx('club_memberships.workspace.linked_people', 'von {count} verknüpften Personen', { count: members.length }) }}</div>
                </div>
                <div class="surface-card p-3 sm:p-4">
                    <div class="text-xs font-semibold uppercase text-secondary">{{ tx('club_memberships.workspace.open', 'Offen') }}</div>
                    <div class="mt-2 text-xl font-bold text-primary sm:text-2xl">{{ formatMoney(openInvoiceTotal) }}</div>
                    <div class="mt-1 text-xs text-secondary">{{ tx('club_memberships.workspace.open_invoices', '{count} offene Rechnung(en)', { count: openInvoices.length }) }}</div>
                </div>
                <div class="surface-card p-3 sm:p-4">
                    <div class="text-xs font-semibold uppercase text-secondary">{{ tx('club_memberships.workspace.sepa_ready', 'SEPA bereit') }}</div>
                    <div class="mt-2 text-xl font-bold text-primary sm:text-2xl">{{ sepaReadyMembersCount }}</div>
                    <div class="mt-1 text-xs text-secondary">{{ tx('club_memberships.workspace.mandates', 'Mandate mit IBAN und Referenz') }}</div>
                </div>
                <div class="surface-card p-3 sm:p-4">
                    <div class="text-xs font-semibold uppercase text-secondary">{{ tx('club_memberships.workspace.recurring_contributions', 'Wiederkehrende Beiträge') }}</div>
                    <div class="mt-2 text-xl font-bold text-primary sm:text-2xl">{{ formatMoney(recurringContributionTotal) }}</div>
                    <div class="mt-1 text-xs text-secondary">{{ tx('club_memberships.workspace.active_rules_sum', 'Summe aktiver Beitragssätze') }}</div>
                </div>
            </section>

            <section class="surface-card overflow-hidden">
                <div class="grid gap-4 border-b border-border p-5 xl:grid-cols-[minmax(0,1fr)_auto] xl:items-center">
                    <div>
                        <p class="text-xs font-semibold uppercase text-secondary">{{ tx('club_memberships.workspace.current_plan', 'Aktueller Vereinsplan') }}</p>
                        <h2 class="mt-1 text-xl font-semibold text-primary">{{ selectedClub.subscription?.plan?.name || 'Free' }}</h2>
                        <div class="mt-3 h-2 max-w-xl overflow-hidden rounded-full bg-inputBg">
                            <div class="h-full rounded-full bg-buttonPrimary" :style="{ width: `${memberUsagePercent}%` }"></div>
                        </div>
                        <p class="mt-2 text-xs text-secondary">
                            Mitglieder: {{ selectedClub.subscription?.member_usage || members.length }}
                            / {{ selectedClub.subscription?.member_limit || tx('club_memberships.workspace.unlimited', 'unbegrenzt') }}
                        </p>
                    </div>

                    <div class="flex flex-wrap gap-2 xl:justify-end">
                        <a
                            :href="route('auth.club-memberships.import-template')"
                            class="rounded-lg border border-border px-4 py-2 text-sm font-semibold text-primary hover:bg-inputBg"
                        >
                            {{ tx('club_memberships.workspace.excel_template', 'Excel-Vorlage') }}
                        </a>
                        <button
                            type="button"
                            class="rounded-lg border border-border px-4 py-2 text-sm font-semibold text-primary hover:bg-inputBg disabled:cursor-not-allowed disabled:opacity-50"
                            :disabled="capabilities.member_import === false"
                            :title="capabilities.member_import === false ? tx('club_memberships.workspace.import_unavailable', 'Import ist ab Starter verfügbar') : ''"
                            @click="showImportModal = true"
                        >
                            {{ tx('club_memberships.workspace.import', 'Importieren') }}
                        </button>
                        <button
                            type="button"
                            class="rounded-lg bg-buttonPrimary px-4 py-2 text-sm font-semibold text-buttonTextPrimary disabled:cursor-not-allowed disabled:opacity-50"
                            :disabled="!canOpenEmailMembers"
                            :title="!canOpenEmailMembers ? tx('club_memberships.workspace.external_members_unavailable', 'Externe Mitglieder sind ab Starter verfügbar') : ''"
                            @click="showAddMemberModal = true"
                        >
                            {{ tx('club_memberships.workspace.add_member', 'Mitglied hinzufügen') }}
                        </button>
                    </div>
                </div>

                <div class="flex gap-2 overflow-x-auto p-3" role="tablist" :aria-label="tx('club_memberships.workspace.sections', 'Mitgliederbereiche')">
                    <button
                        v-for="tab in tabs"
                        :key="tab.key"
                        type="button"
                        role="tab"
                        :aria-selected="activeTab === tab.key"
                        class="inline-flex shrink-0 items-center gap-2 rounded-lg border px-3 py-2 text-sm font-semibold transition"
                        :class="activeTab === tab.key
                            ? 'border-buttonPrimary bg-buttonPrimary text-buttonTextPrimary'
                            : 'border-border bg-card text-secondary hover:bg-inputBg hover:text-primary'"
                        @click="activeTab = tab.key"
                    >
                        <i :class="tab.icon"></i>
                        <span>{{ tab.label }}</span>
                        <span v-if="tab.count !== null" class="rounded bg-black/10 px-1.5 py-0.5 text-xs">{{ tab.count }}</span>
                    </button>
                </div>
            </section>

            <section v-if="activeTab === 'surveys'" class="grid gap-6 xl:grid-cols-[minmax(0,1.4fr)_minmax(20rem,0.6fr)]">
                <div class="surface-card p-5">
                    <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                        <div>
                            <h2 class="text-lg font-semibold text-primary">{{ tx('club_memberships.surveys.title', 'Vereinsumfragen') }}</h2>
                            <p class="mt-1 text-sm text-secondary">{{ tx('club_memberships.surveys.intro', 'Hole Entscheidungen und Rückmeldungen direkt von euren Mitgliedern ein.') }}</p>
                        </div>
                        <button
                            type="button"
                            class="self-start rounded-lg border border-border px-3 py-2 text-sm font-semibold text-primary hover:bg-inputBg disabled:opacity-60"
                            :disabled="surveysLoading"
                            @click="loadSurveys"
                        >
                            {{ tx('auto.Aktualisieren', 'Aktualisieren') }}
                        </button>
                    </div>

                    <p v-if="surveysError" class="mt-4 rounded-lg border border-error/30 bg-error/10 px-4 py-3 text-sm font-semibold text-error" role="alert">
                        {{ surveysError }}
                    </p>
                    <p v-if="surveysLoading" class="mt-5 text-sm text-secondary" role="status">
                        {{ tx('club_memberships.surveys.loading', 'Umfragen werden geladen …') }}
                    </p>
                    <div v-else-if="!surveys.length" class="mt-5 rounded-lg border border-dashed border-border p-6 text-center text-sm text-secondary">
                        {{ tx('club_memberships.surveys.empty', 'Noch keine Umfrage vorhanden. Erstelle rechts die erste Vereinsumfrage.') }}
                    </div>

                    <div v-else class="mt-5 space-y-4">
                        <article v-for="survey in surveys" :key="survey.id" class="rounded-xl border border-border bg-bg p-4">
                            <div class="flex flex-col gap-2 sm:flex-row sm:items-start sm:justify-between">
                                <div>
                                    <h3 class="font-semibold text-primary">{{ survey.question }}</h3>
                                    <p v-if="survey.description" class="mt-1 text-sm text-secondary">{{ survey.description }}</p>
                                </div>
                                <span class="self-start rounded-full px-2.5 py-1 text-xs font-semibold" :class="survey.status === 'open' ? 'bg-success/10 text-success' : 'bg-muted text-secondary'">
                                    {{ survey.status === 'open' ? tx('auto.Offen', 'Offen') : tx('auto.Geschlossen', 'Geschlossen') }}
                                </span>
                            </div>

                            <fieldset class="mt-4 space-y-2" :disabled="survey.status !== 'open' || Boolean(surveySavingId)">
                                <legend class="sr-only">{{ survey.question }}</legend>
                                <label
                                    v-for="option in survey.options"
                                    :key="option.id"
                                    class="flex cursor-pointer items-center justify-between gap-3 rounded-lg border border-border bg-card px-3 py-2 text-sm text-primary"
                                >
                                    <span class="flex min-w-0 items-center gap-2">
                                        <input
                                            type="radio"
                                            :name="`survey-${survey.id}`"
                                            :checked="survey.my_option_id === option.id"
                                            @change="voteSurvey(survey, option.id)"
                                        >
                                        <span class="truncate">{{ option.label }}</span>
                                    </span>
                                    <span class="shrink-0 text-xs font-semibold text-secondary">{{ option.votes }}</span>
                                </label>
                            </fieldset>

                            <div class="mt-4 flex flex-wrap items-center justify-between gap-3 text-xs text-secondary">
                                <span>{{ tx('club_memberships.surveys.votes', '{count} Stimme(n)', { count: survey.votes }) }}</span>
                                <span v-if="survey.quorum">{{ tx('club_memberships.surveys.quorum', 'Quorum: {value}% · {status}', { value: survey.quorum, status: survey.quorum_reached ? tx('auto.Erreicht', 'erreicht') : tx('auto.Offen', 'offen') }) }}</span>
                                <button
                                    v-if="survey.can_close && survey.status === 'open'"
                                    type="button"
                                    class="rounded-lg border border-border px-3 py-1.5 font-semibold text-primary hover:bg-inputBg disabled:opacity-60"
                                    :disabled="Boolean(surveySavingId)"
                                    @click="closeSurvey(survey)"
                                >
                                    {{ tx('club_memberships.surveys.close', 'Umfrage schließen') }}
                                </button>
                            </div>
                        </article>
                    </div>
                </div>

                <form class="surface-card h-fit space-y-4 p-5" @submit.prevent="createSurvey">
                    <div>
                        <h2 class="text-lg font-semibold text-primary">{{ tx('club_memberships.surveys.create_title', 'Neue Umfrage') }}</h2>
                        <p class="mt-1 text-sm text-secondary">{{ tx('club_memberships.surveys.all_members_hint', 'Die Umfrage ist für alle aktiven Vereinsmitglieder sichtbar.') }}</p>
                    </div>
                    <div>
                        <label for="club-survey-question" class="text-xs font-semibold uppercase text-secondary">{{ tx('auto.Frage', 'Frage') }}</label>
                        <input id="club-survey-question" v-model="surveyForm.question" required maxlength="255" class="mt-1 w-full rounded-lg border border-border bg-inputBg px-3 py-2 text-sm text-primary">
                    </div>
                    <div>
                        <label for="club-survey-description" class="text-xs font-semibold uppercase text-secondary">{{ tx('auto.Beschreibung', 'Beschreibung') }}</label>
                        <textarea id="club-survey-description" v-model="surveyForm.description" rows="3" maxlength="2000" class="mt-1 w-full rounded-lg border border-border bg-inputBg px-3 py-2 text-sm text-primary"></textarea>
                    </div>
                    <fieldset class="space-y-2">
                        <legend class="text-xs font-semibold uppercase text-secondary">{{ tx('club_memberships.surveys.answers', 'Antwortmöglichkeiten') }}</legend>
                        <div v-for="(option, index) in surveyForm.options" :key="index" class="flex gap-2">
                            <label :for="`club-survey-option-${index}`" class="sr-only">{{ tx('club_memberships.surveys.answer_number', 'Antwort {number}', { number: index + 1 }) }}</label>
                            <input :id="`club-survey-option-${index}`" v-model="surveyForm.options[index]" required maxlength="255" class="min-w-0 flex-1 rounded-lg border border-border bg-inputBg px-3 py-2 text-sm text-primary">
                            <button v-if="surveyForm.options.length > 2" type="button" class="rounded-lg border border-border px-3 text-secondary hover:bg-inputBg" :aria-label="tx('club_memberships.surveys.remove_answer', 'Antwort entfernen')" @click="removeSurveyOption(index)">
                                <i class="las la-times" aria-hidden="true"></i>
                            </button>
                        </div>
                        <button v-if="surveyForm.options.length < 6" type="button" class="rounded-lg border border-border px-3 py-2 text-sm font-semibold text-primary hover:bg-inputBg" @click="addSurveyOption">
                            {{ tx('club_memberships.surveys.add_answer', 'Antwort hinzufügen') }}
                        </button>
                    </fieldset>
                    <div class="grid gap-3 sm:grid-cols-2 xl:grid-cols-1">
                        <div>
                            <label for="club-survey-quorum" class="text-xs font-semibold uppercase text-secondary">{{ tx('club_memberships.surveys.quorum_optional', 'Quorum in % (optional)') }}</label>
                            <input id="club-survey-quorum" v-model="surveyForm.quorum" type="number" min="1" max="100" class="mt-1 w-full rounded-lg border border-border bg-inputBg px-3 py-2 text-sm text-primary">
                        </div>
                        <div>
                            <label for="club-survey-closes-at" class="text-xs font-semibold uppercase text-secondary">{{ tx('club_memberships.surveys.closes_at', 'Endet am (optional)') }}</label>
                            <input id="club-survey-closes-at" v-model="surveyForm.closes_at" type="datetime-local" class="mt-1 w-full rounded-lg border border-border bg-inputBg px-3 py-2 text-sm text-primary">
                        </div>
                    </div>
                    <button class="w-full rounded-lg bg-buttonPrimary px-4 py-2.5 text-sm font-semibold text-buttonTextPrimary disabled:opacity-60" :disabled="Boolean(surveySavingId)">
                        {{ surveySavingId === 'create' ? tx('club_memberships.surveys.creating', 'Umfrage wird erstellt …') : tx('club_memberships.surveys.create', 'Umfrage erstellen') }}
                    </button>
                </form>
            </section>

            <ClubMembershipProspects v-if="activeTab === 'prospects'" :club="selectedClub" />

            <section v-if="activeTab === 'audit'" class="surface-card p-5">
                <div class="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
                    <div>
                        <h2 class="text-lg font-semibold text-primary">{{ tx('club_memberships.workspace.audit_log', 'Audit Log') }}</h2>
                        <p class="mt-1 text-sm text-secondary">{{ tx('club_memberships.workspace.club_actions', '{count} Vereinsaktion(en)', { count: auditLogs.length }) }}</p>
                    </div>
                </div>

                <div class="mt-4 overflow-x-auto">
                    <table class="min-w-full text-left text-sm">
                        <thead class="text-xs uppercase text-secondary">
                            <tr>
                                <th class="py-2 pr-4">{{ tx('club_memberships.workspace.time', 'Zeit') }}</th>
                                <th class="py-2 pr-4">{{ tx('club_memberships.workspace.action', 'Aktion') }}</th>
                                <th class="py-2 pr-4">{{ tx('club_memberships.workspace.person', 'Person') }}</th>
                                <th class="py-2 pr-4">{{ tx('club_memberships.workspace.details', 'Details') }}</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-border">
                            <tr v-for="entry in auditLogs" :key="entry.id">
                                <td class="py-3 pr-4 text-secondary">{{ formatDateTime(entry.created_at) }}</td>
                                <td class="py-3 pr-4">
                                    <span class="rounded-full bg-air-blue/15 px-2 py-1 text-xs font-semibold text-air-blue">
                                        {{ entry.label }}
                                    </span>
                                </td>
                                <td class="py-3 pr-4 text-primary">{{ entry.actor?.name || '-' }}</td>
                                <td class="py-3 pr-4 text-secondary">{{ auditDetail(entry) }}</td>
                            </tr>
                        </tbody>
                    </table>
                    <p v-if="!auditLogs.length" class="py-6 text-sm text-secondary">{{ tx('club_memberships.empty_audit', 'Noch keine Audit-Einträge.') }}</p>
                </div>
            </section>

            <section v-if="activeTab === 'exports'" class="surface-card p-5">
                <ClubSepaBatches v-if="capabilities.sepa_export !== false" :key="selectedClub.id" :club-id="selectedClub.id" :invoices="invoices" />
                <p v-if="financeActionFeedback" class="mb-4 rounded-lg border border-success/30 bg-success/10 px-3 py-2 text-sm font-semibold text-success" aria-live="polite">
                    {{ financeActionFeedback }}
                </p>
                <p v-if="financeActionError" class="mb-4 rounded-lg border border-error/30 bg-error/10 px-3 py-2 text-sm font-semibold text-error" role="alert">
                    {{ financeActionError }}
                </p>
                <div class="flex flex-col gap-4 xl:flex-row xl:items-start xl:justify-between">
                    <div class="max-w-2xl">
                        <h2 class="text-lg font-semibold text-primary">{{ tx('club_memberships.workspace.sepa_debit', 'SEPA-Lastschrift') }}</h2>
                        <p class="mt-1 text-sm text-secondary">
                            {{ tx('club_memberships.workspace.sepa_intro', 'Hinterlege die Vereinsdaten und exportiere offene Rechnungen mit aktivem Mandat als SEPA-XML.') }}
                        </p>
                    </div>

                    <a
                        :href="route('auth.club-memberships.sepa-export', selectedClub.id)"
                        class="rounded-lg bg-buttonPrimary px-4 py-2 text-sm font-semibold text-buttonTextPrimary disabled:cursor-not-allowed disabled:opacity-50"
                        :class="{ 'pointer-events-none opacity-50': capabilities.sepa_export === false }"
                        :title="capabilities.sepa_export === false ? tx('club_memberships.workspace.sepa_export_unavailable', 'SEPA-Export ist ab Pro verfügbar') : ''"
                    >
                        {{ tx('club_memberships.workspace.sepa_export', 'SEPA-XML exportieren') }}
                    </a>
                </div>

                <form class="mt-4 grid gap-3 md:grid-cols-[1fr_1fr_1fr_1fr_auto]" @submit.prevent="saveSepaSettings">
                    <div>
                        <label class="text-xs font-semibold uppercase text-secondary">{{ tx('club_memberships.workspace.creditor_id', 'Gläubiger-ID') }}</label>
                        <input v-model="sepaSettingsFor(selectedClub).sepa_creditor_id" class="mt-1 w-full rounded-lg border border-border bg-inputBg px-3 py-2 text-sm text-primary" placeholder="DE98ZZZ09999999999">
                    </div>
                    <div>
                        <label class="text-xs font-semibold uppercase text-secondary">{{ tx('club_memberships.workspace.account_holder', 'Kontoinhaber') }}</label>
                        <input v-model="sepaSettingsFor(selectedClub).sepa_account_holder" class="mt-1 w-full rounded-lg border border-border bg-inputBg px-3 py-2 text-sm text-primary" placeholder="Name laut Bankkonto">
                    </div>
                    <div>
                        <label class="text-xs font-semibold uppercase text-secondary">{{ tx('club_memberships.workspace.club_iban', 'Vereins-IBAN') }}</label>
                        <input v-model="sepaSettingsFor(selectedClub).sepa_iban" class="mt-1 w-full rounded-lg border border-border bg-inputBg px-3 py-2 text-sm text-primary" placeholder="DE...">
                    </div>
                    <div>
                        <label class="text-xs font-semibold uppercase text-secondary">{{ tx('club_memberships.workspace.bic_optional', 'BIC optional') }}</label>
                        <input v-model="sepaSettingsFor(selectedClub).sepa_bic" class="mt-1 w-full rounded-lg border border-border bg-inputBg px-3 py-2 text-sm text-primary" placeholder="GENODE...">
                    </div>
                    <div class="flex items-end">
                        <button
                            class="w-full rounded-lg border border-border px-4 py-2 text-sm font-semibold text-primary disabled:cursor-not-allowed disabled:opacity-50"
                            :disabled="capabilities.sepa_export === false || financeSettingsSaving"
                            :title="capabilities.sepa_export === false ? tx('club_memberships.workspace.sepa_export_unavailable', 'SEPA-Export ist ab Pro verfügbar') : ''"
                        >
                            {{ tx('club_memberships.workspace.save', 'Speichern') }}
                        </button>
                    </div>
                </form>
            </section>

            <section v-if="activeTab === 'members' && externalMembers.length" class="surface-card p-5">
                <h2 class="text-lg font-semibold text-primary">{{ tx('club_memberships.workspace.external_title', 'Externe Mitglieder ohne Verknüpfung') }}</h2>
                <p class="mt-1 text-sm text-secondary">
                    {{ tx('club_memberships.workspace.external_intro', 'Diese Personen sind im Verein hinterlegt, aber noch nicht mit einem Airmius-Konto verbunden.') }}
                </p>

                <div class="mt-4 grid gap-3 lg:grid-cols-2">
                    <article v-for="member in filteredExternalMembers" :key="member.id" class="rounded-lg border border-border bg-bg p-4">
                        <div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
                            <div>
                                <h3 class="font-semibold text-primary">{{ member.name || member.email }}</h3>
                                <p class="text-sm text-secondary">{{ member.email }}</p>
                                <p v-if="member.phone || member.city" class="mt-1 text-xs text-secondary">
                                    {{ [member.phone, member.postal_code, member.city].filter(Boolean).join(' · ') }}
                                </p>
                                <p class="mt-1 text-xs text-secondary">
                                    {{ tx('club_memberships.workspace.membership_type', 'Mitgliedschaftstyp') }}: {{ member.membership_type?.name || tx('auto.Kein Typ', 'Kein Typ') }}
                                </p>
                                <p class="mt-1 text-xs text-secondary">
                                    {{ tx('club_memberships.workspace.end', 'Ende') }}: {{ formatDate(member.membership_ends_on) }}
                                </p>
                                <p class="mt-2 text-xs text-secondary">
                                    {{ tx('club_memberships.workspace.member_number', 'Mitgliedsnummer') }}: {{ member.member_number || '-' }} · {{ tx('club_memberships.workspace.license_number', 'Lizenznummer') }}: {{ member.athlete_license_number || '-' }} · {{ tx('auto.Gültig bis', 'Gültig bis') }}: {{ formatDate(member.athlete_license_valid_until) }}
                                </p>
                                <p class="mt-1 text-xs text-secondary">
                                    {{ tx('club_memberships.workspace.invitation', 'Einladung') }}: {{ member.invitation_status === 'pending' ? tx('club_memberships.workspace.sent', 'gesendet') : member.invitation_status === 'linked' ? tx('club_memberships.workspace.linked', 'verknüpft') : tx('club_memberships.workspace.not_sent', 'nicht gesendet') }}
                                </p>
                                <div v-if="member.duplicate_candidate" class="mt-3 rounded-lg border border-warning/40 bg-warning/10 p-3 text-xs text-primary">
                                    <p class="font-semibold">{{ tx('club_memberships.workspace.duplicate_detected', 'Mögliche Dublette erkannt') }}</p>
                                    <p v-if="!member.duplicate_candidate.ambiguous" class="mt-1">
                                        {{ tx('club_memberships.workspace.duplicate_matches', 'Übereinstimmung mit {name} ({email}) über: {reasons}', {
                                            name: member.duplicate_candidate.name,
                                            email: member.duplicate_candidate.email,
                                            reasons: member.duplicate_candidate.reasons.join(', '),
                                        }) }}
                                    </p>
                                    <p v-else class="mt-1">{{ tx('club_memberships.workspace.duplicate_ambiguous', 'Mehrere Akten passen. Korrigiere zuerst E-Mail oder Mitgliedsnummer des externen Datensatzes.') }}</p>
                                </div>
                            </div>

                            <div class="flex flex-wrap gap-2">
                            <button type="button" class="rounded-lg border border-border px-3 py-2 text-sm font-semibold text-primary" @click="openExternalMemberEdit(member)">
                                {{ tx('auto.Bearbeiten', 'Bearbeiten') }}
                            </button>
                            <button type="button" class="rounded-lg border border-border px-3 py-2 text-sm font-semibold text-primary" @click="openTimeline(member, true)">
                                {{ tx('club_memberships.workspace.timeline', 'Verlauf') }}
                            </button>
                            <button
                                type="button"
                                class="rounded-lg border border-border px-3 py-2 text-sm font-semibold text-primary"
                                :disabled="capabilities.invoices === false"
                                :title="capabilities.invoices === false ? tx('club_memberships.workspace.invoices_unavailable', 'Rechnungen sind ab Starter verfügbar') : ''"
                                @click="openInvoice({ ...member, is_external: true })"
                            >
                                {{ tx('club_memberships.workspace.create_invoice', 'Rechnung erstellen') }}
                            </button>
                            <button
                                v-if="member.duplicate_candidate?.user_id"
                                type="button"
                                class="rounded-lg border border-warning/50 px-3 py-2 text-sm font-semibold text-primary hover:bg-warning/10"
                                @click="openDuplicateMerge(member)"
                            >
                                {{ tx('club_memberships.workspace.review_duplicate', 'Prüfen & zusammenführen') }}
                            </button>
                            <button
                                v-else-if="member.invitation_status !== 'linked'"
                                type="button"
                                class="rounded-lg bg-buttonPrimary px-3 py-2 text-sm font-semibold text-buttonTextPrimary"
                                @click="inviteExternalMember(member)"
                            >
                                {{ tx('club_memberships.workspace.invite_link', 'Einladung/Verknüpfung') }}
                            </button>
                            </div>
                        </div>
                        <form v-if="invoiceMemberId === `external-${member.id}`" class="mt-4 grid gap-3 rounded-lg border border-border bg-bg p-4 sm:grid-cols-2 lg:grid-cols-4" @submit.prevent="createInvoice({ ...member, is_external: true })">
                            <input v-model="invoiceForm.title" :aria-label="tx('auto.Titel', 'Titel')" class="rounded-lg border border-border bg-inputBg px-3 py-2 text-sm text-primary" :placeholder="tx('auto.Titel', 'Titel')">
                            <input v-model="invoiceForm.amount" type="number" :min="invoiceForm.waived ? '0' : '0.01'" step="0.01" :aria-label="tx('auto.Betrag', 'Betrag')" class="rounded-lg border border-border bg-inputBg px-3 py-2 text-sm text-primary" :placeholder="tx('auto.Betrag', 'Betrag')">
                            <input v-model="invoiceForm.billing_period_start" type="date" :aria-label="tx('club_memberships.workspace.billing_period_start', 'Zeitraum von')" class="rounded-lg border border-border bg-inputBg px-3 py-2 text-sm text-primary">
                            <input v-model="invoiceForm.billing_period_end" type="date" :aria-label="tx('club_memberships.workspace.billing_period_end', 'Zeitraum bis')" class="rounded-lg border border-border bg-inputBg px-3 py-2 text-sm text-primary">
                            <input v-model="invoiceForm.due_date" type="date" :aria-label="tx('club_memberships.workspace.due_date', 'Fällig am')" class="rounded-lg border border-border bg-inputBg px-3 py-2 text-sm text-primary">
                            <button class="rounded-lg bg-buttonPrimary px-4 py-2 text-sm font-semibold text-buttonTextPrimary disabled:cursor-wait disabled:opacity-60" :disabled="invoiceForm.processing">
                                {{ invoiceForm.processing ? tx('auto.Wird gespeichert …', 'Wird gespeichert …') : tx('auto.Erstellen', 'Erstellen') }}
                            </button>
                            <label class="flex items-start gap-3 rounded-lg border border-air-green/30 bg-air-green/10 p-3 text-sm text-primary sm:col-span-2 lg:col-span-4">
                                <input v-model="invoiceForm.waived" type="checkbox" class="mt-1 rounded border-border text-air-green">
                                <span>
                                    <span class="block font-semibold">{{ tx('club_memberships.workspace.waive_invoice', 'Kulanz-Erlass buchen') }}</span>
                                    <span class="block text-xs text-secondary">{{ tx('club_memberships.workspace.waive_invoice_hint', 'Für diesen Zeitraum wird 0,00 € verbucht und keine Zahlung als bezahlt erfasst.') }}</span>
                                </span>
                            </label>
                            <input v-if="invoiceForm.waived" v-model="invoiceForm.waiver_reason" :aria-label="tx('club_memberships.workspace.waiver_reason', 'Grund für den Erlass')" class="rounded-lg border border-border bg-inputBg px-3 py-2 text-sm text-primary sm:col-span-2 lg:col-span-4" :placeholder="tx('club_memberships.workspace.waiver_reason_placeholder', 'Grund, z. B. Kulanz für Oktober')">
                            <textarea v-model="invoiceForm.description" rows="2" :aria-label="tx('auto.Beschreibung optional', 'Beschreibung optional')" class="rounded-lg border border-border bg-inputBg px-3 py-2 text-sm text-primary sm:col-span-2 lg:col-span-4" :placeholder="tx('auto.Beschreibung optional', 'Beschreibung optional')"></textarea>
                            <p v-if="Object.keys(invoiceForm.errors).length" class="text-sm font-semibold text-error sm:col-span-2 lg:col-span-4" role="alert">{{ Object.values(invoiceForm.errors)[0] }}</p>
                        </form>
                        <ClubMetadataSubjectEditor class="mt-3" :club-id="selectedClub.id" subject-type="external_member" :subject-id="member.id" :subject-label="member.name || member.email" />
                    </article>
                    <p v-if="!filteredExternalMembers.length" class="text-sm text-secondary">{{ tx('auto.Keine passenden externen Personen gefunden.', 'Keine passenden externen Personen gefunden.') }}</p>
                </div>
            </section>

            <Modal :show="Boolean(duplicateMergeMember)" max-width="lg" @close="closeDuplicateMerge">
                <form v-if="duplicateMergeMember" class="space-y-4 p-6" @submit.prevent="mergeDuplicate">
                    <div>
                        <h2 class="text-xl font-bold text-primary">{{ tx('club_memberships.workspace.merge_duplicate_title', 'Dublette kontrolliert zusammenführen') }}</h2>
                        <p class="mt-2 text-sm text-secondary">
                            {{ duplicateMergeMember.name || duplicateMergeMember.email }} → {{ duplicateMergeMember.duplicate_candidate.name }}
                        </p>
                    </div>

                    <fieldset class="space-y-2">
                        <legend class="text-xs font-semibold uppercase text-secondary">{{ tx('club_memberships.workspace.conflict_resolution', 'Umgang mit abweichenden Daten') }}</legend>
                        <label class="flex items-start gap-3 rounded-lg border border-border p-3 text-sm text-primary">
                            <input v-model="duplicateMergeForm.resolution" type="radio" value="keep_registered" class="mt-1">
                            <span><strong class="block">{{ tx('club_memberships.workspace.keep_registered', 'Bestehende Akte bevorzugen') }}</strong>{{ tx('club_memberships.workspace.keep_registered_hint', 'Nur bisher leere Felder werden aus der externen Akte ergänzt.') }}</span>
                        </label>
                        <label class="flex items-start gap-3 rounded-lg border border-border p-3 text-sm text-primary">
                            <input v-model="duplicateMergeForm.resolution" type="radio" value="use_external" class="mt-1">
                            <span><strong class="block">{{ tx('club_memberships.workspace.use_external', 'Externe Fachdaten übernehmen') }}</strong>{{ tx('club_memberships.workspace.use_external_hint', 'Mitgliedschafts- und Beitragsdaten werden übernommen; Rollen und Rechte bleiben unverändert.') }}</span>
                        </label>
                    </fieldset>

                    <div>
                        <label for="duplicate-confirm-email" class="text-xs font-semibold uppercase text-secondary">{{ tx('club_memberships.workspace.confirm_external_email', 'Externe E-Mail zur Bestätigung eingeben') }}</label>
                        <input id="duplicate-confirm-email" v-model.trim="duplicateMergeForm.confirm_email" type="email" required autocomplete="off" class="mt-1 w-full rounded-lg border border-border bg-inputBg px-3 py-2 text-sm text-primary" :placeholder="duplicateMergeMember.email">
                        <p v-if="duplicateMergeForm.errors.confirm_email" class="mt-1 text-sm text-error">{{ duplicateMergeForm.errors.confirm_email }}</p>
                        <p v-if="duplicateMergeForm.errors.target_user_id" class="mt-1 text-sm text-error">{{ duplicateMergeForm.errors.target_user_id }}</p>
                        <p v-if="duplicateMergeForm.errors.member_number" class="mt-1 text-sm text-error">{{ duplicateMergeForm.errors.member_number }}</p>
                    </div>

                    <div class="flex justify-end gap-2">
                        <button type="button" class="rounded-lg border border-border px-4 py-2 text-sm font-semibold text-primary" :disabled="duplicateMergeForm.processing" @click="closeDuplicateMerge">{{ tx('auto.Abbrechen', 'Abbrechen') }}</button>
                        <button class="rounded-lg bg-buttonPrimary px-4 py-2 text-sm font-semibold text-buttonTextPrimary disabled:opacity-60" :disabled="duplicateMergeForm.processing">{{ tx('club_memberships.workspace.merge_now', 'Jetzt zusammenführen') }}</button>
                    </div>
                </form>
            </Modal>

            <Modal :show="Boolean(timelineMember)" max-width="2xl" @close="closeTimeline">
                <div v-if="timelineMember" class="space-y-5 p-6">
                    <div>
                        <h2 class="text-xl font-bold text-primary">{{ tx('club_memberships.workspace.timeline_title', 'Mitgliedschaftsverlauf') }}</h2>
                        <p class="mt-1 text-sm text-secondary">{{ timelineMember.name || timelineMember.email }}</p>
                    </div>
                    <div class="max-h-72 space-y-2 overflow-y-auto">
                        <article v-for="entry in timelineEntriesFor(timelineMember)" :key="entry.id" class="rounded-lg border border-border bg-bg p-3">
                            <div class="flex items-start justify-between gap-3">
                                <div>
                                    <p class="text-xs font-semibold uppercase text-secondary">{{ formatDate(entry.occurred_on) }} · {{ tx(`club_memberships.workspace.timeline_type_${entry.type}`, entry.type) }}</p>
                                    <h3 class="mt-1 font-semibold text-primary">{{ entry.title }}</h3>
                                    <p v-if="entry.description" class="mt-1 text-sm text-secondary">{{ entry.description }}</p>
                                    <p v-if="entry.from_value !== null || entry.to_value !== null" class="mt-1 text-xs text-secondary">{{ entry.from_value || '–' }} → {{ entry.to_value || '–' }}</p>
                                </div>
                                <button v-if="['honor', 'anniversary', 'note'].includes(entry.type)" type="button" class="text-xs font-semibold text-error" @click="deleteTimelineEntry(entry)">{{ tx('auto.Löschen', 'Löschen') }}</button>
                            </div>
                        </article>
                        <p v-if="!timelineEntriesFor(timelineMember).length" class="text-sm text-secondary">{{ tx('club_memberships.workspace.timeline_empty', 'Noch keine Verlaufseinträge.') }}</p>
                    </div>
                    <form class="grid gap-3 rounded-xl border border-border bg-bg p-4 md:grid-cols-2" @submit.prevent="saveTimelineEntry">
                        <div>
                            <label class="text-xs font-semibold uppercase text-secondary">{{ tx('club_memberships.workspace.timeline_type', 'Art') }}</label>
                            <select v-model="timelineForm.type" class="mt-1 w-full rounded-lg border border-border bg-inputBg px-3 py-2 text-sm text-primary">
                                <option value="honor">{{ tx('club_memberships.workspace.timeline_type_honor', 'Ehrung') }}</option>
                                <option value="anniversary">{{ tx('club_memberships.workspace.timeline_type_anniversary', 'Jubiläum') }}</option>
                                <option value="note">{{ tx('club_memberships.workspace.timeline_type_note', 'Verlaufsnotiz') }}</option>
                            </select>
                        </div>
                        <div>
                            <label class="text-xs font-semibold uppercase text-secondary">{{ tx('auto.Datum', 'Datum') }}</label>
                            <input v-model="timelineForm.occurred_on" type="date" required class="mt-1 w-full rounded-lg border border-border bg-inputBg px-3 py-2 text-sm text-primary">
                        </div>
                        <div class="md:col-span-2">
                            <label class="text-xs font-semibold uppercase text-secondary">{{ tx('auto.Titel', 'Titel') }}</label>
                            <input v-model.trim="timelineForm.title" required maxlength="255" class="mt-1 w-full rounded-lg border border-border bg-inputBg px-3 py-2 text-sm text-primary">
                            <p v-if="timelineForm.errors.title" class="mt-1 text-sm text-error">{{ timelineForm.errors.title }}</p>
                        </div>
                        <div class="md:col-span-2">
                            <label class="text-xs font-semibold uppercase text-secondary">{{ tx('auto.Beschreibung', 'Beschreibung') }}</label>
                            <textarea v-model.trim="timelineForm.description" rows="3" maxlength="2000" class="mt-1 w-full rounded-lg border border-border bg-inputBg px-3 py-2 text-sm text-primary"></textarea>
                        </div>
                        <div class="flex justify-end gap-2 md:col-span-2">
                            <button type="button" class="rounded-lg border border-border px-4 py-2 text-sm font-semibold text-primary" @click="closeTimeline">{{ tx('auto.Schließen', 'Schließen') }}</button>
                            <button class="rounded-lg bg-buttonPrimary px-4 py-2 text-sm font-semibold text-buttonTextPrimary disabled:opacity-60" :disabled="timelineForm.processing">{{ tx('auto.Speichern', 'Speichern') }}</button>
                        </div>
                    </form>
                </div>
            </Modal>

            <Modal :show="Boolean(externalMemberEdit)" max-width="2xl" @close="closeExternalMemberEdit">
                <form v-if="externalMemberEdit" class="space-y-5 p-6" @submit.prevent="saveExternalMember">
                    <div>
                        <h2 class="text-xl font-bold text-primary">{{ tx('club_memberships.workspace.external_record', 'Externe Personenakte bearbeiten') }}</h2>
                        <p class="mt-1 text-sm text-secondary">{{ tx('club_memberships.workspace.external_record_hint', 'Kontaktdaten und Mitgliedschaft bleiben auch ohne Airmius-Konto vollständig pflegbar.') }}</p>
                    </div>
                    <div class="grid gap-3 md:grid-cols-2">
                        <label class="text-sm text-primary">{{ tx('club_memberships.workspace.name', 'Name') }}
                            <input v-model.trim="externalMemberForm.name" maxlength="255" class="mt-1 w-full rounded-lg border border-border bg-inputBg px-3 py-2">
                        </label>
                        <label class="text-sm text-primary">{{ tx('club_memberships.workspace.email', 'E-Mail') }}
                            <input v-model.trim="externalMemberForm.email" type="email" required class="mt-1 w-full rounded-lg border border-border bg-inputBg px-3 py-2">
                            <span v-if="externalMemberForm.errors.email" class="mt-1 block text-xs text-error">{{ externalMemberForm.errors.email }}</span>
                        </label>
                        <label class="text-sm text-primary">{{ tx('auto.Telefon', 'Telefon') }}
                            <input v-model.trim="externalMemberForm.phone" maxlength="40" class="mt-1 w-full rounded-lg border border-border bg-inputBg px-3 py-2">
                        </label>
                        <label class="text-sm text-primary">{{ tx('club_memberships.workspace.membership_type', 'Mitgliedschaftstyp') }}
                            <select v-model="externalMemberForm.club_membership_type_id" class="mt-1 w-full rounded-lg border border-border bg-inputBg px-3 py-2">
                                <option value="">{{ tx('auto.Kein Typ', 'Kein Typ') }}</option>
                                <option v-for="type in membershipTypes" :key="type.id" :value="type.id">{{ type.name }}</option>
                            </select>
                        </label>
                        <label class="text-sm text-primary">{{ tx('auto.Status', 'Status') }}
                            <select v-model="externalMemberForm.membership_status" class="mt-1 w-full rounded-lg border border-border bg-inputBg px-3 py-2">
                                <option v-for="status in membershipStatuses" :key="status" :value="status">{{ statusLabel(status) }}</option>
                            </select>
                        </label>
                        <label class="text-sm text-primary">{{ tx('club_memberships.workspace.club_role', 'Vereinsrolle') }}
                            <select v-model="externalMemberForm.role" class="mt-1 w-full rounded-lg border border-border bg-inputBg px-3 py-2">
                                <option v-for="role in clubRoles" :key="role.value" :value="role.value">{{ role.label }}</option>
                            </select>
                        </label>
                        <label class="text-sm text-primary md:col-span-2">{{ tx('auto.Straße', 'Straße') }}
                            <div class="mt-1 grid grid-cols-[1fr_8rem] gap-2">
                                <input v-model.trim="externalMemberForm.street" maxlength="255" class="w-full rounded-lg border border-border bg-inputBg px-3 py-2">
                                <input v-model.trim="externalMemberForm.house_number" maxlength="40" :placeholder="tx('auto.Hausnummer', 'Hausnummer')" class="w-full rounded-lg border border-border bg-inputBg px-3 py-2">
                            </div>
                        </label>
                        <label class="text-sm text-primary">{{ tx('auto.PLZ', 'PLZ') }}
                            <input v-model.trim="externalMemberForm.postal_code" maxlength="30" class="mt-1 w-full rounded-lg border border-border bg-inputBg px-3 py-2">
                        </label>
                        <label class="text-sm text-primary">{{ tx('auto.Ort', 'Ort') }}
                            <input v-model.trim="externalMemberForm.city" maxlength="255" class="mt-1 w-full rounded-lg border border-border bg-inputBg px-3 py-2">
                        </label>
                        <label class="text-sm text-primary">{{ tx('auto.Land', 'Land') }}
                            <input v-model.trim="externalMemberForm.country" maxlength="2" placeholder="DE" class="mt-1 w-full rounded-lg border border-border bg-inputBg px-3 py-2 uppercase">
                        </label>
                    </div>
                    <div class="flex justify-end gap-2">
                        <button type="button" class="rounded-lg border border-border px-4 py-2 text-sm font-semibold text-primary" :disabled="externalMemberForm.processing" @click="closeExternalMemberEdit">{{ tx('auto.Abbrechen', 'Abbrechen') }}</button>
                        <button class="rounded-lg bg-buttonPrimary px-4 py-2 text-sm font-semibold text-buttonTextPrimary disabled:opacity-60" :disabled="externalMemberForm.processing">{{ tx('auto.Speichern', 'Speichern') }}</button>
                    </div>
                </form>
            </Modal>

            <section v-if="activeTab === 'requests'" class="surface-card p-5">
                <div class="flex items-center justify-between gap-4">
                    <div>
                        <h2 class="text-lg font-semibold text-primary">{{ tx('club_memberships.workspace.requests_title', 'Offene Beitrittsanfragen') }}</h2>
                        <p class="mt-1 text-sm text-secondary">
                            {{ tx('club_memberships.workspace.requests_intro', 'Personen treten erst nach Annahme dem Team und Verein bei.') }}
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
                                    <span v-if="request.type === 'pause'">{{ tx('club_memberships.workspace.pause_request', 'möchte die Mitgliedschaft pausieren') }}</span>
                                    <span v-else-if="request.type === 'removal_objection'">{{ tx('club_memberships.workspace.removal_objection', 'widerspricht der Entfernung aus dem Verein') }}</span>
                                    <span v-else>{{ tx('club_memberships.workspace.wants_membership', 'möchte Vereinsmitglied werden') }}</span>
                                </p>
                                <p v-if="request.membership_type" class="mt-1 text-xs text-secondary">
                                    {{ tx('club_memberships.workspace.type', 'Typ') }}: {{ request.membership_type.name }} · {{ tx('club_memberships.workspace.preview', 'Vorschau') }} {{ formatMoney(request.preview_amount) }} / {{ intervalLabel(request.preview_interval) }}
                                </p>
                                <p v-if="request.requested_pause_from" class="mt-1 text-xs text-secondary">
                                    {{ tx('club_memberships.workspace.pause', 'Pause') }}: {{ formatDate(request.requested_pause_from) }} {{ tx('club_memberships.workspace.until', 'bis') }} {{ formatDate(request.requested_pause_until) }}
                                </p>
                                <p v-if="request.message" class="mt-2 text-sm text-secondary">{{ request.message }}</p>
                                <p v-if="request.status === 'information_requested'" class="mt-2 rounded-lg border border-warning/40 bg-warning/10 p-2 text-sm text-primary">
                                    <span class="font-semibold">{{ tx('club_memberships.workspace.information_requested', 'Rückfrage gesendet') }}:</span>
                                    {{ request.information_request_message }}
                                </p>
                                <p v-if="request.status === 'waitlisted'" class="mt-2 rounded-lg border border-border bg-muted p-2 text-sm text-secondary">
                                    {{ tx('club_memberships.workspace.waitlisted', 'Auf Warteliste') }}<span v-if="request.review_note"> · {{ request.review_note }}</span>
                                </p>
                                <p v-if="request.applicant_response_message" class="mt-2 rounded-lg border border-success/30 bg-success/5 p-2 text-sm text-secondary">
                                    <span class="font-semibold text-primary">{{ tx('club_memberships.workspace.applicant_response', 'Antwort') }}:</span>
                                    {{ request.applicant_response_message }}
                                </p>
                                <div v-if="request.application_data && Object.keys(request.application_data).length" class="mt-3 grid gap-2 rounded-lg border border-border bg-bg p-3 text-xs text-secondary md:grid-cols-2">
                                    <p v-for="(value, key) in request.application_data" :key="key">
                                        <span class="font-semibold text-primary">{{ requestDataLabel(key) }}:</span>
                                        {{ requestDataValue(key, value) }}
                                    </p>
                                </div>
                                <div v-if="request.accepted_documents?.length" class="mt-3 rounded-lg border border-border bg-bg p-3 text-xs text-secondary">
                                    <p class="font-semibold text-primary">{{ tx('club_memberships.workspace.confirmed_documents', 'Bestätigte Dokumente') }}</p>
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
                                        {{ tx('club_memberships.workspace.payment_method', 'Zahlmethode') }}: {{ paymentMethodLabel(request.preferred_payment_method) }}
                                    </span>
                                    <span v-if="request.requested_billing_interval" class="rounded-full bg-muted px-2 py-1 text-secondary">
                                        {{ tx('club_memberships.workspace.interval', 'Intervall') }}: {{ intervalLabel(request.requested_billing_interval) }}
                                    </span>
                                </div>
                            </div>

                            <div class="flex flex-wrap gap-2">
                                <button v-if="request.status !== 'information_requested'" type="button" class="rounded-lg bg-buttonPrimary px-4 py-2 text-sm font-semibold text-buttonTextPrimary disabled:cursor-wait disabled:opacity-60" :disabled="processingClubRequestIds.has(request.id)" @click="approveClubRequest(request)">
                                    {{ processingClubRequestIds.has(request.id) ? tx('auto.Wird gespeichert …', 'Wird gespeichert …') : tx('club_memberships.workspace.accept', 'Annehmen') }}
                                </button>
                                <button v-if="request.status === 'pending' || request.status === 'waitlisted'" type="button" class="rounded-lg border border-border px-4 py-2 text-sm font-semibold text-primary disabled:cursor-wait disabled:opacity-60" :disabled="processingClubRequestIds.has(request.id)" @click="requestClubInformation(request)">
                                    {{ tx('club_memberships.workspace.request_information', 'Angaben nachfordern') }}
                                </button>
                                <button v-if="request.status === 'pending'" type="button" class="rounded-lg border border-border px-4 py-2 text-sm font-semibold text-primary disabled:cursor-wait disabled:opacity-60" :disabled="processingClubRequestIds.has(request.id)" @click="waitlistClubRequest(request)">
                                    {{ tx('club_memberships.workspace.waitlist', 'Warteliste') }}
                                </button>
                                <button type="button" class="rounded-lg border border-border px-4 py-2 text-sm font-semibold text-primary disabled:cursor-wait disabled:opacity-60" :disabled="processingClubRequestIds.has(request.id)" @click="declineClubRequest(request)">
                                    {{ tx('club_memberships.workspace.decline', 'Ablehnen') }}
                                </button>
                            </div>
                        </div>
                    </article>

                    <article v-for="request in pendingRequests" :key="request.id" class="rounded-lg border border-border bg-bg p-4">
                        <div class="flex flex-col gap-3 md:flex-row md:items-center md:justify-between">
                            <div>
                                <p class="font-semibold text-primary">{{ request.user.name }}</p>
                                <p class="text-sm text-secondary">
                                    {{ request.user.email }} {{ tx('club_memberships.workspace.team_join_request', 'möchte zu {team}', { team: request.team.name }) }}
                                </p>
                            </div>

                            <div class="flex gap-2">
                                <button
                                    type="button"
                                    class="rounded-lg bg-buttonPrimary px-4 py-2 text-sm font-semibold text-buttonTextPrimary disabled:opacity-60"
                                    :disabled="processingJoinRequestIds.has(request.id)"
                                    @click="approveRequest(request)"
                                >
                                    {{ tx('club_memberships.workspace.accept', 'Annehmen') }}
                                </button>
                                <button
                                    type="button"
                                    class="rounded-lg border border-border px-4 py-2 text-sm font-semibold text-primary disabled:opacity-60"
                                    :disabled="processingJoinRequestIds.has(request.id)"
                                    @click="declineRequest(request)"
                                >
                                    {{ tx('club_memberships.workspace.decline', 'Ablehnen') }}
                                </button>
                            </div>
                        </div>
                    </article>

                    <p v-if="!pendingRequests.length && !clubRequests.length" class="rounded-lg border border-border bg-bg p-4 text-sm text-secondary">
                        {{ tx('club_memberships.workspace.no_requests', 'Keine offenen Anfragen.') }}
                    </p>
                </div>
            </section>

            <section v-if="activeTab === 'rules'" class="space-y-6">
                <section class="surface-card overflow-hidden">
                    <div class="border-b border-border bg-gradient-to-r from-buttonPrimary/10 to-transparent p-5">
                        <div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
                            <div>
                                <p class="text-xs font-semibold uppercase tracking-wide text-air-blue">{{ tx('club_memberships.workspace.wizard_title', 'Beitrittsregeln einrichten') }}</p>
                                <h2 class="mt-1 text-xl font-bold text-primary">{{ rulesWizardSteps[rulesWizardStep].label }}</h2>
                                <p class="mt-1 max-w-2xl text-sm leading-6 text-secondary">{{ rulesWizardSteps[rulesWizardStep].hint }}</p>
                            </div>
                            <div class="flex shrink-0 items-center gap-3">
                                <button type="button" class="rounded-lg border border-border px-3 py-1.5 text-xs font-semibold text-secondary hover:bg-inputBg" @click="cancelRulesWizard">
                                    {{ tx('club_memberships.workspace.wizard_cancel', 'Abbrechen') }}
                                </button>
                                <span class="rounded-full bg-buttonPrimary/15 px-3 py-1.5 text-xs font-bold text-air-blue">
                                    {{ tx('club_memberships.workspace.wizard_step', 'Schritt') }} {{ rulesWizardStep + 1 }} / {{ rulesWizardSteps.length }}
                                </span>
                            </div>
                        </div>
                        <div class="mt-5 grid grid-cols-6 gap-2">
                            <button
                                v-for="(step, index) in rulesWizardSteps"
                                :key="step.key"
                                type="button"
                                class="group text-left"
                                @click="rulesWizardStep = index"
                            >
                                <div class="h-1.5 rounded-full transition" :class="index <= rulesWizardStep ? 'bg-buttonPrimary' : 'bg-inputBg'"></div>
                                <div class="mt-2 hidden text-xs font-semibold text-secondary sm:block">{{ index + 1 }}. {{ step.label }}</div>
                            </button>
                        </div>
                    </div>
                    <div class="flex items-center gap-3 p-4 text-sm text-secondary">
                        <i class="las la-lightbulb text-lg text-warning"></i>
                        <span>{{ tx('club_memberships.workspace.wizard_intro', 'Du kannst jederzeit zurückgehen. Deine Eingaben bleiben erhalten, bis du am Ende speicherst.') }}</span>
                    </div>
                </section>

                <div
                    class="grid gap-6"
                    :class="rulesWizardStep === 2 ? 'xl:grid-cols-[minmax(0,1fr)_24rem]' : 'xl:grid-cols-1'"
                >
                <div class="space-y-6">
                    <section v-if="rulesWizardStep >= 3" class="surface-card p-5">
                        <h2 class="text-lg font-semibold text-primary">{{ tx('club_memberships.workspace.online_requests', 'Online-Anfragen') }}</h2>
                        <form class="mt-4" @submit.prevent="saveMembershipSettings">
                            <div class="grid gap-3 md:grid-cols-3">
                            <label v-show="rulesWizardStep === 3" class="flex items-start gap-3 rounded-lg border border-border bg-bg p-3 text-sm text-primary">
                                <input v-model="membershipSettingsFor(selectedClub).membership_requests_enabled" type="checkbox" class="mt-1 rounded border-border bg-inputBg">
                                <span>
                                    <span class="block font-semibold">{{ tx('club_memberships.workspace.member_requests_enabled', 'Mitgliedsanfragen erlauben') }}</span>
                                    <span class="block text-secondary">{{ tx('club_memberships.workspace.member_requests_hint', 'Interessenten sehen die Beitragstypen und können eine Anfrage stellen.') }}</span>
                                </span>
                            </label>
                            <label v-show="rulesWizardStep === 3" class="flex items-start gap-3 rounded-lg border border-border bg-bg p-3 text-sm text-primary">
                                <input v-model="membershipSettingsFor(selectedClub).member_pause_requests_enabled" type="checkbox" class="mt-1 rounded border-border bg-inputBg">
                                <span>
                                    <span class="block font-semibold">{{ tx('club_memberships.workspace.pause_requests_enabled', 'Pausen-Anfragen erlauben') }}</span>
                                    <span class="block text-secondary">{{ tx('club_memberships.workspace.pause_requests_hint', 'Mitglieder können eine Pause beantragen; der Verein entscheidet.') }}</span>
                                </span>
                            </label>
                            <div v-show="rulesWizardStep === 4" class="rounded-lg border border-border bg-bg p-3 md:col-span-3">
                                <p class="text-sm font-semibold text-primary">{{ tx('club_memberships.workspace.allowed_payment_methods', 'Erlaubte Zahlmethoden') }}</p>
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
                            <div v-show="false" class="rounded-lg border border-border bg-bg p-3 md:col-span-3">
                                <p class="text-sm font-semibold text-primary">{{ tx('club_memberships.workspace.application_fields', 'Mitgliedsantrag-Felder') }}</p>
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
                            </div>
                            <div v-show="rulesWizardStep === 5" class="space-y-4">
                            <div class="rounded-lg border border-border bg-bg p-3 md:col-span-3">
                                <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                                    <div>
                                        <p class="text-sm font-semibold text-primary">{{ tx('club_memberships.workspace.documents_confirmations', 'Dokumente & Bestätigungen') }}</p>
                                        <p class="mt-1 text-xs text-secondary">{{ tx('club_memberships.workspace.documents_hint', 'Verknüpfe Datenschutz, Satzung, Regeln oder Beitragsordnung. Pflichtdokumente müssen Interessenten vor dem Absenden bestätigen.') }}</p>
                                    </div>
                                    <div class="flex flex-wrap gap-2">
                                        <button type="button" class="rounded-lg border border-border px-3 py-2 text-xs font-semibold text-primary hover:bg-inputBg" @click="addMembershipDocumentType">
                                            Dokument-Kategorien verwalten
                                        </button>
                                        <button type="button" class="rounded-lg bg-buttonPrimary px-3 py-2 text-xs font-semibold text-buttonTextPrimary" @click="addMembershipDocument">
                                            {{ tx('club_memberships.workspace.add_document', 'Dokument hinzufügen') }}
                                        </button>
                                    </div>
                                </div>
                                <div class="mt-3 rounded-xl border border-border bg-bg p-3">
                                    <div class="flex items-center justify-between gap-3">
                                        <div>
                                            <p class="text-sm font-semibold text-primary">{{ tx('club_memberships.workspace.document_types_multilingual_title', 'Dokument-Kategorien in allen Sprachen') }}</p>
                                            <p class="mt-1 text-xs text-secondary">{{ tx('club_memberships.workspace.document_types_multilingual_body', 'Lege Kategorien wie Datenschutz oder Satzung fest. Dateien lädst du unten im Bereich Dokumente hoch.') }}</p>
                                        </div>
                                        <button type="button" class="rounded-lg border border-border px-3 py-2 text-xs font-semibold text-primary hover:bg-inputBg" @click="addMembershipDocumentType">{{ tx('club_memberships.workspace.add_type', '+ Typ') }}</button>
                                    </div>
                                    <div class="mt-3 space-y-2">
                                        <div v-for="(type, index) in membershipDocumentTypes" :key="type.value" class="rounded-lg border border-border bg-card p-3">
                                            <div class="mb-2 flex items-center justify-between gap-3">
                                                <span class="text-xs font-semibold text-secondary">{{ type.value }}</span>
                                                <button v-if="!['privacy', 'statutes', 'rules', 'fees', 'sepa', 'other'].includes(type.value)" type="button" class="text-xs font-semibold text-error hover:underline" @click="removeMembershipDocumentType(index)">{{ tx('club_memberships.workspace.remove', 'Entfernen') }}</button>
                                            </div>
                                            <div class="grid gap-2 sm:grid-cols-2 xl:grid-cols-4">
                                                <label v-for="language in [{key:'de',label:'Deutsch'},{key:'en',label:'English'},{key:'fr',label:'Français'},{key:'ar',label:'العربية'}]" :key="language.key" class="text-xs font-semibold text-primary">{{ language.label }}<input v-model="type.labels[language.key]" class="mt-1 w-full rounded-lg border-border bg-inputBg text-sm font-normal text-primary" :placeholder="language.label"></label>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                <div class="mt-4 grid gap-3 md:grid-cols-[minmax(0,1fr)_14rem_auto]">
                                    <label class="flex items-center gap-2 rounded-lg border border-border bg-inputBg px-3 py-2">
                                        <i class="las la-search text-lg text-secondary"></i>
                                        <input v-model="documentSearch" class="min-w-0 flex-1 bg-transparent text-sm text-primary outline-none" :placeholder="tx('club_memberships.workspace.document_search_placeholder', 'Dokumente durchsuchen ...')">
                                    </label>
                                    <select v-model="documentTypeFilter" class="rounded-lg border-border bg-inputBg text-sm text-primary">
                                        <option value="all">{{ tx('club_memberships.workspace.all_document_types', 'Alle Dokument-Kategorien') }}</option>
                                        <option v-for="type in membershipDocumentTypes" :key="type.value" :value="type.value">{{ type.label }}</option>
                                    </select>
                                    <span class="flex items-center justify-center rounded-lg bg-bg px-3 py-2 text-xs font-semibold text-secondary">{{ filteredMembershipDocuments.length }} / {{ membershipSettingsFor(selectedClub).membership_application_documents.length }}</span>
                                </div>
                                <div class="mt-3 space-y-2">
                                    <article
                                        v-for="item in filteredMembershipDocuments"
                                        :key="item.document.id || item.index"
                                        class="overflow-hidden rounded-xl border border-border bg-card"
                                    >
                                        <button type="button" class="flex w-full items-center gap-3 p-3 text-left hover:bg-inputBg" @click="toggleMembershipDocument(item.document.id)">
                                            <span class="flex h-9 w-9 shrink-0 items-center justify-center rounded-lg bg-buttonPrimary/10 text-air-blue"><i class="las la-file-alt text-lg"></i></span>
                                            <span class="min-w-0 flex-1">
                                                <span class="block truncate text-sm font-semibold text-primary">{{ item.document.title || 'Dokument ohne Titel' }}</span>
                                                <span class="mt-1 flex flex-wrap gap-1.5 text-[11px] text-secondary">
                                                    <span class="rounded-full bg-bg px-2 py-0.5">{{ documentTypeLabel(item.document.type) }}</span>
                                                    <span class="rounded-full bg-bg px-2 py-0.5">{{ item.document.membership_type_id ? (membershipTypes.find((type) => type.id === item.document.membership_type_id)?.name || 'Typ') : 'Alle Typen' }}</span>
                                                    <span v-if="item.document.is_required" class="rounded-full bg-warning/15 px-2 py-0.5 text-warning">{{ tx('club_memberships.workspace.confirmation_required', 'Bestätigung erforderlich') }}</span>
                                                    <span v-if="item.document.is_visible" class="rounded-full bg-air-green/15 px-2 py-0.5 text-air-green">{{ tx('club_memberships.workspace.application_visible', 'Im Antrag sichtbar') }}</span>
                                                </span>
                                            </span>
                                            <i class="las text-lg text-secondary" :class="expandedDocumentId === item.document.id ? 'la-angle-up' : 'la-angle-down'"></i>
                                        </button>
                                        <div v-if="expandedDocumentId === item.document.id" class="border-t border-border p-3">
                                            <div class="grid gap-3 md:grid-cols-2">
                                                <label class="block text-sm">
                                                    <span class="font-semibold text-primary">{{ tx('club_memberships.workspace.type', 'Typ') }}</span>
                                                    <select v-model="item.document.type" class="mt-1 w-full rounded-lg border-border bg-inputBg text-sm text-primary">
                                                        <option v-for="type in membershipDocumentTypes" :key="type.value" :value="type.value">{{ type.label }}</option>
                                                    </select>
                                                </label>
                                                <label class="block text-sm">
                                                    <span class="font-semibold text-primary">{{ tx('club_memberships.workspace.applies_to_type', 'Gilt für Mitgliedschaftstyp') }}</span>
                                                    <select v-model="item.document.membership_type_id" class="mt-1 w-full rounded-lg border-border bg-inputBg text-sm text-primary">
                                                        <option :value="null">{{ tx('auto.Alle Typen', 'Alle Typen') }}</option>
                                                        <option v-for="type in membershipTypes" :key="type.id" :value="type.id">{{ type.name }}</option>
                                                    </select>
                                                </label>
                                                <label class="block text-sm">
                                                    <span class="font-semibold text-primary">{{ tx('club_memberships.workspace.document_title', 'Titel') }}</span>
                                                    <input v-model="item.document.title" class="mt-1 w-full rounded-lg border-border bg-inputBg text-sm text-primary" :placeholder="tx('club_memberships.workspace.document_title_placeholder', 'z. B. Datenschutzinformation')">
                                                </label>
                                                <label class="block text-sm md:col-span-2">
                                                    <span class="font-semibold text-primary">{{ tx('club_memberships.workspace.file_link', 'Link zur Datei oder Seite') }}</span>
                                                    <input v-model="item.document.url" type="url" class="mt-1 w-full rounded-lg border-border bg-inputBg text-sm text-primary" :placeholder="'https://...'">
                                                </label>
                                                <label class="block text-sm md:col-span-2">
                                                    <span class="font-semibold text-primary">{{ tx('club_memberships.workspace.upload_file', 'Oder Datei hochladen') }}</span>
                                                    <input type="file" class="mt-1 block w-full rounded-lg border border-border bg-inputBg px-3 py-2 text-sm text-primary" @change="attachMembershipDocumentFile(item.document, $event)">
                                                    <span v-if="item.document.file" class="mt-1 block text-xs text-air-blue">{{ tx('club_memberships.workspace.new_file', 'Neue Datei') }}: {{ item.document.file.name }}</span>
                                                    <a v-else-if="item.document.file_id" :href="item.document.url || '#'" target="_blank" rel="noreferrer" class="mt-1 inline-flex text-xs font-semibold text-air-blue hover:underline">{{ tx('club_memberships.workspace.open_saved_file', 'Gespeicherte Datei öffnen') }}: {{ item.document.file_name || item.document.title }}</a>
                                                    <span class="mt-1 block text-xs text-secondary">{{ tx('club_memberships.workspace.upload_location', 'Hochgeladene Dateien landen im Vereins-Dateimanager im Ordner „Mitgliedsantrag“.') }}</span>
                                                </label>
                                                <label class="block text-sm md:col-span-2">
                                                    <span class="font-semibold text-primary">{{ tx('club_memberships.workspace.hint_text', 'Hinweistext') }}</span>
                                                    <textarea v-model="item.document.description" rows="2" class="mt-1 w-full rounded-lg border-border bg-inputBg text-sm text-primary" :placeholder="tx('club_memberships.workspace.hint_placeholder', 'Optionaler Hinweis für Interessenten')"></textarea>
                                                </label>
                                                <label class="flex items-start gap-2 rounded-lg border border-border bg-bg p-3 text-sm text-primary"><input v-model="item.document.is_visible" type="checkbox" class="mt-1 rounded border-border bg-inputBg"><span><span class="block font-semibold">{{ tx('club_memberships.workspace.show_in_application', 'Im Antrag anzeigen') }}</span><span class="block text-xs text-secondary">{{ tx('club_memberships.workspace.show_in_application_hint', 'User sehen dieses Dokument vor dem Absenden.') }}</span></span></label>
                                                <label class="flex items-start gap-2 rounded-lg border border-border bg-bg p-3 text-sm text-primary"><input v-model="item.document.is_required" type="checkbox" class="mt-1 rounded border-border bg-inputBg"><span><span class="block font-semibold">{{ tx('club_memberships.workspace.confirmation_required', 'Bestätigung erforderlich') }}</span><span class="block text-xs text-secondary">{{ tx('club_memberships.workspace.confirmation_hint', 'Ohne Häkchen kann der Antrag nicht gesendet werden.') }}</span></span></label>
                                            </div>
                                            <div class="mt-3 flex items-center justify-between gap-3"><span class="text-xs text-secondary">{{ documentTypeLabel(item.document.type) }}</span><button type="button" class="rounded-lg border border-error/40 px-3 py-1.5 text-xs font-semibold text-error hover:bg-error/10" @click="removeMembershipDocument(item.index)">{{ tx('club_memberships.workspace.remove', 'Entfernen') }}</button></div>
                                        </div>
                                    </article>
                                    <p v-if="!filteredMembershipDocuments.length" class="rounded-lg border border-dashed border-border p-4 text-sm text-secondary">
                                        {{ documentSearch || documentTypeFilter !== 'all' ? 'Keine Dokumente für diesen Filter gefunden.' : tx('club_memberships.workspace.no_documents', 'Noch keine Dokumente verknüpft.') }}
                                    </p>
                                    <!--
                                        The former always-open editor is intentionally replaced by the compact,
                                        searchable document list above. Only the selected document is expanded.
                                    -->
                                    <!--
                                    <article
                                        :key="document.id || index"
                                        class="rounded-lg border border-border bg-card p-3"
                                    >
                                        <div class="grid gap-3 md:grid-cols-2">
                                            <label class="block text-sm">
                                                <span class="font-semibold text-primary">{{ tx('club_memberships.workspace.type', 'Typ') }}</span>
                                                <select v-model="document.type" class="mt-1 w-full rounded-lg border-border bg-inputBg text-sm text-primary">
                                                    <option v-for="type in membershipDocumentTypes" :key="type.value" :value="type.value">{{ type.label }}</option>
                                                </select>
                                            </label>
                                            <label class="block text-sm">
                                                <span class="font-semibold text-primary">{{ tx('club_memberships.workspace.applies_to_type', 'Gilt für Mitgliedschaftstyp') }}</span>
                                                <select v-model="document.membership_type_id" class="mt-1 w-full rounded-lg border-border bg-inputBg text-sm text-primary">
                                                    <option :value="null">{{ tx('auto.Alle Typen', 'Alle Typen') }}</option>
                                                    <option v-for="type in membershipTypes" :key="type.id" :value="type.id">{{ type.name }}</option>
                                                </select>
                                            </label>
                                            <label class="block text-sm">
                                                <span class="font-semibold text-primary">{{ tx('club_memberships.workspace.document_title', 'Titel') }}</span>
                                                <input v-model="document.title" class="mt-1 w-full rounded-lg border-border bg-inputBg text-sm text-primary" :placeholder="tx('club_memberships.workspace.document_title_placeholder', 'z. B. Datenschutzinformation')">
                                            </label>
                                            <label class="block text-sm md:col-span-2">
                                                <span class="font-semibold text-primary">{{ tx('club_memberships.workspace.file_link', 'Link zur Datei oder Seite') }}</span>
                                                <input v-model="document.url" type="url" class="mt-1 w-full rounded-lg border-border bg-inputBg text-sm text-primary" :placeholder="'https://...'">
                                            </label>
                                            <label class="block text-sm md:col-span-2">
                                                <span class="font-semibold text-primary">{{ tx('club_memberships.workspace.upload_file', 'Oder Datei hochladen') }}</span>
                                                <input
                                                    type="file"
                                                    class="mt-1 block w-full rounded-lg border border-border bg-inputBg px-3 py-2 text-sm text-primary"
                                                    @change="attachMembershipDocumentFile(document, $event)"
                                                >
                                                <span v-if="document.file" class="mt-1 block text-xs text-air-blue">
                                                    {{ tx('club_memberships.workspace.new_file', 'Neue Datei') }}: {{ document.file.name }}
                                                </span>
                                                <a
                                                    v-else-if="document.file_id"
                                                    :href="document.url || '#'"
                                                    target="_blank"
                                                    rel="noreferrer"
                                                    class="mt-1 inline-flex text-xs font-semibold text-air-blue hover:underline"
                                                >
                                                    {{ tx('club_memberships.workspace.open_saved_file', 'Gespeicherte Datei öffnen') }}: {{ document.file_name || document.title }}
                                                </a>
                                                <span class="mt-1 block text-xs text-secondary">
                                                    {{ tx('club_memberships.workspace.upload_location', 'Hochgeladene Dateien landen im Vereins-Dateimanager im Ordner „Mitgliedsantrag“.') }}
                                                </span>
                                            </label>
                                            <label class="block text-sm md:col-span-2">
                                                <span class="font-semibold text-primary">{{ tx('club_memberships.workspace.hint_text', 'Hinweistext') }}</span>
                                                <textarea v-model="document.description" rows="2" class="mt-1 w-full rounded-lg border-border bg-inputBg text-sm text-primary" :placeholder="tx('club_memberships.workspace.hint_placeholder', 'Optionaler Hinweis für Interessenten')"></textarea>
                                            </label>
                                            <label class="flex items-start gap-2 rounded-lg border border-border bg-bg p-3 text-sm text-primary">
                                                <input v-model="document.is_visible" type="checkbox" class="mt-1 rounded border-border bg-inputBg">
                                                <span>
                                                    <span class="block font-semibold">{{ tx('club_memberships.workspace.show_in_application', 'Im Antrag anzeigen') }}</span>
                                                    <span class="block text-xs text-secondary">{{ tx('club_memberships.workspace.show_in_application_hint', 'User sehen dieses Dokument vor dem Absenden.') }}</span>
                                                </span>
                                            </label>
                                            <label class="flex items-start gap-2 rounded-lg border border-border bg-bg p-3 text-sm text-primary">
                                                <input v-model="document.is_required" type="checkbox" class="mt-1 rounded border-border bg-inputBg">
                                                <span>
                                                    <span class="block font-semibold">{{ tx('club_memberships.workspace.confirmation_required', 'Bestätigung erforderlich') }}</span>
                                                    <span class="block text-xs text-secondary">{{ tx('club_memberships.workspace.confirmation_hint', 'Ohne Häkchen kann der Antrag nicht gesendet werden.') }}</span>
                                                </span>
                                            </label>
                                        </div>
                                        <div class="mt-3 flex items-center justify-between gap-3">
                                            <span class="text-xs text-secondary">{{ documentTypeLabel(document.type) }}</span>
                                            <button type="button" class="rounded-lg border border-error/40 px-3 py-1.5 text-xs font-semibold text-error hover:bg-error/10" @click="removeMembershipDocument(index)">
                                                {{ tx('club_memberships.workspace.remove', 'Entfernen') }}
                                            </button>
                                        </div>
                                    </article>
                                    -->
                                </div>
                            </div>
                            </div>
                        </form>
                    </section>

                    <section v-if="rulesWizardStep === 1" class="surface-card p-5">
                        <div class="flex items-start justify-between gap-4">
                            <div>
                                <p class="text-xs font-semibold uppercase tracking-[0.18em] text-air-blue">Schritt 2</p>
                                <h2 class="mt-1 text-lg font-semibold text-primary">{{ tx('club_memberships.workspace.application_fields', 'Antragsfelder für diesen Typ') }}</h2>
                                <p class="mt-1 text-sm text-secondary">{{ tx('club_memberships.workspace.wizard_club_standard_hint', 'Der Vereinsstandard wird nur für den ausgewählten Typ angepasst. Der Mitgliedschaftstyp selbst wird im vorherigen Schritt bearbeitet.') }}</p>
                            </div>
                            <button type="button" class="rounded-lg border border-border px-3 py-2 text-xs font-semibold text-primary hover:bg-inputBg" @click="rulesWizardStep = 0">{{ tx('club_memberships.workspace.select_type', 'Typ auswählen') }}</button>
                        </div>
                        <div v-if="membershipTypeMode === 'choose'" class="mt-4 rounded-xl border border-dashed border-border bg-bg p-4 text-sm text-secondary">
                            Bitte zuerst im Schritt „Mitgliedschaftstyp“ einen neuen Typ erstellen oder einen bestehenden Typ bearbeiten.
                        </div>
                        <div v-else class="mt-4 grid gap-3 sm:grid-cols-2">
                            <label v-for="field in applicationFieldDefinitions" :key="field.key" class="rounded-xl border border-border bg-bg p-3 text-sm text-primary">
                                <span class="font-semibold">{{ field.label }}</span>
                                <select v-model="membershipTypeForm.application_fields[field.key]" class="mt-2 w-full rounded-lg border-border bg-inputBg text-sm text-primary">
                                    <option value="required">{{ tx('club_memberships.workspace.required', 'Pflichtfeld') }}</option>
                                    <option value="optional">{{ tx('club_memberships.workspace.optional', 'Optional') }}</option>
                                    <option value="off">{{ tx('club_memberships.workspace.hidden', 'Ausgeblendet') }}</option>
                                </select>
                            </label>
                            <p v-if="!applicationFieldDefinitions.length" class="sm:col-span-2 rounded-lg border border-dashed border-border p-4 text-sm text-secondary">{{ tx('club_memberships.workspace.no_standard_fields', 'Keine Vereinsstandardfelder konfiguriert.') }}</p>
                        </div>
                        <div v-if="membershipTypeMode !== 'choose'" class="mt-4 flex justify-end">
                            <button type="button" class="rounded-lg bg-buttonPrimary px-4 py-2 text-sm font-semibold text-buttonTextPrimary" @click="storeMembershipType">{{ tx('club_memberships.workspace.save_type_and_fields', 'Typ und Antragsfelder speichern') }}</button>
                        </div>
                    </section>

                    <section v-if="rulesWizardStep === 6" class="surface-card p-5">
                        <p class="text-xs font-semibold uppercase tracking-[0.18em] text-air-blue">{{ tx('club_memberships.workspace.last_step', 'Letzter Schritt') }}</p>
                        <h2 class="mt-1 text-xl font-bold text-primary">{{ tx('club_memberships.workspace.summary', 'Zusammenfassung') }}</h2>
                        <p class="mt-1 text-sm text-secondary">{{ tx('club_memberships.workspace.review_summary', 'Bitte prüfe die wichtigsten Einstellungen. Mit „Fertig“ werden die Vereinsregeln gespeichert.') }}</p>
                        <div class="mt-5 grid gap-3 md:grid-cols-2">
                            <div class="rounded-xl border border-border bg-bg p-4"><p class="text-xs font-semibold uppercase text-secondary">{{ tx('club_memberships.workspace.membership_types_count', 'Mitgliedschaftstypen') }}</p><p class="mt-1 text-lg font-bold text-primary">{{ membershipTypes.length }}</p></div>
                            <div class="rounded-xl border border-border bg-bg p-4"><p class="text-xs font-semibold uppercase text-secondary">{{ tx('club_memberships.workspace.contribution_rules_count', 'Beitragsregeln') }}</p><p class="mt-1 text-lg font-bold text-primary">{{ contributionRules.length }}</p></div>
                            <div class="rounded-xl border border-border bg-bg p-4"><p class="text-xs font-semibold uppercase text-secondary">{{ tx('club_memberships.workspace.application_fields_count', 'Antragsfelder') }}</p><p class="mt-1 text-lg font-bold text-primary">{{ applicationFieldDefinitions.length }}</p></div>
                            <div class="rounded-xl border border-border bg-bg p-4"><p class="text-xs font-semibold uppercase text-secondary">{{ tx('club_memberships.workspace.documents_count', 'Dokumente') }}</p><p class="mt-1 text-lg font-bold text-primary">{{ membershipSettingsFor(selectedClub).membership_application_documents.length }}</p></div>
                        </div>
                    </section>

                    <section v-if="rulesWizardStep === 2" class="surface-card p-5">
                        <h2 class="text-lg font-semibold text-primary">{{ tx('auto.Historische Beitragsregeln', 'Historische Beitragsregeln') }}</h2>
                        <div class="mt-4 space-y-3">
                            <article v-for="rule in contributionRules" :key="rule.id" class="rounded-lg border border-border bg-bg p-4">
                                <div class="flex flex-col gap-2 md:flex-row md:items-start md:justify-between">
                                    <div>
                                        <p class="font-semibold text-primary">{{ rule.name }}</p>
                                        <p class="text-sm text-secondary">
                                            {{ rule.membership_type_name || tx('auto.Alle Typen', 'Alle Typen') }} · {{ formatMoney(rule.amount) }} / {{ intervalLabel(rule.billing_interval) }}
                                        </p>
	                                        <p class="mt-1 text-xs text-secondary">
                                            {{ tx('club_memberships.workspace.applies', 'Gilt') }} {{ formatDate(rule.valid_from) }} {{ tx('auto.bis', 'bis') }} {{ formatDate(rule.valid_until) }}
                                            <span v-if="rule.age_min || rule.age_max"> · {{ tx('club_memberships.workspace.age', 'Alter') }} {{ rule.age_min || 0 }}-{{ rule.age_max || tx('auto.offen', 'offen') }}</span>
	                                        </p>
	                                        <p class="mt-2 flex flex-wrap gap-2 text-xs">
	                                            <span class="rounded-full bg-muted px-2 py-1 font-semibold text-secondary">
	                                                {{ rule.factor_label || contributionRuleTypeLabel(rule.factor_key) }}
	                                            </span>
	                                            <span v-if="rule.factor_key === 'discount' && rule.factor_value" class="rounded-full bg-warning/10 px-2 py-1 font-semibold text-warning">
	                                                {{ rule.factor_operator_label || contributionDiscountOperatorLabel(rule.factor_operator) }}: {{ rule.factor_value }}
	                                            </span>
	                                        </p>
	                                        <p v-if="rule.policy_document" class="mt-2 text-xs font-semibold text-secondary">
	                                            {{ contributionPolicyText('linked') }}: {{ rule.policy_document.title }} · {{ rule.policy_document.version_label }}
	                                        </p>
	                                    </div>
                                    <div class="flex items-center gap-2">
                                        <span class="rounded-full px-2 py-1 text-xs font-semibold" :class="rule.is_active ? 'bg-air-green/15 text-air-green' : 'bg-muted text-secondary'">
                                            {{ rule.is_active ? tx('auto.aktiv', 'aktiv') : tx('club_memberships.workspace.inactive', 'inaktiv') }}
                                        </span>
                                        <button type="button" class="rounded-lg border border-border px-2.5 py-1.5 text-xs font-semibold text-primary hover:bg-inputBg" @click="editContributionRule(rule)">
                                            {{ tx('club_memberships.workspace.edit', 'Bearbeiten') }}
                                        </button>
                                    </div>
                                </div>
                            </article>
                            <p v-if="!contributionRules.length" class="rounded-lg border border-border bg-bg p-4 text-sm text-secondary">{{ tx('auto.Noch keine Beitragsregeln.', 'Noch keine Beitragsregeln.') }}</p>
                        </div>
                    </section>
                </div>

                <aside class="space-y-6">
                    <section v-if="rulesWizardStep === 0" class="surface-card p-5">
                        <div class="flex items-center justify-between gap-3">
                            <h2 class="text-lg font-semibold text-primary">{{ editingMembershipTypeId ? tx('club_memberships.workspace.edit_type', 'Mitgliedschaftstyp bearbeiten') : tx('auto.Mitgliedschaftstyp', 'Mitgliedschaftstyp') }}</h2>
                            <button v-if="editingMembershipTypeId" type="button" class="text-xs font-semibold text-secondary hover:text-primary" @click="cancelMembershipTypeEdit">
                                {{ tx('club_memberships.workspace.cancel_edit', 'Abbrechen') }}
                            </button>
                        </div>
                        <div v-if="membershipTypeMode === 'choose'" class="mt-4 grid gap-3 sm:grid-cols-2">
                            <button type="button" class="group rounded-xl border-2 border-dashed border-buttonPrimary/50 bg-buttonPrimary/5 p-4 text-left transition hover:border-buttonPrimary hover:bg-buttonPrimary/10" @click="startNewMembershipType">
                                <span class="flex h-10 w-10 items-center justify-center rounded-full bg-buttonPrimary text-xl text-buttonTextPrimary">+</span>
                                <span class="mt-3 block font-semibold text-primary">{{ tx('club_memberships.workspace.create_type_choice', 'Neuen Typ erstellen') }}</span>
                                <span class="mt-1 block text-xs leading-5 text-secondary">{{ tx('club_memberships.workspace.create_type_choice_hint', 'Starte mit einem neuen Mitgliedschaftsmodell.') }}</span>
                            </button>
                            <button type="button" class="group rounded-xl border border-emerald-400/50 bg-emerald-500/10 p-4 text-left transition hover:border-emerald-400 hover:bg-emerald-500/15" @click="applySolidarityMembershipTemplate">
                                <span class="flex h-10 w-10 items-center justify-center rounded-full bg-emerald-500 text-xl text-white">
                                    <i class="las la-hands-helping" aria-hidden="true"></i>
                                </span>
                                <span class="mt-3 block font-semibold text-primary">{{ tx('club_memberships.workspace.solidarity_type_name', 'Solidarische Mitgliedschaft') }}</span>
                                <span class="mt-1 block text-xs leading-5 text-secondary">{{ tx('club_memberships.workspace.solidarity_type_hint', 'Vorlage für ermäßigte oder beitragsfreie Mitgliedschaften aus sozialen Gründen.') }}</span>
                            </button>
                            <div class="rounded-xl border border-border bg-bg p-4">
                                <p class="font-semibold text-primary">{{ tx('club_memberships.workspace.edit_type_choice', 'Bestehenden Typ bearbeiten') }}</p>
                                <p class="mt-1 text-xs leading-5 text-secondary">{{ tx('club_memberships.workspace.edit_type_choice_hint', 'Wähle unten einen Typ aus, um ihn zu ändern.') }}</p>
                                <div class="mt-3 flex flex-wrap gap-2">
                                    <button v-for="type in membershipTypes" :key="type.id" type="button" class="rounded-lg border border-border bg-card px-3 py-2 text-xs font-semibold text-primary hover:border-buttonPrimary" @click="editMembershipType(type)">
                                        {{ type.name }}
                                    </button>
                                    <span v-if="!membershipTypes.length" class="text-xs text-secondary">{{ tx('club_memberships.workspace.no_types_yet', 'Noch keine Typen vorhanden.') }}</span>
                                </div>
                            </div>
                            <div class="sm:col-span-2 rounded-xl border border-dashed border-border bg-bg p-3">
                                <p class="text-xs font-semibold uppercase tracking-wide text-secondary">{{ tx('club_memberships.workspace.examples_title', 'Beispiele zur Orientierung') }}</p>
                                <p class="mt-1 text-xs text-secondary">{{ tx('club_memberships.workspace.examples_body', 'Diese Beispiele werden nicht als echte Vereinseinstellungen gespeichert.') }}</p>
                                <div class="mt-2 flex flex-wrap gap-2">
                                    <span v-for="example in ['Jugendmitglied', 'Aktives Mitglied', 'Probetraining', 'Fördermitglied']" :key="example" class="rounded-full bg-card px-3 py-1.5 text-xs font-semibold text-primary">{{ example }}</span>
                                </div>
                            </div>
                        </div>
                        <form v-else class="mt-4 grid gap-3" @submit.prevent="storeMembershipType">
                            <input v-model="membershipTypeForm.name" class="rounded-lg border-border bg-inputBg text-sm text-primary" :placeholder="tx('auto.z. B. Jugendmitglied', 'z. B. Jugendmitglied')" required>
                            <p v-if="membershipTypeForm.errors.name" class="text-xs font-semibold text-error">{{ membershipTypeForm.errors.name }}</p>
                            <input v-model="membershipTypeForm.slug" class="rounded-lg border-border bg-inputBg text-sm text-primary" :placeholder="tx('auto.slug optional', 'slug optional')">
                            <textarea v-model="membershipTypeForm.description" rows="3" class="rounded-lg border-border bg-inputBg text-sm text-primary" :placeholder="tx('Beschreibung', 'Beschreibung')"></textarea>
                            <label class="flex items-center gap-2 text-sm text-primary"><input v-model="membershipTypeForm.is_public" type="checkbox" class="rounded border-border bg-inputBg"> {{ tx('auto.Öffentlich sichtbar', 'Öffentlich sichtbar') }}</label>
                            <button class="rounded-lg bg-buttonPrimary px-4 py-2 text-sm font-semibold text-buttonTextPrimary">{{ editingMembershipTypeId ? tx('club_memberships.workspace.update_type', 'Typ aktualisieren') : tx('auto.Typ speichern', 'Typ speichern') }}</button>
                        </form>
                        <div class="mt-4 flex flex-wrap gap-2">
                            <div v-for="type in membershipTypes" :key="type.id" class="flex items-center gap-2 rounded-lg border border-border bg-bg px-3 py-2">
                                <span class="text-xs font-semibold text-secondary">{{ type.name }}</span>
                                <button type="button" class="text-xs font-semibold text-air-blue hover:underline" @click="editMembershipType(type)">
                                    {{ tx('club_memberships.workspace.edit', 'Bearbeiten') }}
                                </button>
                            </div>
                        </div>
                    </section>

                    <section v-if="rulesWizardStep === 2" class="surface-card p-5">
                        <div class="flex items-center justify-between gap-3">
                            <h2 class="text-lg font-semibold text-primary">{{ editingContributionRuleId ? tx('club_memberships.workspace.edit_rule', 'Beitragsregel bearbeiten') : tx('auto.Neue Beitragsregel', 'Neue Beitragsregel') }}</h2>
                            <button v-if="editingContributionRuleId" type="button" class="text-xs font-semibold text-secondary hover:text-primary" @click="cancelContributionRuleEdit">
                                {{ tx('club_memberships.workspace.cancel_edit', 'Abbrechen') }}
                            </button>
                        </div>
                        <form class="mt-4 grid gap-3" @submit.prevent="storeContributionRule">
                            <select v-model="contributionRuleForm.club_membership_type_id" class="rounded-lg border-border bg-inputBg text-sm text-primary" :aria-label="tx('club_memberships.workspace.membership_type', 'Mitgliedschaftstyp')">
                                <option value="">{{ tx('auto.Alle Typen', 'Alle Typen') }}</option>
                                <option v-for="type in membershipTypes" :key="type.id" :value="type.id">{{ type.name }}</option>
	                            </select>
                            <label class="grid gap-1 text-xs font-semibold text-secondary">
                                {{ contributionPolicyText('model') }}
                                <select v-model="contributionRuleForm.club_policy_document_id" class="rounded-lg border-border bg-inputBg text-sm text-primary" :aria-label="contributionPolicyText('model')">
                                    <option value="">{{ contributionPolicyText('noLink') }}</option>
                                    <option v-for="document in contributionPolicyDocuments" :key="document.id" :value="document.id">
                                        {{ document.title }} · {{ document.version_label }} ({{ formatDate(document.valid_from) }}–{{ formatDate(document.valid_until) }})
                                    </option>
                                </select>
                                <span v-if="contributionRuleForm.errors.club_policy_document_id" class="text-xs font-semibold text-error">{{ contributionRuleForm.errors.club_policy_document_id }}</span>
                            </label>
                            <input v-model="contributionRuleForm.name" class="rounded-lg border-border bg-inputBg text-sm text-primary" :placeholder="tx('club_memberships.workspace.rule_name', 'Regelname')" :aria-label="tx('club_memberships.workspace.rule_name', 'Regelname')" required>
                            <input v-model="contributionRuleForm.amount" type="number" min="0" step="0.01" class="rounded-lg border-border bg-inputBg text-sm text-primary" :placeholder="tx('club_memberships.workspace.amount_eur', 'Beitrag EUR')" :aria-label="tx('club_memberships.workspace.amount_eur', 'Beitrag EUR')" required>
	                            <select v-model="contributionRuleForm.factor_key" class="rounded-lg border-border bg-inputBg text-sm text-primary" :aria-label="tx('club_memberships.workspace.rule_type', 'Regelart')">
	                                <option v-for="type in contributionRuleTypes" :key="type.value" :value="type.value">{{ type.label }}</option>
	                            </select>
	                            <select v-model="contributionRuleForm.billing_interval" class="rounded-lg border-border bg-inputBg text-sm text-primary" :aria-label="tx('club_memberships.workspace.interval', 'Intervall')">
	                                <option v-for="interval in contributionIntervals" :key="interval" :value="interval">{{ intervalLabel(interval) }}</option>
	                            </select>
	                            <select v-model="contributionRuleForm.proration_policy" class="rounded-lg border-border bg-inputBg text-sm text-primary" :aria-label="tx('club_memberships.workspace.entry_billing', 'Eintrittsabrechnung')">
	                                <option v-for="policy in contributionProrationPolicies" :key="policy.value" :value="policy.value">{{ policy.label }}</option>
	                            </select>
	                            <div v-if="contributionRuleForm.factor_key === 'discount'" class="grid grid-cols-2 gap-2">
	                                <select v-model="contributionRuleForm.factor_operator" class="rounded-lg border-border bg-inputBg text-sm text-primary" :aria-label="tx('club_memberships.workspace.discount_type', 'Rabatt-Typ')">
                                    <option value="">{{ tx('club_memberships.workspace.discount_type', 'Rabatt-Typ') }}</option>
	                                    <option v-for="operator in contributionDiscountOperators" :key="operator.value" :value="operator.value">{{ operator.label }}</option>
	                                </select>
                                <input v-model="contributionRuleForm.factor_value" type="number" min="0" step="0.01" class="rounded-lg border-border bg-inputBg text-sm text-primary" :placeholder="tx('club_memberships.workspace.discount_value', 'Rabattwert')" :aria-label="tx('club_memberships.workspace.discount_value', 'Rabattwert')">
	                            </div>
                            <div class="grid grid-cols-2 gap-2">
                                <input v-model="contributionRuleForm.tax_account" class="rounded-lg border-border bg-inputBg text-sm text-primary" :placeholder="tx('club_memberships.workspace.tax_account', 'Steuerkonto')" :aria-label="tx('club_memberships.workspace.tax_account', 'Steuerkonto')">
                                <input v-model="contributionRuleForm.accounting_account" class="rounded-lg border-border bg-inputBg text-sm text-primary" :placeholder="tx('club_memberships.workspace.accounting_account', 'Buchungskonto')" :aria-label="tx('club_memberships.workspace.accounting_account', 'Buchungskonto')">
                            </div>
	                            <div class="grid grid-cols-2 gap-2">
	                                <input v-model="contributionRuleForm.valid_from" type="date" class="rounded-lg border-border bg-inputBg text-sm text-primary" :aria-label="tx('club_memberships.workspace.valid_from', 'Gültig ab')" required>
	                                <input v-model="contributionRuleForm.valid_until" type="date" class="rounded-lg border-border bg-inputBg text-sm text-primary" :aria-label="tx('club_memberships.workspace.valid_until', 'Gültig bis')">
                            </div>
                            <div class="grid grid-cols-2 gap-2">
                                <input v-model="contributionRuleForm.age_min" type="number" min="0" class="rounded-lg border-border bg-inputBg text-sm text-primary" :placeholder="tx('club_memberships.workspace.age_min', 'Alter von')" :aria-label="tx('club_memberships.workspace.age_min', 'Alter von')">
                                <input v-model="contributionRuleForm.age_max" type="number" min="0" class="rounded-lg border-border bg-inputBg text-sm text-primary" :placeholder="tx('club_memberships.workspace.age_max', 'Alter bis')" :aria-label="tx('club_memberships.workspace.age_max', 'Alter bis')">
                            </div>
                            <textarea v-model="contributionRuleForm.notes" rows="2" class="rounded-lg border-border bg-inputBg text-sm text-primary" :placeholder="tx('club_memberships.workspace.notes', 'Notiz')" :aria-label="tx('club_memberships.workspace.notes', 'Notiz')"></textarea>
                            <button class="rounded-lg bg-buttonPrimary px-4 py-2 text-sm font-semibold text-buttonTextPrimary">{{ editingContributionRuleId ? tx('club_memberships.workspace.update_rule', 'Regel aktualisieren') : tx('auto.Regel speichern', 'Regel speichern') }}</button>
                        </form>
                    </section>
                </aside>
                </div>

                <div class="surface-card flex flex-col gap-3 p-4 sm:flex-row sm:items-center sm:justify-between">
                    <button
                        v-if="rulesWizardStep > 0"
                        type="button"
                        class="rounded-lg border border-border px-4 py-2.5 text-sm font-semibold text-primary hover:bg-inputBg"
                        @click="rulesWizardBack"
                    >
                        {{ tx('club_memberships.workspace.wizard_back', 'Zurück') }}
                    </button>
                    <span v-else></span>
                    <p class="text-xs font-semibold text-secondary">{{ rulesWizardSteps[rulesWizardStep].label }}</p>
                    <button
                        v-if="rulesWizardStep < rulesWizardSteps.length - 1"
                        type="button"
                        class="rounded-lg bg-buttonPrimary px-5 py-2.5 text-sm font-semibold text-buttonTextPrimary"
                        @click="rulesWizardNext"
                    >
                        {{ tx('club_memberships.workspace.wizard_next', 'Weiter') }}
                    </button>
                    <button
                        v-else
                        type="button"
                        class="rounded-lg bg-buttonPrimary px-5 py-2.5 text-sm font-semibold text-buttonTextPrimary"
                        @click="saveMembershipSettings"
                    >
                        {{ tx('club_memberships.workspace.wizard_finish', 'Regeln speichern') }}
                    </button>
                </div>
            </section>

            <section v-if="activeTab === 'members'" class="surface-card overflow-hidden">
                <div class="border-b border-border p-5">
                    <div class="flex flex-col gap-4 xl:flex-row xl:items-center xl:justify-between">
                        <div>
                            <h2 class="text-lg font-semibold text-primary">{{ tx('auto.Mitglieder & Beitragsdaten', 'Mitglieder & Beitragsdaten') }}</h2>
                            <p class="mt-1 text-sm text-secondary">
                                {{ tx('auto.Teammitglieder können als echte Vereinsmitglieder oder als reine Teamteilnehmer markiert werden.', 'Teammitglieder können als echte Vereinsmitglieder oder als reine Teamteilnehmer markiert werden.') }}
                            </p>
                        </div>

                        <div class="grid gap-2 sm:grid-cols-[minmax(13rem,1fr)_12rem_14rem]">
                            <label class="flex items-center gap-2 rounded-lg border border-border bg-inputBg px-3 py-2">
                                <i class="las la-search text-lg text-secondary"></i>
                                <input
                                    v-model="memberSearch"
                                    type="search"
                                    class="min-w-0 flex-1 border-0 bg-transparent p-0 text-sm text-primary placeholder-secondary focus:ring-0"
                                    :placeholder="tx('auto.Mitglied suchen', 'Mitglied suchen')"
                                >
                            </label>
                            <select v-model="memberStatusFilter" :aria-label="tx('club_memberships.workspace.member_status_filter', 'Mitgliedschaft filtern')" class="rounded-lg border border-border bg-inputBg px-3 py-2 text-sm text-primary">
                                <option value="all">{{ tx('auto.Alle Status', 'Alle Status') }}</option>
                                <option v-for="status in membershipStatuses" :key="status" :value="status">{{ statusLabel(status) }}</option>
                            </select>
                            <select v-model="memberEndFilter" :aria-label="tx('club_memberships.workspace.membership_end_filter', 'Laufzeit filtern')" class="rounded-lg border border-border bg-inputBg px-3 py-2 text-sm text-primary">
                                <option value="all">{{ tx('auto.Alle Laufzeiten', 'Alle Laufzeiten') }}</option>
                                <option value="ending_30">{{ tx('auto.Endet in 30 Tagen', 'Endet in 30 Tagen') }}</option>
                                <option value="ending_60">{{ tx('auto.Endet in 60 Tagen', 'Endet in 60 Tagen') }}</option>
                                <option value="expired">{{ tx('auto.Bereits abgelaufen', 'Bereits abgelaufen') }}</option>
                                <option value="no_end">{{ tx('auto.Ohne Enddatum', 'Ohne Enddatum') }}</option>
                            </select>
                        </div>
                    </div>
                    <SavedViewBar
                        :views="memberSavedViews.views.value"
                        :loading="memberSavedViews.loading.value"
                        :error="memberSavedViews.error.value"
                        @apply="applyMemberSavedView"
                        @save="saveMemberView"
                        @remove="memberSavedViews.remove"
                    />
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
                                <p v-if="member.phone || member.city" class="mt-1 text-xs text-secondary">{{ [member.phone, member.postal_code, member.city].filter(Boolean).join(' · ') }}</p>
                                <p class="mt-2 text-xs text-secondary">
                                    Nr. {{ formFor(member).member_number || '-' }} · Beitrag {{ formatMoney(formFor(member).contribution_amount) }} · {{ intervalLabel(formFor(member).contribution_interval) }} · {{ paymentMethodLabel(formFor(member).payment_method) || 'Zahlmethode offen' }}
                                </p>
                                <p class="mt-1 text-xs text-secondary">
                                    Lizenznummer: {{ formFor(member).athlete_license_number || '-' }} - Ende {{ formatDate(formFor(member).membership_ends_on) }}
                                </p>
                            </div>

                            <div class="flex flex-wrap gap-2">
                                <ClubMetadataSubjectEditor :club-id="selectedClub.id" subject-type="member" :subject-id="member.id" :subject-label="member.name" />
                                <button
                                    v-if="selectedClub.can_manage_access"
                                    type="button"
                                    class="rounded-lg border border-border px-3 py-2 text-sm font-semibold text-primary hover:border-buttonPrimary hover:text-buttonPrimary"
                                    @click="accessMember = member"
                                >
                                    <i class="las la-user-shield me-1" aria-hidden="true"></i>{{ tx('club_memberships.workspace.access', 'Zugriff') }}
                                </button>
                                <button type="button" class="rounded-lg border border-border px-3 py-2 text-sm text-primary" @click="editingMemberId = editingMemberId === member.id ? null : member.id">
                                    Bearbeiten
                                </button>
                                <button type="button" class="rounded-lg border border-border px-3 py-2 text-sm text-primary" @click="openTimeline(member)">
                                    {{ tx('club_memberships.workspace.timeline', 'Verlauf') }}
                                </button>
                                <button
                                    type="button"
                                    class="rounded-lg bg-buttonPrimary px-3 py-2 text-sm font-semibold text-buttonTextPrimary disabled:cursor-not-allowed disabled:opacity-50"
                                    :disabled="capabilities.invoices === false"
                                    :title="capabilities.invoices === false ? tx('club_memberships.workspace.invoices_unavailable', 'Rechnungen sind ab Starter verfügbar') : ''"
                                    @click="openInvoice(member)"
                                >
                                    {{ tx('auto.Rechnung', 'Rechnung') }}
                                </button>
                                <button
                                    type="button"
                                    class="rounded-lg border border-error/40 px-3 py-2 text-sm font-semibold text-error hover:bg-error/10"
                                    @click="removeMember(member)"
                                >
                                    {{ tx('auto.Aus Verein entfernen', 'Aus Verein entfernen') }}
                                </button>
                            </div>
                        </div>

                        <form v-if="editingMemberId === member.id" class="mt-4 grid gap-3 rounded-lg border border-border bg-bg p-4 md:grid-cols-3" @submit.prevent="saveMember(member)">
                            <fieldset>
                                <legend class="text-xs font-semibold uppercase text-secondary">{{ tx('club_memberships.workspace.roles', 'Rollen') }}</legend>
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
                                <p class="mt-1 text-xs text-secondary">{{ tx('auto.Mehrere Rollen sind möglich, z. B. Trainer und Kassierer.', 'Mehrere Rollen sind möglich, z. B. Trainer und Kassierer.') }}</p>
                            </fieldset>

                            <div>
                                <label :for="`club-member-${member.id}-membership-status`" class="text-xs font-semibold uppercase text-secondary">{{ tx('auto.Mitgliedschaft', 'Mitgliedschaft') }}</label>
                                <select :id="`club-member-${member.id}-membership-status`" v-model="formFor(member).membership_status" class="mt-1 w-full rounded-lg border border-border bg-inputBg px-3 py-2 text-sm text-primary">
                                    <option v-for="status in membershipStatuses" :key="status" :value="status">{{ statusLabel(status) }}</option>
                                </select>
                            </div>

                            <div>
                                <label :for="`club-member-${member.id}-membership-type`" class="text-xs font-semibold uppercase text-secondary">{{ tx('auto.Mitgliedschaftstyp', 'Mitgliedschaftstyp') }}</label>
                                <select :id="`club-member-${member.id}-membership-type`" v-model="formFor(member).club_membership_type_id" class="mt-1 w-full rounded-lg border border-border bg-inputBg px-3 py-2 text-sm text-primary" @change="applyContributionRuleToMember(member)">
                                    <option value="">{{ tx('auto.Kein Typ', 'Kein Typ') }}</option>
                                    <option v-for="type in membershipTypes" :key="type.id" :value="type.id">{{ type.name }}</option>
                                </select>
                                <p v-if="contributionRulePreviewForMember(member)" class="mt-1 text-xs text-secondary">{{ contributionRulePreviewForMember(member) }}</p>
                            </div>

                            <div>
                                <label :for="`club-member-${member.id}-family-group`" class="text-xs font-semibold uppercase text-secondary">{{ tx('club_memberships.workspace.household', 'Familie / Haushalt') }}</label>
                                <input
                                    :id="`club-member-${member.id}-family-group`"
                                    v-model.trim="formFor(member).family_group_key"
                                    maxlength="80"
                                    class="mt-1 w-full rounded-lg border border-border bg-inputBg px-3 py-2 text-sm text-primary"
                                    :placeholder="tx('club_memberships.workspace.household_placeholder', 'z. B. Familie-Mustermann')"
                                >
                            </div>

                            <div>
                                <label :for="`club-member-${member.id}-contribution-payer`" class="text-xs font-semibold uppercase text-secondary">{{ tx('club_memberships.workspace.contribution_payer', 'Beitragszahler') }}</label>
                                <select :id="`club-member-${member.id}-contribution-payer`" v-model="formFor(member).contribution_payer_user_id" class="mt-1 w-full rounded-lg border border-border bg-inputBg px-3 py-2 text-sm text-primary">
                                    <option value="">{{ tx('club_memberships.workspace.pays_self', 'Mitglied zahlt selbst') }}</option>
                                    <option v-for="payer in selectedClub.members" :key="payer.id" :value="payer.id">
                                        {{ payer.name }} · {{ payer.email }}
                                    </option>
                                </select>
                                <p class="mt-1 text-xs text-secondary">{{ tx('club_memberships.workspace.contribution_payer_hint', 'Beitragsrechnungen werden an diese Person adressiert.') }}</p>
                            </div>

                            <div>
                                <label :for="`club-member-${member.id}-member-number`" class="text-xs font-semibold uppercase text-secondary">{{ tx('auto.Mitgliedsnummer', 'Mitgliedsnummer') }}</label>
                                <div class="mt-1 flex gap-2">
                                    <input :id="`club-member-${member.id}-member-number`" v-model="formFor(member).member_number" class="min-w-0 flex-1 rounded-lg border border-border bg-inputBg px-3 py-2 text-sm text-primary">
                                    <button type="button" class="rounded-lg border border-border px-3 py-2 text-xs font-semibold text-primary hover:bg-inputBg" @click="generateMemberNumber(member)">
                                        Generieren
                                    </button>
                                </div>
                            </div>

                            <div>
                                <label :for="`club-member-${member.id}-license-number`" class="text-xs font-semibold uppercase text-secondary">{{ tx('club_memberships.workspace.license', 'Lizenznummer') }}</label>
                                <input
                                    :id="`club-member-${member.id}-license-number`"
                                    v-model="formFor(member).athlete_license_number"
                                    class="mt-1 w-full rounded-lg border border-border bg-inputBg px-3 py-2 text-sm text-primary"
                                    :placeholder="tx('auto.z. B. Spielerpass- oder Verbandsnummer', 'z. B. Spielerpass- oder Verbandsnummer')"
                                >
                            </div>

                            <div>
                                <label :for="`club-member-${member.id}-license-valid-until`" class="text-xs font-semibold uppercase text-secondary">{{ tx('auto.Lizenz gültig bis', 'Lizenz gültig bis') }}</label>
                                <input
                                    :id="`club-member-${member.id}-license-valid-until`"
                                    v-model="formFor(member).athlete_license_valid_until"
                                    type="date"
                                    class="mt-1 w-full rounded-lg border border-border bg-inputBg px-3 py-2 text-sm text-primary"
                                >
                            </div>

                            <div>
                                <label :for="`club-member-${member.id}-contribution`" class="text-xs font-semibold uppercase text-secondary">{{ tx('club_memberships.workspace.contribution', 'Beitrag') }}</label>
                                <input :id="`club-member-${member.id}-contribution`" v-model="formFor(member).contribution_amount" type="number" min="0" step="0.01" class="mt-1 w-full rounded-lg border border-border bg-inputBg px-3 py-2 text-sm text-primary">
                            </div>

                            <div>
                                <label :for="`club-member-${member.id}-interval`" class="text-xs font-semibold uppercase text-secondary">{{ tx('club_memberships.workspace.interval_label', 'Intervall') }}</label>
                                <select :id="`club-member-${member.id}-interval`" v-model="formFor(member).contribution_interval" class="mt-1 w-full rounded-lg border border-border bg-inputBg px-3 py-2 text-sm text-primary">
                                    <option v-for="interval in contributionIntervals" :key="interval" :value="interval">{{ intervalLabel(interval) }}</option>
                                </select>
                            </div>

                            <div>
                                <label :for="`club-member-${member.id}-payment-method`" class="text-xs font-semibold uppercase text-secondary">{{ tx('club_memberships.workspace.payment_method_label', 'Zahlmethode') }}</label>
                                <select :id="`club-member-${member.id}-payment-method`" v-model="formFor(member).payment_method" class="mt-1 w-full rounded-lg border border-border bg-inputBg px-3 py-2 text-sm text-primary">
                                    <option value="">{{ tx('auto.Offen', 'Offen') }}</option>
                                    <option v-for="method in selectedClub.membership_payment_method_options" :key="method.value" :value="method.value">{{ method.label }}</option>
                                </select>
                            </div>

                            <div>
                                <label :for="`club-member-${member.id}-next-invoice`" class="text-xs font-semibold uppercase text-secondary">{{ tx('club_memberships.workspace.next_invoice', 'Nächste automatische Rechnung') }}</label>
                                <input :id="`club-member-${member.id}-next-invoice`" v-model="formFor(member).contribution_next_invoice_on" type="date" class="mt-1 w-full rounded-lg border border-border bg-inputBg px-3 py-2 text-sm text-primary">
                                <p class="mt-1 text-xs text-secondary">{{ tx('club_memberships.workspace.automation_hint', 'Automatik wird ab Pro/Elite ausgeführt.') }}</p>
                            </div>

                            <div>
                                <label :for="`club-member-${member.id}-sepa-iban`" class="text-xs font-semibold uppercase text-secondary">{{ tx('club_memberships.workspace.sepa_iban', 'SEPA IBAN') }}</label>
                                <input :id="`club-member-${member.id}-sepa-iban`" v-model="formFor(member).sepa_iban" class="mt-1 w-full rounded-lg border border-border bg-inputBg px-3 py-2 text-sm text-primary" placeholder="DE...">
                            </div>

                            <div>
                                <label :for="`club-member-${member.id}-sepa-bic`" class="text-xs font-semibold uppercase text-secondary">{{ tx('club_memberships.workspace.sepa_bic', 'SEPA BIC optional') }}</label>
                                <input :id="`club-member-${member.id}-sepa-bic`" v-model="formFor(member).sepa_bic" class="mt-1 w-full rounded-lg border border-border bg-inputBg px-3 py-2 text-sm text-primary" placeholder="GENODE...">
                            </div>

                            <div>
                                <label :for="`club-member-${member.id}-mandate-reference`" class="text-xs font-semibold uppercase text-secondary">{{ tx('club_memberships.workspace.mandate_reference', 'Mandatsreferenz') }}</label>
                                <input :id="`club-member-${member.id}-mandate-reference`" v-model="formFor(member).sepa_mandate_reference" class="mt-1 w-full rounded-lg border border-border bg-inputBg px-3 py-2 text-sm text-primary" placeholder="MANDAT-1001">
                            </div>

                            <div>
                                <label :for="`club-member-${member.id}-mandate-date`" class="text-xs font-semibold uppercase text-secondary">{{ tx('club_memberships.workspace.mandate_date', 'Mandatsdatum') }}</label>
                                <input :id="`club-member-${member.id}-mandate-date`" v-model="formFor(member).sepa_mandate_signed_on" type="date" class="mt-1 w-full rounded-lg border border-border bg-inputBg px-3 py-2 text-sm text-primary">
                            </div>

                            <label class="flex items-center gap-2 self-end rounded-lg border border-border bg-inputBg px-3 py-2 text-sm text-primary">
                                <input v-model="formFor(member).sepa_mandate_active" type="checkbox" class="rounded border-border bg-bg">
                                {{ tx('auto.SEPA-Mandat aktiv', 'SEPA-Mandat aktiv') }}
                            </label>

                            <div>
                                <label :for="`club-member-${member.id}-joined`" class="text-xs font-semibold uppercase text-secondary">{{ tx('club_memberships.workspace.joined', 'Eintritt') }}</label>
                                <input :id="`club-member-${member.id}-joined`" v-model="formFor(member).joined_on" type="date" class="mt-1 w-full rounded-lg border border-border bg-inputBg px-3 py-2 text-sm text-primary">
                            </div>

                            <div>
                                <label :for="`club-member-${member.id}-membership-end`" class="text-xs font-semibold uppercase text-secondary">{{ tx('auto.Ende der Mitgliedschaft', 'Ende der Mitgliedschaft') }}</label>
                                <input :id="`club-member-${member.id}-membership-end`" v-model="formFor(member).membership_ends_on" type="date" class="mt-1 w-full rounded-lg border border-border bg-inputBg px-3 py-2 text-sm text-primary">
                            </div>

                            <div class="md:col-span-2">
                                <label :for="`club-member-${member.id}-notes`" class="text-xs font-semibold uppercase text-secondary">{{ tx('auto.Notiz', 'Notiz') }}</label>
                                <textarea :id="`club-member-${member.id}-notes`" v-model="formFor(member).membership_notes" rows="3" class="mt-1 w-full rounded-lg border border-border bg-inputBg px-3 py-2 text-sm text-primary"></textarea>
                            </div>

                            <div class="md:col-span-3">
                                <button class="rounded-lg bg-buttonPrimary px-4 py-2 text-sm font-semibold text-buttonTextPrimary disabled:cursor-wait disabled:opacity-60" :disabled="savingMemberIds.has(member.id)">
                                    {{ savingMemberIds.has(member.id) ? tx('auto.Wird gespeichert …', 'Wird gespeichert …') : tx('auto.Speichern', 'Speichern') }}
                                </button>
                            </div>
                        </form>

                        <form v-if="invoiceMemberId === member.id" class="mt-4 grid gap-3 rounded-lg border border-border bg-bg p-4 sm:grid-cols-2 lg:grid-cols-4" @submit.prevent="createInvoice(member)">
                            <input v-model="invoiceForm.title" :aria-label="tx('auto.Titel', 'Titel')" class="rounded-lg border border-border bg-inputBg px-3 py-2 text-sm text-primary" :placeholder="tx('auto.Titel', 'Titel')">
                            <input v-model="invoiceForm.amount" type="number" :min="invoiceForm.waived ? '0' : '0.01'" step="0.01" :aria-label="tx('auto.Betrag', 'Betrag')" class="rounded-lg border border-border bg-inputBg px-3 py-2 text-sm text-primary" :placeholder="tx('auto.Betrag', 'Betrag')">
                            <input v-model="invoiceForm.billing_period_start" type="date" :aria-label="tx('club_memberships.workspace.billing_period_start', 'Zeitraum von')" class="rounded-lg border border-border bg-inputBg px-3 py-2 text-sm text-primary">
                            <input v-model="invoiceForm.billing_period_end" type="date" :aria-label="tx('club_memberships.workspace.billing_period_end', 'Zeitraum bis')" class="rounded-lg border border-border bg-inputBg px-3 py-2 text-sm text-primary">
                            <input v-model="invoiceForm.due_date" type="date" :aria-label="tx('club_memberships.workspace.due_date', 'Fällig am')" class="rounded-lg border border-border bg-inputBg px-3 py-2 text-sm text-primary">
                            <button class="rounded-lg bg-buttonPrimary px-4 py-2 text-sm font-semibold text-buttonTextPrimary disabled:cursor-wait disabled:opacity-60" :disabled="invoiceForm.processing">
                                {{ invoiceForm.processing ? tx('auto.Wird gespeichert …', 'Wird gespeichert …') : tx('auto.Erstellen', 'Erstellen') }}
                            </button>
                            <label class="flex items-start gap-3 rounded-lg border border-air-green/30 bg-air-green/10 p-3 text-sm text-primary sm:col-span-2 lg:col-span-4">
                                <input v-model="invoiceForm.waived" type="checkbox" class="mt-1 rounded border-border text-air-green">
                                <span>
                                    <span class="block font-semibold">{{ tx('club_memberships.workspace.waive_invoice', 'Kulanz-Erlass buchen') }}</span>
                                    <span class="block text-xs text-secondary">{{ tx('club_memberships.workspace.waive_invoice_hint', 'Für diesen Zeitraum wird 0,00 € verbucht und keine Zahlung als bezahlt erfasst.') }}</span>
                                </span>
                            </label>
                            <input v-if="invoiceForm.waived" v-model="invoiceForm.waiver_reason" :aria-label="tx('club_memberships.workspace.waiver_reason', 'Grund für den Erlass')" class="rounded-lg border border-border bg-inputBg px-3 py-2 text-sm text-primary sm:col-span-2 lg:col-span-4" :placeholder="tx('club_memberships.workspace.waiver_reason_placeholder', 'Grund, z. B. Kulanz für Oktober')">
                            <textarea v-model="invoiceForm.description" rows="2" :aria-label="tx('auto.Beschreibung optional', 'Beschreibung optional')" class="rounded-lg border border-border bg-inputBg px-3 py-2 text-sm text-primary sm:col-span-2 lg:col-span-4" :placeholder="tx('auto.Beschreibung optional', 'Beschreibung optional')"></textarea>
                            <p v-if="Object.keys(invoiceForm.errors).length" class="text-sm font-semibold text-error sm:col-span-2 lg:col-span-4" role="alert">{{ Object.values(invoiceForm.errors)[0] }}</p>
                        </form>
                    </article>
                    <p v-if="!filteredMembers.length" class="p-6 text-sm text-secondary">
                        {{ tx('club_memberships.workspace.search_empty', 'Keine Mitglieder passen zu deiner Suche.') }}
                    </p>
                </div>
            </section>

            <section v-if="activeTab === 'schedule'" class="surface-card p-5">
                <div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
                    <div>
                        <h2 class="text-lg font-semibold text-primary">{{ tx('club_memberships.workspace.payment_schedule', 'Fälligkeiten') }}</h2>
                        <p class="mt-1 text-sm text-secondary">{{ tx('club_memberships.workspace.payment_schedule_hint', 'Nächste Zahlungen nach Datum sortiert, damit Erinnerungen schneller vorbereitet sind.') }}</p>
                    </div>
                    <span class="rounded-full border border-border bg-bg px-3 py-1 text-xs font-semibold text-secondary">
                        {{ paymentSchedule.length }} {{ tx('auto.Mitglieder', 'Mitglieder') }}
                    </span>
                </div>
                <div class="mt-4 overflow-x-auto">
                    <table class="min-w-full text-left text-sm">
                        <thead class="text-xs uppercase text-secondary">
                            <tr>
                                <th class="py-2 pr-4">{{ tx('auto.Mitglied', 'Mitglied') }}</th>
                                <th class="py-2 pr-4">{{ tx('club_memberships.workspace.next_payment_date', 'Nächste Zahlung') }}</th>
                                <th class="py-2 pr-4">{{ tx('auto.Status', 'Status') }}</th>
                                <th class="py-2 pr-4">{{ tx('club_memberships.workspace.contribution', 'Beitrag') }}</th>
                                <th class="py-2 pr-4">{{ tx('club_memberships.workspace.interval_label', 'Intervall') }}</th>
                                <th class="py-2 pr-4">{{ tx('club_memberships.workspace.payment_method_label', 'Zahlmethode') }}</th>
                                <th class="py-2 pr-4">{{ tx('auto.Aktion', 'Aktion') }}</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-border">
                            <tr v-for="item in paymentSchedule" :key="item.member.id">
                                <td class="py-3 pr-4">
                                    <span class="block font-semibold text-primary">{{ item.member.name }}</span>
                                    <span class="block text-xs text-secondary">{{ item.member.email }}</span>
                                </td>
                                <td class="py-3 pr-4 font-semibold text-primary">{{ formatDate(item.form.contribution_next_invoice_on) }}</td>
                                <td class="py-3 pr-4">
                                    <span class="inline-flex rounded-full px-2 py-1 text-xs font-semibold" :class="paymentDueClass(item.days)">
                                        {{ paymentDueLabel(item.days) }}
                                    </span>
                                </td>
                                <td class="py-3 pr-4 text-primary">{{ formatMoney(item.form.contribution_amount) }}</td>
                                <td class="py-3 pr-4 text-secondary">{{ intervalLabel(item.form.contribution_interval) }}</td>
                                <td class="py-3 pr-4 text-secondary">{{ paymentMethodLabel(item.form.payment_method) || tx('auto.Offen', 'Offen') }}</td>
                                <td class="py-3 pr-4">
                                    <div class="flex flex-wrap gap-2">
                                        <button type="button" class="rounded bg-buttonPrimary px-2 py-1 text-xs font-semibold text-buttonTextPrimary" @click="openInvoice(item.member)">
                                            {{ tx('club_memberships.workspace.create_invoice', 'Rechnung erstellen') }}
                                        </button>
                                        <button type="button" class="rounded border border-border px-2 py-1 text-xs font-semibold text-primary" @click="editingMemberId = item.member.id; activeTab = 'members'">
                                            {{ tx('club_memberships.workspace.edit', 'Bearbeiten') }}
                                        </button>
                                    </div>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                    <p v-if="!paymentSchedule.length" class="py-6 text-sm text-secondary">{{ tx('club_memberships.workspace.no_payment_schedule', 'Noch keine nächsten Zahlungen geplant.') }}</p>
                </div>
            </section>

            <section v-if="activeTab === 'invoices'" class="surface-card p-5">
                <h2 class="text-lg font-semibold text-primary">{{ tx('auto.Rechnungen', 'Rechnungen') }}</h2>
                <p v-if="invoiceActionFeedback" class="mt-3 rounded-lg border border-success/30 bg-success/10 px-3 py-2 text-sm font-semibold text-success" aria-live="polite">{{ invoiceActionFeedback }}</p>
                <p v-if="invoiceActionError" class="mt-3 rounded-lg border border-error/30 bg-error/10 px-3 py-2 text-sm font-semibold text-error" role="alert">{{ invoiceActionError }}</p>
                <div class="mt-4 rounded-xl border border-border bg-bg p-4">
                    <div class="flex flex-col gap-3 lg:flex-row lg:items-end">
                        <label class="flex-1 text-xs font-semibold uppercase text-secondary">
                            {{ tx('club_memberships.workspace.invoice_run_date', 'Stichtag') }}
                            <input v-model="invoiceRunForm.run_date" type="date" class="mt-1 w-full rounded-lg border border-border bg-inputBg px-3 py-2 text-sm text-primary">
                        </label>
                        <label class="flex-1 text-xs font-semibold uppercase text-secondary">
                            {{ tx('club_memberships.workspace.due_date', 'Fällig am') }}
                            <input v-model="invoiceRunForm.due_date" type="date" class="mt-1 w-full rounded-lg border border-border bg-inputBg px-3 py-2 text-sm text-primary">
                        </label>
                        <label class="flex-1 text-xs font-semibold uppercase text-secondary">
                            {{ tx('auto.Titel', 'Titel') }}
                            <input v-model="invoiceRunForm.title" class="mt-1 w-full rounded-lg border border-border bg-inputBg px-3 py-2 text-sm text-primary">
                        </label>
                        <button type="button" class="rounded-lg border border-border px-4 py-2 text-sm font-semibold text-primary disabled:cursor-wait disabled:opacity-60" :disabled="invoiceRunPreviewLoading" @click="previewInvoiceRun">
                            {{ invoiceRunPreviewLoading ? tx('auto.Wird geprüft …', 'Wird geprüft …') : tx('club_memberships.workspace.preview_invoice_run', 'Vorschau prüfen') }}
                        </button>
                        <button type="button" class="rounded-lg bg-buttonPrimary px-4 py-2 text-sm font-semibold text-buttonTextPrimary disabled:cursor-not-allowed disabled:opacity-60" :disabled="invoiceRunForm.processing || !invoiceRunPreview?.billable_count" @click="createInvoiceRun">
                            {{ invoiceRunForm.processing ? tx('auto.Wird gespeichert …', 'Wird gespeichert …') : tx('club_memberships.workspace.create_invoice_run', 'Rechnungslauf erstellen') }}
                        </button>
                    </div>
                    <p v-if="invoiceRunForm.errors.run_date || invoiceRunForm.errors.due_date || invoiceRunForm.errors.title" class="mt-2 text-sm font-semibold text-error">
                        {{ invoiceRunForm.errors.run_date || invoiceRunForm.errors.due_date || invoiceRunForm.errors.title }}
                    </p>
                    <div v-if="invoiceRunPreview" class="mt-4 grid gap-3 sm:grid-cols-2 xl:grid-cols-5">
                        <div class="rounded-lg border border-border bg-inputBg p-3">
                            <p class="text-xs font-semibold uppercase text-secondary">{{ tx('club_memberships.workspace.billable_members', 'Erstellbar') }}</p>
                            <p class="mt-1 text-lg font-bold text-primary">{{ invoiceRunPreview.billable_count }}</p>
                        </div>
                        <div class="rounded-lg border border-border bg-inputBg p-3">
                            <p class="text-xs font-semibold uppercase text-secondary">{{ tx('club_memberships.workspace.invoice_run_total', 'Gesamt') }}</p>
                            <p class="mt-1 text-lg font-bold text-primary">{{ formatMoney(invoiceRunPreview.total_amount) }}</p>
                        </div>
                        <div class="rounded-lg border border-border bg-inputBg p-3">
                            <p class="text-xs font-semibold uppercase text-secondary">{{ tx('club_memberships.workspace.bank_transfer', 'Überweisung') }}</p>
                            <p class="mt-1 text-lg font-bold text-primary">{{ invoiceRunPreview.transfer_count }}</p>
                        </div>
                        <div class="rounded-lg border border-border bg-inputBg p-3">
                            <p class="text-xs font-semibold uppercase text-secondary">{{ tx('club_memberships.workspace.direct_debit', 'Lastschrift') }}</p>
                            <p class="mt-1 text-lg font-bold text-primary">{{ invoiceRunPreview.direct_debit_count }}</p>
                        </div>
                        <div class="rounded-lg border border-border bg-inputBg p-3">
                            <p class="text-xs font-semibold uppercase text-secondary">{{ tx('club_memberships.workspace.skipped', 'Übersprungen') }}</p>
                            <p class="mt-1 text-lg font-bold text-primary">{{ invoiceRunPreview.skipped_count }}</p>
                        </div>
                    </div>
                    <div v-if="invoiceRunPreview?.rows?.length" class="mt-4 max-h-80 overflow-auto rounded-lg border border-border">
                        <table class="min-w-full text-left text-xs">
                            <thead class="bg-inputBg text-secondary">
                                <tr>
                                    <th class="px-3 py-2">{{ tx('auto.Mitglied', 'Mitglied') }}</th>
                                    <th class="px-3 py-2">{{ tx('auto.Betrag', 'Betrag') }}</th>
                                    <th class="px-3 py-2">{{ tx('club_memberships.workspace.period', 'Zeitraum') }}</th>
                                    <th class="px-3 py-2">{{ tx('club_memberships.workspace.payment_method_label', 'Zahlmethode') }}</th>
                                    <th class="px-3 py-2">{{ tx('auto.Status', 'Status') }}</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-border">
                                <tr v-for="row in invoiceRunPreview.rows" :key="`${row.member_type}-${row.member_id}`">
                                    <td class="px-3 py-2 text-primary">
                                        <span class="block font-semibold">{{ row.member_name }}</span>
                                        <span class="block text-secondary">{{ row.member_email || '-' }}</span>
                                    </td>
                                    <td class="px-3 py-2 text-primary">
                                        <span class="block font-semibold">{{ formatMoney(row.amount) }}</span>
                                        <span v-if="row.snapshot?.prorated" class="block text-secondary">
                                            {{ row.snapshot.billable_days }}/{{ row.snapshot.period_days }} Tage ·
                                            Vollbetrag {{ formatMoney(row.snapshot.full_amount) }}
                                        </span>
                                        <span v-if="Number(row.snapshot?.component_amount || 0) > 0" class="block text-secondary">
                                            Zuschläge: {{ formatMoney(row.snapshot.component_amount) }}
                                        </span>
                                        <span v-if="Number(row.snapshot?.discount_amount || 0) > 0" class="block text-secondary">
                                            Rabatte: -{{ formatMoney(row.snapshot.discount_amount) }}
                                        </span>
                                    </td>
                                    <td class="px-3 py-2 text-secondary">{{ formatDate(row.billing_period_start) }} – {{ formatDate(row.billing_period_end) }}</td>
                                    <td class="px-3 py-2 text-secondary">{{ row.payment_flow === 'direct_debit' ? tx('club_memberships.workspace.direct_debit', 'Lastschrift') : tx('club_memberships.workspace.bank_transfer', 'Überweisung') }}</td>
                                    <td class="px-3 py-2">
                                        <span class="rounded-full px-2 py-1 font-semibold" :class="row.can_create ? 'bg-success/10 text-success' : 'bg-warning/10 text-warning'">
                                            {{ row.can_create
                                                ? tx('club_memberships.workspace.ready', 'Bereit')
                                                : (row.skip_reason === 'duplicate'
                                                    ? tx('club_memberships.workspace.duplicate_invoice', 'Schon vorhanden')
                                                    : (row.skip_reason === 'missing_recipient'
                                                        ? tx('club_memberships.workspace.missing_recipient', 'Zahlungs- oder Empfängerdaten fehlen')
                                                        : tx('club_memberships.workspace.skipped', 'Übersprungen'))) }}
                                        </span>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
                <div class="mt-4 flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                    <p class="text-sm text-secondary">{{ filteredInvoices.length }} von {{ invoices.length }} {{ tx('auto.Rechnungen', 'Rechnungen') }} sichtbar.</p>
                    <select v-model="invoiceStatusFilter" :aria-label="tx('club_memberships.workspace.invoice_status_filter', 'Rechnungsstatus filtern')" class="rounded-lg border border-border bg-inputBg px-3 py-2 text-sm text-primary">
                        <option value="all">{{ tx('auto.Alle Status', 'Alle Status') }}</option>
                        <option v-for="status in invoiceStatusOptions" :key="status.value" :value="status.value">
                            {{ status.label }}
                        </option>
                    </select>
                </div>
                <SavedViewBar
                    :views="invoiceSavedViews.views.value"
                    :loading="invoiceSavedViews.loading.value"
                    :error="invoiceSavedViews.error.value"
                    @apply="applyInvoiceSavedView"
                    @save="saveInvoiceView"
                    @remove="invoiceSavedViews.remove"
                />
                <div class="mt-4 grid gap-3 sm:grid-cols-2 xl:grid-cols-4">
                    <div class="rounded-lg border border-border bg-bg p-4">
                        <p class="text-xs font-semibold uppercase text-secondary">{{ tx('auto.Offen', 'Offen') }}</p>
                        <p class="mt-2 text-xl font-bold text-primary">{{ invoiceSummary.open_count || 0 }}</p>
                        <p class="mt-1 text-xs text-secondary">{{ formatMoney(invoiceSummary.open_amount) }}</p>
                    </div>
                    <div class="rounded-lg border border-border bg-bg p-4">
                        <p class="text-xs font-semibold uppercase text-secondary">{{ tx('auto.Bezahlt', 'Bezahlt') }}</p>
                        <p class="mt-2 text-xl font-bold text-primary">{{ invoiceSummary.paid_count || 0 }}</p>
                        <p class="mt-1 text-xs text-secondary">{{ formatMoney(invoiceSummary.paid_amount) }}</p>
                    </div>
                    <div class="rounded-lg border border-border bg-bg p-4">
                        <p class="text-xs font-semibold uppercase text-secondary">{{ tx('auto.Überfällig', 'Überfällig') }}</p>
                        <p class="mt-2 text-xl font-bold text-primary">{{ invoiceSummary.overdue_count || 0 }}</p>
                        <p class="mt-1 text-xs text-secondary">{{ formatMoney(invoiceSummary.overdue_amount) }}</p>
                    </div>
                    <div class="rounded-lg border border-border bg-bg p-4">
                        <p class="text-xs font-semibold uppercase text-secondary">{{ tx('auto.Storniert', 'Storniert') }}</p>
                        <p class="mt-2 text-xl font-bold text-primary">{{ invoiceSummary.cancelled_count || 0 }}</p>
                        <p class="mt-1 text-xs text-secondary">{{ formatMoney(invoiceSummary.cancelled_amount) }}</p>
                    </div>
                </div>
                <div class="mt-4 overflow-x-auto">
                    <table class="min-w-full text-left text-sm">
                        <thead class="text-xs uppercase text-secondary">
                            <tr>
                                <th class="py-2 pr-4">{{ tx('auto.Nr.', 'Nr.') }}</th>
                                <th class="py-2 pr-4">{{ tx('auto.User', 'User') }}</th>
                                <th class="py-2 pr-4">{{ tx('auto.Titel', 'Titel') }}</th>
                                <th class="py-2 pr-4">{{ tx('auto.Betrag', 'Betrag') }}</th>
                                <th class="py-2 pr-4">{{ tx('auto.Fällig', 'Fällig') }}</th>
                                <th class="py-2 pr-4">{{ tx('auto.Status', 'Status') }}</th>
                                <th class="py-2 pr-4">{{ tx('auto.Aktion', 'Aktion') }}</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-border">
                            <tr v-for="invoice in filteredInvoices" :key="invoice.id">
                                <td class="py-3 pr-4 text-primary">{{ invoice.number }}</td>
                                <td class="py-3 pr-4 text-secondary">{{ invoice.user?.name || '-' }}</td>
                                <td class="py-3 pr-4 text-primary">
                                    <span class="block">{{ invoice.title || '-' }}</span>
                                    <span v-if="invoice.billing_period_start || invoice.billing_period_end" class="mt-1 block text-xs text-secondary">
                                        {{ formatDate(invoice.billing_period_start) }} – {{ formatDate(invoice.billing_period_end) }}
                                    </span>
                                    <span class="mt-1 block text-xs text-secondary">
                                        {{ tx('club_memberships.workspace.business_year', 'Geschäftsjahr') }}: {{ invoice.business_year_period?.name || tx('club_memberships.workspace.historically_unassigned', 'historisch unzugeordnet') }}
                                    </span>
                                    <span v-if="['recurring_contribution', 'membership_contribution'].includes(invoice.source)" class="mt-1 block text-xs text-secondary">
                                        {{ tx('club_memberships.workspace.contribution_year', 'Beitragsjahr') }}: {{ invoice.contribution_year_period?.name || tx('club_memberships.workspace.historically_unassigned', 'historisch unzugeordnet') }}
                                    </span>
                                    <span v-if="invoice.reminder_sent_at" class="mt-1 block text-xs font-semibold text-warning">
                                        {{ tx('club_memberships.workspace.reminder_sent_on', 'Erinnert am {date}', { date: formatDate(invoice.reminder_sent_at) }) }}
                                    </span>
                                </td>
                                <td class="py-3 pr-4 text-primary">
                                    {{ formatMoney(invoice.amount) }}
                                    <span v-if="invoice.is_partially_paid" class="mt-1 block text-xs text-secondary">
                                        {{ tx('club_memberships.workspace.outstanding_balance', 'Restbetrag') }}: {{ formatMoney(invoice.outstanding_amount) }}
                                    </span>
                                    <span v-if="Number(invoice.overpaid_amount) > 0" class="mt-1 block text-xs text-secondary">
                                        {{ tx('club_memberships.workspace.overpayment', 'Überzahlung') }}: {{ formatMoney(invoice.overpaid_amount) }}
                                    </span>
                                </td>
                                <td class="py-3 pr-4 text-secondary">{{ formatDate(invoice.due_date) }}</td>
                                <td class="py-3 pr-4">
                                    <div class="flex flex-col gap-2">
                                        <span class="inline-flex w-fit rounded-full px-2 py-1 text-xs font-semibold" :class="invoiceStatusClass(invoice.status)">
                                            {{ invoice.status_label || invoiceStatusLabel(invoice.status) }}
                                        </span>
                                        <select :value="invoice.status" class="rounded border border-border bg-inputBg px-2 py-1 text-xs text-primary disabled:cursor-wait disabled:opacity-60" :disabled="processingInvoiceIds.has(invoice.id)" @change="updateInvoiceStatus(invoice, $event.target.value)">
                                            <option v-for="status in invoiceStatusOptions" :key="status.value" :value="status.value">
                                                {{ status.label }}
                                            </option>
                                        </select>
                                    </div>
                                </td>
                                <td class="py-3 pr-4">
                                    <div class="flex flex-wrap gap-2">
                                        <button v-if="!['paid', 'cancelled', 'waived'].includes(invoice.status)" type="button" class="rounded bg-buttonPrimary px-2 py-1 text-xs text-buttonTextPrimary disabled:cursor-wait disabled:opacity-60" :disabled="processingInvoiceIds.has(invoice.id)" @click="openPayment(invoice)">
                                            {{ tx('club_memberships.workspace.record_payment', 'Zahlung erfassen') }}
                                        </button>
                                        <button
                                            v-if="!['paid', 'cancelled', 'waived'].includes(invoice.status)"
                                            type="button"
                                            class="rounded border border-border px-2 py-1 text-xs text-primary disabled:cursor-not-allowed disabled:opacity-50"
                                            :disabled="capabilities.payment_reminders === false || processingInvoiceIds.has(invoice.id)"
                                            :title="capabilities.payment_reminders === false ? tx('club_memberships.workspace.payment_reminders_unavailable', 'Mahnungen sind ab Club verfügbar') : ''"
                                            @click="sendReminder(invoice)"
                                        >
                                            {{ tx('club_memberships.workspace.payment_reminder', 'Mahnung') }}
                                        </button>
                                    </div>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                    <p v-if="!filteredInvoices.length" class="py-6 text-sm text-secondary">{{ tx('auto.Keine passenden Rechnungen.', 'Keine passenden Rechnungen.') }}</p>
                </div>
            </section>

            <section v-if="activeTab === 'payments'" class="surface-card p-5">
                <div class="flex flex-col gap-4 lg:flex-row lg:items-start lg:justify-between">
                    <div>
                        <p class="text-xs font-semibold uppercase tracking-wide text-air-blue">{{ tx('club_memberships.workspace.treasury_eyebrow', 'Vereinskasse') }}</p>
                        <h2 class="mt-1 text-lg font-semibold text-primary">{{ tx('club_memberships.workspace.treasury_title', 'Einnahmen, Ausgaben und Bestände') }}</h2>
                        <p class="mt-1 max-w-2xl text-sm text-secondary">
                            {{ tx('club_memberships.workspace.treasury_intro', 'Mitgliedszahlungen, Spenden und Vorauszahlungen fließen automatisch ein. Zusätzliche Einnahmen und Ausgaben werden im Kassenbuch erfasst.') }}
                        </p>
                    </div>
                    <div class="flex flex-wrap gap-2">
                        <button
                            type="button"
                            class="rounded-lg border border-air-green/50 px-4 py-2 text-sm font-semibold text-air-green hover:bg-air-green/10"
                            @click="openFinanceEntryModal('income')"
                        >
                            {{ tx('club_memberships.workspace.book_income', 'Einnahme buchen') }}
                        </button>
                        <button
                            type="button"
                            class="rounded-lg border border-error/50 px-4 py-2 text-sm font-semibold text-error hover:bg-error/10"
                            @click="openFinanceEntryModal('expense')"
                        >
                            {{ tx('club_memberships.workspace.book_expense', 'Ausgabe buchen') }}
                        </button>
                    </div>
                </div>

                <p v-if="bankImportFeedback" class="mt-4 rounded-lg border border-success/30 bg-success/10 px-3 py-2 text-sm font-semibold text-success" aria-live="polite">
                    {{ bankImportFeedback }}
                </p>
                <p v-if="financeActionFeedback" class="mt-4 rounded-lg border border-success/30 bg-success/10 px-3 py-2 text-sm font-semibold text-success" aria-live="polite">
                    {{ financeActionFeedback }}
                </p>
                <p v-if="financeActionError" class="mt-4 rounded-lg border border-error/30 bg-error/10 px-3 py-2 text-sm font-semibold text-error" role="alert">
                    {{ financeActionError }}
                </p>

                <div class="mt-5 grid grid-cols-1 gap-3 sm:grid-cols-2 xl:grid-cols-5">
                    <div class="rounded-lg border border-border bg-inputBg/60 p-4">
                        <div class="text-xs font-semibold uppercase text-secondary">{{ tx('club_memberships.workspace.cash_balance', 'Barbestand') }}</div>
                        <div class="mt-2 text-2xl font-bold text-primary">{{ formatMoney(cashBalance) }}</div>
                        <div class="mt-1 text-xs text-secondary">{{ tx('club_memberships.workspace.current_balance', 'aktueller Bestand') }}</div>
                    </div>
                    <div class="rounded-lg border border-border bg-inputBg/60 p-4">
                        <div class="text-xs font-semibold uppercase text-secondary">{{ tx('club_memberships.workspace.bank_balance', 'Bankbestand') }}</div>
                        <div class="mt-2 text-2xl font-bold text-primary">{{ formatMoney(bankBalance) }}</div>
                        <div class="mt-1 text-xs text-secondary">{{ tx('club_memberships.workspace.current_balance', 'aktueller Bestand') }}</div>
                    </div>
                    <div class="rounded-lg border border-border bg-inputBg/60 p-4">
                        <div class="text-xs font-semibold uppercase text-secondary">{{ tx('auto.Gesamt', 'Gesamt') }}</div>
                        <div class="mt-2 text-2xl font-bold text-primary">{{ formatMoney(totalBalance) }}</div>
                        <div class="mt-1 text-xs text-secondary">
                            <span v-if="unassignedBalance > 0">{{ tx('club_memberships.workspace.manual_included', 'inkl. {amount} manuell', { amount: formatMoney(unassignedBalance) }) }}</span>
                            <span v-else>{{ tx('club_memberships.workspace.current_total', 'aktueller Gesamtbestand') }}</span>
                        </div>
                    </div>
                    <div class="rounded-lg border border-air-green/25 bg-air-green/5 p-4">
                        <div class="text-xs font-semibold uppercase text-secondary">{{ tx('club_memberships.workspace.income', 'Einnahmen') }}</div>
                        <div class="mt-2 text-2xl font-bold text-air-green">{{ formatMoney(incomePeriodTotal) }}</div>
                        <div class="mt-1 text-xs text-secondary">{{ financePeriodLabel }}</div>
                    </div>
                    <div class="rounded-lg border border-error/25 bg-error/5 p-4">
                        <div class="text-xs font-semibold uppercase text-secondary">{{ tx('club_memberships.workspace.expenses', 'Ausgaben') }}</div>
                        <div class="mt-2 text-2xl font-bold text-error">{{ formatMoney(expensePeriodTotal) }}</div>
                        <div class="mt-1 text-xs text-secondary">{{ financePeriodLabel }}</div>
                    </div>
                </div>
            </section>

            <section v-if="activeTab === 'payments'" class="surface-card p-5">
                <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                    <div>
                        <h2 class="text-lg font-semibold text-primary">{{ tx('club_memberships.workspace.cashbook', 'Kassenbuch') }}</h2>
                        <p class="mt-1 text-sm text-secondary">
                            {{ tx('club_memberships.workspace.cashbook_intro', 'Freie Einnahmen und Ausgaben, die nicht aus einer Mitgliedsrechnung entstehen.') }}
                        </p>
                    </div>
                    <button
                        type="button"
                        class="rounded-lg border border-border px-4 py-2 text-sm font-semibold text-primary hover:bg-inputBg"
                        @click="openFinanceEntryModal('expense')"
                    >
                        {{ tx('club_memberships.workspace.add_booking', 'Buchung hinzufügen') }}
                    </button>
                </div>

                <div class="mt-4 overflow-x-auto">
                    <table v-if="financeEntries.length" class="min-w-full text-left text-sm">
                        <thead class="text-xs uppercase text-secondary">
                            <tr>
                                <th class="py-2 pr-4">{{ tx('auto.Datum', 'Datum') }}</th>
                                <th class="py-2 pr-4">{{ tx('club_memberships.workspace.booking', 'Buchung') }}</th>
                                <th class="py-2 pr-4">{{ tx('club_memberships.workspace.account', 'Konto') }}</th>
                                <th class="py-2 pr-4">{{ tx('auto.Betrag', 'Betrag') }}</th>
                                <th class="py-2 pr-4">{{ tx('auto.Aktion', 'Aktion') }}</th>
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
                                    <div class="mt-1 text-xs text-secondary">
                                        {{ tx('club_memberships.workspace.business_year', 'Geschäftsjahr') }}: {{ entry.business_year_period?.name || tx('club_memberships.workspace.historically_unassigned', 'historisch unzugeordnet') }}
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
                                        {{ tx('auto.Bearbeiten', 'Bearbeiten') }}
                                    </button>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                    <p v-else class="rounded-lg border border-dashed border-border bg-bg/50 p-4 text-sm text-secondary">
                        {{ tx('club_memberships.workspace.no_entries', 'Noch keine freien Einnahmen oder Ausgaben erfasst.') }}
                    </p>
                </div>
            </section>

            <section v-if="activeTab === 'payments'" class="surface-card p-5">
                <div class="flex flex-col gap-4 lg:flex-row lg:items-center lg:justify-between">
                    <div>
                        <h2 class="text-lg font-semibold text-primary">{{ tx('club_memberships.workspace.bank_reconciliation', 'Bankabgleich') }}</h2>
                        <p class="mt-1 text-sm text-secondary">
                            {{ tx('club_memberships.workspace.bank_intro', 'CSV-Umsätze importieren, Rechnungen automatisch zuordnen und unklare Treffer manuell bestätigen.') }}
                        </p>
                    </div>
                    <button
                        type="button"
                        class="rounded-lg bg-buttonPrimary px-4 py-2 text-sm font-semibold text-buttonTextPrimary disabled:cursor-not-allowed disabled:opacity-50"
                        :disabled="capabilities.bank_reconciliation === false"
                        :title="capabilities.bank_reconciliation === false ? tx('club_memberships.workspace.bank_unavailable', 'Bankabgleich ist ab Pro verfügbar') : ''"
                        @click="showBankImportModal = true"
                    >
                        {{ tx('club_memberships.workspace.import_bank_csv', 'Bank-CSV importieren') }}
                    </button>
                </div>

                <div class="mt-4 flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                        <p class="text-sm text-secondary">{{ tx('club_memberships.workspace.visible_transactions', '{visible} von {total} Umsätzen sichtbar.', { visible: filteredBankTransactions.length, total: bankTransactions.length }) }}</p>
                    <select v-model="transactionStatusFilter" :aria-label="tx('club_memberships.workspace.transaction_status_filter', 'Bankumsatzstatus filtern')" class="rounded-lg border border-border bg-inputBg px-3 py-2 text-sm text-primary">
                        <option value="all">{{ tx('auto.Alle Status', 'Alle Status') }}</option>
                        <option value="matched">{{ tx('club_memberships.workspace.matched', 'Verbucht') }}</option>
                        <option value="suggested">{{ tx('club_memberships.workspace.suggested', 'Vorschlag') }}</option>
                        <option value="unmatched">{{ tx('auto.Offen', 'Offen') }}</option>
                    </select>
                </div>

                <div class="mt-4 overflow-x-auto">
                    <table class="min-w-full text-left text-sm">
                        <thead class="text-xs uppercase text-secondary">
                            <tr>
                                <th class="py-2 pr-4">{{ tx('auto.Datum', 'Datum') }}</th>
                                <th class="py-2 pr-4">{{ tx('auto.Zahler', 'Zahler') }}</th>
                                <th class="py-2 pr-4">{{ tx('auto.Betrag', 'Betrag') }}</th>
                                <th class="py-2 pr-4">{{ tx('auto.Rechnung', 'Rechnung') }}</th>
                                <th class="py-2 pr-4">{{ tx('auto.Status', 'Status') }}</th>
                                <th class="py-2 pr-4">{{ tx('auto.Aktion', 'Aktion') }}</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-border">
                            <tr v-for="transaction in filteredBankTransactions" :key="transaction.id">
                                <td class="py-3 pr-4 text-secondary">
                                    {{ formatDate(transaction.booking_date) }}
                                    <span class="mt-1 block text-xs">{{ transaction.business_year_period?.name || tx('club_memberships.workspace.historically_unassigned', 'historisch unzugeordnet') }}</span>
                                </td>
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
                                        {{ transaction.status === 'matched' ? tx('club_memberships.workspace.matched', 'Verbucht') : transaction.status === 'suggested' ? tx('club_memberships.workspace.suggested', 'Vorschlag') : tx('auto.Offen', 'Offen') }}
                                    </span>
                                    <div class="mt-1 text-xs text-secondary">{{ transaction.match_reason }}</div>
                                </td>
                                <td class="py-3 pr-4">
                                    <button
                                        v-if="transaction.status === 'suggested' && transaction.invoice"
                                        type="button"
                                        class="rounded border border-border px-2 py-1 text-xs text-primary disabled:cursor-wait disabled:opacity-60"
                                        :disabled="processingBankTransactionIds.has(transaction.id)"
                                        @click="confirmBankTransaction(transaction)"
                                    >
                                        {{ processingBankTransactionIds.has(transaction.id) ? tx('auto.Wird gespeichert …', 'Wird gespeichert …') : tx('club_memberships.workspace.confirm', 'Bestätigen') }}
                                    </button>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                    <p v-if="!filteredBankTransactions.length" class="py-6 text-sm text-secondary">{{ tx('club_memberships.workspace.no_bank_transactions', 'Keine passenden Bankumsätze.') }}</p>
                </div>
            </section>

            <section v-if="activeTab === 'exports'" class="surface-card p-5">
                <div class="flex flex-col gap-4 xl:flex-row xl:items-start xl:justify-between">
                    <div class="max-w-2xl">
                        <h2 class="text-lg font-semibold text-primary">{{ tx('club_memberships.workspace.datev_title', 'DATEV / SKR42') }}</h2>
                        <p class="mt-1 text-sm text-secondary">
                            {{ tx('club_memberships.workspace.datev_intro', 'Exportiere bezahlte Mitgliedsbeiträge als CSV-Buchungsstapel. Konten bitte mit Steuerberatung abstimmen.') }}
                        </p>
                    </div>

                    <a
                        :href="datevExportUrl"
                        class="rounded-lg bg-buttonPrimary px-4 py-2 text-sm font-semibold text-buttonTextPrimary"
                        :class="{ 'pointer-events-none opacity-50': capabilities.datev_export === false }"
                        :title="capabilities.datev_export === false ? tx('club_memberships.workspace.datev_unavailable', 'DATEV-Export ist ab Pro verfügbar') : ''"
                    >
                        {{ tx('club_memberships.workspace.datev_export', 'DATEV-CSV exportieren') }}
                    </a>
                </div>

                <div class="mt-4 grid gap-4 xl:grid-cols-[1fr_1fr]">
                    <form class="grid gap-3 md:grid-cols-2" @submit.prevent="saveDatevSettings">
                        <div>
                            <label class="text-xs font-semibold uppercase text-secondary">{{ tx('club_memberships.workspace.advisor_number', 'Beraternummer') }}</label>
                            <input v-model="datevSettingsFor(selectedClub).datev_consultant_number" class="mt-1 w-full rounded-lg border border-border bg-inputBg px-3 py-2 text-sm text-primary" :placeholder="tx('club_memberships.workspace.optional', 'Optional')">
                        </div>
                        <div>
                            <label class="text-xs font-semibold uppercase text-secondary">{{ tx('club_memberships.workspace.client_number', 'Mandantennummer') }}</label>
                            <input v-model="datevSettingsFor(selectedClub).datev_client_number" class="mt-1 w-full rounded-lg border border-border bg-inputBg px-3 py-2 text-sm text-primary" :placeholder="tx('club_memberships.workspace.optional', 'Optional')">
                        </div>
                        <div>
                            <label class="text-xs font-semibold uppercase text-secondary">{{ tx('club_memberships.workspace.revenue_account', 'Erlöskonto SKR42') }}</label>
                            <input v-model="datevSettingsFor(selectedClub).datev_revenue_account" class="mt-1 w-full rounded-lg border border-border bg-inputBg px-3 py-2 text-sm text-primary" placeholder="z. B. 2110">
                        </div>
                        <div>
                            <label class="text-xs font-semibold uppercase text-secondary">{{ tx('club_memberships.workspace.bank_account', 'Bankkonto SKR42') }}</label>
                            <input v-model="datevSettingsFor(selectedClub).datev_bank_account" class="mt-1 w-full rounded-lg border border-border bg-inputBg px-3 py-2 text-sm text-primary" placeholder="z. B. 1200">
                        </div>
                        <div class="md:col-span-2">
                            <label class="block text-sm">{{ feeText('exportAccount') }}
                                <input v-model="datevSettingsFor(selectedClub).datev_fee_account" inputmode="numeric" pattern="[0-9]{1,20}" maxlength="20" class="mt-1 w-full rounded-lg border border-border bg-inputBg px-3 py-2 text-sm text-primary">
                            </label>
                            <p class="text-xs text-secondary">{{ feeText('exportAccountHelp') }}</p>
                        </div>
                        <div class="md:col-span-2">
                            <button
                                class="rounded-lg border border-border px-4 py-2 text-sm font-semibold text-primary disabled:cursor-not-allowed disabled:opacity-50"
                                :disabled="capabilities.datev_export === false || financeSettingsSaving"
                                :title="capabilities.datev_export === false ? tx('club_memberships.workspace.datev_unavailable', 'DATEV-Export ist ab Pro verfügbar') : ''"
                            >
                                DATEV-Einstellungen speichern
                            </button>
                        </div>
                    </form>

                    <div class="grid gap-3 rounded-lg border border-border bg-bg p-4 md:grid-cols-2">
                        <div>
                            <label class="text-xs font-semibold uppercase text-secondary">{{ tx('auto.Von', 'Von') }}</label>
                            <input v-model="datevExportFor(selectedClub).from" type="date" class="mt-1 w-full rounded-lg border border-border bg-inputBg px-3 py-2 text-sm text-primary">
                        </div>
                        <div>
                            <label class="text-xs font-semibold uppercase text-secondary">{{ tx('auto.Bis', 'Bis') }}</label>
                            <input v-model="datevExportFor(selectedClub).to" type="date" class="mt-1 w-full rounded-lg border border-border bg-inputBg px-3 py-2 text-sm text-primary">
                        </div>
                        <p class="text-xs text-secondary md:col-span-2">
                            {{ tx('club_memberships.workspace.datev_period_hint', 'Exportiert werden bezahlte Zahlungen im Zeitraum. Der CSV-Aufbau ist für die Beta bewusst schlicht und prüfbar gehalten.') }}
                        </p>
                    </div>
                </div>
            </section>
        </template>

        <ClubAccessManager
            :show="Boolean(accessMember)"
            :club="selectedClub"
            :member="accessMember"
            :members="members"
            @close="accessMember = null"
        />

        <Modal :show="showAddMemberModal" max-width="2xl" @close="showAddMemberModal = false">
            <div class="p-2">
                <h2 class="text-xl font-bold text-primary">{{ tx('club_memberships.workspace.email_add_title', 'Mitglieder per E-Mail hinzufügen') }}</h2>
                <p class="mt-1 text-sm text-secondary">
                    {{ tx('club_memberships.workspace.email_add_intro', 'Erfasse mehrere Mitglieder auf einmal. Wenn eine Einladung aktiv ist, werden vorhandene Konten verknüpft, sonst geht eine Einladung per E-Mail raus.') }}
                </p>
                <p
                    v-if="capabilities.member_invitation_daily_limit"
                    class="mt-2 rounded-lg border border-border bg-bg px-3 py-2 text-xs font-semibold text-secondary"
                >
                    {{ tx('club_memberships.workspace.free_limit', 'Free-Limit: {remaining} von {limit} Einladungen heute übrig.', { remaining: capabilities.member_invitation_remaining_today, limit: capabilities.member_invitation_daily_limit }) }}
                </p>

                <form class="mt-5 space-y-4" @submit.prevent="addEmailMember">
                    <label class="flex items-center gap-2 rounded-lg border border-border bg-bg px-3 py-2 text-sm text-primary">
                        <input v-model="emailMemberForm.send_invitation" type="checkbox" class="rounded border-border bg-inputBg">
                        {{ tx('club_memberships.workspace.send_invitation', 'Einladung zu Airmius verschicken') }}
                    </label>

                    <div v-if="emailMemberForm.send_invitation">
                        <label for="email-member-invitation-expires" class="text-xs font-semibold uppercase text-secondary">{{ tx('club_memberships.workspace.invitation_expires', 'Einladung gültig bis') }}</label>
                        <input
                            id="email-member-invitation-expires"
                            v-model="emailMemberForm.invitation_expires_at"
                            type="date"
                            class="mt-1 w-full rounded-lg border border-border bg-inputBg px-3 py-2 text-sm text-primary"
                        >
                    </div>

                    <div class="max-h-[60vh] space-y-3 overflow-y-auto pr-1">
                        <article
                            v-for="(member, index) in emailMemberForm.members"
                            :key="index"
                            class="rounded-lg border border-border bg-bg p-4"
                        >
                            <div class="mb-3 flex items-center justify-between gap-3">
                                <h3 class="text-sm font-semibold text-primary">{{ tx('club_memberships.workspace.member_numbered', 'Mitglied {number}', { number: index + 1 }) }}</h3>
                                <button
                                    type="button"
                                    class="rounded-lg border border-border px-3 py-1 text-xs font-semibold text-primary hover:bg-inputBg"
                                    @click="removeEmailMemberRow(index)"
                                >
                                    {{ tx('club_memberships.workspace.remove_row', 'Entfernen') }}
                                </button>
                            </div>

                            <div class="grid gap-3 md:grid-cols-2">
                                <div>
                                    <label :for="`email-member-${index}-name`" class="text-xs font-semibold uppercase text-secondary">{{ tx('club_memberships.workspace.name', 'Name') }}</label>
                                    <input
                                        :id="`email-member-${index}-name`"
                                        v-model="member.name"
                                        class="mt-1 w-full rounded-lg border border-border bg-inputBg px-3 py-2 text-sm text-primary"
                                        :placeholder="tx('club_memberships.workspace.optional', 'Optional')"
                                    >
                                </div>

                                <div>
                                    <label :for="`email-member-${index}-email`" class="text-xs font-semibold uppercase text-secondary">{{ tx('club_memberships.workspace.email', 'E-Mail') }}</label>
                                    <input
                                        :id="`email-member-${index}-email`"
                                        v-model="member.email"
                                        type="email"
                                        required
                                        class="mt-1 w-full rounded-lg border border-border bg-inputBg px-3 py-2 text-sm text-primary"
                                        placeholder="mitglied@example.org"
                                    >
                                </div>

                                <div>
                                    <label :for="`email-member-${index}-phone`" class="text-xs font-semibold uppercase text-secondary">{{ tx('auto.Telefon', 'Telefon') }}</label>
                                    <input :id="`email-member-${index}-phone`" v-model.trim="member.phone" maxlength="40" class="mt-1 w-full rounded-lg border border-border bg-inputBg px-3 py-2 text-sm text-primary">
                                </div>

	                                <div>
	                                    <label :for="`email-member-${index}-membership-status`" class="text-xs font-semibold uppercase text-secondary">{{ tx('auto.Mitgliedschaft', 'Mitgliedschaft') }}</label>
	                                    <select :id="`email-member-${index}-membership-status`" v-model="member.membership_status" class="mt-1 w-full rounded-lg border border-border bg-inputBg px-3 py-2 text-sm text-primary">
	                                        <option v-for="status in membershipStatuses" :key="status" :value="status">{{ statusLabel(status) }}</option>
	                                    </select>
	                                </div>

                                    <div>
                                        <label :for="`email-member-${index}-membership-type`" class="text-xs font-semibold uppercase text-secondary">{{ tx('club_memberships.workspace.membership_type', 'Mitgliedschaftstyp') }}</label>
                                        <select :id="`email-member-${index}-membership-type`" v-model="member.club_membership_type_id" class="mt-1 w-full rounded-lg border border-border bg-inputBg px-3 py-2 text-sm text-primary">
                                            <option value="">{{ tx('auto.Kein Typ', 'Kein Typ') }}</option>
                                            <option v-for="type in membershipTypes" :key="type.id" :value="type.id">{{ type.name }}</option>
                                        </select>
                                    </div>

	                                <div>
	                                    <label :for="`email-member-${index}-role`" class="text-xs font-semibold uppercase text-secondary">{{ tx('club_memberships.workspace.club_role', 'Vereinsrolle') }}</label>
	                                    <select :id="`email-member-${index}-role`" v-model="member.role" class="mt-1 w-full rounded-lg border border-border bg-inputBg px-3 py-2 text-sm text-primary">
	                                        <option v-for="role in clubRoles" :key="role.value" :value="role.value">{{ role.label }}</option>
	                                    </select>
	                                </div>

                                    <div>
                                        <label :for="`email-member-${index}-family-group`" class="text-xs font-semibold uppercase text-secondary">{{ tx('club_memberships.workspace.household', 'Familie / Haushalt') }}</label>
                                        <input :id="`email-member-${index}-family-group`" v-model.trim="member.family_group_key" maxlength="80" class="mt-1 w-full rounded-lg border border-border bg-inputBg px-3 py-2 text-sm text-primary">
                                    </div>

                                    <div class="md:col-span-2 grid gap-3 sm:grid-cols-[1fr_8rem]">
                                        <label :for="`email-member-${index}-street`" class="text-xs font-semibold uppercase text-secondary">{{ tx('auto.Straße', 'Straße') }}
                                            <input :id="`email-member-${index}-street`" v-model.trim="member.street" maxlength="255" class="mt-1 w-full rounded-lg border border-border bg-inputBg px-3 py-2 text-sm text-primary">
                                        </label>
                                        <label :for="`email-member-${index}-house-number`" class="text-xs font-semibold uppercase text-secondary">{{ tx('auto.Hausnummer', 'Hausnummer') }}
                                            <input :id="`email-member-${index}-house-number`" v-model.trim="member.house_number" maxlength="40" class="mt-1 w-full rounded-lg border border-border bg-inputBg px-3 py-2 text-sm text-primary">
                                        </label>
                                    </div>
                                    <div>
                                        <label :for="`email-member-${index}-postal-code`" class="text-xs font-semibold uppercase text-secondary">{{ tx('auto.PLZ', 'PLZ') }}</label>
                                        <input :id="`email-member-${index}-postal-code`" v-model.trim="member.postal_code" maxlength="30" class="mt-1 w-full rounded-lg border border-border bg-inputBg px-3 py-2 text-sm text-primary">
                                    </div>
                                    <div>
                                        <label :for="`email-member-${index}-city`" class="text-xs font-semibold uppercase text-secondary">{{ tx('auto.Ort', 'Ort') }}</label>
                                        <input :id="`email-member-${index}-city`" v-model.trim="member.city" maxlength="255" class="mt-1 w-full rounded-lg border border-border bg-inputBg px-3 py-2 text-sm text-primary">
                                    </div>
                                    <div>
                                        <label :for="`email-member-${index}-country`" class="text-xs font-semibold uppercase text-secondary">{{ tx('auto.Land', 'Land') }}</label>
                                        <input :id="`email-member-${index}-country`" v-model.trim="member.country" maxlength="2" placeholder="DE" class="mt-1 w-full rounded-lg border border-border bg-inputBg px-3 py-2 text-sm uppercase text-primary">
                                    </div>

                                    <div>
                                        <label :for="`email-member-${index}-payer`" class="text-xs font-semibold uppercase text-secondary">{{ tx('club_memberships.workspace.contribution_payer', 'Beitragszahler') }}</label>
                                        <select :id="`email-member-${index}-payer`" v-model="member.contribution_payer_user_id" class="mt-1 w-full rounded-lg border border-border bg-inputBg px-3 py-2 text-sm text-primary">
                                            <option value="">{{ tx('club_memberships.workspace.pays_self', 'Mitglied zahlt selbst') }}</option>
                                            <option v-for="payer in selectedClub.members" :key="payer.id" :value="payer.id">{{ payer.name }} · {{ payer.email }}</option>
                                        </select>
                                    </div>

	                                <div>
	                                    <label :for="`email-member-${index}-member-number`" class="text-xs font-semibold uppercase text-secondary">{{ tx('club_memberships.workspace.member_number', 'Mitgliedsnummer') }}</label>
                                    <input
                                        :id="`email-member-${index}-member-number`"
                                        v-model="member.member_number"
                                        class="mt-1 w-full rounded-lg border border-border bg-inputBg px-3 py-2 text-sm text-primary"
                                        :placeholder="tx('club_memberships.workspace.optional', 'Optional')"
                                    >
                                </div>

                                <div>
                                    <label :for="`email-member-${index}-license-number`" class="text-xs font-semibold uppercase text-secondary">{{ tx('club_memberships.workspace.license_number', 'Lizenznummer') }}</label>
                                    <input
                                        :id="`email-member-${index}-license-number`"
                                        v-model="member.athlete_license_number"
                                        class="mt-1 w-full rounded-lg border border-border bg-inputBg px-3 py-2 text-sm text-primary"
                                        :placeholder="tx('club_memberships.workspace.optional', 'Optional')"
                                    >
                                </div>

                                <div>
                                    <label :for="`email-member-${index}-license-valid-until`" class="text-xs font-semibold uppercase text-secondary">{{ tx('auto.Lizenz gültig bis', 'Lizenz gültig bis') }}</label>
                                    <input
                                        :id="`email-member-${index}-license-valid-until`"
                                        v-model="member.athlete_license_valid_until"
                                        type="date"
                                        class="mt-1 w-full rounded-lg border border-border bg-inputBg px-3 py-2 text-sm text-primary"
                                    >
                                </div>

                                <div>
                                    <label :for="`email-member-${index}-contribution`" class="text-xs font-semibold uppercase text-secondary">{{ tx('club_memberships.workspace.contribution', 'Beitrag') }}</label>
                                    <input
                                        :id="`email-member-${index}-contribution`"
                                        v-model="member.contribution_amount"
                                        type="number"
                                        min="0"
                                        step="0.01"
                                        class="mt-1 w-full rounded-lg border border-border bg-inputBg px-3 py-2 text-sm text-primary"
                                        placeholder="0,00"
                                    >
                                </div>

                                <div>
                                    <label :for="`email-member-${index}-interval`" class="text-xs font-semibold uppercase text-secondary">{{ tx('club_memberships.workspace.interval_label', 'Intervall') }}</label>
                                    <select :id="`email-member-${index}-interval`" v-model="member.contribution_interval" class="mt-1 w-full rounded-lg border border-border bg-inputBg px-3 py-2 text-sm text-primary">
                                        <option v-for="interval in contributionIntervals" :key="interval" :value="interval">{{ intervalLabel(interval) }}</option>
                                    </select>
                                </div>

                                <div>
                                    <label :for="`email-member-${index}-payment-method`" class="text-xs font-semibold uppercase text-secondary">{{ tx('club_memberships.workspace.payment_method_label', 'Zahlungsart') }}</label>
                                    <select
                                        :id="`email-member-${index}-payment-method`"
                                        v-model="member.payment_method"
                                        class="mt-1 w-full rounded-lg border border-border bg-inputBg px-3 py-2 text-sm text-primary"
                                        @change="member.sepa_mandate_active = member.payment_method === 'sepa_debit'"
                                    >
                                        <option v-for="method in selectedClub.membership_payment_method_options" :key="method.value" :value="method.value">{{ method.label }}</option>
                                    </select>
                                </div>

                                <div>
                                    <label :for="`email-member-${index}-next-invoice`" class="text-xs font-semibold uppercase text-secondary">{{ tx('club_memberships.workspace.next_invoice', 'Nächste automatische Rechnung') }}</label>
                                    <input
                                        :id="`email-member-${index}-next-invoice`"
                                        v-model="member.contribution_next_invoice_on"
                                        type="date"
                                        class="mt-1 w-full rounded-lg border border-border bg-inputBg px-3 py-2 text-sm text-primary"
                                    >
                                </div>

                                <div>
                                    <label :for="`email-member-${index}-sepa-iban`" class="text-xs font-semibold uppercase text-secondary">{{ tx('club_memberships.workspace.sepa_iban', 'SEPA IBAN') }}</label>
                                    <input
                                        :id="`email-member-${index}-sepa-iban`"
                                        v-model="member.sepa_iban"
                                        class="mt-1 w-full rounded-lg border border-border bg-inputBg px-3 py-2 text-sm text-primary"
                                        :placeholder="tx('club_memberships.workspace.optional', 'Optional')"
                                    >
                                </div>

                                <div>
                                    <label :for="`email-member-${index}-sepa-bic`" class="text-xs font-semibold uppercase text-secondary">{{ tx('club_memberships.workspace.sepa_bic', 'SEPA BIC') }}</label>
                                    <input
                                        :id="`email-member-${index}-sepa-bic`"
                                        v-model="member.sepa_bic"
                                        class="mt-1 w-full rounded-lg border border-border bg-inputBg px-3 py-2 text-sm text-primary"
                                        :placeholder="tx('club_memberships.workspace.optional', 'Optional')"
                                    >
                                </div>

                                <div>
                                    <label :for="`email-member-${index}-mandate-reference`" class="text-xs font-semibold uppercase text-secondary">{{ tx('club_memberships.workspace.mandate_reference', 'Mandatsreferenz') }}</label>
                                    <input
                                        :id="`email-member-${index}-mandate-reference`"
                                        v-model="member.sepa_mandate_reference"
                                        class="mt-1 w-full rounded-lg border border-border bg-inputBg px-3 py-2 text-sm text-primary"
                                        :placeholder="tx('club_memberships.workspace.optional', 'Optional')"
                                    >
                                </div>

                                <div>
                                    <label :for="`email-member-${index}-mandate-date`" class="text-xs font-semibold uppercase text-secondary">{{ tx('club_memberships.workspace.mandate_date', 'Mandatsdatum') }}</label>
                                    <input
                                        :id="`email-member-${index}-mandate-date`"
                                        v-model="member.sepa_mandate_signed_on"
                                        type="date"
                                        class="mt-1 w-full rounded-lg border border-border bg-inputBg px-3 py-2 text-sm text-primary"
                                    >
                                </div>

                                <label class="flex items-center gap-2 self-end rounded-lg border border-border bg-inputBg px-3 py-2 text-sm text-primary">
                                    <input
                                        v-model="member.sepa_mandate_active"
                                        type="checkbox"
                                        class="rounded border-border bg-bg"
                                        @change="member.payment_method = member.sepa_mandate_active ? 'sepa_debit' : 'bank_transfer'"
                                    >
                                    {{ tx('club_memberships.workspace.sepa_active', 'SEPA aktiv') }}
                                </label>

                                <div>
                                    <label :for="`email-member-${index}-membership-end`" class="text-xs font-semibold uppercase text-secondary">{{ tx('club_memberships.workspace.membership_end', 'Ende der Mitgliedschaft') }}</label>
                                    <input
                                        :id="`email-member-${index}-membership-end`"
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
                        {{ tx('club_memberships.workspace.add_another_member', 'Weiteres Mitglied') }}
                    </button>

                    <div class="flex justify-end gap-2">
                        <button type="button" class="rounded-lg border border-border px-4 py-2 text-sm font-semibold text-primary" @click="showAddMemberModal = false">
                            {{ tx('auto.Abbrechen', 'Abbrechen') }}
                        </button>
                        <button class="rounded-lg bg-buttonPrimary px-4 py-2 text-sm font-semibold text-buttonTextPrimary">
                            {{ tx('club_memberships.workspace.save_members', 'Mitglieder speichern') }}
                        </button>
                    </div>
                </form>
            </div>
        </Modal>

        <Modal :show="showImportModal" max-width="2xl" @close="closeImportModal">
            <div class="p-2">
                <h2 class="text-xl font-bold text-primary">{{ tx('club_memberships.workspace.import_title', 'Mitglieder importieren') }}</h2>
                <p class="mt-1 text-sm text-secondary">
                    {{ tx('club_memberships.workspace.import_intro', 'Importiere Excel- oder CSV-Listen mit Name, E-Mail, Mitgliedsnummer, Lizenznummer, Beitrag, Eintritts- und Enddatum.') }}
                </p>

                <div class="mt-4 rounded-lg border border-border bg-bg p-4 text-sm text-secondary">
                    <p class="font-semibold text-primary">{{ tx('club_memberships.workspace.recommended', 'Empfohlen') }}</p>
                    <p class="mt-1">
                        {{ tx('club_memberships.workspace.template_intro', 'Lade zuerst die Airmius Excel-Vorlage herunter. Die erste Beispielzeile kannst du ersetzen oder entfernen.') }}
                    </p>
                    <a
                        :href="route('auth.club-memberships.import-template')"
                        class="mt-3 inline-flex rounded-lg border border-border px-3 py-2 text-sm font-semibold text-primary hover:bg-inputBg"
                    >
                        {{ tx('club_memberships.workspace.download_template', 'Vorlage herunterladen') }}
                    </a>
                </div>

                <form class="mt-5 space-y-4" @submit.prevent="importPreview ? importEmailMembers() : previewEmailMembers()">
                    <div>
                        <label for="club-member-import-file" class="text-xs font-semibold uppercase text-secondary">{{ tx('club_memberships.workspace.file', 'Datei') }}</label>
                        <input
                            id="club-member-import-file"
                            type="file"
                            accept=".xlsx,.csv,.txt"
                            required
                            class="mt-1 block w-full rounded-lg border border-border bg-inputBg px-3 py-2 text-sm text-primary"
                            @change="selectImportFile"
                        >
                        <p v-if="importForm.errors.file" class="mt-2 text-sm text-error">{{ importForm.errors.file }}</p>
                        <p v-if="importPreviewError" class="mt-2 text-sm font-semibold text-error" role="alert">{{ importPreviewError }}</p>
                    </div>

                    <section
                        v-if="importPreview"
                        class="rounded-lg border border-border bg-bg p-4"
                        aria-live="polite"
                        aria-labelledby="club-member-import-preview-title"
                    >
                        <h3 id="club-member-import-preview-title" class="font-semibold text-primary">
                            {{ tx('club_memberships.workspace.preview_title', 'Import-Vorschau') }}
                        </h3>
                        <p class="mt-1 text-sm text-secondary">
                            {{ tx('club_memberships.workspace.preview_summary', '{valid} von {total} Zeilen können importiert werden. {errors} Fehler wurden gefunden.', { valid: importPreview.valid_rows, total: importPreview.total_rows, errors: importPreview.error_count }) }}
                        </p>

                        <div v-if="importPreview.rows?.length" class="mt-3 max-h-44 overflow-auto rounded-lg border border-border">
                            <table class="min-w-full text-left text-xs">
                                <thead class="sticky top-0 bg-inputBg text-secondary">
                                    <tr>
                                        <th scope="col" class="px-3 py-2">{{ tx('auto.Zeile', 'Zeile') }}</th>
                                        <th scope="col" class="px-3 py-2">{{ tx('auto.Name', 'Name') }}</th>
                                        <th scope="col" class="px-3 py-2">{{ tx('auto.E-Mail', 'E-Mail') }}</th>
                                        <th scope="col" class="px-3 py-2">{{ tx('auto.Aktion', 'Aktion') }}</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-border text-primary">
                                    <tr v-for="row in importPreview.rows" :key="`${row.row}-${row.email}`">
                                        <td class="px-3 py-2">{{ row.row }}</td>
                                        <td class="px-3 py-2">{{ row.name || '—' }}</td>
                                        <td class="px-3 py-2">{{ row.email }}</td>
                                        <td class="px-3 py-2">
                                            {{ row.action === 'link_existing_user'
                                                ? tx('club_memberships.workspace.preview_link', 'Konto verknüpfen')
                                                : row.action === 'update_external_member'
                                                    ? tx('club_memberships.workspace.preview_update', 'Mitglied aktualisieren')
                                                    : tx('club_memberships.workspace.preview_create', 'Mitglied anlegen') }}
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>

                        <ul v-if="importPreview.errors?.length" class="mt-3 space-y-1 text-xs text-error">
                            <li v-for="error in importPreview.errors" :key="`${error.row}-${error.email}-${error.reason}`">
                                {{ tx('club_memberships.workspace.preview_error_row', 'Zeile {row}: {reason}', { row: error.row, reason: error.reason }) }}
                            </li>
                        </ul>
                        <p v-if="!importPreview.can_import" class="mt-3 text-sm font-semibold text-error" role="alert">
                            {{ tx('club_memberships.workspace.preview_empty', 'Es wurden keine importierbaren Mitglieder gefunden.') }}
                        </p>
                    </section>

                    <label class="flex items-start gap-2 rounded-lg border border-border bg-bg px-3 py-2 text-sm text-primary">
                        <input v-model="importForm.send_invitation" type="checkbox" class="mt-1 rounded border-border bg-inputBg">
                        <span>
                            {{ tx('club_memberships.workspace.invitation_link_direct', 'Einladung/Verknüpfung direkt aktivieren') }}
                            <span class="block text-xs text-secondary">
                                {{ tx('club_memberships.workspace.invitation_link_hint', 'Bestehende Airmius-Konten werden verbunden, sonst wird eine Einladung an die E-Mail-Adresse gesendet.') }}
                            </span>
                        </span>
                    </label>

                    <div class="flex justify-end gap-2">
                        <button type="button" class="rounded-lg border border-border px-4 py-2 text-sm font-semibold text-primary" @click="closeImportModal">
                            {{ tx('auto.Abbrechen', 'Abbrechen') }}
                        </button>
                        <button
                            class="rounded-lg bg-buttonPrimary px-4 py-2 text-sm font-semibold text-buttonTextPrimary disabled:cursor-not-allowed disabled:opacity-60"
                            :disabled="importSubmitting || importPreviewLoading || (Boolean(importPreview) && !importPreview.can_import)"
                        >
                            {{ importPreviewLoading
                                ? tx('club_memberships.workspace.preview_loading', 'Datei wird geprüft …')
                                : importPreview
                                    ? tx('club_memberships.workspace.start_import', 'Import jetzt starten')
                                    : tx('club_memberships.workspace.preview_action', 'Vorschau prüfen') }}
                        </button>
                    </div>
                </form>
            </div>
        </Modal>

        <Modal :show="Boolean(paymentInvoice)" max-width="lg" @close="paymentInvoice = null">
            <div class="p-2">
                <h2 class="text-xl font-bold text-primary">{{ tx('club_memberships.workspace.record_payment', 'Zahlung erfassen') }}</h2>
                <p class="mt-1 text-sm text-secondary">
                    {{ paymentInvoice?.number }} · {{ paymentInvoice?.user?.name || '-' }} · {{ formatMoney(paymentInvoice?.amount) }}
                </p>
                <form class="mt-5 space-y-4" @submit.prevent="recordInvoicePayment">
                    <div class="grid gap-3 sm:grid-cols-2">
                        <div>
                            <label for="club-invoice-payment-amount" class="text-xs font-semibold uppercase text-secondary">{{ tx('club_memberships.workspace.amount_eur', 'Betrag EUR') }}</label>
                            <input id="club-invoice-payment-amount" v-model="paymentForm.amount" type="number" min="0.01" step="0.01" class="mt-1 w-full rounded-lg border border-border bg-inputBg px-3 py-2 text-sm text-primary" required>
                            <p v-if="paymentForm.errors.amount" class="mt-1 text-xs text-error">{{ paymentForm.errors.amount }}</p>
                        </div>
                        <div>
                            <label for="club-invoice-payment-method" class="text-xs font-semibold uppercase text-secondary">{{ tx('club_memberships.workspace.payment_method', 'Zahlungsart') }}</label>
                            <select id="club-invoice-payment-method" v-model="paymentForm.method" class="mt-1 w-full rounded-lg border border-border bg-inputBg px-3 py-2 text-sm text-primary">
                                <option value="bank_transfer">{{ tx('club_memberships.payment_method.bank_transfer', 'Überweisung') }}</option>
                                <option value="cash">{{ tx('club_memberships.payment_method.cash', 'Bar') }}</option>
                                <option value="sepa_debit">{{ tx('club_memberships.payment_method.sepa_debit', 'SEPA-Lastschrift') }}</option>
                                <option value="manual">{{ tx('club_memberships.payment_method.manual', 'Manuell') }}</option>
                            </select>
                        </div>
                        <div>
                            <label for="club-invoice-payment-date" class="text-xs font-semibold uppercase text-secondary">{{ tx('club_memberships.workspace.paid_at', 'Zahlungsdatum') }}</label>
                            <input id="club-invoice-payment-date" v-model="paymentForm.paid_at" type="date" class="mt-1 w-full rounded-lg border border-border bg-inputBg px-3 py-2 text-sm text-primary">
                        </div>
                        <div>
                            <label for="club-invoice-payment-reference" class="text-xs font-semibold uppercase text-secondary">{{ tx('club_memberships.workspace.reference', 'Referenz') }}</label>
                            <input id="club-invoice-payment-reference" v-model="paymentForm.reference" class="mt-1 w-full rounded-lg border border-border bg-inputBg px-3 py-2 text-sm text-primary">
                        </div>
                        <div class="sm:col-span-2">
                            <label for="club-invoice-payment-notes" class="text-xs font-semibold uppercase text-secondary">{{ tx('auto.Notiz', 'Notiz') }}</label>
                            <textarea id="club-invoice-payment-notes" v-model="paymentForm.notes" rows="3" class="mt-1 w-full rounded-lg border border-border bg-inputBg px-3 py-2 text-sm text-primary"></textarea>
                        </div>
                    </div>
                    <p v-if="Object.keys(paymentForm.errors).length" class="text-sm font-semibold text-error" role="alert">{{ Object.values(paymentForm.errors)[0] }}</p>
                    <div class="flex flex-wrap justify-end gap-3">
                        <button type="button" class="rounded-lg border border-border px-4 py-2 text-sm font-semibold text-primary" :disabled="paymentForm.processing" @click="paymentInvoice = null">{{ tx('auto.Abbrechen', 'Abbrechen') }}</button>
                        <button class="rounded-lg bg-buttonPrimary px-4 py-2 text-sm font-semibold text-buttonTextPrimary disabled:cursor-wait disabled:opacity-60" :disabled="paymentForm.processing">
                            {{ paymentForm.processing ? tx('auto.Wird gespeichert …', 'Wird gespeichert …') : tx('club_memberships.workspace.record_payment', 'Zahlung erfassen') }}
                        </button>
                    </div>
                </form>
            </div>
        </Modal>

        <Modal :show="showFinanceEntryModal" max-width="2xl" @close="showFinanceEntryModal = false">
            <div class="p-2">
                <h2 class="text-xl font-bold text-primary">
                    {{ editingFinanceEntryId ? tx('club_memberships.workspace.finance_edit_title', 'Buchung bearbeiten') : tx('club_memberships.workspace.finance_add_title', '{type} buchen', { type: financeTypeLabel(financeEntryForm.type) }) }}
                </h2>
                <p class="mt-1 text-sm text-secondary">
                    {{ tx('club_memberships.workspace.finance_intro', 'Erfasse freie Einnahmen und Ausgaben für Kasse oder Bank. Mitgliedszahlungen werden weiterhin über Rechnungen, Spenden oder Vorauszahlungen gebucht.') }}
                </p>

                <form class="mt-5 space-y-4" @submit.prevent="saveFinanceEntry">
                    <div class="grid gap-3 sm:grid-cols-2">
                        <div>
                            <label class="text-xs font-semibold uppercase text-secondary">{{ tx('auto.Typ', 'Typ') }}</label>
                            <select
                                v-model="financeEntryForm.type"
                                class="mt-1 w-full rounded-lg border border-border bg-inputBg px-3 py-2 text-sm text-primary"
                                @change="syncFinanceEntryCategoryForType"
                            >
                                <option value="income">{{ tx('club_memberships.finance.type.income', 'Einnahme') }}</option>
                                <option value="expense">{{ tx('club_memberships.finance.type.expense', 'Ausgabe') }}</option>
                            </select>
                            <p v-if="financeEntryForm.errors.type" class="mt-1 text-xs text-error">{{ financeEntryForm.errors.type }}</p>
                        </div>
                        <div>
                            <label class="text-xs font-semibold uppercase text-secondary">{{ tx('club_memberships.workspace.account', 'Konto') }}</label>
                            <select v-model="financeEntryForm.account" class="mt-1 w-full rounded-lg border border-border bg-inputBg px-3 py-2 text-sm text-primary">
                                <option value="cash">{{ tx('club_memberships.finance.account.cash', 'Bar') }}</option>
                                <option value="bank">{{ tx('club_memberships.finance.account.bank', 'Bank') }}</option>
                            </select>
                            <p v-if="financeEntryForm.errors.account" class="mt-1 text-xs text-error">{{ financeEntryForm.errors.account }}</p>
                        </div>
                        <div>
                            <label class="text-xs font-semibold uppercase text-secondary">{{ tx('auto.Titel', 'Titel') }}</label>
                            <input
                                v-model="financeEntryForm.title"
                                class="mt-1 w-full rounded-lg border border-border bg-inputBg px-3 py-2 text-sm text-primary"
                                :placeholder="tx('club_memberships.workspace.category_placeholder', 'z. B. Hallenmiete')"
                                required
                            >
                            <p v-if="financeEntryForm.errors.title" class="mt-1 text-xs text-error">{{ financeEntryForm.errors.title }}</p>
                        </div>
                        <div>
                            <label class="text-xs font-semibold uppercase text-secondary">{{ tx('club_memberships.workspace.category', 'Kategorie') }}</label>
                            <SearchableSelect
                                v-model="financeEntryForm.category"
                                :options="financeCategoryOptions"
                                :placeholder="tx('club_memberships.workspace.category_placeholder', 'Kategorie suchen oder auswählen')"
                                :empty-text="tx('club_memberships.workspace.category_empty', 'Keine Kategorie gefunden.')"
                                :allow-custom="false"
                                class="mt-1"
                            />
                            <p v-if="financeEntryForm.errors.category" class="mt-1 text-xs text-error">{{ financeEntryForm.errors.category }}</p>
                        </div>
                        <div>
                            <label class="text-xs font-semibold uppercase text-secondary">{{ tx('club_memberships.workspace.amount_eur', 'Betrag EUR') }}</label>
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
                            <label class="text-xs font-semibold uppercase text-secondary">{{ tx('auto.Datum', 'Datum') }}</label>
                            <input
                                v-model="financeEntryForm.booked_on"
                                type="date"
                                class="mt-1 w-full rounded-lg border border-border bg-inputBg px-3 py-2 text-sm text-primary"
                            >
                        </div>
                        <div class="sm:col-span-2">
                            <label class="text-xs font-semibold uppercase text-secondary">{{ tx('club_memberships.workspace.reference', 'Referenz') }}</label>
                            <input
                                v-model="financeEntryForm.reference"
                                class="mt-1 w-full rounded-lg border border-border bg-inputBg px-3 py-2 text-sm text-primary"
                                :placeholder="tx('club_memberships.workspace.reference_placeholder', 'Belegnummer, Kontoauszug, Notiz')"
                            >
                        </div>
                        <div class="sm:col-span-2">
                            <label class="text-xs font-semibold uppercase text-secondary">{{ tx('club_memberships.workspace.description', 'Beschreibung') }}</label>
                            <textarea
                                v-model="financeEntryForm.description"
                                rows="3"
                                class="mt-1 w-full rounded-lg border border-border bg-inputBg px-3 py-2 text-sm text-primary"
                                :placeholder="tx('club_memberships.workspace.optional', 'Optional')"
                            ></textarea>
                        </div>
                    </div>

                    <div class="flex justify-end gap-2">
                        <button type="button" class="rounded-lg border border-border px-4 py-2 text-sm font-semibold text-primary" @click="showFinanceEntryModal = false">
                            {{ tx('auto.Abbrechen', 'Abbrechen') }}
                        </button>
                        <button class="rounded-lg bg-buttonPrimary px-4 py-2 text-sm font-semibold text-buttonTextPrimary" :disabled="financeEntryForm.processing">
                            {{ tx('auto.Speichern', 'Speichern') }}
                        </button>
                    </div>
                </form>
            </div>
        </Modal>

        <Modal :show="showBankImportModal" max-width="2xl" @close="closeBankImportModal">
            <div class="p-2">
                <h2 class="text-xl font-bold text-primary">{{ tx('club_memberships.workspace.bank_import_title', 'Bankumsätze importieren') }}</h2>
                <p class="mt-1 text-sm text-secondary">
                    {{ tx('club_memberships.workspace.bank_import_intro', 'Lade eine CSV aus dem Online-Banking hoch. Erkannt werden typische Spalten wie Datum, Betrag, Auftraggeber, IBAN und Verwendungszweck.') }}
                </p>

                <form class="mt-5 space-y-4" @submit.prevent="bankImportPreview ? importBankTransactions() : previewBankTransactions()">
                    <div>
                        <label class="text-xs font-semibold uppercase text-secondary">{{ tx('club_memberships.workspace.csv_file', 'CSV-Datei') }}</label>
                        <input
                            type="file"
                            accept=".csv,.txt"
                            required
                            class="mt-1 block w-full rounded-lg border border-border bg-inputBg px-3 py-2 text-sm text-primary"
                            @input="resetBankImportPreview($event.target.files[0])"
                        >
                        <p v-if="bankImportForm.errors.file" class="mt-2 text-sm text-error">{{ bankImportForm.errors.file }}</p>
                    </div>

                    <p class="rounded-lg border border-border bg-bg p-3 text-xs text-secondary">
                        {{ tx('club_memberships.workspace.bank_import_hint', 'Die Vorschau schreibt noch nichts. Sichere Treffer mit Rechnungsnummer und Betrag werden erst nach deiner Bestätigung automatisch als bezahlt markiert.') }}
                    </p>

                    <div v-if="bankImportPreview" class="rounded-xl border border-border bg-bg p-4" aria-live="polite">
                        <p class="font-semibold text-primary">
                            {{ tx('club_memberships.workspace.bank_preview_summary', '{importable} von {total} Zeilen können importiert werden; {invalid} fehlerhaft, {duplicates} bereits vorhanden.', {
                                importable: bankImportPreview.stats?.importable || 0,
                                total: bankImportPreview.stats?.total || 0,
                                invalid: bankImportPreview.stats?.invalid || 0,
                                duplicates: bankImportPreview.stats?.duplicates || 0,
                            }) }}
                        </p>
                        <div v-if="bankImportPreview.rows?.length" class="mt-3 max-h-56 overflow-auto">
                            <table class="min-w-full text-left text-xs">
                                <thead class="uppercase text-secondary">
                                    <tr><th class="py-2 pr-3">{{ tx('auto.Zeile', 'Zeile') }}</th><th class="py-2 pr-3">{{ tx('auto.Datum', 'Datum') }}</th><th class="py-2 pr-3">{{ tx('auto.Betrag', 'Betrag') }}</th><th class="py-2 pr-3">{{ tx('auto.Zuordnung', 'Zuordnung') }}</th></tr>
                                </thead>
                                <tbody class="divide-y divide-border">
                                    <tr v-for="row in bankImportPreview.rows" :key="`${row.row}-${row.amount}-${row.purpose}`">
                                        <td class="py-2 pr-3 text-secondary">{{ row.row }}</td>
                                        <td class="py-2 pr-3 text-secondary">{{ formatDate(row.booking_date) }}</td>
                                        <td class="py-2 pr-3 text-primary">{{ formatMoney(row.amount) }}</td>
                                        <td class="py-2 pr-3 text-primary">
                                            <span class="block font-semibold">{{ row.invoice?.number || row.status }}</span>
                                            <span class="block text-secondary">{{ row.reason }}</span>
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                        <ul v-if="bankImportPreview.errors?.length" class="mt-3 space-y-1 text-xs text-error">
                            <li v-for="error in bankImportPreview.errors" :key="`${error.row}-${error.reason}`">
                                {{ tx('club_memberships.workspace.bank_preview_error_row', 'Zeile {row}: {reason}', { row: error.row, reason: error.reason }) }}
                            </li>
                        </ul>
                    </div>

                    <div class="flex flex-wrap justify-end gap-2">
                        <button type="button" class="rounded-lg border border-border px-4 py-2 text-sm font-semibold text-primary" :disabled="bankImportPreviewLoading || bankImportSubmitting" @click="closeBankImportModal">
                            {{ tx('auto.Abbrechen', 'Abbrechen') }}
                        </button>
                        <button class="rounded-lg bg-buttonPrimary px-4 py-2 text-sm font-semibold text-buttonTextPrimary disabled:cursor-not-allowed disabled:opacity-60" :disabled="bankImportPreviewLoading || bankImportSubmitting || (Boolean(bankImportPreview) && !bankImportPreview.can_import)">
                            {{ bankImportPreviewLoading
                                ? tx('club_memberships.workspace.preview_loading', 'Datei wird geprüft …')
                                : bankImportSubmitting
                                    ? tx('auto.Wird gespeichert …', 'Wird gespeichert …')
                                    : bankImportPreview
                                        ? tx('club_memberships.workspace.start_import', 'Import jetzt starten')
                                        : tx('club_memberships.workspace.preview_action', 'Vorschau prüfen') }}
                        </button>
                    </div>
                </form>
            </div>
        </Modal>
    </div>
</template>
