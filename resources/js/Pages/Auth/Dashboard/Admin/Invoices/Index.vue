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

const statusLabel = (status) => ({
    paid: 'Bezahlt',
    open: 'Offen',
    pending: 'Offen',
    overdue: 'Ueberfaellig',
    cancelled: 'Storniert',
}[status] || status || '-')
</script>

<template>
    <Head title="Rechnungen" />

    <div class="space-y-5">
        <section class="surface-card p-5">
            <div class="flex flex-col gap-4 lg:flex-row lg:items-end lg:justify-between">
                <div>
                    <p class="text-xs font-semibold uppercase tracking-wide text-air-blue">Finanzen</p>
                    <h1 class="mt-1 text-2xl font-bold text-primary">Rechnungen</h1>
                    <p class="mt-2 max-w-3xl text-sm text-secondary">
                        Vereinsrechnungen, Mitgliedsbeitraege und Zahlungsstatus zentral ueberblicken.
                    </p>
                </div>
                <Link :href="route('payments.index')" class="rounded-lg border border-border px-4 py-2 text-sm font-semibold text-primary hover:bg-muted">
                    Zahlungen ansehen
                </Link>
            </div>
        </section>

        <section class="grid gap-4 md:grid-cols-4">
            <div class="surface-card p-4">
                <p class="text-xs font-semibold uppercase text-secondary">Rechnungen</p>
                <p class="mt-2 text-2xl font-bold text-primary">{{ summary.count || 0 }}</p>
            </div>
            <div class="surface-card p-4">
                <p class="text-xs font-semibold uppercase text-secondary">Offen</p>
                <p class="mt-2 text-2xl font-bold text-primary">{{ summary.open || 0 }}</p>
            </div>
            <div class="surface-card p-4">
                <p class="text-xs font-semibold uppercase text-secondary">Bezahlt</p>
                <p class="mt-2 text-2xl font-bold text-primary">{{ summary.paid || 0 }}</p>
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
                            <th class="px-5 py-3">Rechnung</th>
                            <th class="px-5 py-3">Kunde</th>
                            <th class="px-5 py-3">Quelle</th>
                            <th class="px-5 py-3">Betrag</th>
                            <th class="px-5 py-3">Bezahlt</th>
                            <th class="px-5 py-3">Status</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-border">
                        <tr v-for="invoice in invoices.data" :key="invoice.id" class="hover:bg-muted/40">
                            <td class="px-5 py-3">
                                <p class="font-semibold text-primary">{{ invoice.number || ('#' + invoice.id) }}</p>
                                <p class="text-xs text-secondary">{{ invoice.title || '-' }}</p>
                                <p class="text-xs text-secondary">Faellig {{ invoice.due_date || '-' }}</p>
                            </td>
                            <td class="px-5 py-3">
                                <p class="font-semibold text-primary">{{ invoice.club?.name || invoice.user?.name || '-' }}</p>
                                <p class="text-xs text-secondary">{{ invoice.user?.email || '-' }}</p>
                            </td>
                            <td class="px-5 py-3 text-secondary">{{ invoice.source || '-' }}</td>
                            <td class="px-5 py-3 font-semibold text-primary">{{ invoice.amount }}</td>
                            <td class="px-5 py-3">
                                <p class="text-primary">{{ invoice.paid_amount }}</p>
                                <p class="text-xs text-secondary">{{ invoice.paid_at || '-' }}</p>
                            </td>
                            <td class="px-5 py-3 text-secondary">{{ statusLabel(invoice.status) }}</td>
                        </tr>
                    </tbody>
                </table>

                <p v-if="!invoices.data.length" class="px-5 py-8 text-sm text-secondary">
                    Noch keine Rechnungen vorhanden.
                </p>
            </div>
        </section>
    </div>
</template>
