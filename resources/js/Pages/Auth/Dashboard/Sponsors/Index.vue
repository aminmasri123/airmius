<script setup>
import AppLayout from '@/Components/Auth/Layouts/AppLayout.vue'
import { Head, router, useForm } from '@inertiajs/vue3'
import { computed, onBeforeUnmount, onMounted, ref, watch } from 'vue'
import { useTheme } from '@/services/useTheme'
import { useI18n } from 'vue-i18n'
import sponsorsAdminLocalization from '@/i18n/sponsorsAdminLocalization.json'
import CountrySelect from '@/Components/CountrySelect.vue'

defineOptions({ layout: AppLayout })

const props = defineProps({
    sponsors: Object,
    canReview: { type: Boolean, default: false },
    partnershipRequests: { type: Array, default: () => [] },
    clubs: { type: Array, default: () => [] },
    stats: {
        type: Object,
        default: () => ({
            total: 0,
            platform: 0,
            outfit_subscription: 0,
            club: 0,
        }),
    },
    filters: {
        type: Object,
        default: () => ({
            scope: 'all',
        }),
    },
})

const { t, locale, mergeLocaleMessage } = useI18n({ useScope: 'global' })
Object.entries(sponsorsAdminLocalization).forEach(([language, messages]) => {
    mergeLocaleMessage(language, { sponsors_admin: messages })
})
const localeCode = computed(() => String(locale.value || 'de').replace('_', '-'))
const scopes = computed(() => [
    { key: 'all', label: t('sponsors_admin.scope_all'), hint: t('sponsors_admin.scope_all_hint') },
    { key: 'platform', label: t('sponsors_admin.scope_platform'), hint: t('sponsors_admin.scope_platform_hint') },
    { key: 'outfit_subscription', label: t('sponsors_admin.scope_outfit'), hint: t('sponsors_admin.scope_outfit_hint') },
    { key: 'club', label: t('sponsors_admin.scope_club'), hint: t('sponsors_admin.scope_club_hint') },
])

const activeScope = ref(props.filters.scope || 'all')
const search = ref(props.filters.q || '')
const clubSearch = ref(props.filters.club_query || '')
const showFormModal = ref(false)
const reviewTarget = ref(null)
const reviewNote = ref('')
const reviewBusy = ref(false)
const reviewError = ref('')
const reviewLabels = {
    name: 'Markenname', legal_name: 'Rechtlicher Unternehmensname', country_code: 'Land',
    registration_number: 'Registernummer', vat_id: 'USt-IdNr.', contact_name: 'Ansprechperson',
    email: 'E-Mail', website: 'Website', logo: 'Logo', logo_light: 'Logo für helles Design',
    logo_dark: 'Logo für dunkles Design',
}
const reviewValue = value => String(value ?? '').trim() || '–'
const openReview = sponsor => { reviewTarget.value = sponsor; reviewNote.value = ''; reviewError.value = '' }
const submitReview = decision => {
    if (!reviewTarget.value || reviewBusy.value) return
    if (decision === 'rejected' && !reviewNote.value.trim()) {
        reviewError.value = 'Bitte begründe die Ablehnung.'
        return
    }
    reviewBusy.value = true
    reviewError.value = ''
    router.post(route('sponsors.review', reviewTarget.value.id), { decision, note: reviewNote.value.trim() }, {
        preserveScroll: true,
        onSuccess: () => { reviewTarget.value = null },
        onError: errors => { reviewError.value = Object.values(errors)[0] || 'Prüfung fehlgeschlagen.' },
        onFinish: () => { reviewBusy.value = false },
    })
}
onMounted(() => {
    if (props.filters.sponsor_id) {
        const sponsor = props.sponsors?.data?.find(row => Number(row.id) === Number(props.filters.sponsor_id))
        if (sponsor) openReview(sponsor)
    }
})
const partnershipDecisionTarget = ref(null)
const partnershipDecisionApprove = ref(true)
const partnershipDecisionNote = ref('')
const partnershipHelpOpen = ref(false)
const editingSponsor = ref(null)
const deleteTarget = ref(null)
const deleteConfirmation = ref('')
const { isDark } = useTheme()
let searchTimer = null
let clubSearchTimer = null

const form = useForm({
    scope: 'platform',
    club_id: '',
    name: '',
    legal_name: '',
    country_code: 'DE',
    registration_number: '',
    vat_id: '',
    verification_status: 'verified',
    verification_note: '',
    contact_name: '',
    email: '',
    website: '',
    logo: '',
    logo_light: '',
    logo_dark: '',
    amount: '',
    starts_at: '',
    ends_at: '',
})

const allSponsors = computed(() => props.sponsors?.data || [])
const selectedClub = computed(() => props.clubs.find((club) => Number(club.id) === Number(form.club_id)) || null)
const isClubSponsor = computed(() => form.scope === 'club')
const canManageSponsors = computed(() => !isClubSponsor.value || selectedClub.value?.capabilities?.sponsors !== false)

const filteredSponsors = computed(() => {
    if (activeScope.value === 'all') return allSponsors.value

    return allSponsors.value.filter((sponsor) => sponsorScope(sponsor) === activeScope.value)
})

const sponsorStats = computed(() => ({
    total: props.stats.total || 0,
    platform: props.stats.platform || 0,
    outfit_subscription: props.stats.outfit_subscription || 0,
    club: props.stats.club || 0,
}))

