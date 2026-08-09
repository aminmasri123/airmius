# AIRMIUS Operations Monitoring Runbook

Stand: 2026-08-09

## Ziel

Der MVP braucht einen wiederholbaren Betriebscheck fuer Fehler, Performance, Queue, geplante Jobs, Webhooks und Mailversand. Der Check laeuft ohne externe SaaS-Abhaengigkeit und kann von Laravel Scheduler, Cron, CI oder einem Host-Monitor ausgefuehrt werden.

## Command

```bash
php artisan airmius:monitor-operations
php artisan airmius:monitor-operations --hours=24
php artisan airmius:monitor-operations --json
```

Der Command gibt `0` zurueck, wenn keine harten Fehler gefunden wurden. Warnungen blockieren nicht, muessen aber vor Livegang abgearbeitet werden. Harte Fehler geben `1` zurueck.

Vor einem Release bindet der zentrale Preflight diesen Monitor optional ein und ergänzt ihn um Repository-, Produktionskonfigurations- und Evidence-Gates:

```bash
php artisan airmius:release-preflight --with-operations --json
php artisan airmius:release-preflight --with-operations --strict
```

Der erste Aufruf eignet sich für eine maschinenlesbare Staging-Diagnose. Der zweite ist das finale Go/No-Go und blockiert zusätzlich bei offenen manuellen Nachweisen. Die Ausgabe bleibt datensparsam und enthält keine Secrets, SQL-Texte, Requestdaten oder personenbezogenen Werte.

Die externe SLO-Abnahme besitzt zusätzlich den versionierten Vertrag `observability-slo-readiness.v1`:

```bash
php artisan airmius:audit-observability --json
php artisan airmius:audit-observability --with-runtime --json
php artisan airmius:audit-observability --with-runtime --json --strict
```

Der erste Aufruf prüft Vertrag, Artefakte, Scheduler und Evidenzstruktur ohne Runtime-Zugriff. `--with-runtime` setzt eine release-identische Staging-Umgebung mit Performance-Telemetrie, externem Error Collector, workerbasierter Queue, Push-Monitoring und privatem Offsite-Backup voraus. `--strict` bleibt bis zur ownergebundenen externen Freigabe rot.

## Scheduler

`routes/console.php` fuehrt den Check stuendlich aus:

```php
Schedule::command('airmius:monitor-operations')
    ->hourly()
    ->withoutOverlapping();
```

Auf dem Server muss der Laravel Scheduler per Cron laufen:

```bash
* * * * * cd /var/www/airmius && php artisan schedule:run >> /dev/null 2>&1
```

## Gepruefte Bereiche

- Fehler: aktuelle Laravel-Logs werden nach `ERROR`, `CRITICAL`, `ALERT` und `EMERGENCY` im Monitoring-Fenster durchsucht.
- Performance: `http.performance` meldet langsame oder DB-intensive Requests und sampelt einen kleinen Anteil normaler Requests fuer p75/p95-Baselines.
- Queue: Tabellen `jobs`, `failed_jobs`, `job_batches`, aktuelle fehlgeschlagene Jobs und zu alte offene Jobs.
- Jobs: kritische AIRMIUS Artisan-Commands muessen registriert sein.
- Webhooks: Stripe-, PayPal-, Commerce- und Outfit-Webhook-Routen muessen registriert sein.
- Mailversand: fehlgeschlagene `mail_deliveries` und konfigurierte Mailer pro Versandkategorie.
- Push: Firebase-Struktur, zu lange wartende und kürzlich fehlgeschlagene Zustellungen.
- Backup: Alter des letzten Manifests und privates Offsite-Ziel; Storage-Fehler werden ohne Disk-, Pfad- oder Exceptiondetails gemeldet.

## Versionierte SLOs

| Signal | Ziel | Alert spätestens | Owner |
|---|---:|---:|---|
| Verfügbarkeit einschließlich Vereine, Marketplace und E-Learning | mindestens 99,9 % | 5 min | SRE / Platform |
| API-5xx | höchstens 1,0 % | 5 min | Backend / SRE |
| Server-/DB-Latenz | p95 höchstens 1.000/500 ms | 10 min | Backend / SRE |
| Gast-Core-Web-Vitals | p75 LCP ≤ 2.500 ms, INP ≤ 200 ms, CLS ≤ 0,1 | 30 min | Frontend / SRE |
| Queue | 0 stale, 0 neue fehlgeschlagene Jobs | 5 min | Backend / SRE |
| Webhooks | höchstens 1,0 % 5xx, Signaturfehler alarmiert | 5 min | Commerce / SRE |
| Mail | höchstens 2,0 % fehlgeschlagen | 15 min | Operations / SRE |
| Push | höchstens 5,0 % fehlgeschlagen, 0 stale | 15 min | Mobile / SRE |
| Backup | höchstens 30 Stunden alt, offsite, Restore-Probe | 60 min | DevOps / SRE |

