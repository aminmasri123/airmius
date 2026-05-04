<script setup>
import { computed, onMounted, ref, useAttrs } from 'vue';

defineProps({
    modelValue: String,
});

defineEmits(['update:modelValue']);
defineOptions({ inheritAttrs: false });

const attrs = useAttrs();
const input = ref(null);
const showPassword = ref(false);
const isPassword = computed(() => attrs.type === 'password');
const inputType = computed(() => isPassword.value && showPassword.value ? 'text' : attrs.type);
const inputAttrs = computed(() => {
    const { class: _class, ...rest } = attrs;

    return rest;
});

onMounted(() => {
    if (input.value.hasAttribute('autofocus')) {
        input.value.focus();
    }
});

defineExpose({ focus: () => input.value.focus() });
</script>

<template>
    <div v-if="isPassword" class="relative" :class="attrs.class">
        <input
            ref="input"
            v-bind="inputAttrs"
            :type="inputType"
            class="block w-full rounded-lg border-border bg-inputBg pr-11 text-primary shadow-sm placeholder:text-secondary/70 focus:border-borderHover focus:ring-borderHover"
            :value="modelValue"
            @input="$emit('update:modelValue', $event.target.value)"
        >
        <button
            type="button"
            class="absolute inset-y-0 right-2 my-auto flex h-8 w-8 items-center justify-center rounded-md text-secondary transition hover:bg-muted hover:text-primary"
            :aria-label="showPassword ? 'Kennwort verbergen' : 'Kennwort anzeigen'"
            :title="showPassword ? 'Kennwort verbergen' : 'Kennwort anzeigen'"
            @click="showPassword = !showPassword"
        >
            <i :class="showPassword ? 'las la-eye-slash' : 'las la-eye'" class="text-lg"></i>
        </button>
    </div>

    <input
        v-else
        ref="input"
        v-bind="attrs"
        class="rounded-lg border-border bg-inputBg text-primary shadow-sm placeholder:text-secondary/70 focus:border-borderHover focus:ring-borderHover"
        :value="modelValue"
        @input="$emit('update:modelValue', $event.target.value)"
    >
</template>
