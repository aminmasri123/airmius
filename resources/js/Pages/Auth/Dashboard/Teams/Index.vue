<script setup>
import AppLayout from '@/Components/Auth/Layouts/AppLayout.vue'
import Modal from '@/Components/Modal.vue'
import SearchableSelect from '@/Components/SearchableSelect.vue'
import { Head, Link, router, usePage } from '@inertiajs/vue3'
import { computed, ref } from 'vue'
import { usePermissions } from '@/composables/usePermissions'
import { useI18n } from 'vue-i18n'

const { can } = usePermissions()
const { t, te } = useI18n()

defineOptions({ layout: AppLayout })

const props = defineProps({
    clubs: Array,
    sports: { type: Array, default: () => [] },
    clubRoles: { type: Array, default: () => ['owner', 'admin', 'manager', 'member'] },
    teamRoles: { type: Array, default: () => ['Coach', 'Captain', 'Player'] },
    filters: { type: Object, default: () => ({}) },
})

const page = usePage()
const user = page.props.auth?.user

const showClubModal = ref(false)
const showTeamModal = ref(false)
const showFilterModal = ref(false)

const selectedClub = ref(null)
const openClubId = ref(null)
const editingClubId = ref(null)
const errors = computed(() => page.props.errors || {})

const clubCreateStep = ref(1)

const clubCreateSteps = [
    { number: 1, label: 'Basis' },
    { number: 2, label: 'Adresse' },
    { number: 3, label: 'Prüfen' },
]

const filtersForm = ref({
    search: props.filters.search || '',
    sport_type: props.filters.sport_type || '',
    location: props.filters.location || '',
})

const clubForm = ref({
    name: '',
    sport_type: '',
    is_official: false,
    official_club_number: '',
    country: user?.country || 'DE',
    street: '',
    house_number: '',
    postal_code: '',
    city: '',
    state: '',
})

const inviteForms = ref({})
const teamForms = ref({})
const clubEditForms = ref({})
const jobForms = ref({})
const editingJobId = ref(null)

const initials = (name) =>
    name?.split(' ').map(n => n[0]).join('').slice(0, 2).toUpperCase()

const sportLabel = (value) => {
    if (!value) return 'Sportart offen'

    const sport = props.sports.find((sport) => sport.slug === value || sport.name === value)
    const slug = sport?.slug || value
    const key = `sports.${slug}`

    return te(key) ? t(key) : (sport?.name || value)
}

const clubRoleLabel = (role) => ({
    owner: 'Owner',
    admin: 'Verein-Admin',
    manager: 'Manager',
    member: 'Mitglied',
}[role] || role)

const teamRoleLabel = (role) => ({
    Coach: 'Trainer',
    Captain: 'Kapitän',
    Player: 'Spieler',
}[role] || role)

const toggleClub = (club) => {
    openClubId.value = openClubId.value === club.id ? null : club.id
}

const openClubModal = () => {
    clubCreateStep.value = 1
    showClubModal.value = true
}

const closeClubModal = () => {
    showClubModal.value = false
    clubCreateStep.value = 1
}

const nextClubStep = () => {
    if (clubCreateStep.value < clubCreateSteps.length) {
        clubCreateStep.value++
    }
}

const prevClubStep = () => {
    if (clubCreateStep.value > 1) {
        clubCreateStep.value--
    }
}

const resetClubForm = () => {
    clubForm.value = {
        name: '',
        sport_type: '',
        is_official: false,
        official_club_number: '',
        country: user?.country || 'DE',
        street: '',
        house_number: '',
        postal_code: '',
        city: '',
        state: '',
    }

    clubCreateStep.value = 1
}

const openTeamModal = (club) => {
    if (club?.subscription_capabilities?.can_create_team === false) {
        return
    }

    selectedClub.value = club
    showTeamModal.value = true
}

const closeTeamModal = () => {
    showTeamModal.value = false
    selectedClub.value = null
}

const teamFormFor = (club) => {
    if (!club?.id) {
        return {
            club_id: '',
            name: '',
            sport_type: props.sports[0]?.slug || '',
        }
    }

    teamForms.value[club.id] ??= {
        club_id: club.id,
        name: '',
        sport_type: club.sport_type || props.sports[0]?.slug || '',
    }

    return teamForms.value[club.id]
}

const inviteFormFor = (team) => {
    inviteForms.value[team.id] ??= { email: '', role: 'Player' }
    return inviteForms.value[team.id]
}

const createClub = () => {
    router.post('/clubs', clubForm.value, {
        preserveScroll: true,
        onSuccess: () => {
            resetClubForm()
            closeClubModal()
        },
    })
}

const applyFilters = () => {
    router.get(route('auth.teams.index'), {
        search: filtersForm.value.search || undefined,
        sport_type: filtersForm.value.sport_type || undefined,
        location: filtersForm.value.location || undefined,
    }, {
        preserveState: true,
        replace: true,
        preserveScroll: true,
        onSuccess: () => {
            showFilterModal.value = false
        },
    })
}

const resetFilters = () => {
    filtersForm.value = {
        search: '',
        sport_type: '',
        location: '',
    }

    applyFilters()
}

const createTeam = () => {
    if (!selectedClub.value?.id) return

    const clubId = selectedClub.value.id

    router.post(route('auth.teams.store'), teamFormFor(selectedClub.value), {
        onSuccess: () => {
            teamForms.value[clubId] = {
                club_id: clubId,
                name: '',
                sport_type: selectedClub.value?.sport_type || props.sports[0]?.slug || '',
            }

            closeTeamModal()
        },
        preserveScroll: true,
    })
}

