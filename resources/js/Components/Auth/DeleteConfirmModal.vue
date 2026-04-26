<template>
    <div v-if="show" class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50">
        <div class="bg-card p-6 rounded-lg shadow-lg max-w-md w-full mx-4">
            <h3 class="text-lg font-semibold text-primary mb-4">{{ title }}</h3>
            <p class="text-secondary mb-4">
                {{ message }}
            </p>
            <p class="text-sm text-secondary mb-4">
                Geben Sie <strong>"{{ confirmText }}"</strong> ein, um zu bestätigen:
            </p>
            <input
                v-model="confirmation"
                type="text"
                class="w-full px-3 py-2 border border-border rounded-lg bg-card text-primary focus:outline-none focus:ring-primary focus:border-primary mb-4"
                :placeholder="confirmText"
            />
            <div class="flex justify-end space-x-2">
                <button
                    @click="cancel"
                    class="px-4 py-2 bg-secondary text-primary rounded-lg hover:bg-secondary/80 transition-colors"
                >
                    {{ cancelText }}
                </button>
                <button
                    @click="confirm"
                    :disabled="confirmation !== confirmText"
                    class="px-4 py-2 bg-error text-white rounded-lg hover:bg-error/80 transition-colors disabled:opacity-50 disabled:cursor-not-allowed"
                >
                    {{ confirmText }}
                </button>
            </div>
        </div>
    </div>
</template>

<script setup>
import { ref, watch } from 'vue'

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
})

const emit = defineEmits(['confirm', 'cancel'])

const confirmation = ref('')

const confirm = () => {
    if (confirmation.value === props.confirmText) {
        emit('confirm')
        confirmation.value = ''
    }
}

const cancel = () => {
    emit('cancel')
    confirmation.value = ''
}

// Reset confirmation when modal is shown/hidden
watch(() => props.show, (newVal) => {
    if (!newVal) {
        confirmation.value = ''
    }
})
</script>