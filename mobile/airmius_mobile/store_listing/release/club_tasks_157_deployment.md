# Vereinsverwaltung 1.0.71 (157)

## Android

- Mitglieder & Beitraege: Mitglied hinzufuegen und Datei importieren direkt sichtbar.
- Mitgliederformular: externes Mitglied ohne Einladung anlegen oder Einladung senden.
- Vereins-Cockpit: Mitglied hinzufuegen, Vereinskalender und To-dos als waehlbare Schnellaktionen.
- To-dos: offene Vereinsvorgaenge sowie eigene Aufgaben anlegen, bearbeiten, abhaken, wieder oeffnen und loeschen.

## Backend vor dem App-Rollout aktualisieren

Das Backend-Paket enthaelt die neuen Aufgaben-Endpunkte, das Modell und die Migration. Aufgaben werden je Verein gespeichert. Verwaltung nur mit der bestehenden Berechtigung zum Verwalten des Vereins.

1. Backend-Dateien im Laravel-Projekt bereitstellen. Die enthaltene routes/api.php basiert auf dem aktuellen Projektstand; abweichende Server-Aenderungen vor dem Ersetzen zusammenfuehren.
2. Aus dem Projektverzeichnis ausfuehren:

```bash
php artisan migrate --force --path=database/migrations/2026_09_27_000104_create_club_tasks_table.php
php artisan route:cache
```

3. Mit einem Vereinsadmin pruefen: Aufgabe anlegen, bearbeiten, abhaken, wieder oeffnen und loeschen. Mit einem anderen Verein darf diese Aufgabe nicht sichtbar sein.
4. Danach das Android App Bundle mit Versionscode 157 hochladen.

Die Migration wurde in der Testdatenbank geprueft. Das Paket fuehrt selbst keine Migration und keine Veroeffentlichung auf dem Produktionsserver aus.

## Versionshinweise

Deutsch:
Mitglieder direkt hinzufuegen und Dateien importieren: Die wichtigsten Aktionen sind jetzt in Mitglieder & Beitraege sichtbar. Im Vereins-Cockpit lassen sich Mitgliedanlage, Vereinskalender und To-dos als Schnellaktionen auswaehlen. Die neue To-do-Uebersicht verbindet offene Vereinsvorgaenge mit eigenen Aufgaben zum Anlegen, Bearbeiten und Abhaken.

English:
Add members and import files directly from Members & Fees. Club Cockpit quick actions now include adding members, opening the club calendar and viewing to-dos. The new to-do overview brings together pending club actions and custom tasks that you can create, edit and mark as completed.
