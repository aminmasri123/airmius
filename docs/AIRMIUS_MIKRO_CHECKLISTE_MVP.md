# 54 -> 25
# AIRMIUS Mikro-Checkliste fuer den stabilen MVP

Stand: 2026-07-07

Legende:
# (1)
- [x] erledigt und lokal verifiziert oder technisch umgesetzt
- [ ] offen, noch nicht fertig oder noch nicht verifiziert

Arbeitsregel: Nach jeder weiteren Bearbeitung wird diese Datei aktualisiert, damit der Fortschritt sichtbar bleibt.

## P0: Sofort starten, blockiert Release

- [x] PHP-Testumgebung reparieren: `pdo_sqlite` und `sqlite3` sind geladen.
- [x] `php artisan test` erneut laufen lassen und echte Codefehler von Umgebungsfehlern trennen.
- [x] Status-Normalisierung fixen: `Pruefung`/`pruefung` muss bei Mitgliedschaften `pending` ergeben.
- [x] Flutter-Testimport korrigieren: `package:airmius_mobile/...` auf `package:airmius/...` aendern.
- [x] Flutter-Registrierungsfehler fixen: bei Register-Failure darf Auth-State nicht `authenticated` werden.
- [x] `flutter test` gruen machen.
- [x] `flutter analyze` gruen machen.
- [x] Node-Abhaengigkeiten installieren und `npm run build` gruen machen.
- [x] README ersetzen: Setup, Web starten, Flutter starten, Tests, Build, Env-Variablen, Deploy.
- [x] `.env.example` pruefen: API URL, Reverb, Sanctum, Mail, Storage, Payments, Push, Maps.
- [x] CI-Minimum anlegen: Composer install, PHP tests, npm build, Flutter analyze/test.
- [x] Smoke-Test-Dokument erstellen: Login, Register, Feed, Club, Team, Event, Chat, Training, Upload.
- [x] Fehlerseiten pruefen: API-v1 401, 403, 404, 422 und 500 sind API-konform; 403-Inertia-Seite ist vorhanden.
- [x] Rate Limits pruefen: Auth, Chat, Upload, Kommentare, Reports und Payments sind fuer API-v1/Web-Routen verdrahtet und getestet.
- [x] Admin-Account und Test-Accounts dokumentieren: Sportler, Trainer, Verein, Elternteil, Admin.

## P1: API und Flutter synchronisieren

- [x] `PUT /api/v1/clubs/{club}` ergaenzen oder Flutter-Speichern entfernen, bis API existiert.
- [x] `/api/v1/membership-applications` ergaenzen: erstellen, anzeigen, zurueckziehen.
- [x] `/api/v1/files/upload-intents` ergaenzen oder Flutter auf bestehende Upload-API umstellen.
- [x] `/api/v1/billing/invoices` ergaenzen oder Flutter-Billing ausblenden.
- [x] `/api/v1/dashboard/daily-flow` routen, falls DashboardController bereits existiert.
- [x] `/api/v1/mobile/sync` routen fuer Flutter-Sync.
- [x] `/api/v1/mobile/push-devices` routen fuer Push-Token.
- [x] Einheitliches API-Fehlerformat festlegen: `message`, `errors`, `code`.
- [x] Einheitliche Pagination festlegen: `data`, `meta`, `links`.
- [x] Flutter-API-Client von Legacy-Fallbacks wie `/friends/*` befreien, sobald `/api/v1` stabil ist.
- [x] Alle Flutter-Screens mit "API fehlt", "spaeter", "Demo" einmal markieren: behalten, anbinden oder aus MVP ausblenden.
- [x] Offline-Queue persistent machen: App-Neustart darf offene Aktionen nicht verlieren.
- [x] Upload-Retry einbauen: abgebrochene Uploads sauber wiederholen.
- [x] Push-Opt-in bauen: Geraet registrieren, Token aktualisieren, Token loeschen bei Logout.
- [x] Deep Links definieren: Club, Team, Event, Post, Chat, Invitation.
- [x] Realtime-Chat in Flutter final anbinden: neue Nachricht, gelesen, typing, Reactions.
- [x] Secure Storage pruefen: Token nie in unsicherem Storage speichern.
- [x] API-Version sichtbar machen: `/api/v1/meta` mit App-Min-Version und Feature Flags nutzen.

## P2: Web/Inertia polieren (12)

- [x] Mobile Bottom Nav kaputte Uebersetzungszeichen bereinigen.
- [x] Grosse Vue-Seiten splitten: Commerce, Admin Commerce, Teams, Training, SportMap.
- [x] Grosse Laravel-Controller splitten: Actions/Services fuer Checkout, Club, Training, Admin Commerce.
- [x] Gemeinsame UI-Komponenten vereinheitlichen: Buttons, Modals, Empty States, Tables, Forms.
- [x] Form-Validierung vereinheitlichen: gleiche Regeln in Web und API.
- [x] Loading States ergaenzen: Feed, Chat, Club-Listen, Uploads, Payments.
- [x] Empty States ergaenzen: keine Teams, keine Events, keine Mitglieder, keine Nachrichten.
- [x] Permission-Fehler freundlich anzeigen: "Du hast dafuer keine Berechtigung".
- [x] Mobile Web pruefen: Dashboard, Clubs, Teams, Feed, Chat, Events.
- [x] Accessibility pruefen: Fokus, Tastatur, Kontraste, Labels.
- [x] Bildgroessen und Lazy Loading pruefen.
- [x] Inertia-Payloads reduzieren, wo Seiten zu schwer sind.
- [x] Public SEO-Seiten pruefen: Title, Meta Description, OG Image, Canonical, Sitemap.
- [x] Oeffentliche Seiten priorisieren: Verein, Event, Sportart, Pricing, Blog.

## P3: Vereins- und Sport-MVP fertigstellen (17) 17

- [x] Rollenmatrix finalisieren: Sportler, Trainer, Verein-Admin, Elternteil, Plattform-Admin.
- [x] Vereinsmitglied einladen: Link, Token, Ablaufdatum, Rollenwahl.
- [x] Mitgliedsantrag bauen: Formular, Status, Annahme, Ablehnung, Rueckzug.
- [x] Mitgliederimport stabilisieren: CSV-Fehlerbericht, Duplikate, Status-Mapping.
- [x] Beitragsregeln finalisieren: monatlich, jaehrlich, Familie, Rabatt, Sonderbeitrag.
- [x] Zahlungsstatus anzeigen: offen, bezahlt, ueberfaellig, storniert.
- [x] Rechnungsuebersicht fuer Verein und Mitglied bauen.
- [x] Audit Log fuer Vereinsaktionen sichtbar machen.
- [x] Team-Mitglieder verwalten: hinzufuegen, entfernen, Rolle aendern.
- [x] Training erstellen, bearbeiten, loeschen.
- [x] Trainingsanwesenheit erfassen.
- [x] Event erstellen, zusagen, absagen, Teilnehmerliste anzeigen.
- [x] Feed posten, kommentieren, liken, melden.
- [x] Chat 1:1 und Gruppenchat final testen.
- [x] Dateiordner und Upload-Rechte testen.
- [x] Notification Center finalisieren: gelesen/ungelesen, loeschen, Linkziel.
- [x] Eltern-/Guardian-Flow definieren: Kind verknuepfen, Einwilligungen, Benachrichtigungen.

## P4: Recht, Vertrauen, Betrieb (12) 12

- [x] Impressum, Datenschutz, AGB, Widerruf und Community Guidelines final pruefen lassen.
- [x] DSGVO-Prozesse bauen: Export, Loeschung, Berichtigung, Einwilligungswiderruf.
- [x] Minderjaehrigen-Konzept finalisieren: Alter, Elternfreigabe, Sichtbarkeit, Direktnachrichten.
- [x] DSA-Prozess bauen: Inhalte melden, Entscheidung, Beschwerde, Moderationslog.
- [x] Cookie/Tracking-Consent pruefen: nur notwendige Cookies ohne Einwilligung.
- [x] Subprozessorenliste pflegen: Hosting, Mail, Storage, Payments, Analytics, Push.
- [x] Backup-Konzept testen: Restore einmal durchfuehren.
- [x] Security Headers aktivieren: HSTS, CSP, X-Frame, Referrer Policy.
- [x] Admin 2FA verpflichtend machen.
- [x] Monitoring aktivieren: Fehler, Queue, Jobs, Webhooks, Mailversand.
- [x] Payment-Webhooks mit echten Signaturen pruefen.
- [x] Store-Readiness vorbereiten: App Icons, Screenshots, Datenschutzangaben, Support-Mail.

## Oeffentliche APIs/Interfaces

- [x] `PUT /api/v1/clubs/{club}`: Vereinsdaten mobil speichern.
- [x] `POST /api/v1/membership-applications`: Mitgliedsantrag aus Flutter/Web erstellen.
- [x] `DELETE /api/v1/membership-applications/{id}` oder Withdraw-Endpoint: Antrag zurueckziehen.
- [x] `POST /api/v1/files/upload-intents`: Upload vorbereiten, Limits/Rechte pruefen.
- [x] `GET /api/v1/billing/invoices`: Rechnungen fuer Nutzer/Verein listen.
- [x] `GET /api/v1/dashboard/daily-flow`: mobile Startseite mit Feed, Events, Tasks, Notifications.
- [x] `POST /api/v1/mobile/push-devices`: Push-Geraet registrieren.
- [x] `POST /api/v1/mobile/sync`: Offline-Aktionen synchronisieren.

## Testplan (8)

- [x] Backend Feature Tests fuer neue API-Routen.
- [x] Flutter Widget Test fuer Login/Register/Dashboard.
- [x] Flutter Repository Tests fuer API-Fehler, Offline, Token Refresh.
- [x] Browser Smoke Test fuer Dashboard, Feed, Clubs, Teams, Events, Chat.
- [x] Permission Tests fuer Sportler, Trainer, Verein-Admin, Elternteil.
- [x] Upload Tests: zu gross, falscher Typ, Erfolg, Abbruch.
- [x] Payment Tests: Checkout, Webhook, Rechnung, Storno.
- [x] Moderation Tests: Report, Review, Entscheidung, Beschwerde.
- [ ] Mobile Realgeraet-Test: Android und iOS, Login, Push, Upload, Deep Link.


### Bearbeitung 2026-07-17: Flutter Widget Tests Login/Register/Dashboard
- [x] Login-Widget-Test ergaenzt: Credentials werden getrimmt/uebergeben, Social-Provider wird ausgeloest.
- [x] Register-Widget-Test ergaenzt: MVP-Felder sichtbar, lokale Geschlecht-Validierung greift.
- [x] Dashboard-Widget-Test ergaenzt: Startseite, Begruessung, Training und Widget-Anpassung rendern.
- [x] Register-UI Material-Assertion behoben: `CheckboxListTile` hat eigenen `Material`-Ancestor.
- [x] Dashboard-Stat-Karten gegen Render-Overflow bei kompakter Breite stabilisiert.
- [x] Verifiziert: `flutter test test/widget_test.dart` = 20 passed.
- [x] Verifiziert: `git diff --check` fuer bearbeitete Flutter-/Checklist-Dateien = sauber.


### Bearbeitung 2026-07-17: Flutter Repository Tests API-Fehler/Offline/Token Refresh
- [x] API-Fehler-Test ergaenzt: `AirmiusApiAuthRepository.currentUser()` reicht Status, Pfad und erste Validierungsmeldung weiter.
- [x] Offline-Test ergaenzt: `/api/v1/mobile/sync` wird offline gequeued und spaeter sauber geflusht.
- [x] Token-Refresh-Test ergaenzt: gespeicherter Token wird bei `restore()` gegen `/api/v1/me` aktualisiert.
- [x] Auth-State verbessert: refreshed User wird beim Restore wieder im TokenStore persistiert.
- [x] Verifiziert: `flutter test test/widget_test.dart` = 23 passed.
- [x] Verifiziert: `git diff --check` fuer bearbeitete Flutter-/Checklist-Dateien = sauber.


### Bearbeitung 2026-07-17: Browser Smoke Test Kernseiten
- [x] `MobileWebSmokeTest` erweitert: Dashboard, Clubs, Teams, Feed, Chat und Events pruefen nicht nur die Inertia-Komponente, sondern auch geladene Smoke-Daten.
- [x] Dashboard prueft kommendes Event, Clubs/Teams pruefen Club- und Team-Payload.
- [x] Feed prueft Smoke-Post, Chat prueft letzte sichtbare Nachricht, Events prueft Event- und Team-Payload.
- [x] Verifiziert: `php artisan test tests/Feature/MobileWebSmokeTest.php` = 1 passed, 88 assertions.
- [x] Verifiziert: `git diff --check` fuer bearbeitete Test-/Checklist-Dateien = sauber.


### Bearbeitung 2026-07-17: Persona Permission Tests
- [x] Neuer Feature-Test `PersonaPermissionMatrixTest` fuer Sportler, Trainer, Verein-Admin und Elternteil.
- [x] Sportler: Feed/Teams erlaubt, Vereinsverwaltung verboten.
- [x] Trainer: Trainer-Cockpit erlaubt, Vereinsverwaltung verboten.
- [x] Verein-Admin: Vereins-/Mitgliederverwaltung erlaubt.
- [x] Elternteil: Kinderbereich erlaubt, Vereinsverwaltung verboten.
- [x] Verifiziert: `php artisan test tests/Feature/PersonaPermissionMatrixTest.php` = 4 passed, 63 assertions.
- [x] Verifiziert: `git diff --check` fuer bearbeitete Test-/Checklist-Dateien = sauber.


### Bearbeitung 2026-07-17: Upload Tests
- [x] Neuer Feature-Test `UploadValidationTest` fuer API-Upload-Flow.
- [x] Zu grosse Datei wird bereits beim Upload-Intent ueber `size_bytes` abgelehnt.
- [x] Gefaehrlicher Dateityp/Extension wird beim echten Upload abgelehnt.
- [x] Erfolgreicher PDF-Upload erstellt Datei und Storage-Objekt.
- [x] Abbruch/Cleanup ueber `DELETE /api/v1/uploads/{file}` loescht Datenbankeintrag und Storage-Datei.
- [x] Verifiziert: `php artisan test tests/Feature/UploadValidationTest.php` = 1 passed, 19 assertions.
- [x] Verifiziert: `git diff --check` fuer bearbeitete Test-/Checklist-Dateien = sauber.


### Bearbeitung 2026-07-17: Payment Tests Checkout/Webhook/Rechnung/Storno
- [x] Neuer Feature-Test `PaymentMvpFlowTest` fuer den mobilen Bankueberweisungs-Checkout.
- [x] Checkout prueft `awaiting_transfer`, Zahlungsreferenz, Bankdaten und API-Payment-Action.
- [x] Rechnung wird beim Checkout erstellt und in `/api/v1/billing/invoices` mit offener Summary sichtbar.
- [x] Storno ueber `/api/v1/subscription-checkouts/{checkout}/cancel` setzt Checkout und Rechnung auf `cancelled`.
- [x] Bestehende Webhook-Signaturtests fuer Stripe, Commerce, PayPal und Outfit-Subscriptions mitverifiziert.
- [x] Verifiziert: `php artisan test tests/Feature/PaymentMvpFlowTest.php tests/Feature/PaymentWebhookSignatureTest.php tests/Feature/ClubInvoicePaymentStatusTest.php` = 9 passed, 160 assertions.
- [x] Verifiziert: `git diff --check` fuer bearbeitete Test-/Checklist-Dateien = sauber.


