<script setup>
import { getCurrentInstance } from 'vue';
import Modal from './Modal.vue';

const emit = defineEmits(['close']);
const titleId = `dialog-modal-title-${getCurrentInstance()?.uid ?? 'default'}`;

defineProps({
    show: {
        type: Boolean,
        default: false,
    },
    maxWidth: {
        type: String,
        default: '2xl',
    },
    closeable: {
        type: Boolean,
        default: true,
    },
});

const close = () => {
    emit('close');
};
</script>

<template>
    <Modal
        :show="show"
        :max-width="maxWidth"
        :closeable="closeable"
        :aria-labelledby="titleId"
        @close="close"
    >
        <div class="px-6 py-4">
            <div :id="titleId" class="text-lg font-semibold text-primary">
                <slot name="title" />
            </div>

            <div class="mt-4 text-sm leading-6 text-secondary">
                <slot name="content" />
            </div>
        </div>

        <div class="flex flex-row justify-end gap-2 border-t border-border bg-inputBg px-6 py-4 text-end">
            <slot name="footer" />
        </div>
    </Modal>
</template>
