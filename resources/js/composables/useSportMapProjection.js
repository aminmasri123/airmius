const EARTH_RADIUS_METERS = 6371000
const TILE_SIZE = 256
const MAX_MERCATOR_LATITUDE = 85.05112878
const DEFAULT_LONGITUDE = 10.4515

const defaultCoordinateValidator = (point) => {
    const latitude = Number(point?.latitude)
    const longitude = Number(point?.longitude)

    return Number.isFinite(latitude) && Number.isFinite(longitude)
}

export const clamp = (value, min, max) => Math.min(Math.max(value, min), max)

export const normalizeLongitude = (longitude, fallback = DEFAULT_LONGITUDE) => {
    const value = Number(longitude)

    if (!Number.isFinite(value)) return fallback

    return ((((value + 180) % 360) + 360) % 360) - 180
}

export const lonToWorldX = (longitude, zoom) => ((normalizeLongitude(longitude) + 180) / 360) * TILE_SIZE * (2 ** zoom)

export const latToWorldY = (latitude, zoom) => {
    const safeLatitude = clamp(Number(latitude), -MAX_MERCATOR_LATITUDE, MAX_MERCATOR_LATITUDE)
    const sine = Math.sin((safeLatitude * Math.PI) / 180)

    return (0.5 - Math.log((1 + sine) / (1 - sine)) / (4 * Math.PI)) * TILE_SIZE * (2 ** zoom)
}

export const worldXToLon = (x, zoom) => normalizeLongitude((x / (TILE_SIZE * (2 ** zoom))) * 360 - 180)

export const worldYToLat = (y, zoom) => {
    const n = Math.PI - (2 * Math.PI * y) / (TILE_SIZE * (2 ** zoom))

    return (180 / Math.PI) * Math.atan(Math.sinh(n))
}

export const tileUrlFromTemplate = (template, zoom, x, y) => String(template || '')
    .replace('{z}', String(zoom))
    .replace('{x}', String(x))
    .replace('{y}', String(y))

export const haversine = (from, to) => {
    const lat1 = Number(from.latitude) * Math.PI / 180
    const lat2 = Number(to.latitude) * Math.PI / 180
    const dLat = (Number(to.latitude) - Number(from.latitude)) * Math.PI / 180
    const dLon = (Number(to.longitude) - Number(from.longitude)) * Math.PI / 180
    const a = Math.sin(dLat / 2) ** 2 + Math.cos(lat1) * Math.cos(lat2) * Math.sin(dLon / 2) ** 2

    return EARTH_RADIUS_METERS * 2 * Math.atan2(Math.sqrt(a), Math.sqrt(1 - a))
}

export const distanceMeters = (points) => {
    let distance = 0

    for (let index = 1; index < points.length; index += 1) {
        distance += haversine(points[index - 1], points[index])
    }

    return Math.round(distance)
}

export const interpolateRoutePoint = (points, progressMeters, isValidCoordinate = defaultCoordinateValidator) => {
    const path = points.filter(isValidCoordinate)

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
