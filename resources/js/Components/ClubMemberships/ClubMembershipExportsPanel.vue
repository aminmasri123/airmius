<script setup>
defineProps({
    selectedClub: { type: Object, required: true },
    capabilities: { type: Object, default: () => ({}) },
    sepaSettingsFor: { type: Function, required: true },
    saveSepaSettings: { type: Function, required: true },
    datevSettingsFor: { type: Function, required: true },
    datevExportFor: { type: Function, required: true },
    saveDatevSettings: { type: Function, required: true },
    datevExportUrl: { type: String, required: true },
})
</script>

<template>
    <div class="space-y-6">
        <section class="surface-card p-5">
            <div class="flex flex-col gap-4 xl:flex-row xl:items-start xl:justify-between">
                <div class="max-w-2xl">
                    <h2 class="text-lg font-semibold text-primary">SEPA-Lastschrift</h2>
                    <p class="mt-1 text-sm text-secondary">
                        Hinterlege die Vereinsdaten und exportiere offene Rechnungen mit aktivem Mandat als SEPA-XML.
                    </p>
                </div>

                <a
                    :href="route('auth.club-memberships.sepa-export', selectedClub.id)"
                    class="rounded-lg bg-buttonPrimary px-4 py-2 text-sm font-semibold text-buttonTextPrimary disabled:cursor-not-allowed disabled:opacity-50"
                    :class="{ 'pointer-events-none opacity-50': capabilities.sepa_export === false }"
                    :title="capabilities.sepa_export === false ? 'SEPA-Export ist ab Pro verfügbar' : ''"
                >
                    SEPA-XML exportieren
                </a>
            </div>

            <form class="mt-4 grid gap-3 md:grid-cols-[1fr_1fr_1fr_1fr_auto]" @submit.prevent="saveSepaSettings">
                <div>
                    <label class="text-xs font-semibold uppercase text-secondary">Gläubiger-ID</label>
                    <input v-model="sepaSettingsFor(selectedClub).sepa_creditor_id" class="mt-1 w-full rounded-lg border border-border bg-inputBg px-3 py-2 text-sm text-primary" placeholder="DE98ZZZ09999999999">
                </div>
                <div>
                    <label class="text-xs font-semibold uppercase text-secondary">Kontoinhaber</label>
                    <input v-model="sepaSettingsFor(selectedClub).sepa_account_holder" class="mt-1 w-full rounded-lg border border-border bg-inputBg px-3 py-2 text-sm text-primary" placeholder="Name laut Bankkonto">
                </div>
                <div>
                    <label class="text-xs font-semibold uppercase text-secondary">Vereins-IBAN</label>
                    <input v-model="sepaSettingsFor(selectedClub).sepa_iban" class="mt-1 w-full rounded-lg border border-border bg-inputBg px-3 py-2 text-sm text-primary" placeholder="DE...">
                </div>
                <div>
                    <label class="text-xs font-semibold uppercase text-secondary">BIC optional</label>
                    <input v-model="sepaSettingsFor(selectedClub).sepa_bic" class="mt-1 w-full rounded-lg border border-border bg-inputBg px-3 py-2 text-sm text-primary" placeholder="GENODE...">
                </div>
                <div class="flex items-end">
                    <button
                        class="w-full rounded-lg border border-border px-4 py-2 text-sm font-semibold text-primary disabled:cursor-not-allowed disabled:opacity-50"
                        :disabled="capabilities.sepa_export === false"
                        :title="capabilities.sepa_export === false ? 'SEPA-Export ist ab Pro verfügbar' : ''"
                    >
                        Speichern
                    </button>
                </div>
            </form>
        </section>

        <section class="surface-card p-5">
            <div class="flex flex-col gap-4 xl:flex-row xl:items-start xl:justify-between">
                <div class="max-w-2xl">
                    <h2 class="text-lg font-semibold text-primary">DATEV / SKR42</h2>
                    <p class="mt-1 text-sm text-secondary">
                        Exportiere bezahlte Mitgliedsbeiträge als CSV-Buchungsstapel. Konten bitte mit Steuerberatung abstimmen.
                    </p>
                </div>

                <a
                    :href="datevExportUrl"
                    class="rounded-lg bg-buttonPrimary px-4 py-2 text-sm font-semibold text-buttonTextPrimary"
                    :class="{ 'pointer-events-none opacity-50': capabilities.datev_export === false }"
                    :title="capabilities.datev_export === false ? 'DATEV-Export ist ab Pro verfügbar' : ''"
                >
                    DATEV-CSV exportieren
                </a>
            </div>

            <div class="mt-4 grid gap-4 xl:grid-cols-[1fr_1fr]">
                <form class="grid gap-3 md:grid-cols-2" @submit.prevent="saveDatevSettings">
                    <div>
                        <label class="text-xs font-semibold uppercase text-secondary">Beraternummer</label>
                        <input v-model="datevSettingsFor(selectedClub).datev_consultant_number" class="mt-1 w-full rounded-lg border border-border bg-inputBg px-3 py-2 text-sm text-primary" placeholder="Optional">
                    </div>
                    <div>
                        <label class="text-xs font-semibold uppercase text-secondary">Mandantennummer</label>
                        <input v-model="datevSettingsFor(selectedClub).datev_client_number" class="mt-1 w-full rounded-lg border border-border bg-inputBg px-3 py-2 text-sm text-primary" placeholder="Optional">
                    </div>
                    <div>
                        <label class="text-xs font-semibold uppercase text-secondary">Erlöskonto SKR42</label>
                        <input v-model="datevSettingsFor(selectedClub).datev_revenue_account" class="mt-1 w-full rounded-lg border border-border bg-inputBg px-3 py-2 text-sm text-primary" placeholder="z. B. 2110">
                    </div>
                    <div>
                        <label class="text-xs font-semibold uppercase text-secondary">Bankkonto SKR42</label>
                        <input v-model="datevSettingsFor(selectedClub).datev_bank_account" class="mt-1 w-full rounded-lg border border-border bg-inputBg px-3 py-2 text-sm text-primary" placeholder="z. B. 1200">
                    </div>
                    <div class="md:col-span-2">
                        <button
                            class="rounded-lg border border-border px-4 py-2 text-sm font-semibold text-primary disabled:cursor-not-allowed disabled:opacity-50"
                            :disabled="capabilities.datev_export === false"
                            :title="capabilities.datev_export === false ? 'DATEV-Export ist ab Pro verfügbar' : ''"
                        >
                            DATEV-Einstellungen speichern
                        </button>
                    </div>
                </form>

                <div class="grid gap-3 rounded-lg border border-border bg-bg p-4 md:grid-cols-2">
                    <div>
                        <label class="text-xs font-semibold uppercase text-secondary">Von</label>
                        <input v-model="datevExportFor(selectedClub).from" type="date" class="mt-1 w-full rounded-lg border border-border bg-inputBg px-3 py-2 text-sm text-primary">
                    </div>
                    <div>
                        <label class="text-xs font-semibold uppercase text-secondary">Bis</label>
                        <input v-model="datevExportFor(selectedClub).to" type="date" class="mt-1 w-full rounded-lg border border-border bg-inputBg px-3 py-2 text-sm text-primary">
                    </div>
                    <p class="text-xs text-secondary md:col-span-2">
                        Exportiert werden bezahlte Zahlungen im Zeitraum. Der CSV-Aufbau ist für die Beta bewusst schlicht und prüfbar gehalten.
                    </p>
                </div>
            </div>
        </section>
    </div>
</template>

