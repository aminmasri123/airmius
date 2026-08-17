<script setup>
import AppLayout from '@/Components/Auth/Layouts/AppLayout.vue'
import { Head, Link, router, usePage } from '@inertiajs/vue3'
import { promptDialog } from '@/services/dialogService'
import { translateAdminClubs } from '@/localization/adminClubs'
import { computed, ref } from 'vue'
import { useI18n } from 'vue-i18n'

defineOptions({ layout: AppLayout })

const props = defineProps({
    clubs: {
        type: Object,
        required: true,
    },
    filters: {
        type: Object,
        default: () => ({}),
    },
    summary: {
        type: Object,
        default: () => ({}),
    },
    canDeleteClubs: {
        type: Boolean,
        default: false,
    },
})

const page = usePage()
const { locale } = useI18n()
const t = (key, replacements = {}) => translateAdminClubs(locale.value, key, replacements)
const query = ref(props.filters.query || '')
const verification = ref(props.filters.verification || '')
const deletingClubId = ref(null)
const notice = ref(null)
const localeCode = computed(() => ({
    de: 'de-DE',
    en: 'en-US',
    fr: 'fr-FR',
    ar: 'ar-EG',
}[locale.value] || 'de-DE'))

const paginationLabel = (label) => String(label || '')
    .replace(/<[^>]*>/g, '')
    .replace(/&laquo;/g, '«')
    .replace(/&raquo;/g, '»')
    .replace(/&amp;/g, '&')

const statusLabel = (status) => t(`admin_clubs.status.${status || 'unknown'}`)
const statusClass = (status) => ({
    verified: 'border-success/30 bg-success/10 text-success',
    pending_verification: 'border-warning/30 bg-warning/10 text-warning',
    rejected: 'border-error/30 bg-error/10 text-error',
}[status] || 'border-border bg-muted text-secondary')

const dateLabel = (value) => {
    if (!value) return '-'

    return new Intl.DateTimeFormat(localeCode.value, {
        day: '2-digit',
        month: '2-digit',
        year: 'numeric',
    }).format(new Date(`${value}T00:00:00`))
}

const submitFilters = () => {
    router.get(route('admin.clubs.index'), {
        query: query.value.trim() || undefined,
        verification: verification.value || undefined,
    }, {
        preserveState: true,
        preserveScroll: true,
        replace: true,
    })
}

const resetFilters = () => {
    query.value = ''
    verification.value = ''
    submitFilters()
}

const deleteClub = async (club) => {
    const confirmationName = await promptDialog({
        title: t('admin_clubs.delete.title', { name: club.name }),
        message: t('admin_clubs.delete.message'),
        inputLabel: t('admin_clubs.delete.input_label'),
        placeholder: club.name,
        confirmLabel: t('admin_clubs.delete.confirm'),
        required: true,
        danger: true,
    })

    if (confirmationName === null) return

    if (confirmationName.trim() !== club.name) {
        notice.value = {
            type: 'error',
            message: t('admin_clubs.delete.mismatch'),
        }
        return
    }

    notice.value = null
    deletingClubId.value = club.id

    router.delete(route('admin.clubs.destroy', club.id), {
        data: { confirmation_name: confirmationName.trim() },
        preserveScroll: true,
        onSuccess: () => {
            notice.value = {
                type: 'success',
                message: page.props.flash?.success || t('admin_clubs.delete.success'),
            }
        },
        onError: (errors) => {
            notice.value = {
                type: 'error',
                message: errors.confirmation_name || t('admin_clubs.delete.error'),
            }
        },
        onFinish: () => {
            deletingClubId.value = null
        },
    })
}
</script>

