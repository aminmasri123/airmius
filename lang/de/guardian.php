<?php

return [
    'responses' => [
        'consent_resent' => 'Anfrage zur Zustimmung erneut gesendet.',
        'consent_approved' => 'Zustimmung wurde erteilt.',
        'consent_revoked' => 'Zustimmung wurde widerrufen.',
        'registration_approved' => 'Die Registrierung wurde bestätigt.',
        'registration_rejected' => 'Die Registrierung wurde abgelehnt.',
        'email_resent' => 'Die E-Mail wurde erneut gesendet.',
        'access_revoked' => 'Die Zustimmung wurde widerrufen.',
        'access_approved' => 'Die Ablehnung wurde zurückgenommen und die Zustimmung erteilt.',
        'account_created' => 'Dein Elternkonto wurde erstellt. Du kannst dich jetzt anmelden.',
    ],
    'validation' => [
        'confirmation_required' => 'Bitte bestätigen Sie, dass Sie erziehungsberechtigt sind.',
        'already_approved' => 'Die Zustimmung wurde bereits erteilt.',
        'guardian_email_missing' => 'Es ist keine E-Mail eines Erziehungsberechtigten hinterlegt.',
        'consent_required' => 'Bis zur Zustimmung eines Erziehungsberechtigten bleiben geschützte Funktionen gesperrt.',
        'resend_wait' => 'Bitte warte noch :seconds Sekunden, bevor du die E-Mail erneut sendest.',
    ],
    'notifications' => [
        'approved_title' => 'Zustimmung erteilt',
        'approved_body' => 'Dein Airmius-Konto wurde von einem Erziehungsberechtigten freigegeben.',
        'revoked_title' => 'Zustimmung widerrufen',
        'revoked_body' => 'Die Freigabe deines Airmius-Kontos wurde widerrufen. Bitte kläre das mit deinem Erziehungsberechtigten.',
        'rejected_title' => 'Zustimmung abgelehnt',
        'rejected_body' => 'Die Freigabe deines Airmius-Kontos wurde abgelehnt. Du kannst eine erneute Anfrage auslösen.',
    ],
];
