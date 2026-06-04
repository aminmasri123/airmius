export const trainingLogFallbackSports = [
    { key: 'laufen', label: 'Laufen', category: 'Schnellauswahl' },
    { key: 'gym', label: 'Gym', category: 'Schnellauswahl' },
    { key: 'schwimmen', label: 'Schwimmen', category: 'Schnellauswahl' },
    { key: 'fussball', label: 'Fußball', category: 'Schnellauswahl' },
    { key: 'cycling', label: 'Radfahren', category: 'Schnellauswahl' },
    { key: 'yoga', label: 'Yoga', category: 'Schnellauswahl' },
]

export const trainingLogTypes = [
    {
        key: 'gym',
        label: 'Gym / Krafttraining',
        shortLabel: 'Gym',
        icon: 'las la-dumbbell',
        sport_type: 'gym',
        title: 'Krafttraining',
        fields: ['sets', 'reps', 'weight_kg', 'duration_minutes', 'intensity', 'notes'],
        mode: 'sets',
        detailTitle: 'Übungen und Sätze',
        entryLabel: 'Übung',
        entryPlaceholder: 'z. B. Kniebeugen, Bankdrücken, Core',
    },
    {
        key: 'run_interval',
        label: 'Laufintervall',
        shortLabel: 'Intervalle',
        icon: 'las la-stopwatch',
        sport_type: 'laufen',
        title: 'Laufintervall',
        fields: ['reps', 'distance_km', 'duration_minutes', 'intensity', 'notes'],
        mode: 'rows',
        detailTitle: 'Intervalle',
        entryLabel: 'Intervall / Abschnitt',
        entryPlaceholder: 'z. B. 6 x 400 m, Trabpause, Sprint',
    },
    {
        key: 'long_run',
        label: 'Long Run',
        shortLabel: 'Long Run',
        icon: 'las la-route',
        sport_type: 'laufen',
        title: 'Long Run',
        fields: ['distance_km', 'duration_minutes', 'intensity', 'notes'],
        mode: 'rows',
        detailTitle: 'Streckenabschnitte',
        entryLabel: 'Abschnitt / Kilometerblock',
        entryPlaceholder: 'Optional: z. B. km 1-5 locker, km 12-15 Endbeschleunigung',
    },
    {
        key: 'swim',
        label: 'Schwimmen',
        shortLabel: 'Swim',
        icon: 'las la-swimmer',
        sport_type: 'schwimmen',
        title: 'Schwimmtraining',
        fields: ['sets', 'reps', 'distance_km', 'duration_minutes', 'intensity', 'notes'],
        mode: 'rows',
        detailTitle: 'Serien und Technik',
        entryLabel: 'Serie / Technik',
        entryPlaceholder: 'z. B. 8 x 50 m Kraul, Technik Beine',
    },
    {
        key: 'football',
        label: 'Fußball',
        shortLabel: 'Fußball',
        icon: 'las la-futbol',
        sport_type: 'fussball',
        title: 'Fußballtraining',
        fields: ['duration_minutes', 'distance_km', 'intensity', 'notes'],
        mode: 'rows',
        detailTitle: 'Drills und Spielformen',
        entryLabel: 'Drill / Spielform',
        entryPlaceholder: 'z. B. Passform, 4 gegen 4, Torschuss',
    },
    {
        key: 'cycling',
        label: 'Radtraining',
        shortLabel: 'Bike',
        icon: 'las la-biking',
        sport_type: 'cycling',
        title: 'Radtraining',
        fields: ['distance_km', 'duration_minutes', 'intensity', 'notes'],
        mode: 'rows',
        detailTitle: 'Streckenabschnitte',
        entryLabel: 'Abschnitt',
        entryPlaceholder: 'z. B. Zone 2, Bergintervall, Kadenz',
    },
    {
        key: 'generic',
        label: 'Freies Training',
        shortLabel: 'Frei',
        icon: 'las la-clipboard-list',
        sport_type: 'laufen',
        title: 'Training',
        fields: ['sets', 'reps', 'weight_kg', 'duration_minutes', 'distance_km', 'intensity', 'notes'],
        mode: 'rows',
        detailTitle: 'Übungen / Werte',
        entryLabel: 'Übung / Abschnitt',
        entryPlaceholder: 'z. B. Technik, Drill, Runde',
    },
]

