<script setup>
import { useForm } from '@inertiajs/vue3'
import { computed, nextTick, onMounted, ref } from 'vue'
import Nav from '@/Components/Guest/Nav.vue'
import Subnav from '@/Components/Guest/Subnav.vue'
import Footer from '@/Components/Guest/Footer.vue'
import SeoHead from '@/Components/Guest/SeoHead.vue'

const props = defineProps({
    canLogin: Boolean,
    canRegister: Boolean,
    laravelVersion: String,
    phpVersion: String,
})

const safeRoute = (name, fallback = '') => {
    try {
        return route().has(name) ? route(name) : fallback
    } catch (error) {
        return fallback
    }
}

onMounted(() => {
    const theme = localStorage.getItem('theme')
    if (theme) {
        document.documentElement.classList.remove('theme-air', 'theme-dark', 'theme-womanly', 'theme-champion', 'theme-sprint', 'theme-arena', 'theme-pulse', 'theme-trail', 'theme-bazaar')
        document.documentElement.classList.add(`theme-${theme}`)
    }

    const queryVariant = new URLSearchParams(window.location.search).get('hero_variant')
    const storedVariant = localStorage.getItem('airmius_hero_variant')
    const resolvedVariant = (queryVariant === 'A' || queryVariant === 'B')
        ? queryVariant
        : (storedVariant === 'A' || storedVariant === 'B')
            ? storedVariant
            : (Math.random() < 0.5 ? 'A' : 'B')

    heroVariant.value = resolvedVariant
    localStorage.setItem('airmius_hero_variant', resolvedVariant)
    trackLandingEvent('landing_view', { hero_variant: resolvedVariant, section: 'hero' })
})
// ========================
// STATE
// ========================
const activeTab = ref('sportler')
const trackLandingEvent = (eventName, payload = {}) => {
    if (typeof window === 'undefined') {
        return
    }

    if (window.dataLayer && typeof window.dataLayer.push === 'function') {
        window.dataLayer.push({ event: 'landing_event', event_name: eventName, ...payload })
        return
    }

    if (window.gtag && typeof window.gtag === 'function') {
        window.gtag('event', eventName, payload)
    }
}

const heroPrimaryCta = computed(() => {
    return props.canRegister ? safeRoute('register', '/register') : safeRoute('login', '/login')
})
const heroPrimaryCtaLabel = computed(() => (props.canRegister ? heroCopy.value.primaryCta : 'Anmelden'))
const canonicalUrl = computed(() => typeof window !== 'undefined' ? `${window.location.origin}/` : '/')
const pageSchema = computed(() => {
    const applicationSchema = {
        '@type': 'SoftwareApplication',
        name: 'Airmius',
        description: 'Plattform für Sportvereine, Teams und Sportler: Organisation, Kommunikation und Vereinsverwaltung an einem Ort.',
        applicationCategory: 'BusinessApplication',
        operatingSystem: 'Web',
        url: typeof window !== 'undefined' ? window.location.origin : undefined,
    }

    const organizationSchema = {
        '@type': 'Organization',
        name: 'Airmius',
        url: typeof window !== 'undefined' ? `${window.location.origin}/` : undefined,
        logo: typeof window !== 'undefined' ? `${window.location.origin}/img/logo/Logo-Airmius-Quervormat.png` : undefined,
    }

    const faqSchema = {
        '@type': 'FAQPage',
        mainEntity: faqItems.map((faq) => ({
            '@type': 'Question',
            name: faq.question,
            acceptedAnswer: {
                '@type': 'Answer',
                text: faq.answer,
            },
        })),
    }

    return {
        '@context': 'https://schema.org',
        '@graph': [applicationSchema, organizationSchema, faqSchema],
    }
})
const contactSubmitRoute = computed(() => safeRoute('contact.store', safeRoute('kontakt.store', '/kontakt-und-melden')))

const tabs = [
    { key: 'sportler', label: 'Sportler' },
    { key: 'trainer', label: 'Trainer' },
    { key: 'vereine', label: 'Vereine' },
]

const heroTrustItems = [
    {
        icon: 'las la-bolt',
        title: 'Schnell starten',
        text: 'Ohne Kreditkarte testen',
    },
    {
        icon: 'las la-shield-alt',
        title: 'DSGVO-konform',
        text: 'Rollen und Rechte im Griff',
    },
    {
        icon: 'las la-layer-group',
        title: 'Alles zentral',
        text: 'Chat, Termine, Teams',
    },
]

const proofPoints = [
    {
        value: '1 App',
        label: 'statt WhatsApp, Excel und E-Mail',
    },
    {
        value: '3 Rollen',
        label: 'Sportler, Trainer und Vereine',
    },
    {
        value: '0 Chaos',
        label: 'durch klare Kommunikation',
    },
]

// Inertia Form für Validation + Loading State
const form = useForm({
    name: '',
    email: '',
    message: ''
})
const heroVariant = ref('A')
const heroCopy = computed(() => {
    if (heroVariant.value === 'B') {
        return {
            badge: 'Jetzt 14 Tage kostenlos testen',
            title: 'Sport-Organisation ohne Chaos',
            highlight: 'dein Team',
            subtitle: 'Alles für Sportler, Teams und Vereine in einer App.',
            primaryCta: 'Jetzt starten',
            secondaryCta: 'Funktionen sehen',
        }
    }

    return {
        badge: 'Jetzt in der Beta - Kostenlos starten',
        title: 'Das soziale Netzwerk für deinen',
        highlight: 'Sport',
        subtitle: 'Für Sportler, Teams und Vereine. Organisation, Kommunikation und Vernetzung – vereint in einer App.',
        primaryCta: 'Jetzt kostenlos starten',
        secondaryCta: 'Vorteile entdecken',
    }
})
const nameInputRef = ref(null)
const emailInputRef = ref(null)
const messageInputRef = ref(null)
const formSuccess = ref(false)

const faqItems = [
    {
        question: 'Brauche ich technische Vorkenntnisse, um Airmius zu nutzen?',
        answer: 'Nein. Airmius ist für Trainer, Spieler und Vereinsverantwortliche gebaut und mit gewohnten Bedienmustern wie Chat, Kalender und Listen intuitiv nutzbar.',
    },
    {
        question: 'Kann ich den Wechsel von WhatsApp und Excel einfach starten?',
        answer: 'Ja. Viele Prozesse lassen sich direkt übernehmen: Trainingstermine, Teilnahmelisten, Teamstrukturen und Nachrichten, damit nichts verloren geht.',
    },
    {
        question: 'Was kostet der Einstieg?',
        answer: 'Der Einstieg ist schnell möglich. Wir helfen dir dabei, den passenden Paketumfang passend zur Teamgröße zu finden.',
    },
    {
        question: 'Wie ist der Datenschutz geregelt?',
        answer: 'Die Plattform legt Wert auf Rollensteuerung, Datensicherheit und transparente Verwaltung durch zentrale Berechtigungen.',
    },
]

// ========================
// METHODS
// ========================

const switchTab = (tab) => {
    activeTab.value = tab
    trackLandingEvent('audience_tab_click', {
        hero_variant: heroVariant.value,
        audience: tab,
    })
}

const onTabKeydown = (event) => {
    const currentIndex = tabs.findIndex((tab) => tab.key === activeTab.value)
    if (currentIndex === -1) {
        return
    }

    const key = event.key
    if (!['ArrowLeft', 'ArrowRight', 'Home', 'End'].includes(key)) {
        return
    }

    event.preventDefault()

    let nextIndex = currentIndex
    if (key === 'ArrowRight') {
        nextIndex = (currentIndex + 1) % tabs.length
    } else if (key === 'ArrowLeft') {
        nextIndex = (currentIndex - 1 + tabs.length) % tabs.length
    } else if (key === 'Home') {
        nextIndex = 0
    } else if (key === 'End') {
        nextIndex = tabs.length - 1
    }

    activeTab.value = tabs[nextIndex].key
    nextTick(() => {
        document.getElementById(`tab-${tabs[nextIndex].key}`)?.focus()
    })
}

const scrollTo = (id) => {
    const el = document.getElementById(id)
    if (!el) return

    const offset = 80
    const elementPosition = el.getBoundingClientRect().top + window.pageYOffset

    window.scrollTo({
        top: elementPosition - offset,
        behavior: window.matchMedia('(prefers-reduced-motion: reduce)').matches ? 'auto' : 'smooth',
    })
}

