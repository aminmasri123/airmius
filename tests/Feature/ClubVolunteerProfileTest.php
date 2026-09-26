<?php

namespace Tests\Feature;

use App\Models\Club;
use App\Models\ClubVolunteerProfile;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ClubVolunteerProfileTest extends TestCase
{
    use RefreshDatabase;

    public function test_member_can_model_minimal_volunteer_profile_for_one_club(): void
    {
        [$club, , $member] = $this->clubWithOwner(true);
        Sanctum::actingAs($member);

        $this->putJson("/api/v1/clubs/{$club->id}/volunteer-profile", [
            'skills' => ['Turnieraufbau', 'Erste Hilfe'],
            'interests' => ['Jugendtraining', 'Kiosk'],
            'availability' => ['Samstag vormittag', 'Heimspiele'],
            'workload_limit_minutes_per_week' => 180,
            'visibility' => ClubVolunteerProfile::VISIBILITY_CLUB_MANAGERS,
        ])->assertOk()
            ->assertJsonPath('data.skills', ['Turnieraufbau', 'Erste Hilfe'])
            ->assertJsonPath('data.interests.0', 'Jugendtraining')
            ->assertJsonPath('data.availability.1', 'Heimspiele')
            ->assertJsonPath('data.workload_limit_minutes_per_week', 180)
            ->assertJsonPath('data.visibility', ClubVolunteerProfile::VISIBILITY_CLUB_MANAGERS);

        $this->getJson("/api/v1/clubs/{$club->id}/volunteer-profile")
            ->assertOk()
            ->assertJsonPath('data.skills', ['Turnieraufbau', 'Erste Hilfe']);

        $this->assertDatabaseCount('club_volunteer_profiles', 1);
        $this->putJson("/api/v1/clubs/{$club->id}/volunteer-profile", [
            'skills' => ['Fahrdienst'],
            'interests' => [],
            'availability' => [],
            'workload_limit_minutes_per_week' => null,
            'visibility' => ClubVolunteerProfile::VISIBILITY_PRIVATE,
        ])->assertOk()
            ->assertJsonPath('data.skills', ['Fahrdienst'])
            ->assertJsonPath('data.visibility', ClubVolunteerProfile::VISIBILITY_PRIVATE);
        $this->assertDatabaseCount('club_volunteer_profiles', 1);
    }

    public function test_volunteer_profile_visibility_respects_member_role_and_club_scope(): void
    {
        [$club, $owner, $member] = $this->clubWithOwner(true);
        $viewer = User::factory()->create();
        $club->users()->attach($viewer->id, [
            'role' => 'member', 'roles' => ['member'], 'membership_status' => 'active',
        ]);
        $outsider = User::factory()->create();
        $profile = ClubVolunteerProfile::query()->create([
            'club_id' => $club->id,
            'user_id' => $member->id,
            'skills' => ['Catering'],
            'interests' => ['Sommerfest'],
            'availability' => ['Freitagabend'],
            'workload_limit_minutes_per_week' => 120,
            'visibility' => ClubVolunteerProfile::VISIBILITY_CLUB_MEMBERS,
        ]);

        Sanctum::actingAs($viewer);
        $this->getJson("/api/v1/clubs/{$club->id}/members/{$member->id}/volunteer-profile")
            ->assertOk()
            ->assertJsonPath('data.id', $profile->id);

        $profile->update(['visibility' => ClubVolunteerProfile::VISIBILITY_CLUB_MANAGERS]);
        $this->getJson("/api/v1/clubs/{$club->id}/members/{$member->id}/volunteer-profile")
            ->assertForbidden();

        Sanctum::actingAs($owner);
        $this->getJson("/api/v1/clubs/{$club->id}/members/{$member->id}/volunteer-profile")
            ->assertOk()
            ->assertJsonPath('data.skills', ['Catering']);

        $profile->update(['visibility' => ClubVolunteerProfile::VISIBILITY_PRIVATE]);
        $this->getJson("/api/v1/clubs/{$club->id}/members/{$member->id}/volunteer-profile")
            ->assertForbidden();

        Sanctum::actingAs($member);
        $this->getJson("/api/v1/clubs/{$club->id}/members/{$member->id}/volunteer-profile")
            ->assertOk();

        Sanctum::actingAs($outsider);
        $this->getJson("/api/v1/clubs/{$club->id}/members/{$member->id}/volunteer-profile")
            ->assertForbidden();
    }

    private function clubWithOwner(bool $withMember = false): array
    {
        $owner = User::factory()->create();
        $club = Club::factory()->create(['owner_id' => $owner->id]);
        $member = null;
        if ($withMember) {
            $member = User::factory()->create();
            $club->users()->attach($member->id, [
                'role' => 'member', 'roles' => ['member'], 'membership_status' => 'active',
            ]);
        }

        return [$club, $owner, $member];
    }
}
