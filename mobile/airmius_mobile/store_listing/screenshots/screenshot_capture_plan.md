# Airmius Mobile Screenshot Capture Plan

This plan defines the screenshot set for Play Store, App Store and internal release QA.

## Visual direction

- Use the native Flutter app in the same dark Airmius look as the mobile web app.
- Keep the Airmius logo visible on auth, shell, dashboard or hero screens.
- Use realistic demo data: ZBB, Airmius Running Club, membership requests, notifications, training and payments.
- Avoid exposing private real user data in store screenshots.
- Prefer German screenshots first, then repeat the same route set for English if the listing is localized.

## Required screenshot routes

1. Login and language/theme
   - Shows Airmius logo, login card, language chooser and dark/normal/system design chooser.
   - Purpose: proves brand, multilingual readiness and onboarding clarity.

2. Home / Operations shell
   - Shows bottom navigation, Airmius panels, profile chip and app structure.
   - Purpose: proves native mobile shell and web-app visual parity.

3. Clubs and direct club profile
   - Shows search, club cards and ZBB club profile with membership CTA.
   - Purpose: proves club discovery and joining flow.

4. Membership application
   - Shows dynamic membership form sections, personal data, contact, address, payment and documents.
   - Purpose: proves the core user-to-club request workflow.

5. Notifications and messages
   - Shows notification center and conversation center with unread states.
   - Purpose: proves communication and club-admin awareness.

6. Events and training
   - Shows training/event list, status chips and event-related actions.
   - Purpose: proves sports and team utility.

7. Documents and club file manager
   - Shows policy documents, upload intent and file-manager destination.
   - Purpose: proves Datenschutz, rules, uploads and club document management.

8. Finance and invoices
   - Shows member billing, invoice cards, open/paid/overdue states.
   - Purpose: proves payments and contribution workflows.

9. Deep link arrival
   - Shows recognized target, auth gate, routing audit and CTA.
   - Purpose: proves app links, QR, push and mail routing readiness.

10. Profile and settings
   - Shows user profile, language/theme persistence, sign-out and account actions.
   - Purpose: proves self-service and account safety.

## Android capture set

- Phone portrait: 1080 x 1920 or device-native equivalent.
- Tablet optional: 1600 x 2560 if Play listing targets tablets.
- Minimum: 8 screenshots.
- Recommended: 10 screenshots matching the route list above.

## iOS capture set

- iPhone 6.7 inch: use the same 10 route screenshots.
- iPhone 6.5 inch fallback if App Store Connect requests it.
- iPad optional only if iPad support is enabled for release.

## QA before capture

- Run a clean app start with demo or staging API.
- Confirm no compile/runtime errors in the target flows.
- Confirm dark logo is readable on dark backgrounds.
- Confirm language/theme selections persist after restart.
- Confirm club deep link `airmius://clubs/26` opens the direct club profile.
- Confirm membership request, event, message, notification and profile links show the deep-link arrival screen.
- Confirm no real addresses, IBANs, access tokens or private user data appear.

## Screenshot naming

- `android-phone-01-login.png`
- `android-phone-02-home.png`
- `android-phone-03-clubs.png`
- `android-phone-04-membership-application.png`
- `android-phone-05-notifications-messages.png`
- `android-phone-06-events-training.png`
- `android-phone-07-documents.png`
- `android-phone-08-finance.png`
- `android-phone-09-deep-link.png`
- `android-phone-10-profile-settings.png`

Repeat with `ios-iphone-` prefix for App Store screenshots.
