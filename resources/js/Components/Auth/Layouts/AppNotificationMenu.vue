<script setup>
import { Link } from '@inertiajs/vue3'
import { ref } from 'vue'
import { useI18n } from 'vue-i18n'

defineProps({
    open: { type: Boolean, default: false },
    unreadCount: { type: Number, default: 0 },
    latestNotifications: { type: Array, default: () => [] },
    iconFor: { type: Function, required: true },
    formatNotificationDate: { type: Function, required: true },
})

const emit = defineEmits(['toggle', 'close', 'open-notification', 'mark-read'])
const notificationBox = ref(null)
const { t } = useI18n()

defineExpose({
    contains: (target) => notificationBox.value?.contains(target) || false,
})
</script>

<template>
    <div ref="notificationBox" class="relative">
        <button
            type="button"
            class="relative rounded-lg p-2 hover:bg-muted"
            :class="{ 'bg-muted': open }"
            @click="emit('toggle')"
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
            v-if="open"
            class="fixed left-3 right-3 top-16 z-50 mt-0 max-h-[calc(100dvh-5rem)] overflow-hidden rounded-2xl border border-border bg-card shadow-xl sm:absolute sm:left-auto sm:right-0 sm:top-full sm:mt-2 sm:max-h-none sm:w-[min(22rem,calc(100vw-1.5rem))] sm:rounded-xl"
        >
            <div class="flex items-center justify-between border-b border-border px-4 py-3">
                <div>
                    <p class="text-sm font-semibold text-primary">{{ t('Benachrichtigungen') }}</p>
                    <p class="text-xs text-secondary">
                        {{ unreadCount ? t('notifications.unread_count', { count: unreadCount }) : t('Alles gelesen') }}
                    </p>
                </div>

                <Link
                    :href="route('auth.notifications.index')"
                    class="rounded-lg px-2 py-1 text-xs font-semibold text-secondary hover:bg-muted hover:text-primary"
                    @click="emit('close')"
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
                    @click="notification.data?.url ? emit('open-notification', notification) : emit('mark-read', notification)"
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
</template>
