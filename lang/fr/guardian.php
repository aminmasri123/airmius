<?php

return [
    'responses' => [
        'consent_resent' => 'Demande de consentement renvoyée.',
        'consent_approved' => 'Le consentement a été accordé.',
        'consent_revoked' => 'Le consentement a été révoqué.',
        'registration_approved' => 'Inscription confirmée.',
        'registration_rejected' => 'Inscription refusée.',
        'email_resent' => 'L’e-mail a été renvoyé.',
        'access_revoked' => 'Le consentement a été révoqué.',
        'access_approved' => 'Le refus a été annulé et le consentement a été accordé.',
        'account_created' => 'Votre compte parent a été créé. Vous pouvez maintenant vous connecter.',
    ],
    'validation' => [
        'confirmation_required' => 'Confirmez que vous êtes le représentant légal de l’enfant.',
        'already_approved' => 'Le consentement a déjà été accordé.',
        'guardian_email_missing' => 'Aucune adresse e-mail de représentant légal n’est enregistrée.',
        'consent_required' => 'Les fonctions protégées restent verrouillées jusqu’à l’accord d’un représentant légal.',
        'resend_wait' => 'Attendez encore :seconds secondes avant de renvoyer l’e-mail.',
    ],
    'notifications' => [
        'approved_title' => 'Consentement accordé',
        'approved_body' => 'Ton compte Airmius a été approuvé par un représentant légal.',
        'revoked_title' => 'Consentement révoqué',
        'revoked_body' => 'L’autorisation de ton compte Airmius a été révoquée. Parles-en avec ton représentant légal.',
        'rejected_title' => 'Consentement refusé',
        'rejected_body' => 'L’autorisation de ton compte Airmius a été refusée. Tu peux envoyer une nouvelle demande.',
    ],
];
