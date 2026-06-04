<script setup>
import Modal from '@/Components/Modal.vue'

defineProps({
    show: { type: Boolean, default: false },
    cancelForm: { type: Object, required: true },
    cancelEvent: { type: Function, required: true },
})

defineEmits(['close'])
</script>

<template>
    <Modal :show="show" max-width="lg" @close="$emit('close')">
        <form class="p-5" @submit.prevent="cancelEvent">
            <h2 class="text-lg font-semibold text-primary">Event absagen</h2>
            <p class="mt-2 text-sm leading-6 text-secondary">
                Teilnehmer mit Zusage werden informiert. Du kannst optional einen Grund angeben.
            </p>

            <label class="mt-4 block text-sm font-semibold text-primary" for="cancel-reason">
                Grund
            </label>
            <textarea
                id="cancel-reason"
                v-model="cancelForm.reason"
                rows="4"
                class="mt-1 w-full resize-none rounded-lg border border-border bg-inputBg px-3 py-3 text-primary placeholder-secondary focus:border-borderHover focus:ring-borderHover"
                placeholder="Optionaler Grund"
            />
            <p v-if="cancelForm.errors.reason" class="mt-1 text-sm text-error">{{ cancelForm.errors.reason }}</p>

            <div class="mt-5 flex justify-end gap-3">
                <button type="button" class="rounded-lg border border-border px-4 py-2 text-primary hover:bg-muted" @click="$emit('close')">
                    Abbrechen
                </button>
                <button class="rounded-lg bg-warning px-4 py-2 font-semibold text-white hover:opacity-90" :disabled="cancelForm.processing">
                    {{ cancelForm.processing ? 'Wird abgesagt...' : 'Event absagen' }}
                </button>
            </div>
        </form>
    </Modal>
</template>
