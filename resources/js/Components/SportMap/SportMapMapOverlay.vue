<script setup>
defineProps({
    activeMapInteractionHint: {
        type: String,
        default: '',
    },
    currentLocationPoint: {
        type: Object,
        default: null,
    },
    mapAttribution: {
        type: String,
        default: '',
    },
    mapPoints: {
        type: Array,
        default: () => [],
    },
    markerStyle: {
        type: Function,
        required: true,
    },
    routePlaybackMarker: {
        type: Object,
        default: null,
    },
    routePlaybackState: {
        type: String,
        default: 'idle',
    },
    routePolyline: {
        type: String,
        default: '',
    },
    trackPolyline: {
        type: String,
        default: '',
    },
})

defineEmits(['remove-point'])
</script>

<template>
    <div class="pointer-events-none absolute right-3 top-3 z-20 max-w-xs rounded-lg border border-border bg-card/95 px-3 py-2 text-xs font-semibold text-secondary shadow-sm">
        {{ activeMapInteractionHint }}
    </div>

    <svg class="absolute inset-0 z-20 h-full w-full" viewBox="0 0 100 100" preserveAspectRatio="none">
        <polyline
            v-if="routePolyline"
            :points="routePolyline"
            fill="none"
            stroke="rgba(255,255,255,.92)"
            stroke-width="2.4"
            stroke-linecap="round"
            stroke-linejoin="round"
        />
        <polyline
            v-if="routePolyline"
            :points="routePolyline"
            fill="none"
            stroke="rgb(59,130,246)"
            stroke-width="1.3"
            stroke-linecap="round"
            stroke-linejoin="round"
        />
        <polyline
            v-if="trackPolyline"
            :points="trackPolyline"
            fill="none"
            stroke="rgb(34,197,94)"
            stroke-width="1.1"
            stroke-dasharray="2 1"
            stroke-linecap="round"
            stroke-linejoin="round"
        />
    </svg>

    <div
        v-if="currentLocationPoint"
        class="absolute z-40 flex h-9 w-9 -translate-x-1/2 -translate-y-1/2 items-center justify-center rounded-full border-2 border-white bg-air-blue text-white shadow-lg"
        :style="markerStyle(currentLocationPoint)"
        :title="currentLocationPoint.name"
        @pointerdown.stop
    >
        <span class="absolute h-12 w-12 rounded-full bg-air-blue/25"></span>
        <i class="las la-location-arrow relative text-lg"></i>
    </div>

    <div
        v-if="routePlaybackMarker && routePlaybackState !== 'idle'"
        class="absolute z-[55] flex h-10 w-10 -translate-x-1/2 -translate-y-1/2 items-center justify-center rounded-full border-2 border-white bg-orange-400 text-slate-950 shadow-xl"
        :style="markerStyle(routePlaybackMarker)"
        :title="$t('sport_map.playback.title')"
        @pointerdown.stop
    >
        <span class="absolute h-14 w-14 rounded-full bg-orange-400/25"></span>
        <i class="las la-running relative text-xl"></i>
    </div>

    <div
        v-for="(point, index) in mapPoints"
        :key="`${point.kind}-${index}-${point.latitude}-${point.longitude}`"
        class="absolute z-30 flex h-8 w-8 -translate-x-1/2 -translate-y-1/2 items-center justify-center rounded-full border-2 border-card text-xs font-bold shadow"
        :class="{
            'bg-blue-500 text-white': point.kind === 'route',
            'bg-emerald-500 text-white': point.kind === 'track',
            'bg-amber-400 text-slate-950': point.kind === 'place',
        }"
        :style="markerStyle(point)"
        :title="point.name"
        @pointerdown.stop
    >
        <button
            v-if="point.removable"
            type="button"
            class="absolute -right-2 -top-2 flex h-5 w-5 items-center justify-center rounded-full border border-card bg-red-500 text-[10px] leading-none text-white shadow hover:bg-red-600"
            :aria-label="$t('sport_map.map_status.remove_point')"
            @click.stop="$emit('remove-point', point)"
            @pointerdown.stop
        >
            <i class="las la-times"></i>
        </button>
        <i v-if="point.kind === 'place'" class="las la-map-marker-alt"></i>
        <span v-else>{{ index + 1 }}</span>
    </div>

    <div v-if="!mapPoints.length" class="absolute bottom-10 left-4 z-30 max-w-xs rounded-lg border border-border bg-card/95 px-3 py-2 text-xs font-semibold text-secondary shadow-sm">
        {{ $t('sport_map.map_status.empty_overlay') }}
    </div>

    <span class="absolute bottom-2 right-2 z-30 rounded bg-card/90 px-2 py-1 text-[10px] font-semibold text-secondary shadow-sm">
        {{ mapAttribution }}
    </span>
</template>
