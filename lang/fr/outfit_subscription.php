<?php

return [
    'responses' => [
        'profile_saved' => 'Profil de style enregistré.',
        'paypal_continue' => 'Vous pouvez poursuivre le paiement PayPal.',
        'requested' => 'Abonnement tenue demandé.',
        'requested_web' => 'Abonnement tenue demandé. Il ne sera activé qu’après le paiement.',
        'issue_sent' => 'Votre signalement a été envoyé.',
        'issue_sent_web' => 'Votre signalement a été envoyé. Notre équipe va examiner la livraison.',
    ],
    'notifications' => [
        'pending_payment_title' => 'Abonnement tenue en attente de paiement',
        'pending_payment_body' => 'Votre abonnement tenue :plan a été réservé. Il ne sera activé qu’après confirmation du paiement.',
        'paid_title' => 'Abonnement tenue activé',
        'paid_body' => 'Votre paiement pour :plan a été confirmé. Votre abonnement tenue est maintenant actif.',
        'requested_title' => 'Nouvelle demande d’abonnement tenue',
        'requested_body' => ':user a demandé :plan. Le paiement est encore en attente.',
        'issue_requested_title' => 'Une livraison tenue nécessite une assistance',
        'issue_requested_body' => ':user a signalé un problème concernant une livraison tenue.',
        'plan_fallback' => 'Abonnement tenue',
        'unpaid_title' => 'Paiement de l’abonnement tenue en attente',
        'unpaid_body' => 'Un paiement a été marqué comme dû pour votre abonnement tenue :plan.:reason',
        'address_updated_title' => 'Adresse de livraison mise à jour',
        'address_updated_body' => 'L’adresse de livraison de votre abonnement tenue :plan a été mise à jour.',
        'cancelled_title' => 'Demande d’abonnement tenue annulée',
        'cancelled_body' => 'Votre demande d’abonnement tenue :plan a été annulée.:reason',
        'issue_status_updated_title' => 'Dossier d’assistance mis à jour',
        'issue_status' => [
            'reviewing' => 'Votre signalement pour :plan est en cours d’examen.',
            'approved' => 'Votre signalement pour :plan a été approuvé.',
            'return_waiting' => 'Nous attendons votre retour pour :plan.',
            'replacement_preparing' => 'Votre remplacement pour :plan est en préparation.',
            'resolved' => 'Votre dossier d’assistance pour :plan a été résolu.',
            'rejected' => 'Votre dossier d’assistance pour :plan a été clôturé.',
            'updated' => 'Votre dossier d’assistance pour :plan a été mis à jour.',
        ],
    ],
];
