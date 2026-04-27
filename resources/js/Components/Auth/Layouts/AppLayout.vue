<!-- Components/Layouts/AppLayout.vue -->
<script setup>
import Sidebar from '@/Components/Auth/Sidebar.vue'
import { Head, Link, router, usePage } from '@inertiajs/vue3'
import UserCard from '@/Components/Auth/UserCard.vue'
import { ref, computed, onMounted, onUnmounted, watch } from 'vue'

defineProps({
    title: String
})

const page = usePage()

const notificationOpen = ref(false)
const currentStatus = ref(page.props.auth?.user?.status || 'online')

let notificationInterval = null
let notificationChannel = null
let statusChannel = null
let markOfflineOnUnload = null

const unreadCount = computed(() => page.props.notificationCenter?.unread_count || 0)
const latestNotifications = computed(() => page.props.notificationCenter?.latest || [])
const statusOptions = [
    { value: 'online', label: 'Online' },
    { value: 'offline', label: 'Offline' },
    { value: 'training', label: 'Training' },
    { value: 'work', label: 'Work' },
]

const iconFor = (type) => ({
    'chat.message': 'las la-comment-dots',
    'post.comment': 'las la-comments',
    'post.like': 'las la-heart',
    'friend.invite': 'las la-user-plus',
    'friend.accepted': 'las la-user-check',
}[type] || 'las la-bell')

const markAsRead = (notification) => {
    if (notification.read) return

    router.post(route('auth.notifications.read', notification.id), {}, {
        preserveScroll: true,
    })
}

const refreshNotifications = () => {
    if (document.hidden) return

    router.reload({
        only: ['notificationCenter', 'unreadChatsCount'],
        preserveScroll: true,
        preserveState: true,
    })
}

const setStatus = (status) => {
    currentStatus.value = status
    window.axios.put(route('auth.user.status.update'), { status }).catch(() => {})
}

const bindRealtime = () => {
    if (!window.Echo || !page.props.auth?.user?.realtime) return

    notificationChannel = window.Echo
        .private(page.props.auth.user.realtime.notification_channel)
        .listen('.notification.created', () => refreshNotifications())

    statusChannel = window.Echo
        .join('users.status')
        .listen('.user.status.updated', () => {})

    const eventChannels = [
        ...(page.props.auth.user.realtime.team_event_channels || []),
        ...(page.props.auth.user.realtime.club_event_channels || []),
        'events.public',
    ]

    eventChannels.forEach((channel) => {
        const subscription = channel === 'events.public'
            ? window.Echo.channel(channel)
            : window.Echo.private(channel)

        subscription.listen('.event.updated', () => {
            if (window.location.pathname.startsWith('/events')) {
                router.reload({ preserveScroll: true, preserveState: true })
            }
        })
    })
}

const unbindRealtime = () => {
    if (!window.Echo || !page.props.auth?.user?.realtime) return

    if (notificationChannel) {
        window.Echo.leave(page.props.auth.user.realtime.notification_channel)
    }

    if (statusChannel) {
        window.Echo.leave('users.status')
    }

    const eventChannels = [
        ...(page.props.auth.user.realtime.team_event_channels || []),
        ...(page.props.auth.user.realtime.club_event_channels || []),
        'events.public',
    ]

    eventChannels.forEach((channel) => {
        window.Echo.leave(channel)
    })
}

onMounted(() => {
    setStatus(currentStatus.value === 'offline' ? 'online' : currentStatus.value)
    bindRealtime()
    markOfflineOnUnload = () => {
        window.axios.put(route('auth.user.status.update'), { status: 'offline' }).catch(() => {})
    }
    window.addEventListener('beforeunload', markOfflineOnUnload)
    notificationInterval = window.setInterval(refreshNotifications, 8000)
})

onUnmounted(() => {
    if (markOfflineOnUnload) {
        window.removeEventListener('beforeunload', markOfflineOnUnload)
    }

    unbindRealtime()

    if (notificationInterval) {
        window.clearInterval(notificationInterval)
    }
})
</script>

<template>
<Head :title="title" />

<div class="flex h-screen bg-bg text-primary">
    <Sidebar />

    <div class="flex-1 flex flex-col">

        <!-- Topbar -->
        <div class="h-16 bg-card flex items-center justify-between px-6 border-b border-border">

            <h1 class="text-lg font-semibold">Dashboard</h1>

            <div class="flex items-center gap-4">
                <select
                    v-model="currentStatus"
                    class="hidden rounded-lg border border-border bg-inputBg px-2 py-1 text-xs text-primary focus:border-borderHover focus:ring-borderHover sm:block"
                    @change="setStatus(currentStatus)"
                >
                    <option v-for="option in statusOptions" :key="option.value" :value="option.value">
                        {{ option.label }}
                    </option>
                </select>

                <!-- 🔔 chats -->

                <div class="relative">
                    <Link href="/conversations" class="hover:bg-muted rounded-lg p-2">
                        <i class="las la-comments text-xl"></i>

                        <span
                            v-if="page.props.unreadChatsCount"
                            class="absolute -right-1 -top-3 min-w-5 px-1.5 py-0.5 text-[10px] bg-error text-white rounded-full"
                        >
                            {{ page.props.unreadChatsCount > 99 ? '99+' : page.props.unreadChatsCount }}
                        </span>
                    </Link>
                </div>

                <!-- 🔔 Notifications -->
                <div class="relative">
                    <button
                        @click="notificationOpen = !notificationOpen"
                        class="relative p-2 hover:bg-muted rounded-lg"
                    >
                        <i class="las la-bell text-xl"></i>

                        <span
                            v-if="unreadCount"
                            class="absolute -right-1 -top-1 min-w-5 px-1.5 py-0.5 text-[10px] bg-error text-white rounded-full"
                        >
                            {{ unreadCount > 99 ? '99+' : unreadCount }}
                        </span>
                    </button>

                    <!-- Dropdown -->
                    <div
                        v-if="notificationOpen"
                        class="absolute right-0 mt-2 w-80 rounded-xl border border-border bg-card shadow-xl z-50"
                    >
                        <div class="flex justify-between items-center px-3 py-2 border-b">
                            <span class="text-sm font-semibold">Benachrichtigungen</span>
                            <Link href="/notifications" class="text-xs text-air-blue" @click="notificationOpen = false">
                                Alle
                            </Link>
                        </div>

                        <div v-if="latestNotifications.length" class="max-h-80 overflow-y-auto">
                            <Link
                                v-for="notification in latestNotifications"
                                :key="notification.id"
                                :href="notification.data?.url || '/notifications'"
                                class="flex gap-3 px-3 py-3 border-b hover:bg-muted"
                                @click="markAsRead(notification); notificationOpen = false"
                            >
                                <i :class="[iconFor(notification.type), 'text-lg']"></i>

                                <div>
                                    <p class="text-xs font-semibold">
                                        {{ notification.data?.title || 'Neue Benachrichtigung' }}
                                    </p>
                                    <p class="text-xs text-secondary">
                                        {{ notification.data?.body }}
                                    </p>
                                </div>
                            </Link>
                        </div>

                        <div v-else class="p-4 text-sm text-secondary text-center">
                            Keine Benachrichtigungen
                        </div>
                    </div>
                </div>

                <!-- 👤 User -->
                <UserCard />

            </div>
        </div>

        <!-- Content -->
        <main class="flex-1 overflow-auto p-6">
            <slot />
        </main>
    </div>
</div>
</template>
