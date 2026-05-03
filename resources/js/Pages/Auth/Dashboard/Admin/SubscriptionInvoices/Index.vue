<script setup>
import AppLayout from '@/Components/Auth/Layouts/AppLayout.vue'
import { Head, Link } from '@inertiajs/vue3'

defineOptions({ layout: AppLayout })

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

const formatMoney = (cents) => new Intl.NumberFormat('de-DE', {
    style: 'currency',
    currency: 'EUR',
}).format(Number(cents || 0) / 100)

const statusLabel = (status) => ({
    open: 'Offen',
    awaiting_transfer: 'Warte auf Überweisung',
    paid: 'Bezahlt',
    overdue: 'Überfällig',
    cancelled: 'Storniert',
}[status] || status)

const methodLabel = (method) => ({
    stripe: 'Stripe',
    paypal: 'PayPal',
    bank_transfer: 'Überweisung',
}[method] || method || '-')
</script>

<template>
    <Head title="Airmius Abo-Rechnungen" />

    <div class="space-y-5">
        <section class="surface-card p-5">
            <p class="text-xs font-semibold uppercase tracking-wide text-air-blue">Billing</p>
            <h1 class="mt-1 text-2xl font-bold text-primary">Airmius Abo-Rechnungen</h1>
            <p class="mt-2 max-w-3xl text-sm text-secondary">
                Rechnungen für Stripe, PayPal und Überweisung zentral kontrollieren.
            </p>
        </section>

        <section class="grid gap-4 md:grid-cols-4">
            <div class="surface-card p-4">
                <p class="text-xs font-semibold uppercase text-secondary">Offen</p>
                <p class="mt-2 text-2xl font-bold text-primary">{{ summary.open || 0 }}</p>
            </div>
            <div class="surface-card p-4">
                <p class="text-xs font-semibold uppercase text-secondary">Bezahlt</p>
                <p class="mt-2 text-2xl font-bold text-primary">{{ summary.paid || 0 }}</p>
            </div>
            <div class="surface-card p-4">
                <p class="text-xs font-semibold uppercase text-secondary">Überfällig</p>
                <p class="mt-2 text-2xl font-bold text-primary">{{ summary.overdue || 0 }}</p>
            </div>
            <div class="surface-card p-4">
                <p class="text-xs font-semibold uppercase text-secondary">Umsatz bezahlt</p>
                <p class="mt-2 text-2xl font-bold text-primary">{{ formatMoney(summary.revenue_cents) }}</p>
            </div>
        </section>

        <section class="surface-card overflow-hidden">
            <div class="overflow-x-auto">
                <table class="min-w-full text-left text-sm">
                    <thead class="bg-bg text-xs uppercase text-secondary">
                        <tr>
                            <th class="px-5 py-3">Rechnung</th>
                            <th class="px-5 py-3">Kunde</th>
                            <th class="px-5 py-3">Plan</th>
                            <th class="px-5 py-3">Zahlung</th>
                            <th class="px-5 py-3">Betrag</th>
                            <th class="px-5 py-3">Status</th>
                            <th class="px-5 py-3 text-right">PDF</th>
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
                            <td class="px-5 py-3 text-secondary">{{ invoice.plan?.name || '-' }}</td>
                            <td class="px-5 py-3">
                                <p class="text-primary">{{ methodLabel(invoice.payment_method) }}</p>
                                <p class="text-xs text-secondary">{{ invoice.payment_reference || '-' }}</p>
                            </td>
                            <td class="px-5 py-3 font-semibold text-primary">{{ invoice.amount }}</td>
                            <td class="px-5 py-3 text-secondary">{{ statusLabel(invoice.status) }}</td>
                            <td class="px-5 py-3 text-right">
                                <Link :href="route('admin.subscription-invoices.download', invoice.id)" class="rounded-lg border border-border px-3 py-1 text-xs font-semibold text-primary hover:bg-muted">
                                    Download
                                </Link>
                            </td>
                        </tr>
                    </tbody>
                </table>

                <p v-if="!invoices.data.length" class="px-5 py-8 text-sm text-secondary">
                    Noch keine Airmius Abo-Rechnungen.
                </p>
            </div>
        </section>
    </div>
</template>
