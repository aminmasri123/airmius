<?php

namespace Tests\Feature;

use App\Models\FriendInvitation;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class MobileFriendInvitationApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_friend_invitation_token_is_visible_only_to_the_invited_email(): void
    {
        $sender = User::factory()->create(['name' => 'Sender']);
        $recipient = User::factory()->create(['email' => 'friend@example.test']);
        $outsider = User::factory()->create(['email' => 'outsider@example.test']);
        $invitation = FriendInvitation::create([
            'sender_id' => $sender->id,
            'recipient_id' => null,
            'email' => $recipient->email,
            'token' => 'friend-token-preview',
            'status' => 'pending',
        ]);

        Sanctum::actingAs($outsider);
        $this->getJson('/api/v1/friends/invitations/token/friend-token-preview')
            ->assertForbidden();

        Sanctum::actingAs($recipient);
        $this->getJson('/api/v1/friends/invitations/token/friend-token-preview')
            ->assertOk()
            ->assertJsonPath('data.id', $invitation->id)
            ->assertJsonPath('data.sender.name', 'Sender')
            ->assertJsonPath('data.status', 'pending');
    }

    public function test_friend_invitation_token_can_be_accepted_once_by_the_recipient(): void
    {
        Notification::fake();
        $sender = User::factory()->create();
        $recipient = User::factory()->create(['email' => 'accept-friend@example.test']);
        $invitation = FriendInvitation::create([
            'sender_id' => $sender->id,
            'recipient_id' => null,
            'email' => $recipient->email,
            'token' => 'friend-token-accept',
            'status' => 'pending',
        ]);

        Sanctum::actingAs($recipient);
        $this->postJson('/api/v1/friends/invitations/token/friend-token-accept/accept')
            ->assertOk()
            ->assertJsonPath('data.invitation_id', $invitation->id)
            ->assertJsonPath('data.status', 'accepted')
            ->assertJsonPath('data.friend_id', $sender->id);

        $this->assertDatabaseHas('friend_invitations', [
            'id' => $invitation->id,
            'recipient_id' => $recipient->id,
            'status' => 'accepted',
        ]);
        $this->assertDatabaseHas('friendships', [
            'user_id' => $recipient->id,
            'friend_id' => $sender->id,
        ]);
        $this->getJson('/api/v1/friends/invitations/token/friend-token-accept')
            ->assertNotFound();
    }

    public function test_friend_invitation_token_can_be_declined_once_by_the_recipient(): void
    {
        $sender = User::factory()->create();
        $recipient = User::factory()->create(['email' => 'decline-friend@example.test']);
        $invitation = FriendInvitation::create([
            'sender_id' => $sender->id,
            'recipient_id' => null,
            'email' => $recipient->email,
            'token' => 'friend-token-decline',
            'status' => 'pending',
        ]);

        Sanctum::actingAs($recipient);
        $this->postJson('/api/v1/friends/invitations/token/friend-token-decline/decline')
            ->assertOk()
            ->assertJsonPath('data.invitation_id', $invitation->id)
            ->assertJsonPath('data.status', 'declined');

        $this->assertDatabaseHas('friend_invitations', [
            'id' => $invitation->id,
            'recipient_id' => $recipient->id,
            'status' => 'declined',
        ]);
        $this->getJson('/api/v1/friends/invitations/token/friend-token-decline')
            ->assertNotFound();
    }
}
