<script setup>
import { computed, nextTick, ref } from 'vue'
import { Link, router, usePage } from '@inertiajs/vue3'
import axios from 'axios'
import Nav from '@/Components/Guest/Nav.vue'
import Subnav from '@/Components/Guest/Subnav.vue'
import Footer from '@/Components/Guest/Footer.vue'
import SeoHead from '@/Components/Guest/SeoHead.vue'
import { useI18n } from 'vue-i18n'
import pricingCopy from './pricingCopy.json'

const props = defineProps({
    canLogin: Boolean,
    canRegister: Boolean,
    plans: { type: Array, default: () => [] },
    planGroups: { type: Object, default: () => ({}) },
    checkoutClubs: { type: Array, default: () => [] },
    pricingCountry: { type: String, default: 'DE' },
    pricingCountrySource: { type: String, default: 'fallback' },
})

const audiences = [
    {
        key: 'sportler',
        label: 'Sportler',
    },
    {
        key: 'trainer',
        label: 'Trainer',
    },
    {
        key: 'verein',
        label: 'Vereine',
    },
    {
        key: 'eltern',
        label: 'Eltern',
    },
    {
        key: 'sponsor',
        label: 'Sponsoren',
    },
    {
        key: 'anbieter',
        label: 'Anbieter',
    },
    {
        key: 'werbeagentur',
        label: 'Werbeagentur',
    },
    {
        key: 'enterprise',
        label: 'Enterprise',
    },
]

const selectedAudience = ref(audiences.find((audience) => props.planGroups[audience.key]?.length)?.key || 'verein')
const page = usePage()
const { t, te, locale } = useI18n()

const checkoutCopy = {
    de: {
        stripe: 'Stripe / Karte', bank_transfer: 'Überweisung', club: 'Verein', choose_club: 'Verein auswählen',
        no_club: 'Du benötigst einen Verein, den du als Inhaber, Admin, Manager oder Finanzverantwortlicher verwaltest.',
        missing_redirect: 'Der Zahlungsanbieter hat keinen Weiterleitungslink geliefert. Bitte versuche es erneut.',
        status_error: 'Checkout konnte nicht gestartet werden (Serverstatus {status}).',
        network_error: 'Checkout konnte wegen eines Netzwerkfehlers nicht gestartet werden. Bitte prüfe deine Verbindung und versuche es erneut.',
    },
    en: {
        stripe: 'Stripe / card', bank_transfer: 'Bank transfer', club: 'Club', choose_club: 'Select a club',
        no_club: 'You need a club that you manage as owner, admin, manager or financial controller.',
        missing_redirect: 'The payment provider did not return a redirect link. Please try again.',
        status_error: 'Checkout could not be started (server status {status}).',
        network_error: 'Checkout could not be started because of a network error. Check your connection and try again.',
    },
    fr: {
        stripe: 'Stripe / carte', bank_transfer: 'Virement bancaire', club: 'Club', choose_club: 'Sélectionner un club',
        no_club: 'Vous avez besoin d’un club que vous gérez comme propriétaire, administrateur, manager ou responsable financier.',
        missing_redirect: 'Le prestataire de paiement n’a fourni aucun lien de redirection. Veuillez réessayer.',
        status_error: 'Le paiement n’a pas pu démarrer (statut serveur {status}).',
        network_error: 'Le paiement n’a pas pu démarrer en raison d’une erreur réseau. Vérifiez votre connexion et réessayez.',
    },
    ar: {
        stripe: 'Stripe / بطاقة', bank_transfer: 'تحويل مصرفي', club: 'النادي', choose_club: 'اختر النادي',
        no_club: 'تحتاج إلى نادٍ تديره بصفتك مالكاً أو مسؤولاً أو مديراً أو مسؤولاً مالياً.',
        missing_redirect: 'لم يرسل مزود الدفع رابط إعادة التوجيه. يرجى المحاولة مرة أخرى.',
        status_error: 'تعذر بدء الدفع (حالة الخادم {status}).',
        network_error: 'تعذر بدء الدفع بسبب خطأ في الشبكة. تحقق من اتصالك وحاول مرة أخرى.',
    },
}

