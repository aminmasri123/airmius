<script setup>
import AppLayout from '@/Components/Auth/Layouts/AppLayout.vue'
import { Head, Link } from '@inertiajs/vue3'
import { computed, onMounted, onUnmounted, reactive, ref, watch } from 'vue'

defineOptions({ layout: AppLayout })

const props = defineProps({
    workspaces: { type: Array, default: () => [] },
    dataEndpoint: { type: String, required: true },
    copy: { type: Object, required: true },
})

const activeWorkspace = ref(props.workspaces[0]?.key || '')
const cache = reactive({})
const errors = reactive({})
const loadingWorkspace = ref('')
const search = ref('')
const priority = ref('all')
const source = ref('all')
let activeRequest = null

const currentWorkspace = computed(() => props.workspaces.find((workspace) => workspace.key === activeWorkspace.value) || null)
const currentData = computed(() => cache[activeWorkspace.value] || null)
const isLoading = computed(() => loadingWorkspace.value === activeWorkspace.value)
const currentError = computed(() => errors[activeWorkspace.value] || '')
const sources = computed(() => currentData.value?.sources || [])

const filteredCases = computed(() => {
    const term = search.value.trim().toLocaleLowerCase()

    return (currentData.value?.cases || []).filter((item) => {
        const matchesPriority = priority.value === 'all' || item.priority === priority.value
        const matchesSource = source.value === 'all' || item.kind === source.value
        const haystack = [item.reference, item.kind_label, item.status_label, item.title]
            .filter(Boolean)
            .join(' ')
            .toLocaleLowerCase()

        return matchesPriority && matchesSource && (!term || haystack.includes(term))
    })
})

const localeCode = computed(() => document.documentElement.lang || 'de-DE')
const formatDate = (value) => {
    if (!value) return '–'

    const date = new Date(value)
    if (Number.isNaN(date.getTime())) return '–'

    return new Intl.DateTimeFormat(localeCode.value, { dateStyle: 'medium', timeStyle: 'short' }).format(date)
}
const formatMoney = (meta) => new Intl.NumberFormat(localeCode.value, {
    style: 'currency',
    currency: meta?.currency || 'EUR',
}).format(Number(meta?.amount_cents || 0) / 100)
const replaceCount = (value, count) => String(value || '').replace(':count', String(count))
const priorityClass = (value) => ({
    urgent: 'border-danger/30 bg-danger/10 text-danger',
    high: 'border-warning/40 bg-warning/10 text-warning',
    normal: 'border-info/30 bg-info/10 text-info',
    low: 'border-border bg-muted text-secondary',
}[value] || 'border-border bg-muted text-secondary')

const loadWorkspace = async (workspace, force = false) => {
    if (!workspace || (!force && cache[workspace])) return

    activeRequest?.abort()
    const controller = new AbortController()
    activeRequest = controller
    loadingWorkspace.value = workspace
    errors[workspace] = ''

    try {
        const url = new URL(props.dataEndpoint, window.location.origin)
        url.searchParams.set('workspace', workspace)
        const response = await fetch(url, {
            method: 'GET',
            headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
            credentials: 'same-origin',
            signal: controller.signal,
        })

        if (!response.ok) throw new Error(`operations:${response.status}`)

        const payload = await response.json()
        cache[workspace] = payload.data
    } catch (error) {
        if (error?.name !== 'AbortError') errors[workspace] = props.copy.load_error
    } finally {
        if (activeRequest === controller) {
            activeRequest = null
            loadingWorkspace.value = ''
        }
    }
}

const selectWorkspace = (workspace) => {
    activeWorkspace.value = workspace
}

watch(activeWorkspace, (workspace) => {
    search.value = ''
    priority.value = 'all'
    source.value = 'all'
    loadWorkspace(workspace)
})

onMounted(() => loadWorkspace(activeWorkspace.value))
onUnmounted(() => activeRequest?.abort())
</script>

