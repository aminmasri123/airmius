<?php

namespace Tests\Feature;

use App\Models\Notification;
use App\Models\NutritionGoal;
use App\Models\NutritionMeal;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class WaterReminderTest extends TestCase
{
    use RefreshDatabase;

    public function test_reminders_require_opt_in_and_are_deduplicated(): void
    {
        $this->travelTo(now('UTC')->setDate(2026, 9, 20)->setTime(10, 5));
        $user = User::factory()->create(['timezone' => 'UTC', 'language' => 'de']);
        $goal = NutritionGoal::query()->create([
            'user_id' => $user->id,
            'goal_type' => 'maintain',
            'diet_style' => 'balanced',
            'water_target_ml' => 2000,
            'water_target_mode' => 'manual',
        ]);

        $this->artisan('airmius:send-water-reminders')->assertSuccessful();
        $this->assertDatabaseCount('notifications', 0);

        $goal->update(['water_reminders_per_day' => 2]);
        $this->artisan('airmius:send-water-reminders')->assertSuccessful();
        $this->artisan('airmius:send-water-reminders')->assertSuccessful();

        $this->assertSame(1, Notification::query()->where('type', 'nutrition.water.reminder')->count());
        $this->assertSame('Zeit für eine Trinkpause', Notification::query()->first()->data['title']);
    }

    public function test_recent_water_entry_suppresses_the_due_reminder(): void
    {
        $this->travelTo(now('UTC')->setDate(2026, 9, 20)->setTime(10, 5));
        $user = User::factory()->create(['timezone' => 'UTC']);
        NutritionGoal::query()->create([
            'user_id' => $user->id,
            'goal_type' => 'maintain',
            'diet_style' => 'balanced',
            'water_target_ml' => 2000,
            'water_target_mode' => 'manual',
            'water_reminders_per_day' => 2,
        ]);
        NutritionMeal::query()->create([
            'user_id' => $user->id,
            'eaten_on' => '2026-09-20',
            'meal_type' => 'drink',
            'title' => 'Water',
            'calories' => 0,
            'protein_g' => 0,
            'carbs_g' => 0,
            'fat_g' => 0,
            'water_ml' => 250,
            'source' => 'manual',
        ]);

        $this->artisan('airmius:send-water-reminders')->assertSuccessful();
        $this->assertDatabaseCount('notifications', 0);
    }

    public function test_weight_estimate_and_reminder_preferences_are_returned_to_mobile(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $this->patchJson('/api/v1/nutrition/goal', [
            'goal_type' => 'maintain',
            'diet_style' => 'balanced',
            'body_weight_kg' => 70,
            'water_target_mode' => 'auto',
            'water_reminders_per_day' => 2,
            'water_reminder_start_hour' => 10,
            'water_reminder_end_hour' => 16,
            'water_reminder_timezone' => 'Europe/Berlin',
        ])->assertOk()
            ->assertJsonPath('data.water_reminders_per_day', 2)
            ->assertJsonPath('data.body_weight_kg', 70);
        $this->assertSame('Europe/Berlin', $user->fresh()->timezone);

        $this->getJson('/api/v1/nutrition?date=2026-09-20')
            ->assertOk()
            ->assertJsonPath('data.water_recommendation.uses_weight', true)
            ->assertJsonPath('data.water_recommendation.base_ml', 2300)
            ->assertJsonPath('data.goal.water_reminders_per_day', 2);
    }

    public function test_three_reminders_from_eight_to_twenty_one_can_be_saved(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $this->patchJson('/api/v1/nutrition/goal', [
            'goal_type' => 'maintain',
            'diet_style' => 'balanced',
            'daily_calories_target' => 2200,
            'protein_target_g' => 120,
            'carbs_target_g' => 260,
            'fat_target_g' => 75,
            'water_target_ml' => 2500,
            'water_target_mode' => 'auto',
            'water_reminders_per_day' => 3,
            'water_reminder_start_hour' => 8,
            'water_reminder_end_hour' => 21,
            'water_reminder_timezone' => '+02:00',
        ])->assertOk()
            ->assertJsonPath('data.water_reminders_per_day', 3)
            ->assertJsonPath('data.water_reminder_start_hour', 8)
            ->assertJsonPath('data.water_reminder_end_hour', 21);

        $this->assertSame('+02:00', $user->fresh()->timezone);
    }

    public function test_reaching_the_daily_target_stops_reminders(): void
    {
        $this->travelTo(now('UTC')->setDate(2026, 9, 20)->setTime(10, 5));
        $user = User::factory()->create(['timezone' => 'UTC']);
        NutritionGoal::query()->create([
            'user_id' => $user->id,
            'goal_type' => 'maintain',
            'diet_style' => 'balanced',
            'water_target_ml' => 2000,
            'water_target_mode' => 'manual',
            'water_reminders_per_day' => 2,
        ]);
        NutritionMeal::query()->create([
            'user_id' => $user->id,
            'eaten_on' => '2026-09-20',
            'meal_type' => 'drink',
            'title' => 'Water',
            'calories' => 0,
            'protein_g' => 0,
            'carbs_g' => 0,
            'fat_g' => 0,
            'water_ml' => 2000,
            'source' => 'manual',
        ]);

        $this->artisan('airmius:send-water-reminders')->assertSuccessful();
        $this->assertDatabaseCount('notifications', 0);
    }
}
