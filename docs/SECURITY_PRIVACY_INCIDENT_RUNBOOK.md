# Airmius Security-/Privacy-Incident-Runbook

Stand: 2026-08-09  
Vertrag: `security-privacy-acceptance.v1`

Dieses Runbook ist eine technische und organisatorische Arbeitsgrundlage. Es ersetzt weder die Einzelfallentscheidung des Datenschutzbeauftragten noch einen unabhängigen Penetrationstest oder eine Rechtsberatung. Der Repository-Drill führt keine Provider-, Netzwerk-, Benachrichtigungs- oder Löschaktion aus und speichert weder personenbezogene Daten noch Secrets.

## Ausführen

```bash
php artisan airmius:audit-security-privacy
php artisan airmius:audit-security-privacy --json
php artisan airmius:audit-security-privacy --strict
```

Der normale Lauf schlägt nur bei einer fehlerhaften technischen Basis fehl. `--strict` bleibt zusätzlich rot, solange DPIA oder externer Penetrationstest nicht evidenzgebunden freigegeben sind. Das Ergebnis darf keine Logauszüge, Tokens, E-Mail-Adressen, IP-Adressen, Payloads oder Konfigurationswerte enthalten.

## Rollen und Verantwortung

| Rolle | Verantwortlich | Aufgabe im Incident |
|---|---|---|
| Incident Commander | SRE / Operations | Priorität, Taktung, Entscheidungen und Übergaben koordinieren |
| Security Lead | Security Engineering | Angriffsweg, Eindämmung, Behebung und technische Verifikation |
| Privacy Lead | Datenschutzbeauftragter | Datenarten, Betroffene, Risiko und Melde-/Informationsentscheidung |
| Technical Owner | Zuständiges Engineering-Team | Betroffenen Dienst sicher stoppen, reparieren und wiederherstellen |
| Communications Owner | Geschäftsführung / Kommunikation | Freigegebene interne und externe Kommunikation |
| Evidence Owner | Security / Legal | Minimierte, zugriffsbeschränkte und manipulationsgeschützte Evidenzkette |

Niemand genehmigt seinen eigenen Hochrisiko-Fix allein. Nur benannte Rollen dürfen unveränderte Roh-Evidenz sehen; Tickets und Release-Manifeste enthalten ausschließlich nicht-sensitive Referenzen.

## Ablauf

1. **Erkennen und einstufen:** Zeitpunkt der Kenntnis, betroffene Dienste und vermutete Auswirkung erfassen; keine Rohdaten in Chat oder Standardtickets kopieren.
2. **Eindämmen und Evidenz sichern:** Zugang oder Funktion mit kleinstmöglicher Wirkung sperren, relevante Logs schreibgeschützt sichern, Secrets bei bestätigtem Risiko über den vorgesehenen Providerprozess rotieren.
3. **Daten- und Risikoanalyse:** Datenklasse, Zweck, Anzahl und Art der Betroffenen, Verschlüsselung/Pseudonymisierung, Mandanten- und Minderjährigenbezug sowie mögliche Folgen dokumentieren.
4. **Meldung entscheiden:** Der Privacy Lead dokumentiert Schwelle, Entscheidung, Zeitpunkt und Begründung. Falls Art. 33 DSGVO greift, erfolgt die Behördenmeldung unverzüglich und möglichst binnen 72 Stunden nach Kenntnis; Verzögerungen werden begründet. Eine Information Betroffener nach Art. 34 wird separat bewertet.
5. **Beheben und wiederherstellen:** Ursache entfernen, Least-Privilege und Mandantengrenzen erneut testen, sauberes Backup/Artefakt verifizieren und schrittweise freigeben.
6. **Nachprüfung:** Regressionstest, Monitoring und Zugriffskontrolle bestätigen; Abschluss erst nach Security- und Privacy-Freigabe.
7. **Nachbereitung:** Innerhalb des freigegebenen Incident-Prozesses Ursachen, wirksame Maßnahmen, Owner und Zieldaten festhalten; Retention der Incident-Evidenz durch Legal/DSB festlegen.

## Datensparsame Evidenz

- Verwende Incident-ID, Zeitstempel, Dienst-/Modulname, Datenklasse, Anzahl als Bandbreite und Hashes statt Inhalte.
- Schwärze Tokens, Cookies, Auth-Header, Zahlungsdaten, Gesundheitsdaten, Nachrichteninhalte und direkte Identifikatoren.
- Erzeuge keine Produktionskopie für den Drill. Tests nutzen synthetische Datensätze und lokale/vergebene Provider.
- Lösche Incident-Evidenz niemals über normale Retention-Jobs; Legal Hold ist dokumentiert, befristet und zugriffsbeschränkt.

## Retention-Drill

Vor einem Löschlauf werden die begrenzten Vorschauen geprüft:

```bash
php artisan airmius:prune-ad-events --limit=1000 --dry-run
php artisan airmius:prune-website-requests --limit=1000 --dry-run
php artisan airmius:prune-recruiting-interests --limit=1000 --dry-run
php artisan airmius:prune-expired-stories --limit=500 --dry-run
php artisan airmius:prune-platform-delivery --limit=1000 --dry-run
php artisan airmius:process-inactive-accounts --dry-run
```

Der Operator vergleicht nur aggregierte Anzahlen, Scheduler-Laufzeit und Fehlerstatus. Erst anschließend läuft der normale, weiterhin begrenzte Scheduler-Job. Bei auffälligem Sprung wird nicht gelöscht, sondern Privacy Lead und Technical Owner prüfen Policy, Uhrzeit und Query-Grenze.

## Externe Abnahmegrenzen

- `dpia_approval`: nur der Datenschutzbeauftragte darf diesen Gate mit versionsgebundener, nicht-sensitiver Evidenz freigeben.
- `external_penetration_test`: nur der benannte Security-Verantwortliche darf nach unabhängiger Prüfung und Schließung kritischer Findings freigeben.

Legal-, DPIA- und Pentest-Abnahme werden zusätzlich ohne duplizierte Gate-Liste über `governance-assurance.v1` koordiniert. Für diese drei Gates sind Waiver und lokale Selbstfreigaben ausgeschlossen. Der verbindliche Ablauf, die vollständigen Airmius-Verarbeitungs-/Pentest-Scope-Gruppen und die datensparsame Evidenzregel stehen in [GOVERNANCE_RELEASE_RUNBOOK.md](GOVERNANCE_RELEASE_RUNBOOK.md).
- Der automatisierte Drill prüft lediglich, dass beide Gates existieren. Er setzt keinen Gate-Status.

Rechtsgrundlage für Zweckbindung, Datenminimierung, Speicherbegrenzung, Sicherheit, Breach-Entscheidung und DPIA ist die [offizielle DSGVO-Fassung bei EUR-Lex](https://eur-lex.europa.eu/eli/reg/2016/679/oj).
