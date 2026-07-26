<?php

namespace Tests\Feature;

use App\Models\Club;
use App\Models\Event;
use App\Models\EventParticipant;
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
        $owner = User::factory()->create();
        $user = User::factory()->create();

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
