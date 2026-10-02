<?php

namespace Tests\Feature;

use App\Models\FriendInvitation;
use App\Models\Friendship;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class MobileSocialProfileApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_mobile_direct_messages_follow_profile_privacy_setting(): void
    {
        $viewer = User::factory()->create();
        $recipient = User::factory()->create([
            'direct_message_privacy' => 'everyone',
        ]);

        Sanctum::actingAs($viewer);

        $this->postJson('/api/v1/chat/conversations', [
            'type' => 'direct',
            'participant_ids' => [$recipient->id],
            'message' => 'Hallo aus dem Profil.',
        ])
            ->assertCreated()
            ->assertJsonPath('data.type', 'direct');

        $recipient->update(['direct_message_privacy' => 'friends']);

        $blockedByPrivacy = User::factory()->create();
        $this->postJson('/api/v1/chat/conversations', [
            'type' => 'direct',
            'participant_ids' => [$blockedByPrivacy->id],
        ])->assertCreated();

        $blockedByPrivacy->update(['direct_message_privacy' => 'friends']);

        $this->postJson('/api/v1/chat/conversations', [
            'type' => 'direct',
            'participant_ids' => [$blockedByPrivacy->id],
        ])
            ->assertForbidden()
            ->assertJsonPath('message', 'Diese Person erlaubt keine Nachrichten von dir.');
    }

    public function test_mobile_profile_exposes_and_updates_follow_and_block_state(): void
    {
        $viewer = User::factory()->create();
        $profile = User::factory()->create([
            'profile_visibility' => 'public',
            'direct_message_privacy' => 'everyone',
            'profile_photo_path' => 'profile-photos/profile.jpg',
        ]);
        $viewer->givePermissionTo(Permission::findOrCreate('follow.user', 'web'));
        Friendship::create(['user_id' => $viewer->id, 'friend_id' => $profile->id]);
        Friendship::create(['user_id' => $profile->id, 'friend_id' => $viewer->id]);
        $invitation = FriendInvitation::create([
            'sender_id' => $viewer->id,
            'recipient_id' => $profile->id,
            'status' => 'pending',
        ]);

        Sanctum::actingAs($viewer);

        $this->getJson("/api/v1/users/{$profile->id}/sport-cv")
            ->assertOk()
            ->assertJsonPath('data.profile.profile_photo_url', $profile->profile_photo_url)
            ->assertJsonPath('data.social.profile_user_id', $profile->id)
            ->assertJsonPath('data.social.is_following', false)
            ->assertJsonPath('data.social.can_follow', true)
            ->assertJsonPath('data.social.can_send_message', true)
            ->assertJsonPath('data.social.has_blocked', false)
            ->assertJsonPath('data.social.is_blocked', false);

        $this->postJson("/api/v1/users/{$profile->id}/follow")
            ->assertOk()
            ->assertJsonPath('data.is_following', true);

        $this->assertDatabaseHas('follows', [
            'follower_id' => $viewer->id,
            'followed_id' => $profile->id,
        ]);

        $this->postJson("/api/v1/users/{$profile->id}/block")
            ->assertOk()
            ->assertJsonPath('data.has_blocked', true)
            ->assertJsonPath('data.is_following', false)
            ->assertJsonPath('data.can_send_message', false);

        $this->assertDatabaseHas('user_blocks', [
            'user_id' => $viewer->id,
            'blocked_user_id' => $profile->id,
        ]);
        $this->assertDatabaseMissing('follows', [
            'follower_id' => $viewer->id,
            'followed_id' => $profile->id,
        ]);
        $this->assertDatabaseMissing('friendships', [
            'user_id' => $viewer->id,
            'friend_id' => $profile->id,
        ]);
        $this->assertDatabaseMissing('friendships', [
            'user_id' => $profile->id,
            'friend_id' => $viewer->id,
        ]);
        $this->assertSame('declined', $invitation->fresh()->status);

        $this->deleteJson("/api/v1/users/{$profile->id}/block")
            ->assertOk()
            ->assertJsonPath('data.has_blocked', false)
            ->assertJsonPath('data.can_follow', true);

        $this->deleteJson("/api/v1/users/{$profile->id}/follow")
            ->assertOk()
            ->assertJsonPath('data.is_following', false);

        $this->assertDatabaseMissing('user_blocks', [
            'user_id' => $viewer->id,
            'blocked_user_id' => $profile->id,
        ]);
        $this->assertDatabaseMissing('follows', [
            'follower_id' => $viewer->id,
            'followed_id' => $profile->id,
        ]);
    }
}
