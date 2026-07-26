# Airmius Mobile Final Operator Handoff

This handoff is the single-page operational summary for finishing the Airmius Flutter mobile app release candidate.

## Current product state

- Product preparation: 99% complete.
- Remaining product work: 1% evidence execution.
- Store/Test/Release preparation: 99% prepared.
- Remaining Store/Test/Release work: real execution, signing, screenshots, domain deployment, API QA and final approvals.

The app must not be called 100% complete until the evidence gates below are executed and documented.

The full release evidence pipeline does not replace manual gates. Use `store_listing/release/manual_evidence_gates.md` for iOS signing, domain verification, screenshots, real API QA, legal/privacy, localization, secure token storage QA, review account and release notes.

## What is already prepared

- Flutter mobile app shell in Airmius dark/mobile-web style.
- Airmius logo assets for dark/normal usage.
- Light/dark/system theme choice.
- Persistent language and theme preferences.
- Secure auth token store backed by platform secure storage, with legacy persistent-store fallback for migration.
- Laravel API client, repositories and typed models.
- Demo/static API transport plus HTTP transport mode.
- Clubs, club profile, membership request, notifications, conversations, training, billing and profile API bindings.
- Deep-link resolver, native Android bridge, native iOS bridge and Flutter navigator.
- Direct club deep-link loading and detail previews for key deep-link target types.
- Android application ID and iOS bundle ID set to `com.airmius.app`.
- Android and iOS icons/splash/launch preparations.
- Store metadata drafts, privacy drafts, review notes and screenshot plan.
- Build/signing runbook.
- Android and iOS CI evidence workflows.
- Android and iOS CI preflight evidence for release configuration and secrets hygiene.
- Release Candidate Gates screen and Release Evidence Center inside the app.

## Scripts prepared

- Web debug:
  - `scripts/run_web_debug.ps1`
- Android debug:
  - `scripts/run_android_debug.ps1`
- Local release prerequisites:
  - `scripts/assert_local_release_prerequisites.ps1`
- Android release-candidate checks:
  - `scripts/release_candidate_checks.ps1`
- Deep-link domain verification:
  - `scripts/check_deep_link_domain.ps1`
- Evidence status summary:
  - `scripts/show_release_evidence_status.ps1`
- Next release steps:
  - `scripts/show_next_release_steps.ps1`
- Next release steps report exporter:
  - `scripts/export_next_release_steps_report.ps1`
- Evidence gate updater:
  - `scripts/update_release_evidence_gate.ps1`
- Evidence report exporter:
  - `scripts/export_release_evidence_report.ps1`
- Evidence bundle packager:
  - `scripts/package_release_evidence_bundle.ps1`
- Evidence bundle content check:
  - `scripts/assert_evidence_bundle_contents.ps1`
- Manual evidence pack generator:
  - `scripts/new_manual_release_evidence_pack.ps1`
- Manual evidence pack checker:
  - `scripts/assert_manual_release_evidence_pack.ps1`
- Logo/theme asset mapping checker:
  - `scripts/assert_logo_theme_assets.ps1`
- Evidence manifest sync:
  - `scripts/sync_release_evidence_manifest.ps1`
- Evidence manifest integrity check:
  - `scripts/assert_release_manifest_integrity.ps1`
- Manual evidence gate status:
  - `scripts/show_manual_evidence_gates.ps1`
- Full release evidence pipeline:
  - `scripts/run_full_release_evidence_pipeline.ps1`
- Release configuration checker:
  - `scripts/assert_release_configuration.ps1`
- Flutter dependency lock checker:
  - `scripts/assert_flutter_dependency_lock.ps1`
- Release version consistency checker:
  - `scripts/assert_release_version_consistency.ps1`
- Release secrets hygiene checker:
  - `scripts/assert_no_release_secrets.ps1`
- Release Go/No-Go checker:
  - `scripts/assert_release_go_no_go.ps1`
