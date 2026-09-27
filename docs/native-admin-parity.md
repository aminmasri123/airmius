# Native Admin Coverage

Status: partial implementation, not a declaration of complete Web/Flutter parity.

The Admin sidebar destination now opens `AdminWorkspaceScreen`. Every destination
in that menu is a native Flutter screen backed by APIs; it does not open the web
admin or require the commerce dashboard to load first.

## Implemented in this change

- Club administration: server-side search, verification filters, pagination,
  owner and subscription details, status selection with confirmation, and the
  existing super-admin deletion workflow with exact club-name confirmation.
- Club verification: replaced the sample-data screen with the API-backed review
  screen, including official number and review notes. Both legacy `pending` and
  `pending_verification` show review actions.
- Member administration: search, status filters, pagination, profile editing,
  role assignment, account suspension, creation with manual/generated passwords,
  optional credential email, deletion, warnings and inactivity notices.
- Trainer applications: paginated/filterable listing and review with notes,
  using the same approve/reject controller as the web.
- Operations and product analytics: native views using the existing web services,
  including the analytics privacy suppression contract.
- Media administration: native visual-source editing and multipart image uploads
  for login slides and configured global visuals; shared web validation.
- Direct navigation to existing native commerce, subscriptions, billing,
  contracts, outfits, editorial, sponsors, support, mail, provider costs,
  platform settings, roles, moderation, sports, badges and gamification screens.
- Explicit self-deletion denial in the member controller, including super admins.

## Known Remaining Parity Work

- The web course quality-review action (`admin.learning.courses.quality.update`)
  still has no corresponding native admin action.
- Full blog rich-content editing, inline image upload and preview parity have not
  been implemented by this change. The existing native editorial editor remains.
- Granular specialist role access to role management and moderation still differs:
  the existing native platform screen/API requires `system.manage`, while the web
  exposes narrower permission-specific areas.
- Existing native commerce/backoffice/platform lists with bounded datasets still
  need a per-list comparison with the web's filtering, pagination and exports.
- Operations currently links to native workspaces, not to every individual case
  detail. Product analytics currently shows metrics and suppression, not every
  explanatory web section.
- Native media uploads need on-device verification. Automated checks cover API
  authorization/validation and widget rendering, not the OS file picker.

## Verification

- `MobilePlatformAdminApiTest` covers new member/media/club/trainer/analytics
  endpoints, validation, status transitions, self-verification and access denial.
- `AdminClubManagementTest` checks the existing web management contract.
- Focused Flutter widget tests cover the commerce-independent menu, confirmation
  cancellation, API-backed screens at large text scale and platform administration.
- No Android build, release upload, deployment or production data mutation was
  performed for this implementation.