function sponsorScope(sponsor) {
    return sponsor.scope || (sponsor.club_id ? 'club' : 'platform')
}

const scopeLabel = (sponsor) => ({
    platform: t('sponsors_admin.scope_platform'),
    outfit_subscription: t('sponsors_admin.scope_outfit'),
    club: sponsor.club?.name || t('sponsors_admin.scope_club'),
}[sponsorScope(sponsor)] || t('sponsors_admin.scope_platform'))

const scopeBadgeClass = (sponsor) => ({
    platform: 'bg-air-blue/10 text-air-blue',
    outfit_subscription: 'bg-accent/10 text-accent',
    club: 'bg-success/10 text-success',
}[sponsorScope(sponsor)] || 'bg-air-blue/10 text-air-blue')

const resetForm = () => {
    editingSponsor.value = null
    form.reset()
    form.clearErrors()
    form.scope = activeScope.value === 'all' ? 'platform' : activeScope.value
    form.club_id = ''
}

const openCreateModal = (scope = activeScope.value) => {
    resetForm()
    form.scope = scope === 'all' ? 'platform' : scope
    showFormModal.value = true
}

const changeScope = (scope) => {
    activeScope.value = scope

    router.get(route('sponsors.index'), {
        scope: scope === 'all' ? undefined : scope,
        q: search.value || undefined,
    }, {
        preserveScroll: true,
        preserveState: true,
        replace: true,
        only: ['sponsors', 'filters'],
    })
}

watch(search, () => {
    clearTimeout(searchTimer)
    searchTimer = setTimeout(() => {
        router.get(route('sponsors.index'), {
            scope: activeScope.value === 'all' ? undefined : activeScope.value,
            q: search.value || undefined,
        }, {
            preserveScroll: true,
            preserveState: true,
            replace: true,
            only: ['sponsors', 'filters'],
        })
    }, 350)
})

watch(clubSearch, () => {
    clearTimeout(clubSearchTimer)
    clubSearchTimer = setTimeout(() => {
        router.get(route('sponsors.index'), {
            scope: activeScope.value === 'all' ? undefined : activeScope.value,
            q: search.value || undefined,
            club_query: clubSearch.value || undefined,
        }, {
            preserveScroll: true,
            preserveState: true,
            replace: true,
            only: ['clubs', 'filters'],
        })
    }, 350)
})

onBeforeUnmount(() => {
    clearTimeout(searchTimer)
    clearTimeout(clubSearchTimer)
})

const visitPage = (url) => {
    if (!url) return

    router.visit(url, {
        preserveScroll: true,
        preserveState: true,
        only: ['sponsors', 'filters'],
    })
}

const closeFormModal = () => {
    showFormModal.value = false
    resetForm()
}

const edit = (sponsor) => {
    editingSponsor.value = sponsor
    form.clearErrors()
    form.scope = sponsorScope(sponsor)
    form.club_id = sponsor.club_id || ''
    form.name = sponsor.name
    form.legal_name = sponsor.legal_name || ''
    form.country_code = sponsor.country_code || 'DE'
    form.registration_number = sponsor.registration_number || ''
    form.vat_id = sponsor.vat_id || ''
    form.verification_status = sponsor.verification_status || 'verified'
    form.verification_note = sponsor.verification_note || ''
    form.contact_name = sponsor.contact_name || ''
    form.email = sponsor.email || ''
    form.website = sponsor.website || ''
    form.logo = sponsor.logo || ''
    form.logo_light = sponsor.logo_light || sponsor.logo || ''
    form.logo_dark = sponsor.logo_dark || sponsor.logo_light || sponsor.logo || ''
    form.amount = sponsor.amount || ''
    form.starts_at = sponsor.starts_at || ''
    form.ends_at = sponsor.ends_at || ''
    showFormModal.value = true
}

const openDeleteModal = (sponsor) => {
    deleteTarget.value = sponsor
    deleteConfirmation.value = ''
}

const closeDeleteModal = () => {
    deleteTarget.value = null
    deleteConfirmation.value = ''
}

const confirmDelete = () => {
    if (!deleteTarget.value || deleteConfirmation.value !== 'delete') return

    router.delete(route('sponsors.destroy', deleteTarget.value.id), {
        preserveScroll: true,
        onSuccess: closeDeleteModal,
    })
}

const submit = () => {
    if (form.scope !== 'club') {
        form.club_id = ''
    }

    const options = {
        preserveScroll: true,
        onSuccess: closeFormModal,
    }

    form.transform(data => {
        if (!editingSponsor.value) return data
        const { verification_status, verification_note, ...profileFields } = data
        return profileFields
    })

    editingSponsor.value
        ? form.put(route('sponsors.update', editingSponsor.value.id), options)
        : form.post(route('sponsors.store'), options)
}

const sponsorLogoUrl = (sponsor) => isDark.value
    ? (sponsor.logo_dark_url || sponsor.logo_light_url || sponsor.logo_url || sponsor.logo)
    : (sponsor.logo_light_url || sponsor.logo_dark_url || sponsor.logo_url || sponsor.logo)

const previewUrl = (source) => {
    if (!source) return ''
    if (source.startsWith('http://') || source.startsWith('https://') || source.startsWith('/')) return source

    return `/storage/${source}`
}

const formatAmount = (amount) => {
    if (amount === null || amount === undefined || amount === '') return '-'

    return new Intl.NumberFormat(localeCode.value, {
        style: 'currency',
        currency: 'EUR',
        minimumFractionDigits: 2,
        maximumFractionDigits: 2,
    }).format(Number(amount))
}

