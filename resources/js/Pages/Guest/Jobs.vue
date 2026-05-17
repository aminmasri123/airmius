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
const { t, te } = useI18n()
const user = computed(() => page.props.auth?.user)
const errors = computed(() => page.props.errors || {})
const selectedJob = ref(null)
const interestNotice = ref(null)
const interestPageNotice = ref(null)

const roleOptions = [
    { value: 'all', label: 'Alle Rollen' },
    { value: 'professional', label: 'Beruflich' },
    { value: 'volunteer', label: 'Ehrenamt' },
]

const sortOptions = [
    { value: 'newest', label: 'Neueste zuerst' },
    { value: 'oldest', label: 'Älteste zuerst' },
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

const hasActiveFilters = computed(() => Boolean(
    filterForm.value.role !== 'all'
    || filterForm.value.sport_type
    || filterForm.value.address
    || filterForm.value.sort !== 'newest'
))

const visibleRoleLabel = computed(() => {
    return roleOptions.find((option) => option.value === filterForm.value.role)?.label || 'Alle Rollen'
})

const resultSummary = computed(() => {
    const total = totalJobs.value

    if (!total) {
        return 'Derzeit sind keine offenen Rollen verfügbar.'
    }

    if (filterForm.value.role === 'professional') {
        return `${total} ${total === 1 ? 'berufliche Rolle' : 'berufliche Rollen'} gefunden.`
    }

    if (filterForm.value.role === 'volunteer') {
        return `${total} ${total === 1 ? 'Ehrenamtsangebot' : 'Ehrenamtsangebote'} gefunden.`
    }

    return `${total} offene ${total === 1 ? 'Rolle' : 'Rollen'} gefunden.`
})

const emptyStateText = computed(() => {
    if (hasActiveFilters.value) {
        return 'Zu deinen aktuellen Filtern gibt es gerade keine offenen Rollen.'
    }

    return 'Schau bald wieder vorbei. Neue Jobs und Ehrenamtsrollen werden hier veröffentlicht.'
})

const sportLabel = (value) => {
    if (!value) return 'Sportart offen'

    const sport = props.sports.find((sport) => sport.slug === value || sport.name === value)
    const slug = sport?.slug || value
    const key = `sports.${slug}`

    return te(key) ? t(key) : (sport?.name || value)
}

const roleLabel = (value) => {
    if (value === 'professional') return 'Beruflich'

    return 'Ehrenamt'
}

const clubAddress = (club) => {
    const cityLine = [club?.postal_code, club?.city].filter(Boolean).join(' ')
    const streetLine = [club?.street, club?.house_number].filter(Boolean).join(' ')

    return [streetLine, cityLine, club?.country].filter(Boolean).join(', ')
}

const formatJobMeta = (job) => {
    return [
        job.location || 'Ort offen',
        job.workload || 'Umfang offen',
        job.employment_type || 'Flexibel',
    ].join(' · ')
}

const formatDate = (isoDate) => {
    if (!isoDate) {
        return null
    }

    return new Intl.DateTimeFormat('de-DE', {
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
                message: 'Dein Interesse wurde gesendet.',
            }
            interestPageNotice.value = {
                type: 'success',
                message: `Dein Interesse für "${selectedJob.value.title}" wurde an den Verein gesendet.`,
            }
            interestForm.value = {
                name: user.value?.name || '',
                email: user.value?.email || '',
                phone: '',
                message: '',
            }
            closeInterestModal()
        },
        onError: () => {
            interestNotice.value = {
                type: 'error',
                message: 'Bitte prüfe deine Angaben.',
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
        title="Jobs im Sport"
        description="Finde Jobs, Ehrenamt und Vereinsrollen im Sport. Airmius verbindet Vereine, Organisationen und Menschen, die den Sport gestalten."
    />

    <div class="min-h-screen bg-bg text-primary">
        <Nav :canLogin="canLogin" :canRegister="canRegister" />
        <Subnav />

        <main class="px-4 pt-36 md:pt-44">
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
                        <span class="text-xs font-semibold uppercase text-secondary">Rollenart</span>
                        <select
                            id="jobs-filter-role"
                            v-model="filterForm.role"
                            class="mt-1 w-full rounded-lg border border-border bg-inputBg px-3 py-3 text-sm text-primary"
                            aria-label="Rollenart"
                        >
                            <option v-for="option in roleOptions" :key="option.value" :value="option.value">
                                {{ option.label }}
                            </option>
                        </select>
                    </label>

                    <label class="block">
                        <span class="text-xs font-semibold uppercase text-secondary">Sportart</span>
                        <SearchableSelect
                            id="jobs-filter-sport"
                            v-model="filterForm.sport_type"
                            class="mt-1 w-full"
                            :options="sports"
                            value-key="slug"
                            translation-prefix="sports"
                            category-translation-prefix="sport_categories"
                            placeholder="Sportart suchen"
                            aria-label="Sportart"
                        />
                    </label>

                    <label class="block">
                        <span class="text-xs font-semibold uppercase text-secondary">Adresse / Ort</span>
                        <input
                            id="jobs-filter-address"
                            v-model="filterForm.address"
                            class="mt-1 w-full rounded-lg border border-border bg-inputBg px-3 py-3 text-sm text-primary"
                            type="search"
                            placeholder="Stadt, PLZ, Land oder Adresse"
                            aria-label="Adresse oder Ort"
                            autocomplete="address-line1"
                        >
                    </label>

                    <label class="block">
                        <span class="text-xs font-semibold uppercase text-secondary">Sortierung</span>
                        <select
                            id="jobs-filter-sort"
                            v-model="filterForm.sort"
                            class="mt-1 w-full rounded-lg border border-border bg-inputBg px-3 py-3 text-sm text-primary"
                            aria-label="Sortierung"
                        >
                            <option v-for="option in sortOptions" :key="option.value" :value="option.value">
                                {{ option.label }}
                            </option>
                        </select>
                    </label>

                    <div class="flex flex-col gap-2 md:justify-end md:flex-row">
                        <button
                            type="submit"
                            class="w-full rounded-lg bg-buttonPrimary px-4 py-3 text-sm font-semibold text-buttonTextPrimary disabled:opacity-60 md:w-auto"
                            aria-label="Jobs filtern"
                        >
                            Filtern
                        </button>
                        <button
                            v-if="hasActiveFilters"
                            type="button"
                            class="w-full rounded-lg border border-border px-4 py-3 text-sm font-semibold text-primary hover:bg-muted md:w-auto"
                            @click="resetFilters"
                            aria-label="Filter zurücksetzen"
                        >
                            Zurücksetzen
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
                            <p v-if="clubAddress(job.club)" class="mt-1 text-xs text-secondary">
                                {{ clubAddress(job.club) }}
                            </p>
                            <p class="mt-3 max-w-2xl whitespace-pre-line text-sm leading-relaxed text-secondary">
                                {{ job.description }}
                            </p>
                            <p v-if="job.published_at" class="mt-2 text-xs text-secondary">
                                Veröffentlicht am {{ formatDate(job.published_at) }}
                            </p>
                        </div>

                        <div class="flex w-full flex-col gap-2 sm:min-w-[220px] sm:w-auto">
                            <button
                                type="button"
                                class="w-full rounded-full border border-border px-4 py-2 text-center text-sm font-semibold text-primary transition hover:bg-muted"
                                @click="openInterestModal(job)"
                                :aria-label="`Jetzt für ${job.title} bewerben`"
                            >
                                {{ $t('guest.jobs.apply') }}
                            </button>
                            <a
                                v-if="job.application_url"
                                :href="job.application_url"
                                target="_blank"
                                rel="noopener noreferrer"
                                class="w-full rounded-full border border-air-blue/30 px-4 py-2 text-center text-sm font-semibold text-air-blue transition hover:bg-air-blue/10"
                                :aria-label="`Externe Bewerbung für ${job.title} öffnen`"
                            >
                                Direkt bewerben
                            </a>
                            <a
                                v-if="job.contact_email"
                                :href="`mailto:${job.contact_email}`"
                                class="w-full rounded-full border border-border px-4 py-2 text-center text-sm font-semibold text-primary transition hover:bg-muted"
                                :aria-label="`E-Mail an Verein für ${job.title} senden`"
                            >
                                E-Mail an Verein
                            </a>
                        </div>
                    </article>

                    <div v-if="!jobItems.length" class="surface-card p-8 text-center text-sm text-secondary">
                        <p class="mb-2 font-semibold text-primary">
                            {{ hasActiveFilters ? 'Keine passenden Rollen gefunden.' : 'Derzeit sind keine offenen Rollen veröffentlicht.' }}
                        </p>
                        <p class="mx-auto mb-4 max-w-lg">
                            {{ emptyStateText }}
                        </p>
                        <button
                            v-if="hasActiveFilters"
                            type="button"
                            class="w-full rounded-lg border border-border px-4 py-2 text-sm font-semibold text-primary hover:bg-muted md:w-auto"
                            @click="resetFilters"
                            aria-label="Filter zurücksetzen"
                        >
                            Filter zurücksetzen
                        </button>
                    </div>
                </div>
                <nav v-if="paginationLinks.length > 1" class="mt-6 flex flex-wrap justify-center gap-2">
                    <Link
                        v-for="link in paginationLinks"
                        :key="`${link.label}-${link.url}`"
                        :href="link.url"
                        preserve-scroll
                        class="rounded border px-3 py-2 text-sm font-bold"
                        :class="link.active ? 'border-borderHover bg-buttonPrimary text-buttonTextPrimary' : 'border-border bg-card text-primary hover:bg-muted'"
                        v-html="link.label"
                    />
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
                        Interesse melden
                    </h2>
                    <p class="mt-2 text-sm text-secondary" id="jobs-interest-intro">
                        Deine Angaben werden an den Verein weitergeleitet. Du musst dafür nicht angemeldet sein.
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
                    <span class="block font-semibold text-primary">Externe Bewerbung vorhanden</span>
                    <span class="mt-1 block">
                        Du kannst dein Interesse hier senden oder dich direkt über das externe Formular bewerben.
                    </span>
                    <a
                        :href="selectedJob.application_url"
                        target="_blank"
                        rel="noopener noreferrer"
                        :aria-label="`Externes Bewerbungsformular für ${selectedJob.title} öffnen`"
                        class="mt-3 inline-flex rounded-lg border border-border bg-card px-3 py-2 text-sm font-semibold text-primary hover:bg-muted"
                    >
                        Extern bewerben
                    </a>
                </div>

                <div
                    v-if="selectedJob.contact_email"
                    class="rounded-lg border border-border bg-card p-3 text-sm text-secondary"
                >
                    <span class="block font-semibold text-primary">Direkte Kontaktmöglichkeit</span>
                    <a
                        :href="`mailto:${selectedJob.contact_email}`"
                        class="mt-1 block text-air-blue underline decoration-air-blue/30 hover:decoration-current"
                        :aria-label="`Kontakt per E-Mail an ${selectedJob.contact_email} senden`"
                    >
                        {{ selectedJob.contact_email }}
                    </a>
                </div>

                <div class="grid gap-3 sm:grid-cols-2">
                    <label class="block">
                        <span class="text-xs font-semibold uppercase text-secondary">Name *</span>
                        <input
                            id="jobs-interest-name"
                            v-model="interestForm.name"
                            required
                            autocomplete="name"
                            class="mt-1 w-full rounded-lg border border-border bg-inputBg px-3 py-3 text-sm text-primary"
                            placeholder="Dein Name"
                            :aria-describedby="errors.name ? 'jobs-interest-name-error' : 'jobs-interest-name-help'"
                            :aria-invalid="Boolean(errors.name)"
                        >
                        <span id="jobs-interest-name-help" class="text-xs text-secondary">
                            Bitte gib deinen vollständigen Namen ein.
                        </span>
                        <span v-if="errors.name" id="jobs-interest-name-error" class="mt-1 block text-xs text-error">
                            {{ errors.name }}
                        </span>
                    </label>

                    <label class="block">
                        <span class="text-xs font-semibold uppercase text-secondary">E-Mail *</span>
                        <input
                            id="jobs-interest-email"
                            v-model="interestForm.email"
                            required
                            type="email"
                            autocomplete="email"
                            class="mt-1 w-full rounded-lg border border-border bg-inputBg px-3 py-3 text-sm text-primary"
                            placeholder="dein@email.de"
                            :aria-describedby="errors.email ? 'jobs-interest-email-error' : 'jobs-interest-email-help'"
                            :aria-invalid="Boolean(errors.email)"
                        >
                        <span id="jobs-interest-email-help" class="text-xs text-secondary">
                            Wir verwenden deine Adresse nur für diese Kontaktaufnahme.
                        </span>
                        <span v-if="errors.email" id="jobs-interest-email-error" class="mt-1 block text-xs text-error">
                            {{ errors.email }}
                        </span>
                    </label>
                </div>

                <label class="block">
                    <span class="text-xs font-semibold uppercase text-secondary">Telefon optional</span>
                    <input
                        id="jobs-interest-phone"
                        v-model="interestForm.phone"
                        autocomplete="tel"
                        inputmode="tel"
                        aria-describedby="jobs-interest-phone-help"
                        class="mt-1 w-full rounded-lg border border-border bg-inputBg px-3 py-3 text-sm text-primary"
                        placeholder="Telefonnummer"
                    >
                    <span id="jobs-interest-phone-help" class="text-xs text-secondary">
                        Optional: Gib eine Telefonnummer für eine direkte Kontaktaufnahme an.
                    </span>
                    <span v-if="errors.phone" class="mt-1 block text-xs text-error">{{ errors.phone }}</span>
                </label>

                <label class="block">
                    <span class="text-xs font-semibold uppercase text-secondary">Nachricht optional</span>
                    <textarea
                        id="jobs-interest-message"
                        v-model="interestForm.message"
                        rows="4"
                        class="mt-1 w-full rounded-lg border border-border bg-inputBg px-3 py-3 text-sm text-primary"
                        placeholder="Kurz vorstellen, Erfahrung nennen oder Rückfrage stellen."
                        aria-describedby="jobs-interest-message-help"
                    ></textarea>
                    <span id="jobs-interest-message-help" class="text-xs text-secondary">
                        Kurzer Hinweis, warum du dich für diese Rolle interessiert hast.
                    </span>
                    <span v-if="errors.message" class="mt-1 block text-xs text-error">{{ errors.message }}</span>
                </label>

                <div class="flex flex-col-reverse gap-2 sm:flex-row sm:justify-end">
                    <button
                        type="button"
                        class="rounded-lg border border-border px-4 py-3 text-sm font-semibold text-primary hover:bg-muted"
                        @click="closeInterestModal"
                        aria-label="Modal schließen"
                    >
                        Schließen
                    </button>
                    <button
                        type="submit"
                        class="rounded-lg bg-buttonPrimary px-4 py-3 text-sm font-semibold text-buttonTextPrimary disabled:opacity-60"
                        :disabled="isSubmittingInterest"
                        :aria-busy="isSubmittingInterest"
                        :aria-label="isSubmittingInterest ? 'Interesse wird gesendet' : 'Interesse senden'"
                    >
                        {{ isSubmittingInterest ? 'Wird gesendet…' : 'Interesse senden' }}
                    </button>
                </div>
            </form>
        </Modal>
    </div>
</template>

