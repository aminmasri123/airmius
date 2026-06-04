<script setup>
import SportMapMapPanel from '@/Components/SportMap/SportMapMapPanel.vue'
import SportMapMapSidebar from '@/Components/SportMap/SportMapMapSidebar.vue'
import { computed } from 'vue'

const props = defineProps({
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
        required: true,
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
    routes: {
        type: Array,
        default: () => [],
    },
    selectedRouteId: {
        type: [Number, String, null],
        default: null,
    },
    sportLabel: {
        type: Function,
        required: true,
    },
    tabLabel: {
        type: Function,
        required: true,
    },
    tabs: {
        type: Array,
        default: () => [],
    },
    trackPolyline: {
        type: String,
        default: '',
    },
})

const emit = defineEmits([
    'center-map',
    'click-map',
    'drag-end',
    'drag-move',
    'drag-start',
    'remove-last-point',
    'remove-point',
    'reset-map-points',
    'reset-playback',
    'select-tab',
    'show-location',
    'tile-error',
    'tile-load',
    'toggle-playback',
    'update:activeMapLayer',
    'update:playbackSpeed',
    'update:selectedRouteId',
    'zoom',
])

const activeMapLayerModel = computed({
    get: () => props.activeMapLayer,
    set: (value) => emit('update:activeMapLayer', value),
})

const playbackSpeedModel = computed({
    get: () => props.playbackSpeed,
    set: (value) => emit('update:playbackSpeed', value),
})

const selectedRouteIdModel = computed({
    get: () => props.selectedRouteId,
    set: (value) => emit('update:selectedRouteId', value),
})
</script>

<template>
    <section class="grid gap-5 xl:grid-cols-[minmax(0,1.25fr),minmax(320px,0.75fr)]">
        <SportMapMapPanel
            v-model:active-map-layer="activeMapLayerModel"
            v-model:playback-speed="playbackSpeedModel"
            :active-map-interaction-hint="activeMapInteractionHint"
            :active-tab="activeTab"
            :current-location-point="currentLocationPoint"
            :fallback-map-label-style="fallbackMapLabelStyle"
            :fallback-map-labels="fallbackMapLabels"
            :format-distance="formatDistance"
            :format-duration="formatDuration"
            :map-attribution="mapAttribution"
            :map-has-real-tiles="mapHasRealTiles"
            :map-is-dragging="mapIsDragging"
            :map-layer-options="mapLayerOptions"
            :map-overlay-tiles="mapOverlayTiles"
            :map-points="mapPoints"
            :map-status-text="mapStatusText"
            :map-tiles="mapTiles"
            :marker-style="markerStyle"
            :playback-can-start="playbackCanStart"
            :playback-elapsed-seconds="playbackElapsedSeconds"
            :playback-progress-meters="playbackProgressMeters"
            :playback-progress-percent="playbackProgressPercent"
            :playback-remaining-seconds="playbackRemainingSeconds"
            :playback-speed-options="playbackSpeedOptions"
            :playback-state="playbackState"
            :playback-status="playbackStatus"
            :playback-total-distance="playbackTotalDistance"
            :route-playback-marker="routePlaybackMarker"
            :route-polyline="routePolyline"
            :track-polyline="trackPolyline"
            @center-map="$emit('center-map')"
            @click-map="$emit('click-map', $event)"
            @drag-end="$emit('drag-end', $event)"
            @drag-move="$emit('drag-move', $event)"
            @drag-start="$emit('drag-start', $event)"
            @remove-last-point="$emit('remove-last-point')"
            @remove-point="$emit('remove-point', $event)"
            @reset-map-points="$emit('reset-map-points')"
            @reset-playback="$emit('reset-playback')"
            @show-location="$emit('show-location')"
            @tile-error="$emit('tile-error', $event)"
            @tile-load="$emit('tile-load', $event)"
            @toggle-playback="$emit('toggle-playback')"
            @zoom="$emit('zoom', $event)"
        />

        <SportMapMapSidebar
            v-model:selected-route-id="selectedRouteIdModel"
            :active-tab="activeTab"
            :format-distance="formatDistance"
            :format-duration="formatDuration"
            :routes="routes"
            :sport-label="sportLabel"
            :tab-label="tabLabel"
            :tabs="tabs"
            @select-tab="$emit('select-tab', $event)"
        />
    </section>
</template>
