<script setup>
defineProps({
    payoutForm: { type: Object, required: true },
    payoutRequestForm: { type: Object, required: true },
    payoutProfile: { type: Object, default: null },
    payoutSummary: { type: Object, default: () => ({}) },
    myPayouts: { type: Array, default: () => [] },
    formatMoney: { type: Function, required: true },
    payoutStatusLabel: { type: Function, required: true },
})

const emit = defineEmits(['store-payout-profile', 'request-payout'])
</script>

<template>
    <section class="grid gap-6 xl:grid-cols-[minmax(0,1fr)_22rem]">
            <article class="surface-card p-5">
                <h2 class="text-lg font-semibold text-primary">{{ $t('commerce.payout.ui.data_title') }}</h2>
                <p class="mt-1 text-sm text-secondary">{{ $t('commerce.payout.ui.data_intro') }}</p>
                <form class="mt-4 grid gap-3 md:grid-cols-2" @submit.prevent="emit('store-payout-profile')">
                    <input v-model="payoutForm.account_holder" class="rounded-lg border-border bg-inputBg text-sm text-primary" :placeholder="$t('commerce.ui.account_holder_placeholder')">
                    <input v-model="payoutForm.paypal_email" type="email" class="rounded-lg border-border bg-inputBg text-sm text-primary" :placeholder="$t('commerce.payout.ui.paypal_email')">
                    <input v-model="payoutForm.iban" class="rounded-lg border-border bg-inputBg text-sm text-primary" :placeholder="$t('commerce.ui.iban_placeholder')">
                    <input v-model="payoutForm.bic" class="rounded-lg border-border bg-inputBg text-sm text-primary" :placeholder="$t('commerce.ui.bic_placeholder')">
                    <input v-model="payoutForm.tax_number" class="rounded-lg border-border bg-inputBg text-sm text-primary" :placeholder="$t('commerce.payout.ui.tax_number_optional')">
                    <textarea v-model="payoutForm.notes" rows="2" class="rounded-lg border-border bg-inputBg text-sm text-primary" :placeholder="$t('commerce.payout.ui.payout_notes')"></textarea>
                    <button class="md:col-span-2 rounded-lg bg-buttonPrimary px-4 py-2 text-sm font-semibold text-buttonTextPrimary">{{ $t('commerce.payout.ui.save_data') }}</button>
                </form>
                <p v-if="payoutProfile" class="mt-3 text-sm text-secondary">{{ $t('commerce.payout.ui.status') }}: {{ payoutStatusLabel(payoutProfile.status) }}</p>

                <div class="mt-6 rounded-lg border border-border bg-bg p-4">
                    <div class="flex flex-col gap-2 sm:flex-row sm:items-start sm:justify-between">
                        <div>
                            <h3 class="font-semibold text-primary">{{ $t('commerce.payout.ui.request_title') }}</h3>
                            <p class="mt-1 text-sm text-secondary">{{ $t('commerce.payout.ui.request_hint') }}</p>
                        </div>
                        <span class="rounded-full bg-muted px-3 py-1 text-xs font-semibold text-secondary">{{ $t('commerce.payout.ui.eligible_badge', { count: payoutSummary.eligible_orders || 0 }) }}</span>
                    </div>
                    <div v-if="payoutRequestForm.errors.payout" class="mt-3 rounded-lg border border-error/40 bg-error/10 p-3 text-sm text-error">
                        {{ payoutRequestForm.errors.payout }}
                    </div>
                    <form class="mt-4 grid gap-3 md:grid-cols-[12rem_minmax(0,1fr)_auto]" @submit.prevent="emit('request-payout')">
                        <select v-model="payoutRequestForm.method" class="rounded-lg border-border bg-inputBg text-sm text-primary">
                            <option value="bank_transfer">{{ $t('commerce.payment.bank_transfer') }}</option>
                            <option value="paypal">{{ $t('commerce.payment.paypal') }}</option>
                        </select>
                        <input v-model="payoutRequestForm.notes" class="rounded-lg border-border bg-inputBg text-sm text-primary" :placeholder="$t('commerce.payout.ui.note_optional')">
                        <button class="rounded-lg bg-buttonPrimary px-4 py-2 text-sm font-semibold text-buttonTextPrimary disabled:opacity-50" :disabled="payoutRequestForm.processing || !Number(payoutSummary.amount_cents || 0)">
                            {{ $t('commerce.payout.ui.request') }}
                        </button>
                    </form>
                </div>

                <div class="mt-6 overflow-hidden rounded-lg border border-border">
                    <div class="border-b border-border bg-bg px-4 py-3">
                        <h3 class="font-semibold text-primary">{{ $t('commerce.payout.ui.history') }}</h3>
                    </div>
                    <div class="divide-y divide-border">
                        <div v-for="payout in myPayouts" :key="payout.id" class="grid gap-2 p-4 text-sm md:grid-cols-[1fr_auto_auto] md:items-center">
                            <div>
                                <p class="font-semibold text-primary">{{ payout.reference || $t('commerce.payout.ui.reference', { id: payout.id }) }}</p>
                                <p class="text-xs text-secondary">{{ $t('commerce.payout.ui.orders_count', { count: payout.orders_count || 0 }) }} · {{ $t(`commerce.payment.${payout.method}`) }}</p>
                            </div>
                            <p class="font-semibold text-primary">{{ formatMoney(payout.amount_cents) }}</p>
                            <span class="justify-self-start rounded-full bg-muted px-2 py-1 text-xs font-semibold text-secondary md:justify-self-end">{{ payoutStatusLabel(payout.status) }}</span>
                        </div>
                        <p v-if="!myPayouts.length" class="p-4 text-sm text-secondary">{{ $t('commerce.payout.ui.none') }}</p>
                    </div>
                </div>
            </article>

            <aside class="surface-card p-5">
                <h2 class="text-lg font-semibold text-primary">{{ $t('commerce.payout.ui.eligible_title') }}</h2>
                <div class="mt-4 space-y-3">
                    <div class="rounded-lg border border-border bg-bg p-3">
                        <p class="text-xs uppercase text-secondary">{{ $t('commerce.payout.ui.eligible_orders') }}</p>
                        <p class="mt-1 text-xl font-bold text-primary">{{ payoutSummary.eligible_orders || 0 }}</p>
                        <p class="mt-1 text-xs text-secondary">{{ $t('commerce.payout.ui.waiting_orders', { count: payoutSummary.waiting_orders || 0 }) }}</p>
                    </div>
                    <div class="rounded-lg border border-border bg-bg p-3">
                        <p class="text-xs uppercase text-secondary">{{ $t('commerce.payout.ui.gross') }}</p>
                        <p class="mt-1 text-xl font-bold text-primary">{{ formatMoney(payoutSummary.gross_cents) }}</p>
                    </div>
                    <div class="rounded-lg border border-border bg-bg p-3">
                        <p class="text-xs uppercase text-secondary">{{ $t('commerce.payout.ui.commission') }}</p>
                        <p class="mt-1 text-xl font-bold text-primary">{{ formatMoney(payoutSummary.commission_cents) }}</p>
                    </div>
                    <div class="rounded-lg border border-success/30 bg-success/10 p-3">
                        <p class="text-xs uppercase text-success">{{ $t('commerce.payout.ui.eligible_title') }}</p>
                        <p class="mt-1 text-xl font-bold text-primary">{{ formatMoney(payoutSummary.amount_cents) }}</p>
                    </div>
                    <div class="rounded-lg border border-border bg-bg p-3">
                        <p class="text-xs uppercase text-secondary">{{ $t('commerce.payout.ui.already_requested') }}</p>
                        <p class="mt-1 text-xl font-bold text-primary">{{ formatMoney(payoutSummary.requested_cents) }}</p>
                        <p class="mt-1 text-xs text-secondary">{{ $t('commerce.payout.ui.open_requests', { count: payoutSummary.requested_count || 0 }) }}</p>
                    </div>
                </div>
            </aside>
        </section>
</template>

