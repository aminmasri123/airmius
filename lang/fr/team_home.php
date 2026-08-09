<?php

return [
    'actions' => [
        'confirm_attendance' => ['label' => 'Répondre pour la présence', 'reason' => 'Ta réponse manque encore pour le prochain événement de l’équipe.'],
        'remind_missing_responses' => ['label' => 'Recueillir les réponses', 'reason' => ':count membres de l’équipe n’ont pas encore répondu.'],
        'check_squad_availability' => ['label' => 'Vérifier la disponibilité', 'reason' => 'Peu de confirmations sont disponibles pour le prochain événement.'],
        'organize_carpool' => ['label' => 'Organiser un covoiturage', 'reason' => 'Aucune place libre n’est encore visible pour le prochain événement.'],
        'collect_open_fees' => ['label' => 'Vérifier les frais ouverts', 'reason' => ':count frais d’équipe sont encore ouverts.'],
        'review_own_fee' => ['label' => 'Vérifier tes frais', 'reason' => 'Tu as encore :count frais ouverts.'],
        'complete_parent_links' => ['label' => 'Compléter les liens parentaux', 'reason' => ':count membres mineurs n’ont pas encore de contact lié.'],
        'extend_season_calendar' => ['label' => 'Compléter le calendrier', 'reason' => 'Le calendrier à venir ne contient actuellement que :count événements.'],
        'team_routine_stable' => ['label' => 'Ouvrir la vue d’équipe', 'reason' => 'Aucune action urgente n’est actuellement ouverte pour l’équipe.'],
    ],
];
