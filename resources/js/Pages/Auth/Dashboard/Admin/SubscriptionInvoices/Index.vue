<script setup>
import AppLayout from '@/Components/Auth/Layouts/AppLayout.vue'
import { Head, Link, router } from '@inertiajs/vue3'
import { computed } from 'vue'
import { useI18n } from 'vue-i18n'

defineOptions({ layout: AppLayout })
const { t, locale } = useI18n()
const tx = (key, fallback, values = {}) => {
    const translated = t(key, values)
    return translated === key ? fallback : translated
}

defineProps({
    invoices: {
        type: Object,
        required: true,
    },
    summary: {
        type: Object,
        default: () => ({}),
    },
})

const localeCode = computed(() => ({ ar: 'ar-EG', fr: 'fr-FR', en: 'en-US', de: 'de-DE' })[locale.value] || 'de-DE')
const formatMoney = (cents) => new Intl.NumberFormat(localeCode.value, {
    style: 'currency',
    currency: 'EUR',
}).format(Number(cents || 0) / 100)

const statusLabel = (status) => tx(`admin_finance.status.${status}`, status || '-')

const statusClasses = (status) => ({
    paid: 'bg-success/10 text-success border-success/30',
    open: 'bg-blue-500/10 text-blue-600 dark:text-blue-300 border-blue-500/30 dark:border-blue-400/40',
    awaiting_transfer: 'bg-orange-500/10 text-orange-600 dark:text-orange-300 border-orange-500/30 dark:border-orange-400/40',
    overdue: 'bg-error/10 text-error border-error/30',
    cancelled: 'bg-muted text-secondary border-border',
}[status] || 'bg-muted text-secondary border-border')

const methodLabel = (method) => tx(`admin_finance.method.${method}`, method || '-')

const canMarkPaid = (invoice) => ['open', 'awaiting_transfer', 'overdue'].includes(invoice.status)

const markPaid = (invoice) => {
    router.post(route('admin.subscription-invoices.mark-paid', invoice.id), {}, {
        preserveScroll: true,
    })
}
</script>

