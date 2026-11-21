<script setup>
import { computed, nextTick, ref, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import translations from '@/i18n/clubMetadataLocalization.json'
import { validateMetadataOptions } from '@/support/metadataOptions'
import { safeFieldMessage } from '@/services/formFeedbackMessages'

const props = defineProps({ modelValue: { type: Array, default: () => [''] }, disabled: Boolean, serverErrors: { type: Object, default: () => ({}) } })
const emit = defineEmits(['update:modelValue', 'change'])
const { locale } = useI18n()
const t = key => (translations[locale.value] || translations.de)[key]
let sequence = 0
const rows = ref([])
const checked = ref(false)
const touched = ref(new Set())
const inputs = new Map()
const prefix = `metadata-choice-${Math.random().toString(36).slice(2)}`
const reset = values => { rows.value = (values.length ? values : ['']).map(value => ({ id: ++sequence, value })); checked.value = false; touched.value = new Set() }
reset(props.modelValue)
watch(() => props.modelValue, values => {
    if (JSON.stringify(values) !== JSON.stringify(rows.value.map(row => row.value))) reset(values)
})
const validation = computed(() => validateMetadataOptions(rows.value.map(row => row.value)))
const message = index => {
    const server = props.serverErrors[`options.${index}`] || (index === 0 ? props.serverErrors.options : null)
    if (server) return safeFieldMessage(server, locale.value)
    return (checked.value || touched.value.has(rows.value[index].id)) && validation.value.rows[index] ? t(validation.value.rows[index]) : ''
}
const changed = () => { emit('update:modelValue', rows.value.map(row => row.value)); emit('change') }
const edit = row => { touched.value.add(row.id); changed() }
const add = async () => {
    if (props.disabled || rows.value.length >= 50) return
    const row = { id: ++sequence, value: '' }
    rows.value.push(row); changed(); await nextTick(); inputs.get(row.id)?.focus()
}
const remove = index => { if (!props.disabled && rows.value.length > 1) { rows.value.splice(index, 1); changed() } }
const move = (index, delta) => {
    if (props.disabled || index + delta < 0 || index + delta >= rows.value.length) return
    const [row] = rows.value.splice(index, 1); rows.value.splice(index + delta, 0, row); changed()
}
const validate = async () => {
    checked.value = true
    const invalid = validation.value.rows.findIndex(Boolean)
    if (invalid >= 0) { await nextTick(); inputs.get(rows.value[invalid].id)?.focus() }
    return !validation.value.list && invalid < 0
}
defineExpose({ validate })
</script>

<template>
    <fieldset class="min-w-0 space-y-3">
        <legend class="text-sm font-semibold text-primary">{{ t('options') }}</legend>
        <div v-for="(row, index) in rows" :key="row.id" class="min-w-0 rounded-xl border border-border p-3" data-option-row>
            <label :for="`${prefix}-${row.id}`" class="text-xs font-semibold text-secondary">{{ t('optionValue') }} {{ index + 1 }}</label>
            <input :id="`${prefix}-${row.id}`" :ref="el => el ? inputs.set(row.id, el) : inputs.delete(row.id)" v-model="row.value" required maxlength="160" :disabled="disabled" :aria-invalid="Boolean(message(index))" :aria-describedby="message(index) ? `${prefix}-${row.id}-error` : undefined" class="mt-1 w-full min-w-0 rounded-lg border border-border bg-inputBg px-3 py-2 text-sm text-primary" @input="edit(row)">
            <p v-if="message(index)" :id="`${prefix}-${row.id}-error`" role="alert" class="mt-1 text-sm text-error">{{ message(index) }}</p>
            <div class="mt-2 flex justify-end gap-1">
                <button type="button" class="min-h-11 min-w-11 rounded-lg border border-border disabled:opacity-40" :disabled="disabled || index === 0" :aria-label="`${t('moveOptionUp')}: ${index + 1}`" :title="t('moveOptionUp')" @click="move(index, -1)">↑</button>
                <button type="button" class="min-h-11 min-w-11 rounded-lg border border-border disabled:opacity-40" :disabled="disabled || index === rows.length - 1" :aria-label="`${t('moveOptionDown')}: ${index + 1}`" :title="t('moveOptionDown')" @click="move(index, 1)">↓</button>
                <button type="button" class="min-h-11 min-w-11 rounded-lg border border-border text-error disabled:opacity-40" :disabled="disabled || rows.length === 1" :aria-label="`${t('removeOption')}: ${index + 1}`" :title="t('removeOption')" @click="remove(index)"><i class="las la-trash" aria-hidden="true"></i></button>
            </div>
        </div>
        <p v-if="checked && validation.list" role="alert" class="text-sm text-error">{{ t(validation.list) }}</p>
        <button type="button" class="min-h-11 rounded-lg border border-border px-4 py-2 text-sm font-semibold text-primary disabled:opacity-40" :disabled="disabled || rows.length >= 50" @click="add">＋ {{ t('addOption') }}</button>
        <p v-if="rows.length >= 50" class="text-xs text-secondary">{{ t('optionLimit') }}</p>
    </fieldset>
</template>
