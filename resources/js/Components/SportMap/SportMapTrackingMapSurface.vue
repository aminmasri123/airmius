<script setup>
defineProps({
    currentLocationPoint: {
        type: Object,
        default: null,
    },
    locationButtonClass: {
        type: [String, Array, Object],
        default: 'absolute left-4 top-4 z-[80] inline-flex h-12 w-12 items-center justify-center rounded-full border border-white/70 bg-white/95 text-blue-600 shadow-lg',
    },
    locationIconClass: {
        type: String,
        default: 'text-xl',
    },
    mapOverlayTiles: {
        type: Array,
        default: () => [],
    },
    mapTiles: {
        type: Array,
        default: () => [],
    },
    markerClass: {
        type: [String, Array, Object],
        default: 'absolute z-40 flex h-8 w-8 -translate-x-1/2 -translate-y-1/2 items-center justify-center rounded-full border-2 border-white bg-air-blue text-white shadow-lg',
    },
    markerPulseClass: {
        type: String,
        default: 'absolute h-11 w-11 rounded-full bg-air-blue/25',
    },
    markerStyle: {
        type: Function,
        required: true,
    },
    routePolyline: {
        type: String,
        default: '',
    },
    surfaceClass: {
        type: [String, Array, Object],
        default: 'relative min-h-0 flex-1 select-none overflow-hidden',
    },
    tileKeyPrefix: {
        type: String,
        default: 'tracking-map',
    },
    trackPolyline: {
        type: String,
        default: '',
    },
})

defineEmits(['click-map', 'show-location'])
</script>

<template>
    <div
        :class="surfaceClass"
        style="background-color: #efe6d1; user-select: none; -webkit-user-select: none;"
        @click="$emit('click-map', $event)"
        @selectstart.prevent
        @dragstart.prevent
    >
        <div class="absolute inset-0 z-0" style="background-color: #efe6d1;"></div>
        <div class="pointer-events-none absolute inset-0 z-0 opacity-80" style="background-image: linear-gradient(90deg, rgba(120,113,108,.16) 1px, transparent 1px), linear-gradient(0deg, rgba(120,113,108,.14) 1px, transparent 1px); background-size: 56px 56px;"></div>

        <img
            v-for="tile in mapTiles"
            :key="`${tileKeyPrefix}-${tile.key}`"
            :src="tile.url"
            :style="tile.style"
            class="pointer-events-none absolute z-[6] max-w-none select-none"
            alt=""
            aria-hidden="true"
            decoding="async"
            loading="eager"
            draggable="false"
            @dragstart.prevent
        >
        <img
            v-for="tile in mapOverlayTiles"
            :key="`${tileKeyPrefix}-overlay-${tile.key}`"
            :src="tile.url"
            :style="tile.style"
            class="pointer-events-none absolute z-[7] max-w-none select-none"
            alt=""
            aria-hidden="true"
            decoding="async"
            loading="eager"
            draggable="false"
            @dragstart.prevent
        >

        <svg class="absolute inset-0 z-20 h-full w-full" viewBox="0 0 100 100" preserveAspectRatio="none">
            <polyline v-if="routePolyline" :points="routePolyline" fill="none" stroke="rgba(255,255,255,.92)" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round" />
            <polyline v-if="routePolyline" :points="routePolyline" fill="none" stroke="rgb(59,130,246)" stroke-width="1.3" stroke-linecap="round" stroke-linejoin="round" />
            <polyline v-if="trackPolyline" :points="trackPolyline" fill="none" stroke="rgb(34,197,94)" stroke-width="1.1" stroke-dasharray="2 1" stroke-linecap="round" stroke-linejoin="round" />
        </svg>

        <div
            v-if="currentLocationPoint"
            :class="markerClass"
            :style="markerStyle(currentLocationPoint)"
        >
            <span :class="markerPulseClass"></span>
            <i :class="['las la-location-arrow relative', locationIconClass]"></i>
        </div>
        <button
            type="button"
            :class="locationButtonClass"
            :aria-label="$t('sport_map.map_status.show_location')"
            @click.stop.prevent="$emit('show-location')"
        >
            <i :class="['las la-location-arrow', locationIconClass]"></i>
        </button>
    </div>
</template>
