<?php

namespace Tests\Feature;

use App\Models\Conversation;
use App\Models\ConversationInvitation;
use App\Models\Message;
use App\Models\MessageHide;
use App\Models\MessageReceipt;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class MobileChatMessageApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_direct_chat_can_be_cleared_for_one_participant_and_reappears_with_only_new_messages(): void
    {
        $viewer = User::factory()->create();
        $peer = User::factory()->create();
        $conversation = Conversation::create(['type' => 'direct']);
        $conversation->users()->attach([
            $viewer->id => ['joined_at' => now()->subMinute()],
            $peer->id => ['joined_at' => now()->subMinute()],
        ]);
        $oldMessage = Message::create([
            'conversation_id' => $conversation->id,
            'sender_id' => $peer->id,
            'message' => 'Alter Verlauf',
            'status' => 'sent',
        ]);

        $this->travel(1)->minutes();
        $this->actingAs($viewer)
            ->deleteJson('/api/v1/chat/conversations/'.$conversation->id.'/clear')
            ->assertOk();

        $this->getJson('/api/v1/chat/conversations')->assertOk()->assertJsonCount(0, 'data');
        $this->getJson('/api/v1/chat/messages/'.$oldMessage->id)->assertNotFound();

        $this->actingAs($peer)
            ->getJson('/api/v1/chat/conversations/'.$conversation->id.'/messages')
            ->assertOk()
            ->assertJsonCount(1, 'data');

        $this->postJson('/api/v1/chat/conversations/'.$conversation->id.'/messages', [
            'message' => 'Neue Nachricht',
        ])->assertCreated();

        $this->actingAs($viewer)
            ->getJson('/api/v1/chat/conversations')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.latest_message.message', 'Neue Nachricht');
        $this->getJson('/api/v1/chat/conversations/'.$conversation->id.'/messages')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.message', 'Neue Nachricht');
        $this->travelBack();
    }

    public function test_group_chat_cannot_be_cleared_with_the_direct_chat_action(): void
    {
        $member = User::factory()->create();
        $conversation = Conversation::create(['type' => 'group', 'owner_id' => $member->id]);
        $conversation->users()->attach($member->id, ['joined_at' => now()]);

        $this->actingAs($member)
            ->deleteJson('/api/v1/chat/conversations/'.$conversation->id.'/clear')
            ->assertUnprocessable();

        $this->assertNull($conversation->users()->findOrFail($member->id)->pivot->cleared_at);
    }

    public function test_group_and_its_messages_are_deleted_when_the_last_member_leaves_without_a_delete_flag(): void
    {
        $member = User::factory()->create();
        $conversation = Conversation::create(['type' => 'group', 'owner_id' => $member->id]);
        $conversation->users()->attach($member->id, ['joined_at' => now()]);
        $message = Message::create([
            'conversation_id' => $conversation->id,
            'sender_id' => $member->id,
            'message' => 'Letzte Nachricht der Gruppe',
            'status' => 'sent',
        ]);

        $this->actingAs($member)
            ->deleteJson('/api/v1/chat/conversations/'.$conversation->id.'/leave')
            ->assertOk();

        $this->assertDatabaseMissing('conversations', ['id' => $conversation->id]);
        $this->assertDatabaseMissing('messages', ['id' => $message->id]);
        $this->assertDatabaseMissing('conversation_users', [
            'conversation_id' => $conversation->id,
            'user_id' => $member->id,
        ]);
    }

    public function test_group_and_messages_remain_when_another_member_is_still_present(): void
    {
        $owner = User::factory()->create();
        $member = User::factory()->create();
        $conversation = Conversation::create(['type' => 'group', 'owner_id' => $owner->id]);
        $conversation->users()->attach([$owner->id, $member->id], ['joined_at' => now()]);
        $message = Message::create([
            'conversation_id' => $conversation->id,
            'sender_id' => $owner->id,
            'message' => 'Nachricht bleibt für das verbleibende Mitglied',
            'status' => 'sent',
        ]);

        $this->actingAs($member)
            ->deleteJson('/api/v1/chat/conversations/'.$conversation->id.'/leave')
            ->assertOk();

        $this->assertDatabaseHas('conversations', ['id' => $conversation->id]);
        $this->assertDatabaseHas('messages', ['id' => $message->id]);
        $this->assertDatabaseHas('conversation_users', [
            'conversation_id' => $conversation->id,
            'user_id' => $owner->id,
        ]);
        $this->assertDatabaseMissing('conversation_users', [
            'conversation_id' => $conversation->id,
            'user_id' => $member->id,
        ]);
    }

    public function test_group_invitation_can_only_be_accepted_by_its_recipient_once(): void
    {
        $owner = User::factory()->create();
        $recipient = User::factory()->create();
        $outsider = User::factory()->create();
        $conversation = Conversation::create(['type' => 'group', 'owner_id' => $owner->id]);
        $conversation->users()->attach($owner->id, ['joined_at' => now()]);
        $invitation = ConversationInvitation::create([
            'conversation_id' => $conversation->id,
            'inviter_id' => $owner->id,
            'recipient_id' => $recipient->id,
            'status' => 'pending',
        ]);
        $base = '/api/v1/chat/conversation-invitations/'.$invitation->id;
        $this->actingAs($outsider)->postJson($base.'/accept')->assertForbidden();
        $this->postJson($base.'/decline')->assertForbidden();
        $this->assertSame('pending', $invitation->fresh()->status);
        $this->assertFalse($conversation->users()->whereKey($outsider->id)->exists());

        $this->actingAs($recipient)->postJson($base.'/accept')->assertOk();
        $this->assertSame('accepted', $invitation->fresh()->status);
        $joinedAt = $conversation->users()->findOrFail($recipient->id)->pivot->joined_at;
        $messageCount = Message::count();
        $this->travel(1)->minutes();
        $this->postJson($base.'/accept')->assertUnprocessable();
        $this->postJson($base.'/decline')->assertUnprocessable();
        $this->assertSame($joinedAt, $conversation->users()->findOrFail($recipient->id)->pivot->joined_at);
        $this->assertSame('accepted', $invitation->fresh()->status);
        $this->assertDatabaseCount('messages', $messageCount);
        $this->travelBack();
    }

    public function test_rejoining_a_group_does_not_mark_inaccessible_old_messages_as_read(): void
    {
        $owner = User::factory()->create();
        $member = User::factory()->create();
        $conversation = Conversation::create(['type' => 'group', 'owner_id' => $owner->id]);
        $conversation->users()->attach([$owner->id, $member->id], ['joined_at' => now()->subHour()]);
        $old = Message::create([
            'conversation_id' => $conversation->id,
            'sender_id' => $owner->id,
            'message' => 'QA before rejoining',
            'status' => 'sent',
        ]);
        $receipt = MessageReceipt::create(['message_id' => $old->id, 'user_id' => $member->id]);
        $base = '/api/v1/chat/conversations/'.$conversation->id;
        $this->actingAs($owner)->deleteJson($base.'/members/'.$member->id)->assertOk();
        $this->travel(1)->minutes();
        $invitation = ConversationInvitation::create([
            'conversation_id' => $conversation->id,
            'inviter_id' => $owner->id,
            'recipient_id' => $member->id,
            'status' => 'pending',
        ]);
        $this->actingAs($member)
            ->postJson('/api/v1/chat/conversation-invitations/'.$invitation->id.'/accept')->assertOk();
        $this->getJson('/api/v1/chat/messages/'.$old->id)->assertForbidden();
        $new = Message::create([
            'conversation_id' => $conversation->id,
            'sender_id' => $owner->id,
            'message' => 'QA after rejoining',
            'status' => 'sent',
        ]);
        $newReceipt = MessageReceipt::create(['message_id' => $new->id, 'user_id' => $member->id]);
        $this->getJson('/api/v1/chat/conversations')->assertOk()
            ->assertJsonPath('data.0.unread_messages_count', 1);
        $this->postJson($base.'/read')->assertOk()
            ->assertJsonPath('data.read_message_ids', [$new->id])
            ->assertJsonPath('data.read_count', 1);
        $this->assertNull($receipt->fresh()->read_at);
        $this->assertNull($receipt->fresh()->delivered_at);
        $this->assertNotNull($newReceipt->fresh()->read_at);
        $this->postJson($base.'/read')->assertOk()->assertJsonPath('data.read_count', 0);
        $this->getJson('/api/v1/chat/conversations')->assertOk()
            ->assertJsonPath('data.0.unread_messages_count', 0);
        $this->get(route('auth.conversations.index'))->assertOk()
            ->assertInertia(fn (Assert $page) => $page->where('conversations.0.unread_count', 0));
        $this->assertNull($receipt->fresh()->delivered_at);
        $this->travelBack();
    }

    public function test_new_group_members_cannot_list_messages_from_before_joining(): void
    {
        $owner = User::factory()->create();
        $member = User::factory()->create();
        $conversation = Conversation::create(['type' => 'group', 'owner_id' => $owner->id]);
        $conversation->users()->attach($owner->id, ['joined_at' => now()->subHour()]);
        $conversation->users()->attach($member->id, ['joined_at' => now()->subMinutes(5)]);
        $old = Message::create([
            'conversation_id' => $conversation->id,
            'sender_id' => $owner->id,
            'message' => 'QA confidential history',
            'status' => 'sent',
        ]);
        $old->forceFill(['created_at' => now()->subMinutes(10)])->save();
        $visible = Message::create([
            'conversation_id' => $conversation->id,
            'sender_id' => $owner->id,
            'message' => 'QA visible after joining',
            'status' => 'sent',
        ]);

        $this->actingAs($member);
        $this->getJson('/api/v1/chat/messages/'.$old->id)->assertForbidden();
        $this->getJson('/api/v1/chat/conversations/'.$conversation->id.'/messages')
            ->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.id', $visible->id);
        $this->actingAs($owner)
            ->getJson('/api/v1/chat/conversations/'.$conversation->id.'/messages')
            ->assertOk()->assertJsonCount(2, 'data');
    }

    public function test_outsiders_and_removed_members_cannot_access_or_mutate_group_messages(): void
    {
        $owner = User::factory()->create();
        $member = User::factory()->create();
        $outsider = User::factory()->create();
        $conversation = Conversation::create(['type' => 'group', 'owner_id' => $owner->id]);
        $conversation->users()->attach([$owner->id, $member->id], ['joined_at' => now()->subMinute()]);
        $message = Message::create([
            'conversation_id' => $conversation->id,
            'sender_id' => $owner->id,
            'message' => 'QA private group message',
            'status' => 'sent',
        ]);
        $receipt = MessageReceipt::create(['message_id' => $message->id, 'user_id' => $member->id]);
        $base = '/api/v1/chat/conversations/'.$conversation->id;
        $messagePath = '/api/v1/chat/messages/'.$message->id;

        $this->actingAs($member)->getJson($messagePath)->assertOk();
        $this->actingAs($owner)->deleteJson($base.'/members/'.$member->id)->assertOk();
        $messageCountAfterRemoval = Message::count();

        foreach ([$outsider, $member] as $deniedUser) {
            $this->actingAs($deniedUser);
            $this->getJson($base)->assertForbidden();
            $this->getJson($base.'/messages')->assertForbidden();
            $this->getJson($messagePath)->assertForbidden();
            $this->postJson($base.'/messages', ['message' => 'Forbidden message'])->assertForbidden();
            $this->postJson($base.'/read')->assertForbidden();
            $this->postJson($base.'/typing', ['is_typing' => true])->assertForbidden();
            $this->postJson($messagePath.'/reactions', ['reaction' => 'like'])->assertForbidden();
            $this->deleteJson($messagePath.'/hide')->assertForbidden();
            $this->deleteJson($messagePath)->assertForbidden();
            $this->getJson('/api/v1/chat/conversations')->assertOk()->assertJsonCount(0, 'data');
        }

        $this->assertNull($receipt->fresh()->read_at);
        $this->assertSame('QA private group message', $message->fresh()->message);
        $this->assertDatabaseCount('messages', $messageCountAfterRemoval);
        $this->assertDatabaseCount('message_hides', 0);
        $this->assertDatabaseCount('message_reactions', 0);
        $this->actingAs($owner)->getJson($messagePath)->assertOk();
    }

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
