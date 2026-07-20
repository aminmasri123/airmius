import {
    sportMapTrackingAnalysisMetrics,
    sportMapTrackingCompactMetrics,
    sportMapTrackingDesktopMetrics,
    sportMapTrackingMobileMetrics,
} from '@/support/sportMapUiOptions'
import { useForm } from '@inertiajs/vue3'
import { computed, ref } from 'vue'

export function useSportMapTrackingSession({
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
}) {
    const trackingPoints = ref([])
    const trackingStartedAt = ref(null)
    const trackingError = ref('')
    const activeTrackingSlide = ref(0)
    const trackingFullscreen = ref(false)
    const editingTrackId = ref(null)
    const isTracking = ref(false)
    const trackingNow = ref(Date.now())
    let watchId = null
    let trackingTimer = null
    let trackingTouchStartX = null
    let trackingPointerStartX = null

    const trackForm = useForm({
        title: '',
        sport_route_id: null,
        sport_type: 'running',
        status: 'completed',
        started_at: null,
        ended_at: null,
        track_points: [],
    })
    const trackGpxImportForm = useForm({
        gpx_file: null,
        sport_type: 'running',
    })

    const editTrackForm = useForm({
        title: '',
        sport_type: 'running',
    })

    const trackingDistance = computed(() => distanceMeters(trackingPoints.value))
    const trackingElapsedSeconds = computed(() => {
        if (!trackingStartedAt.value) return 0

        const start = new Date(trackingStartedAt.value).getTime()
        const lastPointTime = trackingPoints.value.length
            ? new Date(trackingPoints.value[trackingPoints.value.length - 1].recorded_at || trackingStartedAt.value).getTime()
            : start
        const end = isTracking.value ? trackingNow.value : lastPointTime

        return Math.max(0, Math.round((end - start) / 1000))
    })
    const trackingAverageSpeedKmh = computed(() => {
        if (!trackingElapsedSeconds.value || !trackingDistance.value) return 0

        return (trackingDistance.value / 1000) / (trackingElapsedSeconds.value / 3600)
    })
    const trackingAveragePaceSeconds = computed(() => {
        if (!trackingDistance.value) return 0

        return trackingElapsedSeconds.value / (trackingDistance.value / 1000)
    })
    const trackingAverageSpeedLabel = computed(() => trackingAverageSpeedKmh.value > 0 ? `${trackingAverageSpeedKmh.value.toFixed(1)} km/h` : '-')
    const trackingAveragePaceLabel = computed(() => formatPace(trackingAveragePaceSeconds.value))
    const trackingElapsedLabel = computed(() => formatClockDuration(trackingElapsedSeconds.value))
    const trackingCalories = computed(() => estimateCalories(trackingElapsedSeconds.value, trackForm.sport_type))
    const trackingCaloriesLabel = computed(() => formatCalories(trackingCalories.value))
    const trackingLiveStatusLabel = computed(() => {
        if (isTracking.value) return t('sport_map.tracks.live_status.live')

        return trackingPoints.value.length
            ? t('sport_map.tracks.live_status.paused')
            : t('sport_map.tracks.live_status.ready')
    })
    const trackingLastAccuracyLabel = computed(() => {
        const lastPoint = trackingPoints.value[trackingPoints.value.length - 1]
        const accuracy = Number(lastPoint?.accuracy_m || 0)

        return accuracy > 0
            ? t('sport_map.tracks.gps_accuracy', { meters: Math.round(accuracy) })
            : t('sport_map.tracks.gps_ready')
    })
    const trackingStartActionLabel = computed(() => trackingPoints.value.length ? t('sport_map.tracks.continue') : t('sport_map.tracks.start'))
    const trackingStartActionLongLabel = computed(() => trackingPoints.value.length ? t('sport_map.tracks.continue_tracking') : t('sport_map.tracks.start_tracking'))
    const trackingCompactMetrics = computed(() => sportMapTrackingCompactMetrics({
        t,
        pace: trackingAveragePaceLabel.value,
        speed: trackingAverageSpeedLabel.value,
        calories: trackingCaloriesLabel.value,
    }))
    const trackingAnalysisMetrics = computed(() => sportMapTrackingAnalysisMetrics({
        t,
        distance: formatDistance(trackingDistance.value),
        duration: trackingElapsedLabel.value,
        compactMetrics: trackingCompactMetrics.value,
        gps: trackingLastAccuracyLabel.value,
    }))
    const trackingMobileMetrics = computed(() => sportMapTrackingMobileMetrics({
        t,
        compactMetrics: trackingCompactMetrics.value,
        duration: trackingElapsedLabel.value,
        gps: trackingLastAccuracyLabel.value,
    }))
    const trackingDesktopMetrics = computed(() => sportMapTrackingDesktopMetrics({
        t,
        compactMetrics: trackingCompactMetrics.value,
        gps: trackingLastAccuracyLabel.value,
    }))

    const setTrackingSlide = (index) => {
        activeTrackingSlide.value = clamp(index, 0, 2)
    }

    const nextTrackingSlide = () => {
        activeTrackingSlide.value = activeTrackingSlide.value >= 2 ? 0 : activeTrackingSlide.value + 1
    }

    const previousTrackingSlide = () => {
        activeTrackingSlide.value = activeTrackingSlide.value <= 0 ? 2 : activeTrackingSlide.value - 1
    }

    const openTrackingFullscreen = () => {
        trackingFullscreen.value = true
    }

    const closeTrackingFullscreen = () => {
        trackingFullscreen.value = false
    }

    const clearTrackingTimer = () => {
        if (trackingTimer !== null) {
            window.clearInterval(trackingTimer)
            trackingTimer = null
        }
    }

    const startTrackingTimer = () => {
        clearTrackingTimer()
        trackingNow.value = Date.now()
        trackingTimer = window.setInterval(() => {
            trackingNow.value = Date.now()
        }, 1000)
    }

    const stopTracking = () => {
        if (watchId !== null && typeof navigator !== 'undefined' && navigator.geolocation) {
            navigator.geolocation.clearWatch(watchId)
            watchId = null
        }

        isTracking.value = false
        clearTrackingTimer()
    }

    const startTracking = () => {
        trackingError.value = ''

        if (isTracking.value) return

        if (typeof navigator === 'undefined' || !navigator.geolocation) {
            trackingError.value = t('sport_map.tracks.location_unsupported')
            return
        }

        trackForm.title = trackForm.title || defaultTrackTitle()
        trackingStartedAt.value = trackingStartedAt.value || new Date().toISOString()
        isTracking.value = true
        startTrackingTimer()

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

    const startTrackingFromMobile = () => {
        openTrackingFullscreen()
        setTrackingSlide(0)
        startTracking()
    }

    const startTrackingSwipe = (event) => {
        trackingTouchStartX = event.touches?.[0]?.clientX ?? null
    }

    const endTrackingSwipe = (event) => {
        if (trackingTouchStartX === null) return

        const endX = event.changedTouches?.[0]?.clientX ?? trackingTouchStartX
        const delta = endX - trackingTouchStartX
        trackingTouchStartX = null

        if (Math.abs(delta) < 45) return

        if (delta < 0) {
            nextTrackingSlide()
            return
        }

        previousTrackingSlide()
    }

    const startTrackingPointerSwipe = (event) => {
        if (event.pointerType === 'mouse' && event.buttons !== 1) return

        trackingPointerStartX = event.clientX
    }

    const endTrackingPointerSwipe = (event) => {
        if (trackingPointerStartX === null) return

        const delta = event.clientX - trackingPointerStartX
        trackingPointerStartX = null

        if (Math.abs(delta) < 45) return

        if (delta < 0) {
            nextTrackingSlide()
            return
        }

        previousTrackingSlide()
    }

    const cancelTrackingPointerSwipe = () => {
        trackingPointerStartX = null
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
        manualMapPointStatus.value = t('sport_map.map_status.track_point_added', { number: trackingPoints.value.length })
    }

    const removeTrackPointFromMap = (index) => {
        if (!trackingPoints.value[index]) return

        trackingPoints.value.splice(index, 1)
        manualMapPointStatus.value = t('sport_map.map_status.track_point_removed')

        if (!trackingPoints.value.length && !isTracking.value) {
            trackingStartedAt.value = null
        }
    }

    const resetTrackPoints = () => {
        stopTracking()
        trackingPoints.value = []
        trackingStartedAt.value = null
        trackingError.value = ''
        manualMapPointStatus.value = t('sport_map.map_status.track_reset')
    }

    const deleteCurrentTrackDraft = () => {
        resetTrackPoints()
        closeTrackingFullscreen()
    }

    const startTrackEdit = (track) => {
        editingTrackId.value = track.id
        editTrackForm.title = track.title || ''
        editTrackForm.sport_type = track.sport_type || 'running'
        editTrackForm.clearErrors()
    }

    const cancelTrackEdit = () => {
        editingTrackId.value = null
        editTrackForm.reset()
        editTrackForm.clearErrors()
    }

    const updateSavedTrack = (track) => {
        editTrackForm.put(route('auth.sport-tracks.update', track.id), {
            preserveScroll: true,
            onSuccess: () => {
                editingTrackId.value = null
                editTrackForm.reset()
            },
        })
    }

    const saveTrack = () => {
        stopTracking()

        trackForm.title = trackForm.title || defaultTrackTitle()
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
                trackingFullscreen.value = false
            },
        })
    }

    const selectTrackGpxFile = (event) => {
        trackGpxImportForm.gpx_file = event.target.files?.[0] || null
    }

    const importTrackGpx = () => {
        if (!trackGpxImportForm.gpx_file || trackGpxImportForm.processing) return

        trackGpxImportForm.post(route('auth.sport-tracks.import-gpx'), {
            preserveScroll: true,
            onSuccess: () => {
                trackGpxImportForm.reset('gpx_file')
                trackGpxImportForm.sport_type = 'running'
            },
        })
    }

    const cleanupTrackingSession = () => {
        stopTracking()
        trackingTouchStartX = null
        trackingPointerStartX = null
    }

    return {
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
        openTrackingFullscreen,
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
    }
}
