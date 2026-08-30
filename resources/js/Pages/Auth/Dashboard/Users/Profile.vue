<script setup>
import { computed, ref, watch } from 'vue'
import { Head, Link, router, useForm } from '@inertiajs/vue3'
import AppLayout from '@/Components/Auth/Layouts/AppLayout.vue'
import SearchableSelect from '@/Components/SearchableSelect.vue'
import { useI18n } from 'vue-i18n'
import { confirmDialog } from '@/services/dialogService'

const props = defineProps({
    profileUser: Object,
    posts: { type: Array, default: () => [] },
    viewer: Object,
    sports: { type: Array, default: () => [] },
    activeTab: { type: String, default: 'overview' },
})

const { t, te, locale } = useI18n()
const friendshipStatus = ref(props.viewer.friendship_status)
const canSendFriendRequest = ref(props.viewer.can_send_friend_request)
const friendInvitationId = ref(props.viewer.friend_invitation_id)
const friendshipNotice = ref(null)
const friendshipProcessing = ref(false)
const profileActionMenuOpen = ref(false)
const activeProfileTab = ref(props.activeTab || 'overview')

watch(() => props.viewer, (viewer) => {
    friendshipStatus.value = viewer.friendship_status
    canSendFriendRequest.value = viewer.can_send_friend_request
    friendInvitationId.value = viewer.friend_invitation_id
})

watch(() => props.activeTab, (tab) => {
    activeProfileTab.value = tab || 'overview'
})

const follow = () => {
    router.post(route('auth.users.follow', props.profileUser.id), {}, { preserveScroll: true })
}

const unfollow = () => {
    router.delete(route('auth.users.unfollow', props.profileUser.id), { preserveScroll: true })
}

const sendFriendRequest = () => {
    const previous = {
        status: friendshipStatus.value,
        canSend: canSendFriendRequest.value,
        invitationId: friendInvitationId.value,
    }

    friendshipNotice.value = null
    friendshipProcessing.value = true
    friendshipStatus.value = 'sent'
    canSendFriendRequest.value = false

    router.post(route('auth.friends.invitations.store'), {
        user_id: props.profileUser.id,
    }, {
        preserveScroll: true,
        onSuccess: () => {
            friendshipNotice.value = { type: 'success', message: t('friends.request_sent') }
        },
        onError: (errors) => {
            friendshipStatus.value = previous.status
            canSendFriendRequest.value = previous.canSend
            friendInvitationId.value = previous.invitationId
            friendshipNotice.value = {
                type: 'error',
                message: errors.email || errors.user_id || t('friends.request_failed'),
            }
        },
        onFinish: () => {
            friendshipProcessing.value = false
        },
    })
}

const sendMessage = () => {
    router.post(route('auth.conversations.store'), {
        type: 'direct',
        participant_ids: [props.profileUser.id],
    })
}

const blockUser = async () => {
    const confirmed = await confirmDialog({
        title: t('friends.block_title'),
        message: t('friends.block_message', { name: props.profileUser.name }),
        confirmLabel: t('friends.block_confirm'),
        danger: true,
    })

    if (!confirmed) {
        return
    }

    router.post(route('auth.users.block', props.profileUser.id), {}, { preserveScroll: true })
}

const unblockUser = () => {
    router.delete(route('auth.users.unblock', props.profileUser.id), { preserveScroll: true })
}

const acceptFriendRequest = () => {
    if (!friendInvitationId.value) return

    const previous = {
        status: friendshipStatus.value,
        canSend: canSendFriendRequest.value,
        invitationId: friendInvitationId.value,
    }

    friendshipNotice.value = null
    friendshipProcessing.value = true
    friendshipStatus.value = 'friends'
    canSendFriendRequest.value = false

    window.axios.post(
        route('auth.friends.invitations.accept', friendInvitationId.value),
        {},
        { headers: { Accept: 'application/json' } },
    ).then(() => {
        friendInvitationId.value = null
        friendshipNotice.value = { type: 'success', message: t('friends.accepted') }
    }).catch((error) => {
        const errors = error.response?.data?.errors || {}

        friendshipStatus.value = previous.status
        canSendFriendRequest.value = previous.canSend
        friendInvitationId.value = previous.invitationId
        friendshipNotice.value = {
            type: 'error',
                message: errors.invitation?.[0]
                || error.response?.data?.message
                || t('friends.accept_failed'),
        }
    }).finally(() => {
        friendshipProcessing.value = false
    })
}

const removeFriend = async () => {
    const confirmed = await confirmDialog({
        title: t('friends.remove_title'),
        message: t('friends.remove_message', { name: props.profileUser.name }),
        confirmLabel: t('friends.end'),
        danger: true,
    })

    if (!confirmed) {
        return
    }

    router.delete(route('auth.friends.destroy', props.profileUser.id), { preserveScroll: true })
}

const formatDate = (value) => new Intl.DateTimeFormat(locale.value || 'de', { day: '2-digit', month: '2-digit', year: 'numeric' }).format(new Date(value))
const initials = (name) => (name || '?').split(' ').slice(0, 2).map((part) => part.charAt(0)).join('').toUpperCase()

const sportForm = useForm({
    sport_id: '',
    status: 'active',
    experience_level: 'beginner',
})

const recommendationForm = useForm({
    relationship: 'team_member',
    body: '',
})

const reportTargetOpen = ref(false)
const reportForm = useForm({
    type: 'user',
    id: props.profileUser.id,
    reason: 'other',
    details: '',
})

const openProfileReport = () => {
    profileActionMenuOpen.value = false
    reportForm.type = 'user'
    reportForm.id = props.profileUser.id
    reportForm.reason = 'other'
    reportForm.details = ''
    reportForm.clearErrors()
    reportTargetOpen.value = true
}

const closeProfileReport = () => {
    reportTargetOpen.value = false
    reportForm.reset()
}

