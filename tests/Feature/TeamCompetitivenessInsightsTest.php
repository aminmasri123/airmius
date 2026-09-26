<?php

namespace Tests\Feature;

use App\Models\Club;
use App\Models\ClubRoleAssignment;
use App\Models\ClubRoleDefinition;
use App\Models\Event;
use App\Models\Team;
use App\Models\TeamFee;
use App\Models\User;
use App\Support\ClubPermissions;
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

        $this->withHeader('X-App-Locale', 'ar')
            ->getJson(route('api.v1.teams.competitiveness.insights', $team))
            ->assertOk()
            ->assertHeader('Content-Language', 'ar')
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
            ->assertJsonPath('data.participation.playbook.coach_briefing.headline', __('team_competitiveness.briefing.high', locale: 'ar'))
            ->assertJsonPath('data.participation.playbook.coach_briefing.next_actions.0.key', 'remind_missing_responses')
            ->assertJsonPath('data.team_organizer.version', '2026-06-03')
            ->assertJsonPath('data.team_organizer.next_event_id', $nextEvent->id)
            ->assertJsonPath('data.team_organizer.tasks.0.key', 'remind_missing_responses')
            ->assertJsonPath('data.team_organizer.tasks.0.title', __('team_competitiveness.tasks.remind_missing_responses', locale: 'ar'))
            ->assertJsonPath('data.team_organizer.tasks.0.assignee_role', 'coach')
            ->assertJsonPath('data.team_organizer.materials.status', 'check_recommended')
            ->assertJsonPath('data.team_organizer.materials.suggested_lists.1.key', 'first_aid')
            ->assertJsonPath('data.team_organizer.materials.suggested_lists.1.label', __('team_competitiveness.materials.first_aid', locale: 'ar'))
            ->assertJsonPath('data.team_organizer.polls.recommended', true)
            ->assertJsonPath('data.team_organizer.polls.templates.1.key', 'transport_options')
            ->assertJsonPath('data.team_organizer.polls.templates.1.label', __('team_competitiveness.polls.transport_options', locale: 'ar'))
            ->assertJsonPath('data.team_organizer.season_plan.planning_state', 'needs_more_events')
            ->assertJsonPath('data.team_organizer.guardian_mode.channels.2', 'fees')
            ->assertJsonPath('data.fees.counts.open', 1)
            ->assertJsonPath('data.team_actions.0.key', 'remind_missing_responses')
            ->assertJsonPath('data.team_actions.1.key', 'check_availability')
            ->assertJsonPath('data.team_actions.2.key', 'review_open_fees');
    }

    public function test_team_insights_require_scoped_trainer_access_and_respect_explicit_denial(): void
    {
        $owner = User::factory()->create();
        $viewer = User::factory()->create();
        $ordinaryMember = User::factory()->create();
        $club = Club::factory()->create(['owner_id' => $owner->id]);
        $assignedTeam = Team::factory()->create(['club_id' => $club->id]);
        $otherTeam = Team::factory()->create(['club_id' => $club->id]);
        foreach ([$viewer, $ordinaryMember] as $member) {
            $club->users()->attach($member->id, [
                'role' => 'member',
                'roles' => ['member'],
                'membership_status' => 'active',
            ]);
        }
        $assignedTeam->users()->attach($viewer->id, ['role' => TeamRoles::PLAYER]);
        $role = ClubRoleDefinition::query()->create([
            'club_id' => $club->id,
            'key' => 'team_insights_viewer',
            'name' => 'Team-Auswertung',
            'permissions' => [ClubPermissions::TRAINER_COCKPIT_VIEW],
            'is_active' => true,
        ]);
        ClubRoleAssignment::query()->create([
            'club_id' => $club->id,
            'club_role_definition_id' => $role->id,
            'user_id' => $viewer->id,
            'scope_type' => 'team',
            'scope_id' => $assignedTeam->id,
            'scope_key' => 'team:'.$assignedTeam->id,
            'assigned_by' => $owner->id,
        ]);

        Sanctum::actingAs($ordinaryMember);
        $this->getJson(route('api.v1.teams.competitiveness.insights', $assignedTeam))
            ->assertForbidden();

        Sanctum::actingAs($viewer);
        $this->getJson(route('api.v1.teams.competitiveness.insights', $assignedTeam))
            ->assertOk();
        $this->getJson(route('api.v1.teams.competitiveness.insights', $otherTeam))
            ->assertForbidden();

        $assignedTeam->users()->updateExistingPivot($viewer->id, ['role' => TeamRoles::COACH]);
        $club->users()->updateExistingPivot($viewer->id, [
            'permission_overrides' => [ClubPermissions::TRAINER_COCKPIT_VIEW => false],
        ]);

        $this->getJson(route('api.v1.teams.competitiveness.insights', $assignedTeam))
            ->assertForbidden();
    }
}
