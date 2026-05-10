<script setup>
import { computed, ref } from 'vue'
import { Link, router, usePage } from '@inertiajs/vue3'
import Subnav from '@/Components/Guest/Subnav.vue'
import Footer from '@/Components/Guest/Footer.vue'
import SeoHead from '@/Components/Guest/SeoHead.vue'
import AdSlot from '@/Components/Ads/AdSlot.vue'

const props = defineProps({
    canLogin: Boolean,
    canRegister: Boolean,
    authUser: { type: Object, default: null },
    cart: { type: Object, default: () => ({ items_count: 0 }) },
    products: { type: Object, default: () => ({ data: [], links: [], total: 0, per_page: 40 }) },
    featuredProducts: { type: Array, default: () => [] },
    flashDeals: { type: Array, default: () => [] },
    essentialDeals: { type: Array, default: () => [] },
    learningDeals: { type: Array, default: () => [] },
    serviceDeals: { type: Array, default: () => [] },
    outfitPlans: { type: Array, default: () => [] },
    sportCategories: { type: Array, default: () => [] },
    officialStores: { type: Array, default: () => [] },
    categories: { type: Array, default: () => [] },
    segments: { type: Array, default: () => [] },
    pricingCountries: { type: Array, default: () => [] },
    marketplaceVisuals: { type: Object, default: () => ({}) },
    filters: { type: Object, default: () => ({}) },
})

const page = usePage()
const currentUser = computed(() => props.authUser || page.props.auth?.user || null)
const form = ref({
    search: props.filters.search || '',
    category: props.filters.category || '',
    segment: props.filters.segment || '',
    country: props.filters.country || '',
})

const categoryLabels = {
    product: 'Produkt',
    course: 'Kurs',
    camp: 'Camp',
    service: 'Service',
    outfit_subscription: 'Outfit-Abo',
}

const productItems = computed(() => props.products?.data || [])
const allOfferItems = computed(() => [...productItems.value, ...(props.outfitPlans || [])])
const paginationLinks = computed(() => (props.products?.links || []).filter((link) => link.url))
const totalProducts = computed(() => (props.products?.total || productItems.value.length) + (props.outfitPlans?.length || 0))
const cartItemCount = computed(() => Number(props.cart?.items_count || 0))
const heroProduct = computed(() => props.featuredProducts[0] || props.flashDeals[0] || productItems.value[0] || null)
const heroSideProducts = computed(() => (props.featuredProducts.length ? props.featuredProducts : props.flashDeals).slice(1, 4))
const sideBannerUrl = computed(() => props.marketplaceVisuals.side_banner || '/images/marketplace/airmius-marketplace-side-banner.png')
const sideBannerDimensions = computed(() => props.marketplaceVisuals.dimensions?.side_banner || { width: 306, height: 786 })
const sideBannerStyle = computed(() => ({
    backgroundImage: `linear-gradient(180deg, rgba(5, 11, 22, 0.08), rgba(5, 11, 22, 0.18) 45%, rgba(5, 11, 22, 0.75)), url("${sideBannerUrl.value}")`,
    width: `${Math.max(208, Math.min(288, Number(sideBannerDimensions.value.width || 306)))}px`,
}))
const heroImageUrl = computed(() => props.marketplaceVisuals.hero_banner || heroProduct.value?.image_url || '')
const saleBannerUrl = computed(() => props.marketplaceVisuals.sale_banner || '')
const activeSegment = computed(() => props.segments.find((segment) => segment.value === form.value.segment) || props.segments[0] || null)
const segmentLookup = computed(() => Object.fromEntries(props.segments.map((segment) => [segment.value || 'all', segment])))
const productGroups = computed(() => {
    const groups = allOfferItems.value.reduce((carry, product) => {
        const key = product.segment || 'equipment'

        if (!carry[key]) {
            carry[key] = []
        }

        carry[key].push(product)

        return carry
    }, {})

    return Object.entries(groups).map(([key, items]) => ({
        key,
        label: segmentLookup.value[key]?.label || categoryLabels[items[0]?.category] || 'Angebote',
        icon: segmentLookup.value[key]?.icon || 'las la-shopping-bag',
        items,
    }))
})

