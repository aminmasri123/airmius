<script setup>
defineProps({
    selectedClub: { type: Object, required: true },
    members: { type: Array, default: () => [] },
    activeMembersCount: { type: Number, default: 0 },
    openInvoiceTotal: { type: [Number, String], default: 0 },
    openInvoices: { type: Array, default: () => [] },
    sepaReadyMembersCount: { type: Number, default: 0 },
    recurringContributionTotal: { type: [Number, String], default: 0 },
    memberUsagePercent: { type: Number, default: 0 },
    formatMoney: { type: Function, required: true },
    capabilities: { type: Object, default: () => ({}) },
    canOpenEmailMembers: { type: Boolean, default: false },
    tabs: { type: Array, default: () => [] },
    activeTab: { type: String, required: true },
    selectTab: { type: Function, required: true },
    openImportModal: { type: Function, required: true },
    openAddMemberModal: { type: Function, required: true },
})
</script>

<template>
    <div class="space-y-6">
        <section class="grid gap-3 sm:grid-cols-2 xl:grid-cols-4">
            <div class="surface-card p-4">
                <div class="text-xs font-semibold uppercase text-secondary">Aktive Mitglieder</div>
                <div class="mt-2 text-2xl font-bold text-primary">{{ activeMembersCount }}</div>
                <div class="mt-1 text-xs text-secondary">von {{ members.length }} verknüpften Personen</div>
            </div>
            <div class="surface-card p-4">
                <div class="text-xs font-semibold uppercase text-secondary">Offen</div>
                <div class="mt-2 text-2xl font-bold text-primary">{{ formatMoney(openInvoiceTotal) }}</div>
                <div class="mt-1 text-xs text-secondary">{{ openInvoices.length }} offene Rechnung(en)</div>
            </div>
            <div class="surface-card p-4">
                <div class="text-xs font-semibold uppercase text-secondary">SEPA bereit</div>
                <div class="mt-2 text-2xl font-bold text-primary">{{ sepaReadyMembersCount }}</div>
                <div class="mt-1 text-xs text-secondary">Mandate mit IBAN und Referenz</div>
            </div>
            <div class="surface-card p-4">
                <div class="text-xs font-semibold uppercase text-secondary">Wiederkehrende Beiträge</div>
                <div class="mt-2 text-2xl font-bold text-primary">{{ formatMoney(recurringContributionTotal) }}</div>
                <div class="mt-1 text-xs text-secondary">Summe aktiver Beitragssätze</div>
            </div>
        </section>

        <section class="surface-card overflow-hidden">
            <div class="grid gap-4 border-b border-border p-5 xl:grid-cols-[minmax(0,1fr)_auto] xl:items-center">
                <div>
                    <p class="text-xs font-semibold uppercase text-secondary">Aktueller Vereinsplan</p>
                    <h2 class="mt-1 text-xl font-semibold text-primary">{{ selectedClub.subscription?.plan?.name || 'Free' }}</h2>
                    <div class="mt-3 h-2 max-w-xl overflow-hidden rounded-full bg-inputBg">
                        <div class="h-full rounded-full bg-buttonPrimary" :style="{ width: `${memberUsagePercent}%` }"></div>
                    </div>
                    <p class="mt-2 text-xs text-secondary">
                        Mitglieder: {{ selectedClub.subscription?.member_usage || members.length }}
                        / {{ selectedClub.subscription?.member_limit || 'unbegrenzt' }}
                    </p>
                </div>

                <div class="flex flex-wrap gap-2 xl:justify-end">
                    <a
                        :href="route('auth.club-memberships.import-template')"
                        class="rounded-lg border border-border px-4 py-2 text-sm font-semibold text-primary hover:bg-inputBg"
                    >
                        Excel-Vorlage
                    </a>
                    <button
                        type="button"
                        class="rounded-lg border border-border px-4 py-2 text-sm font-semibold text-primary hover:bg-inputBg disabled:cursor-not-allowed disabled:opacity-50"
                        :disabled="capabilities.member_import === false"
                        :title="capabilities.member_import === false ? 'Import ist ab Starter verfügbar' : ''"
                        @click="openImportModal"
                    >
                        Importieren
                    </button>
                    <button
                        type="button"
                        class="rounded-lg bg-buttonPrimary px-4 py-2 text-sm font-semibold text-buttonTextPrimary disabled:cursor-not-allowed disabled:opacity-50"
                        :disabled="!canOpenEmailMembers"
                        :title="!canOpenEmailMembers ? 'Externe Mitglieder sind ab Starter verfügbar' : ''"
                        @click="openAddMemberModal"
                    >
                        Mitglied hinzufügen
                    </button>
                </div>
            </div>

            <div class="flex gap-2 overflow-x-auto p-3">
                <button
                    v-for="tab in tabs"
                    :key="tab.key"
                    type="button"
                    class="inline-flex shrink-0 items-center gap-2 rounded-lg border px-3 py-2 text-sm font-semibold transition"
                    :class="activeTab === tab.key
                        ? 'border-buttonPrimary bg-buttonPrimary text-buttonTextPrimary'
                        : 'border-border bg-card text-secondary hover:bg-inputBg hover:text-primary'"
                    @click="selectTab(tab.key)"
                >
                    <i :class="tab.icon"></i>
                    <span>{{ tab.label }}</span>
                    <span class="rounded bg-black/10 px-1.5 py-0.5 text-xs">{{ tab.count }}</span>
                </button>
            </div>
        </section>
    </div>
</template>

