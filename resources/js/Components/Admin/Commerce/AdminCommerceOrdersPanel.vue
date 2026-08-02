<script setup>
defineProps({
    returnRequests: { type: Array, default: () => [] },
    reportedOrders: { type: Array, default: () => [] },
    orders: { type: Array, default: () => [] },
    formatMoney: { type: Function, required: true },
    formatDateTime: { type: Function, required: true },
    orderIssueLabel: { type: Function, required: true },
    orderPaymentLabel: { type: Function, required: true },
    orderPaymentHint: { type: Function, required: true },
    orderShippingLabel: { type: Function, required: true },
})

const emit = defineEmits([
    'update-return-request',
    'open-issue-reply',
    'update-order-issue',
    'mark-order-paid',
    'open-shipping',
    'open-refund',
])
</script>

<template>
    <section class="surface-card overflow-hidden">
        <div class="border-b border-border p-5">
            <p class="text-xs font-semibold uppercase tracking-wide text-air-blue">After Sales</p>
            <h2 class="mt-1 text-lg font-semibold text-primary">Rücksendungen und Erstattungen</h2>
            <p class="mt-1 text-sm text-secondary">Anfragen prüfen, Ware als erhalten markieren, Bestand wieder einbuchen und Erstattung dokumentieren.</p>
        </div>
        <div class="overflow-x-auto">
            <table class="min-w-full text-left text-sm">
                <tbody class="divide-y divide-border">
                    <tr v-for="request in returnRequests" :key="request.id">
                        <td class="px-5 py-3">
                            <p class="font-semibold text-primary">{{ request.item?.title || request.order?.orderable?.title || `Rücksendung #${request.id}` }}</p>
                            <p class="text-xs text-secondary">{{ request.order?.user?.email || request.guest_email || '-' }}</p>
                        </td>
                        <td class="px-5 py-3 text-secondary">{{ request.status }}</td>
                        <td class="px-5 py-3 text-secondary">{{ request.reason }}</td>
                        <td class="px-5 py-3 text-secondary">{{ formatMoney(request.requested_amount_cents) }}</td>
                        <td class="px-5 py-3 text-right">
                            <div class="flex flex-wrap justify-end gap-2">
                                <button class="rounded-lg border border-border px-3 py-2 text-xs font-semibold text-primary" @click="emit('update-return-request', request, 'approved')">Freigeben</button>
                                <button class="rounded-lg border border-border px-3 py-2 text-xs font-semibold text-primary" @click="emit('update-return-request', request, 'received', true)">Erhalten + Bestand</button>
                                <button class="rounded-lg border border-success/40 px-3 py-2 text-xs font-semibold text-success" @click="emit('update-return-request', request, 'refunded')">Erstattet</button>
                                <button class="rounded-lg border border-warning/40 px-3 py-2 text-xs font-semibold text-warning" @click="emit('update-return-request', request, 'rejected')">Ablehnen</button>
                            </div>
                        </td>
                    </tr>
                </tbody>
            </table>
            <p v-if="!returnRequests.length" class="px-5 py-6 text-sm text-secondary">Noch keine Rücksendungen.</p>
        </div>
    </section>

    <section class="surface-card overflow-hidden">
        <div class="border-b border-border p-5">
            <h2 class="text-lg font-semibold text-primary">Commerce-Bestellungen</h2>
            <div v-if="reportedOrders.length" class="mt-4 rounded-lg border border-warning/30 bg-warning/10 p-4">
                <p class="text-xs font-semibold uppercase tracking-wide text-warning">Gemeldete Probleme</p>
                <div class="mt-3 grid gap-3 lg:grid-cols-2">
                    <article v-for="order in reportedOrders" :key="`reported-${order.id}`" class="rounded-lg border border-warning/30 bg-card p-4">
                        <div class="flex items-start justify-between gap-3">
                            <div>
                                <p class="font-semibold text-primary">Bestellung #{{ order.id }} - {{ order.orderable?.name || order.orderable?.title || order.type }}</p>
                                <p class="mt-1 text-xs text-secondary">{{ order.user?.email || 'Gastbestellung' }} - {{ formatMoney(order.amount_cents) }}</p>
                                <p v-if="order.issue_note" class="mt-2 text-sm text-primary">{{ order.issue_note }}</p>
                                <div v-if="order.issue_response" class="mt-3 rounded-lg border border-border bg-bg p-3 text-sm">
                                    <p class="text-xs font-semibold uppercase text-secondary">Antwort</p>
                                    <p class="mt-1 text-primary">{{ order.issue_response }}</p>
                                </div>
                            </div>
                            <span class="shrink-0 rounded-full bg-warning/15 px-2 py-1 text-xs font-semibold text-warning">{{ orderIssueLabel(order.issue_status) }}</span>
                        </div>
                        <div class="mt-3 flex flex-wrap gap-2">
                            <button class="rounded-lg bg-buttonPrimary px-3 py-2 text-xs font-semibold text-buttonTextPrimary" @click="emit('open-issue-reply', order)">Antworten</button>
                            <button v-if="order.issue_status === 'reported'" class="rounded-lg border border-border px-3 py-2 text-xs font-semibold text-primary" @click="emit('update-order-issue', order, 'reviewing')">Prüfen</button>
                            <button class="rounded-lg border border-success/40 px-3 py-2 text-xs font-semibold text-success" @click="emit('update-order-issue', order, 'resolved')">Gelöst</button>
                            <button class="rounded-lg border border-warning/40 px-3 py-2 text-xs font-semibold text-warning" @click="emit('update-order-issue', order, 'refunded', 'refunded')">Erstattet</button>
                        </div>
                    </article>
                </div>
            </div>
            <p class="mt-1 text-sm text-secondary">Offene Überweisungen für Add-ons und Marketplace manuell bestätigen.</p>
        </div>
        <div class="overflow-x-auto">
            <table class="min-w-full text-left text-sm">
                <tbody class="divide-y divide-border">
                    <tr v-for="order in orders" :key="order.id">
                        <td class="px-5 py-3 font-semibold text-primary">{{ order.orderable?.name || order.orderable?.title || order.type }}</td>
                        <td class="px-5 py-3 text-secondary">
                            <p class="text-xs font-semibold uppercase text-secondary">Bestellt</p>
                            <p class="font-semibold text-primary">{{ formatDateTime(order.created_at) }}</p>
                        </td>
                        <td class="px-5 py-3 text-secondary">{{ order.user?.email || '-' }}</td>
                        <td class="px-5 py-3 text-secondary">{{ formatMoney(order.amount_cents) }}</td>
                        <td class="px-5 py-3 text-secondary">
                            <p class="font-semibold text-primary">{{ orderPaymentLabel(order) }}</p>
                            <p v-if="orderPaymentHint(order)" class="text-xs text-secondary">{{ orderPaymentHint(order) }}</p>
                            <p class="text-xs text-secondary">Versand: {{ orderShippingLabel(order.shipping_status) }}</p>
                            <p v-if="order.tracking_number" class="text-xs text-secondary">{{ order.shipping_carrier }} - {{ order.tracking_number }}</p>
                            <p v-if="order.issue_status && order.issue_status !== 'none'" class="text-xs font-semibold text-warning">{{ orderIssueLabel(order.issue_status) }}</p>
                        </td>
                        <td class="px-5 py-3 text-right">
                            <div class="flex flex-wrap justify-end gap-2">
                                <button v-if="['pending', 'awaiting_transfer'].includes(order.status)" class="rounded-lg bg-buttonPrimary px-3 py-2 text-xs font-semibold text-buttonTextPrimary" @click="emit('mark-order-paid', order)">
                                    Bezahlt
                                </button>
                                <a v-if="order.invoice_number" :href="route('admin.commerce.orders.invoice', order.id)" class="rounded-lg border border-border px-3 py-2 text-xs font-semibold text-primary">Rechnung</a>
                                <a v-if="order.credit_note_number" :href="route('admin.commerce.orders.credit-note', order.id)" class="rounded-lg border border-border px-3 py-2 text-xs font-semibold text-primary">Gutschrift</a>
                                <button class="rounded-lg border border-border px-3 py-2 text-xs font-semibold text-primary" @click="emit('open-shipping', order)">Versand</button>
                                <button class="rounded-lg border border-warning/40 px-3 py-2 text-xs font-semibold text-warning" @click="emit('open-refund', order)">Teilerstattung</button>
                                <button v-if="order.issue_status && order.issue_status !== 'none'" class="rounded-lg bg-buttonPrimary px-3 py-2 text-xs font-semibold text-buttonTextPrimary" @click="emit('open-issue-reply', order)">Antworten</button>
                                <button v-if="order.issue_status === 'reported'" class="rounded-lg border border-border px-3 py-2 text-xs font-semibold text-primary" @click="emit('update-order-issue', order, 'reviewing')">Prüfen</button>
                                <button v-if="order.issue_status && order.issue_status !== 'none'" class="rounded-lg border border-success/40 px-3 py-2 text-xs font-semibold text-success" @click="emit('update-order-issue', order, 'resolved')">Gelöst</button>
                                <button v-if="order.issue_status && order.issue_status !== 'none'" class="rounded-lg border border-warning/40 px-3 py-2 text-xs font-semibold text-warning" @click="emit('update-order-issue', order, 'refunded', 'refunded')">Erstattet</button>
                            </div>
                        </td>
                    </tr>
                </tbody>
            </table>
            <p v-if="!orders.length" class="px-5 py-6 text-sm text-secondary">Noch keine Commerce-Bestellungen.</p>
        </div>
    </section>
</template>


