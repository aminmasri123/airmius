<script setup>
import SportMapTrackingActions from '@/Components/SportMap/SportMapTrackingActions.vue'
import SportMapTrackingMapSurface from '@/Components/SportMap/SportMapTrackingMapSurface.vue'
import SportMapTrackingMetricGrid from '@/Components/SportMap/SportMapTrackingMetricGrid.vue'
import SportMapTrackingSlideControls from '@/Components/SportMap/SportMapTrackingSlideControls.vue'

defineProps({
    activeSlide: {
        type: Number,
        default: 0,
    },
    currentLocationPoint: {
        type: Object,
        default: null,
    },
    desktopMetrics: {
        type: Array,
        default: () => [],
    },
    elapsedLabel: {
        type: String,
        default: '',
    },
    error: {
        type: String,
        default: '',
    },
    formatDistance: {
        type: Function,
        required: true,
    },
    hasPoints: {
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
    isFullscreen: {
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
    startLabel: {
        type: String,
        required: true,
    },
    startLongLabel: {
        type: String,
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
})

defineEmits([
    'click-map',
    'close-fullscreen',
    'delete-current-draft',
    'next-slide',
    'pause',
    'pointer-cancel-swipe',
    'pointer-down-swipe',
    'pointer-up-swipe',
    'previous-slide',
    'reset-points',
    'save',
    'select-slide',
    'show-location',
    'start',
    'start-mobile',
    'touch-end-swipe',
    'touch-start-swipe',
])
</script>

<template>
    <section
        class="overflow-hidden rounded-2xl border border-air-blue/30 bg-gradient-to-br from-air-blue/20 via-inputBg to-emerald-500/10 p-3 shadow-sm sm:p-4"
    >
        <div class="flex items-start justify-between gap-3">
            <div>
                <p class="text-xs font-bold uppercase tracking-wide text-air-blue">{{ $t('sport_map.tracks.fullscreen_title') }}</p>
                <h3 class="mt-1 text-lg font-bold text-primary sm:text-xl">{{ $t('sport_map.tracks.record_title') }}</h3>
                <p class="mt-1 hidden text-sm leading-5 text-secondary sm:block">{{ $t('sport_map.tracks.record_hint') }}</p>
            </div>
            <span
                class="shrink-0 rounded-full border px-3 py-1 text-xs font-bold"
                :class="isTracking ? 'border-emerald-400/40 bg-emerald-500/15 text-emerald-300' : 'border-border bg-card text-secondary'"
            >
                {{ liveStatusLabel }}
            </span>
            <button
                v-if="isFullscreen"
                type="button"
                class="inline-flex h-9 w-9 shrink-0 items-center justify-center rounded-full border border-border bg-card text-primary sm:hidden"
                :aria-label="$t('sport_map.tracks.close_tracking')"
                @click="$emit('close-fullscreen')"
            >
                <i class="las la-times text-lg"></i>
            </button>
        </div>

        <div class="mt-4 lg:hidden">
            <div class="overflow-hidden rounded-[1.75rem] border border-white/10 bg-slate-950/60 shadow-2xl">
                <div
                    class="flex touch-pan-y transition-transform duration-300 ease-out"
                    :style="{ transform: `translateX(-${activeSlide * 100}%)` }"
                    @touchstart.passive="$emit('touch-start-swipe', $event)"
                    @touchend="$emit('touch-end-swipe', $event)"
                    @pointerdown="$emit('pointer-down-swipe', $event)"
                    @pointerup="$emit('pointer-up-swipe', $event)"
                    @pointercancel="$emit('pointer-cancel-swipe')"
                >
                    <article class="flex min-h-[380px] min-w-full flex-col justify-between p-5">
                        <div>
                            <div class="flex items-center justify-between gap-3">
                                <span class="rounded-full bg-air-blue/15 px-3 py-1 text-xs font-black uppercase tracking-wide text-air-blue">Airmius Track</span>
                                <span class="rounded-full border border-border bg-card px-3 py-1 text-xs font-bold text-secondary">{{ liveStatusLabel }}</span>
                            </div>
                            <div class="mt-8 text-center">
                                <p class="text-xs font-bold uppercase text-secondary">{{ $t('sport_map.tracks.distance') }}</p>
                                <p class="mt-3 text-6xl font-black leading-none text-primary">{{ formatDistance(trackingDistance) }}</p>
                                <p class="mt-2 text-sm font-bold text-secondary">{{ elapsedLabel }} - {{ liveStatusLabel }}</p>
                            </div>
                        </div>
                        <SportMapTrackingActions
                            :has-points="hasPoints"
                            icon-class="text-xl"
                            :is-processing="isProcessing"
                            :is-tracking="isTracking"
                            :start-label="startLabel"
                            wrapper-class="mt-5 grid gap-2"
                            start-button-class="inline-flex min-h-14 items-center justify-center gap-2 rounded-2xl bg-buttonPrimary px-4 py-3 text-sm font-black text-buttonTextPrimary shadow-sm"
                            pause-button-class="inline-flex min-h-14 items-center justify-center gap-2 rounded-2xl border border-amber-300/40 bg-amber-400/10 px-4 py-3 text-sm font-black text-amber-200"
                            save-button-class="inline-flex min-h-14 items-center justify-center gap-2 rounded-2xl border border-emerald-400/40 bg-emerald-500/15 px-4 py-3 text-sm font-black text-emerald-200 disabled:opacity-50"
                            delete-button-class="col-span-2 inline-flex min-h-11 items-center justify-center gap-2 rounded-2xl border border-red-400/40 bg-red-500/10 px-4 py-2 text-sm font-black text-red-300"
                            :delete-label="$t('sport_map.tracks.delete_draft')"
                            @delete="$emit('delete-current-draft')"
                            @pause="$emit('pause')"
                            @save="$emit('save')"
                            @start="$emit('start-mobile')"
                        />
                        <p class="mt-4 text-center text-xs font-semibold text-secondary">{{ $t('sport_map.tracks.swipe_map_hint') }}</p>
                    </article>

                    <article class="min-h-[380px] min-w-full overflow-hidden bg-card">
                        <div class="flex items-center justify-between gap-3 border-b border-border px-4 py-3">
                            <div>
                                <p class="text-xs font-bold uppercase tracking-wide text-air-blue">{{ $t('sport_map.tracks.slide_number', { number: 2 }) }}</p>
                                <h4 class="text-base font-black text-primary">{{ $t('sport_map.tracks.map_slide') }}</h4>
                            </div>
                            <span class="rounded-full bg-inputBg px-3 py-1 text-xs font-bold text-secondary">{{ $t('sport_map.tracks.map_slide') }}</span>
                        </div>
                        <SportMapTrackingMapSurface
                            :current-location-point="currentLocationPoint"
                            location-button-class="absolute left-3 top-3 z-[80] inline-flex h-11 w-11 items-center justify-center rounded-2xl border border-white/70 bg-white/95 text-blue-600 shadow-lg"
                            location-icon-class="text-lg"
                            :map-overlay-tiles="mapOverlayTiles"
                            :map-tiles="mapTiles"
                            :marker-style="markerStyle"
                            :route-polyline="routePolyline"
                            surface-class="relative h-72 touch-pan-x select-none overflow-hidden"
                            tile-key-prefix="mobile-track"
                            :track-polyline="trackPolyline"
                            @click-map="$emit('click-map', $event)"
                            @show-location="$emit('show-location')"
                        />
                        <p class="px-4 py-3 text-center text-xs font-semibold text-secondary">{{ $t('sport_map.tracks.swipe_metrics_hint') }}</p>
                    </article>

                    <article class="flex min-h-[380px] min-w-full flex-col p-5">
                        <p class="text-xs font-bold uppercase tracking-wide text-air-blue">{{ $t('sport_map.tracks.slide_number', { number: 3 }) }}</p>
                        <h4 class="mt-1 text-lg font-black text-primary">{{ $t('sport_map.tracks.live_values') }}</h4>
                        <SportMapTrackingMetricGrid
                            :metrics="mobileMetrics"
                            grid-class="mt-4 grid grid-cols-2 gap-3"
                            card-class="rounded-2xl border border-border bg-inputBg p-4"
                            label-class="text-xs font-bold uppercase text-secondary"
                            value-class="mt-1 text-2xl font-black text-primary"
                        />
                    </article>
                </div>
            </div>

            <SportMapTrackingSlideControls
                :active-slide="activeSlide"
                @next="$emit('next-slide')"
                @previous="$emit('previous-slide')"
                @select="$emit('select-slide', $event)"
            />
        </div>

        <div class="mt-4 hidden rounded-2xl border border-white/10 bg-card/80 p-4 lg:block">
            <div class="flex items-end justify-between gap-3">
                <div>
                    <p class="text-xs font-bold uppercase tracking-wide text-secondary">{{ $t('sport_map.tracks.distance') }}</p>
                    <p class="mt-2 text-4xl font-black leading-none text-primary">{{ formatDistance(trackingDistance) }}</p>
                </div>
                <p class="pb-1 text-sm font-bold text-secondary">{{ elapsedLabel }}</p>
            </div>
        </div>

        <SportMapTrackingMetricGrid
            :metrics="desktopMetrics"
            grid-class="-mx-3 mt-3 hidden snap-x gap-3 overflow-x-auto px-3 pb-1 sm:mx-0 lg:grid lg:grid-cols-4 lg:overflow-visible lg:px-0"
            card-class="min-w-[128px] snap-start rounded-2xl border border-border bg-card/80 p-3"
            label-class="text-xs font-bold uppercase text-secondary"
            value-class="mt-1 text-xl font-bold text-primary"
        />

        <p v-if="error" class="mt-3 rounded-xl bg-red-500/10 px-3 py-2 text-sm font-semibold text-red-500">
            {{ error }}
        </p>

        <SportMapTrackingActions
            :delete-label="$t('sport_map.tracks.discard')"
            delete-only-when-paused
            :has-points="hasPoints"
            :is-processing="isProcessing"
            :is-tracking="isTracking"
            :start-label="startLongLabel"
            wrapper-class="mt-4 hidden gap-2 lg:grid"
            delete-button-class="col-span-2 inline-flex min-h-11 items-center justify-center gap-2 rounded-xl border border-border px-4 py-2 text-sm font-bold text-secondary hover:bg-muted"
            @delete="$emit('reset-points')"
            @pause="$emit('pause')"
            @save="$emit('save')"
            @start="$emit('start')"
        />
    </section>
</template>
