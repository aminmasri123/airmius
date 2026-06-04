<script setup>
defineProps({
    metrics: {
        type: Array,
        default: () => [],
    },
    gridClass: {
        type: [String, Array, Object],
        default: 'grid gap-3',
    },
    cardClass: {
        type: [String, Array, Object],
        default: 'rounded-2xl border border-border bg-card/80 p-3',
    },
    labelClass: {
        type: [String, Array, Object],
        default: 'text-xs font-bold uppercase text-secondary',
    },
    valueClass: {
        type: [String, Array, Object],
        default: 'mt-1 text-xl font-bold text-primary',
    },
})
</script>

<template>
    <div :class="gridClass">
        <div
            v-for="metric in metrics"
            :key="metric.key"
            :class="[cardClass, metric.cardClass]"
        >
            <p :class="metric.labelClass || labelClass">{{ metric.label }}</p>
            <p :class="metric.valueClass || valueClass">{{ metric.value }}</p>
        </div>
    </div>
</template>
