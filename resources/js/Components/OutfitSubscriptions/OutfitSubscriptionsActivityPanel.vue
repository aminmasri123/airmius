<script setup>
defineProps({
    subscriptions: { type: Array, default: () => [] },
    statusLabel: { type: Function, required: true },
    paymentProviderLabel: { type: Function, required: true },
    formatDate: { type: Function, required: true },
    formatMoney: { type: Function, required: true },
    shippingAddressLine: { type: Function, required: true },
    isPendingPayment: { type: Function, required: true },
    cancelActionLabel: { type: Function, required: true },
    issueTypeLabel: { type: Function, required: true },
    issueStatusLabel: { type: Function, required: true },
    canRequestIssue: { type: Function, required: true },
    pause: { type: Function, required: true },
    resume: { type: Function, required: true },
    requestCancel: { type: Function, required: true },
    openIssueModal: { type: Function, required: true },
})
</script>

<template>
    <div class="rounded-lg border border-border bg-card p-5 shadow-sm">
        <div class="flex items-start justify-between gap-4">
            <div>
                <h2 class="text-lg font-bold text-primary">Deine Abos und Lieferungen</h2>
                <p class="mt-1 text-sm text-secondary">Status, Liefermonat und Tracking an einem Ort.</p>
            </div>
            <span class="rounded-full bg-inputBg px-3 py-1 text-xs font-semibold text-secondary">{{ subscriptions.length }} Einträge</span>
        </div>

        <div v-if="subscriptions.length" class="mt-5 space-y-4">
            <article v-for="subscription in subscriptions" :key="subscription.id" class="rounded-lg border border-border bg-inputBg p-4">
                <div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
                    <div>
                        <p class="text-base font-bold text-primary">{{ subscription.plan?.name || 'Outfit-Abo' }}</p>
                        <p class="mt-1 text-sm text-secondary">
                            {{ statusLabel(subscription.status) }} - Nächste Lieferung: {{ formatDate(subscription.next_delivery_at) }}
                        </p>
                        <p class="mt-1 text-sm text-secondary">
                            Zahlungsart: {{ paymentProviderLabel(subscription.payment_provider) }}
                        </p>
                        <p v-if="subscription.dunning_level" class="mt-1 text-sm text-amber-200">
                            Mahnstufe {{ subscription.dunning_level }}/3
                            <span v-if="subscription.last_dunning_sent_at"> - letzte Mahnung: {{ formatDate(subscription.last_dunning_sent_at) }}</span>
                        </p>
                        <p v-if="subscription.status === 'payment_paused'" class="mt-1 text-sm text-amber-200">
                            Dieses Abo ist bis zum Zahlungseingang pausiert. Es werden keine weiteren Lieferungen vorbereitet.
                        </p>
                        <p class="mt-1 text-sm font-semibold text-primary">{{ formatMoney(subscription.monthly_price_cents, subscription.currency) }} / Monat</p>
                    </div>
                    <div class="flex flex-wrap gap-2">
                        <button v-if="subscription.status === 'active'" type="button" class="rounded-lg border border-border px-3 py-2 text-sm text-primary hover:bg-card" @click="pause(subscription)">Pausieren</button>
                        <button v-if="subscription.status === 'paused'" type="button" class="rounded-lg border border-border px-3 py-2 text-sm text-primary hover:bg-card" @click="resume(subscription)">Fortsetzen</button>
                        <button v-if="!['cancelled', 'cancels_at_period_end'].includes(subscription.status)" type="button" class="rounded-lg border border-red-500/50 px-3 py-2 text-sm text-red-300 hover:bg-red-500/10" @click="requestCancel(subscription)">
                            {{ cancelActionLabel(subscription) }}
                        </button>
                    </div>
                    <p v-if="subscription.status === 'cancels_at_period_end'" class="mt-2 text-sm text-amber-200">
                        Gekündigt zum {{ formatDate(subscription.current_period_ends_at) }}. Bis dahin bleibt das Abo aktiv.
                    </p>
                </div>

                <div
                    v-if="isPendingPayment(subscription) && subscription.payment_provider === 'bank_transfer'"
                    class="mt-4 rounded-lg border border-warning/30 bg-warning/10 p-4"
                >
                    <div class="flex flex-col gap-2 sm:flex-row sm:items-start sm:justify-between">
                        <div>
                            <p class="text-sm font-bold text-primary">Überweisungsdaten</p>
                            <p class="mt-1 text-xs leading-5 text-secondary">
                                Bitte nutze exakt diesen Verwendungszweck, damit deine Zahlung zugeordnet werden kann.
                            </p>
                        </div>
                        <span class="rounded-full bg-card px-3 py-1 text-xs font-semibold text-warning">Zahlung offen</span>
                    </div>

                    <dl class="mt-4 grid gap-3 text-sm sm:grid-cols-2">
                        <div class="rounded-lg bg-card p-3">
                            <dt class="text-xs uppercase text-secondary">Betrag</dt>
                            <dd class="mt-1 font-bold text-primary">{{ formatMoney(subscription.monthly_price_cents, subscription.currency) }}</dd>
                        </div>
                        <div class="rounded-lg bg-card p-3">
                            <dt class="text-xs uppercase text-secondary">Fällig bis</dt>
                            <dd class="mt-1 font-bold text-primary">{{ formatDate(subscription.payment_due_at) }}</dd>
                        </div>
                        <div class="rounded-lg bg-card p-3 sm:col-span-2">
                            <dt class="text-xs uppercase text-secondary">Verwendungszweck / Zahlungsreferenz</dt>
                            <dd class="mt-1 break-all font-bold text-primary">{{ subscription.payment_reference }}</dd>
                        </div>
                        <div class="rounded-lg bg-card p-3">
                            <dt class="text-xs uppercase text-secondary">Kontoinhaber</dt>
                            <dd class="mt-1 font-bold text-primary">{{ subscription.bank_transfer?.bank_account_holder || '-' }}</dd>
                        </div>
                        <div class="rounded-lg bg-card p-3">
                            <dt class="text-xs uppercase text-secondary">Bank</dt>
                            <dd class="mt-1 font-bold text-primary">{{ subscription.bank_transfer?.bank_name || '-' }}</dd>
                        </div>
                        <div class="rounded-lg bg-card p-3">
                            <dt class="text-xs uppercase text-secondary">IBAN</dt>
                            <dd class="mt-1 break-all font-bold text-primary">{{ subscription.bank_transfer?.iban || '-' }}</dd>
                        </div>
                        <div class="rounded-lg bg-card p-3">
                            <dt class="text-xs uppercase text-secondary">BIC</dt>
                            <dd class="mt-1 font-bold text-primary">{{ subscription.bank_transfer?.bic || '-' }}</dd>
                        </div>
                    </dl>
                </div>

                <div v-if="subscription.shipping_address" class="mt-4 rounded-lg bg-card p-3">
                    <p class="text-xs uppercase text-secondary">Lieferadresse</p>
                    <p class="mt-1 text-sm font-semibold text-primary">{{ subscription.shipping_address.name || '-' }}</p>
                    <p class="text-sm text-secondary">{{ shippingAddressLine(subscription.shipping_address) || '-' }}</p>
                    <p v-if="subscription.shipping_address.note" class="mt-1 text-xs text-secondary">{{ subscription.shipping_address.note }}</p>
                </div>

                <div v-if="subscription.deliveries?.length" class="mt-4 grid gap-3 md:grid-cols-2">
                    <div v-for="delivery in subscription.deliveries" :key="delivery.id" class="rounded-lg bg-card p-3">
                        <p class="text-sm font-semibold text-primary">{{ statusLabel(delivery.status) }}</p>
                        <p class="text-xs text-secondary">{{ formatDate(delivery.delivery_month) }}</p>
                        <p v-if="delivery.tracking_number" class="mt-1 text-xs text-secondary">{{ delivery.carrier }} - {{ delivery.tracking_number }}</p>
                        <a v-if="delivery.tracking_url" :href="delivery.tracking_url" target="_blank" rel="noopener noreferrer" class="mt-1 inline-flex text-xs font-semibold text-accent underline underline-offset-2">
                            Tracking öffnen
                        </a>
                        <p v-if="delivery.notes" class="mt-1 text-xs text-secondary">{{ delivery.notes }}</p>
                        <div v-if="delivery.issue_status" class="mt-3 rounded-lg border border-border bg-inputBg p-3">
                            <p class="text-xs font-semibold uppercase text-secondary">{{ issueTypeLabel(delivery.issue_type) }}</p>
                            <p class="mt-1 text-sm font-semibold text-primary">{{ issueStatusLabel(delivery.issue_status) }}</p>
                            <p v-if="delivery.issue_admin_note" class="mt-1 text-xs text-secondary">{{ delivery.issue_admin_note }}</p>
                            <a v-if="delivery.return_tracking_url" :href="delivery.return_tracking_url" target="_blank" rel="noopener noreferrer" class="mt-1 inline-flex text-xs font-semibold text-accent underline underline-offset-2">
                                Retouren-Tracking öffnen
                            </a>
                        </div>
                        <button v-if="canRequestIssue(delivery)" type="button" class="mt-3 rounded-lg border border-border px-3 py-2 text-xs font-semibold text-primary hover:bg-muted" @click="openIssueModal(delivery)">
                            Problem melden
                        </button>
                    </div>
                </div>
            </article>
        </div>
        <p v-else class="mt-5 rounded-lg bg-inputBg p-4 text-sm text-secondary">Noch kein Outfit-Abo aktiv.</p>
    </div>
</template>