Die Schwellenwerte sind releasegebunden in `ObservabilityAcceptanceRegistry` definiert. Für die Release-Evidenz sind mindestens 24 Stunden Beobachtung sowie je Signal eine nicht-sensitive Dashboard-, Alert-Test- und Verifikationsreferenz erforderlich. Dashboard-URLs, Hosts, Pfade, Querystrings, Header, Bodies, Traces, Tokens, Zugangsdaten und Personen-/Geräte-/Vereins-/Bestellkennungen sind verboten. Die lokale Datei `resources/release/observability_evidence.local.json` bleibt ignoriert und kann den autoritativen Gate nie selbst freigeben.

Gastmetriken werden bevorzugt synthetisch erhoben. Reale Nutzungsmetriken benötigen eine ausdrückliche Einwilligung und werden erst ab mindestens fünf Personen aggregiert; kein zusätzliches Tracking-Cookie oder ungeprüftes RUM-SDK darf allein für diesen Gate aktiviert werden.

## Konfiguration

```env
ERROR_MONITORING_DSN=
OPERATIONS_MONITOR_WINDOW_HOURS=24
OPERATIONS_PERFORMANCE_ENABLED=true
OPERATIONS_SLOW_REQUEST_MS=1000
OPERATIONS_MAX_QUERY_COUNT=100
OPERATIONS_MAX_QUERY_TIME_MS=500
OPERATIONS_PERFORMANCE_SAMPLE_RATE=0.01
OPERATIONS_MAX_RECENT_ERRORS=0
OPERATIONS_LOG_SCAN_BYTES=262144
OPERATIONS_REQUIRE_EXTERNAL_ERROR_MONITORING=true
OPERATIONS_MAX_FAILED_JOBS=0
OPERATIONS_MAX_STALE_JOBS=0
OPERATIONS_STALE_JOB_MINUTES=15
OPERATIONS_WARN_SYNC_QUEUE_IN_PRODUCTION=true
OPERATIONS_WARN_MISSING_WEBHOOK_SECRETS=true
OPERATIONS_MAX_FAILED_MAIL_DELIVERIES=0
OPERATIONS_FAIL_LOG_MAILER_IN_PRODUCTION=true
OBSERVABILITY_MINIMUM_EVIDENCE_WINDOW_HOURS=24
```

`OPERATIONS_PERFORMANCE_SAMPLE_RATE` liegt zwischen `0` und `1`; `0.01` entspricht einem Prozent der unauffaelligen Requests. SLO-Verletzungen und HTTP-5xx werden unabhaengig vom Sampling als Warning geschrieben. Ein Eintrag enthaelt ausschliesslich:

- benannte Route und HTTP-Methode;
- Status, Gesamtdauer und Speicherdelta;
- Anzahl und kumulierte Laufzeit der DB-Abfragen;
- maschinenlesbare Grenzwertverletzungen und, falls vorhanden, die gepruefte Request-ID.

SQL, Bindings, URL/Pfad, Query-Parameter, Request Body, Nutzer-ID, IP und User-Agent werden nicht erfasst. Diese Grenze ist automatisiert getestet und darf auch im externen Log-Collector nicht durch nachtraegliches Enrichment aufgeweicht werden.

## Livegang-Regeln

- `ERROR_MONITORING_DSN` setzen, sobald Sentry, Flare, Bugsnag oder ein vergleichbares Tool angebunden ist.
- `OPERATIONS_PERFORMANCE_ENABLED=true` setzen und `http.performance` nach `route` aggregieren; p75/p95 der Dauer und DB-Zeit sowie die Anzahl der `breaches` alarmierbar machen.
- Nach sieben Pilot-Tagen die Standardgrenzen mit realen p75/p95-Werten kalibrieren; Grenzwerte nicht durch pauschales Hochsetzen „loesen“.
- Queue Worker dauerhaft betreiben, z. B. per Supervisor oder systemd.
- `failed_jobs` aktiv beobachten und nach Behebung leeren oder retryen.
- Mail-Center taeglich pruefen, fehlgeschlagene Mailauslieferungen erneut senden oder als erledigt markieren.
- Payment-Webhooks nur im Live-Modus aktivieren, wenn Signaturpruefung und Provider-Webhook-IDs konfiguriert sind.
- Host-Monitoring soll den JSON-Modus nutzen und bei Exit-Code `1` alarmieren.

## Manuelle Reaktion

1. `php artisan airmius:monitor-operations --json` ausfuehren.
2. Bei `recent_application_errors`: `storage/logs/laravel*.log` pruefen, Ursache beheben, erneut deployen.
3. Bei `recent_failed_jobs`: `php artisan queue:failed` pruefen, Ursache beheben, Jobs retryen oder entfernen.
4. Bei `stale_jobs`: Queue Worker und Supervisor/systemd pruefen.
5. Bei Webhook-Warnungen: Provider-Secrets und Webhook-IDs setzen.
6. Bei Mail-Fehlern: `/admin/mail-center` oeffnen, SMTP/Provider pruefen, Mail erneut senden oder Eintrag erledigen.
7. Bei `http.performance`: zuerst `breaches` und benannte Route pruefen; bei `query_count` den Contract-/N+1-Test ergaenzen, bei `query_time` den produktionsgleichen Queryplan analysieren, bei `request_duration` Queue-, Provider- und PHP-Anteile trennen.
