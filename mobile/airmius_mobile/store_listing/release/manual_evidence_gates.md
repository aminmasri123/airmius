# Airmius Mobile Manual Evidence Gates

This runbook lists release gates that cannot be fully proven by local scripts alone.

The full release evidence pipeline can generate logs, Android artifacts, reports and bundles. It cannot replace human verification, store-console work, legal approval, production domain deployment or signed iOS/TestFlight evidence.

## iOS Release Build

Required evidence:

- Signed IPA or TestFlight-ready build.
- Apple signing identity/provisioning confirmation.
- App Store Connect/TestFlight upload or archive evidence.

Why manual:

- Requires Apple credentials, certificates, provisioning profiles and macOS signing context.
- A no-codesign build or `Runner.app.zip` is partial evidence only.

## Deep Link Domain Verification

Required evidence:

- Live `assetlinks.json` response.
- Live `apple-app-site-association` response.
- No redirects for `.well-known` files.
- Android/iOS link-opening QA notes.

Why manual:

- Requires production/staging domain deployment and real device validation.

## Final Store Screenshots

Required evidence:

- Android screenshots from release-equivalent build.
- iOS screenshots from release-equivalent build.
- No real private data visible.
- Correct dark/normal logo behavior.

Why manual:

- Screenshots must be visually reviewed and captured from devices/simulators.

## Real Laravel API QA

Required evidence:

- Login smoke test.
- Club search/profile smoke test.
- Membership request and withdrawal smoke test.
- Notifications/messages smoke test.
- Events/training smoke test.
- Documents/upload intent smoke test.
- Finance/invoices smoke test.

Suggested helper:

```powershell
.\scripts\run_laravel_api_smoke.ps1 -ApiBaseUrl "https://airmius.com" -Email "review@example.com" -ClubId 26
```

Membership request helper:

```powershell
.\scripts\run_membership_api_smoke.ps1 -ApiBaseUrl "https://airmius.com" -Email "review@example.com" -ClubId 26 -ApplicationPayloadPath "release_evidence\membership-payload.example.local.json"
```

Create the local payload from the safe template first:

```powershell
.\scripts\new_membership_smoke_payload.ps1
```

Withdraw check only on safe staging/review data:

```powershell
.\scripts\run_membership_api_smoke.ps1 -ApiBaseUrl "https://airmius.com" -Email "review@example.com" -ClubId 26 -ApplicationPayloadPath "release_evidence\membership-payload.example.local.json" -WithdrawAfterCreate
```

Use write checks only on safe staging/review data:

```powershell
.\scripts\run_laravel_api_smoke.ps1 -ApiBaseUrl "https://airmius.com" -Email "review@example.com" -ClubId 26 -IncludeWriteChecks
```

Manifest sync behavior:

- The static `logo_theme_asset_mapping` gate can be marked `passed` automatically when `logo-theme-assets.log` or `logo-theme-assets-ios.log` contains no obvious failure markers.
- Read-only smoke evidence can mark `real_api_qa` as `partial_evidence`.
- Smoke evidence with upload-intent write checks still marks `real_api_qa` as `partial_evidence`.
- Membership smoke evidence can mark `real_api_qa` as `partial_evidence`, including create/detail and optional withdrawal proof.
- `real_api_qa` should be marked `passed` only after membership request, withdrawal, notifications/messages, events/training, documents/upload intent and finance/invoices are reviewed with real backend data.
- Membership payload files should stay local/evidence-only and must not contain real private member data.
- Use `store_listing/release/membership_payload.template.json` as a fake-data starting point only.
- Any `FAIL` marker keeps the gate from being auto-passed.

Why manual:

- Requires real Laravel API environment, test users and backend data.

## Mobile Real Device Smoke Test

Manifest gate:

- `real_device_smoke`

Runbook:

- `store_listing/release/real_device_smoke_test_runbook.md`
- `store_listing/release/linux_android_setup_runbook.md`

Linux prerequisite checker:

```bash
scripts/install_android_sdk_user.sh --accept-licenses
scripts/assert_linux_android_release_prerequisites.sh --require-android-device
```

Android evidence helper:

```bash
scripts/run_android_real_device_smoke.sh --build-release
```

iOS/TestFlight evidence helper on macOS:

```bash
scripts/run_ios_real_device_smoke.sh --build-ipa
```

Combined evidence validator after Android and iOS notes are completed:

