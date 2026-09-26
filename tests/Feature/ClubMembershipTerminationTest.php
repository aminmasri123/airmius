<?php

namespace Tests\Feature;

use App\Models\Club;
use App\Models\ClubAccessHandoverReview;
use App\Models\ClubExternalMember;
use App\Models\ClubInventoryItem;
use App\Models\ClubInventoryLoan;
use App\Models\ClubMembershipRequest;
use App\Models\ClubPermissionDelegation;
use App\Models\ClubRoleAssignment;
use App\Models\ClubRoleDefinition;
use App\Models\Invoice;
use App\Models\Notification;
use App\Models\Team;
use App\Models\User;
use App\Support\ClubPermissions;
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
        $role = ClubRoleDefinition::query()->create([
            'club_id' => $club->id, 'key' => 'temporary_office', 'name' => 'Temporary office',
            'permissions' => ['members.manage'], 'is_active' => true,
        ]);
        ClubRoleAssignment::query()->create([
            'club_id' => $club->id, 'club_role_definition_id' => $role->id,
            'user_id' => $member->id, 'assigned_by' => $owner->id,
        ]);
        $delegation = ClubPermissionDelegation::query()->create([
            'club_id' => $club->id, 'grantor_user_id' => $owner->id, 'grantee_user_id' => $member->id,
            'permissions' => ['finance.view'], 'starts_at' => now(), 'ends_at' => now()->addMonth(),
        ]);
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

        $terminationNotification = Notification::query()
            ->where('user_id', $member->id)
            ->where('type', 'club.membership_request_approved')
            ->latest('id')
            ->firstOrFail();
        $this->assertSame('organization.notifications.termination_confirmed_title', $terminationNotification->data['i18n']['title_key']);
        $this->assertSame('organization.notifications.termination_confirmed_body', $terminationNotification->data['i18n']['body_key']);
        $this->assertSame('membership_termination_confirmed', $terminationNotification->data['lifecycle_event']);
        $this->assertSame($terminationDate, $terminationNotification->data['requested_termination_on']);
        $this->assertSame($terminationDate, $terminationNotification->data['membership_ends_on']);
        $this->assertSame('termination', $terminationNotification->data['request_type']);

        $this->assertDatabaseHas('club_user', [
            'club_id' => $club->id,
            'user_id' => $member->id,
            'membership_status' => 'active',
            'membership_ends_on' => $terminationDate,
            'membership_ended_at' => null,
        ]);
        $this->assertDatabaseHas('club_access_handover_reviews', [
            'club_id' => $club->id,
            'departing_user_id' => $member->id,
            'status' => 'pending',
            'delegation_count' => 1,
        ]);
        $this->assertSame(
            $terminationDate,
            ClubAccessHandoverReview::query()->firstOrFail()->due_on->toDateString(),
        );

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
        $this->assertDatabaseMissing('club_role_assignments', [
            'club_id' => $club->id,
            'user_id' => $member->id,
        ]);
        $this->assertNotNull($delegation->fresh()->revoked_at);
        $this->assertDatabaseHas('activities', [
            'club_id' => $club->id,
            'type' => 'club.role_access.ended',
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

    public function test_active_loans_block_termination_and_finances_are_rechecked_on_approval(): void
    {
        $owner = User::factory()->create();
        $member = User::factory()->create();
        $club = Club::factory()->create(['owner_id' => $owner->id]);
        $club->users()->attach($member->id, [
            'role' => 'member',
            'roles' => ['member'],
            'membership_status' => 'active',
        ]);
        $item = ClubInventoryItem::query()->create([
            'club_id' => $club->id,
            'name' => 'Vereinsjacke',
            'quantity_total' => 1,
            'quantity_available' => 0,
            'condition' => 'good',
            'status' => 'active',
        ]);
        $loan = ClubInventoryLoan::query()->create([
            'club_id' => $club->id,
            'club_inventory_item_id' => $item->id,
            'borrower_id' => $member->id,
            'checked_out_by' => $owner->id,
            'quantity' => 1,
            'status' => 'active',
            'checked_out_at' => now(),
        ]);

        Sanctum::actingAs($member);
        $payload = ['requested_termination_on' => now()->addWeek()->toDateString()];
        $this->postJson("/api/v1/clubs/{$club->id}/termination-requests", $payload)
            ->assertUnprocessable()
            ->assertJsonPath('message', 'Ausgeliehenes Vereinsmaterial muss vor dem Austritt zurückgegeben werden.');

        $loan->update(['status' => 'returned', 'returned_at' => now(), 'returned_to' => $owner->id]);
        $this->postJson("/api/v1/clubs/{$club->id}/termination-requests", $payload)->assertCreated();
        $membershipRequest = ClubMembershipRequest::query()->where('type', 'termination')->firstOrFail();

        Invoice::query()->create([
            'club_id' => $club->id,
            'user_id' => $member->id,
            'number' => 'TERM-LATE-1',
            'title' => 'Nachberechneter Beitrag',
            'amount' => 15,
            'status' => 'open',
            'source' => 'manual',
            'due_date' => now()->addWeek(),
            'issued_at' => now(),
        ]);

        Sanctum::actingAs($owner);
        $this->postJson("/api/v1/clubs/{$club->id}/membership-requests/{$membershipRequest->id}/approve")
            ->assertUnprocessable()
            ->assertJsonPath('message', 'Offene Rechnungen müssen vor dem Austritt geklärt werden.');
        $this->assertSame('pending', $membershipRequest->fresh()->status);
    }

    public function test_scheduler_notifications_follow_the_member_management_permission(): void
    {
        $owner = User::factory()->create();
        $specialist = User::factory()->create();
        $blockedManager = User::factory()->create();
        $departingMember = User::factory()->create();
        $club = Club::factory()->create(['owner_id' => $owner->id]);
        $endDate = now()->subDay()->toDateString();
        $club->users()->attach([
            $specialist->id => [
                'role' => 'member', 'roles' => ['member'], 'membership_status' => 'active',
                'permission_overrides' => [ClubPermissions::MEMBERS_MANAGE => true],
            ],
            $blockedManager->id => [
                'role' => 'manager', 'roles' => ['manager'], 'membership_status' => 'active',
                'permission_overrides' => [ClubPermissions::MEMBERS_MANAGE => false],
            ],
            $departingMember->id => [
                'role' => 'member', 'roles' => ['member'], 'membership_status' => 'active',
                'membership_ends_on' => $endDate,
            ],
        ]);

        $this->artisan('airmius:process-membership-terminations', [
            '--date' => $endDate,
        ])->assertExitCode(0);

        $this->assertDatabaseHas('notifications', [
            'user_id' => $specialist->id,
            'type' => 'club.member_membership_ended',
        ]);
        $this->assertFalse(Notification::query()
            ->where('user_id', $blockedManager->id)
            ->where('type', 'club.member_membership_ended')
            ->exists());
    }
}
