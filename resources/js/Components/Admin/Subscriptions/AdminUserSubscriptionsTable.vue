<script setup>
defineProps({
    userSubscriptions: { type: Array, default: () => [] },
    userPlans: { type: Array, default: () => [] },
    formForUserSubscription: { type: Function, required: true },
    saveUserSubscription: { type: Function, required: true },
    cancelUserSubscription: { type: Function, required: true },
    renewUserSubscription: { type: Function, required: true },
})
</script>

<template>
    <section class="surface-card overflow-hidden">
        <div class="border-b border-border p-5">
            <div>
                <h2 class="text-lg font-semibold text-primary">Nutzer-Abos</h2>
                <p class="mt-1 text-sm text-secondary">
                    Persönliche Pläne für Sportler, Trainer, Sponsoren, Anbieter und weitere Rollen verwalten.
                </p>
            </div>
        </div>

        <div class="custom-scrollbar overflow-x-auto">
            <table class="min-w-full text-left text-sm">
                <thead class="bg-bg text-xs uppercase text-secondary">
                    <tr>
                        <th class="px-5 py-3">Nutzer</th>
                        <th class="px-5 py-3">Plan</th>
                        <th class="px-5 py-3">Status</th>
                        <th class="px-5 py-3">Laufzeit</th>
                        <th class="px-5 py-3 text-right">Aktion</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-border">
                    <tr v-for="subscription in userSubscriptions" :key="subscription.id" class="hover:bg-muted/40">
                        <td class="px-5 py-3">
                            <p class="font-semibold text-primary">{{ subscription.user?.name || '-' }}</p>
                            <p class="text-xs text-secondary">{{ subscription.user?.email || '-' }}</p>
                        </td>
                        <td class="px-5 py-3">
                            <select v-model="formForUserSubscription(subscription).subscription_plan_id" class="min-w-44 rounded-lg border-border bg-inputBg text-sm text-primary">
                                <option v-for="plan in userPlans" :key="plan.id" :value="plan.id">{{ plan.name }}</option>
                            </select>
                            <p class="mt-1 text-xs text-secondary">{{ subscription.plan?.target_actor || '-' }}</p>
                        </td>
                        <td class="px-5 py-3">
                            <select v-model="formForUserSubscription(subscription).status" class="min-w-44 rounded-lg border-border bg-inputBg text-sm text-primary">
                                <option value="trialing">Testphase</option>
                                <option value="active">Aktiv</option>
                                <option value="past_due">Zahlung offen</option>
                                <option value="cancels_at_period_end">Gekuendigt zum Ende</option>
                                <option value="cancelled">Gekuendigt</option>
                            </select>
                            <input v-model="formForUserSubscription(subscription).payment_provider" class="mt-2 min-w-44 rounded-lg border-border bg-inputBg text-xs text-primary" placeholder="Zahlungsart">
                        </td>
                        <td class="px-5 py-3">
                            <div class="grid min-w-44 gap-2">
                                <input v-model="formForUserSubscription(subscription).trial_ends_at" type="date" class="rounded-lg border-border bg-inputBg text-xs text-primary">
                                <input v-model="formForUserSubscription(subscription).current_period_ends_at" type="date" class="rounded-lg border-border bg-inputBg text-xs text-primary">
                            </div>
                            <p v-if="subscription.cancels_at" class="mt-1 text-xs text-warning">Endet {{ subscription.cancels_at }}</p>
                        </td>
                        <td class="px-5 py-3 text-right">
                            <div class="flex justify-end gap-2">
                                <button type="button" class="rounded-lg border border-border px-3 py-2 text-xs font-semibold text-primary hover:bg-muted" @click="renewUserSubscription(subscription, 1)">
                                    +1M
                                </button>
                                <button type="button" class="rounded-lg border border-border px-3 py-2 text-xs font-semibold text-primary hover:bg-muted" @click="cancelUserSubscription(subscription, 'period_end')">
                                    Kündigen
                                </button>
                                <button type="button" class="rounded-lg bg-buttonPrimary px-3 py-2 text-sm font-semibold text-buttonTextPrimary disabled:opacity-50" :disabled="formForUserSubscription(subscription).processing" @click="saveUserSubscription(subscription)">
                                    Speichern
                                </button>
                            </div>
                        </td>
                    </tr>
                </tbody>
            </table>

            <p v-if="!userSubscriptions.length" class="px-5 py-8 text-sm text-secondary">
                Noch keine Nutzer-Abos vorhanden.
            </p>
        </div>
    </section>
</template>


