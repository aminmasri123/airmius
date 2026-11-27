<script setup>
import { computed, onBeforeUnmount, onMounted, ref, watch } from 'vue'

const props = defineProps({
    placement: {
        type: String,
        required: true,
    },
    objective: {
        type: String,
        default: '',
    },
    variant: {
        type: String,
        default: 'card',
    },
    fallback: {
        type: Boolean,
        default: true,
    },
})

const ad = ref(null)
const loading = ref(true)
const failed = ref(false)
const slotElement = ref(null)
let refreshTimer
let expiryTimer
let observer
let visible = false
let disposed = false
let requestVersion = 0
let lastChecked = 0

const hasAd = computed(() => Boolean(ad.value?.id && ad.value?.click_url))
const isBanner = computed(() => props.variant === 'banner')
const imageRatio = computed(() => ({
    feed_square: '1 / 1',
    feed_portrait: '4 / 5',
    story_vertical: '9 / 16',
    banner_wide: '1200 / 628',
}[ad.value?.creative_format] || '1200 / 628'))

const fetchAd = async () => {
    if (disposed || !visible || document.visibilityState === 'hidden') return
    const version = ++requestVersion
    loading.value = true
    failed.value = false
    clearTimeout(expiryTimer)
    ad.value = null

    try {
        const response = await window.axios.get('/ads/active', {
            params: {
                placement: props.placement,
                objective: props.objective || undefined,
            },
        })

        if (disposed || version !== requestVersion) return
        const candidate = response.data
        const expires = candidate?.ends_at ? Date.parse(candidate.ends_at) : null
        if (expires !== null && (!Number.isFinite(expires) || expires <= Date.now())) return
        ad.value = candidate || null
        lastChecked = Date.now()
        if (expires !== null) {
            expiryTimer = window.setTimeout(() => {
                ad.value = null
                fetchAd()
            }, Math.min(expires - Date.now(), 2_147_483_647))
        }
    } catch (error) {
        if (disposed || version !== requestVersion) return
        failed.value = true
        ad.value = null
    } finally {
        if (!disposed && version === requestVersion) loading.value = false
    }
}

const onVisibilityChange = () => {
    if (document.visibilityState === 'visible') fetchAd()
}

onMounted(() => {
    observer = new IntersectionObserver(([entry]) => {
        visible = entry.isIntersecting && slotElement.value?.getBoundingClientRect().width > 0
        if (visible && Date.now() - lastChecked >= 60_000 && !loading.value) fetchAd()
        else if (visible && !lastChecked) fetchAd()
    }, { rootMargin: '100px' })
    observer.observe(slotElement.value)
    refreshTimer = window.setInterval(fetchAd, 60_000)
    document.addEventListener('visibilitychange', onVisibilityChange)
})

watch(() => [props.placement, props.objective], fetchAd)
onBeforeUnmount(() => {
    disposed = true
    ++requestVersion
    clearInterval(refreshTimer)
    clearTimeout(expiryTimer)
    observer?.disconnect()
    document.removeEventListener('visibilitychange', onVisibilityChange)
})
</script>

<template>
    <div ref="slotElement" :data-ad-placement="placement">
    <a
        v-if="hasAd"
        :href="ad.click_url"
        class="group relative block overflow-hidden rounded border border-border bg-card text-primary shadow-sm transition hover:-translate-y-0.5 hover:border-buttonPrimary/60 hover:shadow-md"
        :class="isBanner && ad.image_url ? 'sm:grid sm:grid-cols-2' : ''"
        rel="nofollow sponsored"
    >
        <img
            v-if="ad.image_url"
            :src="ad.image_url"
            :alt="ad.headline || ad.name || 'Anzeige'"
            class="w-full object-contain"
            :style="{ aspectRatio: imageRatio, maxHeight: isBanner ? '24rem' : undefined }"
        >

        <div class="flex flex-col justify-between p-5">
            <div>
                <div class="mb-3 inline-flex rounded-full bg-buttonPrimary/90 px-3 py-1 text-[11px] font-black uppercase tracking-wide text-buttonTextPrimary">
                    Anzeige
                </div>
                <p class="font-heading text-xl font-900 leading-tight" :class="isBanner ? 'max-w-xl text-2xl md:text-3xl' : ''">
                    {{ ad.headline || ad.name }}
                </p>
                <p v-if="ad.primary_text || ad.description" class="mt-2 line-clamp-3 text-sm leading-6 text-secondary">
                    {{ ad.primary_text || ad.description }}
                </p>
            </div>
            <span class="mt-4 inline-flex w-fit items-center gap-2 rounded bg-buttonPrimary px-4 py-2 text-sm font-black text-buttonTextPrimary">
                {{ ad.cta_label || 'Mehr erfahren' }}
                <i class="las la-arrow-right"></i>
            </span>
        </div>
    </a>

    <slot v-else-if="fallback && !loading && !failed" name="fallback"></slot>
    <slot v-else-if="fallback && failed" name="fallback"></slot>
    </div>
</template>
