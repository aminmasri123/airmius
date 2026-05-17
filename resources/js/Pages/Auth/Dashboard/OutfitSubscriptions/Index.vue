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

const { t, te } = useI18n()
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

const formatMoney = (cents, currency = 'EUR') => new Intl.NumberFormat('de-DE', {
    style: 'currency',
    currency,
}).format(Number(cents || 0) / 100)

const formatDate = (value) => {
    if (!value) return 'Noch nicht geplant'

    return new Intl.DateTimeFormat('de-DE', {
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
    active: 'Aktiv',
    paused: 'Pausiert',
    payment_paused: 'Wegen Zahlung pausiert',
    cancels_at_period_end: 'Gekuendigt zum Laufzeitende',
    pending_payment: 'Zahlung offen',
    pending_confirmation: 'Wartet auf Freigabe',
    cancelled: 'Gekuendigt',
    planned: 'Geplant',
    preparing: 'In Vorbereitung',
    shipped: 'Versendet',
    delivered: 'Geliefert',
    skipped: 'Ausgesetzt',
}[status] || status)

const issueTypeLabel = (type) => ({
    exchange: 'Umtausch',
    return: 'Retoure',
    damaged: 'Beschaedigt',
    missing_item: 'Artikel fehlt',
    wrong_item: 'Falscher Artikel',
    other: 'Sonstiges',
}[type] || type || '-')

const issueStatusLabel = (status) => ({
    open: 'Offen',
    reviewing: 'In Pruefung',
    approved: 'Freigegeben',
    return_waiting: 'Ruecksendung offen',
    replacement_preparing: 'Ersatz wird vorbereitet',
    resolved: 'Geloest',
    rejected: 'Abgeschlossen',
}[status] || status || '-')

const paymentProviderLabel = (provider) => ({
    bank_transfer: 'Überweisung',
    paypal: 'PayPal',
}[provider] || provider || '-')

const shippingAddressLine = (address) => [
    [address?.street, address?.house_number].filter(Boolean).join(' '),
    [address?.postal_code, address?.city].filter(Boolean).join(' '),
    [address?.state, address?.country].filter(Boolean).join(', '),
].filter(Boolean).join(', ')

const isPendingPayment = (subscription) => subscription?.status === 'pending_payment'

const cancelActionLabel = (subscription) => isPendingPayment(subscription) ? 'Abbrechen' : 'Kuendigen'

const planContractRules = (plan) => plan?.contract_rules || props.contractRules

const planContractTerms = (plan) => (plan?.contract_terms?.length ? plan.contract_terms : [
    'Das Outfit-Abo ist ein monatliches Abonnement mit wiederkehrender Zahlung.',
    'Die erste Lieferung wird erst nach bestaetigter Zahlung vorbereitet.',
    'Pause und Kuendigung gelten nur für zukuenftige Lieferungen.',
])

const brandingLabel = (type) => ({
    sponsor_logo: 'Sponsor-Branding',
    club_logo: 'Vereins-Branding',
    custom: 'Individuelles Branding',
}[type] || 'Branding')

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
                message: 'Style-Profil wurde gespeichert.',
            }
        },
        onError: () => {
            profileFeedback.value = {
                type: 'error',
                message: 'Style-Profil konnte nicht gespeichert werden. Bitte prüfe deine Angaben.',
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
    <Head title="Outfit-Abo" />

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
                        <p class="text-sm font-semibold uppercase tracking-wide text-white/80">Sportkleidung monatlich</p>
                        <h1 class="mt-3 max-w-2xl text-3xl font-black leading-tight text-white sm:text-5xl">
                            Outfit-Abo für deinen Style
                        </h1>
                        <p class="mt-4 max-w-2xl text-sm leading-6 text-white/85 sm:text-base">
                            Wähle einen Plan, pflege dein Style-Profil und erhalte regelmäßig Sport-Outfits passend zu Sportart, Größe, Farben und Markenstil.
                        </p>

                        <div class="mt-6 flex flex-wrap gap-3">
                            <button type="button" class="rounded-lg bg-white px-4 py-2 text-sm font-bold text-slate-950 hover:bg-white/90" @click="scrollToPlans">
                                Plan wählen
                            </button>
                            <a href="#style-profile" class="rounded-lg border border-white/35 bg-white/10 px-4 py-2 text-sm font-bold text-white backdrop-blur hover:bg-white/20">
                                Style-Profil pflegen
                            </a>
                        </div>
                    </div>
                </div>

                <div class="grid content-between gap-4 border-t border-border bg-card p-5 lg:border-l lg:border-t-0 lg:p-6">
                    <div>
                        <p class="text-xs font-semibold uppercase tracking-wide text-accent">Abo-Zentrale</p>
                        <h2 class="mt-2 text-xl font-bold text-primary">Alles auf einen Blick</h2>
                        <p class="mt-2 text-sm leading-6 text-secondary">
                            Aktive Abos, naechste Lieferung und Style-Daten bleiben hier schnell erreichbar.
                        </p>
                    </div>

                    <div class="grid gap-3">
                        <div class="rounded-lg border border-border bg-inputBg p-4">
                            <p class="text-xs uppercase text-secondary">Aktive Abos</p>
                            <p class="mt-2 text-3xl font-black text-primary">{{ activeSubscriptions.length }}</p>
                        </div>
                        <div class="rounded-lg border border-border bg-inputBg p-4">
                            <p class="text-xs uppercase text-secondary">Naechste Lieferung</p>
                            <p class="mt-2 text-lg font-bold text-primary">{{ formatDate(nextDelivery) }}</p>
                        </div>
                        <div class="rounded-lg border border-border bg-inputBg p-4">
                            <p class="text-xs uppercase text-secondary">Style-Profil</p>
                            <p class="mt-2 text-lg font-bold" :class="hasStyleProfile ? 'text-success' : 'text-warning'">
                                {{ hasStyleProfile ? 'Bereit' : 'Noch offen' }}
                            </p>
                        </div>
                    </div>

                    <div class="rounded-lg border border-accent/30 bg-accent/10 p-4">
                        <p class="text-sm font-semibold text-primary">Tipp</p>
                        <p class="mt-1 text-sm text-secondary">
                            Je genauer Grössen, Farben und No-Gos sind, desto besser passt die monatliche Box.
                        </p>
                    </div>
                </div>
            </div>
        </section>

        <section class="grid gap-6 xl:grid-cols-[.85fr_1.15fr]">
            <form id="style-profile" class="rounded-lg border border-border bg-card p-5 shadow-sm" @submit.prevent="updateProfile">
                <div class="flex items-start justify-between gap-4">
                    <div>
                        <h2 class="text-lg font-bold text-primary">Style-Profil</h2>
                        <p class="mt-1 text-sm text-secondary">Diese Angaben steuern die Zusammenstellung deiner Boxen.</p>
                    </div>
                    <button class="rounded-lg bg-buttonPrimary px-4 py-2 text-sm font-semibold text-buttonTextPrimary disabled:opacity-50" :disabled="profileForm.processing">
                        Speichern
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
                        <span class="text-sm font-semibold text-primary">Sportfokus</span>
                        <SearchableSelect
                            v-model="profileForm.sport_focus"
                            :options="sports"
                            value-key="slug"
                            translation-prefix="sports"
                            category-translation-prefix="sport_categories"
                            class="mt-1"
                            placeholder="Sportart suchen"
                        />
                    </label>
                    <label class="block">
                        <span class="text-sm font-semibold text-primary">Passform</span>
                        <select v-model="profileForm.fit_preference" class="mt-1 w-full rounded-lg border-border bg-inputBg text-primary">
                            <option value="slim">Slim</option>
                            <option value="regular">Regular</option>
                            <option value="relaxed">Locker</option>
                        </select>
                    </label>
                    <label class="block">
                        <span class="text-sm font-semibold text-primary">Grössen</span>
                        <input v-model="profileForm.sizes_text" class="mt-1 w-full rounded-lg border-border bg-inputBg text-primary" placeholder="M, L, 42" />
                    </label>
                    <label class="block">
                        <span class="text-sm font-semibold text-primary">Lieblingsfarben</span>
                        <input v-model="profileForm.colors_text" class="mt-1 w-full rounded-lg border-border bg-inputBg text-primary" placeholder="Schwarz, Blau, Weiß" />
                    </label>
                    <label class="block">
                        <span class="text-sm font-semibold text-primary">Ausschlussfarben</span>
                        <input v-model="profileForm.excluded_colors_text" class="mt-1 w-full rounded-lg border-border bg-inputBg text-primary" placeholder="Gelb, Pink" />
                    </label>
                    <label class="block">
                        <span class="text-sm font-semibold text-primary">Stil</span>
                        <select v-model="profileForm.brand_style" class="mt-1 w-full rounded-lg border-border bg-inputBg text-primary">
                            <option value="minimal">Minimal</option>
                            <option value="bold">Auffällig</option>
                            <option value="classic">Klassisch</option>
                            <option value="team">Team-orientiert</option>
                        </select>
                    </label>
                    <label class="block sm:col-span-2">
                        <span class="text-sm font-semibold text-primary">Notizen</span>
                        <textarea v-model="profileForm.notes" rows="3" class="mt-1 w-full rounded-lg border-border bg-inputBg text-primary" placeholder="Materialwuensche, Marken, No-Gos, besondere Hinweise"></textarea>
                    </label>
                </div>
            </form>

            <div class="rounded-lg border border-border bg-card p-5 shadow-sm">
                <div class="flex items-start justify-between gap-4">
                    <div>
                        <h2 class="text-lg font-bold text-primary">Deine Abos und Lieferungen</h2>
                        <p class="mt-1 text-sm text-secondary">Status, Liefermonat und Tracking an einem Ort.</p>
                    </div>
                    <span class="rounded-full bg-inputBg px-3 py-1 text-xs font-semibold text-secondary">{{ subscriptions.length }} Einträge</span>
                </div>

                <div v-if="subscriptions.length" class="mt-5 space-y-4">
                    <article v-for="subscription in subscriptions" :key="subscription.id" class="rounded-lg border border-border bg-inputBg p-4">
                        <div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
                            <div>
                                <p class="text-base font-bold text-primary">{{ subscription.plan?.name || 'Outfit-Abo' }}</p>
                                <p class="mt-1 text-sm text-secondary">
                                    {{ statusLabel(subscription.status) }} - Naechste Lieferung: {{ formatDate(subscription.next_delivery_at) }}
                                </p>
                                <p class="mt-1 text-sm text-secondary">
                                    Zahlungsart: {{ paymentProviderLabel(subscription.payment_provider) }}
                                </p>
                                <p v-if="subscription.dunning_level" class="mt-1 text-sm text-amber-200">
                                    Mahnstufe {{ subscription.dunning_level }}/3
                                    <span v-if="subscription.last_dunning_sent_at"> - letzte Mahnung: {{ formatDate(subscription.last_dunning_sent_at) }}</span>
                                </p>
                                <p v-if="subscription.status === 'payment_paused'" class="mt-1 text-sm text-amber-200">
                                    Dieses Abo ist bis zum Zahlungseingang pausiert. Es werden keine weiteren Lieferungen vorbereitet.
                                </p>
                                <p class="mt-1 text-sm font-semibold text-primary">{{ formatMoney(subscription.monthly_price_cents, subscription.currency) }} / Monat</p>
                            </div>
                            <div class="flex flex-wrap gap-2">
                                <button v-if="subscription.status === 'active'" type="button" class="rounded-lg border border-border px-3 py-2 text-sm text-primary hover:bg-card" @click="pause(subscription)">Pausieren</button>
                                <button v-if="subscription.status === 'paused'" type="button" class="rounded-lg border border-border px-3 py-2 text-sm text-primary hover:bg-card" @click="resume(subscription)">Fortsetzen</button>
                                <button v-if="!['cancelled', 'cancels_at_period_end'].includes(subscription.status)" type="button" class="rounded-lg border border-red-500/50 px-3 py-2 text-sm text-red-300 hover:bg-red-500/10" @click="requestCancel(subscription)">
                                    {{ cancelActionLabel(subscription) }}
                                </button>
                            </div>
                            <p v-if="subscription.status === 'cancels_at_period_end'" class="mt-2 text-sm text-amber-200">
                                Gekuendigt zum {{ formatDate(subscription.current_period_ends_at) }}. Bis dahin bleibt das Abo aktiv.
                            </p>
                        </div>

                        <div
                            v-if="isPendingPayment(subscription) && subscription.payment_provider === 'bank_transfer'"
                            class="mt-4 rounded-lg border border-warning/30 bg-warning/10 p-4"
                        >
                            <div class="flex flex-col gap-2 sm:flex-row sm:items-start sm:justify-between">
                                <div>
                                    <p class="text-sm font-bold text-primary">Ueberweisungsdaten</p>
                                    <p class="mt-1 text-xs leading-5 text-secondary">
                                        Bitte nutze exakt diesen Verwendungszweck, damit deine Zahlung zugeordnet werden kann.
                                    </p>
                                </div>
                                <span class="rounded-full bg-card px-3 py-1 text-xs font-semibold text-warning">Zahlung offen</span>
                            </div>

                            <dl class="mt-4 grid gap-3 text-sm sm:grid-cols-2">
                                <div class="rounded-lg bg-card p-3">
                                    <dt class="text-xs uppercase text-secondary">Betrag</dt>
                                    <dd class="mt-1 font-bold text-primary">{{ formatMoney(subscription.monthly_price_cents, subscription.currency) }}</dd>
                                </div>
                                <div class="rounded-lg bg-card p-3">
                                    <dt class="text-xs uppercase text-secondary">Faellig bis</dt>
                                    <dd class="mt-1 font-bold text-primary">{{ formatDate(subscription.payment_due_at) }}</dd>
                                </div>
                                <div class="rounded-lg bg-card p-3 sm:col-span-2">
                                    <dt class="text-xs uppercase text-secondary">Verwendungszweck / Zahlungsreferenz</dt>
                                    <dd class="mt-1 break-all font-bold text-primary">{{ subscription.payment_reference }}</dd>
                                </div>
                                <div class="rounded-lg bg-card p-3">
                                    <dt class="text-xs uppercase text-secondary">Kontoinhaber</dt>
                                    <dd class="mt-1 font-bold text-primary">{{ subscription.bank_transfer?.bank_account_holder || '-' }}</dd>
                                </div>
                                <div class="rounded-lg bg-card p-3">
                                    <dt class="text-xs uppercase text-secondary">Bank</dt>
                                    <dd class="mt-1 font-bold text-primary">{{ subscription.bank_transfer?.bank_name || '-' }}</dd>
                                </div>
                                <div class="rounded-lg bg-card p-3">
                                    <dt class="text-xs uppercase text-secondary">IBAN</dt>
                                    <dd class="mt-1 break-all font-bold text-primary">{{ subscription.bank_transfer?.iban || '-' }}</dd>
                                </div>
                                <div class="rounded-lg bg-card p-3">
                                    <dt class="text-xs uppercase text-secondary">BIC</dt>
                                    <dd class="mt-1 font-bold text-primary">{{ subscription.bank_transfer?.bic || '-' }}</dd>
                                </div>
                            </dl>
                        </div>

                        <div v-if="subscription.shipping_address" class="mt-4 rounded-lg bg-card p-3">
                            <p class="text-xs uppercase text-secondary">Lieferadresse</p>
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
                                    Tracking öffnen
                                </a>
                                <p v-if="delivery.notes" class="mt-1 text-xs text-secondary">{{ delivery.notes }}</p>
                                <div v-if="delivery.issue_status" class="mt-3 rounded-lg border border-border bg-inputBg p-3">
                                    <p class="text-xs font-semibold uppercase text-secondary">{{ issueTypeLabel(delivery.issue_type) }}</p>
                                    <p class="mt-1 text-sm font-semibold text-primary">{{ issueStatusLabel(delivery.issue_status) }}</p>
                                    <p v-if="delivery.issue_admin_note" class="mt-1 text-xs text-secondary">{{ delivery.issue_admin_note }}</p>
                                    <a v-if="delivery.return_tracking_url" :href="delivery.return_tracking_url" target="_blank" rel="noopener noreferrer" class="mt-1 inline-flex text-xs font-semibold text-accent underline underline-offset-2">
                                        Retouren-Tracking öffnen
                                    </a>
                                </div>
                                <button v-if="canRequestIssue(delivery)" type="button" class="mt-3 rounded-lg border border-border px-3 py-2 text-xs font-semibold text-primary hover:bg-muted" @click="openIssueModal(delivery)">
                                    Problem melden
                                </button>
                            </div>
                        </div>
                    </article>
                </div>
                <p v-else class="mt-5 rounded-lg bg-inputBg p-4 text-sm text-secondary">Noch kein Outfit-Abo aktiv.</p>
            </div>
        </section>

        <section id="outfit-plans">
            <div class="mb-4 flex items-end justify-between gap-4">
                <div>
                    <h2 class="text-lg font-bold text-primary">Pläne wählen</h2>
                    <p class="mt-1 text-sm text-secondary">Sponsor-Subventionen werden direkt vom Monatsbetrag abgezogen.</p>
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
                                    <p class="text-xs text-secondary">Subventioniert von</p>
                                    <p class="truncate text-sm font-semibold text-accent">{{ plan.sponsor.name }}</p>
                                </div>
                            </div>
                        </div>
                        <span class="rounded-full bg-inputBg px-3 py-1 text-xs font-semibold text-primary">{{ plan.items_per_box }} Teile</span>
                    </div>
                    <p class="mt-4 min-h-16 text-sm leading-6 text-secondary">{{ plan.description }}</p>
                    <div class="mt-4 rounded-lg bg-inputBg p-4">
                        <p v-if="plan.sponsor_discount_cents" class="text-xs text-secondary">
                            Statt {{ formatMoney(plan.monthly_price_cents, plan.currency) }} - Rabatt {{ formatMoney(plan.sponsor_discount_cents, plan.currency) }}
                        </p>
                        <p class="text-2xl font-bold text-primary">{{ formatMoney(plan.effective_monthly_price_cents, plan.currency) }}</p>
                        <p class="text-xs text-secondary">pro Monat</p>
                    </div>
                    <div class="mt-4 flex flex-wrap gap-2">
                        <span v-for="sport in plan.sports" :key="sport" class="rounded-full bg-muted px-3 py-1 text-xs text-secondary">{{ sportLabel(sport) }}</span>
                        <span v-if="plan.branding_type !== 'none'" class="rounded-full bg-accent/15 px-3 py-1 text-xs font-semibold text-accent">{{ brandingLabel(plan.branding_type) }}</span>
                    </div>
                    <button type="button" class="mt-5 rounded-lg bg-buttonPrimary px-4 py-2 text-sm font-semibold text-buttonTextPrimary hover:opacity-90" @click="subscribe(plan)">
                        Plan auswaehlen
                    </button>
                </article>
            </div>
        </section>

        <div v-if="pendingSubscribePlan" class="fixed inset-0 z-[80] flex items-center justify-center bg-black/60 p-4 backdrop-blur-sm">
            <div class="max-h-[calc(100vh-2rem)] w-full max-w-lg overflow-y-auto rounded-lg border border-border bg-card p-5 shadow-2xl">
                <div class="flex items-start justify-between gap-4">
                    <div>
                        <p class="text-xs font-semibold uppercase tracking-wide text-accent">Outfit-Abo bestaetigen</p>
                        <h2 class="mt-1 text-lg font-bold text-primary">{{ pendingSubscribePlan.name }}</h2>
                        <p class="mt-2 text-sm leading-6 text-secondary">
                            Nach deiner Bestätigung wird das Abo als Zahlung offen vorgemerkt. Es wird erst aktiviert und beliefert, wenn die Zahlung bestätigt ist.
                        </p>
                    </div>
                    <button type="button" class="rounded-lg p-2 text-secondary hover:bg-muted hover:text-primary" @click="closeSubscribeModal">
                        <i class="las la-times text-xl"></i>
                    </button>
                </div>

                <div class="mt-5 grid gap-3 rounded-lg border border-border bg-inputBg p-4">
                    <div class="flex items-center justify-between gap-4">
                        <span class="text-sm text-secondary">Monatsbetrag</span>
                        <span class="text-lg font-bold text-primary">{{ formatMoney(pendingSubscribePlan.effective_monthly_price_cents, pendingSubscribePlan.currency) }}</span>
                    </div>
                    <div v-if="pendingSubscribePlan.sponsor_discount_cents" class="flex items-center justify-between gap-4">
                        <span class="text-sm text-secondary">Sponsor-Rabatt</span>
                        <span class="text-sm font-semibold text-success">- {{ formatMoney(pendingSubscribePlan.sponsor_discount_cents, pendingSubscribePlan.currency) }}</span>
                    </div>
                    <div class="flex items-center justify-between gap-4">
                        <span class="text-sm text-secondary">Status nach Klick</span>
                        <span class="text-sm font-semibold text-warning">Zahlung offen</span>
                    </div>
                </div>

                <label class="mt-4 block">
                    <span class="text-sm font-semibold text-primary">Zahlungsart</span>
                    <select v-model="subscribePaymentProvider" class="mt-1 w-full rounded-lg border-border bg-inputBg text-primary">
                        <option value="bank_transfer">Überweisung</option>
                        <option value="paypal">PayPal</option>
                    </select>
                    <span class="mt-1 block text-xs text-secondary">
                        Die Zahlung wird danach vorbereitet. Das Abo bleibt bis zur Zahlungsbestaetigung offen.
                    </span>
                </label>

                <div class="mt-4 rounded-lg border border-border bg-inputBg p-4">
                    <p class="text-sm font-bold text-primary">Lieferadresse</p>
                    <div class="mt-3 grid gap-3 sm:grid-cols-2">
                        <label class="block sm:col-span-2">
                            <span class="text-xs font-semibold uppercase text-secondary">Name</span>
                            <input v-model="shippingName" class="mt-1 w-full rounded-lg border-border bg-card text-sm text-primary" placeholder="Vor- und Nachname">
                        </label>
                        <label class="block sm:col-span-2">
                            <span class="text-xs font-semibold uppercase text-secondary">Strasse</span>
                            <input v-model="shippingStreet" class="mt-1 w-full rounded-lg border-border bg-card text-sm text-primary" placeholder="Strasse">
                        </label>
                        <label class="block">
                            <span class="text-xs font-semibold uppercase text-secondary">Hausnummer</span>
                            <input v-model="shippingHouseNumber" class="mt-1 w-full rounded-lg border-border bg-card text-sm text-primary" placeholder="12a">
                        </label>
                        <label class="block">
                            <span class="text-xs font-semibold uppercase text-secondary">Land</span>
                            <input v-model="shippingCountry" maxlength="2" class="mt-1 w-full rounded-lg border-border bg-card text-sm uppercase text-primary" placeholder="DE">
                        </label>
                        <label class="block">
                            <span class="text-xs font-semibold uppercase text-secondary">PLZ</span>
                            <input v-model="shippingPostalCode" class="mt-1 w-full rounded-lg border-border bg-card text-sm text-primary" placeholder="12345">
                        </label>
                        <label class="block">
                            <span class="text-xs font-semibold uppercase text-secondary">Stadt</span>
                            <input v-model="shippingCity" class="mt-1 w-full rounded-lg border-border bg-card text-sm text-primary" placeholder="Berlin">
                        </label>
                        <label class="block sm:col-span-2">
                            <span class="text-xs font-semibold uppercase text-secondary">Bundesland / Region</span>
                            <input v-model="shippingState" class="mt-1 w-full rounded-lg border-border bg-card text-sm text-primary" placeholder="Optional">
                        </label>
                        <label class="block sm:col-span-2">
                            <span class="text-xs font-semibold uppercase text-secondary">Lieferhinweis</span>
                            <textarea v-model="shippingNote" rows="2" class="mt-1 w-full rounded-lg border-border bg-card text-sm text-primary" placeholder="Optional, z.B. bei Nachbarn abgeben"></textarea>
                        </label>
                    </div>
                </div>

                <div class="mt-4 rounded-lg border border-border bg-inputBg p-4">
                    <div class="flex items-start justify-between gap-4">
                        <div>
                            <p class="text-sm font-bold text-primary">Outfit-Abo-Vertrag</p>
                            <p class="mt-1 text-xs leading-5 text-secondary">
                                Diese Bedingungen gelten für diesen Abschluss und werden mit deiner Anfrage gespeichert.
                            </p>
                        </div>
                        <span class="rounded-full bg-muted px-3 py-1 text-xs font-semibold text-secondary">
                            Version {{ planContractRules(pendingSubscribePlan).version || 'aktuell' }}
                        </span>
                    </div>
                    <dl class="mt-4 grid gap-3 text-sm">
                        <div class="flex items-center justify-between gap-4">
                            <dt class="text-secondary">Mindestlaufzeit</dt>
                            <dd class="font-semibold text-primary">{{ planContractRules(pendingSubscribePlan).minimum_term_months }} Monate</dd>
                        </div>
                        <div class="flex items-center justify-between gap-4">
                            <dt class="text-secondary">Pause moeglich ab</dt>
                            <dd class="font-semibold text-primary">Monat {{ planContractRules(pendingSubscribePlan).pause_allowed_after_months }}</dd>
                        </div>
                        <div class="flex items-center justify-between gap-4">
                            <dt class="text-secondary">Kuendigungsfrist</dt>
                            <dd class="font-semibold text-primary">{{ planContractRules(pendingSubscribePlan).cancellation_notice_days }} Tage</dd>
                        </div>
                    </dl>
                    <ul class="mt-4 space-y-2 text-xs leading-5 text-secondary">
                        <li v-for="term in planContractTerms(pendingSubscribePlan)" :key="term">- {{ term }}</li>
                    </ul>
                </div>

                <label class="mt-4 flex items-start gap-3 rounded-lg border border-border bg-card p-3 text-sm text-secondary">
                    <input v-model="subscribeAcceptedContract" type="checkbox" class="mt-1 rounded border-border bg-inputBg">
                    <span>
                        Ich akzeptiere den Outfit-Abo-Vertrag inkl. Mindestlaufzeit, Pausen-, Liefer- und Kuendigungsregeln.
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
                            AGB
                        </a>
                        und
                        <a
                            :href="route('legal.withdrawal')"
                            target="_blank"
                            rel="noopener noreferrer"
                            class="font-semibold text-accent underline underline-offset-2"
                            @click.stop
                        >
                            Widerrufshinweise
                        </a>
                        und weiss, dass das Abo erst nach Zahlungsbestaetigung aktiv wird.
                    </span>
                </label>

                <div class="mt-5 flex flex-col-reverse gap-2 sm:flex-row sm:justify-end">
                    <button type="button" class="rounded-lg border border-border px-4 py-2 text-sm font-semibold text-primary hover:bg-muted" @click="closeSubscribeModal">
                        Abbrechen
                    </button>
                    <button
                        type="button"
                        class="rounded-lg bg-buttonPrimary px-4 py-2 text-sm font-semibold text-buttonTextPrimary hover:opacity-90 disabled:opacity-50"
                        :disabled="!subscribeAcceptedTerms || !subscribeAcceptedContract || !hasShippingAddress || subscribingPlanId === pendingSubscribePlan.id"
                        @click="confirmSubscribe"
                    >
                        {{ subscribingPlanId === pendingSubscribePlan.id ? 'Wird gesendet...' : 'Kostenpflichtig anfragen' }}
                    </button>
                </div>
            </div>
        </div>

        <div v-if="pendingCancelSubscription" class="fixed inset-0 z-[80] flex items-center justify-center bg-black/60 p-4 backdrop-blur-sm">
            <div class="w-full max-w-md rounded-lg border border-border bg-card p-5 shadow-2xl">
                <div class="flex items-start justify-between gap-4">
                    <div>
                        <h2 class="text-lg font-bold text-primary">
                            {{ isPendingPayment(pendingCancelSubscription) ? 'Outfit-Abo-Anfrage abbrechen?' : 'Outfit-Abo kuendigen?' }}
                        </h2>
                        <p class="mt-2 text-sm leading-6 text-secondary">
                            {{ isPendingPayment(pendingCancelSubscription)
                                ? 'Der Kaufvertrag ist noch nicht abgeschlossen. Die offene Anfrage wird abgebrochen und es wird keine Zahlung mehr erwartet.'
                                : 'Das Abo wird beendet. Bereits geplante interne Bearbeitungsschritte werden danach nicht weitergefuehrt.' }}
                        </p>
                    </div>
                    <button type="button" class="rounded-lg p-2 text-secondary hover:bg-muted hover:text-primary" @click="closeCancelModal">
                        <i class="las la-times text-xl"></i>
                    </button>
                </div>

                <div class="mt-5 rounded-lg bg-inputBg p-4">
                    <p class="text-sm font-semibold text-primary">{{ pendingCancelSubscription.plan?.name || 'Outfit-Abo' }}</p>
                    <p class="mt-1 text-sm text-secondary">
                        {{ formatMoney(pendingCancelSubscription.monthly_price_cents, pendingCancelSubscription.currency) }} / Monat
                    </p>
                </div>

                <div class="mt-5 flex flex-col-reverse gap-2 sm:flex-row sm:justify-end">
                    <button type="button" class="rounded-lg border border-border px-4 py-2 text-sm font-semibold text-primary hover:bg-muted" @click="closeCancelModal">
                        Abbrechen
                    </button>
                    <button type="button" class="rounded-lg bg-red-600 px-4 py-2 text-sm font-semibold text-white hover:bg-red-500" @click="confirmCancel">
                        {{ isPendingPayment(pendingCancelSubscription) ? 'Anfrage abbrechen' : 'Kuendigen' }}
                    </button>
                </div>
            </div>
        </div>

        <div v-if="pendingIssueDelivery" class="fixed inset-0 z-[80] flex items-center justify-center bg-black/60 p-4 backdrop-blur-sm">
            <div class="w-full max-w-lg rounded-lg border border-border bg-card p-5 shadow-2xl">
                <div class="flex items-start justify-between gap-4">
                    <div>
                        <p class="text-xs font-semibold uppercase tracking-wide text-accent">Lieferproblem melden</p>
                        <h2 class="mt-1 text-lg font-bold text-primary">{{ statusLabel(pendingIssueDelivery.status) }} vom {{ formatDate(pendingIssueDelivery.delivery_month) }}</h2>
                        <p class="mt-2 text-sm leading-6 text-secondary">
                            Beschreibe kurz, was nicht passt. Das Support-Team sieht Lieferung, Tracking und dein Style-Profil direkt dazu.
                        </p>
                    </div>
                    <button type="button" class="rounded-lg p-2 text-secondary hover:bg-muted hover:text-primary" @click="closeIssueModal">
                        <i class="las la-times text-xl"></i>
                    </button>
                </div>

                <div class="mt-5 grid gap-4">
                    <label class="block">
                        <span class="text-sm font-semibold text-primary">Art des Problems</span>
                        <select v-model="issueForm.issue_type" class="mt-1 w-full rounded-lg border-border bg-inputBg text-primary">
                            <option value="exchange">Umtausch / andere Groesse</option>
                            <option value="return">Retoure</option>
                            <option value="damaged">Beschaedigt</option>
                            <option value="missing_item">Artikel fehlt</option>
                            <option value="wrong_item">Falscher Artikel</option>
                            <option value="other">Sonstiges</option>
                        </select>
                    </label>

                    <label class="block">
                        <span class="text-sm font-semibold text-primary">Beschreibung</span>
                        <textarea v-model="issueForm.issue_description" rows="4" class="mt-1 w-full rounded-lg border-border bg-inputBg text-primary" placeholder="Was ist passiert? Welche Artikel sind betroffen?"></textarea>
                        <span v-if="issueForm.errors.issue_description" class="mt-1 block text-xs text-red-300">{{ issueForm.errors.issue_description }}</span>
                    </label>

                    <label class="block">
                        <span class="text-sm font-semibold text-primary">Wunschloesung</span>
                        <input v-model="issueForm.issue_requested_resolution" class="mt-1 w-full rounded-lg border-border bg-inputBg text-primary" placeholder="z.B. Ersatz, Retoure, Gutschrift">
                    </label>

                    <label class="block">
                        <span class="text-sm font-semibold text-primary">Gewuenschte Groesse</span>
                        <input v-model="issueForm.issue_exchange_size" class="mt-1 w-full rounded-lg border-border bg-inputBg text-primary" placeholder="Optional, z.B. M statt L">
                    </label>
                </div>

                <div class="mt-5 flex flex-col-reverse gap-2 sm:flex-row sm:justify-end">
                    <button type="button" class="rounded-lg border border-border px-4 py-2 text-sm font-semibold text-primary hover:bg-muted" @click="closeIssueModal">
                        Abbrechen
                    </button>
                    <button type="button" class="rounded-lg bg-buttonPrimary px-4 py-2 text-sm font-semibold text-buttonTextPrimary hover:opacity-90 disabled:opacity-50" :disabled="issueForm.processing || !issueForm.issue_description" @click="submitIssue">
                        Meldung senden
                    </button>
                </div>
            </div>
        </div>
    </div>
</template>
