<script setup>
import Modal from '@/Components/Modal.vue'
import { ref, watch } from 'vue'

const props = defineProps({
    ride: { type: Object, default: null },
})

const emit = defineEmits(['close', 'submit'])

const message = ref('')

watch(() => props.ride?.id, () => {
    message.value = ''
})

const submit = () => {
    emit('submit', message.value)
}
</script>

<template>
    <Modal :show="Boolean(ride)" max-width="md" @close="emit('close')">
        <div v-if="ride" class="space-y-4">
            <div>
                <p class="text-xs font-semibold uppercase tracking-wide text-air-blue">Mitfahranfrage</p>
                <h2 class="mt-1 text-lg font-bold text-primary">
                    {{ ride.from }} -> {{ ride.to }}
                </h2>
                <p class="mt-2 text-sm text-secondary">
                    Deine Anfrage wird an den Fahrer gesendet. Kontaktdaten und genaue Treffpunktdetails siehst du erst nach Annahme.
                </p>
            </div>

            <label class="block">
                <span class="text-sm font-semibold text-primary">Nachricht optional</span>
                <textarea
                    v-model="message"
                    rows="4"
                    class="mt-1 w-full rounded-lg border border-border bg-inputBg px-3 py-2 text-sm text-primary"
                    placeholder="z. B. Ich kann am Treffpunkt sein."
                ></textarea>
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
                    class="rounded-lg bg-buttonPrimary px-4 py-2 text-sm font-semibold text-buttonTextPrimary"
                    @click="submit"
                >
                    Anfrage senden
                </button>
            </div>
        </div>
    </Modal>
</template>
