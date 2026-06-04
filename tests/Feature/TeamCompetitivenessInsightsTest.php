<?php

namespace Tests\Feature;

use App\Models\Club;
use App\Models\Event;
use App\Models\Team;
use App\Models\TeamFee;
use App\Models\User;
use App\Support\TeamRoles;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class TeamCompetitivenessInsightsTest extends TestCase
{
    use RefreshDatabase;

    public function test_team_insights_expose_next_event_attendance_reliability_and_actions(): void
    {
        $owner = User::factory()->create();
        $coach = User::factory()->create(['name' => 'Coach Ada']);
        $yes = User::factory()->create(['name' => 'Yes Player']);
        $missing = User::factory()->create(['name' => 'Missing Player']);
        $club = Club::query()->create([
            'owner_id' => $owner->id,
            'name' => 'Airmius Team Club',
        ]);
        $team = Team::factory()->create([
            'club_id' => $club->id,
            'name' => 'Performance Team',
        ]);

        $team->users()->attach($coach->id, ['role' => TeamRoles::COACH]);
        $team->users()->attach($yes->id, ['role' => TeamRoles::PLAYER]);
        $team->users()->attach($missing->id, ['role' => TeamRoles::PLAYER]);

        $nextEvent = Event::query()->create([
            'team_id' => $team->id,
            'club_id' => $club->id,
            'title' => 'Saturday training',
            'type' => 'training',
            'visibility' => 'organization',
            'status' => 'scheduled',
            'start_time' => now()->addDays(2),
            'participant_response_required' => true,
            'participant_response_deadline_at' => now()->addDay(),
        ]);
        $nextEvent->participants()->attach($yes->id, [
            'status' => 'yes',
            'response_mode' => 'self',
            'responded_at' => now(),
        ]);

        Event::query()->create([
            'team_id' => $team->id,
            'club_id' => $club->id,
            'title' => 'Last match',
            'type' => 'match',
            'visibility' => 'organization',
            'status' => 'scheduled',
            'start_time' => now()->subDays(5),
        ])->participants()->attach($yes->id, [
            'status' => 'yes',
            'response_mode' => 'self',
            'responded_at' => now()->subDays(6),
        ]);

        TeamFee::query()->create([
            'team_id' => $team->id,
            'user_id' => $missing->id,
            'collector_id' => $coach->id,
            'category' => 'equipment',
            'amount' => 12.5,
            'currency' => 'EUR',
            'status' => 'open',
        ]);

        Sanctum::actingAs($coach);

        $this->getJson(route('api.v1.teams.competitiveness.insights', $team))
            ->assertOk()
            ->assertJsonPath('data.events.next.id', $nextEvent->id)
            ->assertJsonPath('data.events.next.participation.team_size', 3)
            ->assertJsonPath('data.events.next.participation.responded', 1)
            ->assertJsonPath('data.events.next.participation.missing', 2)
            ->assertJsonPath('data.events.next.participation.attendance_rate', 33.33)
            ->assertJsonPath('data.participation.missing_responses.0.name', 'Coach Ada')
            ->assertJsonPath('data.participation.missing_responses.1.name', 'Missing Player')
            ->assertJsonPath('data.participation.reliability.0.name', 'Coach Ada')
            ->assertJsonPath('data.participation.playbook.version', '2026-06-03')
            ->assertJsonPath('data.participation.playbook.risk', 'high')
            ->assertJsonPath('data.participation.playbook.deadline_state', 'upcoming')
            ->assertJsonPath('data.participation.playbook.missing_count', 2)
            ->assertJsonPath('data.participation.playbook.reminders.0.key', 'push_missing')
            ->assertJsonPath('data.participation.playbook.reminders.0.channel', 'push')
            ->assertJsonPath('data.participation.playbook.low_reliability_members.0.name', 'Coach Ada')
            ->assertJsonPath('data.participation.playbook.coach_briefing.next_actions.0.key', 'remind_missing_responses')
            ->assertJsonPath('data.team_organizer.version', '2026-06-03')
            ->assertJsonPath('data.team_organizer.next_event_id', $nextEvent->id)
            ->assertJsonPath('data.team_organizer.tasks.0.key', 'remind_missing_responses')
            ->assertJsonPath('data.team_organizer.tasks.0.assignee_role', 'coach')
            ->assertJsonPath('data.team_organizer.materials.status', 'check_recommended')
            ->assertJsonPath('data.team_organizer.materials.suggested_lists.1.key', 'first_aid')
            ->assertJsonPath('data.team_organizer.polls.recommended', true)
            ->assertJsonPath('data.team_organizer.polls.templates.1.key', 'transport_options')
            ->assertJsonPath('data.team_organizer.season_plan.planning_state', 'needs_more_events')
            ->assertJsonPath('data.team_organizer.guardian_mode.channels.2', 'fees')
            ->assertJsonPath('data.fees.counts.open', 1)
            ->assertJsonPath('data.team_actions.0.key', 'remind_missing_responses')
            ->assertJsonPath('data.team_actions.1.key', 'check_availability')
            ->assertJsonPath('data.team_actions.2.key', 'review_open_fees');
    }
}
