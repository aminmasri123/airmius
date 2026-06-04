<script setup>
import { Link } from '@inertiajs/vue3'

defineProps({
    memberSearch: { type: String, default: '' },
    memberStatusFilter: { type: String, default: 'all' },
    memberEndFilter: { type: String, default: 'all' },
    membershipStatuses: { type: Array, default: () => [] },
    endingSoonMembersCount: { type: Number, default: 0 },
    filteredMembers: { type: Array, default: () => [] },
    editingMemberId: { type: [Number, String], default: null },
    invoiceMemberId: { type: [Number, String], default: null },
    capabilities: { type: Object, default: () => ({}) },
    clubRoleOptions: { type: Array, default: () => [] },
    membershipTypes: { type: Array, default: () => [] },
    contributionIntervals: { type: Array, default: () => [] },
    invoiceForm: { type: Object, required: true },
    statusLabel: { type: Function, required: true },
    statusClass: { type: Function, required: true },
    formFor: { type: Function, required: true },
    endLabel: { type: Function, required: true },
    formatMoney: { type: Function, required: true },
    intervalLabel: { type: Function, required: true },
    formatDate: { type: Function, required: true },
    setMemberSearch: { type: Function, required: true },
    setMemberStatusFilter: { type: Function, required: true },
    setMemberEndFilter: { type: Function, required: true },
    toggleEditingMember: { type: Function, required: true },
    openInvoice: { type: Function, required: true },
    removeMember: { type: Function, required: true },
    saveMember: { type: Function, required: true },
    generateMemberNumber: { type: Function, required: true },
    createInvoice: { type: Function, required: true },
})
</script>

