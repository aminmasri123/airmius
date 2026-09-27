# Airmius Mobile Store Release Notes

Use this file to prepare release notes for Play Console, App Store Connect, TestFlight and internal release communication.

## Candidate 155

- Version **1.0.70+155** brings the completed club-platform checklist pass into the mobile release candidate. Club context, governance, communications, reports, public pages, automation, data protection and operating guidance are aligned with the latest server work.
- Signed AAB: `release_evidence/artifacts/airmius-1.0.70-155-release.aab`; SHA-256 `1e54393c9c5537c8503311c2ed85a9bba7c0569a4f02e6bc7e8e8d9e026e22e3`. Flutter analysis passed and the Android app bundle build passed; `jarsigner` reported `jar verified`. The full Flutter test suite is not green yet: 426 tests ran with 11 failures in existing membership, guardian and file-manager regression tests, so this candidate should be treated as a build artifact for upload/testing rather than a fully QA-cleared release.
- German Play note: „Die Vereinsplattform wurde umfassend aktualisiert: klarere Verwaltung, bessere Kommunikation, Berichte, Datenschutz- und Betriebsabläufe sowie stabilere Vereinskontexte.“
- English Play note: “The club platform has been broadly updated with clearer administration, better communication, reports, privacy and operations flows, and more stable club context handling.”

## Candidate 154

- Version **1.0.69+154** makes daily club work faster: the cockpit is shorter, finance and management areas are compact, quick actions are configurable, and the next event plus unread announcements use live data. Navigation is role-focused, workspace selection is simpler, and global search now includes permitted invoices.
- Signed AAB: `release_evidence/artifacts/airmius-1.0.69-154-release.aab`; SHA-256 `4407651e23c381784382ab5ccc1c56cba4b23ed5a7f7e5a6b9bde79115662a44`. Flutter analysis and all 340 tests passed; the Android app bundle build passed and `jarsigner` reported `jar verified`.
- Published to Google Play internal testing as release 121 on 2026-09-23. Play Console reports version 154 (1.0.69) available to internal testers.
- German Play note: „Vereinsverwaltung ist jetzt auf den Alltag fokussiert: kompakteres Cockpit, klare Prüf- und Finanzhinweise, frei wählbare Schnellaktionen, echte nächste Termine und Mitteilungen sowie eine rollenbezogene Navigation.“
- English Play note: “Club management now focuses on daily work with a compact dashboard, clearer review and finance guidance, customizable quick actions, live upcoming events and announcements, and role-focused navigation.”

## Candidate 153

- Version **1.0.68+153** completes the final 12-point club usability pass. Role actions are visibly active, fixed club context survives filter resets, event and team creation begin calmly, empty announcements present one clear action, and team rosters, finance cards and established-club setup are more compact. Reports now show real income, expenses and result for the selected annual period.
- Signed AAB: `release_evidence/artifacts/airmius-1.0.68-153-release.aab`; SHA-256 `c2435c1eedfc6a75e3631b343b13c1d96c12245a6f51ba32053bd3c4b0054c49`. Flutter analysis and all 339 tests passed; the Android app bundle build passed and `jarsigner` reported `jar verified`.
- Published to Google Play internal testing as release 120 on 2026-09-22. Play Console reports version 153 (1.0.68) available to internal testers.
- German Play note: „Vereinsverwaltung reagiert jetzt klarer: aktive Rollenaktionen, geschützte Vereinsfilter, ruhigere Event- und Teamformulare, kompaktere Finanzen und Kader sowie aussagekräftigere Jahreswerte.“
- English Play note: “Club management is clearer with active role actions, protected club filters, calmer event and team forms, compact finances and rosters, and more meaningful annual figures.”

## Candidate 152

- Version **1.0.67+152** completes the 12-point club usability follow-up. Finance tabs fit narrow screens, club sport names and event examples are clearer, unavailable role actions and duplicate shortcuts are hidden, and finance wording is easier to understand. Review status, reports, file information and announcement empty states now provide concise guidance.
- Signed AAB: `release_evidence/artifacts/airmius-1.0.67-152-release.aab`; SHA-256 `662e888c33d70fe8bae5e7d146bf709c2fdb59156b09fe1bad9e7d29a1e7bbe5`. Flutter analysis and all 339 tests passed; the Android app bundle build passed and `jarsigner` reported `jar verified`.
- Published to Google Play internal testing as release 119 on 2026-09-22. Play Console reports version 152 (1.0.67) available to internal testers.
- German Play note: „Vereinsverwaltung ist jetzt übersichtlicher: klarere Finanzen, passende Terminbeispiele, kompakte Prüfhinweise, verständliche Kennzahlen und bessere Hinweise bei Dateien und Mitteilungen.“
- English Play note: “Club management is clearer with simpler finance labels, relevant event examples, compact review guidance, explained metrics, and better file and announcement information.”

## Candidate 151

- Version **1.0.66+151** completes the 20-point club UX revision. Club navigation, member cards, finances, profile editing, team actions, event creation, announcements, documents and empty states are clearer and more compact. Immediate announcements now confirm their audience, technical file names are replaced with readable labels, and each area emphasizes one primary action.
- Signed AAB: `release_evidence/artifacts/airmius-1.0.66-151-release.aab`; SHA-256 `4c82c09302475d9cdc687b9079d875c2bdfb436f8548a0fba9973c603e07c7e7`. Flutter analysis and all 339 tests passed; the Android app bundle build passed and `jarsigner` reported `jar verified`.
- Published to Google Play internal testing as release 118 on 2026-09-22. Play Console reports version 151 (1.0.66) available to internal testers.
- Phone update is awaiting Google Play propagation: the connected device still reports versionCode 150/versionName 1.0.65 and its Play listing shows “Open” immediately after publication. The existing app was kept installed to preserve Play signing compatibility and user data.

## Release candidate

- Published **1.0.65+150** to Google Play internal testing as release 117 on 2026-09-22. First-time club setup now emphasizes three practical steps and a chosen starting path. Registration details are deferred, review status stays visible, and member, finance, team, event, document and announcement actions are easier to find. Empty states and post-action links guide users to the next task.
- Signed AAB: `release_evidence/artifacts/airmius-1.0.65-150-release.aab`; SHA-256 `b3446d8f1719796f8e7004976f6858b4f543d5c7a2d2bb937ea3e66924657d0b`. Flutter analysis and all 339 tests passed; the Android app bundle build passed and `jarsigner` reported `jar verified`. Play Console reports version 150 (1.0.65) available to internal testers.
- German Play note: „Vereine lassen sich mit drei klaren Startschritten einfacher einrichten. Mitglieder einladen, Beiträge und Zahlungen verwalten sowie neue Teams und Termine öffnen geht jetzt übersichtlicher.“
- English Play note: “Setting up a club is easier with three clear starting steps. Inviting members, managing dues and payments, and opening new teams and events are more straightforward.”

## Previous candidate 149 (published to internal testing)

- Published **1.0.64+149**: Personal member overview with upcoming club event, unread announcements, current open dues and club documents. Club requests show clearer status, date and payment labels, and filtered empty states return to all requests. Membership navigation and finance actions are easier to scan. Club announcements support drafts, preview, scheduled publication and editing before publication. Event review omits the country when no location was entered.
- Signed AAB: `release_evidence/artifacts/airmius-1.0.64-149-release.aab`; SHA-256 `fbe7f97bd21ce427419d20c5bb7c1fa5f102a2096ec61b20da372c4d87da008c`. Flutter analysis, 277 widget tests, Android bundle build, and signature verification passed. The updated server code and migration were deployed to the active airmius.com installation before publication. Its scheduler ran `airmius:publish-club-announcements` successfully.
- Published to Google Play internal testing as release 116 on 2026-09-22. Play Console reports version 149 (1.0.64) available to internal testers.
- German Play note: „Mein Verein zeigt Termine, Mitteilungen, Beiträge und Dokumente direkt. Anträge und Finanzen sind übersichtlicher. Mitteilungen können als Entwurf gespeichert oder für später geplant werden.“
- English Play note: “My Club now shows events, announcements, dues and documents. Requests and finances are clearer. Announcements can be saved as drafts or scheduled for later.”

## Previous candidate 148 (published to internal testing)

- Current candidate: **1.0.63+148**, Android ID `com.airmius.app`, production API `https://airmius.com`. The club home uses less space when no tasks are pending, existing members no longer see membership offers, and simple events show only relevant fields before creation.
- German Play note: „Der Vereinsstart ist kompakter, wenn keine Aufgaben offen sind. Bestehende Mitglieder sehen eine klarere Mitgliedschaftsansicht, und einfache Events zeigen vor dem Erstellen nur relevante Angaben.“
- English Play note: “The club home is more compact when no tasks are pending. Existing members see a clearer membership view, and simple events show only relevant details before creation.”
- Signed AAB: `release_evidence/artifacts/airmius-1.0.63-148-release.aab`; SHA-256 `d9f37de54c53f92ccb7eb9423ef98bde1ce3f7693f4398f2815498e5e2708b9d`. Android bundle build, Flutter analysis and all 277 widget tests passed; `jarsigner` reported `jar verified`.
- Published to Google Play internal testing as release 115 on 2026-09-22 with German and English notes. Play Console reports version 148 (1.0.63) available to internal testers.

## Previous candidate 147 (published to internal testing)

- Current candidate: **1.0.62+147**, Android ID `com.airmius.app`, production API `https://airmius.com`. Club announcements and overview appear earlier, total and active member counts are distinguished, request status filtering is compact, and basic event creation reaches review in three steps while optional details remain available.
- German Play note: „Vereinsverwaltung einfacher: Mitteilungen und Übersicht sind schneller erreichbar, Mitgliederzahlen klarer, der Antragsfilter kompakter und Events in drei Schritten erstellt. Weitere Angaben bleiben optional.“
- English Play note: “Club management is simpler: announcements and overview are easier to reach, member totals are clearer, request filters are compact, and events can be created in three steps. Additional details remain optional.”
- Signed AAB: `release_evidence/artifacts/airmius-1.0.62-147-release.aab`; SHA-256 `6c6a2c9625550b40037cd6c6e1837753b6b196dda8442614c70ab79862a26d02`. Android bundle build, Flutter analysis and all 277 widget tests passed; `jarsigner` reported `jar verified`.
- Published to Google Play internal testing as release 114 on 2026-09-20. Play Console reports version 147 (1.0.62) available to internal testers. The connected phone had versionCode 147/versionName 1.0.62 installed during the 2026-09-22 UX review.

## Previous candidate 146 (published to internal testing)

- Current candidate: **1.0.61+146**, Android ID `com.airmius.app`, production API `https://airmius.com`. The club cockpit is shorter, requests and payments link directly to their destination, the request inbox no longer shows local-only settings, and club figures and announcements use real server data.
- German Play note: „Vereinsverwaltung übersichtlicher: echte Kennzahlen, direkter Zugang zu Anträgen und Finanzen sowie verständlichere Mitteilungen mit Zielgruppe und Lesebestätigungen.“
- English Play note: “Club management is clearer: live club figures, faster access to requests and finances, and clearer announcements with audience selection and read confirmations.”
- Signed AAB: `release_evidence/artifacts/airmius-1.0.61-146-release.aab`; SHA-256 `66aafdef2e5c3a07a47c1b9e6be15e6bc7483e64263e67044f79877308e4e907`. Android bundle and APK builds passed; `jarsigner` reported `jar verified`. Flutter analysis passed and all 276 widget tests passed before the version bump.
- Published to Google Play internal testing as release 113 on 2026-09-20 with German and English notes. Play Console reports version 146 (1.0.61) available to internal testers. The connected phone installed the update through Play; Android reports versionCode 146/versionName 1.0.61. On-device review covered club home, reports, announcements, invitation entry, and request inbox without changing live club data.

## Previous candidate 145 (published to internal testing)

- Version **1.0.60+145** improved club finance titles, the direct club entry from Workspaces, team calendar empty states, and placement of team deletion. Published as internal test release 112 on 2026-09-20; the connected phone installed versionCode 145 through Play.

## Previous candidate 144 (published to internal testing)

- Current candidate: **1.0.59+144**, Android ID `com.airmius.app`, production API `https://airmius.com`. Club team details use a compact section selector, member search also finds email and membership numbers, and the club cockpit, team list, and cashbook present essential actions with less scrolling.
- German Play note: „Die Vereinsverwaltung ist übersichtlicher: Mitglieder schneller finden, Teambereiche einfacher wechseln und wichtige Aktionen mit weniger Scrollen erreichen.“
- English Play note: “Club management is clearer: find members faster, switch team sections more easily, and reach key actions with less scrolling.”
- Signed AAB: `release_evidence/artifacts/airmius-1.0.59-144-release.aab`; SHA-256 `62de7cf838c1be2762244a8f1f646c1d2f6dd77cf9a1d1e348390a63c88b1913`. Flutter analysis and all 335 tests passed; Android bundle build passed and `jarsigner` reported `jar verified`.
- Published to Google Play internal testing as release 111 on 2026-09-20 with German and English notes. Play Console reports version 144 (1.0.59) available to internal testers.
- Phone update pending Play Store propagation: the connected test phone still reports versionCode 143/versionName 1.0.58 and its Play listing still shows the previous release notes and "Open" rather than "Update" immediately after publication.

## Previous candidate 143 (published to internal testing)

