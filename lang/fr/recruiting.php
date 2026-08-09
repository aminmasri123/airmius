<?php

return [
    'flash' => [
        'created' => 'Offre créée.',
        'updated' => 'Offre mise à jour.',
        'deleted' => 'Offre supprimée.',
        'interest_sent' => 'Votre manifestation d’intérêt a été envoyée.',
    ],
    'pipeline' => [
        'updated' => 'Le statut de la candidature a été enregistré.',
        'erased' => 'La candidature et les coordonnées ont été supprimées.',
    ],
    'validation' => [
        'profile_login_required' => 'Connecte-toi pour partager des données de profil avec cette candidature.',
        'profile_consent_required' => 'Confirme le partage du profil limité à cette finalité.',
        'invalid_transition' => 'Ce changement de statut n’est pas autorisé dans le parcours de recrutement.',
        'chat_not_available' => 'Le chat de candidature exige un compte lié et un consentement explicite au contact.',
    ],
    'chat' => [
        'name' => 'Candidature : :title',
        'description' => 'Chat de candidature protégé. Partage uniquement les informations nécessaires à cette procédure.',
    ],
    'notifications' => [
        'chat_title' => 'Chat de candidature ouvert',
        'chat_body' => 'Le club a démarré un chat au sujet de ta candidature pour « :title ».',
        'offer_title' => 'Une offre pour ta candidature',
        'offer_body' => ':club t’a fait une offre pour « :title ». Tu peux maintenant ouvrir le parcours d’adhésion.',
        'hired_title' => 'Ta candidature a été retenue',
        'hired_body' => ':club a accepté ta candidature pour « :title ». Termine maintenant le parcours d’adhésion.',
    ],
    'mail' => [
        'subject' => 'Nouvelle manifestation d’intérêt : :title',
        'greeting' => 'Bonjour,',
        'intro' => 'Une nouvelle manifestation d’intérêt a été envoyée pour « :title » auprès de :club.',
        'name' => 'Nom : :name',
        'email' => 'E-mail : :email',
        'phone' => 'Téléphone : :phone',
        'message' => 'Message : :message',
        'action' => 'Ouvrir la page des offres',
    ],
];
