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
import Modal from '@/Components/Modal.vue'
import { splitPlanDetails, groupUsageNotes } from '@/support/pricingDetails.js'

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
const summaryFeatures = plan => splitPlanDetails(plan).included.filter(feature => String(feature).length <= 180).slice(0, 3)
const detailSearch = ref('')
const matchesSearch = text => tx(text).toLocaleLowerCase().includes(detailSearch.value.trim().toLocaleLowerCase())
const filteredFeatures = computed(() => splitPlanDetails(detailPlan.value).included.filter(matchesSearch))
const noteGroups = computed(() => groupUsageNotes(splitPlanDetails(detailPlan.value).notes.filter(matchesSearch)))
const selectDetailPlan = id => {
    selectedPlanId.value = id
    detailSearch.value = ''
}
const selectAudience = key => {
    selectedAudience.value = key
    selectedPlanId.value = null
    detailSearch.value = ''
}

const selectView = async (view, focus = false) => {
    activeView.value = view
    if (focus) {
        await nextTick()
        const tab = view === 'plans' ? planTab : detailTab
        tab.value?.focus()
    }
}

const showDetails = async plan => {
    selectDetailPlan(plan.id)
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

    <div class="pricing-page min-h-screen text-primary">
        <Nav :canLogin="canLogin" :canRegister="canRegister" />
        <Subnav />

        <main id="main-content" class="pricing-main" tabindex="-1">
            <div class="pricing-container">
                <header class="pricing-heading">
                    <p class="pricing-eyebrow">{{ ui.eyebrow }}</p>
                    <h1>{{ ui.title }}</h1>
                    <p>{{ ui.intro }}</p>
                </header>

                <div class="audience-picker" role="group" :aria-label="ui.audience">
                    <button v-for="audience in audiences" :key="audience.key" type="button"
                        :aria-pressed="selectedAudience === audience.key" @click="selectAudience(audience.key)">
                        {{ tx(audience.label) }}
                    </button>
                </div>

                <div class="pricing-toolbar">
                    <h2>{{ tx(currentAudience.label) }}</h2>
                    <div role="tablist" :aria-label="ui.title" class="pricing-view-switch">
                        <button id="pricing-plans-tab" ref="planTab" type="button" role="tab" aria-controls="pricing-plans"
                            :aria-selected="activeView === 'plans'" :tabindex="activeView === 'plans' ? 0 : -1"
                            @click="selectView('plans')" @keydown.right.prevent="selectView('details', true)" @keydown.left.prevent="selectView('details', true)"
                            @keydown.home.prevent="selectView('plans', true)" @keydown.end.prevent="selectView('details', true)">
                            {{ ui.plans }}
                        </button>
                        <button id="pricing-details-tab" ref="detailTab" type="button" role="tab" aria-controls="pricing-details"
                            :aria-selected="activeView === 'details'" :tabindex="activeView === 'details' ? 0 : -1"
                            @click="selectView('details')" @keydown.right.prevent="selectView('plans', true)" @keydown.left.prevent="selectView('plans', true)"
                            @keydown.home.prevent="selectView('plans', true)" @keydown.end.prevent="selectView('details', true)">
                            {{ ui.details }}
                        </button>
                    </div>
                </div>

                <section id="pricing-plans" v-show="activeView === 'plans'" role="tabpanel" aria-labelledby="pricing-plans-tab" tabindex="0">
                    <div class="plan-grid" :class="{ 'plan-grid-pair': visiblePlans.length === 2, 'plan-grid-single': visiblePlans.length === 1 }">
                        <article v-for="plan in visiblePlans" :key="plan.id" class="plan-card">
                            <div class="plan-card-heading">
                                <h3>{{ tx(plan.name) }}</h3>
                                <span v-if="ownsPlan(plan)" class="plan-badge">{{ tx('Aktiv') }}</span>
                                <span v-else-if="plan.badge" class="plan-badge">{{ tx(plan.badge) }}</span>
                            </div>
                            <p class="plan-description">{{ tx(plan.description) }}</p>
                            <div class="plan-price">
                                <strong>{{ plan.slug === 'enterprise-verband' ? tx('individuell') : formatPrice(plan.monthly_price_cents, plan.currency) }}</strong>
                                <span v-if="plan.slug !== 'enterprise-verband'">{{ tx(priceCaption(plan)) }}</span>
                            </div>
                            <p v-if="plan.yearly_price_cents" class="plan-price-note">{{ formatPrice(plan.yearly_price_cents, plan.currency) }} {{ tx('pro Jahr') }}</p>
                            <p v-if="plan.localized_price" class="plan-price-note">{{ tx('Lokaler Preis für {country}', { country: plan.pricing_country }) }}</p>

                            <dl class="plan-limits">
                                <div v-if="plan.member_limit"><dt>{{ tx('Mitglieder') }}</dt><dd>{{ plan.member_limit }}</dd></div>
                                <div v-if="plan.team_limit"><dt>{{ tx('Teams') }}</dt><dd>{{ plan.team_limit }}</dd></div>
                                <div><dt>{{ tx('Speicher') }}</dt><dd>{{ plan.storage_gb }} {{ tx('GB') }}</dd></div>
                            </dl>
                            <ul class="plan-highlights">
                                <li v-for="feature in summaryFeatures(plan)" :key="feature"><i class="las la-check" aria-hidden="true"></i><span>{{ tx(feature) }}</span></li>
                            </ul>
                            <div class="plan-actions">
                                <p v-if="ownsPlan(plan)" class="plan-owned">{{ tx('Du besitzt diesen Plan') }}</p>
                                <Link v-else-if="!plan.monthly_price_cents" :href="canRegister ? route('register') : route('login')" class="pricing-primary-action">{{ tx(ctaLabel(plan)) }}</Link>
                                <button v-else type="button" class="pricing-primary-action" @click="requestCheckout(plan, 'stripe')">{{ ui.choose }}</button>
                                <button type="button" class="pricing-detail-action" :aria-label="ui.details + ': ' + tx(plan.name)" @click="showDetails(plan)">
                                    {{ ui.allDetails }} <span aria-hidden="true">→</span>
                                </button>
                            </div>
                        </article>
                    </div>
                    <article v-if="selectedAudience === 'werbeagentur' && !visiblePlans.length" class="pricing-service-card">
                        <span class="pricing-service-icon" aria-hidden="true"><i class="las la-laptop-code"></i></span>
                        <div><h3>{{ tx('Individuelles Angebot') }}</h3><p>{{ tx('Websites und Kampagnen hängen stark von Umfang, Inhalten, Domain, Seitenanzahl und gewünschter Betreuung ab. Deshalb arbeitet Airmius hier mit Anfrage und Angebot.') }}</p></div>
                        <Link :href="route('guest.werbeagentur')" class="pricing-primary-action">{{ tx('Zur Werbeagentur-Seite') }}</Link>
                    </article>
                    <p v-else-if="!visiblePlans.length" class="pricing-empty">{{ ui.empty }}</p>
                </section>

                <section id="pricing-details" v-show="activeView === 'details'" role="tabpanel" aria-labelledby="pricing-details-tab" tabindex="0">
                    <div v-if="detailPlan" class="pricing-detail-layout" :class="{ 'pricing-detail-single': visiblePlans.length === 1 }">
                        <aside v-if="visiblePlans.length > 1" class="detail-plan-picker" :aria-label="ui.choosePlan">
                            <p>{{ ui.choosePlan }}</p>
                            <div role="group" :aria-label="ui.choosePlan">
                                <button v-for="plan in visiblePlans" :key="plan.id" type="button" :aria-pressed="detailPlan.id === plan.id" @click="selectDetailPlan(plan.id)">
                                    <span><strong>{{ tx(plan.name) }}</strong><span>{{ formatPrice(plan.monthly_price_cents, plan.currency) }} · {{ tx(priceCaption(plan)) }}</span></span>
                                    <i class="las la-angle-right" aria-hidden="true"></i>
                                </button>
                            </div>
                        </aside>
                        <article :key="detailPlan.id" class="plan-detail-card">
                            <header class="plan-detail-header">
                                <div><p class="pricing-eyebrow">{{ ui.detailEyebrow }}</p><h3>{{ tx(detailPlan.name) }}</h3><p>{{ tx(detailPlan.description) }}</p></div>
                                <div class="detail-price"><strong>{{ detailPlan.slug === 'enterprise-verband' ? tx('individuell') : formatPrice(detailPlan.monthly_price_cents, detailPlan.currency) }}</strong><span v-if="detailPlan.slug !== 'enterprise-verband'">{{ tx(priceCaption(detailPlan)) }}</span></div>
                            </header>
                            <label class="detail-search"><i class="las la-search" aria-hidden="true"></i><span class="sr-only">{{ ui.search }}</span><input v-model="detailSearch" type="search" :placeholder="ui.search"></label>
                            <section v-if="filteredFeatures.length" class="detail-features">
                                <h4>{{ ui.included }}</h4>
                                <ul><li v-for="(feature, index) in filteredFeatures" :key="index"><i class="las la-check" aria-hidden="true"></i><span>{{ tx(feature) }}</span></li></ul>
                            </section>
                            <section v-if="noteGroups.length" class="detail-notes">
                                <h4>{{ ui.notes }}</h4>
                                <p>{{ ui.detailsHelp }}</p>
                                <details v-for="group in noteGroups" :key="`${detailPlan.id}-${group.key}-${detailSearch}`" class="note-group" :open="Boolean(detailSearch.trim())">
                                    <summary><span>{{ ui.topics[group.key] }}</span><span class="note-count">{{ group.items.length }}</span><i class="las la-plus" aria-hidden="true"></i></summary>
                                    <ul><li v-for="(note, index) in group.items" :key="index">{{ tx(note) }}</li></ul>
                                </details>
                            </section>
                            <div v-if="!filteredFeatures.length && !noteGroups.length" class="pricing-empty"><p>{{ detailSearch ? ui.noResults : ui.emptyDetails }}</p><button v-if="detailSearch" type="button" class="pricing-detail-action" @click="detailSearch = ''">{{ ui.clearSearch }}</button></div>
                        </article>
                    </div>
                    <p v-else class="pricing-empty">{{ ui.empty }}</p>
                </section>
            </div>
        </main>

        <Modal :show="checkoutModal.open" :closeable="!checkoutModal.processing" max-width="lg" :aria-label="tx('Abo kostenpflichtig bestellen')" @close="closeCheckoutModal">
                <div v-if="checkoutModal.plan" class="p-2 sm:p-3">
                    <div class="flex items-start justify-between gap-4">
                        <div>
                            <p class="text-xs font-semibold uppercase tracking-wide text-air-blue">{{ tx('Abo kostenpflichtig bestellen') }}</p>
                            <h2 class="mt-1 text-xl font-bold text-primary">{{ tx(checkoutModal.plan.name) }}</h2>
                            <p class="mt-2 text-sm leading-6 text-secondary">
                                {{ tx('Bitte prüfe dein Abo, bevor du zur Zahlung weitergeleitet wirst.') }}
                            </p>
                        </div>

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
                        <fieldset :disabled="checkoutModal.processing">
                            <legend class="text-secondary">{{ tx('Zahlungsart') }}</legend>
                            <div class="payment-options">
                                <label v-for="provider in ['stripe', 'paypal', 'bank_transfer']" :key="provider">
                                    <input v-model="checkoutModal.provider" type="radio" name="pricing-payment-provider" :value="provider" class="border-border">
                                    <span>{{ tx(providerLabel(provider)) }}</span>
                                </label>
                            </div>
                        </fieldset>
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
                        <button type="button" class="rounded-lg border border-border px-4 py-2 text-sm font-semibold text-primary hover:bg-muted" :disabled="checkoutModal.processing" @click="closeCheckoutModal">
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
        </Modal>

        <Footer />
    </div>
</template>

<style scoped>
.pricing-page { background: color-mix(in srgb, var(--bg) 96%, var(--primary)); }
.pricing-main { padding: 160px 24px 64px; color: var(--primary); }
.pricing-container { max-width: 1080px; margin-inline: auto; }
.pricing-heading { text-align: center; margin: 0 auto 24px; max-width: 640px; }
.pricing-eyebrow { font-size: 11px; font-weight: 750; letter-spacing: .12em; text-transform: uppercase; color: var(--secondary); }
.pricing-heading h1 { margin: 8px 0 10px; font-size: clamp(27px, 3vw, 38px); font-weight: 750; line-height: 1.15; letter-spacing: -.035em; }
.pricing-heading > p:last-child { color: var(--secondary); font-size: 15px; line-height: 1.55; }
.audience-picker { display: flex; flex-wrap: wrap; justify-content: center; gap: 6px; margin: 0 auto 26px; }
.audience-picker button { min-height: 42px; padding: 9px 17px; border: 1px solid var(--border); border-radius: 24px; color: var(--secondary); background: var(--card); font-size: 13px; font-weight: 650; }
.audience-picker button:hover { border-color: var(--secondary); color: var(--primary); }
.audience-picker button[aria-pressed="true"] { color: var(--card); background: var(--primary); border-color: var(--primary); }
.pricing-toolbar { display: flex; justify-content: space-between; align-items: center; gap: 16px; margin-bottom: 18px; }
.pricing-toolbar h2 { font-size: 20px; font-weight: 700; letter-spacing: -.02em; }
.pricing-view-switch { display: inline-flex; gap: 3px; padding: 4px; border: 1px solid var(--border); border-radius: 10px; background: var(--bg); }
.pricing-view-switch button { min-height: 36px; padding: 7px 14px; font-size: 13px; font-weight: 650; color: var(--secondary); border-radius: 7px; }
.pricing-view-switch button[aria-selected="true"] { background: var(--card); color: var(--primary); box-shadow: 0 1px 4px #10203316; }
.plan-grid { display: grid; grid-template-columns: repeat(3, minmax(0, 1fr)); gap: 18px; }
.plan-grid-pair { grid-template-columns: repeat(2, minmax(0, 1fr)); max-width: 840px; margin-inline: auto; }
.plan-grid-single { grid-template-columns: minmax(0, 1fr); max-width: 440px; margin-inline: auto; }
.plan-card, .plan-detail-card, .pricing-service-card { background: var(--card); border: 1px solid var(--border); border-radius: 16px; box-shadow: 0 3px 12px #10203304; }
.plan-card { padding: 26px; display: flex; flex-direction: column; min-width: 0; }
.plan-card-heading { display: flex; align-items: center; justify-content: space-between; gap: 12px; }
.plan-card-heading h3 { font-size: 20px; font-weight: 750; letter-spacing: -.025em; }
.plan-badge { background: var(--muted); color: var(--primary); padding: 4px 8px; font-size: 11px; border-radius: 5px; }
.plan-description { margin-top: 10px; font-size: 14px; line-height: 1.6; color: var(--secondary); }
.plan-price { display: flex; flex-wrap: wrap; align-items: baseline; gap: 7px; margin: 22px 0 2px; }
.plan-price strong { font-size: 36px; font-weight: 750; letter-spacing: -.045em; line-height: 1.2; }
.plan-price > span, .plan-price-note { font-size: 12px; line-height: 1.7; color: var(--secondary); }
.plan-limits { display: flex; flex-wrap: wrap; gap: 16px; padding: 17px 0; margin: 16px 0 0; border-top: 1px solid var(--border); border-bottom: 1px solid var(--border); }
.plan-limits div { display: flex; flex-direction: column-reverse; gap: 2px; }
.plan-limits dd { font-size: 15px; font-weight: 700; }.plan-limits dt { font-size: 11px; color: var(--secondary); }
.plan-highlights { margin: 18px 0 20px; display: grid; gap: 10px; font-size: 14px; line-height: 1.5; }
.plan-highlights li, .detail-features li { display: flex; align-items: baseline; gap: 9px; }
.plan-highlights i, .detail-features i { flex: 0 0 auto; color: var(--secondary); font-weight: 700; }
.plan-actions { margin-top: auto; display: grid; gap: 5px; }
.pricing-primary-action { display: inline-flex; align-items: center; justify-content: center; min-height: 44px; padding: 11px 16px; border-radius: 8px; font-size: 14px; font-weight: 700; text-align: center; color: var(--card); background: var(--primary); }
.pricing-primary-action:hover { opacity: .85; }
.pricing-detail-action { display: inline-flex; gap: 8px; align-items: center; justify-content: center; min-height: 42px; font-size: 12px; font-weight: 650; color: var(--secondary); }
.pricing-detail-action:hover { color: var(--primary); text-decoration: underline; text-underline-offset: 4px; }
.plan-owned { font-size: 13px; padding: 11px; text-align: center; background: var(--muted); border-radius: 8px; }
.pricing-detail-layout { display: grid; grid-template-columns: 230px minmax(0, 1fr); align-items: start; gap: 24px; }
.pricing-detail-single { grid-template-columns: minmax(0, 1fr); max-width: 800px; margin-inline: auto; }
.detail-plan-picker { min-width: 0; }
.detail-plan-picker > p { font-size: 11px; font-weight: 700; text-transform: uppercase; letter-spacing: .07em; color: var(--secondary); margin: 2px 0 10px; }
.detail-plan-picker > div { display: grid; gap: 8px; }
.detail-plan-picker button { display: flex; justify-content: space-between; align-items: center; gap: 10px; text-align: start; padding: 14px; border: 1px solid transparent; border-radius: 10px; color: var(--secondary); }
.detail-plan-picker button:hover { background: var(--card); }
.detail-plan-picker button[aria-pressed="true"] { border-color: var(--border); background: var(--card); color: var(--primary); box-shadow: inset 3px 0 var(--primary); }
.detail-plan-picker:dir(rtl) button[aria-pressed="true"] { box-shadow: inset -3px 0 var(--primary); }
.detail-plan-picker:dir(rtl) button > i, .pricing-detail-action:dir(rtl) > span { transform: rotate(180deg); }
.detail-plan-picker strong { display: block; font-size: 14px; font-weight: 700; margin-bottom: 3px; }
.detail-plan-picker button span span { display: block; font-size: 12px; color: var(--secondary); }
.plan-detail-card { padding: 26px; min-width: 0; }
.plan-detail-header { display: flex; justify-content: space-between; align-items: start; gap: 24px; }
.plan-detail-header h3 { font-size: 24px; font-weight: 750; line-height: 1.25; letter-spacing: -.025em; margin: 5px 0 8px; }
.plan-detail-header > div > p:last-child:not(.pricing-eyebrow) { max-width: 48ch; font-size: 13px; line-height: 1.6; color: var(--secondary); }
.detail-price { text-align: end; flex-shrink: 0; }
.detail-price strong { display: block; font-size: 26px; font-weight: 750; letter-spacing: -.035em; }
.detail-price span { display: block; font-size: 12px; color: var(--secondary); }
.detail-search { display: flex; align-items: center; gap: 8px; border: 1px solid var(--border); background: var(--bg); border-radius: 8px; padding: 0 12px; margin-top: 22px; color: var(--secondary); }
.detail-search input { min-height: 42px; width: 100%; padding: 8px 0; border: 0; background: transparent; font-size: 13px; color: var(--primary); box-shadow: none; }
.detail-search:focus-within { outline: 2px solid var(--focus-ring); outline-offset: 2px; }
.detail-search input:focus-visible { outline: none; }
.detail-features { margin-top: 24px; }
.detail-features h4, .detail-notes h4 { font-size: 14px; font-weight: 700; }
.detail-features ul { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 12px 20px; margin-top: 14px; font-size: 14px; line-height: 1.6; }
.detail-notes { margin-top: 26px; padding-top: 22px; border-top: 1px solid var(--border); }
.detail-notes > p { font-size: 12px; line-height: 1.6; color: var(--secondary); margin: 6px 0 14px; max-width: 62ch; }
.note-group { border-top: 1px solid var(--border); }
.note-group:last-child { border-bottom: 1px solid var(--border); }
.note-group summary { display: flex; align-items: center; gap: 10px; min-height: 50px; font-size: 13px; font-weight: 650; cursor: pointer; list-style: none; }
.note-group summary::-webkit-details-marker { display: none; }
.note-group summary > span:first-child { flex: 1; }
.note-count { font-size: 11px; font-weight: 500; color: var(--secondary); background: var(--bg); border-radius: 5px; padding: 2px 6px; }
.note-group summary > i { color: var(--secondary); }.note-group[open] summary > i { transform: rotate(45deg); }
.note-group ul { display: grid; gap: 16px; padding: 0 0 18px; }
.note-group li { max-width: 68ch; overflow-wrap: anywhere; color: var(--secondary); font-size: 14px; line-height: 1.8; padding-inline-start: 14px; border-inline-start: 2px solid var(--border); }
.pricing-empty { padding: 30px; text-align: center; font-size: 14px; color: var(--secondary); }
.pricing-service-card { padding: 30px; display: flex; gap: 22px; align-items: center; }
.pricing-service-card h3 { font-size: 22px; font-weight: 700; }.pricing-service-card p { color: var(--secondary); font-size: 14px; line-height: 1.7; margin-top: 8px; max-width: 55ch; }
.pricing-service-card .pricing-primary-action { flex-shrink: 0; }
.pricing-service-icon { font-size: 36px; color: var(--secondary); }
.payment-options { display: grid; grid-template-columns: repeat(3, minmax(0, 1fr)); gap: 8px; margin-top: 10px; }
.payment-options label { display: flex; gap: 7px; align-items: center; min-height: 46px; padding: 10px; border: 1px solid var(--border); border-radius: 8px; font-size: 12px; cursor: pointer; }
.payment-options label:has(input:checked) { border-color: var(--primary); background: var(--bg); }
@media (max-width: 900px) {
    .plan-grid { grid-template-columns: repeat(2, minmax(0, 1fr)); }.plan-grid-single { grid-template-columns: minmax(0, 1fr); }
    .pricing-detail-layout { grid-template-columns: 180px minmax(0, 1fr); gap: 16px; }.pricing-detail-single { grid-template-columns: minmax(0, 1fr); }
    .plan-detail-card { padding: 22px; }.detail-features ul { grid-template-columns: minmax(0, 1fr); }
    .pricing-service-card { flex-wrap: wrap; }
}
@media (max-width: 767px) {
    .pricing-main { padding: 90px 16px 36px; }.pricing-heading { text-align: start; margin-bottom: 20px; }.pricing-heading h1 { font-size: 29px; }.pricing-heading > p:last-child { font-size: 14px; }
    .audience-picker { justify-content: start; flex-wrap: nowrap; overflow-x: auto; scrollbar-width: none; margin-bottom: 20px; padding: 3px 3px 10px; }.audience-picker button { flex-shrink: 0; min-height: 44px; padding: 9px 14px; }
    .audience-picker::-webkit-scrollbar, .detail-plan-picker > div::-webkit-scrollbar { display: none; }
    .pricing-toolbar { gap: 10px; }.pricing-toolbar h2 { font-size: 17px; }.pricing-view-switch button { padding: 8px 10px; font-size: 12px; }
    .plan-grid { grid-template-columns: minmax(0, 1fr); max-width: 440px; margin-inline: auto; gap: 16px; }.plan-card { padding: 22px; }
    .pricing-detail-layout { grid-template-columns: minmax(0, 1fr); gap: 16px; }
    .detail-plan-picker > p { display: none; }.detail-plan-picker > div { display: flex; overflow-x: auto; scrollbar-width: none; padding: 3px 3px 9px; }.detail-plan-picker button { flex: 0 0 auto; min-width: 155px; padding: 11px 13px; }
    .plan-detail-card { padding: 20px; }.plan-detail-header { gap: 12px; }.plan-detail-header h3 { font-size: 21px; }.detail-price strong { font-size: 22px; }
    .detail-search { margin-top: 18px; }.detail-features, .detail-notes { margin-top: 20px; }
    .payment-options { grid-template-columns: minmax(0, 1fr); }
}
@media (max-width: 380px) { .pricing-toolbar { align-items: start; flex-direction: column; }.plan-detail-header { flex-direction: column; }.detail-price { text-align: start; } }
@media (prefers-reduced-motion: no-preference) { .audience-picker button, .pricing-primary-action { transition: background-color .15s, color .15s, opacity .15s; } }
</style>
