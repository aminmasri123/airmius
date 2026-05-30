<script setup>
import { useForm, Link, router, usePage } from '@inertiajs/vue3'
import Subnav from '@/Components/Guest/Subnav.vue'
import Footer from '@/Components/Guest/Footer.vue'
import SeoHead from '@/Components/Guest/SeoHead.vue'
import UserCard from '@/Components/Auth/UserCard.vue'
import { useTheme } from '@/services/useTheme'
import { applyLogoFallback, logoWordmark } from '@/services/logoAssets'
import { computed, nextTick, ref, watch } from 'vue'
import { useI18n } from 'vue-i18n'

const props = defineProps({
    canLogin: Boolean,
    canRegister: Boolean,
    authUser: { type: Object, default: null },
    cart: { type: Object, default: () => ({ items_count: 0 }) },
    product: { type: Object, required: true },
    pricingCountries: { type: Array, default: () => [] },
    checkoutAddress: { type: Object, default: () => ({}) },
    profileAddress: { type: Object, default: null },
    shippingAddresses: { type: Array, default: () => [] },
    paymentProviders: { type: Array, default: () => [] },
    marketplaceVisuals: { type: Object, default: () => ({}) },
    relatedProducts: { type: Array, default: () => [] },
})

const page = usePage()
const { isDark } = useTheme()
const { t, locale } = useI18n()
const currentUser = computed(() => props.authUser || page.props.auth?.user || null)
const isAuthenticated = computed(() => Boolean(currentUser.value))
const marketplaceLogo = computed(() => logoWordmark(isDark.value))
const marketplaceReturnTo = '/marketplace'
const loginHref = computed(() => route('login', { redirect: marketplaceReturnTo }))
const registerHref = computed(() => route('register', { redirect: marketplaceReturnTo }))
const cartItemCount = computed(() => Number(props.cart?.items_count || 0))
const selectedGalleryImage = ref(null)
const selectedAttributes = ref({})
const showCheckout = ref(false)
const checkoutSection = ref(null)
const initialAddress = props.profileAddress || props.shippingAddresses[0] || props.checkoutAddress || {}
const form = useForm({
    guest_name: currentUser.value?.name || '',
    guest_email: currentUser.value?.email || '',
    provider: props.paymentProviders[0]?.value || '',
    accepted_terms: false,
    coupon_code: '',
    quantity: 1,
    shipping_country: initialAddress.country || props.product.price?.country || 'DE',
    shipping_state: initialAddress.state || '',
    shipping_postal_code: initialAddress.postal_code || '',
    shipping_city: initialAddress.city || '',
    shipping_street: initialAddress.street || '',
    shipping_house_number: initialAddress.house_number || '',
    save_shipping_address: false,
    shipping_address_label: '',
    customer_type: 'consumer',
    customer_company: '',
    customer_vat_id: '',
})
const addressChoice = ref(props.profileAddress ? 'profile' : (props.shippingAddresses[0] ? `saved:${props.shippingAddresses[0].id}` : 'new'))

const formatMoney = (cents, currency = 'EUR') => new Intl.NumberFormat('de-DE', {
    style: 'currency',
    currency: currency || 'EUR',
}).format(Number(cents || 0) / 100)

const price = computed(() => props.product.price || {
    gross_cents: props.product.price_cents,
    net_cents: props.product.price_cents,
    tax_cents: 0,
    currency: props.product.currency || 'EUR',
    tax_rate: 0,
    tax_label: 'Tax',
})
const unitGrossCents = computed(() => price.value.item_gross_cents ?? price.value.gross_cents ?? props.product.price_cents)
const productUrl = computed(() => typeof window !== 'undefined' ? window.location.href.split('#')[0] : props.product.show_url || '')
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
const productFacts = computed(() => [
    [t('Artikelnummer'), props.product.sku || '-'],
    [t('Kategorie'), categoryLabels[props.product.category] || props.product.category],
    [t('Versand'), props.product.is_shippable ? t('Versandpflichtig') : t('Digital / ohne Versand')],
    [t('Rückgabe'), t('{days} Tage ({policy})', { days: props.product.return_window_days ?? 14, policy: props.product.return_policy_type || 'standard' })],
])
const purchaseFacts = computed(() => [
    { label: t('Lieferung'), value: props.product.delivery_label || (props.product.is_shippable ? t('Versand nach Bestellung') : t('Digital / Termin')), icon: 'las la-truck' },
    { label: t('Rückgabe'), value: props.product.return_label || t('{days} Tage', { days: props.product.return_window_days ?? 14 }), icon: 'las la-undo' },
    { label: t('Anbieter'), value: props.product.provider_name || t('Airmius Anbieter'), icon: 'las la-store' },
])
const galleryImages = computed(() => {
    const images = props.product.gallery_images?.length ? props.product.gallery_images : [props.product.image_url]

    return [...new Set(images.filter(Boolean))]
})
const activeProductImage = computed(() => selectedGalleryImage.value || galleryImages.value[0] || props.product.image_url)
const attributeOptions = (value) => String(value || '')
    .split(/[|,]/)
    .map((option) => option.trim())
    .filter(Boolean)
const displayAttributes = computed(() => {
    if (props.product.attribute_options?.length) {
        return props.product.attribute_options
    }

    return (props.product.product_attributes || []).map((attribute) => ({
        name: attribute.name,
        values: attributeOptions(attribute.value),
    }))
})
const selectedVariant = computed(() => {
    if (!props.product.variants?.length) {
        return null
    }

    return props.product.variants.find((variant) => (variant.attributes || []).every((attribute) => selectedAttributes.value[attribute.name] === attribute.value)) || null
})
const visiblePriceCents = computed(() => selectedVariant.value?.price_cents ?? unitGrossCents.value)
const visibleStock = computed(() => selectedVariant.value?.stock_quantity ?? props.product.stock_quantity)
const maxQuantity = computed(() => Math.max(1, Number(visibleStock.value || 1)))
const clampQuantity = (value) => Math.min(maxQuantity.value, Math.max(1, Math.floor(Number(value || 1))))
const selectedQuantity = computed(() => clampQuantity(form.quantity))
const selectedItemGrossCents = computed(() => visiblePriceCents.value * selectedQuantity.value)
const selectedTotalGrossCents = computed(() => selectedItemGrossCents.value + Number(price.value.shipping_gross_cents || 0))
const paymentProviderItems = computed(() => props.paymentProviders || [])
const selectedPaymentProvider = computed(() => paymentProviderItems.value.find((provider) => provider.value === form.provider) || null)
const selectedProviderLabel = computed(() => selectedPaymentProvider.value?.label || form.provider)
const checkoutUnavailable = computed(() => !paymentProviderItems.value.length)
const checkoutStep = ref('address')
const checkoutSteps = [
    { key: 'address', label: t('Adresse'), icon: 'las la-map-marker-alt' },
    { key: 'payment', label: t('Menge'), icon: 'las la-shopping-bag' },
    { key: 'review', label: t('Prüfen'), icon: 'las la-clipboard-check' },
]
const isShippableProduct = computed(() => Boolean(props.product.is_shippable))
const hasGuestContact = computed(() => isAuthenticated.value
    || (String(form.guest_name || '').trim() && String(form.guest_email || '').trim()))
