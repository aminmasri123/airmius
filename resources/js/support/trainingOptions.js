export const sports = [
    { key: 'all', label: 'Alle', icon: 'las la-layer-group', accent: 'bg-air-blue' },
    { key: 'laufen', label: 'Laufen', icon: 'las la-running', accent: 'bg-emerald-500', metrics: ['Distanz km', 'Pace Ziel', 'Höhenmeter', 'RPE'] },
    { key: 'schwimmen', label: 'Schwimmen', icon: 'las la-swimmer', accent: 'bg-cyan-500', metrics: ['Bahnen', 'Stil', 'Intervall', 'Pausenzeit'] },
    { key: 'gym', label: 'Gym', icon: 'las la-dumbbell', accent: 'bg-rose-500', metrics: ['Sätze', 'Wiederholungen', 'Gewicht kg', 'Pause'] },
    { key: 'fussball', label: 'Fußball', icon: 'las la-futbol', accent: 'bg-lime-500', metrics: ['Schwerpunkt', 'Spielfeld', 'Spielerzahl', 'Drill'] },
    { key: 'tanzen', label: 'Tanzen', icon: 'las la-music', accent: 'bg-fuchsia-500', metrics: ['Stil', 'Choreo', 'Takte', 'Tempo'] },
    { key: 'golf', label: 'Golf', icon: 'las la-golf-ball', accent: 'bg-amber-500', metrics: ['Löcher', 'Schläger', 'Schwerpunkt', 'Zielscore'] },
    { key: 'cycling', label: 'Radfahren', icon: 'las la-biking', accent: 'bg-orange-500', metrics: ['Distanz km', 'Watt Ziel', 'Kadenz', 'Höhenmeter'] },
    { key: 'yoga', label: 'Yoga', icon: 'las la-spa', accent: 'bg-violet-500', metrics: ['Flow', 'Atemfokus', 'Level', 'Haltezeit'] },
]

export const planTrainingTypes = [
    { key: 'gym', label: 'Gym', icon: 'las la-dumbbell', sport_type: 'gym', accent: 'bg-sky-500' },
    { key: 'run_interval', label: 'Intervalle', icon: 'las la-stopwatch', sport_type: 'laufen', accent: 'bg-amber-400' },
    { key: 'long_run', label: 'Long Run', icon: 'las la-route', sport_type: 'laufen', accent: 'bg-emerald-500' },
    { key: 'swim', label: 'Swim', icon: 'las la-swimmer', sport_type: 'schwimmen', accent: 'bg-cyan-500' },
    { key: 'football', label: 'Fußball', icon: 'las la-futbol', sport_type: 'fussball', accent: 'bg-lime-500' },
    { key: 'cycling', label: 'Bike', icon: 'las la-biking', sport_type: 'cycling', accent: 'bg-fuchsia-500' },
    { key: 'generic', label: 'Frei', icon: 'las la-clipboard-list', sport_type: 'laufen', accent: 'bg-indigo-500' },
]

export const aiTrainingMethodGroups = {
    laufen: [
        { key: 'long_run', label: 'Ausdauerlauf', hint: 'ruhig und länger', icon: 'las la-route', accent: 'bg-emerald-500' },
        { key: 'run_interval', label: 'Intervalle', hint: 'schnelle Abschnitte', icon: 'las la-stopwatch', accent: 'bg-amber-400' },
        { key: 'tempo_run', label: 'Tempolauf', hint: 'kontrolliert hart', icon: 'las la-tachometer-alt', accent: 'bg-rose-500' },
        { key: 'recovery_run', label: 'Regeneration', hint: 'locker erholen', icon: 'las la-leaf', accent: 'bg-lime-500' },
    ],
    gym: [
        { key: 'strength', label: 'Kraft', hint: 'stärker werden', icon: 'las la-dumbbell', accent: 'bg-sky-500' },
        { key: 'hypertrophy', label: 'Muskelaufbau', hint: 'Volumen & Technik', icon: 'las la-fire-alt', accent: 'bg-rose-500' },
        { key: 'gym', label: 'Ganzkörper', hint: 'ausgewogen', icon: 'las la-clipboard-list', accent: 'bg-violet-500' },
        { key: 'mobility', label: 'Mobility', hint: 'Beweglichkeit', icon: 'las la-spa', accent: 'bg-emerald-500' },
    ],
    schwimmen: [
        { key: 'swim', label: 'Technik', hint: 'Wasserlage & Stil', icon: 'las la-swimmer', accent: 'bg-cyan-500' },
        { key: 'swim_interval', label: 'Intervalle', hint: 'Serien & Pausen', icon: 'las la-stopwatch', accent: 'bg-amber-400' },
        { key: 'endurance_swim', label: 'Ausdauer', hint: 'ruhige Meter', icon: 'las la-water', accent: 'bg-blue-500' },
    ],
    fussball: [
        { key: 'football', label: 'Technik & Spiel', hint: 'Ball, Taktik, Spielform', icon: 'las la-futbol', accent: 'bg-lime-500' },
        { key: 'football_conditioning', label: 'Kondition', hint: 'spielnah belastbar', icon: 'las la-running', accent: 'bg-emerald-500' },
        { key: 'football_speed', label: 'Sprints', hint: 'Antritt & Explosivität', icon: 'las la-bolt', accent: 'bg-amber-400' },
    ],
    cycling: [
        { key: 'cycling', label: 'Grundlagenfahrt', hint: 'ruhig und lang', icon: 'las la-biking', accent: 'bg-fuchsia-500' },
        { key: 'bike_interval', label: 'Rad-Intervalle', hint: 'Watt & Pausen', icon: 'las la-stopwatch', accent: 'bg-amber-400' },
        { key: 'hill_ride', label: 'Anstiege', hint: 'Kraft am Berg', icon: 'las la-mountain', accent: 'bg-orange-500' },
    ],
    yoga: [
        { key: 'mobility', label: 'Mobility', hint: 'Beweglichkeit', icon: 'las la-spa', accent: 'bg-violet-500' },
        { key: 'recovery', label: 'Regeneration', hint: 'ruhig & entlastend', icon: 'las la-leaf', accent: 'bg-emerald-500' },
    ],
    default: [
        { key: 'generic', label: 'Freier Plan', hint: 'KI wählt passende Einheiten', icon: 'las la-clipboard-list', accent: 'bg-indigo-500' },
    ],
}