const inviteUser = (team) => {
    router.post(route('auth.teams.invite', team.id), inviteFormFor(team), {
        preserveScroll: true,
    })
}

const updateClubMemberRole = (club, member) => {
    router.put(route('auth.clubs.members.update', [club.id, member.id]), {
        role: member.pivot.role,
    }, {
        preserveScroll: true,
    })
}

const clubEditFormFor = (club) => {
    clubEditForms.value[club.id] ??= {
        name: club.name || '',
        sport_type: club.sport_type || '',
        country: club.country || user?.country || 'DE',
        street: club.street || '',
        house_number: club.house_number || '',
        postal_code: club.postal_code || '',
        city: club.city || '',
        state: club.state || '',
    }

    return clubEditForms.value[club.id]
}

const editClub = (club) => {
    editingClubId.value = club.id
    clubEditForms.value[club.id] = {
        name: club.name || '',
        sport_type: club.sport_type || '',
        country: club.country || user?.country || 'DE',
        street: club.street || '',
        house_number: club.house_number || '',
        postal_code: club.postal_code || '',
        city: club.city || '',
        state: club.state || '',
    }

    if (openClubId.value !== club.id) {
        openClubId.value = club.id
    }
}

const cancelClubEdit = () => {
    editingClubId.value = null
}

const updateClub = (club) => {
    router.put(route('auth.clubs.update', club.id), clubEditFormFor(club), {
        preserveScroll: true,
        onSuccess: () => {
            editingClubId.value = null
        },
    })
}

const updateTeamMemberRole = (team, member) => {
    router.put(route('auth.teams.members.update', [team.id, member.id]), {
        role: member.pivot.role,
    }, {
        preserveScroll: true,
    })
}

const deleteClub = (club) => {
    const message = `Verein "${club.name}" wirklich löschen? Dadurch werden auch alle Teams dieses Vereins gelöscht.`

    if (!confirm(message)) return

    router.delete(route('auth.clubs.destroy', club.id), {
        preserveScroll: true,
    })
}

const deleteTeam = (team) => {
    if (!confirm(`Team "${team.name}" wirklich löschen?`)) return

    router.delete(route('auth.teams.destroy', team.id), {
        preserveScroll: true,
    })
}

const emptyJobForm = () => ({
    title: '',
    type: 'volunteer',
    location: '',
    workload: '',
    employment_type: '',
    description: '',
    contact_email: '',
    application_url: '',
    is_published: true,
})

const jobFormFor = (club) => {
    jobForms.value[club.id] ??= emptyJobForm()
    return jobForms.value[club.id]
}

const resetJobForm = (club) => {
    jobForms.value[club.id] = emptyJobForm()
    editingJobId.value = null
}

const editJob = (club, job) => {
    editingJobId.value = job.id

    jobForms.value[club.id] = {
        title: job.title || '',
        type: job.type || 'volunteer',
        location: job.location || '',
        workload: job.workload || '',
        employment_type: job.employment_type || '',
        description: job.description || '',
        contact_email: job.contact_email || '',
        application_url: job.application_url || '',
        is_published: Boolean(job.is_published),
    }
}

const submitJob = (club) => {
    const options = {
        preserveScroll: true,
        onSuccess: () => resetJobForm(club),
    }

    editingJobId.value
        ? router.put(route('auth.organization-jobs.update', editingJobId.value), jobFormFor(club), options)
        : router.post(route('auth.clubs.jobs.store', club.id), jobFormFor(club), options)
}

const deleteJob = (job) => {
    if (!confirm(`Stelle "${job.title}" wirklich löschen?`)) return

    router.delete(route('auth.organization-jobs.destroy', job.id), {
        preserveScroll: true,
    })
}
</script>

