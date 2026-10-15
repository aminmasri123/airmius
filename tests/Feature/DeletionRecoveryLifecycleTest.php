<?php

namespace Tests\Feature;

use App\Actions\Jetstream\DeleteUser;
use App\Models\Club;
use App\Models\User;
use App\Services\ClubDeletionService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class DeletionRecoveryLifecycleTest extends TestCase
{
    public function test_committed_account_and_club_deletions_write_independent_completion_records(): void
    {
        $originalDatabase = config('database.default');
        $connection = 'deletion_recovery_test';
        config()->set('database.connections.'.$connection, [
            ...config('database.connections.sqlite'), 'database' => ':memory:',
        ]);
        config()->set('database.default', $connection);
        Storage::fake('local');
        Notification::fake();
        config([
            'airmius_backup.deletion_ledger_enabled' => true,
            'airmius_backup.deletion_ledger_disk' => 'local',
            'airmius_backup.deletion_ledger_path' => 'backups/privacy-deletions',
        ]);

        try {
            $this->artisan('migrate', ['--database' => $connection, '--force' => true])->assertExitCode(0);
            $person = User::factory()->create();
            app(DeleteUser::class)->delete($person);
            $this->assertDatabaseMissing('users', ['id' => $person->id], $connection);

            $owner = User::factory()->create();
            $club = Club::factory()->create(['owner_id' => $owner->id]);
            $club->forceFill([
                'deletion_requested_at' => now()->subDays(31),
                'deletion_scheduled_at' => now()->subDay(),
                'deletion_owner_id' => $owner->id,
            ])->save();
            app(ClubDeletionService::class)->process($club->id);
            $this->assertDatabaseMissing('clubs', ['id' => $club->id], $connection);

            $pending = Storage::disk('local')->files('backups/privacy-deletions/pending');
            $completed = Storage::disk('local')->files('backups/privacy-deletions/completed');
            $this->assertCount(2, $pending);
            $this->assertCount(2, $completed);
        } finally {
            DB::purge($connection);
            config()->set('database.default', $originalDatabase);
        }
    }
}