const hasRequiredAddress = computed(() => {
    if (!form.shipping_country) {
        return false
    }

    if (!isShippableProduct.value) {
        return true
    }

    return Boolean(
        String(form.shipping_street || '').trim()
        && String(form.shipping_house_number || '').trim()
        && String(form.shipping_postal_code || '').trim()
        && String(form.shipping_city || '').trim()
    )
})
const canContinueAddress = computed(() => Boolean(hasGuestContact.value && hasRequiredAddress.value))
const canContinuePayment = computed(() => Boolean(!checkoutUnavailable.value && form.provider && selectedQuantity.value >= 1))
const checkoutStepHint = computed(() => {
    if (checkoutStep.value === 'address' && !canContinueAddress.value) {
        return t('Bitte Kontakt und Lieferdaten vervollständigen.')
    }

    if (checkoutStep.value === 'payment' && checkoutUnavailable.value) {
        return t('Aktuell ist noch keine Zahlungsart für diesen Marketplace konfiguriert.')
    }

    if (checkoutStep.value === 'payment' && !canContinuePayment.value) {
        return t('Bitte Zahlungsart und Menge prüfen.')
    }

    if (checkoutStep.value === 'review' && !form.accepted_terms) {
        return t('AGB und Widerruf müssen vor dem Kauf bestätigt werden.')
    }

    return ''
})
const isCheckoutStepDisabled = (stepKey) => {
    if (stepKey === 'payment') {
        return !canContinueAddress.value
    }

    if (stepKey === 'review') {
        return !canContinueAddress.value || !canContinuePayment.value
    }

    return false
}
const goToCheckoutStep = (stepKey) => {
    if (!isCheckoutStepDisabled(stepKey)) {
        checkoutStep.value = stepKey
    }
}
const nextCheckoutStep = () => {
    if (checkoutStep.value === 'address' && canContinueAddress.value) {
        checkoutStep.value = 'payment'
        return
    }

    if (checkoutStep.value === 'payment' && canContinuePayment.value) {
        checkoutStep.value = 'review'
    }
}
const previousCheckoutStep = () => {
    if (checkoutStep.value === 'review') {
        checkoutStep.value = 'payment'
        return
    }

    if (checkoutStep.value === 'payment') {
        checkoutStep.value = 'address'
    }
}
const productAvailabilitySchema = computed(() => {
    if (props.product.manages_stock && Number(visibleStock.value || 0) <= 0) {
        return 'https://schema.org/OutOfStock'
    }

    return 'https://schema.org/InStock'
})
const productSchema = computed(() => ({
    '@context': 'https://schema.org',
    '@type': 'Product',
    name: props.product.title,
    description: props.product.description || t('Marketplace-Angebot {title}', { title: props.product.title }),
    image: galleryImages.value,
    sku: props.product.sku || undefined,
    category: categoryLabels[props.product.category] || props.product.category,
    brand: {
        '@type': 'Brand',
        name: props.product.provider_name || 'Airmius Marketplace',
    },
    offers: {
        '@type': 'Offer',
        url: productUrl.value,
        priceCurrency: price.value.currency || 'EUR',
        price: (Number(visiblePriceCents.value || 0) / 100).toFixed(2),
        availability: productAvailabilitySchema.value,
        itemCondition: 'https://schema.org/NewCondition',
        seller: {
            '@type': 'Organization',
            name: props.product.provider_name || 'Airmius Marketplace',
        },
    },
}))
const savedAddressOptions = computed(() => props.shippingAddresses || [])
const regionNames = typeof Intl !== 'undefined' && Intl.DisplayNames
    ? new Intl.DisplayNames([locale.value || 'de'], { type: 'region' })
    : null
const deliveryCountryOptions = computed(() => props.pricingCountries.map((country) => ({
    country: country.country,
    label: `${country.country} - ${regionNames?.of(country.country) || country.country}`,
})))
const relatedProductItems = computed(() => props.relatedProducts || [])
const isLearningProduct = computed(() => ['online_course', 'training_plan'].includes(props.product.offer_type))
const productFaqItems = computed(() => [
    {
        question: t('Wie bekomme ich das Angebot?'),
        answer: props.product.delivery_label || (props.product.is_shippable ? t('Der Anbieter bereitet den Versand nach der Bestellung vor.') : t('Du erhältst nach dem Kauf die weiteren Informationen digital oder per E-Mail.')),
    },
    {
        question: t('Wer ist mein Ansprechpartner?'),
        answer: t('{provider} ist für Angebotsdetails und Erfüllung zuständig. Airmius stellt Checkout, Status und Belege bereit.', { provider: props.product.provider_profile?.name || props.product.provider_name || t('Der Anbieter') }),
    },
    {
        question: t('Wie wird der Endpreis berechnet?'),
        answer: t('Steuer, Versand und Gesamtpreis werden anhand von Lieferland und Kundentyp vor dem Abschluss angezeigt.'),
    },
    {
        question: t('Welche Rückgabe gilt?'),
        answer: props.product.return_label || t('Die Rückgabe richtet sich nach Angebotstyp, Richtlinie und gesetzlicher Lage.'),
    },
])

