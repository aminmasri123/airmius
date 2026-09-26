# Vorstand, Ausschüsse und Arbeitsgruppen

## Datenmodell

Die Governance-Struktur wird additiv in zwei Tabellen gespeichert:

- `club_governance_bodies`: Vorstand, Ausschuss oder Arbeitsgruppe mit Name, Beschreibung, Amtszeit und Sichtbarkeit,
- `club_governance_assignments`: Funktion und Verantwortungsbereich einer internen oder extern geführten Person einschließlich eigener Amtszeit und Sichtbarkeit.

Jeder Datensatz gehört unmittelbar zu genau einem Verein. Eine Verantwortlichkeit verweist auf genau eine Person: entweder ein bestehendes Airmius-Mitglied aus demselben Verein oder ein dort geführtes externes Mitglied. Vorhandene Vereinsrollen, Mannschaften, Abteilungen und Mitgliederdaten werden nicht verändert.

## API und Berechtigungen

`GET /api/v1/clubs/{club}/governance` liefert die Gremien und ihre Verantwortlichkeiten. Vereinsverwaltung und Vereinsmitglieder sehen öffentliche und interne Inhalte. Andere angemeldete Personen erhalten bei einem gelisteten Verein ausschließlich ausdrücklich öffentliche Gremien und darin ausschließlich ausdrücklich öffentliche Verantwortlichkeiten.

Nur Personen mit der bestehenden Vereinsberechtigung `update` dürfen Gremien oder Verantwortlichkeiten anlegen, ändern und löschen. Die schreibenden Endpunkte liegen unter `/governance/bodies` beziehungsweise unter dem jeweils gewählten Gremium in `/assignments`. Personenreferenzen und verschachtelte Ressourcen anderer Vereine werden serverseitig abgewiesen.

## Validierung, Löschschutz und Audit

Erlaubte Gremientypen sind `board`, `committee` und `working_group`. Name und Typ sind pro Verein eindeutig. Enddaten dürfen nicht vor ihrem jeweiligen Startdatum liegen. Jede Verantwortlichkeit benötigt eine Funktion und genau eine gültige interne oder externe Person.

Ein Gremium kann nicht gelöscht werden, solange ihm Verantwortlichkeiten zugeordnet sind. Erst nach ausdrücklichem Entfernen oder späterem Umordnen aller Zuordnungen ist das Löschen möglich. Anlegen, Ändern und Löschen erzeugen eigene `club.governance.*`-Auditeinträge. Das Audit enthält nur die betroffene Entitätsart und keine Namen, Beschreibungen oder Verantwortungsfreitexte.

## Web und native App

Das Vereinsprofil lädt die serverseitig gefilterten Gremien über einen eigenen, seitenspezifisch übersetzten Web-Baustein. Berechtigte Vereinsverwaltung kann Gremien und Verantwortlichkeiten anlegen, bearbeiten und nach Bestätigung löschen. Interne und öffentliche Einträge werden entsprechend der API-Antwort gekennzeichnet.

In der nativen App führt die Vereinsorganisation zu einer eigenen Governance-Ansicht mit denselben Lese- und Schreibabläufen. Die Oberfläche und ihre Feldnamen stehen in DE/EN/FR/AR bereit. Schreibaktionen werden weder in die Offline-Warteschlange aufgenommen noch nach einem Verbindungsabbruch automatisch wiederholt; ein unklarer Ausgang wird zuerst durch erneutes Laden geklärt.

## Noch folgende Einführungsarbeit

T013a bis T013c bilden Backend, Web und native App ab. Die Gesamtanforderung T013 bleibt bis zur anonymisierten Bestandsprobe, realen Browser-/Android-/iOS-Abnahme und kontrollierten Einführung in T013d offen. Es wurde keine produktive Migration ausgeführt.
