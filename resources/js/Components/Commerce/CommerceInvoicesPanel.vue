<script setup>
import { Link } from '@inertiajs/vue3'
import OrderSupportSummary from '@/Components/Commerce/OrderSupportSummary.vue'

defineProps({
    purchaseHistory: { type: Array, default: () => [] },
    activeOrders: { type: Array, default: () => [] },
    returnRequests: { type: Array, default: () => [] },
    focusedOrderId: { type: [String, Number], default: '' },
    formatMoney: { type: Function, required: true },
    formatDateTime: { type: Function, required: true },
    orderRowId: { type: Function, required: true },
    orderPaymentLabel: { type: Function, required: true },
    orderPaymentHint: { type: Function, required: true },
    orderShippingLabel: { type: Function, required: true },
    orderIssueLabel: { type: Function, required: true },
    orderCanReportIssue: { type: Function, required: true },
    orderCanCancel: { type: Function, required: true },
    orderCanReturn: { type: Function, required: true },
})

const emit = defineEmits(['open-purchase-details', 'open-issue-modal', 'cancel-order'])
</script>

<template>
    <section class="surface-card overflow-hidden">
        <div class="border-b border-border p-5">
            <p class="text-xs font-semibold uppercase tracking-wide text-air-blue">Zentrale Übersicht</p>
            <h2 class="mt-1 text-lg font-semibold text-primary">Rechnungen und EinKäufe</h2>
            <p class="mt-1 text-sm text-secondary">Hier stehen Ads, Marketplace, Kurse, Outfit-Abos und Konto-Abos zusammen.</p>
        </div>
        <div class="overflow-x-auto">
            <table class="min-w-full text-left text-sm">
                <thead class="bg-bg text-xs uppercase text-secondary">
                    <tr>
                        <th class="px-5 py-3">Bereich</th>
                        <th class="px-5 py-3">Titel</th>
                        <th class="px-5 py-3">Datum</th>
                        <th class="px-5 py-3">Status</th>
                        <th class="px-5 py-3 text-right">Betrag</th>
                        <th class="px-5 py-3 text-right">Aktion</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-border">
                    <tr v-for="purchase in purchaseHistory" :key="purchase.key">
                        <td class="px-5 py-3">
                            <span class="rounded-full bg-bg px-2.5 py-1 text-xs font-semibold text-secondary">{{ purchase.category }}</span>
                        </td>
                        <td class="px-5 py-3">
                            <p class="font-semibold text-primary">{{ purchase.title }}</p>
                            <p v-if="purchase.description" class="text-xs text-secondary">{{ purchase.description }}</p>
                        </td>
                        <td class="px-5 py-3 text-secondary">{{ formatDateTime(purchase.ordered_at) }}</td>
                        <td class="px-5 py-3 text-secondary">{{ purchase.status_label }}</td>
                        <td class="px-5 py-3 text-right font-semibold text-primary">{{ formatMoney(purchase.amount_cents, purchase.currency) }}</td>
                        <td class="px-5 py-3 text-right">
                            <Link v-if="purchase.learning_course?.url" :href="purchase.learning_course.url" class="mr-2 rounded-lg border border-success/40 px-3 py-2 text-xs font-semibold text-success">
                                Zum Kurs
                            </Link>
                            <a v-if="purchase.invoice_url" :href="purchase.invoice_url" class="rounded-lg border border-border px-3 py-2 text-xs font-semibold text-primary">
                                Rechnung
                            </a>
                            <button v-else-if="purchase.detail_url" type="button" class="rounded-lg border border-border px-3 py-2 text-xs font-semibold text-primary" @click="emit('open-purchase-details', purchase)">
                                Details
                            </button>
                            <span v-else class="text-xs text-secondary">-</span>
                        </td>
                    </tr>
                </tbody>
            </table>
            <p v-if="!purchaseHistory.length" class="px-5 py-6 text-sm text-secondary">Noch keine EinKäufe oder Rechnungen vorhanden.</p>
        </div>
    </section>

    <section class="surface-card overflow-hidden">
        <div class="border-b border-border p-5">
            <h2 class="text-lg font-semibold text-primary">Bestellungen, Probleme und Rücksendungen</h2>
            <p class="mt-1 text-sm text-secondary">Für Marketplace-Bestellungen kannst du hier Rechnungen laden, Probleme melden und Rücksendungen verfolgen.</p>
        </div>
        <div class="overflow-x-auto">
            <table class="min-w-full text-left text-sm">
                <tbody class="divide-y divide-border">
                    <tr
                        v-for="order in activeOrders"
                        :id="orderRowId(order.id)"
                        :key="order.id"
                        class="transition"
                        :class="String(focusedOrderId) === String(order.id) ? 'bg-air-blue/10' : ''"
                    >
                        <td class="px-5 py-3 font-semibold text-primary">{{ order.orderable?.name || order.orderable?.title || order.title || order.type }}</td>
                        <td class="px-5 py-3 text-secondary">
                            <p class="font-semibold text-primary">{{ orderPaymentLabel(order) }}</p>
                            <p v-if="orderPaymentHint(order)" class="text-xs text-secondary">{{ orderPaymentHint(order) }}</p>
                            <p class="text-xs text-secondary">Versand: {{ orderShippingLabel(order.shipping_status) }}</p>
                            <p v-if="orderCanCancel(order)" class="mt-1 text-xs text-secondary">Noch nicht versendet: Storno ist möglich.</p>
                            <p v-else-if="order.shipping_status === 'shipped'" class="mt-1 text-xs text-secondary">Bereits versendet: Storno ist nicht mehr möglich. Nach Zustellung kannst du eine Rücksendung anfragen.</p>
                            <OrderSupportSummary :support="order.support_summary" dense class="mt-3" />
                            <div v-if="order.issue_status && order.issue_status !== 'none'" class="mt-3 rounded-lg border border-warning/30 bg-warning/10 p-3">
                                <p class="text-xs font-semibold uppercase text-warning">{{ orderIssueLabel(order.issue_status) }}</p>
                                <p v-if="order.issue_note" class="mt-1 text-xs text-secondary">Deine Meldung: {{ order.issue_note }}</p>
                                <p v-if="order.issue_response" class="mt-2 text-sm font-semibold text-primary">Antwort: {{ order.issue_response }}</p>
                            </div>
                        </td>
                        <td class="px-5 py-3 text-secondary">{{ formatMoney(order.amount_cents, order.currency) }}</td>
                        <td class="px-5 py-3 text-right">
                            <Link v-if="order.learning_course?.url" :href="order.learning_course.url" class="mr-2 rounded-lg border border-success/40 px-3 py-2 text-xs font-semibold text-success">
                                Zum Kurs
                            </Link>
                            <a v-if="order.invoice_number" :href="route('auth.commerce.orders.invoice', order.id)" class="rounded-lg border border-border px-3 py-2 text-xs font-semibold text-primary">
                                Rechnung
                            </a>
                            <a v-if="order.credit_note_number" :href="route('auth.commerce.orders.credit-note', order.id)" class="ml-2 rounded-lg border border-border px-3 py-2 text-xs font-semibold text-primary">
                                Gutschrift
                            </a>
                            <button v-if="orderCanReportIssue(order)" class="ml-2 rounded-lg border border-border px-3 py-2 text-xs font-semibold text-primary" @click="emit('open-issue-modal', order, 'issue')">
                                Problem melden
                            </button>
                            <button v-if="orderCanCancel(order)" class="ml-2 rounded-lg border border-red-500/50 px-3 py-2 text-xs font-semibold text-red-300 hover:bg-red-500/10" @click="emit('cancel-order', order)">
                                Stornieren
                            </button>
                            <button v-if="orderCanReturn(order)" class="ml-2 rounded-lg border border-warning/40 px-3 py-2 text-xs font-semibold text-warning" @click="emit('open-issue-modal', order, 'return')">
                                Rücksendung
                            </button>
                            <span v-else-if="order.issue_status && order.issue_status !== 'none'" class="text-xs text-secondary">{{ orderIssueLabel(order.issue_status) }}</span>
                        </td>
                    </tr>
                </tbody>
            </table>
            <p v-if="!activeOrders.length" class="px-5 py-6 text-sm text-secondary">Noch keine Bestellungen.</p>
        </div>
    </section>

    <section class="surface-card overflow-hidden">
        <div class="border-b border-border p-5">
            <h2 class="text-lg font-semibold text-primary">Meine Rücksendungen</h2>
        </div>
        <div class="overflow-x-auto">
            <table class="min-w-full text-left text-sm">
                <tbody class="divide-y divide-border">
                    <tr v-for="request in returnRequests" :key="request.id">
                        <td class="px-5 py-3 font-semibold text-primary">{{ request.item?.title || request.order?.orderable?.title || 'Rücksendung' }}</td>
                        <td class="px-5 py-3 text-secondary">{{ request.status }}</td>
                        <td class="px-5 py-3 text-secondary">{{ request.reason }}</td>
                    </tr>
                </tbody>
            </table>
            <p v-if="!returnRequests.length" class="px-5 py-6 text-sm text-secondary">Noch keine Rücksendungen.</p>
        </div>
    </section>
</template>


