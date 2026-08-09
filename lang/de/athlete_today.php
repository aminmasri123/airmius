<?php

return [
    'summary' => [
        'empty' => 'Dein Tag ist noch offen. Starte mit Training, Ernährung oder Wasser.',
        'training' => ':minutes Trainingsminuten',
        'calories' => ':calories kcal',
        'water' => ':water ml Wasser',
        'notifications' => ':count Hinweise',
    ],
    'coach' => [
        'next_training' => 'Deine nächste Einheit ist geplant. Dokumentiere sie direkt danach, damit Belastung und Fortschritt sauber bleiben.',
        'event' => 'Heute steht ein Termin an. Halte Ernährung und Wasser stabil, damit du vorbereitet startest.',
        'start' => 'Plane heute eine kleine, realistische Einheit. Schon 20 bis 30 Minuten halten deinen Rhythmus aktiv.',
        'water' => 'Training ist erledigt. Ziehe jetzt Wasser nach und sichere deine Regeneration.',
        'meal' => 'Training und Wasser sind sichtbar. Trage noch eine Mahlzeit ein, damit der Tagesflow vollständig wird.',
        'complete' => 'Dein Tag ist gut gefüllt. Ergänze bei Bedarf nur noch Feedback, Route oder Notizen.',
    ],
    'steps' => [
        'training' => ['title' => 'Training', 'documented' => 'Training heute dokumentiert', 'plan' => 'Heute eine kurze Einheit planen', 'goal' => ':minutes min Ziel', 'completed' => ':minutes min · :distance', 'document' => 'Dokumentieren', 'start' => 'Training starten'],
        'route' => ['title' => 'Route', 'plan' => 'Neue Route oder Strecke planen', 'meta' => 'GPX, Tracking, Sportkarte', 'open' => 'Route öffnen', 'create' => 'Route planen'],
        'nutrition' => ['title' => 'Ernährung', 'meals' => ':count Mahlzeiten heute', 'meta' => ':current / :target kcal', 'cta' => 'Eintragen'],
        'hydration' => ['title' => 'Wasser', 'body' => ':current ml getrunken', 'meta' => ':target ml Tagesziel', 'cta' => 'Wasser loggen'],
        'reminders' => ['title' => 'Erinnerungen', 'unread' => ':count ungelesene Hinweise', 'none' => 'Keine offenen Termine', 'meta' => 'Inbox & Kalender', 'event_cta' => 'Zum Termin', 'inbox_cta' => 'Inbox öffnen'],
    ],
    'actions' => [
        'catch_up_training' => ['label' => 'Einheit nachholen', 'reason' => 'Eine geplante Einheit dieser Woche ist noch offen.'],
        'complete_training' => ['label' => 'Heutige Einheit starten', 'reason' => 'Dein Plan hat heute Vorrang.'],
        'hydrate' => ['label' => 'Wasser ergänzen', 'reason' => 'Nach dem Training liegt dein Wasserstand noch unter 60 %.'],
        'event' => ['label' => 'Termin vorbereiten', 'reason' => 'Dein nächster Termin findet heute statt.'],
        'meal' => ['label' => 'Mahlzeit eintragen', 'reason' => 'Für heute ist noch keine Mahlzeit dokumentiert.'],
        'start_training' => ['label' => 'Training starten', 'reason' => 'Eine kurze Einheit hält deine Wochenkontinuität aktiv.'],
        'review' => ['label' => 'Woche prüfen', 'reason' => 'Dein Tag ist im Plan. Prüfe Fortschritt und Feedback.'],
    ],
];
