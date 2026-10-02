<script setup>
import AppLayout from '@/Components/Auth/Layouts/AppLayout.vue'
import AppButton from '@/Components/UI/AppButton.vue'
import AppEmptyState from '@/Components/UI/AppEmptyState.vue'
import AppLoadingState from '@/Components/UI/AppLoadingState.vue'
import { Head, Link, router, useForm, usePage } from '@inertiajs/vue3'
import { computed, nextTick, onMounted, onUnmounted, ref, watch } from 'vue'
import { useI18n } from 'vue-i18n'

const { t, locale } = useI18n()
const tx = (key, params = {}) => t(key, params)

const props = defineProps({
    conversations: {
        type: Array,
        default: () => [],
    },
    selectedConversation: {
        type: Object,
        default: null,
    },
    directPeerHasBlocked: {
        type: Boolean,
        default: false,
    },
    users: {
        type: Array,
        default: () => [],
    },
    teams: {
        type: Array,
        default: () => [],
    },
    typingUsers: {
        type: Array,
        default: () => [],
    },
    groupInvitations: {
        type: Array,
        default: () => [],
    },
    messagePage: {
        type: Object,
        default: () => ({ limit: 50, has_more: false, next_limit: 50 }),
    },
    messageSearch: {
        type: String,
        default: '',
    },
})

const page = usePage()
const authUser = page.props.auth?.user
const isSameUser = (left, right) => String(left ?? '') === String(right ?? '')
const isCurrentUser = (userId) => String(userId ?? '') === String(authUser?.id ?? '')
const isNotCurrentUser = (userId) => String(userId ?? '') !== String(authUser?.id ?? '')
const messagesContainer = ref(null)
const attachmentInput = ref(null)
const optimisticMessages = ref([])
const realtimeMessages = ref([])
const reportTarget = ref(null)
const openMessageActionsId = ref(null)
const reportForm = useForm({
    type: 'message',
    id: null,
    reason: 'other',
    details: '',
})
const typingUsers = ref([])
const showNewConversationModal = ref(false)
const showAddMembersModal = ref(false)
const showLeaveConversationModal = ref(false)
const showConversationSettingsModal = ref(false)
const showDirectPrivacyModal = ref(false)
const showChatOnMobile = ref(!!props.selectedConversation)
const conversationSearch = ref('')
const chatMessageSearch = ref(props.messageSearch || '')
const activeConversationFilter = ref('all')
const selectingConversation = ref(false)
const isPinnedToBottom = ref(true)
const loadingOlderMessages = ref(false)
const mediaPreviewIndex = ref(null)
const downloadingFileIds = ref([])
let chatInterval = null
let chatChannelName = null
let userChatChannelName = null
let typingTimeout = null
let typingStopTimeout = null
let realtimeRefreshTimeout = null
let messageSearchTimeout = null

const conversationFilters = [
    { key: 'all', label: tx('chat.filters.all'), icon: 'las la-inbox' },
    { key: 'unread', label: tx('chat.filters.unread'), icon: 'las la-envelope' },
    { key: 'direct', label: tx('chat.filters.direct'), icon: 'las la-user' },
    { key: 'group', label: tx('chat.filters.group'), icon: 'las la-comments' },
    { key: 'team', label: tx('chat.filters.team'), icon: 'las la-users' },
    { key: 'event', label: tx('chat.filters.event'), icon: 'las la-calendar' },
]
const conversationsByTypeKeys = ['direct', 'team', 'group', 'event']

const messageForm = useForm({
    conversation_id: props.selectedConversation?.id ?? null,
    message: '',
    attachments: [],
})

const conversationForm = useForm({
    type: 'direct',
    team_id: null,
    participant_ids: [],
    message: '',
})

const leaveConversationForm = useForm({
    delete_conversation: false,
})
const clearConversationForm = useForm({})
const blockPeerForm = useForm({})

const addMembersForm = useForm({
    participant_ids: [],
})

const groupProfileForm = useForm({
    name: '',
    description: '',
    posting_policy: 'all',
})

const muteForm = useForm({
    minutes: 0,
})

const deleteGroupForm = useForm({})

const initials = (name) => (name || '?')
    .split(' ')
    .slice(0, 2)
    .map((part) => part.charAt(0))
    .join('')
    .toUpperCase()

const avatarFor = (user) => user?.profile_photo_thumb || user?.profile_photo_url || null
const conversationAvatarFor = (conversation) => {
    if (conversation?.type !== 'direct') return null

    const other = conversation.users?.find((user) => isNotCurrentUser(user.id))
    return avatarFor(other)
}

const formatTime = (value) => {
    if (!value) return ''

    return new Intl.DateTimeFormat(locale.value === 'ar' ? 'ar-EG' : (locale.value === 'fr' ? 'fr-FR' : (locale.value === 'en' ? 'en-US' : 'de-DE')), {
        day: '2-digit',
        month: '2-digit',
        hour: '2-digit',
        minute: '2-digit',
    }).format(new Date(value))
}

const titleFor = (conversation) => {
    if (!conversation) return tx('chat.title')

    const others = conversation.users?.filter((user) => isNotCurrentUser(user.id)) || []

    if (conversation.type === 'direct') {
        return others[0]?.name || tx('chat.direct_chat')
    }

    if (conversation.type === 'team') {
        return conversation.team?.name || tx('chat.team_chat')
    }

    if (conversation.type === 'event') {
        return conversation.event?.title || tx('chat.event_chat')
    }

    if (conversation.type === 'group' && conversation.name) {
        return conversation.name
    }

    return others.length
        ? others.map((user) => user.name).join(', ')
        : tx('chat.group_chat')
}

const typeLabelFor = (conversation) => ({
    direct: tx('chat.types.direct'),
    group: tx('chat.types.group'),
    team: tx('chat.types.team'),
    event: tx('chat.types.event'),
}[conversation?.type] || tx('chat.title'))

const latestMessagePreviewFor = (conversation) => {
    const message = conversation?.latest_visible_message

    if (!message) return tx('chat.no_messages')
    if (message.kind === 'system') return message.message || tx('chat.system_notice')

    const prefix = isCurrentUser(message.sender_id)
        ? `${tx('chat.you')}: `
        : (message.sender?.name ? `${message.sender.name}: ` : '')

    if (message.message) {
        return `${prefix}${message.message}`
    }

    const firstAttachment = message.attachments?.[0]

    if (firstAttachment?.file?.type?.startsWith('image/')) {
        return `${prefix}${tx('chat.image_sent')}`
    }

    if (firstAttachment?.file?.type?.startsWith('video/')) {
        return `${prefix}${tx('chat.video_sent')}`
    }

    if (firstAttachment?.file) {
        return `${prefix}${firstAttachment.file.display_name || tx('chat.file_sent')}`
    }

    return `${prefix}${tx('chat.message')}`
}

const conversationsByType = computed(() => {
    const counts = conversationsByTypeKeys.reduce((result, key) => {
        result[key] = props.conversations.filter((conversation) => conversation.type === key).length
        return result
    }, {})

    counts.unread = props.conversations.filter((conversation) => conversation.unread_count > 0).length
    return counts
})

const filteredConversations = computed(() => {
    const search = conversationSearch.value.trim().toLowerCase()

    return props.conversations.filter((conversation) => {
        const matchesFilter = activeConversationFilter.value === 'all'
            || (activeConversationFilter.value === 'unread' && conversation.unread_count > 0)
            || conversation.type === activeConversationFilter.value

        if (!matchesFilter) return false
        if (!search) return true

        const haystack = [
            titleFor(conversation),
            typeLabelFor(conversation),
            conversation.team?.name,
            conversation.event?.title,
            ...(conversation.users || []).map((user) => user.name),
        ].filter(Boolean).join(' ').toLowerCase()

        return haystack.includes(search)
    })
})

const selectedMessages = computed(() => {
    const serverMessages = props.selectedConversation?.messages || []
    const serverIds = new Set(serverMessages.map((message) => message.id))
    const pendingMessages = optimisticMessages.value.filter((message) => !serverIds.has(message.id))
    const pushedMessages = realtimeMessages.value.filter((message) => !serverIds.has(message.id))

    return [...serverMessages, ...pushedMessages, ...pendingMessages].filter((message) => !message.deleted_at)
})
const galleryAttachments = computed(() => selectedMessages.value.flatMap((message) => {
    if (isSystemMessage(message)) return []

    return (message.attachments || [])
        .filter((attachment) => attachment.file?.id && isImageMime(attachment.file.type))
        .map((attachment) => ({ ...attachment, message_id: message.id }))
}))
const activeMediaAttachment = computed(() => mediaPreviewIndex.value === null
    ? null
    : galleryAttachments.value[mediaPreviewIndex.value] || null)

const activeConversationId = () => {
    if (props.selectedConversation?.id) return props.selectedConversation.id

    return new URLSearchParams(window.location.search).get('conversation')
}

const chatReloadData = (data = {}) => {
    const conversationId = activeConversationId()

    return {
        ...(conversationId ? { conversation: conversationId } : {}),
        ...data,
    }
}

const selectedUsers = computed(() => props.selectedConversation?.users || [])
const directPeer = computed(() => selectedUsers.value.find((user) => isNotCurrentUser(user.id)) || null)
const currentUserMembership = computed(() => selectedUsers.value.find((user) => isCurrentUser(user.id))?.pivot || null)
const groupRoleFor = (member) => member?.pivot?.role
    || (isSameUser(member?.id, props.selectedConversation?.owner_id) ? 'owner' : 'member')
const groupRoleLabel = (member) => tx(`chat.ui.roles.${groupRoleFor(member)}`)
const currentGroupRole = computed(() => groupRoleFor(selectedUsers.value.find((user) => isCurrentUser(user.id))))
const isSelectedConversationMuted = computed(() => {
    const mutedUntil = currentUserMembership.value?.muted_until

    return mutedUntil ? new Date(mutedUntil) > new Date() : false
})
const canManageSelectedGroup = computed(() => {
    return props.selectedConversation?.type === 'group'
        && currentGroupRole.value === 'owner'
})
const canManageSelectedMembers = computed(() => {
    return props.selectedConversation?.type === 'group'
        && ['owner', 'moderator'].includes(currentGroupRole.value)
})
const canWriteSelectedConversation = computed(() => {
    return props.selectedConversation?.type !== 'group'
        || props.selectedConversation?.posting_policy !== 'management'
        || ['owner', 'moderator'].includes(currentGroupRole.value)
})
const pendingGroupInvitations = computed(() => {
    return props.selectedConversation?.invitations || []
})
const activeTypingUsers = computed(() => {
    const users = [...(props.typingUsers || []), ...typingUsers.value]
    const seen = new Set()

    return users.filter((user) => {
        if (!user?.id || isCurrentUser(user.id) || seen.has(String(user.id))) {
            return false
        }

        seen.add(String(user.id))
        return true
    })
})
const remainingMembersAfterLeave = computed(() => Math.max(0, selectedUsers.value.length - 1))
const canLeaveConversation = computed(() => {
    return !!props.selectedConversation && props.selectedConversation.type !== 'direct'
})
const availableUsersToAdd = computed(() => {
    const currentIds = new Set(selectedUsers.value.map((user) => String(user.id)))

    return props.users.filter((user) => !currentIds.has(String(user.id)))
})
const canCreateConversation = computed(() => {
    if (conversationForm.type === 'team') {
        return !!conversationForm.team_id
    }

    return conversationForm.type === 'direct'
        ? conversationForm.participant_ids.length === 1
        : conversationForm.participant_ids.length >= 2
})
const canSendMessage = computed(() => {
    return !!props.selectedConversation
        && canWriteSelectedConversation.value
        && (!!messageForm.message.trim() || messageForm.attachments.length > 0)
})

