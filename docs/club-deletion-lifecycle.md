# Club deletion lifecycle

Club owners can request deletion from the Flutter club cockpit or club list and
the web club cockpit/profile. Club admins and managers cannot request or cancel
deletion on behalf of an owner. Platform administration keeps its existing,
separately protected super-admin deletion workflow.
The super-admin workflow deletes immediately, but uses the same accounting/SEPA
and active-subscription blockers and operational-data purge as scheduled deletion.
The database purge is transactional; upload cleanup runs after commit and failed
cleanup remains in the retry manifest for the hourly processor.

The ordinary DELETE club endpoint now requires `confirmation` and returns HTTP
202 with a deletion status, rather than deleting immediately. German and English
confirmation phrases are accepted. GET `/api/v1/clubs/{club}/deletion` returns
the required phrase, deadline and blockers; DELETE on that path cancels the request.
The corresponding session-authenticated status/cancellation endpoints are also
available under the web club routes.

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

Existing SEPA history, accounting records (invoices, payments, bank transactions,
finance entries), and active payment-provider subscriptions block automatic
deletion. Blockers are checked when requesting and again before execution. They
require support review or contract termination; this feature does not decide
retention periods or terminate payment-provider subscriptions.

Operational club data and owned uploads are erased. Personal accounts, personal
sport history, other clubs, retained platform billing and support records are not
erased. Cascading database dependencies are inventoried before deletion; nullable
workspace references are deleted explicitly to avoid unscoped orphan records.
Stored files are deleted after commit through a persistent retry manifest, with
a check for remaining references to shared paths. Backup rotation is unchanged.
Private post media is cleaned from the local disk rather than the public upload disk.

## Deployment

1. Apply `2026_09_27_000106_add_club_deletion_lifecycle.php` before deploying clients.
2. Deploy the server and rebuilt web assets; restart queue workers.
3. Ensure the Laravel scheduler and notification queue workers are running.
   `airmius:process-club-deletions` is scheduled hourly and retries file cleanups.
4. Release the updated mobile client separately.

Do not run the processor as a smoke test against real data: it executes due
requests. Old mobile clients without typed confirmation receive validation errors
instead of bypassing the grace period. No production migration, Android build or
publication is performed by the implementation tests.

Verification: `ClubDeletionLifecycleTest`, club permissions/isolation/profile and
admin tests, Flutter `club_deletion_test.dart`, static Flutter analysis, and Vite
build to an isolated temporary output directory.
