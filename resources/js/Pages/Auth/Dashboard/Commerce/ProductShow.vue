<script setup>
import AppLayout from '@/Components/Auth/Layouts/AppLayout.vue'
import { Head, Link, router, useForm } from '@inertiajs/vue3'
import { computed } from 'vue'

defineOptions({ layout: AppLayout })

const props = defineProps({
    product: { type: Object, required: true },
    pricingCountries: { type: Array, default: () => [] },
    checkoutAddress: { type: Object, default: () => ({}) },
})

const form = useForm({
    provider: 'bank_transfer',
    accepted_terms: false,
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

const formatMoney = (cents, currency = 'EUR') => new Intl.NumberFormat('de-DE', {
    style: 'currency',
    currency,
}).format(Number(cents || 0) / 100)

const checkout = () => {
    form.post(route('auth.commerce.products.checkout', props.product.id))
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
</script>

<template>
    <Head :title="product.title" />

    <div class="space-y-6">
        <section class="surface-card p-5">
            <Link :href="route('auth.commerce.index')" class="text-sm font-semibold text-air-blue">Zurück zum Marketplace</Link>
            <div class="mt-5 grid gap-6 lg:grid-cols-[minmax(0,1fr)_22rem]">
                <article>
                    <div class="mb-5 overflow-hidden rounded-xl border border-border bg-inputBg">
                        <img v-if="product.image_url" :src="product.image_url" :alt="product.title" class="aspect-[16/10] w-full object-cover" />
                        <div v-else class="flex aspect-[16/10] items-center justify-center">
                            <i class="las la-store text-7xl text-air-blue"></i>
                        </div>
                    </div>
                    <p class="text-xs font-semibold uppercase tracking-wide text-secondary">{{ product.category }}</p>
                    <h1 class="mt-2 text-3xl font-bold text-primary">{{ product.title }}</h1>
                    <p class="mt-4 whitespace-pre-line text-sm leading-7 text-secondary">{{ product.description || 'Keine Beschreibung hinterlegt.' }}</p>

                    <div class="mt-6 grid gap-3 sm:grid-cols-2">
                        <div class="rounded-lg border border-border bg-bg p-4">
                            <p class="text-xs uppercase text-secondary">Anbieter</p>
                            <p class="mt-1 font-semibold text-primary">{{ product.user?.name || product.club?.name || 'Airmius Anbieter' }}</p>
                        </div>
                        <div class="rounded-lg border border-border bg-bg p-4">
                            <p class="text-xs uppercase text-secondary">Status</p>
                            <p class="mt-1 font-semibold text-primary">{{ product.status }}</p>
                        </div>
                    </div>
                </article>

                <aside class="rounded-lg border border-border bg-bg p-5">
                    <p class="text-xs uppercase text-secondary">Preis</p>
                    <p class="mt-2 text-3xl font-bold text-primary">{{ formatMoney(price.gross_cents, price.currency) }}</p>
                    <div class="mt-2 rounded-lg border border-border bg-card p-3 text-sm text-secondary">
                        <p>{{ formatMoney(price.net_cents, price.currency) }} netto</p>
                        <p>{{ formatMoney(price.tax_cents, price.currency) }} {{ price.tax_label }} ({{ price.tax_rate }}%)</p>
                        <p class="mt-1">{{ price.shipping_label || 'Versand' }}: {{ formatMoney(price.shipping_gross_cents, price.currency) }}</p>
                    </div>

                    <form class="mt-5 space-y-4" @submit.prevent="checkout">
                        <div>
                            <label class="text-xs font-semibold uppercase text-secondary">Lieferland</label>
                            <select v-model="form.shipping_country" class="mt-1 w-full rounded-lg border-border bg-inputBg text-sm text-primary">
                                <option v-for="country in pricingCountries" :key="country.country" :value="country.country">
                                    {{ country.label }}
                                </option>
                            </select>
                        </div>

                        <div>
                            <label class="text-xs font-semibold uppercase text-secondary">Kundentyp</label>
                            <select v-model="form.customer_type" class="mt-1 w-full rounded-lg border-border bg-inputBg text-sm text-primary">
                                <option value="consumer">Privatkunde</option>
                                <option value="business">Firma / Verein</option>
                            </select>
                        </div>
                        <div v-if="form.customer_type === 'business'" class="grid gap-3">
                            <input v-model="form.customer_company" class="rounded-lg border-border bg-inputBg text-sm text-primary" placeholder="Firma / Verein">
                            <input v-model="form.customer_vat_id" class="rounded-lg border-border bg-inputBg text-sm uppercase text-primary" placeholder="USt-IdNr.">
                        </div>

                        <div class="grid gap-3 sm:grid-cols-[1fr_5rem]">
                            <input v-model="form.shipping_street" class="rounded-lg border-border bg-inputBg text-sm text-primary" placeholder="Straße">
                            <input v-model="form.shipping_house_number" class="rounded-lg border-border bg-inputBg text-sm text-primary" placeholder="Nr.">
                        </div>
                        <div class="grid gap-3 sm:grid-cols-[7rem_1fr]">
                            <input v-model="form.shipping_postal_code" class="rounded-lg border-border bg-inputBg text-sm text-primary" placeholder="PLZ">
                            <input v-model="form.shipping_city" class="rounded-lg border-border bg-inputBg text-sm text-primary" placeholder="Ort">
                        </div>

                        <div>
                            <label class="text-xs font-semibold uppercase text-secondary">Zahlungsart</label>
                            <select v-model="form.provider" class="mt-1 w-full rounded-lg border-border bg-inputBg text-sm text-primary">
                                <option value="bank_transfer">Überweisung</option>
                                <option value="stripe">Stripe</option>
                                <option value="paypal">PayPal</option>
                            </select>
                        </div>

                        <label class="flex items-start gap-3 text-sm text-secondary">
                            <input v-model="form.accepted_terms" type="checkbox" class="mt-1 rounded border-border bg-inputBg">
                            <span>
                                Ich akzeptiere AGB und Widerrufshinweise. Mir ist bewusst, dass der jeweilige Anbieter für sein Angebot verantwortlich sein kann.
                            </span>
                        </label>

                        <button class="w-full rounded-lg bg-buttonPrimary px-4 py-3 text-sm font-semibold text-buttonTextPrimary">
                            Kaufen
                        </button>
                    </form>
                </aside>
            </div>
        </section>
    </div>
</template>
