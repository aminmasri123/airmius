# AIRMIUS Governance Release Runbook

Stand: 9. August 2026  
Vertrag: `governance-assurance.v1`  
Release: `2026-08-09`

Dieses Runbook koordiniert die drei bestehenden externen No-Go-Gates `legal_release_approval`, `dpia_approval` und `external_penetration_test`. Es erzeugt keine vierte Freigabe, ersetzt keine Rechtsberatung und erlaubt weder lokale Selbstfreigabe noch Waiver.

## Schnellprüfung

```bash
php artisan airmius:audit-governance --json
php artisan airmius:audit-governance --json --strict
php artisan airmius:audit-legal-readiness --json
php artisan airmius:release-preflight --json
```

Der normale Governance-Audit muss bei vollständiger technischer Basis Exit-Code 0 liefern. `--strict` bleibt bis zu allen drei autoritativen Freigaben rot. Lokale Vorbereitung kann die drei Einträge in `resources/release/platform_release_gates.json` niemals auf bestanden setzen.

Aktueller technischer Stand:

- 8 Repository-Prüfungen bestanden, 0 Fehler.
- 27 vorhandene Legal-/Privacy-/Security-/Store-/Gastseiten-/Mehrsprachen-Artefakte sind eingebunden.
- 10 öffentliche Rechteseiten und Betroffenenrechte-Einstiege sind registriert.
- 12 Legal-Bereiche, 10 DPIA-Verarbeitungsfamilien, 9 DPIA-Pflichtabschnitte, 16 Pentest-Scope-Gruppen und 8 Pentest-Abnahmeregeln sind versioniert.
- Lokale Governance-Evidenz sowie Legal-, DPIA- und Pentest-Freigabe sind noch offen.

## Verantwortlichkeiten und Trennung

| Aufgabe | Verantwortlich | Darf freigeben | Darf nicht ersetzen |
| --- | --- | --- | --- |
| Rechtstexte, Anbieteridentität, AGB, Consent und Store-Angaben prüfen | Legal / Datenschutz | `legal_release_approval` | DPO-DPIA oder unabhängigen Pentest |
| Risiko, Erforderlichkeit, Verhältnismäßigkeit und Schutzmaßnahmen bewerten | Datenschutzbeauftragte Person | `dpia_approval` | Legal- oder Security-Freigabe |
| Autorisierten Web-/API-/Mobile-Test durchführen | Unabhängiger Security Assessor | technischen Prüfbericht und Retest bestätigen | Release-Manifest selbst pflegen |
| Kritische/hohe Befunde beheben und Nachtest bereitstellen | jeweiliges Engineering-Team | keine externe Freigabe | unabhängige Bewertung |
| Kurzreferenzen und externe Freigabemetadaten eintragen | Release Management | Manifest verwalten | fachliche Selbstfreigabe |
| Gesamt-Go/No-Go ausführen | Release Management | Release nur bei grünem Strict-Preflight | offene oder gewavete Governance-Gates |

Für diese drei Gates ist `waived` immer ein Fehler. Namen der tatsächlich freigebenden Personen gehören ausschließlich in das autoritative Manifest bzw. das geschützte Freigabesystem, nicht in lokale Auditdateien oder Audit-Ausgaben.

## Legal-Abdeckung

Legal und Datenschutz prüfen für die exakte Release-Version gemeinsam:

1. Anbieteridentität, Anschrift, Vertretung, Register, Steuer- und Aufsichtsangaben.
2. Öffentliche Gastseiten für Impressum, Datenschutz, Konto-/Datenlöschung, AGB, Community, Minderjährige, Cookies, Widerruf und Meldung/Support in DE/EN/FR/AR: Deutsch bleibt Quelle; semantische Gleichheit, Rechtsbegriffe, Zahlen, Verweise, Markennamen und arabisches RTL benötigen juristische und muttersprachliche Freigabe.
3. Vertrags- und Rollenmodell für Sportler, Eltern, Trainer, Vereine, Teams, Anbieter, Sponsoren und Agenturen.
4. Verarbeitungen, Zwecke, Rechtsgrundlagen und besondere Kategorien.
5. Separate Cookie-, Werbe-, Conversion- und Produktanalyse-Einwilligungen einschließlich Version/Widerruf.
6. Minderjährige, Elternzustimmung, eingeschränkte Konten und Widerruf.
7. Abos, Rechnungen, Checkout, Widerruf, Erstattung, Payout und Reconciliation.
8. Marketplace, Sponsor, Ads, Recruiting und Werbeagentur.
9. Auftragsverarbeiter, Unterauftragnehmer, Drittlandtransfers, Routing, Push und KI.
10. Play-Data-Safety- und App-Store-Privacy-Angaben gegen die reale Konfiguration.
11. Retention, Export, Berichtigung, Löschung, Einwilligungswiderruf und Incident-Prozess.
12. Versionsgebundene Freigabe ohne Platzhalter oder zukünftiges Freigabedatum.

`LEGAL_EXPECTED_VERSION` und `LEGAL_APPROVED_VERSION` müssen beide `2026-08-09` entsprechen. Eine Änderung an Tracking, Anbietern, Minderjährigen, Zahlungen, Marketplace, Recruiting, KI, Gesundheits- oder Standortverarbeitung macht eine erneute Freigabe erforderlich.

## DPIA-/DSFA-Abdeckung

Das Screening umfasst folgende Airmius-Verarbeitungsfamilien:

