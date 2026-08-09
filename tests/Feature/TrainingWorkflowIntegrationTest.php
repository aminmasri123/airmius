<?php

namespace Tests\Feature;

use App\Models\Club;
use App\Models\Notification;
use App\Models\Team;
use App\Models\TrainingLog;
use App\Models\TrainingPlan;
use App\Models\TrainingPlanItem;
use App\Models\User;
use App\Support\TeamRoles;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class TrainingWorkflowIntegrationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config(['broadcasting.default' => 'log']);
    }

    public function test_plan_log_and_feedback_form_one_web_mobile_workflow(): void
    {
        [$coach, $athlete, $team, $plan, $item] = $this->assignedPlan();
        $athlete->forceFill(['language' => 'ar'])->save();

        $this->actingAs($athlete)->post(route('auth.training.logs.store'), [
            'training_plan_item_id' => $item->id,
            'title' => 'Kontrollierter Tempolauf',
            'sport_type' => 'laufen',
            'status' => 'completed',
            'privacy_scope' => 'trainer',
            'performed_at' => now()->toIso8601String(),
            'duration_minutes' => 48,
            'distance_km' => 8.4,
            'intensity' => 'mittel',
            'wellness' => ['rpe' => 7, 'energy' => 6, 'pain' => 0, 'sleep_hours' => 7.5],
            'entries' => [[
                'title' => '4 x 1 km',
                'reps' => 4,
                'distance_km' => 4,
                'intensity' => 'RPE 8',
            ]],
        ])->assertRedirect();

        $log = TrainingLog::query()->with('entries')->latest('id')->firstOrFail();

        $this->assertSame($athlete->id, $log->user_id);
        $this->assertSame($plan->id, $log->training_plan_id);
        $this->assertSame($item->id, $log->training_plan_item_id);
        $this->assertSame('planned_training', $log->metrics['source_kind']);
        $this->assertSame(8400, $log->distance_meters);
        $this->assertSame(4000, $log->entries->first()->distance_meters);

        Sanctum::actingAs($coach);

        $this->postJson("/api/v1/training/logs/{$log->id}/feedback", [
            'body' => 'Sehr sauber gesteuert. Die nächste Einheit bleibt locker.',
        ])
            ->assertCreated()
            ->assertJsonPath('data.role', 'trainer')
            ->assertJsonPath('data.training_log_id', $log->id)
            ->assertJsonPath('data.author.id', $coach->id);

        Sanctum::actingAs($athlete);

        $this->getJson("/api/v1/training/logs/{$log->id}")
            ->assertOk()
            ->assertJsonPath('data.training_plan_id', $plan->id)
            ->assertJsonPath('data.training_plan_item_id', $item->id)
            ->assertJsonPath('data.feedbacks.0.role', 'trainer')
            ->assertJsonPath('data.feedbacks.0.body', 'Sehr sauber gesteuert. Die nächste Einheit bleibt locker.');

        $notification = Notification::query()
            ->where('user_id', $athlete->id)
            ->where('type', 'training.feedback')
            ->latest('id')
            ->firstOrFail();

        $this->assertSame('ar', $notification->data['locale']);
        $this->assertSame('server.training.notifications.feedback_title', $notification->data['i18n']['title_key']);
        $this->assertSame($log->id, $notification->data['training_log_id']);
    }

    public function test_mobile_log_visibility_respects_private_trainer_and_team_scopes(): void
    {
        [$coach, $athlete, $team] = $this->assignedPlan();
        $teammate = User::factory()->create();
        $team->users()->attach($teammate->id, ['role' => TeamRoles::PLAYER]);

        $private = $this->log($athlete, $team, 'Nur für mich', 'private');
        $trainer = $this->log($athlete, $team, 'Für das Trainerteam', 'trainer');
        $teamLog = $this->log($athlete, $team, 'Für das Team', 'team');

        Sanctum::actingAs($teammate);

        $this->getJson('/api/v1/training/logs?per_page=50')
            ->assertOk()
            ->assertJsonFragment(['id' => $teamLog->id, 'title' => 'Für das Team'])
            ->assertJsonMissing(['title' => 'Nur für mich'])
            ->assertJsonMissing(['title' => 'Für das Trainerteam']);

        Sanctum::actingAs($coach);

        $this->getJson('/api/v1/training/logs?per_page=50')
            ->assertOk()
            ->assertJsonFragment(['id' => $trainer->id, 'title' => 'Für das Trainerteam'])
            ->assertJsonFragment(['id' => $teamLog->id, 'title' => 'Für das Team'])
            ->assertJsonMissing(['title' => 'Nur für mich']);

        $this->getJson("/api/v1/training/logs/{$private->id}")->assertNotFound();
        $this->postJson("/api/v1/training/logs/{$private->id}/feedback", [
            'body' => 'Darf nicht gespeichert werden.',
        ])->assertForbidden();
    }

    public function test_plan_item_deep_link_is_server_validated_and_prefills_documentation(): void
    {
        [, $athlete, , $plan, $item] = $this->assignedPlan();

        $this->actingAs($athlete)
            ->get(route('auth.training.logs.create', ['plan_item_id' => $item->id]))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Auth/Dashboard/Training/LogCreate')
                ->where('prefillPlanItemId', $item->id)
                ->has('plans', 1)
                ->where('plans.0.id', $plan->id)
            );

        $foreignUser = User::factory()->create();

        $this->actingAs($foreignUser)
            ->get(route('auth.training.logs.create', ['plan_item_id' => $item->id]))
            ->assertNotFound();
    }

    /**
     * @return array{User, User, Team, TrainingPlan, TrainingPlanItem}
     */
    private function assignedPlan(): array
    {
        $coach = User::factory()->create(['name' => 'Mina Coach']);
        $athlete = User::factory()->create(['name' => 'Mira Runner']);
        $club = Club::factory()->create(['owner_id' => $coach->id]);
        $team = Team::factory()->create(['club_id' => $club->id, 'sport_type' => 'laufen']);
        $team->users()->attach($coach->id, ['role' => TeamRoles::COACH]);
        $team->users()->attach($athlete->id, ['role' => TeamRoles::PLAYER]);

        $plan = TrainingPlan::query()->create([
            'created_by' => $coach->id,
            'team_id' => $team->id,
            'title' => '10-km-Aufbau',
            'cadence' => 'weekly',
            'status' => 'published',
            'share_permission' => 'read',
        ]);
        $plan->assignments()->create([
            'user_id' => $athlete->id,
            'permission' => 'read',
        ]);
        $item = $plan->items()->create([
            'title' => 'Tempo-Intervalle',
            'sport_type' => 'laufen',
            'scheduled_at' => now(),
            'duration_minutes' => 50,
            'distance_meters' => 8000,
            'intensity' => 'mittel',
            'sort_order' => 1,
        ]);

        return [$coach, $athlete, $team, $plan, $item];
    }

    private function log(User $athlete, Team $team, string $title, string $privacyScope): TrainingLog
    {
        return TrainingLog::query()->create([
            'user_id' => $athlete->id,
            'created_by' => $athlete->id,
            'team_id' => $team->id,
            'title' => $title,
            'status' => 'completed',
            'performed_at' => now(),
            'metrics' => ['privacy_scope' => $privacyScope],
        ]);
    }
}
