<script setup>
import AppLayout from '@/Components/Auth/Layouts/AppLayout.vue'
import { useForm } from '@inertiajs/vue3'
import { computed, onUnmounted, reactive, ref, watch } from 'vue'
import { useI18n } from 'vue-i18n'

const props = defineProps({
    sportTypes: {
        type: Array,
        default: () => [],
    },
    placeTypes: {
        type: Array,
        default: () => [],
    },
    routes: {
        type: Array,
        default: () => [],
    },
    tracks: {
        type: Array,
        default: () => [],
    },
    places: {
        type: Array,
        default: () => [],
    },
    sportCatalog: {
        type: Array,
        default: () => [],
    },
    teams: {
        type: Array,
        default: () => [],
    },
    mapConfig: {
        type: Object,
        default: () => ({}),
    },
})

const { t, te } = useI18n()
const activeTab = ref('landing')
const selectedRouteId = ref(props.routes[0]?.id || null)
const trackingPoints = ref([])
const trackingStartedAt = ref(null)
const trackingError = ref('')
const placeLocationError = ref('')
const placeLocationStatus = ref('')
const draftRouteGeometryPoints = ref([])
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
const isTracking = ref(false)
const routePlaybackState = ref('idle')
const routePlaybackProgressMeters = ref(0)
const routePlaybackSpeed = ref(16)
const routePlaybackStatus = ref('')
let watchId = null
let mapDragStart = null
let mapDragFrame = null
let mapPendingDragEvent = null
let routePlaybackFrame = null
let routePlaybackLastTimestamp = null

const TILE_SIZE = 256
const MAP_WIDTH = 1000
const MAP_HEIGHT = 560
const DEFAULT_CENTER = { latitude: 51.1657, longitude: 10.4515 }
const DEFAULT_ZOOM = 6
const PLAYBACK_SPEED_OPTIONS = [8, 16, 32]
const mapView = ref({
    latitude: DEFAULT_CENTER.latitude,
    longitude: DEFAULT_CENTER.longitude,
    zoom: DEFAULT_ZOOM,
    userChanged: false,
})

const createDefaultWaypoints = () => [
    { name: t('sport_map.routes.start_point'), latitude: '', longitude: '', elevation_m: '' },
    { name: t('sport_map.routes.finish_point'), latitude: '', longitude: '', elevation_m: '' },
]

const routeForm = useForm({
    title: '',
    description: '',
    sport_type: 'running',
    visibility: 'private',
    team_id: null,
    difficulty: 'moderate',
    surface: '',
    waypoints: [],
})

const waypointRows = ref(createDefaultWaypoints())

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
    surface: 'firm',
    environment: 'forest',
    elevation: 'mixed',
    difficulty: 'easy',
    low_traffic: true,
    lit: false,
    water_breaks: false,
    include_places: '',
    avoid_places: '',
})

