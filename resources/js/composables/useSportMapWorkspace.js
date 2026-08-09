import { useForm } from '@inertiajs/vue3'
import { computed, onUnmounted, reactive, ref, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import { useSportMapFormatters } from '@/composables/useSportMapFormatters'
import { useSportMapRoutePlanner } from '@/composables/useSportMapRoutePlanner'
import { useSportMapRoutePlayback } from '@/composables/useSportMapRoutePlayback'
import { useSportMapTrackingSession } from '@/composables/useSportMapTrackingSession'
import { createSportMapTileGrid } from '@/composables/useSportMapTileGrid'
import {
    clamp,
    distanceMeters,
    haversine,
    interpolateRoutePoint,
    latToWorldY,
    lonToWorldX,
    tileUrlFromTemplate,
    worldXToLon,
    worldYToLat,
} from '@/composables/useSportMapProjection'
import {
    routeGeneratorDifficultyOptions,
    routeGeneratorElevationOptions,
    routeGeneratorEnvironmentOptions,
    routeGeneratorRouteTypes,
    routeGeneratorSpeedsKmh,
    routeGeneratorStartModes,
    routeGeneratorSteps,
    routeGeneratorSurfaceOptions,
} from '@/support/sportMapGeneratorOptions'
import {
    defaultSportMapTileSource,
    sportMapFallbackLabels,
    sportMapLandingActions,
    sportMapMapLayerOptions,
    sportMapPlaybackSpeedOptions,
    sportMapTabs,
} from '@/support/sportMapUiOptions'

export function useSportMapWorkspace(props) {
    const { t, te } = useI18n()
    const {
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
    } = useSportMapFormatters({
        t,
        te,
        sportTypes: () => props.sportTypes,
        placeTypes: () => props.placeTypes,
    })
    const activeTab = ref(props.selectedRouteId ? 'routes' : 'landing')
    const selectedRouteId = ref(props.selectedRouteId || props.routes[0]?.id || null)
    const placeLocationError = ref('')
    const placeLocationStatus = ref('')
    const mapIsDragging = ref(false)
    const mapWasDragged = ref(false)
    const manualMapPointStatus = ref('')
    const currentLocationPoint = ref(null)
    const routeGeneratorStep = ref(1)
    const routeGeneratorStatus = ref('')
    const generatedRoutePoints = ref([])
    const generatedRouteGeometryPoints = ref([])
    const generatedRouteMetrics = ref(null)
    const generatedRouteNavigationCues = ref([])
    const isGeneratingRoute = ref(false)
    const routeGeneratorVariantSeed = ref(Date.now())
    const routeGeneratorMapTarget = ref('start')
    const activeMapLayer = ref('standard')
    const activeTileSourceIndex = ref(0)
    const visibleMapTileCount = ref(0)
    const failedMapTileCount = ref(0)
    let mapDragStart = null
    let mapDragFrame = null
    let mapPendingDragEvent = null

    const {
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
    } = useSportMapTrackingSession({
        clamp,
        defaultTrackTitle,
        distanceMeters,
        estimateCalories,
        formatCalories,
        formatClockDuration,
        formatDistance,
        formatPace,
        manualMapPointStatus,
        t,
    })

    const TILE_SIZE = 256
    const MAP_WIDTH = 1000
    const MAP_HEIGHT = 560
    const DEFAULT_CENTER = { latitude: 51.1657, longitude: 10.4515 }
    const DEFAULT_ZOOM = 6
    const mapView = ref({
        latitude: DEFAULT_CENTER.latitude,
        longitude: DEFAULT_CENTER.longitude,
        zoom: DEFAULT_ZOOM,
        userChanged: false,
    })

    const routeGeneratorForm = reactive({
        title: '',
        sport_type: 'running',
        start_mode: 'map_center',
        start_latitude: '',
        start_longitude: '',
        destination_latitude: '',
        destination_longitude: '',
        route_type: 'roundtrip',
        target_mode: 'distance',
        distance_km: 5,
        duration_minutes: 45,
        pace_mode: 'pace',
        pace_min_per_km: 6,
        speed_kmh: 10,
        surface: 'firm',
        environment: 'any',
        elevation: 'mixed',
        difficulty: 'easy',
        low_traffic: true,
        lit: false,
        water_breaks: false,
        include_places: '',
        avoid_places: '',
    })

    const placeForm = useForm({
        name: '',
        type: 'football_pitch',
        description: '',
        latitude: '',
        longitude: '',
        address: '',
        city: '',
        country_code: 'DE',
        visibility: 'public',
        team_id: null,
        sport_types: [],
        amenities: [],
        surfaces: [],
        opening_hours: '',
        gallery_images: [],
        image_uploads: [],
    })

    const placeSportTypesText = ref('')
    const placeAmenitiesText = ref('')
    const placeSurfacesText = ref('')
    const placeImageUrlsText = ref('')
    const placeImageUploads = ref([])

    const clearMapTextSelection = () => {
        if (typeof window === 'undefined' || typeof window.getSelection !== 'function') return

        window.getSelection()?.removeAllRanges()
    }

    const isValidMapCoordinate = (point) => {
        const latitude = Number(point?.latitude)
        const longitude = Number(point?.longitude)

        if (!Number.isFinite(latitude) || !Number.isFinite(longitude)) return false
        if (Math.abs(latitude) > 85.05112878 || Math.abs(longitude) > 180) return false

        return !(Math.abs(latitude) < 0.000001 && Math.abs(longitude) < 0.000001)
    }

    const {
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
    } = useSportMapRoutePlanner({
        isValidMapCoordinate,
        manualMapPointStatus,
        t,
    })

    const draftPlacePoint = computed(() => {
        const latitude = Number(placeForm.latitude)
        const longitude = Number(placeForm.longitude)

        if (!isValidMapCoordinate({ latitude, longitude })) return null

        return {
            latitude,
            longitude,
            name: placeForm.name || 'Neuer Sportplatz',
            kind: 'place',
            source: 'place_draft',
            removable: true,
        }
    })

    const activeGeneratorPoints = computed(() => {
        if (activeTab.value !== 'generator') return []

        if (generatedRoutePoints.value.length) {
            return generatedRoutePoints.value
                .filter(isValidMapCoordinate)
                .map((point, index) => ({ ...point, source: 'generator_draft', sourceIndex: index }))
        }

        return [
            routeGeneratorStartPoint.value ? {
                ...routeGeneratorStartPoint.value,
                name: t('sport_map.routes.start_point'),
                source: 'generator_setup',
            } : null,
            routeGeneratorForm.route_type === 'point_to_point' && routeGeneratorDestinationPoint.value ? {
                ...routeGeneratorDestinationPoint.value,
                name: t('sport_map.routes.finish_point'),
                source: 'generator_setup',
            } : null,
        ]
            .filter(isValidMapCoordinate)
            .map((point, index) => ({ ...point, sourceIndex: index }))
    })

    const selectedRoute = computed(() => props.routes.find((item) => item.id === selectedRouteId.value) || props.routes[0] || null)
    const routePreviewPoints = computed(() => {
        if (activeTab.value === 'generator') {
            return activeGeneratorPoints.value
        }

        const formPoints = waypointRows.value
            .map((point, index) => ({
                name: point.name || t('sport_map.routes.point_name', { number: index + 1 }),
                latitude: point.latitude === '' ? null : Number(point.latitude),
                longitude: point.longitude === '' ? null : Number(point.longitude),
                elevation_m: point.elevation_m === '' ? null : Number(point.elevation_m),
                source: 'route_draft',
                sourceIndex: index,
                removable: true,
            }))
            .filter(isValidMapCoordinate)

        if (formPoints.length) return formPoints

        return (selectedRoute.value?.waypoints || []).filter(isValidMapCoordinate)
    })
    const routeGeometryPoints = computed(() => {
        const coordinates = selectedRoute.value?.route_geometry?.coordinates

        if (!Array.isArray(coordinates)) return []

        return coordinates
            .filter((coordinate) => Array.isArray(coordinate) && coordinate.length >= 2)
            .map((coordinate) => ({
                latitude: Number(coordinate[1]),
                longitude: Number(coordinate[0]),
                elevation_m: coordinate[2] ?? null,
            }))
            .filter(isValidMapCoordinate)
    })

    const anchorRouteLinePoints = (linePoints, waypointPoints = []) => {
        const points = linePoints.filter(isValidMapCoordinate)
        const waypoints = waypointPoints.filter(isValidMapCoordinate)
        const start = waypoints[0]
        const finish = waypoints[waypoints.length - 1]
        const anchored = [...points]

        if (start && (!anchored.length || haversine(start, anchored[0]) > 8)) {
            anchored.unshift(start)
        }

        if (finish && (!anchored.length || haversine(finish, anchored[anchored.length - 1]) > 8)) {
            anchored.push(finish)
        }

        return anchored
    }

    const routeLinePoints = computed(() => {
        if (activeTab.value === 'generator') {
            return generatedRouteGeometryPoints.value.length >= 2
                ? anchorRouteLinePoints(generatedRouteGeometryPoints.value, activeGeneratorPoints.value)
                : activeGeneratorPoints.value
        }

        const formPoints = cleanedWaypoints()

        if (formPoints.length >= 2) {
            return draftRouteGeometryPoints.value.length >= 2
                ? anchorRouteLinePoints(draftRouteGeometryPoints.value, formPoints)
                : formPoints
        }
        if (routeGeometryPoints.value.length >= 2) return anchorRouteLinePoints(routeGeometryPoints.value, selectedRoute.value?.waypoints || [])

        return (selectedRoute.value?.waypoints || []).filter(isValidMapCoordinate)
    })
    const activeTrackPoints = computed(() => (trackingPoints.value.length ? trackingPoints.value : (props.tracks[0]?.track_points || [])).filter(isValidMapCoordinate))
    const mapPoints = computed(() => [
        ...routePreviewPoints.value.map((point) => ({ ...point, kind: 'route' })),
        ...activeTrackPoints.value.map((point, index) => ({
            ...point,
            kind: 'track',
            source: trackingPoints.value.length ? 'track_draft' : 'track_saved',
            sourceIndex: index,
            removable: Boolean(trackingPoints.value.length),
        })),
        ...(draftPlacePoint.value ? [draftPlacePoint.value] : []),
        ...props.places.map((place) => ({ latitude: place.latitude, longitude: place.longitude, name: place.name, kind: 'place', source: 'place_saved' })),
    ].filter(isValidMapCoordinate))
    const mapBoundsPoints = computed(() => [
        ...routeLinePoints.value,
        ...routePreviewPoints.value,
        ...activeTrackPoints.value,
        ...(draftPlacePoint.value ? [draftPlacePoint.value] : []),
        ...props.places.map((place) => ({ latitude: place.latitude, longitude: place.longitude })),
    ].filter(isValidMapCoordinate))
    const bounds = computed(() => {
        if (!mapBoundsPoints.value.length) return null

        const latitudes = mapBoundsPoints.value.map((point) => Number(point.latitude))
        const longitudes = mapBoundsPoints.value.map((point) => Number(point.longitude))

        return {
            north: Math.max(...latitudes),
            south: Math.min(...latitudes),
            east: Math.max(...longitudes),
            west: Math.min(...longitudes),
        }
    })
    const routePolyline = computed(() => polylinePoints(routeLinePoints.value))
    const trackPolyline = computed(() => polylinePoints(activeTrackPoints.value))
    const routePlaybackPath = computed(() => routeLinePoints.value.filter(isValidMapCoordinate))
    const routePlaybackTotalDistance = computed(() => distanceMeters(routePlaybackPath.value))
    const activeRouteSportType = computed(() => {
        if (activeTab.value === 'generator') return routeGeneratorForm.sport_type
        if (activeTab.value === 'routes' && cleanedWaypoints().length >= 2) return routeForm.sport_type

        return selectedRoute.value?.sport_type || routeForm.sport_type || 'running'
    })
    const activeRouteEstimatedDurationSeconds = computed(() => {
        if (activeTab.value === 'generator' && generatedRouteMetrics.value?.estimated_duration_seconds) {
            return Number(generatedRouteMetrics.value.estimated_duration_seconds)
        }

        if (activeTab.value === 'routes' && cleanedWaypoints().length < 2 && selectedRoute.value?.estimated_duration_seconds) {
            return Number(selectedRoute.value.estimated_duration_seconds)
        }

        const speedKmh = routeGeneratorSpeedsKmh[activeRouteSportType.value] || routeGeneratorSpeedsKmh.other

        return Math.max(60, Math.round((routePlaybackTotalDistance.value / 1000) / speedKmh * 3600))
    })
    const routePlaybackBaseMetersPerSecond = computed(() => {
        const speedKmh = routeGeneratorSpeedsKmh[activeRouteSportType.value] || routeGeneratorSpeedsKmh.other

        return Math.max(0.4, speedKmh / 3.6)
    })
    const activeMapPointCount = computed(() => {
        if (activeTab.value === 'generator') return generatedRoutePoints.value.length
        if (activeTab.value === 'routes') return waypointRows.value.filter((point) => point.latitude !== '' && point.longitude !== '').length
        if (activeTab.value === 'tracks') return trackingPoints.value.length
        if (activeTab.value === 'places') return draftPlacePoint.value ? 1 : 0

        return 0
    })
    const mapLayerOptions = computed(() => sportMapMapLayerOptions(props.mapConfig, t))
    const activeMapLayerOption = computed(() => mapLayerOptions.value.find((layer) => layer.key === activeMapLayer.value) || mapLayerOptions.value[0])
    const tileSources = computed(() => activeMapLayerOption.value?.sources || [])
    const defaultTileSource = computed(() => defaultSportMapTileSource(props.mapConfig))
    const activeTileSource = computed(() => tileSources.value[activeTileSourceIndex.value] || tileSources.value[0] || defaultTileSource.value)
    const tileTemplate = computed(() => activeTileSource.value?.url || defaultTileSource.value.url)
    const mapOverlaySource = computed(() => activeMapLayerOption.value?.overlay || null)
    const mapAttribution = computed(() => mapOverlaySource.value
        ? `${activeTileSource.value?.attribution || defaultTileSource.value.attribution} - ${mapOverlaySource.value.attribution}`
        : (activeTileSource.value?.attribution || defaultTileSource.value.attribution))
    const mapHasRealTiles = computed(() => visibleMapTileCount.value > 0)
    const mapStatusText = computed(() => {
        if (manualMapPointStatus.value) return manualMapPointStatus.value
        if (!mapPoints.value.length) return t('sport_map.map_status.empty')

        return t('sport_map.map_status.ready')
    })
    const activeMapInteractionHint = computed(() => {
        if (activeTab.value === 'generator') {
            return routeGeneratorForm.route_type === 'point_to_point' && routeGeneratorMapTarget.value === 'destination'
                ? t('sport_map.map_status.generator_destination_hint')
                : t('sport_map.map_status.generator_start_hint')
        }

        if (activeTab.value === 'routes') return t('sport_map.map_status.routes_hint')
        if (activeTab.value === 'tracks') return t('sport_map.map_status.tracks_hint')

        return t('sport_map.map_status.places_hint')
    })
    const placePreviewImages = computed(() => [
        ...splitList(placeImageUrlsText.value),
        ...placeImageUploads.value.map((file) => URL.createObjectURL(file)),
    ].slice(0, 8))

    const fallbackMapLabels = sportMapFallbackLabels
    const tabs = sportMapTabs
    const landingActions = sportMapLandingActions.map((action) => ({
        ...action,
        key: action.target || action.key,
        title: action.title || (action.titleKey ? t(action.titleKey) : action.key),
        description: action.description || (action.descriptionKey ? t(action.descriptionKey) : ''),
    }))

    watch(() => props.routes, (routes) => {
        const nextRoutes = Array.isArray(routes) ? routes : []

        if (!nextRoutes.some((item) => item.id === selectedRouteId.value)) {
            selectedRouteId.value = nextRoutes[0]?.id || null
        }
    }, { deep: true })

    const routeGeneratorTargetDistanceKm = computed(() => {
        if (routeGeneratorForm.target_mode === 'duration') {
            const speed = routeGeneratorTargetSpeedKmh.value
            return clamp((Number(routeGeneratorForm.duration_minutes) || 45) / 60 * speed, 1, 80)
        }

        return clamp(Number(routeGeneratorForm.distance_km) || 5, 1, 80)
    })

    const routeGeneratorEstimatedMinutes = computed(() => {
        const speed = routeGeneratorTargetSpeedKmh.value

        return Math.max(10, Math.round((routeGeneratorTargetDistanceKm.value / speed) * 60))
    })

    const routeGeneratorDefaultSpeedKmh = computed(() => routeGeneratorSpeedsKmh[routeGeneratorForm.sport_type] || routeGeneratorSpeedsKmh.other)
    const routeGenerationAccess = computed(() => props.sportMapAccess?.route_generation || {
        available: true,
        label: 'Free Routing',
        monthly_limit: 10,
        monthly_used: 0,
        monthly_remaining: 10,
        reason: null,
    })
    const routeGenerationLimitLabel = computed(() => {
        const access = routeGenerationAccess.value

        if (access.monthly_limit === null) {
            return `${access.label || 'Routing'}: unbegrenzt`
        }

        return `${access.label || 'Routing'}: ${access.monthly_remaining ?? 0}/${access.monthly_limit} Vorschläge diesen Monat offen`
    })

    const routeGeneratorTargetSpeedKmh = computed(() => {
        if (routeGeneratorForm.pace_mode === 'pace' && Number(routeGeneratorForm.pace_min_per_km) > 0) {
            return clamp(60 / Number(routeGeneratorForm.pace_min_per_km), 1, 60)
        }

        if (Number(routeGeneratorForm.speed_kmh) > 0) {
            return clamp(Number(routeGeneratorForm.speed_kmh), 1, 80)
        }

        return routeGeneratorDefaultSpeedKmh.value
    })

    const routeGeneratorPaceLabel = computed(() => {
        const pace = 60 / routeGeneratorTargetSpeedKmh.value

        return `${pace.toFixed(pace < 10 ? 1 : 0)} min/km`
    })

    const routeGeneratorStartPoint = computed(() => {
        if (routeGeneratorForm.start_mode === 'current_location') {
            return currentLocationPoint.value && isValidMapCoordinate(currentLocationPoint.value)
                ? currentLocationPoint.value
                : null
        }

        if (routeGeneratorForm.start_mode === 'manual') {
            const latitude = Number(routeGeneratorForm.start_latitude)
            const longitude = Number(routeGeneratorForm.start_longitude)

            if (isValidMapCoordinate({ latitude, longitude })) {
                return { latitude, longitude, name: t('sport_map.routes.start_point') }
            }
        }

        return {
            latitude: safeMapView.value.latitude,
            longitude: safeMapView.value.longitude,
            name: t('sport_map.map_status.center_point_name'),
        }
    })

    const routeGeneratorDestinationPoint = computed(() => {
        const latitude = Number(routeGeneratorForm.destination_latitude)
        const longitude = Number(routeGeneratorForm.destination_longitude)

        if (!isValidMapCoordinate({ latitude, longitude })) {
            return null
        }

        return { latitude, longitude, name: t('sport_map.routes.finish_point') }
    })

    const routeGeneratorSummary = computed(() => ({
        distance: `${routeGeneratorTargetDistanceKm.value.toFixed(routeGeneratorTargetDistanceKm.value < 10 ? 1 : 0)} km`,
        duration: routeGeneratorForm.target_mode === 'duration'
            ? `${Number(routeGeneratorForm.duration_minutes) || 45} min`
            : t('sport_map.generator.approx_minutes', { minutes: routeGeneratorEstimatedMinutes.value }),
        durationLabel: routeGeneratorForm.target_mode === 'duration' ? t('sport_map.generator.target_duration') : t('sport_map.generator.estimated_duration'),
        distanceLabel: routeGeneratorForm.target_mode === 'duration' ? t('sport_map.generator.estimated_distance') : t('sport_map.generator.target_distance'),
        speed: `${routeGeneratorTargetSpeedKmh.value.toFixed(1)} km/h`,
        pace: routeGeneratorPaceLabel.value,
        surface: optionLabel(routeGeneratorSurfaceOptions, routeGeneratorForm.surface, t('sport_map.generator.surface_any')),
        environment: optionLabel(routeGeneratorEnvironmentOptions, routeGeneratorForm.environment, t('sport_map.generator.environment_any')),
        elevation: optionLabel(routeGeneratorElevationOptions, routeGeneratorForm.elevation, t('sport_map.generator.elevation_mixed')),
        difficulty: optionLabel(routeGeneratorDifficultyOptions, routeGeneratorForm.difficulty, t('sport_map.generator.difficulty_easy')),
    }))

    const generatedRouteActualSummary = computed(() => {
        if (!generatedRouteMetrics.value) {
            return null
        }

        const quality = generatedRouteMetrics.value.quality || {}
        const fallbackBacktrackPercent = Number(generatedRouteMetrics.value.route_shape?.backtrack_ratio || 0) * 100

        return {
            distance: formatDistance(generatedRouteMetrics.value.distance_meters),
            duration: formatDuration(generatedRouteMetrics.value.estimated_duration_seconds),
            status: generatedRouteMetrics.value.routing_status || 'estimated',
            provider: generatedRouteMetrics.value.routing_provider || 'local',
            geometryPoints: generatedRouteMetrics.value.geometry_point_count || generatedRouteGeometryPoints.value.length,
            qualityScore: quality.score ?? null,
            qualityLabel: quality.label || '-',
            targetDelta: formatDistance(quality.target_delta_meters || generatedRouteMetrics.value.target_delta_meters || 0),
            backtrackPercent: quality.backtrack_percent ?? fallbackBacktrackPercent,
            shapeAcceptable: quality.shape_acceptable ?? generatedRouteMetrics.value.route_shape?.acceptable ?? false,
        }
    })

    const generatedRouteCuePreview = computed(() => generatedRouteNavigationCues.value.slice(0, 6))

    const setActiveTab = (key) => {
        activeTab.value = key
    }

    const handlePlaceImageUploads = (event) => {
        placeImageUploads.value = Array.from(event.target.files || []).slice(0, 6)
    }

    const setGeneratorStep = (step) => {
        routeGeneratorStep.value = clamp(step, 1, 3)
    }

    const setRouteGeneratorStartMode = (mode) => {
        routeGeneratorForm.start_mode = mode

        if (mode === 'current_location') {
            useCurrentLocationForGenerator()
        }
    }

    const setRouteGeneratorType = (type) => {
        routeGeneratorForm.route_type = type
        routeGeneratorMapTarget.value = type === 'point_to_point' ? 'destination' : 'start'
        generatedRoutePoints.value = []
        generatedRouteGeometryPoints.value = []
        generatedRouteMetrics.value = null
        generatedRouteNavigationCues.value = []
        routeGeneratorStatus.value = type === 'point_to_point'
            ? t('sport_map.generator.status_point_to_point')
            : t('sport_map.generator.status_roundtrip')
    }

    const setGeneratorStartFromCoordinate = (coordinate, label = t('sport_map.generator.start_point')) => {
        routeGeneratorForm.start_mode = 'manual'
        routeGeneratorForm.start_latitude = Number(coordinate.latitude).toFixed(7)
        routeGeneratorForm.start_longitude = Number(coordinate.longitude).toFixed(7)
        routeGeneratorStatus.value = t('sport_map.generator.point_set', { label })
        manualMapPointStatus.value = t('sport_map.generator.map_point_for_generator', { label })

        if (routeGeneratorForm.route_type === 'point_to_point') {
            routeGeneratorMapTarget.value = 'destination'
        }
    }

    const setGeneratorDestinationFromCoordinate = (coordinate, label = t('sport_map.generator.destination_point')) => {
        routeGeneratorForm.destination_latitude = Number(coordinate.latitude).toFixed(7)
        routeGeneratorForm.destination_longitude = Number(coordinate.longitude).toFixed(7)
        routeGeneratorStatus.value = t('sport_map.generator.point_set', { label })
        manualMapPointStatus.value = t('sport_map.generator.map_point_for_destination_route', { label })
    }

    const setGeneratorDestinationFromMapCenter = () => {
        setGeneratorDestinationFromCoordinate(safeMapView.value, t('sport_map.generator.map_center_destination'))
    }

    const clearGeneratorDestination = () => {
        routeGeneratorForm.destination_latitude = ''
        routeGeneratorForm.destination_longitude = ''
        routeGeneratorStatus.value = t('sport_map.generator.destination_removed')
        manualMapPointStatus.value = routeGeneratorStatus.value
    }

    const useCurrentLocationForGenerator = () => {
        routeGeneratorStatus.value = t('sport_map.generator.locating')

        if (!navigator.geolocation) {
            routeGeneratorStatus.value = t('sport_map.generator.geolocation_unavailable')
            return
        }

        navigator.geolocation.getCurrentPosition((position) => {
            const point = {
                latitude: position.coords.latitude,
                longitude: position.coords.longitude,
                accuracy_m: Math.round(position.coords.accuracy || 0),
                name: t('sport_map.generator.start_current_location'),
            }

            if (!isValidMapCoordinate(point)) {
                routeGeneratorStatus.value = t('sport_map.generator.location_invalid')
                return
            }

            currentLocationPoint.value = point
            routeGeneratorForm.start_mode = 'current_location'
            mapView.value.latitude = point.latitude
            mapView.value.longitude = point.longitude
            mapView.value.zoom = Math.max(safeMapView.value.zoom, 15)
            mapView.value.userChanged = true
            routeGeneratorStatus.value = point.accuracy_m
                ? t('sport_map.generator.start_accuracy', { accuracy: point.accuracy_m })
                : t('sport_map.generator.start_location_set')
        }, () => {
            routeGeneratorStatus.value = t('sport_map.generator.location_permission_error')
        }, {
            enableHighAccuracy: true,
            maximumAge: 10000,
            timeout: 15000,
        })
    }

    const generatedRouteTitle = () => routeGeneratorForm.title
        || `${optionLabel(routeGeneratorRouteTypes, routeGeneratorForm.route_type, t('sport_map.generator.route_fallback'))} ${routeGeneratorSummary.value.distance}`

    const routeGeometryToPoints = (geometry) => {
        const coordinates = geometry?.coordinates

        if (!Array.isArray(coordinates)) {
            return []
        }

        return coordinates
            .filter((coordinate) => Array.isArray(coordinate) && coordinate.length >= 2)
            .map((coordinate, index) => ({
                longitude: Number(coordinate[0]),
                latitude: Number(coordinate[1]),
                name: t('sport_map.generator.generated_waypoint', { number: index + 1 }),
                source: 'generator_geometry',
            }))
            .filter(isValidMapCoordinate)
    }

    const generatorWaypointName = (index, isLast) => {
        if (index === 0) return t('sport_map.generator.start_name')
        if (isLast && routeGeneratorForm.route_type === 'roundtrip') return t('sport_map.generator.back_to_start')

        return t('sport_map.generator.route_point', { number: index + 1 })
    }

    const routeProposalPayload = (waypoints = null) => {
        const start = routeGeneratorStartPoint.value
        const destination = routeGeneratorDestinationPoint.value
        const effectiveWaypoints = waypoints || (
            routeGeneratorForm.route_type === 'point_to_point' && isValidMapCoordinate(start) && isValidMapCoordinate(destination)
                ? [
                    {
                        name: start.name || t('sport_map.generator.start_name'),
                        latitude: start.latitude,
                        longitude: start.longitude,
                    },
                    {
                        name: destination.name || t('sport_map.generator.destination_name'),
                        latitude: destination.latitude,
                        longitude: destination.longitude,
                    },
                ]
                : null
        )

        return {
            title: routeGeneratorForm.title,
            sport_type: routeGeneratorForm.sport_type,
            route_type: routeGeneratorForm.route_type,
            target_mode: routeGeneratorForm.target_mode,
            distance_km: routeGeneratorTargetDistanceKm.value,
            duration_minutes: routeGeneratorForm.duration_minutes,
            surface: routeGeneratorForm.surface,
            environment: routeGeneratorForm.environment,
            elevation: routeGeneratorForm.elevation,
            difficulty: routeGeneratorForm.difficulty,
            low_traffic: routeGeneratorForm.low_traffic,
            lit: routeGeneratorForm.lit,
            water_breaks: routeGeneratorForm.water_breaks,
            include_places: routeGeneratorForm.include_places,
            avoid_places: routeGeneratorForm.avoid_places,
            variant_seed: routeGeneratorVariantSeed.value,
            start: isValidMapCoordinate(start) ? {
                name: start.name || t('sport_map.generator.start_name'),
                latitude: start.latitude,
                longitude: start.longitude,
            } : null,
            waypoints: effectiveWaypoints,
        }
    }

    const refreshRouteGeneratorVariant = () => {
        routeGeneratorVariantSeed.value = Date.now() + Math.floor(Math.random() * 1000000)
    }

    const applyRouteProposal = (proposal) => {
        const waypoints = Array.isArray(proposal?.waypoints) ? proposal.waypoints : []
        const geometryPoints = routeGeometryToPoints(proposal?.route_geometry)
        const metrics = proposal?.metrics || {}

        generatedRoutePoints.value = waypoints
            .filter(isValidMapCoordinate)
            .map((point, index, allPoints) => ({
                ...point,
                name: point.name || generatorWaypointName(index, index === allPoints.length - 1),
                source: 'generator_draft',
                sourceIndex: index,
                removable: index > 0 && index < allPoints.length - 1,
            }))

        generatedRouteGeometryPoints.value = geometryPoints
        generatedRouteMetrics.value = {
            ...metrics,
            distance_meters: proposal?.distance_meters || 0,
            estimated_duration_seconds: proposal?.estimated_duration_seconds || 0,
            geometry_point_count: metrics.geometry_point_count || geometryPoints.length,
        }
        generatedRouteNavigationCues.value = Array.isArray(proposal?.navigation_cues) ? proposal.navigation_cues : []

        routeGeneratorStep.value = 3

        if (metrics.routing_status === 'routed' && geometryPoints.length > 2) {
            routeGeneratorStatus.value = t('sport_map.generator.routed_success', {
                distance: formatDistance(proposal.distance_meters),
                duration: formatDuration(proposal.estimated_duration_seconds),
            })
        } else {
            routeGeneratorStatus.value = t('sport_map.generator.routed_fallback')
        }

        manualMapPointStatus.value = routeGeneratorStatus.value
    }

    const requestRouteProposal = async (waypoints = null) => {
        isGeneratingRoute.value = true
        routeGeneratorStatus.value = t('sport_map.generator.calculating_route')

        try {
            const response = await window.axios.post(route('auth.sport-route-proposals.store'), routeProposalPayload(waypoints))
            applyRouteProposal(response.data?.data)
        } catch (error) {
            routeGeneratorStatus.value = error.response?.data?.errors?.route_generation?.[0]
                || error.response?.data?.message
                || t('sport_map.generator.route_failed')
        } finally {
            isGeneratingRoute.value = false
        }
    }

    const generateRouteProposal = () => {
        if (isGeneratingRoute.value) return

        if (!routeGenerationAccess.value.available) {
            routeGeneratorStatus.value = routeGenerationAccess.value.reason || t('sport_map.generator.generator_unavailable')
            return
        }

        if (!isValidMapCoordinate(routeGeneratorStartPoint.value)) {
            routeGeneratorStatus.value = t('sport_map.generator.choose_valid_start')
            return
        }

        if (routeGeneratorForm.route_type === 'point_to_point' && !isValidMapCoordinate(routeGeneratorDestinationPoint.value)) {
            routeGeneratorStatus.value = t('sport_map.generator.choose_destination')
            routeGeneratorStep.value = 1
            routeGeneratorMapTarget.value = 'destination'
            return
        }

        refreshRouteGeneratorVariant()
        requestRouteProposal()
    }

    const removeGeneratedRoutePoint = (index) => {
        if (!generatedRoutePoints.value[index]) return

        if (generatedRoutePoints.value.length <= 2) {
            generatedRoutePoints.value = []
            generatedRouteGeometryPoints.value = []
            generatedRouteMetrics.value = null
            routeGeneratorStatus.value = t('sport_map.generator.proposal_removed')
            return
        }

        generatedRoutePoints.value.splice(index, 1)
        requestRouteProposal(generatedRoutePoints.value.map((point, waypointIndex, allPoints) => ({
            name: point.name || generatorWaypointName(waypointIndex, waypointIndex === allPoints.length - 1),
            latitude: point.latitude,
            longitude: point.longitude,
            elevation_m: point.elevation_m ?? '',
        })))
    }

    const resetGeneratedRoute = () => {
        generatedRoutePoints.value = []
        generatedRouteGeometryPoints.value = []
        generatedRouteMetrics.value = null
        generatedRouteNavigationCues.value = []
        refreshRouteGeneratorVariant()
        routeGeneratorStatus.value = t('sport_map.generator.proposal_reset')
        manualMapPointStatus.value = routeGeneratorStatus.value
    }

    const applyGeneratedRouteToPlanner = () => {
        if (generatedRoutePoints.value.length < 2) {
            routeGeneratorStatus.value = t('sport_map.generator.proposal_required')
            return
        }

        routeForm.title = generatedRouteTitle()
        routeForm.sport_type = routeGeneratorForm.sport_type
        routeForm.difficulty = routeGeneratorForm.difficulty === 'hard' ? 'hard' : routeGeneratorForm.difficulty === 'moderate' ? 'moderate' : 'easy'
        routeForm.surface = routeGeneratorForm.surface === 'any' ? '' : routeGeneratorForm.surface
        routeForm.description = [
            generatedRouteActualSummary.value
                ? t('sport_map.generator.generated_description_routed', { distance: generatedRouteActualSummary.value.distance, duration: generatedRouteActualSummary.value.duration })
                : t('sport_map.generator.generated_description_estimated', { distance: routeGeneratorSummary.value.distance, duration: routeGeneratorSummary.value.duration }),
            t('sport_map.generator.description_preferences', {
                surface: routeGeneratorSummary.value.surface,
                environment: routeGeneratorSummary.value.environment,
                elevation: routeGeneratorSummary.value.elevation,
            }),
            routeGeneratorForm.low_traffic ? t('sport_map.generator.low_traffic_description') : '',
            routeGeneratorForm.lit ? t('sport_map.generator.lit_description') : '',
            routeGeneratorForm.water_breaks ? t('sport_map.generator.water_breaks_description') : '',
            routeGeneratorForm.include_places ? t('sport_map.generator.include_places_description', { places: routeGeneratorForm.include_places }) : '',
            routeGeneratorForm.avoid_places ? t('sport_map.generator.avoid_places_description', { places: routeGeneratorForm.avoid_places }) : '',
        ].filter(Boolean).join('\n')
        waypointRows.value = generatedRoutePoints.value.map((point, index) => ({
            name: point.name || generatorWaypointName(index, index === generatedRoutePoints.value.length - 1),
            latitude: Number(point.latitude).toFixed(7),
            longitude: Number(point.longitude).toFixed(7),
            elevation_m: point.elevation_m ?? '',
        }))
        draftRouteGeometryPoints.value = generatedRouteGeometryPoints.value
        routeForm.route_geometry = generatedRouteGeometryPoints.value.length >= 2
            ? {
                type: 'LineString',
                coordinates: generatedRouteGeometryPoints.value.map((point) => [
                    Number(point.longitude),
                    Number(point.latitude),
                ]),
            }
            : null
        routeForm.navigation_cues = generatedRouteNavigationCues.value
        routeForm.distance_meters = generatedRouteMetrics.value?.distance_meters || null
        routeForm.estimated_duration_seconds = generatedRouteMetrics.value?.estimated_duration_seconds || null
        routeForm.elevation_gain_meters = generatedRouteMetrics.value?.elevation_gain_meters || 0
        routeForm.elevation_loss_meters = generatedRouteMetrics.value?.elevation_loss_meters || 0
        routeForm.metrics = generatedRouteMetrics.value
        activeTab.value = 'routes'
        manualMapPointStatus.value = t('sport_map.generator.proposal_applied')
    }

    const useCurrentLocationForPlace = () => {
        placeLocationError.value = ''
        placeLocationStatus.value = t('sport_map.map_status.location_loading')

        if (!navigator.geolocation) {
            placeLocationError.value = t('sport_map.places.location_error')
            placeLocationStatus.value = ''
            return
        }

        navigator.geolocation.getCurrentPosition(async (position) => {
            placeForm.latitude = position.coords.latitude.toFixed(7)
            placeForm.longitude = position.coords.longitude.toFixed(7)

            try {
                placeLocationStatus.value = t('sport_map.map_status.address_loading')
                const params = new URLSearchParams({
                    format: 'jsonv2',
                    lat: placeForm.latitude,
                    lon: placeForm.longitude,
                    addressdetails: '1',
                })
                const response = await fetch(`https://nominatim.openstreetmap.org/reverse?${params.toString()}`, {
                    headers: { Accept: 'application/json' },
                })

                if (!response.ok) {
                    throw new Error('reverse geocode failed')
                }

                const result = await response.json()
                const address = result.address || {}
                placeForm.city = address.city || address.town || address.village || address.municipality || placeForm.city
                placeForm.address = [
                    address.road,
                    address.house_number,
                ].filter(Boolean).join(' ') || result.display_name || placeForm.address
                placeForm.country_code = String(address.country_code || placeForm.country_code || 'DE').toUpperCase()
                placeLocationStatus.value = t('sport_map.map_status.location_address_applied')
            } catch (error) {
                placeLocationStatus.value = t('sport_map.map_status.location_applied_address_manual')
            }
        }, () => {
            placeLocationError.value = t('sport_map.places.location_error')
            placeLocationStatus.value = ''
        }, {
            enableHighAccuracy: true,
            maximumAge: 10000,
            timeout: 15000,
        })
    }

    const showCurrentLocationOnMap = () => {
        manualMapPointStatus.value = t('sport_map.map_status.location_loading')

        if (!navigator.geolocation) {
            manualMapPointStatus.value = t('sport_map.map_status.location_unavailable')
            return
        }

        navigator.geolocation.getCurrentPosition((position) => {
            const point = {
                latitude: position.coords.latitude,
                longitude: position.coords.longitude,
                accuracy_m: Math.round(position.coords.accuracy || 0),
                name: t('sport_map.map_status.my_location'),
            }

            if (!isValidMapCoordinate(point)) {
                manualMapPointStatus.value = t('sport_map.map_status.location_not_mappable')
                return
            }

            currentLocationPoint.value = point
            mapView.value.latitude = point.latitude
            mapView.value.longitude = point.longitude
            mapView.value.zoom = Math.max(safeMapView.value.zoom, 15)
            mapView.value.userChanged = true
            manualMapPointStatus.value = point.accuracy_m
                ? t('sport_map.map_status.location_shown_accuracy', { meters: point.accuracy_m })
                : t('sport_map.map_status.location_shown')

            if (activeTab.value === 'generator') {
                routeGeneratorForm.start_mode = 'current_location'
                routeGeneratorStatus.value = t('sport_map.generator.current_location_start_status')
            }
        }, () => {
            manualMapPointStatus.value = t('sport_map.map_status.location_permission_error')
        }, {
            enableHighAccuracy: true,
            maximumAge: 10000,
            timeout: 15000,
        })
    }

    const setPlaceFromMap = (coordinate) => {
        placeForm.latitude = coordinate.latitude.toFixed(7)
        placeForm.longitude = coordinate.longitude.toFixed(7)
        placeLocationStatus.value = t('sport_map.map_status.coordinates_applied')
        placeLocationError.value = ''
        manualMapPointStatus.value = t('sport_map.map_status.place_position_applied')
    }

    const clearPlaceMapPoint = () => {
        placeForm.latitude = ''
        placeForm.longitude = ''
        placeLocationStatus.value = t('sport_map.map_status.place_position_removed')
        manualMapPointStatus.value = t('sport_map.map_status.place_position_removed')
    }

    const removeMapPoint = (point) => {
        if (point.source === 'generator_draft') {
            removeGeneratedRoutePoint(point.sourceIndex)
            return
        }

        if (point.source === 'route_draft') {
            removeRouteWaypointFromMap(point.sourceIndex)
            return
        }

        if (point.source === 'track_draft') {
            removeTrackPointFromMap(point.sourceIndex)
            return
        }

        if (point.source === 'place_draft') {
            clearPlaceMapPoint()
        }
    }

    const removeLastActiveMapPoint = () => {
        if (activeMapPointCount.value === 0) {
            manualMapPointStatus.value = t('sport_map.map_status.no_point_to_remove')
            return
        }

        if (activeTab.value === 'generator') {
            removeGeneratedRoutePoint(generatedRoutePoints.value.length - 2)
            return
        }

        if (activeTab.value === 'routes') {
            removeLastRouteWaypoint()
            return
        }

        if (activeTab.value === 'tracks') {
            removeTrackPointFromMap(trackingPoints.value.length - 1)
            return
        }

        if (activeTab.value === 'places') {
            clearPlaceMapPoint()
        }
    }

    const resetActiveMapPoints = () => {
        if (activeMapPointCount.value === 0) {
            resetMapView()
            manualMapPointStatus.value = t('sport_map.map_status.map_centered_no_entries')
            return
        }

        if (activeTab.value === 'generator') {
            resetGeneratedRoute()
            return
        }

        if (activeTab.value === 'routes') {
            resetRouteWaypoints()
            return
        }

        if (activeTab.value === 'tracks') {
            resetTrackPoints()
            return
        }

        if (activeTab.value === 'places') {
            clearPlaceMapPoint()
        }
    }

    const savePlace = () => {
        placeForm.sport_types = splitList(placeSportTypesText.value)
        placeForm.amenities = splitList(placeAmenitiesText.value)
        placeForm.surfaces = splitList(placeSurfacesText.value)
        placeForm.gallery_images = splitList(placeImageUrlsText.value)
        placeForm.image_uploads = placeImageUploads.value
        placeForm.post(route('auth.sport-places.store'), {
            preserveScroll: true,
            forceFormData: true,
            onSuccess: () => {
                placeForm.reset()
                placeForm.type = 'football_pitch'
                placeForm.country_code = 'DE'
                placeForm.visibility = 'public'
                placeSportTypesText.value = ''
                placeAmenitiesText.value = ''
                placeSurfacesText.value = ''
                placeImageUrlsText.value = ''
                placeImageUploads.value = []
                placeLocationError.value = ''
                placeLocationStatus.value = ''
            },
        })
    }

    const autoMapCenter = computed(() => {
        if (!bounds.value) return DEFAULT_CENTER

        return {
            latitude: (Number(bounds.value.north) + Number(bounds.value.south)) / 2,
            longitude: (Number(bounds.value.east) + Number(bounds.value.west)) / 2,
        }
    })

    const autoMapZoom = computed(() => {
        if (!bounds.value) return DEFAULT_ZOOM

        const latitudeSpan = Math.abs(Number(bounds.value.north) - Number(bounds.value.south))
        const longitudeSpan = Math.abs(Number(bounds.value.east) - Number(bounds.value.west))

        if (latitudeSpan < 0.0001 && longitudeSpan < 0.0001) return 12

        const worldLonSpan = Math.max(((Number(bounds.value.east) - Number(bounds.value.west)) / 360) * TILE_SIZE, 0.0001)
        const worldLatSpan = Math.max(Math.abs(latToWorldY(bounds.value.south, 0) - latToWorldY(bounds.value.north, 0)), 0.0001)
        const zoomX = Math.floor(Math.log2((MAP_WIDTH * 0.74) / worldLonSpan))
        const zoomY = Math.floor(Math.log2((MAP_HEIGHT * 0.72) / worldLatSpan))

        return clamp(Math.min(zoomX, zoomY), 2, 17)
    })

    const safeMapView = computed(() => ({
        latitude: Number.isFinite(Number(mapView.value.latitude)) ? Number(mapView.value.latitude) : DEFAULT_CENTER.latitude,
        longitude: Number.isFinite(Number(mapView.value.longitude)) ? Number(mapView.value.longitude) : DEFAULT_CENTER.longitude,
        zoom: clamp(Math.round(Number(mapView.value.zoom) || DEFAULT_ZOOM), 2, 17),
    }))

    const {
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
    } = useSportMapRoutePlayback({
        baseMetersPerSecond: routePlaybackBaseMetersPerSecond,
        clamp,
        estimatedDurationSeconds: activeRouteEstimatedDurationSeconds,
        interpolateRoutePoint,
        isValidMapCoordinate,
        mapView,
        path: routePlaybackPath,
        safeMapView,
        t,
        totalDistance: routePlaybackTotalDistance,
    })

    const mapProjection = computed(() => {
        const zoom = safeMapView.value.zoom
        const center = safeMapView.value

        return {
            zoom,
            left: lonToWorldX(center.longitude, zoom) - MAP_WIDTH / 2,
            top: latToWorldY(center.latitude, zoom) - MAP_HEIGHT / 2,
        }
    })

    const tileUrl = (zoom, x, y) => tileUrlFromTemplate(tileTemplate.value, zoom, x, y)

    const handleTileLoad = () => {
        visibleMapTileCount.value += 1
    }

    const handleTileError = (event) => {
        event.currentTarget.style.display = 'none'
        failedMapTileCount.value += 1

        if (mapHasRealTiles.value) return

        const visibleTileCount = Math.max(mapTiles.value.length, 1)
        const currentSourceFailed = failedMapTileCount.value >= Math.min(visibleTileCount, 8)

        if (!currentSourceFailed || activeTileSourceIndex.value >= tileSources.value.length - 1) return

        activeTileSourceIndex.value += 1
        failedMapTileCount.value = 0
    }

    const mapTiles = computed(() => {
        return createSportMapTileGrid({
            keyPrefix: `${activeMapLayer.value}-${activeTileSourceIndex.value}`,
            mapHeight: MAP_HEIGHT,
            mapWidth: MAP_WIDTH,
            projection: mapProjection.value,
            tileSize: TILE_SIZE,
            urlForTile: tileUrl,
        })
    })

    const mapOverlayTiles = computed(() => {
        if (!mapOverlaySource.value?.url) return []

        return createSportMapTileGrid({
            keyPrefix: `overlay-${activeMapLayer.value}`,
            mapHeight: MAP_HEIGHT,
            mapWidth: MAP_WIDTH,
            projection: mapProjection.value,
            tileSize: TILE_SIZE,
            urlForTile: (zoom, x, y) => tileUrlFromTemplate(mapOverlaySource.value.url, zoom, x, y),
        })
    })

    const coordinateFromMapEvent = (event) => {
        const target = event.currentTarget
        const rect = target.getBoundingClientRect()
        const projection = mapProjection.value
        const x = projection.left + ((event.clientX - rect.left) / rect.width) * MAP_WIDTH
        const y = projection.top + ((event.clientY - rect.top) / rect.height) * MAP_HEIGHT

        return {
            latitude: clamp(worldYToLat(y, projection.zoom), -85.05112878, 85.05112878),
            longitude: worldXToLon(x, projection.zoom),
        }
    }

    const setMapCenterFromWorld = (worldX, worldY, zoom) => {
        const nextZoom = clamp(Math.round(Number(zoom) || DEFAULT_ZOOM), 2, 17)
        const nextLatitude = clamp(worldYToLat(worldY, nextZoom), -85.05112878, 85.05112878)
        const nextLongitude = worldXToLon(worldX, nextZoom)

        if (!Number.isFinite(nextLatitude) || !Number.isFinite(nextLongitude)) return

        mapView.value.latitude = nextLatitude
        mapView.value.longitude = nextLongitude
        mapView.value.zoom = nextZoom
        mapView.value.userChanged = true
    }

    const zoomMap = (direction, anchorEvent = null) => {
        const currentZoom = safeMapView.value.zoom
        const nextZoom = clamp(currentZoom + direction, 2, 17)

        if (nextZoom === currentZoom) return

        if (!anchorEvent) {
            setMapCenterFromWorld(
                lonToWorldX(mapView.value.longitude, nextZoom),
                latToWorldY(mapView.value.latitude, nextZoom),
                nextZoom,
            )
            return
        }

        const rect = anchorEvent.currentTarget.getBoundingClientRect()
        const projection = mapProjection.value
        const pointerXRatio = (anchorEvent.clientX - rect.left) / rect.width
        const pointerYRatio = (anchorEvent.clientY - rect.top) / rect.height
        const anchorWorldX = projection.left + pointerXRatio * MAP_WIDTH
        const anchorWorldY = projection.top + pointerYRatio * MAP_HEIGHT
        const scale = 2 ** (nextZoom - currentZoom)
        const nextLeft = anchorWorldX * scale - pointerXRatio * MAP_WIDTH
        const nextTop = anchorWorldY * scale - pointerYRatio * MAP_HEIGHT

        setMapCenterFromWorld(nextLeft + MAP_WIDTH / 2, nextTop + MAP_HEIGHT / 2, nextZoom)
    }

    const resetMapView = () => {
        mapView.value.latitude = autoMapCenter.value.latitude
        mapView.value.longitude = autoMapCenter.value.longitude
        mapView.value.zoom = autoMapZoom.value
        mapView.value.userChanged = false
        manualMapPointStatus.value = ''
    }

    const startMapDrag = (event) => {
        if (event.button !== undefined && event.button !== 0) return

        event.preventDefault()
        clearMapTextSelection()

        const projection = mapProjection.value
        mapIsDragging.value = true
        mapWasDragged.value = false
        mapDragStart = {
            clientX: event.clientX,
            clientY: event.clientY,
            centerX: projection.left + MAP_WIDTH / 2,
            centerY: projection.top + MAP_HEIGHT / 2,
            width: event.currentTarget.getBoundingClientRect().width,
            height: event.currentTarget.getBoundingClientRect().height,
            zoom: projection.zoom,
        }
        event.currentTarget.setPointerCapture?.(event.pointerId)
    }

    const moveMapDrag = (event) => {
        if (!mapIsDragging.value || !mapDragStart) return

        event.preventDefault()
        clearMapTextSelection()

        mapPendingDragEvent = {
            clientX: event.clientX,
            clientY: event.clientY,
        }

        if (mapDragFrame !== null) return

        mapDragFrame = requestAnimationFrame(() => {
            mapDragFrame = null

            if (!mapIsDragging.value || !mapDragStart || !mapPendingDragEvent) return

            const deltaX = ((mapPendingDragEvent.clientX - mapDragStart.clientX) / mapDragStart.width) * MAP_WIDTH
            const deltaY = ((mapPendingDragEvent.clientY - mapDragStart.clientY) / mapDragStart.height) * MAP_HEIGHT

            if (Math.abs(deltaX) + Math.abs(deltaY) > 3) {
                mapWasDragged.value = true
            }

            setMapCenterFromWorld(mapDragStart.centerX - deltaX, mapDragStart.centerY - deltaY, mapDragStart.zoom)
        })
    }

    const endMapDrag = () => {
        mapIsDragging.value = false
        mapDragStart = null
        mapPendingDragEvent = null
        clearMapTextSelection()
    }

    const handleMapClick = (event) => {
        if (mapWasDragged.value) {
            mapWasDragged.value = false
            return
        }

        const coordinate = coordinateFromMapEvent(event)

        if (activeTab.value === 'generator') {
            if (routeGeneratorForm.route_type === 'point_to_point' && routeGeneratorMapTarget.value === 'destination') {
                setGeneratorDestinationFromCoordinate(coordinate, t('sport_map.generator.destination_point'))
                return
            }

            setGeneratorStartFromCoordinate(coordinate, t('sport_map.generator.start_point'))
            return
        }

        if (activeTab.value === 'routes') {
            setWaypointFromMap(coordinate)
            return
        }

        if (activeTab.value === 'tracks') {
            addManualTrackPoint(coordinate)
            return
        }

        if (activeTab.value === 'places') {
            setPlaceFromMap(coordinate)
        }
    }

    const markerStyle = (point) => {
        const projection = mapProjection.value
        const x = lonToWorldX(point.longitude, projection.zoom)
        const y = latToWorldY(point.latitude, projection.zoom)

        return {
            left: `${((x - projection.left) / MAP_WIDTH) * 100}%`,
            top: `${((y - projection.top) / MAP_HEIGHT) * 100}%`,
        }
    }

    const fallbackMapLabelStyle = (label) => {
        const style = markerStyle(label)
        const left = Number(String(style.left).replace('%', ''))
        const top = Number(String(style.top).replace('%', ''))
        const visible = left > -10 && left < 110 && top > -10 && top < 110

        return {
            ...style,
            opacity: visible ? 1 : 0,
        }
    }

    const svgPoint = (point) => {
        const style = markerStyle(point)

        return [
            Number(String(style.left).replace('%', '')),
            Number(String(style.top).replace('%', '')),
        ]
    }

    const polylinePoints = (points) => points
        .filter((point) => Number.isFinite(Number(point.latitude)) && Number.isFinite(Number(point.longitude)))
        .map((point) => svgPoint(point).join(','))
        .join(' ')

    watch([autoMapCenter, autoMapZoom], ([center, zoom]) => {
        if (mapView.value.userChanged) return

        mapView.value.latitude = center.latitude
        mapView.value.longitude = center.longitude
        mapView.value.zoom = zoom
    }, { immediate: true })

    watch(activeMapLayer, () => {
        activeTileSourceIndex.value = 0
    })

    watch([activeMapLayer, activeTileSourceIndex], () => {
        visibleMapTileCount.value = 0
        failedMapTileCount.value = 0
    })

    watch(trackingFullscreen, (isOpen) => {
        if (typeof document === 'undefined') return

        document.body.style.overflow = isOpen ? 'hidden' : ''
    })

    onUnmounted(() => {
        cleanupTrackingSession()
        cleanupRoutePlayback()
        document.body.style.overflow = ''

        if (mapDragFrame !== null) {
            cancelAnimationFrame(mapDragFrame)
        }
    })

    return {
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
        PLAYBACK_SPEED_OPTIONS: sportMapPlaybackSpeedOptions,
        sportMapFallbackLabels,
        sportMapLandingActions,
        sportMapMapLayerOptions,
        sportMapPlaybackSpeedOptions,
        sportMapTabs,
    }
}
