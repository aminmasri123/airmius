import { router, useForm } from '@inertiajs/vue3'
import { computed, ref, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import { confirmDialog } from '@/services/dialogService'

export function useUserProfilePage(props) {
    const { t, te } = useI18n()
    const friendshipStatus = ref(props.viewer.friendship_status)
    const canSendFriendRequest = ref(props.viewer.can_send_friend_request)
    const friendInvitationId = ref(props.viewer.friend_invitation_id)
    const friendshipNotice = ref(null)
    const friendshipProcessing = ref(false)
    const profileActionMenuOpen = ref(false)
    const activeProfileTab = ref(props.activeTab || 'overview')
    const reportTargetOpen = ref(false)
    const skillForms = ref({})

    const sportForm = useForm({
        sport_id: '',
        status: 'active',
        experience_level: 'beginner',
    })

    const recommendationForm = useForm({
        relationship: 'team_member',
        body: '',
    })

    const reportForm = useForm({
        type: 'user',
        id: props.profileUser.id,
        reason: 'other',
        details: '',
    })

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
                friendshipNotice.value = { type: 'success', message: 'Freundschaftsanfrage gesendet.' }
            },
            onError: (errors) => {
                friendshipStatus.value = previous.status
                canSendFriendRequest.value = previous.canSend
                friendInvitationId.value = previous.invitationId
                friendshipNotice.value = {
                    type: 'error',
                    message: errors.email || errors.user_id || 'Freundschaftsanfrage konnte nicht gesendet werden.',
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
            title: 'Nutzer blockieren',
            message: `${props.profileUser.name} blockieren? Bestehende Freundschaften und offene Anfragen werden entfernt.`,
            confirmLabel: 'Blockieren',
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

        router.post(route('auth.friends.invitations.accept', friendInvitationId.value), {}, {
            preserveScroll: true,
            onSuccess: () => {
                friendshipNotice.value = { type: 'success', message: 'Freundschaft angenommen.' }
            },
            onError: (errors) => {
                friendshipStatus.value = previous.status
                canSendFriendRequest.value = previous.canSend
                friendInvitationId.value = previous.invitationId
                friendshipNotice.value = {
                    type: 'error',
                    message: errors.invitation || errors.message || 'Freundschaft konnte nicht angenommen werden.',
                }
            },
            onFinish: () => {
                friendshipProcessing.value = false
            },
        })
    }

    const removeFriend = async () => {
        const confirmed = await confirmDialog({
            title: 'Freundschaft beenden',
            message: `Möchtest du die Freundschaft mit ${props.profileUser.name} wirklich beenden?`,
            confirmLabel: 'Beenden',
            danger: true,
        })

        if (!confirmed) {
            return
        }

        router.delete(route('auth.friends.destroy', props.profileUser.id), { preserveScroll: true })
    }

    const formatDate = (value) => new Intl.DateTimeFormat('de-DE', { day: '2-digit', month: '2-digit', year: 'numeric' }).format(new Date(value))
    const initials = (name) => (name || '?').split(' ').slice(0, 2).map((part) => part.charAt(0)).join('').toUpperCase()

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

    const setProfileActionMenuOpen = (value) => {
        profileActionMenuOpen.value = Boolean(value)
    }

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

    const groupedSkills = computed(() => props.profileUser.sport_skills.reduce((groups, skill) => {
        const key = skill.sport?.id || 'other'
        groups[key] ??= {
            sport: skill.sport,
            skills: [],
        }
        groups[key].skills.push(skill)

        return groups
    }, {}))

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

    return {
        friendshipStatus,
        canSendFriendRequest,
        friendshipNotice,
        friendshipProcessing,
        profileActionMenuOpen,
        activeProfileTab,
        reportTargetOpen,
        sportForm,
        recommendationForm,
        reportForm,
        trustTone,
        profileStats,
        primarySportProfiles,
        visibleBadges,
        membershipCount,
        privacyLabel,
        tabs,
        groupedSkills,
        follow,
        unfollow,
        sendFriendRequest,
        sendMessage,
        blockUser,
        unblockUser,
        acceptFriendRequest,
        removeFriend,
        formatDate,
        initials,
        openProfileReport,
        closeProfileReport,
        submitProfileReport,
        toggleProfileActionMenu,
        setProfileActionMenuOpen,
        sportLabel,
        statusLabel,
        levelLabel,
        relationshipLabel,
        selectProfileTab,
        skillFormFor,
        addSport,
        updateSkill,
        endorseSkill,
        sendRecommendation,
        approveRecommendation,
        rejectRecommendation,
    }
}


