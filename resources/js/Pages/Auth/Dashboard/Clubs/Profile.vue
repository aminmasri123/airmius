<script setup>
import AppLayout from '@/Components/Auth/Layouts/AppLayout.vue'
import ClubWorkspaceNav from '@/Components/Auth/ClubWorkspaceNav.vue'
import AppButton from '@/Components/UI/AppButton.vue'
import AppEmptyState from '@/Components/UI/AppEmptyState.vue'
import AppLoadingState from '@/Components/UI/AppLoadingState.vue'
import { Head, Link, router, useForm, usePage } from '@inertiajs/vue3'
import { computed, ref } from 'vue'
import { confirmDialog } from '@/services/dialogService'

const props = defineProps({
    clubProfile: Object,
    clubRoles: { type: Array, default: () => ['owner', 'admin', 'manager', 'member'] },
    posts: { type: Array, default: () => [] },
    viewer: Object,
})

const initials = (name) => (name || '?').split(' ').slice(0, 2).map((part) => part.charAt(0)).join('').toUpperCase()
const formatDate = (value) => new Intl.DateTimeFormat('de-DE', { day: '2-digit', month: '2-digit', year: 'numeric' }).format(new Date(value))
const page = usePage()
const storageUrl = (path) => path?.startsWith('http') ? path : `${page.props.uploads?.url || '/storage'}/${path}`
const clubRoleLabel = (role) => ({
    owner: 'Owner',
    admin: 'Verein-Admin',
    manager: 'Manager',
    academy_manager: 'Akademie-Manager',
    financial_controller: 'Kassierer',
    trainer: 'Trainer',
    member: 'Mitglied',
}[role] || role)
const memberRoles = (member) => Array.isArray(member.pivot.roles) && member.pivot.roles.length
    ? member.pivot.roles
    : [member.pivot.role || 'member']
const toggleMemberRole = (member, role) => {
    const roles = memberRoles(member)

    member.pivot.roles = roles.includes(role)
        ? roles.filter((value) => value !== role)
        : [...roles, role]
}
const logoInput = ref(null)
const coverInput = ref(null)
const membershipRequestOpen = ref(false)
const imageForm = useForm({
    logo: null,
    cover_image: null,
})
const membershipRequestForm = useForm({
    club_membership_type_id: props.clubProfile.membership_types?.[0]?.id || '',
    application_data: {},
    accepted_documents: {},
    preferred_payment_method: props.clubProfile.membership_payment_methods?.[0] || '',
    requested_billing_interval: '',
    message: '',
})
const pauseForm = useForm({
    requested_pause_from: '',
    requested_pause_until: '',
    message: '',
})

const clubForm = useForm({
    name: props.clubProfile.name || '',
    sport_type: props.clubProfile.sport_type || '',
    official_club_number: props.clubProfile.requested_official_club_number || props.clubProfile.official_club_number || '',
    country: props.clubProfile.country || 'DE',
    street: props.clubProfile.street || '',
    house_number: props.clubProfile.house_number || '',
    postal_code: props.clubProfile.postal_code || '',
    city: props.clubProfile.city || '',
    state: props.clubProfile.state || '',
    is_listed: props.clubProfile.is_listed !== false,
    teams_are_listed: props.clubProfile.teams_are_listed !== false,
    members_can_post_to_club: props.clubProfile.members_can_post_to_club !== false,
    members_can_post_to_teams: props.clubProfile.members_can_post_to_teams !== false,
})

const uploadImage = (field, event) => {
    const file = event.target.files?.[0] || null

    if (!file) {
        return
    }

    imageForm.logo = field === 'logo' ? file : null
    imageForm.cover_image = field === 'cover_image' ? file : null
    imageForm.post(route('auth.clubs.images.update', props.clubProfile.id), {
        forceFormData: true,
        preserveScroll: true,
        onFinish: () => {
            imageForm.reset()
            if (logoInput.value) logoInput.value.value = null
            if (coverInput.value) coverInput.value.value = null
        },
    })
}

const updateMemberRole = (member) => {
    router.put(route('auth.clubs.members.update', [props.clubProfile.id, member.id]), {
        role: member.pivot.role,
        roles: memberRoles(member),
    }, {
        preserveScroll: true,
    })
}

const updateClubProfile = () => {
    clubForm.put(route('auth.clubs.update', props.clubProfile.id), {
        preserveScroll: true,
    })
}

