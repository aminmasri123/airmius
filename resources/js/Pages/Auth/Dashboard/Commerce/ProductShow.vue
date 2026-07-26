<script setup>
import AppLayout from '@/Components/Auth/Layouts/AppLayout.vue'
import { Head, Link, router, useForm, usePage } from '@inertiajs/vue3'
import { computed, ref } from 'vue'
import { useI18n } from 'vue-i18n'

defineOptions({ layout: AppLayout })

const props = defineProps({
    product: { type: Object, required: true },
    pricingCountries: { type: Array, default: () => [] },
    checkoutAddress: { type: Object, default: () => ({}) },
})

const page = usePage()
const { locale } = useI18n()
const localeCode = computed(() => ({ ar: 'ar-EG', fr: 'fr-FR', en: 'en-US', de: 'de-DE' })[locale.value] || 'de-DE')
const selectedGalleryImage = ref(null)
const form = useForm({
    provider: 'bank_transfer',
    accepted_terms: false,
    coupon_code: '',
    shipping_country: props.checkoutAddress.country || props.product.price?.country || 'DE',
    shipping_state: props.checkoutAddress.state || '',
    shipping_postal_code: props.checkoutAddress.postal_code || '',
    shipping_city: props.checkoutAddress.city || '',
    shipping_street: props.checkoutAddress.street || '',
    shipping_house_number: props.checkoutAddress.house_number || '',
    customer_type: 'consumer',
    customer_company: '',
    customer_vat_id: '',
})

const formatMoney = (cents, currency = 'EUR') => new Intl.NumberFormat(localeCode.value, {
    style: 'currency',
    currency,
}).format(Number(cents || 0) / 100)

const checkout = () => {
    form.post(route('auth.commerce.products.checkout', props.product.id))
}

const addToCart = () => {
    router.post(route('auth.commerce.cart.items.store', props.product.id), { quantity: 1 }, { preserveScroll: true })
}

const price = computed(() => props.product.price || {
    gross_cents: props.product.price_cents,
    item_gross_cents: props.product.price_cents,
    shipping_gross_cents: 0,
    net_cents: props.product.price_cents,
    tax_cents: 0,
    currency: props.product.currency || 'EUR',
    tax_rate: 0,
    tax_label: 'Tax',
})
const galleryImages = computed(() => {
    const images = props.product.gallery_images?.length ? props.product.gallery_images : [props.product.image_url]

    return [...new Set(images.filter(Boolean))]
})
const activeProductImage = computed(() => selectedGalleryImage.value || galleryImages.value[0] || props.product.image_url)
const attributeOptions = (value) => String(value || '')
    .split(/[|,]/)
    .map((option) => option.trim())
    .filter(Boolean)
</script>