### Bearbeitung 2026-07-17: Moderation Tests
- [x] Bestehender Feature-Test `ModerationDsaProcessTest` deckt Report, Admin-Review, Entscheidung, Content-Removal und Beschwerde ab.
- [x] Moderationslog wird fuer Meldung, Entscheidung, Beschwerde und Beschwerdeentscheidung geprueft.
- [x] Automatische Moderationsflags werden mit Entscheidung und Audit-Log verifiziert.
- [x] Verifiziert: `php artisan test tests/Feature/ModerationDsaProcessTest.php` = 2 passed, 38 assertions.
- [x] Verifiziert: `git diff --check` fuer bearbeitete Checklist-Datei = sauber.


### Bearbeitung 2026-07-17: Mobile Realgeraet-Test vorbereitet
- [x] Lokalen Geraetestatus geprueft: `flutter devices` erkennt nur `Linux (desktop)`.
- [x] Toolchain geprueft: `flutter doctor -v` meldet fehlendes Android SDK; iOS ist auf Ubuntu nicht real testbar.
- [x] Runbook ergaenzt: `mobile/airmius_mobile/store_listing/release/real_device_smoke_test_runbook.md`.
- [x] Linux-Setup-Runbook ergaenzt: `mobile/airmius_mobile/store_listing/release/linux_android_setup_runbook.md`.
- [x] Linux-Prerequisite-Checker ergaenzt: `mobile/airmius_mobile/scripts/assert_linux_android_release_prerequisites.sh`.
- [x] Android-Realgeraet-Evidence-Script ergaenzt: `mobile/airmius_mobile/scripts/run_android_real_device_smoke.sh`.
- [x] iOS/TestFlight-Evidence-Script ergaenzt: `mobile/airmius_mobile/scripts/run_ios_real_device_smoke.sh`.
- [x] Checker in Release-Bundle, Evidence-Template, Quickstart und Command-Cheatsheet verlinkt.
- [x] Linux-Checker in Manifest/Next-Step-/Evidence-Reports verlinkt.
- [x] Android-Evidence-Script in Release-Bundle, Manifest, Runbook, Quickstart und Command-Cheatsheet verlinkt.
- [x] iOS-Evidence-Script in Manifest/Next-Step-/Evidence-Reports verlinkt.
- [x] iOS-Evidence-Script in Release-Bundle, Manifest, Runbook, Quickstart und Command-Cheatsheet verlinkt.
- [x] Manifest-Gate `real_device_smoke` fuer Android+iOS Realgeraet-Smoke ergaenzt.
- [x] Manual-Evidence-Pack um `real_device_smoke` mit Android-/iOS-Evidence-Notizen erweitert.
- [x] Realgeraet-Smoke-Evidence-Validator ergaenzt: `mobile/airmius_mobile/scripts/assert_real_device_smoke_evidence.sh`.
- [x] Linux-Android-Setup fuer Ubuntu-Google-Installer korrigiert: kein gemischtes `android-sdk`/Google-Tools-Setup, `cmdline-tools/13.0/bin` wird erkannt.
- [x] Konfliktfreien Linux-Android-SDK-Installer ergaenzt: `mobile/airmius_mobile/scripts/install_android_sdk_user.sh` installiert in `~/Android/Sdk` statt ueber kollidierende Ubuntu-Android-Pakete.
- [x] Linux-Setup-Runbook priorisiert jetzt User-SDK-Installation und dokumentiert die Entfernung kollidierender Pakete wie `android-sdk`, `aapt`, `aidl`, `zipalign`, `adb`.
- [x] Linux-Prerequisite-Checker erkennt den Default-Pfad `~/Android/Sdk`, findet `cmdline-tools/latest`, `cmdline-tools/13.0` und bricht bei kaputtem `adb devices` kontrolliert mit Handlungshinweisen ab.
- [x] Release-Template, Quickstart, Manual Gates, Command-Cheatsheet und Evidence-Bundle-Checks nehmen den neuen User-SDK-Installer auf.
- [x] Verifiziert: `bash -n mobile/airmius_mobile/scripts/assert_real_device_smoke_evidence.sh` = OK.
- [x] Verifiziert: `mobile/airmius_mobile/scripts/assert_real_device_smoke_evidence.sh` bricht korrekt ab, wenn Android-/iOS-Evidence-Dateien fehlen.
- [x] Verifiziert: `mobile/airmius_mobile/scripts/assert_real_device_smoke_evidence.sh --android /tmp/airmius-android-smoke-preflight-20260718/manual_result_template.md --ios /tmp/airmius-ios-smoke-preflight-20260718/manual_result_template.md` bricht korrekt bei offenen Checkboxen und fehlendem `Result: PASS` ab.
- [x] Verifiziert: `bash -n mobile/airmius_mobile/scripts/install_android_sdk_user.sh` = OK.
- [x] Verifiziert: `bash -n mobile/airmius_mobile/scripts/assert_linux_android_release_prerequisites.sh` = OK.
- [x] Verifiziert: `bash -n mobile/airmius_mobile/scripts/run_android_real_device_smoke.sh` = OK.
- [x] Verifiziert: `bash -n mobile/airmius_mobile/scripts/run_ios_real_device_smoke.sh` = OK.
- [x] Verifiziert: `mobile/airmius_mobile/scripts/install_android_sdk_user.sh --dry-run` laeuft durch und zeigt die geplante User-SDK-Installation.
- [x] Verifiziert: `mobile/airmius_mobile/store_listing/release/release_evidence_manifest.json` = valides JSON.
- [x] Verifiziert: `mobile/airmius_mobile/scripts/run_android_real_device_smoke.sh --preflight-only --output-dir /tmp/airmius-android-smoke-preflight-20260718` bricht korrekt vor dem Smoke-Test ab, solange Android-Prerequisites fehlen.
- [x] Verifiziert: `mobile/airmius_mobile/scripts/run_ios_real_device_smoke.sh --preflight-only --output-dir /tmp/airmius-ios-smoke-preflight-20260718` bricht auf Linux korrekt mit macOS/Xcode-Hinweis ab.
- [x] Verifiziert: `mobile/airmius_mobile/scripts/assert_linux_android_release_prerequisites.sh --require-android-device` bricht korrekt mit fehlendem JDK, Android SDK, `adb`, `sdkmanager`, Lizenzen und Geraet ab.
- [x] Verifiziert: `mobile/airmius_mobile/scripts/assert_linux_android_release_prerequisites.sh --require-android-device` bricht auch bei nicht startbarem `adb` sauber ab und zeigt den User-SDK-Installationsweg.
- [x] Verifiziert: `git diff --check` = sauber.
- [x] Geprueft: `pwsh` ist auf diesem Ubuntu-Host nicht installiert; PowerShell-Release-Scripte wurden daher nicht lokal ausgefuehrt.
- [x] Release-Gates und Quickstart auf den Realgeraet-Test fuer Login, Push, Upload und Deep Links verlinkt.
- [ ] Android Realgeraet-Test ausfuehren und Evidenz ablegen.
- [ ] iOS Realgeraet-/TestFlight-Test ausfuehren und Evidenz ablegen.
- [ ] Hauptpunkt erst abhaken, wenn Android und iOS beide bestanden sind.


### Abschlussverifikation 2026-07-17: Testplan-Regression
- [x] Verifiziert: `php artisan test tests/Feature/PaymentMvpFlowTest.php tests/Feature/PaymentWebhookSignatureTest.php tests/Feature/ClubInvoicePaymentStatusTest.php tests/Feature/ModerationDsaProcessTest.php tests/Feature/UploadValidationTest.php tests/Feature/MobileWebSmokeTest.php tests/Feature/PersonaPermissionMatrixTest.php` = 17 passed, 368 assertions.


### Bearbeitung 2026-07-07: Backend final gruen
- [x] Mobile Story Delete rueckwaertskompatibel: direkte Legacy-Storys 204, API-optimierte Upload-Storys 200 mit `data.deleted`.
- [x] Voller Backend-Testlauf gruen: `php artisan test --compact` = 275 passed, 7 skipped.


### Abschlussverifikation 2026-07-07
- [x] Abschlussverifikation `php artisan test --compact`: 275 passed, 7 skipped.
- [x] Abschlussverifikation `npm run build`: gruen.
- [x] Abschlussverifikation `flutter analyze`: No issues found.
- [x] Abschlussverifikation `flutter test`: All tests passed.

## Verifikation

- [x] `flutter analyze`
- [x] `flutter test`
- [x] `npm run build`
- [x] `npm audit --audit-level=high`
- [x] `php artisan test tests/Unit/ClubMembershipImportServiceTest.php`
- [x] `php -m` zeigt `pdo_sqlite` und `sqlite3`
- [x] `php -m` zeigt `gd`
- [x] `php artisan test` vollstaendig gruen: 275 passed, 7 skipped.

## Aktueller Blocker

- [x] Volle Backend-Suite gruen machen: `php artisan test --compact` = 275 passed, 7 skipped.
- [x] PHP GD Extension installieren: `gd` ist geladen.
- [x] Test-UserFactory um `gender` ergaenzen, damit Standard-Testnutzer nicht mehr in `/profile-completion` laufen.
- [x] Fehlende Sport-CV-, Scout-Search-, Team-Daily-Life-, Team-Competitiveness- und Trainer-Cockpit-Routen verdrahten.
- [x] Event/Ride-Modellfelder fuer Teilnahme-Deadline und Fahrgemeinschaften ergaenzen.
- [x] Echte Feature-Fehler gruppiert fixen: fehlende Routen, unerwartete Redirects, API-Contract-Abweichungen, Marketplace/Feed/Mobile-Flows.

### Bearbeitung 2026-07-07: API-Fehlervertrag
- [x] API-v1 Exception-Rendering zentral verdrahtet: Auth, Validation, Authorization, NotFound, MethodNotAllowed, HTTP und 500.
- [x] Fehlerformat kompatibel gemacht: Top-Level `message`, `errors`, `code` plus bestehendes `error.code`/`error.message`.
- [x] Contract-Test fuer 401, 403, 404, 405, 422 und 500 ergaenzt.
- [x] Verifiziert: `php artisan test --filter=MobileApiContractTest` = 17 passed.
- [x] Verifiziert: `php artisan test --compact` = 275 passed, 7 skipped.

### Bearbeitung 2026-07-07: Rate Limits
- [x] Neue benannte Limiter fuer `content-comments`, `content-reports` und `payment-actions` angelegt.
- [x] API-v1 Throttles fuer Auth, Chat, Uploads, Kommentare/Reviews, Reports und Payment-Aktionen verdrahtet.
- [x] 429-Fehlervertrag geprueft: Rate-Limit-Antwort liefert `code=rate_limited` im API-v1-Fehlerformat.
- [x] Verifiziert: `php artisan test --filter=ApiRateLimitContractTest` = 2 passed.
- [x] Verifiziert: `php artisan test --compact` = 275 passed, 7 skipped.

### Bearbeitung 2026-07-07: Test-Accounts
- [x] `docs/TEST_ACCOUNTS.md` erstellt: Plattform-Admin, Sportler, Trainer, Verein-Admin und Elternteil dokumentiert.
- [x] Sicherheitsregel dokumentiert: keine festen produktiven Demo-Passwoerter im Repository.
- [x] README und Smoke-Test-Checkliste auf die Testaccount-Doku verlinkt.

### Bearbeitung 2026-07-07: Pagination Contract
- [x] `ApiPagination` um gemeinsame `meta`, `links` und `payload`-Methoden erweitert.
- [x] Manuelle Pagination in Events und Notifications auf `data`, `meta`, `links` vereinheitlicht.
- [x] Upload-Workspace-Pagination um `links` ergaenzt, ohne bestehende direkte Pagination-Keys zu entfernen.
- [x] Verifiziert: `php artisan test --filter=ApiPaginationContractTest` = 2 passed.
- [x] Verifiziert: `php artisan test --compact` = 275 passed, 7 skipped.

### Bearbeitung 2026-07-08: Flutter API Legacy-Fallbacks
- [x] Login, Profilabruf und Logout im aktiven `AirmiusApiClient` nur noch auf `/api/v1` verdrahtet.
- [x] `friendsMe()` als kompatibler Alias auf `me()` belassen, ohne eigenen `/friends`-Request.
- [x] Optionale Flutter-Payload-Felder mit null-aware Map-Werten bereinigt: Folder `parent_id`, Event `max_participants`.
- [x] Flutter-Tests gegen Legacy-Retry auf `/friends/auth/login`, `/friends/me` und `/friends/auth/logout` ergaenzt.
- [x] Verifiziert: `flutter analyze` = No issues found.
- [x] Verifiziert: `flutter test` = All tests passed.

### Bearbeitung 2026-07-08: Flutter Club-Save an API angebunden
- [x] `updateClub` im Flutter-API-Client und Club-Repository ergaenzt.
- [x] Club-Inline-Speichern nutzt jetzt `PUT /api/v1/clubs/{id}` statt falschem `API fehlt`-Hinweis.
- [x] Club-Karte laedt nach erfolgreichem Speichern neu und zeigt echtes Erfolg-/Fehlerfeedback.
- [x] Flutter-Test fuer `PUT /api/v1/clubs/{id}` ergaenzt.
- [x] Verifiziert: `flutter analyze` = No issues found.
- [x] Verifiziert: `flutter test` = All tests passed.

### Bearbeitung 2026-07-08: Flutter MVP Screen Audit
- [x] `docs/FLUTTER_MVP_SCREEN_AUDIT.md` erstellt: behalten, angebunden und aus MVP ausgeblendet dokumentiert.
- [x] `AirmiusMvpSurface` als zentrale MVP-Oberflaeche ergaenzt.
- [x] Shell-Navigation, Dashboard, Operations Hub und Settings auf MVP/Dev-Modus gefiltert.
- [x] Exakte `API fehlt`-Treffer im Flutter-Code entfernt: keine Treffer mehr.
- [x] Dev-Suites bleiben per `--dart-define=AIRMIUS_SHOW_DEV_SUITES=true` erreichbar.
- [x] Verifiziert: `flutter analyze` = No issues found.
- [x] Verifiziert: `flutter test` = All tests passed.

### Bearbeitung 2026-07-08: Persistente Offline-Queue
- [x] `AirmiusQueuedTransport` speichert Offline-Aktionen in `AirmiusPreferencesStore`.
- [x] Queue wird beim Senden/Flush wiederhergestellt und nach erfolgreichem Flush geleert.
- [x] Fehlgeschlagene Flushes mit HTTP 599 bleiben in der Queue.
- [x] Service-Container nutzt standardmaessig den Plattform-Preferences-Store fuer die Queue.
- [x] Flutter-Test fuer Queue-Persistenz ueber Transport-Neustart ergaenzt.
- [x] Verifiziert: `flutter analyze` = No issues found.
- [x] Verifiziert: `flutter test` = All tests passed.

### Bearbeitung 2026-07-08: Upload-Retry
- [x] `AirmiusUploadRetryPolicy` ergaenzt: Retry bei Timeout, Netzwerkfehlern, HTTP 408/429 und 5xx.
- [x] Datei-Manager-Upload baut pro Versuch eine frische Multipart-Anfrage.
- [x] Feed-Bild- und generische Upload-Endpunkte nutzen denselben Retry.
- [x] Validierungsfehler und lokal nicht lesbare Dateien werden nicht blind erneut gesendet.
- [x] Flutter-Test fuer transienten Retry und 422-Abbruch ergaenzt.
- [x] Verifiziert: `flutter analyze` = No issues found.
- [x] Verifiziert: `flutter test` = All tests passed.