<template>
    <Head :title="tx('admin_finance.subscription_invoice_title', 'Airmius Abo-Rechnungen')" />

    <div class="space-y-6">
        <section class="surface-card border-l-4 border-air-blue p-6">
            <div class="flex flex-col gap-4 lg:flex-row lg:items-end lg:justify-between">
                <div>
                    <p class="text-xs font-semibold uppercase tracking-[0.16em] text-air-blue">{{ tx('admin_finance.billing', 'Billing') }}</p>
                    <h1 class="mt-1 text-3xl font-black text-primary">{{ tx('admin_finance.subscription_invoice_title', 'Airmius Abo-Rechnungen') }}</h1>
                    <p class="mt-2 max-w-3xl text-sm text-secondary">
                        {{ tx('admin_finance.subscription_invoice_intro', 'Alle Abonnements-Rechnungen zentral prüfen, Status aktualisieren und PDFs direkt herunterladen.') }}
                    </p>
                </div>
                <Link :href="route('admin.subscriptions.index')" class="rounded-lg bg-buttonPrimary px-5 py-2.5 text-sm font-semibold text-buttonTextPrimary">
                    {{ tx('admin_finance.manage_subscriptions', 'Abos verwalten') }}
                </Link>
            </div>
        </section>

        <section class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
            <div class="surface-card p-4">
                <p class="text-xs font-semibold uppercase text-secondary">Offen</p>
                <p class="mt-2 text-2xl font-black text-primary">{{ summary.open || 0 }}</p>
                <p class="mt-2 text-xs text-secondary">Warten auf Zahlungseingang</p>
            </div>
            <div class="surface-card p-4">
                <p class="text-xs font-semibold uppercase text-secondary">Bezahlt</p>
                <p class="mt-2 text-2xl font-black text-primary">{{ summary.paid || 0 }}</p>
                <p class="mt-2 text-xs text-secondary">Bereits abgeschlossen</p>
            </div>
            <div class="surface-card p-4">
                <p class="text-xs font-semibold uppercase text-secondary">Überfällig</p>
                <p class="mt-2 text-2xl font-black text-primary">{{ summary.overdue || 0 }}</p>
                <p class="mt-2 text-xs text-secondary">Überfällige Rechnung</p>
            </div>
            <div class="surface-card p-4">
                <p class="text-xs font-semibold uppercase text-secondary">Umsatz bezahlt</p>
                <p class="mt-2 text-2xl font-black text-primary">{{ formatMoney(summary.revenue_cents) }}</p>
                <p class="mt-2 text-xs text-secondary">Gesamt bezahlt</p>
            </div>
        </section>

        <section class="surface-card overflow-hidden">
            <div class="overflow-x-auto">
                <table class="min-w-full text-left text-sm">
                    <thead class="bg-bg text-xs uppercase tracking-[0.12em] text-secondary">
                        <tr>
                            <th class="px-5 py-3">Rechnung</th>
                            <th class="px-5 py-3">Kunde</th>
                            <th class="px-5 py-3">Plan</th>
                            <th class="px-5 py-3">Zahlung</th>
                            <th class="px-5 py-3">Betrag</th>
                            <th class="px-5 py-3">Status</th>
                            <th class="px-5 py-3 text-right">Aktionen</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-border">
                        <tr v-for="invoice in invoices.data" :key="invoice.id" class="hover:bg-muted/40">
                            <td class="px-5 py-3">
                                <p class="font-semibold text-primary">{{ invoice.number }}</p>
                                <p class="text-xs text-secondary">Fällig {{ invoice.due_at || '-' }}</p>
                            </td>
                            <td class="px-5 py-3">
                                <p class="font-semibold text-primary">{{ invoice.club?.name || invoice.user?.name || '-' }}</p>
                                <p class="text-xs text-secondary">{{ invoice.user?.email || '-' }}</p>
                            </td>
                            <td class="px-5 py-3">
                                <p class="font-medium text-primary">{{ invoice.plan?.name || '-' }}</p>
                            </td>
                            <td class="px-5 py-3">
                                <p class="text-primary">{{ methodLabel(invoice.payment_method) }}</p>
                                <p class="text-xs text-secondary">{{ invoice.payment_reference || '-' }}</p>
                            </td>
                            <td class="px-5 py-3 font-semibold text-primary">{{ invoice.amount }}</td>
                            <td class="px-5 py-3">
                                <span class="inline-flex rounded-full border px-3 py-1 text-xs font-semibold" :class="statusClasses(invoice.status)">
                                    {{ statusLabel(invoice.status) }}
                                </span>
                                <p v-if="invoice.checkout_status" class="text-xs text-secondary">Checkout: {{ invoice.checkout_status }}</p>
                                <p v-if="invoice.provider_checkout_id" class="text-xs text-secondary">{{ invoice.provider_checkout_id }}</p>
                            </td>
                            <td class="px-5 py-3 text-right">
                                <div class="flex flex-wrap justify-end gap-2">
                                    <button
                                        v-if="canMarkPaid(invoice)"
                                        type="button"
                                        class="rounded-lg bg-buttonPrimary px-4 py-2 text-xs font-semibold text-buttonTextPrimary"
                                        @click="markPaid(invoice)"
                                    >
                                        Als bezahlt markieren
                                    </button>
                                    <Link :href="route('admin.subscriptions.index')" class="rounded-lg border border-border px-3 py-1 text-xs font-semibold text-primary hover:bg-muted">
                                        Abo
                                    </Link>
                                    <a :href="route('admin.subscription-invoices.download', invoice.id)" download class="rounded-lg border border-border px-3 py-1 text-xs font-semibold text-primary hover:bg-muted">
                                        Download
                                    </a>
                                </div>
                            </td>
                        </tr>
                    </tbody>
                </table>

                <p v-if="!invoices.data.length" class="px-5 py-8 text-sm text-secondary">
                    {{ tx('admin_finance.empty_subscription_invoices', 'Noch keine Airmius Abo-Rechnungen.') }}
                </p>
            </div>
        </section>
    </div>
</template>
