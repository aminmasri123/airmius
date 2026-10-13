<script setup>
import AppLayout from '@/Components/Auth/Layouts/AppLayout.vue'
import ClubWorkspaceNav from '@/Components/Auth/ClubWorkspaceNav.vue'
import labels from '@/i18n/workAutomation.json'
import { Head, usePage } from '@inertiajs/vue3'
import { computed, ref, watch, onMounted, onUnmounted } from 'vue'
import { useI18n } from 'vue-i18n'
import { createRequest, pendingKey, readPending, uncertainResponse } from './requests'

defineOptions({ layout: AppLayout })
const props = defineProps({ clubs: { type: Array, default: () => [] } })
const page = usePage()
const { locale } = useI18n()
const tx = (key) => labels[locale.value]?.[key] || labels.en[key] || key
const query = new URLSearchParams(window.location.search)
const clubId = ref(props.clubs.find((club) => String(club.id) === query.get('club_id'))?.id || props.clubs[0]?.id)
const club = computed(() => props.clubs.find((club) => club.id === clubId.value))
const status = ref('')
const currentPage = ref(1)
const jobs = ref([])
const meta = ref({ last_page: 1, can_create: false, recipient_permissions: [] })
const loading = ref(false)
const busy = ref(false)
const error = ref('')
const pending = ref(null)
const recipientCount = ref(null)
const detail = ref(null)
const dialog = ref(null)
const emptyForm = () => ({ kind: 'reminder', title: '', body: '', recipient_roles: [], scheduled_at: '' })
const form = ref(emptyForm())
const statuses = ['scheduled', 'queued', 'running', 'completed', 'failed']
const storageKey = () => pendingKey(page.props.auth.user.id, clubId.value)
const date = (value) => value ? new Date(value).toLocaleString(locale.value) : ''
const errorLabel = (code) => tx(({ queue_dispatch_failed: 'dispatchError', queue_worker_failed: 'workerError', local_delivery_failed: 'deliveryError', no_recipients: 'noRecipients' })[code] || 'error')
let revision = 0
let poll
let recipientRevision = 0
const previewRecipients = async () => {
    const requestRevision = ++recipientRevision
    recipientCount.value = null
    if (!club.value?.can_create || form.value.recipient_roles.length > 8) return
    try {
        const response = await window.axios.get(route('api.v1.clubs.work-automation-jobs.recipients', clubId.value), { params: { recipient_roles: form.value.recipient_roles } })
        if (requestRevision === recipientRevision) recipientCount.value = response.data.data.count
    } catch { if (requestRevision === recipientRevision) error.value = tx('error') }
}
watch(() => form.value.recipient_roles, previewRecipients, { deep: true })

const load = async (silent = false) => {
    if (!clubId.value) return
    const requestRevision = ++revision
    if (!silent) loading.value = true
    try {
        const response = await window.axios.get(route('api.v1.clubs.work-automation-jobs.index', clubId.value), { params: { status: status.value || undefined, page: currentPage.value } })
        if (requestRevision !== revision) return
        jobs.value = response.data.data
        meta.value = response.data.meta
        error.value = ''
        if (detail.value && dialog.value?.open) {
            await showDetails(detail.value.id)
        }
    } catch (failure) {
        if (requestRevision !== revision) return
        error.value = failure.response?.status === 403 ? tx('noAccess') : tx('error')
        if (failure.response?.status === 403) { jobs.value = []; meta.value.can_create = false; dialog.value?.close() }
    } finally { if (requestRevision === revision) loading.value = false }
}

const submit = async () => {
    if (busy.value) return
    busy.value = true
    error.value = ''
    try {
        if (!pending.value) {
            pending.value = createRequest(form.value)
            sessionStorage.setItem(storageKey(), JSON.stringify(pending.value))
        }
        const response = await window.axios.post(route('api.v1.clubs.work-automation-jobs.store', clubId.value), pending.value)
        sessionStorage.removeItem(storageKey())
        pending.value = null
        form.value = emptyForm()
        await load()
        await showDetails(response.data.data.id)
    } catch (failure) {
        error.value = uncertainResponse(failure) ? tx('uncertain') : (Object.values(failure.response?.data?.errors || {}).flat()[0] || tx('error'))
        if (!uncertainResponse(failure)) { sessionStorage.removeItem(storageKey()); pending.value = null }
    } finally { busy.value = false }
}
const discard = () => { sessionStorage.removeItem(storageKey()); pending.value = null; error.value = '' }
const showDetails = async (id) => {
    const selected = clubId.value
    try {
        const response = await window.axios.get(route('api.v1.clubs.work-automation-jobs.show', [selected, id]))
        if (clubId.value !== selected) return
        detail.value = response.data.data
        dialog.value.showModal()
    } catch { error.value = tx('error') }
}
const retry = async (job) => {
    if (busy.value || !window.confirm(tx('confirmRetry'))) return
    busy.value = true
    try {
        await window.axios.post(route('api.v1.clubs.work-automation-jobs.retry', [clubId.value, job.id]), { expected_attempts: job.attempts })
        await load()
    } catch (failure) { error.value = uncertainResponse(failure) ? tx('uncertain') : tx('error'); await load(true) }
    finally { busy.value = false }
}
watch([status, currentPage], () => load())
watch(clubId, () => {
    revision++
    jobs.value = []; meta.value.can_create = false; detail.value = null; dialog.value?.close()
    currentPage.value = 1; status.value = ''; form.value = emptyForm()
    pending.value = readPending(sessionStorage, storageKey())
    const url = new URL(window.location.href); url.searchParams.set('club_id', clubId.value); window.history.replaceState(null, '', url)
    load()
    previewRecipients()
}, { immediate: true })
onMounted(() => { poll = window.setInterval(() => { if (!busy.value && !document.hidden) load(true) }, 10000) })
onUnmounted(() => { revision++; window.clearInterval(poll) })
</script>

