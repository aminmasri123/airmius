<script setup>
import { computed } from 'vue'
import { Head, Link, useForm } from '@inertiajs/vue3'
import Nav from '@/Components/Guest/Nav.vue'
import Subnav from '@/Components/Guest/Subnav.vue'
import Footer from '@/Components/Guest/Footer.vue'
import { useI18n } from 'vue-i18n'

const props = defineProps({
    status: { type: String, required: true },
    order: { type: Object, required: true },
})

const { t } = useI18n()
const title = computed(() => props.status === 'success' ? t('Bestellung verarbeitet') : t('Bestellung abgebrochen'))

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
                <p class="text-xs font-semibold uppercase tracking-wide text-air-blue">{{ $t("Marketplace") }}</p>
                <h1 class="mt-2 text-3xl font-bold text-primary">{{ title }}</h1>
                <p class="mt-3 text-sm leading-6 text-secondary">
                    <span v-if="status === 'success'">
                        {{ $t("Danke. Deine Bestellung wurde verarbeitet. Wenn die Zahlung bestätigt ist, bekommst du eine E-Mail.") }}
                    </span>
                    <span v-else>
                        {{ $t("Deine Bestellung wurde abgebrochen. Du kannst jederzeit erneut starten.") }}
                    </span>
                </p>

                <div class="mt-6 rounded-lg border border-border bg-bg p-4 text-left text-sm">
                    <p class="font-semibold text-primary">{{ order.title }}</p>
                    <p class="mt-1 text-secondary">{{ order.amount }}</p>
                    <p v-if="order.pricing" class="mt-1 text-secondary">
                        {{ formatMoney(order.pricing.net_cents, order.pricing.currency) }} {{ $t('netto') }} ·
                        {{ formatMoney(order.pricing.tax_cents, order.pricing.currency) }} {{ order.pricing.tax_label }}
                    </p>
                    <p class="mt-1 text-secondary">{{ $t('Status:') }} {{ order.status }}</p>
                </div>

                <form v-if="status === 'success' && order.return_url" class="mt-6 rounded-lg border border-border bg-bg p-4 text-left" @submit.prevent="submitReturn">
                    <h2 class="font-semibold text-primary">{{ $t("Rücksendung anfragen") }}</h2>
                    <textarea v-model="returnForm.reason" rows="4" class="mt-3 w-full rounded-lg border-border bg-inputBg text-sm text-primary" :placeholder="$t('Grund für die Rücksendung')"></textarea>
                    <button class="mt-3 rounded-lg border border-warning/40 px-4 py-2 text-sm font-semibold text-warning" :disabled="returnForm.processing">
                        {{ $t("Rücksendung senden") }}
                    </button>
                </form>

                <Link :href="route('guest.marketplace')" class="mt-6 inline-flex rounded-lg bg-buttonPrimary px-4 py-3 text-sm font-semibold text-buttonTextPrimary hover:bg-buttonPrimaryHover">
                    {{ $t("Zurück zum Marketplace") }}
                </Link>
                <Link v-if="order.learning_course?.url" :href="order.learning_course.url" class="ml-2 mt-6 inline-flex rounded-lg border border-success/40 px-4 py-3 text-sm font-semibold text-success hover:bg-success/10">
                    {{ $t("Zum Kurs") }}
                </Link>
            </section>
        </main>

        <Footer />
    </div>
</template>
