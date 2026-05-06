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
const notificationBox = ref(null)
const searchOpen = ref(false)
const searchBox = ref(null)
const isSmallScreen = ref(false)
const searchTerm = ref('')
const searchResults = ref([])
const searchLoading = ref(false)
const currentStatus = ref(page.props.auth?.user?.status || 'online')
const sidebarOpen = ref(false)
const notificationsMarkedReadLocally = ref(false)

let notificationInterval = null
let notificationChannel = null
let statusChannel = null
let markOfflineOnUnload = null
let searchTimeout = null

const serverUnreadCount = computed(() => page.props.notificationCenter?.unread_count || 0)
const unreadCount = computed(() => notificationsMarkedReadLocally.value ? 0 : serverUnreadCount.value)
const unreadChatsCount = computed(() => page.props.unreadChatsCount || 0)
const latestNotifications = computed(() => {
    const notifications = page.props.notificationCenter?.latest || []

    if (!notificationsMarkedReadLocally.value) {
        return notifications
    }

    return notifications.map((notification) => ({
        ...notification,
        read: true,
    }))
})

const statusOptions = [
    { value: 'online', label: 'status.online' },
    { value: 'offline', label: 'status.offline' },
    { value: 'training', label: 'status.training' },
    { value: 'work', label: 'status.work' },
]

const iconFor = (type) => ({
    'chat.message': 'las la-comment-dots',
    'post.comment': 'las la-comments',
    'event.comment': 'las la-calendar-check',
    'post.like': 'las la-heart',
    'friend.invite': 'las la-user-plus',
    'friend.accepted': 'las la-user-check',
    'user.followed': 'las la-user-plus',
}[type] || 'las la-bell')

const formatNotificationDate = (value) => {
    if (!value) return ''

    return new Intl.DateTimeFormat('de-DE', {
        day: '2-digit',
        month: '2-digit',
        hour: '2-digit',
        minute: '2-digit',
    }).format(new Date(value))
}

const closeNotifications = () => {
    notificationOpen.value = false
}

const markAllNotificationsAsRead = () => {
    if (!serverUnreadCount.value) return

    notificationsMarkedReadLocally.value = true

    router.post(route('auth.notifications.read-all'), {}, {
        preserveScroll: true,
        preserveState: true,
        only: ['notificationCenter', 'auth'],
    })
}

const toggleNotifications = () => {
    notificationOpen.value = !notificationOpen.value
    markAllNotificationsAsRead()
}

const closeNotificationOnOutsideClick = (event) => {
    if (!notificationOpen.value || !notificationBox.value) return

    if (!notificationBox.value.contains(event.target)) {
        closeNotifications()
    }
}

const markAsRead = (notification) => {
    if (notification.read) return

    router.post(route('auth.notifications.read', notification.id), {}, {
        preserveScroll: true,
    })
}

const openNotification = (notification) => {
    markAsRead(notification)
    closeNotifications()
}

const refreshNotifications = () => {
    if (document.hidden) return

    router.reload({
        only: ['notificationCenter', 'unreadChatsCount', 'friendCenter'],
        preserveScroll: true,
        preserveState: true,
    })
}

const setStatus = (status) => {
    currentStatus.value = status
    window.axios.put(route('auth.user.status.update'), { status }).catch(() => { })
}

const closeSearch = () => {
    searchOpen.value = false
    searchTerm.value = ''
    searchResults.value = []
}

const updateScreenSize = () => {
    if (typeof window === 'undefined') return

    isSmallScreen.value = window.matchMedia('(max-width: 639px)').matches
}

const closeSearchOnOutsideClick = (event) => {
    if (isSmallScreen.value || !searchOpen.value || !searchBox.value) return

    if (!searchBox.value.contains(event.target)) {
        closeSearch()
    }
}

const runSearch = () => {
    const term = searchTerm.value.trim()

    if (term.length < 2) {
        searchResults.value = []
        searchLoading.value = false
        return
    }

    searchLoading.value = true

    window.axios.get(route('auth.search'), { params: { q: term } })
        .then((response) => searchResults.value = response.data.results || [])
        .catch(() => searchResults.value = [])
        .finally(() => searchLoading.value = false)
}

const requestJoin = (result) => {
    if (!result.join_url) return

    router.post(result.join_url, {}, {
        preserveScroll: true,
        onSuccess: closeSearch,
    })
}