const formatDate = (value) => {
    if (!value) return '-'

    return new Intl.DateTimeFormat(localeCode.value, {
        day: '2-digit',
        month: '2-digit',
        year: 'numeric',
    }).format(new Date(value))
}

const formatNumber = (value) => new Intl.NumberFormat(localeCode.value).format(Number(value || 0))

const verificationLabel = (status) => t(`sponsors_admin.verification_${status || 'pending_review'}`)

const verificationTone = (status) => ({
    verified: 'bg-success/10 text-success',
    rejected: 'bg-error/10 text-error',
    pending_review: 'bg-warning/10 text-warning',
}[status] || 'bg-warning/10 text-warning')

const openPartnershipDecision = (request, approve) => {
    partnershipDecisionTarget.value = request
    partnershipDecisionApprove.value = approve
    partnershipDecisionNote.value = ''
}

const decidePartnership = () => {
    if (!partnershipDecisionTarget.value) return
    router.post(route('sponsors.partnerships.decision', partnershipDecisionTarget.value.id), {
        decision: partnershipDecisionApprove.value ? 'approve' : 'reject',
        note: partnershipDecisionNote.value,
    }, {
        preserveScroll: true,
        onSuccess: () => { partnershipDecisionTarget.value = null },
    })
}

const paginationLabel = (label) => String(label || '')
    .replace(/<[^>]*>/g, '')
    .replace(/&laquo;/g, '«')
    .replace(/&raquo;/g, '»')
    .replace(/&amp;/g, '&')
</script>

