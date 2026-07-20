# AIRMIUS Legal Review Pack

Stand: 2026-07-17

Dieses Dokument ist ein technisches Review-Paket fuer die juristische Freigabe. Es ersetzt keine Rechtsberatung und behauptet keine anwaltliche Finalfreigabe.

## Oeffentliche Rechteseiten

- Impressum: `/impressum`, Route `legal.imprint`, Controller `LegalPageController::imprint`
- Datenschutz: `/datenschutz`, Route `policy.show`, Controller `LegalPageController::privacy`
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

## Sign-off

- Rechtliche Freigabe durch: offen
- Datum der Freigabe: offen
- Freigegebene Version/Commit: offen
- Offene Auflagen: offen

## Technische Verifikation

- `php artisan test tests/Feature/LegalPagesTest.php`
- `php artisan test --compact`
- `npm run build`
