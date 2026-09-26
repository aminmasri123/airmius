<script setup>
import AppLoadingState from '@/Components/UI/AppLoadingState.vue'
import translations from '@/i18n/clubPolicyDocumentsLocalization.json'
import { confirmDialog } from '@/services/dialogService'
import { computed, onMounted, ref } from 'vue'
import { useI18n } from 'vue-i18n'

const props = defineProps({ clubId: { type: [Number, String], required: true } })
const { locale } = useI18n({ useScope: 'global' })
const messages = computed(() => translations[locale.value] || translations.de)
const dt = (key) => messages.value[key] || translations.de[key] || key
const baseUrl = `/api/v1/clubs/${encodeURIComponent(props.clubId)}/policy-documents`
const types = ['statutes', 'regulation', 'contribution_model']
const state = ref({ documents: [], types, can_manage: false })
const canEdit = computed(() => state.value.can_edit === true
    || (!Object.hasOwn(state.value, 'can_edit') && state.value.can_manage === true))
const canDelete = computed(() => state.value.can_delete === true
    || (!Object.hasOwn(state.value, 'can_delete') && state.value.can_manage === true))
const canDownload = computed(() => state.value.can_download === true
    || (!Object.hasOwn(state.value, 'can_download') && state.value.can_manage === true))
const files = ref([])
const loading = ref(true)
const saving = ref(false)
const uploading = ref(false)
const error = ref('')
const emptyForm = () => ({
    id: null,
    type: 'statutes',
    title: '',
    version_label: '',
    valid_from: '',
    valid_until: '',
    is_public: false,
    notes: '',
    file_id: '',
})
const form = ref(emptyForm())
const documentsFor = (type) => state.value.documents.filter((document) => document.type === type)
const apiError = (cause) => Object.values(cause?.response?.data?.errors || {}).flat()[0]
    || cause?.response?.data?.message || dt('saveError')

const loadFiles = async () => {
    const response = await window.axios.get('/api/v1/uploads', {
        params: { scope: 'club', club_id: props.clubId, per_page: 100 },
        headers: { Accept: 'application/json' },
    })
    files.value = response.data.data || []
}
const loadDocuments = async () => {
    loading.value = true
    error.value = ''
    try {
        state.value = (await window.axios.get(baseUrl, { headers: { Accept: 'application/json' } })).data.data
        if (canEdit.value) await loadFiles()
    } catch (cause) {
        error.value = apiError(cause)
    } finally {
        loading.value = false
    }
}
const editDocument = (document) => {
    form.value = {
        id: document.id,
        type: document.type,
        title: document.title,
        version_label: document.version_label,
        valid_from: document.valid_from,
        valid_until: document.valid_until || '',
        is_public: document.is_public,
        notes: document.notes || '',
        file_id: document.file.id,
    }
}
const saveDocument = async () => {
    saving.value = true
    error.value = ''
    try {
        const url = `${baseUrl}${form.value.id ? `/${form.value.id}` : ''}`
        await window.axios({
            method: form.value.id ? 'put' : 'post',
            url,
            data: { ...form.value, valid_until: form.value.valid_until || null },
            headers: { Accept: 'application/json' },
        })
        form.value = emptyForm()
        await loadDocuments()
    } catch (cause) {
        error.value = apiError(cause)
    } finally {
        saving.value = false
    }
}
const uploadFile = async (event) => {
    const selected = event.target.files?.[0]
    if (!selected) return
    uploading.value = true
    error.value = ''
    try {
        const data = new FormData()
        data.append('scope', 'club')
        data.append('club_id', String(props.clubId))
        data.append('file', selected)
        const response = await window.axios.post('/api/v1/uploads', data, {
            headers: { Accept: 'application/json', 'Content-Type': 'multipart/form-data' },
        })
        await loadFiles()
        form.value.file_id = response.data.data.id
    } catch (cause) {
        error.value = apiError(cause)
    } finally {
        uploading.value = false
        event.target.value = ''
    }
}
const deleteDocument = async (document) => {
    if (!await confirmDialog({ title: dt('deleteTitle'), message: dt('deleteMessage'), confirmLabel: dt('delete') })) return
    saving.value = true
    error.value = ''
    try {
        await window.axios.delete(`${baseUrl}/${document.id}`, { headers: { Accept: 'application/json' } })
        if (form.value.id === document.id) form.value = emptyForm()
        await loadDocuments()
    } catch (cause) {
        error.value = apiError(cause)
    } finally {
        saving.value = false
    }
}

onMounted(loadDocuments)
</script>