const trackForm = useForm({
    title: '',
    sport_route_id: null,
    sport_type: 'running',
    status: 'completed',
    started_at: null,
    ended_at: null,
    track_points: [],
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

const routeGeneratorSteps = [
    { step: 1, label: 'Basis' },
    { step: 2, label: 'Stil' },
    { step: 3, label: 'Vorschlag' },
]

const routeGeneratorStartModes = [
    { key: 'map_center', label: 'Kartenmitte', icon: 'las la-crosshairs' },
    { key: 'current_location', label: 'Mein Standort', icon: 'las la-location-arrow' },
    { key: 'manual', label: 'Koordinaten', icon: 'las la-keyboard' },
]

const routeGeneratorRouteTypes = [
    { key: 'roundtrip', label: 'Rundroute', icon: 'las la-sync', description: 'Start und Ziel sind gleich.' },
    { key: 'point_to_point', label: 'Einmal zum Ziel', icon: 'las la-long-arrow-alt-right', description: 'Keine Rückstrecke.' },
]

const routeGeneratorSurfaceOptions = [
    { key: 'any', label: 'Egal' },
    { key: 'asphalt', label: 'Asphalt' },
    { key: 'firm', label: 'Fester Boden' },
    { key: 'forest', label: 'Waldweg' },
    { key: 'gravel', label: 'Schotter' },
    { key: 'trail', label: 'Trail' },
]

const routeGeneratorEnvironmentOptions = [
    { key: 'nature', label: 'Natur' },
    { key: 'forest', label: 'Wald' },
    { key: 'park', label: 'Park/Stadt' },
    { key: 'water', label: 'Am Wasser' },
]

const routeGeneratorElevationOptions = [
    { key: 'flat', label: 'Flach' },
    { key: 'mixed', label: 'Gemischt' },
    { key: 'hilly', label: 'Huegelig' },
]

const routeGeneratorDifficultyOptions = [
    { key: 'easy', label: 'Leicht' },
    { key: 'moderate', label: 'Mittel' },
    { key: 'hard', label: 'Schwer' },
]

const routeGeneratorSpeedsKmh = {
    running: 9,
    trail_running: 7,
    walking: 5,
    wandern: 4.5,
    cycling: 20,
    mountainbike: 15,
    skateboard: 10,
    fitness: 6,
    football: 6,
    other: 7,
}

const isValidMapCoordinate = (point) => {
    const latitude = Number(point?.latitude)
    const longitude = Number(point?.longitude)

    if (!Number.isFinite(latitude) || !Number.isFinite(longitude)) return false
    if (Math.abs(latitude) > 85.05112878 || Math.abs(longitude) > 180) return false

    return !(Math.abs(latitude) < 0.000001 && Math.abs(longitude) < 0.000001)
}

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
            name: 'Start',
            source: 'generator_setup',
        } : null,
        routeGeneratorForm.route_type === 'point_to_point' && routeGeneratorDestinationPoint.value ? {
            ...routeGeneratorDestinationPoint.value,
            name: 'Ziel',
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

const cleanedWaypoints = () => waypointRows.value
    .map((point) => ({
        name: point.name,
        latitude: point.latitude === '' ? null : Number(point.latitude),
        longitude: point.longitude === '' ? null : Number(point.longitude),
        elevation_m: point.elevation_m === '' ? null : Number(point.elevation_m),
    }))
    .filter(isValidMapCoordinate)

const haversine = (from, to) => {
    const radius = 6371000
    const lat1 = Number(from.latitude) * Math.PI / 180
    const lat2 = Number(to.latitude) * Math.PI / 180
    const dLat = (Number(to.latitude) - Number(from.latitude)) * Math.PI / 180
    const dLon = (Number(to.longitude) - Number(from.longitude)) * Math.PI / 180
    const a = Math.sin(dLat / 2) ** 2 + Math.cos(lat1) * Math.cos(lat2) * Math.sin(dLon / 2) ** 2

    return radius * 2 * Math.atan2(Math.sqrt(a), Math.sqrt(1 - a))
}

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
const trackingDistance = computed(() => distanceMeters(trackingPoints.value))
const routePlaybackPath = computed(() => routeLinePoints.value.filter(isValidMapCoordinate))
const routePlaybackTotalDistance = computed(() => distanceMeters(routePlaybackPath.value))
const routePlaybackCanStart = computed(() => routePlaybackPath.value.length >= 2 && routePlaybackTotalDistance.value > 10)
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
const routePlaybackMarker = computed(() => {
    if (!routePlaybackCanStart.value) return null

    return interpolateRoutePoint(routePlaybackPath.value, routePlaybackProgressMeters.value)
})
const routePlaybackProgressPercent = computed(() => {
    if (!routePlaybackTotalDistance.value) return 0

    return clamp((routePlaybackProgressMeters.value / routePlaybackTotalDistance.value) * 100, 0, 100)
})
const routePlaybackElapsedSeconds = computed(() => {
    if (!routePlaybackTotalDistance.value) return 0

    return Math.round((routePlaybackProgressMeters.value / routePlaybackTotalDistance.value) * activeRouteEstimatedDurationSeconds.value)
})
const routePlaybackRemainingSeconds = computed(() => Math.max(0, activeRouteEstimatedDurationSeconds.value - routePlaybackElapsedSeconds.value))
const activeMapPointCount = computed(() => {
    if (activeTab.value === 'generator') return generatedRoutePoints.value.length
    if (activeTab.value === 'routes') return waypointRows.value.filter((point) => point.latitude !== '' && point.longitude !== '').length
    if (activeTab.value === 'tracks') return trackingPoints.value.length
    if (activeTab.value === 'places') return draftPlacePoint.value ? 1 : 0

    return 0
})
const mapLayerOptions = computed(() => {
    const standardUrl = String(props.mapConfig?.tile_url || 'https://tile.openstreetmap.org/{z}/{x}/{y}.png')
    const standardAttribution = String(props.mapConfig?.attribution || '(c) OpenStreetMap contributors')
    const satelliteUrl = String(props.mapConfig?.satellite_tile_url || '')
    const satelliteAttribution = String(props.mapConfig?.satellite_attribution || '(c) Satellite imagery provider')
    const standardSources = [
        {
            url: standardUrl,
            attribution: standardAttribution,
        },
        {
            url: 'https://a.tile.openstreetmap.de/{z}/{x}/{y}.png',
            attribution: '(c) OpenStreetMap contributors',
        },
        {
            url: 'https://tile.openstreetmap.fr/hot/{z}/{x}/{y}.png',
            attribution: '(c) OpenStreetMap contributors, HOT',
        },
        {
            url: 'https://basemaps.cartocdn.com/rastertiles/voyager/{z}/{x}/{y}.png',
            attribution: '(c) OpenStreetMap contributors, CARTO',
        },
    ]

    return [
        {
            key: 'standard',
            label: 'Karte',
            icon: 'las la-map',
            sources: standardSources,
        },
        {
            key: 'outdoor',
            label: 'Outdoor',
            icon: 'las la-mountain',
            sources: [
                {
                    url: 'https://tile.openstreetmap.fr/hot/{z}/{x}/{y}.png',
                    attribution: '(c) OpenStreetMap contributors, HOT',
                },
                ...standardSources,
            ],
        },
        {
            key: 'satellite',
            label: 'Satellit',
            icon: 'las la-satellite',
            sources: satelliteUrl
                ? [
                    {
                        url: satelliteUrl,
                        attribution: satelliteAttribution,
                    },
                    ...standardSources,
                ]
                : standardSources,
        },
        {
            key: 'hybrid',
            label: 'Hybrid',
            icon: 'las la-layer-group',
            sources: satelliteUrl
                ? [
                    {
                        url: satelliteUrl,
                        attribution: satelliteAttribution,
                    },
                    ...standardSources,
                ]
                : standardSources,
            overlay: satelliteUrl
                ? {
                    url: 'https://basemaps.cartocdn.com/rastertiles/voyager_only_labels/{z}/{x}/{y}.png',
                    attribution: '(c) OpenStreetMap contributors, CARTO',
                }
                : null,
        },
    ]
})
const activeMapLayerOption = computed(() => mapLayerOptions.value.find((layer) => layer.key === activeMapLayer.value) || mapLayerOptions.value[0])
const tileSources = computed(() => activeMapLayerOption.value?.sources || [])
const defaultTileSource = computed(() => ({
    url: String(props.mapConfig?.tile_url || 'https://tile.openstreetmap.org/{z}/{x}/{y}.png'),
    attribution: String(props.mapConfig?.attribution || '(c) OpenStreetMap contributors'),
}))
const activeTileSource = computed(() => tileSources.value[activeTileSourceIndex.value] || tileSources.value[0] || defaultTileSource.value)
const tileTemplate = computed(() => activeTileSource.value?.url || defaultTileSource.value.url)
const mapOverlaySource = computed(() => activeMapLayerOption.value?.overlay || null)
const mapAttribution = computed(() => mapOverlaySource.value
    ? `${activeTileSource.value?.attribution || defaultTileSource.value.attribution} - ${mapOverlaySource.value.attribution}`
    : (activeTileSource.value?.attribution || defaultTileSource.value.attribution))
const mapHasRealTiles = computed(() => visibleMapTileCount.value > 0)
const mapStatusText = computed(() => {
    if (manualMapPointStatus.value) return manualMapPointStatus.value
    if (!mapPoints.value.length) return 'Noch keine Punkte. Plane eine Route, starte Tracking oder füge einen Sportplatz hinzu.'

    return `${mapPoints.value.length} Kartenpunkte geladen.`
})
const placePreviewImages = computed(() => [
    ...splitList(placeImageUrlsText.value),
    ...placeImageUploads.value.map((file) => URL.createObjectURL(file)),
].slice(0, 8))

const fallbackMapLabels = [
    { name: 'Hamburg', latitude: 53.5511, longitude: 9.9937 },
    { name: 'Berlin', latitude: 52.52, longitude: 13.405 },
    { name: 'Hannover', latitude: 52.3759, longitude: 9.732 },
    { name: 'Dortmund', latitude: 51.5136, longitude: 7.4653 },
    { name: 'Koeln', latitude: 50.9375, longitude: 6.9603 },
    { name: 'Frankfurt', latitude: 50.1109, longitude: 8.6821 },
    { name: 'Leipzig', latitude: 51.3397, longitude: 12.3731 },
    { name: 'Nuernberg', latitude: 49.4521, longitude: 11.0767 },
    { name: 'Stuttgart', latitude: 48.7758, longitude: 9.1829 },
    { name: 'Muenchen', latitude: 48.1351, longitude: 11.582 },
]

const tabs = [
    { key: 'landing', label: 'Start', icon: 'las la-compass' },
    { key: 'generator', label: 'Route generieren', icon: 'las la-magic' },
    { key: 'routes', label: 'sport_map.tabs.routes', icon: 'las la-route' },
    { key: 'tracks', label: 'sport_map.tabs.tracks', icon: 'las la-location-arrow' },
    { key: 'places', label: 'sport_map.tabs.places', icon: 'las la-map-marker-alt' },
]

const landingActions = [
    {
        key: 'generator',
        title: 'Route generieren',
        description: 'Parameter wählen und direkt einen passenden Routenvorschlag erzeugen.',
        icon: 'las la-magic',
        color: 'border-indigo-400/40 bg-indigo-500/10 text-indigo-100',
    },
    {
        key: 'routes',
        title: 'Route planen',
        description: 'Start und Ziel setzen, Sportart wählen und Strecke mit Vorschau speichern.',
        icon: 'las la-route',
        color: 'border-sky-400/40 bg-sky-500/10 text-sky-100',
    },
    {
        key: 'tracks',
        title: 'Strecke tracken',
        description: 'Live mit GPS aufzeichnen, Distanz sammeln und Training danach speichern.',
        icon: 'las la-location-arrow',
        color: 'border-emerald-400/40 bg-emerald-500/10 text-emerald-100',
    },
    {
        key: 'places',
        title: 'Sportplatz finden',
        description: 'Plaetze in deiner Umgebung ansehen und schneller Trainingsorte entdecken.',
        icon: 'las la-search-location',
        color: 'border-amber-400/40 bg-amber-500/10 text-amber-100',
    },
    {
        key: 'places',
        title: 'Sportplatz eintragen',
        description: 'Adresse, Bilder, Ausstattung und Sportarten für andere hinzufügen.',
        icon: 'las la-map-pin',
        color: 'border-fuchsia-400/40 bg-fuchsia-500/10 text-fuchsia-100',
    },
]

watch(() => props.routes, (routes) => {
    const nextRoutes = Array.isArray(routes) ? routes : []

    if (!nextRoutes.some((item) => item.id === selectedRouteId.value)) {
        selectedRouteId.value = nextRoutes[0]?.id || null
    }
}, { deep: true })

const catalogLabel = (item, fallback = '') => {
    if (!item) return fallback

    return item.label_key && te(item.label_key) ? t(item.label_key) : (item.label || fallback)
}

const labelFromCatalog = (items, key, fallback = '') => {
    if (!key) return fallback

    const item = items.find((entry) => entry.key === key)

    return catalogLabel(item, fallback || key)
}

const translatedOrFallback = (key, fallback) => te(key) ? t(key) : fallback
const tabLabel = (tab) => tab.label.startsWith('sport_map.') ? t(tab.label) : tab.label
const sportLabel = (key) => labelFromCatalog(props.sportTypes, key, t('sport_map.any_sport'))
const placeTypeLabel = (key) => labelFromCatalog(props.placeTypes, key, key || '-')
const visibilityLabel = (value) => value ? translatedOrFallback(`sport_map.visibility.${value}`, value) : '-'
const trackStatusLabel = (value) => value ? translatedOrFallback(`sport_map.tracks.statuses.${value}`, value) : '-'

const formatDistance = (meters) => {
    const value = Number(meters || 0)

    if (value < 1000) return `${Math.round(value)} m`

    return `${(value / 1000).toFixed(2)} km`
}

const formatDuration = (seconds) => {
    const value = Number(seconds || 0)
    const hours = Math.floor(value / 3600)
    const minutes = Math.round((value % 3600) / 60)

    if (hours <= 0) return `${minutes} min`

    return `${hours} h ${String(minutes).padStart(2, '0')} min`
}

const formatPercent = (value) => `${Number(value || 0).toFixed(1)} %`

const cueText = (cue, index) => {
    const type = String(cue?.type || cue?.maneuver_type || 'continue')
    const road = cue?.road_name ? ` auf ${cue.road_name}` : ''
    const distance = cue?.distance_meters ? ` · ${formatDistance(cue.distance_meters)}` : ''
    const label = {
        start: 'Start',
        finish: 'Ziel erreicht',
        turn: 'Abbiegen',
        new_name: 'Weiter',
        continue: 'Geradeaus',
        roundabout: 'Kreisverkehr',
        merge: 'Einfaedeln',
        fork: 'Gabelung',
    }[type] || `Hinweis ${index + 1}`

    return `${label}${road}${distance}`
}

const qualityBadgeClass = (score) => {
    const value = Number(score || 0)

    if (value >= 85) return 'border-emerald-400/50 bg-emerald-500/10 text-emerald-400'
    if (value >= 70) return 'border-air-blue/50 bg-air-blue/10 text-air-blue'
    if (value >= 50) return 'border-amber-400/50 bg-amber-500/10 text-amber-400'

    return 'border-red-400/50 bg-red-500/10 text-red-400'
}

const splitList = (value) => String(value || '')
    .split(',')
    .map((item) => item.trim())
    .filter(Boolean)

const optionLabel = (options, key, fallback = '-') => options.find((option) => option.key === key)?.label || fallback

const routeGeneratorTargetDistanceKm = computed(() => {
    if (routeGeneratorForm.target_mode === 'duration') {
        const speed = routeGeneratorSpeedsKmh[routeGeneratorForm.sport_type] || 7
        return clamp((Number(routeGeneratorForm.duration_minutes) || 45) / 60 * speed, 1, 80)
    }

    return clamp(Number(routeGeneratorForm.distance_km) || 5, 1, 80)
})

const routeGeneratorEstimatedMinutes = computed(() => {
    const speed = routeGeneratorSpeedsKmh[routeGeneratorForm.sport_type] || 7

    return Math.max(10, Math.round((routeGeneratorTargetDistanceKm.value / speed) * 60))
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
            return { latitude, longitude, name: 'Startpunkt' }
        }
    }

    return {
        latitude: safeMapView.value.latitude,
        longitude: safeMapView.value.longitude,
        name: 'Kartenmitte',
    }
})

