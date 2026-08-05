<script setup>
import AppLayout from '@/Components/Auth/Layouts/AppLayout.vue'
import { Head, Link, useForm } from '@inertiajs/vue3'
import { computed, ref } from 'vue'

defineOptions({ layout: AppLayout })

const props = defineProps({
    categories: {
        type: Array,
        default: () => [],
    },
    usesSocialLogin: Boolean,
    accountEmail: {
        type: String,
        default: '',
    },
})

const codeSent = ref(false)
const form = useForm({
    identity: '',
    categories: [],
    code: '',
})

const hasAllCategories = computed(() => form.categories.length === props.categories.length)
const identityLabel = computed(() => props.usesSocialLogin ? 'E-Mail-Adresse zur Bestätigung' : 'Aktuelles Passwort zur Bestätigung')
const identityHint = computed(() => props.usesSocialLogin
    ? `Du meldest dich mit einem verbundenen Konto an. Gib zur Bestätigung ${props.accountEmail || 'deine Konto-E-Mail-Adresse'} ein.`
    : 'Gib dein aktuelles Passwort ein. Es wird nur zur Bestätigung dieser Löschanfrage verwendet.')

const toggleAll = () => {
    form.categories = hasAllCategories.value ? [] : props.categories.map((category) => category.key)
    form.clearErrors('categories')
}

const requestCode = () => {
    if (!form.categories.length) {
        form.setError('categories', 'Wähle mindestens einen Datenbereich aus.')
        return
    }

    form.post(route('auth.settings.privacy.erasure.code'), {
        preserveScroll: true,
        onSuccess: () => {
            codeSent.value = true
            form.clearErrors('code')
        },
    })
}

const eraseData = () => {
    if (!form.categories.length) {
        form.setError('categories', 'Wähle mindestens einen Datenbereich aus.')
        return
    }

    form.post(route('auth.settings.privacy.erasure.destroy'), {
        preserveScroll: true,
        onSuccess: () => {
            codeSent.value = false
            form.reset('identity', 'categories', 'code')
        },
    })
}
</script>

