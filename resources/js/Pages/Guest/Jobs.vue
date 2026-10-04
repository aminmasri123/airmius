<script setup>
import Nav from '@/Components/Guest/Nav.vue'
import Subnav from '@/Components/Guest/Subnav.vue'
import Footer from '@/Components/Guest/Footer.vue'
import SeoHead from '@/Components/Guest/SeoHead.vue'
import Modal from '@/Components/Modal.vue'
import SearchableSelect from '@/Components/SearchableSelect.vue'
import { Link, router, usePage } from '@inertiajs/vue3'
import { computed, ref } from 'vue'
import { useI18n } from 'vue-i18n'

const props = defineProps({
    canLogin: Boolean,
    canRegister: Boolean,
    jobs: { type: Object, default: () => ({ data: [], links: [], total: 0, per_page: 12 }) },
    sports: { type: Array, default: () => [] },
    filters: { type: Object, default: () => ({}) },
})

const page = usePage()
const { t, te, locale } = useI18n()
const paginationLabel = (label) => String(label || '')
    .replace(/<[^>]*>/g, '')
    .replace(/&laquo;/g, '«')
    .replace(/&raquo;/g, '»')
    .replace(/&amp;/g, '&')
const user = computed(() => page.props.auth?.user)
const errors = computed(() => page.props.errors || {})
const selectedJob = ref(null)
const interestNotice = ref(null)
const interestPageNotice = ref(null)

const roleOptions = [
    { value: 'all', labelKey: 'guest.jobs.filters.roles.all' },
    { value: 'professional', labelKey: 'guest.jobs.filters.roles.professional' },
    { value: 'volunteer', labelKey: 'guest.jobs.filters.roles.volunteer' },
]

const sortOptions = [
    { value: 'newest', labelKey: 'guest.jobs.filters.sort.newest' },
    { value: 'oldest', labelKey: 'guest.jobs.filters.sort.oldest' },
]

const filterForm = ref({
    role: props.filters.role || props.filters.type || 'all',
    sport_type: props.filters.sport_type || '',
    address: props.filters.address || '',
    sort: props.filters.sort || 'newest',
})

const interestForm = ref({
    name: '',
    email: '',
    phone: '',
    message: '',
    accepted_privacy: false,
    shared_profile_fields: [],
    accepted_profile_sharing: false,
    allow_in_app_contact: false,
})
const isSubmittingInterest = ref(false)
const filterSummaryId = 'jobs-result-summary'

const values = [
    ['las la-heart', 'guest.jobs.values.sport.title', 'guest.jobs.values.sport.text'],
    ['las la-rocket', 'guest.jobs.values.ownership.title', 'guest.jobs.values.ownership.text'],
    ['las la-users', 'guest.jobs.values.team.title', 'guest.jobs.values.team.text'],
]

const jobItems = computed(() => props.jobs?.data || [])
const paginationLinks = computed(() => (props.jobs?.links || []).filter((link) => link.url))
const totalJobs = computed(() => Number(props.jobs?.total || jobItems.value.length))
const dateLocale = computed(() => {
    if (locale.value === 'ar') return 'ar'
    if (locale.value === 'fr') return 'fr-FR'
    if (locale.value === 'en') return 'en-US'

    return 'de-DE'
})

const hasActiveFilters = computed(() => Boolean(
    filterForm.value.role !== 'all'
    || filterForm.value.sport_type
    || filterForm.value.address
    || filterForm.value.sort !== 'newest'
))

const visibleRoleLabel = computed(() => {
    const option = roleOptions.find((item) => item.value === filterForm.value.role)

    return option ? t(option.labelKey) : t('guest.jobs.filters.roles.all')
})

const resultSummary = computed(() => {
    const total = totalJobs.value

    if (!total) {
        return t('guest.jobs.results.none')
    }

    if (filterForm.value.role === 'professional') {
        return t(total === 1 ? 'guest.jobs.results.professional_one' : 'guest.jobs.results.professional_many', { total })
    }

    if (filterForm.value.role === 'volunteer') {
        return t(total === 1 ? 'guest.jobs.results.volunteer_one' : 'guest.jobs.results.volunteer_many', { total })
    }

    return t(total === 1 ? 'guest.jobs.results.open_one' : 'guest.jobs.results.open_many', { total })
})

