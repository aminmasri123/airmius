<script setup>
import AppLayout from '@/Components/Auth/Layouts/AppLayout.vue'
import { Head, usePage } from '@inertiajs/vue3'
import { computed, onBeforeUnmount, onMounted, ref } from 'vue'

defineOptions({ layout: AppLayout })

const props = defineProps({
    clubs: { type: Array, default: () => [] },
    supportClubs: { type: Array, default: () => [] },
    abilities: { type: Object, default: () => ({ operate: false, cross_tenant: false }) },
    currentUser: { type: Object, required: true },
    copy: { type: Object, required: true },
    options: { type: Object, required: true },
})

const page = usePage()
const activeTab = ref('mine')
const tickets = ref([])
const ticketsLoaded = ref(false)
const ticketsLoading = ref(false)
const operations = ref({ tickets: [], summary: {}, tenants: [] })
const operationsLoaded = ref(false)
const operationsLoading = ref(false)
const creating = ref(false)
const notice = ref('')
const error = ref('')
const formErrors = ref({})
const savingIds = ref([])
let ticketsController = null
let operationsController = null
let createController = null
const saveControllers = new Map()

const form = ref({
    subject: '',
    message: '',
    category: 'technical',
    priority: 'normal',
    club_id: '',
})
const filters = ref({ status: '', priority: '', category: '', club_id: '', overdue: false })

const localeCode = computed(() => ({ ar: 'ar-EG', fr: 'fr-FR', en: 'en-US', de: 'de-DE' })[page.props.locale] || 'de-DE')
const clubOptions = computed(() => {
    const clubs = [...props.supportClubs, ...operations.value.tenants.map((tenant) => ({ id: tenant.club_id, name: tenant.club_name }))]

    return [...new Map(clubs.map((club) => [Number(club.id), { id: Number(club.id), name: club.name }])).values()]
        .sort((left, right) => left.name.localeCompare(right.name, localeCode.value))
})
const metrics = computed(() => [
    ['metric_total', operations.value.summary.total || 0],
    ['metric_open', operations.value.summary.open || 0],
    ['metric_urgent', operations.value.summary.urgent || 0],
    ['metric_overdue', operations.value.summary.overdue || 0],
    ['metric_response_overdue', operations.value.summary.response_overdue || 0],
    ['metric_resolution_overdue', operations.value.summary.resolution_overdue || 0],
    ['metric_escalated', operations.value.summary.escalated || 0],
    ['metric_health', `${operations.value.summary.sla_health_percent ?? 100}%`],
])

const text = (key, replacements = {}) => Object.entries(replacements).reduce(
    (value, [name, replacement]) => String(value).replaceAll(`:${name}`, String(replacement)),
    props.copy[key] || key,
)
const optionLabel = (group, value) => props.options[group]?.find((option) => option.value === value)?.label || value
const formatDate = (value) => value
    ? new Intl.DateTimeFormat(localeCode.value, { dateStyle: 'medium', timeStyle: 'short' }).format(new Date(value))
    : '—'
const slaLabel = (ticket) => text(`sla_${ticket.sla?.state || 'on_track'}`)
const slaTone = (ticket) => ({
    breached: 'border-error/30 bg-error/10 text-error',
    at_risk: 'border-warning/30 bg-warning/10 text-warning',
    met: 'border-success/30 bg-success/10 text-success',
}[ticket.sla?.state] || 'border-air-blue/30 bg-air-blue/10 text-air-blue')
const isSaving = (ticket) => savingIds.value.includes(ticket.id)
const validationMessage = (field) => formErrors.value[field]?.[0] || ''
const isCanceled = (requestError) => requestError?.code === 'ERR_CANCELED' || requestError?.name === 'CanceledError'
const requestMessage = (requestError, fallback = 'error_generic') => requestError?.response?.status === 422
    ? text('error_validation')
    : (requestError?.response?.data?.message || text(fallback))

const normalizeOperationTicket = (ticket) => ({
    ...ticket,
    form: {
        status: ticket.status,
        priority: ticket.priority,
        assigned_to: ticket.assignee?.id || null,
        admin_note: ticket.admin_note || '',
        escalated: Boolean(ticket.escalated_at),
    },
})

