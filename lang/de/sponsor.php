<?php

return [
    'flash' => [
        'profile_saved' => 'Sponsorprofil gespeichert. Die öffentlichen Angaben werden jetzt geprüft.',
        'created' => 'Sponsor erstellt.',
        'updated' => 'Sponsor aktualisiert.',
        'deleted' => 'Sponsor gelöscht.',
    ],
    'validation' => [
        'verification_incomplete' => 'Das Sponsorprofil ist noch nicht verifizierbar (:count Pflichtangaben offen).',
    ],
    'notifications' => [
        'review_requested_title' => 'Sponsorprofil wartet auf Prüfung',
        'review_requested_body' => ':sponsor hat öffentliche Profildaten geändert.',
        'review_approved_title' => 'Sponsorprofil freigegeben',
        'review_approved_body' => 'Das öffentliche Profil von :sponsor wurde freigegeben.',
        'review_rejected_title' => 'Sponsorprofil nicht freigegeben',
        'review_rejected_body' => 'Das Profil von :sponsor benötigt Änderungen. Öffne das Cockpit für den Prüfhinweis.',
    ],
    'partnership' => [
        'requested' => 'Partnerschaftsanfrage gesendet.',
        'withdrawn' => 'Partnerschaftsanfrage zurückgezogen.',
        'duplicate' => 'Für diesen Verein besteht bereits eine offene oder aktive Partnerschaft.',
        'withdraw_unavailable' => 'Nur offene Partnerschaftsanfragen können zurückgezogen werden.',
        'already_decided' => 'Diese Partnerschaftsanfrage wurde bereits bearbeitet.',
        'decided' => 'Partnerschaftsanfrage bearbeitet.',
        'notification_title' => 'Neue Partnerschaftsanfrage',
        'notification_body' => ':sponsor möchte eine Partnerschaft mit :club eingehen.',
        'approved_title' => 'Partnerschaftsanfrage angenommen',
        'approved_body' => ':club hat die Partnerschaftsanfrage angenommen.',
        'rejected_title' => 'Partnerschaftsanfrage abgelehnt',
        'rejected_body' => ':club hat die Partnerschaftsanfrage abgelehnt.',
    ],
];