### Bearbeitung 2026-07-08: Push-Opt-in
- [x] `AirmiusPushDeviceRegistry` ergaenzt: persistente Device-ID, Opt-in-State, Registrierung, Token-Refresh und Unregister.
- [x] Flutter-API-Client um `POST /api/v1/mobile/push-devices`, `DELETE /api/v1/mobile/push-devices/{device_id}` und `/api/v1/mobile/sync` erweitert.
- [x] Auth-State loescht das Push-Device vor Backend-Logout.
- [x] Notification-Settings-Push-Schalter an die echte Registry/API angebunden.
- [x] Token-Provider als austauschbare Schnittstelle vorbereitet; ohne Firebase/APNS-Provider wird kein Dummy-Token an die API gesendet.
- [x] Flutter-Tests fuer Registrierung, Token-Refresh und Logout-Unregister ergaenzt.
- [x] Verifiziert: `flutter analyze` = No issues found.
- [x] Verifiziert: `flutter test` = All tests passed.

### Bearbeitung 2026-07-08: Deep Links
- [x] Flutter-Resolver fuer Club, Team, Event, Post, Chat und Invitation erweitert.
- [x] Flutter-Navigator kennt die neuen Zieltypen und routet in passende MVP-Screens.
- [x] Mobile Deep-Link-Domain-Config um MVP-Routen und Einladungstoken erweitert.
- [x] Backend-Sync-Contract listet die MVP-Deep-Links konsistent.
- [x] Backend-Resolver unterscheidet Club-Profil und Club-Billing und erkennt Team/Post/Chat/Invitation.
- [x] Flutter-Test fuer MVP-Deep-Link-Resolver ergaenzt.
- [x] Backend-Feature-Test fuer `/api/v1/mobile/deep-links/resolve` ergaenzt.
- [x] Verifiziert: `php artisan test tests/Feature/MobileDeepLinkContractTest.php` = 1 passed.
- [x] Verifiziert: `flutter analyze` = No issues found.
- [x] Verifiziert: `flutter test` = All tests passed.

### Bearbeitung 2026-07-08: Flutter Realtime-Chat MVP
- [x] API-v1 Chat um `POST /api/v1/chat/conversations/{conversation}/read` erweitert.
- [x] Typing-Endpunkt speichert Presence kurzzeitig im Cache und liefert `typing_users`.
- [x] Messages-Response liefert mobile Typing-Informationen als `chat.typing_users`.
- [x] Flutter-Chat pollt Nachrichten alle 4 Sekunden als stabilen MVP-Realtime-Fallback.
- [x] Neue Nachrichten und Reactions werden durch Polling aktualisiert.
- [x] Chat markiert Nachrichten beim Laden/Refresh als gelesen.
- [x] Composer sendet Typing true/false best-effort an die API.
- [x] Message-Modell wertet Read-State aus `read_at`/Receipts aus.
- [x] Backend-Feature-Test fuer Typing-Status und Read-Endpoint ergaenzt.
- [x] Flutter-Client-Test fuer Read- und Typing-Endpunkte ergaenzt.
- [x] Verifiziert: `php artisan test tests/Feature/MobileChatRealtimeContractTest.php` = 1 passed.
- [x] Verifiziert: `flutter analyze` = No issues found.
- [x] Verifiziert: `flutter test` = All tests passed.

### Bearbeitung 2026-07-08: Secure Storage
- [x] `AirmiusSecureTokenStore` schreibt Auth-Tokens nicht mehr in den einfachen Preferences-Fallback.
- [x] Legacy-Token aus dem alten Store werden nur einmalig in Secure Storage migriert und danach geloescht.
- [x] Wenn Secure Storage nicht schreiben kann, wird kein unsicherer Fallback beschrieben.
- [x] Service-Container nutzt weiterhin standardmaessig `AirmiusSecureTokenStore`.
- [x] Flutter-Tests fuer Legacy-Migration und Fallback-Verbot ergaenzt.
- [x] Verifiziert: `flutter analyze` = No issues found.
- [x] Verifiziert: `flutter test` = All tests passed.

### Bearbeitung 2026-07-08: API-Version und Feature Flags
- [x] `/api/v1/meta` liefert `api_version`, `contract_version`, `minimum_app_version` und `feature_flags`.
- [x] Feature Flags fuer MVP-Surface, Offline-Queue, Upload-Retry, Push, Deep Links, Chat-Polling und Secure Storage ergaenzt.
- [x] Flutter-API-Client liest `/api/v1/meta` ueber `apiMeta()`.
- [x] API-Connection-Screen zeigt API-Version, Mindest-App-Version und Feature-Flag-Anzahl aus dem Meta-Endpunkt.
- [x] Backend-Contract-Test fuer Meta erweitert.
- [x] Flutter-Test fuer den Meta-Endpunkt ergaenzt.
- [x] Verifiziert: `php artisan test tests/Feature/MobileApiContractTest.php --filter=mobile_meta` = 1 passed, 36 assertions.
- [x] Verifiziert: `flutter analyze` = No issues found.
- [x] Verifiziert: `flutter test` = All tests passed.

### Bearbeitung 2026-07-08: Grosse Vue-Seiten gesplittet
- [x] `Teams/Index.vue` nutzt `useTeamsWorkspace`; Team-/Vereinslogik bleibt im Composable.
- [x] `Training/Index.vue` nutzt `useTrainingWorkspace`; fehlende Datei-Input-Refs im Composable ergaenzt.
- [x] `Commerce/Index.vue` nutzt `useCommerceWorkspace`; Shop-, Checkout-, Ads-, Provider- und Payout-Logik ausgelagert.
- [x] `Admin/Commerce/Index.vue` nutzt `useAdminCommerceWorkspace`; Admin-Forms, Statusaktionen und Modals ausgelagert.
- [x] `SportMap/Index.vue` nutzt `useSportMapWorkspace`; Tracking-, Routing-, Karten- und Generatorlogik ausgelagert.
- [x] SportMap-Workspace um Template-kompatible Tracking-Labels, Playback-Speed-Alias und Landing-Actions normalisiert.
- [x] Page-Groesse reduziert: die fuenf Page-Dateien von 18.270 auf 10.112 Zeilen.
- [x] Verifiziert: `npm run build` = erfolgreich.
- [x] Verifiziert: `git diff --check` fuer die betroffenen Vue-/Composable-/Doku-Dateien = sauber.

### Bearbeitung 2026-07-08: Grosse Laravel-Controller gesplittet
- [x] Checkout-Payload-, Address-, Cart-Resource- und Pricing-Country-Logik in `CommerceCheckoutPayloadService` ausgelagert.
- [x] Club-Profil-Inertia-Payload in `ClubProfilePayloadService` ausgelagert.
- [x] Training-Plan-, Log-, Plan-Item- und User-Serialisierung in `TrainingResourceService` ausgelagert.
- [x] Admin-Commerce-Summary, Ad-Report und Commerce-Settings in `AdminCommerceDashboardPayloadService` ausgelagert.
- [x] Test-Mail-Transporte in `TransactionalMail` fuer `testing` auf den PHPUnit-Mailer festgelegt, damit Tests keine echten SMTP-Hosts ansprechen.
- [x] Verifiziert: PHP-Syntax fuer alle betroffenen Controller, Services und `TransactionalMail`.
- [x] Verifiziert: `php artisan test` = 277 bestanden, 7 uebersprungen.
- [x] Verifiziert: `git diff --check` fuer die betroffenen PHP- und Doku-Dateien = sauber.

### Bearbeitung 2026-07-08: Gemeinsame UI-Komponenten
- [x] `AppButton` als gemeinsame Button-Basis ergaenzt: Varianten `primary`, `secondary`, `danger`, `ghost`, `subtle`, Groessen, Loading, Disabled und Icon-only.
- [x] Bestehende `PrimaryButton`, `SecondaryButton` und `DangerButton` auf `AppButton` umgestellt, ohne bestehende Imports zu brechen.
- [x] `Modal`-Basis mit einheitlichem Icon-Close-Button, ARIA-Label und fester Hitbox verbessert.
- [x] `DialogModal`, `ConfirmationModal`, `ConfirmActionModal` und `DeleteConfirmModal` auf Theme-Tokens bzw. gemeinsame Button-/Form-Komponenten umgestellt.
- [x] `AppEmptyState`, `AppTable` und `AppFormField` als gemeinsame Basis fuer leere Zustaende, Tabellen und Formularfelder ergaenzt.
- [x] `MailCenterAuditTable` nutzt `AppTable`; `LearningStudioEmptyState` nutzt `AppEmptyState`; `DeleteConfirmModal` nutzt `AppFormField`.
- [x] Verifiziert: `npm run build` = erfolgreich.
- [x] Verifiziert: `git diff --check` fuer die betroffenen UI-/Doku-Dateien = sauber.

### Bearbeitung 2026-07-08: Form-Validierung Web/API
- [x] `ClubProfileRules` als gemeinsame Regelquelle fuer Vereinsprofil-Store und -Update ergaenzt.
- [x] Web-Club-Erstellung und Web-Club-Update nutzen die gemeinsamen Regeln.
- [x] API-v1-Club-Erstellung und `PUT /api/v1/clubs/{club}` nutzen dieselben Regeln.
- [x] Unit-Test `ClubProfileRulesTest` stellt sicher, dass Store/Update die Profilfelder synchron halten und nur die erwarteten Sonderfelder abweichen.
- [x] Verifiziert: PHP-Syntax fuer Rules, Web-Controller, API-Controller und `routes/api.php`.
- [x] Verifiziert: `php artisan test tests/Unit/ClubProfileRulesTest.php` = 1 bestanden, 20 Assertions.
- [x] Verifiziert: `php artisan test tests/Feature/MobileApiContractTest.php` = 17 bestanden, 274 Assertions.
- [x] Verifiziert: `php artisan test` = 278 bestanden, 7 uebersprungen.
- [x] Verifiziert: `git diff --check` fuer die betroffenen PHP-/Doku-Dateien = sauber.

### Bearbeitung 2026-07-08: Loading States
- [x] `AppLoadingState` als gemeinsame Ladeanzeige fuer Formulare und Aktionen ergaenzt.
- [x] Feed-Composer zeigt Posting-Status und deaktivierten Loading-Button.
- [x] Chat zeigt Ladezustand beim Senden und beim Nachladen aelterer Nachrichten.
- [x] Clubprofil zeigt Speicherstatus fuer Vereinsdaten.
- [x] Dateiablage zeigt Upload- und Ordner-Erstellstatus auf Mobile und Desktop.
- [x] Commerce zeigt Processing-State fuer Kampagnenspeicherung und Checkout/Payment.
- [x] Verifiziert: `npm run build` = erfolgreich.
- [x] Verifiziert: `git diff --check` fuer die betroffenen Vue-/Composable-/Doku-Dateien = sauber.

### Bearbeitung 2026-07-08: Empty States
- [x] Teams-Workspace zeigt klare Empty States fuer Vereine ohne Teams.
- [x] Teamkarten zeigen Empty States fuer Teams ohne Mitglieder.
- [x] Vereinsmitgliederlisten im Teams-Workspace und Clubprofil zeigen Empty States.
- [x] Teamprofil zeigt Empty State fuer Teams ohne sichtbare Mitglieder.
- [x] Chat-Sidebar und Chatverlauf nutzen `AppEmptyState` fuer keine Chats, keine passenden Chats und keine Nachrichten.
- [x] Eventliste nutzt `AppEmptyState` fuer keine passenden Events mit Aktionen fuer Filter-Reset und Event-Erstellung.
- [x] Verifiziert: `npm run build` = erfolgreich.
- [x] Verifiziert: `git diff --check` fuer die betroffenen Vue-/Doku-Dateien = sauber.

### Bearbeitung 2026-07-08: Permission-Fehler
- [x] `PermissionDeniedMessage` als zentrale Backend-Meldung fuer generische 403/Authorization-Fehler ergaenzt.
- [x] Web/Inertia-403-Seite zeigt den freundlichen Titel `Du hast dafür keine Berechtigung`.
- [x] API-v1-403-Antworten normalisieren generische `Forbidden`-Texte auf dieselbe freundliche Meldung.
- [x] Globale Inertia-Feedback-Toasts normalisieren generische 403-Fehler.
- [x] Feature-Tests fuer Web-403 und API-v1-403 erweitert.
- [x] Verifiziert: PHP-Syntax fuer `PermissionDeniedMessage`, `ApiErrorResponse` und `bootstrap/app.php`.
- [x] Verifiziert: `php artisan test tests/Feature/ClubMembershipAccessTest.php` = 3 bestanden, 29 Assertions.
- [x] Verifiziert: `php artisan test tests/Feature/MobileApiContractTest.php --filter=regular_club_member_cannot_read_member_or_billing_management_api` = 1 bestanden, 30 Assertions.
- [x] Verifiziert: `npm run build` = erfolgreich.
- [x] Verifiziert: `php artisan test` = 278 bestanden, 7 uebersprungen.
- [x] Verifiziert: `git diff --check` fuer die betroffenen PHP-/Vue-/Doku-Dateien = sauber.

### Bearbeitung 2026-07-08: Bildgroessen und Lazy Loading
- [x] Feed-Avatare, Postbilder und Bildanhaenge mit `loading`, `decoding`, `width` und `height` ergaenzt.
- [x] Club- und Teamprofil-Cover/Logos als eager sichtbare Hero-Bilder markiert.
- [x] Club-/Team-Mitgliederbilder und Sidebar-Avatare lazy/async ergaenzt.
- [x] Chat-Anhangsbilder lazy/async und Media-Preview eager/async ergaenzt.
- [x] Commerce-Warenkorb-, Shop-, Marketplace- und Ads-Vorschaubilder mit stabilen Bildattributen ergaenzt.
- [x] Wiederverwendbare Club-, Chat- und Commerce-Komponenten synchron angepasst.
- [x] Verifiziert: `npm run build` = erfolgreich.
- [x] Verifiziert: `git diff --check` fuer die betroffenen Vue-/Doku-Dateien = sauber.

### Bearbeitung 2026-07-08: Public SEO-Seiten
- [x] Serverseitige SEO-Tags fuer Pricing, Vereine und Marketplace per Feature-Test abgesichert: Title, Description, Canonical und OG Image.
- [x] Marketplace-Produktdetailseiten liefern initiales Product-SEO mit Canonical, OG Image, `og:type=product` und JSON-LD.
- [x] Marketplace-Anbieterseiten liefern initiales Provider-SEO mit Canonical und Beschreibung.
- [x] Sitemap ergaenzt dynamische oeffentliche Marketplace-Produkt-URLs.
- [x] Sitemap ergaenzt oeffentliche Marketplace-Anbieter-URLs fuer Anbieter mit veroeffentlichten Angeboten.
- [x] Verifiziert: `php artisan test tests/Feature/PublicSeoTest.php` = 8 bestanden, 56 Assertions.
- [x] Verifiziert: `php artisan test` = 282 bestanden, 7 uebersprungen.
- [x] Verifiziert: `npm run build` = erfolgreich.
- [x] Verifiziert: `git diff --check` fuer die betroffenen PHP-/Doku-Dateien = sauber.

### Bearbeitung 2026-07-08: Mobile Web Smoke
- [x] Automatisierten Mobile-Web-Smoke-Test mit iPhone-User-Agent ergaenzt.
- [x] Dashboard, Clubs, Teams, Feed, Chat und Events rendern fuer eingeloggten Nutzer mit echtem Club-/Team-Kontext.
- [x] Inertia-Komponenten und `Vary: X-Inertia` werden pro Kernseite geprueft.
- [x] Testdaten decken Club, Team, Feed-Post, Event und Chat-Konversation ab.
- [x] Hinweis: Das ist ein stabiler serverseitiger Mobile-Smoke-Test, kein Pixel-/Viewport-Screenshot-Test.
- [x] Verifiziert: `php -l tests/Feature/MobileWebSmokeTest.php` = keine Syntaxfehler.
- [x] Verifiziert: `php artisan test tests/Feature/MobileWebSmokeTest.php` = 1 bestanden, 66 Assertions.
- [x] Verifiziert: `php artisan test` = 283 bestanden, 7 uebersprungen.

