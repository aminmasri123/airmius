<?php

namespace Tests\Feature;

use App\Models\Conversation;
use App\Models\ConversationInvitation;
use App\Models\File;
use App\Models\Friendship;
use App\Models\Message;
use App\Models\MessageAttachment;
use App\Models\MessageReceipt;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class ChatSecurityTest extends TestCase
{
    use RefreshDatabase;

    public function test_group_supports_multiple_owners_and_moderators(): void
    {
        $owner = User::factory()->create();
        $secondOwner = User::factory()->create();
        $moderator = User::factory()->create();
        $member = User::factory()->create();
        $conversation = Conversation::create([
            'type' => 'group',
            'owner_id' => $owner->id,
            'name' => 'Organisation',
        ]);
        $conversation->users()->attach([
            $owner->id => ['role' => Conversation::ROLE_OWNER, 'joined_at' => now()],
            $secondOwner->id => ['role' => Conversation::ROLE_MEMBER, 'joined_at' => now()],
            $moderator->id => ['role' => Conversation::ROLE_MODERATOR, 'joined_at' => now()],
            $member->id => ['role' => Conversation::ROLE_MEMBER, 'joined_at' => now()],
        ]);

        $this->actingAs($owner)
            ->put(route('auth.conversations.members.role.update', [$conversation, $secondOwner]), [
                'role' => Conversation::ROLE_OWNER,
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('conversation_users', [
            'conversation_id' => $conversation->id,
            'user_id' => $secondOwner->id,
            'role' => Conversation::ROLE_OWNER,
        ]);

        $this->actingAs($moderator)
            ->delete(route('auth.conversations.members.destroy', [$conversation, $member]))
            ->assertRedirect();

        $this->assertDatabaseMissing('conversation_users', [
            'conversation_id' => $conversation->id,
            'user_id' => $member->id,
        ]);

        $this->actingAs($moderator)
            ->delete(route('auth.conversations.members.destroy', [$conversation, $secondOwner]))
            ->assertSessionHasErrors();
    }

    public function test_last_group_owner_cannot_be_demoted(): void
    {
        $owner = User::factory()->create();
        $member = User::factory()->create();
        $conversation = Conversation::create(['type' => 'group', 'owner_id' => $owner->id]);
        $conversation->users()->attach([
            $owner->id => ['role' => Conversation::ROLE_OWNER, 'joined_at' => now()],
            $member->id => ['role' => Conversation::ROLE_MEMBER, 'joined_at' => now()],
        ]);

        $this->actingAs($owner)
            ->put(route('auth.conversations.members.role.update', [$conversation, $owner]), [
                'role' => Conversation::ROLE_MEMBER,
            ])
            ->assertUnprocessable();

        $this->assertSame(Conversation::ROLE_OWNER, $conversation->fresh()->roleFor($owner->id));
    }

    public function test_restricted_group_only_allows_management_to_send_messages(): void
    {
        $owner = User::factory()->create();
        $moderator = User::factory()->create();
        $member = User::factory()->create();
        $conversation = Conversation::create([
            'type' => 'group',
            'owner_id' => $owner->id,
            'posting_policy' => Conversation::POSTING_MANAGEMENT,
        ]);
        $conversation->users()->attach([
            $owner->id => ['role' => Conversation::ROLE_OWNER, 'joined_at' => now()],
            $moderator->id => ['role' => Conversation::ROLE_MODERATOR, 'joined_at' => now()],
            $member->id => ['role' => Conversation::ROLE_MEMBER, 'joined_at' => now()],
        ]);

        $this->actingAs($member)
            ->post(route('auth.messages.store'), [
                'conversation_id' => $conversation->id,
                'message' => 'Nicht erlaubt',
            ])
            ->assertSessionHasErrors();

        $this->actingAs($moderator)
            ->post(route('auth.messages.store'), [
                'conversation_id' => $conversation->id,
                'message' => 'Moderationsmeldung',
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('messages', [
            'conversation_id' => $conversation->id,
            'sender_id' => $moderator->id,
            'message' => 'Moderationsmeldung',
        ]);
    }

    public function test_only_an_owner_can_delete_a_group_for_everyone(): void
    {
        $owner = User::factory()->create();
        $moderator = User::factory()->create();
        $conversation = Conversation::create(['type' => 'group', 'owner_id' => $owner->id]);
        $conversation->users()->attach([
            $owner->id => ['role' => Conversation::ROLE_OWNER, 'joined_at' => now()],
            $moderator->id => ['role' => Conversation::ROLE_MODERATOR, 'joined_at' => now()],
        ]);

        $this->actingAs($moderator)
            ->delete(route('auth.conversations.destroy', $conversation))
            ->assertSessionHasErrors();

        $this->actingAs($owner)
            ->delete(route('auth.conversations.destroy', $conversation))
            ->assertRedirect(route('auth.conversations.index'));

        $this->assertDatabaseMissing('conversations', ['id' => $conversation->id]);
    }

    public function test_web_user_can_delete_a_direct_chat_only_for_themselves(): void
    {
        $viewer = User::factory()->create();
        $peer = User::factory()->create();
        $conversation = Conversation::create(['type' => 'direct']);
        $conversation->users()->attach([$viewer->id, $peer->id], ['joined_at' => now()->subMinute()]);
        $message = Message::create([
            'conversation_id' => $conversation->id,
            'sender_id' => $peer->id,
            'message' => 'Privater Verlauf',
            'status' => 'sent',
        ]);

        $this->actingAs($viewer)
            ->delete(route('auth.conversations.clear', $conversation))
            ->assertRedirect(route('auth.conversations.index'));

        $this->get(route('auth.conversations.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->has('conversations', 0));

        $this->actingAs($peer)
            ->get(route('auth.conversations.index', ['conversation' => $conversation->id]))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->has('selectedConversation.messages', 1)
                ->where('selectedConversation.messages.0.id', $message->id));
    }

    public function test_opening_chat_index_without_selection_does_not_mark_everything_as_read(): void
    {
        $user = User::factory()->create();
        $sender = User::factory()->create();
        $conversation = Conversation::create(['type' => 'direct']);
        $conversation->users()->attach([$user->id, $sender->id], ['joined_at' => now()]);

        $message = Message::create([
            'conversation_id' => $conversation->id,
            'sender_id' => $sender->id,
            'message' => 'Noch ungelesen',
            'status' => 'sent',
        ]);

        $receipt = MessageReceipt::create([
            'message_id' => $message->id,
            'user_id' => $user->id,
        ]);

        $this->actingAs($user)
            ->get(route('auth.conversations.index'))
            ->assertOk();

        $this->assertNull($receipt->fresh()->read_at);
    }

    public function test_group_conversations_can_only_include_allowed_friends(): void
    {
        $actor = User::factory()->create();
        $friend = User::factory()->create();
        $secondFriend = User::factory()->create();
        $stranger = User::factory()->create();

        $this->befriend($actor, $friend);
        $this->befriend($actor, $secondFriend);

        $this->actingAs($actor)
            ->post(route('auth.conversations.store'), [
                'type' => 'group',
                'participant_ids' => [$friend->id, $stranger->id],
            ])
            ->assertRedirect()
            ->assertSessionHasErrors();

        $this->actingAs($actor)
            ->post(route('auth.conversations.store'), [
                'type' => 'group',
                'participant_ids' => [$friend->id, $secondFriend->id],
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('conversations', ['type' => 'group']);
    }

    public function test_web_direct_and_group_chats_can_be_created_with_initial_messages(): void
    {
        $actor = User::factory()->create();
        $recipient = User::factory()->create();
        $friend = User::factory()->create();
        $secondFriend = User::factory()->create();

        $this->befriend($actor, $friend);
        $this->befriend($actor, $secondFriend);

        $this->actingAs($actor)
            ->post(route('auth.conversations.store'), [
                'type' => 'direct',
                'participant_ids' => [$recipient->id],
                'message' => 'Direktnachricht aus Web.',
            ])
            ->assertRedirect();

        $direct = Conversation::query()
            ->where('type', 'direct')
            ->whereHas('users', fn ($query) => $query->where('users.id', $actor->id))
            ->whereHas('users', fn ($query) => $query->where('users.id', $recipient->id))
            ->firstOrFail();

        $this->assertDatabaseHas('messages', [
            'conversation_id' => $direct->id,
            'sender_id' => $actor->id,
            'message' => 'Direktnachricht aus Web.',
        ]);
        $this->assertDatabaseHas('message_receipts', [
            'user_id' => $recipient->id,
        ]);

        $this->actingAs($actor)
            ->post(route('auth.conversations.store'), [
                'type' => 'group',
                'participant_ids' => [$friend->id, $secondFriend->id],
                'name' => 'Laufgruppe Montag',
                'message' => 'Willkommen im Gruppenchat.',
            ])
            ->assertRedirect();

        $group = Conversation::query()
            ->where('type', 'group')
            ->where('owner_id', $actor->id)
            ->where('name', 'Laufgruppe Montag')
            ->firstOrFail();

        $this->assertDatabaseHas('conversation_users', [
            'conversation_id' => $group->id,
            'user_id' => $friend->id,
        ]);
        $this->assertDatabaseHas('messages', [
            'conversation_id' => $group->id,
            'sender_id' => $actor->id,
            'message' => 'Willkommen im Gruppenchat.',
        ]);
    }

    public function test_chat_file_preview_requires_visible_chat_membership(): void
    {
        config([
            'filesystems.uploads_disk' => 'public',
            'filesystems.uploads_url' => '/storage',
        ]);
        Storage::fake('public');

        $sender = User::factory()->create();
        $recipient = User::factory()->create();
        $outsider = User::factory()->create();

        $conversation = Conversation::create(['type' => 'direct']);
        $conversation->users()->attach([$sender->id, $recipient->id], ['joined_at' => now()]);

        $message = Message::create([
            'conversation_id' => $conversation->id,
            'sender_id' => $sender->id,
            'message' => null,
            'status' => 'sent',
        ]);

        Storage::disk('public')->put('users/'.$sender->id.'/chat/example.txt', 'private chat file');

        $file = File::create([
            'user_id' => $sender->id,
            'display_name' => 'example.txt',
            'path' => 'users/'.$sender->id.'/chat/example.txt',
            'type' => 'text/plain',
            'size' => 17,
        ]);

        MessageAttachment::create([
            'message_id' => $message->id,
            'file_id' => $file->id,
        ]);

        $this->actingAs($recipient)
            ->get(route('auth.files.preview', $file))
            ->assertOk();

        $this->delete(route('auth.conversations.clear', $conversation))->assertRedirect();
        $this->get(route('auth.files.preview', $file))->assertForbidden();

        $this->actingAs($sender)
            ->get(route('auth.files.preview', $file))
            ->assertOk();

        $this->actingAs($outsider)
            ->get(route('auth.files.preview', $file))
            ->assertForbidden();
    }

    public function test_group_chat_preview_uses_only_messages_visible_since_joining(): void
    {
        $user = User::factory()->create();
        $firstMember = User::factory()->create();
        $secondMember = User::factory()->create();
        $conversation = Conversation::create(['type' => 'group']);

        $conversation->users()->attach($firstMember->id, ['joined_at' => now()->subHour()]);
        $conversation->users()->attach($secondMember->id, ['joined_at' => now()->subHour()]);

        Message::create([
            'conversation_id' => $conversation->id,
            'sender_id' => $firstMember->id,
            'message' => 'Alte interne Nachricht',
            'status' => 'sent',
            'created_at' => now()->subMinutes(40),
            'updated_at' => now()->subMinutes(40),
        ]);

        $conversation->users()->attach($user->id, ['joined_at' => now()->subMinutes(5)]);

        Message::create([
            'conversation_id' => $conversation->id,
            'sender_id' => $secondMember->id,
            'message' => 'Neue sichtbare Nachricht',
            'status' => 'sent',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->actingAs($user)
            ->get(route('auth.conversations.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('conversations.0.latest_visible_message.message', 'Neue sichtbare Nachricht')
            );
    }

    public function test_only_group_owner_can_update_group_profile(): void
    {
        $owner = User::factory()->create();
        $member = User::factory()->create();
        $conversation = Conversation::create([
            'type' => 'group',
            'owner_id' => $owner->id,
        ]);
        $conversation->users()->attach([$owner->id, $member->id], ['joined_at' => now()]);

        $this->actingAs($member)
            ->put(route('auth.conversations.update', $conversation), [
                'name' => 'Falscher Name',
                'description' => 'Soll nicht gespeichert werden.',
            ])
            ->assertRedirect()
            ->assertSessionHasErrors();

        $this->actingAs($owner)
            ->put(route('auth.conversations.update', $conversation), [
                'name' => 'Laufgruppe Montag',
                'description' => 'Alles rund um das Montagstraining.',
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('conversations', [
            'id' => $conversation->id,
            'name' => 'Laufgruppe Montag',
            'description' => 'Alles rund um das Montagstraining.',
        ]);
    }

    public function test_muted_conversation_suppresses_chat_notifications(): void
    {
        $sender = User::factory()->create();
        $recipient = User::factory()->create();
        $conversation = Conversation::create(['type' => 'direct']);
        $conversation->users()->attach([$sender->id, $recipient->id], ['joined_at' => now()]);

        $this->actingAs($recipient)
            ->put(route('auth.conversations.mute', $conversation), [
                'minutes' => 60,
            ])
            ->assertRedirect();

        $this->actingAs($sender)
            ->post(route('auth.messages.store'), [
                'conversation_id' => $conversation->id,
                'message' => 'Leise Nachricht',
            ])
            ->assertRedirect();

        $this->assertDatabaseMissing('notifications', [
            'user_id' => $recipient->id,
            'type' => 'chat.message',
        ]);
    }

    public function test_group_owner_invites_member_instead_of_directly_adding_them(): void
    {
        $owner = User::factory()->create();
        $member = User::factory()->create();
        $invitee = User::factory()->create();
        $conversation = Conversation::create([
            'type' => 'group',
            'owner_id' => $owner->id,
        ]);
        $conversation->users()->attach([$owner->id, $member->id], ['joined_at' => now()]);
        $this->befriend($owner, $invitee);

        $this->actingAs($owner)
            ->post(route('auth.conversations.members.store', $conversation), [
                'participant_ids' => [$invitee->id],
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('conversation_invitations', [
            'conversation_id' => $conversation->id,
            'recipient_id' => $invitee->id,
            'status' => 'pending',
        ]);

        $this->assertDatabaseMissing('conversation_users', [
            'conversation_id' => $conversation->id,
            'user_id' => $invitee->id,
        ]);

        $systemMessage = Message::query()
            ->where('conversation_id', $conversation->id)
            ->where('kind', 'system')
            ->latest('id')
            ->first();

        $this->assertNotNull($systemMessage);
        $this->assertSame('group.invitation.created', $systemMessage->metadata['event']);
    }

    public function test_group_invitation_acceptance_adds_member_from_accept_time(): void
    {
        $owner = User::factory()->create();
        $invitee = User::factory()->create();
        $conversation = Conversation::create([
            'type' => 'group',
            'owner_id' => $owner->id,
        ]);
        $conversation->users()->attach($owner->id, ['joined_at' => now()]);
        $invitation = ConversationInvitation::create([
            'conversation_id' => $conversation->id,
            'inviter_id' => $owner->id,
            'recipient_id' => $invitee->id,
            'status' => 'pending',
        ]);

        $this->actingAs($invitee)
            ->post(route('auth.conversation-invitations.accept', $invitation))
            ->assertRedirect(route('auth.conversations.index', ['conversation' => $conversation->id]));

        $this->assertDatabaseHas('conversation_invitations', [
            'id' => $invitation->id,
            'status' => 'accepted',
        ]);

        $this->assertDatabaseHas('conversation_users', [
            'conversation_id' => $conversation->id,
            'user_id' => $invitee->id,
        ]);

        $systemMessage = Message::query()
            ->where('conversation_id', $conversation->id)
            ->where('kind', 'system')
            ->latest('id')
            ->first();

        $this->assertNotNull($systemMessage);
        $this->assertSame('group.member.joined', $systemMessage->metadata['event']);
    }

    public function test_group_owner_can_remove_member_and_transfer_owner(): void
    {
        $owner = User::factory()->create();
        $member = User::factory()->create();
        $nextOwner = User::factory()->create();
        $conversation = Conversation::create([
            'type' => 'group',
            'owner_id' => $owner->id,
        ]);
        $conversation->users()->attach([$owner->id, $member->id, $nextOwner->id], ['joined_at' => now()]);

        $this->actingAs($owner)
            ->delete(route('auth.conversations.members.destroy', [$conversation, $member]))
            ->assertRedirect();

        $this->assertDatabaseMissing('conversation_users', [
            'conversation_id' => $conversation->id,
            'user_id' => $member->id,
        ]);

        $this->actingAs($owner)
            ->put(route('auth.conversations.owner.update', $conversation), [
                'user_id' => $nextOwner->id,
            ])
            ->assertRedirect();

        $this->assertSame($nextOwner->id, $conversation->fresh()->owner_id);

        $this->assertTrue(
            Message::query()
                ->where('conversation_id', $conversation->id)
                ->where('kind', 'system')
                ->where('message', 'like', '%Owner%')
                ->exists()
        );
    }

    public function test_chat_message_search_filters_visible_messages(): void
    {
        $user = User::factory()->create();
        $sender = User::factory()->create();
        $conversation = Conversation::create(['type' => 'direct']);
        $conversation->users()->attach([$user->id, $sender->id], ['joined_at' => now()]);

        Message::create([
            'conversation_id' => $conversation->id,
            'sender_id' => $sender->id,
            'message' => 'Laufplan fuer Montag',
            'status' => 'sent',
        ]);
        Message::create([
            'conversation_id' => $conversation->id,
            'sender_id' => $sender->id,
            'message' => 'Ganz anderes Thema',
            'status' => 'sent',
        ]);

        $this->actingAs($user)
            ->get(route('auth.conversations.index', [
                'conversation' => $conversation->id,
                'message_search' => 'Laufplan',
            ]))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->has('selectedConversation.messages', 1)
                ->where('selectedConversation.messages.0.message', 'Laufplan fuer Montag')
            );
    }

    public function test_user_can_hide_message_only_for_themselves(): void
    {
        $user = User::factory()->create();
        $sender = User::factory()->create();
        $conversation = Conversation::create(['type' => 'direct']);
        $conversation->users()->attach([$user->id, $sender->id], ['joined_at' => now()]);

        $message = Message::create([
            'conversation_id' => $conversation->id,
            'sender_id' => $sender->id,
            'message' => 'Nur fuer mich ausblenden',
            'status' => 'sent',
        ]);

        $this->actingAs($user)
            ->delete(route('auth.messages.hide', $message))
            ->assertOk();

        $this->actingAs($user)
            ->get(route('auth.conversations.index', ['conversation' => $conversation->id]))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->has('selectedConversation.messages', 0)
            );

        $this->assertDatabaseHas('messages', ['id' => $message->id]);
    }

    private function befriend(User $first, User $second): void
    {
        Friendship::create(['user_id' => $first->id, 'friend_id' => $second->id]);
        Friendship::create(['user_id' => $second->id, 'friend_id' => $first->id]);
    }
}
