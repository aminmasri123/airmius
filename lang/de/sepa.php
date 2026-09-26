<?php

return [
    'recharge_void_bank' => 'Die Rechnung besitzt Zahlungs- oder Bankvorgänge. Bitte zuerst den Bankstatus und den gesonderten Erstattungsablauf prüfen.',
    'recharge_title' => 'Gebührenweiterbelastung',
    'recharge_account' => 'Das gesonderte Buchhaltungskonto für die Weiterbelastung fehlt oder entspricht dem Bankkonto.',
    'recharge_invoice_controlled' => 'Diese Rechnung gehört zu einer freigegebenen Gebührenweiterbelastung und kann hier nicht geändert oder gelöscht werden.',
    'recharge_changed' => 'Die Gebühr oder der Vorschlag hat sich geändert. Bitte den aktuellen Stand prüfen.',
    'recharge_active' => 'Für diese Gebühr gibt es bereits einen aktiven Vorschlag zur Weiterbelastung.',
    'fee_correction_state' => 'Die Gebührenbuchung wurde geändert. Bitte den aktuellen Stand erneut prüfen.',
    'fee_correction_title' => 'Korrektur einer Rücklastschriftgebühr',
    'fee_export_account_required' => 'Für gebuchte Rücklastschriftgebühren muss zuerst ein eigenes Aufwandskonto in den DATEV-Einstellungen hinterlegt werden.',
    'fee_title' => 'Bankgebühr für Rücklastschrift',
    'fee_recorded' => 'Für diese Rückgabe wurde bereits eine abweichende Gebühr gebucht.',
    'fee_mismatch' => 'Die ausgewählte Ausgabe passt nicht zu Verein, Betrag, Datum oder Bankreferenz.',
    'fee_existing' => 'Zu dieser Bankreferenz gibt es bereits eine Buchung. Bitte die vorhandene Ausgabe ausdrücklich verknüpfen.',
    'fee_controlled' => 'Diese Ausgabe ist mit einer SEPA-Rückgabe verbunden und kann hier nicht geändert werden.',

    'import_mapping' => 'Bitte alle Pflichtfelder eindeutig zuordnen und jede übrige Spalte ausdrücklich ausschließen.',
    'import_file' => 'Bitte eine UTF-8-CSV mit den vorgesehenen Spalten und höchstens 200 Zeilen (2 MB) verwenden.',
    'import_rows' => 'Der Import enthält unklare oder ungültige Zeilen. Bitte alle Fehler vor dem Import klären.',
    'import_review' => 'Datei, Bankergebnis oder Vorschau haben sich geändert. Bitte eine neue Vorschau prüfen und bestätigen.',
    'import_unlinked' => 'Für Positionen ohne verknüpften Zahlungseingang ist eine gesonderte Bestätigung erforderlich. Es wird keine bestehende Zahlung zurückgenommen.',
    'payment_controlled' => 'Diese Zahlung gehört zu einem gespeicherten SEPA-Ergebnis und kann hier nicht geändert oder gelöscht werden.',
    'result_recorded' => 'Für diese Position ist bereits ein anderes Bankergebnis gespeichert.',
    'bank_reference_used' => 'Diese Bank-Buchungsreferenz wurde in diesem Verein bereits verwendet.',
    'payment_mismatch' => 'Die ausgewählte Zahlung passt nicht zu Rechnung, Betrag oder Buchungsdatum.',
    'bank_date' => 'Das Buchungsdatum muss zwischen Einzugsdatum und heute liegen; eine Rückgabe darf nicht vor dem Zahlungseingang liegen.',
    'return_required' => 'Zuerst muss eine belegte Rückgabe gespeichert werden.',
    'export_required' => 'Bankergebnisse können nur für exportierte Lastschriftläufe erfasst werden.',
    'mail_transport' => 'Ein echter Mailtransport ohne automatische Ausweichzustellung ist für diesen Versand erforderlich.',
    'notice_recipient' => 'Für jede Position ist eine gültige E-Mail-Adresse erforderlich. Bitte die Empfänger vor dem Versand prüfen.',
    'notice_recipient_changed' => 'Eine Empfängeradresse hat sich geändert. Bitte den Lauf stornieren und neu vorbereiten.',
    'notice_prepare_first' => 'Bitte zuerst die Vorabinformationen vorbereiten und prüfen.',
    'notice_sending' => 'Eine Vorabinformation wird gerade versendet. Bitte den Versandabschluss abwarten.',
    'notice_subject' => 'SEPA-Vorabinformation: :club – Rechnung :invoice',
    'notice_body' => 'Guten Tag :name,

:club wird für Rechnung :invoice den Betrag :amount am :date per SEPA-Lastschrift einziehen.

Gläubiger-ID: :creditor
Mandatsreferenz: :mandate
Konto endet auf: :iban
Lastschriftlauf: :reference

Bei Fragen wenden Sie sich bitte vor dem Einzug an die Vereinsverwaltung.

:club',
    'retained_history' => 'Der Verein besitzt gespeicherte Lastschriftläufe. Die Finanzhistorie muss erhalten bleiben; eine endgültige Löschung ist deshalb nicht möglich.',
    'credentials' => 'Vereinskonto und Gläubiger-ID müssen vollständig sein.',
    'invoices' => 'Bitte nur offene Rechnungen dieses Vereins auswählen.',
    'reserved' => 'Eine Rechnung gehört bereits zu einem aktiven Lastschriftlauf.',
    'state' => 'Diese Aktion passt nicht zum aktuellen Status.',
    'second_person' => 'Die Freigabe muss eine andere berechtigte Person übernehmen.',
    'notice_date' => 'Das Versanddatum muss zwischen Freigabe und heute liegen.',
    'notice_required' => 'Vor dem Export muss die Vorabinformation für alle Positionen dokumentiert sein.',
    'expired' => 'Das Einzugsdatum liegt in der Vergangenheit.',
    'cancel_exported' => 'Ein exportierter Lauf kann nicht einfach storniert werden. Bitte den Bankstatus prüfen.',
    'changed' => 'Rechnung, Mandat oder Vereinskonto wurden geändert. Den Lauf stornieren und neu vorbereiten.',
    'mandate' => 'Für jede Rechnung ist ein aktives, vollständig hinterlegtes Mandat erforderlich.',
    'lead_time' => 'Die vereinbarte Vorabfrist wird nicht eingehalten.',
    'unavailable' => 'Lastschriftläufe sind derzeit nicht verfügbar.',
    'use_batch' => 'Bitte den gespeicherten Lastschriftlauf öffnen und dort exportieren.',
    'recharge_refund_title' => 'Erstattung Gebührenweiterbelastung',
];
