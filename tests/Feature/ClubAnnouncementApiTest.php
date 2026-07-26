<?php

namespace Tests\Feature;

use App\Models\Club;
use App\Models\Team;
use App\Models\User;
use App\Support\TeamRoles;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ClubAnnouncementApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_members_can_read_and_acknowledge_all_member_announcement(): void
    {
        $owner = User::factory()->create();
        $member = User::factory()->create();
        $outsider = User::factory()->create();
        $club = Club::query()->create(['owner_id' => $owner->id, 'name' => 'Notice Club']);
        $club->users()->attach($member->id, ['role' => 'member', 'membership_status' => 'active']);

        Sanctum::actingAs($owner);
        $announcementId = $this->postJson('/api/v1/clubs/'.$club->id.'/announcements', [
            'title' => 'Sommerfest',
            'body' => 'Bitte meldet euch bis Freitag an.',
            'audience_type' => 'all_members',
        ])->assertCreated()
            ->assertJsonPath('data.title', 'Sommerfest')
            ->assertJsonPath('data.read_by_me', false)
            ->json('data.id');

        Sanctum::actingAs($member);
        $this->getJson('/api/v1/clubs/'.$club->id.'/announcements')
            ->assertOk()
            ->assertJsonPath('data.0.id', $announcementId)
            ->assertJsonPath('data.0.read_by_me', false);

        $this->postJson('/api/v1/clubs/'.$club->id.'/announcements/'.$announcementId.'/read')
            ->assertOk()
            ->assertJsonPath('data.announcement_id', $announcementId);

        $this->getJson('/api/v1/clubs/'.$club->id.'/announcements')
            ->assertJsonPath('data.0.read_by_me', true)
            ->assertJsonPath('data.0.read_count', 1);

        Sanctum::actingAs($outsider);
        $this->getJson('/api/v1/clubs/'.$club->id.'/announcements')->assertForbidden();
    }

    public function test_team_announcement_is_only_visible_to_team_members(): void
    {
        $owner = User::factory()->create();
        $teamMember = User::factory()->create();
        $clubMember = User::factory()->create();
        $club = Club::query()->create(['owner_id' => $owner->id, 'name' => 'Team Notice Club']);
        $club->users()->attach([
            $teamMember->id => ['role' => 'member', 'membership_status' => 'active'],
            $clubMember->id => ['role' => 'member', 'membership_status' => 'active'],
        ]);
        $team = Team::factory()->create(['club_id' => $club->id, 'name' => 'U16']);
        $team->users()->attach($teamMember->id, ['role' => TeamRoles::PLAYER]);

        Sanctum::actingAs($owner);
        $announcementId = $this->postJson('/api/v1/clubs/'.$club->id.'/announcements', [
            'title' => 'Teamtreff',
            'body' => 'Treffpunkt ist 17:30 Uhr.',
            'audience_type' => 'team',
            'team_id' => $team->id,
        ])->assertCreated()->json('data.id');

        Sanctum::actingAs($clubMember);
        $this->getJson('/api/v1/clubs/'.$club->id.'/announcements')
            ->assertOk()->assertJsonCount(0, 'data');

        Sanctum::actingAs($teamMember);
        $this->getJson('/api/v1/clubs/'.$club->id.'/announcements')
            ->assertOk()->assertJsonPath('data.0.id', $announcementId);
    }
}
