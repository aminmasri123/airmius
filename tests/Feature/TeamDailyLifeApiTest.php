<?php

namespace Tests\Feature;

use App\Models\Club;
use App\Models\Event;
use App\Models\Ride;
use App\Models\Team;
use App\Models\TeamFee;
use App\Models\User;
use App\Support\TeamRoles;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class TeamDailyLifeApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_team_daily_life_bundles_attendance_carpools_tasks_cash_guardians_material_and_season(): void
    {
        $owner = User::factory()->create();
        $coach = User::factory()->create([
            'name' => 'Coach Ada',
            'birth_date' => now()->subYears(35)->toDateString(),
        ]);
        $yes = User::factory()->create([
            'name' => 'Yes Player',
            'birth_date' => now()->subYears(20)->toDateString(),
        ]);
        $missingMinor = User::factory()->create([
            'name' => 'Missing Junior',
            'birth_date' => now()->subYears(13)->toDateString(),
            'guardian_email' => 'parent@example.com',
        ]);
        $club = Club::query()->create([
            'owner_id' => $owner->id,
            'name' => 'Airmius Team Club',
        ]);
        $team = Team::factory()->create([
            'club_id' => $club->id,
            'name' => 'U15 Alltag',
            'sport_type' => 'football',
        ]);

        $team->users()->attach($coach->id, ['role' => TeamRoles::COACH]);
        $team->users()->attach($yes->id, ['role' => TeamRoles::PLAYER]);
        $team->users()->attach($missingMinor->id, ['role' => TeamRoles::PLAYER]);

        $nextEvent = Event::query()->create([
            'team_id' => $team->id,
            'club_id' => $club->id,
            'user_id' => $coach->id,
            'title' => 'Saturday match',
            'type' => 'match',
            'visibility' => 'organization',
            'status' => 'scheduled',
            'start_time' => now()->addDays(2),
            'location_name' => 'Home pitch',
            'participant_response_required' => true,
            'participant_response_deadline_at' => now()->addDay(),
        ]);
        $nextEvent->participants()->attach($yes->id, [
            'status' => 'yes',
            'response_mode' => 'self',
            'responded_at' => now(),
        ]);

        Ride::query()->create([
            'team_id' => $team->id,
            'club_id' => $club->id,
            'event_id' => $nextEvent->id,
            'driver_id' => $coach->id,
            'visibility' => 'team',
            'from' => 'Station',
            'to' => 'Home pitch',
            'departure_time' => now()->addDays(2)->subHour(),
            'seats' => 4,
            'contact_details' => 'Chat',
        ])->users()->attach($coach->id, [
            'status' => Ride::MEMBER_STATUS_ACCEPTED,
            'responded_at' => now(),
        ]);

        TeamFee::query()->create([
            'team_id' => $team->id,
            'user_id' => $missingMinor->id,
            'collector_id' => $coach->id,
            'category' => 'equipment',
            'amount' => 12.5,
            'currency' => 'EUR',
            'status' => 'open',
            'due_date' => now()->addWeek(),
        ]);

        Event::query()->create([
            'team_id' => $team->id,
            'club_id' => $club->id,
            'user_id' => $coach->id,
            'title' => 'Next training',
            'type' => 'training',
            'visibility' => 'organization',
            'status' => 'scheduled',
            'start_time' => now()->addDays(5),
        ]);

        Sanctum::actingAs($coach);

        $this->getJson('/api/v1/teams/'.$team->id.'/daily-life')
            ->assertOk()
            ->assertJsonPath('data.version', '2026-06-03.spielerplus_team_life.v1')
            ->assertJsonPath('data.team.id', $team->id)
            ->assertJsonPath('data.team.viewer_role', TeamRoles::COACH)
            ->assertJsonPath('data.today.next_event_id', $nextEvent->id)
            ->assertJsonPath('data.today.primary_action.key', 'remind_missing_responses')
            ->assertJsonPath('data.attendance.next_event.title', 'Saturday match')
            ->assertJsonPath('data.attendance.summary.team_size', 3)
            ->assertJsonPath('data.attendance.summary.responded', 1)
            ->assertJsonPath('data.attendance.summary.missing', 2)
            ->assertJsonPath('data.attendance.missing_responses.0.name', 'Coach Ada')
            ->assertJsonPath('data.carpools.count', 1)
            ->assertJsonPath('data.carpools.available_seats_total', 3)
            ->assertJsonPath('data.tasks.items.0.key', 'remind_missing_responses')
            ->assertJsonPath('data.cash_box.counts.open', 1)
            ->assertJsonPath('data.cash_box.open_items.0.member_name', 'Missing Junior')
            ->assertJsonPath('data.guardian_mode.recommended', true)
            ->assertJsonPath('data.guardian_mode.minor_members_count', 1)
            ->assertJsonPath('data.materials.suggested_lists.3.key', 'jerseys')
            ->assertJsonPath('data.season_planning.planning_state', 'needs_more_events')
            ->assertJsonPath('data.mobile_contract.write_endpoints.attendance', '/api/v1/events/{event}/competitive/participation');
    }
}
