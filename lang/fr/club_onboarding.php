<?php

return [
    'title' => 'Préparer votre club',
    'subtitle' => 'La progression est calculée automatiquement à partir des données réelles du club, des équipes et des adhésions.',
    'progress' => ':completed étapes terminées sur :total',
    'complete' => 'Terminé',
    'open' => 'Ouvert',
    'steps' => [
        'profile' => ['title' => 'Compléter le profil du club', 'description' => 'Le sport, le lieu et le pays constituent une base fiable.'],
        'verification' => ['title' => 'Faire vérifier le club', 'description' => 'La vérification renforce la confiance et la visibilité publique.'],
        'roles' => ['title' => 'Sécuriser la gouvernance', 'description' => 'Au moins deux responsables évitent la dépendance à une seule personne.'],
        'team' => ['title' => 'Créer la première équipe', 'description' => 'Les équipes relient membres, entraînements et événements.'],
        'membership' => ['title' => 'Configurer les adhésions', 'description' => 'Activez les demandes et au moins un type d’adhésion.'],
        'members' => ['title' => 'Ajouter des membres', 'description' => 'Invitez un autre membre ou importez les données existantes.'],
        'event' => ['title' => 'Planifier le premier événement', 'description' => 'Un entraînement, match ou rendez-vous active le calendrier.'],
        'communication' => ['title' => 'Publier la première information', 'description' => 'Une publication ou annonce lance la communication du club.'],
        'documents' => ['title' => 'Déposer le premier document', 'description' => 'Ajoutez un document validé dans l’espace de fichiers protégé.'],
    ],
    'actions' => [
        'profile' => 'Ouvrir le profil', 'verification' => 'Ouvrir la vérification', 'roles' => 'Gérer les rôles',
        'teams' => 'Ouvrir les équipes', 'memberships' => 'Configurer les adhésions', 'members' => 'Ajouter des membres',
        'events' => 'Planifier un événement', 'feed' => 'Créer une information', 'files' => 'Déposer un document',
    ],
];
