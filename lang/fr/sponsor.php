<?php

return [
    'flash' => [
        'profile_saved' => 'Profil sponsor enregistré. Les données publiques sont en cours de vérification.',
        'created' => 'Sponsor créé.',
        'updated' => 'Sponsor mis à jour.',
        'deleted' => 'Sponsor supprimé.',
        'deliverable_created' => 'Prestation convenue créée.',
        'deliverable_updated' => 'Prestation convenue mise à jour.',
        'deliverable_deleted' => 'Prestation convenue supprimée.',
        'sponsor_notified' => 'Sponsor informé.',
        'notification_failed' => 'La notification n’a pas pu être créée.',
    ],
    'validation' => [
        'verification_incomplete' => 'Le profil sponsor ne peut pas encore être vérifié (:count éléments obligatoires manquants).',
        'notification_recipient_missing' => 'Aucun compte destinataire n’est associé à ce sponsor.',
    ],
    'notifications' => [
        'review_requested_title' => 'Profil sponsor à vérifier',
        'review_requested_body' => ':sponsor a modifié les données publiques du profil.',
        'review_approved_title' => 'Profil sponsor approuvé',
        'review_approved_body' => 'Le profil public de :sponsor a été approuvé.',
        'review_rejected_title' => 'Profil sponsor non approuvé',
        'review_rejected_body' => 'Le profil de :sponsor doit être corrigé. Ouvrez le cockpit pour lire la note.',
        'deliverable_update_title' => 'Mise à jour d’une prestation de sponsoring',
        'deliverable_update_body' => 'Veuillez vérifier la prestation « :title » dans le cockpit sponsor.',
    ],
    'partnership' => [
        'requested' => 'Demande de partenariat envoyée.',
        'withdrawn' => 'Demande de partenariat retirée.',
        'duplicate' => 'Un partenariat ouvert ou actif existe déjà pour ce club.',
        'withdraw_unavailable' => 'Seules les demandes ouvertes peuvent être retirées.',
        'already_decided' => 'Cette demande a déjà été traitée.',
        'decided' => 'Demande de partenariat traitée.',
        'notification_title' => 'Nouvelle demande de partenariat',
        'notification_body' => ':sponsor souhaite devenir partenaire de :club.',
        'approved_title' => 'Demande de partenariat acceptée',
        'approved_body' => ':club a accepté la demande de partenariat.',
        'rejected_title' => 'Demande de partenariat refusée',
        'rejected_body' => ':club a refusé la demande de partenariat.',
    ],
];
