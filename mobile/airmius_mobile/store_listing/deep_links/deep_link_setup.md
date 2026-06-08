# Airmius Mobile Deep Link Setup

## Native schemes

- Android and iOS support `airmius://`.
- Android is prepared for `https://app.airmius.com`.
- iOS URL scheme is prepared in `Info.plist`.

## Android App Links

Publish the Android template as:

`https://app.airmius.com/.well-known/assetlinks.json`

Before publishing:

- Replace `REPLACE_WITH_RELEASE_KEY_SHA256_FINGERPRINT` with the SHA-256 fingerprint of the release signing certificate.
- Confirm the final production package name is `com.airmius.app`.
- Confirm the domain is final and HTTPS is valid.
- Confirm the JSON is served with `application/json`.

## iOS Universal Links

Publish the iOS template as:

`https://app.airmius.com/.well-known/apple-app-site-association`

Before publishing:

- Replace `REPLACE_WITH_APPLE_TEAM_ID` with the Apple Developer Team ID.
- Confirm the final bundle identifier is `com.airmius.app`.
- Serve the file without `.json` extension in production.
- Confirm the file is served with `application/json`.
- Add Associated Domains in Xcode when real Apple signing is configured.

## Planned deep-link routes

- `/clubs/{id}` opens a club profile.
- `/membership-applications/{id}` opens request status.
- `/events/{id}` opens an event or training detail.
- `/messages/{id}` opens a conversation.
- `/notifications/{id}` opens a notification detail.
- `/profile/{section}` opens a profile/settings area.

## Remaining mobile implementation

- Wire native incoming-link events to the prepared `AirmiusDeepLinkResolver`.
- Add authenticated/guest fallback routing.
- Add notification payload mapping.
- Add QA cases for cold start, warm app, expired session and missing permissions.
