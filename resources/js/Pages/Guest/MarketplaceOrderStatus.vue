<script setup>
import { Head, Link, useForm } from '@inertiajs/vue3'
import Nav from '@/Components/Guest/Nav.vue'
import Subnav from '@/Components/Guest/Subnav.vue'
import Footer from '@/Components/Guest/Footer.vue'

const props = defineProps({
    status: { type: String, required: true },
    order: { type: Object, required: true },
})

const title = props.status === 'success' ? 'Bestellung verarbeitet' : 'Bestellung abgebrochen'

const formatMoney = (cents, currency = 'EUR') => new Intl.NumberFormat('de-DE', {
    style: 'currency',
    currency: currency || 'EUR',
}).format(Number(cents || 0) / 100)

const returnForm = useForm({
    reason: '',
})

const submitReturn = () => {
    if (!props.order.return_url) {
        return
    }

    returnForm.post(props.order.return_url, { preserveScroll: true })
}
</script>

<template>
    <Head :title="title" />

    <div class="min-h-screen bg-bg text-primary">
        <Nav :canLogin="true" :canRegister="true" />
        <Subnav />

        <main class="px-4 pb-24 pt-36 md:pb-12 md:pt-44">
            <section class="surface-card mx-auto max-w-2xl p-8 text-center">
                <p class="text-xs font-semibold uppercase tracking-wide text-air-blue">Marketplace</p>
                <h1 class="mt-2 text-3xl font-bold text-primary">{{ title }}</h1>
                <p class="mt-3 text-sm leading-6 text-secondary">
                    <span v-if="status === 'success'">
                        Danke. Deine Bestellung wurde verarbeitet. Wenn die Zahlung bestaetigt ist, bekommst du eine E-Mail.
                    </span>
                    <span v-else>
                        Deine Bestellung wurde abgebrochen. Du kannst jederzeit erneut starten.
                    </span>
                </p>

                <div class="mt-6 rounded-lg border border-border bg-bg p-4 text-left text-sm">
                    <p class="font-semibold text-primary">{{ order.title }}</p>
                    <p class="mt-1 text-secondary">{{ order.amount }}</p>
                    <p v-if="order.pricing" class="mt-1 text-secondary">
                        {{ formatMoney(order.pricing.net_cents, order.pricing.currency) }} netto ·
                        {{ formatMoney(order.pricing.tax_cents, order.pricing.currency) }} {{ order.pricing.tax_label }}
                    </p>
                    <p class="mt-1 text-secondary">Status: {{ order.status }}</p>
                </div>

                <form v-if="status === 'success' && order.return_url" class="mt-6 rounded-lg border border-border bg-bg p-4 text-left" @submit.prevent="submitReturn">
                    <h2 class="font-semibold text-primary">Ruecksendung anfragen</h2>
                    <textarea v-model="returnForm.reason" rows="4" class="mt-3 w-full rounded-lg border-border bg-inputBg text-sm text-primary" placeholder="Grund für die Ruecksendung"></textarea>
                    <button class="mt-3 rounded-lg border border-warning/40 px-4 py-2 text-sm font-semibold text-warning" :disabled="returnForm.processing">
                        Ruecksendung senden
                    </button>
                </form>

                <Link :href="route('guest.marketplace')" class="mt-6 inline-flex rounded-lg bg-buttonPrimary px-4 py-3 text-sm font-semibold text-buttonTextPrimary hover:bg-buttonPrimaryHover">
                    Zurueck zum Marketplace
                </Link>
            </section>
        </main>

        <Footer />
    </div>
</template>
