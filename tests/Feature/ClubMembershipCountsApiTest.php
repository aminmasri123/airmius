<?php

namespace Tests\Feature;

use App\Models\Club;
use App\Models\ClubExternalMember;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ClubMembershipCountsApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_member_sees_active_internal_and_external_counts_without_management_records(): void
    {
        $owner = User::factory()->create();
        $viewer = User::factory()->create();
        $otherActive = User::factory()->create();
        $paused = User::factory()->create();
        $club = Club::factory()->create(['owner_id' => $owner->id]);

        $club->users()->attach($viewer->id, ['role' => 'member', 'membership_status' => 'active']);
        $club->users()->attach($otherActive->id, ['role' => 'member', 'membership_status' => 'active']);
        $club->users()->attach($paused->id, ['role' => 'member', 'membership_status' => 'paused']);
        foreach (['active', 'former'] as $status) {
            ClubExternalMember::query()->create([
                'club_id' => $club->id,
                'created_by' => $owner->id,
                'name' => 'External '.$status,
                'email' => $status.'@example.test',
                'role' => 'member',
                'membership_status' => $status,
            ]);
        }

        Sanctum::actingAs($viewer);
        $this->getJson('/api/v1/clubs/'.$club->id)
            ->assertOk()
            ->assertJsonPath('data.management.can_manage', false)
            ->assertJsonPath('data.management.summary.active_members_count', 3)
            ->assertJsonPath('data.management.summary.linked_people_count', 5)
            ->assertJsonMissingPath('data.management.members')
            ->assertJsonMissingPath('data.management.external_members');

        Sanctum::actingAs($owner);
        $this->getJson('/api/v1/clubs/'.$club->id)
            ->assertOk()
            ->assertJsonPath('data.management.summary.active_members_count', 3)
            ->assertJsonPath('data.management.summary.linked_people_count', 5);
    }
}
