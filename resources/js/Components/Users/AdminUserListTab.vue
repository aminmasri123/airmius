<script setup>
import { computed } from 'vue'
import { useI18n } from 'vue-i18n'

const { locale } = useI18n()
const localeCode = computed(() => ({ ar: 'ar-EG', fr: 'fr-FR', en: 'en-US', de: 'de-DE' })[locale.value] || 'de-DE')

const props = defineProps({
    searchQuery: { type: String, default: '' },
    statusFilter: { type: String, default: 'all' },
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
})

const emit = defineEmits([
    'clear',
    'create',
    'delete',
    'edit',
    'page',
    'update:searchQuery',
    'update:statusFilter',
])

const search = computed({
    get: () => props.searchQuery,
    set: (value) => emit('update:searchQuery', value),
})

const status = computed({
    get: () => props.statusFilter,
    set: (value) => emit('update:statusFilter', value),
})

const formatDate = (value) => value ? new Date(value).toLocaleString(localeCode.value) : '-'

const statusLabel = (user) => {
    if (user.account_status === 'suspended') {
        return user.suspended_until ? `Gesperrt bis ${formatDate(user.suspended_until)}` : 'Gesperrt'
    }

    return 'Aktiv'
}
</script>

<template>
    <section class="space-y-4">
        <div class="grid gap-2 rounded-lg border border-border bg-card p-3 lg:grid-cols-[auto_1fr_180px_auto]">
            <button
                type="button"
                class="inline-flex items-center justify-center gap-2 rounded-lg bg-buttonPrimary px-4 py-2 text-sm font-semibold text-buttonTextPrimary transition hover:bg-primary/90"
                @click="emit('create')"
            >
                <i class="las la-plus text-lg"></i>
                Nutzer
            </button>

            <div class="relative">
                <i class="las la-search absolute left-3 top-1/2 -translate-y-1/2 text-lg text-secondary"></i>
                <input
                    v-model="search"
                    type="search"
                    placeholder="Suche nach Name oder E-Mail..."
                    class="w-full rounded-lg border border-border bg-inputBg py-2 pl-10 pr-3 text-sm text-primary placeholder-secondary focus:border-buttonPrimary focus:ring-1 focus:ring-buttonPrimary"
                />
            </div>

            <select v-model="status" class="rounded-lg border border-border bg-inputBg px-3 py-2 text-sm text-primary focus:border-buttonPrimary focus:ring-1 focus:ring-buttonPrimary">
                <option value="all">Alle Status</option>
                <option value="active">Aktiv</option>
                <option value="suspended">Gesperrt</option>
            </select>

            <button
                type="button"
                class="inline-flex items-center justify-center gap-2 rounded-lg border border-border bg-inputBg px-4 py-2 text-sm font-semibold text-primary transition hover:bg-muted"
                @click="emit('clear')"
            >
                <i class="las la-sync text-lg"></i>
                Zurücksetzen
            </button>
        </div>

        <div class="overflow-hidden rounded-lg border border-border bg-card shadow-sm">
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-border">
                    <thead class="bg-card">
                        <tr>
                            <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-primary">ID</th>
                            <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-primary">Name</th>
                            <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-primary">E-Mail</th>
                            <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-primary">Status</th>
                            <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-primary">Erstellt am</th>
                            <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-primary">Aktionen</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-border bg-table">
                        <tr v-for="user in users.data" :key="user.id">
                            <td class="px-4 py-4 text-sm text-primary">{{ user.id }}</td>
                            <td class="px-4 py-4 text-sm font-semibold text-primary">{{ user.name }}</td>
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
                                <div class="flex flex-wrap gap-2">
                                    <button
                                        type="button"
                                        class="inline-flex items-center gap-1 rounded bg-primary px-3 py-1 text-buttonTextPrimary transition-colors hover:bg-primary/80"
                                        @click="emit('edit', user)"
                                    >
                                        <i class="las la-edit"></i>
                                        Bearbeiten
                                    </button>
                                    <button
                                        type="button"
                                        class="inline-flex items-center gap-1 rounded bg-error px-3 py-1 text-buttonTextSecondary transition-colors hover:bg-error/80"
                                        @click="emit('delete', user)"
                                    >
                                        <i class="las la-trash"></i>
                                        Löschen
                                    </button>
                                </div>
                            </td>
                        </tr>

                        <tr v-if="users.data.length === 0">
                            <td colspan="6" class="px-4 py-8 text-center text-sm text-secondary">
                                Keine Nutzer gefunden.
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>

        <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
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
                    class="min-w-10 rounded border border-border px-3 py-2 text-sm transition disabled:cursor-not-allowed disabled:opacity-50"
                    :class="link.active
                        ? 'bg-primary text-buttonTextPrimary'
                        : 'bg-card text-primary hover:bg-secondary/20'"
                    @click="emit('page', link.url)"
                    v-html="link.label"
                />
            </div>
        </div>
    </section>
</template>