const submitProfileReport = () => {
    reportForm.post(route('auth.reports.store'), {
        preserveScroll: true,
        onSuccess: closeProfileReport,
    })
}

const toggleProfileActionMenu = () => {
    profileActionMenuOpen.value = !profileActionMenuOpen.value
}

const skillForms = ref({})

const sportLabel = (sport) => {
    if (!sport) return 'Sportart'

    const key = `sports.${sport.slug}`

    return te(key) ? t(key) : sport.name
}

const statusLabel = (status) => ({
    active: 'Betreibe ich',
    wants_to_learn: 'Möchte ich lernen',
    coach: 'Trainiere ich',
    interested: 'Interessiert mich',
}[status] || status)

const levelLabel = (level) => ({
    beginner: 'Einsteiger',
    intermediate: 'Fortgeschritten',
    advanced: 'Erfahren',
    expert: 'Experte',
    learning: 'Lerne ich',
    developing: 'In Entwicklung',
    solid: 'Solide',
    strong: 'Stark',
}[level] || level)

const relationshipLabel = (relationship) => ({
    visitor: 'Besucher',
    friend: 'Freund',
    team_member: 'Teamkollege',
    trainer: 'Trainer',
    club_admin: 'Verein',
}[relationship] || relationship)

const trustTone = computed(() => {
    const trust = props.profileUser.gamification.trust_score

    if (trust >= 115) return 'text-air-green'
    if (trust < 90) return 'text-error'

    return 'text-air-blue'
})

const profileStats = computed(() => [
    { label: 'Follower', value: props.profileUser.followers_count },
    { label: 'Folgt', value: props.profileUser.following_count },
    { label: 'Beiträge', value: props.profileUser.posts_count },
    { label: 'Level', value: props.profileUser.gamification.level },
    { label: 'Heute XP', value: props.profileUser.gamification.earned_today },
])

const primarySportProfiles = computed(() => props.profileUser.sport_profiles.slice(0, 4))

const visibleBadges = computed(() => props.profileUser.badges.slice(0, 6))

const membershipCount = computed(() => props.profileUser.clubs.length + props.profileUser.teams.length)

const privacyLabel = computed(() => props.profileUser.profile_visibility === 'private' ? 'Privates Profil' : 'Öffentliches Profil')

const tabs = computed(() => [
    { key: 'overview', label: 'Übersicht', icon: 'las la-id-card' },
    { key: 'sports', label: 'Sportarten', icon: 'las la-running' },
    { key: 'skills', label: 'Skills', icon: 'las la-medal' },
    { key: 'posts', label: 'Beiträge', icon: 'las la-stream' },
    { key: 'network', label: 'Netzwerk', icon: 'las la-users' },
    { key: 'recommendations', label: 'Empfehlungen', icon: 'las la-star' },
])

const selectProfileTab = (tab) => {
    activeProfileTab.value = tab

    if (typeof window === 'undefined') {
        return
    }

    const url = new URL(window.location.href)
    url.searchParams.set('tab', tab)
    window.history.replaceState({}, '', url)
}

const groupedSkills = computed(() => {
    return props.profileUser.sport_skills.reduce((groups, skill) => {
        const key = skill.sport?.id || 'other'
        groups[key] ??= {
            sport: skill.sport,
            skills: [],
        }
        groups[key].skills.push(skill)

        return groups
    }, {})
})

const skillFormFor = (skill) => {
    skillForms.value[skill.id] ??= useForm({
        self_level: skill.self_level || 'learning',
        is_visible: true,
        notes: skill.notes || '',
        relationship: 'team_member',
        level: 'confirmed',
        comment: '',
    })

    return skillForms.value[skill.id]
}

const addSport = () => {
    sportForm.post(route('auth.profile.sports.store'), {
        preserveScroll: true,
        onSuccess: () => sportForm.reset('sport_id'),
    })
}

const updateSkill = (skill) => {
    const form = skillFormFor(skill)

    form.put(route('auth.profile.skills.update', skill.id), {
        preserveScroll: true,
    })
}

const endorseSkill = (skill) => {
    const form = skillFormFor(skill)

    form.post(route('auth.users.skills.endorse', [props.profileUser.id, skill.id]), {
        preserveScroll: true,
        onSuccess: () => form.reset('comment'),
    })
}

const sendRecommendation = () => {
    recommendationForm.post(route('auth.users.recommendations.store', props.profileUser.id), {
        preserveScroll: true,
        onSuccess: () => recommendationForm.reset('body'),
    })
}

const approveRecommendation = (recommendation) => {
    router.put(route('auth.profile.recommendations.approve', recommendation.id), {}, { preserveScroll: true })
}

const rejectRecommendation = (recommendation) => {
    router.put(route('auth.profile.recommendations.reject', recommendation.id), {}, { preserveScroll: true })
}
</script>

