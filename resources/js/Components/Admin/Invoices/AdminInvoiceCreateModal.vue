<script setup>
import { computed } from 'vue'

const props = defineProps({
    createModalOpen: { type: Boolean, default: false },
    form: { type: Object, required: true },
    invoiceTypes: { type: Array, default: () => [] },
    users: { type: Array, default: () => [] },
    clubs: { type: Array, default: () => [] },
    recipientMode: { type: String, default: 'person' },
    selectedType: { type: Object, default: null },
    selectedRecipientLabel: { type: String, default: '' },
    exampleTitles: { type: Object, required: true },
    statusLabel: { type: Function, required: true },
    userLabel: { type: Function, required: true },
    closeCreateModal: { type: Function, required: true },
    submit: { type: Function, required: true },
})

const emit = defineEmits(['update:recipientMode'])

const recipientModeModel = computed({
    get: () => props.recipientMode,
    set: (value) => emit('update:recipientMode', value),
})
</script>

<template>
    <Teleport to="body">
        <div
            v-if="createModalOpen"
            class="fixed inset-0 z-[90] flex items-center justify-center bg-black/65 p-3 backdrop-blur-sm sm:p-4"
            @click.self="closeCreateModal"
        >
            <form
                class="flex max-h-[calc(100dvh-1.5rem)] w-full max-w-6xl flex-col overflow-hidden rounded-2xl border border-border bg-card shadow-2xl"
                @submit.prevent="submit"
            >
                <div class="flex shrink-0 items-start justify-between gap-4 border-b border-border px-4 py-4 sm:px-6">
                    <div class="min-w-0">
                        <p class="text-xs font-semibold uppercase tracking-[0.16em] text-air-blue">Neue Rechnung</p>
                        <h2 class="mt-1 text-xl font-black text-primary sm:text-2xl">Rechnung erstellen</h2>
                        <p class="mt-1 text-sm text-secondary">
                            Grund, Empfänger und Leistungsdetails erfassen. Danach wird die Person automatisch informiert.
                        </p>
                    </div>
                    <button
                        type="button"
                        class="inline-flex h-10 w-10 shrink-0 items-center justify-center rounded-xl border border-border text-secondary hover:bg-muted hover:text-primary"
                        :disabled="form.processing"
                        aria-label="Modal schließen"
                        @click="closeCreateModal"
                    >
                        <i class="las la-times text-xl"></i>
                    </button>
                </div>

                <div class="min-h-0 flex-1 overflow-y-auto p-4 sm:p-6">
                    <div class="grid gap-5 xl:grid-cols-[minmax(0,1fr)_22rem]">
                        <div class="space-y-5">
                            <section class="rounded-2xl border border-border bg-inputBg p-4">
                                <p class="text-sm font-bold text-primary">Rechnungsgrund</p>
                                <div class="mt-3 grid gap-2 sm:grid-cols-2 lg:grid-cols-3">
                                    <button
                                        v-for="type in invoiceTypes"
                                        :key="type.value"
                                        type="button"
                                        class="rounded-xl border p-3 text-left transition"
                                        :class="form.source === type.value ? 'border-air-blue bg-air-blue/10 text-primary' : 'border-border bg-card text-secondary hover:border-borderHover hover:text-primary'"
                                        @click="form.source = type.value"
                                    >
                                        <span class="block text-sm font-black">{{ type.label }}</span>
                                        <span class="mt-1 block text-xs leading-5">{{ type.hint }}</span>
                                    </button>
                                </div>
                                <p v-if="form.errors.source" class="mt-1 text-xs text-error">{{ form.errors.source }}</p>
                            </section>

                            <section class="rounded-2xl border border-border bg-inputBg p-4">
                                <div class="flex flex-col gap-3 lg:flex-row lg:items-center lg:justify-between">
                                    <div>
                                        <p class="text-sm font-bold text-primary">Empfänger</p>
                                        <p class="mt-1 text-xs text-secondary">Sportler, Trainer, Sponsor, Kursanbieter oder Verein.</p>
                                    </div>
                                    <div class="grid grid-cols-2 overflow-hidden rounded-xl border border-border bg-card p-1">
                                        <button type="button" class="rounded-lg px-3 py-2 text-sm font-bold" :class="recipientModeModel === 'person' ? 'bg-buttonPrimary text-buttonTextPrimary' : 'text-secondary hover:text-primary'" @click="recipientModeModel = 'person'">
                                            Person
                                        </button>
                                        <button type="button" class="rounded-lg px-3 py-2 text-sm font-bold" :class="recipientModeModel === 'club' ? 'bg-buttonPrimary text-buttonTextPrimary' : 'text-secondary hover:text-primary'" @click="recipientModeModel = 'club'">
                                            Verein
                                        </button>
                                    </div>
                                </div>

                                <div class="mt-4 grid gap-4 lg:grid-cols-2">
                                    <label v-if="recipientModeModel === 'person'" class="block">
                                        <span class="text-sm font-semibold text-primary">Person auswählen</span>
                                        <select v-model="form.user_id" class="mt-1 w-full rounded-xl border-border bg-card text-primary">
                                            <option value="">Sportler, Trainer, Sponsor oder Kursanbieter wählen</option>
                                            <option v-for="user in users" :key="user.id" :value="user.id">{{ userLabel(user) }}</option>
                                        </select>
                                        <p v-if="form.errors.user_id" class="mt-1 text-xs text-error">{{ form.errors.user_id }}</p>
                                    </label>

                                    <label v-if="recipientModeModel === 'club'" class="block">
                                        <span class="text-sm font-semibold text-primary">Verein auswählen</span>
                                        <select v-model="form.club_id" class="mt-1 w-full rounded-xl border-border bg-card text-primary">
                                            <option value="">Verein wählen</option>
                                            <option v-for="club in clubs" :key="club.id" :value="club.id">{{ club.name }}</option>
                                        </select>
                                        <p v-if="form.errors.club_id" class="mt-1 text-xs text-error">{{ form.errors.club_id }}</p>
                                    </label>

                                    <label class="block">
                                        <span class="text-sm font-semibold text-primary">Status</span>
                                        <select v-model="form.status" class="mt-1 w-full rounded-xl border-border bg-card text-primary">
                                            <option value="open">Offen</option>
                                            <option value="pending">Ausstehend</option>
                                            <option value="paid">Bezahlt</option>
                                            <option value="overdue">Überfällig</option>
                                            <option value="cancelled">Storniert</option>
                                        </select>
                                        <p v-if="form.errors.status" class="mt-1 text-xs text-error">{{ form.errors.status }}</p>
                                    </label>
                                </div>
                            </section>

                            <section class="grid gap-4 md:grid-cols-2">
                                <label class="block md:col-span-2">
                                    <span class="text-sm font-semibold text-primary">Titel</span>
                                    <input v-model="form.title" class="mt-1 w-full rounded-xl border-border bg-inputBg text-primary" :placeholder="exampleTitles[form.source] || 'Rechnungstitel'" required>
                                    <p v-if="form.errors.title" class="mt-1 text-xs text-error">{{ form.errors.title }}</p>
                                </label>

                                <label class="block">
                                    <span class="text-sm font-semibold text-primary">Betrag EUR</span>
                                    <input v-model="form.amount" class="mt-1 w-full rounded-xl border-border bg-inputBg text-primary" min="0.01" step="0.01" type="number" required>
                                    <p v-if="form.errors.amount" class="mt-1 text-xs text-error">{{ form.errors.amount }}</p>
                                </label>

                                <label class="block">
                                    <span class="text-sm font-semibold text-primary">Rechnungsnummer</span>
                                    <input v-model="form.number" class="mt-1 w-full rounded-xl border-border bg-inputBg text-primary" placeholder="Optional, sonst automatisch">
                                    <p v-if="form.errors.number" class="mt-1 text-xs text-error">{{ form.errors.number }}</p>
                                </label>

                                <label class="block">
                                    <span class="text-sm font-semibold text-primary">Ausgestellt am</span>
                                    <input v-model="form.issued_at" class="mt-1 w-full rounded-xl border-border bg-inputBg text-primary" type="date">
                                    <p v-if="form.errors.issued_at" class="mt-1 text-xs text-error">{{ form.errors.issued_at }}</p>
                                </label>

                                <label class="block">
                                    <span class="text-sm font-semibold text-primary">Faellig am</span>
                                    <input v-model="form.due_date" class="mt-1 w-full rounded-xl border-border bg-inputBg text-primary" type="date" required>
                                    <p v-if="form.errors.due_date" class="mt-1 text-xs text-error">{{ form.errors.due_date }}</p>
                                </label>

                                <label class="block md:col-span-2">
                                    <span class="text-sm font-semibold text-primary">Beschreibung / Leistungsdetails</span>
                                    <textarea v-model="form.description" class="mt-1 min-h-28 w-full rounded-xl border-border bg-inputBg text-primary" placeholder="z.B. Website-Konzept, Logo-Entwurf, Kursgebuehr, Sponsoring-Paket, Outfit-Abo, Marketplace-Kauf ..."></textarea>
                                    <p v-if="form.errors.description" class="mt-1 text-xs text-error">{{ form.errors.description }}</p>
                                </label>
                            </section>
                        </div>

                        <aside class="rounded-2xl border border-border bg-inputBg p-5 xl:sticky xl:top-0 xl:self-start">
                            <p class="text-xs font-semibold uppercase tracking-[0.16em] text-air-blue">Vorschau</p>
                            <h3 class="mt-2 text-lg font-black text-primary">{{ form.title || exampleTitles[form.source] || 'Neue Rechnung' }}</h3>
                            <div class="mt-4 space-y-3 text-sm">
                                <div class="rounded-xl bg-card p-3">
                                    <p class="text-xs uppercase text-secondary">Grund</p>
                                    <p class="mt-1 font-bold text-primary">{{ selectedType?.label || '-' }}</p>
                                </div>
                                <div class="rounded-xl bg-card p-3">
                                    <p class="text-xs uppercase text-secondary">Empfänger</p>
                                    <p class="mt-1 font-bold text-primary">{{ selectedRecipientLabel }}</p>
                                </div>
                                <div class="grid grid-cols-2 gap-3">
                                    <div class="rounded-xl bg-card p-3">
                                        <p class="text-xs uppercase text-secondary">Betrag</p>
                                        <p class="mt-1 font-bold text-primary">{{ form.amount || '0,00' }} EUR</p>
                                    </div>
                                    <div class="rounded-xl bg-card p-3">
                                        <p class="text-xs uppercase text-secondary">Status</p>
                                        <p class="mt-1 font-bold text-primary">{{ statusLabel(form.status) }}</p>
                                    </div>
                                </div>
                            </div>
                        </aside>
                    </div>
                </div>

                <div class="flex shrink-0 flex-col-reverse gap-3 border-t border-border px-4 py-4 sm:flex-row sm:justify-end sm:px-6">
                    <button type="button" class="rounded-xl border border-border px-5 py-3 text-sm font-bold text-primary hover:bg-muted" :disabled="form.processing" @click="closeCreateModal">
                        Abbrechen
                    </button>
                    <button
                        type="submit"
                        class="inline-flex items-center justify-center gap-2 rounded-xl bg-buttonPrimary px-5 py-3 text-sm font-black text-buttonTextPrimary disabled:cursor-not-allowed disabled:opacity-50"
                        :disabled="form.processing"
                    >
                        <i class="las la-file-invoice text-lg"></i>
                        Rechnung erstellen
                    </button>
                </div>
            </form>
        </div>
    </Teleport>
</template>


