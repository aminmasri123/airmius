<script setup>
import AppLayout from '@/Components/Auth/Layouts/AppLayout.vue'
import { Head, Link } from '@inertiajs/vue3'

defineOptions({ layout: AppLayout })

defineProps({
    payments: {
        type: Object,
        required: true,
    },
    summary: {
        type: Object,
        default: () => ({}),
    },
})

const statusLabel = (status) => ({
    paid: 'Bezahlt',
    pending: 'Offen',
    open: 'Offen',
    failed: 'Fehlgeschlagen',
    cancelled: 'Storniert',
}[status] || status || '-')

const methodLabel = (method) => ({
    paypal: 'PayPal',
    stripe: 'Stripe',
    bank_transfer: 'Ueberweisung',
    cash: 'Bar',
    card: 'Karte',
}[method] || method || '-')
</script>

<template>
    <Head title="Zahlungen" />

    <div class="space-y-5">
        <section class="surface-card p-5">
            <div class="flex flex-col gap-4 lg:flex-row lg:items-end lg:justify-between">
                <div>
                    <p class="text-xs font-semibold uppercase tracking-wide text-air-blue">Finanzen</p>
                    <h1 class="mt-1 text-2xl font-bold text-primary">Zahlungen</h1>
                    <p class="mt-2 max-w-3xl text-sm text-secondary">
                        Alle erfassten Vereins- und Plattformzahlungen an einem Ort.
                    </p>
                </div>
                <Link :href="route('admin.subscription-invoices.index')" class="rounded-lg border border-border px-4 py-2 text-sm font-semibold text-primary hover:bg-muted">
                    Abo-Rechnungen
                </Link>
            </div>
        </section>

        <section class="grid gap-4 md:grid-cols-4">
            <div class="surface-card p-4">
                <p class="text-xs font-semibold uppercase text-secondary">Zahlungen</p>
                <p class="mt-2 text-2xl font-bold text-primary">{{ summary.count || 0 }}</p>
            </div>
            <div class="surface-card p-4">
                <p class="text-xs font-semibold uppercase text-secondary">Bezahlt</p>
                <p class="mt-2 text-2xl font-bold text-primary">{{ summary.paid || 0 }}</p>
            </div>
            <div class="surface-card p-4">
                <p class="text-xs font-semibold uppercase text-secondary">Offen</p>
                <p class="mt-2 text-2xl font-bold text-primary">{{ summary.pending || 0 }}</p>
            </div>
            <div class="surface-card p-4">
                <p class="text-xs font-semibold uppercase text-secondary">Umsatz bezahlt</p>
                <p class="mt-2 text-2xl font-bold text-primary">{{ summary.revenue || '0,00 EUR' }}</p>
            </div>
        </section>

        <section class="surface-card overflow-hidden">
            <div class="overflow-x-auto">
                <table class="min-w-full text-left text-sm">
                    <thead class="bg-bg text-xs uppercase text-secondary">
                        <tr>
                            <th class="px-5 py-3">Zahlung</th>
                            <th class="px-5 py-3">Kunde</th>
                            <th class="px-5 py-3">Rechnung</th>
                            <th class="px-5 py-3">Methode</th>
                            <th class="px-5 py-3">Betrag</th>
                            <th class="px-5 py-3">Status</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-border">
                        <tr v-for="payment in payments.data" :key="payment.id" class="hover:bg-muted/40">
                            <td class="px-5 py-3">
                                <p class="font-semibold text-primary">#{{ payment.id }}</p>
                                <p class="text-xs text-secondary">{{ payment.paid_at || '-' }}</p>
                            </td>
                            <td class="px-5 py-3">
                                <p class="font-semibold text-primary">{{ payment.club?.name || payment.user?.name || '-' }}</p>
                                <p class="text-xs text-secondary">{{ payment.user?.email || '-' }}</p>
                            </td>
                            <td class="px-5 py-3">
                                <p class="text-primary">{{ payment.invoice?.number || '-' }}</p>
                                <p class="text-xs text-secondary">{{ payment.invoice?.title || '' }}</p>
                            </td>
                            <td class="px-5 py-3">
                                <p class="text-primary">{{ methodLabel(payment.method) }}</p>
                                <p class="text-xs text-secondary">{{ payment.reference || '-' }}</p>
                            </td>
                            <td class="px-5 py-3 font-semibold text-primary">{{ payment.amount }}</td>
                            <td class="px-5 py-3 text-secondary">{{ statusLabel(payment.status) }}</td>
                        </tr>
                    </tbody>
                </table>

                <p v-if="!payments.data.length" class="px-5 py-8 text-sm text-secondary">
                    Noch keine Zahlungen vorhanden.
                </p>
            </div>
        </section>
    </div>
</template>
