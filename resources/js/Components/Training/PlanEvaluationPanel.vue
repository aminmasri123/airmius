<script setup>
import { computed, onMounted, onBeforeUnmount, ref } from 'vue'
import { usePage } from '@inertiajs/vue3'
import axios from 'axios'
import copy from '@/lang/planEvaluation.json'

const page = usePage()
const locale = computed(() => page.props.locale || 'de')
const t = (key) => (copy[locale.value] || copy.en)[key] || key
const data = ref(null)
const loading = ref(false)
const error = ref('')
const notice = ref('')
const planId = ref('')
const week = ref('')
const search = ref('')
const openOnly = ref(false)
const replies = ref({})
const sending = ref(null)
const replyErrors = ref({})
let requestId = 0
let debounce
onBeforeUnmount(() => { requestId++; clearTimeout(debounce) })
async function load(page = 1) {
    const id = ++requestId
    loading.value = true
    error.value = ''
    // Do not keep showing a previous athlete/plan after an access or network failure.
    data.value = null
    try {
        const response = await axios.get(route('auth.trainer-cockpit.plan-evaluation'), {
            params: { plan_id: planId.value || undefined, week: week.value || undefined, search: search.value, open_only: openOnly.value ? 1 : 0, page },
        })
        if (id !== requestId) return
        data.value = response.data.data
        planId.value = data.value.plan_id || ''
    } catch {
        if (id === requestId) error.value = t('error')
    } finally {
        if (id === requestId) loading.value = false
    }
}
function changePlan() { week.value = ''; replies.value = {}; load() }
function searchChanged() { clearTimeout(debounce); debounce = setTimeout(() => load(), 300) }
async function send(report) {
    if (sending.value !== null) return
    const body = (replies.value[report.id] || '').trim()
    if (body.length < 2) { replyErrors.value[report.id] = t('replyRequired'); return }
    sending.value = report.id
    replyErrors.value[report.id] = ''
    notice.value = ''
    const page = data.value.pagination.page
    try {
        await axios.post(route('auth.trainer-cockpit.plan-evaluation.feedback', report.id), { body })
        delete replies.value[report.id]
        notice.value = t('sent')
        await load(page)
    } catch (e) {
        replyErrors.value[report.id] = t('sendError')
        if ([401, 403, 404].includes(e.response?.status)) { requestId++; data.value = null; loading.value = false; error.value = t('error') }
    } finally { sending.value = null }
}
const date = (value) => value ? new Intl.DateTimeFormat(locale.value, { dateStyle: 'short', timeStyle: 'short' }).format(new Date(value)) : t('unscheduled')
const weekDate = (value) => value === 'unscheduled' ? t('unscheduled') : new Intl.DateTimeFormat(locale.value).format(new Date(`${value}T12:00:00`))
const number = (v) => new Intl.NumberFormat(locale.value, { maximumFractionDigits: 2 }).format(Number(v))
function values(set) {
    return [
        set.sets > 1 ? `${number(set.sets)} ×` : null,
        set.reps != null ? `${number(set.reps)} ${t('reps')}` : null,
        ...[['weight_kg', 'kg'], ['duration_minutes', 'min'], ['distance_km', 'km'], ['rounds', t('rounds')]].map(([key, unit]) => set[key] != null ? `${number(set[key])} ${unit}` : null),
        set.rest_seconds != null ? `${t('rest')} ${number(set.rest_seconds)} s` : null,
    ].filter(Boolean).join(' · ') || t('notRecorded')
}
onMounted(() => load())
</script>

