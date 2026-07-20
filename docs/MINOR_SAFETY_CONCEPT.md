# AIRMIUS Minderjaehrigen-Konzept MVP

Stand: 2026-07-17

Diese Datei beschreibt die technische MVP-Regel fuer Minderjaehrige. Sie ersetzt keine finale juristische Bewertung.

## Alter und Elternfreigabe

- Unter 16 Jahren ist eine `guardian_email` Pflicht.
- Registrierung und Profilvervollstaendigung setzen Nutzer unter 16 auf `minor_pending_consent`.
- Erst nach Guardian-Zustimmung wird `guardian_consent_at` gesetzt und die Rolle `minor_player` vergeben.
- Widerruf setzt den Status wieder zurueck und blockiert normale Nutzung ueber den bestehenden Guardian-Flow.

## Sichtbarkeit

- Unter 16 Jahren werden die Privacy-Defaults erzwungen:
  - `profile_visibility = private`
  - `direct_message_privacy = friends`
  - `friend_request_privacy = friends`
- Settings-, API-Settings-, Profil- und DSGVO-Berichtigungspfade koennen diese Defaults nicht auf `public` oder `everyone` zuruecksetzen.
- Minderjaehrigen-Profile sind sichtbar fuer: die Person selbst, Guardian-Verknuepfungen, Freunde und Plattform-Admins.

## Direktnachrichten

- Direktnachrichten mit Minderjaehrigen unter 16 sind blockiert, solange keine Guardian-Zustimmung aktiv ist.
- Nach Freigabe sind Direktnachrichten nur fuer Freunde oder Guardian-Verknuepfungen erlaubt.
- Die Regel gilt zentral ueber `User::allowsDirectMessagesFrom()` und damit fuer Web-Chat, API-Chat, Profil-CTA und Gruppenchats.

## Technische Quelle

- Zentrale Regel: `App\Support\MinorSafety`
- Consent-Sync: `App\Support\GuardianConsentState`
- Tests: `tests/Feature/MinorSafetyConceptTest.php`, `tests/Feature/GuardianAccessFlowTest.php`, `tests/Feature/RegistrationTest.php`
