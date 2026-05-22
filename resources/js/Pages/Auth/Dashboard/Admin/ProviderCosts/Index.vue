<script setup>
import AppLayout from '@/Components/Auth/Layouts/AppLayout.vue'
import { Head, router } from '@inertiajs/vue3'
import { computed, ref } from 'vue'

defineOptions({ layout: AppLayout })

const props = defineProps({
    dashboard: {
        type: Object,
        default: () => ({}),
    },
})

const dashboard = computed(() => props.dashboard || {})
const selectedMonth = ref(props.dashboard.period?.month || new Date().toISOString().slice(0, 7))
const cards = computed(() => props.dashboard.cards || [])
const comparisons = computed(() => props.dashboard.comparisons || [])
const usageRows = computed(() => props.dashboard.usageRows || [])
const recommendations = computed(() => props.dashboard.recommendations || [])

const formatNumber = (value) => Number(value || 0).toLocaleString('de-DE')
const formatCurrency = (value) => `${Number(value || 0).toLocaleString('de-DE', {
    minimumFractionDigits: 2,
    maximumFractionDigits: 2,
})} €`
const percentClass = (status) => ({
    critical: 'bg-danger text-white',
    warning: 'bg-air-orange text-black',
    ok: 'bg-emerald-400 text-black',
    active: 'bg-air-blue text-white',
    empty: 'bg-muted text-secondary',
}[status] || 'bg-muted text-secondary')
const recommendationClass = (level) => ({
    critical: 'border-danger/40 bg-danger/10 text-danger',
    warning: 'border-air-orange/40 bg-air-orange/10 text-air-orange',
    info: 'border-air-blue/40 bg-air-blue/10 text-air-blue',
    ok: 'border-emerald-400/40 bg-emerald-400/10 text-emerald-200',
}[level] || 'border-border bg-inputBg text-secondary')

const loadMonth = () => {
    router.get(route('admin.provider-costs.index'), { month: selectedMonth.value }, {
        preserveScroll: true,
        preserveState: true,
    })
}
</script>

