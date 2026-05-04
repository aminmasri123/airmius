<script setup>
import AppLayout from '@/Components/Auth/Layouts/AppLayout.vue'
import { Head, useForm, usePage } from '@inertiajs/vue3'
import { computed, ref } from 'vue'

defineOptions({ layout: AppLayout })

const props = defineProps({
    settings: {
        type: Object,
        default: () => ({}),
    },
})

const page = usePage()
const maintenance = computed(() => props.settings.maintenance || {})
const billing = computed(() => props.settings.billing || {})
const emailTemplates = computed(() => props.settings.email_templates || [])
const activeEmailKey = ref(emailTemplates.value[0]?.key || null)
const activeEmailTemplate = computed(() => emailTemplates.value.find((template) => template.key === activeEmailKey.value) || emailTemplates.value[0])

const initialEmailTemplates = () => Object.fromEntries(emailTemplates.value.map((template) => [
    template.key,
    {
        subject: template.template.subject || '',
        greeting: template.template.greeting || '',
        body: template.template.body || '',
        action_label: template.template.action_label || '',
    },
]))

const placeholderFor = (variable) => `{{ ${variable} }}`

const form = useForm({
    maintenance_enabled: Boolean(maintenance.value.enabled),
    maintenance_title: maintenance.value.title || 'Airmius ist gerade im Wartemodus',
    maintenance_message: maintenance.value.message || 'Wir verbessern gerade die Plattform. Bitte versuche es in Kuerze erneut.',
    billing_bank_account_holder: billing.value.bank_account_holder || 'Airmius',
    billing_bank_name: billing.value.bank_name || '',
    billing_iban: billing.value.iban || '',
    billing_bic: billing.value.bic || '',
    billing_payment_terms_days: billing.value.payment_terms_days || 14,
    email_templates: initialEmailTemplates(),
})

const save = () => {
    form.put(route('admin.settings.update'), {
        preserveScroll: true,
    })
}
</script>

