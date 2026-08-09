<script setup>
import { computed, ref } from 'vue'
import { Head, Link, useForm, usePage } from '@inertiajs/vue3'
import { useI18n } from 'vue-i18n'
import AppLayout from '@/Components/Auth/Layouts/AppLayout.vue'
import sponsorWorkspaceMessages from './messages.json'

defineOptions({ layout: AppLayout })

const props = defineProps({
    workspace: { type: Object, required: true },
})

const page = usePage()
const { t } = useI18n({
    inheritLocale: true,
    messages: sponsorWorkspaceMessages,
})
const profileOpen = ref(false)
const summary = computed(() => props.workspace.summary || {})
const partners = computed(() => props.workspace.partners || [])
const campaigns = computed(() => props.workspace.campaigns || [])
const growth = computed(() => props.workspace.growth || { stages: [], attention: {} })
const growthStages = computed(() => growth.value.stages || [])
const deals = computed(() => props.workspace.deals || [])
const assets = computed(() => props.workspace.assets || [])
const agencyBriefs = computed(() => props.workspace.agency_briefs || [])
const outcomeTimeline = computed(() => props.workspace.outcome_timeline || [])
const capabilities = computed(() => props.workspace.capabilities || {})
const ownProfile = computed(() => props.workspace.own_profile || null)
const locale = computed(() => page.props.locale || 'de-DE')
const numberFormat = computed(() => new Intl.NumberFormat(locale.value))
const percentFormat = computed(() => new Intl.NumberFormat(locale.value, { maximumFractionDigits: 2 }))
const currencyFormat = computed(() => new Intl.NumberFormat(locale.value, { style: 'currency', currency: 'EUR' }))

const profileForm = useForm({
    name: ownProfile.value?.name || '',
    legal_name: ownProfile.value?.legal_name || '',
    country_code: ownProfile.value?.country_code || 'DE',
    registration_number: ownProfile.value?.registration_number || '',
    vat_id: ownProfile.value?.vat_id || '',
    rule_legal_accuracy: Boolean(ownProfile.value?.legal_accuracy_accepted),
    rule_data_privacy: Boolean(ownProfile.value?.data_privacy_accepted),
    contact_name: ownProfile.value?.contact_name || '',
    email: ownProfile.value?.email || page.props.auth?.user?.email || '',
    website: ownProfile.value?.website || '',
    logo: ownProfile.value?.logo || '',
    logo_light: ownProfile.value?.logo_light || '',
    logo_dark: ownProfile.value?.logo_dark || '',
})

const statCards = computed(() => [
    { key: 'partners', label: t('Partnerschaften'), value: numberFormat.value.format(summary.value.active_partners || 0), sub: t('{count} insgesamt', { count: numberFormat.value.format(summary.value.partners || 0) }), icon: 'las la-handshake', tone: 'from-violet-500/20 to-violet-500/5' },
    { key: 'campaigns', label: t('Aktive Kampagnen'), value: numberFormat.value.format(summary.value.active_campaigns || 0), sub: t('{count} insgesamt', { count: numberFormat.value.format(summary.value.campaigns || 0) }), icon: 'las la-bullhorn', tone: 'from-sky-500/20 to-sky-500/5' },
    { key: 'reach', label: t('Impressionen'), value: numberFormat.value.format(summary.value.impressions || 0), sub: t('Gemessene Sichtkontakte'), icon: 'las la-eye', tone: 'from-cyan-500/20 to-cyan-500/5' },
    { key: 'clicks', label: t('Klicks'), value: numberFormat.value.format(summary.value.clicks || 0), sub: `${percentFormat.value.format(summary.value.ctr || 0)} % CTR`, icon: 'las la-mouse-pointer', tone: 'from-emerald-500/20 to-emerald-500/5' },
    { key: 'budget', label: t('Kampagnenbudget'), value: currencyFormat.value.format((summary.value.budget_cents || 0) / 100), sub: t('{amount} eingesetzt', { amount: currencyFormat.value.format((summary.value.spent_cents || 0) / 100) }), icon: 'las la-wallet', tone: 'from-amber-500/20 to-amber-500/5' },
    { key: 'outcomes', label: t('sponsor_workspace.outcomes_28d'), value: numberFormat.value.format((summary.value.leads_28d || 0) + (summary.value.sales_28d || 0)), sub: t('sponsor_workspace.leads_sales', { leads: summary.value.leads_28d || 0, sales: summary.value.sales_28d || 0 }), icon: 'las la-bullseye', tone: 'from-fuchsia-500/20 to-fuchsia-500/5' },
    { key: 'roas', label: t('sponsor_workspace.metric_roas'), value: summary.value.roas_28d == null ? '–' : `${percentFormat.value.format(summary.value.roas_28d)}×`, sub: t('sponsor_workspace.value_28d', { amount: currencyFormat.value.format((summary.value.conversion_value_cents_28d || 0) / 100) }), icon: 'las la-chart-line', tone: 'from-lime-500/20 to-lime-500/5' },
])

