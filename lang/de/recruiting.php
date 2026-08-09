<?php

return [
    'flash' => [
        'created' => 'Stelle erstellt.',
        'updated' => 'Stelle aktualisiert.',
        'deleted' => 'Stelle gelöscht.',
        'interest_sent' => 'Dein Interesse wurde gesendet.',
    ],
    'pipeline' => [
        'updated' => 'Bewerbungsstatus gespeichert.',
        'erased' => 'Bewerbung und Kontaktdaten wurden gelöscht.',
    ],
    'validation' => [
        'profile_login_required' => 'Melde dich an, um Profildaten für diese Bewerbung freizugeben.',
        'profile_consent_required' => 'Bestätige die zweckgebundene Profilfreigabe.',
        'invalid_transition' => 'Dieser Statuswechsel ist im Recruiting-Ablauf nicht zulässig.',
        'chat_not_available' => 'Ein Bewerbungs-Chat ist ohne verknüpftes Konto und ausdrückliche Kontakteinwilligung nicht verfügbar.',
    ],
    'chat' => [
        'name' => 'Bewerbung: :title',
        'description' => 'Geschützter Chat zur Bewerbung. Teile nur Informationen, die für das Verfahren notwendig sind.',
    ],
    'notifications' => [
        'chat_title' => 'Bewerbungs-Chat geöffnet',
        'chat_body' => 'Der Verein hat einen Chat zu deiner Bewerbung für „:title“ gestartet.',
        'offer_title' => 'Angebot zu deiner Bewerbung',
        'offer_body' => ':club hat dir für „:title“ ein Angebot gemacht. Du kannst jetzt den Mitgliedschaftsprozess öffnen.',
        'hired_title' => 'Deine Bewerbung war erfolgreich',
        'hired_body' => ':club hat deine Bewerbung für „:title“ angenommen. Schließe jetzt den Mitgliedschaftsprozess ab.',
    ],
    'mail' => [
        'subject' => 'Neue Interessenmeldung: :title',
        'greeting' => 'Hallo,',
        'intro' => 'Es gibt eine neue Interessenmeldung für „:title“ bei :club.',
        'name' => 'Name: :name',
        'email' => 'E-Mail: :email',
        'phone' => 'Telefon: :phone',
        'message' => 'Nachricht: :message',
        'action' => 'Jobseite öffnen',
    ],
];
