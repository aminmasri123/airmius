<script setup>
defineProps({
    invoices: { type: Array, default: () => [] },
    filteredInvoices: { type: Array, default: () => [] },
    invoiceStatusFilter: { type: String, default: 'all' },
    capabilities: { type: Object, default: () => ({}) },
    formatMoney: { type: Function, required: true },
    formatDate: { type: Function, required: true },
    updateInvoiceStatus: { type: Function, required: true },
    markPaid: { type: Function, required: true },
    sendReminder: { type: Function, required: true },
    setInvoiceStatusFilter: { type: Function, required: true },
})
</script>

<template>
    <section class="surface-card p-5">
        <h2 class="text-lg font-semibold text-primary">Rechnungen</h2>
        <div class="mt-4 flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
            <p class="text-sm text-secondary">{{ filteredInvoices.length }} von {{ invoices.length }} Rechnungen sichtbar.</p>
            <select
                :value="invoiceStatusFilter"
                class="rounded-lg border border-border bg-inputBg px-3 py-2 text-sm text-primary"
                @change="setInvoiceStatusFilter($event.target.value)"
            >
                <option value="all">Alle Status</option>
                <option value="open">Offen</option>
                <option value="paid">Bezahlt</option>
                <option value="overdue">Überfällig</option>
                <option value="cancelled">Storniert</option>
            </select>
        </div>

        <div class="mt-4 overflow-x-auto">
            <table class="min-w-full text-left text-sm">
                <thead class="text-xs uppercase text-secondary">
                    <tr>
                        <th class="py-2 pr-4">Nr.</th>
                        <th class="py-2 pr-4">User</th>
                        <th class="py-2 pr-4">Titel</th>
                        <th class="py-2 pr-4">Betrag</th>
                        <th class="py-2 pr-4">Fällig</th>
                        <th class="py-2 pr-4">Status</th>
                        <th class="py-2 pr-4">Aktion</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-border">
                    <tr v-for="invoice in filteredInvoices" :key="invoice.id">
                        <td class="py-3 pr-4 text-primary">{{ invoice.number }}</td>
                        <td class="py-3 pr-4 text-secondary">{{ invoice.user?.name || '-' }}</td>
                        <td class="py-3 pr-4 text-primary">{{ invoice.title || '-' }}</td>
                        <td class="py-3 pr-4 text-primary">{{ formatMoney(invoice.amount) }}</td>
                        <td class="py-3 pr-4 text-secondary">{{ formatDate(invoice.due_date) }}</td>
                        <td class="py-3 pr-4">
                            <select :value="invoice.status" class="rounded border border-border bg-inputBg px-2 py-1 text-xs text-primary" @change="updateInvoiceStatus(invoice, $event.target.value)">
                                <option value="open">Offen</option>
                                <option value="paid">Bezahlt</option>
                                <option value="overdue">Überfällig</option>
                                <option value="cancelled">Storniert</option>
                            </select>
                        </td>
                        <td class="py-3 pr-4">
                            <div class="flex gap-2">
                                <button v-if="invoice.status !== 'paid'" type="button" class="rounded bg-buttonPrimary px-2 py-1 text-xs text-buttonTextPrimary" @click="markPaid(invoice)">
                                    Bezahlt
                                </button>
                                <button
                                    v-if="invoice.status !== 'paid'"
                                    type="button"
                                    class="rounded border border-border px-2 py-1 text-xs text-primary disabled:cursor-not-allowed disabled:opacity-50"
                                    :disabled="capabilities.payment_reminders === false"
                                    :title="capabilities.payment_reminders === false ? 'Mahnungen sind ab Club verfügbar' : ''"
                                    @click="sendReminder(invoice)"
                                >
                                    Mahnung
                                </button>
                            </div>
                        </td>
                    </tr>
                </tbody>
            </table>
            <p v-if="!filteredInvoices.length" class="py-6 text-sm text-secondary">Keine passenden Rechnungen.</p>
        </div>
    </section>
</template>