const emptyStateText = computed(() => {
    if (hasActiveFilters.value) {
        return t('guest.jobs.empty.filtered_text')
    }

    return t('guest.jobs.empty.default_text')
})

const sportLabel = (value) => {
    if (!value) return t('guest.jobs.meta.sport_open')

    const sport = props.sports.find((sport) => sport.slug === value || sport.name === value)
    const slug = sport?.slug || value
    const key = `sports.${slug}`

    return te(key) ? t(key) : (sport?.name || value)
}

const roleLabel = (value) => {
    if (value === 'professional') return t('guest.jobs.filters.roles.professional')

    return t('guest.jobs.filters.roles.volunteer')
}

const formatJobMeta = (job) => {
    return [
        job.location || t('guest.jobs.meta.location_open'),
        job.workload || t('guest.jobs.meta.workload_open'),
        job.employment_type || t('guest.jobs.meta.flexible'),
    ].join(' · ')
}

const formatDate = (isoDate) => {
    if (!isoDate) {
        return null
    }

    return new Intl.DateTimeFormat(dateLocale.value, {
        day: '2-digit',
        month: 'short',
        year: 'numeric',
    }).format(new Date(isoDate))
}

const applyFilters = () => {
    router.get(route('guest.jobs'), {
        page: undefined,
        type: filterForm.value.role === 'all' ? undefined : filterForm.value.role,
        sport_type: filterForm.value.sport_type || undefined,
        address: filterForm.value.address || undefined,
        sort: filterForm.value.sort || undefined,
    }, {
        preserveState: true,
        preserveScroll: true,
        replace: true,
        only: ['jobs', 'filters'],
    })
}

const resetFilters = () => {
    filterForm.value = {
        role: 'all',
        sport_type: '',
        address: '',
        sort: 'newest',
    }

    applyFilters()
}

const openInterestModal = (job) => {
    selectedJob.value = job
    interestNotice.value = null
    interestPageNotice.value = null
    isSubmittingInterest.value = false
    interestForm.value = {
        name: user.value?.name || '',
        email: user.value?.email || '',
        phone: '',
        message: '',
        accepted_privacy: false,
        shared_profile_fields: [],
        accepted_profile_sharing: false,
        allow_in_app_contact: false,
    }
}

const closeInterestModal = () => {
    selectedJob.value = null
}

const submitInterest = () => {
    if (!selectedJob.value) {
        return
    }

    if (isSubmittingInterest.value) {
        return
    }

    interestNotice.value = null
    interestPageNotice.value = null
    isSubmittingInterest.value = true

    router.post(route('guest.jobs.interest', selectedJob.value.id), interestForm.value, {
        preserveScroll: true,
        onSuccess: () => {
            interestNotice.value = {
                type: 'success',
                message: t('guest.jobs.interest.success'),
            }
            interestPageNotice.value = {
                type: 'success',
                message: t('guest.jobs.interest.page_success', { title: selectedJob.value.title }),
            }
            interestForm.value = {
                name: user.value?.name || '',
                email: user.value?.email || '',
                phone: '',
                message: '',
                accepted_privacy: false,
                shared_profile_fields: [],
                accepted_profile_sharing: false,
                allow_in_app_contact: false,
            }
            closeInterestModal()
        },
        onError: () => {
            interestNotice.value = {
                type: 'error',
                message: t('guest.jobs.interest.error'),
            }
            interestPageNotice.value = null
        },
        onFinish: () => {
            isSubmittingInterest.value = false
        },
    })
}
</script>

