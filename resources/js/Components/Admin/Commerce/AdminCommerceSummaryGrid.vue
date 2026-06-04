<script setup>
defineProps({
    cards: {
        type: Array,
        default: () => [],
    },
})
</script>

<template>
    <section class="grid gap-4 md:grid-cols-3 xl:grid-cols-6">
        <div v-for="card in cards" :key="card.label" class="rounded-lg border border-border bg-card p-4">
            <p class="text-xs uppercase text-secondary">{{ card.label }}</p>
            <p class="mt-2 text-xl font-bold text-primary">{{ card.value }}</p>
        </div>
    </section>
</template>