- Laravel API smoke helper:
  - `scripts/run_laravel_api_smoke.ps1`
- Membership API smoke helper:
  - `scripts/new_membership_smoke_payload.ps1`
  - `scripts/run_membership_api_smoke.ps1`

## Important runbooks

- Machine-readable evidence manifest:
  - `store_listing/release/release_evidence_manifest.json`
- Manual tester quickstart:
  - `store_listing/release/manual_tester_quickstart.md`
- Build and signing:
  - `store_listing/release/build_and_signing_runbook.md`
- Release evidence template:
  - `store_listing/release/release_evidence_template.md`
- Secure token storage QA:
  - `store_listing/release/secure_token_storage_qa.md`
- Store review account:
  - `store_listing/release/store_review_account_runbook.md`
- Store release notes:
  - `store_listing/release/store_release_notes.md`
- Manual evidence gates:
  - `store_listing/release/manual_evidence_gates.md`
- Manual gate sign-off template:
  - `store_listing/release/manual_gate_signoff_template.md`
- Logo and theme parity QA:
  - `store_listing/release/logo_theme_parity_qa.md`
- Final execution sequence:
  - `store_listing/release/final_execution_sequence.md`
- Final command cheatsheet:
  - `store_listing/release/final_command_cheatsheet.md`
- Windows Android setup:
  - `store_listing/release/windows_android_setup_runbook.md`
- Store submission readiness:
  - `store_listing/release/store_submission_readiness_runbook.md`
- Final RC gate register:
  - `store_listing/release/final_release_candidate_gate_register.md`
- Domain verification:
  - `store_listing/deep_links/domain_verification_release_runbook.md`
- Screenshot capture:
  - `store_listing/screenshots/screenshot_capture_plan.md`
- Localization matrix:
  - `store_listing/localization/localization_release_matrix.md`

## Final 1% execution checklist

1. Run Flutter analyze and save evidence.
2. Run local release prerequisite check and save evidence.
3. Run static logo/theme asset mapping check and save evidence.
4. Run Android release build and save `.aab` plus build logs.
5. Run Android APK build for device smoke testing.
6. Run iOS release/no-codesign CI evidence build.
7. Run signed iOS IPA/TestFlight build with Apple credentials.
8. Deploy `assetlinks.json` and `apple-app-site-association`.
9. Run domain verification script.
10. Run native deep-link QA on Android and iOS.
11. Capture final Android/iOS screenshots.
12. Run logo/theme visual QA for Normal, Dunkel and System.
13. Run real Laravel API smoke tests.
14. Complete legal/privacy approval.
15. Complete localization visual QA, including Arabic RTL.
16. Complete secure token storage QA on Android and iOS.
17. Generate and verify the manual evidence pack.
18. Package final release evidence bundle.
19. Prepare store review account and add credentials only inside Play Console/App Store Connect reviewer notes.
20. Prepare Play Console, App Store Connect and TestFlight release notes.
21. Run release configuration check with production HTTPS API and `-UseHttp`.
22. Run release secrets hygiene check before packaging evidence.
23. Update evidence manifest gates after real proof is collected.
24. Sync local evidence into the manifest where possible.
25. Validate evidence manifest integrity.
26. Run release Go/No-Go check.
27. Mark the Go/No-Go gate passed only after the command succeeds.
28. Export the final evidence report and package the final evidence bundle again after the Go/No-Go gate is marked passed.
29. Show evidence status and next release steps to confirm no hidden blockers remain.

## One-command evidence pipeline

After the operator is ready to execute real checks:

```powershell
cd C:\xampp\htdocs\airmius\mobile\airmius_mobile
.\scripts\run_full_release_evidence_pipeline.ps1 -ApiBaseUrl "https://airmius.com"
```

After all manual gates are updated with direct evidence:

```powershell
cd C:\xampp\htdocs\airmius\mobile\airmius_mobile
.\scripts\run_full_release_evidence_pipeline.ps1 -ApiBaseUrl "https://airmius.com" -RunGoNoGo
```

