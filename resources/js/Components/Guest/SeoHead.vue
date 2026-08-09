<script setup>
import { Head, usePage } from '@inertiajs/vue3'
import { computed, onBeforeUnmount, watch } from 'vue'

const props = defineProps({
    title: { type: String, required: true },
    description: { type: String, required: true },
    image: { type: String, default: '/img/logo/Airmius-Logo-Light.png' },
    type: { type: String, default: 'website' },
    canonical: { type: [String, Boolean], default: null },
    canonicalLocale: { type: String, default: '' },
    noindex: { type: Boolean, default: false },
    schema: { type: [Object, Array], default: null },
    alternates: { type: Array, default: () => [] },
    feed: { type: String, default: '' },
})

const siteName = 'Airmius'
const page = usePage()
const supportedLocales = ['de', 'en', 'fr', 'ar']
const normalizeLocale = (value) => {
    const locale = String(value || '').trim().toLowerCase().replace('_', '-').slice(0, 2)

    return supportedLocales.includes(locale) ? locale : 'de'
}
const activeLocale = computed(() => normalizeLocale(
    page.props.locale || (typeof document !== 'undefined' ? document.documentElement.lang : 'de'),
))
const seoLocale = computed(() => props.canonicalLocale
    ? normalizeLocale(props.canonicalLocale)
    : activeLocale.value)
const localizedUrl = (value, locale) => {
    const url = String(value || '').split('#')[0]
    if (!url) return ''

    const [baseUrl, query = ''] = url.split('?', 2)
    const parameters = new URLSearchParams(query)
    parameters.delete('locale')
    if (locale !== 'de') parameters.set('locale', locale)

    const localizedQuery = parameters.toString()
    return localizedQuery ? `${baseUrl}?${localizedQuery}` : baseUrl
}
const fullTitle = computed(() => props.title.includes('Airmius') ? props.title : `${props.title} | ${siteName}`)
const currentUrl = computed(() => {
    if (props.canonical === false) return ''

    const canonical = props.canonical || (typeof window !== 'undefined' ? window.location.href.split('#')[0] : '')

    return localizedUrl(canonical, seoLocale.value)
})
const resolvedAlternates = computed(() => {
    if (props.noindex || !currentUrl.value) return []
    if (props.alternates.length > 0) return props.alternates

    const baseUrl = localizedUrl(currentUrl.value, 'de')
    return [
        ...supportedLocales.map((locale) => ({ hreflang: locale, href: localizedUrl(baseUrl, locale) })),
        { hreflang: 'x-default', href: baseUrl },
    ]
})
const resolvedFeedUrl = computed(() => props.noindex || !props.feed
    ? ''
    : localizedUrl(props.feed, activeLocale.value))
const ogLocale = computed(() => {
    return {
        en: 'en_US',
        fr: 'fr_FR',
        ar: 'ar_AR',
    }[seoLocale.value] || 'de_DE'
})
const ogAlternateLocales = computed(() => {
    const actualLocales = props.alternates.length
        ? props.alternates
            .filter((alternate) => alternate.hreflang !== 'x-default')
            .map((alternate) => normalizeLocale(alternate.hreflang))
        : supportedLocales

    return [...new Set(actualLocales)]
    .filter((locale) => locale !== seoLocale.value)
    .map((locale) => ({
        locale,
        content: {
            en: 'en_US',
            fr: 'fr_FR',
            ar: 'ar_AR',
        }[locale] || 'de_DE',
    }))
})
const absoluteImage = computed(() => {
    if (!props.image) return ''
    if (props.image.startsWith('http')) return props.image

    return typeof window !== 'undefined' ? new URL(props.image, window.location.origin).toString() : props.image
})
const schemaJson = computed(() => props.schema ? JSON.stringify(props.schema) : '')

const schemaScriptId = 'airmius-jsonld-schema'

const updateSchemaJsonScript = () => {
    if (typeof window === 'undefined') return

    const existing = document.querySelector(`script[data-seo-schema="${schemaScriptId}"]`)
    if (existing) {
        existing.remove()
    }

    if (!schemaJson.value) {
        return
    }

    const schemaScript = document.createElement('script')
    schemaScript.type = 'application/ld+json'
    schemaScript.textContent = schemaJson.value
    schemaScript.setAttribute('data-seo-schema', schemaScriptId)
    document.head.appendChild(schemaScript)
}

watch(schemaJson, updateSchemaJsonScript, { immediate: true })

onBeforeUnmount(() => {
    const existing = document.querySelector(`script[data-seo-schema="${schemaScriptId}"]`)
    if (existing) {
        existing.remove()
    }
})
</script>

<template>
    <Head :title="fullTitle">
        <meta head-key="description" name="description" :content="description">
        <meta head-key="robots" name="robots" :content="noindex ? 'noindex,nofollow' : 'index,follow'">
        <link v-if="currentUrl" head-key="canonical" rel="canonical" :href="currentUrl">
        <link
            v-for="alternate in resolvedAlternates"
            :key="alternate.hreflang"
            :head-key="`alternate:${alternate.hreflang}`"
            rel="alternate"
            :hreflang="alternate.hreflang"
            :href="alternate.href"
        >
        <link
            v-if="resolvedFeedUrl"
            head-key="alternate:rss"
            rel="alternate"
            type="application/rss+xml"
            :href="resolvedFeedUrl"
            :title="$t('Airmius Blog')"
        >

        <meta head-key="og:site_name" property="og:site_name" :content="siteName">
        <meta head-key="og:type" property="og:type" :content="type">
        <meta head-key="og:title" property="og:title" :content="fullTitle">
        <meta head-key="og:description" property="og:description" :content="description">
        <meta v-if="currentUrl" head-key="og:url" property="og:url" :content="currentUrl">
        <meta v-if="absoluteImage" head-key="og:image" property="og:image" :content="absoluteImage">
        <meta head-key="og:locale" property="og:locale" :content="ogLocale">
        <meta
            v-for="alternate in ogAlternateLocales"
            :key="alternate.locale"
            :head-key="`og:locale:alternate:${alternate.locale}`"
            property="og:locale:alternate"
            :content="alternate.content"
        >

        <meta head-key="twitter:card" name="twitter:card" content="summary_large_image">
        <meta head-key="twitter:title" name="twitter:title" :content="fullTitle">
        <meta head-key="twitter:description" name="twitter:description" :content="description">
        <meta v-if="absoluteImage" head-key="twitter:image" name="twitter:image" :content="absoluteImage">
    </Head>
</template>
