# Dateien direkt in Lektionen hochladen

## Oberfläche

- App: Kurs-Studio → Kurs → Inhalte → Lektion hinzufügen oder bearbeiten. **Bilder, Videos oder Dokumente hochladen** ist direkt sichtbar und erlaubt mehrere Dateien. Bei Lektionsart Video gibt es zusätzlich **Video vom Gerät hochladen** neben dem weiterhin vorhandenen Video-URL-Feld.
- Hochgeladene Dateien werden mit ihrem ausgewählten Namen im Formular angezeigt und können vor dem Speichern wieder aus der Lektion entfernt werden. Bestehende Links bleiben beim Hinzufügen erhalten. Ein Hauptvideo wird erst nach erfolgreichem Upload ersetzt.
- Während eines Uploads werden Fortschritt beziehungsweise Verarbeitung angezeigt und das Speichern sowie konkurrierende Uploads gesperrt. Die Lektionsart kann während des Uploads nicht gewechselt werden. Bei Abbrechen oder Fehlern bleiben die bisherigen Werte und bereits erfolgreiche Anhänge erhalten.
- Web: Die bereits vorhandenen Video-/Material-Uploads bleiben bestehen. Material erlaubt jetzt Mehrfachauswahl; Speichern und Wechsel des bearbeiteten Lektionsformulars sind während des Uploads gesperrt. Nach einem Uploadfehler bleibt die Fehlermeldung sichtbar und ein weiterer Versuch ist möglich.
- Danach muss die Lektion gespeichert werden. Uploads allein legen noch keine Lektion an. URL-Felder bleiben als Alternative erhalten.

Unterstützte Dateien: PNG/JPEG/WebP/GIF, MP4/WebM/MOV/M4V/MKV/AVI, PDF, DOC/DOCX, PPT/PPTX, XLS/XLSX, CSV/TXT, MP3/WAV/OGG/M4A. Der vorhandene Anwendungsgrenzwert beträgt 500 MiB pro Datei; ein vorgeschaltetes Hosting-/PHP-Limit kann niedriger sein. Die App kann Datei-Streams senden, ohne große Videos vollständig in den Arbeitsspeicher zu laden. Die vorhandene serverseitige Medienoptimierung wird weiterverwendet.

## Rechte und Speicherung

Beide Plattformen verwenden den vorhandenen authentifizierten Kurs-Upload-Endpunkt. Nur der Ersteller des betreffenden Kurses darf Dateien hochladen. Der Server prüft Dateigröße und passende Dateitypen je Zweck; das Hauptvideo-Feld akzeptiert keine Dokumente. Nach dem Speichern stehen Dateien über die bestehenden Video-/Anhangsfunktionen zur Verfügung. Ein aus dem Formular entfernter Upload wird nicht physisch aus dem gemeinsamen Upload-Speicher gelöscht.

## Lokale Prüfung

- `LearningLessonUploadTest` + `MobileLearningStudioApiTest`: **7 Tests / 75 Assertions erfolgreich**. Web/API, Bilder/PDF/Video, Speicherung neuer und bearbeiteter Lektionen, bestehende Links, fremde Kursersteller, Gäste, falscher Typ und Größenlimit.
- Bestehende Fälle `upload_learning_assets` und `local_lesson_video_is_served_only_to_enrolled_users`: **2 Tests / 10 Assertions erfolgreich**.
- `learning_lesson_upload_test.dart`: **6 Tests erfolgreich**, darunter Multipart-Stream, Authentifizierung, Zweck und MIME-Typ, Fortschritt, Mehrfachauswahl auf 390×844 in DE/EN/FR/AR, Entfernen eines neuen Anhangs, Erhalt bestehender Links, Videoersetzung, Abbrechen und Fehlerfall.
- Gemeinsam mit den bisherigen Logo-/Löschtests: **20 Flutter-Tests erfolgreich**.
- Dart-Analyse ohne Befund; Vite-Produktionsbuild erfolgreich; `git diff --check` ohne Fehler.

Stand: lokal umgesetzt und geprüft. Noch kein produktiver Upload durchgeführt und kein neuer App-Release gebaut. Backend, Web-Assets und neuer App-Build müssen veröffentlicht werden; keine Datenbankmigration erforderlich.