- Current candidate: **1.0.58+143**, Android ID `com.airmius.app`, production API `https://airmius.com`. Team lists now precede summary metrics; duplicate mode tabs were removed because team details provide their own navigation. Club finance presents invoice creation and payment recording before optional treasury metrics.
- German Play note: „Die Vereinsverwaltung ist übersichtlicher: Teams erscheinen schneller, doppelte Navigation entfällt und Rechnungen sowie Zahlungen sind leichter zu finden.“
- English Play note: “Club management is easier to navigate: teams appear sooner, duplicate navigation is removed, and invoice and payment actions are easier to find.”
- Signed AAB: `release_evidence/artifacts/airmius-1.0.58-143-release.aab`; SHA-256 `4e278d6f95dbea5c1593944a8013777f567f4ce307d06ac28fea2b0420e7ebe6`. Flutter analysis and all 334 tests passed; Android bundle build passed and `jarsigner` reported `jar verified`.
- Published to Google Play internal testing as release 110 on 2026-09-20 with German and English notes. Play Console reports version 143 (1.0.58) available to internal testers. The update was installed through Google Play on the test phone; Android reports versionCode 143 and versionName 1.0.58, and the app launched successfully.

## Previous candidate 142 (published to internal testing)

- Current candidate: **1.0.57+142**, Android ID `com.airmius.app`, production API `https://airmius.com`. The single-club membership screen skips its oversized club selector, the booking menu is clearly active, and the empty bank state says that no transactions exist yet. These fixes were found during on-device review of build 141.
- German Play note: „Die Vereinsverwaltung wurde weiter verfeinert: Einladungen und Finanzen sind übersichtlicher, der Vereinswechsel erscheint nur bei mehreren Vereinen und Buchungsaktionen sind klar erkennbar.“
- English Play note: “Club management is more polished: invitations and finances are clearer, club selection appears only when needed, and booking actions are easier to recognize.”
- Signed AAB: `release_evidence/artifacts/airmius-1.0.57-142-release.aab`; SHA-256 `0e8f6ec08858a4736494601f5b887edfb6fcce65f5ffde921d4c415e71c5b7bd`. Flutter analysis and focused invitation/finance tests passed; Android bundle build passed and `jarsigner` reported `jar verified`.
- Published to Google Play internal testing as release 109 on 2026-09-20 with German and English notes. Play Console reports version 142 (1.0.57) available to internal testers. The Play-signed update is installed on the test phone; Android reports versionCode 142 and versionName 1.0.57.

## Previous candidate 141 (published to internal testing)

- Current candidate: **1.0.56+141**, Android ID `com.airmius.app`, production API `https://airmius.com`. The club cockpit uses compact metrics and direct navigation; membership invitations start with just name and email; the event wizard waits for input before showing validation; club finance uses compact totals, a readable period filter and a grouped booking menu. Team navigation, onboarding priorities and club sport labels are clearer.
- German Play note: „Die Vereinsverwaltung ist übersichtlicher: kompakteres Cockpit, schneller Zugriff auf Mitglieder, Teams, Termine und Finanzen, einfachere Einladungen und klarere Finanzaktionen. Die Terminplanung zeigt Hinweise erst bei Bedarf.“
- English Play note: “Club management is clearer: a more compact dashboard, quick access to members, teams, events and finances, simpler invitations and clearer finance actions. Event planning shows validation only when needed.”
- Signed AAB: `release_evidence/artifacts/airmius-1.0.56-141-release.aab`; SHA-256 `70999f9d645213757285554a696cb6cf3aa2444731a89e34c231d5dc91fdf551`. Flutter analysis and all 334 tests passed; the team creation flow was verified separately after its layout change. Android bundle build passed and `jarsigner` reported `jar verified`.
- Google Play internal-testing release 108 was published on 20 September 2026 as version 141 (1.0.56), with German and English notes. Play Console reported "Für interne Tester verfügbar" and the linked phone installed versionCode 141 through Play Store. On-device review confirmed the compact club cockpit and simplified invitation form.

## Previous candidate 140 (published to internal testing)

- Current candidate: **1.0.55+140**, Android ID `com.airmius.app`, production API `https://airmius.com`. Club owners open directly into a compact club cockpit with urgent work and direct actions first. Team, event, file, profile and announcement flows retain the selected club; team lists are club-scoped. Onboarding is condensed, member/finance navigation simplified, role labels human-readable, and invoice period filtering now changes the displayed invoices.
- German Play note: „Die Vereinsverwaltung ist einfacher: klarer Einstieg, vereinsbezogene Teams, direkte Aktionen, übersichtlichere Einrichtung und Navigation sowie ein funktionierender Rechnungszeitraumfilter. Vereinsmitteilungen öffnen für den ausgewählten Verein.“
- English Play note: “Club management is easier: a clearer home, club-scoped teams, direct actions, simpler setup and navigation, and a working invoice period filter. Club announcements now open for the selected club.”
- Signed AAB: `release_evidence/artifacts/airmius-1.0.55-140-release.aab`; SHA-256 `18cf966b4715a587b435f614abfe1520af2e9f4413a775916c37215da8e2a167`. Flutter analysis and all 334 tests passed; Android bundle build passed and `jarsigner` reported `jar verified`.
- Google Play internal testing release 107 was published with version 140 (1.0.55) and German/English notes on 20 September 2026. Play Console reports "Für interne Tester verfügbar". The linked Android test device was updated through Play Store and reports versionCode 140, versionName 1.0.55; the app opened directly in the club cockpit.

## Previous candidate 139 (published to internal testing)

- Current candidate: **1.0.54+139**, Android ID `com.airmius.app`, production API `https://airmius.com`. The athlete feed shows a friendly start action instead of repeated zero percentages at the start of a day; the profile places activity higher; sport matching explains when team membership is required and distinguishes empty offers from filtered search results.
- German Play note: „Der Tagesstart ist freundlicher und das Profil kompakter. Beim Sport-Matching erhältst du klarere Hinweise zu leeren Ergebnissen und zur benötigten Teamzugehörigkeit.“
- English Play note: “The daily start is friendlier and the profile is more compact. Sport matching now explains empty results and when team membership is required.”
- Signed AAB: `release_evidence/artifacts/airmius-1.0.54-139-release.aab`; SHA-256 `9f6538c1956c6a0864f49a7336f6aa8883a5f6adedb3e69cced46f08c37f9a79`. Flutter analysis and all 330 tests passed; Android bundle build passed and `jarsigner` reported `jar verified`.
- Uploaded and published to Google Play internal testing on 2026-09-20. Release 106 on track 4701726677920883191 is **139 (1.0.54) – Sportler-UX verbessert**, with German and English notes. Play Console confirms “Für interne Tester verfügbar”; the connected Xiaomi phone was updated through Google Play and `dumpsys package` confirms versionCode 139 and versionName 1.0.54. The app launched successfully.

## Previous candidate 138 (published to internal testing)

- Current candidate: **1.0.53+138**, Android ID `com.airmius.app`, production API `https://airmius.com`. The athlete daily card now displays the three values used for its score (training, nutrition, water). Training, events, friends and profile use more compact layouts; creating a sport-matching offer starts with an explicit partner or team choice.
- German Play note: „Die Sportleransichten sind übersichtlicher: Der Tageswert erklärt jetzt Training, Ernährung und Wasser. Trainingspläne, Events, Freunde und Profil sind kompakter. Beim Sport-Matching wählst du direkt zwischen Sportpartner und Team.“
- English Play note: “Athlete screens are clearer: daily progress now explains training, nutrition and water. Training plans, events, friends and profile are more compact. Sport matching now starts with a partner or team choice.”
- Signed AAB: `release_evidence/artifacts/airmius-1.0.53-138-release.aab`; SHA-256 `3d34176b7ba6350a9faa4f4d383824bc70c85fde97f1f45dc825bd923b5f3330`. Flutter analysis and all 328 tests passed; Android bundle build passed and `jarsigner` reported `jar verified`.
- Uploaded and published to Google Play internal testing on 2026-09-20. Release 105 on track 4701726677920883191 is **138 (1.0.53) – Sportleransichten klarer** with German and English notes. Play Console confirms “Für interne Tester verfügbar”. The connected phone updated through Google Play; Android reports `versionCode=138`, `versionName=1.0.53`, and the app process starts with the corrected daily card visible.

## Previous candidate 137 (published to internal testing)

- Current candidate: **1.0.52+137**, Android ID `com.airmius.app`, production API `https://airmius.com`. Athlete navigation now surfaces Sport-Matching, Events and Friends directly. Training tools move into a labeled menu so the screen title remains readable. Team search, friend requests, event empty states and feed loading errors use clearer labels.
- German Play note: „Die Sportler-Navigation ist übersichtlicher. Sport-Matching, Events und Freunde sind schneller erreichbar. Trainingswerkzeuge, Teamsuche und leere Ansichten sind klarer gestaltet.“
- English Play note: “Athlete navigation is clearer. Sport matching, events and friends are easier to reach. Training tools, team search and empty states are easier to understand.”
- Signed AAB: `release_evidence/artifacts/airmius-1.0.52-137-release.aab`; SHA-256 `0adad04873ea6bd62744517bec9cd4fb92e3ae5ee594a53f0c583fdd073afb00`. Flutter analysis and tests passed; Android bundle build passed and `jarsigner` reported `jar verified`.
- Uploaded and published to Google Play internal testing on 2026-09-20. Release 104 on track 4701726677920883191 is **137 (1.0.52) – Sportler-Navigation verbessert** with German and English notes. Play Console confirms “Für interne Tester verfügbar”. The connected phone installed the update through Play; Android reports `versionCode=137`, `versionName=1.0.52`, and the app process starts.

## Previous candidate 136 (published to internal testing)

- Current candidate: **1.0.51+136**, Android ID `com.airmius.app`, production API `https://airmius.com`. The Teams tab now gives athletes a clear overview of their teams and clubs, a focused search, and an invitation entry point. Club registration is secondary and a redundant navigation button has been removed.
- Signed AAB: `release_evidence/artifacts/airmius-1.0.51-136-release.aab`; SHA-256 `50cb5797ccee789808afc57bfdb67ae1be4cf173122de04a6763e80d2d5ef117`. Flutter analysis passed and `jarsigner` reported `jar verified`.
- Uploaded and published to Google Play internal testing on 2026-09-20. Release 103 on track 4701726677920883191 is **136 (1.0.51) – Teams einfacher finden** with German and English notes. Play Console confirms “Für interne Tester verfügbar”.
- German Play note: „Teams und Vereine sind jetzt leichter zu finden. Der Teams-Bereich zeigt einen klaren Einstieg, eine gezielte Suche und die Möglichkeit, Team-Einladungen zu öffnen.“
- English Play note: “Teams and clubs are now easier to find. The Teams area has a clearer entry point, focused search and a way to open team invitations.”

## Previous candidate 135 (published to internal testing)

- Current candidate: **1.0.50+135**, Android ID `com.airmius.app`, production API `https://airmius.com`. Nutrition now uses a clear “Trinken” tab with one water-progress panel, a custom-amount action and direct access to focused water settings. The overview has a direct meal action and no duplicate water-entry panel. Goal fields and reminder times use full-width controls on narrow phones; automatic water targets show the weight field, while manual targets show the millilitre field.
- Signed AAB: `release_evidence/artifacts/airmius-1.0.50-135-release.aab`; SHA-256 `cd1b71efc64c89d86e697f7a55c2f28d71b25be69180d060b54870e596835f93`. Flutter analysis passed; 267 widget tests passed; Android bundle build passed and `jarsigner` reported `jar verified`.
- Uploaded and published to Google Play internal testing on 2026-09-20. Release 102 on track 4701726677920883191 is **135 (1.0.50) – Ernährung einfacher bedienen** with German and English notes. Play Console confirms “Für interne Tester verfügbar”.

## Previous candidate 134 (published to internal testing)

- New candidate: **1.0.49+134**, Android ID `com.airmius.app`, production API `https://airmius.com`. The Nutrition overview now shows “Ziele bearbeiten” above the targets; the Drink tab links directly to a focused “Trinkziel & Erinnerungen” editor.
- Signed AAB: `release_evidence/artifacts/airmius-1.0.49-134-release.aab`; SHA-256 `b8a37d74f18c32779be165e3cee743b68abbc7912a77eefbd6f91d2fe2aa6d93`. Flutter analysis and Android bundle build passed; `jarsigner` reported `jar verified`.
- Uploaded and published to Google Play internal testing on 2026-09-19. Release 101 on track 4701726677920883191 is **134 (1.0.49) – Trinkziele und Erinnerungen** with German and English notes. Play Console confirms “Für interne Tester verfügbar”.

## Previous candidate 133 (published to internal testing)

- Current candidate: **1.0.48+133**, Android ID `com.airmius.app`, production API `https://airmius.com`.
- Optional water reminders can be enabled in Nutrition. On activation two per day are selected; users can choose one to three and set a daytime window. Notifications are skipped after a recent drink entry or when the daily goal is reached. Phone push also requires enabling app notifications.
- The existing automatic weight and training estimate is labeled as an estimate. Signed AAB: `release_evidence/artifacts/airmius-1.0.48-133-release.aab`; SHA-256 `723fd7f5a5216d49af9ac490ecf5cd7792b9db059bd2b923314e8e03071810a2`.
- Uploaded and published to Google Play internal testing on 2026-09-19. Release 100 on track 4701726677920883191 is **133 (1.0.48) – Trinkerinnerungen** with German and English notes. Play Console confirms “Für interne Tester verfügbar”.

