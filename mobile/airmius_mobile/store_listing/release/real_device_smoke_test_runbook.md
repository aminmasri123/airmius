# Airmius Mobile Real Device Smoke Test Runbook

This runbook closes the manual real-device gate for Android and iOS. It must be executed on real devices with safe test data before MVP release.

## Technischer Status am 2026-08-09

- Flutter Analyze, 251 native Tests sowie signiertes AAB/APK einschließlich Integritätsprüfung sind grün.
- Es liegt weiterhin kein freigegebener Android-Realgeräteabschluss vor.
- iOS benötigt weiterhin macOS, Xcode, Signierung und ein reales Gerät beziehungsweise TestFlight.
- Alte fünfteilige Smoke-Notizen sind keine gültige Evidenz mehr. `cross-device-experience.v1` verlangt auf beiden Plattformen denselben 19-Punkte-Vertrag.

Ergebnis: Der Gate bleibt offen, bis Android und iOS den vollständigen Vertrag bestehen und ihre Referenzen in den autoritativen Manifesten geprüft sind.

## Required devices

- Android physical device with USB debugging enabled.
- iPhone with TestFlight or local Xcode deployment.
- Sicheres Review-/Testkonto ohne private Produktivdaten.
- API environment with stable HTTPS base URL, for example `https://app.airmius.com`.

## Build and install

Android debug smoke:

```bash
flutter devices
flutter run -d <android-device-id> \
  --dart-define=AIRMIUS_API_BASE_URL=https://airmius.com \
  --dart-define=AIRMIUS_USE_HTTP=true
```

Android release-equivalent smoke:

```bash
flutter build apk --release \
  --dart-define=AIRMIUS_API_BASE_URL=https://airmius.com \
  --dart-define=AIRMIUS_USE_HTTP=true
adb install -r build/app/outputs/flutter-apk/app-release.apk
```

Android evidence helper:

```bash
scripts/run_android_real_device_smoke.sh --build-release
```

The helper checks prerequisites, collects Flutter/ADB/device logs, optionally builds and installs the release APK, opens deep links and captures screenshots into `release_evidence/android-real-device-smoke-*`.

iOS smoke on macOS:

```bash
flutter devices
flutter run -d <ios-device-id> \
  --dart-define=AIRMIUS_API_BASE_URL=https://airmius.com \
  --dart-define=AIRMIUS_USE_HTTP=true
```

For store-equivalent iOS evidence, use a signed TestFlight build or an Xcode-installed release build.

iOS/TestFlight evidence helper on macOS:

```bash
scripts/run_ios_real_device_smoke.sh --build-ipa
```

The helper checks macOS/Xcode/Flutter availability, optionally builds the IPA and creates the manual evidence template in `release_evidence/ios-real-device-smoke-*`.

After Android and iOS notes are copied or written into the manual evidence pack, run:

```bash
scripts/assert_real_device_smoke_evidence.sh
```

Der Validator verlangt auf beiden Plattformen exakte Release-/Buildparität, eine kurze nicht-sensitive Evidenzreferenz, `Result: PASS`, keine offenen Punkte und alle 19 `CDX-*`-Kontrollen. Alte Kurzlisten brechen fail-closed ab.

## Verbindliche 19-Punkte-Matrix

| Schlüssel | Prüfumfang auf Android und iOS |
|---|---|
| `CDX-01-release-build` | Release-äquivalentes beziehungsweise signiertes/TestFlight-Build installiert |
| `CDX-02-login-secure-session` | Login, sicherer Restart-Restore, Logout und gelöschte Sitzung nach Restart |
| `CDX-03-push-delivery-target` | Opt-in, Zustellung, Zielöffnung und Tokeninvalidierung nach Logout |
| `CDX-04-event-file-access` | Picker, Upload, geschützte Vorschau, Retry und erneute Autorisierung |
| `CDX-05-deep-links` | Verein, Event, Chat, Einladung und sichere Fallbacks; iOS zusätzlich Universal Links |
| `CDX-06-route-training-event` | Routenauswahl und Navigation zu Training sowie Event |
| `CDX-07-event-training-log` | Eventstart/-ende und vorausgefüllte Trainingsdokumentation |
| `CDX-08-recruiting-profile-consent` | feldweise Profilfreigabe und getrennte Chat-Einwilligung |
| `CDX-09-recruiting-chat-handoff` | Recruiting-Chat und Übergabe in Mitgliedschaft |
| `CDX-10-refund-duplicate-submit` | Doppel-Submit erzeugt keine zweite Erstattung oder Restock-Buchung |
| `CDX-11-payout-reconciliation` | Vorbereitung, Zahlung, Währung, Adjustment und Recovery |
| `CDX-12-gps-ownership` | eigene und fremde GPS-Tracks bleiben eigentumsrichtig getrennt |
| `CDX-13..16` | DE, EN, langes FR sowie semantisches AR mit echtem RTL und Overflow-Prüfung |
| `CDX-17-assistive-technology` | TalkBack beziehungsweise VoiceOver: Namen, Reihenfolge, Aktionen, Statusmeldungen |
| `CDX-18-text-scale-200` | 200 Prozent Text, Reflow, Touchziele und Tastatur-Inset |
| `CDX-19-privacy-review` | keine Roh-URLs, IDs, Kontakte, Tokens, Secrets, Zahl- oder Zahlungsdaten in Evidenz |

## Android deep-link commands

```bash
adb shell am start -a android.intent.action.VIEW -d "airmius://clubs/26" com.airmius.app
adb shell am start -a android.intent.action.VIEW -d "airmius://events/1" com.airmius.app
adb shell am start -a android.intent.action.VIEW -d "airmius://messages/1" com.airmius.app
adb shell am start -a android.intent.action.VIEW -d "airmius://invitations/test-token" com.airmius.app
```

## iOS deep-link checks

Use Safari, Notes, Mail or TestFlight tester notes on the iPhone:

```text
airmius://clubs/26
airmius://events/1
airmius://messages/1
airmius://invitations/test-token
```

For universal links, also test the production/staging HTTPS links after `apple-app-site-association` is deployed without redirects.

## Pass criteria

- Android und iOS bestehen alle 19 Schlüssel; Teilabdeckung ist kein Pass.
- Mobile Build `1.0.36+121` und Backend-Release `2026-08-09` stimmen exakt.
- Evidenzdateien enthalten nur Environment-Alias, Geräteklasse, OS-Version und kurze Artefaktreferenz; Revieweridentität bleibt im autoritativen Manifest.
- Abweichungen werden in einem externen Issue referenziert, nicht als Freitext oder Rohdaten in die Release-Evidenz kopiert.
- Erst danach dürfen Mobile-Manifest und die drei Plattformgates für Realgeräte, Lokalisierung und WCAG freigegeben werden.

## Evidence template

```text
Contract: cross-device-experience.v1
Release: 2026-08-09
Mobile build: 1.0.36+121
Platform: android | ios
Environment alias: staging
Evidence reference: SAFE-ARTEFACT-REFERENCE
Device class: phone-or-tablet | iphone
OS version: MAJOR.MINOR
Result: PASS | FAIL

- [x] CDX-01-release-build
...
- [x] CDX-19-privacy-review
```

Die JSON-Koordinationsvorlage `resources/release/cross_device_evidence.template.json` verbindet zusätzlich Web-Mobile, Web-Desktop, alle vier Sprachen, acht Kernreisen und sieben Assistive-Technology-Gruppen. Sie ersetzt weder das Mobile-Manifest noch menschliche Freigaben.
