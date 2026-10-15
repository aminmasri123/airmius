# Datenbank-Backup und Wiederherstellung

Stand: 15.10.2026

## Produktionsstandard

Airmius unterstützt konsistente SQLite- und MySQL-Backups. In Produktion muss `BACKUP_DISK` auf einen externen, privaten Objektspeicher wie Cloudflare R2 oder S3 zeigen. Der Scheduler erstellt täglich um 02:30 Uhr ein Backup. MySQL wird mit `mysqldump --single-transaction` einschließlich Routinen, Triggern und Events gesichert.

Jedes Backup besitzt ein JSON-Manifest (`airmius.database-backup.v2`) mit SHA-256-Prüfsumme, Größe, Datenbanktreiber, Umgebung und einer konfigurierten Aufbewahrungsdauer. **Der Wert `BACKUP_RETENTION_DAYS` ist derzeit nur Manifest-Metadatum; der Backup-Befehl und der Scheduler löschen damit keine alten Kopien.** Datenbankpasswörter werden nur über eine temporäre Datei mit Modus `0600` an die MySQL-Werkzeuge übergeben und erscheinen nicht in der Prozessliste.

Erforderliche Produktionswerte:

```dotenv
BACKUP_DISK=r2
BACKUP_PATH=backups/database
BACKUP_RETENTION_DAYS=30
BACKUP_PROCESS_TIMEOUT=900
MYSQLDUMP_BINARY=mysqldump
MYSQL_BINARY=mysql
DELETION_RECOVERY_LEDGER_ENABLED=true
DELETION_RECOVERY_LEDGER_DISK=r2
DELETION_RECOVERY_LEDGER_PATH=backups/privacy-deletions
DELETION_RECOVERY_LEDGER_SIGNING_KEY=<separater-geheimer-Schluessel>
```

Der R2-/S3-Bucket muss privat, serverseitig verschlüsselt und mit einer **tatsächlich geprüften** Lifecycle-Regel versehen sein. Die konkrete Aufbewahrung, einschließlich Versionen, Objektsperren, manueller Kopien und Hosting-Backups, muss vor Freigabe begründet und technisch nachgewiesen werden. Zusätzliche monatliche oder jährliche Vollkopien dürfen nicht pauschal angelegt werden: Sie verlängern auch die Zeit, in der bereits gelöschte personenbezogene Daten in Backups verbleiben. Eine konfigurierte 30-Tage-Frist ist ohne wirksame Bucket-Regel keine garantierte Löschfrist. Der Produktionsserver benötigt nur die für Backup und Restore notwendigen Rechte auf das private Präfix.

Lesende R2-Pruefung mit der lokalen, als `APP_ENV=production` markierten Konfiguration am 15.10.2026: `BACKUP_DISK=r2` und `BACKUP_PATH=backups/database`, aber die aufgelisteten Praefixe `backups/database/` und `backups/` enthielten keine Objekte. Die Abfrage der Bucket-Lifecycle-Regeln wurde mit HTTP 403 `AccessDenied` abgewiesen. Im Hostinger-Panel war ein minuetlicher Cron fuer `cron-schedule.php` vorhanden; seine letzte sichtbare Ausgabe zeigte laufende Laravel-Scheduler-Auftraege, aber keinen Nachweis des Backup-Laufs um 02:30 Uhr. Dies belegt weder, dass der tatsaechliche Produktionshost dieselben Zugangsdaten und denselben Bucket nutzt, noch, dass anderswo keine Backups existieren. Vor Freigabe auf dem echten Produktionshost den letzten erfolgreichen Backup-Lauf, Bucket/Praefix, Objektanzahl und -alter sowie die Lifecycle-Regeln mit einem hierzu berechtigten Cloudflare-Admin nachweisen; keine produktive Datenbanksicherung nur zu Testzwecken ausloesen.

Das Löschprotokoll liegt außerhalb der Datenbank im separaten Präfix `DELETION_RECOVERY_LEDGER_PATH`. Sein Speicher darf nicht zusammen mit einem alten Datenbank-Backup zurückgesetzt werden. Die Aufbewahrung seiner signierten Einträge muss länger sein als die längste Frist aller Datenbank-, Hostinger- und Objektspeicher-Backups; eine vorzeitige Lifecycle-Löschung ist unzulässig. Bucketschutz (Versionierung/Objektsperre), Zugriffsrechte und Alarmierung für fehlende Einträge sind betrieblich einzurichten und zu prüfen. Der Signaturschlüssel muss unabhängig von Datenbank-Backups sicher aufbewahrt werden; eine Rotation ohne gesicherte Verifikation der alten Einträge sperrt Restores. Das Protokoll speichert nur Typ, ID, Erstellungsdatum und Zeitpunkte, keine Namen oder Belege.

