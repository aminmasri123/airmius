import { router, useForm, usePage } from '@inertiajs/vue3'
import { computed, nextTick, onMounted, onUnmounted, ref, watch } from 'vue'

export function useChatWorkspace(props) {
    const page = usePage()
    const authUser = page.props.auth?.user
    const isCurrentUser = (userId) => String(userId ?? '') === String(authUser?.id ?? '')
    const isNotCurrentUser = (userId) => String(userId ?? '') !== String(authUser?.id ?? '')
    const messagesContainer = ref(null)
    const attachmentInput = ref(null)
    const optimisticMessages = ref([])
    const realtimeMessages = ref([])
    const reportTarget = ref(null)
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
    const showChatOnMobile = ref(!!props.selectedConversation)
    const conversationSearch = ref('')
    const chatMessageSearch = ref(props.messageSearch || '')
    const activeConversationFilter = ref(props.selectedConversation?.type ?? 'direct')
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
        { key: 'direct', label: 'Personen', icon: 'las la-user' },
        { key: 'team', label: 'Teams', icon: 'las la-users' },
        { key: 'group', label: 'Gruppen', icon: 'las la-comments' },
        { key: 'event', label: 'Training', icon: 'las la-calendar' },
        { key: 'all', label: 'Alle', icon: 'las la-inbox' },
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

    const addMembersForm = useForm({
        participant_ids: [],
    })

    const groupProfileForm = useForm({
        name: '',
        description: '',
    })

    const muteForm = useForm({
        minutes: 0,
    })

    const ownerTransferForm = useForm({
        user_id: null,
    })

    const initials = (name) => (name || '?')
        .split(' ')
        .slice(0, 2)
        .map((part) => part.charAt(0))
        .join('')
        .toUpperCase()

    const formatTime = (value) => {
        if (!value) return ''

        return new Intl.DateTimeFormat('de-DE', {
            day: '2-digit',
            month: '2-digit',
            hour: '2-digit',
            minute: '2-digit',
        }).format(new Date(value))
    }

    const titleFor = (conversation) => {
        if (!conversation) return 'Chat'

        const others = conversation.users?.filter((user) => !isCurrentUser(user.id)) || []

        if (conversation.type === 'direct') {
            return others[0]?.name || 'Direktchat'
        }

        if (conversation.type === 'team') {
            return conversation.team?.name || 'Teamchat'
        }

        if (conversation.type === 'event') {
            return conversation.event?.title || 'Eventchat'
        }

        if (conversation.type === 'group' && conversation.name) {
            return conversation.name
        }

        return others.length
            ? others.map((user) => user.name).join(', ')
            : 'Gruppenchat'
    }

    const typeLabelFor = (conversation) => ({
        direct: 'Direkt',
        group: 'Gruppe',
        team: 'Team',
        event: 'Training',
    }[conversation?.type] || 'Chat')

    const latestMessagePreviewFor = (conversation) => {
        const message = conversation?.latest_visible_message

        if (!message) return 'Noch keine Nachrichten'
        if (message.kind === 'system') return message.message || 'Systemhinweis'

        const prefix = isCurrentUser(message.sender_id)
            ? 'Du: '
            : (message.sender?.name ? `${message.sender.name}: ` : '')

        if (message.message) {
            return `${prefix}${message.message}`
        }

        const firstAttachment = message.attachments?.[0]

        if (firstAttachment?.file?.type?.startsWith('image/')) {
            return `${prefix}Bild gesendet`
        }

        if (firstAttachment?.file?.type?.startsWith('video/')) {
            return `${prefix}Video gesendet`
        }

        if (firstAttachment?.file) {
            return `${prefix}${firstAttachment.file.display_name || 'Datei gesendet'}`
        }

        return `${prefix}Nachricht`
    }

    const conversationsByType = computed(() => {
        return conversationsByTypeKeys.reduce((counts, key) => {
            counts[key] = props.conversations.filter((conversation) => conversation.type === key).length
            return counts
        }, {})
    })

    const filteredConversations = computed(() => {
        const search = conversationSearch.value.trim().toLowerCase()

        return props.conversations.filter((conversation) => {
            const matchesFilter = activeConversationFilter.value === 'all'
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
    const currentUserMembership = computed(() => {
        return selectedUsers.value.find((user) => isCurrentUser(user.id))?.pivot || null
    })
    const isSelectedConversationMuted = computed(() => {
        const mutedUntil = currentUserMembership.value?.muted_until

        return mutedUntil ? new Date(mutedUntil) > new Date() : false
    })
    const canManageSelectedGroup = computed(() => {
        return props.selectedConversation?.type === 'group'
            && (!props.selectedConversation.owner_id || isCurrentUser(props.selectedConversation.owner_id))
    })
    const pendingGroupInvitations = computed(() => {
        return props.selectedConversation?.invitations || []
    })
    const ownerTransferCandidates = computed(() => {
        return selectedUsers.value.filter((user) => !isCurrentUser(user.id))
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
        return !!props.selectedConversation && (!!messageForm.message.trim() || messageForm.attachments.length > 0)
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
                ? 'Upload abgebrochen.'
                : error.response?.status === 429
                ? 'Zu viele Nachrichten in kurzer Zeit.'
                : 'Senden fehlgeschlagen.'
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
        groupProfileForm.clearErrors()
        showConversationSettingsModal.value = true
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
        if (!props.selectedConversation?.id || !canManageSelectedGroup.value || isCurrentUser(member.id)) return

        router.delete(route('auth.conversations.members.destroy', {
            conversation: props.selectedConversation.id,
            user: member.id,
        }), {
            preserveScroll: true,
            only: ['conversations', 'selectedConversation', 'messagePage', 'users', 'groupInvitations', 'flash', 'errors'],
        })
    }

    const transferGroupOwner = () => {
        if (!props.selectedConversation?.id || !ownerTransferForm.user_id || !canManageSelectedGroup.value) return

        ownerTransferForm.put(route('auth.conversations.owner.update', props.selectedConversation.id), {
            preserveScroll: true,
            only: ['conversations', 'selectedConversation', 'messagePage', 'users', 'groupInvitations', 'flash', 'errors'],
            onSuccess: () => {
                ownerTransferForm.reset('user_id')
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
            onSuccess: () => {
                showLeaveConversationModal.value = false
                showChatOnMobile.value = false
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
        if (document.hidden || messageForm.processing || conversationForm.processing) return

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
                ? [...typingUsers.value.filter((user) => !isCurrentUser(user.id) && !isCurrentUser(event.user.id)), event.user]
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
            sending: { icon: 'las la-clock', label: 'Wird gesendet' },
            failed: { icon: 'las la-exclamation-circle', label: 'Nicht gesendet' },
            sent: { icon: 'las la-check', label: 'Gesendet' },
            delivered: { icon: 'las la-check-double', label: 'Angekommen' },
            read: { icon: 'las la-eye', label: 'Gelesen' },
        }[status] || { icon: 'las la-check', label: 'Gesendet' }
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
    const attachmentLabel = (attachment) => attachment.file?.display_name || attachment.file?.path?.split('/').pop() || 'Datei'
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
            realtimeMessages.value = []
            messageForm.conversation_id = conversationId ?? null

            if (conversationId) {
                if (props.selectedConversation?.type && activeConversationFilter.value !== 'all') {
                    activeConversationFilter.value = props.selectedConversation.type
                }

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

    return {
        page,
        authUser,
        messagesContainer,
        attachmentInput,
        optimisticMessages,
        realtimeMessages,
        reportTarget,
        reportForm,
        typingUsers,
        showNewConversationModal,
        showAddMembersModal,
        showLeaveConversationModal,
        showConversationSettingsModal,
        showChatOnMobile,
        conversationSearch,
        chatMessageSearch,
        activeConversationFilter,
        isPinnedToBottom,
        loadingOlderMessages,
        mediaPreviewIndex,
        downloadingFileIds,
        conversationFilters,
        conversationsByTypeKeys,
        messageForm,
        conversationForm,
        leaveConversationForm,
        addMembersForm,
        groupProfileForm,
        muteForm,
        ownerTransferForm,
        initials,
        formatTime,
        titleFor,
        typeLabelFor,
        latestMessagePreviewFor,
        conversationsByType,
        filteredConversations,
        selectedMessages,
        galleryAttachments,
        activeMediaAttachment,
        activeConversationId,
        chatReloadData,
        selectedUsers,
        currentUserMembership,
        isSelectedConversationMuted,
        canManageSelectedGroup,
        pendingGroupInvitations,
        ownerTransferCandidates,
        activeTypingUsers,
        remainingMembersAfterLeave,
        canLeaveConversation,
        availableUsersToAdd,
        canCreateConversation,
        canSendMessage,
        optimisticAttachmentsFor,
        postOptimisticMessage,
        sendMessage,
        retryMessage,
        cancelUpload,
        createConversationAndOpen,
        toggleParticipant,
        setType,
        openLeaveConversationModal,
        openAddMembersModal,
        openConversationSettingsModal,
        saveGroupProfile,
        muteSelectedConversation,
        toggleAddMember,
        addMembersToConversation,
        acceptGroupInvitation,
        declineGroupInvitation,
        removeGroupMember,
        transferGroupOwner,
        closeLeaveConversationModal,
        leaveSelectedConversation,
        onAttachmentChange,
        removePendingAttachment,
        refreshChat,
        scheduleChatRefresh,
        messageContainer,
        isNearBottom,
        onMessagesScroll,
        loadOlderMessages,
        scrollMessagesToBottom,
        goBackToConversations,
        selectConversation,
        markSelectedConversationAsRead,
        bindChatRealtime,
        unbindChatRealtime,
        bindUserChatRealtime,
        unbindUserChatRealtime,
        announceTyping,
        statusIconFor,
        deliverySummaryFor,
        isOwnMessage,
        isSystemMessage,
        systemIconFor,
        canDeleteMessage,
        fileUrl,
        fileThumbnailUrl,
        fileDownloadUrl,
        attachmentLabel,
        isImageMime,
        isVideoMime,
        fileExtension,
        fileSizeLabel,
        fileIconFor,
        reactionCounts,
        userReaction,
        updateMessageReactions,
        updateMessageReceipts,
        markMessageDeleted,
        deleteMessage,
        hideMessageForMe,
        openMediaPreview,
        closeMediaPreview,
        showPreviousMedia,
        showNextMedia,
        trackDownload,
        openReport,
        closeReport,
        submitReport,
        reactToMessage,
    }
}
