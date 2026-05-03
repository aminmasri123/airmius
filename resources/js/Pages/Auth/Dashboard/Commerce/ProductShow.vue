<script setup>
import AppLayout from '@/Components/Auth/Layouts/AppLayout.vue'
import { Head, Link, router, useForm } from '@inertiajs/vue3'

defineOptions({ layout: AppLayout })

const props = defineProps({
    product: { type: Object, required: true },
})

const form = useForm({
    provider: 'bank_transfer',
    accepted_terms: false,
})

const formatMoney = (cents, currency = 'EUR') => new Intl.NumberFormat('de-DE', {
    style: 'currency',
    currency,
}).format(Number(cents || 0) / 100)

const checkout = () => {
    form.post(route('auth.commerce.products.checkout', props.product.id))
}
</script>

<template>
    <Head :title="product.title" />

    <div class="space-y-6">
        <section class="surface-card p-5">
            <Link :href="route('auth.commerce.index')" class="text-sm font-semibold text-air-blue">Zurück zum Marketplace</Link>
            <div class="mt-5 grid gap-6 lg:grid-cols-[minmax(0,1fr)_22rem]">
                <article>
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
                    <p class="mt-2 text-3xl font-bold text-primary">{{ formatMoney(product.price_cents, product.currency) }}</p>

                    <form class="mt-5 space-y-4" @submit.prevent="checkout">
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
