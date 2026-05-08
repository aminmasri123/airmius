<script setup>
import { useForm, Link, router, usePage } from '@inertiajs/vue3'
import Nav from '@/Components/Guest/Nav.vue'
import Subnav from '@/Components/Guest/Subnav.vue'
import Footer from '@/Components/Guest/Footer.vue'
import SeoHead from '@/Components/Guest/SeoHead.vue'
import { computed } from 'vue'

const props = defineProps({
    canLogin: Boolean,
    canRegister: Boolean,
    authUser: { type: Object, default: null },
    cart: { type: Object, default: () => ({ items_count: 0 }) },
    product: { type: Object, required: true },
    pricingCountries: { type: Array, default: () => [] },
})

const page = usePage()
const isAuthenticated = computed(() => Boolean(props.authUser))
const form = useForm({
    guest_name: props.authUser?.name || '',
    guest_email: props.authUser?.email || '',
    provider: 'bank_transfer',
    accepted_terms: false,
    shipping_country: props.product.price?.country || 'DE',
    shipping_state: '',
    shipping_postal_code: '',
    shipping_city: '',
    shipping_street: '',
    shipping_house_number: '',
    customer_type: 'consumer',
    customer_company: '',
    customer_vat_id: '',
})

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

const checkout = () => {
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

    router.post(route('auth.commerce.cart.items.store', props.product.id), { quantity: 1 }, { preserveScroll: true })
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
        <Nav :canLogin="canLogin" :canRegister="canRegister" />
        <Subnav />

        <main class="px-4 pb-24 pt-36 md:pb-12 md:pt-44">
            <section class="mx-auto max-w-6xl">
                <Link :href="route('guest.marketplace')" class="text-sm font-semibold text-air-blue">
                    Zurueck zum Marketplace
                </Link>

                <div class="mt-5 grid gap-6 lg:grid-cols-[minmax(0,1fr)_24rem]">
                    <article class="surface-card p-6">
                        <div class="mb-6 overflow-hidden rounded-xl border border-border bg-inputBg">
                            <img v-if="product.image_url" :src="product.image_url" :alt="product.title" class="aspect-[16/10] w-full object-cover" />
                            <div v-else class="flex aspect-[16/10] items-center justify-center">
                                <i class="las la-store text-7xl text-air-blue"></i>
                            </div>
                        </div>
                        <p class="text-xs font-semibold uppercase tracking-wide text-secondary">{{ product.category }}</p>
                        <h1 class="mt-2 font-heading text-4xl font-900 leading-tight text-primary">{{ product.title }}</h1>
                        <p class="mt-5 whitespace-pre-line text-sm leading-7 text-secondary">
                            {{ product.description || 'Keine Beschreibung hinterlegt.' }}
                        </p>

                        <div class="mt-6 grid gap-3 sm:grid-cols-2">
                            <div class="rounded-lg border border-border bg-bg p-4">
                                <p class="text-xs uppercase text-secondary">Anbieter</p>
                                <p class="mt-1 font-semibold text-primary">{{ product.provider_name || 'Airmius Anbieter' }}</p>
                            </div>
                            <div class="rounded-lg border border-border bg-bg p-4">
                                <p class="text-xs uppercase text-secondary">Kaeuferschutz</p>
                                <p class="mt-1 font-semibold text-primary">Problem melden nach Kauf moeglich</p>
                            </div>
                        </div>
                    </article>

                    <aside class="surface-card h-fit p-6">
                        <div v-if="page.props.flash?.success" class="mb-4 rounded-lg border border-success/30 bg-success/10 px-4 py-3 text-sm font-semibold text-success">
                            {{ page.props.flash.success }}
                        </div>
                        <p class="text-xs uppercase text-secondary">Preis</p>
                        <p class="mt-2 text-3xl font-bold text-primary">{{ formatMoney(price.gross_cents, price.currency) }}</p>
                        <div class="mt-2 rounded-lg border border-border bg-bg p-3 text-sm text-secondary">
                            <p>
                                {{ formatMoney(price.net_cents, price.currency) }} netto
                            </p>
                            <p>
                                {{ formatMoney(price.tax_cents, price.currency) }} {{ price.tax_label }} ({{ price.tax_rate }}%)
                            </p>
                            <p v-if="price.is_estimate" class="mt-2 text-xs">
                                Steuer/Waehrung sind eine technische Schaetzung und werden beim finalen Checkout geprueft.
                            </p>
                        </div>

                        <form class="mt-6 space-y-4" @submit.prevent="checkout">
                            <div>
                                <label class="text-xs font-semibold uppercase text-secondary">Lieferland</label>
                                <select v-model="form.shipping_country" class="mt-1 w-full rounded-lg border-border bg-inputBg text-primary" @change="updateCountry">
                                    <option v-for="country in pricingCountries" :key="country.country" :value="country.country">
                                        {{ country.label }}
                                    </option>
                                </select>
                                <p v-if="form.errors.shipping_country" class="mt-1 text-sm text-red-400">{{ form.errors.shipping_country }}</p>
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
                                    <option value="bank_transfer">Ueberweisung</option>
                                    <option value="stripe">Stripe</option>
                                    <option value="paypal">PayPal</option>
                                </select>
                                <p v-if="form.errors.provider" class="mt-1 text-sm text-red-400">{{ form.errors.provider }}</p>
                            </div>

                            <div class="rounded-lg border border-border bg-bg p-3 text-sm text-secondary">
                                <p class="flex justify-between gap-3">
                                    <span>Zwischensumme</span>
                                    <span class="font-semibold text-primary">{{ formatMoney(price.item_gross_cents ?? price.gross_cents, price.currency) }}</span>
                                </p>
                                <p class="mt-1 flex justify-between gap-3">
                                    <span>{{ price.shipping_label || 'Versand' }}</span>
                                    <span class="font-semibold text-primary">{{ formatMoney(price.shipping_gross_cents, price.currency) }}</span>
                                </p>
                                <p v-if="price.reverse_charge" class="mt-2 text-xs text-air-blue">Reverse-Charge: Steuerschuld geht auf den Leistungsempfaenger ueber.</p>
                                <p v-else-if="price.tax_rule === 'export_outside_eu'" class="mt-2 text-xs text-air-blue">Export ausserhalb der EU: keine EU-MwSt. berechnet.</p>
                            </div>

                            <label class="flex items-start gap-3 text-sm text-secondary">
                                <input v-model="form.accepted_terms" type="checkbox" class="mt-1 rounded border-border bg-inputBg">
                                <span>
                                    Ich akzeptiere AGB und Widerrufshinweise. Mir ist bewusst, dass der jeweilige Anbieter fuer sein Angebot verantwortlich sein kann.
                                </span>
                            </label>
                            <p v-if="form.errors.accepted_terms" class="text-sm text-red-400">{{ form.errors.accepted_terms }}</p>

                            <button
                                class="w-full rounded-lg bg-buttonPrimary px-4 py-3 text-sm font-semibold text-buttonTextPrimary hover:bg-buttonPrimaryHover"
                                :disabled="form.processing"
                                :class="{ 'opacity-60': form.processing }"
                            >
                                {{ isAuthenticated ? 'Jetzt kaufen' : 'Als Gast bestellen' }}
                            </button>

                            <button
                                v-if="isAuthenticated"
                                type="button"
                                class="w-full rounded-lg border border-border px-4 py-3 text-sm font-semibold text-primary hover:bg-muted"
                                @click="addToCart"
                            >
                                In den Warenkorb
                            </button>

                            <Link v-if="isAuthenticated" :href="route('auth.commerce.index')" class="block text-center text-sm font-semibold text-air-blue">
                                Einkaufswagen ansehen ({{ cart.items_count || 0 }})
                            </Link>

                            <Link v-else :href="route('login')" class="block text-center text-sm font-semibold text-air-blue">
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
