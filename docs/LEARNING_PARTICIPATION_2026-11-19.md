# Kurs verlassen und ausblenden

In Web und App bietet „Mein Lernen“ die Ansichten aktive, verlassene, ausgeblendete Kurse sowie frühere Zugänge. Jede Aktion benötigt eine Bestätigung.

- Ausblenden setzt ausschließlich `hidden_at`. Lernzugriff und Teilnahme bleiben aktiv. Unter ausgeblendeten Kursen kann der Kurs wieder eingeblendet werden.
- Verlassen setzt die Einschreibung auf `left` und speichert `left_at`. Geschützte Lerninhalte und Teilnahmeaktionen sind danach gesperrt. Einschreibung, Fortschritt, Antworten, Abgaben, Notizen, ausgestellte Zertifikate und Kaufbelege werden nicht gelöscht.
- Erneut teilnehmen reaktiviert dieselbe selbst verlassene Einschreibung und setzt Sichtbarkeit wieder zurück. Es wird kein neuer Kauf erstellt. Kurs-Verfügbarkeit, Anmeldefrist und Kapazität werden geprüft. Die Kursdetails zeigen für selbst verlassene Kurse „Erneut teilnehmen“ statt einer erneuten Kaufaufforderung.
- Erstattete oder von der Kursleitung entzogene Zugänge können mit dieser Aktion nicht reaktiviert werden. Ein späterer Rückerstattungs-/Entzugsprozess überschreibt auch den Zustand `left` und sperrt damit den Wiedereinstieg.

Die persönlichen Aktionen laufen über `PUT /api/v1/learning/enrollments/{enrollment}/participation` bzw. die entsprechende Web-Route. Erlaubte Aktionen sind `leave`, `hide`, `show`, `resume`. Nur der Eigentümer der Einschreibung darf sie ausführen; Kurs- und Einschreibungszeilen werden beim Schreiben gesperrt. Persönliches Ausblenden verändert nicht die öffentliche Sichtbarkeit des Kurses.

## Veröffentlichung

Die Umsetzung ist lokal. Vor Freigabe der neuen App und des Web-Builds muss das zugehörige Backend veröffentlicht und die Migration `2026_11_19_000002_add_learning_participation_preferences.php` ausgeführt werden. Der zuvor veröffentlichte Quiz-Fix enthält diese neuen Routen und Spalten noch nicht. Ohne Backend-Update funktionieren die neuen Aktionen nicht.

## Tests

- 38 Backend-Tests / 395 Assertions erfolgreich: Web/API-Aktionen, Eigentümerprüfung, verbotene Reaktivierung, Kapazität, Fortschritts-/Kauferhalt und bestehende Kurs-/Einschreibungsabläufe.
- 9 Flutter-Tests erfolgreich: vollständiger Aktionsablauf mit Bestätigung/Abbruch, kein Kauf beim Wiedereinstieg, vorhandene Discovery-/Kaufnavigation und Layouts in vier Sprachen.
- 10 Web-Tests erfolgreich: Dialogtexte, Abbruch und Busy-Sperre in Deutsch/Englisch/Französisch/Arabisch sowie bestehende Discovery-Helfer.

Kein neues Live-Deployment und kein Store-Upload im Rahmen dieser Umsetzung.