const quickTiles = computed(() => [
    { label: 'Flash Deals', hint: 'Heute beliebt', icon: 'las la-bolt', category: '', search: '' },
    { label: 'Produkte', hint: 'Equipment', icon: 'las la-shopping-bag', category: 'product', search: '' },
    { label: 'Kurse', hint: 'Online & vor Ort', icon: 'las la-video', category: 'course', search: '' },
    { label: 'Camps', hint: 'Events & Training', icon: 'las la-campground', category: 'camp', search: '' },
    { label: 'Services', hint: 'Analyse & Beratung', icon: 'las la-hands-helping', category: 'service', search: '' },
    { label: 'Outfit-Abo', hint: 'Sportkleidung', icon: 'las la-tshirt', category: 'outfit_subscription', search: '' },
])

const formatPrice = (cents, currency = 'EUR') => new Intl.NumberFormat('de-DE', {
    style: 'currency',
    currency: currency || 'EUR',
}).format((cents || 0) / 100)

const price = (item) => item.price || {
    gross_cents: item.price_cents,
    net_cents: item.price_cents,
    tax_cents: 0,
    currency: item.currency || 'EUR',
    tax_rate: 0,
    tax_label: 'Tax',
}

const grossPrice = (item) => formatPrice(price(item).gross_cents, price(item).currency)
const netPrice = (item) => formatPrice(price(item).net_cents, price(item).currency)
const taxInfo = (item) => {
    const quote = price(item)

    return `${netPrice(item)} netto · ${formatPrice(quote.tax_cents, quote.currency)} ${quote.tax_label} (${quote.tax_rate}%)`
}

const shortDescription = (text, length = 92) => {
    if (!text) return 'Sportangebot aus dem Airmius Marketplace.'
    if (text.length <= length) return text

    return `${text.slice(0, length).trim()}...`
}

const search = () => {
    router.get(route('guest.marketplace'), {
        search: form.value.search || undefined,
        category: form.value.category || undefined,
        segment: form.value.segment || undefined,
        country: form.value.country || undefined,
    }, {
        preserveScroll: true,
        preserveState: true,
        replace: true,
    })
}

const reset = () => {
    form.value.search = ''
    form.value.category = ''
    form.value.segment = ''
    form.value.country = ''
    search()
}

const searchCategory = (category) => {
    form.value.search = category.query || ''
    form.value.category = category.category || ''
    form.value.segment = category.segment || ''
    search()
}

const selectQuickTile = (tile) => {
    form.value.search = tile.search || ''
    form.value.category = tile.category || ''
    form.value.segment = tile.segment || ''
    search()
}

const selectSegment = (segment) => {
    form.value.segment = segment.value || ''
    search()
}
</script>