const optimisticAttachmentsFor = (temporaryId, attachments) => attachments.map((file, index) => ({
    id: `${temporaryId}-attachment-${index}`,
    file: {
        display_name: file.name,
        type: file.type,
        size: file.size,
    },
}))

const postOptimisticMessage = (optimisticMessage, text, attachments) => {
    const payload = new FormData()
    payload.append('conversation_id', optimisticMessage.conversation_id)
    payload.append('message', text)
    attachments.forEach((file) => payload.append('attachments[]', file))
    optimisticMessage.abort_controller = new AbortController()

    window.axios.post(route('auth.messages.store'), payload, {
        signal: optimisticMessage.abort_controller.signal,
        onUploadProgress: (event) => {
            if (!event.total) return

            optimisticMessage.upload_progress = Math.min(99, Math.round((event.loaded / event.total) * 100))
        },
    }).then((response) => {
        const message = response.data.message
        const index = optimisticMessages.value.findIndex((item) => item.id === optimisticMessage.id)

        if (index !== -1) {
            optimisticMessages.value[index] = {
                ...message,
                local_status: 'sent',
            }
        }

        refreshChat()
    }).catch((error) => {
        optimisticMessage.local_status = 'failed'
        optimisticMessage.delivery_status = 'failed'
        optimisticMessage.error_message = error.code === 'ERR_CANCELED'
            ? tx('chat.errors.upload_cancelled')
            : error.response?.status === 429
            ? tx('chat.errors.rate_limited')
            : tx('chat.errors.send_failed')
    })
}

const sendMessage = () => {
    if (!canSendMessage.value) return

    const text = messageForm.message.trim()
    const attachments = [...messageForm.attachments]
    const temporaryId = `local-${Date.now()}`
    const optimisticMessage = {
        id: temporaryId,
        conversation_id: props.selectedConversation.id,
        sender_id: authUser?.id,
        sender: authUser,
        message: text,
        attachments: optimisticAttachmentsFor(temporaryId, attachments),
        reactions: [],
        created_at: new Date().toISOString(),
        local_status: 'sending',
        delivery_status: 'sending',
        upload_progress: attachments.length ? 0 : null,
        retry_text: text,
        retry_attachments: attachments,
    }

    window.clearTimeout(typingTimeout)
    announceTyping(false)

    optimisticMessages.value.push(optimisticMessage)

    messageForm.reset('message', 'attachments')
    const inputs = Array.isArray(attachmentInput.value) ? attachmentInput.value : [attachmentInput.value]
    inputs.filter(Boolean).forEach((input) => { input.value = '' })
    isPinnedToBottom.value = true
    scrollMessagesToBottom()

    postOptimisticMessage(optimisticMessage, text, attachments)
}

const retryMessage = (message) => {
    if (message.local_status !== 'failed' || !message.retry_attachments) return

    message.local_status = 'sending'
    message.delivery_status = 'sending'
    message.error_message = null
    message.upload_progress = message.retry_attachments.length ? 0 : null
    postOptimisticMessage(message, message.retry_text || '', message.retry_attachments)
}

const cancelUpload = (message) => {
    if (message.local_status !== 'sending') return

    message.abort_controller?.abort()
}

const createConversationAndOpen = () => {
    conversationForm.post(route('auth.conversations.store'), {
        preserveScroll: true,
        onSuccess: () => {
            conversationForm.reset('team_id', 'participant_ids', 'message')
            showNewConversationModal.value = false
        },
    })
}

const toggleParticipant = (id) => {
    if (conversationForm.type === 'team') return

    if (conversationForm.type === 'direct') {
        conversationForm.participant_ids = [id]
        createConversationAndOpen()
        return
    }

    conversationForm.participant_ids = conversationForm.participant_ids.includes(id)
        ? conversationForm.participant_ids.filter((participantId) => participantId !== id)
        : [...conversationForm.participant_ids, id]
}

const setType = (type) => {
    conversationForm.type = type
    conversationForm.team_id = null
    conversationForm.participant_ids = []
}

const openLeaveConversationModal = () => {
    if (!canLeaveConversation.value) return

    leaveConversationForm.delete_conversation = remainingMembersAfterLeave.value <= 1
    showLeaveConversationModal.value = true
}

const openAddMembersModal = () => {
    if (props.selectedConversation?.type !== 'group') return

    addMembersForm.reset('participant_ids')
    showAddMembersModal.value = true
}

const openConversationSettingsModal = () => {
    if (props.selectedConversation?.type !== 'group') return

    groupProfileForm.name = props.selectedConversation.name || ''
    groupProfileForm.description = props.selectedConversation.description || ''
    groupProfileForm.posting_policy = props.selectedConversation.posting_policy || 'all'
    groupProfileForm.clearErrors()
    showConversationSettingsModal.value = true
}

const openDirectPrivacyModal = () => {
    if (props.selectedConversation?.type !== 'direct' || !directPeer.value) return

    showDirectPrivacyModal.value = true
}

const clearSelectedConversation = () => {
    if (props.selectedConversation?.type !== 'direct' || clearConversationForm.processing) return

    clearConversationForm.delete(route('auth.conversations.clear', props.selectedConversation.id), {
        preserveScroll: true,
        preserveState: false,
        only: ['conversations', 'selectedConversation', 'messagePage', 'notificationCenter', 'unreadChatsCount', 'flash', 'errors'],
        onSuccess: () => {
            showDirectPrivacyModal.value = false
            showChatOnMobile.value = false
            optimisticMessages.value = []
            realtimeMessages.value = []
        },
    })
}

const toggleDirectPeerBlock = () => {
    if (!directPeer.value?.id || blockPeerForm.processing) return

    const routeName = props.directPeerHasBlocked ? 'auth.users.unblock' : 'auth.users.block'
    const options = {
        preserveScroll: true,
        only: ['directPeerHasBlocked', 'selectedConversation', 'conversations', 'flash', 'errors'],
        onSuccess: () => {
            showDirectPrivacyModal.value = false
        },
    }

    if (props.directPeerHasBlocked) {
        blockPeerForm.delete(route(routeName, directPeer.value.id), options)
    } else {
        blockPeerForm.post(route(routeName, directPeer.value.id), options)
    }
}

const saveGroupProfile = () => {
    if (!props.selectedConversation?.id || !canManageSelectedGroup.value) return

    groupProfileForm.put(route('auth.conversations.update', props.selectedConversation.id), {
        preserveScroll: true,
        only: ['conversations', 'selectedConversation', 'messagePage', 'typingUsers', 'flash', 'errors'],
        onSuccess: () => {
            showConversationSettingsModal.value = false
        },
    })
}

const muteSelectedConversation = (minutes) => {
    if (!props.selectedConversation?.id) return

    muteForm.minutes = minutes
    muteForm.put(route('auth.conversations.mute', props.selectedConversation.id), {
        preserveScroll: true,
        only: ['conversations', 'selectedConversation', 'notificationCenter', 'unreadChatsCount', 'flash', 'errors'],
    })
}

const toggleAddMember = (id) => {
    addMembersForm.participant_ids = addMembersForm.participant_ids.includes(id)
        ? addMembersForm.participant_ids.filter((participantId) => participantId !== id)
        : [...addMembersForm.participant_ids, id]
}

const addMembersToConversation = () => {
    if (!props.selectedConversation?.id || addMembersForm.participant_ids.length === 0) return

    addMembersForm.post(route('auth.conversations.members.store', props.selectedConversation.id), {
        preserveScroll: true,
        only: ['conversations', 'selectedConversation', 'messagePage', 'users', 'groupInvitations', 'flash', 'errors'],
        onSuccess: () => {
            showAddMembersModal.value = false
            addMembersForm.reset('participant_ids')
        },
    })
}

const acceptGroupInvitation = (invitation) => {
    router.post(route('auth.conversation-invitations.accept', invitation.id), {}, {
        preserveScroll: true,
        only: ['conversations', 'selectedConversation', 'groupInvitations', 'notificationCenter', 'unreadChatsCount', 'flash', 'errors'],
    })
}

const declineGroupInvitation = (invitation) => {
    router.post(route('auth.conversation-invitations.decline', invitation.id), {}, {
        preserveScroll: true,
        only: ['conversations', 'selectedConversation', 'groupInvitations', 'notificationCenter', 'unreadChatsCount', 'flash', 'errors'],
    })
}

const removeGroupMember = (member) => {
    if (!props.selectedConversation?.id || !canManageSelectedMembers.value || isCurrentUser(member.id)) return

    router.delete(route('auth.conversations.members.destroy', {
        conversation: props.selectedConversation.id,
        user: member.id,
    }), {
        preserveScroll: true,
        only: ['conversations', 'selectedConversation', 'messagePage', 'users', 'groupInvitations', 'flash', 'errors'],
    })
}

const canChangeMemberRole = (member) => {
    return canManageSelectedGroup.value && !isCurrentUser(member.id)
}

const canRemoveGroupMember = (member) => {
    if (!canManageSelectedMembers.value || isCurrentUser(member.id)) return false

    return currentGroupRole.value === 'owner' || groupRoleFor(member) === 'member'
}

const updateGroupMemberRole = (member, role) => {
    if (!props.selectedConversation?.id || !canChangeMemberRole(member) || groupRoleFor(member) === role) return

    router.put(route('auth.conversations.members.role.update', {
        conversation: props.selectedConversation.id,
        user: member.id,
    }), { role }, {
        preserveScroll: true,
        only: ['conversations', 'selectedConversation', 'messagePage', 'users', 'flash', 'errors'],
    })
}

const deleteSelectedGroup = () => {
    if (!props.selectedConversation?.id || !canManageSelectedGroup.value || deleteGroupForm.processing) return
    if (!window.confirm(tx('chat.ui.delete_group_confirm'))) return

    deleteGroupForm.delete(route('auth.conversations.destroy', props.selectedConversation.id), {
        preserveState: false,
        onSuccess: () => {
            showConversationSettingsModal.value = false
            showChatOnMobile.value = false
            optimisticMessages.value = []
            realtimeMessages.value = []
        },
    })
}

const closeLeaveConversationModal = () => {
    if (leaveConversationForm.processing) return

    leaveConversationForm.reset('delete_conversation')
    showLeaveConversationModal.value = false
}

const leaveSelectedConversation = () => {
    if (!canLeaveConversation.value) return

    leaveConversationForm.delete(route('auth.conversations.leave', props.selectedConversation.id), {
        preserveScroll: true,
        preserveState: false,
        only: ['conversations', 'selectedConversation', 'messagePage', 'notificationCenter', 'unreadChatsCount', 'flash', 'errors'],
        onSuccess: () => {
            showLeaveConversationModal.value = false
            showChatOnMobile.value = false
            optimisticMessages.value = []
            realtimeMessages.value = []
        },
    })
}

const onAttachmentChange = (event) => {
    messageForm.attachments = Array.from(event.target.files || [])
}

const removePendingAttachment = (index) => {
    messageForm.attachments = messageForm.attachments.filter((_, fileIndex) => fileIndex !== index)

    const inputs = Array.isArray(attachmentInput.value) ? attachmentInput.value : [attachmentInput.value]
    inputs.filter(Boolean).forEach((input) => { input.value = '' })
}