<template>
    <Head :title="t('admin_clubs.title')" />

    <div class="space-y-5">
        <section class="surface-card p-5">
            <div class="flex flex-col gap-4 lg:flex-row lg:items-end lg:justify-between">
                <div>
                    <p class="text-xs font-semibold uppercase text-air-blue">{{ t('admin_clubs.eyebrow') }}</p>
                    <h1 class="mt-1 text-2xl font-bold text-primary">{{ t('admin_clubs.title') }}</h1>
                    <p class="mt-2 max-w-3xl text-sm text-secondary">{{ t('admin_clubs.intro') }}</p>
                </div>
                <Link
                    :href="route('admin.subscriptions.index')"
                    class="inline-flex min-h-10 items-center justify-center gap-2 rounded-lg border border-border px-4 py-2 text-sm font-semibold text-primary hover:bg-muted"
                >
                    <i class="las la-credit-card text-lg" aria-hidden="true"></i>
                    {{ t('admin_clubs.open_subscriptions') }}
                </Link>
            </div>
        </section>

        <section class="surface-card grid grid-cols-2 divide-x divide-y divide-border overflow-hidden lg:grid-cols-4 lg:divide-y-0">
            <div class="p-4">
                <p class="text-xs font-semibold uppercase text-secondary">{{ t('admin_clubs.summary.total') }}</p>
                <p class="mt-1 text-2xl font-bold text-primary">{{ summary.total || 0 }}</p>
            </div>
            <div class="p-4">
                <p class="text-xs font-semibold uppercase text-secondary">{{ t('admin_clubs.summary.verified') }}</p>
                <p class="mt-1 text-2xl font-bold text-success">{{ summary.verified || 0 }}</p>
            </div>
            <div class="p-4">
                <p class="text-xs font-semibold uppercase text-secondary">{{ t('admin_clubs.summary.pending') }}</p>
                <p class="mt-1 text-2xl font-bold text-warning">{{ summary.pending || 0 }}</p>
            </div>
            <div class="p-4">
                <p class="text-xs font-semibold uppercase text-secondary">{{ t('admin_clubs.summary.unlisted') }}</p>
                <p class="mt-1 text-2xl font-bold text-primary">{{ summary.unlisted || 0 }}</p>
            </div>
        </section>

        <div
            v-if="notice"
            class="rounded-lg border px-4 py-3 text-sm"
            :class="notice.type === 'success' ? 'border-success/30 bg-success/10 text-success' : 'border-error/30 bg-error/10 text-error'"
            role="status"
        >
            {{ notice.message }}
        </div>

        <form class="surface-card flex flex-col gap-3 p-4 md:flex-row md:items-end" @submit.prevent="submitFilters">
            <label class="min-w-0 flex-1">
                <span class="mb-1 block text-xs font-semibold uppercase text-secondary">{{ t('admin_clubs.filters.search') }}</span>
                <div class="relative">
                    <i class="las la-search pointer-events-none absolute start-3 top-1/2 -translate-y-1/2 text-lg text-secondary" aria-hidden="true"></i>
                    <input
                        v-model="query"
                        type="search"
                        class="input w-full ps-10"
                        :placeholder="t('admin_clubs.filters.search_placeholder')"
                    />
                </div>
            </label>
            <label class="md:w-64">
                <span class="mb-1 block text-xs font-semibold uppercase text-secondary">{{ t('admin_clubs.filters.verification') }}</span>
                <select v-model="verification" class="input w-full">
                    <option value="">{{ t('admin_clubs.filters.all_statuses') }}</option>
                    <option value="verified">{{ t('admin_clubs.status.verified') }}</option>
                    <option value="pending_verification">{{ t('admin_clubs.status.pending_verification') }}</option>
                    <option value="rejected">{{ t('admin_clubs.status.rejected') }}</option>
                </select>
            </label>
            <div class="flex gap-2">
                <button type="submit" class="min-h-10 rounded-lg bg-buttonPrimary px-4 py-2 text-sm font-semibold text-buttonTextPrimary">
                    {{ t('admin_clubs.filters.apply') }}
                </button>
                <button type="button" class="min-h-10 rounded-lg border border-border px-4 py-2 text-sm font-semibold text-primary hover:bg-muted" @click="resetFilters">
                    {{ t('admin_clubs.filters.reset') }}
                </button>
            </div>
        </form>

        <section class="surface-card overflow-hidden">
            <div class="hidden overflow-x-auto md:block">
                <table class="min-w-full text-left text-sm">
                    <thead class="bg-bg text-xs uppercase text-secondary">
                        <tr>
                            <th class="px-5 py-3">{{ t('admin_clubs.table.club') }}</th>
                            <th class="px-5 py-3">{{ t('admin_clubs.table.owner') }}</th>
                            <th class="px-5 py-3">{{ t('admin_clubs.table.usage') }}</th>
                            <th class="px-5 py-3">{{ t('admin_clubs.table.status') }}</th>
                            <th class="px-5 py-3">{{ t('admin_clubs.table.plan') }}</th>
                            <th class="px-5 py-3 text-right">{{ t('admin_clubs.table.actions') }}</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-border">
                        <tr v-for="club in clubs.data" :key="club.id" class="hover:bg-muted/40">
                            <td class="px-5 py-4">
                                <p class="font-semibold text-primary">{{ club.name }}</p>
                                <p class="mt-1 text-xs text-secondary">
                                    {{ [club.city, club.country].filter(Boolean).join(', ') || '-' }} · {{ t('admin_clubs.created', { date: dateLabel(club.created_at) }) }}
                                </p>
                                <p v-if="club.official_club_number" class="mt-1 text-xs text-secondary">#{{ club.official_club_number }}</p>
                            </td>
                            <td class="px-5 py-4">
                                <p class="font-medium text-primary">{{ club.owner?.name || '-' }}</p>
                                <p class="mt-1 text-xs text-secondary">{{ club.owner?.email || '-' }}</p>
                            </td>
                            <td class="px-5 py-4 text-primary">
                                <p>{{ t('admin_clubs.members', { count: club.members_count }) }}</p>
                                <p class="mt-1 text-xs text-secondary">{{ t('admin_clubs.teams', { count: club.teams_count }) }}</p>
                            </td>
                            <td class="px-5 py-4">
                                <span class="inline-flex rounded-full border px-2.5 py-1 text-xs font-semibold" :class="statusClass(club.verification_status)">
                                    {{ statusLabel(club.verification_status) }}
                                </span>
                                <p class="mt-2 text-xs text-secondary">{{ club.is_listed ? t('admin_clubs.listed') : t('admin_clubs.unlisted') }}</p>
                            </td>
                            <td class="px-5 py-4 font-medium text-primary">{{ club.plan?.name || '-' }}</td>
                            <td class="px-5 py-4">
                                <div class="flex justify-end gap-2">
                                    <Link
                                        :href="route('auth.clubs.show', club.id)"
                                        class="inline-flex size-10 items-center justify-center rounded-lg border border-border text-primary hover:bg-muted"
                                        :aria-label="t('admin_clubs.actions.open_profile')"
                                        :title="t('admin_clubs.actions.open_profile')"
                                    >
                                        <i class="las la-eye text-xl" aria-hidden="true"></i>
                                    </Link>
                                    <Link
                                        :href="route('admin.subscriptions.index', { club_query: club.name })"
                                        class="inline-flex size-10 items-center justify-center rounded-lg border border-border text-primary hover:bg-muted"
                                        :aria-label="t('admin_clubs.actions.open_subscription')"
                                        :title="t('admin_clubs.actions.open_subscription')"
                                    >
                                        <i class="las la-credit-card text-xl" aria-hidden="true"></i>
                                    </Link>
                                    <button
                                        v-if="canDeleteClubs"
                                        type="button"
                                        class="inline-flex size-10 items-center justify-center rounded-lg border border-error/40 text-error hover:bg-error/10 disabled:cursor-not-allowed disabled:opacity-50"
                                        :disabled="deletingClubId === club.id"
                                        :aria-label="t('admin_clubs.actions.delete')"
                                        :title="t('admin_clubs.actions.delete')"
                                        @click="deleteClub(club)"
                                    >
                                        <i class="las text-xl" :class="deletingClubId === club.id ? 'la-spinner la-spin' : 'la-trash'" aria-hidden="true"></i>
                                    </button>
                                </div>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <div class="divide-y divide-border md:hidden">
                <article v-for="club in clubs.data" :key="club.id" class="space-y-4 p-4">
                    <div class="flex items-start justify-between gap-3">
                        <div class="min-w-0">
                            <h2 class="truncate font-semibold text-primary">{{ club.name }}</h2>
                            <p class="mt-1 text-xs text-secondary">{{ [club.city, club.country].filter(Boolean).join(', ') || '-' }}</p>
                        </div>
                        <span class="shrink-0 rounded-full border px-2.5 py-1 text-xs font-semibold" :class="statusClass(club.verification_status)">
                            {{ statusLabel(club.verification_status) }}
                        </span>
                    </div>
                    <dl class="grid grid-cols-2 gap-3 text-sm">
                        <div>
                            <dt class="text-xs text-secondary">{{ t('admin_clubs.table.owner') }}</dt>
                            <dd class="mt-1 truncate font-medium text-primary">{{ club.owner?.name || '-' }}</dd>
                        </div>
                        <div>
                            <dt class="text-xs text-secondary">{{ t('admin_clubs.table.plan') }}</dt>
                            <dd class="mt-1 font-medium text-primary">{{ club.plan?.name || '-' }}</dd>
                        </div>
                        <div>
                            <dt class="text-xs text-secondary">{{ t('admin_clubs.table.usage') }}</dt>
                            <dd class="mt-1 text-primary">{{ t('admin_clubs.members_short', { count: club.members_count }) }} · {{ t('admin_clubs.teams_short', { count: club.teams_count }) }}</dd>
                        </div>
                        <div>
                            <dt class="text-xs text-secondary">{{ t('admin_clubs.created_label') }}</dt>
                            <dd class="mt-1 text-primary">{{ dateLabel(club.created_at) }}</dd>
                        </div>
                    </dl>
                    <div class="flex justify-end gap-2 border-t border-border pt-3">
                        <Link
                            :href="route('auth.clubs.show', club.id)"
                            class="inline-flex size-10 items-center justify-center rounded-lg border border-border text-primary"
                            :aria-label="t('admin_clubs.actions.open_profile')"
                            :title="t('admin_clubs.actions.open_profile')"
                        >
                            <i class="las la-eye text-xl" aria-hidden="true"></i>
                        </Link>
                        <Link
                            :href="route('admin.subscriptions.index', { club_query: club.name })"
                            class="inline-flex size-10 items-center justify-center rounded-lg border border-border text-primary"
                            :aria-label="t('admin_clubs.actions.open_subscription')"
                            :title="t('admin_clubs.actions.open_subscription')"
                        >
                            <i class="las la-credit-card text-xl" aria-hidden="true"></i>
                        </Link>
                        <button
                            v-if="canDeleteClubs"
                            type="button"
                            class="inline-flex size-10 items-center justify-center rounded-lg border border-error/40 text-error disabled:opacity-50"
                            :disabled="deletingClubId === club.id"
                            :aria-label="t('admin_clubs.actions.delete')"
                            :title="t('admin_clubs.actions.delete')"
                            @click="deleteClub(club)"
                        >
                            <i class="las text-xl" :class="deletingClubId === club.id ? 'la-spinner la-spin' : 'la-trash'" aria-hidden="true"></i>
                        </button>
                    </div>
                </article>
            </div>

            <p v-if="!clubs.data.length" class="px-5 py-10 text-center text-sm text-secondary">{{ t('admin_clubs.empty') }}</p>

            <div v-if="clubs.links?.length > 3" class="flex flex-wrap gap-2 border-t border-border px-5 py-4">
                <Link
                    v-for="link in clubs.links"
                    :key="link.label"
                    :href="link.url || '#'"
                    preserve-scroll
                    class="rounded-lg border border-border px-3 py-1.5 text-sm"
                    :class="[
                        link.active ? 'bg-buttonPrimary text-buttonTextPrimary' : 'text-primary hover:bg-muted',
                        !link.url ? 'pointer-events-none opacity-40' : '',
                    ]"
                >
                    {{ paginationLabel(link.label) }}
                </Link>
            </div>
        </section>
    </div>
</template>