<template>
    <Head title="Daten löschen" />

    <div class="mx-auto max-w-4xl space-y-5 p-4 sm:p-6">
        <div class="flex flex-wrap items-start justify-between gap-4">
            <div>
                <p class="text-sm font-semibold uppercase tracking-wide text-secondary">Datenschutz</p>
                <h1 class="mt-1 text-2xl font-bold text-primary sm:text-3xl">Daten löschen, Konto behalten</h1>
                <p class="mt-2 max-w-3xl text-sm leading-6 text-secondary">
                    Entferne gezielt persönliche Daten aus Airmius. Dein Konto, deine E-Mail-Adresse und deine Zugangsdaten bleiben bestehen.
                </p>
            </div>
            <Link :href="route('auth.settings')" class="btn-secondary">Zurück zu Einstellungen</Link>
        </div>

        <div class="rounded-xl border border-error/40 bg-error/10 p-4 text-sm text-primary">
            <p class="font-semibold text-error">Diese Aktion kann nicht rückgängig gemacht werden.</p>
            <p class="mt-1 leading-6 text-secondary">
                Gelöschte Inhalte, Medien und Daten können nicht wiederhergestellt werden. Sichere zuerst alles, was du behalten möchtest.
            </p>
        </div>

        <section class="surface-card p-5">
            <div class="flex flex-wrap items-start justify-between gap-3">
                <div>
                    <h2 class="text-lg font-semibold text-primary">1. Datenbereiche auswählen</h2>
                    <p class="mt-1 text-sm text-secondary">Du kannst einzelne Bereiche wählen oder alles auswählen.</p>
                </div>
                <button type="button" class="btn-secondary" :disabled="form.processing" @click="toggleAll">
                    {{ hasAllCategories ? 'Auswahl aufheben' : 'Alle Bereiche auswählen' }}
                </button>
            </div>

            <p v-if="form.errors.categories" class="mt-3 text-sm text-error">{{ form.errors.categories }}</p>

            <div class="mt-4 space-y-3">
                <label
                    v-for="category in categories"
                    :key="category.key"
                    class="flex cursor-pointer items-start gap-3 rounded-xl border border-border bg-bg p-4 transition hover:border-buttonPrimary/60"
                    :class="form.categories.includes(category.key) ? 'border-buttonPrimary bg-buttonPrimary/5' : ''"
                >
                    <input v-model="form.categories" :value="category.key" type="checkbox" class="mt-1 rounded border-border bg-inputBg">
                    <span>
                        <span class="block font-semibold text-primary">{{ category.label }}</span>
                        <span class="mt-1 block text-sm leading-6 text-secondary">{{ category.description }}</span>
                    </span>
                </label>
            </div>
        </section>

        <section class="surface-card p-5">
            <h2 class="text-lg font-semibold text-primary">2. Anfrage bestätigen</h2>
            <p class="mt-1 text-sm leading-6 text-secondary">
                {{ identityHint }} Danach senden wir einen einmaligen Code an deine Konto-E-Mail-Adresse. Der Code ist 15 Minuten gültig und gilt nur für die aktuell ausgewählten Bereiche.
            </p>

            <div class="mt-4 max-w-xl">
                <label class="block text-sm font-semibold text-primary" for="data-erasure-identity">{{ identityLabel }}</label>
                <input
                    id="data-erasure-identity"
                    v-model="form.identity"
                    :type="usesSocialLogin ? 'email' : 'password'"
                    :autocomplete="usesSocialLogin ? 'email' : 'current-password'"
                    class="input mt-2"
                    :placeholder="usesSocialLogin ? accountEmail : 'Passwort eingeben'"
                    :disabled="form.processing"
                    @input="form.clearErrors('identity')"
                >
                <p v-if="form.errors.identity" class="mt-2 text-sm text-error">{{ form.errors.identity }}</p>
            </div>

            <div class="mt-4 flex flex-wrap gap-3">
                <button type="button" class="btn-primary" :disabled="form.processing" @click="requestCode">
                    {{ codeSent ? 'Neuen Bestätigungscode senden' : 'Bestätigungscode senden' }}
                </button>
            </div>
        </section>

        <section v-if="codeSent" class="surface-card border border-warning/30 p-5">
            <h2 class="text-lg font-semibold text-primary">3. Löschung ausführen</h2>
            <p class="mt-1 text-sm leading-6 text-secondary">
                Gib den Code aus der E-Mail ein. Mit der Bestätigung werden die ausgewählten Daten sofort gelöscht beziehungsweise anonymisiert.
            </p>

            <div class="mt-4 max-w-sm">
                <label class="block text-sm font-semibold text-primary" for="data-erasure-code">Bestätigungscode</label>
                <input
                    id="data-erasure-code"
                    v-model="form.code"
                    type="text"
                    inputmode="numeric"
                    autocomplete="one-time-code"
                    maxlength="6"
                    class="input mt-2 tracking-[0.35em]"
                    placeholder="123456"
                    :disabled="form.processing"
                    @input="form.clearErrors('code')"
                >
                <p v-if="form.errors.code" class="mt-2 text-sm text-error">{{ form.errors.code }}</p>
            </div>

            <button type="button" class="mt-4 rounded-lg bg-error px-4 py-2 text-sm font-semibold text-white transition hover:bg-error/90 disabled:cursor-not-allowed disabled:opacity-60" :disabled="form.processing || !form.code" @click="eraseData">
                Ausgewählte Daten endgültig löschen
            </button>
        </section>

        <section class="rounded-xl border border-border bg-bg p-5 text-sm leading-6 text-secondary">
            <h2 class="font-semibold text-primary">Daten, die erhalten bleiben können</h2>
            <ul class="mt-2 list-disc space-y-1 pl-5">
                <li>Rechnungen, Zahlungen, Abonnements, offene Bestellungen sowie gesetzlich erforderliche Nachweise.</li>
                <li>Vereins-, Team- und Berechtigungszuordnungen, damit keine Daten anderer Personen oder Organisationen verloren gehen.</li>
                <li>Die technische Zuordnung eines Google- oder Microsoft-Logins, wenn du diese Anmeldung weiterhin verwendest.</li>
            </ul>
            <Link :href="route('legal.data-erasure')" class="mt-3 inline-block text-buttonPrimary hover:underline">Öffentliche Informationen zur Datenlöschung</Link>
        </section>
    </div>
</template>