## Previous candidate 132 (published to internal testing)

- Current candidate: **1.0.47+132**, Android ID `com.airmius.app`, production API `https://airmius.com`.
- Sport Matching now starts in list view, supports exact or minimum opponent team sizes, accepts an applicant team size, allows pending withdrawal, and uses device coordinates for distance filtering when permission is granted.
- Signed AAB: `release_evidence/artifacts/airmius-1.0.47-132-release.aab`; SHA-256 `34363dda336663e9203957e79510ec0b75f8b54c9136db4d88bb2c9cea147249`. The matching APK is `release_evidence/artifacts/airmius-1.0.47-132-release.apk`.
- Uploaded and published to Google Play internal testing on 2026-09-19. Release 99 on track 4701726677920883191 is **132 (1.0.47)** with German and English notes. Play Console confirms “Für interne Tester verfügbar”.

## Previous candidate 131 (published to internal testing)

- Current candidate: **1.0.45+130**, Android ID `com.airmius.app`, production API `https://airmius.com`.
- Athletes can create and edit personal training plans in the Android app. The plan form now loads its choices after dependencies are available.
- Verification: Flutter analysis passed, the athlete plan widget test passed, and `TrainingPlanApiCrudTest` passed (8 tests, 152 assertions). The signed AAB contains `can_create_personal_training_plans`; manifest confirms `com.airmius.app`, version 1.0.45/130. SHA-256: `37a4a489f05899d1801b50c9e14178c85b88e594829e0f358d9b0e5793fad7da`.
- Uploaded and published to Google Play internal testing on 2026-09-19. Release 97 on track 4701726677920883191 is **130 (1.0.45)**. Play Console confirms “Für interne Tester verfügbar”.

## Previous candidate 129 (published to internal testing)

- Current candidate: **1.0.44+129**, Android ID `com.airmius.app`, production API `https://airmius.com`.
- Authentication requests now allow 45 seconds instead of 20 seconds. Production login probes needed about 20.4 seconds, which caused Google session validation to time out at the previous boundary.
- Verification: all 326 Flutter tests passed, full Flutter analysis reports no issues, release version consistency and git diff whitespace checks passed.
- Signed bundle: `release_evidence/artifacts/airmius-1.0.44-129-release.aab`; SHA-256 `d3169316c4c20261de2d573b3b03ff95fcd2679aa6187481ceed9550fcc6699a`. Jarsigner reports `jar verified`; manifest confirms `com.airmius.app`, version 1.0.44/129.
- Uploaded, processed and published to Google Play internal testing on 2026-09-18. Release 96 on track 4701726677920883191 is **129 (1.0.44)**. The console confirms “Für interne Tester verfügbar” and identifies 129 as the latest release.

## Previous candidate 128 (published to internal testing)

- Current candidate: **1.0.43+128**, Android ID `com.airmius.app`, production API `https://airmius.com`.
- Includes candidate 127 changes and verifies the profile request before saving a new social-login session. API failures now keep the user on the sign-in screen with an error instead of opening an empty profile.
- Verification: all 326 Flutter tests passed, full Flutter analysis reports no issues, release version consistency and git diff whitespace checks passed.
- Signed bundle built in 293.8 seconds: `release_evidence/artifacts/airmius-1.0.43-128-release.aab`, 90,303,495 bytes; SHA-256 `bc22247b90bf454b42d8afec620b662f63794c3096944411ba90536e96baa7a0`. Jarsigner reports `jar verified` with the existing self-signed certificate/ZIP-stream warnings. Manifest confirms `com.airmius.app`, version 1.0.43/128, minSdk24/targetSdk36, non-debuggable and backup disabled.
- Uploaded, processed and published to Google Play internal testing on 2026-09-18. Release 95 on track 4701726677920883191 is **128 (1.0.43)** with bundle 128 and German/English notes. The console confirms “Für interne Tester verfügbar” and identifies 128 as the latest release.
- The server returned slow responses and HTTP 429 during diagnosis. This app change does not resolve the server-side issue; successful Google sign-in on the updated device still needs verification.

## Previous candidate 127 (historical build evidence)

- Current candidate: **1.0.42+127**, Android/iOS ID `com.airmius.app`, production API `https://airmius.com`.
- Includes candidate 126 changes plus corrected Personal progress module labels in DE/EN/FR/AR.
- Source checks: 321 Flutter tests passed, analyze clean; version consistency and CrossDeviceReadinessTest (5 tests/77 assertions) passed.
- Bundle built locally in 277.9 seconds: `release_evidence/artifacts/airmius-1.0.42-127-release.aab`, 90,303,393 bytes. SHA-256 `6548c4e9ae83fd3a99502c5f816972f4d60f5eb7605487940f8626575435e6e1`. Bundle manifest identifies com.airmius.app, 1.0.42, 127. No upload, rollout or device acceptance yet.
- Backend fixes require separate deployment of commits 099c0b4a and 8487b7aa. GitHub authentication unavailable at last check.

## Previous candidate 126 (historical build evidence)

- App: Airmius Mobile
- Version: `1.0.41+126` (internal test candidate; course/training localization and empty readiness display)
- Android application ID: `com.airmius.app`
- iOS bundle ID: `com.airmius.app`
- API environment: `https://airmius.com`
- New in 1.0.41+126: localized course statuses, singular labels for lessons/sessions/exercises/target sets, and no numeric readiness gauge when the backend reports no data.
- Candidate bundle: `release_evidence/artifacts/airmius-1.0.41-126-release.aab`, 90,304,063 bytes; SHA-256 `4effebd4120159c3ed8b10dfa32f6b1af840b7286e0c13c7963911dd92683b3b`. Release build succeeded in 206.2 seconds; jarsigner reports `jar verified` with self-signed upload-key/ZIP-stream warnings.
- Source verification before version increment: 317 Flutter tests passed and analyze clean. Version consistency passed; CrossDeviceReadinessTest 5 passed/77 assertions. Packaged manifest and bundle manifest strings confirm com.airmius.app, 1.0.41, 126; packaged manifest minSdk24/targetSdk36.
- Status: built locally; internal Play release draft prepared with DE/EN notes, AAB not uploaded or released. Device validation is pending Play installation. The local upload key differs from the installed Play signing key; do not uninstall the user's app to sideload it.
- Backend dependency: commit `099c0b4a` must be deployed separately for training privacy fixes and the empty-data readiness response. GitHub authentication is currently unavailable; deployment unverified.

## Historical evidence (not evidence for candidate 126)

- Previous 125 bundle: `release_evidence/artifacts/airmius-1.0.40-125-release.aab` (SHA-256 `035371e24b95ba0564dbded33f4fb1cdda702fa9a038814cc8afd7ab7232b535`), installed and tested through Play on 2026-09-07.
- Release owner:
- Release date:
- Previous local release AAB (before the final patch; do not upload as the final build): `build/app/outputs/bundle/release/airmius-play-console-v1.0.33-code75.aab` (2026-08-05 21:50 CEST, 82.3 MiB / 86,286,112 bytes)
- Previous AAB SHA-256: `1dd8a1f57381f7ac4e6b5c8e826bb9500bd043f4a9437f2f143ef5401b6482b8`
- Fresh final AAB: `build/app/outputs/bundle/release/airmius-play-console-v1.0.33-code76.aab` (2026-08-06 19:56 CEST, 86,316,875 bytes; Version 1.0.33+76; SHA-256 `0e5a9085586552d438c4ff7e4bc3d1337cf33910ca2bc36ae842c1cb2acc543a`).
- Fresh AAB SHA-256: `c13a361746bec0436d635b293baa7d30f42507b1635d046dae48d97a3ae2437f`.
- Fresh web release build: `build/web`.
- Source verification: `flutter analyze` (no issues).
- Fresh artifact verification: `jarsigner -verify` exited successfully (`jar verified`); the self-signed upload-key and JarInputStream warnings are informational for this Android App Bundle.
- Backend verification: `php artisan test --filter=MobileAuthSecurityTest` (5 passed, 45 assertions); `composer validate --strict` passed. Composer's online vulnerability audit remains an external-network gate.
- Hostinger deployment gate: the matching `bootstrap/app.php` change is live. The invalid Origin login probe returns HTTP 422 (`auth.failed`) and the preflight returns HTTP 204 with the expected CORS headers; a real account/device smoke test remains the final external check.

## Gebündelter Änderungsentwurf (Arbeitsstand)

Dieser Abschnitt beschreibt den aktuellen Sammel-Release. Das frische AAB ist gebaut und signiert; vor dem Play-Console-Upload bleiben die üblichen Store-, Review-, Datenschutz- und Realgeräte-Gates.

- Läufer können jetzt direkt aus dem Trainingsbereich einen freien Lauf ohne vorherigen Trainingsplan starten. Die App zeigt eine OpenStreetMap-Laufkarte, zeichnet GPS-Punkte auf, berechnet aktive Zeit, Distanz und Durchschnittspace, unterstützt Pause/Fortsetzen und speichert den Abschluss mit Belastung, Sichtbarkeit und Notizen als durchgeführtes Training. Auf Android läuft ein vom Nutzer gestarteter Lauf mit dauerhafter Systembenachrichtigung auch bei minimierter App oder gesperrtem Bildschirm weiter; Pause, Abschluss und Verwerfen beenden den Standortdienst sofort. Unfertige Läufe werden lokal wiederherstellbar gesichert.
- Die produktive API-Adresse der Android-App verwendet jetzt `https://airmius.com`. Dadurch schlägt der Login nicht mehr wegen des nicht auflösbaren alten Hosts `app.airmius.com` fehl; bestehende Social-Login-Rückruflinks über den alten Host bleiben kompatibel.
- Auch Debug-Android-Builds verwenden ohne explizites `AIRMIUS_API_BASE_URL` den produktiven HTTPS-Origin statt `http://localhost`, das auf einem echten Gerät auf das Gerät selbst zeigen würde.
- Der versionierte Login-Endpunkt ist zusätzlich von der SPA-CSRF-Prüfung ausgenommen: Browser-Origin- und Flutter-Web-Anfragen werden nicht mehr vor der eigentlichen Zugangsdatenprüfung mit HTTP 419 abgebrochen; die Anmeldung bleibt durch Rate-Limit und Bearer-Token geschützt.
- Bei unterbrochener oder zu langsamer Verbindung liefert die App nun eine verständliche, lokalisierte Login-Fehlermeldung statt eines unklaren generischen Fehlers; Requests werden nach 20 Sekunden sauber abgebrochen.
- Die API-Konfiguration normalisiert zusätzlich alte Alias- und bereits versionierte Eingaben automatisch, damit weder `app.airmius.com` noch ein doppelter `/api/v1/api/v1`-Pfad die Anmeldung unterbrechen kann.
- Social-Login-Callbacks von Produktionsdomains akzeptieren ausschließlich HTTPS; HTTP-Token-Callbacks werden blockiert und durch Regressionstests abgesichert.
- Diagnose-, Datei- und Safety-Ansichten zeigen alte `/friends/...`-Quellen jetzt als kanonische `/api/v1/...`-Mobile-Routen; dadurch werden keine falschen Serverpfade mehr weitergegeben.
- Die Auth-Recovery-Seite öffnet Passwort-Reset und E-Mail-Verifizierung jetzt als echte API-Flows statt Demo-Dialogen; die Schnellaktionen, Panels und Switches folgen der aktiven Farbpalette und bleiben auf kleinen/hellen/RTL-Ansichten bedienbar.
- Die Sicherheitsübersicht ist jetzt aus dem erreichbaren Auth-Bereich „Konto & Sicherheit“ geöffnet; ihre Filter, Statusgruppen, Flows und Aktionen sind in Deutsch, Englisch, Französisch und Arabisch lokalisiert und für kleine Arabic-RTL-Ansichten getestet.
- Konto-Aktionen respektieren jetzt den angeforderten Einstiegsbereich: Passwort-, Sicherheits- und Kontolöschungs-Aktionen springen direkt zum passenden Abschnitt statt nur an den Seitenanfang.
- Die Web-Chat-Ansicht ist durchgehend in Deutsch, Englisch, Französisch und Arabisch nutzbar: Suche, Gruppenverwaltung, Einladungen, Meldungen, Reaktionen, Anhänge, Leerzustände und Screenreader-Beschriftungen folgen jetzt dem aktiven Sprachkatalog.
- Der Web-Dateimanager übernimmt Übersetzungen, semantische Theme-Farben und klare Tast-/Touch-Beschriftungen für Suche, Sortierung, Speicherstatus, Upload, Freigabe, Umbenennen, Löschen und Download.
- Shop-, Warenkorb- und Outfit-Übersichten verwenden lokalisierte Artikel-, Preis-, Zahlungs-, Leer- und Checkout-Texte sowie interpolierte Mengen, Länder und Anbieter in allen vier Sprachen.
- Das Learning-Studio nutzt für Kursanlage, Kursübersicht, Analytics, Publish-Check, Tabs, Kapitel und Lektionen lokalisierte Eingaben, Statuswerte, Aktionen und Leerzustände in allen vier Sprachen.
- Die Studio-Bereiche für Landingpage, Gutscheine, Quiz, Aufgaben, Teilnehmer und Fragen-Inbox sind ebenfalls vollständig lokalisiert und mit klaren Screenreader-Beschriftungen versehen.
- Externe Lern-, Medien-, Bewerbungs- und Rechtslinks öffnen neue Tabs jetzt mit `noopener noreferrer`, um das ursprüngliche Fenster gegen Tabnabbing abzusichern.
- Das Trainer-Cockpit ordnet seine Navigation auf kleinen Bildschirmen automatisch um: Tabs brechen lesbar um, während breite Ansichten kompakt horizontal bleiben.
- Der webbasierte Trainingsplan-Dialog lokalisiert jetzt auch Rhythmus, Ziel, Zeitraum, Freigabe, Sportlerauswahl und erste Einheit; die beschädigte arabische Darstellung wurde durch korrektes RTL-Arabisch ersetzt.