<template>
    <Head title="Systemeinstellungen" />

    <div class="space-y-5">
        <section class="surface-card overflow-hidden">
            <div class="border-b border-border p-5">
                <p class="text-xs font-semibold uppercase tracking-wide text-secondary">Admin</p>
                <h1 class="mt-1 text-2xl font-semibold text-primary">Systemeinstellungen</h1>
                <p class="mt-2 max-w-2xl text-sm text-secondary">
                    Steuere zentrale Plattformfunktionen, die sofort für alle Benutzer wirken.
                </p>
            </div>

            <div class="grid gap-0 lg:grid-cols-[1fr_22rem]">
                <form class="space-y-5 p-5" @submit.prevent="save">
                    <div class="rounded-lg border border-border bg-bg p-4">
                        <div class="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
                            <div>
                                <h2 class="text-lg font-semibold text-primary">Wartemodus</h2>
                                <p class="mt-1 text-sm text-secondary">
                                    Wenn aktiv, sehen alle nicht berechtigten Benutzer nur die Warteseite.
                                </p>
                            </div>

                            <label class="inline-flex cursor-pointer items-center gap-3">
                                <span class="text-sm font-semibold text-secondary">
                                    {{ form.maintenance_enabled ? 'Aktiv' : 'Inaktiv' }}
                                </span>
                                <input v-model="form.maintenance_enabled" type="checkbox" class="peer sr-only">
                                <span
                                    class="relative h-7 w-12 rounded-full transition"
                                    :class="form.maintenance_enabled ? 'bg-buttonPrimary' : 'bg-muted'"
                                >
                                    <span
                                        class="absolute left-1 top-1 h-5 w-5 rounded-full bg-white shadow transition"
                                        :class="{ 'translate-x-5': form.maintenance_enabled }"
                                    ></span>
                                </span>
                            </label>
                        </div>
                    </div>

                    <div class="grid gap-4">
                        <div>
                            <label for="maintenance_title" class="text-sm font-semibold text-primary">Titel</label>
                            <input
                                id="maintenance_title"
                                v-model="form.maintenance_title"
                                type="text"
                                class="mt-1 block w-full rounded-lg border-border bg-inputBg text-primary"
                                maxlength="120"
                                required
                            >
                            <p v-if="form.errors.maintenance_title" class="mt-1 text-sm text-error">
                                {{ form.errors.maintenance_title }}
                            </p>
                        </div>

                        <div>
                            <label for="maintenance_message" class="text-sm font-semibold text-primary">Nachricht</label>
                            <textarea
                                id="maintenance_message"
                                v-model="form.maintenance_message"
                                rows="4"
                                class="mt-1 block w-full rounded-lg border-border bg-inputBg text-primary"
                                maxlength="500"
                                required
                            />
                            <p v-if="form.errors.maintenance_message" class="mt-1 text-sm text-error">
                                {{ form.errors.maintenance_message }}
                            </p>
                        </div>
                    </div>

                    <div class="rounded-lg border border-border bg-bg p-4">
                        <div class="flex flex-col gap-2 sm:flex-row sm:items-start sm:justify-between">
                            <div>
                                <h2 class="text-lg font-semibold text-primary">Airmius Zahlung per Überweisung</h2>
                                <p class="mt-1 text-sm text-secondary">
                                    Diese Bankdaten werden bei Abo-Zahlung per Rechnung/Überweisung angezeigt.
                                </p>
                            </div>
                            <span class="rounded-full bg-air-blue/15 px-3 py-1 text-xs font-semibold text-air-blue">
                                Rechnung
                            </span>
                        </div>

                        <div class="mt-4 grid gap-4 sm:grid-cols-2">
                            <div>
                                <label for="billing_bank_account_holder" class="text-sm font-semibold text-primary">Kontoinhaber</label>
                                <input id="billing_bank_account_holder" v-model="form.billing_bank_account_holder" type="text" class="mt-1 block w-full rounded-lg border-border bg-inputBg text-primary">
                                <p v-if="form.errors.billing_bank_account_holder" class="mt-1 text-sm text-error">
                                    {{ form.errors.billing_bank_account_holder }}
                                </p>
                            </div>

                            <div>
                                <label for="billing_bank_name" class="text-sm font-semibold text-primary">Bank</label>
                                <input id="billing_bank_name" v-model="form.billing_bank_name" type="text" class="mt-1 block w-full rounded-lg border-border bg-inputBg text-primary">
                                <p v-if="form.errors.billing_bank_name" class="mt-1 text-sm text-error">
                                    {{ form.errors.billing_bank_name }}
                                </p>
                            </div>

                            <div>
                                <label for="billing_iban" class="text-sm font-semibold text-primary">IBAN</label>
                                <input id="billing_iban" v-model="form.billing_iban" type="text" class="mt-1 block w-full rounded-lg border-border bg-inputBg text-primary" placeholder="DE...">
                                <p v-if="form.errors.billing_iban" class="mt-1 text-sm text-error">
                                    {{ form.errors.billing_iban }}
                                </p>
                            </div>

                            <div>
                                <label for="billing_bic" class="text-sm font-semibold text-primary">BIC</label>
                                <input id="billing_bic" v-model="form.billing_bic" type="text" class="mt-1 block w-full rounded-lg border-border bg-inputBg text-primary">
                                <p v-if="form.errors.billing_bic" class="mt-1 text-sm text-error">
                                    {{ form.errors.billing_bic }}
                                </p>
                            </div>

                            <div>
                                <label for="billing_payment_terms_days" class="text-sm font-semibold text-primary">Zahlungsziel in Tagen</label>
                                <input id="billing_payment_terms_days" v-model="form.billing_payment_terms_days" type="number" min="1" max="60" class="mt-1 block w-full rounded-lg border-border bg-inputBg text-primary">
                                <p v-if="form.errors.billing_payment_terms_days" class="mt-1 text-sm text-error">
                                    {{ form.errors.billing_payment_terms_days }}
                                </p>
                            </div>
                        </div>
                    </div>

                    <div class="rounded-lg border border-border bg-bg p-4">
                        <div class="flex flex-col gap-2 sm:flex-row sm:items-start sm:justify-between">
                            <div>
                                <h2 class="text-lg font-semibold text-primary">E-Mail-Vorlagen</h2>
                                <p class="mt-1 text-sm text-secondary">
                                    Bearbeite Betreff, Anrede, Inhalt und Buttontexte aller System-E-Mails.
                                </p>
                            </div>
                            <span class="rounded-full bg-air-blue/15 px-3 py-1 text-xs font-semibold text-air-blue">
                                {{ emailTemplates.length }} Vorlagen
                            </span>
                        </div>

                        <div class="mt-4 grid gap-4 xl:grid-cols-[18rem_1fr]">
                            <div class="max-h-[34rem] space-y-2 overflow-y-auto pr-1 custom-scrollbar">
                                <button
                                    v-for="template in emailTemplates"
                                    :key="template.key"
                                    type="button"
                                    class="w-full rounded-lg border px-3 py-2 text-left transition"
                                    :class="activeEmailKey === template.key ? 'border-buttonPrimary bg-buttonPrimary/10 text-primary' : 'border-border bg-card text-secondary hover:border-borderHover'"
                                    @click="activeEmailKey = template.key"
                                >
                                    <span class="block text-sm font-semibold">{{ template.label }}</span>
                                    <span class="mt-1 block text-xs leading-5">{{ template.description }}</span>
                                </button>
                            </div>

                            <div v-if="activeEmailTemplate && form.email_templates[activeEmailTemplate.key]" class="space-y-4 rounded-lg border border-border bg-card p-4">
                                <div>
                                    <p class="text-xs font-semibold uppercase tracking-wide text-secondary">Aktive Vorlage</p>
                                    <h3 class="mt-1 text-lg font-semibold text-primary">{{ activeEmailTemplate.label }}</h3>
                                    <p class="mt-1 text-sm text-secondary">{{ activeEmailTemplate.description }}</p>
                                </div>

                                <div class="grid gap-4 md:grid-cols-2">
                                    <div>
                                        <label class="text-sm font-semibold text-primary">Betreff</label>
                                        <input
                                            v-model="form.email_templates[activeEmailTemplate.key].subject"
                                            type="text"
                                            class="mt-1 block w-full rounded-lg border-border bg-inputBg text-primary"
                                        >
                                    </div>

                                    <div>
                                        <label class="text-sm font-semibold text-primary">Anrede</label>
                                        <input
                                            v-model="form.email_templates[activeEmailTemplate.key].greeting"
                                            type="text"
                                            class="mt-1 block w-full rounded-lg border-border bg-inputBg text-primary"
                                        >
                                    </div>
                                </div>

                                <div>
                                    <label class="text-sm font-semibold text-primary">Inhalt</label>
                                    <textarea
                                        v-model="form.email_templates[activeEmailTemplate.key].body"
                                        rows="10"
                                        class="mt-1 block w-full rounded-lg border-border bg-inputBg font-mono text-sm text-primary"
                                    />
                                    <p class="mt-1 text-xs text-secondary">
                                        Jede neue Zeile wird als eigener Absatz in der E-Mail ausgegeben.
                                    </p>
                                </div>

                                <div>
                                    <label class="text-sm font-semibold text-primary">Buttontext</label>
                                    <input
                                        v-model="form.email_templates[activeEmailTemplate.key].action_label"
                                        type="text"
                                        class="mt-1 block w-full rounded-lg border-border bg-inputBg text-primary"
                                        placeholder="Leer lassen, wenn diese E-Mail keinen Button hat"
                                    >
                                </div>

                                <div class="rounded-lg border border-border bg-bg p-3">
                                    <p class="text-xs font-semibold uppercase tracking-wide text-secondary">Platzhalter</p>
                                    <div class="mt-2 flex flex-wrap gap-2">
                                        <span
                                            v-for="variable in activeEmailTemplate.variables"
                                            :key="variable"
                                            class="rounded-lg border border-border bg-card px-2 py-1 font-mono text-xs text-primary"
                                        >
                                            {{ placeholderFor(variable) }}
                                        </span>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div v-if="page.props.flash?.success" class="rounded-lg border border-success/30 bg-success/10 px-4 py-3 text-sm text-success">
                        {{ page.props.flash.success }}
                    </div>

                    <div class="flex justify-end">
                        <button
                            type="submit"
                            class="btn-primary"
                            :class="{ 'opacity-60': form.processing }"
                            :disabled="form.processing"
                        >
                            Speichern
                        </button>
                    </div>
                </form>

                <aside class="border-t border-border bg-bg p-5 lg:border-l lg:border-t-0">
                    <div class="rounded-lg border border-border bg-card p-4">
                        <p class="text-xs font-semibold uppercase tracking-wide text-secondary">Vorschau</p>
                        <div class="mt-4 rounded-lg border border-border bg-bg p-5">
                            <div class="flex h-12 w-12 items-center justify-center rounded-lg bg-buttonPrimary text-buttonTextPrimary">
                                <i class="las la-tools text-2xl"></i>
                            </div>
                            <h3 class="mt-4 text-lg font-semibold text-primary">
                                {{ form.maintenance_title }}
                            </h3>
                            <p class="mt-2 text-sm leading-6 text-secondary">
                                {{ form.maintenance_message }}
                            </p>
                            <div class="mt-5 h-2 overflow-hidden rounded-full bg-muted">
                                <div class="h-full w-2/3 rounded-full bg-buttonPrimary"></div>
                            </div>
                        </div>
                    </div>

                    <div class="mt-4 rounded-lg border border-border bg-card p-4 text-sm text-secondary">
                        Benutzer mit der Berechtigung <span class="font-semibold text-primary">system.manage</span>
                        können Airmius weiterhin normal verwenden.
                    </div>

                    <div class="mt-4 rounded-lg border border-border bg-card p-4 text-sm text-secondary">
                        <p class="font-semibold text-primary">Überweisungsdaten</p>
                        <dl class="mt-3 space-y-2">
                            <div class="flex justify-between gap-3">
                                <dt>Kontoinhaber</dt>
                                <dd class="text-right text-primary">{{ form.billing_bank_account_holder || '-' }}</dd>
                            </div>
                            <div class="flex justify-between gap-3">
                                <dt>IBAN</dt>
                                <dd class="text-right text-primary">{{ form.billing_iban || '-' }}</dd>
                            </div>
                            <div class="flex justify-between gap-3">
                                <dt>Zahlungsziel</dt>
                                <dd class="text-right text-primary">{{ form.billing_payment_terms_days }} Tage</dd>
                            </div>
                        </dl>
                    </div>
                </aside>
            </div>
        </section>
    </div>
</template>
