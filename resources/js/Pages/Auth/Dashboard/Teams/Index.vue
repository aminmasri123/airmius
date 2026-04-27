<script setup>
import AppLayout from '@/Components/Auth/Layouts/AppLayout.vue'
import Modal from '@/Components/Modal.vue'
import { Head, router } from '@inertiajs/vue3'
import { ref } from 'vue'

defineOptions({ layout: AppLayout })

const props = defineProps({
    clubs: {
        type: Array,
        default: () => [],
    },
    availableUsers: {
        type: Array,
        default: () => [],
    },
    teamRoles: {
        type: Array,
        default: () => ['Coach', 'Captain', 'Player'],
    },
})

const showClubModal = ref(false)
const clubForm = ref({ name: '' })
const inviteForms = ref({})
const teamForms = ref({})
const showTeamModal = ref(false)
const selectedClub = ref(null)

const createClub = () => {
    router.post('/clubs', clubForm.value, {
        preserveScroll: true,
        onSuccess: () => {
            clubForm.value.name = ''
            showClubModal.value = false
        },
    })
}

const openTeamModal = (club) => {
    selectedClub.value = club
    showTeamModal.value = true
}

const inviteFormFor = (team) => {
    inviteForms.value[team.id] ??= {
        user_id: props.availableUsers[0]?.id || '',
        role: 'Player',
    }

    return inviteForms.value[team.id]
}

const teamFormFor = (club) => {
    teamForms.value[club.id] ??= {
        club_id: club.id,
        name: '',
        sport_type: 'football',
    }

    return teamForms.value[club.id]
}

const createTeam = () => {
    const form = teamFormFor(selectedClub.value)
    router.post(route('auth.teams.store'), form, {
        preserveScroll: true,
        onSuccess: () => {
            form.name = ''
            form.sport_type = 'football'
            showTeamModal.value = false
            selectedClub.value = null
            // Erfolgsbenachrichtigung wird automatisch durch Laravel Flash Messages angezeigt
        },
        onError: () => {
            // Fehlerbenachrichtigung wird automatisch durch Laravel Validation Errors angezeigt
        },
    })
}

const inviteUser = (team) => {
    router.post(route('auth.teams.invite', team.id), inviteFormFor(team), { preserveScroll: true })
}

const requestJoin = (team) => {
    router.post(route('auth.teams.join-requests.store', team.id), {}, { preserveScroll: true })
}

const approveJoinRequest = (joinRequest) => {
    router.post(route('auth.team-join-requests.approve', joinRequest.id), { role: 'Player' }, { preserveScroll: true })
}

const declineJoinRequest = (joinRequest) => {
    router.post(route('auth.team-join-requests.decline', joinRequest.id), {}, { preserveScroll: true })
}

const updateMemberRole = (team, member) => {
    router.put(route('auth.teams.members.update', [team.id, member.id]), {
        role: member.pivot.role,
    }, { preserveScroll: true })
}

const removeMember = (team, member) => {
    router.delete(route('auth.teams.members.destroy', [team.id, member.id]), { preserveScroll: true })
}

const deleteTeam = (team) => {
    router.delete(route('auth.teams.destroy', team.id), { preserveScroll: true })
}

const openClubId = ref(null)

const toggleClub = (club) => {
    openClubId.value = openClubId.value === club.id ? null : club.id
}
</script>