const applyAddress = (address) => {
    if (!address) {
        return
    }

    form.shipping_country = address.country || 'DE'
    form.shipping_state = address.state || ''
    form.shipping_postal_code = address.postal_code || ''
    form.shipping_city = address.city || ''
    form.shipping_street = address.street || ''
    form.shipping_house_number = address.house_number || ''
}

watch(addressChoice, (choice) => {
    if (choice === 'profile') {
        applyAddress(props.profileAddress)
        updateCountry()
        return
    }

    if (choice.startsWith('saved:')) {
        applyAddress(savedAddressOptions.value.find((address) => String(address.id) === choice.slice(6)))
        updateCountry()
        return
    }

    form.save_shipping_address = false
})

const normalizeQuantity = () => {
    form.quantity = selectedQuantity.value
}

watch(() => form.quantity, (value) => {
    const normalized = clampQuantity(value)
    if (Number(value) !== normalized) {
        form.quantity = normalized
    }
})

const openCheckout = async () => {
    showCheckout.value = true
    checkoutStep.value = 'address'
    await nextTick()
    checkoutSection.value?.scrollIntoView({ behavior: 'smooth', block: 'start' })
}

const checkout = () => {
    form.quantity = selectedQuantity.value

    form.post(isAuthenticated.value
        ? route('auth.commerce.products.checkout', props.product.id)
        : route('guest.marketplace.products.checkout', props.product.id)
    )
}

const addToCart = () => {
    if (!isAuthenticated.value) {
        router.visit(route('login'))
        return
    }

    router.post(route('auth.commerce.cart.items.store', props.product.id), { quantity: selectedQuantity.value }, { preserveScroll: true })
}

const updateCountry = () => {
    router.get(route('guest.marketplace.products.show', props.product.id), {
        shipping_country: form.shipping_country || undefined,
    }, {
        preserveScroll: true,
        preserveState: true,
        replace: true,
    })
}
</script>

