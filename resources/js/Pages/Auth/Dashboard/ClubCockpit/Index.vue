<script setup>
import { computed, ref, watch } from 'vue'
import { Head, Link, usePage } from '@inertiajs/vue3'
import { useI18n } from 'vue-i18n'
import AppLayout from '@/Components/Auth/Layouts/AppLayout.vue'
import { usePermissions } from '@/composables/usePermissions'

defineOptions({ layout: AppLayout })

const props = defineProps({
    clubs: { type: Array, default: () => [] },
})

const page = usePage()
const { t } = useI18n()
const { can } = usePermissions()
const selectedClubId = ref(props.clubs[0]?.id || null)

watch(() => props.clubs, (clubs) => {
    if (!clubs.some((club) => club.id === selectedClubId.value)) {
        selectedClubId.value = clubs[0]?.id || null
    }
})

const selectedClub = computed(() => props.clubs.find((club) => club.id === selectedClubId.value) || props.clubs[0] || null)
const onboarding = computed(() => selectedClub.value?.onboarding || null)
const locale = computed(() => page.props.locale || 'de')
const currency = computed(() => new Intl.NumberFormat(locale.value, { style: 'currency', currency: 'EUR' }))
const numberFormat = computed(() => new Intl.NumberFormat(locale.value))

const formatMoney = (value) => currency.value.format(Number(value || 0))
const formatNumber = (value) => numberFormat.value.format(Number(value || 0))
const formatBytes = (bytes) => {
    const value = Number(bytes || 0)

    if (value < 1024 * 1024) return `${Math.round(value / 1024)} KB`
    if (value < 1024 * 1024 * 1024) return `${(value / 1024 / 1024).toFixed(1)} MB`

    return `${(value / 1024 / 1024 / 1024).toFixed(2)} GB`
}
const formatDateTime = (value) => {
    if (!value) return t('Noch kein Termin')

    return new Intl.DateTimeFormat(locale.value, {
        dateStyle: 'medium',
        timeStyle: 'short',
    }).format(new Date(value))
}

const statCards = computed(() => {
    const stats = selectedClub.value?.stats || {}

    return [
        { key: 'members', label: 'Mitglieder', value: `${formatNumber(stats.members)} / ${stats.member_limit ? formatNumber(stats.member_limit) : t('Unbegrenzt')}`, icon: 'las la-users', tone: 'from-sky-500/20 to-sky-500/5' },
        { key: 'teams', label: 'Teams', value: `${formatNumber(stats.teams)} / ${stats.team_limit ? formatNumber(stats.team_limit) : t('Unbegrenzt')}`, icon: 'las la-sitemap', tone: 'from-emerald-500/20 to-emerald-500/5' },
        { key: 'open', label: 'Offene Beträge', value: formatMoney(stats.open_invoice_amount), sub: t('{count} offene Rechnungen', { count: formatNumber(stats.open_invoice_count) }), icon: 'las la-file-invoice-dollar', tone: 'from-amber-500/20 to-amber-500/5' },
        { key: 'requests', label: 'Anfragen', value: formatNumber(stats.pending_requests), sub: t('Mitgliedschaft und Teams'), icon: 'las la-user-check', tone: 'from-violet-500/20 to-violet-500/5' },
        { key: 'events', label: 'Nächste Termine', value: formatNumber(stats.upcoming_events), sub: t('Geplante Vereins- und Teamtermine'), icon: 'las la-calendar-check', tone: 'from-cyan-500/20 to-cyan-500/5' },
        { key: 'storage', label: 'Speicher', value: stats.storage_gb ? `${formatBytes(stats.storage_bytes)} / ${formatNumber(stats.storage_gb)} GB` : formatBytes(stats.storage_bytes), sub: stats.storage_gb ? t('Verwendet / Planlimit') : t('Plan ohne festes Limit'), icon: 'las la-database', tone: 'from-rose-500/20 to-rose-500/5' },
    ]
})