const loadTickets = async () => {
    ticketsController?.abort()
    const controller = new AbortController()
    ticketsController = controller
    ticketsLoading.value = true
    error.value = ''

    try {
        const response = await window.axios.get(route('api.v1.support.tickets.index'), {
            signal: controller.signal,
            headers: { 'X-Locale': page.props.locale },
        })
        if (ticketsController === controller) {
            tickets.value = response.data?.data || []
            ticketsLoaded.value = true
        }
    } catch (requestError) {
        if (!isCanceled(requestError)) error.value = requestMessage(requestError, 'load_failed')
    } finally {
        if (ticketsController === controller && !controller.signal.aborted) ticketsLoading.value = false
    }
}

const loadOperations = async () => {
    if (!props.abilities.operate) return
    operationsController?.abort()
    const controller = new AbortController()
    operationsController = controller
    operationsLoading.value = true
    error.value = ''

    const params = Object.fromEntries(Object.entries(filters.value).filter(([, value]) => value !== '' && value !== false))
    if (filters.value.overdue) params.overdue = 1

    try {
        const response = await window.axios.get(route('api.v1.admin.support.tickets.index'), {
            params,
            signal: controller.signal,
            headers: { 'X-Locale': page.props.locale },
        })
        if (operationsController === controller) {
            const data = response.data?.data || {}
            operations.value = {
                tickets: (data.tickets || []).map(normalizeOperationTicket),
                summary: data.summary || {},
                tenants: data.tenants || [],
            }
            operationsLoaded.value = true
        }
    } catch (requestError) {
        if (!isCanceled(requestError)) error.value = requestMessage(requestError, 'load_failed')
    } finally {
        if (operationsController === controller && !controller.signal.aborted) operationsLoading.value = false
    }
}

const activateTab = (tab) => {
    if (tab === 'operations' && !props.abilities.operate) return
    activeTab.value = tab
    notice.value = ''
    error.value = ''
    window.history.replaceState({}, '', `${window.location.pathname}?tab=${tab}`)
    if (tab === 'mine' && !ticketsLoaded.value) void loadTickets()
    if (tab === 'operations' && !operationsLoaded.value) void loadOperations()
}

const createTicket = async () => {
    createController?.abort()
    const controller = new AbortController()
    createController = controller
    creating.value = true
    notice.value = ''
    error.value = ''
    formErrors.value = {}

    try {
        const response = await window.axios.post(route('api.v1.support.tickets.store'), {
            ...form.value,
            club_id: form.value.club_id || null,
        }, {
            signal: controller.signal,
            headers: { 'X-Locale': page.props.locale },
        })
        if (createController === controller) {
            tickets.value = [response.data.data, ...tickets.value.filter((ticket) => ticket.id !== response.data.data.id)]
            ticketsLoaded.value = true
            form.value = { subject: '', message: '', category: 'technical', priority: 'normal', club_id: '' }
            notice.value = text('created')
        }
    } catch (requestError) {
        if (!isCanceled(requestError)) {
            formErrors.value = requestError?.response?.data?.errors || {}
            error.value = requestMessage(requestError)
        }
    } finally {
        if (createController === controller && !controller.signal.aborted) creating.value = false
    }
}

const resetFilters = () => {
    filters.value = { status: '', priority: '', category: '', club_id: '', overdue: false }
    void loadOperations()
}

const assignToMe = (ticket) => {
    ticket.form.assigned_to = props.currentUser.id
}

const saveTicket = async (ticket) => {
    if (isSaving(ticket)) return
    saveControllers.get(ticket.id)?.abort()
    const controller = new AbortController()
    saveControllers.set(ticket.id, controller)
    savingIds.value = [...savingIds.value, ticket.id]
    notice.value = ''
    error.value = ''

    try {
        const response = await window.axios.patch(route('api.v1.admin.support.tickets.update', ticket.id), ticket.form, {
            headers: { 'X-Locale': page.props.locale },
            signal: controller.signal,
        })
        const updated = normalizeOperationTicket(response.data.data)
        operations.value.tickets = operations.value.tickets.map((item) => item.id === updated.id ? updated : item)
        notice.value = text('updated')
        await loadOperations()
    } catch (requestError) {
        if (!isCanceled(requestError)) error.value = requestMessage(requestError)
    } finally {
        if (saveControllers.get(ticket.id) === controller) saveControllers.delete(ticket.id)
        savingIds.value = savingIds.value.filter((id) => id !== ticket.id)
    }
}