```bash
scripts/assert_real_device_smoke_evidence.sh
```

Required evidence:

- Android physical-device and iOS physical-device/TestFlight results for all 19 `cross-device-experience.v1` controls.
- Exact backend release `2026-08-09` and mobile build `1.0.42+127`.
- Release build, secure session, push, event files, deep links, route/training/event, recruiting consent/handoff, refund duplicate protection, payout/reconciliation, GPS ownership, DE/EN/FR/AR/RTL, TalkBack/VoiceOver, 200-percent text/reflow and privacy review.
- Only environment alias, device class, OS version and short artifact references in the coordination evidence. Reviewer identity belongs in the authoritative manifest.
- Screenshots or recordings remain in the protected evidence store; raw URLs/paths, device/account/contact identifiers, secrets, tokens and payment data must not be copied into the coordination file.

Current technical status:

- Flutter Analyze, 251 native tests and signed Android AAB/APK integrity are green.
- Physical Android evidence remains open.
- iOS real-device testing still requires macOS/Xcode/TestFlight.
- Previously generated five-part notes are intentionally rejected by the new validator.

Why manual:

- Push permissions, native upload pickers, secure storage lifecycle and OS-level deep links must be verified on real Android and iOS devices.

## Legal and Privacy Approval

Required evidence:

- Play Data Safety approval.
- App Store Privacy approval.
- Privacy policy URL approval.
- Terms/support URL approval.

Why manual:

- Requires product/legal owner sign-off based on actual data processing.

## Store Submission Readiness

Runbook:

- `store_listing/release/store_submission_readiness_runbook.md`

Required evidence:

- Play Console checklist approved.
- App Store Connect checklist approved.
- TestFlight/signed IPA evidence linked.
- Store review account instructions added only in store-console reviewer notes.
- Final release notes approved.
- Confirmation that no credentials, secrets, tokens, private user data or payment data appear in evidence files.

Why manual:

- Requires store-console access, final release artifacts, reviewer notes, privacy/legal approvals and human owner sign-off.

## Localization Visual QA

Required evidence:

- German visual QA.
- English visual QA.
- French visual QA.
- Arabic RTL visual QA.

Why manual:

- Requires human visual review for truncation, readability, layout and RTL behavior.

## Secure Token Storage QA

Required evidence:

- Android login restore/logout restart QA.
- iOS login restore/logout restart QA.
- Migration result or first-release not-applicable note.
- Confirmation that no raw tokens appear in logs/screenshots.

Why manual:

- Requires actual device/app lifecycle testing.

## Store Review Account

Required evidence:

- Dedicated review account active.
- Credentials entered only in store consoles.
- Safe demo data confirmation.
- Core review path login proof.

Why manual:

- Requires account setup in the backend and secure credential handling outside the repository.

## Store Release Notes

Required evidence:

- Play Console notes.
- App Store Connect notes.
- TestFlight/internal tester notes.
- Release owner approval.

Why manual:

- Requires store-console entry and product owner confirmation.

## How to close manual gates

1. Collect the required evidence.
2. Attach screenshots, logs or approval notes to the release evidence package.
3. Use the sign-off template when owner approval is required:

```text
store_listing/release/manual_gate_signoff_template.md
```

4. Update the matching gate:

```powershell
.\scripts\update_release_evidence_gate.ps1 -GateId "real_api_qa" -Status "passed" -Note "Smoke routes passed on staging with review account."
```

5. Run manifest integrity:

```powershell
.\scripts\assert_release_manifest_integrity.ps1
```

6. Run final Go/No-Go only after all manual gates are `passed`.
## Logo and Theme Parity QA

Runbook:

- `store_listing/release/logo_theme_parity_qa.md`

Suggested evidence pack helper:

```powershell
.\scripts\new_manual_release_evidence_pack.ps1
```

Suggested evidence pack checker after screenshots/sign-offs are attached:

```powershell
.\scripts\assert_manual_release_evidence_pack.ps1
```

Required evidence:

- Normal theme screenshot showing the dark Airmius wordmark on a light background.
- Dunkel theme screenshot showing the white Airmius wordmark on a dark background.
- System theme screenshot or note confirming it follows the device brightness.
- App restart after each theme mode to confirm persisted theme/logo state.
- Login screen and at least one authenticated shell screen checked.

Why manual:

- Logo readability and theme parity are visual release requirements and must be reviewed on real rendered screens.