### Bearbeitung 2026-07-08: Accessibility-Basis
- [x] Auth-Layout mit Skip-Link zum Hauptinhalt ergaenzt.
- [x] Global sichtbare `:focus-visible`-Markierung und kontraststarken Fokus-Ring ergaenzt.
- [x] Auth-Hauptbereich als `main-content` fokussierbar und mit Seitenlabel versehen.
- [x] Mobile Suche, Benachrichtigungen, Chat-Link und Mobile-Menue-Button mit ARIA-Labels/States ergaenzt.
- [x] Feedback-Toasts als `role="status"` mit `aria-live="polite"` markiert.
- [x] Guest-Navigation/Subnavigation mit Navigation-Landmarks, Mobile-Dialog-Attributen und Close-Labels ergaenzt.
- [x] Footer-Scrollbuttons auf `type="button"` normalisiert.
- [x] Accessibility-Smoke-Test fuer Fokus, Tastatur, Labels, Landmarks und Fokus-Kontrast ergaenzt.
- [x] Hinweis: Das ist eine MVP-Basispruefung, kein vollstaendiger Screenreader-/Axe-/Pixel-Audit.
- [x] Verifiziert: `php artisan test tests/Feature/AccessibilitySmokeTest.php` = 3 bestanden, 28 Assertions.
- [x] Verifiziert: `npm run build` = erfolgreich.
- [x] Verifiziert: `php artisan test` = 286 bestanden, 7 uebersprungen.
- [x] Verifiziert: `git diff --check` fuer die betroffenen Vue-/CSS-/Test-/Doku-Dateien = sauber.

### Bearbeitung 2026-07-08: Inertia-Payloads
- [x] Teams-Index sendet den ungenutzten `availableUsers`-Prop nicht mehr.
- [x] Ungenutzte `admins`- und `invitations`-Relationen aus dem Teams-Payload entfernt.
- [x] Team-Mitglieder werden in Teams-/Clubs-Listen nur noch mit benoetigten Feldern geladen; Club-Mitglieder-E-Mails bleiben erhalten, weil die UI sie anzeigt.
- [x] Team-Einladungen laden beim Einladenden nur noch `id` und `name`.
- [x] Regressionstest verhindert, dass fremde globale Nutzer wieder in die Teams-Inertia-Payload gelangen.
- [x] Props-Payload-Budget fuer Teams-Index ergaenzt.
- [x] Verifiziert: `php artisan test tests/Feature/InertiaPayloadBudgetTest.php tests/Feature/MobileWebSmokeTest.php` = 2 bestanden, 86 Assertions.
- [x] Verifiziert: `npm run build` = erfolgreich.
- [x] Verifiziert: `php artisan test` = 287 bestanden, 7 uebersprungen.
- [x] Verifiziert: `git diff --check` fuer die betroffenen Controller-/Test-/Doku-Dateien = sauber.

### Bearbeitung 2026-07-08: Oeffentliche Seiten priorisiert
- [x] Verein-, Pricing- und Blog-Seiten bleiben als priorisierte Public-Einstiege erhalten.
- [x] Neue Public-Seite `/veranstaltungen` fuer oeffentliche Events ergaenzt.
- [x] Neue Public-Seite `/sportarten` fuer aktive Sportarten mit Vereins-/Team-Zaehlern ergaenzt.
- [x] Event- und Sportart-Seiten in Public-Subnavigation verlinkt.
- [x] Serverseitige SEO-Defaults fuer `Guest/Events` und `Guest/Sportarten` ergaenzt.
- [x] Sitemap nimmt Event- und Sportart-Public-URLs auf.
- [x] Feature-Tests fuer Event-/Sportart-SEO und priorisierte Sitemap erweitert.
- [x] Verifiziert: `php artisan test tests/Feature/PublicSeoTest.php` = 10 bestanden, 72 Assertions.
- [x] Verifiziert: `npm run build` = erfolgreich.
- [x] Verifiziert: `php artisan test` = 289 bestanden, 7 uebersprungen.
- [x] Verifiziert: `git diff --check` fuer die betroffenen Route-/SEO-/Vue-/Test-/Doku-Dateien = sauber.

### Bearbeitung 2026-07-08: Rollenmatrix
- [x] Zentrale MVP-Rollenmatrix fuer Sportler, Trainer, Verein-Admin, Elternteil und Plattform-Admin ergaenzt.
- [x] Rollenmatrix mit bestehenden Plattform-, Vereins- und Teamrollen verdrahtet.
- [x] `/api/v1/meta` liefert die Rollenmatrix fuer Web-/Flutter-Clients aus.
- [x] Dokumentation der Rollenmatrix in `docs/AIRMIUS_ROLE_MATRIX_MVP.md` ergaenzt.
- [x] Unit-Test prueft Reihenfolge, Rollenkonsistenz und Plattform-Admin-Vollzugriff.
- [x] Mobile-API-Contract-Test prueft die ausgelieferte Rollenmatrix.
- [x] Verifiziert: `php artisan test tests/Unit/AirmiusRoleMatrixTest.php` = 3 bestanden, 54 Assertions.
- [x] Verifiziert: `php artisan test tests/Feature/MobileApiContractTest.php --filter=mobile_meta` = 1 bestanden, 43 Assertions.
- [x] Verifiziert: `php artisan test --compact` = 292 bestanden, 7 uebersprungen.
- [x] Verifiziert: `git diff --check` fuer die betroffenen Rollenmatrix-/API-/Test-/Doku-Dateien = sauber.

### Bearbeitung 2026-07-08: Vereinsmitglied einladen
- [x] Club-Einladungen speichern jetzt Token, Link und Ablaufdatum zentral ueber `ClubExternalMember::issueInvitation()`.
- [x] Migration fuer `club_external_members.invitation_expires_at` ergaenzt.
- [x] API-Invite unterstuetzt `role` und `invitation_expires_at`; API-Payload liefert `invitation_token`, `invitation_url` und `invitation_expires_at`.
- [x] Web-Mitgliederverwaltung bietet im E-Mail-Invite-Modal Rollenwahl und optionales Ablaufdatum.
- [x] Akzeptieren einer externen Einladung uebernimmt die gewaehlte Vereinsrolle in `club_user.role` und `club_user.roles`.
- [x] Abgelaufene Einladungslinks liefern HTTP 410 und werden als `expired` markiert.
- [x] Owner wird bewusst nicht als Einladungsrolle angeboten; Owner-Uebergabe bleibt ein separater Verwaltungsfall.
- [x] Feature-Test fuer API-Invite, Rollenuebernahme und abgelaufene Links ergaenzt.
- [x] Verifiziert: `php artisan test tests/Feature/ClubMemberInvitationFlowTest.php` = 3 bestanden, 24 Assertions.
- [x] Verifiziert: `npm run build` = erfolgreich.
- [x] Verifiziert: `php artisan test --compact` = 295 bestanden, 7 uebersprungen.
- [x] Verifiziert: `git diff --check` fuer die betroffenen Invite-/Controller-/Vue-/Test-/Doku-Dateien = sauber.

### Bearbeitung 2026-07-08: Mitgliedsantrag
- [x] Bestehender Web-Flow fuer Antrag stellen, Status anzeigen, Rueckzug, Annahme und Ablehnung per Regressionstest abgesichert.
- [x] Web verhindert neue Mitgliedsantraege fuer Nutzer, die bereits Vereinsmitglied sind.
- [x] Web-Ablehnung ist nur noch fuer offene Antraege moeglich; bereits entschiedene Antraege bleiben stabil.
- [x] API-Mitgliedsantraege pruefen jetzt Pflichtdokumente analog zum Web-Formular.
- [x] API prueft Vereins-Zahlmethode und Beitragsintervall konsistent.
- [x] API blockiert neue Mitgliedsantraege von bestehenden Vereinsmitgliedern.
- [x] Angefragtes Beitragsintervall wird in der API auch fuer die Vorschau uebernommen.
- [x] Feature-Test fuer Formular, Status, Annahme, Ablehnung, Rueckzug und API-Pflichtdokumente ergaenzt.
- [x] Verifiziert: `php artisan test tests/Feature/ClubMembershipApplicationFlowTest.php` = 3 bestanden, 36 Assertions.
- [x] Verifiziert: `php artisan test --compact` = 298 bestanden, 7 uebersprungen.
- [x] Verifiziert: `git diff --check` fuer die betroffenen Mitgliedsantrags-/Test-/Doku-Dateien = sauber.

### Bearbeitung 2026-07-08: Mitgliederimport
- [x] CSV-/XLSX-Import behaelt Quell-Zeilennummern fuer Fehlerberichte.
- [x] Import erkennt ungueltige oder fehlende E-Mail-Adressen und meldet sie mit Zeilennummer.
- [x] Import erkennt doppelte E-Mails innerhalb derselben Datei, verarbeitet den ersten Datensatz und ueberspringt Folgeduplikate.
- [x] Import-Report wird ueber Inertia-Flash ausgeliefert und in der Mitgliederverwaltung angezeigt.
- [x] Controller-Import nutzt dieselbe Status-/Betrags-/Datums-/IBAN-/BIC-/Boolean-Normalisierung wie der Import-Service.
- [x] Status-Mapping fuer `Pruefung`/`Prüfung`, `pausiert`, `aktiv`, `ehemalig` und weitere Aliase ist zentralisiert.
- [x] Beitragsintervall-Mapping deckt `monthly`, `quarterly`, `four_monthly`, `semi_yearly`, `yearly` und `once` ab.
- [x] Import respektiert Planlimits fuer manuelle Mitgliederanlage.
- [x] Feature-Test fuer CSV-Fehlerbericht, Duplikate und Status-/Intervall-/Betrag-Mapping ergaenzt.
- [x] Verifiziert: `php artisan test tests/Feature/ClubMembershipImportFlowTest.php tests/Unit/ClubMembershipImportServiceTest.php` = 2 bestanden, 27 Assertions.
- [x] Verifiziert: `npm run build` = erfolgreich.
- [x] Verifiziert: `php artisan test --compact` = 299 bestanden, 7 uebersprungen.
- [x] Verifiziert: `git diff --check` fuer die betroffenen Import-/Inertia-/Vue-/Test-/Doku-Dateien = sauber.

### Bearbeitung 2026-07-08: Beitragsregeln
- [x] Beitragsregeltypen zentral definiert: Standardbeitrag, Familienbeitrag, Rabatt, Sonderbeitrag.
- [x] Web/API liefern Regeltyp- und Rabattoperator-Optionen aus.
- [x] Web/API validieren Rabattregeln mit Operator und Wert.
- [x] Sonderbeitraege werden auf einmalige Abrechnung begrenzt.
- [x] Regelhistorie zeigt Regeltyp und Rabattdetails.
- [x] Monats-/Jahres-/Einmal-Intervalle bleiben ueber bestehende `CONTRIBUTION_INTERVALS` abgedeckt.
- [x] Feature-Test fuer Web- und API-Regeltypen ergaenzt.
- [x] Verifiziert: `php artisan test tests/Feature/ClubContributionRulesTest.php` = 2 bestanden, 25 Assertions.
- [x] Verifiziert: `npm run build` = erfolgreich.
- [x] Verifiziert: `php artisan test --compact` = 301 bestanden, 7 uebersprungen.
- [x] Verifiziert: `git diff --check` fuer die betroffenen Beitragsregel-/Controller-/Vue-/Test-Dateien = sauber.

### Bearbeitung 2026-07-08: Zahlungsstatus
- [x] Rechnungsstatus zentral am `Invoice`-Model definiert: offen, bezahlt, ueberfaellig, storniert.
- [x] `InvoiceResource` liefert `status_label` fuer Flutter/API-Clients aus.
- [x] Club-Billing-API liefert `invoice_status_options`.
- [x] Club-Management-API liefert `invoice_status_options` und zaehlt stornierte Rechnungen nicht mehr als offen.
- [x] Web-Vereinsverwaltung liefert `invoiceStatusOptions` als Inertia-Prop.
- [x] Rechnungslisten zeigen Status-Badge plus Statusauswahl mit Backend-Labels.
- [x] Feature-Test fuer Web- und API-Zahlungsstatus ergaenzt.
- [x] Verifiziert: `php artisan test tests/Feature/ClubInvoicePaymentStatusTest.php` = 2 bestanden, 57 Assertions.
- [x] Verifiziert: `npm run build` = erfolgreich.
- [x] Verifiziert: `php artisan test --compact` = 303 bestanden, 7 uebersprungen.
- [x] Verifiziert: `git diff --check` fuer die betroffenen Zahlungsstatus-/Controller-/Vue-/Test-Dateien = sauber.

### Bearbeitung 2026-07-08: Rechnungsuebersicht
- [x] Zentrale `BillingOverview`-Summary fuer Club- und Mitgliedsrechnungen ergaenzt.
- [x] Vereinsverwaltung liefert `invoice_summary` pro Club.
- [x] Club-Billing-API liefert `invoice_summary` unabhaengig von Pagination.
- [x] Mitglieder-Settings liefern `billingHistory.summary` fuer Club- und Airmius-Rechnungen.
- [x] `/api/v1/settings` und `/api/v1/billing/invoices` liefern Mitglieds-Summary und Statuslabels aus.
- [x] Web zeigt Summary-Cards fuer Vereinsrechnungen und Mitgliedsrechnungen.
- [x] Feature-Test fuer Vereins- und Mitglieds-Rechnungsuebersicht erweitert.
- [x] Verifiziert: `php artisan test tests/Feature/ClubInvoicePaymentStatusTest.php` = 3 bestanden, 112 Assertions.
- [x] Verifiziert: `npm run build` = erfolgreich.
- [x] Verifiziert: `php artisan test --compact` = 304 bestanden, 7 uebersprungen.
- [x] Verifiziert: `git diff --check` fuer die betroffenen Rechnungsuebersicht-/Controller-/Vue-/Test-Dateien = sauber.

### Bearbeitung 2026-07-08: Audit Log
- [x] Zentrale `ClubAuditLog`-Schicht auf Basis des vorhandenen `activities`-Tables ergaenzt.
- [x] Vereinsverwaltung liefert `audit_logs` pro Club aus.
- [x] Club-Billing-API und Club-Management-API liefern `audit_logs` aus.
- [x] Rechnungsanlage, Rechnungsstatuswechsel und Zahlungserfassung schreiben Audit-Eintraege.
- [x] Web-Vereinsverwaltung zeigt einen Audit-Tab mit Zeitpunkt, Aktion, Person und Details.
- [x] Feature-Test fuer Audit-Erstellung und Sichtbarkeit in Web/API ergaenzt.
- [x] Verifiziert: `php artisan test tests/Feature/ClubAuditLogTest.php` = 1 bestanden, 32 Assertions.
- [x] Verifiziert: `npm run build` = erfolgreich.
- [x] Verifiziert: `php artisan test --compact` = 305 bestanden, 7 uebersprungen.
- [x] Verifiziert: `git diff --check` fuer die betroffenen Audit-/Controller-/Vue-/Test-Dateien = sauber.

