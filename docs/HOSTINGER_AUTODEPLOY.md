# Gemeinsame Bereitstellung auf Hostinger

## Status

Der Workflow `hostinger-deploy.yml` wird bei Push auf `main` und manuell ausgelöst.
Er bleibt deaktiviert, solange die Repository-Variable `HOSTINGER_DEPLOY_ENABLED`
nicht exakt `true` lautet. Eine offene interaktive SSH-Sitzung reicht für GitHub
Actions nicht; der Runner benötigt einen eigenen SSH-Zugang.

## Ablauf

### Lokale Prüfung vor dem Push

Einmal je Checkout `npm run setup:hooks` ausführen. Der Git-Pre-Push-Hook gilt auch
für VS Code und prüft jeden gepushten Commit in einer separaten temporären Kopie.
Er prüft Übersetzungen, mobile API-Pfade, Barrierefreiheit, Web-Build,
Build-Manifest und die vollständige Backend-Testsuite. Fehler stoppen den Push.
`npm run check:push` prüft den aktuellen Commit auch ohne Push. PHP ab 8.2,
installierte Composer-/npm-Abhängigkeiten und Node.js werden benötigt.
`AIRMIUS_PHP` kann einen abweichenden PHP-Pfad festlegen. Die lokale Windows-
Installation unter dem benachbarten Ordner `airmius-tools` wird automatisch erkannt.
Serverzugänge, SMTP-Zustellung und Hostinger-Kontingente werden weiterhin bei der
Bereitstellung beziehungsweise mit Live-Prüfungen geprüft.

### Veröffentlichung auf Hostinger

1. Derselbe Commit wird getestet, Composer-Abhängigkeiten werden installiert und
   Vite wird frisch gebaut. Alle Manifestdateien und referenzierten Assets werden geprüft.
2. Ein Archiv enthält gemeinsam `app`, `bootstrap`, `config`, `database`, `lang`,
   `resources`, `routes`, `public`, `vendor`, `artisan`, `cron-schedule.php` und Composer-Metadaten.
   Lokale Arbeitskopien, `.env`, Nutzerdaten, Git-Verzeichnis und App-Builds sind ausgeschlossen.
3. Hostinger prüft die Archiv-Prüfsumme, bereitet ein neues Release außerhalb des
   öffentlichen Verzeichnisses vor und bindet bestehende Konfiguration und Storage ein.
4. Vor dem Update wird der bisherige Code privat gesichert. Die Anwendung geht kurz
   in Wartung, Migrationen laufen, und rsync übernimmt den getesteten Code in den
   echten Ordner `public_html/airmius`. Dieser bleibt im hPanel-Dateimanager geöffnet.
   Produktionskonfiguration, Uploads, Laufzeitdaten und bisherige Build-Dateien
   werden dabei ausdrücklich ausgeschlossen; Caches werden am Live-Pfad neu erstellt.
5. Die öffentliche Commit-Kennung, Laravel `/up`, Startseite und sämtliche
   Manifest-Dateien über HTTPS werden geprüft. Bei Fehlern wird der vorherige Code
   aus der Sicherung wiederhergestellt. Datenbankmigrationen werden nicht automatisch
   zurückgerollt und müssen mit dem vorherigen Code kompatibel bleiben.

## Serverstruktur und Dateimanager

Seit der Anpassung am 20.11.2026 ist
`/domains/airmius.com/public_html/airmius` wieder ein echter Ordner.
`app`, `routes`, `lang`, `resources`, `vendor`, `public/build/assets`,
`storage` und die Produktions-`.env` liegen dort direkt und sind im
Hostinger-Dateimanager zugänglich. Für versteckte Dateien muss dessen entsprechende
Anzeige aktiviert sein. Nur Laravels üblicher öffentlicher Storage-Link bleibt ein Link.

```text
/home/ACCOUNT/domains/airmius.com/public_html/airmius/   echter Live-Ordner
/home/ACCOUNT/.airmius-deploy/
  incoming/                Archive und Prüfberichte (privat)
  releases/COMMIT/         getrennt vorbereitete Releases
  backups/code-COMMIT-*/   Code vor einer Veröffentlichung (privat)
  current -> /home/ACCOUNT/domains/airmius.com/public_html/airmius
  shared/assets/           gemeinsame Quelle historischer Hash-Dateien
  shared/storage -> /home/ACCOUNT/domains/airmius.com/public_html/airmius/storage
```

