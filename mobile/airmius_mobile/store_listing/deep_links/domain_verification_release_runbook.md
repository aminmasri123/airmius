# Airmius Deep Link Domain Verification Runbook

This runbook prepares verified Android App Links and iOS Universal Links for `app.airmius.com`.

## Production domain targets

- Android App Links file:
  - `https://app.airmius.com/.well-known/assetlinks.json`
- iOS Universal Links file:
  - `https://app.airmius.com/.well-known/apple-app-site-association`
- Both files must be served over HTTPS.
- Both files must be served without redirects.
- Both files must use valid JSON.
- The iOS file should be served as `application/json` or `application/pkcs7-mime`.

## Android values to fill before release

- Package name: `com.airmius.app`
- SHA-256 certificate fingerprint:
  - Use the final Play/App Signing fingerprint, not only the local debug keystore.
  - If Play App Signing is enabled, copy the fingerprint from Play Console.
- Template file:
  - `store_listing/deep_links/assetlinks.template.json`

## iOS values to fill before release

- Bundle ID: `com.airmius.app`
- Apple Team ID:
  - Copy from Apple Developer account membership details.
- App ID format:
  - `{TEAM_ID}.com.airmius.app`
- Template file:
  - `store_listing/deep_links/apple-app-site-association.template.json`

## Required link routes

- `/clubs/{id}`
- `/membership-applications/{id}`
- `/events/{id}`
- `/messages/{id}`
- `/notifications/{id}`
- `/profile/{section}`

## Deployment checklist

1. Fill `assetlinks.template.json` with the final SHA-256 fingerprint.
2. Publish it as `.well-known/assetlinks.json`.
3. Fill `apple-app-site-association.template.json` with the final Apple Team ID.
4. Publish it as `.well-known/apple-app-site-association`.
5. Confirm both files are reachable without authentication.
6. Confirm there are no redirects from `https://app.airmius.com/.well-known/...`.
7. Confirm Android manifest includes `android:autoVerify="true"` for `app.airmius.com`.
8. Confirm iOS associated domains include `applinks:app.airmius.com` before archive.
9. Confirm Flutter receives links through `com.airmius.app/deep_links`.
10. Confirm `airmius://clubs/26` and `https://app.airmius.com/clubs/26` both open the same native club profile.

## QA commands

Android manual verification:

```powershell
adb shell pm get-app-links com.airmius.app
adb shell am start -a android.intent.action.VIEW -d "https://app.airmius.com/clubs/26" com.airmius.app
adb shell am start -a android.intent.action.VIEW -d "airmius://clubs/26" com.airmius.app
```

iOS manual verification:

```bash
xcrun simctl openurl booted "https://app.airmius.com/clubs/26"
xcrun simctl openurl booted "airmius://clubs/26"
```

HTTP verification:

```bash
curl -i https://app.airmius.com/.well-known/assetlinks.json
curl -i https://app.airmius.com/.well-known/apple-app-site-association
```

Windows helper:

```powershell
.\scripts\check_deep_link_domain.ps1 -HostName "app.airmius.com"
```

## Expected app behavior

- Club link opens the direct club profile.
- Membership, event, message, notification and profile links open the deep-link arrival screen.
- Unknown links open the safe fallback screen.
- Auth-required links show a protected context and can route after login/session restore.

## Remaining decisions

- Confirm final production domain: `app.airmius.com`.
- Confirm Apple Team ID.
- Confirm Play App Signing SHA-256 fingerprint.
- Confirm whether staging domain should also be verified.
- Confirm whether links should support additional localized paths later.
