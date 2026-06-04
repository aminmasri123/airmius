<script setup>
import { computed } from 'vue'

const props = defineProps({
    modal: {
        type: Object,
        default: () => ({}),
    },
})

const emit = defineEmits(['close', 'confirm', 'update:confirmation'])

const confirmationModel = computed({
    get: () => props.modal?.confirmation || '',
    set: (value) => emit('update:confirmation', value),
})
</script>

<template>
    <div v-if="modal?.open" class="fixed inset-0 z-50 flex items-center justify-center bg-black/60 px-4">
        <div class="w-full max-w-lg rounded-xl border border-danger/30 bg-card p-5 shadow-2xl">
            <h2 class="text-lg font-semibold text-primary">Ads-Kampagne löschen</h2>
            <p class="mt-2 text-sm text-secondary">
                Diese Kampagne wird dauerhaft gelöscht:
                <span class="font-semibold text-primary">{{ modal.campaign?.headline || modal.campaign?.name }}</span>
            </p>
            <p class="mt-4 text-sm text-secondary">
                Bitte gib <strong class="text-primary">delete</strong> ein, um die Löschung zu bestätigen.
            </p>
            <input
                v-model="confirmationModel"
                class="mt-3 w-full rounded-lg border-border bg-inputBg text-sm text-primary"
                placeholder="delete"
                autocomplete="off"
            >
            <div class="mt-5 flex flex-col-reverse gap-3 sm:flex-row sm:justify-end">
                <button
                    type="button"
                    class="rounded-lg border border-border px-4 py-2 text-sm font-semibold text-primary"
                    @click="emit('close')"
                >
                    Abbrechen
                </button>
                <button
                    type="button"
                    class="rounded-lg bg-danger px-4 py-2 text-sm font-semibold text-white disabled:cursor-not-allowed disabled:opacity-50"
                    :disabled="confirmationModel !== 'delete'"
                    @click="emit('confirm')"
                >
                    Endgültig löschen
                </button>
            </div>
        </div>
    </div>
</template>

