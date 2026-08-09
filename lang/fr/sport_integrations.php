<?php

return [
    'providers' => [
        'apple_health' => [
            'label' => 'Apple Health',
            'description' => 'Apple Health peut importer des séances et des parcours normalisés via HealthKit et les autorisations que vous accordez explicitement.',
        ],
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
            'description' => 'Mi Fitness peut être connecté via Android Health Connect ou un import de fichier normalisé. Airmius reçoit uniquement les champs d’activité que vous autorisez explicitement.',
        ],
    ],
    'summary' => [
        'requested' => 'Connexion demandée. Nous vous informerons dès que ce fournisseur sera disponible.',
        'native_ready' => 'L’import normalisé sécurisé est prêt. Lancez l’import dans l’application mobile Airmius.',
        'connected' => 'Compte connecté. Vous pouvez maintenant synchroniser.',
        'normalized_import_ready' => 'Les activités normalisées peuvent être importées en toute sécurité.',
        'activity_imported' => 'Activité importée.',
    ],
    'flash' => [
        'requested' => 'La connexion :provider a été demandée.',
        'native_ready' => ':provider est prêt pour l’import sécurisé via l’application mobile Airmius.',
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
        'route_unavailable' => 'Le parcours sélectionné n’est pas disponible pour votre compte.',
        'team_unavailable' => 'L’équipe sélectionnée n’appartient pas à votre compte.',
        'route_team_mismatch' => 'Le parcours et l’équipe n’appartiennent pas au même contexte d’entraînement.',
    ],
];
