<!-- Components/Layouts/AppLayout.vue -->
<script setup>
import Sidebar from '@/Components/Auth/Sidebar.vue'
import AppMobileBottomNav from '@/Components/Auth/Layouts/AppMobileBottomNav.vue'
import AppMobileSearchOverlay from '@/Components/Auth/Layouts/AppMobileSearchOverlay.vue'
import { Head, Link, router, usePage } from '@inertiajs/vue3'
import UserCard from '@/Components/Auth/UserCard.vue'
import { ref, computed, onMounted, onUnmounted, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import { useAirmiusShellNavigation } from '@/composables/useAirmiusShellNavigation'
import { createPartialReloader } from '@/services/partialReload'

const props = defineProps({
    title: String
})

const page = usePage()
const { workspace } = useAirmiusShellNavigation()
const { t, te, locale } = useI18n()
const workspaceTitle = computed(() => workspace.value.translated ? t(workspace.value.label) : workspace.value.label)
const showMobileBottomNav = computed(() => ![
    'Auth/Dashboard/Training/Index',
    'Auth/Dashboard/Training/LogCreate',
].includes(page.component))
const localeCode = computed(() => ({
    de: 'de-DE',
    en: 'en-US',
    fr: 'fr-FR',
    ar: 'ar-EG',
}[locale.value] || 'de-DE'))
const permissionDeniedMessage = computed(() => te('global_feedback.permission_denied') ? t('global_feedback.permission_denied') : 'Du hast dafür keine Berechtigung.')
const feedbackText = (key, fallback, params = {}) => te(key) ? t(key, params) : fallback
const defaultBackendPermissionMessages = new Set([
    'Forbidden',
    'This action is forbidden.',
    'This action is unauthorized.',
])

const componentTitles = {
    'Auth/Dashboard/Index': 'Dashboard',
    'Auth/Dashboard/Workspaces/Index': 'Arbeitsbereiche',
    'Auth/Dashboard/ClubCockpit/Index': 'Vereins-Cockpit',
    'Auth/Dashboard/Feed/Index': 'Feed',
    'Auth/Dashboard/Files/Index': 'Dateien',
    'Auth/Dashboard/Events/Index': 'Events',
    'Auth/Dashboard/Training/Index': 'Trainingspläne',
    'Auth/Dashboard/Training/LogCreate': 'Training dokumentieren',
    'Auth/Dashboard/Nutrition/Index': 'Ernährung',
    'Auth/Dashboard/SportMap/Index': 'Sportkarte',
    'Auth/Dashboard/Friends/Index': 'Freunde',
    'Auth/Dashboard/Rides/Index': 'Fahrgemeinschaften',
    'Auth/Dashboard/Notifications/Index': 'Benachrichtigungen',
    'Auth/Dashboard/Settings/Index': 'Einstellungen',
    'Auth/Dashboard/Learning/MyCourses': 'Meine Kurse',
    'Auth/Dashboard/Learning/Studio': 'Sportschule',
    'Auth/Dashboard/Commerce/Index': 'Commerce',
    'Auth/Dashboard/Commerce/Cart': 'Warenkorb',
    'Auth/Dashboard/Commerce/BankTransfer': 'Überweisung',
    'Auth/Dashboard/OutfitSubscriptions/Index': 'Outfit-Abo',
    'Auth/Dashboard/Badges/UserIndex': 'Meine Badges',
    'Auth/Dashboard/Badges/Index': 'Badges verwalten',
    'Auth/Dashboard/Blogs/Index': 'Blogs',
    'Auth/Dashboard/Blogs/Categories': 'Blog-Kategorien',
    'Auth/Dashboard/ClubMemberships/Index': 'Mitglieder & Finanzen',
    'Auth/Dashboard/Teams/Index': 'Vereine & Teams',
    'Auth/Dashboard/Users/Index': 'Nutzerverwaltung',
    'Auth/Dashboard/Users/Create': 'Neuen Nutzer erstellen',
    'Auth/Dashboard/Users/Edit': 'Nutzer bearbeiten',
    'Auth/Dashboard/RolesPermissions/Index': 'Rollen & Rechte',
    'Auth/Dashboard/GamificationRules/Index': 'Gamification',
    'Auth/Dashboard/Sports/Index': 'Sportarten verwalten',
    'Auth/Dashboard/Sponsors/Index': 'Sponsoren',
    'Auth/Dashboard/MediaGuidelines/Index': 'Bildmaße',
    'Auth/Dashboard/Admin/Subscriptions/Index': 'Abo-Verwaltung',
    'Auth/Dashboard/Admin/Clubs/Index': 'Vereinsverwaltung',
    'Auth/Dashboard/Admin/SubscriptionInvoices/Index': 'Abo-Rechnungen',
    'Auth/Dashboard/Admin/ClubVerifications/Index': 'Vereinsprüfung',
    'Auth/Dashboard/Admin/MailCenter/Index': 'Mail-Zentrale',
    'Auth/Dashboard/Admin/ProviderCosts/Index': 'Provider-Kosten',
    'Auth/Dashboard/Admin/Settings/Index': 'Systemeinstellungen',
    'Auth/Dashboard/Admin/Payments/Index': 'Zahlungen',
    'Auth/Dashboard/Admin/Invoices/Index': 'Rechnungen',
    'Auth/Dashboard/Admin/OperatingContracts/Index': 'Betriebskosten & Verträge',
    'Auth/Dashboard/Admin/Commerce/Index': 'Admin Commerce',
    'Auth/Dashboard/Admin/OutfitSubscriptions/Index': 'Admin Outfit-Abos',
    'Auth/Dashboard/Admin/Moderation/Index': 'Moderation',
    'Auth/Dashboard/Admin/Operations/Index': 'Operations Center',
}

const dynamicPageTitle = computed(() => {
    const props = page.props || {}

    return props.profileUser?.name
        || props.teamProfile?.name
        || props.clubProfile?.name
        || props.event?.title
        || props.product?.title
        || props.item?.title
        || props.log?.title
        || props.award?.badge?.name
        || null
})

const pageTitle = computed(() => {
    return props.title
        || dynamicPageTitle.value
        || componentTitles[page.component]
        || 'Airmius'
})
const translatedPageTitle = computed(() => {
    const title = pageTitle.value

    return te(title) ? t(title) : title
})

const notificationOpen = ref(false)
const notificationBox = ref(null)
const searchOpen = ref(false)
const searchTerm = ref('')
const searchResults = ref([])
const searchLoading = ref(false)
const searchError = ref('')
const currentStatus = ref(page.props.auth?.user?.status || 'online')
const sidebarOpen = ref(false)
const isRtl = computed(() => page.props.direction === 'rtl')
const notificationsMarkedReadLocally = ref(false)
const realtimeNotifications = ref([])
const notificationRealtimeReady = ref(false)
const feedbackMessages = ref([])

let notificationInterval = null
let notificationChannel = null
let realtimeConnection = null
let realtimeStateHandler = null
let statusChannel = null
let markOfflineOnUnload = null
let searchTimeout = null
let searchController = null
let feedbackId = 0
const feedbackTimers = new Map()
let stopInertiaSuccess = null
let stopInertiaError = null
let stopInertiaInvalid = null
let stopInertiaException = null
const recentFeedback = new Map()
const shownFlashIds = new Set()
const notificationReloader = createPartialReloader({
    only: () => [
        'notificationCenter',
        'unreadChatsCount',
        'friendCenter',
        ...(page.component === 'Auth/Dashboard/Notifications/Index' ? ['notifications'] : []),
    ],
    onSuccess: (responsePage) => {
        const serverIds = new Set(
            (responsePage.props.notificationCenter?.latest || []).map((notification) => Number(notification.id)),
        )

        realtimeNotifications.value = realtimeNotifications.value
            .filter((notification) => !serverIds.has(Number(notification.id)))
    },
})
const eventReloader = createPartialReloader({
    only: ['events', 'calendarEvents', 'eventStats', 'nextEvent', 'calendar'],
    minInterval: 1200,
})

const serverUnreadCount = computed(() => page.props.notificationCenter?.unread_count || 0)
const pendingRealtimeNotifications = computed(() => {
    const serverIds = new Set(
        (page.props.notificationCenter?.latest || []).map((notification) => Number(notification.id)),
    )

    return realtimeNotifications.value
        .filter((notification) => !serverIds.has(Number(notification.id)))
})
const pendingRealtimeUnreadCount = computed(() => pendingRealtimeNotifications.value
    .filter((notification) => !notification.read)
    .length)
const unreadCount = computed(() => notificationsMarkedReadLocally.value
    ? 0
    : serverUnreadCount.value + pendingRealtimeUnreadCount.value)
const unreadChatsCount = computed(() => page.props.unreadChatsCount || 0)
const latestNotifications = computed(() => {
    const notifications = [
        ...pendingRealtimeNotifications.value,
        ...(page.props.notificationCenter?.latest || []),
    ].slice(0, 5)

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
      'event.cancelled': 'las la-calendar-times',
      'post.like': 'las la-heart',
    'friend.invite': 'las la-user-plus',
    'friend.accepted': 'las la-user-check',
    'team.invite': 'las la-users',
    'team.trainer_mentioned': 'las la-chalkboard-teacher',
    'team.member_removed': 'las la-user-minus',
    'club.member_removed': 'las la-user-times',
    'club.member_left': 'las la-door-open',
    'team.member_left': 'las la-door-open',
    'club.membership_request_created': 'las la-user-plus',
    'club.membership_request_withdrawn': 'las la-user-minus',
    'club.member_removal_objection': 'las la-exclamation-circle',
    'guardian.consent_requested': 'las la-user-shield',
    'profile.recommendation_received': 'las la-star',
    'profile.trainer_mentioned': 'las la-chalkboard-teacher',
    'user.followed': 'las la-user-plus',
    'commerce.order.created': 'las la-shopping-bag',
    'commerce.order.issue_reported': 'las la-exclamation-circle',
    'commerce.order.issue_replied': 'las la-comments',
    'commerce.order.paid': 'las la-receipt',
    'commerce.order.shipping_updated': 'las la-shipping-fast',
    'invoice.created': 'las la-file-invoice',
    'invoice.status_updated': 'las la-file-invoice-dollar',
    'subscription.updated': 'las la-credit-card',
    'club.subscription.updated': 'las la-credit-card',
    'admin.ai_token.problem': 'las la-key',
    'admin.ai_token.expiring': 'las la-key',
}[type] || 'las la-bell')

const formatNotificationDate = (value) => {
    if (!value) return ''

    return new Intl.DateTimeFormat(localeCode.value, {
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
    notificationReloader.refresh()
}

const receiveRealtimeNotification = (event) => {
    const notification = event?.notification

    if (!notification?.id) {
        refreshNotifications()
        return
    }

    if (notification.type === 'chat.message') {
        refreshNotifications()
        return
    }

    const alreadyKnown = latestNotifications.value
        .some((item) => Number(item.id) === Number(notification.id))

    if (!alreadyKnown) {
        realtimeNotifications.value = [notification, ...realtimeNotifications.value].slice(0, 5)
        notificationsMarkedReadLocally.value = false
    }

    refreshNotifications()
}

const setStatus = (status) => {
    currentStatus.value = status
    window.axios.put(route('auth.user.status.update'), { status }).catch(() => { })
}

const closeSearch = () => {
    searchController?.abort()
    searchController = null
    window.clearTimeout(searchTimeout)
    searchOpen.value = false
    searchTerm.value = ''
    searchResults.value = []
    searchLoading.value = false
    searchError.value = ''
}

const openSearch = () => {
    notificationOpen.value = false
    sidebarOpen.value = false
    searchOpen.value = true
}

const handleGlobalShortcut = (event) => {
    if ((event.metaKey || event.ctrlKey) && event.key.toLowerCase() === 'k') {
        event.preventDefault()
        openSearch()
    }
}

const isCanceledRequest = (error) => error?.code === 'ERR_CANCELED' || error?.name === 'CanceledError'

const runSearch = async () => {
    const term = searchTerm.value.trim()

    if (term.length < 2) {
        searchResults.value = []
        searchLoading.value = false
        return
    }

    searchController?.abort()
    const controller = new AbortController()
    searchController = controller
    searchLoading.value = true
    searchError.value = ''

    try {
        const response = await window.axios.get(route('auth.search'), {
            params: { q: term },
            signal: controller.signal,
            headers: { 'X-Locale': locale.value },
        })
        if (searchController === controller && searchTerm.value.trim() === term) {
            searchResults.value = response.data.results || []
        }
    } catch (requestError) {
        if (!isCanceledRequest(requestError) && searchController === controller) {
            searchResults.value = []
            searchError.value = t('search.error')
        }
    } finally {
        if (searchController === controller && !controller.signal.aborted) {
            searchController = null
            searchLoading.value = false
        }
    }
}

const requestJoin = (result) => {
    if (!result.join_url) return

    router.post(result.join_url, {}, {
        preserveScroll: true,
        onSuccess: closeSearch,
    })
}

const removeFeedback = (id) => {
    feedbackMessages.value = feedbackMessages.value.filter((message) => message.id !== id)

    if (feedbackTimers.has(id)) {
        window.clearTimeout(feedbackTimers.get(id))
        feedbackTimers.delete(id)
    }
}

const addFeedback = (type, message, flashId = null) => {
    const text = String(message || '').trim()

    if (!text) return

    if (flashId) {
        const flashKey = `${type}:${flashId}`

        if (shownFlashIds.has(flashKey)) return

        shownFlashIds.add(flashKey)
    }

    const signature = `${type}:${text}`
    const now = Date.now()
    const recentUntil = recentFeedback.get(signature) || 0

    if (!flashId && recentUntil > now) return

    if (!flashId) {
        recentFeedback.set(signature, now + 12000)
    }

    recentFeedback.forEach((until, key) => {
        if (until <= now) {
            recentFeedback.delete(key)
        }
    })

    const existing = feedbackMessages.value.find((item) => item.type === type && item.message === text)

    if (existing) {
        feedbackMessages.value = [
            existing,
            ...feedbackMessages.value.filter((item) => item.id !== existing.id),
        ]

        if (feedbackTimers.has(existing.id)) {
            window.clearTimeout(feedbackTimers.get(existing.id))
        }

        feedbackTimers.set(existing.id, window.setTimeout(() => removeFeedback(existing.id), type === 'error' ? 7000 : 4500))
        return
    }

    const id = ++feedbackId
    feedbackMessages.value.unshift({
        id,
        type,
        message: text,
    })

    if (feedbackMessages.value.length > 4) {
        feedbackMessages.value.slice(4).forEach((item) => removeFeedback(item.id))
    }

    feedbackTimers.set(id, window.setTimeout(() => removeFeedback(id), type === 'error' ? 7000 : 4500))
}

const normalizeFeedbackMessage = (message) => {
    const text = String(message || '').trim()

    if (defaultBackendPermissionMessages.has(text)) {
        return permissionDeniedMessage.value
    }

    return text
}

const firstErrorMessage = (errors) => {
    const values = Object.values(errors || {}).flat()
    const first = values.map(normalizeFeedbackMessage).find((value) => value)

    return first || feedbackText('global_feedback.validation', 'Aktion konnte nicht abgeschlossen werden. Bitte prüfe deine Eingaben.')
}

const showFlashFeedback = (flash = {}) => {
    if (flash.success) {
        addFeedback('success', flash.success, flash.id)
    }

    if (flash.error) {
        addFeedback('error', flash.error, flash.id)
    }

    if (flash.message) {
        addFeedback('info', flash.message, flash.id)
    }
}

const httpErrorMessage = (status) => {
    if (status === 401) return feedbackText('global_feedback.session_expired', 'Deine Sitzung ist abgelaufen. Bitte melde dich erneut an.')
    if (status === 403) return permissionDeniedMessage.value
    if (status === 404) return feedbackText('global_feedback.not_found', 'Der angeforderte Inhalt wurde nicht gefunden.')
    if (status === 419) return feedbackText('global_feedback.session_reload', 'Die Sitzung ist abgelaufen. Bitte lade die Seite neu und versuche es erneut.')
    if (status === 422) return feedbackText('global_feedback.validation', 'Bitte prüfe die Eingaben.')
    if (status >= 500) return feedbackText('global_feedback.server', 'Serverfehler. Bitte versuche es gleich erneut.')

    return feedbackText('global_feedback.action_failed', 'Aktion konnte nicht abgeschlossen werden.')
}

const installGlobalFeedback = () => {
    showFlashFeedback(page.props.flash || {})

    stopInertiaSuccess = router.on('success', (event) => {
        showFlashFeedback(event.detail.page.props.flash || {})
    })

    stopInertiaError = router.on('error', (event) => {
        addFeedback('error', firstErrorMessage(event.detail.errors || {}))
    })

    stopInertiaInvalid = router.on('invalid', (event) => {
        event.preventDefault()
        addFeedback('error', httpErrorMessage(event.detail.response?.status))
    })

    stopInertiaException = router.on('exception', (event) => {
        event.preventDefault()
        addFeedback('error', 'Unerwarteter Fehler. Bitte versuche es erneut.')
    })
}

const uninstallGlobalFeedback = () => {
    stopInertiaSuccess?.()
    stopInertiaError?.()
    stopInertiaInvalid?.()
    stopInertiaException?.()

    feedbackTimers.forEach((timer) => window.clearTimeout(timer))
    feedbackTimers.clear()
    recentFeedback.clear()
    shownFlashIds.clear()
}

const bindRealtime = () => {
    if (!window.Echo || !page.props.auth?.user?.realtime) return

    realtimeConnection = window.Echo.connector?.pusher?.connection || null
    if (realtimeConnection) {
        realtimeStateHandler = ({ current }) => {
            if (current !== 'connected') notificationRealtimeReady.value = false
        }
        realtimeConnection.bind('state_change', realtimeStateHandler)
    }

    notificationChannel = window.Echo
        .private(page.props.auth.user.realtime.notification_channel)
        .subscribed(() => {
            notificationRealtimeReady.value = true
            refreshNotifications()
        })
        .error(() => {
            notificationRealtimeReady.value = false
        })
        .listen('.notification.created', receiveRealtimeNotification)

    statusChannel = window.Echo
        .join('users.status')
        .listen('.user.status.updated', (event) => {
            if (Number(event.user?.id) === Number(page.props.auth?.user?.id)) {
                currentStatus.value = event.user.status
            }

            window.dispatchEvent(new CustomEvent('airmius:user-status-updated', {
                detail: event.user,
            }))
        })

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
                eventReloader.refresh()
            }
        })
    })
}

