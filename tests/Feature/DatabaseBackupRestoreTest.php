<?php

namespace Tests\Feature;

use App\Services\DeletionRecoveryLedger;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
use PDO;
use Tests\TestCase;

class DatabaseBackupRestoreTest extends TestCase
{
    public function test_sqlite_database_backup_can_be_restored_and_read(): void
    {
        Storage::fake('local');

        $directory = sys_get_temp_dir().'/airmius-backup-restore-'.uniqid('', true);
        File::ensureDirectoryExists($directory);

        $source = $directory.'/source.sqlite';
        $target = $directory.'/restored.sqlite';
        touch($source);

        $originalDefault = config('database.default');
        $originalSqliteDatabase = config('database.connections.sqlite.database');

        try {
            config([
                'database.default' => 'sqlite',
                'database.connections.sqlite.database' => $source,
            ]);

            DB::purge('sqlite');
            DB::statement('create table backup_probe (id integer primary key, name varchar(255) not null)');
            DB::table('backup_probe')->insert(['id' => 7, 'name' => 'restore-ok']);

            $this->artisan('airmius:backup-database', [
                '--disk' => 'local',
                '--path' => 'backups/database/test.sqlite',
            ])->assertExitCode(0);

            Storage::disk('local')->assertExists('backups/database/test.sqlite');
            Storage::disk('local')->assertExists('backups/database/test.sqlite.json');
            $manifest = json_decode(Storage::disk('local')->get('backups/database/test.sqlite.json'), true, flags: JSON_THROW_ON_ERROR);
            $this->assertSame('airmius.database-backup.v3', $manifest['schema']);
            $this->assertSame(24, $manifest['operations']['rpo_hours']);
            $this->assertSame(4, $manifest['operations']['rto_hours']);
            $this->assertSame('DevOps / SRE', $manifest['operations']['responsible_role']);
            $this->assertTrue($manifest['operations']['encryption_at_rest_required']);
            $this->assertTrue($manifest['operations']['restore_drill_required']);

            $this->artisan('airmius:restore-database', [
                'backup' => 'backups/database/test.sqlite',
                '--disk' => 'local',
                '--target' => $target,
            ])->assertExitCode(0);

            $pdo = new PDO('sqlite:'.$target);
            $row = $pdo->query('select id, name from backup_probe limit 1')->fetch(PDO::FETCH_ASSOC);

            $this->assertSame(7, (int) $row['id']);
            $this->assertSame('restore-ok', $row['name']);
        } finally {
            DB::purge('sqlite');
            config([
                'database.default' => $originalDefault,
                'database.connections.sqlite.database' => $originalSqliteDatabase,
            ]);
            File::deleteDirectory($directory);
        }
    }

    public function test_encrypted_sqlite_backup_can_be_restored(): void
    {
        Storage::fake('local');
        $directory = sys_get_temp_dir().'/airmius-encrypted-backup-'.uniqid('', true);
        File::ensureDirectoryExists($directory);
        $source = $directory.'/source.sqlite';
        $target = $directory.'/restored.sqlite';
        touch($source);
        $originalDefault = config('database.default');
        $originalSqliteDatabase = config('database.connections.sqlite.database');

        try {
            config([
                'database.default' => 'sqlite',
                'database.connections.sqlite.database' => $source,
                'airmius_backup.encryption_enabled' => true,
                'airmius_backup.encryption_key' => base64_encode(random_bytes(32)),
            ]);
            DB::purge('sqlite');
            DB::statement('create table encrypted_probe (id integer primary key, value varchar(255) not null)');
            DB::table('encrypted_probe')->insert(['id' => 1, 'value' => 'secret-ok']);

            $this->artisan('airmius:backup-database', [
                '--disk' => 'local', '--path' => 'backups/database/encrypted.sqlite',
            ])->assertExitCode(0);
            Storage::disk('local')->assertExists('backups/database/encrypted.sqlite.enc');
            $raw = Storage::disk('local')->get('backups/database/encrypted.sqlite.enc');
            $this->assertStringNotContainsString('secret-ok', $raw);

            $this->artisan('airmius:restore-database', [
                'backup' => 'backups/database/encrypted.sqlite.enc', '--disk' => 'local', '--target' => $target,
            ])->assertExitCode(0);
            $pdo = new PDO('sqlite:'.$target);
            $this->assertSame('secret-ok', $pdo->query('select value from encrypted_probe')->fetchColumn());
        } finally {
            DB::purge('sqlite');
            config([
                'database.default' => $originalDefault,
                'database.connections.sqlite.database' => $originalSqliteDatabase,
                'airmius_backup.encryption_enabled' => false,
                'airmius_backup.encryption_key' => null,
            ]);
            File::deleteDirectory($directory);
        }
    }