- Einheitliches, responsives Designsystem mit mehreren Farbpaletten, Hell/Dunkel/System-Modus und besser lesbaren Textstufen.
- Hauptbereiche übernehmen AppBar- und Oberflächenfarben jetzt aus der aktiven Palette, sodass Hell-, Dunkel- und Kontrastmodus auch beim Wechsel zwischen Seiten konsistent bleiben.
- Der Community-Feed verwendet nun ebenfalls dynamische Theme-Neutralfarben für Karten, Eingabefelder, Text, Metadaten und Rahmen; dadurch bleibt er in allen Farbpaletten lesbar.
- Die globale Suche nutzt aktive Palettefarben für Floating Action Button und Filter; Modul-Launcher und Moduldetailkarten übernehmen denselben Akzent statt eines festen Blauwerts.
- Die Verzeichnis-Suche startet ohne künstliche Demoanfrage, sucht nach kurzer Eingabepause automatisch, zeigt lokalisierte Lade-/Fehler-/Leerzustände und bleibt in allen vier Sprachen, RTL, großen Schriftstufen sowie den aktiven Farbpaletten bedienbar.
- Marketplace-Preise, Bewertungen, Verfügbarkeit, Warenkorb, Checkout, Bestellungen und Problemmeldungen verwenden jetzt ebenfalls aktive Theme-Farben; die französische Light-/Trail-Darstellung bleibt mit großer Schrift lesbar.
- Primäraktionen in den erreichbaren Vereins-, Team-, Trainings-, Datei-, Marketplace-, Commerce-, Sponsor-, Blog-, Freunde-, Fahrgemeinschafts- und Profilbereichen folgen ebenfalls dem aktiven Palette-Akzent statt eines festen Blauwerts.
- Team-, Freunde-, Badges-, Blog-, Sponsoren- und Ernährungsseiten verwenden neutrale Texte, Flächen und Ränder aus dem aktiven Theme statt fest verdrahteter Dark-Theme-Werte.
- Vereinsstatus, Rollen, Warnhinweise und Mitgliedschaftsaktionen verwenden jetzt ebenfalls die aktive Theme-Farbskala; der Vereinsworkflow bleibt in hellen, dunklen und kontrastreichen Paletten lesbar.
- Bedienungshilfen für große Schrift, RTL/Arabisch, größere Touch-Ziele, Tastaturfokus, Semantics und klarere Fehlermeldungen.
- Login mit lokaler E-Mail-/Passwortvalidierung und verständlichen, übersetzten Hinweisen.
- Authentifizierungsfehler für Login, Registrierung, 2FA, Netzwerk und Server werden jetzt in allen vier Sprachen lokalisiert; technische Exception-Texte werden nicht mehr direkt angezeigt.
- Der Konto-/Sicherheitsbereich ist vollständig lokalisiert; Passwort-Hilfe, 2FA, E-Mail-Verifizierung, Profilabschluss, Support und Kontolöschung öffnen echte Seiten statt Platzhalteraktionen.
- Datenschutz, E-Mail-Verifizierung und 2FA verwenden für Schutzstatus, Warnungen und Fehler jetzt adaptive Theme-Tokens; der 2FA-Workflow ist zusätzlich in arabischer RTL-Light-Ansicht mit großer Schrift geprüft.
- Das App-Onboarding ist vollständig mehrsprachig für Deutsch, Englisch, Französisch und Arabisch; Rollen, Startbereiche, Berechtigungsgründe und Folgeaktionen bleiben in allen hellen/dunklen Farbpaletten lesbar und sind mit großer RTL-Schrift geprüft.
- Der Onboarding-Abschluss speichert Rolle, Startbereich und die gewählten Berechtigungen jetzt lokal mit Zeitstempel, setzt den Abschlussstatus und verhindert ein Fortfahren ohne Datenschutz-/AGB-Zustimmung; Lade-, Erfolgs- und Fehlerzustände sind lokalisiert.
- Operations- und Release-Hub verwenden adaptive Oberflächen-, Akzent- und Statusfarben; die arabische Light-/Champion-Darstellung bleibt bei 1,35× Schrift bedienbar.
- Commerce/Seller, Training/Events, Sportprofile, Sportkarte, Lernbereich, Abos, Badges, Freunde, Chat, Dateien, Blog und Sponsoren verwenden semantische Theme-Tokens für Erfolg, Warnung und Fehler statt fester Dark-Theme-Farben.
- Arbeitsbereiche, Teams, Einstellungen, Guardian, Sportintegrationen und Vereinsdarstellung verwenden ebenfalls die aktive Palette; schwarze Schatten und Statusfarben bleiben in hellen, dunklen und kontrastreichen Modi lesbar.
- Navigation Menu, Offline Sync Cache, Input-/Keyboard-Accessibility und Localization/RTL-Format-Parity verwenden nun aktive Theme-Tokens für Hintergrund, Text, Statusfarben, Chips, Switches und Karten; eine arabische Light-/Trail-Großschriftprüfung ist enthalten.
- Brand-Theme-Token, Geräte-/Datenschutzberechtigungen, Session-/Token-Sicherheit und State-Feedback verwenden nun ebenfalls aktive Theme-Tokens; die vier Flows sind unter Arabisch, hellem Trail-Theme und 1,35× Schrift geprüft.
- Karten-/Standort-/Routen-, Medien-Upload-, Modal-/Sheet- und mobile Formular-Schema-Flows bleiben mit responsiven Chips, Fallbacks, Uploadstatus, Overlay-Regeln und Fehlermustern palette-aware und RTL-tauglich.
- Analytics-Dashboard, Subscription-/Entitlement-Gates und Push-/Deep-Link-Routing nutzen adaptive KPI-, Status-, Permission-, Badge- und Upgrade-Farben; die Accessibility-Gate deckt alle drei Seiten ab.
- Mobile Table Actions, Provider-/Webhook-Operations, Systemstatus/Incident-Kommunikation und gespeicherte Ansichten/Suchalarme übernehmen aktive Palette-Tokens und bleiben im RTL-Großschrift-Gate lesbar.
- Deep-Link-Routing, Einladungszugriffe und Consent-Signaturversionierung sind als geschützte, palette-aware Prüfseiten enthalten; End-to-End-Journeys, Auth-/Guardian-Gates, Helfer-/Ressourcenplanung, Exact Page Flows, Draft-Recovery und bereichsübergreifende Freigaben erweitern den geprüften Sammelumfang auf 36 Suiten.
- Der geprüfte Sammelumfang wurde auf 64 Suites erweitert: Sponsor-CRM, Store-/Release-Konfiguration, visuelles Fortschrittsaudit, Sitzungs-/Beschlusslog, Inventar/Ausleihe, Trainingsperiodisierung, Mitgliederfeedback, Richtlinien-Rollout, Laravel-Bindings, Vereinsumfragen, Service-/HTTP-Transport, Token-/Repository-/Datenmodell-Sicherheit, Onboarding-Berechtigungen, Kampagnenverwaltung, Rollen-/Workspace-Wechsel, Mitgliederservice, Marketplace-Erfüllung, Orte/Karten, Lernen/Zertifikate, Gesundheit/Vorfälle, Vereinsregeln, Moderation/Audit, Analytics/Reports, API-Loading-/Empty-/Error-/Offline-Zustände und Content Publishing. Alle neuen Suites sind palette-aware, für arabische RTL-Großschrift getestet und verschachtelte Scroll-/Chip-Layouts bleiben ohne Renderfehler.
- Coverage-/Action-Feedback-Seiten, Permission-Onboarding, Profil-Edit-Fehler und Mitgliedsanfrage-Status verwenden ebenfalls aktive Theme-/Semantikfarben; Coverage-Status und Untertitel sind in Deutsch, Englisch, Französisch und Arabisch lokalisiert.
- Sport-Apps & Gesundheitsdaten öffnen aus dem MVP-Drawer jetzt direkt das API-gebundene Integrationscenter; der öffentliche Marketplace beschreibt Gast-Checkout, Login-Bestellungen und Anbieterangebote nun als verfügbar und lokalisiert diese Aussage in DE/EN/FR/AR.
- Die UI-Coverage-Prüffläche ist jetzt in DE/EN/FR/AR vollständig beschriftet, zählt Module/Ops dynamisch und zeigt API-Verknüpfung bzw. Prüfstatus statt eines veralteten „kommt später“-Hinweises.
- Die Web-Einstellungen beschreiben Google-Fit-/Strava-Synchronisation nun als verfügbare sichere Sync-Funktion; veraltete „später“-Hinweise wurden in DE/EN/FR/AR entfernt.
- Die KI-Trainingsplanung beschreibt die automatische Zuordnung von Einheitentypen jetzt als aktive Funktion und bleibt in allen vier Web-Sprachen konsistent.
- Die mobile Sport-Integrations-API liefert jetzt lokale Übersetzungsschlüssel für Anbieter- und Kontostatus; Strava wird nicht mehr fälschlich als „vorbereitet“ beschrieben.
- Die öffentliche Preis-Seite nutzt Übersetzungen auch für Zielgruppen, Pläne, Checkout-Texte und dynamische Preise; Währungen werden nach der gewählten Sprache formatiert.
- Öffentliche Events, Sportarten und Sponsoren verwenden jetzt lokalisierte SEO-/Filter-/Leerzustände, regionale Datumsformate, zugängliche Feldbeschriftungen und eine konsistente Partner-/Sponsorensprache in DE/EN/FR/AR.
- Die öffentliche Zertifikatsprüfung und der Kursdetail-/Lernraum-Flow übersetzen zentrale Status-, Inhalts-, Formular- und Abschlussaktionen; Kursgeld und Zertifikatsdaten folgen dem aktiven Sprach-/Länderformat.
- Gamification und Werbeagentur übersetzen jetzt auch Datenkarten, Pakete, XP-Demo, Datenschutz-/Jugendschutz-Hinweise und Anfrageformular inklusive vorausgefüllter Anfrageziele in DE/EN/FR/AR.
- Authentifizierte Benachrichtigungen, Meine-Kurse, Badges, Arbeitsbereiche und Überweisungs-Checkout verwenden lokalisierte Status-, Datums-, Formular- und Screenreader-Texte.
- Blog-Listen und -Detailseiten, Eltern-Login, Berechtigungsfehler, Profil-/Rechts-/Datenschutz-/AGB-Einstiege nutzen jetzt ebenfalls aktive Übersetzungen und regionale Blog-Datumsformate; dynamische Fachinhalte bleiben serverseitig unverändert.
- Trainingsprotokoll-Details und die Nutzerverwaltung verwenden jetzt eigene DE/EN/FR/AR-Schlüssel für Status, Plan-Ist-Vergleich, Messwerte, Feedback, Sperrfristen und Accessibility-Labels; dynamische Trainings- und Kontodaten bleiben serverseitig unverändert.
- Verfügbarkeit/Abwesenheit, rollenbasierte Home-Widgets, Laravel-API-Mapping, Store-Release-Assets, öffentliche Vereinsprofil-Vorschau, Job-Queue-Monitor und Audit-Timeline nutzen ebenfalls aktive Farbtoken; ein arabischer 1,35×-RTL-Overflow im Kontaktpanel wurde durch responsive Textführung behoben.
- Die Registrierung stapelt Adressfelder auf kleinen Displays und verwendet ein expandierendes Geschlechts-Dropdown; dadurch bleibt der arabische RTL-Fluss auch bei 1,25× Schrift ohne Overflow bedienbar.
- Der Outfit-Abonnement-Checkout stapelt Land/PLZ und Straße/Hausnummer auf schmalen Displays; damit bleibt die kostenpflichtige Anmeldung auch mit großer arabischer RTL-Schrift gut lesbar.
- Profil- und Kontoverwaltung mit Profilbild, Passwortwechsel, Sitzungsverwaltung sowie geschütztem Konto-Löschablauf.
- E-Mail-Verifizierung, Zwei-Faktor-Sicherheit, Wiederherstellungscodes und sichere Authentifizierungs-Gates.
- Übersetzungsabdeckung für Deutsch, Englisch, Französisch und Arabisch mit neutralem Fallback bei fehlenden Einträgen.
- Die sichtbare Modulnavigation sowie zentrale Feed-, Such-, Status-, Datei- und Mitgliedschaftstexte sind auch auf Arabisch vollständig lesbar; französische Modulnamen verwenden korrekte Akzente.
- Maturity-/Sicherheitsübersicht und Medienrichtlinien sind mit echten API-Daten bzw. echten Zielseiten verbunden und für arabische RTL-Großschrift getestet.
- Der Profil-Vervollständigungs-Gate für neue Konten ist jetzt in allen vier Sprachen verfügbar und übernimmt helle, dunkle sowie kontrastreiche Paletten einschließlich Minderjährigen-/Erziehungsberechtigten-Hinweisen.
- Externe Zahlungs-, Beleg- und Marketplace-Links werden vor dem Öffnen auf sichere HTTP(S)-Schemas, Host und fehlende eingebettete Zugangsdaten geprüft.
- Auch Zahlungs-, Lern- und Rechtslinks verwenden jetzt dieselbe zentrale URL-Prüfung und blockieren eingebettete Zugangsdaten sowie ungültige Schemes.
- Bilder, Avatare, Vereinslogos, Galerie- und Videomedien aus API-Antworten werden ebenfalls normalisiert und nur über erlaubte HTTP(S)-Quellen geladen; fehlerhafte Medien erhalten verständliche Fallbacks.
- Deep-Link-Vorschauen zeigen Status-, Event-, Nachrichten- und Wiederholungs-Hinweise jetzt ebenfalls lokalisiert in Deutsch, Englisch, Französisch und Arabisch.
- Geschützte Event-Deep-Links laden nach der Sitzungsprüfung direkt die konkrete Eventdetailseite aus der API; Gäste erhalten ein Auth-Gate und bei fehlenden Rechten eine lokale Wiederholen-Ansicht statt einer allgemeinen Eventliste.
- Geschützte Feed-Deep-Links laden nach der Sitzungsprüfung den konkreten Beitrag aus der API und öffnen die echte Detailansicht; Sichtbarkeit und Moderationsstatus werden serverseitig geprüft, statt private Beiträge aus der URL zu übernehmen.
- Geschützte Chat- und Benachrichtigungs-Deep-Links laden vor dem Öffnen Titel, Zugriff und Inhalt aus der API; ungültige oder fremde IDs führen zu einer lokalisierten Fehler-/Wiederholen-Ansicht statt zu einem allgemeinen Bereich.
- Einzelne Nachrichten-Deep-Links öffnen jetzt zuerst eine geschützte Vorschau und danach genau die zugehörige Konversation; Teilnehmer-, Gruppenbeitritts- und persönliche Ausblendregeln werden serverseitig geprüft.
- Profil-Deep-Links mit Benutzer-ID laden den privacy-gefilterten Sportprofil-Datensatz und öffnen die echte Profilseite; private Profile liefern nur eine neutrale, datensparsame Darstellung.
- Mitgliedschafts-Deep-Links öffnen jetzt den konkreten Antrag über seine ID und gleichen ihn anschließend mit dem passenden Vereinsdatensatz ab; fremde oder nicht mehr vorhandene Anträge werden nicht durch den ersten Vereinsantrag ersetzt.
- Team-Einladungslinks mit Token öffnen nach Authentifizierung eine echte Vorschau; Annahme und Ablehnung laufen über geschützte API-Aktionen mit Prüfung der eingeladenen E-Mail, des offenen Status und des Vereinslimits und werden nach der Antwort ungültig.
- Freundschafts- und Vereins-Einladungslinks werden ebenfalls direkt aufgelöst: Die App zeigt Absender/Verein und Rolle aus der geschützten API und bietet lokalisierte Annahme-/Ablehnungsaktionen; fremde, abgelaufene oder bereits beantwortete Token bleiben gesperrt.
- Benutzerprofile und Medienvorschauen melden Bildinhalte für Screenreader; Avatar-, Galerie- und Video-Fallbacks bleiben auch bei fehlerhaften Quellen verständlich bedienbar.
- Offline-Synchronisation mit Schutz vor dem Persistieren von Passwort-, Authentifizierungs-, Sitzungs- und Löschvorgängen.
- Zusätzliche UI-/API-Flows für Vereine, Teams, Training, Events, Nachrichten, Marketplace, Dateien, Support, Admin und Benachrichtigungen.
- Die Event-Erstellung nutzt jetzt die serverseitige Berechtigung für wiederkehrende Termine und bietet tägliche, wöchentliche, zweiwöchentliche oder monatliche Serien mit Enddatum und Wochentagen lokalisiert an; nicht berechtigte Konten sehen keine versteckte Scheinoption.
- Die Commerce-Verwaltung bietet jetzt einen geschützten, lokalisierten CSV-Export der Bestellungen mit Dateiauswahl und Kopier-Fallback für Plattformen ohne Speicherdialog.
- Teamdetails enthalten jetzt eine API-gebundene Strafkasse für Regeln, offene Gebühren sowie „bezahlt“/„storniert“; Teammitglieder sehen nur eigene Gebühren, Teamverantwortliche erhalten die Verwaltungsaktionen.
- Der Team-Kalender lädt kommende Termine jetzt über die geschützte Events-API, zeigt Datum und Ort responsiv und öffnet die vollständige Event-Detailansicht ohne Platzhalterdaten.
- Teamdateien werden im Teamdetail jetzt über den geschützten Datei-Workspace geladen, mit Typ/Größe angezeigt und direkt in der serverseitigen Vorschau geöffnet; der vollständige Dateimanager startet im Teamkontext.
- Der Team-Chat lädt jetzt ausschließlich die angeforderte Teamkonversation über einen serverseitig geprüften `team_id`-Scope, zeigt letzte Nachricht und Mitgliederzahl und öffnet die bestehende Chat-Detailansicht; berechtigte Mitglieder können den Chat serverseitig eröffnen.
- Der globale Freigabe-Link im Dateimanager öffnet jetzt eine echte Dateiauswahl und kopiert erst nach erfolgreicher, geschützter API-Erstellung den zeitlich begrenzten Link.
- Die Benachrichtigungszentrale bietet jetzt eine geschützte „Alle als gelesen markieren“-Aktion mit optimistischem UI-Zustand, lokalisiertem Fehlerfeedback und bestehender Einzelstatus-Steuerung.
- Event-Details enthalten jetzt echte, geschützte Abstimmungen: Verantwortliche erstellen Fragen mit mehreren Optionen, sichtbare Mitglieder stimmen direkt ab und sehen Ergebnis, Abschlussstatus und die eigene Auswahl.
- Vereinsprofile enthalten jetzt geschützte Umfragen mit Zielgruppe „alle Mitglieder“ oder „Team“, optionalem Quorum, anonymisierter Auswertung, eigener Stimme und Abschlussaktion für Verantwortliche.
- Vereinsprofile enthalten jetzt geschützte Ankündigungen mit Zielgruppe „alle Mitglieder“ oder „Team“, In-App-/Push-Hinweis und direkter Lesebestätigung pro Mitglied.
- Das Vereinsmanagement enthält jetzt einen lokalisierten Rollen-Editor; Rollenwechsel werden mit Akteur, vorheriger/neuer Rolle und Zeitstempel im Audit-Verlauf angezeigt.
- Das eingeloggte Support-Hilfecenter erstellt jetzt echte API-Tickets und zeigt den persönlichen Verlauf mit lokalisierten Statuswerten; Gastanfragen bleiben beim sicheren Kontaktfluss.
- Support-Mitarbeiter erhalten jetzt eine geschützte SLA-/Eskalationsansicht mit offenen, dringenden und überfälligen Tickets, Zuständigkeit, Status, Priorität, internen Notizen und lokalisierter Oberfläche.
- Benachrichtigungskanäle und Ruhezeiten werden jetzt über die geschützte Settings-API synchronisiert; serverseitige Push-Auslieferung respektiert deaktivierte Nutzerkanäle und Ruhezeiten.
- Ein täglicher, lokalisierter E-Mail-Digest fasst ungelesene In-App-Benachrichtigungen zusammen, protokolliert Versandstatus und verhindert doppelte Zustellung; deaktivierte E-Mail-Präferenzen werden respektiert.
- Der Vereinsmitgliederimport zeigt vor dem Schreiben eine geschützte Vorschau mit gültigen Zeilen, Dubletten, Fehlergründen und vorhandenen Konten; der Manager bestätigt den Import erst danach.
- Wenn eine Importdatei keine sichere E-Mail-Spalte enthält, kann der Manager die wichtigen Spalten mobil zuordnen; dieselbe Zuordnung wird anschließend beim bestätigten Schreibimport verwendet.
- Beitragsregeln unterstützen jetzt mobile Standard-, Familien- und Sonderbeiträge sowie prozentuale oder feste Rabatte; Mitgliedsanträge zeigen Grundbetrag, Rabatt und effektiven Endbetrag transparent an.
- Familien-/Haushaltsgruppen können jetzt vereinsbezogen per sicherem Schlüssel zugeordnet werden; ab zwei aktiven Mitgliedern werden Familienbeitrag und Intervall automatisch für die gesamte Gruppe neu berechnet.
- Der persönliche Onboarding-Einstieg zeigt jetzt die serverseitige Konto-Checkliste mit echtem Fortschritt, lokalisierten offenen Schritten und direktem Übergang zur Fortschrittsseite.
- Externe Mitglieder können jetzt in der geschützten Vereinsverwaltung bearbeitet, eingeladen, entfernt und einer Familiengruppe zugeordnet werden; Beiträge und Intervalle werden bei einer vollständigen Gruppe neu berechnet.
- Vereinsbezogene Berechtigungen sind jetzt feingranular: sichere Rollenstandards, explizite Mitglieds-/Finanzrechte pro Verein, serverseitige Durchsetzung, Audit-Log und ein lokalisierter mobiler Berechtigungsdialog.
- Vereinsprofile zeigen jetzt den echten Mitgliedschaftsstatus und bieten für berechtigte Mitglieder eine sichere Pausenanfrage mit Zeitraum sowie den API-gebundenen Vereinsaustritt mit Owner-/Schuldenprüfung.
- Vereinsaustritte laufen jetzt als terminierte, managerbestätigte Anträge mit Begründung, Prüfung offener Rechnungen, automatischer Umstellung auf „ehemalig“ und Entfernung aus Teams.
- Die digitale Mitgliedskarte ist jetzt API-gebunden: kurzlebige, rotierbare Check-in-Codes zeigen nur Minimaldaten, werden als QR-Code angezeigt und können von berechtigten Vereinsverantwortlichen direkt in der Kamera gescannt und einem Event zugeordnet werden.
- Die Trainings-Übungsbibliothek ist jetzt API-gebunden: persönliche, Team- und Vereinsübungen können gesucht, sicher verwaltet und direkt als stabile Momentaufnahme in Trainingspläne übernommen werden.
- Die Trainingsfortschrittsseite zeigt jetzt API-gebundene Einheiten-, Umfangs- und Belastungstrends mit verständlichen Warnsignalen; private Trainingsdaten bleiben auch im Trainerblick ausgeblendet.
- Der Trainingsbereich enthält jetzt einen API-gebundenen Verfügbarkeitsstatus für verfügbar, eingeschränkt, Pause, verletzt oder krank mit Zeitraum, Sichtbarkeit und optionaler persönlicher Notiz; private Hinweise werden serverseitig geschützt, Team-/Traineransichten bleiben datensparsam.
- Trainingspläne können jetzt als unabhängige Vorlagen gespeichert, im eigenen Vorlagenbereich geladen und mit optionalem Zeitraum wieder als bearbeitbarer Entwurf instanziiert werden; Sichtbarkeit und Zuweisungen bleiben serverseitig geprüft.
- Der neue Bereich „Sport-Apps & Gesundheitsdaten“ zeigt Providerstatus, sichere Kontoverbindungen, Synchronisationsstatus und letzte Aktivitäten; normalisierte Importe mit optionalen GPS-Samples sind idempotent API-gebunden und in allen vier Sprachen erreichbar.
- „Sport-Apps & Gesundheitsdaten“ ist zusätzlich als eigener, übersetzter Modul-/Drawer-Einstieg erreichbar und nicht mehr nur über die Einstellungen verborgen.
- Der versionierte Mobile-Meta-Vertrag meldet Sportintegrationen jetzt explizit mit Providerstatus, Konten, Sync, normalisiertem Import sowie GPS-/GPX-Fähigkeiten; Clients können die Funktion sicher erkennen.
- Die globale Suche liefert jetzt sichtbarkeitsgeprüfte Treffer für Personen, Vereine, Teams, Events, Kurse, Produkte und Dateien; mobile Treffer öffnen direkt den passenden Detail- oder Arbeitsbereich.
- Der Eltern-/Guardian-Bereich bietet jetzt einen sicheren Kinderüberblick: Nach Zustimmung erscheinen ausschließlich anstehende Termine und 28-Tage-Trainingssummen; private Notizen, Nachrichten, Straßenadressen und Detailprofile werden nicht ausgeliefert.
- „Upload starten“ im erreichbaren Datei-Operationsbereich öffnet jetzt direkt den echten Datei-Picker und den serverseitigen Upload-Flow des Datei-Managers statt einer UI-Ergebnis-/Platzhalterseite.
- Öffentliche Vereinsauswahl für Mitgliedschaftsanträge mit serverseitig geprüfter Sichtbarkeit und ohne Offenlegung sensibler Finanz-/Mitgliederdaten.
- Echter Mitgliedschaftsantrag mit Vereinsauswahl, Zahlungs-/Beitragsoptionen, Einwilligungen, Validierung, Fehlerzuständen und API-Übermittlung.
- Mitgliedschafts-Einwilligungen werden jetzt mit stabiler Dokumentversion, Zeitstempel, Bestätigungsmethode und optionaler Namensbestätigung im Antragsstatus nachvollziehbar angezeigt.
- Echter Status-/Rückzugsablauf für Mitgliedschaftsanträge sowie API-basierter Vereins-Posteingang mit Prüfen, Genehmigen, Ablehnen und Notiz.
- Die erreichbare Mitgliedsanfrage-Statusseite ist vollständig in Deutsch, Englisch, Französisch und Arabisch lokalisiert; Vereinsname und Status kommen aus der API, der Zeitverlauf und die Aktionen bleiben auch mit großer RTL-Schrift responsiv.
- Die Vereins-Inbox für Mitgliedsanträge ist jetzt vollständig lokalisiert, filtert anhand echter API-Statuswerte und bleibt mit großen arabischen RTL-Schriften ohne Material- oder Layout-Warnungen bedienbar.
- Vereinsverwaltung lädt jetzt den tatsächlich verwaltbaren Verein, speichert Antragsschalter, Feldmodi, Zahlarten und Dokumentverknüpfungen über die geschützte API.
- Die Membership-Admin-Seite lokalisiert Feldkatalog, Bereiche und Prüfentscheidungen in vier Sprachen und stapelt Kennzahlen auf schmalen RTL-Ansichten ergonomisch.
- Vereins-Profilbearbeitung lädt und speichert Name, Sportart, Adresse, Sichtbarkeit und Posting-Regeln des berechtigten Vereins über die API.
- Mitglieder-, Beitrags- und Finanz-Einstiege verwenden die vollständige, berechtigungsgeprüfte Managementseite statt statischer Beispielzeilen.
- Altersfreigaben zeigen jetzt den echten kontobezogenen Fortschritt, Checklisten und Sicherheitsbereiche über die geschützte Maturity-API, übersetzt in vier Sprachen.
- Medienrichtlinien führen zu den realen Datei-, Datenschutz-, Guardian- und Support-Flows; statische Dokument-/Sichtbarkeitsvorschauen wurden aus den Vereins-Einstiegen entfernt.
- Legacy-Operations- und Vereins-Unterseiten öffnen nun die vorhandenen API-Zentren für Teams, Training, Mitgliedschaften, Chat, Marketplace, Billing, Rollen und Plattform-Moderation.
- Öffentliche Interessenanfragen verwenden jetzt ein übersetztes Formular mit Validierung, Datenschutzeinwilligung, rate-limitiertem Gast-Endpunkt und sichtbarem Erfolgs-/Fehlerzustand.
- Der öffentliche Anfrage-Flow zeigt keine internen Kategorienamen mehr; Anfragearten und der Einstieg zum Standortvorschlag sind in Deutsch, Englisch, Französisch und Arabisch lokalisiert.
- Öffentliche Blog-, Sponsor-, Vereins-, Marketplace- und Lernkatalog-Abfragen verwenden jetzt einen gemeinsamen IP-basierten Rate-Limiter gegen automatisiertes Auslesen.
- Fehlende Admin-Berechtigungs- und Vereinsprofil-Ladehinweise sind in allen vier unterstützten Sprachen ergänzt.
- Nicht eingeloggte Profil-Fallbacks zeigen keine erfundene lokale E-Mail-Adresse mehr.
- Der Release-Fallback für API- und Medien-URLs verwendet jetzt konsistent `https://app.airmius.com`, passend zu App-Links, Runbook und Release-Skripten.
- Der öffentliche Standortflow nutzt auf kleinen RTL-/Großschrift-Ansichten automatisch umbrechende Auswahlchips; dekorierte Panels verankern interaktive ListTiles jetzt korrekt für sichtbare Ripple- und Auswahlzustände.
- Inhaltsmeldungen und die Profilbearbeitung verwenden jetzt echte Übersetzungen in Deutsch, Englisch, Französisch und Arabisch; das Profil-Dropdown bleibt bei großer RTL-Schrift auf kleinen Ansichten ohne Überlauf.
- Gemeinsame Bestätigungsdialoge verwenden ohne eigene Beschriftung jetzt einen neutralen, lokalisierten „Bestätigen“-Button statt eines falschen „Zurückziehen“-Texts.
- Die Sportauswahl im Feed-Komposer lädt jetzt echte Sportarten und Skills aus der API; die erweiterten Dropdowns bleiben auch bei großer arabischer RTL-Schrift ohne Überlauf bedienbar.
- Geschützte Deep Links prüfen die aktive Sitzung vor dem Öffnen eines API-Screens und zeigen Gästen stattdessen einen lokalisierten Authentifizierungs-Gate ohne API-Datenzugriff.
- Profil-Hero, Profil-Tabs, Vereins-Wizard, Dateiaktion und Feed-Senden berechnen den Vordergrundkontrast jetzt aus der aktiven Palette; Schließen-/Kopieren-Aktionen sowie die Story-Navigation haben zusätzlich lokalisierte Tooltips für Screenreader und große Touch-Ziele.
- Die Dashboard-Dateikachel verwendet jetzt geschützte Daily-Flow-API-Daten für Dateianzahl und Speicherverbrauch statt fester Beispielwerte; leere oder nicht erreichbare Live-Daten werden klar mit Wiederholen-Aktion angezeigt.
- Der Datei-Manager blendet Speicherverbrauch und Limit aus, solange der Server keine echten Nutzungsdaten liefert; ein irreführender 1-GB-Fallback während Laden/Fehler wurde entfernt.
- Deep-Link-Routing verwendet für Erkennungs- und Fallback-Karten die aktive Farbpalette; unbekannte Links sowie sichtbare Routing-Ziele und Beschreibungen sind jetzt auch auf Französisch und Arabisch verständlich lokalisiert.
- Der Drawer-Einstieg „Nachrichten“ öffnet jetzt zuverlässig die echte Chat-Inbox statt der Benachrichtigungszentrale; dieser Pfad ist zusätzlich per UI-Regressionstest abgesichert.
- Die Legacy-Einstiege „Rollen & Rechte“ und „Gamification-Regeln“ öffnen das gemeinsame API-Adminzentrum jetzt direkt im jeweils passenden Tab statt irreführend bei „Nutzer“ zu starten.
- Zentrale Outfit-Abo-Begriffe wie Plan, Anfrage, Vertrag und Abonnement sind jetzt auch in Französisch und Arabisch übersetzt statt auf englische Fallbacks zurückzufallen.
- Datei-Bereiche, Dateinamen, Serverstatus und Lesefehler sind in Französisch und Arabisch ebenfalls sichtbar lokalisiert.
- Dateioperationen, Vorschau, Freigabe- und Token-Zustände sind jetzt in Französisch und Arabisch vollständig abgedeckt, einschließlich Upload, Download und Teilen.
- Mitgliedschaftsstatus, Rückzug, Antragsfehler und Inbox-Aktionen sind jetzt ebenfalls vollständig auf Französisch und Arabisch übersetzt.
- Der Feed-Bildproxy ist jetzt durch Sanctum und die Post-Sichtbarkeitsrichtlinie geschützt; private Bilder werden weder Gästen noch unberechtigten Konten ausgeliefert.
- Feed-Bildantworten geben keine öffentliche Storage-URL mehr aus; die Mobile-App lädt den geschützten Proxy mit dem aktuellen Bearer-Token und verhindert damit direkte Speicher-Bypässe.
- Alte Nutzer-, Gastpreis-, Gastblog- und Public-Growth-Einstiege zeigen keine lokalen Demo-Karten mehr, sondern öffnen die echten permission-/API-gebundenen Zentren.
- Commerce-, Outfit-, Mail- und System-Admin-Oberflächen haben jetzt vollständige französische und arabische Übersetzungen für Formulare, Statuswerte, Dialoge und Aktionen.
- Das Gastportal öffnet jetzt ein echtes, API-basiertes Vereinsverzeichnis mit Suche nach Verein, Sport und Ort; es zeigt nur verifizierte, gelistete und datensparsame Vereinsdaten und führt sicher zum Kontaktfluss.
- Standortvorschläge und Korrekturen laufen jetzt über ein lokalisiertes, validiertes Gastformular mit Moderationshinweis und API-Übermittlung statt einer UI-Demo.
- Das Gastportal führt jetzt zu echten, sicheren öffentlichen Bereichen für Blog, Partner, Lernen, Marketplace, Kontakt und Recht; interne Operations-FABs wurden aus öffentlichen Einstiegen entfernt.
- Top-Inhalte laden veröffentlichte Blog- und Partnerdaten aus der API und bieten übersetzte Filter-, Lade-, Fehler- und Leerzustände.
- Zertifikate können öffentlich per Code über einen sicheren, abgeschlossenen Lernstatus geprüft werden; sensible Kontodaten und der geschützte PDF-Download bleiben verborgen.
- Der öffentliche Marketplace lädt veröffentlichte Angebote sicher aus der API, unterstützt Suche und Kategorien und führt Interessierte über ein echtes Kontaktformular zum Anbieter.
- Der öffentliche Lernbereich lädt veröffentlichte Kurse aus der API, unterstützt Suche/Kategorien und verknüpft Zertifikatsprüfung und Kursinteresse mit echten Flows statt statischer Beispielkarten.
- Legacy-Einstiege für Badges, Fahrgemeinschaften, Routen, Trainingslogs, Mahlzeiten, Skill-Empfehlungen, Datenrechte und Blog öffnen jetzt die echten API-Zentren statt lokaler UI-Demos.
- Legacy-Einstiege für Sportprofile, Einstellungen und Recht öffnen nun ebenfalls die API-gebundenen Zentren ohne Operations-FABs oder lokale Speicherdemos.
- Die öffentliche Detailansicht ist vollständig übersetzt und zeigt keine Roadmap-/Demo-Versprechen mehr; jede Aktion führt in einen aktuellen öffentlichen Katalog oder sicheren Kontaktfluss.
- Das eingeloggte Dashboard und der Tagesflow laden Trainings-, Routen-, Ernährungs-, Wasser- und Hinweisdaten aus dem geschützten Daily-Flow-Endpunkt; leere oder offline Daten werden klar dargestellt statt mit Beispielwerten gefüllt.
- Der Tagesflow ist als zugänglicher Schnellstart erreichbar, zeigt Fortschritt und Coach-Hinweis aus der API und öffnet die echten Trainings-, Karten-, Ernährungs- und Benachrichtigungsbereiche.
- Rollen, Gamification-Regeln und Nutzer sind als permission-geschützte API-Einstiege sichtbar; Outfit-Abos bleiben als eigener, echter Nutzerbereich erreichbar.
- Nachrichten unterstützen jetzt echte, authentifizierte Dateianhänge bis 10 MB pro Datei (maximal fünf), inklusive lokalisierter Auswahl, Vorschauchips, Entfernen und serverseitiger Berechtigungsprüfung.
- Nachrichtenkarten zeigen jetzt die echte serverseitige Mitgliederzahl mit korrekter Singular-/Plural-Lokalisierung statt eines festen Demo-Werts.
- Arbeitsbereiche zeigen jetzt serverseitige Vereine, Teams, Rollen und offene Teameinladungen; Einladungen können direkt aus dem Detailbereich angenommen werden, ohne Demo-Zahlen oder Schein-Token.
- Workspace-Kontextwechsel öffnen jetzt die echten API-Zentren für Gast, Tagesflow, Trainer, Verein und Administration; lokale Schalter ohne Persistenz wurden entfernt und Workspace-Einstellungen führen in die persistente Einstellungsseite.
- Workspace-Detailbereiche zeigen nur noch serverseitig bekannte Berechtigungen oder einen klaren Hinweis, wenn diese Daten nicht geliefert wurden; Rollen-/Audit-Bezeichnungen und Nachrichten-Typen sind in allen vier Sprachen lokalisiert.
- Der Feed-Composer übersetzt Zielgruppe, Beitragstyp, Sport-/Teamauswahl, Medienhinweise und Upload-Fehler in Deutsch, Englisch, Französisch und Arabisch.
- Öffentliche Nutzerprofile zeigen jetzt nur noch serverseitige Sportprofildaten; Freundschaftsanfragen, Annahme/Ablehnung, Entfernen, vorausgewählte Nachrichten und Profilmeldungen laufen über geschützte API-Aktionen statt lokaler Demo-Zustände.
- Dashboard-Kompatibilitätseinstiege für Freunde und Nachrichten öffnen jetzt dieselben API-Zentren wie die Hauptnavigation; Beispielkontakte, Beispielnachrichten und UI-only-Sendeaktionen wurden entfernt.
- Der Vereinsbereich bietet jetzt direkt eine lokalisierte Suche nach Vereinen und Teams, damit neue Mitgliedschaftsanträge auch aus einem leeren Vereinsbereich ergonomisch gestartet werden können.
- Modulübersichten zeigen im normalen MVP-Modus keine erfundenen Kennzahlen oder Beispielzeilen mehr; jeder sichtbare Einstieg führt in das zuständige API-Center mit echten Lade-, Leer- und Fehlerzuständen.
- API-Fehlermeldungen in Vereins-, Team-, Antrag-, Feed- und Update-Aktionen geben keine rohen Serverantworten mehr aus, sondern sichere, verständliche Nutzerhinweise.
- Technische SQL-, Framework-, Dateipfad- und KI-Exceptiontexte werden weder von der App noch von den Trainings-/Ernährungs-Controllern an Nutzer durchgereicht; Details bleiben serverseitig protokolliert.
- Die zentralen Bereiche Ernährung, Fahrgemeinschaften, Sportkarte, Abos, Commerce, Lernen, Marketplace und Freunde verwenden ebenfalls sichere, lokalisierte Fehlerhinweise ohne rohe Exception-Texte.
- Der Mitgliedschaftsantrag verwendet jetzt stabile interne Auswahlwerte für Mitgliedschaft, Zahlart und Intervall; Adress- und Bankfelder sind in allen vier Sprachen lokalisiert und die Formularflächen passen sich der aktiven Palette an.
- Teameinladungen laden nach dem Widget-Lifecycle zuverlässig echte API-Daten, zeigen Status, Rolle und Aktionen übersetzt in Deutsch, Englisch, Französisch und Arabisch und bleiben mit RTL, großer Schrift und aktiver Palette bedienbar.
- Antragstatus, Vereins-Inbox, Chatblasen, Datei-Vorschau, Upload-Aktion, Dashboard-Aktion und Admin-FAB übernehmen nun adaptive Akzent-, Flächen-, Rahmen- und Textfarben statt fester Dark-Theme-Werte.
- Datei-Freigaben erzeugen jetzt über einen geschützten API-Endpunkt echte, auf 1–30 Tage begrenzte Links mit gehashtem Token und Rate-Limit; die App kopiert den Link erst nach erfolgreicher Serverantwort.
- Ordner-Freigaben sind jetzt ebenfalls echt: Die App lädt die Freundesliste, lässt eine Zielperson auswählen und kopiert den gesamten Ordnerbaum serverseitig nur bei bestehender Freundschaft, inklusive Benachrichtigung und Rate-Limit.
- Die separate Freigabeansicht zeigt keinen erfundenen Token oder simulierte Passwort-/Ablauf-Schalter mehr; mit einem echten Link öffnet, lädt oder kopiert sie den geprüften HTTP(S)-Link, ohne Link bleibt ein klarer Leerzustand.
- Die Dateivorschau zeigt jetzt echte Backend-Metadaten und bietet nur noch Download/Freigabe-Aktionen; lokale Schalter für angebliche Antrags- oder Profilverknüpfungen sowie doppelte Test-Link-Aktionen wurden entfernt.
- Android-Release-Builds verweigern jetzt den Build, wenn kein expliziter Release-Key über `android/key.properties` konfiguriert ist; ein versehentliches Debug-signiertes AAB ist damit ausgeschlossen.

