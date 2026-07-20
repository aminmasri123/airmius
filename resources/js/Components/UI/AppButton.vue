<script setup>
import { computed } from 'vue';

const props = defineProps({
    type: {
        type: String,
        default: 'button',
    },
    variant: {
        type: String,
        default: 'primary',
        validator: (value) => ['primary', 'secondary', 'danger', 'ghost', 'subtle'].includes(value),
    },
    size: {
        type: String,
        default: 'md',
        validator: (value) => ['xs', 'sm', 'md', 'lg'].includes(value),
    },
    block: {
        type: Boolean,
        default: false,
    },
    loading: {
        type: Boolean,
        default: false,
    },
    disabled: {
        type: Boolean,
        default: false,
    },
    iconOnly: {
        type: Boolean,
        default: false,
    },
});

const baseClass = 'inline-flex shrink-0 items-center justify-center gap-2 rounded-lg border font-semibold leading-none transition duration-150 focus:outline-none focus:ring-2 focus:ring-borderHover focus:ring-offset-2 focus:ring-offset-bg disabled:pointer-events-none disabled:opacity-50';

const variantClasses = {
    primary: 'border-transparent bg-buttonPrimary text-buttonTextPrimary shadow-sm hover:bg-buttonPrimaryHover hover:text-buttonTextPrimaryHover',
    secondary: 'border-border bg-card text-buttonTextSecondary shadow-sm hover:border-borderHover hover:text-buttonTextSecondaryHover',
    danger: 'border-transparent bg-error text-white shadow-sm hover:opacity-90',
    ghost: 'border-transparent bg-transparent text-primary hover:bg-muted',
    subtle: 'border-border bg-inputBg text-primary hover:bg-muted',
};

const sizeClasses = {
    xs: 'h-8 px-3 text-xs',
    sm: 'h-9 px-3 text-sm',
    md: 'h-10 px-4 text-sm',
    lg: 'h-11 px-5 text-base',
};

const iconOnlyClasses = {
    xs: 'w-8 px-0',
    sm: 'w-9 px-0',
    md: 'w-10 px-0',
    lg: 'w-11 px-0',
};

const classes = computed(() => [
    baseClass,
    variantClasses[props.variant],
    sizeClasses[props.size],
    props.iconOnly ? iconOnlyClasses[props.size] : '',
    props.block ? 'w-full' : '',
]);
</script>

<template>
    <button :type="type" :disabled="disabled || loading" :class="classes">
        <span
            v-if="loading"
            class="size-4 animate-spin rounded-full border-2 border-current border-r-transparent"
            aria-hidden="true"
        />
        <slot />
    </button>
</template>
