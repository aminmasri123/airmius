<script setup>
defineProps({
    centsToMajor: { type: Function, required: true },
    commerceSettingsForm: { type: Object, required: true },
    formatMoney: { type: Function, required: true },
    marketplaceCommissionForm: { type: Object, required: true },
    moneyInputAttrs: { type: Object, required: true },
    ossReport: { type: Array, default: () => [] },
    shippingRateForm: { type: Object, required: true },
    shippingRates: { type: Array, default: () => [] },
    taxRateForm: { type: Object, required: true },
    taxRates: { type: Array, default: () => [] },
    addMarketplaceCommissionRow: { type: Function, required: true },
    removeMarketplaceCommissionRow: { type: Function, required: true },
    storeShippingRate: { type: Function, required: true },
    storeTaxRate: { type: Function, required: true },
    updateCommerceSettings: { type: Function, required: true },
    updateMarketplaceCommissions: { type: Function, required: true },
    updateShippingRate: { type: Function, required: true },
    updateTaxRate: { type: Function, required: true },
})
</script>

<template>
    <div class="space-y-6">
        <section class="grid gap-6 xl:grid-cols-3">
            <article class="surface-card p-5">
                <div class="flex items-start justify-between gap-3">
                    <div>
                        <p class="text-xs font-semibold uppercase tracking-wide text-air-blue">Export</p>
                        <h2 class="mt-1 text-lg font-semibold text-primary">Steuerberater / DATEV-CSV</h2>
                        <p class="mt-1 text-sm text-secondary">Bestellungen, Steuerland, Rechnungsnummern, Versandstatus und Betraege als CSV.</p>
                    </div>
                    <a :href="route('admin.commerce.export.csv')" class="rounded-lg bg-buttonPrimary px-4 py-2 text-sm font-semibold text-buttonTextPrimary">CSV</a>
                </div>
            </article>

            <article class="surface-card p-5 xl:col-span-2">
                <p class="text-xs font-semibold uppercase tracking-wide text-air-blue">OSS</p>
                <h2 class="mt-1 text-lg font-semibold text-primary">EU-Auswertung nach Land</h2>
                <div class="mt-3 grid gap-3 md:grid-cols-3">
                    <div v-for="row in ossReport" :key="row.country" class="rounded-lg border border-border bg-bg p-3">
                        <p class="text-xs uppercase text-secondary">{{ row.country }} / {{ row.orders_count }} Orders</p>
                        <p class="mt-1 font-semibold text-primary">{{ formatMoney(row.gross_cents) }}</p>
                        <p class="text-xs text-secondary">Steuer {{ formatMoney(row.tax_cents) }}</p>
                    </div>
                    <p v-if="!ossReport.length" class="text-sm text-secondary">Noch keine OSS-Daten.</p>
                </div>
            </article>
        </section>

        <section class="grid gap-6 xl:grid-cols-2">
            <article class="surface-card p-5 xl:col-span-2">
                <div class="flex flex-col gap-1">
                    <p class="text-xs font-semibold uppercase tracking-wide text-air-blue">EU-Konformitaet</p>
                    <h2 class="text-lg font-semibold text-primary">Commerce-Steuerlogik</h2>
                    <p class="text-sm text-secondary">Diese Einstellungen steuern Firmenland, OSS-Verhalten, Export und Reverse-Charge.</p>
                </div>
                <form class="mt-4 grid gap-3 md:grid-cols-5" @submit.prevent="updateCommerceSettings">
                    <input v-model="commerceSettingsForm.company_country" maxlength="2" class="rounded-lg border-border bg-inputBg text-sm uppercase text-primary" placeholder="Firmensitz, z. B. DE">
                    <input v-model="commerceSettingsForm.company_currency" maxlength="3" class="rounded-lg border-border bg-inputBg text-sm uppercase text-primary" placeholder="EUR">
                    <select v-model="commerceSettingsForm.export_vat_mode" class="rounded-lg border-border bg-inputBg text-sm text-primary">
                        <option value="zero">Export ausserhalb EU: 0%</option>
                        <option value="domestic">Export: Inlandssatz</option>
                    </select>
                    <label class="flex items-center gap-2 rounded-lg border border-border bg-card px-3 py-2 text-sm text-primary">
                        <input v-model="commerceSettingsForm.enable_oss" type="checkbox" class="rounded border-border bg-inputBg">
                        OSS aktiv
                    </label>
                    <label class="flex items-center gap-2 rounded-lg border border-border bg-card px-3 py-2 text-sm text-primary">
                        <input v-model="commerceSettingsForm.reverse_charge_enabled" type="checkbox" class="rounded border-border bg-inputBg">
                        Reverse-Charge
                    </label>
                    <button class="rounded-lg bg-buttonPrimary px-4 py-2 text-sm font-semibold text-buttonTextPrimary md:col-span-5">Steuerlogik speichern</button>
                </form>
            </article>

            <article class="surface-card p-5 xl:col-span-2">
                <div class="flex flex-col gap-1">
                    <p class="text-xs font-semibold uppercase tracking-wide text-air-blue">Marketplace</p>
                    <h2 class="text-lg font-semibold text-primary">Provisionen für externe VerKäufer</h2>
                    <p class="text-sm text-secondary">Diese Sätze gelten nur für externe Shop-VerKäufer. Interne Airmius-Angebote und interne Services laufen ohne Marketplace-Provision.</p>
                </div>
                <form class="mt-4 space-y-4" @submit.prevent="updateMarketplaceCommissions">
                    <label class="grid gap-2 rounded-lg border border-border bg-card p-4 md:grid-cols-[1fr_8rem] md:items-center">
                        <span>
                            <span class="block text-sm font-semibold text-primary">Standard-Provision</span>
                            <span class="block text-xs text-secondary">Greift nur, wenn für eine neue Kategorie noch kein eigener Satz hinterlegt ist.</span>
                        </span>
                        <span class="flex items-center gap-2">
                            <input v-model="marketplaceCommissionForm.default_commission_percent" type="number" min="0" max="100" class="w-full rounded-lg border-border bg-inputBg text-sm text-primary">
                            <span class="text-sm text-secondary">%</span>
                        </span>
                    </label>

                    <div class="grid gap-3 md:grid-cols-2">
                        <label
                            v-for="(row, index) in marketplaceCommissionForm.commissions"
                            :key="row.category"
                            class="rounded-lg border border-border bg-card p-4"
                        >
                            <span class="grid gap-2 sm:grid-cols-[1fr_1fr_auto]">
                                <input v-model="marketplaceCommissionForm.commissions[index].label" class="rounded-lg border-border bg-inputBg text-sm font-semibold text-primary" placeholder="Anzeigename, z. B. Fußballschuhe">
                                <input v-model="marketplaceCommissionForm.commissions[index].category" class="rounded-lg border-border bg-inputBg text-sm text-primary" placeholder="slug, z. B. football_shoes">
                                <button type="button" class="rounded-lg border border-danger/40 px-3 py-2 text-xs font-semibold text-danger" @click="removeMarketplaceCommissionRow(index)">
                                    Entfernen
                                </button>
                            </span>
                            <span class="mt-3 flex items-center gap-2">
                                <input v-model="marketplaceCommissionForm.commissions[index].commission_percent" type="number" min="0" max="100" class="w-full rounded-lg border-border bg-inputBg text-sm text-primary">
                                <span class="text-sm text-secondary">%</span>
                            </span>
                        </label>
                    </div>

                    <div class="flex flex-wrap gap-3">
                        <button type="button" class="rounded-lg border border-border px-4 py-2 text-sm font-semibold text-primary" @click="addMarketplaceCommissionRow">
                            Kategorie hinzufügen
                        </button>
                        <button class="rounded-lg bg-buttonPrimary px-4 py-2 text-sm font-semibold text-buttonTextPrimary" :disabled="marketplaceCommissionForm.processing">
                            Provisionen speichern
                        </button>
                    </div>
                </form>
            </article>

            <article class="surface-card p-5">
                <div class="flex flex-col gap-1">
                    <p class="text-xs font-semibold uppercase tracking-wide text-air-blue">Checkout</p>
                    <h2 class="text-lg font-semibold text-primary">Steuern verwalten</h2>
                    <p class="text-sm text-secondary">Der Checkout wählt den passenden Satz über Lieferland und optional Region.</p>
                </div>
                <form class="mt-4 grid gap-3 md:grid-cols-2" @submit.prevent="storeTaxRate">
                    <input v-model="taxRateForm.name" class="rounded-lg border-border bg-inputBg text-sm text-primary" placeholder="Name">
                    <input v-model="taxRateForm.country_code" maxlength="2" class="rounded-lg border-border bg-inputBg text-sm uppercase text-primary" placeholder="DE">
                    <input v-model="taxRateForm.region" class="rounded-lg border-border bg-inputBg text-sm text-primary" placeholder="Region optional">
                    <select v-model="taxRateForm.tax_class" class="rounded-lg border-border bg-inputBg text-sm text-primary">
                        <option value="standard">Standard</option>
                        <option value="reduced">Ermaessigt</option>
                        <option value="zero">Nullsatz</option>
                    </select>
                    <input v-model="taxRateForm.tax_label" class="rounded-lg border-border bg-inputBg text-sm text-primary" placeholder="MwSt.">
                    <input v-model="taxRateForm.rate_percent" type="number" min="0" max="99.99" step="0.01" class="rounded-lg border-border bg-inputBg text-sm text-primary" placeholder="19">
                    <input v-model="taxRateForm.currency" maxlength="3" class="rounded-lg border-border bg-inputBg text-sm uppercase text-primary" placeholder="EUR">
                    <input v-model="taxRateForm.priority" type="number" min="1" class="rounded-lg border-border bg-inputBg text-sm text-primary" placeholder="Priorität">
                    <div class="flex flex-wrap items-center gap-4 text-sm text-primary">
                        <label class="flex items-center gap-2">
                            <input v-model="taxRateForm.is_default" type="checkbox" class="rounded border-border bg-inputBg">
                            Standard
                        </label>
                        <label class="flex items-center gap-2">
                            <input v-model="taxRateForm.is_active" type="checkbox" class="rounded border-border bg-inputBg">
                            Aktiv
                        </label>
                    </div>
                    <button class="rounded-lg bg-buttonPrimary px-4 py-2 text-sm font-semibold text-buttonTextPrimary md:col-span-2">Steuersatz speichern</button>
                </form>

                <div class="mt-5 space-y-3">
                    <div v-for="rate in taxRates" :key="rate.id" class="grid gap-2 rounded-lg border border-border bg-card p-3 md:grid-cols-[1fr_5rem_7rem_5rem_6rem_6rem_auto] md:items-center">
                        <input v-model="rate.name" class="rounded-lg border-border bg-inputBg text-sm text-primary">
                        <input v-model="rate.country_code" maxlength="2" class="rounded-lg border-border bg-inputBg text-sm uppercase text-primary">
                        <select v-model="rate.tax_class" class="rounded-lg border-border bg-inputBg text-sm text-primary">
                            <option value="standard">Standard</option>
                            <option value="reduced">Ermaessigt</option>
                            <option value="zero">Nullsatz</option>
                        </select>
                        <input v-model="rate.tax_label" class="rounded-lg border-border bg-inputBg text-sm text-primary">
                        <input v-model="rate.rate_percent" type="number" min="0" step="0.01" class="rounded-lg border-border bg-inputBg text-sm text-primary">
                        <label class="flex items-center gap-2 text-sm text-primary">
                            <input v-model="rate.is_active" type="checkbox" class="rounded border-border bg-inputBg">
                            Aktiv
                        </label>
                        <button class="rounded-lg border border-border px-3 py-2 text-xs font-semibold text-primary" @click="updateTaxRate(rate)">Speichern</button>
                    </div>
                    <p v-if="!taxRates.length" class="text-sm text-secondary">Noch keine Steuersätze angelegt.</p>
                </div>
            </article>

            <article class="surface-card p-5">
                <div class="flex flex-col gap-1">
                    <p class="text-xs font-semibold uppercase tracking-wide text-air-blue">Checkout</p>
                    <h2 class="text-lg font-semibold text-primary">Versandkosten verwalten</h2>
                    <p class="text-sm text-secondary">Regeln können nach Ursprungslager, Lieferland und PLZ-Prefix greifen, inklusive kostenfrei ab Warenwert.</p>
                </div>
                <form class="mt-4 grid gap-3 md:grid-cols-2" @submit.prevent="storeShippingRate">
                    <input v-model="shippingRateForm.name" class="rounded-lg border-border bg-inputBg text-sm text-primary" placeholder="Name">
                    <input v-model="shippingRateForm.origin_country_code" maxlength="2" class="rounded-lg border-border bg-inputBg text-sm uppercase text-primary" placeholder="Von Land, z. B. DE">
                    <input v-model="shippingRateForm.country_code" maxlength="2" class="rounded-lg border-border bg-inputBg text-sm uppercase text-primary" placeholder="DE oder leer">
                    <input v-model="shippingRateForm.postal_code_prefix" class="rounded-lg border-border bg-inputBg text-sm text-primary" placeholder="PLZ-Prefix optional">
                    <input v-model="shippingRateForm.amount_cents" v-bind="moneyInputAttrs" class="rounded-lg border-border bg-inputBg text-sm text-primary" placeholder="Versand in EUR">
                    <input v-model="shippingRateForm.free_from_cents" v-bind="moneyInputAttrs" class="rounded-lg border-border bg-inputBg text-sm text-primary" placeholder="Kostenfrei ab EUR">
                    <input v-model="shippingRateForm.currency" maxlength="3" class="rounded-lg border-border bg-inputBg text-sm uppercase text-primary" placeholder="EUR">
                    <input v-model="shippingRateForm.priority" type="number" min="1" class="rounded-lg border-border bg-inputBg text-sm text-primary" placeholder="Priorität">
                    <label class="flex items-center gap-2 text-sm text-primary">
                        <input v-model="shippingRateForm.is_active" type="checkbox" class="rounded border-border bg-inputBg">
                        Aktiv
                    </label>
                    <button class="rounded-lg bg-buttonPrimary px-4 py-2 text-sm font-semibold text-buttonTextPrimary md:col-span-2">Versandregel speichern</button>
                </form>

                <div class="mt-5 space-y-3">
                    <div v-for="rate in shippingRates" :key="rate.id" class="grid gap-2 rounded-lg border border-border bg-card p-3 md:grid-cols-[1fr_5rem_5rem_6rem_6rem_6rem_auto] md:items-center">
                        <input v-model="rate.name" class="rounded-lg border-border bg-inputBg text-sm text-primary">
                        <input v-model="rate.origin_country_code" maxlength="2" class="rounded-lg border-border bg-inputBg text-sm uppercase text-primary" placeholder="Von">
                        <input v-model="rate.country_code" maxlength="2" class="rounded-lg border-border bg-inputBg text-sm uppercase text-primary" placeholder="Alle">
                        <input v-model="rate.postal_code_prefix" class="rounded-lg border-border bg-inputBg text-sm text-primary" placeholder="PLZ">
                        <input :value="typeof rate.amount_cents === 'string' ? rate.amount_cents : centsToMajor(rate.amount_cents)" v-bind="moneyInputAttrs" class="rounded-lg border-border bg-inputBg text-sm text-primary" @input="rate.amount_cents = $event.target.value">
                        <label class="flex items-center gap-2 text-sm text-primary">
                            <input v-model="rate.is_active" type="checkbox" class="rounded border-border bg-inputBg">
                            Aktiv
                        </label>
                        <button class="rounded-lg border border-border px-3 py-2 text-xs font-semibold text-primary" @click="updateShippingRate(rate)">Speichern</button>
                    </div>
                    <p v-if="!shippingRates.length" class="text-sm text-secondary">Noch keine Versandregeln angelegt.</p>
                </div>
            </article>
        </section>
    </div>
</template>





