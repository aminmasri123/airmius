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
})

const page = usePage()
const authUser = page.props.auth?.user
const messagesContainer = ref(null)
const optimisticMessages = ref([])
let chatInterval = null

const messageForm = useForm({
    conversation_id: props.selectedConversation?.id ?? null,
    message: '',
})

const conversationForm = useForm({
    type: 'direct',
    participant_ids: [],
    message: '',
})

const showNewConversationModal = ref(false)
const showChatOnMobile = ref(!!props.selectedConversation)

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

    return others.length
        ? others.map((user) => user.name).join(', ')
        : 'Gruppenchat'
}

const selectedMessages = computed(() => {
    const serverMessages = props.selectedConversation?.messages || []
    const serverIds = new Set(serverMessages.map((message) => message.id))
    const pendingMessages = optimisticMessages.value.filter((message) => !serverIds.has(message.id))

    return [...serverMessages, ...pendingMessages]
})
const selectedUsers = computed(() => props.selectedConversation?.users || [])
const canCreateConversation = computed(() => {
    return conversationForm.type === 'direct'
        ? conversationForm.participant_ids.length === 1
        : conversationForm.participant_ids.length >= 2
})

const sendMessage = () => {
    if (!props.selectedConversation || !messageForm.message.trim()) return

    const text = messageForm.message.trim()
    const temporaryId = `local-${Date.now()}`

    optimisticMessages.value.push({
        id: temporaryId,
        conversation_id: props.selectedConversation.id,
        sender_id: authUser?.id,
        sender: authUser,
        message: text,
        created_at: new Date().toISOString(),
        local_status: 'sending',
        delivery_status: 'sending',
    })

    messageForm.reset('message')
    scrollMessagesToBottom()

    window.axios.post(route('auth.messages.store'), {
        conversation_id: props.selectedConversation.id,
        message: text,
    }).then((response) => {
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
            conversationForm.reset('participant_ids', 'message')
            showNewConversationModal.value = false
        },
    })
}

const toggleParticipant = (id) => {
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
    conversationForm.participant_ids = []
}

const refreshChat = () => {
    if (document.hidden || messageForm.processing || conversationForm.processing) return

    router.reload({
        only: ['conversations', 'selectedConversation', 'notificationCenter', 'auth'],
        preserveScroll: true,
        preserveState: true,
        onSuccess: () => scrollMessagesToBottom(false),
    })
}

