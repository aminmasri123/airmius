<?php

namespace Tests\Feature;

use App\Models\Activity;
use App\Models\Club;
use App\Models\ClubRoleAssignment;
use App\Models\ClubRoleDefinition;
use App\Models\ClubTrainingGroup;
use App\Models\Team;
use App\Models\TrainingLog;
use App\Models\TrainingPlan;
use App\Models\TrainingPlanItem;
use App\Models\User;
use App\Services\Training\TrainingFeedbackService;
use App\Support\ClubPermissions;
use App\Support\TeamRoles;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class TrainingPlanEvaluationTest extends TestCase
{
    use RefreshDatabase;

    private string $endpoint = '/api/v1/trainer-cockpit/plan-evaluation';

    private function team(User $coach): Team
    {
        $club = Club::factory()->create(['owner_id' => User::factory()->create()->id]);
        $team = Team::factory()->create(['club_id' => $club->id]);
        $team->users()->attach($coach, ['role' => TeamRoles::COACH]);

        return $team;
    }

    private function plan(User $coach, ?Team $team = null): array
    {
        $plan = TrainingPlan::create(['created_by' => $coach->id, 'team_id' => $team?->id, 'title' => 'Weekly strength', 'status' => 'active']);
        $item = $plan->items()->create(['title' => 'Monday strength', 'scheduled_at' => '2026-11-09 12:00:00', 'metrics' => ['planned_exercises' => [
            ['exercise_key' => 'pushups', 'title' => 'Liegestütze', 'sets' => [
                ['set_index' => 1, 'reps' => 20], ['set_index' => 2, 'reps' => 20], ['set_index' => 3, 'reps' => 20],
            ]],
            ['exercise_key' => 'squats', 'title' => 'Kniebeugen', 'sets' => [['set_index' => 1, 'reps' => 15]]],
        ]]]);

        return [$plan, $item];
    }

    private function log(User $athlete, TrainingPlan $plan, TrainingPlanItem $item, ?Team $team = null, array $metrics = []): TrainingLog
    {
        return TrainingLog::create(['user_id' => $athlete->id, 'created_by' => $athlete->id,
            'training_plan_id' => $plan->id, 'training_plan_item_id' => $item->id,
            'team_id' => $team?->id, 'title' => 'Training report', 'status' => 'completed',
            'performed_at' => '2026-11-09 13:00:00', 'metrics' => ['privacy_scope' => 'trainer', ...$metrics]]);
    }

    public function test_all_recipients_across_clubs_are_paginated_and_deduplicated_with_missing_reports(): void
    {
        $coach = User::factory()->create();
        $teamA = $this->team($coach);
        $teamB = $this->team($coach);
        [$plan, $item] = $this->plan($coach, $teamA);
        $plan->assignments()->create(['team_id' => $teamB->id, 'permission' => 'read']);
        $athletes = User::factory()->count(25)->create();
        foreach ($athletes as $i => $athlete) {
            ($i < 12 ? $teamA : $teamB)->users()->attach($athlete, ['role' => TeamRoles::PLAYER]);
        }
        $teamB->users()->attach($athletes[0], ['role' => TeamRoles::PLAYER]);
        $this->log($athletes[0], $plan, $item, $teamA);
        Sanctum::actingAs($coach);
        $first = $this->getJson($this->endpoint.'?plan_id='.$plan->id)->assertOk()
            ->assertJsonPath('data.summary.athletes', 25)->assertJsonPath('data.summary.reported', 1)
            ->assertJsonPath('data.summary.missing', 24)->assertJsonPath('data.pagination.last_page', 2)
            ->assertJsonCount(20, 'data.rows')->json('data.rows');
        $second = $this->getJson($this->endpoint.'?plan_id='.$plan->id.'&page=2')->assertOk()->assertJsonCount(5, 'data.rows')->json('data.rows');
        $this->assertCount(25, collect([...$first, ...$second])->pluck('id')->unique());
        $this->getJson($this->endpoint.'?plan_id='.$plan->id.'&week=2026-11-16')->assertOk()->assertJsonPath('data.summary.sessions', 0);
        // The web endpoint uses exactly the same audience and comparison service.
        $this->actingAs($coach)->getJson(route('auth.trainer-cockpit.plan-evaluation', ['plan_id' => $plan->id]))
            ->assertOk()->assertJsonPath('data.summary.athletes', 25);
    }

    public function test_coach_sees_only_authorized_recipients_and_not_other_team_logs_for_the_same_athlete(): void
    {
        $coach = User::factory()->create();
        $otherCoach = User::factory()->create();
        $team = $this->team($coach);
        $foreign = $this->team($otherCoach);
        $own = User::factory()->create(['name' => 'Visible Athlete']);
        $hidden = User::factory()->create(['name' => 'Hidden Athlete']);
        $team->users()->attach($own, ['role' => TeamRoles::PLAYER]);
        $foreign->users()->attach([$own->id => ['role' => TeamRoles::PLAYER], $hidden->id => ['role' => TeamRoles::PLAYER]]);
        [$plan, $item] = $this->plan($coach, $team);
        $plan->assignments()->create(['team_id' => $foreign->id, 'permission' => 'read']);
        $visibleLog = $this->log($own, $plan, $item, $team);
        $foreignLog = $this->log($own, $plan, $item, $foreign);
        $foreignLog->update(['notes' => 'Secret other club report']);
        $this->log($hidden, $plan, $item, $foreign);
        $privateLog = $this->log($own, $plan, $item, $team, ['privacy_scope' => 'private']);
        [$hiddenPlan] = $this->plan($otherCoach, $foreign);
        Sanctum::actingAs($coach);
        $this->getJson($this->endpoint.'?plan_id='.$plan->id)->assertOk()->assertJsonCount(1, 'data.rows')
            ->assertJsonPath('data.rows.0.id', $own->id)->assertJsonCount(1, 'data.rows.0.sessions.0.reports')
            ->assertJsonPath('data.rows.0.sessions.0.reports.0.id', $visibleLog->id)
            ->assertDontSee('Hidden Athlete')->assertDontSee('Secret other club report');
        $this->getJson($this->endpoint.'?plan_id='.$hiddenPlan->id)->assertNotFound();
        foreach ([$foreignLog, $privateLog] as $log) {
            $this->postJson($this->endpoint.'/logs/'.$log->id.'/feedback', ['body' => 'Not allowed'])->assertNotFound();
        }
        $this->assertDatabaseCount('training_log_feedback', 0);
        $this->actingAs($coach)->getJson(route('auth.trainer-cockpit.plan-evaluation', ['plan_id' => $hiddenPlan->id]))->assertNotFound();
    }

    public function test_direct_clients_are_limited_to_the_assigned_plan_and_revocation_takes_effect(): void
    {
        $coach = User::factory()->create();
        $this->team($coach); // Gives trainer capability without making the client a team member.
        $athlete = User::factory()->create();
        $otherCoach = User::factory()->create();
        $this->team($otherCoach);
        [$plan, $item] = $this->plan($coach);
        $assignment = $plan->assignments()->create(['user_id' => $athlete->id, 'permission' => 'read']);
        $log = $this->log($athlete, $plan, $item);
        [$otherPlan, $otherItem] = $this->plan($otherCoach);
        $otherPlan->assignments()->create(['user_id' => $athlete->id, 'permission' => 'read']);
        $otherLog = $this->log($athlete, $otherPlan, $otherItem);
        Sanctum::actingAs($coach);
        $this->getJson($this->endpoint.'?plan_id='.$plan->id)->assertOk()->assertJsonPath('data.rows.0.id', $athlete->id);
        $this->postJson($this->endpoint.'/logs/'.$log->id.'/feedback', ['body' => 'Bitte die Wiederholungen ergänzen.'])->assertCreated();
        $this->postJson($this->endpoint.'/logs/'.$otherLog->id.'/feedback', ['body' => 'Not allowed'])->assertNotFound();
        $assignment->delete();
        $this->getJson($this->endpoint.'?plan_id='.$plan->id)->assertNotFound();
        $this->postJson($this->endpoint.'/logs/'.$log->id.'/feedback', ['body' => 'No longer allowed'])->assertNotFound();
    }

    public function test_set_comparison_keeps_original_plan_and_does_not_treat_skipped_or_missing_sets_as_completed(): void
    {
        $coach = User::factory()->create();
        $team = $this->team($coach);
        $athlete = User::factory()->create();
        $team->users()->attach($athlete, ['role' => TeamRoles::PLAYER]);
        [$plan, $item] = $this->plan($coach, $team);
        $log = $this->log($athlete, $plan, $item, $team, ['wellness' => ['pain' => 3], 'plan_snapshot' => ['metrics' => $item->metrics]]);
        $log->entries()->create(['title' => 'Liegestütze', 'sets' => 1, 'reps' => 12, 'metrics' => ['exercise_key' => 'pushups', 'set_index' => 1, 'completed' => true]]);
        $log->entries()->create(['title' => 'Liegestütze', 'sets' => 1, 'reps' => 20, 'metrics' => ['exercise_key' => 'pushups', 'set_index' => 2, 'completed' => false, 'skip_reason' => 'Schmerzen']]);
        $log->entries()->create(['title' => 'Ersatz Kniebeuge', 'sets' => 1, 'reps' => 8, 'metrics' => ['exercise_key' => 'substitute', 'substituted_for' => 'squats', 'completed' => true]]);
        $log->entries()->create(['title' => 'Extra exercise', 'sets' => 1, 'reps' => 5]);
        $item->update(['metrics' => ['planned_exercises' => []]]);
        Sanctum::actingAs($coach);
        $report = $this->getJson($this->endpoint.'?plan_id='.$plan->id)->assertOk()->json('data.rows.0.sessions.0.reports.0');
        $this->assertTrue($report['snapshot']);
        $this->assertCount(3, $report['exercises'][0]['planned']);
        $this->assertSame(20, $report['exercises'][0]['planned'][0]['reps']);
        $this->assertSame(12, $report['exercises'][0]['actual'][0]['reps']);
        $this->assertFalse($report['exercises'][0]['actual'][1]['completed']);
        $this->assertCount(2, $report['exercises'][0]['actual']);
        $this->assertSame('Ersatz Kniebeuge', $report['exercises'][1]['actual'][0]['title']);
        $this->assertSame([], $report['exercises'][2]['planned']);
        $this->assertSame(3, $report['pain']);
    }

    public function test_reply_closes_pending_feedback_and_a_new_athlete_message_reopens_it(): void
    {
        $coach = User::factory()->create();
        $team = $this->team($coach);
        $athlete = User::factory()->create();
        $team->users()->attach($athlete, ['role' => TeamRoles::PLAYER]);
        [$plan, $item] = $this->plan($coach, $team);
        $log = $this->log($athlete, $plan, $item, $team);
        Sanctum::actingAs($coach);
        $url = $this->endpoint.'?plan_id='.$plan->id.'&open_only=1';
        $this->getJson($url)->assertOk()->assertJsonPath('data.summary.feedback_open', 1)->assertJsonCount(1, 'data.rows');
        $this->postJson($this->endpoint.'/logs/'.$log->id.'/feedback', ['body' => 'Danke für deine Rückmeldung.'])->assertCreated();
        $this->getJson($url)->assertOk()->assertJsonPath('data.summary.feedback_open', 0)->assertJsonCount(0, 'data.rows');
        app(TrainingFeedbackService::class)->create($athlete, $log->fresh(), 'Kannst du die Übung anpassen?');
        $this->getJson($url)->assertOk()->assertJsonPath('data.summary.feedback_open', 1)->assertJsonCount(1, 'data.rows')
            ->assertJsonCount(2, 'data.rows.0.sessions.0.reports.0.feedback');
    }

    public function test_team_assignment_removal_and_explicit_club_denial_hide_athletes(): void
    {
        $coach = User::factory()->create();
        $team = $this->team($coach);
        $this->team($coach);
        $athlete = User::factory()->create();
        $team->users()->attach($athlete, ['role' => TeamRoles::PLAYER]);
        [$plan, $item] = $this->plan($coach, $team);
        $log = $this->log($athlete, $plan, $item, $team);
        Sanctum::actingAs($coach);
        $team->users()->detach($athlete);
        $this->getJson($this->endpoint.'?plan_id='.$plan->id)->assertOk()->assertJsonCount(0, 'data.rows');
        $this->postJson($this->endpoint.'/logs/'.$log->id.'/feedback', ['body' => 'No access'])->assertNotFound();
        $team->users()->attach($athlete, ['role' => TeamRoles::PLAYER]);
        $team->club->users()->attach($coach, ['role' => 'member', 'membership_status' => 'active', 'permission_overrides' => [ClubPermissions::TRAINER_COCKPIT_VIEW => false]]);
        $this->getJson($this->endpoint.'?plan_id='.$plan->id)->assertNotFound();
    }

    public function test_non_trainers_cannot_read_or_reply(): void
    {
        $coach = User::factory()->create();
        $team = $this->team($coach);
        $athlete = User::factory()->create();
        $team->users()->attach($athlete, ['role' => TeamRoles::PLAYER]);
        [$plan, $item] = $this->plan($coach, $team);
        $log = $this->log($athlete, $plan, $item, $team);
        Sanctum::actingAs($athlete);
        $this->getJson($this->endpoint.'?plan_id='.$plan->id)->assertForbidden();
        $this->postJson($this->endpoint.'/logs/'.$log->id.'/feedback', ['body' => 'No access'])->assertForbidden();
    }

    public function test_partial_report_from_mobile_is_compared_and_can_be_answered_on_web(): void
    {
        $coach = User::factory()->create();
        $team = $this->team($coach);
        $athlete = User::factory()->create();
        $team->users()->attach($athlete, ['role' => TeamRoles::PLAYER]);
        [$plan, $item] = $this->plan($coach, $team);
        Sanctum::actingAs($athlete);
        $logId = $this->postJson('/api/v1/training/logs', [
            'title' => 'Teilweise geschafft', 'training_plan_item_id' => $item->id,
            'status' => 'partial', 'privacy_scope' => 'trainer', 'duration_minutes' => 18,
            'wellness' => ['pain' => 3],
            'entries' => [
                ['title' => 'Liegestütze', 'exercise_key' => 'pushups', 'set_index' => 1, 'reps' => 12, 'completed' => true],
                ['title' => 'Liegestütze', 'exercise_key' => 'pushups', 'set_index' => 2, 'reps' => 20, 'completed' => false, 'skip_reason' => 'Schmerzen'],
            ],
        ])->assertCreated()->json('data.id');
        Sanctum::actingAs($coach);
        $this->getJson($this->endpoint.'?plan_id='.$plan->id)->assertOk()
            ->assertJsonPath('data.summary.reported', 1)
            ->assertJsonPath('data.rows.0.sessions.0.reports.0.status', 'partial')
            ->assertJsonPath('data.rows.0.sessions.0.reports.0.duration_minutes', 18)
            ->assertJsonPath('data.rows.0.sessions.0.reports.0.exercises.0.planned.0.reps', 20)
            ->assertJsonPath('data.rows.0.sessions.0.reports.0.exercises.0.actual.0.reps', 12)
            ->assertJsonPath('data.rows.0.sessions.0.reports.0.exercises.0.actual.1.completed', false);
        $this->actingAs($coach)->postJson(route('auth.trainer-cockpit.plan-evaluation.feedback', $logId), [
            'body' => 'Danke. Ich passe deine nächste Einheit an.',
        ])->assertCreated();
        $this->getJson($this->endpoint.'?plan_id='.$plan->id)->assertOk()
            ->assertJsonPath('data.summary.feedback_open', 0)
            ->assertJsonPath('data.rows.0.sessions.0.reports.0.feedback.0.body', 'Danke. Ich passe deine nächste Einheit an.');
        $audit = Activity::where('type', 'training.feedback_case_file.viewed')->latest('id')->firstOrFail();
        $this->assertSame([$logId], $audit->data['training_log_ids']);
        $this->assertStringNotContainsString('Danke.', json_encode($audit->data));
    }

    public function test_group_plan_respects_scoped_coach_role_and_individual_assignment_mode(): void
    {
        $owner = User::factory()->create();
        $coach = User::factory()->create();
        $club = Club::factory()->create(['owner_id' => $owner->id]);
        $group = ClubTrainingGroup::create(['club_id' => $club->id, 'name' => 'Gruppe']);
        $team = Team::factory()->create(['club_id' => $club->id, 'club_training_group_id' => $group->id]);
        $foreign = Team::factory()->create(['club_id' => $club->id, 'club_training_group_id' => $group->id]);
        $club->users()->attach($coach, ['role' => 'member', 'membership_status' => 'active']);
        $role = ClubRoleDefinition::create(['club_id' => $club->id, 'key' => 'evaluation_coach', 'name' => 'Trainer',
            'permissions' => [ClubPermissions::TRAINER_COCKPIT_VIEW], 'is_active' => true]);
        ClubRoleAssignment::create(['club_id' => $club->id, 'club_role_definition_id' => $role->id,
            'user_id' => $coach->id, 'scope_type' => 'team', 'scope_id' => $team->id,
            'scope_key' => 'team:'.$team->id, 'assigned_by' => $owner->id]);
        $athletes = User::factory()->count(3)->create();
        $team->users()->attach([$athletes[0]->id => ['role' => TeamRoles::PLAYER], $athletes[1]->id => ['role' => TeamRoles::PLAYER]]);
        $foreign->users()->attach($athletes[2], ['role' => TeamRoles::PLAYER]);
        [$plan, $item] = $this->plan($owner);
        $plan->update(['club_training_group_id' => $group->id]);
        $ownLog = $this->log($athletes[0], $plan, $item, $team);
        $foreignLog = $this->log($athletes[2], $plan, $item, $foreign);
        Sanctum::actingAs($coach);
        $this->getJson($this->endpoint.'?plan_id='.$plan->id)->assertOk()->assertJsonPath('data.summary.athletes', 2);
        $this->postJson($this->endpoint.'/logs/'.$ownLog->id.'/feedback', ['body' => 'Meine berechtigte Rückmeldung.'])->assertCreated();
        $this->postJson($this->endpoint.'/logs/'.$foreignLog->id.'/feedback', ['body' => 'Not allowed'])->assertNotFound();
        // A group plan restricted to selected people must not expose the rest of a team.
        $plan->update(['settings' => ['team_mode' => 'individual']]);
        $plan->assignments()->create(['user_id' => $athletes[0]->id, 'permission' => 'read']);
        $plan->assignments()->create(['user_id' => $athletes[2]->id, 'permission' => 'read']);
        $this->getJson($this->endpoint.'?plan_id='.$plan->id)->assertOk()->assertJsonCount(1, 'data.rows')
            ->assertJsonPath('data.rows.0.id', $athletes[0]->id);
    }
}
