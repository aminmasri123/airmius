<?php

return [
    'status' => [
        'personal' => 'Persönlich',
        'active' => 'Aktiv',
        'foundation_available' => 'Basis vorhanden',
        'training_active' => 'Training aktiv',
        'notifications_active' => 'Benachrichtigungen aktiv',
    ],
    'items' => [
        'athlete' => ['title' => 'Mein Sport', 'description' => 'Feed, Training, Events, Ernährung, Sportkarte und passende Sportpartner.'],
        'coach' => ['title' => 'Trainer-Arbeitsbereich', 'description' => 'Heutige Aufgaben, Teams, Trainingsplanung, Anwesenheit und Feedback.'],
        'club' => ['title' => 'Vereinsverwaltung', 'description' => 'Mitglieder, Teams, Rechnungen, Sponsoren und Vereinskommunikation.'],
        'sponsor' => ['title' => 'Sponsor-Cockpit', 'description' => 'Partnerschaften, Kampagnen, Sichtbarkeit, Angebote und Wirkung zentral steuern.'],
        'guardian' => ['title' => 'Eltern & Guardian', 'description' => 'Kinderprofile, Zustimmung, Sicherheit und Einblick in relevante Vereinsdaten.'],
        'analytics' => ['title' => 'Analyse & Performance', 'description' => 'Leistungsdaten, Training, Feedback und Entwicklungsberichte.'],
        'medical' => ['title' => 'Medical & Recovery', 'description' => 'Belastung, Reha-Hinweise, Freigaben und Feedback im Trainingskontext.'],
        'media' => ['title' => 'Media & Content', 'description' => 'Beiträge, Medien, SEO, Vereinsnews und öffentliche Kommunikation.'],
        'support' => ['title' => 'Support', 'description' => 'Nutzerhilfe, Benachrichtigungen und Eskalationen.'],
    ],
    'work_items' => [
        'created' => 'Aufgabe oder Projekt wurde erstellt.',
        'updated' => 'Aufgabe oder Projekt wurde aktualisiert.',
        'deleted' => 'Aufgabe oder Projekt wurde gelöscht.',
        'club_users_only' => 'Verantwortliche und Beobachter müssen zu diesem Verein gehören.',
        'club_dependencies_only' => 'Abhängigkeiten müssen zu diesem Verein gehören.',
        'invalid_dependency' => 'Ein Vorgang kann nicht von sich selbst abhängen.',
        'invalid_parent' => 'Ein Vorgang kann nicht sein eigener übergeordneter Eintrag sein.',
    ],
];
