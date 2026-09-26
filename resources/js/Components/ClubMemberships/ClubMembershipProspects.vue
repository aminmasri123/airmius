<script setup>
import { computed, reactive, ref, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import { confirmDialog } from '@/services/dialogService'
import prospectTranslations from '@/i18n/clubMembershipProspectsLocalization.json'

const props = defineProps({
    club: { type: Object, default: null },
})

const { t, locale } = useI18n()
const tx = (key, fallback) => {
    const translated = t(key)
    return translated === key ? fallback : translated
}
const pt = key => (prospectTranslations[locale.value] || prospectTranslations.de)[key]
const prospects = ref([])
const loading = ref(false)
const saving = ref(false)
const error = ref('')
const feedback = ref('')
const editingId = ref(null)
const emptyForm = () => ({
    name: '',
    email: '',
    phone: '',
    status: 'prospect',
    source: '',
    trial_at: '',
    trial_outcome: '',
    team_id: '',
    club_membership_type_id: '',
    notes: '',
})
const form = reactive(emptyForm())
const canEdit = computed(() => props.club?.can_manage_members === true
    || props.club?.permissions?.effective?.['members.edit'] === true)
const statusOptions = computed(() => [
    ['prospect', pt('status_prospect')],
    ['trial_scheduled', pt('status_trial_scheduled')],
    ['trial_completed', pt('status_trial_completed')],
    ['application', pt('status_application')],
    ['declined', pt('status_declined')],
    ['archived', pt('status_archived')],
])
const outcomeOptions = computed(() => [
    ['', pt('outcome_open')],
    ['interested', pt('outcome_interested')],
    ['application', pt('outcome_application')],
    ['no_show', pt('outcome_no_show')],
    ['declined', pt('outcome_declined')],
])
const statusLabel = status => statusOptions.value.find(([value]) => value === status)?.[1]
    || (status === 'converted' ? pt('status_converted') : status)
const formatDateTime = value => value
    ? new Intl.DateTimeFormat(locale.value, { dateStyle: 'medium', timeStyle: 'short' }).format(new Date(value))
    : '–'
const normalizeDateTime = value => value ? new Date(value).toISOString().slice(0, 16) : ''
const apiError = exception => Object.values(exception.response?.data?.errors || {}).flat()[0]
    || exception.response?.data?.message
    || pt('error')

const load = async () => {
    if (!props.club?.id) return
    loading.value = true
    error.value = ''
    try {
        const response = await window.axios.get(route('api.v1.clubs.membership-prospects.index', props.club.id), {
            headers: { Accept: 'application/json' },
        })
        prospects.value = response.data?.data || []
    } catch (exception) {
        error.value = apiError(exception)
    } finally {
        loading.value = false
    }
}

const reset = () => {
    Object.assign(form, emptyForm())
    editingId.value = null
}
const edit = prospect => {
    editingId.value = prospect.id
    Object.assign(form, {
        name: prospect.name || '',
        email: prospect.email || '',
        phone: prospect.phone || '',
        status: prospect.status || 'prospect',
        source: prospect.source || '',
        trial_at: normalizeDateTime(prospect.trial_at),
        trial_outcome: prospect.trial_outcome || '',
        team_id: prospect.team_id || '',
        club_membership_type_id: prospect.club_membership_type_id || '',
        notes: prospect.notes || '',
    })
}
const save = async () => {
    if (!props.club?.id || !canEdit.value || saving.value) return
    saving.value = true
    error.value = ''
    feedback.value = ''
    const payload = {
        ...form,
        email: form.email || null,
        phone: form.phone || null,
        source: form.source || null,
        trial_at: form.trial_at || null,
        trial_outcome: form.trial_outcome || null,
        team_id: form.team_id || null,
        club_membership_type_id: form.club_membership_type_id || null,
        notes: form.notes || null,
    }
    try {
        const url = editingId.value
            ? route('api.v1.clubs.membership-prospects.update', [props.club.id, editingId.value])
            : route('api.v1.clubs.membership-prospects.store', props.club.id)
        await window.axios({ method: editingId.value ? 'put' : 'post', url, data: payload, headers: { Accept: 'application/json' } })
        feedback.value = pt('saved')
        reset()
        await load()
    } catch (exception) {
        error.value = apiError(exception)
    } finally {
        saving.value = false
    }
}
const archive = async prospect => {
    const confirmed = await confirmDialog({
        title: pt('archive_title'),
        message: pt('archive_message'),
        confirmLabel: pt('archive'),
    })
    if (!confirmed) return
    try {
        await window.axios.post(route('api.v1.clubs.membership-prospects.archive', [props.club.id, prospect.id]), {}, { headers: { Accept: 'application/json' } })
        await load()
    } catch (exception) {
        error.value = apiError(exception)
    }
}

watch(() => props.club?.id, () => {
    reset()
    void load()
}, { immediate: true })
</script>

<template>
    <section class="grid gap-6 xl:grid-cols-[minmax(0,1.3fr)_minmax(20rem,0.7fr)]">
        <div class="surface-card p-5">
            <div class="flex items-start justify-between gap-3">
                <div>
                    <h2 class="text-lg font-semibold text-primary">{{ pt('title') }}</h2>
                    <p class="mt-1 text-sm text-secondary">{{ pt('intro') }}</p>
                </div>
                <button type="button" class="rounded-lg border border-border px-3 py-2 text-sm font-semibold text-primary" :disabled="loading" @click="load">{{ tx('auto.Aktualisieren', 'Aktualisieren') }}</button>
            </div>
            <p v-if="error" class="mt-4 rounded-lg border border-error/30 bg-error/10 p-3 text-sm text-error">{{ error }}</p>
            <p v-if="feedback" class="mt-4 rounded-lg border border-success/30 bg-success/10 p-3 text-sm text-success">{{ feedback }}</p>
            <p v-if="loading" class="mt-5 text-sm text-secondary">{{ tx('auto.Wird geladen …', 'Wird geladen …') }}</p>
            <p v-else-if="!prospects.length" class="mt-5 rounded-lg border border-dashed border-border p-6 text-center text-sm text-secondary">{{ pt('empty') }}</p>
            <div v-else class="mt-5 space-y-3">
                <article v-for="prospect in prospects" :key="prospect.id" class="rounded-xl border border-border bg-bg p-4">
                    <div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
                        <div>
                            <div class="flex flex-wrap items-center gap-2">
                                <h3 class="font-semibold text-primary">{{ prospect.name }}</h3>
                                <span class="rounded-full bg-muted px-2 py-1 text-xs font-semibold text-secondary">{{ statusLabel(prospect.status) }}</span>
                            </div>
                            <p class="mt-1 text-sm text-secondary">{{ [prospect.email, prospect.phone].filter(Boolean).join(' · ') || '–' }}</p>
                            <p v-if="prospect.trial_at" class="mt-2 text-sm text-primary"><i class="las la-calendar-check mr-1"></i>{{ formatDateTime(prospect.trial_at) }}<span v-if="prospect.team"> · {{ prospect.team.name }}</span></p>
                            <p v-if="prospect.membership_type" class="mt-1 text-xs text-secondary">{{ prospect.membership_type.name }}</p>
                        </div>
                        <div v-if="canEdit && prospect.status !== 'converted'" class="flex gap-2">
                            <button type="button" class="rounded-lg border border-border px-3 py-2 text-sm font-semibold text-primary" @click="edit(prospect)">{{ tx('auto.Bearbeiten', 'Bearbeiten') }}</button>
                            <button v-if="prospect.status !== 'archived'" type="button" class="rounded-lg border border-border px-3 py-2 text-sm font-semibold text-secondary" @click="archive(prospect)">{{ pt('archive') }}</button>
                        </div>
                    </div>
                </article>
            </div>
        </div>

        <form v-if="canEdit" class="surface-card p-5" @submit.prevent="save">
            <h2 class="text-lg font-semibold text-primary">{{ editingId ? pt('edit') : pt('create') }}</h2>
            <div class="mt-4 grid gap-3">
                <label class="text-sm text-primary">{{ tx('auto.Name', 'Name') }}<input v-model.trim="form.name" required maxlength="255" class="mt-1 w-full rounded-lg border-border bg-inputBg"></label>
                <label class="text-sm text-primary">{{ tx('auto.E-Mail', 'E-Mail') }}<input v-model.trim="form.email" type="email" maxlength="255" class="mt-1 w-full rounded-lg border-border bg-inputBg"></label>
                <label class="text-sm text-primary">{{ tx('auto.Telefon', 'Telefon') }}<input v-model.trim="form.phone" maxlength="40" class="mt-1 w-full rounded-lg border-border bg-inputBg"></label>
                <label class="text-sm text-primary">{{ tx('auto.Status', 'Status') }}<select v-model="form.status" class="mt-1 w-full rounded-lg border-border bg-inputBg"><option v-for="option in statusOptions" :key="option[0]" :value="option[0]">{{ option[1] }}</option></select></label>
                <label class="text-sm text-primary">{{ pt('trial_at') }}<input v-model="form.trial_at" type="datetime-local" class="mt-1 w-full rounded-lg border-border bg-inputBg"></label>
                <label class="text-sm text-primary">{{ pt('outcome') }}<select v-model="form.trial_outcome" class="mt-1 w-full rounded-lg border-border bg-inputBg"><option v-for="option in outcomeOptions" :key="option[0]" :value="option[0]">{{ option[1] }}</option></select></label>
                <label class="text-sm text-primary">{{ tx('auto.Team', 'Team') }}<select v-model="form.team_id" class="mt-1 w-full rounded-lg border-border bg-inputBg"><option value="">–</option><option v-for="team in club.teams || []" :key="team.id" :value="team.id">{{ team.name }}</option></select></label>
                <label class="text-sm text-primary">{{ tx('club_memberships.workspace.membership_type', 'Mitgliedschaftstyp') }}<select v-model="form.club_membership_type_id" class="mt-1 w-full rounded-lg border-border bg-inputBg"><option value="">–</option><option v-for="type in club.membership_types || []" :key="type.id" :value="type.id">{{ type.name }}</option></select></label>
                <label class="text-sm text-primary">{{ pt('source') }}<input v-model.trim="form.source" maxlength="100" class="mt-1 w-full rounded-lg border-border bg-inputBg"></label>
                <label class="text-sm text-primary">{{ tx('auto.Notiz', 'Notiz') }}<textarea v-model.trim="form.notes" rows="3" maxlength="5000" class="mt-1 w-full rounded-lg border-border bg-inputBg"></textarea></label>
            </div>
            <div class="mt-4 flex justify-end gap-2">
                <button v-if="editingId" type="button" class="rounded-lg border border-border px-4 py-2 text-sm font-semibold text-primary" @click="reset">{{ tx('auto.Abbrechen', 'Abbrechen') }}</button>
                <button type="submit" class="rounded-lg bg-buttonPrimary px-4 py-2 text-sm font-semibold text-buttonTextPrimary disabled:opacity-60" :disabled="saving">{{ saving ? tx('auto.Wird gespeichert …', 'Wird gespeichert …') : tx('auto.Speichern', 'Speichern') }}</button>
            </div>
        </form>
    </section>
</template>
