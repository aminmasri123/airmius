<script setup>
import { Link } from '@inertiajs/vue3'
import AppButton from '@/Components/UI/AppButton.vue'
import AppLoadingState from '@/Components/UI/AppLoadingState.vue'
import { computed } from 'vue'

const props = defineProps({
    confirmation: {
        type: Object,
        default: () => ({}),
    },
    interval: {
        type: String,
        default: 'monthly',
    },
    price: {
        type: String,
        default: '',
    },
    providerLabel: {
        type: Function,
        default: (value) => value || '',
    },
    title: {
        type: String,
        default: '',
    },
    processing: {
        type: Boolean,
        default: false,
    },
})

const emit = defineEmits(['close', 'confirm', 'update:accepted'])

const acceptedModel = computed({
    get: () => Boolean(props.confirmation?.accepted),
    set: (value) => emit('update:accepted', value),
})
</script>

<template>
    <div v-if="confirmation?.open" class="fixed inset-0 z-[60] flex items-center justify-center bg-black/60 px-4">
        <div class="w-full max-w-lg rounded-xl border border-border bg-card p-5 shadow-2xl">
            <div class="flex items-start justify-between gap-4">
                <div>
                    <p class="text-xs font-semibold uppercase tracking-wide text-air-blue">Checkout bestätigen</p>
                    <h2 class="mt-1 text-lg font-semibold text-primary">{{ title }}</h2>
                    <p class="mt-2 text-sm text-secondary">
                        {{ price }}
                        <span v-if="confirmation.type === 'account_plan'">pro {{ interval === 'yearly' ? 'Jahr' : 'Monat' }}</span>
                        <span> · {{ providerLabel(confirmation.provider) }}</span>
                    </p>
                </div>
                <AppButton
                    type="button"
                    variant="ghost"
                    size="sm"
                    icon-only
                    :disabled="processing"
                    aria-label="Checkout schliessen"
                    title="Checkout schliessen"
                    @click="emit('close')"
                >
                    <i class="las la-times text-xl" aria-hidden="true"></i>
                </AppButton>
            </div>

            <label class="mt-5 flex items-start gap-3 rounded-lg border border-border bg-bg p-3 text-sm text-secondary">
                <input v-model="acceptedModel" type="checkbox" class="mt-1 rounded border-border bg-inputBg">
                <span>
                    Ich akzeptiere AGB, Widerrufshinweise und nehme zur Kenntnis, dass Marketplace-Angebote je nach Produkt durch den jeweiligen Anbieter erbracht werden.
                    <Link :href="route('terms.show')" class="text-air-blue underline">AGB</Link>
                    <span> · </span>
                    <Link :href="route('legal.withdrawal')" class="text-air-blue underline">Widerruf</Link>
                </span>
            </label>

            <AppLoadingState
                v-if="processing"
                class="mt-4"
                label="Zahlung wird vorbereitet..."
                inline
            />

            <div class="mt-5 flex flex-col-reverse gap-3 sm:flex-row sm:justify-end">
                <AppButton type="button" variant="secondary" :disabled="processing" @click="emit('close')">
                    Abbrechen
                </AppButton>
                <AppButton
                    type="button"
                    :disabled="!acceptedModel || processing"
                    :loading="processing"
                    @click="emit('confirm')"
                >
                    {{ processing ? 'Bereitet Zahlung vor...' : 'Zahlungspflichtig fortfahren' }}
                </AppButton>
            </div>
        </div>
    </div>
</template>

