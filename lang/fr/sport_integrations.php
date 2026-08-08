<?php

return [
    'providers' => [
        'google_fit' => [
            'label' => 'Google Fit',
            'description' => 'Connexion via Google OAuth. Les activités peuvent être synchronisées avec les jetons enregistrés.',
        ],
        'garmin' => [
            'label' => 'Garmin',
            'description' => 'L’API Garmin Health nécessite l’approbation du fournisseur. Vous pouvez signaler votre intérêt en attendant l’accès partenaire.',
        ],
        'strava' => [
            'label' => 'Strava',
            'description' => 'Strava connecte les données de course, vélo, natation et entraînement via l’API OAuth officielle.',
        ],
        'fitbit' => [
            'label' => 'Fitbit',
            'description' => 'L’API Web Fitbit peut fournir les activités, pas, distances, calories et données de santé via OAuth. L’intégration est planifiée.',
        ],
        'polar' => [
            'label' => 'Polar',
            'description' => 'Polar AccessLink fournit les données d’entraînement et d’activité via OAuth/API. L’intégration est planifiée.',
        ],
        'mi_fitness' => [
            'label' => 'Mi Fitness',
            'description' => 'Mi Fitness ne propose pas de connexion OAuth standard simple. Votre intérêt pour cette connexion sera enregistré.',
        ],
    ],
    'summary' => [
        'requested' => 'Connexion demandée. Nous vous informerons dès que ce fournisseur sera disponible.',
        'connected' => 'Compte connecté. Vous pouvez maintenant synchroniser.',
    ],
    'flash' => [
        'requested' => 'La connexion :provider a été demandée.',
        'not_configured' => ':provider n’est pas encore configuré. Ajoutez l’identifiant et le secret client dans l’environnement.',
        'expired' => 'La connexion :provider a expiré. Sélectionnez de nouveau Connecter.',
        'token_exchange_failed' => ':provider n’a pas pu échanger le code d’autorisation contre des jetons : :error',
        'connected' => ':provider a été connecté.',
        'activity_created' => 'Séance d’entraînement ajoutée.',
        'disconnected' => 'Connexion à l’application sportive supprimée.',
        'activity_deleted' => 'Activité importée supprimée.',
        'activity_renamed' => 'Activité importée renommée.',
        'activities_deleted' => ':count activités importées ont été supprimées.',
    ],
    'errors' => [
        'unknown_provider' => 'Erreur inconnue du fournisseur',
    ],
];
