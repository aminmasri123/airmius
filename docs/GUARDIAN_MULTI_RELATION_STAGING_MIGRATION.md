# Mehr-Sorgeberechtigte: kontrollierte Staging-Migration

Stand: 2026-09-26

Dieses Einfuehrungsblatt beschreibt den kontrollierten Staging-Rollout fuer die Migration von Legacy-Guardian-Feldern auf `guardian_child_relationships`. Die Hauptcheckliste bleibt bis zur Pilot-Abnahme unveraendert.

## Ziel

- Legacy-Felder `users.guardian_user_id` und `users.guardian_email` bleiben lesbar, werden aber aus der neuen Beziehungstabelle gespiegelt.
- Pro minderjaehrigem Mitglied koennen mehrere Sorgeberechtigte pro Verein mit Status `invited`, `accepted`, `ambiguous`, `declined` oder `revoked` existieren.
- Primaerkontakt ist eindeutig pro Kind und Verein.
- Datenschutzexport und Teil-Loeschung behandeln die neue Beziehungstabelle explizit.

## Staging-Ablauf

1. Migration `2026_09_26_000026_create_guardian_child_relationships_table.php` auf Staging ausfuehren.
2. Rollen-/Rechte-Seed erneut ausfuehren, damit Guardian- und Vereinsrollen konsistent verfuegbar sind.
3. Legacy-Backfill nur fuer eine begrenzte Vereinsauswahl starten und Datensaetze mit widerspruechlicher E-Mail/Konto-Kombination als `ambiguous` pruefen.
4. Pro Pilotverein mindestens diese Faelle kontrollieren: ein Legacy-Guardian, mehrere eingeladene Sorgeberechtigte, Primaerkontaktwechsel, Widerruf, fremder Verein, normales Mitglied ohne Bearbeitungsrecht.
5. Datenschutzexport fuer Kind und Sorgeberechtigte herunterladen und `guardian.relationships` pruefen.
6. Profil-Teillöschung fuer eine Sorgeberechtigte Person ausfuehren und bestaetigen, dass Kontaktfelder minimiert, aber Club-/Kind-/Statuskontext erhalten bleiben.
7. Erst nach fachlicher Abnahme Backfill auf weitere Vereine ausweiten.

## Rollback

- Bei fachlichen Fehlern: `GuardianChildRelationshipService::rollbackBackfillForChild()` nur fuer betroffene Kinder ausfuehren; manuell erfasste Beziehungen bleiben erhalten.
- Bei Schema-Problemen: Deployment stoppen, neue Schreibpfade deaktivieren und Legacy-Felder weiter nutzen.
- Primaerkontakt-Sync validieren: `users.guardian_user_id` und `users.guardian_email` muessen den akzeptierten Primaerkontakt widerspiegeln.

## Abnahmeevidenz

- Feature-Tests:
  - `tests/Feature/GuardianChildRelationshipServiceTest.php`
  - `tests/Feature/ClubGuardianRelationshipManagementTest.php`
  - `tests/Feature/PrivacyRightsProcessTest.php`
  - `tests/Feature/UserDataErasureTest.php`
- Stichprobe dokumentieren: Pilotverein, Anzahl Kinder, Anzahl Beziehungen, Anzahl `ambiguous`, Export-/Loeschzeitpunkt, Testergebnis.
- Risiken bis Produktivfreigabe: noch offene Legacy-Importquellen, manuelle Klaerung von `ambiguous`, Supportprozess fuer falsch eingeladene E-Mail-Adressen.
