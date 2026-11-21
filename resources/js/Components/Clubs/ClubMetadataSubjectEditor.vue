<script setup>
import ClubMetadataSection from '@/Components/Clubs/ClubMetadataSection.vue'
import AppLoadingState from '@/Components/UI/AppLoadingState.vue'
import valueTranslations from '@/i18n/clubMetadataValuesLocalization.json'
import { computed, ref, onMounted, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import { formFailureText, safeFieldMessage } from '@/services/formFeedbackMessages'

const props = defineProps({
    clubId: { type: [Number, String], required: true },
    subjectType: { type: String, required: true },
    subjectId: { type: [Number, String], required: true },
    subjectLabel: { type: String, default: '' },
    direct: { type: Boolean, default: false },
})
const { locale } = useI18n({ useScope: 'global' })
const copy = computed(() => valueTranslations[locale.value] || valueTranslations.de)
const vt = (key, values = {}) => {
    let text = copy.value[key] || valueTranslations.de[key] || key
    Object.entries(values).forEach(([name, value]) => { text = text.replace(`{${name}}`, value) })
    return text
}
const open = ref(props.direct)
const managing = ref(false)
const canEdit = computed(() => payload.value.can_edit === true)
const loading = ref(false)
const saving = ref(false)
const loaded = ref(false)
const payload = ref({ fields: [], categories: [] })
const values = ref({})
const selectedCategories = ref([])
const error = ref('')
const success = ref('')
const url = computed(() => `/api/v1/clubs/${encodeURIComponent(props.clubId)}/metadata/subjects/${encodeURIComponent(props.subjectType)}/${encodeURIComponent(props.subjectId)}`)
const apiError = (cause) => cause?.response?.status === 422
    ? safeFieldMessage(Object.values(cause.response.data?.errors || {}).flat()[0], locale.value)
    : formFailureText(cause?.response?.status, locale.value)
const hydrate = (data) => {
    payload.value = data
    values.value = Object.fromEntries(data.fields.map((field) => [field.id, field.value]))
    selectedCategories.value = data.categories.filter((category) => category.selected).map((category) => category.id)
}
const load = async (preserveDraft = false) => {
    const draft = preserveDraft && loaded.value ? { values: { ...values.value }, categories: [...selectedCategories.value] } : null
    loading.value = true
    error.value = ''
    try {
        hydrate((await window.axios.get(url.value, { headers: { Accept: 'application/json' } })).data.data)
        if (draft) {
            for (const field of payload.value.fields) if (Object.hasOwn(draft.values, field.id)) values.value[field.id] = draft.values[field.id]
            selectedCategories.value = payload.value.categories.filter(item => draft.categories.includes(item.id)).map(item => item.id)
        }
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
    if (saving.value || !canEdit.value) return
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
const leaveManagement = async () => { managing.value = false; await load(true) }
onMounted(() => { if (props.direct) load() })
watch(() => props.direct, value => { if (value && !open.value) { open.value = true; if (!loaded.value) load() } })
</script>

<template>
    <div :class="open ? 'basis-full w-full min-w-0' : ''">
        <button v-if="!direct && !open" type="button" class="rounded-lg border border-border px-3 py-2 text-sm font-semibold text-primary" :aria-expanded="open" @click="toggle">{{ vt('open') }}</button>
        <section v-if="open" class="metadata-subject-editor rounded-xl border border-border bg-card" :aria-label="vt('title', { name: subjectLabel })">
            <header class="metadata-subject-header flex flex-wrap items-center justify-between gap-3 border-b border-border p-4">
                <h3 class="min-w-0 font-semibold text-primary">{{ vt('title', { name: subjectLabel }) }}</h3>
                <div class="flex flex-wrap items-center gap-2">
                    <button v-if="managing" type="button" class="rounded-lg border border-border px-3 py-2 text-sm font-semibold text-primary" @click="leaveManagement">{{ vt('backToValues') }}</button>
                    <button v-else-if="canEdit" type="button" class="rounded-lg border border-border px-3 py-2 text-sm font-semibold text-primary" :disabled="saving" @click="managing = true">{{ vt('manageFields') }}</button>
                    <button v-if="!direct" type="button" class="rounded-lg px-3 py-2 text-sm text-secondary" :disabled="saving" @click="open = false">{{ vt('close') }}</button>
                </div>
            </header>
            <div v-if="managing && canEdit" class="metadata-subject-scroll p-4"><ClubMetadataSection :club-id="clubId" /></div>
            <form v-show="!managing" class="metadata-subject-form" @submit.prevent="save">
                <div class="metadata-subject-scroll space-y-4 p-4">
                    <AppLoadingState v-if="loading" :label="vt('loading')" inline />
                    <p v-if="error" class="rounded-lg border border-error/30 p-3 text-sm text-error" role="alert">{{ error }} <button v-if="!loaded" type="button" class="ms-2 underline" @click="load()">{{ vt('retry') }}</button></p>
                    <p v-if="success" class="text-sm text-success" role="status">{{ success }}</p>
                    <template v-if="loaded && !loading">
                        <p v-if="!canEdit" class="text-sm text-secondary">{{ vt('readOnly') }}</p>
                        <p v-if="!payload.fields.length && !payload.categories.length" class="text-sm text-secondary">{{ vt('empty') }}</p>
                <div class="grid gap-3 md:grid-cols-2">
                    <label v-for="field in payload.fields" :key="field.id" class="rounded-lg border p-3 text-xs font-semibold" :class="field.is_sensitive ? 'border-warning/40 bg-warning/5 text-primary' : 'border-border text-secondary'">
                        <span>{{ field.label }}<span v-if="field.is_required" class="text-danger"> *</span></span>
                        <span v-if="field.is_sensitive" class="mt-1 block text-[11px] font-normal text-warning">{{ vt('sensitive') }}</span>
                        <span v-if="!field.is_active" class="mt-1 block text-[11px] font-normal text-secondary">{{ vt('inactive') }}</span>
                        <textarea v-if="field.field_type === 'textarea'" v-model="values[field.id]" :required="field.is_required" :disabled="!canEdit || saving || !field.is_active" rows="3" maxlength="10000" class="mt-2 w-full rounded-lg border border-border bg-inputBg px-3 py-2 text-sm font-normal text-primary"></textarea>
                        <select v-else-if="field.field_type === 'select'" v-model="values[field.id]" :required="field.is_required" :disabled="!canEdit || saving || !field.is_active" class="mt-2 w-full rounded-lg border border-border bg-inputBg px-3 py-2 text-sm font-normal text-primary"><option :value="null">{{ vt('unset') }}</option><option v-for="option in field.options" :key="option" :value="option">{{ option }}</option></select>
                        <select v-else-if="field.field_type === 'boolean'" v-model="values[field.id]" :required="field.is_required" :disabled="!canEdit || saving || !field.is_active" class="mt-2 w-full rounded-lg border border-border bg-inputBg px-3 py-2 text-sm font-normal text-primary"><option :value="null">{{ vt('unset') }}</option><option :value="true">{{ vt('yes') }}</option><option :value="false">{{ vt('no') }}</option></select>
                        <input v-else v-model="values[field.id]" :required="field.is_required" :disabled="!canEdit || saving || !field.is_active" :type="field.field_type === 'number' ? 'number' : field.field_type === 'date' ? 'date' : 'text'" :step="field.field_type === 'number' ? '0.0001' : undefined" :maxlength="field.field_type === 'text' ? 1000 : undefined" class="mt-2 w-full rounded-lg border border-border bg-inputBg px-3 py-2 text-sm font-normal text-primary">
                    </label>
                </div>
                <fieldset v-if="payload.categories.length" class="rounded-lg border border-border p-3"><legend class="px-1 text-sm font-semibold text-primary">{{ vt('categories') }}</legend><div class="flex flex-wrap gap-2"><label v-for="category in payload.categories" :key="category.id" class="flex items-center gap-2 rounded-full border border-border px-3 py-2 text-sm text-primary"><input v-model="selectedCategories" type="checkbox" :value="category.id" :disabled="!canEdit || saving || !category.is_active" class="rounded border-border"><span class="h-2.5 w-2.5 rounded-full" :style="{ backgroundColor: category.color || '#64748B' }"></span>{{ category.name }}<span v-if="!category.is_active" class="text-xs text-secondary">({{ vt('inactive') }})</span></label></div></fieldset>

                    </template>
                </div>
                <footer v-if="loaded && canEdit && !loading" class="metadata-subject-footer flex justify-end border-t border-border p-4">
                    <button class="rounded-lg bg-buttonPrimary px-4 py-2 text-sm font-semibold text-buttonTextPrimary disabled:opacity-60" :disabled="saving">{{ saving ? vt('saving') : vt('save') }}</button>
                </footer>
            </form>
        </section>
    </div>
</template>
<style scoped>
.metadata-subject-editor { overflow: hidden; }
.metadata-subject-header, .metadata-subject-footer { flex-shrink: 0; background: var(--card); }
.metadata-subject-header h3 { overflow-wrap: anywhere; }
.metadata-subject-scroll { max-height: min(60dvh, 42rem); overflow-y: auto; overscroll-behavior: contain; scroll-padding-block: 1rem; }
.metadata-subject-form { min-height: 0; }
</style>