Vor dem Play-Console-Upload bleiben die Release-Prüfungen und der manuelle Android-Gerätetest als unabhängige Freigabegates bestehen.
Der Sammel-Release verwendet den erhöhten `versionCode` `11`, damit der Upload auch dann akzeptiert wird, wenn Version `1.0.9+10` bereits intern getestet oder in Play Console verwendet wurde.

Web-Sammelstand (26.07.2026):

- Der eingeloggte Trainingsbereich ist ergonomischer für tägliche Nutzung: zentrale Sport-, Status-, Plan-, KI- und Dokumentationsbegriffe folgen DE/EN/FR/AR, Datum/Zeit/Distanz passen sich der Sprache an und leere sowie fehlerhafte Zustände nennen die nächste sinnvolle Aktion.
- Fahrgemeinschaften, Outfit-Checkout und Admin-Moderation führen Datenschutz-/Statusfeedback, regionale Datums-/Geldformate und bestätigte destruktive Aktionen konsistent zusammen; Event-Wizard und Zahlungszentrale verwenden lokalisierte Validierungs-, Status- und Leerzustände in DE/EN/FR/AR.
- Dashboard, Ernährung, Gast-Marketplace, Vereinsfinanzen, Admin-Commerce und Abo-Rechnungen verwenden regionale Zahlen-/Datumsformate sowie lokalisierte Fehler-, Leer- und Statuszustände; Subscription-Planaktionen fragen vor Änderungen verständlich nach.
- Dateien und Chat verwenden lokalisierte Upload-, Suche-, Freigabe-, Leer-, Fehler- und Nachrichtenstatus; wichtige Aktionen sind mit klaren Touch-Zielen und verständlichen Beschriftungen erreichbar.
- Commerce lokalisiert Shop-Navigation, Checkout-Anbieter, Bestell-/Versand-/Erstattungs-/Auszahlungsstatus sowie Geld- und Datumsformate für Deutsch, Englisch, Französisch und Arabisch.
- Freundschaftsanfragen, Annahme/Ablehnung, Meldung, Entfernen und Leerzustände sind im Web-Freunde-Bereich ebenfalls zentral in allen vier Sprachen beschrieben.
- Vereins-/Team-Aktionen verwenden jetzt einen gemeinsamen DE/EN/FR/AR-Katalog für Registrierung, Einladungen, Beitrittsanfragen, Rollen, Sponsoren, Jobs und destruktive Bestätigungen; Wizard-Schritte und Bearbeitungstabs reagieren auf Sprachwechsel.
- Ernährung nutzt für Wasser, KI-Fotoanalyse und Fortschrittsfeedback semantische AIRMIUS-Akzentfarben; globale Layout-Feedbacks, Benachrichtigungszeiten sowie Event-, Commerce-, Vereins- und Teamdetails folgen der aktiven Sprache und Region.

