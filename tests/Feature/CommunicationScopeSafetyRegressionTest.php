<?php

namespace Tests\Feature;

use App\Models\Club;
use App\Models\ClubAnnouncement;
use App\Models\ContentReport;
use App\Models\Conversation;
use App\Models\Friendship;
use App\Models\Message;
use App\Models\MessageHide;
use App\Models\ModerationFlag;
use App\Models\Notification;
use App\Models\Team;
use App\Models\User;
use App\Support\TeamRoles;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class CommunicationScopeSafetyRegressionTest extends TestCase
{
    use RefreshDatabase;

    public function test_direct_and_group_chats_reject_misleading_foreign_club_context(): void
    {
        $actor = User::factory()->create();
        $friend = User::factory()->create();
        $secondFriend = User::factory()->create();
        $club = Club::query()->create(['owner_id' => $actor->id, 'name' => 'Scope Club']);
        $foreignClub = Club::query()->create(['owner_id' => User::factory()->create()->id, 'name' => 'Foreign Club']);

        $this->befriend($actor, $friend);
        $this->befriend($actor, $secondFriend);
        $club->users()->syncWithoutDetaching([
            $actor->id => ['role' => 'member', 'membership_status' => 'active'],
            $friend->id => ['role' => 'member', 'membership_status' => 'active'],
            $secondFriend->id => ['role' => 'member', 'membership_status' => 'active'],
        ]);
        $foreignClub->users()->syncWithoutDetaching([
            $actor->id => ['role' => 'member', 'membership_status' => 'active'],
        ]);

        Sanctum::actingAs($actor);
        $this->postJson('/api/v1/chat/conversations', [
            'type' => 'direct',
            'club_id' => $foreignClub->id,
            'participant_ids' => [$friend->id],
        ])->assertUnprocessable();

        $this->actingAs($actor)
            ->post(route('auth.conversations.store'), [
                'type' => 'group',
                'club_id' => $foreignClub->id,
                'participant_ids' => [$friend->id, $secondFriend->id],
            ])
            ->assertStatus(422);

        $this->postJson('/api/v1/chat/conversations', [
            'type' => 'group',
            'club_id' => $club->id,
            'participant_ids' => [$friend->id, $secondFriend->id],
            'name' => 'Vereinsgruppe',
        ])->assertCreated()->assertJsonPath('data.club_id', $club->id);
    }

    public function test_pending_minor_cannot_use_team_chat_until_guardian_consent_is_resolved(): void
    {
        Role::findOrCreate('minor_pending_consent', 'web');
        $coach = User::factory()->create();
        $minor = User::factory()->create(['birth_date' => now()->subYears(13)->toDateString()]);
        $club = Club::query()->create(['owner_id' => $coach->id, 'name' => 'Youth Club']);
        $team = Team::factory()->create(['club_id' => $club->id, 'name' => 'U14']);
        $club->users()->syncWithoutDetaching([
            $coach->id => ['role' => 'manager', 'membership_status' => 'active'],
            $minor->id => ['role' => 'member', 'membership_status' => 'active'],
        ]);
        $team->users()->attach([
            $coach->id => ['role' => TeamRoles::COACH],
            $minor->id => ['role' => TeamRoles::PLAYER],
        ]);
        $minor->assignRole('minor_pending_consent');

        Sanctum::actingAs($minor);
        $this->getJson('/api/v1/chat/conversations')->assertForbidden();
        $this->postJson('/api/v1/chat/conversations', [
            'type' => 'team',
            'team_id' => $team->id,
        ])->assertForbidden();

        $minor->removeRole('minor_pending_consent');
        $conversationId = $this->postJson('/api/v1/chat/conversations', [
            'type' => 'team',
            'team_id' => $team->id,
            'message' => 'Training ist bestaetigt.',
        ])->assertCreated()->assertJsonPath('data.team.id', $team->id)->json('data.id');

        $this->postJson('/api/v1/chat/conversations/'.$conversationId.'/messages', [
            'message' => 'Ich bin dabei.',
        ])->assertCreated();
    }

    public function test_message_reporting_and_removed_message_visibility_respect_historical_access(): void
    {
        $owner = User::factory()->create();
        $lateMember = User::factory()->create();
        $conversation = Conversation::query()->create(['type' => 'group', 'owner_id' => $owner->id]);
        $conversation->users()->attach($owner->id, ['joined_at' => now()->subHour()]);
        $old = Message::query()->create([
            'conversation_id' => $conversation->id,
            'sender_id' => $owner->id,
            'message' => 'Alte vertrauliche Message',
            'status' => 'sent',
        ]);
        $old->forceFill([
            'created_at' => now()->subMinutes(30),
            'updated_at' => now()->subMinutes(30),
        ])->save();
        $conversation->users()->attach($lateMember->id, ['joined_at' => now()->subMinutes(5)]);
        $visible = Message::query()->create([
            'conversation_id' => $conversation->id,
            'sender_id' => $owner->id,
            'message' => 'Sichtbare Message nach Beitritt',
            'status' => 'sent',
        ]);
        MessageHide::query()->create(['message_id' => $visible->id, 'user_id' => $lateMember->id]);

        Sanctum::actingAs($lateMember);
        $this->postJson('/api/v1/reports', [
            'type' => 'message',
            'id' => $old->id,
            'reason' => 'other',
            'details' => 'Erratene alte Message-ID.',
        ])->assertForbidden();
        $this->postJson('/api/v1/reports', [
            'type' => 'message',
            'id' => $visible->id,
            'reason' => 'other',
            'details' => 'Verborgene Message soll nicht reportbar sein.',
        ])->assertNotFound();

        $visible->forceFill(['moderation_status' => 'removed'])->save();
        Notification::query()->create([
            'user_id' => $lateMember->id,
            'type' => 'chat.message',
            'data' => [
                'conversation_id' => $conversation->id,
                'message_id' => $visible->id,
                'url' => '/conversations?conversation='.$conversation->id,
            ],
        ]);

        $this->getJson('/api/v1/chat/conversations/'.$conversation->id.'/messages')
            ->assertOk()
            ->assertJsonCount(0, 'data');
        $this->getJson('/api/v1/chat/messages/'.$visible->id)->assertNotFound();

        $this->postJson('/api/v1/notifications/read-all')->assertOk();
        $this->assertDatabaseHas('notifications', [
            'user_id' => $lateMember->id,
            'type' => 'chat.message',
            'read' => false,
        ]);
    }

    public function test_announcement_publish_creates_moderation_flag_and_owner_scoped_notification(): void
    {
        $owner = User::factory()->create();
        $member = User::factory()->create();
        $outsider = User::factory()->create();
        $club = Club::query()->create(['owner_id' => $owner->id, 'name' => 'Moderation Club']);
        $club->users()->attach($member->id, ['role' => 'member', 'membership_status' => 'active']);

        Sanctum::actingAs($owner);
        $announcementId = $this->postJson('/api/v1/clubs/'.$club->id.'/announcements', [
            'title' => 'Wichtige Info',
            'body' => 'Bitte klick hier fuer gratis geld.',
            'audience_type' => 'all_members',
            'publication_mode' => 'now',
        ])->assertCreated()->json('data.id');

        $this->assertDatabaseHas('moderation_flags', [
            'flaggable_type' => ClubAnnouncement::class,
            'flaggable_id' => $announcementId,
            'source' => 'automatic',
        ]);
        $this->assertSame(1, ModerationFlag::query()
            ->where('flaggable_type', ClubAnnouncement::class)
            ->where('flaggable_id', $announcementId)
            ->count());
        $this->assertDatabaseHas('notifications', [
            'user_id' => $member->id,
            'type' => 'club.announcement',
        ]);

        $notification = Notification::query()->where('user_id', $member->id)->firstOrFail();
        $payload = $notification->data;
        $this->assertSame($club->id, $payload['club_id']);
        $this->assertSame($announcementId, $payload['announcement_id']);
        $this->assertArrayNotHasKey('recipients', $payload);

        Sanctum::actingAs($outsider);
        $this->getJson('/api/v1/notifications/'.$notification->id)->assertNotFound();
        $this->getJson('/api/v1/clubs/'.$club->id.'/announcements')->assertForbidden();
    }

    private function befriend(User $first, User $second): void
    {
        Friendship::query()->create(['user_id' => $first->id, 'friend_id' => $second->id]);
        Friendship::query()->create(['user_id' => $second->id, 'friend_id' => $first->id]);
    }
}
