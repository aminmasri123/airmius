# Airmius Mobile Privacy Submission Matrix

## Store label readiness

| Area | Draft status | Remaining work |
| --- | --- | --- |
| Personal data | Prepared | Confirm final API fields and profile editor fields |
| Membership data | Prepared | Confirm club-specific required fields and withdrawal retention |
| Documents/files | Prepared | Confirm upload storage, deletion and admin access rules |
| Payments/invoices | Prepared | Confirm informational vs transactional payment behavior |
| Notifications/messages | Prepared | Confirm push provider and message retention |
| Location | Conditional | Decide whether location permission ships in first release |
| Analytics/tracking | Unknown | Confirm SDK list before submission |
| Account deletion/export | Needs confirmation | Confirm production backend routes and support flow |

## Recommended first store build posture

- Keep payments informational unless a full payment processor is connected and disclosed.
- Keep location optional and request it only inside map/nearby flows.
- Avoid tracking SDKs in the first release unless absolutely necessary.
- Use a staging/review account with realistic club data.
- Ensure membership withdrawal and document visibility are clearly described.

## Backend confirmations needed

- Production API base URL.
- Privacy policy URL.
- Terms/support URL.
- Data deletion process.
- Push provider.
- File storage provider.
- Payment/accounting provider.
- Analytics/crash reporting provider.
