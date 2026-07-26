<?php

namespace Tests\Feature;

use App\Models\Club;
use App\Models\Team;
use App\Models\TeamInvitation;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class MobileTeamInvitationApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_team_invitation_token_is_visible_only_to_the_invited_email(): void
    {
        $owner = User::factory()->create();
        $recipient = User::factory()->create(['email' => 'member@example.test']);
        $outsider = User::factory()->create(['email' => 'other@example.test']);
        $club = Club::factory()->create(['owner_id' => $owner->id]);
        $team = Team::factory()->create(['club_id' => $club->id, 'name' => 'Token Team']);
        $invitation = TeamInvitation::create([
            'team_id' => $team->id,
            'inviter_id' => $owner->id,
            'email' => $recipient->email,
            'token' => 'secure-team-token',
            'role' => 'Player',
            'status' => 'pending',
        ]);

        Sanctum::actingAs($outsider);
        $this->getJson('/api/v1/team-invitations/token/secure-team-token')
            ->assertForbidden();

        Sanctum::actingAs($recipient);
        $this->getJson('/api/v1/team-invitations/token/secure-team-token')
            ->assertOk()
            ->assertJsonPath('data.id', $invitation->id)
            ->assertJsonPath('data.team.name', 'Token Team')
            ->assertJsonPath('data.role', 'Player');
    }

    public function test_team_invitation_token_can_be_accepted_once_by_the_recipient(): void
    {
        $owner = User::factory()->create();
        $recipient = User::factory()->create(['email' => 'accept@example.test']);
        $club = Club::factory()->create(['owner_id' => $owner->id]);
        $team = Team::factory()->create(['club_id' => $club->id]);
        $invitation = TeamInvitation::create([
            'team_id' => $team->id,
            'inviter_id' => $owner->id,
            'email' => $recipient->email,
            'token' => 'accept-team-token',
            'role' => 'Coach',
            'status' => 'pending',
        ]);

        Sanctum::actingAs($recipient);
        $this->postJson('/api/v1/team-invitations/token/accept-team-token/accept')
            ->assertOk()
            ->assertJsonPath('data.id', $team->id);

        $this->assertDatabaseHas('team_invitations', [
            'id' => $invitation->id,
            'recipient_id' => $recipient->id,
            'status' => 'accepted',
        ]);
        $this->assertDatabaseHas('team_user', [
            'team_id' => $team->id,
            'user_id' => $recipient->id,
            'role' => 'Coach',
        ]);

        $this->getJson('/api/v1/team-invitations/token/accept-team-token')
            ->assertNotFound();
    }

    public function test_team_invitation_token_can_be_declined_once_by_the_recipient(): void
    {
        $owner = User::factory()->create();
        $recipient = User::factory()->create(['email' => 'decline@example.test']);
        $club = Club::factory()->create(['owner_id' => $owner->id]);
        $team = Team::factory()->create(['club_id' => $club->id]);
        $invitation = TeamInvitation::create([
            'team_id' => $team->id,
            'inviter_id' => $owner->id,
            'email' => $recipient->email,
            'token' => 'decline-team-token',
            'role' => 'Player',
            'status' => 'pending',
        ]);

        Sanctum::actingAs($recipient);
        $this->postJson('/api/v1/team-invitations/token/decline-team-token/decline')
            ->assertOk()
            ->assertJsonPath('data.id', $invitation->id)
            ->assertJsonPath('data.status', 'declined');

        $this->assertDatabaseHas('team_invitations', [
            'id' => $invitation->id,
            'recipient_id' => $recipient->id,
            'status' => 'declined',
        ]);

        $this->getJson('/api/v1/team-invitations/token/decline-team-token')
            ->assertNotFound();
    }
}