### Bearbeitung 2026-07-08: Team-Mitgliederverwaltung
- [x] Web-Route `POST /teams/{team}/members` fuer direktes Hinzufuegen bestehender Vereinsmitglieder ergaenzt.
- [x] API-Routen `POST /api/v1/teams/{team}/members` und `DELETE /api/v1/teams/{team}/members/{user}` ergaenzt.
- [x] Direkte Teamaufnahme verlangt bestehende Vereinsmitgliedschaft; externe Nutzer laufen weiter ueber Einladung/Antrag.
- [x] Teamrollen koennen im bestehenden Web/API-Flow weiter geaendert werden.
- [x] Entfernen aus dem Team synchronisiert den Team-Chat und laesst die Vereinsmitgliedschaft bestehen.
- [x] Team-Resource liefert `can_remove_members` fuer Mobile-/API-Clients aus.
- [x] Web-Teamansicht bietet Auswahl vorhandener Vereinsmitglieder plus Rollenwahl zum Hinzufuegen.
- [x] Feature-Test fuer Web/API Hinzufuegen, Rollenwechsel, Entfernen und Nicht-Vereinsmitglied-Block ergaenzt.
- [x] Verifiziert: `php artisan test tests/Feature/TeamMemberManagementTest.php` = 3 bestanden, 29 Assertions.
- [x] Verifiziert: `npm run build` = erfolgreich.
- [x] Verifiziert: `php artisan test --compact` = 308 bestanden, 7 uebersprungen.
- [x] Verifiziert: `git diff --check` fuer die betroffenen Team-/Route-/Vue-/Test-/Doku-Dateien = sauber.

### Bearbeitung 2026-07-08: Training erstellen/bearbeiten/loeschen
- [x] API-Route `POST /api/v1/training/plans` fuer Trainingsplan-Erstellung ergaenzt.
- [x] API-Routen `PUT` und `DELETE /api/v1/training/plans/{trainingPlan}` fuer Plan-Bearbeitung und -Loeschung ergaenzt.
- [x] API-Routen fuer Plan-Einheiten ergaenzt: `POST`, `PUT`, `DELETE /api/v1/training/plans/{trainingPlan}/items`.
- [x] Plan-Write-Rechte nutzen bestehende `TrainingResourceService::canWritePlan`-Regel.
- [x] Loeschen von Plaenen und Einheiten bereinigt ungenutzte Trainingsbilder.
- [x] `TrainingPlanResource` liefert `can_write`, `can_delete` und vollstaendigere Einheitenfelder fuer Mobile/API aus.
- [x] Feature-Test fuer API-Plan-CRUD, API-Einheiten-CRUD und Read-only-Schreibschutz ergaenzt.
- [x] Verifiziert: `php artisan test tests/Feature/TrainingPlanApiCrudTest.php` = 3 bestanden, 35 Assertions.
- [x] Verifiziert: `php artisan test tests/Feature/TrainingSystemTest.php tests/Feature/TrainerCockpitWeeklyControlTest.php` = 5 bestanden, 42 Assertions.
- [x] Verifiziert: `php artisan test --compact` = 311 bestanden, 7 uebersprungen.
- [x] Verifiziert: `npm run build` = erfolgreich.
- [x] Verifiziert: `git diff --check` fuer die betroffenen Training-/Route-/Resource-/Test-/Doku-Dateien = sauber.

### Bearbeitung 2026-07-08: Trainingsanwesenheit
- [x] Web-Route `PUT /events/{event}/attendance` fuer Trainer-/Coach-Bulk-Erfassung ergaenzt.
- [x] API-Route `PUT /api/v1/events/{event}/attendance` fuer Flutter/Mobile ergaenzt.
- [x] Zentrale `EventAttendance`-Regel ergaenzt: Event-Besitzer, Team-Staff und berechtigte Clubrollen duerfen Anwesenheit erfassen.
- [x] Anwesenheit kann fuer Team-/Vereinsmitglieder als `yes`, `late`, `maybe` oder `no` gespeichert werden.
- [x] Event-API liefert `late_count`, `can_update`, `can_manage_attendance`, `response_mode` und `responded_at`.
- [x] Web-Eventdetail zeigt Verspaetet-Zaehler und Trainer-Maske fuer Kader-Anwesenheit.
- [x] SQLite-Event-Participant-Constraint auf `late` erweitert, damit Schema und `Event::PARTICIPANT_STATUSES` identisch sind.
- [x] Feature-Tests fuer Web- und Mobile-Anwesenheit ergaenzt.
- [x] Verifiziert: `php artisan test tests/Feature/MobileEventApiTest.php tests/Feature/EventParticipationWebFlowTest.php` = 5 bestanden, 53 Assertions.
- [x] Verifiziert: `npm run build` = erfolgreich.
- [x] Verifiziert: `php artisan test --compact` = 313 bestanden, 7 uebersprungen.
- [x] Verifiziert: `git diff --check` fuer die betroffenen Event-/Route-/Resource-/Vue-/Test-/Doku-Dateien = sauber.

### Bearbeitung 2026-07-08: Event erstellen/zusagen/absagen/Teilnehmerliste
- [x] API-Routen `PUT /api/v1/events/{event}`, `POST /api/v1/events/{event}/cancel` und `DELETE /api/v1/events/{event}` ergaenzt.
- [x] API-Event-Update nutzt dieselben Sichtbarkeitsregeln wie Create: public, organization, private mit Club-/Team-Pflicht.
- [x] Event-API liefert Cancel-Daten, Reminder/Teilnahme-Deadline und Rechteflags `can_update`, `can_delete`, `can_cancel`.
- [x] Mobile-Meta meldet Event-Faehigkeiten fuer Create, Update, Cancel, Delete, Participation und Attendance.
- [x] Mobile/API-Test deckt Create, Update, RSVP, Teilnehmerliste, Cancel und Delete ab.
- [x] Web/Inertia-Test deckt Event-Erstellung, Absage-RSVP, Teilnehmerliste und Event-Cancel ab.
- [x] Verifiziert: `php artisan test tests/Feature/MobileEventApiTest.php tests/Feature/EventParticipationWebFlowTest.php tests/Feature/EventIndexTest.php` = 8 bestanden, 116 Assertions.
- [x] Verifiziert: `npm run build` = erfolgreich.
- [x] Verifiziert: `php artisan test --compact` = 315 bestanden, 7 uebersprungen.
- [x] Verifiziert: `git diff --check` fuer die betroffenen Event-/Route-/Resource-/Meta-/Test-/Doku-Dateien = sauber.

### Bearbeitung 2026-07-08: Feed posten/kommentieren/liken/melden
- [x] Bestehende Web- und API-Flows fuer Feed-Posts, Kommentare, Likes/Helpful und Reports geprueft.
- [x] Mobile/API-Test um Post- und Kommentar-Reports erweitert.
- [x] Web/Inertia-Test um Post- und Kommentar-Reports erweitert.
- [x] Reports schreiben `content_reports` und `moderation_flags`.
- [x] Gemeldete Posts/Kommentare werden fuer fremde Nutzer aus Feed und Kommentarlisten ausgeblendet; Autoren sehen eigene gemeldete Posts weiter.
- [x] Verifiziert: `php artisan test tests/Feature/MobileFeedApiTest.php tests/Feature/FeedTest.php` = 21 bestanden, 140 Assertions.
- [x] Verifiziert: `npm run build` = erfolgreich.
- [x] Verifiziert: `php artisan test --compact` = 317 bestanden, 7 uebersprungen.
- [x] Verifiziert: `git diff --check` fuer die betroffenen Feed-/Report-/Test-/Doku-Dateien = sauber.

### Bearbeitung 2026-07-17: Chat 1:1 und Gruppenchat
- [x] API-Route `POST /api/v1/chat/conversations` fuer Direct-, Group- und Teamchats ergaenzt.
- [x] API nutzt Web-Regeln: Direct nur mit erlaubten DMs, Gruppen nur mit befreundeten/erlaubten Nutzern, Teamchat nur fuer Teammitglieder.
- [x] Initiale Nachrichten werden beim Erstellen gespeichert, moderiert und mit Receipts versehen.
- [x] Flutter-API-Client und Conversation-Repository koennen Direct-/Group-Konversationen erstellen.
- [x] Web-Test fuer Direct- und Gruppenchat mit erster Nachricht ergaenzt.
- [x] Mobile/API-Test fuer Direct-, Gruppen- und Teamchat-Erstellung ergaenzt.
- [x] Flutter-Test fuer `/api/v1/chat/conversations` ergaenzt.
- [x] Verifiziert: `php artisan test tests/Feature/ChatSecurityTest.php tests/Feature/MobileChatRealtimeContractTest.php` = 14 bestanden, 101 Assertions.
- [x] Verifiziert: `npm run build` = erfolgreich.
- [x] Verifiziert: `php artisan test --compact` = 319 bestanden, 7 uebersprungen.
- [x] Verifiziert: `git diff --check` = sauber.
- [x] Restpunkt dokumentiert: `flutter analyze`/`flutter test` sind in dieser Sandbox durch Snap-Cache-Schreibrechte unter `/home/amin-masri/snap/flutter/common/.cache` blockiert; die Flutter-Testdatei ist ergaenzt und muss ausserhalb der Sandbox ausgefuehrt werden.

### Bearbeitung 2026-07-17: Dateiordner und Upload-Rechte
- [x] API-Upload-Rechte gehaertet: persoenliche Uploads bleiben erlaubt, Club-/Team-/Event-Uploads brauchen `file.upload` bzw. bestehende Admin-/Coach-Regeln.
- [x] API-Ordnerrechte gehaertet: scoped Ordner erstellen braucht Upload-Recht, Umbenennen nutzt `FolderPolicy@update`, Loeschen nutzt `FolderPolicy@delete`.
- [x] API-Dateirechte gehaertet: Umbenennen nutzt `FilePolicy@update`, Loeschen nutzt `FilePolicy@delete`.
- [x] API-Upload-Intent-Regex korrigiert, damit ungueltige Datei-/Pfadnamen nicht mehr als 500er enden.
- [x] Tests fuer persoenliche Dateiordner: Besitzer darf erstellen/umbenennen/loeschen, fremde Nutzer werden blockiert.
- [x] Tests fuer scoped API-Uploads: Mitglied ohne Upload-Recht wird blockiert, Upload-Recht erlaubt Intent/Ordner/Upload/Umbenennen, Delete-Recht ist separat erforderlich.
- [x] Tests fuer Web/Inertia-Uploads: Vereinsordner und Vereinsdateien respektieren Upload-/Delete-Rechte.
- [x] Verifiziert: `php artisan test tests/Feature/FileManagerFeatureTest.php` = 9 bestanden, 115 Assertions.
- [x] Verifiziert: `php artisan test tests/Feature/MobileApiContractTest.php tests/Feature/ApiPaginationContractTest.php tests/Feature/ApiRateLimitContractTest.php` = 21 bestanden, 427 Assertions.
- [x] Verifiziert: `npm run build` = erfolgreich.
- [x] Verifiziert: `php artisan test --compact` = 322 bestanden, 7 uebersprungen.
- [x] Verifiziert: `git diff --check` = sauber.

### Bearbeitung 2026-07-17: Notification Center
- [x] Web-Notification-Payload vereinheitlicht: `title`, `body`, `url`, `action_url`, `read`, `unread` werden direkt geliefert.
- [x] API-Notification-Resource vereinheitlicht: Linkziel wird aus `action_url` oder `url` normalisiert und mit `unread` ausgeliefert.
- [x] Web-UI nutzt normalisierte Notification-Felder fuer Titel, Text, Linkziel und Loesch-Label.
- [x] Flutter-Notification-Repository liest Detailantworten korrekt aus `data`.
- [x] Flutter-Notification-Client sendet Pagination mit `page` an `/api/v1/notifications`.
- [x] Web-Test deckt Liste, gelesen, ungelesen, alle gelesen, Fremdzugriff, Loeschen und Linkziel ab.
- [x] API-Test deckt `unread_only`, Detail, gelesen, ungelesen, Fremdzugriff, Loeschen und Linkziel ab.
- [x] Flutter-Test fuer Notification-Detailmapping und Listenpagination ergaenzt.
- [x] Verifiziert: `php artisan test tests/Feature/NotificationCenterFeatureTest.php tests/Feature/MobileApiContractTest.php tests/Feature/ApiPaginationContractTest.php` = 21 bestanden, 413 Assertions.
- [x] Verifiziert: `npm run build` = erfolgreich.
- [x] Verifiziert: `php artisan test --compact` = 324 bestanden, 7 uebersprungen.
- [x] Verifiziert: `git diff --check` = sauber.
- [x] Restpunkt dokumentiert: `flutter test`/`flutter analyze` bleiben in dieser Sandbox durch den Flutter-Snap-Cache unter `/home/amin-masri/snap/flutter/common/.cache` blockiert; Dart-Formatierung wurde erfolgreich ausgefuehrt.

### Bearbeitung 2026-07-17: Eltern-/Guardian-Flow
- [x] Zustimmung per Guardian-Token verknuepft eingeloggte Erziehungsberechtigte dauerhaft ueber `guardian_user_id`.
- [x] Elternkonto-Erstellung verknuepft vorhandene Kinderprofile mit gleicher `guardian_email`.
- [x] Widerruf setzt Kinder zurueck auf `minor_pending_consent` und entfernt `minor_player`.
- [x] Erneute Zustimmung setzt `guardian_consent_at`, entfernt Widerruf/Ablehnung und aktiviert wieder `minor_player`.
- [x] In-App-Benachrichtigungen fuer Kind ergaenzt: Zustimmung erteilt, Zustimmung abgelehnt, Zustimmung widerrufen.
- [x] Guardian-Fremdzugriff auf nicht verknuepfte Kinder wird getestet und blockiert.
- [x] Tests fuer Token-Zustimmung, Elternkonto-Verknuepfung, Ablehnung, Widerruf und erneute Zustimmung ergaenzt.
- [x] Verifiziert: `php artisan test tests/Feature/GuardianAccessFlowTest.php tests/Feature/RegistrationTest.php tests/Feature/MaturityWebSessionApiTest.php` = 14 bestanden, 1 uebersprungen, 108 Assertions.
- [x] Verifiziert: `npm run build` = erfolgreich.
- [x] Verifiziert: `php artisan test --compact` = 328 bestanden, 7 uebersprungen.
- [x] Verifiziert: `git diff --check` = sauber.

### Bearbeitung 2026-07-17: Rechtstexte und Legal-Review-Paket
- [x] Oeffentliche Rechteseiten geprueft: Impressum, Datenschutz, AGB, Community-Richtlinien, Jugendschutz, Cookies, Widerruf, Kontakt/Melden.
- [x] Sitemap-Abdeckung fuer alle Legal-Seiten getestet.
- [x] Legal-Env-Felder in `.env.example` gegen `config/legal.php` geprueft.
- [x] `docs/LEGAL_REVIEW_PACK.md` erstellt: Routen, Pflichtdaten, vorhandene DPA-Unterlagen, Subprozessor-/Provider-Hinweise und juristische Pruefpunkte.
- [x] Externe anwaltliche Freigabe nicht behauptet; Sign-off-Felder im Review-Paket bleiben bewusst offen.
- [x] Verifiziert: `php artisan test tests/Feature/LegalPagesTest.php tests/Feature/PublicSeoTest.php` = 12 bestanden, 193 Assertions.
- [x] Verifiziert: `npm run build` = erfolgreich.
- [x] Verifiziert: `php artisan test --compact` = 329 bestanden, 7 uebersprungen.
- [x] Verifiziert: `git diff --check` = sauber.

