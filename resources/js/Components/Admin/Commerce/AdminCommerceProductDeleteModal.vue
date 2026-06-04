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
    <div v-if="modal.open" class="fixed inset-0 z-50 flex items-center justify-center bg-black/60 px-4">
        <div class="w-full max-w-lg rounded-xl border border-danger/30 bg-card p-5 shadow-2xl">
            <div class="flex items-start gap-3">
                <div class="flex h-11 w-11 shrink-0 items-center justify-center rounded-lg bg-danger/10 text-danger">
                    <i class="las la-trash text-2xl"></i>
                </div>
                <div>
                    <p class="text-xs font-semibold uppercase tracking-wide text-danger">Produkt löschen</p>
                    <h2 class="mt-1 text-lg font-semibold text-primary">{{ modal.product?.title }}</h2>
                    <p class="mt-2 text-sm leading-6 text-secondary">
                        Produkte ohne Bestellungen werden endgültig gelöscht. Wenn bereits Bestellungen existieren, wird das Produkt aus Sicherheitsgründen archiviert, damit Rechnungen und Käufe nachvollziehbar bleiben.
                    </p>
                </div>
            </div>
            <label class="mt-5 block">
                <span class="text-xs font-semibold uppercase text-secondary">Zur Bestätigung delete eingeben</span>
                <input v-model="confirmationModel" class="mt-2 w-full rounded-lg border-border bg-inputBg text-sm text-primary" placeholder="delete">
            </label>
            <div class="mt-5 flex flex-col-reverse gap-2 sm:flex-row sm:justify-end">
                <button type="button" class="rounded-lg border border-border px-4 py-2 text-sm font-semibold text-primary" :disabled="modal.processing" @click="emit('close')">
                    Abbrechen
                </button>
                <button
                    type="button"
                    class="rounded-lg bg-danger px-4 py-2 text-sm font-semibold text-white disabled:cursor-not-allowed disabled:opacity-50"
                    :disabled="confirmationModel !== 'delete' || modal.processing"
                    @click="emit('confirm')"
                >
                    Löschen
                </button>
            </div>
        </div>
    </div>
</template>

