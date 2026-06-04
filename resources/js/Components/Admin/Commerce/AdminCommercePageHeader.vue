<script setup>
defineProps({
    campaignStatusError: {
        type: String,
        default: '',
    },
    success: {
        type: String,
        default: '',
    },
})
</script>

<template>
    <section class="surface-card p-5">
        <p class="text-xs font-semibold uppercase tracking-wide text-air-blue">Business</p>
        <h1 class="mt-1 text-2xl font-bold text-primary">Commerce-Zentrale</h1>
        <p class="mt-2 max-w-3xl text-sm text-secondary">
            Coupons, Add-ons, Marketplace, Ads und Umsatzkennzahlen für die Beta zentral vorbereiten.
        </p>
        <div v-if="success" class="mt-4 rounded-lg border border-success/30 bg-success/10 px-4 py-3 text-sm text-success">
            {{ success }}
        </div>
        <div v-if="campaignStatusError" class="mt-4 rounded-lg border border-danger/30 bg-danger/10 px-4 py-3 text-sm text-danger">
            {{ campaignStatusError }}
        </div>
    </section>
</template>

