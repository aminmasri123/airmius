# AIRMIUS Store Readiness MVP

Stand: 2026-08-09

## Status

AIRMIUS ist technisch fuer die Store-Vorbereitung strukturiert. Der lokale, nicht autoritative Evidenzstand betraegt 3 von 22 Gates: Version und App-IDs sind konsistent, das Android-AAB und -APK wurden signiert gebaut und auf ZIP-Integritaet, Android-Signatur sowie Mindest-SDK 24 geprueft. Die App ist noch nicht einreichbereit, weil finale Screenshots, Realgeraetepruefungen, iOS-Signierung, Store-Console-Eintraege, Review-Account-Daten und juristische Freigaben manuell beziehungsweise durch externe Systeme abgeschlossen werden muessen.

## Vorhandene Artefakte

- App-IDs:
  - Android: `com.airmius.app`
  - iOS: `com.airmius.app`
- Version:
  - Flutter: `1.0.33+77`
- App Icons:
  - Web/PWA Icons: `public/icons/airmius-icon-192.png`, `public/icons/airmius-icon-512.png`, maskable Varianten.
  - Android Launcher Icons: `mobile/airmius_mobile/android/app/src/main/res/mipmap-*`.
  - iOS App Icon Set: `mobile/airmius_mobile/ios/Runner/Assets.xcassets/AppIcon.appiconset`.
- Store Texte:
  - Google Play: `mobile/airmius_mobile/store_listing/play_store/de-DE`.
  - App Store: `mobile/airmius_mobile/store_listing/app_store/de-DE`.
- Datenschutzangaben:
  - Play Data Safety Draft: `mobile/airmius_mobile/store_listing/privacy/play_data_safety_draft.md`.
  - App Store Privacy Draft: `mobile/airmius_mobile/store_listing/privacy/app_store_privacy_draft.md`.
  - Privacy Matrix: `mobile/airmius_mobile/store_listing/privacy/privacy_submission_matrix.md`.
- Screenshots:
  - Capture-Plan: `mobile/airmius_mobile/store_listing/screenshots/screenshot_capture_plan.md`.
- Review und Submission:
  - Review-Account-Runbook: `mobile/airmius_mobile/store_listing/release/store_review_account_runbook.md`.
  - Release Notes: `mobile/airmius_mobile/store_listing/release/store_release_notes.md`.
  - Store Submission Runbook: `mobile/airmius_mobile/store_listing/release/store_submission_readiness_runbook.md`.
  - Release Evidence Manifest: `mobile/airmius_mobile/store_listing/release/release_evidence_manifest.json`.

## Support- und Datenschutzkontakte

- Support-Mail: `support@airmius.com`
- Datenschutz-Mail: `datenschutz@airmius.com`
- Relevante Env-Schalter:
  - `LEGAL_SUPPORT_EMAIL`
  - `LEGAL_PRIVACY_EMAIL`
  - `MAIL_SUPPORT_FROM_ADDRESS`

## Final vor Einreichung

- Android-Artefakte aus der freigegebenen Pipeline in Play Console hochladen und Play App Signing dort bestaetigen; lokale Build-Nachweise ersetzen diese Store-Pruefung nicht.
- iOS signierte IPA/TestFlight-Builds erzeugen.
- Screenshots auf release-equivalenter App aufnehmen, ohne private Nutzer-, Zahlungs- oder Vereinsdaten.
- Play Data Safety und App Store Privacy Labels mit juristischer Freigabe finalisieren.
- Store Review Account in Play Console/App Store Connect hinterlegen, aber keine Zugangsdaten im Repository speichern.
- Final Go/No-Go ueber `mobile/airmius_mobile/store_listing/release/release_evidence_manifest.json` durchfuehren.

## Technische Mindestpruefung

```bash
php artisan test tests/Feature/StoreReadinessDocumentationTest.php
```

Diese Pruefung validiert, dass die Store-Readiness-Artefakte vorhanden sind, kritische Icons lesbare Zielgroessen haben, Store-/Privacy-Texte nicht leer sind und Support-/Datenschutzkontakte konfiguriert sind.
