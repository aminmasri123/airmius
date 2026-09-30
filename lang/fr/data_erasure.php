<?php

return [
    'flash' => [
        'code_sent' => 'Nous vous avons envoyé un code de confirmation par e-mail. Il est valable 15 minutes.',
        'completed' => 'Les données sélectionnées ont été supprimées ou anonymisées si nécessaire. Votre compte reste actif.',
    ],
    'validation' => [
        'code_delivery_failed' => 'Le courriel de confirmation n’a pas pu être envoyé. Veuillez réessayer plus tard ou contacter le support.',
        'identity_required' => 'Veuillez confirmer votre identité.',
        'categories_required' => 'Sélectionnez au moins une catégorie de données.',
        'code_required' => 'Saisissez le code reçu par e-mail.',
        'code_invalid' => 'Le code est invalide, expiré ou ne correspond pas aux données sélectionnées. Demandez un nouveau code.',
        'email_mismatch' => 'L’adresse e-mail saisie ne correspond pas à votre compte.',
        'password_incorrect' => 'Le mot de passe saisi est incorrect.',
        'category_invalid' => 'Au moins une catégorie de données valide doit être sélectionnée.',
    ],
    'categories' => [
        'profile' => [
            'label' => 'Profil et coordonnées',
            'description' => 'Votre nom, photo, numéro de téléphone, adresse, biographie et autres données de profil sont supprimés. Votre e-mail et vos identifiants restent associés au compte.',
        ],
        'content' => [
            'label' => 'Publications, commentaires et stories',
            'description' => 'Vos publications, commentaires, stories et médias personnels associés sont définitivement supprimés.',
        ],
        'messages' => [
            'label' => 'Vos messages de chat',
            'description' => 'Le contenu de vos messages et pièces jointes est supprimé. Seule une indication neutre reste dans les conversations.',
        ],
        'files' => [
            'label' => 'Fichiers et dossiers',
            'description' => 'Les fichiers, aperçus et dossiers personnels que vous avez importés sont définitivement supprimés.',
        ],
        'sport_and_health' => [
            'label' => 'Données sportives, de localisation et de santé',
            'description' => 'Les activités importées, itinéraires, traces, profils sportifs, journaux d’entraînement et données nutritionnelles sont supprimés.',
        ],
        'social_and_integrations' => [
            'label' => 'Relations sociales et intégrations',
            'description' => 'Les amitiés, abonnements, notifications, identifiants d’appareil et connexions sportives externes sont supprimés. Le lien technique de connexion Google ou Microsoft est conservé.',
        ],
        'commerce' => [
            'label' => 'Boutique et commandes',
            'description' => 'Les paniers, adresses de livraison, favoris et avis sont supprimés. Les commandes terminées sans obligation de conservation sont anonymisées.',
        ],
    ],
    'summary' => [
        'pseudonym' => 'Utilisateur Airmius n° :id',
        'retained_account' => 'Votre compte, votre adresse e-mail et vos identifiants restent actifs.',
        'retained_relationships' => 'Les affectations aux clubs, équipes et autorisations ne sont pas supprimées automatiquement afin de préserver les données d’autres personnes ou organisations.',
        'profile' => 'Profil et coordonnées',
        'content' => 'Publications, commentaires et stories',
        'content_media' => 'Médias des publications et stories',
        'messages' => 'Vos messages de chat',
        'message_attachments' => 'Pièces jointes du chat',
        'files' => 'Fichiers',
        'folders' => 'Dossiers',
        'sport_and_health' => 'Données sportives, de localisation et de santé',
        'social_and_integrations' => 'Relations sociales, notifications et intégrations',
        'commerce' => 'Paniers, adresses de livraison, favoris et avis',
        'retained_orders' => ':count dossiers de commande restent temporairement conservés en raison d’un paiement, d’une facture, d’une livraison ou d’un litige en cours.',
        'financial_records' => 'Les factures, paiements, abonnements et autres justificatifs requis par la loi ou un contrat ne sont pas supprimés par cette fonction.',
        'message_deleted' => '[Message supprimé]',
        'personal_data_removed' => '[Données personnelles supprimées]',
    ],
    'email_templates' => [
        'data_erasure_code' => [
            'subject' => 'Code de confirmation pour la suppression de données',
            'greeting' => 'Bonjour,',
            'body' => "Vous avez demandé la suppression de certaines données personnelles de votre compte Airmius sans supprimer le compte.\nVotre code de confirmation est : {{ code }}\nLe code est valable 15 minutes.\nSi vous n’êtes pas à l’origine de cette demande, ignorez cet e-mail et sécurisez votre compte.",
            'action_label' => '',
        ],
        'data_erasure_completed' => [
            'subject' => 'Les données demandées ont été traitées',
            'greeting' => 'Bonjour {{ name }},',
            'body' => "Les données sélectionnées ont été supprimées ou anonymisées si nécessaire. Votre compte Airmius reste actif.\nLes factures, paiements, contrats et autres justificatifs soumis à une obligation légale de conservation peuvent rester stockés.\nSi vous n’êtes pas à l’origine de cette demande, contactez l’assistance Airmius.",
            'action_label' => '',
        ],
        'subscription_renewed' => [
            'subject' => 'Ton abonnement Airmius a été renouvelé',
            'greeting' => 'Bonjour {{ name }},',
            'body' => "Ton abonnement Airmius a été renouvelé.\nFormule : {{ plan_name }}\nNouvelle échéance : {{ end_date }}\nMerci d’utiliser Airmius.",
            'action_label' => 'Voir les formules',
        ],
        'subscription_resumed' => [
            'subject' => 'Ton abonnement Airmius va continuer',
            'greeting' => 'Bonjour {{ name }},',
            'body' => "Ta résiliation programmée a été retirée.\nFormule : {{ plan_name }}\nProchain renouvellement : {{ renewal_date }}\nTon abonnement continue sans interruption.",
            'action_label' => 'Voir les formules',
        ],
        'subscription_payment_issue' => [
            'subject' => 'Le paiement de ton abonnement Airmius est en attente',
            'greeting' => 'Bonjour {{ name }},',
            'body' => "Un paiement de ton abonnement Airmius est en attente ou ta période d’essai a expiré.\nFormule : {{ plan_name }}\nStatut : paiement en attente\nMerci de mettre à jour le paiement pour conserver toutes les fonctions réservées.",
            'action_label' => 'Renouveler la formule',
        ],
        'subscription_ending_soon' => [
            'subject' => 'Ton abonnement Airmius se termine bientôt',
            'greeting' => 'Bonjour {{ name }},',
            'body' => "{{ ending_message }}\nFormule : {{ plan_name }}\nDate de fin : {{ end_date }}\nChoisis à temps une formule adaptée si tu souhaites continuer à utiliser Airmius.",
            'action_label' => 'Voir les formules',
        ],
        'subscription_cancelled' => [
            'subject' => 'Résiliation de l’abonnement Airmius confirmée',
            'greeting' => 'Bonjour {{ name }},',
            'body' => "{{ cancel_message }}\nFormule : {{ plan_name }}\nFin le : {{ end_date }}\nTu pourras réactiver une formule adaptée à tout moment.",
            'action_label' => 'Voir les formules',
        ],
        'subscription_invoice_awaiting_transfer' => [
            'subject' => 'La facture Airmius {{ invoice_number }} attend ton virement',
            'greeting' => 'Bonjour {{ name }},',
            'body' => "Ta facture Airmius a été créée et attend le paiement par virement.\nFacture : {{ invoice_number }}\nFormule : {{ plan_name }}\nMontant : {{ amount }}\nÉchéance : {{ due_date }}\nRéférence : {{ payment_reference }}\nTitulaire : {{ bank_account_holder }}\nBanque : {{ bank_name }}\nIBAN : {{ iban }}\nBIC : {{ bic }}\nNous confirmerons le paiement dans Airmius dès sa réception.",
            'action_label' => 'Télécharger la facture',
        ],
        'subscription_invoice_paid' => [
            'subject' => 'Paiement de la facture Airmius {{ invoice_number }} confirmé',
            'greeting' => 'Bonjour {{ name }},',
            'body' => "Ton paiement a été confirmé et ton abonnement Airmius est actif.\nFacture : {{ invoice_number }}\nFormule : {{ plan_name }}\nMontant : {{ amount }}\nPayée le : {{ paid_date }}\nMoyen de paiement : {{ payment_method }}\nMerci d’utiliser Airmius.",
            'action_label' => 'Télécharger la facture',
        ],
        'together' => 'à vous',
    ],
];
