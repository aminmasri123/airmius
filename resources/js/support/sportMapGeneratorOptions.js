export const routeGeneratorSteps = [
    { step: 1, label: 'Basis', labelKey: 'sport_map.generator.step_base' },
    { step: 2, label: 'Stil', labelKey: 'sport_map.generator.step_style' },
    { step: 3, label: 'Vorschlag', labelKey: 'sport_map.generator.step_proposal' },
]

export const routeGeneratorStartModes = [
    { key: 'map_center', label: 'Kartenmitte', labelKey: 'sport_map.generator.start_map_center', icon: 'las la-crosshairs' },
    { key: 'current_location', label: 'Mein Standort', labelKey: 'sport_map.generator.start_current_location', icon: 'las la-location-arrow' },
    { key: 'manual', label: 'Koordinaten', labelKey: 'sport_map.generator.start_manual', icon: 'las la-keyboard' },
]

export const routeGeneratorRouteTypes = [
    { key: 'roundtrip', label: 'Rundroute', labelKey: 'sport_map.generator.route_roundtrip', icon: 'las la-sync', description: 'Start und Ziel sind gleich.', descriptionKey: 'sport_map.generator.route_roundtrip_description' },
    { key: 'point_to_point', label: 'Einmal zum Ziel', labelKey: 'sport_map.generator.route_point_to_point', icon: 'las la-long-arrow-alt-right', description: 'Keine Rückstrecke.', descriptionKey: 'sport_map.generator.route_point_to_point_description' },
]

export const routeGeneratorSurfaceOptions = [
    { key: 'any', label: 'Egal', labelKey: 'sport_map.generator.surface_any' },
    { key: 'asphalt', label: 'Asphalt', labelKey: 'sport_map.generator.surface_asphalt' },
    { key: 'firm', label: 'Fester Boden', labelKey: 'sport_map.generator.surface_firm' },
    { key: 'forest', label: 'Waldweg', labelKey: 'sport_map.generator.surface_forest' },
    { key: 'gravel', label: 'Schotter', labelKey: 'sport_map.generator.surface_gravel' },
    { key: 'trail', label: 'Trail', labelKey: 'sport_map.generator.surface_trail' },
]

export const routeGeneratorEnvironmentOptions = [
    { key: 'any', label: 'Egal', labelKey: 'sport_map.generator.environment_any' },
    { key: 'nature', label: 'Natur', labelKey: 'sport_map.generator.environment_nature' },
    { key: 'forest', label: 'Wald', labelKey: 'sport_map.generator.environment_forest' },
    { key: 'park', label: 'Park/Stadt', labelKey: 'sport_map.generator.environment_park' },
    { key: 'water', label: 'Am Wasser', labelKey: 'sport_map.generator.environment_water' },
]

export const routeGeneratorElevationOptions = [
    { key: 'flat', label: 'Flach', labelKey: 'sport_map.generator.elevation_flat' },
    { key: 'mixed', label: 'Gemischt', labelKey: 'sport_map.generator.elevation_mixed' },
    { key: 'hilly', label: 'Hügelig', labelKey: 'sport_map.generator.elevation_hilly' },
]

export const routeGeneratorDifficultyOptions = [
    { key: 'easy', label: 'Leicht', labelKey: 'sport_map.generator.difficulty_easy' },
    { key: 'moderate', label: 'Mittel', labelKey: 'sport_map.generator.difficulty_moderate' },
    { key: 'hard', label: 'Schwer', labelKey: 'sport_map.generator.difficulty_hard' },
]

export const routeGeneratorSpeedsKmh = {
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