const validateContactForm = () => {
    form.clearErrors()
    const trimmedName = form.name.trim()
    const trimmedEmail = form.email.trim()
    const trimmedMessage = form.message.trim()
    const emailPattern = /^[^\s@]+@[^\s@]+\.[^\s@]+$/

    if (!trimmedName) {
        form.setError('name', 'Bitte gib deinen Namen ein.')
    }

    if (!trimmedEmail) {
        form.setError('email', 'Bitte gib deine E-Mail-Adresse ein.')
    } else if (!emailPattern.test(trimmedEmail)) {
        form.setError('email', 'Bitte gib eine gültige E-Mail-Adresse ein.')
    }

    if (!trimmedMessage) {
        form.setError('message', 'Bitte schreibe uns mindestens eine kurze Nachricht.')
    } else if (trimmedMessage.length < 8) {
        form.setError('message', 'Bitte formuliere deine Nachricht etwas ausführlicher.')
    }

    if (Object.keys(form.errors).length > 0) {
        if (form.errors.name && nameInputRef.value) {
            nextTick(() => nameInputRef.value.focus())
        } else if (form.errors.email && emailInputRef.value) {
            nextTick(() => emailInputRef.value.focus())
        } else if (form.errors.message && messageInputRef.value) {
            nextTick(() => messageInputRef.value.focus())
        }
        trackLandingEvent('contact_form_validation_error', {
            hero_variant: heroVariant.value,
            errors: Object.keys(form.errors).join(','),
        })
        return false
    }

    return true
}

const clearFieldError = (field) => {
    if (!form.errors[field]) {
        return
    }

    if (typeof form.clearErrors === 'function') {
        form.clearErrors(field)
    }
}

const submitForm = () => {
    if (form.processing) {
        return
    }

    if (!validateContactForm()) {
        return
    }

    trackLandingEvent('contact_form_submit_attempt', {
        hero_variant: heroVariant.value,
        message_length: form.message.trim().length,
    })

    form.post(contactSubmitRoute.value, {
        preserveScroll: true,
        preserveState: true,
        onSuccess: () => {
            form.reset()
            form.clearErrors()
            formSuccess.value = true
            trackLandingEvent('contact_form_submit_success', {
                hero_variant: heroVariant.value,
            })
            setTimeout(() => formSuccess.value = false, 3000)
        },
        onError: () => {
            formSuccess.value = false
            trackLandingEvent('contact_form_submit_error', {
                hero_variant: heroVariant.value,
            })
        },
    })
}

const onHeroPrimaryCtaClick = () => {
    trackLandingEvent('hero_primary_cta_click', {
        hero_variant: heroVariant.value,
        location: 'hero',
        destination: heroPrimaryCta.value,
    })
}

const onHeroSecondaryCtaClick = () => {
    trackLandingEvent('hero_secondary_cta_click', {
        hero_variant: heroVariant.value,
        location: 'hero',
        target: 'vorteile',
    })
    scrollTo('vorteile')
}

const onBannerPrimaryCtaClick = () => {
    trackLandingEvent('cta_banner_primary_click', {
        hero_variant: heroVariant.value,
        location: 'cta_banner',
        destination: heroPrimaryCta.value,
    })
}

const onBannerSecondaryCtaClick = () => {
    trackLandingEvent('cta_banner_secondary_click', {
        hero_variant: heroVariant.value,
        location: 'cta_banner',
        target: 'funktionen',
    })
    scrollTo('funktionen')
}

</script>

