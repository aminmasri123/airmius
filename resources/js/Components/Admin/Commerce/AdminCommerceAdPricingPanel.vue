<script setup>
defineProps({
    commerceSettingsForm: {
        type: Object,
        required: true,
    },
    moneyInputAttrs: {
        type: Object,
        required: true,
    },
    adPricingCards: {
        type: Array,
        default: () => [],
    },
    formatMoney: {
        type: Function,
        required: true,
    },
    majorToCents: {
        type: Function,
        required: true,
    },
    updateCommerceSettings: {
        type: Function,
        required: true,
    },
})
</script>

<template>
    <section class="grid gap-6 xl:grid-cols-3">
        <article class="surface-card p-5 xl:col-span-2">
            <div class="flex flex-col gap-1">
                <p class="text-xs font-semibold uppercase tracking-wide text-air-blue">Ads Abrechnung</p>
                <h2 class="text-lg font-semibold text-primary">Kosten und Preise verwalten</h2>
                <p class="text-sm text-secondary">Diese Werte steuern, wie Kampagnenbudget für Impressionen, Klicks, Leads und Sales verbraucht wird.</p>
            </div>

            <form class="mt-5 grid gap-4 md:grid-cols-2" @submit.prevent="updateCommerceSettings">
                <label class="rounded-lg border border-border bg-card p-4">
                    <span class="text-xs font-semibold uppercase tracking-wide text-secondary">CPM</span>
                    <span class="mt-1 block text-sm font-semibold text-primary">Preis pro 1.000 Impressionen</span>
                    <div class="mt-3 flex items-center gap-2">
                        <input v-model="commerceSettingsForm.ads_cpm_cents" v-bind="moneyInputAttrs" class="w-full rounded-lg border-border bg-inputBg text-sm text-primary" placeholder="5,00">
                        <span class="shrink-0 text-sm text-secondary">EUR</span>
                    </div>
                    <span class="mt-2 block text-xs text-secondary">{{ commerceSettingsForm.ads_cpm_cents || '0,00' }} EUR pro 1.000 Views</span>
                </label>

                <label class="rounded-lg border border-border bg-card p-4">
                    <span class="text-xs font-semibold uppercase tracking-wide text-secondary">CPC</span>
                    <span class="mt-1 block text-sm font-semibold text-primary">Preis pro Klick</span>
                    <div class="mt-3 flex items-center gap-2">
                        <input v-model="commerceSettingsForm.ads_cpc_cents" v-bind="moneyInputAttrs" class="w-full rounded-lg border-border bg-inputBg text-sm text-primary" placeholder="0,30">
                        <span class="shrink-0 text-sm text-secondary">EUR</span>
                    </div>
                    <span class="mt-2 block text-xs text-secondary">{{ commerceSettingsForm.ads_cpc_cents || '0,00' }} EUR pro Klick</span>
                </label>

                <label class="rounded-lg border border-border bg-card p-4">
                    <span class="text-xs font-semibold uppercase tracking-wide text-secondary">CPL</span>
                    <span class="mt-1 block text-sm font-semibold text-primary">Preis pro Lead</span>
                    <div class="mt-3 flex items-center gap-2">
                        <input v-model="commerceSettingsForm.ads_cpl_cents" v-bind="moneyInputAttrs" class="w-full rounded-lg border-border bg-inputBg text-sm text-primary" placeholder="2,00">
                        <span class="shrink-0 text-sm text-secondary">EUR</span>
                    </div>
                    <span class="mt-2 block text-xs text-secondary">{{ commerceSettingsForm.ads_cpl_cents || '0,00' }} EUR pro Lead</span>
                </label>

                <label class="rounded-lg border border-border bg-card p-4">
                    <span class="text-xs font-semibold uppercase tracking-wide text-secondary">CPA</span>
                    <span class="mt-1 block text-sm font-semibold text-primary">Provision pro Verkauf</span>
                    <div class="mt-3 flex items-center gap-2">
                        <input v-model="commerceSettingsForm.ads_cpa_percent" type="number" min="0" max="100" class="w-full rounded-lg border-border bg-inputBg text-sm text-primary" placeholder="10">
                        <span class="shrink-0 text-sm text-secondary">%</span>
                    </div>
                    <span class="mt-2 block text-xs text-secondary">{{ commerceSettingsForm.ads_cpa_percent || 0 }} % vom Warenwert</span>
                </label>

                <label class="rounded-lg border border-border bg-card p-4 md:col-span-2">
                    <span class="text-xs font-semibold uppercase tracking-wide text-secondary">Mindestbudget</span>
                    <span class="mt-1 block text-sm font-semibold text-primary">Kleinstes Kampagnenbudget für Nutzer</span>
                    <div class="mt-3 flex items-center gap-2">
                        <input v-model="commerceSettingsForm.ads_min_budget_cents" v-bind="moneyInputAttrs" class="w-full rounded-lg border-border bg-inputBg text-sm text-primary" placeholder="10,00">
                        <span class="shrink-0 text-sm text-secondary">EUR</span>
                    </div>
                    <span class="mt-2 block text-xs text-secondary">Aktuell: {{ formatMoney(majorToCents(commerceSettingsForm.ads_min_budget_cents)) }}</span>
                </label>

                <label class="rounded-lg border border-border bg-card p-4 md:col-span-2">
                    <span class="text-xs font-semibold uppercase tracking-wide text-secondary">Frequency Capping</span>
                    <span class="mt-1 block text-sm font-semibold text-primary">Max. Impressionen pro Kampagne und Tag</span>
                    <div class="mt-3 flex items-center gap-2">
                        <input v-model="commerceSettingsForm.ads_frequency_cap_per_day" type="number" min="0" max="100" class="w-full rounded-lg border-border bg-inputBg text-sm text-primary" placeholder="3">
                        <span class="shrink-0 text-sm text-secondary">pro User/Session</span>
                    </div>
                    <span class="mt-2 block text-xs text-secondary">0 deaktiviert das Cap. Gilt auch für interne priorisierte Ads.</span>
                </label>

                <div class="rounded-lg border border-border bg-card p-4 md:col-span-2">
                    <span class="text-xs font-semibold uppercase tracking-wide text-secondary">Placement Caps</span>
                    <span class="mt-1 block text-sm font-semibold text-primary">Unterschiedliche Limits je Fläche</span>
                    <div class="mt-3 grid gap-3 sm:grid-cols-2">
                        <label class="text-xs font-semibold uppercase text-secondary">
                            Feed
                            <input v-model="commerceSettingsForm.ads_frequency_cap_feed" type="number" min="0" max="100" class="mt-1 w-full rounded-lg border-border bg-inputBg text-sm text-primary">
                        </label>
                        <label class="text-xs font-semibold uppercase text-secondary">
                            Sidebar
                            <input v-model="commerceSettingsForm.ads_frequency_cap_sidebar" type="number" min="0" max="100" class="mt-1 w-full rounded-lg border-border bg-inputBg text-sm text-primary">
                        </label>
                        <label class="text-xs font-semibold uppercase text-secondary">
                            Marketplace Karte
                            <input v-model="commerceSettingsForm.ads_frequency_cap_marketplace_card" type="number" min="0" max="100" class="mt-1 w-full rounded-lg border-border bg-inputBg text-sm text-primary">
                        </label>
                        <label class="text-xs font-semibold uppercase text-secondary">
                            Sponsor-Bereich
                            <input v-model="commerceSettingsForm.ads_frequency_cap_sponsor_section" type="number" min="0" max="100" class="mt-1 w-full rounded-lg border-border bg-inputBg text-sm text-primary">
                        </label>
                    </div>
                </div>

                <button class="rounded-lg bg-buttonPrimary px-4 py-2 text-sm font-semibold text-buttonTextPrimary md:col-span-2" :disabled="commerceSettingsForm.processing">
                    Ads-Preise speichern
                </button>
            </form>
        </article>

        <aside class="surface-card p-5">
            <p class="text-xs font-semibold uppercase tracking-wide text-air-blue">Kontrolle</p>
            <h2 class="mt-1 text-lg font-semibold text-primary">Berechnungsvorschau</h2>
            <div class="mt-4 space-y-3">
                <div v-for="price in adPricingCards" :key="price.key" class="rounded-lg border border-border bg-bg p-3">
                    <div class="flex items-start justify-between gap-3">
                        <div>
                            <p class="text-sm font-semibold text-primary">{{ price.label }} - {{ price.title }}</p>
                            <p class="mt-1 text-xs text-secondary">{{ price.formula }}</p>
                        </div>
                        <span class="shrink-0 rounded-md border border-border px-2 py-1 text-xs font-semibold text-primary">{{ price.value || 0 }} {{ price.suffix }}</span>
                    </div>
                    <p class="mt-2 text-xs text-air-blue">{{ price.example }}</p>
                </div>
            </div>
        </aside>
    </section>
</template>

