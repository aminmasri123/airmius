<script setup>
import AppLayout from "@/Components/Auth/Layouts/AppLayout.vue"
import { useSportMapWorkspace } from "@/composables/useSportMapWorkspace"

const props = defineProps({
    sportTypes: { type: Array, default: () => [] },
    placeTypes: { type: Array, default: () => [] },
    routes: { type: Array, default: () => [] },
    tracks: { type: Array, default: () => [] },
    places: { type: Array, default: () => [] },
    sportCatalog: { type: Array, default: () => [] },
    teams: { type: Array, default: () => [] },
    mapConfig: { type: Object, default: () => ({}) },
    sportMapAccess: { type: Object, default: () => ({}) },
})

const {
    t,
    te,
    catalogLabel,
    cueText,
    defaultTrackTitle,
    estimateCalories,
    formatCalories,
    formatClockDuration,
    formatDistance,
    formatDuration,
    formatPace,
    formatPercent,
    optionLabel,
    placeTypeLabel,
    qualityBadgeClass,
    splitList,
    sportLabel,
    tabLabel,
    trackAveragePaceLabel,
    trackAverageSpeedLabel,
    trackCaloriesLabel,
    trackStatusLabel,
    visibilityLabel,
    activeTab,
    selectedRouteId,
    placeLocationError,
    placeLocationStatus,
    mapIsDragging,
    mapWasDragged,
    manualMapPointStatus,
    currentLocationPoint,
    routeGeneratorStep,
    routeGeneratorStatus,
    generatedRoutePoints,
    generatedRouteGeometryPoints,
    generatedRouteMetrics,
    generatedRouteNavigationCues,
    isGeneratingRoute,
    routeGeneratorVariantSeed,
    routeGeneratorMapTarget,
    activeMapLayer,
    activeTileSourceIndex,
    visibleMapTileCount,
    failedMapTileCount,
    activeTrackingSlide,
    addManualTrackPoint,
    cancelTrackEdit,
    cancelTrackingPointerSwipe,
    cleanupTrackingSession,
    closeTrackingFullscreen,
    deleteCurrentTrackDraft,
    editTrackForm,
    editingTrackId,
    endTrackingPointerSwipe,
    endTrackingSwipe,
    importTrackGpx,
    isTracking,
    nextTrackingSlide,
    previousTrackingSlide,
    removeTrackPointFromMap,
    resetTrackPoints,
    saveTrack,
    selectTrackGpxFile,
    setTrackingSlide,
    startTrackEdit,
    startTracking,
    startTrackingFromMobile,
    startTrackingPointerSwipe,
    startTrackingSwipe,
    stopTracking,
    trackForm,
    trackGpxImportForm,
    trackingAnalysisMetrics,
    trackingAveragePaceLabel,
    trackingAverageSpeedLabel,
    trackingCaloriesLabel,
    trackingCompactMetrics,
    trackingDesktopMetrics,
    trackingDistance,
    trackingElapsedLabel,
    trackingError,
    trackingFullscreen,
    trackingLastAccuracyLabel,
    trackingLiveStatusLabel,
    trackingMobileMetrics,
    trackingPoints,
    trackingStartActionLabel,
    trackingStartActionLongLabel,
    updateSavedTrack,
    TILE_SIZE,
    MAP_WIDTH,
    MAP_HEIGHT,
    DEFAULT_CENTER,
    DEFAULT_ZOOM,
    mapView,
    routeGeneratorForm,
    placeForm,
    placeSportTypesText,
    placeAmenitiesText,
    placeSurfacesText,
    placeImageUrlsText,
    placeImageUploads,
    clearMapTextSelection,
    isValidMapCoordinate,
    addWaypoint,
    cleanedWaypoints,
    draftRouteGeometryPoints,
    importRouteGpx,
    removeLastRouteWaypoint,
    removeRouteWaypointFromMap,
    removeWaypoint,
    resetRouteWaypoints,
    routeForm,
    routeGpxImportForm,
    saveRoute,
    selectRouteGpxFile,
    setWaypointFromMap,
    waypointRows,
    draftPlacePoint,
    activeGeneratorPoints,
    selectedRoute,
    routePreviewPoints,
    routeGeometryPoints,
    anchorRouteLinePoints,
    routeLinePoints,
    activeTrackPoints,
    mapPoints,
    mapBoundsPoints,
    bounds,
    routePolyline,
    trackPolyline,
    routePlaybackPath,
    routePlaybackTotalDistance,
    activeRouteSportType,
    activeRouteEstimatedDurationSeconds,
    routePlaybackBaseMetersPerSecond,
    activeMapPointCount,
    mapLayerOptions,
    activeMapLayerOption,
    tileSources,
    defaultTileSource,
    activeTileSource,
    tileTemplate,
    mapOverlaySource,
    mapAttribution,
    mapHasRealTiles,
    mapStatusText,
    activeMapInteractionHint,
    placePreviewImages,
    fallbackMapLabels,
    tabs,
    landingActions,
    routeGeneratorTargetDistanceKm,
    routeGeneratorEstimatedMinutes,
    routeGeneratorDefaultSpeedKmh,
    routeGenerationAccess,
    routeGenerationLimitLabel,
    routeGeneratorTargetSpeedKmh,
    routeGeneratorPaceLabel,
    routeGeneratorStartPoint,
    routeGeneratorDestinationPoint,
    routeGeneratorSummary,
    generatedRouteActualSummary,
    generatedRouteCuePreview,
    setActiveTab,
    handlePlaceImageUploads,
    setGeneratorStep,
    setRouteGeneratorStartMode,
    setRouteGeneratorType,
    setGeneratorStartFromCoordinate,
    setGeneratorDestinationFromCoordinate,
    setGeneratorDestinationFromMapCenter,
    clearGeneratorDestination,
    useCurrentLocationForGenerator,
    generatedRouteTitle,
    routeGeometryToPoints,
    generatorWaypointName,
    routeProposalPayload,
    refreshRouteGeneratorVariant,
    applyRouteProposal,
    requestRouteProposal,
    generateRouteProposal,
    removeGeneratedRoutePoint,
    resetGeneratedRoute,
    applyGeneratedRouteToPlanner,
    useCurrentLocationForPlace,
    showCurrentLocationOnMap,
    setPlaceFromMap,
    clearPlaceMapPoint,
    removeMapPoint,
    removeLastActiveMapPoint,
    resetActiveMapPoints,
    savePlace,
    autoMapCenter,
    autoMapZoom,
    safeMapView,
    cleanupRoutePlayback,
    resetRoutePlayback,
    routePlaybackCanStart,
    routePlaybackElapsedSeconds,
    routePlaybackMarker,
    routePlaybackProgressMeters,
    routePlaybackProgressPercent,
    routePlaybackRemainingSeconds,
    routePlaybackSpeed,
    routePlaybackState,
    routePlaybackStatus,
    toggleRoutePlayback,
    mapProjection,
    tileUrl,
    handleTileLoad,
    handleTileError,
    mapTiles,
    mapOverlayTiles,
    coordinateFromMapEvent,
    setMapCenterFromWorld,
    zoomMap,
    resetMapView,
    startMapDrag,
    moveMapDrag,
    endMapDrag,
    handleMapClick,
    markerStyle,
    fallbackMapLabelStyle,
    svgPoint,
    polylinePoints,
    routeGeneratorDifficultyOptions,
    routeGeneratorElevationOptions,
    routeGeneratorEnvironmentOptions,
    routeGeneratorRouteTypes,
    routeGeneratorSpeedsKmh,
    routeGeneratorStartModes,
    routeGeneratorSteps,
    routeGeneratorSurfaceOptions,
    defaultSportMapTileSource,
    PLAYBACK_SPEED_OPTIONS,
    sportMapFallbackLabels,
    sportMapLandingActions,
    sportMapMapLayerOptions,
    sportMapPlaybackSpeedOptions,
    sportMapTabs,
} = useSportMapWorkspace(props)

const tx = (key, fallback, values = {}) => {
    const translated = t(key, values)
    return translated === key ? fallback : translated
}
</script>