const checkoutText = (key, params = {}) => {
    const language = String(locale.value || 'de').split('-')[0]
    let value = checkoutCopy[language]?.[key] || checkoutCopy.de[key] || key

    Object.entries(params).forEach(([name, replacement]) => {
        value = value.replaceAll(`{${name}}`, String(replacement))
    })

    return value
}

const tx = (value, params = {}) => {
    const key = String(value ?? '')
    return te(key) ? t(key, params) : key.replace(/\{(\w+)\}/g, (match, name) => params[name] ?? match)
}
const couponCode = ref('')
const checkoutModal = ref({
    open: false,
    plan: null,
    provider: 'paypal',
    accepted: false,
    processing: false,
    error: '',
    clubId: '',
    requestId: '',
})

const requestedAudience = typeof window !== 'undefined'
    ? new URLSearchParams(window.location.search).get('audience')
    : null

if (requestedAudience && audiences.some((audience) => audience.key === requestedAudience)) {
    selectedAudience.value = requestedAudience
}

const currentAudience = computed(() => audiences.find((audience) => audience.key === selectedAudience.value) || audiences[2])
const visiblePlans = computed(() => props.planGroups[selectedAudience.value] || [])

const ui = computed(() => pricingCopy[String(locale.value).split('-')[0]] || pricingCopy.de)
const activeView = ref('plans')
const selectedPlanId = ref(null)
const detailPlan = computed(() => visiblePlans.value.find(plan => String(plan.id) === String(selectedPlanId.value)) || visiblePlans.value[0])
const planTab = ref(null)
const detailTab = ref(null)
const summaryFeatures = plan => (plan.features || []).filter(feature => String(feature).length <= 180).slice(0, 3)

const selectView = async (view, focus = false) => {
    activeView.value = view
    if (focus) {
        await nextTick()
        const tab = view === 'plans' ? planTab : detailTab
        tab.value?.focus()
    }
}

const showDetails = async plan => {
    selectedPlanId.value = plan.id
    await selectView('details', true)
}

const formatPrice = (cents, currency = 'EUR') => {
    const numberLocale = {
        de: 'de-DE',
        en: 'en-US',
        fr: 'fr-FR',
        ar: 'ar-EG',
    }[locale.value] || 'de-DE'

    return new Intl.NumberFormat(numberLocale, {
        style: 'currency',
        currency,
    }).format((Number(cents) || 0) / 100)
}

const priceCaption = (plan) => {
    if (plan.slug === 'enterprise-verband') return 'individuell'
    if (!plan.monthly_price_cents) return 'kostenlos'

    return 'pro Monat'
}

const ownsPlan = (plan) => Boolean(plan.is_owned)

const ctaLabel = (plan) => plan.cta_label || (plan.monthly_price_cents ? 'Plan testen' : 'Kostenlos starten')

const newRequestId = () => {
    if (typeof crypto !== 'undefined' && typeof crypto.randomUUID === 'function') {
        return `pricing:${crypto.randomUUID()}`
    }

    return `pricing:${Date.now()}:${Math.random().toString(36).slice(2)}`
}

const checkoutNeedsClub = computed(() => checkoutModal.value.plan?.target_actor === 'verein')
const checkoutCanSubmit = computed(() => checkoutModal.value.accepted
    && !checkoutModal.value.processing
    && (!checkoutNeedsClub.value || Boolean(checkoutModal.value.clubId)))

const requestCheckout = (plan, provider) => {
    if (ownsPlan(plan)) return

    if (!page.props.auth?.user) {
        router.visit(route('login', {
            redirect: route('guest.pricing', { audience: selectedAudience.value }),
        }))
        return
    }

    checkoutModal.value = {
        open: true,
        plan,
        provider,
        accepted: false,
        processing: false,
        error: '',
        clubId: plan.target_actor === 'verein'
            ? (props.checkoutClubs.find(club => String(club.id) === new URL(page.url, 'http://localhost').searchParams.get('club_id'))?.id || props.checkoutClubs[0]?.id || '')
            : '',
        requestId: newRequestId(),
    }
}

const closeCheckoutModal = () => {
    checkoutModal.value = {
        open: false,
        plan: null,
        provider: 'paypal',
        accepted: false,
        processing: false,
        error: '',
        clubId: '',
        requestId: '',
    }
}

const providerLabel = (provider) => ({
    stripe: checkoutText('stripe'),
    paypal: 'PayPal',
    bank_transfer: checkoutText('bank_transfer'),
})[provider] || provider

