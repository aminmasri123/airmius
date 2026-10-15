<script setup>
import AppLayout from '@/Components/Auth/Layouts/AppLayout.vue'
import { Head, Link, useForm } from '@inertiajs/vue3'
import { computed } from 'vue'

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
}[status.value] || 'Noch nicht eingereicht'))

const submit = () => form.post(route('auth.role-applications.store'), {
    preserveScroll: true,
    onSuccess: () => {
        form.application_data.legal_accuracy = false
        form.application_data.data_privacy = false
    },
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

        <form v-else class="space-y-5" @submit.prevent="submit">
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
                    <label class="block">
                        <span class="text-sm font-semibold text-primary">Land *</span>
                        <input v-model="form.application_data.country_code" required minlength="2" maxlength="2" class="input mt-1 uppercase" placeholder="DE">
                    </label>
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
