<script setup>
import { Link } from '@inertiajs/vue3'

defineProps({
    notifications: { type: Object, required: true },
    formatNumber: { type: Function, required: true },
    notificationTime: { type: Function, required: true },
    translatedNotificationText: { type: Function, required: true },
})
</script>

<template>
    <div class="surface-card p-4 sm:p-5">
        <div class="flex items-start justify-between gap-3">
            <div>
                <p class="text-xs font-bold uppercase tracking-wide text-amber-300">{{ $t('Inbox') }}</p>
                <h2 class="mt-1 text-lg font-black text-primary">{{ $t('dashboard.unread_items', { count: formatNumber(notifications.unread_count) }) }}</h2>
            </div>
            <Link :href="route('auth.notifications.index')" class="rounded-xl border border-border px-3 py-2 text-xs font-bold text-primary hover:border-air-blue hover:text-air-blue">
                {{ $t('Öffnen') }}
            </Link>
        </div>

        <div v-if="notifications.latest?.length" class="mt-4 divide-y divide-border">
            <Link
                v-for="item in notifications.latest.slice(0, 3)"
                :key="item.id"
                :href="route('auth.notifications.index')"
                class="block py-3"
            >
                <p class="truncate text-sm font-black" :class="item.read ? 'text-secondary' : 'text-primary'">{{ translatedNotificationText(item.title) }}</p>
                <p class="mt-1 truncate text-xs text-secondary">{{ item.body ? translatedNotificationText(item.body) : notificationTime(item.created_at) }}</p>
            </Link>
        </div>
        <div v-else class="mt-5 rounded-2xl border border-dashed border-border p-6 text-center text-sm text-secondary">
            {{ $t('Keine neuen Nachrichten.') }}
        </div>
    </div>
</template>

