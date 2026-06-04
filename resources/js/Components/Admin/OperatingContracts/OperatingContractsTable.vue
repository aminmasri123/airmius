<script setup>
import { Link } from '@inertiajs/vue3'
import { computed } from 'vue'

const props = defineProps({
    contracts: { type: Object, required: true },
    rows: { type: Array, default: () => [] },
    canManage: { type: Boolean, default: false },
    filterStatus: { type: String, default: 'active' },
    filterCategory: { type: String, default: 'all' },
    filterSearch: { type: String, default: '' },
    allStatusOptions: { type: Array, default: () => [] },
    allCategoryOptions: { type: Array, default: () => [] },
    statusClass: { type: Function, required: true },
    deadlineClass: { type: Function, required: true },
    deadlineLabel: { type: Function, required: true },
    ownerLabel: { type: Function, required: true },
    openCreateModal: { type: Function, required: true },
    openEditModal: { type: Function, required: true },
    deleteContract: { type: Function, required: true },
    applyFilters: { type: Function, required: true },
    resetFilters: { type: Function, required: true },
})

const emit = defineEmits(['update:filterStatus', 'update:filterCategory', 'update:filterSearch'])

const filterStatusModel = computed({
    get: () => props.filterStatus,
    set: (value) => emit('update:filterStatus', value),
})

const filterCategoryModel = computed({
    get: () => props.filterCategory,
    set: (value) => emit('update:filterCategory', value),
})

const filterSearchModel = computed({
    get: () => props.filterSearch,
    set: (value) => emit('update:filterSearch', value),
})
</script>