const bindRealtime = () => {
    if (!window.Echo || !page.props.auth?.user?.realtime) return

    notificationChannel = window.Echo
        .private(page.props.auth.user.realtime.notification_channel)
        .listen('.notification.created', () => refreshNotifications())

    statusChannel = window.Echo
        .join('users.status')
        .listen('.user.status.updated', () => { })

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
    updateScreenSize()

    setStatus(currentStatus.value === 'offline' ? 'online' : currentStatus.value)

    bindRealtime()

    markOfflineOnUnload = () => {
        window.axios.put(route('auth.user.status.update'), { status: 'offline' }).catch(() => { })
    }

    window.addEventListener('beforeunload', markOfflineOnUnload)
    window.addEventListener('resize', updateScreenSize)
    document.addEventListener('pointerdown', closeSearchOnOutsideClick)
    document.addEventListener('pointerdown', closeNotificationOnOutsideClick)

    notificationInterval = window.setInterval(refreshNotifications, 8000)
})

onUnmounted(() => {
    document.body.style.overflow = ''

    if (markOfflineOnUnload) {
        window.removeEventListener('beforeunload', markOfflineOnUnload)
    }

    window.removeEventListener('resize', updateScreenSize)
    document.removeEventListener('pointerdown', closeSearchOnOutsideClick)
    document.removeEventListener('pointerdown', closeNotificationOnOutsideClick)

    unbindRealtime()

    if (notificationInterval) {
        window.clearInterval(notificationInterval)
    }

    if (searchTimeout) {
        window.clearTimeout(searchTimeout)
    }
})

watch(searchTerm, () => {
    window.clearTimeout(searchTimeout)
    searchTimeout = window.setTimeout(runSearch, 250)
})

watch(serverUnreadCount, (count) => {
    if (count > 0) {
        notificationsMarkedReadLocally.value = false
    }
})

watch([sidebarOpen, searchOpen, isSmallScreen], ([isSidebarOpen, isSearchOpen, isMobile]) => {
    if (typeof document === 'undefined') return

    document.body.style.overflow = isSidebarOpen || (isSearchOpen && isMobile) ? 'hidden' : ''
})
</script>