<template>
    <section class="space-y-4" :aria-busy="loading">
        <div><h2 class="text-xl font-bold text-primary">{{ t('title') }}</h2><p class="mt-2 text-sm text-secondary">{{ t('intro') }}</p></div>
        <div class="surface-card grid gap-4 p-4 md:grid-cols-2">
            <label class="space-y-2 text-sm font-bold">{{ t('plan') }}
                <select v-model="planId" :disabled="loading" class="evaluation-input" @change="changePlan">
                    <option v-for="plan in data?.plans || []" :key="plan.id" :value="plan.id">{{ plan.title }}</option>
                </select>
            </label>
            <label class="space-y-2 text-sm font-bold">{{ t('week') }}
                <select v-model="week" :disabled="loading" class="evaluation-input" @change="load()">
                    <option value="">{{ t('allWeeks') }}</option><option v-for="w in data?.weeks || []" :key="w" :value="w">{{ weekDate(w) }}</option>
                </select>
            </label>
            <label class="space-y-2 text-sm font-bold">{{ t('search') }}<input v-model="search" type="search" maxlength="100" class="evaluation-input" @input="searchChanged" /></label>
            <div class="flex flex-wrap items-center gap-4"><label class="flex items-center gap-2 text-sm"><input v-model="openOnly" type="checkbox" @change="load()" />{{ t('openOnly') }}</label><button class="evaluation-button" :disabled="loading" @click="load()">{{ t('refresh') }}</button></div>
        </div>
        <p v-if="notice" role="status" class="text-sm text-buttonPrimary">{{ notice }}</p>
        <p v-if="loading" role="status">{{ t('loading') }}</p>
        <p v-if="error" role="alert" class="surface-card p-4 text-red-500">{{ error }}</p>
        <template v-if="data">
            <p v-if="!data.plans.length" class="surface-card p-5">{{ t('empty') }}</p>
            <template v-else>
                <div class="grid grid-cols-2 gap-3 lg:grid-cols-5"><div v-for="key in ['athletes','reported','missing','feedback_open','pain']" :key="key" class="surface-card p-4"><p class="text-2xl font-bold text-primary">{{ data.summary[key] || 0 }}</p><p class="mt-1 text-sm text-secondary">{{ t(key) }}</p></div></div>
                <p class="text-sm text-secondary">{{ t('missingHint') }} {{ t('openHint') }}</p>
                <p v-if="!data.rows.length" class="surface-card p-5">{{ t('noResults') }}</p>
                <details v-for="athlete in data.rows" :key="`${planId}-${athlete.id}`" class="surface-card overflow-hidden">
                    <summary class="cursor-pointer p-4"><strong class="text-lg">{{ athlete.name }}</strong><p class="mt-1 text-sm text-secondary">{{ athlete.teams.join(' · ') || t('direct') }}</p><div class="mt-3 flex flex-wrap gap-2 text-sm"><span class="evaluation-pill">{{ athlete.reported }}/{{ athlete.sessions.length }} {{ t('reported') }}</span><span v-if="athlete.feedback_open" class="evaluation-pill">{{ athlete.feedback_open }} {{ t('feedback_open') }}</span><span v-if="athlete.pain" class="evaluation-pill text-red-500">{{ t('pain') }}</span></div></summary>
                    <div class="space-y-5 border-t border-border p-4">
                        <article v-for="session in athlete.sessions" :key="session.id" class="space-y-3">
                            <div><h3 class="font-bold text-primary">{{ session.title }}</h3><p class="text-sm text-secondary">{{ date(session.scheduled_at) }}</p></div>
                            <p v-if="session.missing" class="text-sm text-secondary">{{ t(session.overdue ? 'overdue' : 'noReport') }}</p>
                            <div v-for="report in session.reports.length ? session.reports : [{ id: `missing-${session.id}`, exercises: session.planned }]" :key="report.id" class="space-y-3 rounded-xl border border-border p-3">
                                <template v-if="!session.missing"><p class="text-sm font-bold">{{ date(report.performed_at) }} · {{ t(report.status) }} · {{ t(report.feedback_open ? 'feedback_open' : 'reviewed') }}</p><p v-if="report.pain > 0" class="text-sm font-bold text-red-500">{{ t('pain') }}: {{ report.pain }}/10</p><p v-if="report.rpe != null" class="text-sm">{{ t('rpe') }}: {{ report.rpe }}/10</p><p v-if="report.duration_minutes != null || report.distance_meters != null" class="text-sm">{{ t('sessionTotal') }}: {{ values({ duration_minutes: report.duration_minutes, distance_km: report.distance_meters == null ? null : report.distance_meters / 1000 }) }}</p><p v-if="report.notes" class="whitespace-pre-wrap text-sm">{{ report.notes }}</p><p class="text-xs text-secondary">{{ t(report.snapshot ? 'snapshot' : 'legacy') }}</p></template>
                                <div v-for="(exercise, ei) in report.exercises" :key="ei" class="rounded-lg bg-inputBg p-3">
                                    <h4 class="font-bold">{{ exercise.title }} <span v-if="!exercise.planned.length" class="text-xs">· {{ t('extra') }}</span></h4><p v-if="exercise.notes" class="mt-1 whitespace-pre-wrap text-sm text-secondary">{{ exercise.notes }}</p><p v-if="exercise.unstructured" class="mt-1 text-sm text-secondary">{{ t('unstructured') }}</p>
                                    <div class="mt-3 grid gap-4 sm:grid-cols-2">
                                        <div><h5 class="text-xs font-bold uppercase text-buttonPrimary">{{ t('planned') }}</h5><p v-for="(set, si) in exercise.planned" :key="si" class="mt-2 text-sm">{{ t('set') }} {{ set.set_index || si + 1 }}: {{ values(set) }}</p><p v-if="!exercise.planned.length" class="text-sm">—</p></div>
                                        <div><h5 class="text-xs font-bold uppercase text-buttonPrimary">{{ t('actual') }}</h5><div v-for="(set, si) in exercise.actual" :key="si" class="mt-2 text-sm"><p>{{ t('set') }} {{ set.set_index || si + 1 }}: {{ set.completed === false ? t('notCompleted') : values(set) }}</p><p v-if="set.substituted_for">{{ t('substitution') }}: {{ set.title }}</p><p v-if="set.skip_reason || set.notes" class="whitespace-pre-wrap text-secondary">{{ set.skip_reason || set.notes }}</p></div><p v-if="!exercise.actual.length" class="mt-2 text-sm text-secondary">{{ t('notRecorded') }}</p></div>
                                    </div>
                                </div>
                                <template v-if="!session.missing">
                                    <h4 class="font-bold">{{ t('feedback') }}</h4><p v-if="report.trainer_feedback" class="whitespace-pre-wrap text-sm">{{ t('trainer') }}: {{ report.trainer_feedback }}</p><div v-for="feedback in report.feedback" :key="feedback.id" class="rounded-lg border border-border p-3"><p class="text-xs text-secondary">{{ feedback.author }} · {{ t(feedback.role) }} · {{ date(feedback.created_at) }}</p><p class="mt-1 whitespace-pre-wrap text-sm">{{ feedback.body }}</p></div>
                                    <form class="space-y-3" @submit.prevent="send(report)"><label class="block text-sm">{{ t('reply') }}<textarea v-model="replies[report.id]" class="evaluation-input mt-2" rows="3" minlength="2" maxlength="3000" required :disabled="sending === report.id" /></label><p v-if="replyErrors[report.id]" role="alert" class="text-sm text-red-500">{{ replyErrors[report.id] }}</p><button class="evaluation-button" :disabled="sending !== null">{{ t('send') }}</button></form>
                                </template>
                            </div>
                        </article>
                    </div>
                </details>
                <nav class="flex flex-wrap items-center justify-between gap-3" :aria-label="t('page')"><button class="evaluation-button" :disabled="data.pagination.page <= 1" @click="load(data.pagination.page - 1)">{{ t('previous') }}</button><span class="text-sm">{{ t('page') }} {{ data.pagination.page }}/{{ data.pagination.last_page }} · {{ data.pagination.total }} {{ t('total') }}</span><button class="evaluation-button" :disabled="data.pagination.page >= data.pagination.last_page" @click="load(data.pagination.page + 1)">{{ t('next') }}</button></nav>
            </template>
        </template>
    </section>
</template>

<style scoped>
.evaluation-input { @apply block w-full min-w-0 rounded-lg border border-border bg-inputBg p-3 text-primary; }
.evaluation-button { @apply min-h-11 rounded-lg border border-border px-4 py-2 font-bold text-buttonPrimary disabled:opacity-40; }
.evaluation-pill { @apply rounded-full border border-border px-3 py-1; }
</style>
