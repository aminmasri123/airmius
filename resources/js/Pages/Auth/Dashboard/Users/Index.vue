<script setup>
import { Head, router } from '@inertiajs/vue3'
import AppLayout from '@/Components/Auth/Layouts/AppLayout.vue'
import DeleteConfirmModal from '@/Components/Auth/DeleteConfirmModal.vue'
import { computed, ref, watch } from 'vue'
import { useI18n } from 'vue-i18n'

const { t, locale } = useI18n()
const tx = (key, fallback, values = {}) => {
    const translated = t(key, values)
    return translated === key ? fallback : translated
}

const props = defineProps({
    users: {
        type: Object,
        default: () => ({
            data: [],
            links: [],
            from: null,
            to: null,
            total: 0,
        }),
    },
    filters: {
        type: Object,
        default: () => ({
            search: '',
            status: 'all',
            tab: 'users',
            inactive_search: '',
            inactive_stage: 'all',
        }),
    },
    inactiveUsers: {
        type: Object,
        default: () => ({
            data: [],
            links: [],
            from: null,
            to: null,
            total: 0,
        }),
    },
    inactiveSummary: {
        type: Object,
        default: () => ({}),
    },
    inactiveRules: {
        type: Array,
        default: () => [],
    },
    canManageInactivity: {
        type: Boolean,
        default: false,
    },
    warnings: {
        type: Array,
        default: () => [],
    },
})

const searchQuery = ref(props.filters.search || '')
const statusFilter = ref(props.filters.status || 'all')
const activeTab = ref(props.filters.tab || 'users')
const inactiveSearch = ref(props.filters.inactive_search || '')
const inactiveStage = ref(props.filters.inactive_stage || 'all')
const warningCategoryFilter = ref('all')
const showDeleteModal = ref(false)
const userToDelete = ref(null)
const sendingNoticeId = ref(null)

const createUser = () => {
    router.visit(route('members.create'))
}

const clearSearch = () => {
    searchQuery.value = ''
    statusFilter.value = 'all'
}

const clearInactiveFilters = () => {
    inactiveSearch.value = ''
    inactiveStage.value = 'all'
}

let searchTimeout = null
watch([searchQuery, statusFilter], ([search, status]) => {
    clearTimeout(searchTimeout)

    searchTimeout = setTimeout(() => {
        router.get(route('members.index'), {
            search: search || undefined,
            status: status !== 'all' ? status : undefined,
            tab: activeTab.value,
        }, {
            preserveState: true,
            replace: true,
            only: ['users', 'filters'],
        })
    }, 300)
})

watch(activeTab, (tab) => {
    router.get(route('members.index'), {
        tab,
        search: searchQuery.value || undefined,
        status: statusFilter.value !== 'all' ? statusFilter.value : undefined,
        inactive_search: inactiveSearch.value || undefined,
        inactive_stage: inactiveStage.value !== 'all' ? inactiveStage.value : undefined,
    }, {
        preserveState: true,
        preserveScroll: true,
        replace: true,
        only: ['filters'],
    })
})

watch([inactiveSearch, inactiveStage], ([search, stage]) => {
    clearTimeout(searchTimeout)

    searchTimeout = setTimeout(() => {
        router.get(route('members.index'), {
            tab: 'inactivity',
            inactive_search: search || undefined,
            inactive_stage: stage !== 'all' ? stage : undefined,
        }, {
            preserveState: true,
            replace: true,
            only: ['inactiveUsers', 'inactiveSummary', 'filters'],
        })
    }, 300)
})

const visitPage = (url) => {
    if (!url) return

    router.visit(url, {
        preserveState: true,
        preserveScroll: true,
        only: ['users', 'filters'],
    })
}

const openDeleteModal = (user) => {
    userToDelete.value = user
    showDeleteModal.value = true
}

const handleDeleteConfirm = () => {
    if (userToDelete.value) {
        router.delete(route('members.destroy', userToDelete.value.id))
        showDeleteModal.value = false
        userToDelete.value = null
    }
}

const handleDeleteCancel = () => {
    showDeleteModal.value = false
    userToDelete.value = null
}

