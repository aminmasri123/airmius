<script setup>
defineProps({
    profileStats: { type: Array, default: () => [] },
})
</script>

<template>
    <section class="grid grid-cols-2 gap-2 sm:grid-cols-3 lg:grid-cols-5 xl:grid-cols-5">
        <div
            v-for="stat in profileStats"
            :key="stat.label"
            class="rounded-xl border border-border bg-card p-3 shadow-sm sm:p-4"
        >
            <div class="text-xl font-bold text-primary sm:text-2xl">{{ stat.value }}</div>
            <div class="mt-1 text-[11px] font-semibold uppercase text-secondary sm:text-xs">{{ stat.label }}</div>
        </div>
    </section>
</template>
