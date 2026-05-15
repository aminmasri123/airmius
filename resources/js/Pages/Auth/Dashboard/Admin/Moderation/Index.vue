<script setup>
import AppLayout from '@/Components/Auth/Layouts/AppLayout.vue'
import { Head, router, usePage } from '@inertiajs/vue3'
import { computed, ref } from 'vue'

defineOptions({ layout: AppLayout })

const props = defineProps({
    flags: { type: Array, default: () => [] },
    reports: { type: Array, default: () => [] },
    warnings: { type: Array, default: () => [] },
    warningSummary: { type: Object, default: () => ({}) },
})

const page = usePage()
const activeTab = ref('reports')
const warningCategoryFilter = ref('all')

const openReports = computed(() => props.reports.filter((item) => item.status === 'open'))
const openFlags = computed(() => props.flags.filter((item) => item.status === 'open'))
const warningCategories = computed(() => [...new Set(props.warnings.flatMap((warning) => warning.flag?.categories || []))].sort())
const filteredWarnings = computed(() => {
    if (warningCategoryFilter.value === 'all') {
        return props.warnings
    }

    return props.warnings.filter((warning) => (warning.flag?.categories || []).includes(warningCategoryFilter.value))
})
const storageUrl = (path) => path?.startsWith('http') ? path : `${page.props.uploads?.url || '/storage'}/${path}`

const badgeClass = (severity) => ({
    high: 'bg-error/10 text-error border-error/30',
    medium: 'bg-warning/10 text-warning border-warning/30',
    low: 'bg-muted text-secondary border-border',
}[severity] || 'bg-muted text-secondary border-border')

const contentLabel = (content) => {
    if (!content) return 'Gelöschter Inhalt'
    return `${content.type} #${content.id}`
}

const updateReport = (report, status, removeContent = false) => {
    router.put(route('admin.moderation.reports.update', report.id), {
        status,
        remove_content: removeContent,
    }, {
        preserveScroll: true,
    })
}

const updateFlag = (flag, status, removeContent = false) => {
    router.put(route('admin.moderation.flags.update', flag.id), {
        status,
        remove_content: removeContent,
    }, {
        preserveScroll: true,
    })
}
</script>

