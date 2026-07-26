<script setup>
import AppLayout from '@/Components/Auth/Layouts/AppLayout.vue'
import { Head, Link, router, useForm } from '@inertiajs/vue3'
import { computed, reactive } from 'vue'
import { useI18n } from 'vue-i18n'

defineOptions({ layout: AppLayout })

const { t, te, locale } = useI18n({ useScope: 'global' })
const localeCode = computed(() => String(locale.value || 'de').replace('_', '-'))
const tx = (key, fallback, params) => te(key) ? t(key, params) : fallback
const formatNumber = (value) => new Intl.NumberFormat(localeCode.value).format(Number(value || 0))
const formatDate = (value) => {
    if (!value) return '-'

    const date = new Date(value)
    if (Number.isNaN(date.getTime())) return value

    return new Intl.DateTimeFormat(localeCode.value, { dateStyle: 'medium', timeStyle: 'short' }).format(date)
}
const categoryLabel = (category) => tx(`mail_center.categories.${category}`, category)
const paginationLabel = (label) => String(label || '')
    .replace(/<[^>]*>/g, '')
    .replace(/&laquo;/g, '«')
    .replace(/&raquo;/g, '»')
    .replace(/&amp;/g, '&')

const props = defineProps({
    deliveries: {
        type: Object,
        required: true,
    },
    summary: {
        type: Object,
        default: () => ({}),
    },
    queue: {
        type: Object,
        default: () => ({}),
    },
    senders: {
        type: Array,
        default: () => [],
    },
    preferences: {
        type: Object,
        default: () => ({}),
    },
    canManageSecrets: {
        type: Boolean,
        default: false,
    },
    audits: {
        type: Array,
        default: () => [],
    },
    categories: {
        type: Array,
        default: () => [],
    },
    types: {
        type: Array,
        default: () => [],
    },
    filters: {
        type: Object,
        default: () => ({}),
    },
})

const filterForm = useForm({
    status: props.filters.status || '',
    type: props.filters.type || '',
})

const preferenceForm = useForm({
    invoice_primary_category: props.preferences.invoice_primary_category || 'system',
    invoice_fallback_category: props.preferences.invoice_fallback_category || 'billing',
    disabled_categories: props.preferences.disabled_categories || [],
})

const deliveryRows = computed(() => props.deliveries.data || [])
const recentFailedJobs = computed(() => props.queue.recent_failed_jobs || [])
const resendCategories = reactive({})
const senderForms = reactive(Object.fromEntries(props.senders.map((sender) => [
    sender.category,
    {
        from_address: sender.address || '',
        from_name: sender.name || '',
        host: sender.host || '',
        port: sender.port || '',
        username: sender.username || '',
        scheme: sender.scheme || '',
        active: !sender.disabled,
        new_password: '',
        processing: false,
    },
])))

const applyFilters = () => {
    router.get(route('admin.mail-center.index'), {
        status: filterForm.status || undefined,
        type: filterForm.type || undefined,
    }, {
        preserveState: true,
        preserveScroll: true,
    })
}

const savePreferences = () => {
    preferenceForm.put(route('admin.mail-center.preferences.update'), {
        preserveScroll: true,
    })
}

const toggleDisabledCategory = (category, checked) => {
    const current = new Set(preferenceForm.disabled_categories || [])

    if (checked) {
        current.add(category)
    } else {
        current.delete(category)
    }

    current.delete('system')
    preferenceForm.disabled_categories = Array.from(current)
}

const resendDelivery = (delivery) => {
    const category = resendCategories[delivery.id] || delivery.fallback_category || delivery.primary_category || 'billing'

    router.post(route('admin.mail-center.resend', delivery.id), { category }, {
        preserveScroll: true,
    })
}

const resolveDelivery = (delivery) => {
    router.put(route('admin.mail-center.resolve', delivery.id), {}, {
        preserveScroll: true,
    })
}

