# Airmius Staged-Rollout-Runbook

Stand: 9. August 2026  
Vertrag: `staged-rollout.v1`

## Ziel und unverhandelbare Grenzen

Neue integrierte Arbeitsräume werden deterministisch mit `0 → 5 → 25 → 100` Prozent freigeschaltet. Die Zuordnung ist zustandslos und stabil: HMAC über Feature und interne Auth-ID, keine Rollout-Tabelle, kein Cookie, kein Tracking-Event und keine Ausgabe von ID oder Bucket. Öffentliche Gast-, Auth-, Legal-, Privacy-, Discovery-, SEO-, Kontakt-, Marketplace-, Learning-, Recruiting-, Sponsor- und Bestellstatusseiten werden nie personenbezogen gebucketet. Dadurch bleiben ihre Cache-, Indexierungs-, Barrierefreiheits- und Datenschutzverträge unverändert.

## Verantwortlichkeit

| Aufgabe | Responsible | Accountable | Consulted | Informed |
|---|---|---|---|---|
| Stufe konfigurieren und beobachten | SRE + Product | CTO | QA, Support, Security, Datenschutz | Engineering, Customer Success |
| Kill-Switch auslösen | SRE | CTO | Product, Security | Support, Management |
| Tenant-/Autorisierungsprüfung | Backend + Security | CTO | QA, Datenschutz | Product |
| Externe Evidenz freigeben | SRE + Product | CTO | Support, QA | Engineering |

## Konfiguration

Erlaubt sind ausschließlich `0`, `5`, `25` und `100`. Ungültige Werte sperren das Feature fail-closed. Bestehendes Verhalten bleibt mit Default `100` erhalten. `AIRMIUS_ROLLOUT_KILL_SWITCH=true` sperrt alle gestuften Features; die vier featurebezogenen Kill-Switches sperren jeweils nur ihren Arbeitsraum. Der Kill-Switch gewinnt immer vor Pilot- und Rollenzugriff.

Nach einer `.env`-Änderung:

```bash
php artisan config:cache
php artisan airmius:audit-staged-rollout --json
```

Der optionale Pilot-Override gilt nur, wenn der Pilot aktiviert ist, die Club-ID aus Route, geprüftem Header oder Sitzung in der konfigurierten Kohorte liegt und die angemeldete Person tatsächlich mit diesem Club verbunden ist. Eine frei gesetzte Club-ID gewährt keinen Zugriff. Ohne Pilotkontext erfolgt nur die deterministische Nutzerzuordnung.

## Ablauf je Feature

1. Vor `5 %`: automatisierte Suite, Release-Preflight, Tenant-Isolation, Rollback-Probe und externe Pflichtgates prüfen.
2. `5 %` mindestens 24 Stunden beobachten. Keine Roh-IDs oder einzelnen Nutzerpfade in Evidenz übernehmen.
3. `25 %` mindestens 48 Stunden beobachten. Nur aggregierte SLO-, Incident- und Supportnachweise verwenden.
4. `100 %` mindestens 72 Stunden stabil beobachten und CTO-Freigabe dokumentieren.
5. Pro Stufe Fehlerquote, Latenz, kritische Journey, Support-SLA, Autorisierung und Gastseiten-Regression prüfen.

Die Vorlage `resources/release/staged_rollout_evidence.template.json` enthält nur Status und nicht-sensitive Referenzen. Nie Salt, Auth-/Club-/User-IDs, Namen, Kontakte, URL-Parameter, IP-Adressen, Freitext-Supportfälle oder einzelne Buckets eintragen.

## Sofortiger Rollback

Bei Cross-Tenant-Zugriff, Datenschutz-/Security-Incident, Datenverlust, irreversibler Abrechnungsabweichung oder kritischer SLO-Verletzung:

1. globalen oder Feature-Kill-Switch setzen;
2. Konfigurationscache neu erzeugen;
3. betroffene Arbeitsraum-Routen mit Web- und API-Smoke-Test prüfen;
4. Gastseiten separat auf `200`, Cache-/SEO-Header, DE/EN/FR/AR, RTL und Tastaturzugriff prüfen;
5. Incident-Runbook starten und nur datensparsame Evidenz referenzieren.

Das Abschalten löscht oder verändert keine Nutzdaten und benötigt kein Datenbank-Rollback. Web-Nutzer werden mit lokalisierter Meldung zum sicheren Workspace-Wähler geführt; APIs antworten `503`, `feature_rollout_unavailable`, `Retry-After` und `private, no-store` über den vorhandenen API-Vertrag.
