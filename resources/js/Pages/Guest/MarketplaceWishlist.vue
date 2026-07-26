<script setup>
import { computed } from 'vue'
import { Link, router, usePage } from '@inertiajs/vue3'
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
    products: { type: Array, default: () => [] },
    marketplaceVisuals: { type: Object, default: () => ({}) },
})

const page = usePage()
const { isDark } = useTheme()
const { t, locale } = useI18n()
const currentUser = computed(() => props.authUser || page.props.auth?.user || null)
const marketplaceLogo = computed(() => logoWordmark(isDark.value))
const productItems = computed(() => props.products || [])
const cartItemCount = computed(() => Number(props.cart?.items_count || 0))
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
const formatMoney = (cents, currency = 'EUR') => new Intl.NumberFormat(localeCode.value, {
    style: 'currency',
    currency: currency || 'EUR',
}).format(Number(cents || 0) / 100)

const price = (product) => product.price || {
    gross_cents: product.price_cents,
    currency: product.currency || 'EUR',
}

const availabilityLabel = (product) => {
    if (product.offer_type === 'online_course' || product.offer_type === 'training_plan' || product.category === 'service') {
        return t('Digital / Termin')
    }

    if (!product.manages_stock) {
        return t('Auf Anfrage')
    }

    const stock = Number(product.stock_quantity || 0)

    if (stock <= 0) {
        return t('Aktuell vergriffen')
    }

    return stock <= 5 ? t('Nur {count} verfügbar', { count: stock }) : t('Auf Lager')
}

const removeFromWishlist = (product) => {
    router.delete(route('auth.commerce.products.wishlist.destroy', product.id), {
        preserveScroll: true,
    })
}
</script>