const openMembershipRequest = () => {
    membershipRequestForm.club_membership_type_id = props.clubProfile.membership_types?.[0]?.id || ''
    membershipRequestForm.application_data = Object.fromEntries(
        (props.clubProfile.membership_application_fields || [])
            .filter((field) => field.mode !== 'off')
            .map((field) => [field.key, props.viewer.application_prefill?.[field.key] || (field.type === 'checkbox' ? false : '')])
    )
    membershipRequestForm.accepted_documents = Object.fromEntries(
        (props.clubProfile.membership_application_documents || [])
            .map((document) => [document.id, false])
    )
    membershipRequestForm.preferred_payment_method = props.clubProfile.membership_payment_methods?.[0] || ''
    membershipRequestForm.requested_billing_interval = props.clubProfile.membership_types?.[0]?.billing_interval || ''
    membershipRequestForm.message = ''
    membershipRequestOpen.value = true
}

const submitMembershipRequest = () => {
    membershipRequestForm.post(route('auth.club-membership-requests.store', props.clubProfile.id), {
        preserveScroll: true,
        onSuccess: () => {
            membershipRequestOpen.value = false
            membershipRequestForm.reset()
        },
    })
}

const withdrawMembershipRequest = async () => {
    const confirmed = await confirmDialog({
        title: 'Anfrage zurückziehen',
        message: `Möchtest du deine Mitgliedschaftsanfrage bei "${props.clubProfile.name}" wirklich zurückziehen?`,
        confirmLabel: 'Zurückziehen',
        danger: true,
    })

    if (!confirmed) return

    router.delete(route('auth.club-membership-requests.destroy', props.clubProfile.id), {
        preserveScroll: true,
    })
}

const requestPause = () => {
    pauseForm.post(route('auth.club-membership-pause-requests.store', props.clubProfile.id), {
        preserveScroll: true,
        onSuccess: () => pauseForm.reset(),
    })
}

const leaveClub = async () => {
    const confirmed = await confirmDialog({
        title: 'Verein verlassen',
        message: `Möchtest du den Verein "${props.clubProfile.name}" wirklich verlassen? Du wirst auch aus allen Teams dieses Vereins entfernt. Das ist nur möglich, wenn keine offenen Rechnungen bestehen.`,
        confirmLabel: 'Verlassen',
        danger: true,
    })

    if (!confirmed) return

    router.post(route('auth.club-memberships.leave', props.clubProfile.id), {}, {
        preserveScroll: true,
    })
}

const intervalLabel = (interval) => ({
    monthly: 'Monat',
    quarterly: 'Quartal',
    four_monthly: '4 Monate',
    semi_yearly: '6 Monate',
    yearly: 'Jahr',
    once: 'einmalig',
    none: 'kein Beitrag',
}[interval] || interval)

const paymentMethodLabel = (value) => props.clubProfile.membership_payment_method_options?.find((method) => method.value === value)?.label || value

const visibleMembershipDocuments = computed(() => props.clubProfile.membership_application_documents || [])

const applicationFieldSections = computed(() => {
    const sections = []

    ;(props.clubProfile.membership_application_fields || [])
        .filter((field) => field.mode !== 'off')
        .forEach((field) => {
            let section = sections.find((candidate) => candidate.name === field.section)

            if (!section) {
                section = { name: field.section, fields: [] }
                sections.push(section)
            }

            section.fields.push(field)
        })

    return sections
})

const formatMoney = (value) => new Intl.NumberFormat('de-DE', {
    style: 'currency',
    currency: 'EUR',
}).format(Number(value || 0))
</script>

