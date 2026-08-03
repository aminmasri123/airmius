# Airmius Mobile Build and Signing Runbook

This runbook defines the Android and iOS release build path for Airmius Mobile.

## Release mode inputs

- Production API base URL:
  - `AIRMIUS_API_BASE_URL=https://airmius.com`
- HTTP transport enabled:
  - `AIRMIUS_USE_HTTP=true`
- Android application ID:
  - `com.airmius.app`
- iOS bundle ID:
  - `com.airmius.app`
- App version:
  - `1.0.32+51` for the current release candidate.

## Android prerequisites

- Android SDK command-line tools installed.
- Android licenses accepted.
- Release keystore created and stored outside source control.
- `android/key.properties` created from `android/key.properties.example`.
- Release Gradle configuration refuses to fall back to the debug key when `key.properties` is missing.
- Play App Signing SHA-256 fingerprint copied for App Links.
- Final `assetlinks.json` deployed to `https://app.airmius.com/.well-known/assetlinks.json`.

## Android build commands

```powershell
flutter pub get
flutter analyze
flutter build appbundle --release --dart-define=AIRMIUS_API_BASE_URL=https://airmius.com --dart-define=AIRMIUS_USE_HTTP=true
flutter build apk --release --dart-define=AIRMIUS_API_BASE_URL=https://airmius.com --dart-define=AIRMIUS_USE_HTTP=true
```

Expected outputs:

- `build/app/outputs/bundle/release/app-release.aab`
- `build/app/outputs/flutter-apk/app-release.apk`

## Android release checks

- App launches with Airmius splash and correct dark logo.
- Login uses production/staging Laravel API depending on release channel.
- `airmius://clubs/26` opens the native app.
- `https://app.airmius.com/clubs/26` opens the native app after App Links verification.
- Permissions match Play Data Safety draft.
- App icon and round/adaptive icons display correctly.
- No debug banner.
- Version and build number match release notes.

## iOS prerequisites

- Apple Developer account active.
- Bundle ID `com.airmius.app` exists in Apple Developer portal.
- Associated Domains capability enabled.
- Associated domain includes `applinks:app.airmius.com`.
- Final `apple-app-site-association` deployed to `https://app.airmius.com/.well-known/apple-app-site-association`.
- App Store Connect app record created.
- TestFlight internal testing group ready.

## iOS build commands

```bash
flutter pub get
flutter analyze
flutter build ipa --release --dart-define=AIRMIUS_API_BASE_URL=https://airmius.com --dart-define=AIRMIUS_USE_HTTP=true
```

CI release-equivalent build without Apple signing:

```bash
flutter build ios --release --no-codesign --dart-define=AIRMIUS_API_BASE_URL=https://airmius.com --dart-define=AIRMIUS_USE_HTTP=true
```

Expected output:

- `build/ios/ipa/*.ipa`
- CI no-codesign artifact: `Runner.app.zip`

## iOS release checks

- App launches with Airmius launch screen.
- URL scheme `airmius://clubs/26` opens the native app.
- Universal Link `https://app.airmius.com/clubs/26` opens the native app after domain verification.
- Privacy usage descriptions are present.
- Non-exempt encryption declaration is set.
- Screenshots match App Store Connect device requirements.
- TestFlight upload processes without entitlement/signing errors.
- CI no-codesign build passes before signed archive attempt.

## Shared pre-submission gates

- Flutter analyze passes.
- Android release build passes.
- iOS release build passes.
- Store screenshots captured from the signed or release-equivalent build.
- Deep link domain files are deployed and verified.
- Review account works against the selected backend.
- Privacy labels match real data collection and processing.
- Secure token storage decision is finalized before public release.

## Release artifact handoff

- Android:
  - Upload `.aab` to Play Console internal testing first.
  - Keep `.apk` for device smoke testing only.
- iOS:
  - Upload `.ipa` through Xcode/Transporter or CI.
  - Submit to TestFlight internal testing before App Review.

## Known blockers until actually executed

- Build output cannot be considered green until `flutter analyze` and release builds are executed.
- Store signing cannot be considered final until real keystore and Apple signing profiles are used.
- Deep Links cannot be considered verified until domain files are live and OS verification succeeds.
- Screenshots cannot be considered final until captured from the final release-equivalent app.
