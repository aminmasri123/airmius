<?php

$source = require __DIR__.'/data_erasure.php';
$templates = $source['email_templates'] ?? [];
unset($templates['together']);

return array_replace($templates, [
    'account_welcome' => [
        'subject' => 'Bienvenue sur Airmius',
        'greeting' => 'Bonjour {{ name }},',
        'body' => "Bienvenue sur Airmius. Votre compte a été créé avec succès.\nVous pouvez maintenant vous connecter, compléter votre profil et utiliser Airmius pour vos entraînements, clubs, équipes, événements et activités sportives quotidiennes.\nSi vous n’avez pas créé ce compte, contactez l’assistance Airmius.",
        'action_label' => 'Ouvrir Airmius',
    ],
    'account_created_with_credentials' => [
        'subject' => 'Votre compte Airmius a été créé',
        'greeting' => 'Bonjour {{ name }},',
        'body' => "Un compte Airmius a été créé pour vous.\nE-mail : {{ email }}\nMot de passe temporaire : {{ temporary_password }}\nConnectez-vous et modifiez votre mot de passe dès la première connexion.\nSi vous n’attendiez pas la création de ce compte, contactez l’assistance Airmius.",
        'action_label' => 'Se connecter à Airmius',
    ],
    'account_deletion_code' => [
        'subject' => 'Code de confirmation de suppression du compte',
        'greeting' => 'Bonjour,',
        'body' => "Vous avez demandé la suppression de votre compte Airmius.\nVotre code de confirmation est : {{ code }}\nCe code est valable 15 minutes.\nSi vous ne souhaitez pas supprimer votre compte, ignorez cet e-mail.",
        'action_label' => '',
    ],
    'account_deletion_completed' => [
        'subject' => 'Votre compte Airmius a été supprimé',
        'greeting' => 'Bonjour {{ name }},',
        'body' => "Votre compte Airmius a été supprimé avec succès.\nCet e-mail confirme que la suppression du compte est terminée.\nSi vous n’avez pas demandé cette suppression, contactez l’assistance Airmius.",
        'action_label' => '',
    ],
    'guardian_access_code' => [
        'subject' => 'Votre code d’accès à l’espace responsable Airmius',
        'greeting' => 'Bonjour,',
        'body' => "Vous avez demandé un code d’accès à l’espace responsable légal d’Airmius.\nVotre code est : {{ code }}\nCe code est valable 15 minutes.\nSi vous n’avez pas demandé ce code, ignorez cet e-mail.",
        'action_label' => '',
    ],
    'guardian_consent_requested' => [
        'subject' => 'Consentement à l’inscription sur Airmius',
        'greeting' => 'Bonjour,',
        'body' => "{{ minor_name }} s’est inscrit sur Airmius et a moins de 16 ans.\nVeuillez examiner la demande. Vous pouvez accepter ou refuser l’inscription.\nSi vous n’attendiez pas cette demande, ignorez cet e-mail.",
        'action_label' => 'Accepter ou refuser',
    ],
    'password_reset' => [
        'subject' => 'Réinitialiser le mot de passe',
        'greeting' => 'Bonjour !',
        'body' => "Vous recevez cet e-mail parce que nous avons reçu une demande de réinitialisation du mot de passe de votre compte.\nCe lien expire dans {{ expires_minutes }} minutes.",
        'action_label' => 'Réinitialiser le mot de passe',
    ],
    'contact_form_admin' => [
        'subject' => 'Nouvelle demande du formulaire de contact de {{ name }}',
        'greeting' => 'Nouvelle demande de contact',
        'body' => "Nom : {{ name }}\nE-mail : {{ email }}\n\n{{ message }}",
        'action_label' => '',
    ],
    'login_successful' => [
        'subject' => 'Nouvelle connexion à Airmius',
        'greeting' => 'Bonjour,',
        'body' => "Une connexion à votre compte Airmius vient de réussir.\nDate et heure : {{ logged_in_at }}\nAdresse IP : {{ ip_address }}\nAppareil/navigateur : {{ user_agent }}\nS’il s’agissait de vous, aucune action n’est requise.\nSinon, modifiez immédiatement votre mot de passe et contactez l’assistance Airmius.",
        'action_label' => '',
    ],
    'login_two_factor_code' => [
        'subject' => 'Votre code de sécurité Airmius',
        'greeting' => 'Bonjour {{ name }},',
        'body' => "Votre code de sécurité de connexion est : {{ code }}\nCe code est valable {{ expires_minutes }} minutes et ne peut être utilisé qu’une fois.\nSi vous n’avez pas initié cette connexion, modifiez votre mot de passe.",
        'action_label' => '',
    ],
    'login_lockout' => [
        'subject' => 'Plusieurs tentatives de connexion échouées sur Airmius',
        'greeting' => 'Bonjour,',
        'body' => "Plusieurs tentatives de connexion échouées ont été détectées pour votre compte Airmius.\nLa connexion a été temporairement bloquée pour protéger votre compte.\nDate et heure : {{ locked_at }}\nAdresse IP : {{ ip_address }}\nAppareil/navigateur : {{ user_agent }}\nS’il s’agissait de vous, attendez un moment puis réessayez.\nSinon, modifiez votre mot de passe et vérifiez la sécurité de votre compte.",
        'action_label' => '',
    ],
    'account_suspended' => [
        'subject' => 'Votre compte Airmius a été temporairement suspendu',
        'greeting' => 'Bonjour {{ name }},',
        'body' => "Votre compte Airmius a été temporairement suspendu.\nMotif : {{ reason }}\nSuspendu jusqu’au : {{ suspended_until }}\nSi vous pensez que cette suspension est incorrecte, contactez l’assistance et demandez une vérification.",
        'action_label' => 'Contacter l’assistance',
    ],
    'inactive_account_first' => [
        'subject' => 'Votre compte Airmius est inactif depuis un certain temps',
        'greeting' => 'Bonjour {{ name }},',
        'body' => "Votre compte Airmius n’a pas été utilisé depuis un certain temps.\nPour des raisons de confidentialité, nous contrôlons régulièrement les comptes inactifs.\nPour continuer à utiliser Airmius, reconnectez-vous afin que votre compte reste actif.",
        'action_label' => 'Se connecter à Airmius',
    ],
    'inactive_account_second' => [
        'subject' => 'Rappel : votre compte Airmius est toujours inactif',
        'greeting' => 'Bonjour {{ name }},',
        'body' => "Votre compte Airmius est toujours inactif.\nUne nouvelle connexion annulera le contrôle de confidentialité programmé.\nSans réaction, votre compte pourra ensuite être désactivé et anonymisé.",
        'action_label' => 'Garder le compte actif',
    ],
    'inactive_account_scheduled' => [
        'subject' => 'Compte Airmius programmé pour anonymisation',
        'greeting' => 'Bonjour {{ name }},',
        'body' => "Votre compte Airmius est inactif depuis un certain temps.\nNous avons donc programmé son anonymisation pour des raisons de confidentialité.\nDate prévue : {{ scheduled_date }}\nSi vous vous connectez avant cette date, votre compte restera actif.",
        'action_label' => 'Garder le compte actif',
    ],
    'subscription_invoice_reminder' => [
        'subject' => 'Rappel : la facture Airmius {{ invoice_number }} est impayée',
        'greeting' => 'Bonjour {{ name }},',
        'body' => "Aucun paiement n’a encore été enregistré pour votre facture Airmius.\nFacture : {{ invoice_number }}\nOffre : {{ plan_name }}\nMontant : {{ amount }}\nÉchue depuis le : {{ due_date }}\nRéférence de paiement : {{ payment_reference }}\nSi vous avez déjà payé, ignorez ce rappel. Le paiement sera marqué après le rapprochement bancaire.",
        'action_label' => 'Télécharger la facture',
    ],
    'commerce_order_completed' => [
        'subject' => 'Commande Airmius confirmée',
        'greeting' => 'Bonjour {{ name }},',
        'body' => "Votre commande a été confirmée avec succès.\nCommande : {{ order_title }}\nMontant : {{ amount }}\nStatut : payée\nMerci d’utiliser Airmius.",
        'action_label' => 'Voir la marketplace',
    ],
    'external_club_membership_invitation' => [
        'subject' => 'Invitation à rejoindre {{ club_name }} sur Airmius',
        'greeting' => 'Bonjour {{ name }},',
        'body' => "{{ inviter_name }} vous a invité au nom de {{ club_name }} et souhaite vous connecter à Airmius.\nAvec un compte Airmius, vous pouvez suivre les informations du club, les factures, l’historique des paiements, les équipes et les messages.\nSi vous possédez déjà un compte avec cette adresse e-mail, connectez-vous pour terminer l’association. Sinon, inscrivez-vous avec cette adresse.",
        'action_label' => 'Voir l’invitation',
    ],
    'external_team_invitation' => [
        'subject' => 'Invitation à rejoindre {{ team_name }}',
        'greeting' => 'Bonjour,',
        'body' => "{{ inviter_name }} vous a invité à rejoindre {{ team_name }} sur Airmius.\nAcceptez l’invitation pour rejoindre l’équipe.",
        'action_label' => 'Accepter l’invitation',
    ],
    'external_friend_invitation' => [
        'subject' => '{{ sender_name }} souhaite se connecter avec vous sur Airmius',
        'greeting' => 'Bonjour,',
        'body' => "{{ sender_name }} vous a invité comme ami sur Airmius.\nSi vous possédez déjà un compte avec cette adresse e-mail, connectez-vous et acceptez l’invitation. Sinon, inscrivez-vous avec cette adresse.",
        'action_label' => 'Accepter l’invitation',
    ],
]);
