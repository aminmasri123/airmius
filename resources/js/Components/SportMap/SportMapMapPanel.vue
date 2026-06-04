<script setup>
import SportMapBaseLayer from '@/Components/SportMap/SportMapBaseLayer.vue'
import SportMapMapControls from '@/Components/SportMap/SportMapMapControls.vue'
import SportMapMapHeader from '@/Components/SportMap/SportMapMapHeader.vue'
import SportMapMapOverlay from '@/Components/SportMap/SportMapMapOverlay.vue'
import SportMapRoutePlaybackControls from '@/Components/SportMap/SportMapRoutePlaybackControls.vue'

defineProps({
    activeMapInteractionHint: {
        type: String,
        default: '',
    },
    activeMapLayer: {
        type: String,
        required: true,
    },
    activeTab: {
        type: String,
        default: '',
    },
    currentLocationPoint: {
        type: Object,
        default: null,
    },
    fallbackMapLabelStyle: {
        type: Function,
        required: true,
    },
    fallbackMapLabels: {
        type: Array,
        default: () => [],
    },
    formatDistance: {
        type: Function,
        required: true,
    },
    formatDuration: {
        type: Function,
        required: true,
    },
    mapAttribution: {
        type: String,
        default: '',
    },
    mapHasRealTiles: {
        type: Boolean,
        default: false,
    },
    mapIsDragging: {
        type: Boolean,
        default: false,
    },
    mapLayerOptions: {
        type: Array,
        default: () => [],
    },
    mapOverlayTiles: {
        type: Array,
        default: () => [],
    },
    mapPoints: {
        type: Array,
        default: () => [],
    },
    mapStatusText: {
        type: String,
        default: '',
    },
    mapTiles: {
        type: Array,
        default: () => [],
    },
    markerStyle: {
        type: Function,
        required: true,
    },
    playbackCanStart: {
        type: Boolean,
        default: false,
    },
    playbackElapsedSeconds: {
        type: Number,
        default: 0,
    },
    playbackProgressMeters: {
        type: Number,
        default: 0,
    },
    playbackProgressPercent: {
        type: Number,
        default: 0,
    },
    playbackRemainingSeconds: {
        type: Number,
        default: 0,
    },
    playbackSpeed: {
        type: Number,
        default: 1,
    },
    playbackSpeedOptions: {
        type: Array,
        default: () => [],
    },
    playbackState: {
        type: String,
        default: 'idle',
    },
    playbackStatus: {
        type: String,
        default: '',
    },
    playbackTotalDistance: {
        type: Number,
        default: 0,
    },
    routePlaybackMarker: {
        type: Object,
        default: null,
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

defineEmits([
    'center-map',
    'click-map',
    'drag-end',
    'drag-move',
    'drag-start',
    'remove-last-point',
    'remove-point',
    'reset-map-points',
    'reset-playback',
    'show-location',
    'tile-error',
    'tile-load',
    'toggle-playback',
    'update:activeMapLayer',
    'update:playbackSpeed',
    'zoom',
])
</script>

<template>
    <div
        class="overflow-hidden rounded-lg border border-border bg-card shadow-sm"
        :class="activeTab === 'tracks' ? 'hidden lg:block' : ''"
    >
        <SportMapMapHeader
            :active-map-layer="activeMapLayer"
            :layer-options="mapLayerOptions"
            :status-text="mapStatusText"
            @update:active-map-layer="$emit('update:activeMapLayer', $event)"
        />

        <div
            class="relative aspect-[4/3] min-h-[320px] touch-none select-none overflow-hidden sm:aspect-[16/9]"
            :class="mapIsDragging ? 'cursor-grabbing' : 'cursor-crosshair'"
            style="background-color: #efe6d1; user-select: none; -webkit-user-select: none;"
            role="application"
            :aria-label="$t('sport_map.map_status.interactive_sport_map')"
            @pointerdown="$emit('drag-start', $event)"
            @pointermove="$emit('drag-move', $event)"
            @pointerup="$emit('drag-end')"
            @pointercancel="$emit('drag-end')"
            @pointerleave="$emit('drag-end')"
            @click="$emit('click-map', $event)"
            @selectstart.prevent
            @dragstart.prevent
            @wheel.prevent="$emit('zoom', $event.deltaY > 0 ? -1 : 1, $event)"
        >
            <SportMapBaseLayer
                :fallback-map-label-style="fallbackMapLabelStyle"
                :fallback-map-labels="fallbackMapLabels"
                :map-has-real-tiles="mapHasRealTiles"
                :map-overlay-tiles="mapOverlayTiles"
                :map-tiles="mapTiles"
                @tile-error="$emit('tile-error', $event)"
                @tile-load="$emit('tile-load')"
            />

            <SportMapMapControls
                @center="$emit('center-map')"
                @remove-last="$emit('remove-last-point')"
                @reset="$emit('reset-map-points')"
                @show-location="$emit('show-location')"
                @zoom-in="$emit('zoom', 1)"
                @zoom-out="$emit('zoom', -1)"
            />

            <SportMapMapOverlay
                :active-map-interaction-hint="activeMapInteractionHint"
                :current-location-point="currentLocationPoint"
                :map-attribution="mapAttribution"
                :map-points="mapPoints"
                :marker-style="markerStyle"
                :route-playback-marker="routePlaybackMarker"
                :route-playback-state="playbackState"
                :route-polyline="routePolyline"
                :track-polyline="trackPolyline"
                @remove-point="$emit('remove-point', $event)"
            />

            <SportMapRoutePlaybackControls
                :speed="playbackSpeed"
                :can-start="playbackCanStart"
                :elapsed-seconds="playbackElapsedSeconds"
                :format-distance="formatDistance"
                :format-duration="formatDuration"
                :progress-meters="playbackProgressMeters"
                :progress-percent="playbackProgressPercent"
                :remaining-seconds="playbackRemainingSeconds"
                :speed-options="playbackSpeedOptions"
                :state="playbackState"
                :status="playbackStatus"
                :total-distance="playbackTotalDistance"
                @reset="$emit('reset-playback')"
                @toggle="$emit('toggle-playback')"
                @update:speed="$emit('update:playbackSpeed', $event)"
            />
        </div>
    </div>
</template>
