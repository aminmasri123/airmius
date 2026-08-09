<script setup>
import { Head } from '@inertiajs/vue3'
import { computed, onBeforeUnmount, watch } from 'vue'

const props = defineProps({
    title: { type: String, required: true },
    description: { type: String, required: true },
    image: { type: String, default: '/img/logo/Airmius-Logo-Light.png' },
    type: { type: String, default: 'website' },
    canonical: { type: [String, Boolean], default: null },
    noindex: { type: Boolean, default: false },
    schema: { type: [Object, Array], default: null },
})

const siteName = 'Airmius'
const fullTitle = computed(() => props.title.includes('Airmius') ? props.title : `${props.title} | ${siteName}`)
const currentUrl = computed(() => {
    if (props.canonical === false) return ''

    return props.canonical || (typeof window !== 'undefined' ? window.location.href.split('#')[0] : '')
})
const ogLocale = computed(() => {
    const locale = typeof document !== 'undefined' ? document.documentElement.lang.slice(0, 2) : 'de'

    return {
        en: 'en_US',
        fr: 'fr_FR',
        ar: 'ar_AR',
    }[locale] || 'de_DE'
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

        <meta head-key="og:site_name" property="og:site_name" :content="siteName">
        <meta head-key="og:type" property="og:type" :content="type">
        <meta head-key="og:title" property="og:title" :content="fullTitle">
        <meta head-key="og:description" property="og:description" :content="description">
        <meta v-if="currentUrl" head-key="og:url" property="og:url" :content="currentUrl">
        <meta v-if="absoluteImage" head-key="og:image" property="og:image" :content="absoluteImage">
        <meta head-key="og:locale" property="og:locale" :content="ogLocale">

        <meta head-key="twitter:card" name="twitter:card" content="summary_large_image">
        <meta head-key="twitter:title" name="twitter:title" :content="fullTitle">
        <meta head-key="twitter:description" name="twitter:description" :content="description">
        <meta v-if="absoluteImage" head-key="twitter:image" name="twitter:image" :content="absoluteImage">
    </Head>
</template>
