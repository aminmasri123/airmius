<script setup>
import SportMapTrackPreparationPanel from '@/Components/SportMap/SportMapTrackPreparationPanel.vue'
import SportMapTrackRecorderPanel from '@/Components/SportMap/SportMapTrackRecorderPanel.vue'
import SportMapTracksHistoryPanel from '@/Components/SportMap/SportMapTracksHistoryPanel.vue'

defineProps({
    activeSlide: {
        type: Number,
        default: 0,
    },
    catalogLabel: {
        type: Function,
        required: true,
    },
    currentLocationPoint: {
        type: Object,
        default: null,
    },
    desktopMetrics: {
        type: Array,
        default: () => [],
    },
    editForm: {
        type: Object,
        required: true,
    },
    editingTrackId: {
        type: [Number, String, null],
        default: null,
    },
    elapsedLabel: {
        type: String,
        default: '',
    },
    error: {
        type: String,
        default: '',
    },
    form: {
        type: Object,
        required: true,
    },
    formatDistance: {
        type: Function,
        required: true,
    },
    formatDuration: {
        type: Function,
        required: true,
    },
    gpxImportForm: {
        type: Object,
        required: true,
    },
    hasPoints: {
        type: Boolean,
        default: false,
    },
    isFullscreen: {
        type: Boolean,
        default: false,
    },
    isProcessing: {
        type: Boolean,
        default: false,
    },
    isTracking: {
        type: Boolean,
        default: false,
    },
    liveStatusLabel: {
        type: String,
        default: '',
    },
    mapOverlayTiles: {
        type: Array,
        default: () => [],
    },
    mapTiles: {
        type: Array,
        default: () => [],
    },
    markerStyle: {
        type: Function,
        required: true,
    },
    mobileMetrics: {
        type: Array,
        default: () => [],
    },
    routePolyline: {
        type: String,
        default: '',
    },
    routes: {
        type: Array,
        default: () => [],
    },
    sportLabel: {
        type: Function,
        required: true,
    },
    sportTypes: {
        type: Array,
        default: () => [],
    },
    startLabel: {
        type: String,
        required: true,
    },
    startLongLabel: {
        type: String,
        required: true,
    },
    trackAveragePaceLabel: {
        type: Function,
        required: true,
    },
    trackAverageSpeedLabel: {
        type: Function,
        required: true,
    },
    trackCaloriesLabel: {
        type: Function,
        required: true,
    },
    trackPolyline: {
        type: String,
        default: '',
    },
    trackingDistance: {
        type: Number,
        default: 0,
    },
    tracks: {
        type: Array,
        default: () => [],
    },
    trackStatusLabel: {
        type: Function,
        required: true,
    },
})

defineEmits([
    'cancel-edit',
    'click-map',
    'close-fullscreen',
    'delete-current-draft',
    'edit',
    'import-gpx',
    'next-slide',
    'pause',
    'pointer-cancel-swipe',
    'pointer-down-swipe',
    'pointer-up-swipe',
    'previous-slide',
    'reset-points',
    'save',
    'select-gpx-file',
    'select-slide',
    'show-location',
    'start',
    'start-mobile',
    'touch-end-swipe',
    'touch-start-swipe',
    'update',
])
</script>

<template>
    <div class="grid gap-4 p-3 sm:p-4 lg:grid-cols-[minmax(0,0.95fr),minmax(0,1.05fr)]">
        <div class="space-y-4">
            <SportMapTrackRecorderPanel
                :active-slide="activeSlide"
                :current-location-point="currentLocationPoint"
                :desktop-metrics="desktopMetrics"
                :elapsed-label="elapsedLabel"
                :error="error"
                :format-distance="formatDistance"
                :has-points="hasPoints"
                :is-fullscreen="isFullscreen"
                :is-processing="isProcessing"
                :is-tracking="isTracking"
                :live-status-label="liveStatusLabel"
                :map-overlay-tiles="mapOverlayTiles"
                :map-tiles="mapTiles"
                :marker-style="markerStyle"
                :mobile-metrics="mobileMetrics"
                :route-polyline="routePolyline"
                :start-label="startLabel"
                :start-long-label="startLongLabel"
                :track-polyline="trackPolyline"
                :tracking-distance="trackingDistance"
                @click-map="$emit('click-map', $event)"
                @close-fullscreen="$emit('close-fullscreen')"
                @delete-current-draft="$emit('delete-current-draft')"
                @next-slide="$emit('next-slide')"
                @pause="$emit('pause')"
                @pointer-cancel-swipe="$emit('pointer-cancel-swipe')"
                @pointer-down-swipe="$emit('pointer-down-swipe', $event)"
                @pointer-up-swipe="$emit('pointer-up-swipe', $event)"
                @previous-slide="$emit('previous-slide')"
                @reset-points="$emit('reset-points')"
                @save="$emit('save')"
                @select-slide="$emit('select-slide', $event)"
                @show-location="$emit('show-location')"
                @start="$emit('start')"
                @start-mobile="$emit('start-mobile')"
                @touch-end-swipe="$emit('touch-end-swipe', $event)"
                @touch-start-swipe="$emit('touch-start-swipe', $event)"
            />

            <SportMapTrackPreparationPanel
                :catalog-label="catalogLabel"
                :form="form"
                :routes="routes"
                :sport-types="sportTypes"
            />
        </div>

        <SportMapTracksHistoryPanel
            :catalog-label="catalogLabel"
            :edit-form="editForm"
            :editing-track-id="editingTrackId"
            :format-distance="formatDistance"
            :format-duration="formatDuration"
            :gpx-import-form="gpxImportForm"
            :sport-label="sportLabel"
            :sport-types="sportTypes"
            :track-average-pace-label="trackAveragePaceLabel"
            :track-average-speed-label="trackAverageSpeedLabel"
            :track-calories-label="trackCaloriesLabel"
            :tracks="tracks"
            :track-status-label="trackStatusLabel"
            @cancel-edit="$emit('cancel-edit')"
            @edit="$emit('edit', $event)"
            @import-gpx="$emit('import-gpx')"
            @select-gpx-file="$emit('select-gpx-file', $event)"
            @update="$emit('update', $event)"
        />
    </div>
</template>
