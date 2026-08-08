<script setup>
import { computed } from 'vue'

const props = defineProps({
    clubSearch: { type: String, default: '' },
    filteredClubs: { type: Array, default: () => [] },
    clubPlans: { type: Array, default: () => [] },
    statusLabels: { type: Object, required: true },
    formForClub: { type: Function, required: true },
    saveClub: { type: Function, required: true },
    cancelClubSubscription: { type: Function, required: true },
    renewClubSubscription: { type: Function, required: true },
})

const emit = defineEmits(['update:clubSearch'])

const searchModel = computed({
    get: () => props.clubSearch,
    set: (value) => emit('update:clubSearch', value),
})
</script>

<template>
    <section class="surface-card overflow-hidden">
        <div class="border-b border-border p-5">
            <div class="flex flex-col gap-3 lg:flex-row lg:items-center lg:justify-between">
                <div>
                    <h2 class="text-lg font-semibold text-primary">Vereins-Abos</h2>
                    <p class="mt-1 text-sm text-secondary">Ordne Vereinen einen Vereinsplan zu und behalte Nutzung und Limits im Blick.</p>
                </div>
                <div class="relative w-full lg:w-80">
                    <i class="las la-search absolute left-3 top-1/2 -translate-y-1/2 text-secondary"></i>
                    <input v-model="searchModel" type="search" class="w-full rounded-lg border-border bg-inputBg py-2 pl-10 pr-3 text-sm text-primary" placeholder="Verein oder Plan suchen">
                </div>
            </div>
        </div>

        <div class="custom-scrollbar overflow-x-auto">
            <table class="min-w-full text-left text-sm">
                <thead class="bg-bg text-xs uppercase text-secondary">
                    <tr>
                        <th class="px-5 py-3">Verein</th>
                        <th class="px-5 py-3">Nutzung</th>
                        <th class="px-5 py-3">{{ $t('Aktueller Plan') }}</th>
                        <th class="px-5 py-3">Neuer Plan</th>
                        <th class="px-5 py-3">Status</th>
                        <th class="px-5 py-3">Laufzeit</th>
                        <th class="px-5 py-3 text-right">Aktion</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-border">
                    <tr v-for="club in filteredClubs" :key="club.id" class="hover:bg-muted/40">
                        <td class="px-5 py-3">
                            <p class="font-semibold text-primary">{{ club.name }}</p>
                            <p class="text-xs text-secondary">ID {{ club.id }}</p>
                        </td>
                        <td class="px-5 py-3 text-secondary">
                            <p>{{ club.member_usage }} Mitglieder</p>
                            <p class="text-xs">{{ club.teams_count }} Teams</p>
                        </td>
                        <td class="px-5 py-3">
                            <span class="rounded-full bg-air-blue/15 px-2 py-1 text-xs font-semibold text-air-blue">
                                {{ club.plan?.name || 'Free' }}
                            </span>
                            <p v-if="club.subscription?.payment_provider" class="mt-1 text-xs text-secondary">{{ club.subscription.payment_provider }}</p>
                        </td>
                        <td class="px-5 py-3">
                            <select v-model="formForClub(club).subscription_plan_id" class="min-w-40 rounded-lg border-border bg-inputBg text-sm text-primary">
                                <option v-for="plan in clubPlans" :key="plan.id" :value="plan.id">{{ plan.name }}</option>
                            </select>
                        </td>
                        <td class="px-5 py-3">
                            <select v-model="formForClub(club).status" class="min-w-36 rounded-lg border-border bg-inputBg text-sm text-primary">
                                <option value="trialing">Testphase</option>
                                <option value="active">Aktiv</option>
                                <option value="past_due">Zahlung offen</option>
                                <option value="cancels_at_period_end">Gekündigt zum Ende</option>
                                <option value="cancelled">Gekündigt</option>
                            </select>
                            <p class="mt-1 text-xs text-secondary">{{ statusLabels[formForClub(club).status] }}</p>
                        </td>
                        <td class="px-5 py-3">
                            <div class="grid min-w-44 gap-2">
                                <input v-model="formForClub(club).trial_ends_at" type="date" class="rounded-lg border-border bg-inputBg text-xs text-primary" title="Testphase bis">
                                <input v-model="formForClub(club).current_period_ends_at" type="date" class="rounded-lg border-border bg-inputBg text-xs text-primary" title="Aktuelle Periode bis">
                            </div>
                            <p v-if="club.subscription?.cancels_at" class="mt-1 text-xs text-warning">Endet {{ club.subscription.cancels_at }}</p>
                        </td>
                        <td class="px-5 py-3 text-right">
                            <div class="flex justify-end gap-2">
                                <button type="button" class="rounded-lg border border-border px-3 py-2 text-xs font-semibold text-primary hover:bg-muted disabled:opacity-50" :disabled="!club.subscription" @click="renewClubSubscription(club, 1)">
                                    +1M
                                </button>
                                <button type="button" class="rounded-lg border border-border px-3 py-2 text-xs font-semibold text-primary hover:bg-muted disabled:opacity-50" :disabled="!club.subscription" @click="cancelClubSubscription(club, 'period_end')">
                                    Kündigen
                                </button>
                                <button type="button" class="rounded-lg bg-buttonPrimary px-3 py-2 text-sm font-semibold text-buttonTextPrimary disabled:opacity-50" :disabled="formForClub(club).processing" @click="saveClub(club)">
                                    Speichern
                                </button>
                            </div>
                        </td>
                    </tr>
                </tbody>
            </table>

            <p v-if="!filteredClubs.length" class="px-5 py-8 text-sm text-secondary">
                Keine Vereine gefunden.
            </p>
        </div>
    </section>
</template>
