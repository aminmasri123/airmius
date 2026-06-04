import { router, useForm } from '@inertiajs/vue3'
import { computed, ref, watch } from 'vue'
import { confirmDialog } from '@/services/dialogService'

export function useAdminInvoices(props) {
    const today = new Date().toISOString().slice(0, 10)
    const recipientMode = ref('person')
    const createModalOpen = ref(false)
    const statusModal = ref({ open: false, invoice: null })

    const form = useForm({
        source: props.invoiceTypes[0]?.value || 'custom',
        club_id: '',
        user_id: '',
        number: '',
        title: '',
        description: '',
        amount: '',
        status: 'open',
        due_date: today,
        issued_at: today,
    })

    const statusForm = useForm({
        status: 'open',
    })

    const exampleTitles = {
        account_subscription: 'Konto-Abo / Upgrade',
        outfit_subscription_manual: 'Outfit-Abo Rechnung',
        marketplace_purchase: 'Marketplace Kauf',
        elearning: 'E-Learning Kursgebuehr',
        ads: 'Werbekampagne / ADS',
        agency_website: 'Website-Projekt',
        agency_logo: 'Logo-Design',
        agency_branding: 'Branding-Paket',
        sponsorship: 'Sponsoring-Paket',
        custom: 'Individuelle Leistung',
    }

    const statusOptions = [
        { value: 'open', label: 'Offen', hint: 'Rechnung ist erstellt und noch nicht bezahlt.' },
        { value: 'pending', label: 'Ausstehend', hint: 'Zahlung oder Prüfung ist noch in Bearbeitung.' },
        { value: 'paid', label: 'Bezahlt', hint: 'Rechnung wird als bezahlt markiert.' },
        { value: 'overdue', label: 'Überfällig', hint: 'Fälligkeit ist abgelaufen.' },
        { value: 'cancelled', label: 'Storniert', hint: 'Rechnung ist nicht mehr aktiv.' },
    ]

    const selectedType = computed(() => props.invoiceTypes.find((type) => type.value === form.source))
    const selectedRecipientLabel = computed(() => {
        if (recipientMode.value === 'person') {
            return props.users.find((user) => String(user.id) === String(form.user_id))?.name || 'Person wählen'
        }

        return props.clubs.find((club) => String(club.id) === String(form.club_id))?.name || 'Verein wählen'
    })

    watch(() => form.source, (source) => {
        if (!form.title || Object.values(exampleTitles).includes(form.title)) {
            form.title = exampleTitles[source] || ''
        }
    })

    watch(recipientMode, (mode) => {
        if (mode === 'person') form.club_id = ''
        if (mode === 'club') form.user_id = ''
    })

    const submit = () => {
        form.post(route('invoices.store'), {
            preserveScroll: true,
            onSuccess: () => {
                const source = form.source

                form.reset()
                form.source = source
                form.status = 'open'
                form.due_date = today
                form.issued_at = today
                recipientMode.value = 'person'
                createModalOpen.value = false
            },
        })
    }

    const openCreateModal = () => {
        if (!form.title) {
            form.title = exampleTitles[form.source] || ''
        }

        createModalOpen.value = true
    }

    const closeCreateModal = () => {
        if (form.processing) return

        createModalOpen.value = false
    }

    const openStatusModal = (invoice) => {
        if (!invoice.status_update_url) return

        statusModal.value = { open: true, invoice }
        statusForm.clearErrors()
        statusForm.status = invoice.status || 'open'
    }

    const closeStatusModal = () => {
        if (statusForm.processing) return

        statusModal.value = { open: false, invoice: null }
        statusForm.clearErrors()
    }

    const submitStatus = () => {
        const invoice = statusModal.value.invoice

        if (!invoice?.status_update_url) return

        statusForm.put(invoice.status_update_url, {
            preserveScroll: true,
            onSuccess: closeStatusModal,
        })
    }

    const userLabel = (user) => user.email
        ? `${user.name || user.email} (${user.email})`
        : (user.name || `Nutzer #${user.id}`)

    const recipientLabel = (invoice) => {
        if (invoice.club?.name && invoice.user?.name) return `${invoice.user.name} / ${invoice.club.name}`
        if (invoice.user?.name) return invoice.user.name
        if (invoice.club?.name) return invoice.club.name

        return 'Ohne Empfänger'
    }

    const statusLabel = (status) => ({
        paid: 'Bezahlt',
        open: 'Offen',
        pending: 'Ausstehend',
        awaiting_transfer: 'Warte auf Überweisung',
        overdue: 'Überfällig',
        cancelled: 'Storniert',
        failed: 'Fehlgeschlagen',
    }[status] || status || '-')

    const statusClasses = (status) => ({
        paid: 'bg-air-green/15 text-air-green border-air-green/30',
        open: 'bg-air-blue/15 text-air-blue border-air-blue/30',
        pending: 'bg-air-orange/15 text-air-orange border-air-orange/30',
        awaiting_transfer: 'bg-air-orange/15 text-air-orange border-air-orange/30',
        overdue: 'bg-error/15 text-error border-error/30',
        cancelled: 'bg-muted text-secondary border-border',
        failed: 'bg-error/15 text-error border-error/30',
    }[status] || 'bg-muted text-secondary border-border')

    const deleteInvoice = async (invoice) => {
        if (!invoice.delete_url) {
            return
        }

        const confirmed = await confirmDialog({
            title: 'Rechnung löschen',
            message: `Soll Rechnung ${invoice.number || invoice.id} wirklich gelöscht werden?`,
            confirmLabel: 'Löschen',
            danger: true,
        })

        if (!confirmed) {
            return
        }

        router.delete(invoice.delete_url, { preserveScroll: true })
    }

    return {
        recipientMode,
        createModalOpen,
        statusModal,
        form,
        statusForm,
        exampleTitles,
        statusOptions,
        selectedType,
        selectedRecipientLabel,
        submit,
        openCreateModal,
        closeCreateModal,
        openStatusModal,
        closeStatusModal,
        submitStatus,
        userLabel,
        recipientLabel,
        statusLabel,
        statusClasses,
        deleteInvoice,
    }
}



