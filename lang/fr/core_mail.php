<?php

$catalog = require __DIR__.'/../de/core_mail.php';

$catalog['common'] = [
    'greeting' => 'Bonjour :name,', 'together' => 'à toutes et à tous', 'not_set' => 'pas encore défini', 'salutation' => 'Cordialement, l’équipe Airmius',
    'fields' => [
        'subscription' => 'Abonnement : :value', 'amount' => 'Montant : :value', 'due_on' => 'À régler avant le : :value', 'due_since' => 'Échu depuis le : :value',
        'payment_reference' => 'Référence de paiement : :value', 'delivery_month' => 'Mois de livraison : :value', 'shipping' => 'Expédition : :value',
        'tracking_link' => 'Lien de suivi : :value', 'note' => 'Remarque : :value', 'invoice_number' => 'Numéro de facture : :value', 'title' => 'Titre : :value',
        'old_status' => 'Statut précédent : :value', 'new_status' => 'Nouveau statut : :value', 'status' => 'Statut : :value', 'club' => 'Club : :value',
        'country' => 'Pays : :value', 'requested_club_number' => 'Numéro de club demandé : :value', 'account_holder' => 'Titulaire du compte : :value',
        'iban' => 'IBAN : :value', 'bic' => 'BIC : :value', 'purpose' => 'Référence du virement : :value', 'message' => 'Message : :value',
    ],
    'actions' => [
        'invoice' => 'Voir la facture', 'review_club' => 'Examiner la demande', 'open_club' => 'Ouvrir le club', 'marketplace' => 'Ouvrir la marketplace',
        'outfit' => 'Voir l’abonnement tenue', 'outfits' => 'Voir les abonnements tenue', 'verify_email' => 'Confirmer l’adresse e-mail',
        'trainer_cockpit' => 'Ouvrir le cockpit entraîneur', 'view_application' => 'Voir la demande', 'review_trainers' => 'Examiner les demandes d’entraîneur',
    ],
];
$catalog['verification'] = [
    'subject' => 'Confirmez votre adresse e-mail pour Airmius', 'body' => 'Confirmez votre adresse e-mail afin d’activer complètement votre compte Airmius.',
    'security_note' => 'Ce lien de sécurité expire. Si vous n’avez pas créé ce compte, vous pouvez ignorer ce message.',
];
$catalog['invoice'] = array_replace_recursive($catalog['invoice'], [
    'new_subject' => 'Nouvelle facture de :sender', 'new_body' => 'Vous avez reçu une nouvelle facture.', 'club_body' => 'Vous avez reçu une nouvelle facture de :club.',
    'reminder_subject' => 'Rappel de paiement pour la facture :invoice', 'reminder_body' => 'La facture :invoice est arrivée à échéance. Veuillez vérifier le montant restant.',
    'settle_if_open' => 'Veuillez vérifier la facture et la régler à temps si elle est toujours ouverte.', 'settle' => 'Veuillez vérifier la facture et la régler à temps.',
    'status_subject' => 'Le statut de votre facture a été mis à jour', 'status_body' => 'Le statut de votre facture a été mis à jour.',
    'status_review' => 'Veuillez consulter vos factures si un paiement est encore dû.', 'bank_transfer' => 'Paiement par virement bancaire :', 'club_fallback' => 'votre club',
    'statuses' => ['paid' => 'Payée', 'open' => 'Ouverte', 'pending' => 'En attente', 'awaiting_transfer' => 'En attente du virement', 'overdue' => 'En retard', 'cancelled' => 'Annulée', 'failed' => 'Échouée'],
]);
$catalog['club_registration'] = [
    'review_subject' => 'Nouvelle demande de club : :club', 'review_body' => ':applicant a enregistré un club.', 'submitted_subject' => 'Votre demande de club a été envoyée',
    'submitted_body' => 'Votre club « :club » a été créé et attend maintenant son examen.', 'owner_body' => 'Vous êtes immédiatement enregistré comme propriétaire et pouvez gérer le club depuis le tableau de bord.',
    'visibility_body' => 'Le club ne sera visible publiquement et marqué comme officiel qu’après son approbation.',
];
$catalog['commerce_return'] = [
    'subject' => 'Retour mis à jour', 'body' => 'Votre retour pour :item a été mis à jour.', 'item_fallback' => 'votre commande',
    'default_note' => 'Vous pouvez consulter le statut actuel dans votre espace marketplace.',
    'statuses' => ['requested' => 'Demandé', 'approved' => 'Approuvé', 'received' => 'Reçu', 'refunded' => 'Remboursé', 'rejected' => 'Refusé'],
];
$catalog['outfit'] = array_replace_recursive($catalog['outfit'], [
    'plan_fallback' => 'Abonnement tenue', 'carrier_fallback' => 'Service de livraison', 'payment_reminder_subject' => 'Rappel : paiement de l’abonnement tenue en attente',
    'payment_reminder_body' => 'Nous n’avons pas encore reçu le paiement de votre abonnement tenue.', 'dunning_subject' => 'Rappel de paiement :level : paiement de l’abonnement tenue en attente',
    'final_dunning_subject' => 'Dernier rappel : paiement de l’abonnement tenue en attente', 'dunning_body' => 'Un paiement de votre abonnement tenue actif est en attente.',
    'dunning_continue' => 'Veuillez régler le paiement afin que votre abonnement tenue continue sans interruption.',
    'dunning_paused' => 'Votre abonnement tenue a été suspendu jusqu’à réception du paiement. Aucune nouvelle livraison ne sera préparée pendant cette période.',
    'expired_subject' => 'Demande d’abonnement tenue supprimée', 'expired_body' => 'Votre demande d’abonnement tenue a été supprimée, car aucun paiement n’a été reçu sous 9 jours.',
    'expired_restart' => 'Vous pouvez effectuer une nouvelle demande à tout moment si vous souhaitez toujours utiliser l’abonnement tenue.',
    'delivery_subjects' => ['planned' => 'Votre livraison de tenue a été planifiée', 'preparing' => 'Votre livraison de tenue est en préparation', 'shipped' => 'Votre tenue a été expédiée', 'delivered' => 'Votre tenue a été livrée', 'cancelled' => 'Votre livraison de tenue a été annulée'],
    'delivery_bodies' => ['planned' => 'Votre prochaine box de vêtements de sport a été planifiée.', 'preparing' => 'Votre box de vêtements de sport est en préparation.', 'shipped' => 'Votre box de vêtements de sport a été expédiée.', 'delivered' => 'Votre box de vêtements de sport a été marquée comme livrée.', 'cancelled' => 'Votre box de vêtements de sport a été annulée.'],
]);
$catalog['trainer'] = [
    'approved_subject' => 'Votre demande d’entraîneur a été approuvée', 'rejected_subject' => 'Votre demande d’entraîneur a été refusée',
    'approved_body' => 'Airmius a examiné et approuvé votre demande d’entraîneur. Votre accès entraîneur reste actif.',
    'rejected_body' => 'Airmius a refusé votre demande d’entraîneur.', 'disabled_body' => 'Votre accès entraîneur a de nouveau été désactivé.',
    'review_subject' => 'Nouvelle demande d’entraîneur : :applicant', 'review_body' => ':applicant souhaite utiliser l’espace entraîneur sur Airmius.',
    'review_pending' => 'L’accès entraîneur a été activé immédiatement et attend l’examen d’Airmius.',
];

return $catalog;
