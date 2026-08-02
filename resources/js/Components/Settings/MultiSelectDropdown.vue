<script setup>
import { computed, onBeforeUnmount, onMounted, ref } from 'vue'

const props = defineProps({
    modelValue: {
        type: Array,
        default: () => [],
    },
    options: {
        type: Array,
        default: () => [],
    },
    labelKey: {
        type: String,
        default: 'name',
    },
    valueKey: {
        type: String,
        default: 'id',
    },
    placeholder: {
        type: String,
        default: 'Auswählen',
    },
    emptyText: {
        type: String,
        default: 'Keine Optionen verfügbar.',
    },
    selectedText: {
        type: Function,
        default: null,
    },
})

const emit = defineEmits(['update:modelValue'])
const root = ref(null)
const open = ref(false)

const selectedValues = computed(() => new Set(props.modelValue.map((value) => String(value))))
const selectedOptions = computed(() => props.options.filter((option) => selectedValues.value.has(String(option[props.valueKey]))))
const buttonLabel = computed(() => {
    if (!selectedOptions.value.length) return props.placeholder
    if (props.selectedText) return props.selectedText(selectedOptions.value)

    return selectedOptions.value.map((option) => option[props.labelKey]).join(', ')
})

const toggle = (option) => {
    const value = option[props.valueKey]
    const next = selectedValues.value.has(String(value))
        ? props.modelValue.filter((item) => String(item) !== String(value))
        : [...props.modelValue, value]

    emit('update:modelValue', next)
}

const closeOnOutsideClick = (event) => {
    if (!root.value?.contains(event.target)) open.value = false
}

onMounted(() => document.addEventListener('pointerdown', closeOnOutsideClick))
onBeforeUnmount(() => document.removeEventListener('pointerdown', closeOnOutsideClick))
</script>

<template>
    <div ref="root" class="relative">
        <button
            type="button"
            class="flex min-h-11 w-full items-center justify-between gap-3 rounded-lg border border-border bg-inputBg px-3 py-2 text-left text-sm text-primary transition hover:border-borderHover"
            :aria-expanded="open"
            @click="open = !open"
        >
            <span class="min-w-0 truncate" :class="selectedOptions.length ? 'text-primary' : 'text-secondary'">
                {{ buttonLabel }}
            </span>
            <i class="las shrink-0" :class="open ? 'la-angle-up' : 'la-angle-down'" aria-hidden="true"></i>
        </button>

        <div
            v-if="open"
            class="absolute z-40 mt-1 max-h-72 w-full overflow-y-auto rounded-lg border border-border bg-card p-2 shadow-xl"
        >
            <label
                v-for="option in options"
                :key="option[valueKey]"
                class="flex cursor-pointer items-start gap-2 rounded-md px-2 py-2 text-sm hover:bg-muted"
            >
                <input
                    type="checkbox"
                    class="mt-0.5 rounded border-border bg-inputBg text-buttonPrimary"
                    :checked="selectedValues.has(String(option[valueKey]))"
                    @change="toggle(option)"
                />
                <span class="min-w-0">
                    <span class="block font-semibold text-primary">{{ option[labelKey] }}</span>
                    <span v-if="option.description" class="mt-0.5 block text-xs text-secondary">{{ option.description }}</span>
                </span>
            </label>

            <p v-if="!options.length" class="px-2 py-2 text-sm text-secondary">
                {{ emptyText }}
            </p>
        </div>
    </div>
</template>
