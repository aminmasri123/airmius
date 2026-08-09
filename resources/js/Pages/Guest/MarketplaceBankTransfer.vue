<script setup>
import { Link } from '@inertiajs/vue3'
import Nav from '@/Components/Guest/Nav.vue'
import Subnav from '@/Components/Guest/Subnav.vue'
import Footer from '@/Components/Guest/Footer.vue'
import SeoHead from '@/Components/Guest/SeoHead.vue'
import { computed } from 'vue'
import { useI18n } from 'vue-i18n'

defineProps({
    order: { type: Object, required: true },
    bank: { type: Object, required: true },
})

const { locale } = useI18n()
const localeCode = computed(() => ({ ar: 'ar-EG', fr: 'fr-FR', en: 'en-US', de: 'de-DE' })[locale.value] || 'de-DE')
const formatMoney = (cents, currency = 'EUR') => new Intl.NumberFormat(localeCode.value, {
    style: 'currency',
    currency: currency || 'EUR',
}).format(Number(cents || 0) / 100)
</script>

<template>
    <SeoHead
        :title="$t('Überweisung')"
        :description="$t('guest.order_pages.payment_description')"
        :canonical="false"
        noindex
    />

    <div class="min-h-screen bg-bg text-primary">
        <Nav :canLogin="true" :canRegister="true" />
        <Subnav />

        <main id="main-content" class="px-4 pb-24 pt-36 md:pb-12 md:pt-44" tabindex="-1">
            <div class="mx-auto max-w-3xl space-y-6">
                <section class="surface-card p-6">
                    <p class="text-xs font-semibold uppercase tracking-wide text-air-blue">{{ $t("Überweisung") }}</p>
                    <h1 class="mt-1 text-2xl font-bold text-primary">{{ order.title }}</h1>
                    <p class="mt-2 text-sm text-secondary">{{ $t("Bitte überweise den Betrag mit exakt diesem Verwendungszweck.") }}</p>
                </section>

                <section class="surface-card p-6">
                    <dl class="grid gap-4 text-sm">
                        <div class="flex justify-between gap-4 border-b border-border pb-3">
                            <dt class="text-secondary">{{ $t("Betrag") }}</dt>
                            <dd class="font-semibold text-primary">{{ order.amount }}</dd>
                        </div>
                        <div v-if="order.pricing" class="grid gap-2 rounded-lg border border-border bg-bg p-3">
                            <div class="flex justify-between gap-4">
                                <dt class="text-secondary">{{ $t("Netto") }}</dt>
                                <dd class="font-semibold text-primary">{{ formatMoney(order.pricing.net_cents, order.pricing.currency) }}</dd>
                            </div>
                            <div class="flex justify-between gap-4">
                                <dt class="text-secondary">{{ order.pricing.tax_label }} ({{ order.pricing.tax_rate }}%)</dt>
                                <dd class="font-semibold text-primary">{{ formatMoney(order.pricing.tax_cents, order.pricing.currency) }}</dd>
                            </div>
                        </div>
                        <div class="flex justify-between gap-4 border-b border-border pb-3">
                            <dt class="text-secondary">{{ $t("Verwendungszweck") }}</dt>
                            <dd class="font-semibold text-primary">{{ order.payment_reference }}</dd>
                        </div>
                        <div class="flex justify-between gap-4 border-b border-border pb-3">
                            <dt class="text-secondary">{{ $t("Fällig bis") }}</dt>
                            <dd class="font-semibold text-primary">{{ order.due_at }}</dd>
                        </div>
                        <div class="flex justify-between gap-4 border-b border-border pb-3">
                            <dt class="text-secondary">{{ $t("Kontoinhaber") }}</dt>
                            <dd class="font-semibold text-primary">{{ bank.bank_account_holder }}</dd>
                        </div>
                        <div class="flex justify-between gap-4 border-b border-border pb-3">
                            <dt class="text-secondary">{{ $t("Bank") }}</dt>
                            <dd class="font-semibold text-primary">{{ bank.bank_name || '-' }}</dd>
                        </div>
                        <div class="flex justify-between gap-4 border-b border-border pb-3">
                            <dt class="text-secondary">{{ $t("IBAN") }}</dt>
                            <dd class="font-semibold text-primary">{{ bank.iban }}</dd>
                        </div>
                        <div class="flex justify-between gap-4">
                            <dt class="text-secondary">{{ $t("BIC") }}</dt>
                            <dd class="font-semibold text-primary">{{ bank.bic || '-' }}</dd>
                        </div>
                    </dl>
                </section>

                <Link :href="route('guest.marketplace')" class="inline-flex rounded-lg border border-border px-4 py-2 text-sm font-semibold text-primary hover:bg-muted">
                    {{ $t("Zurück zum Marketplace") }}
                </Link>
            </div>
        </main>

        <Footer />
    </div>
</template>
