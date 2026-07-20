# Backup- und Restore-Runbook

Stand: 2026-07-17

## Ziel

AIRMIUS braucht einen wiederholbaren Restore-Test. Ein Backup gilt erst als brauchbar, wenn mindestens eine Wiederherstellung in eine separate Zieldatenbank erfolgreich war und die Daten lesbar sind.

## MVP-Implementierung

- `php artisan airmius:backup-database` erstellt fuer die aktuelle SQLite-Datenbank ein Backup auf `BACKUP_DISK`.
- `php artisan airmius:restore-database <backup> --target=/absolute/path/restore.sqlite` stellt ein Backup in eine separate SQLite-Datei wieder her.
- Zu jedem Backup wird ein Manifest `<backup>.json` mit Schema `airmius.database-backup.v1`, SHA-256-Hash, Groesse, Quelle, Ziel-Disk und Retention geschrieben.
- Restore verifiziert den Manifest-Hash, wenn das Manifest vorhanden ist.

## Lokaler Restore-Test

```bash
php artisan airmius:backup-database --disk=local --path=backups/database/manual-restore-test.sqlite
php artisan airmius:restore-database backups/database/manual-restore-test.sqlite --disk=local --target=/tmp/airmius-restore-test.sqlite --force
```

Danach die Restore-Datei separat pruefen, niemals direkt die Live-Datei ueberschreiben.

## Produktivregeln

- `BACKUP_DISK=r2` und `BACKUP_PATH=backups/database` sind in `.env.example` vorbereitet.
- Produktive Restore-Tests muessen in eine isolierte Datenbank oder einen isolierten Server laufen.
- Vor Produktivbetrieb mit MySQL/MariaDB muss ein datenbankgerechter Dump/Restore-Pfad ergaenzt werden, z. B. `mysqldump`/`mariadb-dump` oder ein geprueftes Backup-Paket.
- Backups muessen verschluesselt oder zugriffsbeschraenkt gespeichert werden.
- Retention aktuell: `BACKUP_RETENTION_DAYS=30`.
- Mindestens monatlich einen Restore-Test dokumentieren.

## Verifizierter Stand

- Automatischer Restore-Test: `php artisan test tests/Feature/DatabaseBackupRestoreTest.php`
- Der Test erstellt eine echte SQLite-Quelldatenbank, schreibt ein Backup mit Manifest, restored in eine separate SQLite-Datei und liest den Testdatensatz aus der Restore-Datei.