<template>
    <Head :title="tx('title')" />
    <main class="mx-auto max-w-6xl space-y-6 p-4 sm:p-6">
        <ClubWorkspaceNav />
        <header class="flex flex-wrap items-center justify-between gap-3">
            <div><h1 class="text-2xl font-bold text-primary">{{ tx('title') }}</h1><p class="text-sm text-secondary">{{ tx('supported') }}</p></div>
            <label class="flex items-center gap-2 text-sm text-primary">{{ tx('club') }}
                <select v-model="clubId" :disabled="busy" class="max-w-full rounded-lg border-border bg-inputBg"><option v-for="item in clubs" :key="item.id" :value="item.id">{{ item.name }}</option></select>
            </label>
        </header>
        <p v-if="error" role="alert" class="break-words text-sm text-red-600">{{ error }}</p>
        <section v-if="pending" class="space-y-2 border-y border-border py-4">
            <p class="text-sm text-primary">{{ tx('uncertain') }}</p>
            <p class="break-words text-sm text-secondary">{{ pending.payload.title }} · {{ pending.idempotency_key }}</p>
            <div class="flex flex-wrap gap-3"><button :disabled="busy" class="text-sm font-semibold text-primary" @click="submit">{{ tx('resend') }}</button><button :disabled="busy" class="text-sm text-secondary" @click="discard">{{ tx('discard') }}</button></div>
        </section>
        <form v-if="meta.can_create && !pending" class="grid gap-4 border-y border-border py-5 sm:grid-cols-2" @submit.prevent="submit">
            <h2 class="text-lg font-semibold text-primary sm:col-span-2">{{ tx('new') }}</h2>
            <label class="text-sm text-primary">{{ tx('kind') }}<select v-model="form.kind" class="mt-1 w-full rounded-lg border-border bg-inputBg"><option value="reminder">{{ tx('reminder') }}</option><option value="escalation">{{ tx('escalation') }}</option></select></label>
            <label class="text-sm text-primary">{{ tx('when') }}<input v-model="form.scheduled_at" type="datetime-local" class="mt-1 w-full rounded-lg border-border bg-inputBg" /><span class="text-xs text-secondary">{{ form.scheduled_at ? date(form.scheduled_at) : tx('immediate') }}</span></label>
            <label class="text-sm text-primary sm:col-span-2">{{ tx('subject') }}<input v-model="form.title" required maxlength="160" class="mt-1 w-full rounded-lg border-border bg-inputBg" /></label>
            <label class="text-sm text-primary sm:col-span-2">{{ tx('body') }}<textarea v-model="form.body" required maxlength="2000" rows="3" class="mt-1 w-full rounded-lg border-border bg-inputBg" /></label>
            <label class="text-sm text-primary">{{ tx('recipients') }}<select v-model="form.recipient_roles" multiple size="4" class="mt-1 w-full rounded-lg border-border bg-inputBg"><option v-for="permission in meta.recipient_permissions" :key="permission.key" :value="permission.key">{{ permission.key }}</option></select><span class="text-xs text-secondary">{{ form.recipient_roles.length ? form.recipient_roles.join(', ') : tx('defaultRecipients') }}</span></label>
            <div class="flex flex-col items-end justify-end gap-2"><p v-if="recipientCount !== null" class="text-sm text-secondary">{{ tx('recipientCount') }}: {{ recipientCount }}</p><button type="submit" :disabled="busy || loading || recipientCount === null || recipientCount === 0 || form.recipient_roles.length > 8" class="rounded-lg bg-primary px-4 py-2 text-sm font-semibold text-white disabled:opacity-50"><i class="las la-plus" aria-hidden="true" /> {{ tx('create') }}</button></div>
        </form>
        <section class="space-y-3">
            <div class="flex flex-wrap items-center justify-between gap-3">
                <label class="text-sm text-primary">{{ tx('status') }} <select v-model="status" class="rounded-lg border-border bg-inputBg" @change="currentPage = 1"><option value="">{{ tx('all') }}</option><option v-for="value in statuses" :key="value" :value="value">{{ tx(value) }}</option></select></label>
                <button type="button" :title="tx('refresh')" :aria-label="tx('refresh')" :disabled="loading" class="h-10 w-10 rounded-lg text-xl text-primary" @click="load()"><i class="las la-sync" aria-hidden="true" /></button>
            </div>
            <p v-if="loading" role="status" class="text-sm text-secondary">{{ tx('loading') }}</p>
            <p v-else-if="!jobs.length && !error" class="py-8 text-center text-secondary">{{ tx('empty') }}</p>
            <ul class="divide-y divide-border">
                <li v-for="job in jobs" :key="job.id" class="flex flex-wrap items-center gap-3 py-4">
                    <div class="min-w-0 flex-1"><button class="max-w-full break-words text-start font-semibold text-primary" @click="showDetails(job.id)">{{ job.title || tx(job.kind) }} #{{ job.id }}</button><p class="text-xs text-secondary">{{ tx(job.kind) }} · {{ tx('attempts') }}: {{ job.attempts }} · {{ date(job.scheduled_at || job.queued_at) }}</p><p v-if="job.error_code" class="text-sm text-red-600">{{ errorLabel(job.error_code) }}</p></div>
                    <span :class="job.status === 'failed' ? 'text-red-600' : 'text-primary'" class="text-sm font-semibold">{{ tx(job.status) }}</span>
                    <button v-if="job.can_retry" :disabled="busy" :title="tx('retry')" :aria-label="tx('retry')" class="h-10 w-10 text-xl text-primary" @click="retry(job)"><i class="las la-redo-alt" aria-hidden="true" /></button>
                    <button :title="tx('details')" :aria-label="tx('details')" class="h-10 w-10 text-xl text-primary" @click="showDetails(job.id)"><i class="las la-info-circle" aria-hidden="true" /></button>
                </li>
            </ul>
            <nav class="flex items-center justify-center gap-4" :aria-label="tx('page')"><button :disabled="currentPage <= 1 || loading" :aria-label="tx('previous')" :title="tx('previous')" class="h-10 w-10 text-xl disabled:opacity-30" @click="currentPage--"><i class="las la-angle-left" /></button><span class="text-sm">{{ currentPage }} / {{ meta.last_page }}</span><button :disabled="currentPage >= meta.last_page || loading" :aria-label="tx('next')" :title="tx('next')" class="h-10 w-10 text-xl disabled:opacity-30" @click="currentPage++"><i class="las la-angle-right" /></button></nav>
        </section>
        <dialog ref="dialog" class="max-h-[85vh] w-[calc(100%-2rem)] max-w-xl overflow-auto rounded-lg border border-border bg-card p-5 text-primary backdrop:bg-black/40" aria-labelledby="automation-detail-title">
            <template v-if="detail">
                <div class="flex items-start justify-between gap-3"><h2 id="automation-detail-title" class="break-words text-lg font-semibold">{{ detail.title || tx(detail.kind) }} #{{ detail.id }}</h2><button :aria-label="tx('close')" :title="tx('close')" class="h-8 w-8 shrink-0 text-xl" @click="dialog.close()"><i class="las la-times" /></button></div>
                <p class="my-3 text-sm font-semibold">{{ tx(detail.status) }} · {{ tx('attempts') }}: {{ detail.attempts }}</p>
                <p class="whitespace-pre-wrap break-words text-sm">{{ detail.body }}</p>
                <p v-if="detail.error_code" role="status" class="mt-3 text-sm text-red-600">{{ errorLabel(detail.error_code) }}</p>
                <dl class="my-4 space-y-2 text-sm"><div v-for="[field, key] in [['idempotency_key','key'],['scheduled_at','when'],['queued_at','created'],['started_at','started'],['completed_at','finished'],['failed_at','failedAt'],['retry_queued_at','retryAt']]" :key="field"><template v-if="detail[field]"><dt class="text-secondary">{{ tx(key) }}</dt><dd class="break-words">{{ field === 'idempotency_key' ? detail[field] : date(detail[field]) }}</dd></template></div></dl>
                <p class="break-words text-sm">{{ tx('recipients') }}: {{ detail.recipient_roles.length ? detail.recipient_roles.join(', ') : tx('defaultRecipients') }}</p>
                <template v-if="detail.result"><p class="mt-3 text-sm">{{ tx('stored') }}: {{ detail.result.stored_notifications }}</p><p class="text-sm">{{ tx('skipped') }}: {{ detail.result.skipped_notifications }}</p></template>
                <h3 class="mt-5 font-semibold">{{ tx('history') }}</h3><ol class="mt-2 space-y-2 text-sm"><li v-for="(event, index) in detail.history" :key="index">{{ tx(event.event.split('.').pop()) }} · {{ date(event.at) }}<span v-if="event.data.error_code" class="block text-red-600">{{ errorLabel(event.data.error_code) }}</span></li></ol>
                <button v-if="detail.can_retry" :disabled="busy" class="mt-4 text-sm font-semibold" @click="retry(detail)"><i class="las la-redo-alt" /> {{ tx('retry') }}</button>
            </template>
        </dialog>
    </main>
</template>
