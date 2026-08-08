<script setup>
import { computed } from 'vue'
import { useI18n } from 'vue-i18n'

const { locale } = useI18n()
const localeCode = computed(() => ({ ar: 'ar-EG', fr: 'fr-FR', en: 'en-US', de: 'de-DE' })[locale.value] || 'de-DE')

const props = defineProps({
    categoryFilter: { type: String, default: 'all' },
    warnings: { type: Array, default: () => [] },
})

const emit = defineEmits(['edit', 'update:categoryFilter'])

const selectedCategory = computed({
    get: () => props.categoryFilter,
    set: (value) => emit('update:categoryFilter', value),
})

const warningCategories = computed(() => {
    const categories = new Set()

    props.warnings.forEach((warning) => {
        ;(warning.flag?.categories || []).forEach((category) => categories.add(category))
    })

    return Array.from(categories).sort()
})

const filteredWarnings = computed(() => {
    if (selectedCategory.value === 'all') {
        return props.warnings
    }

    return props.warnings.filter((warning) =>
        (warning.flag?.categories || []).includes(selectedCategory.value),
    )
})

const formatDate = (value) => value ? new Date(value).toLocaleString(localeCode.value) : '-'

const badgeClass = (severity) => {
    if (severity === 'high') return 'bg-error/10 text-error'
    if (severity === 'medium') return 'bg-warning/10 text-warning'
    return 'bg-secondary/20 text-secondary'
}
</script>

<template>
    <section class="overflow-hidden rounded-lg border border-border bg-card shadow-sm">
        <div class="flex flex-col gap-3 border-b border-border p-4 sm:flex-row sm:items-center sm:justify-between">
            <div>
                <h2 class="text-lg font-semibold text-primary">User-Warnungen</h2>
                <p class="text-sm text-secondary">
                    Hier siehst du, welche Nutzer bereits Moderationswarnungen bekommen haben.
                </p>
            </div>

            <select
                v-model="selectedCategory"
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
                        <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-primary">{{ $t('Kategorie') }}</th>
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
                                class="inline-flex items-center gap-1 rounded bg-primary px-3 py-1 text-buttonTextPrimary transition-colors hover:bg-primary/80"
                                @click="emit('edit', warning.user)"
                            >
                                <i class="las la-edit"></i>
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
</template>
