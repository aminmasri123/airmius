<script setup>
defineProps({
    summary: { type: Object, required: true },
    clubsCount: { type: Number, default: 0 },
})
</script>

<template>
    <section class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
        <div class="surface-card p-4">
            <p class="text-xs font-semibold uppercase text-secondary">Pläne</p>
            <p class="mt-2 text-2xl font-bold text-primary">{{ summary.plans }}</p>
            <p class="text-sm text-secondary">{{ summary.publicPlans }} aktiv und öffentlich</p>
        </div>
        <div class="surface-card p-4">
            <p class="text-xs font-semibold uppercase text-secondary">Vereins-Abos</p>
            <p class="mt-2 text-2xl font-bold text-primary">{{ summary.clubSubscriptions }}</p>
            <p class="text-sm text-secondary">zugeordneten Vereinen</p>
        </div>
        <div class="surface-card p-4">
            <p class="text-xs font-semibold uppercase text-secondary">Nutzer-Abos</p>
            <p class="mt-2 text-2xl font-bold text-primary">{{ summary.userSubscriptions }}</p>
            <p class="text-sm text-secondary">Sportler, Trainer und Partner</p>
        </div>
        <div class="surface-card p-4">
            <p class="text-xs font-semibold uppercase text-secondary">Vereine</p>
            <p class="mt-2 text-2xl font-bold text-primary">{{ clubsCount }}</p>
            <p class="text-sm text-secondary">für Plan-Zuordnung</p>
        </div>
    </section>
</template>




