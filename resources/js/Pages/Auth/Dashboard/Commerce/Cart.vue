<script setup>
import { computed, ref, watch } from 'vue'
import { Head, Link, router, useForm } from '@inertiajs/vue3'
import Subnav from '@/Components/Guest/Subnav.vue'
import Footer from '@/Components/Guest/Footer.vue'
import SeoHead from '@/Components/Guest/SeoHead.vue'
import { useI18n } from 'vue-i18n'
import {
    applyCheckoutValidationErrors,
    checkoutFallback,
    checkoutRedirectUrl,
    createCheckoutRequestId,
    hasKnownCheckoutResponse,
    postIdempotentCheckout,
} from '@/composables/useIdempotentCheckout'

const props = defineProps({
    authUser: { type: Object, default: null },
    cart: { type: Object, default: () => ({ items: [], summary: {} }) },
    pricingCountries: { type: Array, default: () => [] },
    checkoutAddress: { type: Object, default: () => ({}) },
    profileAddress: { type: Object, default: null },
    shippingAddresses: { type: Array, default: () => [] },
    marketplaceVisuals: { type: Object, default: () => ({}) },
})

const { t, locale } = useI18n()
const localeCode = computed(() => ({ ar: 'ar-EG', fr: 'fr-FR', en: 'en-US', de: 'de-DE' })[locale.value] || 'de-DE')
const initialAddress = props.profileAddress || props.shippingAddresses[0] || props.checkoutAddress || {}
const cartCheckoutForm = useForm({
    provider: 'bank_transfer',
    accepted_terms: false,
    shipping_country: initialAddress.country || 'DE',
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
const checkoutError = ref('')
const checkoutProcessing = ref(false)
const checkoutRequestId = ref(createCheckoutRequestId('commerce-cart'))
const cartItems = computed(() => props.cart?.items || [])
const cartItemCount = computed(() => cartItems.value.length)
const savedAddressOptions = computed(() => props.shippingAddresses || [])
const sideBannerUrl = computed(() => props.marketplaceVisuals.side_banner || '/images/marketplace/airmius-marketplace-side-banner.png')
const sideBannerDimensions = computed(() => props.marketplaceVisuals.dimensions?.side_banner || { width: 192, height: 1080 })
const sideBannerStyle = computed(() => ({
    backgroundImage: `linear-gradient(180deg, rgba(5, 11, 22, 0.28), rgba(5, 11, 22, 0.45)), url("${sideBannerUrl.value}")`,
    width: `${Math.max(148, Math.min(192, Number(sideBannerDimensions.value.width || 192)))}px`,
}))

const formatMoney = (cents, currency = 'EUR') => new Intl.NumberFormat(localeCode.value, {
    style: 'currency',
    currency: currency || 'EUR',
}).format(Number(cents || 0) / 100)

const applyAddress = (address) => {
    if (!address) {
        return
    }

    cartCheckoutForm.shipping_country = address.country || 'DE'
    cartCheckoutForm.shipping_state = address.state || ''
    cartCheckoutForm.shipping_postal_code = address.postal_code || ''
    cartCheckoutForm.shipping_city = address.city || ''
    cartCheckoutForm.shipping_street = address.street || ''
    cartCheckoutForm.shipping_house_number = address.house_number || ''
}

watch(addressChoice, (choice) => {
    if (choice === 'profile') {
        applyAddress(props.profileAddress)
        return
    }

    if (choice.startsWith('saved:')) {
        applyAddress(savedAddressOptions.value.find((address) => String(address.id) === choice.slice(6)))
        return
    }

    cartCheckoutForm.save_shipping_address = false
})

const updateCartItem = (item, quantity) => {
    const stock = Number(item.product?.stock_quantity || 1)
    const nextQuantity = Math.min(Math.max(1, Number(quantity || 1)), stock)

    router.put(route('auth.commerce.cart.items.update', item.id), { quantity: nextQuantity }, { preserveScroll: true })
}

const removeCartItem = (item) => {
    router.delete(route('auth.commerce.cart.items.destroy', item.id), { preserveScroll: true })
}

const checkoutCart = async () => {
    if (checkoutProcessing.value) return

    checkoutError.value = ''

    if (!cartCheckoutForm.accepted_terms) {
        checkoutError.value = t('Bitte akzeptiere AGB und Widerrufshinweise, bevor du die Bestellung abschickst.')
        cartCheckoutForm.setError('accepted_terms', checkoutError.value)
        return
    }

    checkoutProcessing.value = true
    cartCheckoutForm.clearErrors()

    try {
        const response = await postIdempotentCheckout(
            route('auth.commerce.cart.checkout'),
            cartCheckoutForm.data(),
            checkoutRequestId.value,
        )
        const redirectUrl = checkoutRedirectUrl(response)

        if (redirectUrl) {
            window.location.assign(redirectUrl)
            return
        }

        checkoutError.value = checkoutFallback(locale.value, 'missing_redirect')
        checkoutRequestId.value = createCheckoutRequestId('commerce-cart')
    } catch (error) {
        checkoutError.value = applyCheckoutValidationErrors(cartCheckoutForm, error)
            || checkoutFallback(locale.value, 'start_failed')

        if (hasKnownCheckoutResponse(error)) {
            checkoutRequestId.value = createCheckoutRequestId('commerce-cart')
        }
    } finally {
        checkoutProcessing.value = false
    }
}
</script>

<template>
    <Head :title="$t('Warenkorb')" />
    <SeoHead :title="$t('Airmius Warenkorb')" :description="$t('Deine ausgewählten Marketplace-Produkte im Airmius Warenkorb.')" />

    <div class="min-h-screen bg-bg text-primary">
        <Subnav vertical />

        <aside
            class="pointer-events-none fixed left-0 top-0 z-0 hidden h-screen w-[16vw] min-w-[13rem] max-w-[18rem] overflow-hidden bg-buttonPrimary/20 bg-cover bg-center xl:block"
            :style="sideBannerStyle"
        >
            <div class="absolute inset-0 bg-buttonPrimary/10"></div>
            <div class="absolute inset-x-4 top-72 text-center text-buttonTextPrimary drop-shadow">
                <p class="font-heading text-3xl font-900 leading-none">{{ $t("AIRMIUS") }}</p>
                <p class="mt-2 text-sm font-black uppercase tracking-wide">{{ $t("Warenkorb") }}</p>
            </div>
        </aside>

        <aside
            class="pointer-events-none fixed right-0 top-0 z-0 hidden h-screen w-[16vw] min-w-[13rem] max-w-[18rem] scale-x-[-1] overflow-hidden bg-buttonPrimary/20 bg-cover bg-center xl:block"
            :style="sideBannerStyle"
        >
            <div class="absolute inset-0 bg-buttonPrimary/10"></div>
            <div class="absolute inset-x-4 top-72 text-center text-buttonTextPrimary drop-shadow">
                <p class="scale-x-[-1] font-heading text-3xl font-900 leading-none">{{ $t("AIRMIUS") }}</p>
                <p class="mt-2 scale-x-[-1] text-sm font-black uppercase tracking-wide">{{ $t("Marketplace") }}</p>
            </div>
        </aside>

        <main class="relative z-10 pb-24 pt-0 md:pb-14 xl:mx-[16vw]">
            <section class="border-b border-border bg-bg px-4 py-3 shadow-sm">
                <div class="mx-auto flex max-w-7xl items-center justify-between gap-4 overflow-hidden rounded-lg border border-border bg-card px-5 py-3 text-primary shadow-sm">
                    <Link :href="route('guest.marketplace')" class="flex min-w-0 items-center gap-3">
                        <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-full bg-buttonPrimary text-buttonTextPrimary">
                            <i class="las la-running text-2xl"></i>
                        </div>
                        <div class="min-w-0">
                            <p class="font-heading text-lg font-900 leading-tight sm:text-2xl">{{ $t("AIRMIUS Marketplace") }}</p>
                            <p class="truncate text-xs font-semibold text-secondary sm:text-sm">
                                {{ $t("Zurück zu Sport Deals, Kursen, Camps und Services") }}
                            </p>
                        </div>
                    </Link>
                    <Link
                        :href="route('auth.commerce.cart.index')"
                        class="relative inline-flex h-11 w-11 items-center justify-center rounded-full bg-buttonPrimary text-buttonTextPrimary"
                        :aria-label="$t('Warenkorb')"
                        :title="$t('Warenkorb')"
                    >
                        <i class="las la-shopping-cart text-2xl"></i>
                        <span
                            v-if="cartItemCount"
                            class="absolute -right-1 -top-1 flex h-5 min-w-5 items-center justify-center rounded-full bg-error px-1 text-[11px] font-black leading-none text-white ring-2 ring-card"
                        >
                            {{ cartItemCount }}
                        </span>
                    </Link>
                </div>
            </section>

            <section class="mx-auto max-w-7xl px-4 py-5">
                <div class="rounded bg-card p-5 shadow-sm">
                    <p class="text-xs font-semibold uppercase tracking-wide text-air-blue">{{ $t("Marketplace") }}</p>
                    <div class="mt-1 flex flex-col gap-3 md:flex-row md:items-end md:justify-between">
                        <div>
                            <h1 class="font-heading text-3xl font-900 text-primary">{{ $t("Warenkorb") }}</h1>
                            <p class="mt-2 max-w-2xl text-sm text-secondary">
                                {{ $t("Nur die Produkte, die du in den Einkaufswagen gelegt hast, werden hier angezeigt.") }}
                            </p>
                        </div>
                        <Link :href="route('guest.marketplace')" class="inline-flex w-fit rounded bg-buttonPrimary px-4 py-3 text-sm font-black text-buttonTextPrimary">
                            {{ $t("Weiter einkaufen") }}
                        </Link>
                    </div>
                </div>
            </section>

            <section v-if="cartItems.length" class="mx-auto grid max-w-7xl gap-5 px-4 xl:grid-cols-[minmax(0,1fr)_24rem]">
                <div class="overflow-hidden rounded bg-card shadow-sm">
                    <div class="border-b border-border p-5">
                        <h2 class="text-lg font-black text-primary">{{ $t("Ausgewählte Artikel") }}</h2>
                        <p class="mt-1 text-sm text-secondary">{{ $t('{count} Artikel im Warenkorb', { count: cartItemCount }) }}</p>
                    </div>

                    <div class="divide-y divide-border">
                        <article v-for="item in cartItems" :key="item.id" class="grid gap-4 p-5 md:grid-cols-[6rem_minmax(0,1fr)_8rem_auto] md:items-center">
                            <Link :href="item.product?.show_url || route('auth.commerce.products.show', item.product?.id)" class="block overflow-hidden rounded border border-border bg-inputBg">
                                <img v-if="item.product?.image_url" :src="item.product.image_url" :alt="item.product.title" width="192" height="192" loading="lazy" decoding="async" class="aspect-square h-full w-full object-cover">
                                <div v-else class="flex aspect-square items-center justify-center">
                                    <i class="las la-store text-4xl text-buttonPrimary"></i>
                                </div>
                            </Link>

                            <div class="min-w-0">
                                <div class="flex flex-wrap items-center gap-2">
                                    <span class="rounded-full bg-muted px-2.5 py-1 text-xs font-bold uppercase text-secondary">{{ item.product?.category || $t('Produkt') }}</span>
                                    <span v-if="item.product?.sku" class="text-xs text-secondary">{{ $t('Art.-Nr.') }} {{ item.product.sku }}</span>
                                </div>
                                <Link :href="item.product?.show_url || route('auth.commerce.products.show', item.product?.id)" class="mt-2 block break-words text-base font-black text-primary hover:text-buttonPrimary">
                                    {{ item.product?.title }}
                                </Link>
                                <p class="mt-1 line-clamp-2 text-sm text-secondary">{{ item.product?.description }}</p>
                                <p class="mt-2 text-xs font-bold text-success">{{ $t('Verfügbar:') }} {{ item.product?.stock_quantity }} {{ $t('Stück') }}</p>
                            </div>

                            <div>
                                <label class="text-xs font-bold uppercase text-secondary">{{ $t("Menge") }}</label>
                                <input
                                    :value="item.quantity"
                                    type="number"
                                    min="1"
                                    :max="item.product?.stock_quantity || 1"
                                    class="mt-1 w-full rounded border-border bg-inputBg text-sm font-black text-primary"
                                    @change="updateCartItem(item, Number($event.target.value || 1))"
                                >
                            </div>

                            <div class="flex items-center justify-between gap-3 md:block md:text-right">
                                <p class="text-lg font-black text-primary">{{ formatMoney(item.line_total_cents, item.product?.currency || cart.summary?.currency || 'EUR') }}</p>
                                <button class="mt-0 rounded border border-error/40 px-3 py-2 text-xs font-bold text-error hover:bg-error/10 md:mt-3" @click="removeCartItem(item)">
                                    {{ $t("Entfernen") }}
                                </button>
                            </div>
                        </article>
                    </div>
                </div>

                <form class="h-fit rounded bg-card p-5 shadow-sm" @submit.prevent="checkoutCart">
                    <h2 class="text-lg font-black text-primary">{{ $t("Bestellung") }}</h2>
                    <div class="mt-4 space-y-3 rounded border border-border bg-bg p-4 text-sm">
                        <div class="flex justify-between gap-4 text-secondary">
                            <span>{{ $t("Warenwert") }}</span>
                            <span class="font-bold text-primary">{{ formatMoney(cart.summary?.item_gross_cents, cart.summary?.currency) }}</span>
                        </div>
                        <div class="flex justify-between gap-4 text-secondary">
                            <span>{{ $t("Versand") }}</span>
                            <span class="font-bold text-primary">{{ formatMoney(cart.summary?.shipping_cents, cart.summary?.currency) }}</span>
                        </div>
                        <div class="flex justify-between gap-4 text-secondary">
                            <span>{{ $t("Steuer") }}</span>
                            <span class="font-bold text-primary">{{ formatMoney(cart.summary?.tax_cents, cart.summary?.currency) }}</span>
                        </div>
                        <div class="border-t border-border pt-3">
                            <div class="flex justify-between gap-4 text-base font-black text-primary">
                                <span>{{ $t("Gesamt") }}</span>
                                <span>{{ formatMoney(cart.summary?.amount_cents, cart.summary?.currency) }}</span>
                            </div>
                        </div>
                    </div>

                    <div class="mt-5 grid gap-3">
                        <select v-model="cartCheckoutForm.shipping_country" class="rounded border-border bg-inputBg text-sm text-primary">
                            <option v-for="country in pricingCountries" :key="country.country" :value="country.country">{{ country.label }}</option>
                        </select>
                        <select v-model="cartCheckoutForm.provider" class="rounded border-border bg-inputBg text-sm text-primary">
                            <option value="bank_transfer">{{ $t("Überweisung") }}</option>
                            <option value="stripe">{{ $t("Stripe") }}</option>
                            <option value="paypal">{{ $t("PayPal") }}</option>
                        </select>
                        <select v-model="cartCheckoutForm.customer_type" class="rounded border-border bg-inputBg text-sm text-primary">
                            <option value="consumer">{{ $t("Privatkunde") }}</option>
                            <option value="business">{{ $t("Firma / Verein") }}</option>
                        </select>
                        <input v-if="cartCheckoutForm.customer_type === 'business'" v-model="cartCheckoutForm.customer_vat_id" class="rounded border-border bg-inputBg text-sm uppercase text-primary" :placeholder="$t('USt-IdNr.')">
                        <input v-if="cartCheckoutForm.customer_type === 'business'" v-model="cartCheckoutForm.customer_company" class="rounded border-border bg-inputBg text-sm text-primary" :placeholder="$t('Firma / Verein')">
                        <div class="rounded border border-border bg-bg p-3">
                            <label class="text-xs font-bold uppercase text-secondary">{{ $t("Adresse") }}</label>
                            <select v-model="addressChoice" class="mt-2 w-full rounded border-border bg-inputBg text-sm text-primary">
                                <option v-if="profileAddress" value="profile">
                                    {{ $t('Meine Adresse') }}{{ profileAddress.summary ? ` - ${profileAddress.summary}` : '' }}
                                </option>
                                <option v-for="address in savedAddressOptions" :key="address.id" :value="`saved:${address.id}`">
                                    {{ address.label }}{{ address.summary ? ` - ${address.summary}` : '' }}
                                </option>
                                <option value="new">{{ $t("Neue Lieferadresse") }}</option>
                            </select>
                        </div>
                        <div class="grid gap-3 sm:grid-cols-[1fr_6rem]">
                            <input v-model="cartCheckoutForm.shipping_street" class="rounded border-border bg-inputBg text-sm text-primary" :placeholder="$t('Straße')">
                            <input v-model="cartCheckoutForm.shipping_house_number" class="rounded border-border bg-inputBg text-sm text-primary" :placeholder="$t('Nr.')">
                        </div>
                        <div class="grid gap-3 sm:grid-cols-[8rem_1fr]">
                            <input v-model="cartCheckoutForm.shipping_postal_code" class="rounded border-border bg-inputBg text-sm text-primary" :placeholder="$t('PLZ')">
                            <input v-model="cartCheckoutForm.shipping_city" class="rounded border-border bg-inputBg text-sm text-primary" :placeholder="$t('Ort')">
                        </div>
                        <label class="flex items-start gap-3 rounded border border-border bg-bg p-3 text-sm text-secondary">
                            <input v-model="cartCheckoutForm.save_shipping_address" type="checkbox" class="mt-1 rounded border-border bg-inputBg">
                            <span>{{ $t("Diese Lieferadresse speichern") }}</span>
                        </label>
                        <input
                            v-if="cartCheckoutForm.save_shipping_address"
                            v-model="cartCheckoutForm.shipping_address_label"
                            class="rounded border-border bg-inputBg text-sm text-primary"
                            :placeholder="$t('Name der Lieferadresse, z. B. Zuhause')"
                        >
                    </div>

                    <label class="mt-4 flex items-start gap-3 rounded border border-border bg-bg p-3 text-sm text-secondary">
                        <input v-model="cartCheckoutForm.accepted_terms" type="checkbox" class="mt-1 rounded border-border bg-inputBg">
                        <span>
                            {{ $t("Ich akzeptiere") }}
                            <Link :href="route('terms.show')" target="_blank" rel="noopener noreferrer" class="font-semibold text-air-blue underline underline-offset-2" @click.stop>{{ $t("AGB") }}</Link>
                            {{ $t("und") }}
                            <Link :href="route('legal.withdrawal')" target="_blank" rel="noopener noreferrer" class="font-semibold text-air-blue underline underline-offset-2" @click.stop>{{ $t("Widerrufshinweise") }}</Link>.
                        </span>
                    </label>
                    <p v-if="cartCheckoutForm.errors.accepted_terms" class="mt-2 rounded border border-error/30 bg-error/10 px-3 py-2 text-sm font-semibold text-error">
                        {{ cartCheckoutForm.errors.accepted_terms }}
                    </p>
                    <p v-else-if="checkoutError" class="mt-2 rounded border border-error/30 bg-error/10 px-3 py-2 text-sm font-semibold text-error">
                        {{ checkoutError }}
                    </p>

                    <button class="mt-5 w-full rounded bg-buttonPrimary px-4 py-3 text-sm font-black text-buttonTextPrimary disabled:opacity-50" :disabled="checkoutProcessing">
                        {{ checkoutProcessing ? $t('Checkout wird gestartet...') : $t('Jetzt kaufen') }}
                    </button>
                </form>
            </section>

            <section v-else class="mx-auto max-w-7xl px-4">
                <div class="grid gap-4 rounded bg-card p-10 text-center shadow-sm">
                    <div class="mx-auto flex h-16 w-16 items-center justify-center rounded-full border border-border bg-bg">
                        <i class="las la-shopping-bag text-3xl text-buttonPrimary"></i>
                    </div>
                    <div>
                        <h2 class="text-xl font-black text-primary">{{ $t("Dein Warenkorb ist leer") }}</h2>
                        <p class="mt-2 text-sm text-secondary">{{ $t("Füge ein Marketplace-Produkt hinzu, dann erscheint es hier.") }}</p>
                    </div>
                    <Link :href="route('guest.marketplace')" class="mx-auto rounded bg-buttonPrimary px-4 py-3 text-sm font-black text-buttonTextPrimary">
                        {{ $t("Marketplace ansehen") }}
                    </Link>
                </div>
            </section>
        </main>

        <Footer />
    </div>
</template>
