<script setup>
import Nav from '@/Components/Guest/Nav.vue'
import Subnav from '@/Components/Guest/Subnav.vue'
import Footer from '@/Components/Guest/Footer.vue'
import SeoHead from '@/Components/Guest/SeoHead.vue'
import Modal from '@/Components/Modal.vue'
import SearchableSelect from '@/Components/SearchableSelect.vue'
import { router, usePage } from '@inertiajs/vue3'
import { computed, ref } from 'vue'
import { useI18n } from 'vue-i18n'

const props = defineProps({
    canLogin: Boolean,
    canRegister: Boolean,
    jobs: { type: Array, default: () => [] },
    sports: { type: Array, default: () => [] },
    filters: { type: Object, default: () => ({}) },
})

const page = usePage()
const { t, te } = useI18n()
const user = computed(() => page.props.auth?.user)
const errors = computed(() => page.props.errors || {})
const selectedJob = ref(null)
const interestNotice = ref(null)
const filterForm = ref({
    sport_type: props.filters.sport_type || '',
    address: props.filters.address || '',
})
const interestForm = ref({
    name: '',
    email: '',
    phone: '',
    message: '',
})

const values = [
    ['las la-heart', 'guest.jobs.values.sport.title', 'guest.jobs.values.sport.text'],
    ['las la-rocket', 'guest.jobs.values.ownership.title', 'guest.jobs.values.ownership.text'],
    ['las la-users', 'guest.jobs.values.team.title', 'guest.jobs.values.team.text'],
]

const hasActiveFilters = computed(() => Boolean(filterForm.value.sport_type || filterForm.value.address))

const sportLabel = (value) => {
    if (!value) return 'Sportart offen'

    const sport = props.sports.find((sport) => sport.slug === value || sport.name === value)
    const slug = sport?.slug || value
    const key = `sports.${slug}`

    return te(key) ? t(key) : (sport?.name || value)
}

const clubAddress = (club) => {
    const cityLine = [club?.postal_code, club?.city].filter(Boolean).join(' ')
    const streetLine = [club?.street, club?.house_number].filter(Boolean).join(' ')

    return [streetLine, cityLine, club?.country].filter(Boolean).join(', ')
}

const applyFilters = () => {
    router.get(route('guest.jobs'), {
        sport_type: filterForm.value.sport_type || undefined,
        address: filterForm.value.address || undefined,
    }, {
        preserveState: true,
        preserveScroll: true,
        replace: true,
    })
}

const resetFilters = () => {
    filterForm.value = {
        sport_type: '',
        address: '',
    }

    applyFilters()
}

const openInterestModal = (job) => {
    selectedJob.value = job
    interestNotice.value = null
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
    if (!selectedJob.value) return

    interestNotice.value = null

    router.post(route('guest.jobs.interest', selectedJob.value.id), interestForm.value, {
        preserveScroll: true,
        onSuccess: () => {
            interestNotice.value = {
                type: 'success',
                message: 'Dein Interesse wurde gesendet.',
            }
            interestForm.value = {
                name: user.value?.name || '',
                email: user.value?.email || '',
                phone: '',
                message: '',
            }
        },
        onError: () => {
            interestNotice.value = {
                type: 'error',
                message: 'Bitte prüfe deine Angaben.',
            }
        },
    })
}
</script>

