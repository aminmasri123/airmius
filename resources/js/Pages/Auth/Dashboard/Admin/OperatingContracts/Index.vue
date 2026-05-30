<script setup>
import AppLayout from '@/Components/Auth/Layouts/AppLayout.vue'
import { Head, Link, router, useForm } from '@inertiajs/vue3'
import { computed, ref } from 'vue'
import { confirmDialog } from '@/services/dialogService'

defineOptions({ layout: AppLayout })

const props = defineProps({
    contracts: { type: Object, required: true },
    summary: { type: Object, default: () => ({}) },
    filters: { type: Object, default: () => ({}) },
    options: { type: Object, default: () => ({}) },
    owners: { type: Array, default: () => [] },
    canManage: { type: Boolean, default: false },
})

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
        title: 'Vertrag loeschen',
        message: `Soll "${contract.name}" wirklich geloescht werden?`,
        confirmLabel: 'Loeschen',
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
</script>

<template>
    <Head title="Betriebskosten & Vertraege" />

    <div class="space-y-6">
        <section class="overflow-hidden rounded-2xl border-l-4 border-l-air-blue border-border bg-card">
            <div class="grid gap-5 p-5 lg:grid-cols-[1fr_auto] lg:items-center lg:p-6">
                <div>
                    <p class="text-xs font-semibold uppercase tracking-[0.18em] text-air-blue">Interne Kontrolle</p>
                    <h1 class="mt-2 text-3xl font-black text-primary">Betriebskosten & Vertraege</h1>
                    <p class="mt-2 max-w-3xl text-sm leading-6 text-secondary">
                        WLAN, Handy, Leasing, Hosting, Software und Dienstleister mit Kosten, Fristen und naechsten Zahlungen im Blick.
                    </p>
                </div>
                <button
                    v-if="canManage"
                    type="button"
                    class="inline-flex items-center justify-center gap-2 rounded-xl bg-buttonPrimary px-4 py-3 text-sm font-black text-buttonTextPrimary hover:bg-buttonPrimaryHover"
                    @click="openCreateModal"
                >
                    <i class="las la-plus-circle text-lg"></i>
                    Vertrag anlegen
                </button>
            </div>
        </section>

        <section class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
            <div class="rounded-2xl border border-border bg-card p-5">
                <p class="text-xs font-bold uppercase tracking-wide text-secondary">Aktive Vertraege</p>
                <p class="mt-3 text-3xl font-black text-primary">{{ summary.active_count || 0 }}</p>
            </div>
            <div class="rounded-2xl border border-border bg-card p-5">
                <p class="text-xs font-bold uppercase tracking-wide text-secondary">Fixkosten / Monat</p>
                <p class="mt-3 text-3xl font-black text-primary">{{ summary.monthly_total || '0,00 EUR' }}</p>
            </div>
            <div class="rounded-2xl border border-border bg-card p-5">
                <p class="text-xs font-bold uppercase tracking-wide text-secondary">Fixkosten / Jahr</p>
                <p class="mt-3 text-3xl font-black text-primary">{{ summary.yearly_total || '0,00 EUR' }}</p>
            </div>
            <div class="rounded-2xl border border-border bg-card p-5">
                <p class="text-xs font-bold uppercase tracking-wide text-secondary">Fristen & Zahlungen</p>
                <p class="mt-3 text-3xl font-black text-primary">{{ (summary.due_soon_count || 0) + (summary.notice_soon_count || 0) }}</p>
                <p class="mt-1 text-xs text-secondary">{{ summary.due_soon_count || 0 }} Zahlungen, {{ summary.notice_soon_count || 0 }} Fristen</p>
            </div>
        </section>

        <section class="grid gap-5 xl:grid-cols-[minmax(0,1fr)_22rem]">
            <div class="space-y-5">
                <form class="rounded-2xl border border-border bg-card p-4" @submit.prevent="applyFilters">
                    <div class="grid gap-3 md:grid-cols-[11rem_13rem_minmax(0,1fr)_auto] md:items-end">
                        <label class="block">
                            <span class="text-sm font-semibold text-primary">Status</span>
                            <select v-model="filterStatus" class="mt-1 w-full rounded-xl border-border bg-inputBg text-primary">
                                <option v-for="status in allStatusOptions" :key="status.value" :value="status.value">
                                    {{ status.label }}
                                </option>
                            </select>
                        </label>
                        <label class="block">
                            <span class="text-sm font-semibold text-primary">Kategorie</span>
                            <select v-model="filterCategory" class="mt-1 w-full rounded-xl border-border bg-inputBg text-primary">
                                <option v-for="category in allCategoryOptions" :key="category.value" :value="category.value">
                                    {{ category.label }}
                                </option>
                            </select>
                        </label>
                        <label class="block">
                            <span class="text-sm font-semibold text-primary">Suche</span>
                            <input
                                v-model="filterSearch"
                                class="mt-1 w-full rounded-xl border-border bg-inputBg text-primary"
                                placeholder="Anbieter, Vertrag, Kundennummer ..."
                            >
                        </label>
                        <div class="flex gap-2">
                            <button type="submit" class="rounded-xl bg-buttonPrimary px-4 py-2.5 text-sm font-black text-buttonTextPrimary">
                                Filtern
                            </button>
                            <button type="button" class="rounded-xl border border-border px-4 py-2.5 text-sm font-bold text-primary hover:bg-muted" @click="resetFilters">
                                Reset
                            </button>
                        </div>
                    </div>
                </form>

                <section class="overflow-hidden rounded-2xl border border-border bg-card">
                    <div class="flex flex-col gap-3 border-b border-border p-5 sm:flex-row sm:items-center sm:justify-between">
                        <div>
                            <p class="text-xs font-semibold uppercase tracking-[0.16em] text-air-blue">Vertragsliste</p>
                            <h2 class="mt-1 text-lg font-black text-primary">Externe Abos und laufende Kosten</h2>
                        </div>
                        <button
                            v-if="canManage"
                            type="button"
                            class="inline-flex items-center justify-center gap-2 rounded-xl border border-border px-4 py-2 text-sm font-bold text-primary hover:bg-muted"
                            @click="openCreateModal"
                        >
                            <i class="las la-plus text-lg"></i>
                            Neu
                        </button>
                    </div>

                    <div class="hidden overflow-x-auto lg:block">
                        <table class="min-w-full text-left text-sm">
                            <thead class="bg-bg text-xs uppercase tracking-[0.12em] text-secondary">
                                <tr>
                                    <th class="px-5 py-3">Vertrag</th>
                                    <th class="px-5 py-3">Kosten</th>
                                    <th class="px-5 py-3">Termine</th>
                                    <th class="px-5 py-3">Status</th>
                                    <th class="px-5 py-3 text-right">Aktionen</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-border">
                                <tr v-for="contract in rows" :key="contract.id" class="align-top hover:bg-muted/40">
                                    <td class="px-5 py-4">
                                        <p class="font-black text-primary">{{ contract.name }}</p>
                                        <p class="mt-1 text-sm text-secondary">{{ contract.vendor || 'Kein Anbieter' }}</p>
                                        <p class="mt-1 text-xs text-secondary">{{ contract.category_label }}</p>
                                    </td>
                                    <td class="px-5 py-4">
                                        <p class="font-black text-primary">{{ contract.amount }}</p>
                                        <p class="text-xs text-secondary">{{ contract.billing_interval_label }}</p>
                                        <p class="mt-2 text-xs text-secondary">Monatlich: {{ contract.monthly_amount }}</p>
                                    </td>
                                    <td class="px-5 py-4">
                                        <p class="font-bold text-primary">Zahlung: {{ contract.next_due_on || '-' }}</p>
                                        <p class="text-xs" :class="deadlineClass(contract.days_until_next_due)">
                                            {{ deadlineLabel(contract.days_until_next_due) }}
                                        </p>
                                        <p class="mt-2 font-bold text-primary">Kuendigung: {{ contract.notice_until_on || '-' }}</p>
                                        <p class="text-xs" :class="deadlineClass(contract.days_until_notice)">
                                            {{ deadlineLabel(contract.days_until_notice) }}
                                        </p>
                                    </td>
                                    <td class="px-5 py-4">
                                        <span class="inline-flex rounded-full border px-3 py-1 text-xs font-black" :class="statusClass(contract.status)">
                                            {{ contract.status_label }}
                                        </span>
                                        <p class="mt-2 text-xs text-secondary">{{ contract.payment_method_label }}</p>
                                        <p class="mt-1 text-xs text-secondary">{{ ownerLabel(contract.owner) }}</p>
                                    </td>
                                    <td class="px-5 py-4 text-right">
                                        <div class="flex flex-wrap justify-end gap-2">
                                            <a
                                                v-if="contract.website"
                                                :href="contract.website"
                                                target="_blank"
                                                rel="noopener"
                                                class="rounded-lg border border-border px-3 py-2 text-xs font-bold text-primary hover:bg-muted"
                                            >
                                                Website
                                            </a>
                                            <a
                                                v-if="contract.document_url"
                                                :href="contract.document_url"
                                                target="_blank"
                                                rel="noopener"
                                                class="rounded-lg border border-border px-3 py-2 text-xs font-bold text-primary hover:bg-muted"
                                            >
                                                Beleg
                                            </a>
                                            <button
                                                v-if="contract.update_url"
                                                type="button"
                                                class="rounded-lg border border-border px-3 py-2 text-xs font-bold text-primary hover:bg-muted"
                                                @click="openEditModal(contract)"
                                            >
                                                Bearbeiten
                                            </button>
                                            <button
                                                v-if="contract.delete_url"
                                                type="button"
                                                class="rounded-lg bg-error px-3 py-2 text-xs font-bold text-white hover:bg-error/90"
                                                @click="deleteContract(contract)"
                                            >
                                                Loeschen
                                            </button>
                                        </div>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>

                    <div class="grid gap-3 p-4 lg:hidden">
                        <article v-for="contract in rows" :key="contract.id" class="rounded-xl border border-border bg-inputBg p-4">
                            <div class="flex items-start justify-between gap-3">
                                <div class="min-w-0">
                                    <p class="truncate font-black text-primary">{{ contract.name }}</p>
                                    <p class="mt-1 text-sm text-secondary">{{ contract.vendor || contract.category_label }}</p>
                                </div>
                                <span class="shrink-0 rounded-full border px-3 py-1 text-xs font-black" :class="statusClass(contract.status)">
                                    {{ contract.status_label }}
                                </span>
                            </div>
                            <div class="mt-4 grid grid-cols-2 gap-3 text-sm">
                                <div>
                                    <p class="text-xs uppercase text-secondary">Kosten</p>
                                    <p class="mt-1 font-bold text-primary">{{ contract.amount }}</p>
                                    <p class="text-xs text-secondary">{{ contract.billing_interval_label }}</p>
                                </div>
                                <div>
                                    <p class="text-xs uppercase text-secondary">Naechste Zahlung</p>
                                    <p class="mt-1 font-bold text-primary">{{ contract.next_due_on || '-' }}</p>
                                    <p class="text-xs" :class="deadlineClass(contract.days_until_next_due)">
                                        {{ deadlineLabel(contract.days_until_next_due) }}
                                    </p>
                                </div>
                            </div>
                            <div class="mt-4 flex flex-wrap gap-2">
                                <button v-if="contract.update_url" type="button" class="rounded-lg border border-border px-3 py-2 text-xs font-bold text-primary" @click="openEditModal(contract)">Bearbeiten</button>
                                <button v-if="contract.delete_url" type="button" class="rounded-lg bg-error px-3 py-2 text-xs font-bold text-white" @click="deleteContract(contract)">Loeschen</button>
                            </div>
                        </article>
                    </div>

                    <p v-if="!rows.length" class="px-5 py-10 text-center text-sm text-secondary">
                        Noch keine passenden Vertraege vorhanden.
                    </p>

                    <div v-if="contracts.links?.length > 3" class="flex flex-wrap gap-2 border-t border-border px-5 py-4">
                        <Link
                            v-for="link in contracts.links"
                            :key="link.label"
                            :href="link.url || '#'"
                            preserve-scroll
                            class="rounded-lg border border-border px-3 py-2 text-sm"
                            :class="[link.active ? 'bg-buttonPrimary text-buttonTextPrimary' : 'text-primary hover:bg-muted', !link.url ? 'pointer-events-none opacity-40' : '']"
                            v-html="link.label"
                        />
                    </div>
                </section>
            </div>

            <aside class="space-y-5">
                <section class="rounded-2xl border border-border bg-card p-5">
                    <p class="text-xs font-semibold uppercase tracking-[0.16em] text-air-blue">Naechste Termine</p>
                    <div class="mt-4 space-y-3">
                        <article v-for="item in upcoming" :key="`${item.id}-${item.type}`" class="rounded-xl border border-border bg-inputBg p-3">
                            <div class="flex items-start justify-between gap-3">
                                <div class="min-w-0">
                                    <p class="truncate text-sm font-black text-primary">{{ item.name }}</p>
                                    <p class="truncate text-xs text-secondary">{{ item.vendor || item.label }}</p>
                                </div>
                                <span class="shrink-0 rounded-full bg-card px-2 py-1 text-[11px] font-bold text-secondary">
                                    {{ item.label }}
                                </span>
                            </div>
                            <div class="mt-3 flex items-center justify-between gap-3 text-sm">
                                <span class="font-bold text-primary">{{ item.date || '-' }}</span>
                                <span :class="['font-bold', deadlineClass(item.days)]">{{ deadlineLabel(item.days) }}</span>
                            </div>
                        </article>
                        <p v-if="!upcoming.length" class="text-sm text-secondary">Keine anstehenden Zahlungen oder Fristen.</p>
                    </div>
                </section>

                <section class="rounded-2xl border border-border bg-card p-5">
                    <p class="text-xs font-semibold uppercase tracking-[0.16em] text-air-blue">Kosten nach Kategorie</p>
                    <div class="mt-4 space-y-3">
                        <article v-for="category in categories" :key="category.category" class="rounded-xl border border-border bg-inputBg p-3">
                            <div class="flex items-center justify-between gap-3">
                                <p class="text-sm font-black text-primary">{{ category.label }}</p>
                                <span class="rounded-full bg-card px-2 py-1 text-xs font-bold text-secondary">{{ category.count }}</span>
                            </div>
                            <p class="mt-2 text-sm font-bold text-secondary">{{ category.monthly_total }} / Monat</p>
                        </article>
                        <p v-if="!categories.length" class="text-sm text-secondary">Noch keine aktiven Kosten erfasst.</p>
                    </div>
                </section>
            </aside>
        </section>

        <Teleport to="body">
            <div
                v-if="createModalOpen"
                class="fixed inset-0 z-[90] flex items-center justify-center bg-black/65 p-3 backdrop-blur-sm sm:p-4"
                @click.self="closeModal"
            >
                <form
                    class="flex max-h-[calc(100dvh-1.5rem)] w-full max-w-6xl flex-col overflow-hidden rounded-2xl border border-border bg-card shadow-2xl"
                    @submit.prevent="submit"
                >
                    <div class="flex shrink-0 items-start justify-between gap-4 border-b border-border px-4 py-4 sm:px-6">
                        <div class="min-w-0">
                            <p class="text-xs font-semibold uppercase tracking-[0.16em] text-air-blue">Betriebskosten</p>
                            <h2 class="mt-1 text-xl font-black text-primary sm:text-2xl">
                                {{ editingContract ? 'Vertrag bearbeiten' : 'Vertrag anlegen' }}
                            </h2>
                            <p class="mt-1 text-sm text-secondary">Kosten, Zahlungsrhythmus, Laufzeit und Kuendigungsfrist zentral erfassen.</p>
                        </div>
                        <button
                            type="button"
                            class="inline-flex h-10 w-10 shrink-0 items-center justify-center rounded-xl border border-border text-secondary hover:bg-muted hover:text-primary"
                            :disabled="form.processing"
                            aria-label="Modal schliessen"
                            @click="closeModal"
                        >
                            <i class="las la-times text-xl"></i>
                        </button>
                    </div>

                    <div class="min-h-0 flex-1 overflow-y-auto p-4 sm:p-6">
                        <div class="grid gap-5 xl:grid-cols-[minmax(0,1fr)_22rem]">
                            <div class="space-y-5">
                                <section class="grid gap-4 rounded-2xl border border-border bg-inputBg p-4 md:grid-cols-2 xl:grid-cols-3">
                                    <label class="block md:col-span-2">
                                        <span class="text-sm font-semibold text-primary">Name</span>
                                        <input v-model="form.name" class="mt-1 w-full rounded-xl border-border bg-card text-primary" placeholder="WLAN Buero, Handyvertrag, Fahrzeugleasing ..." required>
                                        <p v-if="form.errors.name" class="mt-1 text-xs text-error">{{ form.errors.name }}</p>
                                    </label>
                                    <label class="block">
                                        <span class="text-sm font-semibold text-primary">Anbieter</span>
                                        <input v-model="form.vendor" class="mt-1 w-full rounded-xl border-border bg-card text-primary" placeholder="Telekom, Vodafone, Leasingfirma ...">
                                        <p v-if="form.errors.vendor" class="mt-1 text-xs text-error">{{ form.errors.vendor }}</p>
                                    </label>
                                    <label class="block">
                                        <span class="text-sm font-semibold text-primary">Kategorie</span>
                                        <select v-model="form.category" class="mt-1 w-full rounded-xl border-border bg-card text-primary">
                                            <option v-for="category in categoryOptions" :key="category.value" :value="category.value">{{ category.label }}</option>
                                        </select>
                                        <p v-if="form.errors.category" class="mt-1 text-xs text-error">{{ form.errors.category }}</p>
                                    </label>
                                    <label class="block">
                                        <span class="text-sm font-semibold text-primary">Status</span>
                                        <select v-model="form.status" class="mt-1 w-full rounded-xl border-border bg-card text-primary">
                                            <option v-for="status in statusOptions" :key="status.value" :value="status.value">{{ status.label }}</option>
                                        </select>
                                        <p v-if="form.errors.status" class="mt-1 text-xs text-error">{{ form.errors.status }}</p>
                                    </label>
                                    <label class="block">
                                        <span class="text-sm font-semibold text-primary">Verantwortlich</span>
                                        <select v-model="form.owner_user_id" class="mt-1 w-full rounded-xl border-border bg-card text-primary">
                                            <option value="">Nicht zugewiesen</option>
                                            <option v-for="owner in owners" :key="owner.id" :value="owner.id">{{ ownerLabel(owner) }}</option>
                                        </select>
                                        <p v-if="form.errors.owner_user_id" class="mt-1 text-xs text-error">{{ form.errors.owner_user_id }}</p>
                                    </label>
                                </section>

                                <section class="grid gap-4 rounded-2xl border border-border bg-inputBg p-4 md:grid-cols-2 xl:grid-cols-4">
                                    <label class="block">
                                        <span class="text-sm font-semibold text-primary">Betrag</span>
                                        <input v-model="form.amount" class="mt-1 w-full rounded-xl border-border bg-card text-primary" min="0" step="0.01" type="number" required>
                                        <p v-if="form.errors.amount" class="mt-1 text-xs text-error">{{ form.errors.amount }}</p>
                                    </label>
                                    <label class="block">
                                        <span class="text-sm font-semibold text-primary">Waehrung</span>
                                        <input v-model="form.currency" class="mt-1 w-full rounded-xl border-border bg-card text-primary uppercase" maxlength="3" placeholder="EUR">
                                        <p v-if="form.errors.currency" class="mt-1 text-xs text-error">{{ form.errors.currency }}</p>
                                    </label>
                                    <label class="block">
                                        <span class="text-sm font-semibold text-primary">Rhythmus</span>
                                        <select v-model="form.billing_interval" class="mt-1 w-full rounded-xl border-border bg-card text-primary">
                                            <option v-for="interval in intervalOptions" :key="interval.value" :value="interval.value">{{ interval.label }}</option>
                                        </select>
                                        <p v-if="form.errors.billing_interval" class="mt-1 text-xs text-error">{{ form.errors.billing_interval }}</p>
                                    </label>
                                    <label class="block">
                                        <span class="text-sm font-semibold text-primary">Zahlungsart</span>
                                        <select v-model="form.payment_method" class="mt-1 w-full rounded-xl border-border bg-card text-primary">
                                            <option value="">Keine Angabe</option>
                                            <option v-for="method in paymentMethodOptions" :key="method.value" :value="method.value">{{ method.label }}</option>
                                        </select>
                                        <p v-if="form.errors.payment_method" class="mt-1 text-xs text-error">{{ form.errors.payment_method }}</p>
                                    </label>
                                </section>

                                <section class="grid gap-4 rounded-2xl border border-border bg-inputBg p-4 md:grid-cols-2 xl:grid-cols-5">
                                    <label class="block">
                                        <span class="text-sm font-semibold text-primary">Start</span>
                                        <input v-model="form.starts_on" class="mt-1 w-full rounded-xl border-border bg-card text-primary" type="date">
                                        <p v-if="form.errors.starts_on" class="mt-1 text-xs text-error">{{ form.errors.starts_on }}</p>
                                    </label>
                                    <label class="block">
                                        <span class="text-sm font-semibold text-primary">Ende</span>
                                        <input v-model="form.ends_on" class="mt-1 w-full rounded-xl border-border bg-card text-primary" type="date">
                                        <p v-if="form.errors.ends_on" class="mt-1 text-xs text-error">{{ form.errors.ends_on }}</p>
                                    </label>
                                    <label class="block">
                                        <span class="text-sm font-semibold text-primary">Naechste Zahlung</span>
                                        <input v-model="form.next_due_on" class="mt-1 w-full rounded-xl border-border bg-card text-primary" type="date">
                                        <p v-if="form.errors.next_due_on" class="mt-1 text-xs text-error">{{ form.errors.next_due_on }}</p>
                                    </label>
                                    <label class="block">
                                        <span class="text-sm font-semibold text-primary">Kuendigungsfrist Tage</span>
                                        <input v-model="form.cancellation_period_days" class="mt-1 w-full rounded-xl border-border bg-card text-primary" min="0" step="1" type="number">
                                        <p v-if="form.errors.cancellation_period_days" class="mt-1 text-xs text-error">{{ form.errors.cancellation_period_days }}</p>
                                    </label>
                                    <label class="block">
                                        <span class="text-sm font-semibold text-primary">Kuendigen bis</span>
                                        <input v-model="form.notice_until_on" class="mt-1 w-full rounded-xl border-border bg-card text-primary" type="date">
                                        <p v-if="form.errors.notice_until_on" class="mt-1 text-xs text-error">{{ form.errors.notice_until_on }}</p>
                                    </label>
                                    <label class="flex items-center gap-3 rounded-xl border border-border bg-card p-3 md:col-span-2">
                                        <input v-model="form.auto_renews" type="checkbox" class="rounded border-border bg-inputBg text-air-blue">
                                        <span>
                                            <span class="block text-sm font-semibold text-primary">Automatische Verlängerung</span>
                                            <span class="block text-xs text-secondary">Bei aktiven Abos und Leasingvertraegen eingeschaltet lassen.</span>
                                        </span>
                                    </label>
                                </section>

                                <section class="grid gap-4 rounded-2xl border border-border bg-inputBg p-4 md:grid-cols-2">
                                    <label class="block">
                                        <span class="text-sm font-semibold text-primary">Vertragsnummer</span>
                                        <input v-model="form.contract_number" class="mt-1 w-full rounded-xl border-border bg-card text-primary">
                                        <p v-if="form.errors.contract_number" class="mt-1 text-xs text-error">{{ form.errors.contract_number }}</p>
                                    </label>
                                    <label class="block">
                                        <span class="text-sm font-semibold text-primary">Kunden-/Accountreferenz</span>
                                        <input v-model="form.account_reference" class="mt-1 w-full rounded-xl border-border bg-card text-primary">
                                        <p v-if="form.errors.account_reference" class="mt-1 text-xs text-error">{{ form.errors.account_reference }}</p>
                                    </label>
                                    <label class="block">
                                        <span class="text-sm font-semibold text-primary">Kontakt E-Mail</span>
                                        <input v-model="form.contact_email" class="mt-1 w-full rounded-xl border-border bg-card text-primary" type="email">
                                        <p v-if="form.errors.contact_email" class="mt-1 text-xs text-error">{{ form.errors.contact_email }}</p>
                                    </label>
                                    <label class="block">
                                        <span class="text-sm font-semibold text-primary">Website / Portal</span>
                                        <input v-model="form.website" class="mt-1 w-full rounded-xl border-border bg-card text-primary" placeholder="https://..." type="url">
                                        <p v-if="form.errors.website" class="mt-1 text-xs text-error">{{ form.errors.website }}</p>
                                    </label>
                                    <label class="block md:col-span-2">
                                        <span class="text-sm font-semibold text-primary">Beleg-/Dokumentenlink</span>
                                        <input v-model="form.document_url" class="mt-1 w-full rounded-xl border-border bg-card text-primary" placeholder="Ablagepfad oder Link">
                                        <p v-if="form.errors.document_url" class="mt-1 text-xs text-error">{{ form.errors.document_url }}</p>
                                    </label>
                                    <label class="block md:col-span-2">
                                        <span class="text-sm font-semibold text-primary">Notizen</span>
                                        <textarea v-model="form.notes" class="mt-1 min-h-28 w-full rounded-xl border-border bg-card text-primary" placeholder="Interne Hinweise, Konditionen, Kontaktweg, Besonderheiten ..."></textarea>
                                        <p v-if="form.errors.notes" class="mt-1 text-xs text-error">{{ form.errors.notes }}</p>
                                    </label>
                                </section>
                            </div>

                            <aside class="rounded-2xl border border-border bg-inputBg p-5 xl:sticky xl:top-0 xl:self-start">
                                <p class="text-xs font-semibold uppercase tracking-[0.16em] text-air-blue">Vorschau</p>
                                <h3 class="mt-2 text-lg font-black text-primary">{{ form.name || 'Neuer Vertrag' }}</h3>
                                <div class="mt-4 space-y-3 text-sm">
                                    <div class="rounded-xl bg-card p-3">
                                        <p class="text-xs uppercase text-secondary">Kategorie</p>
                                        <p class="mt-1 font-bold text-primary">{{ optionLabel(categoryOptions, form.category, '-') }}</p>
                                    </div>
                                    <div class="grid grid-cols-2 gap-3">
                                        <div class="rounded-xl bg-card p-3">
                                            <p class="text-xs uppercase text-secondary">Kosten</p>
                                            <p class="mt-1 font-bold text-primary">{{ form.amount || '0,00' }} {{ form.currency || 'EUR' }}</p>
                                        </div>
                                        <div class="rounded-xl bg-card p-3">
                                            <p class="text-xs uppercase text-secondary">Rhythmus</p>
                                            <p class="mt-1 font-bold text-primary">{{ optionLabel(intervalOptions, form.billing_interval, '-') }}</p>
                                        </div>
                                    </div>
                                    <div class="rounded-xl bg-card p-3">
                                        <p class="text-xs uppercase text-secondary">Naechste Zahlung</p>
                                        <p class="mt-1 font-bold text-primary">{{ form.next_due_on || '-' }}</p>
                                    </div>
                                    <div class="rounded-xl bg-card p-3">
                                        <p class="text-xs uppercase text-secondary">Kuendigen bis</p>
                                        <p class="mt-1 font-bold text-primary">{{ form.notice_until_on || 'Automatisch aus Ende minus Frist' }}</p>
                                    </div>
                                </div>
                            </aside>
                        </div>
                    </div>

                    <div class="flex shrink-0 flex-col-reverse gap-3 border-t border-border px-4 py-4 sm:flex-row sm:justify-end sm:px-6">
                        <button type="button" class="rounded-xl border border-border px-5 py-3 text-sm font-bold text-primary hover:bg-muted" :disabled="form.processing" @click="closeModal">
                            Abbrechen
                        </button>
                        <button
                            type="submit"
                            class="inline-flex items-center justify-center gap-2 rounded-xl bg-buttonPrimary px-5 py-3 text-sm font-black text-buttonTextPrimary disabled:cursor-not-allowed disabled:opacity-50"
                            :disabled="form.processing"
                        >
                            <i class="las la-save text-lg"></i>
                            {{ editingContract ? 'Speichern' : 'Anlegen' }}
                        </button>
                    </div>
                </form>
            </div>
        </Teleport>
    </div>
</template>
