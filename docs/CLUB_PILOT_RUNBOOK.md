# Airmius Vereins-Pilot-Runbook

Stand: 2026-08-09  
Vertrag: `club-pilot.v1`

Dieses Runbook bereitet einen geschlossenen Pilot mit drei bis fünf Vereinen vor. Repository-Checks ersetzen weder echte Vereinsnutzung noch Freigaben. Namen, IDs, Kontakte, Mitgliedsnummern, Freitexte, Gesundheitsdaten und Rohereignisse gehören nicht in Pilot- oder Release-Evidenz.

## Verantwortliche

| Rolle | Verantwortung |
|---|---|
| Customer Success (Responsible) | Auswahl, Migration, Schulung, Check-ins und Outcome-Erfassung |
| Product Lead (Accountable) | Pilotumfang, KPI-Ziele, Fortführung oder Abbruch |
| SRE / Support | Betriebsbereitschaft, SLA, Rollback-Drill und Incident-Begleitung |
| Security / Datenschutz | Datenverarbeitung, Incident- und DPIA-Grenzen |
| Club Product / Engineering | Journey-Fehler, sichere Korrekturen und Regressionstests |

## Konfiguration und Prüfung

Pilotvereine werden ausschließlich über die Pilotumgebung konfiguriert:

```dotenv
PILOT_ENABLED=true
PILOT_CLUB_IDS=101,102,103
PILOT_DURATION_WEEKS=8
PILOT_BASELINE_DAYS=28
PILOT_MIN_ONBOARDING_PERCENT=80
```

Die IDs dürfen nicht in Logs, Tickets, JSON-Ausgaben oder Evidenzdateien übernommen werden. Die aggregierte Prüfung gibt nur Kohortengröße, Anzahl gefundener/verifizierter/bereiter Vereine und den mittleren Onboardingwert aus.

```bash
php artisan airmius:audit-club-pilot --json
php artisan airmius:audit-club-pilot --with-data --json
php artisan airmius:audit-club-pilot --with-data --strict
```

Ohne `--with-data` wird keine Clubtabelle gelesen. Der normale Lauf schlägt nur bei einem fehlerhaften technischen Vertrag fehl. `--strict` bleibt rot, bis Laufzeit-, lokale und externe Evidenz vollständig sind.

## Auswahl und Vorbereitung

Jeder der drei bis fünf Vereine benötigt:

- einen verantwortlichen Owner und einen zweiten Admin;
- abgeschlossene Verifikation oder verbindlich terminierte Verifikation;
- eine repräsentative, vorher bereinigte Mitglieder-Importdatei;
- mindestens einen Trainer- und einen Mitglieder-Testkreis;
- bestätigte Support-, Datenschutz- und Rollback-Kontakte außerhalb der Release-Evidenz;
- bestätigte Datenschutz-/Auftragsverarbeitungsbedingungen.

Vor Woche 0 müssen Produktions-Preflight, Backup-/Restore-Probe, Security-/Privacy-Drill, Gast-Discovery, öffentlicher Mitgliedschaftseinstieg und die drei Kernreisen technisch grün sein. Offene DPIA-, Pentest-, Legal-, Sprach-, Accessibility- oder Provider-Gates werden nicht durch Pilotmetriken aufgehoben.

## Zeitplan

| Zeitpunkt | Owner | Abnahme |
|---:|---|---|
| Woche 0 | Customer Success + Product | anonymisierte Baseline, Importvorschau, Rollen, Schulung und Rollback bestätigt |
| Woche 1 | Customer Success | Aktivierung, Login-/Einladungsprobleme und erste Mitgliedschaftsreise geprüft |
| Woche 3 | Product + Customer Success | Trainer-Woche, Sportler-Tag und Gast-Einstieg werden tatsächlich genutzt |
| Woche 6 | SRE + Support | SLA, Fehlertrend, Backup/Restore und keine kritischen Findings |
| Woche 8 | Product Lead | Zielwerte, Restbefunde, Rückbaubarkeit und Rolloutentscheidung signiert |

## KPI-Vertrag

Nur aggregierte Baseline-/Outcome-Werte werden erfasst:

| Kennzahl | Ziel |
|---|---:|
| Onboarding-Abschluss | +10 Prozentpunkte |
| wöchentlich aktive Mitglieder | +5 Prozentpunkte |
| Trainingsdokumentation | +10 Prozentpunkte |
| Durchlaufzeit Mitgliederverwaltung | −20 % |
| Supportlösungen innerhalb SLA | +5 Prozentpunkte |
| kritische Incidents | maximal 0 |

Produktanalyse bleibt einwilligungsgebunden, schließt Minderjährige aus und unterdrückt Gruppen unter der freigegebenen Mindestgröße. Customer-Success-Zeiten dürfen nur als Vereins-/Kohortenaggregat ohne Personen- oder Tickettext erfasst werden.

## Evidenz

1. `resources/release/club_pilot_evidence.template.json` nach `resources/release/club_pilot_evidence.local.json` kopieren.
2. Vereine ausschließlich als `pilot-01` bis `pilot-05` referenzieren.
3. Keine Namen, IDs, E-Mails, Telefonnummern, Adressen, Nachrichten, Notizen oder Freitexte ergänzen.
4. Baseline und Outcome für alle sechs Kennzahlen sowie Journey-, Rollback-, Support- und vier Freigabereferenzen pflegen.
5. Die lokale Datei bleibt ignoriert und ist nicht automatisch autoritativ.
6. Erst nach fachlicher Prüfung nicht-sensitive Nachweisreferenzen in den Gate `club_pilot` des Plattformmanifests übernehmen.

## Rollback

Sofortiger Stop oder Rückbau wird bei bestätigtem Mandantenübergriff, kritischem Privacy-/Security-Incident, nicht ausgleichbarem Billingzustand, wiederholtem Datenverlust/Restore-Fehler oder längerer Nichtverfügbarkeit einer Kernreise ausgelöst.

1. Neue Einladungen, Importe und riskante Mutationen für die Pilotkohorte stoppen.
2. Incident Commander beziehungsweise Product Lead und Clubkontakt über den freigegebenen Kanal informieren.
3. Letztes geprüftes Backup und Outbox-/Queue-Zustand sichern; keine Events oder Nutzerdaten manuell löschen.
4. Vorherige Anwendungsversion ausrollen und Worker neu starten.
5. Additive Daten erhalten; destruktive Migrationen nur separat und nach Restore-Nachweis.
6. Mitgliedschaft, Training, Billing, Gastzugang und Mandantengrenzen mit synthetischen Konten smoke-testen.
7. Wiederaufnahme nur nach Product-, SRE- und gegebenenfalls Security-/DSB-Freigabe.

Ein Rollback gilt erst als geprobt, wenn technische Wiederherstellung, Datenkonsistenz, Kernreisen und Supportkommunikation über eine nicht-sensitive Evidenzreferenz bestätigt sind.