<template>

    <SeoHead
        title="Airmius - Sportvereine, Teams und Sportler digital vernetzen"
        description="Airmius ist die Plattform für Sportler, Trainer, Teams und Vereine: Organisation, Kommunikation, Trainingsplanung und Vereinsverwaltung an einem Ort."
        :schema="pageSchema"
        :canonical="canonicalUrl"
    />
    <a href="#main-content" class="sr-only focus:not-sr-only focus:fixed focus:top-4 focus:left-4 focus:z-50 focus:bg-card focus:px-4 focus:py-2 focus:rounded-lg focus:border focus:border-border">
        Zum Seiteninhalt springen
    </a>
    <div id="app" class="w-full h-full bg-bg text-primary overflow-auto">
        <!-- NAV -->
        <Nav :canLogin="canLogin" :canRegister="canRegister" />

        <!-- SUB NAV -->
         <Subnav />

        <!-- MAIN CONTENT -->
        <main id="main-content">

        <!-- HERO -->
        <section id="hero" class="pt-16 pb-14 sm:pt-36 sm:pb-20 px-4 min-h-[calc(100dvh-5rem)] sm:min-h-[calc(100dvh-7rem)] flex items-center">
            <div class="max-w-7xl mx-auto flex flex-col lg:flex-row items-center gap-12 lg:gap-32">
                <div class="flex-1 text-center lg:text-left">
                    <div
                        class="anim-fade inline-flex items-center gap-2 bg-white/5 border border-white/10 rounded-full px-4 py-1.5 text-xs font-medium text-air-green mb-6">
                        <span class="pulse-dot bg-air-green"></span> {{ heroCopy.badge }}
                    </div>
                    <h1 id="hero-title"
                        class="anim-fade-d1 font-heading font-900 text-4xl sm:text-5xl lg:text-6xl leading-tight tracking-tight">
                        {{ heroCopy.title }} <br>
                        <span
                            class="bg-gradient-to-r from-air-blue via-air-green to-air-orange bg-clip-text text-transparent">{{ heroCopy.highlight }}</span>
                    </h1>
                    <p id="hero-subtitle"
                        class="anim-fade-d2 mt-5 text-gray-400 text-lg sm:text-xl max-w-xl mx-auto lg:mx-0 leading-relaxed">
                        {{ heroCopy.subtitle }}
                    </p>
                    <div class="anim-fade-d3 mt-8 flex flex-col sm:flex-row gap-3 justify-center lg:justify-start">
                        <a :href="heroPrimaryCta" :aria-label="heroPrimaryCtaLabel" @click="onHeroPrimaryCtaClick"
                            class="bg-air-blue hover:bg-blue-600 glow-blue text-white font-bold px-8 py-3.5 rounded-full text-center transition">
                            {{ heroPrimaryCtaLabel }}
                        </a>
                        <button type="button" @click="onHeroSecondaryCtaClick"
                            class="border border-white/15 hover:border-white/30 text-white font-semibold px-8 py-3.5 rounded-full text-center transition">
                            {{ heroCopy.secondaryCta }}
                        </button>
                    </div>
                    <div
                        class="anim-fade-d4 mt-8 flex flex-wrap items-center gap-4 justify-center lg:justify-start text-sm text-gray-500">
                        <span v-for="item in heroTrustItems" :key="item.title" class="flex items-center gap-1.5">
                            <i class="las la-check-circle text-air-green" aria-hidden="true"></i>{{ item.title }}
                        </span>
                    </div>
                    <div class="anim-fade-d4 mt-6 hidden sm:grid sm:grid-cols-3 gap-3 max-w-2xl mx-auto lg:mx-0">
                        <div v-for="item in heroTrustItems" :key="item.text"
                            class="rounded-2xl border border-white/10 bg-white/[0.03] p-4 text-left">
                            <i :class="[item.icon, 'text-air-green text-xl mb-2']" aria-hidden="true"></i>
                            <div class="font-heading font-700 text-sm text-white">{{ item.title }}</div>
                            <p class="mt-1 text-xs text-gray-500 leading-snug">{{ item.text }}</p>
                        </div>
                    </div>
                </div>





                <!-- Mockup: mobil ausblenden -->
                <div class="hidden lg:block flex-1 max-w-lg w-full">
                    <div class="mockup-screen float-loop">
                        <div class="flex items-center gap-2 mb-4">
                            <span class="w-3 h-3 rounded-full bg-red-500/80"></span>
                            <span class="w-3 h-3 rounded-full bg-yellow-500/80"></span>
                            <span class="w-3 h-3 rounded-full bg-green-500/80"></span>
                        </div>
                        <div class="space-y-3">
                            <div
                                class="card-item item-1 bg-air-blue/10 border border-air-blue/20 rounded-xl p-3 flex items-center gap-3">
                                <div class="w-10 h-10 rounded-lg bg-air-blue/20 flex items-center justify-center">
                                    <i class="las la-comment text-air-blue"></i>
                                </div>
                                <div>
                                    <div class="text-xs text-gray-400">Team Chat</div>
                                    <div class="text-sm font-medium">Training morgen um 18:00 OK</div>
                                </div>
                            </div>

                            <div
                                class="card-item item-2 bg-air-green/10 border border-air-green/20 rounded-xl p-3 flex items-center gap-3">
                                <div class="w-10 h-10 rounded-lg bg-air-green/20 flex items-center justify-center">
                                    <i class="las la-calendar text-air-green"></i>
                                </div>
                                <div>
                                    <div class="text-xs text-gray-400">Nächstes Spiel</div>
                                    <div class="text-sm font-medium">Sa, 15:30 – FC Muster vs. Sportfreunde</div>
                                </div>
                            </div>

                            <div
                                class="card-item item-3 bg-air-orange/10 border border-air-orange/20 rounded-xl p-3 flex items-center gap-3">
                                <div class="w-10 h-10 rounded-lg bg-air-orange/20 flex items-center justify-center">
                                    <i class="las la-chart-bar text-air-orange"></i>
                                </div>
                                <div>
                                    <div class="text-xs text-gray-400">Deine Statistik</div>
                                    <div class="text-sm font-medium">12 Trainings · 89% Anwesenheit</div>
                                </div>
                            </div>

                            <div class="flex gap-2 mt-2 card-item item-4">
                                <div class="flex-1 bg-white/5 rounded-lg p-2 text-center">
                                    <div class="text-2xl font-heading font-bold text-air-blue">24</div>
                                    <div class="text-[10px] text-gray-500">Spieler</div>
                                </div>
                                <div class="flex-1 bg-white/5 rounded-lg p-2 text-center">
                                    <div class="text-2xl font-heading font-bold text-air-green">18</div>
                                    <div class="text-[10px] text-gray-500">Zusagen</div>
                                </div>
                                <div class="flex-1 bg-white/5 rounded-lg p-2 text-center">
                                    <div class="text-2xl font-heading font-bold text-air-orange">3</div>
                                    <div class="text-[10px] text-gray-500">Absagen</div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </section>
        <!-- PROBLEM → LÖSUNG -->
        <section class="py-16 sm:py-24 px-4 border-t border-white/5 min-h-screen sm:h-dvh flex items-center">
            <div class="max-w-6xl mx-auto">
                <div class="text-center mb-14">
                    <h2 class="font-heading font-800 text-3xl sm:text-4xl">Statt <span class="text-red-400">5
                            Tools</span> nur <span class="text-air-green">eine Lösung</span></h2>
                    <p class="text-gray-400 mt-3 max-w-2xl mx-auto">WhatsApp, Excel, OneNote, E-Mail, Telefon?!</p>
                    <p class="text-gray-400 mt-2"> Schluss mit dem Chaos! AIRMIUS vereint alles.</p>
                </div>
                <div class="grid sm:grid-cols-3 gap-6">
                    <div class="grad-card rounded-2xl p-6 text-center">
                        <div class="w-14 h-14 mx-auto rounded-xl bg-red-500/10 flex items-center justify-center mb-4">
                            <i class="las la-comment-dots text-red-400 text-3xl"></i>
                        </div>
                        <h3 class="font-heading font-700 text-lg mb-2">Chaos bei Kommunikation</h3>
                        <p class="text-sm text-gray-400">Infos gehen in WhatsApp-Gruppen unter. Wichtige Nachrichten
                            werden übersehen.</p>
                        <div class="mt-4 pt-4 border-t border-white/5">
                            <span class="text-air-green text-sm font-semibold">→ Zentraler Team-Chat mit Struktur</span>
                        </div>
                    </div>

                    <div class="grad-card rounded-2xl p-6 text-center">
                        <div class="w-14 h-14 mx-auto rounded-xl bg-red-500/10 flex items-center justify-center mb-4">
                            <i class="las la-clock text-red-400 text-3xl"></i>
                        </div>
                        <h3 class="font-heading font-700 text-lg mb-2">Hoher Zeitaufwand</h3>
                        <p class="text-sm text-gray-400">Manuelle Listen, endlose Abfragen, Zettelwirtschaft. Zeit, die
                            im Training fehlt.</p>
                        <div class="mt-4 pt-4 border-t border-white/5">
                            <span class="text-air-green text-sm font-semibold">→ Automatisierte Prozesse</span>
                        </div>
                    </div>
                    <div class="grad-card rounded-2xl p-6 text-center">
                        <div class="w-14 h-14 mx-auto rounded-xl bg-red-500/10 flex items-center justify-center mb-4">
                            <i class="las la-eye-slash text-red-400 text-3xl"></i>
                        </div>
                        <h3 class="font-heading font-700 text-lg mb-2">Fehlende Übersicht</h3>
                        <p class="text-sm text-gray-400">Wer kommt? Wann ist Training? Wo stehen wir? Keiner weiß
                            Bescheid.</p>
                        <div class="mt-4 pt-4 border-t border-white/5">
                            <span class="text-air-green text-sm font-semibold">→ Echtzeit-Dashboard für alles</span>
                        </div>
                    </div>
                </div>
                <div class="mt-10 grid sm:grid-cols-3 gap-4">
                    <div v-for="point in proofPoints" :key="point.value"
                        class="rounded-2xl border border-white/10 bg-white/[0.03] px-5 py-4 text-center">
                        <div class="font-heading font-800 text-2xl text-white">{{ point.value }}</div>
                        <p class="mt-1 text-xs text-gray-500">{{ point.label }}</p>
                    </div>
                </div>
            </div>
        </section>

        <!-- VORTEILE -->
        <section id="vorteile" class="py-16 sm:py-24 px-4 border-t border-white/5">
            <div class="max-w-6xl mx-auto">
                <div class="text-center mb-8 sm:mb-10 px-2">
                    <span
                        class="text-air-blue text-xs sm:text-sm font-semibold uppercase tracking-wider">Vorteile</span>
                    <h2 class="font-heading font-800 text-2xl sm:text-3xl lg:text-4xl mt-2 leading-tight">
                        Eine App für <span
                            class="bg-gradient-to-r from-air-blue to-air-green bg-clip-text text-transparent">alle im
                            Sport</span>
                    </h2>
                    <p class="text-gray-400 mt-3 text-sm sm:text-base max-w-xl mx-auto">Egal ob Sportler, Trainer oder
                        Verein – AIRMIUS macht
                        deinen Alltag einfacher.</p>
                </div>

                <!-- Tabs: Mobil nur Icons, horizontal scroll -->
                <div class="mb-8 sm:mb-10 flex justify-center px-4">
                    <div class="inline-flex bg-white/5 rounded-full p-1 gap-1 flex-wrap justify-center" role="tablist"
                        aria-label="Zielgruppen">
                        <button v-for="tab in tabs" :key="tab.key" type="button" :id="`tab-${tab.key}`"
                            :aria-controls="`tabpanel-${tab.key}`" :aria-selected="activeTab === tab.key"
                            :tabindex="activeTab === tab.key ? 0 : -1" @click="switchTab(tab.key)"
                            @keydown="onTabKeydown" :class="[
                            'rounded-full px-4 sm:px-5 py-2.5 text-sm font-semibold transition-all flex items-center gap-2 whitespace-nowrap',
                            activeTab === tab.key ? 'tab-active' : 'text-gray-400 hover:text-white'
                        ]">
                            {{ tab.label }}
                        </button>
                    </div>
                </div>

                <!-- Sportler: 2 Spalten mobil -->
                <div v-show="activeTab === 'sportler'" id="tabpanel-sportler" role="tabpanel" aria-labelledby="tab-sportler"
                    class="grid grid-cols-2 sm:grid-cols-2 lg:grid-cols-4 gap-3 sm:gap-4">
                    <div class="benefit-card grad-card rounded-xl sm:rounded-2xl p-3 sm:p-5">
                        <div
                            class="w-8 h-8 sm:w-10 sm:h-10 rounded-lg bg-air-blue/15 flex items-center justify-center mb-2 sm:mb-3">
                            <i class="las la-grip-lines text-air-blue text-lg sm:text-xl"></i>
                        </div>
                        <h4 class="font-heading font-600 text-xs sm:text-sm mb-1 leading-tight">Alles an einem Ort</h4>
                        <p class="text-xs text-gray-400 leading-snug">Training, Spiele, Nachrichten – eine App
                            für alles.</p>
                    </div>

                    <div class="benefit-card grad-card rounded-xl sm:rounded-2xl p-3 sm:p-5">
                        <div
                            class="w-8 h-8 sm:w-10 sm:h-10 rounded-lg bg-air-blue/15 flex items-center justify-center mb-2 sm:mb-3">
                            <i class="las la-comment text-air-blue text-lg sm:text-xl"></i>
                        </div>
                        <h4 class="font-heading font-600 text-xs sm:text-sm mb-1 leading-tight">Kein WhatsApp-Chaos</h4>
                        <p class="text-xs text-gray-400 leading-snug">Strukturierte Kommunikation statt
                            endloser Gruppenflut.</p>
                    </div>

                    <div class="benefit-card grad-card rounded-xl sm:rounded-2xl p-3 sm:p-5">
                        <div
                            class="w-8 h-8 sm:w-10 sm:h-10 rounded-lg bg-air-blue/15 flex items-center justify-center mb-2 sm:mb-3">
                            <i class="las la-mouse-pointer text-air-blue text-lg sm:text-xl"></i>
                        </div>
                        <h4 class="font-heading font-600 text-xs sm:text-sm mb-1 leading-tight">Ein-Klick Zu-/Absage
                        </h4>
                        <p class="text-xs text-gray-400 leading-snug">Teilnahme bestätigen war noch nie so
                            einfach.</p>
                    </div>

                    <div class="benefit-card grad-card rounded-xl sm:rounded-2xl p-3 sm:p-5">
                        <div
                            class="w-8 h-8 sm:w-10 sm:h-10 rounded-lg bg-air-blue/15 flex items-center justify-center mb-2 sm:mb-3">
                            <i class="las la-heart text-air-blue text-lg sm:text-xl"></i>
                        </div>
                        <h4 class="font-heading font-600 text-xs sm:text-sm mb-1 leading-tight">Echtzeit-Übersicht</h4>
                        <p class="text-xs text-gray-400 leading-snug">Immer wissen, was wann wo stattfindet.
                        </p>
                    </div>

                    <div class="benefit-card grad-card rounded-xl sm:rounded-2xl p-3 sm:p-5">
                        <div
                            class="w-8 h-8 sm:w-10 sm:h-10 rounded-lg bg-air-blue/15 flex items-center justify-center mb-2 sm:mb-3">
                            <i class="las la-chart-bar text-air-blue text-lg sm:text-xl"></i>
                        </div>
                        <h4 class="font-heading font-600 text-xs sm:text-sm mb-1 leading-tight">Persönliche Statistiken
                        </h4>
                        <p class="text-xs text-gray-400 leading-snug">Dein Fortschritt auf einen Blick –
                            Motivation pur.</p>
                    </div>

                    <div class="benefit-card grad-card rounded-xl sm:rounded-2xl p-3 sm:p-5">
                        <div
                            class="w-8 h-8 sm:w-10 sm:h-10 rounded-lg bg-air-blue/15 flex items-center justify-center mb-2 sm:mb-3">
                            <i class="las la-history text-air-blue text-lg sm:text-xl"></i>
                        </div>
                        <h4 class="font-heading font-600 text-xs sm:text-sm mb-1 leading-tight">Trainingshistorie</h4>
                        <p class="text-xs text-gray-400 leading-snug">Alle vergangenen Einheiten jederzeit
                            einsehen.</p>
                    </div>

                    <div class="benefit-card grad-card rounded-xl sm:rounded-2xl p-3 sm:p-5">
                        <div
                            class="w-8 h-8 sm:w-10 sm:h-10 rounded-lg bg-air-blue/15 flex items-center justify-center mb-2 sm:mb-3">
                            <i class="las la-bolt text-air-blue text-lg sm:text-xl"></i>
                        </div>
                        <h4 class="font-heading font-600 text-xs sm:text-sm mb-1 leading-tight">Motivation steigern</h4>
                        <p class="text-xs text-gray-400 leading-snug">Sichtbarer Fortschritt = mehr Leistung.
                        </p>
                    </div>

                    <div class="benefit-card grad-card rounded-xl sm:rounded-2xl p-3 sm:p-5">
                        <div
                            class="w-8 h-8 sm:w-10 sm:h-10 rounded-lg bg-air-blue/15 flex items-center justify-center mb-2 sm:mb-3">
                            <i class="las la-mobile text-air-blue text-lg sm:text-xl"></i>
                        </div>
                        <h4 class="font-heading font-600 text-xs sm:text-sm mb-1 leading-tight">Überall verfügbar</h4>
                        <p class="text-xs text-gray-400 leading-snug">Smartphone, Tablet, Desktop – immer
                            dabei.</p>
                    </div>

                    <div class="benefit-card grad-card rounded-xl sm:rounded-2xl p-3 sm:p-5">
                        <div
                            class="w-8 h-8 sm:w-10 sm:h-10 rounded-lg bg-air-blue/15 flex items-center justify-center mb-2 sm:mb-3">
                            <i class="las la-car text-air-blue text-lg sm:text-xl"></i>
                        </div>
                        <h4 class="font-heading font-600 text-xs sm:text-sm mb-1 leading-tight">Fahrgemeinschaften</h4>
                        <p class="text-xs text-gray-400 leading-snug">Gemeinsam zu Training & Events fahren –
                            Kosten teilen.</p>
                    </div>

                    <div class="benefit-card grad-card rounded-xl sm:rounded-2xl p-3 sm:p-5">
                        <div
                            class="w-8 h-8 sm:w-10 sm:h-10 rounded-lg bg-air-blue/15 flex items-center justify-center mb-2 sm:mb-3">
                            <i class="las la-shopping-bag text-air-blue text-lg sm:text-xl"></i>
                        </div>
                        <h4 class="font-heading font-600 text-xs sm:text-sm mb-1 leading-tight">Einkaufsgemeinschaft
                        </h4>
                        <p class="text-xs text-gray-400 leading-snug">Bestellt euer Equipment gemeinsam zum
                            exklusiven Preis.</p>
                    </div>

                    <div class="benefit-card grad-card rounded-xl sm:rounded-2xl p-3 sm:p-5 col-span-2 sm:col-span-1">
                        <div
                            class="w-8 h-8 sm:w-10 sm:h-10 rounded-lg bg-air-blue/15 flex items-center justify-center mb-2 sm:mb-3">
                            <i class="las la-users text-air-blue text-lg sm:text-xl"></i>
                        </div>
                        <h4 class="font-heading font-600 text-xs sm:text-sm mb-1 leading-tight">Sport-Buddy finden</h4>
                        <p class="text-xs text-gray-400 leading-snug">Finde jederzeit Leute zum Laufen,
                            Trainieren oder Spielen.</p>
                    </div>
                </div>

                <!-- Trainer: 1 Spalte mobil -->
                <div v-show="activeTab === 'trainer'" id="tabpanel-trainer" role="tabpanel" aria-labelledby="tab-trainer"
                    class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-3 sm:gap-4">
                    <div class="benefit-card grad-card rounded-xl sm:rounded-2xl p-4 sm:p-5">
                        <div
                            class="w-8 h-8 sm:w-10 sm:h-10 rounded-lg bg-air-green/15 flex items-center justify-center mb-2 sm:mb-3">
                            <i class="las la-clock text-air-green text-lg sm:text-xl"></i>
                        </div>
                        <h4 class="font-heading font-600 text-sm mb-1">Massive Zeitersparnis</h4>
                        <p class="text-xs text-gray-400">Automatisiere Routineaufgaben und fokussiere dich aufs
                            Training.</p>
                    </div>
                    <div class="benefit-card grad-card rounded-xl sm:rounded-2xl p-4 sm:p-5">
                        <div
                            class="w-8 h-8 sm:w-10 sm:h-10 rounded-lg bg-air-green/15 flex items-center justify-center mb-2 sm:mb-3">
                            <i class="las la-tasks text-air-green text-lg sm:text-xl"></i>
                        </div>
                        <h4 class="font-heading font-600 text-sm mb-1">Keine manuellen Listen</h4>
                        <p class="text-xs text-gray-400">Schluss mit Excel-Tabellen und handgeschriebenen Listen.</p>
                    </div>
                    <div class="benefit-card grad-card rounded-xl sm:rounded-2xl p-4 sm:p-5">
                        <div
                            class="w-8 h-8 sm:w-10 sm:h-10 rounded-lg bg-air-green/15 flex items-center justify-center mb-2 sm:mb-3">
                            <i class="las la-user-check text-air-green text-lg sm:text-xl"></i>
                        </div>
                        <h4 class="font-heading font-600 text-sm mb-1">Auto-Anwesenheit</h4>
                        <p class="text-xs text-gray-400">Automatische Erfassung – wer war da, wer nicht.</p>
                    </div>
                    <div class="benefit-card grad-card rounded-xl sm:rounded-2xl p-4 sm:p-5">
                        <div
                            class="w-8 h-8 sm:w-10 sm:h-10 rounded-lg bg-air-green/15 flex items-center justify-center mb-2 sm:mb-3">
                            <i class="las la-users text-air-green text-lg sm:text-xl"></i>
                        </div>
                        <h4 class="font-heading font-600 text-sm mb-1">Teamverwaltung</h4>
                        <p class="text-xs text-gray-400">Spieler hinzufügen, Gruppen erstellen, Struktur schaffen.</p>
                    </div>
                    <div class="benefit-card grad-card rounded-xl sm:rounded-2xl p-4 sm:p-5">
                        <div
                            class="w-8 h-8 sm:w-10 sm:h-10 rounded-lg bg-air-green/15 flex items-center justify-center mb-2 sm:mb-3">
                            <i class="las la-calendar-plus text-air-green text-lg sm:text-xl"></i>
                        </div>
                        <h4 class="font-heading font-600 text-sm mb-1">Trainings- & Spielplanung</h4>
                        <p class="text-xs text-gray-400">Termine erstellen in Sekunden – auch wiederkehrend.</p>
                    </div>
                    <div class="benefit-card grad-card rounded-xl sm:rounded-2xl p-4 sm:p-5">
                        <div
                            class="w-8 h-8 sm:w-10 sm:h-10 rounded-lg bg-air-green/15 flex items-center justify-center mb-2 sm:mb-3">
                            <i class="las la-eye text-air-green text-lg sm:text-xl"></i>
                        </div>
                        <h4 class="font-heading font-600 text-sm mb-1">Echtzeit-Teilnehmer</h4>
                        <p class="text-xs text-gray-400">Sofort sehen, wer zugesagt hat – keine Nachfragen mehr.</p>
                    </div>
                    <div class="benefit-card grad-card rounded-xl sm:rounded-2xl p-4 sm:p-5">
                        <div
                            class="w-8 h-8 sm:w-10 sm:h-10 rounded-lg bg-air-green/15 flex items-center justify-center mb-2 sm:mb-3">
                            <i class="las la-bullhorn text-air-green text-lg sm:text-xl"></i>
                        </div>
                        <h4 class="font-heading font-600 text-sm mb-1">Zentrale Kommunikation</h4>
                        <p class="text-xs text-gray-400">Keine Infoverluste mehr – alle erreichen, sofort.</p>
                    </div>
                    <div class="benefit-card grad-card rounded-xl sm:rounded-2xl p-4 sm:p-5">
                        <div
                            class="w-8 h-8 sm:w-10 sm:h-10 rounded-lg bg-air-green/15 flex items-center justify-center mb-2 sm:mb-3">
                            <i class="las la-chart-line text-air-green text-lg sm:text-xl"></i>
                        </div>
                        <h4 class="font-heading font-600 text-sm mb-1">Leistungsanalysen</h4>
                        <p class="text-xs text-gray-400">Datenbasiert bessere Entscheidungen treffen.</p>
                    </div>
                </div>

                <!-- Vereine: 1 Spalte mobil -->
                <div v-show="activeTab === 'vereine'" id="tabpanel-vereine" role="tabpanel" aria-labelledby="tab-vereine"
                    class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-3 sm:gap-4">
                    <div class="benefit-card grad-card rounded-xl sm:rounded-2xl p-4 sm:p-5">
                        <div
                            class="w-8 h-8 sm:w-10 sm:h-10 rounded-lg bg-air-orange/15 flex items-center justify-center mb-2 sm:mb-3">
                            <i class="las la-laptop text-air-orange text-lg sm:text-xl"></i>
                        </div>
                        <h4 class="font-heading font-600 text-sm mb-1">Digitalisierung</h4>
                        <p class="text-xs text-gray-400">Kein Excel, kein Papier – moderner Vereinsbetrieb.</p>
                    </div>

                    <div class="benefit-card grad-card rounded-xl sm:rounded-2xl p-4 sm:p-5">
                        <div
                            class="w-8 h-8 sm:w-10 sm:h-10 rounded-lg bg-air-orange/15 flex items-center justify-center mb-2 sm:mb-3">
                            <i class="las la-building text-air-orange text-lg sm:text-xl"></i>
                        </div>
                        <h4 class="font-heading font-600 text-sm mb-1">Zentrale Verwaltung</h4>
                        <p class="text-xs text-gray-400">Alle Teams & Mitglieder übersichtlich an einem Ort.</p>
                    </div>

                    <div class="benefit-card grad-card rounded-xl sm:rounded-2xl p-4 sm:p-5">
                        <div
                            class="w-8 h-8 sm:w-10 sm:h-10 rounded-lg bg-air-orange/15 flex items-center justify-center mb-2 sm:mb-3">
                            <i class="las la-sitemap text-air-orange text-lg sm:text-xl"></i>
                        </div>
                        <h4 class="font-heading font-600 text-sm mb-1">Klare Strukturen</h4>
                        <p class="text-xs text-gray-400">Hierarchien und Rollen sauber abgebildet.</p>
                    </div>

                    <div class="benefit-card grad-card rounded-xl sm:rounded-2xl p-4 sm:p-5">
                        <div
                            class="w-8 h-8 sm:w-10 sm:h-10 rounded-lg bg-air-orange/15 flex items-center justify-center mb-2 sm:mb-3">
                            <i class="las la-share-alt text-air-orange text-lg sm:text-xl"></i>
                        </div>
                        <h4 class="font-heading font-600 text-sm mb-1">Ebenenübergreifend</h4>
                        <p class="text-xs text-gray-400">Vorstand → Trainer → Spieler – Infos fließen reibungslos.</p>
                    </div>

                    <div class="benefit-card grad-card rounded-xl sm:rounded-2xl p-4 sm:p-5">
                        <div
                            class="w-8 h-8 sm:w-10 sm:h-10 rounded-lg bg-air-orange/15 flex items-center justify-center mb-2 sm:mb-3">
                            <i class="las la-piggy-bank text-air-orange text-lg sm:text-xl"></i>
                        </div>
                        <h4 class="font-heading font-600 text-sm mb-1">Zeit- & Kostenersparnis</h4>
                        <p class="text-xs text-gray-400">Weniger Aufwand, weniger Kosten, mehr Fokus.</p>
                    </div>

                    <div class="benefit-card grad-card rounded-xl sm:rounded-2xl p-4 sm:p-5">
                        <div
                            class="w-8 h-8 sm:w-10 sm:h-10 rounded-lg bg-air-orange/15 flex items-center justify-center mb-2 sm:mb-3">
                            <i class="las la-star text-air-orange text-lg sm:text-xl"></i>
                        </div>
                        <h4 class="font-heading font-600 text-sm mb-1">Professionelle Wirkung</h4>
                        <p class="text-xs text-gray-400">Mehr Attraktivität für neue Mitglieder & Sponsoren.</p>
                    </div>

                    <div class="benefit-card grad-card rounded-xl sm:rounded-2xl p-4 sm:p-5">
                        <div
                            class="w-8 h-8 sm:w-10 sm:h-10 rounded-lg bg-air-orange/15 flex items-center justify-center mb-2 sm:mb-3">
                            <i class="las la-wallet text-air-orange text-lg sm:text-xl"></i>
                        </div>
                        <h4 class="font-heading font-600 text-sm mb-1">Beiträge verwalten</h4>
                        <p class="text-xs text-gray-400">Mitgliedsbeiträge einziehen, Mahnungen automatisieren,
                            Überblick behalten.</p>
                    </div>

                    <div class="benefit-card grad-card rounded-xl sm:rounded-2xl p-4 sm:p-5">
                        <div
                            class="w-8 h-8 sm:w-10 sm:h-10 rounded-lg bg-air-orange/15 flex items-center justify-center mb-2 sm:mb-3">
                            <i class="las la-handshake text-air-orange text-lg sm:text-xl"></i>
                        </div>
                        <h4 class="font-heading font-600 text-sm mb-1">Sponsoren-Management</h4>
                        <p class="text-xs text-gray-400">Sponsoren pflegen, Pakete verwalten, Sichtbarkeit messen –
                            alles zentral.</p>
                    </div>

                    <div class="benefit-card grad-card rounded-xl sm:rounded-2xl p-4 sm:p-5">
                        <div
                            class="w-8 h-8 sm:w-10 sm:h-10 rounded-lg bg-air-orange/15 flex items-center justify-center mb-2 sm:mb-3">
                            <i class="las la-file-invoice text-air-orange text-lg sm:text-xl"></i>
                        </div>
                        <h4 class="font-heading font-600 text-sm mb-1">Belege & Abrechnung</h4>
                        <p class="text-xs text-gray-400">Rechnungen & Quittungen digital ablegen, Ausgaben tracken,
                            Kassenbuch führen.</p>
                    </div>

                    <div class="benefit-card grad-card rounded-xl sm:rounded-2xl p-4 sm:p-5">
                        <div
                            class="w-8 h-8 sm:w-10 sm:h-10 rounded-lg bg-air-orange/15 flex items-center justify-center mb-2 sm:mb-3">
                            <i class="las la-calendar-plus text-air-orange text-lg sm:text-xl"></i>
                        </div>
                        <h4 class="font-heading font-600 text-sm mb-1">Termine für alle</h4>
                        <p class="text-xs text-gray-400">Versammlungen, Events & Spieltage anlegen – mit Zu-/Absagen für
                            den ganzen Verein.</p>
                    </div>
                </div>
            </div>
        </section>

        <!-- FUNKTIONEN -->
        <section id="funktionen" class="py-16 sm:py-24 px-4 border-t border-white/5"
            style="background: radial-gradient(ellipse 60% 40% at 50% 100%, rgba(0,102,255,.08) 0%, transparent 60%);">
            <div class="max-w-6xl mx-auto">
                <div class="text-center mb-14">
                    <span class="text-air-green text-sm font-semibold uppercase tracking-wider">Funktionen</span>
                    <h2 class="font-heading font-800 text-3xl sm:text-4xl mt-2">Alles, was du brauchst</h2>
                    <p class="text-gray-400 mt-3 max-w-xl mx-auto">Leistungsstarke Features, einfach zu bedienen.</p>
                </div>
                <div class="grid sm:grid-cols-2 lg:grid-cols-3 gap-6">
                    <div class="grad-card rounded-2xl p-6 hover:border-air-blue/30 transition group">
                        <div
                            class="w-12 h-12 rounded-xl bg-air-blue/15 flex items-center justify-center mb-4 group-hover:glow-blue transition">
                            <i class="las la-comments text-air-blue text-2xl"></i>
                        </div>
                        <h3 class="font-heading font-700 mb-2">Team-Chat</h3>
                        <p class="text-sm text-gray-400">Echtzeit-Kommunikation wie WhatsApp – aber strukturiert,
                            übersichtlich und ohne Ablenkung.</p>
                    </div>
                    <div class="grad-card rounded-2xl p-6 hover:border-air-green/30 transition group">
                        <div
                            class="w-12 h-12 rounded-xl bg-air-green/15 flex items-center justify-center mb-4 group-hover:glow-green transition">
                            <i class="las la-dumbbell text-air-green text-2xl"></i>
                        </div>
                        <h3 class="font-heading font-700 mb-2">Trainingsplanung</h3>
                        <p class="text-sm text-gray-400">Erstelle Trainingspläne, wiederkehrende Termine und teile sie
                            mit dem ganzen Team.</p>
                    </div>
                    <div class="grad-card rounded-2xl p-6 hover:border-air-orange/30 transition group">
                        <div
                            class="w-12 h-12 rounded-xl bg-air-orange/15 flex items-center justify-center mb-4 group-hover:glow-orange transition">
                            <i class="las la-calendar text-air-orange text-2xl"></i>
                        </div>
                        <h3 class="font-heading font-700 mb-2">Terminverwaltung</h3>
                        <p class="text-sm text-gray-400">Spiele, Training, Events – alles im Kalender. Mit Erinnerungen
                            und Zu-/Absagen.</p>
                    </div>
                    <div class="grad-card rounded-2xl p-6 hover:border-air-blue/30 transition group">
                        <div
                            class="w-12 h-12 rounded-xl bg-air-blue/15 flex items-center justify-center mb-4 group-hover:glow-blue transition">
                            <i class="las la-users text-air-blue text-2xl"></i>
                        </div>
                        <h3 class="font-heading font-700 mb-2">Teammanagement</h3>
                        <p class="text-sm text-gray-400">Spieler verwalten, Rollen zuweisen, Teams strukturieren – alles
                            zentral.</p>
                    </div>
                    <div class="grad-card rounded-2xl p-6 hover:border-air-green/30 transition group">
                        <div
                            class="w-12 h-12 rounded-xl bg-air-green/15 flex items-center justify-center mb-4 group-hover:glow-green transition">
                            <i class="las la-chart-bar text-air-green text-2xl"></i>
                        </div>
                        <h3 class="font-heading font-700 mb-2">Statistiken & Analysen</h3>
                        <p class="text-sm text-gray-400">Leistungsdaten, Anwesenheitsquoten und Fortschritt – visuell
                            aufbereitet.</p>
                    </div>
                    <div class="grad-card rounded-2xl p-6 hover:border-air-orange/30 transition group">
                        <div
                            class="w-12 h-12 rounded-xl bg-air-orange/15 flex items-center justify-center mb-4 group-hover:glow-orange transition">
                            <i class="las la-clipboard text-air-orange text-2xl"></i>
                        </div>
                        <h3 class="font-heading font-700 mb-2">Anwesenheitssystem</h3>
                        <p class="text-sm text-gray-400">Automatische Erfassung, wer dabei war. Keine Listen, kein
                            Nachfragen.</p>
                    </div>
                </div>
            </div>
        </section>

        <!-- SPORTARTEN -->
        <section id="sportarten" class="py-16 sm:py-24 px-4 border-t border-white/5">
            <div class="max-w-6xl mx-auto text-center">
                <span class="text-air-orange text-sm font-semibold uppercase tracking-wider">Sportarten</span>
                <h2 class="font-heading font-800 text-3xl sm:text-4xl mt-2">Für jede Sportart gemacht</h2>
                <p class="text-gray-400 mt-3 max-w-xl mx-auto">Teamsport oder Einzelsport – AIRMIUS passt sich an.</p>
                <div class="mt-12 flex flex-wrap justify-center gap-4">
                    <div
                        class="grad-card rounded-2xl px-6 py-5 flex items-center gap-3 hover:border-air-blue/30 transition">
                        <span class="text-3xl">⚽</span><span class="font-heading font-600">Fußball</span>
                    </div>
                    <div
                        class="grad-card rounded-2xl px-6 py-5 flex items-center gap-3 hover:border-air-green/30 transition">
                        <span class="text-3xl">🏀</span><span class="font-heading font-600">Basketball</span>
                    </div>
                    <div
                        class="grad-card rounded-2xl px-6 py-5 flex items-center gap-3 hover:border-air-orange/30 transition">
                        <span class="text-3xl">🏋️</span><span class="font-heading font-600">Fitness</span>
                    </div>
                    <div
                        class="grad-card rounded-2xl px-6 py-5 flex items-center gap-3 hover:border-air-blue/30 transition">
                        <span class="text-3xl">🎾</span><span class="font-heading font-600">Tennis</span>
                    </div>
                    <div
                        class="grad-card rounded-2xl px-6 py-5 flex items-center gap-3 hover:border-air-green/30 transition">
                        <span class="text-3xl">🏐</span><span class="font-heading font-600">Volleyball</span>
                    </div>
                    <div
                        class="grad-card rounded-2xl px-6 py-5 flex items-center gap-3 hover:border-air-orange/30 transition">
                        <span class="text-3xl">🏊</span><span class="font-heading font-600">Schwimmen</span>
                    </div>
                    <div
                        class="grad-card rounded-2xl px-6 py-5 flex items-center gap-3 hover:border-air-blue/30 transition">
                        <span class="text-3xl">🤸</span><span class="font-heading font-600">Turnen</span>
                    </div>
                    <div
                        class="grad-card rounded-2xl px-6 py-5 flex items-center gap-3 hover:border-air-green/30 transition">
                        <span class="text-3xl">🚴</span><span class="font-heading font-600">Radsport</span>
                    </div>
                    <div
                        class="grad-card rounded-2xl px-6 py-5 flex items-center gap-3 hover:border-air-orange/30 transition">
                        <span class="text-3xl">🥊</span><span class="font-heading font-600">Kampfsport</span>
                    </div>
                    <div
                        class="grad-card rounded-2xl px-6 py-5 flex items-center gap-3 hover:border-white/10 transition">
                        <span class="text-3xl">+</span>
                        <span class="font-heading font-600 text-gray-400">und viele mehr</span>
                    </div>
                </div>
            </div>
        </section>

        <!-- ÜBER UNS -->
        <section id="ueber" class="py-16 sm:py-24 px-4 border-t border-white/5"
            style="background: radial-gradient(ellipse 50% 40% at 20% 50%, rgba(0,200,83,.06) 0%, transparent 50%);">
            <div class="max-w-5xl mx-auto">
                <div class="text-center mb-14">
                    <span class="text-air-blue text-sm font-semibold uppercase tracking-wider">Über uns</span>
                    <h2 class="font-heading font-800 text-3xl sm:text-4xl mt-2">Wir digitalisieren den Sport</h2>
                </div>

                <div class="grid md:grid-cols-2 gap-8">
                    <div class="grad-card rounded-2xl p-8">
                        <div class="w-12 h-12 rounded-xl bg-air-blue/15 flex items-center justify-center mb-4">
                            <i class="las la-rocket text-air-blue text-2xl"></i>
                        </div>
                        <h3 class="font-heading font-700 text-xl mb-3">Unsere Vision</h3>
                        <p class="text-gray-400 leading-relaxed">
                            Die Digitalisierung im Sport vorantreiben. Wir bauen ein soziales Netzwerk für den Sport –
                            vergleichbar mit LinkedIn für berufliche Chancen und Airmius für sportliche Vernetzung.
                            Technologie soll den Sport besser, fairer und für alle zugänglicher machen.
                        </p>
                    </div>

                    <div class="grad-card rounded-2xl p-8">
                        <div class="w-12 h-12 rounded-xl bg-air-green/15 flex items-center justify-center mb-4">
                            <i class="las la-crosshairs text-air-green text-2xl"></i>
                        </div>
                        <h3 class="font-heading font-700 text-xl mb-3">Unsere Mission</h3>
                        <p class="text-gray-400 leading-relaxed">
                            Sport einfacher organisieren und Menschen verbinden. Wir schaffen eine Plattform, auf der
                            Trainer, Spieler und Vereine sich vernetzen, Chancen entdecken und ihre sportliche Zukunft
                            gestalten können. <br> Alles an einem Ort!
                        </p>
                    </div>
                </div>

                <div class="mt-8 grid sm:grid-cols-3 gap-4">
                    <div class="grad-card rounded-xl p-5 text-center">
                        <div class="flex items-center justify-center gap-2 mb-2">
                            <i class="las la-shield-alt text-air-blue"></i>
                            <span class="font-heading font-600">DSGVO-konform</span>
                        </div>
                        <p class="text-xs text-gray-500">Datenschutz nach höchsten Standards</p>
                    </div>

                    <div class="grad-card rounded-xl p-5 text-center">
                        <div class="flex items-center justify-center gap-2 mb-2">
                            <i class="las la-flag text-air-orange"></i>
                            <span class="font-heading font-600">Made in Germany</span>
                        </div>
                        <p class="text-xs text-gray-500">Entwickelt und gehostet in Deutschland</p>
                    </div>

                    <div class="grad-card rounded-xl p-5 text-center">
                        <div class="flex items-center justify-center gap-2 mb-2">
                            <i class="las la-heart text-air-green"></i>
                            <span class="font-heading font-600">Startup mit Herz</span>
                        </div>
                        <p class="text-xs text-gray-500">Von Sportlern für Sportler gebaut</p>
                    </div>
                </div>
            </div>
        </section>

        <!-- BLOG -->
        <section id="blog" class="py-16 sm:py-24 px-4 border-t border-white/5">
            <div class="max-w-6xl mx-auto">
                <div class="text-center mb-14">
                    <span class="text-air-green text-sm font-semibold uppercase tracking-wider">Blog</span>
                    <h2 class="font-heading font-800 text-3xl sm:text-4xl mt-2">Neuigkeiten & Tipps</h2>
                </div>
                <div class="grid sm:grid-cols-2 lg:grid-cols-3 gap-6">
                    <div class="grad-card rounded-2xl overflow-hidden group hover:border-air-blue/20 transition">
                        <div
                            class="h-40 bg-gradient-to-br from-air-blue/20 to-air-blue/5 flex items-center justify-center">
                            <i class="las la-brain text-air-blue text-5xl opacity-60"></i>
                        </div>
                        <div class="p-5">
                            <span class="text-xs uppercase tracking-wider text-air-blue font-semibold">Training</span>
                            <h3 class="font-heading font-600 mt-2 mb-2 group-hover:text-air-blue transition">5 Tipps für
                                effektiveres Mannschaftstraining</h3>
                            <p class="text-xs text-gray-500">Wie du mit einfachen Methoden das Beste aus jeder Einheit
                                holst.</p>
                        </div>
                    </div>
                    <div class="grad-card rounded-2xl overflow-hidden group hover:border-air-green/20 transition">
                        <div
                            class="h-40 bg-gradient-to-br from-air-green/20 to-air-green/5 flex items-center justify-center">
                            <i class="las la-fire text-air-green text-5xl opacity-60"></i>
                        </div>
                        <div class="p-5">
                            <span class="text-xs uppercase tracking-wider text-air-green font-semibold">Motivation</span>
                            <h3 class="font-heading font-600 mt-2 mb-2 group-hover:text-air-green transition">Wie du
                                dein Team langfristig motivierst</h3>
                            <p class="text-xs text-gray-500">Strategien für mehr Engagement und Teamgeist.</p>
                        </div>
                    </div>
                    <div class="grad-card rounded-2xl overflow-hidden group hover:border-air-orange/20 transition">
                        <div
                            class="h-40 bg-gradient-to-br from-air-orange/20 to-air-orange/5 flex items-center justify-center">
                            <i class="las la-laptop-code text-air-orange text-5xl opacity-60"></i>
                        </div>
                        <div class="p-5">
                            <span
                                class="text-xs uppercase tracking-wider text-air-orange font-semibold">Digitalisierung</span>
                            <h3 class="font-heading font-600 mt-2 mb-2 group-hover:text-air-orange transition">Warum
                                dein Verein jetzt digital werden muss</h3>
                            <p class="text-xs text-gray-500">Der Wettbewerbsvorteil durch moderne Vereinsführung.</p>
                        </div>
                    </div>
                </div>
            </div>
        </section>

        <!-- FAQ -->
        <section id="faq" class="py-16 sm:py-24 px-4 border-t border-white/5">
            <div class="max-w-3xl mx-auto">
                <div class="text-center mb-10">
                    <span class="text-air-blue text-xs sm:text-sm font-semibold uppercase tracking-wider">FAQ</span>
                    <h2 class="font-heading font-800 text-2xl sm:text-3xl mt-2">Häufige Fragen</h2>
                    <p class="text-gray-400 mt-3 text-sm sm:text-base">Alles, was du vor dem Start wissen musst.</p>
                </div>
                <div class="space-y-3">
                    <details v-for="faq in faqItems" :key="faq.question" class="grad-card rounded-2xl p-4 sm:p-5">
                        <summary
                            class="cursor-pointer list-none text-sm sm:text-base font-heading font-600 text-white flex justify-between items-center">
                            {{ faq.question }}
                            <span aria-hidden="true" class="ml-4 text-xs text-air-green">+</span>
                        </summary>
                        <p class="text-sm text-gray-400 mt-3 leading-relaxed">{{ faq.answer }}</p>
                    </details>
                </div>
            </div>
        </section>

        <!-- KONTAKT -->
        <section id="kontakt" class="py-16 sm:py-24 px-4 border-t border-white/5"
            style="background: radial-gradient(ellipse 60% 50% at 50% 0%, rgba(0,102,255,.08) 0%, transparent 50%);">
            <div class="max-w-3xl mx-auto">
                <div class="text-center mb-10">
                    <span class="text-air-blue text-sm font-semibold uppercase tracking-wider">Kontakt</span>
                    <h2 class="font-heading font-800 text-3xl sm:text-4xl mt-2">Schreib uns</h2>
                    <p class="text-gray-400 mt-3">Fragen, Feedback oder Partnerschaften? Wir freuen uns auf dich.</p>
                </div>
                <h3 id="kontakt-form-title" class="font-heading font-700 text-lg sm:text-xl text-white mb-2">
                    Kontakt aufnehmen</h3>
                <p id="kontakt-form-hinweis" class="text-xs text-gray-400 mb-2">
                    Wir antworten so schnell wie möglich.</p>
                <form novalidate @submit.prevent="submitForm" class="grad-card rounded-2xl p-6 sm:p-8 space-y-5"
                    aria-labelledby="kontakt-form-title" aria-describedby="kontakt-form-hinweis">
                    <div class="grid sm:grid-cols-2 gap-5">
                        <div>
                            <label for="cf-name" class="block text-sm font-medium text-gray-300 mb-1.5">Name</label>
                            <input id="cf-name" v-model="form.name" name="name" autocomplete="name" required
                                :aria-invalid="Boolean(form.errors.name)"
                                :aria-describedby="form.errors.name ? 'cf-name-error' : 'cf-name-help'" type="text"
                                ref="nameInputRef" placeholder="Dein Name" @input="clearFieldError('name')"
                                class="w-full bg-white/5 border border-white/10 rounded-xl px-4 py-3 text-sm text-white placeholder-gray-500 focus:outline-none focus:border-air-blue/50 transition">
                            <p id="cf-name-help" class="sr-only text-gray-500 text-xs mt-1">Bitte gib deinen Namen ein.</p>
                            <div v-if="form.errors.name" id="cf-name-error" role="alert" class="text-red-400 text-xs mt-1">{{ form.errors.name }}</div>
                        </div>
                        <div>
                            <label for="cf-email" class="block text-sm font-medium text-gray-300 mb-1.5">E-Mail</label>
                            <input id="cf-email" v-model="form.email" name="email" autocomplete="email" required
                                :aria-invalid="Boolean(form.errors.email)"
                                :aria-describedby="form.errors.email ? 'cf-email-error' : 'cf-email-help'" type="email"
                                ref="emailInputRef"
                                @input="clearFieldError('email')"
                                placeholder="deine@email.de"
                                class="w-full bg-white/5 border border-white/10 rounded-xl px-4 py-3 text-sm text-white placeholder-gray-500 focus:outline-none focus:border-air-blue/50 transition">
                            <p id="cf-email-help" class="sr-only text-gray-500 text-xs mt-1">Bitte gib eine gültige E-Mail-Adresse an.</p>
                            <div v-if="form.errors.email" id="cf-email-error" class="text-red-400 text-xs mt-1">{{ form.errors.email }}
                            </div>
                        </div>
                    </div>
                    <div>
                        <label for="cf-msg" class="block text-sm font-medium text-gray-300 mb-1.5">Nachricht</label>
                        <textarea id="cf-msg" v-model="form.message" name="message" required rows="4"
                            :aria-invalid="Boolean(form.errors.message)"
                            :aria-describedby="form.errors.message ? 'cf-message-error' : 'cf-msg-help'"
                            placeholder="Was möchtest du uns sagen?"
                            ref="messageInputRef" @input="clearFieldError('message')"
                            class="w-full bg-white/5 border border-white/10 rounded-xl px-4 py-3 text-sm text-white placeholder-gray-500 focus:outline-none focus:border-air-blue/50 transition resize-none"></textarea>
                            <p id="cf-msg-help" class="sr-only text-gray-500 text-xs mt-1">Kurze Anwendungsfrage, Feedback oder Supportbedarf.</p>
                            <div v-if="form.errors.message" id="cf-message-error" class="text-red-400 text-xs mt-1">{{ form.errors.message }}
                            </div>
                    </div>
                    <button type="submit" :disabled="form.processing"
                        class="w-full bg-air-blue hover:bg-blue-600 disabled:opacity-50 disabled:cursor-not-allowed text-white font-bold py-3.5 rounded-full transition">
                        <span v-if="form.processing">Wird gesendet...</span>
                        <span v-else>Nachricht senden</span>
                    </button>
                    <div v-show="formSuccess" role="status" aria-live="polite" class="text-center text-air-green text-sm font-medium py-2">
                        Danke! Deine Nachricht wurde gesendet.
                    </div>
                </form>
                <div class="mt-8 flex justify-center gap-5">
                    <button type="button" @click="scrollTo('blog')"
                        aria-label="Zum Blog-Bereich scrollen"
                        class="w-10 h-10 rounded-full bg-white/5 border border-white/10 flex items-center justify-center hover:border-air-blue/50 transition">
                        <i class="lab la-instagram text-gray-400" aria-hidden="true"></i>
                        <span class="sr-only">Blog lesen</span>
                    </button>
                    <button type="button" @click="scrollTo('funktionen')"
                        aria-label="Zum Funktionsbereich scrollen"
                        class="w-10 h-10 rounded-full bg-white/5 border border-white/10 flex items-center justify-center hover:border-air-blue/50 transition">
                        <i class="lab la-twitter text-gray-400" aria-hidden="true"></i>
                        <span class="sr-only">Funktionen ansehen</span>
                    </button>
                    <button type="button" @click="scrollTo('ueber')"
                        aria-label="Zum Über-uns-Bereich scrollen"
                        class="w-10 h-10 rounded-full bg-white/5 border border-white/10 flex items-center justify-center hover:border-air-blue/50 transition">
                        <i class="lab la-linkedin text-gray-400" aria-hidden="true"></i>
                        <span class="sr-only">Über uns ansehen</span>
                    </button>
                    <button type="button" @click="scrollTo('kontakt')"
                        aria-label="Zum Kontaktformular scrollen"
                        class="w-10 h-10 rounded-full bg-white/5 border border-white/10 flex items-center justify-center hover:border-air-blue/50 transition">
                        <i class="lab la-facebook text-gray-400" aria-hidden="true"></i>
                        <span class="sr-only">Kontaktbereich</span>
                    </button>
                </div>
            </div>
        </section>

        <!-- CTA BANNER -->
        <section class="py-16 px-4 border-t border-white/5">
            <div class="max-w-4xl mx-auto text-center grad-card rounded-3xl p-10 sm:p-14"
                style="background: linear-gradient(135deg, rgba(0,102,255,.15), rgba(0,200,83,.1), rgba(255,109,0,.08)); border-color: rgba(0,102,255,.2);">
                <h2 class="font-heading font-800 text-3xl sm:text-4xl">Bereit, dein Team zu digitalisieren?</h2>
                <p class="text-gray-400 mt-3 max-w-lg mx-auto">Starte jetzt kostenlos und erlebe, wie einfach
                    Sportorganisation sein kann.</p>
                <div class="mt-8 flex flex-col sm:flex-row gap-3 justify-center">
                    <a :href="heroPrimaryCta" @click="onBannerPrimaryCtaClick"
                        class="bg-air-blue hover:bg-blue-600 glow-blue text-white font-bold px-8 py-3.5 rounded-full transition">
                        {{ heroCopy.primaryCta }}
                    </a>
                    <button type="button" @click="onBannerSecondaryCtaClick"
                        class="border border-white/15 hover:border-white/30 text-white font-semibold px-8 py-3.5 rounded-full transition">
                        {{ heroCopy.secondaryCta }}
                    </button>
                </div>
            </div>
        </section>

        </main>

        <!-- FOOTER -->
        <Footer />
    </div>