<template>
    <div class="space-y-5">
        <form class="rounded-2xl border border-border bg-card p-4" @submit.prevent="applyFilters">
            <div class="grid gap-3 md:grid-cols-[11rem_13rem_minmax(0,1fr)_auto] md:items-end">
                <label class="block">
                    <span class="text-sm font-semibold text-primary">Status</span>
                    <select v-model="filterStatusModel" class="mt-1 w-full rounded-xl border-border bg-inputBg text-primary">
                        <option v-for="status in allStatusOptions" :key="status.value" :value="status.value">
                            {{ status.label }}
                        </option>
                    </select>
                </label>
                <label class="block">
                    <span class="text-sm font-semibold text-primary">Kategorie</span>
                    <select v-model="filterCategoryModel" class="mt-1 w-full rounded-xl border-border bg-inputBg text-primary">
                        <option v-for="category in allCategoryOptions" :key="category.value" :value="category.value">
                            {{ category.label }}
                        </option>
                    </select>
                </label>
                <label class="block">
                    <span class="text-sm font-semibold text-primary">Suche</span>
                    <input
                        v-model="filterSearchModel"
                        class="mt-1 w-full rounded-xl border-border bg-inputBg text-primary"
                        placeholder="Anbieter, Vertrag, Kundennummer ..."
                    >
                </label>
                <div class="flex gap-2">
                    <button type="submit" class="rounded-xl bg-buttonPrimary px-4 py-2.5 text-sm font-black text-buttonTextPrimary">
                        Filtern
                    </button>
                    <button type="button" class="rounded-xl border border-border px-4 py-2.5 text-sm font-bold text-primary hover:bg-muted" @click="resetFilters">
                        Reset
                    </button>
                </div>
            </div>
        </form>

        <section class="overflow-hidden rounded-2xl border border-border bg-card">
            <div class="flex flex-col gap-3 border-b border-border p-5 sm:flex-row sm:items-center sm:justify-between">
                <div>
                    <p class="text-xs font-semibold uppercase tracking-[0.16em] text-air-blue">Vertragsliste</p>
                    <h2 class="mt-1 text-lg font-black text-primary">Externe Abos und laufende Kosten</h2>
                </div>
                <button
                    v-if="canManage"
                    type="button"
                    class="inline-flex items-center justify-center gap-2 rounded-xl border border-border px-4 py-2 text-sm font-bold text-primary hover:bg-muted"
                    @click="openCreateModal"
                >
                    <i class="las la-plus text-lg"></i>
                    Neu
                </button>
            </div>

            <div class="hidden overflow-x-auto lg:block">
                <table class="min-w-full text-left text-sm">
                    <thead class="bg-bg text-xs uppercase tracking-[0.12em] text-secondary">
                        <tr>
                            <th class="px-5 py-3">Vertrag</th>
                            <th class="px-5 py-3">Kosten</th>
                            <th class="px-5 py-3">Termine</th>
                            <th class="px-5 py-3">Status</th>
                            <th class="px-5 py-3 text-right">Aktionen</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-border">
                        <tr v-for="contract in rows" :key="contract.id" class="align-top hover:bg-muted/40">
                            <td class="px-5 py-4">
                                <p class="font-black text-primary">{{ contract.name }}</p>
                                <p class="mt-1 text-sm text-secondary">{{ contract.vendor || 'Kein Anbieter' }}</p>
                                <p class="mt-1 text-xs text-secondary">{{ contract.category_label }}</p>
                            </td>
                            <td class="px-5 py-4">
                                <p class="font-black text-primary">{{ contract.amount }}</p>
                                <p class="text-xs text-secondary">{{ contract.billing_interval_label }}</p>
                                <p class="mt-2 text-xs text-secondary">Monatlich: {{ contract.monthly_amount }}</p>
                            </td>
                            <td class="px-5 py-4">
                                <p class="font-bold text-primary">Zahlung: {{ contract.next_due_on || '-' }}</p>
                                <p class="text-xs" :class="deadlineClass(contract.days_until_next_due)">
                                    {{ deadlineLabel(contract.days_until_next_due) }}
                                </p>
                                <p class="mt-2 font-bold text-primary">Kündigung: {{ contract.notice_until_on || '-' }}</p>
                                <p class="text-xs" :class="deadlineClass(contract.days_until_notice)">
                                    {{ deadlineLabel(contract.days_until_notice) }}
                                </p>
                            </td>
                            <td class="px-5 py-4">
                                <span class="inline-flex rounded-full border px-3 py-1 text-xs font-black" :class="statusClass(contract.status)">
                                    {{ contract.status_label }}
                                </span>
                                <p class="mt-2 text-xs text-secondary">{{ contract.payment_method_label }}</p>
                                <p class="mt-1 text-xs text-secondary">{{ ownerLabel(contract.owner) }}</p>
                            </td>
                            <td class="px-5 py-4 text-right">
                                <div class="flex flex-wrap justify-end gap-2">
                                    <a
                                        v-if="contract.website"
                                        :href="contract.website"
                                        target="_blank"
                                        rel="noopener"
                                        class="rounded-lg border border-border px-3 py-2 text-xs font-bold text-primary hover:bg-muted"
                                    >
                                        Website
                                    </a>
                                    <a
                                        v-if="contract.document_url"
                                        :href="contract.document_url"
                                        target="_blank"
                                        rel="noopener"
                                        class="rounded-lg border border-border px-3 py-2 text-xs font-bold text-primary hover:bg-muted"
                                    >
                                        Beleg
                                    </a>
                                    <button
                                        v-if="contract.update_url"
                                        type="button"
                                        class="rounded-lg border border-border px-3 py-2 text-xs font-bold text-primary hover:bg-muted"
                                        @click="openEditModal(contract)"
                                    >
                                        Bearbeiten
                                    </button>
                                    <button
                                        v-if="contract.delete_url"
                                        type="button"
                                        class="rounded-lg bg-error px-3 py-2 text-xs font-bold text-white hover:bg-error/90"
                                        @click="deleteContract(contract)"
                                    >
                                        Löschen
                                    </button>
                                </div>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <div class="grid gap-3 p-4 lg:hidden">
                <article v-for="contract in rows" :key="contract.id" class="rounded-xl border border-border bg-inputBg p-4">
                    <div class="flex items-start justify-between gap-3">
                        <div class="min-w-0">
                            <p class="truncate font-black text-primary">{{ contract.name }}</p>
                            <p class="mt-1 text-sm text-secondary">{{ contract.vendor || contract.category_label }}</p>
                        </div>
                        <span class="shrink-0 rounded-full border px-3 py-1 text-xs font-black" :class="statusClass(contract.status)">
                            {{ contract.status_label }}
                        </span>
                    </div>
                    <div class="mt-4 grid grid-cols-2 gap-3 text-sm">
                        <div>
                            <p class="text-xs uppercase text-secondary">Kosten</p>
                            <p class="mt-1 font-bold text-primary">{{ contract.amount }}</p>
                            <p class="text-xs text-secondary">{{ contract.billing_interval_label }}</p>
                        </div>
                        <div>
                            <p class="text-xs uppercase text-secondary">Nächste Zahlung</p>
                            <p class="mt-1 font-bold text-primary">{{ contract.next_due_on || '-' }}</p>
                            <p class="text-xs" :class="deadlineClass(contract.days_until_next_due)">
                                {{ deadlineLabel(contract.days_until_next_due) }}
                            </p>
                        </div>
                    </div>
                    <div class="mt-4 flex flex-wrap gap-2">
                        <button v-if="contract.update_url" type="button" class="rounded-lg border border-border px-3 py-2 text-xs font-bold text-primary" @click="openEditModal(contract)">Bearbeiten</button>
                        <button v-if="contract.delete_url" type="button" class="rounded-lg bg-error px-3 py-2 text-xs font-bold text-white" @click="deleteContract(contract)">Löschen</button>
                    </div>
                </article>
            </div>

            <p v-if="!rows.length" class="px-5 py-10 text-center text-sm text-secondary">
                Noch keine passenden Verträge vorhanden.
            </p>

            <div v-if="contracts.links?.length > 3" class="flex flex-wrap gap-2 border-t border-border px-5 py-4">
                <Link
                    v-for="link in contracts.links"
                    :key="link.label"
                    :href="link.url || '#'"
                    preserve-scroll
                    class="rounded-lg border border-border px-3 py-2 text-sm"
                    :class="[link.active ? 'bg-buttonPrimary text-buttonTextPrimary' : 'text-primary hover:bg-muted', !link.url ? 'pointer-events-none opacity-40' : '']"
                    v-html="link.label"
                />
            </div>
        </section>
    </div>
</template>




