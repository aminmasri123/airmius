<script setup>
import { ref, watch } from 'vue';
import Modal from '@/Components/Modal.vue';
import AppButton from '@/Components/UI/AppButton.vue';
import AppFormField from '@/Components/UI/AppFormField.vue';

const props = defineProps({
    show: {
        type: Boolean,
        default: false,
    },
    title: {
        type: String,
        default: 'Bestätigung erforderlich',
    },
    message: {
        type: String,
        default: 'Sind Sie sicher, dass Sie diese Aktion ausführen möchten?',
    },
    confirmText: {
        type: String,
        default: 'delete',
    },
    cancelText: {
        type: String,
        default: 'Abbrechen',
    },
});

const emit = defineEmits(['confirm', 'cancel']);

const confirmation = ref('');

const reset = () => {
    confirmation.value = '';
};

const confirm = () => {
    if (confirmation.value !== props.confirmText) {
        return;
    }

    emit('confirm');
    reset();
};

const cancel = () => {
    emit('cancel');
    reset();
};

watch(() => props.show, (show) => {
    if (!show) {
        reset();
    }
});
</script>

<template>
    <Modal :show="show" max-width="md" @close="cancel">
        <div class="p-5">
            <div class="flex size-11 items-center justify-center rounded-lg bg-error/10 text-error">
                <i class="las la-exclamation-triangle text-2xl" aria-hidden="true"></i>
            </div>

            <h3 class="mt-4 text-lg font-semibold text-primary">
                {{ title }}
            </h3>

            <p class="mt-2 text-sm leading-6 text-secondary">
                {{ message }}
            </p>

            <AppFormField
                class="mt-4"
                id="delete-confirmation"
                :label="`Geben Sie &quot;${confirmText}&quot; ein, um zu bestätigen:`"
            >
                <input
                    v-model="confirmation"
                    id="delete-confirmation"
                    type="text"
                    class="w-full rounded-lg border border-border bg-inputBg px-3 py-2 text-primary placeholder:text-secondary/70 focus:border-borderHover focus:outline-none focus:ring-borderHover"
                    :placeholder="confirmText"
                    @keydown.enter.prevent="confirm"
                >
            </AppFormField>

            <div class="mt-6 flex flex-col-reverse gap-2 sm:flex-row sm:justify-end">
                <AppButton type="button" variant="secondary" @click="cancel">
                    {{ cancelText }}
                </AppButton>
                <AppButton
                    type="button"
                    variant="danger"
                    :disabled="confirmation !== confirmText"
                    @click="confirm"
                >
                    {{ confirmText }}
                </AppButton>
            </div>
        </div>
    </Modal>
</template>