English summary:

- Unified responsive design system with multiple palettes, light/dark/system modes and clearer type scales.
- Main areas now derive their app-bar and surface colors from the active palette, keeping light, dark and high-contrast navigation consistent across page changes.
- Team, friends, badges, blog, sponsor and nutrition pages now derive neutral text, surface and border colors from the active theme instead of fixed dark-theme values.
- Club status, role, warning and membership actions now also use the active theme color scale, keeping the club workflow readable in light, dark and high-contrast palettes.
- Accessibility improvements for large text, RTL/Arabic, touch targets, keyboard focus, semantics and error feedback.
- Local login validation with translated, actionable messages.
- Profile and account management for profile photos, password changes, sessions and protected account deletion.
- Email verification, two-factor security, recovery codes and authentication gates.
- The account/security hub is fully localized; password recovery, 2FA, email verification, profile completion, support and account deletion now open real screens instead of placeholder actions.
- Privacy, email verification and two-factor security now use adaptive theme tokens for protection status, warnings and errors; the 2FA workflow is also covered in Arabic RTL light mode with large text.
- Registration stacks address fields on compact screens and uses an expanded gender dropdown, keeping Arabic RTL usable at 1.25× text without overflow.
- The outfit-subscription checkout stacks country/postal code and street/house number on narrow screens, keeping paid enrollment readable with large Arabic RTL text.
- German, English, French and Arabic localization with a neutral fallback for incomplete entries.
- The profile-completion gate for new accounts is now localized in all four languages and follows light, dark and high-contrast palettes, including minor/guardian guidance.
- External payment, document and marketplace links are validated for safe HTTP(S) schemes, hosts and the absence of embedded credentials before opening.
- Payment, learning and legal links now use the same centralized URL validation and reject embedded credentials and invalid schemes.
- Images, avatars, club logos, galleries and video media from API responses are normalized and loaded only from permitted HTTP(S) sources, with clear fallbacks for invalid media.
- Deep-link previews now localize status, event, messaging and retry feedback in German, English, French and Arabic as well.
- User profiles and media previews now expose image content to screen readers; avatar, gallery and video fallbacks remain understandable when sources fail.
- Offline sync now refuses to persist password, authentication, session and deletion requests.
- Additional UI/API flows for clubs, teams, training, events, messaging, marketplace, files, support, admin and notifications.
- Training plans can now be saved as independent, server-scoped templates and instantiated later with optional dates as editable drafts; assignments and visibility remain permission checked.
- The new “Sport apps & health data” area shows provider status, protected accounts, synchronization state and recent activities; normalized imports with optional GPS samples are idempotent, API-backed and localized in all four supported languages.
- “Sport apps & health data” is also available as its own localized module/drawer entry instead of being reachable only through settings.
- The versioned mobile meta contract now explicitly advertises sport integrations, account sync, normalized imports and GPS/GPX capabilities so clients can detect the feature safely.
- Global search now returns visibility-checked people, clubs, teams, events, courses, products and files; mobile results open the matching detail or workspace flow directly.
- The directory search starts empty instead of issuing a fabricated demo query, debounces live input, and keeps loading, error and empty states localized and palette-aware across all four languages, RTL and large text.
- Marketplace prices, reviews, availability, cart, checkout, orders and issue states now also use the active theme colors; the French light/trail layout remains readable with large text.
- Protected event deep links now load the concrete event detail from the API after the session check; guests see an authentication gate and denied requests show a localized retry state instead of a generic event list.
- Protected feed deep links now load the concrete post from the API after the session check and open the real detail screen; visibility and moderation are enforced server-side instead of trusting post data from the URL.
- Protected chat and notification deep links now resolve title, access and content through the API before opening; invalid or foreign IDs show a localized error/retry state instead of a generic section.
- Individual message deep links now load a protected preview and then the exact conversation; participant, group-join and per-user hide rules are enforced server-side.
- Profile links with a user ID now load the privacy-filtered sport profile and open the real profile detail; private profiles expose only a neutral, data-minimized view.
- Membership deep links now resolve the concrete application by ID and match it to the club-scoped API record; foreign or missing applications are never replaced by the first club request.
- Guardians can now open a protected child overview after consent, showing only upcoming schedules and 28-day training aggregates; private notes, messages, street addresses and detailed profiles stay out of the API response.
- Team calendars and team files now load from protected, team-scoped APIs; dates, locations, file metadata and detail navigation remain responsive and accessible.
- Team chat now requests only the selected team scope through the protected API, shows the latest message and member count, and opens the existing realtime chat detail flow with server-side membership checks.
- The file manager share action now opens a real file picker and copies a time-limited protected link only after the API has created it successfully.
- The notifications center now provides a protected “mark all as read” action with optimistic UI feedback, localized error handling and the existing per-notification read-state control.
- Event details now include real protected polls: event managers can create multi-option questions, visible members can vote directly, and everyone sees the result, status and their own selection.
- Club profiles now include protected polls for all members or a selected team, with optional quorum, privacy-safe results, personal voting state and a manager close action.
- Club profiles now include protected announcements for all active members or a selected team, with in-app/push notification delivery and a per-member read confirmation.
- Club management now includes a localized member role editor; role changes are shown in an audit history with actor, previous/new role and timestamp.
- The authenticated support centre now creates real API-backed tickets and shows the user's history with localized status values; guest requests continue through the protected contact flow.
- Club contribution rules now support mobile standard, family and special contributions plus percentage or fixed discounts; membership applications expose the base amount, discount and effective total transparently.
- Club managers can assign a safe club-scoped family/household key; once two active members share it, the family contribution and billing interval are recalculated for the complete group.
- The personal onboarding entry now uses the server-side account checklist, shows real progress and localized open steps, and links directly to the progress center.
- External members can now be edited, invited and removed from the protected club workspace, assigned to a family group, and recalculated when the group reaches the active-member threshold.
- If an import file has no confidently detected email column, managers can map the important columns on mobile; the confirmed write uses the same mapping.
- Support staff now have a protected SLA/escalation view for open, urgent and overdue tickets, assignees, status, priority and internal notes, with a localized mobile surface.
- The reachable file-operations “Start upload” action now opens the real file picker and server-backed file-manager upload flow instead of a UI-only result/placeholder page.
- The reachable membership-request status page is fully localized in German, English, French and Arabic; club name and status come from the API, while the timeline and actions remain responsive with large RTL text.
- The club membership inbox is now fully localized, filters by real API status values and remains usable with large Arabic RTL text without Material or layout warnings.
- Membership administration now localizes its field catalogue, sections and review decisions in four languages and wraps metrics ergonomically on narrow RTL layouts.
- Public interest requests now use a translated, validated, rate-limited guest contact flow with clear success and error states.
- The public interest flow no longer exposes internal category identifiers; request types and the location-suggestion entry are localized in German, English, French and Arabic.
- The guest portal now opens a real API-backed club directory with club, sport and location search; it exposes only verified, listed and privacy-safe club data and routes safely to the contact flow.
- Location suggestions and corrections now use a translated, validated guest form and the moderation contact API instead of a UI-only demo.
- The guest portal now links to real, safe public areas for blog, partners, learning, marketplace, contact and legal information; internal operations actions were removed from public entry points.
- Top content loads published blog and partner records from the API with translated filter, loading, error and empty states.
- Certificates can be verified publicly by code against an active, completed learning record; sensitive account data and the protected PDF download remain hidden.
- The public marketplace now loads published offers safely from the API, supports search and categories, and routes interested visitors to a real provider contact form.
- The public learning area now loads published courses from the API, supports search/categories, and connects certificate verification and course interest to real flows instead of static sample cards.
- Legacy entry points for badges, carpools, routes, training logs, meals, skill recommendations, data rights and blog now open real API centers instead of local UI demos.
- Legacy entry points for sport profiles, settings and legal documents now open API-backed centres without operations FABs or local-only save demos.
- The public detail view is fully localized and no longer shows roadmap/demo promises; each action opens a current public catalogue or safe contact flow.
- The authenticated dashboard and Today Flow now load training, route, nutrition, hydration and notification data from the protected daily-flow endpoint; empty/offline data is shown honestly instead of placeholder values.
- Today Flow is available as an accessible quick start, renders API progress and coach context, and opens the real training, map, nutrition and notification centres.
- Roles, gamification rules and user administration are visible as permission-scoped API entry points; outfit subscriptions remain a separate real member area.
- Messages now support real authenticated file attachments up to 10 MB per file (maximum five), with localized picking, removable preview chips and server-side permission checks.
- Conversation cards now show the real server-side member count with correct singular/plural localization instead of a fixed demo value.
- Workspaces now show server-derived clubs, teams, roles and pending team invitations; invitations can be accepted directly from the detail view without demo counts or placeholder tokens.
- Workspace context switches now open the real API-backed guest, Today Flow, trainer, club and admin centres; non-persistent local switches were removed and workspace settings lead to the persistent settings page.
- Workspace detail views show only server-provided permissions or an honest unavailable-data notice; role/audit labels and message types are localized in all four supported languages.
- The feed composer now localizes audience, post type, sport/team selection, media guidance and upload errors in German, English, French and Arabic.
- The feed composer now loads real sports and skills from the API; its expanded dropdowns remain usable with large Arabic RTL text without overflow.
- Protected deep links now verify the active session before constructing an API screen and show guests a localized authentication gate without fetching protected data.
- Profile hero/tabs, club wizard, file actions and feed sending now derive foreground contrast from the active palette; close/copy actions and story navigation also expose localized tooltips for assistive technologies and large touch targets.
- The dashboard file card now uses protected Daily Flow API data for file count and storage usage instead of fixed sample values; empty or unavailable live data is shown honestly with a retry action.
- The file manager hides storage usage and limits until the server provides real usage data; the misleading 1 GB loading/error fallback has been removed.
- Deep-link routing now uses the active palette for recognition and fallback cards; unknown links plus visible routing destinations and descriptions are clearly localized in French and Arabic.
- The drawer's “Messages” entry now reliably opens the real chat inbox instead of the notification centre; this path is covered by a UI regression test as well.
- The legacy “Roles & permissions” and “Gamification rules” entries now open the shared API-backed admin centre on their matching tab instead of landing misleadingly on “Users”.
- Core outfit subscription terms such as plan, request, contract and subscription are now translated in French and Arabic instead of falling back to English.
- File scopes, file names, backend status and read errors are also visibly localized in French and Arabic.
- File operations, previews, sharing and token states are now fully covered in French and Arabic, including upload, download and sharing actions.
- Membership status, withdrawal, application errors and inbox actions are now fully translated in French and Arabic as well.
- The feed image proxy is now protected by Sanctum and the post visibility policy; private images are no longer served to guests or unauthorized accounts.
- Public user profiles now show server-provided sports data only; friend requests, accept/decline, removal, preselected messages and profile reports use protected API actions instead of local demo state.
- Dashboard compatibility entries for friends and messaging now open the same API-backed centres as the main navigation; sample contacts, sample messages and UI-only send actions were removed.
- The club area now offers a localized search entry for clubs and teams, so a new membership request can be started comfortably even when the personal club list is empty.
- Module overviews no longer show invented metrics or sample rows in the normal MVP mode; every visible entry opens the responsible API-backed centre with real loading, empty and error states.
- API errors in club, team, application, feed and update actions no longer expose raw server responses; users receive safe, understandable feedback instead.
- SQL, framework, filesystem and AI exception details are no longer passed to users by the app or training/nutrition controllers; technical details remain server-side for diagnostics.
- Nutrition, carpools, sport map, subscriptions, commerce, learning, marketplace and friends now use safe, localized error feedback without raw exception text as well.
- Der Datei- und Upload-Bereich verwendet jetzt adaptive Hell-/Dunkel-Kontraste für Ordner, Dateien, Filter, Eingaben, Speicheranzeige und Fehlermeldungen; leere und fehlerhafte Zustände bieten klare nächste Aktionen.
- Modulstartseiten zeigen im normalen MVP jetzt keine statischen Beispielzeilen mehr: jede Seite bietet eine lokalisierte, große Öffnen-Aktion direkt zum echten API-Bereich; Deutsch bleibt deutsch, während Englisch, Französisch und Arabisch sauber auf ihre Übersetzungen bzw. den neutralen Fallback zurückgreifen.
- Vereins-, Team-, Profil-, Training-/Event-, Feed-, Nachrichten-, Sportkarten-, Marketplace-, Commerce-, Lern-, Abo-, Outfit- und Jugendschutzdetails übernehmen neutrale Text-, Eingabe-, Flächen- und Randfarben aus dem aktiven Theme.
- Auch Mitgliedschaftsverwaltung, Chatdetail, neue Unterhaltung, Antrag-/Inbox-Workflows, Suche, Checkout, Produkt-/Profil-/Blog-/Sponsor-Details, Lernlektionen und Dateioperationen sind jetzt für Hellmodus, große Schrift und klare Touch-Ziele abgestimmt.
- Die Modulnamen sind nun vollständig für Deutsch, Englisch, Französisch und Arabisch abgedeckt; fehlende Labels fallen nicht mehr unerwartet auf Deutsch oder Englisch zurück.
- Native Deep Links zeigen jetzt lokalisierte Routing-Audits, sichere Fallbacks, API-Detailvorschauen und adaptive Kontraste in allen vier unterstützten Sprachen.
- Vereins-, Finanz-, Commerce-, Trainingsplan-, Rollen-, Admin- und Einladungsdetails übernehmen nun auch adaptive AppBar-, Scaffold-, Eingabe- und Dropdown-Flächen statt fester Dark-Theme-Farben.
- Profil-Sicherheit, Datenschutz/Einwilligung und Vereinsprofilbearbeitung folgen jetzt denselben adaptiven Theme-Tokens für Formulare, Panels, Ränder und Statushinweise.
- Die Benachrichtigungs-/Update-Zentrale nutzt adaptive Chips, Flächen, Icon- und Textfarben auch bei hellen Paletten.
- Die Benachrichtigungszentrale lokalisiert Titel, Filter, Nachrichtenstatus und Push-Einstieg jetzt auch korrekt auf Arabisch; Kennzahlen stapeln sich bei großer RTL-Schrift automatisch.
- Alte Einstiege für Event-Anwesenheit und Trainingsplan-Details öffnen jetzt die API-gebundenen Event- sowie Trainingsplan-/Log-Zentren; statische Demo-Karten und Scheinaktionen wurden entfernt. Der arabische Event-/Trainings-Titel ist ebenfalls lokalisiert.
- Arabische Kernnavigation, Suche, Feed-, Mitgliedschafts- und Statusbeschriftungen sind jetzt vollständig übersetzt; Feed-Lade- und Engagementzeilen umbrechen bei großer RTL-Schrift ohne Overflow.
- Der Dashboard-Ladefehler zeigt jetzt nur noch verständliche API-Nutzerhinweise statt roher Exception-/Servertexte.
- Auch der Trainingsplan-Bildupload verwendet bei unbekannten Netzwerkfehlern einen sicheren lokalisierten Fallback statt roher Exceptiontexte.
- Membership applications now use stable internal values for membership, payment and interval choices; address and bank fields are localized in all four languages and form surfaces follow the active palette.
- Team invitations now load real API data after the widget lifecycle, localize status, role and actions in German, English, French and Arabic, and remain usable with RTL, large text and the active palette.
- Vereins- und Teamprofile übernehmen regionale Datums-/Geldformate sowie sprachwechselreaktive Rollen-, Trainings- und Strafkassenstatus; Beitritts-, Austritts- und Buchungsbestätigungen sind in DE/EN/FR/AR lokalisiert.
- Die Admin-Vereinsprüfung und das Provider-Kosten-Dashboard verwenden aktive Sprache/Region für Status, Hinweise, Zahlen und Geldwerte sowie semantische Warnfarben für helle und dunkle Paletten.
- Betriebskosten und Verträge zeigen regionale Datums-/Geldformate, lokalisierte Fristen-/Status-/Löschzustände und getrennte Summen je Währung; Pagination rendert keine unkontrollierten HTML-Labels mehr.
- Gamification-Regeln zeigen lokalisierte Admin-Texte, Rollen, Kennzahlen und Balance-Hinweise mit regionaler Zahlenformatierung und theme-aware Warnfarben.
- Die Media-Guidelines-Zentrale zeigt lokalisierte Suche, Upload-/Slider-Steuerung, Feedback und Tabellenzustände; Entfernen-Aktionen folgen dem aktiven Theme.
- Das Mailcenter zeigt lokalisierte Versand-, Queue- und Audit-Zustände, regionale Zeitformate sowie sichere Pagination ohne unkontrolliertes HTML und semantische Statusfarben.
- Die Systemsettings zeigen lokalisierte KI-Tokenstatus und Warnungen mit regionalen Ablaufdaten; sensible Einstellungen bleiben 2FA- und berechtigungsgebunden.
- Die Sportartenverwaltung zeigt lokalisierte Filter, Nutzungszahlen sowie klare Erstell-/Löschzustände mit regionaler Zahlenformatierung.
- Die Blog-Kategorienverwaltung zeigt lokalisierte Formulare und Zustände, regionale Kennzahlen und sichere Pagination ohne unkontrolliertes HTML.
- Application status, club inbox, chat bubbles, file preview, upload action, dashboard action and admin FAB now use adaptive accent, surface, border and text colors instead of fixed dark-theme values.
- File sharing now uses a protected API endpoint with a rate-limited, hashed token and a 1–30 day expiry; the app copies a link only after the server creates it.
- Folder sharing is now real as well: the app loads the friend list, lets the user choose a recipient and copies the complete folder tree server-side only for an existing friendship, with notification and rate limiting.
- The standalone shared-file view no longer fabricates tokens or simulates password/expiry switches; with a real link it opens, downloads or copies the validated HTTP(S) URL, and without one it shows a clear empty state.
- The file preview now shows backend metadata and only offers real download/share actions; local switches for pretend application/profile linkage and duplicate test-link actions were removed.
- Android release builds now refuse to proceed without an explicit release key configured through `android/key.properties`, preventing an accidentally debug-signed AAB.
- Native deep links now provide localized routing audits, secure fallbacks, API detail previews and adaptive contrast in all four supported languages.
- Club, finance, commerce, training-plan, role, admin and invitation details now also use adaptive app-bar, scaffold, input and dropdown surfaces instead of fixed dark-theme colors.
- Profile security, privacy/consent and club profile editing now use the same adaptive theme tokens for forms, panels, borders and status feedback.
- The notifications/update centre now uses adaptive chip, surface, icon and text colors across light palettes as well.
- The notification centre now localizes titles, filters, read states and the push entry correctly in Arabic; metrics wrap automatically with large RTL text.
- Legacy event-attendance and training-plan detail links now open the API-backed event and plan/log centres; static demo cards and placeholder actions were removed, and the Arabic event/training title is localized.
- Core Arabic navigation, search, feed, membership and status labels are now translated; feed loading and engagement rows wrap safely with large RTL text instead of overflowing.
- Dashboard loading failures now show understandable API user messages instead of raw exception or server text.
- Training-plan image uploads also use a safe localized fallback for unknown network errors instead of raw exception text.

