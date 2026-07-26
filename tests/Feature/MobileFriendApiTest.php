<?php

namespace Tests\Feature;

use App\Models\FriendInvitation;
use App\Models\Friendship;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MobileFriendApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_mobile_friend_api_supports_invite_accept_list_and_remove(): void
    {
        $sender = User::factory()->create();
        $recipient = User::factory()->create();

        $invite = $this->actingAs($sender)
            ->postJson('/api/v1/friends/invitations', [
                'user_id' => $recipient->id,
            ])
            ->assertCreated()
            ->assertJsonPath('data.recipient_id', $recipient->id);

        $invitationId = $invite->json('data.invitation_id');

        $this->actingAs($recipient)
            ->getJson('/api/v1/friends')
            ->assertOk()
            ->assertJsonPath('data.receivedInvitations.0.id', $invitationId)
            ->assertJsonPath('data.receivedInvitations.0.sender.id', $sender->id);

        $this->actingAs($recipient)
            ->postJson('/api/v1/friends/invitations/'.$invitationId.'/accept')
            ->assertOk()
            ->assertJsonPath('data.status', 'accepted');

        $this->assertDatabaseHas(Friendship::class, [
            'user_id' => $sender->id,
            'friend_id' => $recipient->id,
        ]);
        $this->assertDatabaseHas(Friendship::class, [
            'user_id' => $recipient->id,
            'friend_id' => $sender->id,
        ]);

        $this->actingAs($recipient)
            ->deleteJson('/api/v1/friends/'.$sender->id)
            ->assertOk()
            ->assertJsonPath('data.deleted', true);

        $this->assertDatabaseMissing(Friendship::class, [
            'user_id' => $sender->id,
            'friend_id' => $recipient->id,
        ]);
    }

    public function test_user_cannot_accept_another_users_invitation(): void
    {
        $sender = User::factory()->create();
        $recipient = User::factory()->create();
        $stranger = User::factory()->create();
        $invitation = FriendInvitation::query()->create([
            'sender_id' => $sender->id,
            'recipient_id' => $recipient->id,
            'email' => $recipient->email,
            'token' => str_repeat('a', 64),
            'status' => 'pending',
        ]);

        $this->actingAs($stranger)
            ->postJson('/api/v1/friends/invitations/'.$invitation->id.'/accept')
            ->assertForbidden();
    }
}