const actionText = {
    requests: {
        title: 'Anfragen prüfen',
        description: 'Offene Mitglieds- oder Teambeitritte sauber entscheiden.',
    },
    billing: {
        title: 'Rechnungen klären',
        description: 'Offene Beiträge prüfen, Zahlung erfassen oder erinnern.',
    },
    sepa: {
        title: 'SEPA vervollständigen',
        description: 'Mandate und IBANs für automatische Abbuchungen nachziehen.',
    },
    structure: {
        title: 'Vereinsstruktur pflegen',
        description: 'Teams, Rollen und Vereinsdaten aktuell halten.',
    },
    events: {
        title: 'Termine steuern',
        description: 'Trainings, Spiele und Meetings im Blick behalten.',
    },
}
</script>

<template>
    <Head :title="t('Vereins-Cockpit')" />

    <div class="space-y-5">
        <section class="surface-card overflow-hidden">
            <div class="grid gap-5 p-5 lg:grid-cols-[1fr_340px] lg:items-end">
                <div>
                    <p class="text-xs font-bold uppercase tracking-wide text-buttonPrimary">{{ t('Vereinsführung') }}</p>
                    <h1 class="mt-2 text-2xl font-bold text-primary md:text-3xl">{{ t('Vereins-Cockpit') }}</h1>
                    <p class="mt-2 max-w-3xl text-sm leading-6 text-secondary">
                        {{ t('Alle wichtigen Vereinssignale an einem Ort: Anfragen, Beiträge, Termine, Speicher und nächste Aufgaben.') }}
                    </p>
                </div>

                <label v-if="clubs.length > 1" class="block">
                    <span class="mb-2 block text-xs font-bold uppercase text-secondary">{{ t('Verein') }}</span>
                    <select v-model="selectedClubId" class="w-full rounded-lg border-border bg-inputBg text-sm font-semibold text-primary">
                        <option v-for="club in clubs" :key="club.id" :value="club.id">{{ club.name }}</option>
                    </select>
                </label>
            </div>

            <div v-if="selectedClub" class="border-t border-border px-5 py-4">
                <div class="flex flex-col gap-3 md:flex-row md:items-center md:justify-between">
                    <div>
                        <h2 class="text-lg font-bold text-primary">{{ selectedClub.name }}</h2>
                        <p class="text-sm text-secondary">
                            {{ t('Aktueller Plan') }}: {{ selectedClub.plan?.name || t('Free') }}
                        </p>
                    </div>
                    <Link
                        :href="route('guest.pricing', { audience: 'verein' })"
                        class="inline-flex items-center justify-center gap-2 rounded-lg border border-border px-4 py-2 text-sm font-bold text-primary transition hover:border-borderHover"
                    >
                        <i class="las la-rocket"></i>
                        {{ selectedClub.locked ? t('Club-Plan aktivieren') : t('Plan prüfen') }}
                    </Link>
                </div>
            </div>
        </section>

        <section v-if="!selectedClub" class="surface-card overflow-hidden">
            <div class="grid gap-5 p-6 lg:grid-cols-[1fr_auto] lg:items-center">
                <div>
                    <span class="flex h-12 w-12 items-center justify-center rounded-xl bg-buttonPrimary/10 text-buttonPrimary"><i class="las la-building text-2xl"></i></span>
                    <h2 class="mt-4 text-xl font-bold text-primary">{{ t('Richte deinen Vereinsbereich ein') }}</h2>
                    <p class="mt-2 max-w-2xl text-sm leading-6 text-secondary">
                        {{ can('club.create')
                            ? t('Registriere zuerst deinen Verein. Danach führst du Teams, Mitglieder, Termine und Beiträge Schritt für Schritt zusammen.')
                            : t('Dein Konto hat eine Vereinsrolle, ist aber noch keinem verwaltbaren Verein zugeordnet. Bitte den Vereinsinhaber um eine Einladung mit der passenden Rolle.') }}
                    </p>
                </div>
                <Link
                    v-if="can('club.create')"
                    :href="route('auth.teams.index', { create_club: 1 })"
                    class="inline-flex min-h-11 items-center justify-center gap-2 rounded-lg bg-buttonPrimary px-5 py-3 text-sm font-bold text-buttonTextPrimary"
                >
                    <i class="las la-plus"></i>
                    {{ t('Verein registrieren') }}
                </Link>
                <Link
                    v-else
                    :href="route('auth.teams.index')"
                    class="inline-flex min-h-11 items-center justify-center gap-2 rounded-lg border border-border px-5 py-3 text-sm font-bold text-primary"
                >
                    <i class="las la-envelope-open-text"></i>
                    {{ t('Einladungen prüfen') }}
                </Link>
            </div>

            <div class="grid gap-px border-t border-border bg-border sm:grid-cols-3">
                <div class="bg-card p-4"><p class="text-xs font-bold uppercase text-secondary">1. {{ t('Verein') }}</p><p class="mt-1 text-sm text-primary">{{ t('Basisdaten und Sichtbarkeit festlegen') }}</p></div>
                <div class="bg-card p-4"><p class="text-xs font-bold uppercase text-secondary">2. {{ t('Team') }}</p><p class="mt-1 text-sm text-primary">{{ t('Erstes Team erstellen und Trainer zuordnen') }}</p></div>
                <div class="bg-card p-4"><p class="text-xs font-bold uppercase text-secondary">3. {{ t('Mitglieder') }}</p><p class="mt-1 text-sm text-primary">{{ t('Einladen oder bestehende Daten importieren') }}</p></div>
            </div>
        </section>

        <template v-else>
            <section v-if="selectedClub.locked" class="surface-card border-amber-400/60 bg-amber-500/10 p-5">
                <div class="flex flex-col gap-3 md:flex-row md:items-center md:justify-between">
                    <div>
                        <p class="text-xs font-bold uppercase text-amber-200">{{ t('Premium-Funktion') }}</p>
                        <h2 class="mt-1 text-lg font-bold text-primary">{{ t('Vereins-Cockpit ist im Club-Plan enthalten') }}</h2>
                        <p class="mt-1 text-sm text-secondary">
                            {{ t('Du siehst die Vorschau. Operative Steuerung, Warnungen und Priorisierung werden mit dem Club-Plan freigeschaltet.') }}
                        </p>
                    </div>
                    <Link :href="route('guest.pricing', { audience: 'verein' })" class="rounded-lg bg-buttonPrimary px-4 py-2 text-sm font-bold text-buttonTextPrimary">
                        {{ t('Upgrade ansehen') }}
                    </Link>
                </div>
            </section>

            <section v-if="onboarding" class="surface-card overflow-hidden">
                <div class="grid gap-4 p-5 lg:grid-cols-[1fr_auto] lg:items-center">
                    <div>
                        <p class="text-xs font-bold uppercase tracking-wide text-buttonPrimary">{{ onboarding.title }}</p>
                        <h2 class="mt-1 text-xl font-bold text-primary">{{ onboarding.progress_label }}</h2>
                        <p class="mt-1 max-w-3xl text-sm leading-6 text-secondary">{{ onboarding.subtitle }}</p>
                    </div>
                    <div class="flex items-center gap-3">
                        <div class="h-2.5 w-40 overflow-hidden rounded-full bg-inputBg" role="progressbar" :aria-valuenow="onboarding.completion_percent" aria-valuemin="0" aria-valuemax="100">
                            <div class="h-full rounded-full bg-buttonPrimary transition-all" :style="{ width: `${onboarding.completion_percent}%` }"></div>
                        </div>
                        <strong class="text-lg text-primary">{{ onboarding.completion_percent }}%</strong>
                    </div>
                </div>

                <div class="grid gap-px border-t border-border bg-border md:grid-cols-2 xl:grid-cols-3">
                    <article v-for="step in onboarding.steps" :key="step.key" class="flex flex-col bg-card p-4">
                        <div class="flex items-start gap-3">
                            <span
                                class="mt-0.5 flex h-8 w-8 shrink-0 items-center justify-center rounded-full"
                                :class="step.done ? 'bg-emerald-500/15 text-emerald-300' : 'bg-buttonPrimary/10 text-buttonPrimary'"
                                aria-hidden="true"
                            >
                                <i :class="step.done ? 'las la-check' : 'las la-arrow-right'"></i>
                            </span>
                            <div class="min-w-0 flex-1">
                                <div class="flex items-start justify-between gap-2">
                                    <h3 class="font-bold text-primary">{{ step.title }}</h3>
                                    <span class="shrink-0 rounded-full bg-inputBg px-2 py-1 text-[11px] font-bold text-secondary">{{ step.status_label }}</span>
                                </div>
                                <p class="mt-1 text-xs leading-5 text-secondary">{{ step.description }}</p>
                            </div>
                        </div>
                        <Link
                            v-if="!step.done"
                            :href="step.action_path"
                            class="mt-3 inline-flex min-h-10 items-center justify-center gap-2 self-start rounded-lg border border-border px-3 py-2 text-xs font-bold text-primary transition hover:border-borderHover"
                        >
                            {{ step.action_label }}
                            <i class="las la-arrow-right rtl:rotate-180"></i>
                        </Link>
                    </article>
                </div>
            </section>

            <section class="grid gap-3 sm:grid-cols-2 xl:grid-cols-3">
                <article
                    v-for="card in statCards"
                    :key="card.key"
                    class="surface-card bg-gradient-to-br p-4"
                    :class="card.tone"
                >
                    <div class="flex items-start justify-between gap-3">
                        <div>
                            <p class="text-xs font-bold uppercase text-secondary">{{ t(card.label) }}</p>
                            <p class="mt-2 text-2xl font-bold text-primary">{{ card.value }}</p>
                            <p v-if="card.sub" class="mt-1 text-xs text-secondary">{{ card.sub }}</p>
                        </div>
                        <div class="flex h-10 w-10 items-center justify-center rounded-lg bg-inputBg text-buttonPrimary">
                            <i :class="[card.icon, 'text-xl']"></i>
                        </div>
                    </div>
                </article>
            </section>

            <section class="grid gap-5 xl:grid-cols-[1.15fr_.85fr]">
                <div class="surface-card p-5">
                    <div class="flex items-center justify-between gap-3">
                        <div>
                            <p class="text-xs font-bold uppercase text-buttonPrimary">{{ t('Heute wichtig') }}</p>
                            <h2 class="mt-1 text-xl font-bold text-primary">{{ t('Nächste Aufgaben') }}</h2>
                        </div>
                        <span class="rounded-full bg-inputBg px-3 py-1 text-xs font-bold text-primary">
                            {{ selectedClub.stats.pending_requests + selectedClub.stats.overdue_invoice_count + selectedClub.stats.sepa_missing }}
                        </span>
                    </div>

                    <div class="mt-4 space-y-3">
                        <Link
                            v-for="action in selectedClub.actions"
                            :key="action.key"
                            :href="action.href"
                            class="flex items-center gap-3 rounded-lg border border-border bg-inputBg/50 p-3 transition hover:border-borderHover"
                        >
                            <span class="flex h-10 w-10 items-center justify-center rounded-lg bg-card text-buttonPrimary">
                                <i :class="[action.icon, 'text-xl']"></i>
                            </span>
                            <span class="min-w-0 flex-1">
                                <span class="block font-bold text-primary">{{ t(actionText[action.key]?.title || action.key) }}</span>
                                <span class="block text-xs leading-5 text-secondary">{{ t(actionText[action.key]?.description || '') }}</span>
                            </span>
                            <span class="rounded-full bg-card px-2 py-1 text-xs font-bold text-primary">{{ formatNumber(action.count) }}</span>
                        </Link>
                    </div>
                </div>

                <div class="surface-card p-5">
                    <div class="flex items-center justify-between gap-3">
                        <div>
                            <p class="text-xs font-bold uppercase text-buttonPrimary">{{ t('Kalender') }}</p>
                            <h2 class="mt-1 text-xl font-bold text-primary">{{ t('Nächste Termine') }}</h2>
                        </div>
                        <Link :href="route('auth.events.index')" class="text-sm font-bold text-buttonPrimary">{{ t('Öffnen') }}</Link>
                    </div>

                    <div v-if="selectedClub.upcoming_events.length" class="mt-4 space-y-3">
                        <article v-for="event in selectedClub.upcoming_events" :key="event.id" class="rounded-lg border border-border bg-inputBg/50 p-3">
                            <p class="font-bold text-primary">{{ event.title }}</p>
                            <p class="mt-1 text-xs text-secondary">{{ formatDateTime(event.start_time) }}</p>
                            <p v-if="event.location" class="mt-1 text-xs text-secondary">{{ event.location }}</p>
                        </article>
                    </div>

                    <div v-else class="mt-4 rounded-lg border border-dashed border-border p-6 text-center text-sm text-secondary">
                        {{ t('Noch keine kommenden Termine geplant.') }}
                    </div>
                </div>
            </section>
        </template>
    </div>
</template>
