# Airmius Mobile Manual Tester Quickstart

This guide is for local manual testing before final release evidence is collected.

## 1. Start as web debug app

Use demo/static data:

```powershell
cd C:\xampp\htdocs\airmius\mobile\airmius_mobile
.\scripts\run_web_debug.ps1
```

Use Laravel HTTP API:

```powershell
cd C:\xampp\htdocs\airmius\mobile\airmius_mobile
.\scripts\run_web_debug.ps1 -ApiBaseUrl "http://localhost" -UseHttp
```

## 2. Start as Android debug app

Use demo/static data:

```powershell
cd C:\xampp\htdocs\airmius\mobile\airmius_mobile
.\scripts\run_android_debug.ps1
```

Use Laravel HTTP API from Android emulator:

```powershell
cd C:\xampp\htdocs\airmius\mobile\airmius_mobile
.\scripts\run_android_debug.ps1 -ApiBaseUrl "http://10.0.2.2" -UseHttp
```

## 3. Core smoke test

1. Open login screen.
2. Confirm Normal theme shows the dark Airmius wordmark on the light background.
3. Confirm Dunkel theme shows the white Airmius wordmark on the dark background.
4. Confirm System theme follows device brightness.
5. Switch language and theme, restart app, confirm settings persist.
6. Login with demo account.
7. Open Operations Hub.
8. Open Clubs.
9. Search/open ZBB.
10. Open membership form.
11. Submit request.
12. Withdraw request.
13. Open notifications.
14. Open conversations.
15. Open events/training.
16. Open profile.
17. Open Release Candidate Gates.
18. Open Release Evidence Center.
19. Log out, restart and confirm the session is cleared.
20. Log in, restart and confirm the session is restored.
21. Confirm the store review account can complete the core review path without exposing real private data.
22. Confirm release notes do not mention secrets, private data or unverified completion claims.

## 4. Deep link smoke test

Android:

```powershell
adb shell am start -a android.intent.action.VIEW -d "airmius://clubs/26" com.airmius.app
adb shell am start -a android.intent.action.VIEW -d "airmius://messages/1" com.airmius.app
adb shell am start -a android.intent.action.VIEW -d "airmius://events/1" com.airmius.app
adb shell am start -a android.intent.action.VIEW -d "airmius://notifications/1" com.airmius.app
```

Expected:

- Club link opens direct ZBB profile.
- Message/event/notification links open the deep-link arrival screen with detail preview.
- Unknown link opens safe fallback.

## 4a. Real device smoke test

Use the focused runbook before final MVP release:

```text
store_listing/release/real_device_smoke_test_runbook.md
```

Minimum required real-device checks:

- Android and iOS login with restart/session-restore/logout.
- Android and iOS push opt-in, token registration, test notification and logout token cleanup.
- Android and iOS native upload picker with successful upload and retryable failure.
- Android and iOS deep links for club, event, chat/message and invitation targets.

## 5. Release candidate evidence

Prepare local folders and sign-off files for manual screenshots, API QA and approvals:

```powershell
cd C:\xampp\htdocs\airmius\mobile\airmius_mobile
.\scripts\new_manual_release_evidence_pack.ps1
```

Check the manual evidence pack after screenshots, logs and sign-offs are added:

```powershell
cd C:\xampp\htdocs\airmius\mobile\airmius_mobile
.\scripts\assert_manual_release_evidence_pack.ps1
```

Logo/theme visual QA runbook:

```powershell
store_listing\release\logo_theme_parity_qa.md
```

Logo/theme static asset check:

```powershell
.\scripts\assert_logo_theme_assets.ps1
```

When ready to collect evidence:

```powershell
cd C:\xampp\htdocs\airmius\mobile\airmius_mobile
.\scripts\assert_local_release_prerequisites.ps1
```

The prerequisite check now stops early when Flutter, Java, Android SDK path, `adb`, Android `cmdline-tools`/`sdkmanager`, or accepted Android licenses are missing. If it reports missing licenses, install Android command-line tools first and then run:

```powershell
flutter doctor --android-licenses
```

Linux Android prerequisite checker:

```bash
cd /var/www/airmius/mobile/airmius_mobile
scripts/install_android_sdk_user.sh --accept-licenses
export ANDROID_HOME="$HOME/Android/Sdk"
export ANDROID_SDK_ROOT="$HOME/Android/Sdk"
export PATH="$ANDROID_HOME/platform-tools:$ANDROID_HOME/cmdline-tools/latest/bin:$PATH"
scripts/assert_linux_android_release_prerequisites.sh --require-android-device
```

Linux Android real-device evidence helper after prerequisites pass:

```bash
scripts/run_android_real_device_smoke.sh --build-release
```

macOS iOS/TestFlight evidence helper:

```bash
scripts/run_ios_real_device_smoke.sh --build-ipa
```

```powershell
cd C:\xampp\htdocs\airmius\mobile\airmius_mobile
.\scripts\release_candidate_checks.ps1 -ApiBaseUrl "https://airmius.com"
```

If Flutter is not in `PATH`:

```powershell
.\scripts\release_candidate_checks.ps1 -ApiBaseUrl "https://airmius.com" -FlutterCommand "C:\flutter\bin\flutter.bat"
```

This runs:

- `flutter pub get`
- `flutter analyze`
- Android App Bundle release build
- Android APK release build
- local evidence log capture into `release_evidence`
- Android artifact copy into `release_evidence\artifacts`

