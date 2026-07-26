<script setup>
import AppLayout from '@/Components/Auth/Layouts/AppLayout.vue'
import { Head, Link, router } from '@inertiajs/vue3'
import { onMounted, onUnmounted } from 'vue'
import { useI18n } from 'vue-i18n'

defineOptions({ layout: AppLayout })

const props = defineProps({
    notifications: {
        type: Object,
        default: () => ({ data: [], links: [] }),
    },
})

const { t, locale } = useI18n()
const tx = (value, params = {}) => t(value, params)

let notificationsInterval = null

const formatDate = (value) => new Intl.DateTimeFormat(locale.value === 'ar' ? 'ar' : (locale.value === 'fr' ? 'fr-FR' : (locale.value === 'en' ? 'en-US' : 'de-DE')), {
    day: '2-digit',
    month: '2-digit',
    year: 'numeric',
    hour: '2-digit',
    minute: '2-digit',
}).format(new Date(value))

const iconFor = (type) => ({
    'chat.message': 'las la-comment-dots',
    'post.comment': 'las la-comments',
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
    'training.log.saved': 'las la-running',
    'training.feedback': 'las la-clipboard-check',
    'training.plan.changed': 'las la-calendar-check',
    'user.followed': 'las la-user-plus',
    'commerce.order.created': 'las la-shopping-bag',
    'commerce.order.issue_reported': 'las la-exclamation-circle',
    'commerce.order.issue_replied': 'las la-comments',
    'commerce.order.paid': 'las la-receipt',
    'commerce.order.shipping_updated': 'las la-shipping-fast',
    'invoice.created': 'las la-file-invoice',
    'invoice.status_updated': 'las la-file-invoice-dollar',
}[type] || 'las la-bell')

const markAsRead = (notification) => {
    if (notification.read) return

    router.post(route('auth.notifications.read', notification.id), {}, {
        preserveScroll: true,
    })
}

const markAsUnread = (notification) => {
    if (!notification.read) return

    router.post(route('auth.notifications.unread', notification.id), {}, {
        preserveScroll: true,
    })
}

const deleteNotification = (notification) => {
    router.delete(route('auth.notifications.destroy', notification.id), {
        preserveScroll: true,
        only: ['notifications', 'notificationCenter', 'auth', 'flash'],
    })
}

const canObjectToRemoval = (notification) => (
    ['club.member_removed', 'team.member_removed'].includes(notification.type)
    && notification.data?.club_id
)

const objectToRemoval = (notification) => {
    router.post(route('auth.club-memberships.removal-objection', notification.data.club_id), {
        message: tx('notifications.removal_objection_message'),
    }, {
        preserveScroll: true,
        only: ['notifications', 'notificationCenter', 'auth', 'flash'],
        onSuccess: () => markAsRead(notification),
    })
}

const notificationTitle = (notification) => notification.title || notification.data?.title || tx('Neue Benachrichtigung')
const notificationBody = (notification) => notification.body || notification.data?.body || notification.data?.message || null
const notificationActionUrl = (notification) => notification.action_url || notification.url || notification.data?.action_url || notification.data?.url || null

const visitPage = (url) => {
    if (!url) return

    router.visit(url, {
        preserveScroll: true,
        preserveState: true,
        only: ['notifications', 'auth'],
    })
}

const refreshNotifications = () => {
    if (document.hidden) return

    router.reload({
        only: ['notifications', 'notificationCenter', 'auth'],
        preserveScroll: true,
        preserveState: true,
    })
}

onMounted(() => {
    notificationsInterval = window.setInterval(refreshNotifications, 5000)
})

onUnmounted(() => {
    if (notificationsInterval) {
        window.clearInterval(notificationsInterval)
    }
})
</script>

