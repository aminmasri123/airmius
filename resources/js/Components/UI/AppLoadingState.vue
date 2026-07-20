<script setup>
defineProps({
    label: {
        type: String,
        default: 'Wird geladen...',
    },
    inline: {
        type: Boolean,
        default: false,
    },
});
</script>

<template>
    <div
        class="text-secondary"
        :class="inline ? 'inline-flex items-center gap-2 text-sm' : 'flex items-center justify-center gap-3 rounded-lg border border-border bg-inputBg px-4 py-6 text-sm'"
        role="status"
        aria-live="polite"
    >
        <span class="size-4 animate-spin rounded-full border-2 border-current border-r-transparent" aria-hidden="true"></span>
        <span>{{ label }}</span>
    </div>
</template>
