<?php

namespace Tests\Feature;

use App\Models\Club;
use App\Models\Notification;
use App\Models\Post;
use App\Models\User;
use Database\Seeders\RolesPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class NotificationCenterFeatureTest extends TestCase
{
    use RefreshDatabase;

    public function test_bulk_read_is_owner_scoped_and_keeps_chat_unread_for_all_personas(): void
    {
        $this->seed(RolesPermissionsSeeder::class);
        foreach (['player', 'coach', 'club_owner', 'sponsor'] as $role) {
            $owner = User::factory()->create();
            $owner->assignRole($role);
            $foreign = User::factory()->create();
            $notice = Notification::query()->create([
                'user_id' => $owner->id, 'type' => 'event.reminder',
                'data' => ['title' => 'QA own notice'], 'read' => false,
            ]);
            $chat = Notification::query()->create([
                'user_id' => $owner->id, 'type' => 'chat.message',
                'data' => ['title' => 'QA chat'], 'read' => false,
            ]);
            $foreignNotice = Notification::query()->create([
                'user_id' => $foreign->id, 'type' => 'event.reminder',
                'data' => ['title' => 'QA foreign notice'], 'read' => false,
            ]);
            Sanctum::actingAs($owner);
            $this->getJson('/api/v1/notifications?unread_only=true')->assertOk()
                ->assertJsonCount(1, 'data');
            $base = '/api/v1/notifications/'.$foreignNotice->id;
            $this->getJson($base)->assertNotFound();
            $this->postJson($base.'/read')->assertNotFound();
            $this->postJson($base.'/unread')->assertNotFound();
            $this->deleteJson($base)->assertNotFound();
            $this->postJson('/api/v1/notifications/read-all')->assertOk()
                ->assertJsonPath('data.unread_count', 0);
            $this->assertTrue($notice->fresh()->read);
            $this->assertFalse($chat->fresh()->read);
            $this->assertFalse($foreignNotice->fresh()->read);
            $this->getJson('/api/v1/notifications?unread_only=true')->assertOk()
                ->assertJsonCount(0, 'data');
        }
    }

    public function test_web_notification_center_handles_read_unread_delete_and_action_links(): void
    {
        $user = User::factory()->create();
        $other = User::factory()->create();

        $notification = Notification::query()->create([
            'user_id' => $user->id,
            'type' => 'event.reminder',
            'data' => [
                'title' => 'Training heute',
                'body' => 'Beginn ist 18:00 Uhr.',
                'action_url' => '/events/7',
            ],
            'read' => false,
        ]);

        Notification::query()->create([
            'user_id' => $user->id,
            'type' => 'chat.message',
            'data' => ['title' => 'Chat'],
            'read' => false,
        ]);

        $otherNotification = Notification::query()->create([
            'user_id' => $other->id,
            'type' => 'event.reminder',
            'data' => ['title' => 'Fremd'],
            'read' => false,
        ]);

        $this->actingAs($user)
            ->get(route('auth.notifications.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->has('notifications.data', 1)
                ->where('notifications.data.0.id', $notification->id)
                ->where('notifications.data.0.title', 'Training heute')
                ->where('notifications.data.0.body', 'Beginn ist 18:00 Uhr.')
                ->where('notifications.data.0.url', '/events/7')
                ->where('notifications.data.0.action_url', '/events/7')
                ->where('notifications.data.0.read', false)
                ->where('notifications.data.0.unread', true)
            );

        $this->actingAs($user)
            ->post(route('auth.notifications.read', $notification))
            ->assertRedirect();

        $this->assertTrue($notification->fresh()->read);

        $this->actingAs($user)
            ->post(route('auth.notifications.unread', $notification))
            ->assertRedirect();

        $this->assertFalse($notification->fresh()->read);

        $this->actingAs($user)
            ->post(route('auth.notifications.read-all'))
            ->assertRedirect();

        $this->assertTrue($notification->fresh()->read);

        $this->actingAs($user)
            ->post(route('auth.notifications.read', $otherNotification))
            ->assertRedirect()
            ->assertSessionHasErrors();

        $this->actingAs($user)
            ->delete(route('auth.notifications.destroy', $notification))
            ->assertRedirect();

        $this->assertDatabaseMissing('notifications', ['id' => $notification->id]);
    }

    public function test_api_notification_center_handles_detail_unread_delete_and_ownership(): void
    {
        $user = User::factory()->create();
        $other = User::factory()->create();

        $notification = Notification::query()->create([
            'user_id' => $user->id,
            'type' => 'invoice.created',
            'data' => [
                'title' => 'Neue Rechnung',
                'message' => 'Deine Vereinsrechnung ist bereit.',
                'url' => '/billing/invoices/11',
            ],
            'read' => false,
        ]);

        Notification::query()->create([
            'user_id' => $user->id,
            'type' => 'event.reminder',
            'data' => ['title' => 'Schon gelesen'],
            'read' => true,
        ]);

        $otherNotification = Notification::query()->create([
            'user_id' => $other->id,
            'type' => 'invoice.created',
            'data' => ['title' => 'Fremd'],
            'read' => false,
        ]);

        Sanctum::actingAs($user);

        $this->getJson('/api/v1/notifications?unread_only=1')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $notification->id)
            ->assertJsonPath('data.0.body', 'Deine Vereinsrechnung ist bereit.')
            ->assertJsonPath('data.0.url', 'airmius://notifications')
            ->assertJsonPath('data.0.action_url', 'airmius://notifications')
            ->assertJsonPath('data.0.data.url', '/billing/invoices/11')
            ->assertJsonPath('data.0.read', false)
            ->assertJsonPath('data.0.unread', true)
            ->assertJsonPath('meta.unread_count', 1);

        $this->getJson("/api/v1/notifications/{$notification->id}")
            ->assertOk()
            ->assertJsonPath('data.id', $notification->id)
            ->assertJsonPath('data.action_url', 'airmius://notifications')
            ->assertJsonPath('data.data.url', '/billing/invoices/11');

        $this->postJson("/api/v1/notifications/{$notification->id}/read")
            ->assertOk()
            ->assertJsonPath('data.read', true)
            ->assertJsonPath('data.unread', false);

        $this->postJson("/api/v1/notifications/{$notification->id}/unread")
            ->assertOk()
            ->assertJsonPath('data.read', false)
            ->assertJsonPath('data.unread', true);

        $this->getJson("/api/v1/notifications/{$otherNotification->id}")
            ->assertNotFound();

        $this->deleteJson("/api/v1/notifications/{$notification->id}")
            ->assertOk()
            ->assertJsonPath('data.deleted', true);

        $this->assertDatabaseMissing('notifications', ['id' => $notification->id]);
    }

    public function test_legacy_member_link_notification_is_routed_to_member_safe_club_profile(): void
    {
        $user = User::factory()->create();
        $club = Club::factory()->create([
            'owner_id' => User::factory()->create()->id,
        ]);
        $club->users()->attach($user->id, [
            'role' => 'member',
            'roles' => ['member'],
            'membership_status' => 'active',
        ]);

        $notification = Notification::query()->create([
            'user_id' => $user->id,
            'type' => 'club.member_linked',
            'data' => [
                'title' => 'Mit Verein verknüpft',
                'url' => '/club-memberships',
                'club_id' => $club->id,
            ],
            'read' => false,
        ]);

        $this->actingAs($user)
            ->get(route('auth.notifications.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('notifications.data.0.id', $notification->id)
                ->where('notifications.data.0.action_url', '/clubs/'.$club->id)
                ->where('notifications.data.0.data.mobile_url', 'airmius://clubs/'.$club->id)
            );

        Sanctum::actingAs($user);
        $this->getJson("/api/v1/notifications/{$notification->id}")
            ->assertOk()
            ->assertJsonPath('data.action_url', '/clubs/'.$club->id)
            ->assertJsonPath('data.data.mobile_url', 'airmius://clubs/'.$club->id);

        $this->actingAs($user)
            ->get(route('auth.clubs.show', $club))
            ->assertOk();
    }

    public function test_post_engagement_notifications_open_the_concrete_post_on_web_and_mobile(): void
    {
        $user = User::factory()->create();
        $post = Post::factory()->create([
            'user_id' => $user->id,
            'visibility' => 'public',
            'moderation_status' => 'approved',
        ]);
        $notification = Notification::query()->create([
            'user_id' => $user->id,
            'type' => 'post.comment',
            'data' => [
                'title' => 'Neuer Kommentar',
                'url' => '/feed',
                'post_id' => $post->id,
            ],
            'read' => false,
        ]);

        $this->actingAs($user)
            ->get(route('auth.notifications.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('notifications.data.0.id', $notification->id)
                ->where('notifications.data.0.action_url', '/feed?post='.$post->id)
                ->where('notifications.data.0.data.mobile_url', 'airmius://feed/'.$post->id)
            );

        Sanctum::actingAs($user);
        $this->getJson("/api/v1/notifications/{$notification->id}")
            ->assertOk()
            ->assertJsonPath('data.action_url', 'airmius://feed/'.$post->id)
            ->assertJsonPath('data.data.action_url', '/feed?post='.$post->id)
            ->assertJsonPath('data.data.mobile_url', 'airmius://feed/'.$post->id);
    }
}