<template>
    <Head title="Provider-Kosten" />

    <div class="space-y-5">
        <section class="surface-card overflow-hidden">
            <div class="border-b border-border p-5">
                <div class="flex flex-col gap-4 lg:flex-row lg:items-start lg:justify-between">
                    <div>
                        <p class="text-xs font-semibold uppercase tracking-wide text-secondary">Admin Kontrolle</p>
                        <h1 class="mt-1 text-2xl font-semibold text-primary">Provider-Kosten</h1>
                        <p class="mt-2 max-w-3xl text-sm leading-6 text-secondary">
                            Behalte Karte, Routing, Navigation und KI im Blick. Die Zahlen helfen zu erkennen,
                            wann Mapbox, GraphHopper, openrouteservice, eigene Infrastruktur oder KI-Anbieter gewechselt werden sollten.
                        </p>
                    </div>
                    <div class="flex w-full gap-2 sm:w-auto">
                        <input v-model="selectedMonth" type="month" class="min-w-0 flex-1 rounded-xl border border-border bg-inputBg px-3 py-2 text-sm text-primary sm:w-44">
                        <button type="button" class="rounded-xl bg-buttonPrimary px-4 py-2 text-sm font-bold text-buttonTextPrimary" @click="loadMonth">
                            Laden
                        </button>
                    </div>
                </div>
            </div>

            <div class="grid gap-3 p-5 sm:grid-cols-2 xl:grid-cols-4">
                <article v-for="card in cards" :key="card.label" class="rounded-2xl border border-border bg-bg p-4">
                    <div class="flex items-start justify-between gap-3">
                        <div>
                            <p class="text-xs font-bold uppercase text-secondary">{{ card.label }}</p>
                            <p class="mt-2 text-2xl font-black text-primary">{{ formatNumber(card.value) }}</p>
                            <p class="mt-1 text-xs font-semibold text-secondary">{{ card.unit }}</p>
                        </div>
                        <span class="rounded-full px-2.5 py-1 text-xs font-black" :class="percentClass(card.status)">
                            {{ card.percent !== null && card.percent !== undefined ? `${card.percent}%` : 'Info' }}
                        </span>
                    </div>
                    <div v-if="card.limit" class="mt-4">
                        <div class="h-2 overflow-hidden rounded-full bg-card">
                            <div class="h-full rounded-full bg-buttonPrimary" :style="{ width: `${Math.min(100, card.percent || 0)}%` }"></div>
                        </div>
                        <p class="mt-2 text-xs text-secondary">{{ formatNumber(card.limit) }} im freien/überwachten Bereich</p>
                    </div>
                    <p class="mt-3 text-sm leading-6 text-secondary">{{ card.hint }}</p>
                </article>
            </div>
        </section>

        <section class="grid gap-4 xl:grid-cols-[0.8fr_1.2fr]">
            <div class="space-y-3">
                <article
                    v-for="item in recommendations"
                    :key="item.title"
                    class="rounded-2xl border p-4"
                    :class="recommendationClass(item.level)"
                >
                    <p class="text-sm font-black text-primary">{{ item.title }}</p>
                    <p class="mt-2 text-sm leading-6 text-secondary">{{ item.body }}</p>
                </article>

                <article class="rounded-2xl border border-border bg-card p-4">
                    <p class="text-xs font-bold uppercase text-air-blue">Annahmen</p>
                    <div class="mt-3 space-y-2 text-sm text-secondary">
                        <p>USD → EUR: {{ dashboard.assumptions?.usd_to_eur }}</p>
                        <p>Eigene Routing-Infrastruktur: {{ formatCurrency(dashboard.assumptions?.self_hosted_monthly_eur) }} / Monat</p>
                        <p class="leading-6">{{ dashboard.assumptions?.note }}</p>
                    </div>
                </article>
            </div>

            <div class="space-y-4">
                <article v-for="comparison in comparisons" :key="comparison.label" class="rounded-2xl border border-border bg-card p-4 lg:p-5">
                    <div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
                        <div>
                            <p class="text-xs font-bold uppercase text-air-blue">{{ comparison.area }}</p>
                            <h2 class="mt-1 text-xl font-black text-primary">{{ comparison.label }}</h2>
                            <p class="mt-1 text-sm text-secondary">
                                Aktuell: {{ formatNumber(comparison.units) }} {{ comparison.unit_label }}
                            </p>
                        </div>
                        <div class="rounded-xl border border-border bg-inputBg px-3 py-2 text-sm">
                            <p class="text-xs font-bold uppercase text-secondary">Günstigster Plan</p>
                            <p class="font-black text-primary">{{ comparison.cheapest_plan?.label || '-' }}</p>
                            <p class="text-secondary">{{ formatCurrency(comparison.cheapest_plan?.estimated_cost_eur) }}</p>
                        </div>
                    </div>

                    <div v-if="comparison.break_even" class="mt-4 rounded-xl border border-air-orange/30 bg-air-orange/10 p-3 text-sm leading-6 text-secondary">
                        <span class="font-black text-air-orange">Wechselpunkt:</span>
                        {{ comparison.break_even.message }}
                        Zielkosten ca. {{ formatCurrency(comparison.break_even.target_cost_eur) }}.
                    </div>

                    <div class="mt-4 overflow-x-auto">
                        <table class="min-w-full text-left text-sm">
                            <thead class="text-xs uppercase text-secondary">
                                <tr>
                                    <th class="px-3 py-2">Anbieter</th>
                                    <th class="px-3 py-2">Jetzt</th>
                                    <th class="px-3 py-2">2x Nutzung</th>
                                    <th class="px-3 py-2">5x Nutzung</th>
                                    <th class="px-3 py-2">Hinweis</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-border">
                                <tr v-for="plan in comparison.plans" :key="plan.key" class="align-top">
                                    <td class="px-3 py-3">
                                        <p class="font-black text-primary">{{ plan.label }}</p>
                                        <p class="text-xs text-secondary">{{ plan.provider }}</p>
                                    </td>
                                    <td class="px-3 py-3 font-bold text-primary">{{ formatCurrency(plan.estimated_cost_eur) }}</td>
                                    <td class="px-3 py-3 text-secondary">{{ formatCurrency(plan.projected_2x_eur) }}</td>
                                    <td class="px-3 py-3 text-secondary">{{ formatCurrency(plan.projected_5x_eur) }}</td>
                                    <td class="max-w-xs px-3 py-3 text-secondary">
                                        <span v-if="!plan.available" class="mb-1 inline-flex rounded-full bg-danger/10 px-2 py-1 text-xs font-bold text-danger">
                                            Limit prüfen
                                        </span>
                                        <p class="leading-5">{{ plan.risk || (plan.free_units ? `${formatNumber(plan.free_units)} frei enthalten.` : 'Plan im aktuellen Volumen nutzbar.') }}</p>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </article>
            </div>
        </section>

        <section class="surface-card overflow-hidden">
            <div class="border-b border-border p-5">
                <h2 class="text-lg font-semibold text-primary">Rohdaten dieses Monats</h2>
                <p class="mt-1 text-sm text-secondary">
                    Hier siehst du, welche Provider wirklich genutzt wurden. KI-Features können später dieselbe Tabelle füllen.
                </p>
            </div>
            <div class="overflow-x-auto">
                <table class="min-w-full text-left text-sm">
                    <thead class="bg-bg text-xs uppercase text-secondary">
                        <tr>
                            <th class="px-5 py-3">Bereich</th>
                            <th class="px-5 py-3">Provider</th>
                            <th class="px-5 py-3">Service</th>
                            <th class="px-5 py-3">Operation</th>
                            <th class="px-5 py-3">Requests</th>
                            <th class="px-5 py-3">Nutzer</th>
                            <th class="px-5 py-3">Tokens</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-border">
                        <tr v-for="row in usageRows" :key="`${row.area}-${row.provider}-${row.service}-${row.operation}`">
                            <td class="px-5 py-4 font-bold text-primary">{{ row.area }}</td>
                            <td class="px-5 py-4 text-secondary">{{ row.provider }}</td>
                            <td class="px-5 py-4 text-secondary">{{ row.service }}</td>
                            <td class="px-5 py-4 text-secondary">{{ row.operation }}</td>
                            <td class="px-5 py-4 text-primary">{{ formatNumber(row.requests) }}</td>
                            <td class="px-5 py-4 text-secondary">{{ formatNumber(row.users) }}</td>
                            <td class="px-5 py-4 text-secondary">
                                {{ formatNumber(row.input_tokens + row.output_tokens) }}
                            </td>
                        </tr>
                        <tr v-if="!usageRows.length">
                            <td colspan="7" class="px-5 py-10 text-center text-secondary">
                                Noch keine Provider-Nutzung für diesen Monat gemessen.
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </section>
    </div>
</template>
