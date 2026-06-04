<script setup>
defineProps({
    stats: { type: Array, default: () => [] },
})
</script>

<template>
    <section class="grid grid-cols-2 gap-3 xl:grid-cols-4">
        <div v-for="item in stats" :key="item.key" class="surface-card overflow-hidden p-4">
            <div class="flex items-start justify-between gap-3">
                <div>
                    <p class="text-[11px] font-bold uppercase tracking-wide text-secondary">{{ item.label }}</p>
                    <p class="mt-2 text-2xl font-black text-primary">{{ item.value }}</p>
                    <p class="mt-1 text-xs font-semibold text-secondary">{{ item.meta }}</p>
                </div>
                <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-2xl bg-gradient-to-br text-white shadow-lg" :class="item.tone">
                    <i :class="[item.icon, 'text-xl']"></i>
                </span>
            </div>
        </div>
    </section>
</template>
