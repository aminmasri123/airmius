<script setup>
import AppLoadingState from '@/Components/UI/AppLoadingState.vue'
import { confirmDialog } from '@/services/dialogService'
import periodTranslations from '@/i18n/clubYearPeriodsLocalization.json'
import { computed, onMounted, ref } from 'vue'
import { useI18n } from 'vue-i18n'

const props = defineProps({ clubId: { type: [Number, String], required: true } })
const { locale } = useI18n({ useScope: 'global' })
const messages = computed(() => periodTranslations[locale.value] || periodTranslations.de)
const pt = (key) => messages.value[key] || periodTranslations.de[key] || key
const baseUrl = `/api/v1/clubs/${encodeURIComponent(props.clubId)}/year-periods`
const state = ref({ periods: [], can_manage: false, can_view_reports: false })
const loading = ref(true)
const saving = ref(false)
const reportLoading = ref(false)
const report = ref(null)
const error = ref('')
const emptyForm = () => ({ id: null, type: 'business', name: '', starts_on: '', ends_on: '' })
const form = ref(emptyForm())
const types = ['business', 'contribution', 'sport']
const canEdit = computed(() => state.value.can_edit === true || (!Object.hasOwn(state.value, 'can_edit') && state.value.can_manage === true))
const canDelete = computed(() => state.value.can_delete === true || (!Object.hasOwn(state.value, 'can_delete') && state.value.can_manage === true))
const periodsFor = (type) => state.value.periods.filter((period) => period.type === type)
const number = (value) => new Intl.NumberFormat(locale.value).format(Number(value || 0))
const money = (value) => new Intl.NumberFormat(locale.value, { style: 'currency', currency: 'EUR' }).format(Number(value || 0))
const apiError = (cause) => Object.values(cause?.response?.data?.errors || {}).flat()[0]
    || cause?.response?.data?.message || pt('saveError')
const loadPeriods = async () => {
    loading.value = true
    error.value = ''
    try {
        state.value = (await window.axios.get(baseUrl, { headers: { Accept: 'application/json' } })).data.data
    } catch (cause) {
        error.value = apiError(cause)
    } finally {
        loading.value = false
    }
}
const editPeriod = (period) => { form.value = { ...period } }
const savePeriod = async () => {
    saving.value = true
    error.value = ''
    try {
        const url = `${baseUrl}${form.value.id ? `/${form.value.id}` : ''}`
        await window.axios({ method: form.value.id ? 'put' : 'post', url, data: form.value, headers: { Accept: 'application/json' } })
        form.value = emptyForm()
        await loadPeriods()
    } catch (cause) {
        error.value = apiError(cause)
    } finally {
        saving.value = false
    }
}
const deletePeriod = async (period) => {
    if (!await confirmDialog({ title: pt('deleteTitle'), message: pt('deleteMessage'), confirmLabel: pt('delete') })) return
    saving.value = true
    error.value = ''
    try {
        await window.axios.delete(`${baseUrl}/${period.id}`, { headers: { Accept: 'application/json' } })
        await loadPeriods()
    } catch (cause) {
        error.value = apiError(cause)
    } finally {
        saving.value = false
    }
}
const loadReport = async (type, periodId) => {
    reportLoading.value = true
    error.value = ''
    try {
        report.value = (await window.axios.get(`${baseUrl}/report`, {
            params: { type, period_id: periodId },
            headers: { Accept: 'application/json' },
        })).data.data
    } catch (cause) {
        error.value = apiError(cause)
    } finally {
        reportLoading.value = false
    }
}

onMounted(loadPeriods)
</script>

