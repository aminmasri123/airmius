<script setup>
import AppLayout from '@/Components/Auth/Layouts/AppLayout.vue'
import SearchableSelect from '@/Components/SearchableSelect.vue'
import { Head, router, useForm, usePage } from '@inertiajs/vue3'
import { computed, ref } from 'vue'
import { useI18n } from 'vue-i18n'
import { useTheme } from '@/services/useTheme'

defineOptions({ layout: AppLayout })

const props = defineProps({
    plans: { type: Array, default: () => [] },
    styleProfile: { type: Object, default: null },
    subscriptions: { type: Array, default: () => [] },
    heroImageUrl: { type: String, default: '/images/marketplace/airmius_outfit_abo.webp' },
    sports: { type: Array, default: () => [] },
    contractRules: { type: Object, default: () => ({
        minimum_term_months: 3,
        pause_allowed_after_months: 3,
        cancellation_notice_days: 14,
        changes_locked_after_shipping_preparation: true,
    }) },
})

const { t, te, locale } = useI18n()
const tx = (key, fallback, values = {}) => {
    const translated = t(key, values)
    return translated === key ? fallback : translated
}
const { isDark } = useTheme()
const page = usePage()
const currentUser = computed(() => page.props.auth?.user || {})
const countryCode = (value) => {
    const code = String(value || '').trim().toUpperCase()

    return code.length === 2 ? code : 'DE'
}
const profileForm = useForm({
    sport_focus: props.styleProfile?.sport_focus || '',
    sizes_text: (props.styleProfile?.sizes || []).join(', '),
    fit_preference: props.styleProfile?.fit_preference || 'regular',
    colors_text: (props.styleProfile?.colors || []).join(', '),
    excluded_colors_text: (props.styleProfile?.excluded_colors || []).join(', '),
    brand_style: props.styleProfile?.brand_style || 'minimal',
    notes: props.styleProfile?.notes || '',
})
const issueForm = useForm({
    issue_type: 'exchange',
    issue_description: '',
    issue_requested_resolution: '',
    issue_exchange_size: '',
})

const pendingCancelSubscription = ref(null)
const pendingIssueDelivery = ref(null)
const pendingSubscribePlan = ref(null)
const subscribeAcceptedTerms = ref(false)
const subscribeAcceptedContract = ref(false)
const subscribePaymentProvider = ref('bank_transfer')
const shippingName = ref(currentUser.value.name || '')
const shippingCountry = ref(countryCode(currentUser.value.country))
const shippingStreet = ref(currentUser.value.street || '')
const shippingHouseNumber = ref(currentUser.value.house_number || '')
const shippingPostalCode = ref(currentUser.value.postal_code || '')
const shippingCity = ref(currentUser.value.city || '')
const shippingState = ref(currentUser.value.state || '')
const shippingNote = ref('')
const subscribingPlanId = ref(null)
const profileFeedback = ref(null)
const activeSubscriptions = computed(() => props.subscriptions.filter((subscription) => ['active', 'paused', 'payment_paused', 'cancels_at_period_end'].includes(subscription.status)))
const hasShippingAddress = computed(() => Boolean(
    shippingName.value &&
    shippingCountry.value &&
    shippingStreet.value &&
    shippingPostalCode.value &&
    shippingCity.value
))
const nextDelivery = computed(() => activeSubscriptions.value
    .map((subscription) => subscription.next_delivery_at)
    .filter(Boolean)
    .sort()[0] || null)
const hasStyleProfile = computed(() => Boolean(
    profileForm.sport_focus ||
    profileForm.sizes_text ||
    profileForm.colors_text ||
    profileForm.excluded_colors_text ||
    profileForm.notes
))

const toList = (value) => String(value || '')
    .split(',')
    .map((item) => item.trim())
    .filter(Boolean)

const sportLabel = (value) => {
    const sport = props.sports.find((sport) => sport.slug === value || sport.name === value || String(sport.id) === String(value))

    if (!sport) return value

    const key = `sports.${sport.slug}`

    return te(key) ? t(key) : sport.name
}

const initials = (name) => (name || '?')
    .split(' ')
    .map((part) => part[0])
    .join('')
    .slice(0, 2)
    .toUpperCase()

const sponsorLogoUrl = (sponsor) => isDark.value
    ? (sponsor?.logo_dark_url || sponsor?.logo_light_url || sponsor?.logo_url)
    : (sponsor?.logo_light_url || sponsor?.logo_dark_url || sponsor?.logo_url)

const localeCode = computed(() => ({ ar: 'ar-EG', fr: 'fr-FR', en: 'en-US', de: 'de-DE' })[locale.value] || 'de-DE')
const formatMoney = (cents, currency = 'EUR') => new Intl.NumberFormat(localeCode.value, {
    style: 'currency',
    currency,
}).format(Number(cents || 0) / 100)

const formatDate = (value) => {
    if (!value) return tx('outfit_workspace.not_planned', 'Noch nicht geplant')

    return new Intl.DateTimeFormat(localeCode.value, {
        day: '2-digit',
        month: '2-digit',
        year: 'numeric',
    }).format(new Date(value))
}

const scrollToPlans = () => {
    document.getElementById('outfit-plans')?.scrollIntoView({
        behavior: 'smooth',
        block: 'start',
    })
}

const statusLabel = (status) => ({
    active: tx('outfit_workspace.status.active', 'Aktiv'),
    paused: tx('outfit_workspace.status.paused', 'Pausiert'),
    payment_paused: tx('outfit_workspace.status.payment_paused', 'Wegen Zahlung pausiert'),
    cancels_at_period_end: tx('outfit_workspace.status.cancels_at_period_end', 'Gekündigt zum Laufzeitende'),
    pending_payment: tx('outfit_workspace.status.pending_payment', 'Zahlung offen'),
    pending_confirmation: tx('outfit_workspace.status.pending_confirmation', 'Wartet auf Freigabe'),
    cancelled: tx('outfit_workspace.status.cancelled', 'Gekündigt'),
    planned: tx('outfit_workspace.status.planned', 'Geplant'),
    preparing: tx('outfit_workspace.status.preparing', 'In Vorbereitung'),
    shipped: tx('outfit_workspace.status.shipped', 'Versendet'),
    delivered: tx('outfit_workspace.status.delivered', 'Geliefert'),
    skipped: tx('outfit_workspace.status.skipped', 'Ausgesetzt'),
}[status] || status)

const issueTypeLabel = (type) => ({
    exchange: tx('outfit_workspace.issue_types.exchange', 'Umtausch'),
    return: tx('outfit_workspace.issue_types.return', 'Retoure'),
    damaged: tx('outfit_workspace.issue_types.damaged', 'Beschädigt'),
    missing_item: tx('outfit_workspace.issue_types.missing_item', 'Artikel fehlt'),
    wrong_item: tx('outfit_workspace.issue_types.wrong_item', 'Falscher Artikel'),
    other: tx('outfit_workspace.issue_types.other', 'Sonstiges'),
}[type] || type || '-')