const unbindRealtime = () => {
    if (!window.Echo || !page.props.auth?.user?.realtime) return

    notificationRealtimeReady.value = false

    if (realtimeConnection && realtimeStateHandler) {
        realtimeConnection.unbind('state_change', realtimeStateHandler)
    }
    realtimeConnection = null
    realtimeStateHandler = null

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
    installGlobalFeedback()

    setStatus(currentStatus.value === 'offline' ? 'online' : currentStatus.value)

    bindRealtime()

    markOfflineOnUnload = () => {
        window.axios.put(route('auth.user.status.update'), { status: 'offline' }).catch(() => { })
    }

    window.addEventListener('beforeunload', markOfflineOnUnload)
    document.addEventListener('keydown', handleGlobalShortcut)
    document.addEventListener('pointerdown', closeNotificationOnOutsideClick)

    // Poll only while the private realtime channel is unavailable.
    notificationInterval = window.setInterval(() => {
        if (!notificationRealtimeReady.value) refreshNotifications()
    }, 5000)
    document.addEventListener('visibilitychange', refreshNotifications)
    window.addEventListener('online', refreshNotifications)
})

onUnmounted(() => {
    document.body.style.overflow = ''
    uninstallGlobalFeedback()

    if (markOfflineOnUnload) {
        window.removeEventListener('beforeunload', markOfflineOnUnload)
    }

    document.removeEventListener('keydown', handleGlobalShortcut)
    document.removeEventListener('pointerdown', closeNotificationOnOutsideClick)
    document.removeEventListener('visibilitychange', refreshNotifications)
    window.removeEventListener('online', refreshNotifications)

    unbindRealtime()

    if (notificationInterval) {
        window.clearInterval(notificationInterval)
    }

    notificationReloader.cancel()
    eventReloader.cancel()

    if (searchTimeout) {
        window.clearTimeout(searchTimeout)
    }
    searchController?.abort()
})

