<script setup>
import AppLayout from '@/Components/Auth/Layouts/AppLayout.vue'
import ConfirmActionModal from '@/Components/ConfirmActionModal.vue'
import { Head, Link, router } from '@inertiajs/vue3'
import { computed, onBeforeUnmount, reactive, ref, watch } from 'vue'
import { useI18n } from 'vue-i18n'

defineOptions({ layout: AppLayout })

const props = defineProps({
    applications: { type: Object, default: () => ({ data: [], links: [] }) },
    jobs: { type: Array, default: () => [] },
    filters: { type: Object, default: () => ({}) },
    statuses: { type: Array, default: () => [] },
    stats: { type: Object, default: () => ({}) },
    retention_months: { type: Number, default: 6 },
})

const { t, locale } = useI18n()
const query = ref(props.filters.q || '')
const selectedStatus = ref(props.filters.status || '')
const selectedJob = ref(props.filters.job_id || '')
const drafts = reactive({})
const savingIds = reactive(new Set())
const deleteTarget = ref(null)
const deleting = ref(false)
let searchTimer = null

const applicationItems = computed(() => props.applications?.data || [])
const statusOptions = computed(() => ['', ...props.statuses])
const dateLocale = computed(() => ({ de: 'de-DE', en: 'en-US', fr: 'fr-FR', ar: 'ar' }[locale.value] || locale.value))

const statusLabel = (status) => t(`recruiting_pipeline.status.${status || 'all'}`)
const statusTone = (status) => ({
    new: 'border-sky-400/40 bg-sky-500/10 text-sky-300',
    reviewing: 'border-violet-400/40 bg-violet-500/10 text-violet-300',
    contacted: 'border-cyan-400/40 bg-cyan-500/10 text-cyan-300',
    interview: 'border-amber-400/40 bg-amber-500/10 text-amber-300',
    offered: 'border-emerald-400/40 bg-emerald-500/10 text-emerald-300',
    hired: 'border-air-green/40 bg-air-green/10 text-air-green',
    rejected: 'border-rose-400/40 bg-rose-500/10 text-rose-300',
}[status] || 'border-border bg-inputBg text-secondary')

const formatDate = (value) => value
    ? new Intl.DateTimeFormat(dateLocale.value, { dateStyle: 'medium', timeStyle: 'short' }).format(new Date(value))
    : t('recruiting_pipeline.not_available')

const draftFor = (application) => {
    drafts[application.id] ??= {
        status: application.status || 'new',
        internal_note: application.internal_note || '',
    }

    return drafts[application.id]
}

const reload = ({ resetPage = true } = {}) => {
    router.get(route('auth.recruiting-pipeline.index'), {
        q: query.value || undefined,
        status: selectedStatus.value || undefined,
        job_id: selectedJob.value || undefined,
        page: resetPage ? undefined : props.applications?.current_page,
    }, {
        only: ['applications', 'jobs', 'filters', 'statuses', 'stats', 'retention_months'],
        preserveState: true,
        preserveScroll: true,
        replace: true,
    })
}

watch(query, () => {
    clearTimeout(searchTimer)
    searchTimer = setTimeout(() => reload(), 350)
})
watch([selectedStatus, selectedJob], () => reload())
onBeforeUnmount(() => clearTimeout(searchTimer))

const save = (application) => {
    if (savingIds.has(application.id)) return
    savingIds.add(application.id)
    router.put(route('auth.recruiting-pipeline.applications.update', application.id), draftFor(application), {
        only: ['applications', 'stats'],
        preserveScroll: true,
        onFinish: () => savingIds.delete(application.id),
    })
}

const confirmErase = () => {
    if (!deleteTarget.value || deleting.value) return
    deleting.value = true
    router.delete(route('auth.recruiting-pipeline.applications.destroy', deleteTarget.value.id), {
        only: ['applications', 'stats', 'jobs'],
        preserveScroll: true,
        onSuccess: () => { deleteTarget.value = null },
        onFinish: () => { deleting.value = false },
    })
}

const openPage = (link) => {
    if (!link?.url) return
    router.visit(link.url, {
        only: ['applications', 'stats'],
        preserveState: true,
        preserveScroll: true,
    })
}
</script>

