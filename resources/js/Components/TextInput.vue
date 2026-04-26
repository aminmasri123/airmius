<script setup>
import { onMounted, ref } from 'vue';

defineProps({
    modelValue: String,
});

defineEmits(['update:modelValue']);

const input = ref(null);

onMounted(() => {
    if (input.value.hasAttribute('autofocus')) {
        input.value.focus();
    }
});

defineExpose({ focus: () => input.value.focus() });
</script>

<template>
    <input
        ref="input"
        class="rounded-lg border-border bg-inputBg text-primary shadow-sm placeholder:text-secondary/70 focus:border-borderHover focus:ring-borderHover"
        :value="modelValue"
        @input="$emit('update:modelValue', $event.target.value)"
    >
</template>
