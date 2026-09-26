<?php

namespace Tests\Feature;

use App\Models\Club;
use App\Models\ClubRoleAssignment;
use App\Models\ClubRoleDefinition;
use App\Models\Team;
use App\Models\TrainingLog;
use App\Models\TrainingPlan;
use App\Models\TrainingPlanItem;
use App\Models\User;
use App\Support\ClubPermissions;
use App\Support\TeamRoles;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class MobileTrainerCockpitApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_coach_receives_only_managed_team_control_data(): void
    {
        [$coach, $athlete, $team] = $this->coachTeam();
        $foreignOwner = User::factory()->create();
        $foreignClub = Club::query()->create([
            'owner_id' => $foreignOwner->id,
            'name' => 'Foreign Club',
        ]);
        $foreignTeam = Team::factory()->create([
            'club_id' => $foreignClub->id,
            'name' => 'Foreign Team',
        ]);
        $foreignTeam->users()->attach(User::factory()->create(), ['role' => TeamRoles::PLAYER]);

        $plan = TrainingPlan::query()->create([
            'created_by' => $coach->id,
            'team_id' => $team->id,
            'title' => 'Safe race week',
            'status' => 'active',
        ]);
        TrainingPlanItem::query()->create([
            'training_plan_id' => $plan->id,
            'title' => 'Recovery run',
            'scheduled_at' => now()->subDay(),
        ]);
        TrainingLog::query()->create([
            'user_id' => $athlete->id,
            'created_by' => $athlete->id,
            'trainer_id' => $coach->id,
            'team_id' => $team->id,
            'training_plan_id' => $plan->id,
            'title' => 'Hard intervals',
            'status' => 'completed',
            'performed_at' => now()->subDay(),
            'duration_minutes' => 55,
            'distance_meters' => 9000,
            'intensity' => 'high',
            'metrics' => ['wellness' => ['rpe' => 9, 'energy' => 4, 'pain' => 3]],
        ]);

        Sanctum::actingAs($coach);

        $this->getJson('/api/v1/trainer-cockpit')
            ->assertOk()
            ->assertJsonCount(1, 'data.teams')
            ->assertJsonPath('data.teams.0.id', $team->id)
            ->assertJsonPath('data.summary.athletes', 2)
            ->assertJsonPath('data.summary.risk_athletes', 1)
            ->assertJsonPath('data.coachWeekly.risk_athletes.0.name', $athlete->name)
            ->assertJsonMissing(['name' => 'Foreign Team']);
    }

    public function test_regular_user_cannot_open_mobile_trainer_cockpit(): void
    {
        Sanctum::actingAs(User::factory()->create());

        $this->getJson('/api/v1/trainer-cockpit')->assertForbidden();
    }

    public function test_scoped_cockpit_role_sees_only_assigned_team_and_explicit_denial_closes_legacy_access(): void
    {
        $owner = User::factory()->create();
        $specialist = User::factory()->create();
        $deniedManager = User::factory()->create();
        $deniedDirectCoach = User::factory()->create();
        $club = Club::factory()->create(['owner_id' => $owner->id]);
        $assignedTeam = Team::factory()->create(['club_id' => $club->id, 'name' => 'Assigned Team']);
        $hiddenTeam = Team::factory()->create(['club_id' => $club->id, 'name' => 'Hidden Team']);
        $assignedAthlete = User::factory()->create();
        $hiddenAthlete = User::factory()->create();
        $assignedTeam->users()->attach($assignedAthlete->id, ['role' => TeamRoles::PLAYER]);
        $hiddenTeam->users()->attach($hiddenAthlete->id, ['role' => TeamRoles::PLAYER]);
        $club->users()->attach($specialist->id, ['role' => 'member', 'membership_status' => 'active']);
        $club->users()->attach($deniedManager->id, [
            'role' => 'manager',
            'membership_status' => 'active',
            'permission_overrides' => [ClubPermissions::TRAINER_COCKPIT_VIEW => false],
        ]);
        $club->users()->attach($deniedDirectCoach->id, [
            'role' => 'member',
            'membership_status' => 'active',
            'permission_overrides' => [ClubPermissions::TRAINER_COCKPIT_VIEW => false],
        ]);
        $assignedTeam->users()->attach($deniedDirectCoach->id, ['role' => TeamRoles::COACH]);
        $role = ClubRoleDefinition::query()->create([
            'club_id' => $club->id,
            'key' => 'team_cockpit_viewer',
            'name' => 'Team cockpit viewer',
            'permissions' => [ClubPermissions::TRAINER_COCKPIT_VIEW],
            'is_active' => true,
        ]);
        ClubRoleAssignment::query()->create([
            'club_id' => $club->id,
            'club_role_definition_id' => $role->id,
            'user_id' => $specialist->id,
            'scope_type' => 'team',
            'scope_id' => $assignedTeam->id,
            'scope_key' => 'team:'.$assignedTeam->id,
            'assigned_by' => $owner->id,
        ]);
        TrainingLog::query()->create([
            'user_id' => $assignedAthlete->id,
            'created_by' => $assignedAthlete->id,
            'team_id' => $assignedTeam->id,
            'title' => 'Assigned Team Log',
            'status' => 'completed',
            'performed_at' => now(),
            'metrics' => ['privacy_scope' => 'trainer'],
        ]);
        TrainingLog::query()->create([
            'user_id' => $hiddenAthlete->id,
            'created_by' => $hiddenAthlete->id,
            'team_id' => $hiddenTeam->id,
            'title' => 'Hidden Team Log',
            'status' => 'completed',
            'performed_at' => now(),
            'metrics' => ['privacy_scope' => 'trainer'],
        ]);

        Sanctum::actingAs($specialist);
        $this->getJson('/api/v1/trainer-cockpit')
            ->assertOk()
            ->assertJsonCount(1, 'data.teams')
            ->assertJsonPath('data.teams.0.id', $assignedTeam->id)
            ->assertJsonMissing(['name' => 'Hidden Team'])
            ->assertSee('Assigned Team Log')
            ->assertDontSee('Hidden Team Log')
            ->assertJsonCount(1, 'data.recentLogs');

        Sanctum::actingAs($deniedManager);
        $this->getJson('/api/v1/trainer-cockpit')->assertForbidden();

        Sanctum::actingAs($deniedDirectCoach);
        $this->getJson('/api/v1/trainer-cockpit')->assertForbidden();
        $this->getJson('/api/v1/training/logs/'.TrainingLog::query()->where('title', 'Assigned Team Log')->value('id'))
            ->assertNotFound();
    }

    public function test_teams_without_current_training_data_do_not_report_stable_readiness(): void
    {
        [$coach, $athlete, $team] = $this->coachTeam();
        Sanctum::actingAs($coach);

        $this->getJson('/api/v1/trainer-cockpit')
            ->assertOk()
            ->assertJsonPath('data.coachWeekly.current_week.session_count', 0)
            ->assertJsonPath('data.coachWeekly.risk_level', 'empty')
            ->assertJsonPath('data.coachWeekly.readiness_score', 0)
            ->assertJsonPath('data.coachWeekly.team_cards.0.risk_level', 'empty')
            ->assertJsonPath('data.coachWeekly.team_cards.0.readiness_score', 0);
    }

    public function test_trainer_cockpit_route_works_with_trailing_slash(): void
    {
        [$coach, $athlete, $team] = $this->coachTeam();

        Sanctum::actingAs($coach);

        $this->getJson('/api/v1/trainer-cockpit/')
            ->assertOk()
            ->assertJsonPath('data.summary.teams', 1)
            ->assertJsonPath('data.teams.0.name', $team->name);
    }

    public function test_weekly_control_excludes_future_logs_from_stats_and_risk(): void
    {
        $this->travelTo(now()->setDate(2026, 9, 7)->setTime(12, 0));
        [$coach, $athlete, $team] = $this->coachTeam();
        foreach ([
            ['Current', now()->subDays(6)->startOfDay(), 30, 1],
            ['Previous', now()->subDays(7)->endOfDay(), 60, 1],
            ['Future', now()->addDay()->startOfDay(), 180, 8],
        ] as [$title, $date, $minutes, $pain]) {
            TrainingLog::query()->create([
                'user_id' => $athlete->id, 'created_by' => $athlete->id,
                'trainer_id' => $coach->id, 'team_id' => $team->id,
                'title' => $title, 'status' => 'completed',
                'performed_at' => $date, 'duration_minutes' => $minutes,
                'metrics' => ['wellness' => ['pain' => $pain]],
            ]);
        }
        Sanctum::actingAs($coach);
        $this->getJson('/api/v1/trainer-cockpit')->assertOk()
            ->assertJsonPath('data.coachWeekly.current_week.session_count', 1)
            ->assertJsonPath('data.coachWeekly.current_week.duration_minutes', 30)
            ->assertJsonPath('data.coachWeekly.previous_week.session_count', 1)
            ->assertJsonPath('data.coachWeekly.previous_week.duration_minutes', 60)
            ->assertJsonPath('data.coachWeekly.team_cards.0.sessions', 1)
            ->assertJsonCount(2, 'data.feedbackOpen')
            ->assertJsonCount(0, 'data.coachWeekly.risk_athletes');
    }

    public function test_private_athlete_logs_are_hidden_from_cockpit_lists_and_aggregates(): void
    {
        [$coach, $athlete, $team] = $this->coachTeam();
        $privateLog = null;
        foreach (['trainer', 'private'] as $privacy) {
            $log = TrainingLog::query()->create([
                'user_id' => $athlete->id, 'created_by' => $athlete->id,
                'trainer_id' => $coach->id, 'team_id' => $team->id,
                'title' => "QA {$privacy} log", 'status' => 'completed',
                'performed_at' => now()->subDay(),
                'duration_minutes' => $privacy === 'private' ? 180 : 30,
                'metrics' => ['privacy_scope' => $privacy, 'wellness' => ['pain' => $privacy === 'private' ? 8 : 1]],
            ]);
            if ($privacy === 'private') {
                $privateLog = $log;
            }
        }
        Sanctum::actingAs($coach);
        $this->getJson('/api/v1/trainer-cockpit')->assertOk()
            ->assertDontSee('QA private log')
            ->assertSee('QA trainer log')
            ->assertJsonCount(1, 'data.recentLogs')
            ->assertJsonCount(1, 'data.feedbackOpen')
            ->assertJsonPath('data.coachWeekly.current_week.session_count', 1)
            ->assertJsonPath('data.coachWeekly.current_week.duration_minutes', 30)
            ->assertJsonPath('data.coachWeekly.team_cards.0.sessions', 1)
            ->assertJsonCount(0, 'data.coachWeekly.risk_athletes');
        $this->postJson("/api/v1/trainer-cockpit/logs/{$privateLog->id}/feedback", [
            'body' => 'QA unauthorized feedback',
        ])->assertForbidden();
        $this->assertSame(0, $privateLog->feedbacks()->count());
    }

    public function test_coach_can_send_feedback_only_for_visible_training_log(): void
    {
        [$coach, $athlete, $team] = $this->coachTeam();
        $visibleLog = TrainingLog::query()->create([
            'user_id' => $athlete->id,
            'created_by' => $athlete->id,
            'trainer_id' => $coach->id,
            'team_id' => $team->id,
            'title' => 'Tempo run',
            'status' => 'completed',
            'performed_at' => now(),
            'metrics' => ['privacy_scope' => 'trainer'],
        ]);
        $foreignLog = TrainingLog::query()->create([
            'user_id' => User::factory()->create()->id,
            'created_by' => User::factory()->create()->id,
            'title' => 'Private session',
            'status' => 'completed',
            'performed_at' => now(),
            'metrics' => ['privacy_scope' => 'private'],
        ]);
        Sanctum::actingAs($coach);

        $this->postJson("/api/v1/trainer-cockpit/logs/{$visibleLog->id}/feedback", [
            'body' => 'Sehr kontrollierte Belastung. Morgen locker trainieren.',
        ])
            ->assertCreated()
            ->assertJsonPath('data.role', 'trainer');

        $this->postJson("/api/v1/trainer-cockpit/logs/{$foreignLog->id}/feedback", [
            'body' => 'Unbefugter Zugriff',
        ])->assertForbidden();

        $this->assertDatabaseHas('training_log_feedback', [
            'training_log_id' => $visibleLog->id,
            'user_id' => $coach->id,
        ]);
    }

    /**
     * @return array{User, User, Team}
     */
    private function coachTeam(): array
    {
        $coach = User::factory()->create(['name' => 'Mina Coach']);
        $athlete = User::factory()->create(['name' => 'Mira Runner']);
        $owner = User::factory()->create();
        $club = Club::query()->create([
            'owner_id' => $owner->id,
            'name' => 'Airmius Club',
        ]);
        $team = Team::factory()->create([
            'club_id' => $club->id,
            'name' => 'U18 Performance',
            'sport_type' => 'running',
        ]);
        $team->users()->attach($coach->id, ['role' => TeamRoles::COACH]);
        $team->users()->attach($athlete->id, ['role' => TeamRoles::PLAYER]);

        return [$coach, $athlete, $team];
    }
}
