<script setup>
defineProps({
    upcoming: { type: Array, default: () => [] },
    categories: { type: Array, default: () => [] },
    deadlineClass: { type: Function, required: true },
    deadlineLabel: { type: Function, required: true },
})
</script>

<template>
    <aside class="space-y-5">
        <section class="rounded-2xl border border-border bg-card p-5">
            <p class="text-xs font-semibold uppercase tracking-[0.16em] text-air-blue">Nächste Termine</p>
            <div class="mt-4 space-y-3">
                <article v-for="item in upcoming" :key="`${item.id}-${item.type}`" class="rounded-xl border border-border bg-inputBg p-3">
                    <div class="flex items-start justify-between gap-3">
                        <div class="min-w-0">
                            <p class="truncate text-sm font-black text-primary">{{ item.name }}</p>
                            <p class="truncate text-xs text-secondary">{{ item.vendor || item.label }}</p>
                        </div>
                        <span class="shrink-0 rounded-full bg-card px-2 py-1 text-[11px] font-bold text-secondary">
                            {{ item.label }}
                        </span>
                    </div>
                    <div class="mt-3 flex items-center justify-between gap-3 text-sm">
                        <span class="font-bold text-primary">{{ item.date || '-' }}</span>
                        <span :class="['font-bold', deadlineClass(item.days)]">{{ deadlineLabel(item.days) }}</span>
                    </div>
                </article>
                <p v-if="!upcoming.length" class="text-sm text-secondary">Keine anstehenden Zahlungen oder Fristen.</p>
            </div>
        </section>

        <section class="rounded-2xl border border-border bg-card p-5">
            <p class="text-xs font-semibold uppercase tracking-[0.16em] text-air-blue">Kosten nach Kategorie</p>
            <div class="mt-4 space-y-3">
                <article v-for="category in categories" :key="category.category" class="rounded-xl border border-border bg-inputBg p-3">
                    <div class="flex items-center justify-between gap-3">
                        <p class="text-sm font-black text-primary">{{ category.label }}</p>
                        <span class="rounded-full bg-card px-2 py-1 text-xs font-bold text-secondary">{{ category.count }}</span>
                    </div>
                    <p class="mt-2 text-sm font-bold text-secondary">{{ category.monthly_total }} / Monat</p>
                </article>
                <p v-if="!categories.length" class="text-sm text-secondary">Noch keine aktiven Kosten erfasst.</p>
            </div>
        </section>
    </aside>
</template>

