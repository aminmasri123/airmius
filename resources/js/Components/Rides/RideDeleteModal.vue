<script setup>
import Modal from '@/Components/Modal.vue'
import { ref, watch } from 'vue'

const props = defineProps({
    ride: { type: Object, default: null },
    show: { type: Boolean, default: false },
})

const emit = defineEmits(['close', 'confirm'])

const confirmation = ref('')

watch(() => [props.show, props.ride?.id], () => {
    confirmation.value = ''
})

const confirm = () => {
    if (confirmation.value !== 'DELETE') {
        return
    }

    emit('confirm')
}
</script>

<template>
    <Modal :show="show" max-width="md" @close="emit('close')">
        <div v-if="ride" class="space-y-4">
            <div>
                <p class="text-xs font-semibold uppercase tracking-wide text-error">Fahrgemeinschaft löschen</p>
                <h2 class="mt-1 text-lg font-bold text-primary">
                    {{ ride.from }} -> {{ ride.to }}
                </h2>
                <p class="mt-2 text-sm text-secondary">
                    Diese Fahrt wird dauerhaft gelöscht. Beigetretene Mitfahrer verlieren den Zugriff auf Kontakt- und Treffpunktdaten.
                </p>
            </div>

            <div class="rounded-lg border border-error/30 bg-error/10 p-3 text-sm text-error">
                Bitte gib <strong>DELETE</strong> ein, um die Aktion zu bestätigen.
            </div>

            <label class="block">
                <span class="text-sm font-semibold text-primary">Bestätigung</span>
                <input
                    v-model="confirmation"
                    class="mt-1 w-full rounded-lg border border-border bg-inputBg px-3 py-2 text-sm text-primary"
                    placeholder="DELETE"
                    autocomplete="off"
                >
            </label>

            <div class="flex flex-col-reverse gap-2 sm:flex-row sm:justify-end">
                <button
                    type="button"
                    class="rounded-lg border border-border px-4 py-2 text-sm font-semibold text-primary hover:bg-muted"
                    @click="emit('close')"
                >
                    Abbrechen
                </button>
                <button
                    type="button"
                    class="rounded-lg bg-error px-4 py-2 text-sm font-semibold text-white disabled:cursor-not-allowed disabled:opacity-50"
                    :disabled="confirmation !== 'DELETE'"
                    @click="confirm"
                >
                    Endgültig löschen
                </button>
            </div>
        </div>
    </Modal>
</template>