## Public store release notes

German:

```text
Trainingspläne und durchgeführte Einheiten greifen jetzt direkt ineinander. Neu: Freie Läufe lassen sich ohne vorherige Planung direkt starten, auf einer Laufkarte per GPS aufzeichnen, pausieren und fortsetzen. Auf Android läuft die Aufzeichnung mit sichtbarer Systembenachrichtigung auch bei minimierter App oder gesperrtem Bildschirm weiter. Airmius zeigt aktive Zeit, Distanz und Durchschnittspace und speichert Route, Belastung und Notizen anschließend im Trainingsverlauf. Übungen lassen sich per Drag-and-drop ordnen; Trainer können Vorlagen individuell anpassen und gezielt an Sportler senden.
```

English:

```text
Training plans and completed sessions now work together directly. New: athletes can start a free run without a plan, record it with GPS on a live map, pause and resume it, and see active time, distance and average pace. On Android, recording continues under a visible system notification when the app is minimized or the screen is locked. Route, effort and notes are saved to training history. Exercises can be reordered by drag and drop, while coaches can tailor templates before sending them to selected athletes.
```

French:

```text
Mise à jour majeure d’Airmius Mobile : interface responsive avec modes clair/sombre, grands textes et prise en charge RTL, parcours de compte et 2FA sécurisés, tableau de bord, flux du jour et espaces de travail alimentés par l’API, ainsi que des fonctions localisées pour le fil et la messagerie. Le partage de fichiers et dossiers, les fichiers de chat, les invitations d’équipe, d’amitié et de club ainsi que les médias du fil sont gérables directement avec contrôle des permissions.
```

