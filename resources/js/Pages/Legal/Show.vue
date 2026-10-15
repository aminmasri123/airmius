<script setup>
import { computed } from 'vue'
import { Link, usePage } from '@inertiajs/vue3'
import ApplicationLogo from '@/Components/ApplicationLogo.vue'
import LanguageDropdown from '@/Components/LanguageDropdown.vue'
import Footer from '@/Components/Guest/Footer.vue'
import SeoHead from '@/Components/Guest/SeoHead.vue'
import { useI18n } from 'vue-i18n'

const props = defineProps({
    title: {
        type: String,
        required: true,
    },
    sections: {
        type: Array,
        default: () => [],
    },
    note: {
        type: String,
        default: null,
    },
    action: {
        type: Object,
        default: null,
    },
    contentLocale: {
        type: String,
        default: 'de',
    },
    sourceLocale: {
        type: String,
        default: 'de',
    },
    textDirection: {
        type: String,
        default: 'ltr',
    },
    translationComplete: {
        type: Boolean,
        default: false,
    },
    translationContract: {
        type: String,
        default: null,
    },
    externalReviewRequired: {
        type: Boolean,
        default: true,
    },
})

const { t } = useI18n()
const tx = (value, params = {}) => t(value, params)
const page = usePage()
const currentUser = computed(() => page.props.auth?.user || null)
const actionHref = computed(() => currentUser.value && props.action?.authenticated_href
    ? props.action.authenticated_href
    : props.action?.href)
const actionLabel = computed(() => currentUser.value && props.action?.authenticated_label
    ? props.action.authenticated_label
    : props.action?.label)
const primaryLinkClass = 'inline-flex min-h-11 items-center justify-center rounded-lg bg-primary px-4 py-2 text-center text-sm font-semibold text-bg transition hover:opacity-90 hover:text-bg'
const secondaryLinkClass = 'inline-flex min-h-11 items-center justify-center rounded-lg border border-border px-4 py-2 text-sm font-semibold text-primary transition hover:bg-muted'
const localizedRoute = (name) => route(name, props.contentLocale === props.sourceLocale
    ? {}
    : { locale: props.contentLocale })
const currentRouteName = computed(() => route().current())
const otherDeletionGuide = computed(() => {
    if (currentRouteName.value === 'legal.account-deletion') {
        return { route: 'legal.club-deletion', labelKey: 'deletionGuide.aboutClub' }
    }
    if (currentRouteName.value === 'legal.club-deletion') {
        return { route: 'legal.account-deletion', labelKey: 'deletionGuide.aboutAccount' }
    }
    return null
})
const deletionContentsLabel = computed(() => ({
    de: 'Auf dieser Seite',
    en: 'On this page',
    fr: 'Sur cette page',
    ar: 'في هذه الصفحة',
}[props.contentLocale] || 'Auf dieser Seite'))
const deletionContactLabel = computed(() => ({
    de: 'Kontakt für Löschanfragen',
    en: 'Contact us about deletion',
    fr: 'Contacter le support pour une suppression',
    ar: 'التواصل بشأن طلب الحذف',
}[props.contentLocale] || 'Kontakt für Löschanfragen'))
const isSourcesLine = (line) => ['Quellen: ', 'Sources: ', 'المصادر: '].some((prefix) => line.startsWith(prefix))
const sourcesFromLine = (line) => line.slice(line.indexOf(': ') + 2).split(' ; ')
const sourceLabel = (source) => {
    if (source.includes('eur-lex.europa.eu')) return 'DSGVO · EUR-Lex'
    if (source.includes('/ao_1977/__147.html')) return '§ 147 AO'
    if (source.includes('/hgb/__257.html')) return '§ 257 HGB'
    if (source.includes('/ustg_1980/__14b.html')) return '§ 14b UStG'
    return new URL(source).hostname
}
const seoCanonical = computed(() => currentRouteName.value ? localizedRoute(currentRouteName.value) : null)
const seoDescription = computed(() => `${props.title} · ${props.sections[0]?.body?.[0] || 'Airmius'}`.slice(0, 160))
const seoAlternates = computed(() => {
    if (!currentRouteName.value) return []

    const alternates = ['de', 'en', 'fr', 'ar'].map((locale) => ({
        hreflang: locale,
        href: route(currentRouteName.value, locale === props.sourceLocale ? {} : { locale }),
    }))

    return [...alternates, { hreflang: 'x-default', href: route(currentRouteName.value) }]
})

const legalLinks = [
    { labelKey: 'guest.footer.imprint', route: 'legal.imprint' },
    { labelKey: 'guest.footer.privacy', route: 'policy.show' },
    { labelKey: 'settings.delete_account.title', route: 'legal.account-deletion' },
    { labelKey: 'clubDeletion.title', route: 'legal.club-deletion' },
    { labelKey: 'data_erasure.meta_title', route: 'legal.data-erasure' },
    { labelKey: 'guest.footer.terms', route: 'terms.show' },
    { labelKey: 'guest.footer.community', route: 'legal.community' },
    { labelKey: 'guest.footer.minors', route: 'legal.minors' },
    { labelKey: 'guest.footer.cookies', route: 'legal.cookies' },
    { labelKey: 'guest.footer.withdrawal', route: 'legal.withdrawal' },
    { labelKey: 'guest.footer.reporting', route: 'legal.reporting' },
]
</script>

