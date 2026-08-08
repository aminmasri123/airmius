<script setup>
defineProps({
    createModalOpen: { type: Boolean, default: false },
    editingContract: { type: Object, default: null },
    form: { type: Object, required: true },
    owners: { type: Array, default: () => [] },
    categoryOptions: { type: Array, default: () => [] },
    statusOptions: { type: Array, default: () => [] },
    intervalOptions: { type: Array, default: () => [] },
    paymentMethodOptions: { type: Array, default: () => [] },
    ownerLabel: { type: Function, required: true },
    optionLabel: { type: Function, required: true },
    submit: { type: Function, required: true },
    closeModal: { type: Function, required: true },
})
</script>

<template>
    <Teleport to="body">
        <div
            v-if="createModalOpen"
            class="fixed inset-0 z-[90] flex items-center justify-center bg-black/65 p-3 backdrop-blur-sm sm:p-4"
            @click.self="closeModal"
        >
            <form
                class="flex max-h-[calc(100dvh-1.5rem)] w-full max-w-6xl flex-col overflow-hidden rounded-2xl border border-border bg-card shadow-2xl"
                @submit.prevent="submit"
            >
                <div class="flex shrink-0 items-start justify-between gap-4 border-b border-border px-4 py-4 sm:px-6">
                    <div class="min-w-0">
                        <p class="text-xs font-semibold uppercase tracking-[0.16em] text-air-blue">Betriebskosten</p>
                        <h2 class="mt-1 text-xl font-black text-primary sm:text-2xl">
                            {{ editingContract ? 'Vertrag bearbeiten' : 'Vertrag anlegen' }}
                        </h2>
                        <p class="mt-1 text-sm text-secondary">Kosten, Zahlungsrhythmus, Laufzeit und Kündigungsfrist zentral erfassen.</p>
                    </div>
                    <button
                        type="button"
                        class="inline-flex h-10 w-10 shrink-0 items-center justify-center rounded-xl border border-border text-secondary hover:bg-muted hover:text-primary"
                        :disabled="form.processing"
                        aria-label="Modal schließen"
                        @click="closeModal"
                    >
                        <i class="las la-times text-xl"></i>
                    </button>
                </div>

                <div class="min-h-0 flex-1 overflow-y-auto p-4 sm:p-6">
                    <div class="grid gap-5 xl:grid-cols-[minmax(0,1fr)_22rem]">
                        <div class="space-y-5">
                            <section class="grid gap-4 rounded-2xl border border-border bg-inputBg p-4 md:grid-cols-2 xl:grid-cols-3">
                                <label class="block md:col-span-2">
                                    <span class="text-sm font-semibold text-primary">{{ $t('Name') }}</span>
                                    <input v-model="form.name" class="mt-1 w-full rounded-xl border-border bg-card text-primary" placeholder="WLAN Büro, Handyvertrag, Fahrzeugleasing ..." required>
                                    <p v-if="form.errors.name" class="mt-1 text-xs text-error">{{ form.errors.name }}</p>
                                </label>
                                <label class="block">
                                    <span class="text-sm font-semibold text-primary">Anbieter</span>
                                    <input v-model="form.vendor" class="mt-1 w-full rounded-xl border-border bg-card text-primary" placeholder="Telekom, Vodafone, Leasingfirma ...">
                                    <p v-if="form.errors.vendor" class="mt-1 text-xs text-error">{{ form.errors.vendor }}</p>
                                </label>
                                <label class="block">
                                    <span class="text-sm font-semibold text-primary">{{ $t('Kategorie') }}</span>
                                    <select v-model="form.category" class="mt-1 w-full rounded-xl border-border bg-card text-primary">
                                        <option v-for="category in categoryOptions" :key="category.value" :value="category.value">{{ category.label }}</option>
                                    </select>
                                    <p v-if="form.errors.category" class="mt-1 text-xs text-error">{{ form.errors.category }}</p>
                                </label>
                                <label class="block">
                                    <span class="text-sm font-semibold text-primary">Status</span>
                                    <select v-model="form.status" class="mt-1 w-full rounded-xl border-border bg-card text-primary">
                                        <option v-for="status in statusOptions" :key="status.value" :value="status.value">{{ status.label }}</option>
                                    </select>
                                    <p v-if="form.errors.status" class="mt-1 text-xs text-error">{{ form.errors.status }}</p>
                                </label>
                                <label class="block">
                                    <span class="text-sm font-semibold text-primary">Verantwortlich</span>
                                    <select v-model="form.owner_user_id" class="mt-1 w-full rounded-xl border-border bg-card text-primary">
                                        <option value="">Nicht zugewiesen</option>
                                        <option v-for="owner in owners" :key="owner.id" :value="owner.id">{{ ownerLabel(owner) }}</option>
                                    </select>
                                    <p v-if="form.errors.owner_user_id" class="mt-1 text-xs text-error">{{ form.errors.owner_user_id }}</p>
                                </label>
                            </section>

                            <section class="grid gap-4 rounded-2xl border border-border bg-inputBg p-4 md:grid-cols-2 xl:grid-cols-4">
                                <label class="block">
                                    <span class="text-sm font-semibold text-primary">Betrag</span>
                                    <input v-model="form.amount" class="mt-1 w-full rounded-xl border-border bg-card text-primary" min="0" step="0.01" type="number" required>
                                    <p v-if="form.errors.amount" class="mt-1 text-xs text-error">{{ form.errors.amount }}</p>
                                </label>
                                <label class="block">
                                    <span class="text-sm font-semibold text-primary">Währung</span>
                                    <input v-model="form.currency" class="mt-1 w-full rounded-xl border-border bg-card text-primary uppercase" maxlength="3" placeholder="EUR">
                                    <p v-if="form.errors.currency" class="mt-1 text-xs text-error">{{ form.errors.currency }}</p>
                                </label>
                                <label class="block">
                                    <span class="text-sm font-semibold text-primary">Rhythmus</span>
                                    <select v-model="form.billing_interval" class="mt-1 w-full rounded-xl border-border bg-card text-primary">
                                        <option v-for="interval in intervalOptions" :key="interval.value" :value="interval.value">{{ interval.label }}</option>
                                    </select>
                                    <p v-if="form.errors.billing_interval" class="mt-1 text-xs text-error">{{ form.errors.billing_interval }}</p>
                                </label>
                                <label class="block">
                                    <span class="text-sm font-semibold text-primary">Zahlungsart</span>
                                    <select v-model="form.payment_method" class="mt-1 w-full rounded-xl border-border bg-card text-primary">
                                        <option value="">Keine Angabe</option>
                                        <option v-for="method in paymentMethodOptions" :key="method.value" :value="method.value">{{ method.label }}</option>
                                    </select>
                                    <p v-if="form.errors.payment_method" class="mt-1 text-xs text-error">{{ form.errors.payment_method }}</p>
                                </label>
                            </section>

                            <section class="grid gap-4 rounded-2xl border border-border bg-inputBg p-4 md:grid-cols-2 xl:grid-cols-5">
                                <label class="block">
                                    <span class="text-sm font-semibold text-primary">Start</span>
                                    <input v-model="form.starts_on" class="mt-1 w-full rounded-xl border-border bg-card text-primary" type="date">
                                    <p v-if="form.errors.starts_on" class="mt-1 text-xs text-error">{{ form.errors.starts_on }}</p>
                                </label>
                                <label class="block">
                                    <span class="text-sm font-semibold text-primary">Ende</span>
                                    <input v-model="form.ends_on" class="mt-1 w-full rounded-xl border-border bg-card text-primary" type="date">
                                    <p v-if="form.errors.ends_on" class="mt-1 text-xs text-error">{{ form.errors.ends_on }}</p>
                                </label>
                                <label class="block">
                                    <span class="text-sm font-semibold text-primary">Nächste Zahlung</span>
                                    <input v-model="form.next_due_on" class="mt-1 w-full rounded-xl border-border bg-card text-primary" type="date">
                                    <p v-if="form.errors.next_due_on" class="mt-1 text-xs text-error">{{ form.errors.next_due_on }}</p>
                                </label>
                                <label class="block">
                                    <span class="text-sm font-semibold text-primary">Kündigungsfrist Tage</span>
                                    <input v-model="form.cancellation_period_days" class="mt-1 w-full rounded-xl border-border bg-card text-primary" min="0" step="1" type="number">
                                    <p v-if="form.errors.cancellation_period_days" class="mt-1 text-xs text-error">{{ form.errors.cancellation_period_days }}</p>
                                </label>
                                <label class="block">
                                    <span class="text-sm font-semibold text-primary">Kündigen bis</span>
                                    <input v-model="form.notice_until_on" class="mt-1 w-full rounded-xl border-border bg-card text-primary" type="date">
                                    <p v-if="form.errors.notice_until_on" class="mt-1 text-xs text-error">{{ form.errors.notice_until_on }}</p>
                                </label>
                                <label class="flex items-center gap-3 rounded-xl border border-border bg-card p-3 md:col-span-2">
                                    <input v-model="form.auto_renews" type="checkbox" class="rounded border-border bg-inputBg text-air-blue">
                                    <span>
                                        <span class="block text-sm font-semibold text-primary">Automatische Verlängerung</span>
                                        <span class="block text-xs text-secondary">Bei aktiven Abos und Leasingverträgen eingeschaltet lassen.</span>
                                    </span>
                                </label>
                            </section>

                            <section class="grid gap-4 rounded-2xl border border-border bg-inputBg p-4 md:grid-cols-2">
                                <label class="block">
                                    <span class="text-sm font-semibold text-primary">Vertragsnummer</span>
                                    <input v-model="form.contract_number" class="mt-1 w-full rounded-xl border-border bg-card text-primary">
                                    <p v-if="form.errors.contract_number" class="mt-1 text-xs text-error">{{ form.errors.contract_number }}</p>
                                </label>
                                <label class="block">
                                    <span class="text-sm font-semibold text-primary">Kunden-/Accountreferenz</span>
                                    <input v-model="form.account_reference" class="mt-1 w-full rounded-xl border-border bg-card text-primary">
                                    <p v-if="form.errors.account_reference" class="mt-1 text-xs text-error">{{ form.errors.account_reference }}</p>
                                </label>
                                <label class="block">
                                    <span class="text-sm font-semibold text-primary">Kontakt E-Mail</span>
                                    <input v-model="form.contact_email" class="mt-1 w-full rounded-xl border-border bg-card text-primary" type="email">
                                    <p v-if="form.errors.contact_email" class="mt-1 text-xs text-error">{{ form.errors.contact_email }}</p>
                                </label>
                                <label class="block">
                                    <span class="text-sm font-semibold text-primary">Website / Portal</span>
                                    <input v-model="form.website" class="mt-1 w-full rounded-xl border-border bg-card text-primary" :placeholder="$t('commerce.ui.url_placeholder')" type="url">
                                    <p v-if="form.errors.website" class="mt-1 text-xs text-error">{{ form.errors.website }}</p>
                                </label>
                                <label class="block md:col-span-2">
                                    <span class="text-sm font-semibold text-primary">Beleg-/Dokumentenlink</span>
                                    <input v-model="form.document_url" class="mt-1 w-full rounded-xl border-border bg-card text-primary" placeholder="Ablagepfad oder Link">
                                    <p v-if="form.errors.document_url" class="mt-1 text-xs text-error">{{ form.errors.document_url }}</p>
                                </label>
                                <label class="block md:col-span-2">
                                    <span class="text-sm font-semibold text-primary">Notizen</span>
                                    <textarea v-model="form.notes" class="mt-1 min-h-28 w-full rounded-xl border-border bg-card text-primary" placeholder="Interne Hinweise, Konditionen, Kontaktweg, Besonderheiten ..."></textarea>
                                    <p v-if="form.errors.notes" class="mt-1 text-xs text-error">{{ form.errors.notes }}</p>
                                </label>
                            </section>
                        </div>

                        <aside class="rounded-2xl border border-border bg-inputBg p-5 xl:sticky xl:top-0 xl:self-start">
                            <p class="text-xs font-semibold uppercase tracking-[0.16em] text-air-blue">Vorschau</p>
                            <h3 class="mt-2 text-lg font-black text-primary">{{ form.name || 'Neuer Vertrag' }}</h3>
                            <div class="mt-4 space-y-3 text-sm">
                                <div class="rounded-xl bg-card p-3">
                                    <p class="text-xs uppercase text-secondary">{{ $t('Kategorie') }}</p>
                                    <p class="mt-1 font-bold text-primary">{{ optionLabel(categoryOptions, form.category, '-') }}</p>
                                </div>
                                <div class="grid grid-cols-2 gap-3">
                                    <div class="rounded-xl bg-card p-3">
                                        <p class="text-xs uppercase text-secondary">Kosten</p>
                                        <p class="mt-1 font-bold text-primary">{{ form.amount || '0,00' }} {{ form.currency || 'EUR' }}</p>
                                    </div>
                                    <div class="rounded-xl bg-card p-3">
                                        <p class="text-xs uppercase text-secondary">Rhythmus</p>
                                        <p class="mt-1 font-bold text-primary">{{ optionLabel(intervalOptions, form.billing_interval, '-') }}</p>
                                    </div>
                                </div>
                                <div class="rounded-xl bg-card p-3">
                                    <p class="text-xs uppercase text-secondary">Nächste Zahlung</p>
                                    <p class="mt-1 font-bold text-primary">{{ form.next_due_on || '-' }}</p>
                                </div>
                                <div class="rounded-xl bg-card p-3">
                                    <p class="text-xs uppercase text-secondary">Kündigen bis</p>
                                    <p class="mt-1 font-bold text-primary">{{ form.notice_until_on || 'Automatisch aus Ende minus Frist' }}</p>
                                </div>
                            </div>
                        </aside>
                    </div>
                </div>

                <div class="flex shrink-0 flex-col-reverse gap-3 border-t border-border px-4 py-4 sm:flex-row sm:justify-end sm:px-6">
                    <button type="button" class="rounded-xl border border-border px-5 py-3 text-sm font-bold text-primary hover:bg-muted" :disabled="form.processing" @click="closeModal">
                        Abbrechen
                    </button>
                    <button
                        type="submit"
                        class="inline-flex items-center justify-center gap-2 rounded-xl bg-buttonPrimary px-5 py-3 text-sm font-black text-buttonTextPrimary disabled:cursor-not-allowed disabled:opacity-50"
                        :disabled="form.processing"
                    >
                        <i class="las la-save text-lg"></i>
                        {{ editingContract ? 'Speichern' : 'Anlegen' }}
                    </button>
                </div>
            </form>
        </div>
    </Teleport>
</template>