<template>
    <section class="rounded-lg border border-border bg-card p-5">
        <div class="flex flex-wrap items-start justify-between gap-3"><div><h2 class="text-lg font-semibold text-primary">{{ pt('title') }}</h2><p class="mt-1 text-sm text-secondary">{{ pt('hint') }}</p></div><button v-if="error" type="button" class="rounded-lg border border-border px-3 py-2 text-xs font-semibold text-primary" @click="loadPeriods">{{ pt('retry') }}</button></div>
        <AppLoadingState v-if="loading" class="mt-4" :label="pt('loading')" inline />
        <p v-if="error" class="mt-3 rounded-lg border border-danger/30 bg-danger/10 p-3 text-sm text-danger" role="alert">{{ error }}</p>
        <div v-if="!loading" class="mt-5 grid gap-4 lg:grid-cols-3">
            <div v-for="type in types" :key="type">
                <h3 class="font-semibold text-primary">{{ pt(type) }}</h3>
                <div class="mt-3 space-y-2">
                    <article v-for="period in periodsFor(type)" :key="period.id" class="rounded-lg border border-border bg-bg p-3 text-sm">
                        <div class="flex items-start justify-between gap-2"><p class="font-semibold text-primary">{{ period.name }}</p><span class="text-xs text-secondary">{{ pt(period.status) }}</span></div>
                        <p class="mt-1 text-secondary">{{ period.starts_on }} – {{ period.ends_on }}</p>
                        <div v-if="canEdit || canDelete || state.can_view_reports" class="mt-3 flex flex-wrap gap-3"><button v-if="state.can_view_reports" type="button" class="text-xs font-semibold text-link" @click="loadReport(type, period.id)">{{ pt('report') }}</button><button v-if="canEdit && !period.finance_closed_at" type="button" class="text-xs font-semibold text-link" @click="editPeriod(period)">{{ pt('edit') }}</button><button v-if="canDelete && !period.finance_closed_at" type="button" class="text-xs font-semibold text-danger" @click="deletePeriod(period)">{{ pt('delete') }}</button></div>
                    </article>
                    <p v-if="!periodsFor(type).length" class="text-sm text-secondary">{{ pt('empty') }}</p>
                    <button v-if="state.can_view_reports" type="button" class="text-left text-xs font-semibold text-link" @click="loadReport(type, 'unassigned')">{{ pt('unassignedReport') }}</button>
                </div>
            </div>
        </div>
        <AppLoadingState v-if="reportLoading" class="mt-5" :label="pt('reportLoading')" inline />
        <div v-if="report && !reportLoading" class="mt-5 rounded-lg border border-border bg-bg p-4" role="status">
            <div class="flex items-start justify-between gap-3"><div><h3 class="font-semibold text-primary">{{ report.period?.name || pt('unassigned') }}</h3><p class="mt-1 text-xs text-secondary">{{ pt(report.type) }} · {{ report.period ? `${report.period.starts_on} – ${report.period.ends_on}` : pt('unassignedHint') }}</p></div><button type="button" class="text-xs font-semibold text-secondary" @click="report = null">{{ pt('close') }}</button></div>
            <div v-if="report.summary.invoices" class="mt-4 grid gap-2 sm:grid-cols-2 lg:grid-cols-4">
                <p class="rounded-lg bg-card p-3 text-sm text-primary"><span class="block text-xs text-secondary">{{ pt('invoiceCount') }}</span>{{ number(report.summary.invoices.total_count) }}</p>
                <p class="rounded-lg bg-card p-3 text-sm text-primary"><span class="block text-xs text-secondary">{{ pt('openAmount') }}</span>{{ money(report.summary.invoices.open_amount) }}</p>
                <p class="rounded-lg bg-card p-3 text-sm text-primary"><span class="block text-xs text-secondary">{{ pt('paidAmount') }}</span>{{ money(report.summary.invoices.paid_amount) }}</p>
                <p class="rounded-lg bg-card p-3 text-sm text-primary"><span class="block text-xs text-secondary">{{ pt('overdueCount') }}</span>{{ number(report.summary.invoices.overdue_count) }}</p>
            </div>
            <div v-if="report.summary.bank_transactions" class="mt-3 grid gap-2 sm:grid-cols-2 lg:grid-cols-4">
                <p class="rounded-lg bg-card p-3 text-sm text-primary"><span class="block text-xs text-secondary">{{ pt('bankCount') }}</span>{{ number(report.summary.bank_transactions.total_count) }}</p>
                <p class="rounded-lg bg-card p-3 text-sm text-primary"><span class="block text-xs text-secondary">{{ pt('credits') }}</span>{{ money(report.summary.bank_transactions.credit_amount) }}</p>
                <p class="rounded-lg bg-card p-3 text-sm text-primary"><span class="block text-xs text-secondary">{{ pt('debits') }}</span>{{ money(report.summary.bank_transactions.debit_amount) }}</p>
                <p class="rounded-lg bg-card p-3 text-sm text-primary"><span class="block text-xs text-secondary">{{ pt('net') }}</span>{{ money(report.summary.bank_transactions.net_amount) }}</p>
            </div>
            <div v-if="report.summary.finance_entries" class="mt-3 grid gap-2 sm:grid-cols-2 lg:grid-cols-4">
                <p class="rounded-lg bg-card p-3 text-sm text-primary"><span class="block text-xs text-secondary">{{ pt('entryCount') }}</span>{{ number(report.summary.finance_entries.total_count) }}</p>
                <p class="rounded-lg bg-card p-3 text-sm text-primary"><span class="block text-xs text-secondary">{{ pt('income') }}</span>{{ money(report.summary.finance_entries.income_amount) }}</p>
                <p class="rounded-lg bg-card p-3 text-sm text-primary"><span class="block text-xs text-secondary">{{ pt('expenses') }}</span>{{ money(report.summary.finance_entries.expense_amount) }}</p>
                <p class="rounded-lg bg-card p-3 text-sm text-primary"><span class="block text-xs text-secondary">{{ pt('net') }}</span>{{ money(report.summary.finance_entries.net_amount) }}</p>
            </div>
            <div v-if="report.summary.events" class="mt-3 grid gap-2 sm:grid-cols-3">
                <p class="rounded-lg bg-card p-3 text-sm text-primary"><span class="block text-xs text-secondary">{{ pt('eventCount') }}</span>{{ number(report.summary.events.total_count) }}</p>
                <p class="rounded-lg bg-card p-3 text-sm text-primary"><span class="block text-xs text-secondary">{{ pt('scheduledCount') }}</span>{{ number(report.summary.events.scheduled_count) }}</p>
                <p class="rounded-lg bg-card p-3 text-sm text-primary"><span class="block text-xs text-secondary">{{ pt('cancelledCount') }}</span>{{ number(report.summary.events.cancelled_count) }}</p>
            </div>
            <p v-if="report.selection === 'period'" class="mt-4 rounded-lg border border-warning/30 bg-warning/10 p-3 text-xs text-secondary">{{ pt('unassignedSeparate') }} {{ report.unassigned.invoices ? `${number(report.unassigned.invoices.total_count)} ${pt('invoices')}` : `${number(report.unassigned.events.total_count)} ${pt('events')}` }}<span v-if="report.unassigned.bank_transactions"> · {{ number(report.unassigned.bank_transactions.total_count) }} {{ pt('bankTransactions') }}</span><span v-if="report.unassigned.finance_entries"> · {{ number(report.unassigned.finance_entries.total_count) }} {{ pt('financeEntries') }}</span></p>
        </div>
        <form v-if="canEdit" class="mt-5 grid gap-3 border-t border-border pt-5 md:grid-cols-2 lg:grid-cols-5" @submit.prevent="savePeriod">
            <select v-model="form.type" :aria-label="pt('type')" class="rounded-lg border border-border bg-inputBg px-3 py-2 text-sm text-primary"><option v-for="type in types" :key="type" :value="type">{{ pt(type) }}</option></select>
            <input v-model="form.name" required maxlength="160" class="rounded-lg border border-border bg-inputBg px-3 py-2 text-sm text-primary" :placeholder="pt('name')" :aria-label="pt('name')">
            <input v-model="form.starts_on" required type="date" class="rounded-lg border border-border bg-inputBg px-3 py-2 text-sm text-primary" :aria-label="pt('startsOn')">
            <input v-model="form.ends_on" required type="date" class="rounded-lg border border-border bg-inputBg px-3 py-2 text-sm text-primary" :aria-label="pt('endsOn')">
            <div class="flex gap-2"><button class="rounded-lg bg-buttonPrimary px-3 py-2 text-xs font-semibold text-buttonTextPrimary" :disabled="saving">{{ form.id ? pt('save') : pt('add') }}</button><button v-if="form.id" type="button" class="px-3 py-2 text-xs font-semibold text-secondary" @click="form = emptyForm()">{{ pt('cancel') }}</button></div>
        </form>
    </section>
</template>
