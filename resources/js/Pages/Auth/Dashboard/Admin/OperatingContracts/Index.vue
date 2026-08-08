<script setup>
import AppLayout from '@/Components/Auth/Layouts/AppLayout.vue'
import { Head, Link, router, useForm } from '@inertiajs/vue3'
import { computed, ref } from 'vue'
import { confirmDialog } from '@/services/dialogService'
import { useI18n } from 'vue-i18n'

defineOptions({ layout: AppLayout })

const props = defineProps({
    contracts: { type: Object, required: true },
    summary: { type: Object, default: () => ({}) },
    filters: { type: Object, default: () => ({}) },
    options: { type: Object, default: () => ({}) },
    owners: { type: Array, default: () => [] },
    canManage: { type: Boolean, default: false },
})

const { t, te, locale, messages } = useI18n({ useScope: 'global' })
const localeCode = computed(() => ({ ar: 'ar-EG', fr: 'fr-FR', en: 'en-US', de: 'de-DE' })[locale.value] || 'de-DE')
const tAuto = (value, params = {}) => {
    const source = String(value ?? '').trim()
    if (!source || locale.value === 'de') return source

    const dictionary = messages.value?.[locale.value]?.auto || {}
    if (dictionary[source]) return dictionary[source]

    return te(source) ? t(source, params) : source
}

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
const allStatusOptions = computed(() => [{ value: 'all', label: tAuto('Alle Status') }, ...statusOptions.value])
const allCategoryOptions = computed(() => [{ value: 'all', label: tAuto('Alle Kategorien') }, ...categoryOptions.value])
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
        title: tAuto('Vertrag löschen'),
        message: t('operating_contracts.messages.delete', { name: contract.name }),
        confirmLabel: tAuto('Löschen'),
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
    if (days === null || days === undefined) return tAuto('-')
    if (days < 0) return t('operating_contracts.deadline.since', { days: Math.abs(days) })
    if (days === 0) return t('operating_contracts.deadline.today')
    if (days === 1) return t('operating_contracts.deadline.tomorrow')

    return t('operating_contracts.deadline.in_days', { days })
}

const statusLabel = (status) => ({
    active: tAuto('Aktiv'),
    paused: tAuto('Pausiert'),
    cancelled: tAuto('Gekündigt'),
    ended: tAuto('Beendet'),
}[status] || tAuto(status || '-'))

const categoryLabel = (category) => ({
    telecom: tAuto('WLAN & Internet'),
    mobile: tAuto('Handy & Mobilfunk'),
    leasing: tAuto('Leasing'),
    software: tAuto('Software'),
    hosting: tAuto('Hosting & Domains'),
    insurance: tAuto('Versicherung'),
    office: tAuto('Büro & Standort'),
    marketing: tAuto('Marketing'),
    finance: tAuto('Finanzen & Steuer'),
    service: tAuto('Dienstleister'),
    other: tAuto('Sonstiges'),
}[category] || tAuto(category || 'Sonstiges'))

const intervalLabel = (interval) => ({
    weekly: tAuto('Wöchentlich'),
    monthly: tAuto('Monatlich'),
    quarterly: tAuto('Quartalsweise'),
    yearly: tAuto('Jährlich'),
    one_time: tAuto('Einmalig'),
}[interval] || tAuto(interval || '-'))

const paymentMethodLabel = (method) => ({
    direct_debit: tAuto('Lastschrift'),
    bank_transfer: tAuto('Überweisung'),
    card: tAuto('Karte'),
    paypal: tAuto('PayPal'),
    invoice: tAuto('Rechnung'),
    cash: tAuto('Bar'),
    other: tAuto('Sonstiges'),
}[method] || tAuto(method || '-'))

const formatDate = (value) => {
    if (!value) return '-'

    const raw = String(value)
    const date = /^\d{4}-\d{2}-\d{2}$/.test(raw) ? new Date(`${raw}T12:00:00`) : new Date(raw)

    return Number.isNaN(date.getTime())
        ? '-'
        : new Intl.DateTimeFormat(localeCode.value, { day: '2-digit', month: '2-digit', year: 'numeric' }).format(date)
}

