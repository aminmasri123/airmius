<?php
namespace Tests\Feature;

use App\Models\Conversation;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ConversationPublicLinkTest extends TestCase
{
    use RefreshDatabase;

    public function test_cleared_chat_links_remain_unavailable_until_a_new_message(): void
    {
        $user = User::factory()->create();
        $peer = User::factory()->create();
        $chat = Conversation::create(['type' => 'direct']);
        $chat->users()->attach([$user->id, $peer->id]);
        $old = \App\Models\Message::create(['conversation_id' => $chat->id, 'sender_id' => $peer->id, 'message' => 'Old private history', 'status' => 'sent', 'moderation_status' => 'approved']);
        $this->actingAs($user)->delete('/conversations/'.$chat->id.'/clear')->assertRedirect();
        foreach ([$chat->id, $chat->public_id] as $id) {
            $this->get('/conversations/'.$id)->assertNotFound();
            $this->getJson('/api/v1/chat/conversations/'.$id)->assertNotFound();
        }
        $this->actingAs($peer)->get('/conversations/'.$chat->public_id)->assertOk();
        $new = \App\Models\Message::create(['conversation_id' => $chat->id, 'sender_id' => $peer->id, 'message' => 'New greeting', 'status' => 'sent', 'moderation_status' => 'approved']);
        $this->actingAs($user)->get('/conversations/'.$chat->public_id)->assertOk();
        $this->getJson('/api/v1/chat/conversations/'.$chat->public_id)->assertOk();
        $this->getJson('/api/v1/chat/conversations/'.$chat->id.'/messages')->assertOk()->assertJsonMissing(['message' => $old->message])->assertJsonFragment(['message' => $new->message]);
    }

    public function test_mobile_accepts_both_link_types_and_rejects_foreign_accounts(): void
    {
        $user = User::factory()->create();
        $chat = Conversation::create(['type' => 'direct']);
        $chat->users()->attach($user->id);
        \Laravel\Sanctum\Sanctum::actingAs($user);
        foreach ([$chat->id, $chat->public_id] as $id) {
            $this->getJson('/api/v1/chat/conversations/'.$id)->assertOk()->assertJsonPath('data.id', $chat->id);
        }
        \Laravel\Sanctum\Sanctum::actingAs(User::factory()->create());
        foreach ([$chat->id, $chat->public_id, 'unknown'] as $id) {
            $this->getJson('/api/v1/chat/conversations/'.$id)->assertNotFound();
        }
    }

    public function test_legacy_link_redirects_only_for_members(): void
    {
        $user = User::factory()->create();
        $chat = Conversation::create(['type' => 'direct']);
        $chat->users()->attach($user->id);
        $this->assertTrue(\Illuminate\Support\Str::isUuid($chat->public_id));
        $this->actingAs($user)->get('/conversations/'.$chat->id)
            ->assertRedirect('/conversations/'.$chat->public_id);
        $this->get('/conversations/'.$chat->id.'?conversation='.$chat->id.'&message_limit=50&message_search=test')
            ->assertRedirect(route('auth.conversations.show', ['conversation' => $chat->public_id, 'message_limit' => 50, 'message_search' => 'test']));
        $this->get('/conversations/'.$chat->public_id.'?message_limit=50')
            ->assertOk()->assertInertia(fn ($page) => $page->component('Auth/Dashboard/Chat/Index')
                ->where('selectedConversation.id', $chat->id));
        $this->actingAs(User::factory()->create());
        foreach ([$chat->id, $chat->public_id, 'missing-chat'] as $identifier) {
            $this->get('/conversations/'.$identifier)->assertNotFound()
                ->assertInertia(fn ($page) => $page->component('Auth/Dashboard/Chat/Unavailable'));
        }
    }
}
