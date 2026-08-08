<script setup>
import Modal from '@/Components/Modal.vue'

defineProps({
    showAddMemberModal: { type: Boolean, default: false },
    showImportModal: { type: Boolean, default: false },
    showBankImportModal: { type: Boolean, default: false },
    capabilities: { type: Object, required: true },
    membershipStatuses: { type: Array, default: () => [] },
    contributionIntervals: { type: Array, default: () => [] },
    emailMemberForm: { type: Object, required: true },
    importForm: { type: Object, required: true },
    bankImportForm: { type: Object, required: true },
    statusLabel: { type: Function, required: true },
    intervalLabel: { type: Function, required: true },
    addEmailMember: { type: Function, required: true },
    addEmailMemberRow: { type: Function, required: true },
    removeEmailMemberRow: { type: Function, required: true },
    importEmailMembers: { type: Function, required: true },
    importBankTransactions: { type: Function, required: true },
})

const emit = defineEmits([
    'update:showAddMemberModal',
    'update:showImportModal',
    'update:showBankImportModal',
])

const closeAddMemberModal = () => emit('update:showAddMemberModal', false)
const closeImportModal = () => emit('update:showImportModal', false)
const closeBankImportModal = () => emit('update:showBankImportModal', false)
</script>

<template>
        <Modal :show="showAddMemberModal" max-width="2xl" @close="closeAddMemberModal">
            <div class="p-2">
                <h2 class="text-xl font-bold text-primary">Mitglieder per E-Mail hinzufügen</h2>
                <p class="mt-1 text-sm text-secondary">
                    Erfasse mehrere Mitglieder auf einmal. Wenn eine Einladung aktiv ist, werden vorhandene Konten verknüpft, sonst geht eine Einladung per E-Mail raus.
                </p>
                <p
                    v-if="capabilities.member_invitation_daily_limit"
                    class="mt-2 rounded-lg border border-border bg-bg px-3 py-2 text-xs font-semibold text-secondary"
                >
                    Free-Limit: {{ capabilities.member_invitation_remaining_today }} von {{ capabilities.member_invitation_daily_limit }} Einladungen heute übrig.
                </p>

                <form class="mt-5 space-y-4" @submit.prevent="addEmailMember">
                    <label class="flex items-center gap-2 rounded-lg border border-border bg-bg px-3 py-2 text-sm text-primary">
                        <input v-model="emailMemberForm.send_invitation" type="checkbox" class="rounded border-border bg-inputBg">
                        Einladung zu Airmius verschicken
                    </label>

                    <div class="max-h-[60vh] space-y-3 overflow-y-auto pr-1">
                        <article
                            v-for="(member, index) in emailMemberForm.members"
                            :key="index"
                            class="rounded-lg border border-border bg-bg p-4"
                        >
                            <div class="mb-3 flex items-center justify-between gap-3">
                                <h3 class="text-sm font-semibold text-primary">Mitglied {{ index + 1 }}</h3>
                                <button
                                    type="button"
                                    class="rounded-lg border border-border px-3 py-1 text-xs font-semibold text-primary hover:bg-inputBg"
                                    @click="removeEmailMemberRow(index)"
                                >
                                    Entfernen
                                </button>
                            </div>

                            <div class="grid gap-3 md:grid-cols-2">
                                <div>
                                    <label class="text-xs font-semibold uppercase text-secondary">{{ $t('Name') }}</label>
                                    <input
                                        v-model="member.name"
                                        class="mt-1 w-full rounded-lg border border-border bg-inputBg px-3 py-2 text-sm text-primary"
                                        placeholder="Optional"
                                    >
                                </div>

                                <div>
                                    <label class="text-xs font-semibold uppercase text-secondary">{{ $t('E-Mail') }}</label>
                                    <input
                                        v-model="member.email"
                                        type="email"
                                        required
                                        class="mt-1 w-full rounded-lg border border-border bg-inputBg px-3 py-2 text-sm text-primary"
                                        placeholder="mitglied@example.org"
                                    >
                                </div>

                                <div>
                                    <label class="text-xs font-semibold uppercase text-secondary">Mitgliedschaft</label>
                                    <select v-model="member.membership_status" class="mt-1 w-full rounded-lg border border-border bg-inputBg px-3 py-2 text-sm text-primary">
                                        <option v-for="status in membershipStatuses" :key="status" :value="status">{{ statusLabel(status) }}</option>
                                    </select>
                                </div>

                                <div>
                                    <label class="text-xs font-semibold uppercase text-secondary">Mitgliedsnummer</label>
                                    <input
                                        v-model="member.member_number"
                                        class="mt-1 w-full rounded-lg border border-border bg-inputBg px-3 py-2 text-sm text-primary"
                                        placeholder="Optional"
                                    >
                                </div>

                                <div>
                                    <label class="text-xs font-semibold uppercase text-secondary">Lizenznummer</label>
                                    <input
                                        v-model="member.athlete_license_number"
                                        class="mt-1 w-full rounded-lg border border-border bg-inputBg px-3 py-2 text-sm text-primary"
                                        placeholder="Optional"
                                    >
                                </div>

                                <div>
                                    <label class="text-xs font-semibold uppercase text-secondary">Beitrag</label>
                                    <input
                                        v-model="member.contribution_amount"
                                        type="number"
                                        min="0"
                                        step="0.01"
                                        class="mt-1 w-full rounded-lg border border-border bg-inputBg px-3 py-2 text-sm text-primary"
                                        placeholder="0,00"
                                    >
                                </div>

                                <div>
                                    <label class="text-xs font-semibold uppercase text-secondary">Intervall</label>
                                    <select v-model="member.contribution_interval" class="mt-1 w-full rounded-lg border border-border bg-inputBg px-3 py-2 text-sm text-primary">
                                        <option v-for="interval in contributionIntervals" :key="interval" :value="interval">{{ intervalLabel(interval) }}</option>
                                    </select>
                                </div>

                                <div>
                                    <label class="text-xs font-semibold uppercase text-secondary">Nächste automatische Rechnung</label>
                                    <input
                                        v-model="member.contribution_next_invoice_on"
                                        type="date"
                                        class="mt-1 w-full rounded-lg border border-border bg-inputBg px-3 py-2 text-sm text-primary"
                                    >
                                </div>

                                <div>
                                    <label class="text-xs font-semibold uppercase text-secondary">SEPA IBAN</label>
                                    <input
                                        v-model="member.sepa_iban"
                                        class="mt-1 w-full rounded-lg border border-border bg-inputBg px-3 py-2 text-sm text-primary"
                                        placeholder="Optional"
                                    >
                                </div>

                                <div>
                                    <label class="text-xs font-semibold uppercase text-secondary">SEPA BIC</label>
                                    <input
                                        v-model="member.sepa_bic"
                                        class="mt-1 w-full rounded-lg border border-border bg-inputBg px-3 py-2 text-sm text-primary"
                                        placeholder="Optional"
                                    >
                                </div>

                                <div>
                                    <label class="text-xs font-semibold uppercase text-secondary">Mandatsreferenz</label>
                                    <input
                                        v-model="member.sepa_mandate_reference"
                                        class="mt-1 w-full rounded-lg border border-border bg-inputBg px-3 py-2 text-sm text-primary"
                                        placeholder="Optional"
                                    >
                                </div>

                                <div>
                                    <label class="text-xs font-semibold uppercase text-secondary">Mandatsdatum</label>
                                    <input
                                        v-model="member.sepa_mandate_signed_on"
                                        type="date"
                                        class="mt-1 w-full rounded-lg border border-border bg-inputBg px-3 py-2 text-sm text-primary"
                                    >
                                </div>

                                <label class="flex items-center gap-2 self-end rounded-lg border border-border bg-inputBg px-3 py-2 text-sm text-primary">
                                    <input v-model="member.sepa_mandate_active" type="checkbox" class="rounded border-border bg-bg">
                                    SEPA aktiv
                                </label>

                                <div>
                                    <label class="text-xs font-semibold uppercase text-secondary">Ende der Mitgliedschaft</label>
                                    <input
                                        v-model="member.membership_ends_on"
                                        type="date"
                                        class="mt-1 w-full rounded-lg border border-border bg-inputBg px-3 py-2 text-sm text-primary"
                                    >
                                </div>
                            </div>
                        </article>
                    </div>

                    <button
                        type="button"
                        class="rounded-lg border border-border px-4 py-2 text-sm font-semibold text-primary hover:bg-inputBg"
                        @click="addEmailMemberRow"
                    >
                        Weiteres Mitglied
                    </button>

                    <div class="flex justify-end gap-2">
                        <button type="button" class="rounded-lg border border-border px-4 py-2 text-sm font-semibold text-primary" @click="closeAddMemberModal">
                            Abbrechen
                        </button>
                        <button class="rounded-lg bg-buttonPrimary px-4 py-2 text-sm font-semibold text-buttonTextPrimary">
                            Mitglieder speichern
                        </button>
                    </div>
                </form>
            </div>
        </Modal>

        <Modal :show="showImportModal" max-width="2xl" @close="closeImportModal">
            <div class="p-2">
                <h2 class="text-xl font-bold text-primary">Mitglieder importieren</h2>
                <p class="mt-1 text-sm text-secondary">
                    Importiere Excel- oder CSV-Listen mit Name, E-Mail, Mitgliedsnummer, Lizenznummer, Beitrag, Eintritts- und Enddatum.
                </p>

                <div class="mt-4 rounded-lg border border-border bg-bg p-4 text-sm text-secondary">
                    <p class="font-semibold text-primary">Empfohlen</p>
                    <p class="mt-1">
                        Lade zuerst die Airmius Excel-Vorlage herunter. Die erste Beispielzeile kannst du ersetzen oder entfernen.
                    </p>
                    <a
                        :href="route('auth.club-memberships.import-template')"
                        class="mt-3 inline-flex rounded-lg border border-border px-3 py-2 text-sm font-semibold text-primary hover:bg-inputBg"
                    >
                        Vorlage herunterladen
                    </a>
                </div>

                <form class="mt-5 space-y-4" @submit.prevent="importEmailMembers">
                    <div>
                        <label class="text-xs font-semibold uppercase text-secondary">Datei</label>
                        <input
                            type="file"
                            accept=".xlsx,.csv,.txt"
                            required
                            class="mt-1 block w-full rounded-lg border border-border bg-inputBg px-3 py-2 text-sm text-primary"
                            @input="importForm.file = $event.target.files[0]"
                        >
                        <p v-if="importForm.errors.file" class="mt-2 text-sm text-error">{{ importForm.errors.file }}</p>
                    </div>

                    <label class="flex items-start gap-2 rounded-lg border border-border bg-bg px-3 py-2 text-sm text-primary">
                        <input v-model="importForm.send_invitation" type="checkbox" class="mt-1 rounded border-border bg-inputBg">
                        <span>
                            Einladung/Verknüpfung direkt aktivieren
                            <span class="block text-xs text-secondary">
                                Bestehende Airmius-Konten werden verbunden, sonst wird eine Einladung an die E-Mail-Adresse gesendet.
                            </span>
                        </span>
                    </label>

                    <div class="flex justify-end gap-2">
                        <button type="button" class="rounded-lg border border-border px-4 py-2 text-sm font-semibold text-primary" @click="closeImportModal">
                            Abbrechen
                        </button>
                        <button class="rounded-lg bg-buttonPrimary px-4 py-2 text-sm font-semibold text-buttonTextPrimary" :disabled="importForm.processing">
                            Import starten
                        </button>
                    </div>
                </form>
            </div>
        </Modal>

        <Modal :show="showBankImportModal" max-width="2xl" @close="closeBankImportModal">
            <div class="p-2">
                <h2 class="text-xl font-bold text-primary">Bankumsätze importieren</h2>
                <p class="mt-1 text-sm text-secondary">
                    Lade eine CSV aus dem Online-Banking hoch. Erkannt werden typische Spalten wie Datum, Betrag, Auftraggeber, IBAN und Verwendungszweck.
                </p>

                <form class="mt-5 space-y-4" @submit.prevent="importBankTransactions">
                    <div>
                        <label class="text-xs font-semibold uppercase text-secondary">CSV-Datei</label>
                        <input
                            type="file"
                            accept=".csv,.txt"
                            required
                            class="mt-1 block w-full rounded-lg border border-border bg-inputBg px-3 py-2 text-sm text-primary"
                            @input="bankImportForm.file = $event.target.files[0]"
                        >
                        <p v-if="bankImportForm.errors.file" class="mt-2 text-sm text-error">{{ bankImportForm.errors.file }}</p>
                    </div>

                    <p class="rounded-lg border border-border bg-bg p-3 text-xs text-secondary">
                        Sichere Treffer mit Rechnungsnummer und Betrag werden automatisch als bezahlt markiert. Vorschläge kannst du danach bestätigen.
                    </p>

                    <div class="flex justify-end gap-2">
                        <button type="button" class="rounded-lg border border-border px-4 py-2 text-sm font-semibold text-primary" @click="closeBankImportModal">
                            Abbrechen
                        </button>
                        <button class="rounded-lg bg-buttonPrimary px-4 py-2 text-sm font-semibold text-buttonTextPrimary" :disabled="bankImportForm.processing">
                            Import starten
                        </button>
                    </div>
                </form>
            </div>
        </Modal>
</template>
