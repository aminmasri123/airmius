const standardTileUrl = 'https://tile.openstreetmap.org/{z}/{x}/{y}.png'
const standardTileAttribution = '(c) OpenStreetMap contributors'

export const defaultSportMapTileSource = (mapConfig = {}) => ({
    url: String(mapConfig?.tile_url || standardTileUrl),
    attribution: String(mapConfig?.attribution || standardTileAttribution),
})

export const sportMapMapLayerOptions = (mapConfig = {}, t = (key) => key) => {
    const standardSource = defaultSportMapTileSource(mapConfig)
    const satelliteUrl = String(mapConfig?.satellite_tile_url || '')
    const satelliteAttribution = String(mapConfig?.satellite_attribution || '(c) Satellite imagery provider')
    const standardSources = [
        standardSource,
        {
            url: 'https://a.tile.openstreetmap.de/{z}/{x}/{y}.png',
            attribution: standardTileAttribution,
        },
        {
            url: 'https://tile.openstreetmap.fr/hot/{z}/{x}/{y}.png',
            attribution: `${standardTileAttribution}, HOT`,
        },
        {
            url: 'https://basemaps.cartocdn.com/rastertiles/voyager/{z}/{x}/{y}.png',
            attribution: `${standardTileAttribution}, CARTO`,
        },
    ]

    return [
        {
            key: 'standard',
            label: t('sport_map.map_layers.standard'),
            icon: 'las la-map',
            sources: standardSources,
        },
        {
            key: 'outdoor',
            label: t('sport_map.map_layers.outdoor'),
            icon: 'las la-mountain',
            sources: [
                {
                    url: 'https://tile.openstreetmap.fr/hot/{z}/{x}/{y}.png',
                    attribution: `${standardTileAttribution}, HOT`,
                },
                ...standardSources,
            ],
        },
        {
            key: 'satellite',
            label: t('sport_map.map_layers.satellite'),
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
            label: t('sport_map.map_layers.hybrid'),
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
                    attribution: `${standardTileAttribution}, CARTO`,
                }
                : null,
        },
    ]
}

export const sportMapFallbackLabels = [
    { name: 'Hamburg', latitude: 53.5511, longitude: 9.9937 },
    { name: 'Berlin', latitude: 52.52, longitude: 13.405 },
    { name: 'Hannover', latitude: 52.3759, longitude: 9.732 },
    { name: 'Dortmund', latitude: 51.5136, longitude: 7.4653 },
    { name: 'Köln', latitude: 50.9375, longitude: 6.9603 },
    { name: 'Frankfurt', latitude: 50.1109, longitude: 8.6821 },
    { name: 'Leipzig', latitude: 51.3397, longitude: 12.3731 },
    { name: 'Nürnberg', latitude: 49.4521, longitude: 11.0767 },
    { name: 'Stuttgart', latitude: 48.7758, longitude: 9.1829 },
    { name: 'München', latitude: 48.1351, longitude: 11.582 },
]

export const sportMapTabs = [
    { key: 'landing', label: 'sport_map.tabs.start', icon: 'las la-compass' },
    { key: 'generator', label: 'sport_map.tabs.generator', icon: 'las la-magic' },
    { key: 'routes', label: 'sport_map.tabs.routes', icon: 'las la-route' },
    { key: 'tracks', label: 'sport_map.tabs.tracks', icon: 'las la-location-arrow' },
    { key: 'places', label: 'sport_map.tabs.places', icon: 'las la-map-marker-alt' },
]

export const sportMapPlaybackSpeedOptions = [8, 16, 32]

export const sportMapTrackingCompactMetrics = ({ t, pace, speed, calories }) => [
    {
        key: 'pace',
        label: t('sport_map.tracks.metrics.pace'),
        value: pace,
    },
    {
        key: 'speed',
        label: t('sport_map.tracks.metrics.speed'),
        value: speed,
    },
    {
        key: 'calories',
        label: t('sport_map.tracks.metrics.calories'),
        value: calories,
    },
]

export const sportMapTrackingAnalysisMetrics = ({ t, distance, duration, compactMetrics, gps }) => [
    {
        key: 'distance',
        label: t('sport_map.tracks.distance'),
        value: distance,
    },
    {
        key: 'duration',
        label: t('sport_map.tracks.metrics.duration'),
        value: duration,
    },
    ...compactMetrics,
    {
        key: 'gps',
        label: t('sport_map.tracks.metrics.gps'),
        value: gps,
        cardClass: 'col-span-2',
        valueClass: 'mt-2 text-xl font-black text-primary',
    },
]

export const sportMapTrackingMobileMetrics = ({ t, compactMetrics, duration, gps }) => [
    ...compactMetrics,
    {
        key: 'duration',
        label: t('sport_map.tracks.metrics.duration'),
        value: duration,
    },
    {
        key: 'gps',
        label: t('sport_map.tracks.metrics.gps'),
        value: gps,
        valueClass: 'mt-1 text-sm font-black text-primary',
    },
]

export const sportMapTrackingDesktopMetrics = ({ t, compactMetrics, gps }) => [
    ...compactMetrics,
    {
        key: 'gps',
        label: t('sport_map.tracks.metrics.gps'),
        value: gps,
        valueClass: 'mt-1 truncate text-sm font-bold text-primary',
    },
]

export const sportMapLandingActions = [
    {
        key: 'generate-route',
        target: 'generator',
        titleKey: 'sport_map.landing.generate_route_title',
        descriptionKey: 'sport_map.landing.generate_route_description',
        icon: 'las la-magic',
        color: 'border-indigo-400/40 bg-indigo-500/10 text-indigo-100',
    },
    {
        key: 'plan-route',
        target: 'routes',
        titleKey: 'sport_map.landing.plan_route_title',
        descriptionKey: 'sport_map.landing.plan_route_description',
        icon: 'las la-route',
        color: 'border-sky-400/40 bg-sky-500/10 text-sky-100',
    },
    {
        key: 'track-route',
        target: 'tracks',
        titleKey: 'sport_map.landing.track_route_title',
        descriptionKey: 'sport_map.landing.track_route_description',
        icon: 'las la-location-arrow',
        color: 'border-emerald-400/40 bg-emerald-500/10 text-emerald-100',
    },
    {
        key: 'find-place',
        target: 'places',
        titleKey: 'sport_map.landing.find_place_title',
        descriptionKey: 'sport_map.landing.find_place_description',
        icon: 'las la-search-location',
        color: 'border-amber-400/40 bg-amber-500/10 text-amber-100',
    },
    {
        key: 'add-place',
        target: 'places',
        titleKey: 'sport_map.landing.add_place_title',
        descriptionKey: 'sport_map.landing.add_place_description',
        icon: 'las la-map-pin',
        color: 'border-fuchsia-400/40 bg-fuchsia-500/10 text-fuchsia-100',
    },
]