const saveSender = (sender) => {
    const form = senderForms[sender.category]
    form.processing = true

    router.put(route('admin.mail-center.senders.update', sender.category), {
        from_address: form.from_address,
        from_name: form.from_name,
        host: form.host,
        port: form.port,
        username: form.username,
        scheme: form.scheme,
        active: Boolean(form.active),
        new_password: form.new_password,
    }, {
        preserveScroll: true,
        onFinish: () => {
            form.processing = false
            form.new_password = ''
        },
    })
}

const testSender = (sender) => {
    router.post(route('admin.mail-center.senders.test', sender.category), {}, {
        preserveScroll: true,
    })
}

const statusLabel = (status) => ({
    sent: t('mail_center.status.sent'),
    failed: t('mail_center.status.failed'),
    skipped: t('mail_center.status.skipped'),
    resolved: t('mail_center.status.resolved'),
}[status] || status || '-')

const statusClass = (status) => ({
    sent: 'border-success/30 bg-success/10 text-success',
    failed: 'border-error/30 bg-error/10 text-error',
    skipped: 'border-warning/30 bg-warning/10 text-warning',
    resolved: 'border-info/30 bg-info/10 text-info',
}[status] || 'border-border bg-muted text-secondary')

const readyLabel = (sender) => {
    if (sender.resolved?.mailer === 'log') {
        return t('mail_center.sender.local_log')
    }

    return sender.ready ? t('mail_center.sender.ready') : t('mail_center.sender.incomplete')
}
</script>

