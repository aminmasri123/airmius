# Airmius Mobile Secure Token Storage QA

This runbook proves that the mobile app stores, restores and clears auth sessions through platform secure storage.

## Scope

- Android: `flutter_secure_storage` with Android SDK 23+ secure ciphers.
- iOS: Keychain-backed secure storage through `flutter_secure_storage`.
- Fallback migration: legacy persistent token store can be read once and then migrated into secure storage.
- Logout: session must be removed from secure storage and fallback storage.

## Source files

- `lib/core/airmius_secure_token_store.dart`
- `lib/core/airmius_service_container.dart`
- `lib/core/airmius_persistent_token_store.dart`
- `android/app/build.gradle.kts`
- `android/app/src/main/AndroidManifest.xml`

## Required setup

- Run with release-equivalent API configuration.
- Use a real test account that is safe for screenshots and logs.
- Do not capture or publish raw auth tokens.
- Android device/emulator must run Android 6.0/API 23 or newer.
- iOS device/simulator must use a release-equivalent build where Keychain access works.

## Android QA

1. Install a clean app build.
2. Log in with the review/test account.
3. Fully close the app.
4. Reopen the app.
5. Confirm the user session is restored without re-entering credentials.
6. Log out.
7. Fully close the app again.
8. Reopen the app.
9. Confirm the app returns to the login screen.
10. Confirm Android backup is disabled for the app manifest.

Evidence to attach:

- Build variant and device name.
- Android version/API level.
- Login restore screenshot after restart.
- Logout restart screenshot.
- Note confirming no token value was exposed in logs/screenshots.

## iOS QA

1. Install a clean app build.
2. Log in with the review/test account.
3. Fully terminate the app.
4. Reopen the app.
5. Confirm the user session is restored.
6. Log out.
7. Fully terminate and reopen the app.
8. Confirm the app returns to the login screen.

Evidence to attach:

- Build variant and device/simulator name.
- iOS version.
- Login restore screenshot after restart.
- Logout restart screenshot.
- Note confirming no token value was exposed in logs/screenshots.

## Migration QA

Use this only when a previous app version stored a test session in the legacy preference store.

1. Install the old build with the legacy persistent token store.
2. Log in with the test account.
3. Upgrade to the secure-storage build without deleting app data.
4. Open the app.
5. Confirm the session is restored.
6. Log out.
7. Restart the app.
8. Confirm the session is cleared.

Evidence to attach:

- Old build identifier.
- New build identifier.
- Migration result note.
- Logout-clear result note.

## Pass criteria

- Android session restore works.
- Android logout clears session.
- iOS session restore works.
- iOS logout clears session.
- Migration path works or is explicitly marked not applicable for first release.
- No raw tokens appear in screenshots, logs or evidence files.

## Fail criteria

- User is unexpectedly logged out after restart.
- User remains logged in after logout and restart.
- Token value appears in visible logs or screenshots.
- Android backup remains enabled for encrypted session material.
