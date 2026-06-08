# Airmius Mobile Store Review Account Runbook

This runbook prepares the review/test account information required for Play Console and App Store Connect review.

## Purpose

Store reviewers must be able to open the app, log in, inspect safe demo content and test core flows without accessing real private member data.

## Review account requirements

- Use a dedicated review account.
- Do not use a real member, club admin or staff account.
- Do not expose real private health, payment, address or identity data.
- Keep the account active for the full review window.
- Keep credentials stable until both stores finish review.
- Make the account read-safe where possible.
- Seed the account with realistic but non-private demo data.

## Required account data

- E-mail:
- Password:
- Role:
- Linked test club:
- Linked test team:
- Membership request test state:
- Notification examples:
- Conversation examples:
- Event/training examples:
- Invoice/payment examples:
- Document examples:

## Reviewer notes

Use this text as the basis for Play Console and App Store Connect review notes:

```text
Airmius is a club and team management app. Please use the provided review account to inspect club discovery, membership request flows, notifications, messages, events, documents, invoices and profile settings.

The account contains safe demo data only. No real member, health, payment or private identity data is included. Some features depend on the configured Laravel API environment and may show prepared demo/test data for review.
```

## Core review path

1. Open the app.
2. Confirm the Airmius logo is readable in dark mode.
3. Log in with the review account.
4. Open Clubs.
5. Open the linked test club.
6. Open membership request flow.
7. Open notifications.
8. Open messages.
9. Open events/training.
10. Open documents.
11. Open finance/invoices.
12. Open profile/settings.
13. Log out.

## Evidence to attach

- Review account owner.
- Review account e-mail.
- Confirmation that the password was entered in the store console, not committed into the repo.
- Screenshot of successful login.
- Screenshot of safe club profile.
- Note confirming no real private data is visible.
- Note confirming the account is active until review completion.

## Pass criteria

- Review account logs in successfully.
- Core review path is accessible.
- Demo data is safe and realistic.
- No real private data is visible.
- Credentials are not committed to repository files.

## Fail criteria

- Account cannot log in.
- Account is disabled before review finishes.
- Real private data is visible.
- Password is stored in repo, screenshots or public logs.
