<script setup>
import AppLayout from '@/Components/Auth/Layouts/AppLayout.vue'
import { useForm } from '@inertiajs/vue3'
import { computed, onUnmounted, ref, watch } from 'vue'
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
})

const { t, te } = useI18n()
const activeTab = ref('routes')
const selectedRouteId = ref(props.routes[0]?.id || null)
const trackingPoints = ref([])
const trackingStartedAt = ref(null)
const trackingError = ref('')
const placeLocationError = ref('')
const isTracking = ref(false)
let watchId = null

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
})

const placeSportTypesText = ref('')
const placeAmenitiesText = ref('')
const placeSurfacesText = ref('')

const selectedRoute = computed(() => props.routes.find((item) => item.id === selectedRouteId.value) || props.routes[0] || null)
const routePreviewPoints = computed(() => {
    const formPoints = cleanedWaypoints()

    if (formPoints.length >= 2) return formPoints

    return selectedRoute.value?.waypoints || []
})
const activeTrackPoints = computed(() => trackingPoints.value.length ? trackingPoints.value : (props.tracks[0]?.track_points || []))
const mapPoints = computed(() => [
    ...routePreviewPoints.value.map((point) => ({ ...point, kind: 'route' })),
    ...activeTrackPoints.value.map((point) => ({ ...point, kind: 'track' })),
    ...props.places.map((place) => ({ latitude: place.latitude, longitude: place.longitude, name: place.name, kind: 'place' })),
].filter((point) => point.latitude !== null && point.latitude !== undefined && point.longitude !== null && point.longitude !== undefined))
const bounds = computed(() => {
    if (!mapPoints.value.length) return null

    const latitudes = mapPoints.value.map((point) => Number(point.latitude))
    const longitudes = mapPoints.value.map((point) => Number(point.longitude))

    return {
        north: Math.max(...latitudes),
        south: Math.min(...latitudes),
        east: Math.max(...longitudes),
        west: Math.min(...longitudes),
    }
})
const routePolyline = computed(() => polylinePoints(routePreviewPoints.value))
const trackPolyline = computed(() => polylinePoints(activeTrackPoints.value))
const trackingDistance = computed(() => distanceMeters(trackingPoints.value))

