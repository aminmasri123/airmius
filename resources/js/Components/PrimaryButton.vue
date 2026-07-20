<script setup>
import AppButton from './UI/AppButton.vue';

defineProps({
    type: {
        type: String,
        default: 'submit',
    },
});
</script>

<template>
    <AppButton :type="type" variant="primary">
        <slot />
    </AppButton>
</template>
