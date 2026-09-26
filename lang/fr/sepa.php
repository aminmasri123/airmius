<?php

return [
    'recharge_void_bank' => 'Cette facture comporte des paiements ou des opérations bancaires. Vérifiez leur état et le processus distinct de remboursement.',
    'recharge_title' => 'Refacturation des frais de rejet',
    'recharge_account' => 'Le compte distinct de refacturation manque ou correspond au compte bancaire.',
    'recharge_invoice_controlled' => 'Cette facture appartient à une refacturation approuvée et ne peut pas être modifiée ou supprimée ici.',
    'recharge_changed' => 'Les frais ou la proposition ont changé. Vérifiez leur état actuel.',
    'recharge_active' => 'Une proposition active de refacturation existe déjà pour ces frais.',
    'fee_correction_state' => 'Les frais ont changé. Vérifiez à nouveau leur état actuel.',
    'fee_correction_title' => 'Correction des frais de rejet',
    'fee_export_account_required' => 'Configurez un compte de charges distinct dans les paramètres DATEV avant d’exporter les frais de rejet enregistrés.',
    'fee_title' => 'Frais bancaires de rejet',
    'fee_recorded' => 'Des frais différents ont déjà été enregistrés pour ce rejet.',
    'fee_mismatch' => 'La dépense sélectionnée ne correspond pas au club, au montant, à la date ou à la référence bancaire.',
    'fee_existing' => 'Une écriture existe déjà pour cette référence bancaire. Associez explicitement la dépense existante.',
    'fee_controlled' => 'Cette dépense est liée à un rejet SEPA et ne peut pas être modifiée ici.',

    'import_mapping' => 'Associez chaque champ obligatoire de manière unique et excluez explicitement les autres colonnes.',
    'import_file' => 'Utilisez un CSV UTF-8 avec les colonnes prévues et au maximum 200 lignes (2 Mo).',
    'import_rows' => 'L’import contient des lignes ambiguës ou invalides. Corrigez toutes les erreurs avant l’import.',
    'import_review' => 'Le fichier, le résultat bancaire ou l’aperçu a changé. Vérifiez et confirmez un nouvel aperçu.',
    'import_unlinked' => 'Les positions sans encaissement lié exigent une confirmation distincte. Aucun paiement existant ne sera annulé.',
    'payment_controlled' => 'Ce paiement appartient à un résultat SEPA enregistré et ne peut pas être modifié ou supprimé ici.',
    'result_recorded' => 'Un autre résultat bancaire est déjà enregistré pour cette ligne.',
    'bank_reference_used' => 'Cette référence bancaire a déjà été utilisée dans ce club.',
    'payment_mismatch' => 'Le paiement sélectionné ne correspond pas à la facture, au montant ou à la date comptable.',
    'bank_date' => 'La date comptable doit être comprise entre le prélèvement et aujourd’hui ; un retour ne peut pas précéder l’encaissement.',
    'return_required' => 'Enregistrez d’abord un retour documenté.',
    'export_required' => 'Les résultats bancaires ne peuvent être enregistrés que pour les lots exportés.',
    'mail_transport' => 'Un transport de courrier réel sans envoi de secours automatique est nécessaire.',
    'notice_recipient' => 'Chaque ligne nécessite une adresse e-mail valide. Vérifiez les destinataires avant l’envoi.',
    'notice_recipient_changed' => 'Une adresse de destinataire a changé. Annulez le lot et préparez-en un nouveau.',
    'notice_prepare_first' => 'Préparez et vérifiez d’abord les prénotifications.',
    'notice_sending' => 'Une prénotification est en cours d’envoi. Attendez la fin de l’envoi.',
    'notice_subject' => 'Prénotification SEPA : :club – facture :invoice',
    'notice_body' => 'Bonjour :name,

:club prélèvera :amount pour la facture :invoice par prélèvement SEPA le :date.

Identifiant créancier : :creditor
Référence du mandat : :mandate
Compte se terminant par : :iban
Lot de prélèvement : :reference

Pour toute question, contactez l’administration du club avant le prélèvement.

:club',
    'retained_history' => 'Ce club possède des lots de prélèvement enregistrés. Son historique financier doit être conservé ; la suppression définitive est donc indisponible.',
    'credentials' => 'Complétez le compte du club et son identifiant créancier.',
    'invoices' => 'Sélectionnez uniquement les factures ouvertes de ce club.',
    'reserved' => 'Une facture appartient déjà à un lot actif.',
    'state' => 'Cette action ne correspond pas à l’état actuel.',
    'second_person' => 'Une autre personne autorisée doit approuver ce lot.',
    'notice_date' => 'La date d’envoi doit être comprise entre l’approbation et aujourd’hui.',
    'notice_required' => 'Documentez la notification préalable de toutes les lignes avant l’export.',
    'expired' => 'La date de prélèvement est passée.',
    'cancel_exported' => 'Un lot exporté ne peut pas être annulé simplement. Vérifiez son état bancaire.',
    'changed' => 'Une facture, un mandat ou le compte a changé. Annulez et préparez un nouveau lot.',
    'mandate' => 'Chaque facture nécessite un mandat actif et complet.',
    'lead_time' => 'Le délai de notification convenu n’est pas respecté.',
    'unavailable' => 'Les lots de prélèvement sont actuellement indisponibles.',
    'use_batch' => 'Ouvrez le lot enregistré pour l’exporter.',
    'recharge_refund_title' => 'Remboursement de frais refacturés',
];
