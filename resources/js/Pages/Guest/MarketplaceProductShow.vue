<script setup>
import { useForm, Link, router, usePage } from '@inertiajs/vue3'
import Subnav from '@/Components/Guest/Subnav.vue'
import Footer from '@/Components/Guest/Footer.vue'
import SeoHead from '@/Components/Guest/SeoHead.vue'
import { computed, ref, watch } from 'vue'

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
    marketplaceVisuals: { type: Object, default: () => ({}) },
})

const page = usePage()
const currentUser = computed(() => props.authUser || page.props.auth?.user || null)
const isAuthenticated = computed(() => Boolean(currentUser.value))
const cartItemCount = computed(() => Number(props.cart?.items_count || 0))
const selectedGalleryImage = ref(null)
const selectedAttributes = ref({})
const initialAddress = props.profileAddress || props.shippingAddresses[0] || props.checkoutAddress || {}
const form = useForm({
    guest_name: currentUser.value?.name || '',
    guest_email: currentUser.value?.email || '',
    provider: 'bank_transfer',
    accepted_terms: true,
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
const sideBannerUrl = computed(() => props.marketplaceVisuals.side_banner || '/images/marketplace/airmius-marketplace-side-banner.png')
const sideBannerDimensions = computed(() => props.marketplaceVisuals.dimensions?.side_banner || { width: 306, height: 786 })
const sideBannerStyle = computed(() => ({
    backgroundImage: `linear-gradient(180deg, rgba(5, 11, 22, 0.08), rgba(5, 11, 22, 0.18) 45%, rgba(5, 11, 22, 0.75)), url("${sideBannerUrl.value}")`,
    width: `${Math.max(208, Math.min(288, Number(sideBannerDimensions.value.width || 306)))}px`,
}))
const categoryLabels = {
    product: 'Produkt',
    course: 'Kurs',
    camp: 'Camp',
    service: 'Service',
    outfit_subscription: 'Outfit-Abo',
}
const productFacts = computed(() => [
    ['Artikelnummer', props.product.sku || '-'],
    ['Kategorie', categoryLabels[props.product.category] || props.product.category],
    ['Versand', props.product.is_shippable ? 'Versandpflichtig' : 'Digital / ohne Versand'],
    ['Rückgabe', `${props.product.return_window_days ?? 14} Tage (${props.product.return_policy_type || 'standard'})`],
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
const savedAddressOptions = computed(() => props.shippingAddresses || [])

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
        return
    }

    if (choice.startsWith('saved:')) {
        applyAddress(savedAddressOptions.value.find((address) => String(address.id) === choice.slice(6)))
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

const checkout = () => {
    form.quantity = selectedQuantity.value
    form.accepted_terms = true

    if (!isAuthenticated.value) {
        router.visit(route('login'))
        return
    }

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
        :title="`${product.title} kaufen`"
        :description="product.description || 'Marketplace-Angebot auf Airmius ansehen und als Gast bestellen.'"
    />

    <div class="min-h-screen bg-bg text-primary">
        <Subnav vertical />

        <aside
            class="pointer-events-none fixed left-0 top-0 z-0 hidden h-screen min-w-[13rem] max-w-[18rem] overflow-hidden bg-buttonPrimary/20 bg-cover bg-center xl:block"
            :style="sideBannerStyle"
        >
            <div class="absolute inset-0 bg-buttonPrimary/10"></div>
            <div class="absolute inset-x-4 top-72 text-center text-buttonTextPrimary drop-shadow">
                <p class="font-heading text-3xl font-900 leading-none">AIRMIUS</p>
                <p class="mt-2 text-sm font-black uppercase tracking-wide">Marketplace</p>
            </div>
        </aside>

        <aside
            class="pointer-events-none fixed right-0 top-0 z-0 hidden h-screen min-w-[13rem] max-w-[18rem] scale-x-[-1] overflow-hidden bg-buttonPrimary/20 bg-cover bg-center xl:block"
            :style="sideBannerStyle"
        >
            <div class="absolute inset-0 bg-buttonPrimary/10"></div>
        </aside>

        <main class="relative z-10 pb-24 pt-0 md:pb-14 xl:mx-[16vw] xl:pr-24">
            <section class="border-b border-border bg-bg px-4 py-3 shadow-sm">
                <div class="mx-auto flex max-w-7xl items-center justify-between gap-4 overflow-hidden rounded-lg border border-border bg-card px-5 py-3 text-primary shadow-sm">
                    <Link :href="route('guest.marketplace')" class="flex min-w-0 items-center gap-3">
                        <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-full bg-buttonPrimary text-buttonTextPrimary">
                            <i class="las la-arrow-left text-xl"></i>
                        </span>
                        <span class="min-w-0">
                            <span class="block font-heading text-lg font-900 leading-tight sm:text-2xl">AIRMIUS Marketplace</span>
                            <span class="block truncate text-xs font-semibold text-secondary sm:text-sm">Zurück zu allen Sport Deals</span>
                        </span>
                    </Link>
                    <Link
                        v-if="currentUser"
                        href="/card"
                        class="relative hidden h-10 w-10 items-center justify-center rounded-full bg-buttonPrimary text-buttonTextPrimary sm:inline-flex"
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
                </div>
            </section>

            <section class="mx-auto max-w-7xl px-4 py-4">
                <div class="grid items-stretch gap-4 lg:grid-cols-[minmax(0,1fr)_24rem]">
                    <article class="overflow-hidden rounded border border-border bg-card shadow-sm">
                        <div class="grid gap-0 xl:grid-cols-[minmax(0,1fr)_20rem]">
                            <div class="relative min-h-[24rem] bg-inputBg">
                                <img v-if="activeProductImage" :src="activeProductImage" :alt="product.title" class="absolute inset-0 h-full w-full object-cover" />
                                <div v-else class="absolute inset-0 flex items-center justify-center">
                                    <i class="las la-store text-7xl text-buttonPrimary"></i>
                                </div>
                                <div class="absolute inset-0 bg-gradient-to-t from-black/75 via-black/20 to-transparent"></div>
                                <div class="absolute bottom-0 left-0 max-w-3xl p-6 text-white">
                                    <p class="text-xs font-black uppercase tracking-wide text-white/80">{{ categoryLabels[product.category] || product.category }}</p>
                                    <h1 class="mt-2 font-heading text-4xl font-900 leading-tight md:text-5xl">{{ product.title }}</h1>
                                    <p class="mt-3 text-sm font-semibold text-white/90">{{ product.provider_name || 'Airmius Anbieter' }}</p>
                                </div>
                            </div>
                            <div class="border-l border-border p-5">
                                <p class="text-xs font-black uppercase tracking-wide text-secondary">Produktinformation</p>
                                <dl class="mt-4 grid gap-3">
                                    <div v-for="fact in productFacts" :key="fact[0]" class="rounded border border-border bg-bg p-3">
                                        <dt class="text-[11px] font-bold uppercase text-secondary">{{ fact[0] }}</dt>
                                        <dd class="mt-1 text-sm font-semibold text-primary">{{ fact[1] }}</dd>
                                    </div>
                                </dl>
                                <div v-if="galleryImages.length > 1" class="mt-4">
                                    <p class="text-xs font-black uppercase tracking-wide text-secondary">Bilder</p>
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
                            </div>
                        </div>
                        <div class="grid gap-5 border-t border-border p-6 lg:grid-cols-[minmax(0,1fr)_18rem]">
                            <div>
                                <h2 class="text-lg font-black text-primary">Beschreibung</h2>
                                <p class="mt-3 whitespace-pre-line text-sm leading-7 text-secondary">
                                    {{ product.description || 'Keine Beschreibung hinterlegt.' }}
                                </p>
                            </div>
                            <div>
                                <h2 class="text-lg font-black text-primary">Varianten</h2>
                                <div v-if="displayAttributes.length" class="mt-3 grid gap-3">
                                    <label v-for="attribute in displayAttributes" :key="attribute.name" class="block">
                                        <span class="text-xs font-bold uppercase text-secondary">{{ attribute.name }}</span>
                                        <select v-model="selectedAttributes[attribute.name]" class="mt-1 w-full rounded-lg border-border bg-bg text-sm font-semibold text-primary">
                                            <option value="">Bitte wählen</option>
                                            <option v-for="option in attribute.values" :key="option" :value="option">
                                                {{ option }}
                                            </option>
                                        </select>
                                    </label>
                                    <div v-if="selectedVariant" class="rounded-lg border border-border bg-bg p-3 text-xs text-secondary">
                                        <p v-if="selectedVariant.sku">Artikelnummer: {{ selectedVariant.sku }}</p>
                                        <p v-if="selectedVariant.stock_quantity !== null">Bestand: {{ selectedVariant.stock_quantity }}</p>
                                    </div>
                                </div>
                                <p v-else class="mt-3 text-sm text-secondary">Noch keine Eigenschaften hinterlegt.</p>

                                <h2 class="mt-6 text-lg font-black text-primary">Merkmale</h2>
                                <ul v-if="product.features?.length" class="mt-3 space-y-2">
                                    <li v-for="feature in product.features" :key="feature" class="flex gap-2 text-sm font-semibold text-primary">
                                        <i class="las la-check mt-0.5 text-lg text-buttonPrimary"></i>
                                        <span>{{ feature }}</span>
                                    </li>
                                </ul>
                                <p v-else class="mt-3 text-sm text-secondary">Noch keine Merkmale hinterlegt.</p>
                            </div>
                        </div>
                    </article>

                    <aside class="surface-card h-full p-6">
                        <div v-if="page.props.flash?.success" class="mb-4 rounded-lg border border-success/30 bg-success/10 px-4 py-3 text-sm font-semibold text-success">
                            {{ page.props.flash.success }}
                        </div>
                        <p class="text-xs uppercase text-secondary">Preis</p>
                        <p class="mt-2 text-3xl font-bold text-primary">{{ formatMoney(visiblePriceCents, price.currency) }}</p>
                        <div class="mt-2 rounded-lg border border-border bg-bg p-3 text-sm text-secondary">
                            <p>
                                {{ formatMoney(price.net_cents, price.currency) }} netto
                            </p>
                            <p>
                                {{ formatMoney(price.tax_cents, price.currency) }} {{ price.tax_label }} ({{ price.tax_rate }}%)
                            </p>
                            <p v-if="price.is_estimate" class="mt-2 text-xs">
                                Steuer/Währung sind eine technische Schätzung und werden beim finalen Checkout geprüft.
                            </p>
                        </div>

                        <form class="mt-6 space-y-4" @submit.prevent="checkout">
                            <div class="space-y-4">
                            <div>
                                <label class="text-xs font-semibold uppercase text-secondary">Lieferland</label>
                                <select v-model="form.shipping_country" class="mt-1 w-full rounded-lg border-border bg-inputBg text-primary" @change="updateCountry">
                                    <option v-for="country in pricingCountries" :key="country.country" :value="country.country">
                                        {{ country.label }}
                                    </option>
                                </select>
                                <p v-if="form.errors.shipping_country" class="mt-1 text-sm text-red-400">{{ form.errors.shipping_country }}</p>
                            </div>

                            <div v-if="isAuthenticated" class="rounded-lg border border-border bg-bg p-3">
                                <label class="text-xs font-bold uppercase text-secondary">Adresse</label>
                                <select v-model="addressChoice" class="mt-2 w-full rounded-lg border-border bg-inputBg text-sm text-primary">
                                    <option v-if="profileAddress" value="profile">
                                        Meine Adresse{{ profileAddress.summary ? ` - ${profileAddress.summary}` : '' }}
                                    </option>
                                    <option v-for="address in savedAddressOptions" :key="address.id" :value="`saved:${address.id}`">
                                        {{ address.label }}{{ address.summary ? ` - ${address.summary}` : '' }}
                                    </option>
                                    <option value="new">Neue Lieferadresse</option>
                                </select>
                            </div>

                            <div class="grid gap-3 sm:grid-cols-[1fr_7rem]">
                                <div>
                                    <label class="text-xs font-semibold uppercase text-secondary">Straße</label>
                                    <input v-model="form.shipping_street" class="mt-1 w-full rounded-lg border-border bg-inputBg text-primary" autocomplete="shipping street-address" />
                                </div>
                                <div>
                                    <label class="text-xs font-semibold uppercase text-secondary">Nr.</label>
                                    <input v-model="form.shipping_house_number" class="mt-1 w-full rounded-lg border-border bg-inputBg text-primary" autocomplete="shipping address-line2" />
                                </div>
                            </div>

                            <div class="grid gap-3 sm:grid-cols-[8rem_1fr]">
                                <div>
                                    <label class="text-xs font-semibold uppercase text-secondary">PLZ</label>
                                    <input v-model="form.shipping_postal_code" class="mt-1 w-full rounded-lg border-border bg-inputBg text-primary" autocomplete="shipping postal-code" />
                                </div>
                                <div>
                                    <label class="text-xs font-semibold uppercase text-secondary">Ort</label>
                                    <input v-model="form.shipping_city" class="mt-1 w-full rounded-lg border-border bg-inputBg text-primary" autocomplete="shipping address-level2" />
                                </div>
                            </div>

                            <template v-if="isAuthenticated">
                                <label class="flex items-start gap-3 rounded-lg border border-border bg-bg p-3 text-sm text-secondary">
                                    <input v-model="form.save_shipping_address" type="checkbox" class="mt-1 rounded border-border bg-inputBg">
                                    <span>Diese Lieferadresse speichern</span>
                                </label>
                                <input
                                    v-if="form.save_shipping_address"
                                    v-model="form.shipping_address_label"
                                    class="w-full rounded-lg border-border bg-inputBg text-sm text-primary"
                                    placeholder="Name der Lieferadresse, z. B. Zuhause"
                                >
                            </template>

                            <div v-if="!isAuthenticated">
                                <label class="text-xs font-semibold uppercase text-secondary">Name</label>
                                <input v-model="form.guest_name" class="mt-1 w-full rounded-lg border-border bg-inputBg text-primary" required autocomplete="name" />
                                <p v-if="form.errors.guest_name" class="mt-1 text-sm text-red-400">{{ form.errors.guest_name }}</p>
                            </div>

                            <div>
                                <label class="text-xs font-semibold uppercase text-secondary">Kundentyp</label>
                                <select v-model="form.customer_type" class="mt-1 w-full rounded-lg border-border bg-inputBg text-primary">
                                    <option value="consumer">Privatkunde</option>
                                    <option value="business">Firma / Verein</option>
                                </select>
                            </div>

                            <div v-if="form.customer_type === 'business'" class="space-y-3">
                                <div>
                                    <label class="text-xs font-semibold uppercase text-secondary">Firma / Verein</label>
                                    <input v-model="form.customer_company" class="mt-1 w-full rounded-lg border-border bg-inputBg text-primary" autocomplete="organization" />
                                </div>
                                <div>
                                    <label class="text-xs font-semibold uppercase text-secondary">USt-IdNr.</label>
                                    <input v-model="form.customer_vat_id" class="mt-1 w-full rounded-lg border-border bg-inputBg text-primary uppercase" placeholder="z. B. ATU..." />
                                </div>
                            </div>

                            <div v-if="!isAuthenticated">
                                <label class="text-xs font-semibold uppercase text-secondary">E-Mail</label>
                                <input v-model="form.guest_email" type="email" class="mt-1 w-full rounded-lg border-border bg-inputBg text-primary" required autocomplete="email" />
                                <p v-if="form.errors.guest_email" class="mt-1 text-sm text-red-400">{{ form.errors.guest_email }}</p>
                            </div>

                            <div>
                                <label class="text-xs font-semibold uppercase text-secondary">Zahlungsart</label>
                                <select v-model="form.provider" class="mt-1 w-full rounded-lg border-border bg-inputBg text-primary">
                                    <option value="bank_transfer">Überweisung</option>
                                    <option value="stripe">Stripe</option>
                                    <option value="paypal">PayPal</option>
                                </select>
                                <p v-if="form.errors.provider" class="mt-1 text-sm text-red-400">{{ form.errors.provider }}</p>
                            </div>

                            </div>

                            <div>
                                <label class="text-xs font-semibold uppercase text-secondary">Menge</label>
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
                                <p class="mt-1 text-xs text-secondary">Verfuegbar: {{ maxQuantity }}</p>
                                <p v-if="form.errors.quantity" class="mt-1 text-sm text-red-400">{{ form.errors.quantity }}</p>
                            </div>

                            <div class="rounded-lg border border-border bg-bg p-3 text-sm text-secondary">
                                <p class="flex justify-between gap-3">
                                    <span>Zwischensumme</span>
                                    <span class="font-semibold text-primary">{{ formatMoney(selectedItemGrossCents, price.currency) }}</span>
                                </p>
                                <p class="mt-1 flex justify-between gap-3">
                                    <span>{{ price.shipping_label || 'Versand' }}</span>
                                    <span class="font-semibold text-primary">{{ formatMoney(price.shipping_gross_cents, price.currency) }}</span>
                                </p>
                                <p class="mt-3 flex justify-between gap-3 border-t border-border pt-3 text-base font-black text-primary">
                                    <span>Gesamt</span>
                                    <span>{{ formatMoney(selectedTotalGrossCents, price.currency) }}</span>
                                </p>
                                <p v-if="price.reverse_charge" class="mt-2 text-xs text-air-blue">Reverse-Charge: Steuerschuld geht auf den Leistungsempfänger über.</p>
                                <p v-else-if="price.tax_rule === 'export_outside_eu'" class="mt-2 text-xs text-air-blue">Export außerhalb der EU: keine EU-MwSt. berechnet.</p>
                            </div>

                            <label class="hidden items-start gap-3 text-sm text-secondary">
                                <input v-model="form.accepted_terms" type="checkbox" class="mt-1 rounded border-border bg-inputBg">
                                <span>
                                    Ich akzeptiere AGB und Widerrufshinweise. Mir ist bewusst, dass der jeweilige Anbieter für sein Angebot verantwortlich sein kann.
                                </span>
                            </label>
                            <p v-if="form.errors.accepted_terms" class="text-sm text-red-400">{{ form.errors.accepted_terms }}</p>

                            <button
                                class="w-full rounded-lg bg-buttonPrimary px-4 py-3 text-sm font-semibold text-buttonTextPrimary hover:bg-buttonPrimaryHover"
                                :disabled="form.processing"
                                :class="{ 'opacity-60': form.processing }"
                            >
                                Jetzt kaufen
                            </button>

                            <button
                                type="button"
                                class="w-full rounded-lg border border-border px-4 py-3 text-sm font-semibold text-primary hover:bg-muted"
                                @click="addToCart"
                            >
                                In den Einkaufswagen
                            </button>

                            <Link v-if="isAuthenticated" :href="route('auth.commerce.cart.index')" class="hidden text-center text-sm font-semibold text-air-blue">
                                Einkaufswagen ansehen ({{ cart.items_count || 0 }})
                            </Link>

                            <Link v-else :href="route('login')" class="hidden text-center text-sm font-semibold text-air-blue">
                                Mit Konto anmelden
                            </Link>
                        </form>
                    </aside>
                </div>
            </section>
        </main>

        <Footer />
    </div>
</template>
