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

- Update 2026-10-01: course quality review now has a native screen and paginated
  API with the web action's validation/permission plus verified-account and
  platform-admin 2FA protection. Role management and moderation now support
  their specialist permissions with separately filtered response data.
- Full blog rich-content editing, inline image upload and preview parity have not
  been implemented by this change. The existing native editorial editor remains.
- Existing native commerce/backoffice/platform lists with bounded datasets still
  need a per-list comparison with the web's filtering, pagination and exports.
  Editorial search/pagination and contract search/status/category pagination were
  added on 2026-10-01. This is not complete export parity. Metadata-only editorial
  saves now preserve existing rich HTML and scheduled publication timestamps.
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
- See [2026-10-01 recheck](PARITY_RECHECK_2026-10-01.md) for additional automated
  evidence and explicitly outstanding device/provider checks.

## T33-07 / T33-10 / T33-21 Bounded Follow-up (2026-10-01)

Scope: editorial, platform and backoffice only. Learning, sponsors, calendars
and commerce were not changed in this follow-up.

- **T33-07 specialist access:** `users.assign_roles` opens native role management;
  `moderation.manage` opens moderation and authorizes decisions and appeals.
  Responses exclude unrelated data according to capability. Menu entries, tabs
  and the legacy roles entry point use the same boundaries. Platform-wide actions
  still require `system.manage`. Existing system-manager moderation access and
  the `system.manage` + `user.manage` role-management fallback are retained.
  Protected roles, high-risk permission restrictions, super-admin-only permission
  creation, audit recording and existing two-factor middleware are unchanged.
- **T33-10 data-loss fix, not rich-editor completion:** unchanged `content_text`
  preserves stored HTML and revision content, including formatting/inline images.
  Native saves retain existing publication timestamps. Changed bodies are still
  escaped plain text. Outstanding: structured rich editing, lossless changes to
  rich bodies, inline/cover image upload and rendered draft preview. The web has
  `blogs.content-images.store` and `blogs.preview`; the native editorial API/editor
  still expose neither. No arbitrary HTML input was enabled.
- **T33-21 editorial:** search and previous/next page controls now use the existing
  status/search API, resetting to page one on filter changes. An ID tie-breaker
  makes ordering deterministic for equal update timestamps. Still absent: native
  content-locale filtering/translation creation and web category relationship
  search (`orWhereHas('blogCategory')`). Page sizes remain native 30 versus web 12.
- **T33-21 contracts:** name/vendor/contract-number/account-reference search,
  status/category filters, 20-row pagination and native controls replace the
  100-record ceiling. Contract totals/monthly cost cover the whole authorized
  dataset, independent of filters/pages. Read-only finance access cannot mutate
  contracts. Native default remains `all` versus web `active`.

### Exact Remaining List Work in This Scope

| List | Remaining work / verified web comparison |
| --- | --- |
| User/club subscriptions | Native latest 100 each; no filters or paging. Web user/club search finds assignments by name/email/plan; native assignment selectors stop at 250 without server search. Web user-subscription history also stops at 100. |
| Pending bank transfers | Native and web both stop at 50 without continuation controls: a shared limit. |
| Subscription invoices | Native stops at 100; web paginates 50. Native needs page metadata/controls and invoice document access. |
| Payments | Native stops at 100; web paginates 25. Native totals/revenue derive from that slice and need global aggregates as well as pagination. |
| Billing invoices | Native stops at 100 stored `Invoice` rows; web paginates 25 across membership, subscription, commerce and outfit invoice sources. Unified source coverage, pagination, document actions and global totals remain. |
| Platform legacy users/clubs tabs | Still capped at 50/100 without filters/pages. Dedicated native member/club screens already have server paging; the legacy tabs are not equivalent. |
| Moderation flags/reports/warnings | Both native and web retain 80/80/100 limits. Native lacks the web warning-category filter and flag category payload; open/appeal counts use bounded collections. Neither side has continuation controls. |
| Sports | Both load all rows; native lacks web client-side search and active/inactive filtering. |
| Roles/permissions, badges, gamification, subscription plans | Complete collections, not truncated pages. No server pagination/export contract found in the corresponding web controllers. Gamification health details remain outside this list fix. |
| Exports | No CSV route found for these editorial/platform/backoffice lists, including contracts. The explicit admin commerce CSV route is separate and was not inspected or changed here. Native export/download parity is not claimed. |

Non-contract backoffice summaries still use bounded collections. This follow-up
does not claim full T33-10 or T33-21 completion.

### Follow-up Verification

- `tests/Feature/NativeAdminParityTest.php`: specialist access/cross-scope denial,
  protected roles/permissions, HTML revision preservation, stable editorial pages,
  contract filters/pages/global totals and read-only authorization (5 tests).
- `mobile/airmius_mobile/test/native_admin_parity_test.dart`: pagination/search
  resets, specialist-only tabs and retained two-factor gating at large text
  (5 tests). Targeted Dart analysis reports no issues.
- Existing API suites: `MobilePlatformAdminApiTest`, `MobileAdminBackofficeApiTest`,
  `MobileEditorialSponsorApiTest`. Focused existing Flutter rendering/client
  checks cover editorial/platform/backoffice, including Arabic RTL.
- A broader Flutter name selection also hit the club-finance navigation test
  (`club-scoped finance does not expose trainer sponsor or platform admin`):
  `Admin` is visible for its `subscriptions.manage` fixture. This cross-workspace
  navigation policy was not changed by this follow-up.