<template>
    <SeoHead
        :title="`${title} | Airmius`"
        :description="seoDescription"
        :canonical="seoCanonical"
        :alternates="seoAlternates"
    />

    <main class="min-h-screen bg-bg text-primary" :lang="contentLocale" :dir="textDirection">
        <header class="border-b border-border bg-card/70">
            <div class="mx-auto flex max-w-6xl items-center justify-between gap-2 px-4 py-4">
                <Link :href="localizedRoute('welcome')" class="flex min-w-0 items-center">
                    <ApplicationLogo class="h-10 w-auto max-w-[8rem] sm:max-w-[11rem]" />
                </Link>

                <div class="flex shrink-0 items-center gap-2">
                    <LanguageDropdown />
                    <template v-if="currentUser">
                        <Link :href="localizedRoute('profile.show')" :class="[secondaryLinkClass, 'hidden min-[420px]:inline-flex']">{{ tx('Mein Konto') }}</Link>
                        <Link :href="localizedRoute('auth.dashboard')" :class="primaryLinkClass">{{ tx('Dashboard') }}</Link>
                    </template>
                    <template v-else>
                        <Link :href="localizedRoute('login')" :class="[secondaryLinkClass, 'hidden min-[420px]:inline-flex']">{{ tx('Anmelden') }}</Link>
                        <Link :href="localizedRoute('register')" :class="primaryLinkClass">{{ tx('Registrieren') }}</Link>
                    </template>
                </div>
            </div>
        </header>

        <section class="mx-auto grid max-w-6xl gap-6 px-4 py-8 lg:grid-cols-[16rem_minmax(0,1fr)]">
            <aside class="h-fit min-w-0 rounded-lg border border-border bg-card p-3">
                <p class="px-2 pb-2 text-xs font-semibold uppercase tracking-wide text-secondary">{{ tx('guest.footer.legal') }}</p>
                <nav
                    class="flex gap-2 overflow-x-auto pb-1 lg:block lg:space-y-1 lg:overflow-visible lg:pb-0"
                    :aria-label="tx('guest.footer.legal')"
                >
                    <Link
                        v-for="item in legalLinks"
                        :key="item.route"
                        :href="localizedRoute(item.route)"
                        class="block shrink-0 whitespace-nowrap rounded-lg px-3 py-2 text-sm text-secondary hover:bg-muted hover:text-primary"
                        :class="{ 'bg-muted font-semibold text-primary': route().current(item.route) }"
                        :aria-current="route().current(item.route) ? 'page' : undefined"
                    >
                        {{ tx(item.labelKey) }}
                    </Link>
                </nav>
            </aside>

            <article data-no-auto-translate class="rounded-lg border border-border bg-card">
                <div class="border-b border-border p-5 sm:p-8">
                    <p class="text-xs font-semibold uppercase tracking-wide text-secondary">Airmius</p>
                    <h1 class="mt-2 text-3xl font-semibold text-primary sm:text-4xl">{{ title }}</h1>
                    <p v-if="note" class="mt-4 rounded-lg border border-warning/30 bg-warning/10 p-3 text-sm text-warning">
                        {{ note }}
                    </p>
                    <Link v-if="action" :href="actionHref" :class="[primaryLinkClass, 'mt-4 w-full sm:w-auto']">
                        {{ actionLabel }}
                    </Link>
                    <div v-if="otherDeletionGuide" class="mt-3 flex flex-wrap gap-x-5 gap-y-2">
                        <Link :href="localizedRoute(otherDeletionGuide.route)" class="inline-flex min-h-10 items-center text-sm font-medium text-link underline underline-offset-2 hover:text-primary">
                            {{ tx(otherDeletionGuide.labelKey) }}
                        </Link>
                        <Link :href="localizedRoute('legal.reporting')" class="inline-flex min-h-10 items-center text-sm font-medium text-link underline underline-offset-2 hover:text-primary">
                            {{ deletionContactLabel }}
                        </Link>
                    </div>
                </div>

                <nav
                    v-if="otherDeletionGuide && sections.length > 1"
                    class="border-b border-border p-5 sm:p-8"
                    :aria-label="deletionContentsLabel"
                >
                    <h2 class="text-base font-semibold text-primary">{{ deletionContentsLabel }}</h2>
                    <ol class="mt-3 grid gap-x-6 gap-y-2 sm:grid-cols-2">
                        <li v-for="(section, index) in sections" :key="`${index}-${section.title}`">
                            <a :href="`#section-${index + 1}`" class="inline-flex min-h-9 items-center break-words text-sm text-link underline underline-offset-2 hover:text-primary">
                                {{ section.title }}
                            </a>
                        </li>
                    </ol>
                </nav>

                <div class="space-y-8 p-5 sm:p-8">
                    <section v-for="(section, index) in sections" :id="otherDeletionGuide ? `section-${index + 1}` : undefined" :key="section.title" class="scroll-mt-6">
                        <h2 class="text-xl font-semibold text-primary">{{ section.title }}</h2>
                        <div class="mt-3 space-y-2 text-sm leading-7 text-secondary">
                            <template v-for="line in section.body" :key="line">
                                <div v-if="isSourcesLine(line)">
                                    <p>{{ line.split(': ')[0] }}:</p>
                                    <ul class="mt-1 grid gap-1 sm:grid-cols-2">
                                        <li v-for="source in sourcesFromLine(line)" :key="source">
                                            <a :href="source" :title="source" target="_blank" rel="noopener noreferrer" class="inline-flex min-h-9 items-center font-medium text-link underline underline-offset-2">
                                                <bdi dir="ltr">{{ sourceLabel(source) }}</bdi>
                                            </a>
                                        </li>
                                    </ul>
                                </div>
                                <p v-else>{{ line }}</p>
                            </template>
                        </div>
                    </section>
                </div>
            </article>
        </section>

        <Footer />
    </main>
</template>