const issueStatusLabel = (status) => ({
    open: tx('outfit_workspace.issue_status.open', 'Offen'),
    reviewing: tx('outfit_workspace.issue_status.reviewing', 'In Prüfung'),
    approved: tx('outfit_workspace.issue_status.approved', 'Freigegeben'),
    return_waiting: tx('outfit_workspace.issue_status.return_waiting', 'Rücksendung offen'),
    replacement_preparing: tx('outfit_workspace.issue_status.replacement_preparing', 'Ersatz wird vorbereitet'),
    resolved: tx('outfit_workspace.issue_status.resolved', 'Gelöst'),
    rejected: tx('outfit_workspace.issue_status.rejected', 'Abgeschlossen'),
}[status] || status || '-')

const paymentProviderLabel = (provider) => ({
    bank_transfer: tx('outfit_workspace.payment.bank_transfer', 'Überweisung'),
    paypal: tx('outfit_workspace.payment.paypal', 'PayPal'),
}[provider] || provider || '-')

const shippingAddressLine = (address) => [
    [address?.street, address?.house_number].filter(Boolean).join(' '),
    [address?.postal_code, address?.city].filter(Boolean).join(' '),
    [address?.state, address?.country].filter(Boolean).join(', '),
].filter(Boolean).join(', ')

const isPendingPayment = (subscription) => subscription?.status === 'pending_payment'

const cancelActionLabel = (subscription) => isPendingPayment(subscription)
    ? tx('outfit_workspace.actions.cancel_pending', 'Abbrechen')
    : tx('outfit_workspace.actions.cancel', 'Kündigen')

const planContractRules = (plan) => plan?.contract_rules || props.contractRules

const planContractTerms = (plan) => (plan?.contract_terms?.length ? plan.contract_terms : [
    tx('outfit_workspace.contract_terms.monthly', 'Das Outfit-Abo ist ein monatliches Abonnement mit wiederkehrender Zahlung.'),
    tx('outfit_workspace.contract_terms.payment', 'Die erste Lieferung wird erst nach bestätigter Zahlung vorbereitet.'),
    tx('outfit_workspace.contract_terms.future', 'Pause und Kündigung gelten nur für zukünftige Lieferungen.'),
])

const brandingLabel = (type) => ({
    sponsor_logo: tx('outfit_workspace.branding.sponsor', 'Sponsor-Branding'),
    club_logo: tx('outfit_workspace.branding.club', 'Vereins-Branding'),
    custom: tx('outfit_workspace.branding.custom', 'Individuelles Branding'),
}[type] || tx('outfit_workspace.branding.default', 'Branding'))

const updateProfile = () => {
    profileFeedback.value = null

    profileForm.transform((data) => ({
        sport_focus: data.sport_focus,
        sizes: toList(data.sizes_text),
        fit_preference: data.fit_preference,
        colors: toList(data.colors_text),
        excluded_colors: toList(data.excluded_colors_text),
        brand_style: data.brand_style,
        notes: data.notes,
    })).put(route('auth.outfit-subscriptions.profile.update'), {
        preserveScroll: true,
        onSuccess: () => {
            profileFeedback.value = {
                type: 'success',
                message: tx('outfit_workspace.feedback.profile_saved', 'Style-Profil wurde gespeichert.'),
            }
        },
        onError: () => {
            profileFeedback.value = {
                type: 'error',
                message: tx('outfit_workspace.feedback.profile_failed', 'Style-Profil konnte nicht gespeichert werden. Bitte prüfe deine Angaben.'),
            }
        },
    })
}

const subscribe = (plan) => {
    pendingSubscribePlan.value = plan
    subscribeAcceptedTerms.value = false
    subscribeAcceptedContract.value = false
    subscribePaymentProvider.value = 'bank_transfer'
}

const closeSubscribeModal = () => {
    if (subscribingPlanId.value) return

    pendingSubscribePlan.value = null
    subscribeAcceptedTerms.value = false
    subscribeAcceptedContract.value = false
    subscribePaymentProvider.value = 'bank_transfer'
}

const confirmSubscribe = () => {
    if (!pendingSubscribePlan.value || !subscribeAcceptedTerms.value || !subscribeAcceptedContract.value || !hasShippingAddress.value) return

    subscribingPlanId.value = pendingSubscribePlan.value.id

    router.post(route('auth.outfit-subscriptions.store', pendingSubscribePlan.value.id), {
        accepted_terms: subscribeAcceptedTerms.value,
        accepted_contract: subscribeAcceptedContract.value,
        payment_provider: subscribePaymentProvider.value,
        shipping_name: shippingName.value,
        shipping_country: shippingCountry.value,
        shipping_street: shippingStreet.value,
        shipping_house_number: shippingHouseNumber.value,
        shipping_postal_code: shippingPostalCode.value,
        shipping_city: shippingCity.value,
        shipping_state: shippingState.value,
        shipping_note: shippingNote.value,
    }, {
        preserveScroll: true,
        onFinish: () => {
            subscribingPlanId.value = null
            pendingSubscribePlan.value = null
            subscribeAcceptedTerms.value = false
            subscribeAcceptedContract.value = false
            subscribePaymentProvider.value = 'bank_transfer'
        },
    })
}

const pause = (subscription) => router.post(route('auth.outfit-subscriptions.pause', subscription.id), {}, { preserveScroll: true })
const resume = (subscription) => router.post(route('auth.outfit-subscriptions.resume', subscription.id), {}, { preserveScroll: true })
const requestCancel = (subscription) => {
    pendingCancelSubscription.value = subscription
}
const closeCancelModal = () => {
    pendingCancelSubscription.value = null
}
const confirmCancel = () => {
    if (!pendingCancelSubscription.value) return

    router.post(route('auth.outfit-subscriptions.cancel', pendingCancelSubscription.value.id), {}, {
        preserveScroll: true,
        onFinish: closeCancelModal,
    })
}

const canRequestIssue = (delivery) => ['shipped', 'delivered'].includes(delivery.status) && !['open', 'reviewing', 'approved', 'return_waiting', 'replacement_preparing'].includes(delivery.issue_status)
const openIssueModal = (delivery) => {
    issueForm.reset()
    issueForm.clearErrors()
    issueForm.issue_type = 'exchange'
    pendingIssueDelivery.value = delivery
}
const closeIssueModal = () => {
    if (issueForm.processing) return

    pendingIssueDelivery.value = null
}
const submitIssue = () => {
    if (!pendingIssueDelivery.value) return

    issueForm.post(route('auth.outfit-deliveries.issue.request', pendingIssueDelivery.value.id), {
        preserveScroll: true,
        onSuccess: closeIssueModal,
    })
}
</script>