<template>
    <SeoHead
        :title="t('guest.jobs.meta_title')"
        :description="t('guest.jobs.meta_description')"
    />

    <div class="min-h-screen bg-bg text-primary">
        <Nav :canLogin="canLogin" :canRegister="canRegister" />
        <Subnav />

        <main id="main-content" class="px-4 pt-36 md:pt-44" tabindex="-1">
            <section class="mx-auto max-w-6xl text-center">
                <span class="text-sm font-semibold uppercase tracking-wider text-air-green">{{ $t('Jobs') }}</span>
                <h1 class="mx-auto mt-3 max-w-4xl font-heading text-4xl font-900 leading-tight sm:text-5xl">
                    {{ $t('guest.jobs.hero.title') }}
                </h1>
                <p class="mx-auto mt-5 max-w-2xl text-lg leading-relaxed text-secondary">
                    {{ $t('guest.jobs.hero.subtitle') }}
                </p>
            </section>

            <section class="mx-auto mt-14 grid max-w-6xl gap-5 lg:grid-cols-3">
                <div v-for="[icon, title, text] in values" :key="title" class="surface-card p-5 text-center">
                    <div class="mx-auto mb-4 flex h-12 w-12 items-center justify-center rounded-lg bg-inputBg">
                        <i :class="[icon, 'text-2xl text-air-green']"></i>
                    </div>
                    <h2 class="font-bold text-primary">{{ $t(title) }}</h2>
                    <p class="mt-2 text-sm leading-relaxed text-secondary">{{ $t(text) }}</p>
                </div>

            </section>

            <section class="mx-auto mt-14 max-w-6xl pb-20">
                <div class="mb-6">
                    <div class="flex flex-col gap-1 sm:flex-row sm:items-end sm:justify-between">
                        <div>
                            <h2 class="text-2xl font-bold text-primary">{{ $t('guest.jobs.open_roles') }}</h2>
                            <p
                                :id="filterSummaryId"
                                class="mt-2 text-sm text-secondary"
                                role="status"
                                aria-live="polite"
                                aria-atomic="true"
                            >
                                {{ visibleRoleLabel }} · {{ resultSummary }}
                            </p>
                            <div
                                v-if="interestPageNotice"
                                class="mt-3 rounded-lg border border-air-green/30 bg-air-green/10 px-4 py-3 text-sm font-medium text-air-green"
                                role="status"
                                aria-live="polite"
                            >
                                {{ interestPageNotice.message }}
                            </div>
                        </div>
                    </div>
                </div>

                <form
                    class="mb-6 grid gap-3 rounded-xl border border-border bg-card p-4 md:grid-cols-[minmax(0,1fr)_minmax(0,1fr)_minmax(0,1fr)_minmax(0,1fr)_auto]"
                    @submit.prevent="applyFilters"
                >
                    <label class="block">
                        <span class="text-xs font-semibold uppercase text-secondary">{{ t('guest.jobs.filters.role_type') }}</span>
                        <select
                            id="jobs-filter-role"
                            v-model="filterForm.role"
                            class="mt-1 w-full rounded-lg border border-border bg-inputBg px-3 py-3 text-sm text-primary"
                            :aria-label="t('guest.jobs.filters.role_type')"
                        >
                            <option v-for="option in roleOptions" :key="option.value" :value="option.value">
                                {{ t(option.labelKey) }}
                            </option>
                        </select>
                    </label>

                    <label class="block">
                        <span class="text-xs font-semibold uppercase text-secondary">{{ t('guest.jobs.filters.sport') }}</span>
                        <SearchableSelect
                            id="jobs-filter-sport"
                            v-model="filterForm.sport_type"
                            class="mt-1 w-full"
                            :options="sports"
                            value-key="slug"
                            translation-prefix="sports"
                            category-translation-prefix="sport_categories"
                            :placeholder="t('guest.jobs.filters.sport_placeholder')"
                            :aria-label="t('guest.jobs.filters.sport')"
                        />
                    </label>

                    <label class="block">
                        <span class="text-xs font-semibold uppercase text-secondary">{{ t('guest.jobs.filters.address') }}</span>
                        <input
                            id="jobs-filter-address"
                            v-model="filterForm.address"
                            class="mt-1 w-full rounded-lg border border-border bg-inputBg px-3 py-3 text-sm text-primary"
                            type="search"
                            :placeholder="t('guest.jobs.filters.address_placeholder')"
                            :aria-label="t('guest.jobs.filters.address_aria')"
                            autocomplete="address-line1"
                        >
                    </label>

                    <label class="block">
                        <span class="text-xs font-semibold uppercase text-secondary">{{ t('guest.jobs.filters.sort_label') }}</span>
                        <select
                            id="jobs-filter-sort"
                            v-model="filterForm.sort"
                            class="mt-1 w-full rounded-lg border border-border bg-inputBg px-3 py-3 text-sm text-primary"
                            :aria-label="t('guest.jobs.filters.sort_label')"
                        >
                            <option v-for="option in sortOptions" :key="option.value" :value="option.value">
                                {{ t(option.labelKey) }}
                            </option>
                        </select>
                    </label>

                    <div class="flex flex-col gap-2 md:justify-end md:flex-row">
                        <button
                            type="submit"
                            class="w-full rounded-lg bg-buttonPrimary px-4 py-3 text-sm font-semibold text-buttonTextPrimary disabled:opacity-60 md:w-auto"
                            :aria-label="t('guest.jobs.filters.submit_aria')"
                        >
                            {{ t('guest.jobs.filters.submit') }}
                        </button>
                        <button
                            v-if="hasActiveFilters"
                            type="button"
                            class="w-full rounded-lg border border-border px-4 py-3 text-sm font-semibold text-primary hover:bg-muted md:w-auto"
                            @click="resetFilters"
                            :aria-label="t('guest.jobs.filters.reset')"
                        >
                            {{ t('guest.jobs.filters.reset') }}
                        </button>
                    </div>
                </form>

                <div class="space-y-3">
                    <article
                        v-for="job in jobItems"
                        :key="job.id"
                        class="surface-card flex flex-col gap-4 p-5 sm:flex-row sm:items-start sm:justify-between"
                    >
                        <div>
                            <div class="mb-2 flex flex-wrap items-center gap-2">
                                <span
                                    class="rounded-full px-2 py-1 text-xs font-semibold"
                                    :class="job.type === 'volunteer' ? 'bg-air-green/15 text-air-green' : 'bg-air-blue/15 text-air-blue'"
                                >
                                    {{ roleLabel(job.type) }}
                                </span>
                                <span class="rounded-full bg-inputBg px-2 py-1 text-xs font-semibold text-secondary">
                                    {{ sportLabel(job.club?.sport_type) }}
                                </span>
                                <span class="text-xs text-secondary">{{ job.club?.name }}</span>
                            </div>
                            <h3 class="text-lg font-bold text-primary">{{ job.title }}</h3>
                            <p class="mt-1 text-sm text-secondary">
                                {{ formatJobMeta(job) }}
                            </p>
                            <p v-if="job.sport || job.minimum_experience_level" class="mt-2 text-xs font-semibold text-air-blue">
                                {{ job.sport?.name || sportLabel(job.club?.sport_type) }}
                                <span v-if="job.minimum_experience_level"> · {{ t('recruiting.criteria.from_experience', { level: t(`recruiting.experience.${job.minimum_experience_level}`) }) }}</span>
                            </p>
                            <p class="mt-3 max-w-2xl whitespace-pre-line text-sm leading-relaxed text-secondary">
                                {{ job.description }}
                            </p>
                            <p v-if="job.published_at" class="mt-2 text-xs text-secondary">
                                {{ t('guest.jobs.meta.published_on', { date: formatDate(job.published_at) }) }}
                            </p>
                        </div>

                        <div class="flex w-full flex-col gap-2 sm:min-w-[220px] sm:w-auto">
                            <button
                                type="button"
                                class="w-full rounded-full border border-border px-4 py-2 text-center text-sm font-semibold text-primary transition hover:bg-muted"
                                @click="openInterestModal(job)"
                                :aria-label="t('guest.jobs.interest.apply_aria', { title: job.title })"
                            >
                                {{ $t('guest.jobs.apply') }}
                            </button>
                            <a
                                v-if="job.application_url"
                                :href="job.application_url"
                                target="_blank"
                                rel="noopener noreferrer"
                                class="w-full rounded-full border border-air-blue/30 px-4 py-2 text-center text-sm font-semibold text-air-blue transition hover:bg-air-blue/10"
                                :aria-label="t('guest.jobs.interest.external_aria', { title: job.title })"
                            >
                                {{ t('guest.jobs.interest.apply_external') }}
                            </a>
                            <a
                                v-if="job.contact_email"
                                :href="`mailto:${job.contact_email}`"
                                class="w-full rounded-full border border-border px-4 py-2 text-center text-sm font-semibold text-primary transition hover:bg-muted"
                                :aria-label="t('guest.jobs.interest.email_club_aria', { title: job.title })"
                            >
                                {{ t('guest.jobs.interest.email_club') }}
                            </a>
                        </div>
                    </article>

                    <div v-if="!jobItems.length" class="surface-card p-8 text-center text-sm text-secondary">
                        <p class="mb-2 font-semibold text-primary">
                            {{ hasActiveFilters ? t('guest.jobs.empty.filtered_title') : t('guest.jobs.empty.default_title') }}
                        </p>
                        <p class="mx-auto mb-4 max-w-lg">
                            {{ emptyStateText }}
                        </p>
                        <button
                            v-if="hasActiveFilters"
                            type="button"
                            class="w-full rounded-lg border border-border px-4 py-2 text-sm font-semibold text-primary hover:bg-muted md:w-auto"
                            @click="resetFilters"
                            :aria-label="t('guest.jobs.filters.reset')"
                        >
                            {{ t('guest.jobs.filters.reset') }}
                        </button>
                    </div>
                </div>
                <nav v-if="paginationLinks.length > 1" class="mt-6 flex flex-wrap justify-center gap-2">
                    <Link
                        v-for="link in paginationLinks"
                        :key="`${link.label}-${link.url}`"
                        :href="link.url"
                        :only="['jobs', 'filters']"
                        preserve-state
                        preserve-scroll
                        class="rounded border px-3 py-2 text-sm font-bold"
                        :class="link.active ? 'border-borderHover bg-buttonPrimary text-buttonTextPrimary' : 'border-border bg-card text-primary hover:bg-muted'"
                    >
                        {{ paginationLabel(link.label) }}
                    </Link>
                </nav>
            </section>
        </main>

        <Footer />

        <Modal :show="Boolean(selectedJob)" max-width="lg" @close="closeInterestModal">
            <form
                v-if="selectedJob"
                class="space-y-5"
                @submit.prevent="submitInterest"
                aria-labelledby="jobs-interest-title"
                :aria-describedby="'jobs-interest-intro jobs-selected-summary ' + (interestNotice ? 'jobs-interest-notice' : '')"
            >
                <div>
                    <p class="text-xs font-semibold uppercase tracking-wide text-secondary">
                        {{ selectedJob.club?.name }}
                    </p>
                    <h2 class="mt-1 text-xl font-bold text-primary" id="jobs-interest-title">
                        {{ t('guest.jobs.interest.title') }}
                    </h2>
                    <p class="mt-2 text-sm text-secondary" id="jobs-interest-intro">
                        {{ t('guest.jobs.interest.intro') }}
                    </p>
                </div>

                <div
                    id="jobs-interest-notice"
                    v-if="interestNotice"
                    class="rounded-lg border px-4 py-3 text-sm"
                    :class="interestNotice.type === 'success'
                        ? 'border-air-green/30 bg-air-green/10 text-air-green'
                        : 'border-error/30 bg-error/10 text-error'"
                    role="status"
                    aria-live="polite"
                >
                    {{ interestNotice.message }}
                </div>

                <div class="rounded-lg border border-border bg-inputBg p-3 text-sm text-secondary" id="jobs-selected-summary">
                    <strong class="text-primary">{{ selectedJob.title }}</strong>
                    <span class="block">
                        {{ formatJobMeta(selectedJob) }}
                    </span>
                </div>

                <div
                    v-if="selectedJob.application_url"
                    class="rounded-lg border border-air-blue/30 bg-air-blue/10 p-3 text-sm text-secondary"
                >
                    <span class="block font-semibold text-primary">{{ t('guest.jobs.interest.external_available') }}</span>
                    <span class="mt-1 block">
                        {{ t('guest.jobs.interest.external_hint') }}
                    </span>
                    <a
                        :href="selectedJob.application_url"
                        target="_blank"
                        rel="noopener noreferrer"
                        :aria-label="t('guest.jobs.interest.external_form_aria', { title: selectedJob.title })"
                        class="mt-3 inline-flex rounded-lg border border-border bg-card px-3 py-2 text-sm font-semibold text-primary hover:bg-muted"
                    >
                        {{ t('guest.jobs.interest.apply_external') }}
                    </a>
                </div>

                <div
                    v-if="selectedJob.contact_email"
                    class="rounded-lg border border-border bg-card p-3 text-sm text-secondary"
                >
                    <span class="block font-semibold text-primary">{{ t('guest.jobs.interest.direct_contact') }}</span>
                    <a
                        :href="`mailto:${selectedJob.contact_email}`"
                        class="mt-1 block text-air-blue underline decoration-air-blue/30 hover:decoration-current"
                        :aria-label="t('guest.jobs.interest.contact_email_aria', { email: selectedJob.contact_email })"
                    >
                        {{ selectedJob.contact_email }}
                    </a>
                </div>

                <div class="grid gap-3 sm:grid-cols-2">
                    <label class="block">
                        <span class="text-xs font-semibold uppercase text-secondary">{{ t('guest.jobs.interest.name_label') }} *</span>
                        <input
                            id="jobs-interest-name"
                            v-model="interestForm.name"
                            required
                            autocomplete="name"
                            class="mt-1 w-full rounded-lg border border-border bg-inputBg px-3 py-3 text-sm text-primary"
                            :placeholder="t('guest.jobs.interest.name_placeholder')"
                            :aria-describedby="errors.name ? 'jobs-interest-name-error' : 'jobs-interest-name-help'"
                            :aria-invalid="Boolean(errors.name)"
                        >
                        <span id="jobs-interest-name-help" class="text-xs text-secondary">
                            {{ t('guest.jobs.interest.name_help') }}
                        </span>
                        <span v-if="errors.name" id="jobs-interest-name-error" class="mt-1 block text-xs text-error">
                            {{ errors.name }}
                        </span>
                    </label>

                    <label class="block">
                        <span class="text-xs font-semibold uppercase text-secondary">{{ t('guest.jobs.interest.email_label') }} *</span>
                        <input
                            id="jobs-interest-email"
                            v-model="interestForm.email"
                            required
                            type="email"
                            autocomplete="email"
                            class="mt-1 w-full rounded-lg border border-border bg-inputBg px-3 py-3 text-sm text-primary"
                            placeholder="name@example.com"
                            :aria-describedby="errors.email ? 'jobs-interest-email-error' : 'jobs-interest-email-help'"
                            :aria-invalid="Boolean(errors.email)"
                        >
                        <span id="jobs-interest-email-help" class="text-xs text-secondary">
                            {{ t('guest.jobs.interest.email_help') }}
                        </span>
                        <span v-if="errors.email" id="jobs-interest-email-error" class="mt-1 block text-xs text-error">
                            {{ errors.email }}
                        </span>
                    </label>
                </div>

                <label class="block">
                    <span class="text-xs font-semibold uppercase text-secondary">{{ t('guest.jobs.interest.phone_label') }}</span>
                    <input
                        id="jobs-interest-phone"
                        v-model="interestForm.phone"
                        autocomplete="tel"
                        inputmode="tel"
                        aria-describedby="jobs-interest-phone-help"
                        class="mt-1 w-full rounded-lg border border-border bg-inputBg px-3 py-3 text-sm text-primary"
                        :placeholder="t('guest.jobs.interest.phone_placeholder')"
                    >
                    <span id="jobs-interest-phone-help" class="text-xs text-secondary">
                        {{ t('guest.jobs.interest.phone_help') }}
                    </span>
                    <span v-if="errors.phone" class="mt-1 block text-xs text-error">{{ errors.phone }}</span>
                </label>

                <label class="block">
                    <span class="text-xs font-semibold uppercase text-secondary">{{ t('guest.jobs.interest.message_label') }}</span>
                    <textarea
                        id="jobs-interest-message"
                        v-model="interestForm.message"
                        rows="4"
                        class="mt-1 w-full rounded-lg border border-border bg-inputBg px-3 py-3 text-sm text-primary"
                        :placeholder="t('guest.jobs.interest.message_placeholder')"
                        aria-describedby="jobs-interest-message-help"
                    ></textarea>
                    <span id="jobs-interest-message-help" class="text-xs text-secondary">
                        {{ t('guest.jobs.interest.message_help') }}
                    </span>
                    <span v-if="errors.message" class="mt-1 block text-xs text-error">{{ errors.message }}</span>
                </label>

                <p class="rounded-lg border border-border bg-inputBg/50 px-3 py-2 text-xs leading-5 text-secondary">
                    {{ t('guest.jobs.interest.privacy_notice') }}
                </p>
                <label class="flex items-start gap-3 rounded-lg border border-border px-3 py-3 text-sm text-primary">
                    <input v-model="interestForm.accepted_privacy" type="checkbox" class="mt-0.5 rounded border-border text-buttonPrimary" required>
                    <span>{{ t('guest.jobs.interest.privacy_accept') }}</span>
                </label>
                <span v-if="errors.accepted_privacy" class="text-xs text-error">{{ errors.accepted_privacy }}</span>

                <section v-if="user" class="rounded-lg border border-air-blue/30 bg-air-blue/5 p-3">
                    <p class="text-sm font-semibold text-primary">{{ t('recruiting.profile_share.title') }}</p>
                    <p class="mt-1 text-xs leading-5 text-secondary">{{ t('recruiting.profile_share.help') }}</p>
                    <div class="mt-3 grid gap-2 sm:grid-cols-2">
                        <label v-for="field in ['sports', 'experience']" :key="field" class="flex items-start gap-2 text-sm text-primary">
                            <input v-model="interestForm.shared_profile_fields" :value="field" type="checkbox" class="mt-0.5 rounded border-border text-buttonPrimary">
                            <span>{{ t(`recruiting.profile_share.${field}`) }}</span>
                        </label>
                    </div>
                    <label v-if="interestForm.shared_profile_fields.length" class="mt-3 flex items-start gap-2 border-t border-border pt-3 text-sm text-primary">
                        <input v-model="interestForm.accepted_profile_sharing" type="checkbox" class="mt-0.5 rounded border-border text-buttonPrimary" required>
                        <span>{{ t('recruiting.profile_share.consent') }}</span>
                    </label>
                    <label class="mt-3 flex items-start gap-2 text-sm text-primary">
                        <input v-model="interestForm.allow_in_app_contact" type="checkbox" class="mt-0.5 rounded border-border text-buttonPrimary">
                        <span>{{ t('recruiting.profile_share.chat_consent') }}</span>
                    </label>
                    <p class="mt-2 text-xs text-secondary">{{ t('recruiting.profile_share.revoke') }}</p>
                </section>

                <div class="flex flex-col-reverse gap-2 sm:flex-row sm:justify-end">
                    <button
                        type="button"
                        class="rounded-lg border border-border px-4 py-3 text-sm font-semibold text-primary hover:bg-muted"
                        @click="closeInterestModal"
                        :aria-label="t('guest.jobs.interest.close')"
                    >
                        {{ t('guest.jobs.interest.close') }}
                    </button>
                    <button
                        type="submit"
                        class="rounded-lg bg-buttonPrimary px-4 py-3 text-sm font-semibold text-buttonTextPrimary disabled:opacity-60"
                        :disabled="isSubmittingInterest || !interestForm.accepted_privacy || (interestForm.shared_profile_fields.length > 0 && !interestForm.accepted_profile_sharing)"
                        :aria-busy="isSubmittingInterest"
                        :aria-label="isSubmittingInterest ? t('guest.jobs.interest.sending') : t('guest.jobs.interest.send')"
                    >
                        {{ isSubmittingInterest ? t('guest.jobs.interest.sending') : t('guest.jobs.interest.send') }}
                    </button>
                </div>
            </form>
        </Modal>
    </div>
</template>
