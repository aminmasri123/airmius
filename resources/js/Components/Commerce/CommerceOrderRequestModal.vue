<script setup>
import { computed } from 'vue'

const props = defineProps({
    modal: {
        type: Object,
        default: () => ({}),
    },
})

const emit = defineEmits(['close', 'submit', 'update:note'])

const noteModel = computed({
    get: () => props.modal?.note || '',
    set: (value) => emit('update:note', value),
})

const title = computed(() => props.modal?.mode === 'return' ? 'Rücksendung anfragen' : 'Problem melden')
</script>

<template>
    <div v-if="modal?.open" class="fixed inset-0 z-50 flex items-center justify-center bg-black/60 px-4">
        <div class="w-full max-w-lg rounded-xl border border-border bg-card p-5 shadow-2xl">
            <h2 class="text-lg font-semibold text-primary">{{ title }}</h2>
            <p class="mt-2 text-sm text-secondary">
                Beschreibe kurz, was geprüft werden soll.
            </p>
            <textarea v-model="noteModel" rows="5" class="mt-4 w-full rounded-lg border-border bg-inputBg text-sm text-primary" placeholder="Grund eingeben"></textarea>
            <div class="mt-5 flex justify-end gap-3">
                <button type="button" class="rounded-lg border border-border px-4 py-2 text-sm font-semibold text-primary" @click="emit('close')">
                    Abbrechen
                </button>
                <button type="button" class="rounded-lg bg-buttonPrimary px-4 py-2 text-sm font-semibold text-buttonTextPrimary" @click="emit('submit')">
                    Senden
                </button>
            </div>
        </div>
    </div>
</template>