<template>
    <Head :title="product.title" />

    <div class="space-y-6">
        <div v-if="page.props.flash?.success" class="rounded-lg border border-success/30 bg-success/10 px-4 py-3 text-sm font-semibold text-success">
            {{ page.props.flash.success }}
        </div>

        <section class="surface-card p-5">
            <Link :href="route('auth.commerce.index')" class="text-sm font-semibold text-air-blue">{{ $t("Zurück zum Marketplace") }}</Link>
            <div class="mt-5 grid gap-6 lg:grid-cols-[minmax(0,1fr)_22rem]">
                <article>
                    <div class="mb-5 overflow-hidden rounded-xl border border-border bg-inputBg">
                        <img v-if="activeProductImage" :src="activeProductImage" :alt="product.title" class="aspect-[16/10] w-full object-cover" />
                        <div v-else class="flex aspect-[16/10] items-center justify-center">
                            <i class="las la-store text-7xl text-air-blue"></i>
                        </div>
                    </div>
                    <div v-if="galleryImages.length > 1" class="mb-5 grid grid-cols-5 gap-2">
                        <button
                            v-for="image in galleryImages"
                            :key="image"
                            type="button"
                            :class="[
                                'overflow-hidden rounded-lg border bg-inputBg',
                                activeProductImage === image ? 'border-buttonPrimary' : 'border-border'
                            ]"
                            @click="selectedGalleryImage = image"
                        >
                            <img :src="image" :alt="product.title" class="aspect-square w-full object-cover" />
                        </button>
                    </div>
                    <p class="text-xs font-semibold uppercase tracking-wide text-secondary">{{ product.category }}</p>
                    <h1 class="mt-2 text-3xl font-bold text-primary">{{ product.title }}</h1>
                    <p class="mt-4 whitespace-pre-line text-sm leading-7 text-secondary">{{ product.description || $t('Keine Beschreibung hinterlegt.') }}</p>

                    <div v-if="product.product_attributes?.length" class="mt-6 rounded-lg border border-border bg-bg p-4">
                        <h2 class="font-semibold text-primary">{{ $t("Varianten") }}</h2>
                        <div class="mt-3 grid gap-3 sm:grid-cols-2">
                            <label v-for="attribute in product.product_attributes" :key="`${attribute.name}-${attribute.value}`" class="block">
                                <span class="text-xs font-semibold uppercase text-secondary">{{ attribute.name }}</span>
                                <select class="mt-1 w-full rounded-lg border-border bg-card text-sm font-semibold text-primary">
                                    <option v-for="option in attributeOptions(attribute.value)" :key="option" :value="option">
                                        {{ option }}
                                    </option>
                                </select>
                            </label>
                        </div>
                    </div>

                    <div class="mt-6 grid gap-3 sm:grid-cols-2">
                        <div class="rounded-lg border border-border bg-bg p-4">
                            <p class="text-xs uppercase text-secondary">{{ $t("Anbieter") }}</p>
                            <p class="mt-1 font-semibold text-primary">{{ product.user?.name || product.club?.name || $t('Airmius Anbieter') }}</p>
                        </div>
                        <div class="rounded-lg border border-border bg-bg p-4">
                            <p class="text-xs uppercase text-secondary">{{ $t('Status') }}</p>
                            <p class="mt-1 font-semibold text-primary">{{ product.status }}</p>
                        </div>
                    </div>
                </article>

                <aside class="rounded-lg border border-border bg-bg p-5">
                    <p class="text-xs uppercase text-secondary">{{ $t("Preis") }}</p>
                    <p class="mt-2 text-3xl font-bold text-primary">{{ formatMoney(price.gross_cents, price.currency) }}</p>
                    <div class="mt-2 rounded-lg border border-border bg-card p-3 text-sm text-secondary">
                        <p>{{ formatMoney(price.net_cents, price.currency) }} {{ $t('netto') }}</p>
                        <p>{{ formatMoney(price.tax_cents, price.currency) }} {{ price.tax_label }} ({{ price.tax_rate }}%)</p>
                        <p class="mt-1">{{ price.shipping_label || $t('Versand') }}: {{ formatMoney(price.shipping_gross_cents, price.currency) }}</p>
                    </div>

                    <form class="mt-5 space-y-4" @submit.prevent="checkout">
                        <div>
                            <label class="text-xs font-semibold uppercase text-secondary">{{ $t("Lieferland") }}</label>
                            <select v-model="form.shipping_country" class="mt-1 w-full rounded-lg border-border bg-inputBg text-sm text-primary">
                                <option v-for="country in pricingCountries" :key="country.country" :value="country.country">
                                    {{ country.label }}
                                </option>
                            </select>
                        </div>

                        <div>
                            <label class="text-xs font-semibold uppercase text-secondary">{{ $t("Kundentyp") }}</label>
                            <select v-model="form.customer_type" class="mt-1 w-full rounded-lg border-border bg-inputBg text-sm text-primary">
                                <option value="consumer">{{ $t("Privatkunde") }}</option>
                                <option value="business">{{ $t("Firma / Verein") }}</option>
                            </select>
                        </div>
                        <div v-if="form.customer_type === 'business'" class="grid gap-3">
                            <input v-model="form.customer_company" class="rounded-lg border-border bg-inputBg text-sm text-primary" :placeholder="$t('Firma / Verein')">
                            <input v-model="form.customer_vat_id" class="rounded-lg border-border bg-inputBg text-sm uppercase text-primary" :placeholder="$t('USt-IdNr.')">
                        </div>

                        <div class="grid gap-3 sm:grid-cols-[1fr_5rem]">
                            <input v-model="form.shipping_street" class="rounded-lg border-border bg-inputBg text-sm text-primary" :placeholder="$t('Straße')">
                            <input v-model="form.shipping_house_number" class="rounded-lg border-border bg-inputBg text-sm text-primary" :placeholder="$t('Nr.')">
                        </div>
                        <div class="grid gap-3 sm:grid-cols-[7rem_1fr]">
                            <input v-model="form.shipping_postal_code" class="rounded-lg border-border bg-inputBg text-sm text-primary" :placeholder="$t('PLZ')">
                            <input v-model="form.shipping_city" class="rounded-lg border-border bg-inputBg text-sm text-primary" :placeholder="$t('Ort')">
                        </div>

                        <div>
                            <label class="text-xs font-semibold uppercase text-secondary">{{ $t("Zahlungsart") }}</label>
                            <select v-model="form.provider" class="mt-1 w-full rounded-lg border-border bg-inputBg text-sm text-primary">
                                <option value="bank_transfer">{{ $t("Überweisung") }}</option>
                                <option value="stripe">{{ $t("Stripe") }}</option>
                                <option value="paypal">{{ $t("PayPal") }}</option>
                            </select>
                        </div>

                        <div v-if="product.learning_course_id">
                            <label class="text-xs font-semibold uppercase text-secondary">{{ $t("Kurs-Gutschein") }}</label>
                            <input v-model="form.coupon_code" class="mt-1 w-full rounded-lg border-border bg-inputBg text-sm text-primary" :placeholder="$t('Code eingeben')">
                            <p v-if="form.errors.coupon_code" class="mt-1 text-sm text-error">{{ form.errors.coupon_code }}</p>
                        </div>

                        <label class="flex items-start gap-3 text-sm text-secondary">
                            <input v-model="form.accepted_terms" type="checkbox" class="mt-1 rounded border-border bg-inputBg">
                            <span>
                                {{ $t("Ich akzeptiere AGB und Widerrufshinweise. Mir ist bewusst, dass der jeweilige Anbieter für sein Angebot verantwortlich sein kann.") }}
                            </span>
                        </label>

                        <button class="w-full rounded-lg bg-buttonPrimary px-4 py-3 text-sm font-semibold text-buttonTextPrimary">
                            {{ $t("Kaufen") }}
                        </button>
                        <button type="button" class="w-full rounded-lg border border-border px-4 py-3 text-sm font-semibold text-primary hover:bg-muted" @click="addToCart">
                            {{ $t("In den Warenkorb") }}
                        </button>
                        <Link :href="route('auth.commerce.cart.index')" class="block text-center text-sm font-semibold text-air-blue">
                            {{ $t("Einkaufswagen ansehen") }}
                        </Link>
                    </form>
                </aside>
            </div>
        </section>
    </div>
</template>
