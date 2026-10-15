<script setup>
import AppLayout from '@/Components/Auth/Layouts/AppLayout.vue'
import CountrySelect from '@/Components/CountrySelect.vue'
import { Head, Link, useForm } from '@inertiajs/vue3'
import { computed, ref } from 'vue'

defineOptions({ layout: AppLayout })

const props = defineProps({
    application: { type: Object, default: null },
    hasSponsorRole: Boolean,
})

const existing = props.application?.application_data || {}
const form = useForm({
    type: 'sponsor',
    message: props.application?.message || '',
    application_data: {
        company_name: existing.company_name || '',
        legal_name: existing.legal_name || '',
        website: existing.website || '',
        country_code: existing.country_code || 'DE',
        contact_name: existing.contact_name || '',
        contact_email: existing.contact_email || '',
        phone: existing.phone || '',
        interests: existing.interests || '',
        goals: existing.goals || '',
        legal_accuracy: false,
        data_privacy: false,
    },
})

const status = computed(() => props.hasSponsorRole ? 'approved' : props.application?.status)
const canSubmit = computed(() => status.value !== 'approved')
const statusLabel = computed(() => ({
    pending: 'Prüfung läuft',
    approved: 'Freigegeben',
    rejected: 'Überarbeitung möglich',
    withdrawn: 'Zurückgezogen',
}[status.value] || 'Noch nicht eingereicht'))
const withdrawForm = useForm({})
const confirmWithdrawal = ref(false)
const requestedAt = computed(() => props.application?.requested_at
    ? new Intl.DateTimeFormat('de-DE', { dateStyle: 'medium', timeStyle: 'short' }).format(new Date(props.application.requested_at))
    : null)

const submit = () => form.post(route('auth.role-applications.store'), {
    preserveScroll: true,
    onSuccess: () => {
        form.application_data.legal_accuracy = false
        form.application_data.data_privacy = false
    },
})
const withdraw = () => withdrawForm.delete(route('auth.role-applications.withdraw', props.application.id), {
    preserveScroll: true,
    onSuccess: () => { confirmWithdrawal.value = false },
})
</script>

