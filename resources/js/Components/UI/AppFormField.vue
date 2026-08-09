<script setup>
import { computed } from 'vue';

const props = defineProps({
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

const hintId = computed(() => props.id && props.hint && !props.error ? `${props.id}-hint` : undefined);
const errorId = computed(() => props.id && props.error ? `${props.id}-error` : undefined);
const describedBy = computed(() => errorId.value || hintId.value);
</script>

<template>
    <div class="space-y-1.5">
        <label v-if="label" :for="id || undefined" class="block text-sm font-medium text-primary">
            {{ label }}
            <span v-if="required" class="text-error" aria-hidden="true">*</span>
        </label>

        <slot
            :describedby="describedBy"
            :invalid="Boolean(error)"
        />

        <p
            v-if="error"
            :id="errorId"
            class="text-sm text-error"
            role="alert"
            aria-live="assertive"
        >
            {{ error }}
        </p>

        <p v-else-if="hint" :id="hintId" class="text-xs leading-5 text-secondary">
            {{ hint }}
        </p>
    </div>
</template>