<template>
    <AppLayout>
        <Head :title="profileUser.name" />

        <div class="mx-auto max-w-7xl space-y-6">
            <section class="overflow-hidden rounded-xl border border-border bg-card shadow-sm">
                <div class="relative h-32 bg-[color:var(--surface-strong)] sm:h-40">
                    <div class="absolute inset-0 opacity-90" style="background: linear-gradient(135deg, color-mix(in srgb, var(--buttonPrimary) 34%, transparent), color-mix(in srgb, var(--accent-2) 18%, transparent) 52%, color-mix(in srgb, var(--accent-3) 18%, transparent));"></div>
                    <div class="absolute inset-x-0 bottom-0 h-24" style="background: linear-gradient(180deg, transparent, var(--card));"></div>
                </div>

                <div class="px-4 pb-6 sm:px-6">
                    <div class="grid gap-5 lg:grid-cols-[1fr_auto] lg:items-start">
                        <div class="flex min-w-0 flex-col gap-4 sm:flex-row sm:items-start">
                            <div class="relative z-10 -mt-14 shrink-0 sm:-mt-16">
                                <img
                                    v-if="profileUser.profile_photo_url"
                                    :src="profileUser.profile_photo_url"
                                    :alt="profileUser.name"
                                    class="size-28 rounded-xl border-4 border-card object-cover shadow-lg sm:size-32"
                                />
                                <div
                                    v-else
                                    class="flex size-28 items-center justify-center rounded-xl border-4 border-card bg-buttonPrimary text-3xl font-bold text-buttonTextPrimary shadow-lg sm:size-32 sm:text-4xl"
                                >
                                    {{ initials(profileUser.name) }}
                                </div>
                            </div>

                            <div class="min-w-0 pt-1 sm:pt-4">
                                <div class="flex flex-wrap items-center gap-2">
                                    <span class="rounded-full border border-border bg-card px-3 py-1 text-xs font-semibold text-secondary">
                                        {{ privacyLabel }}
                                    </span>
                                    <span v-if="profileUser.gamification.rank" class="rounded-full bg-buttonPrimary px-3 py-1 text-xs font-bold text-buttonTextPrimary">
                                        {{ profileUser.gamification.rank }}
                                    </span>
                                    <span v-if="viewer.friendship_status === 'friends'" class="rounded-full border border-success/40 bg-success/10 px-3 py-1 text-xs font-semibold text-success">
                                        Befreundet
                                    </span>
                                </div>

                                <h1 class="mt-3 break-words text-2xl font-bold leading-tight tracking-normal text-primary sm:text-4xl">
                                    {{ profileUser.name }}
                                </h1>

                                <div class="mt-2 flex flex-wrap items-center gap-x-4 gap-y-2 text-sm text-secondary">
                                    <span v-if="profileUser.email" class="inline-flex items-center gap-1.5">
                                        <i class="las la-envelope text-base"></i>
                                        {{ profileUser.email }}
                                    </span>
                                    <span v-if="profileUser.athlete_license_number" class="inline-flex items-center gap-1.5">
                                        <i class="las la-id-card text-base"></i>
                                        Lizenz {{ profileUser.athlete_license_number }}
                                    </span>
                                    <span class="inline-flex items-center gap-1.5">
                                        <i class="las la-users text-base"></i>
                                        {{ membershipCount }} Bereiche
                                    </span>
                                </div>

                                <div v-if="viewer.can_view_private_profile" class="mt-4 flex flex-wrap gap-2">
                                    <span
                                        v-for="profile in primarySportProfiles"
                                        :key="profile.id"
                                        class="rounded-full border border-border bg-inputBg px-3 py-1.5 text-xs font-semibold text-primary"
                                    >
                                        {{ sportLabel(profile.sport) }} - {{ levelLabel(profile.experience_level) }}
                                    </span>
                                    <span v-if="!primarySportProfiles.length" class="rounded-full border border-border bg-inputBg px-3 py-1.5 text-xs font-semibold text-secondary">
                                        Keine Sportarten hinterlegt
                                    </span>
                                </div>
                            </div>
                        </div>

                        <div class="flex w-full flex-col gap-2 lg:w-auto lg:items-end">
                            <Link
                                v-if="viewer.is_self"
                                :href="route('profile.show')"
                                class="inline-flex items-center justify-center gap-2 rounded-xl bg-buttonPrimary px-4 py-2.5 text-sm font-semibold text-buttonTextPrimary hover:bg-buttonPrimaryHover"
                            >
                                <i class="las la-user-edit text-lg"></i>
                                Profil bearbeiten
                            </Link>

                            <template v-else>
                                <div class="relative flex w-full flex-wrap items-stretch gap-2 lg:w-auto lg:justify-end">
                                <button
                                v-if="viewer.can_send_message"
                                type="button"
                                class="inline-flex min-h-11 min-w-[7.25rem] flex-1 items-center justify-center gap-1 rounded-xl bg-buttonPrimary px-2.5 py-2 text-xs font-bold text-buttonTextPrimary hover:bg-buttonPrimaryHover sm:gap-2 sm:px-4 sm:text-sm lg:flex-none"
                                @click="sendMessage"
                            >
                                <i class="las la-comment text-lg"></i>
                                <span>Nachricht</span>
                            </button>
                                <span v-else-if="viewer.is_blocked" class="inline-flex min-h-11 min-w-[7.25rem] flex-1 items-center justify-center rounded-xl border border-border px-2.5 py-2 text-center text-xs text-secondary sm:px-4 sm:text-sm lg:flex-none">
                                    Nachrichten blockiert
                                </span>

                                <Link
                                    v-if="friendshipStatus === 'friends'"
                                    :href="route('auth.challenges.index', { invite_user: profileUser.id })"
                                    class="inline-flex min-h-11 min-w-[7.25rem] flex-1 items-center justify-center gap-1 rounded-xl border border-air-blue/40 bg-air-blue/10 px-2.5 py-2 text-xs font-bold text-air-blue hover:bg-air-blue/15 sm:gap-2 sm:px-4 sm:text-sm lg:flex-none"
                                >
                                    <i class="las la-flag-checkered text-lg"></i>
                                    <span>Challenge</span>
                                </Link>

                                <button
                                v-if="viewer.can_follow && !viewer.is_following"
                                type="button"
                                class="inline-flex min-h-11 min-w-[7.25rem] flex-1 items-center justify-center gap-1 rounded-xl border border-border bg-card px-2.5 py-2 text-xs font-bold text-primary hover:border-borderHover sm:gap-2 sm:px-4 sm:text-sm lg:flex-none"
                                @click="follow"
                            >
                                    <i class="las la-plus text-lg"></i>
                                    <span>Folgen</span>
                                </button>
                                <button
                                v-else-if="viewer.can_follow"
                                type="button"
                                class="inline-flex min-h-11 min-w-[7.25rem] flex-1 items-center justify-center gap-1 rounded-xl border border-border bg-card px-2.5 py-2 text-xs font-bold text-primary hover:border-borderHover sm:gap-2 sm:px-4 sm:text-sm lg:flex-none"
                                @click="unfollow"
                            >
                                    <i class="las la-user-minus text-lg"></i>
                                    <span>Entfolgen</span>
                                </button>

                                <button
                                v-if="canSendFriendRequest"
                                type="button"
                                :disabled="friendshipProcessing"
                                class="inline-flex min-h-11 min-w-[7.25rem] flex-1 items-center justify-center gap-1 rounded-xl border border-border bg-card px-2.5 py-2 text-xs font-bold text-primary hover:border-borderHover disabled:cursor-wait disabled:opacity-70 sm:gap-2 sm:px-4 sm:text-sm lg:flex-none"
                                @click="sendFriendRequest"
                            >
                                    <i class="las la-user-plus text-lg"></i>
                                    <span>Freund</span>
                                </button>
                                <button
                                v-else-if="friendshipStatus === 'received'"
                                type="button"
                                :disabled="friendshipProcessing"
                                class="inline-flex min-h-11 min-w-[7.25rem] flex-1 items-center justify-center gap-1 rounded-xl bg-buttonPrimary px-2.5 py-2 text-xs font-bold text-buttonTextPrimary hover:bg-buttonPrimaryHover disabled:cursor-wait disabled:opacity-70 sm:gap-2 sm:px-4 sm:text-sm lg:flex-none"
                                @click="acceptFriendRequest"
                            >
                                    <i class="las la-check text-lg"></i>
                                    <span>Annehmen</span>
                                </button>
                                <span v-else-if="friendshipStatus === 'sent'" class="inline-flex min-h-11 min-w-[7.25rem] flex-1 items-center justify-center gap-1 rounded-xl border border-success/35 bg-success/10 px-2.5 py-2 text-center text-xs font-bold text-success sm:gap-2 sm:px-4 sm:text-sm lg:flex-none">
                                    <i class="las la-check-circle text-lg"></i>
                                    <span>Anfrage</span>
                                </span>
                                <button
                                    v-else-if="friendshipStatus === 'friends'"
                                    type="button"
                                    class="inline-flex min-h-11 min-w-[7.25rem] flex-1 items-center justify-center gap-1 rounded-xl border border-error/40 px-2.5 py-2 text-xs font-bold text-error hover:bg-error/10 sm:gap-2 sm:px-4 sm:text-sm lg:flex-none"
                                    @click="removeFriend"
                                >
                                    <i class="las la-user-times text-lg"></i>
                                    <span>{{ $t('Entfernen') }}</span>
                                </button>

                                <button
                                    type="button"
                                    class="inline-flex min-h-11 w-11 shrink-0 items-center justify-center rounded-xl border border-border bg-card text-primary hover:border-borderHover"
                                    aria-label="Weitere Aktionen"
                                    @click="toggleProfileActionMenu"
                                >
                                    <i class="las la-ellipsis-v text-xl"></i>
                                </button>
                                <div
                                    v-if="profileActionMenuOpen"
                                    class="absolute right-0 top-full z-20 mt-2 w-48 overflow-hidden rounded-2xl border border-border bg-card shadow-xl"
                                >
                                    <button
                                        type="button"
                                        class="flex w-full items-center gap-2 px-4 py-3 text-left text-sm font-bold text-primary hover:bg-inputBg"
                                        @click="openProfileReport"
                                    >
                                        <i class="las la-flag text-lg"></i>
                                        Melden
                                    </button>
                                    <button
                                        v-if="viewer.has_blocked"
                                        type="button"
                                        class="flex w-full items-center gap-2 px-4 py-3 text-left text-sm font-bold text-primary hover:bg-inputBg"
                                        @click="profileActionMenuOpen = false; unblockUser()"
                                    >
                                        <i class="las la-unlock text-lg"></i>
                                        Entblockieren
                                    </button>
                                    <button
                                        v-else
                                        type="button"
                                        class="flex w-full items-center gap-2 px-4 py-3 text-left text-sm font-bold text-error hover:bg-error/10"
                                        @click="profileActionMenuOpen = false; blockUser()"
                                    >
                                        <i class="las la-ban text-lg"></i>
                                        Blockieren
                                    </button>
                                </div>
                                </div>
                                <p
                                    v-if="friendshipNotice"
                                    :class="[
                                        'rounded-xl border px-3 py-2 text-center text-xs font-bold lg:w-full lg:border-0 lg:p-0 lg:text-left lg:text-sm',
                                        friendshipNotice.type === 'error' ? 'text-error' : 'text-success',
                                        friendshipNotice.type === 'error' ? 'border-error/30 bg-error/10' : 'border-success/30 bg-success/10',
                                    ]"
                                >
                                    {{ friendshipNotice.message }}
                                </p>
                            </template>
                        </div>
                    </div>
                </div>
            </section>

            <template v-if="viewer.can_view_private_profile">
                <section class="grid grid-cols-2 gap-2 sm:grid-cols-3 lg:grid-cols-5 xl:grid-cols-5">
                    <div
                        v-for="stat in profileStats"
                        :key="stat.label"
                        class="rounded-xl border border-border bg-card p-3 shadow-sm sm:p-4"
                    >
                        <div class="text-xl font-bold text-primary sm:text-2xl">{{ stat.value }}</div>
                        <div class="mt-1 text-[11px] font-semibold uppercase text-secondary sm:text-xs">{{ stat.label }}</div>
                    </div>
                </section>

                <nav class="rounded-xl border border-border bg-card p-2 shadow-sm">
                    <div class="grid grid-cols-2 gap-2 sm:flex sm:min-w-max">
                        <button
                            v-for="tab in tabs"
                            :key="tab.key"
                            type="button"
                            :class="[
                                'inline-flex min-h-10 items-center justify-center gap-1.5 rounded-lg px-2 py-2 text-xs font-semibold transition sm:gap-2 sm:px-4 sm:text-sm',
                                activeProfileTab === tab.key
                                    ? 'bg-buttonPrimary text-buttonTextPrimary'
                                    : 'text-secondary hover:bg-inputBg hover:text-primary',
                            ]"
                            @click="selectProfileTab(tab.key)"
                        >
                            <i :class="[tab.icon, 'text-lg']"></i>
                            {{ tab.label }}
                        </button>
                    </div>
                </nav>

                <section :class="['grid gap-6', activeProfileTab === 'overview' ? 'xl:grid-cols-[minmax(0,1fr)_360px]' : '']">
                    <div v-if="['overview', 'sports', 'skills', 'posts'].includes(activeProfileTab)" class="space-y-6">
                        <section v-if="activeProfileTab === 'overview'" class="overflow-hidden rounded-xl border border-border bg-card shadow-sm">
                            <div class="grid lg:grid-cols-[1.25fr_.75fr]">
                                <div class="p-5 sm:p-6">
                                    <div class="flex flex-wrap items-center gap-2">
                                        <span class="rounded-full bg-buttonPrimary px-3 py-1 text-xs font-bold text-buttonTextPrimary">
                                            {{ profileUser.gamification.title }}
                                        </span>
                                        <span class="rounded-full border border-border bg-inputBg px-3 py-1 text-xs font-semibold text-secondary">
                                            {{ profileUser.gamification.streak_days }} Tage Streak
                                        </span>
                                        <span class="rounded-full border border-border bg-inputBg px-3 py-1 text-xs font-semibold text-secondary">
                                            {{ profileUser.gamification.health_label }}
                                        </span>
                                    </div>

                                    <div class="mt-5 flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between">
                                        <div>
                                            <div class="text-sm font-semibold uppercase tracking-wide text-secondary">Fortschritt</div>
                                            <div class="mt-1 text-3xl font-bold text-primary">Level {{ profileUser.gamification.level }}</div>
                                            <div class="mt-1 text-sm text-secondary">
                                                {{ profileUser.gamification.xp }} XP von {{ profileUser.gamification.next_level_xp }} XP
                                            </div>
                                            <div class="mt-1 text-xs font-semibold uppercase tracking-wide text-secondary">
                                                Noch {{ profileUser.gamification.xp_to_next_level }} XP bis zum nächsten Level
                                            </div>
                                        </div>
                                        <div class="rounded-xl border border-border bg-inputBg px-4 py-3">
                                            <div :class="['text-2xl font-bold', trustTone]">{{ profileUser.gamification.trust_score }}</div>
                                            <div class="text-xs font-semibold uppercase tracking-wide text-secondary">Trust Score</div>
                                            <div class="mt-1 text-xs text-secondary">Heute {{ profileUser.gamification.earned_today }} XP</div>
                                        </div>
                                    </div>

                                    <div class="mt-5 h-3 overflow-hidden rounded-full bg-inputBg">
                                        <div class="h-full rounded-full bg-buttonPrimary" :style="{ width: `${profileUser.gamification.progress}%` }"></div>
                                    </div>
                                </div>

                                <div class="border-t border-border bg-bg p-5 sm:p-6 lg:border-l lg:border-t-0">
                                    <div class="text-sm font-semibold uppercase tracking-wide text-secondary">{{ $t('Badges') }}</div>
                                    <div class="mt-4 grid grid-cols-2 gap-3">
                                        <div
                                            v-for="badge in visibleBadges"
                                            :key="badge.id"
                                            class="rounded-xl border border-border bg-card p-3"
                                        >
                                            <div class="text-2xl text-primary">
                                                <i :class="badge.icon || 'las la-medal'"></i>
                                            </div>
                                            <div class="mt-2 line-clamp-2 text-sm font-semibold text-primary">{{ badge.name }}</div>
                                        </div>
                                        <p v-if="!visibleBadges.length" class="col-span-2 text-sm text-secondary">Noch keine Badges sichtbar.</p>
                                    </div>
                                </div>
                            </div>
                        </section>

                        <section v-if="activeProfileTab === 'overview'" class="rounded-xl border border-border bg-card p-5 shadow-sm sm:p-6">
                            <div class="flex items-center justify-between gap-4">
                                <div>
                                    <h2 class="text-lg font-bold text-primary">{{ $t('Profil') }}</h2>
                                    <p class="mt-1 text-sm text-secondary">Bio, Sportarten und öffentliche Einordnung.</p>
                                </div>
                            </div>

                            <p v-if="profileUser.bio" class="mt-5 whitespace-pre-line text-sm leading-7 text-primary">
                                {{ profileUser.bio }}
                            </p>
                            <p v-else class="mt-5 rounded-xl border border-dashed border-border bg-bg p-4 text-sm text-secondary">
                                Dieses Profil hat noch keine Bio.
                            </p>
                        </section>

                        <section v-if="activeProfileTab === 'sports'" class="rounded-xl border border-border bg-card p-5 shadow-sm sm:p-6">
                            <div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
                                <div>
                                    <h2 class="text-lg font-bold text-primary">Sportliches Profil</h2>
                                    <p class="mt-1 text-sm text-secondary">Sportarten, Ziele und Erfahrungslevel.</p>
                                </div>
                                <span class="rounded-full bg-inputBg px-3 py-1 text-xs font-semibold text-secondary">
                                    {{ profileUser.sport_profiles.length }} Sportarten
                                </span>
                            </div>

                            <form
                                v-if="viewer.is_self"
                                class="mt-5 grid gap-3 rounded-xl border border-border bg-bg p-4 md:grid-cols-[1fr_170px_170px_auto]"
                                @submit.prevent="addSport"
                            >
                                <SearchableSelect
                                    v-model="sportForm.sport_id"
                                    :options="sports"
                                    value-key="id"
                                    translation-prefix="sports"
                                    category-translation-prefix="sport_categories"
                                    placeholder="Sportart suchen"
                                />
                                <select v-model="sportForm.status" class="rounded-lg border border-border bg-inputBg px-3 py-2 text-sm text-primary">
                                    <option value="active">Betreibe ich</option>
                                    <option value="wants_to_learn">Möchte ich lernen</option>
                                    <option value="coach">Trainiere ich</option>
                                    <option value="interested">Interessiert mich</option>
                                </select>
                                <select v-model="sportForm.experience_level" class="rounded-lg border border-border bg-inputBg px-3 py-2 text-sm text-primary">
                                    <option value="beginner">Einsteiger</option>
                                    <option value="intermediate">Fortgeschritten</option>
                                    <option value="advanced">Erfahren</option>
                                    <option value="expert">Experte</option>
                                </select>
                                <button class="rounded-lg bg-buttonPrimary px-4 py-2 text-sm font-semibold text-buttonTextPrimary hover:bg-buttonPrimaryHover">
                                    Hinzufügen
                                </button>
                            </form>

                            <div class="mt-5 grid gap-3 sm:grid-cols-2">
                                <article
                                    v-for="profile in profileUser.sport_profiles"
                                    :key="profile.id"
                                    class="rounded-xl border border-border bg-bg p-4"
                                >
                                    <div class="flex items-start justify-between gap-3">
                                        <div>
                                            <h3 class="font-semibold text-primary">{{ sportLabel(profile.sport) }}</h3>
                                            <p class="mt-1 text-sm text-secondary">{{ statusLabel(profile.status) }}</p>
                                        </div>
                                        <span class="rounded-full bg-inputBg px-3 py-1 text-xs font-semibold text-primary">
                                            {{ levelLabel(profile.experience_level) }}
                                        </span>
                                    </div>
                                    <div v-if="profile.performance_metrics?.length" class="mt-4 grid gap-2">
                                        <div
                                            v-for="metric in profile.performance_metrics"
                                            :key="metric.key"
                                            class="rounded-lg border border-border bg-card px-3 py-2"
                                        >
                                            <p class="text-[11px] font-semibold uppercase tracking-wide text-secondary">
                                                {{ metric.label }}
                                            </p>
                                            <p class="mt-1 break-words text-sm font-semibold text-primary">
                                                {{ metric.value }}
                                            </p>
                                        </div>
                                    </div>
                                </article>
                                <p v-if="!profileUser.sport_profiles.length" class="text-sm text-secondary">Noch keine Sportarten hinterlegt.</p>
                            </div>
                        </section>

                        <section v-if="activeProfileTab === 'skills'" class="rounded-xl border border-border bg-card p-5 shadow-sm sm:p-6">
                            <div>
                                <h2 class="text-lg font-bold text-primary">Skills & Bestätigungen</h2>
                                <p class="mt-1 text-sm text-secondary">Skills entstehen aus den gewählten Sportarten und können bestätigt werden.</p>
                            </div>

                            <div class="mt-5 space-y-5">
                                <article
                                    v-for="group in groupedSkills"
                                    :key="group.sport?.id || 'other'"
                                    class="rounded-xl border border-border bg-bg p-4"
                                >
                                    <h3 class="font-semibold text-primary">{{ sportLabel(group.sport) }}</h3>

                                    <div class="mt-4 grid gap-3">
                                        <div
                                            v-for="skill in group.skills"
                                            :key="skill.id"
                                            class="rounded-xl border border-border bg-card p-4"
                                        >
                                            <div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
                                                <div>
                                                    <h4 class="font-semibold text-primary">{{ skill.skill.name }}</h4>
                                                    <p class="mt-1 text-sm text-secondary">{{ skill.skill.description }}</p>
                                                    <p class="mt-2 text-xs font-semibold text-secondary">
                                                        Eigenes Level: {{ levelLabel(skill.self_level) }} - {{ skill.endorsements_count }} Bestätigungen
                                                    </p>
                                                </div>

                                                <button
                                                    v-if="!viewer.is_self"
                                                    type="button"
                                                    class="rounded-lg border border-border px-3 py-2 text-sm font-semibold text-primary hover:bg-inputBg disabled:opacity-60"
                                                    :disabled="skill.viewer_has_endorsed"
                                                    @click="endorseSkill(skill)"
                                                >
                                                    {{ skill.viewer_has_endorsed ? 'Bestätigt' : 'Bestätigen' }}
                                                </button>
                                            </div>

                                            <form v-if="viewer.is_self" class="mt-3 grid gap-2 sm:grid-cols-[180px_1fr_auto]" @submit.prevent="updateSkill(skill)">
                                                <select v-model="skillFormFor(skill).self_level" class="rounded-lg border border-border bg-inputBg px-3 py-2 text-sm text-primary">
                                                    <option value="learning">Lerne ich</option>
                                                    <option value="developing">In Entwicklung</option>
                                                    <option value="solid">Solide</option>
                                                    <option value="strong">Stark</option>
                                                    <option value="expert">Experte</option>
                                                </select>
                                                <input v-model="skillFormFor(skill).notes" class="rounded-lg border border-border bg-inputBg px-3 py-2 text-sm text-primary" placeholder="Kurze Notiz, optional" />
                                                <button class="rounded-lg bg-buttonPrimary px-3 py-2 text-sm font-semibold text-buttonTextPrimary">Speichern</button>
                                            </form>

                                            <form v-else-if="!skill.viewer_has_endorsed" class="mt-3 grid gap-2 sm:grid-cols-[150px_150px_1fr]" @submit.prevent="endorseSkill(skill)">
                                                <select v-model="skillFormFor(skill).relationship" class="rounded-lg border border-border bg-inputBg px-3 py-2 text-sm text-primary">
                                                    <option value="visitor">Besucher</option>
                                                    <option value="friend">Freund</option>
                                                    <option value="team_member">Teamkollege</option>
                                                    <option value="trainer">Trainer</option>
                                                    <option value="club_admin">Verein</option>
                                                </select>
                                                <select v-model="skillFormFor(skill).level" class="rounded-lg border border-border bg-inputBg px-3 py-2 text-sm text-primary">
                                                    <option value="confirmed">Kann ich bestätigen</option>
                                                    <option value="good">Gut</option>
                                                    <option value="strong">Stark</option>
                                                    <option value="exceptional">Außergewöhnlich</option>
                                                </select>
                                                <input v-model="skillFormFor(skill).comment" class="rounded-lg border border-border bg-inputBg px-3 py-2 text-sm text-primary" placeholder="Kommentar, optional" />
                                            </form>

                                            <div v-if="skill.endorsements.length" class="mt-3 flex flex-wrap gap-2">
                                                <span v-for="endorsement in skill.endorsements" :key="endorsement.id" class="rounded-full bg-inputBg px-3 py-1 text-xs text-secondary">
                                                    {{ endorsement.endorser.name }} - {{ relationshipLabel(endorsement.relationship) }}
                                                </span>
                                            </div>
                                        </div>
                                    </div>
                                </article>

                                <p v-if="!profileUser.sport_skills.length" class="rounded-xl border border-dashed border-border bg-bg p-4 text-sm text-secondary">
                                    Sobald Sportarten hinzugefügt werden, erscheinen hier passende Skills.
                                </p>
                            </div>
                        </section>

                        <section v-if="activeProfileTab === 'posts'" class="rounded-xl border border-border bg-card p-5 shadow-sm sm:p-6">
                            <div class="flex items-center justify-between gap-4">
                                <div>
                                    <h2 class="text-lg font-bold text-primary">Aktuelle Beiträge</h2>
                                    <p class="mt-1 text-sm text-secondary">Die letzten sichtbaren Aktivitäten dieses Profils.</p>
                                </div>
                            </div>

                            <div class="mt-5 space-y-3">
                                <article v-for="post in posts" :key="post.id" class="rounded-xl border border-border bg-bg p-4">
                                    <div class="flex flex-wrap items-center gap-2 text-xs text-secondary">
                                        <Link v-if="post.team" :href="route('auth.teams.show', post.team.id)" class="font-semibold text-primary hover:underline">{{ post.team.name }}</Link>
                                        <Link v-else-if="post.club" :href="route('auth.clubs.show', post.club.id)" class="font-semibold text-primary hover:underline">{{ post.club.name }}</Link>
                                        <span v-else class="font-semibold text-primary">Public</span>
                                        <span>- {{ formatDate(post.created_at) }}</span>
                                    </div>
                                    <p class="mt-3 whitespace-pre-line text-sm leading-6 text-primary">{{ post.content }}</p>
                                    <div class="mt-3 flex gap-4 text-xs text-secondary">
                                        <span>{{ post.likes_count }} Likes</span>
                                        <span>{{ post.comments_count }} Kommentare</span>
                                    </div>
                                </article>
                                <p v-if="!posts.length" class="rounded-xl border border-dashed border-border bg-bg p-4 text-sm text-secondary">
                                    Keine sichtbaren Beiträge vorhanden.
                                </p>
                            </div>
                        </section>
                    </div>

                    <aside v-if="['overview', 'network', 'recommendations'].includes(activeProfileTab)" class="space-y-6">
                        <section v-if="activeProfileTab === 'network' || activeProfileTab === 'overview'" class="rounded-xl border border-border bg-card p-5 shadow-sm">
                            <h2 class="text-sm font-bold uppercase tracking-wide text-secondary">Teams</h2>
                            <div class="mt-4 space-y-2">
                                <Link v-for="team in profileUser.teams" :key="team.id" :href="route('auth.teams.show', team.id)" class="flex items-center gap-3 rounded-lg p-2 hover:bg-inputBg">
                                    <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-lg bg-buttonPrimary text-sm font-semibold text-buttonTextPrimary">{{ initials(team.name) }}</div>
                                    <span class="min-w-0 truncate text-sm font-semibold text-primary">{{ team.name }}</span>
                                </Link>
                                <p v-if="!profileUser.teams.length" class="text-sm text-secondary">Keine Teams sichtbar.</p>
                            </div>
                        </section>

                        <section v-if="activeProfileTab === 'network' || activeProfileTab === 'overview'" class="rounded-xl border border-border bg-card p-5 shadow-sm">
                            <h2 class="text-sm font-bold uppercase tracking-wide text-secondary">Vereine</h2>
                            <div class="mt-4 space-y-2">
                                <Link v-for="club in profileUser.clubs" :key="club.id" :href="route('auth.clubs.show', club.id)" class="flex items-center gap-3 rounded-lg p-2 hover:bg-inputBg">
                                    <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-lg bg-inputBg text-sm font-semibold text-primary">{{ initials(club.name) }}</div>
                                    <span class="min-w-0 truncate text-sm font-semibold text-primary">{{ club.name }}</span>
                                </Link>
                                <p v-if="!profileUser.clubs.length" class="text-sm text-secondary">Keine Vereine sichtbar.</p>
                            </div>
                        </section>

                        <section v-if="activeProfileTab === 'recommendations'" class="rounded-xl border border-border bg-card p-5 shadow-sm">
                            <h2 class="text-sm font-bold uppercase tracking-wide text-secondary">Empfehlungen</h2>

                            <form v-if="!viewer.is_self" class="mt-4 space-y-3 rounded-xl border border-border bg-bg p-4" @submit.prevent="sendRecommendation">
                                <div>
                                    <select v-model="recommendationForm.relationship" class="w-full rounded-lg border border-border bg-inputBg px-3 py-2 text-sm text-primary">
                                        <option value="visitor">Besucher</option>
                                        <option value="friend">Freund</option>
                                        <option value="team_member">Teamkollege</option>
                                        <option value="trainer">Trainer</option>
                                        <option value="club_admin">Verein</option>
                                    </select>
                                    <p v-if="recommendationForm.errors.relationship" class="mt-1 text-xs text-error">{{ recommendationForm.errors.relationship }}</p>
                                </div>

                                <div>
                                    <textarea v-model="recommendationForm.body" rows="4" class="w-full rounded-lg border border-border bg-inputBg px-3 py-2 text-sm text-primary" placeholder="Empfehlung schreiben"></textarea>
                                    <p class="mt-1 text-xs" :class="recommendationForm.errors.body ? 'text-error' : 'text-secondary'">
                                        {{ recommendationForm.errors.body || 'Mindestens 20 Zeichen.' }}
                                    </p>
                                </div>

                                <button
                                    class="w-full rounded-lg bg-buttonPrimary px-4 py-2 text-sm font-semibold text-buttonTextPrimary disabled:opacity-60"
                                    :disabled="recommendationForm.processing"
                                >
                                    {{ recommendationForm.processing ? 'Wird gesendet...' : 'Senden' }}
                                </button>
                            </form>

                            <div class="mt-4 space-y-3">
                                <article v-for="recommendation in profileUser.recommendations" :key="recommendation.id" class="rounded-xl border border-border bg-bg p-4">
                                    <p class="text-sm leading-relaxed text-primary">{{ recommendation.body }}</p>
                                    <p class="mt-3 text-xs text-secondary">
                                        {{ recommendation.author.name }} - {{ relationshipLabel(recommendation.relationship) }} - {{ recommendation.status === 'pending' ? 'wartet auf Freigabe' : 'veröffentlicht' }}
                                    </p>
                                    <div v-if="viewer.is_self && recommendation.status === 'pending'" class="mt-3 flex gap-2">
                                        <button class="rounded-lg bg-buttonPrimary px-3 py-2 text-sm text-buttonTextPrimary" @click="approveRecommendation(recommendation)">Freigeben</button>
                                        <button class="rounded-lg border border-border px-3 py-2 text-sm text-primary" @click="rejectRecommendation(recommendation)">Ablehnen</button>
                                    </div>
                                </article>
                                <p v-if="!profileUser.recommendations.length" class="text-sm text-secondary">Noch keine Empfehlungen sichtbar.</p>
                            </div>
                        </section>
                    </aside>
                </section>
            </template>

            <section v-else class="rounded-xl border border-border bg-card p-6 text-center shadow-sm">
                <div class="mx-auto flex size-14 items-center justify-center rounded-xl bg-inputBg text-2xl text-secondary">
                    <i class="las la-lock"></i>
                </div>
                <h2 class="mt-4 text-xl font-bold text-primary">Dieses Profil ist privat</h2>
                <p class="mx-auto mt-2 max-w-xl text-sm leading-6 text-secondary">
                    Details, Beiträge, Teams und Sportprofil sind nur für berechtigte Personen sichtbar.
                </p>
            </section>

            <section v-if="viewer.can_manage_roles" class="rounded-xl border border-border bg-card p-5 shadow-sm">
                <h2 class="text-lg font-bold text-primary">Rollen & Berechtigungen</h2>
                <div class="mt-4 grid gap-4 text-sm md:grid-cols-2">
                    <div class="rounded-xl border border-border bg-bg p-4">
                        <div class="font-semibold text-primary">{{ $t('Rollen') }}</div>
                        <div class="mt-2 text-secondary">{{ profileUser.roles.length ? profileUser.roles.join(', ') : 'Keine Rollen' }}</div>
                    </div>
                    <div class="rounded-xl border border-border bg-bg p-4">
                        <div class="font-semibold text-primary">Berechtigungen</div>
                        <div class="mt-2 max-h-32 overflow-auto text-secondary">
                            {{ profileUser.permissions.length ? profileUser.permissions.join(', ') : 'Keine Berechtigungen' }}
                        </div>
                    </div>
                </div>
            </section>

            <div
                v-if="reportTargetOpen"
                class="fixed inset-0 z-[80] flex items-center justify-center bg-black/60 p-4"
                @click.self="closeProfileReport"
            >
                <form class="w-full max-w-lg rounded-xl border border-border bg-card p-5 shadow-xl" @submit.prevent="submitProfileReport">
                    <div class="flex items-start justify-between gap-4">
                        <div>
                            <p class="text-xs font-semibold uppercase tracking-wide text-secondary">Profil melden</p>
                            <h2 class="mt-1 text-xl font-semibold text-primary">Warum soll dieses Profil geprüft werden?</h2>
                        </div>
                        <button type="button" class="rounded-lg p-2 text-secondary hover:bg-muted" @click="closeProfileReport">
                            <i class="las la-times"></i>
                        </button>
                    </div>

                    <div class="mt-4 space-y-4">
                        <select v-model="reportForm.reason" class="w-full rounded-lg border-border bg-inputBg text-primary">
                            <option value="insult">Beleidigung</option>
                            <option value="bullying">Mobbing</option>
                            <option value="hate">Hassrede</option>
                            <option value="sexual">Sexueller Inhalt</option>
                            <option value="violence">Gewalt</option>
                            <option value="threat">Drohung</option>
                            <option value="image_rights">Bild ohne Zustimmung</option>
                            <option value="spam">Spam</option>
                            <option value="other">Sonstiges</option>
                        </select>
                        <p v-if="reportForm.errors.reason" class="text-sm text-error">{{ reportForm.errors.reason }}</p>

                        <textarea
                            v-model="reportForm.details"
                            rows="4"
                            class="w-full rounded-lg border-border bg-inputBg text-primary"
                            placeholder="Details optional"
                        />
                        <p v-if="reportForm.errors.details" class="text-sm text-error">{{ reportForm.errors.details }}</p>
                    </div>

                    <div class="mt-5 flex justify-end gap-2">
                        <button type="button" class="rounded-lg border border-border px-4 py-2 text-sm text-primary hover:border-borderHover" @click="closeProfileReport">Abbrechen</button>
                        <button type="submit" class="rounded-lg bg-buttonPrimary px-4 py-2 text-sm font-semibold text-buttonTextPrimary" :disabled="reportForm.processing">
                            Meldung senden
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </AppLayout>
</template>
