# Airmius Mobile Real Device Smoke Test Runbook

This runbook closes the manual real-device gate for Android and iOS. It must be executed on real devices with safe test data before MVP release.

## Local status on 2026-07-17

- Checked on Ubuntu 24.04.2 with Flutter 3.44.5.
- `flutter devices` only found `Linux (desktop)`.
- `flutter doctor -v` reported no Android SDK.
- iOS real-device testing is not possible on this Ubuntu host; it requires macOS, Xcode and Apple signing/TestFlight context.

Result: gate remains open until Android and iOS device evidence is collected.

## Required devices

- Android physical device with USB debugging enabled.
- iPhone with TestFlight or local Xcode deployment.
- Safe review/test user with no private production data.
- API environment with stable HTTPS base URL, for example `https://app.airmius.com`.

## Build and install

Android debug smoke:

```bash
flutter devices
flutter run -d <android-device-id> \
  --dart-define=AIRMIUS_API_BASE_URL=https://app.airmius.com \
  --dart-define=AIRMIUS_USE_HTTP=true
```

Android release-equivalent smoke:

```bash
flutter build apk --release \
  --dart-define=AIRMIUS_API_BASE_URL=https://app.airmius.com \
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
  --dart-define=AIRMIUS_API_BASE_URL=https://app.airmius.com \
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

The validator requires both platforms to contain `Result: PASS`, no open checklist items and checked lines for login, push, upload, deep links and private-data review.

## Test matrix

| Area | Android expected result | iOS expected result | Evidence |
| --- | --- | --- | --- |
| Login | Fresh install opens login, test user logs in, restart restores session, logout clears session after restart. | Same. | Screen recording or screenshots plus device/app version. |
| Push opt-in | Permission prompt appears, opt-in registers device token, test notification arrives, tapping opens the intended target, logout removes token. | Same. | Screenshot of permission, notification, target screen and backend/device-token note. |
| Upload | Feed/file upload opens native picker, valid image/PDF uploads, progress/success state is visible, failed upload can retry. | Same. | Screenshot/recording and uploaded test file reference. |
| Deep link: club | `airmius://clubs/<id>` or verified HTTPS link opens club target. | Same via Safari/Notes/TestFlight link. | Screenshot before/after link open. |
| Deep link: event | `airmius://events/<id>` or verified HTTPS link opens event target/fallback preview. | Same. | Screenshot before/after link open. |
| Deep link: chat | `airmius://messages/<id>` opens chat/deep-link arrival safely for authorized user. | Same. | Screenshot before/after link open. |
| Deep link: invitation | Invitation link opens join/application target or safe fallback when invalid/expired. | Same. | Screenshot before/after link open. |

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

- Android and iOS both pass Login, Push, Upload and Deep Link checks.
- Evidence contains no private user data, secrets, tokens or payment data.
- Failures are linked to an issue with device, OS version, build number and reproduction steps.
- The release evidence manifest gate is only marked passed after both platforms have evidence.

## Evidence template

```text
Date:
Tester:
API base URL:
Build number:

Android device / OS:
Android result: PASS / FAIL
Android evidence paths:
Android notes:

iOS device / OS:
iOS result: PASS / FAIL
iOS evidence paths:
iOS notes:

Confirmed areas:
- Login:
- Push:
- Upload:
- Club deep link:
- Event deep link:
- Chat deep link:
- Invitation deep link:

Private-data check: PASS / FAIL
Release owner sign-off:
```
