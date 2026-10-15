<?php

namespace Tests\Feature;

use App\Services\DeletionRecoveryLedger;
use Illuminate\Support\Facades\Storage;
use PDO;
use RuntimeException;
use Tests\TestCase;

class DeletionRecoveryLedgerTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
        config([
            'airmius_backup.deletion_ledger_enabled' => true,
            'airmius_backup.deletion_ledger_disk' => 'local',
            'airmius_backup.deletion_ledger_path' => 'backups/privacy-deletions',
        ]);
    }

    public function test_pending_or_resurrected_deletion_blocks_restore(): void
    {
        $ledger = app(DeletionRecoveryLedger::class);
        $createdAt = '2026-10-01 10:00:00';
        $id = $ledger->prepare('club', 23, $createdAt);
        $this->assertNotNull($id);
        $this->assertCount(1, Storage::disk('local')->files('backups/privacy-deletions/pending'));

        $database = tempnam(sys_get_temp_dir(), 'airmius-ledger-test-');
        $pdo = new PDO('sqlite:'.$database);
        $pdo->exec('CREATE TABLE clubs (id INTEGER PRIMARY KEY, created_at TEXT)');
        $pdo->exec("INSERT INTO clubs (id, created_at) VALUES (23, '{$createdAt}')");
        try {
            try {
                $ledger->verifySqlite($database);
                $this->fail('An unfinished deletion must block restore.');
            } catch (RuntimeException $exception) {
                $this->assertStringContainsString('completed record missing', $exception->getMessage());
            }

            $ledger->complete($id);
            try {
                $ledger->verifySqlite($database);
                $this->fail('A resurrected club must block restore.');
            } catch (RuntimeException $exception) {
                $this->assertStringContainsString('previously deleted', $exception->getMessage());
            }

            $pdo->exec('DELETE FROM clubs WHERE id = 23');
            $ledger->verifySqlite($database);
            $pdo->exec("INSERT INTO clubs (id, created_at) VALUES (23, '2026-10-15 10:00:00')");
            $ledger->verifySqlite($database);
        } finally {
            $pdo = null;
            @unlink($database);
        }
    }

    public function test_tampered_completion_record_blocks_restore(): void
    {
        $ledger = app(DeletionRecoveryLedger::class);
        $id = $ledger->prepare('user', 34, '2026-10-01 10:00:00');
        $ledger->complete($id);
        Storage::disk('local')->put('backups/privacy-deletions/completed/'.$id.'.json', '{}');

        $database = tempnam(sys_get_temp_dir(), 'airmius-ledger-test-');
        $pdo = new PDO('sqlite:'.$database);
        $pdo->exec('CREATE TABLE users (id INTEGER PRIMARY KEY, created_at TEXT)');
        try {
            $this->expectException(RuntimeException::class);
            $this->expectExceptionMessage('integrity failed');
            $ledger->verifySqlite($database);
        } finally {
            $pdo = null;
            @unlink($database);
        }
    }
}
