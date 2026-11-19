# Kurszertifikate: Logo hochladen

## Umsetzung

Web und App bieten in neuen und bestehenden Kursen zusätzlich zum URL-Feld einen Datei-Upload an. Erlaubt sind PNG, JPEG und WebP bis 5 MB und 4096 × 4096 Pixel. Der Upload funktioniert vor dem Anlegen eines Kurses über einen authentifizierten Endpunkt ohne Kurs-ID. Nach dem Upload wird die zurückgegebene URL in das Formular eingesetzt; erst das Speichern des Kurses übernimmt sie in die Kursdaten. Während des Uploads ist Speichern gesperrt. Bei einem Uploadfehler bleibt die bisherige URL erhalten.

Der Download des Zertifikats verwendet das Logo jetzt als PDF-Bild. Zuvor wurde das bestehende Logo-Feld überhaupt nicht ausgewertet. Eingebettet werden die vom neuen Upload stammenden Dateien im Verzeichnis des jeweiligen Kurserstellers. Die PDF-Erzeugung liest diese aus dem konfigurierten Upload-Speicher und ruft keine fremden URLs auf. Externe Logo-URLs bleiben als bisherige Eingabemöglichkeit erhalten, werden von diesem lokalen PDF-Logo-Lader jedoch nicht heruntergeladen.

Neue Oberflächentexte liegen auf Deutsch, Englisch, Französisch und Arabisch vor. Bestehende Kursberechtigungen und Zertifikats-Zugriffskontrollen bleiben erhalten.

## Aktuelle Möglichkeiten zum Entfernen

Die ursprünglich fehlenden Löschfunktionen wurden anschließend ergänzt; siehe `LEARNING_DELETION_2026-11-19.md`. Kurse können nun in Web und App unter Kursdaten beziehungsweise Einstellungen dauerhaft gelöscht werden, Abschnitte im Inhaltsbereich. Beide Aktionen verlangen eine Warnung und die Eingabe DELETE. Archivieren bleibt als Alternative erhalten.

- Web: Kurs-Studio → eigenen Kurs auswählen → Kursdaten → Status „Archiviert“ → Kursdaten speichern.
- App: Kurs-Studio → eigenen Kurs auswählen → Einstellungen → Kurs bearbeiten → Status „Archiviert“ → Speichern.
- Einzelne Lektionen: im Bereich Inhalte über die jeweilige Löschaktion und Bestätigung entfernbar.
- Abschnitte können nun über „Abschnitt löschen“ entfernt werden, auch wenn sie leer sind. Archivieren ist keine dauerhafte Löschung; die Kursdaten bleiben gespeichert.

## Lokale Prüfung

- `LearningCertificateLogoTest` und `MobileLearningStudioApiTest`: 7 Tests / 62 Assertions, erfolgreich. Darunter Upload vor Kursanlage, Übernahme der URL, eingebettetes Bild im Zertifikats-PDF, Ausschluss fremder Erstellerpfade, gesperrter Gastzugriff und ungültige/zu große Bilder.
- Vorhandene Zertifikatsfälle in `LearningStudioTest`, `LearningLocalizationContractTest`, `MobileLearningApiTest`: 7 Tests / 99 Assertions, erfolgreich.
- `learning_certificate_logo_test.dart`: 6 Tests, erfolgreich. Multipart-Endpunkt, Authentifizierung und MIME-Typ; Dateiauswahl in vier Sprachen, Uploadstatus und Erhalt alter Werte im Fehlerfall.
- `learningCertificateLogo.test.mjs`: 1 Test, erfolgreich. Web-Multipart-Upload, Uploadstatus, Fehleranzeige, Wiederholung und Größenprüfung.
- Dart-Analyse ohne Befund und Vite-Produktionsbuild erfolgreich.

Stand: lokal umgesetzt. Backend-Routen, Controller, PDF-Service und Web-Assets müssen gemeinsam veröffentlicht werden. Die App benötigt einen neuen Build. Es wurde kein produktives Dokument oder Benutzerkurs verändert; eine Datenbankmigration ist nicht erforderlich.
