<script setup>
import { useForm, Link } from '@inertiajs/vue3'
import Nav from '@/Components/Guest/Nav.vue'
import Subnav from '@/Components/Guest/Subnav.vue'
import Footer from '@/Components/Guest/Footer.vue'
import SeoHead from '@/Components/Guest/SeoHead.vue'

const props = defineProps({
    canLogin: Boolean,
    canRegister: Boolean,
    product: { type: Object, required: true },
})

const form = useForm({
    guest_name: '',
    guest_email: '',
    provider: 'bank_transfer',
    accepted_terms: false,
})

const formatMoney = (cents, currency = 'EUR') => new Intl.NumberFormat('de-DE', {
    style: 'currency',
    currency,
}).format(Number(cents || 0) / 100)

const checkout = () => {
    form.post(route('guest.marketplace.products.checkout', props.product.id))
}
</script>

<template>
    <SeoHead
        :title="`${product.title} kaufen`"
        :description="product.description || 'Marketplace-Angebot auf Airmius ansehen und als Gast bestellen.'"
    />

    <div class="min-h-screen bg-bg text-primary">
        <Nav :canLogin="canLogin" />
        <Subnav />

        <main class="px-4 pb-24 pt-36 md:pb-12 md:pt-44">
            <section class="mx-auto max-w-6xl">
                <Link :href="route('guest.marketplace')" class="text-sm font-semibold text-air-blue">
                    Zurueck zum Marketplace
                </Link>

                <div class="mt-5 grid gap-6 lg:grid-cols-[minmax(0,1fr)_24rem]">
                    <article class="surface-card p-6">
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
                        <p class="text-xs uppercase text-secondary">Preis</p>
                        <p class="mt-2 text-3xl font-bold text-primary">{{ formatMoney(product.price_cents, product.currency) }}</p>

                        <form class="mt-6 space-y-4" @submit.prevent="checkout">
                            <div>
                                <label class="text-xs font-semibold uppercase text-secondary">Name</label>
                                <input v-model="form.guest_name" class="mt-1 w-full rounded-lg border-border bg-inputBg text-primary" required autocomplete="name" />
                                <p v-if="form.errors.guest_name" class="mt-1 text-sm text-red-400">{{ form.errors.guest_name }}</p>
                            </div>

                            <div>
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
                                Als Gast bestellen
                            </button>

                            <Link :href="route('login')" class="block text-center text-sm font-semibold text-air-blue">
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
