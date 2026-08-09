<?php

return [
    'providers' => [
        'apple_health' => [
            'label' => 'Apple Health',
            'description' => 'Apple Health kann über HealthKit und deine ausdrücklich erteilten Berechtigungen normalisierte Trainings- und Routendaten importieren.',
        ],
        'google_fit' => [
            'label' => 'Google Fit',
            'description' => 'Verknüpfung über Google OAuth. Aktivitäten können mit den gespeicherten Tokens synchronisiert werden.',
        ],
        'garmin' => [
            'label' => 'Garmin',
            'description' => 'Die Garmin Health API benötigt eine Anbieterfreigabe. Du kannst Interesse markieren, bis die Partnerfreigabe aktiv ist.',
        ],
        'strava' => [
            'label' => 'Strava',
            'description' => 'Strava verbindet Lauf-, Rad-, Schwimm- und Workout-Daten über die offizielle OAuth-API.',
        ],
        'fitbit' => [
            'label' => 'Fitbit',
            'description' => 'Die Fitbit Web API kann Aktivitäten, Schritte, Distanz, Kalorien und Gesundheitsdaten per OAuth bereitstellen. Die Integration ist vorgemerkt.',
        ],
        'polar' => [
            'label' => 'Polar',
            'description' => 'Polar AccessLink stellt Trainings- und Aktivitätsdaten per OAuth/API bereit. Die Integration ist vorgemerkt.',
        ],
        'mi_fitness' => [
            'label' => 'Mi Fitness',
            'description' => 'Mi Fitness kann über Android Health Connect oder einen normalisierten Dateiimport angebunden werden. Airmius übernimmt nur ausdrücklich freigegebene Aktivitätsfelder.',
        ],
    ],
    'summary' => [
        'requested' => 'Verknüpfung vorgemerkt. Wir informieren dich, sobald dieser Anbieter freigeschaltet ist.',
        'native_ready' => 'Der sichere normalisierte Import ist vorbereitet. Starte den Import in der mobilen Airmius App.',
        'connected' => 'Konto verbunden. Du kannst jetzt synchronisieren.',
        'normalized_import_ready' => 'Normalisierte Aktivitäten können sicher importiert werden.',
        'activity_imported' => 'Aktivität wurde importiert.',
    ],
    'flash' => [
        'requested' => ':provider wurde vorgemerkt.',
        'native_ready' => ':provider ist für den sicheren Import über die mobile Airmius App vorbereitet.',
        'not_configured' => ':provider ist noch nicht konfiguriert. Bitte Client-ID und Client-Secret in der Umgebung hinterlegen.',
        'expired' => 'Die Verbindung mit :provider ist abgelaufen. Bitte klicke erneut auf Verbinden.',
        'token_exchange_failed' => ':provider konnte den Autorisierungscode nicht gegen Tokens tauschen: :error',
        'connected' => ':provider wurde verbunden.',
        'activity_created' => 'Trainingseinheit wurde eingetragen.',
        'disconnected' => 'Sport-App-Verknüpfung entfernt.',
        'activity_deleted' => 'Importierte Aktivität wurde gelöscht.',
        'activity_renamed' => 'Importierte Aktivität wurde umbenannt.',
        'activities_deleted' => ':count importierte Aktivitäten wurden gelöscht.',
    ],
    'errors' => [
        'unknown_provider' => 'Unbekannter Anbieterfehler',
        'route_unavailable' => 'Die gewählte Route ist für dein Konto nicht verfügbar.',
        'team_unavailable' => 'Das gewählte Team gehört nicht zu deinem Konto.',
        'route_team_mismatch' => 'Route und Team gehören nicht zum selben Trainingskontext.',
    ],
];
