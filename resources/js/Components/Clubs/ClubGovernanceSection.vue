<script setup>
import AppLoadingState from '@/Components/UI/AppLoadingState.vue'
import ClubGovernanceMeetings from './ClubGovernanceMeetings.vue'
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
    return { id: null, template_key: '', type: 'board', name: '', description: '', starts_on: '', ends_on: '', is_public: false, positions: [] }
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
const personKey = (assignment) => assignment?.user_id
    ? `user:${assignment.user_id}`
    : assignment?.club_external_member_id ? `external:${assignment.club_external_member_id}` : ''
const editablePosition = (position, index = 0) => ({
    id: position.id || null,
    title: position.title || position.position_title || '',
    responsibilities: position.responsibilities || '',
    sort_order: position.sort_order ?? index,
    is_required: position.is_required === true,
    allows_multiple: position.allows_multiple === true,
    is_public: position.is_public === true,
    person_key: personKey(position.assignments?.[0] || position),
    assignment_id: position.assignments?.[0]?.id || (position.position_title ? position.id : null),
})
const bodyPositions = (body) => body.positions?.length
    ? body.positions
    : (body.assignments || []).map((assignment, index) => ({ ...assignment, title: assignment.position_title, sort_order: index, assignments: [assignment] }))
const editBody = (body) => {
    bodyForm.value = {
        ...body,
        starts_on: body.starts_on || '',
        ends_on: body.ends_on || '',
        positions: bodyPositions(body).map(editablePosition),
    }
}
const applyTemplate = () => {
    const template = governance.value.templates?.find((entry) => entry.key === bodyForm.value.template_key)
    if (!template) return
    bodyForm.value = {
        ...bodyForm.value,
        type: template.type,
        name: template.name,
        description: template.description,
        positions: template.positions.map(editablePosition),
    }
}
const addPosition = () => bodyForm.value.positions.push(editablePosition({}, bodyForm.value.positions.length))
const removePosition = (index) => bodyForm.value.positions.splice(index, 1)
const positionPayload = (position, index, form) => {
    const [kind, rawId] = (position.person_key || '').split(':')
    return {
        ...(position.id ? { id: position.id } : {}),
        title: position.title,
        responsibilities: position.responsibilities || null,
        sort_order: index,
        is_required: position.is_required,
        allows_multiple: position.allows_multiple,
        is_public: position.is_public,
        // Existing multi-person assignments stay intact and remain editable below.
        replace_assignments: false,
        assignments: rawId ? [{
            ...(position.assignment_id ? { id: position.assignment_id } : {}),
            user_id: kind === 'user' ? Number(rawId) : null,
            club_external_member_id: kind === 'external' ? Number(rawId) : null,
            starts_on: form.starts_on || null,
            ends_on: form.ends_on || null,
            is_public: position.is_public,
        }] : [],
    }
}
const saveBody = async () => {
    saving.value = true
    error.value = ''
    try {
        const form = bodyForm.value
        const url = `${baseUrl}/bodies${form.id ? `/${form.id}` : ''}`
        const data = { ...form, template_key: form.template_key || null, replace_positions: true, positions: form.positions.map((position, index) => positionPayload(position, index, form)) }
        await window.axios({ method: form.id ? 'put' : 'post', url, data, headers: { Accept: 'application/json' } })
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
                    <div v-for="position in bodyPositions(body)" :key="`position-${position.id}`" class="rounded-lg border border-border bg-card p-3 text-sm">
                        <div class="flex items-start justify-between gap-2">
                            <div><p class="font-semibold text-primary">{{ position.title || position.position_title }}</p><p v-if="position.responsibilities" class="mt-1 whitespace-pre-line text-secondary">{{ position.responsibilities }}</p></div>
                            <span class="text-xs text-secondary">{{ position.is_public ? gt('publicLabel') : gt('internal') }}</span>
                        </div>
                        <div v-if="position.assignments?.length" class="mt-3 space-y-2">
                            <div v-for="assignment in position.assignments" :key="assignment.id" class="flex flex-wrap items-center justify-between gap-2 border-t border-border pt-2">
                                <div><p class="font-medium text-primary">{{ assignment.person.name }}</p><p v-if="bodyPeriod(assignment)" class="text-xs text-secondary">{{ gt('period') }}: {{ bodyPeriod(assignment) }}</p></div>
                                <div v-if="canEdit || canDelete" class="flex gap-3"><button v-if="canEdit" type="button" class="text-xs font-semibold text-link" @click="editAssignment(body, assignment)">{{ gt('edit') }}</button><button v-if="canDelete" type="button" class="text-xs font-semibold text-danger" @click="deleteAssignment(body, assignment)">{{ gt('delete') }}</button></div>
                            </div>
                        </div>
                        <p v-else class="mt-2 text-xs text-secondary">{{ gt('vacant') }}</p>
                    </div>
                    <p v-if="!bodyPositions(body).length" class="text-sm text-secondary">{{ gt('emptyAssignments') }}</p>
                </div>
                <div v-if="canEdit || canDelete" class="mt-3 flex flex-wrap gap-3"><button v-if="canEdit" type="button" class="text-xs font-semibold text-link" @click="editBody(body)">{{ gt('edit') }}</button><button v-if="canEdit" type="button" class="text-xs font-semibold text-link" @click="beginAssignment(body)">{{ gt('addAssignment') }}</button><button v-if="canDelete" type="button" class="text-xs font-semibold text-danger" @click="deleteBody(body)">{{ gt('delete') }}</button></div>
            </article>
            <p v-if="!governance.bodies.length" class="text-sm text-secondary">{{ gt('empty') }}</p>
        </div>

        <div v-if="canEdit" class="mt-6 border-t border-border pt-6">
            <form class="mx-auto grid max-w-3xl gap-6" @submit.prevent="saveBody">
                <div>
                    <h3 class="text-base font-semibold text-primary">{{ bodyForm.id ? gt('editBody') : gt('addBody') }}</h3>
                    <p class="mt-1 text-sm text-secondary">{{ gt('formHint') }}</p>
                </div>

                <label v-if="!bodyForm.id" class="grid gap-2 text-sm font-semibold text-primary">
                    {{ gt('template') }}
                    <select v-model="bodyForm.template_key" class="rounded-lg border border-border bg-inputBg px-3 py-3 text-sm text-primary" @change="applyTemplate">
                        <option value="">{{ gt('customTemplate') }}</option>
                        <option v-for="template in governance.templates" :key="template.key" :value="template.key">{{ template.name }}</option>
                    </select>
                    <span class="text-xs font-normal text-secondary">{{ gt('templateHint') }}</span>
                </label>

                <div class="grid gap-3 sm:grid-cols-2">
                    <label class="grid gap-2 text-sm font-semibold text-primary">{{ gt('type') }}<select v-model="bodyForm.type" class="rounded-lg border border-border bg-inputBg px-3 py-3 text-sm text-primary"><option value="board">{{ gt('board') }}</option><option value="committee">{{ gt('committee') }}</option><option value="working_group">{{ gt('working_group') }}</option></select></label>
                    <label class="grid gap-2 text-sm font-semibold text-primary">{{ gt('name') }}<input v-model="bodyForm.name" required maxlength="160" class="rounded-lg border border-border bg-inputBg px-3 py-3 text-sm text-primary"></label>
                    <label class="grid gap-2 text-sm font-semibold text-primary sm:col-span-2">{{ gt('description') }}<textarea v-model="bodyForm.description" maxlength="3000" rows="3" class="rounded-lg border border-border bg-inputBg px-3 py-3 text-sm text-primary"></textarea></label>
                    <label class="grid gap-2 text-sm font-semibold text-primary">{{ gt('startsOn') }}<input v-model="bodyForm.starts_on" type="date" class="rounded-lg border border-border bg-inputBg px-3 py-3 text-sm text-primary"></label>
                    <label class="grid gap-2 text-sm font-semibold text-primary">{{ gt('endsOn') }}<input v-model="bodyForm.ends_on" type="date" class="rounded-lg border border-border bg-inputBg px-3 py-3 text-sm text-primary"></label>
                </div>

                <div class="grid gap-3">
                    <div><h4 class="font-semibold text-primary">{{ gt('positions') }}</h4><p class="mt-1 text-sm text-secondary">{{ gt('positionsHint') }}</p></div>
                    <div v-for="(position, index) in bodyForm.positions" :key="position.id || `new-${index}`" class="grid gap-3 rounded-lg border border-border p-4 sm:grid-cols-2">
                        <label class="grid gap-2 text-sm font-semibold text-primary">{{ gt('position') }}<input v-model="position.title" required maxlength="160" class="rounded-lg border border-border bg-inputBg px-3 py-2 text-sm text-primary"></label>
                        <label class="grid gap-2 text-sm font-semibold text-primary">{{ gt('person') }}<select v-model="position.person_key" class="rounded-lg border border-border bg-inputBg px-3 py-2 text-sm text-primary"><option value="">{{ gt('vacant') }}</option><option v-for="person in governance.member_options" :key="`${person.kind}:${person.id}`" :value="`${person.kind}:${person.id}`">{{ person.name }}{{ person.kind === 'external' ? ` · ${gt('external')}` : '' }}</option></select></label>
                        <label class="grid gap-2 text-sm font-semibold text-primary sm:col-span-2">{{ gt('responsibilities') }}<textarea v-model="position.responsibilities" maxlength="3000" rows="2" class="rounded-lg border border-border bg-inputBg px-3 py-2 text-sm text-primary"></textarea></label>
                        <div class="flex flex-wrap gap-4 text-xs text-secondary sm:col-span-2"><label class="flex items-center gap-2"><input v-model="position.is_required" type="checkbox" class="rounded border-border bg-inputBg">{{ gt('requiredPosition') }}</label><label class="flex items-center gap-2"><input v-model="position.allows_multiple" type="checkbox" class="rounded border-border bg-inputBg">{{ gt('allowsMultiple') }}</label><label class="flex items-center gap-2"><input v-model="position.is_public" type="checkbox" class="rounded border-border bg-inputBg">{{ gt('publicPosition') }}</label><button type="button" class="ml-auto font-semibold text-danger" @click="removePosition(index)">{{ gt('remove') }}</button></div>
                    </div>
                    <button type="button" class="w-fit rounded-lg border border-border px-3 py-2 text-sm font-semibold text-primary" @click="addPosition">+ {{ gt('addPosition') }}</button>
                </div>

                <label class="flex items-start gap-3 text-sm text-secondary"><input v-model="bodyForm.is_public" type="checkbox" class="mt-1 rounded border-border bg-inputBg"><span><strong class="block text-primary">{{ gt('public') }}</strong>{{ gt('publicHint') }}</span></label>
                <div class="flex flex-wrap justify-end gap-2 border-t border-border pt-4"><button v-if="bodyForm.id" type="button" class="rounded-lg border border-border px-4 py-3 text-sm font-semibold text-secondary" @click="bodyForm = emptyBody()">{{ gt('cancel') }}</button><button class="rounded-lg bg-buttonPrimary px-5 py-3 text-sm font-semibold text-buttonTextPrimary" :disabled="saving">{{ bodyForm.id ? gt('save') : gt('add') }}</button></div>
            </form>

            <form v-if="assignmentForm.body_id" class="mx-auto mt-6 grid max-w-3xl gap-2 rounded-lg border border-border p-4" @submit.prevent="saveAssignment">
                <select v-model="assignmentForm.person_key" required :aria-label="gt('person')" class="rounded-lg border border-border bg-inputBg px-3 py-2 text-sm text-primary"><option value="" disabled>{{ gt('selectPerson') }}</option><option v-for="person in governance.member_options" :key="`${person.kind}:${person.id}`" :value="`${person.kind}:${person.id}`">{{ person.name }}</option></select>
                <input v-model="assignmentForm.position_title" required maxlength="160" class="rounded-lg border border-border bg-inputBg px-3 py-2 text-sm text-primary" :placeholder="gt('position')" :aria-label="gt('position')">
                <textarea v-model="assignmentForm.responsibilities" maxlength="3000" rows="2" class="rounded-lg border border-border bg-inputBg px-3 py-2 text-sm text-primary" :placeholder="gt('responsibilities')" :aria-label="gt('responsibilities')"></textarea>
                <div class="grid grid-cols-2 gap-2"><input v-model="assignmentForm.starts_on" type="date" :aria-label="gt('startsOn')" class="rounded-lg border border-border bg-inputBg px-3 py-2 text-sm text-primary"><input v-model="assignmentForm.ends_on" type="date" :aria-label="gt('endsOn')" class="rounded-lg border border-border bg-inputBg px-3 py-2 text-sm text-primary"></div>
                <label class="flex items-center gap-2 text-xs text-secondary"><input v-model="assignmentForm.is_public" type="checkbox" class="rounded border-border bg-inputBg">{{ gt('public') }}</label>
                <div class="flex gap-2"><button class="rounded-lg bg-buttonPrimary px-3 py-2 text-xs font-semibold text-buttonTextPrimary" :disabled="saving">{{ assignmentForm.id ? gt('save') : gt('add') }}</button><button type="button" class="px-3 py-2 text-xs font-semibold text-secondary" @click="assignmentForm = emptyAssignment()">{{ gt('cancel') }}</button></div>
            </form>
        </div>
    </section>
    <ClubGovernanceMeetings v-if="governance.can_view_meetings" :club-id="clubId" />
</template>