<template>
    <Teleport to="body">
        <div
            v-if="trackingFullscreen"
            class="fixed inset-0 z-[2147483647] flex flex-col overflow-hidden bg-[#07101c] px-4 pb-4 text-primary sm:hidden"
            style="padding-top: max(1rem, env(safe-area-inset-top));"
        >
            <header class="flex shrink-0 items-center justify-between gap-3">
                <div class="min-w-0">
                    <p class="text-[11px] font-black uppercase tracking-[0.2em] text-air-blue">Airmius Track</p>
                    <h2 class="truncate text-xl font-black text-primary">Live-Tracking</h2>
                </div>
                <div class="flex items-center gap-2">
                    <span
                        class="rounded-full border px-3 py-1 text-xs font-bold"
                        :class="isTracking ? 'border-emerald-400/40 bg-emerald-500/15 text-emerald-300' : 'border-border bg-card text-secondary'"
                    >
                        {{ trackingLiveStatusLabel }}
                    </span>
                    <button
                        type="button"
                        class="inline-flex h-11 w-11 items-center justify-center rounded-full border border-white/10 bg-white/10 text-primary shadow-lg"
                        aria-label="Tracking schließen"
                        @click="closeTrackingFullscreen"
                    >
                        <i class="las la-times text-2xl"></i>
                    </button>
                </div>
            </header>

            <main
                class="mt-4 min-h-0 flex-1 overflow-hidden rounded-[2rem] border border-white/10 bg-slate-950 shadow-2xl"
                @touchstart.passive="startTrackingSwipe"
                @touchend="endTrackingSwipe"
                @pointerdown="startTrackingPointerSwipe"
                @pointerup="endTrackingPointerSwipe"
                @pointercancel="cancelTrackingPointerSwipe"
            >
                <div
                    class="flex h-full transition-transform duration-300 ease-out"
                    :style="{ transform: `translateX(-${activeTrackingSlide * 100}%)` }"
                >
                    <section class="flex min-w-full flex-col justify-between p-5">
                        <div class="space-y-5">
                            <div class="rounded-3xl border border-white/10 bg-white/[0.03] p-4">
                                <p class="text-xs font-black uppercase tracking-wide text-air-blue">Aktueller Lauf</p>
                                <label class="mt-3 block">
                                    <span class="sr-only">Bezeichnung</span>
                                    <input
                                        v-model="trackForm.title"
                                        class="w-full rounded-2xl border border-white/10 bg-slate-950/70 px-4 py-3 text-base font-black text-primary placeholder:text-secondary"
                                        :placeholder="defaultTrackTitle()"
                                    >
                                </label>
                                <label class="mt-3 block">
                                    <span class="sr-only">Sportart</span>
                                    <select v-model="trackForm.sport_type" class="w-full rounded-2xl border border-white/10 bg-slate-950/70 px-4 py-3 text-sm font-bold text-primary">
                                        <option v-for="sport in sportTypes" :key="sport.key" :value="sport.key">{{ catalogLabel(sport, sport.key) }}</option>
                                    </select>
                                </label>
                            </div>

                            <div class="py-4 text-center">
                                <p class="text-xs font-black uppercase tracking-[0.24em] text-secondary">{{ $t('sport_map.tracks.distance') }}</p>
                                <p class="mt-5 text-7xl font-black leading-none text-primary">{{ formatDistance(trackingDistance) }}</p>
                                <p class="mt-4 text-lg font-black text-secondary">{{ trackingElapsedLabel }}</p>
                            </div>

                            <div class="grid grid-cols-2 gap-3">
                                <div class="rounded-3xl border border-white/10 bg-white/[0.03] p-4">
                                    <p class="text-xs font-bold uppercase text-secondary">Pace</p>
                                    <p class="mt-1 text-2xl font-black text-primary">{{ trackingAveragePaceLabel }}</p>
                                </div>
                                <div class="rounded-3xl border border-white/10 bg-white/[0.03] p-4">
                                    <p class="text-xs font-bold uppercase text-secondary">km/h</p>
                                    <p class="mt-1 text-2xl font-black text-primary">{{ trackingAverageSpeedLabel }}</p>
                                </div>
                            </div>
                        </div>

                        <div class="grid gap-3">
                            <button
                                v-if="!isTracking"
                                type="button"
                                class="inline-flex min-h-16 w-full items-center justify-center gap-3 rounded-full bg-buttonPrimary px-5 text-base font-black text-buttonTextPrimary shadow-lg"
                                @click="startTracking"
                            >
                                <i class="las la-play text-2xl"></i>
                                {{ trackingPoints.length ? 'Weiter' : 'Starten' }}
                            </button>
                            <button
                                v-else
                                type="button"
                                class="inline-flex min-h-16 w-full items-center justify-center gap-3 rounded-full border border-amber-300/40 bg-amber-400/10 px-5 text-base font-black text-amber-200"
                                @click="stopTracking"
                            >
                                <i class="las la-pause text-2xl"></i>
                                Pause
                            </button>
                            <div v-if="trackingPoints.length" class="grid grid-cols-2 gap-3">
                                <button
                                    type="button"
                                    class="inline-flex min-h-13 items-center justify-center gap-2 rounded-full border border-emerald-400/40 bg-emerald-500/15 px-4 text-sm font-black text-emerald-200 disabled:opacity-50"
                                    :disabled="trackForm.processing"
                                    @click="saveTrack"
                                >
                                    <i class="las la-save"></i>
                                    Speichern
                                </button>
                                <button
                                    type="button"
                                    class="inline-flex min-h-13 items-center justify-center gap-2 rounded-full border border-red-400/40 bg-red-500/10 px-4 text-sm font-black text-red-300"
                                    @click="deleteCurrentTrackDraft"
                                >
                                    <i class="las la-trash"></i>
                                    Löschen
                                </button>
                            </div>
                        </div>
                    </section>

                    <section class="flex min-w-full flex-col overflow-hidden bg-card">
                        <div class="flex shrink-0 items-center justify-between gap-3 px-5 py-4">
                            <div>
                                <p class="text-xs font-black uppercase tracking-wide text-air-blue">Karte</p>
                                <h3 class="text-lg font-black text-primary">Live-Position</h3>
                            </div>
                            <span class="rounded-full bg-inputBg px-3 py-1 text-xs font-bold text-secondary">Live</span>
                        </div>
                        <div
                            class="relative min-h-0 flex-1 select-none overflow-hidden"
                            style="background-color: #efe6d1; user-select: none; -webkit-user-select: none;"
                            @click="handleMapClick"
                            @selectstart.prevent
                            @dragstart.prevent
                        >
                            <div class="absolute inset-0 z-0" style="background-color: #efe6d1;"></div>
                            <div class="pointer-events-none absolute inset-0 z-0 opacity-80" style="background-image: linear-gradient(90deg, rgba(120,113,108,.16) 1px, transparent 1px), linear-gradient(0deg, rgba(120,113,108,.14) 1px, transparent 1px); background-size: 56px 56px;"></div>
                            <img
                                v-for="tile in mapTiles"
                                :key="`fullscreen-track-${tile.key}`"
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
                                :key="`fullscreen-track-overlay-${tile.key}`"
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
                                class="absolute z-40 flex h-8 w-8 -translate-x-1/2 -translate-y-1/2 items-center justify-center rounded-full border-2 border-white bg-air-blue text-white shadow-lg"
                                :style="markerStyle(currentLocationPoint)"
                            >
                                <span class="absolute h-11 w-11 rounded-full bg-air-blue/25"></span>
                                <i class="las la-location-arrow relative text-base"></i>
                            </div>
                            <button
                                type="button"
                                class="absolute left-4 top-4 z-[80] inline-flex h-12 w-12 items-center justify-center rounded-full border border-white/70 bg-white/95 text-blue-600 shadow-lg"
                                aria-label="Mein Standort anzeigen"
                                @click.stop.prevent="showCurrentLocationOnMap"
                            >
                                <i class="las la-location-arrow text-xl"></i>
                            </button>
                        </div>
                    </section>

                    <section class="flex min-w-full flex-col p-5">
                        <p class="text-xs font-black uppercase tracking-wide text-air-blue">Analyse</p>
                        <h3 class="mt-1 text-2xl font-black text-primary">Live-Werte</h3>
                        <div class="mt-5 grid flex-1 grid-cols-2 content-start gap-3">
                            <div class="rounded-3xl border border-white/10 bg-white/[0.03] p-4">
                                <p class="text-xs font-bold uppercase text-secondary">Distanz</p>
                                <p class="mt-2 text-3xl font-black text-primary">{{ formatDistance(trackingDistance) }}</p>
                            </div>
                            <div class="rounded-3xl border border-white/10 bg-white/[0.03] p-4">
                                <p class="text-xs font-bold uppercase text-secondary">Zeit</p>
                                <p class="mt-2 text-3xl font-black text-primary">{{ trackingElapsedLabel }}</p>
                            </div>
                            <div class="rounded-3xl border border-white/10 bg-white/[0.03] p-4">
                                <p class="text-xs font-bold uppercase text-secondary">Pace</p>
                                <p class="mt-2 text-3xl font-black text-primary">{{ trackingAveragePaceLabel }}</p>
                            </div>
                            <div class="rounded-3xl border border-white/10 bg-white/[0.03] p-4">
                                <p class="text-xs font-bold uppercase text-secondary">km/h</p>
                                <p class="mt-2 text-3xl font-black text-primary">{{ trackingAverageSpeedLabel }}</p>
                            </div>
                            <div class="col-span-2 rounded-3xl border border-white/10 bg-white/[0.03] p-4">
                                <p class="text-xs font-bold uppercase text-secondary">GPS</p>
                                <p class="mt-2 text-xl font-black text-primary">{{ trackingLastAccuracyLabel }}</p>
                            </div>
                        </div>
                    </section>
                </div>
            </main>

            <footer class="mt-4 flex shrink-0 items-center justify-between gap-3">
                <button
                    type="button"
                    class="inline-flex h-12 w-12 items-center justify-center rounded-full border border-white/10 bg-white/10 text-primary disabled:opacity-35"
                    :disabled="activeTrackingSlide === 0"
                    @click="previousTrackingSlide"
                >
                    <i class="las la-arrow-left"></i>
                </button>
                <div class="flex items-center gap-2">
                    <button
                        v-for="index in 3"
                        :key="index"
                        type="button"
                        class="h-2.5 rounded-full transition-all"
                        :class="activeTrackingSlide === index - 1 ? 'w-9 bg-air-blue' : 'w-2.5 bg-white/30'"
                        @click="setTrackingSlide(index - 1)"
                    ></button>
                </div>
                <button
                    type="button"
                    class="inline-flex h-12 w-12 items-center justify-center rounded-full border border-white/10 bg-white/10 text-primary disabled:opacity-35"
                    :disabled="activeTrackingSlide === 2"
                    @click="nextTrackingSlide"
                >
                    <i class="las la-arrow-right"></i>
                </button>
            </footer>
        </div>
    </Teleport>

    <AppLayout :title="$t('sport_map.title')">
        <div class="mx-auto flex w-full max-w-7xl flex-col gap-5">
            <section class="rounded-lg border border-border bg-card p-4 shadow-sm sm:p-5">
                <div class="flex flex-col gap-4 lg:flex-row lg:items-center lg:justify-between">
                    <div class="max-w-3xl">
                        <p class="text-xs font-semibold uppercase tracking-wide text-air-blue">
                            Schritt 1: Sportkarte starten
                        </p>
                        <h2 class="mt-2 text-2xl font-bold text-primary sm:text-3xl">
                            {{ $t('sport_map.title') }}
                        </h2>
                        <p class="mt-2 text-sm leading-6 text-secondary">
                            Plane Routen, tracke deine Strecke live oder finde und teile Sportplätze in deiner Umgebung.
                        </p>
                    </div>

                    <div class="grid grid-cols-3 gap-2 text-center">
                        <div class="rounded-lg border border-border bg-inputBg px-3 py-2">
                            <p class="text-lg font-bold text-primary">{{ routes.length }}</p>
                            <p class="text-xs text-secondary">{{ $t('sport_map.stats.routes') }}</p>
                        </div>
                        <div class="rounded-lg border border-border bg-inputBg px-3 py-2">
                            <p class="text-lg font-bold text-primary">{{ tracks.length }}</p>
                            <p class="text-xs text-secondary">{{ $t('sport_map.stats.tracks') }}</p>
                        </div>
                        <div class="rounded-lg border border-border bg-inputBg px-3 py-2">
                            <p class="text-lg font-bold text-primary">{{ places.length }}</p>
                            <p class="text-xs text-secondary">{{ $t('sport_map.stats.places') }}</p>
                        </div>
                    </div>
                </div>
            </section>

            <section v-if="activeTab === 'landing'" class="grid gap-4 md:grid-cols-2 xl:grid-cols-5">
                <button
                    v-for="action in landingActions"
                    :key="`${action.key}-${action.title}`"
                    type="button"
                    class="group rounded-2xl border p-4 text-left transition hover:-translate-y-0.5 hover:shadow-xl"
                    :class="action.color"
                    @click="setActiveTab(action.key)"
                >
                    <div class="flex h-12 w-12 items-center justify-center rounded-2xl bg-white/15 text-2xl">
                        <i :class="action.icon"></i>
                    </div>
                    <h3 class="mt-4 text-base font-bold text-primary group-hover:text-white">{{ action.title }}</h3>
                    <p class="mt-2 text-sm leading-6 text-secondary group-hover:text-white/80">{{ action.description }}</p>
                    <span class="mt-4 inline-flex items-center gap-2 text-sm font-semibold">
                        Loslegen
                        <i class="las la-arrow-right"></i>
                    </span>
                </button>
            </section>

            <section v-if="activeTab !== 'landing'" class="grid gap-5 xl:grid-cols-[minmax(0,1.25fr),minmax(320px,0.75fr)]">
                <div
                    class="overflow-hidden rounded-lg border border-border bg-card shadow-sm"
                    :class="activeTab === 'tracks' ? 'hidden lg:block' : ''"
                >
                    <div class="flex flex-col gap-3 border-b border-border px-4 py-3 sm:flex-row sm:items-center sm:justify-between">
                        <div>
                            <p class="text-sm font-semibold text-primary">{{ $t('sport_map.map_preview') }}</p>
                            <p class="text-xs text-secondary">{{ mapStatusText }}</p>
                        </div>
                        <div class="flex flex-wrap items-center gap-2">
                            <div class="flex overflow-hidden rounded-lg border border-border bg-inputBg p-1">
                                <button
                                    v-for="layer in mapLayerOptions"
                                    :key="layer.key"
                                    type="button"
                                    class="inline-flex min-h-9 items-center justify-center gap-1.5 rounded-md px-3 text-xs font-bold transition"
                                    :class="activeMapLayer === layer.key ? 'bg-buttonPrimary text-buttonTextPrimary shadow-sm' : 'text-secondary hover:bg-card hover:text-primary'"
                                    :aria-label="`${layer.label} anzeigen`"
                                    @click="activeMapLayer = layer.key"
                                >
                                    <i :class="layer.icon"></i>
                                    <span>{{ layer.label }}</span>
                                </button>
                            </div>
                            <span class="rounded-full bg-inputBg px-3 py-1 text-xs font-semibold text-secondary">
                                Interaktive Karte
                            </span>
                        </div>
                    </div>

                    <div
                        class="relative aspect-[4/3] min-h-[320px] touch-none select-none overflow-hidden sm:aspect-[16/9]"
                        :class="mapIsDragging ? 'cursor-grabbing' : 'cursor-crosshair'"
                        style="background-color: #efe6d1; user-select: none; -webkit-user-select: none;"
                        role="application"
                        aria-label="Interaktive Sportkarte"
                        @pointerdown="startMapDrag"
                        @pointermove="moveMapDrag"
                        @pointerup="endMapDrag"
                        @pointercancel="endMapDrag"
                        @pointerleave="endMapDrag"
                        @click="handleMapClick"
                        @selectstart.prevent
                        @dragstart.prevent
                        @wheel.prevent="zoomMap($event.deltaY > 0 ? -1 : 1, $event)"
                    >
                        <div class="absolute inset-0 z-0" style="background-color: #efe6d1;"></div>
                        <div class="pointer-events-none absolute inset-0 z-0 opacity-80"
                            style="background-image: linear-gradient(90deg, rgba(120,113,108,.16) 1px, transparent 1px), linear-gradient(0deg, rgba(120,113,108,.14) 1px, transparent 1px); background-size: 56px 56px;">
                        </div>
                        <div class="pointer-events-none absolute -left-[8%] top-[12%] z-0 h-[34%] w-[52%] rotate-[-8deg] rounded-[45%] bg-emerald-200/65 blur-[1px]"></div>
                        <div class="pointer-events-none absolute right-[5%] top-[6%] z-0 h-[30%] w-[32%] rotate-[16deg] rounded-[42%] bg-lime-200/70 blur-[1px]"></div>
                        <div class="pointer-events-none absolute bottom-[5%] left-[16%] z-0 h-[26%] w-[42%] rotate-[10deg] rounded-[44%] bg-green-200/55 blur-[1px]"></div>
                        <div class="pointer-events-none absolute bottom-[12%] right-[10%] z-0 h-[18%] w-[28%] rotate-[-14deg] rounded-[42%] bg-amber-100/80 blur-[1px]"></div>
                        <svg class="pointer-events-none absolute inset-0 z-[1] h-full w-full" viewBox="0 0 100 100" preserveAspectRatio="none">
                            <path d="M-5 70 C 18 64, 35 62, 52 55 S 83 39, 105 35" fill="none" stroke="rgba(255,255,255,.82)" stroke-width="3.8" />
                            <path d="M-5 70 C 18 64, 35 62, 52 55 S 83 39, 105 35" fill="none" stroke="rgba(234,179,8,.72)" stroke-width="1.8" />
                            <path d="M7 12 C 25 22, 34 38, 48 50 S 72 70, 95 86" fill="none" stroke="rgba(255,255,255,.8)" stroke-width="2.6" />
                            <path d="M7 12 C 25 22, 34 38, 48 50 S 72 70, 95 86" fill="none" stroke="rgba(120,113,108,.52)" stroke-width="1.1" stroke-dasharray="2 1.4" />
                            <path d="M-3 34 C 20 36, 35 31, 55 33 S 80 43, 103 45" fill="none" stroke="rgba(255,255,255,.75)" stroke-width="2.1" />
                            <path d="M-3 34 C 20 36, 35 31, 55 33 S 80 43, 103 45" fill="none" stroke="rgba(120,113,108,.42)" stroke-width=".9" />
                            <path d="M30 -5 C 34 17, 41 34, 39 52 S 36 77, 45 105" fill="none" stroke="rgba(255,255,255,.7)" stroke-width="1.8" />
                            <path d="M30 -5 C 34 17, 41 34, 39 52 S 36 77, 45 105" fill="none" stroke="rgba(120,113,108,.36)" stroke-width=".8" />
                        </svg>
                        <div class="pointer-events-none absolute inset-0 z-[2] bg-gradient-to-b from-white/20 via-transparent to-amber-950/5"></div>

                        <img
                            v-for="tile in mapTiles"
                            :key="tile.key"
                            :src="tile.url"
                            :style="tile.style"
                            class="pointer-events-none absolute z-[6] max-w-none select-none"
                            alt=""
                            aria-hidden="true"
                            decoding="async"
                            loading="eager"
                            draggable="false"
                            @dragstart.prevent
                            @load="handleTileLoad"
                            @error="handleTileError"
                        >
                        <img
                            v-for="tile in mapOverlayTiles"
                            :key="tile.key"
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

                        <template v-if="!mapHasRealTiles">
                            <span
                                v-for="label in fallbackMapLabels"
                                :key="label.name"
                                class="pointer-events-none absolute z-[8] -translate-x-1/2 -translate-y-1/2 rounded bg-white/80 px-2 py-0.5 text-[11px] font-bold text-stone-700 shadow-sm"
                                :style="fallbackMapLabelStyle(label)"
                            >
                                {{ label.name }}
                            </span>
                        </template>

                        <div
                            class="pointer-events-auto absolute left-3 top-3 z-[80] flex flex-col overflow-hidden rounded-xl border border-white/70 bg-white/95 shadow-2xl ring-1 ring-slate-950/20 backdrop-blur"
                            @click.stop
                            @pointerdown.stop
                            @pointermove.stop
                            @pointerup.stop
                            @wheel.stop
                        >
                            <button
                                type="button"
                                class="flex h-10 w-10 items-center justify-center text-lg font-black text-slate-950 transition hover:bg-air-blue hover:text-white"
                                aria-label="Karte vergrößern"
                                @click.stop.prevent="zoomMap(1)"
                            >
                                +
                            </button>
                            <button
                                type="button"
                                class="flex h-10 w-10 items-center justify-center border-t border-slate-300 text-lg font-black text-slate-950 transition hover:bg-air-blue hover:text-white"
                                aria-label="Karte verkleinern"
                                @click.stop.prevent="zoomMap(-1)"
                            >
                                -
                            </button>
                            <button
                                type="button"
                                class="flex h-10 w-10 items-center justify-center border-t border-slate-300 text-base text-slate-800 transition hover:bg-slate-900 hover:text-white"
                                aria-label="Karte zentrieren"
                                @click.stop.prevent="resetMapView"
                            >
                                <i class="las la-crosshairs"></i>
                            </button>
                            <button
                                type="button"
                                class="flex h-10 w-10 items-center justify-center border-t border-slate-300 text-base text-blue-600 transition hover:bg-blue-600 hover:text-white"
                                aria-label="Mein Standort anzeigen"
                                title="Mein Standort"
                                @click.stop.prevent="showCurrentLocationOnMap"
                            >
                                <i class="las la-location-arrow"></i>
                            </button>
                            <button
                                type="button"
                                class="flex h-10 w-10 items-center justify-center border-t border-slate-300 text-base text-slate-700 transition hover:bg-slate-900 hover:text-white"
                                aria-label="Letzten Punkt entfernen"
                                @click.stop.prevent="removeLastActiveMapPoint"
                            >
                                <i class="las la-undo-alt"></i>
                            </button>
                            <button
                                type="button"
                                class="flex h-10 w-10 items-center justify-center border-t border-slate-300 text-base text-red-600 transition hover:bg-red-600 hover:text-white"
                                aria-label="Punkte zurücksetzen"
                                @click.stop.prevent="resetActiveMapPoints"
                            >
                                <i class="las la-trash"></i>
                            </button>
                        </div>

                        <div class="pointer-events-none absolute right-3 top-3 z-20 max-w-xs rounded-lg border border-border bg-card/95 px-3 py-2 text-xs font-semibold text-secondary shadow-sm">
                            <span v-if="activeTab === 'generator'">
                                Karte ziehen/zoomen. Klick setzt {{ routeGeneratorForm.route_type === 'point_to_point' && routeGeneratorMapTarget === 'destination' ? 'den Zielpunkt' : 'den Startpunkt' }}.
                            </span>
                            <span v-else-if="activeTab === 'routes'">Karte ziehen/zoomen. Klick setzt den nächsten Routenpunkt.</span>
                            <span v-else-if="activeTab === 'tracks'">Karte ziehen/zoomen. Klick setzt einen manuellen Trackpunkt.</span>
                            <span v-else>Karte ziehen/zoomen. Klick setzt die Sportplatz-Position.</span>
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
                            title="Route-Vorschau"
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
                                aria-label="Punkt entfernen"
                                @click.stop="removeMapPoint(point)"
                                @pointerdown.stop
                            >
                                <i class="las la-times"></i>
                            </button>
                            <i v-if="point.kind === 'place'" class="las la-map-marker-alt"></i>
                            <span v-else>{{ index + 1 }}</span>
                        </div>

                        <div v-if="!mapPoints.length" class="absolute bottom-10 left-4 z-30 max-w-xs rounded-lg border border-border bg-card/95 px-3 py-2 text-xs font-semibold text-secondary shadow-sm">
                            Karte bereit. Wähle unten Route planen, Tracking oder Sportplatz eintragen.
                        </div>

                        <div
                            v-if="routePlaybackCanStart"
                            class="pointer-events-auto absolute bottom-4 left-4 right-4 z-[70] rounded-xl border border-border bg-card/95 p-3 shadow-xl backdrop-blur"
                            @click.stop
                            @pointerdown.stop
                            @pointermove.stop
                            @pointerup.stop
                            @wheel.stop
                        >
                            <div class="flex flex-col gap-3 md:flex-row md:items-center md:justify-between">
                                <div class="min-w-0 flex-1">
                                    <div class="flex items-center justify-between gap-3">
                                        <p class="text-sm font-bold text-primary">Route-Vorschau</p>
                                        <p class="shrink-0 text-xs font-semibold text-secondary">
                                            {{ formatDistance(routePlaybackProgressMeters) }} / {{ formatDistance(routePlaybackTotalDistance) }}
                                        </p>
                                    </div>
                                    <div class="mt-2 h-2 overflow-hidden rounded-full bg-inputBg">
                                        <div class="h-full rounded-full bg-orange-400 transition-[width]" :style="{ width: `${routePlaybackProgressPercent}%` }"></div>
                                    </div>
                                    <p class="mt-1 text-xs text-secondary">
                                        {{ formatDuration(routePlaybackElapsedSeconds) }} gelaufen • {{ formatDuration(routePlaybackRemainingSeconds) }} Rest
                                        <span v-if="routePlaybackStatus"> • {{ routePlaybackStatus }}</span>
                                    </p>
                                </div>

                                <div class="flex flex-wrap items-center gap-2">
                                    <button
                                        type="button"
                                        class="inline-flex min-h-10 items-center justify-center gap-2 rounded-lg bg-buttonPrimary px-4 py-2 text-sm font-semibold text-buttonTextPrimary"
                                        @click="toggleRoutePlayback"
                                    >
                                        <i :class="routePlaybackState === 'playing' ? 'las la-pause' : 'las la-play'"></i>
                                        {{ routePlaybackState === 'playing' ? 'Pause' : routePlaybackState === 'paused' ? 'Weiter' : 'Start' }}
                                    </button>
                                    <button
                                        type="button"
                                        class="inline-flex min-h-10 items-center justify-center gap-2 rounded-lg border border-border px-3 py-2 text-sm font-semibold text-primary hover:bg-muted"
                                        @click="resetRoutePlayback('Route-Vorschau zurückgesetzt.')"
                                    >
                                        <i class="las la-redo-alt"></i>
                                        Reset
                                    </button>
                                    <div class="flex overflow-hidden rounded-lg border border-border">
                                        <button
                                            v-for="speed in PLAYBACK_SPEED_OPTIONS"
                                            :key="speed"
                                            type="button"
                                            class="min-h-10 px-3 text-xs font-bold"
                                            :class="routePlaybackSpeed === speed ? 'bg-orange-400 text-slate-950' : 'bg-inputBg text-secondary hover:text-primary'"
                                            @click="routePlaybackSpeed = speed"
                                        >
                                            {{ speed }}x
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <span class="absolute bottom-2 right-2 z-30 rounded bg-card/90 px-2 py-1 text-[10px] font-semibold text-secondary shadow-sm">
                            {{ mapAttribution }}
                        </span>
                    </div>
                </div>

                <div class="rounded-lg border border-border bg-card p-4 shadow-sm">
                    <div class="grid grid-cols-2 gap-2 sm:grid-cols-5">
                        <button
                            v-for="tab in tabs"
                            :key="tab.key"
                            type="button"
                            class="flex min-h-11 items-center justify-center gap-2 rounded-lg border px-2 text-sm font-semibold"
                            :class="activeTab === tab.key ? 'border-buttonPrimary bg-buttonPrimary text-buttonTextPrimary' : 'border-border bg-inputBg text-secondary hover:text-primary'"
                            :aria-label="tabLabel(tab)"
                            @click="setActiveTab(tab.key)"
                        >
                            <i :class="tab.icon"></i>
                            <span class="hidden sm:inline">{{ tabLabel(tab) }}</span>
                        </button>
                    </div>

                    <div class="mt-4 space-y-3">
                        <button
                            v-for="routeItem in routes.slice(0, 5)"
                            :key="routeItem.id"
                            type="button"
                            class="w-full rounded-lg border border-border bg-inputBg p-3 text-left hover:bg-muted"
                            :class="{ 'border-buttonPrimary': selectedRouteId === routeItem.id }"
                            @click="selectedRouteId = routeItem.id"
                        >
                            <div class="flex items-center justify-between gap-3">
                                <p class="truncate text-sm font-semibold text-primary">{{ routeItem.title }}</p>
                                <span class="shrink-0 text-xs font-semibold text-secondary">{{ formatDistance(routeItem.distance_meters) }}</span>
                            </div>
                            <p class="mt-1 text-xs text-secondary">
                                {{ sportLabel(routeItem.sport_type) }} - {{ formatDuration(routeItem.estimated_duration_seconds) }}
                            </p>
                        </button>
                    </div>
                </div>
            </section>

            <section v-if="activeTab !== 'landing'" class="rounded-lg border border-border bg-card shadow-sm">
                <div v-if="activeTab === 'generator'" class="space-y-5 p-4">
                    <div class="grid gap-2 sm:grid-cols-3">
                        <button
                            v-for="item in routeGeneratorSteps"
                            :key="item.step"
                            type="button"
                            class="rounded-xl border px-4 py-3 text-left transition"
                            :class="routeGeneratorStep === item.step ? 'border-air-blue bg-air-blue/15 text-primary' : 'border-border bg-inputBg text-secondary hover:text-primary'"
                            @click="setGeneratorStep(item.step)"
                        >
                            <span class="text-xs font-bold uppercase text-air-blue">Schritt {{ item.step }}</span>
                            <p class="mt-1 text-sm font-bold">{{ item.label }}</p>
                        </button>
                    </div>

                    <p v-if="routeGeneratorStatus && routeGeneratorStep !== 3" class="rounded-lg bg-air-blue/10 px-3 py-2 text-sm font-semibold text-air-blue">
                        {{ routeGeneratorStatus }}
                    </p>
                    <p class="rounded-lg border border-border bg-inputBg px-3 py-2 text-xs font-semibold text-secondary">
                        {{ routeGenerationLimitLabel }}
                    </p>

                    <div v-if="routeGeneratorStep === 1" class="grid gap-5 lg:grid-cols-[minmax(0,0.9fr),minmax(0,1.1fr)]">
                        <div class="space-y-4">
                            <div>
                                <h3 class="text-lg font-bold text-primary">Route generieren</h3>
                                <p class="mt-1 text-sm leading-6 text-secondary">Wähle nur die wichtigsten Daten. Die Details kannst du danach in der normalen Routenplanung speichern.</p>
                            </div>

                            <label class="space-y-1">
                                <span class="text-xs font-semibold text-secondary">Routenname optional</span>
                                <input v-model="routeGeneratorForm.title" class="w-full rounded-lg border border-border bg-inputBg px-3 py-2 text-sm" placeholder="z. B. Waldlauf nach Feierabend">
                            </label>

                            <label class="space-y-1">
                                <span class="text-xs font-semibold text-secondary">Sportart</span>
                                <select v-model="routeGeneratorForm.sport_type" class="w-full rounded-lg border border-border bg-inputBg px-3 py-2 text-sm">
                                    <option v-for="sport in sportTypes" :key="sport.key" :value="sport.key">{{ catalogLabel(sport, sport.key) }}</option>
                                </select>
                            </label>

                            <div class="grid gap-2 sm:grid-cols-3">
                                <button
                                    v-for="mode in routeGeneratorStartModes"
                                    :key="mode.key"
                                    type="button"
                                    class="rounded-xl border px-3 py-3 text-left text-sm font-semibold"
                                    :class="routeGeneratorForm.start_mode === mode.key ? 'border-air-blue bg-air-blue/15 text-primary' : 'border-border bg-inputBg text-secondary hover:text-primary'"
                                    @click="setRouteGeneratorStartMode(mode.key)"
                                >
                                    <i :class="mode.icon"></i>
                                    <span class="ml-2">{{ mode.label }}</span>
                                </button>
                            </div>

                            <div v-if="routeGeneratorForm.start_mode === 'manual'" class="grid gap-3 sm:grid-cols-2">
                                <input v-model="routeGeneratorForm.start_latitude" type="number" step="0.0000001" class="rounded-lg border border-border bg-inputBg px-3 py-2 text-sm" placeholder="Latitude">
                                <input v-model="routeGeneratorForm.start_longitude" type="number" step="0.0000001" class="rounded-lg border border-border bg-inputBg px-3 py-2 text-sm" placeholder="Longitude">
                            </div>

                            <button type="button" class="inline-flex min-h-11 items-center justify-center gap-2 rounded-lg border border-border px-4 py-2 text-sm font-semibold hover:bg-muted" @click="useCurrentLocationForGenerator">
                                <i class="las la-location-arrow"></i>
                                Mein Standort als Startpunkt
                            </button>
                        </div>

                        <div class="space-y-4">
                            <div class="grid gap-2 sm:grid-cols-2">
                                <button
                                    v-for="type in routeGeneratorRouteTypes"
                                    :key="type.key"
                                    type="button"
                                    class="rounded-xl border px-4 py-4 text-left"
                                    :class="routeGeneratorForm.route_type === type.key ? 'border-air-blue bg-air-blue/15 text-primary' : 'border-border bg-inputBg text-secondary hover:text-primary'"
                                    @click="setRouteGeneratorType(type.key)"
                                >
                                    <i :class="type.icon"></i>
                                    <span class="ml-2 text-sm font-bold">{{ type.label }}</span>
                                    <span class="mt-2 block text-xs text-secondary">{{ type.description }}</span>
                                </button>
                            </div>

                            <div v-if="routeGeneratorForm.route_type === 'point_to_point'" class="rounded-xl border border-border bg-inputBg p-3">
                                <div class="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
                                    <div>
                                        <p class="text-sm font-bold text-primary">Zielpunkt</p>
                                        <p class="text-xs text-secondary">Kartenklick kann Start oder Ziel setzen. Für Zielroute brauchst du ein echtes Ziel.</p>
                                    </div>
                                    <div class="flex overflow-hidden rounded-lg border border-border">
                                        <button
                                            type="button"
                                            class="px-3 py-2 text-xs font-bold"
                                            :class="routeGeneratorMapTarget === 'start' ? 'bg-air-blue text-white' : 'bg-card text-secondary hover:text-primary'"
                                            @click="routeGeneratorMapTarget = 'start'"
                                        >
                                            Start setzen
                                        </button>
                                        <button
                                            type="button"
                                            class="border-l border-border px-3 py-2 text-xs font-bold"
                                            :class="routeGeneratorMapTarget === 'destination' ? 'bg-air-blue text-white' : 'bg-card text-secondary hover:text-primary'"
                                            @click="routeGeneratorMapTarget = 'destination'"
                                        >
                                            Ziel setzen
                                        </button>
                                    </div>
                                </div>

                                <div class="mt-3 grid gap-3 sm:grid-cols-2">
                                    <input v-model="routeGeneratorForm.destination_latitude" type="number" step="0.0000001" class="rounded-lg border border-border bg-card px-3 py-2 text-sm" placeholder="Ziel Latitude">
                                    <input v-model="routeGeneratorForm.destination_longitude" type="number" step="0.0000001" class="rounded-lg border border-border bg-card px-3 py-2 text-sm" placeholder="Ziel Longitude">
                                </div>

                                <div class="mt-3 flex flex-wrap gap-2">
                                    <button type="button" class="rounded-lg border border-border px-3 py-2 text-xs font-semibold hover:bg-muted" @click="setGeneratorDestinationFromMapCenter">
                                        Kartenmitte als Ziel
                                    </button>
                                    <button type="button" class="rounded-lg border border-border px-3 py-2 text-xs font-semibold hover:bg-muted" @click="clearGeneratorDestination">
                                        Ziel entfernen
                                    </button>
                                    <span v-if="routeGeneratorDestinationPoint" class="rounded-lg bg-emerald-500/10 px-3 py-2 text-xs font-bold text-emerald-500">
                                        Ziel gesetzt: {{ Number(routeGeneratorDestinationPoint.latitude).toFixed(5) }}, {{ Number(routeGeneratorDestinationPoint.longitude).toFixed(5) }}
                                    </span>
                                </div>
                            </div>

                            <div class="rounded-xl border border-border bg-inputBg p-3">
                                <div class="grid gap-2 sm:grid-cols-2">
                                    <button
                                        type="button"
                                        class="rounded-lg border px-3 py-2 text-sm font-semibold"
                                        :class="routeGeneratorForm.target_mode === 'distance' ? 'border-air-blue bg-air-blue/15 text-primary' : 'border-border bg-card text-secondary'"
                                        @click="routeGeneratorForm.target_mode = 'distance'"
                                    >
                                        Distanz
                                    </button>
                                    <button
                                        type="button"
                                        class="rounded-lg border px-3 py-2 text-sm font-semibold"
                                        :class="routeGeneratorForm.target_mode === 'duration' ? 'border-air-blue bg-air-blue/15 text-primary' : 'border-border bg-card text-secondary'"
                                        @click="routeGeneratorForm.target_mode = 'duration'"
                                    >
                                        Dauer
                                    </button>
                                </div>

                                <div class="mt-3 grid gap-3" :class="routeGeneratorForm.target_mode === 'duration' ? 'sm:grid-cols-3' : 'sm:grid-cols-2'">
                                    <label v-if="routeGeneratorForm.target_mode === 'distance'" class="space-y-1">
                                        <span class="text-xs font-semibold text-secondary">Distanz in km</span>
                                        <input v-model="routeGeneratorForm.distance_km" type="number" min="1" max="80" step="0.5" class="w-full rounded-lg border border-border bg-card px-3 py-2 text-sm">
                                    </label>
                                    <label v-if="routeGeneratorForm.target_mode === 'duration'" class="space-y-1">
                                        <span class="text-xs font-semibold text-secondary">Dauer in Minuten</span>
                                        <input v-model="routeGeneratorForm.duration_minutes" type="number" min="10" max="360" step="5" class="w-full rounded-lg border border-border bg-card px-3 py-2 text-sm">
                                    </label>
                                    <label v-if="routeGeneratorForm.target_mode === 'duration'" class="space-y-1">
                                        <span class="text-xs font-semibold text-secondary">Tempo</span>
                                        <select v-model="routeGeneratorForm.pace_mode" class="w-full rounded-lg border border-border bg-card px-3 py-2 text-sm">
                                            <option value="pace">Pace min/km</option>
                                            <option value="speed">km/h</option>
                                        </select>
                                    </label>
                                    <label v-if="routeGeneratorForm.target_mode === 'duration' && routeGeneratorForm.pace_mode === 'pace'" class="space-y-1">
                                        <span class="text-xs font-semibold text-secondary">Pace min/km</span>
                                        <input v-model="routeGeneratorForm.pace_min_per_km" type="number" min="2.5" max="30" step="0.1" class="w-full rounded-lg border border-border bg-card px-3 py-2 text-sm">
                                    </label>
                                    <label v-if="routeGeneratorForm.target_mode === 'duration' && routeGeneratorForm.pace_mode === 'speed'" class="space-y-1">
                                        <span class="text-xs font-semibold text-secondary">Geschwindigkeit km/h</span>
                                        <input v-model="routeGeneratorForm.speed_kmh" type="number" min="1" max="80" step="0.5" class="w-full rounded-lg border border-border bg-card px-3 py-2 text-sm">
                                    </label>
                                </div>
                            </div>

                            <div class="rounded-xl border border-air-blue/30 bg-air-blue/10 p-4">
                                <p class="text-sm font-bold text-primary">Aktuelle Planung</p>
                                <div class="mt-3 grid grid-cols-2 gap-2 text-sm text-secondary sm:grid-cols-4">
                                    <span>{{ routeGeneratorSummary.distance }}</span>
                                    <span>{{ routeGeneratorSummary.duration }}</span>
                                    <span>{{ routeGeneratorSummary.pace }}</span>
                                    <span>{{ routeGeneratorForm.route_type === 'roundtrip' ? 'Rundroute' : 'Einmal zum Ziel' }}</span>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div v-else-if="routeGeneratorStep === 2" class="grid gap-5 lg:grid-cols-2">
                        <div class="space-y-4">
                            <div>
                                <h3 class="text-lg font-bold text-primary">Route-Stil</h3>
                                <p class="mt-1 text-sm leading-6 text-secondary">Diese Parameter bestimmen, wie der Vorschlag wirken soll: Untergrund, Umgebung, Steigung und Schwierigkeit.</p>
                            </div>

                            <div>
                                <p class="text-xs font-semibold uppercase text-secondary">Untergrund</p>
                                <div class="mt-2 flex flex-wrap gap-2">
                                    <button
                                        v-for="surface in routeGeneratorSurfaceOptions"
                                        :key="surface.key"
                                        type="button"
                                        class="rounded-full border px-3 py-2 text-sm font-semibold"
                                        :class="routeGeneratorForm.surface === surface.key ? 'border-air-blue bg-air-blue/15 text-primary' : 'border-border bg-inputBg text-secondary hover:text-primary'"
                                        @click="routeGeneratorForm.surface = surface.key"
                                    >
                                        {{ surface.label }}
                                    </button>
                                </div>
                            </div>

                            <div>
                                <p class="text-xs font-semibold uppercase text-secondary">Umgebung</p>
                                <div class="mt-2 grid gap-2 sm:grid-cols-2">
                                    <button
                                        v-for="environment in routeGeneratorEnvironmentOptions"
                                        :key="environment.key"
                                        type="button"
                                        class="rounded-xl border px-3 py-3 text-left text-sm font-semibold"
                                        :class="routeGeneratorForm.environment === environment.key ? 'border-air-blue bg-air-blue/15 text-primary' : 'border-border bg-inputBg text-secondary hover:text-primary'"
                                        @click="routeGeneratorForm.environment = environment.key"
                                    >
                                        {{ environment.label }}
                                    </button>
                                </div>
                            </div>
                        </div>

                        <div class="space-y-4">
                            <div>
                                <p class="text-xs font-semibold uppercase text-secondary">Steigung</p>
                                <div class="mt-2 grid gap-2 sm:grid-cols-3">
                                    <button
                                        v-for="elevation in routeGeneratorElevationOptions"
                                        :key="elevation.key"
                                        type="button"
                                        class="rounded-xl border px-3 py-3 text-sm font-semibold"
                                        :class="routeGeneratorForm.elevation === elevation.key ? 'border-air-blue bg-air-blue/15 text-primary' : 'border-border bg-inputBg text-secondary hover:text-primary'"
                                        @click="routeGeneratorForm.elevation = elevation.key"
                                    >
                                        {{ elevation.label }}
                                    </button>
                                </div>
                            </div>

                            <div>
                                <p class="text-xs font-semibold uppercase text-secondary">Schwierigkeit</p>
                                <div class="mt-2 grid gap-2 sm:grid-cols-3">
                                    <button
                                        v-for="difficulty in routeGeneratorDifficultyOptions"
                                        :key="difficulty.key"
                                        type="button"
                                        class="rounded-xl border px-3 py-3 text-sm font-semibold"
                                        :class="routeGeneratorForm.difficulty === difficulty.key ? 'border-air-blue bg-air-blue/15 text-primary' : 'border-border bg-inputBg text-secondary hover:text-primary'"
                                        @click="routeGeneratorForm.difficulty = difficulty.key"
                                    >
                                        {{ difficulty.label }}
                                    </button>
                                </div>
                            </div>

                            <div class="grid gap-3 sm:grid-cols-3">
                                <label class="flex items-center gap-3 rounded-xl border border-border bg-inputBg p-3 text-sm font-semibold text-primary">
                                    <input v-model="routeGeneratorForm.low_traffic" type="checkbox" class="rounded border-border">
                                    Verkehrsarm
                                </label>
                                <label class="flex items-center gap-3 rounded-xl border border-border bg-inputBg p-3 text-sm font-semibold text-primary">
                                    <input v-model="routeGeneratorForm.lit" type="checkbox" class="rounded border-border">
                                    Beleuchtet
                                </label>
                                <label class="flex items-center gap-3 rounded-xl border border-border bg-inputBg p-3 text-sm font-semibold text-primary">
                                    <input v-model="routeGeneratorForm.water_breaks" type="checkbox" class="rounded border-border">
                                    Trinkpunkte
                                </label>
                            </div>

                            <div class="grid gap-3 sm:grid-cols-2">
                                <input v-model="routeGeneratorForm.include_places" class="rounded-lg border border-border bg-inputBg px-3 py-2 text-sm" placeholder="Lieblingsorte optional">
                                <input v-model="routeGeneratorForm.avoid_places" class="rounded-lg border border-border bg-inputBg px-3 py-2 text-sm" placeholder="Orte vermeiden optional">
                            </div>
                        </div>
                    </div>

                    <div v-else class="grid gap-5 lg:grid-cols-[minmax(0,0.85fr),minmax(0,1.15fr)]">
                        <div class="space-y-4">
                            <div>
                                <h3 class="text-lg font-bold text-primary">Vorschlag prüfen</h3>
                                <p class="mt-1 text-sm leading-6 text-secondary">Der Vorschlag erscheint auf der Karte. Danach kannst du ihn in die normale Routenplanung übernehmen.</p>
                            </div>

                            <div class="grid grid-cols-2 gap-2 text-sm sm:grid-cols-4">
                                <div class="rounded-xl border border-border bg-inputBg p-3">
                                    <p class="text-xs font-semibold text-secondary">{{ routeGeneratorSummary.distanceLabel }}</p>
                                    <p class="mt-1 font-bold text-primary">{{ routeGeneratorSummary.distance }}</p>
                                </div>
                                <div class="rounded-xl border border-border bg-inputBg p-3">
                                    <p class="text-xs font-semibold text-secondary">{{ routeGeneratorSummary.durationLabel }}</p>
                                    <p class="mt-1 font-bold text-primary">{{ routeGeneratorSummary.duration }}</p>
                                </div>
                                <div class="rounded-xl border border-border bg-inputBg p-3">
                                    <p class="text-xs font-semibold text-secondary">Untergrund</p>
                                    <p class="mt-1 font-bold text-primary">{{ routeGeneratorSummary.surface }}</p>
                                </div>
                                <div class="rounded-xl border border-border bg-inputBg p-3">
                                    <p class="text-xs font-semibold text-secondary">Tempo</p>
                                    <p class="mt-1 font-bold text-primary">{{ routeGeneratorSummary.pace }}</p>
                                </div>
                            </div>

                            <div v-if="generatedRouteActualSummary" class="grid grid-cols-2 gap-2 text-sm sm:grid-cols-5">
                                <div class="rounded-xl border border-air-blue/40 bg-air-blue/10 p-3">
                                    <p class="text-xs font-semibold text-secondary">Berechnet</p>
                                    <p class="mt-1 font-bold text-primary">{{ generatedRouteActualSummary.distance }}</p>
                                </div>
                                <div class="rounded-xl border border-air-blue/40 bg-air-blue/10 p-3">
                                    <p class="text-xs font-semibold text-secondary">Zeit</p>
                                    <p class="mt-1 font-bold text-primary">{{ generatedRouteActualSummary.duration }}</p>
                                </div>
                                <div class="rounded-xl border border-air-blue/40 bg-air-blue/10 p-3">
                                    <p class="text-xs font-semibold text-secondary">Routing</p>
                                    <p class="mt-1 font-bold text-primary">{{ generatedRouteActualSummary.status }}</p>
                                </div>
                                <div class="rounded-xl border p-3" :class="qualityBadgeClass(generatedRouteActualSummary.qualityScore)">
                                    <p class="text-xs font-semibold opacity-80">Qualität</p>
                                    <p class="mt-1 font-bold">{{ generatedRouteActualSummary.qualityScore ?? '-' }}%</p>
                                </div>
                                <div class="rounded-xl border border-air-blue/40 bg-air-blue/10 p-3">
                                    <p class="text-xs font-semibold text-secondary">Wegpunkte</p>
                                    <p class="mt-1 font-bold text-primary">{{ generatedRouteActualSummary.geometryPoints }}</p>
                                </div>
                            </div>

                            <div v-if="generatedRouteActualSummary" class="rounded-xl border border-border bg-inputBg p-3 text-sm">
                                <div class="grid gap-2 sm:grid-cols-3">
                                    <div>
                                        <p class="text-xs font-semibold text-secondary">Abweichung vom Ziel</p>
                                        <p class="mt-1 font-bold text-primary">{{ generatedRouteActualSummary.targetDelta }}</p>
                                    </div>
                                    <div>
                                        <p class="text-xs font-semibold text-secondary">Rücklauf</p>
                                        <p class="mt-1 font-bold text-primary">{{ formatPercent(generatedRouteActualSummary.backtrackPercent) }}</p>
                                    </div>
                                    <div>
                                        <p class="text-xs font-semibold text-secondary">Routenform</p>
                                        <p class="mt-1 font-bold" :class="generatedRouteActualSummary.shapeAcceptable ? 'text-emerald-400' : 'text-amber-400'">
                                            {{ generatedRouteActualSummary.shapeAcceptable ? 'passt' : 'prüfen' }}
                                        </p>
                                    </div>
                                </div>
                            </div>

                            <div class="flex flex-wrap gap-2">
                                <button type="button" class="inline-flex min-h-11 items-center justify-center gap-2 rounded-lg bg-buttonPrimary px-4 py-2 text-sm font-semibold text-buttonTextPrimary disabled:cursor-not-allowed disabled:opacity-60" :disabled="isGeneratingRoute || !routeGenerationAccess.available" @click="generateRouteProposal">
                                    <i class="las la-magic"></i>
                                    {{ isGeneratingRoute ? 'Berechnet...' : 'Vorschlag generieren' }}
                                </button>
                                <button type="button" class="inline-flex min-h-11 items-center justify-center gap-2 rounded-lg border border-border px-4 py-2 text-sm font-semibold hover:bg-muted disabled:cursor-not-allowed disabled:opacity-60" :disabled="generatedRoutePoints.length < 2 || isGeneratingRoute" @click="applyGeneratedRouteToPlanner">
                                    <i class="las la-route"></i>
                                    In Routenplanung übernehmen
                                </button>
                            </div>

                            <p v-if="routeGeneratorStatus" class="rounded-lg bg-air-blue/10 px-3 py-2 text-sm font-semibold text-air-blue">
                                {{ routeGeneratorStatus }}
                            </p>
                        </div>

                        <div class="rounded-xl border border-border bg-inputBg p-4">
                            <div class="flex items-center justify-between gap-3">
                                <div>
                                    <p class="text-sm font-bold text-primary">{{ generatedRouteTitle() }}</p>
                                    <p class="mt-1 text-xs text-secondary">{{ routeGeneratorSummary.environment }} - {{ routeGeneratorSummary.elevation }} - {{ routeGeneratorSummary.difficulty }}</p>
                                </div>
                                <span class="rounded-full bg-card px-3 py-1 text-xs font-semibold text-primary">Vorschlag bereit</span>
                            </div>

                            <div class="mt-4 space-y-2">
                                <div
                                    v-for="(point, index) in generatedRoutePoints"
                                    :key="`${point.latitude}-${point.longitude}-${index}`"
                                    class="flex items-center justify-between gap-3 rounded-lg border border-border bg-card px-3 py-2 text-sm"
                                >
                                    <div>
                                        <p class="font-semibold text-primary">{{ point.name }}</p>
                                        <p class="text-xs text-secondary">{{ Number(point.latitude).toFixed(5) }}, {{ Number(point.longitude).toFixed(5) }}</p>
                                    </div>
                                    <button v-if="point.removable" type="button" class="text-red-400 hover:text-red-300" aria-label="Punkt entfernen" @click="removeGeneratedRoutePoint(index)">
                                        <i class="las la-times"></i>
                                    </button>
                                </div>

                                <p v-if="!generatedRoutePoints.length" class="rounded-lg border border-dashed border-border px-3 py-6 text-center text-sm text-secondary">
                                    Noch kein Vorschlag. Klicke auf "Vorschlag generieren".
                                </p>
                            </div>

                            <div v-if="generatedRouteCuePreview.length" class="mt-5 rounded-xl border border-border bg-card p-3">
                                <div class="flex items-center justify-between gap-3">
                                    <p class="text-sm font-bold text-primary">Abbiegehinweise</p>
                                    <span class="rounded-full bg-inputBg px-2 py-1 text-xs font-semibold text-secondary">{{ generatedRouteNavigationCues.length }}</span>
                                </div>
                                <ol class="mt-3 space-y-2">
                                    <li
                                        v-for="(cue, index) in generatedRouteCuePreview"
                                        :key="`${cue.type || cue.maneuver_type}-${index}`"
                                        class="flex gap-2 rounded-lg border border-border bg-inputBg px-3 py-2 text-xs text-secondary"
                                    >
                                        <span class="flex h-5 w-5 shrink-0 items-center justify-center rounded-full bg-air-blue/15 text-[10px] font-bold text-air-blue">{{ index + 1 }}</span>
                                        <span>{{ cueText(cue, index) }}</span>
                                    </li>
                                </ol>
                            </div>
                        </div>
                    </div>

                    <div class="flex flex-wrap justify-between gap-2 border-t border-border pt-4">
                        <button type="button" class="rounded-lg border border-border px-4 py-2 text-sm font-semibold hover:bg-muted" :disabled="routeGeneratorStep === 1" @click="setGeneratorStep(routeGeneratorStep - 1)">
                            Zurück
                        </button>
                        <div class="flex flex-wrap gap-2">
                            <button type="button" class="rounded-lg border border-border px-4 py-2 text-sm font-semibold hover:bg-muted" @click="resetGeneratedRoute">
                                Zurücksetzen
                            </button>
                            <button v-if="routeGeneratorStep < 3" type="button" class="rounded-lg bg-buttonPrimary px-4 py-2 text-sm font-semibold text-buttonTextPrimary" @click="setGeneratorStep(routeGeneratorStep + 1)">
                                Weiter
                            </button>
                            <button v-else type="button" class="rounded-lg bg-buttonPrimary px-4 py-2 text-sm font-semibold text-buttonTextPrimary disabled:cursor-not-allowed disabled:opacity-60" :disabled="isGeneratingRoute || !routeGenerationAccess.available" @click="generateRouteProposal">
                                {{ isGeneratingRoute ? 'Berechnet...' : 'Neu generieren' }}
                            </button>
                        </div>
                    </div>
                </div>

                <div v-else-if="activeTab === 'routes'" class="grid gap-5 p-4 lg:grid-cols-[minmax(0,0.9fr),minmax(0,1.1fr)]">
                    <form class="space-y-4" @submit.prevent="saveRoute">
                        <div>
                            <h3 class="text-lg font-bold text-primary">{{ $t('sport_map.routes.create_title') }}</h3>
                            <p class="mt-1 text-sm text-secondary">{{ $t('sport_map.routes.create_hint') }}</p>
                        </div>

                        <div class="grid gap-3 sm:grid-cols-2">
                            <label class="space-y-1">
                                <span class="text-xs font-semibold text-secondary">{{ $t('sport_map.form.title') }}</span>
                                <input v-model="routeForm.title" required class="w-full rounded-lg border border-border bg-inputBg px-3 py-2 text-sm">
                            </label>
                            <label class="space-y-1">
                                <span class="text-xs font-semibold text-secondary">{{ $t('sport_map.form.sport') }}</span>
                                <select v-model="routeForm.sport_type" class="w-full rounded-lg border border-border bg-inputBg px-3 py-2 text-sm">
                                    <option v-for="sport in sportTypes" :key="sport.key" :value="sport.key">{{ catalogLabel(sport, sport.key) }}</option>
                                </select>
                            </label>
                            <label class="space-y-1">
                                <span class="text-xs font-semibold text-secondary">{{ $t('sport_map.form.visibility') }}</span>
                                <select v-model="routeForm.visibility" class="w-full rounded-lg border border-border bg-inputBg px-3 py-2 text-sm">
                                    <option value="private">{{ $t('sport_map.visibility.private') }}</option>
                                    <option value="public">{{ $t('sport_map.visibility.public') }}</option>
                                    <option value="team">{{ $t('sport_map.visibility.team') }}</option>
                                </select>
                            </label>
                            <label v-if="routeForm.visibility === 'team'" class="space-y-1">
                                <span class="text-xs font-semibold text-secondary">{{ $t('sport_map.form.team') }}</span>
                                <select v-model="routeForm.team_id" class="w-full rounded-lg border border-border bg-inputBg px-3 py-2 text-sm">
                                    <option :value="null">-</option>
                                    <option v-for="team in teams" :key="team.id" :value="team.id">{{ team.name }}</option>
                                </select>
                            </label>
                        </div>

                        <label class="space-y-1">
                            <span class="text-xs font-semibold text-secondary">{{ $t('sport_map.form.description') }}</span>
                            <textarea v-model="routeForm.description" rows="3" class="w-full rounded-lg border border-border bg-inputBg px-3 py-2 text-sm"></textarea>
                        </label>

                        <div class="space-y-2">
                            <div class="flex items-center justify-between gap-3">
                                <p class="text-sm font-semibold text-primary">{{ $t('sport_map.routes.waypoints') }}</p>
                                <button type="button" class="rounded-lg border border-border px-3 py-2 text-xs font-semibold hover:bg-muted" @click="addWaypoint">
                                    <i class="las la-plus"></i>
                                    {{ $t('sport_map.routes.add_waypoint') }}
                                </button>
                            </div>

                            <div v-for="(point, index) in waypointRows" :key="index" class="grid gap-2 rounded-lg border border-border bg-inputBg p-3 sm:grid-cols-[1fr,1fr,1fr,auto]">
                                <input v-model="point.name" class="rounded-lg border border-border bg-card px-3 py-2 text-sm" :placeholder="$t('sport_map.form.name')">
                                <input v-model="point.latitude" type="number" step="0.0000001" class="rounded-lg border border-border bg-card px-3 py-2 text-sm" :placeholder="$t('sport_map.form.latitude')">
                                <input v-model="point.longitude" type="number" step="0.0000001" class="rounded-lg border border-border bg-card px-3 py-2 text-sm" :placeholder="$t('sport_map.form.longitude')">
                                <button type="button" class="rounded-lg border border-border px-3 py-2 text-sm hover:bg-muted" @click="removeWaypoint(index)">
                                    <i class="las la-trash"></i>
                                </button>
                            </div>
                        </div>

                        <button type="submit" class="inline-flex min-h-11 items-center justify-center gap-2 rounded-lg bg-buttonPrimary px-4 py-2 text-sm font-semibold text-buttonTextPrimary disabled:opacity-50" :disabled="routeForm.processing || cleanedWaypoints().length < 2">
                            <i class="las la-save"></i>
                            {{ $t('sport_map.routes.save') }}
                        </button>
                    </form>

                    <div>
                        <h3 class="text-lg font-bold text-primary">{{ $t('sport_map.routes.saved_title') }}</h3>
                        <div class="mt-3 grid gap-3">
                            <article v-for="routeItem in routes" :key="routeItem.id" class="rounded-lg border border-border bg-inputBg p-4">
                                <div class="flex items-start justify-between gap-3">
                                    <div class="min-w-0">
                                        <p class="truncate text-sm font-bold text-primary">{{ routeItem.title }}</p>
                                        <p class="mt-1 text-xs text-secondary">
                                            {{ sportLabel(routeItem.sport_type) }} - {{ visibilityLabel(routeItem.visibility) }} - {{ $t('sport_map.routes.cues_count', { count: routeItem.navigation_cues?.length || 0 }) }}
                                        </p>
                                    </div>
                                    <span class="shrink-0 rounded-full bg-card px-3 py-1 text-xs font-semibold text-primary">
                                        {{ formatDistance(routeItem.distance_meters) }}
                                    </span>
                                </div>
                                <div class="mt-3 grid grid-cols-3 gap-2 text-xs text-secondary">
                                    <span>{{ formatDuration(routeItem.estimated_duration_seconds) }}</span>
                                    <span>{{ $t('sport_map.elevation_gain_meters', { meters: routeItem.elevation_gain_meters || 0 }) }}</span>
                                    <span>{{ $t('sport_map.routes.tracks_count', { count: routeItem.tracks_count || 0 }) }}</span>
                                </div>
                            </article>
                        </div>
                    </div>
                </div>

                <div v-else-if="activeTab === 'tracks'" class="grid gap-4 p-3 sm:p-4 lg:grid-cols-[minmax(0,0.95fr),minmax(0,1.05fr)]">
                    <div class="space-y-4">
                        <div v-if="false" class="fixed inset-0 z-[9999] flex flex-col bg-[#07101c] px-4 py-5 text-primary sm:hidden">
                            <div class="flex items-center justify-between gap-3">
                                <div>
                                    <p class="text-xs font-black uppercase tracking-wide text-air-blue">Airmius Track</p>
                                    <h2 class="text-xl font-black text-primary">Live-Tracking</h2>
                                </div>
                                <div class="flex items-center gap-2">
                                    <span
                                        class="rounded-full border px-3 py-1 text-xs font-bold"
                                        :class="isTracking ? 'border-emerald-400/40 bg-emerald-500/15 text-emerald-300' : 'border-border bg-card text-secondary'"
                                    >
                                        {{ trackingLiveStatusLabel }}
                                    </span>
                                    <button type="button" class="inline-flex h-11 w-11 items-center justify-center rounded-full border border-border bg-card text-primary" aria-label="Tracking schließen" @click="closeTrackingFullscreen">
                                        <i class="las la-times text-xl"></i>
                                    </button>
                                </div>
                            </div>

                            <div class="mt-5 flex-1 overflow-hidden rounded-[2rem] border border-white/10 bg-slate-950 shadow-2xl">
                                <div
                                    class="flex h-full touch-pan-y transition-transform duration-300 ease-out"
                                    :style="{ transform: `translateX(-${activeTrackingSlide * 100}%)` }"
                                    @touchstart.passive="startTrackingSwipe"
                                    @touchend="endTrackingSwipe"
                                    @pointerdown="startTrackingPointerSwipe"
                                    @pointerup="endTrackingPointerSwipe"
                                    @pointercancel="cancelTrackingPointerSwipe"
                                >
                                    <article class="flex min-w-full flex-col justify-between p-5">
                                        <div class="text-center">
                                            <p class="text-xs font-black uppercase tracking-[0.22em] text-secondary">{{ $t('sport_map.tracks.distance') }}</p>
                                            <p class="mt-5 text-7xl font-black leading-none text-primary">{{ formatDistance(trackingDistance) }}</p>
                                            <p class="mt-4 text-lg font-black text-secondary">{{ trackingElapsedLabel }}</p>
                                        </div>
                                        <div class="grid gap-3">
                                            <button v-if="!isTracking" type="button" class="inline-flex min-h-16 w-full items-center justify-center gap-3 rounded-full bg-buttonPrimary px-5 text-base font-black text-buttonTextPrimary shadow-lg" @click="startTracking">
                                                <i class="las la-play text-2xl"></i>
                                                {{ trackingPoints.length ? 'Weiter' : 'Starten' }}
                                            </button>
                                            <button v-else type="button" class="inline-flex min-h-16 w-full items-center justify-center gap-3 rounded-full border border-amber-300/40 bg-amber-400/10 px-5 text-base font-black text-amber-200" @click="stopTracking">
                                                <i class="las la-pause text-2xl"></i>
                                                Pause
                                            </button>
                                            <div v-if="trackingPoints.length" class="grid grid-cols-2 gap-3">
                                                <button type="button" class="inline-flex min-h-13 items-center justify-center gap-2 rounded-full border border-emerald-400/40 bg-emerald-500/15 px-4 text-sm font-black text-emerald-200" :disabled="trackForm.processing" @click="saveTrack">
                                                    <i class="las la-save"></i>
                                                    Speichern
                                                </button>
                                                <button type="button" class="inline-flex min-h-13 items-center justify-center gap-2 rounded-full border border-red-400/40 bg-red-500/10 px-4 text-sm font-black text-red-300" @click="deleteCurrentTrackDraft">
                                                    <i class="las la-trash"></i>
                                                    Löschen
                                                </button>
                                            </div>
                                        </div>
                                    </article>

                                    <article class="min-w-full overflow-hidden bg-card">
                                        <div class="flex items-center justify-between gap-3 px-5 py-4">
                                            <div>
                                                <p class="text-xs font-black uppercase tracking-wide text-air-blue">Karte</p>
                                                <h3 class="text-lg font-black text-primary">Position</h3>
                                            </div>
                                            <span class="rounded-full bg-inputBg px-3 py-1 text-xs font-bold text-secondary">Live</span>
                                        </div>
                                        <div class="relative h-[calc(100%-76px)] select-none overflow-hidden" style="background-color: #efe6d1; user-select: none; -webkit-user-select: none;" @click="handleMapClick" @selectstart.prevent @dragstart.prevent>
                                            <div class="absolute inset-0 z-0" style="background-color: #efe6d1;"></div>
                                            <div class="pointer-events-none absolute inset-0 z-0 opacity-80" style="background-image: linear-gradient(90deg, rgba(120,113,108,.16) 1px, transparent 1px), linear-gradient(0deg, rgba(120,113,108,.14) 1px, transparent 1px); background-size: 56px 56px;"></div>
                                            <img v-for="tile in mapTiles" :key="`fullscreen-track-${tile.key}`" :src="tile.url" :style="tile.style" class="pointer-events-none absolute z-[6] max-w-none select-none" alt="" aria-hidden="true" decoding="async" loading="eager" draggable="false" @dragstart.prevent>
                                            <img v-for="tile in mapOverlayTiles" :key="`fullscreen-track-overlay-${tile.key}`" :src="tile.url" :style="tile.style" class="pointer-events-none absolute z-[7] max-w-none select-none" alt="" aria-hidden="true" decoding="async" loading="eager" draggable="false" @dragstart.prevent>
                                            <svg class="absolute inset-0 z-20 h-full w-full" viewBox="0 0 100 100" preserveAspectRatio="none">
                                                <polyline v-if="routePolyline" :points="routePolyline" fill="none" stroke="rgba(255,255,255,.92)" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round" />
                                                <polyline v-if="routePolyline" :points="routePolyline" fill="none" stroke="rgb(59,130,246)" stroke-width="1.3" stroke-linecap="round" stroke-linejoin="round" />
                                                <polyline v-if="trackPolyline" :points="trackPolyline" fill="none" stroke="rgb(34,197,94)" stroke-width="1.1" stroke-dasharray="2 1" stroke-linecap="round" stroke-linejoin="round" />
                                            </svg>
                                            <button type="button" class="absolute left-4 top-4 z-[80] inline-flex h-12 w-12 items-center justify-center rounded-full border border-white/70 bg-white/95 text-blue-600 shadow-lg" aria-label="Mein Standort anzeigen" @click.stop.prevent="showCurrentLocationOnMap">
                                                <i class="las la-location-arrow text-xl"></i>
                                            </button>
                                        </div>
                                    </article>

                                    <article class="flex min-w-full flex-col p-5">
                                        <p class="text-xs font-black uppercase tracking-wide text-air-blue">Analyse</p>
                                        <h3 class="mt-1 text-2xl font-black text-primary">Live-Werte</h3>
                                        <div class="mt-5 grid flex-1 grid-cols-2 gap-3">
                                            <div class="rounded-3xl border border-border bg-inputBg p-4">
                                                <p class="text-xs font-bold uppercase text-secondary">Pace</p>
                                                <p class="mt-2 text-3xl font-black text-primary">{{ trackingAveragePaceLabel }}</p>
                                            </div>
                                            <div class="rounded-3xl border border-border bg-inputBg p-4">
                                                <p class="text-xs font-bold uppercase text-secondary">km/h</p>
                                                <p class="mt-2 text-3xl font-black text-primary">{{ trackingAverageSpeedLabel }}</p>
                                            </div>
                                            <div class="rounded-3xl border border-border bg-inputBg p-4">
                                                <p class="text-xs font-bold uppercase text-secondary">Zeit</p>
                                                <p class="mt-2 text-3xl font-black text-primary">{{ trackingElapsedLabel }}</p>
                                            </div>
                                            <div class="rounded-3xl border border-border bg-inputBg p-4">
                                                <p class="text-xs font-bold uppercase text-secondary">GPS</p>
                                                <p class="mt-2 text-lg font-black text-primary">{{ trackingLastAccuracyLabel }}</p>
                                            </div>
                                        </div>
                                    </article>
                                </div>
                            </div>

                            <div class="mt-4 flex items-center justify-between gap-3">
                                <button type="button" class="inline-flex h-11 w-11 items-center justify-center rounded-full border border-border bg-card text-primary disabled:opacity-40" :disabled="activeTrackingSlide === 0" @click="previousTrackingSlide">
                                    <i class="las la-arrow-left"></i>
                                </button>
                                <div class="flex items-center gap-2">
                                    <button v-for="index in 3" :key="index" type="button" class="h-2.5 rounded-full transition-all" :class="activeTrackingSlide === index - 1 ? 'w-8 bg-air-blue' : 'w-2.5 bg-secondary/40'" @click="setTrackingSlide(index - 1)"></button>
                                </div>
                                <button type="button" class="inline-flex h-11 w-11 items-center justify-center rounded-full border border-border bg-card text-primary disabled:opacity-40" :disabled="activeTrackingSlide === 2" @click="nextTrackingSlide">
                                    <i class="las la-arrow-right"></i>
                                </button>
                            </div>
                        </div>

                        <section
                            class="overflow-hidden rounded-2xl border border-air-blue/30 bg-gradient-to-br from-air-blue/20 via-inputBg to-emerald-500/10 p-3 shadow-sm sm:p-4"
                        >
                            <div class="flex items-start justify-between gap-3">
                                <div>
                                    <p class="text-xs font-bold uppercase tracking-wide text-air-blue">Live-Tracking</p>
                                    <h3 class="mt-1 text-lg font-bold text-primary sm:text-xl">Strecke aufzeichnen</h3>
                                    <p class="mt-1 hidden text-sm leading-5 text-secondary sm:block">Starten, Handy einstecken und nach dem Training speichern.</p>
                                </div>
                                <span
                                    class="shrink-0 rounded-full border px-3 py-1 text-xs font-bold"
                                    :class="isTracking ? 'border-emerald-400/40 bg-emerald-500/15 text-emerald-300' : 'border-border bg-card text-secondary'"
                                >
                                    {{ trackingLiveStatusLabel }}
                                </span>
                                <button
                                    v-if="trackingFullscreen"
                                    type="button"
                                    class="inline-flex h-9 w-9 shrink-0 items-center justify-center rounded-full border border-border bg-card text-primary sm:hidden"
                                    aria-label="Tracking Vollbild schließen"
                                    @click="closeTrackingFullscreen"
                                >
                                    <i class="las la-times text-lg"></i>
                                </button>
                            </div>

                            <div class="mt-4 lg:hidden">
                                <div class="overflow-hidden rounded-[1.75rem] border border-white/10 bg-slate-950/60 shadow-2xl">
                                    <div
                                        class="flex touch-pan-y transition-transform duration-300 ease-out"
                                        :style="{ transform: `translateX(-${activeTrackingSlide * 100}%)` }"
                                        @touchstart.passive="startTrackingSwipe"
                                        @touchend="endTrackingSwipe"
                                        @pointerdown="startTrackingPointerSwipe"
                                        @pointerup="endTrackingPointerSwipe"
                                        @pointercancel="cancelTrackingPointerSwipe"
                                    >
                                <article class="flex min-h-[380px] min-w-full flex-col justify-between p-5">
                                    <div>
                                        <div class="flex items-center justify-between gap-3">
                                            <span class="rounded-full bg-air-blue/15 px-3 py-1 text-xs font-black uppercase tracking-wide text-air-blue">Airmius Track</span>
                                            <span class="rounded-full border border-border bg-card px-3 py-1 text-xs font-bold text-secondary">{{ trackingLiveStatusLabel }}</span>
                                        </div>
                                    <div class="mt-8 text-center">
                                        <p class="text-xs font-bold uppercase text-secondary">{{ $t('sport_map.tracks.distance') }}</p>
                                        <p class="mt-3 text-6xl font-black leading-none text-primary">{{ formatDistance(trackingDistance) }}</p>
                                        <p class="mt-2 text-sm font-bold text-secondary">{{ trackingElapsedLabel }} • {{ trackingLiveStatusLabel }}</p>
                                    </div>
                                    </div>
                                    <div class="mt-5 grid gap-2" :class="trackingPoints.length ? 'grid-cols-2' : 'grid-cols-1'">
                                        <button v-if="!isTracking" type="button" class="inline-flex min-h-14 items-center justify-center gap-2 rounded-2xl bg-buttonPrimary px-4 py-3 text-sm font-black text-buttonTextPrimary shadow-sm" @click="startTrackingFromMobile">
                                            <i class="las la-play text-xl"></i>
                                            {{ trackingPoints.length ? 'Weiter' : 'Starten' }}
                                        </button>
                                        <button v-else type="button" class="inline-flex min-h-14 items-center justify-center gap-2 rounded-2xl border border-amber-300/40 bg-amber-400/10 px-4 py-3 text-sm font-black text-amber-200" @click="stopTracking">
                                            <i class="las la-pause text-xl"></i>
                                            Pause
                                        </button>
                                        <button v-if="trackingPoints.length" type="button" class="inline-flex min-h-14 items-center justify-center gap-2 rounded-2xl border border-emerald-400/40 bg-emerald-500/15 px-4 py-3 text-sm font-black text-emerald-200 disabled:opacity-50" :disabled="trackForm.processing || trackingPoints.length < 1" @click="saveTrack">
                                            <i class="las la-save text-xl"></i>
                                            Speichern
                                        </button>
                                        <button v-if="trackingPoints.length" type="button" class="col-span-2 inline-flex min-h-11 items-center justify-center gap-2 rounded-2xl border border-red-400/40 bg-red-500/10 px-4 py-2 text-sm font-black text-red-300" @click="deleteCurrentTrackDraft">
                                            <i class="las la-trash"></i>
                                            Lauf löschen
                                        </button>
                                    </div>
                                    <p class="mt-4 text-center text-xs font-semibold text-secondary">Nach links swipen für Karte</p>
                                </article>

                                <article class="min-h-[380px] min-w-full overflow-hidden bg-card">
                                    <div class="flex items-center justify-between gap-3 border-b border-border px-4 py-3">
                                        <div>
                                            <p class="text-xs font-bold uppercase tracking-wide text-air-blue">Slide 2</p>
                                            <h4 class="text-base font-black text-primary">Karte</h4>
                                        </div>
                                        <span class="rounded-full bg-inputBg px-3 py-1 text-xs font-bold text-secondary">Karte</span>
                                    </div>
                                    <div
                                        class="relative h-72 touch-pan-x select-none overflow-hidden"
                                        style="background-color: #efe6d1; user-select: none; -webkit-user-select: none;"
                                        @click="handleMapClick"
                                        @selectstart.prevent
                                        @dragstart.prevent
                                    >
                                        <div class="absolute inset-0 z-0" style="background-color: #efe6d1;"></div>
                                        <div class="pointer-events-none absolute inset-0 z-0 opacity-80" style="background-image: linear-gradient(90deg, rgba(120,113,108,.16) 1px, transparent 1px), linear-gradient(0deg, rgba(120,113,108,.14) 1px, transparent 1px); background-size: 56px 56px;"></div>
                                        <img
                                            v-for="tile in mapTiles"
                                            :key="`mobile-track-${tile.key}`"
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
                                            :key="`mobile-track-overlay-${tile.key}`"
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
                                            class="absolute z-40 flex h-8 w-8 -translate-x-1/2 -translate-y-1/2 items-center justify-center rounded-full border-2 border-white bg-air-blue text-white shadow-lg"
                                            :style="markerStyle(currentLocationPoint)"
                                        >
                                            <span class="absolute h-11 w-11 rounded-full bg-air-blue/25"></span>
                                            <i class="las la-location-arrow relative text-base"></i>
                                        </div>
                                        <button type="button" class="absolute left-3 top-3 z-[80] inline-flex h-11 w-11 items-center justify-center rounded-2xl border border-white/70 bg-white/95 text-blue-600 shadow-lg" aria-label="Mein Standort anzeigen" @click.stop.prevent="showCurrentLocationOnMap">
                                            <i class="las la-location-arrow text-lg"></i>
                                        </button>
                                    </div>
                                    <p class="px-4 py-3 text-center text-xs font-semibold text-secondary">Nochmals swipen für Pace und km/h</p>
                                </article>

                                <article class="flex min-h-[380px] min-w-full flex-col p-5">
                                    <p class="text-xs font-bold uppercase tracking-wide text-air-blue">Slide 3</p>
                                    <h4 class="mt-1 text-lg font-black text-primary">Live-Werte</h4>
                                    <div class="mt-4 grid grid-cols-2 gap-3">
                                        <div class="rounded-2xl border border-border bg-inputBg p-4">
                                            <p class="text-xs font-bold uppercase text-secondary">Pace</p>
                                            <p class="mt-1 text-2xl font-black text-primary">{{ trackingAveragePaceLabel }}</p>
                                        </div>
                                        <div class="rounded-2xl border border-border bg-inputBg p-4">
                                            <p class="text-xs font-bold uppercase text-secondary">km/h</p>
                                            <p class="mt-1 text-2xl font-black text-primary">{{ trackingAverageSpeedLabel }}</p>
                                        </div>
                                        <div class="rounded-2xl border border-border bg-inputBg p-4">
                                            <p class="text-xs font-bold uppercase text-secondary">Zeit</p>
                                            <p class="mt-1 text-2xl font-black text-primary">{{ trackingElapsedLabel }}</p>
                                        </div>
                                        <div class="rounded-2xl border border-border bg-inputBg p-4">
                                            <p class="text-xs font-bold uppercase text-secondary">GPS</p>
                                            <p class="mt-1 text-sm font-black text-primary">{{ trackingLastAccuracyLabel }}</p>
                                        </div>
                                    </div>
                                </article>
                            </div>

                                    </div>

                                <div class="mt-4 flex items-center justify-between gap-3">
                                    <button type="button" class="inline-flex h-11 w-11 items-center justify-center rounded-full border border-border bg-card text-primary disabled:opacity-40" :disabled="activeTrackingSlide === 0" @click="previousTrackingSlide">
                                        <i class="las la-arrow-left"></i>
                                    </button>
                                    <div class="flex items-center gap-2">
                                        <button
                                            v-for="index in 3"
                                            :key="index"
                                            type="button"
                                            class="h-2.5 rounded-full transition-all"
                                            :class="activeTrackingSlide === index - 1 ? 'w-8 bg-air-blue' : 'w-2.5 bg-secondary/40'"
                                            @click="setTrackingSlide(index - 1)"
                                        ></button>
                                    </div>
                                    <button type="button" class="inline-flex h-11 w-11 items-center justify-center rounded-full border border-border bg-card text-primary disabled:opacity-40" :disabled="activeTrackingSlide === 2" @click="nextTrackingSlide">
                                        <i class="las la-arrow-right"></i>
                                    </button>
                                </div>
                            </div>

                            <div class="mt-4 hidden rounded-2xl border border-white/10 bg-card/80 p-4 lg:block">
                                <div class="flex items-end justify-between gap-3">
                                    <div>
                                        <p class="text-xs font-bold uppercase tracking-wide text-secondary">{{ $t('sport_map.tracks.distance') }}</p>
                                        <p class="mt-2 text-4xl font-black leading-none text-primary">{{ formatDistance(trackingDistance) }}</p>
                                    </div>
                                    <p class="pb-1 text-sm font-bold text-secondary">{{ trackingElapsedLabel }}</p>
                                </div>
                            </div>

                            <div class="-mx-3 mt-3 hidden snap-x gap-3 overflow-x-auto px-3 pb-1 sm:mx-0 lg:grid lg:grid-cols-3 lg:overflow-visible lg:px-0">
                                <div class="min-w-[128px] snap-start rounded-2xl border border-border bg-card/80 p-3">
                                    <p class="text-xs font-bold uppercase text-secondary">Pace</p>
                                    <p class="mt-1 text-xl font-bold text-primary">{{ trackingAveragePaceLabel }}</p>
                                </div>
                                <div class="min-w-[128px] snap-start rounded-2xl border border-border bg-card/80 p-3">
                                    <p class="text-xs font-bold uppercase text-secondary">km/h</p>
                                    <p class="mt-1 text-xl font-bold text-primary">{{ trackingAverageSpeedLabel }}</p>
                                </div>
                                <div class="min-w-[128px] snap-start rounded-2xl border border-border bg-card/80 p-3">
                                    <p class="text-xs font-bold uppercase text-secondary">GPS</p>
                                    <p class="mt-1 truncate text-sm font-bold text-primary">{{ trackingLastAccuracyLabel }}</p>
                                </div>
                            </div>

                            <p v-if="trackingError" class="mt-3 rounded-xl bg-red-500/10 px-3 py-2 text-sm font-semibold text-red-500">
                                {{ trackingError }}
                            </p>

                            <div class="mt-4 hidden gap-2 lg:grid" :class="trackingPoints.length ? 'grid-cols-2' : 'grid-cols-1'">
                                <button v-if="!isTracking" type="button" class="inline-flex min-h-12 items-center justify-center gap-2 rounded-xl bg-buttonPrimary px-4 py-3 text-sm font-bold text-buttonTextPrimary shadow-sm" @click="startTracking">
                                    <i class="las la-play text-lg"></i>
                                    {{ trackingPoints.length ? 'Weiter tracken' : 'Tracking starten' }}
                                </button>
                                <button v-else type="button" class="inline-flex min-h-12 items-center justify-center gap-2 rounded-xl border border-amber-300/40 bg-amber-400/10 px-4 py-3 text-sm font-bold text-amber-200" @click="stopTracking">
                                    <i class="las la-pause text-lg"></i>
                                    Pause
                                </button>
                                <button v-if="trackingPoints.length" type="button" class="inline-flex min-h-12 items-center justify-center gap-2 rounded-xl border border-emerald-400/40 bg-emerald-500/15 px-4 py-3 text-sm font-bold text-emerald-200 disabled:opacity-50" :disabled="trackForm.processing || trackingPoints.length < 1" @click="saveTrack">
                                    <i class="las la-save text-lg"></i>
                                    Speichern
                                </button>
                                <button v-if="trackingPoints.length && !isTracking" type="button" class="col-span-2 inline-flex min-h-11 items-center justify-center gap-2 rounded-xl border border-border px-4 py-2 text-sm font-bold text-secondary hover:bg-muted" @click="resetTrackPoints">
                                    <i class="las la-trash"></i>
                                    Verwerfen
                                </button>
                            </div>
                        </section>

                        <details class="rounded-2xl border border-border bg-inputBg p-4">
                            <summary class="flex cursor-pointer list-none items-center justify-between gap-3">
                                <span>
                                    <span class="block text-xs font-bold uppercase tracking-wide text-secondary">Vorbereitung</span>
                                    <span class="block text-base font-bold text-primary">Bezeichnung & Sportart</span>
                                </span>
                                <span class="inline-flex h-9 w-9 items-center justify-center rounded-full border border-border bg-card text-secondary">
                                    <i class="las la-sliders-h"></i>
                                </span>
                            </summary>

                            <div class="mt-4 grid gap-3 border-t border-border pt-4">
                                <label class="space-y-1">
                                    <span class="text-xs font-semibold text-secondary">{{ $t('sport_map.form.title') }}</span>
                                    <input v-model="trackForm.title" class="w-full rounded-xl border border-border bg-card px-3 py-3 text-sm" placeholder="z. B. Morgenlauf, 5-km-Runde, Intervalltraining">
                                </label>
                                <label class="space-y-1">
                                    <span class="text-xs font-semibold text-secondary">{{ $t('sport_map.form.sport') }}</span>
                                    <select v-model="trackForm.sport_type" class="w-full rounded-xl border border-border bg-card px-3 py-3 text-sm">
                                        <option v-for="sport in sportTypes" :key="sport.key" :value="sport.key">{{ catalogLabel(sport, sport.key) }}</option>
                                    </select>
                                </label>
                                <label class="space-y-1">
                                    <span class="text-xs font-semibold text-secondary">{{ $t('sport_map.tracks.route_optional') }}</span>
                                    <select v-model="trackForm.sport_route_id" class="w-full rounded-xl border border-border bg-card px-3 py-3 text-sm">
                                        <option :value="null">{{ $t('sport_map.tracks.free_track') }}</option>
                                        <option v-for="routeItem in routes" :key="routeItem.id" :value="routeItem.id">{{ routeItem.title }}</option>
                                    </select>
                                </label>
                            </div>
                        </details>
                    </div>

                    <div class="rounded-2xl border border-border bg-inputBg p-4">
                        <div class="flex items-center justify-between gap-3">
                            <div>
                                <p class="text-xs font-bold uppercase tracking-wide text-secondary">Historie</p>
                                <h3 class="text-lg font-bold text-primary">{{ $t('sport_map.tracks.saved_title') }}</h3>
                            </div>
                            <span class="rounded-full bg-card px-3 py-1 text-xs font-semibold text-secondary">{{ tracks.length }} Tracks</span>
                        </div>

                        <div class="mt-3 grid gap-3">
                            <article v-for="track in tracks" :key="track.id" class="rounded-xl border border-border bg-card p-4">
                                <div v-if="editingTrackId === track.id" class="grid gap-3">
                                    <label class="space-y-1">
                                        <span class="text-xs font-semibold text-secondary">Bezeichnung</span>
                                        <input
                                            v-model="editTrackForm.title"
                                            class="w-full rounded-xl border border-border bg-inputBg px-3 py-3 text-sm font-semibold text-primary"
                                            placeholder="z. B. Morgenlauf, 5-km-Runde"
                                        >
                                        <span v-if="editTrackForm.errors.title" class="text-xs font-semibold text-red-500">{{ editTrackForm.errors.title }}</span>
                                    </label>
                                    <label class="space-y-1">
                                        <span class="text-xs font-semibold text-secondary">Sportart</span>
                                        <select v-model="editTrackForm.sport_type" class="w-full rounded-xl border border-border bg-inputBg px-3 py-3 text-sm font-semibold text-primary">
                                            <option v-for="sport in sportTypes" :key="sport.key" :value="sport.key">{{ catalogLabel(sport, sport.key) }}</option>
                                        </select>
                                        <span v-if="editTrackForm.errors.sport_type" class="text-xs font-semibold text-red-500">{{ editTrackForm.errors.sport_type }}</span>
                                    </label>
                                    <div class="grid grid-cols-2 gap-2">
                                        <button
                                            type="button"
                                            class="inline-flex min-h-11 items-center justify-center rounded-xl bg-buttonPrimary px-4 py-2 text-sm font-bold text-buttonTextPrimary disabled:opacity-50"
                                            :disabled="editTrackForm.processing || !editTrackForm.title"
                                            @click="updateSavedTrack(track)"
                                        >
                                            Speichern
                                        </button>
                                        <button
                                            type="button"
                                            class="inline-flex min-h-11 items-center justify-center rounded-xl border border-border px-4 py-2 text-sm font-bold text-primary"
                                            :disabled="editTrackForm.processing"
                                            @click="cancelTrackEdit"
                                        >
                                            Abbrechen
                                        </button>
                                    </div>
                                </div>

                                <div v-else class="flex items-start justify-between gap-3">
                                    <div class="min-w-0">
                                        <p class="truncate text-sm font-bold text-primary">{{ track.title }}</p>
                                        <p class="mt-1 text-xs text-secondary">{{ sportLabel(track.sport_type) }} - {{ trackStatusLabel(track.status) }}</p>
                                    </div>
                                    <div class="flex shrink-0 items-center gap-2">
                                        <span class="rounded-full bg-inputBg px-3 py-1 text-xs font-semibold text-primary">{{ formatDistance(track.distance_meters) }}</span>
                                        <button
                                            v-if="track.can_edit"
                                            type="button"
                                            class="inline-flex h-9 w-9 items-center justify-center rounded-full border border-border bg-inputBg text-primary hover:bg-muted"
                                            aria-label="Track bearbeiten"
                                            @click="startTrackEdit(track)"
                                        >
                                            <i class="las la-pen"></i>
                                        </button>
                                    </div>
                                </div>
                                <div v-if="editingTrackId !== track.id" class="mt-3 grid grid-cols-1 gap-2 text-xs text-secondary sm:grid-cols-3">
                                    <span class="rounded-lg bg-inputBg px-3 py-2">{{ formatDuration(track.duration_seconds) }}</span>
                                    <span class="rounded-lg bg-inputBg px-3 py-2">{{ trackAveragePaceLabel(track) }}</span>
                                    <span class="rounded-lg bg-inputBg px-3 py-2">{{ trackAverageSpeedLabel(track) }}</span>
                                </div>
                            </article>
                            <div v-if="!tracks.length" class="rounded-xl border border-dashed border-border bg-card/60 p-5 text-center">
                                <p class="text-sm font-bold text-primary">Noch kein Track gespeichert.</p>
                                <p class="mt-1 text-sm text-secondary">Starte dein erstes Tracking direkt links.</p>
                            </div>
                        </div>
                    </div>
                </div>

                <div v-else class="grid gap-5 p-4 lg:grid-cols-[minmax(0,0.9fr),minmax(0,1.1fr)]">
                    <form class="space-y-4" @submit.prevent="savePlace">
                        <div>
                            <h3 class="text-lg font-bold text-primary">{{ $t('sport_map.places.create_title') }}</h3>
                            <p class="mt-1 text-sm text-secondary">{{ $t('sport_map.places.create_hint') }}</p>
                        </div>

                        <div class="grid gap-3 sm:grid-cols-2">
                            <label class="space-y-1">
                                <span class="text-xs font-semibold text-secondary">{{ $t('sport_map.form.name') }}</span>
                                <input v-model="placeForm.name" required class="w-full rounded-lg border border-border bg-inputBg px-3 py-2 text-sm">
                            </label>
                            <label class="space-y-1">
                                <span class="text-xs font-semibold text-secondary">{{ $t('sport_map.places.type') }}</span>
                                <select v-model="placeForm.type" class="w-full rounded-lg border border-border bg-inputBg px-3 py-2 text-sm">
                                    <option v-for="type in placeTypes" :key="type.key" :value="type.key">{{ catalogLabel(type, type.key) }}</option>
                                </select>
                            </label>
                            <label class="space-y-1">
                                <span class="text-xs font-semibold text-secondary">{{ $t('sport_map.form.latitude') }}</span>
                                <input v-model="placeForm.latitude" required type="number" step="0.0000001" class="w-full rounded-lg border border-border bg-inputBg px-3 py-2 text-sm">
                            </label>
                            <label class="space-y-1">
                                <span class="text-xs font-semibold text-secondary">{{ $t('sport_map.form.longitude') }}</span>
                                <input v-model="placeForm.longitude" required type="number" step="0.0000001" class="w-full rounded-lg border border-border bg-inputBg px-3 py-2 text-sm">
                            </label>
                        </div>

                        <div>
                            <button type="button" class="rounded-lg border border-border px-3 py-2 text-sm font-semibold hover:bg-muted" @click="useCurrentLocationForPlace">
                                <i class="las la-crosshairs"></i>
                                {{ $t('sport_map.places.use_location') }}
                            </button>
                            <p v-if="placeLocationStatus" class="mt-2 rounded-lg bg-air-blue/10 px-3 py-2 text-sm font-semibold text-air-blue">
                                {{ placeLocationStatus }}
                            </p>
                            <p v-if="placeLocationError" class="mt-2 rounded-lg bg-red-500/10 px-3 py-2 text-sm text-red-600">
                                {{ placeLocationError }}
                            </p>
                        </div>

                        <label class="space-y-1">
                            <span class="text-xs font-semibold text-secondary">{{ $t('sport_map.form.description') }}</span>
                            <textarea v-model="placeForm.description" rows="3" class="w-full rounded-lg border border-border bg-inputBg px-3 py-2 text-sm"></textarea>
                        </label>

                        <div class="grid gap-3 sm:grid-cols-2">
                            <input v-model="placeForm.city" class="rounded-lg border border-border bg-inputBg px-3 py-2 text-sm" :placeholder="$t('sport_map.places.city')">
                            <input v-model="placeForm.address" class="rounded-lg border border-border bg-inputBg px-3 py-2 text-sm" :placeholder="$t('sport_map.places.address')">
                            <input v-model="placeSportTypesText" class="rounded-lg border border-border bg-inputBg px-3 py-2 text-sm" :placeholder="$t('sport_map.places.sports_placeholder')">
                            <input v-model="placeAmenitiesText" class="rounded-lg border border-border bg-inputBg px-3 py-2 text-sm" :placeholder="$t('sport_map.places.amenities_placeholder')">
                            <input v-model="placeSurfacesText" class="rounded-lg border border-border bg-inputBg px-3 py-2 text-sm" :placeholder="$t('sport_map.places.surfaces_placeholder')">
                            <input v-model="placeForm.opening_hours" class="rounded-lg border border-border bg-inputBg px-3 py-2 text-sm" :placeholder="$t('sport_map.places.opening_hours')">
                        </div>

                        <div class="rounded-xl border border-border bg-inputBg/60 p-3">
                            <p class="text-sm font-semibold text-primary">Bilder vom Sportplatz</p>
                            <p class="mt-1 text-xs leading-5 text-secondary">Füge Fotos vom Platz, Eingang, Belag oder Ausstattung hinzu. Du kannst Dateien hochladen oder Bild-URLs eintragen.</p>
                            <div class="mt-3 grid gap-3 sm:grid-cols-2">
                                <label class="block">
                                    <span class="text-xs font-semibold text-secondary">Bilder hochladen</span>
                                    <input type="file" accept="image/jpeg,image/png,image/webp" multiple class="mt-1 w-full rounded-lg border border-border bg-card px-3 py-2 text-sm" @change="handlePlaceImageUploads">
                                </label>
                                <label class="block">
                                    <span class="text-xs font-semibold text-secondary">Bild-URLs optional</span>
                                    <input v-model="placeImageUrlsText" class="mt-1 w-full rounded-lg border border-border bg-card px-3 py-2 text-sm" placeholder="https://... , https://...">
                                </label>
                            </div>
                            <div v-if="placePreviewImages.length" class="mt-3 grid grid-cols-4 gap-2">
                                <img
                                    v-for="image in placePreviewImages"
                                    :key="image"
                                    :src="image"
                                    alt=""
                                    class="aspect-video rounded-lg border border-border object-cover"
                                >
                            </div>
                        </div>

                        <button type="submit" class="inline-flex min-h-11 items-center justify-center gap-2 rounded-lg bg-buttonPrimary px-4 py-2 text-sm font-semibold text-buttonTextPrimary disabled:opacity-50" :disabled="placeForm.processing">
                            <i class="las la-map-pin"></i>
                            {{ $t('sport_map.places.save') }}
                        </button>
                    </form>

                    <div>
                        <h3 class="text-lg font-bold text-primary">{{ $t('sport_map.places.saved_title') }}</h3>
                        <div class="mt-3 grid gap-3 sm:grid-cols-2">
                            <article v-for="place in places" :key="place.id" class="rounded-lg border border-border bg-inputBg p-4">
                                <img
                                    v-if="place.gallery_images?.length"
                                    :src="place.gallery_images[0]"
                                    :alt="place.name"
                                    class="mb-3 aspect-video w-full rounded-lg object-cover"
                                >
                                <div class="flex items-start gap-3">
                                    <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-lg bg-amber-400 text-slate-950">
                                        <i class="las la-map-marker-alt"></i>
                                    </div>
                                    <div class="min-w-0">
                                        <p class="truncate text-sm font-bold text-primary">{{ place.name }}</p>
                                        <p class="mt-1 text-xs text-secondary">{{ placeTypeLabel(place.type) }} - {{ place.location?.city || $t('sport_map.places.no_city') }}</p>
                                    </div>
                                </div>
                                <p v-if="place.description" class="mt-3 line-clamp-2 text-xs text-secondary">{{ place.description }}</p>
                                <div class="mt-3 flex flex-wrap gap-2">
                                    <span v-for="sport in place.sport_types" :key="sport" class="rounded-full bg-card px-2 py-1 text-[11px] font-semibold text-secondary">{{ sport }}</span>
                                </div>
                            </article>
                        </div>
                    </div>
                </div>
            </section>
        </div>
    </AppLayout>
</template>


