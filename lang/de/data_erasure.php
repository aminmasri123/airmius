<?php

return [
    'flash' => [
        'code_sent' => 'Wir haben dir einen Bestätigungscode per E-Mail gesendet. Er ist 15 Minuten gültig.',
        'completed' => 'Die ausgewählten Daten wurden gelöscht oder – soweit erforderlich – anonymisiert. Dein Konto bleibt bestehen.',
    ],
    'validation' => [
        'identity_required' => 'Bitte bestätige deine Identität.',
        'categories_required' => 'Wähle mindestens einen Datenbereich aus.',
        'code_required' => 'Bitte gib den Code aus deiner E-Mail ein.',
        'code_invalid' => 'Der Code ist ungültig, abgelaufen oder passt nicht zu der ausgewählten Datenlöschung. Bitte fordere einen neuen Code an.',
        'email_mismatch' => 'Die eingegebene E-Mail-Adresse stimmt nicht mit deinem Konto überein.',
        'password_incorrect' => 'Das eingegebene Passwort ist nicht korrekt.',
        'category_invalid' => 'Mindestens eine gültige Datenkategorie muss ausgewählt werden.',
    ],
    'categories' => [
        'profile' => [
            'label' => 'Profil- und Kontaktdaten',
            'description' => 'Name, Foto, Telefonnummer, Adresse, Bio und weitere Profilangaben werden entfernt. E-Mail-Adresse und Zugangsdaten bleiben für dein Konto erhalten.',
        ],
        'content' => [
            'label' => 'Beiträge, Kommentare und Storys',
            'description' => 'Eigene Beiträge, Kommentare, Storys und die dazugehörigen persönlichen Medien werden dauerhaft entfernt.',
        ],
        'messages' => [
            'label' => 'Eigene Chat-Nachrichten',
            'description' => 'Der Inhalt eigener Nachrichten und Anhänge wird entfernt. In Unterhaltungen bleibt nur ein neutraler Löschhinweis bestehen.',
        ],
        'files' => [
            'label' => 'Dateien und Ordner',
            'description' => 'Eigene hochgeladene Dateien, Vorschaubilder und persönliche Ordner werden dauerhaft entfernt.',
        ],
        'sport_and_health' => [
            'label' => 'Sport-, Standort- und Gesundheitsdaten',
            'description' => 'Importierte Aktivitäten, Routen, Tracks, Sportprofile, Trainingsprotokolle sowie Ernährungsdaten werden entfernt.',
        ],
        'social_and_integrations' => [
            'label' => 'Soziale Verbindungen und Integrationen',
            'description' => 'Freundschaften, Follows, Benachrichtigungen, Gerätekennungen und externe Sport-Verknüpfungen werden entfernt. Die technische Zuordnung für Google- oder Microsoft-Anmeldung bleibt erhalten.',
        ],
        'commerce' => [
            'label' => 'Shop- und Bestelldaten',
            'description' => 'Warenkörbe, Lieferadressen, Merkliste und Bewertungen werden entfernt. Nicht aufbewahrungspflichtige, abgeschlossene Bestellungen werden anonymisiert.',
        ],
    ],
    'summary' => [
        'pseudonym' => 'Airmius Nutzer #:id',
        'retained_account' => 'Dein Konto, deine E-Mail-Adresse und deine Zugangsdaten bleiben erhalten.',
        'retained_relationships' => 'Vereins-, Team- und Berechtigungszuordnungen werden nicht automatisch gelöscht, damit keine Daten anderer Personen oder Organisationen verloren gehen.',
        'profile' => 'Profil- und Kontaktdaten',
        'content' => 'Beiträge, Kommentare und Storys',
        'content_media' => 'Medien aus Beiträgen und Storys',
        'messages' => 'Eigene Chat-Nachrichten',
        'message_attachments' => 'Chat-Anhänge',
        'files' => 'Dateien',
        'folders' => 'Ordner',
        'sport_and_health' => 'Sport-, Standort- und Gesundheitsdaten',
        'social_and_integrations' => 'Soziale Verbindungen, Benachrichtigungen und Integrationen',
        'commerce' => 'Warenkörbe, Lieferadressen, Merklisten und Bewertungen',
        'retained_orders' => ':count Bestellvorgang/-vorgänge bleiben vorerst erhalten, weil sie einen Zahlungs-, Rechnungs-, Liefer- oder offenen Klärungsbezug haben können.',
        'financial_records' => 'Rechnungen, Zahlungen, Abonnements und andere gesetzlich oder vertraglich erforderliche Nachweise werden nicht durch diese Funktion gelöscht.',
        'message_deleted' => '[Nachricht gelöscht]',
        'personal_data_removed' => '[Personenbezogene Angaben entfernt]',
    ],
    'email_templates' => [
        'data_erasure_code' => [
            'subject' => 'Bestätigungscode zur Datenlöschung',
            'greeting' => 'Hallo,',
            'body' => "du hast angefordert, ausgewählte personenbezogene Daten aus deinem Airmius-Konto zu löschen, ohne dein Konto zu löschen.\nDein Bestätigungscode lautet: {{ code }}\nDer Code ist 15 Minuten gültig.\nWenn du diese Anfrage nicht selbst ausgelöst hast, kannst du diese E-Mail ignorieren und solltest dein Konto absichern.",
            'action_label' => '',
        ],
        'data_erasure_completed' => [
            'subject' => 'Deine angeforderten Daten wurden bearbeitet',
            'greeting' => 'Hallo {{ name }},',
            'body' => "deine ausgewählten Daten wurden gelöscht oder – soweit erforderlich – anonymisiert. Dein Airmius-Konto bleibt bestehen.\nRechnungs-, Zahlungs-, Vertrags- und andere gesetzlich aufzubewahrende Nachweise können weiterhin gespeichert bleiben.\nFalls du diese Anfrage nicht selbst ausgelöst hast, kontaktiere bitte den Airmius-Support.",
            'action_label' => '',
        ],
        'subscription_renewed' => [
            'subject' => 'Airmius Abo wurde verlängert',
            'greeting' => 'Hallo {{ name }},',
            'body' => "dein Airmius Abo wurde verlängert.\nPlan: {{ plan_name }}\nNeue Laufzeit bis: {{ end_date }}\nDanke, dass du Airmius nutzt.",
            'action_label' => 'Pläne ansehen',
        ],
        'subscription_resumed' => [
            'subject' => 'Dein Airmius Abo läuft weiter',
            'greeting' => 'Hallo {{ name }},',
            'body' => "deine vorgemerkte Kündigung wurde zurückgenommen.\nPlan: {{ plan_name }}\nNächster Verlängerungstermin: {{ renewal_date }}\nDein Abo läuft ohne Unterbrechung weiter.",
            'action_label' => 'Pläne ansehen',
        ],
        'subscription_payment_issue' => [
            'subject' => 'Zahlung für dein Airmius Abo ist offen',
            'greeting' => 'Hallo {{ name }},',
            'body' => "für dein Airmius Abo ist eine Zahlung offen oder deine Testphase ist abgelaufen.\nPlan: {{ plan_name }}\nStatus: Zahlung offen\nBitte aktualisiere die Zahlung, damit alle gebuchten Funktionen aktiv bleiben.",
            'action_label' => 'Plan verlängern',
        ],
        'subscription_ending_soon' => [
            'subject' => 'Dein Airmius Abo endet bald',
            'greeting' => 'Hallo {{ name }},',
            'body' => "{{ ending_message }}\nPlan: {{ plan_name }}\nEnddatum: {{ end_date }}\nWenn du Airmius weiter nutzen möchtest, kannst du rechtzeitig einen passenden Plan wählen.",
            'action_label' => 'Pläne ansehen',
        ],
        'subscription_cancelled' => [
            'subject' => 'Airmius Abo-Kündigung bestätigt',
            'greeting' => 'Hallo {{ name }},',
            'body' => "{{ cancel_message }}\nPlan: {{ plan_name }}\nEndet am: {{ end_date }}\nDu kannst später jederzeit wieder einen passenden Plan aktivieren.",
            'action_label' => 'Pläne ansehen',
        ],
        'subscription_invoice_awaiting_transfer' => [
            'subject' => 'Airmius Rechnung {{ invoice_number }} wartet auf Überweisung',
            'greeting' => 'Hallo {{ name }},',
            'body' => "deine Airmius Rechnung wurde erstellt und wartet auf Zahlung per Überweisung.\nRechnung: {{ invoice_number }}\nPlan: {{ plan_name }}\nBetrag: {{ amount }}\nFällig bis: {{ due_date }}\nVerwendungszweck: {{ payment_reference }}\nKontoinhaber: {{ bank_account_holder }}\nBank: {{ bank_name }}\nIBAN: {{ iban }}\nBIC: {{ bic }}\nSobald die Zahlung eingegangen ist, bestätigen wir sie in Airmius.",
            'action_label' => 'Rechnung herunterladen',
        ],
        'subscription_invoice_paid' => [
            'subject' => 'Zahlung für Airmius Rechnung {{ invoice_number }} bestätigt',
            'greeting' => 'Hallo {{ name }},',
            'body' => "deine Zahlung wurde bestätigt. Dein Airmius Abo ist aktiv.\nRechnung: {{ invoice_number }}\nPlan: {{ plan_name }}\nBetrag: {{ amount }}\nBezahlt am: {{ paid_date }}\nZahlungsart: {{ payment_method }}\nDanke, dass du Airmius nutzt.",
            'action_label' => 'Rechnung herunterladen',
        ],
        'together' => 'zusammen',
    ],
];