const localeCode = computed(() => ({ ar: 'ar-EG', fr: 'fr-FR', en: 'en-US', de: 'de-DE' })[locale.value] || 'de-DE')
const formatDate = (value) => value ? new Intl.DateTimeFormat(localeCode.value, { dateStyle: 'short', timeStyle: 'short' }).format(new Date(value)) : '-'

const inactiveCards = computed(() => [
    { label: tx('users_admin.inactivity.12', '12+ Monate'), value: props.inactiveSummary.inactive_12 || 0, tone: 'text-warning' },
    { label: tx('users_admin.inactivity.18', '18+ Monate'), value: props.inactiveSummary.inactive_18 || 0, tone: 'text-warning' },
    { label: tx('users_admin.inactivity.24', '24+ Monate'), value: props.inactiveSummary.inactive_24 || 0, tone: 'text-error' },
    { label: tx('users_admin.inactivity.36', '36+ Monate'), value: props.inactiveSummary.inactive_36 || 0, tone: 'text-error' },
    { label: tx('users_admin.inactivity.mail_failed', 'Mail-Fehler'), value: props.inactiveSummary.mail_failed || 0, tone: 'text-error' },
])

const statusLabel = (user) => {
    if (user.account_status === 'suspended') {
        return user.suspended_until ? tx('users_admin.status.suspended_until', 'Gesperrt bis {date}', { date: formatDate(user.suspended_until) }) : tx('users_admin.status.suspended', 'Gesperrt')
    }

    return tx('users_admin.status.active', 'Aktiv')
}

const warningCategories = computed(() => {
    const categories = new Set()

    props.warnings.forEach((warning) => {
        ;(warning.flag?.categories || []).forEach((category) => categories.add(category))
    })

    return Array.from(categories).sort()
})

const filteredWarnings = computed(() => {
    if (warningCategoryFilter.value === 'all') {
        return props.warnings
    }

    return props.warnings.filter((warning) =>
        (warning.flag?.categories || []).includes(warningCategoryFilter.value),
    )
})

const badgeClass = (severity) => {
    if (severity === 'high') return 'bg-error/10 text-error'
    if (severity === 'medium') return 'bg-warning/10 text-warning'
    return 'bg-secondary/20 text-secondary'
}

const initials = (name) => (name || '?')
    .split(' ')
    .map((part) => part[0])
    .join('')
    .slice(0, 2)
    .toUpperCase()

const stageLabel = (stage) => ({
    first: tx('users_admin.stage.first', 'Erste Mail fällig'),
    second: tx('users_admin.stage.second', 'Zweite Mail fällig'),
    scheduled: tx('users_admin.stage.scheduled', 'Profil ausblenden'),
    anonymize: tx('users_admin.stage.anonymize', 'Anonymisierung prüfen'),
    waiting: tx('users_admin.stage.waiting', 'Warten'),
    active: tx('users_admin.stage.active', 'Aktiv'),
    check: tx('users_admin.stage.check', 'Prüfen'),
}[stage] || stage)

const stageClass = (stage) => {
    if (['anonymize', 'scheduled'].includes(stage)) return 'bg-error/10 text-error'
    if (['first', 'second', 'check'].includes(stage)) return 'bg-warning/10 text-warning'
    return 'bg-success/10 text-success'
}

const mailBadgeClass = (status) => {
    if (status === 'sent') return 'bg-success/10 text-success'
    if (status === 'failed') return 'bg-error/10 text-error'
    if (status === 'skipped') return 'bg-warning/10 text-warning'
    return 'bg-secondary/20 text-secondary'
}

const sendInactivityNotice = (user, stage) => {
    sendingNoticeId.value = `${user.id}-${stage}`

    router.post(route('admin.members.inactivity-notice', user.id), {
        stage,
    }, {
        preserveScroll: true,
        onFinish: () => {
            sendingNoticeId.value = null
        },
    })
}
</script>

