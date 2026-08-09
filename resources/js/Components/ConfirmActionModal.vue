<script setup>
import { getCurrentInstance } from 'vue'
import Modal from '@/Components/Modal.vue'
import AppButton from '@/Components/UI/AppButton.vue'

defineProps({
    show: {
        type: Boolean,
        default: false,
    },
    title: {
        type: String,
        default: 'Aktion bestätigen',
    },
    message: {
        type: String,
        default: '',
    },
    confirmLabel: {
        type: String,
        default: 'Bestätigen',
    },
    cancelLabel: {
        type: String,
        default: 'Abbrechen',
    },
    danger: {
        type: Boolean,
        default: false,
    },
    processing: {
        type: Boolean,
        default: false,
    },
})

const emit = defineEmits(['cancel', 'confirm'])
const instanceId = getCurrentInstance()?.uid ?? 'default'
const titleId = `confirm-action-title-${instanceId}`
const descriptionId = `confirm-action-description-${instanceId}`
</script>

<template>
    <Modal
        :show="show"
        max-width="md"
        :aria-labelledby="titleId"
        :aria-describedby="message ? descriptionId : undefined"
        @close="emit('cancel')"
    >
        <div class="p-5">
            <div
                class="flex h-11 w-11 items-center justify-center rounded-lg"
                :class="danger ? 'bg-error/10 text-error' : 'bg-buttonPrimary/10 text-buttonPrimary'"
            >
                <i :class="danger ? 'las la-exclamation-triangle text-2xl' : 'las la-check-circle text-2xl'" aria-hidden="true"></i>
            </div>

            <h2 :id="titleId" class="mt-4 text-lg font-semibold text-primary">{{ title }}</h2>
            <p v-if="message" :id="descriptionId" class="mt-2 text-sm leading-6 text-secondary">{{ message }}</p>

            <div class="mt-6 flex flex-col-reverse gap-2 sm:flex-row sm:justify-end">
                <AppButton
                    type="button"
                    variant="secondary"
                    :disabled="processing"
                    @click="emit('cancel')"
                >
                    {{ cancelLabel }}
                </AppButton>
                <AppButton
                    type="button"
                    :variant="danger ? 'danger' : 'primary'"
                    :disabled="processing"
                    @click="emit('confirm')"
                >
                    {{ confirmLabel }}
                </AppButton>
            </div>
        </div>
    </Modal>
</template>
