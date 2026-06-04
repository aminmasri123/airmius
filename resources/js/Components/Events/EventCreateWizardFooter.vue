<script setup>
defineProps({
    createStep: { type: Number, required: true },
    currentStepValidationMessage: { type: String, default: '' },
    form: { type: Object, required: true },
    nextStep: { type: Function, required: true },
    prevStep: { type: Function, required: true },
    steps: { type: Array, default: () => [] },
    submit: { type: Function, required: true },
})
</script>

<template>
    <div class="shrink-0 border-t border-border bg-card p-4">
        <div v-if="currentStepValidationMessage" class="mb-3 rounded-lg border border-warning/40 bg-warning/10 px-3 py-2 text-sm font-semibold text-warning">
            {{ currentStepValidationMessage }}
        </div>

        <div class="flex gap-3">
            <button
                type="button"
                class="flex-1 rounded-lg border border-border px-4 py-3 font-semibold text-secondary transition hover:border-borderHover hover:text-primary disabled:opacity-50"
                :disabled="createStep === 1"
                @click="prevStep"
            >
                Zurück
            </button>

            <button
                v-if="createStep < steps.length"
                type="button"
                class="flex-1 rounded-lg bg-buttonPrimary px-4 py-3 font-semibold text-buttonTextPrimary transition hover:bg-buttonPrimaryHover disabled:cursor-not-allowed disabled:opacity-50"
                :disabled="!!currentStepValidationMessage"
                @click="nextStep"
            >
                Weiter
            </button>

            <button
                v-else
                type="button"
                class="flex-1 rounded-lg bg-buttonPrimary px-4 py-3 font-semibold text-buttonTextPrimary transition hover:bg-buttonPrimaryHover disabled:opacity-50"
                :disabled="form.processing"
                @click="submit"
            >
                {{ form.processing ? 'Speichern...' : $t('events.create') }}
            </button>
        </div>
    </div>
</template>

