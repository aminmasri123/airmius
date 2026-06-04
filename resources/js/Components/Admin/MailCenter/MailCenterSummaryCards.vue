<script setup>
import { computed } from 'vue'

const props = defineProps({
    summary: {
        type: Object,
        default: () => ({}),
    },
})

const cards = computed(() => [
    { key: 'total', label: 'Protokoll', value: props.summary.total || 0, class: 'text-primary' },
    { key: 'sent', label: 'Gesendet', value: props.summary.sent || 0, class: 'text-emerald-300' },
    { key: 'failed', label: 'Fehlgeschlagen', value: props.summary.failed || 0, class: 'text-rose-300' },
    { key: 'skipped', label: 'Gedrosselt', value: props.summary.skipped || 0, class: 'text-amber-300' },
    { key: 'resolved', label: 'Erledigt', value: props.summary.resolved || 0, class: 'text-sky-300' },
])
</script>

<template>
    <section class="grid gap-4 md:grid-cols-2 xl:grid-cols-5">
        <div
            v-for="card in cards"
            :key="card.key"
            class="surface-card p-4"
        >
            <p class="text-xs font-semibold uppercase text-secondary">{{ card.label }}</p>
            <p :class="['mt-2 text-2xl font-bold', card.class]">{{ card.value }}</p>
        </div>
    </section>
</template>
