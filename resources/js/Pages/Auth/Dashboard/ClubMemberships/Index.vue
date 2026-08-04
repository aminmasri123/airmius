<script setup>
import AppLayout from '@/Components/Auth/Layouts/AppLayout.vue'
import ClubWorkspaceNav from '@/Components/Auth/ClubWorkspaceNav.vue'
import Modal from '@/Components/Modal.vue'
import SearchableSelect from '@/Components/SearchableSelect.vue'
import { Head, Link, router, useForm, usePage } from '@inertiajs/vue3'
import { computed, ref } from 'vue'
import { confirmDialog } from '@/services/dialogService'
import { useI18n } from 'vue-i18n'

defineOptions({ layout: AppLayout })

const props = defineProps({
    clubs: { type: Array, default: () => [] },
    membershipStatuses: { type: Array, default: () => ['active', 'non_member', 'pending', 'former'] },
    contributionIntervals: { type: Array, default: () => ['none', 'monthly', 'quarterly', 'yearly', 'once'] },
    contributionRuleTypes: { type: Array, default: () => [{ value: 'standard', label: 'Standardbeitrag' }] },
    contributionDiscountOperators: { type: Array, default: () => [{ value: 'percent', label: 'Prozentualer Rabatt' }] },
    invoiceStatusOptions: { type: Array, default: () => [
        { value: 'open', label: 'Offen' },
        { value: 'paid', label: 'Bezahlt' },
        { value: 'overdue', label: 'Überfällig' },
        { value: 'cancelled', label: 'Storniert' },
    ] },
    clubRoles: { type: Array, default: () => [{ value: 'member', label: 'Mitglied' }] },
    teamRoles: { type: Array, default: () => ['Coach', 'Captain', 'Player'] },
})

const page = usePage()
const { t, locale } = useI18n()
const tx = (key, fallback = key, values = {}) => {
    const translated = t(key, values)
    return translated === key ? fallback : translated
}
const selectedClubId = ref(props.clubs[0]?.id || null)
const activeTab = ref('members')
const rulesWizardStep = ref(0)
const membershipTypeMode = ref('choose')
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
    role: 'member',
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
    invitation_expires_at: '',
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
    application_fields: {},
})
const editingMembershipTypeId = ref(null)
const contributionRuleForm = useForm({
    club_membership_type_id: '',
    name: '',
    valid_from: new Date().toISOString().slice(0, 10),
    valid_until: '',
    billing_interval: 'monthly',
    amount: '',
    age_min: '',
    age_max: '',
    factor_key: 'standard',
    factor_operator: '',
    factor_value: '',
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
const applicationFieldDefinitions = computed(() => selectedClub.value?.membership_application_fields || [])
const capabilities = computed(() => selectedClub.value?.capabilities || {})
const canOpenEmailMembers = computed(() => capabilities.value.external_members !== false || capabilities.value.member_invitations !== false)
const members = computed(() => selectedClub.value?.members || [])
const externalMembers = computed(() => selectedClub.value?.external_members || [])
const invoices = computed(() => selectedClub.value?.invoices || [])
const payments = computed(() => selectedClub.value?.payments || [])
const financeEntries = computed(() => selectedClub.value?.finance_entries || [])
const bankTransactions = computed(() => selectedClub.value?.bank_transactions || [])
const auditLogs = computed(() => selectedClub.value?.audit_logs || [])

const activeMembersCount = computed(() => members.value.filter((member) => formFor(member).membership_status === 'active').length)
const openInvoices = computed(() => invoices.value.filter((invoice) => ['open', 'overdue'].includes(invoice.status)))
const openInvoiceTotal = computed(() => openInvoices.value.reduce((sum, invoice) => sum + Number(invoice.amount || 0), 0))
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
    { key: 'members', label: tx('auto.Mitglieder', 'Mitglieder'), count: members.value.length + externalMembers.value.length, icon: 'las la-users' },
    { key: 'requests', label: tx('auto.Anfragen', 'Anfragen'), count: pendingRequests.value.length + clubRequests.value.length, icon: 'las la-user-plus' },
    { key: 'rules', label: tx('auto.Beitragsregeln', 'Beitragsregeln'), count: contributionRules.value.length, icon: 'las la-sliders-h' },
    { key: 'invoices', label: tx('auto.Rechnungen', 'Rechnungen'), count: openInvoices.value.length, icon: 'las la-file-invoice' },
    { key: 'payments', label: tx('auto.Finanzen', 'Finanzen'), count: payments.value.length + financeEntries.value.length, icon: 'las la-university' },
    { key: 'audit', label: tx('club_memberships.workspace.audit_log', 'Audit'), count: auditLogs.value.length, icon: 'las la-history' },
    { key: 'exports', label: 'SEPA & DATEV', count: sepaReadyMembersCount.value, icon: 'las la-file-export' },
])

const rulesWizardSteps = computed(() => [
    {
        key: 'types',
        label: tx('auto.Mitgliedschaftstyp', 'Mitgliedschaftstypen'),
        hint: tx('club_memberships.workspace.wizard_types_hint', 'Lege fest, welche Arten von Mitgliedschaften Interessenten auswählen können.'),
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
        key: 'application',
        label: tx('club_memberships.workspace.application_fields', 'Antrag & Felder'),
        hint: tx('club_memberships.workspace.wizard_application_hint', 'Bestimme, welche Informationen Interessenten im Antrag angeben müssen.'),
    },
    {
        key: 'payment',
        label: tx('club_memberships.workspace.allowed_payment_methods', 'Zahlungsarten'),
        hint: tx('club_memberships.workspace.wizard_payment_hint', 'Lege fest, wie Beiträge bezahlt werden können.'),
    },
    {
        key: 'documents',
        label: tx('club_memberships.workspace.documents_confirmations', 'Dokumente & Abschluss'),
        hint: tx('club_memberships.workspace.wizard_documents_hint', 'Verknüpfe wichtige Dokumente und veröffentliche anschließend deine Regeln.'),
    },
])

