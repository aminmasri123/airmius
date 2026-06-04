<script setup>
defineProps({
    pendingBankTransfers: { type: Array, default: () => [] },
    markTransferPaid: { type: Function, required: true },
})
</script>

<template>
    <section class="surface-card overflow-hidden">
        <div class="border-b border-border p-5">
            <div class="flex flex-col gap-2 lg:flex-row lg:items-center lg:justify-between">
                <div>
                    <h2 class="text-lg font-semibold text-primary">Offene Überweisungen</h2>
                    <p class="mt-1 text-sm text-secondary">
                        Airmius-Abos, die per Banküberweisung gebucht wurden und noch manuell bestätigt werden müssen.
                    </p>
                </div>
                <span class="rounded-full bg-air-blue/15 px-3 py-1 text-xs font-semibold text-air-blue">
                    {{ pendingBankTransfers.length }} offen
                </span>
            </div>
        </div>

        <div class="custom-scrollbar overflow-x-auto">
            <table v-if="pendingBankTransfers.length" class="min-w-full text-left text-sm">
                <thead class="bg-bg text-xs uppercase text-secondary">
                    <tr>
                        <th class="px-5 py-3">Referenz</th>
                        <th class="px-5 py-3">Kunde</th>
                        <th class="px-5 py-3">Plan</th>
                        <th class="px-5 py-3">Betrag</th>
                        <th class="px-5 py-3">Faellig</th>
                        <th class="px-5 py-3 text-right">Aktion</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-border">
                    <tr v-for="checkout in pendingBankTransfers" :key="checkout.id" class="hover:bg-muted/40">
                        <td class="px-5 py-3">
                            <p class="font-semibold text-primary">{{ checkout.payment_reference }}</p>
                            <p class="text-xs text-secondary">Checkout {{ checkout.id }}</p>
                        </td>
                        <td class="px-5 py-3">
                            <p class="font-semibold text-primary">{{ checkout.club?.name || checkout.user?.name || '-' }}</p>
                            <p class="text-xs text-secondary">{{ checkout.user?.email || '-' }}</p>
                        </td>
                        <td class="px-5 py-3 text-secondary">{{ checkout.plan?.name || '-' }}</td>
                        <td class="px-5 py-3 font-semibold text-primary">{{ checkout.amount }}</td>
                        <td class="px-5 py-3 text-secondary">{{ checkout.due_at || '-' }}</td>
                        <td class="px-5 py-3 text-right">
                            <button type="button" class="rounded-lg bg-buttonPrimary px-3 py-2 text-sm font-semibold text-buttonTextPrimary" @click="markTransferPaid(checkout)">
                                Als bezahlt markieren
                            </button>
                        </td>
                    </tr>
                </tbody>
            </table>

            <p v-else class="px-5 py-8 text-sm text-secondary">
                Keine offenen Überweisungen.
            </p>
        </div>
    </section>
</template>