### Bearbeitung 2026-07-17: DSGVO-Prozesse
- [x] Datenexport fuer Web und API ergaenzt: `GET /settings/privacy/export` und `GET /api/v1/privacy/export` liefern JSON-Schema `airmius.privacy-export.v1`.
- [x] Berichtigungsprozess fuer Web und API ergaenzt: `PATCH /settings/privacy/correction` und `PATCH /api/v1/privacy/correction` aktualisieren Profil-, Adress- und Privacy-Felder.
- [x] Einwilligungswiderruf fuer Web und API ergaenzt: `POST /settings/privacy/withdraw-consents` und `POST /api/v1/privacy/withdraw-consents` setzen Ad-Consents gezielt oder komplett zurueck.
- [x] Loeschprozess im Export und in `docs/PRIVACY_RIGHTS_PROCESS.md` dokumentiert: Web/API-Codebestaetigung plus bestehender Retention-Anonymisierungspfad.
- [x] Settings-Privacy-Tab um Datenauskunft-Download, Einwilligungswiderruf und Link zur Profildaten-Berichtigung erweitert.
- [x] Activity-Logs fuer `privacy.profile_corrected` und `privacy.consent_withdrawn` ergaenzt, ohne korrigierte Werte doppelt im Log zu speichern.
- [x] Feature-Tests fuer Export, Berichtigung, Widerruf und bestehenden Loeschprozess ergaenzt.
- [x] Verifiziert: `php artisan test tests/Feature/PrivacyRightsProcessTest.php tests/Feature/DeleteAccountTest.php` = 6 bestanden, 37 Assertions.
- [x] Verifiziert: `php artisan test tests/Feature/MobileApiContractTest.php tests/Feature/ApiPaginationContractTest.php tests/Feature/ApiRateLimitContractTest.php` = 21 bestanden, 427 Assertions.
- [x] Verifiziert: `npm run build` = erfolgreich.
- [x] Verifiziert: `php artisan test --compact` = 333 bestanden, 7 uebersprungen.
- [x] Verifiziert: `git diff --check` = sauber.

### Bearbeitung 2026-07-17: Minderjaehrigen-Konzept
- [x] Zentrale Regel `App\Support\MinorSafety` ergaenzt: Consent-Alter 16, Privacy-Defaults, Guardian-Consent-Status, Profil-Sichtbarkeit und DM-Freigabe.
- [x] Registrierung, Profilvervollstaendigung und API-Profilupdate setzen fuer Nutzer unter 16 automatisch `profile_visibility=private`, `direct_message_privacy=friends`, `friend_request_privacy=friends`.
- [x] Web-/API-Settings, Fortify-Profilupdate und DSGVO-Berichtigung koennen diese Minderjaehrigen-Defaults nicht auf `public` oder `everyone` zuruecksetzen.
- [x] Minderjaehrigen-Profile sind nicht oeffentlich sichtbar; Zugriff bleibt fuer eigenes Konto, Guardian-Verknuepfung, Freunde und Plattform-Admins erlaubt.
- [x] Direktnachrichten mit Minderjaehrigen sind ohne aktive Guardian-Freigabe blockiert; nach Freigabe nur fuer Freunde oder Guardian-Verknuepfungen erlaubt.
- [x] Web-Chat, API-Chat, Profil-CTA und Gruppenchats nutzen die zentrale `User::allowsDirectMessagesFrom()`-Regel.
- [x] `docs/MINOR_SAFETY_CONCEPT.md` erstellt: Alter, Elternfreigabe, Sichtbarkeit und Direktnachrichten dokumentiert.
- [x] Verifiziert: `php artisan test tests/Feature/MinorSafetyConceptTest.php tests/Feature/GuardianAccessFlowTest.php tests/Feature/RegistrationTest.php tests/Feature/ChatSecurityTest.php tests/Feature/MobileChatRealtimeContractTest.php` = 31 bestanden, 1 uebersprungen, 220 Assertions.
- [x] Verifiziert: `php artisan test tests/Feature/MobileApiContractTest.php tests/Feature/PrivacyRightsProcessTest.php tests/Feature/UserSportCvProfileTest.php` = 23 bestanden, 391 Assertions.
- [x] Verifiziert: `npm run build` = erfolgreich.
- [x] Verifiziert: `php artisan test --compact` = 337 bestanden, 7 uebersprungen.
- [x] Verifiziert: `git diff --check` = sauber.

### Bearbeitung 2026-07-17: DSA-Moderationsprozess
- [x] DSA-Felder fuer Reports und automatische Flags ergaenzt: `decision_reason`, `action_taken`, Appeal-Status, Appeal-Entscheidung und Review-Zeitpunkte.
- [x] `moderation_logs` angelegt und `App\Support\ModerationAuditLog` ergaenzt: Report, Entscheidung, Beschwerde und Beschwerdeentscheidung werden nachvollziehbar geloggt.
- [x] Report-Erstellung schreibt jetzt explizit `status=open` und Logaktion `reported`.
- [x] Admin-Entscheidungen fuer Reports/Flags speichern Begründung, Aktion und Logaktion `decision`.
- [x] Reporter koennen nach einer Entscheidung Beschwerde einreichen: `POST /reports/{report}/appeal` und `POST /api/v1/reports/{report}/appeal`.
- [x] Admins koennen Beschwerden entscheiden: `PUT /admin/moderation/reports/{report}/appeal`.
- [x] Admin-Moderationspayload liefert DSA-Felder und die letzten Logs pro Report/Flag.
- [x] `docs/DSA_MODERATION_PROCESS.md` erstellt: Melden, Entscheidung, Beschwerde, Beschwerdeentscheidung und Log dokumentiert.
- [x] Verifiziert: `php artisan test tests/Feature/ModerationDsaProcessTest.php tests/Feature/FeedTest.php tests/Feature/MobileFeedApiTest.php` = 23 bestanden, 178 Assertions.
- [x] Verifiziert: `php artisan test tests/Feature/MobileApiContractTest.php tests/Feature/ApiRateLimitContractTest.php tests/Feature/MemberIndexFeatureTest.php` = 21 bestanden, 401 Assertions.
- [x] Verifiziert: `npm run build` = erfolgreich.
- [x] Verifiziert: `php artisan test --compact` = 339 bestanden, 7 uebersprungen.
- [x] Verifiziert: `git diff --check` = sauber.
- [x] Nebenbei stabilisiert: `tests/Feature/GlobalSearchTest.php` erwartet jetzt mindestens die drei Vertrags-Treffer statt exakt drei zufallsabhaengige Treffer.

### Bearbeitung 2026-07-17: Cookie-/Tracking-Consent
- [x] Inertia-Payload liefert `privacyConsent` global aus: Gaeste erhalten Ads-Personalisierung und Ads-Messung standardmaessig `false`, eingeloggte Nutzer erhalten ihre gespeicherten User-Consents.
- [x] `auth.user` enthaelt `ads_personalization_consent` und `ads_measurement_consent`, damit Webscreens denselben Consent-Stand nutzen.
- [x] `resources/js/services/privacyConsent.js` ergaenzt: `canTrackMarketingEvent()` erlaubt clientseitige Marketing-/Conversion-Events nur bei aktiver Mess-Einwilligung.
- [x] Landingpage-Events an `dataLayer` oder `gtag` werden blockiert, solange `ads_measurement_consent` nicht aktiv ist.
- [x] App-Shell geprueft: keine externen Analytics-, Marketing-, Retargeting- oder Pixel-Skripte im Weblayout eingebunden.
- [x] Cookie-Seite aktualisiert: notwendige lokale Speicherwerte benannt, aktuelle Nicht-Einbindung externer Trackinganbieter dokumentiert und Consent-Grenze fuer dataLayer/gtag erklaert.
- [x] `docs/COOKIE_TRACKING_CONSENT.md` erstellt: aktueller Stand, technische Grenze und Pruefpunkte vor neuen Tags.
- [x] Verifiziert: `php artisan test tests/Feature/CookieTrackingConsentTest.php` = 4 bestanden, 62 Assertions.
- [x] Verifiziert: `php artisan test tests/Feature/LegalPagesTest.php tests/Feature/PrivacyRightsProcessTest.php` = 6 bestanden, 156 Assertions.
- [x] Verifiziert: `npm run build` = erfolgreich.
- [x] Verifiziert: `php artisan test --compact` = 343 bestanden, 7 uebersprungen.
- [x] Verifiziert: `git diff --check` = sauber.

### Bearbeitung 2026-07-17: Subprozessorenliste
- [x] `docs/DATA_PROCESSING_PROVIDERS.md` zur AVV-/DPA- und Subprozessorenliste erweitert.
- [x] MVP-Status nach Kategorien ergaenzt: Hosting, Storage/CDN/Backups, Mail, Payments, Analytics/Tracking, Push, Realtime, Karten/Routing, KI, Social/OAuth/Fitness und Monitoring.
- [x] Hostinger und Cloudflare als aktiv vorgesehene Anbieter mit vorhandenen DPA-Nachweisen dokumentiert.
- [x] Mail dokumentiert: SMTP/Log und optionale SES/Postmark/Resend-Konfiguration, konkreter Produktiv-SMTP-Anbieter muss vor Livegang eingetragen werden.
- [x] Payments dokumentiert: Stripe, PayPal und Bankueberweisung inklusive Live-Modus-/Webhook-Pruefgrenze.
- [x] Analytics dokumentiert: aktuell keine externen Analytics-/Marketing-Tags aktiv, neue Anbieter nur mit Opt-in, Cookie-Seite und DPA.
- [x] Push dokumentiert: FCM/APNS vorbereitet, externer Versand noch nicht aktiv; Opt-in/Opt-out und Loeschung bei Logout bleiben Pruefpunkt vor echter Auslieferung.
- [x] `tests/Feature/SubprocessorDocumentationTest.php` ergaenzt: Mindestabdeckung und Abgleich kritischer `.env.example`-Schalter werden geprueft.
- [x] Verifiziert: `php artisan test tests/Feature/SubprocessorDocumentationTest.php` = 2 bestanden, 40 Assertions.
- [x] Verifiziert: `php artisan test tests/Feature/LegalPagesTest.php tests/Feature/CookieTrackingConsentTest.php tests/Feature/PrivacyRightsProcessTest.php` = 10 bestanden, 218 Assertions.
- [x] Verifiziert: `npm run build` = erfolgreich.
- [x] Verifiziert: `php artisan test --compact` = 345 bestanden, 7 uebersprungen.
- [x] Verifiziert: `git diff --check` = sauber.

### Bearbeitung 2026-07-17: Backup-/Restore-Konzept
- [x] `config/airmius_backup.php` ergaenzt: `BACKUP_DISK`, `BACKUP_PATH` und `BACKUP_RETENTION_DAYS` zentral konfigurierbar.
- [x] `.env.example` um `BACKUP_PATH=backups/database` erweitert.
- [x] `php artisan airmius:backup-database` ergaenzt: erstellt SQLite-Backup auf konfigurierter Disk und schreibt Manifest `airmius.database-backup.v1` mit SHA-256, Groesse, Quelle und Retention.
- [x] `php artisan airmius:restore-database` ergaenzt: restored Backup nur in explizit gesetztes absolutes Ziel und verifiziert Manifest-Hash, wenn vorhanden.
- [x] Live-Restore-Schutz ergaenzt: ohne `--target` bricht Restore ab; vorhandene Zieldatei wird nur mit `--force` ueberschrieben.
- [x] `docs/BACKUP_RESTORE_RUNBOOK.md` erstellt: lokaler Restore-Test, Produktivregeln, R2-Konfiguration, Retention und MySQL/MariaDB-Restpunkt dokumentiert.
- [x] Restore einmal technisch durchgefuehrt: `tests/Feature/DatabaseBackupRestoreTest.php` erstellt echte SQLite-Quelldatenbank, sichert sie, restored in separate SQLite-Datei und liest den Testdatensatz.
- [x] Verifiziert: `php artisan test tests/Feature/DatabaseBackupRestoreTest.php` = 2 bestanden, 7 Assertions.
- [x] Verifiziert: `php artisan test tests/Feature/SubprocessorDocumentationTest.php tests/Feature/CookieTrackingConsentTest.php` = 6 bestanden, 102 Assertions.
- [x] Verifiziert: `npm run build` = erfolgreich.
- [x] Verifiziert: `php artisan test --compact` = 347 bestanden, 7 uebersprungen.
- [x] Verifiziert: `git diff --check` = sauber.

### Bearbeitung 2026-07-17: Security Headers
- [x] `App\Http\Middleware\ApplySecurityHeaders` global registriert.
- [x] `Content-Security-Policy` aktiviert mit `default-src 'self'`, `object-src 'none'`, `frame-ancestors 'none'`, eingeschraenkten Quellen fuer Fonts/Images/Connect und Websocket-Support.
- [x] `X-Frame-Options: DENY`, `Referrer-Policy: strict-origin-when-cross-origin`, `X-Content-Type-Options: nosniff`, `X-Permitted-Cross-Domain-Policies: none` und `Permissions-Policy` aktiviert.
- [x] HSTS aktiviert fuer HTTPS- bzw. `X-Forwarded-Proto: https`-Requests: `max-age=31536000; includeSubDomains; preload`.
- [x] Security-Header konfigurierbar gemacht: `SECURITY_HEADERS_ENABLED`, `SECURITY_HSTS_*`, `SECURITY_CSP_ENABLED`.
- [x] `docs/SECURITY_REVIEW.md` aktualisiert: Header sind umgesetzt; Restpunkt ist HTTPS-Erzwingung und CSP-Verengung nach neuen Integrationen.
- [x] Verifiziert: `php artisan test tests/Feature/SecurityHeadersTest.php` = 3 bestanden, 26 Assertions.
- [x] Verifiziert: `php artisan test tests/Feature/MobileApiContractTest.php tests/Feature/LegalPagesTest.php tests/Feature/PublicSeoTest.php tests/Feature/CookieTrackingConsentTest.php` = 33 bestanden, 538 Assertions.
- [x] Verifiziert: `npm run build` = erfolgreich.
- [x] Verifiziert: `php artisan test --compact` = 350 bestanden, 7 uebersprungen.
- [x] Verifiziert: `git diff --check` = sauber.

### Bearbeitung 2026-07-17: Admin-2FA-Pflicht
- [x] `App\Support\AdminTwoFactor` ergaenzt: Plattform-Adminrollen `super_admin`, `admin`, `system_admin` benoetigen `two_factor_secret` und `two_factor_confirmed_at`.
- [x] Web-Adminbereich ueber `HardenAdminArea` gehaertet: Plattform-Admins ohne bestaetigte 2FA werden zu `/settings?tab=security` umgeleitet und erhalten eine klare Fehlermeldung.
- [x] Mobile/API-Admin-Commerce-Routen mit `EnsurePlatformAdminTwoFactor` geschuetzt; fehlende 2FA liefert `403` mit Code `admin_two_factor_required`.
- [x] `User` castet `two_factor_confirmed_at` als Datetime.
- [x] MailCenter-Super-Admin-Testaccount auf bestaetigte 2FA aktualisiert.
- [x] `docs/SECURITY_REVIEW.md` aktualisiert: Admin-2FA-Pflicht und Recovery-Code-Hinweis dokumentiert.
- [x] Verifiziert: `php artisan test tests/Feature/AdminAreaSecurityTest.php` = 5 bestanden, 12 Assertions.
- [x] Verifiziert: `php artisan test tests/Feature/AdminAreaSecurityTest.php tests/Feature/MobileApiContractTest.php tests/Feature/MailCenterControllerTest.php tests/Feature/ModerationDsaProcessTest.php tests/Feature/UserSubscriptionAccountManagementTest.php` = 38 bestanden, 405 Assertions.
- [x] Verifiziert: `npm run build` = erfolgreich.
- [x] Verifiziert: `php artisan test --compact` = 353 bestanden, 7 uebersprungen.
- [x] Verifiziert: `git diff --check` = sauber.

