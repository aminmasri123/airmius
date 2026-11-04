# Android 1.0.228 (320) – Trainer notification verification

Date: 2026-11-04

## Diagnosis
Local previous bundle: artifacts/airmius-1.0.227-319.aab, version 1.0.227 / 319 from its build manifest. Compiled ARM64 binary contains admin_trainer_applications. This is not proof that the installed phone uses this exact artifact.
Previous SHA256: 2B044952EC7EC71A40D37440D6AD93EF71BA6DEB1C29945C7E5C2ED93F6B5850

Reproduced the screenshot through the real API client's JSON normalization: /admin/trainer-applications?application_id=4 became https://airmius.com/admin/trainer-applications%3Fapplication_id=4. The resolver therefore treated the query as a path segment and returned unknown.

## Fix
Relative URL conversion now preserves the parsed path, query and fragment separately. Backend notification normalization returns the canonical air mius scheme (airmius://admin/trainer-applications?application_id=4) and does not overwrite it with a generic notification fallback.

## Checks
- Regression reproduced before fix and passed after fix.
- Flutter: 2 tests passed, including actual API normalization, notification parsing, authenticated resolution and destination screen/application ID.
- PHP: 4 tests, 25 assertions passed (specific application, forbidden unauthorized access, role resignation).
- Dart analysis: no errors; 2 style infos in API client.
- Release appbundle build succeeded: 1.0.228 / 320.
- jarsigner verification succeeded (self-signed Android certificate and ZIP streaming warnings emitted).
- Not installed or exercised on the user's phone. No live backend deployment or Play upload performed.

## Upload artifact
artifacts/airmius-1.0.228-320.aab
SHA256: 186C9D5E794CF8AAC6871CC0BD989331AA92F6B2F574F3E78182299262AE2CAE

Backend NotificationRouting change still requires deployment. Trainer resignation also requires the new backend controller and routes from the previous change.