<template>
    <SeoHead
        title="Jobs im Sport"
        description="Finde Jobs, Ehrenamt und Vereinsrollen im Sport. Airmius verbindet Vereine, Organisationen und Menschen, die den Sport gestalten wollen."
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
                    <h2 class="text-2xl font-bold text-primary">{{ $t('guest.jobs.open_roles') }}</h2>
                    <p class="mt-2 text-secondary">{{ $t('guest.jobs.open_roles_text') }}</p>
                </div>

                <form
                    class="mb-6 grid gap-3 rounded-xl border border-border bg-card p-4 md:grid-cols-[minmax(0,1fr)_minmax(0,1fr)_auto]"
                    @submit.prevent="applyFilters"
                >
                    <label class="block">
                        <span class="text-xs font-semibold uppercase text-secondary">Sportart</span>
                        <SearchableSelect
                            v-model="filterForm.sport_type"
                            class="mt-1 w-full"
                            :options="sports"
                            value-key="slug"
                            translation-prefix="sports"
                            category-translation-prefix="sport_categories"
                            placeholder="Sportart suchen"
                        />
                    </label>

                    <label class="block">
                        <span class="text-xs font-semibold uppercase text-secondary">Adresse / Ort</span>
                        <input
                            v-model="filterForm.address"
                            class="mt-1 w-full rounded-lg border border-border bg-inputBg px-3 py-3 text-sm text-primary"
                            placeholder="Stadt, PLZ, Land oder Adresse"
                        >
                    </label>

                    <div class="flex flex-col gap-2 md:justify-end">
                        <button class="rounded-lg bg-buttonPrimary px-4 py-3 text-sm font-semibold text-buttonTextPrimary">
                            Filtern
                        </button>
                        <button
                            v-if="hasActiveFilters"
                            type="button"
                            class="rounded-lg border border-border px-4 py-3 text-sm font-semibold text-primary hover:bg-muted"
                            @click="resetFilters"
                        >
                            Zurücksetzen
                        </button>
                    </div>
                </form>

                <div class="space-y-3">
                    <article v-for="job in jobs" :key="job.id" class="surface-card flex flex-col gap-4 p-5 sm:flex-row sm:items-start sm:justify-between">
                        <div>
                            <div class="mb-2 flex flex-wrap items-center gap-2">
                                <span class="rounded-full px-2 py-1 text-xs font-semibold" :class="job.type === 'volunteer' ? 'bg-air-green/15 text-air-green' : 'bg-air-blue/15 text-air-blue'">
                                    {{ job.type === 'volunteer' ? 'Ehrenamt' : 'Beruf' }}
                                </span>
                                <span class="rounded-full bg-inputBg px-2 py-1 text-xs font-semibold text-secondary">
                                    {{ sportLabel(job.club?.sport_type) }}
                                </span>
                                <span class="text-xs text-secondary">{{ job.club?.name }}</span>
                            </div>
                            <h3 class="text-lg font-bold text-primary">{{ job.title }}</h3>
                            <p class="mt-1 text-sm text-secondary">
                                {{ job.location || 'Ort offen' }} · {{ job.workload || 'Umfang offen' }} · {{ job.employment_type || 'Flexibel' }}
                            </p>
                            <p v-if="clubAddress(job.club)" class="mt-1 text-xs text-secondary">
                                {{ clubAddress(job.club) }}
                            </p>
                            <p class="mt-3 max-w-2xl whitespace-pre-line text-sm leading-relaxed text-secondary">{{ job.description }}</p>
                        </div>
                        <button
                            type="button"
                            class="rounded-full border border-border px-4 py-2 text-center text-sm font-semibold text-primary transition hover:bg-muted"
                            @click="openInterestModal(job)"
                        >
                            {{ $t('guest.jobs.apply') }}
                        </button>
                    </article>

                    <div v-if="!jobs.length" class="surface-card p-8 text-center text-sm text-secondary">
                        Aktuell sind noch keine offenen Stellen veröffentlicht.
                    </div>
                </div>
            </section>
        </main>

        <Footer />

        <Modal :show="Boolean(selectedJob)" max-width="lg" @close="closeInterestModal">
            <form v-if="selectedJob" class="space-y-5" @submit.prevent="submitInterest">
                <div>
                    <p class="text-xs font-semibold uppercase tracking-wide text-secondary">
                        {{ selectedJob.club?.name }}
                    </p>
                    <h2 class="mt-1 text-xl font-bold text-primary">
                        Interesse melden
                    </h2>
                    <p class="mt-2 text-sm text-secondary">
                        Deine Angaben werden an den Verein weitergeleitet. Du musst dafür nicht angemeldet sein.
                    </p>
                </div>

                <div
                    v-if="interestNotice"
                    class="rounded-lg border px-4 py-3 text-sm"
                    :class="interestNotice.type === 'success'
                        ? 'border-air-green/30 bg-air-green/10 text-air-green'
                        : 'border-error/30 bg-error/10 text-error'"
                >
                    {{ interestNotice.message }}
                </div>

                <div class="rounded-lg border border-border bg-inputBg p-3 text-sm text-secondary">
                    <strong class="text-primary">{{ selectedJob.title }}</strong>
                    <span class="block">
                        {{ selectedJob.location || 'Ort offen' }} · {{ selectedJob.workload || 'Umfang offen' }}
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
                        class="mt-3 inline-flex rounded-lg border border-border bg-card px-3 py-2 text-sm font-semibold text-primary hover:bg-muted"
                    >
                        Extern bewerben
                    </a>
                </div>

                <div class="grid gap-3 sm:grid-cols-2">
                    <label class="block">
                        <span class="text-xs font-semibold uppercase text-secondary">Name *</span>
                        <input
                            v-model="interestForm.name"
                            required
                            class="mt-1 w-full rounded-lg border border-border bg-inputBg px-3 py-3 text-sm text-primary"
                            placeholder="Dein Name"
                        >
                        <span v-if="errors.name" class="mt-1 block text-xs text-error">{{ errors.name }}</span>
                    </label>

                    <label class="block">
                        <span class="text-xs font-semibold uppercase text-secondary">E-Mail *</span>
                        <input
                            v-model="interestForm.email"
                            required
                            type="email"
                            class="mt-1 w-full rounded-lg border border-border bg-inputBg px-3 py-3 text-sm text-primary"
                            placeholder="dein@email.de"
                        >
                        <span v-if="errors.email" class="mt-1 block text-xs text-error">{{ errors.email }}</span>
                    </label>
                </div>

                <label class="block">
                    <span class="text-xs font-semibold uppercase text-secondary">Telefon optional</span>
                    <input
                        v-model="interestForm.phone"
                        class="mt-1 w-full rounded-lg border border-border bg-inputBg px-3 py-3 text-sm text-primary"
                        placeholder="Telefonnummer"
                    >
                    <span v-if="errors.phone" class="mt-1 block text-xs text-error">{{ errors.phone }}</span>
                </label>

                <label class="block">
                    <span class="text-xs font-semibold uppercase text-secondary">Nachricht optional</span>
                    <textarea
                        v-model="interestForm.message"
                        rows="4"
                        class="mt-1 w-full rounded-lg border border-border bg-inputBg px-3 py-3 text-sm text-primary"
                        placeholder="Kurz vorstellen, Erfahrung nennen oder Rückfrage stellen."
                    ></textarea>
                    <span v-if="errors.message" class="mt-1 block text-xs text-error">{{ errors.message }}</span>
                </label>

                <div class="flex flex-col-reverse gap-2 sm:flex-row sm:justify-end">
                    <button
                        type="button"
                        class="rounded-lg border border-border px-4 py-3 text-sm font-semibold text-primary hover:bg-muted"
                        @click="closeInterestModal"
                    >
                        Schließen
                    </button>
                    <button class="rounded-lg bg-buttonPrimary px-4 py-3 text-sm font-semibold text-buttonTextPrimary">
                        Interesse senden
                    </button>
                </div>
            </form>
        </Modal>
    </div>
</template>