export const defaultTrainingTypeForSport = () => 'balanced'

export const trainingSections = [
    { key: 'overview', label: 'Übersicht', hint: 'Start', icon: 'las la-home' },
    { key: 'plans', label: 'Pläne', hint: 'Aufbau', icon: 'las la-clipboard-list' },
    { key: 'week', label: 'Woche', hint: 'Kalender', icon: 'las la-calendar-week' },
    { key: 'logs', label: 'Logs', hint: 'Dokumentation', icon: 'las la-pen-alt' },
    { key: 'analysis', label: 'Analyse', hint: 'Signale', icon: 'las la-chart-line' },
]

export const planWizardSteps = [
    { label: 'Basis', hint: 'Name & Rhythmus', icon: 'las la-clipboard-list' },
    { label: 'Ziel', hint: 'Zeitraum & Niveau', icon: 'las la-bullseye' },
    { label: 'Freigabe', hint: 'Team & Sportler', icon: 'las la-user-friends' },
    { label: 'Einheit', hint: 'Erstes Training', icon: 'las la-running' },
]

export const aiPlanSteps = [
    { label: 'Ziel', hint: 'Was soll besser werden?', icon: 'las la-bullseye' },
    { label: 'Rahmen', hint: 'Zeit, Niveau, Regeln', icon: 'las la-sliders-h' },
    { label: 'Vorschau', hint: 'Prüfen und speichern', icon: 'las la-check-circle' },
]

export const aiPlanDurationPresets = [
    { label: '1 Monat', weeks: 4, hint: 'Schneller Start' },
    { label: '2 Monate', weeks: 8, hint: 'Aufbau' },
    { label: '3 Monate', weeks: 12, hint: 'Stabiler Block' },
    { label: '6 Monate', weeks: 26, hint: 'Langfristig' },
]

export const exerciseLibrary = [
    { training_type: 'gym', sport_type: 'gym', title: 'Kniebeuge Progression', focus: 'Kraft', duration_minutes: 45, todos: 'Warm-up 10 Minuten\n3-5 Arbeitssätze\nTechnikvideo nach schwerstem Satz', metrics: { Sätze: '4', Wiederholungen: '6-10', 'Gewicht kg': 'RPE 7-8', Pause: '120s' } },
    { training_type: 'long_run', sport_type: 'laufen', title: 'Long Run Zone 2', focus: 'Ausdauer', duration_minutes: 70, todos: 'Locker starten\nPace stabil halten\nLetzte 10 Minuten kontrollieren', metrics: { 'Distanz km': '10-16', 'Pace Ziel': 'Zone 2', Höhenmeter: '-', RPE: '4-5' } },
    { training_type: 'run_interval', sport_type: 'laufen', title: 'Intervall 6 x 400m', focus: 'Tempo', duration_minutes: 50, todos: '15 Minuten einlaufen\n6 x 400m schnell\n200m Trabpause\n10 Minuten auslaufen', metrics: { 'Distanz km': '6-8', 'Pace Ziel': '5k-Pace', Höhenmeter: '-', RPE: '8' } },
    { training_type: 'swim', sport_type: 'schwimmen', title: 'Technik + Intervalle', focus: 'Wasserlage', duration_minutes: 55, todos: '200m einschwimmen\n6 x 50m Technik\n8 x 100m konstant\nlocker ausschwimmen', metrics: { Bahnen: '40+', Stil: 'Frei', Intervall: '100m', Pausenzeit: '20s' } },
    { training_type: 'football', sport_type: 'fussball', title: 'Ballkontrolle + Sprints', focus: 'Explosivität', duration_minutes: 60, todos: 'Koordination\nDribbling-Parcours\n8 x 20m Sprint\nkleines Abschlussspiel', metrics: { Schwerpunkt: 'Technik', Spielfeld: 'Halbfeld', Spielerzahl: '4-8', Drill: 'Sprint + Ball' } },
]

export const cadenceLabels = {
    single: 'Einmalig',
    daily: 'Täglich',
    weekly: 'Wöchentlich',
    monthly: 'Monatlich',
}

export const phaseLabels = {
    base: 'Grundlage',
    build: 'Aufbau',
    peak: 'Peak',
    recovery: 'Regeneration',
    rehab: 'Reha',
}

export const levelLabels = {
    beginner: 'Einsteiger',
    intermediate: 'Fortgeschritten',
    advanced: 'Advanced',
    elite: 'Leistung',
}

export const loadLabels = {
    low: 'Locker',
    medium: 'Mittel',
    high: 'Hoch',
    test: 'Test',
}

export const permissionLabels = {
    read: 'Nur lesen',
    write: 'Mitarbeiten',
}

