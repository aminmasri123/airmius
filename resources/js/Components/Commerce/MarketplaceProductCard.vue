<script setup>
import { computed } from 'vue'
import { Link } from '@inertiajs/vue3'
import { useI18n } from 'vue-i18n'
import { formatCurrency } from '@/utils/currency'

const props = defineProps({
    product: { type: Object, required: true },
})

const { t, locale } = useI18n()

const categoryLabels = computed(() => ({
    product: t('Produkt'),
    course: t('Kurs'),
    camp: t('Camp'),
    service: t('Service'),
    outfit_subscription: t('Outfit-Abo'),
}))
const price = computed(() => props.product.price || {
    gross_cents: props.product.price_cents,
    net_cents: props.product.price_cents,
    tax_cents: 0,
    currency: props.product.currency || 'EUR',
    tax_rate: 0,
    tax_label: 'Tax',
})
const grossPrice = computed(() => formatCurrency(price.value.gross_cents, price.value.currency, locale.value))
const oldPrice = computed(() => props.product.old_price_cents
    ? formatCurrency(props.product.old_price_cents, price.value.currency, locale.value)
    : '')
const taxInfo = computed(() => {
    const net = formatCurrency(price.value.net_cents, price.value.currency, locale.value)
    const tax = formatCurrency(price.value.tax_cents, price.value.currency, locale.value)

    return `${net} ${t('netto')} · ${tax} ${price.value.tax_label} (${price.value.tax_rate}%)`
})
const reviewSummary = computed(() => props.product.review_summary || {})
const reviewCount = computed(() => Number(reviewSummary.value.rating_count || 0))
const verifiedPurchaseCount = computed(() => Number(reviewSummary.value.verified_purchase_count || 0))
const wishlistCount = computed(() => Number(props.product.wishlist_summary?.count || 0))
const isWishlisted = computed(() => Boolean(props.product.viewer?.is_wishlisted))
const categoryLabel = computed(() => categoryLabels.value[props.product.category] || props.product.category)
const providerName = computed(() => props.product.provider_profile?.name || props.product.provider_name || 'Airmius Marketplace')
const availabilityLabel = computed(() => {
    if (props.product.category === 'outfit_subscription') {
        return props.product.items_per_box ? t('{count} Teile je Box', { count: props.product.items_per_box }) : t('Monatlich kündbar')
    }

    if (props.product.offer_type === 'online_course' || props.product.offer_type === 'training_plan' || props.product.category === 'service') {
        return t('Digital / Termin')
    }

    if (!props.product.manages_stock) {
        return t('Auf Anfrage')
    }

    const stock = Number(props.product.stock_quantity || 0)

    if (stock <= 0) {
        return t('Aktuell vergriffen')
    }

    return stock <= 5 ? t('Nur {count} verfügbar', { count: stock }) : t('Auf Lager')
})

const shortDescription = (text, length = 78) => {
    if (!text) return t('Sportangebot aus dem Airmius Marketplace.')
    if (text.length <= length) return text

    return `${text.slice(0, length).trim()}...`
}
</script>

<template>
    <article class="group flex h-full overflow-hidden rounded border border-border bg-card transition hover:border-borderHover">
        <Link :href="product.show_url" class="flex w-full flex-col">
            <div class="relative h-36 shrink-0 overflow-hidden bg-inputBg sm:h-auto sm:aspect-square">
                <img v-if="product.image_url" :src="product.image_url" :alt="product.title" class="h-full w-full object-cover transition group-hover:scale-105" />
                <i v-else :class="[product.visual_icon, 'flex h-full items-center justify-center text-6xl text-buttonPrimary']"></i>
                <span class="absolute left-2 top-2 rounded bg-card/90 px-2 py-1 text-[11px] font-black text-buttonPrimary">{{ product.badge }}</span>
            </div>
            <div class="flex flex-1 flex-col p-2 sm:p-3">
                <p class="text-[11px] font-bold uppercase tracking-wide text-secondary">
                    {{ categoryLabel }}
                </p>
                <h3 class="mt-1 line-clamp-2 min-h-[2.25rem] text-xs font-bold text-primary group-hover:text-buttonPrimary sm:min-h-[2.5rem] sm:text-sm">
                    {{ product.title }}
                </h3>
                <p class="mt-1 hidden text-xs leading-5 text-secondary sm:line-clamp-2">
                    {{ shortDescription(product.description) }}
                </p>
                <div class="mt-auto pt-3">
                    <p class="text-base font-black text-primary sm:text-lg">{{ grossPrice }}</p>
                    <p v-if="reviewCount" class="mt-1 flex flex-wrap items-center gap-x-1 gap-y-0.5 text-xs font-bold text-air-orange">
                        <i class="las la-star text-sm"></i>
                        <span>{{ reviewSummary.rating_avg }} / 5</span>
                        <span class="text-secondary">({{ reviewCount }})</span>
                        <span v-if="verifiedPurchaseCount" class="inline-flex items-center gap-1 text-success">
                            <i class="las la-check-circle text-sm"></i>
                            <span>{{ verifiedPurchaseCount }} {{ $t('verifiziert') }}</span>
                        </span>
                    </p>
                    <p v-if="wishlistCount" class="mt-1 flex items-center gap-1 text-xs font-bold text-error">
                        <i :class="[isWishlisted ? 'las' : 'lar', 'la-heart text-sm']"></i>
                        <span>{{ wishlistCount }}</span>
                        <span class="text-secondary">{{ $t('gemerkt') }}</span>
                    </p>
                    <p class="hidden text-xs text-secondary sm:block">{{ taxInfo }}</p>
                    <p v-if="oldPrice" class="text-xs text-secondary line-through">{{ oldPrice }}</p>
                </div>
                <div class="mt-2 hidden items-center justify-between gap-2 text-xs text-secondary sm:flex">
                    <span class="inline-flex min-w-0 items-center gap-1">
                        <span class="flex h-5 w-5 shrink-0 items-center justify-center overflow-hidden rounded-full bg-buttonPrimary/10 text-[9px] font-black text-buttonPrimary">
                            <img v-if="product.provider_profile?.logo_url" :src="product.provider_profile.logo_url" :alt="product.provider_profile.name" class="h-full w-full object-cover" />
                            <span v-else>{{ product.provider_profile?.initials || 'AM' }}</span>
                        </span>
                        <span class="truncate">{{ providerName }}</span>
                        <i v-if="product.provider_profile?.verified" class="las la-check-circle text-base text-success"></i>
                    </span>
                    <span class="shrink-0 font-semibold text-primary">{{ availabilityLabel }}</span>
                </div>
                <div class="mt-3 hidden flex-wrap gap-1 sm:flex">
                    <span
                        v-for="badge in product.trust_badges?.slice(0, 2)"
                        :key="`${product.id}-${badge}`"
                        class="rounded bg-muted px-2 py-1 text-[11px] font-bold text-secondary"
                    >
                        {{ badge }}
                    </span>
                </div>
                <span class="mt-3 inline-flex w-full items-center justify-center gap-2 rounded border border-buttonPrimary/40 px-3 py-2 text-xs font-black text-buttonPrimary transition group-hover:bg-buttonPrimary group-hover:text-buttonTextPrimary">
                    {{ $t("Details ansehen") }}
                    <i class="las la-arrow-right text-base"></i>
                </span>
            </div>
        </Link>
    </article>
</template>


