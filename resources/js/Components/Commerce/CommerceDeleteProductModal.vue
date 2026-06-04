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
        <div class="w-full max-w-lg rounded-xl border border-border bg-card p-5 shadow-2xl">
            <h2 class="text-lg font-semibold text-primary">Produkt löschen</h2>
            <p class="mt-2 text-sm text-secondary">
                Gib <span class="font-semibold text-primary">delete</span> ein. Wenn es bereits Bestellungen gibt, wird das Produkt archiviert statt gelöscht.
            </p>
            <p class="mt-3 rounded-lg border border-border bg-bg p-3 text-sm font-semibold text-primary">{{ modal.product?.title }}</p>
            <input v-model="confirmationModel" class="mt-4 w-full rounded-lg border-border bg-inputBg text-sm text-primary" placeholder="delete">
            <div class="mt-5 flex justify-end gap-3">
                <button type="button" class="rounded-lg border border-border px-4 py-2 text-sm font-semibold text-primary" @click="emit('close')">
                    Abbrechen
                </button>
                <button
                    type="button"
                    class="rounded-lg border border-danger/50 px-4 py-2 text-sm font-semibold text-danger disabled:cursor-not-allowed disabled:opacity-50"
                    :disabled="confirmationModel !== 'delete'"
                    @click="emit('confirm')"
                >
                    Löschen
                </button>
            </div>
        </div>
    </div>
</template>

