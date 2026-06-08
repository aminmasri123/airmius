# Airmius Mobile Store Release Notes

Use this file to prepare release notes for Play Console, App Store Connect, TestFlight and internal release communication.

## Release candidate

- App: Airmius Mobile
- Version: `1.0.0+1`
- Android application ID: `com.airmius.app`
- iOS bundle ID: `com.airmius.app`
- API environment:
- Release owner:
- Release date:

## Public store release notes

German:

```text
Erste Airmius Mobile App fuer Vereine, Teams und Mitglieder. Enthalten sind Vereinsprofile, Mitgliedschaftsanfragen, Benachrichtigungen, Nachrichten, Events, Dokumente, Finanzen und Profileinstellungen.
```

English:

```text
Initial Airmius Mobile app for clubs, teams and members. Includes club profiles, membership requests, notifications, messages, events, documents, finance and profile settings.
```

French:

```text
Premiere application mobile Airmius pour les clubs, equipes et membres. Inclut les profils de club, demandes d'adhesion, notifications, messages, evenements, documents, finances et parametres de profil.
```

## TestFlight / internal tester notes

```text
Please test the core mobile flows: login, clubs, membership request, request withdrawal, notifications, messages, events/training, documents, invoices, profile, language/theme persistence and deep links.

Known release gates still require evidence: Flutter analyze, Android/iOS release builds, screenshots, real API QA, secure token storage QA, domain verification, localization QA and privacy/legal sign-off.
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
