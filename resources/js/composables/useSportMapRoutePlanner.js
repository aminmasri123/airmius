import { useForm } from '@inertiajs/vue3'
import { ref } from 'vue'

export function useSportMapRoutePlanner({
    isValidMapCoordinate,
    manualMapPointStatus,
    t,
}) {
    const draftRouteGeometryPoints = ref([])

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
        route_geometry: null,
        navigation_cues: [],
        distance_meters: null,
        estimated_duration_seconds: null,
        elevation_gain_meters: null,
        elevation_loss_meters: null,
        metrics: null,
    })

    const routeGpxImportForm = useForm({
        gpx_file: null,
        sport_type: 'running',
        visibility: 'private',
    })

    const waypointRows = ref(createDefaultWaypoints())

    const cleanedWaypoints = () => waypointRows.value
        .map((point) => ({
            name: point.name,
            latitude: point.latitude === '' ? null : Number(point.latitude),
            longitude: point.longitude === '' ? null : Number(point.longitude),
            elevation_m: point.elevation_m === '' ? null : Number(point.elevation_m),
        }))
        .filter(isValidMapCoordinate)

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
        manualMapPointStatus.value = t('sport_map.map_status.route_point_added', { number: nextIndex + 1 })
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

        manualMapPointStatus.value = t('sport_map.map_status.route_point_removed')
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
        manualMapPointStatus.value = t('sport_map.map_status.route_points_reset')
    }

    const resetRouteForm = () => {
        routeForm.reset()
        routeForm.sport_type = 'running'
        routeForm.visibility = 'private'
        routeForm.difficulty = 'moderate'
        routeForm.route_geometry = null
        routeForm.navigation_cues = []
        routeForm.distance_meters = null
        routeForm.estimated_duration_seconds = null
        routeForm.elevation_gain_meters = null
        routeForm.elevation_loss_meters = null
        routeForm.metrics = null
        waypointRows.value = createDefaultWaypoints()
        draftRouteGeometryPoints.value = []
    }

    const saveRoute = () => {
        routeForm.waypoints = cleanedWaypoints()

        if (draftRouteGeometryPoints.value.length < 2) {
            routeForm.route_geometry = null
            routeForm.navigation_cues = []
            routeForm.distance_meters = null
            routeForm.estimated_duration_seconds = null
            routeForm.elevation_gain_meters = null
            routeForm.elevation_loss_meters = null
            routeForm.metrics = null
        } else if (!routeForm.route_geometry) {
            routeForm.route_geometry = {
                type: 'LineString',
                coordinates: draftRouteGeometryPoints.value.map((point) => [
                    Number(point.longitude),
                    Number(point.latitude),
                ]),
            }
        }

        routeForm.post(route('auth.sport-routes.store'), {
            preserveScroll: true,
            onSuccess: resetRouteForm,
        })
    }

    const selectRouteGpxFile = (event) => {
        routeGpxImportForm.gpx_file = event.target.files?.[0] || null
    }

    const importRouteGpx = () => {
        if (!routeGpxImportForm.gpx_file || routeGpxImportForm.processing) return

        routeGpxImportForm.post(route('auth.sport-routes.import-gpx'), {
            preserveScroll: true,
            onSuccess: () => {
                routeGpxImportForm.reset('gpx_file')
                routeGpxImportForm.sport_type = 'running'
                routeGpxImportForm.visibility = 'private'
            },
        })
    }

    return {
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
    }
}
