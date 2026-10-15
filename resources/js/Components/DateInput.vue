<script setup>
import { ref, watch } from 'vue'

defineOptions({ inheritAttrs: false })

const props = defineProps({
    modelValue: { type: String, default: '' },
    placeholder: { type: String, default: 'TT.MM.JJJJ' },
})

const emit = defineEmits(['update:modelValue'])
const displayValue = ref(toDisplayValue(props.modelValue))
const input = ref(null)

watch(() => props.modelValue, (value) => {
    // While typing an incomplete date the form model is intentionally empty.
    // Keep the visible partial value until the date is complete.
    if (!value && displayValue.value && displayValue.value.length < 10) return
    const normalized = toDisplayValue(value)
    if (normalized !== displayValue.value) displayValue.value = normalized
    if (input.value && (!normalized || toIso(normalized))) {
        input.value.setCustomValidity('')
    }
})

function toDisplayValue(value) {
    const text = String(value || '').trim()
    const isoMatch = /^(\d{4})-(\d{2})-(\d{2})/.exec(text)
    if (isoMatch) return `${isoMatch[3]}.${isoMatch[2]}.${isoMatch[1]}`
    return formatDigits(text.replace(/\D/g, '').slice(0, 8))
}

function formatDigits(value) {
    let result = ''
    for (let index = 0; index < value.length; index += 1) {
        if (index === 2 || index === 4) result += '.'
        result += value[index]
    }
    return result
}

function toIso(value) {
    const match = /^(\d{2})\.(\d{2})\.(\d{4})$/.exec(value)
    if (!match) return ''

    const day = Number(match[1])
    const month = Number(match[2])
    const year = Number(match[3])
    const date = new Date(year, month - 1, day)
    if (date.getFullYear() !== year || date.getMonth() !== month - 1 || date.getDate() !== day) return ''

    return `${match[3]}-${match[2]}-${match[1]}`
}

function handleInput(event) {
    const rawValue = String(event.target.value || '').trim()
    const isoMatch = /^(\d{4})-(\d{2})-(\d{2})$/.exec(rawValue)

    if (isoMatch) {
        displayValue.value = `${isoMatch[3]}.${isoMatch[2]}.${isoMatch[1]}`
        event.target.value = displayValue.value
        event.target.setCustomValidity('')
        emit('update:modelValue', toIso(displayValue.value))
        return
    }

    const digits = rawValue.replace(/\D/g, '').slice(0, 8)
    displayValue.value = formatDigits(digits)
    event.target.value = displayValue.value
    const isoValue = toIso(displayValue.value)
    event.target.setCustomValidity(
        displayValue.value && !isoValue ? invalidDateMessage() : '',
    )
    emit('update:modelValue', isoValue)
}

function invalidDateMessage() {
    const language = String(document.documentElement.lang || 'de').toLowerCase()
    if (language.startsWith('en')) return 'Enter a valid date in DD.MM.YYYY format.'
    if (language.startsWith('fr')) return 'Saisissez une date valide au format JJ.MM.AAAA.'
    if (language.startsWith('ar')) return 'أدخل تاريخًا صالحًا بالتنسيق يوم.شهر.سنة.'
    return 'Bitte ein gültiges Datum im Format TT.MM.JJJJ eingeben.'
}
</script>

<template>
    <input
        ref="input"
        v-bind="$attrs"
        :value="displayValue"
        type="text"
        inputmode="numeric"
        maxlength="10"
        :placeholder="placeholder"
        @input="handleInput"
    />
</template>
