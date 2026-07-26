<script setup>
import AppLayout from '@/Components/Auth/Layouts/AppLayout.vue'
import { Head, router, usePage } from '@inertiajs/vue3'
import { computed, ref } from 'vue'
import { confirmDialog } from '@/services/dialogService'
import { useI18n } from 'vue-i18n'

defineOptions({ layout: AppLayout })

const { t, locale } = useI18n()
const tx = (key, fallback, values = {}) => {
    const translated = t(key, values)
    return translated === key ? fallback : translated
}

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
const isVideo = (content) => content?.media_type?.startsWith('video/')
const localeCode = computed(() => ({ ar: 'ar-EG', fr: 'fr-FR', en: 'en-US', de: 'de-DE' })[locale.value] || 'de-DE')
const formatDateTime = (value) => value
    ? new Intl.DateTimeFormat(localeCode.value, { dateStyle: 'short', timeStyle: 'short' }).format(new Date(value))
    : tx('moderation.unknown_date', 'Unbekannt')

const badgeClass = (severity) => ({
    high: 'bg-error/10 text-error border-error/30',
    medium: 'bg-warning/10 text-warning border-warning/30',
    low: 'bg-muted text-secondary border-border',
}[severity] || 'bg-muted text-secondary border-border')

const contentLabel = (content) => {
    if (!content) return tx('moderation.deleted_content', 'Gelöschter Inhalt')
    return `${content.type} #${content.id}`
}

const persistReportUpdate = (report, status, removeContent) => {
    router.put(route('admin.moderation.reports.update', report.id), {
        status,
        remove_content: removeContent,
    }, {
        preserveScroll: true,
    })
}

const updateReport = async (report, status, removeContent = false) => {
    if (removeContent) {
        const confirmed = await confirmDialog({
            title: tx('moderation.remove_title', 'Inhalt entfernen'),
            message: tx('moderation.remove_message', 'Dieser Inhalt wird dauerhaft entfernt. Fortfahren?'),
            confirmLabel: tx('moderation.remove_confirm', 'Endgültig entfernen'),
            danger: true,
        })
        if (!confirmed) return
    }

    persistReportUpdate(report, status, removeContent)
}

const persistFlagUpdate = (flag, status, removeContent) => {
    router.put(route('admin.moderation.flags.update', flag.id), {
        status,
        remove_content: removeContent,
    }, {
        preserveScroll: true,
    })
}

const updateFlag = async (flag, status, removeContent = false) => {
    if (removeContent) {
        const confirmed = await confirmDialog({
            title: tx('moderation.remove_title', 'Inhalt entfernen'),
            message: tx('moderation.remove_message', 'Dieser Inhalt wird dauerhaft entfernt. Fortfahren?'),
            confirmLabel: tx('moderation.remove_confirm', 'Endgültig entfernen'),
            danger: true,
        })
        if (!confirmed) return
    }

    persistFlagUpdate(flag, status, removeContent)
}
</script>

