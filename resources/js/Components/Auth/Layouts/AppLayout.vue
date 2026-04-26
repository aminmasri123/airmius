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

let notificationInterval = null

const unreadCount = computed(() => page.props.notificationCenter?.unread_count || 0)
const latestNotifications = computed(() => page.props.notificationCenter?.latest || [])

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
        only: ['notificationCenter'],
        preserveScroll: true,
        preserveState: true,
    })
}

onMounted(() => {
    notificationInterval = window.setInterval(refreshNotifications, 8000)
})

onUnmounted(() => {
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
                <!-- 🔔 chats -->

                <div>
                    <Link href="/conversations" class="relative  hover:bg-muted rounded-lg">
                        <i class="las la-comments text-xl"></i>

                        <span
                            v-if="page.props.unreadChatsCount"
                            class="absolute -right-1 -top-1 min-w-5 px-1.5 py-0.5 text-[10px] bg-error text-white rounded-full"
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
