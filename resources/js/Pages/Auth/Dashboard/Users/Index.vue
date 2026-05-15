<script setup>
import { Head, router } from '@inertiajs/vue3'
import AppLayout from '@/Components/Auth/Layouts/AppLayout.vue'
import DeleteConfirmModal from '@/Components/Auth/DeleteConfirmModal.vue'
import { computed, ref, watch } from 'vue'

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
        }),
    },
    warnings: {
        type: Array,
        default: () => [],
    },
})

const searchQuery = ref(props.filters.search || '')
const statusFilter = ref(props.filters.status || 'all')
const activeTab = ref('users')
const warningCategoryFilter = ref('all')
const showDeleteModal = ref(false)
const userToDelete = ref(null)

const createUser = () => {
    router.visit(route('members.create'))
}

const clearSearch = () => {
    searchQuery.value = ''
    statusFilter.value = 'all'
}

let searchTimeout = null
watch([searchQuery, statusFilter], ([search, status]) => {
    clearTimeout(searchTimeout)

    searchTimeout = setTimeout(() => {
        router.get(route('members.index'), {
            search: search || undefined,
            status: status !== 'all' ? status : undefined,
        }, {
            preserveState: true,
            replace: true,
            only: ['users', 'filters'],
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

const formatDate = (value) => value ? new Date(value).toLocaleString('de-DE') : '-'

const statusLabel = (user) => {
    if (user.account_status === 'suspended') {
        return user.suspended_until ? `Gesperrt bis ${formatDate(user.suspended_until)}` : 'Gesperrt'
    }

    return 'Aktiv'
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
</script>

<template>
    <AppLayout>

        <Head title="Nutzerverwaltung" />

        <div class="space-y-6">
            <div class="mb-6">
                <h1 class="text-2xl font-semibold text-primary">Admin Dashboard – Nutzer</h1>
                <p class="mt-2 text-sm text-secondary">
                    Übersicht aller registrierten Nutzer.
                </p>
            </div>

            <div class="flex flex-wrap gap-2 rounded-lg border border-border bg-card p-3">
                <button
                    type="button"
                    @click="activeTab = 'users'"
                    class="rounded-md px-4 py-2 text-sm font-semibold transition"
                    :class="activeTab === 'users' ? 'bg-buttonPrimary text-buttonTextPrimary' : 'text-secondary hover:bg-secondary/10 hover:text-primary'"
                >
                    Nutzer
                    <span class="ml-2 rounded-full bg-secondary/20 px-2 py-0.5 text-xs">{{ users.total }}</span>
                </button>
                <button
                    type="button"
                    @click="activeTab = 'warnings'"
                    class="rounded-md px-4 py-2 text-sm font-semibold transition"
                    :class="activeTab === 'warnings' ? 'bg-buttonPrimary text-buttonTextPrimary' : 'text-secondary hover:bg-secondary/10 hover:text-primary'"
                >
                    Warnungen
                    <span class="ml-2 rounded-full bg-secondary/20 px-2 py-0.5 text-xs">{{ warnings.length }}</span>
                </button>
            </div>

            <!-- Suchfeld und Aktionen -->
            <div v-if="activeTab === 'users'" class="flex mb-4 ">
                <button @click="createUser"
                    class="inline-flex items-center justify-center px-4 py-2 bg-buttonPrimary text-buttonTextPrimary rounded-l-lg hover:bg-primary/90 transition">
                    <i class="las la-plus "></i>

                </button>

                <div class="relative flex-1">
                    <input v-model="searchQuery" type="text" placeholder="Suche nach Name oder E-Mail..."
                        class="w-full px-4 py-2 border border-border bg-card text-primary placeholder-secondary focus:ring-1 focus:ring-bg " />
                    
                </div>
                    <select v-model="statusFilter" class="border border-border bg-card px-4 py-2 text-primary focus:ring-1 focus:ring-bg">
                        <option value="all">Alle Status</option>
                        <option value="active">Aktiv</option>
                        <option value="suspended">Gesperrt</option>
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
                                        Bearbeiten
                                    </button>
                                    <button @click="openDeleteModal(user)"
                                        class="px-3 py-1 bg-error text-buttonTextSecondary rounded hover:bg-error/80 transition-colors">
                                        Löschen
                                    </button>
                                </div>
                            </td>
                        </tr>

                        <tr v-if="users.data.length === 0">
                            <td colspan="6" class="px-4 py-4 text-center text-sm text-secondary">
                                Keine Nutzer gefunden.
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

            <section v-else class="overflow-hidden rounded-lg border border-border bg-card shadow-sm">
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