<template>

    <Head :title="title" />

    <div class="h-dvh w-full overflow-hidden bg-bg text-primary">
        <Sidebar :open="sidebarOpen" @close="sidebarOpen = false" />

        <div class="flex h-dvh min-w-0 flex-1 flex-col md:pl-[260px]">
            <!-- Topbar -->
            <header class="sticky top-0 z-40 shrink-0 border-b border-border bg-card/95 backdrop-blur">
                <div class="flex h-16 items-center justify-between px-3 sm:px-4 lg:px-6">

                    <!-- LINKS -->
                    <div class="flex items-center gap-2 min-w-0">

                        <!-- ☰ MOBILE MENU BUTTON -->
                        <button type="button" class="rounded-lg p-2 hover:bg-muted md:hidden"
                            @click="sidebarOpen = !sidebarOpen">
                            <i class="las la-bars text-xl"></i>
                        </button>

                        <!-- Titel -->
                        <h1 class="truncate text-base font-semibold sm:text-lg">
                            {{ $t(title || 'Dashboard') }}
                        </h1>
                    </div>

                    <!-- RECHTS -->
                    <div class="flex items-center gap-2 sm:gap-3">

                        <!-- Search Mobile -->
                        <button type="button" class="rounded-lg p-2 hover:bg-muted sm:hidden"
                            @click="searchOpen = true">
                            <i class="las la-search text-xl"></i>
                        </button>

                        <!-- Search Desktop -->
                        <div ref="searchBox" class="relative hidden sm:block w-48 lg:w-72">
                            <i class="las la-search absolute left-3 top-1/2 -translate-y-1/2 text-secondary"></i>

                            <input v-model="searchTerm" @focus="searchOpen = true"
                                class="w-full rounded-lg border border-border bg-inputBg py-2 pl-9 pr-3 text-sm"
                                :placeholder="$t('search.placeholder')">

                            <div
                                v-if="searchOpen"
                                class="absolute left-0 right-0 top-full z-50 mt-2 overflow-hidden rounded-xl border border-border bg-card shadow-xl"
                            >
                                <div class="max-h-96 overflow-y-auto">
                                    <div v-if="searchTerm.trim().length < 2" class="p-4 text-sm text-secondary">
                                        Mindestens 2 Zeichen eingeben.
                                    </div>

                                    <div v-else-if="searchLoading" class="p-4 text-sm text-secondary">
                                        Suche lÃ¤uft...
                                    </div>

                                    <div v-else-if="searchResults.length">
                                        <div v-for="result in searchResults" :key="`${result.type}-${result.id}`"
                                            class="flex items-center gap-3 border-b border-border px-3 py-3 last:border-b-0">
                                            <Link :href="result.url" class="min-w-0 flex-1" @click="closeSearch">
                                                <p class="truncate text-sm font-semibold text-primary">
                                                    {{ result.title }}
                                                </p>
                                                <p class="truncate text-xs text-secondary">
                                                    {{ result.subtitle }}
                                                </p>
                                            </Link>

                                            <button v-if="result.join_url" type="button" @click="requestJoin(result)"
                                                class="shrink-0 rounded-lg border border-border px-2 py-2 text-xs hover:bg-inputBg">
                                                Beitreten
                                            </button>
                                        </div>
                                    </div>

                                    <div v-else class="p-4 text-center text-sm text-secondary">
                                        Keine passenden Ergebnisse.
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Chats -->
                        <Link href="/conversations" class="relative rounded-lg p-2 hover:bg-muted">
                            <i class="las la-comments text-xl"></i>
                            <span
                                v-if="unreadChatsCount"
                                class="absolute -right-1 -top-1 flex h-5 min-w-5 items-center justify-center rounded-full bg-red-500 px-1 text-[10px] font-bold leading-none text-white"
                            >
                                {{ unreadChatsCount > 99 ? '99+' : unreadChatsCount }}
                            </span>
                        </Link>

                        <!-- Notifications -->
                        <div ref="notificationBox" class="relative">
                            <button
                                type="button"
                                class="relative rounded-lg p-2 hover:bg-muted"
                                :class="{ 'bg-muted': notificationOpen }"
                                @click="toggleNotifications"
                            >
                                <i class="las la-bell text-xl"></i>
                                <span
                                    v-if="unreadCount"
                                    class="absolute -right-1 -top-1 flex h-5 min-w-5 items-center justify-center rounded-full bg-red-500 px-1 text-[10px] font-bold leading-none text-white"
                                >
                                    {{ unreadCount > 99 ? '99+' : unreadCount }}
                                </span>
                            </button>

                            <div
                                v-if="notificationOpen"
                                class="absolute right-0 top-full z-50 mt-2 w-[min(22rem,calc(100vw-1.5rem))] overflow-hidden rounded-xl border border-border bg-card shadow-xl"
                            >
                                <div class="flex items-center justify-between border-b border-border px-4 py-3">
                                    <div>
                                        <p class="text-sm font-semibold text-primary">Benachrichtigungen</p>
                                        <p class="text-xs text-secondary">
                                            {{ unreadCount ? `${unreadCount} ungelesen` : 'Alles gelesen' }}
                                        </p>
                                    </div>

                                    <Link
                                        :href="route('auth.notifications.index')"
                                        class="rounded-lg px-2 py-1 text-xs font-semibold text-secondary hover:bg-muted hover:text-primary"
                                        @click="closeNotifications"
                                    >
                                        Alle
                                    </Link>
                                </div>

                                <div v-if="latestNotifications.length" class="max-h-96 overflow-y-auto">
                                    <div
                                        v-for="notification in latestNotifications"
                                        :key="notification.id"
                                        class="flex gap-3 border-b border-border px-4 py-3 last:border-b-0 hover:bg-muted/60"
                                        :class="notification.read ? 'opacity-75' : ''"
                                    >
                                        <div
                                            class="flex h-10 w-10 shrink-0 items-center justify-center rounded-lg"
                                            :class="notification.read ? 'bg-inputBg text-secondary' : 'bg-buttonPrimary text-buttonTextPrimary'"
                                        >
                                            <i :class="[iconFor(notification.type), 'text-lg']"></i>
                                        </div>

                                        <div class="min-w-0 flex-1">
                                            <p class="truncate text-sm font-semibold text-primary">
                                                {{ notification.data?.title || 'Neue Benachrichtigung' }}
                                            </p>
                                            <p v-if="notification.data?.body" class="mt-0.5 line-clamp-2 text-xs text-secondary">
                                                {{ notification.data.body }}
                                            </p>
                                            <p class="mt-1 text-[11px] text-secondary">
                                                {{ formatNotificationDate(notification.created_at) }}
                                            </p>
                                        </div>

                                        <Link
                                            v-if="notification.data?.url"
                                            :href="notification.data.url"
                                            class="self-center rounded-lg border border-border px-2 py-1 text-xs font-semibold hover:bg-inputBg"
                                            @click="openNotification(notification)"
                                        >
                                            Öffnen
                                        </Link>

                                        <button
                                            v-else-if="!notification.read"
                                            type="button"
                                            class="self-center rounded-lg border border-border px-2 py-1 text-xs font-semibold hover:bg-inputBg"
                                            @click="markAsRead(notification)"
                                        >
                                            Gelesen
                                        </button>
                                    </div>
                                </div>

                                <div v-else class="px-4 py-8 text-center">
                                    <div class="mx-auto flex h-11 w-11 items-center justify-center rounded-lg bg-muted text-secondary">
                                        <i class="las la-bell-slash text-xl"></i>
                                    </div>
                                    <p class="mt-3 text-sm font-semibold text-primary">Keine Benachrichtigungen</p>
                                    <p class="mt-1 text-xs text-secondary">Neue Anfragen und Updates erscheinen hier.</p>
                                </div>
                            </div>
                        </div>

                        <!-- User -->
                        <div class="hidden sm:block">
                            <UserCard />
                        </div>

                    </div>
                </div>
            </header>

            <!-- Mobile Search Overlay -->
            <Teleport to="body">
                <div v-if="searchOpen" class="fixed inset-0 z-[70] bg-black/60 p-3 sm:hidden">
                    <div class="overflow-hidden rounded-2xl border border-border bg-card shadow-xl">
                        <div class="flex items-center gap-2 border-b border-border p-3">
                            <div class="relative min-w-0 flex-1">
                                <i class="las la-search absolute left-3 top-1/2 -translate-y-1/2 text-secondary"></i>

                                <input v-model="searchTerm"
                                    class="w-full rounded-lg border border-border bg-inputBg py-3 pl-9 pr-3 text-sm text-primary"
                                    :placeholder="$t('search.short')" autofocus>
                            </div>

                            <button type="button"
                                class="rounded-lg px-3 py-2 text-sm text-secondary hover:bg-muted hover:text-primary"
                                @click="closeSearch">
                                Schließen
                            </button>
                        </div>

                        <div class="max-h-[70vh] overflow-y-auto">
                            <div v-if="searchTerm.trim().length < 2" class="p-4 text-sm text-secondary">
                                Mindestens 2 Zeichen eingeben.
                            </div>

                            <div v-else-if="searchLoading" class="p-4 text-sm text-secondary">
                                Suche läuft...
                            </div>

                            <div v-else-if="searchResults.length">
                                <div v-for="result in searchResults" :key="`${result.type}-${result.id}`"
                                    class="flex items-center gap-3 border-b border-border px-3 py-3 last:border-b-0">
                                    <Link :href="result.url" class="min-w-0 flex-1" @click="closeSearch">
                                        <p class="truncate text-sm font-semibold text-primary">
                                            {{ result.title }}
                                        </p>
                                        <p class="truncate text-xs text-secondary">
                                            {{ result.subtitle }}
                                        </p>
                                    </Link>

                                    <button v-if="result.join_url" type="button" @click="requestJoin(result)"
                                        class="shrink-0 rounded-lg border border-border px-2 py-2 text-xs hover:bg-inputBg">
                                        Beitreten
                                    </button>
                                </div>
                            </div>

                            <div v-else class="p-4 text-center text-sm text-secondary">
                                Keine passenden Ergebnisse.
                            </div>
                        </div>
                    </div>
                </div>
            </Teleport>

            <!-- Content -->
            <main class="min-h-0 flex-1 overflow-y-auto overflow-x-hidden overscroll-contain p-3 pb-24 sm:p-4 lg:p-6">
                <slot />
            </main>
        </div>
    </div>
</template>
