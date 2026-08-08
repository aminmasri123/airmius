<?php

return [
    'rules' => [
        'inactive' => ['month' => '12 mois', 'title' => 'Marquer comme inactif', 'description' => 'L’utilisateur est conservé, mais clairement identifié comme inactif dans l’administration.'],
        'reactivation' => ['month' => '18 mois', 'title' => 'Envoyer un e-mail de réactivation', 'description' => 'Envoyer un rappel automatique ou manuel et journaliser l’envoi dans le centre de messagerie.'],
        'hidden' => ['month' => '24 mois', 'title' => 'Masquer le profil', 'description' => 'Désactiver le profil public, la visibilité dans la recherche et les communications non essentielles.'],
        'anonymized' => ['month' => '36 mois', 'title' => 'Anonymiser', 'description' => 'Supprimer ou anonymiser les données personnelles qui ne sont plus nécessaires.'],
        'archived' => ['month' => 'Données obligatoires', 'title' => 'Archiver séparément', 'description' => 'Les factures, paiements et contrats restent disponibles pendant les délais légaux de conservation.'],
    ],
];
