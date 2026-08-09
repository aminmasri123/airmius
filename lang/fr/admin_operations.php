<?php

return [
    'workspaces' => [
        'platform' => ['label' => 'Platform Ops', 'description' => 'Validations, SLA du support et livraison technique.'],
        'trust' => ['label' => 'Trust & Safety', 'description' => 'Modération, recours et décisions.'],
        'revenue' => ['label' => 'Revenue Ops', 'description' => 'Commandes, retours, versements et dossiers tenue.'],
    ],
    'kinds' => [
        'club_verification' => 'Validation du club', 'trainer_application' => 'Candidature coach',
        'mail_delivery' => 'Envoi e-mail', 'support_ticket' => 'Ticket support',
        'moderation_flag' => 'Alerte de modération', 'content_report' => 'Signalement',
        'content_appeal' => 'Recours', 'order_issue' => 'Incident de commande',
        'return_request' => 'Retour', 'seller_application' => 'Candidature vendeur',
        'payout' => 'Versement', 'outfit_issue' => 'Dossier tenue',
    ],
    'case_title' => ':kind n° :id',
    'priorities' => ['urgent' => 'Urgent', 'high' => 'Élevée', 'normal' => 'Normale', 'low' => 'Faible'],
    'statuses' => [
        'pending' => 'Ouvert', 'pending_verification' => 'Validation en attente', 'failed' => 'Échec',
        'open' => 'Ouvert', 'in_progress' => 'En cours', 'waiting_user' => 'Réponse attendue',
        'appeal_pending' => 'Recours en attente', 'reported' => 'Signalé', 'reviewing' => 'En vérification',
        'requested' => 'Demandé', 'approved' => 'Approuvé', 'received' => 'Reçu',
        'prepared' => 'Préparé', 'seller_recovery_required' => 'Recouvrement requis',
        'return_waiting' => 'Retour attendu', 'replacement_preparing' => 'Remplacement en préparation',
    ],
    'actions' => ['open_workspace' => 'Ouvrir dans l’espace spécialisé'],
    'timeline' => [
        'case_opened' => 'Dossier ouvert', 'review_recorded' => 'Décision de modération enregistrée',
        'commerce_recorded' => 'Action commerciale enregistrée',
    ],
    'ui' => [
        'page_title' => 'Centre des opérations', 'eyebrow' => 'Plan de contrôle Airmius',
        'title' => 'Une seule boîte de réception opérationnelle',
        'intro' => 'Traitez par priorité et échéance. Chaque espace ne charge que les données strictement nécessaires, à la demande.',
        'privacy_badge' => 'Vue minimisée conforme au RGPD', 'workspace_tabs' => 'Espaces opérationnels',
        'refresh' => 'Actualiser',
        'loading' => 'Chargement de l’espace…', 'load_error' => 'Impossible de charger cet espace.',
        'retry' => 'Réessayer', 'cached' => 'En cache', 'live' => 'Chargé maintenant',
        'metrics' => ['visible' => 'Dossiers visibles', 'urgent' => 'Urgents', 'overdue' => 'En retard', 'sources' => 'Sources'],
        'filters' => [
            'title' => 'Filtrer les dossiers', 'search' => 'Rechercher une référence, un type ou un statut',
            'priority' => 'Priorité', 'source' => 'Source', 'all' => 'Tous', 'result' => ':count résultats',
        ],
        'cases' => [
            'title' => 'File de travail ouverte', 'empty' => 'Aucun dossier ouvert pour ce filtre.',
            'opened' => 'Ouvert', 'due' => 'Échéance', 'overdue' => 'En retard', 'amount' => 'Montant',
            'metadata_only' => 'Métadonnées uniquement – accès spécialisé non accordé',
            'access' => 'Accès',
            'more_available' => 'D’autres dossiers sont disponibles dans l’espace spécialisé. Cette vue est volontairement limitée.',
        ],
        'timeline' => [
            'title' => 'Chronologie d’audit', 'intro' => 'Événements minimisés sans personnes, texte libre ni données brutes.',
            'empty' => 'Aucun événement pour le moment.',
        ],
        'privacy' => [
            'title' => 'Confidentialité par défaut',
            'text' => 'Cette projection ne contient aucun nom, e-mail, message, motif, texte d’erreur ou contenu d’audit brut.',
            'lazy' => 'Les espaces sont chargés à la demande via AJAX, sans interrogation périodique.',
        ],
    ],
];
