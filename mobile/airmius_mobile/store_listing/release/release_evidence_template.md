# Airmius Mobile Release Evidence Template

Use this file for the final release-candidate evidence package.

## Release candidate metadata

- Release candidate:
- Date:
- Tester:
- Backend environment:
- Flutter version:
- Android signing source:
- iOS signing source:
- API base URL:
- App version:
- Manual tester quickstart:
  - `store_listing/release/manual_tester_quickstart.md`
- Final operator handoff:
  - `store_listing/release/final_operator_handoff.md`
- Machine-readable evidence manifest:
  - `store_listing/release/release_evidence_manifest.json`
- Local web debug script:
  - `scripts/run_web_debug.ps1`
- Local Android debug script:
  - `scripts/run_android_debug.ps1`
- Local release prerequisites script:
  - `scripts/assert_local_release_prerequisites.ps1`
- Linux Android user SDK installer:
  - `scripts/install_android_sdk_user.sh`
- Linux Android prerequisite script:
  - `scripts/assert_linux_android_release_prerequisites.sh`
- Android real-device smoke script:
  - `scripts/run_android_real_device_smoke.sh`
- iOS/TestFlight smoke script:
  - `scripts/run_ios_real_device_smoke.sh`
- Real device evidence validator:
  - `scripts/assert_real_device_smoke_evidence.sh`
- Evidence status script:
  - `scripts/show_release_evidence_status.ps1`
- Next release steps script:
  - `scripts/show_next_release_steps.ps1`
- Next release steps report exporter:
  - `scripts/export_next_release_steps_report.ps1`
- Evidence update script:
  - `scripts/update_release_evidence_gate.ps1`
- Evidence report export script:
  - `scripts/export_release_evidence_report.ps1`
- Evidence manifest sync script:
  - `scripts/sync_release_evidence_manifest.ps1`
- Evidence manifest integrity script:
  - `scripts/assert_release_manifest_integrity.ps1`
- Manual evidence gates status script:
  - `scripts/show_manual_evidence_gates.ps1`
- Evidence bundle packaging script:
  - `scripts/package_release_evidence_bundle.ps1`
- Evidence bundle content script:
  - `scripts/assert_evidence_bundle_contents.ps1`
- Logo/theme asset check script:
  - `scripts/assert_logo_theme_assets.ps1`
- Full release evidence pipeline script:
  - `scripts/run_full_release_evidence_pipeline.ps1`
- Manual evidence gates:
  - `store_listing/release/manual_evidence_gates.md`
- Logo and theme parity QA:
  - `store_listing/release/logo_theme_parity_qa.md`
- Manual gate sign-off template:
  - `store_listing/release/manual_gate_signoff_template.md`
- Manual evidence pack helper:
  - `scripts/new_manual_release_evidence_pack.ps1`
- Manual evidence pack checker:
  - `scripts/assert_manual_release_evidence_pack.ps1`
- Final execution sequence:
  - `store_listing/release/final_execution_sequence.md`
- Final command cheatsheet:
  - `store_listing/release/final_command_cheatsheet.md`
- Windows Android setup runbook:
  - `store_listing/release/windows_android_setup_runbook.md`
- Linux Android setup runbook:
  - `store_listing/release/linux_android_setup_runbook.md`
- Real device smoke test runbook:
  - `store_listing/release/real_device_smoke_test_runbook.md`
- Store submission readiness runbook:
  - `store_listing/release/store_submission_readiness_runbook.md`

## Gate: Android and iOS Real Device Smoke

- Manifest gate ID:
  - `real_device_smoke`
- Android command:
  - `scripts/run_android_real_device_smoke.sh --build-release`
- iOS/macOS command:
  - `scripts/run_ios_real_device_smoke.sh --build-ipa`
- Manual evidence files:
  - `release_evidence/manual/real_device_smoke/android-real-device-smoke.md`
  - `release_evidence/manual/real_device_smoke/ios-real-device-smoke.md`
- Required outcome:
  - Android and iOS both pass login, push, upload and deep-link checks before the AIRMIUS MVP checklist item is marked done.
- Validator:
  - `scripts/assert_real_device_smoke_evidence.sh`

## Gate 1: Flutter Analyze

- Prerequisite command:
  - `.\scripts\assert_local_release_prerequisites.ps1`
- Linux Android device prerequisite command:
  - `scripts/install_android_sdk_user.sh --accept-licenses`
  - `scripts/assert_linux_android_release_prerequisites.sh --require-android-device`
- Android real-device evidence command:
  - `scripts/run_android_real_device_smoke.sh --build-release`
- iOS/TestFlight evidence command:
  - `scripts/run_ios_real_device_smoke.sh --build-ipa`
