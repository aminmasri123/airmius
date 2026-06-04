<script setup>
defineProps({
    form: { type: Object, required: true },
})

const bankFields = [
    { model: 'billing_bank_account_holder', label: 'Kontoinhaber' },
    { model: 'billing_bank_name', label: 'Bank' },
    { model: 'billing_iban', label: 'IBAN', placeholder: 'DE...' },
    { model: 'billing_bic', label: 'BIC' },
    { model: 'billing_payment_terms_days', label: 'Zahlungsziel in Tagen', type: 'number', min: 1, max: 60 },
]
</script>

<template>
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
            <div v-for="field in bankFields" :key="field.model">
                <label :for="field.model" class="text-sm font-semibold text-primary">{{ field.label }}</label>
                <input
                    :id="field.model"
                    v-model="form[field.model]"
                    :type="field.type || 'text'"
                    :min="field.min"
                    :max="field.max"
                    class="mt-1 block w-full rounded-lg border-border bg-inputBg text-primary"
                    :placeholder="field.placeholder || ''"
                >
                <p v-if="form.errors[field.model]" class="mt-1 text-sm text-error">
                    {{ form.errors[field.model] }}
                </p>
            </div>
        </div>
    </div>
</template>

