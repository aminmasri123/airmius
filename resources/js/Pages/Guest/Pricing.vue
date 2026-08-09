<script setup>
import { computed, ref } from 'vue'
import { Link, router, usePage } from '@inertiajs/vue3'
import axios from 'axios'
import Nav from '@/Components/Guest/Nav.vue'
import Subnav from '@/Components/Guest/Subnav.vue'
import Footer from '@/Components/Guest/Footer.vue'
import SeoHead from '@/Components/Guest/SeoHead.vue'
import { useI18n } from 'vue-i18n'

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
        icon: 'las la-running',
        title: 'Kostenlos starten, sportlich sichtbar werden.',
        description: 'Sportler bleiben im Kern kostenlos. Pro-Funktionen kommen später für Portfolio, Statistiken und mehr Sichtbarkeit.',
    },
    {
        key: 'trainer',
        label: 'Trainer',
        icon: 'las la-chalkboard-teacher',
        title: 'Training organisieren, Teams begleiten, Wissen verkaufen.',
        description: 'Trainer arbeiten kostenlos im Vereinsplan. Solo-Trainer bekommen später eigene Gruppen, Camps und Kursverkauf.',
    },
    {
        key: 'verein',
        label: 'Vereine',
        icon: 'las la-users',
        title: 'Vereinsverwaltung, Community und Kommunikation in einem System.',
        description: 'Die Vereinspläne sind der Hauptumsatz: Mitglieder, Teams, Beiträge, Rechnungen, Dateien und Sponsoren.',
    },
    {
        key: 'eltern',
        label: 'Eltern',
        icon: 'las la-shield-alt',
        title: 'Sicherheit für Minderjährige bleibt kostenlos.',
        description: 'Elternzustimmung, Widerruf und Kinderüberblick sind Vertrauensfunktionen und werden nicht separat monetarisiert.',
    },
    {
        key: 'sponsor',
        label: 'Sponsoren',
        icon: 'las la-bullhorn',
        title: 'Regionale Sichtbarkeit im Sportumfeld.',
        description: 'Sponsorprofile, Kampagnen und lokale Anzeigen kommen als B2B-Umsatzsäule, sobald genug Aktivität vorhanden ist.',
    },
    {
        key: 'anbieter',
        label: 'Anbieter',
        icon: 'las la-store',
        title: 'Marketplace für Produkte, Kurse und Services.',
        description: 'Anbieter können später Produkte, Camps, Kurse und Dienstleistungen rund um Sport verkaufen.',
    },
    {
        key: 'werbeagentur',
        label: 'Werbeagentur',
        icon: 'las la-laptop-code',
        title: 'Websites, Kampagnen und digitale Sichtbarkeit für Vereine.',
        description: 'Airmius bietet Vereinen Website-Erstellung, Landingpages und digitale Kampagnen als Angebotsservice nach Anfrage.',
    },
    {
        key: 'enterprise',
        label: 'Enterprise',
        icon: 'las la-network-wired',
        title: 'Individuelle Lösungen für Verbände und große Organisationen.',
        description: 'Enterprise ist für Mandantenfähigkeit, Migration, Schnittstellen, Schulung und SLA gedacht.',
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
    return te(key) ? t(key, params) : key
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

const planPill = (label, tone = 'neutral') => ({ label, tone })

const audienceDecisionMatrix = {
    sportler: [
        { area: 'Training dokumentieren', free: planPill('frei'), premium: planPill('Pro nur für KI/Analyse', 'pro'), note: 'Dokumentation soll nicht blockiert werden.' },
        { area: 'Sportkarte & Tracking', free: planPill('frei'), premium: planPill('mehr Route-Limit', 'pro'), note: 'OSM-Karte und GPS bleiben Basisfunktion.' },
        { area: 'Routen generieren', free: planPill('10/Monat'), premium: planPill('150/Monat', 'pro'), note: 'Externe Routingkosten bleiben kontrollierbar.' },
        { area: 'KI-Bildanalyse Ernährung', free: planPill('nicht enthalten', 'locked'), premium: planPill('Sportler Pro', 'pro'), note: 'Bild-KI kostet Anbieter-Geld und braucht klare Zustimmung.' },
        { area: 'KI-Trainingspläne', free: planPill('kurz limitiert'), premium: planPill('länger + mehr Versuche', 'pro'), note: 'Free testet, Pro bekommt ernsthafte Planung.' },
    ],
    trainer: [
        { area: 'Im Verein trainieren', free: planPill('frei im Vereinsplan'), premium: planPill('Club/Pro für Verein', 'pro'), note: 'Trainer sollen innerhalb eines Vereins nicht extra bezahlen müssen.' },
        { area: 'Eigene Gruppen', free: planPill('nicht enthalten', 'locked'), premium: planPill('Trainer Pro', 'pro'), note: 'Eigene Kundengruppen sind ein eigener Business-Use-Case.' },
        { area: 'KI-Planung & Regelprüfung', free: planPill('nicht enthalten', 'locked'), premium: planPill('Trainer Pro / Vereins-Pro', 'pro'), note: 'Mehr Verantwortung, KI-Kosten und Prüfpflicht.' },
        { area: 'Kurse & Camps', free: planPill('später ansehen'), premium: planPill('Trainer Pro', 'pro'), note: 'Verkauf und Teilnehmerverwaltung gehören ins Premium-Paket.' },
    ],
    verein: [
        { area: 'Starten mit Verein', free: planPill('Free: 1 Team / 25 Mitglieder'), premium: planPill('Starter für Wachstum', 'pro'), note: 'Kleine Vereine können risikofrei testen.' },
        { area: 'Mitglieder aufnehmen', free: planPill('limitiert'), premium: planPill('Starter', 'pro'), note: 'Onboarding, Import und saubere Stammdaten sparen echte Büroarbeit.' },
        { area: 'Rechnungen & Zahlungen', free: planPill('nicht enthalten', 'locked'), premium: planPill('Starter / Pro', 'pro'), note: 'Finanzen brauchen Verlässlichkeit, Protokollierung und Support.' },
        { area: 'Trainer- & Vereins-Cockpit', free: planPill('Basis'), premium: planPill('Club', 'pro'), note: 'Operative Steuerung ist der Kernnutzen für zahlende Vereine.' },
        { area: 'SEPA, DATEV, Bankabgleich', free: planPill('nicht enthalten', 'locked'), premium: planPill('Pro', 'pro'), note: 'Buchhaltung ist ein klarer professioneller Mehrwert.' },
        { area: 'API, eigene Regeln, Audit', free: planPill('nicht enthalten', 'locked'), premium: planPill('Elite', 'pro'), note: 'Das braucht Sonderlogik, Sicherheit und Begleitung.' },
    ],
    eltern: [
        { area: 'Kinderübersicht', free: planPill('frei'), premium: planPill('kein Eltern-Premium geplant'), note: 'Kinderschutz und Zustimmung bleiben Vertrauensbasis.' },
        { area: 'Vereinsinfos & Termine', free: planPill('frei über Verein'), premium: planPill('abhängig vom Verein', 'pro'), note: 'Eltern zahlen nicht doppelt für Vereinsorganisation.' },
    ],
    sponsor: [
        { area: 'Sponsorprofil', free: planPill('nicht öffentlich buchbar'), premium: planPill('Sponsor Local', 'pro'), note: 'B2B-Sichtbarkeit wird als eigenes Angebot verkauft.' },
        { area: 'Reporting & Kampagnen', free: planPill('nicht enthalten', 'locked'), premium: planPill('Sponsor Pro später', 'pro'), note: 'Messung und Laufzeiten brauchen Betreuung.' },
    ],
    anbieter: [
        { area: 'Produkte & Services', free: planPill('Marketplace-Basis'), premium: planPill('Provision/Add-on', 'pro'), note: 'Airmius verdient dort, wo Umsatz entsteht.' },
        { area: 'Standorte & Abholung', free: planPill('Basis'), premium: planPill('Premium-Sichtbarkeit später', 'pro'), note: 'Echte Filialen und Abholstationen stärken Vertrauen.' },
    ],
}

const currentDecisionRows = computed(() => audienceDecisionMatrix[selectedAudience.value] || [])
const decisionPillClass = (tone) => ({
    pro: 'border-air-blue/40 bg-air-blue/10 text-air-blue',
    locked: 'border-warning/40 bg-warning/10 text-warning',
    neutral: 'border-border bg-bg text-primary',
})[tone || 'neutral']

const accessRules = {
    sportler: [
        { feature: 'Persönliches Dashboard, Feed, Chat, Dateien 1 GB', plan: 'Free', reason: 'Soll Einstieg und tägliche Nutzung nicht blockieren.' },
        { feature: 'Sportkarte, Tracking, Sportplätze, 10 automatische Routenvorschläge/Monat', plan: 'Free', reason: 'Basis-Bewegung und Orte sollen kostenlos bleiben; externe Routingkosten bleiben begrenzt.' },
        { feature: 'KI-Ernährungsbild, Trainings-KI kurz, Profilportfolio', plan: 'Sportler Pro', reason: 'Hat externe Kosten und persönlichen Premium-Nutzen.' },
        { feature: 'Erweiterte Analyse, Sichtbarkeit, Bewerbungsmappe, bis 150 Routenvorschläge/Monat', plan: 'Sportler Pro', reason: 'Mehrwert für ambitionierte Sportler und kontrollierte Providerkosten.' },
    ],
    trainer: [
        { feature: 'Trainer-Cockpit im Verein, Teamkommunikation, Training dokumentieren', plan: 'Free im Vereinsplan', reason: 'Trainer sollen im Verein ohne Extra-Hürde arbeiten.' },
        { feature: 'Eigene Trainingsgruppen, Vorlagen, Kurse, Camps', plan: 'Trainer Pro', reason: 'Solo-Trainer erzeugen eigenen Verwaltungs- und Umsatznutzen.' },
        { feature: 'KI-Planung, Regelprüfung, Belastungswarnungen', plan: 'Trainer Pro / Vereins-Pro', reason: 'Kostet KI und braucht höhere Verantwortung.' },
    ],
    verein: [
        { feature: 'Vereinsprofil, 1 Team, Basis-Mitglieder, Basis-Chat', plan: 'Free', reason: 'Kleine Vereine können starten und Airmius testen.' },
        { feature: 'Mitglieder-Onboarding, Import, Rechnungen, Zahlungshistorie', plan: 'Starter', reason: 'Echte Verwaltung spart Arbeit und rechtfertigt Abo.' },
        { feature: 'Vereins-Cockpit, QR-Anwesenheit, Sponsoren, Mahnungen, Sportorte/Standorte', plan: 'Club', reason: 'Das sind operative Vereinsfunktionen mit dauerhaftem Nutzen.' },
        { feature: 'Saisonplanung, Belastungssteuerung, SEPA, DATEV, Bankabgleich', plan: 'Pro', reason: 'Professionelle Verwaltung und Analyse für größere Vereine.' },
        { feature: 'API, Multi-Standort, Audit, eigene Regeln', plan: 'Elite', reason: 'Braucht Support, Sicherheit und Sonderlogik.' },
    ],
    eltern: [
        { feature: 'Kinderüberblick, Zustimmung, Widerruf, Sicherheit', plan: 'Free', reason: 'Kinderschutz und Vertrauen dürfen nicht hinter Paywall liegen.' },
        { feature: 'Zahlungsübersicht, Termine, Vereinsinfos', plan: 'Free / Vereinsplan', reason: 'Hängt vom Verein ab, nicht vom Eltern-Abo.' },
    ],
    sponsor: [
        { feature: 'Sponsorprofil, Vereinsplatzierungen, Kampagnen', plan: 'Sponsor Local', reason: 'B2B-Sichtbarkeit ist ein eigener Umsatzbereich.' },
        { feature: 'Reporting, Laufzeiten, Zielgruppen, Conversion', plan: 'Sponsor Pro später', reason: 'Erweiterte Werbung braucht Messung und Support.' },
    ],
    anbieter: [
        { feature: 'Marketplace-Profil, Produkte, Standorte, Abholung', plan: 'Anbieter Marketplace', reason: 'Anbieter nutzen Airmius kommerziell.' },
        { feature: 'Premium-Platzierungen, Kampagnen, Auszahlungen', plan: 'Provision / Add-on', reason: 'Kosten entstehen durch Reichweite, Zahlung und Support.' },
    ],
    werbeagentur: [
        { feature: 'Vereinswebsite, Landingpages, Kampagnen', plan: 'Angebot', reason: 'Umfang ist individuell und wird nicht pauschal bepreist.' },
        { feature: 'SEO, Texte, Sponsorenbereiche, rechtliche Grundstruktur', plan: 'Angebot', reason: 'Projektarbeit mit Abnahme statt Standard-Abo.' },
    ],
    enterprise: [
        { feature: 'Mandanten, Migration, Schnittstellen, SLA, AVV', plan: 'Enterprise', reason: 'Braucht Vertrag, Support und technische Begleitung.' },
        { feature: 'Eigene Regeln, API, Audit, Datenexporte', plan: 'Enterprise', reason: 'Für Verbände und große Organisationen.' },
    ],
}

const roadmap = [
    { phase: '1', title: 'Vereins-Cockpit', plan: 'Club', text: 'Offene Anfragen, Rechnungen, SEPA-Lücken, Events, Speicher, Aufgaben und Schnellaktionen.' },
    { phase: '2', title: 'Trainer-Cockpit', plan: 'Free im Verein / Trainer Pro', text: 'Heute anstehende Einheiten, offene Feedbacks, Teamform, Verletzungen und schnelle Dokumentation.' },
    { phase: '3', title: 'QR-Anwesenheit', plan: 'Club', text: 'Check-in für Trainings und Events, manuelle Korrektur, Abwesenheitsgründe und Auswertung.' },
    { phase: '4', title: 'Übungsbibliothek', plan: 'Free lesen / Pro verwalten', text: 'Airmius-Übungen, eigene Vereinsübungen, Medien, Ziele, Equipment und Risiken.' },
    { phase: '5', title: 'Mitglieder-Onboarding', plan: 'Starter', text: 'Schrittweise Aufnahme mit Team, Beitrag, Einwilligungen, Elternlogik und Einladung.' },
    { phase: '6', title: 'Saisonplanung', plan: 'Pro', text: 'Saisonphasen, Wochen, Ziele, Tests, Spiele/Wettkämpfe und Planfortschritt.' },
    { phase: '7', title: 'Belastung & Risiko', plan: 'Pro', text: 'Umfang, Intensität, Regeneration, Verletzungswarnungen und klare Empfehlungen.' },
    { phase: '8', title: 'KI + Airmius-Regeln', plan: 'Sportler Pro / Trainer Pro / Club Pro', text: 'KI erstellt, Airmius prüft Umfang, Pace, Regeneration, Risiko und Nachvollziehbarkeit.' },
    { phase: '9', title: 'Verwaltung Plus', plan: 'Pro / Elite', text: 'Material, Hallen, Helfer, Medienrechte, Sponsoren-CRM, Vereinsberichte und Exporte.' },
]

const currentAccessRules = computed(() => accessRules[selectedAudience.value] || [])

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
        maximumFractionDigits: 0,
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
        clubId: plan.target_actor === 'verein' ? (props.checkoutClubs[0]?.id || '') : '',
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
            <section class="mx-auto max-w-6xl text-center">
                <p class="text-sm font-semibold uppercase tracking-wider text-air-blue">{{ tx('Preise') }}</p>
                <h1 class="mx-auto mt-3 max-w-4xl font-heading text-4xl font-900 leading-tight sm:text-5xl">
                    {{ tx('Ein Preismodell für alle, die Sport organisieren, erleben oder unterstützen.') }}
                </h1>
                <p class="mx-auto mt-5 max-w-2xl text-lg leading-relaxed text-secondary">
                    {{ tx('Sportler und Eltern starten kostenlos. Vereine zahlen fair nach Größe. Trainer, Sponsoren und Anbieter bekommen eigene Wege, wenn ihre Funktionen wachsen.') }}
                </p>
            </section>

            <section class="mx-auto mt-10 max-w-7xl">
                <div class="flex gap-2 overflow-x-auto rounded-lg border border-border bg-card p-2">
                    <button
                        v-for="audience in audiences"
                        :key="audience.key"
                        type="button"
                        class="flex shrink-0 items-center gap-2 rounded-md px-4 py-2 text-sm font-semibold transition"
                        :class="selectedAudience === audience.key ? 'bg-buttonPrimary text-buttonTextPrimary' : 'text-secondary hover:bg-muted hover:text-primary'"
                        @click="selectedAudience = audience.key"
                    >
                        <i :class="audience.icon"></i>
                        <span>{{ tx(audience.label) }}</span>
                    </button>
                </div>
            </section>

            <section v-if="currentDecisionRows.length" class="mx-auto mt-6 max-w-7xl rounded-lg border border-border bg-card p-5">
                <div class="flex flex-col gap-2 sm:flex-row sm:items-end sm:justify-between">
                    <div>
                        <p class="text-xs font-semibold uppercase tracking-wide text-air-blue">{{ tx('Abo-Entscheidung') }}</p>
                        <h2 class="mt-1 text-2xl font-bold text-primary">{{ tx('Was bleibt frei, was gehört in Premium?') }}</h2>
                    </div>
                    <p class="max-w-2xl text-sm leading-6 text-secondary">
                        {{ tx('Die Tabelle macht die Produktlogik sichtbar: Basisnutzung bleibt niedrigschwellig, kostenintensive oder professionelle Funktionen werden begrenzt oder bezahlt.') }}
                    </p>
                </div>

                <div class="mt-5 overflow-hidden rounded-lg border border-border">
                    <div class="hidden grid-cols-[1.1fr_0.8fr_0.8fr_1.4fr] gap-0 border-b border-border bg-bg px-4 py-3 text-xs font-semibold uppercase tracking-wide text-secondary md:grid">
                        <span>{{ tx('Funktion') }}</span>
                        <span>{{ tx('Free/Basis') }}</span>
                        <span>{{ tx('Premium') }}</span>
                        <span>{{ tx('Warum?') }}</span>
                    </div>
                    <div
                        v-for="row in currentDecisionRows"
                        :key="row.area"
                        class="grid gap-3 border-b border-border px-4 py-4 text-sm last:border-b-0 md:grid-cols-[1.1fr_0.8fr_0.8fr_1.4fr] md:items-center"
                    >
                        <div>
                            <p class="font-semibold text-primary">{{ tx(row.area) }}</p>
                        </div>
                        <span class="inline-flex w-fit rounded-full border px-2.5 py-1 text-xs font-semibold" :class="decisionPillClass(row.free.tone)">
                            {{ tx(row.free.label) }}
                        </span>
                        <span class="inline-flex w-fit rounded-full border px-2.5 py-1 text-xs font-semibold" :class="decisionPillClass(row.premium.tone)">
                            {{ tx(row.premium.label) }}
                        </span>
                        <p class="text-sm leading-6 text-secondary">{{ tx(row.note) }}</p>
                    </div>
                </div>
            </section>

            <section class="mx-auto mt-8 max-w-7xl">
                <div class="grid gap-6 lg:grid-cols-[0.75fr_1.25fr]">
                    <aside class="rounded-lg border border-border bg-card p-6">
                        <p class="text-xs font-semibold uppercase tracking-wide text-air-blue">{{ tx(currentAudience.label) }}</p>
                        <h2 class="mt-2 text-2xl font-bold text-primary">{{ tx(currentAudience.title) }}</h2>
                        <p class="mt-3 text-sm leading-relaxed text-secondary">{{ tx(currentAudience.description) }}</p>
                        <div class="mt-6 rounded-lg border border-border bg-bg p-4 text-sm text-secondary">
                            <p class="font-semibold text-primary">{{ tx('Produktregel') }}</p>
                            <p class="mt-2">
                                {{ tx('Airmius bleibt für Sportler und Eltern niedrigschwellig. Bezahlt wird dort, wo echte Verwaltung, Reichweite, Support oder Umsatz entsteht.') }}
                            </p>
                        </div>
                        <div v-if="currentAccessRules.length" class="mt-4 rounded-lg border border-air-blue/30 bg-air-blue/10 p-4 text-sm">
                            <p class="font-semibold text-primary">{{ tx('Free oder Premium?') }}</p>
                            <p class="mt-2 text-secondary">
                                {{ tx('Basis bleibt frei. Premium beginnt dort, wo Airmius Verwaltung automatisiert, externe Kosten erzeugt oder professionellen Support braucht.') }}
                            </p>
                        </div>
                        <div v-if="selectedAudience === 'werbeagentur'" class="mt-4 rounded-lg border border-air-blue/40 bg-air-blue/10 p-4 text-sm text-secondary">
                            <p class="font-semibold text-primary">{{ tx('Website-Service') }}</p>
                            <p class="mt-2">
                                {{ tx('Der Preis hängt vom Umfang ab. Vereine stellen zuerst eine Anfrage und erhalten danach ein klares Angebot.') }}
                            </p>
                            <Link :href="route('guest.werbeagentur')" class="mt-3 inline-flex rounded-lg bg-buttonPrimary px-4 py-2 text-sm font-semibold text-buttonTextPrimary">
                                {{ tx('Werbeagentur ansehen') }}
                            </Link>
                        </div>
                        <div class="mt-4 rounded-lg border border-border bg-bg p-4">
                            <label class="text-xs font-semibold uppercase text-secondary">{{ tx('Rabattcode') }}</label>
                            <input
                                v-model="couponCode"
                                type="text"
                                class="mt-2 w-full rounded-lg border-border bg-inputBg text-sm uppercase text-primary"
                                :aria-label="tx('Rabattcode')"
                                :placeholder="tx('z. B. BETA50')"
                            >
                            <p class="mt-2 text-xs text-secondary">{{ tx('Der Code wird beim Bezahlen automatisch berücksichtigt.') }}</p>
                        </div>
                        <div class="mt-4 rounded-lg border border-border bg-bg p-4 text-sm text-secondary">
                            <p class="font-semibold text-primary">{{ tx('Land & Währung') }}</p>
                            <p class="mt-2">
                                {{ tx('Preise für {country} erkannt', { country: pricingCountry }) }}
                                <span class="text-xs">({{ pricingCountrySource }})</span>.
                            </p>
                        </div>
                    </aside>

                    <div class="grid gap-5 md:grid-cols-2 xl:grid-cols-3">
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

                            <ul class="mt-5 flex-1 space-y-2 text-sm text-secondary">
                                <li v-for="feature in plan.features" :key="feature" class="flex gap-2">
                                    <i class="las la-check mt-0.5 text-air-green"></i>
                                    <span>{{ tx(feature) }}</span>
                                </li>
                            </ul>

                            <div class="mt-6 grid gap-2">
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
                </div>
            </section>

            <section v-if="currentAccessRules.length" class="mx-auto mt-10 max-w-7xl rounded-lg border border-border bg-card p-5">
                <div class="flex flex-col gap-2 sm:flex-row sm:items-end sm:justify-between">
                    <div>
                        <p class="text-xs font-semibold uppercase tracking-wide text-air-blue">{{ tx('Funktionslogik') }}</p>
                        <h2 class="mt-1 text-2xl font-bold text-primary">{{ tx('Was ist kostenlos, was gehört in Premium?') }}</h2>
                    </div>
                    <p class="max-w-2xl text-sm leading-6 text-secondary">
                        {{ tx('Diese Einordnung ist die Grundlage für die nächsten Module. So bleibt Airmius fair für kleine Nutzer, aber tragfähig für Vereine, Trainer und Anbieter.') }}
                    </p>
                </div>

                <div class="mt-5 grid gap-3 lg:grid-cols-3">
                    <article
                        v-for="rule in currentAccessRules"
                        :key="`${selectedAudience}-${rule.feature}`"
                        class="rounded-lg border border-border bg-bg p-4"
                    >
                        <span class="inline-flex rounded-full bg-air-blue/10 px-2 py-1 text-xs font-semibold text-air-blue">{{ tx(rule.plan) }}</span>
                        <h3 class="mt-3 text-base font-bold text-primary">{{ tx(rule.feature) }}</h3>
                        <p class="mt-2 text-sm leading-6 text-secondary">{{ tx(rule.reason) }}</p>
                    </article>
                </div>
            </section>

            <section class="mx-auto mt-10 max-w-7xl rounded-lg border border-border bg-card p-5">
                <div class="flex flex-col gap-2 sm:flex-row sm:items-end sm:justify-between">
                    <div>
                        <p class="text-xs font-semibold uppercase tracking-wide text-air-blue">{{ tx('Ausbauplan') }}</p>
                        <h2 class="mt-1 text-2xl font-bold text-primary">{{ tx('Reihenfolge für die fehlenden Vereins- und Trainerfunktionen') }}</h2>
                    </div>
                    <p class="max-w-2xl text-sm leading-6 text-secondary">
                        {{ tx('Die Reihenfolge ist bewusst produktorientiert: erst Cockpits und Anwesenheit, danach Planung, Analyse und schwere Verwaltungsfunktionen.') }}
                    </p>
                </div>

                <div class="mt-5 grid gap-3 md:grid-cols-2 xl:grid-cols-3">
                    <article
                        v-for="item in roadmap"
                        :key="item.phase"
                        class="rounded-lg border border-border bg-bg p-4"
                    >
                        <div class="flex items-start justify-between gap-3">
                            <span class="flex h-9 w-9 items-center justify-center rounded-lg bg-buttonPrimary text-sm font-bold text-buttonTextPrimary">{{ item.phase }}</span>
                            <span class="rounded-full bg-inputBg px-2 py-1 text-xs font-semibold text-secondary">{{ tx(item.plan) }}</span>
                        </div>
                        <h3 class="mt-4 text-lg font-bold text-primary">{{ tx(item.title) }}</h3>
                        <p class="mt-2 text-sm leading-6 text-secondary">{{ tx(item.text) }}</p>
                    </article>
                </div>
            </section>
        </main>

        <Teleport to="body">
            <div v-if="checkoutModal.open && checkoutModal.plan" class="fixed inset-0 z-[90] flex items-center justify-center bg-black/65 p-4 backdrop-blur-sm">
                <div class="w-full max-w-lg rounded-lg border border-border bg-card p-5 shadow-2xl">
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
