<script setup>
defineProps({
    type: {
        type: String,
        default: 'button',
    },
});
</script>

<template>
    <button :type="type" class="inline-flex items-center justify-center px-4 py-2 bg-card border border-border rounded-lg font-semibold text-xs text-buttonTextSecondary uppercase tracking-widest shadow-sm hover:border-borderHover hover:text-buttonTextSecondaryHover focus:outline-none focus:ring-2 focus:ring-borderHover focus:ring-offset-2 focus:ring-offset-bg disabled:opacity-25 transition ease-in-out duration-150">
        <slot />
    </button>
</template>