<template>
    <Head :title="t('sponsors_admin.page_title')" />

    <div class="space-y-5">
        <section class="rounded-lg border border-border bg-card p-5">
            <div class="flex flex-col gap-4 lg:flex-row lg:items-center lg:justify-between">
                <div>
                    <p class="text-sm font-semibold uppercase tracking-wider text-air-blue">{{ t('sponsors_admin.eyebrow') }}</p>
                    <h1 class="mt-1 text-2xl font-bold text-primary">{{ t('sponsors_admin.title') }}</h1>
                    <p class="mt-2 max-w-3xl text-sm leading-6 text-secondary">
                        {{ t('sponsors_admin.intro') }}
                    </p>
                </div>

                <button
                    type="button"
                    class="inline-flex items-center justify-center gap-2 rounded-lg bg-buttonPrimary px-4 py-2 font-semibold text-buttonTextPrimary transition hover:bg-primary/90"
                    @click="openCreateModal()"
                >
                    <i class="las la-plus text-lg"></i>
                    {{ t('sponsors_admin.create') }}
                </button>
            </div>

            <div class="mt-5 grid gap-3 sm:grid-cols-2 xl:grid-cols-4">
                <div class="rounded-lg border border-border bg-inputBg p-4">
                    <p class="text-xs font-semibold uppercase text-secondary">{{ t('sponsors_admin.stats_total') }}</p>
                    <p class="mt-2 text-2xl font-bold text-primary">{{ formatNumber(sponsorStats.total) }}</p>
                </div>
                <div class="rounded-lg border border-border bg-inputBg p-4">
                    <p class="text-xs font-semibold uppercase text-secondary">{{ t('sponsors_admin.stats_platform') }}</p>
                    <p class="mt-2 text-2xl font-bold text-primary">{{ formatNumber(sponsorStats.platform) }}</p>
                </div>
                <div class="rounded-lg border border-border bg-inputBg p-4">
                    <p class="text-xs font-semibold uppercase text-secondary">{{ t('sponsors_admin.stats_outfit') }}</p>
                    <p class="mt-2 text-2xl font-bold text-primary">{{ formatNumber(sponsorStats.outfit_subscription) }}</p>
                </div>
                <div class="rounded-lg border border-border bg-inputBg p-4">
                    <p class="text-xs font-semibold uppercase text-secondary">{{ t('sponsors_admin.stats_clubs') }}</p>
                    <p class="mt-2 text-2xl font-bold text-primary">{{ formatNumber(sponsorStats.club) }}</p>
                </div>
            </div>
        </section>

        <section v-if="partnershipRequests.length" class="rounded-lg border border-warning/40 bg-card p-5">
            <p class="text-xs font-bold uppercase tracking-wide text-warning">Partnerschaften</p>
            <div class="mt-1 flex items-center gap-2">
                <h2 class="text-xl font-bold text-primary">Offene Partnerschaftsanfragen</h2>
                <button
                    type="button"
                    class="flex h-8 w-8 shrink-0 items-center justify-center rounded-full border border-border text-buttonPrimary hover:bg-inputBg"
                    aria-label="Wie funktionieren Partnerschaftsanfragen?"
                    :aria-expanded="partnershipHelpOpen"
                    aria-controls="club-partnership-help"
                    @click="partnershipHelpOpen = !partnershipHelpOpen"
                ><i class="las la-question-circle text-lg"></i></button>
            </div>
            <div v-if="partnershipHelpOpen" id="club-partnership-help" class="mt-3 rounded-lg border border-warning/35 bg-warning/10 p-4 text-sm leading-6 text-secondary">
                <p class="font-bold text-primary">Was passiert mit einer Anfrage?</p>
                <p class="mt-1">Nur berechtigte Vereinsverantwortliche können das Angebot prüfen. Kontrolliere Laufzeit, Rechte, Leistungen und Nachricht. Mit „Annehmen“ wird die Partnerschaft aktiv; mit „Ablehnen“ wird sie beendet. Deine optionale Rückmeldung wird dem Sponsor angezeigt.</p>
            </div>
            <div class="mt-4 grid gap-3 lg:grid-cols-2">
                <article v-for="request in partnershipRequests" :key="request.id" class="rounded-lg border border-border bg-inputBg p-4">
                    <div class="flex items-start justify-between gap-3">
                        <div>
                            <p class="font-bold text-primary">{{ request.sponsor?.name }} → {{ request.club?.name }}</p>
                            <p class="mt-1 text-xs text-secondary">{{ formatDate(request.starts_at) }} – {{ formatDate(request.ends_at) }} · {{ request.package_code || 'Individuell' }}</p>
                        </div>
                        <span class="rounded-full bg-warning/10 px-2 py-1 text-xs font-bold text-warning">Offen</span>
                    </div>
                    <div v-if="request.rights_package?.length" class="mt-3 flex flex-wrap gap-1.5"><span v-for="right in request.rights_package" :key="right" class="rounded-full border border-border bg-card px-2 py-1 text-xs text-secondary">{{ right }}</span></div>
                    <p v-if="request.individual_offer_terms" class="mt-3 text-sm leading-6 text-secondary">{{ request.individual_offer_terms }}</p>
                    <div class="mt-4 flex gap-2">
                        <button type="button" class="min-h-10 flex-1 rounded-lg border border-error/40 px-3 text-sm font-bold text-error" @click="openPartnershipDecision(request, false)">Ablehnen</button>
                        <button type="button" class="min-h-10 flex-1 rounded-lg bg-buttonPrimary px-3 text-sm font-bold text-buttonTextPrimary" @click="openPartnershipDecision(request, true)">Annehmen</button>
                    </div>
                </article>
            </div>
        </section>

        <section class="rounded-lg border border-border bg-card p-3">
            <div class="flex flex-col gap-3 lg:flex-row lg:items-center lg:justify-between">
                <div class="flex flex-wrap gap-2">
                <button
                    v-for="scope in scopes"
                    :key="scope.key"
                    type="button"
                    class="rounded-md px-4 py-2 text-left text-sm font-semibold transition"
                    :class="activeScope === scope.key ? 'bg-buttonPrimary text-buttonTextPrimary' : 'text-secondary hover:bg-secondary/10 hover:text-primary'"
                    @click="changeScope(scope.key)"
                >
                    {{ scope.label }}
                    <span class="ml-2 rounded-full bg-secondary/20 px-2 py-0.5 text-xs">
                        {{ formatNumber(scope.key === 'all' ? sponsorStats.total : sponsorStats[scope.key]) }}
                    </span>
                </button>
                </div>
                <input
                    v-model="search"
                    type="search"
                    class="w-full rounded-lg border-border bg-inputBg text-primary lg:max-w-xs"
                    :placeholder="t('sponsors_admin.search_placeholder')"
                    :aria-label="t('sponsors_admin.search_placeholder')"
                >
            </div>
        </section>

        <section class="grid gap-4 xl:grid-cols-3">
            <article
                v-for="sponsor in filteredSponsors"
                :key="sponsor.id"
                class="flex min-h-56 flex-col justify-between rounded-lg border border-border bg-card p-4"
            >
                <div class="flex items-start justify-between gap-3">
                    <div class="flex min-w-0 items-center gap-3">
                        <div class="flex h-12 w-12 shrink-0 items-center justify-center rounded-lg border border-border bg-inputBg p-2">
                            <img
                                v-if="sponsorLogoUrl(sponsor)"
                                :src="sponsorLogoUrl(sponsor)"
                                :alt="sponsor.name"
                                class="max-h-full max-w-full object-contain"
                            >
                            <span v-else class="text-sm font-bold text-primary">{{ sponsor.name?.slice(0, 2)?.toUpperCase() }}</span>
                        </div>
                        <div class="min-w-0">
                            <h2 class="truncate text-lg font-semibold text-primary">{{ sponsor.name }}</h2>
                            <p class="truncate text-sm text-secondary">{{ sponsor.contact_name || sponsor.email || t('sponsors_admin.contact_fallback') }}</p>
                        </div>
                    </div>

                    <div class="flex shrink-0 flex-col items-end gap-1">
                        <span :class="['rounded-full px-2 py-1 text-xs font-semibold', scopeBadgeClass(sponsor)]">{{ scopeLabel(sponsor) }}</span>
                        <span :class="['rounded-full px-2 py-1 text-xs font-semibold', verificationTone(sponsor.verification_status)]">{{ verificationLabel(sponsor.verification_status) }}</span>
                    </div>
                </div>

                <div class="mt-4 grid gap-3 text-sm sm:grid-cols-2">
                    <div class="rounded-lg bg-inputBg p-3">
                        <p class="text-xs uppercase text-secondary">{{ t('sponsors_admin.budget') }}</p>
                        <p class="mt-1 font-semibold text-primary">{{ formatAmount(sponsor.amount) }}</p>
                    </div>
                    <div class="rounded-lg bg-inputBg p-3">
                        <p class="text-xs uppercase text-secondary">{{ t('sponsors_admin.duration') }}</p>
                        <p class="mt-1 font-semibold text-primary">{{ formatDate(sponsor.starts_at) }} – {{ formatDate(sponsor.ends_at) }}</p>
                    </div>
                </div>

                <div class="mt-4 flex flex-wrap gap-2">
                    <button v-if="sponsor.verification_status === 'pending_review'" type="button" class="rounded-lg border border-border px-3 py-2 text-sm font-semibold text-primary hover:bg-secondary/10" @click="openReview(sponsor)">Änderungen prüfen</button>
                    <a
                        v-if="sponsor.website"
                        :href="sponsor.website"
                        target="_blank"
                        rel="noopener noreferrer"
                        class="inline-flex items-center gap-1 rounded-lg border border-border px-3 py-2 text-sm font-semibold text-primary hover:bg-secondary/10"
                    >
                        <i class="las la-external-link-alt"></i>
                        {{ t('sponsors_admin.website') }}
                    </a>
                    <button class="rounded-lg border border-border px-3 py-2 text-sm font-semibold text-primary hover:bg-secondary/10" @click="edit(sponsor)">
                        {{ t('Bearbeiten') }}
                    </button>
                    <button class="rounded-lg border border-error/40 px-3 py-2 text-sm font-semibold text-error hover:bg-error/10" @click="openDeleteModal(sponsor)">
                        {{ t('Löschen') }}
                    </button>
                </div>
            </article>

            <div v-if="filteredSponsors.length === 0" class="rounded-lg border border-dashed border-border bg-card p-8 text-center xl:col-span-3">
                <p class="text-lg font-semibold text-primary">{{ t('sponsors_admin.empty_title') }}</p>
                <p class="mt-2 text-sm text-secondary">{{ t('sponsors_admin.empty_body') }}</p>
                <button
                    type="button"
                    class="mt-4 rounded-lg bg-buttonPrimary px-4 py-2 font-semibold text-buttonTextPrimary"
                    @click="openCreateModal(activeScope)"
                >
                    {{ t('sponsors_admin.create') }}
                </button>
            </div>
        </section>

        <div v-if="sponsors?.links?.length > 3" class="flex flex-col gap-3 rounded-lg border border-border bg-card p-4 sm:flex-row sm:items-center sm:justify-between">
            <p class="text-sm text-secondary">
                {{ t('sponsors_admin.pagination', { from: sponsors.from, to: sponsors.to, total: sponsors.total }) }}
            </p>
            <div class="flex flex-wrap gap-1">
                <button
                    v-for="link in sponsors.links"
                    :key="link.label"
                    type="button"
                    :disabled="!link.url"
                    class="min-w-10 rounded border border-border px-3 py-2 text-sm transition disabled:cursor-not-allowed disabled:opacity-50"
                    :class="link.active ? 'bg-primary text-buttonTextPrimary' : 'bg-card text-primary hover:bg-secondary/20'"
                    @click="visitPage(link.url)"
                >
                    {{ paginationLabel(link.label) }}
                </button>
            </div>
        </div>

        <Teleport to="body">
            <div
                v-if="showFormModal"
                class="fixed inset-0 z-[80] flex items-center justify-center overflow-y-auto bg-black/60 px-4 py-6"
                @click.self="closeFormModal"
            >
                <form class="w-full max-w-5xl rounded-lg border border-border bg-card shadow-2xl" @submit.prevent="submit">
                    <div class="sticky top-0 z-10 flex items-start justify-between gap-4 border-b border-border bg-card p-5">
                        <div>
                            <p class="text-xs font-semibold uppercase tracking-wide text-air-blue">{{ t('sponsors_admin.modal_eyebrow') }}</p>
                            <h2 class="mt-1 text-xl font-bold text-primary">
                                {{ editingSponsor ? t('sponsors_admin.modal_edit_title') : t('sponsors_admin.modal_create_title') }}
                            </h2>
                            <p class="mt-1 text-sm text-secondary">{{ t('sponsors_admin.modal_intro') }}</p>
                        </div>
                        <button type="button" class="rounded-lg border border-border px-3 py-2 text-secondary hover:text-primary" @click="closeFormModal">
                            <i class="las la-times text-xl"></i>
                        </button>
                    </div>

                    <div class="max-h-[75vh] overflow-y-auto p-5">
                        <div v-if="!canManageSponsors" class="mb-4 rounded-lg border border-border bg-inputBg p-4 text-sm text-primary">
                            {{ t('sponsors_admin.plan_notice') }}
                        </div>

                        <div class="grid gap-4 md:grid-cols-2 xl:grid-cols-3">
                            <label class="block">
                                <span class="text-sm font-semibold text-primary">{{ t('sponsors_admin.field_scope') }}</span>
                                <select v-model="form.scope" class="mt-1 w-full rounded-lg border-border bg-inputBg text-primary">
                                    <option value="platform">{{ t('sponsors_admin.scope_platform') }}</option>
                                    <option value="outfit_subscription">{{ t('sponsors_admin.scope_outfit') }}</option>
                                    <option value="club">{{ t('sponsors_admin.scope_club') }}</option>
                                </select>
                                <div v-if="form.errors.scope" class="mt-1 text-sm text-error">{{ form.errors.scope }}</div>
                            </label>

                            <label v-if="form.scope === 'club'" class="block">
                                <span class="text-sm font-semibold text-primary">{{ t('sponsors_admin.field_club') }}</span>
                                <input v-model="clubSearch" type="search" class="mt-1 w-full rounded-lg border-border bg-inputBg text-primary" :placeholder="t('sponsors_admin.club_search_placeholder')">
                                <select v-model="form.club_id" class="mt-1 w-full rounded-lg border-border bg-inputBg text-primary" required>
                                    <option value="">{{ t('sponsors_admin.club_placeholder') }}</option>
                                    <option v-for="club in clubs" :key="club.id" :value="club.id">{{ club.name }}</option>
                                </select>
                                <div v-if="form.errors.club_id" class="mt-1 text-sm text-error">{{ form.errors.club_id }}</div>
                            </label>

                            <label class="block">
                                <span class="text-sm font-semibold text-primary">{{ t('sponsors_admin.field_name') }}</span>
                                <input v-model="form.name" class="mt-1 w-full rounded-lg border-border bg-inputBg text-primary" :placeholder="t('sponsors_admin.name_placeholder')" required>
                                <div v-if="form.errors.name" class="mt-1 text-sm text-error">{{ form.errors.name }}</div>
                            </label>

                            <label class="block">
                                <span class="text-sm font-semibold text-primary">{{ t('sponsors_admin.field_legal_name') }}</span>
                                <input v-model="form.legal_name" class="mt-1 w-full rounded-lg border-border bg-inputBg text-primary">
                            </label>

                            <div class="block">
                                <span class="text-sm font-semibold text-primary">{{ t('sponsors_admin.field_country') }}</span>
                                <CountrySelect v-model="form.country_code" :label="t('sponsors_admin.field_country')" />
                            </div>

                            <label class="block">
                                <span class="text-sm font-semibold text-primary">{{ t('sponsors_admin.field_registration') }}</span>
                                <input v-model="form.registration_number" class="mt-1 w-full rounded-lg border-border bg-inputBg text-primary">
                            </label>

                            <label class="block">
                                <span class="text-sm font-semibold text-primary">{{ t('sponsors_admin.field_vat') }}</span>
                                <input v-model="form.vat_id" class="mt-1 w-full rounded-lg border-border bg-inputBg text-primary">
                            </label>

                            <label v-if="!editingSponsor" class="block">
                                <span class="text-sm font-semibold text-primary">{{ t('sponsors_admin.field_verification') }}</span>
                                <select v-model="form.verification_status" class="mt-1 w-full rounded-lg border-border bg-inputBg text-primary">
                                    <option value="pending_review">{{ t('sponsors_admin.verification_pending_review') }}</option>
                                    <option value="verified">{{ t('sponsors_admin.verification_verified') }}</option>
                                    <option value="rejected">{{ t('sponsors_admin.verification_rejected') }}</option>
                                </select>
                                <div v-if="form.errors.verification_status" class="mt-1 text-sm text-error">{{ form.errors.verification_status }}</div>
                            </label>

                            <label v-if="!editingSponsor" class="block xl:col-span-2">
                                <span class="text-sm font-semibold text-primary">{{ t('sponsors_admin.field_verification_note') }}</span>
                                <input v-model="form.verification_note" class="mt-1 w-full rounded-lg border-border bg-inputBg text-primary">
                            </label>

                            <label class="block">
                                <span class="text-sm font-semibold text-primary">{{ t('sponsors_admin.field_contact') }}</span>
                                <input v-model="form.contact_name" class="mt-1 w-full rounded-lg border-border bg-inputBg text-primary" :placeholder="t('sponsors_admin.contact_placeholder')">
                                <div v-if="form.errors.contact_name" class="mt-1 text-sm text-error">{{ form.errors.contact_name }}</div>
                            </label>

                            <label class="block">
                                <span class="text-sm font-semibold text-primary">{{ t('sponsors_admin.field_email') }}</span>
                                <input v-model="form.email" class="mt-1 w-full rounded-lg border-border bg-inputBg text-primary" :placeholder="t('sponsors_admin.email_placeholder')" type="email">
                                <div v-if="form.errors.email" class="mt-1 text-sm text-error">{{ form.errors.email }}</div>
                            </label>

                            <label class="block">
                                <span class="text-sm font-semibold text-primary">{{ t('sponsors_admin.field_website') }}</span>
                                <input v-model="form.website" class="mt-1 w-full rounded-lg border-border bg-inputBg text-primary" :placeholder="t('sponsors_admin.website_placeholder')" type="url">
                                <div v-if="form.errors.website" class="mt-1 text-sm text-error">{{ form.errors.website }}</div>
                            </label>

                            <div class="rounded-lg border border-border bg-inputBg p-4 xl:col-span-3">
                                <div class="flex flex-col gap-2 sm:flex-row sm:items-start sm:justify-between">
                                    <div>
                                        <p class="text-sm font-semibold text-primary">{{ t('sponsors_admin.logo_title') }}</p>
                                        <p class="mt-1 text-xs text-secondary">
                                            {{ t('sponsors_admin.logo_help') }}
                                        </p>
                                    </div>
                                    <span class="rounded-full bg-card px-3 py-1 text-xs font-semibold text-secondary">
                                        {{ isDark ? t('sponsors_admin.palette_dark') : t('sponsors_admin.palette_light') }}
                                    </span>
                                </div>

                                <div class="mt-4 grid gap-4 md:grid-cols-2">
                                    <label class="block rounded-lg border border-border bg-card p-3">
                                        <span class="text-sm font-semibold text-primary">{{ t('sponsors_admin.logo_light') }}</span>
                                        <input v-model="form.logo_light" class="mt-2 w-full rounded-lg border-border bg-inputBg text-primary" :placeholder="t('sponsors_admin.logo_light_placeholder')">
                                        <div class="mt-3 flex h-20 items-center justify-center rounded-lg border border-border bg-white p-3">
                                            <img v-if="form.logo_light" :src="previewUrl(form.logo_light)" :alt="t('sponsors_admin.logo_light')" class="max-h-full max-w-full object-contain">
                                            <span v-else class="text-xs text-slate-500">{{ t('sponsors_admin.preview_light') }}</span>
                                        </div>
                                    </label>

                                    <label class="block rounded-lg border border-border bg-card p-3">
                                        <span class="text-sm font-semibold text-primary">{{ t('sponsors_admin.logo_dark') }}</span>
                                        <input v-model="form.logo_dark" class="mt-2 w-full rounded-lg border-border bg-inputBg text-primary" :placeholder="t('sponsors_admin.logo_dark_placeholder')">
                                        <div class="mt-3 flex h-20 items-center justify-center rounded-lg border border-border bg-slate-950 p-3">
                                            <img v-if="form.logo_dark" :src="previewUrl(form.logo_dark)" :alt="t('sponsors_admin.logo_dark')" class="max-h-full max-w-full object-contain">
                                            <span v-else class="text-xs text-slate-400">{{ t('sponsors_admin.preview_dark') }}</span>
                                        </div>
                                    </label>
                                </div>

                                <input v-model="form.logo" type="hidden">
                            </div>

                            <label class="block">
                                <span class="text-sm font-semibold text-primary">{{ t('sponsors_admin.field_amount') }}</span>
                                <input v-model="form.amount" class="mt-1 w-full rounded-lg border-border bg-inputBg text-primary" :placeholder="t('sponsors_admin.amount_placeholder')" type="number" min="0" step="0.01">
                                <div v-if="form.errors.amount" class="mt-1 text-sm text-error">{{ form.errors.amount }}</div>
                            </label>

                            <label class="block">
                                <span class="text-sm font-semibold text-primary">{{ t('sponsors_admin.field_start') }}</span>
                                <input v-model="form.starts_at" class="mt-1 w-full rounded-lg border-border bg-inputBg text-primary" type="date">
                                <div v-if="form.errors.starts_at" class="mt-1 text-sm text-error">{{ form.errors.starts_at }}</div>
                            </label>

                            <label class="block">
                                <span class="text-sm font-semibold text-primary">{{ t('sponsors_admin.field_end') }}</span>
                                <input v-model="form.ends_at" class="mt-1 w-full rounded-lg border-border bg-inputBg text-primary" type="date">
                                <div v-if="form.errors.ends_at" class="mt-1 text-sm text-error">{{ form.errors.ends_at }}</div>
                            </label>
                        </div>
                    </div>

                    <div class="flex flex-col-reverse gap-2 border-t border-border p-5 sm:flex-row sm:justify-end">
                        <button type="button" class="rounded-lg border border-border px-4 py-2 font-semibold text-primary" @click="closeFormModal">
                            {{ t('Abbrechen') }}
                        </button>
                        <button class="rounded-lg bg-buttonPrimary px-4 py-2 font-semibold text-buttonTextPrimary disabled:cursor-not-allowed disabled:opacity-50" :disabled="form.processing || !canManageSponsors">
                            {{ editingSponsor ? t('sponsors_admin.save') : t('sponsors_admin.create') }}
                        </button>
                    </div>
                </form>
            </div>
        </Teleport>

        <Teleport to="body">
            <div
                v-if="deleteTarget"
                class="fixed inset-0 z-[90] flex items-center justify-center bg-black/60 px-4"
                @click.self="closeDeleteModal"
            >
                <div class="w-full max-w-md rounded-lg border border-border bg-card p-5 shadow-xl">
                    <div class="flex items-start justify-between gap-4">
                        <div>
                            <p class="text-xs font-semibold uppercase tracking-wide text-error">{{ t('sponsors_admin.delete_title') }}</p>
                            <h2 class="mt-1 text-lg font-semibold text-primary">{{ deleteTarget.name }}</h2>
                            <p class="mt-2 text-sm leading-6 text-secondary">
                                {{ t('sponsors_admin.delete_body') }} <span class="font-semibold text-primary">delete</span>.
                            </p>
                        </div>
                        <button type="button" class="rounded-lg border border-border px-3 py-1 text-secondary hover:text-primary" @click="closeDeleteModal">
                            <i class="las la-times text-lg"></i>
                        </button>
                    </div>

                    <input
                        v-model="deleteConfirmation"
                        class="mt-4 w-full rounded-lg border-border bg-inputBg text-primary"
                        :placeholder="t('sponsors_admin.delete_placeholder')"
                        autocomplete="off"
                    >

                    <div class="mt-5 flex justify-end gap-2">
                        <button type="button" class="rounded-lg border border-border px-4 py-2 text-sm font-semibold text-primary" @click="closeDeleteModal">
                            {{ t('Abbrechen') }}
                        </button>
                        <button
                            type="button"
                            class="rounded-lg bg-error px-4 py-2 text-sm font-semibold text-white disabled:cursor-not-allowed disabled:opacity-50"
                            :disabled="deleteConfirmation !== 'delete'"
                            @click="confirmDelete"
                        >
                            {{ t('sponsors_admin.delete_confirm') }}
                        </button>
                    </div>
                </div>
            </div>
        </Teleport>
        <Teleport to="body">
            <div v-if="partnershipDecisionTarget" class="fixed inset-0 z-[95] flex items-center justify-center bg-black/60 px-4" @click.self="partnershipDecisionTarget = null">
                <form class="w-full max-w-md rounded-lg border border-border bg-card p-5 shadow-xl" @submit.prevent="decidePartnership">
                    <div class="flex items-start justify-between gap-4">
                        <div>
                            <p class="text-xs font-bold uppercase tracking-wide" :class="partnershipDecisionApprove ? 'text-success' : 'text-error'">Partnerschaft</p>
                            <h2 class="mt-1 text-lg font-bold text-primary">{{ partnershipDecisionApprove ? 'Partnerschaft annehmen?' : 'Partnerschaft ablehnen?' }}</h2>
                            <p class="mt-2 text-sm text-secondary">{{ partnershipDecisionTarget.sponsor?.name }} → {{ partnershipDecisionTarget.club?.name }}</p>
                        </div>
                        <button type="button" class="flex h-10 w-10 items-center justify-center rounded-lg border border-border text-primary" aria-label="Schließen" @click="partnershipDecisionTarget = null"><i class="las la-times"></i></button>
                    </div>
                    <label class="mt-4 block"><span class="mb-1.5 block text-sm font-bold text-primary">Rückmeldung (optional)</span><textarea v-model="partnershipDecisionNote" maxlength="2000" rows="4" class="w-full rounded-lg border-border bg-inputBg text-primary" /></label>
                    <div class="mt-5 flex justify-end gap-2">
                        <button type="button" class="min-h-11 rounded-lg border border-border px-4 text-sm font-bold text-primary" @click="partnershipDecisionTarget = null">Abbrechen</button>
                        <button type="submit" class="min-h-11 rounded-lg px-5 text-sm font-bold" :class="partnershipDecisionApprove ? 'bg-buttonPrimary text-buttonTextPrimary' : 'bg-error text-white'">{{ partnershipDecisionApprove ? 'Annehmen' : 'Ablehnen' }}</button>
                    </div>
                </form>
            </div>
        </Teleport>
        <Teleport to="body">
            <div v-if="reviewTarget" class="fixed inset-0 z-[100] flex items-end justify-center bg-black/65 sm:items-center sm:p-5" @click.self="reviewTarget = null">
                <section role="dialog" aria-modal="true" aria-label="Sponsorprofil prüfen" class="max-h-[95dvh] w-full overflow-y-auto rounded-t-lg border border-border bg-card p-5 shadow-xl sm:max-w-2xl sm:rounded-lg">
                    <div class="flex items-start justify-between gap-3">
                        <div><h2 class="text-xl font-bold text-primary">Sponsorprofil prüfen</h2><p class="text-sm text-secondary">{{ reviewTarget.name }} · {{ verificationLabel(reviewTarget.verification_status) }}</p></div>
                        <button type="button" class="rounded border border-border px-3 py-2 text-primary" aria-label="Schließen" @click="reviewTarget = null"><i class="las la-times" /></button>
                    </div>
                    <h3 class="mt-5 font-bold text-primary">Änderungen seit der Einreichung</h3>
                    <p v-if="!reviewTarget.review_changes?.length" class="mt-2 text-sm text-secondary">Für diese Einreichung liegt kein Vorher/Nachher-Vergleich vor. Bitte alle aktuellen Angaben prüfen.</p>
                    <div v-else class="mt-2 grid gap-2">
                        <div v-for="(change, index) in reviewTarget.review_changes" :key="index" class="rounded border border-border bg-inputBg p-3 text-sm">
                            <p class="font-bold text-primary">{{ reviewLabels[change.field] || change.field }}</p>
                            <p class="break-all text-secondary">Vorher: {{ reviewValue(change.before) }}</p>
                            <p class="break-all text-primary">Jetzt: {{ reviewValue(change.after) }}</p>
                        </div>
                    </div>
                    <h3 class="mt-6 font-bold text-primary">Aktuelle Unternehmensangaben</h3>
                    <dl class="mt-2 grid gap-2 sm:grid-cols-2">
                        <div v-for="(label, field) in reviewLabels" :key="field" class="min-w-0 border-b border-border py-2 text-sm">
                            <dt class="font-semibold text-secondary">{{ label }}</dt><dd class="break-all text-primary">{{ reviewValue(reviewTarget.profile_fields?.[field]) }}</dd>
                        </div>
                    </dl>
                    <template v-if="canReview && reviewTarget.verification_status === 'pending_review'">
                        <label class="mt-5 block text-sm font-bold text-primary">Prüfnotiz<textarea v-model="reviewNote" rows="3" maxlength="2000" class="mt-1 w-full rounded border-border bg-inputBg text-primary" placeholder="Bei Ablehnung erforderlich" /></label>
                        <p v-if="reviewError" class="mt-2 text-sm text-error" role="alert">{{ reviewError }}</p>
                        <div class="mt-4 flex flex-wrap justify-end gap-2">
                            <button type="button" :disabled="reviewBusy" class="rounded border border-border px-4 py-2 text-primary" @click="reviewTarget = null">Schließen</button>
                            <button type="button" :disabled="reviewBusy" class="rounded border border-error px-4 py-2 font-bold text-error" @click="submitReview('rejected')">Ablehnen</button>
                            <button type="button" :disabled="reviewBusy" class="rounded bg-buttonPrimary px-4 py-2 font-bold text-buttonTextPrimary" @click="submitReview('verified')">Freigeben</button>
                        </div>
                    </template>
                </section>
            </div>
        </Teleport>
    </div>
</template>
