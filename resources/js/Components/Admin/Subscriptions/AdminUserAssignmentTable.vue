<script setup>
import { computed } from 'vue'

const props = defineProps({
    actorLabels: { type: Object, required: true },
    selectedActor: { type: String, required: true },
    userSearch: { type: String, default: '' },
    filteredUsers: { type: Array, default: () => [] },
    selectedUserPlans: { type: Array, default: () => [] },
    subscriptionForUser: { type: Function, required: true },
    formForUser: { type: Function, required: true },
    displayUserName: { type: Function, required: true },
    saveUser: { type: Function, required: true },
    cancelUserSubscription: { type: Function, required: true },
    renewUserSubscription: { type: Function, required: true },
})

const emit = defineEmits(['update:userSearch'])

const searchModel = computed({
    get: () => props.userSearch,
    set: (value) => emit('update:userSearch', value),
})
</script>

<template>
    <section class="surface-card overflow-hidden">
        <div class="border-b border-border p-5">
            <div class="flex flex-col gap-3 lg:flex-row lg:items-center lg:justify-between">
                <div>
                    <h2 class="text-lg font-semibold text-primary">{{ actorLabels[selectedActor] }}-Abos zuordnen</h2>
                    <p class="mt-1 text-sm text-secondary">
                        Liste alle Nutzer und ordne ihnen manuell einen passenden {{ actorLabels[selectedActor] }}-Plan zu.
                    </p>
                </div>
                <div class="relative w-full lg:w-80">
                    <i class="las la-search absolute left-3 top-1/2 -translate-y-1/2 text-secondary"></i>
                    <input v-model="searchModel" type="search" class="w-full rounded-lg border-border bg-inputBg py-2 pl-10 pr-3 text-sm text-primary" placeholder="Nutzer, E-Mail oder Plan suchen">
                </div>
            </div>
        </div>

        <div class="custom-scrollbar overflow-x-auto">
            <table class="min-w-full text-left text-sm">
                <thead class="bg-bg text-xs uppercase text-secondary">
                    <tr>
                        <th class="px-5 py-3">Nutzer</th>
                        <th class="px-5 py-3">Aktueller Plan</th>
                        <th class="px-5 py-3">Neuer Plan</th>
                        <th class="px-5 py-3">Status</th>
                        <th class="px-5 py-3">Laufzeit</th>
                        <th class="px-5 py-3 text-right">Aktion</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-border">
                    <tr v-for="user in filteredUsers" :key="user.id" class="hover:bg-muted/40">
                        <td class="px-5 py-3">
                            <p class="font-semibold text-primary">{{ displayUserName(user) }}</p>
                            <p class="text-xs text-secondary">{{ user.email || '-' }}</p>
                        </td>
                        <td class="px-5 py-3">
                            <span class="rounded-full bg-air-blue/15 px-2 py-1 text-xs font-semibold text-air-blue">
                                {{ subscriptionForUser(user)?.plan?.name || 'Kein Abo' }}
                            </span>
                            <p v-if="subscriptionForUser(user)?.payment_provider" class="mt-1 text-xs text-secondary">{{ subscriptionForUser(user).payment_provider }}</p>
                        </td>
                        <td class="px-5 py-3">
                            <select v-model="formForUser(user).subscription_plan_id" class="min-w-44 rounded-lg border-border bg-inputBg text-sm text-primary">
                                <option v-for="plan in selectedUserPlans" :key="plan.id" :value="plan.id">{{ plan.name }}</option>
                            </select>
                        </td>
                        <td class="px-5 py-3">
                            <select v-model="formForUser(user).status" class="min-w-44 rounded-lg border-border bg-inputBg text-sm text-primary">
                                <option value="trialing">Testphase</option>
                                <option value="active">Aktiv</option>
                                <option value="past_due">Zahlung offen</option>
                                <option value="cancels_at_period_end">Gekuendigt zum Ende</option>
                                <option value="cancelled">Gekuendigt</option>
                            </select>
                            <input v-model="formForUser(user).payment_provider" class="mt-2 min-w-44 rounded-lg border-border bg-inputBg text-xs text-primary" placeholder="Zahlungsart">
                        </td>
                        <td class="px-5 py-3">
                            <div class="grid min-w-44 gap-2">
                                <input v-model="formForUser(user).trial_ends_at" type="date" class="rounded-lg border-border bg-inputBg text-xs text-primary">
                                <input v-model="formForUser(user).current_period_ends_at" type="date" class="rounded-lg border-border bg-inputBg text-xs text-primary">
                            </div>
                            <p v-if="subscriptionForUser(user)?.cancels_at" class="mt-1 text-xs text-warning">Endet {{ subscriptionForUser(user).cancels_at }}</p>
                        </td>
                        <td class="px-5 py-3 text-right">
                            <div class="flex justify-end gap-2">
                                <button type="button" class="rounded-lg border border-border px-3 py-2 text-xs font-semibold text-primary hover:bg-muted disabled:opacity-50" :disabled="!subscriptionForUser(user)" @click="renewUserSubscription(subscriptionForUser(user), 1)">
                                    +1M
                                </button>
                                <button type="button" class="rounded-lg border border-border px-3 py-2 text-xs font-semibold text-primary hover:bg-muted disabled:opacity-50" :disabled="!subscriptionForUser(user)" @click="cancelUserSubscription(subscriptionForUser(user), 'period_end')">
                                    Kündigen
                                </button>
                                <button type="button" class="rounded-lg bg-buttonPrimary px-3 py-2 text-sm font-semibold text-buttonTextPrimary disabled:opacity-50" :disabled="formForUser(user).processing || !selectedUserPlans.length" @click="saveUser(user)">
                                    Speichern
                                </button>
                            </div>
                        </td>
                    </tr>
                </tbody>
            </table>

            <p v-if="!filteredUsers.length" class="px-5 py-8 text-sm text-secondary">
                Keine Nutzer gefunden.
            </p>
            <p v-else-if="!selectedUserPlans.length" class="px-5 pb-5 text-sm text-warning">
                Für {{ actorLabels[selectedActor] }} sind noch keine Abo-Pläne vorhanden.
            </p>
        </div>
    </section>
</template>

