<script setup>
import { ref, watch } from 'vue'

defineOptions({ inheritAttrs: false })

const props = defineProps({
    modelValue: { type: String, default: '' },
    placeholder: { type: String, default: 'TT.MM.JJJJ' },
    calendar: { type: Boolean, default: false },
    trailingSeparators: { type: Boolean, default: false },
})

const emit = defineEmits(['update:modelValue'])
const displayValue = ref(toDisplayValue(props.modelValue))
const input = ref(null)
const calendarInput = ref(null)
const calendarLabel = () => ({de:'Datum im Kalender wählen',en:'Choose date from calendar',fr:'Choisir une date dans le calendrier',ar:'اختيار تاريخ من التقويم'}[document.documentElement.lang.split('-')[0]] || 'Choose date from calendar')
const openCalendar = () => { try { if (calendarInput.value.showPicker) calendarInput.value.showPicker(); else { calendarInput.value.focus(); calendarInput.value.click() } } catch { calendarInput.value.focus() } }
const chooseDate = event => { const value = event.target.value; displayValue.value = toDisplayValue(value); input.value?.setCustomValidity(''); emit('update:modelValue', value) }

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

function formatDigits(value, trailing = false) {
    let result = ''
    for (let index = 0; index < value.length; index += 1) {
        if (index === 2 || index === 4) result += '.'
        result += value[index]
    }
    if (trailing && [2,4].includes(value.length)) result += '.'
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
    displayValue.value = formatDigits(digits, props.trailingSeparators && !event.inputType?.startsWith('delete'))
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
    <div v-if="calendar" class="relative min-w-0">
        <input ref="input" v-bind="$attrs" :value="displayValue" type="text" inputmode="numeric" maxlength="10" :placeholder="placeholder" class="w-full pr-12" @input="handleInput" />
        <button type="button" class="absolute right-1 top-1/2 -translate-y-1/2 rounded p-2" :aria-label="calendarLabel()" :disabled="$attrs.disabled" @click="openCalendar"><i class="las la-calendar text-xl" aria-hidden="true"></i></button>
        <input ref="calendarInput" type="date" tabindex="-1" aria-hidden="true" class="pointer-events-none absolute h-px w-px opacity-0" :value="modelValue" :min="$attrs.min" :max="$attrs.max" @change="chooseDate" />
    </div>
    <input v-else
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