onMounted(() => {
    const requestedTab = new URLSearchParams(window.location.search).get('tab')
    activeTab.value = requestedTab === 'operations' && props.abilities.operate ? 'operations' : 'mine'
    if (activeTab.value === 'operations') void loadOperations()
    else void loadTickets()
})

onBeforeUnmount(() => {
    ticketsController?.abort()
    operationsController?.abort()
    createController?.abort()
    saveControllers.forEach((controller) => controller.abort())
    saveControllers.clear()
})
</script>

<template>
    <Head :title="text('title')" />

    <main class="mx-auto max-w-7xl space-y-6" aria-labelledby="support-title">
        <header class="overflow-hidden rounded-3xl border border-border bg-gradient-to-br from-air-blue/15 via-card to-emerald-400/10 p-5 shadow-sm sm:p-8">
            <div class="flex flex-col gap-5 lg:flex-row lg:items-start lg:justify-between">
                <div class="max-w-3xl">
                    <p class="text-xs font-black uppercase tracking-[0.18em] text-air-blue">{{ text('eyebrow') }}</p>
                    <h1 id="support-title" class="mt-2 text-3xl font-black text-primary sm:text-4xl">{{ text('title') }}</h1>
                    <p class="mt-3 text-sm leading-6 text-secondary sm:text-base">{{ text('subtitle') }}</p>
                </div>
                <aside class="max-w-xl rounded-2xl border border-success/25 bg-success/10 p-4" aria-labelledby="support-privacy-title">
                    <h2 id="support-privacy-title" class="flex items-center gap-2 text-sm font-bold text-primary">
                        <i class="las la-user-shield text-xl text-success" aria-hidden="true"></i>
                        {{ text('privacy_title') }}
                    </h2>
                    <p class="mt-1 text-xs leading-5 text-secondary">{{ text('privacy_body') }}</p>
                </aside>
            </div>
        </header>

        <div class="flex gap-2 overflow-x-auto rounded-2xl border border-border bg-card p-2" role="tablist" :aria-label="text('title')">
            <button
                type="button"
                class="min-h-11 shrink-0 rounded-xl px-4 py-2 text-sm font-bold transition"
                :class="activeTab === 'mine' ? 'bg-buttonPrimary text-buttonTextPrimary shadow-sm' : 'text-secondary hover:bg-muted hover:text-primary'"
                role="tab"
                :aria-selected="activeTab === 'mine'"
                @click="activateTab('mine')"
            >
                <i class="las la-ticket-alt me-2" aria-hidden="true"></i>{{ text('mine_tab') }}
            </button>
            <button
                v-if="abilities.operate"
                type="button"
                class="min-h-11 shrink-0 rounded-xl px-4 py-2 text-sm font-bold transition"
                :class="activeTab === 'operations' ? 'bg-buttonPrimary text-buttonTextPrimary shadow-sm' : 'text-secondary hover:bg-muted hover:text-primary'"
                role="tab"
                :aria-selected="activeTab === 'operations'"
                @click="activateTab('operations')"
            >
                <i class="las la-headset me-2" aria-hidden="true"></i>{{ text('operations_tab') }}
            </button>
        </div>

        <p v-if="notice" class="rounded-xl border border-success/30 bg-success/10 p-3 text-sm font-semibold text-success" role="status" aria-live="polite">{{ notice }}</p>
        <div v-if="error" class="flex items-center justify-between gap-4 rounded-xl border border-error/30 bg-error/10 p-3 text-sm font-semibold text-error" role="alert" aria-live="assertive">
            <span>{{ error }}</span>
            <button type="button" class="shrink-0 underline" @click="activeTab === 'operations' ? loadOperations() : loadTickets()">{{ text('retry') }}</button>
        </div>

        <section v-if="activeTab === 'mine'" class="grid gap-6 xl:grid-cols-[minmax(0,0.85fr)_minmax(0,1.15fr)]" role="tabpanel">
            <form class="surface-card h-fit p-5 sm:p-6" @submit.prevent="createTicket">
                <h2 class="text-xl font-black text-primary">{{ text('new_title') }}</h2>
                <p class="mt-1 text-sm leading-6 text-secondary">{{ text('new_body') }}</p>

                <div class="mt-5 space-y-4">
                    <label class="block text-sm font-bold text-primary">
                        {{ text('subject') }}
                        <input v-model.trim="form.subject" type="text" maxlength="160" required class="mt-1 min-h-11 w-full rounded-xl border border-border bg-inputBg px-3 text-primary focus:border-air-blue focus:ring-air-blue" :aria-invalid="Boolean(validationMessage('subject'))">
                        <span v-if="validationMessage('subject')" class="mt-1 block text-xs text-error">{{ validationMessage('subject') }}</span>
                    </label>
                    <label class="block text-sm font-bold text-primary">
                        {{ text('message') }}
                        <textarea v-model.trim="form.message" rows="6" minlength="10" maxlength="10000" required class="mt-1 w-full rounded-xl border border-border bg-inputBg px-3 py-2 text-primary focus:border-air-blue focus:ring-air-blue" :aria-invalid="Boolean(validationMessage('message'))"></textarea>
                        <span v-if="validationMessage('message')" class="mt-1 block text-xs text-error">{{ validationMessage('message') }}</span>
                    </label>
                    <div class="grid gap-4 sm:grid-cols-2">
                        <label class="block text-sm font-bold text-primary">
                            {{ text('category') }}
                            <select v-model="form.category" class="mt-1 min-h-11 w-full rounded-xl border border-border bg-inputBg px-3 text-primary focus:border-air-blue focus:ring-air-blue">
                                <option v-for="option in options.categories" :key="option.value" :value="option.value">{{ option.label }}</option>
                            </select>
                        </label>
                        <label class="block text-sm font-bold text-primary">
                            {{ text('priority') }}
                            <select v-model="form.priority" class="mt-1 min-h-11 w-full rounded-xl border border-border bg-inputBg px-3 text-primary focus:border-air-blue focus:ring-air-blue">
                                <option v-for="option in options.priorities" :key="option.value" :value="option.value">{{ option.label }}</option>
                            </select>
                        </label>
                    </div>
                    <label v-if="clubs.length" class="block text-sm font-bold text-primary">
                        {{ text('club') }}
                        <select v-model="form.club_id" class="mt-1 min-h-11 w-full rounded-xl border border-border bg-inputBg px-3 text-primary focus:border-air-blue focus:ring-air-blue">
                            <option value="">{{ text('no_club') }}</option>
                            <option v-for="club in clubs" :key="club.id" :value="club.id">{{ club.name }}</option>
                        </select>
                    </label>
                    <button type="submit" :disabled="creating" class="inline-flex min-h-11 w-full items-center justify-center rounded-xl bg-buttonPrimary px-4 py-3 text-sm font-black text-buttonTextPrimary transition hover:bg-buttonPrimaryHover disabled:cursor-wait disabled:opacity-60">
                        <i class="las me-2" :class="creating ? 'la-circle-notch animate-spin' : 'la-paper-plane'" aria-hidden="true"></i>
                        {{ creating ? text('sending') : text('send') }}
                    </button>
                </div>
            </form>

            <section class="surface-card overflow-hidden" aria-labelledby="ticket-history-title">
                <div class="flex items-start justify-between gap-4 border-b border-border p-5 sm:p-6">
                    <div>
                        <h2 id="ticket-history-title" class="text-xl font-black text-primary">{{ text('history_title') }}</h2>
                        <p class="mt-1 text-sm text-secondary">{{ text('history_body') }}</p>
                    </div>
                    <button type="button" :disabled="ticketsLoading" class="inline-flex min-h-10 shrink-0 items-center rounded-xl border border-border px-3 text-sm font-bold text-primary hover:border-air-blue hover:text-air-blue disabled:opacity-50" @click="loadTickets">
                        <i class="las la-sync me-2" :class="{ 'animate-spin': ticketsLoading }" aria-hidden="true"></i>{{ text('reload') }}
                    </button>
                </div>
                <div v-if="ticketsLoading && !ticketsLoaded" class="space-y-3 p-5" aria-busy="true" :aria-label="text('loading')">
                    <div v-for="index in 3" :key="index" class="h-28 animate-pulse rounded-2xl bg-inputBg"></div>
                </div>
                <div v-else-if="tickets.length" class="divide-y divide-border">
                    <article v-for="ticket in tickets" :key="ticket.id" class="p-5 sm:p-6">
                        <div class="flex flex-wrap items-start justify-between gap-3">
                            <div class="min-w-0">
                                <p class="text-xs font-bold text-secondary">{{ text('ticket_number', { id: ticket.id }) }}</p>
                                <h3 class="mt-1 break-words text-base font-black text-primary">{{ ticket.subject }}</h3>
                            </div>
                            <span class="rounded-full border px-2.5 py-1 text-xs font-bold" :class="slaTone(ticket)">{{ slaLabel(ticket) }}</span>
                        </div>
                        <p class="mt-3 whitespace-pre-wrap break-words text-sm leading-6 text-secondary">{{ ticket.message }}</p>
                        <div class="mt-4 flex flex-wrap gap-2 text-xs font-semibold">
                            <span class="rounded-full bg-muted px-2.5 py-1 text-primary">{{ optionLabel('statuses', ticket.status) }}</span>
                            <span class="rounded-full bg-muted px-2.5 py-1 text-primary">{{ optionLabel('priorities', ticket.priority) }}</span>
                            <span class="rounded-full bg-muted px-2.5 py-1 text-primary">{{ optionLabel('categories', ticket.category) }}</span>
                            <span v-if="ticket.club" class="rounded-full bg-muted px-2.5 py-1 text-primary">{{ ticket.club.name }}</span>
                        </div>
                        <div class="mt-4 grid gap-1 text-xs text-secondary sm:grid-cols-2">
                            <span>{{ text('created_at', { date: formatDate(ticket.created_at) }) }}</span>
                            <span>{{ text('response_due', { date: formatDate(ticket.response_due_at) }) }}</span>
                            <span>{{ text('resolution_due', { date: formatDate(ticket.due_at) }) }}</span>
                        </div>
                    </article>
                </div>
                <div v-else class="p-10 text-center">
                    <i class="las la-ticket-alt text-4xl text-secondary" aria-hidden="true"></i>
                    <h3 class="mt-3 text-lg font-black text-primary">{{ text('empty_title') }}</h3>
                    <p class="mt-1 text-sm text-secondary">{{ text('empty_body') }}</p>
                </div>
            </section>
        </section>

        <section v-else-if="abilities.operate" class="space-y-6" role="tabpanel" aria-labelledby="operations-title">
            <div>
                <h2 id="operations-title" class="text-2xl font-black text-primary">{{ text('operations_title') }}</h2>
                <p class="mt-1 text-sm text-secondary">{{ text('operations_body') }}</p>
            </div>

            <form class="surface-card p-5" @submit.prevent="loadOperations">
                <h3 class="text-base font-black text-primary">{{ text('filters_title') }}</h3>
                <div class="mt-4 grid gap-3 sm:grid-cols-2 xl:grid-cols-5">
                    <label class="text-xs font-bold text-secondary">{{ text('status') || text('all_statuses') }}
                        <select v-model="filters.status" class="mt-1 min-h-11 w-full rounded-xl border border-border bg-inputBg px-3 text-sm text-primary"><option value="">{{ text('all_statuses') }}</option><option v-for="option in options.statuses" :key="option.value" :value="option.value">{{ option.label }}</option></select>
                    </label>
                    <label class="text-xs font-bold text-secondary">{{ text('priority') }}
                        <select v-model="filters.priority" class="mt-1 min-h-11 w-full rounded-xl border border-border bg-inputBg px-3 text-sm text-primary"><option value="">{{ text('all_priorities') }}</option><option v-for="option in options.priorities" :key="option.value" :value="option.value">{{ option.label }}</option></select>
                    </label>
                    <label class="text-xs font-bold text-secondary">{{ text('category') }}
                        <select v-model="filters.category" class="mt-1 min-h-11 w-full rounded-xl border border-border bg-inputBg px-3 text-sm text-primary"><option value="">{{ text('all_categories') }}</option><option v-for="option in options.categories" :key="option.value" :value="option.value">{{ option.label }}</option></select>
                    </label>
                    <label class="text-xs font-bold text-secondary">{{ text('club') }}
                        <select v-model="filters.club_id" class="mt-1 min-h-11 w-full rounded-xl border border-border bg-inputBg px-3 text-sm text-primary"><option value="">{{ text('all_clubs') }}</option><option v-for="club in clubOptions" :key="club.id" :value="club.id">{{ club.name }}</option></select>
                    </label>
                    <label class="flex min-h-11 items-center gap-2 self-end rounded-xl border border-border bg-inputBg px-3 text-sm font-bold text-primary"><input v-model="filters.overdue" type="checkbox" class="rounded border-border text-air-blue focus:ring-air-blue">{{ text('overdue_only') }}</label>
                </div>
                <div class="mt-4 flex flex-wrap gap-2">
                    <button type="submit" :disabled="operationsLoading" class="min-h-10 rounded-xl bg-buttonPrimary px-4 text-sm font-black text-buttonTextPrimary disabled:opacity-50">{{ text('apply_filters') }}</button>
                    <button type="button" :disabled="operationsLoading" class="min-h-10 rounded-xl border border-border px-4 text-sm font-bold text-primary disabled:opacity-50" @click="resetFilters">{{ text('reset_filters') }}</button>
                </div>
            </form>

            <div v-if="operationsLoading && !operationsLoaded" class="grid gap-3 sm:grid-cols-2 xl:grid-cols-4" aria-busy="true" :aria-label="text('loading')">
                <div v-for="index in 8" :key="index" class="h-24 animate-pulse rounded-2xl bg-inputBg"></div>
            </div>
            <template v-else>
                <section class="grid gap-3 sm:grid-cols-2 xl:grid-cols-4" :aria-label="text('metric_health')">
                    <div v-for="metric in metrics" :key="metric[0]" class="surface-card p-4">
                        <p class="text-xs font-bold uppercase tracking-wide text-secondary">{{ text(metric[0]) }}</p>
                        <p class="mt-2 text-2xl font-black text-primary">{{ metric[1] }}</p>
                    </div>
                </section>

                <section v-if="operations.tenants.length" class="surface-card p-5" aria-labelledby="tenant-report-title">
                    <h3 id="tenant-report-title" class="text-lg font-black text-primary">{{ text('tenants_title') }}</h3>
                    <p class="mt-1 text-sm text-secondary">{{ text('tenants_body') }}</p>
                    <div class="mt-4 grid gap-3 md:grid-cols-2 xl:grid-cols-3">
                        <article v-for="tenant in operations.tenants" :key="tenant.club_id" class="rounded-2xl border border-border bg-inputBg p-4">
                            <div class="flex items-start justify-between gap-3"><h4 class="font-black text-primary">{{ tenant.club_name }}</h4><span class="text-sm font-black text-air-blue">{{ tenant.sla_health_percent }}%</span></div>
                            <div class="mt-3 flex flex-wrap gap-2 text-xs font-semibold text-secondary"><span>{{ text('tenant_total', { count: tenant.total }) }}</span><span>{{ text('tenant_open', { count: tenant.open }) }}</span><span>{{ text('tenant_overdue', { count: tenant.overdue }) }}</span></div>
                        </article>
                    </div>
                </section>

                <section aria-labelledby="operation-tickets-title">
                    <h3 id="operation-tickets-title" class="text-xl font-black text-primary">{{ text('tickets_title') }}</h3>
                    <div v-if="operations.tickets.length" class="mt-4 space-y-4">
                        <article v-for="ticket in operations.tickets" :key="ticket.id" class="surface-card p-5 sm:p-6">
                            <div class="flex flex-col gap-4 lg:flex-row lg:items-start lg:justify-between">
                                <div class="min-w-0 flex-1">
                                    <div class="flex flex-wrap items-center gap-2"><span class="text-xs font-bold text-secondary">{{ text('ticket_number', { id: ticket.id }) }}</span><span class="rounded-full border px-2.5 py-1 text-xs font-bold" :class="slaTone(ticket)">{{ slaLabel(ticket) }}</span></div>
                                    <h4 class="mt-2 break-words text-lg font-black text-primary">{{ ticket.subject }}</h4>
                                    <p class="mt-2 whitespace-pre-wrap break-words text-sm leading-6 text-secondary">{{ ticket.message }}</p>
                                    <dl class="mt-4 grid gap-2 text-xs sm:grid-cols-2">
                                        <div><dt class="font-bold text-secondary">{{ text('requester') }}</dt><dd class="mt-0.5 break-words text-primary">{{ ticket.requester?.name || '—' }} · {{ ticket.requester?.email || '—' }}</dd></div>
                                        <div><dt class="font-bold text-secondary">{{ text('club') }}</dt><dd class="mt-0.5 text-primary">{{ ticket.club?.name || text('no_club') }}</dd></div>
                                        <div><dt class="font-bold text-secondary">{{ text('created_at', { date: formatDate(ticket.created_at) }) }}</dt></div>
                                        <div><dt class="font-bold text-secondary">{{ text('response_due', { date: formatDate(ticket.response_due_at) }) }}</dt></div>
                                        <div><dt class="font-bold text-secondary">{{ text('resolution_due', { date: formatDate(ticket.due_at) }) }}</dt></div>
                                    </dl>
                                </div>
                                <div class="w-full rounded-2xl border border-border bg-inputBg p-4 lg:max-w-md">
                                    <div class="grid gap-3 sm:grid-cols-2">
                                        <label class="text-xs font-bold text-secondary">{{ text('status') }}<select v-model="ticket.form.status" class="mt-1 min-h-10 w-full rounded-lg border border-border bg-card px-2 text-sm text-primary"><option v-for="option in options.statuses" :key="option.value" :value="option.value">{{ option.label }}</option></select></label>
                                        <label class="text-xs font-bold text-secondary">{{ text('priority') }}<select v-model="ticket.form.priority" class="mt-1 min-h-10 w-full rounded-lg border border-border bg-card px-2 text-sm text-primary"><option v-for="option in options.priorities" :key="option.value" :value="option.value">{{ option.label }}</option></select></label>
                                    </div>
                                    <div class="mt-3 flex flex-wrap items-center justify-between gap-2 text-sm"><span class="text-secondary"><strong>{{ text('assignee') }}:</strong> {{ ticket.form.assigned_to === currentUser.id ? currentUser.name : (ticket.assignee?.name || text('unassigned')) }}</span><button v-if="ticket.form.assigned_to !== currentUser.id" type="button" class="font-bold text-air-blue underline" @click="assignToMe(ticket)">{{ text('assign_me') }}</button></div>
                                    <label class="mt-3 block text-xs font-bold text-secondary">{{ text('internal_note') }}<textarea v-model.trim="ticket.form.admin_note" rows="3" maxlength="4000" class="mt-1 w-full rounded-lg border border-border bg-card px-2 py-2 text-sm text-primary"></textarea><span class="mt-1 block font-normal">{{ text('internal_note_hint') }}</span></label>
                                    <label class="mt-3 flex items-center gap-2 text-sm font-bold text-primary"><input v-model="ticket.form.escalated" type="checkbox" class="rounded border-border text-air-blue focus:ring-air-blue">{{ text('escalated') }}</label>
                                    <button type="button" :disabled="isSaving(ticket)" class="mt-4 inline-flex min-h-10 w-full items-center justify-center rounded-xl bg-buttonPrimary px-4 text-sm font-black text-buttonTextPrimary disabled:opacity-50" @click="saveTicket(ticket)"><i class="las me-2" :class="isSaving(ticket) ? 'la-circle-notch animate-spin' : 'la-save'" aria-hidden="true"></i>{{ isSaving(ticket) ? text('saving') : text('save') }}</button>
                                </div>
                            </div>
                        </article>
                    </div>
                    <p v-else class="mt-4 surface-card p-8 text-center text-sm text-secondary">{{ text('no_operation_tickets') }}</p>
                </section>
            </template>
        </section>
    </main>
</template>