const rulesWizardNext = () => {
    if (rulesWizardStep.value < rulesWizardSteps.value.length - 1) rulesWizardStep.value += 1
}

const rulesWizardBack = () => {
    if (rulesWizardStep.value > 0) rulesWizardStep.value -= 1
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

const localeCode = computed(() => ({ ar: 'ar-EG', fr: 'fr-FR', en: 'en-US', de: 'de-DE' })[locale.value] || 'de-DE')
const formatMoney = (value) => new Intl.NumberFormat(localeCode.value, {
    style: 'currency',
    currency: 'EUR',
}).format(Number(value || 0))

const formatDate = (value) => {
    if (!value) return '-'
    return new Intl.DateTimeFormat(localeCode.value, { day: '2-digit', month: '2-digit', year: 'numeric' }).format(new Date(value))
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
    const options = {
        preserveScroll: true,
        onSuccess: () => {
            editingMembershipTypeId.value = null
            membershipTypeForm.reset('name', 'slug', 'description')
        },
    }

    if (editingMembershipTypeId.value) {
        membershipTypeForm.put(route('auth.club-memberships.types.update', [selectedClub.value.id, editingMembershipTypeId.value]), options)
    } else {
        membershipTypeForm.post(route('auth.club-memberships.types.store', selectedClub.value.id), options)
    }
}

const storeContributionRule = () => {
    const options = {
        preserveScroll: true,
        onSuccess: () => {
            editingContributionRuleId.value = null
            contributionRuleForm.reset('name', 'amount', 'valid_until', 'age_min', 'age_max', 'factor_operator', 'factor_value', 'notes')
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
    contributionRuleForm.name = rule.name || ''
    contributionRuleForm.valid_from = rule.valid_from || new Date().toISOString().slice(0, 10)
    contributionRuleForm.valid_until = rule.valid_until || ''
    contributionRuleForm.billing_interval = rule.billing_interval || 'monthly'
    contributionRuleForm.amount = rule.amount ?? ''
    contributionRuleForm.age_min = rule.age_min ?? ''
    contributionRuleForm.age_max = rule.age_max ?? ''
    contributionRuleForm.factor_key = rule.factor_key || 'standard'
    contributionRuleForm.factor_operator = rule.factor_operator || ''
    contributionRuleForm.factor_value = rule.factor_value ?? ''
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
    contributionRuleForm.factor_key = 'standard'
    contributionRuleForm.is_active = true
}

const contributionRuleTypeLabel = (value) => props.contributionRuleTypes.find((type) => type.value === value)?.label || value || 'Standardbeitrag'
const contributionDiscountOperatorLabel = (value) => props.contributionDiscountOperators.find((operator) => operator.value === value)?.label || value

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
                                <td class="py-3 pr-4 text-secondary">{{ formatDate(entry.created_at) }}</td>
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
                            :disabled="capabilities.sepa_export === false"
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
                    <article v-for="member in externalMembers" :key="member.id" class="rounded-lg border border-border bg-bg p-4">
                        <div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
                            <div>
                                <h3 class="font-semibold text-primary">{{ member.name || member.email }}</h3>
                                <p class="text-sm text-secondary">{{ member.email }}</p>
                                <p class="mt-1 text-xs text-secondary">
                                    {{ tx('club_memberships.workspace.end', 'Ende') }}: {{ formatDate(member.membership_ends_on) }}
                                </p>
                                <p class="mt-2 text-xs text-secondary">
                                    {{ tx('club_memberships.workspace.member_number', 'Mitgliedsnummer') }}: {{ member.member_number || '-' }} · {{ tx('club_memberships.workspace.license_number', 'Lizenznummer') }}: {{ member.athlete_license_number || '-' }}
                                </p>
                                <p class="mt-1 text-xs text-secondary">
                                    {{ tx('club_memberships.workspace.invitation', 'Einladung') }}: {{ member.invitation_status === 'pending' ? tx('club_memberships.workspace.sent', 'gesendet') : member.invitation_status === 'linked' ? tx('club_memberships.workspace.linked', 'verknüpft') : tx('club_memberships.workspace.not_sent', 'nicht gesendet') }}
                                </p>
                            </div>

                            <button
                                v-if="member.invitation_status !== 'linked'"
                                type="button"
                                class="rounded-lg bg-buttonPrimary px-3 py-2 text-sm font-semibold text-buttonTextPrimary"
                                @click="inviteExternalMember(member)"
                            >
                                {{ tx('club_memberships.workspace.invite_link', 'Einladung/Verknüpfung') }}
                            </button>
                        </div>
                    </article>
                </div>
            </section>

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

                            <div class="flex gap-2">
                                <button type="button" class="rounded-lg bg-buttonPrimary px-4 py-2 text-sm font-semibold text-buttonTextPrimary" @click="approveClubRequest(request)">
                                    {{ tx('club_memberships.workspace.accept', 'Annehmen') }}
                                </button>
                                <button type="button" class="rounded-lg border border-border px-4 py-2 text-sm font-semibold text-primary" @click="declineClubRequest(request)">
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
                            <span class="shrink-0 rounded-full bg-buttonPrimary/15 px-3 py-1.5 text-xs font-bold text-air-blue">
                                {{ tx('club_memberships.workspace.wizard_step', 'Schritt') }} {{ rulesWizardStep + 1 }} / {{ rulesWizardSteps.length }}
                            </span>
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
                    :class="rulesWizardStep === 1 ? 'xl:grid-cols-[minmax(0,1fr)_24rem]' : 'xl:grid-cols-1'"
                >
                <div class="space-y-6">
                    <section v-if="rulesWizardStep >= 2" class="surface-card p-5">
                        <h2 class="text-lg font-semibold text-primary">{{ tx('club_memberships.workspace.online_requests', 'Online-Anfragen') }}</h2>
                        <form class="mt-4" @submit.prevent="saveMembershipSettings">
                            <div class="grid gap-3 md:grid-cols-3">
                            <label v-show="rulesWizardStep === 2" class="flex items-start gap-3 rounded-lg border border-border bg-bg p-3 text-sm text-primary">
                                <input v-model="membershipSettingsFor(selectedClub).membership_requests_enabled" type="checkbox" class="mt-1 rounded border-border bg-inputBg">
                                <span>
                                    <span class="block font-semibold">{{ tx('club_memberships.workspace.member_requests_enabled', 'Mitgliedsanfragen erlauben') }}</span>
                                    <span class="block text-secondary">{{ tx('club_memberships.workspace.member_requests_hint', 'Interessenten sehen die Beitragstypen und können eine Anfrage stellen.') }}</span>
                                </span>
                            </label>
                            <label v-show="rulesWizardStep === 2" class="flex items-start gap-3 rounded-lg border border-border bg-bg p-3 text-sm text-primary">
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
                            <div v-show="rulesWizardStep === 3" class="rounded-lg border border-border bg-bg p-3 md:col-span-3">
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
                                    <button type="button" class="rounded-lg border border-border px-3 py-2 text-xs font-semibold text-primary hover:bg-inputBg" @click="addMembershipDocument">
                                        {{ tx('club_memberships.workspace.add_document', 'Dokument hinzufügen') }}
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
                                                <span class="font-semibold text-primary">{{ tx('club_memberships.workspace.type', 'Typ') }}</span>
                                                <select v-model="document.type" class="mt-1 w-full rounded-lg border-border bg-inputBg text-sm text-primary">
                                                    <option v-for="type in selectedClub.membership_application_document_types" :key="type.value" :value="type.value">{{ type.label }}</option>
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
                                                <input v-model="document.url" type="url" class="mt-1 w-full rounded-lg border-border bg-inputBg text-sm text-primary" placeholder="https://...">
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
                                    <p v-if="!membershipSettingsFor(selectedClub).membership_application_documents.length" class="rounded-lg border border-dashed border-border p-4 text-sm text-secondary">
                                        {{ tx('club_memberships.workspace.no_documents', 'Noch keine Dokumente verknüpft.') }}
                                    </p>
                                </div>
                            </div>
                            </div>
                        </form>
                    </section>

                    <section v-if="rulesWizardStep === 1" class="surface-card p-5">
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
                        </div>
                        <form v-else class="mt-4 grid gap-3" @submit.prevent="storeMembershipType">
                            <input v-model="membershipTypeForm.name" class="rounded-lg border-border bg-inputBg text-sm text-primary" :placeholder="tx('auto.z. B. Jugendmitglied', 'z. B. Jugendmitglied')" required>
                            <input v-model="membershipTypeForm.slug" class="rounded-lg border-border bg-inputBg text-sm text-primary" :placeholder="tx('auto.slug optional', 'slug optional')">
                            <textarea v-model="membershipTypeForm.description" rows="3" class="rounded-lg border-border bg-inputBg text-sm text-primary" :placeholder="tx('Beschreibung', 'Beschreibung')"></textarea>
                            <label class="flex items-center gap-2 text-sm text-primary"><input v-model="membershipTypeForm.is_public" type="checkbox" class="rounded border-border bg-inputBg"> {{ tx('auto.Öffentlich sichtbar', 'Öffentlich sichtbar') }}</label>
                            <div v-if="applicationFieldDefinitions.length" class="rounded-xl border border-border bg-bg p-3">
                                <div class="mb-2">
                                    <p class="text-sm font-semibold text-primary">{{ tx('club_memberships.workspace.type_fields_title', 'Antragsfelder für diesen Typ') }}</p>
                                    <p class="mt-1 text-xs text-secondary">{{ tx('club_memberships.workspace.type_fields_hint', 'Der Vereinsstandard wird übernommen und kann hier angepasst werden.') }}</p>
                                </div>
                                <div class="grid gap-2 sm:grid-cols-2">
                                    <label v-for="field in applicationFieldDefinitions" :key="field.key" class="rounded-lg border border-border bg-card p-2 text-xs text-primary">
                                        <span class="font-semibold">{{ field.label }}</span>
                                        <select v-model="membershipTypeForm.application_fields[field.key]" class="mt-1 w-full rounded-lg border-border bg-inputBg text-xs text-primary">
                                            <option value="required">{{ tx('club_memberships.workspace.required', 'Pflichtfeld') }}</option>
                                            <option value="optional">{{ tx('club_memberships.workspace.optional', 'Optional') }}</option>
                                            <option value="off">{{ tx('club_memberships.workspace.hidden', 'Ausgeblendet') }}</option>
                                        </select>
                                    </label>
                                </div>
                            </div>
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

                    <section v-if="rulesWizardStep === 1" class="surface-card p-5">
                        <div class="flex items-center justify-between gap-3">
                            <h2 class="text-lg font-semibold text-primary">{{ editingContributionRuleId ? tx('club_memberships.workspace.edit_rule', 'Beitragsregel bearbeiten') : tx('auto.Neue Beitragsregel', 'Neue Beitragsregel') }}</h2>
                            <button v-if="editingContributionRuleId" type="button" class="text-xs font-semibold text-secondary hover:text-primary" @click="cancelContributionRuleEdit">
                                {{ tx('club_memberships.workspace.cancel_edit', 'Abbrechen') }}
                            </button>
                        </div>
                        <form class="mt-4 grid gap-3" @submit.prevent="storeContributionRule">
                            <select v-model="contributionRuleForm.club_membership_type_id" class="rounded-lg border-border bg-inputBg text-sm text-primary">
                                <option value="">{{ tx('auto.Alle Typen', 'Alle Typen') }}</option>
                                <option v-for="type in membershipTypes" :key="type.id" :value="type.id">{{ type.name }}</option>
	                            </select>
                            <input v-model="contributionRuleForm.name" class="rounded-lg border-border bg-inputBg text-sm text-primary" :placeholder="tx('auto.Regelname', 'Regelname')" required>
                            <input v-model="contributionRuleForm.amount" type="number" min="0" step="0.01" class="rounded-lg border-border bg-inputBg text-sm text-primary" :placeholder="tx('auto.Beitrag EUR', 'Beitrag EUR')" required>
	                            <select v-model="contributionRuleForm.factor_key" class="rounded-lg border-border bg-inputBg text-sm text-primary">
	                                <option v-for="type in contributionRuleTypes" :key="type.value" :value="type.value">{{ type.label }}</option>
	                            </select>
	                            <select v-model="contributionRuleForm.billing_interval" class="rounded-lg border-border bg-inputBg text-sm text-primary">
	                                <option v-for="interval in contributionIntervals" :key="interval" :value="interval">{{ intervalLabel(interval) }}</option>
	                            </select>
	                            <div v-if="contributionRuleForm.factor_key === 'discount'" class="grid grid-cols-2 gap-2">
	                                <select v-model="contributionRuleForm.factor_operator" class="rounded-lg border-border bg-inputBg text-sm text-primary">
                                    <option value="">{{ tx('club_memberships.workspace.discount_type', 'Rabatt-Typ') }}</option>
	                                    <option v-for="operator in contributionDiscountOperators" :key="operator.value" :value="operator.value">{{ operator.label }}</option>
	                                </select>
                                <input v-model="contributionRuleForm.factor_value" type="number" min="0" step="0.01" class="rounded-lg border-border bg-inputBg text-sm text-primary" :placeholder="tx('club_memberships.workspace.discount_value', 'Rabattwert')">
	                            </div>
	                            <div class="grid grid-cols-2 gap-2">
	                                <input v-model="contributionRuleForm.valid_from" type="date" class="rounded-lg border-border bg-inputBg text-sm text-primary" required>
	                                <input v-model="contributionRuleForm.valid_until" type="date" class="rounded-lg border-border bg-inputBg text-sm text-primary">
                            </div>
                            <div class="grid grid-cols-2 gap-2">
                                <input v-model="contributionRuleForm.age_min" type="number" min="0" class="rounded-lg border-border bg-inputBg text-sm text-primary" :placeholder="tx('auto.Alter von', 'Alter von')">
                                <input v-model="contributionRuleForm.age_max" type="number" min="0" class="rounded-lg border-border bg-inputBg text-sm text-primary" :placeholder="tx('auto.Alter bis', 'Alter bis')">
                            </div>
                            <textarea v-model="contributionRuleForm.notes" rows="2" class="rounded-lg border-border bg-inputBg text-sm text-primary" :placeholder="tx('auto.Notiz', 'Notiz')"></textarea>
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
                            <select v-model="memberStatusFilter" class="rounded-lg border border-border bg-inputBg px-3 py-2 text-sm text-primary">
                                <option value="all">{{ tx('auto.Alle Status', 'Alle Status') }}</option>
                                <option v-for="status in membershipStatuses" :key="status" :value="status">{{ statusLabel(status) }}</option>
                            </select>
                            <select v-model="memberEndFilter" class="rounded-lg border border-border bg-inputBg px-3 py-2 text-sm text-primary">
                                <option value="all">{{ tx('auto.Alle Laufzeiten', 'Alle Laufzeiten') }}</option>
                                <option value="ending_30">{{ tx('auto.Endet in 30 Tagen', 'Endet in 30 Tagen') }}</option>
                                <option value="ending_60">{{ tx('auto.Endet in 60 Tagen', 'Endet in 60 Tagen') }}</option>
                                <option value="expired">{{ tx('auto.Bereits abgelaufen', 'Bereits abgelaufen') }}</option>
                                <option value="no_end">{{ tx('auto.Ohne Enddatum', 'Ohne Enddatum') }}</option>
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
                            <div>
                                <label class="text-xs font-semibold uppercase text-secondary">{{ tx('club_memberships.workspace.roles', 'Rollen') }}</label>
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
                            </div>

                            <div>
                                <label class="text-xs font-semibold uppercase text-secondary">{{ tx('auto.Mitgliedschaft', 'Mitgliedschaft') }}</label>
                                <select v-model="formFor(member).membership_status" class="mt-1 w-full rounded-lg border border-border bg-inputBg px-3 py-2 text-sm text-primary">
                                    <option v-for="status in membershipStatuses" :key="status" :value="status">{{ statusLabel(status) }}</option>
                                </select>
                            </div>

                            <div>
                                <label class="text-xs font-semibold uppercase text-secondary">{{ tx('auto.Mitgliedschaftstyp', 'Mitgliedschaftstyp') }}</label>
                                <select v-model="formFor(member).club_membership_type_id" class="mt-1 w-full rounded-lg border border-border bg-inputBg px-3 py-2 text-sm text-primary">
                                    <option value="">{{ tx('auto.Kein Typ', 'Kein Typ') }}</option>
                                    <option v-for="type in membershipTypes" :key="type.id" :value="type.id">{{ type.name }}</option>
                                </select>
                            </div>

                            <div>
                                <label class="text-xs font-semibold uppercase text-secondary">{{ tx('auto.Mitgliedsnummer', 'Mitgliedsnummer') }}</label>
                                <div class="mt-1 flex gap-2">
                                    <input v-model="formFor(member).member_number" class="min-w-0 flex-1 rounded-lg border border-border bg-inputBg px-3 py-2 text-sm text-primary">
                                    <button type="button" class="rounded-lg border border-border px-3 py-2 text-xs font-semibold text-primary hover:bg-inputBg" @click="generateMemberNumber(member)">
                                        Generieren
                                    </button>
                                </div>
                            </div>

                            <div>
                                <label class="text-xs font-semibold uppercase text-secondary">{{ tx('club_memberships.workspace.license', 'Lizenznummer') }}</label>
                                <input
                                    v-model="formFor(member).athlete_license_number"
                                    class="mt-1 w-full rounded-lg border border-border bg-inputBg px-3 py-2 text-sm text-primary"
                                    :placeholder="tx('auto.z. B. Spielerpass- oder Verbandsnummer', 'z. B. Spielerpass- oder Verbandsnummer')"
                                >
                            </div>

                            <div>
                                <label class="text-xs font-semibold uppercase text-secondary">{{ tx('club_memberships.workspace.contribution', 'Beitrag') }}</label>
                                <input v-model="formFor(member).contribution_amount" type="number" min="0" step="0.01" class="mt-1 w-full rounded-lg border border-border bg-inputBg px-3 py-2 text-sm text-primary">
                            </div>

                            <div>
                                <label class="text-xs font-semibold uppercase text-secondary">{{ tx('club_memberships.workspace.interval_label', 'Intervall') }}</label>
                                <select v-model="formFor(member).contribution_interval" class="mt-1 w-full rounded-lg border border-border bg-inputBg px-3 py-2 text-sm text-primary">
                                    <option v-for="interval in contributionIntervals" :key="interval" :value="interval">{{ intervalLabel(interval) }}</option>
                                </select>
                            </div>

                            <div>
                                <label class="text-xs font-semibold uppercase text-secondary">{{ tx('club_memberships.workspace.payment_method_label', 'Zahlmethode') }}</label>
                                <select v-model="formFor(member).payment_method" class="mt-1 w-full rounded-lg border border-border bg-inputBg px-3 py-2 text-sm text-primary">
                                    <option value="">{{ tx('auto.Offen', 'Offen') }}</option>
                                    <option v-for="method in selectedClub.membership_payment_method_options" :key="method.value" :value="method.value">{{ method.label }}</option>
                                </select>
                            </div>

                            <div>
                                <label class="text-xs font-semibold uppercase text-secondary">{{ tx('club_memberships.workspace.next_invoice', 'Nächste automatische Rechnung') }}</label>
                                <input v-model="formFor(member).contribution_next_invoice_on" type="date" class="mt-1 w-full rounded-lg border border-border bg-inputBg px-3 py-2 text-sm text-primary">
                                <p class="mt-1 text-xs text-secondary">{{ tx('club_memberships.workspace.automation_hint', 'Automatik wird ab Pro/Elite ausgeführt.') }}</p>
                            </div>

                            <div>
                                <label class="text-xs font-semibold uppercase text-secondary">{{ tx('club_memberships.workspace.sepa_iban', 'SEPA IBAN') }}</label>
                                <input v-model="formFor(member).sepa_iban" class="mt-1 w-full rounded-lg border border-border bg-inputBg px-3 py-2 text-sm text-primary" placeholder="DE...">
                            </div>

                            <div>
                                <label class="text-xs font-semibold uppercase text-secondary">{{ tx('club_memberships.workspace.sepa_bic', 'SEPA BIC optional') }}</label>
                                <input v-model="formFor(member).sepa_bic" class="mt-1 w-full rounded-lg border border-border bg-inputBg px-3 py-2 text-sm text-primary" placeholder="GENODE...">
                            </div>

                            <div>
                                <label class="text-xs font-semibold uppercase text-secondary">{{ tx('club_memberships.workspace.mandate_reference', 'Mandatsreferenz') }}</label>
                                <input v-model="formFor(member).sepa_mandate_reference" class="mt-1 w-full rounded-lg border border-border bg-inputBg px-3 py-2 text-sm text-primary" placeholder="MANDAT-1001">
                            </div>

                            <div>
                                <label class="text-xs font-semibold uppercase text-secondary">{{ tx('club_memberships.workspace.mandate_date', 'Mandatsdatum') }}</label>
                                <input v-model="formFor(member).sepa_mandate_signed_on" type="date" class="mt-1 w-full rounded-lg border border-border bg-inputBg px-3 py-2 text-sm text-primary">
                            </div>

                            <label class="flex items-center gap-2 self-end rounded-lg border border-border bg-inputBg px-3 py-2 text-sm text-primary">
                                <input v-model="formFor(member).sepa_mandate_active" type="checkbox" class="rounded border-border bg-bg">
                                {{ tx('auto.SEPA-Mandat aktiv', 'SEPA-Mandat aktiv') }}
                            </label>

                            <div>
                                <label class="text-xs font-semibold uppercase text-secondary">{{ tx('club_memberships.workspace.joined', 'Eintritt') }}</label>
                                <input v-model="formFor(member).joined_on" type="date" class="mt-1 w-full rounded-lg border border-border bg-inputBg px-3 py-2 text-sm text-primary">
                            </div>

                            <div>
                                <label class="text-xs font-semibold uppercase text-secondary">{{ tx('auto.Ende der Mitgliedschaft', 'Ende der Mitgliedschaft') }}</label>
                                <input v-model="formFor(member).membership_ends_on" type="date" class="mt-1 w-full rounded-lg border border-border bg-inputBg px-3 py-2 text-sm text-primary">
                            </div>

                            <div class="md:col-span-2">
                                <label class="text-xs font-semibold uppercase text-secondary">{{ tx('auto.Notiz', 'Notiz') }}</label>
                                <textarea v-model="formFor(member).membership_notes" rows="3" class="mt-1 w-full rounded-lg border border-border bg-inputBg px-3 py-2 text-sm text-primary"></textarea>
                            </div>

                            <div class="md:col-span-3">
                                <button class="rounded-lg bg-buttonPrimary px-4 py-2 text-sm font-semibold text-buttonTextPrimary">
                                    {{ tx('auto.Speichern', 'Speichern') }}
                                </button>
                            </div>
                        </form>

                        <form v-if="invoiceMemberId === member.id" class="mt-4 grid gap-3 rounded-lg border border-border bg-bg p-4 md:grid-cols-[1fr_140px_170px_auto]" @submit.prevent="createInvoice(member)">
                            <input v-model="invoiceForm.title" class="rounded-lg border border-border bg-inputBg px-3 py-2 text-sm text-primary" :placeholder="tx('auto.Titel', 'Titel')">
                            <input v-model="invoiceForm.amount" type="number" min="0.01" step="0.01" class="rounded-lg border border-border bg-inputBg px-3 py-2 text-sm text-primary" :placeholder="tx('auto.Betrag', 'Betrag')">
                            <input v-model="invoiceForm.due_date" type="date" class="rounded-lg border border-border bg-inputBg px-3 py-2 text-sm text-primary">
                            <button class="rounded-lg bg-buttonPrimary px-4 py-2 text-sm font-semibold text-buttonTextPrimary">
                                {{ tx('auto.Erstellen', 'Erstellen') }}
                            </button>
                            <textarea v-model="invoiceForm.description" rows="2" class="rounded-lg border border-border bg-inputBg px-3 py-2 text-sm text-primary md:col-span-4" :placeholder="tx('auto.Beschreibung optional', 'Beschreibung optional')"></textarea>
                        </form>
                    </article>
                    <p v-if="!filteredMembers.length" class="p-6 text-sm text-secondary">
                        {{ tx('club_memberships.workspace.search_empty', 'Keine Mitglieder passen zu deiner Suche.') }}
                    </p>
                </div>
            </section>

            <section v-if="activeTab === 'invoices'" class="surface-card p-5">
                <h2 class="text-lg font-semibold text-primary">{{ tx('auto.Rechnungen', 'Rechnungen') }}</h2>
                <div class="mt-4 flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                    <p class="text-sm text-secondary">{{ filteredInvoices.length }} von {{ invoices.length }} {{ tx('auto.Rechnungen', 'Rechnungen') }} sichtbar.</p>
                    <select v-model="invoiceStatusFilter" class="rounded-lg border border-border bg-inputBg px-3 py-2 text-sm text-primary">
                        <option value="all">{{ tx('auto.Alle Status', 'Alle Status') }}</option>
                        <option v-for="status in invoiceStatusOptions" :key="status.value" :value="status.value">
                            {{ status.label }}
                        </option>
                    </select>
                </div>
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
                                <td class="py-3 pr-4 text-primary">{{ invoice.title || '-' }}</td>
                                <td class="py-3 pr-4 text-primary">{{ formatMoney(invoice.amount) }}</td>
                                <td class="py-3 pr-4 text-secondary">{{ formatDate(invoice.due_date) }}</td>
                                <td class="py-3 pr-4">
                                    <div class="flex flex-col gap-2">
                                        <span class="inline-flex w-fit rounded-full px-2 py-1 text-xs font-semibold" :class="invoiceStatusClass(invoice.status)">
                                            {{ invoice.status_label || invoiceStatusLabel(invoice.status) }}
                                        </span>
                                        <select :value="invoice.status" class="rounded border border-border bg-inputBg px-2 py-1 text-xs text-primary" @change="updateInvoiceStatus(invoice, $event.target.value)">
                                            <option v-for="status in invoiceStatusOptions" :key="status.value" :value="status.value">
                                                {{ status.label }}
                                            </option>
                                        </select>
                                    </div>
                                </td>
                                <td class="py-3 pr-4">
                                    <div class="flex gap-2">
                                        <button v-if="invoice.status !== 'paid'" type="button" class="rounded bg-buttonPrimary px-2 py-1 text-xs text-buttonTextPrimary" @click="markPaid(invoice)">
                                            {{ tx('auto.Bezahlt', 'Bezahlt') }}
                                        </button>
                                        <button
                                            v-if="invoice.status !== 'paid'"
                                            type="button"
                                            class="rounded border border-border px-2 py-1 text-xs text-primary disabled:cursor-not-allowed disabled:opacity-50"
                                            :disabled="capabilities.payment_reminders === false"
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
                    <select v-model="transactionStatusFilter" class="rounded-lg border border-border bg-inputBg px-3 py-2 text-sm text-primary">
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
                                        {{ transaction.status === 'matched' ? tx('club_memberships.workspace.matched', 'Verbucht') : transaction.status === 'suggested' ? tx('club_memberships.workspace.suggested', 'Vorschlag') : tx('auto.Offen', 'Offen') }}
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
                                        {{ tx('club_memberships.workspace.confirm', 'Bestätigen') }}
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
                        <label class="text-xs font-semibold uppercase text-secondary">{{ tx('club_memberships.workspace.invitation_expires', 'Einladung gültig bis') }}</label>
                        <input
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
                                    <label class="text-xs font-semibold uppercase text-secondary">{{ tx('club_memberships.workspace.name', 'Name') }}</label>
                                    <input
                                        v-model="member.name"
                                        class="mt-1 w-full rounded-lg border border-border bg-inputBg px-3 py-2 text-sm text-primary"
                                        :placeholder="tx('club_memberships.workspace.optional', 'Optional')"
                                    >
                                </div>

                                <div>
                                    <label class="text-xs font-semibold uppercase text-secondary">{{ tx('club_memberships.workspace.email', 'E-Mail') }}</label>
                                    <input
                                        v-model="member.email"
                                        type="email"
                                        required
                                        class="mt-1 w-full rounded-lg border border-border bg-inputBg px-3 py-2 text-sm text-primary"
                                        placeholder="mitglied@example.org"
                                    >
                                </div>

	                                <div>
	                                    <label class="text-xs font-semibold uppercase text-secondary">{{ tx('auto.Mitgliedschaft', 'Mitgliedschaft') }}</label>
	                                    <select v-model="member.membership_status" class="mt-1 w-full rounded-lg border border-border bg-inputBg px-3 py-2 text-sm text-primary">
	                                        <option v-for="status in membershipStatuses" :key="status" :value="status">{{ statusLabel(status) }}</option>
	                                    </select>
	                                </div>

	                                <div>
	                                    <label class="text-xs font-semibold uppercase text-secondary">{{ tx('club_memberships.workspace.club_role', 'Vereinsrolle') }}</label>
	                                    <select v-model="member.role" class="mt-1 w-full rounded-lg border border-border bg-inputBg px-3 py-2 text-sm text-primary">
	                                        <option v-for="role in clubRoles" :key="role.value" :value="role.value">{{ role.label }}</option>
	                                    </select>
	                                </div>

	                                <div>
	                                    <label class="text-xs font-semibold uppercase text-secondary">{{ tx('club_memberships.workspace.member_number', 'Mitgliedsnummer') }}</label>
                                    <input
                                        v-model="member.member_number"
                                        class="mt-1 w-full rounded-lg border border-border bg-inputBg px-3 py-2 text-sm text-primary"
                                        :placeholder="tx('club_memberships.workspace.optional', 'Optional')"
                                    >
                                </div>

                                <div>
                                    <label class="text-xs font-semibold uppercase text-secondary">{{ tx('club_memberships.workspace.license_number', 'Lizenznummer') }}</label>
                                    <input
                                        v-model="member.athlete_license_number"
                                        class="mt-1 w-full rounded-lg border border-border bg-inputBg px-3 py-2 text-sm text-primary"
                                        :placeholder="tx('club_memberships.workspace.optional', 'Optional')"
                                    >
                                </div>

                                <div>
                                    <label class="text-xs font-semibold uppercase text-secondary">{{ tx('club_memberships.workspace.contribution', 'Beitrag') }}</label>
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
                                    <label class="text-xs font-semibold uppercase text-secondary">{{ tx('club_memberships.workspace.interval_label', 'Intervall') }}</label>
                                    <select v-model="member.contribution_interval" class="mt-1 w-full rounded-lg border border-border bg-inputBg px-3 py-2 text-sm text-primary">
                                        <option v-for="interval in contributionIntervals" :key="interval" :value="interval">{{ intervalLabel(interval) }}</option>
                                    </select>
                                </div>

                                <div>
                                    <label class="text-xs font-semibold uppercase text-secondary">{{ tx('club_memberships.workspace.next_invoice', 'Nächste automatische Rechnung') }}</label>
                                    <input
                                        v-model="member.contribution_next_invoice_on"
                                        type="date"
                                        class="mt-1 w-full rounded-lg border border-border bg-inputBg px-3 py-2 text-sm text-primary"
                                    >
                                </div>

                                <div>
                                    <label class="text-xs font-semibold uppercase text-secondary">{{ tx('club_memberships.workspace.sepa_iban', 'SEPA IBAN') }}</label>
                                    <input
                                        v-model="member.sepa_iban"
                                        class="mt-1 w-full rounded-lg border border-border bg-inputBg px-3 py-2 text-sm text-primary"
                                        :placeholder="tx('club_memberships.workspace.optional', 'Optional')"
                                    >
                                </div>

                                <div>
                                    <label class="text-xs font-semibold uppercase text-secondary">{{ tx('club_memberships.workspace.sepa_bic', 'SEPA BIC') }}</label>
                                    <input
                                        v-model="member.sepa_bic"
                                        class="mt-1 w-full rounded-lg border border-border bg-inputBg px-3 py-2 text-sm text-primary"
                                        :placeholder="tx('club_memberships.workspace.optional', 'Optional')"
                                    >
                                </div>

                                <div>
                                    <label class="text-xs font-semibold uppercase text-secondary">{{ tx('club_memberships.workspace.mandate_reference', 'Mandatsreferenz') }}</label>
                                    <input
                                        v-model="member.sepa_mandate_reference"
                                        class="mt-1 w-full rounded-lg border border-border bg-inputBg px-3 py-2 text-sm text-primary"
                                        :placeholder="tx('club_memberships.workspace.optional', 'Optional')"
                                    >
                                </div>

                                <div>
                                    <label class="text-xs font-semibold uppercase text-secondary">{{ tx('club_memberships.workspace.mandate_date', 'Mandatsdatum') }}</label>
                                    <input
                                        v-model="member.sepa_mandate_signed_on"
                                        type="date"
                                        class="mt-1 w-full rounded-lg border border-border bg-inputBg px-3 py-2 text-sm text-primary"
                                    >
                                </div>

                                <label class="flex items-center gap-2 self-end rounded-lg border border-border bg-inputBg px-3 py-2 text-sm text-primary">
                                    <input v-model="member.sepa_mandate_active" type="checkbox" class="rounded border-border bg-bg">
                                    {{ tx('club_memberships.workspace.sepa_active', 'SEPA aktiv') }}
                                </label>

                                <div>
                                    <label class="text-xs font-semibold uppercase text-secondary">{{ tx('club_memberships.workspace.membership_end', 'Ende der Mitgliedschaft') }}</label>
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

        <Modal :show="showImportModal" max-width="2xl" @close="showImportModal = false">
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

                <form class="mt-5 space-y-4" @submit.prevent="importEmailMembers">
                    <div>
                        <label class="text-xs font-semibold uppercase text-secondary">{{ tx('club_memberships.workspace.file', 'Datei') }}</label>
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
                            {{ tx('club_memberships.workspace.invitation_link_direct', 'Einladung/Verknüpfung direkt aktivieren') }}
                            <span class="block text-xs text-secondary">
                                {{ tx('club_memberships.workspace.invitation_link_hint', 'Bestehende Airmius-Konten werden verbunden, sonst wird eine Einladung an die E-Mail-Adresse gesendet.') }}
                            </span>
                        </span>
                    </label>

                    <div class="flex justify-end gap-2">
                        <button type="button" class="rounded-lg border border-border px-4 py-2 text-sm font-semibold text-primary" @click="showImportModal = false">
                            {{ tx('auto.Abbrechen', 'Abbrechen') }}
                        </button>
                        <button class="rounded-lg bg-buttonPrimary px-4 py-2 text-sm font-semibold text-buttonTextPrimary" :disabled="importForm.processing">
                        {{ tx('club_memberships.workspace.start_import', 'Import starten') }}
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

        <Modal :show="showBankImportModal" max-width="2xl" @close="showBankImportModal = false">
            <div class="p-2">
                <h2 class="text-xl font-bold text-primary">{{ tx('club_memberships.workspace.bank_import_title', 'Bankumsätze importieren') }}</h2>
                <p class="mt-1 text-sm text-secondary">
                    {{ tx('club_memberships.workspace.bank_import_intro', 'Lade eine CSV aus dem Online-Banking hoch. Erkannt werden typische Spalten wie Datum, Betrag, Auftraggeber, IBAN und Verwendungszweck.') }}
                </p>

                <form class="mt-5 space-y-4" @submit.prevent="importBankTransactions">
                    <div>
                        <label class="text-xs font-semibold uppercase text-secondary">{{ tx('club_memberships.workspace.csv_file', 'CSV-Datei') }}</label>
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
                        {{ tx('club_memberships.workspace.bank_import_hint', 'Sichere Treffer mit Rechnungsnummer und Betrag werden automatisch als bezahlt markiert. Vorschläge kannst du danach bestätigen.') }}
                    </p>

                    <div class="flex justify-end gap-2">
                        <button type="button" class="rounded-lg border border-border px-4 py-2 text-sm font-semibold text-primary" @click="showBankImportModal = false">
                            {{ tx('auto.Abbrechen', 'Abbrechen') }}
                        </button>
                        <button class="rounded-lg bg-buttonPrimary px-4 py-2 text-sm font-semibold text-buttonTextPrimary" :disabled="bankImportForm.processing">
                            {{ tx('club_memberships.workspace.start_import', 'Import starten') }}
                        </button>
                    </div>
                </form>
            </div>
        </Modal>
    </div>
</template>
