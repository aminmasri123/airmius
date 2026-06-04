import { router, useForm, usePage } from '@inertiajs/vue3'
import { computed, ref } from 'vue'
import { confirmDialog } from '@/services/dialogService'

export function useClubMembershipsWorkspace(props) {
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
    const bankTransactions = computed(() => selectedClub.value?.bank_transactions || [])
    
    const activeMembersCount = computed(() => members.value.filter((member) => formFor(member).membership_status === 'active').length)
    const openInvoices = computed(() => invoices.value.filter((invoice) => ['open', 'overdue'].includes(invoice.status)))
    const openInvoiceTotal = computed(() => openInvoices.value.reduce((sum, invoice) => sum + Number(invoice.amount || 0), 0))
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
        { key: 'payments', label: 'Zahlungen', count: bankTransactions.value.length, icon: 'las la-university' },
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
        yearly: 'Jährlich',
        once: 'Einmalig',
    }[interval] || interval)
    
    const formatMoney = (value) => new Intl.NumberFormat('de-DE', {
        style: 'currency',
        currency: 'EUR',
    }).format(Number(value || 0))
    
    const formatDate = (value) => {
        if (!value) return '-'
        return new Intl.DateTimeFormat('de-DE', { day: '2-digit', month: '2-digit', year: 'numeric' }).format(new Date(value))
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
        }
    
        return membershipSettingsForms.value[club.id]
    }
    
    const saveMembershipSettings = () => {
        router.put(route('auth.club-memberships.settings.update', selectedClub.value.id), membershipSettingsFor(selectedClub.value), {
            preserveScroll: true,
        })
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
    return {
        page,
        selectedClubId,
        activeTab,
        memberSearch,
        memberStatusFilter,
        memberEndFilter,
        invoiceStatusFilter,
        transactionStatusFilter,
        editingMemberId,
        invoiceMemberId,
        showAddMemberModal,
        showImportModal,
        showBankImportModal,
        memberForms,
        sepaSettingsForms,
        datevSettingsForms,
        datevExportForms,
        membershipSettingsForms,
        processingJoinRequestIds,
        createEmailMemberRow,
        emailMemberForm,
        importForm,
        bankImportForm,
        membershipTypeForm,
        contributionRuleForm,
        selectedClub,
        pageError,
        pendingRequests,
        clubRequests,
        membershipTypes,
        contributionRules,
        capabilities,
        canOpenEmailMembers,
        members,
        externalMembers,
        invoices,
        bankTransactions,
        activeMembersCount,
        openInvoices,
        openInvoiceTotal,
        sepaReadyMembersCount,
        memberUsagePercent,
        recurringContributionTotal,
        dateOnly,
        daysUntil,
        matchesMembershipEndFilter,
        endingSoonMembersCount,
        endLabel,
        filteredMembers,
        filteredInvoices,
        filteredBankTransactions,
        tabs,
        statusLabel,
        statusClass,
        clubRoleOptions,
        intervalLabel,
        formatMoney,
        formatDate,
        formFor,
        sepaSettingsFor,
        saveSepaSettings,
        datevSettingsFor,
        datevExportFor,
        saveDatevSettings,
        membershipSettingsFor,
        saveMembershipSettings,
        datevExportUrl,
        invoiceForm,
        approveRequest,
        declineRequest,
        approveClubRequest,
        declineClubRequest,
        storeMembershipType,
        storeContributionRule,
        saveMember,
        generateMemberNumber,
        removeMember,
        openInvoice,
        createInvoice,
        markPaid,
        updateInvoiceStatus,
        sendReminder,
        importBankTransactions,
        confirmBankTransaction,
        addEmailMember,
        addEmailMemberRow,
        removeEmailMemberRow,
        invitationRowsToSend,
        decrementInvitationLimit,
        importEmailMembers,
        inviteExternalMember,
    }
}
