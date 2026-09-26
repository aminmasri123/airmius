<script setup>
import AppLoadingState from '@/Components/UI/AppLoadingState.vue'
import { confirmDialog } from '@/services/dialogService'
import governanceTranslations from '@/i18n/clubGovernanceLocalization.json'
import { computed, onMounted, ref } from 'vue'
import { useI18n } from 'vue-i18n'

const props = defineProps({ clubId: { type: [Number, String], required: true } })
const { locale } = useI18n({ useScope: 'global' })
const messages = computed(() => governanceTranslations[locale.value] || governanceTranslations.de)
const gt = (key) => messages.value[key] || governanceTranslations.de[key] || key
const baseUrl = `/api/v1/clubs/${encodeURIComponent(props.clubId)}/governance`
const governance = ref({ bodies: [], member_options: [], can_manage: false })
const canEdit = computed(() => governance.value.can_edit === true
    || (!Object.hasOwn(governance.value, 'can_edit') && governance.value.can_manage === true))
const canDelete = computed(() => governance.value.can_delete === true
    || (!Object.hasOwn(governance.value, 'can_delete') && governance.value.can_manage === true))
const loading = ref(true)
const saving = ref(false)
const error = ref('')
const bodyForm = ref(emptyBody())
const assignmentForm = ref(emptyAssignment())

function emptyBody() {
    return { id: null, type: 'board', name: '', description: '', starts_on: '', ends_on: '', is_public: false }
}
function emptyAssignment(bodyId = null) {
    return { id: null, body_id: bodyId, person_key: '', position_title: '', responsibilities: '', starts_on: '', ends_on: '', is_public: false }
}
const apiError = (cause) => Object.values(cause?.response?.data?.errors || {}).flat()[0]
    || cause?.response?.data?.message || gt('saveError')
const loadGovernance = async () => {
    loading.value = true
    error.value = ''
    try {
        governance.value = (await window.axios.get(baseUrl, { headers: { Accept: 'application/json' } })).data.data
    } catch (cause) {
        error.value = apiError(cause)
    } finally {
        loading.value = false
    }
}
const bodyPeriod = (body) => [body.starts_on, body.ends_on].filter(Boolean).join(' – ')
const editBody = (body) => { bodyForm.value = { ...body, starts_on: body.starts_on || '', ends_on: body.ends_on || '' } }
const saveBody = async () => {
    saving.value = true
    error.value = ''
    try {
        const form = bodyForm.value
        const url = `${baseUrl}/bodies${form.id ? `/${form.id}` : ''}`
        await window.axios({ method: form.id ? 'put' : 'post', url, data: form, headers: { Accept: 'application/json' } })
        bodyForm.value = emptyBody()
        await loadGovernance()
    } catch (cause) {
        error.value = apiError(cause)
    } finally {
        saving.value = false
    }
}
const deleteBody = async (body) => {
    if (!await confirmDialog({ title: gt('deleteBodyTitle'), message: gt('deleteBodyMessage'), confirmLabel: gt('delete') })) return
    saving.value = true
    error.value = ''
    try {
        await window.axios.delete(`${baseUrl}/bodies/${body.id}`, { headers: { Accept: 'application/json' } })
        await loadGovernance()
    } catch (cause) {
        error.value = apiError(cause)
    } finally {
        saving.value = false
    }
}
const beginAssignment = (body) => { assignmentForm.value = emptyAssignment(body.id) }
const editAssignment = (body, assignment) => {
    const personKey = assignment.user_id ? `user:${assignment.user_id}` : `external:${assignment.club_external_member_id}`
    assignmentForm.value = { ...assignment, body_id: body.id, person_key: personKey, starts_on: assignment.starts_on || '', ends_on: assignment.ends_on || '' }
}
const saveAssignment = async () => {
    const form = assignmentForm.value
    const [kind, rawId] = form.person_key.split(':')
    const data = { ...form, user_id: kind === 'user' ? Number(rawId) : null, club_external_member_id: kind === 'external' ? Number(rawId) : null }
    delete data.body_id
    delete data.person_key
    delete data.person
    saving.value = true
    error.value = ''
    try {
        const url = `${baseUrl}/bodies/${form.body_id}/assignments${form.id ? `/${form.id}` : ''}`
        await window.axios({ method: form.id ? 'put' : 'post', url, data, headers: { Accept: 'application/json' } })
        assignmentForm.value = emptyAssignment()
        await loadGovernance()
    } catch (cause) {
        error.value = apiError(cause)
    } finally {
        saving.value = false
    }
}
const deleteAssignment = async (body, assignment) => {
    if (!await confirmDialog({ title: gt('deleteAssignmentTitle'), message: gt('deleteAssignmentMessage'), confirmLabel: gt('delete') })) return
    saving.value = true
    error.value = ''
    try {
        await window.axios.delete(`${baseUrl}/bodies/${body.id}/assignments/${assignment.id}`, { headers: { Accept: 'application/json' } })
        await loadGovernance()
    } catch (cause) {
        error.value = apiError(cause)
    } finally {
        saving.value = false
    }
}

