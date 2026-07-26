<script setup>
import { computed } from 'vue'
import { Link, usePage } from '@inertiajs/vue3'
import Subnav from '@/Components/Guest/Subnav.vue'
import Footer from '@/Components/Guest/Footer.vue'
import SeoHead from '@/Components/Guest/SeoHead.vue'
import UserCard from '@/Components/Auth/UserCard.vue'
import { useTheme } from '@/services/useTheme'
import { applyLogoFallback, logoWordmark } from '@/services/logoAssets'
import { useI18n } from 'vue-i18n'

const props = defineProps({
    canLogin: Boolean,
    canRegister: Boolean,
    authUser: { type: Object, default: null },
    cart: { type: Object, default: () => ({ items_count: 0 }) },
    provider: { type: Object, required: true },
    products: { type: Object, default: () => ({ data: [], links: [], total: 0 }) },
    marketplaceVisuals: { type: Object, default: () => ({}) },
})

const page = usePage()
const { isDark } = useTheme()
const { t, locale } = useI18n()
const currentUser = computed(() => props.authUser || page.props.auth?.user || null)
const marketplaceLogo = computed(() => logoWordmark(isDark.value))
const cartItemCount = computed(() => Number(props.cart?.items_count || 0))
const productItems = computed(() => props.products?.data || [])
const paginationLinks = computed(() => (props.products?.links || []).filter((link) => link.url))
const sideBannerUrl = computed(() => props.marketplaceVisuals.side_banner || '/images/marketplace/airmius-marketplace-side-banner.png')
const sideBannerDimensions = computed(() => props.marketplaceVisuals.dimensions?.side_banner || { width: 192, height: 1080 })
const sideBannerStyle = computed(() => ({
    backgroundImage: `linear-gradient(180deg, rgba(5, 11, 22, 0.28), rgba(5, 11, 22, 0.45)), url("${sideBannerUrl.value}")`,
    width: `${Math.max(148, Math.min(192, Number(sideBannerDimensions.value.width || 192)))}px`,
}))

const categoryLabels = {
    product: t('Produkt'),
    course: t('Kurs'),
    camp: t('Camp'),
    service: t('Service'),
    outfit_subscription: t('Outfit-Abo'),
}

const localeCode = computed(() => ({ ar: 'ar-EG', fr: 'fr-FR', en: 'en-US', de: 'de-DE' })[locale.value] || 'de-DE')
const formatPrice = (cents, currency = 'EUR') => new Intl.NumberFormat(localeCode.value, {
    style: 'currency',
    currency: currency || 'EUR',
}).format((cents || 0) / 100)

const price = (item) => item.price || {
    gross_cents: item.price_cents,
    currency: item.currency || 'EUR',
}

const grossPrice = (item) => formatPrice(price(item).gross_cents, price(item).currency)
const shortDescription = (text, length = 110) => {
    if (!text) return t('Marketplace-Angebot auf Airmius.')
    if (text.length <= length) return text

    return `${text.slice(0, length).trim()}...`
}
</script>