const scrollMessagesToBottom = (smooth = true) => {
    nextTick(() => {
        if (!messagesContainer.value) return

        messagesContainer.value.scrollTo({
            top: messagesContainer.value.scrollHeight,
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
    }).catch(() => {})
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

watch(
    () => props.selectedConversation?.id,
    () => {
        scrollMessagesToBottom(false)
        selectConversation()
        markSelectedConversationAsRead()
    },
)

watch(
    () => selectedMessages.value.length,
    () => scrollMessagesToBottom(),
)

onMounted(() => {
    scrollMessagesToBottom(false)
    markSelectedConversationAsRead()
    chatInterval = window.setInterval(refreshChat, 2500)
})

onUnmounted(() => {
    if (chatInterval) {
        window.clearInterval(chatInterval)
    }
})
</script>

<template>
    <AppLayout title="Chat">
        <Head title="Chat" />

        <!-- MOBILE: Konversationsliste oder Chat -->
        <div class="h-[calc(100vh-3rem)] min-h-[680px]">
            <!-- Mobile Konversationsliste -->
            <div v-show="!showChatOnMobile" class="flex h-full flex-col lg:hidden">
                <div class="border-b border-border bg-card p-4">
                    <div class="mb-4 flex items-center justify-between">
                        <h1 class="text-xl font-semibold text-primary">Chat</h1>
                        <button
                            @click="showNewConversationModal = true"
                            class="rounded-full bg-buttonPrimary p-2 text-buttonTextPrimary hover:bg-buttonPrimaryHover"
                        >
                            <i class="las la-plus text-xl"></i>
                        </button>
                    </div>
                    <p class="text-sm text-secondary">{{ conversations.length }} Konversationen</p>
                </div>

                <div class="min-h-0 flex-1 overflow-y-auto p-2 custom-scrollbar">
                    <Link
    v-for="conversation in conversations"
    :key="conversation.id"
    :href="route('auth.conversations.index', { conversation: conversation.id })"
    @click="selectConversation"
    preserve-scroll
    preserve-state
    class="flex gap-3 rounded-lg p-3 transition hover:bg-muted hover:text-card"
    :class="selectedConversation?.id === conversation.id ? 'bg-muted text-card' : 'text-primary'"
>
                        <div class="flex h-11 w-11 shrink-0 items-center justify-center rounded-lg bg-buttonPrimary text-sm font-semibold text-buttonTextPrimary">
                            {{ initials(titleFor(conversation)) }}
                        </div>
                        <div class="min-w-0 flex-1">
                            <div class="flex items-center justify-between gap-2">
                                <h2 class="truncate text-sm font-semibold">{{ titleFor(conversation) }}</h2>
                                <span class="shrink-0 text-xs opacity-75">{{ formatTime(conversation.messages_max_created_at) }}</span>
                            </div>
                            <p class="mt-1 truncate text-xs opacity-75">
                                {{ conversation.type === 'group' ? 'Gruppe' : 'Direkt' }} · {{ conversation.users.length }} Mitglieder
                            </p>
                        </div>
                    </Link>

                    <div v-if="conversations.length === 0" class="p-6 text-center text-sm text-secondary">
                        Noch keine Chats.
                    </div>
                </div>
            </div>

            <!-- Mobile Chat View -->
            <div v-show="showChatOnMobile" class="flex h-full flex-col lg:hidden">
                <div v-if="selectedConversation" class="flex items-center justify-between gap-4 border-b border-border bg-card p-4">
                    <div class="flex items-center gap-3 min-w-0">
                        <button @click="goBackToConversations" class="shrink-0 text-primary hover:text-secondary">
                            <i class="las la-arrow-left text-xl"></i>
                        </button>
                        <div class="min-w-0">
                            <h2 class="truncate text-lg font-semibold text-primary">{{ titleFor(selectedConversation) }}</h2>
                            <p class="text-xs text-secondary">{{ selectedUsers.length }} Mitglieder</p>
                        </div>
                    </div>
                </div>

                <div v-if="selectedConversation" ref="messagesContainer" class="min-h-0 flex-1 space-y-3 overflow-y-auto p-4 custom-scrollbar">
                    <div
                        v-for="message in selectedMessages"
                        :key="message.id"
                        class="flex"
                        :class="isOwnMessage(message) ? 'justify-end' : 'justify-start'"
                    >
                        <div
                            class="max-w-[78%] rounded-lg px-4 py-3"
                            :class="isOwnMessage(message)
                                ? 'bg-buttonPrimary text-buttonTextPrimary'
                                : 'bg-inputBg text-primary'"
                        >
                            <div class="mb-1 flex items-center justify-between gap-4 text-xs opacity-80">
                                <span>{{ message.sender?.name }}</span>
                                <span>{{ formatTime(message.created_at) }}</span>
                            </div>
                            <p class="whitespace-pre-line text-sm leading-6">{{ message.message }}</p>
                            <div v-if="isOwnMessage(message)" class="mt-1 flex justify-end">
                                <span
                                    class="inline-flex items-center gap-1 text-xs opacity-80"
                                    :title="statusIconFor(message).label"
                                >
                                    <i :class="[statusIconFor(message).icon, message.local_status === 'failed' ? 'text-error' : '']"></i>
                                    <span class="sr-only">{{ statusIconFor(message).label }}</span>
                                </span>
                            </div>
                        </div>
                    </div>

                    <div v-if="selectedMessages.length === 0" class="flex h-full items-center justify-center text-sm text-secondary">
                        Keine Nachrichten in diesem Chat.
                    </div>
                </div>

                <form v-if="selectedConversation" class="border-t border-border bg-card p-4" @submit.prevent="sendMessage">
                    <div class="flex gap-2">
                        <textarea
                            v-model="messageForm.message"
                            rows="2"
                            placeholder="Nachricht..."
                            class="min-w-0 flex-1 resize-none rounded-lg border border-border bg-inputBg px-3 py-2 text-sm text-primary placeholder-secondary focus:border-primary focus:ring-primary"
                            required
                        />
                        <button
                            type="submit"
                            :disabled="messageForm.processing || !messageForm.message.trim()"
                            class="rounded-lg bg-buttonPrimary px-4 py-2 text-buttonTextPrimary transition hover:bg-buttonPrimaryHover disabled:cursor-not-allowed disabled:opacity-50"
                        >
                            <i class="las la-paper-plane text-xl"></i>
                        </button>
                    </div>
                </form>

                <div v-else class="flex h-full items-center justify-center p-8 text-center text-secondary">
                    Wähle einen Chat.
                </div>
            </div>

            <!-- DESKTOP: 3-Spalten Layout -->
            <div class="hidden h-full grid-cols-1 gap-4 lg:grid lg:grid-cols-[320px_1fr_340px]">
                <aside class="flex min-h-0 flex-col rounded-lg border border-border bg-card">
                    <div class="border-b border-border p-4">
                        <div class="mb-4 flex items-center justify-between">
                            <h1 class="text-xl font-semibold text-primary">Chat</h1>
                            <button
                                @click="showNewConversationModal = true"
                                class="rounded-full bg-buttonPrimary p-2 text-buttonTextPrimary hover:bg-buttonPrimaryHover"
                            >
                                <i class="las la-plus text-xl"></i>
                            </button>
                        </div>
                        <p class="text-sm text-secondary">{{ conversations.length }} Konversationen</p>
                    </div>

                    <div class="min-h-0 flex-1 overflow-y-auto p-2 custom-scrollbar">
                        <Link
    v-for="conversation in conversations"
    :key="conversation.id"
    :href="route('auth.conversations.index', { conversation: conversation.id })"
    @click="selectConversation"
    preserve-scroll
    preserve-state
    class="flex gap-3 rounded-lg p-3 transition hover:bg-muted hover:text-card"
    :class="selectedConversation?.id === conversation.id ? 'bg-muted text-card' : 'text-primary'"
>
                            <div class="flex h-11 w-11 shrink-0 items-center justify-center rounded-lg bg-buttonPrimary text-sm font-semibold text-buttonTextPrimary">
                                {{ initials(titleFor(conversation)) }}
                            </div>
                            <div class="min-w-0 flex-1">
                                <div class="flex items-center justify-between gap-2">
                                    <h2 class="truncate text-sm font-semibold">{{ titleFor(conversation) }}</h2>
                                    <span class="shrink-0 text-xs opacity-75">{{ formatTime(conversation.messages_max_created_at) }}</span>
                                </div>
                                <p class="mt-1 truncate text-xs opacity-75">
                                    {{ conversation.type === 'group' ? 'Gruppe' : 'Direkt' }} · {{ conversation.users.length }} Mitglieder
                                </p>
                            </div>
                        </Link>

                        <div v-if="conversations.length === 0" class="p-6 text-center text-sm text-secondary">
                            Noch keine Chats.
                        </div>
                    </div>
                </aside>

                <section class="flex min-h-0 flex-col rounded-lg border border-border bg-card">
                    <div v-if="selectedConversation" class="flex items-center justify-between gap-4 border-b border-border p-4">
                        <div class="min-w-0">
                            <h2 class="truncate text-lg font-semibold text-primary">{{ titleFor(selectedConversation) }}</h2>
                            <p class="mt-1 text-sm text-secondary">
                                {{ selectedConversation.type === 'group' ? 'Gruppenchat' : 'Direktchat' }}
                                · {{ selectedUsers.length }} Mitglieder
                            </p>
                        </div>
                        <div class="flex -space-x-2">
                            <div
                                v-for="member in selectedUsers.slice(0, 4)"
                                :key="member.id"
                                class="flex h-9 w-9 items-center justify-center rounded-lg border border-card bg-inputBg text-xs font-semibold text-primary"
                            >
                                {{ initials(member.name) }}
                            </div>
                        </div>
                    </div>

                    <div v-if="selectedConversation" ref="messagesContainer" class="min-h-0 flex-1 space-y-3 overflow-y-auto p-4 custom-scrollbar">
                        <div
                            v-for="message in selectedMessages"
                            :key="message.id"
                            class="flex"
                            :class="isOwnMessage(message) ? 'justify-end' : 'justify-start'"
                        >
                            <div
                                class="max-w-[78%] rounded-lg px-4 py-3"
                                :class="isOwnMessage(message)
                                    ? 'bg-buttonPrimary text-buttonTextPrimary'
                                    : 'bg-inputBg text-primary'"
                            >
                                <div class="mb-1 flex items-center justify-between gap-4 text-xs opacity-80">
                                    <span>{{ message.sender?.name }}</span>
                                    <span>{{ formatTime(message.created_at) }}</span>
                                </div>
                                <p class="whitespace-pre-line text-sm leading-6">{{ message.message }}</p>
                                <div v-if="isOwnMessage(message)" class="mt-1 flex justify-end">
                                    <span
                                        class="inline-flex items-center gap-1 text-xs opacity-80"
                                        :title="statusIconFor(message).label"
                                    >
                                        <i :class="[statusIconFor(message).icon, message.local_status === 'failed' ? 'text-error' : '']"></i>
                                        <span class="sr-only">{{ statusIconFor(message).label }}</span>
                                    </span>
                                </div>
                            </div>
                        </div>

                        <div v-if="selectedMessages.length === 0" class="flex h-full items-center justify-center text-sm text-secondary">
                            Keine Nachrichten in diesem Chat.
                        </div>
                    </div>

                    <form v-if="selectedConversation" class="border-t border-border p-4" @submit.prevent="sendMessage">
                        <div class="flex gap-2">
                            <textarea
                                v-model="messageForm.message"
                                rows="2"
                                placeholder="Nachricht schreiben..."
                                class="min-w-0 flex-1 resize-none rounded-lg border border-border bg-inputBg px-3 py-2 text-sm text-primary placeholder-secondary focus:border-primary focus:ring-primary"
                                required
                            />
                            <button
                                type="submit"
                                :disabled="messageForm.processing || !messageForm.message.trim()"
                                class="rounded-lg bg-buttonPrimary px-4 py-2 text-buttonTextPrimary transition hover:bg-buttonPrimaryHover disabled:cursor-not-allowed disabled:opacity-50"
                            >
                                <i class="las la-paper-plane text-xl"></i>
                            </button>
                        </div>
                    </form>

                    <div v-else class="flex h-full items-center justify-center p-8 text-center text-secondary">
                        Wähle einen Chat oder starte eine neue Konversation.
                    </div>
                </section>

                <aside class="min-h-0 hidden flex-col rounded-lg border border-border bg-card p-4 lg:flex">
                    <h2 class="text-sm font-semibold uppercase tracking-wide text-secondary">Neue Konversation</h2>

                    <div class="mt-4 grid grid-cols-2 rounded-lg border border-border bg-inputBg p-1">
                        <button
                            type="button"
                            class="rounded px-3 py-2 text-sm font-medium"
                            :class="conversationForm.type === 'direct' ? 'bg-card text-primary shadow-sm' : 'text-secondary'"
                            @click="setType('direct')"
                        >
                            Direkt
                        </button>
                        <button
                            type="button"
                            class="rounded px-3 py-2 text-sm font-medium"
                            :class="conversationForm.type === 'group' ? 'bg-card text-primary shadow-sm' : 'text-secondary'"
                            @click="setType('group')"
                        >
                            Gruppe
                        </button>
                    </div>

                    <form class="mt-4 flex h-[calc(100%-5rem)] min-h-0 flex-col" @submit.prevent="createConversationAndOpen">
                        <div class="min-h-0 flex-1 overflow-y-auto pr-1 custom-scrollbar">
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
                                <div class="flex h-9 w-9 shrink-0 items-center justify-center rounded bg-buttonPrimary text-xs font-semibold text-buttonTextPrimary">
                                    {{ initials(member.name) }}
                                </div>
                                <div class="min-w-0 flex-1">
                                    <p class="truncate text-sm font-medium text-primary">{{ member.name }}</p>
                                    <p class="truncate text-xs text-secondary">{{ member.email }}</p>
                                </div>
                                <i
                                    class="las text-lg"
                                    :class="conversationForm.participant_ids.includes(member.id) ? 'la-check-circle text-success' : 'la-circle text-secondary'"
                                ></i>
                            </button>
                        </div>

                        <textarea
                            v-model="conversationForm.message"
                            rows="3"
                            placeholder="Erste Nachricht optional"
                            class="mt-3 resize-none rounded-lg border border-border bg-inputBg px-3 py-2 text-sm text-primary placeholder-secondary focus:border-primary focus:ring-primary"
                        />

                        <button
                            type="submit"
                            :disabled="conversationForm.processing || !canCreateConversation"
                            class="mt-3 rounded-lg bg-buttonPrimary px-4 py-2 text-sm font-semibold text-buttonTextPrimary transition hover:bg-buttonPrimaryHover disabled:cursor-not-allowed disabled:opacity-50"
                        >
                            Chat starten
                        </button>
                    </form>
                </aside>
            </div>
        </div>

        <!-- Modal für neue Konversation (Mobile) -->
        <div v-if="showNewConversationModal" class="fixed inset-0 z-50 flex items-center justify-center bg-black/50">
            <div class="flex h-full w-full flex-col bg-card sm:h-auto sm:w-full sm:max-w-md sm:rounded-lg">
                <!-- Header -->
                <div class="border-b border-border p-4">
                    <div class="flex items-center justify-between">
                        <h2 class="text-lg font-semibold text-primary">Neue Konversation</h2>
                        <button @click="showNewConversationModal = false" class="text-secondary hover:text-primary">
                            <i class="las la-times text-2xl"></i>
                        </button>
                    </div>
                </div>

                <!-- Tabs -->
                <div class="border-b border-border bg-inputBg p-2">
                    <div class="grid grid-cols-2 gap-1 rounded-lg">
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
                    </div>
                </div>

                <!-- User List -->
                <form class="flex min-h-0 flex-1 flex-col" @submit.prevent="createConversationAndOpen">
                    <div class="min-h-0 flex-1 overflow-y-auto p-2 custom-scrollbar">
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
                                <p class="text-sm font-medium text-primary">{{ member.name }}</p>
                                <p class="truncate text-xs text-secondary">{{ member.email }}</p>
                            </div>
                            <i
                                class="las text-xl"
                                :class="conversationForm.participant_ids.includes(member.id) ? 'la-check-circle text-success' : 'la-circle text-secondary'"
                            ></i>
                        </button>
                    </div>

                    <!-- Message Input -->
                    <div class="border-t border-border p-3">
                        <textarea
                            v-model="conversationForm.message"
                            rows="2"
                            placeholder="Erste Nachricht optional"
                            class="mb-3 w-full resize-none rounded-lg border border-border bg-inputBg px-3 py-2 text-sm text-primary placeholder-secondary focus:border-primary focus:ring-primary"
                        />

                        <!-- Buttons -->
                        <div class="flex gap-2">
                            <button
                                type="button"
                                @click="showNewConversationModal = false"
                                class="flex-1 rounded-lg border border-border px-4 py-2 text-sm font-medium text-secondary transition hover:bg-inputBg"
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
    </AppLayout>
</template>
