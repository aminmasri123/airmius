# Club deletion lifecycle

Club owners can request deletion from the Flutter club settings or the dedicated
web club settings page. Club admins and managers cannot request or cancel
deletion on behalf of an owner. The public, unauthenticated explanations are
`/verein-loeschen` and `/konto-loeschen` on the web and the corresponding pages
linked directly from the app's guest portal and its legal hub. Club and
personal-account deletion are separate.
The last club owner must transfer ownership or finish deleting the club before
deleting their personal account. Platform administration keeps its existing,
separately protected super-admin deletion workflow.
The super-admin workflow deletes immediately, but uses the same accounting/SEPA
and active-subscription blockers and operational-data purge as scheduled deletion.
The database purge is transactional; upload cleanup runs after commit and failed
cleanup remains in the retry manifest for the hourly processor.

The ordinary DELETE club endpoint now requires an exact `confirmation` phrase
and a six-digit email code after password verification (account email for
social-only login). It returns HTTP 202 with a deletion status rather than
deleting immediately. German and English confirmation phrases are accepted.
GET `/api/v1/clubs/{club}/deletion` returns the required phrase, deadline and
blockers; DELETE on that path cancels the request. The corresponding
session-authenticated status/cancellation endpoints are also available under
the web club routes.

The grace period is 30 days. Duplicate requests keep the original deadline.
Cancellation remains possible until the processor actually removes the club.
The club stays usable during the grace period. Changing the owner automatically
cancels the previous request on the next processor run. Row locks serialize
processing and cancellation. An additional reminder is sent in the last 7 days.

Owners and active governance assignments in board bodies are notified. Registered
users receive queued in-app notifications and email; external board members with
an email address receive email. Delivery is dispatched after transaction commit.
Only the registered owner (or platform administration) can approve deletion; a
free-text governance position such as president does not grant deletion rights.

An active payment-provider subscription blocks the request and execution.
For club accounting and SEPA history, the owner downloads a finance export,
confirms independent archiving of the required original records, and opens a
support review. The export is a handover package, not a complete or
GoBD-compliant long-term archive. The 30-day request can then start; execution
remains blocked until a platform super-admin records a reasoned approval.
Changes to financial records or missing linked receipts invalidate the export
and approval. The application does not decide a club's tax retention periods or
promise to archive its bookkeeping. Airmius-owned invoices are retained
separately according to their own applicable duties.

Operational club data and owned uploads are erased. Personal accounts, personal
sport history, other clubs, retained platform billing and support records are not
erased. Cascading database dependencies are inventoried before deletion; nullable
workspace references are deleted explicitly to avoid unscoped orphan records.
Stored files are deleted after commit through a persistent retry manifest, with
a check for remaining references to shared paths. Signed deletion recovery
records are written outside the database and checked when restoring a database
backup; they do not automatically erase resurrected data. Backup rotation is
unchanged and must be checked independently. See `docs/BACKUP_RESTORE_RUNBOOK.md`.
Private post media is cleaned from the local disk rather than the public upload disk.

## Deployment

1. Take and verify a production backup; test restoration into an isolated target.
   Review and apply all pending migrations before deploying clients.
2. Configure a private, independently retained deletion recovery ledger and
   signing key. Verify its object retention and the actual Hostinger backup
   lifetime; see the backup runbook. Confirm the finance export and support
   review permissions with isolated test roles.
3. Deploy the server and rebuilt web assets; restart queue workers.
4. Ensure the Laravel scheduler and notification queue workers are running.
   `airmius:process-club-deletions` is scheduled hourly and retries file cleanups.
5. As an unauthenticated visitor, verify both public web pages, their localized
   JSON responses, links from the footer, and the account/club process text.
   Test both direct app guest-portal links and the legal hub on the release build.
6. Release the updated mobile client separately only after its server endpoints
   are live and its guest pages have been checked on a device.

Observed on 15 October 2026: the public production site still returned 404 for
`/verein-loeschen` and showed an older `/konto-loeschen` page. The local tests
and build do not prove that production is updated. Do not mark this release
complete until the live checks in step 5 pass.

Do not run the processor as a smoke test against real data: it executes due
requests. Old mobile clients without typed confirmation receive validation errors
instead of bypassing the grace period. No production migration, Android build or
publication is performed by the implementation tests.

Verification: `ClubDeletionLifecycleTest`, `ClubFinancialClosingExportTest`,
`DeleteAccountTest`, `LegalPagesTest`, `LocalizedLegalGuestPagesTest`, deletion
recovery/restore tests, Flutter `club_deletion_test.dart` and the guest legal-hub
widget test, static Flutter analysis, and a Vite build. Production activation
and legal/AVV review are separate release gates.
