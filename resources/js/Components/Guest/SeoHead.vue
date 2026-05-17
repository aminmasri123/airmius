<script setup>
import { Head } from '@inertiajs/vue3'
import { computed, onBeforeUnmount, watch } from 'vue'

const props = defineProps({
    title: { type: String, required: true },
    description: { type: String, required: true },
    image: { type: String, default: '/img/logo/Logo-Airmius-Quervormat.png' },
    type: { type: String, default: 'website' },
    canonical: { type: String, default: null },
    noindex: { type: Boolean, default: false },
    schema: { type: [Object, Array], default: null },
})

const siteName = 'Airmius'
const fullTitle = computed(() => props.title.includes('Airmius') ? props.title : `${props.title} | ${siteName}`)
const currentUrl = computed(() => props.canonical || (typeof window !== 'undefined' ? window.location.href.split('#')[0] : ''))
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
        <meta name="description" :content="description">
        <meta name="robots" :content="noindex ? 'noindex,nofollow' : 'index,follow'">
        <link v-if="currentUrl" rel="canonical" :href="currentUrl">

        <meta property="og:site_name" :content="siteName">
        <meta property="og:type" :content="type">
        <meta property="og:title" :content="fullTitle">
        <meta property="og:description" :content="description">
        <meta v-if="currentUrl" property="og:url" :content="currentUrl">
        <meta v-if="absoluteImage" property="og:image" :content="absoluteImage">

        <meta name="twitter:card" content="summary_large_image">
        <meta name="twitter:title" :content="fullTitle">
        <meta name="twitter:description" :content="description">
        <meta v-if="absoluteImage" name="twitter:image" :content="absoluteImage">
    </Head>
</template>
