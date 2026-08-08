# AIRMIUS Operations Monitoring Runbook

Stand: 2026-08-08

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