const maxTimelineOutcomes = computed(() => Math.max(1, ...outcomeTimeline.value.map((row) => (row.leads || 0) + (row.sales || 0))))
const stageIcon = (key) => ({
    brief: 'las la-clipboard-list',
    deal: 'las la-handshake',
    asset: 'las la-photo-video',
    campaign: 'las la-bullhorn',
    outcome: 'las la-chart-line',
}[key] || 'las la-circle')
const dealStateLabel = (state) => t(`sponsor_workspace.deal_${state || 'ready'}`)

const statusLabel = (status) => ({
    active: t('Aktiv'),
    upcoming: t('Startet bald'),
    ended: t('Beendet'),
    draft: t('Entwurf'),
    pending_review: t('In Prüfung'),
    paused: t('Pausiert'),
    completed: t('Abgeschlossen'),
    rejected: t('Abgelehnt'),
    verified: t('sponsor_workspace.verified'),
}[status] || status || t('Offen'))

const statusTone = (status) => ({
    active: 'border-emerald-400/40 bg-emerald-500/10 text-emerald-200',
    upcoming: 'border-sky-400/40 bg-sky-500/10 text-sky-200',
    pending_review: 'border-amber-400/40 bg-amber-500/10 text-amber-200',
    paused: 'border-amber-400/40 bg-amber-500/10 text-amber-200',
    ended: 'border-border bg-inputBg text-secondary',
    completed: 'border-border bg-inputBg text-secondary',
    rejected: 'border-rose-400/40 bg-rose-500/10 text-rose-200',
    verified: 'border-emerald-400/40 bg-emerald-500/10 text-emerald-200',
}[status] || 'border-border bg-inputBg text-secondary')

const formatDate = (value) => value
    ? new Intl.DateTimeFormat(locale.value, { dateStyle: 'medium' }).format(new Date(value))
    : t('Offen')

const openProfile = () => {
    const profile = ownProfile.value || {}
    profileForm.defaults({
        name: profile.name || '',
        legal_name: profile.legal_name || '',
        country_code: profile.country_code || 'DE',
        registration_number: profile.registration_number || '',
        vat_id: profile.vat_id || '',
        rule_legal_accuracy: Boolean(profile.legal_accuracy_accepted),
        rule_data_privacy: Boolean(profile.data_privacy_accepted),
        contact_name: profile.contact_name || '',
        email: profile.email || page.props.auth?.user?.email || '',
        website: profile.website || '',
        logo: profile.logo || '',
        logo_light: profile.logo_light || '',
        logo_dark: profile.logo_dark || '',
    })
    profileForm.reset()
    profileForm.clearErrors()
    profileOpen.value = true
}

const submitProfile = () => {
    profileForm.put(route('auth.sponsor-workspace.profile.update'), {
        preserveScroll: true,
        onSuccess: () => { profileOpen.value = false },
    })
}
</script>

