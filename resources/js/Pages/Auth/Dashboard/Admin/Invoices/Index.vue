<script setup>
import AppLayout from '@/Components/Auth/Layouts/AppLayout.vue'
import { Head, Link, router, useForm } from '@inertiajs/vue3'
import { computed, ref, watch } from 'vue'
import { confirmDialog } from '@/services/dialogService'

defineOptions({ layout: AppLayout })

const props = defineProps({
    invoices: { type: Object, required: true },
    summary: { type: Object, default: () => ({}) },
    clubs: { type: Array, default: () => [] },
    users: { type: Array, default: () => [] },
    invoiceTypes: { type: Array, default: () => [] },
})

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

const selectedType = computed(() => props.invoiceTypes.find((type) => type.value === form.source))
const selectedRecipientLabel = computed(() => {
    if (recipientMode.value === 'person') {
        return props.users.find((user) => String(user.id) === String(form.user_id))?.name || 'Person wählen'
    }

    return props.clubs.find((club) => String(club.id) === String(form.club_id))?.name || 'Verein wählen'
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
    return 'Ohne Empfaenger'
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

const statusOptions = [
    { value: 'open', label: 'Offen', hint: 'Rechnung ist erstellt und noch nicht bezahlt.' },
    { value: 'pending', label: 'Ausstehend', hint: 'Zahlung oder Prüfung ist noch in Bearbeitung.' },
    { value: 'paid', label: 'Bezahlt', hint: 'Rechnung wird als bezahlt markiert.' },
    { value: 'overdue', label: 'Überfällig', hint: 'Faelligkeit ist abgelaufen.' },
    { value: 'cancelled', label: 'Storniert', hint: 'Rechnung ist nicht mehr aktiv.' },
]

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
</script>

<template>
    <Head title="Rechnungen" />

    <div class="space-y-6">
        <section class="overflow-hidden rounded-2xl border-l-4 border-l-air-blue border-border bg-card">
            <div class="grid gap-6 p-5 lg:grid-cols-[1fr_auto] lg:items-center lg:p-6">
                <div>
                        <p class="text-xs font-semibold uppercase tracking-[0.18em] text-air-blue">Finanzen</p>
                        <h1 class="mt-2 text-3xl font-black text-primary">Rechnungszentrale</h1>
                        <p class="mt-2 max-w-3xl text-sm leading-6 text-secondary">
                        Erstelle und prüfe Rechnungen für Konto-Abos, Outfit-Abos, Marketplace-Kaeufe, Kurse,
                        ADS, Sponsoring und Werbeagentur-Leistungen wie Website, Logo oder Branding.
                    </p>
                </div>
                <div class="flex flex-col gap-2 sm:flex-row">
                    <button
                        type="button"
                        class="inline-flex items-center justify-center gap-2 rounded-xl bg-buttonPrimary px-4 py-3 text-sm font-black text-buttonTextPrimary hover:bg-buttonPrimaryHover"
                        @click="openCreateModal"
                    >
                        <i class="las la-plus-circle text-lg"></i>
                        Rechnung erstellen
                    </button>
                    <Link :href="route('payments.index')" class="inline-flex items-center justify-center gap-2 rounded-xl border border-border px-4 py-3 text-sm font-bold text-primary hover:bg-muted">
                        <i class="las la-credit-card text-lg"></i>
                        Zahlungen
                    </Link>
                </div>
            </div>
        </section>

        <section class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
            <div class="rounded-2xl border border-border bg-card p-5">
                <p class="text-xs font-bold uppercase tracking-wide text-secondary">Alle Rechnungen</p>
                <p class="mt-3 text-3xl font-black text-primary">{{ summary.count || 0 }}</p>
            </div>
            <div class="rounded-2xl border border-border bg-card p-5">
                <p class="text-xs font-bold uppercase tracking-wide text-secondary">Offen</p>
                <p class="mt-3 text-3xl font-black text-primary">{{ summary.open || 0 }}</p>
            </div>
            <div class="rounded-2xl border border-border bg-card p-5">
                <p class="text-xs font-bold uppercase tracking-wide text-secondary">Bezahlt</p>
                <p class="mt-3 text-3xl font-black text-primary">{{ summary.paid || 0 }}</p>
            </div>
            <div class="rounded-2xl border border-border bg-card p-5">
                <p class="text-xs font-bold uppercase tracking-wide text-secondary">Umsatz bezahlt</p>
                <p class="mt-3 text-3xl font-black text-primary">{{ summary.revenue || '0,00 EUR' }}</p>
            </div>
        </section>

        <section class="rounded-2xl border-l-4 border-l-air-blue border border-border bg-card">
            <div class="flex flex-col gap-2 border-b border-border p-5 sm:flex-row sm:items-center sm:justify-between">
                <div>
                    <p class="text-xs font-semibold uppercase tracking-[0.16em] text-air-blue">Rechnungsliste</p>
                    <h2 class="mt-1 text-lg font-black text-primary">Alle Quellen zentral</h2>
                </div>
                <div class="flex flex-col gap-3 sm:flex-row sm:items-center">
                    <p class="text-sm text-secondary">ADS, Marketplace, E-Learning, Outfit, Konto-Abo und manuelle Rechnungen</p>
                    <button type="button" class="inline-flex items-center justify-center gap-2 rounded-xl bg-buttonPrimary px-4 py-2 text-sm font-black text-buttonTextPrimary hover:bg-buttonPrimaryHover" @click="openCreateModal">
                        <i class="las la-plus-circle text-lg"></i>
                        Neu
                    </button>
                </div>
            </div>

            <div class="hidden overflow-x-auto lg:block">
                <table class="min-w-full text-left text-sm">
                    <thead class="bg-bg text-xs uppercase tracking-[0.12em] text-secondary">
                        <tr>
                            <th class="px-5 py-3">Rechnung</th>
                            <th class="px-5 py-3">Empfaenger</th>
                            <th class="px-5 py-3">Grund</th>
                            <th class="px-5 py-3">Betrag</th>
                            <th class="px-5 py-3">Bezahlt</th>
                            <th class="px-5 py-3">Status</th>
                            <th class="px-5 py-3 text-right">Aktionen</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-border">
                        <tr v-for="invoice in invoices.data" :key="invoice.id" class="hover:bg-muted/40">
                            <td class="px-5 py-4">
                                <p class="font-black text-primary">{{ invoice.number || ('#' + invoice.id) }}</p>
                                <p class="mt-1 text-xs text-secondary">{{ invoice.title || '-' }}</p>
                                <p class="text-xs text-secondary">Faellig {{ invoice.due_date || '-' }}</p>
                            </td>
                            <td class="px-5 py-4">
                                <p class="font-bold text-primary">{{ recipientLabel(invoice) }}</p>
                                <p class="text-xs text-secondary">{{ invoice.user?.email || '-' }}</p>
                            </td>
                            <td class="px-5 py-4">
                                <p class="font-bold text-primary">{{ invoice.type_label || '-' }}</p>
                                <p class="text-xs text-secondary">{{ invoice.source || '-' }}</p>
                            </td>
                            <td class="px-5 py-4 font-black text-primary">{{ invoice.amount }}</td>
                            <td class="px-5 py-4">
                                <p class="text-primary">{{ invoice.paid_amount }}</p>
                                <p class="text-xs text-secondary">{{ invoice.paid_at || '-' }}</p>
                            </td>
                            <td class="px-5 py-4">
                            <button
                                v-if="invoice.status_update_url"
                                type="button"
                                class="inline-flex items-center rounded-full border px-3 py-1 text-xs font-semibold transition hover:scale-[1.02] hover:ring-2 hover:ring-air-blue/30"
                                :class="statusClasses(invoice.status)"
                                @click="openStatusModal(invoice)"
                            >
                                {{ statusLabel(invoice.status) }}
                            </button>
                            <span v-else class="inline-flex rounded-full border px-3 py-1 text-xs font-black" :class="statusClasses(invoice.status)">
                                {{ statusLabel(invoice.status) }}
                            </span>
                            </td>
                            <td class="px-5 py-4 text-right">
                                <div class="flex flex-wrap justify-end gap-2">
                                    <a v-if="invoice.download_url" :href="invoice.download_url" class="rounded-lg border border-border px-3 py-2 text-xs font-bold text-primary hover:bg-muted">PDF</a>
                                    <button v-if="invoice.delete_url" type="button" class="rounded-lg bg-error px-3 py-2 text-xs font-semibold text-white hover:bg-error/90" @click="deleteInvoice(invoice)">Löschen</button>
                                    <span v-if="!invoice.download_url && !invoice.delete_url" class="text-xs text-secondary">-</span>
                                </div>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <div class="grid gap-3 p-4 lg:hidden">
                <article v-for="invoice in invoices.data" :key="invoice.id" class="rounded-xl border border-border bg-inputBg p-4">
                    <div class="flex items-start justify-between gap-3">
                        <div class="min-w-0">
                            <p class="truncate font-black text-primary">{{ invoice.number || ('#' + invoice.id) }}</p>
                            <p class="mt-1 text-sm text-secondary">{{ invoice.title || invoice.type_label }}</p>
                        </div>
                                    <button
                                        v-if="invoice.status_update_url"
                                        type="button"
                                        class="shrink-0 rounded-full border px-3 py-1 text-xs font-semibold"
                                        :class="statusClasses(invoice.status)"
                                        @click="openStatusModal(invoice)"
                                    >
                                        {{ statusLabel(invoice.status) }}
                                    </button>
                                    <span v-else class="shrink-0 rounded-full border px-3 py-1 text-xs font-black" :class="statusClasses(invoice.status)">
                                        {{ statusLabel(invoice.status) }}
                                    </span>
                    </div>
                    <div class="mt-4 grid grid-cols-2 gap-3 text-sm">
                        <div>
                            <p class="text-xs uppercase text-secondary">Empfaenger</p>
                            <p class="mt-1 font-bold text-primary">{{ recipientLabel(invoice) }}</p>
                        </div>
                        <div>
                            <p class="text-xs uppercase text-secondary">Betrag</p>
                            <p class="mt-1 font-bold text-primary">{{ invoice.amount }}</p>
                        </div>
                    </div>
                    <div class="mt-4 flex flex-wrap gap-2">
                        <a v-if="invoice.download_url" :href="invoice.download_url" class="rounded-lg border border-border px-3 py-2 text-xs font-bold text-primary">PDF</a>
                                <button v-if="invoice.delete_url" type="button" class="rounded-lg bg-error px-3 py-2 text-xs font-semibold text-white hover:bg-error/90" @click="deleteInvoice(invoice)">Löschen</button>
                    </div>
                </article>
            </div>

            <p v-if="!invoices.data.length" class="px-5 py-8 text-sm text-secondary">Noch keine Rechnungen vorhanden.</p>

            <div v-if="invoices.links?.length > 3" class="flex flex-wrap gap-2 border-t border-border px-5 py-4">
                <Link
                    v-for="link in invoices.links"
                    :key="link.label"
                    :href="link.url || '#'"
                    preserve-scroll
                    class="rounded-lg border border-border px-3 py-2 text-sm"
                    :class="[link.active ? 'bg-buttonPrimary text-buttonTextPrimary' : 'text-primary hover:bg-muted', !link.url ? 'pointer-events-none opacity-40' : '']"
                    v-html="link.label"
                />
            </div>
        </section>

        <Teleport to="body">
            <div
                v-if="statusModal.open"
                class="fixed inset-0 z-[90] flex items-center justify-center bg-black/65 p-3 backdrop-blur-sm sm:p-4"
                @click.self="closeStatusModal"
            >
                <form
                    class="w-full max-w-xl overflow-hidden rounded-2xl border border-border bg-card shadow-2xl"
                    @submit.prevent="submitStatus"
                >
                    <div class="flex items-start justify-between gap-4 border-b border-border px-5 py-4">
                        <div class="min-w-0">
                            <p class="text-xs font-semibold uppercase tracking-[0.16em] text-air-blue">Status aktualisieren</p>
                            <h2 class="mt-1 truncate text-xl font-black text-primary">
                                {{ statusModal.invoice?.number || 'Rechnung' }}
                            </h2>
                            <p class="mt-1 text-sm text-secondary">
                                {{ statusModal.invoice?.title || 'Rechnungsstatus verwalten' }}
                            </p>
                        </div>
                        <button
                            type="button"
                            class="inline-flex h-10 w-10 shrink-0 items-center justify-center rounded-xl border border-border text-secondary hover:bg-muted hover:text-primary"
                            :disabled="statusForm.processing"
                            aria-label="Status-Modal schließen"
                            @click="closeStatusModal"
                        >
                            <i class="las la-times text-xl"></i>
                        </button>
                    </div>

                    <div class="space-y-4 p-5">
                        <div class="grid gap-3 sm:grid-cols-2">
                            <button
                                v-for="option in statusOptions"
                                :key="option.value"
                                type="button"
                                class="rounded-xl border p-4 text-left transition"
                                :class="statusForm.status === option.value ? 'border-air-blue bg-air-blue/10 text-primary' : 'border-border bg-inputBg text-secondary hover:border-borderHover hover:text-primary'"
                                @click="statusForm.status = option.value"
                            >
                                <span class="block text-sm font-black">{{ option.label }}</span>
                                <span class="mt-1 block text-xs leading-5">{{ option.hint }}</span>
                            </button>
                        </div>
                        <p v-if="statusForm.errors.status" class="text-sm text-error">{{ statusForm.errors.status }}</p>

                        <div class="rounded-xl border border-border bg-inputBg p-4 text-sm">
                            <div class="flex items-center justify-between gap-4">
                                <span class="text-secondary">Aktuell</span>
                                <span class="rounded-full border px-3 py-1 text-xs font-black" :class="statusClasses(statusModal.invoice?.status)">
                                    {{ statusLabel(statusModal.invoice?.status) }}
                                </span>
                            </div>
                            <div class="mt-3 flex items-center justify-between gap-4">
                                <span class="text-secondary">Neu</span>
                                <span class="rounded-full border px-3 py-1 text-xs font-black" :class="statusClasses(statusForm.status)">
                                    {{ statusLabel(statusForm.status) }}
                                </span>
                            </div>
                        </div>
                    </div>

                    <div class="flex flex-col-reverse gap-3 border-t border-border px-5 py-4 sm:flex-row sm:justify-end">
                        <button type="button" class="rounded-xl border border-border px-5 py-3 text-sm font-bold text-primary hover:bg-muted" :disabled="statusForm.processing" @click="closeStatusModal">
                            Abbrechen
                        </button>
                        <button
                            type="submit"
                            class="inline-flex items-center justify-center gap-2 rounded-xl bg-buttonPrimary px-5 py-3 text-sm font-black text-buttonTextPrimary disabled:cursor-not-allowed disabled:opacity-50"
                            :disabled="statusForm.processing"
                        >
                            <i class="las la-sync-alt text-lg"></i>
                            Status speichern
                        </button>
                    </div>
                </form>
            </div>
        </Teleport>

        <Teleport to="body">
            <div
                v-if="createModalOpen"
                class="fixed inset-0 z-[90] flex items-center justify-center bg-black/65 p-3 backdrop-blur-sm sm:p-4"
                @click.self="closeCreateModal"
            >
                <form
                    class="flex max-h-[calc(100dvh-1.5rem)] w-full max-w-6xl flex-col overflow-hidden rounded-2xl border border-border bg-card shadow-2xl"
                    @submit.prevent="submit"
                >
                    <div class="flex shrink-0 items-start justify-between gap-4 border-b border-border px-4 py-4 sm:px-6">
                        <div class="min-w-0">
                            <p class="text-xs font-semibold uppercase tracking-[0.16em] text-air-blue">Neue Rechnung</p>
                            <h2 class="mt-1 text-xl font-black text-primary sm:text-2xl">Rechnung erstellen</h2>
                            <p class="mt-1 text-sm text-secondary">
                                Grund, Empfaenger und Leistungsdetails erfassen. Danach wird die Person automatisch informiert.
                            </p>
                        </div>
                        <button
                            type="button"
                            class="inline-flex h-10 w-10 shrink-0 items-center justify-center rounded-xl border border-border text-secondary hover:bg-muted hover:text-primary"
                            :disabled="form.processing"
                            aria-label="Modal schließen"
                            @click="closeCreateModal"
                        >
                            <i class="las la-times text-xl"></i>
                        </button>
                    </div>

                    <div class="min-h-0 flex-1 overflow-y-auto p-4 sm:p-6">
                        <div class="grid gap-5 xl:grid-cols-[minmax(0,1fr)_22rem]">
                            <div class="space-y-5">
                                <section class="rounded-2xl border border-border bg-inputBg p-4">
                                    <p class="text-sm font-bold text-primary">Rechnungsgrund</p>
                                    <div class="mt-3 grid gap-2 sm:grid-cols-2 lg:grid-cols-3">
                                        <button
                                            v-for="type in invoiceTypes"
                                            :key="type.value"
                                            type="button"
                                            class="rounded-xl border p-3 text-left transition"
                                            :class="form.source === type.value ? 'border-air-blue bg-air-blue/10 text-primary' : 'border-border bg-card text-secondary hover:border-borderHover hover:text-primary'"
                                            @click="form.source = type.value"
                                        >
                                            <span class="block text-sm font-black">{{ type.label }}</span>
                                            <span class="mt-1 block text-xs leading-5">{{ type.hint }}</span>
                                        </button>
                                    </div>
                                    <p v-if="form.errors.source" class="mt-1 text-xs text-error">{{ form.errors.source }}</p>
                                </section>

                                <section class="rounded-2xl border border-border bg-inputBg p-4">
                                    <div class="flex flex-col gap-3 lg:flex-row lg:items-center lg:justify-between">
                                        <div>
                                            <p class="text-sm font-bold text-primary">Empfaenger</p>
                                            <p class="mt-1 text-xs text-secondary">Sportler, Trainer, Sponsor, Kursanbieter oder Verein.</p>
                                        </div>
                                        <div class="grid grid-cols-2 overflow-hidden rounded-xl border border-border bg-card p-1">
                                            <button type="button" class="rounded-lg px-3 py-2 text-sm font-bold" :class="recipientMode === 'person' ? 'bg-buttonPrimary text-buttonTextPrimary' : 'text-secondary hover:text-primary'" @click="recipientMode = 'person'">
                                                Person
                                            </button>
                                            <button type="button" class="rounded-lg px-3 py-2 text-sm font-bold" :class="recipientMode === 'club' ? 'bg-buttonPrimary text-buttonTextPrimary' : 'text-secondary hover:text-primary'" @click="recipientMode = 'club'">
                                                Verein
                                            </button>
                                        </div>
                                    </div>

                                    <div class="mt-4 grid gap-4 lg:grid-cols-2">
                                        <label v-if="recipientMode === 'person'" class="block">
                                            <span class="text-sm font-semibold text-primary">Person auswählen</span>
                                            <select v-model="form.user_id" class="mt-1 w-full rounded-xl border-border bg-card text-primary">
                                                <option value="">Sportler, Trainer, Sponsor oder Kursanbieter wählen</option>
                                                <option v-for="user in users" :key="user.id" :value="user.id">{{ userLabel(user) }}</option>
                                            </select>
                                            <p v-if="form.errors.user_id" class="mt-1 text-xs text-error">{{ form.errors.user_id }}</p>
                                        </label>

                                        <label v-if="recipientMode === 'club'" class="block">
                                            <span class="text-sm font-semibold text-primary">Verein auswählen</span>
                                            <select v-model="form.club_id" class="mt-1 w-full rounded-xl border-border bg-card text-primary">
                                                <option value="">Verein wählen</option>
                                                <option v-for="club in clubs" :key="club.id" :value="club.id">{{ club.name }}</option>
                                            </select>
                                            <p v-if="form.errors.club_id" class="mt-1 text-xs text-error">{{ form.errors.club_id }}</p>
                                        </label>

                                        <label class="block">
                                            <span class="text-sm font-semibold text-primary">Status</span>
                                            <select v-model="form.status" class="mt-1 w-full rounded-xl border-border bg-card text-primary">
                                                <option value="open">Offen</option>
                                                <option value="pending">Ausstehend</option>
                                                <option value="paid">Bezahlt</option>
                                                <option value="overdue">Überfällig</option>
                                                <option value="cancelled">Storniert</option>
                                            </select>
                                            <p v-if="form.errors.status" class="mt-1 text-xs text-error">{{ form.errors.status }}</p>
                                        </label>
                                    </div>
                                </section>

                                <section class="grid gap-4 md:grid-cols-2">
                                    <label class="block md:col-span-2">
                                        <span class="text-sm font-semibold text-primary">Titel</span>
                                        <input v-model="form.title" class="mt-1 w-full rounded-xl border-border bg-inputBg text-primary" :placeholder="exampleTitles[form.source] || 'Rechnungstitel'" required>
                                        <p v-if="form.errors.title" class="mt-1 text-xs text-error">{{ form.errors.title }}</p>
                                    </label>

                                    <label class="block">
                                        <span class="text-sm font-semibold text-primary">Betrag EUR</span>
                                        <input v-model="form.amount" class="mt-1 w-full rounded-xl border-border bg-inputBg text-primary" min="0.01" step="0.01" type="number" required>
                                        <p v-if="form.errors.amount" class="mt-1 text-xs text-error">{{ form.errors.amount }}</p>
                                    </label>

                                    <label class="block">
                                        <span class="text-sm font-semibold text-primary">Rechnungsnummer</span>
                                        <input v-model="form.number" class="mt-1 w-full rounded-xl border-border bg-inputBg text-primary" placeholder="Optional, sonst automatisch">
                                        <p v-if="form.errors.number" class="mt-1 text-xs text-error">{{ form.errors.number }}</p>
                                    </label>

                                    <label class="block">
                                        <span class="text-sm font-semibold text-primary">Ausgestellt am</span>
                                        <input v-model="form.issued_at" class="mt-1 w-full rounded-xl border-border bg-inputBg text-primary" type="date">
                                        <p v-if="form.errors.issued_at" class="mt-1 text-xs text-error">{{ form.errors.issued_at }}</p>
                                    </label>

                                    <label class="block">
                                        <span class="text-sm font-semibold text-primary">Faellig am</span>
                                        <input v-model="form.due_date" class="mt-1 w-full rounded-xl border-border bg-inputBg text-primary" type="date" required>
                                        <p v-if="form.errors.due_date" class="mt-1 text-xs text-error">{{ form.errors.due_date }}</p>
                                    </label>

                                    <label class="block md:col-span-2">
                                        <span class="text-sm font-semibold text-primary">Beschreibung / Leistungsdetails</span>
                                        <textarea v-model="form.description" class="mt-1 min-h-28 w-full rounded-xl border-border bg-inputBg text-primary" placeholder="z.B. Website-Konzept, Logo-Entwurf, Kursgebuehr, Sponsoring-Paket, Outfit-Abo, Marketplace-Kauf ..."></textarea>
                                        <p v-if="form.errors.description" class="mt-1 text-xs text-error">{{ form.errors.description }}</p>
                                    </label>
                                </section>
                            </div>

                            <aside class="rounded-2xl border border-border bg-inputBg p-5 xl:sticky xl:top-0 xl:self-start">
                                <p class="text-xs font-semibold uppercase tracking-[0.16em] text-air-blue">Vorschau</p>
                                <h3 class="mt-2 text-lg font-black text-primary">{{ form.title || exampleTitles[form.source] || 'Neue Rechnung' }}</h3>
                                <div class="mt-4 space-y-3 text-sm">
                                    <div class="rounded-xl bg-card p-3">
                                        <p class="text-xs uppercase text-secondary">Grund</p>
                                        <p class="mt-1 font-bold text-primary">{{ selectedType?.label || '-' }}</p>
                                    </div>
                                    <div class="rounded-xl bg-card p-3">
                                        <p class="text-xs uppercase text-secondary">Empfaenger</p>
                                        <p class="mt-1 font-bold text-primary">{{ selectedRecipientLabel }}</p>
                                    </div>
                                    <div class="grid grid-cols-2 gap-3">
                                        <div class="rounded-xl bg-card p-3">
                                            <p class="text-xs uppercase text-secondary">Betrag</p>
                                            <p class="mt-1 font-bold text-primary">{{ form.amount || '0,00' }} EUR</p>
                                        </div>
                                        <div class="rounded-xl bg-card p-3">
                                            <p class="text-xs uppercase text-secondary">Status</p>
                                            <p class="mt-1 font-bold text-primary">{{ statusLabel(form.status) }}</p>
                                        </div>
                                    </div>
                                </div>
                            </aside>
                        </div>
                    </div>

                    <div class="flex shrink-0 flex-col-reverse gap-3 border-t border-border px-4 py-4 sm:flex-row sm:justify-end sm:px-6">
                        <button type="button" class="rounded-xl border border-border px-5 py-3 text-sm font-bold text-primary hover:bg-muted" :disabled="form.processing" @click="closeCreateModal">
                            Abbrechen
                        </button>
                        <button
                            type="submit"
                            class="inline-flex items-center justify-center gap-2 rounded-xl bg-buttonPrimary px-5 py-3 text-sm font-black text-buttonTextPrimary disabled:cursor-not-allowed disabled:opacity-50"
                            :disabled="form.processing"
                        >
                            <i class="las la-file-invoice text-lg"></i>
                            Rechnung erstellen
                        </button>
                    </div>
                </form>
            </div>
        </Teleport>
    </div>
</template>