<template>
    <SeoHead
        title="Airmius Sport Marketplace"
        description="Sportfokussierter Marketplace für Produkte, Kurse, Camps und Services. Gäste können direkt ohne Konto bestellen."
    />

    <div class="min-h-screen bg-bg text-primary">
        <Subnav vertical />

        <aside
            class="pointer-events-none fixed left-0 top-0 z-0 hidden h-screen w-[16vw] min-w-[13rem] max-w-[18rem] overflow-hidden bg-buttonPrimary/20 bg-cover bg-center xl:block"
            :style="sideBannerStyle"
        >
            <div class="absolute inset-0 bg-buttonPrimary/10"></div>
            <div class="absolute inset-x-4 top-72 text-center text-buttonTextPrimary drop-shadow">
                <p class="font-heading text-3xl font-900 leading-none">AIRMIUS</p>
                <p class="mt-2 text-sm font-black uppercase tracking-wide">Sport Deals</p>
            </div>
        </aside>

        <aside
            class="pointer-events-none fixed right-0 top-0 z-0 hidden h-screen w-[16vw] min-w-[13rem] max-w-[18rem] scale-x-[-1] overflow-hidden bg-buttonPrimary/20 bg-cover bg-center xl:block"
            :style="sideBannerStyle"
        >
            <div class="absolute inset-0 bg-buttonPrimary/10"></div>
            <div class="absolute inset-x-4 top-72 text-center text-buttonTextPrimary drop-shadow">
                <p class="scale-x-[-1] font-heading text-3xl font-900 leading-none">AIRMIUS</p>
                <p class="mt-2 scale-x-[-1] text-sm font-black uppercase tracking-wide">Marketplace</p>
            </div>
        </aside>

        <main class="relative z-10 pb-24 pt-0 md:pb-14 xl:mx-[16vw]">
            <section class="border-b border-border bg-bg px-4 py-3 shadow-sm">
                <div class="mx-auto flex max-w-7xl items-center justify-between gap-4 overflow-hidden rounded-lg border border-border bg-card px-5 py-3 text-primary shadow-sm">
                    <div class="flex min-w-0 items-center gap-3">
                        <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-full bg-buttonPrimary text-buttonTextPrimary">
                            <i class="las la-running text-2xl"></i>
                        </div>
                        <div class="min-w-0">
                            <p class="font-heading text-lg font-900 leading-tight sm:text-2xl">AIRMIUS Marketplace</p>
                            <p class="truncate text-xs font-semibold text-secondary sm:text-sm">
                                Sport Deals, Kurse, Camps und Services passend zu deinem Design
                            </p>
                        </div>
                    </div>
                    <div class="hidden items-center gap-2 text-sm font-black sm:flex">
                        <Link
                            v-if="currentUser"
                            href="/card"
                            class="relative inline-flex h-10 w-10 items-center justify-center rounded-full bg-buttonPrimary text-buttonTextPrimary"
                            aria-label="Warenkorb"
                            title="Warenkorb"
                        >
                            <i class="las la-shopping-cart text-xl"></i>
                            <span
                                v-if="cartItemCount"
                                class="absolute -right-1 -top-1 flex h-5 min-w-5 items-center justify-center rounded-full bg-error px-1 text-[11px] font-black leading-none text-white ring-2 ring-card"
                            >
                                {{ cartItemCount }}
                            </span>
                        </Link>
                        <span v-else class="rounded-full bg-muted px-3 py-1 text-secondary">Gastbestellung moeglich</span>
                        <span class="rounded-full bg-muted px-3 py-1 text-secondary">Vereine</span>
                        <span class="rounded-full bg-muted px-3 py-1 text-secondary">Athleten</span>
                    </div>
                </div>
            </section>

            <section class="bg-card/70 px-4 py-4 shadow-sm backdrop-blur">
                <form class="mx-auto flex max-w-7xl flex-col gap-3 rounded-2xl border border-border bg-bg/70 p-3 shadow-sm lg:flex-row lg:items-center" @submit.prevent="search">
                    <div class="grid gap-3 sm:grid-cols-3 lg:w-[34rem] lg:shrink-0">
                        <label class="relative block">
                            <span class="sr-only">Kategorie</span>
                            <select v-model="form.category" class="h-12 w-full rounded-xl border-border bg-inputBg px-4 pr-9 text-sm font-bold text-primary outline-none transition focus:border-buttonPrimary focus:ring-2 focus:ring-buttonPrimary/25">
                                <option v-for="category in categories" :key="category.value" :value="category.value">
                                    {{ category.label }}
                                </option>
                            </select>
                        </label>
                        <label class="relative block">
                            <span class="sr-only">Bereich</span>
                            <select v-model="form.segment" class="h-12 w-full rounded-xl border-border bg-inputBg px-4 pr-9 text-sm font-bold text-primary outline-none transition focus:border-buttonPrimary focus:ring-2 focus:ring-buttonPrimary/25">
                                <option v-for="segment in segments" :key="segment.value || 'all'" :value="segment.value">
                                    {{ segment.label }}
                                </option>
                            </select>
                        </label>
                        <label class="relative block">
                            <span class="sr-only">Land</span>
                            <select v-model="form.country" class="h-12 w-full rounded-xl border-border bg-inputBg px-4 pr-9 text-sm font-bold text-primary outline-none transition focus:border-buttonPrimary focus:ring-2 focus:ring-buttonPrimary/25" @change="search">
                                <option value="">Land automatisch</option>
                                <option v-for="country in pricingCountries" :key="country.country" :value="country.country">
                                    {{ country.label }}
                                </option>
                            </select>
                        </label>
                    </div>

                    <div class="relative min-w-0 flex-1">
                        <i class="las la-search absolute left-4 top-1/2 -translate-y-1/2 text-2xl text-buttonPrimary"></i>
                        <input
                            v-model="form.search"
                            class="h-13 min-h-12 w-full rounded-2xl border-border bg-inputBg py-3 pl-12 pr-12 text-sm font-semibold text-primary outline-none transition placeholder:text-secondary/70 focus:border-buttonPrimary focus:ring-2 focus:ring-buttonPrimary/25"
                            placeholder="Was suchst du? Laufschuhe, Camps, Kurse, Analyse..."
                        />
                        <button
                            v-if="form.search"
                            type="button"
                            class="absolute right-3 top-1/2 flex h-7 w-7 -translate-y-1/2 items-center justify-center rounded-full bg-muted text-secondary transition hover:bg-buttonPrimary hover:text-buttonTextPrimary"
                            aria-label="Suche leeren"
                            @click="form.search = ''"
                        >
                            <i class="las la-times"></i>
                        </button>
                    </div>

                    <div class="grid grid-cols-[1fr_auto] gap-2 lg:w-auto lg:shrink-0">
                        <button class="h-12 rounded-xl bg-buttonPrimary px-6 text-sm font-black text-buttonTextPrimary shadow-sm transition hover:bg-buttonPrimaryHover focus:outline-none focus:ring-2 focus:ring-buttonPrimary/30">
                            Suchen
                        </button>
                        <button type="button" class="h-12 rounded-xl border border-border bg-card px-4 text-sm font-bold text-primary transition hover:bg-muted focus:outline-none focus:ring-2 focus:ring-buttonPrimary/20" @click="reset">
                            Reset
                        </button>
                    </div>
                </form>
            </section>

            <section class="mx-auto grid max-w-7xl gap-4 px-4 py-4 lg:grid-cols-[13rem_1fr_13rem]">
                <aside class="rounded bg-card p-2 shadow-sm">
                    <button
                        v-for="category in sportCategories"
                        :key="category.label"
                        class="flex w-full items-center gap-2 rounded px-2 py-2 text-left text-xs font-semibold text-primary transition hover:bg-muted hover:text-buttonPrimary"
                        @click="searchCategory(category)"
                    >
                        <i :class="[category.icon, 'text-lg text-buttonPrimary']"></i>
                        <span class="truncate">{{ category.label }}</span>
                    </button>
                </aside>

                <section class="grid gap-4 md:grid-cols-[1fr_15rem]">
                    <Link
                        :href="heroProduct?.show_url || route('guest.marketplace')"
                        class="relative min-h-[19rem] overflow-hidden rounded bg-buttonPrimary shadow-sm"
                    >
                        <img
                            v-if="heroImageUrl"
                            :src="heroImageUrl"
                            :alt="heroProduct?.title || 'Airmius Marketplace'"
                            class="absolute inset-0 h-full w-full object-cover"
                        />
                        <div class="absolute inset-0 bg-gradient-to-r from-black/75 via-black/35 to-transparent"></div>
                        <div class="relative flex min-h-[19rem] max-w-lg flex-col justify-center p-6 text-white">
                            <p class="text-xs font-black uppercase tracking-wide text-white/80">Airmius Marketplace</p>
                            <h1 class="mt-2 font-heading text-4xl font-900 leading-tight md:text-5xl">
                                Sport Deals für Training, Team und Wettkampf
                            </h1>
                            <p class="mt-4 text-sm leading-6 text-white/90">
                                Weniger scrollen, schneller finden: Kategorien, Aktionen und kuratierte Reihen statt alle Produkte auf einmal.
                            </p>
                            <span class="mt-5 inline-flex w-fit rounded bg-buttonPrimary px-4 py-3 text-sm font-black text-buttonTextPrimary">
                                Jetzt entdecken
                            </span>
                        </div>
                    </Link>

                    <div class="grid gap-4">
                        <div class="rounded bg-card p-4 shadow-sm">
                            <p class="text-sm font-black text-primary">Hilfe & Bestellung</p>
                            <p class="mt-1 text-xs leading-5 text-secondary">Gastbestellung, Login-Bestellung und Anbieterangebote sind vorbereitet.</p>
                        </div>
                        <Link :href="currentUser ? route('auth.commerce.index') : route('login')" class="rounded bg-card p-4 shadow-sm transition hover:bg-muted">
                            <p class="text-sm font-black text-primary">Anbieter werden</p>
                            <p class="mt-1 text-xs leading-5 text-secondary">Vereine, Trainer und Shops können Angebote einstellen.</p>
                        </Link>
                        <Link
                            v-for="product in heroSideProducts"
                            :key="product.id"
                            :href="product.show_url"
                            class="flex gap-3 rounded bg-card p-3 shadow-sm transition hover:bg-muted"
                        >
                            <div class="h-12 w-12 shrink-0 overflow-hidden rounded bg-inputBg">
                                <img v-if="product.image_url" :src="product.image_url" :alt="product.title" class="h-full w-full object-cover" />
                                <i v-else :class="[product.visual_icon, 'flex h-full items-center justify-center text-2xl text-buttonPrimary']"></i>
                            </div>
                            <div class="min-w-0 flex-1">
                                <p class="line-clamp-2 break-words text-xs font-black leading-4 text-primary">{{ product.title }}</p>
                                <p class="text-xs font-bold text-buttonPrimary">{{ grossPrice(product) }} brutto</p>
                            </div>
                        </Link>
                    </div>
                </section>

                <aside class="hidden lg:block">
                    <AdSlot placement="sidebar" variant="sidebar">
                        <template #fallback>
                            <div class="relative min-h-[27rem] overflow-hidden rounded border border-border bg-card p-5 text-primary shadow-sm">
                                <img v-if="saleBannerUrl" :src="saleBannerUrl" alt="" class="absolute inset-0 h-full w-full object-cover" />
                                <div v-if="saleBannerUrl" class="absolute inset-0 bg-gradient-to-b from-black/70 via-black/35 to-black/70"></div>
                                <div class="relative flex min-h-[27rem] flex-col justify-between">
                                    <div :class="saleBannerUrl ? 'text-white' : 'text-primary'">
                                        <p class="text-xs font-black uppercase tracking-wide opacity-75">Aktion</p>
                                        <p class="mt-2 font-heading text-2xl font-900 leading-tight">Sport Sale</p>
                                        <p class="mt-2 text-sm font-semibold leading-6">
                                            Produkte, Camps und Kurse aus deinem Sportnetzwerk.
                                        </p>
                                    </div>
                                    <div class="rounded-lg border border-border bg-bg/95 p-4 text-primary shadow-sm">
                                        <div class="flex items-center gap-3">
                                            <span class="flex h-11 w-11 shrink-0 items-center justify-center rounded-full bg-buttonPrimary text-buttonTextPrimary">
                                                <i class="las la-bolt text-2xl"></i>
                                            </span>
                                            <div>
                                                <p class="text-xs font-bold uppercase text-secondary">Deals</p>
                                                <p class="text-2xl font-black leading-none">bis -40%</p>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </template>
                    </AdSlot>
                </aside>
            </section>

            <section class="mx-auto max-w-7xl px-4">
                <div class="grid gap-3 rounded bg-card p-4 shadow-sm sm:grid-cols-2 lg:grid-cols-6">
                    <button
                        v-for="tile in quickTiles"
                        :key="tile.label"
                        class="flex items-center gap-3 rounded bg-muted p-3 text-left transition hover:border-borderHover hover:bg-table"
                        @click="selectQuickTile(tile)"
                    >
                        <span class="flex h-12 w-12 shrink-0 items-center justify-center rounded-full bg-buttonPrimary text-buttonTextPrimary">
                            <i :class="[tile.icon, 'text-2xl']"></i>
                        </span>
                        <span class="min-w-0">
                            <span class="block truncate text-sm font-black text-primary">{{ tile.label }}</span>
                            <span class="block truncate text-xs text-secondary">{{ tile.hint }}</span>
                        </span>
                    </button>
                </div>
            </section>

            <section class="mx-auto mt-4 max-w-7xl px-4">
                <div class="rounded bg-card p-3 shadow-sm">
                    <div class="flex items-center justify-between gap-3">
                        <div>
                            <h2 class="text-sm font-black text-primary">Produktbereiche</h2>
                            <p class="text-xs text-secondary">Aktiv: {{ activeSegment?.label || 'Alle Bereiche' }}</p>
                        </div>
                        <button class="text-xs font-bold text-buttonPrimary" @click="selectSegment({ value: '' })">Alle anzeigen</button>
                    </div>
                    <div class="mt-3 flex gap-2 overflow-x-auto overscroll-x-contain rounded-lg pb-2 [scrollbar-color:theme(colors.border)_transparent] [scrollbar-width:thin]">
                        <button
                            v-for="segment in segments"
                            :key="segment.value || 'all'"
                            type="button"
                            class="flex shrink-0 items-center gap-2 rounded border px-3 py-2 text-xs font-bold transition"
                            :class="form.segment === segment.value ? 'border-buttonPrimary bg-buttonPrimary text-buttonTextPrimary' : 'border-border bg-inputBg text-primary hover:border-borderHover'"
                            @click="selectSegment(segment)"
                        >
                            <i :class="[segment.icon, 'text-base']"></i>
                            {{ segment.label }}
                        </button>
                    </div>
                </div>
            </section>

            <section class="mx-auto mt-4 max-w-7xl px-4">
                <div class="rounded bg-buttonPrimary px-4 py-3 text-buttonTextPrimary shadow-sm">
                    <div class="flex items-center justify-between gap-4">
                        <h2 class="flex items-center gap-2 text-lg font-black">
                            <i class="las la-bolt text-2xl"></i>
                            Flash Deals
                        </h2>
                        <span class="text-sm font-bold">Limitierte Demo-Angebote</span>
                    </div>
                </div>
                <div class="grid grid-cols-2 gap-2 rounded-b bg-card p-3 shadow-sm md:grid-cols-4 xl:grid-cols-6">
                    <Link
                        v-for="product in flashDeals"
                        :key="product.id"
                        :href="product.show_url"
                        class="group overflow-hidden rounded border border-border bg-card transition hover:border-borderHover"
                    >
                        <div class="relative aspect-[4/3] overflow-hidden bg-inputBg">
                            <img v-if="product.image_url" :src="product.image_url" :alt="product.title" class="h-full w-full object-cover transition group-hover:scale-105" />
                            <i v-else :class="[product.visual_icon, 'flex h-full items-center justify-center text-5xl text-buttonPrimary']"></i>
                            <span class="absolute right-2 top-2 rounded bg-muted px-2 py-1 text-[11px] font-black text-error">-{{ 12 + (product.id % 38) }}%</span>
                        </div>
                        <div class="p-2">
                            <h3 class="line-clamp-2 min-h-[2.25rem] text-xs font-semibold text-primary">{{ product.title }}</h3>
                            <p class="mt-1 text-sm font-black text-primary">{{ grossPrice(product) }}</p>
                            <p class="text-[11px] text-secondary">brutto · {{ netPrice(product) }} netto</p>
                            <p v-if="product.old_price_cents" class="text-[11px] text-secondary line-through">{{ formatPrice(product.old_price_cents, price(product).currency) }}</p>
                        </div>
                    </Link>
                </div>
            </section>

            <section class="mx-auto mt-4 max-w-7xl px-4">
                <AdSlot placement="marketplace_card" variant="banner" :fallback="false" />
            </section>

            <section class="mx-auto mt-4 grid max-w-7xl gap-4 px-4 xl:grid-cols-[1fr_20rem]">
                <div class="rounded bg-card p-4 shadow-sm">
                    <h2 class="text-center text-lg font-black text-primary">Alles für deinen Sportalltag</h2>
                    <div class="mt-4 grid grid-cols-2 gap-3 md:grid-cols-4 xl:grid-cols-6">
                        <Link
                            v-for="product in essentialDeals"
                            :key="product.id"
                            :href="product.show_url"
                            class="text-center"
                        >
                            <div class="mx-auto aspect-square max-w-[8rem] overflow-hidden rounded-full bg-buttonPrimary/20">
                                <img v-if="product.image_url" :src="product.image_url" :alt="product.title" class="h-full w-full object-cover" />
                                <i v-else :class="[product.visual_icon, 'flex h-full items-center justify-center text-5xl text-white']"></i>
                            </div>
                            <p class="mt-2 line-clamp-2 text-xs font-semibold text-primary">{{ product.title }}</p>
                        </Link>
                    </div>
                </div>

                <div class="rounded bg-card p-4 shadow-sm">
                    <h2 class="text-lg font-black text-primary">Offizielle Stores</h2>
                    <div class="mt-3 grid grid-cols-2 gap-2">
                        <div
                            v-for="store in officialStores"
                            :key="store.name"
                            class="rounded border border-border bg-muted p-3 text-center"
                        >
                            <i :class="[store.icon, 'text-3xl text-buttonPrimary']"></i>
                            <p class="mt-1 truncate text-xs font-black text-primary">{{ store.name }}</p>
                            <p class="text-xs font-bold text-error">{{ store.discount }}</p>
                        </div>
                    </div>
                </div>
            </section>

            <section class="mx-auto mt-4 max-w-7xl space-y-4 px-4">
                <div v-if="learningDeals.length" class="rounded bg-card shadow-sm">
                    <div class="flex items-center justify-between border-b border-border px-4 py-3">
                        <h2 class="text-lg font-black text-primary">Kurse & Camps</h2>
                        <button class="text-sm font-bold text-buttonPrimary" @click="selectQuickTile({ category: 'course' })">Mehr sehen</button>
                    </div>
                    <div class="grid grid-cols-2 gap-2 p-3 md:grid-cols-5">
                        <Link v-for="product in learningDeals" :key="product.id" :href="product.show_url" class="group">
                            <div class="aspect-[4/3] overflow-hidden rounded bg-inputBg">
                                <img v-if="product.image_url" :src="product.image_url" :alt="product.title" class="h-full w-full object-cover transition group-hover:scale-105" />
                                <i v-else :class="[product.visual_icon, 'flex h-full items-center justify-center text-5xl text-buttonPrimary']"></i>
                            </div>
                            <p class="mt-2 line-clamp-2 text-xs font-semibold text-primary">{{ product.title }}</p>
                            <p class="text-sm font-black text-primary">{{ grossPrice(product) }}</p>
                            <p class="text-[11px] text-secondary">{{ netPrice(product) }} netto</p>
                        </Link>
                    </div>
                </div>

                <div v-if="serviceDeals.length" class="rounded bg-card shadow-sm">
                    <div class="flex items-center justify-between border-b border-border px-4 py-3">
                        <h2 class="text-lg font-black text-primary">Services & Analysen</h2>
                        <button class="text-sm font-bold text-buttonPrimary" @click="selectQuickTile({ category: 'service' })">Mehr sehen</button>
                    </div>
                    <div class="grid grid-cols-2 gap-2 p-3 md:grid-cols-5">
                        <Link v-for="product in serviceDeals" :key="product.id" :href="product.show_url" class="group">
                            <div class="aspect-[4/3] overflow-hidden rounded bg-inputBg">
                                <img v-if="product.image_url" :src="product.image_url" :alt="product.title" class="h-full w-full object-cover transition group-hover:scale-105" />
                                <i v-else :class="[product.visual_icon, 'flex h-full items-center justify-center text-5xl text-buttonPrimary']"></i>
                            </div>
                            <p class="mt-2 line-clamp-2 text-xs font-semibold text-primary">{{ product.title }}</p>
                            <p class="text-sm font-black text-primary">{{ grossPrice(product) }}</p>
                            <p class="text-[11px] text-secondary">{{ netPrice(product) }} netto</p>
                        </Link>
                    </div>
                </div>

                <div v-if="outfitPlans.length" class="rounded bg-card shadow-sm">
                    <div class="flex items-center justify-between border-b border-border px-4 py-3">
                        <h2 class="text-lg font-black text-primary">Outfit-Abos</h2>
                        <button class="text-sm font-bold text-buttonPrimary" @click="selectQuickTile({ category: 'outfit_subscription' })">Mehr sehen</button>
                    </div>
                    <div class="grid grid-cols-2 gap-2 p-3 md:grid-cols-4">
                        <Link v-for="plan in outfitPlans" :key="plan.id" :href="route('login')" class="rounded border border-border p-3 transition hover:border-borderHover">
                            <div class="flex h-24 items-center justify-center rounded bg-muted">
                                <i class="las la-tshirt text-5xl text-buttonPrimary"></i>
                            </div>
                            <p class="mt-2 line-clamp-2 text-xs font-semibold text-primary">{{ plan.title }}</p>
                            <p class="text-sm font-black text-primary">{{ grossPrice(plan) }}</p>
                            <p class="text-[11px] text-secondary">{{ netPrice(plan) }} netto</p>
                        </Link>
                    </div>
                </div>
            </section>

            <section class="mx-auto mt-4 max-w-7xl px-4">
                <div class="rounded bg-card shadow-sm">
                    <div class="flex flex-wrap items-center justify-between gap-3 border-b border-border px-4 py-3">
                        <div>
                            <h2 class="text-lg font-black text-primary">Alle Angebote</h2>
                            <p class="text-xs text-secondary">
                                {{ totalProducts }} Treffer, angezeigt werden maximal {{ products.per_page || 40 }} pro Seite.
                            </p>
                        </div>
                        <Link :href="currentUser ? route('auth.commerce.index') : route('login')" class="rounded bg-buttonPrimary px-4 py-2 text-sm font-black text-buttonTextPrimary hover:bg-buttonPrimaryHover">
                            Angebot einstellen
                        </Link>
                    </div>

                    <div class="space-y-5 p-3">
                        <div v-for="group in productGroups" :key="group.key" class="rounded border border-border bg-bg/40 p-3">
                            <div class="mb-3 flex items-center justify-between gap-3">
                                <h3 class="flex items-center gap-2 text-base font-black text-primary">
                                    <i :class="[group.icon, 'text-xl text-buttonPrimary']"></i>
                                    {{ group.label }}
                                </h3>
                                <span class="rounded bg-muted px-2 py-1 text-xs font-bold text-secondary">{{ group.items.length }} Angebote</span>
                            </div>

                            <div class="grid grid-cols-2 gap-2 md:grid-cols-4 xl:grid-cols-5">
                                <article
                                    v-for="product in group.items"
                                    :key="product.id"
                                    class="group overflow-hidden rounded border border-border bg-card transition hover:border-borderHover"
                                >
                                    <Link :href="product.show_url" class="block">
                                        <div class="relative aspect-square overflow-hidden bg-inputBg">
                                            <img v-if="product.image_url" :src="product.image_url" :alt="product.title" class="h-full w-full object-cover transition group-hover:scale-105" />
                                            <i v-else :class="[product.visual_icon, 'flex h-full items-center justify-center text-6xl text-buttonPrimary']"></i>
                                            <span class="absolute left-2 top-2 rounded bg-card/90 px-2 py-1 text-[11px] font-black text-buttonPrimary">{{ product.badge }}</span>
                                        </div>
                                        <div class="p-3">
                                            <p class="text-[11px] font-bold uppercase tracking-wide text-secondary">
                                                {{ categoryLabels[product.category] || product.category }}
                                            </p>
                                            <h3 class="mt-1 line-clamp-2 min-h-[2.5rem] text-sm font-bold text-primary group-hover:text-buttonPrimary">
                                                {{ product.title }}
                                            </h3>
                                            <p class="mt-1 hidden text-xs leading-5 text-secondary sm:line-clamp-2">
                                                {{ shortDescription(product.description, 78) }}
                                            </p>
                                            <div class="mt-3">
                                                <p class="text-lg font-black text-primary">{{ grossPrice(product) }}</p>
                                                <p class="text-xs text-secondary">{{ taxInfo(product) }}</p>
                                                <p v-if="product.old_price_cents" class="text-xs text-secondary line-through">{{ formatPrice(product.old_price_cents, price(product).currency) }}</p>
                                            </div>
                                            <div class="mt-2 flex items-center justify-between text-xs text-secondary">
                                                <span>{{ product.rating }} / 5</span>
                                                <span>{{ product.sold_count }} verkauft</span>
                                            </div>
                                            <p class="mt-2 truncate text-xs text-secondary">{{ product.provider_name || 'Airmius Marketplace' }}</p>
                                        </div>
                                    </Link>
                                </article>
                            </div>
                        </div>

                        <div v-if="!allOfferItems.length" class="rounded border border-border bg-muted p-8 text-center">
                            <p class="text-lg font-bold text-primary">Keine passenden Angebote gefunden.</p>
                            <p class="mt-2 text-sm text-secondary">Passe Suche oder Kategorie an, dann werden wieder Angebote angezeigt.</p>
                        </div>
                    </div>

                    <div v-if="paginationLinks.length > 1" class="flex flex-wrap justify-center gap-2 border-t border-border px-4 py-4">
                        <Link
                            v-for="link in paginationLinks"
                            :key="`${link.label}-${link.url}`"
                            :href="link.url"
                            preserve-scroll
                            class="rounded border px-3 py-2 text-sm font-bold"
                            :class="link.active ? 'border-borderHover bg-buttonPrimary text-buttonTextPrimary' : 'border-border bg-card text-primary hover:bg-muted'"
                            v-html="link.label"
                        />
                    </div>
                </div>
            </section>
        </main>

        <Footer />
    </div>
</template>
