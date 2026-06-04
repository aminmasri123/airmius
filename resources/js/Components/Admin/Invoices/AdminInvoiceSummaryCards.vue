<script setup>
defineProps({
    summary: { type: Object, default: () => ({}) },
})
</script>

<template>
    <section class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
        <div class="rounded-2xl border border-border bg-card p-5">
            <p class="text-xs font-bold uppercase tracking-wide text-secondary">Alle Rechnungen</p>
            <p class="mt-3 text-3xl font-black text-primary">{{ summary.count || 0 }}</p>
        </div>
        <div class="rounded-2xl border border-border bg-card p-5">
            <p class="text-xs font-bold uppercase tracking-wide text-secondary">Offen</p>
            <p class="mt-3 text-3xl font-black text-primary">{{ summary.open || 0 }}</p>
        </div>
        <div class="rounded-2xl border border-border bg-card p-5">
            <p class="text-xs font-bold uppercase tracking-wide text-secondary">Bezahlt</p>
            <p class="mt-3 text-3xl font-black text-primary">{{ summary.paid || 0 }}</p>
        </div>
        <div class="rounded-2xl border border-border bg-card p-5">
            <p class="text-xs font-bold uppercase tracking-wide text-secondary">Umsatz bezahlt</p>
            <p class="mt-3 text-3xl font-black text-primary">{{ summary.revenue || '0,00 EUR' }}</p>
        </div>
    </section>
</template>
