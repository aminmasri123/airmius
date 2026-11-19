# Quizze und Kursaufgaben

## Bedienung in Web und App

Im Kurs-Studio unter Quiz oder Aufgaben einen Eintrag erstellen. Nach dem Speichern öffnet das Stift-Symbol denselben Editor zum Bearbeiten. In der App verwendet der Editor eine eigene Seite mit festen Feldabständen und einem Speichern-Bereich außerhalb des scrollbaren Inhalts; die Tastatur wird berücksichtigt.

Ein Quiz unterstützt bis zu 50 Fragen mit jeweils eigenem Typ:

- Kurzer Text: automatische Bewertung anhand hinterlegter zulässiger Antworten, ohne Beachtung von Groß-/Kleinschreibung oder zusätzlichen Leerzeichen.
- Langer Text: manuelle Bewertung durch den Kurs-Ersteller; offene Versuche erscheinen zur Bewertung im Studio.
- Mehrfachauswahl: die gesamte Auswahl muss den richtigen Antworten entsprechen.
- Einzelauswahl: genau eine richtige Antwort.
- Sortieren: die beim Erstellen festgelegte Reihenfolge ist die Lösung; Teilnehmende erhalten eine gemischte Reihenfolge und verschieben die Elemente nach oben/unten.

Fragen und Auswahl-/Sortierelemente benötigen Text oder Bild. Beides zusammen ist möglich. Bilder können als URL oder als Datei hinzugefügt werden (PNG, JPEG, WebP, GIF; bis 10 MiB und 4096 × 4096 Pixel). Auswahl-/Sortierfragen benötigen 2–20 Elemente.

Kursaufgaben verwenden denselben Frageneditor. Sie können die fünf Antworttypen kombinieren; die Bewertung bleibt bei Aufgaben manuell über den vorhandenen Punkte-/Feedback-Ablauf. Bestehende Aufgaben ohne strukturierte Fragen behalten ihre Text-/Link-Abgabe.

## Bewertung, Bearbeitung und Berechtigungen

Alle Quizfragen sind gleich gewichtet. Lange Textantworten erhalten bei der manuellen Bewertung richtig/falsch plus optionales Feedback. Ein Versuch mit offenen manuellen Bewertungen gilt niemals als bestanden und löst keinen Abschluss bzw. kein Zertifikat aus.

Beim Absenden wird der damalige Fragenstand einschließlich Quiz-Bestehensgrenze gespeichert. Spätere Änderungen überschreiben diese Grundlage nicht. Geänderte Fragebögen erhöhen ihre Revision; eine noch auf dem alten Stand geöffnete Abgabe wird mit einem Hinweis abgewiesen. Bestehende klassische Quizze behalten ihre bisherige Bewertung, bis sie im neuen Editor gespeichert werden.

Nur der jeweilige Kurs-Ersteller darf Quizze/Aufgaben erstellen, bearbeiten, Bilder hochladen und bewerten. Fremde Frage-IDs sowie unpassende Kurs-/Quiz-/Versuch-Kombinationen werden abgewiesen. Teilnehmende benötigen eine aktive Einschreibung; Freigabezeiten bleiben wirksam. Teilnehmer-Antworten enthalten keine Lösungsschlüssel.

Die Oberflächen und Meldungen sind auf Deutsch, Englisch, Französisch und Arabisch vorhanden.

## Veröffentlichung

Die Änderungen sind lokal implementiert, nicht auf Hostinger veröffentlicht und nicht als Android-Release gebaut.

Zuerst den Backend-Code und die zusammengehörigen Web-Build-Dateien veröffentlichen und die Migration `2026_11_19_000001_extend_learning_assessments.php` mit `php artisan migrate --force` ausführen. Anschließend die neue App bauen/veröffentlichen. Vor Nutzung der neuen Fragetypen müssen Teilnehmende die dazu passende App-Version verwenden; ältere Apps können diese Eingabefelder nicht darstellen.

## Verifikation

- Backend: 47 Tests, 758 Assertions erfolgreich. Gemischte Quizze, automatische/manuelle Bewertung, kein vorzeitiger Abschluss, Snapshots, Revisionen, Web/API-Bearbeitung, fremde Frage-IDs, Bild-Upload-Berechtigungen und bestehende Lernabläufe.
- Web: 12 Komponententests erfolgreich; davon 7 für den neuen Frageneditor, Teilnehmerantworten, Sortierreihenfolge, manuelle Bewertung und Bild-/Textvalidierung. Die übrigen prüfen vorhandene Lösch- und Logo-Abläufe.
- App: 31 Tests im gemeinsamen Regressionslauf erfolgreich; anschließend 8 Assessment-Tests erfolgreich, einschließlich eines zusätzlich ergänzten Tests für fehlgeschlagenes Speichern und erneuten erfolgreichen Versuch. Insgesamt 32 unterschiedliche Tests.
- Dart-Analyse der betroffenen Assessment-, Upload-, Studio- und Teilnehmerdateien: keine Probleme.
- Produktions-Web-Build erfolgreich.

Automatisierte Komponenten-/Widgettests ersetzen keinen Test auf einem echten Android-Gerät. Ein Live-Test auf Hostinger und ein Android-Release-Build wurden hier nicht durchgeführt.
