# AIRMIUS DSA-Moderationsprozess MVP

Stand: 2026-07-17

Diese Datei beschreibt den technischen MVP-Prozess fuer Meldungen, Moderationsentscheidungen, Beschwerden und Moderationslogs. Sie ersetzt keine juristische Pruefung.

## Melden

- Web/API: `POST /reports` bzw. `POST /api/v1/reports`
- Meldbare Objekte: Post, Kommentar, Nachricht, Story, Profil
- Ergebnis:
  - Eintrag in `content_reports`
  - User-Report-Flag in `moderation_flags`
  - Moderationsstatus des Inhalts auf `reported`, sofern das Objekt `moderation_status` unterstuetzt
  - Logeintrag `reported` in `moderation_logs`

## Entscheidung

- Admin Report Review: `PUT /admin/moderation/reports/{report}`
- Admin Flag Review: `PUT /admin/moderation/flags/{flag}`
- Erlaubte Status: `open`, `dismissed`, `actioned`
- Optional: `remove_content=true`
- Ergebnis:
  - `reviewed_by`, `reviewed_at`, `decision_reason`, `action_taken`
  - Inhalt wird bei Entfernung auf `moderation_status=removed` gesetzt oder bei Nachrichten geloescht
  - Logeintrag `decision`

## Beschwerde

- Web/API: `POST /reports/{report}/appeal` bzw. `POST /api/v1/reports/{report}/appeal`
- Nur Reporter der urspruenglichen Meldung duerfen Beschwerde einreichen.
- Beschwerde ist erst nach einer Entscheidung moeglich.
- Ergebnis:
  - `appeal_status=pending`
  - `appeal_reason`, `appealed_at`
  - Logeintrag `appeal_submitted`

## Beschwerdeentscheidung

- Admin: `PUT /admin/moderation/reports/{report}/appeal`
- Status: `accepted` oder `rejected`
- Ergebnis:
  - `appeal_status`, `appeal_decision`, `appeal_decided_by`, `appeal_decided_at`
  - Bei akzeptierter Beschwerde gegen eine dismissed-Meldung wird der Report wieder `open`
  - Logeintrag `appeal_decided`

## Moderationslog

- Tabelle: `moderation_logs`
- Speichert: Falltyp, Fall-ID, Actor, Aktion, vorheriger/neuer Status, Begründung und Metadaten.
- Admin-Moderationsseite liefert Logs pro Report/Flag im Inertia-Payload aus.

## Verifikation

- `tests/Feature/ModerationDsaProcessTest.php`
- Bestehende Report-Tests: `tests/Feature/FeedTest.php`, `tests/Feature/MobileFeedApiTest.php`
