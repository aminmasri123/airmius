<script setup>
defineProps({
    form: { type: Object, required: true },
})

const billingFields = [
    { model: 'billing_brand_name', label: 'Markenname für PDF-Header', placeholder: 'Airmius', maxlength: 120 },
    { model: 'billing_company_name', label: 'Marke / Rechnungsname', placeholder: 'Airmius' },
    { model: 'billing_legal_name', label: 'Rechtlicher Firmenname', placeholder: 'z. B. Airmius GmbH' },
    { model: 'billing_company_street', label: 'Straße und Hausnummer' },
    { model: 'billing_company_postal_code', label: 'PLZ' },
    { model: 'billing_company_city', label: 'Ort' },
    { model: 'billing_company_country', label: 'Land' },
    { model: 'billing_company_email', label: 'Rechnungs-E-Mail', type: 'email' },
    { model: 'billing_company_website', label: 'Website' },
    { model: 'billing_managing_director', label: 'Vertreten durch' },
    { model: 'billing_tax_number', label: 'Steuernummer' },
    { model: 'billing_vat_id', label: 'USt-IdNr.', placeholder: 'DE...' },
    { model: 'billing_court', label: 'Registergericht' },
    { model: 'billing_registration_number', label: 'Registernummer' },
    { model: 'billing_small_business_notice', label: 'Kleinunternehmer- / Steuerhinweis', placeholder: 'z. B. Gem. § 19 UStG wird keine Umsatzsteuer berechnet.', wide: true },
    { model: 'billing_invoice_note', label: 'Allgemeiner Rechnungshinweis', wide: true },
]
</script>

<template>
    <div class="rounded-lg border border-border bg-bg p-4">
        <div class="flex flex-col gap-2 sm:flex-row sm:items-start sm:justify-between">
            <div>
                <h2 class="text-lg font-semibold text-primary">Rechtliche Rechnungsdaten</h2>
                <p class="mt-1 text-sm text-secondary">
                    Diese Angaben erscheinen auf allen Airmius-PDFs. Leere Felder werden im PDF als Platzhalter markiert.
                </p>
            </div>
            <span class="rounded-full bg-air-orange/15 px-3 py-1 text-xs font-semibold text-air-orange">
                PDF
            </span>
        </div>

        <div class="mt-4 grid gap-4 sm:grid-cols-2">
            <div
                v-for="field in billingFields"
                :key="field.model"
                :class="{ 'sm:col-span-2': field.wide }"
            >
                <label :for="field.model" class="text-sm font-semibold text-primary">{{ field.label }}</label>
                <input
                    :id="field.model"
                    v-model="form[field.model]"
                    :type="field.type || 'text'"
                    class="mt-1 block w-full rounded-lg border-border bg-inputBg text-primary"
                    :placeholder="field.placeholder || ''"
                    :maxlength="field.maxlength"
                >
                <p v-if="form.errors[field.model]" class="mt-1 text-sm text-error">
                    {{ form.errors[field.model] }}
                </p>
            </div>
        </div>
    </div>
</template>