const refreshChat = () => {
    if (document.hidden || selectingConversation.value || messageForm.processing || conversationForm.processing) return

    const shouldStickToBottom = isPinnedToBottom.value

    router.reload({
        only: ['conversations', 'selectedConversation', 'messagePage', 'typingUsers', 'notificationCenter', 'groupInvitations', 'auth'],
        data: chatReloadData({
            message_limit: props.messagePage?.limit || 50,
            message_search: chatMessageSearch.value.trim(),
        }),
        preserveScroll: true,
        preserveState: true,
        onSuccess: () => {
            if (props.selectedConversation && shouldStickToBottom) scrollMessagesToBottom(false)
        },
    })
}

const scheduleChatRefresh = () => {
    window.clearTimeout(realtimeRefreshTimeout)
    realtimeRefreshTimeout = window.setTimeout(refreshChat, 150)
}

const messageContainer = () => Array.isArray(messagesContainer.value)
    ? messagesContainer.value[0]
    : messagesContainer.value

const isNearBottom = (container) => {
    if (!container) return true

    return container.scrollHeight - container.scrollTop - container.clientHeight < 120
}

const onMessagesScroll = () => {
    const container = messageContainer()
    if (!container) return

    isPinnedToBottom.value = isNearBottom(container)

    if (container.scrollTop <= 80) {
        loadOlderMessages()
    }
}

const loadOlderMessages = () => {
    if (!props.selectedConversation?.id || loadingOlderMessages.value || !props.messagePage?.has_more) return

    const container = messageContainer()
    if (!container) return

    loadingOlderMessages.value = true
    const previousHeight = container.scrollHeight
    const previousTop = container.scrollTop

    router.reload({
        only: ['selectedConversation', 'messagePage', 'typingUsers'],
        data: chatReloadData({
            message_limit: props.messagePage.next_limit || ((props.messagePage.limit || 50) + 50),
            message_search: chatMessageSearch.value.trim(),
        }),
        preserveScroll: true,
        preserveState: true,
        onFinish: () => {
            nextTick(() => {
                const updatedContainer = messageContainer()

                if (updatedContainer) {
                    updatedContainer.scrollTop = updatedContainer.scrollHeight - previousHeight + previousTop
                }

                loadingOlderMessages.value = false
                isPinnedToBottom.value = false
            })
        },
    })
}

const scrollMessagesToBottom = (smooth = true) => {
    nextTick(() => {
        const container = messageContainer()

        if (!container) return

        container.scrollTo({
            top: container.scrollHeight,
            behavior: smooth ? 'smooth' : 'auto',
        })
        isPinnedToBottom.value = true
    })
}

const goBackToConversations = () => {
    showChatOnMobile.value = false
}

const selectConversation = () => {
    selectingConversation.value = true
    showChatOnMobile.value = true
}

const markSelectedConversationAsRead = () => {
    if (!props.selectedConversation?.id) return

    window.axios.post(route('auth.messages.read'), {
        conversation_id: props.selectedConversation.id,
    }).then(() => {
        router.reload({ only: ['conversations', 'unreadChatsCount', 'notificationCenter'] })
    }).catch(() => { })
}

const bindChatRealtime = () => {
    if (!window.Echo || !props.selectedConversation?.id) return

    unbindChatRealtime()

    chatChannelName = `chat.conversation.${props.selectedConversation.id}`

    window.Echo.private(chatChannelName)
        .listen('.message.sent', (event) => {
            const message = event.message

            if (isCurrentUser(message.sender_id)) return
            if (realtimeMessages.value.some((item) => item.id === message.id)) return

            realtimeMessages.value.push(message)
            markSelectedConversationAsRead()
        })
        .listen('.message.deleted', (event) => {
            markMessageDeleted(event.message_id)
        })
        .listen('.message.reaction.updated', (event) => {
            updateMessageReactions(event.message_id, event.reactions)
        })
        .listen('.message.receipts.updated', (event) => {
            updateMessageReceipts(event.messages || [])
        })
        .listen('.chat.conversation.updated', () => {
            scheduleChatRefresh()
        })
        .listen('.chat.typing', (event) => {
            if (event.user && isCurrentUser(event.user.id)) return

            typingUsers.value = event.typing
                ? [...typingUsers.value.filter((user) => !isCurrentUser(user.id)), event.user]
                : typingUsers.value.filter((user) => !isCurrentUser(user.id))

            window.clearTimeout(typingStopTimeout)
            typingStopTimeout = window.setTimeout(() => {
                typingUsers.value = []
            }, 3000)
        })
}

const unbindChatRealtime = () => {
    if (window.Echo && chatChannelName) {
        window.Echo.leave(chatChannelName)
    }

    chatChannelName = null
    typingUsers.value = []
}

const bindUserChatRealtime = () => {
    if (!window.Echo || !authUser?.id || userChatChannelName) return

    userChatChannelName = `chat.user.${authUser.id}`

    window.Echo.private(userChatChannelName)
        .listen('.chat.conversation.updated', () => {
            scheduleChatRefresh()
        })
}

const unbindUserChatRealtime = () => {
    if (window.Echo && userChatChannelName) {
        window.Echo.leave(userChatChannelName)
    }

    userChatChannelName = null
}

const announceTyping = (typingValue = null) => {
    if (!props.selectedConversation?.id) return

    window.clearTimeout(typingTimeout)

    if (typingValue !== null) {
        window.axios.post(route('auth.conversations.typing', props.selectedConversation.id), {
            typing: typingValue,
        }).catch(() => { })
        return
    }

    typingTimeout = window.setTimeout(() => {
        const typing = !!messageForm.message.trim()

        window.axios.post(route('auth.conversations.typing', props.selectedConversation.id), {
            typing,
        }).catch(() => { })
    }, 200)
}

const statusIconFor = (message) => {
    const status = message.local_status || message.delivery_status

    return {
        sending: { icon: 'las la-clock', label: tx('chat.status.sending') },
        failed: { icon: 'las la-exclamation-circle', label: tx('chat.status.failed') },
        sent: { icon: 'las la-check', label: tx('chat.status.sent') },
        delivered: { icon: 'las la-check-double', label: tx('chat.status.delivered') },
        read: { icon: 'las la-eye', label: tx('chat.status.read') },
    }[status] || { icon: 'las la-check', label: tx('chat.status.sent') }
}

const deliverySummaryFor = (message) => {
    const receipts = message.receipts || []

    if (!isOwnMessage(message) || !receipts.length) {
        return statusIconFor(message).label
    }

    const read = receipts.filter((receipt) => receipt.read_at).length
    const delivered = receipts.filter((receipt) => receipt.delivered_at).length

    if (read === receipts.length) return `Gelesen von ${read}/${receipts.length}`
    if (delivered === receipts.length) return `Angekommen bei ${delivered}/${receipts.length}`
    if (delivered > 0) return `Angekommen bei ${delivered}/${receipts.length}`

    return `Gesendet an ${receipts.length}`
}

const isOwnMessage = (message) => isCurrentUser(message.sender_id)
const isSystemMessage = (message) => message.kind === 'system'
const systemIconFor = (message) => ({
    'group.invitation.created': 'las la-user-plus',
    'group.invitation.declined': 'las la-user-times',
    'group.created': 'las la-comments',
    'group.member.joined': 'las la-door-open',
    'group.member.left': 'las la-sign-out-alt',
    'group.member.removed': 'las la-user-minus',
    'group.owner.transferred': 'las la-crown',
    'group.profile.updated': 'las la-pen',
}[message.metadata?.event] || 'las la-info-circle')
const canDeleteMessage = (message) => {
    if (isSystemMessage(message) || !isOwnMessage(message) || !message.id || String(message.id).startsWith('local-')) {
        return false
    }

    return !(message.receipts || []).some((receipt) => receipt.read_at)
}

const fileUrl = (file) => file?.id ? route('auth.files.preview', file.id) : '#'
const fileThumbnailUrl = (file) => file?.id ? route('auth.files.preview', { file: file.id, thumbnail: 1 }) : null
const fileDownloadUrl = (file) => file?.id ? route('auth.files.download', file.id) : fileUrl(file)
const attachmentLabel = (attachment) => attachment.file?.display_name || attachment.file?.path?.split('/').pop() || tx('chat.file')
const isImageMime = (type) => type?.startsWith('image/')
const isVideoMime = (type) => type?.startsWith('video/')
const fileExtension = (file) => (file?.path?.split('.').pop() || '').toLowerCase()
const fileSizeLabel = (size) => {
    if (!size) return ''
    if (size < 1024) return `${size} B`
    if (size < 1024 * 1024) return `${(size / 1024).toFixed(1)} KB`

    return `${(size / 1024 / 1024).toFixed(1)} MB`
}
const fileIconFor = (file) => {
    const type = file?.type || ''
    const extension = fileExtension(file)

    if (type.includes('pdf') || extension === 'pdf') return 'las la-file-pdf text-red-500'
    if (type.includes('word') || ['doc', 'docx'].includes(extension)) return 'las la-file-word text-blue-500'
    if (type.includes('excel') || type.includes('spreadsheet') || ['xls', 'xlsx', 'csv'].includes(extension)) return 'las la-file-excel text-green-600'
    if (type.includes('powerpoint') || type.includes('presentation') || ['ppt', 'pptx'].includes(extension)) return 'las la-file-powerpoint text-orange-500'
    if (type.includes('zip') || ['zip', 'rar', '7z'].includes(extension)) return 'las la-file-archive text-yellow-600'
    if (type.includes('audio')) return 'las la-file-audio text-purple-500'
    if (type.includes('text') || ['txt', 'md'].includes(extension)) return 'las la-file-alt text-secondary'

    return 'las la-file text-secondary'
}

const reactionCounts = (message) => {
    return (message.reactions || []).reduce((counts, reaction) => {
        counts[reaction.reaction] = (counts[reaction.reaction] || 0) + 1
        return counts
    }, {})
}

const userReaction = (message) => {
    return (message.reactions || []).find((reaction) => String(reaction.user_id) === String(authUser?.id))?.reaction
}

const updateMessageReactions = (messageId, reactions) => {
    const message = selectedMessages.value.find((item) => item.id === messageId)
    if (message) message.reactions = reactions || []
}

const updateMessageReceipts = (messages) => {
    messages.forEach((updatedMessage) => {
        const message = selectedMessages.value.find((item) => item.id === updatedMessage.id)

        if (!message) return

        message.receipts = updatedMessage.receipts || message.receipts || []
        message.delivery_status = updatedMessage.delivery_status || message.delivery_status
    })
}

const markMessageDeleted = (messageId) => {
    optimisticMessages.value = optimisticMessages.value.filter((message) => message.id !== messageId)
    realtimeMessages.value = realtimeMessages.value.filter((message) => message.id !== messageId)

    const message = props.selectedConversation?.messages?.find((item) => item.id === messageId)
    if (message) message.deleted_at = new Date().toISOString()
}

const deleteMessage = (message) => {
    if (!canDeleteMessage(message)) return

    window.axios.delete(route('auth.messages.destroy', message.id))
        .then(() => markMessageDeleted(message.id))
        .catch(() => { })
}

const hideMessageForMe = (message) => {
    if (!message.id || String(message.id).startsWith('local-')) return

    window.axios.delete(route('auth.messages.hide', message.id))
        .then(() => markMessageDeleted(message.id))
        .catch(() => { })
}

const openMediaPreview = (attachment) => {
    const index = galleryAttachments.value.findIndex((item) => item.id === attachment.id)

    if (index !== -1) {
        mediaPreviewIndex.value = index
    }
}

const closeMediaPreview = () => {
    mediaPreviewIndex.value = null
}

