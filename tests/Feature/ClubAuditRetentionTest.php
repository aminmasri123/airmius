<?php

namespace Tests\Feature;

use App\Models\Activity;
use App\Models\Club;
use App\Models\User;
use App\Support\ClubAuditLog;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ClubAuditRetentionTest extends TestCase
{
    use RefreshDatabase;

    public function test_only_club_audit_entries_from_the_last_seven_days_are_visible(): void
    {
        $club = Club::factory()->create();
        $actor = User::factory()->create();
        $expired = ClubAuditLog::record($club, $actor, 'club.member.updated');
        $expired->forceFill(['created_at' => now()->subDays(8), 'updated_at' => now()->subDays(8)])->save();
        ClubAuditLog::record($club, $actor, 'club.member.role_updated');

        $logs = ClubAuditLog::forClub($club);

        $this->assertCount(1, $logs);
        $this->assertSame('club.member.role_updated', $logs[0]['type']);
    }

    public function test_prune_command_deletes_expired_club_entries_only(): void
    {
        $club = Club::factory()->create();
        $expired = Activity::query()->create(['club_id' => $club->id, 'type' => 'club.member.updated']);
        $expired->forceFill(['created_at' => now()->subDays(8), 'updated_at' => now()->subDays(8)])->save();
        $current = Activity::query()->create(['club_id' => $club->id, 'type' => 'club.member.role_updated']);
        $unrelated = Activity::query()->create(['type' => 'platform.activity']);
        $unrelated->forceFill(['created_at' => now()->subDays(30), 'updated_at' => now()->subDays(30)])->save();

        $this->artisan('airmius:prune-club-audit-logs')->assertSuccessful();

        $this->assertDatabaseMissing('activities', ['id' => $expired->id]);
        $this->assertDatabaseHas('activities', ['id' => $current->id]);
        $this->assertDatabaseHas('activities', ['id' => $unrelated->id]);
    }
}
