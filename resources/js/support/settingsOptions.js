export const manualActivityTypeOptions = [
    'Training',
    'Laufen',
    'Radfahren',
    'Schwimmen',
    'Fußball',
    'Fitness',
    'Krafttraining',
    'Yoga',
    'Gehen',
    'Sonstiges',
]

export const settingsThemeOptions = [
    { key: 'air', label: 'Air', descriptionKey: 'air', description: 'Klar, leicht und fokussiert.', colors: ['#0ea5e9', '#10b981', '#f7fbff'] },
    { key: 'dark', label: 'Dark', descriptionKey: 'dark', description: 'Konzentriert für späte Sessions.', colors: ['#0c1016', '#60a5fa', '#34d399'] },
    { key: 'womanly', label: 'Womanly', descriptionKey: 'womanly', description: 'Warm, stark und elegant.', colors: ['#be185d', '#fde8f2', '#0f9f6e'] },
    { key: 'champion', label: 'Champion', descriptionKey: 'champion', description: 'Goldene Energie für Gewinner.', colors: ['#b45309', '#f59e0b', '#fffaf0'] },
    { key: 'sprint', label: 'Sprint', descriptionKey: 'sprint', description: 'Frisch, schnell und aktiv.', colors: ['#059669', '#10b981', '#f5fff9'] },
    { key: 'arena', label: 'Arena', descriptionKey: 'arena', description: 'Ruhig, robust und professionell.', colors: ['#334155', '#64748b', '#f8fafc'] },
    { key: 'pulse', label: 'Pulse', descriptionKey: 'pulse', description: 'Dynamisch und motivierend.', colors: ['#ea580c', '#f97316', '#fff7ed'] },
    { key: 'trail', label: 'Trail', descriptionKey: 'trail', description: 'Natürlich, ausdauernd und bodenständig.', colors: ['#4d7c0f', '#65a30d', '#f6f8f2'] },
    { key: 'bazaar', label: 'Bazaar Rush', descriptionKey: 'bazaar', description: 'Lebendig, verkaufsstark und frisch für Marketplace-Flows.', colors: ['#00a8c6', '#ff8a00', '#ffffff'] },
]

export const trainingDayOptions = [
    { key: 'monday', short: 'Mo', long: 'Montag' },
    { key: 'tuesday', short: 'Di', long: 'Dienstag' },
    { key: 'wednesday', short: 'Mi', long: 'Mittwoch' },
    { key: 'thursday', short: 'Do', long: 'Donnerstag' },
    { key: 'friday', short: 'Fr', long: 'Freitag' },
    { key: 'saturday', short: 'Sa', long: 'Samstag' },
    { key: 'sunday', short: 'So', long: 'Sonntag' },
]

export const trainingDayAliases = {
    monday: ['monday', 'mon', 'mo', 'montag', 'lundi', 'lun', 'ا�"اث�?�S�?'],
    tuesday: ['tuesday', 'tue', 'di', 'dienstag', 'mardi', 'mar', 'ا�"ث�"اثاء'],
    wednesday: ['wednesday', 'wed', 'mi', 'mittwoch', 'mercredi', 'mer', 'ا�"أربعاء', 'ا�"اربعاء'],
    thursday: ['thursday', 'thu', 'do', 'donnerstag', 'jeudi', 'jeu', 'ا�"خ�.�Sس'],
    friday: ['friday', 'fri', 'fr', 'freitag', 'vendredi', 'ven', 'ا�"ج�.عة'],
    saturday: ['saturday', 'sat', 'sa', 'samstag', 'samedi', 'sam', 'ا�"سبت'],
    sunday: ['sunday', 'sun', 'so', 'sonntag', 'dimanche', 'dim', 'ا�"أحد', 'ا�"احد'],
}

export const weekendAliases = ['weekend', 'weekends', 'wochenende', 'week-end', 'عط�"ة', '�?�?ا�Sة']

export const runningBestTimeKeys = [
    'run_best_100m_time',
    'run_best_200m_time',
    'run_best_400m_time',
    'run_best_800m_time',
    'run_best_1500m_time',
    'run_best_3000m_time',
    'best_5k_time',
    'best_10k_time',
    'best_half_marathon_time',
    'best_marathon_time',
]

export const strengthPerformanceKeys = [
    'bodyweight_kg',
    'bench_press_1rm_kg',
    'squat_1rm_kg',
    'deadlift_1rm_kg',
    'overhead_press_1rm_kg',
    'leg_press_1rm_kg',
    'pullups_max_reps',
    'dips_max_reps',
    'pushups_max_reps',
    'plank_seconds',
    'wall_sit_seconds',
    'burpees_1min',
    'jump_rope_1min',
]

export const cyclingPerformanceKeys = [
    'weekly_elevation_m',
    'ftp_watts',
    'power_20min_watts',
    'threshold_hr_bpm',
    'max_hr_bpm',
    'avg_speed_kmh',
    'cadence_rpm',
]

export const swimmingBestTimeKeys = [
    'swim_best_50m_time',
    'swim_best_100m_time',
    'swim_best_200m_time',
    'swim_best_400m_time',
    'swim_best_800m_time',
    'swim_best_1500m_time',
]

export const teamPerformanceKeys = [
    'matches_per_week',
    'match_minutes',
    'sprint_30m_time',
    'cooper_12min_m',
    'yo_yo_level',
    'vertical_jump_cm',
]

export const performanceSectionConfigs = {
    running: {
        keys: runningBestTimeKeys,
        titleKey: 'running_best_times_title',
        title: 'Bestzeiten',
        hintKey: 'running_best_times_hint',
        hint: 'Optional: Trage nur die Distanzen ein, die du wirklich kennst. Das hilft der KI bei Pace, Intervallen und Regeneration.',
        classes: 'border-air-blue/30 bg-air-blue/5',
    },
    strength: {
        keys: strengthPerformanceKeys,
        titleKey: 'strength_performance_title',
        title: 'Kraftwerte & Fitness-Tests',
        hintKey: 'strength_performance_hint',
        hint: 'Optional: Trage geschätzte oder getestete Werte ein. Das hilft der KI bei Übungsauswahl, Intensität, Progression und Regeneration.',
        classes: 'border-success/30 bg-success/5',
    },
    cycling: {
        keys: cyclingPerformanceKeys,
        titleKey: 'cycling_performance_title',
        title: 'Radsport-Leistungswerte',
        hintKey: 'cycling_performance_hint',
        hint: 'Optional: Leistung, Puls, Höhenmeter und Trittfrequenz machen Radpläne deutlich genauer.',
        classes: 'border-info/30 bg-info/5',
    },
    swimming: {
        keys: swimmingBestTimeKeys,
        titleKey: 'swimming_best_times_title',
        title: 'Schwimmzeiten',
        hintKey: 'swimming_best_times_hint',
        hint: 'Optional: Zeiten über mehrere Distanzen helfen bei Intervallen, Techniktempo und Ausdauerbereichen.',
        classes: 'border-air-blue/30 bg-air-blue/5',
    },
    team: {
        keys: teamPerformanceKeys,
        titleKey: 'team_performance_title',
        title: 'Spiel- & Athletikwerte',
        hintKey: 'team_performance_hint',
        hint: 'Optional: Spielbelastung, Sprint, Ausdauer und Sprungkraft helfen bei Belastungssteuerung und Athletik.',
        classes: 'border-warning/30 bg-warning/5',
    },
}