The iOS signed IPA still needs macOS and Apple signing.

Domain verification helper:

```powershell
cd C:\xampp\htdocs\airmius\mobile\airmius_mobile
.\scripts\check_deep_link_domain.ps1 -HostName "app.airmius.com"
```

Evidence status helper:

```powershell
cd C:\xampp\htdocs\airmius\mobile\airmius_mobile
.\scripts\show_release_evidence_status.ps1
```

Laravel API smoke helper:

```powershell
cd C:\xampp\htdocs\airmius\mobile\airmius_mobile
.\scripts\run_laravel_api_smoke.ps1 -ApiBaseUrl "https://airmius.com" -Email "review@example.com" -ClubId 26
```

Membership API smoke helper:

```powershell
cd C:\xampp\htdocs\airmius\mobile\airmius_mobile
.\scripts\new_membership_smoke_payload.ps1
.\scripts\run_membership_api_smoke.ps1 -ApiBaseUrl "https://airmius.com" -Email "review@example.com" -ClubId 26 -ApplicationPayloadPath "release_evidence\membership-payload.example.local.json"
```

Evidence gate update helper:

```powershell
cd C:\xampp\htdocs\airmius\mobile\airmius_mobile
.\scripts\update_release_evidence_gate.ps1 -GateId "real_api_qa" -Status "passed" -Note "Smoke routes passed on staging"
```

Evidence report helper:

```powershell
cd C:\xampp\htdocs\airmius\mobile\airmius_mobile
.\scripts\export_release_evidence_report.ps1
```

Evidence manifest sync helper:

```powershell
cd C:\xampp\htdocs\airmius\mobile\airmius_mobile
.\scripts\sync_release_evidence_manifest.ps1
```

Evidence manifest integrity helper:

```powershell
cd C:\xampp\htdocs\airmius\mobile\airmius_mobile
.\scripts\assert_release_manifest_integrity.ps1
```

Manual evidence gates helper:

```powershell
cd C:\xampp\htdocs\airmius\mobile\airmius_mobile
.\scripts\show_manual_evidence_gates.ps1
```

Full release evidence pipeline:

```powershell
cd C:\xampp\htdocs\airmius\mobile\airmius_mobile
.\scripts\run_full_release_evidence_pipeline.ps1 -ApiBaseUrl "https://airmius.com"
```

Final Go/No-Go pipeline:

```powershell
cd C:\xampp\htdocs\airmius\mobile\airmius_mobile
.\scripts\run_full_release_evidence_pipeline.ps1 -ApiBaseUrl "https://airmius.com" -RunGoNoGo
```

Evidence bundle helper:

```powershell
cd C:\xampp\htdocs\airmius\mobile\airmius_mobile
.\scripts\package_release_evidence_bundle.ps1
```

Evidence bundle content helper:

```powershell
cd C:\xampp\htdocs\airmius\mobile\airmius_mobile
.\scripts\assert_evidence_bundle_contents.ps1
```

Release configuration helper:

```powershell
cd C:\xampp\htdocs\airmius\mobile\airmius_mobile
.\scripts\assert_release_configuration.ps1 -ApiBaseUrl "https://airmius.com" -UseHttp
```

Release secrets hygiene helper:

```powershell
cd C:\xampp\htdocs\airmius\mobile\airmius_mobile
.\scripts\assert_no_release_secrets.ps1
```

Release Go/No-Go helper:

```powershell
cd C:\xampp\htdocs\airmius\mobile\airmius_mobile
.\scripts\assert_release_go_no_go.ps1
```

The bundle helper creates:

- `store_listing\release\airmius_release_evidence_bundle.zip`

## 6. Pass criteria

- No compile/runtime errors in core flows.
- Dark and normal logo modes look correct.
- Language/theme persistence works.
- Auth/session restore works.
- Direct club deep link works.
- Detail previews load for membership/event/message/notification deep links.
- Release Candidate Gates and Release Evidence Center are visible.
- No real private data is visible in screenshots.
- Release evidence bundle contains the final runbooks, manifest, screenshots, privacy/deep-link files and generated build artifacts where available.
- Release evidence bundle content check passes.
- Secure token storage QA is completed using `store_listing/release/secure_token_storage_qa.md`.
- Store review account QA is completed using `store_listing/release/store_review_account_runbook.md`.
- Store release notes are completed using `store_listing/release/store_release_notes.md`.
- Release configuration check passes with HTTPS API, HTTP transport and no localhost.
- Release secrets hygiene check passes before evidence packaging.
- Release Go/No-Go check passes only after every manifest gate has direct evidence.
- Evidence manifest sync is used only after real logs/artifacts exist.
- Evidence manifest integrity check passes before final Go/No-Go.
- Manual evidence gates helper shows no open manual gates before final Go/No-Go.
- Full release evidence pipeline is used when the operator is ready to run actual analyze/build/evidence generation, package the bundle and sync the bundle gate before Go/No-Go.
- Final Go/No-Go pipeline verifies manual evidence gates, marks the Go/No-Go gate passed only after the command succeeds, then refreshes report and bundle.

## 7. Manual evidence gates

Before final Go/No-Go, complete the non-automated gates in:

- `store_listing/release/manual_evidence_gates.md`

For the short final execution order, use:

- `store_listing/release/final_execution_sequence.md`
