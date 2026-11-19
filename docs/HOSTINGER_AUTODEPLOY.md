# Gemeinsame Bereitstellung auf Hostinger

## Status

Der Workflow `hostinger-deploy.yml` wird bei Push auf `main` und manuell ausgelöst.
Er bleibt deaktiviert, solange die Repository-Variable `HOSTINGER_DEPLOY_ENABLED`
nicht exakt `true` lautet. Eine offene interaktive SSH-Sitzung reicht für GitHub
Actions nicht; der Runner benötigt einen eigenen SSH-Zugang.

## Ablauf

1. Derselbe Commit wird getestet, Composer-Abhängigkeiten werden installiert und
   Vite wird frisch gebaut. Alle Manifestdateien und referenzierten Assets werden geprüft.
2. Ein Archiv enthält gemeinsam `app`, `bootstrap`, `config`, `database`, `lang`,
   `resources`, `routes`, `public`, `vendor`, `artisan`, `cron-schedule.php` und Composer-Metadaten.
   Lokale Arbeitskopien, `.env`, Nutzerdaten, Git-Verzeichnis und App-Builds sind ausgeschlossen.
3. Hostinger prüft die Archiv-Prüfsumme, bereitet ein neues Release außerhalb des
   öffentlichen Verzeichnisses vor und bindet bestehende Konfiguration und Storage ein.
4. Die Anwendung geht kurz in Wartung, Migrationen laufen, und ein Symlink wird
   atomar auf das neue Release umgeschaltet. Alte Dateien werden nicht einzeln überschrieben.
5. Die öffentliche Commit-Kennung und Laravel `/up` werden geprüft. Bei Fehlern
   wird auf das vorherige Code-Release zurückgeschaltet. Datenbankmigrationen werden
   nicht automatisch zurückgerollt; Migrationen müssen mit dem vorherigen Release kompatibel sein.

## Einmalige Einrichtung vor Aktivierung

Der bisherige Stand unter `/domains/airmius.com/public_html/airmius` besitzt zwei
verschiedene Build-Verzeichnisse. Sein Frontcontroller und der tatsächliche
DocumentRoot müssen zuerst auf dem Server geprüft werden. Nicht blind verschieben
oder einen der bisherigen Verzeichnisse löschen.

Die Release-Struktur soll außerhalb von `public_html` liegen:

```text
/home/ACCOUNT/.airmius-deploy/
  incoming/
  releases/INITIAL/        vollständige bestehende Anwendung als Ausgangspunkt
  current -> releases/INITIAL
  shared/.env             vorhandene Produktionskonfiguration
  shared/storage/         vorhandene Nutzerdaten und Laufzeitdaten
  shared/assets/          bestehende und neue Assets mit Inhalts-Hash
```

Die bestehende Domain bleibt auf dem bisherigen Anwendungsverzeichnis. Dieses
ist ein Symlink auf `current`; `.htaccess` leitet HTTP ausschließlich in `public/`.
Dadurch bleiben bestehende Cron-Pfade zu `artisan` und `cron-schedule.php` gültig.
Der Runner prüft vor jedem Wechsel, dass der Domain-Pfad auf das aktuelle Release zeigt.

Am 19.11.2026 wurden die Produktionsdatenbank und das bisherige Verzeichnis privat
unter `.airmius-deploy/backups` gesichert und ein unverändertes Ausgangsrelease
angelegt. Die Symlink-Erreichbarkeit, Startseite und `/up` wurden danach geprüft.
Der alte Hostinger-GitHub-Webhook ist deaktiviert, damit nur dieser Workflow deployt.

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
