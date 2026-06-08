# Apple App Privacy Draft

## Data linked to the user

| Data type | Examples | Purpose |
| --- | --- | --- |
| Contact Info | Email, phone, guardian contact | Account management, membership requests, club communication |
| User Content | Messages, uploaded files, profile content | Messaging, document upload, club/member workflows |
| Identifiers | User ID, push token, session token | Authentication, notifications, security |
| Financial Info | Invoice status, dues/payment method labels | Club contribution visibility and billing workflows |
| Location | Nearby clubs, event locations, map context | Location-based club/event features if enabled |

## Data not necessarily linked to the user

| Data type | Examples | Purpose |
| --- | --- | --- |
| Diagnostics | Crash/error context if later enabled | App stability |
| Usage Data | Feature usage if analytics are enabled | Product improvement |

## Tracking

Initial draft assumes no cross-app tracking and no advertising identifier usage.

Before App Store submission, confirm:

- No third-party tracking SDK is active.
- No IDFA usage is configured.
- Analytics, if enabled, are disclosed accurately.
- Sponsor/ads modules do not perform cross-app tracking in the mobile build.

## App Store privacy questionnaire notes

- Account creation/login is required for member-specific features.
- Some club discovery screens can be public/guest-visible depending on backend configuration.
- Camera/photo permissions are feature-triggered for QR, uploads and documents.
- Location should be optional and only requested when maps/nearby features are used.
- Notifications should be optional and user-configurable.

## Remaining legal/product checks

- Finalize privacy policy URL.
- Finalize data deletion URL or support process.
- Confirm club-admin access to submitted membership data.
- Confirm retention rules for withdrawn membership requests.
- Confirm file/document storage retention and deletion flow.