<template>
    <Head :title="t('Sponsor-Cockpit')" />

    <div class="space-y-5">
        <section class="surface-card overflow-hidden">
            <div class="grid gap-5 p-5 lg:grid-cols-[1fr_auto] lg:items-end">
                <div>
                    <p class="text-xs font-bold uppercase tracking-wide text-violet-300">{{ t('Sponsoring & Reichweite') }}</p>
                    <h1 class="mt-2 text-2xl font-bold text-primary md:text-3xl">{{ t('Sponsor-Cockpit') }}</h1>
                    <p class="mt-2 max-w-3xl text-sm leading-6 text-secondary">
                        {{ t('Steuere dein Markenprofil, Partnerschaften und Kampagnen. Reichweite und Wirkung bleiben dabei jederzeit nachvollziehbar.') }}
                    </p>
                </div>

                <div class="flex flex-wrap gap-2">
                    <button
                        v-if="capabilities.edit_own_profile"
                        type="button"
                        class="inline-flex min-h-11 items-center justify-center gap-2 rounded-lg border border-border px-4 py-2 text-sm font-bold text-primary transition hover:border-borderHover"
                        @click="openProfile"
                    >
                        <i class="las la-building"></i>
                        {{ ownProfile ? t('Markenprofil bearbeiten') : t('Markenprofil anlegen') }}
                    </button>
                    <Link
                        :href="route('auth.commerce.index')"
                        class="inline-flex min-h-11 items-center justify-center gap-2 rounded-lg bg-buttonPrimary px-4 py-2 text-sm font-bold text-buttonTextPrimary"
                    >
                        <i class="las la-plus"></i>
                        {{ t('Kampagne erstellen') }}
                    </Link>
                </div>
            </div>

            <div v-if="capabilities.edit_own_profile && !ownProfile" class="border-t border-amber-400/30 bg-amber-500/10 px-5 py-4">
                <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                    <div>
                        <p class="font-bold text-primary">{{ t('Dein Markenprofil ist noch nicht vollständig') }}</p>
                        <p class="mt-1 text-sm text-secondary">{{ t('Füge Logo, Website und Kontakt hinzu, damit Vereine und Sportler deine Marke klar erkennen.') }}</p>
                    </div>
                    <button type="button" class="shrink-0 text-sm font-bold text-buttonPrimary" @click="openProfile">
                        {{ t('Jetzt einrichten') }}
                    </button>
                </div>
            </div>
            <div v-else-if="ownProfile" class="flex flex-col gap-2 border-t border-border px-5 py-4 sm:flex-row sm:items-center sm:justify-between">
                <div>
                    <p class="font-bold text-primary">{{ t('sponsor_workspace.verification_title') }}</p>
                    <p class="mt-1 text-sm text-secondary">{{ t(`sponsor_workspace.verification_${ownProfile.verification_status || 'pending_review'}`) }}</p>
                    <p v-if="ownProfile.verification_note" class="mt-1 text-sm text-warning">{{ ownProfile.verification_note }}</p>
                </div>
                <span class="w-fit rounded-full border px-3 py-1 text-xs font-bold" :class="statusTone(ownProfile.verification_status)">{{ statusLabel(ownProfile.verification_status) }}</span>
            </div>
        </section>

        <section class="grid gap-3 sm:grid-cols-2 xl:grid-cols-4">
            <article v-for="card in statCards" :key="card.key" class="surface-card bg-gradient-to-br p-4" :class="card.tone">
                <div class="flex items-start justify-between gap-3">
                    <div class="min-w-0">
                        <p class="text-xs font-bold uppercase text-secondary">{{ card.label }}</p>
                        <p class="mt-2 truncate text-2xl font-bold text-primary">{{ card.value }}</p>
                        <p class="mt-1 text-xs text-secondary">{{ card.sub }}</p>
                    </div>
                    <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-lg bg-inputBg text-violet-300">
                        <i :class="[card.icon, 'text-xl']"></i>
                    </span>
                </div>
            </article>
        </section>

        <section class="surface-card overflow-hidden">
            <div class="flex flex-wrap items-start justify-between gap-3 border-b border-border bg-gradient-to-r from-violet-500/10 to-transparent p-5">
                <div class="max-w-3xl">
                    <p class="text-xs font-bold uppercase tracking-[0.14em] text-violet-300">{{ t('sponsor_workspace.growth_eyebrow') }}</p>
                    <h2 class="mt-1 text-xl font-bold text-primary md:text-2xl">{{ t('sponsor_workspace.growth_title') }}</h2>
                    <p class="mt-1 text-sm leading-6 text-secondary">{{ t('sponsor_workspace.growth_description') }}</p>
                </div>
                <Link
                    :href="route('auth.sponsor-workspace.index')"
                    :only="['workspace']"
                    preserve-scroll
                    class="inline-flex min-h-10 items-center gap-2 rounded-lg border border-border bg-card px-3 py-2 text-sm font-bold text-primary hover:border-borderHover"
                >
                    <i class="las la-sync-alt"></i>
                    {{ t('sponsor_workspace.refresh') }}
                </Link>
            </div>

            <div class="grid gap-2 p-3 sm:grid-cols-2 sm:p-4 lg:grid-cols-5">
                <Link
                    v-for="(stage, index) in growthStages"
                    :key="stage.key"
                    :href="stage.action_url"
                    class="group relative flex min-h-32 flex-col justify-between rounded-xl border border-border bg-bg p-4 transition hover:-translate-y-0.5 hover:border-violet-400/60"
                >
                    <div class="flex items-start justify-between gap-3">
                        <span class="flex h-10 w-10 items-center justify-center rounded-lg bg-violet-500/10 text-violet-300"><i :class="[stageIcon(stage.key), 'text-xl']"></i></span>
                        <span class="text-xs font-black text-secondary">0{{ index + 1 }}</span>
                    </div>
                    <div class="mt-4">
                        <p class="font-bold text-primary">{{ t(`sponsor_workspace.stage_${stage.key}`) }}</p>
                        <p class="mt-1 text-xs text-secondary">{{ t('sponsor_workspace.stage_total', { count: numberFormat.format(stage.count || 0) }) }}</p>
                        <p class="text-xs font-bold text-buttonPrimary">{{ t('sponsor_workspace.stage_open', { count: numberFormat.format(stage.open_count || 0) }) }}</p>
                    </div>
                </Link>
            </div>
        </section>

        <section class="grid gap-5 xl:grid-cols-3">
            <article class="surface-card p-5">
                <div class="flex items-center justify-between gap-3">
                    <h2 class="text-lg font-bold text-primary">{{ t('sponsor_workspace.connected_deals') }}</h2>
                    <span class="rounded-full bg-inputBg px-2.5 py-1 text-xs font-bold text-secondary">{{ deals.length }}</span>
                </div>
                <div v-if="deals.length" class="mt-4 space-y-3">
                    <Link v-for="deal in deals.slice(0, 4)" :key="deal.key" :href="deal.action_url" class="block rounded-lg border border-border bg-inputBg/50 p-3 hover:border-borderHover">
                        <div class="flex items-start justify-between gap-3">
                            <div class="min-w-0">
                                <p class="truncate font-bold text-primary">{{ deal.partner_name }}</p>
                                <p class="mt-1 truncate text-xs text-secondary">{{ deal.club?.name || t('Airmius Plattform') }}</p>
                            </div>
                            <span class="rounded-full border px-2 py-1 text-[10px] font-bold" :class="statusTone(deal.status)">{{ statusLabel(deal.status) }}</span>
                        </div>
                        <div class="mt-3 flex flex-wrap gap-2 text-[11px] font-semibold text-secondary">
                            <span>{{ deal.campaigns_count }} {{ t('sponsor_workspace.stage_campaign') }}</span>
                            <span>·</span>
                            <span>{{ deal.assets_count }} {{ t('sponsor_workspace.stage_asset') }}</span>
                            <span>·</span>
                            <span>{{ deal.outcomes_28d }} {{ t('sponsor_workspace.stage_outcome') }}</span>
                        </div>
                        <p class="mt-2 text-xs font-bold text-buttonPrimary">{{ dealStateLabel(deal.workflow_state) }}</p>
                    </Link>
                </div>
                <p v-else class="mt-4 rounded-lg border border-dashed border-border p-4 text-sm text-secondary">{{ t('sponsor_workspace.empty_deals') }}</p>
            </article>

            <article class="surface-card p-5">
                <div class="flex items-center justify-between gap-3">
                    <h2 class="text-lg font-bold text-primary">{{ t('sponsor_workspace.creative_assets') }}</h2>
                    <span class="rounded-full bg-inputBg px-2.5 py-1 text-xs font-bold text-secondary">{{ assets.length }}</span>
                </div>
                <div v-if="assets.length" class="mt-4 grid grid-cols-2 gap-3">
                    <Link v-for="asset in assets.slice(0, 4)" :key="asset.key" :href="asset.action_url" class="group overflow-hidden rounded-lg border border-border bg-inputBg/50 hover:border-borderHover">
                        <div class="aspect-[4/3] overflow-hidden bg-bg">
                            <img v-if="asset.preview_url" :src="asset.preview_url" :alt="asset.name" loading="lazy" decoding="async" class="h-full w-full object-cover transition group-hover:scale-105" />
                            <i v-else class="las la-photo-video flex h-full items-center justify-center text-4xl text-violet-300"></i>
                        </div>
                        <div class="p-2.5">
                            <p class="truncate text-xs font-bold text-primary">{{ asset.name }}</p>
                            <p class="truncate text-[11px] text-secondary">{{ asset.campaign_name }} · {{ asset.format }}</p>
                        </div>
                    </Link>
                </div>
                <p v-else class="mt-4 rounded-lg border border-dashed border-border p-4 text-sm text-secondary">{{ t('sponsor_workspace.empty_assets') }}</p>
            </article>

            <article class="surface-card p-5">
                <div class="flex items-center justify-between gap-3">
                    <h2 class="text-lg font-bold text-primary">{{ t('sponsor_workspace.agency_briefs') }}</h2>
                    <span class="rounded-full bg-inputBg px-2.5 py-1 text-xs font-bold text-secondary">{{ agencyBriefs.length }}</span>
                </div>
                <p class="mt-2 text-xs leading-5 text-secondary">{{ t('sponsor_workspace.privacy_minimized_briefs') }}</p>
                <div v-if="agencyBriefs.length" class="mt-4 space-y-3">
                    <Link v-for="brief in agencyBriefs.slice(0, 4)" :key="brief.key" :href="brief.action_url" class="flex items-center gap-3 rounded-lg border border-border bg-inputBg/50 p-3 hover:border-borderHover">
                        <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-lg bg-violet-500/10 text-violet-300"><i class="las la-clipboard-check text-xl"></i></span>
                        <span class="min-w-0 flex-1">
                            <span class="block truncate text-sm font-bold text-primary">{{ brief.club?.name || brief.package }}</span>
                            <span class="block text-xs text-secondary">{{ brief.has_domain ? t('sponsor_workspace.has_domain') : t('sponsor_workspace.no_domain') }} · {{ formatDate(brief.submitted_at) }}</span>
                        </span>
                        <span class="rounded-full border px-2 py-1 text-[10px] font-bold" :class="statusTone(brief.status)">{{ statusLabel(brief.status) }}</span>
                    </Link>
                </div>
                <p v-else class="mt-4 rounded-lg border border-dashed border-border p-4 text-sm text-secondary">{{ t('sponsor_workspace.empty_briefs') }}</p>
            </article>
        </section>

        <section class="grid gap-5 xl:grid-cols-[1.15fr_.85fr]">
            <div class="surface-card p-5">
                <div class="flex flex-wrap items-start justify-between gap-3">
                    <div>
                        <p class="text-xs font-bold uppercase text-violet-300">{{ t('Performance') }}</p>
                        <h2 class="mt-1 text-xl font-bold text-primary">{{ t('Letzte Kampagnen') }}</h2>
                    </div>
                    <Link :href="route('auth.commerce.index')" class="text-sm font-bold text-buttonPrimary">{{ t('Alle Kampagnen') }}</Link>
                </div>

                <div v-if="campaigns.length" class="mt-4 space-y-3">
                    <div v-if="outcomeTimeline.length" class="rounded-lg border border-border bg-inputBg/50 p-4">
                        <div class="flex items-center justify-between gap-3">
                            <p class="text-sm font-bold text-primary">{{ t('sponsor_workspace.outcome_timeline') }}</p>
                            <span class="text-xs text-secondary">28 {{ t('sponsor_workspace.days') }}</span>
                        </div>
                        <div class="mt-4 flex h-20 items-end gap-1" :aria-label="t('sponsor_workspace.outcome_timeline')">
                            <div v-for="row in outcomeTimeline" :key="row.date" class="group relative min-w-1 flex-1 rounded-t bg-buttonPrimary/70" :style="{ height: `${Math.max(5, (((row.leads || 0) + (row.sales || 0)) / maxTimelineOutcomes) * 100)}%` }" :title="`${row.date}: ${(row.leads || 0) + (row.sales || 0)}`"></div>
                        </div>
                    </div>
                    <article v-for="campaign in campaigns.slice(0, 6)" :key="campaign.id" class="rounded-lg border border-border bg-inputBg/50 p-4">
                        <div class="flex flex-wrap items-start justify-between gap-3">
                            <div class="min-w-0">
                                <p class="truncate font-bold text-primary">{{ campaign.headline || campaign.name }}</p>
                                <p class="mt-1 text-xs text-secondary">{{ campaign.placement || t('Alle Platzierungen') }} · {{ formatDate(campaign.starts_at) }}</p>
                            </div>
                            <span class="rounded-full border px-2.5 py-1 text-xs font-bold" :class="statusTone(campaign.status)">
                                {{ statusLabel(campaign.status) }}
                            </span>
                        </div>
                        <div class="mt-3 grid grid-cols-2 gap-2 text-sm sm:grid-cols-5">
                            <div><p class="font-bold text-primary">{{ numberFormat.format(campaign.impressions || 0) }}</p><p class="text-xs text-secondary">{{ t('Impressionen') }}</p></div>
                            <div><p class="font-bold text-primary">{{ numberFormat.format(campaign.clicks || 0) }}</p><p class="text-xs text-secondary">{{ t('Klicks') }}</p></div>
                            <div><p class="font-bold text-primary">{{ percentFormat.format(campaign.ctr || 0) }} %</p><p class="text-xs text-secondary">CTR</p></div>
                            <div><p class="font-bold text-primary">{{ numberFormat.format((campaign.leads_28d || 0) + (campaign.sales_28d || 0)) }}</p><p class="text-xs text-secondary">{{ t('sponsor_workspace.outcomes') }}</p></div>
                            <div><p class="font-bold text-primary">{{ campaign.roas_28d == null ? '–' : `${percentFormat.format(campaign.roas_28d)}×` }}</p><p class="text-xs text-secondary">{{ t('sponsor_workspace.metric_roas') }}</p></div>
                        </div>
                    </article>
                </div>

                <div v-else class="mt-4 rounded-lg border border-dashed border-border p-7 text-center">
                    <span class="mx-auto flex h-12 w-12 items-center justify-center rounded-full bg-inputBg text-buttonPrimary"><i class="las la-bullhorn text-2xl"></i></span>
                    <p class="mt-3 font-bold text-primary">{{ t('Noch keine Kampagne vorhanden') }}</p>
                    <p class="mt-1 text-sm text-secondary">{{ t('Starte mit einem klaren Ziel, einer Region und einem kontrollierbaren Budget.') }}</p>
                    <Link :href="route('auth.commerce.index')" class="mt-4 inline-flex rounded-lg bg-buttonPrimary px-4 py-2 text-sm font-bold text-buttonTextPrimary">{{ t('Erste Kampagne erstellen') }}</Link>
                </div>
            </div>

            <div class="space-y-5">
                <section class="surface-card p-5">
                    <div class="flex items-start justify-between gap-3">
                        <div>
                            <p class="text-xs font-bold uppercase text-violet-300">{{ t('Netzwerk') }}</p>
                            <h2 class="mt-1 text-xl font-bold text-primary">{{ t('Partnerschaften') }}</h2>
                        </div>
                        <Link :href="route('guest.sponsors')" class="text-sm font-bold text-buttonPrimary">{{ t('Öffentlich ansehen') }}</Link>
                    </div>

                    <div v-if="partners.length" class="mt-4 space-y-3">
                        <article v-for="partner in partners.slice(0, 5)" :key="partner.id" class="flex items-center gap-3 rounded-lg border border-border bg-inputBg/50 p-3">
                            <img v-if="partner.logo_url" :src="partner.logo_url" :alt="partner.name" class="h-11 w-11 rounded-lg bg-white object-contain p-1.5" />
                            <span v-else class="flex h-11 w-11 shrink-0 items-center justify-center rounded-lg bg-card text-violet-300"><i class="las la-handshake text-xl"></i></span>
                            <div class="min-w-0 flex-1">
                                <p class="truncate font-bold text-primary">{{ partner.name }}</p>
                                <p class="truncate text-xs text-secondary">{{ partner.club?.name || t('Airmius Plattform') }}</p>
                            </div>
                            <span class="rounded-full border px-2 py-1 text-[11px] font-bold" :class="statusTone(partner.status)">{{ statusLabel(partner.status) }}</span>
                        </article>
                    </div>
                    <p v-else class="mt-4 rounded-lg border border-dashed border-border p-5 text-sm text-secondary">
                        {{ t('Noch keine Partnerschaft verknüpft. Dein Verein oder das Airmius-Team kann eine Zusammenarbeit zuordnen.') }}
                    </p>
                </section>

                <section class="surface-card p-5">
                    <p class="text-xs font-bold uppercase text-violet-300">{{ t('Nächster sinnvoller Schritt') }}</p>
                    <h2 class="mt-1 text-lg font-bold text-primary">
                        {{ !ownProfile && capabilities.edit_own_profile ? t('Markenprofil vervollständigen') : (summary.active_campaigns ? t('Performance regelmäßig prüfen') : t('Erste Reichweite aufbauen')) }}
                    </h2>
                    <p class="mt-2 text-sm leading-6 text-secondary">
                        {{ !ownProfile && capabilities.edit_own_profile ? t('Ein vollständiges Profil schafft Vertrauen und verbessert deine Darstellung in der App und auf der Website.') : (summary.active_campaigns ? t('Beobachte CTR, Budget und Laufzeit. Optimiere zuerst Zielgruppe und Botschaft, bevor du das Budget erhöhst.') : t('Lege eine kleine, regional fokussierte Kampagne an und miss die Reaktion der Sport-Community.')) }}
                    </p>
                </section>
            </div>
        </section>
    </div>

    <div v-if="profileOpen" class="fixed inset-0 z-[80] flex items-end justify-center bg-black/65 p-0 backdrop-blur-sm sm:items-center sm:p-5" @click.self="profileOpen = false">
        <section class="max-h-[92dvh] w-full overflow-y-auto rounded-t-2xl border border-border bg-card p-5 shadow-2xl sm:max-w-2xl sm:rounded-2xl">
            <div class="flex items-start justify-between gap-4">
                <div>
                    <p class="text-xs font-bold uppercase text-violet-300">{{ t('Deine Marke') }}</p>
                    <h2 class="mt-1 text-xl font-bold text-primary">{{ t('Markenprofil bearbeiten') }}</h2>
                    <p class="mt-1 text-sm text-secondary">{{ t('Diese Angaben sind für Sportler, Vereine und öffentliche Sponsorenseiten sichtbar.') }}</p>
                </div>
                <button type="button" class="flex h-10 w-10 items-center justify-center rounded-full bg-inputBg text-primary" :aria-label="t('Schließen')" @click="profileOpen = false"><i class="las la-times text-xl"></i></button>
            </div>

            <form class="mt-5 grid gap-4 sm:grid-cols-2" @submit.prevent="submitProfile">
                <label class="sm:col-span-2"><span class="mb-1.5 block text-sm font-bold text-primary">{{ t('Markenname') }}</span><input v-model="profileForm.name" required class="w-full rounded-lg border-border bg-inputBg text-primary" /><span v-if="profileForm.errors.name" class="mt-1 block text-xs text-danger">{{ profileForm.errors.name }}</span></label>
                <label class="sm:col-span-2"><span class="mb-1.5 block text-sm font-bold text-primary">{{ t('sponsor_workspace.legal_name') }}</span><input v-model="profileForm.legal_name" required class="w-full rounded-lg border-border bg-inputBg text-primary" /></label>
                <label><span class="mb-1.5 block text-sm font-bold text-primary">{{ t('sponsor_workspace.country') }}</span><input v-model="profileForm.country_code" required maxlength="2" class="w-full rounded-lg border-border bg-inputBg uppercase text-primary" /></label>
                <label><span class="mb-1.5 block text-sm font-bold text-primary">{{ t('sponsor_workspace.registration') }}</span><input v-model="profileForm.registration_number" class="w-full rounded-lg border-border bg-inputBg text-primary" /></label>
                <label class="sm:col-span-2"><span class="mb-1.5 block text-sm font-bold text-primary">{{ t('sponsor_workspace.vat_id') }}</span><input v-model="profileForm.vat_id" class="w-full rounded-lg border-border bg-inputBg text-primary" /></label>
                <label><span class="mb-1.5 block text-sm font-bold text-primary">{{ t('Ansprechperson') }}</span><input v-model="profileForm.contact_name" class="w-full rounded-lg border-border bg-inputBg text-primary" /></label>
                <label><span class="mb-1.5 block text-sm font-bold text-primary">{{ t('E-Mail') }}</span><input v-model="profileForm.email" type="email" class="w-full rounded-lg border-border bg-inputBg text-primary" /></label>
                <label class="sm:col-span-2"><span class="mb-1.5 block text-sm font-bold text-primary">{{ t('Website') }}</span><input v-model="profileForm.website" type="url" :placeholder="t('commerce.ui.url_placeholder')" class="w-full rounded-lg border-border bg-inputBg text-primary" /><span v-if="profileForm.errors.website" class="mt-1 block text-xs text-danger">{{ profileForm.errors.website }}</span></label>
                <label class="sm:col-span-2"><span class="mb-1.5 block text-sm font-bold text-primary">{{ t('Logo-URL') }}</span><input v-model="profileForm.logo" type="url" :placeholder="t('commerce.ui.url_placeholder')" class="w-full rounded-lg border-border bg-inputBg text-primary" /><span v-if="profileForm.errors.logo" class="mt-1 block text-xs text-danger">{{ profileForm.errors.logo }}</span></label>
                <details class="sm:col-span-2 rounded-lg border border-border bg-inputBg/50 p-3">
                    <summary class="cursor-pointer text-sm font-bold text-primary">{{ t('Optionale Logos für hellen und dunklen Hintergrund') }}</summary>
                    <div class="mt-3 grid gap-3 sm:grid-cols-2">
                        <label><span class="mb-1 block text-xs font-bold text-secondary">{{ t('Logo hell') }}</span><input v-model="profileForm.logo_light" type="url" class="w-full rounded-lg border-border bg-card text-primary" /></label>
                        <label><span class="mb-1 block text-xs font-bold text-secondary">{{ t('Logo dunkel') }}</span><input v-model="profileForm.logo_dark" type="url" class="w-full rounded-lg border-border bg-card text-primary" /></label>
                    </div>
                </details>
                <div class="grid gap-2 rounded-lg border border-border bg-inputBg/50 p-3 text-sm text-primary sm:col-span-2">
                    <label class="flex items-start gap-2"><input v-model="profileForm.rule_legal_accuracy" required type="checkbox" class="mt-1 rounded border-border bg-inputBg" /><span>{{ t('sponsor_workspace.legal_accuracy') }}</span></label>
                    <label class="flex items-start gap-2"><input v-model="profileForm.rule_data_privacy" required type="checkbox" class="mt-1 rounded border-border bg-inputBg" /><span>{{ t('sponsor_workspace.data_privacy') }}</span></label>
                </div>
                <div class="flex flex-col-reverse gap-2 sm:col-span-2 sm:flex-row sm:justify-end">
                    <button type="button" class="min-h-11 rounded-lg border border-border px-4 py-2 text-sm font-bold text-primary" @click="profileOpen = false">{{ t('Abbrechen') }}</button>
                    <button type="submit" :disabled="profileForm.processing" class="min-h-11 rounded-lg bg-buttonPrimary px-5 py-2 text-sm font-bold text-buttonTextPrimary disabled:opacity-60">{{ profileForm.processing ? t('Wird gespeichert …') : t('Profil speichern') }}</button>
                </div>
            </form>
        </section>
    </div>
</template>
