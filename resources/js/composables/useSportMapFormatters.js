const DEFAULT_TRACKING_WEIGHT_KG = 75

const trackingMetBySportType = {
    walking: 3.8,
    wandern: 5.3,
    running: 9.8,
    trail_running: 10.5,
    cycling: 7.5,
    mountainbike: 8.5,
    skateboard: 5,
    fitness: 5,
    football: 7,
    other: 6,
}

const asArray = (value) => {
    const resolved = typeof value === 'function' ? value() : value

    return Array.isArray(resolved) ? resolved : []
}

export function useSportMapFormatters({ t, te, sportTypes = [], placeTypes = [] }) {
    const catalogLabel = (item, fallback = '') => {
        if (!item) return fallback

        return item.label_key && te(item.label_key) ? t(item.label_key) : (item.label || fallback)
    }

    const labelFromCatalog = (items, key, fallback = '') => {
        if (!key) return fallback

        const item = asArray(items).find((entry) => entry.key === key)

        return catalogLabel(item, fallback || key)
    }

    const translatedOrFallback = (key, fallback) => te(key) ? t(key) : fallback
    const tabLabel = (tab) => tab.label.startsWith('sport_map.') ? t(tab.label) : tab.label
    const sportLabel = (key) => labelFromCatalog(sportTypes, key, t('sport_map.any_sport'))
    const placeTypeLabel = (key) => labelFromCatalog(placeTypes, key, key || '-')
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

    const formatClockDuration = (seconds) => {
        const value = Math.max(0, Math.floor(Number(seconds || 0)))
        const hours = Math.floor(value / 3600)
        const minutes = Math.floor((value % 3600) / 60)
        const secs = value % 60

        return hours > 0
            ? `${hours}:${String(minutes).padStart(2, '0')}:${String(secs).padStart(2, '0')}`
            : `${minutes}:${String(secs).padStart(2, '0')}`
    }

    const formatPace = (secondsPerKm) => {
        const value = Number(secondsPerKm || 0)

        if (!Number.isFinite(value) || value <= 0) return '-'

        const minutes = Math.floor(value / 60)
        const seconds = Math.round(value % 60)

        return `${minutes}:${String(seconds).padStart(2, '0')} /km`
    }

    const estimateCalories = (durationSeconds, sportType) => {
        const minutes = Number(durationSeconds || 0) / 60

        if (!Number.isFinite(minutes) || minutes <= 0) return 0

        const met = trackingMetBySportType[sportType] || trackingMetBySportType.other

        return Math.max(0, Math.round((met * 3.5 * DEFAULT_TRACKING_WEIGHT_KG / 200) * minutes))
    }

    const formatCalories = (calories) => {
        const value = Number(calories || 0)

        return value > 0 ? `${Math.round(value)} kcal` : '-'
    }

    const trackAverageSpeedLabel = (track) => {
        const distance = Number(track?.distance_meters || 0)
        const duration = Number(track?.duration_seconds || 0)

        if (distance <= 0 || duration <= 0) return '-'

        return `${((distance / 1000) / (duration / 3600)).toFixed(1)} km/h`
    }

    const trackAveragePaceLabel = (track) => {
        const distance = Number(track?.distance_meters || 0)
        const duration = Number(track?.duration_seconds || 0)

        if (distance <= 0 || duration <= 0) return '-'

        return formatPace(duration / (distance / 1000))
    }

    const trackCaloriesLabel = (track) => formatCalories(track?.metrics?.calories ?? estimateCalories(track?.duration_seconds, track?.sport_type))

    const defaultTrackTitle = () => {
        const now = new Date()

        return `Training ${now.toLocaleDateString('de-DE')} ${now.toLocaleTimeString('de-DE', { hour: '2-digit', minute: '2-digit' })}`
    }

    const formatPercent = (value) => `${Number(value || 0).toFixed(1)} %`

    const cueText = (cue, index) => {
        const type = String(cue?.type || cue?.maneuver_type || 'continue')
        const roadName = String(cue?.road_name || '').trim()
        const road = roadName ? ` ${t('sport_map.cues.road', { road: roadName })}` : ''
        const distance = cue?.distance_meters ? ` - ${formatDistance(cue.distance_meters)}` : ''
        const labelKey = `sport_map.cues.types.${type}`
        const label = te(labelKey)
            ? t(labelKey)
            : t('sport_map.cues.unknown', { number: index + 1 })

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

    const optionLabel = (options, key, fallback = '-') => {
        const option = options.find((item) => item.key === key)

        if (!option) return fallback
        if (option.labelKey) return t(option.labelKey)

        return option.label || fallback
    }

    return {
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
        labelFromCatalog,
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
        translatedOrFallback,
        visibilityLabel,
    }
}