### Bearbeitung 2026-07-17: Operations Monitoring
- [x] `config/airmius_monitoring.php` ergaenzt: Schwellenwerte fuer Fehlerlogs, Queue, stale Jobs, Webhook-Secrets und fehlgeschlagene Mailzustellungen.
- [x] `App\Support\OperationsMonitor` ergaenzt: prueft Laravel-Fehlerlogs, Datenbankverbindung, Queue-Tabellen, failed/stale Jobs, kritische Artisan-Commands, Webhook-Routen und Mailer-Konfiguration.
- [x] `php artisan airmius:monitor-operations` ergaenzt mit Tabellen-Ausgabe, JSON-Modus und Exit-Code `1` bei harten Betriebsfehlern.
- [x] DB-Ausfall wird kontrolliert als Monitoring-Fehler gemeldet; der Command bricht nicht mehr mit ungefangener QueryException ab.
- [x] Scheduler aktiviert: `airmius:monitor-operations` laeuft stuendlich mit `withoutOverlapping`.
- [x] `.env.example` um `OPERATIONS_*`-Schalter und `ERROR_MONITORING_DSN`-Kontext erweitert.
- [x] `docs/OPERATIONS_MONITORING_RUNBOOK.md` erstellt: Command, Scheduler/Cron, gepruefte Bereiche, Livegang-Regeln und Reaktion auf Alarme.
- [x] Verifiziert: `php artisan test tests/Feature/OperationsMonitoringTest.php` = 2 bestanden, 9 Assertions.
- [x] Verifiziert: `php artisan test tests/Feature/OperationsMonitoringTest.php tests/Feature/MailCenterControllerTest.php tests/Feature/AdminAreaSecurityTest.php tests/Feature/MobileApiContractTest.php tests/Feature/SecurityHeadersTest.php` = 32 bestanden, 379 Assertions.
- [x] Verifiziert: `php artisan airmius:monitor-operations --hours=1 --json` liefert kontrollierte Fehler fuer den lokalen Zustand: MySQL nicht erreichbar, aktuelle Log-Errors, Webhook-Secrets fehlen.
- [x] Verifiziert: `npm run build` = erfolgreich.
- [x] Verifiziert: `php artisan test --compact` = 355 bestanden, 7 uebersprungen.
- [x] Verifiziert: `git diff --check` = sauber.

### Bearbeitung 2026-07-17: Payment-Webhook-Signaturen
- [x] `App\Support\PaymentWebhookVerifier` ergaenzt: zentrale Pruefung fuer Stripe-HMAC und PayPal-Provider-Signaturverifikation.
- [x] Stripe-Webhooks fuer Abo und Commerce verlangen jetzt `STRIPE_WEBHOOK_SECRET`, `Stripe-Signature`, gueltige HMAC-SHA256-Signatur und konfigurierbares Toleranzfenster.
- [x] `.env.example` und `config/services.php` um `STRIPE_WEBHOOK_TOLERANCE_SECONDS` erweitert.
- [x] PayPal-Webhooks fuer Abo, Commerce und Outfit verlangen jetzt Webhook-ID, PayPal-Credentials, Pflicht-Header und erfolgreiche Provider-Verifikation ueber `/v1/notifications/verify-webhook-signature`.
- [x] Alte Sandbox-Ausnahme fuer Outfit-PayPal ohne Webhook-ID entfernt; Tests simulieren jetzt echte PayPal-Verifikation per `Http::fake`.
- [x] `docs/SECURITY_REVIEW.md` aktualisiert: Signaturpruefung ist umgesetzt; vor Livegang bleiben echte Provider-Sandbox-Testevents mit echten Secrets/Webhook-IDs.
- [x] Verifiziert: `php artisan test tests/Feature/PaymentWebhookSignatureTest.php` = 5 bestanden, 15 Assertions.
- [x] Verifiziert: `php artisan test tests/Feature/OutfitSubscriptionModuleTest.php` = 20 bestanden, 69 Assertions.
- [x] Verifiziert: `php artisan test tests/Feature/PaymentWebhookSignatureTest.php tests/Feature/OutfitSubscriptionModuleTest.php tests/Feature/MobileApiContractTest.php tests/Feature/UserSubscriptionAccountManagementTest.php tests/Feature/AdminSubscriptionManagementTest.php` = 53 bestanden, 423 Assertions.
- [x] Verifiziert: `npm run build` = erfolgreich.
- [x] Verifiziert: `php artisan test --compact` = 360 bestanden, 7 uebersprungen.
- [x] Verifiziert: `git diff --check` = sauber.

### Bearbeitung 2026-07-17: Store-Readiness
- [x] Vorhandene Mobile-Release-Struktur geprueft: Store-Listings, Privacy-Drafts, Screenshot-Plan, Review-Account-Runbook, Release Notes, Submission-Runbook und Release-Manifest existieren unter `mobile/airmius_mobile/store_listing`.
- [x] App-IDs und Version dokumentiert: Android `com.airmius.app`, iOS `com.airmius.app`, Flutter `1.0.33+77`.
- [x] App-Icons technisch geprueft: Web/PWA 192/512, Android Launcher `xxxhdpi` 192x192, iOS 1024x1024.
- [x] Support- und Datenschutzkontakte fuer Store-Formulare geprueft: `support@airmius.com`, `datenschutz@airmius.com`, `MAIL_SUPPORT_FROM_ADDRESS`.
- [x] `docs/STORE_READINESS_MVP.md` erstellt: Status, vorhandene Artefakte, Kontakte, finale No-Go-Punkte und technischer Pruefbefehl.
- [x] `tests/Feature/StoreReadinessDocumentationTest.php` ergaenzt: prueft Artefakte, Release-Manifest-Gates, Icon-Dimensionen, Kontakte und nicht-leere Store-/Privacy-Texte.
- [x] Verifiziert: `php artisan test tests/Feature/StoreReadinessDocumentationTest.php` = 5 bestanden, 88 Assertions.
- [x] Verifiziert: `php artisan test tests/Feature/StoreReadinessDocumentationTest.php tests/Feature/OperationsMonitoringTest.php tests/Feature/PaymentWebhookSignatureTest.php tests/Feature/LegalPagesTest.php tests/Feature/SubprocessorDocumentationTest.php` = 16 bestanden, 273 Assertions.
- [x] Verifiziert: `npm run build` = erfolgreich.
- [x] Verifiziert: `php artisan test --compact` = 365 bestanden, 7 uebersprungen.
- [x] Verifiziert: `git diff --check` = sauber.
- [x] Nicht behauptet: finale Store-Einreichung. Ausstehend bleiben signierte Builds, echte Screenshots, Store-Console-Eintraege, Review-Account in den Konsolen und juristische Freigaben.

### Bearbeitung 2026-08-09: Mi Fitness, Sport-App-Import und Gastseiten-Regression

- [x] Gemeinsame Provider-Registry fuer Web und Mobile eingefuehrt; Mi Fitness als normalisierte Health-Connect-/Dateibruecke in DE/EN/FR/AR und RTL ergaenzt.
- [x] Direkte Sync-Aktionen auf Google Fit und Strava begrenzt; Apple Health, Garmin und Mi Fitness zeigen ihren tatsaechlichen Bridge-/Partnerstatus.
- [x] Gesundheitszusammenfassungen auf elf validierte Zahlenfelder begrenzt; unbekannte Felder und redundante Provider-IDs werden nicht in Aktivitaetsmetriken gespeichert.
- [x] Private Routen und fremde Teams koennen nicht ueber IDs an importierte Tracks gebunden werden.
- [x] Additive eindeutige `source_activity_id` verhindert Track-Dubletten bei wiederholtem oder umbenanntem Providerimport und uebernimmt vorhandene externe IDs migrationssicher.
- [x] Verifiziert: gezielte Sportintegrations-/Lokalisierungstests = 6 bestanden, 399 Assertions.
- [x] Verifiziert: fokussierte Gastseiten-/SEO-/AJAX-Suite = 21 bestanden, 931 Assertions.
- [x] Verifiziert: `php artisan test` = 922 bestanden, 4 bewusst uebersprungen, 24.132 Assertions.
- [x] Verifiziert: `flutter test` = 251 bestanden; gezielte Analyse der drei geaenderten Sportintegrationsdateien = keine Befunde.
- [x] Verifiziert: `npm run build` = 1.043 Module, 180 Manifest-Eintraege; arabischer Kern 310,99 KB unter dem festen 311-KB-Budget.
- [x] Verifiziert: Repository-Preflight = 10 bestanden, 13 externe Evidenzgates ausstehend, 0 Fehler, 206 Artefakte.
- [ ] Extern: Garmin-Partnerfreigabe sowie echte Health-Connect-/Google-Fit-OAuth-Smokes und Cross-Device-Abnahme auf Android/iOS.

### Bearbeitung 2026-08-09: Gast-Pricing und Abo-Checkout

- [x] Zustandsaendernden GET-Checkout entfernt; Gast-Pricing und authentifizierter Commerce-Arbeitsraum starten Stripe, PayPal und Ueberweisung ausschliesslich per POST/AJAX.
- [x] `Idempotency-Key` und `payment-actions`-Rate-Limit verhindern doppelte Checkouts bei Wiederholung, Mehrfachklick oder unsicherer Netzantwort.
- [x] Nur aktive und oeffentliche Plaene sind kaufbar; explizite fremde Vereins-IDs werden abgewiesen und nicht mehr still durch einen eigenen Verein ersetzt.
- [x] Die Gast-Preis-Seite liefert nur bis zu 50 verwaltbare Vereine und erkennt auch aktive Vereinsabos korrekt als bereits vorhanden.
- [x] Checkout und Rechnung entstehen atomar; Fehler von Stripe oder PayPal setzen Checkout auf `failed` und Rechnung auf `cancelled` statt offene Scheinvorgaenge zu hinterlassen.
- [x] Provider-Abbruchlinks sind zeitlich begrenzt signiert, eigentuemergebunden und stornieren Checkout sowie Rechnung gemeinsam.
- [x] Checkout-Auswahl, Fehler- und Leerzustaende sind in DE/EN/FR/AR inklusive RTL ohne zusaetzliche Kernkatalog-Last vorhanden; Login erhaelt Zielgruppe und Ruecksprung zur Preis-Seite.
- [x] Provider-Fehlerlogs enthalten Status und interne Checkout-ID, aber keine rohen Providerantworten.
- [x] Verifiziert: neue Checkout-Sicherheitssuite = 8 bestanden; gemeinsam mit Mobile-API = 26 bestanden, 364 Assertions.
- [x] Verifiziert: erweiterte Gastseiten-/SEO-/Legal-/WCAG-/Checkout-Suite = 45 bestanden, 5.755 Assertions.
- [x] Verifiziert: `php artisan test --compact` = 930 bestanden, 4 bewusst uebersprungen, 24.191 Assertions.
- [x] Verifiziert: `npm run build` = 1.043 Module; `git diff --check` und Pint sind sauber.
- [ ] Extern: echte Stripe-Testkonto-/PayPal-Sandbox-Reise inklusive Rueckkehr, signiertem Webhook, Refund und Reconciliation evidenzgebunden abnehmen.

### Bearbeitung 2026-08-09: Marketplace- und Outfit-Zahlungsabbruch

- [x] Marketplace-Abbruchlinks fuer angemeldete und Gast-Bestellungen sowie Outfit-PayPal-Abbruchlinks sind 24 Stunden gueltig signiert und mit `payment-actions` begrenzt; unsignierte Aufrufe liefern `403`.
- [x] Commerce-Abbruch ist eigentuemer- beziehungsweise tokengebunden, transaktional und nur im Status `pending` erlaubt; abgeschlossene oder bezahlte Bestellungen koennen nicht mehr durch eine alte Rueckkehr-URL storniert werden.
- [x] Outfit-Abbruch ist eigentuemergebunden, transaktional und nur fuer `pending_payment`/`pending` erlaubt; aktive und bezahlte Abos bleiben unveraendert.
- [x] Stripe-/PayPal-Startfehler setzen lokale Marketplace-Bestellungen und Outfit-Zahlungen unter Datenbanksperre auf `failed`, entfernen veraltete Checkout-URLs und speichern nur einen internen Fehlerzeitpunkt.
- [x] Provider-Fehlerlogs enthalten interne IDs und HTTP-Status, aber keine rohen Antwortkoerper; Checkout-, Abbruch- und Fehlertexte sind in DE/EN/FR/AR vorhanden.
- [x] Private Gast-Bestellseiten behalten `no-store`, `noindex` und `no-referrer`; der bestehende Gastseiten-Vertrag verwendet fuer Abbruch jetzt die echte signierte Provider-URL.
- [x] Verifiziert: neue Zahlungsabbruch-Sicherheitssuite = 8 bestanden; fokussierte Commerce-/Outfit-/Gastregression = 65 bestanden.
- [x] Verifiziert: `php artisan test --compact` = 938 bestanden, 4 bewusst uebersprungen, 24.332 Assertions.
- [x] Verifiziert: `npm run build` = 1.043 Module, 180 Manifest-Eintraege; arabischer Kern 310.986 Bytes unter dem 311-KB-Budget.
- [x] Verifiziert: Repository-Preflight = 10 bestanden, 13 externe Evidenzgates ausstehend, 0 Fehler; technischer Stand gruen, Release bleibt korrekt `no_go`.

### Bearbeitung 2026-08-09: Einheitliche Checkout-Idempotenz und atomarer Warenkorb

- [x] Konto-Abo, Commerce-Add-on, Marketplace-Einzelkauf, Warenkorb, Outfit-Abo und Gast-Marketplace verwenden denselben kleinen JSON-/AJAX-Vertrag mit `Idempotency-Key` und `payment-actions`-Rate-Limit.
- [x] Idempotenz-Scope bindet HTTP-Methode, benannte Route und gehashte konkrete Route inklusive Produkt-/Planparameter; derselbe Client-Schluessel kann dadurch niemals die Antwort eines anderen Produkts oder Plans wiedergeben.
- [x] Angemeldete Nutzer werden ueber ihre interne ID, Gaeste ueber eine gehashte Browser-Session und nur ohne Session ueber einen gehashten Netzwerk-Fallback getrennt. Gespeichert werden weder Session-ID, IP-Adresse noch Request-Inhalt.
- [x] Erfassung, Replay, Ablauf und Sperruebernahme des Idempotenzdatensatzes sind transaktional; parallele Requests liefern einen kontrollierten `409`-Verarbeitungsstatus oder dieselbe gespeicherte JSON-Antwort.
- [x] Warenkorbpruefung, Produkt-/Bestandssperren, Preisermittlung, Bestellung, Positionen und Leeren des Warenkorbs laufen atomar. Ein Netzretry kann nach dem Leeren keine zweite Bestellung erzeugen.
- [x] Einzelbestellungen und Bestellpositionen entstehen ebenfalls gemeinsam; direkte authentifizierte Produkt-URLs sperren nicht freigegebene Marketplace-Produkte jetzt wie die Gastseiten.
- [x] Gemeinsamer Frontend-Helfer steuert Request-ID, JSON-Checkout, Redirect, Feldfehler und DE/EN/FR/AR-Fallbacks. Unsichere Netzantworten behalten dieselbe ID; eindeutige Serverantworten rotieren sie. Verarbeitungszustand und Doppelklickschutz sind auf Gast-, Commerce-, Warenkorb- und Outfit-Oberflaechen sichtbar.
- [x] Verifiziert: neue Idempotenz-Sicherheitssuite = 6 bestanden, 44 Assertions; fokussierte Checkout-/Marketplace-/Outfit-/Gast-/Locale-Regression = 93 bestanden, 2.609 Assertions.
- [x] Verifiziert: `php artisan test --compact` = 944 bestanden, 4 bewusst uebersprungen, 24.404 Assertions.
- [x] Verifiziert: `npm run build` = 1.044 Module, 181 Manifest-Eintraege; gemeinsamer Checkout-Chunk 1,97 KB, arabischer Kern unveraendert 310.986 Bytes unter dem 311-KB-Budget.
- [x] Verifiziert: Pint, PHP-Lint und `git diff --check` sind sauber; Repository-Preflight = 10 bestanden, 13 externe Evidenzgates ausstehend, 0 Fehler und korrektes `no_go`.
- [ ] Extern: echte Stripe-/PayPal-/Webhook-/Refund-/Reconciliation-Reisen auf Staging pruefen; diese Release-Evidenz kann nicht durch Repositorytests ersetzt werden.

