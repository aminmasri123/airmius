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
const selectedClub = ref(null)
const openClubId = ref(null)
const errors = computed(() => page.props.errors || {})

const filtersForm = ref({
    search: props.filters.search || '',
    sport_type: props.filters.sport_type || '',
    location: props.filters.location || '',
})
const clubForm = ref({
    name: '',
    sport_type: '',
    country: user?.country || 'DE',
    street: '',
    house_number: '',
    postal_code: '',
    city: '',
    state: '',
})
const inviteForms = ref({})
const teamForms = ref({})
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

const toggleClub = (club) =>
    openClubId.value = openClubId.value === club.id ? null : club.id

const openTeamModal = (club) => {
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
        onSuccess: () => {
            clubForm.value = {
                name: '',
                sport_type: '',
                country: user?.country || 'DE',
                street: '',
                house_number: '',
                postal_code: '',
                city: '',
                state: '',
            }
            showClubModal.value = false
        }
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
    })
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
    router.post(route('auth.teams.invite', team.id), inviteFormFor(team))
}

const updateClubMemberRole = (club, member) => {
    router.put(route('auth.clubs.members.update', [club.id, member.id]), {
        role: member.pivot.role,
    }, {
        preserveScroll: true,
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
    if (!confirm(`Stelle "${job.title}" wirklich loeschen?`)) return
    router.delete(route('auth.organization-jobs.destroy', job.id), { preserveScroll: true })
}
</script>

<template>
<Head title="Teams" />

<div class="space-y-6">

    <!-- HEADER -->
    <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-4">
        <div>
            <h1 class="text-2xl font-bold text-primary">{{ $t('Vereine & Teams') }}</h1>
            <p class="text-sm text-secondary">{{ $t('Verwalte Teams, Mitglieder und Rollen') }}</p>
        </div>

        <button v-if="can('club.create')"
            @click="showClubModal = true"
            class="rounded-lg bg-buttonPrimary text-buttonTextPrimary px-4 py-2 text-sm font-semibold hover:bg-buttonPrimaryHover"
        >
           + {{ $t('Verein erstellen') }}
        </button>
    </div>

    <form class="grid gap-3 rounded-xl border border-border bg-card p-4 md:grid-cols-4" @submit.prevent="applyFilters">
        <input v-model="filtersForm.search" class="rounded-lg border border-border bg-inputBg px-3 py-2 text-sm text-primary" :placeholder="$t('Verein suchen')" />
        <SearchableSelect v-model="filtersForm.sport_type" :options="sports" value-key="slug" translation-prefix="sports" category-translation-prefix="sport_categories" :placeholder="$t('Sportart suchen')" />
        <input v-model="filtersForm.location" class="rounded-lg border border-border bg-inputBg px-3 py-2 text-sm text-primary" :placeholder="$t('Ort, Stadt, PLZ oder Land')" />
        <button class="rounded-lg bg-buttonPrimary text-buttonTextPrimary px-4 py-2 text-sm font-semibold hover:bg-buttonPrimaryHover">{{ $t('Suchen') }}</button>
    </form>

    <!-- CLUBS -->
    <div v-for="club in clubs" :key="club.id" class="rounded-xl border bg-card p-5 space-y-4">

        <!-- CLUB HEADER -->
        <div class="flex items-center justify-between cursor-pointer" @click="toggleClub(club)">
            <div class="flex items-center gap-3">
                <div class="h-10 w-10 flex items-center justify-center rounded-full bg-muted text-sm font-bold">
                    {{ initials(club.name) }}
                </div>

                <div>
                    <Link :href="route('auth.clubs.show', club.id)" class="font-semibold hover:underline">
                        {{ club.name }}
                    </Link>
                    <p class="text-xs text-secondary">
                        {{ sportLabel(club.sport_type) }} · {{ club.city || 'Ort offen' }} {{ club.postal_code || '' }} · {{ club.country || user?.country || 'Land offen' }} · {{ club.teams.length }} Teams
                    </p>
                </div>
            </div>

            <div class="flex items-center gap-2">
                <button
                    v-if="can('team.store') && club.can_manage"
                    @click.stop="openTeamModal(club)"
                    class="text-sm px-3 py-1 rounded-lg border hover:bg-muted"
                >
                    + Team
                </button>
                <button
                    v-if="club.can_delete"
                    type="button"
                    class="rounded-lg bg-error px-3 py-1 text-sm font-semibold text-white hover:opacity-90"
                    @click.stop="deleteClub(club)"
                >
                    Löschen
                </button>
            </div>
        </div>

        <!-- TEAMS GRID -->
        <div v-if="openClubId === club.id" class="grid md:grid-cols-2 xl:grid-cols-3 gap-4">

            <div v-for="team in club.teams" :key="team.id" class="rounded-xl border bg-bg p-4 space-y-4">

                <!-- TEAM HEADER -->
                <div class="flex justify-between items-start">
                    <div class="flex items-center gap-3">
                        <div class="h-9 w-9 flex items-center justify-center rounded-full bg-muted text-xs font-bold">
                            {{ initials(team.name) }}
                        </div>

                        <div>
                            <Link :href="route('auth.teams.show', team.id)" class="font-semibold hover:underline">
                                {{ team.name }}
                            </Link>
                            <p class="text-xs text-secondary">
                                {{ team.users?.length || 0 }} Mitglieder
                            </p>
                        </div>
                    </div>

                    <div class="flex items-center gap-2">
                        <span class="text-xs bg-muted px-2 py-1 rounded-full">
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

                <!-- MEMBERS -->
                <div class="space-y-2">
                    <div
                        v-for="member in team.users"
                        :key="member.id"
                        class="flex items-center gap-3 p-2 rounded-lg hover:bg-muted"
                    >
                        <div class="h-8 w-8 flex items-center justify-center rounded-full bg-muted text-xs font-semibold">
                            {{ initials(member.name) }}
                        </div>

                        <div class="flex-1 min-w-0">
                            <p class="truncate text-sm font-medium">
                                {{ member.name }}
                            </p>
                            <p class="text-xs text-secondary">
                                {{ member.pivot.role }}
                            </p>
                        </div>

                        <select
                            v-if="team.can_manage"
                            v-model="member.pivot.role"
                            @change="updateTeamMemberRole(team, member)"
                            class="bg-card text-xs border rounded px-2 py-1"
                        >
                            <option v-for="role in teamRoles" :key="role" :value="role">
                                {{ teamRoleLabel(role) }}
                            </option>
                        </select>
                        <span v-else class="rounded-full bg-muted px-2 py-1 text-xs text-secondary">
                            {{ teamRoleLabel(member.pivot.role) }}
                        </span>
                    </div>
                </div>

                <!-- INVITE -->
                <form @submit.prevent="inviteUser(team)" class="flex gap-2">
                    <input
                        v-model="inviteFormFor(team).email"
                        type="email"
                        placeholder="E-Mail"
                        class="bg-inputBg flex-1 border rounded px-2 py-1 text-sm"
                    >

                    <button class="bg-buttonPrimary text-buttonTextPrimary px-3 rounded">
                        Einladen
                    </button>
                </form>

            </div>
        </div>

        <div v-if="openClubId === club.id" class="rounded-xl border border-border bg-bg p-4">
            <div class="mb-4 flex items-center justify-between gap-3">
                <div>
                    <h2 class="font-semibold text-primary">Vereinsmitglieder</h2>
                    <p class="text-xs text-secondary">Owner, Admins und Manager steuern die Rollen im Verein.</p>
                </div>
                <span class="rounded-full bg-muted px-3 py-1 text-xs text-secondary">{{ club.users?.length || 0 }} Mitglieder</span>
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
                        class="h-9 w-9 rounded-full object-cover"
                    >
                    <div v-else class="flex h-9 w-9 items-center justify-center rounded-full bg-muted text-xs font-semibold text-primary">
                        {{ initials(member.name) }}
                    </div>

                    <div class="min-w-0 flex-1">
                        <p class="truncate text-sm font-medium text-primary">{{ member.name }}</p>
                        <p class="truncate text-xs text-secondary">{{ member.email }}</p>
                    </div>

                    <select
                        v-if="club.can_manage"
                        v-model="member.pivot.role"
                        class="rounded border border-border bg-inputBg px-2 py-1 text-xs text-primary"
                        @change="updateClubMemberRole(club, member)"
                    >
                        <option v-for="role in clubRoles" :key="role" :value="role">
                            {{ clubRoleLabel(role) }}
                        </option>
                    </select>
                    <span v-else class="rounded-full bg-muted px-2 py-1 text-xs text-secondary">
                        {{ clubRoleLabel(member.pivot.role) }}
                    </span>
                </div>
            </div>
        </div>

        <div v-if="openClubId === club.id" class="rounded-xl border border-border bg-bg p-4">
            <div class="mb-4 flex items-center justify-between gap-3">
                <div>
                    <h2 class="font-semibold text-primary">Ehrenamt & Berufe</h2>
                    <p class="text-xs text-secondary">Offene Stellen dieser Organisation erscheinen nach Veröffentlichung auf der Webseite.</p>
                </div>
                <span class="rounded-full bg-muted px-3 py-1 text-xs text-secondary">{{ club.jobs?.length || 0 }} Stellen</span>
            </div>

            <form v-if="club.can_manage" class="grid gap-3 rounded-lg border border-border bg-card p-4 md:grid-cols-2" @submit.prevent="submitJob(club)">
                <input v-model="jobFormFor(club).title" required class="rounded-lg border border-border bg-inputBg px-3 py-2 text-sm text-primary" placeholder="Titel, z.B. Jugendtrainer U15" />
                <select v-model="jobFormFor(club).type" class="rounded-lg border border-border bg-inputBg px-3 py-2 text-sm text-primary">
                    <option value="volunteer">Ehrenamt</option>
                    <option value="professional">Beruf / bezahlte Stelle</option>
                </select>
                <input v-model="jobFormFor(club).location" class="rounded-lg border border-border bg-inputBg px-3 py-2 text-sm text-primary" placeholder="Ort / Remote" />
                <input v-model="jobFormFor(club).workload" class="rounded-lg border border-border bg-inputBg px-3 py-2 text-sm text-primary" placeholder="Umfang, z.B. 6 Std./Woche" />
                <input v-model="jobFormFor(club).employment_type" class="rounded-lg border border-border bg-inputBg px-3 py-2 text-sm text-primary" placeholder="Art, z.B. Teilzeit, Minijob, Ehrenamt" />
                <input v-model="jobFormFor(club).contact_email" type="email" class="rounded-lg border border-border bg-inputBg px-3 py-2 text-sm text-primary" placeholder="Kontakt E-Mail" />
                <input v-model="jobFormFor(club).application_url" class="rounded-lg border border-border bg-inputBg px-3 py-2 text-sm text-primary md:col-span-2" placeholder="Bewerbungslink, optional" />
                <textarea v-model="jobFormFor(club).description" required rows="4" class="rounded-lg border border-border bg-inputBg px-3 py-2 text-sm text-primary md:col-span-2" placeholder="Beschreibung, Aufgaben, Voraussetzungen"></textarea>
                <label class="flex items-center gap-2 text-sm text-primary">
                    <input v-model="jobFormFor(club).is_published" type="checkbox" class="rounded border-border bg-inputBg">
                    Auf Webseite veröffentlichen
                </label>
                <div class="flex gap-2 md:justify-end">
                    <button v-if="editingJobId" type="button" class="rounded-lg border border-border px-4 py-2 text-sm text-primary" @click="resetJobForm(club)">Abbrechen</button>
                    <button class="rounded-lg bg-buttonPrimary px-4 py-2 text-sm font-semibold text-buttonTextPrimary">
                        {{ editingJobId ? 'Aktualisieren' : 'Stelle erstellen' }}
                    </button>
                </div>
            </form>

            <div class="mt-4 grid gap-3 md:grid-cols-2">
                <article v-for="job in club.jobs" :key="job.id" class="rounded-lg border border-border bg-card p-4">
                    <div class="flex items-start justify-between gap-3">
                        <div>
                            <span class="rounded-full px-2 py-1 text-xs" :class="job.type === 'volunteer' ? 'bg-air-green/15 text-air-green' : 'bg-air-blue/15 text-air-blue'">
                                {{ job.type === 'volunteer' ? 'Ehrenamt' : 'Beruf' }}
                            </span>
                            <h3 class="mt-3 font-semibold text-primary">{{ job.title }}</h3>
                            <p class="mt-1 text-xs text-secondary">{{ job.location || 'Ort offen' }} · {{ job.workload || 'Umfang offen' }}</p>
                        </div>
                        <span class="rounded-full px-2 py-1 text-xs" :class="job.is_published ? 'bg-air-green/15 text-air-green' : 'bg-muted text-secondary'">
                            {{ job.is_published ? 'Online' : 'Entwurf' }}
                        </span>
                    </div>
                    <p class="mt-3 line-clamp-3 text-sm text-secondary">{{ job.description }}</p>
                    <div v-if="club.can_manage" class="mt-4 flex gap-2">
                        <button class="rounded border border-border px-3 py-1 text-sm text-primary" @click="editJob(club, job)">Bearbeiten</button>
                        <button class="rounded bg-error px-3 py-1 text-sm text-white" @click="deleteJob(job)">Löschen</button>
                    </div>
                </article>
                <div v-if="!club.jobs?.length" class="rounded-lg border border-border bg-card p-4 text-sm text-secondary">
                    Noch keine Stellen für diese Organisation.
                </div>
            </div>
        </div>
    </div>
</div>

<!-- MODALS -->
<Modal :show="showClubModal" @close="showClubModal = false">
    <div class="space-y-4  ">
        <h2 class="font-bold">{{ $t('Verein erstellen') }}</h2>
        <input v-model="clubForm.name" class="w-full text-buttonTextPrimary rounded border p-2" placeholder="Vereinsname">
        <SearchableSelect v-model="clubForm.sport_type" :options="sports" value-key="slug" translation-prefix="sports" category-translation-prefix="sport_categories" placeholder="Sportart suchen" />
        <div class="grid gap-3 md:grid-cols-2 ">
            <select v-model="clubForm.country" class="w-full rounded border p-2 text-buttonTextPrimary" required>
                <option value="DE">Deutschland</option>
                <option value="AT">Österreich</option>
                <option value="CH">Schweiz</option>
                <option value="FR">Frankreich</option>
                <option value="NL">Niederlande</option>
                <option value="BE">Belgien</option>
                <option value="TR">Türkei</option>
                <option value="US">USA</option>
            </select>
            <input v-model="clubForm.city" class="w-full text-buttonTextPrimary rounded border p-2" placeholder="Stadt">
            <input v-model="clubForm.postal_code" class="w-full text-buttonTextPrimary rounded border p-2" placeholder="PLZ">
            <input v-model="clubForm.state" class="w-full text-buttonTextPrimary rounded border p-2" placeholder="Region">
            <input v-model="clubForm.street" class="w-full text-buttonTextPrimary rounded border p-2" placeholder="Straße">
            <input v-model="clubForm.house_number" class="w-full text-buttonTextPrimary rounded border p-2" placeholder="Hausnummer">
        </div>
        <button @click="createClub" class="w-full bg-buttonPrimary text-buttonTextPrimary py-2 rounded">
            Speichern
        </button>
    </div>
</Modal>

<Modal :show="showTeamModal" @close="closeTeamModal">
    <div v-if="selectedClub" class="space-y-4">
        <h2 class="font-bold">Team erstellen</h2>
        <input v-model="teamFormFor(selectedClub).name" class="bg-inputBg w-full border p-2 rounded">
        <p v-if="errors.name" class="text-sm text-error">{{ errors.name }}</p>
        <SearchableSelect v-model="teamFormFor(selectedClub).sport_type" :options="sports" value-key="slug" translation-prefix="sports" category-translation-prefix="sport_categories" placeholder="Sportart suchen" />
        <p v-if="errors.club_id" class="text-sm text-error">{{ errors.club_id }}</p>
        <p v-if="errors.sport_type" class="text-sm text-error">{{ errors.sport_type }}</p>
        <button @click="createTeam" class="w-full bg-buttonPrimary text-buttonTextPrimary py-2 rounded">
            Erstellen
        </button>
    </div>
</Modal>

</template>
