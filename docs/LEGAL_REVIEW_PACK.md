# AIRMIUS Legal Review Pack

Stand: 2026-08-09

Dieses Dokument ist ein technisches Review-Paket fuer die juristische Freigabe. Es ersetzt keine Rechtsberatung und behauptet keine anwaltliche Finalfreigabe.

## Oeffentliche Rechteseiten

- Impressum: `/impressum`, Route `legal.imprint`, Controller `LegalPageController::imprint`
- Datenschutz: `/datenschutz`, Route `policy.show`, Controller `LegalPageController::privacy`
- Kontoloeschung: `/konto-loeschen`, Route `legal.account-deletion`, Controller `LegalPageController::accountDeletion`
- Partielle Datenloeschung: `/daten-loeschen`, Route `legal.data-erasure`, Controller `LegalPageController::dataErasure`
- AGB: `/agb`, Route `terms.show`, Controller `LegalPageController::terms`
- Community-Richtlinien: `/community-richtlinien`, Route `legal.community`, Controller `LegalPageController::community`
- Jugendschutz: `/jugendschutz`, Route `legal.minors`, Controller `LegalPageController::minors`
- Cookies: `/cookies`, Route `legal.cookies`, Controller `LegalPageController::cookies`
- Widerruf: `/widerruf`, Route `legal.withdrawal`, Controller `LegalPageController::withdrawal`
- Kontakt und Melden: `/kontakt-und-melden`, Route `legal.reporting`, Controller `LegalPageController::reporting`

## Pflichtdaten vor Livegang

- `LEGAL_PROVIDER_NAME`
- `LEGAL_STREET`
- `LEGAL_CITY`
- `LEGAL_COUNTRY`
- `LEGAL_EMAIL`
- `LEGAL_SUPPORT_EMAIL`
- `LEGAL_PRIVACY_EMAIL`
- `LEGAL_LEGAL_EMAIL`
- `LEGAL_PHONE`
- `LEGAL_REPRESENTATIVE`
- `LEGAL_REGISTER`
- `LEGAL_VAT_ID`
- `LEGAL_SUPERVISORY_AUTHORITY`
- `LEGAL_CONTENT_RESPONSIBLE`

Zusaetzlich muessen die Billing-/Rechnungsdaten in den Admin-Settings bzw. `config/airmius.php` zu den Impressumsdaten passen.

## Unterlagen im Repository

- `docs/legal/cloudflare_customer_DPAv3-German.pdf`
- `docs/legal/Hostinger DPA-AVV Anhang zur Datenverarbeitung.pdf`
- `docs/DATA_PROCESSING_PROVIDERS.md`
- `docs/SECURITY_REVIEW.md`
- `docs/USER_CASES.md`

## Juristische Pruefpunkte

- Anbieteridentitaet, Registerangaben, USt-ID, Vertreter und ladungsfaehige Anschrift final bestaetigen.
- Datenschutztext gegen tatsaechlich eingesetzte Anbieter, Regionen, AVV/DPA und internationale Transfers pruefen.
- Minderjaehrigenkonzept gegen Art. 8 DSGVO, nationales Recht und Plattformfunktionen pruefen.
- AGB gegen reale Leistungsbeschreibung, Rollen von Vereinen, Trainern, Sportlern, Eltern, Marketplace-Anbietern und Sponsoren pruefen.
- Widerrufsbelehrung fuer digitale Dienste, Add-ons, Marketplace, Ads und Website-/Agenturleistungen finalisieren.
- Community-Richtlinien und Meldeprozesse gegen DSA-/Moderationspflichten pruefen.
- Cookie-/Tracking-Seite gegen die tatsaechlich aktiven Cookies, Ads-Signale, Conversion-Messung und Consent-Flows pruefen.
- Auftragsverarbeiterliste gegen produktive Anbieter und Subprozessoren aktualisieren.

## Mehrsprachige Freigabe

- Deutsch ist die einzige in Code und Katalog gepflegte Ausgangsfassung; `localized-legal-content.v1` liefert EN, FR und AR bereits serverseitig aus und faellt bei fehlender Abdeckung sicher auf Deutsch zurueck.
- Fuer jede der zehn Seiten sind Titel, Abschnitte, Hinweise und Aktionen in EN/FR/AR gegen die deutsche Quelle zu pruefen. Zahlen, Daten, DSGVO-/DDG-Verweise, Pfade und Anbieter-/Produktnamen duerfen sich nicht veraendern.
- Anbietername, Anschrift, Register-, Steuer-, Telefon- und E-Mail-Angaben werden als geschuetzte Werte unveraendert eingesetzt; nur der umgebende Rechtstext wird lokalisiert.
- Arabisch ist zusaetzlich visuell in RTL auf Mobiltelefon und Desktop zu pruefen. Navigation, Fokusreihenfolge, Links, gemischte E-Mail-/Telefonwerte und Satzzeichen muessen lesbar bleiben.
- `translationComplete=true` bestaetigt nur technische Katalogabdeckung. Es ersetzt weder juristische noch muttersprachliche Freigabe; das externe `legal_release_approval` bleibt bis zu dieser Pruefung offen.
- Die Entwuerfe werden mit `scripts/translate_visible_ui_offline.py --source-scope legal --retranslate-identical` ueber das lokal installierte Modell erzeugt. Es werden nur statische deutsche Controller-Bausteine verarbeitet, keine konfigurierten Anbieter-, Adress-, Kontakt- oder Nutzerdaten und kein externer Uebersetzungsdienst.
- `scripts/merge_visible_ui_translations.php` schreibt daraus die vier kleinen, serverseitigen Kataloge unter `resources/legal/`. Lokale Fortschrittsdateien bleiben ignoriert; nur die technisch auditierten Kataloge gehoeren ins Release.

## Sign-off

- Rechtliche Freigabe durch: offen
- Datum der Freigabe: offen
- Freigegebene Version/Commit: offen
- Offene Auflagen: offen

Die Freigabe wird versionsgebunden in der Produktionsumgebung hinterlegt:

```dotenv
LEGAL_APPROVED_BY="Name/Kanzlei"
LEGAL_APPROVED_AT=2026-08-09
LEGAL_APPROVED_VERSION="2026-08-09"
LEGAL_EXPECTED_VERSION="2026-08-09"
LEGAL_APPROVAL_CONDITIONS="Keine"
```

`LEGAL_APPROVED_VERSION` und `LEGAL_EXPECTED_VERSION` müssen identisch sein und dem aktuellen versionierten Release-Vertrag entsprechen. `LEGAL_APPROVED_AT` muss ein gültiges, nicht zukünftiges Datum im Format `YYYY-MM-DD` sein. Bei jeder rechtlich relevanten Änderung an Produkt, Tracking, Anbietern, Minderjährigen-, Zahlungs-, Marketplace-, Recruiting-, KI-, Gesundheits- oder Standortprozessen ist eine neue Freigabe erforderlich.

## Technische Verifikation

- `php artisan test tests/Feature/LegalPagesTest.php`
- `php artisan test tests/Feature/LocalizedLegalGuestPagesTest.php`
- `php artisan test --compact`
- `npm run build`
- `php artisan airmius:audit-legal-readiness` (muss in der Release-Pipeline Exit-Code 0 liefern)
- `php artisan airmius:audit-governance --json --strict` (bleibt bis zu Legal-, DPIA- und unabhängiger Pentest-Freigabe rot)
