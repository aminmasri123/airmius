<script setup>
import Modal from '@/Components/Modal.vue'

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
</script>

<template>
    <Modal :show="show" max-width="md" @close="emit('cancel')">
        <div class="p-5">
            <div
                class="flex h-11 w-11 items-center justify-center rounded-lg"
                :class="danger ? 'bg-error/10 text-error' : 'bg-buttonPrimary/10 text-buttonPrimary'"
            >
                <i :class="danger ? 'las la-exclamation-triangle text-2xl' : 'las la-check-circle text-2xl'"></i>
            </div>

            <h2 class="mt-4 text-lg font-semibold text-primary">{{ title }}</h2>
            <p class="mt-2 text-sm leading-6 text-secondary">{{ message }}</p>

            <div class="mt-6 flex flex-col-reverse gap-2 sm:flex-row sm:justify-end">
                <button
                    type="button"
                    class="rounded-lg border border-border px-4 py-2 text-sm font-semibold text-primary transition hover:bg-inputBg disabled:opacity-60"
                    :disabled="processing"
                    @click="emit('cancel')"
                >
                    {{ cancelLabel }}
                </button>
                <button
                    type="button"
                    class="rounded-lg px-4 py-2 text-sm font-semibold transition disabled:opacity-60"
                    :class="danger ? 'bg-error text-white hover:opacity-90' : 'bg-buttonPrimary text-buttonTextPrimary hover:bg-buttonPrimaryHover'"
                    :disabled="processing"
                    @click="emit('confirm')"
                >
                    {{ confirmLabel }}
                </button>
            </div>
        </div>
    </Modal>
</template>