const setCsrfToken = (token) => {
    if (!token) return ''

    page.props.csrf_token = token

    const metaToken = document.querySelector('meta[name="csrf-token"]')

    if (metaToken) {
        metaToken.setAttribute('content', token)
    }

    return token
}

const csrfToken = () => {
    if (page.props.csrf_token) return page.props.csrf_token

    const metaToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content')

    if (metaToken) return metaToken

    const tokenCookie = document.cookie
        .split('; ')
        .find((cookie) => cookie.startsWith('XSRF-TOKEN='))
        ?.split('=')[1]

    return tokenCookie ? decodeURIComponent(tokenCookie) : ''
}

const xsrfCookieToken = () => {
    const tokenCookie = document.cookie
        .split('; ')
        .find((cookie) => cookie.startsWith('XSRF-TOKEN='))
        ?.split('=')[1]

    return tokenCookie ? decodeURIComponent(tokenCookie) : ''
}

const refreshCsrfToken = async () => {
    try {
        const response = await axios.get('/checkout/csrf-token', {
            headers: {
                Accept: 'application/json',
            },
        })

        return setCsrfToken(response.data?.csrf_token) || csrfToken()
    } catch (error) {
        return csrfToken()
    }
}

const createCheckout = (plan, token) => axios.post(route('subscription-checkout.store', plan.id), {
        provider: checkoutModal.value.provider,
        billing_interval: 'monthly',
        coupon_code: couponCode.value,
        accepted_terms: checkoutModal.value.accepted,
        club_id: checkoutModal.value.clubId || null,
    }, {
    headers: {
        Accept: 'application/json',
        'X-CSRF-TOKEN': token,
        'X-XSRF-TOKEN': xsrfCookieToken(),
        'X-Checkout-Mode': 'json',
        'Idempotency-Key': checkoutModal.value.requestId,
    },
})

const startCheckout = async () => {
    const plan = checkoutModal.value.plan

    if (!plan || !checkoutCanSubmit.value) return

    checkoutModal.value.processing = true
    checkoutModal.value.error = ''

    try {
        let response

        try {
            response = await createCheckout(plan, await refreshCsrfToken())
        } catch (error) {
            if (error.response?.status !== 419) {
                throw error
            }

            response = await createCheckout(plan, await refreshCsrfToken())
        }

        if (response.data?.redirect_url) {
            window.location.assign(response.data.redirect_url)
            return
        }

        checkoutModal.value.error = checkoutText('missing_redirect')
    } catch (error) {
        const status = error.response?.status
        const serverMessage = Object.values(error.response?.data?.errors || {})?.flat()?.[0]
            || error.response?.data?.message
        checkoutModal.value.error = serverMessage
            || (status ? checkoutText('status_error', { status }) : checkoutText('network_error'))

        if (serverMessage && status) {
            checkoutModal.value.error = `${serverMessage} (${status})`
        }

        if (status) {
            checkoutModal.value.requestId = newRequestId()
        }
    } finally {
        checkoutModal.value.processing = false
    }
}
</script>