The pipeline packages the evidence bundle, syncs the manifest again so `release_evidence_bundle` can become passed, and only then runs Go/No-Go when `-RunGoNoGo` is present.

When `-RunGoNoGo` succeeds, the pipeline verifies manual evidence gates, marks `release_go_no_go` as passed, validates the manifest again, exports a fresh report, packages the evidence bundle again and prints the final evidence status plus next release steps so the final ZIP and terminal output reflect the final decision.

## Go / no-go rule

Go is allowed only when all evidence gates are complete and documented.

No-go if any of these are missing:

- Analyze evidence.
- Android release artifact.
- iOS signed/TestFlight evidence.
- iOS no-codesign evidence alone is partial only; signed IPA/TestFlight evidence is still required.
- Domain verification evidence.
- Final screenshots.
- Real API QA.
- Legal/privacy sign-off.
- Localization visual QA.
- Secure token storage QA.
- Release evidence bundle.
- Store review account.
- Store release notes.
- Store submission readiness.
- Manual evidence gates.
- Manual evidence pack.
- Local release prerequisites.
- Logo theme asset mapping.
- Logo/theme parity QA.
- Release configuration check.
- Release secrets hygiene.
- Release Go/No-Go decision.

The Go/No-Go script ignores its own manifest gate during blocker evaluation. This avoids a self-blocking release loop; the full pipeline marks the `release_go_no_go` gate passed only after the command succeeds.

Every evaluated gate must be explicitly `passed`. Unknown, mistyped, pending, partial, failed or blocked status values are treated as release blockers.

## Suggested first command after approval

```powershell
cd C:\xampp\htdocs\airmius\mobile\airmius_mobile
.\scripts\release_candidate_checks.ps1 -ApiBaseUrl "https://airmius.com"
```

This command should only be run when the operator is ready to collect real release evidence.

The command writes local logs and Android artifacts into:

- `release_evidence\flutter-pub-get.log`
- `release_evidence\flutter-analyze.log`
- `release_evidence\android-appbundle-build.log`
- `release_evidence\android-apk-build.log`
- `release_evidence\release-configuration-check.log`
- `release_evidence\release-secrets-hygiene.log`
- `release_evidence\artifacts\app-release.aab`
- `release_evidence\artifacts\app-release.apk`

To inspect the current evidence manifest status:

```powershell
cd C:\xampp\htdocs\airmius\mobile\airmius_mobile
.\scripts\show_release_evidence_status.ps1
```

To inspect the current evidence percentages and next open gates:

```powershell
cd C:\xampp\htdocs\airmius\mobile\airmius_mobile
.\scripts\show_next_release_steps.ps1
```

To export those next steps as a Markdown handoff report:

```powershell
cd C:\xampp\htdocs\airmius\mobile\airmius_mobile
.\scripts\export_next_release_steps_report.ps1
```

To update a gate after real evidence has been collected:

```powershell
cd C:\xampp\htdocs\airmius\mobile\airmius_mobile
.\scripts\update_release_evidence_gate.ps1 -GateId "flutter_analyze" -Status "passed" -Note "Analyze passed in CI artifact airmius-mobile-release-evidence/flutter-analyze.log"
```

To export a readable evidence report from the manifest:

```powershell
cd C:\xampp\htdocs\airmius\mobile\airmius_mobile
.\scripts\export_release_evidence_report.ps1
```

To sync available local evidence logs/artifacts into the manifest:

```powershell
cd C:\xampp\htdocs\airmius\mobile\airmius_mobile
.\scripts\sync_release_evidence_manifest.ps1
```

To package all available release evidence into a single ZIP:

```powershell
cd C:\xampp\htdocs\airmius\mobile\airmius_mobile
.\scripts\package_release_evidence_bundle.ps1
```

Default output:

- `store_listing\release\airmius_release_evidence_bundle.zip`
