# Airmius Mobile Localization Release Matrix

This matrix defines localization readiness for the first Airmius Mobile release.

## Supported languages

- German `de`
- English `en`
- French `fr`
- Arabic `ar`

## Release priority

German is the primary release language. English, French and Arabic must be available for core navigation, auth, club discovery, membership requests, notifications, profile and release-critical system states.

Arabic must be checked with RTL layout enabled.

## Must-localize screens before store release

1. Login and onboarding
   - Logo remains visual and does not need translation.
   - Login labels, password recovery, registration, guest entry, language and theme chooser must be localized.

2. App shell
   - Bottom navigation, drawer groups, header actions, search labels and profile labels must be localized.

3. Clubs
   - Search, filters, club cards, member counts, join CTA, request sent state and withdraw copy must be localized.

4. Membership application
   - Personal data, contact data, address data, guardian data, emergency contact, payment data and document labels must be localized.
   - Validation messages must be localized.

5. Notifications and messages
   - Notification titles can come from API, but static UI labels, filters, empty states and error states must be localized.

6. Events and training
   - Tabs, statuses, empty states, RSVP labels and training labels must be localized.

7. Profile and settings
   - Account details, sign-out, refresh, language, theme, privacy and security labels must be localized.

8. Deep-link arrival screens
   - Target detected, auth gate, routing audit, fallback and CTA labels must be localized.

9. Store/release visible screens
   - Release readiness, store configuration and QA labels should be localized enough for internal QA, but German can remain primary for admin-only release suites.

## Translation QA checklist

- No visible raw keys such as `profile.signOut` appear.
- No mixed-language primary CTA on the same screen.
- Dynamic values remain readable in every language.
- Long French labels wrap without clipping.
- Arabic layout is RTL and buttons remain reachable.
- Numbers, dates and currency labels are understandable.
- Error, empty, loading and success states are translated.
- Store screenshots are captured in German first.
- If English/French/Arabic screenshots are used, repeat screenshot QA in that language.

## API localization expectations

- Backend should return user-generated content as entered by users.
- Backend system notifications should provide localized `title` and `body` if the user locale is sent.
- Flutter client sends locale through the API client.
- The app stores selected locale and restores it on startup.

## Technische und menschliche Gates

- Die statische DE/EN/FR/AR-Key-, Platzhalter- und arabische Schriftparität ist automatisiert grün; die App setzt arabische `Directionality` tatsächlich auf RTL.
- Widget-Verträge prüfen mehrere produktive Kernflächen bereits in RTL, Semantics, Textskalierung und responsiven Zuständen.
- Offen bleibt die menschliche Cross-Device-Sichtprüfung mit `cross-device-experience.v1`: Android, iOS, Web-Mobile und Web-Desktop; DE/EN/FR/AR; lange französische Labels; echtes arabisches RTL; Fehler-/Leer-/Lade-/Erfolgszustände.
- TalkBack, VoiceOver, NVDA/JAWS, Keyboard-only, 200/400-Prozent-Zoom, Reflow, Focus-not-obscured, Forced Colors und reduzierte Bewegung benötigen physische beziehungsweise menschliche Evidenz.
- Store-Screenshots und Freigaben werden nur als kurze Artefaktreferenzen übernommen; Rohpfade, Kontakte, Gerätekennungen, URLs und Freitext gehören nicht in die Koordinationsdatei.
