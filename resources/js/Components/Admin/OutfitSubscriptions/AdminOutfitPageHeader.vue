<script setup>
defineProps({
    summary: { type: Object, default: () => ({}) },
})
</script>

<template>
    <section class="rounded-lg border border-border bg-card p-5">
        <div class="flex flex-col gap-4 lg:flex-row lg:items-end lg:justify-between">
            <div>
                <p class="text-sm font-semibold uppercase text-accent">Sportkleidung-Abo Modul</p>
                <h1 class="mt-2 text-2xl font-bold text-primary">Outfit-Abo Pläne</h1>
                <p class="mt-2 max-w-3xl text-sm text-secondary">
                    Verwalte monatliche Sportkleidung-Abos, Sponsor-Rabatte und Branding-Regeln. Diese Seite ist über eigene Permissions geschützt.
                </p>
            </div>
            <div class="grid grid-cols-2 gap-3 sm:grid-cols-4 lg:grid-cols-6">
                <div class="rounded-lg bg-inputBg p-3">
                    <p class="text-xs uppercase text-secondary">Pläne</p>
                    <p class="mt-1 text-xl font-bold text-primary">{{ summary.plans || 0 }}</p>
                </div>
                <div class="rounded-lg bg-inputBg p-3">
                    <p class="text-xs uppercase text-secondary">Aktiv</p>
                    <p class="mt-1 text-xl font-bold text-primary">{{ summary.activePlans || 0 }}</p>
                </div>
                <div class="rounded-lg bg-inputBg p-3">
                    <p class="text-xs uppercase text-secondary">Sponsor</p>
                    <p class="mt-1 text-xl font-bold text-primary">{{ summary.sponsoredPlans || 0 }}</p>
                </div>
                <div class="rounded-lg bg-inputBg p-3">
                    <p class="text-xs uppercase text-secondary">Abos</p>
                    <p class="mt-1 text-xl font-bold text-primary">{{ summary.subscriptions || 0 }}</p>
                </div>
                <div class="rounded-lg bg-inputBg p-3">
                    <p class="text-xs uppercase text-secondary">Offen</p>
                    <p class="mt-1 text-xl font-bold text-primary">{{ summary.pendingPayments || 0 }}</p>
                </div>
                <div class="rounded-lg bg-inputBg p-3">
                    <p class="text-xs uppercase text-secondary">Bezahlt</p>
                    <p class="mt-1 text-xl font-bold text-primary">{{ summary.paidSubscriptions || 0 }}</p>
                </div>
            </div>
        </div>
    </section>
</template>