watch(searchTerm, () => {
    window.clearTimeout(searchTimeout)
    searchController?.abort()
    searchController = null
    searchError.value = ''

    if (searchTerm.value.trim().length < 2) {
        searchResults.value = []
        searchLoading.value = false
        return
    }

    searchResults.value = []
    searchLoading.value = true
    searchTimeout = window.setTimeout(runSearch, 300)
})

watch(serverUnreadCount, (count) => {
    if (count > 0) {
        notificationsMarkedReadLocally.value = false
    }
})

watch([sidebarOpen, searchOpen], ([isSidebarOpen, isSearchOpen]) => {
    if (typeof document === 'undefined') return

    document.body.style.overflow = isSidebarOpen || isSearchOpen ? 'hidden' : ''
})
</script>

<template>

    <Head :title="translatedPageTitle" />

    <div class="min-h-dvh w-full bg-bg text-primary">
        <a href="#main-content" class="skip-link">{{ t('Zum Hauptinhalt springen') }}</a>

        <Sidebar :open="sidebarOpen" @close="sidebarOpen = false" />

        <Teleport to="body">
            <div
                v-if="feedbackMessages.length"
                class="pointer-events-none fixed inset-x-0 bottom-4 z-[90] flex flex-col gap-2 px-3 sm:bottom-auto sm:left-auto sm:right-4 sm:top-4 sm:w-[min(24rem,calc(100vw-2rem))] sm:px-0"
                role="status"
                aria-live="polite"
                aria-atomic="false"
            >
                <TransitionGroup name="airmius-feedback" tag="div" class="space-y-2">
                    <article
                        v-for="feedback in feedbackMessages"
                        :key="feedback.id"
                        class="pointer-events-auto flex items-start gap-3 rounded-2xl border bg-card/95 p-3 shadow-2xl backdrop-blur"
                        :class="{
                            'border-success/40': feedback.type === 'success',
                            'border-danger/40': feedback.type === 'error',
                            'border-air-blue/40': feedback.type === 'info',
                        }"
                    >
                        <span
                            class="mt-0.5 flex h-9 w-9 shrink-0 items-center justify-center rounded-xl"
                            :class="{
                                'bg-success/15 text-success': feedback.type === 'success',
                                'bg-danger/15 text-danger': feedback.type === 'error',
                                'bg-air-blue/15 text-air-blue': feedback.type === 'info',
                            }"
                        >
                            <i
                                :class="[
                                    feedback.type === 'success' ? 'las la-check' : (feedback.type === 'error' ? 'las la-exclamation-circle' : 'las la-info-circle'),
                                    'text-xl'
                                ]"
                            ></i>
                        </span>
                        <div class="min-w-0 flex-1">
                            <p class="text-sm font-bold text-primary">
                                {{ feedback.type === 'success' ? 'Gespeichert' : (feedback.type === 'error' ? 'Hinweis' : 'Info') }}
                            </p>
                            <p class="mt-0.5 text-sm leading-5 text-secondary">{{ feedback.message }}</p>
                        </div>
                        <button
                            type="button"
                            class="rounded-lg p-1.5 text-secondary hover:bg-muted hover:text-primary"
                            aria-label="Meldung schließen"
                            @click="removeFeedback(feedback.id)"
                        >
                            <i class="las la-times text-lg"></i>
                        </button>
                    </article>
                </TransitionGroup>
            </div>
        </Teleport>

        <div
            class="flex min-h-dvh min-w-0 flex-1 flex-col"
            :class="isRtl ? 'md:pr-72' : 'md:pl-72'"
        >
            <!-- Topbar -->
            <header class="sticky top-0 z-40 shrink-0 border-b border-border bg-card/95 backdrop-blur">
                <div class="flex h-16 items-center justify-between px-3 sm:px-4 lg:px-6">

                    <!-- LINKS -->
                    <div class="flex items-center gap-2 min-w-0">

                        <!-- ☰ MOBILE MENU BUTTON -->
                        <button type="button" class="rounded-lg p-2 hover:bg-muted md:hidden"
                            :aria-label="t('Navigation öffnen')"
                            :aria-expanded="sidebarOpen"
                            @click="sidebarOpen = !sidebarOpen">
                            <i class="las la-bars text-xl"></i>
                        </button>

                        <!-- Titel -->
                        <div class="min-w-0">
                            <p class="hidden truncate text-[10px] font-black uppercase tracking-[0.14em] text-secondary sm:block">
                                {{ workspaceTitle }}
                            </p>
                            <h1 class="truncate text-base font-semibold sm:text-lg">{{ translatedPageTitle }}</h1>
                        </div>
                    </div>

                    <!-- RECHTS -->
                    <div class="flex items-center gap-2 sm:gap-3">
                        <Link
                            :href="route('welcome')"
                            class="hidden items-center gap-2 rounded-lg border border-border px-3 py-2 text-sm font-semibold text-primary hover:bg-muted lg:flex"
                        >
                            <i class="las la-external-link-alt text-lg"></i>
                            <span>{{ t('Gastseite') }}</span>
                        </Link>

                        <!-- Search Mobile -->
                        <button type="button" class="rounded-lg p-2 hover:bg-muted sm:hidden"
                            :aria-label="t('Suche öffnen')"
                            @click="openSearch">
                            <i class="las la-search text-xl"></i>
                        </button>

                        <!-- Search Desktop -->
                        <button
                            type="button"
                            class="hidden min-h-10 w-48 items-center gap-2 rounded-xl border border-border bg-inputBg px-3 text-sm text-secondary transition hover:border-air-blue/60 hover:text-primary focus-visible:ring-2 focus-visible:ring-air-blue sm:flex lg:w-72"
                            :aria-label="t('search.open_command')"
                            aria-haspopup="dialog"
                            :aria-expanded="searchOpen"
                            @click="openSearch"
                        >
                            <i class="las la-search text-lg" aria-hidden="true"></i>
                            <span class="min-w-0 flex-1 truncate text-start">{{ t('search.short') }}</span>
                            <kbd class="rounded-md border border-border bg-card px-1.5 py-0.5 text-[10px] font-bold text-secondary">{{ t('search.shortcut') }}</kbd>
                        </button>

                        <!-- Chats -->
                        <Link href="/conversations" class="relative rounded-lg p-2 hover:bg-muted" :aria-label="t('Chats öffnen')">
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
                                :aria-label="t('Benachrichtigungen öffnen')"
                                :aria-expanded="notificationOpen"
                                aria-haspopup="dialog"
                                aria-controls="notification-popover"
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
                                id="notification-popover"
                                class="fixed left-3 right-3 top-16 z-50 mt-0 max-h-[calc(100dvh-5rem)] overflow-hidden rounded-2xl border border-border bg-card shadow-xl sm:absolute sm:left-auto sm:right-0 sm:top-full sm:mt-2 sm:max-h-none sm:w-[min(22rem,calc(100vw-1.5rem))] sm:rounded-xl"
                                role="dialog"
                                aria-modal="false"
                                aria-labelledby="notification-popover-title"
                            >
                                <div class="flex items-center justify-between border-b border-border px-4 py-3">
                                    <div>
                                        <p id="notification-popover-title" class="text-sm font-semibold text-primary">{{ t('Benachrichtigungen') }}</p>
                                        <p class="text-xs text-secondary">
                                            {{ unreadCount ? t('notifications.unread_count', { count: unreadCount }) : t('Alles gelesen') }}
                                        </p>
                                    </div>

                                    <Link
                                        :href="route('auth.notifications.index')"
                                        class="rounded-lg px-2 py-1 text-xs font-semibold text-secondary hover:bg-muted hover:text-primary"
                                        @click="closeNotifications"
                                    >
                                        {{ t('Alle') }}
                                    </Link>
                                </div>

                                <div v-if="latestNotifications.length" class="max-h-[calc(100dvh-10rem)] overflow-y-auto sm:max-h-96">
                                    <component
                                        v-for="notification in latestNotifications"
                                        :key="notification.id"
                                        :is="notification.data?.url ? 'a' : 'button'"
                                        :href="notification.data?.url || undefined"
                                        type="button"
                                        class="flex w-full gap-3 border-b border-border px-3 py-3 text-left last:border-b-0 hover:bg-muted/60 sm:px-4"
                                        :class="[
                                            notification.read ? 'opacity-75' : '',
                                            notification.data?.url ? 'cursor-pointer' : 'cursor-default',
                                        ]"
                                        @click="notification.data?.url ? openNotification(notification) : markAsRead(notification)"
                                    >
                                        <div
                                            class="hidden h-10 w-10 shrink-0 items-center justify-center rounded-lg min-[380px]:flex"
                                            :class="notification.read ? 'bg-inputBg text-secondary' : 'bg-buttonPrimary text-buttonTextPrimary'"
                                        >
                                            <i :class="[iconFor(notification.type), 'text-lg']"></i>
                                        </div>

                                        <div class="min-w-0 flex-1">
                                            <p class="line-clamp-2 text-sm font-semibold text-primary sm:truncate">
                                                {{ notification.data?.title || t('Neue Benachrichtigung') }}
                                            </p>
                                            <p v-if="notification.data?.body" class="mt-0.5 line-clamp-2 text-xs text-secondary">
                                                {{ notification.data.body }}
                                            </p>
                                            <p class="mt-1 text-[11px] text-secondary">
                                                {{ formatNotificationDate(notification.created_at) }}
                                            </p>
                                        </div>

                                        <span
                                            v-if="notification.data?.url"
                                            class="flex h-9 w-9 shrink-0 items-center justify-center self-center rounded-full border border-border text-secondary"
                                        >
                                            <i class="las la-angle-right text-base"></i>
                                        </span>

                                        <span
                                            v-else-if="!notification.read"
                                            class="shrink-0 self-center rounded-full border border-border px-2.5 py-1 text-[11px] font-bold text-secondary"
                                        >
                                            {{ t('Neu') }}
                                        </span>
                                    </component>
                                </div>

                                <div v-else class="px-4 py-8 text-center">
                                    <div class="mx-auto flex h-11 w-11 items-center justify-center rounded-lg bg-muted text-secondary">
                                        <i class="las la-bell-slash text-xl"></i>
                                    </div>
                                    <p class="mt-3 text-sm font-semibold text-primary">{{ t('Keine Benachrichtigungen') }}</p>
                                    <p class="mt-1 text-xs text-secondary">{{ t('layout.notifications_empty_hint') }}</p>
                                </div>
                            </div>
                        </div>

                        <!-- User -->
                        <div>
                            <UserCard />
                        </div>

                    </div>
                </div>
            </header>

            <AppMobileSearchOverlay
                v-model:search-term="searchTerm"
                :open="searchOpen"
                :search-error="searchError"
                :search-loading="searchLoading"
                :search-results="searchResults"
                @close="closeSearch"
                @request-join="requestJoin"
                @retry="runSearch"
            />

            <!-- Content -->
            <main
                id="main-content"
                class="min-w-0 flex-1 overflow-x-hidden p-3 pb-24 sm:p-4 lg:p-6"
                tabindex="-1"
                :aria-label="translatedPageTitle"
            >
                <slot />
            </main>
        </div>

        <AppMobileBottomNav v-if="showMobileBottomNav" />
    </div>
</template>

<style scoped>
.airmius-feedback-enter-active,
.airmius-feedback-leave-active {
    transition: opacity 160ms ease, transform 160ms ease;
}

.airmius-feedback-enter-from,
.airmius-feedback-leave-to {
    opacity: 0;
    transform: translateY(12px);
}
</style>
