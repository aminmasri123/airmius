<?php

namespace Tests\Feature;

use App\Models\Club;
use App\Models\ClubExternalMember;
use App\Models\ClubMembershipRequest;
use App\Models\Invoice;
use App\Models\Team;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ClubMembershipTerminationTest extends TestCase
{
    use RefreshDatabase;

    public function test_member_can_request_termination_manager_can_approve_and_scheduler_closes_membership(): void
    {
        $owner = User::factory()->create();
        $member = User::factory()->create();
        $club = Club::factory()->create(['owner_id' => $owner->id]);
        $club->users()->attach($member->id, [
            'role' => 'member',
            'roles' => ['member'],
            'membership_status' => 'active',
            'joined_on' => now()->subYear()->toDateString(),
        ]);
        $team = Team::factory()->create(['club_id' => $club->id]);
        $team->users()->attach($member->id, ['role' => 'member']);
        $terminationDate = now()->addDays(7)->toDateString();

        Sanctum::actingAs($member);

        $this->postJson("/api/v1/clubs/{$club->id}/termination-requests", [
            'requested_termination_on' => $terminationDate,
            'termination_reason' => 'Umzug in eine andere Stadt',
        ])
            ->assertCreated()
            ->assertJsonPath('data.type', 'termination')
            ->assertJsonPath('data.status', 'pending')
            ->assertJsonPath('data.requested_termination_on', $terminationDate)
            ->assertJsonPath('data.termination_reason', 'Umzug in eine andere Stadt');

        $membershipRequest = ClubMembershipRequest::query()->firstOrFail();

        Sanctum::actingAs($owner);

        $this->postJson(
            "/api/v1/clubs/{$club->id}/membership-requests/{$membershipRequest->id}/approve",
        )
            ->assertOk()
            ->assertJsonPath('data.status', 'approved');

        $this->assertDatabaseHas('club_user', [
            'club_id' => $club->id,
            'user_id' => $member->id,
            'membership_status' => 'active',
            'membership_ends_on' => $terminationDate,
            'membership_ended_at' => null,
        ]);

        $this->artisan('airmius:process-membership-terminations', [
            '--date' => $terminationDate,
        ])->assertExitCode(0);

        $this->assertDatabaseHas('club_user', [
            'club_id' => $club->id,
            'user_id' => $member->id,
            'membership_status' => 'former',
        ]);
        $this->assertDatabaseMissing('team_user', [
            'team_id' => $team->id,
            'user_id' => $member->id,
        ]);
        $this->assertNotNull(
            DB::table('club_user')
                ->where('club_id', $club->id)
                ->where('user_id', $member->id)
                ->value('membership_ended_at'),
        );
    }

    public function test_open_invoice_blocks_termination_request_and_owner_cannot_request_one(): void
    {
        $owner = User::factory()->create();
        $member = User::factory()->create();
        $club = Club::factory()->create(['owner_id' => $owner->id]);
        $club->users()->attach($member->id, [
            'role' => 'member',
            'roles' => ['member'],
            'membership_status' => 'active',
        ]);
        Invoice::query()->create([
            'club_id' => $club->id,
            'user_id' => $member->id,
            'number' => 'TERM-1',
            'title' => 'Beitrag',
            'amount' => 20,
            'status' => 'open',
            'source' => 'manual',
            'due_date' => now()->addWeek(),
            'issued_at' => now(),
        ]);

        Sanctum::actingAs($member);

        $this->postJson("/api/v1/clubs/{$club->id}/termination-requests", [
            'requested_termination_on' => now()->addWeek()->toDateString(),
        ])
            ->assertStatus(422)
            ->assertJsonPath('message', 'Offene Rechnungen müssen vor dem Austritt geklärt werden.');

        Sanctum::actingAs($owner);

        $this->postJson("/api/v1/clubs/{$club->id}/termination-requests", [
            'requested_termination_on' => now()->addWeek()->toDateString(),
        ])
            ->assertStatus(422);
    }

    public function test_scheduler_marks_external_members_former(): void
    {
        $owner = User::factory()->create();
        $club = Club::factory()->create(['owner_id' => $owner->id]);
        $endDate = now()->subDay()->toDateString();
        $external = ClubExternalMember::query()->create([
            'club_id' => $club->id,
            'created_by' => $owner->id,
            'name' => 'Externe Person',
            'email' => 'external-termination@example.test',
            'membership_status' => 'active',
            'membership_ends_on' => $endDate,
        ]);

        $this->artisan('airmius:process-membership-terminations', [
            '--date' => $endDate,
        ])->assertExitCode(0);

        $external->refresh();

        $this->assertSame('former', $external->membership_status);
        $this->assertNotNull($external->membership_ended_at);
        $this->assertSame($endDate, $external->membership_ended_at->toDateString());
    }
}
