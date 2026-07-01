<script setup>
import { computed, onBeforeUnmount, onMounted, ref, watch } from 'vue'
import { useI18n } from 'vue-i18n'

const props = defineProps({
    modelValue: { type: String, default: '' },
    options: { type: Array, default: () => [] },
    placeholder: { type: String, default: 'Suchen' },
    labelKey: { type: String, default: 'name' },
    valueKey: { type: String, default: 'name' },
    translationPrefix: { type: String, default: '' },
    categoryTranslationPrefix: { type: String, default: '' },
    emptyText: { type: String, default: 'Keine Sportart gefunden.' },
    allowCustom: { type: Boolean, default: true },
})

const emit = defineEmits(['update:modelValue'])
const { t, te, locale } = useI18n()
const open = ref(false)
const query = ref(props.modelValue || '')
const selectRef = ref(null)

const optionLabel = (option) => {
    const key = props.translationPrefix && option.slug ? `${props.translationPrefix}.${option.slug}` : null

    return key && te(key) ? t(key) : (option[props.labelKey] || '')
}

const optionCategory = (option) => {
    if (!option.category) return ''

    const legacyCategories = {
        'Olympisch': 'olympic',
        'Olympisch / weltweit': 'olympic_worldwide',
        'IOC-anerkannt': 'ioc_recognized',
        'Weltweit': 'worldwide',
    }

    const category = legacyCategories[option.category] || option.category
    const key = props.categoryTranslationPrefix ? `${props.categoryTranslationPrefix}.${category}` : null

    return key && te(key) ? t(key) : option.category
}

const selectedLabel = (value) => {
    if (!value) return ''

    const option = props.options.find((option) =>
        option[props.valueKey] === value
        || option.slug === value
        || option[props.labelKey] === value
    )

    if (option) {
        return optionLabel(option)
    }

    return value
}

watch(() => props.modelValue, (value) => {
    query.value = selectedLabel(value)
}, { immediate: true })

watch(locale, () => {
    query.value = selectedLabel(props.modelValue)
})

const filteredOptions = computed(() => {
    const search = query.value.trim().toLowerCase()

    return props.options
        .filter((option) => {
            if (!search) return true

            return [
                optionLabel(option),
                option[props.labelKey],
                optionCategory(option),
                option.category,
                option.slug,
            ].filter(Boolean).some((value) => String(value).toLowerCase().includes(search))
        })
        .slice(0, 30)
})

const selectOption = (option) => {
    const value = option[props.valueKey] || option[props.labelKey] || ''

    query.value = optionLabel(option)
    emit('update:modelValue', value)
    open.value = false
}

const updateQuery = (value) => {
    query.value = value
    if (props.allowCustom) {
        emit('update:modelValue', value)
    }
    open.value = true
}

const clear = () => {
    query.value = ''
    emit('update:modelValue', '')
    open.value = false
}

const closeOnOutsideClick = (event) => {
    if (!selectRef.value?.contains(event.target)) {
        close()
    }
}

const close = () => {
    open.value = false

    if (!props.allowCustom) {
        query.value = selectedLabel(props.modelValue)
    }
}

onMounted(() => {
    document.addEventListener('pointerdown', closeOnOutsideClick)
})

onBeforeUnmount(() => {
    document.removeEventListener('pointerdown', closeOnOutsideClick)
})
</script>

<template>
    <div ref="selectRef" class="relative">
        <div class="flex rounded-lg border border-border bg-inputBg focus-within:border-borderHover">
            <input
                :value="query"
                type="text"
                class="min-w-0 flex-1 rounded-lg border-0 bg-transparent px-3 py-2 text-sm text-primary placeholder-secondary focus:ring-0"
                :placeholder="placeholder"
                @focus="open = true"
                @input="updateQuery($event.target.value)"
                @keydown.escape="close"
                @blur="setTimeout(close, 120)"
            >
            <button
                v-if="query"
                type="button"
                class="px-3 text-secondary hover:text-primary"
                @click="clear"
            >
                <i class="las la-times"></i>
            </button>
        </div>

        <div
            v-if="open"
            class="absolute z-30 mt-1 max-h-64 w-full overflow-y-auto rounded-lg border border-border bg-card py-1 shadow-lg"
        >
            <button
                v-for="option in filteredOptions"
                :key="option.slug || option[labelKey]"
                type="button"
                class="block w-full px-3 py-2 text-start text-sm hover:bg-muted"
                @mousedown.prevent="selectOption(option)"
            >
                <span class="block font-medium text-primary">{{ optionLabel(option) }}</span>
                <span v-if="optionCategory(option)" class="block text-xs text-secondary">{{ optionCategory(option) }}</span>
            </button>

            <div v-if="!filteredOptions.length" class="px-3 py-2 text-sm text-secondary">
                {{ emptyText }}
            </div>
        </div>
    </div>
</template>
