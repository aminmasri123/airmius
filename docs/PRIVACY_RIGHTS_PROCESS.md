# AIRMIUS DSGVO-Prozess MVP

Stand: 2026-09-26

Diese Datei beschreibt die technische Umsetzung der Betroffenenrechte im MVP. Sie ersetzt keine anwaltliche Pruefung.

## Export / Auskunft

- Web: `GET /settings/privacy/export` (`auth.settings.privacy.export`)
- API: `GET /api/v1/privacy/export` (`api.v1.privacy.export`)
- Format: JSON, Schema `airmius.privacy-export.v1`
- Inhalt: Account-Metadaten, Profil, Adresse, Privacy-Settings, Guardian-Status, Rollen, Vereine, Teams, Sportprofile, eigene Posts, Dateien-Metadaten, Notifications, Integrationen, Billing-Metadaten und eine Routenuebersicht fuer Betroffenenrechte.
- Prozessnachweis: `rights.process` enthaelt die versionierte Fallmatrix `airmius.privacy-rights-process.v1` mit Frist, Identitaetspruefung, Endpunkten, Status und Evidenzen.

## Berichtigung

- Web: `PATCH /settings/privacy/correction` (`auth.settings.privacy.correct`)
- API: `PATCH /api/v1/privacy/correction` (`api.v1.privacy.correct`)
- Bestehende Oberflaechen bleiben aktiv: Profilformular, Adresse/Privacy-Settings, `PUT /api/v1/me/profile`, `PATCH /api/v1/settings`.
- Aenderungen werden als `privacy.profile_corrected` im Activity-Log protokolliert. Werte werden dabei nicht doppelt im Log gespeichert, nur Feldnamen.

## Loeschung

- Web: `POST /user/deletion-code`, danach `DELETE /user`
- API: `POST /api/v1/account/deletion-code`, danach `DELETE /api/v1/account`
- Schutz: Passwort oder Social-Login-E-Mail plus E-Mail-Code.
- Aktuelles Verhalten: manuelle Kontolöschung loescht den User-Datensatz nach Code-Bestaetigung.
- Retention-Prozess fuer inaktive Konten: `UserPrivacyRetentionService` anonymisiert Profile, Inhalte, Tokens, Verknuepfungen und Medien.

## Einwilligungswiderruf

- Web: `POST /settings/privacy/withdraw-consents` (`auth.settings.privacy.withdraw-consents`)
- API: `POST /api/v1/privacy/withdraw-consents` (`api.v1.privacy.withdraw-consents`)
- Unterstuetzte Werte: `ads_personalization`, `ads_measurement`, `product_analytics`, `recruiting_profile_sharing`, `all`
- Wirkung: setzt die entsprechenden Consent-Felder auf `false` und schreibt `privacy.consent_withdrawn` ins Activity-Log.

## Fallmatrix / Fristen

- API: `GET /api/v1/privacy/rights-process` (`api.v1.privacy.rights-process`)
- Schema: `airmius.privacy-rights-process.v1`
- Standardfrist: 30 Tage.
- Enthaltene Fallarten: Auskunft, Berichtigung, Einschraenkung, Widerspruch und Loeschung.
- Identitaetspruefung: authentifizierte Sitzung fuer Auskunft, Berichtigung und Widerrufe; E-Mail-Code mit Kategoriebindung fuer Teil-Loeschungen; eindeutige E-Mail-Validierung bei E-Mail-Berichtigung.
- Einschraenkung und Widerspruch sind fuer Consent-/Profilfreigaben technisch umgesetzt, aber die allgemeine Fallwarteschlange mit Bearbeiterentscheidung bleibt offen.

## Verifikation

- Feature-Test: `tests/Feature/PrivacyRightsProcessTest.php`
- Bestehender Loesch-Test: `tests/Feature/DeleteAccountTest.php`
