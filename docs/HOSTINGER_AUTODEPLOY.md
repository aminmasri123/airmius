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
   `resources`, `routes`, `public`, `vendor`, `artisan` und Composer-Metadaten.
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

Vor dem ersten Lauf: Datenbank und Dateien sichern; ein Ausgangsrelease aus dem
tatsächlich laufenden Stand erstellen; Storage und `.env` erhalten; Schreibrechte
prüfen; DocumentRoot auf `current/public` umstellen (oder einen bestätigten
öffentlichen Symlink dorthin einrichten). Die Website und Cron-/Queue-Pfade müssen
anschließend verifiziert werden. Der Deployment-Runner verweigert den ersten Lauf,
wenn der öffentliche Pfad nicht auf das bisherige `current/public` zeigt.

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
- `HOSTINGER_WEB_ROOT`: bestätigter öffentlicher Pfad, der auf `current/public` zeigt
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

Die Workflow-Datei allein aktiviert noch keinen Zugang und veröffentlicht noch nichts.

Lesende SSH-Prüfung am 19.11.2026: PHP CLI 8.2.36; tatsächliches Projektverzeichnis
`/home/u270834523/domains/airmius.com/public_html/airmius`. Der vorhandene Root-
`index.php` lädt `storage`, `vendor` und `bootstrap` direkt aus diesem Verzeichnis.
Eine `index.php` im übergeordneten `public_html` war nicht vorhanden. Damit ist
die Umstellung auf einen öffentlichen `current/public`-Pfad ein notwendiger,
separat zu prüfender Einrichtungsschritt; die aktuellen Dateien dürfen nicht
ungeprüft durch einen Standard-Laravel-Frontcontroller ersetzt werden.

Lokale Vorprüfung: Workflow-YAML geparst, Bash-Syntax geprüft, aktueller Vite-Build
vollständig gegen sein Manifest geprüft, vier Tests für vollständige/fehlende Assets
und ungültige Imports/Pfade bestanden. Noch kein Produktionslauf und noch keine
Abnahme des serverseitigen Release-Wechsels.