const showPreviousMedia = () => {
    if (mediaPreviewIndex.value === null || galleryAttachments.value.length === 0) return

    mediaPreviewIndex.value = (mediaPreviewIndex.value - 1 + galleryAttachments.value.length) % galleryAttachments.value.length
}

const showNextMedia = () => {
    if (mediaPreviewIndex.value === null || galleryAttachments.value.length === 0) return

    mediaPreviewIndex.value = (mediaPreviewIndex.value + 1) % galleryAttachments.value.length
}

const trackDownload = (file) => {
    if (!file?.id) return

    downloadingFileIds.value = [...downloadingFileIds.value.filter((id) => id !== file.id), file.id]
    window.setTimeout(() => {
        downloadingFileIds.value = downloadingFileIds.value.filter((id) => id !== file.id)
    }, 1400)
}

const openReport = (message) => {
    if (!message.id || String(message.id).startsWith('local-')) return

    reportTarget.value = message
    reportForm.type = 'message'
    reportForm.id = message.id
    reportForm.reason = 'other'
    reportForm.details = ''
    reportForm.clearErrors()
}

const closeReport = () => {
    reportTarget.value = null
    reportForm.reset()
}

const submitReport = () => {
    reportForm.post(route('auth.reports.store'), {
        preserveScroll: true,
        only: ['selectedConversation', 'notificationCenter', 'unreadChatsCount', 'flash', 'errors'],
        onSuccess: closeReport,
    })
}

const reactToMessage = (message, reaction) => {
    if (!message.id || String(message.id).startsWith('local-')) return

    window.axios.post(route('auth.messages.reactions.store', message.id), { reaction })
        .then((response) => updateMessageReactions(message.id, response.data.reactions))
        .catch(() => { })
}

watch(
    () => props.selectedConversation?.id,
    (conversationId) => {
        selectingConversation.value = false
        realtimeMessages.value = []
        messageForm.conversation_id = conversationId ?? null

        if (conversationId) {
            scrollMessagesToBottom(false)
            isPinnedToBottom.value = true
            selectConversation()
            markSelectedConversationAsRead()
            bindChatRealtime()
        } else {
            unbindChatRealtime()
            showChatOnMobile.value = false
        }
    },
)

watch(chatMessageSearch, () => {
    window.clearTimeout(messageSearchTimeout)
    messageSearchTimeout = window.setTimeout(() => {
        router.reload({
            only: ['selectedConversation', 'messagePage', 'messageSearch'],
            data: chatReloadData({
                message_limit: 50,
                message_search: chatMessageSearch.value.trim(),
            }),
            preserveScroll: true,
            preserveState: true,
            onSuccess: () => {
                if (!chatMessageSearch.value.trim()) scrollMessagesToBottom(false)
            },
        })
    }, 250)
})

watch(
    () => props.messageSearch,
    (value) => {
        if (value !== chatMessageSearch.value) {
            chatMessageSearch.value = value || ''
        }
    },
)

watch(
    () => selectedMessages.value.length,
    () => {
        if (props.selectedConversation && isPinnedToBottom.value && !loadingOlderMessages.value) {
            scrollMessagesToBottom()
        }
    },
)

onMounted(() => {
    bindUserChatRealtime()

    if (props.selectedConversation) {
        scrollMessagesToBottom(false)
        isPinnedToBottom.value = true
        markSelectedConversationAsRead()
        bindChatRealtime()
    }

    chatInterval = window.setInterval(refreshChat, 2500)
})

onUnmounted(() => {
    unbindChatRealtime()
    unbindUserChatRealtime()
    window.clearTimeout(typingTimeout)
    window.clearTimeout(typingStopTimeout)
    window.clearTimeout(realtimeRefreshTimeout)
    window.clearTimeout(messageSearchTimeout)

    if (chatInterval) {
        window.clearInterval(chatInterval)
    }
})
</script>