- Training, Ernährung, Trinken, Körper- und mögliche Gesundheitsdaten.
- Präzise Standort-, Routen- und Live-Trackingdaten.
- Minderjährige, Eltern/Erziehungsberechtigte und andere vulnerable Personen.
- Sport-Matching, Profiling und Recruiting.
- Personalisierte Werbung, Sponsoring und Conversion-Zuordnung.
- Produktanalyse und verhaltensbezogene Kennzahlen.
- KI-Assistenzen, Bilder und Empfehlungen.
- Community, Chat, Dateien und Moderation.
- Zahlungen, Marketplace, Erstattungen und Auszahlungen.
- Vereinsmandanten, Mitgliedschaften, Beiträge und Administration.

Die freizugebende DPIA dokumentiert mindestens:

1. systematische Beschreibung, Umfang, Kontext und Zwecke;
2. Erforderlichkeit, Verhältnismäßigkeit und Rechtsgrundlagen;
3. betroffene Gruppen, Datenflüsse, Empfänger, Auftragsverarbeiter und Transfers;
4. Risiken für Rechte und Freiheiten;
5. Kontrollen, Garantien, technische/organisatorische Maßnahmen und Restrisiko;
6. Rat der datenschutzbeauftragten Person und nachvollziehbare Entscheidung;
7. Sicht betroffener Personen oder Vertretungen, soweit angemessen;
8. vorherige Konsultation nach Art. 36 DSGVO, wenn hohes Restrisiko verbleibt;
9. Änderungs- und periodische Review-Trigger.

Grundlagen sind die [amtliche DSGVO-Fassung bei EUR-Lex](https://eur-lex.europa.eu/eli/reg/2016/679/oj) und die vom EDPB bestätigten [WP29-Leitlinien zur DPIA, WP248 rev.01](https://www.edpb.europa.eu/endorsed-wp29-guidelines_en).

## Unabhängiger Penetrationstest

Der Auftrag benennt explizit alle 16 Scope-Gruppen aus `GovernanceAssuranceRegistry`: öffentliche Gast-/Token-/Checkout-Seiten; Identität und Sessions; Rollen-/Mandanten-/Objekttrennung; API/BOLA/Mass Assignment; Admin/Super-Admin/Support; Chat/Realtime/Uploads; Commerce/Payments/Webhooks; Recruiting/Consent; Training/Gesundheit/GPS; Ads/Sponsor/Agency; E-Learning/Blog/Feed/Story; Mobile Storage/Transport/Push/Deep Links; Eingabe-/Geschäftslogik/Rate Limits; Header/CORS/Storage/Backup/Cloud; Betroffenenrechte; Dependencies/Secrets/Logging/Supply Chain.

Abnahmebedingungen:

- unabhängiger, ausdrücklich autorisierter Prüfer;
- exakte Backend-Release-Version und signierter Mobile-Build;
- freigegebene Rules of Engagement, Scope und Testfenster;
- dedizierte Testdaten, nicht destruktive Durchführung und erreichbarer Incident-Kontakt;
- jeder Scope geprüft oder mit formellem Blocker dokumentiert;
- alle kritischen und hohen Befunde behoben und unabhängig nachgetestet;
- verbleibendes Risiko mit Owner, Termin und formeller Annahme;
- kurze Referenzen auf Executive Report und Remediation-Retest.

Methodische Ausgangspunkte sind der aktuelle [OWASP Web Security Testing Guide](https://owasp.org/www-project-web-security-testing-guide/latest/) und der [BSI-Leitfaden für IS-Penetrationstests](https://www.bsi.bund.de/SharedDocs/Downloads/DE/BSI/Sicherheitsberatung/Pentest_Webcheck/Leitfaden_Penetrationstest.pdf). Der Auftrag muss zusätzlich die reale Airmius-Mandanten-, Zahlungs-, Mobile- und Gastseitenlogik abdecken; ein reiner automatisierter Scanner reicht nicht.

## Datensparsame Evidenz

Für eine lokale Vorbereitung:

```bash
cp resources/release/governance_evidence.template.json resources/release/governance_evidence.local.json
php artisan airmius:audit-governance --json
```

Erlaubt sind ausschließlich kurze Referenzen aus Buchstaben, Zahlen, Punkt, Unterstrich und Bindestrich. Nicht erlaubt sind:

- Namen, E-Mail-Adressen, Telefonnummern oder sonstige personenbezogene Angaben;
- Schwachstellen, Exploitdetails, Risiko-Freitext oder Rohberichte;
- Hosts, URLs, Dateipfade, Requests, Responses, Header, Payloads oder Stacktraces;
- Nutzer-, Geräte-, Vereins-, Bestell- oder Zahlungskennungen;
- Tokens, Cookies, Zugangsdaten, API-Schlüssel oder Secrets.

Der Audit gibt weder lokale Referenzen noch Reviewer-Identitäten aus. Der eigentliche Bericht, die DPIA und Vertragsunterlagen bleiben in einem geschützten, zugriffskontrollierten System.

## Ressourcenschonender Ablauf

| Schritt | Owner | Ergebnis | Richtwert |
| --- | --- | --- | --- |
| Vorlage ausfüllen und Scope-/Artefaktlücken schließen | Engineering / Product / Release | lokale Struktur grün | 0,5–1 Tag |
| Anbieteridentität und Vertragstexte finalisieren | Legal / Management | Legal-Review-Paket | 1–3 Tage plus externe Prüfung |
| DPIA-Workshop und Risikobehandlung | DPO / Product / Security | vollständige DPIA | 2–5 Tage plus Freigabe |
| Pentest beauftragen und Testzugänge vorbereiten | Security / DevOps | Rules of Engagement | 1–2 Tage |
| unabhängigen Test und Remediation durchführen | Assessor / Engineering | Bericht und Retest | typischerweise 1–3 Wochen |
| drei Gates mit Kurzreferenzen freigeben | jeweilige Owner / Release | Strict-Audit grün | 1–2 Stunden |

Die Zeiten sind technische Richtwerte, keine Zusage des externen Legal-, DPO- oder Security-Dienstleisters.