## TestFlight / internal tester notes

```text
Please test the core mobile flows: login, clubs, membership request, request withdrawal, notifications, messages, events/training, documents, invoices, profile, language/theme persistence and deep links.

Known release gates still require evidence: Flutter analyze, Android/iOS release builds, screenshots, real API QA, secure token storage QA, domain verification, localization QA and privacy/legal sign-off.

Android release gate: declare the `location` foreground-service type in Play Console, provide the requested user-initiated free-run demo video, and keep the in-app location disclosure and privacy policy wording aligned with the submitted declaration.
```

## Play Console release notes checklist

- German release notes added.
- English release notes added if listing locale is enabled.
- No private backend URLs or credentials included.
- No unverified claims such as "fully certified" or "100% complete".
- Known test limitations documented only in internal/reviewer notes, not public marketing copy.

## App Store Connect release notes checklist

- What's New text added.
- TestFlight notes added.
- Reviewer notes reference the review account runbook.
- No credentials committed to repository files.
- Privacy-sensitive features match App Store Privacy labels.

## Evidence to attach

- Screenshot/export of Play Console release notes.
- Screenshot/export of App Store Connect release notes.
- TestFlight notes confirmation.
- Internal release owner approval.

## Pass criteria

- Public release notes are accurate and do not overpromise.
- Internal tester notes list the exact flows and open evidence gates.
- Reviewer notes link conceptually to the safe store review account.
- No secrets, credentials or private data are included.
