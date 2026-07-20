<?php

namespace Tests\Feature;

use App\Models\Club;
use App\Models\Team;
use App\Models\User;
use App\Support\TeamRoles;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class TeamMemberManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_web_club_owner_can_add_update_and_remove_team_member(): void
    {
        [$owner, $member, $club, $team] = $this->teamFixture();

        $this->actingAs($owner)
            ->post(route('auth.teams.members.store', $team), [
                'user_id' => $member->id,
                'role' => TeamRoles::PLAYER,
            ])
            ->assertRedirect()
            ->assertSessionHasNoErrors()
            ->assertSessionHas('success', 'Mitglied wurde zum Team hinzugefügt.');

        $this->assertDatabaseHas('team_user', [
            'team_id' => $team->id,
            'user_id' => $member->id,
            'role' => TeamRoles::PLAYER,
        ]);

        $conversationId = DB::table('conversations')
            ->where('type', 'team')
            ->where('team_id', $team->id)
            ->value('id');

        $this->assertNotNull($conversationId);
        $this->assertDatabaseHas('conversation_users', [
            'conversation_id' => $conversationId,
            'user_id' => $member->id,
        ]);

        $this->actingAs($owner)
            ->put(route('auth.teams.members.update', [$team, $member]), [
                'role' => TeamRoles::CAPTAIN,
            ])
            ->assertRedirect()
            ->assertSessionHasNoErrors()
            ->assertSessionHas('success', 'Teamrolle aktualisiert.');

        $this->assertDatabaseHas('team_user', [
            'team_id' => $team->id,
            'user_id' => $member->id,
            'role' => TeamRoles::CAPTAIN,
        ]);

        $this->actingAs($owner)
            ->delete(route('auth.teams.members.destroy', [$team, $member]))
            ->assertRedirect()
            ->assertSessionHas('success', 'Mitglied entfernt.');

        $this->assertDatabaseMissing('team_user', [
            'team_id' => $team->id,
            'user_id' => $member->id,
        ]);
        $this->assertDatabaseMissing('conversation_users', [
            'conversation_id' => $conversationId,
            'user_id' => $member->id,
        ]);
    }

    public function test_api_club_owner_can_add_update_and_remove_team_member(): void
    {
        [$owner, $member, $club, $team] = $this->teamFixture();

        Sanctum::actingAs($owner);

        $this->postJson("/api/v1/teams/{$team->id}/members", [
            'user_id' => $member->id,
            'role' => TeamRoles::PLAYER,
        ])
            ->assertCreated()
            ->assertJsonPath('data.id', $team->id)
            ->assertJsonPath('data.can_remove_members', true)
            ->assertJsonPath('data.users.0.id', $member->id)
            ->assertJsonPath('data.users.0.team_role', TeamRoles::PLAYER);

        $this->putJson("/api/v1/teams/{$team->id}/members/{$member->id}", [
            'role' => TeamRoles::TREASURER,
        ])
            ->assertOk()
            ->assertJsonPath('data.users.0.id', $member->id)
            ->assertJsonPath('data.users.0.team_role', TeamRoles::TREASURER);

        $this->deleteJson("/api/v1/teams/{$team->id}/members/{$member->id}")
            ->assertOk()
            ->assertJsonPath('data.id', $team->id);

        $this->assertDatabaseMissing('team_user', [
            'team_id' => $team->id,
            'user_id' => $member->id,
        ]);
    }

    public function test_direct_team_add_requires_existing_club_membership(): void
    {
        [$owner, , , $team] = $this->teamFixture();
        $outsideUser = User::factory()->create();

        Sanctum::actingAs($owner);

        $this->postJson("/api/v1/teams/{$team->id}/members", [
            'user_id' => $outsideUser->id,
            'role' => TeamRoles::PLAYER,
        ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['user_id']);

        $this->assertDatabaseMissing('team_user', [
            'team_id' => $team->id,
            'user_id' => $outsideUser->id,
        ]);
    }

    private function teamFixture(): array
    {
        $owner = User::factory()->create();
        $member = User::factory()->create();
        $club = Club::factory()->create([
            'owner_id' => $owner->id,
            'name' => 'Airmius Team Club',
        ]);
        $team = Team::factory()->create([
            'club_id' => $club->id,
            'name' => 'MVP Team',
        ]);

        $club->users()->syncWithoutDetaching([
            $member->id => [
                'role' => 'member',
                'roles' => ['member'],
                'membership_status' => 'active',
                'joined_on' => now()->toDateString(),
            ],
        ]);

        return [$owner, $member, $club, $team];
    }
}
