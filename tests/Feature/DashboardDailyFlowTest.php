<?php

namespace Tests\Feature;

use App\Models\NutritionMeal;
use App\Models\File;
use App\Models\SportRoute;
use App\Models\TrainingLog;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
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
}
