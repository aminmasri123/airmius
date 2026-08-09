<?php

return [
    'actions' => [
        'confirm_attendance' => ['label' => 'Teilnahme beantworten', 'reason' => 'Für den nächsten Teamtermin fehlt noch deine Rückmeldung.'],
        'remind_missing_responses' => ['label' => 'Rückmeldungen einholen', 'reason' => ':count Teammitglieder haben noch nicht geantwortet.'],
        'check_squad_availability' => ['label' => 'Kaderlage prüfen', 'reason' => 'Für den nächsten Termin sind bisher nur wenige Zusagen vorhanden.'],
        'organize_carpool' => ['label' => 'Fahrgemeinschaft organisieren', 'reason' => 'Für den nächsten Termin sind noch keine freien Plätze sichtbar.'],
        'collect_open_fees' => ['label' => 'Offene Gebühren prüfen', 'reason' => ':count Teamgebühren sind noch offen.'],
        'review_own_fee' => ['label' => 'Eigene Gebühr prüfen', 'reason' => 'Für dich sind :count Gebühren noch offen.'],
        'complete_parent_links' => ['label' => 'Elternverknüpfungen vervollständigen', 'reason' => ':count minderjährige Mitglieder haben noch keine verknüpfte Kontaktperson.'],
        'extend_season_calendar' => ['label' => 'Teamkalender ergänzen', 'reason' => 'Der kommende Teamkalender enthält erst :count Termine.'],
        'team_routine_stable' => ['label' => 'Teamübersicht öffnen', 'reason' => 'Der Teamalltag ist aktuell ohne dringende offene Aktion.'],
    ],
];
