# Airmius Platform Delivery Runbook

Stand: 8. August 2026

Dieses Runbook beschreibt den sicheren Betrieb der umgesetzten Plattformbasis. Es ergänzt den [Optimierungsplan 2030](AIRMIUS_OPTIMIERUNGSPLAN_2030.md) und ist für Entwicklung, Release, SRE und Datenschutzkoordination bestimmt.

## 1. Umgesetzter Stand

- rollen- und workspaceabhängige App-Shell mit fünf primären Bereichen;
- gemeinsamer Sprachwechsel für Web und API;
- DE, EN, FR und AR sowie RTL-Shell für Arabisch;
- versionierter API-Vertrag mit Request-ID, Modul- und Verarbeitungszweck-Headern;
- Idempotenz für authentifizierte API-Mutationen;
- transaktionale Domain-Outbox für Events, Community, Commerce, Learning und Sprache;
- Least-Privilege-Rollen `platform_engineer` und `security_admin`;
- lazy Inertia-Props, koaleszierte Partial Reloads und Realtime-Fast-Path;
- automatische Aufbewahrungsbereinigung für Delivery-Daten;
- sichere Organisationskontexte für direkte und teamvermittelte Vereinszugriffe;
- Mobile-Härtung für rollenbasierte Adminbereiche, eingebettete Zugriffssperren und Marketplace-Direktzugriff auf den Warenkorb.

## 2. Release-Reihenfolge

Die Migrationen sind additiv. Der Anwendungscode darf erst nach erfolgreicher Migration aktiviert werden.

```bash
composer install --no-dev --prefer-dist --optimize-autoloader
npm ci
npm run build
php artisan migrate --force
php artisan optimize
php artisan queue:restart
```

Danach müssen genau ein Laravel-Scheduler und ausreichend Queue-Worker aktiv sein. Ohne Queue-Worker bleiben Outbox-Ereignisse sicher gespeichert, werden aber nicht an nachgelagerte Verbraucher ausgeliefert.

## 3. Scheduler und Queue

Neue geplante Aufgaben:

| Aufgabe | Takt | Zweck |
|---|---:|---|
| `airmius:dispatch-domain-outbox` | jede Minute | wartende oder wiederholbare Outbox-Ereignisse einreihen |
| `airmius:prune-platform-delivery` | täglich 03:55 | abgelaufene Idempotenzdaten sowie alte ausgelieferte/fehlgeschlagene Events löschen |

Prüfung ohne produktiven Cache-Lock:

```bash
CACHE_STORE=array php artisan schedule:list
php artisan airmius:dispatch-domain-outbox --limit=100
```

Aufbewahrung:

- abgelaufene Idempotenzantworten werden entfernt;
- veröffentlichte Outbox-Ereignisse werden nach 30 Tagen entfernt;
- endgültig fehlgeschlagene Outbox-Ereignisse werden nach 90 Tagen entfernt.

## 4. Betriebsindikatoren

Mindestens folgende Werte überwachen:

| Signal | Warnung | Kritisch |
|---|---:|---:|
| ältestes wartendes Outbox-Ereignis | > 2 Minuten | > 10 Minuten |
| wiederholbare Outbox-Ereignisse | > 20 | > 100 |
| endgültig fehlgeschlagene Events/24 h | > 0 | > 10 |
| API-Fehlerquote | > 1 % | > 3 % |
| p95 API-Latenz | > 500 ms | > 1.000 ms |
| Queue-Alter | > 60 s | > 300 s |

Outbox-Nutzlasten dürfen keine Post-Inhalte, Lerninhalte, Zahlungsdaten oder Gesundheitsrohdaten enthalten. Neue Event-Typen benötigen einen Contract-Test auf Datenminimierung.

## 5. Rollback

1. vorherigen Anwendungscode erneut ausrollen;
2. Queue-Worker mit `php artisan queue:restart` neu starten;
3. additive Tabellen und Rollen zunächst bestehen lassen;
4. neue Scheduler-Einträge mit dem Code-Rollback entfernen;
5. wartende Outbox-Ereignisse nicht manuell löschen; nach Korrektur erneut zustellen;
6. Datenmigrationen erst in einem separaten, geprüften Release zurückbauen.

Ein Code-Rollback benötigt deshalb kein destruktives Datenbank-Rollback.

## 6. Abnahme-Checks

Stand dieses Releases:

| Check | Ergebnis |
|---|---|
| PHP-Gesamtsuite | 621 bestanden, 4 bewusst übersprungen, 6.121 Assertions |
| Flutter Analyze | keine Befunde |
| Flutter-Tests | 244 bestanden |
| Vite-Produktionsbuild | erfolgreich |
| Composer-Manifest | gültig |
| Scheduler-Auflösung | mit `CACHE_STORE=array` erfolgreich |
| Whitespace-/Patch-Prüfung | `git diff --check` erfolgreich |

Die Flutter-Ausgabe weist darauf hin, dass lokale Linux-Desktop-Builds zusätzliche Systempakete wie `clang` benötigen. Analyse und Widget-Tests sind davon nicht betroffen.

## 7. Mehrsprachigkeit

Aktiv unterstützt sind `de`, `en`, `fr` und `ar`; Arabisch wird RTL gerendert. Locale-Katalog-Parität und Mobile-Kernübersetzungen sind automatisiert getestet.

Der Audit `php artisan airmius:i18n-audit` meldet weiterhin Legacy-Kandidaten in älteren Vue- und PHP-Modulen. Diese Kandidaten sind schrittweise nach Nutzerfrequenz zu migrieren:

1. Authentifizierung, Heute, Training, Events und Chat;
2. Verein, Team, Mitglieder und Dateien;
3. Marketplace, Learning, Abos und Outfit;
4. Sponsor, Agentur, Recruiting und Admin.

Jede Migration ersetzt sichtbare Literale durch semantische Keys, ergänzt DE/EN/FR/AR gemeinsam und erhält einen RTL- oder Contract-Test. Der Audit-Wert darf pro Release nicht steigen.

## 8. Datenschutz- und Rollenfreigabe

- `super_admin`: Break-Glass und globale Governance, nicht für Tagesarbeit;
- `platform_engineer`: technische Plattformpflege ohne automatische Inhalts- oder Finanzrechte;
- `security_admin`: Sicherheits- und Berechtigungsverwaltung ohne globale Geschäftsrechte;
- Fachrollen bleiben an Organisation und Ressource gebunden;
- sensible Verarbeitung benötigt einen dokumentierten Purpose und deny-by-default bei Zwecküberschreitung.

Vor Pilotbetrieb bleiben eine organisatorische DPIA-Freigabe, ein externer Penetrationstest und eine native sprachliche Abnahme erforderlich. Diese Punkte können nicht durch automatisierte Repository-Tests ersetzt werden.
