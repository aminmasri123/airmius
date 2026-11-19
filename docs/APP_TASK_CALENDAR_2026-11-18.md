# App-Aufgabenkalender

Lokal umgesetzt am 18.11.2026 in `mobile/airmius_mobile/lib/screens/club_tasks_screen.dart`:

- Monat: echtes Raster mit sieben Spalten, lokalem Wochenbeginn, Markierung für Heute und Auswahl sowie Anzahl der Einträge pro Tag. Antippen zeigt darunter sämtliche Aufgaben und Termine des Tages.
- Woche: sieben kompakte Tagesfelder mit Eintragszahl und Tagesliste für die Auswahl, statt sieben großer leerer Karten.
- Jahr: zwölf Monatskacheln in zwei Spalten mit Anzahl der Einträge. Antippen öffnet den entsprechenden Monat.
- Tag: vollständige Tagesliste; die bisherige Begrenzung auf acht Einträge wurde entfernt. Aufgaben öffnen ihre vorhandene Detailansicht.
- Navigation: bestehende Monats-/Jahreswechsel mit korrekter Begrenzung auf gültige Kalendertage bleiben erhalten. Hinzu kommen Heute-Schaltfläche und an die Schreibrichtung angepasste Pfeile.
- Der Vereinsfilter ist kompakter. Der Hinzufügen-Button liegt in einer eigenen SafeArea unter dem scrollbaren Inhalt und überlagert keine Kalenderinhalte.
- Zwei bei der Prüfung gefundene Überläufe in den Zuordnungs-Auswahlfeldern des Aufgaben-Editors sind korrigiert.

## Prüfung

`club_calendar_test.dart` und `club_task_editor_test.dart`: **14 Tests erfolgreich**. Getestet auf simuliertem 390×844-Gerät: alle Ansichten auf Deutsch, Englisch, Französisch und Arabisch; Monatsraster und Tagesauswahl; Wochenleiste; Jahreskacheln mit Wechsel zum Monat; zehn Einträge vollständig sichtbar; Abstand zum unteren Button; doppelte Systemschrift; Monatswechsel vom 31. und Schaltjahre; Öffnen des Editors, Tastatur, Zuweisung und Checkliste.

Statische Dart-Analyse ohne Befund. Kein Backend-Eingriff und kein neuer Android-Release in diesem Arbeitsschritt. Eine Prüfung auf einem physischen Smartphone ist damit nicht behauptet.
