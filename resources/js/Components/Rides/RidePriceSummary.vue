<script setup>
import { useI18n } from 'vue-i18n'
import { ridePriceLabel, ridePricingText } from '@/support/ridePricing'
defineProps({ pricing: { type: Object, default: () => ({}) } })
const { locale } = useI18n()
const text = key => ridePricingText(locale.value, key)
</script>
<template>
    <div class="space-y-2 rounded-lg border border-border p-3 text-sm" aria-live="polite">
        <p class="font-semibold text-primary" dir="auto">{{ ridePriceLabel(pricing, locale) }}</p>
        <template v-if="pricing.requires_confirmation">
            <p class="font-semibold text-warning">{{ text('changed') }}</p>
            <p dir="auto">{{ text('agreed') }}: {{ ridePriceLabel(pricing.agreed, locale) }}</p>
            <p>{{ text('update_warning') }}</p>
        </template>
        <template v-if="pricing.mode === 'contribution'">
            <p class="text-secondary">{{ text('all_included') }}</p>
            <p class="text-secondary">{{ text('direct_payment') }}</p>
        </template>
    </div>
</template>
