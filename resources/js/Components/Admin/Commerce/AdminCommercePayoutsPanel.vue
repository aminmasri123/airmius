<script setup>
defineProps({
    payoutCandidates: { type: Array, default: () => [] },
    payoutProfiles: { type: Array, default: () => [] },
    payouts: { type: Array, default: () => [] },
    formatMoney: { type: Function, required: true },
})

const emit = defineEmits(['create-payout', 'update-payout-profile', 'mark-payout-paid'])
</script>

<template>
    <section class="surface-card overflow-hidden">
        <div class="border-b border-border p-5">
            <p class="text-xs font-semibold uppercase tracking-wide text-air-blue">Marketplace</p>
            <h2 class="mt-1 text-lg font-semibold text-primary">Auszahlungen</h2>
            <p class="mt-1 text-sm text-secondary">Offene Marketplace-Erloese sammeln, Provision abziehen und Auszahlung vorbereiten.</p>
        </div>

        <div class="grid gap-6 p-5 xl:grid-cols-2">
            <div>
                <h3 class="font-semibold text-primary">Offene Auszahlungsbetraege</h3>
                <div class="mt-3 overflow-x-auto rounded-lg border border-border">
                    <table class="min-w-full text-left text-sm">
                        <tbody class="divide-y divide-border">
                            <tr v-for="candidate in payoutCandidates" :key="candidate.user_id">
                                <td class="px-4 py-3">
                                    <p class="font-semibold text-primary">{{ candidate.name || candidate.email }}</p>
                                    <p class="text-xs text-secondary">{{ candidate.orders_count }} Bestellungen</p>
                                </td>
                                <td class="px-4 py-3 text-secondary">
                                    <p>Brutto {{ formatMoney(candidate.gross_cents) }}</p>
                                    <p>Provision {{ formatMoney(candidate.commission_cents) }}</p>
                                </td>
                                <td class="px-4 py-3 font-semibold text-primary">{{ formatMoney(candidate.amount_cents) }}</td>
                                <td class="px-4 py-3 text-right">
                                    <button class="rounded-lg bg-buttonPrimary px-3 py-2 text-xs font-semibold text-buttonTextPrimary" @click="emit('create-payout', candidate)">
                                        Vorbereiten
                                    </button>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                    <p v-if="!payoutCandidates.length" class="px-4 py-6 text-sm text-secondary">Keine offenen Auszahlungen.</p>
                </div>
            </div>

            <div>
                <h3 class="font-semibold text-primary">Auszahlungsdaten prüfen</h3>
                <div class="mt-3 overflow-x-auto rounded-lg border border-border">
                    <table class="min-w-full text-left text-sm">
                        <tbody class="divide-y divide-border">
                            <tr v-for="profile in payoutProfiles" :key="profile.id">
                                <td class="px-4 py-3">
                                    <p class="font-semibold text-primary">{{ profile.user?.name || profile.user?.email }}</p>
                                    <p class="text-xs text-secondary">{{ profile.paypal_email || profile.iban || 'Keine Zahlungsdaten' }}</p>
                                </td>
                                <td class="px-4 py-3 text-secondary">{{ profile.status }}</td>
                                <td class="px-4 py-3 text-right">
                                    <div class="flex justify-end gap-2">
                                        <button class="rounded-lg border border-border px-3 py-2 text-xs font-semibold text-primary" @click="emit('update-payout-profile', profile, 'approved')">Freigeben</button>
                                        <button class="rounded-lg border border-danger/40 px-3 py-2 text-xs font-semibold text-danger" @click="emit('update-payout-profile', profile, 'blocked')">Sperren</button>
                                    </div>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                    <p v-if="!payoutProfiles.length" class="px-4 py-6 text-sm text-secondary">Noch keine Auszahlungsprofile.</p>
                </div>
            </div>
        </div>

        <div class="border-t border-border p-5">
            <h3 class="font-semibold text-primary">Auszahlungshistorie</h3>
            <div class="mt-3 overflow-x-auto rounded-lg border border-border">
                <table class="min-w-full text-left text-sm">
                    <tbody class="divide-y divide-border">
                        <tr v-for="payout in payouts" :key="payout.id">
                            <td class="px-4 py-3">
                                <p class="font-semibold text-primary">{{ payout.reference || `Auszahlung #${payout.id}` }}</p>
                                <p class="text-xs text-secondary">{{ payout.user?.email || '-' }}</p>
                            </td>
                            <td class="px-4 py-3 text-secondary">
                                <p>Brutto {{ formatMoney(payout.gross_cents) }}</p>
                                <p>Provision {{ formatMoney(payout.commission_cents) }}</p>
                            </td>
                            <td class="px-4 py-3 font-semibold text-primary">{{ formatMoney(payout.amount_cents) }}</td>
                            <td class="px-4 py-3 text-secondary">{{ payout.status }}</td>
                            <td class="px-4 py-3 text-right">
                                <button v-if="payout.status !== 'paid'" class="rounded-lg bg-buttonPrimary px-3 py-2 text-xs font-semibold text-buttonTextPrimary" @click="emit('mark-payout-paid', payout)">
                                    Ausgezahlt
                                </button>
                            </td>
                        </tr>
                    </tbody>
                </table>
                <p v-if="!payouts.length" class="px-4 py-6 text-sm text-secondary">Noch keine Auszahlungen vorbereitet.</p>
            </div>
        </div>
    </section>
</template>

