<?php

namespace Tests\Feature;

use App\Models\Club;
use App\Models\Event;
use App\Models\Team;
use App\Models\User;
use App\Support\TeamRoles;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class EventParticipationWebFlowTest extends TestCase
{
    use RefreshDatabase;

    public function test_member_must_give_reason_for_decline_and_sees_saved_response_on_event_page(): void
    {
        $owner = User::factory()->create();
        $member = User::factory()->create(['name' => 'Mia Member']);
        $club = Club::query()->create([
            'owner_id' => $owner->id,
            'name' => 'Airmius Verein',
        ]);
        $team = Team::factory()->create([
            'club_id' => $club->id,
            'name' => 'Match Team',
        ]);
        $team->users()->attach($member->id, ['role' => TeamRoles::PLAYER]);
        $event = Event::query()->create([
            'club_id' => $club->id,
            'team_id' => $team->id,
            'user_id' => $owner->id,
            'title' => 'Training am Abend',
            'type' => 'training',
            'visibility' => 'private',
            'status' => 'scheduled',
            'start_time' => now()->addDays(2),
            'participant_response_required' => true,
            'participant_response_deadline_at' => now()->addDay(),
        ]);

        $this->actingAs($member)
            ->post(route('auth.events.join', $event), ['status' => 'no'])
            ->assertSessionHasErrors('response_reason');

        $this->actingAs($member)
            ->post(route('auth.events.join', $event), [
                'status' => 'no',
                'response_reason' => 'Knie braucht Pause',
                'response_mode' => 'self',
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('event_participants', [
            'event_id' => $event->id,
            'user_id' => $member->id,
            'status' => 'no',
            'response_reason' => 'Knie braucht Pause',
            'response_mode' => 'self',
        ]);

        $this->actingAs($member)
            ->get(route('auth.events.show', $event))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Auth/Dashboard/Events/Show')
                ->where('currentParticipantStatus', 'no')
                ->where('currentParticipantResponse.reason', 'Knie braucht Pause')
                ->where('participationPolicy.response_required', true)
                ->where('participationPolicy.deadline_expired', false)
            );
    }

    public function test_web_event_flow_creates_event_records_rsvp_shows_participants_and_cancels_event(): void
    {
        $owner = User::factory()->create();
        $member = User::factory()->create(['name' => 'Mia Member']);

        $this->actingAs($owner)
            ->post(route('auth.events.store'), [
                'title' => 'Offenes Lauftraining',
                'type' => 'training',
                'visibility' => 'public',
                'start_time' => now()->addDays(2)->toDateTimeString(),
                'end_time' => now()->addDays(2)->addHour()->toDateTimeString(),
                'location_name' => 'Stadion',
                'location_city' => 'Saarbruecken',
                'max_participants' => 12,
                'event_timezone' => 'Europe/Berlin',
            ])
            ->assertRedirect();

        $event = Event::query()->where('title', 'Offenes Lauftraining')->firstOrFail();

        $this->actingAs($member)
            ->post(route('auth.events.join', $event), [
                'status' => 'no',
                'response_mode' => 'self',
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('event_participants', [
            'event_id' => $event->id,
            'user_id' => $member->id,
            'status' => 'no',
            'response_mode' => 'self',
        ]);

        $this->actingAs($owner)
            ->get(route('auth.events.show', $event))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Auth/Dashboard/Events/Show')
                ->where('event.title', 'Offenes Lauftraining')
                ->where('event.participants.0.name', 'Mia Member')
                ->where('event.participants.0.pivot.status', 'no')
            );

        $this->actingAs($owner)
            ->post(route('auth.events.cancel', $event), [
                'reason' => 'Platz gesperrt.',
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('events', [
            'id' => $event->id,
            'status' => 'cancelled',
            'cancellation_reason' => 'Platz gesperrt.',
        ]);
    }

    public function test_team_staff_can_record_training_attendance_for_team_members(): void
    {
        $owner = User::factory()->create();
        $coach = User::factory()->create();
        $athlete = User::factory()->create();
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

        $this->actingAs($coach)
            ->put(route('auth.events.attendance.update', $event), [
                'attendance' => [
                    ['user_id' => $athlete->id, 'status' => 'yes'],
                    ['user_id' => $coach->id, 'status' => 'late', 'response_reason' => 'Kommt nach.'],
                ],
            ])
            ->assertRedirect()
            ->assertSessionHas('success', 'Trainingsanwesenheit gespeichert.');

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

        $this->actingAs($athlete)
            ->put(route('auth.events.attendance.update', $event), [
                'attendance' => [
                    ['user_id' => $athlete->id, 'status' => 'no'],
                ],
            ])
            ->assertRedirect();

        $this->assertDatabaseMissing('event_participants', [
            'event_id' => $event->id,
            'user_id' => $athlete->id,
            'status' => 'no',
        ]);
    }
}
