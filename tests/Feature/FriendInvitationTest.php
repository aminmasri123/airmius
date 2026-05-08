<?php

namespace Tests\Feature;

use App\Models\FriendInvitation;
use App\Models\Friendship;
use App\Models\User;
use App\Notifications\ExternalFriendInvitation;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
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
}