- Prerequisite evidence artifact:
  - `release_evidence/local-release-prerequisites.log`
- Command:
  - `flutter analyze`
- CI artifact:
  - `airmius-mobile-release-evidence/flutter-analyze.log`
- Result:
  - Pending
- Evidence:
  - Attach terminal output or CI log.
- Notes:

## Gate 1b: Flutter Dependency Lock

- Command:
  - `.\scripts\assert_flutter_dependency_lock.ps1`
- CI artifact:
  - `airmius-mobile-release-evidence/flutter-dependency-lock.log`
  - `airmius-mobile-ios-release-evidence/flutter-dependency-lock-ios.log`
- Result:
  - Pending
- Evidence:
  - Attach dependency lock log after `flutter pub get`.
- Notes:

## Gate 1c: Logo and Theme Asset Mapping

- Command:
  - `.\scripts\assert_logo_theme_assets.ps1`
- CI artifact:
  - `airmius-mobile-release-evidence/logo-theme-assets.log`
  - `airmius-mobile-ios-release-evidence/logo-theme-assets-ios.log`
- Result:
  - Pending
- Evidence:
  - Attach static logo/theme asset mapping log.
- Notes:

## Gate 1d: Manual Evidence Pack

- Command:
  - `.\scripts\assert_manual_release_evidence_pack.ps1`
- Evidence artifact:
  - `release_evidence/manual-evidence-pack.log`
- Result:
  - Pending
- Evidence:
  - Attach manual evidence pack checker log from final `-RunGoNoGo` execution.
- Notes:

## Gate 2: Android Release Build

- Command:
  - `flutter build appbundle --release --dart-define=AIRMIUS_API_BASE_URL=https://app.airmius.com --dart-define=AIRMIUS_USE_HTTP=true`
- CI artifact:
  - `airmius-mobile-release-evidence/android-appbundle-build.log`
  - `airmius-mobile-release-evidence/artifacts/app-release.aab`
- Result:
  - Pending
- Artifact:
  - `build/app/outputs/bundle/release/app-release.aab`
- Evidence:
  - Attach build log and artifact path.
- Notes:

## Gate 3: iOS Release Build

- Command:
  - `flutter build ipa --release --dart-define=AIRMIUS_API_BASE_URL=https://app.airmius.com --dart-define=AIRMIUS_USE_HTTP=true`
- CI artifact:
  - `airmius-mobile-ios-release-evidence/flutter-analyze-ios.log`
  - `airmius-mobile-ios-release-evidence/ios-release-build-no-codesign.log`
  - `airmius-mobile-ios-release-evidence/artifacts/Runner.app.zip`
- Signed IPA note:
  - A signed `.ipa` still requires Apple signing credentials, provisioning and App Store Connect/TestFlight setup.
- Sync note:
  - No-codesign evidence may be marked as `partial_evidence`; the gate passes only with signed IPA/TestFlight evidence.
- Result:
  - Pending
- Artifact:
  - `build/ios/ipa/*.ipa`
- Evidence:
  - Attach build log and artifact path.
- Notes:

## Gate 4: Deep Link Domain Verification

- Android URL:
  - `https://app.airmius.com/.well-known/assetlinks.json`
- iOS URL:
  - `https://app.airmius.com/.well-known/apple-app-site-association`
- Windows helper:
  - `scripts/check_deep_link_domain.ps1`
- Result:
  - Pending
- Evidence:
  - Attach HTTP response headers and body validation.
- Notes:

## Gate 5: Native Deep Link QA

- Android custom scheme:
  - `airmius://clubs/26`
- Android App Link:
  - `https://app.airmius.com/clubs/26`
- iOS custom scheme:
  - `airmius://clubs/26`
- iOS Universal Link:
  - `https://app.airmius.com/clubs/26`
- Result:
  - Pending
- Evidence:
  - Attach screenshots or QA notes.
- Notes:

## Gate 6: Screenshot Capture

- Android screenshots:
  - Pending
- iOS screenshots:
  - Pending
- Evidence:
  - Attach final screenshot folder paths.
- Notes:

## Gate 7: Real API QA

- Login:
  - Pending
- Club search/profile:
  - Pending
- Membership request:
  - Pending
- Withdraw request:
  - Pending
- Notifications/messages:
  - Pending
- Events/training:
  - Pending
- Documents/upload intents:
  - Pending
- Finance/invoices:
  - Pending
- Evidence:
  - Attach QA notes and API log references.
- Helper:
  - `scripts/run_laravel_api_smoke.ps1`
  - Expected log: `release_evidence/laravel-api-smoke.log`