<template>

    <Head title="Teams" />

    <div class="space-y-6">
        <div class="flex justify-between">
            <div>
                <h1 class="text-2xl font-bold text-primary">Organizations & Teams</h1>
                <p class="mt-1 text-sm text-secondary">Organization -> Teams -> Members</p>
            </div>

            <button class="rounded-lg bg-buttonPrimary px-4 py-2 text-buttonTextPrimary hover:bg-buttonPrimaryHover"
                @click="showClubModal = true">
                + Organization
            </button>
        </div>

        <div v-for="club in clubs" :key="club.id" class="space-y-4">
            <div class="flex justify-between items-center cursor-pointer" @click="clubs.length > 3 && toggleClub(club)">
                <div class="flex items-center gap-4">
                    <div
                        class="flex h-10 w-10 items-center justify-center rounded-lg border border-border bg-inputBg text-secondary">
                        {{ club.name.charAt(0).toUpperCase() }}
                    </div>
                    <div>
                        <h2 class="text-xl font-semibold text-primary">{{ club.name }}</h2>
                        <p class="text-sm text-secondary">{{ club.teams.length }} teams</p>
                        <p class="text-xs text-secondary">Admins: {{club.admins?.map((admin) => admin.name).join(', ')
                            || 'none'}}</p>
                        <p class="text-xs text-secondary">Sponsors: {{ club.sponsors?.length || 0 }}</p>
                    </div>
                </div>
                <button class="rounded-lg bg-buttonPrimary px-4 py-2 text-buttonTextPrimary hover:bg-buttonPrimaryHover"
                    @click="openTeamModal(club)">
                    + Team
                </button>
            </div>




            <div v-if="clubs.length <= 3 || openClubId === club.id"
                class="grid grid-cols-1 gap-6 md:grid-cols-2 xl:grid-cols-3">
                <article v-for="team in club.teams" :key="team.id" class="surface-card p-5">
                    <div class="mb-4 flex items-start justify-between">
                        <div
                            class="flex h-10 w-10 items-center justify-center rounded-lg border border-border bg-inputBg text-secondary">
                            {{ team.name.charAt(0).toUpperCase() }}
                        </div>
                        <span class="rounded-full bg-borderHover px-3 py-1 text-xs text-primary">ACTIVE</span>
                    </div>

                    <h3 class="text-lg font-semibold text-primary">{{ team.name }}</h3>
                    <p class="text-sm text-secondary">{{ team.sport_type || 'football' }} · {{ team.users_count ??
                        team.users?.length ?? 0 }} members</p>
                    <p class="mt-1 text-sm text-secondary">
                        Coach: {{team.users?.find((user) => user.pivot?.role === 'Coach')?.name || 'none'}}
                    </p>

                    <div class="mt-4 space-y-3">
                        <div v-if="team.users?.length" class="space-y-2">
                            <div v-for="member in team.users" :key="member.id" class="flex items-center gap-2 text-sm">
                                <span class="min-w-0 flex-1 truncate text-primary">{{ member.name }}</span>
                                <select v-model="member.pivot.role"
                                    class="rounded border-border bg-inputBg text-xs text-primary"
                                    @change="updateMemberRole(team, member)">
                                    <option v-for="role in teamRoles" :key="role" :value="role">{{ role }}</option>
                                </select>
                                <button class="text-error" @click="removeMember(team, member)">
                                    <i class="las la-user-minus"></i>
                                </button>
                            </div>
                        </div>

                        <form class="flex gap-2" @submit.prevent="inviteUser(team)">
                            <select v-model="inviteFormFor(team).user_id"
                                class="min-w-0 flex-1 rounded border-border bg-inputBg text-xs text-primary">
                                <option v-for="user in availableUsers" :key="user.id" :value="user.id">{{ user.name }}
                                </option>
                            </select>
                            <select v-model="inviteFormFor(team).role"
                                class="rounded border-border bg-inputBg text-xs text-primary">
                                <option v-for="role in teamRoles" :key="role" :value="role">{{ role }}</option>
                            </select>
                            <button
                                class="rounded bg-buttonPrimary px-2 py-1 text-xs text-buttonTextPrimary">Invite</button>
                        </form>

                        <button class="text-xs text-secondary underline" @click="requestJoin(team)">
                            Request to join
                        </button>

                        <div v-if="team.join_requests?.length" class="rounded border border-border p-2">
                            <div class="mb-1 text-xs font-semibold text-primary">Join requests</div>
                            <div v-for="joinRequest in team.join_requests" :key="joinRequest.id"
                                class="flex items-center gap-2 text-xs">
                                <span class="flex-1 truncate">{{ joinRequest.user.name }}</span>
                                <button class="text-success" @click="approveJoinRequest(joinRequest)">Accept</button>
                                <button class="text-error" @click="declineJoinRequest(joinRequest)">Decline</button>
                            </div>
                        </div>

                        <div v-if="team.invitations?.length" class="text-xs text-secondary">
                            Pending invites: {{ team.invitations.length }}
                        </div>

                        <button class="text-xs text-error underline" @click="deleteTeam(team)">
                            Delete team
                        </button>
                    </div>
                </article>
            </div>
        </div>
    </div>

    <Modal :show="showTeamModal" max-width="md" @close="showTeamModal = false">
        <div class="space-y-4 text-primary">
            <h2 class="text-lg font-bold">Create team for {{ selectedClub?.name }}</h2>
            <div class="space-y-3">
                <div>
                    <label class="block text-sm font-medium text-primary mb-1">Team name</label>
                    <input v-model="teamFormFor(selectedClub).name" placeholder="Enter team name"
                        class="w-full rounded-lg border border-border bg-inputBg p-2 focus:border-borderHover focus:ring-borderHover"
                        required />
                </div>
                <div>
                    <label class="block text-sm font-medium text-primary mb-1">Sport type</label>
                    <input v-model="teamFormFor(selectedClub).sport_type" placeholder="e.g. football, basketball"
                        class="w-full rounded-lg border border-border bg-inputBg p-2 focus:border-borderHover focus:ring-borderHover" />
                </div>
            </div>
            <div class="flex gap-3">
                <button class="flex-1 rounded-lg bg-gray-500 py-2 text-white hover:bg-gray-600"
                    @click="showTeamModal = false">
                    Cancel
                </button>
                <button
                    class="flex-1 rounded-lg bg-buttonPrimary py-2 text-buttonTextPrimary hover:bg-buttonPrimaryHover"
                    @click="createTeam">
                    Create team
                </button>
            </div>
        </div>
    </Modal>

    <Modal :show="showClubModal" max-width="md" closeable @close="showClubModal = false">
        <div class="space-y-4 text-primary">
            <h2 class="text-lg font-bold">Create organization</h2>
            <input v-model="clubForm.name" placeholder="Organization name"
                class="w-full rounded-lg border border-border bg-inputBg p-2 focus:border-borderHover focus:ring-borderHover" />
            <button class="w-full rounded-lg bg-buttonPrimary py-2 text-buttonTextPrimary hover:bg-buttonPrimaryHover"
                @click="createClub">
                Save
            </button>
        </div>
    </Modal>
</template>
