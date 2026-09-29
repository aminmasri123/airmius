<script setup>
import { computed, nextTick, onMounted, ref, useId } from 'vue'
import { useI18n } from 'vue-i18n'
import axios from 'axios'
import defaults from '../../data/countries.json'

const props = defineProps({ modelValue: String, label: { type: String, default: 'Land' }, required: Boolean, disabled: Boolean })
const emit = defineEmits(['update:modelValue'])
const { locale } = useI18n()
const id = useId()
const rows = ref(defaults)
const open = ref(false)
const query = ref('')
const active = ref(0)
const search = ref(null)
const canManage = ref(false)
const editing = ref(false)
const busy = ref(false)
const error = ref('')
const extra = ref({ code: '', de: '', en: '' })
const english = computed(() => locale.value.startsWith('en'))
const text = (de, en) => english.value ? en : de
const name = row => row.names[locale.value.split('-')[0]] || row.names.en || row.names.de || row.code
const normalize = value => String(value || '').normalize('NFD').replace(/[\u0300-\u036f]/g, '').toLowerCase()
const selected = computed(() => rows.value.find(row => row.code === props.modelValue?.toUpperCase()))
const filtered = computed(() => rows.value.filter(row => normalize([row.code, ...Object.values(row.names)].join(' ')).includes(normalize(query.value))).sort((a, b) => name(a).localeCompare(name(b), locale.value)))
async function toggle() {
    open.value = !open.value
    query.value = ''
    active.value = 0
    if (open.value) { await nextTick(); search.value?.focus() }
}
function choose(row) {
    emit('update:modelValue', row.code)
    open.value = false
    document.getElementById(`${id}-trigger`)?.focus()
}
function focusTrigger() {
    document.getElementById(`${id}-trigger`)?.focus()
}
async function move(delta) {
    active.value = Math.max(0, Math.min(filtered.value.length - 1, active.value + delta))
    await nextTick()
    document.getElementById(`${id}-${active.value}`)?.scrollIntoView({ block: 'nearest' })
}
async function saveExtra() {
    busy.value = true
    error.value = ''
    try {
        const response = await axios.post('/country-catalog', { code: extra.value.code, names: { de: extra.value.de.trim(), en: extra.value.en.trim() } })
        rows.value = response.data.data
        editing.value = false
        choose(rows.value.find(row => row.code === extra.value.code.trim().toUpperCase()))
    } catch (failure) {
        error.value = Object.values(failure.response?.data?.errors || {}).flat().join(' ') || text('Speichern fehlgeschlagen. Bitte erneut versuchen.', 'Saving failed. Please try again.')
    } finally { busy.value = false }
}
onMounted(async () => {
    try {
        const response = await axios.get('/country-catalog')
        rows.value = response.data.data
        canManage.value = response.data.can_manage
    } catch { /* The bundled complete list remains available offline. */ }
})
</script>

<template>
    <div class="relative min-w-0" @keydown.esc.stop.prevent="open = false" @focusout="event => { if (!event.currentTarget.contains(event.relatedTarget)) open = false }">
        <select class="sr-only" tabindex="-1" :aria-label="label" :value="modelValue?.toUpperCase() || ''" :required="required" :disabled="disabled" @change="emit('update:modelValue', $event.target.value)" @invalid.prevent="focusTrigger">
            <option value=""></option>
            <option v-if="modelValue && !selected" :value="modelValue.toUpperCase()">{{ modelValue }}</option>
            <option v-for="row in rows" :key="row.code" :value="row.code">{{ name(row) }}</option>
        </select>
        <button :id="`${id}-trigger`" :disabled="disabled" type="button" class="mt-1 flex w-full items-center justify-between gap-2 rounded-lg border border-border bg-inputBg px-3 py-2 text-left text-primary disabled:opacity-60" :aria-label="label" aria-haspopup="listbox" :aria-expanded="open" @click="toggle">
            <span class="break-words">{{ selected ? name(selected) : (modelValue || text('Land auswählen', 'Select country')) }}</span>
            <i class="las la-angle-down shrink-0" aria-hidden="true" />
        </button>
        <div v-if="open" class="absolute z-50 mt-1 w-full min-w-0 rounded-lg border border-border bg-inputBg p-2 shadow-lg">
            <input ref="search" v-model="query" class="w-full rounded border-border bg-inputBg text-primary" :placeholder="text('Land oder Kürzel suchen', 'Search country or code')" :aria-label="text('Land suchen', 'Search country')" role="combobox" :aria-controls="`${id}-list`" aria-expanded="true" :aria-activedescendant="filtered.length ? `${id}-${active}` : undefined" autocomplete="off" @input="active = 0" @keydown.down.prevent="move(1)" @keydown.up.prevent="move(-1)" @keydown.enter.prevent="filtered[active] && choose(filtered[active])" />
            <ul :id="`${id}-list`" role="listbox" :aria-label="label" class="mt-2 max-h-60 overflow-y-auto">
                <li v-for="(row, index) in filtered" :id="`${id}-${index}`" :key="row.code" role="option" :aria-selected="row.code === modelValue" class="cursor-pointer rounded px-3 py-2 text-primary" :class="index === active ? 'bg-buttonPrimary/15' : ''" @mousedown.prevent @click="choose(row)">
                    {{ name(row) }} <span class="text-xs text-secondary">({{ row.code }})</span>
                </li>
            </ul>
            <p v-if="!filtered.length" class="p-2 text-sm text-secondary">{{ text('Kein Land gefunden', 'No country found') }}</p>
            <button v-if="canManage" type="button" class="mt-2 text-sm text-primary underline" @click="editing = !editing">{{ text('Land ergänzen', 'Add country') }}</button>
            <div v-if="editing" class="mt-2 grid gap-2" @keydown.enter.prevent>
                <input v-model="extra.code" maxlength="2" class="w-full rounded border-border bg-inputBg text-primary" :aria-label="text('Ländercode (2 Buchstaben)', 'Country code (2 letters)')" :placeholder="text('Kürzel (2 Buchstaben)', 'Code (2 letters)')" />
                <input v-model="extra.de" maxlength="100" class="w-full rounded border-border bg-inputBg text-primary" placeholder="Name (Deutsch)" aria-label="Name (Deutsch)" />
                <input v-model="extra.en" maxlength="100" class="w-full rounded border-border bg-inputBg text-primary" placeholder="Name (English)" aria-label="Name (English)" />
                <p v-if="error" role="alert" class="text-sm text-error">{{ error }}</p>
                <button type="button" class="rounded bg-buttonPrimary px-3 py-2 text-buttonTextPrimary" :disabled="busy || !extra.de.trim() || !extra.en.trim() || !/^[a-z]{2}$/i.test(extra.code)" @click="saveExtra">{{ text('Speichern', 'Save') }}</button>
            </div>
        </div>
    </div>
</template>
