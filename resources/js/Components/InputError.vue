<script setup>
defineProps({
    message: String,
    id: {
        type: String,
        default: '',
    },
});
</script>

<template>
    <div v-show="message" role="alert" aria-live="assertive">
        <p :id="id || undefined" class="text-sm text-red-600">
            {{ message }}
        </p>
    </div>
</template>
