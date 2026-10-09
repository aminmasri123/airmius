<script setup>
import { computed, reactive, ref, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import Modal from '@/Components/Modal.vue'
import translations from '@/i18n/clubFinanceWorkspaceLocalization.json'

const props = defineProps({ clubId: { type: Number, required: true }, invoices: { type: Array, default: () => [] }, payments: { type: Array, default: () => [] } })
const emit = defineEmits(['changed', 'options'])
const { locale } = useI18n()
const c = key => translations[(locale.value || 'de').split('-')[0]]?.[key] || key
const data = ref({ teams: [], departments: [], projects: [], cost_centers: [], periods: [], accounts: [] })
const budgets = ref([])
const orders = ref([])
const view = ref('budgets')
const periodId = ref('')
const busy = ref(false)
const error = ref('')
const modal = ref('')
const form = reactive({})
let generation = 0
const labels = { club: 'Verein', department: 'Abteilung', team: 'Team', project: 'Projekt', draft: 'Entwurf', submitted: 'Eingereicht', approved: 'Genehmigt', rejected: 'Abgelehnt', archived: 'Archiviert', ordered: 'Bestellt', partially_received: 'Teilweise geliefert', received: 'Geliefert' }
const money = cents => new Intl.NumberFormat(locale.value || 'de', { style: 'currency', currency: 'EUR' }).format(Number(cents || 0) / 100)
const cents = value => Math.round(Number(value || 0) * 100)
const today = () => new Date().toISOString().slice(0, 10)
const filteredBudgets = computed(() => budgets.value.filter(b => !periodId.value || Number(b.club_year_period_id) === Number(periodId.value)))
const title = computed(() => ({ budget: 'Budget', account: 'Kasse / Bankkonto', transfer: 'Geldtransfer', order: 'Beschaffung', delivery: 'Wareneingang', pay: 'Zahlung erfassen', assign: 'Zuordnung', reference: 'Projekt / Kostenstelle', close: 'Jahresabschluss' })[modal.value] || '')
const teamName = id => data.value.teams.find(t => t.id === id)?.name || c('Verein')
const api = path => `/api/v1/clubs/${props.clubId}/${path}`
const failure = e => Object.values(e.response?.data?.errors || {}).flat().join(' ') || e.response?.data?.message || c('Die Aktion konnte nicht abgeschlossen werden.')

async function load() {
    const current = ++generation
    const clubId = props.clubId
    busy.value = true
    error.value = ''
    try {
        const metadata = await window.axios.get(`/api/v1/clubs/${clubId}/finance-workspace`)
        if (current !== generation) return
        if (!metadata.data.data.available) {
            data.value.available = false
            return
        }
        const results = await Promise.all([
            window.axios.get(`/api/v1/clubs/${clubId}/budgets`),
            window.axios.get(`/api/v1/clubs/${clubId}/procurements`),
        ])
        if (current !== generation) return
        data.value = metadata.data.data
        budgets.value = results[0].data.data.budgets
        orders.value = results[1].data.data.procurement_requests
        emit('options', { ...data.value, budgets: budgets.value })
    } catch (e) {
        if (current === generation) error.value = failure(e)
    } finally {
        if (current === generation) busy.value = false
    }
}

watch(() => props.clubId, () => {
    modal.value = ''
    periodId.value = ''
    data.value = { teams: [], departments: [], projects: [], cost_centers: [], periods: [], accounts: [] }
    budgets.value = []
    orders.value = []
    load()
}, { immediate: true })

function open(mode, row = null, extra = null) {
    Object.keys(form).forEach(key => delete form[key])
    error.value = ''
    if (mode === 'budget') Object.assign(form, {
        id: row?.id, name: row?.name || '', scope_type: row?.scope_type || 'club',
        club_year_period_id: row?.club_year_period_id || periodId.value || data.value.periods[0]?.id || '',
        parent_id: row?.parent_id || null, team_id: row?.team_id || null,
        club_department_id: row?.club_department_id || null, club_project_id: row?.club_project_id || null,
        project_name: row?.project_name || '', version: row?.version || 1,
        approval_status: row?.approval_status || 'draft', notes: row?.notes || '',
        responsible_user_id: row?.responsible_user_id || null,
        income: (row?.planned_income_cents || 0) / 100, expense: (row?.planned_expense_cents || 0) / 100,
    })
    if (mode === 'account') Object.assign(form, { name: '', type: 'cash', team_id: null, opening: 0, opened_on: today() })
    if (mode === 'transfer') Object.assign(form, { from_id: '', to_id: '', amount: '', booked_on: today(), reference: '', idempotency_key: crypto.randomUUID() })
    if (mode === 'order') Object.assign(form, { title: '', supplier: '', club_budget_id: null, items: [{ name: '', quantity_requested: 1, price: 0 }] })
    if (mode === 'delivery') Object.assign(form, { id: row.id, received_on: today(), reference: '', items: row.items.map(i => ({ id: i.id, name: i.name, quantity_received: 0, remaining: i.quantity_ordered - i.quantity_received })) })
    if (mode === 'pay') Object.assign(form, { id: row.id, receiptId: extra.id, paid_on: today(), account: 'bank', club_money_account_id: null, reference: extra.reference || '' })
    if (mode === 'assign') Object.assign(form, { id: row.id, kind: extra, team_id: row.team_id || null, club_budget_id: row.club_budget_id || null, club_department_id: row.club_department_id || null, club_project_id: row.club_project_id || null, club_cost_center_id: row.club_cost_center_id || null, club_money_account_id: row.club_money_account_id || null, account: row.method === 'cash' ? 'cash' : 'bank' })
    if (mode === 'reference') Object.assign(form, { name: '', code: '', kind: 'cost-center' })
    if (mode === 'close') Object.assign(form, { id: row.id, next_period_id: '', confirmed: false })
    modal.value = mode
}

async function action(method, path, payload) {
    if (busy.value) return false
    const clubId = props.clubId
    busy.value = true
    error.value = ''
    try {
        await window.axios[method](api(path), payload)
        if (clubId !== props.clubId) return false
        modal.value = ''
        await load()
        emit('changed')
        return true
    } catch (e) {
        if (clubId === props.clubId) error.value = failure(e)
        return false
    } finally {
        busy.value = false
    }
}

async function save() {
    if (modal.value === 'budget') {
        const payload = { ...form, planned_income_cents: cents(form.income), planned_expense_cents: cents(form.expense) }
        if (form.scope_type !== 'team') payload.team_id = null
        if (form.scope_type !== 'department') payload.club_department_id = null
        if (form.scope_type !== 'project') { payload.club_project_id = null; payload.project_name = null }
        else payload.project_name = data.value.projects.find(p => p.id === form.club_project_id)?.name || form.project_name
        await action(form.id ? 'put' : 'post', form.id ? `budgets/${form.id}` : 'budgets', payload)
    } else if (modal.value === 'account') await action('post', 'money-accounts', { ...form, opening_cents: cents(form.opening) })
    else if (modal.value === 'transfer') await action('post', 'money-transfers', { ...form, amount_cents: cents(form.amount) })
    else if (modal.value === 'order') await action('post', 'procurements', { ...form, status: 'submitted', items: form.items.map(i => ({ ...i, unit_price_cents: cents(i.price) })) })
    else if (modal.value === 'delivery') await action('post', `procurements/${form.id}/receipts`, { ...form, items: form.items.filter(i => Number(i.quantity_received) > 0) })
    else if (modal.value === 'pay') await action('post', `procurements/${form.id}/receipts/${form.receiptId}/payment`, form)
    else if (modal.value === 'assign') await action('put', `finance-scopes/${form.kind}/${form.id}`, form)
    else if (modal.value === 'reference') await action('post', `finance-references/${form.kind}`, form)
    else if (modal.value === 'close') await action('post', `year-periods/${form.id}/finance-close`, form)
}

defineExpose({ refresh: load })
</script>

<template>
    <section v-if="data.available" class="min-w-0 border-y border-border py-5">
        <div class="flex flex-wrap items-center justify-between gap-3">
            <h2 class="text-lg font-semibold text-primary">{{ c("Budgets & Teamkassen") }}</h2>
            <button type="button" :disabled="busy" :title="c('Aktualisieren')" :aria-label="c('Aktualisieren')" class="p-2 text-secondary disabled:opacity-50" @click="load"><i class="las la-sync text-xl"></i></button>
        </div>
        <div class="mt-3 flex flex-wrap items-center gap-2 border-b border-border">
            <button v-for="tab in [{ id: 'budgets', name: 'Budgets' }, { id: 'accounts', name: 'Kassen & Konten' }, { id: 'orders', name: 'Beschaffungen' }, { id: 'assignments', name: 'Zuordnungen' }, { id: 'closing', name: 'Jahresabschluss' }]" :key="tab.id" type="button" :class="view === tab.id ? 'border-air-blue text-air-blue' : 'border-transparent text-secondary'" class="border-b-2 px-3 py-2 text-sm font-semibold" @click="view = tab.id">{{ c(tab.name) }}</button>
        </div>
        <p v-if="error" role="alert" class="mt-3 text-sm text-error">{{ error }}</p>
        <div v-if="view === 'budgets'" class="mt-4">
            <div class="flex flex-wrap items-center gap-3">
                <select v-model="periodId" :aria-label="c('Geschäftsjahr')" class="rounded-lg border-border bg-inputBg text-sm text-primary"><option value="">{{ c("Alle Geschäftsjahre") }}</option><option v-for="p in data.periods" :key="p.id" :value="p.id">{{ p.name }}</option></select>
                <button v-if="data.can_manage" :disabled="busy" type="button" class="text-sm font-semibold text-air-blue" @click="open('budget')"><i class="las la-plus"></i> {{ c("Budget anlegen") }}</button>
            </div>
            <div class="mt-3 overflow-x-auto">
                <table class="w-full whitespace-nowrap text-left text-sm">
                    <thead class="text-xs text-secondary"><tr><th class="p-2">{{ c("Budget / Bereich") }}</th><th class="p-2">{{ c("Status") }}</th><th class="p-2 text-right">{{ c("Ausgabenplan") }}</th><th class="p-2 text-right">{{ c("Ausgegeben") }}</th><th class="p-2 text-right">{{ c("Reserviert") }}</th><th class="p-2 text-right">{{ c("Frei") }}</th><th class="p-2 text-right">{{ c("Einnahmen") }}</th><th class="p-2 text-right">{{ c("Offene Forderungen") }}</th><th class="p-2">{{ c("Aktionen") }}</th></tr></thead>
                    <tbody class="divide-y divide-border"><tr v-for="b in filteredBudgets" :key="b.id"><td class="p-2"><span class="font-semibold text-primary">{{ b.name }}</span><span class="block text-xs text-secondary">{{ b.team_name || b.department_name || b.project_name || c('Verein') }} · {{ b.year_period?.name }} · V{{ b.version }}</span></td><td class="p-2 text-secondary">{{ c(labels[b.approval_status]) }}</td><td class="p-2 text-right">{{ money(b.planned_expense_cents) }}</td><td class="p-2 text-right">{{ money(b.financial_report.actual_expense_cents) }}</td><td class="p-2 text-right">{{ money(b.financial_report.reserved_expense_cents) }}</td><td class="p-2 text-right font-semibold" :class="b.financial_report.remaining_budget_cents < 0 ? 'text-error' : 'text-air-green'">{{ money(b.financial_report.remaining_budget_cents) }}</td><td class="p-2 text-right">{{ money(b.financial_report.actual_income_cents) }}</td><td class="p-2 text-right">{{ money(b.financial_report.open_receivables_cents) }}</td><td class="p-2"><div class="flex gap-2"><button v-if="data.can_manage && (!['approved', 'archived'].includes(b.approval_status) || data.can_approve)" :disabled="busy" :title="c('Budget bearbeiten')" :aria-label="c('Budget bearbeiten')" @click="open('budget', b)"><i class="las la-pen text-lg"></i></button><button v-if="data.can_approve && b.approval_status === 'submitted'" :disabled="busy" :title="c('Genehmigen')" :aria-label="c('Genehmigen')" @click="action('put', `budgets/${b.id}/approval`, { approval_status: 'approved' })"><i class="las la-check text-lg text-air-green"></i></button><button v-if="data.can_approve && b.approval_status === 'submitted'" :disabled="busy" :title="c('Ablehnen')" :aria-label="c('Ablehnen')" @click="action('put', `budgets/${b.id}/approval`, { approval_status: 'rejected' })"><i class="las la-times text-lg text-error"></i></button></div></td></tr></tbody>
                </table>
            </div>
            <p v-if="!filteredBudgets.length && !busy" class="mt-3 text-sm text-secondary">{{ c("Keine Budgets vorhanden.") }}</p>
        </div>
        <div v-if="view === 'accounts'" class="mt-4">
            <div v-if="data.can_manage" class="flex flex-wrap gap-4"><button :disabled="busy" type="button" class="text-sm font-semibold text-air-blue" @click="open('account')"><i class="las la-plus"></i> {{ c("Kasse / Konto anlegen") }}</button><button :disabled="busy || data.accounts.length < 2" type="button" class="text-sm font-semibold text-air-blue disabled:opacity-50" @click="open('transfer')"><i class="las la-exchange-alt"></i> {{ c("Transfer") }}</button></div>
            <table class="mt-3 w-full text-left text-sm"><thead class="text-xs text-secondary"><tr><th class="p-2">{{ c("Kasse / Konto") }}</th><th class="p-2">{{ c("Bereich") }}</th><th class="p-2">{{ c("Art") }}</th><th class="p-2 text-right">{{ c("Bestand") }}</th></tr></thead><tbody class="divide-y divide-border"><tr v-for="a in data.accounts" :key="a.id"><td class="p-2 font-semibold text-primary">{{ a.name }}</td><td class="p-2 text-secondary">{{ teamName(a.team_id) }}</td><td class="p-2 text-secondary">{{ a.type === 'cash' ? c('Bar') : c('Bank') }}</td><td class="p-2 text-right font-semibold">{{ money(a.balance_cents) }}</td></tr></tbody></table>
            <p v-if="!data.accounts.length && !busy" class="mt-3 text-sm text-secondary">{{ c("Keine Kassen oder Konten angelegt.") }}</p>
        </div>
        <div v-if="view === 'orders'" class="mt-4">
            <button v-if="data.can_manage" :disabled="busy" type="button" class="text-sm font-semibold text-air-blue" @click="open('order')"><i class="las la-plus"></i> {{ c("Beschaffung beantragen") }}</button>
            <div v-for="o in orders" :key="o.id" class="border-b border-border py-4">
                <div class="flex flex-wrap items-center justify-between gap-3"><div><h3 class="font-semibold text-primary">{{ o.title }}</h3><p class="text-xs text-secondary">{{ o.budget_name || c('Verein') }} · {{ o.supplier }} · {{ c(labels[o.status] || o.status) }} · {{ money(o.estimated_total_cents) }}</p></div><div class="flex flex-wrap gap-3 text-sm"><template v-if="data.can_approve && o.can_approve !== false && o.status === 'submitted'"><button :disabled="busy" @click="action('put', `procurements/${o.id}/approval`, { status: 'approved' })">{{ c("Genehmigen") }}</button><button :disabled="busy" @click="action('put', `procurements/${o.id}/approval`, { status: 'rejected' })">{{ c("Ablehnen") }}</button></template><button v-if="data.can_manage && o.status === 'approved'" :disabled="busy" @click="action('post', `procurements/${o.id}/order`, {})">{{ c("Bestellen") }}</button><button v-if="data.can_manage && ['ordered', 'partially_received'].includes(o.status)" :disabled="busy" @click="open('delivery', o)">{{ c("Wareneingang") }}</button></div></div>
                <div v-for="r in o.receipts" :key="r.id" class="mt-2 flex flex-wrap items-center justify-between gap-2 text-sm"><span>{{ r.received_on }} · {{ r.reference || `#${r.id}` }} · {{ money(r.total_cents) }}</span><span v-if="r.paid" class="text-air-green">{{ c('Bezahlt') }} · {{ r.paid_on }}</span><button v-else-if="data.can_manage" :disabled="busy" class="text-air-blue" @click="open('pay', o, r)">{{ c("Zahlung erfassen") }}</button><span v-else class="text-secondary">{{ c("Unbezahlt") }}</span></div>
            </div>
            <p v-if="!orders.length && !busy" class="mt-3 text-sm text-secondary">{{ c("Keine Beschaffungen vorhanden.") }}</p>
        </div>
        <div v-if="view === 'assignments'" class="mt-4 space-y-4">
            <button v-if="data.can_manage" :disabled="busy" type="button" class="text-sm font-semibold text-air-blue" @click="open('reference')"><i class="las la-plus"></i> {{ c("Projekt / Kostenstelle anlegen") }}</button>
            <div v-for="group in [{ kind: 'invoice', title: 'Rechnungen', rows: invoices }, { kind: 'payment', title: 'Zahlungen', rows: payments }]" :key="group.kind"><h3 class="text-sm font-semibold text-primary">{{ c(group.title) }}</h3><div v-for="row in group.rows" :key="row.id" class="flex items-center justify-between gap-3 border-b border-border py-2 text-sm"><div class="min-w-0"><span class="break-words">{{ row.number || row.receipt_number || `#${row.id}` }} · {{ row.title || row.reference || row.purpose }}</span><span class="block text-xs text-secondary">{{ row.team_id ? teamName(row.team_id) : row.club_budget_id ? budgets.find(b => b.id === row.club_budget_id)?.name : c('Verein / keine eigene Zuordnung') }}</span></div><button v-if="data.can_manage" :disabled="busy" :title="c('Zuordnung bearbeiten')" :aria-label="c('Zuordnung bearbeiten')" class="shrink-0 p-2" @click="open('assign', row, group.kind)"><i class="las la-pen text-lg"></i></button></div></div>
        </div>
        <div v-if="view === 'closing'" class="mt-4 divide-y divide-border">
            <div v-for="p in data.periods" :key="p.id" class="flex flex-wrap items-center justify-between gap-3 py-3 text-sm">
                <div><span class="font-semibold text-primary">{{ p.name }}</span><span class="block text-xs text-secondary">{{ p.starts_on.slice(0, 10) }} · {{ p.ends_on.slice(0, 10) }}</span></div>
                <span v-if="p.finance_closed_at" class="text-air-green">{{ c('Abgeschlossen') }} · {{ money(p.finance_closing_snapshot?.total_cents) }}</span>
                <button v-else-if="data.can_close && p.ends_on.slice(0, 10) < today()" :disabled="busy" class="text-air-blue" @click="open('close', p)">{{ c('Abschließen') }}</button>
            </div>
        </div>
        <Modal :show="Boolean(modal)" max-width="2xl" @close="modal = ''">
            <form class="max-h-[calc(100dvh-6rem)] space-y-4 overflow-y-auto rounded-lg bg-card p-5" @submit.prevent="save">
                <h3 class="pr-8 text-lg font-semibold text-primary">{{ c(title) }}</h3>
                <p v-if="error" role="alert" class="text-sm text-error">{{ error }}</p>
                <template v-if="modal === 'close'">
                    <p class="text-sm text-secondary">{{ c('Buchungen dieses Jahres werden gesperrt. Der Saldo wird ohne neue Einnahmen ins Folgejahr übernommen.') }}</p>
                    <label class="block text-sm text-secondary">{{ c('Folgejahr') }}<select v-model="form.next_period_id" required class="mt-1 w-full rounded-lg border-border bg-inputBg text-primary"><option value="" disabled>{{ c('Auswählen') }}</option><option v-for="p in data.periods.filter(p => p.id !== form.id && !p.finance_closed_at && p.starts_on.slice(0, 10) > data.periods.find(x => x.id === form.id)?.ends_on.slice(0, 10))" :key="p.id" :value="p.id">{{ p.name }}</option></select></label>
                    <label class="flex min-h-11 items-center gap-2 text-sm text-primary"><input v-model="form.confirmed" class="h-4 min-h-0 w-4 shrink-0" type="checkbox" required>{{ c('Abschluss bestätigen') }}</label>
                </template>
                <div class="grid grid-cols-1 gap-3 sm:grid-cols-2">
                    <label v-if="['budget', 'account', 'reference'].includes(modal)" class="text-sm text-secondary">{{ c("Name") }}<input v-model="form.name" required maxlength="160" class="mt-1 w-full rounded-lg border-border bg-inputBg text-primary"></label>
                    <template v-if="modal === 'reference'"><label class="text-sm text-secondary">{{ c("Art") }}<select v-model="form.kind" class="mt-1 w-full rounded-lg border-border bg-inputBg text-primary"><option value="cost-center">{{ c("Kostenstelle") }}</option><option value="project">{{ c("Projekt") }}</option></select></label><label class="text-sm text-secondary">{{ c("Kennung") }}<input v-model="form.code" required maxlength="40" class="mt-1 w-full rounded-lg border-border bg-inputBg text-primary"></label></template>
                    <template v-if="modal === 'budget'">
                        <label class="text-sm text-secondary">{{ c("Geschäftsjahr") }}<select v-model="form.club_year_period_id" required class="mt-1 w-full rounded-lg border-border bg-inputBg text-primary"><option value="" disabled>{{ c("Auswählen") }}</option><option v-for="p in data.periods" :key="p.id" :value="p.id">{{ p.name }}</option></select></label>
                        <label class="text-sm text-secondary">{{ c("Bereich") }}<select v-model="form.scope_type" class="mt-1 w-full rounded-lg border-border bg-inputBg text-primary"><option v-for="s in ['club', 'department', 'team', 'project']" :key="s" :value="s">{{ c(labels[s]) }}</option></select></label>
                        <label class="text-sm text-secondary">{{ c("Übergeordnetes Budget") }}<select v-model="form.parent_id" class="mt-1 w-full rounded-lg border-border bg-inputBg text-primary"><option :value="null">{{ c("Keines") }}</option><option v-for="b in budgets.filter(b => b.id !== form.id && b.club_year_period_id === Number(form.club_year_period_id))" :key="b.id" :value="b.id">{{ b.name }}</option></select></label>
                        <label class="text-sm text-secondary">{{ c("Geplante Einnahmen (EUR)") }}<input v-model="form.income" type="number" min="0" step="0.01" required class="mt-1 w-full rounded-lg border-border bg-inputBg text-primary"></label>
                        <label class="text-sm text-secondary">{{ c("Ausgabenbudget (EUR)") }}<input v-model="form.expense" type="number" min="0" step="0.01" required class="mt-1 w-full rounded-lg border-border bg-inputBg text-primary"></label>
                        <label class="text-sm text-secondary">{{ c("Version") }}<input v-model.number="form.version" type="number" min="1" max="999" required class="mt-1 w-full rounded-lg border-border bg-inputBg text-primary"></label>
                        <label class="text-sm text-secondary">{{ c("Status") }}<select v-model="form.approval_status" class="mt-1 w-full rounded-lg border-border bg-inputBg text-primary"><option v-for="s in data.can_approve ? ['draft', 'submitted', 'approved', 'rejected', 'archived'] : ['draft', 'submitted']" :key="s" :value="s">{{ c(labels[s]) }}</option></select></label>
                    </template>
                    <label v-if="modal === 'account'" class="text-sm text-secondary">{{ c("Art") }}<select v-model="form.type" class="mt-1 w-full rounded-lg border-border bg-inputBg text-primary"><option value="cash">{{ c("Barkasse") }}</option><option value="bank">{{ c("Bankkonto") }}</option></select></label>
                    <label v-if="modal === 'account'" class="text-sm text-secondary">{{ c("Anfangsbestand (EUR)") }}<input v-model="form.opening" type="number" step="0.01" required class="mt-1 w-full rounded-lg border-border bg-inputBg text-primary"></label>
                    <label v-if="modal === 'account'" class="text-sm text-secondary">{{ c("Stichtag") }}<input v-model="form.opened_on" type="date" required class="mt-1 w-full rounded-lg border-border bg-inputBg text-primary"></label>
                    <label v-if="['assign', 'account'].includes(modal) || (modal === 'budget' && form.scope_type === 'team')" class="text-sm text-secondary">{{ c("Team") }}<select v-model="form.team_id" class="mt-1 w-full rounded-lg border-border bg-inputBg text-primary"><option :value="null">{{ c("Verein") }}</option><option v-for="t in data.teams" :key="t.id" :value="t.id">{{ t.name }}</option></select></label>
                    <label v-if="modal === 'assign' || (modal === 'budget' && form.scope_type === 'department')" class="text-sm text-secondary">{{ c("Abteilung") }}<select v-model="form.club_department_id" class="mt-1 w-full rounded-lg border-border bg-inputBg text-primary"><option :value="null">{{ c("Keine") }}</option><option v-for="d in data.departments" :key="d.id" :value="d.id">{{ d.name }}</option></select></label>
                    <label v-if="modal === 'assign' || (modal === 'budget' && form.scope_type === 'project')" class="text-sm text-secondary">{{ c("Projekt") }}<select v-model="form.club_project_id" class="mt-1 w-full rounded-lg border-border bg-inputBg text-primary"><option :value="null">{{ c("Keines") }}</option><option v-for="p in data.projects" :key="p.id" :value="p.id">{{ p.name }}</option></select></label>
                    <label v-if="modal === 'budget' && form.scope_type === 'project' && !form.club_project_id" class="text-sm text-secondary">{{ c("Projektname") }}<input v-model="form.project_name" required maxlength="160" class="mt-1 w-full rounded-lg border-border bg-inputBg text-primary"></label>
                    <label v-if="['assign', 'order'].includes(modal)" class="text-sm text-secondary">{{ c("Budget") }}<select v-model="form.club_budget_id" class="mt-1 w-full rounded-lg border-border bg-inputBg text-primary"><option :value="null">{{ c("Keines") }}</option><option v-for="b in budgets.filter(b => modal !== 'order' || b.approval_status === 'approved')" :key="b.id" :value="b.id">{{ b.name }} · {{ b.year_period?.name }}</option></select></label>
                    <label v-if="modal === 'assign'" class="text-sm text-secondary">{{ c("Kostenstelle") }}<select v-model="form.club_cost_center_id" class="mt-1 w-full rounded-lg border-border bg-inputBg text-primary"><option :value="null">{{ c("Keine") }}</option><option v-for="c in data.cost_centers" :key="c.id" :value="c.id">{{ c.code }} · {{ c.name }}</option></select></label>
                    <label v-if="modal === 'assign' && form.kind === 'payment'" class="text-sm text-secondary">{{ c("Kasse / Konto") }}<select v-model="form.club_money_account_id" class="mt-1 w-full rounded-lg border-border bg-inputBg text-primary"><option :value="null">{{ c("Allgemein") }}</option><option v-for="a in data.accounts.filter(a => a.type === form.account)" :key="a.id" :value="a.id">{{ a.name }}</option></select></label>
                    <template v-if="modal === 'transfer'"><label v-for="field in [{ id: 'from_id', name: 'Von' }, { id: 'to_id', name: 'Nach' }]" :key="field.id" class="text-sm text-secondary">{{ c(field.name) }}<select v-model="form[field.id]" required class="mt-1 w-full rounded-lg border-border bg-inputBg text-primary"><option value="" disabled>{{ c("Auswählen") }}</option><option v-for="a in data.accounts" :key="a.id" :value="a.id">{{ a.name }}</option></select></label><label class="text-sm text-secondary">{{ c("Betrag (EUR)") }}<input v-model="form.amount" type="number" min="0.01" step="0.01" required class="mt-1 w-full rounded-lg border-border bg-inputBg text-primary"></label><label class="text-sm text-secondary">{{ c("Datum") }}<input v-model="form.booked_on" type="date" required class="mt-1 w-full rounded-lg border-border bg-inputBg text-primary"></label></template>
                    <template v-if="modal === 'order'"><label class="text-sm text-secondary">{{ c("Titel") }}<input v-model="form.title" required maxlength="180" class="mt-1 w-full rounded-lg border-border bg-inputBg text-primary"></label><label class="text-sm text-secondary">{{ c("Lieferant") }}<input v-model="form.supplier" maxlength="180" class="mt-1 w-full rounded-lg border-border bg-inputBg text-primary"></label></template>
                    <label v-if="modal === 'delivery'" class="text-sm text-secondary">{{ c("Lieferdatum") }}<input v-model="form.received_on" type="date" required class="mt-1 w-full rounded-lg border-border bg-inputBg text-primary"></label>
                    <template v-if="modal === 'pay'"><label class="text-sm text-secondary">{{ c("Zahlungsdatum") }}<input v-model="form.paid_on" type="date" required class="mt-1 w-full rounded-lg border-border bg-inputBg text-primary"></label><label class="text-sm text-secondary">{{ c("Zahlart") }}<select v-model="form.account" class="mt-1 w-full rounded-lg border-border bg-inputBg text-primary"><option value="bank">{{ c("Bank") }}</option><option value="cash">{{ c("Bar") }}</option></select></label><label class="text-sm text-secondary">{{ c("Kasse / Konto") }}<select v-model="form.club_money_account_id" class="mt-1 w-full rounded-lg border-border bg-inputBg text-primary"><option :value="null">{{ c("Allgemein") }}</option><option v-for="a in data.accounts.filter(a => a.type === form.account)" :key="a.id" :value="a.id">{{ a.name }}</option></select></label></template>
                    <label v-if="['transfer', 'pay', 'delivery'].includes(modal)" class="text-sm text-secondary">{{ c("Referenz") }}<input v-model="form.reference" maxlength="120" class="mt-1 w-full rounded-lg border-border bg-inputBg text-primary"></label>
                </div>
                <template v-if="modal === 'order'"><div v-for="(item, index) in form.items" :key="index" class="grid grid-cols-1 items-end gap-2 sm:grid-cols-[1fr_90px_110px_32px]"><label class="text-sm text-secondary">{{ c("Artikel") }}<input v-model="item.name" required class="mt-1 w-full rounded-lg border-border bg-inputBg text-primary"></label><label class="text-sm text-secondary">{{ c("Anzahl") }}<input v-model.number="item.quantity_requested" type="number" min="1" required class="mt-1 w-full rounded-lg border-border bg-inputBg text-primary"></label><label class="text-sm text-secondary">{{ c("Stückpreis (EUR)") }}<input v-model="item.price" type="number" min="0" step="0.01" required class="mt-1 w-full rounded-lg border-border bg-inputBg text-primary"></label><button type="button" :disabled="form.items.length === 1" :title="c('Artikel entfernen')" :aria-label="c('Artikel entfernen')" @click="form.items.splice(index, 1)"><i class="las la-trash text-lg"></i></button></div><button type="button" class="text-sm text-air-blue" @click="form.items.push({ name: '', quantity_requested: 1, price: 0 })"><i class="las la-plus"></i> {{ c("Artikel") }}</button></template>
                <template v-if="modal === 'delivery'"><label v-for="i in form.items.filter(i => i.remaining > 0)" :key="i.id" class="flex flex-wrap items-center justify-between gap-3 text-sm text-secondary">{{ i.name }} · {{ c('offen') }} {{ i.remaining }}<input v-model.number="i.quantity_received" type="number" min="0" :max="i.remaining" class="w-28 rounded-lg border-border bg-inputBg text-primary"></label></template>
                <div class="flex justify-end gap-3"><button type="button" :disabled="busy" class="text-sm text-secondary" @click="modal = ''">{{ c("Abbrechen") }}</button><button type="submit" :disabled="busy" class="rounded-lg bg-buttonPrimary px-4 py-2 text-sm font-semibold text-buttonTextPrimary disabled:opacity-50">{{ c("Speichern") }}</button></div>
            </form>
        </Modal>
    </section>
</template>
