<script setup>
import { useI18n } from 'vue-i18n'

const { t } = useI18n()

defineProps({
    form: {
        type: Object,
        required: true,
    },
    moneyInputAttrs: {
        type: Object,
        default: () => ({}),
    },
})

const emit = defineEmits(['close', 'submit'])
</script>

<template>
    <div v-if="form.open" class="fixed inset-0 z-50 flex items-center justify-center bg-black/60 px-4">
        <div class="w-full max-w-lg rounded-xl border border-border bg-card p-5 shadow-2xl">
            <h2 class="text-lg font-semibold text-primary">{{ t('commerce_refunds.title') }}</h2>
            <div class="mt-4 grid gap-3">
                <input v-model="form.amount_cents" v-bind="moneyInputAttrs" class="rounded-lg border-border bg-inputBg text-sm text-primary" :placeholder="t('commerce_refunds.amount_placeholder')">
                <textarea v-model="form.reason" rows="3" class="rounded-lg border-border bg-inputBg text-sm text-primary" :placeholder="t('commerce_refunds.reason_placeholder')"></textarea>
                <p class="text-xs text-secondary">{{ t('commerce_refunds.idempotency_hint') }}</p>
            </div>
            <div class="mt-5 flex justify-end gap-3">
                <button type="button" class="rounded-lg border border-border px-4 py-2 text-sm font-semibold text-primary" :disabled="form.processing" @click="emit('close')">
                    {{ t('commerce_refunds.cancel') }}
                </button>
                <button type="button" class="rounded-lg bg-warning px-4 py-2 text-sm font-semibold text-white disabled:opacity-50" :disabled="form.processing" @click="emit('submit')">
                    {{ t('commerce_refunds.submit') }}
                </button>
            </div>
        </div>
    </div>
</template>
