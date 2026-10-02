# Nachprüfung der sechs offenen Bereiche

Stand: 01.10.2026. Lokaler Arbeitsstand, kein Deployment und keine Store-Veröffentlichung.

## Technisch erledigt

- [x] Web/App-Kalender: Tag, Woche, Monat und Jahr; Datumsgrenzen und Schaltjahre.
- [x] Native Kursqualitätsprüfung mit Suche, Seitenwechsel, Status, Notiz und Hervorhebung; Webvalidierung und Admin-2FA.
- [x] Fachrollen für Rollenverwaltung und Moderation erhalten begrenzte native Zugänge ohne globale Systemrechte. Daten anderer Bereiche bleiben gesperrt.
- [x] Redaktion: Suche und Seitenwechsel; Metadatenänderungen erhalten bestehendes Rich-HTML und den Veröffentlichungszeitpunkt.
- [x] Betriebskostenverträge: Suche, Status-/Kategoriefilter und Seitenwechsel; globale Summen unabhängig von der aktuellen Seite.
- [x] Facility-Einstieg verwendet echte Vereinsressourcen statt Beispieldaten. Optionales Buchungszeitfenster wird an den bestehenden Checkout gesendet und nach Erfolg neu geladen.
- [x] Persistenznachweis für Ressourcenbuchungen mit anschließendem GET, Konflikten, Wartung und Vereinsgrenzen.
- [x] Native Sponsorprofilfelder einschließlich rechtlicher Daten und expliziter Zustimmungen ergänzt.
- [x] Bekannte falsche Webpfade in der Self-Service-Gap-Matrix korrigiert und mit registrierten Routen verglichen.

Diese Haken bestätigen Implementierung und gezielte automatisierte Nachweise, nicht die vollständigen Live-Szenarien T16-08, T25-02, T29-01, T33-07/10/21 oder T37-06.

## Automatisierte Nachweise

- `ClubInventoryApiTest`: 19 Tests, 264 Assertions. SQLite-Testdatenbank; keine Produktionsdaten verändert. Fester Testzeitpunkt für das historische Öffnungszeiten-Szenario.
- `inventory_checkout_dialog_test.dart`: 3 Widgettests für Mengenvalidierung, Zeitfenster-Payload und echten Facility-Einstieg.
- `DocumentedWorkspaceRoutesTest` und `MobileLearningQualityReviewApiTest`: 7 Tests, 85 Assertions.
- Gemeinsamer Abschlusslauf von `DocumentedWorkspaceRoutesTest`, `ClubInventoryApiTest`, `NativeAdminParityTest`, `SponsorProfileParityTest`, `MobileLearningQualityReviewApiTest`: 36 Tests, 500 Assertions, erfolgreich.
- `NativeAdminParityTest`, `SponsorProfileParityTest`, `MobilePlatformAdminApiTest`, `MobileEditorialSponsorApiTest`, `MobileLearningStudioApiTest`: zusammen 35 Tests, 394 Assertions.
- `MobilePushDeliveryServiceTest`, `VerificationMailSenderTest`, `PaymentWebhookSignatureTest`, `ProviderWebhookAndBankIdempotencyTest`, `UploadValidationTest`, `MobileReleaseEvidenceIntegrityContractTest`: zusammen 27 Tests, 150 Assertions. Kontrollierte Testverträge, keine reale Providerzustellung oder Bankeinreichung.
- `tests/Frontend/clubCalendar.test.mjs`: erfolgreich.
- Sponsor-Widgettests umfassen Neuanlage, Profilzustände, verweigerte Rechte, Validierungs-/Netzfehler, Offline-Warteschlange, Abbruch und schmale Displays. Ein dabei gefundener Layoutüberlauf wurde korrigiert; der Wiederholungslauf mit den Buchungsdialogtests war erfolgreich.
- Gemeinsamer abschließender Lauf von Kalender, Sponsorprofil, Kursqualitätsprüfung, Buchungsdialog und Adminparität: **39 Widgettests erfolgreich** (`/tmp/airmius-parity-widget-tests.log`).
- Vite-Produktionsbuild erfolgreich in `/tmp/airmius-parity-build`; bestehende Hinweise auf Browserslist-Alter. Keine produktiven Assets ersetzt.
- Fokus-Analyse der zwölf geänderten mobilen Screens/Komponenten: ohne Befund.
- Breiter Vergleichslauf `widget_test.dart`: exakt dieselben zwölf fehlgeschlagenen Testnamen im bisherigen Git-Stand und im aktuellen Arbeitsstand; keine zusätzlichen Fehlfälle in diesem Lauf. Protokolle: `/tmp/airmius-baseline-widget-tests.log`, `/tmp/airmius-current-widget-tests.log`. Der Vergleichsordner enthält die Kalenderkorrektur, während die von den zwölf Fehlern betroffenen Quellen dem vorherigen Git-Stand entsprechen.

## Noch nicht abgeschlossen

- [ ] Breite Flutter-Regression: zwölf Fehler auch im isolierten Vergleichsstand. Betroffen sind Feed/Arabisch, DATEV, Teilrechnung, UC25, Aufgaben, UC23-Mitgliederdaten, Rollen-Navigation (zwei Fälle), Chat-Navigation, Guardian, UC31-Dateiupload und arabisches Mitgliedschaftsformular. Diese Suite ist nicht grün; gezielte Tests ersetzen keine vollständige Releasefreigabe. Vergleichsprotokoll: `/tmp/airmius-baseline-widget-tests.log`.
- [ ] Vollständiger nativer Rich-Text-Blogeditor mit Inline-Bildupload und formatgetreuer Vorschau. Formatierung bleibt bei reinen Metadatenänderungen erhalten; Bearbeiten des Textes ist weiterhin Plain Text.
- [ ] Vollständige Listen-/Exportparität für sämtliche Commerce-, Plattform- und Backoffice-Listen; die ergänzte Vertragspaginierung deckt nicht automatisch alle Listen ab.
- [ ] Grafischer Ressourcenplaner und Serienbuchungen. Der Einstieg bietet den realen Inventar-Checkout, keine vollständige Scheduler-Suite.
- [ ] Live-Abnahme auf Gerät und Web mit den betroffenen Rollen.
- [ ] Push: echte Zustellung Vordergrund/Hintergrund, Öffnen der Zielansicht und verweigerte Berechtigung.
- [ ] E-Mail: Testempfänger, Zustellung und Links; keine Nachricht an reale Mitglieder ungeprüft senden.
- [ ] Kamera/GPS/Dateiauswahl: reales Gerät, Erlauben/Ablehnen/Widerrufen, Abbruch, Offline-Fehler.
- [ ] Zahlungen: autorisierte Provider-Sandbox, Erfolg/Abbruch/Webhook-Wiederholung und Abgleich. Keine echte Belastung ausgeführt.
- [ ] Bankdateien: anonymisierte freigegebene Testdatei, Import/Export, Dubletten und fachliche Prüfung. Keine Einreichung bei einer Bank.
- [ ] Store-Update: signiertes Testrelease installieren, Update über vorhandene Installation, Anmeldung und Datenbestand prüfen. Keine Veröffentlichung ausgeführt.

Für Live-Nachweise werden Testkonto/Rollen, Zielumgebung, Android-Gerät und autorisierte Testempfänger bzw. Provider-Sandbox benötigt. Ergebnisse mit Build, Umgebung, Rolle und Datum dokumentieren, erst danach die entsprechenden Live-Checklistenpunkte abhaken.