<template>
    <Head :title="tx('Benachrichtigungen')" />

    <div class="mx-auto max-w-4xl space-y-6">
        <div>
            <div>
                <h1 class="text-2xl font-bold text-primary">{{ tx('Benachrichtigungen') }}</h1>
                <p class="mt-1 text-sm text-secondary">{{ tx('Alles Wichtige aus Chat, Feed und Einladungen an einem Ort.') }}</p>
            </div>
        </div>

        <div class="surface-card overflow-hidden">
            <div v-if="notifications.data.length" class="divide-y divide-border">
                <div
                    v-for="notification in notifications.data"
                    :key="notification.id"
                    class="flex gap-4 p-4 transition hover:bg-muted/60"
                    :class="notification.read ? 'opacity-75' : ''"
                >
                    <div
                        class="flex h-11 w-11 shrink-0 items-center justify-center rounded-lg"
                        :class="notification.read ? 'bg-inputBg text-secondary' : 'bg-buttonPrimary text-buttonTextPrimary'"
                    >
                        <i :class="[iconFor(notification.type), 'text-xl']"></i>
                    </div>

                    <div class="min-w-0 flex-1">
                        <div class="flex flex-col gap-2 sm:flex-row sm:items-start sm:justify-between">
                            <div class="min-w-0">
                                <h2 class="text-sm font-semibold text-primary">
                                    {{ notificationTitle(notification) }}
                                </h2>
                                <p v-if="notificationBody(notification)" class="mt-1 text-sm text-secondary">
                                    {{ notificationBody(notification) }}
                                </p>
                                <p class="mt-2 text-xs text-secondary">
                                    {{ formatDate(notification.created_at) }}
                                </p>
                            </div>

                            <div class="flex shrink-0 gap-2">
                                <a
                                    v-if="notificationActionUrl(notification)"
                                    :href="notificationActionUrl(notification)"
                                    class="rounded-lg bg-buttonPrimary px-3 py-2 text-xs font-semibold text-buttonTextPrimary transition hover:bg-buttonPrimaryHover"
                                    @click="markAsRead(notification)"
                                >
                                    {{ tx('Öffnen') }}
                                </a>

                                <button
                                    v-if="canObjectToRemoval(notification)"
                                    type="button"
                                    class="rounded-lg border border-warning/40 px-3 py-2 text-xs font-semibold text-warning transition hover:bg-warning/10"
                                    @click="objectToRemoval(notification)"
                                >
                                    {{ tx('Widersprechen') }}
                                </button>

                                <button
                                    type="button"
                                    class="rounded-lg border border-border px-3 py-2 text-xs font-semibold text-primary transition hover:border-borderHover"
                                    @click="notification.read ? markAsUnread(notification) : markAsRead(notification)"
                                >
                                    {{ notification.read ? tx('notifications.unread') : tx('notifications.read') }}
                                </button>

                                <button
                                    type="button"
                                    class="inline-flex h-9 w-9 items-center justify-center rounded-lg border border-border text-secondary transition hover:border-error/40 hover:bg-error/10 hover:text-error"
                                    :aria-label="`${tx('notifications.label')} ${notificationTitle(notification)} ${tx('löschen')}`"
                                    :title="tx('Benachrichtigung löschen')"
                                    @click="deleteNotification(notification)"
                                >
                                    <i class="las la-times text-lg"></i>
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div v-else class="p-10 text-center">
                <div class="mx-auto flex h-14 w-14 items-center justify-center rounded-xl bg-muted text-secondary">
                    <i class="las la-bell-slash text-2xl"></i>
                </div>
                <h2 class="mt-4 text-lg font-semibold text-primary">{{ tx('Noch keine Benachrichtigungen') }}</h2>
                <p class="mt-1 text-sm text-secondary">{{ tx('Sobald etwas passiert, landet es hier.') }}</p>
            </div>
        </div>

        <div v-if="notifications.links?.length > 3" class="flex flex-wrap justify-center gap-1">
            <button
                v-for="link in notifications.links"
                :key="link.label"
                type="button"
                :disabled="!link.url"
                class="min-w-10 rounded border border-border px-3 py-2 text-sm transition disabled:cursor-not-allowed disabled:opacity-50"
                :class="link.active ? 'bg-buttonPrimary text-buttonTextPrimary' : 'bg-card text-primary hover:bg-muted'"
                @click="visitPage(link.url)"
                v-html="link.label"
            />
        </div>
    </div>
</template>
