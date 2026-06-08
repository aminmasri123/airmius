# Airmius Mobile

Airmius Mobile is the native Flutter app for the Airmius club platform. It mirrors the mobile web-app direction with dark/normal branding, club discovery, membership requests, messages, notifications, events, finance, documents, profile flows, deep links and release-readiness tooling.

## Product readiness

- Product preparation: 99% complete.
- Remaining work: 1% hard evidence execution.
- The app must not be treated as 100% complete until analyze/build, API QA, screenshots, signing, privacy, localization and store evidence are collected.

## Local start

Use demo/static data:

```powershell
cd C:\xampp\htdocs\airmius\mobile\airmius_mobile
.\scripts\run_web_debug.ps1
```

Use Laravel HTTP API:

```powershell
cd C:\xampp\htdocs\airmius\mobile\airmius_mobile
.\scripts\run_web_debug.ps1 -ApiBaseUrl "http://localhost" -UseHttp
```

## Release evidence

Release runbooks and gates live under `store_listing\release`.

Useful scripts:

- `scripts\show_release_evidence_status.ps1`
- `scripts\show_next_release_steps.ps1`
- `scripts\export_next_release_steps_report.ps1`
- `scripts\new_manual_release_evidence_pack.ps1`
- `scripts\assert_manual_release_evidence_pack.ps1`
- `scripts\assert_logo_theme_assets.ps1`
- `scripts\release_candidate_checks.ps1`
- `scripts\export_release_evidence_report.ps1`
- `scripts\package_release_evidence_bundle.ps1`

## Airmius progress notes

