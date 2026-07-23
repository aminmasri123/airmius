# Datenbank-Backup und Wiederherstellung

Stand: 20.07.2026

## Produktionsstandard

Airmius unterstützt konsistente SQLite- und MySQL-Backups. In Produktion muss `BACKUP_DISK` auf einen externen, privaten Objektspeicher wie Cloudflare R2 oder S3 zeigen. Der Scheduler erstellt täglich um 02:30 Uhr ein Backup. MySQL wird mit `mysqldump --single-transaction` einschließlich Routinen, Triggern und Events gesichert.

Jedes Backup besitzt ein JSON-Manifest (`airmius.database-backup.v2`) mit SHA-256-Prüfsumme, Größe, Datenbanktreiber, Umgebung und Aufbewahrungsdauer. Datenbankpasswörter werden nur über eine temporäre Datei mit Modus `0600` an die MySQL-Werkzeuge übergeben und erscheinen nicht in der Prozessliste.

Erforderliche Produktionswerte:

```dotenv
BACKUP_DISK=r2
BACKUP_PATH=backups/database
BACKUP_RETENTION_DAYS=30
BACKUP_PROCESS_TIMEOUT=900
MYSQLDUMP_BINARY=mysqldump
MYSQL_BINARY=mysql
```

Der R2-/S3-Bucket muss privat, serverseitig verschlüsselt, versioniert und mit einer Lifecycle-Regel versehen sein. Empfohlen werden 30 tägliche, 12 monatliche und mindestens eine jährliche unveränderliche Kopie. Der Produktionsserver benötigt nur Schreib- und Leserechte auf das Backup-Präfix, keine öffentlichen Rechte.

## Backup ausführen

```bash
php artisan airmius:backup-database
```

Für einen manuellen Pfad:

```bash
php artisan airmius:backup-database --disk=r2 --path=backups/database/manual.sql
```

Ein erfolgreicher Exit-Code allein genügt nicht. Monitoring muss zusätzlich Existenz, Größe und Alter des letzten Manifests kontrollieren.

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
5. Erst nach Freigabe einen dokumentierten Cutover durchführen.

Ein Restore-Drill ist mindestens monatlich sowie vor jedem größeren Release auszuführen und mit RPO, RTO, Backup-ID, Prüfsumme und verantwortlicher Person zu protokollieren.

## SQLite-Restore

```bash
php artisan airmius:restore-database backups/database/test.sqlite \
  --disk=local \
  --target=/tmp/airmius-restore-test.sqlite \
  --force
```

Auch hierbei wird die Manifest-Prüfsumme vor Abschluss kontrolliert.
