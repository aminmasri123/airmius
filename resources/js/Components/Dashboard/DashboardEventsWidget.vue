<script setup>
import { Link } from '@inertiajs/vue3'

defineProps({
    events: { type: Object, required: true },
    formatNumber: { type: Function, required: true },
    formatDateTime: { type: Function, required: true },
})
</script>

<template>
    <div class="surface-card p-4 sm:p-5">
        <div class="flex items-start justify-between gap-3">
            <div>
                <p class="text-xs font-bold uppercase tracking-wide text-emerald-300">{{ $t('Termine') }}</p>
                <h2 class="mt-1 text-lg font-black text-primary">{{ $t('dashboard.planned_count', { count: formatNumber(events.upcoming_count) }) }}</h2>
            </div>
            <Link :href="route('auth.events.index')" class="rounded-xl border border-border px-3 py-2 text-xs font-bold text-primary hover:border-air-blue hover:text-air-blue">
                {{ $t('Kalender') }}
            </Link>
        </div>

        <div v-if="events.next?.length" class="mt-4 divide-y divide-border">
            <Link v-for="event in events.next.slice(0, 3)" :key="event.id" :href="route('auth.events.index')" class="block py-3">
                <p class="truncate text-sm font-black text-primary">{{ event.title }}</p>
                <p class="mt-1 text-xs text-secondary">{{ formatDateTime(event.start_time) }} - {{ event.location || $t('ohne Ort') }}</p>
            </Link>
        </div>
        <div v-else class="mt-5 rounded-2xl border border-dashed border-border p-6 text-center text-sm text-secondary">
            {{ $t('Keine kommenden Termine.') }}
        </div>
    </div>
</template>