<template>
    <Head title="Moderation" />

    <div class="space-y-5">
        <section class="surface-card p-5">
            <div class="flex flex-col gap-4 md:flex-row md:items-end md:justify-between">
                <div>
                    <p class="text-xs font-semibold uppercase tracking-wide text-secondary">Admin</p>
                    <h1 class="mt-1 text-2xl font-semibold text-primary">Moderation</h1>
                    <p class="mt-2 max-w-2xl text-sm text-secondary">
                        Prüfe gemeldete und automatisch markierte Inhalte aus Feed, Kommentaren und Chat.
                    </p>
                </div>

                <div class="grid grid-cols-2 gap-3 md:grid-cols-4">
                    <div class="rounded-lg border border-border bg-bg px-4 py-3">
                        <p class="text-xs text-secondary">Offene Meldungen</p>
                        <p class="mt-1 text-2xl font-semibold text-primary">{{ openReports.length }}</p>
                    </div>
                    <div class="rounded-lg border border-border bg-bg px-4 py-3">
                        <p class="text-xs text-secondary">Automatische Treffer</p>
                        <p class="mt-1 text-2xl font-semibold text-primary">{{ openFlags.length }}</p>
                    </div>
                    <div class="rounded-lg border border-border bg-bg px-4 py-3">
                        <p class="text-xs text-secondary">Warnungen 90 Tage</p>
                        <p class="mt-1 text-2xl font-semibold text-primary">{{ warningSummary.warnings_90_days || 0 }}</p>
                        <p class="mt-1 text-xs text-secondary">{{ warningSummary.users_with_warnings_90_days || 0 }} Nutzer</p>
                    </div>
                    <div class="rounded-lg border border-danger/30 bg-danger/10 px-4 py-3">
                        <p class="text-xs text-secondary">Gesperrte Konten</p>
                        <p class="mt-1 text-2xl font-semibold text-primary">{{ warningSummary.suspended_users || 0 }}</p>
                    </div>
                </div>
            </div>
        </section>

        <section class="surface-card overflow-hidden">
            <div class="flex gap-2 border-b border-border p-3">
                <button
                    type="button"
                    class="rounded-lg px-4 py-2 text-sm font-semibold"
                    :class="activeTab === 'reports' ? 'bg-buttonPrimary text-buttonTextPrimary' : 'bg-muted text-secondary'"
                    @click="activeTab = 'reports'"
                >
                    Meldungen
                </button>
                <button
                    type="button"
                    class="rounded-lg px-4 py-2 text-sm font-semibold"
                    :class="activeTab === 'flags' ? 'bg-buttonPrimary text-buttonTextPrimary' : 'bg-muted text-secondary'"
                    @click="activeTab = 'flags'"
                >
                    Automatisch markiert
                </button>
                <button
                    type="button"
                    class="rounded-lg px-4 py-2 text-sm font-semibold"
                    :class="activeTab === 'warnings' ? 'bg-buttonPrimary text-buttonTextPrimary' : 'bg-muted text-secondary'"
                    @click="activeTab = 'warnings'"
                >
                    Warnungen
                </button>
            </div>

            <div v-if="page.props.flash?.success" class="m-4 rounded-lg border border-success/30 bg-success/10 px-4 py-3 text-sm text-success">
                {{ page.props.flash.success }}
            </div>

            <div v-if="activeTab === 'reports'" class="divide-y divide-border">
                <article v-for="report in reports" :key="report.id" class="grid gap-4 p-4 lg:grid-cols-[1fr_18rem]">
                    <div>
                        <div class="flex flex-wrap items-center gap-2">
                            <span class="rounded border border-border bg-bg px-2 py-1 text-xs font-semibold text-primary">
                                {{ contentLabel(report.content) }}
                            </span>
                            <span class="rounded border border-border px-2 py-1 text-xs text-secondary">
                                {{ report.reason }}
                            </span>
                            <span class="rounded border border-border px-2 py-1 text-xs text-secondary">
                                {{ report.status }}
                            </span>
                        </div>

                        <p class="mt-3 whitespace-pre-line rounded-lg bg-bg p-3 text-sm leading-6 text-primary">
                            {{ report.content?.text || 'Kein Inhalt mehr vorhanden.' }}
                        </p>

                        <img
                            v-if="report.content?.image"
                            :src="storageUrl(report.content.image)"
                            alt=""
                            class="mt-3 max-h-96 w-full rounded-lg border border-border object-contain bg-bg"
                        />

                        <p v-if="report.details" class="mt-2 text-sm text-secondary">
                            Hinweis: {{ report.details }}
                        </p>
                        <p class="mt-2 text-xs text-secondary">
                            Gemeldet von {{ report.reporter?.name || 'Unbekannt' }} · {{ report.created_at }}
                        </p>
                    </div>

                    <div class="flex flex-col gap-2">
                        <button class="btn" @click="updateReport(report, 'dismissed')">Als unkritisch schließen</button>
                        <button class="btn-primary" @click="updateReport(report, 'actioned')">Als bearbeitet markieren</button>
                        <button class="rounded-lg bg-error px-4 py-2 text-sm font-semibold text-white" @click="updateReport(report, 'actioned', true)">
                            Inhalt entfernen
                        </button>
                    </div>
                </article>

                <div v-if="!reports.length" class="p-8 text-center text-sm text-secondary">
                    Keine Meldungen vorhanden.
                </div>
            </div>

            <div v-else-if="activeTab === 'flags'" class="divide-y divide-border">
                <article v-for="flag in flags" :key="flag.id" class="grid gap-4 p-4 lg:grid-cols-[1fr_18rem]">
                    <div>
                        <div class="flex flex-wrap items-center gap-2">
                            <span class="rounded border border-border bg-bg px-2 py-1 text-xs font-semibold text-primary">
                                {{ contentLabel(flag.content) }}
                            </span>
                            <span class="rounded border px-2 py-1 text-xs font-semibold" :class="badgeClass(flag.severity)">
                                {{ flag.severity }}
                            </span>
                            <span class="rounded border border-border px-2 py-1 text-xs text-secondary">
                                {{ flag.status }}
                            </span>
                            <span v-if="flag.automated_action" class="rounded border border-warning/40 bg-warning/10 px-2 py-1 text-xs text-warning">
                                {{ flag.automated_action }}
                            </span>
                        </div>

                        <p class="mt-3 whitespace-pre-line rounded-lg bg-bg p-3 text-sm leading-6 text-primary">
                            {{ flag.content?.text || 'Kein Inhalt mehr vorhanden.' }}
                        </p>

                        <img
                            v-if="flag.content?.image"
                            :src="storageUrl(flag.content.image)"
                            alt=""
                            class="mt-3 max-h-96 w-full rounded-lg border border-border object-contain bg-bg"
                        />

                        <div class="mt-2 flex flex-wrap gap-2 text-xs text-secondary">
                            <span v-for="category in flag.categories" :key="category" class="rounded bg-muted px-2 py-1">
                                {{ category }}
                            </span>
                            <span v-for="term in flag.matched_terms" :key="term" class="rounded bg-muted px-2 py-1">
                                Treffer: {{ term }}
                            </span>
                        </div>
                        <p class="mt-2 text-xs text-secondary">
                            Quelle: {{ flag.source }} · Benutzer: {{ flag.user?.name || 'System' }} · {{ flag.created_at }}
                        </p>
                    </div>

                    <div class="flex flex-col gap-2">
                        <button class="btn" @click="updateFlag(flag, 'dismissed')">Als unkritisch schließen</button>
                        <button class="btn-primary" @click="updateFlag(flag, 'actioned')">Als bearbeitet markieren</button>
                        <button class="rounded-lg bg-error px-4 py-2 text-sm font-semibold text-white" @click="updateFlag(flag, 'actioned', true)">
                            Inhalt entfernen
                        </button>
                    </div>
                </article>

                <div v-if="!flags.length" class="p-8 text-center text-sm text-secondary">
                    Keine automatischen Treffer vorhanden.
                </div>
            </div>

            <div v-else>
                <div class="flex flex-col gap-3 border-b border-border p-4 sm:flex-row sm:items-center sm:justify-between">
                    <div>
                        <h2 class="text-lg font-semibold text-primary">User-Warnungen</h2>
                        <p class="mt-1 text-sm text-secondary">Hier siehst du, wer bereits Warnpunkte hat und aus welcher Kategorie sie stammen.</p>
                    </div>
                    <select v-model="warningCategoryFilter" class="rounded-lg border-border bg-inputBg text-sm text-primary">
                        <option value="all">Alle Kategorien</option>
                        <option v-for="category in warningCategories" :key="category" :value="category">{{ category }}</option>
                    </select>
                </div>

                <div class="overflow-x-auto">
                    <table class="min-w-full text-left text-sm">
                        <thead class="border-b border-border bg-bg">
                            <tr>
                                <th class="px-4 py-3 text-xs font-semibold uppercase tracking-wide text-secondary">User</th>
                                <th class="px-4 py-3 text-xs font-semibold uppercase tracking-wide text-secondary">Kategorie</th>
                                <th class="px-4 py-3 text-xs font-semibold uppercase tracking-wide text-secondary">Severity</th>
                                <th class="px-4 py-3 text-xs font-semibold uppercase tracking-wide text-secondary">Punkte</th>
                                <th class="px-4 py-3 text-xs font-semibold uppercase tracking-wide text-secondary">Grund</th>
                                <th class="px-4 py-3 text-xs font-semibold uppercase tracking-wide text-secondary">Datum</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-border">
                            <tr v-for="warning in filteredWarnings" :key="warning.id">
                                <td class="px-4 py-3">
                                    <p class="font-semibold text-primary">{{ warning.user?.name || 'Unbekannt' }}</p>
                                    <p class="text-xs text-secondary">{{ warning.user?.email || '-' }}</p>
                                    <p v-if="warning.user?.account_status === 'suspended'" class="mt-1 text-xs font-semibold text-error">
                                        Gesperrt bis {{ warning.user?.suspended_until || '-' }}
                                    </p>
                                </td>
                                <td class="px-4 py-3">
                                    <div class="flex flex-wrap gap-1">
                                        <span v-for="category in warning.flag?.categories || []" :key="category" class="rounded bg-muted px-2 py-1 text-xs text-secondary">
                                            {{ category }}
                                        </span>
                                        <span v-if="!(warning.flag?.categories || []).length" class="text-secondary">-</span>
                                    </div>
                                    <div v-if="(warning.flag?.matched_terms || []).length" class="mt-1 flex flex-wrap gap-1">
                                        <span v-for="term in warning.flag.matched_terms" :key="term" class="rounded bg-warning/10 px-2 py-1 text-xs text-warning">
                                            {{ term }}
                                        </span>
                                    </div>
                                </td>
                                <td class="px-4 py-3">
                                    <span class="rounded border px-2 py-1 text-xs font-semibold" :class="badgeClass(warning.severity)">
                                        {{ warning.severity }}
                                    </span>
                                </td>
                                <td class="px-4 py-3 font-semibold text-primary">{{ warning.points }}</td>
                                <td class="max-w-sm px-4 py-3 text-secondary">{{ warning.reason }}</td>
                                <td class="px-4 py-3 text-secondary">{{ warning.created_at }}</td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <div v-if="!filteredWarnings.length" class="p-8 text-center text-sm text-secondary">
                    Keine Warnungen zu diesem Filter vorhanden.
                </div>
            </div>
        </section>
    </div>
</template>
