<script setup>
defineProps({
    trainingTypes: { type: Array, default: () => [] },
    form: { type: Object, required: true },
    trainingTypeTheme: { type: Function, required: true },
    trainingTypeButtonClass: { type: Function, required: true },
})

defineEmits(['close', 'select'])
</script>

<template>
    <div class="fixed inset-0 z-40 bg-black/60 px-3 pb-3 pt-16 sm:hidden" @click.self="$emit('close')">
        <div class="mt-auto max-h-[78vh] overflow-hidden rounded-3xl border border-border bg-card shadow-2xl">
            <div class="flex items-center justify-between gap-3 border-b border-border p-4">
                <div>
                    <p class="text-xs font-semibold uppercase tracking-wide text-air-blue">Trainingsart</p>
                    <h3 class="text-lg font-semibold text-primary">Was machst du heute?</h3>
                </div>
                <button
                    type="button"
                    class="flex h-10 w-10 items-center justify-center rounded-full border border-border text-secondary"
                    aria-label="Schließen"
                    @click="$emit('close')"
                >
                    <i class="las la-times text-xl"></i>
                </button>
            </div>
            <div class="custom-scrollbar max-h-[62vh] space-y-2 overflow-y-auto p-3">
                <button
                    v-for="type in trainingTypes"
                    :key="`mobile-type-${type.key}`"
                    type="button"
                    class="flex w-full items-center gap-3 rounded-2xl border p-3 text-left transition"
                    :class="trainingTypeButtonClass(type)"
                    @click="$emit('select', type.key)"
                >
                    <span class="flex h-11 w-11 shrink-0 items-center justify-center rounded-2xl" :class="trainingTypeTheme(type.key).icon">
                        <i :class="type.icon" class="text-xl"></i>
                    </span>
                    <span class="min-w-0 flex-1">
                        <span class="block truncate text-sm font-semibold">{{ type.shortLabel }}</span>
                        <span class="mt-0.5 block truncate text-xs opacity-80">{{ type.label }}</span>
                    </span>
                    <i v-if="form.training_type === type.key" class="las la-check-circle text-xl"></i>
                </button>
            </div>
        </div>
    </div>
</template>

