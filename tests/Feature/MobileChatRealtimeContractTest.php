<?php

namespace Tests\Feature;

use App\Models\Club;
use App\Models\Conversation;
use App\Models\ConversationInvitation;
use App\Models\Friendship;
use App\Models\Message;
use App\Models\MessageHide;
use App\Models\MessageReceipt;
use App\Models\Notification;
use App\Models\Team;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class MobileChatRealtimeContractTest extends TestCase
{
    use RefreshDatabase;

    public function test_mobile_chat_can_create_direct_group_and_team_conversations(): void
    {
        $actor = User::factory()->create();
        $recipient = User::factory()->create();
        $friend = User::factory()->create();
        $secondFriend = User::factory()->create();
        $stranger = User::factory()->create();
        $teamMate = User::factory()->create();

        $this->befriend($actor, $friend);
        $this->befriend($actor, $secondFriend);

        $club = Club::factory()->create(['owner_id' => $actor->id]);
        $team = Team::factory()->create(['club_id' => $club->id]);
        $team->users()->attach($actor->id, ['role' => 'Coach']);
        $team->users()->attach($teamMate->id, ['role' => 'Player']);

        Sanctum::actingAs($actor);

        $directId = $this->postJson('/api/v1/chat/conversations', [
            'type' => 'direct',
            'participant_ids' => [$recipient->id],
            'message' => 'Direktnachricht fuer Training.',
        ])
            ->assertCreated()
            ->assertJsonPath('data.type', 'direct')
            ->assertJsonPath('data.messages_count', 1)
            ->json('data.id');

        $this->assertDatabaseHas('conversation_users', [
            'conversation_id' => $directId,
            'user_id' => $recipient->id,
        ]);
        $this->assertDatabaseHas('messages', [
            'conversation_id' => $directId,
            'sender_id' => $actor->id,
            'message' => 'Direktnachricht fuer Training.',
        ]);

        $groupId = $this->postJson('/api/v1/chat/conversations', [
            'type' => 'group',
            'participant_ids' => [$friend->id, $secondFriend->id],
            'name' => 'Laufgruppe Montag',
            'message' => 'Willkommen in der Gruppe.',
        ])
            ->assertCreated()
            ->assertJsonPath('data.type', 'group')
            ->assertJsonPath('data.name', 'Laufgruppe Montag')
            ->assertJsonPath('data.owner_id', $actor->id)
            ->assertJsonPath('data.messages_count', 1)
            ->json('data.id');

        $this->assertDatabaseHas('conversation_users', [
            'conversation_id' => $groupId,
            'user_id' => $friend->id,
        ]);
        $this->assertDatabaseHas('message_receipts', [
            'user_id' => $secondFriend->id,
        ]);

        $this->postJson('/api/v1/chat/conversations', [
            'type' => 'group',
            'participant_ids' => [$friend->id, $stranger->id],
            'name' => 'Nicht erlaubt',
        ])->assertForbidden();

        $teamConversationId = $this->postJson('/api/v1/chat/conversations', [
            'type' => 'team',
            'team_id' => $team->id,
            'message' => 'Teamchat startet.',
        ])
            ->assertCreated()
            ->assertJsonPath('data.type', 'team')
            ->assertJsonPath('data.team_id', $team->id)
            ->assertJsonPath('data.messages_count', 1)
            ->json('data.id');

        $this->assertDatabaseHas('conversation_users', [
            'conversation_id' => $teamConversationId,
            'user_id' => $teamMate->id,
        ]);

        $this->getJson('/api/v1/chat/conversations?team_id='.$team->id)
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $teamConversationId)
            ->assertJsonPath('data.0.team_id', $team->id);
    }

    public function test_mobile_chat_exposes_typing_state_and_marks_messages_as_read(): void
    {
        $sender = User::factory()->create([
            'name' => 'Lena Lauf',
            'profile_photo_path' => 'profile-photos/lena.jpg',
        ]);
        $recipient = User::factory()->create();
        $conversation = Conversation::create(['type' => 'direct']);
        $conversation->users()->attach([$sender->id, $recipient->id], ['joined_at' => now()]);

        $message = Message::create([
            'conversation_id' => $conversation->id,
            'sender_id' => $sender->id,
            'message' => 'Training startet um 18 Uhr.',
            'status' => 'sent',
        ]);

        $receipt = MessageReceipt::create([
            'message_id' => $message->id,
            'user_id' => $recipient->id,
            'delivered_at' => null,
            'read_at' => null,
        ]);

        $notification = Notification::create([
            'user_id' => $recipient->id,
            'type' => 'chat.message',
            'data' => ['conversation_id' => $conversation->id],
            'read' => false,
        ]);

        Sanctum::actingAs($sender);
        $this->postJson("/api/v1/chat/conversations/{$conversation->id}/typing", ['typing' => true])
            ->assertOk()
            ->assertJsonPath('data.typing', true);

        Sanctum::actingAs($recipient);
        $conversationsResponse = $this->getJson('/api/v1/chat/conversations')
            ->assertOk()
            ->assertJsonPath('data.0.unread_messages_count', 1)
            ->assertJsonPath('data.0.latest_message.message', 'Training startet um 18 Uhr.');

        $senderPayload = collect($conversationsResponse->json('data.0.users'))
            ->firstWhere('id', $sender->id);
        $this->assertSame($sender->profile_photo_thumb, $senderPayload['profile_photo_thumb']);

        $this->getJson("/api/v1/chat/conversations/{$conversation->id}/messages")
            ->assertOk()
            ->assertJsonPath('data.0.sender.profile_photo_thumb', $sender->profile_photo_thumb)
            ->assertJsonPath('chat.typing_users.0.name', 'Lena Lauf');

        $this->postJson("/api/v1/chat/conversations/{$conversation->id}/read")
            ->assertOk()
            ->assertJsonPath('data.read_count', 1)
            ->assertJsonPath('data.read_message_ids.0', $message->id);

        $this->getJson('/api/v1/chat/conversations')
            ->assertOk()
            ->assertJsonPath('data.0.unread_messages_count', 0);

        $this->assertNotNull($receipt->fresh()->read_at);
        $this->assertTrue($notification->fresh()->read);
    }

    public function test_mobile_conversation_list_returns_latest_message_visible_to_current_user(): void
    {
        $user = User::factory()->create();
        $member = User::factory()->create();
        $conversation = Conversation::create(['type' => 'group']);

        $conversation->users()->attach($member->id, ['joined_at' => now()->subHour()]);

        Message::create([
            'conversation_id' => $conversation->id,
            'sender_id' => $member->id,
            'message' => 'Nachricht vor dem Beitritt',
            'status' => 'sent',
            'created_at' => now()->subMinutes(20),
            'updated_at' => now()->subMinutes(20),
        ]);

        $conversation->users()->attach($user->id, ['joined_at' => now()->subMinutes(10)]);

        $visibleMessage = Message::create([
            'conversation_id' => $conversation->id,
            'sender_id' => $member->id,
            'message' => 'Heute laufen wir um 18 Uhr.',
            'status' => 'sent',
            'created_at' => now()->subMinutes(5),
            'updated_at' => now()->subMinutes(5),
        ]);

        $hiddenMessage = Message::create([
            'conversation_id' => $conversation->id,
            'sender_id' => $member->id,
            'message' => 'Persoenlich ausgeblendete Nachricht',
            'status' => 'sent',
            'created_at' => now()->subMinutes(2),
            'updated_at' => now()->subMinutes(2),
        ]);
        MessageHide::create([
            'message_id' => $hiddenMessage->id,
            'user_id' => $user->id,
        ]);

        Message::create([
            'conversation_id' => $conversation->id,
            'sender_id' => $member->id,
            'message' => 'Durch Moderation entfernte Nachricht',
            'status' => 'sent',
            'moderation_status' => 'removed',
        ]);

        Sanctum::actingAs($user);

        $this->getJson('/api/v1/chat/conversations')
            ->assertOk()
            ->assertJsonPath('data.0.latest_message.id', $visibleMessage->id)
            ->assertJsonPath('data.0.latest_message.message', 'Heute laufen wir um 18 Uhr.');
    }

    public function test_mobile_group_chat_has_full_management_and_invitation_lifecycle(): void
    {
        $owner = User::factory()->create(['name' => 'Group Owner']);
        $member = User::factory()->create(['name' => 'Group Member']);
        $invitee = User::factory()->create(['name' => 'New Friend']);
        $conversation = Conversation::create([
            'type' => 'group',
            'owner_id' => $owner->id,
            'name' => 'Alte Gruppe',
        ]);
        $conversation->users()->attach([
            $owner->id => ['role' => Conversation::ROLE_OWNER, 'joined_at' => now()],
            $member->id => ['role' => Conversation::ROLE_MEMBER, 'joined_at' => now()],
        ]);
        $this->befriend($owner, $invitee);

        Sanctum::actingAs($owner);

        $this->putJson("/api/v1/chat/conversations/{$conversation->id}", [
            'name' => 'Neue Laufgruppe',
            'description' => 'Montags und mittwochs',
            'posting_policy' => Conversation::POSTING_MANAGEMENT,
        ])
            ->assertOk()
            ->assertJsonPath('data.name', 'Neue Laufgruppe')
            ->assertJsonPath('data.posting_policy', Conversation::POSTING_MANAGEMENT)
            ->assertJsonPath('data.viewer_role', Conversation::ROLE_OWNER)
            ->assertJsonPath('data.permissions.can_manage_roles', true)
            ->assertJsonFragment([
                'id' => $owner->id,
                'conversation_role' => Conversation::ROLE_OWNER,
            ]);

        $this->putJson("/api/v1/chat/conversations/{$conversation->id}/mute", [
            'minutes' => 480,
        ])
            ->assertOk()
            ->assertJsonPath('data.id', $conversation->id);

        $this->postJson("/api/v1/chat/conversations/{$conversation->id}/members", [
            'participant_ids' => [$invitee->id],
        ])->assertOk();

        $inviteNotification = Notification::query()
            ->where('user_id', $invitee->id)
            ->where('type', 'chat.group_invite')
            ->firstOrFail();
        $this->assertSame('airmius://chat/invitations', $inviteNotification->data['mobile_url']);
        $this->assertSame('airmius://chat/invitations', $inviteNotification->data['deep_link']);

        $invitationId = ConversationInvitation::query()
            ->where('conversation_id', $conversation->id)
            ->where('recipient_id', $invitee->id)
            ->value('id');

        Sanctum::actingAs($invitee);
        $this->getJson('/api/v1/chat/conversation-invitations')
            ->assertOk()
            ->assertJsonPath('data.0.id', $invitationId)
            ->assertJsonPath('data.0.inviter.name', 'Group Owner')
            ->assertJsonPath('data.0.conversation.name', 'Neue Laufgruppe');

        $this->postJson("/api/v1/chat/conversation-invitations/{$invitationId}/accept")
            ->assertOk()
            ->assertJsonFragment(['id' => $invitee->id]);

        Sanctum::actingAs($member);
        $this->getJson("/api/v1/chat/conversations/{$conversation->id}")
            ->assertOk()
            ->assertJsonPath('data.viewer_role', Conversation::ROLE_MEMBER)
            ->assertJsonPath('data.permissions.can_manage_members', false)
            ->assertJsonPath('data.permissions.can_manage_roles', false);

        $this->putJson("/api/v1/chat/conversations/{$conversation->id}", [
            'name' => 'Nicht erlaubt',
        ])->assertForbidden();

        Sanctum::actingAs($owner);
        $this->putJson("/api/v1/chat/conversations/{$conversation->id}/members/{$member->id}/role", [
            'role' => Conversation::ROLE_MODERATOR,
        ])
            ->assertOk()
            ->assertJsonFragment([
                'id' => $member->id,
                'conversation_role' => Conversation::ROLE_MODERATOR,
            ]);

        $this->deleteJson("/api/v1/chat/conversations/{$conversation->id}/members/{$member->id}")
            ->assertOk();
        $this->putJson("/api/v1/chat/conversations/{$conversation->id}/owner", [
            'user_id' => $invitee->id,
        ])
            ->assertOk()
            ->assertJsonPath('data.owner_id', $invitee->id);

        $this->assertDatabaseMissing('conversation_users', [
            'conversation_id' => $conversation->id,
            'user_id' => $member->id,
        ]);
        $this->assertSame($invitee->id, $conversation->fresh()->owner_id);
    }

    private function befriend(User $first, User $second): void
    {
        Friendship::create(['user_id' => $first->id, 'friend_id' => $second->id]);
        Friendship::create(['user_id' => $second->id, 'friend_id' => $first->id]);
    }
}
