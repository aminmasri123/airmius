# Sponsorenmodul: Lueckenmatrix T036a

Stand: 2026-09-26

Diese Matrix auditiert das bestehende Sponsorenmodul gegen Kontakte, Gespraechsverlauf, Angebote, Vertraege, Fristen, Zahlungen und Rechte. Die Hauptcheckliste bleibt unveraendert.

## Abdeckung

| Bereich | Vorhandener Vertrag | Luecke | Risiko | Regression |
| --- | --- | --- | --- | --- |
| Kontakte | `sponsors.contact_name`, `email`, `website`, Rechtsdaten und Verifikationsstatus werden ueber die Sponsor-Management-API gepflegt. Oeffentliche Sponsor-APIs blenden interne Kontakt- und Betragsdaten aus. | Keine normalisierten Kontaktrollen, keine mehreren Ansprechpartner, keine Verbindung zu Vereins-/Club-Kontaktstammdaten. | Ein Wechsel der Ansprechpartner ist nicht historisiert; operatives Follow-up kann in Freitext/externen Kanaelen verloren gehen. | `SponsorModuleGapMatrixContractTest::test_management_projection_keeps_contact_and_finance_fields_but_no_conversation_offer_contract_payment_claims` |
| Gespraechsverlauf | SponsorWorkspace verknuepft Partner, Kampagnen, Assets, Agenturbriefings und Outcomes. | Kein Sponsor-Gespraechsobjekt, keine Timeline, kein `conversation_id`, keine Nachrichtenzuordnung und keine Follow-up-Aufgaben am Sponsor. | Deal-Status kann nicht belastbar aus Kommunikation hergeleitet oder auditiert werden. | Gleicher Projektionstest stellt sicher, dass keine nicht vorhandenen Gespraechsfelder im API-Vertrag behauptet werden. |
| Angebote | Kampagnen und Briefings bilden Wachstumsflow `brief -> deal -> asset -> campaign -> outcome` ab. | Kein Angebotsmodell mit Status, Gueltigkeit, Positionen, Gegenleistungen oder Freigabe. | Angebote koennen nicht versioniert, angenommen, abgelehnt oder in Vertrag/Zahlung ueberfuehrt werden. | Projektionstest prueft, dass `offer*`-Felder nicht im Sponsor-Management-Vertrag erscheinen. |
| Vertraege | Sponsor hat Scope, Laufzeit, Betrag, Verifikationsfelder und Revenue-Trust-Readiness. | Kein Vertragstitel, Vertragsnummer, Vertragsdokument, Rechtepaket, Kuendigungs-/Verlaengerungslogik oder Vertragsversion. | Vertragspflichten und Rechte koennen nicht gegen Sponsor, Verein, Kampagne und Dokumente abgeglichen werden. | `SponsorModuleGapMatrixContractTest::test_sponsor_finance_contract_readiness_blocks_untrusted_approval_and_allows_complete_profile` |
| Fristen | `starts_at` und `ends_at` bestimmen aktiven, kommenden oder beendeten Partnerstatus. | Keine Wiedervorlage, Zahlungsfaelligkeit, Kuendigungsfrist, Angebotsablauf oder SLA-Frist pro Sponsor. | Verlaengerungen, Mahnungen und Rechteauslauf sind manuell und leicht zu uebersehen. | Projektionstest und Workspace-Status aus bestehenden `SponsorAgencyWorkspaceContractTest`-Faellen. |
| Zahlungen | Betrag `amount` existiert; Commerce/Ads-Outcomes enthalten Budget, Spend und Conversion-Wert. | Keine Sponsor-Rechnung, Zahlungsstatus, Zahlungsfaelligkeit, Teilzahlung, Beleg, Zahlungseingang oder Finance-Entry-Verknuepfung. | Finanzstatus eines Sponsorings kann nicht revisionssicher aus dem Modul heraus bewertet werden. | Projektionstest prueft, dass keine `payment_*`, `invoice_id` oder `finance_entry_id`-Claims existieren. |
| Rechte | Globale Verwaltung ueber `finance.edit`, `system.manage`, Admin-/Sponsor-Manager-Rollen; Club-Sponsorenrechte sind getrennt nach `sponsors.edit` und `sponsors.delete`. | Kein separates Sponsor-Recht fuer Anzeigen, Vertragsfreigabe, Zahlungen, Kontaktaufnahme, Export oder Rechtepaketverwaltung. | Zu grobe Rollen koennen Vertrags-/Finanzhandlungen und Stammdatenpflege vermischen. | `SponsorModuleGapMatrixContractTest::test_role_contract_keeps_global_finance_and_club_sponsor_permissions_separate` |

## Naechste umsetzbare Luecken

1. Sponsor-Deal-Objekt mit Ansprechpartnern, Angebotsstatus, Vertragsnummer, Vertragsdokument und Fristen einfuehren.
2. Sponsor-Zahlungsplan mit Faelligkeit, Zahlungseingang, Rechnung/Beleg und Finance-Entry-Verknuepfung modellieren.
3. Rechtepakete und getrennte Permissions fuer Vertragsfreigabe, Zahlungsstatus, Kontaktverlauf und Sponsor-Export ergaenzen.
4. Gespraechs-/Timeline-Anbindung fuer Sponsor-spezifische Notizen, Follow-ups und Chat-/Mail-Referenzen schaffen.