<template>
    <AppLayout :title="clubProfile.name">

        <Head :title="clubProfile.name" />

        <div class="mx-auto max-w-5xl space-y-6">
            <ClubWorkspaceNav
                active="structure"
                description="Vereinsprofil, Teams, Rollen und sichtbare Vereinsbeiträge."
            />

            <section class="overflow-hidden rounded-lg border border-border bg-card">
                <div class="relative h-40 bg-gradient-to-r from-buttonPrimary to-borderHover">
                    <img v-if="clubProfile.cover_image" :src="storageUrl(clubProfile.cover_image)"
                        :alt="clubProfile.name" width="1200" height="320" loading="eager" decoding="async" fetchpriority="high" class="h-full w-full object-cover" />
                    <button v-if="viewer.can_manage" type="button"
                        class="absolute bottom-3 right-3 rounded-lg bg-card/90 px-3 py-2 text-sm font-semibold text-primary shadow hover:bg-card"
                        @click="coverInput?.click()">
                        <i class="las la-camera"></i> Titelbild
                    </button>
                    <input ref="coverInput" type="file" accept="image/*" class="hidden"
                        @change="uploadImage('cover_image', $event)" />
                </div>
                <div class="px-5 pb-5">
                    <div class="-mt-12 flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between">
                        <div class="flex items-end gap-4">
                            <div
                                class="group relative flex h-24 w-24 items-center justify-center overflow-hidden rounded-lg border-4 border-card bg-inputBg text-3xl font-bold text-primary">
                                <img v-if="clubProfile.logo" :src="storageUrl(clubProfile.logo)" :alt="clubProfile.name"
                                    width="96" height="96" loading="eager" decoding="async" class="h-full w-full object-cover" />
                                <span v-else>{{ initials(clubProfile.name) }}</span>
                                <button v-if="viewer.can_manage" type="button"
                                    class="absolute inset-0 flex items-center justify-center bg-black/50 text-sm font-semibold text-white opacity-0 transition group-hover:opacity-100"
                                    @click="logoInput?.click()">
                                    <i class="las la-camera text-xl"></i>
                                </button>
                                <input ref="logoInput" type="file" accept="image/*" class="hidden"
                                    @change="uploadImage('logo', $event)" />
                            </div>
                            <div class="pb-1">
                                <h1 class="text-2xl font-bold text-primary">{{ clubProfile.name }}</h1>
                                <p class="text-sm text-secondary">Verein · {{ viewer.is_member ? 'Mitglied' : 'Profil'
                                    }}</p>
                            </div>
                        </div>

                        <div class="flex flex-wrap gap-2">
                            <button
                                v-if="!viewer.is_member && !viewer.can_manage && clubProfile.membership_requests_enabled && !viewer.has_pending_membership_request"
                                type="button"
                                class="rounded-lg bg-buttonPrimary px-4 py-2 text-sm font-semibold text-buttonTextPrimary hover:bg-buttonPrimaryHover"
                                @click="openMembershipRequest"
                            >
                                Mitgliedschaft anfragen
                            </button>
                            <span
                                v-if="!viewer.is_member && viewer.has_pending_membership_request"
                                class="rounded-lg border border-success/40 px-4 py-2 text-sm font-semibold text-success"
                            >
                                Anfrage gesendet
                            </span>
                            <button
                                v-if="!viewer.is_member && viewer.has_pending_membership_request"
                                type="button"
                                class="rounded-lg border border-error/40 px-4 py-2 text-sm font-semibold text-error hover:bg-error/10"
                                @click="withdrawMembershipRequest"
                            >
                                Anfrage zurückziehen
                            </button>
                            <Link :href="route('auth.teams.index')"
                                class="rounded-lg border border-border px-4 py-2 text-sm text-primary hover:bg-inputBg">
                                Teams ansehen
                            </Link>
                            <button
                                v-if="viewer.is_member && !viewer.can_manage"
                                type="button"
                                class="rounded-lg border border-error/40 px-4 py-2 text-sm font-semibold text-error hover:bg-error/10"
                                @click="leaveClub"
                            >
                                Verein verlassen
                            </button>
                        </div>
                    </div>
                </div>
            </section>

            <section class="grid gap-4 sm:grid-cols-3">
                <div class="rounded-lg border border-border bg-card p-4">
                    <div class="text-2xl font-semibold text-primary">{{ clubProfile.users_count }}</div>
                    <div class="text-sm text-secondary">Mitglieder</div>
                </div>
                <div class="rounded-lg border border-border bg-card p-4">
                    <div class="text-2xl font-semibold text-primary">{{ clubProfile.teams_count }}</div>
                    <div class="text-sm text-secondary">Teams</div>
                </div>
                <div class="rounded-lg border border-border bg-card p-4">
                    <div class="text-2xl font-semibold text-primary">{{ clubProfile.posts_count }}</div>
                    <div class="text-sm text-secondary">Beiträge</div>
                </div>
            </section>

            <section v-if="viewer.is_member && clubProfile.member_pause_requests_enabled && !viewer.can_manage" class="rounded-lg border border-border bg-card p-5">
                <h2 class="text-lg font-semibold text-primary">Mitgliedschaft pausieren</h2>
                <p class="mt-1 text-sm text-secondary">
                    Dein Verein erlaubt Pausen-Anfragen. Die Pause wird erst nach Freigabe durch die Vereinsverwaltung aktiv.
                </p>
                <form class="mt-4 grid gap-3 md:grid-cols-3" @submit.prevent="requestPause">
                    <input v-model="pauseForm.requested_pause_from" type="date" class="rounded-lg border border-border bg-inputBg px-3 py-2 text-sm text-primary" required>
                    <input v-model="pauseForm.requested_pause_until" type="date" class="rounded-lg border border-border bg-inputBg px-3 py-2 text-sm text-primary">
                    <input v-model="pauseForm.message" class="rounded-lg border border-border bg-inputBg px-3 py-2 text-sm text-primary" placeholder="Grund optional">
                    <button class="rounded-lg bg-buttonPrimary px-4 py-2 text-sm font-semibold text-buttonTextPrimary" :disabled="pauseForm.processing">
                        Pause anfragen
                    </button>
                </form>
            </section>

            <section v-if="viewer.can_manage" class="rounded-lg border border-border bg-card p-5">
                <h2 class="text-lg font-semibold text-primary">Vereinsdaten</h2>
                <p class="mt-1 text-sm text-secondary">
                    Offizielle Vereine müssen ihre Vereinsnummer hinterlegen. Nicht-offizielle Gruppen können das Feld leer lassen.
                </p>

                <form class="mt-4 grid gap-4 md:grid-cols-2" @submit.prevent="updateClubProfile">
                    <div class="md:col-span-2 rounded-lg border border-border bg-bg p-4 text-sm">
                        <div class="flex flex-wrap items-center gap-2">
                            <span class="font-semibold text-primary">Prüfstatus:</span>
                            <span class="rounded-full bg-inputBg px-2 py-1 text-xs font-semibold text-secondary">
                                {{ clubProfile.verification_status === 'pending_verification' ? 'Wartet auf Prüfung' : clubProfile.verification_status === 'verified' ? 'Freigegeben' : clubProfile.verification_status === 'rejected' ? 'Abgelehnt' : clubProfile.verification_status }}
                            </span>
                            <span v-if="clubProfile.is_official" class="rounded-full bg-success/10 px-2 py-1 text-xs font-semibold text-success">
                                Offiziell
                            </span>
                        </div>
                        <p v-if="clubProfile.official_club_number" class="mt-2 text-secondary">
                            Vereinsnummer: {{ clubProfile.official_club_number }}
                        </p>
                        <p v-else-if="clubProfile.requested_official_club_number" class="mt-2 text-secondary">
                            Beantragte Vereinsnummer: {{ clubProfile.requested_official_club_number }}
                        </p>
                        <p v-if="clubProfile.verification_notes" class="mt-2 text-secondary">
                            Hinweis: {{ clubProfile.verification_notes }}
                        </p>
                    </div>

                    <div>
                        <label class="text-sm font-semibold text-primary">Vereinsname</label>
                        <input v-model="clubForm.name" class="mt-1 w-full rounded-lg border border-border bg-inputBg px-3 py-2 text-sm text-primary">
                    </div>

                    <div>
                        <label class="text-sm font-semibold text-primary">Sportart</label>
                        <input v-model="clubForm.sport_type" class="mt-1 w-full rounded-lg border border-border bg-inputBg px-3 py-2 text-sm text-primary">
                    </div>

                    <label class="flex items-start gap-3 rounded-lg border border-border bg-bg p-3 text-sm text-primary">
                        <input v-model="clubForm.is_listed" type="checkbox" class="mt-1 rounded border-border bg-inputBg">
                        <span>
                            <span class="block font-semibold">Verein auflisten</span>
                            <span class="block text-xs text-secondary">Der Verein darf in Vereinslisten und Auswahlfeldern sichtbar sein.</span>
                        </span>
                    </label>

                    <label class="flex items-start gap-3 rounded-lg border border-border bg-bg p-3 text-sm text-primary">
                        <input v-model="clubForm.teams_are_listed" type="checkbox" class="mt-1 rounded border-border bg-inputBg">
                        <span>
                            <span class="block font-semibold">Teams auflisten</span>
                            <span class="block text-xs text-secondary">Teams dürfen außerhalb des internen Vereinsbereichs sichtbar sein.</span>
                        </span>
                    </label>

                    <label class="flex items-start gap-3 rounded-lg border border-border bg-bg p-3 text-sm text-primary">
                        <input v-model="clubForm.members_can_post_to_club" type="checkbox" class="mt-1 rounded border-border bg-inputBg">
                        <span>
                            <span class="block font-semibold">Vereinsbeiträge erlauben</span>
                            <span class="block text-xs text-secondary">Normale Mitglieder dürfen Beiträge für den Verein erstellen.</span>
                        </span>
                    </label>

                    <label class="flex items-start gap-3 rounded-lg border border-border bg-bg p-3 text-sm text-primary">
                        <input v-model="clubForm.members_can_post_to_teams" type="checkbox" class="mt-1 rounded border-border bg-inputBg">
                        <span>
                            <span class="block font-semibold">Teambeiträge erlauben</span>
                            <span class="block text-xs text-secondary">Normale Teammitglieder dürfen Beiträge für ihre Teams erstellen.</span>
                        </span>
                    </label>

                    <label v-if="false" class="flex items-start gap-3 rounded-lg border border-border bg-bg p-3 text-sm text-primary md:col-span-2">
                        <input v-model="clubForm.is_official" type="checkbox" class="mt-1 rounded border-border bg-inputBg">
                        <span>
                            <span class="block font-semibold">Offizieller Verein</span>
                            <span class="block text-secondary">Aktivieren, wenn der Verein offiziell registriert oder einem Verband zugeordnet ist.</span>
                        </span>
                    </label>

                    <div class="md:col-span-2">
                        <label class="text-sm font-semibold text-primary">Vereinsnummer zur Prüfung</label>
                        <input
                            v-model="clubForm.official_club_number"
                            class="mt-1 w-full rounded-lg border border-border bg-inputBg px-3 py-2 text-sm text-primary"
                            placeholder="z. B. Vereinsregister- oder Verbandsnummer"
                        >
                        <p class="mt-1 text-xs text-secondary">
                            Wenn die Nummer neu oder geändert ist, wird sie zur Admin-Prüfung vorgemerkt.
                        </p>
                    </div>

                    <div>
                        <label class="text-sm font-semibold text-primary">Land</label>
                        <input v-model="clubForm.country" maxlength="2" class="mt-1 w-full rounded-lg border border-border bg-inputBg px-3 py-2 text-sm uppercase text-primary">
                    </div>

                    <div>
                        <label class="text-sm font-semibold text-primary">Stadt</label>
                        <input v-model="clubForm.city" class="mt-1 w-full rounded-lg border border-border bg-inputBg px-3 py-2 text-sm text-primary">
                    </div>

                    <div>
                        <label class="text-sm font-semibold text-primary">PLZ</label>
                        <input v-model="clubForm.postal_code" class="mt-1 w-full rounded-lg border border-border bg-inputBg px-3 py-2 text-sm text-primary">
                    </div>

                    <div>
                        <label class="text-sm font-semibold text-primary">Region</label>
                        <input v-model="clubForm.state" class="mt-1 w-full rounded-lg border border-border bg-inputBg px-3 py-2 text-sm text-primary">
                    </div>

                    <div>
                        <label class="text-sm font-semibold text-primary">Straße</label>
                        <input v-model="clubForm.street" class="mt-1 w-full rounded-lg border border-border bg-inputBg px-3 py-2 text-sm text-primary">
                    </div>

                    <div>
                        <label class="text-sm font-semibold text-primary">Hausnummer</label>
                        <input v-model="clubForm.house_number" class="mt-1 w-full rounded-lg border border-border bg-inputBg px-3 py-2 text-sm text-primary">
                    </div>

                    <div class="md:col-span-2">
                        <div class="flex flex-col gap-3 sm:flex-row sm:items-center">
                            <AppButton type="submit" :loading="clubForm.processing" :disabled="clubForm.processing">
                                {{ clubForm.processing ? 'Speichert...' : 'Vereinsdaten speichern' }}
                            </AppButton>
                            <AppLoadingState v-if="clubForm.processing" label="Vereinsdaten werden gespeichert..." inline />
                        </div>
                    </div>
                </form>
            </section>

            <div class="grid gap-6 lg:grid-cols-[1fr_320px]">
                <section class="space-y-4">
                    <article v-for="post in posts" :key="post.id" class="rounded-lg border border-border bg-card p-4">
                        <div class="flex items-center gap-3">
                            <img :src="post.user.profile_photo_url" :alt="post.user.name"
                                width="40" height="40" loading="lazy" decoding="async"
                                class="h-10 w-10 rounded-full object-cover">
                            <div>
                                <Link :href="route('auth.users.show', post.user.id)"
                                    class="text-sm font-semibold text-primary hover:underline">
                                    {{ post.user.name }}
                                </Link>
                                <p class="text-xs text-secondary">{{ post.team?.name || clubProfile.name }} · {{
                                    formatDate(post.created_at) }}</p>
                            </div>
                        </div>
                        <p class="mt-3 whitespace-pre-line text-sm leading-6 text-primary">{{ post.content }}</p>
                        <div class="mt-3 flex gap-4 text-xs text-secondary">
                            <span>{{ post.likes_count }} Likes</span>
                            <span>{{ post.comments_count }} Kommentare</span>
                        </div>
                    </article>
                    <div v-if="!posts.length"
                        class="rounded-lg border border-border bg-card p-8 text-center text-sm text-secondary">
                        Noch keine sichtbaren Beiträge.
                    </div>
                </section>

                <aside class="space-y-4">
                    <section class="rounded-lg border border-border bg-card p-4">
                        <h2 class="text-sm font-semibold uppercase tracking-wide text-secondary">Teams</h2>
                        <div class="mt-4 space-y-2">
                            <Link v-for="team in clubProfile.teams" :key="team.id"
                                :href="route('auth.teams.show', team.id)"
                                class="flex items-center gap-3 rounded-lg p-2 hover:bg-inputBg">
                                <div
                                    class="flex h-9 w-9 items-center justify-center rounded bg-buttonPrimary text-sm font-semibold text-buttonTextPrimary">
                                    {{ initials(team.name) }}</div>
                                <div class="min-w-0">
                                    <p class="truncate text-sm font-medium text-primary">{{ team.name }}</p>
                                    <p class="text-xs text-secondary">{{ team.users_count }} Mitglieder</p>
                                </div>
                            </Link>
                            <AppEmptyState
                                v-if="!clubProfile.teams.length"
                                title="Noch keine Teams"
                                description="Teams dieses Vereins erscheinen hier, sobald sie erstellt wurden."
                                compact
                            >
                                <template #icon>
                                    <i class="las la-users text-xl" aria-hidden="true"></i>
                                </template>
                            </AppEmptyState>
                        </div>
                    </section>

                    <section class="rounded-lg border border-border bg-card p-4">
                        <h2 class="text-sm font-semibold uppercase tracking-wide text-secondary">Admins</h2>
                        <div class="mt-4 space-y-2">
                            <Link v-for="admin in clubProfile.admins" :key="admin.id"
                                :href="route('auth.users.show', admin.id)"
                                class="flex items-center gap-3 rounded-lg p-2 hover:bg-inputBg">
                                <img v-if="admin.profile_photo_thumb" :src="admin.profile_photo_thumb" :alt="admin.name" width="32" height="32" loading="lazy" decoding="async"
                                    class="h-8 w-8 rounded-full object-cover" />
                                <div v-else
                                    class="flex h-8 w-8 items-center justify-center rounded-full bg-buttonPrimary text-xs font-semibold text-buttonTextPrimary">
                                    {{ initials(admin?.name) }}
                                </div>
                                <span class="min-w-0 truncate text-sm font-medium text-primary">{{ admin.name }}</span>
                            </Link>
                        </div>
                    </section>

                    <section v-if="viewer.is_member || viewer.can_manage" class="rounded-lg border border-border bg-card p-4">
                        <h2 class="text-sm font-semibold uppercase tracking-wide text-secondary">Mitglieder</h2>
                        <div class="mt-4 space-y-2">
                            <div v-for="member in clubProfile.members" :key="member.id"
                                class="flex flex-col gap-3 rounded-lg border border-transparent p-3 hover:border-border hover:bg-inputBg">
                                <div class="flex min-w-0 items-center gap-3">
                                    <Link :href="route('auth.users.show', member.id)"
                                        class="flex min-w-0 items-center gap-3 hover:underline">
                                        <img v-if="member.profile_photo_thumb" :src="member.profile_photo_thumb"
                                            :alt="member.name" width="32" height="32" loading="lazy" decoding="async" class="h-8 w-8 rounded-full object-cover" />
                                        <div v-else
                                            class="flex h-8 w-8 shrink-0 items-center justify-center rounded-full bg-buttonPrimary text-xs font-semibold text-buttonTextPrimary">
                                            {{ initials(member?.name) }}
                                        </div>
                                        <span class="truncate text-sm font-medium text-primary">{{ member.name }}</span>
                                    </Link>
                                    <p v-if="!viewer.can_manage" class="ml-auto shrink-0 text-right text-xs text-secondary">
                                        {{ memberRoles(member).map(clubRoleLabel).join(', ') }}
                                    </p>
                                </div>

                                <div v-if="viewer.can_manage" class="flex flex-wrap items-center gap-2">
                                    <button
                                        v-for="role in clubRoles"
                                        :key="role"
                                        type="button"
                                        class="rounded-full border px-2.5 py-1 text-xs font-semibold transition"
                                        :class="memberRoles(member).includes(role)
                                            ? 'border-buttonPrimary bg-buttonPrimary text-buttonTextPrimary'
                                            : 'border-border bg-card text-secondary hover:border-borderHover hover:text-primary'"
                                        :aria-pressed="memberRoles(member).includes(role)"
                                        @click="toggleMemberRole(member, role)"
                                    >
                                        {{ clubRoleLabel(role) }}
                                    </button>

                                    <button type="button" class="ml-auto rounded bg-buttonPrimary px-3 py-1.5 text-xs font-semibold text-buttonTextPrimary" @click="updateMemberRole(member)">
                                        Speichern
                                    </button>
                                </div>
                            </div>
                            <AppEmptyState
                                v-if="!clubProfile.members.length"
                                title="Noch keine Mitglieder"
                                description="Angenommene Mitglieder werden in dieser Liste sichtbar."
                                compact
                            >
                                <template #icon>
                                    <i class="las la-id-badge text-xl" aria-hidden="true"></i>
                                </template>
                            </AppEmptyState>
                        </div>
                    </section>
                </aside>
            </div>

            <Teleport to="body">
                <div v-if="membershipRequestOpen" class="fixed inset-0 z-[9999] flex items-center justify-center bg-black/60 px-4 py-6">
                    <form class="airmius-modal-scroll max-h-[calc(100vh-3rem)] w-full max-w-lg overflow-y-auto rounded-lg border border-border bg-card p-5 shadow-2xl lg:max-w-3xl xl:max-w-4xl" @submit.prevent="submitMembershipRequest">
                    <div class="flex items-start justify-between gap-4">
                        <div>
                            <p class="text-xs font-semibold uppercase tracking-wide text-air-blue">Mitgliedsantrag</p>
                            <h2 class="mt-1 text-xl font-bold text-primary">{{ clubProfile.name }}</h2>
                        </div>
                        <button type="button" class="rounded-lg p-2 text-secondary hover:bg-muted" @click="membershipRequestOpen = false">
                            <i class="las la-times text-xl"></i>
                        </button>
                    </div>

                    <div class="mt-4 space-y-3">
                        <label class="block">
                            <span class="text-sm font-semibold text-primary">Mitgliedschaftstyp</span>
                            <select v-model="membershipRequestForm.club_membership_type_id" class="mt-1 w-full rounded-lg border-border bg-inputBg text-primary">
                                <option value="">Allgemeine Anfrage</option>
                                <option v-for="type in clubProfile.membership_types" :key="type.id" :value="type.id">
                                    {{ type.name }}
                                    <template v-if="type.amount !== null && type.amount !== undefined">
                                        - {{ formatMoney(type.amount) }} / {{ intervalLabel(type.billing_interval) }}
                                    </template>
                                </option>
                            </select>
                        </label>

                        <div v-if="clubProfile.membership_types?.length" class="rounded-lg border border-border bg-bg p-3 text-sm text-secondary">
                            <p v-for="type in clubProfile.membership_types" :key="type.id" class="py-1">
                                <span class="font-semibold text-primary">{{ type.name }}:</span>
                                <span v-if="type.amount !== null && type.amount !== undefined">{{ formatMoney(type.amount) }} / {{ intervalLabel(type.billing_interval) }}</span>
                                <span v-else>Beitrag nach Rücksprache</span>
                            </p>
                        </div>

                        <div v-for="section in applicationFieldSections" :key="section.name" class="rounded-lg border border-border bg-bg p-3">
                            <h3 class="text-sm font-semibold text-primary">{{ section.name }}</h3>
                            <div class="mt-3 grid gap-3 md:grid-cols-2">
                                <label v-for="field in section.fields" :key="field.key" class="block text-sm">
                                    <span class="font-semibold text-primary">
                                        {{ field.label }}
                                        <span v-if="field.mode === 'required'" class="text-error">*</span>
                                    </span>
                                    <select
                                        v-if="field.type === 'select'"
                                        v-model="membershipRequestForm.application_data[field.key]"
                                        class="mt-1 w-full rounded-lg border-border bg-inputBg text-primary"
                                        :required="field.mode === 'required'"
                                    >
                                        <option value="">Bitte wählen</option>
                                        <option v-for="option in field.options" :key="option.value" :value="option.value">{{ option.label }}</option>
                                    </select>
                                    <label v-else-if="field.type === 'checkbox'" class="mt-2 flex items-start gap-2 rounded-lg border border-border bg-card p-3 text-secondary">
                                        <input v-model="membershipRequestForm.application_data[field.key]" type="checkbox" class="mt-1 rounded border-border bg-inputBg" :required="field.mode === 'required'">
                                        <span>{{ field.label }}</span>
                                    </label>
                                    <input
                                        v-else
                                        v-model="membershipRequestForm.application_data[field.key]"
                                        :type="field.type || 'text'"
                                        :maxlength="field.max || undefined"
                                        class="mt-1 w-full rounded-lg border-border bg-inputBg text-primary"
                                        :required="field.mode === 'required'"
                                    >
                                    <p v-if="membershipRequestForm.errors[`application_data.${field.key}`]" class="mt-1 text-xs text-error">
                                        {{ membershipRequestForm.errors[`application_data.${field.key}`] }}
                                    </p>
                                </label>
                            </div>
                        </div>

                        <div class="grid gap-3 md:grid-cols-2">
                            <label class="block">
                                <span class="text-sm font-semibold text-primary">Gewünschte Zahlmethode</span>
                                <select v-model="membershipRequestForm.preferred_payment_method" class="mt-1 w-full rounded-lg border-border bg-inputBg text-primary">
                                    <option value="">Nach Rücksprache</option>
                                    <option v-for="method in clubProfile.membership_payment_methods" :key="method" :value="method">
                                        {{ paymentMethodLabel(method) }}
                                    </option>
                                </select>
                            </label>
                            <label class="block">
                                <span class="text-sm font-semibold text-primary">Beitragsintervall</span>
                                <select v-model="membershipRequestForm.requested_billing_interval" class="mt-1 w-full rounded-lg border-border bg-inputBg text-primary">
                                    <option value="">Wie vom Verein festgelegt</option>
                                    <option value="monthly">Monatlich</option>
                                    <option value="quarterly">Quartal</option>
                                    <option value="four_monthly">Alle 4 Monate</option>
                                    <option value="semi_yearly">Alle 6 Monate</option>
                                    <option value="yearly">Jährlich</option>
                                    <option value="once">Einmalig</option>
                                </select>
                            </label>
                        </div>

                        <div v-if="visibleMembershipDocuments.length" class="rounded-lg border border-border bg-bg p-3">
                            <h3 class="text-sm font-semibold text-primary">Dokumente des Vereins</h3>
                            <p class="mt-1 text-xs text-secondary">
                                Bitte lies die verknüpften Dokumente. Pflichtdokumente müssen vor dem Absenden bestätigt werden.
                            </p>
                            <div class="mt-3 space-y-3">
                                <label
                                    v-for="document in visibleMembershipDocuments"
                                    :key="document.id"
                                    class="flex items-start gap-3 rounded-lg border border-border bg-card p-3 text-sm text-secondary"
                                >
                                    <input
                                        v-model="membershipRequestForm.accepted_documents[document.id]"
                                        type="checkbox"
                                        class="mt-1 rounded border-border bg-inputBg"
                                        :required="document.is_required"
                                    >
                                    <span class="min-w-0">
                                        <span class="block font-semibold text-primary">
                                            {{ document.title }}
                                            <span v-if="document.is_required" class="text-error">*</span>
                                        </span>
                                        <span v-if="document.description" class="mt-1 block text-xs">{{ document.description }}</span>
                                        <a
                                            v-if="document.url"
                                            :href="document.url"
                                            target="_blank"
                                            rel="noreferrer"
                                            class="mt-2 inline-flex text-xs font-semibold text-air-blue hover:underline"
                                        >
                                            Dokument öffnen
                                        </a>
                                        <span v-else class="mt-2 block text-xs text-error">Kein Link hinterlegt</span>
                                        <span v-if="membershipRequestForm.errors[`accepted_documents.${document.id}`]" class="mt-1 block text-xs text-error">
                                            {{ membershipRequestForm.errors[`accepted_documents.${document.id}`] }}
                                        </span>
                                    </span>
                                </label>
                            </div>
                        </div>

                        <label class="block">
                            <span class="text-sm font-semibold text-primary">Nachricht</span>
                            <textarea v-model="membershipRequestForm.message" rows="4" class="mt-1 w-full rounded-lg border-border bg-inputBg text-primary" placeholder="Warum möchtest du Mitglied werden?"></textarea>
                        </label>
                    </div>

                    <button class="mt-5 w-full rounded-lg bg-buttonPrimary px-4 py-3 text-sm font-semibold text-buttonTextPrimary" :disabled="membershipRequestForm.processing">
                        Anfrage senden
                    </button>
                    </form>
                </div>
            </Teleport>
        </div>
    </AppLayout>
</template>

<style scoped>
.airmius-modal-scroll {
    scrollbar-width: thin;
    scrollbar-color: var(--air-blue, #60a5fa) rgba(15, 23, 42, 0.75);
}

.airmius-modal-scroll::-webkit-scrollbar {
    width: 10px;
}

.airmius-modal-scroll::-webkit-scrollbar-track {
    background: rgba(15, 23, 42, 0.75);
    border-radius: 999px;
}

.airmius-modal-scroll::-webkit-scrollbar-thumb {
    background: linear-gradient(180deg, var(--button-primary, #60a5fa), var(--air-blue, #38bdf8));
    border: 2px solid rgba(15, 23, 42, 0.75);
    border-radius: 999px;
}

.airmius-modal-scroll::-webkit-scrollbar-thumb:hover {
    background: linear-gradient(180deg, var(--button-primary-hover, #3b82f6), var(--air-blue, #38bdf8));
}
</style>