export const trainingLogDetailTemplates = {
    run_interval: [
        {
            key: 'classic_400',
            label: '6 x 400 m',
            description: 'Einlaufen, 6 schnelle Wiederholungen, Auslaufen.',
            entries: [
                { title: 'Einlaufen', duration_minutes: 10, distance_km: 1.5, intensity: 'locker' },
                { title: '6 x 400 m schnell', reps: 6, distance_km: 0.4, intensity: 'RPE 8' },
                { title: 'Trabpause', reps: 6, duration_minutes: 1.5, intensity: 'locker' },
                { title: 'Auslaufen', duration_minutes: 10, distance_km: 1.5, intensity: 'locker' },
            ],
        },
        {
            key: 'pyramid',
            label: 'Pyramide',
            description: '200-400-800-400-200 mit lockeren Pausen.',
            entries: [
                { title: 'Einlaufen', duration_minutes: 12, intensity: 'locker' },
                { title: '200-400-800-400-200 m', reps: 5, distance_km: 2, intensity: 'RPE 7-9' },
                { title: 'Pausen locker traben', duration_minutes: 8, intensity: 'locker' },
            ],
        },
    ],
    long_run: [
        {
            key: 'steady',
            label: 'Ruhiger Dauerlauf',
            description: 'Nur Gesamtdaten plus Gefühl dokumentieren.',
            entries: [],
        },
        {
            key: 'finish',
            label: 'Endbeschleunigung',
            description: 'Lockerer Start, schneller Abschluss.',
            entries: [
                { title: 'Locker laufen', distance_km: 8, intensity: 'Zone 2' },
                { title: 'Endbeschleunigung', distance_km: 3, intensity: 'RPE 7' },
                { title: 'Cool-down', distance_km: 1, intensity: 'locker' },
            ],
        },
    ],
    swim: [
        {
            key: 'technique',
            label: 'Technik + Ausdauer',
            description: 'Einschwimmen, Technikserie, Hauptserie.',
            entries: [
                { title: 'Einschwimmen', distance_km: 0.2, intensity: 'locker' },
                { title: '6 x 50 m Technik', reps: 6, distance_km: 0.05, intensity: 'sauber' },
                { title: '4 x 100 m Kraul', reps: 4, distance_km: 0.1, intensity: 'mittel' },
                { title: 'Ausschwimmen', distance_km: 0.1, intensity: 'locker' },
            ],
        },
    ],
    football: [
        {
            key: 'team_session',
            label: 'Teamtraining',
            description: 'Aktivierung, Technik, Spielform, Abschluss.',
            entries: [
                { title: 'Aktivierung und Mobilität', duration_minutes: 12, intensity: 'locker' },
                { title: 'Passform / Technik', duration_minutes: 18, intensity: 'mittel' },
                { title: 'Spielform 4 gegen 4', duration_minutes: 25, intensity: 'hoch' },
                { title: 'Torschuss / Standards', duration_minutes: 15, intensity: 'mittel' },
            ],
        },
    ],
    cycling: [
        {
            key: 'zone2',
            label: 'Zone 2 Ride',
            description: 'Grundlage mit ruhiger Intensität.',
            entries: [
                { title: 'Einrollen', duration_minutes: 10, intensity: 'locker' },
                { title: 'Zone 2 Block', duration_minutes: 60, intensity: 'Zone 2' },
                { title: 'Ausrollen', duration_minutes: 10, intensity: 'locker' },
            ],
        },
    ],
}

export const trainingLogSteps = [
    { id: 1, label: 'Training wählen', short: 'Start' },
    { id: 2, label: 'Dokumentieren', short: 'Doku' },
    { id: 3, label: 'Abschließen', short: 'Finish' },
]

export const trainingLogTypeThemes = {
    gym: {
        active: 'border-sky-400 bg-sky-500 text-white shadow-sm shadow-sky-500/20',
        idle: 'border-sky-500/30 bg-sky-500/10 text-sky-100 hover:border-sky-400/70 hover:bg-sky-500/20',
        icon: 'bg-sky-400/20 text-sky-100',
    },
    run_interval: {
        active: 'border-amber-300 bg-amber-400 text-slate-950 shadow-sm shadow-amber-400/20',
        idle: 'border-amber-400/30 bg-amber-400/10 text-amber-100 hover:border-amber-300/70 hover:bg-amber-400/20',
        icon: 'bg-amber-300/20 text-amber-100',
    },
    long_run: {
        active: 'border-emerald-300 bg-emerald-500 text-white shadow-sm shadow-emerald-500/20',
        idle: 'border-emerald-400/30 bg-emerald-500/10 text-emerald-100 hover:border-emerald-300/70 hover:bg-emerald-500/20',
        icon: 'bg-emerald-400/20 text-emerald-100',
    },
    swim: {
        active: 'border-cyan-300 bg-cyan-500 text-slate-950 shadow-sm shadow-cyan-500/20',
        idle: 'border-cyan-400/30 bg-cyan-500/10 text-cyan-100 hover:border-cyan-300/70 hover:bg-cyan-500/20',
        icon: 'bg-cyan-300/20 text-cyan-100',
    },
    football: {
        active: 'border-lime-300 bg-lime-500 text-slate-950 shadow-sm shadow-lime-500/20',
        idle: 'border-lime-400/30 bg-lime-500/10 text-lime-100 hover:border-lime-300/70 hover:bg-lime-500/20',
        icon: 'bg-lime-300/20 text-lime-100',
    },
    cycling: {
        active: 'border-fuchsia-300 bg-fuchsia-500 text-white shadow-sm shadow-fuchsia-500/20',
        idle: 'border-fuchsia-400/30 bg-fuchsia-500/10 text-fuchsia-100 hover:border-fuchsia-300/70 hover:bg-fuchsia-500/20',
        icon: 'bg-fuchsia-400/20 text-fuchsia-100',
    },
    generic: {
        active: 'border-indigo-300 bg-indigo-500 text-white shadow-sm shadow-indigo-500/20',
        idle: 'border-indigo-400/30 bg-indigo-500/10 text-indigo-100 hover:border-indigo-300/70 hover:bg-indigo-500/20',
        icon: 'bg-indigo-400/20 text-indigo-100',
    },
}