const tabs = [
    { key: 'routes', label: 'sport_map.tabs.routes', icon: 'las la-route' },
    { key: 'tracks', label: 'sport_map.tabs.tracks', icon: 'las la-location-arrow' },
    { key: 'places', label: 'sport_map.tabs.places', icon: 'las la-map-marker-alt' },
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

const splitList = (value) => String(value || '')
    .split(',')
    .map((item) => item.trim())
    .filter(Boolean)

const cleanedWaypoints = () => waypointRows.value
    .map((point) => ({
        name: point.name,
        latitude: point.latitude === '' ? null : Number(point.latitude),
        longitude: point.longitude === '' ? null : Number(point.longitude),
        elevation_m: point.elevation_m === '' ? null : Number(point.elevation_m),
    }))
    .filter((point) => Number.isFinite(point.latitude) && Number.isFinite(point.longitude))

const addWaypoint = () => {
    waypointRows.value.push({
        name: t('sport_map.routes.point_name', { number: waypointRows.value.length + 1 }),
        latitude: '',
        longitude: '',
        elevation_m: '',
    })
}

const removeWaypoint = (index) => {
    if (waypointRows.value.length <= 2) return

    waypointRows.value.splice(index, 1)
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

    if (!navigator.geolocation) {
        placeLocationError.value = t('sport_map.places.location_error')
        return
    }

    navigator.geolocation.getCurrentPosition((position) => {
        placeForm.latitude = position.coords.latitude.toFixed(7)
        placeForm.longitude = position.coords.longitude.toFixed(7)
    }, () => {
        placeLocationError.value = t('sport_map.places.location_error')
    }, {
        enableHighAccuracy: true,
        maximumAge: 10000,
        timeout: 15000,
    })
}

const savePlace = () => {
    placeForm.sport_types = splitList(placeSportTypesText.value)
    placeForm.amenities = splitList(placeAmenitiesText.value)
    placeForm.surfaces = splitList(placeSurfacesText.value)
    placeForm.post(route('auth.sport-places.store'), {
        preserveScroll: true,
        onSuccess: () => {
            placeForm.reset()
            placeForm.type = 'football_pitch'
            placeForm.country_code = 'DE'
            placeForm.visibility = 'public'
            placeSportTypesText.value = ''
            placeAmenitiesText.value = ''
            placeSurfacesText.value = ''
            placeLocationError.value = ''
        },
    })
}

const markerStyle = (point) => {
    if (!bounds.value) return { left: '50%', top: '50%' }

    const width = Math.max(bounds.value.east - bounds.value.west, 0.0001)
    const height = Math.max(bounds.value.north - bounds.value.south, 0.0001)
    const x = ((Number(point.longitude) - bounds.value.west) / width) * 88 + 6
    const y = (1 - ((Number(point.latitude) - bounds.value.south) / height)) * 78 + 11

    return {
        left: `${x}%`,
        top: `${y}%`,
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

const haversine = (from, to) => {
    const radius = 6371000
    const lat1 = Number(from.latitude) * Math.PI / 180
    const lat2 = Number(to.latitude) * Math.PI / 180
    const dLat = (Number(to.latitude) - Number(from.latitude)) * Math.PI / 180
    const dLon = (Number(to.longitude) - Number(from.longitude)) * Math.PI / 180
    const a = Math.sin(dLat / 2) ** 2 + Math.cos(lat1) * Math.cos(lat2) * Math.sin(dLon / 2) ** 2

    return radius * 2 * Math.atan2(Math.sqrt(a), Math.sqrt(1 - a))
}

onUnmounted(stopTracking)
</script>

<template>
    <AppLayout :title="$t('sport_map.title')">
        <div class="mx-auto flex w-full max-w-7xl flex-col gap-5">
            <section class="rounded-lg border border-border bg-card p-4 shadow-sm sm:p-5">
                <div class="flex flex-col gap-4 lg:flex-row lg:items-center lg:justify-between">
                    <div class="max-w-3xl">
                        <p class="text-xs font-semibold uppercase tracking-wide text-secondary">
                            {{ $t('sport_map.eyebrow') }}
                        </p>
                        <h2 class="mt-2 text-2xl font-bold text-primary sm:text-3xl">
                            {{ $t('sport_map.title') }}
                        </h2>
                        <p class="mt-2 text-sm leading-6 text-secondary">
                            {{ $t('sport_map.subtitle') }}
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

            <section class="grid gap-5 xl:grid-cols-[minmax(0,1.25fr),minmax(320px,0.75fr)]">
                <div class="overflow-hidden rounded-lg border border-border bg-card shadow-sm">
                    <div class="flex items-center justify-between border-b border-border px-4 py-3">
                        <div>
                            <p class="text-sm font-semibold text-primary">{{ $t('sport_map.map_preview') }}</p>
                            <p class="text-xs text-secondary">{{ $t('sport_map.map_hint') }}</p>
                        </div>
                        <span class="rounded-full bg-inputBg px-3 py-1 text-xs font-semibold text-secondary">
                            {{ mapPoints.length }} {{ $t('sport_map.points') }}
                        </span>
                    </div>

                    <div class="relative aspect-[4/3] min-h-[320px] overflow-hidden bg-[radial-gradient(circle_at_20%_20%,rgba(34,197,94,0.18),transparent_30%),linear-gradient(135deg,rgba(59,130,246,0.12),rgba(15,23,42,0.02))] sm:aspect-[16/9]">
                        <div class="absolute inset-0 opacity-30"
                            style="background-image: linear-gradient(to right, currentColor 1px, transparent 1px), linear-gradient(to bottom, currentColor 1px, transparent 1px); background-size: 42px 42px;">
                        </div>

                        <svg class="absolute inset-0 h-full w-full" viewBox="0 0 100 100" preserveAspectRatio="none">
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
                            v-for="(point, index) in mapPoints"
                            :key="`${point.kind}-${index}-${point.latitude}-${point.longitude}`"
                            class="absolute flex h-8 w-8 -translate-x-1/2 -translate-y-1/2 items-center justify-center rounded-full border-2 border-card text-xs font-bold shadow"
                            :class="{
                                'bg-blue-500 text-white': point.kind === 'route',
                                'bg-emerald-500 text-white': point.kind === 'track',
                                'bg-amber-400 text-slate-950': point.kind === 'place',
                            }"
                            :style="markerStyle(point)"
                            :title="point.name"
                        >
                            <i v-if="point.kind === 'place'" class="las la-map-marker-alt"></i>
                            <span v-else>{{ index + 1 }}</span>
                        </div>

                        <div v-if="!mapPoints.length" class="absolute inset-0 flex items-center justify-center p-6 text-center">
                            <div>
                                <i class="las la-map-marked-alt text-4xl text-secondary"></i>
                                <p class="mt-2 text-sm font-semibold text-primary">{{ $t('sport_map.empty_map') }}</p>
                                <p class="mt-1 text-xs text-secondary">{{ $t('sport_map.empty_map_hint') }}</p>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="rounded-lg border border-border bg-card p-4 shadow-sm">
                    <div class="grid grid-cols-3 gap-2">
                        <button
                            v-for="tab in tabs"
                            :key="tab.key"
                            type="button"
                            class="flex min-h-11 items-center justify-center gap-2 rounded-lg border px-2 text-sm font-semibold"
                            :class="activeTab === tab.key ? 'border-buttonPrimary bg-buttonPrimary text-buttonTextPrimary' : 'border-border bg-inputBg text-secondary hover:text-primary'"
                            :aria-label="$t(tab.label)"
                            @click="activeTab = tab.key"
                        >
                            <i :class="tab.icon"></i>
                            <span class="hidden sm:inline">{{ $t(tab.label) }}</span>
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

            <section class="rounded-lg border border-border bg-card shadow-sm">
                <div v-if="activeTab === 'routes'" class="grid gap-5 p-4 lg:grid-cols-[minmax(0,0.9fr),minmax(0,1.1fr)]">
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

                        <button type="submit" class="inline-flex min-h-11 items-center justify-center gap-2 rounded-lg bg-buttonPrimary px-4 py-2 text-sm font-semibold text-buttonTextPrimary disabled:opacity-50" :disabled="placeForm.processing">
                            <i class="las la-map-pin"></i>
                            {{ $t('sport_map.places.save') }}
                        </button>
                    </form>

                    <div>
                        <h3 class="text-lg font-bold text-primary">{{ $t('sport_map.places.saved_title') }}</h3>
                        <div class="mt-3 grid gap-3 sm:grid-cols-2">
                            <article v-for="place in places" :key="place.id" class="rounded-lg border border-border bg-inputBg p-4">
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
