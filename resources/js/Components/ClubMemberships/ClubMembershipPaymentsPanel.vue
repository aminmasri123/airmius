<script setup>
defineProps({
    bankTransactions: { type: Array, default: () => [] },
    filteredBankTransactions: { type: Array, default: () => [] },
    transactionStatusFilter: { type: String, default: 'all' },
    capabilities: { type: Object, default: () => ({}) },
    formatMoney: { type: Function, required: true },
    formatDate: { type: Function, required: true },
    setTransactionStatusFilter: { type: Function, required: true },
    openBankImportModal: { type: Function, required: true },
    confirmBankTransaction: { type: Function, required: true },
})
</script>

<template>
    <section class="surface-card p-5">
        <div class="flex flex-col gap-4 lg:flex-row lg:items-center lg:justify-between">
            <div>
                <h2 class="text-lg font-semibold text-primary">Bankabgleich</h2>
                <p class="mt-1 text-sm text-secondary">
                    CSV-Umsätze importieren, Rechnungen automatisch zuordnen und unklare Treffer manuell bestätigen.
                </p>
            </div>
            <button
                type="button"
                class="rounded-lg bg-buttonPrimary px-4 py-2 text-sm font-semibold text-buttonTextPrimary disabled:cursor-not-allowed disabled:opacity-50"
                :disabled="capabilities.bank_reconciliation === false"
                :title="capabilities.bank_reconciliation === false ? 'Bankabgleich ist ab Pro verfügbar' : ''"
                @click="openBankImportModal"
            >
                Bank-CSV importieren
            </button>
        </div>

        <div class="mt-4 flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
            <p class="text-sm text-secondary">{{ filteredBankTransactions.length }} von {{ bankTransactions.length }} Umsätzen sichtbar.</p>
            <select
                :value="transactionStatusFilter"
                class="rounded-lg border border-border bg-inputBg px-3 py-2 text-sm text-primary"
                @change="setTransactionStatusFilter($event.target.value)"
            >
                <option value="all">Alle Status</option>
                <option value="matched">Verbucht</option>
                <option value="suggested">Vorschlag</option>
                <option value="unmatched">Offen</option>
            </select>
        </div>

        <div class="mt-4 overflow-x-auto">
            <table class="min-w-full text-left text-sm">
                <thead class="text-xs uppercase text-secondary">
                    <tr>
                        <th class="py-2 pr-4">Datum</th>
                        <th class="py-2 pr-4">Zahler</th>
                        <th class="py-2 pr-4">Betrag</th>
                        <th class="py-2 pr-4">Rechnung</th>
                        <th class="py-2 pr-4">Status</th>
                        <th class="py-2 pr-4">Aktion</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-border">
                    <tr v-for="transaction in filteredBankTransactions" :key="transaction.id">
                        <td class="py-3 pr-4 text-secondary">{{ formatDate(transaction.booking_date) }}</td>
                        <td class="py-3 pr-4">
                            <div class="text-primary">{{ transaction.debtor_name || '-' }}</div>
                            <div class="text-xs text-secondary">{{ transaction.debtor_iban || transaction.purpose || '-' }}</div>
                        </td>
                        <td class="py-3 pr-4 text-primary">{{ formatMoney(transaction.amount) }}</td>
                        <td class="py-3 pr-4 text-secondary">
                            {{ transaction.invoice?.number || '-' }}
                            <span v-if="transaction.invoice?.title">- {{ transaction.invoice.title }}</span>
                        </td>
                        <td class="py-3 pr-4">
                            <span class="rounded-full px-2 py-1 text-xs font-semibold" :class="{
                                'bg-air-green/15 text-air-green': transaction.status === 'matched',
                                'bg-air-blue/15 text-air-blue': transaction.status === 'suggested',
                                'bg-muted text-secondary': transaction.status === 'unmatched',
                            }">
                                {{ transaction.status === 'matched' ? 'Verbucht' : transaction.status === 'suggested' ? 'Vorschlag' : 'Offen' }}
                            </span>
                            <div class="mt-1 text-xs text-secondary">{{ transaction.match_reason }}</div>
                        </td>
                        <td class="py-3 pr-4">
                            <button
                                v-if="transaction.status === 'suggested' && transaction.invoice"
                                type="button"
                                class="rounded border border-border px-2 py-1 text-xs text-primary"
                                @click="confirmBankTransaction(transaction)"
                            >
                                Bestätigen
                            </button>
                        </td>
                    </tr>
                </tbody>
            </table>
            <p v-if="!filteredBankTransactions.length" class="py-6 text-sm text-secondary">Keine passenden Bankumsätze.</p>
        </div>
    </section>
</template>

