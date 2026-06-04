<script setup>
defineProps({
    flash: {
        type: Object,
        default: () => ({}),
    },
})
</script>

<template>
    <section class="surface-card p-5">
        <p class="text-xs font-semibold uppercase tracking-wide text-air-blue">Käufe & Abos</p>
        <h1 class="mt-1 text-2xl font-bold text-primary">Shop, Rechnungen und Angebote</h1>
        <p class="mt-2 max-w-3xl text-sm text-secondary">
            Verwalte Marketplace-Käufe, Kurse, Ads, Outfit-Abos, Konto-Abos, Warenkorb und Rechnungen an einem Ort.
        </p>
        <div v-if="flash?.success" class="mt-4 rounded-lg border border-success/30 bg-success/10 px-4 py-3 text-sm text-success">
            {{ flash.success }}
        </div>
        <div v-if="flash?.error" class="mt-4 rounded-lg border border-danger/30 bg-danger/10 px-4 py-3 text-sm text-danger">
            {{ flash.error }}
        </div>
    </section>
</template>

