<?php

namespace Tests\Feature;

use App\Models\Conversation;
use App\Models\Message;
use App\Models\MessageHide;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MobileChatMessageApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_participant_can_open_a_message_deep_link_but_an_outsider_cannot(): void
    {
        $participant = User::factory()->create();
        $sender = User::factory()->create();
        $outsider = User::factory()->create();
        $conversation = Conversation::create(['type' => 'direct']);
        $conversation->users()->attach([
            $participant->id => ['joined_at' => now()],
            $sender->id => ['joined_at' => now()],
        ]);
        $message = Message::create([
            'conversation_id' => $conversation->id,
            'sender_id' => $sender->id,
            'message' => 'Direkte Nachricht für den Deep-Link.',
            'status' => 'sent',
        ]);

        $this->actingAs($participant)
            ->getJson('/api/v1/chat/messages/'.$message->id)
            ->assertOk()
            ->assertJsonPath('data.id', $message->id)
            ->assertJsonPath('data.conversation_id', $conversation->id)
            ->assertJsonPath('data.message', 'Direkte Nachricht für den Deep-Link.');

        $this->actingAs($outsider)
            ->getJson('/api/v1/chat/messages/'.$message->id)
            ->assertForbidden();
    }

    public function test_hidden_message_deep_link_returns_not_found_for_the_hiding_participant(): void
    {
        $participant = User::factory()->create();
        $sender = User::factory()->create();
        $conversation = Conversation::create(['type' => 'direct']);
        $conversation->users()->attach([
            $participant->id => ['joined_at' => now()],
            $sender->id => ['joined_at' => now()],
        ]);
        $message = Message::create([
            'conversation_id' => $conversation->id,
            'sender_id' => $sender->id,
            'message' => 'Nur für mich verborgen.',
            'status' => 'sent',
        ]);
        MessageHide::create([
            'message_id' => $message->id,
            'user_id' => $participant->id,
        ]);

        $this->actingAs($participant)
            ->getJson('/api/v1/chat/messages/'.$message->id)
            ->assertNotFound();
    }
}
