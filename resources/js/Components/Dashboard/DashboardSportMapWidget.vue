<script setup>
import { Link } from '@inertiajs/vue3'

defineProps({
    sportMap: { type: Object, required: true },
    formatNumber: { type: Function, required: true },
    formatDistance: { type: Function, required: true },
})
</script>

<template>
    <div class="surface-card p-4 sm:p-5">
        <div class="flex items-start justify-between gap-3">
            <div>
                <p class="text-xs font-bold uppercase tracking-wide text-sky-300">{{ $t('Sportkarte') }}</p>
                <h2 class="mt-1 text-lg font-black text-primary">{{ $t('Routen & Orte') }}</h2>
            </div>
            <Link :href="route('auth.sport-map.index')" class="rounded-xl border border-border px-3 py-2 text-xs font-bold text-primary hover:border-air-blue hover:text-air-blue">
                {{ $t('Karte') }}
            </Link>
        </div>

        <div class="mt-4 grid grid-cols-3 gap-2 text-center">
            <div>
                <p class="text-lg font-black text-primary">{{ formatNumber(sportMap.routes_count) }}</p>
                <p class="text-xs text-secondary">{{ $t('Routen') }}</p>
            </div>
            <div>
                <p class="text-lg font-black text-primary">{{ formatNumber(sportMap.tracks_count) }}</p>
                <p class="text-xs text-secondary">{{ $t('Tracks') }}</p>
            </div>
            <div>
                <p class="text-lg font-black text-primary">{{ formatNumber(sportMap.places_count) }}</p>
                <p class="text-xs text-secondary">{{ $t('Plätze') }}</p>
            </div>
        </div>

        <div class="mt-5 rounded-2xl border border-border p-4">
            <p class="text-xs font-bold uppercase tracking-wide text-secondary">{{ $t('Diese Woche getrackt') }}</p>
            <p class="mt-1 text-2xl font-black text-primary">{{ formatDistance(sportMap.week_track_distance_meters) }}</p>
            <p class="mt-2 truncate text-xs text-secondary">
                {{ $t('Letzte Route:') }} {{ sportMap.last_route?.title || $t('Noch keine Route') }}
            </p>
        </div>
    </div>
</template>

