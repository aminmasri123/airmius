import { router, useForm } from '@inertiajs/vue3'
import { computed, ref } from 'vue'
import { confirmDialog } from '@/services/dialogService'

const defaultForm = () => ({
    owner_user_id: '',
    name: '',
    vendor: '',
    category: 'software',
    status: 'active',
    amount: '',
    currency: 'EUR',
    billing_interval: 'monthly',
    payment_method: 'direct_debit',
    next_due_on: '',
    starts_on: '',
    ends_on: '',
    notice_until_on: '',
    cancellation_period_days: '',
    auto_renews: true,
    contract_number: '',
    account_reference: '',
    contact_email: '',
    website: '',
    document_url: '',
    notes: '',
})

export function useAdminOperatingContracts({ props }) {
    const createModalOpen = ref(false)
    const editingContract = ref(null)
    const filterStatus = ref(props.filters.status || 'active')
    const filterCategory = ref(props.filters.category || 'all')
    const filterSearch = ref(props.filters.q || '')
    const form = useForm(defaultForm())

    const statusOptions = computed(() => props.options.statuses || [])
    const categoryOptions = computed(() => props.options.categories || [])
    const intervalOptions = computed(() => props.options.billingIntervals || [])
    const paymentMethodOptions = computed(() => props.options.paymentMethods || [])
    const allStatusOptions = computed(() => [{ value: 'all', label: 'Alle Status' }, ...statusOptions.value])
    const allCategoryOptions = computed(() => [{ value: 'all', label: 'Alle Kategorien' }, ...categoryOptions.value])
    const rows = computed(() => props.contracts?.data || [])
    const upcoming = computed(() => props.summary.upcoming || [])
    const categories = computed(() => props.summary.categories || [])

    const fillForm = (values = {}) => {
        const defaults = defaultForm()

        Object.keys(defaults).forEach((key) => {
            form[key] = values[key] ?? defaults[key]
        })

        form.clearErrors()
    }

    const openCreateModal = () => {
        editingContract.value = null
        fillForm()
        createModalOpen.value = true
    }

    const openEditModal = (contract) => {
        editingContract.value = contract
        fillForm({
            owner_user_id: contract.owner_user_id || '',
            name: contract.name || '',
            vendor: contract.vendor || '',
            category: contract.category || 'software',
            status: contract.status || 'active',
            amount: contract.raw_amount ?? '',
            currency: contract.currency || 'EUR',
            billing_interval: contract.billing_interval || 'monthly',
            payment_method: contract.payment_method || 'direct_debit',
            next_due_on: contract.next_due_on || '',
            starts_on: contract.starts_on || '',
            ends_on: contract.ends_on || '',
            notice_until_on: contract.notice_until_on || '',
            cancellation_period_days: contract.cancellation_period_days ?? '',
            auto_renews: Boolean(contract.auto_renews),
            contract_number: contract.contract_number || '',
            account_reference: contract.account_reference || '',
            contact_email: contract.contact_email || '',
            website: contract.website || '',
            document_url: contract.document_url || '',
            notes: contract.notes || '',
        })
        createModalOpen.value = true
    }

    const closeModal = () => {
        if (form.processing) return

        createModalOpen.value = false
        editingContract.value = null
        form.clearErrors()
    }

    const submit = () => {
        const options = {
            preserveScroll: true,
            onSuccess: closeModal,
        }

        if (editingContract.value?.update_url) {
            form.put(editingContract.value.update_url, options)
            return
        }

        form.post(route('admin.operating-contracts.store'), options)
    }

    const applyFilters = () => {
        router.get(route('admin.operating-contracts.index'), {
            status: filterStatus.value,
            category: filterCategory.value,
            q: filterSearch.value || undefined,
        }, {
            preserveScroll: true,
            preserveState: true,
            replace: true,
        })
    }

    const resetFilters = () => {
        filterStatus.value = 'active'
        filterCategory.value = 'all'
        filterSearch.value = ''
        applyFilters()
    }

    const deleteContract = async (contract) => {
        if (!contract.delete_url) return

        const confirmed = await confirmDialog({
            title: 'Vertrag löschen',
            message: `Soll "${contract.name}" wirklich gelöscht werden?`,
            confirmLabel: 'Löschen',
            danger: true,
        })

        if (!confirmed) return

        router.delete(contract.delete_url, { preserveScroll: true })
    }

    const statusClass = (status) => ({
        active: 'border-success/30 bg-success/10 text-success',
        paused: 'border-air-orange/30 bg-air-orange/10 text-air-orange',
        cancelled: 'border-secondary/30 bg-muted text-secondary',
        ended: 'border-border bg-inputBg text-secondary',
    }[status] || 'border-border bg-inputBg text-secondary')

    const deadlineClass = (days) => {
        if (days === null || days === undefined) return 'text-secondary'
        if (days < 0) return 'text-error'
        if (days <= 14) return 'text-air-orange'
        if (days <= 30) return 'text-air-blue'

        return 'text-secondary'
    }

    const deadlineLabel = (days) => {
        if (days === null || days === undefined) return '-'
        if (days < 0) return `seit ${Math.abs(days)} Tagen`
        if (days === 0) return 'heute'
        if (days === 1) return 'morgen'

        return `in ${days} Tagen`
    }

    const ownerLabel = (owner) => {
        if (!owner) return 'Nicht zugewiesen'

        return owner.email ? `${owner.name || owner.email} (${owner.email})` : owner.name
    }

    const optionLabel = (items, value, fallback = '-') => items.find((item) => item.value === value)?.label || fallback

    return {
        createModalOpen,
        editingContract,
        filterStatus,
        filterCategory,
        filterSearch,
        form,
        statusOptions,
        categoryOptions,
        intervalOptions,
        paymentMethodOptions,
        allStatusOptions,
        allCategoryOptions,
        rows,
        upcoming,
        categories,
        fillForm,
        openCreateModal,
        openEditModal,
        closeModal,
        submit,
        applyFilters,
        resetFilters,
        deleteContract,
        statusClass,
        deadlineClass,
        deadlineLabel,
        ownerLabel,
        optionLabel,
    }
}