onMounted(loadGovernance)
</script>

<template>
    <section class="rounded-lg border border-border bg-card p-5">
        <div class="flex flex-wrap items-start justify-between gap-3">
            <div><h2 class="text-lg font-semibold text-primary">{{ gt('title') }}</h2><p class="mt-1 text-sm text-secondary">{{ gt('hint') }}</p></div>
            <button v-if="error" type="button" class="rounded-lg border border-border px-3 py-2 text-xs font-semibold text-primary" @click="loadGovernance">{{ gt('retry') }}</button>
        </div>
        <AppLoadingState v-if="loading" class="mt-4" :label="gt('loading')" inline />
        <p v-if="error" class="mt-3 rounded-lg border border-danger/30 bg-danger/10 p-3 text-sm text-danger" role="alert">{{ error }}</p>

        <div v-if="!loading" class="mt-5 grid gap-4 lg:grid-cols-2">
            <article v-for="body in governance.bodies" :key="body.id" class="rounded-lg border border-border bg-bg p-4">
                <div class="flex items-start justify-between gap-3"><div><p class="text-xs font-semibold uppercase tracking-wide text-secondary">{{ gt(body.type) }}</p><h3 class="font-semibold text-primary">{{ body.name }}</h3></div><span class="text-xs text-secondary">{{ body.is_public ? gt('publicLabel') : gt('internal') }}</span></div>
                <p v-if="body.description" class="mt-2 whitespace-pre-line text-sm text-secondary">{{ body.description }}</p>
                <p v-if="bodyPeriod(body)" class="mt-2 text-xs text-secondary">{{ gt('period') }}: {{ bodyPeriod(body) }}</p>
                <div class="mt-4 space-y-2">
                    <div v-for="assignment in body.assignments" :key="assignment.id" class="rounded-lg border border-border bg-card p-3 text-sm">
                        <div class="flex items-start justify-between gap-2"><div><p class="font-semibold text-primary">{{ assignment.person.name }}</p><p class="text-secondary">{{ assignment.position_title }}</p></div><span class="text-xs text-secondary">{{ assignment.is_public ? gt('publicLabel') : gt('internal') }}</span></div>
                        <p v-if="assignment.responsibilities" class="mt-2 whitespace-pre-line text-secondary">{{ assignment.responsibilities }}</p>
                        <p v-if="bodyPeriod(assignment)" class="mt-2 text-xs text-secondary">{{ gt('period') }}: {{ bodyPeriod(assignment) }}</p>
                        <div v-if="canEdit || canDelete" class="mt-2 flex gap-3"><button v-if="canEdit" type="button" class="text-xs font-semibold text-link" @click="editAssignment(body, assignment)">{{ gt('edit') }}</button><button v-if="canDelete" type="button" class="text-xs font-semibold text-danger" @click="deleteAssignment(body, assignment)">{{ gt('delete') }}</button></div>
                    </div>
                    <p v-if="!body.assignments.length" class="text-sm text-secondary">{{ gt('emptyAssignments') }}</p>
                </div>
                <div v-if="canEdit || canDelete" class="mt-3 flex flex-wrap gap-3"><button v-if="canEdit" type="button" class="text-xs font-semibold text-link" @click="editBody(body)">{{ gt('edit') }}</button><button v-if="canEdit" type="button" class="text-xs font-semibold text-link" @click="beginAssignment(body)">{{ gt('addAssignment') }}</button><button v-if="canDelete" type="button" class="text-xs font-semibold text-danger" @click="deleteBody(body)">{{ gt('delete') }}</button></div>
            </article>
            <p v-if="!governance.bodies.length" class="text-sm text-secondary">{{ gt('empty') }}</p>
        </div>

        <div v-if="canEdit" class="mt-5 grid gap-4 border-t border-border pt-5 lg:grid-cols-2">
            <form class="grid gap-2 rounded-lg border border-border p-4" @submit.prevent="saveBody">
                <select v-model="bodyForm.type" :aria-label="gt('type')" class="rounded-lg border border-border bg-inputBg px-3 py-2 text-sm text-primary"><option value="board">{{ gt('board') }}</option><option value="committee">{{ gt('committee') }}</option><option value="working_group">{{ gt('working_group') }}</option></select>
                <input v-model="bodyForm.name" required maxlength="160" class="rounded-lg border border-border bg-inputBg px-3 py-2 text-sm text-primary" :placeholder="gt('name')" :aria-label="gt('name')">
                <textarea v-model="bodyForm.description" maxlength="3000" rows="2" class="rounded-lg border border-border bg-inputBg px-3 py-2 text-sm text-primary" :placeholder="gt('description')" :aria-label="gt('description')"></textarea>
                <div class="grid grid-cols-2 gap-2"><input v-model="bodyForm.starts_on" type="date" :aria-label="gt('startsOn')" class="rounded-lg border border-border bg-inputBg px-3 py-2 text-sm text-primary"><input v-model="bodyForm.ends_on" type="date" :aria-label="gt('endsOn')" class="rounded-lg border border-border bg-inputBg px-3 py-2 text-sm text-primary"></div>
                <label class="flex items-center gap-2 text-xs text-secondary"><input v-model="bodyForm.is_public" type="checkbox" class="rounded border-border bg-inputBg">{{ gt('public') }}</label>
                <div class="flex gap-2"><button class="rounded-lg bg-buttonPrimary px-3 py-2 text-xs font-semibold text-buttonTextPrimary" :disabled="saving">{{ bodyForm.id ? gt('save') : gt('add') }}</button><button v-if="bodyForm.id" type="button" class="px-3 py-2 text-xs font-semibold text-secondary" @click="bodyForm = emptyBody()">{{ gt('cancel') }}</button></div>
            </form>

            <form v-if="assignmentForm.body_id" class="grid gap-2 rounded-lg border border-border p-4" @submit.prevent="saveAssignment">
                <select v-model="assignmentForm.person_key" required :aria-label="gt('person')" class="rounded-lg border border-border bg-inputBg px-3 py-2 text-sm text-primary"><option value="" disabled>{{ gt('selectPerson') }}</option><option v-for="person in governance.member_options" :key="`${person.kind}:${person.id}`" :value="`${person.kind}:${person.id}`">{{ person.name }}</option></select>
                <input v-model="assignmentForm.position_title" required maxlength="160" class="rounded-lg border border-border bg-inputBg px-3 py-2 text-sm text-primary" :placeholder="gt('position')" :aria-label="gt('position')">
                <textarea v-model="assignmentForm.responsibilities" maxlength="3000" rows="2" class="rounded-lg border border-border bg-inputBg px-3 py-2 text-sm text-primary" :placeholder="gt('responsibilities')" :aria-label="gt('responsibilities')"></textarea>
                <div class="grid grid-cols-2 gap-2"><input v-model="assignmentForm.starts_on" type="date" :aria-label="gt('startsOn')" class="rounded-lg border border-border bg-inputBg px-3 py-2 text-sm text-primary"><input v-model="assignmentForm.ends_on" type="date" :aria-label="gt('endsOn')" class="rounded-lg border border-border bg-inputBg px-3 py-2 text-sm text-primary"></div>
                <label class="flex items-center gap-2 text-xs text-secondary"><input v-model="assignmentForm.is_public" type="checkbox" class="rounded border-border bg-inputBg">{{ gt('public') }}</label>
                <div class="flex gap-2"><button class="rounded-lg bg-buttonPrimary px-3 py-2 text-xs font-semibold text-buttonTextPrimary" :disabled="saving">{{ assignmentForm.id ? gt('save') : gt('add') }}</button><button type="button" class="px-3 py-2 text-xs font-semibold text-secondary" @click="assignmentForm = emptyAssignment()">{{ gt('cancel') }}</button></div>
            </form>
        </div>
    </section>
</template>
