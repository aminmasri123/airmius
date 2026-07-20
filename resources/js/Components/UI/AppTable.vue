<script setup>
import AppEmptyState from './AppEmptyState.vue';

defineProps({
    empty: {
        type: Boolean,
        default: false,
    },
    emptyTitle: {
        type: String,
        default: 'Keine Eintraege',
    },
    emptyDescription: {
        type: String,
        default: '',
    },
});
</script>

<template>
    <div class="overflow-hidden rounded-lg border border-border bg-card">
        <div v-if="$slots.toolbar" class="border-b border-border p-3">
            <slot name="toolbar" />
        </div>

        <div v-if="!empty" class="overflow-x-auto">
            <table class="min-w-full divide-y divide-border text-left text-sm">
                <thead v-if="$slots.head" class="bg-table text-xs uppercase text-secondary">
                    <slot name="head" />
                </thead>
                <tbody class="divide-y divide-border bg-card text-primary">
                    <slot />
                </tbody>
            </table>
        </div>

        <div v-else class="p-4">
            <AppEmptyState :title="emptyTitle" :description="emptyDescription" compact>
                <template v-if="$slots.emptyActions" #actions>
                    <slot name="emptyActions" />
                </template>
            </AppEmptyState>
        </div>
    </div>
</template>