### Bearbeitung 2026-08-09: Kritische Checkout-Lokalisierung und barrierearme Dialoge

- [x] Der eingeloggte Commerce-Checkout liefert alle kaufentscheidenden Texte sofort aus einem kleinen, seitenlokalen DE/EN/FR/AR-Katalog. Er ist damit weder vom spaeter geladenen globalen Auto-Woerterbuch abhaengig noch zeigt er beim Einstieg kurz deutsche Fallbacktexte.
- [x] Bestellbestaetigung und Warenkorb-Checkout verwenden die gemeinsame native `Modal`-/`dialog`-Basis mit zugänglichem Titel, Escape, Fokusfalle, Fokuswiederherstellung, Scroll-Sperre und lokalisiertem Schliess-Label. Das Schliessen bleibt waehrend einer laufenden Zahlungsanfrage gesperrt.
- [x] Der Outfit-Abo-Checkout nutzt dieselbe Dialogbasis; Zustimmungstexte kommen direkt aus dem Kernkatalog. Der Gast-Marketplace uebersetzt Versand-Fallback und Provider-Verantwortung ohne optionale Laufzeituebersetzung.
- [x] Land- und Kundentyp-Auswahl im Warenkorb besitzen jetzt programmatische DE/EN/FR/AR-Beschriftungen; RTL bleibt Bestandteil des vorhandenen Shell-Vertrags.
- [x] Ressourcengrenze eingehalten: die neuen Checkouttexte liegen nur im lazy Commerce-Seitenchunk. Der renderkritische arabische Kern bleibt exakt 310.986 Bytes und damit unter dem 311-KB-Budget.
- [x] Verifiziert: neue Checkout-Experience-Vertraege = 3 Tests; fokussierte Checkout-/Locale-/WCAG-/Gastregression = 39 bestanden, 1.553 Assertions.
- [x] Verifiziert: `php artisan test --compact` = 947 bestanden, 4 bewusst uebersprungen, 24.580 Assertions.
- [x] Verifiziert: `npm run build` = 1.045 Module, 181 Manifest-Eintraege; Pint, JSON-Pruefung und `git diff --check` sind sauber.
- [x] Verifiziert: Repository-Preflight = 10 bestanden, 13 externe Evidenzgates ausstehend, 0 Fehler; automatisierte Basis gruen, Release korrekt `no_go`.
- [ ] Extern: Checkout und Dialoge mit Tastatur, Screenreader, Zoom/Reflow und echten Stripe-/PayPal-Sandbox-Reisen in allen vier Sprachen abnehmen.

### Bearbeitung 2026-08-09: Mehrsprachiger, stateless Blog-RSS- und Gast-Discovery-Vertrag

- [x] Der oeffentliche RSS-Feed ist aus der Routenclosure in einen kleinen dedizierten Controller verschoben und liefert Titel, Beschreibung, Sprachcode, Self-Link und Artikelziele passend zu DE/EN/FR/AR aus.
- [x] Sprachfassungen besitzen getrennte Servercaches und ETags. Gegenseitige Atom-Alternativen verbinden alle vier Feeds; stabile sprachneutrale GUIDs verhindern Dubletten beim Sprachwechsel.
- [x] RSS, Sitemap und robots.txt laufen ohne Web-Session, Fehler-Share und CSRF-Cookie. Damit bleiben die oeffentlich cachebaren Maschinenendpunkte wirklich zustandslos und erzeugen keine unnoetigen Gastkennungen.
- [x] Der Feed ist auf 30 aktuelle Beitraege begrenzt, laedt nur benoetigte Spalten und zwei feste Relationen, liefert `Last-Modified`/304 und entfernt ungueltige XML-Steuerzeichen. Autorennamen verwenden den gueltigen `dc:creator`-Vertrag statt des fuer E-Mail-Adressen gedachten RSS-`author`-Elements.
- [x] Blogliste und Artikelseite bewerben im initialen HTML und nach Clientnavigation automatisch den zur aktiven Sprache passenden RSS-Feed. Kategorien-SEO bleibt auch nach Hydration direkt lokalisiert und ist nicht vom Auto-Woerterbuch abhaengig.
- [x] Verifiziert: neue RSS-/Discovery-Suite = 4 bestanden, 115 Assertions; fokussierte Gast-/SEO-/Locale-/Security-/Delivery-Suite = 46 bestanden, 854 Assertions.
- [x] Verifiziert: `php artisan test --compact` = 951 bestanden, 4 bewusst uebersprungen, 24.702 Assertions.
- [x] Verifiziert: `npm run build` = 1.045 Module, 181 Manifest-Eintraege; kein neuer Browser-Chunk, arabischer Kern unveraendert 310.986 Bytes.
- [x] Verifiziert: Pint und `git diff --check` sauber; Repository-Preflight = 10 bestanden, 13 externe Evidenzgates ausstehend, 0 Fehler und korrektes `no_go`.
- [ ] Extern: lokalisierte Feed-Erkennung und Darstellung mit realen Feed-Readern/Crawlern auf release-identischem Staging stichprobenartig abnehmen.

### Bearbeitung 2026-08-09: Mehrsprachige, ressourcenschonende PWA-Installation

- [x] Das Web-App-Manifest ist aus der fest deutschen Routenclosure in einen kleinen dedizierten Controller verschoben und liefert Name, Beschreibung, Sprache und Schreibrichtung direkt in DE/EN/FR/AR aus.
- [x] `start_url` bewahrt die aktiv gewaehlte Sprache, waehrend `id` und `scope` stabil bleiben. Drei lokalisierte Schnellaktionen fuehren Gaeste direkt zu Vereinen, Marketplace und E-Learning.
- [x] Die bisher erzwungene Hochformatausrichtung ist entfernt; `orientation: any`, Standalone-/Desktop-Fallbacks und Navigation in einer vorhandenen App-Instanz unterstuetzen Smartphone, Tablet und Desktop.
- [x] Das Manifest besitzt pro Sprache getrennten 24-Stunden-Servercache und ETag, `stale-while-revalidate`, 304-Antworten und den bestehenden `Vary`-/RTL-Vertrag. Session-, Fehler-Share- und CSRF-Middleware sind fuer diesen oeffentlichen Maschinenendpunkt entfernt; es entsteht kein Gast-Cookie.
- [x] Die falsch als 192/512 Pixel deklarierten 664×569-Quelldateien sind durch deterministisch aus dem unveraenderten Airmius-Mark erzeugte 180-, 192- und 512-Pixel-Icons ersetzt. Transparente `any`- und dunkel hinterlegte `maskable`-Varianten besitzen echte quadratische Abmessungen; die Marke liegt innerhalb der 40-Prozent-Sicherheitszone.
- [x] Das reproduzierbare GD-Skript `scripts/build_pwa_icons.php` erzeugt alle Installationsassets ohne externe Abhaengigkeit oder generative Markenabweichung.
- [x] Verifiziert: neue Manifest-/Icon-Suite = 4 bestanden, 187 Assertions; fokussierte Gast-/Manifest-/RSS-/SEO-/Security-Suite = 34 bestanden, 931 Assertions.
- [x] Verifiziert: `php artisan test --compact` = 955 bestanden, 4 bewusst uebersprungen, 24.889 Assertions.
- [x] Verifiziert: `npm run build` = 1.045 Module, 181 Manifest-Eintraege; kein zusaetzlicher Browser-Chunk, arabischer Kern unveraendert 310.986 Bytes.
- [x] Verifiziert: Pint und `git diff --check` sauber; Repository-Preflight = 10 bestanden, 13 externe Evidenzgates ausstehend, 0 Fehler und korrektes `no_go`.
- [ ] Extern: Installation, Maskable-Cropping, Shortcut-Start und Sprachpersistenz auf Chrome/Edge/Android sowie Safari/iOS gegen release-identisches Staging abnehmen.

### Bearbeitung 2026-08-09: Echte Blog-Inhaltssprachen und Übersetzungsvarianten

- [x] Blogbeiträge und ihre Revisionen besitzen jetzt eine validierte Inhaltssprache (`de`, `en`, `fr`, `ar`) sowie eine migrationssicher befüllte Übersetzungsgruppe.
- [x] Eine eindeutige Datenbankregel und lokalisierte Konfliktbehandlung verhindern auch bei parallelen Schreibvorgängen doppelte Sprachvarianten innerhalb derselben Gruppe.
- [x] Der Web-Editor zeigt Sprache und Übersetzungsabdeckung, filtert sprachbezogen und legt fehlende Varianten gezielt als leeren Entwurf an; Titel und Inhalt werden nicht irreführend als Übersetzung kopiert. Arabische Inhalte schalten die Schreibrichtung automatisch auf RTL.
- [x] Blog-Gastliste, Kategorienzählung, verwandte Beiträge, RSS und öffentliche API bevorzugen die exakte Sprache. Nur wenn sie nicht veröffentlicht vorliegt, wird einmalig die deutsche Fassung mit sichtbarem Sprachhinweis ausgeliefert.
- [x] Artikel besitzen inhaltsbezogenes `lang`/`dir`, Sprachwechsel und tatsächliche Canonical-/Hreflang-Ziele. Ein alter Sprachlink leitet mit `301` auf die veröffentlichte passende Variante; Sitemap und RSS behaupten keine nicht vorhandenen Übersetzungen mehr.
- [x] Web- und Mobile-Redaktion verwenden denselben zentralen Übersetzungsvertrag; Übersetzungsabdeckung wird gebündelt geladen und erzeugt keine Abfrage pro Beitrag.
- [x] Ressourcengrenze eingehalten: vollständige DE/EN/FR/AR-Texte liegen in einem 2.697-Byte großen lazy Blog-Chunk; der arabische Kern bleibt unverändert bei 310.986 Bytes.
- [x] Verifiziert: neue Blog-Übersetzungssuite = 8 bestanden, 117 Assertions; fokussierte Blog-/SEO-/API-/Lokalisierungsregression = 51 bestanden, 2.394 Assertions.
- [x] Verifiziert: `php artisan test --compact` = 963 bestanden, 4 bewusst übersprungen, 25.018 Assertions.
- [x] Verifiziert: `npm run build` = 1.046 Module, 182 Manifest-Einträge; Pint ist sauber.
- [x] Verifiziert: Repository-Preflight = 10 bestanden, 13 externe Evidenzgates ausstehend, 0 Fehler und korrektes `no_go`.
- [ ] Extern: redaktionelle Qualität echter DE/EN/FR/AR-Artikel, arabische Typografie und Suchmaschinen-/Feed-Reader-Erkennung auf release-identischem Staging fachlich abnehmen.

### Bearbeitung 2026-08-09: Echte E-Learning-Kurssprachen und sichere Übersetzungsfamilien

- [x] Kurse besitzen eine migrationssicher befüllte Übersetzungsgruppe; `language` ist auf DE/EN/FR/AR normalisiert und pro Kursfamilie datenbankseitig eindeutig.
- [x] Web- und Mobile-Tutor-Studio nutzen denselben geschützten Schreibvertrag. Ein Tutor kann nur eigene Kurse verbinden; doppelte Zielsprachen werden auch bei parallelen Schreibvorgängen kontrolliert abgewiesen.
- [x] Der Studio-Assistent übernimmt ausschließlich nichtsprachliche Metadaten. Titel, Beschreibung, Kapitel, Lektionen, Aufgaben, Quiz, Produktverknüpfung, Einschreibung, Fortschritt und Zertifikat bleiben je Sprachvariante getrennt; dadurch entstehen keine Scheinübersetzung und keine falsche Kauf- oder Lernfreischaltung.
- [x] Gastkatalog, öffentliche API, authentifizierter Mobile-Katalog und gemeinsamer Commerce-Katalog zeigen je Kursfamilie zuerst die exakte Sprache und sonst genau eine sichtbar gekennzeichnete deutsche Fallbackfassung.
- [x] Kursdetailseiten besitzen `lang`/`dir`, reale Sprachwechsel, inhaltsbezogene Canonical-/Hreflang-Ziele und `Course`-Schema. Nicht eingeschriebene Aufrufe wechseln per `301` zur exakten Variante; eine bestehende Einschreibung bleibt sicher auf ihrem eigenen Kurs und Fortschritt.
- [x] Die Sitemap veröffentlicht ausschließlich real vorhandene Sprachvarianten. Zertifikatdaten und PDF-Standardtexte folgen der Kurssprache; öffentliche Zertifikatseiten geben die Schreibrichtung mit aus.
- [x] Ressourcengrenze eingehalten: vollständige DE/EN/FR/AR-Hinweise liegen in einem gemeinsamen 2,23-KB-Lazy-Chunk; der kritische arabische Kern bleibt unverändert bei 310.986 Bytes.
- [x] Verifiziert: neue Kursübersetzungssuite = 7 bestanden, 84 Assertions; vollständige Backend-Suite = 970 bestanden, 4 bewusst übersprungen, 25.174 Assertions.
- [x] Verifiziert: `npm run build` = 1.047 Module und 183 Manifest-Einträge; Pint und `git diff --check` sind sauber.
- [x] Verifiziert: Repository-Preflight = 10 bestanden, 13 externe Evidenzgates ausstehend, 0 Fehler und korrektes `no_go`.
- [ ] Extern: echte Kursübersetzungen samt arabischer Typografie, Screenreader-/RTL-Verhalten, separaten Kaufprodukten und Zertifikat-PDFs auf release-identischem Staging fachlich abnehmen.


## 23.09.2026 – Ausbau zur vollständigen Vereinsplattform

Die vollständige, weiterhin offene Gesamtplanung wird in [AIRMIUS_VEREINSPLATTFORM_UMSETZUNG_CHECKLISTE.md](AIRMIUS_VEREINSPLATTFORM_UMSETZUNG_CHECKLISTE.md) geführt. Diese ergänzt die MVP-Liste und ersetzt keine bisherigen Nachweise.

- [x] Vollständige Nutzeranforderungen aus 36 Bereichen als Checkliste übernehmen.
- [x] Ersten Zahlungsbaustein implementieren: Teilzahlungen, Restbeträge, Überzahlungen, Korrekturen und Anpassung von Web/API/App/Bankabgleich/SEPA.
- [x] Bisherige Zahlungsabläufe und vollständigen Flutter-Testbestand nachprüfen; Nachweise in der Ausbau-Checkliste.
- [ ] Sieben offene Repository-Prüfbefunde Q001–Q007 aus dem umfassenden PHP-Lauf abarbeiten.
- [ ] Dauerhafte Lastschriftläufe, Freigaben, Vorabinformationen und Rücklastschriften umsetzen.
- [ ] Weitere Ausbaupakete P01–P12 vollständig abschließen und fachlich abnehmen.