<template>
    <Head :title="tx('moderation.page_title', 'Moderation')" />

    <div class="space-y-5">
        <section class="surface-card p-5">
            <div class="flex flex-col gap-4 md:flex-row md:items-end md:justify-between">
                <div>
                    <p class="text-xs font-semibold uppercase tracking-wide text-secondary">{{ tx('moderation.eyebrow', 'Admin') }}</p>
                    <h1 class="mt-1 text-2xl font-semibold text-primary">{{ tx('moderation.title', 'Moderation') }}</h1>
                    <p class="mt-2 max-w-2xl text-sm text-secondary">
                        {{ tx('moderation.intro', 'Prüfe gemeldete und automatisch markierte Inhalte aus Feed, Kommentaren und Chat.') }}
                    </p>
                </div>

                <div class="grid grid-cols-2 gap-3 md:grid-cols-4">
                    <div class="rounded-lg border border-border bg-bg px-4 py-3">
                        <p class="text-xs text-secondary">{{ tx('moderation.metrics.reports', 'Offene Meldungen') }}</p>
                        <p class="mt-1 text-2xl font-semibold text-primary">{{ openReports.length }}</p>
                    </div>
                    <div class="rounded-lg border border-border bg-bg px-4 py-3">
                        <p class="text-xs text-secondary">{{ tx('moderation.metrics.flags', 'Automatische Treffer') }}</p>
                        <p class="mt-1 text-2xl font-semibold text-primary">{{ openFlags.length }}</p>
                    </div>
                    <div class="rounded-lg border border-border bg-bg px-4 py-3">
                        <p class="text-xs text-secondary">{{ tx('moderation.metrics.warnings', 'Warnungen 90 Tage') }}</p>
                        <p class="mt-1 text-2xl font-semibold text-primary">{{ warningSummary.warnings_90_days || 0 }}</p>
                        <p class="mt-1 text-xs text-secondary">{{ warningSummary.users_with_warnings_90_days || 0 }} {{ tx('moderation.users', 'Nutzer') }}</p>
                    </div>
                    <div class="rounded-lg border border-danger/30 bg-danger/10 px-4 py-3">
                        <p class="text-xs text-secondary">{{ tx('moderation.metrics.suspended', 'Gesperrte Konten') }}</p>
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
                    {{ tx('moderation.tabs.reports', 'Meldungen') }}
                </button>
                <button
                    type="button"
                    class="rounded-lg px-4 py-2 text-sm font-semibold"
                    :class="activeTab === 'flags' ? 'bg-buttonPrimary text-buttonTextPrimary' : 'bg-muted text-secondary'"
                    @click="activeTab = 'flags'"
                >
                    {{ tx('moderation.tabs.flags', 'Automatisch markiert') }}
                </button>
                <button
                    type="button"
                    class="rounded-lg px-4 py-2 text-sm font-semibold"
                    :class="activeTab === 'warnings' ? 'bg-buttonPrimary text-buttonTextPrimary' : 'bg-muted text-secondary'"
                    @click="activeTab = 'warnings'"
                >
                    {{ tx('moderation.tabs.warnings', 'Warnungen') }}
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

                        <video
                            v-if="report.content?.image && isVideo(report.content)"
                            :src="storageUrl(report.content.image)"
                            controls
                            class="mt-3 max-h-96 w-full rounded-lg border border-border bg-black"
                        ></video>

                        <img
                            v-else-if="report.content?.image"
                            :src="storageUrl(report.content.image)"
                            alt=""
                            class="mt-3 max-h-96 w-full rounded-lg border border-border object-contain bg-bg"
                        />

                        <p v-if="report.details" class="mt-2 text-sm text-secondary">
                            {{ tx('moderation.note', 'Hinweis:') }} {{ report.details }}
                        </p>
                        <p class="mt-2 text-xs text-secondary">
                            {{ tx('moderation.reported_by', 'Gemeldet von') }} {{ report.reporter?.name || tx('moderation.unknown_user', 'Unbekannt') }} · {{ formatDateTime(report.created_at) }}
                        </p>
                    </div>

                    <div class="flex flex-col gap-2">
                        <button class="btn" @click="updateReport(report, 'dismissed')">{{ tx('moderation.actions.dismiss', 'Als unkritisch schließen') }}</button>
                        <button class="btn-primary" @click="updateReport(report, 'actioned')">{{ tx('moderation.actions.actioned', 'Als bearbeitet markieren') }}</button>
                        <button class="rounded-lg bg-error px-4 py-2 text-sm font-semibold text-white" @click="updateReport(report, 'actioned', true)">
                            {{ tx('moderation.actions.remove', 'Inhalt entfernen') }}
                        </button>
                    </div>
                </article>

                <div v-if="!reports.length" class="p-8 text-center text-sm text-secondary">
                    {{ tx('moderation.empty.reports', 'Keine Meldungen vorhanden.') }}
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

                        <video
                            v-if="flag.content?.image && isVideo(flag.content)"
                            :src="storageUrl(flag.content.image)"
                            controls
                            class="mt-3 max-h-96 w-full rounded-lg border border-border bg-black"
                        ></video>

                        <img
                            v-else-if="flag.content?.image"
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
                            {{ tx('moderation.source', 'Quelle:') }} {{ flag.source }} · {{ tx('moderation.user', 'Benutzer:') }} {{ flag.user?.name || tx('moderation.system', 'System') }} · {{ formatDateTime(flag.created_at) }}
                        </p>
                    </div>

                    <div class="flex flex-col gap-2">
                        <button class="btn" @click="updateFlag(flag, 'dismissed')">{{ tx('moderation.actions.dismiss', 'Als unkritisch schließen') }}</button>
                        <button class="btn-primary" @click="updateFlag(flag, 'actioned')">{{ tx('moderation.actions.actioned', 'Als bearbeitet markieren') }}</button>
                        <button class="rounded-lg bg-error px-4 py-2 text-sm font-semibold text-white" @click="updateFlag(flag, 'actioned', true)">
                            {{ tx('moderation.actions.remove', 'Inhalt entfernen') }}
                        </button>
                    </div>
                </article>

                <div v-if="!flags.length" class="p-8 text-center text-sm text-secondary">
                    {{ tx('moderation.empty.flags', 'Keine automatischen Treffer vorhanden.') }}
                </div>
            </div>

            <div v-else>
                <div class="flex flex-col gap-3 border-b border-border p-4 sm:flex-row sm:items-center sm:justify-between">
                    <div>
                        <h2 class="text-lg font-semibold text-primary">{{ tx('users_admin_ui.warning_title', 'User-Warnungen') }}</h2>
                        <p class="mt-1 text-sm text-secondary">{{ tx('users_admin.intro', 'Übersicht der Warnpunkte und Kategorien.') }}</p>
                    </div>
                    <select v-model="warningCategoryFilter" class="rounded-lg border-border bg-inputBg text-sm text-primary">
                        <option value="all">{{ tx('users_admin_ui.all_categories', 'Alle Kategorien') }}</option>
                        <option v-for="category in warningCategories" :key="category" :value="category">{{ category }}</option>
                    </select>
                </div>

                <div class="overflow-x-auto">
                    <table class="min-w-full text-left text-sm">
                        <thead class="border-b border-border bg-bg">
                            <tr>
                                <th class="px-4 py-3 text-xs font-semibold uppercase tracking-wide text-secondary">{{ tx('users_admin_ui.user', 'User') }}</th>
                                <th class="px-4 py-3 text-xs font-semibold uppercase tracking-wide text-secondary">{{ tx('users_admin_ui.category', 'Kategorie') }}</th>
                                <th class="px-4 py-3 text-xs font-semibold uppercase tracking-wide text-secondary">{{ tx('users_admin_ui.severity', 'Severity') }}</th>
                                <th class="px-4 py-3 text-xs font-semibold uppercase tracking-wide text-secondary">{{ tx('users_admin_ui.points', 'Punkte') }}</th>
                                <th class="px-4 py-3 text-xs font-semibold uppercase tracking-wide text-secondary">{{ tx('users_admin_ui.reason', 'Grund') }}</th>
                                <th class="px-4 py-3 text-xs font-semibold uppercase tracking-wide text-secondary">{{ tx('users_admin_ui.date', 'Datum') }}</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-border">
                            <tr v-for="warning in filteredWarnings" :key="warning.id">
                                <td class="px-4 py-3">
                                    <p class="font-semibold text-primary">{{ warning.user?.name || tx('moderation.unknown_user', 'Unbekannt') }}</p>
                                    <p class="text-xs text-secondary">{{ warning.user?.email || '-' }}</p>
                                    <p v-if="warning.user?.account_status === 'suspended'" class="mt-1 text-xs font-semibold text-error">
                                        {{ t('users_admin.status.suspended_until', { date: warning.user?.suspended_until || '-' }) }}
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
                                <td class="px-4 py-3 text-secondary">{{ formatDateTime(warning.created_at) }}</td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <div v-if="!filteredWarnings.length" class="p-8 text-center text-sm text-secondary">
                    {{ tx('moderation.empty.warnings', 'Keine Warnungen zu diesem Filter vorhanden.') }}
                </div>
            </div>
        </section>
    </div>
</template>
