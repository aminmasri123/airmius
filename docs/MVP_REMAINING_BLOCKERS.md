# AIRMIUS MVP Remaining Blockers

Stand: 2026-07-07

## Gruen verifiziert

- `php artisan test --compact` ist gruen: 269 passed, 7 skipped, 2106 assertions.
- `npm run build` laeuft erfolgreich.
- `npm audit --audit-level=high` meldet `found 0 vulnerabilities`.
- `flutter test` laeuft erfolgreich: All tests passed.
- `flutter analyze` laeuft erfolgreich: No issues found.
- `php artisan route:list --path=api/v1` zeigt die neuen Mobile-MVP-Endpunkte.
- `php artisan test tests/Unit/ClubMembershipImportServiceTest.php` ist gruen.

## Noch blockierend

### Backend Feature-Suite

Nicht mehr blockierend: SQLite, GD und alle zuvor offenen Backend-Feature-Failures sind repariert. Der volle Lauf erreicht jetzt:

- 269 passed
- 7 skipped
- 0 failed

Erledigte Fehlergruppen:

- Profile-Completion-Redirects durch Test-UserFactory-Gender-Default reduziert.
- Fehlende Sport-CV-, Daily-Flow-, Team-, Trainer-Cockpit-, Marketplace-Trust- und Mobile-API-Routen/Payloads verdrahtet.
- Feed, Story, Event-Teilnahme, Event-Reminder, Marketplace-Reviews/Wishlist, Mobile Meta und Motivation-Vertraege repariert.
- `php artisan test --compact` laeuft gruen.

### Noch nicht automatisiert

- Browser-Smoke fuer Dashboard, Feed, Clubs, Teams, Events und Chat ist inzwischen als Feature-Test abgedeckt.
- Mobile Realgeraet-Test fuer Android/iOS mit Push, Upload und Deep Links bleibt manuell offen; Runbook: `mobile/airmius_mobile/store_listing/release/real_device_smoke_test_runbook.md`.
- Lokaler Stand 2026-07-17: `flutter devices` sieht nur Linux Desktop; Android SDK fehlt laut `flutter doctor -v`; iOS braucht macOS/Xcode/TestFlight.
- Vollstaendiger Security-/Legal-Review vor Produktionsstart.