<template>
    <Head :title="t('recruiting_pipeline.page_title')" />

    <div class="space-y-5">
        <section class="surface-card overflow-hidden">
            <div class="grid gap-5 p-5 lg:grid-cols-[1fr_auto] lg:items-end">
                <div>
                    <p class="text-xs font-bold uppercase tracking-wide text-air-green">{{ t('recruiting_pipeline.eyebrow') }}</p>
                    <h1 class="mt-2 text-2xl font-bold text-primary md:text-3xl">{{ t('recruiting_pipeline.title') }}</h1>
                    <p class="mt-2 max-w-3xl text-sm leading-6 text-secondary">{{ t('recruiting_pipeline.intro') }}</p>
                </div>
                <Link :href="route('auth.teams.index')" class="inline-flex min-h-11 items-center justify-center rounded-lg border border-border px-4 py-2 text-sm font-bold text-primary hover:border-borderHover">
                    {{ t('recruiting_pipeline.manage_jobs') }}
                </Link>
            </div>
            <div class="border-t border-border bg-inputBg/40 px-5 py-3 text-xs leading-5 text-secondary">
                {{ t('recruiting_pipeline.retention_notice', { months: retention_months }) }}
            </div>
        </section>

        <section class="grid gap-3 sm:grid-cols-2 xl:grid-cols-4">
            <article class="surface-card p-4">
                <p class="text-xs font-bold uppercase text-secondary">{{ t('recruiting_pipeline.stats.total') }}</p>
                <p class="mt-2 text-2xl font-bold text-primary">{{ stats.total || 0 }}</p>
            </article>
            <article class="surface-card p-4">
                <p class="text-xs font-bold uppercase text-secondary">{{ t('recruiting_pipeline.stats.new') }}</p>
                <p class="mt-2 text-2xl font-bold text-sky-300">{{ stats.new || 0 }}</p>
            </article>
            <article class="surface-card p-4">
                <p class="text-xs font-bold uppercase text-secondary">{{ t('recruiting_pipeline.stats.in_progress') }}</p>
                <p class="mt-2 text-2xl font-bold text-amber-300">{{ (stats.reviewing || 0) + (stats.contacted || 0) + (stats.interview || 0) + (stats.offered || 0) }}</p>
            </article>
            <article class="surface-card p-4">
                <p class="text-xs font-bold uppercase text-secondary">{{ t('recruiting_pipeline.stats.hired') }}</p>
                <p class="mt-2 text-2xl font-bold text-air-green">{{ stats.hired || 0 }}</p>
            </article>
        </section>

        <section class="surface-card p-4">
            <div class="grid gap-3 md:grid-cols-[minmax(0,1fr)_minmax(180px,.45fr)_minmax(220px,.6fr)]">
                <label>
                    <span class="text-xs font-bold uppercase text-secondary">{{ t('recruiting_pipeline.filters.search') }}</span>
                    <input v-model="query" type="search" class="mt-1 w-full rounded-lg border-border bg-inputBg text-primary" :placeholder="t('recruiting_pipeline.filters.search_placeholder')">
                </label>
                <label>
                    <span class="text-xs font-bold uppercase text-secondary">{{ t('recruiting_pipeline.filters.status') }}</span>
                    <select v-model="selectedStatus" class="mt-1 w-full rounded-lg border-border bg-inputBg text-primary">
                        <option v-for="status in statusOptions" :key="status || 'all'" :value="status">{{ statusLabel(status) }}</option>
                    </select>
                </label>
                <label>
                    <span class="text-xs font-bold uppercase text-secondary">{{ t('recruiting_pipeline.filters.job') }}</span>
                    <select v-model="selectedJob" class="mt-1 w-full rounded-lg border-border bg-inputBg text-primary">
                        <option value="">{{ t('recruiting_pipeline.filters.all_jobs') }}</option>
                        <option v-for="job in jobs" :key="job.id" :value="job.id">
                            {{ job.club?.name }} · {{ job.title }} ({{ job.applications_count }})
                        </option>
                    </select>
                </label>
            </div>
        </section>

        <section v-if="applicationItems.length" class="grid gap-4 xl:grid-cols-2">
            <article v-for="application in applicationItems" :key="application.id" class="surface-card flex flex-col p-5">
                <div class="flex flex-wrap items-start justify-between gap-3">
                    <div class="min-w-0">
                        <p class="truncate text-lg font-bold text-primary">{{ application.name }}</p>
                        <p class="mt-1 text-xs text-secondary">{{ application.job?.club?.name }} · {{ application.job?.title }}</p>
                    </div>
                    <span class="rounded-full border px-2.5 py-1 text-xs font-bold" :class="statusTone(application.status)">{{ statusLabel(application.status) }}</span>
                </div>

                <div class="mt-4 grid gap-2 text-sm sm:grid-cols-2">
                    <a :href="`mailto:${application.email}`" class="truncate rounded-lg border border-border bg-inputBg/50 px-3 py-2 text-buttonPrimary">{{ application.email }}</a>
                    <a v-if="application.phone" :href="`tel:${application.phone}`" class="truncate rounded-lg border border-border bg-inputBg/50 px-3 py-2 text-buttonPrimary">{{ application.phone }}</a>
                    <p v-else class="rounded-lg border border-border bg-inputBg/50 px-3 py-2 text-secondary">{{ t('recruiting_pipeline.no_phone') }}</p>
                </div>
                <p class="mt-3 whitespace-pre-line rounded-lg border border-border bg-inputBg/40 p-3 text-sm leading-6 text-secondary">
                    {{ application.message || t('recruiting_pipeline.no_message') }}
                </p>
                <p class="mt-2 text-xs text-secondary">{{ t('recruiting_pipeline.submitted_at', { date: formatDate(application.submitted_at) }) }}</p>

                <div class="mt-4 grid gap-3 sm:grid-cols-[180px_1fr]">
                    <label>
                        <span class="text-xs font-bold uppercase text-secondary">{{ t('recruiting_pipeline.field_status') }}</span>
                        <select v-model="draftFor(application).status" class="mt-1 w-full rounded-lg border-border bg-inputBg text-primary">
                            <option v-for="status in statuses" :key="status" :value="status">{{ statusLabel(status) }}</option>
                        </select>
                    </label>
                    <label>
                        <span class="text-xs font-bold uppercase text-secondary">{{ t('recruiting_pipeline.field_note') }}</span>
                        <textarea v-model="draftFor(application).internal_note" rows="2" maxlength="2000" class="mt-1 w-full rounded-lg border-border bg-inputBg text-primary" :placeholder="t('recruiting_pipeline.note_placeholder')"></textarea>
                    </label>
                </div>

                <div class="mt-auto flex flex-col-reverse gap-2 pt-4 sm:flex-row sm:justify-between">
                    <button type="button" class="rounded-lg border border-error/40 px-3 py-2 text-sm font-bold text-error hover:bg-error/10" @click="deleteTarget = application">
                        {{ t('recruiting_pipeline.erase') }}
                    </button>
                    <button type="button" class="rounded-lg bg-buttonPrimary px-4 py-2 text-sm font-bold text-buttonTextPrimary disabled:opacity-60" :disabled="savingIds.has(application.id)" @click="save(application)">
                        {{ savingIds.has(application.id) ? t('recruiting_pipeline.saving') : t('recruiting_pipeline.save') }}
                    </button>
                </div>
            </article>
        </section>

        <section v-else class="surface-card p-10 text-center">
            <span class="mx-auto flex h-14 w-14 items-center justify-center rounded-full bg-inputBg text-air-green"><i class="las la-user-check text-2xl"></i></span>
            <h2 class="mt-4 text-xl font-bold text-primary">{{ t('recruiting_pipeline.empty_title') }}</h2>
            <p class="mt-2 text-sm text-secondary">{{ t('recruiting_pipeline.empty_body') }}</p>
        </section>

        <nav v-if="applicationItems.length && applications.links?.length" class="surface-card flex flex-wrap justify-center gap-2 p-3" :aria-label="t('recruiting_pipeline.pagination')">
            <button v-for="link in applications.links" :key="link.label" type="button" class="min-h-10 rounded-lg border px-3 py-2 text-sm" :class="link.active ? 'border-buttonPrimary bg-buttonPrimary text-buttonTextPrimary' : 'border-border text-primary disabled:opacity-40'" :disabled="!link.url" @click="openPage(link)" v-html="link.label"></button>
        </nav>
    </div>

    <ConfirmActionModal
        :show="Boolean(deleteTarget)"
        :title="t('recruiting_pipeline.erase_title')"
        :message="t('recruiting_pipeline.erase_message', { name: deleteTarget?.name || '' })"
        :confirm-label="t('recruiting_pipeline.erase_confirm')"
        :cancel-label="t('recruiting_pipeline.cancel')"
        :processing="deleting"
        tone="danger"
        @close="deleteTarget = null"
        @confirm="confirmErase"
    />
</template>
