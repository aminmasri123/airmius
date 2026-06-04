<script setup>
defineProps({
    selectedType: { type: Object, required: true },
    detailSummary: { type: String, default: '' },
    isLiveTraining: { type: Boolean, default: false },
    form: { type: Object, required: true },
})

defineEmits(['start-live', 'finish-live'])
</script>

<template>
    <section class="hidden rounded-2xl border border-air-blue/30 bg-air-blue/10 p-4 lg:block 2xl:hidden">
        <div class="flex flex-col gap-3 lg:flex-row lg:items-center lg:justify-between">
            <div class="min-w-0">
                <p class="text-xs font-semibold uppercase tracking-wide text-air-blue">Arbeitsmodus</p>
                <p class="mt-1 text-sm font-semibold text-primary">{{ selectedType.label }} - {{ detailSummary }}</p>
            </div>
            <div class="flex flex-wrap gap-2">
                <button
                    v-if="!isLiveTraining"
                    type="button"
                    class="rounded-xl border border-air-blue/40 bg-card px-4 py-2 text-sm font-semibold text-primary hover:bg-air-blue/10"
                    @click="$emit('start-live')"
                >
                    Live starten
                </button>
                <button
                    v-else
                    type="button"
                    class="rounded-xl border border-air-blue/40 bg-card px-4 py-2 text-sm font-semibold text-primary hover:bg-air-blue/10"
                    @click="$emit('finish-live')"
                >
                    Live beenden
                </button>
                <button type="submit" class="rounded-xl bg-buttonPrimary px-5 py-2 text-sm font-semibold text-buttonTextPrimary disabled:opacity-60" :disabled="form.processing">
                    Training speichern
                </button>
            </div>
        </div>
    </section>
</template>
