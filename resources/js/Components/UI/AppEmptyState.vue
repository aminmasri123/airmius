<script setup>
defineProps({
    title: {
        type: String,
        default: '',
    },
    description: {
        type: String,
        default: '',
    },
    compact: {
        type: Boolean,
        default: false,
    },
});
</script>

<template>
    <div
        class="flex flex-col items-center justify-center rounded-lg border border-dashed border-border bg-inputBg text-center"
        :class="compact ? 'px-4 py-6' : 'px-6 py-10'"
    >
        <div v-if="$slots.icon" class="mb-4 flex size-11 items-center justify-center rounded-lg bg-muted text-secondary">
            <slot name="icon" />
        </div>

        <h3 v-if="title" class="text-sm font-semibold text-primary">
            {{ title }}
        </h3>

        <p v-if="description" class="mt-2 max-w-md text-sm leading-6 text-secondary">
            {{ description }}
        </p>

        <div v-if="$slots.actions" class="mt-5 flex flex-wrap items-center justify-center gap-2">
            <slot name="actions" />
        </div>
    </div>
</template>
