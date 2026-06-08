# Airmius Mobile Store Review Notes

## Purpose

Airmius is a club and sports team platform. The mobile app mirrors the Airmius mobile web experience and focuses on club discovery, membership requests, messaging, notifications, events, billing visibility, files and profile management.

## Data categories to review before submission

- Account data: name, email, role, profile details.
- Club membership data: application status, membership type, club relation.
- Contact data: email, phone and optional guardian/emergency data where enabled by a club.
- Documents and files: club policy documents, uploaded membership files, consent documents.
- Financial data: invoice status, contribution information and payment method labels.
- Messages and notifications: inbox items, conversations, unread counters.
- Location-related data: optional nearby clubs, sports locations, events and carpool/facility features.
- Media permissions: camera/photo access for QR codes, uploads, profile or club documents.

## Permissions

- Camera: QR codes, membership cards, uploads and proofs.
- Photo library/files: attachments, club documents, membership documents and media.
- Location: sports map, nearby clubs, event locations and carpool/facility features.
- Notifications: membership request updates, club messages, events, invoices and security notices.

## Review account

Prepare a non-production demo account before submission.

- Email: review@airmius.com
- Role: member/player with at least one club request and one visible club.
- Notes: The app can run in demo transport mode, but store review should use a real staging API when available.

## Remaining review tasks

- Confirm final privacy policy URL.
- Confirm support URL and support email.
- Confirm production API base URL.
- Compare Play Data Safety and App Store Privacy drafts against final backend and SDK list.
- Confirm deep-link domains and publish Android `assetlinks.json`.
- Confirm iOS associated domains and publish `apple-app-site-association`.
- Replace deep-link template placeholders with real release certificate and Apple Team ID.
- Confirm whether location is active in the first store build.
- Confirm whether payments are informational only or transactional in the first store build.
- Generate final Android and iOS screenshots.
- Run Flutter analyze and release builds before submission.
