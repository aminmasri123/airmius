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
}
