# Google Play Data Safety Draft

## App purpose

Airmius is a sports club platform for club discovery, membership requests, club communication, events, training, documents, invoices and profile management.

## Data collected or processed

| Category | Examples | Purpose | Shared with third parties | Required |
| --- | --- | --- | --- | --- |
| Personal info | Name, email, role, profile details | Account, profile, membership workflows | No, except infrastructure/processors | Yes for account use |
| Contact info | Email, phone, guardian contact, emergency contact | Membership requests, club administration, safety | No, except infrastructure/processors | Depends on club form |
| User content | Messages, documents, uploads, profile text | Communication, files, membership documents | No, except selected club/admin recipients | Depends on feature |
| Financial info | Invoice status, contribution labels, payment method labels | Club dues visibility and billing workflows | No, except payment/accounting providers if enabled | Depends on club |
| Location | Event locations, nearby clubs, sports map context | Club/event discovery, maps, carpool/facility features | No, except map/location providers if enabled | Optional |
| Photos and videos | Profile/club/document uploads | Membership documents, chat attachments, profile media | No, except storage processors | Optional |
| App activity | Notifications, conversations, events, feature usage | App functionality, unread counts, auditability | No, except infrastructure/processors | Yes for app operation |
| Device or other IDs | Push token, session/device token | Security, notifications, session handling | No, except push provider | Depends on feature |

## Security practices

- Data is transmitted over HTTPS in production.
- Authentication uses token-based API access.
- User-facing sensitive actions should be audited by backend systems.
- Store release must confirm secure token storage implementation before submission.

## User controls

- Profile and visibility settings.
- Notification preferences.
- Membership request withdrawal.
- Club document visibility configured by club admins.
- Account export/deletion flows should be confirmed before final store submission.

## Remaining confirmation before Play submission

- Confirm whether analytics/tracking SDKs are included.
- Confirm whether payment processing is informational or transactional in the first build.
- Confirm whether location permission is shipped in the first build.
- Confirm final privacy policy URL.
- Confirm support email and data deletion contact.
