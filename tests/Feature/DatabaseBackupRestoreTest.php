<?php

namespace Tests\Feature;

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
            $this->assertSame('airmius.database-backup.v2', $manifest['schema']);
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

    public function test_restore_requires_explicit_absolute_target(): void
    {
        $this->artisan('airmius:restore-database', [
            'backup' => 'backups/database/test.sqlite',
        ])->assertExitCode(1);
    }
}
