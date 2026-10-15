# Testphase 36: Technische Vollstaendigkeit

Stand: 2026-10-15

Dieser Bericht ergaenzt das generierte technische Inventar. Er trennt statisch nachgewiesene Schutzmechanismen, lokal ausgefuehrte Tests und Nachweise, die zwingend eine isolierte Staging-Umgebung mit MySQL, Queue-Worker und Test-Providern benoetigen.

| ID | Ergebnis | Nachweis und verbleibender Staging-Test |
| --- | --- | --- |
| T36-01 | Teilweise bestaetigt | Laravel-Routeninventar laesst sich erzeugen; die vorhandene Testinventar-Datei ordnet T36 zu. Fachliche Klick- und Rollenbeweise bleiben pro Route erforderlich. |
| T36-02 | Teilweise bestaetigt | Web- und Flutter-Flaechen sind im Inventar enthalten. Produkt/Demo/veraltet muss bei jeder neuen Datei weiterhin klassifiziert werden. |
| T36-03 | Statisch bestaetigt | Alle Scheduler-Eintraege besitzen `withoutOverlapping()`. Faelligkeit und erneuter Lauf muessen mit Testdaten in Staging ausgefuehrt werden. |
| T36-04 | Automatisiert abgedeckt | Domain-Outbox besitzt Claim, Retry/Backoff und Wiederaufnahme; Foundation- und Cross-Module-Outbox-Tests sind vorhanden. |
| T36-05 | Automatisiert abgedeckt | Stripe-HMAC, Ablaufzeit, PayPal-Verifikation und Provider-Event-Deduplizierung sind in Featuretests enthalten. |
| T36-06 | Teilweise bestaetigt | Provider-Smoke-Audit und lokale Fehlerpfade existieren. Echte Timeouts duerfen nur gegen Stripe-Testmodus, PayPal-Sandbox, Testmail und Test-Push laufen. |
| T36-07 | Behoben | Der globale Statuskanal wurde durch empfaengergebundene private Kanaele ersetzt. Chat-, Nutzer-, Benachrichtigungs-, Team- und Vereinskanalregeln bleiben serverseitig autorisiert. |
| T36-08 | Automatisiert teilweise abgedeckt | Gastseiten und Public APIs besitzen SEO/Public-Content-Tests; mobiler Realgeraete-Durchlauf bleibt offen. |
| T36-09 | Automatisiert abgedeckt | Direkte Routen, ETag/304, private No-Store-Antworten und Asset-Cache-Regeln sind durch HTTP-Delivery-Tests beschrieben. |
| T36-10 | Teilweise bestaetigt | Backup/Restore erzwingt separates Ziel, Manifest und SHA-256. Der reale Wiederherstellungslauf benoetigt SQLite-Unterstuetzung oder eine separate MySQL-Testdatenbank. |
| T36-11 | Teilweise bestaetigt | Request-Telemetrie und Query-Plan-Audit existieren. Last- und MySQL-Planmessung mit realistischen Daten bleibt Staging-Pflicht. |
| T36-12 | Lokal ausgefuehrt | Der Phase-36-Vertragstest wird ohne Produktivdaten ausgefuehrt. Datenbankabhaengige Featuretests sind lokal blockiert, solange keine Test-DB erreichbar ist. |
| T36-13 | Statisch bestaetigt | Mobile Meta/Sync liefern Vertragsversion, Domains, Retry-Regel, Rechte, Push und kontogebundene Pending-Zustaende. Loesch- und Vereinswechsel muessen auf zwei echten Clients wiederholt werden. |
| T36-14 | Statisch bestaetigt | Externe Mitglieder-API verlangt Token-Ability und `MEMBERS_MANAGE`; Unterobjekte werden auf `club_id` geprueft. Widerruf und Wiederholung bleiben ein Staging-Lauf. |
| T36-15 | Automatisiert teilweise abgedeckt | `/up`, CORS/OPTIONS, CSRF-Ausnahmen, signierte Downloads, Cache- und Rate-Limit-Vertraege sind vorhanden. Reverse-Proxy/TLS muss gegen die exakte Staging-Version geprueft werden. |

## Lokaler Befund

- `php artisan route:list --json` war erfolgreich.
- `php artisan schedule:list` konnte ohne erreichbare MySQL-Cache-Lock-Tabelle nicht ausgefuehrt werden. Das ist eine Umgebungsgrenze, kein bestandener Scheduler-Lauf.
- `php artisan airmius:release-preflight --json` meldete korrekt `no_go`: zwei Repository-Gates sind fehlgeschlagen und dreizehn externe Nachweise sind offen. Eine Freigabe mit 10/10 waere daher derzeit nicht serioes.
- PHP besitzt in dieser Umgebung `pdo_mysql`, aber kein `pdo_sqlite`; datenbankabhaengige Featuretests brauchen eine separate MySQL-Testdatenbank.

## Staging-Reihenfolge

1. Separate MySQL-Testdatenbank, `QUEUE_CONNECTION=database` oder Redis und gemeinsame Cache-Locks konfigurieren.
2. Migrationen und Seeds ausschliesslich dort ausfuehren.
3. Relevante Featuretests fuer Outbox, Webhooks, Broadcast, Public Delivery, Backup/Restore, Mobile Sync und externe API starten.
4. `php artisan schedule:list` und jeden faelligen `airmius:*`-Befehl einmal vor Frist, einmal faellig und einmal als Wiederholung ausfuehren.
5. Stripe-Testmodus, PayPal-Sandbox, Testmail und Test-Push mit absichtlichem Timeout, Replay und falscher Signatur pruefen.
6. Backup in ein separates Ziel wiederherstellen und fachliche Stichproben sowie Loeschledger pruefen.
7. Release-Preflight erneut ausfuehren; nur bei `decision: go` und vollstaendigen externen Nachweisen freigeben.
