<script setup>
import { computed, onMounted, ref } from 'vue'

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

const hasAd = computed(() => Boolean(ad.value?.id && ad.value?.click_url))
const isBanner = computed(() => props.variant === 'banner')
const isSidebar = computed(() => props.variant === 'sidebar')

const fetchAd = async () => {
    loading.value = true
    failed.value = false

    try {
        const response = await window.axios.get('/ads/active', {
            params: {
                placement: props.placement,
                objective: props.objective || undefined,
            },
        })

        ad.value = response.data || null
    } catch (error) {
        failed.value = true
        ad.value = null
    } finally {
        loading.value = false
    }
}

onMounted(fetchAd)
</script>

<template>
    <a
        v-if="hasAd"
        :href="ad.click_url"
        class="group relative block overflow-hidden border border-border bg-card text-primary shadow-sm transition hover:-translate-y-0.5 hover:border-buttonPrimary/60 hover:shadow-md"
        :class="[
            isBanner ? 'min-h-[11rem] rounded' : '',
            isSidebar ? 'min-h-[27rem] rounded' : '',
            !isBanner && !isSidebar ? 'rounded p-4' : '',
        ]"
        rel="nofollow sponsored"
    >
        <img
            v-if="ad.image_url"
            :src="ad.image_url"
            :alt="ad.headline || ad.name || 'Anzeige'"
            :class="[
                isBanner || isSidebar ? 'absolute inset-0 h-full w-full object-cover' : 'mb-3 aspect-[1.91/1] w-full rounded object-cover',
            ]"
        >
        <div
            v-if="isBanner || isSidebar"
            class="absolute inset-0 bg-gradient-to-b from-black/20 via-black/30 to-black/80"
        ></div>

        <div
            :class="[
                isBanner || isSidebar ? 'relative flex h-full min-h-[inherit] flex-col justify-between p-5 text-white' : '',
            ]"
        >
            <div>
                <div class="mb-3 inline-flex rounded-full bg-buttonPrimary/90 px-3 py-1 text-[11px] font-black uppercase tracking-wide text-buttonTextPrimary">
                    Anzeige
                </div>
                <p class="font-heading text-xl font-900 leading-tight" :class="isBanner ? 'max-w-xl text-2xl md:text-3xl' : ''">
                    {{ ad.headline || ad.name }}
                </p>
                <p v-if="ad.primary_text || ad.description" class="mt-2 line-clamp-3 text-sm leading-6" :class="isBanner || isSidebar ? 'text-white/90' : 'text-secondary'">
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
</template>
