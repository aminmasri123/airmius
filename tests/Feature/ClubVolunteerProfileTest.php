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
            'preferred_areas' => ['organization'],
        ])->assertOk()
            ->assertJsonPath('data.skills', ['Turnieraufbau', 'Erste Hilfe'])
            ->assertJsonPath('data.interests.0', 'Jugendtraining')
            ->assertJsonPath('data.availability.1', 'Heimspiele')
            ->assertJsonPath('data.workload_limit_minutes_per_week', 180)
            ->assertJsonPath('data.visibility', ClubVolunteerProfile::VISIBILITY_CLUB_MANAGERS);

        $this->getJson("/api/v1/clubs/{$club->id}/volunteer-candidates")
            ->assertForbidden();

        Sanctum::actingAs($club->owner);
        $this->getJson("/api/v1/clubs/{$club->id}/volunteer-candidates")
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.preferred_areas.0', 'organization');
        $this->assertDatabaseHas('notifications', [
            'user_id' => $club->owner_id,
            'type' => 'club.volunteer_candidate',
        ]);
        Sanctum::actingAs($member);

        $this->putJson("/api/v1/clubs/{$club->id}/volunteer-profile", [
            'skills' => ['Turnieraufbau', 'Erste Hilfe'],
            'interests' => ['Jugendtraining', 'Kiosk'],
            'availability' => ['Samstag vormittag', 'Heimspiele'],
            'workload_limit_minutes_per_week' => 180,
            'visibility' => ClubVolunteerProfile::VISIBILITY_CLUB_MANAGERS,
            'preferred_areas' => ['organization'],
        ])->assertOk();
        $this->assertDatabaseCount('notifications', 1);

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
        Sanctum::actingAs($club->owner);
        $this->getJson("/api/v1/clubs/{$club->id}/volunteer-candidates")
            ->assertOk()->assertJsonCount(0, 'data');
    }

    public function test_candidate_preferences_are_validated_and_scoped_to_club(): void
    {
        [$club, $owner, $member] = $this->clubWithOwner(true);
        $otherClub = Club::factory()->create();
        $foreignTeam = $otherClub->teams()->create(['name' => 'Fremdes Team']);
        Sanctum::actingAs($member);

        $payload = [
            'skills' => ['Aufbau'],
            'availability' => ['Samstag'],
            'visibility' => ClubVolunteerProfile::VISIBILITY_CLUB_MANAGERS,
            'preferred_team_ids' => [$foreignTeam->id],
        ];
        $this->putJson("/api/v1/clubs/{$club->id}/volunteer-profile", $payload)
            ->assertUnprocessable()->assertJsonValidationErrors('preferred_team_ids.0');
        $this->putJson("/api/v1/clubs/{$club->id}/volunteer-profile", [
            ...$payload, 'preferred_team_ids' => [],
        ])->assertUnprocessable()->assertJsonValidationErrors('preferred_areas');

        $team = $club->teams()->create(['name' => 'Jugend']);
        $this->putJson("/api/v1/clubs/{$club->id}/volunteer-profile", [
            ...$payload, 'preferred_team_ids' => [$team->id],
        ])->assertOk()->assertJsonPath('data.preferred_team_ids.0', $team->id);

        Sanctum::actingAs($owner);
        $this->getJson("/api/v1/clubs/{$club->id}/volunteer-candidates")
            ->assertOk()->assertJsonCount(1, 'data');
        $this->getJson("/api/v1/clubs/{$otherClub->id}/volunteer-candidates")
            ->assertForbidden();
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
