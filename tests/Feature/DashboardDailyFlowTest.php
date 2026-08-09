<?php

namespace Tests\Feature;

use App\Models\File;
use App\Models\NutritionGoal;
use App\Models\NutritionMeal;
use App\Models\SportRoute;
use App\Models\TrainingLog;
use App\Models\TrainingPlan;
use App\Models\TrainingPlanItem;
use App\Models\User;
use App\Services\AthleteDailyFlowService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia as Assert;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class DashboardDailyFlowTest extends TestCase
{
    use RefreshDatabase;

    public function test_dashboard_exposes_daily_flow_for_athlete_day(): void
    {
        $this->travelTo(now()->setDate(2026, 6, 1)->setTime(10, 0));

        $user = User::factory()->create();

        TrainingLog::query()->create([
            'user_id' => $user->id,
            'created_by' => $user->id,
            'title' => 'Morgenlauf',
            'sport_type' => 'running',
            'status' => 'completed',
            'performed_at' => now()->copy()->subHour(),
            'duration_minutes' => 35,
            'distance_meters' => 7200,
        ]);

        File::query()->create([
            'user_id' => $user->id,
            'display_name' => 'Trainingsplan.pdf',
            'path' => 'uploads/trainingsplan.pdf',
            'type' => 'application/pdf',
            'size' => 4096,
        ]);

        NutritionMeal::query()->create([
            'user_id' => $user->id,
            'eaten_on' => '2026-06-01',
            'meal_type' => 'breakfast',
            'title' => 'Recovery Bowl',
            'calories' => 640,
            'protein_g' => 32,
            'carbs_g' => 85,
            'fat_g' => 14,
            'water_ml' => 800,
            'source' => 'manual',
        ]);

        SportRoute::query()->create([
            'user_id' => $user->id,
            'title' => 'Parkrunde',
            'sport_type' => 'running',
            'visibility' => 'private',
            'status' => 'planned',
            'distance_meters' => 5000,
            'waypoints' => [
                ['lat' => 52.52, 'lng' => 13.405],
                ['lat' => 52.53, 'lng' => 13.415],
            ],
        ]);

        $this->actingAs($user)
            ->get(route('auth.dashboard'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Auth/Dashboard/Index')
                ->has('dashboard.daily_flow.steps', 5)
                ->where('dashboard.daily_flow.steps.0.key', 'training')
                ->where('dashboard.daily_flow.steps.1.key', 'route')
                ->where('dashboard.daily_flow.steps.2.key', 'nutrition')
                ->where('dashboard.daily_flow.steps.3.key', 'hydration')
                ->where('dashboard.daily_flow.steps.4.key', 'reminders')
                ->where('dashboard.daily_flow.steps.0.progress', 78)
                ->where('dashboard.daily_flow.steps.1.body', 'Parkrunde')
                ->where('dashboard.daily_flow.steps.2.body', '1 Mahlzeiten heute')
                ->where('dashboard.daily_flow.steps.3.body', '800 ml getrunken')
                ->where('dashboard.daily_flow.score', 46)
            );
    }

    public function test_daily_flow_widget_can_be_saved_in_dashboard_preferences(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->patch(route('auth.dashboard.preferences.update'), [
                'widget_keys' => ['daily_flow', 'training', 'nutrition'],
            ])
            ->assertNoContent();

        $this->assertSame(['daily_flow', 'training', 'nutrition'], $user->refresh()->dashboard_widget_keys);
    }

    public function test_mobile_api_exposes_daily_flow_contract(): void
    {
        $this->travelTo(now()->setDate(2026, 6, 1)->setTime(10, 0));

        $user = User::factory()->create();

        TrainingLog::query()->create([
            'user_id' => $user->id,
            'created_by' => $user->id,
            'title' => 'Morgenlauf',
            'sport_type' => 'running',
            'status' => 'completed',
            'performed_at' => now()->copy()->subHour(),
            'duration_minutes' => 35,
            'distance_meters' => 7200,
        ]);

        File::query()->create([
            'user_id' => $user->id,
            'display_name' => 'Trainingsplan.pdf',
            'path' => 'uploads/trainingsplan.pdf',
            'type' => 'application/pdf',
            'size' => 4096,
        ]);

        Sanctum::actingAs($user);

        $this->getJson('/api/v1/dashboard/daily-flow')
            ->assertOk()
            ->assertJsonPath('data.steps.0.key', 'training')
            ->assertJsonPath('data.steps.1.key', 'route')
            ->assertJsonPath('data.steps.2.key', 'nutrition')
            ->assertJsonPath('data.steps.3.key', 'hydration')
            ->assertJsonPath('data.steps.4.key', 'reminders')
            ->assertJsonPath('data.steps.0.progress', 78)
            ->assertJsonPath('data.files.count', 1)
            ->assertJsonPath('data.files.bytes', 4096)
            ->assertJsonPath('data.mobile_context.shell.navigation', 'bottom_tabs')
            ->assertJsonPath('data.mobile_context.shell.primary_action', 'start_training')
            ->assertJsonPath('data.mobile_context.quick_actions.0.key', 'start_training')
            ->assertJsonPath('data.mobile_context.quick_actions.1.key', 'scan_meal')
            ->assertJsonPath('data.mobile_context.quick_actions.1.required_permissions.0', 'camera')
            ->assertJsonPath('data.mobile_context.quick_actions.2.key', 'start_tracking')
            ->assertJsonPath('data.mobile_context.quick_actions.2.required_permissions.1', 'location_background')
            ->assertJsonPath('data.mobile_context.offline_status.retry_header', 'Idempotency-Key')
            ->assertJsonStructure([
                'data' => [
                    'score',
                    'files' => ['count', 'bytes'],
                    'summary',
                    'coach_note',
                    'mobile_context' => [
                        'shell',
                        'quick_actions',
                        'offline_status',
                        'permission_prompts',
                    ],
                    'steps' => [
                        '*' => ['key', 'title', 'body', 'meta', 'progress', 'href', 'cta', 'icon'],
                    ],
                ],
            ]);
    }

    public function test_daily_flow_personalizes_goals_and_proves_weekly_plan_continuity(): void
    {
        $this->travelTo(now()->setDate(2026, 6, 3)->setTime(10, 0));

        $user = User::factory()->create();
        NutritionGoal::query()->create([
            'user_id' => $user->id,
            'daily_calories_target' => 2800,
            'body_weight_kg' => 80,
            'water_target_mode' => 'auto',
        ]);
        $plan = TrainingPlan::query()->create([
            'created_by' => $user->id,
            'title' => 'Wettkampfwoche',
            'status' => 'published',
        ]);
        $mondayItem = TrainingPlanItem::query()->create([
            'training_plan_id' => $plan->id,
            'title' => 'Montagslauf',
            'scheduled_at' => now()->copy()->startOfWeek()->addHours(8),
            'duration_minutes' => 45,
        ]);
        $todayItem = TrainingPlanItem::query()->create([
            'training_plan_id' => $plan->id,
            'title' => 'Tempolauf',
            'scheduled_at' => now()->copy()->setTime(17, 0),
            'duration_minutes' => 60,
        ]);
        TrainingPlanItem::query()->create([
            'training_plan_id' => $plan->id,
            'title' => 'Langer Lauf',
            'scheduled_at' => now()->copy()->endOfWeek()->subDays(2)->setTime(9, 0),
            'duration_minutes' => 45,
        ]);

        TrainingLog::query()->create([
            'user_id' => $user->id,
            'created_by' => $user->id,
            'training_plan_id' => $plan->id,
            'training_plan_item_id' => $mondayItem->id,
            'title' => 'Montagslauf erledigt',
            'status' => 'completed',
            'performed_at' => now()->copy()->startOfWeek()->addHours(8),
            'duration_minutes' => 45,
        ]);
        TrainingLog::query()->create([
            'user_id' => $user->id,
            'created_by' => $user->id,
            'title' => 'Mobility',
            'status' => 'completed',
            'performed_at' => now()->copy()->subHour(),
            'duration_minutes' => 60,
        ]);

        Sanctum::actingAs($user);

        $this->getJson('/api/v1/dashboard/daily-flow')
            ->assertOk()
            ->assertJsonPath('data.goals.training_minutes', 60)
            ->assertJsonPath('data.goals.calories', 2800)
            ->assertJsonPath('data.goals.water_ml', 3150)
            ->assertJsonPath('data.goals.water_mode', 'auto')
            ->assertJsonPath('data.week.status', 'on_track')
            ->assertJsonPath('data.week.planned_sessions', 3)
            ->assertJsonPath('data.week.due_sessions', 1)
            ->assertJsonPath('data.week.completed_planned_sessions', 1)
            ->assertJsonPath('data.week.completed_sessions', 2)
            ->assertJsonPath('data.week.adherence_percent', 100)
            ->assertJsonPath('data.week.days.0.state', 'completed')
            ->assertJsonPath('data.week.days.2.state', 'partial')
            ->assertJsonPath('data.primary_action.key', 'complete_training')
            ->assertJsonPath('data.primary_action.api_target', '/api/v1/training/logs')
            ->assertJsonPath('data.primary_action.href', route('auth.training.logs.create', ['plan_item_id' => $todayItem->id]));
    }

    public function test_daily_flow_respects_arabic_locale_and_does_not_leak_private_notes(): void
    {
        $user = User::factory()->create();
        TrainingLog::query()->create([
            'user_id' => $user->id,
            'created_by' => $user->id,
            'title' => 'Privates Training',
            'status' => 'completed',
            'performed_at' => now()->subHour(),
            'duration_minutes' => 30,
            'notes' => 'PRIVATE-COACH-NOTE',
            'metrics' => ['private_marker' => 'PRIVATE-METRIC'],
        ]);

        Sanctum::actingAs($user);

        $response = $this->withHeader('X-Locale', 'ar')
            ->getJson('/api/v1/dashboard/daily-flow')
            ->assertOk()
            ->assertHeader('Content-Language', 'ar')
            ->assertHeader('X-Airmius-Text-Direction', 'rtl')
            ->assertJsonPath('data.steps.0.title', 'التدريب')
            ->assertJsonPath('data.primary_action.label', 'إضافة الماء');

        $response->assertDontSee('PRIVATE-COACH-NOTE');
        $response->assertDontSee('PRIVATE-METRIC');
    }

    public function test_daily_flow_query_budget_stays_bounded_with_a_visible_plan(): void
    {
        $user = User::factory()->create();
        $plan = TrainingPlan::query()->create([
            'created_by' => $user->id,
            'title' => 'Budgetplan',
            'status' => 'published',
        ]);
        TrainingPlanItem::query()->create([
            'training_plan_id' => $plan->id,
            'title' => 'Budgeteinheit',
            'scheduled_at' => now()->addDay(),
            'duration_minutes' => 45,
        ]);

        DB::flushQueryLog();
        DB::enableQueryLog();

        app(AthleteDailyFlowService::class)->forUser($user);
        $queryCount = count(DB::getQueryLog());

        DB::disableQueryLog();

        $this->assertLessThanOrEqual(12, $queryCount, "Daily flow used {$queryCount} queries");
    }

    public function test_daily_flow_ui_and_localization_contracts_are_complete(): void
    {
        $dashboard = (string) file_get_contents(resource_path('js/Pages/Auth/Dashboard/Index.vue'));
        $widget = (string) file_get_contents(resource_path('js/Components/Dashboard/DashboardDailyFlowWidget.vue'));

        $this->assertStringContainsString("import DashboardDailyFlowWidget from '@/Components/Dashboard/DashboardDailyFlowWidget.vue'", $dashboard);
        $this->assertStringContainsString("v-if=\"isWidgetVisible('daily_flow')\"", $dashboard);
        $this->assertStringContainsString(':daily-flow="dailyFlow"', $dashboard);
        $this->assertStringContainsString('week.days', $widget);
        $this->assertStringContainsString('primaryAction', $widget);

        $reference = Arr::dot(require lang_path('de/athlete_today.php'));
        foreach (['en', 'fr', 'ar'] as $locale) {
            $catalog = Arr::dot(require lang_path("{$locale}/athlete_today.php"));
            $this->assertSame(array_keys($reference), array_keys($catalog), "athlete_today:{$locale} key parity");

            foreach ($reference as $key => $source) {
                preg_match_all('/:[A-Za-z_][A-Za-z0-9_]*/', (string) $source, $sourceMatches);
                preg_match_all('/:[A-Za-z_][A-Za-z0-9_]*/', (string) $catalog[$key], $translatedMatches);
                $this->assertSame($sourceMatches[0], $translatedMatches[0], "athlete_today:{$locale} placeholder parity for {$key}");
            }
        }
    }
}