<template>
    <SeoHead
        :title="$t('marketplace.provider.seo_title', { name: provider.name })"
        :description="provider.description || $t('marketplace.provider.seo_description', { name: provider.name })"
        :image="provider.logo_url || provider.cover_url || undefined"
    />

    <div class="min-h-screen bg-bg text-primary">
        <Subnav vertical />

        <aside
            class="pointer-events-none fixed left-0 top-0 z-0 hidden h-screen overflow-hidden bg-buttonPrimary/10 bg-cover bg-center opacity-50 2xl:block"
            :style="sideBannerStyle"
            aria-hidden="true"
        >
            <div class="absolute inset-0 bg-bg/35"></div>
        </aside>

        <aside
            class="pointer-events-none fixed right-0 top-0 z-0 hidden h-screen scale-x-[-1] overflow-hidden bg-buttonPrimary/10 bg-cover bg-center opacity-50 2xl:block"
            :style="sideBannerStyle"
            aria-hidden="true"
        >
            <div class="absolute inset-0 bg-bg/35"></div>
        </aside>

        <main class="relative z-10 mx-auto max-w-[86rem] pb-24 pt-0 md:pb-14 md:pr-28 2xl:pr-24">
            <section class="border-b border-border bg-bg px-4 py-3 shadow-sm">
                <div class="mx-auto flex max-w-7xl items-center justify-between gap-3 rounded border border-border bg-card px-4 py-3 text-primary shadow-sm sm:px-5">
                    <Link :href="route('guest.marketplace')" class="flex min-w-0 flex-1 items-center gap-3">
                        <img :src="marketplaceLogo" alt="AIRMIUS" class="h-10 w-auto max-w-[10.5rem] shrink-0 object-contain sm:h-12 sm:max-w-none" @error="applyLogoFallback">
                        <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-full bg-buttonPrimary text-buttonTextPrimary">
                            <i class="las la-arrow-left text-xl"></i>
                        </span>
                        <span class="hidden min-w-0 sm:block">
                            <span class="block font-heading text-lg font-900 leading-tight sm:text-2xl">{{ $t("Marketplace") }}</span>
                            <span class="block truncate text-xs font-semibold text-secondary sm:text-sm">{{ $t("Zurück zu allen Angeboten") }}</span>
                        </span>
                    </Link>
                    <div class="flex shrink-0 items-center justify-end gap-2 text-sm font-black">
                        <Link
                            v-if="currentUser"
                            :href="route('auth.commerce.cart.index')"
                            class="relative inline-flex h-10 w-10 items-center justify-center rounded-full bg-buttonPrimary text-buttonTextPrimary"
                            :aria-label="$t('Warenkorb')"
                            :title="$t('Warenkorb')"
                        >
                            <i class="las la-shopping-cart text-xl"></i>
                            <span
                                v-if="cartItemCount"
                                class="absolute -right-1 -top-1 flex h-5 min-w-5 items-center justify-center rounded-full bg-error px-1 text-[11px] font-black leading-none text-white ring-2 ring-card"
                            >
                                {{ cartItemCount }}
                            </span>
                        </Link>
                        <UserCard v-if="currentUser" />
                        <template v-else>
                            <Link v-if="canLogin" :href="route('login', { redirect: '/marketplace' })" class="rounded-full border border-border px-3 py-2 text-secondary transition hover:border-buttonPrimary hover:text-primary">
                                {{ $t("Anmelden") }}
                            </Link>
                            <Link v-if="canRegister" :href="route('register', { redirect: '/marketplace' })" class="rounded-full bg-buttonPrimary px-3 py-2 text-buttonTextPrimary transition hover:bg-buttonPrimaryHover">
                                {{ $t("Registrieren") }}
                            </Link>
                        </template>
                    </div>
                </div>
            </section>

            <section class="mx-auto max-w-7xl px-4 py-4">
                <div class="overflow-hidden rounded border border-border bg-card shadow-sm">
                    <div class="relative min-h-[18rem] bg-buttonPrimary">
                        <img v-if="provider.cover_url" :src="provider.cover_url" :alt="provider.name" class="absolute inset-0 h-full w-full object-cover" />
                        <div class="absolute inset-0 bg-gradient-to-r from-black/80 via-black/45 to-black/15"></div>
                        <div class="relative flex min-h-[18rem] flex-col justify-end gap-4 p-6 text-white md:flex-row md:items-end md:justify-between md:p-8">
                            <div class="flex min-w-0 flex-col gap-4 sm:flex-row sm:items-end">
                                <span class="flex h-24 w-24 shrink-0 items-center justify-center overflow-hidden rounded border border-white/30 bg-white/15 text-2xl font-black text-white backdrop-blur">
                                    <img v-if="provider.logo_url" :src="provider.logo_url" :alt="provider.name" class="h-full w-full object-cover" />
                                    <span v-else>{{ provider.initials || 'AM' }}</span>
                                </span>
                                <div class="min-w-0">
                                    <p class="text-xs font-black uppercase tracking-wide text-white/75">{{ provider.type }}</p>
                                    <h1 class="mt-2 font-heading text-4xl font-900 leading-tight md:text-5xl">
                                        {{ provider.name }}
                                    </h1>
                                    <p class="mt-3 max-w-2xl text-sm font-semibold leading-6 text-white/90">{{ provider.description }}</p>
                                </div>
                            </div>
                            <div class="grid gap-2 text-sm font-semibold text-white/90">
                                <span class="inline-flex items-center gap-2 rounded bg-white/15 px-3 py-2 backdrop-blur">
                                    <i class="las la-map-marker-alt text-lg"></i>
                                    {{ provider.location || $t('Online') }}
                                </span>
                                <span v-if="provider.verified" class="inline-flex items-center gap-2 rounded bg-success/20 px-3 py-2 text-white backdrop-blur">
                                    <i class="las la-check-circle text-lg"></i>
                                    {{ $t("Verifizierter Anbieter") }}
                                </span>
                            </div>
                        </div>
                    </div>

                    <div class="grid gap-3 border-t border-border bg-bg/60 p-4 md:grid-cols-3">
                        <div class="rounded border border-border bg-card p-4">
                            <p class="text-xs font-black uppercase text-secondary">{{ $t("Angebote") }}</p>
                            <p class="mt-1 text-2xl font-black text-primary">{{ products.total || productItems.length }}</p>
                        </div>
                        <div class="rounded border border-border bg-card p-4">
                            <p class="text-xs font-black uppercase text-secondary">{{ $t("Standort") }}</p>
                            <p class="mt-1 text-sm font-semibold text-primary">{{ provider.location || $t('Online') }}</p>
                        </div>
                        <div class="rounded border border-border bg-card p-4">
                            <p class="text-xs font-black uppercase text-secondary">{{ $t("Vertrauen") }}</p>
                            <p class="mt-1 text-sm font-semibold text-primary">{{ provider.verified ? $t('Verifiziertes Profil') : $t('Marketplace-Anbieter') }}</p>
                        </div>
                    </div>
                    <div v-if="provider.locations?.length" class="border-t border-border bg-bg/60 p-4">
                        <p class="text-xs font-black uppercase tracking-wide text-secondary">{{ $t('Standorte & Abholung') }}</p>
                        <div class="mt-3 grid gap-3 md:grid-cols-3">
                            <article v-for="location in provider.locations" :key="location.id" class="rounded border border-border bg-card p-4">
                                <div class="flex items-start justify-between gap-3">
                                    <div>
                                        <h3 class="font-black text-primary">{{ location.name }}</h3>
                                        <p class="mt-1 text-sm text-secondary">{{ location.address }}</p>
                                    </div>
                                    <span v-if="location.pickup_enabled" class="rounded bg-buttonPrimary/10 px-2 py-1 text-[11px] font-black text-buttonPrimary">{{ $t('Abholung') }}</span>
                                </div>
                                <p v-if="location.opening_hours" class="mt-3 text-xs text-secondary">{{ location.opening_hours }}</p>
                                <p v-if="location.note" class="mt-2 text-xs text-secondary">{{ location.note }}</p>
                            </article>
                        </div>
                    </div>
                </div>
            </section>

            <section class="mx-auto max-w-7xl px-4">
                <div class="rounded border border-border bg-card shadow-sm">
                    <div class="flex flex-wrap items-center justify-between gap-3 border-b border-border px-4 py-3">
                        <div>
                            <p class="text-xs font-black uppercase tracking-wide text-secondary">{{ $t("Anbieter-Sortiment") }}</p>
                            <h2 class="text-lg font-black text-primary">{{ $t('Alle Angebote von {name}', { name: provider.name }) }}</h2>
                        </div>
                        <Link :href="route('guest.marketplace')" class="rounded border border-border px-4 py-2 text-sm font-semibold text-primary hover:bg-muted">
                            {{ $t("Marketplace ansehen") }}
                        </Link>
                    </div>

                    <div v-if="productItems.length" class="grid grid-cols-1 gap-3 p-3 sm:grid-cols-2 md:grid-cols-3 xl:grid-cols-4">
                        <article
                            v-for="product in productItems"
                            :key="product.id"
                            class="group overflow-hidden rounded border border-border bg-bg transition hover:border-borderHover"
                        >
                            <Link :href="product.show_url" class="block">
                                <div class="relative aspect-[4/3] overflow-hidden bg-inputBg">
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
                                        {{ shortDescription(product.description) }}
                                    </p>
                                    <p class="mt-3 text-lg font-black text-primary">{{ grossPrice(product) }}</p>
                                    <div class="mt-3 flex flex-wrap gap-1">
                                        <span
                                            v-for="badge in product.trust_badges?.slice(0, 2)"
                                            :key="`${product.id}-${badge}`"
                                            class="rounded bg-muted px-2 py-1 text-[11px] font-bold text-secondary"
                                        >
                                            {{ badge }}
                                        </span>
                                    </div>
                                </div>
                            </Link>
                        </article>
                    </div>

                    <div v-else class="p-8 text-center">
                        <p class="text-lg font-bold text-primary">{{ $t("Noch keine verfügbaren Angebote.") }}</p>
                        <p class="mt-2 text-sm text-secondary">{{ $t("Schau später wieder vorbei oder entdecke andere Anbieter im Marketplace.") }}</p>
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
