<script setup>
import { useForm, usePage } from '@inertiajs/vue3'
import { computed, nextTick, onMounted, ref } from 'vue'
import { useI18n } from 'vue-i18n'
import Nav from '@/Components/Guest/Nav.vue'
import Subnav from '@/Components/Guest/Subnav.vue'
import Footer from '@/Components/Guest/Footer.vue'
import SeoHead from '@/Components/Guest/SeoHead.vue'
import { canTrackMarketingEvent } from '@/services/privacyConsent'

const props = defineProps({
    canLogin: Boolean,
    canRegister: Boolean,
    laravelVersion: String,
    phpVersion: String,
})

const { t } = useI18n()
const page = usePage()

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

    if (!canTrackMarketingEvent(page.props)) {
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
const heroPrimaryCtaLabel = computed(() => (props.canRegister ? heroCopy.value.primaryCta : t('Anmelden')))
const canonicalUrl = computed(() => typeof window !== 'undefined' ? `${window.location.origin}/` : '/')
const pageSchema = computed(() => {
    const applicationSchema = {
        '@type': 'SoftwareApplication',
        name: 'Airmius',
        description: t('guest.welcome.seo.description'),
        applicationCategory: 'BusinessApplication',
        operatingSystem: 'Web',
        url: typeof window !== 'undefined' ? window.location.origin : undefined,
    }

    const organizationSchema = {
        '@type': 'Organization',
        name: 'Airmius',
        url: typeof window !== 'undefined' ? `${window.location.origin}/` : undefined,
        logo: typeof window !== 'undefined' ? `${window.location.origin}/img/logo/Airmius-Logo-Light.png` : undefined,
    }

    const faqSchema = {
        '@type': 'FAQPage',
        mainEntity: faqItems.value.map((faq) => ({
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
    { key: 'sportler', labelKey: 'guest.welcome.audiences.athletes' },
    { key: 'trainer', labelKey: 'guest.welcome.audiences.coaches' },
    { key: 'vereine', labelKey: 'guest.welcome.audiences.clubs' },
]

const heroTrustItems = [
    {
        key: 'start',
        icon: 'las la-bolt',
        titleKey: 'guest.welcome.hero.trust.start.title',
        textKey: 'guest.welcome.hero.trust.start.text',
    },
    {
        key: 'privacy',
        icon: 'las la-shield-alt',
        titleKey: 'guest.welcome.hero.trust.privacy.title',
        textKey: 'guest.welcome.hero.trust.privacy.text',
    },
    {
        key: 'central',
        icon: 'las la-layer-group',
        titleKey: 'guest.welcome.hero.trust.central.title',
        textKey: 'guest.welcome.hero.trust.central.text',
    },
]

const proofPoints = computed(() => [
    {
        value: t('guest.welcome.problem.proof.app.value'),
        label: t('guest.welcome.problem.proof.app.label'),
    },
    {
        value: t('guest.welcome.problem.proof.roles.value'),
        label: t('guest.welcome.problem.proof.roles.label'),
    },
    {
        value: t('guest.welcome.problem.proof.chaos.value'),
        label: t('guest.welcome.problem.proof.chaos.label'),
    },
])

const problemCards = [
    {
        key: 'communication',
        icon: 'las la-comment-dots',
        titleKey: 'guest.welcome.problem.cards.communication.title',
        textKey: 'guest.welcome.problem.cards.communication.text',
        solutionKey: 'guest.welcome.problem.cards.communication.solution',
    },
    {
        key: 'time',
        icon: 'las la-clock',
        titleKey: 'guest.welcome.problem.cards.time.title',
        textKey: 'guest.welcome.problem.cards.time.text',
        solutionKey: 'guest.welcome.problem.cards.time.solution',
    },
    {
        key: 'overview',
        icon: 'las la-eye-slash',
        titleKey: 'guest.welcome.problem.cards.overview.title',
        textKey: 'guest.welcome.problem.cards.overview.text',
        solutionKey: 'guest.welcome.problem.cards.overview.solution',
    },
]

const mockupCards = [
    {
        key: 'chat',
        icon: 'las la-comment',
        bgClass: 'bg-air-blue/10',
        borderClass: 'border-air-blue/20',
        iconBgClass: 'bg-air-blue/20',
        iconTextClass: 'text-air-blue',
        titleKey: 'guest.welcome.mockup.chat.title',
        textKey: 'guest.welcome.mockup.chat.text',
    },
    {
        key: 'game',
        icon: 'las la-calendar',
        bgClass: 'bg-air-green/10',
        borderClass: 'border-air-green/20',
        iconBgClass: 'bg-air-green/20',
        iconTextClass: 'text-air-green',
        titleKey: 'guest.welcome.mockup.game.title',
        textKey: 'guest.welcome.mockup.game.text',
    },
    {
        key: 'stats',
        icon: 'las la-chart-bar',
        bgClass: 'bg-air-orange/10',
        borderClass: 'border-air-orange/20',
        iconBgClass: 'bg-air-orange/20',
        iconTextClass: 'text-air-orange',
        titleKey: 'guest.welcome.mockup.stats.title',
        textKey: 'guest.welcome.mockup.stats.text',
    },
]

const mockupStats = [
    { key: 'players', value: '24', labelKey: 'guest.welcome.mockup.numbers.players' },
    { key: 'accepted', value: '18', labelKey: 'guest.welcome.mockup.numbers.accepted' },
    { key: 'declined', value: '3', labelKey: 'guest.welcome.mockup.numbers.declined' },
]

const benefitCards = {
    sportler: [
        ['las la-grip-lines', 'guest.welcome.benefits.cards.athletes.all.title', 'guest.welcome.benefits.cards.athletes.all.text'],
        ['las la-comment', 'guest.welcome.benefits.cards.athletes.chat.title', 'guest.welcome.benefits.cards.athletes.chat.text'],
        ['las la-mouse-pointer', 'guest.welcome.benefits.cards.athletes.rsvp.title', 'guest.welcome.benefits.cards.athletes.rsvp.text'],
        ['las la-heart', 'guest.welcome.benefits.cards.athletes.live.title', 'guest.welcome.benefits.cards.athletes.live.text'],
        ['las la-chart-bar', 'guest.welcome.benefits.cards.athletes.stats.title', 'guest.welcome.benefits.cards.athletes.stats.text'],
        ['las la-history', 'guest.welcome.benefits.cards.athletes.history.title', 'guest.welcome.benefits.cards.athletes.history.text'],
        ['las la-bolt', 'guest.welcome.benefits.cards.athletes.motivation.title', 'guest.welcome.benefits.cards.athletes.motivation.text'],
        ['las la-mobile', 'guest.welcome.benefits.cards.athletes.available.title', 'guest.welcome.benefits.cards.athletes.available.text'],
        ['las la-car', 'guest.welcome.benefits.cards.athletes.rides.title', 'guest.welcome.benefits.cards.athletes.rides.text'],
        ['las la-shopping-bag', 'guest.welcome.benefits.cards.athletes.groupbuy.title', 'guest.welcome.benefits.cards.athletes.groupbuy.text'],
        ['las la-users', 'guest.welcome.benefits.cards.athletes.buddy.title', 'guest.welcome.benefits.cards.athletes.buddy.text', 'col-span-2 sm:col-span-1'],
    ],
    trainer: [
        ['las la-clock', 'guest.welcome.benefits.cards.coaches.time.title', 'guest.welcome.benefits.cards.coaches.time.text'],
        ['las la-tasks', 'guest.welcome.benefits.cards.coaches.lists.title', 'guest.welcome.benefits.cards.coaches.lists.text'],
        ['las la-user-check', 'guest.welcome.benefits.cards.coaches.attendance.title', 'guest.welcome.benefits.cards.coaches.attendance.text'],
        ['las la-users', 'guest.welcome.benefits.cards.coaches.teams.title', 'guest.welcome.benefits.cards.coaches.teams.text'],
        ['las la-calendar-plus', 'guest.welcome.benefits.cards.coaches.planning.title', 'guest.welcome.benefits.cards.coaches.planning.text'],
        ['las la-eye', 'guest.welcome.benefits.cards.coaches.participants.title', 'guest.welcome.benefits.cards.coaches.participants.text'],
        ['las la-bullhorn', 'guest.welcome.benefits.cards.coaches.communication.title', 'guest.welcome.benefits.cards.coaches.communication.text'],
        ['las la-chart-line', 'guest.welcome.benefits.cards.coaches.analytics.title', 'guest.welcome.benefits.cards.coaches.analytics.text'],
    ],
    vereine: [
        ['las la-laptop', 'guest.welcome.benefits.cards.clubs.digital.title', 'guest.welcome.benefits.cards.clubs.digital.text'],
        ['las la-building', 'guest.welcome.benefits.cards.clubs.admin.title', 'guest.welcome.benefits.cards.clubs.admin.text'],
        ['las la-sitemap', 'guest.welcome.benefits.cards.clubs.structure.title', 'guest.welcome.benefits.cards.clubs.structure.text'],
        ['las la-share-alt', 'guest.welcome.benefits.cards.clubs.levels.title', 'guest.welcome.benefits.cards.clubs.levels.text'],
        ['las la-piggy-bank', 'guest.welcome.benefits.cards.clubs.savings.title', 'guest.welcome.benefits.cards.clubs.savings.text'],
        ['las la-star', 'guest.welcome.benefits.cards.clubs.professional.title', 'guest.welcome.benefits.cards.clubs.professional.text'],
        ['las la-wallet', 'guest.welcome.benefits.cards.clubs.fees.title', 'guest.welcome.benefits.cards.clubs.fees.text'],
        ['las la-handshake', 'guest.welcome.benefits.cards.clubs.sponsors.title', 'guest.welcome.benefits.cards.clubs.sponsors.text'],
        ['las la-file-invoice', 'guest.welcome.benefits.cards.clubs.accounting.title', 'guest.welcome.benefits.cards.clubs.accounting.text'],
        ['las la-calendar-plus', 'guest.welcome.benefits.cards.clubs.events.title', 'guest.welcome.benefits.cards.clubs.events.text'],
    ],
}

const featureCards = [
    ['las la-comments', 'air-blue', 'guest.welcome.features.cards.chat.title', 'guest.welcome.features.cards.chat.text'],
    ['las la-dumbbell', 'air-green', 'guest.welcome.features.cards.training.title', 'guest.welcome.features.cards.training.text'],
    ['las la-calendar', 'air-orange', 'guest.welcome.features.cards.calendar.title', 'guest.welcome.features.cards.calendar.text'],
    ['las la-users', 'air-blue', 'guest.welcome.features.cards.team.title', 'guest.welcome.features.cards.team.text'],
    ['las la-chart-bar', 'air-green', 'guest.welcome.features.cards.analytics.title', 'guest.welcome.features.cards.analytics.text'],
    ['las la-clipboard', 'air-orange', 'guest.welcome.features.cards.attendance.title', 'guest.welcome.features.cards.attendance.text'],
]

const sportChips = [
    ['football', '⚽', 'guest.welcome.sports.items.football'],
    ['basketball', '🏀', 'guest.welcome.sports.items.basketball'],
    ['fitness', '🏋️', 'guest.welcome.sports.items.fitness'],
    ['tennis', '🎾', 'guest.welcome.sports.items.tennis'],
    ['volleyball', '🏐', 'guest.welcome.sports.items.volleyball'],
    ['swimming', '🏊', 'guest.welcome.sports.items.swimming'],
    ['gymnastics', '🤸', 'guest.welcome.sports.items.gymnastics'],
    ['cycling', '🚴', 'guest.welcome.sports.items.cycling'],
    ['martial', '🥋', 'guest.welcome.sports.items.martial'],
    ['more', '+', 'guest.welcome.sports.items.more'],
]

const aboutCards = [
    ['las la-rocket', 'air-blue', 'guest.welcome.about.vision.title', 'guest.welcome.about.vision.text'],
    ['las la-crosshairs', 'air-green', 'guest.welcome.about.mission.title', 'guest.welcome.about.mission.text'],
]

const aboutBadges = [
    ['las la-shield-alt', 'air-blue', 'guest.welcome.about.badges.privacy.title', 'guest.welcome.about.badges.privacy.text'],
    ['las la-flag', 'air-orange', 'guest.welcome.about.badges.germany.title', 'guest.welcome.about.badges.germany.text'],
    ['las la-heart', 'air-green', 'guest.welcome.about.badges.heart.title', 'guest.welcome.about.badges.heart.text'],
]

const blogCards = [
    ['las la-brain', 'air-blue', 'guest.welcome.blog.cards.training.category', 'guest.welcome.blog.cards.training.title', 'guest.welcome.blog.cards.training.text'],
    ['las la-fire', 'air-green', 'guest.welcome.blog.cards.motivation.category', 'guest.welcome.blog.cards.motivation.title', 'guest.welcome.blog.cards.motivation.text'],
    ['las la-laptop-code', 'air-orange', 'guest.welcome.blog.cards.digital.category', 'guest.welcome.blog.cards.digital.title', 'guest.welcome.blog.cards.digital.text'],
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
            badge: t('guest.welcome.hero.variant_b.badge'),
            title: t('guest.welcome.hero.variant_b.title'),
            highlight: t('guest.welcome.hero.variant_b.highlight'),
            subtitle: t('guest.welcome.hero.variant_b.subtitle'),
            primaryCta: t('guest.welcome.hero.variant_b.primary_cta'),
            secondaryCta: t('guest.welcome.hero.variant_b.secondary_cta'),
        }
    }

    return {
        badge: t('guest.welcome.hero.variant_a.badge'),
        title: t('guest.welcome.hero.variant_a.title'),
        highlight: t('guest.welcome.hero.variant_a.highlight'),
        subtitle: t('guest.welcome.hero.variant_a.subtitle'),
        primaryCta: t('guest.welcome.hero.variant_a.primary_cta'),
        secondaryCta: t('guest.welcome.hero.variant_a.secondary_cta'),
    }
})
const nameInputRef = ref(null)
const emailInputRef = ref(null)
const messageInputRef = ref(null)
const formSuccess = ref(false)

const faqItems = computed(() => [
    {
        question: t('guest.welcome.faq.items.tech.question'),
        answer: t('guest.welcome.faq.items.tech.answer'),
    },
    {
        question: t('guest.welcome.faq.items.switch.question'),
        answer: t('guest.welcome.faq.items.switch.answer'),
    },
    {
        question: t('guest.welcome.faq.items.cost.question'),
        answer: t('guest.welcome.faq.items.cost.answer'),
    },
    {
        question: t('guest.welcome.faq.items.privacy.question'),
        answer: t('guest.welcome.faq.items.privacy.answer'),
    },
])

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
        form.setError('name', t('guest.welcome.contact.validation.name_required'))
    }

    if (!trimmedEmail) {
        form.setError('email', t('guest.welcome.contact.validation.email_required'))
    } else if (!emailPattern.test(trimmedEmail)) {
        form.setError('email', t('guest.welcome.contact.validation.email_invalid'))
    }

    if (!trimmedMessage) {
        form.setError('message', t('guest.welcome.contact.validation.message_required'))
    } else if (trimmedMessage.length < 8) {
        form.setError('message', t('guest.welcome.contact.validation.message_short'))
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
        :title="t('guest.welcome.seo.title')"
        :description="t('guest.welcome.seo.description')"
        :schema="pageSchema"
        :canonical="canonicalUrl"
    />
    <a href="#main-content" class="sr-only focus:not-sr-only focus:fixed focus:top-4 focus:left-4 focus:z-50 focus:bg-card focus:px-4 focus:py-2 focus:rounded-lg focus:border focus:border-border">
        {{ t('guest.welcome.skip_to_content') }}
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
                        <span class="text-air-blue">{{ heroCopy.highlight }}</span>
                    </h1>
                    <p id="hero-subtitle"
                        class="anim-fade-d2 mt-5 text-secondary text-lg sm:text-xl max-w-xl mx-auto lg:mx-0 leading-relaxed">
                        {{ heroCopy.subtitle }}
                    </p>
                    <div class="anim-fade-d3 mt-8 flex flex-col sm:flex-row gap-3 justify-center lg:justify-start">
                        <a :href="heroPrimaryCta" :aria-label="heroPrimaryCtaLabel" @click="onHeroPrimaryCtaClick"
                            class="bg-air-blue hover:bg-blue-600 glow-blue text-white font-bold px-8 py-3.5 rounded-full text-center transition">
                            {{ heroPrimaryCtaLabel }}
                        </a>
                        <button type="button" @click="onHeroSecondaryCtaClick"
                            class="border border-border bg-card text-primary shadow-sm hover:border-air-blue/50 hover:bg-muted font-semibold px-8 py-3.5 rounded-full text-center transition">
                            {{ heroCopy.secondaryCta }}
                        </button>
                    </div>
                    <div
                        class="anim-fade-d4 mt-8 flex flex-wrap items-center gap-4 justify-center lg:justify-start text-sm text-secondary">
                        <span v-for="item in heroTrustItems" :key="item.key" class="flex items-center gap-1.5">
                            <i class="las la-check-circle text-air-green" aria-hidden="true"></i>{{ t(item.titleKey) }}
                        </span>
                    </div>
                    <div class="anim-fade-d4 mt-6 hidden sm:grid sm:grid-cols-3 gap-3 max-w-2xl mx-auto lg:mx-0">
                        <div v-for="item in heroTrustItems" :key="item.key"
                            class="rounded-2xl border border-border bg-card p-4 text-left">
                            <i :class="[item.icon, 'text-air-green text-xl mb-2']" aria-hidden="true"></i>
                            <div class="font-heading font-700 text-sm text-primary">{{ t(item.titleKey) }}</div>
                            <p class="mt-1 text-xs text-secondary leading-snug">{{ t(item.textKey) }}</p>
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
                                v-for="(card, index) in mockupCards"
                                :key="card.key"
                                :class="[`card-item item-${index + 1}`, card.bgClass, card.borderClass]"
                                class="border rounded-xl p-3 flex items-center gap-3">
                                <div :class="card.iconBgClass" class="w-10 h-10 rounded-lg flex items-center justify-center">
                                    <i :class="[card.icon, card.iconTextClass]"></i>
                                </div>
                                <div>
                                    <div class="text-xs text-gray-400">{{ t(card.titleKey) }}</div>
                                    <div class="text-sm font-medium">{{ t(card.textKey) }}</div>
                                </div>
                            </div>

                            <div class="flex gap-2 mt-2 card-item item-4">
                                <div v-for="(stat, index) in mockupStats" :key="stat.key" class="flex-1 bg-white/5 rounded-lg p-2 text-center">
                                    <div :class="['text-2xl font-heading font-bold', index === 0 ? 'text-air-blue' : index === 1 ? 'text-air-green' : 'text-air-orange']">{{ stat.value }}</div>
                                    <div class="text-[10px] text-gray-500">{{ t(stat.labelKey) }}</div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </section>
        <!-- PROBLEM LÖSUNG -->
        <section class="py-16 sm:py-24 px-4 border-t border-white/5 min-h-screen sm:h-dvh flex items-center">
            <div class="max-w-6xl mx-auto">
                <div class="text-center mb-14">
                    <h2 class="font-heading font-800 text-3xl sm:text-4xl">
                        {{ t('guest.welcome.problem.title_before') }}
                        <span class="text-red-400">{{ t('guest.welcome.problem.title_bad') }}</span>
                        {{ t('guest.welcome.problem.title_middle') }}
                        <span class="text-air-green">{{ t('guest.welcome.problem.title_good') }}</span>
                    </h2>
                    <p class="text-gray-400 mt-3 max-w-2xl mx-auto">{{ t('guest.welcome.problem.subtitle') }}</p>
                    <p class="text-gray-400 mt-2">{{ t('guest.welcome.problem.description') }}</p>
                </div>
                <div class="grid sm:grid-cols-3 gap-6">
                    <div v-for="card in problemCards" :key="card.key" class="grad-card rounded-2xl p-6 text-center">
                        <div class="w-14 h-14 mx-auto rounded-xl bg-red-500/10 flex items-center justify-center mb-4">
                            <i :class="[card.icon, 'text-red-400 text-3xl']"></i>
                        </div>
                        <h3 class="font-heading font-700 text-lg mb-2">{{ t(card.titleKey) }}</h3>
                        <p class="text-sm text-gray-400">{{ t(card.textKey) }}</p>
                        <div class="mt-4 pt-4 border-t border-white/5">
                            <span class="text-air-green text-sm font-semibold">→ {{ t(card.solutionKey) }}</span>
                        </div>
                    </div>
                </div>
                <div class="mt-10 grid sm:grid-cols-3 gap-4">
                    <div v-for="point in proofPoints" :key="point.value"
                        class="rounded-2xl border border-border bg-card/85 px-5 py-4 text-center shadow-sm backdrop-blur">
                        <div class="font-heading font-800 text-2xl text-primary">{{ point.value }}</div>
                        <p class="mt-1 text-xs font-semibold text-secondary">{{ point.label }}</p>
                    </div>
                </div>
            </div>
        </section>

        <!-- VORTEILE -->
        <section id="vorteile" class="py-16 sm:py-24 px-4 border-t border-white/5">
            <div class="max-w-6xl mx-auto">
                <div class="text-center mb-8 sm:mb-10 px-2">
                    <span
                        class="text-air-blue text-xs sm:text-sm font-semibold uppercase tracking-wider">{{ t('guest.nav.benefits') }}</span>
                    <h2 class="font-heading font-800 text-2xl sm:text-3xl lg:text-4xl mt-2 leading-tight">
                        {{ t('guest.welcome.benefits.title_before') }}
                        <span class="bg-gradient-to-r from-air-blue to-air-green bg-clip-text text-transparent">{{ t('guest.welcome.benefits.title_highlight') }}</span>
                    </h2>
                    <p class="text-gray-400 mt-3 text-sm sm:text-base max-w-xl mx-auto">{{ t('guest.welcome.benefits.subtitle') }}</p>
                </div>

                <!-- Tabs: Mobil nur Icons, horizontal scroll -->
                <div class="mb-8 sm:mb-10 flex justify-center px-4">
                    <div class="inline-flex bg-white/5 rounded-full p-1 gap-1 flex-wrap justify-center" role="tablist"
                        :aria-label="t('guest.welcome.benefits.audience_label')">
                        <button v-for="tab in tabs" :key="tab.key" type="button" :id="`tab-${tab.key}`"
                            :aria-controls="`tabpanel-${tab.key}`" :aria-selected="activeTab === tab.key"
                            :tabindex="activeTab === tab.key ? 0 : -1" @click="switchTab(tab.key)"
                            @keydown="onTabKeydown" :class="[
                            'rounded-full px-4 sm:px-5 py-2.5 text-sm font-semibold transition-all flex items-center gap-2 whitespace-nowrap',
                            activeTab === tab.key ? 'tab-active' : 'text-gray-400 hover:text-white'
                        ]">
                            {{ t(tab.labelKey) }}
                        </button>
                    </div>
                </div>

                <div v-if="activeTab === 'sportler'" id="tabpanel-sportler" role="tabpanel" aria-labelledby="tab-sportler"
                    class="grid grid-cols-2 sm:grid-cols-2 lg:grid-cols-4 gap-3 sm:gap-4">
                    <div
                        v-for="(card, index) in benefitCards.sportler"
                        :key="card[1]"
                        :class="card[3] || ''"
                        class="benefit-card grad-card rounded-xl sm:rounded-2xl p-3 sm:p-5"
                    >
                        <div class="w-8 h-8 sm:w-10 sm:h-10 rounded-lg bg-air-blue/15 flex items-center justify-center mb-2 sm:mb-3">
                            <i :class="[card[0], 'text-air-blue text-lg sm:text-xl']"></i>
                        </div>
                        <h4 class="font-heading font-600 text-xs sm:text-sm mb-1 leading-tight">{{ t(card[1]) }}</h4>
                        <p class="text-xs text-gray-400 leading-snug">{{ t(card[2]) }}</p>
                    </div>
                </div>

                <div v-if="activeTab === 'trainer'" id="tabpanel-trainer" role="tabpanel" aria-labelledby="tab-trainer"
                    class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-3 sm:gap-4">
                    <div
                        v-for="card in benefitCards.trainer"
                        :key="card[1]"
                        class="benefit-card grad-card rounded-xl sm:rounded-2xl p-4 sm:p-5"
                    >
                        <div class="w-8 h-8 sm:w-10 sm:h-10 rounded-lg bg-air-green/15 flex items-center justify-center mb-2 sm:mb-3">
                            <i :class="[card[0], 'text-air-green text-lg sm:text-xl']"></i>
                        </div>
                        <h4 class="font-heading font-600 text-sm mb-1">{{ t(card[1]) }}</h4>
                        <p class="text-xs text-gray-400">{{ t(card[2]) }}</p>
                    </div>
                </div>

                <div v-if="activeTab === 'vereine'" id="tabpanel-vereine" role="tabpanel" aria-labelledby="tab-vereine"
                    class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-3 sm:gap-4">
                    <div
                        v-for="card in benefitCards.vereine"
                        :key="card[1]"
                        class="benefit-card grad-card rounded-xl sm:rounded-2xl p-4 sm:p-5"
                    >
                        <div class="w-8 h-8 sm:w-10 sm:h-10 rounded-lg bg-air-orange/15 flex items-center justify-center mb-2 sm:mb-3">
                            <i :class="[card[0], 'text-air-orange text-lg sm:text-xl']"></i>
                        </div>
                        <h4 class="font-heading font-600 text-sm mb-1">{{ t(card[1]) }}</h4>
                        <p class="text-xs text-gray-400">{{ t(card[2]) }}</p>
                    </div>
                </div>
            </div>
        </section>

        <!-- FUNKTIONEN -->
        <section id="funktionen" class="py-16 sm:py-24 px-4 border-t border-white/5"
            style="background: radial-gradient(ellipse 60% 40% at 50% 100%, rgba(0,102,255,.08) 0%, transparent 60%);">
            <div class="max-w-6xl mx-auto">
                <div class="text-center mb-14">
                    <span class="text-air-green text-sm font-semibold uppercase tracking-wider">{{ t('guest.nav.features') }}</span>
                    <h2 class="font-heading font-800 text-3xl sm:text-4xl mt-2">{{ t('guest.welcome.features.title') }}</h2>
                    <p class="text-gray-400 mt-3 max-w-xl mx-auto">{{ t('guest.welcome.features.subtitle') }}</p>
                </div>
                <div class="grid sm:grid-cols-2 lg:grid-cols-3 gap-6">
                    <div
                        v-for="card in featureCards"
                        :key="card[2]"
                        :class="card[1] === 'air-blue' ? 'hover:border-air-blue/30' : card[1] === 'air-green' ? 'hover:border-air-green/30' : 'hover:border-air-orange/30'"
                        class="grad-card rounded-2xl p-6 transition group"
                    >
                        <div
                            :class="card[1] === 'air-blue' ? 'bg-air-blue/15 group-hover:glow-blue' : card[1] === 'air-green' ? 'bg-air-green/15 group-hover:glow-green' : 'bg-air-orange/15 group-hover:glow-orange'"
                            class="w-12 h-12 rounded-xl flex items-center justify-center mb-4 transition"
                        >
                            <i :class="[card[0], card[1] === 'air-blue' ? 'text-air-blue' : card[1] === 'air-green' ? 'text-air-green' : 'text-air-orange', 'text-2xl']"></i>
                        </div>
                        <h3 class="font-heading font-700 mb-2">{{ t(card[2]) }}</h3>
                        <p class="text-sm text-gray-400">{{ t(card[3]) }}</p>
                    </div>
                </div>
            </div>
        </section>

        <!-- SPORTARTEN -->
        <section id="sportarten" class="py-16 sm:py-24 px-4 border-t border-white/5">
            <div class="max-w-6xl mx-auto text-center">
                <span class="text-air-orange text-sm font-semibold uppercase tracking-wider">{{ t('guest.nav.sports') }}</span>
                <h2 class="font-heading font-800 text-3xl sm:text-4xl mt-2">{{ t('guest.welcome.sports.title') }}</h2>
                <p class="text-gray-400 mt-3 max-w-xl mx-auto">{{ t('guest.welcome.sports.subtitle') }}</p>
                <div class="mt-12 flex flex-wrap justify-center gap-4">
                    <div
                        v-for="(sport, index) in sportChips"
                        :key="sport[0]"
                        :class="index % 3 === 0 ? 'hover:border-air-blue/30' : index % 3 === 1 ? 'hover:border-air-green/30' : index % 3 === 2 ? 'hover:border-air-orange/30' : 'hover:border-white/10'"
                        class="grad-card rounded-2xl px-6 py-5 flex items-center gap-3 transition"
                    >
                        <span class="text-3xl">{{ sport[1] }}</span>
                        <span :class="sport[0] === 'more' ? 'text-gray-400' : ''" class="font-heading font-600">{{ t(sport[2]) }}</span>
                    </div>
                </div>
            </div>
        </section>

        <!-- ÜBER UNS -->
        <section id="ueber" class="py-16 sm:py-24 px-4 border-t border-white/5"
            style="background: radial-gradient(ellipse 50% 40% at 20% 50%, rgba(0,200,83,.06) 0%, transparent 50%);">
            <div class="max-w-5xl mx-auto">
                <div class="text-center mb-14">
                    <span class="text-air-blue text-sm font-semibold uppercase tracking-wider">{{ t('guest.nav.about') }}</span>
                    <h2 class="font-heading font-800 text-3xl sm:text-4xl mt-2">{{ t('guest.welcome.about.title') }}</h2>
                </div>

                <div class="grid md:grid-cols-2 gap-8">
                    <div v-for="card in aboutCards" :key="card[2]" class="grad-card rounded-2xl p-8">
                        <div
                            :class="card[1] === 'air-blue' ? 'bg-air-blue/15' : 'bg-air-green/15'"
                            class="w-12 h-12 rounded-xl flex items-center justify-center mb-4"
                        >
                            <i :class="[card[0], card[1] === 'air-blue' ? 'text-air-blue' : 'text-air-green', 'text-2xl']"></i>
                        </div>
                        <h3 class="font-heading font-700 text-xl mb-3">{{ t(card[2]) }}</h3>
                        <p class="text-gray-400 leading-relaxed">{{ t(card[3]) }}</p>
                    </div>
                </div>

                <div class="mt-8 grid sm:grid-cols-3 gap-4">
                    <div v-for="badge in aboutBadges" :key="badge[2]" class="grad-card rounded-xl p-5 text-center">
                        <div class="flex items-center justify-center gap-2 mb-2">
                            <i :class="[badge[0], badge[1] === 'air-blue' ? 'text-air-blue' : badge[1] === 'air-green' ? 'text-air-green' : 'text-air-orange']"></i>
                            <span class="font-heading font-600">{{ t(badge[2]) }}</span>
                        </div>
                        <p class="text-xs text-gray-500">{{ t(badge[3]) }}</p>
                    </div>
                </div>
            </div>
        </section>

        <!-- BLOG -->
        <section id="blog" class="py-16 sm:py-24 px-4 border-t border-white/5">
            <div class="max-w-6xl mx-auto">
                <div class="text-center mb-14">
                    <span class="text-air-green text-sm font-semibold uppercase tracking-wider">{{ t('guest.nav.blog') }}</span>
                    <h2 class="font-heading font-800 text-3xl sm:text-4xl mt-2">{{ t('guest.welcome.blog.title') }}</h2>
                </div>
                <div class="grid sm:grid-cols-2 lg:grid-cols-3 gap-6">
                    <div
                        v-for="card in blogCards"
                        :key="card[3]"
                        :class="card[1] === 'air-blue' ? 'hover:border-air-blue/20' : card[1] === 'air-green' ? 'hover:border-air-green/20' : 'hover:border-air-orange/20'"
                        class="grad-card rounded-2xl overflow-hidden group transition"
                    >
                        <div
                            :class="card[1] === 'air-blue' ? 'from-air-blue/20 to-air-blue/5' : card[1] === 'air-green' ? 'from-air-green/20 to-air-green/5' : 'from-air-orange/20 to-air-orange/5'"
                            class="h-40 bg-gradient-to-br flex items-center justify-center"
                        >
                            <i :class="[card[0], card[1] === 'air-blue' ? 'text-air-blue' : card[1] === 'air-green' ? 'text-air-green' : 'text-air-orange', 'text-5xl opacity-60']"></i>
                        </div>
                        <div class="p-5">
                            <span :class="card[1] === 'air-blue' ? 'text-air-blue' : card[1] === 'air-green' ? 'text-air-green' : 'text-air-orange'" class="text-xs uppercase tracking-wider font-semibold">{{ t(card[2]) }}</span>
                            <h3 :class="card[1] === 'air-blue' ? 'group-hover:text-air-blue' : card[1] === 'air-green' ? 'group-hover:text-air-green' : 'group-hover:text-air-orange'" class="font-heading font-600 mt-2 mb-2 transition">{{ t(card[3]) }}</h3>
                            <p class="text-xs text-gray-500">{{ t(card[4]) }}</p>
                        </div>
                    </div>
                </div>
            </div>
        </section>

        <!-- FAQ -->
        <section id="faq" class="py-16 sm:py-24 px-4 border-t border-white/5">
            <div class="max-w-3xl mx-auto">
                <div class="text-center mb-10">
                    <span class="text-air-blue text-xs sm:text-sm font-semibold uppercase tracking-wider">{{ t('guest.welcome.faq.eyebrow') }}</span>
                    <h2 class="font-heading font-800 text-2xl sm:text-3xl mt-2">{{ t('guest.welcome.faq.title') }}</h2>
                    <p class="text-gray-400 mt-3 text-sm sm:text-base">{{ t('guest.welcome.faq.subtitle') }}</p>
                </div>
                <div class="space-y-3">
                    <details v-for="faq in faqItems" :key="faq.question" class="grad-card rounded-2xl p-4 sm:p-5">
                        <summary
                            class="cursor-pointer list-none text-sm sm:text-base font-heading font-600 text-primary flex justify-between items-center">
                            {{ faq.question }}
                            <span aria-hidden="true" class="ml-4 text-xs text-air-green">+</span>
                        </summary>
                        <p class="text-sm text-secondary mt-3 leading-relaxed">{{ faq.answer }}</p>
                    </details>
                </div>
            </div>
        </section>

        <!-- KONTAKT -->
        <section id="kontakt" class="py-16 sm:py-24 px-4 border-t border-white/5"
            style="background: radial-gradient(ellipse 60% 50% at 50% 0%, rgba(0,102,255,.08) 0%, transparent 50%);">
            <div class="max-w-3xl mx-auto">
                <div class="text-center mb-10">
                    <span class="text-air-blue text-sm font-semibold uppercase tracking-wider">{{ t('guest.nav.contact') }}</span>
                    <h2 class="font-heading font-800 text-3xl sm:text-4xl mt-2">{{ t('guest.welcome.contact.title') }}</h2>
                    <p class="text-gray-400 mt-3">{{ t('guest.welcome.contact.subtitle') }}</p>
                </div>
                <h3 id="kontakt-form-title" class="font-heading font-700 text-lg sm:text-xl text-white mb-2">
                    {{ t('guest.welcome.contact.form_title') }}</h3>
                <p id="kontakt-form-hinweis" class="text-xs text-gray-400 mb-2">
                    {{ t('guest.welcome.contact.form_hint') }}</p>
                <form novalidate @submit.prevent="submitForm" class="grad-card rounded-2xl p-6 sm:p-8 space-y-5"
                    aria-labelledby="kontakt-form-title" aria-describedby="kontakt-form-hinweis">
                    <div class="grid sm:grid-cols-2 gap-5">
                        <div>
                            <label for="cf-name" class="block text-sm font-medium text-gray-300 mb-1.5">{{ t('guest.welcome.contact.name_label') }}</label>
                            <input id="cf-name" v-model="form.name" name="name" autocomplete="name" required
                                :aria-invalid="Boolean(form.errors.name)"
                                :aria-describedby="form.errors.name ? 'cf-name-error' : 'cf-name-help'" type="text"
                                ref="nameInputRef" :placeholder="t('guest.welcome.contact.name_placeholder')" @input="clearFieldError('name')"
                                class="w-full bg-white/5 border border-white/10 rounded-xl px-4 py-3 text-sm text-white placeholder-gray-500 focus:outline-none focus:border-air-blue/50 transition">
                            <p id="cf-name-help" class="sr-only text-gray-500 text-xs mt-1">{{ t('guest.welcome.contact.name_help') }}</p>
                            <div v-if="form.errors.name" id="cf-name-error" role="alert" class="text-red-400 text-xs mt-1">{{ form.errors.name }}</div>
                        </div>
                        <div>
                            <label for="cf-email" class="block text-sm font-medium text-gray-300 mb-1.5">{{ t('guest.welcome.contact.email_label') }}</label>
                            <input id="cf-email" v-model="form.email" name="email" autocomplete="email" required
                                :aria-invalid="Boolean(form.errors.email)"
                                :aria-describedby="form.errors.email ? 'cf-email-error' : 'cf-email-help'" type="email"
                                ref="emailInputRef"
                                @input="clearFieldError('email')"
                                :placeholder="t('guest.welcome.contact.email_placeholder')"
                                class="w-full bg-white/5 border border-white/10 rounded-xl px-4 py-3 text-sm text-white placeholder-gray-500 focus:outline-none focus:border-air-blue/50 transition">
                            <p id="cf-email-help" class="sr-only text-gray-500 text-xs mt-1">{{ t('guest.welcome.contact.email_help') }}</p>
                            <div v-if="form.errors.email" id="cf-email-error" class="text-red-400 text-xs mt-1">{{ form.errors.email }}
                            </div>
                        </div>
                    </div>
                    <div>
                        <label for="cf-msg" class="block text-sm font-medium text-gray-300 mb-1.5">{{ t('guest.welcome.contact.message_label') }}</label>
                        <textarea id="cf-msg" v-model="form.message" name="message" required rows="4"
                            :aria-invalid="Boolean(form.errors.message)"
                            :aria-describedby="form.errors.message ? 'cf-message-error' : 'cf-msg-help'"
                            :placeholder="t('guest.welcome.contact.message_placeholder')"
                            ref="messageInputRef" @input="clearFieldError('message')"
                            class="w-full bg-white/5 border border-white/10 rounded-xl px-4 py-3 text-sm text-white placeholder-gray-500 focus:outline-none focus:border-air-blue/50 transition resize-none"></textarea>
                            <p id="cf-msg-help" class="sr-only text-gray-500 text-xs mt-1">{{ t('guest.welcome.contact.message_help') }}</p>
                            <div v-if="form.errors.message" id="cf-message-error" class="text-red-400 text-xs mt-1">{{ form.errors.message }}
                            </div>
                    </div>
                    <button type="submit" :disabled="form.processing"
                        class="w-full bg-air-blue hover:bg-blue-600 disabled:opacity-50 disabled:cursor-not-allowed text-white font-bold py-3.5 rounded-full transition">
                        <span v-if="form.processing">{{ t('guest.welcome.contact.sending') }}</span>
                        <span v-else>{{ t('guest.welcome.contact.submit') }}</span>
                    </button>
                    <div v-show="formSuccess" role="status" aria-live="polite" class="text-center text-air-green text-sm font-medium py-2">
                        {{ t('guest.welcome.contact.success') }}
                    </div>
                </form>
                <div class="mt-8 flex justify-center gap-5">
                    <button type="button" @click="scrollTo('blog')"
                        :aria-label="t('guest.welcome.contact.social.blog_aria')"
                        class="w-10 h-10 rounded-full bg-white/5 border border-white/10 flex items-center justify-center hover:border-air-blue/50 transition">
                        <i class="lab la-instagram text-gray-400" aria-hidden="true"></i>
                        <span class="sr-only">{{ t('guest.welcome.contact.social.blog') }}</span>
                    </button>
                    <button type="button" @click="scrollTo('funktionen')"
                        :aria-label="t('guest.welcome.contact.social.features_aria')"
                        class="w-10 h-10 rounded-full bg-white/5 border border-white/10 flex items-center justify-center hover:border-air-blue/50 transition">
                        <i class="lab la-twitter text-gray-400" aria-hidden="true"></i>
                        <span class="sr-only">{{ t('guest.welcome.contact.social.features') }}</span>
                    </button>
                    <button type="button" @click="scrollTo('ueber')"
                        :aria-label="t('guest.welcome.contact.social.about_aria')"
                        class="w-10 h-10 rounded-full bg-white/5 border border-white/10 flex items-center justify-center hover:border-air-blue/50 transition">
                        <i class="lab la-linkedin text-gray-400" aria-hidden="true"></i>
                        <span class="sr-only">{{ t('guest.welcome.contact.social.about') }}</span>
                    </button>
                    <button type="button" @click="scrollTo('kontakt')"
                        :aria-label="t('guest.welcome.contact.social.contact_aria')"
                        class="w-10 h-10 rounded-full bg-white/5 border border-white/10 flex items-center justify-center hover:border-air-blue/50 transition">
                        <i class="lab la-facebook text-gray-400" aria-hidden="true"></i>
                        <span class="sr-only">{{ t('guest.welcome.contact.social.contact') }}</span>
                    </button>
                </div>
            </div>
        </section>

        <!-- CTA BANNER -->
        <section class="py-16 px-4 border-t border-white/5">
            <div class="max-w-4xl mx-auto text-center grad-card rounded-3xl p-10 sm:p-14"
                style="background: linear-gradient(135deg, rgba(0,102,255,.15), rgba(0,200,83,.1), rgba(255,109,0,.08)); border-color: rgba(0,102,255,.2);">
                <h2 class="font-heading font-800 text-3xl sm:text-4xl">{{ t('guest.welcome.cta.title') }}</h2>
                <p class="text-secondary mt-3 max-w-lg mx-auto">{{ t('guest.welcome.cta.subtitle') }}</p>
                <div class="mt-8 flex flex-col sm:flex-row gap-3 justify-center">
                    <a :href="heroPrimaryCta" @click="onBannerPrimaryCtaClick"
                        class="bg-air-blue hover:bg-blue-600 glow-blue text-white font-bold px-8 py-3.5 rounded-full transition">
                        {{ heroCopy.primaryCta }}
                    </a>
                    <button type="button" @click="onBannerSecondaryCtaClick"
                        class="border border-border bg-card text-primary shadow-sm hover:border-air-blue/50 hover:bg-muted font-semibold px-8 py-3.5 rounded-full transition">
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