const routeGeneratorDestinationPoint = computed(() => {
    const latitude = Number(routeGeneratorForm.destination_latitude)
    const longitude = Number(routeGeneratorForm.destination_longitude)

    if (!isValidMapCoordinate({ latitude, longitude })) {
        return null
    }

    return { latitude, longitude, name: 'Ziel' }
})

const routeGeneratorSummary = computed(() => ({
    distance: `${routeGeneratorTargetDistanceKm.value.toFixed(routeGeneratorTargetDistanceKm.value < 10 ? 1 : 0)} km`,
    duration: `${routeGeneratorEstimatedMinutes.value} min`,
    surface: optionLabel(routeGeneratorSurfaceOptions, routeGeneratorForm.surface, 'Egal'),
    environment: optionLabel(routeGeneratorEnvironmentOptions, routeGeneratorForm.environment, 'Natur'),
    elevation: optionLabel(routeGeneratorElevationOptions, routeGeneratorForm.elevation, 'Gemischt'),
    difficulty: optionLabel(routeGeneratorDifficultyOptions, routeGeneratorForm.difficulty, 'Leicht'),
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

const addWaypoint = () => {
    draftRouteGeometryPoints.value = []
    waypointRows.value.push({
        name: t('sport_map.routes.point_name', { number: waypointRows.value.length + 1 }),
        latitude: '',
        longitude: '',
        elevation_m: '',
    })
}

const removeWaypoint = (index) => {
    if (waypointRows.value.length <= 2) return

    draftRouteGeometryPoints.value = []
    waypointRows.value.splice(index, 1)
}

const setWaypointFromMap = (coordinate) => {
    draftRouteGeometryPoints.value = []
    const emptyIndex = waypointRows.value.findIndex((point) => point.latitude === '' || point.longitude === '')
    const nextIndex = emptyIndex >= 0 ? emptyIndex : waypointRows.value.length

    if (emptyIndex < 0) {
        addWaypoint()
    }

    waypointRows.value[nextIndex].latitude = coordinate.latitude.toFixed(7)
    waypointRows.value[nextIndex].longitude = coordinate.longitude.toFixed(7)
    waypointRows.value[nextIndex].name = waypointRows.value[nextIndex].name || t('sport_map.routes.point_name', { number: nextIndex + 1 })
    manualMapPointStatus.value = `Routenpunkt ${nextIndex + 1} wurde aus der Karte übernommen.`
}

const removeRouteWaypointFromMap = (index) => {
    if (!waypointRows.value[index]) return

    draftRouteGeometryPoints.value = []

    if (waypointRows.value.length > 2) {
        waypointRows.value.splice(index, 1)
    } else {
        waypointRows.value[index].latitude = ''
        waypointRows.value[index].longitude = ''
        waypointRows.value[index].elevation_m = ''
    }

    manualMapPointStatus.value = 'Routenpunkt wurde entfernt.'
}

const removeLastRouteWaypoint = () => {
    const lastIndex = [...waypointRows.value]
        .map((point, index) => ({ point, index }))
        .filter(({ point }) => point.latitude !== '' && point.longitude !== '')
        .at(-1)?.index

    if (lastIndex === undefined) return

    removeRouteWaypointFromMap(lastIndex)
}

const resetRouteWaypoints = () => {
    waypointRows.value = createDefaultWaypoints()
    draftRouteGeometryPoints.value = []
    manualMapPointStatus.value = 'Routenpunkte wurden zurückgesetzt.'
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
        ? 'Setze jetzt den Zielpunkt auf der Karte oder per Koordinaten.'
        : 'Rundroute aktiv: Kartenklick setzt den Startpunkt.'
}

const setGeneratorStartFromCoordinate = (coordinate, label = 'Startpunkt') => {
    routeGeneratorForm.start_mode = 'manual'
    routeGeneratorForm.start_latitude = Number(coordinate.latitude).toFixed(7)
    routeGeneratorForm.start_longitude = Number(coordinate.longitude).toFixed(7)
    routeGeneratorStatus.value = `${label} wurde gesetzt.`
    manualMapPointStatus.value = `${label} wurde für den Routengenerator übernommen.`

    if (routeGeneratorForm.route_type === 'point_to_point') {
        routeGeneratorMapTarget.value = 'destination'
    }
}

const setGeneratorDestinationFromCoordinate = (coordinate, label = 'Zielpunkt') => {
    routeGeneratorForm.destination_latitude = Number(coordinate.latitude).toFixed(7)
    routeGeneratorForm.destination_longitude = Number(coordinate.longitude).toFixed(7)
    routeGeneratorStatus.value = `${label} wurde gesetzt.`
    manualMapPointStatus.value = `${label} wurde für die Zielroute übernommen.`
}

const setGeneratorDestinationFromMapCenter = () => {
    setGeneratorDestinationFromCoordinate(safeMapView.value, 'Kartenmitte als Ziel')
}

const clearGeneratorDestination = () => {
    routeGeneratorForm.destination_latitude = ''
    routeGeneratorForm.destination_longitude = ''
    routeGeneratorStatus.value = 'Zielpunkt wurde entfernt.'
    manualMapPointStatus.value = routeGeneratorStatus.value
}

const useCurrentLocationForGenerator = () => {
    routeGeneratorStatus.value = 'Standort wird ermittelt...'

    if (!navigator.geolocation) {
        routeGeneratorStatus.value = 'Standort ist in diesem Browser nicht verfügbar.'
        return
    }

    navigator.geolocation.getCurrentPosition((position) => {
        const point = {
            latitude: position.coords.latitude,
            longitude: position.coords.longitude,
            accuracy_m: Math.round(position.coords.accuracy || 0),
            name: 'Mein Standort',
        }

        if (!isValidMapCoordinate(point)) {
            routeGeneratorStatus.value = 'Standort konnte nicht verwendet werden.'
            return
        }

        currentLocationPoint.value = point
        routeGeneratorForm.start_mode = 'current_location'
        mapView.value.latitude = point.latitude
        mapView.value.longitude = point.longitude
        mapView.value.zoom = Math.max(safeMapView.value.zoom, 15)
        mapView.value.userChanged = true
        routeGeneratorStatus.value = point.accuracy_m
            ? `Startpunkt gesetzt. Genauigkeit ca. ${point.accuracy_m} m.`
            : 'Startpunkt wurde auf deinen Standort gesetzt.'
    }, () => {
        routeGeneratorStatus.value = 'Standort konnte nicht ermittelt werden. Bitte Browser-Berechtigung prüfen.'
    }, {
        enableHighAccuracy: true,
        maximumAge: 10000,
        timeout: 15000,
    })
}

const generatedRouteTitle = () => routeGeneratorForm.title
    || `${optionLabel(routeGeneratorRouteTypes, routeGeneratorForm.route_type, 'Route')} ${routeGeneratorSummary.value.distance}`

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
            name: `Wegpunkt ${index + 1}`,
            source: 'generator_geometry',
        }))
        .filter(isValidMapCoordinate)
}

