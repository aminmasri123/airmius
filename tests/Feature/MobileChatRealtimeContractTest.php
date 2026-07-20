<?php

namespace Tests\Feature;

use App\Models\Club;
use App\Models\Conversation;
use App\Models\Friendship;
use App\Models\Message;
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
    }

    public function test_mobile_chat_exposes_typing_state_and_marks_messages_as_read(): void
    {
        $sender = User::factory()->create(['name' => 'Lena Lauf']);
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
        $this->getJson("/api/v1/chat/conversations/{$conversation->id}/messages")
            ->assertOk()
            ->assertJsonPath('chat.typing_users.0.name', 'Lena Lauf');

        $this->postJson("/api/v1/chat/conversations/{$conversation->id}/read")
            ->assertOk()
            ->assertJsonPath('data.read_count', 1)
            ->assertJsonPath('data.read_message_ids.0', $message->id);

        $this->assertNotNull($receipt->fresh()->read_at);
        $this->assertTrue($notification->fresh()->read);
    }

    private function befriend(User $first, User $second): void
    {
        Friendship::create(['user_id' => $first->id, 'friend_id' => $second->id]);
        Friendship::create(['user_id' => $second->id, 'friend_id' => $first->id]);
    }
}
