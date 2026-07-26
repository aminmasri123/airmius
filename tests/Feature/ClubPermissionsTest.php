<?php

namespace Tests\Feature;

use App\Models\Club;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ClubPermissionsTest extends TestCase
{
    use RefreshDatabase;

    public function test_club_manager_can_manage_members_and_view_but_not_change_finance_by_default(): void
    {
        $owner = User::factory()->create();
        $manager = User::factory()->create();
        $club = Club::factory()->create(['owner_id' => $owner->id]);
        $club->users()->attach($manager->id, [
            'role' => 'manager',
            'roles' => ['manager'],
            'membership_status' => 'active',
        ]);

        Sanctum::actingAs($manager);

        $this->getJson("/api/v1/clubs/{$club->id}/members")
            ->assertOk();
        $this->getJson("/api/v1/clubs/{$club->id}/billing")
            ->assertOk();
        $this->postJson("/api/v1/clubs/{$club->id}/finance-entries", [
            'type' => 'expense',
            'account' => 'cash',
            'title' => 'Nicht erlaubt',
            'amount' => 10,
        ])->assertForbidden();
    }

    public function test_owner_can_grant_finance_permissions_to_one_club_member(): void
    {
        $owner = User::factory()->create();
        $manager = User::factory()->create();
        $club = Club::factory()->create(['owner_id' => $owner->id]);
        $club->users()->attach($manager->id, [
            'role' => 'manager',
            'roles' => ['manager'],
            'membership_status' => 'active',
        ]);

        Sanctum::actingAs($owner);
        $this->putJson("/api/v1/clubs/{$club->id}/members/{$manager->id}/permissions", [
            'permissions' => [
                'finance.view' => true,
                'finance.manage' => true,
            ],
        ])
            ->assertOk()
            ->assertJsonFragment(['finance.manage' => true])
            ->assertJsonFragment(['finance.view' => true]);

        $this->assertDatabaseHas('club_user', [
            'club_id' => $club->id,
            'user_id' => $manager->id,
        ]);

        Sanctum::actingAs($manager);
        $this->getJson("/api/v1/clubs/{$club->id}/billing")
            ->assertOk();
    }

    public function test_member_cannot_change_club_permissions(): void
    {
        $owner = User::factory()->create();
        $member = User::factory()->create();
        $club = Club::factory()->create(['owner_id' => $owner->id]);
        $club->users()->attach($member->id, [
            'role' => 'member',
            'roles' => ['member'],
            'membership_status' => 'active',
        ]);

        Sanctum::actingAs($member);

        $this->putJson("/api/v1/clubs/{$club->id}/members/{$member->id}/permissions", [
            'permissions' => ['finance.view' => true],
        ])->assertForbidden();
    }
}