const generatorWaypointName = (index, isLast) => {
    if (index === 0) return 'Start'
    if (isLast && routeGeneratorForm.route_type === 'roundtrip') return 'Zurück zum Start'

    return `Routenpunkt ${index + 1}`
}

const routeProposalPayload = (waypoints = null) => {
    const start = routeGeneratorStartPoint.value
    const destination = routeGeneratorDestinationPoint.value
    const effectiveWaypoints = waypoints || (
        routeGeneratorForm.route_type === 'point_to_point' && isValidMapCoordinate(start) && isValidMapCoordinate(destination)
            ? [
                {
                    name: start.name || 'Start',
                    latitude: start.latitude,
                    longitude: start.longitude,
                },
                {
                    name: destination.name || 'Ziel',
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
            name: start.name || 'Start',
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
        routeGeneratorStatus.value = `Route wurde auf echten Wegen berechnet: ${formatDistance(proposal.distance_meters)}, ${formatDuration(proposal.estimated_duration_seconds)}.`
    } else {
        routeGeneratorStatus.value = 'Routingdienst konnte keine echte Wegstrecke liefern. Bitte Startpunkt/Distanz aendern oder Routing-Konfiguration prüfen.'
    }

    manualMapPointStatus.value = routeGeneratorStatus.value
}

const requestRouteProposal = async (waypoints = null) => {
    isGeneratingRoute.value = true
    routeGeneratorStatus.value = 'Route wird auf echten Wegen berechnet...'

    try {
        const response = await window.axios.post(route('auth.sport-route-proposals.store'), routeProposalPayload(waypoints))
        applyRouteProposal(response.data?.data)
    } catch (error) {
        routeGeneratorStatus.value = error.response?.data?.message
            || 'Route konnte nicht berechnet werden. Bitte prüfe Startpunkt, Distanz und Internetverbindung.'
    } finally {
        isGeneratingRoute.value = false
    }
}

const generateRouteProposal = () => {
    if (!isValidMapCoordinate(routeGeneratorStartPoint.value)) {
        routeGeneratorStatus.value = 'Bitte zuerst einen gueltigen Startpunkt wählen.'
        return
    }

    if (routeGeneratorForm.route_type === 'point_to_point' && !isValidMapCoordinate(routeGeneratorDestinationPoint.value)) {
        routeGeneratorStatus.value = 'Bitte für "Einmal zum Ziel" zuerst einen Zielpunkt auf der Karte oder per Koordinaten setzen.'
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
        routeGeneratorStatus.value = 'Routenvorschlag wurde entfernt.'
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
    routeGeneratorStatus.value = 'Routenvorschlag wurde zurückgesetzt.'
    manualMapPointStatus.value = routeGeneratorStatus.value
}

const applyGeneratedRouteToPlanner = () => {
    if (generatedRoutePoints.value.length < 2) {
        routeGeneratorStatus.value = 'Bitte zuerst eine Route generieren.'
        return
    }

    routeForm.title = generatedRouteTitle()
    routeForm.sport_type = routeGeneratorForm.sport_type
    routeForm.difficulty = routeGeneratorForm.difficulty === 'hard' ? 'hard' : routeGeneratorForm.difficulty === 'moderate' ? 'moderate' : 'easy'
    routeForm.surface = routeGeneratorForm.surface === 'any' ? '' : routeGeneratorForm.surface
    routeForm.description = [
        generatedRouteActualSummary.value
            ? `Generiert mit Airmius auf echten Wegen: ${generatedRouteActualSummary.value.distance}, ca. ${generatedRouteActualSummary.value.duration}.`
            : `Generiert mit Airmius: ${routeGeneratorSummary.value.distance}, ca. ${routeGeneratorSummary.value.duration}.`,
        `Untergrund: ${routeGeneratorSummary.value.surface}; Umgebung: ${routeGeneratorSummary.value.environment}; Steigung: ${routeGeneratorSummary.value.elevation}.`,
        routeGeneratorForm.low_traffic ? 'Verkehrsarme Strecke bevorzugt.' : '',
        routeGeneratorForm.lit ? 'Beleuchtete Wege bevorzugt.' : '',
        routeGeneratorForm.water_breaks ? 'Trink- und Pausenpunkte gewuenscht.' : '',
        routeGeneratorForm.include_places ? `Lieblingsorte: ${routeGeneratorForm.include_places}.` : '',
        routeGeneratorForm.avoid_places ? `Vermeiden: ${routeGeneratorForm.avoid_places}.` : '',
    ].filter(Boolean).join('\n')
    waypointRows.value = generatedRoutePoints.value.map((point, index) => ({
        name: point.name || generatorWaypointName(index, index === generatedRoutePoints.value.length - 1),
        latitude: Number(point.latitude).toFixed(7),
        longitude: Number(point.longitude).toFixed(7),
        elevation_m: point.elevation_m ?? '',
    }))
    draftRouteGeometryPoints.value = generatedRouteGeometryPoints.value
    activeTab.value = 'routes'
    manualMapPointStatus.value = 'Routenvorschlag wurde in die Routenplanung übernommen.'
}

const saveRoute = () => {
    routeForm.waypoints = cleanedWaypoints()
    routeForm.post(route('auth.sport-routes.store'), {
        preserveScroll: true,
        onSuccess: () => {
            routeForm.reset()
            routeForm.sport_type = 'running'
            routeForm.visibility = 'private'
            routeForm.difficulty = 'moderate'
            waypointRows.value = createDefaultWaypoints()
            draftRouteGeometryPoints.value = []
        },
    })
}

const startTracking = () => {
    trackingError.value = ''

    if (!navigator.geolocation) {
        trackingError.value = t('sport_map.tracks.location_unsupported')
        return
    }

    trackingPoints.value = []
    trackingStartedAt.value = new Date().toISOString()
    isTracking.value = true

    watchId = navigator.geolocation.watchPosition((position) => {
        trackingPoints.value.push({
            latitude: position.coords.latitude,
            longitude: position.coords.longitude,
            elevation_m: position.coords.altitude,
            accuracy_m: position.coords.accuracy,
            recorded_at: new Date(position.timestamp || Date.now()).toISOString(),
        })
    }, () => {
        trackingError.value = t('sport_map.tracks.location_error')
        stopTracking()
    }, {
        enableHighAccuracy: true,
        maximumAge: 5000,
        timeout: 20000,
    })
}

const stopTracking = () => {
    if (watchId !== null) {
        navigator.geolocation.clearWatch(watchId)
        watchId = null
    }

    isTracking.value = false
}

const addManualTrackPoint = (coordinate) => {
    trackingPoints.value.push({
        latitude: coordinate.latitude,
        longitude: coordinate.longitude,
        elevation_m: null,
        accuracy_m: null,
        recorded_at: new Date().toISOString(),
    })
    trackingStartedAt.value = trackingStartedAt.value || new Date().toISOString()
    manualMapPointStatus.value = `Trackpunkt ${trackingPoints.value.length} wurde manuell gesetzt.`
}

const removeTrackPointFromMap = (index) => {
    if (!trackingPoints.value[index]) return

    trackingPoints.value.splice(index, 1)
    manualMapPointStatus.value = 'Trackpunkt wurde entfernt.'

    if (!trackingPoints.value.length && !isTracking.value) {
        trackingStartedAt.value = null
    }
}

const resetTrackPoints = () => {
    trackingPoints.value = []
    trackingStartedAt.value = null
    manualMapPointStatus.value = 'Trackpunkte wurden zurückgesetzt.'
}

const saveTrack = () => {
    stopTracking()

    trackForm.track_points = trackingPoints.value
    trackForm.started_at = trackingStartedAt.value
    trackForm.ended_at = new Date().toISOString()
    trackForm.status = 'completed'
    trackForm.post(route('auth.sport-tracks.store'), {
        preserveScroll: true,
        onSuccess: () => {
            trackForm.reset()
            trackForm.sport_type = 'running'
            trackForm.status = 'completed'
            trackingPoints.value = []
            trackingStartedAt.value = null
        },
    })
}

const useCurrentLocationForPlace = () => {
    placeLocationError.value = ''
    placeLocationStatus.value = 'Standort wird ermittelt...'

    if (!navigator.geolocation) {
        placeLocationError.value = t('sport_map.places.location_error')
        placeLocationStatus.value = ''
        return
    }

    navigator.geolocation.getCurrentPosition(async (position) => {
        placeForm.latitude = position.coords.latitude.toFixed(7)
        placeForm.longitude = position.coords.longitude.toFixed(7)

        try {
            placeLocationStatus.value = 'Adresse wird gesucht...'
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
            placeLocationStatus.value = 'Standort und Adresse wurden übernommen.'
        } catch (error) {
            placeLocationStatus.value = 'Standort wurde übernommen, Adresse bitte manuell ergaenzen.'
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
    manualMapPointStatus.value = 'Standort wird ermittelt...'

    if (!navigator.geolocation) {
        manualMapPointStatus.value = 'Standort ist in diesem Browser nicht verfügbar.'
        return
    }

    navigator.geolocation.getCurrentPosition((position) => {
        const point = {
            latitude: position.coords.latitude,
            longitude: position.coords.longitude,
            accuracy_m: Math.round(position.coords.accuracy || 0),
            name: 'Mein Standort',
        }

        if (!isValidMapCoordinate(point)) {
            manualMapPointStatus.value = 'Standort konnte nicht auf der Karte angezeigt werden.'
            return
        }

        currentLocationPoint.value = point
        mapView.value.latitude = point.latitude
        mapView.value.longitude = point.longitude
        mapView.value.zoom = Math.max(safeMapView.value.zoom, 15)
        mapView.value.userChanged = true
        manualMapPointStatus.value = point.accuracy_m
            ? `Dein Standort wird angezeigt. Genauigkeit ca. ${point.accuracy_m} m.`
            : 'Dein Standort wird auf der Karte angezeigt.'

        if (activeTab.value === 'generator') {
            routeGeneratorForm.start_mode = 'current_location'
            routeGeneratorStatus.value = 'Dein Standort ist jetzt der Startpunkt für die Routengenerierung.'
        }
    }, () => {
        manualMapPointStatus.value = 'Standort konnte nicht ermittelt werden. Bitte Browser-Berechtigung prüfen.'
    }, {
        enableHighAccuracy: true,
        maximumAge: 10000,
        timeout: 15000,
    })
}

const setPlaceFromMap = (coordinate) => {
    placeForm.latitude = coordinate.latitude.toFixed(7)
    placeForm.longitude = coordinate.longitude.toFixed(7)
    placeLocationStatus.value = 'Koordinaten wurden aus der Karte übernommen.'
    placeLocationError.value = ''
    manualMapPointStatus.value = 'Sportplatz-Position wurde aus der Karte übernommen.'
}

const clearPlaceMapPoint = () => {
    placeForm.latitude = ''
    placeForm.longitude = ''
    placeLocationStatus.value = 'Sportplatz-Position wurde entfernt.'
    manualMapPointStatus.value = 'Sportplatz-Position wurde entfernt.'
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
        manualMapPointStatus.value = 'Kein Punkt zum Entfernen vorhanden.'
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
        manualMapPointStatus.value = 'Karte wurde zentriert. Es gibt aktuell keine Punkte zum Zurücksetzen.'
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

const clamp = (value, min, max) => Math.min(Math.max(value, min), max)

const normalizeLongitude = (longitude) => {
    const value = Number(longitude)

    if (!Number.isFinite(value)) return DEFAULT_CENTER.longitude

    return ((((value + 180) % 360) + 360) % 360) - 180
}

const lonToWorldX = (longitude, zoom) => ((normalizeLongitude(longitude) + 180) / 360) * TILE_SIZE * (2 ** zoom)

const latToWorldY = (latitude, zoom) => {
    const safeLatitude = clamp(Number(latitude), -85.05112878, 85.05112878)
    const sine = Math.sin((safeLatitude * Math.PI) / 180)

    return (0.5 - Math.log((1 + sine) / (1 - sine)) / (4 * Math.PI)) * TILE_SIZE * (2 ** zoom)
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

const mapProjection = computed(() => {
    const zoom = safeMapView.value.zoom
    const center = safeMapView.value

    return {
        zoom,
        left: lonToWorldX(center.longitude, zoom) - MAP_WIDTH / 2,
        top: latToWorldY(center.latitude, zoom) - MAP_HEIGHT / 2,
    }
})

const tileUrlFromTemplate = (template, zoom, x, y) => template
    .replace('{z}', String(zoom))
    .replace('{x}', String(x))
    .replace('{y}', String(y))

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
    const projection = mapProjection.value
    const zoom = projection.zoom
    const tilesPerAxis = 2 ** zoom
    const startX = Math.floor(projection.left / TILE_SIZE) - 1
    const endX = Math.floor((projection.left + MAP_WIDTH) / TILE_SIZE) + 1
    const startY = Math.floor(projection.top / TILE_SIZE) - 1
    const endY = Math.floor((projection.top + MAP_HEIGHT) / TILE_SIZE) + 1
    const tiles = []

    for (let rawX = startX; rawX <= endX; rawX += 1) {
        const x = ((rawX % tilesPerAxis) + tilesPerAxis) % tilesPerAxis

        for (let y = startY; y <= endY; y += 1) {
            if (y < 0 || y >= tilesPerAxis) continue

            tiles.push({
                key: `${activeMapLayer.value}-${activeTileSourceIndex.value}-${zoom}-${rawX}-${y}`,
                url: tileUrl(zoom, x, y),
                style: {
                    left: `${((rawX * TILE_SIZE - projection.left) / MAP_WIDTH) * 100}%`,
                    top: `${((y * TILE_SIZE - projection.top) / MAP_HEIGHT) * 100}%`,
                    width: `${(TILE_SIZE / MAP_WIDTH) * 100}%`,
                    height: `${(TILE_SIZE / MAP_HEIGHT) * 100}%`,
                },
            })
        }
    }

    return tiles
})

const mapOverlayTiles = computed(() => {
    if (!mapOverlaySource.value?.url) return []

    const projection = mapProjection.value
    const zoom = projection.zoom
    const tilesPerAxis = 2 ** zoom
    const startX = Math.floor(projection.left / TILE_SIZE) - 1
    const endX = Math.floor((projection.left + MAP_WIDTH) / TILE_SIZE) + 1
    const startY = Math.floor(projection.top / TILE_SIZE) - 1
    const endY = Math.floor((projection.top + MAP_HEIGHT) / TILE_SIZE) + 1
    const tiles = []

    for (let rawX = startX; rawX <= endX; rawX += 1) {
        const x = ((rawX % tilesPerAxis) + tilesPerAxis) % tilesPerAxis

        for (let y = startY; y <= endY; y += 1) {
            if (y < 0 || y >= tilesPerAxis) continue

            tiles.push({
                key: `overlay-${activeMapLayer.value}-${zoom}-${rawX}-${y}`,
                url: tileUrlFromTemplate(mapOverlaySource.value.url, zoom, x, y),
                style: {
                    left: `${((rawX * TILE_SIZE - projection.left) / MAP_WIDTH) * 100}%`,
                    top: `${((y * TILE_SIZE - projection.top) / MAP_HEIGHT) * 100}%`,
                    width: `${(TILE_SIZE / MAP_WIDTH) * 100}%`,
                    height: `${(TILE_SIZE / MAP_HEIGHT) * 100}%`,
                },
            })
        }
    }

    return tiles
})

const worldXToLon = (x, zoom) => normalizeLongitude((x / (TILE_SIZE * (2 ** zoom))) * 360 - 180)

const worldYToLat = (y, zoom) => {
    const n = Math.PI - (2 * Math.PI * y) / (TILE_SIZE * (2 ** zoom))

    return (180 / Math.PI) * Math.atan(Math.sinh(n))
}

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
            setGeneratorDestinationFromCoordinate(coordinate, 'Zielpunkt')
            return
        }

        setGeneratorStartFromCoordinate(coordinate, 'Startpunkt')
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

const distanceMeters = (points) => {
    let distance = 0

    for (let index = 1; index < points.length; index += 1) {
        distance += haversine(points[index - 1], points[index])
    }

    return Math.round(distance)
}

const interpolateRoutePoint = (points, progressMeters) => {
    const path = points.filter(isValidMapCoordinate)

    if (!path.length) return null
    if (path.length === 1 || progressMeters <= 0) return path[0]

    let walked = 0

    for (let index = 1; index < path.length; index += 1) {
        const from = path[index - 1]
        const to = path[index]
        const segmentDistance = haversine(from, to)

        if (walked + segmentDistance >= progressMeters) {
            const ratio = segmentDistance <= 0 ? 0 : (progressMeters - walked) / segmentDistance

            return {
                latitude: Number(from.latitude) + ((Number(to.latitude) - Number(from.latitude)) * ratio),
                longitude: Number(from.longitude) + ((Number(to.longitude) - Number(from.longitude)) * ratio),
                name: 'Route-Vorschau',
            }
        }

        walked += segmentDistance
    }

    return path[path.length - 1]
}

const cancelRoutePlaybackFrame = () => {
    if (routePlaybackFrame !== null) {
        cancelAnimationFrame(routePlaybackFrame)
        routePlaybackFrame = null
    }

    routePlaybackLastTimestamp = null
}

const centerRoutePlaybackOnMap = (point) => {
    if (!isValidMapCoordinate(point)) return

    mapView.value.latitude = point.latitude
    mapView.value.longitude = point.longitude
    mapView.value.zoom = Math.max(safeMapView.value.zoom, 15)
    mapView.value.userChanged = true
}

const routePlaybackMetersPerSecond = () => {
    const speedKmh = routeGeneratorSpeedsKmh[activeRouteSportType.value] || routeGeneratorSpeedsKmh.other

    return Math.max(0.4, speedKmh / 3.6) * routePlaybackSpeed.value
}

const tickRoutePlayback = (timestamp) => {
    if (routePlaybackState.value !== 'playing') {
        cancelRoutePlaybackFrame()
        return
    }

    if (routePlaybackLastTimestamp === null) {
        routePlaybackLastTimestamp = timestamp
    }

    const deltaSeconds = Math.min((timestamp - routePlaybackLastTimestamp) / 1000, 0.25)
    routePlaybackLastTimestamp = timestamp
    routePlaybackProgressMeters.value = Math.min(
        routePlaybackTotalDistance.value,
        routePlaybackProgressMeters.value + (routePlaybackMetersPerSecond() * deltaSeconds),
    )

    if (routePlaybackMarker.value) {
        centerRoutePlaybackOnMap(routePlaybackMarker.value)
    }

    if (routePlaybackProgressMeters.value >= routePlaybackTotalDistance.value) {
        routePlaybackState.value = 'finished'
        routePlaybackStatus.value = 'Route-Vorschau beendet.'
        cancelRoutePlaybackFrame()
        return
    }

    routePlaybackFrame = requestAnimationFrame(tickRoutePlayback)
}

const startRoutePlayback = () => {
    if (!routePlaybackCanStart.value) {
        routePlaybackStatus.value = 'Bitte zuerst eine Route mit mindestens zwei Punkten planen oder generieren.'
        return
    }

    if (routePlaybackState.value === 'finished' || routePlaybackProgressMeters.value >= routePlaybackTotalDistance.value) {
        routePlaybackProgressMeters.value = 0
    }

    routePlaybackState.value = 'playing'
    routePlaybackStatus.value = 'Route-Vorschau laeuft.'
    cancelRoutePlaybackFrame()

    if (routePlaybackMarker.value) {
        centerRoutePlaybackOnMap(routePlaybackMarker.value)
    }

    routePlaybackFrame = requestAnimationFrame(tickRoutePlayback)
}

const pauseRoutePlayback = () => {
    if (routePlaybackState.value !== 'playing') return

    routePlaybackState.value = 'paused'
    routePlaybackStatus.value = 'Route-Vorschau pausiert.'
    cancelRoutePlaybackFrame()
}

const resetRoutePlayback = (status = '') => {
    cancelRoutePlaybackFrame()
    routePlaybackState.value = 'idle'
    routePlaybackProgressMeters.value = 0
    routePlaybackStatus.value = status
}

const toggleRoutePlayback = () => {
    if (routePlaybackState.value === 'playing') {
        pauseRoutePlayback()
        return
    }

    startRoutePlayback()
}

watch(routePlaybackPath, () => {
    resetRoutePlayback()
}, { deep: true })

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

onUnmounted(() => {
    stopTracking()
    cancelRoutePlaybackFrame()

    if (mapDragFrame !== null) {
        cancelAnimationFrame(mapDragFrame)
    }
})
</script>

<template>
    <AppLayout :title="$t('sport_map.title')">
        <div class="mx-auto flex w-full max-w-7xl flex-col gap-5">
            <section class="rounded-lg border border-border bg-card p-4 shadow-sm sm:p-5">
                <div class="flex flex-col gap-4 lg:flex-row lg:items-center lg:justify-between">
                    <div class="max-w-3xl">
                        <p class="text-xs font-semibold uppercase tracking-wide text-air-blue">
                            Schritt 1 · Sportkarte starten
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
                <div class="overflow-hidden rounded-lg border border-border bg-card shadow-sm">
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
                                {{ mapPoints.length }} {{ $t('sport_map.points') }}
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
                                        {{ formatDuration(routePlaybackElapsedSeconds) }} gelaufen · {{ formatDuration(routePlaybackRemainingSeconds) }} Rest
                                        <span v-if="routePlaybackStatus"> · {{ routePlaybackStatus }}</span>
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

                                <div class="mt-3 grid gap-3 sm:grid-cols-2">
                                    <label class="space-y-1">
                                        <span class="text-xs font-semibold text-secondary">Distanz in km</span>
                                        <input v-model="routeGeneratorForm.distance_km" type="number" min="1" max="80" step="0.5" class="w-full rounded-lg border border-border bg-card px-3 py-2 text-sm">
                                    </label>
                                    <label class="space-y-1">
                                        <span class="text-xs font-semibold text-secondary">Dauer in Minuten</span>
                                        <input v-model="routeGeneratorForm.duration_minutes" type="number" min="10" max="360" step="5" class="w-full rounded-lg border border-border bg-card px-3 py-2 text-sm">
                                    </label>
                                </div>
                            </div>

                            <div class="rounded-xl border border-air-blue/30 bg-air-blue/10 p-4">
                                <p class="text-sm font-bold text-primary">Aktuelle Planung</p>
                                <div class="mt-3 grid grid-cols-2 gap-2 text-sm text-secondary sm:grid-cols-4">
                                    <span>{{ routeGeneratorSummary.distance }}</span>
                                    <span>{{ routeGeneratorSummary.duration }}</span>
                                    <span>{{ routeGeneratorSummary.difficulty }}</span>
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

                            <div class="grid grid-cols-2 gap-2 text-sm sm:grid-cols-3">
                                <div class="rounded-xl border border-border bg-inputBg p-3">
                                    <p class="text-xs font-semibold text-secondary">Ziel-Distanz</p>
                                    <p class="mt-1 font-bold text-primary">{{ routeGeneratorSummary.distance }}</p>
                                </div>
                                <div class="rounded-xl border border-border bg-inputBg p-3">
                                    <p class="text-xs font-semibold text-secondary">Ziel-Dauer</p>
                                    <p class="mt-1 font-bold text-primary">{{ routeGeneratorSummary.duration }}</p>
                                </div>
                                <div class="rounded-xl border border-border bg-inputBg p-3">
                                    <p class="text-xs font-semibold text-secondary">Untergrund</p>
                                    <p class="mt-1 font-bold text-primary">{{ routeGeneratorSummary.surface }}</p>
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
                                <button type="button" class="inline-flex min-h-11 items-center justify-center gap-2 rounded-lg bg-buttonPrimary px-4 py-2 text-sm font-semibold text-buttonTextPrimary disabled:cursor-not-allowed disabled:opacity-60" :disabled="isGeneratingRoute" @click="generateRouteProposal">
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
                                <span class="rounded-full bg-card px-3 py-1 text-xs font-semibold text-primary">{{ generatedRoutePoints.length }} Kontrollpunkte</span>
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
                            <button v-else type="button" class="rounded-lg bg-buttonPrimary px-4 py-2 text-sm font-semibold text-buttonTextPrimary disabled:cursor-not-allowed disabled:opacity-60" :disabled="isGeneratingRoute" @click="generateRouteProposal">
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

                <div v-else-if="activeTab === 'tracks'" class="grid gap-5 p-4 lg:grid-cols-[minmax(0,0.9fr),minmax(0,1.1fr)]">
                    <div class="space-y-4">
                        <div>
                            <h3 class="text-lg font-bold text-primary">{{ $t('sport_map.tracks.title') }}</h3>
                            <p class="mt-1 text-sm text-secondary">{{ $t('sport_map.tracks.hint') }}</p>
                        </div>

                        <div class="grid gap-3 sm:grid-cols-2">
                            <label class="space-y-1">
                                <span class="text-xs font-semibold text-secondary">{{ $t('sport_map.form.title') }}</span>
                                <input v-model="trackForm.title" class="w-full rounded-lg border border-border bg-inputBg px-3 py-2 text-sm">
                            </label>
                            <label class="space-y-1">
                                <span class="text-xs font-semibold text-secondary">{{ $t('sport_map.form.sport') }}</span>
                                <select v-model="trackForm.sport_type" class="w-full rounded-lg border border-border bg-inputBg px-3 py-2 text-sm">
                                    <option v-for="sport in sportTypes" :key="sport.key" :value="sport.key">{{ catalogLabel(sport, sport.key) }}</option>
                                </select>
                            </label>
                            <label class="space-y-1 sm:col-span-2">
                                <span class="text-xs font-semibold text-secondary">{{ $t('sport_map.tracks.route_optional') }}</span>
                                <select v-model="trackForm.sport_route_id" class="w-full rounded-lg border border-border bg-inputBg px-3 py-2 text-sm">
                                    <option :value="null">{{ $t('sport_map.tracks.free_track') }}</option>
                                    <option v-for="routeItem in routes" :key="routeItem.id" :value="routeItem.id">{{ routeItem.title }}</option>
                                </select>
                            </label>
                        </div>

                        <div class="rounded-lg border border-border bg-inputBg p-4">
                            <div class="grid grid-cols-3 gap-2 text-center">
                                <div>
                                    <p class="text-lg font-bold text-primary">{{ trackingPoints.length }}</p>
                                    <p class="text-xs text-secondary">{{ $t('sport_map.points') }}</p>
                                </div>
                                <div>
                                    <p class="text-lg font-bold text-primary">{{ formatDistance(trackingDistance) }}</p>
                                    <p class="text-xs text-secondary">{{ $t('sport_map.tracks.distance') }}</p>
                                </div>
                                <div>
                                    <p class="text-lg font-bold text-primary">{{ isTracking ? $t('sport_map.tracks.live') : '-' }}</p>
                                    <p class="text-xs text-secondary">{{ $t('sport_map.tracks.status') }}</p>
                                </div>
                            </div>

                            <p v-if="trackingError" class="mt-3 rounded-lg bg-red-500/10 px-3 py-2 text-sm text-red-600">
                                {{ trackingError }}
                            </p>

                            <div class="mt-4 flex flex-wrap gap-2">
                                <button v-if="!isTracking" type="button" class="rounded-lg border border-border px-4 py-2 text-sm font-semibold hover:bg-muted" @click="startTracking">
                                    <i class="las la-play"></i>
                                    {{ $t('sport_map.tracks.start') }}
                                </button>
                                <button v-else type="button" class="rounded-lg border border-border px-4 py-2 text-sm font-semibold hover:bg-muted" @click="stopTracking">
                                    <i class="las la-pause"></i>
                                    {{ $t('sport_map.tracks.stop') }}
                                </button>
                                <button type="button" class="rounded-lg bg-buttonPrimary px-4 py-2 text-sm font-semibold text-buttonTextPrimary disabled:opacity-50" :disabled="trackForm.processing || trackingPoints.length < 1 || !trackForm.title" @click="saveTrack">
                                    <i class="las la-save"></i>
                                    {{ $t('sport_map.tracks.save') }}
                                </button>
                            </div>
                        </div>
                    </div>

                    <div>
                        <h3 class="text-lg font-bold text-primary">{{ $t('sport_map.tracks.saved_title') }}</h3>
                        <div class="mt-3 grid gap-3">
                            <article v-for="track in tracks" :key="track.id" class="rounded-lg border border-border bg-inputBg p-4">
                                <div class="flex items-start justify-between gap-3">
                                    <div>
                                        <p class="text-sm font-bold text-primary">{{ track.title }}</p>
                                        <p class="mt-1 text-xs text-secondary">{{ sportLabel(track.sport_type) }} - {{ trackStatusLabel(track.status) }}</p>
                                    </div>
                                    <span class="rounded-full bg-card px-3 py-1 text-xs font-semibold text-primary">{{ formatDistance(track.distance_meters) }}</span>
                                </div>
                                <div class="mt-3 grid grid-cols-3 gap-2 text-xs text-secondary">
                                    <span>{{ formatDuration(track.duration_seconds) }}</span>
                                    <span>{{ $t('sport_map.elevation_gain_meters', { meters: track.elevation_gain_meters || 0 }) }}</span>
                                    <span>{{ $t('sport_map.tracks.points_count', { count: track.track_points?.length || 0 }) }}</span>
                                </div>
                            </article>
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
