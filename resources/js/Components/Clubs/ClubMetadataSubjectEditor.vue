<script setup>
import AppLoadingState from '@/Components/UI/AppLoadingState.vue'
import valueTranslations from '@/i18n/clubMetadataValuesLocalization.json'
import { computed, ref } from 'vue'
import { useI18n } from 'vue-i18n'

const props = defineProps({
    clubId: { type: [Number, String], required: true },
    subjectType: { type: String, required: true },
    subjectId: { type: [Number, String], required: true },
    subjectLabel: { type: String, default: '' },
})
const { locale } = useI18n({ useScope: 'global' })
const copy = computed(() => valueTranslations[locale.value] || valueTranslations.de)
const vt = (key, values = {}) => {
    let text = copy.value[key] || valueTranslations.de[key] || key
    Object.entries(values).forEach(([name, value]) => { text = text.replace(`{${name}}`, value) })
    return text
}
const open = ref(false)
const loading = ref(false)
const saving = ref(false)
const loaded = ref(false)
const payload = ref({ fields: [], categories: [] })
const values = ref({})
const selectedCategories = ref([])
const error = ref('')
const success = ref('')
const url = computed(() => `/api/v1/clubs/${encodeURIComponent(props.clubId)}/metadata/subjects/${encodeURIComponent(props.subjectType)}/${encodeURIComponent(props.subjectId)}`)
const apiError = (cause) => Object.values(cause?.response?.data?.errors || {}).flat()[0]
    || cause?.response?.data?.message || vt('error')
const hydrate = (data) => {
    payload.value = data
    values.value = Object.fromEntries(data.fields.map((field) => [field.id, field.value]))
    selectedCategories.value = data.categories.filter((category) => category.selected).map((category) => category.id)
}
const load = async () => {
    loading.value = true
    error.value = ''
    try {
        hydrate((await window.axios.get(url.value, { headers: { Accept: 'application/json' } })).data.data)
        loaded.value = true
    } catch (cause) {
        error.value = apiError(cause)
    } finally {
        loading.value = false
    }
}
const toggle = async () => {
    open.value = !open.value
    success.value = ''
    if (open.value && !loaded.value) await load()
}
const save = async () => {
    saving.value = true
    error.value = ''
    success.value = ''
    try {
        const activeValues = Object.fromEntries(payload.value.fields.filter((field) => field.is_active).map((field) => [field.id, values.value[field.id] ?? null]))
        const activeCategoryIds = payload.value.categories.filter((category) => category.is_active && selectedCategories.value.includes(category.id)).map((category) => category.id)
        hydrate((await window.axios.put(url.value, { values: activeValues, category_ids: activeCategoryIds }, { headers: { Accept: 'application/json' } })).data.data)
        success.value = vt('saved')
    } catch (cause) {
        error.value = apiError(cause)
    } finally {
        saving.value = false
    }
}
</script>

<template>
    <div :class="open ? 'basis-full w-full' : ''">
        <button type="button" class="rounded-lg border border-border px-3 py-2 text-sm font-semibold text-primary" :aria-expanded="open" @click="toggle">{{ vt('open') }}</button>
        <div v-if="open" class="mt-3 rounded-lg border border-border bg-card p-4" role="region" :aria-label="vt('title', { name: subjectLabel })">
            <div class="flex items-start justify-between gap-3"><div><h3 class="font-semibold text-primary">{{ vt('title', { name: subjectLabel }) }}</h3><p class="mt-1 text-xs text-secondary">{{ vt('hint') }}</p></div><button type="button" class="text-xs font-semibold text-secondary" @click="open = false">{{ vt('close') }}</button></div>
            <AppLoadingState v-if="loading" class="mt-3" :label="vt('loading')" inline />
            <p v-if="error" class="mt-3 rounded-lg border border-danger/30 bg-danger/10 p-3 text-sm text-danger" role="alert">{{ error }} <button type="button" class="ms-2 underline" @click="load">{{ vt('retry') }}</button></p>
            <p v-if="success" class="mt-3 rounded-lg border border-success/30 bg-success/10 p-3 text-sm text-success" role="status">{{ success }}</p>
            <form v-if="loaded && !loading" class="mt-4 space-y-4" @submit.prevent="save">
                <p v-if="!payload.fields.length && !payload.categories.length" class="text-sm text-secondary">{{ vt('empty') }}</p>
                <div class="grid gap-3 md:grid-cols-2">
                    <label v-for="field in payload.fields" :key="field.id" class="rounded-lg border p-3 text-xs font-semibold" :class="field.is_sensitive ? 'border-warning/40 bg-warning/5 text-primary' : 'border-border text-secondary'">
                        <span>{{ field.label }}<span v-if="field.is_required" class="text-danger"> *</span></span>
                        <span v-if="field.is_sensitive" class="mt-1 block text-[11px] font-normal text-warning">{{ vt('sensitive') }}</span>
                        <span v-if="!field.is_active" class="mt-1 block text-[11px] font-normal text-secondary">{{ vt('inactive') }}</span>
                        <textarea v-if="field.field_type === 'textarea'" v-model="values[field.id]" :required="field.is_required" :disabled="!field.is_active" rows="3" maxlength="10000" class="mt-2 w-full rounded-lg border border-border bg-inputBg px-3 py-2 text-sm font-normal text-primary"></textarea>
                        <select v-else-if="field.field_type === 'select'" v-model="values[field.id]" :required="field.is_required" :disabled="!field.is_active" class="mt-2 w-full rounded-lg border border-border bg-inputBg px-3 py-2 text-sm font-normal text-primary"><option :value="null">{{ vt('unset') }}</option><option v-for="option in field.options" :key="option" :value="option">{{ option }}</option></select>
                        <select v-else-if="field.field_type === 'boolean'" v-model="values[field.id]" :required="field.is_required" :disabled="!field.is_active" class="mt-2 w-full rounded-lg border border-border bg-inputBg px-3 py-2 text-sm font-normal text-primary"><option :value="null">{{ vt('unset') }}</option><option :value="true">{{ vt('yes') }}</option><option :value="false">{{ vt('no') }}</option></select>
                        <input v-else v-model="values[field.id]" :required="field.is_required" :disabled="!field.is_active" :type="field.field_type === 'number' ? 'number' : field.field_type === 'date' ? 'date' : 'text'" :step="field.field_type === 'number' ? '0.0001' : undefined" :maxlength="field.field_type === 'text' ? 1000 : undefined" class="mt-2 w-full rounded-lg border border-border bg-inputBg px-3 py-2 text-sm font-normal text-primary">
                    </label>
                </div>
                <fieldset v-if="payload.categories.length" class="rounded-lg border border-border p-3"><legend class="px-1 text-sm font-semibold text-primary">{{ vt('categories') }}</legend><div class="flex flex-wrap gap-2"><label v-for="category in payload.categories" :key="category.id" class="flex items-center gap-2 rounded-full border border-border px-3 py-2 text-sm text-primary"><input v-model="selectedCategories" type="checkbox" :value="category.id" :disabled="!category.is_active" class="rounded border-border"><span class="h-2.5 w-2.5 rounded-full" :style="{ backgroundColor: category.color || '#64748B' }"></span>{{ category.name }}<span v-if="!category.is_active" class="text-xs text-secondary">({{ vt('inactive') }})</span></label></div></fieldset>
                <div class="flex justify-end"><button class="rounded-lg bg-buttonPrimary px-4 py-2 text-sm font-semibold text-buttonTextPrimary disabled:opacity-60" :disabled="saving">{{ vt('save') }}</button></div>
            </form>
        </div>
    </div>
</template>
