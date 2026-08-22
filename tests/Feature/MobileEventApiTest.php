<?php

namespace Tests\Feature;

use App\Models\Club;
use App\Models\Event;
use App\Models\EventParticipant;
use App\Models\MobileDeviceToken;
use App\Models\MobilePushDelivery;
use App\Models\Notification;
use App\Models\Team;
use App\Models\User;
use App\Support\TeamRoles;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class MobileEventApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_mobile_event_participation_can_be_saved_changed_and_withdrawn(): void
    {
        $owner = User::factory()->create([
            'language' => 'en',
            'notification_channels' => ['push' => true, 'club' => true],
            'notification_quiet_time' => 'none',
        ]);
        $user = User::factory()->create(['name' => 'Mia Member']);

        MobileDeviceToken::query()->create([
            'user_id' => $owner->id,
            'device_id' => 'event-owner-android',
            'platform' => 'android',
            'provider' => 'fcm',
            'token' => 'event-owner-fcm-token',
            'token_hash' => hash('sha256', 'event-owner-fcm-token'),
            'channels' => ['event_reminders'],
            'permissions' => ['notifications' => true],
            'timezone' => 'Europe/Berlin',
        ]);

        $event = Event::query()->create([
            'user_id' => $owner->id,
            'title' => 'Lauftreff',
            'type' => 'training',
            'visibility' => 'public',
            'status' => 'scheduled',
            'start_time' => now()->addDay(),
            'location_name' => 'Sportplatz',
            'location_city' => 'Saarbruecken',
            'notes' => 'Bitte 10 Minuten vorher da sein.',
        ]);

        Sanctum::actingAs($user);

        $this->getJson('/api/v1/events/'.$event->id)
            ->assertOk()
            ->assertJsonPath('data.my_participation_status', null)
            ->assertJsonPath('data.can_join', true);

        $this->postJson('/api/v1/events/'.$event->id.'/participation', ['status' => 'yes'])
            ->assertOk()
            ->assertJsonPath('data.my_participation_status', 'yes')
            ->assertJsonPath('data.yes_count', 1);

        $this->assertDatabaseHas('event_participants', [
            'event_id' => $event->id,
            'user_id' => $user->id,
            'status' => 'yes',
            'response_mode' => 'mobile',
        ]);

        $this->postJson('/api/v1/events/'.$event->id.'/participation', ['status' => 'yes'])
            ->assertOk()
            ->assertJsonPath('data.my_participation_status', 'yes');

        $this->postJson('/api/v1/events/'.$event->id.'/participation', ['status' => 'maybe'])
            ->assertOk()
            ->assertJsonPath('data.my_participation_status', 'maybe')
            ->assertJsonPath('data.maybe_count', 1);

        $this->deleteJson('/api/v1/events/'.$event->id.'/participation')
            ->assertOk()
            ->assertJsonPath('data.my_participation_status', null);

        $this->assertDatabaseMissing('event_participants', [
            'event_id' => $event->id,
            'user_id' => $user->id,
        ]);

        $notifications = Notification::query()
            ->where('user_id', $owner->id)
            ->where('type', 'event.participation_response')
            ->orderBy('id')
            ->get();

        $this->assertCount(3, $notifications);
        $this->assertSame(
            ['yes', 'maybe', null],
            $notifications->map(fn (Notification $notification) => $notification->data['participation_status'])->all(),
        );
        $this->assertSame(
            ['responded', 'responded', 'withdrawn'],
            $notifications->map(fn (Notification $notification) => $notification->data['participation_action'])->all(),
        );
        $this->assertSame('New response to your event', $notifications->first()->data['title']);
        $this->assertSame('Mia Member responded “Going” to “Lauftreff”.', $notifications->first()->data['body']);
        $this->assertSame(
            'server.events.notifications.response_title',
            $notifications->first()->data['i18n']['title_key'],
        );

        $this->assertSame(3, MobilePushDelivery::query()
            ->whereIn('notification_id', $notifications->pluck('id'))
            ->where('channel', 'event_reminders')
            ->count());

        $delivery = MobilePushDelivery::query()
            ->where('notification_id', $notifications->first()->id)
            ->firstOrFail();

        $this->assertSame('airmius://events/'.$event->id, $delivery->payload['deep_link']);
    }

    public function test_mobile_event_capacity_blocks_new_yes_responses(): void
    {
        $owner = User::factory()->create();
        $first = User::factory()->create();
        $second = User::factory()->create();

        $event = Event::query()->create([
            'user_id' => $owner->id,
            'title' => 'Teamtraining',
            'type' => 'training',
            'visibility' => 'public',
            'status' => 'scheduled',
            'start_time' => now()->addDay(),
            'max_participants' => 1,
        ]);

        EventParticipant::query()->create([
            'event_id' => $event->id,
            'user_id' => $first->id,
            'status' => 'yes',
        ]);

        Sanctum::actingAs($second);

        $this->postJson('/api/v1/events/'.$event->id.'/participation', ['status' => 'yes'])
            ->assertStatus(422);

        $this->postJson('/api/v1/events/'.$event->id.'/participation', ['status' => 'maybe'])
            ->assertOk()
            ->assertJsonPath('data.my_participation_status', 'maybe');
    }

    public function test_mobile_event_lifecycle_supports_create_update_rsvp_participant_list_cancel_and_delete(): void
    {
        $owner = User::factory()->create();
        $participant = User::factory()->create(['name' => 'Mia Member']);

        Sanctum::actingAs($owner);

        $eventId = $this->postJson('/api/v1/events', [
            'title' => 'Open Track Session',
            'type' => 'training',
            'visibility' => 'public',
            'start_time' => now()->addDays(3)->toISOString(),
            'end_time' => now()->addDays(3)->addHour()->toISOString(),
            'location_name' => 'Stadion',
            'location_city' => 'Saarbruecken',
            'max_participants' => 20,
            'event_timezone' => 'Europe/Berlin',
        ])
            ->assertOk()
            ->assertJsonPath('data.title', 'Open Track Session')
            ->assertJsonPath('data.status', 'scheduled')
            ->assertJsonPath('data.event_timezone', 'Europe/Berlin')
            ->assertJsonPath('data.can_update', true)
            ->json('data.id');

        $this->putJson('/api/v1/events/'.$eventId, [
            'title' => 'Open Track Session Updated',
            'location_city' => 'Berlin',
            'max_participants' => 25,
        ])
            ->assertOk()
            ->assertJsonPath('data.title', 'Open Track Session Updated')
            ->assertJsonPath('data.location_city', 'Berlin')
            ->assertJsonPath('data.max_participants', 25);

        Sanctum::actingAs($participant);

        $this->postJson('/api/v1/events/'.$eventId.'/participation', [
            'status' => 'yes',
        ])
            ->assertOk()
            ->assertJsonPath('data.my_participation_status', 'yes')
            ->assertJsonPath('data.yes_count', 1);

        Sanctum::actingAs($owner);

        $this->getJson('/api/v1/events/'.$eventId)
            ->assertOk()
            ->assertJsonPath('data.participants.0.name', 'Mia Member')
            ->assertJsonPath('data.participants.0.pivot.status', 'yes');

        $this->postJson('/api/v1/events/'.$eventId.'/cancel', [
            'reason' => 'Platz gesperrt.',
        ])
            ->assertOk()
            ->assertJsonPath('data.status', 'cancelled')
            ->assertJsonPath('data.cancellation_reason', 'Platz gesperrt.')
            ->assertJsonPath('data.can_delete', true);

        $this->deleteJson('/api/v1/events/'.$eventId)
            ->assertOk()
            ->assertJsonPath('data.deleted', true)
            ->assertJsonPath('data.id', $eventId);

        $this->assertDatabaseMissing('events', [
            'id' => $eventId,
        ]);
    }

    public function test_publishing_a_club_event_notifies_all_active_club_members(): void
    {
        $owner = User::factory()->create();
        $activeMember = User::factory()->create([
            'language' => 'en',
            'notification_channels' => ['push' => true, 'club' => true],
            'notification_quiet_time' => 'none',
        ]);
        $pausedMember = User::factory()->create();
        $club = Club::query()->create([
            'owner_id' => $owner->id,
            'name' => 'Airmius Athletics',
        ]);
        $club->users()->attach([
            $activeMember->id => ['role' => 'member', 'membership_status' => 'active'],
            $pausedMember->id => ['role' => 'member', 'membership_status' => 'paused'],
        ]);

        MobileDeviceToken::query()->create([
            'user_id' => $activeMember->id,
            'device_id' => 'club-member-android',
            'platform' => 'android',
            'provider' => 'fcm',
            'token' => 'club-member-fcm-token',
            'token_hash' => hash('sha256', 'club-member-fcm-token'),
            'channels' => ['event_reminders'],
            'permissions' => ['notifications' => true],
            'timezone' => 'Europe/Berlin',
        ]);

        Sanctum::actingAs($owner);

        $eventId = $this->postJson('/api/v1/events', [
            'club_id' => $club->id,
            'title' => 'Club summer festival',
            'type' => 'meeting',
            'visibility' => 'organization',
            'start_time' => now()->addWeek()->setTime(18, 0)->toISOString(),
            'event_timezone' => 'Europe/Berlin',
        ])->assertOk()->json('data.id');

        $notification = Notification::query()
            ->where('user_id', $activeMember->id)
            ->where('type', 'event.published')
            ->firstOrFail();

        $this->assertSame('en', $notification->data['locale']);
        $this->assertSame('New club event', $notification->data['title']);
        $this->assertSame($eventId, $notification->data['event_id']);
        $this->assertSame('airmius://events/'.$eventId, $notification->data['mobile_url']);
        $this->assertSame('airmius://events/'.$eventId, $notification->data['deep_link']);
        $this->assertSame(
            'server.events.notifications.club_published_title',
            $notification->data['i18n']['title_key'],
        );
        $this->assertDatabaseMissing('notifications', [
            'user_id' => $owner->id,
            'type' => 'event.published',
        ]);
        $this->assertDatabaseMissing('notifications', [
            'user_id' => $pausedMember->id,
            'type' => 'event.published',
        ]);

        $delivery = MobilePushDelivery::query()
            ->where('notification_id', $notification->id)
            ->firstOrFail();

        $this->assertSame('event_reminders', $delivery->channel);
        $this->assertSame('airmius://events/'.$eventId, $delivery->payload['deep_link']);
    }

    public function test_publishing_a_team_event_notifies_only_members_of_that_team(): void
    {
        $owner = User::factory()->create();
        $teamMember = User::factory()->create();
        $clubOnlyMember = User::factory()->create();
        $club = Club::query()->create([
            'owner_id' => $owner->id,
            'name' => 'Team Scope Club',
        ]);
        $club->users()->attach([
            $teamMember->id => ['role' => 'member', 'membership_status' => 'active'],
            $clubOnlyMember->id => ['role' => 'member', 'membership_status' => 'active'],
        ]);
        $team = Team::factory()->create([
            'club_id' => $club->id,
            'name' => 'U18 Performance',
        ]);
        $team->users()->attach([
            $owner->id => ['role' => TeamRoles::COACH],
            $teamMember->id => ['role' => TeamRoles::PLAYER],
        ]);

        Sanctum::actingAs($owner);

        $eventId = $this->postJson('/api/v1/events', [
            'team_id' => $team->id,
            'title' => 'U18 strength training',
            'type' => 'training',
            'visibility' => 'private',
            'start_time' => now()->addDays(3)->setTime(17, 30)->toISOString(),
            'event_timezone' => 'Europe/Berlin',
        ])->assertOk()->json('data.id');

        $notification = Notification::query()
            ->where('user_id', $teamMember->id)
            ->where('type', 'event.published')
            ->firstOrFail();

        $this->assertSame($eventId, $notification->data['event_id']);
        $this->assertSame(
            'server.events.notifications.team_published_title',
            $notification->data['i18n']['title_key'],
        );
        $this->assertDatabaseMissing('notifications', [
            'user_id' => $clubOnlyMember->id,
            'type' => 'event.published',
        ]);
        $this->assertDatabaseMissing('notifications', [
            'user_id' => $owner->id,
            'type' => 'event.published',
        ]);
    }

    public function test_free_mobile_event_accounts_cannot_bypass_recurring_event_entitlement(): void
    {
        $user = User::factory()->create();

        Sanctum::actingAs($user);

        $this->getJson('/api/v1/events')
            ->assertOk()
            ->assertJsonPath('event_creation.allows_recurring', false);

        $this->postJson('/api/v1/events', [
            'title' => 'Unberechtigte Serie',
            'type' => 'training',
            'visibility' => 'public',
            'start_time' => now()->addDays(3)->toISOString(),
            'recurring' => 'weekly',
            'recurrence_days' => [1],
            'recurrence_ends_at' => now()->addWeeks(4)->toISOString(),
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('recurring');

        $this->assertDatabaseMissing('events', [
            'title' => 'Unberechtigte Serie',
        ]);
    }

    public function test_mobile_event_attendance_can_be_recorded_by_team_staff(): void
    {
        $owner = User::factory()->create();
        $coach = User::factory()->create();
        $athlete = User::factory()->create(['name' => 'Mira Runner']);
        $outsider = User::factory()->create();
        $club = Club::query()->create([
            'owner_id' => $owner->id,
            'name' => 'Airmius Club',
        ]);
        $team = Team::factory()->create([
            'club_id' => $club->id,
            'name' => 'U18 Performance',
        ]);

        $team->users()->attach($coach->id, ['role' => TeamRoles::COACH]);
        $team->users()->attach($athlete->id, ['role' => TeamRoles::PLAYER]);

        $event = Event::query()->create([
            'club_id' => $club->id,
            'team_id' => $team->id,
            'user_id' => $owner->id,
            'title' => 'Athletiktraining',
            'type' => 'training',
            'visibility' => 'private',
            'status' => 'scheduled',
            'start_time' => now()->addDay(),
        ]);

        Sanctum::actingAs($coach);

        $this->getJson('/api/v1/events/'.$event->id.'/attendance')
            ->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonFragment([
                'name' => 'Mira Runner',
                'status' => null,
            ])
            ->assertJsonPath('meta.event_id', $event->id);

        $this->putJson('/api/v1/events/'.$event->id.'/attendance', [
            'attendance' => [
                ['user_id' => $athlete->id, 'status' => 'yes'],
                ['user_id' => $coach->id, 'status' => 'late', 'response_reason' => 'Kommt nach.'],
            ],
        ])
            ->assertOk()
            ->assertJsonPath('data.yes_count', 1)
            ->assertJsonPath('data.late_count', 1)
            ->assertJsonPath('data.can_manage_attendance', true);

        $this->assertDatabaseHas('event_participants', [
            'event_id' => $event->id,
            'user_id' => $athlete->id,
            'status' => 'yes',
            'response_mode' => 'trainer',
        ]);
        $this->assertDatabaseHas('event_participants', [
            'event_id' => $event->id,
            'user_id' => $coach->id,
            'status' => 'late',
            'response_reason' => 'Kommt nach.',
            'response_mode' => 'trainer',
        ]);

        $this->putJson('/api/v1/events/'.$event->id.'/attendance', [
            'attendance' => [
                ['user_id' => $outsider->id, 'status' => 'yes'],
            ],
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('attendance');

        Sanctum::actingAs($outsider);
        $this->getJson('/api/v1/events/'.$event->id.'/attendance')
            ->assertNotFound();
    }

    public function test_mobile_event_comments_are_visible_only_to_event_members_and_notify_recipients(): void
    {
        $owner = User::factory()->create(['name' => 'Coach Owner']);
        $participant = User::factory()->create(['name' => 'Mira Member']);
        $outsider = User::factory()->create();
        $club = Club::query()->create([
            'owner_id' => $owner->id,
            'name' => 'Comment Club',
        ]);
        $team = Team::factory()->create([
            'club_id' => $club->id,
            'name' => 'Comment Team',
        ]);
        $team->users()->attach($participant->id, ['role' => TeamRoles::PLAYER]);

        $event = Event::query()->create([
            'club_id' => $club->id,
            'team_id' => $team->id,
            'user_id' => $owner->id,
            'title' => 'Private Team Event',
            'type' => 'training',
            'visibility' => 'private',
            'status' => 'scheduled',
            'start_time' => now()->addDay(),
        ]);

        Sanctum::actingAs($participant);

        $this->postJson('/api/v1/events/'.$event->id.'/comments', [
            'content' => 'Ich bringe die Leibchen mit.',
        ])
            ->assertCreated()
            ->assertJsonPath('data.content', 'Ich bringe die Leibchen mit.')
            ->assertJsonPath('data.mine', true)
            ->assertJsonPath('data.user.name', 'Mira Member')
            ->assertJsonMissingPath('data.user.email');

        $this->assertDatabaseHas('event_comments', [
            'event_id' => $event->id,
            'user_id' => $participant->id,
            'content' => 'Ich bringe die Leibchen mit.',
        ]);
        $this->assertDatabaseHas('notifications', [
            'user_id' => $owner->id,
            'type' => 'event.comment',
        ]);

        Sanctum::actingAs($owner);

        $this->getJson('/api/v1/events/'.$event->id.'/comments')
            ->assertOk()
            ->assertJsonPath('data.0.content', 'Ich bringe die Leibchen mit.')
            ->assertJsonPath('data.0.mine', false)
            ->assertJsonPath('meta.current_page', 1);

        Sanctum::actingAs($outsider);

        $this->getJson('/api/v1/events/'.$event->id.'/comments')
            ->assertNotFound();
        $this->postJson('/api/v1/events/'.$event->id.'/comments', [
            'content' => 'Nicht erlaubt',
        ])->assertNotFound();
    }

    public function test_mobile_event_decisions_can_be_created_listed_and_voted_with_event_visibility(): void
    {
        $owner = User::factory()->create();
        $member = User::factory()->create();
        $outsider = User::factory()->create();
        $club = Club::query()->create([
            'owner_id' => $owner->id,
            'name' => 'Poll Club',
        ]);
        $team = Team::factory()->create([
            'club_id' => $club->id,
            'name' => 'Poll Team',
        ]);
        $team->users()->attach($member->id, ['role' => TeamRoles::PLAYER]);

        $event = Event::query()->create([
            'club_id' => $club->id,
            'team_id' => $team->id,
            'user_id' => $owner->id,
            'title' => 'Private Poll Event',
            'type' => 'training',
            'visibility' => 'private',
            'status' => 'scheduled',
            'start_time' => now()->addDay(),
        ]);

        Sanctum::actingAs($owner);

        $decisionId = $this->postJson('/api/v1/events/'.$event->id.'/decisions', [
            'question' => 'Welche Farbe sollen die Leibchen haben?',
            'description' => 'Bitte bis heute Abend abstimmen.',
            'options' => ['Blau', 'Gelb'],
        ])
            ->assertCreated()
            ->assertJsonPath('data.question', 'Welche Farbe sollen die Leibchen haben?')
            ->assertJsonCount(2, 'data.options')
            ->json('data.id');

        Sanctum::actingAs($member);

        $this->getJson('/api/v1/events/'.$event->id.'/decisions')
            ->assertOk()
            ->assertJsonPath('data.0.id', $decisionId)
            ->assertJsonPath('data.0.my_option_id', null)
            ->assertJsonPath('data.0.options.0.votes', 0);

        $optionId = $this->getJson('/api/v1/events/'.$event->id.'/decisions')
            ->json('data.0.options.0.id');

        $this->postJson('/api/v1/events/'.$event->id.'/decisions/'.$decisionId.'/vote', [
            'option_id' => $optionId,
        ])
            ->assertOk()
            ->assertJsonPath('data.decision_id', $decisionId)
            ->assertJsonPath('data.option_id', $optionId);

        $this->getJson('/api/v1/events/'.$event->id.'/decisions')
            ->assertJsonPath('data.0.my_option_id', $optionId)
            ->assertJsonPath('data.0.options.0.votes', 1);

        Sanctum::actingAs($owner);

        $this->postJson('/api/v1/events/'.$event->id.'/decisions/'.$decisionId.'/close')
            ->assertOk()
            ->assertJsonPath('data.status', 'closed');

        Sanctum::actingAs($member);

        $this->postJson('/api/v1/events/'.$event->id.'/decisions/'.$decisionId.'/vote', [
            'option_id' => $optionId,
        ])->assertUnprocessable();

        $this->postJson('/api/v1/events/'.$event->id.'/decisions', [
            'question' => 'Nicht erlaubt',
            'options' => ['Ja', 'Nein'],
        ])->assertForbidden();

        Sanctum::actingAs($outsider);

        $this->getJson('/api/v1/events/'.$event->id.'/decisions')->assertForbidden();
        $this->postJson('/api/v1/events/'.$event->id.'/decisions/'.$decisionId.'/vote', [
            'option_id' => $optionId,
        ])->assertForbidden();
    }
}