<template>
    <SeoHead
        :title="$t('Meine Wunschliste')"
        :description="$t('Gespeicherte Marketplace-Angebote auf Airmius ansehen.')"
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

        <main class="relative z-10 mx-auto max-w-[86rem] pb-24 pt-0 md:pb-14">
            <section class="border-b border-border bg-bg px-3 py-2 shadow-sm sm:px-4 sm:py-3">
                <div class="mx-auto flex max-w-7xl flex-wrap items-center justify-between gap-2 rounded-lg border border-border bg-card px-3 py-2 text-primary shadow-sm sm:flex-nowrap sm:gap-3 sm:px-5 sm:py-3">
                    <Link :href="route('guest.marketplace')" class="flex min-w-0 flex-1 items-center gap-3">
                        <img :src="marketplaceLogo" alt="AIRMIUS" class="h-9 w-auto max-w-[8.25rem] shrink-0 object-contain sm:h-12 sm:max-w-none" @error="applyLogoFallback">
                        <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-full bg-buttonPrimary text-buttonTextPrimary">
                            <i class="las la-arrow-left text-xl"></i>
                        </span>
                        <span class="hidden min-w-0 sm:block">
                            <span class="block font-heading text-lg font-900 leading-tight sm:text-2xl">{{ $t("Meine Wunschliste") }}</span>
                            <span class="block truncate text-xs font-semibold text-secondary sm:text-sm">{{ $t("Gespeicherte Sport Deals und Anbieter wiederfinden") }}</span>
                        </span>
                    </Link>

                    <div class="flex shrink-0 items-center justify-end gap-2 text-sm font-black">
                        <Link
                            :href="route('guest.marketplace')"
                            class="hidden rounded-full border border-border px-3 py-2 text-secondary transition hover:border-buttonPrimary hover:text-primary sm:inline-flex"
                        >
                            {{ $t("Marketplace") }}
                        </Link>
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
                    </div>
                </div>
            </section>

            <section class="mx-auto max-w-7xl px-3 py-4 sm:px-4">
                <div v-if="page.props.flash?.success" class="mb-4 rounded-lg border border-success/30 bg-success/10 px-4 py-3 text-sm font-semibold text-success">
                    {{ page.props.flash.success }}
                </div>

                <div class="rounded border border-border bg-card shadow-sm">
                    <div class="flex flex-wrap items-center justify-between gap-3 border-b border-border px-4 py-3">
                        <div>
                            <p class="text-xs font-black uppercase tracking-wide text-secondary">{{ $t("Wunschliste") }}</p>
                            <h1 class="text-lg font-black text-primary sm:text-2xl">{{ $t("Gemerkte Angebote") }}</h1>
                        </div>
                        <span class="rounded bg-muted px-3 py-1 text-xs font-bold text-secondary">
                            {{ productItems.length }} {{ $t('Angebote') }}
                        </span>
                    </div>

                    <div v-if="productItems.length" class="grid grid-cols-1 gap-3 p-3 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4">
                        <article
                            v-for="product in productItems"
                            :key="product.id"
                            class="flex h-full flex-col overflow-hidden rounded border border-border bg-bg transition hover:border-borderHover"
                        >
                            <Link :href="product.show_url" class="group block">
                                <div class="relative aspect-[4/3] overflow-hidden bg-inputBg">
                                    <img v-if="product.image_url" :src="product.image_url" :alt="product.title" class="h-full w-full object-cover transition group-hover:scale-105" />
                                    <i v-else :class="[product.visual_icon, 'flex h-full items-center justify-center text-6xl text-buttonPrimary']"></i>
                                    <span class="absolute left-2 top-2 rounded bg-card/90 px-2 py-1 text-[11px] font-black text-buttonPrimary">
                                        {{ product.badge }}
                                    </span>
                                </div>
                            </Link>

                            <div class="flex flex-1 flex-col p-3">
                                <p class="text-[11px] font-bold uppercase tracking-wide text-secondary">
                                    {{ categoryLabels[product.category] || product.category }}
                                </p>
                                <Link :href="product.show_url" class="mt-1 line-clamp-2 min-h-[2.5rem] text-sm font-black text-primary hover:text-buttonPrimary">
                                    {{ product.title }}
                                </Link>

                                <div class="mt-auto pt-4">
                                    <p class="text-lg font-black text-primary">
                                        {{ formatMoney(price(product).gross_cents, price(product).currency) }}
                                    </p>
                                    <div class="mt-2 flex items-center justify-between gap-2 text-xs text-secondary">
                                        <span class="inline-flex min-w-0 items-center gap-1">
                                            <span class="flex h-5 w-5 shrink-0 items-center justify-center overflow-hidden rounded-full bg-buttonPrimary/10 text-[9px] font-black text-buttonPrimary">
                                                <img v-if="product.provider_profile?.logo_url" :src="product.provider_profile.logo_url" :alt="product.provider_profile.name" class="h-full w-full object-cover" />
                                                <span v-else>{{ product.provider_profile?.initials || 'AM' }}</span>
                                            </span>
                                            <span class="truncate">{{ product.provider_profile?.name || product.provider_name || 'Airmius Marketplace' }}</span>
                                        </span>
                                        <span class="shrink-0 font-semibold text-primary">{{ availabilityLabel(product) }}</span>
                                    </div>
                                </div>

                                <div class="mt-3 flex gap-2">
                                    <Link :href="product.show_url" class="inline-flex flex-1 items-center justify-center gap-2 rounded bg-buttonPrimary px-3 py-2 text-xs font-black text-buttonTextPrimary hover:bg-buttonPrimaryHover">
                                        {{ $t("Details ansehen") }}
                                        <i class="las la-arrow-right text-base"></i>
                                    </Link>
                                    <button
                                        type="button"
                                        class="inline-flex h-9 w-9 shrink-0 items-center justify-center rounded border border-error/30 bg-error/10 text-error transition hover:bg-error/15"
                                        :aria-label="$t('Aus Wunschliste entfernen')"
                                        :title="$t('Aus Wunschliste entfernen')"
                                        @click="removeFromWishlist(product)"
                                    >
                                        <i class="las la-heart-broken text-lg"></i>
                                    </button>
                                </div>
                            </div>
                        </article>
                    </div>

                    <div v-else class="p-8 text-center">
                        <span class="mx-auto flex h-14 w-14 items-center justify-center rounded-full bg-buttonPrimary/10 text-buttonPrimary">
                            <i class="lar la-heart text-3xl"></i>
                        </span>
                        <p class="mt-4 text-lg font-black text-primary">{{ $t("Noch keine gemerkten Angebote") }}</p>
                        <p class="mx-auto mt-2 max-w-xl text-sm leading-6 text-secondary">
                            {{ $t("Merke Produkte, Kurse, Camps und Services, die du später vergleichen möchtest.") }}
                        </p>
                        <Link :href="route('guest.marketplace')" class="mt-5 inline-flex items-center justify-center gap-2 rounded bg-buttonPrimary px-4 py-2 text-sm font-black text-buttonTextPrimary hover:bg-buttonPrimaryHover">
                            {{ $t("Marketplace ansehen") }}
                            <i class="las la-arrow-right text-base"></i>
                        </Link>
                    </div>
                </div>
            </section>
        </main>

        <Footer />
    </div>
</template>