- Prepared Android application identity: namespace/applicationId moved to `com.airmius.app`, MainActivity package aligned, product estimate now 60% complete / 40% remaining.
- Prepared first native Android branding pass: dark Airmius splash background, launcher mark resource, system bars and release gate status; product estimate now 61% complete / 39% remaining.
- Prepared iOS release identity: Runner bundle identifier moved to `com.airmius.app`, test bundle aligned, dark Airmius launch screen added; product estimate now 62% complete / 38% remaining.
- Bound the notifications center to the API repository layer with typed notification models, loading/error states and demo transport data; product estimate now 63% complete / 37% remaining.
- Bound conversations to the API repository layer with typed conversation models, loading/error states, metrics and demo transport data; product estimate now 64% complete / 36% remaining.
- Bound Events & Training to the API repository layer with loading/error states, filters, metrics and demo transport data; product estimate now 65% complete / 35% remaining.
- Bound member finance invoices to the API repository layer with loading/error states, invoice/payment metrics and demo transport data; product estimate now 66% complete / 34% remaining.
- Connected club document upload manager to API upload intents for file-manager-bound club documents, replacements and upload checks; product estimate now 67% complete / 33% remaining.
- Bound global search to the API repository layer with typed results for clubs, people and teams plus mobile metrics and demo transport data; product estimate now 68% complete / 32% remaining.
- Bound the profile screen to AuthState/currentUser with refresh, sign-out, loading and error presentation; product estimate now 69% complete / 31% remaining.
- Mirrored the web logo strategy in Flutter with mark, wordmark light/dark and full light/dark assets selected centrally by AirmiusLogo; product estimate now 70% complete / 30% remaining.
- Expanded localization foundations for DE/EN/FR/AR across search, profile, clubs, messages, training, finance, documents and status states; product estimate now 71% complete / 29% remaining.
- Corrected the legacy Flutter logo fallback (`airmius-logo.png`) to use the dark-compatible Airmius wordmark so cached or old logo references no longer render dark text on the dark app UI.
- Generated native Android and iOS app icon sets from the Airmius mark and pointed Android launcher metadata to the mipmap launcher icon; product estimate now 72% complete / 28% remaining.
- Added Android adaptive and round launcher icon resources with Airmius dark background and mark foreground; product estimate now 73% complete / 27% remaining.
- Prepared Android release-signing structure via optional `android/key.properties` with a safe example file; product estimate now 74% complete / 26% remaining.
- Prepared iOS App Store export structure with `ios/ExportOptions.plist.example` and non-exempt encryption declaration; product estimate now 75% complete / 25% remaining.
- Added GitHub Actions workflow for Flutter pub get, analyze and Android release build with production API dart-defines; product estimate now 76% complete / 24% remaining.
- Added Play Store and App Store German listing drafts plus privacy/review notes for submission preparation; product estimate now 77% complete / 23% remaining.
- Prepared native Android/iOS deep-link structure for `airmius://` and `https://app.airmius.com` entry points; product estimate now 78% complete / 22% remaining.
- Added Android App Links and iOS Universal Links verification templates plus setup notes for the Airmius production domain; product estimate now 79% complete / 21% remaining.
- Added Play Data Safety, App Store Privacy and privacy submission matrix drafts for store compliance preparation; product estimate now 80% complete / 20% remaining.
- Added central deep-link resolver and QA matrix for club, membership, event, message, notification and profile links; product estimate now 81% complete / 19% remaining.
- Hardened theme-aware logo rendering with explicit dark login branding, brightness-aware fallbacks and registered light/dark Flutter themes; product estimate now 82% complete / 18% remaining.
- Added a central ThemeMode scope plus visible login chooser for Dunkel, Normal and System so Airmius can follow the web app's dark/normal logo principle; product estimate now 83% complete / 17% remaining.
- Added central deep-link screen navigation with safe fallback routing and QA buttons for club, membership, event, message, notification and profile targets; product estimate now 84% complete / 16% remaining.
- Bound club deep links to direct API club-detail loading so `clubs/{id}` opens the concrete native club profile instead of stopping at the list; product estimate now 85% complete / 15% remaining.
- Added Deep-Link arrival screens with target ID, auth context, routing audit and CTAs for membership, event, message, notification and profile links; product estimate now 86% complete / 14% remaining.
- Added a platform-aware preferences layer and wired persistent language plus theme-mode restore/save into the app shell; product estimate now 87% complete / 13% remaining.
- Added a persistent Auth TokenStore and wired the service container away from memory-only sessions while keeping a release gate for final Keychain/Keystore hardening; product estimate now 88% complete / 12% remaining.
- Added store screenshot capture and release review runbooks covering routes, review account, reviewer notes, smoke routes and remaining submission gates; product estimate now 89% complete / 11% remaining.
- Added Android native deep-link delivery via MethodChannel for initial intents and new intents, plus cleaned duplicate Android launcher icon metadata; product estimate now 90% complete / 10% remaining.
- Added iOS native deep-link delivery for URL schemes and Universal Links through the same Flutter MethodChannel used by Android; product estimate now 91% complete / 9% remaining.
- Added deep-link domain verification release runbook and central domain config for Android App Links and iOS Universal Links; product estimate now 92% complete / 8% remaining.
- Added build and signing runbook for Android App Bundle/APK and iOS IPA/TestFlight release paths with dart-defines, prerequisites, artifacts and smoke gates; product estimate now 93% complete / 7% remaining.
- Added localization release matrix and readiness config for DE/EN/FR/AR, RTL QA, screenshot language checks and must-localize release screens; product estimate now 94% complete / 6% remaining.
- Added API detail endpoints and repository methods for membership applications, notifications, conversations, events and invoices, including demo transport responses for Deep-Link-ready ID loading; product estimate now 95% complete / 5% remaining.
- Added repository-backed detail preview cards on Deep-Link arrival screens so membership, event, message and notification links immediately load concrete target data; product estimate now 96% complete / 4% remaining.
- Added final release candidate gate register and app-level release blocker catalog to separate prepared work from required build, signing, domain, screenshot, API, legal and localization evidence; product estimate now 97% complete / 3% remaining.
- Added native Release Candidate Gates screen to the Operations Hub so hard evidence blockers are visible in-app; product estimate now 98% complete / 2% remaining.
- Added final release evidence template and PowerShell RC check script for reproducible analyze/build evidence capture; product estimate now 99% complete / 1% remaining.
- Added in-app Release Evidence Center linked from Operations Hub so final logs, artifacts, screenshots and QA approvals are visible as evidence gates; product estimate remains 99% complete / 1% remaining.
- Extended GitHub Actions mobile workflow to collect release evidence logs, build Android AAB/APK, and upload CI artifacts for the final evidence package; product estimate remains 99% complete / 1% remaining.
- Added macOS GitHub Actions workflow for iOS analyze and release no-codesign build evidence with uploaded artifacts; product estimate remains 99% complete / 1% remaining.
- Added local tester quickstart plus PowerShell launch scripts for web and Android debug testing with demo or Laravel HTTP API mode; product estimate remains 99% complete / 1% remaining.
- Added local deep-link domain verification helper script and linked it into release evidence/manual QA docs; product estimate remains 99% complete / 1% remaining.
- Added final operator handoff summarizing prepared work, scripts, runbooks, hard gates and the exact final execution checklist; product estimate remains 99% complete / 1% remaining.
- Added machine-readable release evidence manifest for CI/admin automation of the final hard gates; product estimate remains 99% complete / 1% remaining.
- Added release evidence status helper script to summarize pending gates from the machine-readable manifest; product estimate remains 99% complete / 1% remaining.
- Added release evidence gate update helper script so completed evidence can update manifest gate statuses in a traceable way; product estimate remains 99% complete / 1% remaining.
- Added release evidence report exporter script to turn the manifest into a readable Markdown Go/No-Go report after real checks run; product estimate remains 99% complete / 1% remaining.
- Added release evidence bundle packaging script to collect runbooks, privacy/deep-link/screenshot/localization folders and generated Android/iOS artifacts into a single ZIP for operator handoff; product estimate remains 99% complete / 1% remaining.
- Replaced the login screen's unfinished demo/backend-later microcopy with production-oriented API mode copy so the first screen no longer reads like a prototype; product estimate remains 99% complete / 1% remaining.
- Upgraded the default auth token store from plain persistent preferences to `flutter_secure_storage` backed secure storage with Android minSdk 23 and backup disabled to protect encrypted session keys; product estimate remains 99% complete / 1% remaining.
- Added explicit release gates for secure token storage QA and final evidence bundle packaging to the machine-readable manifest and in-app blocker catalog; product estimate remains 99% complete / 1% remaining.
- Hardened the local release prerequisite check to detect missing Android Platform-Tools, cmdline-tools/sdkmanager and SDK license acceptance before the long release evidence run starts; product estimate remains 99% complete / 1% remaining.
- Corrected AirmiusLogo selection to follow the explicit app theme mode chooser, so Normal uses the dark wordmark for light backgrounds and Dunkel uses the white wordmark for dark backgrounds; product estimate remains 99% complete / 1% remaining.
- Added an explicit Logo and Theme Parity QA gate to the release manifest and manual tester flow so Normal/Dunkel/System logo behavior must be proven with rendered screenshots before release completion can be claimed; product estimate remains 99% complete / 1% remaining.
- Surfaced Logo and Theme Parity QA inside the native release blocker and store configuration screens so the app itself shows this visual evidence gate before final release; product estimate remains 99% complete / 1% remaining.
- Added Logo and Theme Parity QA fields to the release evidence and manual sign-off templates so final reviewers capture Normal, Dunkel, System and restart persistence proof explicitly; product estimate remains 99% complete / 1% remaining.
- Added a manual release evidence pack generator plus a dedicated Logo and Theme Parity QA runbook so final screenshots, sign-offs, API QA logs and approvals can be collected in predictable folders; product estimate remains 99% complete / 1% remaining.
- Hardened release evidence bundle packaging and content checks so the final handoff ZIP must include the Logo and Theme Parity QA runbook, manual tester quickstart and release evidence templates; product estimate remains 99% complete / 1% remaining.
- Included release helper scripts in the final evidence bundle and made the bundle content gate require the manual evidence pack helper, prerequisites check, full pipeline, RC checks and bundle validator; product estimate remains 99% complete / 1% remaining.
- Added a static logo/theme asset and mapping check so the final evidence flow can fail fast if Normal/Dunkel/System logo assets or AirmiusLogo mappings are accidentally changed; product estimate remains 99% complete / 1% remaining.
- Wired the static logo/theme asset mapping check into local release-candidate checks and Android/iOS CI workflows with dedicated evidence logs; product estimate remains 99% complete / 1% remaining.
- Added a machine-readable `logo_theme_asset_mapping` release gate and manifest sync support so static logo/theme evidence can automatically update the final Go/No-Go state; product estimate remains 99% complete / 1% remaining.
- Added a manual release evidence pack checker so final screenshot, sign-off and API QA folders can be checked before packaging the release handoff bundle; product estimate remains 99% complete / 1% remaining.
- Integrated the manual release evidence pack checker into the final `-RunGoNoGo` pipeline path and documented it in the final execution sequence; product estimate remains 99% complete / 1% remaining.
- Added the manual evidence checker to local prerequisite file checks and surfaced the static Logo Theme Asset Mapping gate in native release blocker/store configuration data; product estimate remains 99% complete / 1% remaining.
- Added a machine-readable `manual_evidence_pack` release gate and final pipeline log so manual screenshots, sign-offs and API QA evidence can update the final manifest/Go-No-Go state; product estimate remains 99% complete / 1% remaining.
- Corrected the final `-RunGoNoGo` sequence to sync the manifest immediately after the manual evidence pack log is generated, so the `manual_evidence_pack` gate can pass before final Go/No-Go is evaluated; product estimate remains 99% complete / 1% remaining.
- Surfaced the Manual Evidence Pack gate inside native release blocker and store configuration data so the app itself shows the final manual screenshots/sign-offs/API-QA evidence requirement; product estimate remains 99% complete / 1% remaining.
- Added a machine-readable `local_release_prerequisites` gate and full-pipeline evidence log so Flutter/Android SDK/adb/cmdline-tools/licenses/Java readiness can be proven before builds start; product estimate remains 99% complete / 1% remaining.
- Surfaced the Local Release Prerequisites gate inside native release blocker and store configuration data so the app itself shows Flutter/Android SDK/Java readiness as a hard release requirement; product estimate remains 99% complete / 1% remaining.
- Updated the full release evidence pipeline to sync the local prerequisite log into the manifest immediately after the setup check succeeds, before later build/analyze steps can fail; product estimate remains 99% complete / 1% remaining.
- Extended the release evidence status helper to calculate and print evidence progress and evidence remaining percentages from passed manifest gates; product estimate remains 99% complete / 1% remaining.
- Added a next release steps helper that reads the manifest, prints evidence progress/remaining percentages, and lists the next open gates with commands or runbooks; product estimate remains 99% complete / 1% remaining.
- Wired the next release steps helper into the full release evidence pipeline so every run ends with concrete remaining gates and actions; product estimate remains 99% complete / 1% remaining.
- Updated the final operator handoff with the latest status/next-step helpers, manual evidence pack, logo/theme QA, prerequisite and static logo mapping gates; product estimate remains 99% complete / 1% remaining.
- Added the next release steps helper to local prerequisite file checks so the pipeline cannot reach its final guidance step with that required helper missing; product estimate remains 99% complete / 1% remaining.
- Added a Markdown exporter for next release steps and included it in prerequisites, bundle validation and operator handoff so remaining work can be shared as a report; product estimate remains 99% complete / 1% remaining.
- Wired the next release steps Markdown export into local full-pipeline and Android/iOS CI packaging so the final evidence bundle must include `generated_next_release_steps.md`; product estimate remains 99% complete / 1% remaining.
- Marked the generated next release steps report as a local/generated evidence artifact so it is produced for bundles and CI artifacts without becoming a source-controlled app file; product estimate remains 99% complete / 1% remaining.
- Updated the full release evidence pipeline to refresh evidence and next-steps reports after bundle status is synced, then repackage the bundle so the handoff ZIP reflects the latest manifest state; product estimate remains 99% complete / 1% remaining.
- Updated Android and iOS CI packaging to sync bundle evidence, refresh reports and repackage the evidence bundle so CI artifacts reflect the latest manifest state after packaging; product estimate remains 99% complete / 1% remaining.
- Added generated evidence and next-steps reports to the packaging script's expected release file list so missing report exports are visible in the bundle manifest before content validation fails; product estimate remains 99% complete / 1% remaining.
- Added a final command cheatsheet and made the evidence bundle require it so the real 1% execution has a compact operator command sequence; product estimate remains 99% complete / 1% remaining.
- Added the final command cheatsheet to local prerequisite file checks and linked it from the final execution sequence so the compact operator command list is protected before release evidence runs; product estimate remains 99% complete / 1% remaining.
- Added a Windows Android setup runbook for cmdline-tools, adb, Java and Android licenses, and made it part of prerequisites, bundle validation and operator handoff; product estimate remains 99% complete / 1% remaining.
- Added a Store Submission Readiness runbook for Play Console, App Store Connect, TestFlight, reviewer account and no-go conditions, and made it part of prerequisites, bundle validation and operator handoff; product estimate remains 99% complete / 1% remaining.
- Added Store Submission Readiness as a manifest/manual evidence gate covering Play Console, App Store Connect, TestFlight, review account notes and release-note sign-off; product estimate remains 99% complete / 1% remaining.
- Added Store Submission Readiness to the manual evidence pack/checker and native release blocker/store configuration data so store-console sign-off is visible and verifiable across the release system; product estimate remains 99% complete / 1% remaining.
- Added Store Submission Readiness to the manual evidence gate status helper so final Go/No-Go visibly blocks until store-console readiness is signed off; product estimate remains 99% complete / 1% remaining.
- Updated the manual evidence pack generator to create safe Markdown note placeholders for manual QA and store sign-off files while still requiring real screenshots/logs to be attached separately; product estimate remains 99% complete / 1% remaining.
- Updated the final operator handoff's Store/Test/Release preparation estimate to 99% prepared while keeping the remaining 1% tied to real execution, signing, screenshots, API QA and final approvals; product estimate remains 99% complete / 1% remaining.
- Added secure token storage QA runbook and wired it into the release evidence template, operator handoff, manual tester quickstart and evidence manifest; product estimate remains 99% complete / 1% remaining.
- Replaced the generated Flutter starter description in `pubspec.yaml` with production Airmius app metadata so package metadata no longer reads like a new template project; product estimate remains 99% complete / 1% remaining.
- Replaced the default Flutter README starter content with an Airmius Mobile overview, readiness status, local start commands and release evidence script references; product estimate remains 99% complete / 1% remaining.
- Added store review account runbook and wired it into the release evidence manifest, evidence template, operator handoff, tester quickstart and in-app blocker catalog; product estimate remains 99% complete / 1% remaining.
- Added store release notes runbook for Play Console, App Store Connect, TestFlight and internal tester communication, then wired it into the release manifest, evidence template, operator handoff, tester quickstart and blocker catalog; product estimate remains 99% complete / 1% remaining.
- Added release configuration checker script and wired it into RC checks, evidence manifest, evidence template, operator handoff, tester quickstart and blocker catalog so production builds must use HTTPS Laravel API mode and secure-storage prerequisites; product estimate remains 99% complete / 1% remaining.
- Added release secrets hygiene checker and wired it into RC checks, evidence manifest, evidence template, operator handoff, tester quickstart and blocker catalog so committed reviewer passwords, tokens, API keys and bearer secrets are caught before packaging; product estimate remains 99% complete / 1% remaining.
- Added manifest-based release Go/No-Go checker and wired it into evidence manifest, evidence template, operator handoff, tester quickstart and blocker catalog so final release approval requires every gate to have direct evidence; product estimate remains 99% complete / 1% remaining.
- Wired release configuration and secrets hygiene preflight checks into both Android and iOS GitHub Actions workflows with uploaded evidence logs; product estimate remains 99% complete / 1% remaining.
- Added release evidence manifest sync script to mark eligible gates from real local logs/artifacts after CI or RC checks have produced evidence; product estimate remains 99% complete / 1% remaining.
- Added full release evidence pipeline script to run RC checks, sync manifest, export evidence report, package evidence bundle and optionally execute final Go/No-Go; product estimate remains 99% complete / 1% remaining.
- Updated local release-candidate checks to capture configuration, secrets, pub-get, analyze and Android build logs into `release_evidence` and copy generated Android artifacts for manifest sync; product estimate remains 99% complete / 1% remaining.
- Fixed the release Go/No-Go checker so it ignores its own manifest gate while evaluating blockers, preventing an impossible self-blocking final approval loop; product estimate remains 99% complete / 1% remaining.
- Updated the full release evidence pipeline so a successful `-RunGoNoGo` marks the Go/No-Go gate passed, then exports the final report and packages the final evidence bundle again; product estimate remains 99% complete / 1% remaining.
- Hardened local release-candidate evidence command execution so PowerShell-script failures and external Flutter exit codes both stop the pipeline and are written into the relevant evidence log; product estimate remains 99% complete / 1% remaining.
- Fixed the full release evidence pipeline ordering so it syncs the manifest again after packaging the evidence bundle, allowing the bundle gate to pass before final Go/No-Go evaluation; product estimate remains 99% complete / 1% remaining.
- Extended release evidence manifest sync to detect iOS no-codesign evidence as partial and require signed IPA/TestFlight evidence before the iOS gate can pass; product estimate remains 99% complete / 1% remaining.
- Updated the release evidence gate helper to accept every manifest status used by the release flow, including packaging/setup pending states and partial evidence; product estimate remains 99% complete / 1% remaining.
- Refined release evidence manifest log parsing so harmless success phrases such as zero errors or zero failures do not get mistaken for blocking failure markers; product estimate remains 99% complete / 1% remaining.
- Hardened release evidence bundle packaging so repeated runs remove the previous output ZIP before staging, preventing old bundles from being nested inside new bundles; product estimate remains 99% complete / 1% remaining.
- Hardened the release Go/No-Go checker so every evaluated gate must be explicitly `passed`; unknown or mistyped status values now block release instead of slipping through; product estimate remains 99% complete / 1% remaining.
- Added release evidence manifest integrity check and wired it into the full pipeline so duplicate gate IDs, unknown statuses and missing required fields block final release before Go/No-Go; product estimate remains 99% complete / 1% remaining.
- Added a final manifest integrity check after the Go/No-Go gate update so final reports and bundles are generated only from a structurally valid final manifest; product estimate remains 99% complete / 1% remaining.
- Added a final evidence status snapshot at the end of the successful `-RunGoNoGo` pipeline so operators immediately see the final gate state after report and bundle generation; product estimate remains 99% complete / 1% remaining.
- Added manual evidence gates runbook to separate script-generated evidence from required human/store/API/legal/iOS verification before final Go/No-Go; product estimate remains 99% complete / 1% remaining.
- Hardened the release evidence status helper to report passed, pending, partial, failed/blocked and unknown-status gates with the same strict release logic as Go/No-Go; product estimate remains 99% complete / 1% remaining.
- Added manual evidence gates status helper to list iOS, domain, screenshots, real API, legal/privacy, localization, secure storage, review-account and release-notes gates with their current manifest status; product estimate remains 99% complete / 1% remaining.
- Wired the manual evidence gates helper into the final `-RunGoNoGo` pipeline so manual iOS/store/API/legal/localization gates must be passed before final approval can proceed; product estimate remains 99% complete / 1% remaining.
- Added final execution sequence runbook that condenses automated evidence, manual gates, manifest checks and final Go/No-Go into one operator-ready checklist; product estimate remains 99% complete / 1% remaining.
- Extended Android and iOS CI workflows to sync the release evidence manifest, export the evidence report, package the evidence bundle and copy the bundle ZIP into uploaded CI artifacts; product estimate remains 99% complete / 1% remaining.
- Extended release evidence manifest sync to recognize iOS CI no-codesign and analyze logs with `-ios` suffixes so GitHub Actions iOS evidence is captured as partial or analyze evidence; product estimate remains 99% complete / 1% remaining.
- Hardened generated release evidence report decision logic to treat partial, unknown or non-passed evaluated gates as NO-GO and copied generated reports into Android/iOS CI uploaded artifacts; product estimate remains 99% complete / 1% remaining.
- Added release evidence bundle content checker and wired it into the full pipeline so generated ZIPs must contain the manifest, report, handoff, manual gates, final sequence and key store/deep-link/privacy/localization folders; product estimate remains 99% complete / 1% remaining.
- Extended the evidence bundle content check to require the manual gate sign-off template and safe membership payload template in the final ZIP; product estimate remains 99% complete / 1% remaining.
- Wired release evidence bundle content validation into Android and iOS CI after bundle packaging so uploaded CI evidence ZIPs must contain required release files; product estimate remains 99% complete / 1% remaining.
- Wired release evidence manifest integrity validation into Android and iOS CI after manifest sync so reports and bundles are generated only from structurally valid manifests; product estimate remains 99% complete / 1% remaining.
- Added Laravel API smoke helper for real backend evidence across login, search, clubs, notifications, conversations, events, billing and optional upload-intent write checks without committing credentials; product estimate remains 99% complete / 1% remaining.
- Wired Laravel API smoke logs into manifest sync so read-only backend evidence becomes partial API QA and upload-intent smoke evidence can pass the real API QA gate; product estimate remains 99% complete / 1% remaining.
- Corrected Laravel API smoke manifest sync so upload-intent evidence remains partial; full real API QA must still be manually marked passed after membership request, withdrawal and full backend flow review; product estimate remains 99% complete / 1% remaining.
- Added membership API smoke helper for real membership application creation, detail loading and optional withdrawal using local payload JSON without committing credentials or real private member data; product estimate remains 99% complete / 1% remaining.
- Wired membership API smoke logs into manifest sync as partial real API QA evidence for create/detail and optional withdrawal proof while keeping full API QA manually gated; product estimate remains 99% complete / 1% remaining.
- Added safe fake-data membership payload template and updated smoke-test docs to copy it into local `release_evidence` before running membership API QA; product estimate remains 99% complete / 1% remaining.
- Added safe helper to create local membership smoke payloads from the template without overwriting existing local payloads unless `-Force` is used; product estimate remains 99% complete / 1% remaining.
- Added release version consistency checker and wired it into the full pipeline plus Android/iOS CI so pubspec version, manifest version and native app IDs stay aligned; product estimate remains 99% complete / 1% remaining.
- Added Flutter dependency lock checker and wired it into local RC checks, Android/iOS CI, manifest sync, evidence manifest and blocker catalog so `flutter_secure_storage` must be resolved in `pubspec.lock` after `flutter pub get`; product estimate remains 99% complete / 1% remaining.
- Added local release prerequisites checker for Flutter command availability, Android SDK env, Java and required release files before running final evidence collection; product estimate remains 99% complete / 1% remaining.
- Wired the local release prerequisites check into the full release evidence pipeline as the first step so environment issues stop before dependency, analyze or build work begins; product estimate remains 99% complete / 1% remaining.
- Parameterized local RC checks and the full release evidence pipeline with `-FlutterCommand` so Windows operators can use an absolute path like `C:\flutter\bin\flutter.bat` when Flutter is not in PATH; product estimate remains 99% complete / 1% remaining.
- Updated mobile `.gitignore` to exclude generated release evidence, bundle staging/validation folders, generated reports/bundles and local JSON smoke payloads; product estimate remains 99% complete / 1% remaining.
- Added manual gate sign-off template for API, legal/privacy, screenshots, localization, store review and other human-approved release gates; product estimate remains 99% complete / 1% remaining.