<template>
    <Head :title="copy.page_title" />

    <div class="space-y-5">
        <p class="sr-only" aria-live="polite">
            {{ isLoading ? copy.loading : (currentError || '') }}
        </p>

        <section class="relative overflow-hidden rounded-3xl border border-white/10 bg-slate-950 px-5 py-6 text-white shadow-xl sm:px-7 lg:px-9 lg:py-8">
            <div class="pointer-events-none absolute -right-16 -top-24 h-72 w-72 rounded-full bg-cyan-400/20 blur-3xl"></div>
            <div class="pointer-events-none absolute -bottom-28 left-1/4 h-64 w-64 rounded-full bg-violet-500/20 blur-3xl"></div>
            <div class="relative flex flex-col gap-5 lg:flex-row lg:items-end lg:justify-between">
                <div class="max-w-3xl">
                    <p class="text-xs font-bold uppercase tracking-[0.22em] text-cyan-300">{{ copy.eyebrow }}</p>
                    <h1 class="mt-2 text-3xl font-semibold tracking-tight sm:text-4xl">{{ copy.title }}</h1>
                    <p class="mt-3 max-w-2xl text-sm leading-6 text-slate-300 sm:text-base">{{ copy.intro }}</p>
                </div>
                <div class="flex flex-wrap items-center gap-3">
                    <span class="inline-flex items-center gap-2 rounded-full border border-emerald-300/30 bg-emerald-300/10 px-3 py-2 text-xs font-semibold text-emerald-200">
                        <i class="las la-user-shield text-base" aria-hidden="true"></i>
                        {{ copy.privacy_badge }}
                    </span>
                    <button
                        type="button"
                        class="inline-flex min-h-11 items-center gap-2 rounded-xl border border-white/20 bg-white/10 px-4 py-2 text-sm font-semibold transition hover:bg-white/15 focus:outline-none focus:ring-2 focus:ring-cyan-300"
                        :disabled="isLoading"
                        @click="loadWorkspace(activeWorkspace, true)"
                    >
                        <i class="las la-sync" :class="{ 'animate-spin': isLoading }" aria-hidden="true"></i>
                        {{ copy.refresh }}
                    </button>
                </div>
            </div>
        </section>

        <section class="surface-card overflow-hidden" :aria-label="copy.workspace_tabs">
            <div class="flex snap-x gap-2 overflow-x-auto p-3 sm:p-4" role="tablist">
                <button
                    v-for="workspace in workspaces"
                    :key="workspace.key"
                    type="button"
                    role="tab"
                    class="min-w-[15rem] snap-start rounded-2xl border px-4 py-3 text-start transition focus:outline-none focus:ring-2 focus:ring-primary/40"
                    :class="activeWorkspace === workspace.key ? 'border-primary bg-primary/10 shadow-sm' : 'border-border bg-bg hover:bg-muted'"
                    :aria-selected="activeWorkspace === workspace.key"
                    @click="selectWorkspace(workspace.key)"
                >
                    <span class="flex items-center gap-3">
                        <span class="grid h-10 w-10 shrink-0 place-items-center rounded-xl bg-primary/10 text-primary">
                            <i :class="workspace.icon" class="text-xl" aria-hidden="true"></i>
                        </span>
                        <span>
                            <span class="block text-sm font-semibold text-primary">{{ workspace.label }}</span>
                            <span class="mt-0.5 block text-xs leading-5 text-secondary">{{ workspace.description }}</span>
                        </span>
                    </span>
                </button>
            </div>
        </section>

        <div v-if="isLoading && !currentData" class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4" aria-hidden="true">
            <div v-for="index in 4" :key="index" class="surface-card h-28 animate-pulse bg-muted"></div>
        </div>

        <section v-else-if="currentError && !currentData" class="surface-card p-8 text-center">
            <i class="las la-exclamation-circle text-4xl text-danger" aria-hidden="true"></i>
            <p class="mt-3 text-sm font-semibold text-primary">{{ currentError }}</p>
            <button type="button" class="btn-primary mt-4" @click="loadWorkspace(activeWorkspace, true)">{{ copy.retry }}</button>
        </section>

        <template v-else-if="currentData">
            <section class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
                <article class="surface-card p-5">
                    <p class="text-xs font-semibold uppercase tracking-wide text-secondary">{{ copy.metrics.visible }}</p>
                    <p class="mt-2 text-3xl font-semibold text-primary">{{ currentData.summary.visible }}</p>
                </article>
                <article class="surface-card border-danger/20 p-5">
                    <p class="text-xs font-semibold uppercase tracking-wide text-secondary">{{ copy.metrics.urgent }}</p>
                    <p class="mt-2 text-3xl font-semibold text-danger">{{ currentData.summary.urgent }}</p>
                </article>
                <article class="surface-card border-warning/30 p-5">
                    <p class="text-xs font-semibold uppercase tracking-wide text-secondary">{{ copy.metrics.overdue }}</p>
                    <p class="mt-2 text-3xl font-semibold text-warning">{{ currentData.summary.overdue }}</p>
                </article>
                <article class="surface-card p-5">
                    <p class="text-xs font-semibold uppercase tracking-wide text-secondary">{{ copy.metrics.sources }}</p>
                    <p class="mt-2 text-3xl font-semibold text-primary">{{ currentData.summary.sources }}</p>
                </article>
            </section>

            <section class="surface-card p-4 sm:p-5">
                <div class="flex flex-col gap-4 xl:flex-row xl:items-end xl:justify-between">
                    <div>
                        <h2 class="text-lg font-semibold text-primary">{{ copy.filters.title }}</h2>
                        <p class="mt-1 text-xs text-secondary">{{ replaceCount(copy.filters.result, filteredCases.length) }}</p>
                    </div>
                    <div class="grid gap-3 sm:grid-cols-3 xl:min-w-[46rem]">
                        <label class="sm:col-span-1">
                            <span class="sr-only">{{ copy.filters.search }}</span>
                            <span class="relative block">
                                <i class="las la-search pointer-events-none absolute start-3 top-1/2 -translate-y-1/2 text-secondary" aria-hidden="true"></i>
                                <input v-model="search" type="search" class="form-input min-h-11 w-full ps-10" :placeholder="copy.filters.search">
                            </span>
                        </label>
                        <label>
                            <span class="sr-only">{{ copy.filters.priority }}</span>
                            <select v-model="priority" class="form-select min-h-11 w-full">
                                <option value="all">{{ copy.filters.priority }} · {{ copy.filters.all }}</option>
                                <option value="urgent">{{ copy.priorities.urgent }}</option>
                                <option value="high">{{ copy.priorities.high }}</option>
                                <option value="normal">{{ copy.priorities.normal }}</option>
                                <option value="low">{{ copy.priorities.low }}</option>
                            </select>
                        </label>
                        <label>
                            <span class="sr-only">{{ copy.filters.source }}</span>
                            <select v-model="source" class="form-select min-h-11 w-full">
                                <option value="all">{{ copy.filters.source }} · {{ copy.filters.all }}</option>
                                <option v-for="item in sources" :key="item.kind" :value="item.kind">{{ item.label }}</option>
                            </select>
                        </label>
                    </div>
                </div>
            </section>

            <div class="grid gap-5 xl:grid-cols-[minmax(0,1.65fr)_minmax(19rem,0.75fr)]">
                <section class="surface-card overflow-hidden">
                    <div class="flex items-center justify-between border-b border-border px-4 py-4 sm:px-5">
                        <h2 class="text-lg font-semibold text-primary">{{ copy.cases.title }}</h2>
                        <span class="text-xs font-semibold text-secondary">{{ currentWorkspace?.label }}</span>
                    </div>

                    <div v-if="filteredCases.length" class="divide-y divide-border">
                        <article v-for="item in filteredCases" :key="item.key" class="p-4 transition hover:bg-muted/40 sm:p-5">
                            <div class="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
                                <div class="min-w-0">
                                    <div class="flex flex-wrap items-center gap-2">
                                        <span class="rounded-md border px-2 py-1 text-[0.68rem] font-bold uppercase tracking-wide" :class="priorityClass(item.priority)">
                                            {{ item.priority_label }}
                                        </span>
                                        <span v-if="item.is_overdue" class="rounded-md bg-danger px-2 py-1 text-[0.68rem] font-bold uppercase tracking-wide text-white">
                                            {{ copy.cases.overdue }}
                                        </span>
                                        <span class="text-xs font-mono text-secondary">{{ item.reference }}</span>
                                    </div>
                                    <h3 class="mt-2 truncate text-base font-semibold text-primary">{{ item.title }}</h3>
                                    <p class="mt-1 text-sm text-secondary">{{ item.status_label }}</p>
                                </div>
                                <Link
                                    v-if="item.target_url"
                                    :href="item.target_url"
                                    class="inline-flex min-h-10 shrink-0 items-center justify-center gap-2 rounded-xl border border-border bg-bg px-3 py-2 text-sm font-semibold text-primary transition hover:border-primary/40 hover:text-primary"
                                >
                                    {{ item.target_label }}
                                    <i class="las la-arrow-right rtl:rotate-180" aria-hidden="true"></i>
                                </Link>
                            </div>

                            <dl class="mt-4 grid gap-3 rounded-xl bg-muted/60 p-3 text-xs sm:grid-cols-3">
                                <div>
                                    <dt class="text-secondary">{{ copy.cases.opened }}</dt>
                                    <dd class="mt-1 font-medium text-primary">{{ formatDate(item.opened_at) }}</dd>
                                </div>
                                <div>
                                    <dt class="text-secondary">{{ copy.cases.due }}</dt>
                                    <dd class="mt-1 font-medium" :class="item.is_overdue ? 'text-danger' : 'text-primary'">{{ formatDate(item.due_at) }}</dd>
                                </div>
                                <div v-if="item.meta?.amount_cents !== undefined">
                                    <dt class="text-secondary">{{ copy.cases.amount }}</dt>
                                    <dd class="mt-1 font-medium text-primary">{{ formatMoney(item.meta) }}</dd>
                                </div>
                                <div v-else-if="!item.target_url" class="sm:col-span-1">
                                    <dt class="sr-only">{{ copy.cases.access }}</dt>
                                    <dd class="mt-1 text-secondary"><i class="las la-lock me-1" aria-hidden="true"></i>{{ copy.cases.metadata_only }}</dd>
                                </div>
                            </dl>
                        </article>
                    </div>
                    <div v-else class="px-5 py-12 text-center text-sm text-secondary">
                        <i class="las la-check-circle mb-2 block text-4xl text-success" aria-hidden="true"></i>
                        {{ copy.cases.empty }}
                    </div>
                    <p v-if="currentData.summary.has_more" class="border-t border-border bg-info/5 px-5 py-3 text-xs text-secondary">
                        <i class="las la-info-circle me-1 text-info" aria-hidden="true"></i>{{ copy.cases.more_available }}
                    </p>
                </section>

                <aside class="space-y-5">
                    <section class="surface-card p-5">
                        <h2 class="text-lg font-semibold text-primary">{{ copy.timeline.title }}</h2>
                        <p class="mt-1 text-xs leading-5 text-secondary">{{ copy.timeline.intro }}</p>
                        <ol v-if="currentData.timeline.length" class="mt-5 space-y-4">
                            <li v-for="entry in currentData.timeline" :key="entry.key" class="relative ps-6">
                                <span class="absolute start-0 top-1.5 h-2.5 w-2.5 rounded-full bg-primary ring-4 ring-primary/10"></span>
                                <p class="text-sm font-medium text-primary">{{ entry.label }}</p>
                                <p class="mt-1 break-all text-xs text-secondary">{{ entry.event }}<span v-if="entry.status"> · {{ entry.status }}</span></p>
                                <time class="mt-1 block text-xs text-secondary">{{ formatDate(entry.occurred_at) }}</time>
                            </li>
                        </ol>
                        <p v-else class="mt-5 text-sm text-secondary">{{ copy.timeline.empty }}</p>
                    </section>

                    <section class="rounded-2xl border border-emerald-300/30 bg-emerald-50 p-5 text-emerald-950 dark:bg-emerald-950/20 dark:text-emerald-100">
                        <div class="flex items-start gap-3">
                            <i class="las la-shield-alt mt-0.5 text-2xl text-emerald-600" aria-hidden="true"></i>
                            <div>
                                <h2 class="font-semibold">{{ copy.privacy.title }}</h2>
                                <p class="mt-2 text-xs leading-5 opacity-80">{{ copy.privacy.text }}</p>
                                <p class="mt-2 text-xs leading-5 opacity-80">{{ copy.privacy.lazy }}</p>
                            </div>
                        </div>
                    </section>
                </aside>
            </div>
        </template>
    </div>
</template>