<template>
    <Head title="Teams" />

    <div class="space-y-6">
        <!-- HEADER -->
        <div class="flex items-start justify-between gap-4">
            <div class="min-w-0">
                <h1 class="text-2xl font-bold text-primary">
                    {{ $t('Vereine & Teams') }}
                </h1>

                <p class="text-sm text-secondary">
                    {{ $t('Verwalte Teams, Mitglieder und Rollen') }}
                </p>
            </div>

            <!-- Mobile Plus Button -->
            <button
                v-if="can('club.create')"
                type="button"
                class="flex h-11 w-11 shrink-0 items-center justify-center rounded bg-buttonPrimary text-buttonTextPrimary shadow sm:hidden"
                @click="openClubModal"
                :aria-label="$t('Verein registrieren')"
            >
                <i class="las la-plus text-2xl"></i>
            </button>

            <!-- Desktop Button -->
            <button
                v-if="can('club.create')"
                type="button"
                class="hidden rounded-lg bg-buttonPrimary px-4 py-3 text-sm font-semibold text-buttonTextPrimary hover:bg-buttonPrimaryHover sm:inline-flex"
                @click="openClubModal"
            >
                + {{ $t('Verein registrieren') }}
            </button>
        </div>

        <!-- MOBILE FILTER SHORT BAR -->
        <!-- <form
            class="rounded-xl border border-border bg-card p-3 md:hidden"
            @submit.prevent="applyFilters"
        >
            <div class="flex gap-2">
                <input
                    v-model="filtersForm.search"
                    class="min-w-0 flex-1 rounded-lg border border-border bg-inputBg px-3 py-3 text-sm text-primary"
                    :placeholder="$t('Verein suchen')"
                >

                <button
                    type="submit"
                    class="flex h-11 w-11 shrink-0 items-center justify-center rounded-lg bg-buttonPrimary text-buttonTextPrimary"
                    :aria-label="$t('Suchen')"
                >
                    <i class="las la-search text-xl"></i>
                </button>

                <button
                    type="button"
                    class="relative flex h-11 w-11 shrink-0 items-center justify-center rounded-lg border border-border bg-inputBg text-primary"
                    @click="showFilterModal = true"
                    aria-label="Filter"
                >
                    <i class="las la-sliders-h text-xl"></i>

                    <span
                        v-if="filtersForm.sport_type || filtersForm.location"
                        class="absolute -right-1 -top-1 h-3 w-3 rounded-full bg-buttonPrimary"
                    ></span>
                </button>
            </div>
        </form> -->

        <!-- DESKTOP FILTER -->
        <!-- <form
            class="hidden gap-3 rounded-xl border border-border bg-card p-4 md:grid md:grid-cols-4"
            @submit.prevent="applyFilters"
        >
            <input
                v-model="filtersForm.search"
                class="rounded-lg border border-border bg-inputBg px-3 py-3 text-sm text-primary"
                :placeholder="$t('Verein suchen')"
            >

            <SearchableSelect
                v-model="filtersForm.sport_type"
                :options="sports"
                value-key="slug"
                translation-prefix="sports"
                category-translation-prefix="sport_categories"
                :placeholder="$t('Sportart suchen')"
            />

            <input
                v-model="filtersForm.location"
                class="rounded-lg border border-border bg-inputBg px-3 py-3 text-sm text-primary"
                :placeholder="$t('Ort, Stadt, PLZ oder Land')"
            >

            <button
                class="rounded-lg bg-buttonPrimary px-4 py-3 text-sm font-semibold text-buttonTextPrimary hover:bg-buttonPrimaryHover"
            >
                {{ $t('Suchen') }}
            </button>
        </form> -->

        <!-- CLUBS -->
        <div
            v-for="club in clubs"
            :key="club.id"
            class="space-y-4 rounded-xl border border-border bg-card p-4 sm:p-5"
        >
            <!-- CLUB HEADER -->
            <div
                class="flex cursor-pointer flex-col gap-3 sm:flex-row sm:items-center sm:justify-between"
                @click="toggleClub(club)"
            >
                <div class="flex min-w-0 items-center gap-3">
                    <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-full bg-muted text-sm font-bold">
                        {{ initials(club.name) }}
                    </div>

                    <div class="min-w-0">
                        <Link
                            :href="route('auth.clubs.show', club.id)"
                            class="font-semibold text-primary hover:underline"
                            @click.stop
                        >
                            {{ club.name }}
                        </Link>

                        <p class="break-words text-xs text-secondary">
                            {{ sportLabel(club.sport_type) }}
                            · {{ club.city || 'Ort offen' }} {{ club.postal_code || '' }}
                            · {{ club.country || user?.country || 'Land offen' }}
                            · {{ club.teams.length }} Teams
                        </p>
                    </div>
                </div>

                <div class="flex flex-wrap items-center gap-2 sm:justify-end">
                    <button
                        v-if="club.can_manage"
                        type="button"
                        class="rounded-lg border border-border px-3 py-2 text-sm text-primary hover:bg-muted"
                        @click.stop="editClub(club)"
                    >
                        Daten bearbeiten
                    </button>

                    <button
                        v-if="can('team.store') && club.can_manage"
                        type="button"
                        class="rounded-lg border border-border px-3 py-2 text-sm text-primary hover:bg-muted disabled:cursor-not-allowed disabled:opacity-50"
                        :disabled="club.subscription_capabilities?.can_create_team === false"
                        :title="club.subscription_capabilities?.can_create_team === false ? 'Teamlimit des aktuellen Plans erreicht' : ''"
                        @click.stop="openTeamModal(club)"
                    >
                        + Team
                    </button>

                    <button
                        v-if="club.can_delete"
                        type="button"
                        class="rounded-lg bg-error px-3 py-2 text-sm font-semibold text-white hover:opacity-90"
                        @click.stop="deleteClub(club)"
                    >
                        Löschen
                    </button>
                </div>
            </div>

            <!-- CLUB EDIT -->
            <form
                v-if="openClubId === club.id && editingClubId === club.id"
                class="rounded-xl border border-border bg-bg p-4"
                @submit.prevent="updateClub(club)"
            >
                <div class="mb-4 flex flex-col gap-2 sm:flex-row sm:items-end sm:justify-between">
                    <div>
                        <h2 class="font-semibold text-primary">Vereinsdaten bearbeiten</h2>
                        <p class="text-xs text-secondary">
                            Basisdaten, Adresse und Sportart pflegen. Offizielle Pruefung laeuft separat ueber Admin.
                        </p>
                    </div>
                    <span
                        class="w-fit rounded-full px-3 py-1 text-xs font-semibold"
                        :class="club.verification_status === 'verified'
                            ? 'bg-success/10 text-success'
                            : club.verification_status === 'rejected'
                                ? 'bg-error/10 text-error'
                                : 'bg-warning/10 text-warning'"
                    >
                        {{ club.verification_status === 'verified' ? 'Freigegeben' : club.verification_status === 'rejected' ? 'Abgelehnt' : 'Wartet auf Pruefung' }}
                    </span>
                </div>

                <div class="grid gap-3 md:grid-cols-2 xl:grid-cols-3">
                    <label class="block xl:col-span-2">
                        <span class="text-xs font-semibold uppercase text-secondary">Vereinsname</span>
                        <input v-model="clubEditFormFor(club).name" required class="mt-1 w-full rounded-lg border border-border bg-inputBg px-3 py-2 text-sm text-primary">
                    </label>

                    <label class="block">
                        <span class="text-xs font-semibold uppercase text-secondary">Sportart</span>
                        <SearchableSelect
                            v-model="clubEditFormFor(club).sport_type"
                            class="mt-1 w-full"
                            :options="sports"
                            value-key="slug"
                            translation-prefix="sports"
                            category-translation-prefix="sport_categories"
                            placeholder="Sportart suchen"
                        />
                    </label>

                    <label class="block">
                        <span class="text-xs font-semibold uppercase text-secondary">Land</span>
                        <select v-model="clubEditFormFor(club).country" required class="mt-1 w-full rounded-lg border border-border bg-inputBg px-3 py-2 text-sm text-primary">
                            <option value="DE">Deutschland</option>
                            <option value="AT">Oesterreich</option>
                            <option value="CH">Schweiz</option>
                            <option value="FR">Frankreich</option>
                            <option value="NL">Niederlande</option>
                            <option value="BE">Belgien</option>
                            <option value="TR">Tuerkei</option>
                            <option value="US">USA</option>
                        </select>
                    </label>

                    <label class="block">
                        <span class="text-xs font-semibold uppercase text-secondary">Stadt</span>
                        <input v-model="clubEditFormFor(club).city" class="mt-1 w-full rounded-lg border border-border bg-inputBg px-3 py-2 text-sm text-primary">
                    </label>

                    <label class="block">
                        <span class="text-xs font-semibold uppercase text-secondary">PLZ</span>
                        <input v-model="clubEditFormFor(club).postal_code" class="mt-1 w-full rounded-lg border border-border bg-inputBg px-3 py-2 text-sm text-primary">
                    </label>

                    <label class="block">
                        <span class="text-xs font-semibold uppercase text-secondary">Region</span>
                        <input v-model="clubEditFormFor(club).state" class="mt-1 w-full rounded-lg border border-border bg-inputBg px-3 py-2 text-sm text-primary">
                    </label>

                    <label class="block">
                        <span class="text-xs font-semibold uppercase text-secondary">Strasse</span>
                        <input v-model="clubEditFormFor(club).street" class="mt-1 w-full rounded-lg border border-border bg-inputBg px-3 py-2 text-sm text-primary">
                    </label>

                    <label class="block">
                        <span class="text-xs font-semibold uppercase text-secondary">Hausnummer</span>
                        <input v-model="clubEditFormFor(club).house_number" class="mt-1 w-full rounded-lg border border-border bg-inputBg px-3 py-2 text-sm text-primary">
                    </label>
                </div>

                <div class="mt-4 flex flex-col-reverse gap-2 sm:flex-row sm:justify-end">
                    <button type="button" class="rounded-lg border border-border px-4 py-2 text-sm font-semibold text-primary hover:bg-muted" @click="cancelClubEdit">
                        Abbrechen
                    </button>
                    <button class="rounded-lg bg-buttonPrimary px-4 py-2 text-sm font-semibold text-buttonTextPrimary">
                        Speichern
                    </button>
                </div>
            </form>

            <!-- TEAMS GRID -->
            <div
                v-if="openClubId === club.id"
                class="grid gap-4 md:grid-cols-2 xl:grid-cols-3"
            >
                <div
                    v-for="team in club.teams"
                    :key="team.id"
                    class="space-y-4 rounded-xl border border-border bg-bg p-4"
                >
                    <div class="flex items-start justify-between gap-3">
                        <div class="flex min-w-0 items-center gap-3">
                            <div class="flex h-9 w-9 shrink-0 items-center justify-center rounded-full bg-muted text-xs font-bold">
                                {{ initials(team.name) }}
                            </div>

                            <div class="min-w-0">
                                <Link
                                    :href="route('auth.teams.show', team.id)"
                                    class="font-semibold text-primary hover:underline"
                                >
                                    {{ team.name }}
                                </Link>

                                <p class="text-xs text-secondary">
                                    {{ team.users?.length || 0 }} Mitglieder
                                </p>
                            </div>
                        </div>

                        <div class="flex shrink-0 items-center gap-2">
                            <span class="rounded-full bg-muted px-2 py-1 text-xs text-secondary">
                                Aktiv
                            </span>

                            <button
                                v-if="team.can_delete"
                                type="button"
                                class="rounded bg-error px-2 py-1 text-xs font-semibold text-white hover:opacity-90"
                                @click="deleteTeam(team)"
                            >
                                Löschen
                            </button>
                        </div>
                    </div>

                    <div class="space-y-2">
                        <div
                            v-for="member in team.users"
                            :key="member.id"
                            class="flex items-center gap-3 rounded-lg p-2 hover:bg-muted"
                        >
                            <div class="flex h-8 w-8 shrink-0 items-center justify-center rounded-full bg-muted text-xs font-semibold">
                                {{ initials(member.name) }}
                            </div>

                            <div class="min-w-0 flex-1">
                                <p class="truncate text-sm font-medium text-primary">
                                    {{ member.name }}
                                </p>

                                <p class="text-xs text-secondary">
                                    {{ member.pivot.role }}
                                </p>
                            </div>

                            <select
                                v-if="team.can_manage"
                                v-model="member.pivot.role"
                                class="rounded border border-border bg-card px-2 py-1 text-xs text-primary"
                                @change="updateTeamMemberRole(team, member)"
                            >
                                <option
                                    v-for="role in teamRoles"
                                    :key="role"
                                    :value="role"
                                >
                                    {{ teamRoleLabel(role) }}
                                </option>
                            </select>

                            <span
                                v-else
                                class="rounded-full bg-muted px-2 py-1 text-xs text-secondary"
                            >
                                {{ teamRoleLabel(member.pivot.role) }}
                            </span>
                        </div>
                    </div>

                    <form
                        class="flex flex-col gap-2 sm:flex-row"
                        @submit.prevent="inviteUser(team)"
                    >
                        <input
                            v-model="inviteFormFor(team).email"
                            type="email"
                            placeholder="E-Mail"
                            class="min-w-0 flex-1 rounded border border-border bg-inputBg px-3 py-2 text-sm text-primary"
                        >

                        <button class="rounded bg-buttonPrimary px-3 py-2 text-sm text-buttonTextPrimary">
                            Einladen
                        </button>
                    </form>
                </div>
            </div>

            <!-- CLUB MEMBERS -->
            <div
                v-if="openClubId === club.id"
                class="rounded-xl border border-border bg-bg p-4"
            >
                <div class="mb-4 flex items-center justify-between gap-3">
                    <div>
                        <h2 class="font-semibold text-primary">
                            Vereinsmitglieder
                        </h2>

                        <p class="text-xs text-secondary">
                            Owner, Admins und Manager steuern die Rollen im Verein.
                        </p>
                    </div>

                    <span class="rounded-full bg-muted px-3 py-1 text-xs text-secondary">
                        {{ club.users?.length || 0 }} Mitglieder
                    </span>
                </div>

                <div class="grid gap-2 md:grid-cols-2">
                    <div
                        v-for="member in club.users"
                        :key="member.id"
                        class="flex items-center gap-3 rounded-lg border border-border bg-card p-3"
                    >
                        <img
                            v-if="member.profile_photo_thumb"
                            :src="member.profile_photo_thumb"
                            :alt="member.name"
                            class="h-9 w-9 shrink-0 rounded-full object-cover"
                        >

                        <div
                            v-else
                            class="flex h-9 w-9 shrink-0 items-center justify-center rounded-full bg-muted text-xs font-semibold text-primary"
                        >
                            {{ initials(member.name) }}
                        </div>

                        <div class="min-w-0 flex-1">
                            <p class="truncate text-sm font-medium text-primary">
                                {{ member.name }}
                            </p>

                            <p class="truncate text-xs text-secondary">
                                {{ member.email }}
                            </p>
                        </div>

                        <select
                            v-if="club.can_manage"
                            v-model="member.pivot.role"
                            class="rounded border border-border bg-inputBg px-2 py-1 text-xs text-primary"
                            @change="updateClubMemberRole(club, member)"
                        >
                            <option
                                v-for="role in clubRoles"
                                :key="role"
                                :value="role"
                            >
                                {{ clubRoleLabel(role) }}
                            </option>
                        </select>

                        <span
                            v-else
                            class="rounded-full bg-muted px-2 py-1 text-xs text-secondary"
                        >
                            {{ clubRoleLabel(member.pivot.role) }}
                        </span>
                    </div>
                </div>
            </div>

            <!-- JOBS -->
            <div
                v-if="openClubId === club.id"
                class="rounded-xl border border-border bg-bg p-4"
            >
                <div class="mb-4 flex items-center justify-between gap-3">
                    <div>
                        <h2 class="font-semibold text-primary">
                            Ehrenamt & Berufe
                        </h2>

                        <p class="text-xs text-secondary">
                            Offene Stellen dieser Organisation erscheinen nach Veröffentlichung auf der Webseite.
                        </p>
                    </div>

                    <span class="rounded-full bg-muted px-3 py-1 text-xs text-secondary">
                        {{ club.jobs?.length || 0 }} Stellen
                    </span>
                </div>

                <form
                    v-if="club.can_manage"
                    class="grid gap-3 rounded-lg border border-border bg-card p-4 md:grid-cols-2"
                    @submit.prevent="submitJob(club)"
                >
                    <input
                        v-model="jobFormFor(club).title"
                        required
                        class="rounded-lg border border-border bg-inputBg px-3 py-2 text-sm text-primary"
                        placeholder="Titel, z.B. Jugendtrainer U15"
                    >

                    <select
                        v-model="jobFormFor(club).type"
                        class="rounded-lg border border-border bg-inputBg px-3 py-2 text-sm text-primary"
                    >
                        <option value="volunteer">Ehrenamt</option>
                        <option value="professional">Beruf / bezahlte Stelle</option>
                    </select>

                    <input
                        v-model="jobFormFor(club).location"
                        class="rounded-lg border border-border bg-inputBg px-3 py-2 text-sm text-primary"
                        placeholder="Ort / Remote"
                    >

                    <input
                        v-model="jobFormFor(club).workload"
                        class="rounded-lg border border-border bg-inputBg px-3 py-2 text-sm text-primary"
                        placeholder="Umfang, z.B. 6 Std./Woche"
                    >

                    <input
                        v-model="jobFormFor(club).employment_type"
                        class="rounded-lg border border-border bg-inputBg px-3 py-2 text-sm text-primary"
                        placeholder="Art, z.B. Teilzeit, Minijob, Ehrenamt"
                    >

                    <input
                        v-model="jobFormFor(club).contact_email"
                        type="email"
                        class="rounded-lg border border-border bg-inputBg px-3 py-2 text-sm text-primary"
                        placeholder="Kontakt E-Mail"
                    >

                    <input
                        v-model="jobFormFor(club).application_url"
                        class="rounded-lg border border-border bg-inputBg px-3 py-2 text-sm text-primary md:col-span-2"
                        placeholder="Bewerbungslink, optional"
                    >

                    <textarea
                        v-model="jobFormFor(club).description"
                        required
                        rows="4"
                        class="rounded-lg border border-border bg-inputBg px-3 py-2 text-sm text-primary md:col-span-2"
                        placeholder="Beschreibung, Aufgaben, Voraussetzungen"
                    ></textarea>

                    <label class="flex items-center gap-2 text-sm text-primary">
                        <input
                            v-model="jobFormFor(club).is_published"
                            type="checkbox"
                            class="rounded border-border bg-inputBg"
                        >
                        Auf Webseite veröffentlichen
                    </label>

                    <div class="flex gap-2 md:justify-end">
                        <button
                            v-if="editingJobId"
                            type="button"
                            class="rounded-lg border border-border px-4 py-2 text-sm text-primary"
                            @click="resetJobForm(club)"
                        >
                            Abbrechen
                        </button>

                        <button class="rounded-lg bg-buttonPrimary px-4 py-2 text-sm font-semibold text-buttonTextPrimary">
                            {{ editingJobId ? 'Aktualisieren' : 'Stelle erstellen' }}
                        </button>
                    </div>
                </form>

                <div class="mt-4 grid gap-3 md:grid-cols-2">
                    <article
                        v-for="job in club.jobs"
                        :key="job.id"
                        class="rounded-lg border border-border bg-card p-4"
                    >
                        <div class="flex items-start justify-between gap-3">
                            <div>
                                <span
                                    class="rounded-full px-2 py-1 text-xs"
                                    :class="job.type === 'volunteer'
                                        ? 'bg-air-green/15 text-air-green'
                                        : 'bg-air-blue/15 text-air-blue'"
                                >
                                    {{ job.type === 'volunteer' ? 'Ehrenamt' : 'Beruf' }}
                                </span>

                                <h3 class="mt-3 font-semibold text-primary">
                                    {{ job.title }}
                                </h3>

                                <p class="mt-1 text-xs text-secondary">
                                    {{ job.location || 'Ort offen' }} · {{ job.workload || 'Umfang offen' }}
                                </p>
                            </div>

                            <span
                                class="rounded-full px-2 py-1 text-xs"
                                :class="job.is_published ? 'bg-air-green/15 text-air-green' : 'bg-muted text-secondary'"
                            >
                                {{ job.is_published ? 'Online' : 'Entwurf' }}
                            </span>
                        </div>

                        <p class="mt-3 line-clamp-3 text-sm text-secondary">
                            {{ job.description }}
                        </p>

                        <div v-if="club.can_manage" class="mt-4 flex gap-2">
                            <button
                                class="rounded border border-border px-3 py-1 text-sm text-primary"
                                @click="editJob(club, job)"
                            >
                                Bearbeiten
                            </button>

                            <button
                                class="rounded bg-error px-3 py-1 text-sm text-white"
                                @click="deleteJob(job)"
                            >
                                Löschen
                            </button>
                        </div>
                    </article>

                    <div
                        v-if="!club.jobs?.length"
                        class="rounded-lg border border-border bg-card p-4 text-sm text-secondary"
                    >
                        Noch keine Stellen für diese Organisation.
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- MOBILE FILTER MODAL -->
    <Teleport to="body">
        <div
            v-if="showFilterModal"
            class="fixed inset-0 z-[80] flex items-center justify-center bg-black/60"
            @click.self="showFilterModal = false"
        >
            <div class="flex h-full w-full flex-col bg-card sm:h-auto sm:max-h-[90vh] sm:max-w-lg sm:rounded-2xl sm:border sm:border-border sm:shadow-xl">
                <div class="shrink-0 border-b border-border bg-card p-4">
                    <div class="flex items-center justify-between gap-4">
                        <div>
                            <h2 class="text-lg font-semibold text-primary">
                                Filter
                            </h2>

                            <p class="mt-1 text-sm text-secondary">
                                Suche nach Vereinen, Sportart oder Ort.
                            </p>
                        </div>

                        <button
                            type="button"
                            class="rounded-lg border border-border px-3 py-1 text-secondary hover:border-borderHover hover:text-primary"
                            @click="showFilterModal = false"
                        >
                            ✕
                        </button>
                    </div>
                </div>

                <div class="min-h-0 flex-1 space-y-4 overflow-y-auto p-4">
                    <div>
                        <label class="block text-sm font-semibold text-primary">
                            Verein suchen
                        </label>

                        <input
                            v-model="filtersForm.search"
                            class="mt-1 w-full rounded-lg border border-border bg-inputBg px-3 py-3 text-sm text-primary"
                            :placeholder="$t('Verein suchen')"
                        >
                    </div>

                    <div>
                        <label class="block text-sm font-semibold text-primary">
                            Sportart
                        </label>

                        <SearchableSelect
                            v-model="filtersForm.sport_type"
                            class="mt-1"
                            :options="sports"
                            value-key="slug"
                            translation-prefix="sports"
                            category-translation-prefix="sport_categories"
                            :placeholder="$t('Sportart suchen')"
                        />
                    </div>

                    <div>
                        <label class="block text-sm font-semibold text-primary">
                            Ort
                        </label>

                        <input
                            v-model="filtersForm.location"
                            class="mt-1 w-full rounded-lg border border-border bg-inputBg px-3 py-3 text-sm text-primary"
                            :placeholder="$t('Ort, Stadt, PLZ oder Land')"
                        >
                    </div>
                </div>

                <div class="shrink-0 border-t border-border bg-card p-4">
                    <div class="flex gap-3">
                        <button
                            type="button"
                            class="flex-1 rounded-lg border border-border px-4 py-3 font-semibold text-secondary hover:border-borderHover hover:text-primary"
                            @click="resetFilters"
                        >
                            Zurücksetzen
                        </button>

                        <button
                            type="button"
                            class="flex-1 rounded-lg bg-buttonPrimary px-4 py-3 font-semibold text-buttonTextPrimary hover:bg-buttonPrimaryHover"
                            @click="applyFilters"
                        >
                            Anwenden
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </Teleport>

    <!-- CLUB CREATE WIZARD MODAL -->
    <Teleport to="body">
        <div
            v-if="showClubModal"
            class="fixed inset-0 z-[80] flex items-center justify-center bg-black/60"
            @click.self="closeClubModal"
        >
            <div class="flex h-full w-full flex-col bg-card sm:h-auto sm:max-h-[92vh] sm:max-w-2xl sm:rounded-2xl sm:border sm:border-border sm:shadow-xl">
                <div class="shrink-0 border-b border-border bg-card p-4">
                    <div class="flex items-start justify-between gap-4">
                        <div class="min-w-0">
                            <h2 class="truncate text-lg font-semibold text-primary">
                                {{ $t('Verein registrieren') }}
                            </h2>

                            <p class="mt-1 text-sm text-secondary">
                                Schritt {{ clubCreateStep }} von {{ clubCreateSteps.length }}
                            </p>
                        </div>

                        <button
                            type="button"
                            class="shrink-0 rounded-lg border border-border px-3 py-1 text-secondary hover:border-borderHover hover:text-primary"
                            @click="closeClubModal"
                        >
                            ✕
                        </button>
                    </div>

                    <div class="mt-4 grid grid-cols-3 gap-2">
                        <button
                            v-for="step in clubCreateSteps"
                            :key="step.number"
                            type="button"
                            class="rounded-full px-2 py-2 text-xs font-semibold transition"
                            :class="clubCreateStep === step.number
                                ? 'bg-buttonPrimary text-buttonTextPrimary'
                                : clubCreateStep > step.number
                                    ? 'bg-air-green/15 text-air-green'
                                    : 'bg-inputBg text-secondary'"
                            @click="clubCreateStep = step.number"
                        >
                            {{ step.label }}
                        </button>
                    </div>
                </div>

                <div class="min-h-0 flex-1 overflow-y-auto p-4">
                    <section v-show="clubCreateStep === 1" class="space-y-4">
                        <div>
                            <h3 class="text-base font-semibold text-primary">
                                Basisdaten
                            </h3>

                            <p class="mt-1 text-sm text-secondary">
                                Name, Sportart und Land des Vereins. Nach dem Absenden prueft Airmius den Antrag.
                            </p>
                        </div>

                        <div>
                            <label class="block text-sm font-semibold text-primary">
                                Vereinsname
                            </label>

                            <input
                                v-model="clubForm.name"
                                class="mt-1 w-full rounded-lg border border-border bg-inputBg px-3 py-3 text-sm text-primary"
                                placeholder="Vereinsname"
                            >
                        </div>

                        <div>
                            <label class="block text-sm font-semibold text-primary">
                                Sportart
                            </label>

                            <SearchableSelect
                                v-model="clubForm.sport_type"
                                class="mt-1 w-full"
                                :options="sports"
                                value-key="slug"
                                translation-prefix="sports"
                                category-translation-prefix="sport_categories"
                                placeholder="Sportart suchen"
                            />
                        </div>

                        <label class="flex items-start gap-3 rounded-lg border border-border bg-bg p-3 text-sm text-primary">
                            <input
                                v-model="clubForm.is_official"
                                type="checkbox"
                                class="mt-1 rounded border-border bg-inputBg"
                            >
                            <span>
                                <span class="block font-semibold">Offizielle Pruefung beantragen</span>
                                <span class="block text-secondary">Der Verein wird erst nach Admin-Freigabe oeffentlich sichtbar und als offiziell markiert.</span>
                            </span>
                        </label>

                        <div v-if="clubForm.is_official">
                            <label class="block text-sm font-semibold text-primary">
                                Vereinsnummer zur Pruefung
                            </label>

                            <input
                                v-model="clubForm.official_club_number"
                                class="mt-1 w-full rounded-lg border border-border bg-inputBg px-3 py-3 text-sm text-primary"
                                placeholder="z. B. Vereinsregister- oder Verbandsnummer"
                            >
                        </div>

                        <div>
                            <label class="block text-sm font-semibold text-primary">
                                Land
                            </label>

                            <select
                                v-model="clubForm.country"
                                class="mt-1 w-full rounded-lg border border-border bg-inputBg px-3 py-3 text-sm text-primary"
                                required
                            >
                                <option value="DE">Deutschland</option>
                                <option value="AT">Österreich</option>
                                <option value="CH">Schweiz</option>
                                <option value="FR">Frankreich</option>
                                <option value="NL">Niederlande</option>
                                <option value="BE">Belgien</option>
                                <option value="TR">Türkei</option>
                                <option value="US">USA</option>
                            </select>
                        </div>
                    </section>

                    <section v-show="clubCreateStep === 2" class="space-y-4">
                        <div>
                            <h3 class="text-base font-semibold text-primary">
                                Adresse
                            </h3>

                            <p class="mt-1 text-sm text-secondary">
                                Optional: Standort des Vereins eintragen.
                            </p>
                        </div>

                        <div class="grid grid-cols-1 gap-3 sm:grid-cols-2">
                            <input v-model="clubForm.city" class="rounded-lg border border-border bg-inputBg px-3 py-3 text-sm text-primary" placeholder="Stadt">
                            <input v-model="clubForm.postal_code" class="rounded-lg border border-border bg-inputBg px-3 py-3 text-sm text-primary" placeholder="PLZ">
                            <input v-model="clubForm.state" class="rounded-lg border border-border bg-inputBg px-3 py-3 text-sm text-primary" placeholder="Region">
                            <input v-model="clubForm.street" class="rounded-lg border border-border bg-inputBg px-3 py-3 text-sm text-primary" placeholder="Straße">
                            <input v-model="clubForm.house_number" class="rounded-lg border border-border bg-inputBg px-3 py-3 text-sm text-primary" placeholder="Hausnummer">
                        </div>
                    </section>

                    <section v-show="clubCreateStep === 3" class="space-y-4">
                        <div>
                            <h3 class="text-base font-semibold text-primary">
                                Prüfen
                            </h3>

                            <p class="mt-1 text-sm text-secondary">
                                Kontrolliere die Angaben vor dem Absenden. Der Verein wird als Antrag gespeichert.
                            </p>
                        </div>

                        <div class="rounded-xl border border-border bg-inputBg p-4">
                            <div class="space-y-3 text-sm">
                                <p><strong>Verein:</strong> {{ clubForm.name || '-' }}</p>
                                <p><strong>Sportart:</strong> {{ sportLabel(clubForm.sport_type) }}</p>
                                <p><strong>Offizielle Pruefung:</strong> {{ clubForm.is_official ? 'Beantragt' : 'Nicht beantragt' }}</p>
                                <p v-if="clubForm.is_official"><strong>Vereinsnummer zur Pruefung:</strong> {{ clubForm.official_club_number || '-' }}</p>
                                <p><strong>Status nach Absenden:</strong> Wartet auf Pruefung</p>
                                <p><strong>Land:</strong> {{ clubForm.country || '-' }}</p>
                                <p>
                                    <strong>Adresse:</strong>
                                    {{ clubForm.street || '-' }}
                                    {{ clubForm.house_number || '' }},
                                    {{ clubForm.postal_code || '' }}
                                    {{ clubForm.city || '' }}
                                </p>
                                <p><strong>Region:</strong> {{ clubForm.state || '-' }}</p>
                            </div>
                        </div>
                    </section>
                </div>

                <div class="shrink-0 border-t border-border bg-card p-4">
                    <div class="flex gap-3">
                        <button
                            type="button"
                            class="flex-1 rounded-lg border border-border px-4 py-3 font-semibold text-secondary hover:border-borderHover hover:text-primary disabled:opacity-50"
                            :disabled="clubCreateStep === 1"
                            @click="prevClubStep"
                        >
                            Zurück
                        </button>

                        <button
                            v-if="clubCreateStep < clubCreateSteps.length"
                            type="button"
                            class="flex-1 rounded-lg bg-buttonPrimary px-4 py-3 font-semibold text-buttonTextPrimary hover:bg-buttonPrimaryHover"
                            @click="nextClubStep"
                        >
                            Weiter
                        </button>

                        <button
                            v-else
                            type="button"
                            class="flex-1 rounded-lg bg-buttonPrimary px-4 py-3 font-semibold text-buttonTextPrimary hover:bg-buttonPrimaryHover"
                            @click="createClub"
                        >
                            Speichern
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </Teleport>

    <!-- TEAM MODAL -->
    <Modal :show="showTeamModal" @close="closeTeamModal">
        <div v-if="selectedClub" class="space-y-4">
            <h2 class="font-bold text-primary">
                Team erstellen
            </h2>

            <input
                v-model="teamFormFor(selectedClub).name"
                class="w-full rounded border border-border bg-inputBg p-3 text-primary"
                placeholder="Teamname"
            >

            <p v-if="errors.name" class="text-sm text-error">
                {{ errors.name }}
            </p>

            <SearchableSelect
                v-model="teamFormFor(selectedClub).sport_type"
                :options="sports"
                value-key="slug"
                translation-prefix="sports"
                category-translation-prefix="sport_categories"
                placeholder="Sportart suchen"
            />

            <p v-if="errors.club_id" class="text-sm text-error">
                {{ errors.club_id }}
            </p>

            <p v-if="errors.sport_type" class="text-sm text-error">
                {{ errors.sport_type }}
            </p>

            <button
                @click="createTeam"
                class="w-full rounded bg-buttonPrimary py-3 text-buttonTextPrimary"
            >
                Erstellen
            </button>
        </div>
    </Modal>
</template>
