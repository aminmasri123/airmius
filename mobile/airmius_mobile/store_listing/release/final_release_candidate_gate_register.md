# Airmius Mobile Final Release Candidate Gate Register

This register defines the remaining proof required before Airmius Mobile can be called release-ready.

## Current state

- Product work is substantially prepared.
- Native Android and iOS project identity is prepared.
- Airmius branding, app icons, splash/launch screens and logo variants are prepared.
- Flutter API client, repositories, auth state, persistent preferences and persistent token store are prepared.
- Deep links, native bridges and domain verification runbooks are prepared.
- Store listing, privacy drafts, screenshot plan, review runbook and build/signing runbook are prepared.

## Hard gates before claiming completion

1. Flutter static analysis
   - Required evidence: `flutter analyze` exits successfully.
- Current status: Not executed in this thread.
- Evidence template:
  - `store_listing/release/release_evidence_template.md`
- CI artifact:
  - `airmius-mobile-release-evidence/flutter-analyze.log`

2. Android release build
   - Required evidence: signed or release-equivalent `.aab` exists.
- Current status: Runbook prepared, build not executed.
- Evidence command helper:
  - `scripts/release_candidate_checks.ps1`
- CI artifact:
  - `airmius-mobile-release-evidence/artifacts/app-release.aab`
  - `airmius-mobile-release-evidence/artifacts/app-release.apk`

3. iOS release build
   - Required evidence: archive or `.ipa` exists and is accepted by TestFlight tooling.
- Current status: Runbook prepared, build not executed.
- Evidence command:
  - `flutter build ipa --release --dart-define=AIRMIUS_API_BASE_URL=https://app.airmius.com --dart-define=AIRMIUS_USE_HTTP=true`
- CI artifact:
  - `airmius-mobile-ios-release-evidence/ios-release-build-no-codesign.log`
  - `airmius-mobile-ios-release-evidence/artifacts/Runner.app.zip`
- Note:
  - Signed IPA/TestFlight evidence still requires Apple signing credentials and provisioning.

4. Android signing
   - Required evidence: final keystore or Play signing setup confirmed.
   - Current status: signing structure prepared, final credential not provided.

5. iOS signing
   - Required evidence: Apple Developer Team ID, provisioning and capabilities confirmed.
   - Current status: bundle and runbook prepared, final account/signing not provided.

6. Domain verification
   - Required evidence: live `.well-known/assetlinks.json` and `apple-app-site-association` return valid responses without redirects.
   - Current status: templates and runbook prepared, files not deployed.

7. Store screenshots
   - Required evidence: final Android and iOS screenshots captured from release-equivalent app.
   - Current status: capture plan prepared, screenshots not captured.

8. Store privacy/legal confirmation
   - Required evidence: privacy labels match real Laravel API behavior and legal owner approves.
   - Current status: drafts prepared, legal confirmation not provided.

9. Real API QA
   - Required evidence: production or staging Laravel API supports expected mobile endpoints and auth behavior.
   - Current status: Flutter contract and demo responses prepared; real endpoint QA not executed.

10. Localization QA
   - Required evidence: DE/EN/FR/AR core flows checked visually, including Arabic RTL.
   - Current status: language system and matrix prepared; full visual QA not executed.

11. Logo and theme parity QA
   - Required evidence: Normal theme shows the dark Airmius wordmark on light backgrounds, Dunkel theme shows the white Airmius wordmark on dark backgrounds, and System follows device brightness after app restart.
   - Current status: theme-aware logo selection prepared; final rendered screenshot evidence not captured.

12. Store submission readiness
   - Required evidence: Play Console, App Store Connect and TestFlight readiness checklist approved after final Go/No-Go, final screenshots, privacy labels, review account notes and release notes.
   - Current status: runbook prepared, final store-console sign-off not executed.

## Go / no-go rule

The app can be called release-ready only when all hard gates above have direct evidence.

Preparation work alone is not enough for release readiness. Build logs, artifacts, deployed domain files, captured screenshots and QA notes are required.

## Recommended final order

1. Fix local Flutter toolchain issues.
2. Run manual smoke test with `store_listing/release/manual_tester_quickstart.md`.
3. Run `flutter analyze`.
4. Run Android release build.
5. Run iOS release build on macOS with Apple signing.
6. Deploy domain verification files.
7. Verify deep links on Android and iOS.
8. Capture screenshots.
9. Complete privacy/legal review.
10. Run final smoke test.
11. Submit to internal testing tracks before public review.
