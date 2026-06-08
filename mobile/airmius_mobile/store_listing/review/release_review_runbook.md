# Airmius Mobile Release Review Runbook

This runbook prepares Airmius Mobile for Play Store, App Store and internal QA review.

## Review account

- Provide a staging or demo account with the role `player`.
- Recommended demo credentials:
  - Email: `zbb.bop.it@gmail.com`
  - Role: player
  - Club context: ZBB
- The account must be able to view clubs, send a membership request, withdraw it, open notifications, open messages, view events and view invoices.
- If admin-only screens are submitted in screenshots, provide an additional `club_admin` review account.

## Reviewer notes

- Airmius is a club, team and membership-management app.
- The app contains user-generated club data, messages, files and payment-related records.
- The current screenshots should use demo/staging data only.
- If payment features are reviewed without live payment, explain that billing flows are staging/demo and no real payment is captured.
- If location permissions are requested, explain they are for sport maps, event routes and travel/carpool context.
- If camera/files permissions are requested, explain they are for QR check-in, document uploads and membership attachments.

## Pre-submission checklist

- Android application ID is `com.airmius.app`.
- iOS bundle ID is `com.airmius.app`.
- App icon uses the Airmius mark.
- Splash/launch screen uses Airmius dark branding.
- Privacy URL is available and matches store metadata.
- Terms/support URL is available.
- Deep link domain files are uploaded before enabling verified links.
- Store listing text matches available product behavior.
- Screenshots are captured from the Flutter app, not the web app.
- Screenshots do not expose real personal data.
- Build artifacts are signed with release credentials.
- Flutter analyze and release builds are green before submission.

## Functional smoke routes

1. Login with the review account.
2. Change language and theme, restart app, confirm persistence.
3. Open clubs, search ZBB, open club profile.
4. Send membership request, confirm status, withdraw request.
5. Open notifications and messages.
6. Open events/training.
7. Open documents/file upload manager.
8. Open member finance/invoices.
9. Open `airmius://clubs/26` and confirm the concrete club profile opens.
10. Open membership, event, message, notification and profile deep links and confirm arrival screens.
11. Sign out and confirm guest login screen returns.

## Known remaining release gates

- Replace file-backed token persistence with secure storage for final mobile release.
- Run Flutter analyze and release builds on Android/iOS.
- Confirm Android SDK licenses and release keystore.
- Confirm Apple Developer signing, capabilities and TestFlight upload.
- Capture final device screenshots.
- Confirm legal/privacy labels against the real Laravel API and data processing.