<template>
    <AppLayout>

        <Head :title="tx('users_admin.page_title', 'Nutzerverwaltung')" />

        <div class="space-y-6">
            <div class="mb-6">
                <h1 class="text-2xl font-semibold text-primary">{{ tx('users_admin.title', 'Admin Dashboard – Nutzer') }}</h1>
                <p class="mt-2 text-sm text-secondary">
                    {{ tx('users_admin.intro', 'Übersicht aller registrierten Nutzer.') }}
                </p>
            </div>

            <div class="flex flex-wrap gap-2 rounded-lg border border-border bg-card p-3">
                <button
                    v-if="canManageInactivity"
                    type="button"
                    @click="activeTab = 'users'"
                    class="rounded-md px-4 py-2 text-sm font-semibold transition"
                    :class="activeTab === 'users' ? 'bg-buttonPrimary text-buttonTextPrimary' : 'text-secondary hover:bg-secondary/10 hover:text-primary'"
                >
                    {{ tx('users_admin.tabs.users', 'Nutzer') }}
                    <span class="ml-2 rounded-full bg-secondary/20 px-2 py-0.5 text-xs">{{ users.total }}</span>
                </button>
                <button
                    type="button"
                    @click="activeTab = 'warnings'"
                    class="rounded-md px-4 py-2 text-sm font-semibold transition"
                    :class="activeTab === 'warnings' ? 'bg-buttonPrimary text-buttonTextPrimary' : 'text-secondary hover:bg-secondary/10 hover:text-primary'"
                >
                    {{ tx('users_admin.tabs.warnings', 'Warnungen') }}
                    <span class="ml-2 rounded-full bg-secondary/20 px-2 py-0.5 text-xs">{{ warnings.length }}</span>
                </button>
                <button
                    type="button"
                    @click="activeTab = 'inactivity'"
                    class="rounded-md px-4 py-2 text-sm font-semibold transition"
                    :class="activeTab === 'inactivity' ? 'bg-buttonPrimary text-buttonTextPrimary' : 'text-secondary hover:bg-secondary/10 hover:text-primary'"
                >
                    {{ tx('users_admin.tabs.inactivity', 'Inaktivität & DSGVO') }}
                    <span class="ml-2 rounded-full bg-secondary/20 px-2 py-0.5 text-xs">{{ inactiveSummary.inactive_12 || 0 }}</span>
                </button>
            </div>

            <!-- Suchfeld und Aktionen -->
            <div v-if="activeTab === 'users'" class="flex mb-4 ">
                <button @click="createUser"
                    class="inline-flex items-center justify-center px-4 py-2 bg-buttonPrimary text-buttonTextPrimary rounded-l-lg hover:bg-primary/90 transition">
                    <i class="las la-plus "></i>

                </button>

                <div class="relative flex-1">
                    <input v-model="searchQuery" type="text" :placeholder="tx('users_admin.search', 'Suche nach Name oder E-Mail...')"
                        class="w-full px-4 py-2 border border-border bg-card text-primary placeholder-secondary focus:ring-1 focus:ring-bg " />
                    
                </div>
                    <select v-model="statusFilter" class="border border-border bg-card px-4 py-2 text-primary focus:ring-1 focus:ring-bg">
                        <option value="all">{{ tx('users_admin.filters.all', 'Alle Status') }}</option>
                        <option value="active">{{ tx('users_admin.status.active', 'Aktiv') }}</option>
                        <option value="suspended">{{ tx('users_admin.status.suspended', 'Gesperrt') }}</option>
                    </select>
                    <button @click="clearSearch"
                    class="inline-flex items-center justify-center px-4 py-2 bg-buttonPrimary text-buttonTextPrimary rounded-r-lg hover:bg-primary/90 transition">
                        <i class="las la-sync"></i>
                    </button>
            </div>

            <div v-if="activeTab === 'users'" class="overflow-hidden rounded-lg border border-border bg-card shadow-sm">
                <table class="min-w-full divide-y divide-border">
                    <thead class="bg-card">
                        <tr>
                            <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-primary">
                                ID</th>
                            <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-primary">
                                Name</th>
                            <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-primary">
                                E-Mail</th>
                            <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-primary">
                                Status</th>
                            <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-primary">
                                Erstellt am</th>
                            <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-primary">
                                Aktionen</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-border bg-table">
                        <tr v-for="user in users.data" :key="user.id">
                            <td class="px-4 py-4 text-sm text-primary">{{ user.id }}</td>
                            <td class="px-4 py-4 text-sm text-primary">{{ user.name }}</td>
                            <td class="px-4 py-4 text-sm text-primary">{{ user.email }}</td>
                            <td class="px-4 py-4 text-sm">
                                <span
                                    class="rounded-full px-3 py-1 text-xs font-semibold"
                                    :class="user.account_status === 'suspended' ? 'bg-error/10 text-error' : 'bg-success/10 text-success'"
                                >
                                    {{ statusLabel(user) }}
                                </span>
                                <p v-if="user.suspension_reason" class="mt-1 max-w-xs truncate text-xs text-secondary">
                                    {{ user.suspension_reason }}
                                </p>
                            </td>
                            <td class="px-4 py-4 text-sm text-primary">{{ formatDate(user.created_at) }}</td>
                            <td class="px-4 py-4 text-sm">
                                <div class="flex space-x-2">
                                    <button @click="router.visit(route('members.edit', user.id))"
                                        class="px-3 py-1 bg-primary text-buttonTextPrimary rounded hover:bg-primary/80 transition-colors">
                                        {{ tx('users_admin.actions.edit', 'Bearbeiten') }}
                                    </button>
                                    <button @click="openDeleteModal(user)"
                                        class="px-3 py-1 bg-error text-buttonTextSecondary rounded hover:bg-error/80 transition-colors">
                                        {{ tx('users_admin.actions.delete', 'Löschen') }}
                                    </button>
                                </div>
                            </td>
                        </tr>

                        <tr v-if="users.data.length === 0">
                            <td colspan="6" class="px-4 py-4 text-center text-sm text-secondary">
                                {{ tx('users_admin.empty', 'Keine Nutzer gefunden.') }}
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <div v-if="activeTab === 'users'" class="mt-4 flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                <p class="text-sm text-secondary">
                    <span v-if="users.total > 0">
                        Zeige {{ users.from }} bis {{ users.to }} von {{ users.total }} Nutzern.
                    </span>
                    <span v-else>Keine Nutzer vorhanden.</span>
                </p>

                <div v-if="users.links.length > 3" class="flex flex-wrap gap-1">
                    <button
                        v-for="link in users.links"
                        :key="link.label"
                        type="button"
                        :disabled="!link.url"
                        @click="visitPage(link.url)"
                        class="min-w-10 rounded border border-border px-3 py-2 text-sm transition disabled:cursor-not-allowed disabled:opacity-50"
                        :class="link.active
                            ? 'bg-primary text-buttonTextPrimary'
                            : 'bg-card text-primary hover:bg-secondary/20'"
                        v-html="link.label"
                    />
                </div>
            </div>

            <section v-if="activeTab === 'warnings'" class="overflow-hidden rounded-lg border border-border bg-card shadow-sm">
                <div class="flex flex-col gap-3 border-b border-border p-4 sm:flex-row sm:items-center sm:justify-between">
                    <div>
                        <h2 class="text-lg font-semibold text-primary">User-Warnungen</h2>
                        <p class="text-sm text-secondary">
                            Hier siehst du, welche Nutzer bereits Moderationswarnungen bekommen haben.
                        </p>
                    </div>

                    <select
                        v-model="warningCategoryFilter"
                        class="rounded-md border border-border bg-card px-3 py-2 text-sm text-primary focus:ring-1 focus:ring-bg"
                    >
                        <option value="all">Alle Kategorien</option>
                        <option v-for="category in warningCategories" :key="category" :value="category">
                            {{ category }}
                        </option>
                    </select>
                </div>

                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-border">
                        <thead class="bg-card">
                            <tr>
                                <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-primary">Nutzer</th>
                                <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-primary">Kategorie</th>
                                <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-primary">Severity</th>
                                <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-primary">Punkte</th>
                                <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-primary">Grund</th>
                                <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-primary">Datum</th>
                                <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-primary">Aktion</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-border bg-table">
                            <tr v-for="warning in filteredWarnings" :key="warning.id">
                                <td class="px-4 py-4 text-sm text-primary">
                                    <div class="font-semibold">{{ warning.user?.name || 'Unbekannt' }}</div>
                                    <div class="text-xs text-secondary">{{ warning.user?.email || '-' }}</div>
                                    <span
                                        v-if="warning.user?.account_status === 'suspended'"
                                        class="mt-2 inline-flex rounded-full bg-error/10 px-2 py-1 text-xs font-semibold text-error"
                                    >
                                        Gesperrt bis {{ formatDate(warning.user?.suspended_until) }}
                                    </span>
                                </td>
                                <td class="px-4 py-4 text-sm text-primary">
                                    <div class="flex flex-wrap gap-1">
                                        <span
                                            v-for="category in warning.flag?.categories || []"
                                            :key="`${warning.id}-${category}`"
                                            class="rounded-full bg-secondary/20 px-2 py-1 text-xs font-semibold text-secondary"
                                        >
                                            {{ category }}
                                        </span>
                                        <span v-if="!(warning.flag?.categories || []).length" class="text-secondary">-</span>
                                    </div>
                                    <div v-if="(warning.flag?.matched_terms || []).length" class="mt-2 text-xs text-secondary">
                                        Treffer: {{ warning.flag.matched_terms.join(', ') }}
                                    </div>
                                </td>
                                <td class="px-4 py-4 text-sm">
                                    <span class="rounded-full px-3 py-1 text-xs font-semibold" :class="badgeClass(warning.severity)">
                                        {{ warning.severity || '-' }}
                                    </span>
                                </td>
                                <td class="px-4 py-4 text-sm font-semibold text-primary">{{ warning.points }}</td>
                                <td class="max-w-md px-4 py-4 text-sm text-secondary">{{ warning.reason || '-' }}</td>
                                <td class="px-4 py-4 text-sm text-primary">{{ formatDate(warning.created_at) }}</td>
                                <td class="px-4 py-4 text-sm">
                                    <button
                                        v-if="warning.user?.id"
                                        type="button"
                                        @click="router.visit(route('members.edit', warning.user.id))"
                                        class="rounded bg-primary px-3 py-1 text-buttonTextPrimary transition-colors hover:bg-primary/80"
                                    >
                                        Bearbeiten
                                    </button>
                                </td>
                            </tr>

                            <tr v-if="filteredWarnings.length === 0">
                                <td colspan="7" class="px-4 py-8 text-center text-sm text-secondary">
                                    Keine Warnungen gefunden.
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </section>

            <section v-if="activeTab === 'inactivity'" class="space-y-4">
                <div class="grid gap-3 sm:grid-cols-2 xl:grid-cols-5">
                    <article
                        v-for="card in inactiveCards"
                        :key="card.label"
                        class="rounded-lg border border-border bg-card p-4"
                    >
                        <p class="text-xs font-semibold uppercase text-secondary">{{ card.label }}</p>
                        <p class="mt-2 text-2xl font-bold" :class="card.tone">{{ card.value }}</p>
                    </article>
                </div>

                <section class="rounded-lg border border-border bg-card p-5">
                    <div class="flex flex-col gap-2 md:flex-row md:items-center md:justify-between">
                        <div>
                            <h2 class="text-lg font-semibold text-primary">Regeln</h2>
                            <p class="text-sm text-secondary">
                                Diese Regeln gelten für die automatische Prüfung und deine manuelle Kontrolle.
                            </p>
                        </div>
                        <button
                            type="button"
                            class="rounded-md border border-border bg-inputBg px-4 py-2 text-sm font-semibold text-primary transition hover:bg-muted"
                            @click="router.visit(route('admin.mail-center.index', { type: 'inactive_account.first' }))"
                        >
                            Mail-Zentrale öffnen
                        </button>
                    </div>

                    <div class="mt-4 grid gap-3 md:grid-cols-2 xl:grid-cols-5">
                        <article
                            v-for="rule in inactiveRules"
                            :key="rule.month"
                            class="rounded-lg border border-border bg-inputBg p-4"
                        >
                            <p class="text-xs font-bold uppercase text-buttonPrimary">{{ rule.month }}</p>
                            <h3 class="mt-2 text-sm font-semibold text-primary">{{ rule.title }}</h3>
                            <p class="mt-2 text-xs leading-5 text-secondary">{{ rule.description }}</p>
                        </article>
                    </div>
                </section>

                <section class="overflow-hidden rounded-lg border border-border bg-card shadow-sm">
                    <div class="grid gap-3 border-b border-border p-4 lg:grid-cols-[1fr_220px_auto]">
                        <div class="relative">
                            <i class="las la-search absolute left-3 top-1/2 -translate-y-1/2 text-lg text-secondary"></i>
                            <input
                                v-model="inactiveSearch"
                                type="search"
                                class="w-full rounded-md border border-border bg-inputBg py-2 pl-10 pr-3 text-sm text-primary placeholder-secondary focus:border-buttonPrimary focus:ring-1 focus:ring-buttonPrimary"
                                placeholder="Name oder E-Mail suchen"
                            />
                        </div>

                        <select
                            v-model="inactiveStage"
                            class="rounded-md border border-border bg-inputBg px-3 py-2 text-sm text-primary focus:border-buttonPrimary focus:ring-1 focus:ring-buttonPrimary"
                        >
                            <option value="all">Alle Nutzer</option>
                            <option value="12">12+ Monate inaktiv</option>
                            <option value="18">18+ Monate inaktiv</option>
                            <option value="24">24+ Monate inaktiv</option>
                            <option value="36">36+ Monate inaktiv</option>
                            <option value="mail_failed">Mail fehlgeschlagen</option>
                        </select>

                        <button
                            type="button"
                            class="rounded-md border border-border bg-inputBg px-4 py-2 text-sm font-semibold text-primary transition hover:bg-muted"
                            @click="clearInactiveFilters"
                        >
                            Zurücksetzen
                        </button>
                    </div>

                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-border">
                            <thead class="bg-card">
                                <tr>
                                    <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-primary">Nutzer</th>
                                    <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-primary">Letzter Login</th>
                                    <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-primary">DSGVO-Status</th>
                                    <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-primary">Mailstatus</th>
                                    <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-primary">Aktionen</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-border bg-table">
                                <tr v-for="user in inactiveUsers.data" :key="user.id">
                                    <td class="px-4 py-4">
                                        <div class="flex items-center gap-3">
                                            <img
                                                v-if="user.profile_photo_url"
                                                :src="user.profile_photo_url"
                                                :alt="user.name"
                                                class="h-10 w-10 rounded-full object-cover"
                                            />
                                            <div
                                                v-else
                                                class="flex h-10 w-10 items-center justify-center rounded-full bg-buttonPrimary text-sm font-bold text-buttonTextPrimary"
                                            >
                                                {{ initials(user.name) }}
                                            </div>
                                            <div>
                                                <p class="font-semibold text-primary">{{ user.name }}</p>
                                                <p class="text-xs text-secondary">{{ user.email }}</p>
                                            </div>
                                        </div>
                                    </td>

                                    <td class="px-4 py-4 text-sm text-primary">
                                        <p>{{ user.last_login_at || user.last_seen_at || '-' }}</p>
                                        <p class="mt-1 text-xs text-secondary">
                                            {{ user.inactive_days ?? '-' }} Tage inaktiv
                                        </p>
                                    </td>

                                    <td class="px-4 py-4 text-sm">
                                        <span class="rounded-full px-3 py-1 text-xs font-semibold" :class="stageClass(user.recommended_stage)">
                                            {{ stageLabel(user.recommended_stage) }}
                                        </span>
                                        <p class="mt-2 text-xs text-secondary">
                                            Datenschutz: {{ user.privacy_status }} · Profil: {{ user.profile_visibility }}
                                        </p>
                                        <p v-if="user.deletion_scheduled_at" class="mt-1 text-xs text-error">
                                            Anonymisierung geplant: {{ user.deletion_scheduled_at }}
                                        </p>
                                    </td>

                                    <td class="px-4 py-4 text-sm">
                                        <div class="space-y-1 text-xs text-secondary">
                                            <p>12M: {{ user.first_warning_sent_at || 'nicht gesendet' }}</p>
                                            <p>18M: {{ user.second_warning_sent_at || 'nicht gesendet' }}</p>
                                        </div>
                                        <div v-if="user.last_mail" class="mt-2">
                                            <span class="rounded-full px-2 py-1 text-xs font-semibold" :class="mailBadgeClass(user.last_mail.status)">
                                                {{ user.last_mail.status }}
                                            </span>
                                            <p class="mt-1 max-w-xs truncate text-xs text-secondary">
                                                {{ user.last_mail.type }} · {{ user.last_mail.created_at }}
                                            </p>
                                            <p v-if="user.last_mail.error_message" class="mt-1 max-w-xs truncate text-xs text-error">
                                                {{ user.last_mail.error_message }}
                                            </p>
                                        </div>
                                    </td>

                                    <td class="px-4 py-4">
                                        <div class="flex flex-wrap gap-2">
                                            <button
                                                type="button"
                                                class="rounded-md border border-border bg-inputBg px-3 py-2 text-xs font-semibold text-primary transition hover:bg-muted disabled:cursor-not-allowed disabled:opacity-60"
                                                :disabled="sendingNoticeId === `${user.id}-first`"
                                                @click="sendInactivityNotice(user, 'first')"
                                            >
                                                12M-Mail
                                            </button>
                                            <button
                                                type="button"
                                                class="rounded-md border border-border bg-inputBg px-3 py-2 text-xs font-semibold text-primary transition hover:bg-muted disabled:cursor-not-allowed disabled:opacity-60"
                                                :disabled="sendingNoticeId === `${user.id}-second`"
                                                @click="sendInactivityNotice(user, 'second')"
                                            >
                                                18M-Mail
                                            </button>
                                            <button
                                                type="button"
                                                class="rounded-md border border-error/40 bg-error/10 px-3 py-2 text-xs font-semibold text-error transition hover:bg-error/20 disabled:cursor-not-allowed disabled:opacity-60"
                                                :disabled="sendingNoticeId === `${user.id}-scheduled`"
                                                @click="sendInactivityNotice(user, 'scheduled')"
                                            >
                                                24M-Hinweis
                                            </button>
                                        </div>
                                    </td>
                                </tr>

                                <tr v-if="inactiveUsers.data.length === 0">
                                    <td colspan="5" class="px-4 py-8 text-center text-sm text-secondary">
                                        Keine passenden Nutzer gefunden.
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>

                    <div class="flex flex-col gap-3 border-t border-border p-4 sm:flex-row sm:items-center sm:justify-between">
                        <p class="text-sm text-secondary">
                            <span v-if="inactiveUsers.total > 0">
                                Zeige {{ inactiveUsers.from }} bis {{ inactiveUsers.to }} von {{ inactiveUsers.total }} Nutzern.
                            </span>
                            <span v-else>Keine Nutzer vorhanden.</span>
                        </p>

                        <div v-if="inactiveUsers.links.length > 3" class="flex flex-wrap gap-1">
                            <button
                                v-for="link in inactiveUsers.links"
                                :key="link.label"
                                type="button"
                                :disabled="!link.url"
                                @click="visitPage(link.url)"
                                class="min-w-10 rounded border border-border px-3 py-2 text-sm transition disabled:cursor-not-allowed disabled:opacity-50"
                                :class="link.active
                                    ? 'bg-primary text-buttonTextPrimary'
                                    : 'bg-card text-primary hover:bg-secondary/20'"
                                v-html="link.label"
                            />
                        </div>
                    </div>
                </section>
            </section>
        </div>

        <DeleteConfirmModal
            :show="showDeleteModal"
            title="Nutzer löschen"
            :message="`Sind Sie sicher, dass Sie ${userToDelete?.name} löschen möchten? Diese Aktion kann nicht rückgängig gemacht werden.`"
            confirm-text="delete"
            @confirm="handleDeleteConfirm"
            @cancel="handleDeleteCancel"
        />
    </AppLayout>
</template>