`.htaccess` leitet HTTP ausschließlich nach `public/`; interne Anwendungsdateien
werden nicht öffentlich ausgeliefert. Bestehende Cron-Pfade bleiben gültig.
Assets im Live-Ordner sind echte Dateien (Hardlinks), keine Verzeichnis-Symlinks.
Direkte Codeänderungen im Dateimanager werden beim nächsten Deployment durch GitHub
ersetzt; `.env` und `storage` bleiben erhalten.

Die einmalige Migration erfolgt mit `scripts/deploy/hostinger-file-manager-layout.sh`
unter derselben Deployment-Sperre. Sie übernimmt ausschließlich den aktuell laufenden
Release-Stand und behält den ursprünglichen Link, Release und Storage-Snapshot privat.
Der alte Hostinger-GitHub-Webhook ist deaktiviert; nur der Actions-Workflow deployt.

SSH-Port laut gezeigter hPanel-Sitzung: `65002`; Benutzer `u270834523`; Host
`46.202.158.65`. Diese Angaben sind vor Einrichtung des neuen Zugangs zu verifizieren.
PHP CLI und PHP-FPM müssen die Composer-Anforderungen erfüllen (Workflow PHP 8.2,
insbesondere GD, mbstring, intl sowie Produktions-Datenbanktreiber).

## GitHub-Konfiguration

Repository-Secrets (niemals in Git speichern):

- `HOSTINGER_SSH_HOST`
- `HOSTINGER_SSH_PORT`
- `HOSTINGER_SSH_USER`
- `HOSTINGER_SSH_KEY`: dedizierter privater Deployment-Schlüssel
- `HOSTINGER_SSH_KNOWN_HOSTS`: separat verifizierter Hostschlüssel für den SSH-Port;
  keine ungeprüfte `ssh-keyscan`-Ausgabe übernehmen.

Repository-Variablen:

- `HOSTINGER_DEPLOY_BASE`: absoluter privater Release-Pfad
- `HOSTINGER_WEB_ROOT`: bestehender Domain-Pfad, der auf `current` zeigt
- `HOSTINGER_SITE_URL`: `https://airmius.com`
- `HOSTINGER_DEPLOY_ENABLED`: erst nach erfolgreicher Einrichtung `true`

Der öffentliche Teil des dedizierten SSH-Schlüssels muss auf Hostinger autorisiert
werden. Passwort, private Schlüssel und `.env` dürfen weder in Chat noch Logs landen.
Zusätzliche Hostinger-Git-Autodeployments dürfen nicht parallel dieselben Dateien
überschreiben. Das Repository sollte `main` schützen und Änderungen per Review übernehmen.

## Erstabnahme

Manuell auslösen, Actions-Protokoll prüfen, Commit-Kennung auf `/deployment.json`
vergleichen, eine vorhandene Kursurkunde herunterladen und die Bezeichnung
„Airmius Lernplattform“ prüfen. Danach Web-Kursansicht, Quiz-/Aufgabenabgabe und
API mit einem berechtigten Testkonto prüfen. App-Oberflächen werden separat gebaut
und verteilt; ein Backend-Deployment aktualisiert keine installierte App.

Der dedizierte Deployment-Schlüssel ist auf Hostinger mit `restrict` autorisiert.
Die fünf Repository-Secrets wurden verschlüsselt hinterlegt; private Schlüssel und
Produktionskonfiguration bleiben außerhalb des Repositories. Die Automatik ist
mit `HOSTINGER_DEPLOY_ENABLED=true` freigegeben. Fehlgeschlagene Tests verhindern
die Veröffentlichung weiterhin.

Die öffentlichen Browserwerte `VITE_APP_NAME`, `VITE_ECHO_ENABLED` und die
öffentlichen Reverb-/Pusher-Verbindungswerte werden als `HOSTINGER_VITE_*`-Variablen
im Build verwendet. Anbieter-Geheimnisse und der private Laravel-Anwendungsschlüssel
werden nicht kopiert. Der aktuell konfigurierte Broadcast-Treiber auf Hostinger
ist `log`; die Einrichtung eines laufenden WebSocket-Dienstes ist ein eigener Schritt.

Auch die reguläre Backend-CI baut Vite vor den Build-Vertragstests. Temporäre
Prüfberichte und lokale Hilfsabhängigkeiten unter `tmp/` gehören nicht ins Repository.
Ein erneuter Lauf für einen bereits aktiven Commit prüft dessen Kennung und `/up`,
ohne das aktive Release zu überschreiben.

Prüfungen: Workflow-YAML, Bash-Syntax, Vite-Manifest und vier Tests für
vollständige/fehlende Assets sowie ungültige Imports/Pfade; vollständige Backend-
Testsuite vor jeder Veröffentlichung. Ein JUnit-Bericht wird auch bei Testfehlern
als GitHub-Artefakt abgelegt.
