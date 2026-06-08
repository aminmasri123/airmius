<script setup>
defineProps({
    canEnterStep: { type: Function, required: true },
    closeCreateModal: { type: Function, required: true },
    createStep: { type: Number, required: true },
    goToStep: { type: Function, required: true },
    steps: { type: Array, default: () => [] },
})
</script>

<template>
    <div class="shrink-0 border-b border-border bg-card p-4">
        <div class="flex items-start justify-between gap-4">
            <div class="min-w-0">
                <h2 class="truncate text-lg font-semibold text-primary">
                    {{ $t('events.create') }}
                </h2>

                <p class="mt-1 text-sm text-secondary">
                    Schritt {{ createStep }} von {{ steps.length }}
                </p>
            </div>

            <button
                type="button"
                class="shrink-0 rounded-lg border border-border px-3 py-1 text-secondary transition hover:border-borderHover hover:text-primary"
                @click="closeCreateModal"
            >
                x
            </button>
        </div>

        <div class="mt-4 grid grid-cols-4 gap-2">
            <button
                v-for="step in steps"
                :key="step.number"
                type="button"
                class="rounded-full px-2 py-2 text-xs font-semibold transition disabled:cursor-not-allowed disabled:opacity-45"
                :class="createStep === step.number
                    ? 'bg-buttonPrimary text-buttonTextPrimary'
                    : createStep > step.number
                        ? 'bg-air-green/15 text-air-green'
                        : 'bg-inputBg text-secondary'"
                :disabled="!canEnterStep(step.number)"
                @click="goToStep(step.number)"
            >
                {{ step.label }}
            </button>
        </div>
    </div>
</template>