<template>
    <Head :title="tx('outfit_workspace.page_title', 'Outfit-Abo')" />

    <div class="space-y-6 p-4 sm:p-6">
        <div
            v-if="page.props.flash?.success || page.props.flash?.error"
            class="rounded-lg border px-4 py-3 text-sm font-semibold"
            :class="page.props.flash?.success
                ? 'border-success/30 bg-success/10 text-success'
                : 'border-error/30 bg-error/10 text-error'"
            role="status"
        >
            {{ page.props.flash?.success || page.props.flash?.error }}
        </div>

        <section class="overflow-hidden rounded-lg border border-border bg-card shadow-sm">
            <div class="grid min-h-[28rem] lg:grid-cols-[minmax(0,1fr)_22rem] xl:grid-cols-[minmax(0,1fr)_27rem]">
                <div class="relative flex min-h-[24rem] items-end overflow-hidden bg-black">
                    <img
                        :src="heroImageUrl"
                        alt="Airmius Outfit-Abo"
                        class="absolute inset-0 h-full w-full object-cover"
                    >
                    <div class="absolute inset-0 bg-gradient-to-t from-black/85 via-black/35 to-black/10"></div>

                    <div class="relative z-10 max-w-3xl p-5 sm:p-7 lg:p-8">
                        <p class="text-sm font-semibold uppercase tracking-wide text-white/80">{{ tx('auto.Sportkleidung monatlich', 'Sportkleidung monatlich') }}</p>
                        <h1 class="mt-3 max-w-2xl text-3xl font-black leading-tight text-white sm:text-5xl">
                            {{ tx('auto.Outfit-Abo für deinen Style', 'Outfit-Abo für deinen Style') }}
                        </h1>
                        <p class="mt-4 max-w-2xl text-sm leading-6 text-white/85 sm:text-base">
                            {{ tx('auto.Wähle einen Plan, pflege dein Style-Profil und erhalte regelmäßig Sport-Outfits passend zu Sportart, Größe, Farben und Markenstil.', 'Wähle einen Plan, pflege dein Style-Profil und erhalte regelmäßig Sport-Outfits passend zu Sportart, Größe, Farben und Markenstil.') }}
                        </p>

                        <div class="mt-6 flex flex-wrap gap-3">
                            <button type="button" class="rounded-lg bg-white px-4 py-2 text-sm font-bold text-slate-950 hover:bg-white/90" @click="scrollToPlans">
                                {{ tx('auto.Plan wählen', 'Plan wählen') }}
                            </button>
                            <a href="#style-profile" class="rounded-lg border border-white/35 bg-white/10 px-4 py-2 text-sm font-bold text-white backdrop-blur hover:bg-white/20">
                                {{ tx('auto.Style-Profil pflegen', 'Style-Profil pflegen') }}
                            </a>
                        </div>
                    </div>
                </div>

                <div class="grid content-between gap-4 border-t border-border bg-card p-5 lg:border-l lg:border-t-0 lg:p-6">
                    <div>
                        <p class="text-xs font-semibold uppercase tracking-wide text-accent">{{ tx('outfit_workspace.eyebrow', 'Abo-Zentrale') }}</p>
                        <h2 class="mt-2 text-xl font-bold text-primary">{{ tx('outfit_workspace.overview', 'Alles auf einen Blick') }}</h2>
                        <p class="mt-2 text-sm leading-6 text-secondary">
                            {{ tx('auto.Aktive Abos, nächste Lieferung und Style-Daten bleiben hier schnell erreichbar.', 'Aktive Abos, nächste Lieferung und Style-Daten bleiben hier schnell erreichbar.') }}
                        </p>
                    </div>

                    <div class="grid gap-3">
                        <div class="rounded-lg border border-border bg-inputBg p-4">
                            <p class="text-xs uppercase text-secondary">{{ tx('outfit_workspace.active_subscriptions', 'Aktive Abos') }}</p>
                            <p class="mt-2 text-3xl font-black text-primary">{{ activeSubscriptions.length }}</p>
                        </div>
                        <div class="rounded-lg border border-border bg-inputBg p-4">
                            <p class="text-xs uppercase text-secondary">{{ tx('outfit_workspace.next_delivery', 'Nächste Lieferung') }}</p>
                            <p class="mt-2 text-lg font-bold text-primary">{{ formatDate(nextDelivery) }}</p>
                        </div>
                        <div class="rounded-lg border border-border bg-inputBg p-4">
                            <p class="text-xs uppercase text-secondary">{{ tx('outfit_workspace.style_profile', 'Style-Profil') }}</p>
                            <p class="mt-2 text-lg font-bold" :class="hasStyleProfile ? 'text-success' : 'text-warning'">
                                {{ hasStyleProfile ? tx('outfit_ui.ready', 'Bereit') : tx('outfit_ui.open', 'Noch offen') }}
                            </p>
                        </div>
                    </div>

                    <div class="rounded-lg border border-accent/30 bg-accent/10 p-4">
                        <p class="text-sm font-semibold text-primary">{{ tx('auto.Tipp', 'Tipp') }}</p>
                        <p class="mt-1 text-sm text-secondary">
                            {{ tx('auto.Je genauer Grössen, Farben und No-Gos sind, desto besser passt die monatliche Box.', 'Je genauer Grössen, Farben und No-Gos sind, desto besser passt die monatliche Box.') }}
                        </p>
                    </div>
                </div>
            </div>
        </section>

        <section class="grid gap-6 xl:grid-cols-[.85fr_1.15fr]">
            <form id="style-profile" class="rounded-lg border border-border bg-card p-5 shadow-sm" @submit.prevent="updateProfile">
                <div class="flex items-start justify-between gap-4">
                    <div>
                        <h2 class="text-lg font-bold text-primary">{{ tx('outfit_workspace.style_profile', 'Style-Profil') }}</h2>
                        <p class="mt-1 text-sm text-secondary">{{ tx('auto.Diese Angaben steuern die Zusammenstellung deiner Boxen.', 'Diese Angaben steuern die Zusammenstellung deiner Boxen.') }}</p>
                    </div>
                    <button class="rounded-lg bg-buttonPrimary px-4 py-2 text-sm font-semibold text-buttonTextPrimary disabled:opacity-50" :disabled="profileForm.processing">
                        {{ tx('auto.Speichern', 'Speichern') }}
                    </button>
                </div>

                <div
                    v-if="profileFeedback"
                    class="mt-4 rounded-lg border px-4 py-3 text-sm font-semibold"
                    :class="profileFeedback.type === 'success'
                        ? 'border-success/30 bg-success/10 text-success'
                        : 'border-error/30 bg-error/10 text-error'"
                    role="status"
                >
                    {{ profileFeedback.message }}
                </div>

                <div class="mt-5 grid gap-4 sm:grid-cols-2">
                    <label class="block">
                        <span class="text-sm font-semibold text-primary">{{ tx('auto.Sportfokus', 'Sportfokus') }}</span>
                        <SearchableSelect
                            v-model="profileForm.sport_focus"
                            :options="sports"
                            value-key="slug"
                            translation-prefix="sports"
                            category-translation-prefix="sport_categories"
                            class="mt-1"
                            :placeholder="tx('auto.Sportart suchen', 'Sportart suchen')"
                        />
                    </label>
                    <label class="block">
                        <span class="text-sm font-semibold text-primary">{{ tx('auto.Passform', 'Passform') }}</span>
                        <select v-model="profileForm.fit_preference" class="mt-1 w-full rounded-lg border-border bg-inputBg text-primary">
                            <option value="slim">{{ tx('auto.Slim', 'Slim') }}</option>
                            <option value="regular">{{ tx('auto.Regular', 'Regular') }}</option>
                            <option value="relaxed">{{ tx('auto.Locker', 'Locker') }}</option>
                        </select>
                    </label>
                    <label class="block">
                        <span class="text-sm font-semibold text-primary">{{ tx('auto.Grössen', 'Grössen') }}</span>
                        <input v-model="profileForm.sizes_text" class="mt-1 w-full rounded-lg border-border bg-inputBg text-primary" :placeholder="tx('auto.M, L, 42', 'M, L, 42')" />
                    </label>
                    <label class="block">
                        <span class="text-sm font-semibold text-primary">{{ tx('auto.Lieblingsfarben', 'Lieblingsfarben') }}</span>
                        <input v-model="profileForm.colors_text" class="mt-1 w-full rounded-lg border-border bg-inputBg text-primary" :placeholder="tx('auto.Schwarz, Blau, Weiß', 'Schwarz, Blau, Weiß')" />
                    </label>
                    <label class="block">
                        <span class="text-sm font-semibold text-primary">{{ tx('auto.Ausschlussfarben', 'Ausschlussfarben') }}</span>
                        <input v-model="profileForm.excluded_colors_text" class="mt-1 w-full rounded-lg border-border bg-inputBg text-primary" :placeholder="tx('auto.Gelb, Pink', 'Gelb, Pink')" />
                    </label>
                    <label class="block">
                        <span class="text-sm font-semibold text-primary">{{ tx('auto.Stil', 'Stil') }}</span>
                        <select v-model="profileForm.brand_style" class="mt-1 w-full rounded-lg border-border bg-inputBg text-primary">
                            <option value="minimal">{{ tx('auto.Minimal', 'Minimal') }}</option>
                            <option value="bold">{{ tx('auto.Auffällig', 'Auffällig') }}</option>
                            <option value="classic">{{ tx('auto.Klassisch', 'Klassisch') }}</option>
                            <option value="team">{{ tx('auto.Team-orientiert', 'Team-orientiert') }}</option>
                        </select>
                    </label>
                    <label class="block sm:col-span-2">
                        <span class="text-sm font-semibold text-primary">{{ tx('auto.Notizen', 'Notizen') }}</span>
                        <textarea v-model="profileForm.notes" rows="3" class="mt-1 w-full rounded-lg border-border bg-inputBg text-primary" :placeholder="tx('auto.Materialwünsche, Marken, No-Gos, besondere Hinweise', 'Materialwünsche, Marken, No-Gos, besondere Hinweise')"></textarea>
                    </label>
                </div>
            </form>

            <div class="rounded-lg border border-border bg-card p-5 shadow-sm">
                <div class="flex items-start justify-between gap-4">
                    <div>
                        <h2 class="text-lg font-bold text-primary">{{ tx('outfit_workspace.subscriptions_title', 'Deine Abos und Lieferungen') }}</h2>
                        <p class="mt-1 text-sm text-secondary">{{ tx('outfit_workspace.subscriptions_hint', 'Status, Liefermonat und Tracking an einem Ort.') }}</p>
                    </div>
                    <span class="rounded-full bg-inputBg px-3 py-1 text-xs font-semibold text-secondary">{{ subscriptions.length }} {{ tx('auto.Einträge', 'Einträge') }}</span>
                </div>

                <div v-if="subscriptions.length" class="mt-5 space-y-4">
                    <article v-for="subscription in subscriptions" :key="subscription.id" class="rounded-lg border border-border bg-inputBg p-4">
                        <div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
                            <div>
                                <p class="text-base font-bold text-primary">{{ subscription.plan?.name || tx('outfit_ui.subscription', 'Outfit-Abo') }}</p>
                                <p class="mt-1 text-sm text-secondary">
                                    {{ statusLabel(subscription.status) }} - {{ tx('auto.Nächste Lieferung', 'Nächste Lieferung') }}: {{ formatDate(subscription.next_delivery_at) }}
                                </p>
                                <p class="mt-1 text-sm text-secondary">
                                    {{ tx('auto.Zahlungsart', 'Zahlungsart') }}: {{ paymentProviderLabel(subscription.payment_provider) }}
                                </p>
                                <p v-if="subscription.dunning_level" class="mt-1 text-sm text-amber-200">
                                    {{ tx('outfit_ui.warning_level', 'Mahnstufe') }} {{ subscription.dunning_level }}/3
                                    <span v-if="subscription.last_dunning_sent_at"> - {{ tx('outfit_ui.last_warning', 'letzte Mahnung') }}: {{ formatDate(subscription.last_dunning_sent_at) }}</span>
                                </p>
                                <p v-if="subscription.status === 'payment_paused'" class="mt-1 text-sm text-amber-200">
                                    {{ tx('auto.Dieses Abo ist bis zum Zahlungseingang pausiert. Es werden keine weiteren Lieferungen vorbereitet.', 'Dieses Abo ist bis zum Zahlungseingang pausiert. Es werden keine weiteren Lieferungen vorbereitet.') }}
                                </p>
                                <p class="mt-1 text-sm font-semibold text-primary">{{ formatMoney(subscription.monthly_price_cents, subscription.currency) }} / {{ tx('auto.Monat', 'Monat') }}</p>
                            </div>
                            <div class="flex flex-wrap gap-2">
                                <button v-if="subscription.status === 'active'" type="button" class="rounded-lg border border-border px-3 py-2 text-sm text-primary hover:bg-card" @click="pause(subscription)">{{ tx('auto.Pausieren', 'Pausieren') }}</button>
                                <button v-if="subscription.status === 'paused'" type="button" class="rounded-lg border border-border px-3 py-2 text-sm text-primary hover:bg-card" @click="resume(subscription)">{{ tx('auto.Fortsetzen', 'Fortsetzen') }}</button>
                                <button v-if="!['cancelled', 'cancels_at_period_end'].includes(subscription.status)" type="button" class="rounded-lg border border-error/50 px-3 py-2 text-sm text-error hover:bg-error/10" @click="requestCancel(subscription)">
                                    {{ cancelActionLabel(subscription) }}
                                </button>
                            </div>
                            <p v-if="subscription.status === 'cancels_at_period_end'" class="mt-2 text-sm text-amber-200">
                                {{ tx('outfit_ui.cancelled_at', 'Gekündigt zum') }} {{ formatDate(subscription.current_period_ends_at) }}. {{ tx('outfit_ui.active_until', 'Bis dahin bleibt das Abo aktiv.') }}
                            </p>
                        </div>

                        <div
                            v-if="isPendingPayment(subscription) && subscription.payment_provider === 'bank_transfer'"
                            class="mt-4 rounded-lg border border-warning/30 bg-warning/10 p-4"
                        >
                            <div class="flex flex-col gap-2 sm:flex-row sm:items-start sm:justify-between">
                                <div>
                                    <p class="text-sm font-bold text-primary">{{ tx('auto.Überweisungsdaten', 'Überweisungsdaten') }}</p>
                                    <p class="mt-1 text-xs leading-5 text-secondary">
                                        {{ tx('auto.Bitte nutze exakt diesen Verwendungszweck, damit deine Zahlung zugeordnet werden kann.', 'Bitte nutze exakt diesen Verwendungszweck, damit deine Zahlung zugeordnet werden kann.') }}
                                    </p>
                                </div>
                                <span class="rounded-full bg-card px-3 py-1 text-xs font-semibold text-warning">{{ tx('outfit_workspace.status.pending_payment', 'Zahlung offen') }}</span>
                            </div>

                            <dl class="mt-4 grid gap-3 text-sm sm:grid-cols-2">
                                <div class="rounded-lg bg-card p-3">
                                    <dt class="text-xs uppercase text-secondary">{{ tx('auto.Betrag', 'Betrag') }}</dt>
                                    <dd class="mt-1 font-bold text-primary">{{ formatMoney(subscription.monthly_price_cents, subscription.currency) }}</dd>
                                </div>
                                <div class="rounded-lg bg-card p-3">
                                    <dt class="text-xs uppercase text-secondary">{{ tx('outfit_ui.due', 'Fällig bis') }}</dt>
                                    <dd class="mt-1 font-bold text-primary">{{ formatDate(subscription.payment_due_at) }}</dd>
                                </div>
                                <div class="rounded-lg bg-card p-3 sm:col-span-2">
                                    <dt class="text-xs uppercase text-secondary">{{ tx('auto.Verwendungszweck / Zahlungsreferenz', 'Verwendungszweck / Zahlungsreferenz') }}</dt>
                                    <dd class="mt-1 break-all font-bold text-primary">{{ subscription.payment_reference }}</dd>
                                </div>
                                <div class="rounded-lg bg-card p-3">
                                    <dt class="text-xs uppercase text-secondary">{{ tx('outfit_ui.bank_holder', 'Kontoinhaber') }}</dt>
                                    <dd class="mt-1 font-bold text-primary">{{ subscription.bank_transfer?.bank_account_holder || '-' }}</dd>
                                </div>
                                <div class="rounded-lg bg-card p-3">
                                    <dt class="text-xs uppercase text-secondary">{{ tx('outfit_ui.bank', 'Bank') }}</dt>
                                    <dd class="mt-1 font-bold text-primary">{{ subscription.bank_transfer?.bank_name || '-' }}</dd>
                                </div>
                                <div class="rounded-lg bg-card p-3">
                                    <dt class="text-xs uppercase text-secondary">{{ tx('outfit_ui.iban', 'IBAN') }}</dt>
                                    <dd class="mt-1 break-all font-bold text-primary">{{ subscription.bank_transfer?.iban || '-' }}</dd>
                                </div>
                                <div class="rounded-lg bg-card p-3">
                                    <dt class="text-xs uppercase text-secondary">{{ tx('outfit_ui.bic', 'BIC') }}</dt>
                                    <dd class="mt-1 font-bold text-primary">{{ subscription.bank_transfer?.bic || '-' }}</dd>
                                </div>
                            </dl>
                        </div>

                        <div v-if="subscription.shipping_address" class="mt-4 rounded-lg bg-card p-3">
                            <p class="text-xs uppercase text-secondary">{{ tx('auto.Lieferadresse', 'Lieferadresse') }}</p>
                            <p class="mt-1 text-sm font-semibold text-primary">{{ subscription.shipping_address.name || '-' }}</p>
                            <p class="text-sm text-secondary">{{ shippingAddressLine(subscription.shipping_address) || '-' }}</p>
                            <p v-if="subscription.shipping_address.note" class="mt-1 text-xs text-secondary">{{ subscription.shipping_address.note }}</p>
                        </div>

                        <div v-if="subscription.deliveries?.length" class="mt-4 grid gap-3 md:grid-cols-2">
                            <div v-for="delivery in subscription.deliveries" :key="delivery.id" class="rounded-lg bg-card p-3">
                                <p class="text-sm font-semibold text-primary">{{ statusLabel(delivery.status) }}</p>
                                <p class="text-xs text-secondary">{{ formatDate(delivery.delivery_month) }}</p>
                                <p v-if="delivery.tracking_number" class="mt-1 text-xs text-secondary">{{ delivery.carrier }} - {{ delivery.tracking_number }}</p>
                                <a v-if="delivery.tracking_url" :href="delivery.tracking_url" target="_blank" rel="noopener noreferrer" class="mt-1 inline-flex text-xs font-semibold text-accent underline underline-offset-2">
                                    {{ tx('auto.Tracking öffnen', 'Tracking öffnen') }}
                                </a>
                                <p v-if="delivery.notes" class="mt-1 text-xs text-secondary">{{ delivery.notes }}</p>
                                <div v-if="delivery.issue_status" class="mt-3 rounded-lg border border-border bg-inputBg p-3">
                                    <p class="text-xs font-semibold uppercase text-secondary">{{ issueTypeLabel(delivery.issue_type) }}</p>
                                    <p class="mt-1 text-sm font-semibold text-primary">{{ issueStatusLabel(delivery.issue_status) }}</p>
                                    <p v-if="delivery.issue_admin_note" class="mt-1 text-xs text-secondary">{{ delivery.issue_admin_note }}</p>
                                    <a v-if="delivery.return_tracking_url" :href="delivery.return_tracking_url" target="_blank" rel="noopener noreferrer" class="mt-1 inline-flex text-xs font-semibold text-accent underline underline-offset-2">
                                        {{ tx('auto.Retouren-Tracking öffnen', 'Retouren-Tracking öffnen') }}
                                    </a>
                                </div>
                                <button v-if="canRequestIssue(delivery)" type="button" class="mt-3 rounded-lg border border-border px-3 py-2 text-xs font-semibold text-primary hover:bg-muted" @click="openIssueModal(delivery)">
                                    {{ tx('auto.Problem melden', 'Problem melden') }}
                                </button>
                            </div>
                        </div>
                    </article>
                </div>
                <p v-else class="mt-5 rounded-lg bg-inputBg p-4 text-sm text-secondary">{{ tx('auto.Noch kein Outfit-Abo aktiv.', 'Noch kein Outfit-Abo aktiv.') }}</p>
            </div>
        </section>

        <section id="outfit-plans">
            <div class="mb-4 flex items-end justify-between gap-4">
                <div>
                        <h2 class="text-lg font-bold text-primary">{{ tx('outfit_workspace.choose_plan', 'Pläne wählen') }}</h2>
                    <p class="mt-1 text-sm text-secondary">{{ tx('auto.Sponsor-Subventionen werden direkt vom Monatsbetrag abgezogen.', 'Sponsor-Subventionen werden direkt vom Monatsbetrag abgezogen.') }}</p>
                </div>
            </div>

            <div class="grid gap-4 md:grid-cols-2 xl:grid-cols-3">
                <article v-for="plan in plans" :key="plan.id" class="flex flex-col rounded-lg border border-border bg-card p-5 shadow-sm transition hover:-translate-y-0.5 hover:border-borderHover hover:shadow-lg">
                    <div class="flex items-start justify-between gap-4">
                        <div>
                            <h3 class="text-lg font-bold text-primary">{{ plan.name }}</h3>
                            <div v-if="plan.sponsor" class="mt-2 flex items-center gap-2">
                                <div class="flex h-9 w-9 shrink-0 items-center justify-center overflow-hidden rounded-lg border border-border bg-inputBg text-xs font-black text-primary">
                                    <img v-if="sponsorLogoUrl(plan.sponsor)" :src="sponsorLogoUrl(plan.sponsor)" :alt="plan.sponsor.name" class="h-full w-full object-contain p-1">
                                    <span v-else>{{ initials(plan.sponsor.name) }}</span>
                                </div>
                                <div class="min-w-0">
                                    <p class="text-xs text-secondary">{{ tx('auto.Subventioniert von', 'Subventioniert von') }}</p>
                                    <p class="truncate text-sm font-semibold text-accent">{{ plan.sponsor.name }}</p>
                                </div>
                            </div>
                        </div>
                        <span class="rounded-full bg-inputBg px-3 py-1 text-xs font-semibold text-primary">{{ plan.items_per_box }} {{ tx('auto.Teile', 'Teile') }}</span>
                    </div>
                    <p class="mt-4 min-h-16 text-sm leading-6 text-secondary">{{ plan.description }}</p>
                    <div class="mt-4 rounded-lg bg-inputBg p-4">
                        <p v-if="plan.sponsor_discount_cents" class="text-xs text-secondary">
                            {{ tx('outfit_ui.instead', 'Statt') }} {{ formatMoney(plan.monthly_price_cents, plan.currency) }} - {{ tx('auto.Rabatt', 'Rabatt') }} {{ formatMoney(plan.sponsor_discount_cents, plan.currency) }}
                        </p>
                        <p class="text-2xl font-bold text-primary">{{ formatMoney(plan.effective_monthly_price_cents, plan.currency) }}</p>
                        <p class="text-xs text-secondary">{{ tx('auto.pro Monat', 'pro Monat') }}</p>
                    </div>
                    <div class="mt-4 flex flex-wrap gap-2">
                        <span v-for="sport in plan.sports" :key="sport" class="rounded-full bg-muted px-3 py-1 text-xs text-secondary">{{ sportLabel(sport) }}</span>
                        <span v-if="plan.branding_type !== 'none'" class="rounded-full bg-accent/15 px-3 py-1 text-xs font-semibold text-accent">{{ brandingLabel(plan.branding_type) }}</span>
                    </div>
                    <button type="button" class="mt-5 rounded-lg bg-buttonPrimary px-4 py-2 text-sm font-semibold text-buttonTextPrimary hover:opacity-90" @click="subscribe(plan)">
                        {{ tx('auto.Plan auswählen', 'Plan auswählen') }}
                    </button>
                </article>
            </div>
        </section>

        <div v-if="pendingSubscribePlan" class="fixed inset-0 z-[80] flex items-center justify-center bg-black/60 p-4 backdrop-blur-sm">
            <div class="max-h-[calc(100vh-2rem)] w-full max-w-lg overflow-y-auto rounded-lg border border-border bg-card p-5 shadow-2xl">
                <div class="flex items-start justify-between gap-4">
                    <div>
                        <p class="text-xs font-semibold uppercase tracking-wide text-accent">{{ tx('auto.Outfit-Abo bestätigen', 'Outfit-Abo bestätigen') }}</p>
                        <h2 class="mt-1 text-lg font-bold text-primary">{{ pendingSubscribePlan.name }}</h2>
                        <p class="mt-2 text-sm leading-6 text-secondary">
                            {{ tx('auto.Nach deiner Bestätigung wird das Abo als Zahlung offen vorgemerkt. Es wird erst aktiviert und beliefert, wenn die Zahlung bestätigt ist.', 'Nach deiner Bestätigung wird das Abo als Zahlung offen vorgemerkt. Es wird erst aktiviert und beliefert, wenn die Zahlung bestätigt ist.') }}
                        </p>
                    </div>
                    <button type="button" class="rounded-lg p-2 text-secondary hover:bg-muted hover:text-primary" @click="closeSubscribeModal">
                        <i class="las la-times text-xl"></i>
                    </button>
                </div>

                <div class="mt-5 grid gap-3 rounded-lg border border-border bg-inputBg p-4">
                    <div class="flex items-center justify-between gap-4">
                        <span class="text-sm text-secondary">{{ tx('auto.Monatsbetrag', 'Monatsbetrag') }}</span>
                        <span class="text-lg font-bold text-primary">{{ formatMoney(pendingSubscribePlan.effective_monthly_price_cents, pendingSubscribePlan.currency) }}</span>
                    </div>
                    <div v-if="pendingSubscribePlan.sponsor_discount_cents" class="flex items-center justify-between gap-4">
                        <span class="text-sm text-secondary">{{ tx('auto.Sponsor-Rabatt', 'Sponsor-Rabatt') }}</span>
                        <span class="text-sm font-semibold text-success">- {{ formatMoney(pendingSubscribePlan.sponsor_discount_cents, pendingSubscribePlan.currency) }}</span>
                    </div>
                    <div class="flex items-center justify-between gap-4">
                        <span class="text-sm text-secondary">{{ tx('auto.Status nach Klick', 'Status nach Klick') }}</span>
                        <span class="text-sm font-semibold text-warning">{{ tx('outfit_workspace.status.pending_payment', 'Zahlung offen') }}</span>
                    </div>
                </div>

                <label class="mt-4 block">
                    <span class="text-sm font-semibold text-primary">{{ tx('auto.Zahlungsart', 'Zahlungsart') }}</span>
                    <select v-model="subscribePaymentProvider" class="mt-1 w-full rounded-lg border-border bg-inputBg text-primary">
                        <option value="bank_transfer">{{ tx('outfit_workspace.payment.bank_transfer', 'Überweisung') }}</option>
                        <option value="paypal">{{ tx('outfit_workspace.payment.paypal', 'PayPal') }}</option>
                    </select>
                    <span class="mt-1 block text-xs text-secondary">
                        {{ tx('auto.Die Zahlung wird danach vorbereitet. Das Abo bleibt bis zur Zahlungsbestätigung offen.', 'Die Zahlung wird danach vorbereitet. Das Abo bleibt bis zur Zahlungsbestätigung offen.') }}
                    </span>
                </label>

                <div class="mt-4 rounded-lg border border-border bg-inputBg p-4">
                    <p class="text-sm font-bold text-primary">{{ tx('auto.Lieferadresse', 'Lieferadresse') }}</p>
                    <div class="mt-3 grid gap-3 sm:grid-cols-2">
                        <label class="block sm:col-span-2">
                            <span class="text-xs font-semibold uppercase text-secondary">{{ tx('outfit_ui.name', 'Name') }}</span>
                            <input v-model="shippingName" class="mt-1 w-full rounded-lg border-border bg-card text-sm text-primary" :placeholder="tx('auto.Vor- und Nachname', 'Vor- und Nachname')">
                        </label>
                        <label class="block sm:col-span-2">
                            <span class="text-xs font-semibold uppercase text-secondary">{{ tx('auto.Straße', 'Straße') }}</span>
                            <input v-model="shippingStreet" class="mt-1 w-full rounded-lg border-border bg-card text-sm text-primary" :placeholder="tx('auto.Straße', 'Straße')">
                        </label>
                        <label class="block">
                            <span class="text-xs font-semibold uppercase text-secondary">{{ tx('auto.Hausnummer', 'Hausnummer') }}</span>
                            <input v-model="shippingHouseNumber" class="mt-1 w-full rounded-lg border-border bg-card text-sm text-primary" placeholder="12a">
                        </label>
                        <label class="block">
                            <span class="text-xs font-semibold uppercase text-secondary">{{ tx('auto.Land', 'Land') }}</span>
                            <input v-model="shippingCountry" maxlength="2" class="mt-1 w-full rounded-lg border-border bg-card text-sm uppercase text-primary" placeholder="DE">
                        </label>
                        <label class="block">
                            <span class="text-xs font-semibold uppercase text-secondary">{{ tx('auto.PLZ', 'PLZ') }}</span>
                            <input v-model="shippingPostalCode" class="mt-1 w-full rounded-lg border-border bg-card text-sm text-primary" placeholder="12345">
                        </label>
                        <label class="block">
                            <span class="text-xs font-semibold uppercase text-secondary">{{ tx('auto.Stadt', 'Stadt') }}</span>
                            <input v-model="shippingCity" class="mt-1 w-full rounded-lg border-border bg-card text-sm text-primary" placeholder="Berlin">
                        </label>
                        <label class="block sm:col-span-2">
                            <span class="text-xs font-semibold uppercase text-secondary">{{ tx('auto.Bundesland / Region', 'Bundesland / Region') }}</span>
                            <input v-model="shippingState" class="mt-1 w-full rounded-lg border-border bg-card text-sm text-primary" :placeholder="tx('auto.Optional', 'Optional')">
                        </label>
                        <label class="block sm:col-span-2">
                            <span class="text-xs font-semibold uppercase text-secondary">{{ tx('auto.Lieferhinweis', 'Lieferhinweis') }}</span>
                            <textarea v-model="shippingNote" rows="2" class="mt-1 w-full rounded-lg border-border bg-card text-sm text-primary" :placeholder="tx('auto.Optional, z.B. bei Nachbarn abgeben', 'Optional, z.B. bei Nachbarn abgeben')"></textarea>
                        </label>
                    </div>
                </div>

                <div class="mt-4 rounded-lg border border-border bg-inputBg p-4">
                    <div class="flex items-start justify-between gap-4">
                        <div>
                            <p class="text-sm font-bold text-primary">{{ tx('auto.Outfit-Abo-Vertrag', 'Outfit-Abo-Vertrag') }}</p>
                            <p class="mt-1 text-xs leading-5 text-secondary">
                                {{ tx('auto.Diese Bedingungen gelten für diesen Abschluss und werden mit deiner Anfrage gespeichert.', 'Diese Bedingungen gelten für diesen Abschluss und werden mit deiner Anfrage gespeichert.') }}
                            </p>
                        </div>
                        <span class="rounded-full bg-muted px-3 py-1 text-xs font-semibold text-secondary">
                            Version {{ planContractRules(pendingSubscribePlan).version || tx('outfit_ui.current', 'aktuell') }}
                        </span>
                    </div>
                    <dl class="mt-4 grid gap-3 text-sm">
                        <div class="flex items-center justify-between gap-4">
                            <dt class="text-secondary">{{ tx('auto.Mindestlaufzeit', 'Mindestlaufzeit') }}</dt>
                            <dd class="font-semibold text-primary">{{ planContractRules(pendingSubscribePlan).minimum_term_months }} {{ tx('auto.Monate', 'Monate') }}</dd>
                        </div>
                        <div class="flex items-center justify-between gap-4">
                            <dt class="text-secondary">{{ tx('auto.Pause möglich ab', 'Pause möglich ab') }}</dt>
                            <dd class="font-semibold text-primary">{{ tx('auto.Monat', 'Monat') }} {{ planContractRules(pendingSubscribePlan).pause_allowed_after_months }}</dd>
                        </div>
                        <div class="flex items-center justify-between gap-4">
                            <dt class="text-secondary">{{ tx('auto.Kündigungsfrist', 'Kündigungsfrist') }}</dt>
                            <dd class="font-semibold text-primary">{{ planContractRules(pendingSubscribePlan).cancellation_notice_days }} {{ tx('auto.Tage', 'Tage') }}</dd>
                        </div>
                    </dl>
                    <ul class="mt-4 space-y-2 text-xs leading-5 text-secondary">
                        <li v-for="term in planContractTerms(pendingSubscribePlan)" :key="term">- {{ term }}</li>
                    </ul>
                </div>

                <label class="mt-4 flex items-start gap-3 rounded-lg border border-border bg-card p-3 text-sm text-secondary">
                    <input v-model="subscribeAcceptedContract" type="checkbox" class="mt-1 rounded border-border bg-inputBg">
                    <span>
                        {{ tx('auto.Ich akzeptiere den Outfit-Abo-Vertrag inkl. Mindestlaufzeit, Pausen-, Liefer- und Kündigungsregeln.', 'Ich akzeptiere den Outfit-Abo-Vertrag inkl. Mindestlaufzeit, Pausen-, Liefer- und Kündigungsregeln.') }}
                    </span>
                </label>

                <label class="mt-4 flex items-start gap-3 rounded-lg border border-border bg-card p-3 text-sm text-secondary">
                    <input v-model="subscribeAcceptedTerms" type="checkbox" class="mt-1 rounded border-border bg-inputBg">
                    <span>
                        Ich akzeptiere
                        <a
                            :href="route('terms.show')"
                            target="_blank"
                            rel="noopener noreferrer"
                            class="font-semibold text-accent underline underline-offset-2"
                            @click.stop
                        >
                            {{ tx('outfit_ui.terms', 'AGB') }}
                        </a>
                        und
                        <a
                            :href="route('legal.withdrawal')"
                            target="_blank"
                            rel="noopener noreferrer"
                            class="font-semibold text-accent underline underline-offset-2"
                            @click.stop
                        >
                            {{ tx('outfit_ui.withdrawal', 'Widerrufshinweise') }}
                        </a>
                        {{ tx('auto.und weiß, dass das Abo erst nach Zahlungsbestätigung aktiv wird.', 'und weiß, dass das Abo erst nach Zahlungsbestätigung aktiv wird.') }}
                    </span>
                </label>

                <div class="mt-5 flex flex-col-reverse gap-2 sm:flex-row sm:justify-end">
                    <button type="button" class="rounded-lg border border-border px-4 py-2 text-sm font-semibold text-primary hover:bg-muted" @click="closeSubscribeModal">
                        {{ tx('auto.Abbrechen', 'Abbrechen') }}
                    </button>
                    <button
                        type="button"
                        class="rounded-lg bg-buttonPrimary px-4 py-2 text-sm font-semibold text-buttonTextPrimary hover:opacity-90 disabled:opacity-50"
                        :disabled="!subscribeAcceptedTerms || !subscribeAcceptedContract || !hasShippingAddress || subscribingPlanId === pendingSubscribePlan.id"
                        @click="confirmSubscribe"
                    >
                        {{ subscribingPlanId === pendingSubscribePlan.id ? tx('outfit_ui.sending', 'Wird gesendet...') : tx('outfit_ui.request_paid', 'Kostenpflichtig anfragen') }}
                    </button>
                </div>
            </div>
        </div>

        <div v-if="pendingCancelSubscription" class="fixed inset-0 z-[80] flex items-center justify-center bg-black/60 p-4 backdrop-blur-sm">
            <div class="w-full max-w-md rounded-lg border border-border bg-card p-5 shadow-2xl">
                <div class="flex items-start justify-between gap-4">
                    <div>
                        <h2 class="text-lg font-bold text-primary">
                            {{ isPendingPayment(pendingCancelSubscription) ? tx('outfit_ui.cancel_request_title', 'Outfit-Abo-Anfrage abbrechen?') : tx('outfit_ui.cancel_title', 'Outfit-Abo kündigen?') }}
                        </h2>
                        <p class="mt-2 text-sm leading-6 text-secondary">
                            {{ isPendingPayment(pendingCancelSubscription)
                                ? tx('outfit_ui.cancel_request_text', 'Der Kaufvertrag ist noch nicht abgeschlossen. Die offene Anfrage wird abgebrochen und es wird keine Zahlung mehr erwartet.')
                                : tx('outfit_ui.cancel_text', 'Das Abo wird beendet. Bereits geplante interne Bearbeitungsschritte werden danach nicht weitergeführt.') }}
                        </p>
                    </div>
                    <button type="button" class="rounded-lg p-2 text-secondary hover:bg-muted hover:text-primary" @click="closeCancelModal">
                        <i class="las la-times text-xl"></i>
                    </button>
                </div>

                <div class="mt-5 rounded-lg bg-inputBg p-4">
                    <p class="text-sm font-semibold text-primary">{{ pendingCancelSubscription.plan?.name || tx('outfit_ui.subscription', 'Outfit-Abo') }}</p>
                    <p class="mt-1 text-sm text-secondary">
                        {{ formatMoney(pendingCancelSubscription.monthly_price_cents, pendingCancelSubscription.currency) }} / {{ tx('auto.Monat', 'Monat') }}
                    </p>
                </div>

                <div class="mt-5 flex flex-col-reverse gap-2 sm:flex-row sm:justify-end">
                    <button type="button" class="rounded-lg border border-border px-4 py-2 text-sm font-semibold text-primary hover:bg-muted" @click="closeCancelModal">
                        {{ tx('auto.Abbrechen', 'Abbrechen') }}
                    </button>
                    <button type="button" class="rounded-lg bg-error px-4 py-2 text-sm font-semibold text-buttonTextPrimary hover:opacity-90" @click="confirmCancel">
                        {{ isPendingPayment(pendingCancelSubscription) ? tx('auto.Anfrage abbrechen', 'Anfrage abbrechen') : tx('auto.Kündigen', 'Kündigen') }}
                    </button>
                </div>
            </div>
        </div>

        <div v-if="pendingIssueDelivery" class="fixed inset-0 z-[80] flex items-center justify-center bg-black/60 p-4 backdrop-blur-sm">
            <div class="w-full max-w-lg rounded-lg border border-border bg-card p-5 shadow-2xl">
                <div class="flex items-start justify-between gap-4">
                    <div>
                        <p class="text-xs font-semibold uppercase tracking-wide text-accent">{{ tx('auto.Lieferproblem melden', 'Lieferproblem melden') }}</p>
                        <h2 class="mt-1 text-lg font-bold text-primary">{{ statusLabel(pendingIssueDelivery.status) }} {{ tx('auto.vom', 'vom') }} {{ formatDate(pendingIssueDelivery.delivery_month) }}</h2>
                        <p class="mt-2 text-sm leading-6 text-secondary">
                            {{ tx('auto.Beschreibe kurz, was nicht passt. Das Support-Team sieht Lieferung, Tracking und dein Style-Profil direkt dazu.', 'Beschreibe kurz, was nicht passt. Das Support-Team sieht Lieferung, Tracking und dein Style-Profil direkt dazu.') }}
                        </p>
                    </div>
                    <button type="button" class="rounded-lg p-2 text-secondary hover:bg-muted hover:text-primary" @click="closeIssueModal">
                        <i class="las la-times text-xl"></i>
                    </button>
                </div>

                <div class="mt-5 grid gap-4">
                    <label class="block">
                        <span class="text-sm font-semibold text-primary">{{ tx('auto.Art des Problems', 'Art des Problems') }}</span>
                        <select v-model="issueForm.issue_type" class="mt-1 w-full rounded-lg border-border bg-inputBg text-primary">
                            <option value="exchange">{{ tx('auto.Umtausch / andere Grüße', 'Umtausch / andere Grüße') }}</option>
                            <option value="return">{{ tx('auto.Retoure', 'Retoure') }}</option>
                            <option value="damaged">{{ tx('auto.Beschädigt', 'Beschädigt') }}</option>
                            <option value="missing_item">{{ tx('auto.Artikel fehlt', 'Artikel fehlt') }}</option>
                            <option value="wrong_item">{{ tx('auto.Falscher Artikel', 'Falscher Artikel') }}</option>
                            <option value="other">{{ tx('auto.Sonstiges', 'Sonstiges') }}</option>
                        </select>
                    </label>

                    <label class="block">
                        <span class="text-sm font-semibold text-primary">{{ tx('outfit_ui.description', 'Beschreibung') }}</span>
                        <textarea v-model="issueForm.issue_description" rows="4" class="mt-1 w-full rounded-lg border-border bg-inputBg text-primary" :placeholder="tx('auto.Was ist passiert? Welche Artikel sind betroffen?', 'Was ist passiert? Welche Artikel sind betroffen?')"></textarea>
                        <span v-if="issueForm.errors.issue_description" class="mt-1 block text-xs text-error">{{ issueForm.errors.issue_description }}</span>
                    </label>

                    <label class="block">
                        <span class="text-sm font-semibold text-primary">{{ tx('auto.Wunschlösung', 'Wunschlösung') }}</span>
                        <input v-model="issueForm.issue_requested_resolution" class="mt-1 w-full rounded-lg border-border bg-inputBg text-primary" :placeholder="tx('auto.z.B. Ersatz, Retoure, Gutschrift', 'z.B. Ersatz, Retoure, Gutschrift')">
                    </label>

                    <label class="block">
                        <span class="text-sm font-semibold text-primary">{{ tx('auto.Gewünschte Größe', 'Gewünschte Größe') }}</span>
                        <input v-model="issueForm.issue_exchange_size" class="mt-1 w-full rounded-lg border-border bg-inputBg text-primary" :placeholder="tx('auto.Optional, z.B. M statt L', 'Optional, z.B. M statt L')">
                    </label>
                </div>

                <div class="mt-5 flex flex-col-reverse gap-2 sm:flex-row sm:justify-end">
                    <button type="button" class="rounded-lg border border-border px-4 py-2 text-sm font-semibold text-primary hover:bg-muted" @click="closeIssueModal">
                        {{ tx('auto.Abbrechen', 'Abbrechen') }}
                    </button>
                    <button type="button" class="rounded-lg bg-buttonPrimary px-4 py-2 text-sm font-semibold text-buttonTextPrimary hover:opacity-90 disabled:opacity-50" :disabled="issueForm.processing || !issueForm.issue_description" @click="submitIssue">
                        {{ tx('auto.Meldung senden', 'Meldung senden') }}
                    </button>
                </div>
            </div>
        </div>
    </div>
</template>