<template>
    <section class="rounded-lg border border-border bg-card p-5" aria-labelledby="club-policy-documents-title">
        <div class="flex flex-wrap items-start justify-between gap-3">
            <div><h2 id="club-policy-documents-title" class="text-lg font-semibold text-primary">{{ dt('title') }}</h2><p class="mt-1 text-sm text-secondary">{{ dt('hint') }}</p></div>
            <button v-if="error" type="button" class="rounded-lg border border-border px-3 py-2 text-xs font-semibold text-primary" @click="loadDocuments">{{ dt('retry') }}</button>
        </div>
        <AppLoadingState v-if="loading" class="mt-4" :label="dt('loading')" inline />
        <p v-if="error" class="mt-3 rounded-lg border border-danger/30 bg-danger/10 p-3 text-sm text-danger" role="alert">{{ error }}</p>

        <div v-if="!loading" class="mt-5 grid gap-4 lg:grid-cols-3">
            <div v-for="type in types" :key="type">
                <h3 class="font-semibold text-primary">{{ dt(type) }}</h3>
                <div class="mt-3 space-y-2">
                    <article v-for="document in documentsFor(type)" :key="document.id" class="rounded-lg border border-border bg-bg p-3 text-sm">
                        <div class="flex items-start justify-between gap-2"><div><p class="font-semibold text-primary">{{ document.title }}</p><p class="text-xs text-secondary">{{ dt('version') }} {{ document.version_label }}</p></div><span class="text-xs text-secondary">{{ dt(document.status) }}</span></div>
                        <p class="mt-2 text-secondary">{{ document.valid_from }} – {{ document.valid_until || dt('unlimited') }}</p>
                        <p class="mt-1 text-xs text-secondary">{{ document.is_public ? dt('public') : dt('internal') }}</p>
                        <p v-if="document.notes" class="mt-2 whitespace-pre-line text-secondary">{{ document.notes }}</p>
                        <div class="mt-3 flex flex-wrap gap-3"><a v-if="document.is_public || canDownload" :href="document.file.download_url" class="text-xs font-semibold text-link">{{ dt('download') }}</a><button v-if="canEdit" type="button" class="text-xs font-semibold text-link" @click="editDocument(document)">{{ dt('edit') }}</button><button v-if="canDelete" type="button" class="text-xs font-semibold text-danger" @click="deleteDocument(document)">{{ dt('delete') }}</button></div>
                    </article>
                    <p v-if="!documentsFor(type).length" class="text-sm text-secondary">{{ dt('empty') }}</p>
                </div>
            </div>
        </div>

        <form v-if="canEdit" class="mt-5 grid gap-3 border-t border-border pt-5 md:grid-cols-2 lg:grid-cols-3" @submit.prevent="saveDocument">
            <label class="text-sm text-primary"><span class="mb-1 block font-semibold">{{ dt('file') }}</span><select v-model="form.file_id" required class="w-full rounded-lg border border-border bg-inputBg px-3 py-2 text-sm text-primary"><option value="" disabled>{{ dt('selectFile') }}</option><option v-for="file in files" :key="file.id" :value="file.id">{{ file.display_name }}</option></select></label>
            <label class="text-sm text-primary"><span class="mb-1 block font-semibold">{{ dt('uploadFile') }}</span><input type="file" class="block w-full rounded-lg border border-border bg-inputBg px-3 py-2 text-sm text-primary" :aria-label="dt('uploadFile')" :disabled="uploading" @change="uploadFile"><span class="mt-1 block text-xs text-secondary">{{ uploading ? dt('uploading') : dt('fileHint') }}</span></label>
            <label class="text-sm text-primary"><span class="mb-1 block font-semibold">{{ dt('documentTitle') }}</span><input v-model="form.title" required maxlength="160" class="w-full rounded-lg border border-border bg-inputBg px-3 py-2 text-sm text-primary"></label>
            <label class="text-sm text-primary"><span class="mb-1 block font-semibold">{{ dt('version') }}</span><input v-model="form.version_label" required maxlength="80" class="w-full rounded-lg border border-border bg-inputBg px-3 py-2 text-sm text-primary"></label>
            <label class="text-sm text-primary"><span class="mb-1 block font-semibold">{{ dt('validFrom') }}</span><input v-model="form.valid_from" required type="date" class="w-full rounded-lg border border-border bg-inputBg px-3 py-2 text-sm text-primary"></label>
            <label class="text-sm text-primary"><span class="mb-1 block font-semibold">{{ dt('validUntil') }}</span><input v-model="form.valid_until" type="date" class="w-full rounded-lg border border-border bg-inputBg px-3 py-2 text-sm text-primary"></label>
            <label class="text-sm text-primary"><span class="mb-1 block font-semibold">{{ dt('type') }}</span><select v-model="form.type" required class="w-full rounded-lg border border-border bg-inputBg px-3 py-2 text-sm text-primary"><option v-for="type in types" :key="type" :value="type">{{ dt(type) }}</option></select></label>
            <label class="flex items-center gap-2 rounded-lg border border-border px-3 py-2 text-sm text-primary"><input v-model="form.is_public" type="checkbox" class="rounded border-border bg-inputBg">{{ dt('public') }}</label>
            <label class="text-sm text-primary md:col-span-2 lg:col-span-3"><span class="mb-1 block font-semibold">{{ dt('notes') }}</span><textarea v-model="form.notes" maxlength="5000" rows="3" class="w-full rounded-lg border border-border bg-inputBg px-3 py-2 text-sm text-primary"></textarea></label>
            <div class="flex gap-2"><button class="rounded-lg bg-buttonPrimary px-3 py-2 text-xs font-semibold text-buttonTextPrimary" :disabled="saving || uploading">{{ form.id ? dt('save') : dt('add') }}</button><button v-if="form.id" type="button" class="px-3 py-2 text-xs font-semibold text-secondary" @click="form = emptyForm()">{{ dt('cancel') }}</button></div>
        </form>
    </section>
</template>
