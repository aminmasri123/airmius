<script setup>
import Modal from '@/Components/Modal.vue'

defineProps({
    editingJobId: {
        type: [Number, String, null],
        default: null,
    },
    errors: {
        type: Object,
        default: () => ({}),
    },
    isSubmittingJob: {
        type: Boolean,
        default: false,
    },
    jobFormFor: {
        type: Function,
        required: true,
    },
    jobModalNotice: {
        type: Object,
        default: null,
    },
    selectedClub: {
        type: Object,
        default: null,
    },
    show: {
        type: Boolean,
        default: false,
    },
})

defineEmits(['close', 'submit'])
</script>

<template>
    <Modal :show="show" max-width="xl" @close="$emit('close')">
        <form
            v-if="selectedClub"
            class="space-y-5"
            aria-labelledby="job-modal-title"
            @submit.prevent="$emit('submit', selectedClub)"
        >
            <div>
                <p class="text-xs font-semibold uppercase tracking-wide text-secondary">
                    {{ selectedClub.name }}
                </p>
                <h2 id="job-modal-title" class="mt-1 text-lg font-bold text-primary">
                    {{ editingJobId ? 'Eintrag bearbeiten' : 'Jobs- oder Ehrenamtsangebot erstellen' }}
                </h2>
                <p class="mt-2 text-sm text-secondary">
                    Beschreibe die Aufgabe klar genug, damit Interessierte sofort verstehen, ob sie passt und wie sie Kontakt aufnehmen können.
                </p>
            </div>

            <div
                v-if="jobModalNotice"
                class="rounded-lg border px-4 py-3 text-sm"
                role="alert"
                :class="jobModalNotice.type === 'success'
                    ? 'border-success/30 bg-success/10 text-success'
                    : 'border-error/30 bg-error/10 text-error'"
            >
                {{ jobModalNotice.message }}
            </div>

            <section class="space-y-3">
                <h3 class="text-sm font-semibold text-primary">Was wird gesucht?</h3>

                <div class="grid gap-3 sm:grid-cols-2">
                    <label class="block sm:col-span-2">
                        <span class="text-xs font-semibold uppercase text-secondary">Titel *</span>
                        <input
                            v-model="jobFormFor(selectedClub).title"
                            required
                            autocomplete="off"
                            class="mt-1 w-full rounded-lg border border-border bg-inputBg px-3 py-3 text-sm text-primary"
                            placeholder="z.B. Jugendtrainer U15"
                        >
                        <span v-if="errors.title" class="mt-1 block text-xs text-error">{{ errors.title }}</span>
                    </label>

                    <label class="block">
                        <span class="text-xs font-semibold uppercase text-secondary">Kategorie</span>
                        <select
                            v-model="jobFormFor(selectedClub).type"
                            class="mt-1 w-full rounded-lg border border-border bg-inputBg px-3 py-3 text-sm text-primary"
                        >
                            <option value="volunteer">Ehrenamt</option>
                            <option value="professional">Beruf / bezahlte Stelle</option>
                        </select>
                        <span v-if="errors.type" class="mt-1 block text-xs text-error">{{ errors.type }}</span>
                    </label>

                    <label class="block">
                        <span class="text-xs font-semibold uppercase text-secondary">Art</span>
                        <input
                            v-model="jobFormFor(selectedClub).employment_type"
                            autocomplete="off"
                            class="mt-1 w-full rounded-lg border border-border bg-inputBg px-3 py-3 text-sm text-primary"
                            placeholder="Teilzeit, Minijob, Ehrenamt"
                        >
                        <span v-if="errors.employment_type" class="mt-1 block text-xs text-error">{{ errors.employment_type }}</span>
                    </label>
                </div>
            </section>

            <section class="space-y-3">
                <h3 class="text-sm font-semibold text-primary">Rahmen</h3>

                <div class="grid gap-3 sm:grid-cols-2">
                    <label class="block">
                        <span class="text-xs font-semibold uppercase text-secondary">Adresse / Ort</span>
                        <input
                            v-model="jobFormFor(selectedClub).location"
                            autocomplete="address-line1"
                            class="mt-1 w-full rounded-lg border border-border bg-inputBg px-3 py-3 text-sm text-primary"
                            placeholder="Sportanlage, Adresse, Stadt oder Remote"
                        >
                        <span v-if="errors.location" class="mt-1 block text-xs text-error">{{ errors.location }}</span>
                    </label>

                    <label class="block">
                        <span class="text-xs font-semibold uppercase text-secondary">Umfang</span>
                        <input
                            v-model="jobFormFor(selectedClub).workload"
                            autocomplete="off"
                            class="mt-1 w-full rounded-lg border border-border bg-inputBg px-3 py-3 text-sm text-primary"
                            placeholder="z.B. 6 Std./Woche"
                        >
                        <span v-if="errors.workload" class="mt-1 block text-xs text-error">{{ errors.workload }}</span>
                    </label>
                </div>
            </section>

            <section class="space-y-3">
                <h3 class="text-sm font-semibold text-primary">Beschreibung & Kontakt</h3>

                <label class="block">
                    <span class="text-xs font-semibold uppercase text-secondary">Beschreibung *</span>
                    <textarea
                        v-model="jobFormFor(selectedClub).description"
                        required
                        rows="5"
                        class="mt-1 w-full rounded-lg border border-border bg-inputBg px-3 py-3 text-sm text-primary"
                        placeholder="Aufgaben, Voraussetzungen, Zeitraum und was die Person wissen sollte."
                    ></textarea>
                    <span v-if="errors.description" class="mt-1 block text-xs text-error">{{ errors.description }}</span>
                </label>

                <div class="grid gap-3 sm:grid-cols-2">
                    <label class="block">
                        <span class="text-xs font-semibold uppercase text-secondary">Kontakt E-Mail</span>
                        <input
                            v-model="jobFormFor(selectedClub).contact_email"
                            type="email"
                            autocomplete="email"
                            class="mt-1 w-full rounded-lg border border-border bg-inputBg px-3 py-3 text-sm text-primary"
                            placeholder="kontakt@verein.de"
                        >
                        <span v-if="errors.contact_email" class="mt-1 block text-xs text-error">{{ errors.contact_email }}</span>
                    </label>

                    <label class="block">
                        <span class="text-xs font-semibold uppercase text-secondary">Externer Bewerbungslink optional</span>
                        <input
                            v-model="jobFormFor(selectedClub).application_url"
                            type="url"
                            inputmode="url"
                            autocomplete="url"
                            class="mt-1 w-full rounded-lg border border-border bg-inputBg px-3 py-3 text-sm text-primary"
                            placeholder="https://formular.verein.de"
                        >
                        <span v-if="errors.application_url" class="mt-1 block text-xs text-error">{{ errors.application_url }}</span>
                        <span class="mt-1 block text-xs text-secondary">
                            Nur ausfüllen, wenn Interessierte zusätzlich auf ein externes Formular weitergeleitet werden sollen.
                        </span>
                    </label>
                </div>
            </section>

            <label class="flex items-start gap-3 rounded-lg border border-border bg-bg p-3 text-sm text-primary">
                <input
                    v-model="jobFormFor(selectedClub).is_published"
                    type="checkbox"
                    class="mt-1 rounded border-border bg-inputBg"
                >
                <span>
                    <span class="block font-semibold">Auf Webseite veröffentlichen</span>
                    <span class="block text-xs text-secondary">Wenn deaktiviert, bleibt der Eintrag als Entwurf im Dashboard.</span>
                </span>
            </label>

            <div class="flex flex-col-reverse gap-2 sm:flex-row sm:justify-end">
                <button
                    type="button"
                    class="rounded-lg border border-border px-4 py-3 text-sm font-semibold text-primary hover:bg-muted"
                    @click="$emit('close')"
                >
                    Abbrechen
                </button>
                <button
                    class="rounded-lg bg-buttonPrimary px-4 py-3 text-sm font-semibold text-buttonTextPrimary disabled:cursor-not-allowed disabled:opacity-60"
                    :disabled="isSubmittingJob"
                    :aria-busy="isSubmittingJob"
                >
                    {{ isSubmittingJob ? 'Wird gespeichert...' : (editingJobId ? 'Aktualisieren' : 'Eintrag erstellen') }}
                </button>
            </div>
        </form>
    </Modal>
</template>

