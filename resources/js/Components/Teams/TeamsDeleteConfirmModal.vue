<script setup>
import Modal from '@/Components/Modal.vue'
import { computed } from 'vue'

const props = defineProps({
    deleteConfirmation: {
        type: String,
        default: '',
    },
    deleteReason: {
        type: String,
        default: '',
    },
    deleteTarget: {
        type: Object,
        default: null,
    },
    show: {
        type: Boolean,
        default: false,
    },
})

const emit = defineEmits([
    'close',
    'confirm',
    'update:deleteConfirmation',
    'update:deleteReason',
])

const confirmationModel = computed({
    get: () => props.deleteConfirmation,
    set: (value) => emit('update:deleteConfirmation', value),
})

const reasonModel = computed({
    get: () => props.deleteReason,
    set: (value) => emit('update:deleteReason', value),
})
</script>

<template>
    <Modal :show="show" max-width="md" @close="$emit('close')">
        <div v-if="deleteTarget" class="space-y-4">
            <div>
                <h2 class="text-lg font-bold text-primary">{{ deleteTarget.title }}</h2>
                <p class="mt-2 text-sm text-secondary">{{ deleteTarget.description }}</p>
            </div>

            <div class="rounded-lg border border-error/30 bg-error/10 p-3 text-sm text-error">
                {{ $t('teams_workspace.delete.instruction', { confirmation: deleteTarget.confirmText || 'delete' }) }}
            </div>

            <label class="block">
                <span class="text-sm font-semibold text-primary">{{ $t('teams_workspace.delete.confirmation') }}</span>
                <input
                    v-model="confirmationModel"
                    class="mt-1 w-full rounded-lg border border-border bg-inputBg px-3 py-2 text-sm text-primary"
                    :placeholder="deleteTarget.confirmText || 'delete'"
                    autocomplete="off"
                >
            </label>

            <label v-if="deleteTarget.requiresReason" class="block">
                <span class="text-sm font-semibold text-primary">{{ $t('teams_workspace.delete.reason') }}</span>
                <textarea
                    v-model="reasonModel"
                    rows="4"
                    class="mt-1 w-full rounded-lg border border-border bg-inputBg px-3 py-2 text-sm text-primary"
                    :placeholder="$t('teams_workspace.delete.reason_placeholder')"
                ></textarea>
                <p class="mt-1 text-xs text-secondary">{{ $t('teams_workspace.delete.reason_hint') }}</p>
            </label>

            <div class="flex flex-col-reverse gap-2 sm:flex-row sm:justify-end">
                <button
                    type="button"
                    class="rounded-lg border border-border px-4 py-2 text-sm font-semibold text-primary hover:bg-muted"
                    @click="$emit('close')"
                >
                    {{ $t('teams_workspace.delete.cancel') }}
                </button>
                <button
                    type="button"
                    class="rounded-lg bg-error px-4 py-2 text-sm font-semibold text-white disabled:cursor-not-allowed disabled:opacity-50"
                    :disabled="deleteConfirmation !== (deleteTarget.confirmText || 'delete')"
                    @click="$emit('confirm')"
                >
                    {{ deleteTarget.buttonLabel || $t('teams_workspace.delete.permanent') }}
                </button>
            </div>
        </div>
    </Modal>
</template>