<template>
    <Head title="Sponsor werden" />

    <div class="mx-auto max-w-5xl space-y-5">
        <section class="surface-card p-5 sm:p-6">
            <div class="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
                <div>
                    <p class="text-sm font-semibold uppercase text-air-blue">Sponsor-Partnerschaft</p>
                    <h1 class="mt-2 text-2xl font-bold text-primary">Sponsor werden</h1>
                    <p class="mt-2 max-w-2xl text-sm leading-6 text-secondary">
                        Stelle deinen Antrag in wenigen Schritten. Nach der Prüfung wird dein Sponsor-Cockpit automatisch freigeschaltet.
                    </p>
                </div>
                <span class="rounded-lg border border-border bg-inputBg px-3 py-2 text-sm font-semibold text-primary">
                    {{ statusLabel }}
                </span>
            </div>
        </section>

        <section v-if="status === 'approved'" class="surface-card p-6">
            <h2 class="text-lg font-semibold text-primary">Dein Sponsorzugang ist aktiv</h2>
            <p class="mt-2 text-sm text-secondary">Vervollständige jetzt dein Markenprofil und starte deine erste Partnerschaft.</p>
            <Link :href="route('auth.sponsor-workspace.index')" class="mt-4 inline-flex rounded-lg bg-buttonPrimary px-4 py-3 text-sm font-semibold text-buttonTextPrimary">
                Sponsor-Cockpit öffnen
            </Link>
        </section>

        <section v-else-if="application" class="surface-card p-5 sm:p-6">
            <div class="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
                <div>
                    <p class="text-sm font-semibold uppercase text-air-blue">Antragsstatus</p>
                    <h2 class="mt-2 text-xl font-bold text-primary">{{ statusLabel }}</h2>
                    <p v-if="requestedAt" class="mt-2 text-sm text-secondary">Eingereicht am {{ requestedAt }}</p>
                    <p v-if="status === 'pending'" class="mt-2 max-w-2xl text-sm leading-6 text-secondary">Deine Angaben werden geprüft. Änderungen kannst du unten direkt speichern.</p>
                    <p v-else-if="status === 'rejected'" class="mt-2 max-w-2xl text-sm leading-6 text-secondary">Prüfe den Hinweis, passe deine Angaben an und reiche den Antrag erneut ein.</p>
                    <p v-else-if="status === 'withdrawn'" class="mt-2 max-w-2xl text-sm leading-6 text-secondary">Der Antrag wird nicht weiter geprüft. Du kannst mit aktualisierten Angaben einen neuen Antrag einreichen.</p>
                </div>
                <span class="rounded-lg border border-border bg-inputBg px-3 py-2 text-sm font-semibold text-primary">{{ statusLabel }}</span>
            </div>
            <div v-if="status === 'pending'" class="mt-5 border-t border-border pt-5">
                <button v-if="!confirmWithdrawal" type="button" class="text-sm font-semibold text-error hover:underline" @click="confirmWithdrawal = true">Antrag zurückziehen</button>
                <div v-else class="rounded-lg border border-error/30 bg-error/10 p-4">
                    <p class="text-sm font-semibold text-primary">Sponsor-Antrag wirklich zurückziehen?</p>
                    <p class="mt-1 text-sm text-secondary">Die Prüfung wird beendet. Später kannst du einen neuen Antrag stellen.</p>
                    <div class="mt-4 flex flex-wrap gap-3">
                        <button type="button" class="rounded-lg border border-border px-4 py-2 text-sm font-semibold text-primary" :disabled="withdrawForm.processing" @click="confirmWithdrawal = false">Behalten</button>
                        <button type="button" class="rounded-lg bg-error px-4 py-2 text-sm font-semibold text-white disabled:opacity-60" :disabled="withdrawForm.processing" @click="withdraw">{{ withdrawForm.processing ? 'Wird zurückgezogen...' : 'Jetzt zurückziehen' }}</button>
                    </div>
                </div>
            </div>
        </section>

        <form v-if="status !== 'approved'" class="space-y-5" @submit.prevent="submit">
            <section class="surface-card p-5 sm:p-6">
                <h2 class="text-lg font-semibold text-primary">1. Unternehmen</h2>
                <div class="mt-4 grid gap-4 sm:grid-cols-2">
                    <label class="block">
                        <span class="text-sm font-semibold text-primary">Markenname *</span>
                        <input v-model="form.application_data.company_name" required maxlength="255" class="input mt-1" autocomplete="organization">
                        <span v-if="form.errors['application_data.company_name']" class="mt-1 block text-sm text-error">{{ form.errors['application_data.company_name'] }}</span>
                    </label>
                    <label class="block">
                        <span class="text-sm font-semibold text-primary">Rechtlicher Unternehmensname</span>
                        <input v-model="form.application_data.legal_name" maxlength="255" class="input mt-1">
                    </label>
                    <label class="block">
                        <span class="text-sm font-semibold text-primary">Website</span>
                        <input v-model="form.application_data.website" type="url" maxlength="2048" class="input mt-1" placeholder="https://">
                        <span v-if="form.errors['application_data.website']" class="mt-1 block text-sm text-error">{{ form.errors['application_data.website'] }}</span>
                    </label>
                    <div class="block">
                        <span class="text-sm font-semibold text-primary">Land *</span>
                        <CountrySelect v-model="form.application_data.country_code" label="Land" required />
                        <span v-if="form.errors['application_data.country_code']" class="mt-1 block text-sm text-error">{{ form.errors['application_data.country_code'] }}</span>
                    </div>
                </div>
            </section>

            <section class="surface-card p-5 sm:p-6">
                <h2 class="text-lg font-semibold text-primary">2. Kontakt</h2>
                <div class="mt-4 grid gap-4 sm:grid-cols-2">
                    <label class="block">
                        <span class="text-sm font-semibold text-primary">Ansprechperson *</span>
                        <input v-model="form.application_data.contact_name" required maxlength="255" class="input mt-1" autocomplete="name">
                    </label>
                    <label class="block">
                        <span class="text-sm font-semibold text-primary">E-Mail *</span>
                        <input v-model="form.application_data.contact_email" required type="email" maxlength="255" class="input mt-1" autocomplete="email">
                    </label>
                    <label class="block sm:col-span-2">
                        <span class="text-sm font-semibold text-primary">Telefon</span>
                        <input v-model="form.application_data.phone" type="tel" maxlength="80" class="input mt-1" autocomplete="tel">
                    </label>
                </div>
            </section>

            <section class="surface-card p-5 sm:p-6">
                <h2 class="text-lg font-semibold text-primary">3. Sponsoringinteresse</h2>
                <div class="mt-4 grid gap-4">
                    <label class="block">
                        <span class="text-sm font-semibold text-primary">Sportarten, Regionen oder Vereine</span>
                        <textarea v-model="form.application_data.interests" rows="3" maxlength="1000" class="input mt-1" placeholder="Wofür interessierst du dich?"></textarea>
                    </label>
                    <label class="block">
                        <span class="text-sm font-semibold text-primary">Ziele und gewünschte Zusammenarbeit</span>
                        <textarea v-model="form.application_data.goals" rows="4" maxlength="2000" class="input mt-1" placeholder="Was möchtest du gemeinsam erreichen?"></textarea>
                    </label>
                    <label class="block">
                        <span class="text-sm font-semibold text-primary">Zusätzliche Nachricht</span>
                        <textarea v-model="form.message" rows="3" maxlength="2000" class="input mt-1"></textarea>
                    </label>
                    <label class="flex items-start gap-3 text-sm text-primary">
                        <input v-model="form.application_data.legal_accuracy" required type="checkbox" class="mt-1 rounded border-border">
                        <span>Ich bestätige, dass die Unternehmens- und Kontaktangaben richtig sind.</span>
                    </label>
                    <label class="flex items-start gap-3 text-sm text-primary">
                        <input v-model="form.application_data.data_privacy" required type="checkbox" class="mt-1 rounded border-border">
                        <span>Ich stimme der Verarbeitung der Angaben zur Prüfung des Sponsor-Antrags zu.</span>
                    </label>
                </div>
            </section>

            <section v-if="application?.review_notes" class="rounded-lg border border-warning/30 bg-warning/10 p-4 text-sm text-primary">
                <strong>Hinweis der Prüfung:</strong> {{ application.review_notes }}
            </section>

            <div class="flex flex-col-reverse gap-3 sm:flex-row sm:justify-end">
                <Link :href="route('guest.sponsors')" class="rounded-lg border border-border px-5 py-3 text-center text-sm font-semibold text-primary">Abbrechen</Link>
                <button type="submit" class="rounded-lg bg-buttonPrimary px-5 py-3 text-sm font-semibold text-buttonTextPrimary disabled:opacity-60" :disabled="form.processing || !canSubmit">
                    {{ form.processing ? 'Wird gesendet...' : (status === 'pending' ? 'Antrag aktualisieren' : 'Antrag einreichen') }}
                </button>
            </div>
        </form>
    </div>
</template>
