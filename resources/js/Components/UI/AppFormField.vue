<script setup>
defineProps({
    id: {
        type: String,
        default: '',
    },
    label: {
        type: String,
        default: '',
    },
    hint: {
        type: String,
        default: '',
    },
    error: {
        type: String,
        default: '',
    },
    required: {
        type: Boolean,
        default: false,
    },
});
</script>

<template>
    <div class="space-y-1.5">
        <label v-if="label" :for="id || undefined" class="block text-sm font-medium text-primary">
            {{ label }}
            <span v-if="required" class="text-error" aria-hidden="true">*</span>
        </label>

        <slot />

        <p v-if="error" class="text-sm text-error">
            {{ error }}
        </p>

        <p v-else-if="hint" class="text-xs leading-5 text-secondary">
            {{ hint }}
        </p>
    </div>
</template>