</template>
<style scoped>
/* 1. Mockup schwebt dauerhaft */
@keyframes floatLoop {

    0%,
    100% {
        transform: translateY(0px);
    }

    50% {
        transform: translateY(-12px);
    }
}

.float-loop {
    animation: floatLoop 6s ease-in-out infinite;
}

/* 2. Items verschwinden und listen sich neu auf - 10s Loop */
@keyframes listLoop {

    /* Start: alle da */
    0% {
        opacity: 1;
        transform: translateY(0) scale(1);
    }

    /* 60%: alle verschwinden */
    60% {
        opacity: 1;
        transform: translateY(0) scale(1);
    }

    65% {
        opacity: 0;
        transform: translateY(-10px) scale(0.95);
    }

    /* 70%: noch weg */
    70% {
        opacity: 0;
        transform: translateY(10px) scale(0.95);
    }

    /* 100%: wieder da */
    100% {
        opacity: 1;
        transform: translateY(0) scale(1);
    }
}

.card-item {
    animation: listLoop 10s ease-in-out infinite;
}

/* Nacheinander einblenden mit delay */
.item-1 {
    animation-delay: 0s;
}

.item-2 {
    animation-delay: 0.15s;
}

.item-3 {
    animation-delay: 0.3s;
}

.item-4 {
    animation-delay: 0.45s;
}

.scrollbar-hide::-webkit-scrollbar {
    display: none;
}

.scrollbar-hide {
    -ms-overflow-style: none;
    scrollbar-width: none;
}

details[open] summary span {
    transform: rotate(45deg);
}

details summary::-webkit-details-marker {
    display: none;
}

details summary {
    list-style: none;
}

@media (prefers-reduced-motion: reduce) {
    .float-loop,
    .card-item {
        animation: none;
    }
}
</style>