<template>
    <section class="surface-card overflow-hidden">
        <div class="border-b border-border p-5">
            <div class="flex flex-col gap-4 xl:flex-row xl:items-center xl:justify-between">
                <div>
                    <h2 class="text-lg font-semibold text-primary">Mitglieder & Beitragsdaten</h2>
                    <p class="mt-1 text-sm text-secondary">
                        Teammitglieder können als echte Vereinsmitglieder oder als reine Teamteilnehmer markiert werden.
                    </p>
                </div>

                <div class="grid gap-2 sm:grid-cols-[minmax(13rem,1fr)_12rem_14rem]">
                    <label class="flex items-center gap-2 rounded-lg border border-border bg-inputBg px-3 py-2">
                        <i class="las la-search text-lg text-secondary"></i>
                        <input
                            :value="memberSearch"
                            type="search"
                            class="min-w-0 flex-1 border-0 bg-transparent p-0 text-sm text-primary placeholder-secondary focus:ring-0"
                            placeholder="Mitglied suchen"
                            @input="setMemberSearch($event.target.value)"
                        >
                    </label>
                    <select
                        :value="memberStatusFilter"
                        class="rounded-lg border border-border bg-inputBg px-3 py-2 text-sm text-primary"
                        @change="setMemberStatusFilter($event.target.value)"
                    >
                        <option value="all">Alle Status</option>
                        <option v-for="status in membershipStatuses" :key="status" :value="status">{{ statusLabel(status) }}</option>
                    </select>
                    <select
                        :value="memberEndFilter"
                        class="rounded-lg border border-border bg-inputBg px-3 py-2 text-sm text-primary"
                        @change="setMemberEndFilter($event.target.value)"
                    >
                        <option value="all">Alle Laufzeiten</option>
                        <option value="ending_30">Endet in 30 Tagen</option>
                        <option value="ending_60">Endet in 60 Tagen</option>
                        <option value="expired">Bereits abgelaufen</option>
                        <option value="no_end">Ohne Enddatum</option>
                    </select>
                </div>
            </div>
            <div v-if="endingSoonMembersCount" class="mt-4 rounded-lg border border-warning/30 bg-warning/10 px-4 py-3 text-sm text-warning">
                {{ endingSoonMembersCount }} Mitgliedschaft{{ endingSoonMembersCount === 1 ? '' : 'en' }} endet innerhalb der nächsten 30 Tage.
            </div>
        </div>

        <div class="divide-y divide-border">
            <article v-for="member in filteredMembers" :key="member.id" class="p-5">
                <div class="flex flex-col gap-4 xl:flex-row xl:items-start xl:justify-between">
                    <div class="min-w-0">
                        <div class="flex flex-wrap items-center gap-2">
                            <Link :href="route('auth.users.show', member.id)" class="font-semibold text-primary hover:underline">
                                {{ member.name }}
                            </Link>
                            <span class="rounded-full px-2 py-1 text-xs font-semibold" :class="statusClass(formFor(member).membership_status)">
                                {{ statusLabel(formFor(member).membership_status) }}
                            </span>
                            <span
                                v-if="endLabel(formFor(member).membership_ends_on)"
                                class="rounded-full bg-warning/10 px-2 py-1 text-xs font-semibold text-warning"
                            >
                                {{ endLabel(formFor(member).membership_ends_on) }}
                            </span>
                        </div>
                        <p class="mt-1 text-sm text-secondary">{{ member.email }}</p>
                        <p class="mt-2 text-xs text-secondary">
                            Nr. {{ formFor(member).member_number || '-' }} · Beitrag {{ formatMoney(formFor(member).contribution_amount) }} · {{ intervalLabel(formFor(member).contribution_interval) }}
                        </p>
                        <p class="mt-1 text-xs text-secondary">
                            Lizenznummer: {{ formFor(member).athlete_license_number || '-' }} - Ende {{ formatDate(formFor(member).membership_ends_on) }}
                        </p>
                    </div>

                    <div class="flex flex-wrap gap-2">
                        <button type="button" class="rounded-lg border border-border px-3 py-2 text-sm text-primary" @click="toggleEditingMember(member)">
                            Bearbeiten
                        </button>
                        <button
                            type="button"
                            class="rounded-lg bg-buttonPrimary px-3 py-2 text-sm font-semibold text-buttonTextPrimary disabled:cursor-not-allowed disabled:opacity-50"
                            :disabled="capabilities.invoices === false"
                            :title="capabilities.invoices === false ? 'Rechnungen sind ab Starter verfügbar' : ''"
                            @click="openInvoice(member)"
                        >
                            Rechnung
                        </button>
                        <button
                            type="button"
                            class="rounded-lg border border-error/40 px-3 py-2 text-sm font-semibold text-error hover:bg-error/10"
                            @click="removeMember(member)"
                        >
                            Aus Verein entfernen
                        </button>
                    </div>
                </div>

                <form v-if="editingMemberId === member.id" class="mt-4 grid gap-3 rounded-lg border border-border bg-bg p-4 md:grid-cols-3" @submit.prevent="saveMember(member)">
                    <div>
                        <label class="text-xs font-semibold uppercase text-secondary">Rollen</label>
                        <div class="mt-1 grid gap-2 rounded-lg border border-border bg-inputBg p-3">
                            <label v-for="role in clubRoleOptions" :key="role.value" class="flex items-center gap-2 text-sm text-primary">
                                <input
                                    v-model="formFor(member).roles"
                                    type="checkbox"
                                    :value="role.value"
                                    class="rounded border-border bg-card"
                                >
                                <span>{{ role.label }}</span>
                            </label>
                        </div>
                        <p class="mt-1 text-xs text-secondary">Mehrere Rollen sind möglich, z. B. Trainer und Kassierer.</p>
                    </div>

                    <div>
                        <label class="text-xs font-semibold uppercase text-secondary">Mitgliedschaft</label>
                        <select v-model="formFor(member).membership_status" class="mt-1 w-full rounded-lg border border-border bg-inputBg px-3 py-2 text-sm text-primary">
                            <option v-for="status in membershipStatuses" :key="status" :value="status">{{ statusLabel(status) }}</option>
                        </select>
                    </div>

                    <div>
                        <label class="text-xs font-semibold uppercase text-secondary">Mitgliedschaftstyp</label>
                        <select v-model="formFor(member).club_membership_type_id" class="mt-1 w-full rounded-lg border border-border bg-inputBg px-3 py-2 text-sm text-primary">
                            <option value="">Kein Typ</option>
                            <option v-for="type in membershipTypes" :key="type.id" :value="type.id">{{ type.name }}</option>
                        </select>
                    </div>

                    <div>
                        <label class="text-xs font-semibold uppercase text-secondary">Mitgliedsnummer</label>
                        <div class="mt-1 flex gap-2">
                            <input v-model="formFor(member).member_number" class="min-w-0 flex-1 rounded-lg border border-border bg-inputBg px-3 py-2 text-sm text-primary">
                            <button type="button" class="rounded-lg border border-border px-3 py-2 text-xs font-semibold text-primary hover:bg-inputBg" @click="generateMemberNumber(member)">
                                Generieren
                            </button>
                        </div>
                    </div>

                    <div>
                        <label class="text-xs font-semibold uppercase text-secondary">Lizenznummer</label>
                        <input
                            v-model="formFor(member).athlete_license_number"
                            class="mt-1 w-full rounded-lg border border-border bg-inputBg px-3 py-2 text-sm text-primary"
                            placeholder="z. B. Spielerpass- oder Verbandsnummer"
                        >
                    </div>

                    <div>
                        <label class="text-xs font-semibold uppercase text-secondary">Beitrag</label>
                        <input v-model="formFor(member).contribution_amount" type="number" min="0" step="0.01" class="mt-1 w-full rounded-lg border border-border bg-inputBg px-3 py-2 text-sm text-primary">
                    </div>

                    <div>
                        <label class="text-xs font-semibold uppercase text-secondary">Intervall</label>
                        <select v-model="formFor(member).contribution_interval" class="mt-1 w-full rounded-lg border border-border bg-inputBg px-3 py-2 text-sm text-primary">
                            <option v-for="interval in contributionIntervals" :key="interval" :value="interval">{{ intervalLabel(interval) }}</option>
                        </select>
                    </div>

                    <div>
                        <label class="text-xs font-semibold uppercase text-secondary">Nächste automatische Rechnung</label>
                        <input v-model="formFor(member).contribution_next_invoice_on" type="date" class="mt-1 w-full rounded-lg border border-border bg-inputBg px-3 py-2 text-sm text-primary">
                        <p class="mt-1 text-xs text-secondary">Automatik wird ab Pro/Elite ausgeführt.</p>
                    </div>

                    <div>
                        <label class="text-xs font-semibold uppercase text-secondary">SEPA IBAN</label>
                        <input v-model="formFor(member).sepa_iban" class="mt-1 w-full rounded-lg border border-border bg-inputBg px-3 py-2 text-sm text-primary" placeholder="DE...">
                    </div>

                    <div>
                        <label class="text-xs font-semibold uppercase text-secondary">SEPA BIC optional</label>
                        <input v-model="formFor(member).sepa_bic" class="mt-1 w-full rounded-lg border border-border bg-inputBg px-3 py-2 text-sm text-primary" placeholder="GENODE...">
                    </div>

                    <div>
                        <label class="text-xs font-semibold uppercase text-secondary">Mandatsreferenz</label>
                        <input v-model="formFor(member).sepa_mandate_reference" class="mt-1 w-full rounded-lg border border-border bg-inputBg px-3 py-2 text-sm text-primary" placeholder="MANDAT-1001">
                    </div>

                    <div>
                        <label class="text-xs font-semibold uppercase text-secondary">Mandatsdatum</label>
                        <input v-model="formFor(member).sepa_mandate_signed_on" type="date" class="mt-1 w-full rounded-lg border border-border bg-inputBg px-3 py-2 text-sm text-primary">
                    </div>

                    <label class="flex items-center gap-2 self-end rounded-lg border border-border bg-inputBg px-3 py-2 text-sm text-primary">
                        <input v-model="formFor(member).sepa_mandate_active" type="checkbox" class="rounded border-border bg-bg">
                        SEPA-Mandat aktiv
                    </label>

                    <div>
                        <label class="text-xs font-semibold uppercase text-secondary">Eintritt</label>
                        <input v-model="formFor(member).joined_on" type="date" class="mt-1 w-full rounded-lg border border-border bg-inputBg px-3 py-2 text-sm text-primary">
                    </div>

                    <div>
                        <label class="text-xs font-semibold uppercase text-secondary">Ende der Mitgliedschaft</label>
                        <input v-model="formFor(member).membership_ends_on" type="date" class="mt-1 w-full rounded-lg border border-border bg-inputBg px-3 py-2 text-sm text-primary">
                    </div>

                    <div class="md:col-span-2">
                        <label class="text-xs font-semibold uppercase text-secondary">Notiz</label>
                        <textarea v-model="formFor(member).membership_notes" rows="3" class="mt-1 w-full rounded-lg border border-border bg-inputBg px-3 py-2 text-sm text-primary"></textarea>
                    </div>

                    <div class="md:col-span-3">
                        <button class="rounded-lg bg-buttonPrimary px-4 py-2 text-sm font-semibold text-buttonTextPrimary">
                            Speichern
                        </button>
                    </div>
                </form>

                <form v-if="invoiceMemberId === member.id" class="mt-4 grid gap-3 rounded-lg border border-border bg-bg p-4 md:grid-cols-[1fr_140px_170px_auto]" @submit.prevent="createInvoice(member)">
                    <input v-model="invoiceForm.title" class="rounded-lg border border-border bg-inputBg px-3 py-2 text-sm text-primary" placeholder="Titel">
                    <input v-model="invoiceForm.amount" type="number" min="0.01" step="0.01" class="rounded-lg border border-border bg-inputBg px-3 py-2 text-sm text-primary" placeholder="Betrag">
                    <input v-model="invoiceForm.due_date" type="date" class="rounded-lg border border-border bg-inputBg px-3 py-2 text-sm text-primary">
                    <button class="rounded-lg bg-buttonPrimary px-4 py-2 text-sm font-semibold text-buttonTextPrimary">
                        Erstellen
                    </button>
                    <textarea v-model="invoiceForm.description" rows="2" class="rounded-lg border border-border bg-inputBg px-3 py-2 text-sm text-primary md:col-span-4" placeholder="Beschreibung optional"></textarea>
                </form>
            </article>
            <p v-if="!filteredMembers.length" class="p-6 text-sm text-secondary">
                Keine Mitglieder passen zu deiner Suche.
            </p>
        </div>
    </section>
</template>


