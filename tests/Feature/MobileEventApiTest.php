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
    }
}
