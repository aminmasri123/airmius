<?php

namespace Tests\Feature;

use App\Models\FriendInvitation;
use App\Models\Friendship;
use App\Models\User;
use App\Notifications\ExternalFriendInvitation;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class FriendInvitationTest extends TestCase
{
    use RefreshDatabase;

    public function test_external_friend_invitation_is_sent_by_email(): void
    {
        Notification::fake();

        $sender = User::factory()->create();

        $this->actingAs($sender)
            ->post(route('auth.friends.invitations.store'), [
                'email' => 'external@example.com',
            ])
            ->assertSessionHasNoErrors()
            ->assertRedirect();

        $invitation = FriendInvitation::query()->firstOrFail();

        $this->assertSame($sender->id, $invitation->sender_id);
        $this->assertNull($invitation->recipient_id);
        $this->assertSame('external@example.com', $invitation->email);
        $this->assertNotNull($invitation->token);

        Notification::assertSentOnDemand(ExternalFriendInvitation::class);
    }

    public function test_external_friend_invitation_can_be_accepted_after_registration(): void
    {
        Notification::fake();

        $sender = User::factory()->create();

        $this->actingAs($sender)
            ->post(route('auth.friends.invitations.store'), [
                'email' => 'external@example.com',
            ]);

        $recipient = User::factory()->create(['email' => 'external@example.com']);
        $invitation = FriendInvitation::query()->firstOrFail();

        $this->actingAs($recipient)
            ->get(route('auth.friends.invitations.accept-by-token', $invitation->token))
            ->assertRedirect(route('auth.friends.index'));

        $this->assertDatabaseHas('friend_invitations', [
            'id' => $invitation->id,
            'recipient_id' => $recipient->id,
            'status' => 'accepted',
        ]);

        $this->assertTrue(Friendship::query()
            ->where('user_id', $sender->id)
            ->where('friend_id', $recipient->id)
            ->exists());

        $this->assertTrue(Friendship::query()
            ->where('user_id', $recipient->id)
            ->where('friend_id', $sender->id)
            ->exists());
    }

    public function test_sender_can_withdraw_a_pending_friend_invitation_from_the_app(): void
    {
        Notification::fake();

        $sender = User::factory()->create();
        $recipient = User::factory()->create();

        Sanctum::actingAs($sender);

        $this->postJson('/api/v1/friends/invitations', [
            'user_id' => $recipient->id,
        ])->assertCreated();

        $invitation = FriendInvitation::query()->firstOrFail();

        $this->deleteJson("/api/v1/friends/invitations/{$invitation->id}")
            ->assertOk()
            ->assertJsonPath('data.status', 'cancelled');

        $this->assertDatabaseHas('friend_invitations', [
            'id' => $invitation->id,
            'sender_id' => $sender->id,
            'status' => 'cancelled',
        ]);
        $this->assertNotNull($invitation->fresh()->responded_at);

        Sanctum::actingAs($recipient);

        $this->getJson('/api/v1/friends')
            ->assertOk()
            ->assertJsonPath('data.receivedInvitations', []);
    }

    public function test_recipient_cannot_withdraw_a_friend_invitation(): void
    {
        $sender = User::factory()->create();
        $recipient = User::factory()->create();

        Sanctum::actingAs($sender);
        $this->postJson('/api/v1/friends/invitations', [
            'user_id' => $recipient->id,
        ])->assertCreated();

        $invitation = FriendInvitation::query()->firstOrFail();

        Sanctum::actingAs($recipient);
        $this->deleteJson("/api/v1/friends/invitations/{$invitation->id}")
            ->assertForbidden();

        $this->assertSame('pending', $invitation->fresh()->status);
    }
}
