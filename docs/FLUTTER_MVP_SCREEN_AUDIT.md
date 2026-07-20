# Flutter MVP Screen Audit

Stand: 2026-07-08

## Ergebnis

- [x] Exakte `API fehlt`-Hinweise im Flutter-Code entfernt: keine Treffer mehr.
- [x] 218 Flutter-Dateien enthalten noch bewusst vorbereitende Begriffe wie `spaeter`, `Demo`, `UI bereit` oder `vorbereitet`.
- [x] Diese Dateien werden nicht geloescht, sondern zentral ueber die MVP-Oberflaeche gesteuert.
- [x] Standard-MVP blendet Demo-, Parity-, Store-, Release-, Premium- und Admin-Suite-Bereiche aus der Hauptnavigation aus.
- [x] Entwickler koennen die vollstaendige Suite mit `--dart-define=AIRMIUS_SHOW_DEV_SUITES=true` wieder sichtbar machen.

## Behalten Im MVP

- [x] Login, Registrierung, Profil und Profilabschluss.
- [x] Dashboard mit Training, Fokus, Termine, Dateien und Inbox.
- [x] Vereine & Teams inkl. Club-Details, Team-Erstellung, Mitgliedschaft, Mitgliederverwaltung und Club-Speichern.
- [x] Feed.
- [x] Updates, Notifications und Chat-Einstieg.
- [x] Events & Training.
- [x] Dateien.
- [x] Einstellungen, Datenschutz, Vereinsregeln und Basis-Vereinsverwaltung.

## Angebunden

- [x] Club-Speichern nutzt `PUT /api/v1/clubs/{id}`.
- [x] Auth, Profilabruf und Logout nutzen nur `/api/v1`.
- [x] Dashboard zeigt keine Standard-Schnellaktionen mehr auf Sportkarte oder Ernaehrung.
- [x] Operations Hub filtert im Standardmodus nur MVP-relevante Kernpunkte.

## Aus MVP Ausgeblendet

- [x] Developer-/Parity-Suites: UI Coverage, Design System, Web Route Parity, Release-/Store-/Evidence-Screens.
- [x] Commerce/Premium-Suites: Marketplace, Commerce, Sponsoren, Outfit-Abos.
- [x] Erweiterte Sport-Suites: Ernaehrung, Sportkarte, Badges, Gamification, Kurse.
- [x] Erweiterte Admin-/Betriebs-Suites: System Admin, Analytics, Provider, Webhooks, Job Queue.
- [x] Erweiterte Vereins-Suites ohne finalen API-Vertrag: Umfragen, Inventar, Ressourcenbuchung, Meeting-Protokolle, Sponsor CRM.

## Technische Umsetzung

- [x] `AirmiusMvpSurface` definiert sichtbare Module, Dashboard-Widgets und Operations-Hub-Eintraege.
- [x] `ShellScreen` filtert Drawer-Module und versteckt Operations Hub/Gastseite im normalen MVP.
- [x] `DashboardScreen` filtert Dashboard-Widgets ueber `AirmiusMvpSurface`.
- [x] `OperationsHubScreen` zeigt ohne Dev-Flag nur MVP-relevante Eintraege.
- [x] `SettingsCenterScreen` versteckt Operations Hub, Onboarding, UI Coverage und Design System ohne Dev-Flag.

## Dev-Modus

```bash
flutter run --dart-define=AIRMIUS_SHOW_DEV_SUITES=true
```

Damit werden alle vorbereiteten Suite- und Parity-Screens wieder sichtbar.
