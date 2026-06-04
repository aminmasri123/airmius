<script setup>
import { computed } from 'vue'

const props = defineProps({
    trainingSteps: { type: Array, default: () => [] },
    currentStep: { type: Number, required: true },
})

const emit = defineEmits(['update:currentStep'])

const selectedStep = computed({
    get: () => props.currentStep,
    set: (value) => emit('update:currentStep', value),
})
</script>

<template>
    <div class="min-w-0 self-start rounded-2xl border border-border bg-card p-1.5 2xl:col-span-2">
        <div class="grid grid-cols-3 gap-1.5 sm:gap-2">
            <button
                v-for="step in trainingSteps"
                :key="step.id"
                type="button"
                class="rounded-xl px-2 py-2 text-left transition sm:px-3 sm:py-3"
                :class="selectedStep === step.id ? 'bg-air-blue text-white shadow-sm shadow-air-blue/20' : 'bg-inputBg/40 text-secondary hover:bg-muted hover:text-primary'"
                @click="selectedStep = step.id"
            >
                <span class="block text-[10px] font-semibold uppercase tracking-wide sm:text-[11px]">Schritt {{ step.id }}</span>
                <span class="mt-0.5 block truncate text-xs font-semibold sm:mt-1 sm:text-sm">{{ step.short || step.label }}</span>
            </button>
        </div>
    </div>
</template>