<template>
    <SeoHead
        :title="tx('Airmius Preise für Sportler, Trainer, Vereine, Partner und Werbeagentur')"
        :description="tx('Faire Airmius Pläne für Sportler, Trainer, Vereine, Eltern, Sponsoren, Anbieter, Verbände und Website-Services für Vereine.')"
    />

    <div class="min-h-screen bg-bg text-primary">
        <Nav :canLogin="canLogin" :canRegister="canRegister" />
        <Subnav />

        <main id="main-content" class="px-4 mb-8 pt-36 md:pt-44" tabindex="-1">
            <header class="mx-auto max-w-7xl">
                <h1 class="font-heading text-3xl font-900 sm:text-4xl">{{ ui.title }}</h1>
                <p class="mt-2 text-secondary">{{ ui.intro }}</p>
            </header>

            <section class="mx-auto mt-6 max-w-7xl">
                <label for="pricing-audience" class="mb-2 block text-sm font-semibold">{{ ui.audience }}</label>
                <select id="pricing-audience" v-model="selectedAudience" class="min-h-11 w-full rounded-lg border-border bg-inputBg text-primary sm:max-w-sm">
                    <option v-for="audience in audiences" :key="audience.key" :value="audience.key">{{ tx(audience.label) }}</option>
                </select>
                <div role="tablist" :aria-label="ui.title" class="mt-5 flex gap-2 border-b border-border">
                    <button id="pricing-plans-tab" ref="planTab" type="button" role="tab" aria-controls="pricing-plans"
                        :aria-selected="activeView === 'plans'" :tabindex="activeView === 'plans' ? 0 : -1"
                        class="min-h-11 border-b-2 px-3 py-2 text-sm font-semibold focus-visible:outline focus-visible:outline-2 focus-visible:outline-air-blue"
                        :class="activeView === 'plans' ? 'border-air-blue text-primary' : 'border-transparent text-secondary'"
                        @click="selectView('plans')" @keydown.right.prevent="selectView('details', true)" @keydown.left.prevent="selectView('details', true)"
                        @keydown.home.prevent="selectView('plans', true)" @keydown.end.prevent="selectView('details', true)">
                        {{ ui.plans }}
                    </button>
                    <button id="pricing-details-tab" ref="detailTab" type="button" role="tab" aria-controls="pricing-details"
                        :aria-selected="activeView === 'details'" :tabindex="activeView === 'details' ? 0 : -1"
                        class="min-h-11 border-b-2 px-3 py-2 text-sm font-semibold focus-visible:outline focus-visible:outline-2 focus-visible:outline-air-blue"
                        :class="activeView === 'details' ? 'border-air-blue text-primary' : 'border-transparent text-secondary'"
                        @click="selectView('details')" @keydown.right.prevent="selectView('plans', true)" @keydown.left.prevent="selectView('plans', true)"
                        @keydown.home.prevent="selectView('plans', true)" @keydown.end.prevent="selectView('details', true)">
                        {{ ui.details }}
                    </button>
                </div>
            </section>

            <section id="pricing-plans" v-show="activeView === 'plans'" role="tabpanel" aria-labelledby="pricing-plans-tab" tabindex="0" class="mx-auto mt-6 max-w-7xl">
                <h2 class="sr-only">{{ tx(currentAudience.label) }} – {{ ui.plans }}</h2>
                    <div class="mx-auto grid items-start gap-5" :class="visiblePlans.length === 1 ? 'max-w-xl' : visiblePlans.length === 2 ? 'max-w-4xl md:grid-cols-2' : 'md:grid-cols-2 xl:grid-cols-3'">
                        <article
                            v-if="selectedAudience === 'werbeagentur' && !visiblePlans.length"
                            class="surface-card flex flex-col p-5 md:col-span-2 xl:col-span-3"
                        >
                            <div class="grid gap-6 lg:grid-cols-[0.75fr_1.25fr]">
                                <div>
                                    <h3 class="text-xl font-bold text-primary">{{ tx('Individuelles Angebot') }}</h3>
                                    <p class="mt-3 text-sm leading-relaxed text-secondary">
                                        {{ tx('Websites und Kampagnen hängen stark von Umfang, Inhalten, Domain, Seitenanzahl und gewünschter Betreuung ab. Deshalb arbeitet Airmius hier mit Anfrage und Angebot.') }}
                                    </p>
                                    <Link
                                        :href="route('guest.werbeagentur')"
                                        class="mt-5 inline-flex rounded-lg bg-buttonPrimary px-4 py-2 text-sm font-semibold text-buttonTextPrimary"
                                    >
                                        {{ tx('Zur Werbeagentur-Seite') }}
                                    </Link>
                                </div>
                                <ul class="grid gap-3 text-sm text-secondary sm:grid-cols-2">
                                    <li class="rounded-lg bg-bg p-4">
                                        <p class="font-semibold text-primary">{{ tx('Website') }}</p>
                                        <p class="mt-1">{{ tx('Vereinsseite, Landingpage oder Kampagnenseite.') }}</p>
                                    </li>
                                    <li class="rounded-lg bg-bg p-4">
                                        <p class="font-semibold text-primary">{{ tx('Sichtbarkeit') }}</p>
                                        <p class="mt-1">{{ tx('SEO-Grundlage, Texte, Struktur und lokale Auffindbarkeit.') }}</p>
                                    </li>
                                    <li class="rounded-lg bg-bg p-4">
                                        <p class="font-semibold text-primary">{{ tx('Sponsoren') }}</p>
                                        <p class="mt-1">{{ tx('Sponsorenbereiche, Angebotsseiten und digitale Pakete.') }}</p>
                                    </li>
                                    <li class="rounded-lg bg-bg p-4">
                                        <p class="font-semibold text-primary">{{ tx('Prozess') }}</p>
                                        <p class="mt-1">{{ tx('Anfrage, klares Angebot, Annahme, Umsetzung.') }}</p>
                                    </li>
                                </ul>
                            </div>
                        </article>

                        <article
                            v-for="plan in visiblePlans"
                            :key="plan.id"
                            class="surface-card flex flex-col p-5"
                            :class="['club', 'trainer-pro', 'sportler-free'].includes(plan.slug) ? 'border-air-blue/60 shadow-lg shadow-air-blue/10' : ''"
                        >
                            <div>
                                <div class="flex items-start justify-between gap-3">
                                    <h3 class="text-xl font-bold text-primary">{{ tx(plan.name) }}</h3>
                                    <span
                                        v-if="ownsPlan(plan)"
                                        class="rounded-full bg-air-green/15 px-2 py-1 text-xs font-semibold text-air-green"
                                    >
                                        {{ tx('Aktiv') }}
                                    </span>
                                    <span v-else-if="plan.badge" class="rounded-full bg-air-blue/15 px-2 py-1 text-xs font-semibold text-air-blue">{{ tx(plan.badge) }}</span>
                                </div>
                                <p class="mt-3 min-h-16 text-sm leading-relaxed text-secondary">{{ tx(plan.description) }}</p>
                            </div>

                            <div class="mt-5">
                                <p class="text-3xl font-900 text-primary">{{ formatPrice(plan.monthly_price_cents, plan.currency) }}</p>
                                <p class="text-sm text-secondary">{{ tx(priceCaption(plan)) }}</p>
                                <p v-if="plan.yearly_price_cents" class="mt-1 text-xs text-secondary">
                                    {{ formatPrice(plan.yearly_price_cents, plan.currency) }} {{ tx('pro Jahr') }}
                                </p>
                                <p v-if="plan.localized_price" class="mt-1 text-xs text-air-blue">
                                    {{ tx('Lokaler Preis für {country}', { country: plan.pricing_country }) }}
                                </p>
                            </div>

                            <dl class="mt-5 grid gap-2 text-sm">
                                <div v-if="plan.member_limit" class="flex justify-between gap-3 border-b border-border pb-2">
                                    <dt class="text-secondary">{{ tx('Mitglieder') }}</dt>
                                    <dd class="font-semibold text-primary">{{ plan.member_limit }}</dd>
                                </div>
                                <div v-if="plan.team_limit" class="flex justify-between gap-3 border-b border-border pb-2">
                                    <dt class="text-secondary">{{ tx('Teams') }}</dt>
                                    <dd class="font-semibold text-primary">{{ plan.team_limit }}</dd>
                                </div>
                                <div class="flex justify-between gap-3 border-b border-border pb-2">
                                    <dt class="text-secondary">{{ tx('Speicher') }}</dt>
                                    <dd class="font-semibold text-primary">{{ plan.storage_gb }} {{ tx('GB') }}</dd>
                                </div>
                            </dl>

                            <ul class="mt-5 space-y-2 text-sm text-secondary">
                                <li v-for="feature in summaryFeatures(plan)" :key="feature" class="flex gap-2">
                                    <i class="las la-check mt-0.5 text-air-green"></i>
                                    <span>{{ tx(feature) }}</span>
                                </li>
                            </ul>

                            <button type="button" class="mt-3 min-h-11 text-start text-sm font-semibold text-air-blue underline underline-offset-2" :aria-label="ui.details + ': ' + tx(plan.name)" @click="showDetails(plan)">
                                {{ ui.allDetails }}
                            </button>

                            <div class="mt-4 grid gap-2">
                                <div
                                    v-if="ownsPlan(plan)"
                                    class="rounded-lg border border-air-green/40 bg-air-green/10 px-4 py-3 text-center text-sm font-semibold text-air-green"
                                >
                                    {{ tx('Du besitzt diesen Plan') }}
                                </div>

                                <Link
                                    v-else-if="!plan.monthly_price_cents"
                                    :href="canRegister ? route('register') : route('login')"
                                    class="rounded-lg bg-buttonPrimary px-4 py-2 text-center text-sm font-semibold text-buttonTextPrimary"
                                >
                                    {{ tx(ctaLabel(plan)) }}
                                </Link>

                                <template v-else>
                                    <button
                                        type="button"
                                        class="rounded-lg bg-buttonPrimary px-4 py-2 text-center text-sm font-semibold text-buttonTextPrimary"
                                        @click="requestCheckout(plan, 'stripe')"
                                    >
                                        {{ tx('Mit Stripe zahlen') }}
                                    </button>
                                    <button
                                        type="button"
                                        class="rounded-lg border border-border px-4 py-2 text-center text-sm font-semibold text-primary hover:bg-muted"
                                        @click="requestCheckout(plan, 'paypal')"
                                    >
                                        {{ tx('Mit PayPal zahlen') }}
                                    </button>
                                    <button
                                        type="button"
                                        class="rounded-lg border border-border px-4 py-2 text-center text-sm font-semibold text-primary hover:bg-muted"
                                        @click="requestCheckout(plan, 'bank_transfer')"
                                    >
                                        {{ tx('Per Überweisung zahlen') }}
                                    </button>
                                </template>
                            </div>
                        </article>
                    </div>
                <p v-if="!visiblePlans.length && selectedAudience !== 'werbeagentur'" class="rounded-lg border border-border bg-card p-5 text-secondary">{{ ui.empty }}</p>
            </section>

            <section id="pricing-details" v-show="activeView === 'details'" role="tabpanel" aria-labelledby="pricing-details-tab" tabindex="0" class="mx-auto mt-6 max-w-7xl">
                <template v-if="detailPlan">
                    <label for="pricing-detail-plan" class="mb-2 block text-sm font-semibold">{{ ui.choosePlan }}</label>
                    <select id="pricing-detail-plan" :value="detailPlan.id" @change="selectedPlanId = $event.target.value" class="min-h-11 w-full rounded-lg border-border bg-inputBg text-primary sm:max-w-sm">
                        <option v-for="plan in visiblePlans" :key="plan.id" :value="plan.id">{{ tx(plan.name) }} – {{ formatPrice(plan.monthly_price_cents, plan.currency) }} {{ tx(priceCaption(plan)) }}</option>
                    </select>
                    <article :key="detailPlan.id" class="mt-4 rounded-lg border border-border bg-card p-5">
                        <h2 class="text-xl font-bold">{{ tx(detailPlan.name) }}</h2>
                        <p class="mt-2 text-sm text-secondary">{{ ui.detailsHelp }}</p>
                        <ul class="mt-4 divide-y divide-border text-sm">
                            <li v-for="(feature, index) in detailPlan.features" :key="index" class="py-3 leading-6">
                                <details v-if="String(feature).length > 180">
                                    <summary class="cursor-pointer font-semibold text-primary">{{ tx(feature).slice(0, 120) }}…</summary>
                                    <p class="mt-2 whitespace-pre-line text-secondary">{{ tx(feature) }}</p>
                                </details>
                                <span v-else>{{ tx(feature) }}</span>
                            </li>
                        </ul>
                    </article>
                </template>
                <p v-else class="rounded-lg border border-border bg-card p-5 text-secondary">{{ ui.empty }}</p>
            </section>
        </main>

        <Teleport to="body">
            <div v-if="checkoutModal.open && checkoutModal.plan" class="fixed inset-0 z-[90] flex items-center justify-center bg-black/65 p-4 backdrop-blur-sm">
                <div class="max-h-[90dvh] w-full max-w-lg overflow-y-auto rounded-lg border border-border bg-card p-5 shadow-2xl">
                    <div class="flex items-start justify-between gap-4">
                        <div>
                            <p class="text-xs font-semibold uppercase tracking-wide text-air-blue">{{ tx('Abo kostenpflichtig bestellen') }}</p>
                            <h2 class="mt-1 text-xl font-bold text-primary">{{ tx(checkoutModal.plan.name) }}</h2>
                            <p class="mt-2 text-sm leading-6 text-secondary">
                                {{ tx('Bitte prüfe dein Abo, bevor du zur Zahlung weitergeleitet wirst.') }}
                            </p>
                        </div>
                        <button type="button" class="rounded-lg p-2 text-secondary hover:bg-muted hover:text-primary" @click="closeCheckoutModal">
                            <i class="las la-times text-xl"></i>
                        </button>
                    </div>

                    <div class="mt-5 grid gap-3 rounded-lg border border-border bg-bg p-4 text-sm">
                        <div class="flex items-center justify-between gap-4">
                            <span class="text-secondary">{{ tx('Plan') }}</span>
                            <span class="font-semibold text-primary">{{ tx(checkoutModal.plan.name) }}</span>
                        </div>
                        <div class="flex items-center justify-between gap-4">
                            <span class="text-secondary">{{ tx('Preis') }}</span>
                            <span class="font-semibold text-primary">{{ formatPrice(checkoutModal.plan.monthly_price_cents, checkoutModal.plan.currency) }} {{ tx('pro Monat') }}</span>
                        </div>
                        <div class="flex items-center justify-between gap-4">
                            <span class="text-secondary">{{ tx('Zahlungsart') }}</span>
                            <span class="font-semibold text-primary">{{ tx(providerLabel(checkoutModal.provider)) }}</span>
                        </div>
                        <label v-if="checkoutNeedsClub" class="grid gap-2 border-t border-border pt-3">
                            <span class="text-secondary">{{ checkoutText('club') }}</span>
                            <select
                                v-model="checkoutModal.clubId"
                                class="w-full rounded-lg border-border bg-inputBg text-primary"
                                :aria-label="checkoutText('club')"
                            >
                                <option value="">{{ checkoutText('choose_club') }}</option>
                                <option v-for="club in checkoutClubs" :key="club.id" :value="club.id">{{ club.name }}</option>
                            </select>
                            <span v-if="!checkoutClubs.length" class="text-xs leading-5 text-warning">{{ checkoutText('no_club') }}</span>
                        </label>
                    </div>

                    <label class="mt-4 block text-sm font-semibold text-primary">
                        {{ tx('Rabattcode') }}
                        <input v-model="couponCode" type="text" class="mt-2 w-full rounded-lg border-border bg-inputBg text-sm uppercase text-primary" :aria-label="tx('Rabattcode')" :placeholder="tx('z. B. BETA50')">
                        <span class="mt-1 block text-xs font-normal text-secondary">{{ tx('Der Code wird beim Bezahlen automatisch berücksichtigt.') }}</span>
                    </label>

                    <label class="mt-4 flex items-start gap-3 rounded-lg border border-border bg-bg p-3 text-sm text-secondary">
                        <input v-model="checkoutModal.accepted" type="checkbox" class="mt-1 rounded border-border bg-inputBg">
                        <span>
                            {{ tx('Ich akzeptiere') }}
                            <a :href="route('terms.show')" target="_blank" rel="noopener noreferrer" class="font-semibold text-air-blue underline underline-offset-2" @click.stop>{{ tx('AGB') }}</a>,
                            <a :href="route('legal.withdrawal')" target="_blank" rel="noopener noreferrer" class="font-semibold text-air-blue underline underline-offset-2" @click.stop>{{ tx('Widerrufshinweise') }}</a>
                            {{ tx('und weiß, dass ich ein kostenpflichtiges Abo abschließe.') }}
                        </span>
                    </label>

                    <p v-if="checkoutModal.error" class="mt-3 rounded-lg border border-red-500/40 bg-red-500/10 px-3 py-2 text-sm text-red-200">
                        {{ checkoutModal.error }}
                    </p>

                    <div class="mt-5 flex flex-col-reverse gap-2 sm:flex-row sm:justify-end">
                        <button type="button" class="rounded-lg border border-border px-4 py-2 text-sm font-semibold text-primary hover:bg-muted" @click="closeCheckoutModal">
                            {{ tx('Abbrechen') }}
                        </button>
                        <button
                            type="button"
                            class="rounded-lg bg-buttonPrimary px-4 py-2 text-sm font-semibold text-buttonTextPrimary disabled:opacity-50"
                            :disabled="!checkoutCanSubmit"
                            @click="startCheckout"
                        >
                            {{ tx(checkoutModal.processing ? 'Checkout wird gestartet...' : 'Zahlungspflichtig bestellen') }}
                        </button>
                    </div>
                </div>
            </div>
        </Teleport>

        <Footer />
    </div>
</template>
