# AIRMIUS MVP Smoke Test Checklist

Stand: 2026-07-07

## Voraussetzung

- Backend laeuft mit `php artisan serve`.
- Vite laeuft mit `npm run dev` oder Assets sind mit `npm run build` gebaut.
- Queue/Reverb laufen, wenn Chat, Notifications oder Realtime getestet werden.
- Flutter nutzt dieselbe API Base URL wie das Web.
- Testrollen sind vorhanden: Sportler, Trainer, Verein-Admin, Elternteil, Plattform-Admin; Details siehe `docs/TEST_ACCOUNTS.md`.

## P0 Kernflows

- [ ] Web Login funktioniert.
- [ ] Flutter Login funktioniert.
- [ ] Web Registrierung funktioniert.
- [ ] Flutter Registrierung zeigt bei Fehlern keinen authentifizierten Zustand.
- [ ] Profil kann im Web gespeichert werden.
- [ ] Profil kann in Flutter gespeichert werden.
- [ ] `/api/v1/dashboard/daily-flow` liefert Daten fuer den eingeloggten Nutzer.
- [ ] `/api/v1/mobile/sync` liefert Contract, Feature Flags, Permissions, Push und Deep Links.
- [ ] Push-Device kann ueber `/api/v1/mobile/push-devices` registriert und geloescht werden.

## Vereine und Mitglieder

- [ ] Verein kann erstellt werden.
- [ ] Verein kann mobil ueber `PUT /api/v1/clubs/{club}` gespeichert werden.
- [ ] Mitgliedsantrag kann club-scoped erstellt werden.
- [ ] Mitgliedsantrag kann ueber `/api/v1/membership-applications/{id}` angezeigt werden.
- [ ] Mitgliedsantrag kann ueber `/api/v1/membership-applications/{id}/withdraw` zurueckgezogen werden.
- [ ] Verein-Admin sieht offene Antraege.
- [ ] Verein-Admin kann Antrag annehmen.
- [ ] Verein-Admin kann Antrag ablehnen.
- [ ] CSV-Mitgliederimport normalisiert `Pruefung`/`Prüfung` zu `pending`.

## Feed, Chat, Events, Training

- [ ] Feed laedt im Web.
- [ ] Feed laedt in Flutter.
- [ ] Beitrag erstellen funktioniert.
- [ ] Kommentar erstellen funktioniert.
- [ ] Like/Helpful funktioniert.
- [ ] Report/Melden funktioniert.
- [ ] Chat-Konversationen laden.
- [ ] Nachricht senden funktioniert.
- [ ] Eventliste laedt.
- [ ] Event erstellen funktioniert.
- [ ] Event-Zusage/Absage funktioniert.
- [ ] Trainingsplaene laden.
- [ ] Trainingslogs laden.

## Dateien, Rechnungen, Notifications

- [ ] `/api/v1/files/upload-intents` liefert Upload-Ziel, Limits und Formfelder.
- [ ] Datei-Upload ueber `/api/v1/uploads` funktioniert.
- [ ] Datei umbenennen funktioniert.
- [ ] Datei loeschen funktioniert.
- [ ] `/api/v1/billing/invoices` liefert Club- und Abo-Rechnungen mit `amount_cents` und `currency`.
- [ ] Einzelrechnung ueber `/api/v1/billing/invoices/{id}` laedt.
- [ ] Notifications laden.
- [ ] Notification gelesen/ungelesen funktioniert.
- [ ] Alle Notifications als gelesen markieren funktioniert.

## Release Blocker

- [ ] `php artisan test` ist gruen oder Restfehler sind dokumentiert.
- [ ] `npm run build` ist gruen.
- [ ] `flutter analyze` ist gruen oder nur akzeptierte Warnings bleiben.
- [ ] `flutter test` ist gruen.
- [ ] Keine kaputten i18n-Zeichen in sichtbarer Navigation.
- [ ] Keine Demo-/Platzhalter-Aktion im MVP-Hauptflow sichtbar.