const formatMoney = (value, currency = 'EUR') => {
    const normalizedCurrency = /^[A-Z]{3}$/.test(String(currency || '').toUpperCase())
        ? String(currency).toUpperCase()
        : 'EUR'

    return new Intl.NumberFormat(localeCode.value, {
        style: 'currency',
        currency: normalizedCurrency,
    }).format(Number(value || 0))
}
const formatTotals = (totals, fallback = null) => {
    if (Array.isArray(totals) && totals.length) {
        return totals.map((total) => formatMoney(total.amount, total.currency)).join(' · ')
    }

    return fallback || formatMoney(0)
}

const ownerLabel = (owner) => {
    if (!owner) return tAuto('Nicht zugewiesen')

    return owner.email ? `${owner.name || owner.email} (${owner.email})` : owner.name
}

const optionLabel = (items, value, fallback = '-') => {
    if (items === categoryOptions.value) return categoryLabel(value)
    if (items === intervalOptions.value) return intervalLabel(value)
    if (items === statusOptions.value) return statusLabel(value)
    if (items === paymentMethodOptions.value) return paymentMethodLabel(value)

    return tAuto(items.find((item) => item.value === value)?.label || fallback)
}

const paginationLabel = (label) => {
    const plain = String(label || '')
        .replace(/<[^>]*>/g, '')
        .replace(/&laquo;/g, '«')
        .replace(/&raquo;/g, '»')
        .replace(/&amp;/g, '&')
        .trim()

    return tAuto(plain)
}
</script>

