<script setup>
defineProps({
    billingHistory: {
        type: Object,
        default: () => ({ invoices: [], payments: [], subscription_invoices: [] }),
    },
    canDeleteOpenSubscriptionPayment: { type: Function, required: true },
    canOpenStripePortal: { type: Function, required: true },
    cancelSubscription: { type: Function, required: true },
    currentUserSubscriptions: { type: Array, default: () => [] },
    formatDate: { type: Function, required: true },
    formatMoney: { type: Function, required: true },
    hasAirmiusBank: { type: Function, required: true },
    hasClubBank: { type: Function, required: true },
    invoiceStatusLabel: { type: Function, required: true },
    isOpenSubscriptionPayment: { type: Function, required: true },
    isPayableClubInvoice: { type: Function, required: true },
    isSubscriptionCancellable: { type: Function, required: true },
    openBankTransferModal: { type: Function, required: true },
    openPaymentActionModal: { type: Function, required: true },
    openProviderPortal: { type: Function, required: true },
    settingsText: { type: Function, required: true },
})
</script>

<template>
    <div class="space-y-5">
        <section class="surface-card p-5">
            <h2 class="text-lg font-semibold text-primary">{{ settingsText('billing.subscriptions_title', 'Meine Airmius Abos') }}</h2>
            <p class="mt-1 text-sm text-secondary">
                {{ settingsText('billing.subscriptions_description', 'Aktuelle persönliche Airmius Pläne und Laufzeiten.') }}
            </p>

            <div class="mt-4 grid gap-3 md:grid-cols-2">
                <div v-for="subscription in currentUserSubscriptions" :key="subscription.id" class="rounded-lg border border-border bg-bg p-4">
                    <div class="flex items-start justify-between gap-3">
                        <div>
                            <p class="font-semibold text-primary">{{ subscription.plan?.name || settingsText('billing.airmius_subscription', 'Airmius Abo') }}</p>
                            <p class="mt-1 text-sm text-secondary">{{ invoiceStatusLabel(subscription.status) }}</p>
                        </div>
                        <div class="flex shrink-0 flex-wrap justify-end gap-2">
                            <button
                                v-if="canOpenStripePortal(subscription)"
                                type="button"
                                class="rounded-lg border border-border px-3 py-1 text-xs font-semibold text-primary hover:bg-muted"
                                @click="openProviderPortal(subscription)"
                            >
                                {{ settingsText('billing.payment_portal', 'Zahlungsportal') }}
                            </button>
                            <p
                                v-else-if="subscription.payment_provider === 'stripe'"
                                class="text-xs text-secondary"
                            >
                                {{ settingsText('billing.payment_portal_inactive', 'Zahlungsportal ist für dieses Abo momentan nicht aktiv.') }}
                            </p>

                            <button
                                v-if="isSubscriptionCancellable(subscription)"
                                type="button"
                                class="rounded-lg border border-border px-3 py-1 text-xs font-semibold text-primary hover:bg-muted"
                                @click="cancelSubscription(subscription)"
                            >
                                {{ settingsText('billing.cancel', 'Kündigen') }}
                            </button>
                        </div>
                    </div>
                    <dl class="mt-3 space-y-2 text-sm">
                        <div class="flex justify-between gap-3">
                            <dt class="text-secondary">{{ settingsText('billing.payment_method', 'Zahlungsart') }}</dt>
                            <dd class="text-primary">{{ subscription.payment_provider || '-' }}</dd>
                        </div>
                        <div class="flex justify-between gap-3">
                            <dt class="text-secondary">{{ settingsText('billing.runs_until', 'Läuft bis') }}</dt>
                            <dd class="text-primary">{{ formatDate(subscription.current_period_ends_at || subscription.trial_ends_at) }}</dd>
                        </div>
                        <div v-if="subscription.cancels_at" class="flex justify-between gap-3">
                            <dt class="text-secondary">{{ settingsText('billing.cancelled_at', 'Gekündigt zum') }}</dt>
                            <dd class="text-primary">{{ formatDate(subscription.cancels_at) }}</dd>
                        </div>
                    </dl>
                </div>
            </div>

            <p v-if="!currentUserSubscriptions.length" class="mt-4 text-sm text-secondary">
                {{ settingsText('billing.no_subscription', 'Du hast noch kein persönliches Airmius Abo.') }}
            </p>
        </section>

        <section class="surface-card p-5">
            <h2 class="text-lg font-semibold text-primary">{{ settingsText('billing.subscription_invoices_title', 'Airmius Abo-Rechnungen') }}</h2>
            <p class="mt-1 text-sm text-secondary">
                {{ settingsText('billing.subscription_invoices_description', 'Rechnungen für Airmius Pläne und Plattform-Abos.') }}
            </p>

            <div class="mt-4 overflow-x-auto">
                <table class="min-w-full text-left text-sm">
                    <thead class="text-xs uppercase text-secondary">
                        <tr>
                            <th class="py-2 pr-4">{{ settingsText('billing.table.number', 'Nr.') }}</th>
                            <th class="py-2 pr-4">{{ settingsText('billing.table.plan', 'Plan') }}</th>
                            <th class="py-2 pr-4">{{ settingsText('billing.table.club', 'Verein') }}</th>
                            <th class="py-2 pr-4">{{ settingsText('billing.table.amount', 'Betrag') }}</th>
                            <th class="py-2 pr-4">{{ settingsText('billing.table.due', 'Fällig') }}</th>
                            <th class="py-2 pr-4">{{ settingsText('billing.table.status', 'Status') }}</th>
                            <th class="py-2 pr-4">{{ settingsText('billing.table.pay', 'Zahlen') }}</th>
                            <th class="py-2 pr-4 text-right">PDF</th>
                            <th class="py-2 pr-4 text-right">{{ settingsText('billing.table.action', 'Aktion') }}</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-border">
                        <tr v-for="invoice in billingHistory.subscription_invoices" :key="invoice.id">
                            <td class="py-3 pr-4 text-primary">{{ invoice.number }}</td>
                            <td class="py-3 pr-4 text-primary">{{ invoice.plan?.name || invoice.title || '-' }}</td>
                            <td class="py-3 pr-4 text-secondary">{{ invoice.club?.name || '-' }}</td>
                            <td class="py-3 pr-4 text-primary">{{ formatMoney(Number(invoice.amount_cents || 0) / 100) }}</td>
                            <td class="py-3 pr-4 text-secondary">{{ formatDate(invoice.due_at) }}</td>
                            <td class="py-3 pr-4 text-secondary">{{ invoiceStatusLabel(invoice.status) }}</td>
                            <td class="py-3 pr-4">
                                <button
                                    v-if="isPayableClubInvoice(invoice) && hasAirmiusBank()"
                                    type="button"
                                    class="rounded-lg border border-border px-3 py-1 text-xs font-semibold text-primary hover:bg-muted"
                                    @click="openBankTransferModal('airmius', invoice)"
                                >
                                    {{ settingsText('billing.bank_details', 'Bankdaten') }}
                                </button>
                                <span v-else-if="isPayableClubInvoice(invoice)" class="text-xs text-warning">{{ settingsText('billing.bank_details_missing', 'Bankdaten fehlen') }}</span>
                                <span v-else class="text-xs text-secondary">-</span>
                            </td>
                            <td class="py-3 pr-4 text-right">
                                <a :href="route('auth.subscription-invoices.download', invoice.id)" download class="rounded-lg border border-border px-3 py-1 text-xs font-semibold text-primary hover:bg-muted">
                                    Download
                                </a>
                            </td>
                            <td class="py-3 pr-4 text-right">
                                <div v-if="isOpenSubscriptionPayment(invoice) || canDeleteOpenSubscriptionPayment(invoice)" class="flex flex-wrap justify-end gap-2">
                                    <button
                                        v-if="isOpenSubscriptionPayment(invoice)"
                                        type="button"
                                        class="rounded-lg border border-warning px-3 py-1 text-xs font-semibold text-warning hover:bg-warning/10"
                                        @click="openPaymentActionModal('cancel', invoice)"
                                    >
                                        {{ settingsText('actions.cancel', 'Abbrechen') }}
                                    </button>
                                    <button
                                        v-if="canDeleteOpenSubscriptionPayment(invoice)"
                                        type="button"
                                        class="rounded-lg border border-error px-3 py-1 text-xs font-semibold text-error hover:bg-error/10"
                                        @click="openPaymentActionModal('delete', invoice)"
                                    >
                                        {{ settingsText('actions.delete', 'Löschen') }}
                                    </button>
                                </div>
                                <span v-else class="text-xs text-secondary">-</span>
                            </td>
                        </tr>
                    </tbody>
                </table>

                <p v-if="!billingHistory.subscription_invoices.length" class="py-6 text-sm text-secondary">
                    {{ settingsText('billing.no_subscription_invoices', 'Noch keine Airmius Abo-Rechnungen vorhanden.') }}
                </p>
            </div>
        </section>

        <section class="surface-card p-5">
            <h2 class="text-lg font-semibold text-primary">{{ settingsText('billing.my_invoices_title', 'Meine Rechnungen') }}</h2>
            <p class="mt-1 text-sm text-secondary">
                {{ settingsText('billing.my_invoices_description', 'Hier siehst du offene und bezahlte Vereinsbeiträge.') }}
            </p>

            <div class="mt-4 overflow-x-auto">
                <table class="min-w-full text-left text-sm">
                    <thead class="text-xs uppercase text-secondary">
                        <tr>
                            <th class="py-2 pr-4">{{ settingsText('billing.table.number', 'Nr.') }}</th>
                            <th class="py-2 pr-4">{{ settingsText('billing.table.club', 'Verein') }}</th>
                            <th class="py-2 pr-4">{{ settingsText('billing.table.title', 'Titel') }}</th>
                            <th class="py-2 pr-4">{{ settingsText('billing.table.amount', 'Betrag') }}</th>
                            <th class="py-2 pr-4">{{ settingsText('billing.table.due', 'Fällig') }}</th>
                            <th class="py-2 pr-4">{{ settingsText('billing.table.status', 'Status') }}</th>
                            <th class="py-2 pr-4">{{ settingsText('billing.table.pay', 'Zahlen') }}</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-border">
                        <tr v-for="invoice in billingHistory.invoices" :key="invoice.id">
                            <td class="py-3 pr-4 text-primary">{{ invoice.number }}</td>
                            <td class="py-3 pr-4 text-secondary">{{ invoice.club?.name || '-' }}</td>
                            <td class="py-3 pr-4 text-primary">{{ invoice.title || '-' }}</td>
                            <td class="py-3 pr-4 text-primary">{{ formatMoney(invoice.amount) }}</td>
                            <td class="py-3 pr-4 text-secondary">{{ formatDate(invoice.due_date) }}</td>
                            <td class="py-3 pr-4 text-secondary">{{ invoiceStatusLabel(invoice.status) }}</td>
                            <td class="py-3 pr-4">
                                <button
                                    v-if="isPayableClubInvoice(invoice) && hasClubBank(invoice)"
                                    type="button"
                                    class="rounded-lg border border-border px-3 py-1 text-xs font-semibold text-primary hover:bg-muted"
                                    @click="openBankTransferModal('club', invoice)"
                                >
                                    {{ settingsText('billing.bank_details', 'Bankdaten') }}
                                </button>
                                <span v-else-if="isPayableClubInvoice(invoice)" class="text-xs text-warning">{{ settingsText('billing.bank_details_missing', 'Bankdaten fehlen') }}</span>
                                <span v-else class="text-xs text-secondary">-</span>
                            </td>
                        </tr>
                    </tbody>
                </table>

                <p v-if="!billingHistory.invoices.length" class="py-6 text-sm text-secondary">
                    {{ settingsText('billing.no_invoices', 'Noch keine Rechnungen vorhanden.') }}
                </p>
            </div>
        </section>

        <section class="surface-card p-5">
            <h2 class="text-lg font-semibold text-primary">{{ settingsText('billing.payment_history_title', 'Zahlungshistorie') }}</h2>

            <div class="mt-4 overflow-x-auto">
                <table class="min-w-full text-left text-sm">
                    <thead class="text-xs uppercase text-secondary">
                        <tr>
                            <th class="py-2 pr-4">{{ settingsText('billing.table.date', 'Datum') }}</th>
                            <th class="py-2 pr-4">{{ settingsText('billing.table.club', 'Verein') }}</th>
                            <th class="py-2 pr-4">{{ settingsText('billing.table.invoice', 'Rechnung') }}</th>
                            <th class="py-2 pr-4">{{ settingsText('billing.table.amount', 'Betrag') }}</th>
                            <th class="py-2 pr-4">{{ settingsText('billing.table.status', 'Status') }}</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-border">
                        <tr v-for="payment in billingHistory.payments" :key="payment.id">
                            <td class="py-3 pr-4 text-secondary">{{ formatDate(payment.paid_at || payment.created_at) }}</td>
                            <td class="py-3 pr-4 text-secondary">{{ payment.club?.name || '-' }}</td>
                            <td class="py-3 pr-4 text-primary">{{ payment.invoice?.number || '-' }}</td>
                            <td class="py-3 pr-4 text-primary">{{ formatMoney(payment.amount) }}</td>
                            <td class="py-3 pr-4 text-secondary">{{ payment.status === 'paid' ? invoiceStatusLabel('paid') : invoiceStatusLabel(payment.status) }}</td>
                        </tr>
                    </tbody>
                </table>

                <p v-if="!billingHistory.payments.length" class="py-6 text-sm text-secondary">
                    {{ settingsText('billing.no_payments', 'Noch keine Zahlungen markiert.') }}
                </p>
            </div>
        </section>
    </div>
</template>

