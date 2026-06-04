<script setup>
defineProps({
    deleteCandidate: { type: Object, default: null },
    tAuto: { type: Function, required: true },
})

defineEmits(['cancel', 'confirm'])
</script>

<template>
    <div v-if="deleteCandidate" class="fixed inset-0 z-[80] flex items-end justify-center bg-black/60 p-4 sm:items-center">
        <div class="w-full max-w-md rounded-2xl border border-border bg-card p-5 shadow-2xl">
            <h2 class="text-lg font-bold text-primary">{{ tAuto('Eintrag löschen?') }}</h2>
            <p class="mt-2 text-sm text-secondary">
                {{ tAuto(`"${deleteCandidate.title}" wird aus deinem Tageslog entfernt.`) }}
            </p>
            <div class="mt-5 flex justify-end gap-2">
                <button type="button" class="rounded-xl border border-border px-4 py-2 text-sm font-bold text-primary hover:bg-muted" @click="$emit('cancel')">
                    {{ tAuto('Abbrechen') }}
                </button>
                <button type="button" class="rounded-xl bg-danger px-4 py-2 text-sm font-bold text-white" @click="$emit('confirm')">
                    {{ tAuto('Löschen') }}
                </button>
            </div>
        </div>
    </div>
</template>

