<?php

namespace Tests\Feature;

use App\Models\Club;
use App\Models\ClubExternalMember;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class MobileClubExternalInvitationApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_club_invitation_token_is_visible_only_to_the_invited_email(): void
    {
        $owner = User::factory()->create();
        $recipient = User::factory()->create(['email' => 'club-member@example.test']);
        $outsider = User::factory()->create(['email' => 'club-outsider@example.test']);
        $club = Club::factory()->create(['owner_id' => $owner->id, 'name' => 'Airmius Club']);
        $externalMember = ClubExternalMember::create([
            'club_id' => $club->id,
            'created_by' => $owner->id,
            'name' => 'Club Member',
            'email' => $recipient->email,
            'role' => 'member',
            'membership_status' => 'pending',
        ])->issueInvitation(now()->addDay());

        Sanctum::actingAs($outsider);
        $this->getJson('/api/v1/club-external-invitations/'.$externalMember->invitation_token)
            ->assertForbidden();

        Sanctum::actingAs($recipient);
        $this->getJson('/api/v1/club-external-invitations/'.$externalMember->invitation_token)
            ->assertOk()
            ->assertJsonPath('data.club.name', 'Airmius Club')
            ->assertJsonPath('data.role', 'member')
            ->assertJsonPath('data.status', 'pending');
    }

    public function test_club_invitation_token_can_be_accepted_once_by_the_recipient(): void
    {
        $owner = User::factory()->create();
        $recipient = User::factory()->create(['email' => 'accept-club@example.test']);
        $club = Club::factory()->create(['owner_id' => $owner->id]);
        $externalMember = ClubExternalMember::create([
            'club_id' => $club->id,
            'created_by' => $owner->id,
            'name' => 'Club Member',
            'email' => $recipient->email,
            'role' => 'trainer',
            'membership_status' => 'active',
        ])->issueInvitation(now()->addDay());

        Sanctum::actingAs($recipient);
        $this->postJson('/api/v1/club-external-invitations/'.$externalMember->invitation_token.'/accept')
            ->assertOk();

        $this->assertDatabaseHas('club_user', [
            'club_id' => $club->id,
            'user_id' => $recipient->id,
            'role' => 'trainer',
        ]);
        $this->assertDatabaseMissing('club_external_members', [
            'id' => $externalMember->id,
        ]);
        $this->getJson('/api/v1/club-external-invitations/'.$externalMember->invitation_token)
            ->assertNotFound();
    }

    public function test_club_invitation_token_can_be_declined_once_by_the_recipient(): void
    {
        $owner = User::factory()->create();
        $recipient = User::factory()->create(['email' => 'decline-club@example.test']);
        $club = Club::factory()->create(['owner_id' => $owner->id]);
        $externalMember = ClubExternalMember::create([
            'club_id' => $club->id,
            'created_by' => $owner->id,
            'name' => 'Club Member',
            'email' => $recipient->email,
            'role' => 'member',
            'membership_status' => 'pending',
        ])->issueInvitation(now()->addDay());

        Sanctum::actingAs($recipient);
        $this->postJson('/api/v1/club-external-invitations/'.$externalMember->invitation_token.'/decline')
            ->assertOk()
            ->assertJsonPath('data.status', 'declined');

        $this->assertDatabaseHas('club_external_members', [
            'id' => $externalMember->id,
            'invitation_status' => 'declined',
        ]);
        $this->getJson('/api/v1/club-external-invitations/'.$externalMember->invitation_token)
            ->assertNotFound();
    }
}
