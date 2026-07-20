<script setup>
import AppButton from './UI/AppButton.vue';

defineProps({
    type: {
        type: String,
        default: 'button',
    },
});
</script>

<template>
    <AppButton :type="type" variant="danger">
        <slot />
    </AppButton>
</template>
