<script setup>
import AppLayout from '@/Components/Auth/Layouts/AppLayout.vue'
import { Head, Link, router } from '@inertiajs/vue3'
import { onMounted, onUnmounted } from 'vue'

defineOptions({ layout: AppLayout })

const props = defineProps({
    notifications: {
        type: Object,
        default: () => ({ data: [], links: [] }),
    },
})

let notificationsInterval = null

const formatDate = (value) => new Intl.DateTimeFormat('de-DE', {
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
}[type] || 'las la-bell')

const markAsRead = (notification) => {
    if (notification.read) return

    router.post(route('auth.notifications.read', notification.id), {}, {
        preserveScroll: true,
    })
}

const markAllAsRead = () => {
    router.post(route('auth.notifications.read-all'), {}, {
        preserveScroll: true,
    })
}

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
    <Head title="Benachrichtigungen" />

    <div class="mx-auto max-w-4xl space-y-6">
        <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
            <div>
                <h1 class="text-2xl font-bold text-primary">Benachrichtigungen</h1>
                <p class="mt-1 text-sm text-secondary">
                    Alles Wichtige aus Chat, Feed und Einladungen an einem Ort.
                </p>
            </div>

            <button
                type="button"
                class="inline-flex items-center justify-center gap-2 rounded-lg border border-border bg-card px-4 py-2 text-sm font-semibold text-primary transition hover:border-borderHover"
                @click="markAllAsRead"
            >
                <i class="las la-check-double"></i>
                Alle gelesen
            </button>
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
                                    {{ notification.data?.title || 'Neue Benachrichtigung' }}
                                </h2>
                                <p v-if="notification.data?.body" class="mt-1 text-sm text-secondary">
                                    {{ notification.data.body }}
                                </p>
                                <p class="mt-2 text-xs text-secondary">
                                    {{ formatDate(notification.created_at) }}
                                </p>
                            </div>

                            <div class="flex shrink-0 gap-2">
                                <Link
                                    v-if="notification.data?.url"
                                    :href="notification.data.url"
                                    class="rounded-lg bg-buttonPrimary px-3 py-2 text-xs font-semibold text-buttonTextPrimary transition hover:bg-buttonPrimaryHover"
                                    @click="markAsRead(notification)"
                                >
                                    Öffnen
                                </Link>

                                <button
                                    v-if="!notification.read"
                                    type="button"
                                    class="rounded-lg border border-border px-3 py-2 text-xs font-semibold text-primary transition hover:border-borderHover"
                                    @click="markAsRead(notification)"
                                >
                                    Gelesen
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
                <h2 class="mt-4 text-lg font-semibold text-primary">Noch keine Benachrichtigungen</h2>
                <p class="mt-1 text-sm text-secondary">Sobald etwas passiert, landet es hier.</p>
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
