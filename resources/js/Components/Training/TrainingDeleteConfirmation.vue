<script setup>
import { computed } from 'vue'
import { useI18n } from 'vue-i18n'

const props = defineProps({
    confirmation: {
        type: String,
        default: '',
    },
    confirmLabel: {
        type: String,
        default: 'Endgültig löschen',
    },
    descriptionAfter: {
        type: String,
        default: '',
    },
    descriptionBefore: {
        type: String,
        required: true,
    },
    title: {
        type: String,
        default: '',
    },
})

const emit = defineEmits(['cancel', 'confirm', 'update:confirmation'])
const { t } = useI18n()

const confirmationModel = computed({
    get: () => props.confirmation,
    set: (value) => emit('update:confirmation', value),
})
</script>

<template>
    <div class="p-4">
        <p class="text-sm text-secondary">
            {{ descriptionBefore }}
            <span class="font-semibold text-primary">{{ title }}</span>
            {{ descriptionAfter }}
            {{ t('Tippe') }} <span class="font-semibold text-danger">delete</span>{{ t(', um fortzufahren.') }}
        </p>
        <input v-model="confirmationModel" class="mt-4 w-full rounded-xl border border-border bg-inputBg px-3 py-2 text-primary" placeholder="delete" />
        <div class="mt-4 flex justify-end gap-2">
            <button type="button" class="rounded-xl border border-border px-4 py-2 text-sm font-semibold text-primary" @click="emit('cancel')">
                {{ t('Abbrechen') }}
            </button>
            <button type="button" class="rounded-xl bg-danger px-4 py-2 text-sm font-semibold text-white disabled:opacity-50" :disabled="confirmationModel !== 'delete'" @click="emit('confirm')">
                {{ confirmLabel }}
            </button>
        </div>
    </div>
</template>