    public function test_backup_pruning_removes_only_expired_database_backup_objects(): void
    {
        Storage::fake('local');
        Storage::disk('local')->put('backups/database/expired.sql', 'old');
        Storage::disk('local')->put('backups/database/current.sql', 'new');
        touch(Storage::disk('local')->path('backups/database/expired.sql'), now()->subDays(31)->getTimestamp());

        $this->artisan('airmius:prune-database-backups', ['--disk' => 'local', '--days' => 30])
            ->assertExitCode(0);

        Storage::disk('local')->assertMissing('backups/database/expired.sql');
        Storage::disk('local')->assertExists('backups/database/current.sql');
    }

    public function test_restore_requires_explicit_absolute_target(): void
    {
        $this->artisan('airmius:restore-database', [
            'backup' => 'backups/database/test.sqlite',
        ])->assertExitCode(1);
    }

    public function test_restore_rejects_a_backup_without_a_verifiable_manifest(): void
    {
        Storage::fake('local');
        Storage::disk('local')->put('backups/database/unverified.sqlite', 'unverified');
        $target = sys_get_temp_dir().'/airmius-unverified-'.uniqid().'.sqlite';

        $this->artisan('airmius:restore-database', [
            'backup' => 'backups/database/unverified.sqlite',
            '--disk' => 'local',
            '--target' => $target,
        ])->assertExitCode(1);

        $this->assertFileDoesNotExist($target);
    }

    public function test_restore_refuses_to_pass_when_backup_resurrects_a_deleted_account(): void
    {
        Storage::fake('local');
        config([
            'airmius_backup.deletion_ledger_enabled' => true,
            'airmius_backup.deletion_ledger_disk' => 'local',
            'airmius_backup.deletion_ledger_path' => 'backups/privacy-deletions',
        ]);
        $directory = sys_get_temp_dir().'/airmius-backup-ledger-'.uniqid('', true);
        File::ensureDirectoryExists($directory);
        $source = $directory.'/source.sqlite';
        $target = $directory.'/restored.sqlite';
        touch($source);
        $originalDefault = config('database.default');
        $originalSqliteDatabase = config('database.connections.sqlite.database');
        try {
            config(['database.default' => 'sqlite', 'database.connections.sqlite.database' => $source]);
            DB::purge('sqlite');
            DB::statement('CREATE TABLE users (id INTEGER PRIMARY KEY, created_at TEXT)');
            DB::table('users')->insert(['id' => 34, 'created_at' => '2026-10-01 10:00:00']);
            $this->artisan('airmius:backup-database', [
                '--disk' => 'local', '--path' => 'backups/database/old.sqlite',
            ])->assertExitCode(0);

            $ledger = app(DeletionRecoveryLedger::class);
            $ledger->complete($ledger->prepare('user', 34, '2026-10-01 10:00:00'));
            $this->artisan('airmius:restore-database', [
                'backup' => 'backups/database/old.sqlite', '--disk' => 'local', '--target' => $target,
            ])->assertExitCode(1);
            $this->assertTrue(File::exists($target));
        } finally {
            DB::purge('sqlite');
            config(['database.default' => $originalDefault, 'database.connections.sqlite.database' => $originalSqliteDatabase]);
            File::deleteDirectory($directory);
        }
    }
}