Beobachtung im Hostinger-Panel für `airmius.com` am 15.10.2026: Taegliche automatische Backups waren aktiviert. Datenbank-Backups waren vom 14.10.2026 bis mindestens 30.08.2026 auswaehlbar; die separate Ansicht `Website-Sicherung` meldete dagegen fuer die Domain keine verfuegbaren Backups. Die Plan-Details nannten Frankfurt als Hosting-Serverstandort und Frankreich als Speicherort der Hostinger-Backups; das sagt nichts ueber R2 oder manuelle Kopien aus. Hostinger nennt fuer automatische Web-/Cloud-Hosting-Backups sieben Tage fuer taegliche und sechs Wochen fuer woechentliche Sicherungen ([Hostinger-Hilfe](https://www.hostinger.com/support/5981435-how-to-download-backups-at-hostinger/), abgerufen am 15.10.2026). Der auswaehlbare Datenbankstand vom 30.08. liegt am 15.10. bereits ausserhalb von sechs Wochen. Diese Beobachtung belegt weder den konkret gebuchten Tarif noch eine garantierte maximale Aufbewahrungsfrist fuer alle Kopien; die unterschiedlichen Backup-Ansichten muessen mit Hostinger geklaert werden. Vor Freigabe Tarif, tatsaechliche Hostinger-Einstellungen, aeltere manuelle Sicherungen und weitere Kopien pruefen und die Loeschprotokoll-Aufbewahrung daran ausrichten.

Löschungen schreiben vor der Datenbanktransaktion einen `pending`-Eintrag und nach erfolgreichem Commit einen `completed`-Eintrag. Kann `pending` nicht geschrieben werden, wird die Löschung abgebrochen. Scheitert `completed` nach dem Commit oder fehlt ein Eintrag, bleibt die Wiederherstellung bis zur manuellen Prüfung gesperrt. Die Prüfung darf fehlende Einträge nicht stillschweigend entfernen. Das Verfahren deckt nur Löschungen **ab Aktivierung** des Protokolls ab; ältere Vorgänge müssen vor einem Restore getrennt abgeglichen werden. Ein leeres Protokoll ist für sich kein Beweis, dass keine Löschungen stattgefunden haben.

## Backup ausführen

```bash
php artisan airmius:backup-database
```

Für einen manuellen Pfad:

```bash
php artisan airmius:backup-database --disk=r2 --path=backups/database/manual.sql
```

Ein erfolgreicher Exit-Code allein genügt nicht. Monitoring muss zusätzlich Existenz, Größe und Alter des letzten Manifests sowie die tatsächliche Löschung abgelaufener Backup-Dateien **und ihrer Versionen** kontrollieren. Der Befehl selbst erzwingt diese Löschung bislang nicht.

## MySQL-Restore-Drill

Ein Restore darf niemals direkt in die aktuell konfigurierte Anwendungsdatenbank erfolgen. Zuerst eine leere, getrennte Datenbank anlegen und dann:

```bash
php artisan airmius:restore-database backups/database/airmius-mysql-TIMESTAMP.sql \
  --disk=r2 \
  --connection=mysql \
  --target-database=airmius_restore_drill \
  --force
```

Der Befehl verweigert die Wiederherstellung, wenn das Ziel dem aktuell konfigurierten Datenbanknamen entspricht. Vor einem Recovery-Cutover sind mindestens folgende Prüfungen nötig:

1. Migrationstabelle und Tabellenanzahl vergleichen.
2. Benutzer-, Vereins-, Rechnungs- und Zahlungsanzahlen plausibilisieren.
3. Stichproben für Dateien, Beziehungen und verschlüsselte Felder ausführen.
4. Anwendung mit der Restore-Datenbank in einer isolierten Umgebung starten.
5. Der Restore-Befehl vergleicht den isolierten Zielstand mit dem unabhängigen, signierten Löschprotokoll. Meldet er ein wiederaufgetauchtes Konto/einen Verein, einen ausstehenden Vorgang oder einen Integritätsfehler, bleibt der Cutover gesperrt. Gelöschte Daten dürfen nur nach dokumentierter Prüfung im Zielstand erneut entfernt werden; dann den Restore-Check wiederholen. Löschungen vor Aktivierung des Protokolls gesondert abgleichen.
6. Erst nach Freigabe einen dokumentierten Cutover durchführen.

Das Protokoll wird für neue Konto- und Vereinslöschungen geschrieben und beim Restore geprüft. Es löscht wiederhergestellte Daten **nicht automatisch** und ersetzt keine Freigabe des Datenschutzverantwortlichen. Restore-Verfahren außerhalb dieses Befehls müssen denselben Check durchführen. Zusätzliche Hostinger- oder Objektspeicher-Sicherungen und deren tatsächliche Löschfristen sind vor Produktionsfreigabe zu verifizieren.

Ein Restore-Drill ist mindestens monatlich sowie vor jedem größeren Release auszuführen und mit RPO, RTO, Backup-ID, Prüfsumme und verantwortlicher Person zu protokollieren.

## SQLite-Restore

```bash
php artisan airmius:restore-database backups/database/test.sqlite \
  --disk=local \
  --target=/tmp/airmius-restore-test.sqlite \
  --force
```

Auch hierbei wird die Manifest-Prüfsumme vor Abschluss kontrolliert.
