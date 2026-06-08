# Airmius Mobile Store Submission Readiness Runbook

Use this runbook after technical release evidence is collected and before submitting to Google Play, App Store Connect or TestFlight.

## Scope

This runbook covers final store-console readiness. It does not replace build evidence, legal approval, screenshots, API QA or final Go/No-Go.

## Hard prerequisites

- Final Go/No-Go is passed.
- Android release artifact exists.
- iOS signed IPA/TestFlight evidence exists.
- Final screenshots are captured.
- Privacy/legal labels are approved.
- Store review account is prepared.
- Release notes are approved.
- No private user data, tokens, passwords or payment data appear in screenshots or evidence files.

## Google Play checklist

- App name and short description match approved store listing.
- Full description is approved.
- App icon and feature graphics are final.
- Phone screenshots are final.
- Data Safety form matches real Laravel API behavior.
- Privacy policy URL is live.
- Support/contact URL or email is live.
- App content rating is complete.
- Target audience and ads declaration are complete.
- Production release uses the approved Android App Bundle.
- Play App Signing setup is confirmed.
- Release notes do not contain secrets or unverified claims.
- Review account instructions are entered only in Play Console reviewer notes.

## App Store Connect checklist

- App name, subtitle and description are approved.
- Bundle ID is `com.airmius.app`.
- iOS screenshots are final.
- App Privacy answers match real data handling.
- Privacy policy URL is live.
- Support URL is live.
- Export compliance is completed.
- Age rating is completed.
- TestFlight/signed IPA evidence is available.
- Release notes do not contain secrets or unverified claims.
- Review account instructions are entered only in App Review notes.

## Reviewer account

Use the dedicated review/demo account only.

Do not put passwords in repository files, screenshots, generated reports or evidence bundles.

Reviewer notes should explain:

- Login path.
- Core review route.
- Club search and membership request path.
- That data is demo/review-safe.
- Any feature requiring backend/staging state.

## Final evidence to attach

- `store_listing/release/generated_release_evidence_report.md`
- `store_listing/release/generated_next_release_steps.md`
- `store_listing/release/airmius_release_evidence_bundle.zip`
- Final Android/iOS screenshots.
- Android `.aab` evidence.
- iOS signed/TestFlight evidence.
- Legal/privacy approval notes.

## No-go conditions

Do not submit if any of these are true:

- Any evaluated manifest gate is not `passed`.
- Final Go/No-Go did not run.
- Store screenshots contain private data.
- Review credentials are stored in repository/evidence files.
- Privacy labels are not approved.
- iOS has only no-codesign evidence and no signed/TestFlight evidence.
- Android has no release-equivalent AAB evidence.