<template>
    <AppLayout :title="tx('chat.title')">
        <Head :title="tx('chat.title')" />

        <div class="grid h-[calc(100dvh-5rem)] min-h-0 grid-cols-1 gap-2 overflow-hidden sm:gap-4 lg:grid-cols-[minmax(300px,380px)_minmax(0,1fr)]">
            <aside
                class="min-h-0 flex-col rounded-lg border border-border bg-card"
                :class="showChatOnMobile ? 'hidden lg:flex' : 'flex'"
            >
                <div class="border-b border-border p-4">
                    <div class="flex items-center justify-between gap-3">
                        <div class="min-w-0">
                            <h1 class="text-xl font-semibold text-primary">{{ tx('chat.title') }}</h1>
                            <p class="mt-1 text-sm text-secondary">
                                {{ tx('chat.choose_hint') }}
                            </p>
                        </div>
                        <button
                            type="button"
                            class="flex h-10 w-10 shrink-0 items-center justify-center rounded-lg bg-buttonPrimary text-buttonTextPrimary transition hover:bg-buttonPrimaryHover"
                            :title="tx('chat.new_conversation')"
                            :aria-label="tx('chat.new_conversation')"
                            @click="showNewConversationModal = true"
                        >
                            <i class="las la-plus text-xl"></i>
                        </button>
                    </div>

                    <label class="mt-4 flex items-center gap-2 rounded-lg border border-border bg-inputBg px-3 py-2">
                        <i class="las la-search text-lg text-secondary"></i>
                        <input
                            v-model="conversationSearch"
                            type="search"
                            :placeholder="tx('chat.search_placeholder')"
                            class="min-w-0 flex-1 border-0 bg-transparent p-0 text-sm text-primary placeholder-secondary focus:ring-0"
                        >
                    </label>

                    <div class="mt-3 flex gap-2 overflow-x-auto pb-1 custom-scrollbar">
                        <button
                            v-for="filter in conversationFilters"
                            :key="filter.key"
                            type="button"
                            class="inline-flex shrink-0 items-center gap-2 rounded-lg border px-3 py-2 text-sm font-medium transition"
                            :class="activeConversationFilter === filter.key
                                ? 'border-primary bg-buttonPrimary text-buttonTextPrimary'
                                : 'border-border bg-card text-secondary hover:bg-inputBg hover:text-primary'"
                            @click="activeConversationFilter = filter.key"
                        >
                            <i :class="filter.icon"></i>
                            <span>{{ filter.label }}</span>
                            <span v-if="filter.key !== 'all'" class="text-xs opacity-75">
                                {{ conversationsByType[filter.key] || 0 }}
                            </span>
                            <span v-else class="text-xs opacity-75">{{ conversations.length }}</span>
                        </button>
                    </div>
                </div>

                <div class="min-h-0 flex-1 overflow-y-auto p-2 custom-scrollbar">
                    <div v-if="groupInvitations.length" class="mb-3 space-y-2 rounded-lg border border-border bg-inputBg p-2">
                        <p class="px-1 text-xs font-semibold uppercase text-secondary">{{ tx('chat.group_invitations') }}</p>
                        <div
                            v-for="invitation in groupInvitations"
                            :key="invitation.id"
                            class="rounded-lg bg-card p-3"
                        >
                            <p class="truncate text-sm font-semibold text-primary">
                                {{ titleFor(invitation.conversation) }}
                            </p>
                            <p class="mt-1 truncate text-xs text-secondary">
                                {{ tx('chat.invited_by') }} {{ invitation.inviter?.name || tx('chat.member') }}
                            </p>
                            <div class="mt-3 flex gap-2">
                                <button
                                    type="button"
                                    class="flex-1 rounded-lg bg-buttonPrimary px-3 py-2 text-xs font-semibold text-buttonTextPrimary"
                                    @click="acceptGroupInvitation(invitation)"
                                >
                                    {{ tx('chat.accept') }}
                                </button>
                                <button
                                    type="button"
                                    class="flex-1 rounded-lg border border-border px-3 py-2 text-xs font-semibold text-secondary"
                                    @click="declineGroupInvitation(invitation)"
                                >
                                    {{ tx('chat.decline') }}
                                </button>
                            </div>
                        </div>
                    </div>

                    <Link
                        v-for="conversation in filteredConversations"
                        :key="conversation.id"
                        :href="route('auth.conversations.show', conversation.id)"
                        preserve-scroll
                        class="mb-1 flex gap-3 rounded-lg p-3 text-primary transition hover:bg-muted"
                        :class="selectedConversation?.id === conversation.id ? 'bg-muted' : ''"
                        @click="selectConversation"
                    >
                        <div class="flex h-11 w-11 shrink-0 items-center justify-center overflow-hidden rounded-lg bg-buttonPrimary text-sm font-semibold text-buttonTextPrimary">
                            <img
                                v-if="conversationAvatarFor(conversation)"
                                :src="conversationAvatarFor(conversation)"
                                :alt="titleFor(conversation)"
                                class="h-full w-full object-cover"
                            >
                            <span v-else>{{ initials(titleFor(conversation)) }}</span>
                        </div>
                        <div class="min-w-0 flex-1">
                            <div class="flex items-start justify-between gap-2">
                                <h2 class="truncate text-sm font-semibold">{{ titleFor(conversation) }}</h2>
                                <div class="flex shrink-0 items-center gap-2">
                                    <span
                                        v-if="conversation.unread_count"
                                        class="rounded-full bg-error px-2 py-0.5 text-[11px] font-semibold text-white"
                                    >
                                        {{ conversation.unread_count > 99 ? '99+' : conversation.unread_count }}
                                    </span>
                                    <span class="text-xs text-secondary">
                                        {{ formatTime(conversation.messages_max_created_at) }}
                                    </span>
                                </div>
                            </div>
                            <p
                                class="mt-1 truncate text-xs"
                                :class="conversation.unread_count ? 'font-semibold text-primary' : 'text-secondary'"
                            >
                                {{ latestMessagePreviewFor(conversation) }}
                            </p>
                            <p class="mt-0.5 truncate text-[11px] text-secondary">
                                {{ typeLabelFor(conversation) }} - {{ conversation.users.length }} {{ tx('chat.members') }}
                            </p>
                        </div>
                    </Link>

                    <AppEmptyState
                        v-if="conversations.length === 0"
                        class="m-3"
                        :title="tx('chat.empty_title')"
                        :description="tx('chat.empty_description')"
                        compact
                    >
                        <template #icon>
                            <i class="las la-comments text-xl" aria-hidden="true"></i>
                        </template>
                    </AppEmptyState>

                    <AppEmptyState
                        v-else-if="filteredConversations.length === 0"
                        class="m-3"
                        :title="tx('chat.no_matches_title')"
                        :description="tx('chat.no_matches_description')"
                        compact
                    >
                        <template #icon>
                            <i class="las la-filter text-xl" aria-hidden="true"></i>
                        </template>
                    </AppEmptyState>
                </div>
            </aside>

            <section
                class="min-h-0 flex-col rounded-lg border border-border bg-card"
                :class="showChatOnMobile ? 'flex' : 'hidden lg:flex'"
            >
                <div v-if="selectedConversation" class="flex items-center justify-between gap-4 border-b border-border p-4">
                    <div class="flex min-w-0 items-center gap-3">
                        <button
                            type="button"
                            class="flex h-9 w-9 shrink-0 items-center justify-center rounded-lg text-primary hover:bg-inputBg lg:hidden"
                            :title="tx('chat.back')"
                            :aria-label="tx('chat.back')"
                            @click="goBackToConversations"
                        >
                            <i class="las la-arrow-left text-xl"></i>
                        </button>
                        <div class="min-w-0">
                            <h2 class="truncate text-lg font-semibold text-primary">{{ titleFor(selectedConversation) }}</h2>
                            <p class="mt-1 text-sm text-secondary">
                                {{ typeLabelFor(selectedConversation) }}{{ tx('chat.chat_suffix') }} · {{ selectedUsers.length }} {{ tx('chat.members') }}
                                <span v-if="isSelectedConversationMuted"> · {{ tx('chat.muted') }}</span>
                            </p>
                        </div>
                    </div>
                    <div class="hidden -space-x-2 sm:flex">
                        <div
                            v-for="member in selectedUsers.slice(0, 4)"
                            :key="member.id"
                            class="flex h-9 w-9 items-center justify-center overflow-hidden rounded-lg border border-card bg-inputBg text-xs font-semibold text-primary"
                        >
                            <img
                                v-if="avatarFor(member)"
                                :src="avatarFor(member)"
                                :alt="member.name"
                                class="h-full w-full object-cover"
                            >
                            <span v-else>{{ initials(member.name) }}</span>
                        </div>
                    </div>
                    <div class="flex items-center gap-1">
                        <button
                            type="button"
                            class="flex h-9 w-9 shrink-0 items-center justify-center rounded-lg text-secondary transition hover:bg-inputBg hover:text-primary"
                            :title="isSelectedConversationMuted ? tx('chat.unmute') : tx('chat.mute')"
                            :aria-label="isSelectedConversationMuted ? tx('chat.unmute') : tx('chat.mute')"
                            :disabled="muteForm.processing"
                            @click="muteSelectedConversation(isSelectedConversationMuted ? 0 : 480)"
                        >
                            <i :class="['las text-xl', isSelectedConversationMuted ? 'la-bell-slash' : 'la-bell']"></i>
                        </button>
                        <button
                            v-if="selectedConversation.type === 'group'"
                            type="button"
                            class="flex h-9 w-9 shrink-0 items-center justify-center rounded-lg text-secondary transition hover:bg-inputBg hover:text-primary"
                            :title="tx('chat.group_profile')"
                            :aria-label="tx('chat.group_profile')"
                            @click="openConversationSettingsModal"
                        >
                            <i class="las la-cog text-xl"></i>
                        </button>
                        <button
                            v-if="selectedConversation.type === 'direct'"
                            type="button"
                            class="flex h-9 w-9 shrink-0 items-center justify-center rounded-lg text-secondary transition hover:bg-inputBg hover:text-primary"
                            :title="tx('chat.ui.direct_privacy')"
                            :aria-label="tx('chat.ui.direct_privacy')"
                            @click="openDirectPrivacyModal"
                        >
                            <i class="las la-user-shield text-xl"></i>
                        </button>
                        <button
                            v-if="selectedConversation.type === 'group' && canManageSelectedMembers"
                            type="button"
                            class="flex h-9 w-9 shrink-0 items-center justify-center rounded-lg text-secondary transition hover:bg-inputBg hover:text-primary"
                            :title="tx('chat.add_people')"
                            :aria-label="tx('chat.add_people')"
                            @click="openAddMembersModal"
                        >
                            <i class="las la-user-plus text-xl"></i>
                        </button>
                        <button
                            v-if="canLeaveConversation"
                            type="button"
                            class="flex h-9 w-9 shrink-0 items-center justify-center rounded-lg text-secondary transition hover:bg-inputBg hover:text-error"
                            :title="tx('chat.leave_group')"
                            :aria-label="tx('chat.leave_group')"
                            @click="openLeaveConversationModal"
                        >
                            <i class="las la-sign-out-alt text-xl"></i>
                        </button>
                    </div>
                </div>

                <label v-if="selectedConversation" class="mx-3 mt-3 flex items-center gap-2 rounded-lg border border-border bg-inputBg px-3 py-2 sm:mx-4">
                    <i class="las la-search text-lg text-secondary"></i>
                    <input
                        v-model="chatMessageSearch"
                        type="search"
                        :placeholder="tx('chat.message_search')"
                        class="min-w-0 flex-1 border-0 bg-transparent p-0 text-sm text-primary placeholder-secondary focus:ring-0"
                    >
                    <button
                        v-if="chatMessageSearch"
                        type="button"
                        class="text-secondary hover:text-primary"
                        :title="tx('chat.clear_search')"
                        :aria-label="tx('chat.clear_search')"
                        @click="chatMessageSearch = ''"
                    >
                        <i class="las la-times"></i>
                    </button>
                </label>

                <div v-if="selectedConversation" ref="messagesContainer" class="min-h-0 flex-1 space-y-3 overflow-y-auto p-3 sm:p-4 custom-scrollbar" @scroll.passive="onMessagesScroll">
                    <div v-if="messagePage?.has_more || loadingOlderMessages" class="flex justify-center">
                        <AppButton
                            type="button"
                            variant="secondary"
                            size="xs"
                            :loading="loadingOlderMessages"
                            :disabled="loadingOlderMessages"
                            @click="loadOlderMessages"
                        >
                            {{ loadingOlderMessages ? tx('chat.loading_older') : tx('chat.load_older') }}
                        </AppButton>
                    </div>

                    <div
                        v-for="message in selectedMessages"
                        :key="message.id"
                        class="flex"
                        :class="isSystemMessage(message) ? 'justify-center' : (isOwnMessage(message) ? 'justify-end' : 'justify-start')"
                    >
                        <div
                            class="relative max-w-[92%] rounded-lg px-3 py-2.5 transition sm:max-w-[82%] sm:px-4 sm:py-3"
                            :class="isSystemMessage(message)
                                ? 'border border-border bg-card text-secondary'
                                : isOwnMessage(message)
                                ? 'cursor-pointer bg-buttonPrimary text-buttonTextPrimary'
                                : 'cursor-pointer bg-inputBg text-primary'"
                            :title="isSystemMessage(message) ? undefined : tx('chat.reactions_hint')"
                            @click="!isSystemMessage(message) && (openMessageActionsId = openMessageActionsId === message.id ? null : message.id)"
                        >
                            <div v-if="isSystemMessage(message)" class="flex items-center justify-center gap-2 text-center text-xs">
                                <i :class="[systemIconFor(message), 'text-base']"></i>
                                <span>{{ message.message }}</span>
                                <span class="shrink-0 opacity-70">{{ formatTime(message.created_at) }}</span>
                            </div>

                            <template v-else>
                            <div class="mb-1 flex items-center justify-between gap-4 text-xs opacity-80">
                                <span class="flex min-w-0 items-center gap-1.5 truncate">
                                    <img
                                        v-if="avatarFor(message.sender)"
                                        :src="avatarFor(message.sender)"
                                        :alt="message.sender?.name || ''"
                                        class="h-5 w-5 shrink-0 rounded object-cover"
                                    >
                                    <span class="truncate">{{ message.sender?.name }}</span>
                                </span>
                                <span class="shrink-0">{{ formatTime(message.created_at) }}</span>
                            </div>
                            <p v-if="message.message" class="break-words whitespace-pre-line text-sm leading-6">{{ message.message }}</p>

                            <div v-if="message.attachments?.length" class="mt-2 space-y-1">
                                <div
                                    v-for="attachment in message.attachments"
                                    :key="attachment.id"
                                    class="overflow-hidden rounded border border-border/50 bg-card/40"
                                >
                                    <button
                                        v-if="attachment.file?.id && isImageMime(attachment.file.type)"
                                        type="button"
                                        class="block w-full"
                                        :title="tx('chat.ui.view_attachment', { name: attachmentLabel(attachment) })"
                                        :aria-label="tx('chat.ui.view_attachment', { name: attachmentLabel(attachment) })"
                                        @click="openMediaPreview(attachment)"
                                    >
                                        <img
                                            :src="fileUrl(attachment.file)"
                                            :alt="attachmentLabel(attachment)"
                                            width="960"
                                            height="720"
                                            loading="lazy"
                                            decoding="async"
                                            class="max-h-72 w-full object-cover"
                                        />
                                    </button>

                                    <video
                                        v-else-if="attachment.file?.id && isVideoMime(attachment.file.type)"
                                        :src="fileUrl(attachment.file)"
                                        :poster="fileThumbnailUrl(attachment.file)"
                                        controls
                                        preload="metadata"
                                        class="max-h-72 w-full bg-black"
                                    ></video>

                                    <a
                                        v-else-if="attachment.file"
                                        :href="attachment.file.id ? fileDownloadUrl(attachment.file) : undefined"
                                        class="flex items-center gap-3 px-3 py-2 text-xs"
                                        :class="attachment.file.id ? 'hover:bg-inputBg/70' : 'cursor-default'"
                                        :title="tx('chat.ui.download_attachment', { name: attachmentLabel(attachment) })"
                                        @click="trackDownload(attachment.file)"
                                    >
                                        <i :class="[fileIconFor(attachment.file), 'text-2xl']"></i>
                                        <span class="min-w-0 flex-1">
                                            <span class="block truncate font-semibold">{{ attachmentLabel(attachment) }}</span>
                                            <span class="block text-[11px] opacity-75">
                                                {{ fileExtension(attachment.file).toUpperCase() || tx('chat.file').toUpperCase() }}
                                                <span v-if="fileSizeLabel(attachment.file.size)"> · {{ fileSizeLabel(attachment.file.size) }}</span>
                                            </span>
                                        </span>
                                        <i v-if="attachment.file.id" :class="['las text-lg opacity-75', downloadingFileIds.includes(attachment.file.id) ? 'la-spinner la-spin' : 'la-download']"></i>
                                    </a>
                                </div>
                            </div>

                            <div v-if="message.local_status === 'sending' && message.upload_progress !== null" class="mt-2">
                                <div class="h-1.5 overflow-hidden rounded-full bg-black/20">
                                    <div
                                        class="h-full rounded-full bg-white/80 transition-all"
                                        :style="{ width: `${message.upload_progress || 8}%` }"
                                    ></div>
                                </div>
                                <button
                                    type="button"
                                    class="mt-2 text-xs font-semibold opacity-80 hover:opacity-100"
                                    @click="cancelUpload(message)"
                                >
                                    {{ tx('chat.ui.cancel_upload') }}
                                </button>
                            </div>

                            <div v-if="openMessageActionsId === message.id" class="mt-2 flex justify-end text-xs">
                                <div class="w-56 max-w-full overflow-hidden rounded-xl border border-border bg-card shadow-xl">
                                    <div class="grid grid-cols-3 gap-1 border-b border-border/60 p-2">
                                        <button
                                            v-for="reaction in ['like', 'heart', 'ok']"
                                            :key="reaction"
                                            type="button"
                                            class="inline-flex h-9 items-center justify-center rounded-lg border text-xs font-semibold transition"
                                            :class="userReaction(message) === reaction ? 'border-primary bg-primary/10 text-primary' : 'border-border/60 bg-input text-secondary hover:text-primary'"
                                            :title="tx(`chat.ui.reactions.${reaction}`)"
                                            @click.stop="reactToMessage(message, reaction); openMessageActionsId = null"
                                        >
                                            <i :class="['las text-lg', reaction === 'heart' ? 'la-heart' : reaction === 'ok' ? 'la-check' : 'la-thumbs-up']"></i>
                                            <span v-if="reactionCounts(message)[reaction]" class="ml-1 opacity-70">{{ reactionCounts(message)[reaction] }}</span>
                                        </button>
                                    </div>
                                    <button
                                        v-if="canDeleteMessage(message)"
                                        type="button"
                                        class="flex w-full items-center gap-2 px-3 py-2.5 text-left font-semibold text-secondary transition hover:bg-input hover:text-primary"
                                        @click.stop="deleteMessage(message); openMessageActionsId = null"
                                    >
                                        <i class="las la-trash text-lg"></i>
                                        {{ tx('chat.ui.delete_for_all') }}
                                    </button>
                                    <button
                                        v-if="!String(message.id).startsWith('local-')"
                                        type="button"
                                        class="flex w-full items-center gap-2 px-3 py-2.5 text-left font-semibold text-secondary transition hover:bg-input hover:text-primary"
                                        @click.stop="hideMessageForMe(message); openMessageActionsId = null"
                                    >
                                        <i class="las la-eye-slash text-lg"></i>
                                        {{ tx('chat.ui.hide_for_me') }}
                                    </button>
                                    <button
                                        v-if="message.local_status === 'failed'"
                                        type="button"
                                        class="flex w-full items-center gap-2 px-3 py-2.5 text-left font-semibold text-error transition hover:bg-input"
                                        :title="message.error_message || tx('chat.ui.retry')"
                                        @click.stop="retryMessage(message); openMessageActionsId = null"
                                    >
                                        <i class="las la-redo-alt text-lg"></i>
                                        {{ tx('chat.ui.retry') }}
                                    </button>
                                    <div
                                        v-if="isOwnMessage(message) && !String(message.id).startsWith('local-') && !canDeleteMessage(message) && message.local_status !== 'failed'"
                                        class="flex items-center gap-2 px-3 py-2.5 font-semibold text-secondary/70"
                                        :title="tx('chat.ui.not_deletable')"
                                    >
                                        <i class="las la-lock text-lg"></i>
                                        {{ tx('chat.ui.not_deletable') }}
                                    </div>
                                    <button
                                        v-if="!isOwnMessage(message) && !String(message.id).startsWith('local-')"
                                        type="button"
                                        class="flex w-full items-center gap-2 px-3 py-2.5 text-left font-semibold text-secondary transition hover:bg-input hover:text-primary"
                                        @click.stop="openReport(message); openMessageActionsId = null"
                                    >
                                        <i class="las la-flag text-lg"></i>
                                        {{ tx('chat.ui.report_message') }}
                                    </button>
                                </div>
                            </div>

                            <div
                                v-if="Object.keys(reactionCounts(message)).length"
                                class="mt-2 flex flex-wrap gap-1"
                                :class="isOwnMessage(message) ? 'justify-end' : 'justify-start'"
                            >
                                <span
                                    v-for="(count, reaction) in reactionCounts(message)"
                                    :key="reaction"
                                    class="inline-flex h-7 items-center gap-1 rounded-full border border-border/60 bg-card px-2 text-xs font-semibold text-secondary shadow-sm"
                                    :class="userReaction(message) === reaction ? 'border-primary/60 text-primary' : ''"
                                >
                                    <i :class="['las text-base', reaction === 'heart' ? 'la-heart' : reaction === 'ok' ? 'la-check' : 'la-thumbs-up']"></i>
                                    <span>{{ count }}</span>
                                </span>
                            </div>

                            <div v-if="isOwnMessage(message)" class="mt-1 flex justify-end">
                                <span class="inline-flex items-center gap-1 text-xs opacity-80" :title="deliverySummaryFor(message)">
                                    <i :class="[statusIconFor(message).icon, message.local_status === 'failed' ? 'text-error' : '']"></i>
                                    <span class="hidden sm:inline">{{ deliverySummaryFor(message) }}</span>
                                </span>
                            </div>
                            <p v-if="message.error_message" class="mt-1 text-right text-xs text-error">
                                {{ message.error_message }}
                            </p>
                            </template>
                        </div>
                    </div>

                    <AppEmptyState
                        v-if="selectedMessages.length === 0"
                        :title="tx('chat.no_messages')"
                        :description="tx('chat.empty_description')"
                        compact
                    >
                        <template #icon>
                            <i class="las la-comment-dots text-xl" aria-hidden="true"></i>
                        </template>
                    </AppEmptyState>

                    <div v-if="activeTypingUsers.length" class="text-xs text-secondary">
                        {{ tx('chat.ui.typing', { names: activeTypingUsers.map((user) => user.name).join(', ') }) }}
                    </div>
                </div>

                <form v-if="selectedConversation && canWriteSelectedConversation" class="border-t border-border p-3 sm:p-4" @submit.prevent="sendMessage">
                    <AppLoadingState
                        v-if="messageForm.processing"
                        class="mb-2"
                        :label="tx('chat.ui.sending')"
                        inline
                    />
                    <div v-if="messageForm.attachments.length" class="mb-2 flex flex-wrap gap-2 text-xs text-secondary">
                        <span v-for="(file, index) in messageForm.attachments" :key="`${file.name}-${index}`" class="inline-flex max-w-full items-center gap-2 rounded border border-border px-2 py-1">
                            {{ file.name }}
                            <button type="button" class="text-secondary hover:text-primary" :title="tx('chat.ui.remove_attachment')" :aria-label="tx('chat.ui.remove_attachment')" @click="removePendingAttachment(index)">
                                <i class="las la-times"></i>
                            </button>
                        </span>
                    </div>
                    <div class="flex gap-2">
                        <textarea
                            v-model="messageForm.message"
                            rows="2"
                            :placeholder="tx('chat.ui.message_placeholder')"
                            class="min-w-0 flex-1 resize-none rounded-lg border border-border bg-inputBg px-3 py-2 text-sm text-primary placeholder-secondary focus:border-primary focus:ring-primary"
                            @keydown.enter.exact.prevent="sendMessage"
                            @input="announceTyping"
                        />
                <label class="flex cursor-pointer items-center rounded-lg border border-border px-3 py-2 text-primary hover:bg-inputBg">
                            <span class="sr-only">{{ tx('chat.file') }}</span>
                            <i class="las la-paperclip text-xl"></i>
                            <input ref="attachmentInput" type="file" multiple class="hidden" @change="onAttachmentChange">
                        </label>
                        <AppButton
                            type="submit"
                            :disabled="messageForm.processing || !canSendMessage"
                            :loading="messageForm.processing"
                            icon-only
                            :aria-label="tx('chat.ui.send_message')"
                            :title="tx('chat.ui.send_message')"
                        >
                            <i v-if="!messageForm.processing" class="las la-paper-plane text-xl" aria-hidden="true"></i>
                        </AppButton>
                    </div>
                </form>

                <div v-else-if="selectedConversation" class="border-t border-border bg-inputBg px-4 py-4 text-center">
                    <p class="text-sm font-medium text-primary">{{ tx('chat.ui.posting_restricted') }}</p>
                    <p class="mt-1 text-xs text-secondary">{{ tx('chat.ui.posting_restricted_hint') }}</p>
                </div>

                <div v-else class="flex min-h-0 flex-1 items-center justify-center p-8 text-center">
                    <div class="max-w-sm">
                        <div class="mx-auto flex h-14 w-14 items-center justify-center rounded-lg bg-inputBg text-primary">
                            <i class="las la-user-lock text-3xl"></i>
                        </div>
                        <h2 class="mt-4 text-lg font-semibold text-primary">{{ tx('chat.ui.no_chat_open') }}</h2>
                        <p class="mt-2 text-sm leading-6 text-secondary">
                            {{ tx('chat.ui.no_chat_hint') }}
                        </p>
                        <button
                            type="button"
                            class="mt-5 rounded-lg bg-buttonPrimary px-4 py-2 text-sm font-semibold text-buttonTextPrimary hover:bg-buttonPrimaryHover lg:hidden"
                            @click="goBackToConversations"
                        >
                            {{ tx('chat.ui.choose_chat') }}
                        </button>
                    </div>
                </div>
            </section>
        </div>

        <div
            v-if="activeMediaAttachment"
            class="fixed inset-0 z-[100] flex items-center justify-center bg-black/90 p-3"
            @click.self="closeMediaPreview"
        >
            <button
                type="button"
                class="absolute right-4 top-4 flex h-10 w-10 items-center justify-center rounded-lg bg-white/10 text-white hover:bg-white/20"
                :title="tx('chat.ui.close')"
                :aria-label="tx('chat.ui.close')"
                @click="closeMediaPreview"
            >
                <i class="las la-times text-2xl"></i>
            </button>
            <button
                v-if="galleryAttachments.length > 1"
                type="button"
                class="absolute left-3 top-1/2 flex h-11 w-11 -translate-y-1/2 items-center justify-center rounded-lg bg-white/10 text-white hover:bg-white/20"
                :title="tx('chat.ui.previous_image')"
                :aria-label="tx('chat.ui.previous_image')"
                @click="showPreviousMedia"
            >
                <i class="las la-angle-left text-2xl"></i>
            </button>
            <img
                :src="fileUrl(activeMediaAttachment.file)"
                :alt="attachmentLabel(activeMediaAttachment)"
                width="1200"
                height="900"
                loading="eager"
                decoding="async"
                class="max-h-[82dvh] max-w-full rounded-lg object-contain"
            />
            <button
                v-if="galleryAttachments.length > 1"
                type="button"
                class="absolute right-3 top-1/2 flex h-11 w-11 -translate-y-1/2 items-center justify-center rounded-lg bg-white/10 text-white hover:bg-white/20"
                :title="tx('chat.ui.next_image')"
                :aria-label="tx('chat.ui.next_image')"
                @click="showNextMedia"
            >
                <i class="las la-angle-right text-2xl"></i>
            </button>
            <a
                :href="fileDownloadUrl(activeMediaAttachment.file)"
                class="absolute bottom-4 rounded-lg bg-white px-4 py-2 text-sm font-semibold text-black"
                @click="trackDownload(activeMediaAttachment.file)"
            >
                {{ tx('chat.ui.download') }}
            </a>
        </div>

        <div v-if="showConversationSettingsModal" class="fixed inset-0 z-[90] flex items-center justify-center bg-black/60 p-3">
            <div class="flex max-h-[calc(100vh-1.5rem)] w-full max-w-lg flex-col overflow-hidden rounded-lg border border-border bg-card shadow-2xl">
                <div class="border-b border-border p-4">
                    <div class="flex items-start justify-between gap-3">
                        <div class="min-w-0">
                            <h2 class="text-lg font-semibold text-primary">{{ tx('chat.ui.group_settings') }}</h2>
                            <p class="mt-1 truncate text-sm text-secondary">{{ selectedUsers.length }} {{ tx('chat.members') }}</p>
                        </div>
                        <button type="button" class="text-secondary hover:text-primary" :title="tx('chat.ui.close')" :aria-label="tx('chat.ui.close')" @click="showConversationSettingsModal = false">
                            <i class="las la-times text-2xl"></i>
                        </button>
                    </div>
                </div>

                <form class="min-h-0 flex-1 overflow-y-auto p-4 custom-scrollbar" @submit.prevent="saveGroupProfile">
                    <div class="space-y-4">
                        <label class="block">
                            <span class="text-xs font-semibold uppercase text-secondary">{{ tx('chat.ui.name') }}</span>
                            <input
                                v-model="groupProfileForm.name"
                                type="text"
                                maxlength="120"
                                class="mt-1 w-full rounded-lg border border-border bg-inputBg px-3 py-2 text-sm text-primary focus:border-primary focus:ring-primary"
                                :placeholder="tx('chat.ui.group_name_placeholder')"
                                :disabled="!canManageSelectedGroup"
                            >
                            <p v-if="groupProfileForm.errors.name" class="mt-1 text-sm text-error">{{ groupProfileForm.errors.name }}</p>
                        </label>

                        <label class="block">
                            <span class="text-xs font-semibold uppercase text-secondary">{{ tx('chat.ui.description') }}</span>
                            <textarea
                                v-model="groupProfileForm.description"
                                rows="4"
                                maxlength="500"
                                class="mt-1 w-full resize-none rounded-lg border border-border bg-inputBg px-3 py-2 text-sm text-primary focus:border-primary focus:ring-primary"
                                :placeholder="tx('chat.ui.group_description_placeholder')"
                                :disabled="!canManageSelectedGroup"
                            />
                            <p v-if="groupProfileForm.errors.description" class="mt-1 text-sm text-error">{{ groupProfileForm.errors.description }}</p>
                        </label>

                        <div class="rounded-lg border border-border bg-inputBg p-3">
                            <p class="text-xs font-semibold uppercase text-secondary">{{ tx('chat.ui.notifications') }}</p>
                            <p class="mt-1 text-sm text-primary">
                                {{ isSelectedConversationMuted ? tx('chat.ui.muted_notice') : tx('chat.ui.notifications_active') }}
                            </p>
                            <div class="mt-3 grid grid-cols-2 gap-2 sm:grid-cols-4">
                                <button type="button" class="rounded-lg border border-border px-3 py-2 text-xs font-semibold text-primary hover:bg-card" @click="muteSelectedConversation(60)">
                                    {{ tx('chat.ui.mute_hour') }}
                                </button>
                                <button type="button" class="rounded-lg border border-border px-3 py-2 text-xs font-semibold text-primary hover:bg-card" @click="muteSelectedConversation(480)">
                                    {{ tx('chat.ui.mute_eight_hours') }}
                                </button>
                                <button type="button" class="rounded-lg border border-border px-3 py-2 text-xs font-semibold text-primary hover:bg-card" @click="muteSelectedConversation(10080)">
                                    {{ tx('chat.ui.mute_week') }}
                                </button>
                                <button type="button" class="rounded-lg border border-border px-3 py-2 text-xs font-semibold text-primary hover:bg-card" @click="muteSelectedConversation(0)">
                                    {{ tx('chat.ui.active') }}
                                </button>
                            </div>
                        </div>

                        <div class="rounded-lg border border-border p-3">
                            <p class="text-xs font-semibold uppercase text-secondary">{{ tx('chat.ui.permissions') }}</p>
                            <label class="mt-3 block text-sm font-medium text-primary" for="group-posting-policy">{{ tx('chat.ui.who_can_write') }}</label>
                            <select
                                id="group-posting-policy"
                                v-model="groupProfileForm.posting_policy"
                                class="mt-2 w-full rounded-lg border border-border bg-inputBg px-3 py-2 text-sm text-primary"
                                :disabled="!canManageSelectedGroup"
                            >
                                <option value="all">{{ tx('chat.ui.posting_all') }}</option>
                                <option value="management">{{ tx('chat.ui.posting_management') }}</option>
                            </select>
                        </div>

                        <div class="rounded-lg border border-border p-3">
                            <p class="text-xs font-semibold uppercase text-secondary">{{ tx('chat.members') }}</p>
                            <div class="mt-3 space-y-2">
                                <div
                                    v-for="member in selectedUsers"
                                    :key="member.id"
                                    class="flex items-center gap-3 rounded-lg bg-inputBg px-3 py-2"
                                >
                                    <div class="flex h-9 w-9 shrink-0 items-center justify-center rounded-lg bg-buttonPrimary text-xs font-semibold text-buttonTextPrimary">
                                        {{ initials(member.name) }}
                                    </div>
                                    <div class="min-w-0 flex-1">
                                        <p class="truncate text-sm font-medium text-primary">{{ member.name }}</p>
                                        <p class="text-xs text-secondary">{{ groupRoleLabel(member) }}</p>
                                    </div>
                                    <select
                                        v-if="canChangeMemberRole(member)"
                                        :value="groupRoleFor(member)"
                                        class="max-w-32 rounded-lg border border-border bg-card px-2 py-1 text-xs text-primary"
                                        :aria-label="tx('chat.ui.change_role_for', { name: member.name })"
                                        @change="updateGroupMemberRole(member, $event.target.value)"
                                    >
                                        <option value="owner">{{ tx('chat.ui.roles.owner') }}</option>
                                        <option value="moderator">{{ tx('chat.ui.roles.moderator') }}</option>
                                        <option value="member">{{ tx('chat.ui.roles.member') }}</option>
                                    </select>
                                    <button
                                        v-if="canRemoveGroupMember(member)"
                                        type="button"
                                        class="flex h-8 w-8 items-center justify-center rounded-lg border border-danger/40 text-danger"
                                        :title="tx('chat.ui.remove')"
                                        :aria-label="tx('chat.ui.remove')"
                                        @click="removeGroupMember(member)"
                                    >
                                        <i class="las la-user-minus" aria-hidden="true"></i>
                                    </button>
                                </div>
                            </div>
                        </div>

                        <div v-if="pendingGroupInvitations.length" class="rounded-lg border border-border p-3">
                            <p class="text-xs font-semibold uppercase text-secondary">{{ tx('chat.ui.pending_invitations') }}</p>
                            <div class="mt-3 space-y-2">
                                <div
                                    v-for="invitation in pendingGroupInvitations"
                                    :key="invitation.id"
                                    class="flex items-center justify-between gap-3 rounded-lg bg-inputBg px-3 py-2"
                                >
                                    <div class="min-w-0">
                                        <p class="truncate text-sm font-medium text-primary">
                                            {{ invitation.recipient?.name || invitation.recipient?.email || tx('chat.ui.invited_member') }}
                                        </p>
                                        <p class="truncate text-xs text-secondary">
                                            {{ tx('chat.invited_by') }} {{ invitation.inviter?.name || tx('chat.member') }}
                                        </p>
                                    </div>
                                    <span class="shrink-0 rounded-full border border-border px-2 py-1 text-[11px] font-semibold text-secondary">{{ tx('chat.ui.pending') }}</span>
                                </div>
                            </div>
                        </div>

                        <div v-if="canManageSelectedGroup" class="border-t border-danger/30 pt-4">
                            <p class="text-sm font-semibold text-danger">{{ tx('chat.ui.danger_zone') }}</p>
                            <p class="mt-1 text-xs text-secondary">{{ tx('chat.ui.delete_group_description') }}</p>
                            <button
                                type="button"
                                class="mt-3 rounded-lg border border-danger/50 px-3 py-2 text-sm font-semibold text-danger hover:bg-danger/10"
                                :disabled="deleteGroupForm.processing"
                                @click="deleteSelectedGroup"
                            >
                                {{ tx('chat.ui.delete_group') }}
                            </button>
                        </div>
                    </div>
                </form>

                <div class="flex gap-2 border-t border-border p-3">
                    <button
                        type="button"
                        class="flex-1 rounded-lg border border-border px-4 py-2 text-sm font-medium text-secondary transition hover:bg-inputBg"
                        @click="showConversationSettingsModal = false"
                    >
                        {{ tx('chat.ui.close') }}
                    </button>
                    <button
                        v-if="canManageSelectedGroup"
                        type="button"
                        :disabled="groupProfileForm.processing"
                        class="flex-1 rounded-lg bg-buttonPrimary px-4 py-2 text-sm font-semibold text-buttonTextPrimary transition hover:bg-buttonPrimaryHover disabled:cursor-not-allowed disabled:opacity-50"
                        @click="saveGroupProfile"
                    >
                        {{ tx('chat.ui.save') }}
                    </button>
                </div>
            </div>
        </div>

        <div v-if="showAddMembersModal" class="fixed inset-0 z-[90] flex items-center justify-center bg-black/60 p-3">
            <div class="flex max-h-[calc(100vh-1.5rem)] w-full max-w-lg flex-col overflow-hidden rounded-lg border border-border bg-card shadow-2xl">
                <div class="border-b border-border p-4">
                    <div class="flex items-center justify-between gap-3">
                        <div>
                            <h2 class="text-lg font-semibold text-primary">{{ tx('chat.ui.invite_people') }}</h2>
                            <p class="mt-1 text-sm text-secondary">{{ titleFor(selectedConversation) }}</p>
                        </div>
                        <button type="button" class="text-secondary hover:text-primary" :title="tx('chat.ui.close')" :aria-label="tx('chat.ui.close')" @click="showAddMembersModal = false">
                            <i class="las la-times text-2xl"></i>
                        </button>
                    </div>
                </div>

                <form class="flex min-h-0 flex-1 flex-col" @submit.prevent="addMembersToConversation">
                    <div class="min-h-0 flex-1 overflow-y-auto p-2 custom-scrollbar">
                        <button
                            v-for="member in availableUsersToAdd"
                            :key="member.id"
                            type="button"
                            class="mb-2 flex w-full items-center gap-3 rounded-lg border p-3 text-left transition"
                            :class="addMembersForm.participant_ids.includes(member.id)
                                ? 'border-primary bg-inputBg'
                                : 'border-border hover:bg-inputBg'"
                            @click="toggleAddMember(member.id)"
                        >
                            <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-lg bg-buttonPrimary text-sm font-semibold text-buttonTextPrimary">
                                {{ initials(member.name) }}
                            </div>
                            <div class="min-w-0 flex-1">
                                <p class="truncate text-sm font-medium text-primary">{{ member.name }}</p>
                                <p class="truncate text-xs text-secondary">{{ member.email }}</p>
                            </div>
                            <i
                                class="las text-xl"
                                :class="addMembersForm.participant_ids.includes(member.id) ? 'la-check-circle text-success' : 'la-circle text-secondary'"
                            ></i>
                        </button>

                        <p v-if="availableUsersToAdd.length === 0" class="p-6 text-center text-sm text-secondary">
                            {{ tx('chat.ui.no_more_people') }}
                        </p>
                    </div>

                    <div class="border-t border-border p-3">
                        <div class="flex gap-2">
                            <button
                                type="button"
                                class="flex-1 rounded-lg border border-border px-4 py-2 text-sm font-medium text-secondary transition hover:bg-inputBg"
                                @click="showAddMembersModal = false"
                            >
                                {{ tx('chat.ui.cancel') }}
                            </button>
                            <button
                                type="submit"
                                :disabled="addMembersForm.processing || addMembersForm.participant_ids.length === 0"
                                class="flex-1 rounded-lg bg-buttonPrimary px-4 py-2 text-sm font-semibold text-buttonTextPrimary transition hover:bg-buttonPrimaryHover disabled:cursor-not-allowed disabled:opacity-50"
                            >
                                {{ tx('chat.ui.send_invitation') }}
                            </button>
                        </div>
                    </div>
                </form>
            </div>
        </div>

        <div v-if="showNewConversationModal" class="fixed inset-0 z-[90] flex items-center justify-center bg-black/60 p-3">
            <div class="flex max-h-[calc(100vh-1.5rem)] w-full max-w-lg flex-col overflow-hidden rounded-lg border border-border bg-card shadow-2xl">
                <div class="border-b border-border p-4">
                    <div class="flex items-center justify-between gap-3">
                        <div>
                            <h2 class="text-lg font-semibold text-primary">{{ tx('chat.ui.new_conversation') }}</h2>
                            <p class="mt-1 text-sm text-secondary">{{ tx('chat.ui.group_selection_hint') }}</p>
                        </div>
                        <button type="button" class="text-secondary hover:text-primary" :title="tx('chat.ui.close')" :aria-label="tx('chat.ui.close')" @click="showNewConversationModal = false">
                            <i class="las la-times text-2xl"></i>
                        </button>
                    </div>
                </div>

                <div class="border-b border-border bg-inputBg p-2">
                    <div class="grid grid-cols-3 gap-1 rounded-lg">
                        <button
                            type="button"
                            class="rounded px-3 py-2 text-sm font-medium transition"
                            :class="conversationForm.type === 'direct' ? 'bg-card text-primary shadow-sm' : 'text-secondary'"
                            @click="setType('direct')"
                        >
                            {{ tx('chat.ui.direct') }}
                        </button>
                        <button
                            type="button"
                            class="rounded px-3 py-2 text-sm font-medium transition"
                            :class="conversationForm.type === 'group' ? 'bg-card text-primary shadow-sm' : 'text-secondary'"
                            @click="setType('group')"
                        >
                            {{ tx('chat.ui.group') }}
                        </button>
                        <button
                            type="button"
                            class="rounded px-3 py-2 text-sm font-medium transition"
                            :class="conversationForm.type === 'team' ? 'bg-card text-primary shadow-sm' : 'text-secondary'"
                            @click="setType('team')"
                        >
                            {{ tx('chat.ui.team') }}
                        </button>
                    </div>
                </div>

                <form class="flex min-h-0 flex-1 flex-col" @submit.prevent="createConversationAndOpen">
                    <div v-if="conversationForm.type === 'team'" class="p-3">
                        <select
                            v-model="conversationForm.team_id"
                            class="w-full rounded-lg border border-border bg-inputBg px-3 py-2 text-sm text-primary focus:border-primary focus:ring-primary"
                        >
                            <option :value="null">{{ tx('chat.ui.choose_team') }}</option>
                            <option v-for="team in teams" :key="team.id" :value="team.id">{{ team.name }}</option>
                        </select>
                    </div>

                    <div v-else class="min-h-0 flex-1 overflow-y-auto p-2 custom-scrollbar">
                        <button
                            v-for="member in users"
                            :key="member.id"
                            type="button"
                            class="mb-2 flex w-full items-center gap-3 rounded-lg border p-3 text-left transition"
                            :class="conversationForm.participant_ids.includes(member.id)
                                ? 'border-primary bg-inputBg'
                                : 'border-border hover:bg-inputBg'"
                            @click="toggleParticipant(member.id)"
                        >
                            <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-lg bg-buttonPrimary text-sm font-semibold text-buttonTextPrimary">
                                {{ initials(member.name) }}
                            </div>
                            <div class="min-w-0 flex-1">
                                <p class="truncate text-sm font-medium text-primary">{{ member.name }}</p>
                                <p class="truncate text-xs text-secondary">{{ member.email }}</p>
                            </div>
                            <i
                                class="las text-xl"
                                :class="conversationForm.participant_ids.includes(member.id) ? 'la-check-circle text-success' : 'la-circle text-secondary'"
                            ></i>
                        </button>
                    </div>

                    <div class="border-t border-border p-3">
                        <textarea
                            v-model="conversationForm.message"
                            rows="2"
                            :placeholder="tx('chat.ui.first_message_optional')"
                            class="mb-3 w-full resize-none rounded-lg border border-border bg-inputBg px-3 py-2 text-sm text-primary placeholder-secondary focus:border-primary focus:ring-primary"
                        />

                        <div class="flex gap-2">
                            <div v-if="conversationForm.type === 'group'" class="flex flex-1 items-center text-xs text-secondary">
                                {{ tx('chat.ui.people_selected', { count: conversationForm.participant_ids.length }) }}
                            </div>
                            <button
                                type="button"
                                class="flex-1 rounded-lg border border-border px-4 py-2 text-sm font-medium text-secondary transition hover:bg-inputBg"
                                @click="showNewConversationModal = false"
                            >
                                {{ tx('chat.ui.cancel') }}
                            </button>
                            <button
                                type="submit"
                                :disabled="conversationForm.processing || !canCreateConversation"
                                class="flex-1 rounded-lg bg-buttonPrimary px-4 py-2 text-sm font-semibold text-buttonTextPrimary transition hover:bg-buttonPrimaryHover disabled:cursor-not-allowed disabled:opacity-50"
                            >
                                {{ tx('chat.ui.start_chat') }}
                            </button>
                        </div>
                    </div>
                </form>
            </div>
        </div>

        <div v-if="showDirectPrivacyModal" class="fixed inset-0 z-[90] flex items-center justify-center bg-black/60 p-4" @click.self="showDirectPrivacyModal = false">
            <div class="w-full max-w-md rounded-lg bg-card shadow-xl">
                <div class="flex items-start justify-between gap-4 border-b border-border p-4">
                    <div>
                        <h2 class="text-lg font-semibold text-primary">{{ tx('chat.ui.direct_privacy') }}</h2>
                        <p class="mt-1 text-sm text-secondary">{{ directPeer?.name }}</p>
                    </div>
                    <button type="button" class="text-secondary hover:text-primary" @click="showDirectPrivacyModal = false">
                        <i class="las la-times text-2xl"></i>
                    </button>
                </div>

                <div class="space-y-3 p-4">
                    <div class="rounded-lg border border-border p-4">
                        <h3 class="font-semibold text-primary">{{ tx('chat.ui.delete_chat_for_me') }}</h3>
                        <p class="mt-1 text-sm text-secondary">{{ tx('chat.ui.delete_chat_for_me_hint') }}</p>
                        <button
                            type="button"
                            class="mt-3 rounded-lg bg-error px-4 py-2 text-sm font-semibold text-white disabled:opacity-50"
                            :disabled="clearConversationForm.processing"
                            @click="clearSelectedConversation"
                        >
                            {{ tx('chat.ui.delete_chat') }}
                        </button>
                    </div>

                    <div class="rounded-lg border border-border p-4">
                        <h3 class="font-semibold text-primary">{{ props.directPeerHasBlocked ? tx('chat.ui.unblock_person') : tx('chat.ui.block_person') }}</h3>
                        <p class="mt-1 text-sm text-secondary">{{ props.directPeerHasBlocked ? tx('chat.ui.unblock_person_hint') : tx('chat.ui.block_person_hint') }}</p>
                        <button
                            type="button"
                            class="mt-3 rounded-lg border border-error px-4 py-2 text-sm font-semibold text-error disabled:opacity-50"
                            :disabled="blockPeerForm.processing"
                            @click="toggleDirectPeerBlock"
                        >
                            {{ props.directPeerHasBlocked ? tx('chat.ui.unblock_person') : tx('chat.ui.block_person') }}
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <div v-if="showLeaveConversationModal" class="fixed inset-0 z-[90] flex items-center justify-center bg-black/60 p-4">
            <div class="w-full max-w-md rounded-lg bg-card shadow-xl">
                <div class="border-b border-border p-4">
                    <div class="flex items-start justify-between gap-4">
                        <div>
                            <h2 class="text-lg font-semibold text-primary">{{ tx('chat.ui.leave_title') }}</h2>
                            <p class="mt-1 text-sm text-secondary">
                                {{ tx('chat.ui.leave_description', { title: titleFor(selectedConversation) }) }}
                            </p>
                        </div>
                        <button
                            type="button"
                            class="text-secondary hover:text-primary"
                            @click="closeLeaveConversationModal"
                        >
                            <i class="las la-times text-2xl"></i>
                        </button>
                    </div>
                </div>

                <div class="space-y-4 p-4">
                    <div class="rounded-lg border border-border bg-inputBg p-3 text-sm text-secondary">
                        {{ tx('chat.ui.remaining_members', { count: remainingMembersAfterLeave }) }}
                    </div>

                    <label
                        v-if="remainingMembersAfterLeave <= 1"
                        class="flex gap-3 rounded-lg border border-border p-3 text-sm text-primary"
                    >
                        <input
                            v-model="leaveConversationForm.delete_conversation"
                            type="checkbox"
                            class="mt-1 rounded border-border text-buttonPrimary focus:ring-buttonPrimary"
                        >
                        <span>
                            {{ tx('chat.ui.delete_group_hint') }}
                        </span>
                    </label>
                </div>

                <div class="flex gap-2 border-t border-border p-4">
                    <button
                        type="button"
                        class="flex-1 rounded-lg border border-border px-4 py-2 text-sm font-medium text-secondary transition hover:bg-inputBg"
                        @click="closeLeaveConversationModal"
                    >
                        {{ tx('chat.ui.cancel') }}
                    </button>
                    <button
                        type="button"
                        :disabled="leaveConversationForm.processing"
                        class="flex-1 rounded-lg bg-error px-4 py-2 text-sm font-semibold text-white transition hover:opacity-90 disabled:cursor-not-allowed disabled:opacity-50"
                        @click="leaveSelectedConversation"
                    >
                        {{ leaveConversationForm.delete_conversation ? tx('chat.ui.leave_and_delete') : tx('chat.leave_group') }}
                    </button>
                </div>
            </div>
        </div>

        <div
            v-if="reportTarget"
            class="fixed inset-0 z-[80] flex items-center justify-center bg-black/60 p-4"
            @click.self="closeReport"
        >
            <form class="w-full max-w-lg rounded-lg border border-border bg-card p-5 shadow-xl" @submit.prevent="submitReport">
                <div class="flex items-start justify-between gap-4">
                    <div>
                        <p class="text-xs font-semibold uppercase tracking-wide text-secondary">{{ tx('chat.ui.report_message') }}</p>
                        <h2 class="mt-1 text-xl font-semibold text-primary">{{ tx('chat.ui.report_question') }}</h2>
                    </div>
                    <button type="button" class="rounded p-2 text-secondary hover:bg-muted" :title="tx('chat.ui.close')" :aria-label="tx('chat.ui.close')" @click="closeReport">
                        <i class="las la-times"></i>
                    </button>
                </div>

                <div class="mt-4 space-y-4">
                    <select v-model="reportForm.reason" class="w-full rounded-lg border-border bg-inputBg text-primary">
                        <option value="insult">{{ tx('chat.ui.report_reasons.insult') }}</option>
                        <option value="bullying">{{ tx('chat.ui.report_reasons.bullying') }}</option>
                        <option value="hate">{{ tx('chat.ui.report_reasons.hate') }}</option>
                        <option value="sexual">{{ tx('chat.ui.report_reasons.sexual') }}</option>
                        <option value="violence">{{ tx('chat.ui.report_reasons.violence') }}</option>
                        <option value="threat">{{ tx('chat.ui.report_reasons.threat') }}</option>
                        <option value="image_rights">{{ tx('chat.ui.report_reasons.image_rights') }}</option>
                        <option value="spam">{{ tx('chat.ui.report_reasons.spam') }}</option>
                        <option value="other">{{ tx('chat.ui.report_reasons.other') }}</option>
                    </select>
                    <p v-if="reportForm.errors.reason" class="text-sm text-error">{{ reportForm.errors.reason }}</p>

                    <textarea
                        v-model="reportForm.details"
                        rows="4"
                        class="w-full rounded-lg border-border bg-inputBg text-primary"
                        :placeholder="tx('chat.ui.report_details_placeholder')"
                    />
                    <p v-if="reportForm.errors.details" class="text-sm text-error">{{ reportForm.errors.details }}</p>
                </div>

                <div class="mt-5 flex justify-end gap-2">
                    <button type="button" class="btn" @click="closeReport">{{ tx('chat.ui.cancel') }}</button>
                    <button type="submit" class="btn-primary" :disabled="reportForm.processing">
                        {{ tx('chat.ui.submit_report') }}
                    </button>
                </div>
            </form>
        </div>
    </AppLayout>
</template>
