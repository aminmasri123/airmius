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
    analysisMetrics: {
        type: Array,
        default: () => [],
    },
    catalogLabel: {
        type: Function,
        required: true,
    },
    compactMetrics: {
        type: Array,
        default: () => [],
    },
    currentLocationPoint: {
        type: Object,
        default: null,
    },
    defaultTrackTitle: {
        type: Function,
        required: true,
    },
    elapsedLabel: {
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
    hasPoints: {
        type: Boolean,
        default: false,
    },
    isOpen: {
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
    routePolyline: {
        type: String,
        default: '',
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
    'close',
    'delete-current-draft',
    'next-slide',
    'pause',
    'pointer-cancel-swipe',
    'pointer-down-swipe',
    'pointer-up-swipe',
    'previous-slide',
    'save',
    'select-slide',
    'show-location',
    'start',
    'touch-end-swipe',
    'touch-start-swipe',
])
</script>

<template>
    <Teleport to="body">
        <div
            v-if="isOpen"
            class="fixed inset-0 z-[2147483647] flex flex-col overflow-hidden bg-[#07101c] px-4 pb-4 text-primary sm:hidden"
            style="padding-top: max(1rem, env(safe-area-inset-top));"
        >
            <header class="flex shrink-0 items-center justify-between gap-3">
                <div class="min-w-0">
                    <p class="text-[11px] font-black uppercase tracking-[0.2em] text-air-blue">Airmius Track</p>
                    <h2 class="truncate text-xl font-black text-primary">{{ $t('sport_map.tracks.fullscreen_title') }}</h2>
                </div>
                <div class="flex items-center gap-2">
                    <span
                        class="rounded-full border px-3 py-1 text-xs font-bold"
                        :class="isTracking ? 'border-emerald-400/40 bg-emerald-500/15 text-emerald-300' : 'border-border bg-card text-secondary'"
                    >
                        {{ liveStatusLabel }}
                    </span>
                    <button
                        type="button"
                        class="inline-flex h-11 w-11 items-center justify-center rounded-full border border-white/10 bg-white/10 text-primary shadow-lg"
                        :aria-label="$t('sport_map.tracks.close_tracking')"
                        @click="$emit('close')"
                    >
                        <i class="las la-times text-2xl"></i>
                    </button>
                </div>
            </header>

            <main
                class="mt-4 min-h-0 flex-1 overflow-hidden rounded-[2rem] border border-white/10 bg-slate-950 shadow-2xl"
                @touchstart.passive="$emit('touch-start-swipe', $event)"
                @touchend="$emit('touch-end-swipe', $event)"
                @pointerdown="$emit('pointer-down-swipe', $event)"
                @pointerup="$emit('pointer-up-swipe', $event)"
                @pointercancel="$emit('pointer-cancel-swipe')"
            >
                <div
                    class="flex h-full transition-transform duration-300 ease-out"
                    :style="{ transform: `translateX(-${activeSlide * 100}%)` }"
                >
                    <section class="flex min-w-full flex-col justify-between p-5">
                        <div class="space-y-4">
                            <details v-if="!isTracking && !hasPoints" class="group rounded-3xl border border-white/10 bg-white/[0.03]">
                                <summary class="flex min-h-16 cursor-pointer list-none items-center justify-between gap-3 px-4 py-3">
                                    <span class="min-w-0">
                                        <span class="block text-[11px] font-black uppercase tracking-wide text-air-blue">{{ $t('sport_map.tracks.prepare_start') }}</span>
                                        <span class="block truncate text-base font-black text-primary">{{ form.title || defaultTrackTitle() }}</span>
                                        <span class="block text-xs font-bold text-secondary">{{ sportLabel(form.sport_type) }}</span>
                                    </span>
                                    <span class="inline-flex h-10 w-10 shrink-0 items-center justify-center rounded-full border border-white/10 bg-slate-950/70 text-primary">
                                        <i class="las la-sliders-h text-xl"></i>
                                    </span>
                                </summary>
                                <div class="grid gap-3 border-t border-white/10 px-4 pb-4 pt-3">
                                    <label class="block">
                                        <span class="mb-1 block text-xs font-bold uppercase text-secondary">{{ $t('sport_map.form.title') }}</span>
                                        <input
                                            v-model="form.title"
                                            class="w-full rounded-2xl border border-white/10 bg-slate-950/70 px-4 py-3 text-base font-black text-primary placeholder:text-secondary"
                                            :placeholder="defaultTrackTitle()"
                                        >
                                    </label>
                                    <label class="block">
                                        <span class="mb-1 block text-xs font-bold uppercase text-secondary">{{ $t('sport_map.form.sport') }}</span>
                                        <select v-model="form.sport_type" class="w-full rounded-2xl border border-white/10 bg-slate-950/70 px-4 py-3 text-sm font-bold text-primary">
                                            <option v-for="sport in sportTypes" :key="sport.key" :value="sport.key">{{ catalogLabel(sport, sport.key) }}</option>
                                        </select>
                                    </label>
                                </div>
                            </details>
                            <div v-else class="flex min-h-14 items-center justify-between gap-3 rounded-full border border-white/10 bg-white/[0.03] px-4">
                                <div class="min-w-0">
                                    <p class="truncate text-sm font-black text-primary">{{ form.title || defaultTrackTitle() }}</p>
                                    <p class="text-xs font-bold text-secondary">{{ sportLabel(form.sport_type) }}</p>
                                </div>
                                <button
                                    type="button"
                                    class="inline-flex h-10 w-10 shrink-0 items-center justify-center rounded-full border border-white/10 bg-slate-950/70 text-secondary"
                                    :aria-label="$t('sport_map.tracks.show_live_values')"
                                    @click="$emit('select-slide', 2)"
                                >
                                    <i class="las la-chart-line"></i>
                                </button>
                            </div>

                            <div class="py-2 text-center">
                                <p class="text-xs font-black uppercase tracking-[0.24em] text-secondary">{{ $t('sport_map.tracks.distance') }}</p>
                                <p class="mt-4 text-6xl font-black leading-none text-primary">{{ formatDistance(trackingDistance) }}</p>
                                <p class="mt-3 text-base font-black text-secondary">{{ elapsedLabel }}</p>
                            </div>

                            <SportMapTrackingMetricGrid
                                :metrics="compactMetrics"
                                grid-class="grid grid-cols-3 gap-2"
                                card-class="rounded-2xl border border-white/10 bg-white/[0.03] p-3"
                                label-class="text-[11px] font-bold uppercase text-secondary"
                                value-class="mt-1 text-lg font-black text-primary"
                            />
                        </div>

                        <SportMapTrackingActions
                            :has-points="hasPoints"
                            icon-class="text-2xl"
                            :is-processing="isProcessing"
                            :is-tracking="isTracking"
                            :start-label="startLabel"
                            variant="stacked"
                            wrapper-class="grid gap-3"
                            start-button-class="inline-flex min-h-16 w-full items-center justify-center gap-3 rounded-full bg-buttonPrimary px-5 text-base font-black text-buttonTextPrimary shadow-lg"
                            pause-button-class="inline-flex min-h-16 w-full items-center justify-center gap-3 rounded-full border border-amber-300/40 bg-amber-400/10 px-5 text-base font-black text-amber-200"
                            save-button-class="inline-flex min-h-13 items-center justify-center gap-2 rounded-full border border-emerald-400/40 bg-emerald-500/15 px-4 text-sm font-black text-emerald-200 disabled:opacity-50"
                            delete-button-class="inline-flex min-h-13 items-center justify-center gap-2 rounded-full border border-red-400/40 bg-red-500/10 px-4 text-sm font-black text-red-300"
                            :delete-label="$t('sport_map.tracks.delete_draft_short')"
                            @delete="$emit('delete-current-draft')"
                            @pause="$emit('pause')"
                            @save="$emit('save')"
                            @start="$emit('start')"
                        />
                    </section>

                    <section class="flex min-w-full flex-col overflow-hidden bg-card">
                        <div class="flex shrink-0 items-center justify-between gap-3 px-5 py-4">
                            <div>
                                <p class="text-xs font-black uppercase tracking-wide text-air-blue">{{ $t('sport_map.tracks.map_slide') }}</p>
                                <h3 class="text-lg font-black text-primary">{{ $t('sport_map.tracks.live_position') }}</h3>
                            </div>
                            <span class="rounded-full bg-inputBg px-3 py-1 text-xs font-bold text-secondary">{{ $t('sport_map.tracks.live_status.live') }}</span>
                        </div>
                        <SportMapTrackingMapSurface
                            :current-location-point="currentLocationPoint"
                            :map-overlay-tiles="mapOverlayTiles"
                            :map-tiles="mapTiles"
                            :marker-style="markerStyle"
                            :route-polyline="routePolyline"
                            tile-key-prefix="fullscreen-track"
                            :track-polyline="trackPolyline"
                            @click-map="$emit('click-map', $event)"
                            @show-location="$emit('show-location')"
                        />
                    </section>

                    <section class="flex min-w-full flex-col p-5">
                        <p class="text-xs font-black uppercase tracking-wide text-air-blue">{{ $t('sport_map.tracks.analysis') }}</p>
                        <h3 class="mt-1 text-2xl font-black text-primary">{{ $t('sport_map.tracks.live_values') }}</h3>
                        <SportMapTrackingMetricGrid
                            :metrics="analysisMetrics"
                            grid-class="mt-5 grid flex-1 grid-cols-2 content-start gap-3"
                            card-class="rounded-3xl border border-white/10 bg-white/[0.03] p-4"
                            label-class="text-xs font-bold uppercase text-secondary"
                            value-class="mt-2 text-3xl font-black text-primary"
                        />
                    </section>
                </div>
            </main>

            <SportMapTrackingSlideControls
                :active-slide="activeSlide"
                active-dot-class="w-9 bg-air-blue"
                button-class="inline-flex h-12 w-12 items-center justify-center rounded-full border border-white/10 bg-white/10 text-primary disabled:opacity-35"
                inactive-dot-class="w-2.5 bg-white/30"
                wrapper-class="mt-4 flex shrink-0 items-center justify-between gap-3"
                @next="$emit('next-slide')"
                @previous="$emit('previous-slide')"
                @select="$emit('select-slide', $event)"
            />
        </div>
    </Teleport>
</template>