<template>
    <SeoHead
        :title="$t('marketplace.product.seo_title', { title: product.title })"
        :description="product.description || $t('Marketplace-Angebot auf Airmius ansehen und als Gast bestellen.')"
        type="product"
        :image="activeProductImage || undefined"
        :schema="productSchema"
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
                <div class="mx-auto flex max-w-7xl items-center justify-between gap-3 rounded-lg border border-border bg-card px-4 py-3 text-primary shadow-sm sm:px-5">
                    <Link :href="route('guest.marketplace')" class="flex min-w-0 flex-1 items-center gap-3">
                        <img :src="marketplaceLogo" alt="AIRMIUS" class="h-10 w-auto max-w-[10.5rem] shrink-0 object-contain sm:h-12 sm:max-w-none" @error="applyLogoFallback">
                        <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-full bg-buttonPrimary text-buttonTextPrimary">
                            <i class="las la-arrow-left text-xl"></i>
                        </span>
                        <span class="hidden min-w-0 sm:block">
                            <span class="block font-heading text-lg font-900 leading-tight sm:text-2xl">{{ $t("AIRMIUS Marketplace") }}</span>
                            <span class="block truncate text-xs font-semibold text-secondary sm:text-sm">{{ $t("Zurück zu allen Sport Deals") }}</span>
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
                            <Link
                                v-if="canLogin"
                                :href="loginHref"
                                class="rounded-full border border-border px-3 py-2 text-secondary transition hover:border-buttonPrimary hover:text-primary"
                            >
                                {{ $t("Anmelden") }}
                            </Link>
                            <Link
                                v-if="canRegister"
                                :href="registerHref"
                                class="rounded-full bg-buttonPrimary px-3 py-2 text-buttonTextPrimary transition hover:bg-buttonPrimaryHover"
                            >
                                {{ $t("Registrieren") }}
                            </Link>
                        </template>
                    </div>
                </div>
            </section>

            <section class="mx-auto max-w-7xl px-4 py-4">
                <div :class="['grid items-start gap-4', showCheckout ? 'lg:grid-cols-[minmax(0,1fr)_24rem]' : '']">
                    <article class="overflow-hidden rounded border border-border bg-card shadow-sm">
                        <div class="grid items-start gap-0 xl:grid-cols-[minmax(0,1fr)_20rem]">
                            <div class="relative h-[28rem] max-h-[68vh] min-h-[22rem] w-full bg-inputBg">
                                <img v-if="activeProductImage" :src="activeProductImage" :alt="product.title" class="absolute inset-0 h-full w-full object-cover" />
                                <div v-else class="absolute inset-0 flex items-center justify-center">
                                    <i class="las la-store text-7xl text-buttonPrimary"></i>
                                </div>
                                <div class="absolute inset-0 bg-gradient-to-t from-black/75 via-black/20 to-transparent"></div>
                                <div class="absolute bottom-0 left-0 max-w-3xl p-6 text-white">
                                    <p class="text-xs font-black uppercase tracking-wide text-white/80">{{ categoryLabels[product.category] || product.category }}</p>
                                    <h1 class="mt-2 font-heading text-4xl font-900 leading-tight md:text-5xl">{{ product.title }}</h1>
                                    <p class="mt-3 text-sm font-semibold text-white/90">{{ product.provider_name || $t('Airmius Anbieter') }}</p>
                                </div>
                            </div>
                            <div class="border-l border-border p-5">
                                <p class="text-xs font-black uppercase tracking-wide text-secondary">{{ $t("Produktinformation") }}</p>
                                <dl class="mt-4 grid gap-3">
                                    <div v-for="fact in productFacts" :key="fact[0]" class="rounded border border-border bg-bg p-3">
                                        <dt class="text-[11px] font-bold uppercase text-secondary">{{ fact[0] }}</dt>
                                        <dd class="mt-1 text-sm font-semibold text-primary">{{ fact[1] }}</dd>
                                    </div>
                                </dl>
                                <div v-if="galleryImages.length > 1" class="mt-4">
                                    <p class="text-xs font-black uppercase tracking-wide text-secondary">{{ $t("Bilder") }}</p>
                                    <div class="mt-2 grid grid-cols-4 gap-2">
                                        <button
                                            v-for="image in galleryImages"
                                            :key="image"
                                            type="button"
                                            :class="[
                                                'overflow-hidden rounded border bg-bg',
                                                activeProductImage === image ? 'border-buttonPrimary' : 'border-border'
                                            ]"
                                            @click="selectedGalleryImage = image"
                                        >
                                            <img :src="image" :alt="product.title" class="aspect-square w-full object-cover" />
                                        </button>
                                    </div>
                                </div>
                                <div class="mt-5 rounded border border-border bg-bg p-4">
                                    <p class="text-xs font-black uppercase tracking-wide text-secondary">{{ $t("Preis") }}</p>
                                    <p class="mt-1 text-3xl font-black text-primary">{{ formatMoney(visiblePriceCents, price.currency) }}</p>
                                    <p class="mt-2 text-xs leading-5 text-secondary">
                                        {{ $t("Steuer und Versand werden im Checkout aus Lieferadresse und Kundentyp berechnet.") }}
                                    </p>
                                    <div class="mt-4 grid gap-2 text-xs font-semibold text-secondary">
                                        <p class="flex items-center gap-2">
                                            <i class="las la-shield-alt text-lg text-buttonPrimary"></i>
                                            {{ $t("Anbieter, Preis und Steuer werden vor Abschluss ausgewiesen.") }}
                                        </p>
                                        <p class="flex items-center gap-2">
                                            <i class="las la-undo text-lg text-buttonPrimary"></i>
                                            {{ $t('Rückgabe:') }} {{ product.return_window_days ?? 14 }} {{ $t('Tage nach Richtlinie.') }}
                                        </p>
                                    </div>
                                    <div class="mt-4 grid gap-2 sm:grid-cols-2 xl:grid-cols-1">
                                        <button
                                            type="button"
                                            class="rounded-lg bg-buttonPrimary px-4 py-3 text-sm font-semibold text-buttonTextPrimary hover:bg-buttonPrimaryHover"
                                            @click="openCheckout"
                                        >
                                            {{ $t("Jetzt kaufen") }}
                                        </button>
                                        <button
                                            type="button"
                                            class="rounded-lg border border-border px-4 py-3 text-sm font-semibold text-primary hover:bg-muted"
                                            @click="addToCart"
                                        >
                                            {{ $t("In den Einkaufswagen") }}
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="grid gap-3 border-t border-border bg-bg/60 p-4 md:grid-cols-3">
                            <div
                                v-for="fact in purchaseFacts"
                                :key="fact.label"
                                class="flex items-center gap-3 rounded border border-border bg-card p-3"
                            >
                                <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded bg-buttonPrimary/10 text-buttonPrimary">
                                    <i :class="[fact.icon, 'text-xl']"></i>
                                </span>
                                <span class="min-w-0">
                                    <span class="block text-[11px] font-black uppercase tracking-wide text-secondary">{{ fact.label }}</span>
                                    <span class="block truncate text-sm font-semibold text-primary">{{ fact.value }}</span>
                                </span>
                            </div>
                        </div>
                        <div class="grid gap-5 border-t border-border p-6 lg:grid-cols-[minmax(0,1fr)_18rem]">
                            <div>
                                <h2 class="text-lg font-black text-primary">{{ $t("Beschreibung") }}</h2>
                                <p class="mt-3 whitespace-pre-line text-sm leading-7 text-secondary">
                                    {{ product.description || $t('Keine Beschreibung hinterlegt.') }}
                                </p>
                                <div v-if="isLearningProduct" class="mt-6 grid gap-4 md:grid-cols-2">
                                    <div v-if="product.course_outline?.length" class="rounded-lg border border-border bg-bg p-4">
                                        <h3 class="text-sm font-black uppercase text-secondary">{{ $t("Inhalt") }}</h3>
                                        <ol class="mt-3 space-y-3">
                                            <li v-for="(item, index) in product.course_outline" :key="item" class="flex gap-3 text-sm text-primary">
                                                <span class="flex h-6 w-6 shrink-0 items-center justify-center rounded-full bg-buttonPrimary text-xs font-black text-buttonTextPrimary">{{ index + 1 }}</span>
                                                <span>{{ item }}</span>
                                            </li>
                                        </ol>
                                    </div>
                                    <div v-if="product.learning_goals?.length || product.coaching_enabled" class="rounded-lg border border-border bg-bg p-4">
                                        <h3 class="text-sm font-black uppercase text-secondary">{{ $t("Lernziel") }}</h3>
                                        <ul v-if="product.learning_goals?.length" class="mt-3 space-y-2">
                                            <li v-for="goal in product.learning_goals" :key="goal" class="flex gap-2 text-sm text-primary">
                                                <i class="las la-check mt-0.5 text-lg text-buttonPrimary"></i>
                                                <span>{{ goal }}</span>
                                            </li>
                                        </ul>
                                        <div v-if="product.coaching_enabled" class="mt-4 rounded-lg border border-buttonPrimary/30 bg-buttonPrimary/10 p-3 text-sm text-primary">
                                            <p class="font-bold">{{ $t("Trainer-Feedback inklusive") }}</p>
                                            <p v-if="product.coach_feedback_instructions" class="mt-2 text-secondary">{{ product.coach_feedback_instructions }}</p>
                                            <p v-else class="mt-2 text-secondary">{{ $t("Athleten können Fortschritt und Fragen nach dem Kauf mit dem Trainer teilen.") }}</p>
                                        </div>
                                    </div>
                                </div>

                                <div class="mt-6 rounded border border-border bg-bg p-4">
                                    <h2 class="text-lg font-black text-primary">{{ $t("Häufige Fragen") }}</h2>
                                    <div class="mt-3 divide-y divide-border">
                                        <details
                                            v-for="item in productFaqItems"
                                            :key="item.question"
                                            class="group py-3"
                                        >
                                            <summary class="flex cursor-pointer list-none items-center justify-between gap-3 text-sm font-black text-primary">
                                                {{ item.question }}
                                                <i class="las la-angle-down text-lg text-buttonPrimary transition group-open:rotate-180"></i>
                                            </summary>
                                            <p class="mt-2 text-sm leading-6 text-secondary">{{ item.answer }}</p>
                                        </details>
                                    </div>
                                </div>
                            </div>
                            <div>
                                <div class="rounded border border-border bg-bg p-4">
                                    <p class="text-xs font-black uppercase tracking-wide text-secondary">{{ $t("Anbieter") }}</p>
                                    <div class="mt-3 flex items-center gap-3">
                                        <span class="flex h-12 w-12 shrink-0 items-center justify-center overflow-hidden rounded bg-buttonPrimary/10 text-sm font-black text-buttonPrimary">
                                            <img v-if="product.provider_profile?.logo_url" :src="product.provider_profile.logo_url" :alt="product.provider_profile.name" class="h-full w-full object-cover" />
                                            <span v-else>{{ product.provider_profile?.initials || 'AM' }}</span>
                                        </span>
                                        <span class="min-w-0">
                                            <span class="flex items-center gap-1 text-base font-black text-primary">
                                                {{ product.provider_profile?.name || product.provider_name || $t('Airmius Anbieter') }}
                                                <i v-if="product.provider_profile?.verified" class="las la-check-circle text-lg text-success"></i>
                                            </span>
                                            <span class="mt-1 block text-xs font-semibold text-secondary">{{ product.provider_profile?.type || product.provider_type || $t('Marketplace Anbieter') }}</span>
                                            <span class="mt-1 block text-xs text-secondary">{{ product.provider_profile?.location || $t('Online') }}</span>
                                        </span>
                                    </div>
                                    <div class="mt-3 flex flex-wrap gap-1">
                                        <span
                                            v-for="badge in product.trust_badges"
                                            :key="badge"
                                            class="rounded bg-muted px-2 py-1 text-[11px] font-bold text-secondary"
                                        >
                                            {{ badge }}
                                        </span>
                                    </div>
                                    <div v-if="product.provider_profile?.locations?.length" class="mt-4 space-y-2 rounded border border-border bg-card p-3">
                                        <p class="text-xs font-black uppercase text-secondary">{{ $t('Abholung & Standorte') }}</p>
                                        <div v-for="location in product.provider_profile.locations.slice(0, 3)" :key="location.id" class="text-xs text-secondary">
                                            <p class="font-bold text-primary">{{ location.name }}</p>
                                            <p>{{ location.address }}</p>
                                            <p v-if="location.opening_hours">{{ location.opening_hours }}</p>
                                        </div>
                                    </div>
                                    <Link
                                        v-if="product.provider_profile?.url"
                                        :href="product.provider_profile.url"
                                        class="mt-4 inline-flex w-full items-center justify-center gap-2 rounded border border-buttonPrimary/40 px-3 py-2 text-xs font-black text-buttonPrimary hover:bg-buttonPrimary hover:text-buttonTextPrimary"
                                    >
                                        {{ $t("Anbieterprofil ansehen") }}
                                        <i class="las la-arrow-right text-base"></i>
                                    </Link>
                                </div>

                                <h2 class="mt-6 text-lg font-black text-primary">{{ $t("Varianten") }}</h2>
                                <div v-if="displayAttributes.length" class="mt-3 grid gap-3">
                                    <label v-for="attribute in displayAttributes" :key="attribute.name" class="block">
                                        <span class="text-xs font-bold uppercase text-secondary">{{ attribute.name }}</span>
                                        <select v-model="selectedAttributes[attribute.name]" class="mt-1 w-full rounded-lg border-border bg-bg text-sm font-semibold text-primary">
                                            <option value="">{{ $t("Bitte wählen") }}</option>
                                            <option v-for="option in attribute.values" :key="option" :value="option">
                                                {{ option }}
                                            </option>
                                        </select>
                                    </label>
                                    <div v-if="selectedVariant" class="rounded-lg border border-border bg-bg p-3 text-xs text-secondary">
                                        <p v-if="selectedVariant.sku">{{ $t('Artikelnummer:') }} {{ selectedVariant.sku }}</p>
                                        <p v-if="selectedVariant.stock_quantity !== null">{{ $t('Bestand:') }} {{ selectedVariant.stock_quantity }}</p>
                                    </div>
                                </div>
                                <p v-else class="mt-3 text-sm text-secondary">{{ $t("Noch keine Eigenschaften hinterlegt.") }}</p>

                                <h2 class="mt-6 text-lg font-black text-primary">{{ $t("Merkmale") }}</h2>
                                <ul v-if="product.features?.length" class="mt-3 space-y-2">
                                    <li v-for="feature in product.features" :key="feature" class="flex gap-2 text-sm font-semibold text-primary">
                                        <i class="las la-check mt-0.5 text-lg text-buttonPrimary"></i>
                                        <span>{{ feature }}</span>
                                    </li>
                                </ul>
                                <p v-else class="mt-3 text-sm text-secondary">{{ $t("Noch keine Merkmale hinterlegt.") }}</p>
                            </div>
                        </div>
                    </article>

                    <aside v-if="showCheckout" ref="checkoutSection" class="surface-card h-full p-6">
                        <div v-if="page.props.flash?.success" class="mb-4 rounded-lg border border-success/30 bg-success/10 px-4 py-3 text-sm font-semibold text-success">
                            {{ page.props.flash.success }}
                        </div>
                        <p class="text-xs uppercase text-secondary">{{ $t("Preis") }}</p>
                        <p class="mt-2 text-3xl font-bold text-primary">{{ formatMoney(visiblePriceCents, price.currency) }}</p>
                        <div class="mt-2 rounded-lg border border-border bg-bg p-3 text-sm text-secondary">
                            <p>
                                {{ formatMoney(price.net_cents, price.currency) }} {{ $t('netto') }}
                            </p>
                            <p>
                                {{ formatMoney(price.tax_cents, price.currency) }} {{ price.tax_label }} ({{ price.tax_rate }}%)
                            </p>
                            <p v-if="price.is_estimate" class="mt-2 text-xs">
                                {{ $t("Steuer/Währung sind eine technische Schätzung und werden beim finalen Checkout geprüft.") }}
                            </p>
                        </div>

                        <div class="mt-5 grid grid-cols-3 gap-2">
                            <button
                                v-for="(step, index) in checkoutSteps"
                                :key="step.key"
                                type="button"
                                class="rounded-lg border px-2 py-3 text-center text-[11px] font-black transition sm:text-xs"
                                :class="checkoutStep === step.key
                                    ? 'border-buttonPrimary bg-buttonPrimary text-buttonTextPrimary'
                                    : 'border-border bg-bg text-secondary hover:border-buttonPrimary hover:text-primary'"
                                :disabled="isCheckoutStepDisabled(step.key)"
                                @click="goToCheckoutStep(step.key)"
                            >
                                <span class="mx-auto mb-1 flex h-7 w-7 items-center justify-center rounded-full border border-current/30">
                                    <i :class="[step.icon, 'text-base']"></i>
                                </span>
                                <span class="block">{{ index + 1 }}. {{ step.label }}</span>
                            </button>
                        </div>
                        <p v-if="checkoutStepHint" class="mt-2 rounded-lg border border-warning/30 bg-warning/10 px-3 py-2 text-xs font-semibold text-warning">
                            {{ checkoutStepHint }}
                        </p>

                        <form class="mt-6 space-y-4" @submit.prevent="checkout">
                            <section v-show="checkoutStep === 'address'" class="space-y-4">
                            <div v-if="isAuthenticated" class="rounded-lg border border-border bg-bg p-3">
                                <label class="text-xs font-bold uppercase text-secondary">{{ $t("Adresse") }}</label>
                                <select v-model="addressChoice" class="mt-2 w-full rounded-lg border-border bg-inputBg text-sm text-primary">
                                    <option v-if="profileAddress" value="profile">
                                        {{ $t('Meine Adresse') }}{{ profileAddress.summary ? ` - ${profileAddress.summary}` : '' }}
                                    </option>
                                    <option v-for="address in savedAddressOptions" :key="address.id" :value="`saved:${address.id}`">
                                        {{ address.label }}{{ address.summary ? ` - ${address.summary}` : '' }}
                                    </option>
                                    <option value="new">{{ $t("Neue Lieferadresse") }}</option>
                                </select>
                            </div>

                            <div class="rounded-lg border border-buttonPrimary/20 bg-buttonPrimary/10 p-3 text-sm text-primary">
                                <p class="flex items-center gap-2 font-black">
                                    <i class="las la-lock text-lg text-buttonPrimary"></i>
                                    {{ $t("Sicherer Checkout") }}
                                </p>
                                <div class="mt-3 grid gap-2 text-xs font-semibold text-secondary">
                                    <p class="flex items-center gap-2">
                                        <i :class="[selectedPaymentProvider?.icon || 'las la-credit-card', 'text-base text-buttonPrimary']"></i>
                                        {{ $t('Zahlungsart:') }} {{ selectedProviderLabel || $t('Nicht konfiguriert') }}
                                    </p>
                                    <p class="flex items-center gap-2">
                                        <i class="las la-file-invoice text-base text-buttonPrimary"></i>
                                        {{ $t("Preis, Steuer und Versand werden vor Abschluss angezeigt.") }}
                                    </p>
                                    <p class="flex items-center gap-2">
                                        <i class="las la-envelope text-base text-buttonPrimary"></i>
                                        {{ $t("Bestellstatus und Rechnung kommen per E-Mail.") }}
                                    </p>
                                </div>
                            </div>

                            <div>
                                <label class="text-xs font-semibold uppercase text-secondary">{{ $t("Land der Lieferadresse") }}</label>
                                <select v-model="form.shipping_country" class="mt-1 w-full rounded-lg border-border bg-inputBg text-primary" @change="updateCountry">
                                    <option v-for="country in deliveryCountryOptions" :key="country.country" :value="country.country">
                                        {{ country.label }}
                                    </option>
                                </select>
                                <p class="mt-1 text-xs text-secondary">{{ $t("Die Steuer wird daraus automatisch berechnet.") }}</p>
                                <p v-if="form.errors.shipping_country" class="mt-1 text-sm text-red-400">{{ form.errors.shipping_country }}</p>
                            </div>

                            <div class="grid gap-3 sm:grid-cols-[1fr_7rem]">
                                <div>
                                    <label class="text-xs font-semibold uppercase text-secondary">{{ $t("Straße") }}</label>
                                    <input v-model="form.shipping_street" class="mt-1 w-full rounded-lg border-border bg-inputBg text-primary" autocomplete="shipping street-address" />
                                </div>
                                <div>
                                    <label class="text-xs font-semibold uppercase text-secondary">{{ $t("Nr.") }}</label>
                                    <input v-model="form.shipping_house_number" class="mt-1 w-full rounded-lg border-border bg-inputBg text-primary" autocomplete="shipping address-line2" />
                                </div>
                            </div>

                            <div class="grid gap-3 sm:grid-cols-[8rem_1fr]">
                                <div>
                                    <label class="text-xs font-semibold uppercase text-secondary">{{ $t("PLZ") }}</label>
                                    <input v-model="form.shipping_postal_code" class="mt-1 w-full rounded-lg border-border bg-inputBg text-primary" autocomplete="shipping postal-code" />
                                </div>
                                <div>
                                    <label class="text-xs font-semibold uppercase text-secondary">{{ $t("Ort") }}</label>
                                    <input v-model="form.shipping_city" class="mt-1 w-full rounded-lg border-border bg-inputBg text-primary" autocomplete="shipping address-level2" />
                                </div>
                            </div>

                            <template v-if="isAuthenticated">
                                <label class="flex items-start gap-3 rounded-lg border border-border bg-bg p-3 text-sm text-secondary">
                                    <input v-model="form.save_shipping_address" type="checkbox" class="mt-1 rounded border-border bg-inputBg">
                                    <span>{{ $t("Diese Lieferadresse speichern") }}</span>
                                </label>
                                <input
                                    v-if="form.save_shipping_address"
                                    v-model="form.shipping_address_label"
                                    class="w-full rounded-lg border-border bg-inputBg text-sm text-primary"
                                    :placeholder="$t('Name der Lieferadresse, z. B. Zuhause')"
                                >
                            </template>

                            <div v-if="!isAuthenticated">
                                <label class="text-xs font-semibold uppercase text-secondary">{{ $t("Name") }}</label>
                                <input v-model="form.guest_name" class="mt-1 w-full rounded-lg border-border bg-inputBg text-primary" required autocomplete="name" />
                                <p v-if="form.errors.guest_name" class="mt-1 text-sm text-red-400">{{ form.errors.guest_name }}</p>
                            </div>

                            <div>
                                <label class="text-xs font-semibold uppercase text-secondary">{{ $t("Kundentyp") }}</label>
                                <select v-model="form.customer_type" class="mt-1 w-full rounded-lg border-border bg-inputBg text-primary">
                                    <option value="consumer">{{ $t("Privatkunde") }}</option>
                                    <option value="business">{{ $t("Firma / Verein") }}</option>
                                </select>
                            </div>

                            <div v-if="form.customer_type === 'business'" class="space-y-3">
                                <div>
                                    <label class="text-xs font-semibold uppercase text-secondary">{{ $t("Firma / Verein") }}</label>
                                    <input v-model="form.customer_company" class="mt-1 w-full rounded-lg border-border bg-inputBg text-primary" autocomplete="organization" />
                                </div>
                                <div>
                                    <label class="text-xs font-semibold uppercase text-secondary">{{ $t("USt-IdNr.") }}</label>
                                    <input v-model="form.customer_vat_id" class="mt-1 w-full rounded-lg border-border bg-inputBg text-primary uppercase" :placeholder="$t('z. B. ATU...')" />
                                </div>
                            </div>

                            <div v-if="!isAuthenticated">
                                <label class="text-xs font-semibold uppercase text-secondary">{{ $t("E-Mail") }}</label>
                                <input v-model="form.guest_email" type="email" class="mt-1 w-full rounded-lg border-border bg-inputBg text-primary" required autocomplete="email" />
                                <p v-if="form.errors.guest_email" class="mt-1 text-sm text-red-400">{{ form.errors.guest_email }}</p>
                            </div>

                            <div>
                                <label class="text-xs font-semibold uppercase text-secondary">{{ $t("Zahlungsart") }}</label>
                                <select v-model="form.provider" class="mt-1 w-full rounded-lg border-border bg-inputBg text-primary" :disabled="checkoutUnavailable">
                                    <option v-if="checkoutUnavailable" value="">{{ $t("Keine Zahlungsart konfiguriert") }}</option>
                                    <option v-for="provider in paymentProviderItems" :key="provider.value" :value="provider.value">
                                        {{ provider.label }}
                                    </option>
                                </select>
                                <p v-if="selectedPaymentProvider?.description" class="mt-1 text-xs text-secondary">{{ selectedPaymentProvider.description }}</p>
                                <p v-if="form.errors.provider" class="mt-1 text-sm text-red-400">{{ form.errors.provider }}</p>
                            </div>

                            <button
                                type="button"
                                class="w-full rounded-lg bg-buttonPrimary px-4 py-3 text-sm font-semibold text-buttonTextPrimary hover:bg-buttonPrimaryHover disabled:cursor-not-allowed disabled:opacity-60"
                                :disabled="!canContinueAddress"
                                @click="nextCheckoutStep"
                            >
                                {{ $t("Weiter zu Menge & Preis") }}
                            </button>
                            </section>

                            <section v-show="checkoutStep === 'payment'" class="space-y-4">
                            <div>
                                <label class="text-xs font-semibold uppercase text-secondary">{{ $t("Menge") }}</label>
                                <input
                                    v-model.number="form.quantity"
                                    type="number"
                                    min="1"
                                    :max="maxQuantity"
                                    @input="normalizeQuantity"
                                    @change="normalizeQuantity"
                                    @blur="normalizeQuantity"
                                    class="mt-1 h-12 w-full rounded-lg border-border bg-inputBg text-primary"
                                />
                                <p class="mt-1 text-xs text-secondary">{{ $t('Verfügbar:') }} {{ maxQuantity }}</p>
                                <p v-if="form.errors.quantity" class="mt-1 text-sm text-red-400">{{ form.errors.quantity }}</p>
                            </div>

                            <div class="flex gap-2">
                                <button
                                    type="button"
                                    class="flex-1 rounded-lg border border-border px-4 py-3 text-sm font-semibold text-primary hover:bg-muted"
                                    @click="previousCheckoutStep"
                                >
                                    {{ $t("Zurück") }}
                                </button>
                                <button
                                    type="button"
                                    class="flex-1 rounded-lg bg-buttonPrimary px-4 py-3 text-sm font-semibold text-buttonTextPrimary hover:bg-buttonPrimaryHover disabled:cursor-not-allowed disabled:opacity-60"
                                    :disabled="!canContinuePayment"
                                    @click="nextCheckoutStep"
                                >
                                    {{ $t("Prüfen") }}
                                </button>
                            </div>
                            </section>

                            <section v-show="checkoutStep === 'review'" class="space-y-4">
                            <div class="rounded-lg border border-buttonPrimary/20 bg-buttonPrimary/10 p-3 text-xs font-semibold text-secondary">
                                <p class="mb-2 text-sm font-black text-primary">{{ $t("Bestellung prüfen") }}</p>
                                <div class="grid gap-2">
                                    <p class="flex justify-between gap-3">
                                        <span>{{ $t("Lieferland") }}</span>
                                        <span class="text-right text-primary">{{ form.shipping_country }}</span>
                                    </p>
                                    <p class="flex justify-between gap-3">
                                        <span>{{ $t("Zahlungsart") }}</span>
                                        <span class="text-right text-primary">{{ selectedProviderLabel }}</span>
                                    </p>
                                    <p class="flex justify-between gap-3">
                                        <span>{{ $t("Menge") }}</span>
                                        <span class="text-right text-primary">{{ selectedQuantity }}</span>
                                    </p>
                                </div>
                            </div>

                            <div class="rounded-lg border border-border bg-bg p-3 text-sm text-secondary">
                                <p class="flex justify-between gap-3">
                                    <span>{{ $t("Zwischensumme") }}</span>
                                    <span class="font-semibold text-primary">{{ formatMoney(selectedItemGrossCents, price.currency) }}</span>
                                </p>
                                <p class="mt-1 flex justify-between gap-3">
                                    <span>{{ price.shipping_label || 'Versand' }}</span>
                                    <span class="font-semibold text-primary">{{ formatMoney(price.shipping_gross_cents, price.currency) }}</span>
                                </p>
                                <p class="mt-3 flex justify-between gap-3 border-t border-border pt-3 text-base font-black text-primary">
                                    <span>{{ $t("Gesamt") }}</span>
                                    <span>{{ formatMoney(selectedTotalGrossCents, price.currency) }}</span>
                                </p>
                                <p v-if="price.reverse_charge" class="mt-2 text-xs text-air-blue">{{ $t("Reverse-Charge: Steuerschuld geht auf den Leistungsempfänger über.") }}</p>
                                <p v-else-if="price.tax_rule === 'export_outside_eu'" class="mt-2 text-xs text-air-blue">{{ $t("Export außerhalb der EU: keine EU-MwSt. berechnet.") }}</p>
                            </div>

                            <div v-if="product.learning_course_id" class="rounded-lg border border-border bg-bg p-3">
                                <label class="text-xs font-semibold uppercase text-secondary">{{ $t("Kurs-Gutschein") }}</label>
                                <input v-model="form.coupon_code" class="mt-1 w-full rounded-lg border-border bg-inputBg text-sm text-primary" :placeholder="$t('Code eingeben')">
                                <p v-if="form.errors.coupon_code" class="mt-1 text-sm text-red-400">{{ form.errors.coupon_code }}</p>
                            </div>

                            <label class="flex items-start gap-3 rounded-lg border border-border bg-bg p-3 text-sm text-secondary">
                                <input v-model="form.accepted_terms" type="checkbox" class="mt-1 rounded border-border bg-inputBg">
                                <span>
                                    {{ $t("Ich akzeptiere") }}
                                    <Link :href="route('terms.show')" target="_blank" class="font-semibold text-air-blue underline underline-offset-2" @click.stop>{{ $t("AGB") }}</Link>
                                    {{ $t("und") }}
                                    <Link :href="route('legal.withdrawal')" target="_blank" class="font-semibold text-air-blue underline underline-offset-2" @click.stop>{{ $t("Widerrufshinweise") }}</Link>.
                                    Mir ist bewusst, dass der jeweilige Anbieter für sein Angebot verantwortlich sein kann.
                                </span>
                            </label>
                            <p v-if="form.errors.accepted_terms" class="text-sm text-red-400">{{ form.errors.accepted_terms }}</p>

                            <button
                                type="button"
                                class="w-full rounded-lg border border-border px-4 py-3 text-sm font-semibold text-primary hover:bg-muted"
                                @click="previousCheckoutStep"
                            >
                                {{ $t("Zurück zu Menge") }}
                            </button>

                            <button
                                class="w-full rounded-lg bg-buttonPrimary px-4 py-3 text-sm font-semibold text-buttonTextPrimary hover:bg-buttonPrimaryHover"
                                :disabled="form.processing || !form.accepted_terms"
                                :class="{ 'opacity-60': form.processing || !form.accepted_terms }"
                            >
                                {{ $t("Jetzt kaufen") }}
                            </button>

                            <button
                                type="button"
                                class="w-full rounded-lg border border-border px-4 py-3 text-sm font-semibold text-primary hover:bg-muted"
                                @click="addToCart"
                            >
                                {{ $t("In den Einkaufswagen") }}
                            </button>

                            <Link v-if="isAuthenticated" :href="route('auth.commerce.cart.index')" class="hidden text-center text-sm font-semibold text-air-blue">
                                Einkaufswagen ansehen ({{ cart.items_count || 0 }})
                            </Link>

                            <Link v-else :href="route('login')" class="hidden text-center text-sm font-semibold text-air-blue">
                                {{ $t("Mit Konto anmelden") }}
                            </Link>
                            </section>
                        </form>
                    </aside>
                </div>
            </section>

            <section v-if="relatedProductItems.length" class="mx-auto mt-4 max-w-7xl px-4">
                <div class="rounded border border-border bg-card shadow-sm">
                    <div class="flex flex-wrap items-center justify-between gap-3 border-b border-border px-4 py-3">
                        <div>
                            <p class="text-xs font-black uppercase tracking-wide text-secondary">{{ $t("Marketplace") }}</p>
                            <h2 class="text-lg font-black text-primary">{{ $t("Ähnliche Produkte") }}</h2>
                        </div>
                        <Link :href="route('guest.marketplace')" class="text-sm font-bold text-buttonPrimary hover:text-buttonPrimaryHover">
                            {{ $t("Alle Angebote ansehen") }}
                        </Link>
                    </div>

                    <div class="grid grid-cols-2 gap-2 p-3 sm:grid-cols-3 lg:grid-cols-5">
                        <Link
                            v-for="relatedProduct in relatedProductItems"
                            :key="relatedProduct.id"
                            :href="relatedProduct.show_url"
                            class="group overflow-hidden rounded border border-border bg-bg transition hover:border-borderHover"
                        >
                            <div class="relative aspect-square overflow-hidden bg-inputBg">
                                <img
                                    v-if="relatedProduct.image_url"
                                    :src="relatedProduct.image_url"
                                    :alt="relatedProduct.title"
                                    class="h-full w-full object-cover transition group-hover:scale-105"
                                />
                                <i v-else :class="[relatedProduct.visual_icon, 'flex h-full items-center justify-center text-5xl text-buttonPrimary']"></i>
                                <span class="absolute left-2 top-2 rounded bg-card/90 px-2 py-1 text-[11px] font-black text-buttonPrimary">
                                    {{ relatedProduct.badge }}
                                </span>
                            </div>
                            <div class="p-3">
                                <p class="text-[11px] font-bold uppercase tracking-wide text-secondary">
                                    {{ categoryLabels[relatedProduct.category] || relatedProduct.category }}
                                </p>
                                <h3 class="mt-1 line-clamp-2 min-h-[2.5rem] text-sm font-bold text-primary group-hover:text-buttonPrimary">
                                    {{ relatedProduct.title }}
                                </h3>
                                <p class="mt-3 text-lg font-black text-primary">
                                    {{ formatMoney(relatedProduct.price?.gross_cents ?? relatedProduct.price_cents, relatedProduct.price?.currency ?? relatedProduct.currency) }}
                                </p>
                                <p class="mt-1 truncate text-xs text-secondary">
                                    {{ relatedProduct.provider_name || 'Airmius Marketplace' }}
                                </p>
                            </div>
                        </Link>
                    </div>
                </div>
            </section>
        </main>

        <Footer />
    </div>
</template>
