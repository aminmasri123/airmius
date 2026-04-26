<script setup>
defineProps({
    type: {
        type: String,
        default: 'submit',
    },
});
</script>

<template>
    <button :type="type" class="inline-flex items-center justify-center px-4 py-2 bg-buttonPrimary border border-transparent rounded-lg font-semibold text-xs text-buttonTextPrimary uppercase tracking-widest shadow-sm hover:bg-buttonPrimaryHover hover:text-buttonTextPrimaryHover focus:bg-buttonPrimaryHover active:scale-[0.99] focus:outline-none focus:ring-2 focus:ring-borderHover focus:ring-offset-2 focus:ring-offset-bg disabled:opacity-50 transition ease-in-out duration-150">
        <slot />
    </button>
</template>
