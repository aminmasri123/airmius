# Kurse und Abschnitte löschen

## Bedienung

- Web: Kurs-Studio → Kurs wählen → Kursdaten → **Kurs dauerhaft löschen**.
- App: Kurs-Studio → Kurs wählen → Einstellungen → **Kurs dauerhaft löschen**.
- Abschnitte: In beiden Oberflächen im Bereich Inhalte am jeweiligen Abschnitt → **Abschnitt löschen**.

Die Bestätigung nennt den Titel sowie die betroffenen Daten. Die Schaltfläche wird erst bei der exakten Eingabe `DELETE` aktiv. Abbrechen löst keinen Request aus. Auch der Server verlangt die Bestätigung. Während der Web-Anfrage werden weitere Löschaktionen gesperrt; nach erfolgreicher Löschung aktualisieren beide Oberflächen die Daten. Fehler führen nicht zum vorzeitigen Entfernen aus der Oberfläche.

## Löschumfang

Ein Kurs wird einschließlich seiner Abschnitte, Lektionen, Quizze und Versuche, Aufgaben und Einreichungen, Kommentare, Notizen, Einschreibungen und Fortschritte, Bewertungen, Gutscheine, Zertifikatsdatensätze und weiteren kursabhängigen Datensätze entfernt. Zertifikatslinks sind danach nicht mehr gültig. Verknüpfte Verkaufsangebote bleiben als Datensätze erhalten, erhalten aber den Status archiviert und verlieren die Kursverknüpfung. Bestellungen und Zahlungsbelege werden nicht gelöscht und keine automatische Rückzahlung ausgelöst. Andere Sprachversionen sowie bereits hochgeladene oder heruntergeladene Dateien bleiben erhalten. Die Warnung bietet Archivieren als Alternative an.

Bei einem Abschnitt werden dessen Lektionen und die an diese Lektionen gebundenen Quizze/Aufgaben samt abhängigen Datensätzen gelöscht. Dadurch bleiben keine versehentlich losgelösten Lektionen, Quizze oder Aufgaben zurück. Kursweite Tests ohne Lektionsbindung, übrige Abschnitte, Einschreibungen und bereits ausgestellte Zertifikate bleiben bestehen. Die Abschnittspositionen, Kursdauer und offenen Fortschrittsanzeigen werden aktualisiert; allein durch das Entfernen von Inhalten werden keine Zertifikate oder Abschlussbelohnungen ausgegeben.

## Berechtigungen und Technik

Web und API verwenden dieselben Controller-Aktionen und den transaktionalen `LearningDeletionService`. Nur der Kursersteller darf löschen. Die Eigentümerprüfung wird auch am gesperrten Kursdatensatz innerhalb der Transaktion durchgeführt. Abschnitte müssen tatsächlich zum angegebenen Kurs gehören. Fremde Nutzer und eingeschriebene Teilnehmer erhalten 403, kursfremde Abschnitts-IDs 404 und Gäste keinen Zugriff. Finanzdatensätze und die Dateien im Upload-Speicher werden von diesem Dienst nicht gelöscht.

## Lokale Prüfung

- `LearningDeletionTest` und `MobileLearningStudioApiTest`: **11 Tests / 118 Assertions erfolgreich**. Web- und API-Löschung, Bestätigungspflicht, Datenkaskaden, andere Sprachversionen, übrige Abschnitte, deaktiviertes Verkaufsangebot, Erhalt echter Bestellungen/Bestellpositionen, Fremdzugriff und Gäste.
- `learning_deletion_test.dart`: **8 Tests erfolgreich**. Tatsächliche Kurs-Studio-Oberfläche auf 390×844: Kurs- und Abschnittslöschung jeweils in DE/EN/FR/AR, Abbrechen, falsche Bestätigung, korrekter Request und automatische Aktualisierung.
- `learningDeletion.test.mjs`: **4 Tests erfolgreich**. Warnung, zwingende exakte Eingabe, Sperrung während Verarbeitung, Zurücksetzen beim Zielwechsel und Abbrechen jeweils in vier Sprachen.
- Dart-Analyse ohne Befund, Vite-Produktionsbuild erfolgreich, `git diff --check` ohne Fehler.

Stand: lokal implementiert und getestet. Keine echten Benutzerkurse gelöscht. Zur Veröffentlichung sind Backend, Sprach-/Datendateien und Web-Assets gemeinsam auszuliefern; die App benötigt einen neuen Build. Keine neue Datenbankmigration erforderlich.
