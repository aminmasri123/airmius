# AIRMIUS DSGVO-Prozess MVP

Stand: 2026-07-17

Diese Datei beschreibt die technische Umsetzung der Betroffenenrechte im MVP. Sie ersetzt keine anwaltliche Pruefung.

## Export / Auskunft

- Web: `GET /settings/privacy/export` (`auth.settings.privacy.export`)
- API: `GET /api/v1/privacy/export` (`api.v1.privacy.export`)
- Format: JSON, Schema `airmius.privacy-export.v1`
- Inhalt: Account-Metadaten, Profil, Adresse, Privacy-Settings, Guardian-Status, Rollen, Vereine, Teams, Sportprofile, eigene Posts, Dateien-Metadaten, Notifications, Integrationen, Billing-Metadaten und eine Routenuebersicht fuer Betroffenenrechte.

## Berichtigung

- Web: `PATCH /settings/privacy/correction` (`auth.settings.privacy.correct`)
- API: `PATCH /api/v1/privacy/correction` (`api.v1.privacy.correct`)
- Bestehende Oberflaechen bleiben aktiv: Profilformular, Adresse/Privacy-Settings, `PUT /api/v1/me/profile`, `PATCH /api/v1/settings`.
- Aenderungen werden als `privacy.profile_corrected` im Activity-Log protokolliert. Werte werden dabei nicht doppelt im Log gespeichert, nur Feldnamen.

## Loeschung

- Web: `POST /user/deletion-code`, danach `DELETE /user`
- API: `POST /api/v1/account/deletion-code`, danach `DELETE /api/v1/account`
- Schutz: Passwort oder Social-Login-E-Mail plus E-Mail-Code.
- Aktuelles Verhalten: manuelle Kontoloeschung loescht den User-Datensatz nach Code-Bestaetigung.
- Retention-Prozess fuer inaktive Konten: `UserPrivacyRetentionService` anonymisiert Profile, Inhalte, Tokens, Verknuepfungen und Medien.

## Einwilligungswiderruf

- Web: `POST /settings/privacy/withdraw-consents` (`auth.settings.privacy.withdraw-consents`)
- API: `POST /api/v1/privacy/withdraw-consents` (`api.v1.privacy.withdraw-consents`)
- Unterstuetzte Werte: `ads_personalization`, `ads_measurement`, `all`
- Wirkung: setzt die entsprechenden Consent-Felder auf `false` und schreibt `privacy.consent_withdrawn` ins Activity-Log.

## Verifikation

- Feature-Test: `tests/Feature/PrivacyRightsProcessTest.php`
- Bestehender Loesch-Test: `tests/Feature/DeleteAccountTest.php`