- Membership helper:
  - `scripts/new_membership_smoke_payload.ps1`
  - `scripts/run_membership_api_smoke.ps1`
  - Expected log: `release_evidence/membership-api-smoke.log`
  - Safe payload template: `store_listing/release/membership_payload.template.json`

## Gate 8: Legal and Privacy

- Play Data Safety:
  - Pending
- App Store Privacy:
  - Pending
- Privacy policy URL:
  - Pending
- Terms/support URL:
  - Pending
- Evidence:
  - Attach approval note or owner sign-off.

## Gate 9: Store Submission Readiness

- Runbook:
  - `store_listing/release/store_submission_readiness_runbook.md`
- Play Console:
  - Pending
- App Store Connect:
  - Pending
- TestFlight/signed IPA:
  - Pending
- Review account notes:
  - Pending
- Final release notes:
  - Pending
- Evidence:
  - Attach store-console/readiness owner sign-off without credentials or secrets.

## Gate 10: Localization QA

- German:
  - Pending
- English:
  - Pending
- French:
  - Pending
- Arabic RTL:
  - Pending
- Evidence:
  - Attach screenshots or QA notes.

## Gate 11: Logo and Theme Parity QA

- Runbook:
  - `store_listing/release/logo_theme_parity_qa.md`
- Static asset/mapping check:
  - `scripts/assert_logo_theme_assets.ps1`
- Evidence pack helper:
  - `scripts/new_manual_release_evidence_pack.ps1`
- Normal theme:
  - Pending
- Dunkel theme:
  - Pending
- System theme:
  - Pending
- Restart persistence:
  - Pending
- Login screen:
  - Pending
- Authenticated shell screen:
  - Pending
- Evidence:
  - Attach screenshots proving Normal uses the dark wordmark on light backgrounds, Dunkel uses the white wordmark on dark backgrounds, and System follows device brightness.

## Gate 12: Secure Token Storage QA

- Runbook:
  - `store_listing/release/secure_token_storage_qa.md`
- Android:
  - Pending
- iOS:
  - Pending
- Migration:
  - Pending or not applicable for first release
- Evidence:
  - Attach restart/restore/logout QA notes and screenshots without exposing raw tokens.

## Gate 13: Release Evidence Bundle

- Command:
  - `.\scripts\package_release_evidence_bundle.ps1`
- Artifact:
  - `store_listing\release\airmius_release_evidence_bundle.zip`
- Result:
  - Pending
- Evidence:
  - Attach bundle path and bundle manifest.

## Gate 12: Store Review Account

- Runbook:
  - `store_listing/release/store_review_account_runbook.md`
- Review account:
  - Pending
- Play Console review notes:
  - Pending
- App Store Connect review notes:
  - Pending
- Evidence:
  - Attach login QA note, safe demo-data confirmation and store-console credential entry confirmation.

## Gate 13: Store Release Notes

- Runbook:
  - `store_listing/release/store_release_notes.md`
- Play Console:
  - Pending
- App Store Connect:
  - Pending
- TestFlight/internal notes:
  - Pending
- Evidence:
  - Attach console/export screenshots or release owner approval note.

## Gate 14: Release Configuration Check

- Command:
  - `.\scripts\assert_release_configuration.ps1 -ApiBaseUrl "https://app.airmius.com" -UseHttp`
- Result:
  - Pending
- Evidence:
  - Attach terminal output, `airmius-mobile-release-evidence/release-configuration-check.log` or `airmius-mobile-ios-release-evidence/release-configuration-check-ios.log`.

## Gate 15: Release Secrets Hygiene

- Command:
  - `.\scripts\assert_no_release_secrets.ps1`
- Result:
  - Pending
- Evidence:
  - Attach terminal output, `airmius-mobile-release-evidence/release-secrets-hygiene.log` or `airmius-mobile-ios-release-evidence/release-secrets-hygiene-ios.log`.

## Gate 16: Release Go/No-Go Decision

- Command:
  - `.\scripts\assert_release_go_no_go.ps1`
- Result:
  - Pending
- Evidence:
  - Attach terminal output after all other gates have direct evidence.
- Note:
  - The Go/No-Go script intentionally ignores its own manifest gate while evaluating blockers. The full pipeline marks this gate passed after the command succeeds, then refreshes the report and bundle.
  - Any evaluated gate status other than `passed` blocks the final release, including unknown or mistyped statuses.

## Final decision

- Go / No-Go:
  - Pending
- Decision owner:
- Decision date:
- Remaining exceptions:

## Final package

- Bundle command:
  - `.\scripts\package_release_evidence_bundle.ps1`
- Bundle artifact:
  - `store_listing\release\airmius_release_evidence_bundle.zip`
- Bundle result:
  - Pending
- Notes:
