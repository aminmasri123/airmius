<script setup>
import AppLoadingState from '@/Components/UI/AppLoadingState.vue'
import metadataTranslations from '@/i18n/clubMetadataLocalization.json'
import { confirmDialog } from '@/services/dialogService'
import { computed, onMounted, ref } from 'vue'
import { useI18n } from 'vue-i18n'

const props = defineProps({ clubId: { type: [Number, String], required: true } })
const { locale } = useI18n({ useScope: 'global' })
const messages = computed(() => metadataTranslations[locale.value] || metadataTranslations.de)
const mt = (key) => messages.value[key] || metadataTranslations.de[key] || key
const baseUrl = `/api/v1/clubs/${encodeURIComponent(props.clubId)}/metadata`
const state = ref({ custom_fields: [], categories: [], number_ranges: [], can_manage: false, can_edit: false, can_delete: false })
const canEdit = computed(() => state.value.can_edit === true
    || (!Object.hasOwn(state.value, 'can_edit') && state.value.can_manage === true))
const canDelete = computed(() => state.value.can_delete === true
    || (!Object.hasOwn(state.value, 'can_delete') && state.value.can_manage === true))
const loading = ref(true)
const saving = ref(false)
const error = ref('')
const activePanel = ref('fields')
const entityTypes = ['member', 'external_member', 'team', 'event', 'inventory_item']
const categoryScopes = ['member', 'team', 'event', 'inventory_item']
const rangeScopes = ['member', 'invoice', 'receipt', 'donation', 'inventory_item', 'shop_invoice', 'shop_credit_note', 'shop_sku']
const fieldTypes = ['text', 'textarea', 'number', 'date', 'boolean', 'select']
const emptyField = () => ({ id: null, entity_type: 'member', key: '', label: '', field_type: 'text', options_text: '', is_required: false, is_sensitive: false, is_active: true, sort_order: 0 })
const emptyCategory = () => ({ id: null, scope: 'member', name: '', color: '#2563EB', is_active: true, sort_order: 0 })
const emptyRange = () => ({ id: null, scope: 'member', name: '', prefix: '', suffix: '', padding: 4, start_number: 1, reset_policy: 'never', is_active: true })
const fieldForm = ref(emptyField())
const categoryForm = ref(emptyCategory())
const rangeForm = ref(emptyRange())
const apiError = (cause) => Object.values(cause?.response?.data?.errors || {}).flat()[0]
    || cause?.response?.data?.message || mt('saveError')

const loadMetadata = async () => {
    loading.value = true
    error.value = ''
    try {
        state.value = (await window.axios.get(baseUrl, { headers: { Accept: 'application/json' } })).data.data
    } catch (cause) {
        error.value = apiError(cause)
    } finally {
        loading.value = false
    }
}
const request = async (method, path, data = undefined) => {
    saving.value = true
    error.value = ''
    try {
        await window.axios({ method, url: `${baseUrl}/${path}`, data, headers: { Accept: 'application/json' } })
        await loadMetadata()
        return true
    } catch (cause) {
        error.value = apiError(cause)
        return false
    } finally {
        saving.value = false
    }
}
const saveField = async () => {
    const current = fieldForm.value
    const payload = { ...current, options: current.field_type === 'select' ? current.options_text.split(/\r?\n/).map((value) => value.trim()).filter(Boolean) : null }
    delete payload.id
    delete payload.options_text
    if (await request(current.id ? 'put' : 'post', `custom-fields${current.id ? `/${current.id}` : ''}`, payload)) fieldForm.value = emptyField()
}
const saveCategory = async () => {
    const current = categoryForm.value
    const payload = { ...current, color: current.color || null }
    delete payload.id
    if (await request(current.id ? 'put' : 'post', `categories${current.id ? `/${current.id}` : ''}`, payload)) categoryForm.value = emptyCategory()
}
const saveRange = async () => {
    const current = rangeForm.value
    const payload = { ...current }
    delete payload.id
    if (await request(current.id ? 'put' : 'post', `number-ranges${current.id ? `/${current.id}` : ''}`, payload)) rangeForm.value = emptyRange()
}
const editField = (item) => { fieldForm.value = { ...item, options_text: (item.options || []).join('\n') } }
const editCategory = (item) => { categoryForm.value = { ...item, color: item.color || '#2563EB' } }
const editRange = (item) => { rangeForm.value = { ...item } }
const remove = async (path) => {
    if (!await confirmDialog({ title: mt('deleteTitle'), message: mt('deleteMessage'), confirmLabel: mt('delete') })) return
    await request('delete', path)
}
const toggleDefault = (range) => request(range.is_default ? 'delete' : 'put', `number-ranges/${range.id}/default`)

onMounted(loadMetadata)
</script>

<template>
    <section class="rounded-lg border border-border bg-card p-5" aria-labelledby="club-metadata-title">
        <div class="flex flex-wrap items-start justify-between gap-3">
            <div><h2 id="club-metadata-title" class="text-lg font-semibold text-primary">{{ mt('title') }}</h2><p class="mt-1 text-sm text-secondary">{{ mt('hint') }}</p></div>
            <button v-if="error" type="button" class="rounded-lg border border-border px-3 py-2 text-xs font-semibold text-primary" @click="loadMetadata">{{ mt('retry') }}</button>
        </div>
        <AppLoadingState v-if="loading" class="mt-4" :label="mt('loading')" inline />
        <p v-if="error" class="mt-3 rounded-lg border border-danger/30 bg-danger/10 p-3 text-sm text-danger" role="alert">{{ error }}</p>

        <template v-if="!loading">
            <div class="mt-5 flex flex-wrap gap-2" role="tablist" :aria-label="mt('title')">
                <button v-for="panel in ['fields', 'categories', 'ranges']" :id="`metadata-tab-${panel}`" :key="panel" type="button" role="tab" :aria-selected="activePanel === panel" :aria-controls="`metadata-panel-${panel}`" class="rounded-full px-4 py-2 text-sm font-semibold" :class="activePanel === panel ? 'bg-buttonPrimary text-buttonTextPrimary' : 'border border-border text-primary'" @click="activePanel = panel">{{ mt(panel) }}</button>
            </div>

            <div v-show="activePanel === 'fields'" id="metadata-panel-fields" class="mt-5" role="tabpanel" aria-labelledby="metadata-tab-fields">
                <div class="grid gap-3 lg:grid-cols-2">
                    <article v-for="item in state.custom_fields" :key="item.id" class="rounded-lg border border-border bg-bg p-4 text-sm">
                        <div class="flex items-start justify-between gap-3"><div><p class="font-semibold text-primary">{{ item.label }}</p><p class="mt-1 font-mono text-xs text-secondary">{{ item.key }}</p></div><span class="rounded-full px-2 py-1 text-xs" :class="item.is_active ? 'bg-success/10 text-success' : 'bg-inputBg text-secondary'">{{ mt(item.is_active ? 'statusActive' : 'statusInactive') }}</span></div>
                        <p class="mt-2 text-secondary">{{ mt(item.entity_type) }} · {{ mt(item.field_type) }}<span v-if="item.is_required"> · {{ mt('required') }}</span><span v-if="item.is_sensitive"> · {{ mt('sensitive') }}</span></p>
                        <p v-if="item.options?.length" class="mt-2 text-xs text-secondary">{{ item.options.join(' · ') }}</p>
                        <div v-if="canEdit || canDelete" class="mt-3 flex gap-3"><button v-if="canEdit" type="button" class="text-xs font-semibold text-link" @click="editField(item)">{{ mt('edit') }}</button><button v-if="canDelete" type="button" class="text-xs font-semibold text-danger" @click="remove(`custom-fields/${item.id}`)">{{ mt('delete') }}</button></div>
                    </article>
                </div>
                <p v-if="!state.custom_fields.length" class="text-sm text-secondary">{{ mt('empty') }}</p>
                <form v-if="canEdit" class="mt-5 grid gap-3 rounded-lg border border-border p-4 md:grid-cols-2" @submit.prevent="saveField">
                    <label class="text-xs font-semibold text-secondary">{{ mt('target') }}<select v-model="fieldForm.entity_type" class="mt-1 w-full rounded-lg border border-border bg-inputBg px-3 py-2 text-sm text-primary"><option v-for="item in entityTypes" :key="item" :value="item">{{ mt(item) }}</option></select></label>
                    <label class="text-xs font-semibold text-secondary">{{ mt('type') }}<select v-model="fieldForm.field_type" class="mt-1 w-full rounded-lg border border-border bg-inputBg px-3 py-2 text-sm text-primary"><option v-for="item in fieldTypes" :key="item" :value="item">{{ mt(item) }}</option></select></label>
                    <label class="text-xs font-semibold text-secondary">{{ mt('name') }}<input v-model="fieldForm.label" required maxlength="160" class="mt-1 w-full rounded-lg border border-border bg-inputBg px-3 py-2 text-sm text-primary"></label>
                    <label class="text-xs font-semibold text-secondary">{{ mt('key') }}<input v-model="fieldForm.key" required maxlength="80" pattern="[a-z][a-z0-9_]*" class="mt-1 w-full rounded-lg border border-border bg-inputBg px-3 py-2 font-mono text-sm text-primary"></label>
                    <label v-if="fieldForm.field_type === 'select'" class="text-xs font-semibold text-secondary md:col-span-2">{{ mt('options') }}<textarea v-model="fieldForm.options_text" required rows="3" class="mt-1 w-full rounded-lg border border-border bg-inputBg px-3 py-2 text-sm text-primary"></textarea></label>
                    <label class="text-xs font-semibold text-secondary">{{ mt('sortOrder') }}<input v-model.number="fieldForm.sort_order" required type="number" min="0" max="10000" class="mt-1 w-full rounded-lg border border-border bg-inputBg px-3 py-2 text-sm text-primary"></label>
                    <div class="flex flex-wrap items-center gap-4 text-sm text-secondary"><label><input v-model="fieldForm.is_required" type="checkbox" class="me-2 rounded border-border">{{ mt('required') }}</label><label><input v-model="fieldForm.is_sensitive" type="checkbox" class="me-2 rounded border-border">{{ mt('sensitive') }}</label><label><input v-model="fieldForm.is_active" type="checkbox" class="me-2 rounded border-border">{{ mt('active') }}</label></div>
                    <div class="flex gap-2 md:col-span-2"><button class="rounded-lg bg-buttonPrimary px-4 py-2 text-xs font-semibold text-buttonTextPrimary" :disabled="saving">{{ fieldForm.id ? mt('save') : mt('add') }}</button><button v-if="fieldForm.id" type="button" class="px-3 py-2 text-xs font-semibold text-secondary" @click="fieldForm = emptyField()">{{ mt('cancel') }}</button></div>
                </form>
            </div>

            <div v-show="activePanel === 'categories'" id="metadata-panel-categories" class="mt-5" role="tabpanel" aria-labelledby="metadata-tab-categories">
                <div class="grid gap-3 lg:grid-cols-2"><article v-for="item in state.categories" :key="item.id" class="rounded-lg border border-border bg-bg p-4 text-sm"><div class="flex items-center gap-2"><span class="h-3 w-3 rounded-full" :style="{ backgroundColor: item.color || '#64748B' }"></span><p class="font-semibold text-primary">{{ item.name }}</p></div><p class="mt-2 text-secondary">{{ mt(item.scope) }} · {{ mt(item.is_active ? 'statusActive' : 'statusInactive') }}</p><div v-if="canEdit || canDelete" class="mt-3 flex gap-3"><button v-if="canEdit" type="button" class="text-xs font-semibold text-link" @click="editCategory(item)">{{ mt('edit') }}</button><button v-if="canDelete" type="button" class="text-xs font-semibold text-danger" @click="remove(`categories/${item.id}`)">{{ mt('delete') }}</button></div></article></div>
                <p v-if="!state.categories.length" class="text-sm text-secondary">{{ mt('empty') }}</p>
                <template v-if="canEdit">
                <form class="mt-5 grid gap-3 rounded-lg border border-border p-4 md:grid-cols-2" @submit.prevent="saveCategory"><label class="text-xs font-semibold text-secondary">{{ mt('target') }}<select v-model="categoryForm.scope" class="mt-1 w-full rounded-lg border border-border bg-inputBg px-3 py-2 text-sm text-primary"><option v-for="item in categoryScopes" :key="item" :value="item">{{ mt(item) }}</option></select></label><label class="text-xs font-semibold text-secondary">{{ mt('name') }}<input v-model="categoryForm.name" required maxlength="160" class="mt-1 w-full rounded-lg border border-border bg-inputBg px-3 py-2 text-sm text-primary"></label><label class="text-xs font-semibold text-secondary">{{ mt('color') }}<input v-model="categoryForm.color" type="color" class="mt-1 h-10 w-full rounded-lg border border-border bg-inputBg p-1"></label><label class="text-xs font-semibold text-secondary">{{ mt('sortOrder') }}<input v-model.number="categoryForm.sort_order" required type="number" min="0" max="10000" class="mt-1 w-full rounded-lg border border-border bg-inputBg px-3 py-2 text-sm text-primary"></label><label class="text-sm text-secondary"><input v-model="categoryForm.is_active" type="checkbox" class="me-2 rounded border-border">{{ mt('active') }}</label><div class="flex gap-2"><button class="rounded-lg bg-buttonPrimary px-4 py-2 text-xs font-semibold text-buttonTextPrimary" :disabled="saving">{{ categoryForm.id ? mt('save') : mt('add') }}</button><button v-if="categoryForm.id" type="button" class="px-3 py-2 text-xs font-semibold text-secondary" @click="categoryForm = emptyCategory()">{{ mt('cancel') }}</button></div></form>
                </template>
            </div>

            <div v-show="activePanel === 'ranges'" id="metadata-panel-ranges" class="mt-5" role="tabpanel" aria-labelledby="metadata-tab-ranges">
                <div class="grid gap-3 lg:grid-cols-2"><article v-for="item in state.number_ranges" :key="item.id" class="rounded-lg border border-border bg-bg p-4 text-sm"><div class="flex items-start justify-between gap-3"><div><p class="font-semibold text-primary">{{ item.name }}</p><p class="mt-1 text-secondary">{{ mt(item.scope) }}</p></div><span v-if="item.is_default" class="rounded-full bg-success/10 px-2 py-1 text-xs font-semibold text-success">{{ mt('default') }}</span></div><dl class="mt-3 grid grid-cols-2 gap-2 text-xs"><div><dt class="text-secondary">{{ mt('preview') }}</dt><dd class="mt-1 font-mono text-primary">{{ item.preview }}</dd></div><div><dt class="text-secondary">{{ mt('nextNumber') }}</dt><dd class="mt-1 text-primary">{{ item.next_number }}</dd></div><div><dt class="text-secondary">{{ mt('allocations') }}</dt><dd class="mt-1 text-primary">{{ item.allocations_count }}</dd></div><div><dt class="text-secondary">{{ mt('resetPolicy') }}</dt><dd class="mt-1 text-primary">{{ mt(item.reset_policy) }}</dd></div></dl><div v-if="canEdit || canDelete" class="mt-3 flex flex-wrap gap-3"><button v-if="canEdit" type="button" class="text-xs font-semibold text-link" @click="editRange(item)">{{ mt('edit') }}</button><button v-if="canEdit" type="button" class="text-xs font-semibold text-link" :disabled="saving || (!item.is_active && !item.is_default)" @click="toggleDefault(item)">{{ mt(item.is_default ? 'clearDefault' : 'setDefault') }}</button><button v-if="canDelete" type="button" class="text-xs font-semibold text-danger" @click="remove(`number-ranges/${item.id}`)">{{ mt('delete') }}</button></div></article></div>
                <p v-if="!state.number_ranges.length" class="text-sm text-secondary">{{ mt('empty') }}</p>
                <template v-if="canEdit">
                <form class="mt-5 grid gap-3 rounded-lg border border-border p-4 md:grid-cols-2 lg:grid-cols-4" @submit.prevent="saveRange"><label class="text-xs font-semibold text-secondary">{{ mt('target') }}<select v-model="rangeForm.scope" class="mt-1 w-full rounded-lg border border-border bg-inputBg px-3 py-2 text-sm text-primary"><option v-for="item in rangeScopes" :key="item" :value="item">{{ mt(item) }}</option></select></label><label class="text-xs font-semibold text-secondary">{{ mt('name') }}<input v-model="rangeForm.name" required maxlength="160" class="mt-1 w-full rounded-lg border border-border bg-inputBg px-3 py-2 text-sm text-primary"></label><label class="text-xs font-semibold text-secondary">{{ mt('prefix') }}<input v-model="rangeForm.prefix" maxlength="40" class="mt-1 w-full rounded-lg border border-border bg-inputBg px-3 py-2 font-mono text-sm text-primary"></label><label class="text-xs font-semibold text-secondary">{{ mt('suffix') }}<input v-model="rangeForm.suffix" maxlength="40" class="mt-1 w-full rounded-lg border border-border bg-inputBg px-3 py-2 font-mono text-sm text-primary"></label><label class="text-xs font-semibold text-secondary">{{ mt('padding') }}<input v-model.number="rangeForm.padding" required type="number" min="1" max="12" class="mt-1 w-full rounded-lg border border-border bg-inputBg px-3 py-2 text-sm text-primary"></label><label class="text-xs font-semibold text-secondary">{{ mt('startNumber') }}<input v-model.number="rangeForm.start_number" required type="number" min="1" class="mt-1 w-full rounded-lg border border-border bg-inputBg px-3 py-2 text-sm text-primary"></label><label class="text-xs font-semibold text-secondary">{{ mt('resetPolicy') }}<select v-model="rangeForm.reset_policy" class="mt-1 w-full rounded-lg border border-border bg-inputBg px-3 py-2 text-sm text-primary"><option value="never">{{ mt('never') }}</option><option value="yearly">{{ mt('yearly') }}</option></select></label><label class="flex items-center text-sm text-secondary"><input v-model="rangeForm.is_active" type="checkbox" class="me-2 rounded border-border">{{ mt('active') }}</label><div class="flex gap-2 lg:col-span-4"><button class="rounded-lg bg-buttonPrimary px-4 py-2 text-xs font-semibold text-buttonTextPrimary" :disabled="saving">{{ rangeForm.id ? mt('save') : mt('add') }}</button><button v-if="rangeForm.id" type="button" class="px-3 py-2 text-xs font-semibold text-secondary" @click="rangeForm = emptyRange()">{{ mt('cancel') }}</button></div></form>
                </template>
            </div>
        </template>
    </section>
</template>