<template>
    <Head :title="t('mail_center.page_title')" />

    <div class="space-y-5">
        <section class="surface-card p-5">
            <div class="flex flex-col gap-4 lg:flex-row lg:items-end lg:justify-between">
                <div>
                    <p class="text-xs font-semibold uppercase tracking-wide text-air-blue">{{ t('mail_center.eyebrow') }}</p>
                    <h1 class="mt-1 text-2xl font-bold text-primary">{{ t('mail_center.title') }}</h1>
                    <p class="mt-2 max-w-3xl text-sm text-secondary">
                        {{ t('mail_center.intro') }}
                    </p>
                </div>
                <div class="rounded-lg border border-border bg-bg px-4 py-3 text-sm text-secondary">
                    {{ t('mail_center.local_test_notice') }}
                </div>
            </div>
        </section>

        <section class="grid gap-4 md:grid-cols-2 xl:grid-cols-5">
            <div class="surface-card p-4">
                <p class="text-xs font-semibold uppercase text-secondary">{{ t('mail_center.summary.total') }}</p>
                <p class="mt-2 text-2xl font-bold text-primary">{{ formatNumber(summary.total) }}</p>
            </div>
            <div class="surface-card p-4">
                <p class="text-xs font-semibold uppercase text-secondary">{{ t('mail_center.summary.sent') }}</p>
                <p class="mt-2 text-2xl font-bold text-success">{{ formatNumber(summary.sent) }}</p>
            </div>
            <div class="surface-card p-4">
                <p class="text-xs font-semibold uppercase text-secondary">{{ t('mail_center.summary.failed') }}</p>
                <p class="mt-2 text-2xl font-bold text-error">{{ formatNumber(summary.failed) }}</p>
            </div>
            <div class="surface-card p-4">
                <p class="text-xs font-semibold uppercase text-secondary">{{ t('mail_center.summary.skipped') }}</p>
                <p class="mt-2 text-2xl font-bold text-warning">{{ formatNumber(summary.skipped) }}</p>
            </div>
            <div class="surface-card p-4">
                <p class="text-xs font-semibold uppercase text-secondary">{{ t('mail_center.summary.resolved') }}</p>
                <p class="mt-2 text-2xl font-bold text-info">{{ formatNumber(summary.resolved) }}</p>
            </div>
        </section>

        <section class="grid gap-5 xl:grid-cols-[1.1fr_0.9fr]">
            <div class="surface-card p-5">
                <div class="flex flex-col gap-4 lg:flex-row lg:items-end lg:justify-between">
                    <div>
                        <h2 class="text-lg font-semibold text-primary">{{ t('mail_center.sender_rules.title') }}</h2>
                        <p class="mt-1 text-sm text-secondary">
                            {{ t('mail_center.sender_rules.hint') }}
                        </p>
                    </div>
                    <form class="grid gap-3 sm:grid-cols-[1fr_1fr_auto]" @submit.prevent="savePreferences">
                        <label class="text-sm font-semibold text-primary">
                            {{ t('mail_center.sender_rules.primary') }}
                            <select v-model="preferenceForm.invoice_primary_category" class="mt-1 w-full rounded-lg border-border bg-inputBg text-primary">
                                <option v-for="category in categories" :key="category" :value="category">{{ categoryLabel(category) }}</option>
                            </select>
                        </label>
                        <label class="text-sm font-semibold text-primary">
                            Ersatz
                            <select v-model="preferenceForm.invoice_fallback_category" class="mt-1 w-full rounded-lg border-border bg-inputBg text-primary">
                                <option value="">{{ t('mail_center.sender_rules.no_fallback') }}</option>
                                <option v-for="category in categories" :key="category" :value="category">{{ categoryLabel(category) }}</option>
                            </select>
                        </label>
                        <button
                            type="submit"
                            class="self-end rounded-lg border border-buttonPrimary bg-buttonPrimary px-4 py-2 text-sm font-semibold text-buttonTextPrimary transition hover:brightness-110 disabled:cursor-not-allowed disabled:border-border disabled:bg-muted disabled:text-secondary disabled:hover:brightness-100"
                            :disabled="preferenceForm.processing"
                        >
                            {{ t('mail_center.actions.save') }}
                        </button>
                    </form>
                </div>

                <div class="mt-5 grid gap-3 md:grid-cols-2">
                    <div v-for="sender in senders" :key="sender.category" class="rounded-lg border border-border bg-bg p-4">
                        <div class="flex items-start justify-between gap-3">
                            <div>
                                <p class="text-sm font-semibold text-primary">{{ categoryLabel(sender.category) }}</p>
                                <p class="mt-1 text-xs text-secondary">{{ sender.address }}</p>
                            </div>
                            <span class="rounded-full border px-2 py-1 text-xs font-semibold" :class="sender.ready || sender.resolved?.mailer === 'log' ? 'border-success/30 bg-success/10 text-success' : 'border-warning/30 bg-warning/10 text-warning'">
                                {{ sender.disabled ? t('mail_center.sender.disabled') : readyLabel(sender) }}
                            </span>
                        </div>
                        <p class="mt-3 text-xs text-secondary">
                            {{ t('mail_center.sender.mailer') }} {{ sender.resolved?.mailer || sender.mailer || '-' }}
                        </p>
                        <div v-if="canManageSecrets" class="mt-4 space-y-3 border-t border-border pt-4">
                            <div class="grid gap-3 sm:grid-cols-2">
                                <label class="text-xs font-semibold text-secondary">
                                    {{ t('mail_center.fields.from_address') }}
                                    <input v-model="senderForms[sender.category].from_address" type="email" class="mt-1 w-full rounded-lg border-border bg-inputBg text-sm text-primary">
                                </label>
                                <label class="text-xs font-semibold text-secondary">
                                    {{ t('mail_center.fields.from_name') }}
                                    <input v-model="senderForms[sender.category].from_name" type="text" class="mt-1 w-full rounded-lg border-border bg-inputBg text-sm text-primary">
                                </label>
                                <label class="text-xs font-semibold text-secondary">
                                    {{ t('mail_center.fields.smtp_host') }}
                                    <input v-model="senderForms[sender.category].host" type="text" class="mt-1 w-full rounded-lg border-border bg-inputBg text-sm text-primary">
                                </label>
                                <label class="text-xs font-semibold text-secondary">
                                    Port
                                    <input v-model="senderForms[sender.category].port" type="number" min="1" max="65535" class="mt-1 w-full rounded-lg border-border bg-inputBg text-sm text-primary">
                                </label>
                                <label class="text-xs font-semibold text-secondary">
                                    {{ t('mail_center.fields.smtp_user') }}
                                    <input v-model="senderForms[sender.category].username" type="email" class="mt-1 w-full rounded-lg border-border bg-inputBg text-sm text-primary">
                                </label>
                                <label class="text-xs font-semibold text-secondary">
                                    {{ t('mail_center.fields.encryption') }}
                                    <select v-model="senderForms[sender.category].scheme" class="mt-1 w-full rounded-lg border-border bg-inputBg text-sm text-primary">
                                        <option value="">{{ t('mail_center.fields.default') }}</option>
                                        <option value="smtp">smtp</option>
                                        <option value="smtps">smtps</option>
                                    </select>
                                </label>
                            </div>

                            <label class="block text-xs font-semibold text-secondary">
                                {{ t('mail_center.fields.new_password') }}
                                <input
                                    v-model="senderForms[sender.category].new_password"
                                    type="password"
                                    autocomplete="new-password"
                                    :placeholder="t('mail_center.fields.password_placeholder')"
                                    class="mt-1 w-full rounded-lg border-border bg-inputBg text-sm text-primary"
                                >
                            </label>

                            <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                                <label class="inline-flex items-center gap-2 text-xs font-semibold text-secondary">
                                    <input v-model="senderForms[sender.category].active" type="checkbox" class="h-4 w-4 rounded border-border bg-inputBg text-buttonPrimary accent-buttonPrimary">
                                    {{ t('mail_center.sender.active') }}
                                </label>
                                <div class="flex gap-2">
                                    <button type="button" class="rounded-lg border border-border bg-inputBg px-3 py-2 text-xs font-semibold text-primary transition hover:bg-muted" @click="testSender(sender)">
                                        {{ t('mail_center.actions.test_mail') }}
                                    </button>
                                    <button
                                        type="button"
                                        class="rounded-lg border border-buttonPrimary bg-buttonPrimary px-3 py-2 text-xs font-semibold text-buttonTextPrimary transition hover:brightness-110 disabled:cursor-not-allowed disabled:border-border disabled:bg-muted disabled:text-secondary disabled:hover:brightness-100"
                                        :disabled="senderForms[sender.category].processing"
                                        @click="saveSender(sender)"
                                    >
                                        {{ t('mail_center.actions.save') }}
                                    </button>
                                </div>
                            </div>

                            <p class="text-xs text-secondary">
                                {{ t('mail_center.sender.password_status', { status: sender.has_password ? t('mail_center.sender.password_set') : t('mail_center.sender.password_unset') }) }}<span v-if="sender.password_updated_at"> · {{ t('mail_center.sender.last_updated', { date: formatDate(sender.password_updated_at) }) }}</span>. {{ t('mail_center.sender.never_shown') }}
                            </p>
                        </div>
                    </div>
                </div>
            </div>

            <div class="surface-card p-5">
                <h2 class="text-lg font-semibold text-primary">{{ t('mail_center.queue.title') }}</h2>
                <div class="mt-4 grid gap-3 sm:grid-cols-2">
                    <div class="rounded-lg border border-border bg-bg p-4">
                        <p class="text-xs font-semibold uppercase text-secondary">{{ t('mail_center.queue.pending') }}</p>
                        <p class="mt-2 text-2xl font-bold text-primary">{{ formatNumber(queue.pending_jobs) }}</p>
                    </div>
                    <div class="rounded-lg border border-border bg-bg p-4">
                        <p class="text-xs font-semibold uppercase text-secondary">{{ t('mail_center.queue.failed') }}</p>
                        <p class="mt-2 text-2xl font-bold text-error">{{ formatNumber(queue.failed_jobs) }}</p>
                    </div>
                </div>
                <div class="mt-4 space-y-3">
                    <div v-if="!recentFailedJobs.length" class="rounded-lg border border-border bg-bg p-4 text-sm text-secondary">
                        {{ t('mail_center.queue.empty') }}
                    </div>
                    <div v-for="job in recentFailedJobs" :key="job.id" class="rounded-lg border border-error/20 bg-error/5 p-4">
                        <div class="flex items-center justify-between gap-3 text-xs text-secondary">
                            <span>{{ t('mail_center.queue.job') }} #{{ job.id }} · {{ job.queue }}</span>
                            <span>{{ formatDate(job.failed_at) }}</span>
                        </div>
                        <p class="mt-2 text-sm text-error">{{ job.error }}</p>
                    </div>
                </div>
            </div>
        </section>

        <section v-if="canManageSecrets" class="surface-card overflow-hidden">
            <div class="border-b border-border p-5">
                <h2 class="text-lg font-semibold text-primary">{{ t('mail_center.audit.title') }}</h2>
                <p class="mt-1 text-sm text-secondary">
                    {{ t('mail_center.audit.hint') }}
                </p>
            </div>
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-border text-sm">
                    <thead class="bg-bg">
                        <tr class="text-left text-xs uppercase tracking-wide text-secondary">
                            <th class="px-5 py-3">{{ t('mail_center.table.time') }}</th>
                            <th class="px-5 py-3">{{ t('mail_center.table.category') }}</th>
                            <th class="px-5 py-3">{{ t('mail_center.table.action') }}</th>
                            <th class="px-5 py-3">{{ t('mail_center.table.admin') }}</th>
                            <th class="px-5 py-3">{{ t('mail_center.table.details') }}</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-border">
                        <tr v-if="!audits.length">
                            <td colspan="5" class="px-5 py-8 text-center text-secondary">{{ t('mail_center.audit.empty') }}</td>
                        </tr>
                        <tr v-for="audit in audits" :key="audit.id">
                            <td class="px-5 py-4 text-secondary">{{ formatDate(audit.created_at) }}</td>
                            <td class="px-5 py-4 text-primary">{{ categoryLabel(audit.category) }}</td>
                            <td class="px-5 py-4 text-primary">{{ audit.action }}</td>
                            <td class="px-5 py-4 text-secondary">#{{ audit.actor_id || '-' }}</td>
                            <td class="px-5 py-4 text-xs text-secondary">
                                <span v-if="audit.after?.password_changed">{{ t('mail_center.audit.password_changed') }} </span>
                                <span v-if="audit.after?.from_address">{{ t('mail_center.audit.sender') }} {{ audit.after.from_address }}</span>
                                <span v-else-if="audit.after?.error">{{ t('mail_center.audit.error') }} {{ audit.after.error }}</span>
                                <span v-else>-</span>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </section>

        <section class="surface-card overflow-hidden">
            <div class="border-b border-border p-5">
                <div class="flex flex-col gap-4 lg:flex-row lg:items-end lg:justify-between">
                    <div>
                        <h2 class="text-lg font-semibold text-primary">{{ t('mail_center.log.title') }}</h2>
                        <p class="mt-1 text-sm text-secondary">
                            {{ t('mail_center.log.hint') }}
                        </p>
                    </div>
                    <form class="grid gap-3 sm:grid-cols-[12rem_16rem_auto]" @submit.prevent="applyFilters">
                        <select v-model="filterForm.status" class="rounded-lg border-border bg-inputBg text-primary">
                            <option value="">{{ t('mail_center.filters.all_statuses') }}</option>
                            <option value="sent">{{ t('mail_center.status.sent') }}</option>
                            <option value="failed">{{ t('mail_center.status.failed') }}</option>
                            <option value="skipped">{{ t('mail_center.status.skipped') }}</option>
                            <option value="resolved">{{ t('mail_center.status.resolved') }}</option>
                        </select>
                        <select v-model="filterForm.type" class="rounded-lg border-border bg-inputBg text-primary">
                            <option value="">{{ t('mail_center.filters.all_types') }}</option>
                            <option v-for="type in types" :key="type" :value="type">{{ type }}</option>
                        </select>
                        <button type="submit" class="rounded-lg border border-border px-4 py-2 text-sm font-semibold text-primary hover:bg-muted">
                            {{ t('mail_center.actions.filter') }}
                        </button>
                    </form>
                </div>
            </div>

            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-border text-sm">
                    <thead class="bg-bg">
                        <tr class="text-left text-xs uppercase tracking-wide text-secondary">
                            <th class="px-5 py-3">{{ t('mail_center.table.status') }}</th>
                            <th class="px-5 py-3">{{ t('mail_center.table.type') }}</th>
                            <th class="px-5 py-3">{{ t('mail_center.table.recipient') }}</th>
                            <th class="px-5 py-3">{{ t('mail_center.table.sender') }}</th>
                            <th class="px-5 py-3">{{ t('mail_center.table.time') }}</th>
                            <th class="px-5 py-3">{{ t('mail_center.table.error') }}</th>
                            <th class="px-5 py-3">{{ t('mail_center.table.actions') }}</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-border">
                        <tr v-if="!deliveryRows.length">
                            <td colspan="7" class="px-5 py-10 text-center text-secondary">
                                {{ t('mail_center.log.empty') }}
                            </td>
                        </tr>
                        <tr v-for="delivery in deliveryRows" :key="delivery.id" class="align-top">
                            <td class="px-5 py-4">
                                <span class="rounded-full border px-2 py-1 text-xs font-semibold" :class="statusClass(delivery.status)">
                                    {{ statusLabel(delivery.status) }}
                                </span>
                            </td>
                            <td class="px-5 py-4 text-primary">{{ delivery.mail_type }}</td>
                            <td class="px-5 py-4">
                                <p class="font-semibold text-primary">{{ delivery.recipient.name || '-' }}</p>
                                <p class="text-xs text-secondary">{{ delivery.recipient.email || '-' }}</p>
                            </td>
                            <td class="px-5 py-4">
                                <p class="text-primary">{{ delivery.from_address || '-' }}</p>
                                <p class="text-xs text-secondary">{{ delivery.used_category || delivery.primary_category || '-' }} · {{ delivery.mailer || '-' }}</p>
                            </td>
                            <td class="px-5 py-4 text-secondary">{{ formatDate(delivery.sent_at || delivery.created_at) }}</td>
                            <td class="max-w-md px-5 py-4 text-xs text-secondary">
                                {{ delivery.error_message || '-' }}
                            </td>
                            <td class="min-w-64 px-5 py-4">
                                <div v-if="delivery.status !== 'sent'" class="flex flex-col gap-2">
                                    <div v-if="delivery.resendable" class="flex gap-2">
                                        <select v-model="resendCategories[delivery.id]" class="min-w-32 rounded-lg border-border bg-inputBg text-xs text-primary">
                                            <option v-for="category in categories" :key="category" :value="category">
                                                {{ categoryLabel(category) }}
                                            </option>
                                        </select>
                                        <button type="button" class="rounded-lg bg-buttonPrimary px-3 py-2 text-xs font-semibold text-buttonTextPrimary" @click="resendDelivery(delivery)">
                                            {{ t('mail_center.actions.resend') }}
                                        </button>
                                    </div>
                                    <button type="button" class="w-fit rounded-lg border border-border px-3 py-2 text-xs font-semibold text-primary hover:bg-muted" @click="resolveDelivery(delivery)">
                                        {{ t('mail_center.actions.resolve') }}
                                    </button>
                                </div>
                                <span v-else class="text-xs text-secondary">-</span>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <div v-if="deliveries.links?.length" class="flex flex-wrap gap-2 border-t border-border p-4">
                <Link
                    v-for="link in deliveries.links"
                    :key="link.label"
                    :href="link.url || ''"
                    class="rounded-lg border px-3 py-2 text-sm"
                    :class="link.active ? 'border-buttonPrimary bg-buttonPrimary text-buttonTextPrimary' : 'border-border text-primary hover:bg-muted'"
                >
                    {{ paginationLabel(link.label) }}
                </Link>
            </div>
        </section>
    </div>
</template>
