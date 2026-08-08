<?php

return [
    'providers' => [
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
            'description' => 'Mi Fitness bietet keine einfache Standard-OAuth-Anbindung. Die gewünschte Verknüpfung wird vorgemerkt.',
        ],
    ],
    'summary' => [
        'requested' => 'Verknüpfung vorgemerkt. Wir informieren dich, sobald dieser Anbieter freigeschaltet ist.',
        'connected' => 'Konto verbunden. Du kannst jetzt synchronisieren.',
    ],
    'flash' => [
        'requested' => ':provider wurde vorgemerkt.',
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
    ],
];