<template>
                    <Head :title="tAuto('Betriebskosten & Verträge')" />

    <div class="space-y-6">
        <section class="overflow-hidden rounded-2xl border-l-4 border-l-air-blue border-border bg-card">
            <div class="grid gap-5 p-5 lg:grid-cols-[1fr_auto] lg:items-center lg:p-6">
                <div>
                    <p class="text-xs font-semibold uppercase tracking-[0.18em] text-air-blue">{{ tAuto('Interne Kontrolle') }}</p>
                    <h1 class="mt-2 text-3xl font-black text-primary">{{ tAuto('Betriebskosten & Verträge') }}</h1>
                    <p class="mt-2 max-w-3xl text-sm leading-6 text-secondary">
                        {{ tAuto('WLAN, Handy, Leasing, Hosting, Software und Dienstleister mit Kosten, Fristen und nächsten Zahlungen im Blick.') }}
                    </p>
                </div>
                <button
                    v-if="canManage"
                    type="button"
                    class="inline-flex items-center justify-center gap-2 rounded-xl bg-buttonPrimary px-4 py-3 text-sm font-black text-buttonTextPrimary hover:bg-buttonPrimaryHover"
                    @click="openCreateModal"
                >
                    <i class="las la-plus-circle text-lg"></i>
                    {{ tAuto('Vertrag anlegen') }}
                </button>
            </div>
        </section>

        <section class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
            <div class="rounded-2xl border border-border bg-card p-5">
                    <p class="text-xs font-bold uppercase tracking-wide text-secondary">{{ tAuto('Aktive Verträge') }}</p>
                <p class="mt-3 text-3xl font-black text-primary">{{ summary.active_count || 0 }}</p>
            </div>
            <div class="rounded-2xl border border-border bg-card p-5">
                <p class="text-xs font-bold uppercase tracking-wide text-secondary">{{ tAuto('Fixkosten / Monat') }}</p>
                <p class="mt-3 text-3xl font-black text-primary">{{ formatTotals(summary.monthly_totals, summary.monthly_total) }}</p>
            </div>
            <div class="rounded-2xl border border-border bg-card p-5">
                <p class="text-xs font-bold uppercase tracking-wide text-secondary">{{ tAuto('Fixkosten / Jahr') }}</p>
                <p class="mt-3 text-3xl font-black text-primary">{{ formatTotals(summary.yearly_totals, summary.yearly_total) }}</p>
            </div>
            <div class="rounded-2xl border border-border bg-card p-5">
                <p class="text-xs font-bold uppercase tracking-wide text-secondary">{{ tAuto('Fristen & Zahlungen') }}</p>
                <p class="mt-3 text-3xl font-black text-primary">{{ (summary.due_soon_count || 0) + (summary.notice_soon_count || 0) }}</p>
                <p class="mt-1 text-xs text-secondary">{{ summary.due_soon_count || 0 }} {{ tAuto('Zahlungen') }}, {{ summary.notice_soon_count || 0 }} {{ tAuto('Fristen') }}</p>
            </div>
        </section>

        <section class="grid gap-5 xl:grid-cols-[minmax(0,1fr)_22rem]">
            <div class="space-y-5">
                <form class="rounded-2xl border border-border bg-card p-4" @submit.prevent="applyFilters">
                    <div class="grid gap-3 md:grid-cols-[11rem_13rem_minmax(0,1fr)_auto] md:items-end">
                        <label class="block">
                            <span class="text-sm font-semibold text-primary">{{ tAuto('Status') }}</span>
                            <select v-model="filterStatus" class="mt-1 w-full rounded-xl border-border bg-inputBg text-primary">
                                <option v-for="status in allStatusOptions" :key="status.value" :value="status.value">
                                    {{ status.value === 'all' ? status.label : statusLabel(status.value) }}
                                </option>
                            </select>
                        </label>
                        <label class="block">
                            <span class="text-sm font-semibold text-primary">{{ tAuto('Kategorie') }}</span>
                            <select v-model="filterCategory" class="mt-1 w-full rounded-xl border-border bg-inputBg text-primary">
                                <option v-for="category in allCategoryOptions" :key="category.value" :value="category.value">
                                    {{ category.value === 'all' ? category.label : categoryLabel(category.value) }}
                                </option>
                            </select>
                        </label>
                        <label class="block">
                            <span class="text-sm font-semibold text-primary">{{ tAuto('Suche') }}</span>
                            <input
                                v-model="filterSearch"
                                class="mt-1 w-full rounded-xl border-border bg-inputBg text-primary"
                                :placeholder="tAuto('Anbieter, Vertrag, Kundennummer ...')"
                            >
                        </label>
                        <div class="flex gap-2">
                            <button type="submit" class="rounded-xl bg-buttonPrimary px-4 py-2.5 text-sm font-black text-buttonTextPrimary">
                                {{ tAuto('Filtern') }}
                            </button>
                            <button type="button" class="rounded-xl border border-border px-4 py-2.5 text-sm font-bold text-primary hover:bg-muted" @click="resetFilters">
                                {{ tAuto('Reset') }}
                            </button>
                        </div>
                    </div>
                </form>

                <section class="overflow-hidden rounded-2xl border border-border bg-card">
                    <div class="flex flex-col gap-3 border-b border-border p-5 sm:flex-row sm:items-center sm:justify-between">
                        <div>
                            <p class="text-xs font-semibold uppercase tracking-[0.16em] text-air-blue">{{ tAuto('Vertragsliste') }}</p>
                            <h2 class="mt-1 text-lg font-black text-primary">{{ tAuto('Externe Abos und laufende Kosten') }}</h2>
                        </div>
                        <button
                            v-if="canManage"
                            type="button"
                            class="inline-flex items-center justify-center gap-2 rounded-xl border border-border px-4 py-2 text-sm font-bold text-primary hover:bg-muted"
                            @click="openCreateModal"
                        >
                            <i class="las la-plus text-lg"></i>
                            {{ tAuto('Neu') }}
                        </button>
                    </div>

                    <div class="hidden overflow-x-auto lg:block">
                        <table class="min-w-full text-left text-sm">
                            <thead class="bg-bg text-xs uppercase tracking-[0.12em] text-secondary">
                                <tr>
                                    <th class="px-5 py-3">{{ tAuto('Vertrag') }}</th>
                                    <th class="px-5 py-3">{{ tAuto('Kosten') }}</th>
                                    <th class="px-5 py-3">{{ tAuto('Termine') }}</th>
                                    <th class="px-5 py-3">{{ tAuto('Status') }}</th>
                                    <th class="px-5 py-3 text-right">{{ tAuto('Aktionen') }}</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-border">
                                <tr v-for="contract in rows" :key="contract.id" class="align-top hover:bg-muted/40">
                                    <td class="px-5 py-4">
                                        <p class="font-black text-primary">{{ contract.name }}</p>
                                        <p class="mt-1 text-sm text-secondary">{{ contract.vendor || tAuto('Kein Anbieter') }}</p>
                                        <p class="mt-1 text-xs text-secondary">{{ categoryLabel(contract.category) }}</p>
                                    </td>
                                    <td class="px-5 py-4">
                                        <p class="font-black text-primary">{{ contract.amount }}</p>
                                        <p class="text-xs text-secondary">{{ intervalLabel(contract.billing_interval) }}</p>
                                        <p class="mt-2 text-xs text-secondary">{{ tAuto('Monatlich:') }} {{ formatMoney(contract.raw_monthly_amount, contract.currency) }}</p>
                                    </td>
                                    <td class="px-5 py-4">
                                        <p class="font-bold text-primary">{{ tAuto('Zahlung:') }} {{ formatDate(contract.next_due_on) }}</p>
                                        <p class="text-xs" :class="deadlineClass(contract.days_until_next_due)">
                                            {{ deadlineLabel(contract.days_until_next_due) }}
                                        </p>
                                        <p class="mt-2 font-bold text-primary">{{ tAuto('Kündigung:') }} {{ formatDate(contract.notice_until_on) }}</p>
                                        <p class="text-xs" :class="deadlineClass(contract.days_until_notice)">
                                            {{ deadlineLabel(contract.days_until_notice) }}
                                        </p>
                                    </td>
                                    <td class="px-5 py-4">
                                        <span class="inline-flex rounded-full border px-3 py-1 text-xs font-black" :class="statusClass(contract.status)">
                                            {{ statusLabel(contract.status) }}
                                        </span>
                                        <p class="mt-2 text-xs text-secondary">{{ paymentMethodLabel(contract.payment_method) }}</p>
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
                                                {{ tAuto('Website') }}
                                            </a>
                                            <a
                                                v-if="contract.document_url"
                                                :href="contract.document_url"
                                                target="_blank"
                                                rel="noopener"
                                                class="rounded-lg border border-border px-3 py-2 text-xs font-bold text-primary hover:bg-muted"
                                            >
                                                {{ tAuto('Beleg') }}
                                            </a>
                                            <button
                                                v-if="contract.update_url"
                                                type="button"
                                                class="rounded-lg border border-border px-3 py-2 text-xs font-bold text-primary hover:bg-muted"
                                                @click="openEditModal(contract)"
                                            >
                                                {{ tAuto('Bearbeiten') }}
                                            </button>
                                            <button
                                                v-if="contract.delete_url"
                                                type="button"
                                                class="rounded-lg bg-error px-3 py-2 text-xs font-bold text-buttonTextPrimary hover:bg-error/90"
                                                @click="deleteContract(contract)"
                                            >
                                                {{ tAuto('Löschen') }}
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
                                    <p class="mt-1 text-sm text-secondary">{{ contract.vendor || categoryLabel(contract.category) }}</p>
                                </div>
                                <span class="shrink-0 rounded-full border px-3 py-1 text-xs font-black" :class="statusClass(contract.status)">
                                    {{ statusLabel(contract.status) }}
                                </span>
                            </div>
                            <div class="mt-4 grid grid-cols-2 gap-3 text-sm">
                                <div>
                                    <p class="text-xs uppercase text-secondary">{{ tAuto('Kosten') }}</p>
                                    <p class="mt-1 font-bold text-primary">{{ contract.amount }}</p>
                                    <p class="text-xs text-secondary">{{ intervalLabel(contract.billing_interval) }}</p>
                                </div>
                                <div>
                                    <p class="text-xs uppercase text-secondary">{{ tAuto('Nächste Zahlung') }}</p>
                                    <p class="mt-1 font-bold text-primary">{{ formatDate(contract.next_due_on) }}</p>
                                    <p class="text-xs" :class="deadlineClass(contract.days_until_next_due)">
                                        {{ deadlineLabel(contract.days_until_next_due) }}
                                    </p>
                                </div>
                            </div>
                            <div class="mt-4 flex flex-wrap gap-2">
                                <button v-if="contract.update_url" type="button" class="rounded-lg border border-border px-3 py-2 text-xs font-bold text-primary" @click="openEditModal(contract)">{{ tAuto('Bearbeiten') }}</button>
                                <button v-if="contract.delete_url" type="button" class="rounded-lg bg-error px-3 py-2 text-xs font-bold text-buttonTextPrimary" @click="deleteContract(contract)">{{ tAuto('Löschen') }}</button>
                            </div>
                        </article>
                    </div>

                    <p v-if="!rows.length" class="px-5 py-10 text-center text-sm text-secondary">
                        {{ tAuto('Noch keine passenden Verträge vorhanden.') }}
                    </p>

                    <div v-if="contracts.links?.length > 3" class="flex flex-wrap gap-2 border-t border-border px-5 py-4">
                        <Link
                            v-for="link in contracts.links"
                            :key="link.label"
                            :href="link.url || '#'"
                            preserve-scroll
                            class="rounded-lg border border-border px-3 py-2 text-sm"
                            :class="[link.active ? 'bg-buttonPrimary text-buttonTextPrimary' : 'text-primary hover:bg-muted', !link.url ? 'pointer-events-none opacity-40' : '']"
                        >
                            {{ paginationLabel(link.label) }}
                        </Link>
                    </div>
                </section>
            </div>

            <aside class="space-y-5">
                <section class="rounded-2xl border border-border bg-card p-5">
                    <p class="text-xs font-semibold uppercase tracking-[0.16em] text-air-blue">{{ tAuto('Nächste Termine') }}</p>
                    <div class="mt-4 space-y-3">
                        <article v-for="item in upcoming" :key="`${item.id}-${item.type}`" class="rounded-xl border border-border bg-inputBg p-3">
                            <div class="flex items-start justify-between gap-3">
                                <div class="min-w-0">
                                    <p class="truncate text-sm font-black text-primary">{{ item.name }}</p>
                                    <p class="truncate text-xs text-secondary">{{ item.vendor || tAuto(item.label) }}</p>
                                </div>
                                <span class="shrink-0 rounded-full bg-card px-2 py-1 text-[11px] font-bold text-secondary">
                                    {{ tAuto(item.label) }}
                                </span>
                            </div>
                            <div class="mt-3 flex items-center justify-between gap-3 text-sm">
                                <span class="font-bold text-primary">{{ formatDate(item.date) }}</span>
                                <span :class="['font-bold', deadlineClass(item.days)]">{{ deadlineLabel(item.days) }}</span>
                            </div>
                        </article>
                        <p v-if="!upcoming.length" class="text-sm text-secondary">{{ tAuto('Keine anstehenden Zahlungen oder Fristen.') }}</p>
                    </div>
                </section>

                <section class="rounded-2xl border border-border bg-card p-5">
                    <p class="text-xs font-semibold uppercase tracking-[0.16em] text-air-blue">{{ tAuto('Kosten nach Kategorie') }}</p>
                    <div class="mt-4 space-y-3">
                        <article v-for="category in categories" :key="category.category" class="rounded-xl border border-border bg-inputBg p-3">
                            <div class="flex items-center justify-between gap-3">
                                <p class="text-sm font-black text-primary">{{ categoryLabel(category.category) }}</p>
                                <span class="rounded-full bg-card px-2 py-1 text-xs font-bold text-secondary">{{ category.count }}</span>
                            </div>
                            <p class="mt-2 text-sm font-bold text-secondary">{{ formatTotals(category.monthly_totals, category.monthly_total) }} / {{ tAuto('Monat') }}</p>
                        </article>
                        <p v-if="!categories.length" class="text-sm text-secondary">{{ tAuto('Noch keine aktiven Kosten erfasst.') }}</p>
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
                            <p class="text-xs font-semibold uppercase tracking-[0.16em] text-air-blue">{{ tAuto('Betriebskosten') }}</p>
                            <h2 class="mt-1 text-xl font-black text-primary sm:text-2xl">
                                {{ editingContract ? tAuto('Vertrag bearbeiten') : tAuto('Vertrag anlegen') }}
                            </h2>
                            <p class="mt-1 text-sm text-secondary">{{ tAuto('Kosten, Zahlungsrhythmus, Laufzeit und Kündigungsfrist zentral erfassen.') }}</p>
                        </div>
                        <button
                            type="button"
                            class="inline-flex h-10 w-10 shrink-0 items-center justify-center rounded-xl border border-border text-secondary hover:bg-muted hover:text-primary"
                            :disabled="form.processing"
                            :aria-label="tAuto('Modal schließen')"
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
                                        <span class="text-sm font-semibold text-primary">{{ tAuto('Name') }}</span>
                                        <input v-model="form.name" class="mt-1 w-full rounded-xl border-border bg-card text-primary" :placeholder="tAuto('WLAN Büro, Handyvertrag, Fahrzeugleasing ...')" required>
                                        <p v-if="form.errors.name" class="mt-1 text-xs text-error">{{ form.errors.name }}</p>
                                    </label>
                                    <label class="block">
                                        <span class="text-sm font-semibold text-primary">{{ tAuto('Anbieter') }}</span>
                                        <input v-model="form.vendor" class="mt-1 w-full rounded-xl border-border bg-card text-primary" :placeholder="tAuto('Telekom, Vodafone, Leasingfirma ...')">
                                        <p v-if="form.errors.vendor" class="mt-1 text-xs text-error">{{ form.errors.vendor }}</p>
                                    </label>
                                    <label class="block">
                                        <span class="text-sm font-semibold text-primary">{{ tAuto('Kategorie') }}</span>
                                        <select v-model="form.category" class="mt-1 w-full rounded-xl border-border bg-card text-primary">
                                            <option v-for="category in categoryOptions" :key="category.value" :value="category.value">{{ categoryLabel(category.value) }}</option>
                                        </select>
                                        <p v-if="form.errors.category" class="mt-1 text-xs text-error">{{ form.errors.category }}</p>
                                    </label>
                                    <label class="block">
                                        <span class="text-sm font-semibold text-primary">{{ tAuto('Status') }}</span>
                                        <select v-model="form.status" class="mt-1 w-full rounded-xl border-border bg-card text-primary">
                                            <option v-for="status in statusOptions" :key="status.value" :value="status.value">{{ statusLabel(status.value) }}</option>
                                        </select>
                                        <p v-if="form.errors.status" class="mt-1 text-xs text-error">{{ form.errors.status }}</p>
                                    </label>
                                    <label class="block">
                                        <span class="text-sm font-semibold text-primary">{{ tAuto('Verantwortlich') }}</span>
                                        <select v-model="form.owner_user_id" class="mt-1 w-full rounded-xl border-border bg-card text-primary">
                                            <option value="">{{ tAuto('Nicht zugewiesen') }}</option>
                                            <option v-for="owner in owners" :key="owner.id" :value="owner.id">{{ ownerLabel(owner) }}</option>
                                        </select>
                                        <p v-if="form.errors.owner_user_id" class="mt-1 text-xs text-error">{{ form.errors.owner_user_id }}</p>
                                    </label>
                                </section>

                                <section class="grid gap-4 rounded-2xl border border-border bg-inputBg p-4 md:grid-cols-2 xl:grid-cols-4">
                                    <label class="block">
                                        <span class="text-sm font-semibold text-primary">{{ tAuto('Betrag') }}</span>
                                        <input v-model="form.amount" class="mt-1 w-full rounded-xl border-border bg-card text-primary" min="0" step="0.01" type="number" required>
                                        <p v-if="form.errors.amount" class="mt-1 text-xs text-error">{{ form.errors.amount }}</p>
                                    </label>
                                    <label class="block">
                                        <span class="text-sm font-semibold text-primary">{{ tAuto('Währung') }}</span>
                                        <input v-model="form.currency" class="mt-1 w-full rounded-xl border-border bg-card text-primary uppercase" maxlength="3" placeholder="EUR">
                                        <p v-if="form.errors.currency" class="mt-1 text-xs text-error">{{ form.errors.currency }}</p>
                                    </label>
                                    <label class="block">
                                        <span class="text-sm font-semibold text-primary">{{ tAuto('Rhythmus') }}</span>
                                        <select v-model="form.billing_interval" class="mt-1 w-full rounded-xl border-border bg-card text-primary">
                                            <option v-for="interval in intervalOptions" :key="interval.value" :value="interval.value">{{ intervalLabel(interval.value) }}</option>
                                        </select>
                                        <p v-if="form.errors.billing_interval" class="mt-1 text-xs text-error">{{ form.errors.billing_interval }}</p>
                                    </label>
                                    <label class="block">
                                        <span class="text-sm font-semibold text-primary">{{ tAuto('Zahlungsart') }}</span>
                                        <select v-model="form.payment_method" class="mt-1 w-full rounded-xl border-border bg-card text-primary">
                                            <option value="">{{ tAuto('Keine Angabe') }}</option>
                                            <option v-for="method in paymentMethodOptions" :key="method.value" :value="method.value">{{ paymentMethodLabel(method.value) }}</option>
                                        </select>
                                        <p v-if="form.errors.payment_method" class="mt-1 text-xs text-error">{{ form.errors.payment_method }}</p>
                                    </label>
                                </section>

                                <section class="grid gap-4 rounded-2xl border border-border bg-inputBg p-4 md:grid-cols-2 xl:grid-cols-5">
                                    <label class="block">
                                        <span class="text-sm font-semibold text-primary">{{ tAuto('Start') }}</span>
                                        <input v-model="form.starts_on" class="mt-1 w-full rounded-xl border-border bg-card text-primary" type="date">
                                        <p v-if="form.errors.starts_on" class="mt-1 text-xs text-error">{{ form.errors.starts_on }}</p>
                                    </label>
                                    <label class="block">
                                        <span class="text-sm font-semibold text-primary">{{ tAuto('Ende') }}</span>
                                        <input v-model="form.ends_on" class="mt-1 w-full rounded-xl border-border bg-card text-primary" type="date">
                                        <p v-if="form.errors.ends_on" class="mt-1 text-xs text-error">{{ form.errors.ends_on }}</p>
                                    </label>
                                    <label class="block">
                                        <span class="text-sm font-semibold text-primary">{{ tAuto('Nächste Zahlung') }}</span>
                                        <input v-model="form.next_due_on" class="mt-1 w-full rounded-xl border-border bg-card text-primary" type="date">
                                        <p v-if="form.errors.next_due_on" class="mt-1 text-xs text-error">{{ form.errors.next_due_on }}</p>
                                    </label>
                                    <label class="block">
                                        <span class="text-sm font-semibold text-primary">{{ tAuto('Kündigungsfrist Tage') }}</span>
                                        <input v-model="form.cancellation_period_days" class="mt-1 w-full rounded-xl border-border bg-card text-primary" min="0" step="1" type="number">
                                        <p v-if="form.errors.cancellation_period_days" class="mt-1 text-xs text-error">{{ form.errors.cancellation_period_days }}</p>
                                    </label>
                                    <label class="block">
                                        <span class="text-sm font-semibold text-primary">{{ tAuto('Kündigen bis') }}</span>
                                        <input v-model="form.notice_until_on" class="mt-1 w-full rounded-xl border-border bg-card text-primary" type="date">
                                        <p v-if="form.errors.notice_until_on" class="mt-1 text-xs text-error">{{ form.errors.notice_until_on }}</p>
                                    </label>
                                    <label class="flex items-center gap-3 rounded-xl border border-border bg-card p-3 md:col-span-2">
                                        <input v-model="form.auto_renews" type="checkbox" class="rounded border-border bg-inputBg text-air-blue">
                                        <span>
                                            <span class="block text-sm font-semibold text-primary">{{ tAuto('Automatische Verlängerung') }}</span>
                                            <span class="block text-xs text-secondary">{{ tAuto('Bei aktiven Abos und Leasingverträgen eingeschaltet lassen.') }}</span>
                                        </span>
                                    </label>
                                </section>

                                <section class="grid gap-4 rounded-2xl border border-border bg-inputBg p-4 md:grid-cols-2">
                                    <label class="block">
                                        <span class="text-sm font-semibold text-primary">{{ tAuto('Vertragsnummer') }}</span>
                                        <input v-model="form.contract_number" class="mt-1 w-full rounded-xl border-border bg-card text-primary">
                                        <p v-if="form.errors.contract_number" class="mt-1 text-xs text-error">{{ form.errors.contract_number }}</p>
                                    </label>
                                    <label class="block">
                                        <span class="text-sm font-semibold text-primary">{{ tAuto('Kunden-/Accountreferenz') }}</span>
                                        <input v-model="form.account_reference" class="mt-1 w-full rounded-xl border-border bg-card text-primary">
                                        <p v-if="form.errors.account_reference" class="mt-1 text-xs text-error">{{ form.errors.account_reference }}</p>
                                    </label>
                                    <label class="block">
                                        <span class="text-sm font-semibold text-primary">{{ tAuto('Kontakt E-Mail') }}</span>
                                        <input v-model="form.contact_email" class="mt-1 w-full rounded-xl border-border bg-card text-primary" type="email">
                                        <p v-if="form.errors.contact_email" class="mt-1 text-xs text-error">{{ form.errors.contact_email }}</p>
                                    </label>
                                    <label class="block">
                                        <span class="text-sm font-semibold text-primary">{{ tAuto('Website / Portal') }}</span>
                                        <input v-model="form.website" class="mt-1 w-full rounded-xl border-border bg-card text-primary" :placeholder="t('commerce.ui.url_placeholder')" type="url">
                                        <p v-if="form.errors.website" class="mt-1 text-xs text-error">{{ form.errors.website }}</p>
                                    </label>
                                    <label class="block md:col-span-2">
                                        <span class="text-sm font-semibold text-primary">{{ tAuto('Beleg-/Dokumentenlink') }}</span>
                                        <input v-model="form.document_url" class="mt-1 w-full rounded-xl border-border bg-card text-primary" :placeholder="tAuto('Ablagepfad oder Link')">
                                        <p v-if="form.errors.document_url" class="mt-1 text-xs text-error">{{ form.errors.document_url }}</p>
                                    </label>
                                    <label class="block md:col-span-2">
                                        <span class="text-sm font-semibold text-primary">{{ tAuto('Notizen') }}</span>
                                        <textarea v-model="form.notes" class="mt-1 min-h-28 w-full rounded-xl border-border bg-card text-primary" :placeholder="tAuto('Interne Hinweise, Konditionen, Kontaktweg, Besonderheiten ...')"></textarea>
                                        <p v-if="form.errors.notes" class="mt-1 text-xs text-error">{{ form.errors.notes }}</p>
                                    </label>
                                </section>
                            </div>

                            <aside class="rounded-2xl border border-border bg-inputBg p-5 xl:sticky xl:top-0 xl:self-start">
                                <p class="text-xs font-semibold uppercase tracking-[0.16em] text-air-blue">{{ tAuto('Vorschau') }}</p>
                                <h3 class="mt-2 text-lg font-black text-primary">{{ form.name || tAuto('Neuer Vertrag') }}</h3>
                                <div class="mt-4 space-y-3 text-sm">
                                    <div class="rounded-xl bg-card p-3">
                                        <p class="text-xs uppercase text-secondary">{{ tAuto('Kategorie') }}</p>
                                        <p class="mt-1 font-bold text-primary">{{ optionLabel(categoryOptions, form.category, '-') }}</p>
                                    </div>
                                    <div class="grid grid-cols-2 gap-3">
                                        <div class="rounded-xl bg-card p-3">
                                            <p class="text-xs uppercase text-secondary">{{ tAuto('Kosten') }}</p>
                                            <p class="mt-1 font-bold text-primary">{{ form.amount ? formatMoney(form.amount, form.currency) : formatMoney(0, form.currency) }}</p>
                                        </div>
                                        <div class="rounded-xl bg-card p-3">
                                            <p class="text-xs uppercase text-secondary">{{ tAuto('Rhythmus') }}</p>
                                            <p class="mt-1 font-bold text-primary">{{ optionLabel(intervalOptions, form.billing_interval, '-') }}</p>
                                        </div>
                                    </div>
                                    <div class="rounded-xl bg-card p-3">
                                        <p class="text-xs uppercase text-secondary">{{ tAuto('Nächste Zahlung') }}</p>
                                        <p class="mt-1 font-bold text-primary">{{ formatDate(form.next_due_on) }}</p>
                                    </div>
                                    <div class="rounded-xl bg-card p-3">
                                        <p class="text-xs uppercase text-secondary">{{ tAuto('Kündigen bis') }}</p>
                                        <p class="mt-1 font-bold text-primary">{{ form.notice_until_on ? formatDate(form.notice_until_on) : tAuto('Automatisch aus Ende minus Frist') }}</p>
                                    </div>
                                </div>
                            </aside>
                        </div>
                    </div>

                    <div class="flex shrink-0 flex-col-reverse gap-3 border-t border-border px-4 py-4 sm:flex-row sm:justify-end sm:px-6">
                        <button type="button" class="rounded-xl border border-border px-5 py-3 text-sm font-bold text-primary hover:bg-muted" :disabled="form.processing" @click="closeModal">
                            {{ tAuto('Abbrechen') }}
                        </button>
                        <button
                            type="submit"
                            class="inline-flex items-center justify-center gap-2 rounded-xl bg-buttonPrimary px-5 py-3 text-sm font-black text-buttonTextPrimary disabled:cursor-not-allowed disabled:opacity-50"
                            :disabled="form.processing"
                        >
                            <i class="las la-save text-lg"></i>
                            {{ editingContract ? tAuto('Speichern') : tAuto('Anlegen') }}
                        </button>
                    </div>
                </form>
            </div>
        </Teleport>
    </div>
</template>
