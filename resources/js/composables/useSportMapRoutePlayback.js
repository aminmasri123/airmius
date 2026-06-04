import { computed, ref, watch } from 'vue'

export function useSportMapRoutePlayback({
    path,
    totalDistance,
    estimatedDurationSeconds,
    baseMetersPerSecond,
    mapView,
    safeMapView,
    interpolateRoutePoint,
    isValidMapCoordinate,
    clamp,
    t,
    defaultSpeed = 16,
}) {
    const routePlaybackState = ref('idle')
    const routePlaybackProgressMeters = ref(0)
    const routePlaybackSpeed = ref(defaultSpeed)
    const routePlaybackStatus = ref('')
    let routePlaybackFrame = null
    let routePlaybackLastTimestamp = null

    const routePlaybackCanStart = computed(() => path.value.length >= 2 && totalDistance.value > 10)
    const routePlaybackMarker = computed(() => {
        if (!routePlaybackCanStart.value) return null

        return interpolateRoutePoint(path.value, routePlaybackProgressMeters.value)
    })
    const routePlaybackProgressPercent = computed(() => {
        if (!totalDistance.value) return 0

        return clamp((routePlaybackProgressMeters.value / totalDistance.value) * 100, 0, 100)
    })
    const routePlaybackElapsedSeconds = computed(() => {
        if (!totalDistance.value) return 0

        return Math.round((routePlaybackProgressMeters.value / totalDistance.value) * estimatedDurationSeconds.value)
    })
    const routePlaybackRemainingSeconds = computed(() => Math.max(0, estimatedDurationSeconds.value - routePlaybackElapsedSeconds.value))

    const currentBaseMetersPerSecond = () => {
        if (typeof baseMetersPerSecond === 'function') {
            return Number(baseMetersPerSecond())
        }

        if (baseMetersPerSecond && typeof baseMetersPerSecond === 'object' && 'value' in baseMetersPerSecond) {
            return Number(baseMetersPerSecond.value)
        }

        return Number(baseMetersPerSecond)
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

    const routePlaybackMetersPerSecond = () => Math.max(0.4, currentBaseMetersPerSecond()) * routePlaybackSpeed.value

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
            totalDistance.value,
            routePlaybackProgressMeters.value + (routePlaybackMetersPerSecond() * deltaSeconds),
        )

        if (routePlaybackMarker.value) {
            centerRoutePlaybackOnMap(routePlaybackMarker.value)
        }

        if (routePlaybackProgressMeters.value >= totalDistance.value) {
            routePlaybackState.value = 'finished'
            routePlaybackStatus.value = t('sport_map.playback.finished_status')
            cancelRoutePlaybackFrame()
            return
        }

        routePlaybackFrame = requestAnimationFrame(tickRoutePlayback)
    }

    const startRoutePlayback = () => {
        if (!routePlaybackCanStart.value) {
            routePlaybackStatus.value = t('sport_map.playback.unavailable_status')
            return
        }

        if (routePlaybackState.value === 'finished' || routePlaybackProgressMeters.value >= totalDistance.value) {
            routePlaybackProgressMeters.value = 0
        }

        routePlaybackState.value = 'playing'
        routePlaybackStatus.value = t('sport_map.playback.running_status')
        cancelRoutePlaybackFrame()

        if (routePlaybackMarker.value) {
            centerRoutePlaybackOnMap(routePlaybackMarker.value)
        }

        routePlaybackFrame = requestAnimationFrame(tickRoutePlayback)
    }

    const pauseRoutePlayback = () => {
        if (routePlaybackState.value !== 'playing') return

        routePlaybackState.value = 'paused'
        routePlaybackStatus.value = t('sport_map.playback.paused_status')
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

    watch(path, () => {
        resetRoutePlayback()
    }, { deep: true })

    return {
        cleanupRoutePlayback: cancelRoutePlaybackFrame,
        pauseRoutePlayback,
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
        startRoutePlayback,
        toggleRoutePlayback,
    }
}
