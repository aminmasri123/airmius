<script setup>
import AppLayout from '@/Components/Auth/Layouts/AppLayout.vue'
import { Head, Link, router, useForm, usePage } from '@inertiajs/vue3'
import { computed, nextTick, onMounted, onUnmounted, ref, watch } from 'vue'

const props = defineProps({
    conversations: {
        type: Array,
        default: () => [],
    },
    selectedConversation: {
        type: Object,
        default: null,
    },
    users: {
        type: Array,
        default: () => [],
    },
    teams: {
        type: Array,
        default: () => [],
    },
})

const page = usePage()
const authUser = page.props.auth?.user
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
const showLeaveConversationModal = ref(false)
const showChatOnMobile = ref(!!props.selectedConversation)
const conversationSearch = ref('')
const activeConversationFilter = ref(props.selectedConversation?.type ?? 'direct')
let chatInterval = null
let chatChannelName = null
let typingTimeout = null
let typingStopTimeout = null

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

    const others = conversation.users?.filter((user) => user.id !== authUser?.id) || []

    if (conversation.type === 'direct') {
        return others[0]?.name || 'Direktchat'
    }

    if (conversation.type === 'team') {
        return conversation.team?.name || 'Teamchat'
    }

    if (conversation.type === 'event') {
        return conversation.event?.title || 'Eventchat'
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

const selectedUsers = computed(() => props.selectedConversation?.users || [])
const remainingMembersAfterLeave = computed(() => Math.max(0, selectedUsers.value.length - 1))
const canLeaveConversation = computed(() => {
    return !!props.selectedConversation && props.selectedConversation.type !== 'direct'
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

const sendMessage = () => {
    if (!canSendMessage.value) return

    const text = messageForm.message.trim()
    const temporaryId = `local-${Date.now()}`

    optimisticMessages.value.push({
        id: temporaryId,
        conversation_id: props.selectedConversation.id,
        sender_id: authUser?.id,
        sender: authUser,
        message: text,
        attachments: [],
        reactions: [],
        created_at: new Date().toISOString(),
        local_status: 'sending',
        delivery_status: 'sending',
    })

    const payload = new FormData()
    payload.append('conversation_id', props.selectedConversation.id)
    payload.append('message', text)
    messageForm.attachments.forEach((file) => payload.append('attachments[]', file))

    messageForm.reset('message', 'attachments')
    const inputs = Array.isArray(attachmentInput.value) ? attachmentInput.value : [attachmentInput.value]
    inputs.filter(Boolean).forEach((input) => { input.value = '' })
    scrollMessagesToBottom()

    window.axios.post(route('auth.messages.store'), payload).then((response) => {
        const message = response.data.message
        const index = optimisticMessages.value.findIndex((item) => item.id === temporaryId)

        if (index !== -1) {
            optimisticMessages.value[index] = {
                ...message,
                local_status: 'sent',
            }
        }

        refreshChat()
    }).catch(() => {
        const message = optimisticMessages.value.find((item) => item.id === temporaryId)

        if (message) {
            message.local_status = 'failed'
            message.delivery_status = 'failed'
        }
    })
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

const refreshChat = () => {
    if (document.hidden || messageForm.processing || conversationForm.processing) return

    router.reload({
        only: ['conversations', 'selectedConversation', 'notificationCenter', 'auth'],
        preserveScroll: true,
        preserveState: true,
        onSuccess: () => {
            if (props.selectedConversation) scrollMessagesToBottom(false)
        },
    })
}

const scrollMessagesToBottom = (smooth = true) => {
    nextTick(() => {
        const container = Array.isArray(messagesContainer.value)
            ? messagesContainer.value[0]
            : messagesContainer.value

        if (!container) return

        container.scrollTo({
            top: container.scrollHeight,
            behavior: smooth ? 'smooth' : 'auto',
        })
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
    }).catch(() => { })
}

const bindChatRealtime = () => {
    if (!window.Echo || !props.selectedConversation?.id) return

    unbindChatRealtime()

    chatChannelName = `chat.conversation.${props.selectedConversation.id}`

    window.Echo.private(chatChannelName)
        .listen('.message.sent', (event) => {
            const message = event.message

            if (message.sender_id === authUser?.id) return
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
        .listen('.chat.typing', (event) => {
            if (event.user?.id === authUser?.id) return

            typingUsers.value = event.typing
                ? [...typingUsers.value.filter((user) => user.id !== event.user.id), event.user]
                : typingUsers.value.filter((user) => user.id !== event.user.id)

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

const announceTyping = () => {
    if (!props.selectedConversation?.id) return

    window.clearTimeout(typingTimeout)
    typingTimeout = window.setTimeout(() => {
        window.axios.post(route('auth.conversations.typing', props.selectedConversation.id), {
            typing: true,
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

const isOwnMessage = (message) => message.sender_id === authUser?.id
const canDeleteMessage = (message) => {
    if (!isOwnMessage(message) || !message.id || String(message.id).startsWith('local-')) {
        return false
    }

    return !(message.receipts || []).some((receipt) => receipt.read_at)
}

const fileUrl = (file) => file?.url || (file?.path ? `${page.props.uploads?.url || '/storage'}/${file.path}` : '#')
const fileThumbnailUrl = (file) => file?.thumbnail_url || (file?.thumbnail_path ? `${page.props.uploads?.url || '/storage'}/${file.thumbnail_path}` : null)
const fileDownloadUrl = (file) => file?.id ? route('auth.files.download', file.id) : fileUrl(file)
const attachmentLabel = (attachment) => attachment.file?.path?.split('/').pop() || 'Datei'
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
    return (message.reactions || []).find((reaction) => reaction.user_id === authUser?.id)?.reaction
}

const updateMessageReactions = (messageId, reactions) => {
    const message = selectedMessages.value.find((item) => item.id === messageId)
    if (message) message.reactions = reactions || []
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
            selectConversation()
            markSelectedConversationAsRead()
            bindChatRealtime()
        } else {
            unbindChatRealtime()
            showChatOnMobile.value = false
        }
    },
)

watch(
    () => selectedMessages.value.length,
    () => {
        if (props.selectedConversation) scrollMessagesToBottom()
    },
)

onMounted(() => {
    if (props.selectedConversation) {
        scrollMessagesToBottom(false)
        markSelectedConversationAsRead()
        bindChatRealtime()
    }

    chatInterval = window.setInterval(refreshChat, 2500)
})

onUnmounted(() => {
    unbindChatRealtime()
    window.clearTimeout(typingTimeout)
    window.clearTimeout(typingStopTimeout)

    if (chatInterval) {
        window.clearInterval(chatInterval)
    }
})
</script>

<template>
    <AppLayout title="Chat">
        <Head title="Chat" />

        <div class="grid h-[calc(100vh-5rem)] min-h-0 grid-cols-1 gap-4 overflow-hidden lg:grid-cols-[minmax(300px,380px)_minmax(0,1fr)]">
            <aside
                class="min-h-0 flex-col rounded-lg border border-border bg-card"
                :class="showChatOnMobile ? 'hidden lg:flex' : 'flex'"
            >
                <div class="border-b border-border p-4">
                    <div class="flex items-center justify-between gap-3">
                        <div class="min-w-0">
                            <h1 class="text-xl font-semibold text-primary">Chat</h1>
                            <p class="mt-1 text-sm text-secondary">
                                Erst Person oder Gruppe wählen, dann öffnen.
                            </p>
                        </div>
                        <button
                            type="button"
                            class="flex h-10 w-10 shrink-0 items-center justify-center rounded-lg bg-buttonPrimary text-buttonTextPrimary transition hover:bg-buttonPrimaryHover"
                            title="Neue Konversation"
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
                            placeholder="Person, Team oder Training suchen"
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
                    <Link
                        v-for="conversation in filteredConversations"
                        :key="conversation.id"
                        :href="route('auth.conversations.index', { conversation: conversation.id })"
                        preserve-scroll
                        preserve-state
                        class="mb-1 flex gap-3 rounded-lg p-3 text-primary transition hover:bg-muted"
                        :class="selectedConversation?.id === conversation.id ? 'bg-muted' : ''"
                        @click="selectConversation"
                    >
                        <div class="flex h-11 w-11 shrink-0 items-center justify-center rounded-lg bg-buttonPrimary text-sm font-semibold text-buttonTextPrimary">
                            {{ initials(titleFor(conversation)) }}
                        </div>
                        <div class="min-w-0 flex-1">
                            <div class="flex items-start justify-between gap-2">
                                <h2 class="truncate text-sm font-semibold">{{ titleFor(conversation) }}</h2>
                                <span class="shrink-0 text-xs text-secondary">
                                    {{ formatTime(conversation.messages_max_created_at) }}
                                </span>
                            </div>
                            <p class="mt-1 truncate text-xs text-secondary">
                                {{ typeLabelFor(conversation) }} - {{ conversation.users.length }} Mitglieder
                            </p>
                        </div>
                    </Link>

                    <div v-if="conversations.length === 0" class="p-8 text-center text-sm text-secondary">
                        Noch keine Chats. Starte oben eine neue Konversation.
                    </div>

                    <div v-else-if="filteredConversations.length === 0" class="p-8 text-center text-sm text-secondary">
                        Keine passenden Chats Für diesen Filter.
                    </div>
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
                            title="Zurück"
                            @click="goBackToConversations"
                        >
                            <i class="las la-arrow-left text-xl"></i>
                        </button>
                        <div class="min-w-0">
                            <h2 class="truncate text-lg font-semibold text-primary">{{ titleFor(selectedConversation) }}</h2>
                            <p class="mt-1 text-sm text-secondary">
                                {{ typeLabelFor(selectedConversation) }}chat - {{ selectedUsers.length }} Mitglieder
                            </p>
                        </div>
                    </div>
                    <div class="hidden -space-x-2 sm:flex">
                        <div
                            v-for="member in selectedUsers.slice(0, 4)"
                            :key="member.id"
                            class="flex h-9 w-9 items-center justify-center rounded-lg border border-card bg-inputBg text-xs font-semibold text-primary"
                        >
                            {{ initials(member.name) }}
                        </div>
                    </div>
                    <button
                        v-if="canLeaveConversation"
                        type="button"
                        class="flex h-9 w-9 shrink-0 items-center justify-center rounded-lg text-secondary transition hover:bg-inputBg hover:text-error"
                        title="Gruppe verlassen"
                        @click="openLeaveConversationModal"
                    >
                        <i class="las la-sign-out-alt text-xl"></i>
                    </button>
                </div>

                <div v-if="selectedConversation" ref="messagesContainer" class="min-h-0 flex-1 space-y-3 overflow-y-auto p-4 custom-scrollbar">
                    <div
                        v-for="message in selectedMessages"
                        :key="message.id"
                        class="flex"
                        :class="isOwnMessage(message) ? 'justify-end' : 'justify-start'"
                    >
                        <div
                            class="max-w-[82%] rounded-lg px-4 py-3"
                            :class="isOwnMessage(message)
                                ? 'bg-buttonPrimary text-buttonTextPrimary'
                                : 'bg-inputBg text-primary'"
                        >
                            <div class="mb-1 flex items-center justify-between gap-4 text-xs opacity-80">
                                <span class="truncate">{{ message.sender?.name }}</span>
                                <span class="shrink-0">{{ formatTime(message.created_at) }}</span>
                            </div>
                            <p v-if="message.message" class="whitespace-pre-line text-sm leading-6">{{ message.message }}</p>

                            <div v-if="message.attachments?.length" class="mt-2 space-y-1">
                                <div
                                    v-for="attachment in message.attachments"
                                    :key="attachment.id"
                                    class="overflow-hidden rounded border border-border/50 bg-card/40"
                                >
                                    <a
                                        v-if="attachment.file && isImageMime(attachment.file.type)"
                                        :href="fileDownloadUrl(attachment.file)"
                                        class="block"
                                        :title="`${attachmentLabel(attachment)} herunterladen`"
                                    >
                                        <img
                                            :src="fileUrl(attachment.file)"
                                            :alt="attachmentLabel(attachment)"
                                            class="max-h-72 w-full object-cover"
                                        />
                                    </a>

                                    <video
                                        v-else-if="attachment.file && isVideoMime(attachment.file.type)"
                                        :src="fileUrl(attachment.file)"
                                        :poster="fileThumbnailUrl(attachment.file)"
                                        controls
                                        preload="metadata"
                                        class="max-h-72 w-full bg-black"
                                    ></video>

                                    <a
                                        v-else-if="attachment.file"
                                        :href="fileDownloadUrl(attachment.file)"
                                        class="flex items-center gap-3 px-3 py-2 text-xs hover:bg-inputBg/70"
                                        :title="`${attachmentLabel(attachment)} herunterladen`"
                                    >
                                        <i :class="[fileIconFor(attachment.file), 'text-2xl']"></i>
                                        <span class="min-w-0 flex-1">
                                            <span class="block truncate font-semibold">{{ attachmentLabel(attachment) }}</span>
                                            <span class="block text-[11px] opacity-75">
                                                {{ fileExtension(attachment.file).toUpperCase() || 'DATEI' }}
                                                <span v-if="fileSizeLabel(attachment.file.size)"> · {{ fileSizeLabel(attachment.file.size) }}</span>
                                            </span>
                                        </span>
                                        <i class="las la-download text-lg opacity-75"></i>
                                    </a>
                                </div>
                            </div>

                            <div class="mt-2 flex flex-wrap items-center gap-1 text-xs">
                                <button
                                    v-for="reaction in ['like', 'heart', 'ok']"
                                    :key="reaction"
                                    type="button"
                                    class="rounded border px-2 py-1"
                                    :class="userReaction(message) === reaction ? 'border-primary bg-card text-primary' : 'border-border/50 opacity-80'"
                                    @click="reactToMessage(message, reaction)"
                                >
                                    {{ reaction }} {{ reactionCounts(message)[reaction] || '' }}
                                </button>
                                <button
                                    v-if="canDeleteMessage(message)"
                                    type="button"
                                    class="ml-auto rounded border border-border/50 px-2 py-1 opacity-80"
                                    @click="deleteMessage(message)"
                                >
                                    <i class="las la-trash"></i>
                                </button>
                                <span
                                    v-else-if="isOwnMessage(message) && !String(message.id).startsWith('local-')"
                                    class="ml-auto rounded border border-transparent px-2 py-1 text-secondary opacity-80"
                                    title="Bereits gelesen - nicht mehr löschbar"
                                >
                                    <i class="las la-lock"></i>
                                </span>
                                <button
                                    v-else-if="!String(message.id).startsWith('local-')"
                                    type="button"
                                    class="ml-auto rounded border border-border/50 px-2 py-1 opacity-80"
                                    title="Nachricht melden"
                                    @click="openReport(message)"
                                >
                                    <i class="las la-flag"></i>
                                </button>
                            </div>

                            <div v-if="isOwnMessage(message)" class="mt-1 flex justify-end">
                                <span class="inline-flex items-center gap-1 text-xs opacity-80" :title="statusIconFor(message).label">
                                    <i :class="[statusIconFor(message).icon, message.local_status === 'failed' ? 'text-error' : '']"></i>
                                    <span class="sr-only">{{ statusIconFor(message).label }}</span>
                                </span>
                            </div>
                        </div>
                    </div>

                    <div v-if="selectedMessages.length === 0" class="flex h-full items-center justify-center text-sm text-secondary">
                        Keine Nachrichten in diesem Chat.
                    </div>

                    <div v-if="typingUsers.length" class="text-xs text-secondary">
                        {{ typingUsers.map((user) => user.name).join(', ') }} schreibt...
                    </div>
                </div>

                <form v-if="selectedConversation" class="border-t border-border p-4" @submit.prevent="sendMessage">
                    <div v-if="messageForm.attachments.length" class="mb-2 flex flex-wrap gap-2 text-xs text-secondary">
                        <span v-for="file in messageForm.attachments" :key="file.name" class="rounded border border-border px-2 py-1">
                            {{ file.name }}
                        </span>
                    </div>
                    <div class="flex gap-2">
                        <textarea
                            v-model="messageForm.message"
                            rows="2"
                            placeholder="Nachricht schreiben..."
                            class="min-w-0 flex-1 resize-none rounded-lg border border-border bg-inputBg px-3 py-2 text-sm text-primary placeholder-secondary focus:border-primary focus:ring-primary"
                            @keydown.enter.prevent="sendMessage"
                            @input="announceTyping"
                        />
                        <label class="flex cursor-pointer items-center rounded-lg border border-border px-3 py-2 text-primary hover:bg-inputBg">
                            <i class="las la-paperclip text-xl"></i>
                            <input ref="attachmentInput" type="file" multiple class="hidden" @change="onAttachmentChange">
                        </label>
                        <button
                            type="submit"
                            :disabled="messageForm.processing || !canSendMessage"
                            class="rounded-lg bg-buttonPrimary px-4 py-2 text-buttonTextPrimary transition hover:bg-buttonPrimaryHover disabled:cursor-not-allowed disabled:opacity-50"
                        >
                            <i class="las la-paper-plane text-xl"></i>
                        </button>
                    </div>
                </form>

                <div v-else class="flex min-h-0 flex-1 items-center justify-center p-8 text-center">
                    <div class="max-w-sm">
                        <div class="mx-auto flex h-14 w-14 items-center justify-center rounded-lg bg-inputBg text-primary">
                            <i class="las la-user-lock text-3xl"></i>
                        </div>
                        <h2 class="mt-4 text-lg font-semibold text-primary">Kein Chat geöffnet</h2>
                        <p class="mt-2 text-sm leading-6 text-secondary">
                            Aus Datenschutzgruenden wird keine Konversation automatisch angezeigt.
                            Waehle links bewusst eine Person, ein Team oder eine Gruppe aus.
                        </p>
                        <button
                            type="button"
                            class="mt-5 rounded-lg bg-buttonPrimary px-4 py-2 text-sm font-semibold text-buttonTextPrimary hover:bg-buttonPrimaryHover lg:hidden"
                            @click="goBackToConversations"
                        >
                            Chat auswählen
                        </button>
                    </div>
                </div>
            </section>
        </div>

        <div v-if="showNewConversationModal" class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 p-0 sm:p-4">
            <div class="flex h-full w-full flex-col bg-card sm:h-[min(720px,90vh)] sm:max-w-lg sm:rounded-lg">
                <div class="border-b border-border p-4">
                    <div class="flex items-center justify-between gap-3">
                        <div>
                            <h2 class="text-lg font-semibold text-primary">Neue Konversation</h2>
                            <p class="mt-1 text-sm text-secondary">Direktchat, Gruppe oder Team gezielt starten.</p>
                        </div>
                        <button type="button" class="text-secondary hover:text-primary" @click="showNewConversationModal = false">
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
                            Direkt
                        </button>
                        <button
                            type="button"
                            class="rounded px-3 py-2 text-sm font-medium transition"
                            :class="conversationForm.type === 'group' ? 'bg-card text-primary shadow-sm' : 'text-secondary'"
                            @click="setType('group')"
                        >
                            Gruppe
                        </button>
                        <button
                            type="button"
                            class="rounded px-3 py-2 text-sm font-medium transition"
                            :class="conversationForm.type === 'team' ? 'bg-card text-primary shadow-sm' : 'text-secondary'"
                            @click="setType('team')"
                        >
                            Team
                        </button>
                    </div>
                </div>

                <form class="flex min-h-0 flex-1 flex-col" @submit.prevent="createConversationAndOpen">
                    <div v-if="conversationForm.type === 'team'" class="p-3">
                        <select
                            v-model="conversationForm.team_id"
                            class="w-full rounded-lg border border-border bg-inputBg px-3 py-2 text-sm text-primary focus:border-primary focus:ring-primary"
                        >
                            <option :value="null">Team auswählen</option>
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
                            placeholder="Erste Nachricht optional"
                            class="mb-3 w-full resize-none rounded-lg border border-border bg-inputBg px-3 py-2 text-sm text-primary placeholder-secondary focus:border-primary focus:ring-primary"
                        />

                        <div class="flex gap-2">
                            <button
                                type="button"
                                class="flex-1 rounded-lg border border-border px-4 py-2 text-sm font-medium text-secondary transition hover:bg-inputBg"
                                @click="showNewConversationModal = false"
                            >
                                Abbrechen
                            </button>
                            <button
                                type="submit"
                                :disabled="conversationForm.processing || !canCreateConversation"
                                class="flex-1 rounded-lg bg-buttonPrimary px-4 py-2 text-sm font-semibold text-buttonTextPrimary transition hover:bg-buttonPrimaryHover disabled:cursor-not-allowed disabled:opacity-50"
                            >
                                Chat starten
                            </button>
                        </div>
                    </div>
                </form>
            </div>
        </div>

        <div v-if="showLeaveConversationModal" class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 p-4">
            <div class="w-full max-w-md rounded-lg bg-card shadow-xl">
                <div class="border-b border-border p-4">
                    <div class="flex items-start justify-between gap-4">
                        <div>
                            <h2 class="text-lg font-semibold text-primary">Gruppe verlassen?</h2>
                            <p class="mt-1 text-sm text-secondary">
                                Du wirst aus "{{ titleFor(selectedConversation) }}" entfernt und siehst danach keine neuen Nachrichten mehr.
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
                        Nach deinem Austritt bleiben
                        <span class="font-semibold text-primary">{{ remainingMembersAfterLeave }}</span>
                        Mitglied(er) in dieser Gruppe.
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
                            Gruppe direkt löschen, weil danach höchstens eine Person übrig bleibt.
                            Die Conversation wird dadurch Für alle verbleibenden Mitglieder entfernt.
                        </span>
                    </label>
                </div>

                <div class="flex gap-2 border-t border-border p-4">
                    <button
                        type="button"
                        class="flex-1 rounded-lg border border-border px-4 py-2 text-sm font-medium text-secondary transition hover:bg-inputBg"
                        @click="closeLeaveConversationModal"
                    >
                        Abbrechen
                    </button>
                    <button
                        type="button"
                        :disabled="leaveConversationForm.processing"
                        class="flex-1 rounded-lg bg-error px-4 py-2 text-sm font-semibold text-white transition hover:opacity-90 disabled:cursor-not-allowed disabled:opacity-50"
                        @click="leaveSelectedConversation"
                    >
                        {{ leaveConversationForm.delete_conversation ? 'Verlassen und löschen' : 'Gruppe verlassen' }}
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
                        <p class="text-xs font-semibold uppercase tracking-wide text-secondary">Nachricht melden</p>
                        <h2 class="mt-1 text-xl font-semibold text-primary">Warum soll diese Nachricht geprüft werden?</h2>
                    </div>
                    <button type="button" class="rounded p-2 text-secondary hover:bg-muted" @click="closeReport">
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
                    <button type="button" class="btn" @click="closeReport">Abbrechen</button>
                    <button type="submit" class="btn-primary" :disabled="reportForm.processing">
                        Meldung senden
                    </button>
                </div>
            </form>
        </div>
    </AppLayout>
</template>
